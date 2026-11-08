<main class="main">
  <header>
    <h1 class="page-title">Campaigns</h1>
    <p class="muted" style="margin:6px 0 0;max-width:680px">Each campaign keeps its own party, stores, carts and pack history. Only you see your campaigns. Each campaign plays one system, chosen when it starts, and only sees that system’s items, which every DM’s campaigns of that system share. The other pages show the campaign you’re running now; switch with the menu at the top of the sidebar.</p>
  </header>

  <form class="card card-dashed form-row" method="post" action="<?= e(url('/dm/campaigns')) ?>">
    <?= csrf_field() ?>
    <label class="field grow">New campaign
      <input name="name" required maxlength="100" placeholder="e.g. Curse of the Sunken Crown">
    </label>
    <label class="field mid">System <span class="small">(can’t be changed later)</span>
      <select name="ruleset"><?php foreach (CAMPAIGN_RULESETS as $value => $label): ?><option value="<?= e($value) ?>"><?= e($label) ?></option><?php endforeach; ?></select>
    </label>
    <button class="btn btn-primary" type="submit">Start campaign</button>
  </form>

  <?php if (array_filter($campaigns, fn($c) => (int)$c['ruleset_open'])): ?>
    <p class="card card-dashed" style="margin:0"><strong>Set your older campaigns’ systems.</strong> Campaigns from before systems were added all count as 5e. If one plays something else, pick it below: the items in its packs, stores, carts and history are copied into that system’s catalog, and the campaign moves to the copies. Catalog items it doesn’t use stay 5e only. Each campaign can do this once, even to confirm 5e.</p>
  <?php endif; ?>

  <div class="table-wrap">
    <table class="ledger">
      <caption>Your campaigns</caption>
      <thead><tr><th>Name</th><th>System</th><th>Party</th><th>Stores</th><th>Started</th><th class="right">Actions</th></tr></thead>
      <tbody>
        <?php foreach ($campaigns as $c):
          $isCurrent = (int)$c['id'] === (int)($current['id'] ?? 0);
          $isEmpty = (int)$c['character_count'] === 0 && (int)$c['store_count'] === 0; ?>
          <tr id="campaign-<?= (int)$c['id'] ?>">
            <td>
              <form class="move-form" method="post" action="<?= e(url('/dm/campaigns/' . $c['id'])) ?>">
                <?= csrf_field() ?>
                <label class="visually-hidden" for="cname-<?= (int)$c['id'] ?>">Name of <?= e($c['name']) ?></label>
                <input class="input-sm" style="width:240px" id="cname-<?= (int)$c['id'] ?>" name="name" value="<?= e($c['name']) ?>" required maxlength="100">
                <button class="link-btn" type="submit">Rename</button>
                <?php if ($isCurrent): ?><span class="status status-open">Running now</span><?php endif; ?>
              </form>
            </td>
            <td class="nowrap">
              <?php if ((int)$c['ruleset_open']): ?>
                <form class="move-form" method="post" action="<?= e(url('/dm/campaigns/' . $c['id'] . '/ruleset')) ?>" hx-confirm="Set the system of <?= e($c['name']) ?>? This can only be done once.">
                  <?= csrf_field() ?>
                  <label class="visually-hidden" for="cruleset-<?= (int)$c['id'] ?>">System of <?= e($c['name']) ?></label>
                  <select class="input-sm" style="width:auto" id="cruleset-<?= (int)$c['id'] ?>" name="ruleset">
                    <?php foreach (CAMPAIGN_RULESETS as $value => $label): ?><option value="<?= e($value) ?>"<?= $c['ruleset'] === $value ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
                  </select>
                  <button class="link-btn" type="submit">Set once</button>
                </form>
              <?php else: ?>
                <?= e(ruleset_label($c['ruleset'])) ?>
              <?php endif; ?>
            </td>
            <td class="nowrap"><?= (int)$c['character_count'] ?> <?= (int)$c['character_count'] === 1 ? 'adventurer' : 'adventurers' ?></td>
            <td class="nowrap"><?= (int)$c['store_count'] ?></td>
            <td class="nowrap muted"><?= e(substr($c['created_at'], 0, 10)) ?></td>
            <td class="right nowrap">
              <?php if (!$isCurrent): ?>
                <form method="post" action="<?= e(url('/dm/campaigns/switch')) ?>" style="display:inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="campaign_id" value="<?= (int)$c['id'] ?>">
                  <button class="btn" type="submit">Switch to</button>
                </form>
              <?php endif; ?>
              <?php if ($isEmpty && count($campaigns) > 1): ?>
                <form method="post" action="<?= e(url('/dm/campaigns/' . $c['id'] . '/delete')) ?>" style="display:inline"
                      hx-confirm="Delete <?= e($c['name']) ?>? Its pack history goes with it.">
                  <?= csrf_field() ?>
                  <button class="link-btn danger" type="submit">Delete</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if (!$campaigns): ?><tr><td colspan="6" class="empty">No campaigns yet. Start one above, picking the system it plays.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>
  <p class="muted small" style="margin:0">A new campaign’s system can’t be changed. A campaign can be deleted once its characters and stores are gone, so if you pick the wrong system, start another and delete the empty one.</p>
</main>
