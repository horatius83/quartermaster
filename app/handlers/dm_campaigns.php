<?php
declare(strict_types=1);

function dm_campaigns(): void
{
    require_dm(needsCampaign: false);
    page('dm/campaigns', [
        'active'    => 'campaigns',
        'campaigns' => campaigns(),
        'current'   => current_campaign(),
    ], 'Campaigns');
}

function dm_campaign_create(): void
{
    require_dm(needsCampaign: false);
    $name = post_str('name', 100);
    if ($name === '') {
        flash('Give the campaign a name.', 'error');
        redirect('/dm/campaigns');
    }
    switch_campaign(create_campaign($name, owner_id(), posted_campaign_ruleset()));
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

/** A campaign from before systems says which one it plays. This works once, and only for those campaigns. */
function dm_campaign_set_ruleset(int $id): void
{
    require_dm();
    $ruleset = post_str('ruleset', 10);
    if (!isset(CAMPAIGN_RULESETS[$ruleset])) {
        flash('Pick one of the systems in the list.', 'error');
        redirect('/dm/campaigns');
    }
    try {
        [$campaign, $copied] = transaction(function () use ($id, $ruleset) {
            $campaign = q_one('SELECT * FROM campaigns WHERE id = ? AND owner_user_id = ?' . for_update(), [$id, owner_id()]);
            if (!$campaign) {
                throw new RuntimeException('No such campaign.');
            }
            if (!(int)$campaign['ruleset_open']) {
                throw new RuntimeException("{$campaign['name']}’s system is already set, and can’t be changed.");
            }
            return [$campaign, settle_campaign_system($campaign, $ruleset)];
        });
    } catch (PDOException $e) {
        throw $e; // a PDOException is a RuntimeException too, but it's a real error, not a message for the DM
    } catch (RuntimeException $e) {
        flash($e->getMessage(), 'error');
        redirect('/dm/campaigns');
    }
    $label = ruleset_label($ruleset);
    flash($ruleset === $campaign['ruleset']
        ? "{$campaign['name']} plays {$label}, and that’s now fixed."
        : "{$campaign['name']} now plays {$label}, and that’s now fixed. Its packs and stores moved to {$label} items, and {$copied} " . ($copied === 1 ? 'item was' : 'items were') . " copied into the {$label} catalog.");
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
            $campaign = q_one('SELECT * FROM campaigns WHERE id = ? AND owner_user_id = ?' . for_update(), [$id, owner_id()]);
            if (!$campaign) {
                throw new RuntimeException('That campaign is already gone.');
            }
            if (q_val('SELECT 1 FROM characters WHERE campaign_id = ? UNION ALL SELECT 1 FROM stores WHERE campaign_id = ? LIMIT 1', [$id, $id])) {
                throw new RuntimeException("Remove {$campaign['name']}’s characters and stores before deleting it.");
            }
            if ((int)q_val('SELECT COUNT(*) FROM campaigns WHERE owner_user_id = ?', [owner_id()]) === 1) {
                throw new RuntimeException('This is your only campaign, so it stays.');
            }
            q('DELETE FROM campaigns WHERE id = ?', [$id]);
            return $campaign;
        });
    } catch (PDOException $e) {
        throw $e;
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
