<?php
/** @var string $content @var string $title @var string $active */
$user = current_user();
$flashes = take_flashes();
$nav = function (string $key, string $href, string $label, string $extra = '') use ($active): string {
    $current = $key === $active ? ' aria-current="page"' : '';
    return '<a class="nav-link" href="' . e(url($href)) . '"' . $current . '>' . e($label) . $extra . '</a>';
};
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?> · Quartermaster</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Caveat:wght@500;700&family=Patrick+Hand&display=swap">
<link rel="stylesheet" href="<?= e(url('/assets/app.css')) ?>">
<script src="https://unpkg.com/htmx.org@2.0.4/dist/htmx.min.js"></script>
<script src="<?= e(url('/assets/app.js')) ?>" defer></script>
</head>
<body hx-boost="true" hx-headers='{"X-CSRF-Token": "<?= e(csrf_token()) ?>"}'>
<?php if ($user && $user['role'] === 'dm'): ?>
<div class="shell">
  <nav class="sidebar" aria-label="Main">
    <a class="brand" href="<?= e(url('/dm')) ?>">
      <?= render('partials/icon_pouch', []) ?>
      <span>Quartermaster</span>
    </a>
    <?= $nav('dashboard', '/dm', 'Dashboard') ?>
    <?= $nav('inventory', '/dm/inventory', 'Player inventories') ?>
    <?= $nav('stores', '/dm/stores', 'Stores') ?>
    <?= $nav('catalog', '/dm/catalog', 'Item catalog') ?>
    <?= $nav('carts', '/dm/carts', 'Cart review', render('partials/cart_badge_oob', ['inline' => true])) ?>
    <?= $nav('party', '/dm/party', 'Party & logins') ?>
    <div class="nav-rule"></div>
    <form method="post" action="<?= e(url('/logout')) ?>">
      <?= csrf_field() ?>
      <button class="nav-link nav-button" type="submit">Sign out (<?= e($user['username']) ?>)</button>
    </form>
  </nav>
  <?= $content ?>
</div>
<?php elseif ($user): ?>
<?php $me = q_one('SELECT * FROM characters WHERE user_id = ?', [$user['id']]); ?>
<div class="player-shell">
  <header class="topbar">
    <div class="topbar-left">
      <a class="brand" href="<?= e(url('/shop')) ?>"><?= render('partials/icon_pouch', []) ?><span>Quartermaster</span></a>
      <nav class="topnav" aria-label="Player">
        <?= $nav('shop', '/shop', 'Shops') ?>
        <?= $nav('pack', '/pack', 'My pack') ?>
      </nav>
    </div>
    <div class="topbar-right">
      <?php if ($me): ?>
        <span class="muted-light"><?= e($me['name']) ?></span>
        <?= render('partials/purse_pill', ['coin' => (int)$me['coin_cp']]) ?>
      <?php endif; ?>
      <form method="post" action="<?= e(url('/logout')) ?>">
        <?= csrf_field() ?>
        <button class="link-light" type="submit">Sign out</button>
      </form>
    </div>
  </header>
  <?= $content ?>
</div>
<?php else: ?>
<div class="auth-shell">
  <?= $content ?>
</div>
<?php endif; ?>
<div id="toasts" class="toasts" aria-live="polite"><?php foreach ($flashes as $f): ?><p class="toast toast-<?= e($f['kind']) ?>"><?= e($f['message']) ?></p><?php endforeach; ?></div>
</body>
</html>
