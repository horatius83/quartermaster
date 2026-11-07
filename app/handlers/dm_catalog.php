<?php
declare(strict_types=1);

/** The fields of the catalog's rows, locked while the table redraws. */
const CATALOG_ROW_FIELDS = '#catalog-body input, #catalog-body textarea, #catalog-body select';

/** Catalog items matching the filters, with every one of $tags; with $id, only that item, if it matches. */
function catalog_rows(string $search, string $cat, array $tags, ?int $id = null): array
{
    $sql = 'SELECT i.*, cat.name AS category, cat.slug AS category_slug,
                   (SELECT COUNT(*) FROM inventory inv WHERE inv.item_id = i.id)
                 + (SELECT COUNT(*) FROM store_stock st WHERE st.item_id = i.id)
                 + (SELECT COUNT(*) FROM cart_items ci WHERE ci.item_id = i.id) AS uses
              FROM items i JOIN categories cat ON cat.id = i.category_id WHERE 1 = 1';
    $params = [];
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
    return with_item_tags(q_all($sql . ' ORDER BY cat.sort_order, i.name', $params));
}

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
    if (htmx_target() === 'catalog-body') {
        fragment('partials/catalog_body', ['items' => catalog_rows($search, $cat, $tags)]);
    }
    if (htmx_target() === 'tag-filter') {
        fragment('partials/catalog_tag_filter', ['selected' => $tags]);
    }
    page('dm/catalog', [
        'active' => 'catalog',
        'items'  => catalog_rows($search, $cat, $tags),
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
    if (item_by_name($name)) {
        htmx_fail("“{$name}” is already in the catalog.");
    }
    $priceText = post_str('price', 40);
    $price = parse_price($priceText);
    if ($priceText !== '' && $price === null) {
        htmx_fail('Write prices like “15 gp”, “5 sp” or “1 gp 5 sp”.');
    }
    $tags = posted_tags();
    try {
        transaction(function () use ($name, $price, $tags) {
            $item = create_item($name, post_int('category_id'), post_float('weight'), $price ?? 0, post_str('description', 1000));
            set_item_tags((int)$item['id'], $tags);
        });
    } catch (PDOException $e) {
        // Someone added the name between the check above and the insert.
        if (!item_by_name($name)) {
            throw $e;
        }
        htmx_fail("“{$name}” is already in the catalog.");
    }
    toast("Added {$name} to the catalog.");
    $afterSwap = ['qm-reset' => ['form' => '#catalog-form']];
    if ($tags) {
        $afterSwap['qm-tags-changed'] = true;
    }
    header('HX-Trigger-After-Swap: ' . json_encode($afterSwap));
    fragment('partials/catalog_body', ['items' => catalog_rows(...posted_filters())]);
}

function dm_catalog_update(int $id): void
{
    require_dm();
    $item = catalog_item($id) ?? abort(404, 'No such item.');
    $name = post_str('name', 150) ?: $item['name'];
    $clash = item_by_name($name);
    if ($clash && (int)$clash['id'] !== $id) {
        htmx_fail("Another item is already called “{$name}”.");
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
        fragment('partials/catalog_body', ['items' => catalog_rows(...posted_filters())]);
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
    echo '';
    exit;
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
              WHERE i.name LIKE ? ORDER BY i.name LIMIT 12',
            ['%' . $search . '%']
        );
    foreach ($rows as $r) {
        echo '<option value="' . e($r['name']) . '">' . e($r['category'] . ' · ' . coins((int)$r['price_cp']) . ' · ' . fmt_weight($r['weight'])) . '</option>';
    }
    exit;
}
