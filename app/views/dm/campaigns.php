<main class="main">
  <header>
    <h1 class="page-title">Campaigns</h1>
    <p class="muted" style="margin:6px 0 0;max-width:680px">Each campaign keeps its own party, stores, carts and pack history. The item catalog is shared, so every campaign can stock the same gear. The other pages show the campaign you’re running now; switch with the menu at the top of the sidebar.</p>
  </header>

  <form class="card card-dashed form-row" method="post" action="<?= e(url('/dm/campaigns')) ?>">
    <?= csrf_field() ?>
    <label class="field grow">New campaign
      <input name="name" required maxlength="100" placeholder="e.g. Curse of the Sunken Crown">
    </label>
    <button class="btn btn-primary" type="submit">Start campaign</button>
  </form>

  <div class="table-wrap">
    <table class="ledger">
      <caption>Your campaigns</caption>
      <thead><tr><th>Name</th><th>Party</th><th>Stores</th><th>Started</th><th class="right">Actions</th></tr></thead>
      <tbody>
        <?php foreach ($campaigns as $c):
          $isCurrent = (int)$c['id'] === (int)$current['id'];
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
      </tbody>
    </table>
  </div>
  <p class="muted small" style="margin:0">A campaign can be deleted once its characters and stores are gone.</p>
</main>
