<?php
declare(strict_types=1);

function dm_campaigns(): void
{
    require_dm();
    page('dm/campaigns', [
        'active'    => 'campaigns',
        'campaigns' => campaigns(),
        'current'   => current_campaign(),
    ], 'Campaigns');
}

function dm_campaign_create(): void
{
    require_dm();
    $name = post_str('name', 100);
    if ($name === '') {
        flash('Give the campaign a name.', 'error');
        redirect('/dm/campaigns');
    }
    switch_campaign(create_campaign($name));
    flash("{$name} begins. Add its adventurers to get started.");
    redirect('/dm/party');
}

function dm_campaign_update(int $id): void
{
    require_dm();
    $campaign = campaign($id) ?? abort(404, 'No such campaign.');
    $name = post_str('name', 100);
    if ($name === '') {
        flash('A campaign needs a name.', 'error');
        redirect('/dm/campaigns');
    }
    q('UPDATE campaigns SET name = ? WHERE id = ?', [$name, $id]);
    flash("Renamed {$campaign['name']} to {$name}.");
    redirect('/dm/campaigns');
}

/** Show another campaign on every DM screen. Goes to the dashboard, since the page you were on belongs to the old one. */
function dm_campaign_switch(): void
{
    require_dm();
    $campaign = campaign(post_int('campaign_id')) ?? abort(404, 'No such campaign.');
    switch_campaign((int)$campaign['id']);
    flash("Now running {$campaign['name']}.");
    redirect('/dm');
}

/**
 * Only an empty campaign can go, so a slip can't wipe out a party. Its pack history goes with it (ON DELETE CASCADE).
 * The checks run with the campaign row locked: adding a character or store to it has to wait, so none can sneak in.
 */
function dm_campaign_delete(int $id): void
{
    require_dm();
    try {
        $campaign = transaction(function () use ($id) {
            $campaign = q_one('SELECT * FROM campaigns WHERE id = ?' . for_update(), [$id]);
            if (!$campaign) {
                throw new RuntimeException('That campaign is already gone.');
            }
            if (q_val('SELECT 1 FROM characters WHERE campaign_id = ? UNION ALL SELECT 1 FROM stores WHERE campaign_id = ? LIMIT 1', [$id, $id])) {
                throw new RuntimeException("Remove {$campaign['name']}’s characters and stores before deleting it.");
            }
            if ((int)q_val('SELECT COUNT(*) FROM campaigns') === 1) {
                throw new RuntimeException('This is your only campaign, so it stays.');
            }
            q('DELETE FROM campaigns WHERE id = ?', [$id]);
            return $campaign;
        });
    } catch (RuntimeException $e) {
        flash($e->getMessage(), 'error');
        redirect('/dm/campaigns');
    }
    if ($id === campaign_id()) {
        unset($_SESSION['campaign_id']);
    }
    flash("Deleted {$campaign['name']}.");
    redirect('/dm/campaigns');
}
