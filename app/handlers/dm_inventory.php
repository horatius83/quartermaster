<?php
declare(strict_types=1);

function dm_inventory_index(): void
{
    require_dm();
    $first = q_val('SELECT id FROM characters ORDER BY name LIMIT 1');
    if (!$first) {
        flash('Add a character to the party first.');
        redirect('/dm/party');
    }
    redirect('/dm/inventory/' . $first);
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
        'characters' => q_all('SELECT id, name FROM characters ORDER BY name'),
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
    add_to_inventory($characterId, (int)$item['id'], $qty, post_str('notes', 255));

    toast("Gave {$qty} × {$item['name']} to {$char['name']}.");
    header('HX-Trigger-After-Swap: ' . json_encode(['qm-reset' => ['form' => '#give-form']]));
    inventory_fragment_with_total($characterId);
}

/** Save coin, class and carrying capacity. */
function dm_inventory_sheet(int $characterId): void
{
    require_dm();
    $char = character($characterId) ?? abort(404, 'No such character.');
    $coin = coins_from_parts(post_int('gp'), post_int('sp'), post_int('cp'));
    q(
        'UPDATE characters SET coin_cp = ?, class_label = ?, carry_capacity = ? WHERE id = ?',
        [$coin, post_str('class_label', 100), max(0, post_float('carry_capacity', (float)$char['carry_capacity'])), $characterId]
    );
    toast('Saved ' . $char['name'] . '’s purse: ' . coins($coin) . '.');
    $char = character($characterId);
    echo render('partials/character_sheet', ['char' => $char]);
    echo render('partials/carried_oob', ['char' => $char, 'carried' => carried_weight($characterId)]);
    exit;
}

function dm_inv_qty(int $rowId): void
{
    require_dm();
    $row = inventory_row($rowId) ?? abort(404, 'That item is no longer in the pack.');
    $newQty = (int)$row['quantity'] + (post_int('delta') >= 0 ? 1 : -1);
    if ($newQty <= 0) {
        q('DELETE FROM inventory WHERE id = ?', [$rowId]);
        echo '';
    } else {
        q('UPDATE inventory SET quantity = ? WHERE id = ?', [$newQty, $rowId]);
        echo render('partials/inventory_row', ['r' => inventory_row($rowId), 'characters' => other_characters((int)$row['character_id'])]);
    }
    echo render('partials/carried_oob', ['char' => character((int)$row['character_id']), 'carried' => carried_weight((int)$row['character_id'])]);
    exit;
}

function dm_inv_delete(int $rowId): void
{
    require_dm();
    $row = inventory_row($rowId) ?? abort(404, 'That item is no longer in the pack.');
    q('DELETE FROM inventory WHERE id = ?', [$rowId]);
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
    transaction(function () use ($row, $to) {
        q('DELETE FROM inventory WHERE id = ?', [$row['id']]);
        add_to_inventory((int)$to['id'], (int)$row['item_id'], (int)$row['quantity'], $row['notes']);
    });
    toast("Moved {$row['quantity']} × {$row['name']} to {$to['name']}.");
    echo render('partials/carried_oob', ['char' => character((int)$row['character_id']), 'carried' => carried_weight((int)$row['character_id'])]);
    exit;
}

function other_characters(int $exceptId): array
{
    return q_all('SELECT id, name FROM characters WHERE id <> ? ORDER BY name', [$exceptId]);
}

function inventory_fragment_with_total(int $characterId): never
{
    $char = character($characterId);
    echo render('partials/inventory_body', ['rows' => inventory_rows($characterId), 'char' => $char, 'filtered' => false]);
    echo render('partials/inventory_filters', ['char' => $char, 'cat' => '', 'oob' => true]);
    echo render('partials/carried_oob', ['char' => character($characterId), 'carried' => carried_weight($characterId)]);
    exit;
}
