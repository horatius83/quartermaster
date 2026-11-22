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

/** Approving a cart whose lines changed since the DM's page showed them. */
final class CartChanged extends RuntimeException
{
}

function dm_cart_approve(int $cartId): void
{
    require_dm();
    submitted_cart_or_fail($cartId);
    $seen = post_str('seen', 2000);

    try {
        transaction(function () use ($cartId, $seen) {
            // Lock the cart and the purse so two clicks can't spend the same coin twice.
            $cart = q_one('SELECT * FROM carts WHERE id = ?' . for_update(), [$cartId]);
            if (!$cart || $cart['status'] !== 'submitted') {
                throw new RuntimeException('That cart has already been handled.');
            }
            $purse = lock_purse((int)$cart['character_id']);
            $char = $purse['char'];
            $storeName = (string)q_val('SELECT name FROM stores WHERE id = ?', [$cart['store_id']]);
            $lines = q_all(
                'SELECT ci.*, i.name FROM cart_items ci JOIN items i ON i.id = ci.item_id WHERE ci.cart_id = ?',
                [$cartId]
            );
            if (!$lines) {
                throw new RuntimeException('The cart is empty. Decline it instead.');
            }
            if (cart_signature($lines) !== $seen) {
                throw new CartChanged('This cart changed since you opened it. Check it again before approving.');
            }
            $total = 0;
            foreach ($lines as $l) {
                $total += (int)$l['quantity'] * (int)$l['unit_price_cp'];
            }
            if ($total > $purse['coin']) {
                $short = coins($total - $purse['coin']);
                throw new RuntimeException($purse['shared']
                    ? "The party purse is short {$short}. Adjust the cart or the purse first."
                    : "{$char['name']} is short {$short}. Adjust the cart or their purse first.");
            }
            foreach ($lines as $l) {
                $label = named_with_notes($l['name'], $l['notes']);
                $stock = q_one(
                    'SELECT id, quantity FROM store_stock WHERE store_id = ? AND item_id = ? AND notes = ?' . for_update(),
                    [$cart['store_id'], $l['item_id'], $l['notes']]
                );
                if (!$stock) {
                    throw new RuntimeException("{$label} is no longer sold here. Set its quantity to 0 first.");
                }
                if ($stock['quantity'] !== null) {
                    if ((int)$stock['quantity'] < (int)$l['quantity']) {
                        throw new RuntimeException("Only {$stock['quantity']} × {$label} left in stock.");
                    }
                    q('UPDATE store_stock SET quantity = quantity - ? WHERE id = ?', [$l['quantity'], $stock['id']]);
                }
                add_to_inventory((int)$char['id'], (int)$l['item_id'], (int)$l['quantity'], $l['notes']);
                $haggled = $l['list_price_cp'] !== null
                    ? ', for ' . coins((int)$l['unit_price_cp']) . ' each (shelf price ' . coins((int)$l['list_price_cp']) . ')'
                    : '';
                log_inventory((int)$char['id'], 'purchase', $l, (int)$l['quantity'], notes: $l['notes'], detail: 'Bought at ' . $storeName . $haggled);
            }
            change_purse($purse, -$total, 'purchase', 'Paid at ' . $storeName, "Paid at {$storeName}, for {$char['name']}");
            q("UPDATE carts SET status = 'approved', decided_at = ? WHERE id = ?", [now(), $cartId]);
        });
    } catch (CartChanged $e) {
        // Show the cart as it is now, so the DM sees what changed.
        toast($e->getMessage(), 'error');
        fragment('partials/cart_card', ['cart' => cart_detail($cartId)]);
    } catch (RuntimeException $e) {
        htmx_fail($e->getMessage());
    }

    $cart = cart_detail($cartId);
    toast((int)$cart['shared_purse']
        ? "Approved. The party purse paid " . coins($cart['total_cp']) . " for {$cart['character_name']}’s cart."
        : "Approved. {$cart['character_name']} paid " . coins($cart['total_cp']) . '.');
    echo render('partials/cart_card', ['cart' => $cart]);
    echo render('partials/badge_oob', ['id' => 'cart-badge', 'count' => pending_cart_count()]);
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
    echo render('partials/badge_oob', ['id' => 'cart-badge', 'count' => pending_cart_count()]);
    exit;
}

/** A line of a cart in this campaign that waits on the DM, or a refusal. */
function submitted_cart_line(int $lineId): array
{
    $line = q_one(
        'SELECT ci.*, i.name FROM cart_items ci JOIN items i ON i.id = ci.item_id
           JOIN carts c ON c.id = ci.cart_id JOIN characters ch ON ch.id = c.character_id
          WHERE ci.id = ? AND ch.campaign_id = ?',
        [$lineId, campaign_id()]
    ) ?? abort(404, 'That line is gone.');
    submitted_cart_or_fail((int)$line['cart_id']);
    return $line;
}

/**
 * Change a line of a cart waiting on the DM, with the cart locked as Approve locks it, so a change can't land on a cart
 * that was approved or withdrawn a moment before. $change gets the line, read again under the lock.
 */
function change_submitted_line(int $lineId, callable $change): void
{
    $line = submitted_cart_line($lineId);
    try {
        transaction(function () use ($line, $change) {
            $status = q_val('SELECT status FROM carts WHERE id = ?' . for_update(), [$line['cart_id']]);
            $now = q_one('SELECT * FROM cart_items WHERE id = ?' . for_update(), [$line['id']]);
            if ($status !== 'submitted' || !$now) {
                throw new RuntimeException('That cart has already been handled.');
            }
            $change($now + ['name' => $line['name']]);
        });
    } catch (PDOException $e) {
        throw $e;
    } catch (RuntimeException $e) {
        htmx_fail($e->getMessage());
    }
    fragment('partials/cart_card', ['cart' => cart_detail((int)$line['cart_id'])]);
}

/** DM adjusts a quantity in a submitted cart (0 removes the line). The line keeps the price it has, haggled or not. */
function dm_cart_item_qty(int $lineId): void
{
    require_dm();
    $typed = post_int('quantity', PHP_INT_MIN);
    change_submitted_line($lineId, function (array $line) use ($typed) {
        $qty = $typed === PHP_INT_MIN ? (int)$line['quantity'] : max(0, $typed);
        if ($qty === 0) {
            q('DELETE FROM cart_items WHERE id = ?', [$line['id']]);
        } else {
            q('UPDATE cart_items SET quantity = ? WHERE id = ?', [$qty, $line['id']]);
        }
    });
}

/**
 * DM sets what one of a line costs in this cart alone, after haggling: the shelf keeps its price. list_price_cp keeps
 * the shelf price the line was added at, and a blank price (or that price typed) puts the line back at it.
 */
function dm_cart_item_price(int $lineId): void
{
    require_dm();
    $typed = post_str('price', 40);
    $price = null;
    if ($typed !== '') {
        $price = parse_price($typed, whole: true)
            ?? htmx_fail('Type one price, such as 3 gp, 5 sp or 1 gp 5 sp.');
        if ($price < 0) {
            htmx_fail('A price can’t be below 0 gp.');
        }
        if ($price > MAX_SALE_CP) {
            htmx_fail('The price can be at most ' . coins(MAX_SALE_CP) . '.');
        }
    }
    change_submitted_line($lineId, function (array $line) use ($price) {
        $listed = (int)($line['list_price_cp'] ?? $line['unit_price_cp']);
        $price ??= $listed;
        q(
            'UPDATE cart_items SET unit_price_cp = ?, list_price_cp = ? WHERE id = ?',
            [$price, $price === $listed ? null : $listed, $line['id']]
        );
        if ($price !== (int)$line['unit_price_cp']) {
            $label = named_with_notes($line['name'], $line['notes']);
            toast($price === $listed
                ? "{$label} is back at its shelf price, " . coins($price) . ' each.'
                : "{$label} now costs " . coins($price) . ' each, in this cart only.');
        }
    });
}
