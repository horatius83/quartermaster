<?php /** @var array $old */ ?>
<main class="main">
  <header>
    <h1 class="page-title">Create the admin account</h1>
    <p class="muted" style="margin:6px 0 0;max-width:640px">The admin is a separate login that adds and deletes DMs and other logins, and keeps a log of every change. It doesn’t run campaigns, so you keep this DM login for your games. Once the admin exists, this page closes.</p>
  </header>

  <form class="card card-stack" method="post" action="<?= e(url('/dm/admin-account')) ?>" style="max-width:520px">
    <?= csrf_field() ?>
    <label class="field">Admin username
      <input name="username" required maxlength="50" autocomplete="off" value="<?= e($old['username'] ?? '') ?>">
    </label>
    <label class="field">Admin password (8+ characters)
      <input name="password" type="password" required minlength="8" autocomplete="new-password">
    </label>
    <button class="btn btn-primary" type="submit">Create admin account</button>
  </form>
</main>
