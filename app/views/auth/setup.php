<main class="auth-card">
  <div class="brand"><?= render('partials/icon_pouch', []) ?><span>Quartermaster</span></div>
  <form class="card card-stack" method="post" action="<?= e(url('/setup')) ?>" hx-boost="false">
    <?= csrf_field() ?>
    <h1 class="section-title">First, the Dungeon Master</h1>
    <p class="muted" style="margin:0">Create the DM account and name your first campaign. You’ll add players and their logins next.</p>
    <?php // Errors stay on the card rather than fading as toasts, so they can't be missed. ?>
    <?php foreach (take_flashes('error') as $f): ?><p class="notice notice-err" role="alert"><?= e($f['message']) ?></p><?php endforeach; ?>
    <label class="field">DM username
      <input name="username" autocomplete="username" required autofocus maxlength="50" value="<?= e($old['username'] ?? '') ?>">
    </label>
    <label class="field">Password (8+ characters)
      <input type="password" name="password" autocomplete="new-password" required minlength="8">
    </label>
    <label class="field">Campaign name
      <input name="campaign" required maxlength="100" placeholder="e.g. Curse of the Sunken Crown" value="<?= e($old['campaign'] ?? '') ?>">
    </label>
    <button class="btn btn-primary btn-big" type="submit">Create DM account</button>
  </form>
</main>
