<?php
declare(strict_types=1);

function dm_inventory_index(): void
{
    require_dm();
    $first = party_names()[0] ?? null;
    if (!$first) {
        flash('Add a character to the party first.');
        redirect('/dm/party');
    }
    redirect('/dm/inventory/' . $first['id']);
}

function dm_inventory(int $characterId): void
{
    require_dm();
    $char = character($characterId) ?? abort(404, 'No such character.');
    $cat = get_str('cat', 30);

    // htmx category filter only needs the table body.
    if (htmx_target() === 'inv-body') {
        echo render('partials/inventory_body', ['rows' => inventory_rows($characterId, $cat), 'char' => $char, 'filtered' => $cat !== '', 'shared' => shares_purse()]);
        echo render('partials/inventory_filters', ['char' => $char, 'cat' => $cat, 'oob' => true]);
        exit;
    }

    page('dm/inventory', [
        'active'     => 'inventory',
        'char'       => $char,
        'characters' => party_names(),
        'rows'       => inventory_rows($characterId, $cat),
        'cat'        => $cat,
        'carried'    => carried_weight($characterId),
        'shared'     => shares_purse(),
        'showStash'  => shares_purse() || party_stash_held(),
    ], $char['name'] . '’s pack');
}

/**
 * The item, quantity and notes posted on a give form (partials/give_form), finding the item in the catalog or adding it.
 * @return array{0: array, 1: int, 2: string}
 */
function posted_give(): array
{
    $name = post_str('item', 150);
    if ($name === '') {
        htmx_fail('Type the name of the item to give.');
    }
    $item = find_or_create_item($name, post_int('category_id'), post_float('weight'), parse_price(post_str('price', 40)) ?? 0);
    return [$item, max(1, post_int('quantity', 1)), post_str('notes', 255)];
}

function dm_inventory_give(int $characterId): void
{
    require_dm();
    $char = character($characterId) ?? abort(404, 'No such character.');
    [$item, $qty, $notes] = posted_give();
    transaction(function () use ($characterId, $item, $qty, $notes) {
        add_to_inventory($characterId, (int)$item['id'], $qty, $notes);
        log_inventory($characterId, 'give', ['item_id' => $item['id'], 'name' => $item['name']], $qty, notes: $notes);
    });

    toast("Gave {$qty} × {$item['name']} to {$char['name']}.");
    header('HX-Trigger-After-Swap: ' . json_encode(['qm-reset' => ['form' => '#give-form']]));
    inventory_fragment_with_total($characterId);
}

/** Save coin, class and carrying capacity. */
function dm_inventory_sheet(int $characterId): void
{
    require_dm();
    $char = character($characterId) ?? abort(404, 'No such character.');
    // A sheet drawn while the party shared a purse has no coin fields, so its delta is null and the coin stays as it is.
    $delta = posted_coin_delta();
    $shared = transaction(function () use ($char, $delta, $characterId) {
        $purse = lock_purse($characterId);
        // While the party shares a purse, the coin is the party's (edited on the party stash page), so it's left alone.
        $current = (int)$purse['char']['coin_cp'];
        $coin = $purse['shared'] ? $current : purse_after($current, $delta);
        q(
            'UPDATE characters SET coin_cp = ?, class_label = ?, carry_capacity = ? WHERE id = ?',
            [$coin, post_str('class_label', 100), max(0, post_float('carry_capacity', (float)$char['carry_capacity'])), $characterId]
        );
        log_inventory($characterId, 'coin', coinCp: $coin - $current);
        return $purse['shared'];
    });
    $char = character($characterId);
    toast($shared ? 'Saved ' . $char['name'] . '’s sheet.' : 'Saved ' . $char['name'] . '’s purse: ' . coins((int)$char['coin_cp']) . '.');
    echo render('partials/character_sheet', ['char' => $char, 'shared' => $shared]);
    echo render('partials/carried_oob', ['char' => $char, 'carried' => carried_weight($characterId)]);
    exit;
}

function dm_inv_qty(int $rowId): void
{
    require_dm();
    $row = inventory_row($rowId) ?? abort(404, 'That item is no longer in the pack.');
    $delta = post_int('delta') >= 0 ? 1 : -1;
    try {
        $newQty = transaction(function () use ($row, $rowId, $delta) {
            $newQty = step_stack('inventory', $rowId, $delta);
            log_inventory((int)$row['character_id'], 'adjust', $row, $delta, notes: $row['notes']);
            return $newQty;
        });
    } catch (RuntimeException $e) {
        htmx_fail($e->getMessage());
    }
    if ($newQty <= 0) {
        echo '';
    } else {
        echo render('partials/inventory_row', ['r' => inventory_row($rowId), 'characters' => other_characters((int)$row['character_id']), 'shared' => shares_purse()]);
    }
    echo render('partials/carried_oob', ['char' => character((int)$row['character_id']), 'carried' => carried_weight((int)$row['character_id'])]);
    exit;
}

function dm_inv_delete(int $rowId): void
{
    require_dm();
    $row = inventory_row($rowId) ?? abort(404, 'That item is no longer in the pack.');
    try {
        transaction(function () use ($row, $rowId) {
            $qty = take_from_stack('inventory', $rowId, null);
            log_inventory((int)$row['character_id'], 'remove', $row, -$qty, notes: $row['notes']);
        });
    } catch (RuntimeException $e) {
        htmx_fail($e->getMessage());
    }
    toast("Removed {$row['name']}.");
    echo render('partials/carried_oob', ['char' => character((int)$row['character_id']), 'carried' => carried_weight((int)$row['character_id'])]);
    exit;
}

/** Move a whole stack to another character, or to the party stash. */
function dm_inv_move(int $rowId): void
{
    require_dm();
    $row = inventory_row($rowId) ?? abort(404, 'That item is no longer in the pack.');
    if (post_str('to', 10) === 'party') {
        if (!shares_purse()) {
            htmx_fail('This campaign no longer shares a party stash. Reload the page.');
        }
        try {
            $qty = pack_to_stash($row, null);
        } catch (RuntimeException $e) {
            htmx_fail($e->getMessage());
        }
        toast("Moved {$qty} × {$row['name']} to the party stash.");
        echo render('partials/carried_oob', ['char' => character((int)$row['character_id']), 'carried' => carried_weight((int)$row['character_id'])]);
        exit;
    }
    $to = character(post_int('to')) ?? htmx_fail('Pick who should receive it.');
    if ((int)$to['id'] === (int)$row['character_id']) {
        htmx_fail("{$to['name']} already has it.");
    }
    $from = character((int)$row['character_id']);
    try {
        $qty = transaction(function () use ($row, $from, $to) {
            $qty = take_from_stack('inventory', (int)$row['id'], null);
            add_to_inventory((int)$to['id'], (int)$row['item_id'], $qty, $row['notes']);
            log_inventory((int)$from['id'], 'move_out', $row, -$qty, notes: $row['notes'], detail: 'To ' . $to['name']);
            log_inventory((int)$to['id'], 'move_in', $row, $qty, notes: $row['notes'], detail: 'From ' . $from['name']);
            return $qty;
        });
    } catch (RuntimeException $e) {
        htmx_fail($e->getMessage());
    }
    toast("Moved {$qty} × {$row['name']} to {$to['name']}.");
    echo render('partials/carried_oob', ['char' => character((int)$row['character_id']), 'carried' => carried_weight((int)$row['character_id'])]);
    exit;
}

function other_characters(int $exceptId): array
{
    return array_values(array_filter(party_names(), fn($c) => (int)$c['id'] !== $exceptId));
}

function inventory_fragment_with_total(int $characterId): never
{
    $char = character($characterId);
    echo render('partials/inventory_body', ['rows' => inventory_rows($characterId), 'char' => $char, 'filtered' => false, 'shared' => shares_purse()]);
    echo render('partials/inventory_filters', ['char' => $char, 'cat' => '', 'oob' => true]);
    echo render('partials/carried_oob', ['char' => character($characterId), 'carried' => carried_weight($characterId)]);
    exit;
}
