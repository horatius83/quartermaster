<?php
declare(strict_types=1);

/** The fields and buttons of the catalog's rows and its page buttons, locked while the table redraws. */
const CATALOG_ROW_FIELDS = '#catalog-body input, #catalog-body textarea, #catalog-body select, #catalog-body button, #catalog-pager button';

/** The filters a request sends so its redraw matches the page. Changing a filter or page sends only these. */
const CATALOG_FILTERS = '[name=q], #cat-filter, #tag-filter';

/** What a save sends, so its redraw also stays on the page being looked at. */
const CATALOG_SAVE_INCLUDES = CATALOG_FILTERS . ', #catalog-page';

/** Every row is a set of inputs, so this also keeps the page light enough to edit. */
const CATALOG_PAGE_SIZE = 50;

/** The FROM and WHERE for the current campaign's system's items matching the filters, and its parameters. */
function catalog_query(string $search, string $cat, array $tags, ?int $id = null): array
{
    $sql = ' FROM items i JOIN categories cat ON cat.id = i.category_id WHERE i.ruleset = ?';
    $params = [campaign_ruleset()];
    if ($search !== '') {
        $sql .= ' AND i.name LIKE ?';
        $params[] = '%' . $search . '%';
    }
    if ($cat !== '') {
        $sql .= ' AND cat.slug = ?';
        $params[] = $cat;
    }
    if ($id !== null) {
        $sql .= ' AND i.id = ?';
        $params[] = $id;
    }
    foreach ($tags as $tag) {
        $sql .= ' AND EXISTS (SELECT 1 FROM item_tags t WHERE t.item_id = i.id AND t.tag = ?)';
        $params[] = $tag;
    }
    return [$sql, $params];
}

/** The columns a catalog row shows, with $query from catalog_query() and any LIMIT in $suffix. */
function catalog_select(array $query, string $suffix = ''): array
{
    [$sql, $params] = $query;
    return with_item_tags(q_all(
        'SELECT i.*, cat.name AS category, cat.slug AS category_slug,
                (SELECT COUNT(*) FROM inventory inv WHERE inv.item_id = i.id)
              + (SELECT COUNT(*) FROM store_stock st WHERE st.item_id = i.id)
              + (SELECT COUNT(*) FROM cart_items ci WHERE ci.item_id = i.id) AS uses'
        . $sql . ' ORDER BY cat.sort_order, i.name, i.id' . $suffix,
        $params
    ));
}

/** Catalog items matching the filters, with every one of $tags; with $id, only that item, if it matches. */
function catalog_rows(string $search, string $cat, array $tags, ?int $id = null): array
{
    return catalog_select(catalog_query($search, $cat, $tags, $id));
}

/**
 * One page of the catalog: what partials/catalog_body draws. A page past the end shows the last one, since
 * a delete or a filter can leave a page with nothing on it.
 */
function catalog_page(string $search, string $cat, array $tags, int $page): array
{
    $query = catalog_query($search, $cat, $tags);
    $total = (int)q_val('SELECT COUNT(*)' . $query[0], $query[1]);
    $pages = max(1, (int)ceil($total / CATALOG_PAGE_SIZE));
    $page = max(1, min($page, $pages));
    $offset = ($page - 1) * CATALOG_PAGE_SIZE;
    return [
        'items' => catalog_select($query, ' LIMIT ' . CATALOG_PAGE_SIZE . ' OFFSET ' . $offset),
        'page'  => $page,
        'pages' => $pages,
        'total' => $total,
        'first' => $offset + 1,
    ];
}

/** Redraw the table after a form or row save, staying on the page the DM was looking at. */
function catalog_redraw(): never
{
    fragment('partials/catalog_body', ['catalog' => catalog_page(...posted_filters(), page: post_int('page')), 'oob' => true]);
}

/** An item of the current campaign's system. Another system's reads as "not found", as a tab left open on another campaign might ask for one. */
function catalog_item(int $id): ?array
{
    return catalog_rows('', '', [], $id)[0] ?? null;
}

/** The page's filters as sent along with a catalog form or row save: search, category and tags, for catalog_rows(). */
function posted_filters(): array
{
    return [post_str('q', 100), post_str('cat', 30), filter_tags($_POST)];
}

/** The tags typed into a catalog form. A tag that's too long stops the save rather than being cut short. */
function posted_tags(): array
{
    $tags = parse_tags(post_str('tags'));
    foreach ($tags as $tag) {
        if (mb_strlen($tag) > MAX_TAG_LENGTH) {
            htmx_fail('Keep each tag to ' . MAX_TAG_LENGTH . ' characters. Separate tags with commas.');
        }
    }
    return $tags;
}

/** Have the page's tag filter fetch itself again once this response is swapped in, because the tags in use changed. */
function refresh_tag_filter(): void
{
    header('HX-Trigger-After-Swap: ' . json_encode(['qm-tags-changed' => true]));
}

function dm_catalog(): void
{
    require_dm();
    $search = get_str('q');
    $cat = get_str('cat', 30);
    $tags = filter_tags($_GET);
    if (htmx_target() === 'tag-filter') {
        fragment('partials/catalog_tag_filter', ['selected' => $tags]);
    }
    $catalog = catalog_page($search, $cat, $tags, (int)get_str('page', 9));
    if (htmx_target() === 'catalog-body') {
        fragment('partials/catalog_body', ['catalog' => $catalog, 'oob' => true]);
    }
    page('dm/catalog', [
        'active' => 'catalog',
        'catalog' => $catalog,
        'search' => $search,
        'cat'    => $cat,
        'tags'   => $tags,
    ], 'Item catalog');
}

function dm_catalog_create(): void
{
    require_dm();
    $name = post_str('name', 150);
    if ($name === '') {
        htmx_fail('Give the item a name.');
    }
    $ruleset = campaign_ruleset();
    $taken = "“{$name}” is already in the catalog for " . ruleset_label($ruleset) . '.';
    if (item_by_name($name, $ruleset)) {
        htmx_fail($taken);
    }
    $priceText = post_str('price', 40);
    $price = parse_price($priceText);
    if ($priceText !== '' && $price === null) {
        htmx_fail('Write prices like “15 gp”, “5 sp” or “1 gp 5 sp”.');
    }
    $tags = posted_tags();
    try {
        transaction(function () use ($name, $price, $tags, $ruleset) {
            $item = create_item($name, post_int('category_id'), post_float('weight'), $price ?? 0, post_str('description', 1000), $ruleset);
            set_item_tags((int)$item['id'], $tags);
        });
    } catch (PDOException $e) {
        // Someone added the name between the check above and the insert.
        if (!item_by_name($name, $ruleset)) {
            throw $e;
        }
        htmx_fail($taken);
    }
    toast("Added {$name} to the catalog.");
    $afterSwap = ['qm-reset' => ['form' => '#catalog-form']];
    if ($tags) {
        $afterSwap['qm-tags-changed'] = true;
    }
    header('HX-Trigger-After-Swap: ' . json_encode($afterSwap));
    catalog_redraw();
}

function dm_catalog_update(int $id): void
{
    require_dm();
    $item = catalog_item($id) ?? abort(404, 'No such item.');
    $name = post_str('name', 150) ?: $item['name'];
    $clash = item_by_name($name, $item['ruleset']);
    if ($clash && (int)$clash['id'] !== $id) {
        htmx_fail('Another ' . ruleset_label($item['ruleset']) . " item is already called “{$name}”.");
    }
    $price = parse_price(post_str('price', 40));
    if ($price === null) {
        htmx_fail('Write prices like “15 gp”, “5 sp” or “1 gp 5 sp”.');
    }
    $catId = category_by_id(post_int('category_id')) ? post_int('category_id') : (int)$item['category_id'];
    $description = post_str('description', 1000);
    // A row without the field (a tab opened before tags existed, say) leaves them alone.
    $tags = isset($_POST['tags']) ? posted_tags() : $item['tags'];
    $tagsChanged = $tags !== $item['tags'];
    transaction(function () use ($id, $name, $catId, $price, $description, $tags, $tagsChanged) {
        q(
            'UPDATE items SET name = ?, category_id = ?, weight = ?, price_cp = ?, description = ? WHERE id = ?',
            [$name, $catId, max(0, post_float('weight')), $price, $description === '' ? null : $description, $id]
        );
        if ($tagsChanged) {
            set_item_tags($id, $tags);
        }
    });
    if ($tagsChanged) {
        refresh_tag_filter();
    }
    $row = catalog_rows(...posted_filters(), id: $id)[0] ?? null;
    if (!$row) {
        // The edit took it out of the filtered table, which may now be empty, so redraw the table.
        toast("Saved {$name}. It no longer matches the filters, so it’s hidden.");
        header('HX-Retarget: #catalog-body');
        catalog_redraw();
    }
    toast("Saved {$name}.");
    fragment('partials/catalog_row', ['i' => $row]);
}

function dm_catalog_delete(int $id): void
{
    require_dm();
    $item = catalog_item($id) ?? abort(404, 'No such item.');
    if ((int)$item['uses'] > 0) {
        htmx_fail("{$item['name']} is in a pack, a store or a cart, so it can’t be deleted.");
    }
    q('DELETE FROM items WHERE id = ?', [$id]);
    if ($item['tags']) {
        refresh_tag_filter();
    }
    toast("Deleted {$item['name']}.");
    // The next page's first item moves up and the count changes, so the whole table is drawn again.
    catalog_redraw();
}

/** A name in the form the import compares, since DMs usually type a straight apostrophe where the data has a curly one. */
function srd_name_key(string $name): string
{
    return mb_strtolower(str_replace('’', "'", $name));
}

/** The bundled SRD items for a system in SRD_RULESETS: [name, category slug, weight, price, tags, description] rows. */
function srd_items(string $ruleset): array
{
    return require APP_DIR . "/data/srd-{$ruleset}.php";
}

/**
 * Load the SRD items for the current campaign's system. A name that system already has is skipped, so a DM's own
 * prices and descriptions are never overwritten.
 */
function dm_catalog_import_srd(): void
{
    require_dm();
    $ruleset = campaign_ruleset();
    // Setting the system copies the old catalog across, which would take all of these along if they were loaded first.
    if ((int)current_campaign()['ruleset_open']) {
        htmx_fail('Set this campaign’s system under Campaigns first.');
    }
    if (!in_array($ruleset, SRD_RULESETS, true)) {
        htmx_fail('There are no SRD items to load for ' . ruleset_label($ruleset) . '.');
    }
    // The button says which system it loads. Another tab may have switched to a campaign of a different one since.
    if (post_str('edition', 10) !== $ruleset) {
        htmx_fail('Nothing was added: you’re running a ' . ruleset_label($ruleset) . ' campaign now. Reload this page, then try again.');
    }
    $categoryIds = array_column(categories(), 'id', 'slug');
    $rows = srd_items($ruleset);
    // The database decides what counts as the same name, so its collation is what's asked here, not PHP.
    $taken = q_all('SELECT name FROM items WHERE ruleset = ?', [$ruleset]);
    $seen = array_flip(array_map(srd_name_key(...), array_column($taken, 'name')));
    $added = transaction(function () use ($ruleset, $categoryIds, $rows, $seen) {
        $added = 0;
        foreach ($rows as [$name, $slug, $weight, $price, $tags, $description]) {
            if (isset($seen[srd_name_key($name)])) {
                continue;
            }
            try {
                $item = create_item($name, (int)$categoryIds[$slug], (float)$weight, parse_price($price) ?? 0, $description, $ruleset);
            } catch (PDOException $e) {
                // The database's own rules call it taken after all (an accented spelling, or someone adding it just now).
                if (!item_by_name($name, $ruleset)) {
                    throw $e;
                }
                continue;
            }
            set_item_tags((int)$item['id'], $tags);
            $added++;
        }
        return $added;
    });
    $skipped = count($rows) - $added;
    toast("Added {$added} " . ruleset_label($ruleset) . ' SRD ' . ($added === 1 ? 'item' : 'items') . ($skipped ? " and skipped {$skipped} already in the catalog." : '.'));
    refresh_tag_filter();
    catalog_redraw();
}

/** <option>s for the item-name <datalist> on the give / stock forms. */
function dm_catalog_suggest(): void
{
    require_dm();
    $search = get_str('item', 150);
    $rows = $search === ''
        ? []
        : q_all(
            'SELECT i.name, i.weight, i.price_cp, cat.name AS category FROM items i
               JOIN categories cat ON cat.id = i.category_id
              WHERE i.name LIKE ? AND i.ruleset = ? ORDER BY i.name LIMIT 12',
            ['%' . $search . '%', campaign_ruleset()]
        );
    foreach ($rows as $r) {
        echo '<option value="' . e($r['name']) . '">' . e($r['category'] . ' · ' . coins((int)$r['price_cp']) . ' · ' . fmt_weight($r['weight'])) . '</option>';
    }
    exit;
}
