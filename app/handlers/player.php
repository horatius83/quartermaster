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
    $tags = filter_tags($_GET);

    $stock = with_item_tags(store_stock_rows($storeId), 'item_id');
    $inCategory = $cat === '' ? $stock : array_filter($stock, fn($w) => $w['category_slug'] === $cat);
    $shown = having_tags($inCategory, $tags);

    // So no pick leads nowhere, the categories on offer have wares with the picked tags, and the tags on offer are
    // on the wares showing. The current category and tags stay listed too, so they can be undone.
    $catsHere = [];
    foreach ($stock as $w) {
        if ($w['category_slug'] === $cat || !array_diff($tags, $w['tags'])) {
            $catsHere[$w['category_slug']] = $w['category'];
        }
    }

    $waresView = [
        'store' => $store, 'wares' => $shown,
        'catsHere' => $catsHere, 'cat' => $cat, 'tags' => $tags,
        'tagsHere' => merge_tags($tags, ...array_column($shown, 'tags')),
    ];
    if (htmx_target() === 'wares') {
        fragment('partials/shop_wares', $waresView);
    }

    $cart = active_cart((int)$char['id'], $storeId);

    page('player/shop', $waresView + [
        'active'   => 'shop',
        'char'     => $char,
        'stores'   => stores_for_character((int)$char['id']),
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
    $label = named_with_notes($stock['name'], $stock['notes']);
    $line = q_one('SELECT * FROM cart_items WHERE cart_id = ? AND item_id = ? AND notes = ?', [$cart['id'], $stock['item_id'], $stock['notes']]);
    $newQty = ($line ? (int)$line['quantity'] : 0) + 1;
    if ($stock['quantity'] !== null && $newQty > (int)$stock['quantity']) {
        htmx_fail("Only {$stock['quantity']} × {$label} in stock.");
    }
    if ($line) {
        q('UPDATE cart_items SET quantity = ?, unit_price_cp = ? WHERE id = ?', [$newQty, $stock['price_cp'], $line['id']]);
    } else {
        q(
            'INSERT INTO cart_items (cart_id, item_id, notes, quantity, unit_price_cp) VALUES (?, ?, ?, 1, ?)',
            [$cart['id'], $stock['item_id'], $stock['notes'], $stock['price_cp']]
        );
    }
    toast("Added {$label} to your cart.");
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
        $stock = q_one(
            'SELECT quantity FROM store_stock WHERE store_id = ? AND item_id = ? AND notes = ?',
            [$line['store_id'], $line['item_id'], $line['notes']]
        );
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
    $rows = inventory_rows((int)$char['id']);
    $offered = offered_quantities((int)$char['id']);
    foreach ($rows as &$r) {
        $r['sellable'] = max(0, (int)$r['quantity'] - ($offered[stack_key((int)$r['item_id'], $r['notes'])] ?? 0));
    }
    unset($r);
    $mine = 'sa.character_id = ? AND ';
    page('player/pack', [
        'active'  => 'pack',
        'char'    => $char,
        'rows'    => $rows,
        'carried' => carried_weight((int)$char['id']),
        'orders'  => array_map(fn($o) => cart_detail((int)$o['id']), $orders),
        'offers'  => sale_list($mine . "sa.status IN ('countered','pending')", [$char['id']], "CASE sa.status WHEN 'countered' THEN 0 ELSE 1 END, sa.created_at"),
        'sold'    => sale_list($mine . "sa.status IN ('sold','rejected','declined')", [$char['id']], 'sa.decided_at DESC', 8),
        'shared'  => shares_purse(),
        'coin'    => spendable_cp($char),
        'stash'   => shares_purse() ? party_stash_rows() : [],
    ], 'My pack');
}

/** Put some of a pack stack in the party stash, for anyone in the party to take. Items on offer to sell stay. */
function pack_to_party(int $rowId): void
{
    $char = require_player();
    if (!shares_purse()) {
        back_to_pack('Your party doesn’t share a stash.', 'error');
    }
    $row = inventory_row($rowId);
    if (!$row || (int)$row['character_id'] !== (int)$char['id']) {
        back_to_pack('That item is no longer in your pack.', 'error');
    }
    try {
        $qty = pack_to_stash($row, post_int('quantity', 1), keepOffered: true);
    } catch (PDOException $e) {
        throw $e;
    } catch (RuntimeException $e) {
        back_to_pack($e->getMessage(), 'error');
    }
    back_to_pack("Put {$qty} × {$row['name']} in the party stash.");
}

/** Take some of a party stash stack into the pack. */
function pack_take_from_party(int $rowId): void
{
    $char = require_player();
    if (!shares_purse()) {
        back_to_pack('Your party doesn’t share a stash.', 'error');
    }
    $row = party_stash_row($rowId) ?? back_to_pack('Someone has already taken that from the party stash.', 'error');
    try {
        $qty = stash_to_pack($row, $char, post_int('quantity', 1));
    } catch (PDOException $e) {
        throw $e;
    } catch (RuntimeException $e) {
        back_to_pack($e->getMessage(), 'error');
    }
    back_to_pack("Took {$qty} × {$row['name']} from the party stash.");
}

/** Back to the pack with a message; player sale forms are plain (boosted) forms, so the whole page redraws. */
function back_to_pack(string $message, string $kind = 'ok'): never
{
    flash($message, $kind);
    redirect('/pack');
}

/** One of the player's own sales, or back to the pack if it's someone else's or gone. */
function player_sale(array $char, int $saleId): array
{
    $sale = sale_detail($saleId);
    if (!$sale || (int)$sale['character_id'] !== (int)$char['id']) {
        back_to_pack('That offer is gone.', 'error');
    }
    return $sale;
}

/** Offer some of a stack in the pack to the DM, at a price for the lot. */
function pack_sell(int $rowId): void
{
    $char = require_player();
    $row = inventory_row($rowId);
    if (!$row || (int)$row['character_id'] !== (int)$char['id']) {
        back_to_pack('That item is no longer in your pack.', 'error');
    }
    $qty = post_int('quantity', 1);
    $price = parse_price(post_str('price', 40));
    if ($price === null || $price <= 0 || $price > MAX_SALE_CP) {
        back_to_pack('Name your price, like “8 gp” or “1 gp 5 sp”.', 'error');
    }
    try {
        transaction(function () use ($char, $row, $rowId, $qty, $price) {
            // Lock the stack first, so two offers sent at once can't both count the same items as free.
            $held = q_val('SELECT quantity FROM inventory WHERE id = ?' . for_update(), [$rowId]);
            if ($held === null) {
                throw new RuntimeException('That item is no longer in your pack.');
            }
            $free = (int)$held - (offered_quantities((int)$char['id'])[stack_key((int)$row['item_id'], $row['notes'])] ?? 0);
            if ($free <= 0) {
                throw new RuntimeException("All your {$row['name']} are already on offer.");
            }
            if ($qty < 1 || $qty > $free) {
                throw new RuntimeException($free === 1 ? "You can offer 1 {$row['name']}." : "You can offer 1 to {$free} × {$row['name']}.");
            }
            q(
                "INSERT INTO sales (character_id, item_id, notes, quantity, asking_cp, status, player_note, created_at)
                 VALUES (?, ?, ?, ?, ?, 'pending', ?, ?)",
                [$char['id'], $row['item_id'], $row['notes'], $qty, $price, post_str('player_note', 500), now()]
            );
        });
    } catch (RuntimeException $e) {
        back_to_pack($e->getMessage(), 'error');
    }
    back_to_pack("Offered {$qty} × {$row['name']} to your DM for " . coins($price) . '. Nothing leaves your pack until it’s sold.');
}

/** Take back an offer the DM hasn't answered yet. */
function pack_sale_withdraw(int $saleId): void
{
    $char = require_player();
    $sale = player_sale($char, $saleId);
    if (!q("DELETE FROM sales WHERE id = ? AND status = 'pending'", [$saleId])->rowCount()) {
        back_to_pack('Your DM has already answered that offer.', 'error');
    }
    back_to_pack("Withdrew your offer for the {$sale['name']}.");
}

/** Take the DM's counter-offer: the sale goes through at their price. */
function pack_sale_accept(int $saleId): void
{
    $char = require_player();
    $sale = player_sale($char, $saleId);
    try {
        settle_sale($saleId, byPlayer: true, seenPrice: post_int('offer_cp', -1));
    } catch (RuntimeException $e) {
        back_to_pack($sale['status'] === 'countered' ? $e->getMessage() : 'That counter-offer has been withdrawn or already settled.', 'error');
    }
    back_to_pack("Sold {$sale['quantity']} × {$sale['name']} for " . coins(post_int('offer_cp')) . '.');
}

/** Turn down the DM's counter-offer; the items stay in the pack. */
function pack_sale_decline(int $saleId): void
{
    $char = require_player();
    $sale = player_sale($char, $saleId);
    if (!q("UPDATE sales SET status = 'declined', decided_at = ? WHERE id = ? AND status = 'countered'", [now(), $saleId])->rowCount()) {
        back_to_pack('That counter-offer has been withdrawn or already settled.', 'error');
    }
    back_to_pack("Turned down the offer for the {$sale['name']}. It stays in your pack.");
}

/** The player's requests to the DM, and the box to write a new one. Seeing a reply clears the Requests badge. */
function player_requests(): void
{
    $char = require_player();
    // Unseen replies come first, so the page always shows them however many requests there are, then those still open.
    $requests = request_list(
        'r.character_id = ?',
        [$char['id']],
        "CASE WHEN r.status = 'closed' AND r.dm_reply <> '' AND r.reply_seen_at IS NULL THEN 0 WHEN r.status = 'open' THEN 1 ELSE 2 END,
         COALESCE(r.closed_at, r.created_at) DESC, r.id DESC",
        REQUESTS_SHOWN
    );
    // Only the replies on the page count as seen; any beyond it keep the badge until a later visit shows them.
    $shown = array_column(array_filter($requests, fn($r) => $r['status'] === 'closed' && $r['dm_reply'] !== '' && $r['reply_seen_at'] === null), 'id');
    if ($shown) {
        q(
            'UPDATE requests SET reply_seen_at = ? WHERE character_id = ? AND id IN (' . implode(',', array_fill(0, count($shown), '?')) . ')',
            [now(), $char['id'], ...$shown]
        );
    }
    page('player/requests', [
        'active'    => 'requests',
        'char'      => $char,
        'requests'  => $requests,
        'openCount' => open_request_count_of((int)$char['id']),
        'draft'     => (string)(take_old_input()['body'] ?? ''),
    ], 'Requests');
}

/** Send the DM a request or comment. */
function player_request_create(): void
{
    $char = require_player();
    $body = post_text('body', MAX_REQUEST_CHARS);
    if ($body === '') {
        back_with_error('/requests', 'Write your request first.', []);
    }
    $full = transaction(function () use ($char, $body): int {
        // Lock the character first, so two sends at once can't both count the same free slot.
        q('SELECT id FROM characters WHERE id = ?' . for_update(), [$char['id']]);
        $open = open_request_count_of((int)$char['id']);
        if ($open >= MAX_OPEN_REQUESTS) {
            return $open;
        }
        q(
            "INSERT INTO requests (character_id, body, status, created_at) VALUES (?, ?, 'open', ?)",
            [$char['id'], $body, now()]
        );
        return 0;
    });
    if ($full) {
        back_with_error('/requests', "You have {$full} requests waiting on your DM. Withdraw one, or wait for an answer, before sending more.", ['body'], MAX_REQUEST_CHARS);
    }
    flash('Sent to your DM.');
    redirect('/requests');
}

/** Take back a request the DM hasn't answered yet. */
function player_request_withdraw(int $requestId): void
{
    $char = require_player();
    $gone = q("DELETE FROM requests WHERE id = ? AND character_id = ? AND status = 'open'", [$requestId, $char['id']])->rowCount();
    flash($gone ? 'Request withdrawn.' : 'Your DM has already answered that request.', $gone ? 'ok' : 'error');
    redirect('/requests');
}
