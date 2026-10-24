<?php
declare(strict_types=1);

function dm_carts(): void
{
    require_dm();
    $inCampaign = 'SELECT c.id FROM carts c JOIN characters ch ON ch.id = c.character_id WHERE ch.campaign_id = ? AND ';
    $pendingIds = array_column(q_all($inCampaign . "c.status = 'submitted' ORDER BY c.submitted_at", [campaign_id()]), 'id');
    $recentIds  = array_column(q_all($inCampaign . "c.status IN ('approved','declined') ORDER BY c.decided_at DESC LIMIT 6", [campaign_id()]), 'id');
    page('dm/carts', [
        'active'  => 'carts',
        'pending' => array_map(fn($id) => cart_detail((int)$id), $pendingIds),
        'recent'  => array_map(fn($id) => cart_detail((int)$id), $recentIds),
    ], 'Cart review');
}

function submitted_cart_or_fail(int $cartId): array
{
    $cart = cart_detail($cartId) ?? abort(404, 'No such cart.');
    if ($cart['status'] !== 'submitted') {
        htmx_fail('That cart has already been handled.');
    }
    return $cart;
}

function dm_cart_approve(int $cartId): void
{
    require_dm();
    submitted_cart_or_fail($cartId);

    try {
        transaction(function () use ($cartId) {
            // Lock the cart and the purse so two clicks can't spend the same coin twice.
            $cart = q_one('SELECT * FROM carts WHERE id = ?' . for_update(), [$cartId]);
            if (!$cart || $cart['status'] !== 'submitted') {
                throw new RuntimeException('That cart has already been handled.');
            }
            $char = q_one('SELECT * FROM characters WHERE id = ?' . for_update(), [$cart['character_id']]);
            $storeName = (string)q_val('SELECT name FROM stores WHERE id = ?', [$cart['store_id']]);
            $lines = q_all(
                'SELECT ci.*, i.name FROM cart_items ci JOIN items i ON i.id = ci.item_id WHERE ci.cart_id = ?',
                [$cartId]
            );
            if (!$lines) {
                throw new RuntimeException('The cart is empty. Decline it instead.');
            }
            $total = 0;
            foreach ($lines as $l) {
                $total += (int)$l['quantity'] * (int)$l['unit_price_cp'];
            }
            if ($total > (int)$char['coin_cp']) {
                throw new RuntimeException("{$char['name']} is short " . coins($total - (int)$char['coin_cp']) . '. Adjust the cart or their purse first.');
            }
            foreach ($lines as $l) {
                $stock = q_one(
                    'SELECT id, quantity FROM store_stock WHERE store_id = ? AND item_id = ?' . for_update(),
                    [$cart['store_id'], $l['item_id']]
                );
                if (!$stock) {
                    throw new RuntimeException("{$l['name']} is no longer sold here. Set its quantity to 0 first.");
                }
                if ($stock['quantity'] !== null) {
                    if ((int)$stock['quantity'] < (int)$l['quantity']) {
                        throw new RuntimeException("Only {$stock['quantity']} × {$l['name']} left in stock.");
                    }
                    q('UPDATE store_stock SET quantity = quantity - ? WHERE id = ?', [$l['quantity'], $stock['id']]);
                }
                add_to_inventory((int)$char['id'], (int)$l['item_id'], (int)$l['quantity']);
                log_inventory((int)$char['id'], 'purchase', $l, (int)$l['quantity'], detail: 'Bought at ' . $storeName);
            }
            q('UPDATE characters SET coin_cp = coin_cp - ? WHERE id = ?', [$total, $char['id']]);
            log_inventory((int)$char['id'], 'purchase', coinCp: -$total, detail: 'Paid at ' . $storeName);
            q("UPDATE carts SET status = 'approved', decided_at = ? WHERE id = ?", [now(), $cartId]);
        });
    } catch (RuntimeException $e) {
        htmx_fail($e->getMessage());
    }

    $cart = cart_detail($cartId);
    toast("Approved. {$cart['character_name']} paid " . coins($cart['total_cp']) . '.');
    echo render('partials/cart_card', ['cart' => $cart]);
    echo render('partials/cart_badge_oob', []);
    exit;
}

function dm_cart_decline(int $cartId): void
{
    require_dm();
    submitted_cart_or_fail($cartId);
    q("UPDATE carts SET status = 'declined', dm_note = ?, decided_at = ? WHERE id = ?", [post_str('dm_note', 500), now(), $cartId]);
    $cart = cart_detail($cartId);
    toast("Declined {$cart['character_name']}’s cart.");
    echo render('partials/cart_card', ['cart' => $cart]);
    echo render('partials/cart_badge_oob', []);
    exit;
}

/** DM adjusts a quantity in a submitted cart (0 removes the line). */
function dm_cart_item_qty(int $lineId): void
{
    require_dm();
    $line = q_one('SELECT * FROM cart_items WHERE id = ?', [$lineId]) ?? abort(404, 'That line is gone.');
    submitted_cart_or_fail((int)$line['cart_id']);
    $qty = max(0, post_int('quantity', (int)$line['quantity']));
    if ($qty === 0) {
        q('DELETE FROM cart_items WHERE id = ?', [$lineId]);
    } else {
        q('UPDATE cart_items SET quantity = ? WHERE id = ?', [$qty, $lineId]);
    }
    fragment('partials/cart_card', ['cart' => cart_detail((int)$line['cart_id'])]);
}
