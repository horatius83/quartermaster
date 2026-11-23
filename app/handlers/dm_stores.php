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
        'access'     => store_access_ids($store),
        'pcts'       => store_price_pcts($storeId),
        // The DM's other campaigns, which the store can be copied to.
        'elsewhere'  => q_all('SELECT id, name, ruleset FROM campaigns WHERE owner_user_id = ? AND id <> ? ORDER BY name, id', [owner_id(), campaign_id()]),
    ], $store['name']);
}

/**
 * The price shares posted for the party, [character id => percent], with 100% and blanks left out. Characters the
 * page didn't show (it predates them, or prices per character) keep $current's share. Null if one isn't a percentage.
 */
function posted_price_pcts(array $characterIds, array $current): ?array
{
    if (!isset($_POST['pct'])) {
        return $current;
    }
    $posted = $_POST['pct'];
    if (!is_array($posted)) {
        return null;
    }
    $pcts = [];
    foreach ($characterIds as $cid) {
        if (!array_key_exists($cid, $posted)) {
            if (isset($current[$cid])) {
                $pcts[$cid] = $current[$cid];
            }
            continue;
        }
        if (!is_string($posted[$cid])) {
            return null;
        }
        $text = trim(str_replace('%', '', $posted[$cid]));
        if ($text === '') {
            continue;
        }
        if (!ctype_digit($text) || (int)$text > MAX_PRICE_PCT) {
            return null;
        }
        if ((int)$text !== 100) {
            $pcts[$cid] = (int)$text;
        }
    }
    return $pcts;
}

/**
 * Who a list of the party's names with ticks ("Who can shop here", a shelf line's "Seen by") leaves ticked, as ids.
 * The list posts $field[] for the ticked and {$field}_shown[] for every name it showed. A character it didn't show
 * (who joined after the page was drawn) keeps what they had: in if $current is null (everyone), else if in $current.
 * Pages drawn before {$field}_shown existed showed everyone.
 */
function posted_party_choice(array $allIds, string $field, ?array $current): array
{
    $ticked = array_map('intval', (array)($_POST[$field] ?? []));
    $shown = isset($_POST[$field . '_shown']) ? array_map('intval', (array)$_POST[$field . '_shown']) : $allIds;
    return array_values(array_filter($allIds, fn($cid) => in_array($cid, $shown, true)
        ? in_array($cid, $ticked, true)
        : ($current === null || in_array($cid, $current, true))));
}

function dm_store_update(int $storeId): void
{
    require_dm();
    $store = store($storeId) ?? abort(404, 'No such store.');
    $name = post_str('name', 100) ?: $store['name'];
    $allIds = array_map('intval', array_column(party_names(), 'id'));
    $chosen = posted_party_choice($allIds, 'access', store_access_ids($store));
    $pcts = posted_price_pcts($allIds, store_price_pcts($storeId));
    if ($pcts === null) {
        htmx_fail('Write each character’s prices as a percentage from 0 to ' . MAX_PRICE_PCT . ', like 100 for the usual prices or 120 for a fifth more.');
    }

    transaction(function () use ($storeId, $name, $chosen, $allIds, $pcts) {
        // Everyone ticked = no restriction (so new characters can shop too). Nobody ticked = nobody.
        $restricted = count($chosen) < count($allIds);
        q(
            'UPDATE stores SET name = ?, kind = ?, blurb = ?, restricted = ? WHERE id = ?',
            [$name, post_str('kind', 100), post_str('blurb', 1000), $restricted ? 1 : 0, $storeId]
        );
        q('DELETE FROM store_access WHERE store_id = ?', [$storeId]);
        if ($restricted) {
            foreach ($chosen as $cid) {
                q('INSERT INTO store_access (store_id, character_id) VALUES (?, ?)', [$storeId, $cid]);
            }
        }
        $before = store_price_pcts($storeId);
        q('DELETE FROM store_prices WHERE store_id = ?', [$storeId]);
        foreach ($pcts as $cid => $pct) {
            q('INSERT INTO store_prices (store_id, character_id, price_pct) VALUES (?, ?, ?)', [$storeId, $cid, $pct]);
        }
        // Open carts show the new prices straight away.
        foreach ($allIds as $cid) {
            if (($before[$cid] ?? 100) !== ($pcts[$cid] ?? 100)) {
                reprice_open_cart($storeId, $cid);
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
 * for the DM to say who shops there before opening it. Carts, who can shop here, what each character pays and who
 * sees each shelf line belong to this party, so they aren't copied: the copy and its shelves start unrestricted, at
 * 100% for everyone.
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
    fragment('partials/stock_body', ['stock' => store_stock_rows($storeId), 'party' => party_names()]);
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
    fragment('partials/stock_row', ['s' => stock_row($stockId), 'party' => party_names()]);
}

/**
 * Who sees one shelf line: everyone ticked is everyone (characters who join later too), otherwise only those ticked.
 * Characters who no longer see it lose it from their open carts, as when it's pulled from the shelf.
 */
function dm_stock_viewers(int $stockId): void
{
    require_dm();
    $row = stock_row($stockId) ?? abort(404, 'That item is no longer stocked.');
    $party = party_names();
    $allIds = array_map('intval', array_column($party, 'id'));
    $chosen = posted_party_choice($allIds, 'viewers', $row['viewers']);
    $everyone = count($chosen) === count($allIds);

    $dropped = transaction(function () use ($stockId, $chosen, $everyone) {
        q('UPDATE store_stock SET restricted = ? WHERE id = ?', [$everyone ? 0 : 1, $stockId]);
        q('DELETE FROM stock_viewers WHERE stock_id = ?', [$stockId]);
        if (!$everyone) {
            foreach ($chosen as $cid) {
                q('INSERT INTO stock_viewers (stock_id, character_id) VALUES (?, ?)', [$stockId, $cid]);
            }
        }
        return remove_unseen_from_open_carts($stockId);
    });
    $label = named_with_notes($row['name'], $row['notes']);
    $names = array_column(array_filter($party, fn($c) => in_array((int)$c['id'], $chosen, true)), 'name');
    toast(match (true) {
        $everyone => "Everyone can see {$label}.",
        !$chosen  => "{$label} is hidden from every player.",
        default   => 'Only ' . implode(', ', $names) . " can see {$label}.",
    } . ($dropped ? ' Taken out of ' . ($dropped === 1 ? 'an open cart' : "{$dropped} open carts") . '.' : ''));
    fragment('partials/stock_row', ['s' => stock_row($stockId), 'party' => $party, 'seenOpen' => true]);
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
