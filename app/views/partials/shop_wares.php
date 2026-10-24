<?php
$catsHere ??= null;
$base = url('/shop/' . $store['id']);
?>
<div id="wares" class="card-stack" style="gap:16px">
  <?php if ($catsHere !== null && count($catsHere) > 1): ?>
  <nav class="chips" aria-label="Filter by category">
    <?php foreach (array_merge(['' => 'All'], $catsHere) as $slug => $name):
      $href = $slug === '' ? $base : $base . '?cat=' . urlencode((string)$slug); ?>
      <a class="chip" href="<?= e($href) ?>" hx-get="<?= e($href) ?>" hx-target="#wares" hx-swap="outerHTML" hx-push-url="true"<?= $cat === (string)$slug ? ' aria-current="true"' : '' ?>><?= e($name) ?></a>
    <?php endforeach; ?>
  </nav>
  <?php endif; ?>
  <div class="grid-cards" style="grid-template-columns:repeat(auto-fill,minmax(220px,1fr))">
    <?php foreach ($wares as $w): $soldOut = $w['quantity'] !== null && (int)$w['quantity'] <= 0; ?>
      <article class="card ware">
        <?= category_tag($w['category'], $w['category_slug']) ?>
        <h2 class="card-title"><?= e($w['name']) ?></h2>
        <div class="row-between muted small">
          <span><?= e(fmt_weight($w['weight'])) ?></span>
          <span><?= $w['quantity'] === null ? 'Plenty' : ($soldOut ? 'Sold out' : (int)$w['quantity'] . ' left') ?></span>
        </div>
        <div class="ware-foot">
          <span class="price"><?= e(coins((int)$w['price_cp'])) ?></span>
          <button class="btn btn-dark" type="button"<?= $soldOut ? ' disabled' : '' ?>
            hx-post="<?= e(url('/shop/' . $store['id'] . '/add')) ?>" hx-vals='{"stock_id": <?= (int)$w['id'] ?>}'
            hx-target="#cart" hx-swap="outerHTML" aria-label="Add <?= e($w['name']) ?> to cart">Add to cart</button>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
  <?php if (!$wares): ?><div class="card empty">The shelves are bare right now.</div><?php endif; ?>
</div>
