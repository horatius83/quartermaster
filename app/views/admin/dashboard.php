<?php /** @var array $counts @var array $srd */ ?>
<main class="main">
  <header>
    <h1 class="page-title">Dashboard</h1>
    <p class="muted" style="margin:6px 0 0;max-width:680px">The whole install at a glance, and the SRD items each system’s catalog can start from.</p>
  </header>

  <section class="grid-stats" aria-label="At a glance">
    <div class="card stat"><div class="stat-value"><?= (int)$counts['dms'] ?></div><div class="muted">DMs</div></div>
    <div class="card stat"><div class="stat-value"><?= (int)$counts['players'] ?></div><div class="muted">Player logins</div></div>
    <div class="card stat"><div class="stat-value"><?= (int)$counts['campaigns'] ?></div><div class="muted">Campaigns</div></div>
    <div class="card stat"><div class="stat-value"><?= number_format((int)$counts['items']) ?></div><div class="muted">Catalog items</div></div>
  </section>

  <section class="card-stack" aria-labelledby="srd-h">
    <h2 id="srd-h" class="section-title">SRD items</h2>
    <p class="muted" style="margin:0;max-width:680px">Adds a system’s weapons, armor, ammunition, gear and tools to its catalog, which every DM’s campaigns of that system share. Items the catalog already has are left as they are, so DMs’ own prices and descriptions are never overwritten.</p>
    <div class="grid-cards">
      <?php foreach ($srd as $ruleset => $s):
        $missing = $s['total'] - $s['present'];
        $loaded = $missing === 0;
        $label = $s['label']; ?>
        <article class="card card-stack" aria-labelledby="srd-<?= e($ruleset) ?>">
          <div class="row-between" style="align-items:baseline">
            <h3 class="card-title" id="srd-<?= e($ruleset) ?>"><?= e($label) ?> SRD</h3>
            <span class="status <?= $loaded ? 'status-open' : 'status-hidden' ?>"><?= $loaded ? 'Loaded' : ($s['present'] ? 'Partly loaded' : 'Not loaded') ?></span>
          </div>
          <p class="muted small" style="margin:0">
            <?php if ($loaded): ?>
              All <?= $s['total'] ?> items are already in the <?= e($label) ?> catalog. There’s nothing to add.
            <?php elseif ($s['present']): ?>
              <?= $s['present'] ?> of <?= $s['total'] ?> are in the <?= e($label) ?> catalog. Loading adds the other <?= $missing ?>, which were deleted, renamed or never loaded.
            <?php else: ?>
              None of the <?= $s['total'] ?> items are in the <?= e($label) ?> catalog yet.
            <?php endif; ?>
          </p>
          <form method="post" action="<?= e(url('/admin/srd')) ?>"<?php if (!$loaded): ?> hx-confirm="Add <?= $missing ?> <?= e($label) ?> SRD <?= $missing === 1 ? 'item' : 'items' ?> to the catalog every DM’s <?= e($label) ?> campaigns share?"<?php endif; ?>>
            <?= csrf_field() ?>
            <input type="hidden" name="edition" value="<?= e($ruleset) ?>">
            <button class="btn<?= $loaded ? '' : ' btn-primary' ?>" type="submit"<?= $loaded ? ' disabled' : '' ?>>Load <?= e($label) ?> SRD items</button>
          </form>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
</main>
