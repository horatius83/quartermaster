<main class="main">
  <header>
    <h1 class="page-title">Change password</h1>
    <p class="muted" style="margin:6px 0 0;max-width:640px">You stay signed in here. Anywhere else you’re signed in will need the new password.</p>
  </header>

  <form class="card card-stack" method="post" action="<?= e(url('/account/password')) ?>" style="max-width:520px">
    <?= csrf_field() ?>
    <label class="field">Current password
      <input name="current_password" type="password" required autocomplete="current-password">
    </label>
    <label class="field">New password (<?= STAFF_PASSWORD_MIN ?>+ characters)
      <input name="new_password" type="password" required minlength="<?= STAFF_PASSWORD_MIN ?>" autocomplete="new-password">
    </label>
    <label class="field">New password again
      <input name="confirm_password" type="password" required minlength="<?= STAFF_PASSWORD_MIN ?>" autocomplete="new-password">
    </label>
    <button class="btn btn-primary" type="submit">Change password</button>
  </form>
</main>
