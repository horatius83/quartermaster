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
<link rel="stylesheet" href="<?= e(url('/assets/app.css')) ?>">
<script src="<?= e(url('/assets/htmx-2.0.4.min.js')) ?>"></script>
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
    <?php $allCampaigns = campaign_names(); $here = current_campaign(); ?>
    <?php if (count($allCampaigns) > 1): ?>
      <form class="campaign-switch" hx-post="<?= e(url('/dm/campaigns/switch')) ?>" hx-trigger="change">
        <label class="visually-hidden" for="campaign-switch">Campaign</label>
        <select id="campaign-switch" name="campaign_id">
          <?php foreach ($allCampaigns as $c): ?>
            <option value="<?= (int)$c['id'] ?>"<?= (int)$c['id'] === (int)$here['id'] ? ' selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
    <?php else: ?>
      <p class="campaign-name"><?= e($here['name'] ?? '') ?></p>
    <?php endif; ?>
    <?= $nav('dashboard', '/dm', 'Dashboard') ?>
    <?= $nav('inventory', '/dm/inventory', 'Player inventories') ?>
    <?= $nav('history', '/dm/history', 'Pack history') ?>
    <?= $nav('stores', '/dm/stores', 'Stores') ?>
    <?= $nav('catalog', '/dm/catalog', 'Item catalog') ?>
    <?= $nav('carts', '/dm/carts', 'Cart review', render('partials/cart_badge_oob', ['inline' => true])) ?>
    <?= $nav('party', '/dm/party', 'Party & logins') ?>
    <div class="nav-rule"></div>
    <?= $nav('campaigns', '/dm/campaigns', 'Campaigns') ?>
    <?= $nav('password', '/account/password', 'Change password') ?>
    <a class="nav-link" href="<?= e(url('/assets/srd-notices.txt')) ?>" hx-boost="false">SRD licences</a>
    <?php if (!admin_exists()): ?><?= $nav('admin-account', '/dm/admin-account', 'Create the admin account') ?><?php endif; ?>
    <form method="post" action="<?= e(url('/logout')) ?>">
      <?= csrf_field() ?>
      <button class="nav-link nav-button" type="submit">Sign out (<?= e($user['username']) ?>)</button>
    </form>
  </nav>
  <?= $content ?>
</div>
<?php elseif ($user && $user['role'] === 'admin'): ?>
<div class="shell">
  <nav class="sidebar" aria-label="Main">
    <a class="brand" href="<?= e(url('/admin')) ?>">
      <?= render('partials/icon_pouch', []) ?>
      <span>Quartermaster</span>
    </a>
    <p class="campaign-name">Admin</p>
    <?= $nav('dashboard', '/admin', 'Dashboard') ?>
    <?= $nav('users', '/admin/users', 'Users') ?>
    <?= $nav('log', '/admin/log', 'Account log') ?>
    <div class="nav-rule"></div>
    <?= $nav('password', '/account/password', 'Change password') ?>
    <a class="nav-link" href="<?= e(url('/assets/srd-notices.txt')) ?>" hx-boost="false">SRD licences</a>
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
        <?= $nav('history', '/history', 'History') ?>
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
  <footer class="muted small" style="padding:16px;text-align:center"><a href="<?= e(url('/assets/srd-notices.txt')) ?>" hx-boost="false">SRD licences</a></footer>
</div>
<?php else: ?>
<div class="auth-shell">
  <?= $content ?>
</div>
<?php endif; ?>
<div id="toasts" class="toasts" aria-live="polite"><?php foreach ($flashes as $f): ?><p class="toast toast-<?= e($f['kind']) ?>"><?= e($f['message']) ?></p><?php endforeach; ?></div>
</body>
</html>
