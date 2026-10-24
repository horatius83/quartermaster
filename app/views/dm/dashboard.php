<main class="main">
  <header class="page-head">
    <div>
      <p class="muted" style="margin:0"><?= e(config('campaign_name', '')) ?></p>
      <h1 class="page-title">The Party Ledger</h1>
    </div>
    <div class="actions">
      <a class="btn" href="<?= e(url('/dm/stores')) ?>">Stores</a>
      <a class="btn btn-primary" href="<?= e(url('/dm/inventory')) ?>">Give item to player</a>
    </div>
  </header>

  <section class="grid-stats" aria-label="At a glance">
    <div class="card stat"><div class="stat-value"><?= count($characters) ?></div><div class="muted">Adventurers</div></div>
    <div class="card stat"><div class="stat-value"><?= $openStores ?></div><div class="muted">Stores open</div></div>
    <div class="card stat"><div class="stat-value"><?= count($pending) ?></div><div class="muted">Carts to review</div></div>
    <div class="card stat"><div class="stat-value"><?= number_format(intdiv($partyCoin, CP_PER_GP)) ?></div><div class="muted">Party gold (gp)</div></div>
  </section>

  <div class="split">
    <section class="wide" aria-labelledby="party-h">
      <h2 id="party-h" class="section-title">The Party</h2>
      <?php if (!$characters): ?>
        <div class="card empty">No adventurers yet. <a href="<?= e(url('/dm/party')) ?>">Add your players</a>.</div>
      <?php endif; ?>
      <div class="grid-cards">
        <?php foreach ($characters as $c):
          $cap = max(1.0, (float)$c['carry_capacity']);
          $pct = min(100, round((float)$c['load_weight'] / $cap * 100));
          $p = coin_parts((int)$c['coin_cp']); ?>
          <article class="card card-stack">
            <div class="row-between" style="align-items:baseline">
              <h3 class="card-title"><?= e($c['name']) ?></h3>
              <span class="muted small"><?= e($c['class_label']) ?></span>
            </div>
            <div class="muted small"><?= $c['username'] ? 'Login: ' . e($c['username']) : 'No login yet' ?></div>
            <div class="coins">
              <span><strong class="gp"><?= number_format($p['gp']) ?></strong> gp</span>
              <span><strong class="sp"><?= $p['sp'] ?></strong> sp</span>
              <span><strong class="cp"><?= $p['cp'] ?></strong> cp</span>
            </div>
            <div>
              <div class="row-between small muted"><span>Carrying</span><span><?= e(fmt_weight($c['load_weight'])) ?> / <?= e(fmt_weight($c['carry_capacity'])) ?></span></div>
              <div class="meter<?= (float)$c['load_weight'] > $cap ? ' over' : '' ?>" role="img" aria-label="<?= $pct ?>% of carrying capacity"><span style="width: <?= $pct ?>%"></span></div>
            </div>
            <div class="row-between ledger-rule">
              <span class="muted small"><?= (int)$c['item_count'] ?> items</span>
              <a href="<?= e(url('/dm/inventory/' . $c['id'])) ?>">Open pack →</a>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </section>

    <aside class="narrow">
      <section class="card-stack" aria-labelledby="carts-h">
        <h2 id="carts-h" class="section-title">Carts awaiting you</h2>
        <?php if (!$pending): ?><p class="muted" style="margin:0">Nothing to review. The shops are quiet.</p><?php endif; ?>
        <?php foreach ($pending as $cart): ?>
          <a class="card card-dashed row-between" style="text-decoration:none;color:inherit" href="<?= e(url('/dm/carts')) ?>">
            <span>
              <strong style="font-size:20px"><?= e($cart['character_name']) ?></strong><br>
              <span class="muted small"><?= e($cart['store_name']) ?> · <?= (int)$cart['unit_count'] ?> items · <?= e(time_ago($cart['submitted_at'])) ?></span>
            </span>
            <span class="price"><?= e(coins((int)$cart['total_cp'])) ?></span>
          </a>
        <?php endforeach; ?>
      </section>

      <section class="card-stack" aria-labelledby="stores-h">
        <h2 id="stores-h" class="section-title">Stores</h2>
        <?php if (!$stores): ?><p class="muted" style="margin:0">No stores yet. <a href="<?= e(url('/dm/stores')) ?>">Open one</a>.</p><?php else: ?>
        <ul class="list-plain card" style="padding:0 18px">
          <?php foreach ($stores as $s): ?>
            <li class="row-between" style="padding:12px 0">
              <span>
                <a href="<?= e(url('/dm/stores/' . $s['id'])) ?>" style="font-size:20px;color:var(--ink);text-decoration:none"><?= e($s['name']) ?></a><br>
                <span class="muted small"><?= (int)$s['stock_count'] ?> items for sale</span>
              </span>
              <span class="status <?= $s['is_open'] ? 'status-open' : 'status-hidden' ?>"><?= $s['is_open'] ? 'Open' : 'Hidden' ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
        <?php endif; ?>
      </section>
    </aside>
  </div>
</main>
