<?php
declare(strict_types=1);

/** The store, if this character may shop in it right now. */
function player_store(array $char, int $storeId): array
{
    foreach (stores_for_character((int)$char['id']) as $s) {
        if ((int)$s['id'] === $storeId) {
            return $s;
        }
    }
    abort(404, 'That shop is closed to you.');
}

/** The character's active cart for a store: a submitted one if waiting on the DM, else the open one. */
function active_cart(int $characterId, int $storeId, bool $create = false): ?array
{
    $cart = q_one(
        "SELECT * FROM carts WHERE character_id = ? AND store_id = ? AND status IN ('open','submitted')
          ORDER BY CASE status WHEN 'submitted' THEN 0 ELSE 1 END, id DESC LIMIT 1",
        [$characterId, $storeId]
    );
    if (!$cart && $create) {
        $id = insert(
            "INSERT INTO carts (character_id, store_id, status, created_at) VALUES (?, ?, 'open', ?)",
            [$characterId, $storeId, now()]
        );
        $cart = q_one('SELECT * FROM carts WHERE id = ?', [$id]);
    }
    return $cart;
}

function cart_panel(array $char, array $store): never
{
    $cart = active_cart((int)$char['id'], (int)$store['id']);
    fragment('partials/shop_cart', [
        'char'  => character((int)$char['id']),
        'store' => $store,
        'cart'  => $cart ? cart_detail((int)$cart['id']) : null,
    ]);
}

function shop_index(): void
{
    $char = require_player();
    $stores = stores_for_character((int)$char['id']);
    if (!$stores) {
        page('player/no_shops', ['active' => 'shop', 'char' => $char], 'Shops');
    }
    redirect('/shop/' . $stores[0]['id']);
}

function shop(int $storeId): void
{
    $char = require_player();
    $store = player_store($char, $storeId);
    $cat = get_str('cat', 30);

    $wares = store_stock_rows($storeId);
    $catsHere = [];
    foreach ($wares as $w) {
        $catsHere[$w['category_slug']] = $w['category'];
    }

    if (htmx_target() === 'wares') {
        fragment('partials/shop_wares', [
            'store' => $store, 'wares' => $cat === '' ? $wares : store_stock_rows($storeId, $cat),
            'catsHere' => $catsHere, 'cat' => $cat,
        ]);
    }

    $cart = active_cart((int)$char['id'], $storeId);

    page('player/shop', [
        'active'   => 'shop',
        'char'     => $char,
        'store'    => $store,
        'stores'   => stores_for_character((int)$char['id']),
        'wares'    => $cat === '' ? $wares : store_stock_rows($storeId, $cat),
        'catsHere' => $catsHere,
        'cat'      => $cat,
        'cart'     => $cart ? cart_detail((int)$cart['id']) : null,
    ], $store['name']);
}

function shop_add(int $storeId): void
{
    $char = require_player();
    $store = player_store($char, $storeId);
    $stock = stock_row(post_int('stock_id')) ?? htmx_fail('That item is no longer for sale.');
    if ((int)$stock['store_id'] !== $storeId) {
        htmx_fail('That item is not sold here.');
    }
    $cart = active_cart((int)$char['id'], $storeId, true);
    if ($cart['status'] === 'submitted') {
        htmx_fail('Your cart is with the DM. Withdraw it to make changes.');
    }
    $line = q_one('SELECT * FROM cart_items WHERE cart_id = ? AND item_id = ?', [$cart['id'], $stock['item_id']]);
    $newQty = ($line ? (int)$line['quantity'] : 0) + 1;
    if ($stock['quantity'] !== null && $newQty > (int)$stock['quantity']) {
        htmx_fail("Only {$stock['quantity']} × {$stock['name']} in stock.");
    }
    if ($line) {
        q('UPDATE cart_items SET quantity = ?, unit_price_cp = ? WHERE id = ?', [$newQty, $stock['price_cp'], $line['id']]);
    } else {
        q('INSERT INTO cart_items (cart_id, item_id, quantity, unit_price_cp) VALUES (?, ?, 1, ?)', [$cart['id'], $stock['item_id'], $stock['price_cp']]);
    }
    toast("Added {$stock['name']} to your cart.");
    cart_panel($char, $store);
}

function shop_line_qty(int $lineId): void
{
    $char = require_player();
    $line = q_one(
        'SELECT ci.*, c.character_id, c.store_id, c.status FROM cart_items ci JOIN carts c ON c.id = ci.cart_id WHERE ci.id = ?',
        [$lineId]
    );
    if (!$line || (int)$line['character_id'] !== (int)$char['id']) {
        abort(404, 'That line is gone.');
    }
    $store = player_store($char, (int)$line['store_id']);
    if ($line['status'] !== 'open') {
        htmx_fail('Your cart is with the DM. Withdraw it to make changes.');
    }
    $newQty = (int)$line['quantity'] + (post_int('delta') >= 0 ? 1 : -1);
    if ($newQty <= 0) {
        q('DELETE FROM cart_items WHERE id = ?', [$lineId]);
    } else {
        $stock = q_one('SELECT quantity FROM store_stock WHERE store_id = ? AND item_id = ?', [$line['store_id'], $line['item_id']]);
        if ($stock && $stock['quantity'] !== null && $newQty > (int)$stock['quantity']) {
            htmx_fail("Only {$stock['quantity']} in stock.");
        }
        q('UPDATE cart_items SET quantity = ? WHERE id = ?', [$newQty, $lineId]);
    }
    cart_panel($char, $store);
}

function shop_submit(int $storeId): void
{
    $char = require_player();
    $store = player_store($char, $storeId);
    $cart = active_cart((int)$char['id'], $storeId);
    if (!$cart || $cart['status'] !== 'open' || !cart_detail((int)$cart['id'])['lines']) {
        htmx_fail('Add something to your cart first.');
    }
    q(
        "UPDATE carts SET status = 'submitted', player_note = ?, submitted_at = ? WHERE id = ?",
        [post_str('player_note', 500), now(), $cart['id']]
    );
    toast('Sent to your DM. Coin is only spent once they approve.');
    cart_panel($char, $store);
}

function shop_withdraw(int $storeId): void
{
    $char = require_player();
    $store = player_store($char, $storeId);
    $cart = active_cart((int)$char['id'], $storeId);
    if (!$cart || $cart['status'] !== 'submitted') {
        htmx_fail('There is nothing waiting on the DM.');
    }
    q("UPDATE carts SET status = 'open', submitted_at = NULL WHERE id = ?", [$cart['id']]);
    toast('Cart withdrawn. You can change it now.');
    cart_panel($char, $store);
}

function player_pack(): void
{
    $char = require_player();
    $orders = q_all(
        "SELECT c.id FROM carts c WHERE c.character_id = ? AND c.status IN ('submitted','approved','declined')
          ORDER BY COALESCE(c.decided_at, c.submitted_at) DESC LIMIT 8",
        [$char['id']]
    );
    page('player/pack', [
        'active'  => 'pack',
        'char'    => $char,
        'rows'    => inventory_rows((int)$char['id']),
        'carried' => carried_weight((int)$char['id']),
        'orders'  => array_map(fn($o) => cart_detail((int)$o['id']), $orders),
    ], 'My pack');
}
