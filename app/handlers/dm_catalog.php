<?php
declare(strict_types=1);

function catalog_rows(string $search = '', string $cat = ''): array
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
    return q_all($sql . ' ORDER BY cat.sort_order, i.name', $params);
}

function catalog_item(int $id): ?array
{
    return q_one(
        'SELECT i.*, cat.name AS category, cat.slug AS category_slug,
                (SELECT COUNT(*) FROM inventory inv WHERE inv.item_id = i.id)
              + (SELECT COUNT(*) FROM store_stock st WHERE st.item_id = i.id)
              + (SELECT COUNT(*) FROM cart_items ci WHERE ci.item_id = i.id) AS uses
           FROM items i JOIN categories cat ON cat.id = i.category_id WHERE i.id = ?',
        [$id]
    );
}

function dm_catalog(): void
{
    require_dm();
    $search = get_str('q');
    $cat = get_str('cat', 30);
    if (htmx_target() === 'catalog-body') {
        fragment('partials/catalog_body', ['items' => catalog_rows($search, $cat)]);
    }
    page('dm/catalog', [
        'active' => 'catalog',
        'items'  => catalog_rows($search, $cat),
        'search' => $search,
        'cat'    => $cat,
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
    find_or_create_item($name, post_int('category_id'), post_float('weight'), $price ?? 0);
    toast("Added {$name} to the catalog.");
    header('HX-Trigger-After-Swap: ' . json_encode(['qm-reset' => ['form' => '#catalog-form']]));
    fragment('partials/catalog_body', ['items' => catalog_rows()]);
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
    q(
        'UPDATE items SET name = ?, category_id = ?, weight = ?, price_cp = ? WHERE id = ?',
        [$name, $catId, max(0, post_float('weight')), $price, $id]
    );
    toast("Saved {$name}.");
    fragment('partials/catalog_row', ['i' => catalog_item($id)]);
}

function dm_catalog_delete(int $id): void
{
    require_dm();
    $item = catalog_item($id) ?? abort(404, 'No such item.');
    if ((int)$item['uses'] > 0) {
        htmx_fail("{$item['name']} is in a pack, a store or a cart, so it can’t be deleted.");
    }
    q('DELETE FROM items WHERE id = ?', [$id]);
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
