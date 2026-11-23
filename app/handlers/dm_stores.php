<?php
declare(strict_types=1);

function dm_stores_index(): void
{
    require_dm();
    $first = q_val('SELECT id FROM stores WHERE campaign_id = ? ORDER BY name LIMIT 1', [campaign_id()]);
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
        // Back to the store page the form was on, not wherever /dm/stores would bounce to.
        $from = store(post_int('from'));
        back_with_error($from ? '/dm/stores/' . $from['id'] : '/dm/stores', 'Give the store a name.', ['name', 'kind']);
    }
    $id = insert(
        'INSERT INTO stores (campaign_id, name, kind, blurb, is_open, created_at) VALUES (?, ?, ?, ?, 0, ?)',
        [campaign_id(), $name, post_str('kind', 100), '', now()]
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
        'characters' => party_names(),
        'access'     => store_access_ids($storeId),
        // The DM's other campaigns, which the store can be copied to.
        'elsewhere'  => q_all('SELECT id, name, ruleset FROM campaigns WHERE owner_user_id = ? AND id <> ? ORDER BY name, id', [owner_id(), campaign_id()]),
    ], $store['name']);
}

function dm_store_update(int $storeId): void
{
    require_dm();
    $store = store($storeId) ?? abort(404, 'No such store.');
    $name = post_str('name', 100) ?: $store['name'];
    $allIds = array_map('intval', array_column(party_names(), 'id'));
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

/**
 * Copy a store into another of this DM's campaigns; this one stays as it is. A campaign playing the same system gets
 * its shelves too, with the same prices and stock counts. One playing another system can't use these items, so it gets
 * the store's name, kind and note with empty shelves. Either way the copy starts hidden, open to that whole party,
 * for the DM to say who shops there before opening it. Carts and who can shop here belong to this party, so they
 * aren't copied.
 */
function dm_store_copy(int $storeId): void
{
    require_dm();
    $store = store($storeId) ?? abort(404, 'No such store.');
    $target = campaign(post_int('campaign_id'));
    if (!$target || (int)$target['id'] === campaign_id()) {
        flash('Pick another of your campaigns to copy the store to.', 'error');
        redirect('/dm/stores/' . $storeId);
    }
    $fromId = campaign_id();
    $toId = (int)$target['id'];

    try {
        [$target, $sameSystem, $lines] = transaction(function () use ($store, $fromId, $toId) {
            // Both campaign rows, lower id first, so neither can be deleted or have its system set under the copy, and
            // two copies the opposite way can't deadlock. The systems are compared from these rows, not the ones read
            // before, since setting a campaign's system (dm_campaign_set_ruleset) locks the same row and changes the
            // items on its shelves.
            $locked = [];
            foreach ([min($fromId, $toId), max($fromId, $toId)] as $id) {
                $row = lock_campaign($id);
                if (!$row || (int)$row['owner_user_id'] !== owner_id()) {
                    throw new RuntimeException($id === $toId ? 'That campaign is gone. Pick another.' : 'This campaign is gone.');
                }
                $locked[$id] = $row;
            }
            $target = $locked[$toId];
            $sameSystem = $target['ruleset'] === $locked[$fromId]['ruleset'];
            $live = q_one('SELECT * FROM stores WHERE id = ? AND campaign_id = ?', [$store['id'], $fromId]);
            if (!$live) {
                throw new RuntimeException("{$store['name']} has closed.");
            }
            $copyId = insert(
                'INSERT INTO stores (campaign_id, name, kind, blurb, is_open, created_at) VALUES (?, ?, ?, ?, 0, ?)',
                [$toId, $live['name'], $live['kind'], $live['blurb'], now()]
            );
            $lines = !$sameSystem ? 0 : q(
                'INSERT INTO store_stock (store_id, item_id, notes, price_cp, quantity)
                 SELECT ?, item_id, notes, price_cp, quantity FROM store_stock WHERE store_id = ?',
                [$copyId, $store['id']]
            )->rowCount();
            return [$target, $sameSystem, $lines];
        });
    } catch (PDOException $e) {
        throw $e;
    } catch (RuntimeException $e) {
        flash($e->getMessage(), 'error');
        redirect(store($storeId) ? '/dm/stores/' . $storeId : '/dm/stores');
    }

    flash($sameSystem
        ? "Copied {$store['name']} to {$target['name']}" . ($lines ? ', shelves and all' : '') . '. It’s hidden there until you open it.'
        : "{$target['name']} plays " . ruleset_label($target['ruleset']) . ', so these shelves can’t go there. '
            . "It has an empty {$store['name']} of its own now, hidden until you stock and open it.");
    redirect('/dm/stores/' . $storeId);
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
    // The same item with other notes is a shelf line of its own: a level 1 scroll of each spell.
    $notes = posted_stock_notes();
    $label = named_with_notes($item['name'], $notes);

    $existing = q_one('SELECT id FROM store_stock WHERE store_id = ? AND item_id = ? AND notes = ?', [$storeId, $item['id'], $notes]);
    if ($existing) {
        q('UPDATE store_stock SET price_cp = ?, quantity = ? WHERE id = ?', [$price, $qty, $existing['id']]);
        toast("Updated {$label} on the shelf.");
    } else {
        q('INSERT INTO store_stock (store_id, item_id, notes, price_cp, quantity) VALUES (?, ?, ?, ?, ?)', [$storeId, $item['id'], $notes, $price, $qty]);
        toast("Stocked {$label}.");
    }
    header('HX-Trigger-After-Swap: ' . json_encode(['qm-reset' => ['form' => '#stock-form']]));
    fragment('partials/stock_body', ['stock' => store_stock_rows($storeId)]);
}

/** Spells for the stock form's Notes, when the item being stocked is a spell scroll: those a scroll of its level can hold. */
function dm_stock_spells(): void
{
    require_dm();
    $level = scroll_level(get_str('item', 150));
    if ($level === null) {
        exit;
    }
    $spells = spell_suggestions(campaign_ruleset(), $level, get_str('notes', STOCK_NOTES_MAX));
    foreach (array_slice($spells, 0, SPELL_SUGGEST_MAX) as [$name, $note]) {
        // Picked, it fills Notes as it will be saved: with a straight apostrophe (see posted_stock_notes()).
        echo '<li role="option" class="suggest-opt" aria-selected="false" data-value="' . e(str_replace('’', "'", $name)) . '">'
            . '<span class="suggest-name">' . e($name) . '</span>'
            . '<span class="suggest-meta">' . e($note) . '</span>'
            . '</li>';
    }
    $more = count($spells) - SPELL_SUGGEST_MAX;
    if ($more > 0) {
        echo '<li class="suggest-foot" role="presentation">' . e($more . ' more ' . ($more === 1 ? 'fits' : 'fit') . '. Type more of the name to find it.') . '</li>';
    }
    exit;
}

/** Inline edit of notes / price / quantity on one shelf row. */
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
    // Rows drawn before notes existed post none; keep what the line has.
    $notes = isset($_POST['notes']) ? posted_stock_notes() : $row['notes'];
    $label = named_with_notes($row['name'], $notes);
    try {
        $dropped = transaction(function () use ($row, $stockId, $notes, $price, $qty, $label) {
            if ($notes === $row['notes']) {
                q('UPDATE store_stock SET price_cp = ?, quantity = ? WHERE id = ?', [$price, $qty, $stockId]);
                return 0;
            }
            // New notes make it a different thing (another spell on the scroll), so carts don't follow it. Lock the
            // carts holding the old line, so none is sent to the DM while this decides.
            $holders = q_all(
                "SELECT c.status FROM carts c JOIN cart_items ci ON ci.cart_id = c.id
                  WHERE c.store_id = ? AND c.status IN ('open','submitted') AND ci.item_id = ? AND ci.notes = ?" . for_update(),
                [$row['store_id'], $row['item_id'], $row['notes']]
            );
            if (in_array('submitted', array_column($holders, 'status'), true)) {
                throw new RuntimeException('A cart waiting on you holds ' . named_with_notes($row['name'], $row['notes']) . '. Approve or decline it before changing the notes.');
            }
            if (q_val(
                'SELECT 1 FROM store_stock WHERE store_id = ? AND item_id = ? AND notes = ? AND id <> ?',
                [$row['store_id'], $row['item_id'], $notes, $stockId]
            )) {
                throw new RuntimeException("{$label} is already on the shelf.");
            }
            q('UPDATE store_stock SET notes = ?, price_cp = ?, quantity = ? WHERE id = ?', [$notes, $price, $qty, $stockId]);
            // As when it's pulled from the shelf: open carts drop the old line, and their players add the new one if they want it.
            return remove_from_open_carts((int)$row['store_id'], (int)$row['item_id'], $row['notes']);
        });
    } catch (PDOException $e) {
        // Another save gave a line of this item the same notes just now. (Caught first: it's a RuntimeException too.)
        if ($e->getCode() !== '23000') {
            throw $e;
        }
        htmx_fail("{$label} is already on the shelf.");
    } catch (RuntimeException $e) {
        htmx_fail($e->getMessage());
    }
    toast("Saved {$label}: " . coins($price) . ', ' . ($qty === null ? 'unlimited' : $qty . ' in stock') . '.'
        . ($dropped ? ' Taken out of ' . ($dropped === 1 ? 'an open cart' : "{$dropped} open carts") . '.' : ''));
    fragment('partials/stock_row', ['s' => stock_row($stockId)]);
}

function dm_stock_delete(int $stockId): void
{
    require_dm();
    $row = stock_row($stockId) ?? abort(404, 'That item is no longer stocked.');
    q('DELETE FROM store_stock WHERE id = ?', [$stockId]);
    // Drop it from any open carts too, so players can't buy it.
    remove_from_open_carts((int)$row['store_id'], (int)$row['item_id'], $row['notes']);
    toast('Pulled ' . named_with_notes($row['name'], $row['notes']) . ' from the shelf.');
    echo '';
    exit;
}
