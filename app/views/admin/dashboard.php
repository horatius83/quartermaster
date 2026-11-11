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
    <p class="muted" style="margin:0;max-width:680px">Adds a system’s weapons, armor, ammunition, gear and tools, or its magic items, to its catalog, which every DM’s campaigns of that system share. Items the catalog already has are left as they are, so DMs’ own prices and descriptions are never overwritten. The 5e SRD gives most magic items no price, so they’re priced by rarity, from 100 gp for common to 200,000 gp for legendary; the potion of healing keeps the SRD’s 50 gp.</p>
    <div class="grid-cards">
      <?php foreach ($srd as $ruleset => $system):
        $label = $system['label']; ?>
        <article class="card card-stack" aria-labelledby="srd-<?= e($ruleset) ?>">
          <h3 class="card-title" id="srd-<?= e($ruleset) ?>"><?= e($label) ?> SRD</h3>
          <?php foreach ($system['sets'] as $set => $s):
            $missing = $s['total'] - $s['present'];
            // Every name is there, but some descriptions still have a sentence an earlier version of the data had.
            $tidy = $missing === 0 && $s['stale'] > 0;
            $loaded = $missing === 0 && !$tidy;
            $descriptions = $s['stale'] === 1 ? '1 description' : $s['stale'] . ' descriptions';
            $id = 'srd-' . $ruleset . '-' . $set;
            $names = SRD_SETS[$set];
            $noun = $names['noun'] . 's';
            $button = "Load {$label} SRD {$names['button']}"; ?>
            <section class="card-stack" aria-labelledby="<?= e($id) ?>">
              <div class="row-between" style="align-items:baseline">
                <h4 id="<?= e($id) ?>" style="margin:0"><?= e($names['title']) ?></h4>
                <span class="status <?= $loaded ? 'status-open' : 'status-hidden' ?>"><?= $loaded ? 'Loaded' : ($tidy ? 'Needs tidying' : ($s['present'] ? 'Partly loaded' : 'Not loaded')) ?></span>
              </div>
              <p class="muted small" style="margin:0">
                <?php if ($loaded): ?>
                  All <?= $s['total'] ?> <?= $noun ?> are already in the <?= e($label) ?> catalog. There’s nothing to add.
                <?php elseif ($tidy): ?>
                  All <?= $s['total'] ?> <?= $noun ?> are in the <?= e($label) ?> catalog, but <?= $descriptions ?> still <?= $s['stale'] === 1 ? 'mentions' : 'mention' ?> a table the catalog doesn’t have. Loading takes that sentence out and changes nothing else.
                <?php elseif ($s['present']): ?>
                  <?= $s['present'] ?> of <?= $s['total'] ?> <?= $noun ?> are in the <?= e($label) ?> catalog. Loading adds the other <?= $missing ?>, which were deleted, renamed or never loaded.
                <?php else: ?>
                  None of the <?= $s['total'] ?> <?= $noun ?> are in the <?= e($label) ?> catalog yet.
                <?php endif; ?>
                <?php if ($s['stale'] && !$tidy): ?>
                  It also takes a sentence about a table the catalog doesn’t have out of <?= $descriptions ?>.
                <?php endif; ?>
              </p>
              <form method="post" action="<?= e(url('/admin/srd')) ?>"<?php if ($tidy): ?> hx-confirm="Take the sentence about a missing table out of <?= $descriptions ?> in the catalog every DM’s <?= e($label) ?> campaigns share?"<?php elseif (!$loaded): ?> hx-confirm="Add <?= $missing ?> <?= e($label) ?> SRD <?= e($names['noun'] . ($missing === 1 ? '' : 's')) ?> to the catalog every DM’s <?= e($label) ?> campaigns share?"<?php endif; ?>>
                <?= csrf_field() ?>
                <input type="hidden" name="edition" value="<?= e($ruleset) ?>">
                <input type="hidden" name="set" value="<?= e($set) ?>">
                <button class="btn<?= $loaded ? '' : ' btn-primary' ?>" type="submit"<?= $loaded ? ' disabled' : '' ?>><?= e($button) ?></button>
              </form>
            </section>
          <?php endforeach; ?>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
</main>
