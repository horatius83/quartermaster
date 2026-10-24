<main class="auth-card">
  <div class="brand"><?= render('partials/icon_pouch', []) ?><span>Quartermaster</span></div>
  <form class="card card-stack" method="post" action="<?= e(url('/login')) ?>" hx-boost="false">
    <?= csrf_field() ?>
    <h1 class="section-title">Sign in</h1>
    <?php if ($error): ?><p class="notice notice-err" role="alert"><?= e($error) ?></p><?php endif; ?>
    <label class="field">Username
      <input name="username" value="<?= e($username) ?>" autocomplete="username" required autofocus>
    </label>
    <label class="field">Password
      <input type="password" name="password" autocomplete="current-password" required>
    </label>
    <button class="btn btn-primary btn-big" type="submit">Open the ledger</button>
    <p class="muted small" style="margin:0">Players: your DM sets up your login.</p>
  </form>
</main>
