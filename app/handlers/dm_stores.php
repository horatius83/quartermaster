<?php
declare(strict_types=1);

function dm_stores_index(): void
{
    require_dm();
    $first = q_val('SELECT id FROM stores ORDER BY name LIMIT 1');
    if ($first) {
        redirect('/dm/stores/' . $first);
    }
    page('dm/stores', ['active' => 'stores', 'stores' => [], 'store' => null], 'Stores');
}

function dm_store_create(): void
{
    require_dm();
    $name = post_str('name', 100);
    if ($name === '') {
        flash('Give the store a name.', 'error');
        redirect('/dm/stores');
    }
    $id = insert(
        'INSERT INTO stores (name, kind, blurb, is_open, created_at) VALUES (?, ?, ?, 0, ?)',
        [$name, post_str('kind', 100), '', now()]
    );
    flash("{$name} is ready for stocking. It stays hidden from players until you open it.");
    redirect('/dm/stores/' . $id);
}

function dm_store(int $storeId): void
{
    require_dm();
    $store = store($storeId) ?? abort(404, 'No such store.');
    page('dm/stores', [
        'active'     => 'stores',
        'stores'     => stores_summary(),
        'store'      => $store,
        'stock'      => store_stock_rows($storeId),
        'characters' => q_all('SELECT id, name FROM characters ORDER BY name'),
        'access'     => store_access_ids($storeId),
    ], $store['name']);
}

function dm_store_update(int $storeId): void
{
    require_dm();
    $store = store($storeId) ?? abort(404, 'No such store.');
    $name = post_str('name', 100) ?: $store['name'];
    $allIds = array_map('intval', array_column(q_all('SELECT id FROM characters'), 'id'));
    $chosen = array_values(array_intersect($allIds, array_map('intval', (array)($_POST['access'] ?? []))));

    transaction(function () use ($storeId, $name, $chosen, $allIds) {
        q('UPDATE stores SET name = ?, kind = ?, blurb = ? WHERE id = ?', [$name, post_str('kind', 100), post_str('blurb', 1000), $storeId]);
        q('DELETE FROM store_access WHERE store_id = ?', [$storeId]);
        // Everyone ticked = no restriction (so new characters can shop too).
        if (count($chosen) < count($allIds)) {
            foreach ($chosen as $cid) {
                q('INSERT INTO store_access (store_id, character_id) VALUES (?, ?)', [$storeId, $cid]);
            }
        }
    });
    if ($chosen === [] && $allIds !== []) {
        flash('Saved. Nobody is ticked, so no one can shop here.', 'warn');
    } else {
        flash('Store details saved.');
    }
    redirect('/dm/stores/' . $storeId);
}

function dm_store_toggle(int $storeId): void
{
    require_dm();
    $store = store($storeId) ?? abort(404, 'No such store.');
    $open = (int)$store['is_open'] === 1 ? 0 : 1;
    q('UPDATE stores SET is_open = ? WHERE id = ?', [$open, $storeId]);
    $store['is_open'] = $open;
    toast($open ? "{$store['name']} is open for business." : "{$store['name']} is now hidden from players.");
    fragment('partials/store_switch', ['store' => $store]);
}

function dm_store_delete(int $storeId): void
{
    require_dm();
    $store = store($storeId) ?? abort(404, 'No such store.');
    q('DELETE FROM stores WHERE id = ?', [$storeId]);
    flash("{$store['name']} has closed its doors for good.");
    redirect('/dm/stores');
}

function dm_stock_add(int $storeId): void
{
    require_dm();
    store($storeId) ?? abort(404, 'No such store.');
    $name = post_str('item', 150);
    if ($name === '') {
        htmx_fail('Type the name of an item to stock.');
    }
    $priceText = post_str('price', 40);
    $price = parse_price($priceText);
    if ($priceText !== '' && $price === null) {
        htmx_fail('Write prices like “15 gp”, “5 sp” or “1 gp 5 sp”.');
    }
    $item = find_or_create_item($name, post_int('category_id'), post_float('weight'), $price ?? 0);
    $price ??= (int)$item['price_cp'];
    $qtyText = post_str('quantity', 10);
    $qty = ($qtyText === '' || $qtyText === '∞') ? null : max(0, (int)$qtyText);

    $existing = q_one('SELECT id FROM store_stock WHERE store_id = ? AND item_id = ?', [$storeId, $item['id']]);
    if ($existing) {
        q('UPDATE store_stock SET price_cp = ?, quantity = ? WHERE id = ?', [$price, $qty, $existing['id']]);
        toast("Updated {$item['name']} on the shelf.");
    } else {
        q('INSERT INTO store_stock (store_id, item_id, price_cp, quantity) VALUES (?, ?, ?, ?)', [$storeId, $item['id'], $price, $qty]);
        toast("Stocked {$item['name']}.");
    }
    header('HX-Trigger-After-Swap: ' . json_encode(['qm-reset' => ['form' => '#stock-form']]));
    fragment('partials/stock_body', ['stock' => store_stock_rows($storeId)]);
}

/** Inline edit of price / quantity on one shelf row. */
function dm_stock_update(int $stockId): void
{
    require_dm();
    $row = stock_row($stockId) ?? abort(404, 'That item is no longer stocked.');
    $price = parse_price(post_str('price', 40));
    if ($price === null) {
        htmx_fail('Write prices like “15 gp”, “5 sp” or “1 gp 5 sp”.');
    }
    $qtyText = post_str('quantity', 10);
    $qty = ($qtyText === '' || $qtyText === '∞') ? null : max(0, (int)$qtyText);
    q('UPDATE store_stock SET price_cp = ?, quantity = ? WHERE id = ?', [$price, $qty, $stockId]);
    toast("Saved {$row['name']}: " . coins($price) . ', ' . ($qty === null ? 'unlimited' : $qty . ' in stock') . '.');
    fragment('partials/stock_row', ['s' => stock_row($stockId)]);
}

function dm_stock_delete(int $stockId): void
{
    require_dm();
    $row = stock_row($stockId) ?? abort(404, 'That item is no longer stocked.');
    q('DELETE FROM store_stock WHERE id = ?', [$stockId]);
    // Drop it from any open carts too, so players can't buy it.
    q("DELETE FROM cart_items WHERE item_id = ? AND cart_id IN (SELECT id FROM carts WHERE store_id = ? AND status = 'open')", [$row['item_id'], $row['store_id']]);
    toast("Pulled {$row['name']} from the shelf.");
    echo '';
    exit;
}
