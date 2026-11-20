<?php
declare(strict_types=1);

// The party purse and stash of a campaign that shares one (see set_shared_purse). The DM edits the purse and hands
// out, adds or takes away stash items here, as on a pack. Once sharing stops, the page stays until the stash is empty.

/** The party stash page, while the campaign shares a purse or its stash still holds something. */
function dm_party_stash(): void
{
    require_dm();
    $purse = party_purse();
    $shared = shares_purse();
    if (!$shared && !(int)$purse['stacks']) {
        flash('This campaign doesn’t share a party purse. Turn it on under Campaigns.');
        redirect('/dm/campaigns');
    }
    page('dm/party_stash', [
        'active'     => 'inventory',
        'characters' => party_names(),
        'rows'       => party_stash_rows(),
        'purse'      => $purse,
        'shared'     => $shared,
    ], 'Party stash');
}

/** Save the party purse, applying the change from what the form showed, as a character's sheet does. */
function dm_party_stash_purse(): void
{
    require_dm();
    $delta = posted_coin_delta();
    try {
        $coin = transaction(function () use ($delta) {
            $campaign = lock_campaign(campaign_id());
            if (!shares_purse($campaign)) {
                throw new RuntimeException('This campaign no longer shares a party purse.');
            }
            $current = (int)$campaign['purse_cp'];
            $coin = purse_after($current, $delta);
            q('UPDATE campaigns SET purse_cp = ? WHERE id = ?', [$coin, $campaign['id']]);
            log_party((int)$campaign['id'], 'coin', coinCp: $coin - $current);
            return $coin;
        });
    } catch (RuntimeException $e) {
        htmx_fail($e->getMessage());
    }
    toast('Saved the party purse: ' . coins($coin) . '.');
    fragment('partials/party_purse_form', ['coin' => $coin]);
}

function dm_party_stash_give(): void
{
    require_dm();
    if (!shares_purse()) {
        htmx_fail('This campaign doesn’t share a party purse.');
    }
    [$item, $qty, $notes] = posted_give();
    transaction(function () use ($item, $qty, $notes) {
        lock_campaign(campaign_id());
        add_to_party_stash(campaign_id(), (int)$item['id'], $qty, $notes);
        log_party(campaign_id(), 'give', ['item_id' => $item['id'], 'name' => $item['name']], $qty, notes: $notes);
    });
    toast("Put {$qty} × {$item['name']} in the party stash.");
    header('HX-Trigger-After-Swap: ' . json_encode(['qm-reset' => ['form' => '#give-form']]));
    fragment('partials/inventory_body', ['rows' => party_stash_rows(), 'stash' => true, 'filtered' => false, 'shared' => true]);
}

/** A stash stack in this campaign, or a 404. */
function dm_stash_row(int $rowId): array
{
    return party_stash_row($rowId) ?? abort(404, 'That item is no longer in the party stash.');
}

function dm_stash_qty(int $rowId): void
{
    require_dm();
    $row = dm_stash_row($rowId);
    $delta = post_int('delta') >= 0 ? 1 : -1;
    try {
        $newQty = transaction(function () use ($row, $rowId, $delta) {
            $newQty = step_stack('party_items', $rowId, $delta);
            log_party((int)$row['campaign_id'], 'adjust', $row, $delta, notes: $row['notes']);
            return $newQty;
        });
    } catch (RuntimeException $e) {
        htmx_fail($e->getMessage());
    }
    if ($newQty <= 0) {
        exit;
    }
    fragment('partials/inventory_row', ['r' => party_stash_row($rowId), 'characters' => party_names(), 'stash' => true, 'shared' => shares_purse()]);
}

function dm_stash_delete(int $rowId): void
{
    require_dm();
    $row = dm_stash_row($rowId);
    try {
        transaction(function () use ($row) {
            $qty = take_from_stack('party_items', (int)$row['id'], null);
            log_party((int)$row['campaign_id'], 'remove', $row, -$qty, notes: $row['notes']);
        });
    } catch (RuntimeException $e) {
        htmx_fail($e->getMessage());
    }
    toast("Removed {$row['name']} from the party stash.");
    exit;
}

/** Hand a whole stash stack to a character. */
function dm_stash_move(int $rowId): void
{
    require_dm();
    $row = dm_stash_row($rowId);
    $to = character(post_int('to')) ?? htmx_fail('Pick who should receive it.');
    try {
        $qty = stash_to_pack($row, $to, null);
    } catch (RuntimeException $e) {
        htmx_fail($e->getMessage());
    }
    toast("Gave {$qty} × {$row['name']} to {$to['name']}.");
    exit;
}
