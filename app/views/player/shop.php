<div class="player-main">
  <main class="main" style="flex:999 1 560px">
    <nav class="pills" aria-label="Shops in town">
      <?php foreach ($stores as $s): ?>
        <a class="pill" href="<?= e(url('/shop/' . $s['id'])) ?>"<?= (int)$s['id'] === (int)$store['id'] ? ' aria-current="page"' : '' ?>><?= e($s['name']) ?></a>
      <?php endforeach; ?>
    </nav>
    <div>
      <h1 class="page-title"><?= e($store['name']) ?></h1>
      <?php if ($store['blurb'] !== ''): ?><p class="muted" style="margin:4px 0 0">“<?= e($store['blurb']) ?>”</p><?php endif; ?>
    </div>
    <?= render('partials/shop_wares', ['store' => $store, 'wares' => $wares, 'catsHere' => $catsHere, 'cat' => $cat]) ?>
  </main>
  <?= render('partials/shop_cart', ['char' => $char, 'store' => $store, 'cart' => $cart]) ?>
</div>
