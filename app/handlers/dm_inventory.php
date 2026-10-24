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
        echo render('partials/inventory_body', ['rows' => inventory_rows($characterId, $cat), 'char' => $char, 'filtered' => $cat !== '']);
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
    ], $char['name'] . '’s pack');
}

function dm_inventory_give(int $characterId): void
{
    require_dm();
    $char = character($characterId) ?? abort(404, 'No such character.');
    $name = post_str('item', 150);
    $qty  = max(1, post_int('quantity', 1));
    if ($name === '') {
        htmx_fail('Type the name of the item to give.');
    }
    $price = parse_price(post_str('price', 40)) ?? 0;
    $item = find_or_create_item($name, post_int('category_id'), post_float('weight'), $price);
    $notes = post_str('notes', 255);
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
    // Apply the change from the purse the form showed, so coin spent on a cart approved meanwhile isn't overwritten.
    $delta = coins_from_parts(post_int('gp'), post_int('sp'), post_int('cp')) - post_int('coin_was', (int)$char['coin_cp']);
    $coin = transaction(function () use ($char, $delta, $characterId) {
        $current = (int)q_val('SELECT coin_cp FROM characters WHERE id = ?' . for_update(), [$characterId]);
        $coin = max(0, $current + $delta);
        q(
            'UPDATE characters SET coin_cp = ?, class_label = ?, carry_capacity = ? WHERE id = ?',
            [$coin, post_str('class_label', 100), max(0, post_float('carry_capacity', (float)$char['carry_capacity'])), $characterId]
        );
        log_inventory($characterId, 'coin', coinCp: $coin - $current);
        return $coin;
    });
    toast('Saved ' . $char['name'] . '’s purse: ' . coins($coin) . '.');
    $char = character($characterId);
    echo render('partials/character_sheet', ['char' => $char]);
    echo render('partials/carried_oob', ['char' => $char, 'carried' => carried_weight($characterId)]);
    exit;
}

/** Lock a pack row inside a transaction and return its current quantity. */
function locked_inventory_qty(int $rowId): int
{
    $qty = q_val('SELECT quantity FROM inventory WHERE id = ?' . for_update(), [$rowId]);
    if ($qty === null) {
        throw new RuntimeException('That item is no longer in the pack.');
    }
    return (int)$qty;
}

function dm_inv_qty(int $rowId): void
{
    require_dm();
    $row = inventory_row($rowId) ?? abort(404, 'That item is no longer in the pack.');
    $delta = post_int('delta') >= 0 ? 1 : -1;
    try {
        $newQty = transaction(function () use ($row, $rowId, $delta) {
            $newQty = locked_inventory_qty($rowId) + $delta;
            if ($newQty <= 0) {
                q('DELETE FROM inventory WHERE id = ?', [$rowId]);
            } else {
                q('UPDATE inventory SET quantity = ? WHERE id = ?', [$newQty, $rowId]);
            }
            log_inventory((int)$row['character_id'], 'adjust', $row, $delta, notes: $row['notes']);
            return $newQty;
        });
    } catch (RuntimeException $e) {
        htmx_fail($e->getMessage());
    }
    if ($newQty <= 0) {
        echo '';
    } else {
        echo render('partials/inventory_row', ['r' => inventory_row($rowId), 'characters' => other_characters((int)$row['character_id'])]);
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
            $qty = locked_inventory_qty($rowId);
            q('DELETE FROM inventory WHERE id = ?', [$rowId]);
            log_inventory((int)$row['character_id'], 'remove', $row, -$qty, notes: $row['notes']);
        });
    } catch (RuntimeException $e) {
        htmx_fail($e->getMessage());
    }
    toast("Removed {$row['name']}.");
    echo render('partials/carried_oob', ['char' => character((int)$row['character_id']), 'carried' => carried_weight((int)$row['character_id'])]);
    exit;
}

/** Move a whole stack to another character. */
function dm_inv_move(int $rowId): void
{
    require_dm();
    $row = inventory_row($rowId) ?? abort(404, 'That item is no longer in the pack.');
    $to = character(post_int('to')) ?? htmx_fail('Pick who should receive it.');
    if ((int)$to['id'] === (int)$row['character_id']) {
        htmx_fail("{$to['name']} already has it.");
    }
    $from = character((int)$row['character_id']);
    try {
        $qty = transaction(function () use ($row, $from, $to) {
            $qty = locked_inventory_qty((int)$row['id']);
            q('DELETE FROM inventory WHERE id = ?', [$row['id']]);
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
    echo render('partials/inventory_body', ['rows' => inventory_rows($characterId), 'char' => $char, 'filtered' => false]);
    echo render('partials/inventory_filters', ['char' => $char, 'cat' => '', 'oob' => true]);
    echo render('partials/carried_oob', ['char' => character($characterId), 'carried' => carried_weight($characterId)]);
    exit;
}
