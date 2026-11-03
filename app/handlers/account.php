<?php
declare(strict_types=1);

// Admins and DMs change their own password here. Players' passwords are set by their DM, and DMs' can
// also be reset by the admin (admin_user_password).

function account_password_form(): void
{
    require_staff();
    page('account/password', ['active' => 'password'], 'Change password');
}

function account_password_submit(): void
{
    $user = require_staff();
    $current = (string)($_POST['current_password'] ?? '');
    $new     = (string)($_POST['new_password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');

    // Guessing here counts against the same per-name limit as signing in (not the address-wide one: you're
    // already signed in). Once it's reached the session is signed out, so a stolen one can't be used to
    // find the password: signing back in needs the password itself.
    $row = q_one('SELECT id, username, role, password_hash FROM users WHERE id = ?', [$user['id']]);
    $result = check_password($user['username'], $row, $current, 'on the change-password form', addressLimit: false);
    if ($result === 'blocked') {
        logout_user();
        flash(wrong_password_pause_message() . ' You’ve been signed out.', 'error');
        redirect('/login');
    }
    // Nothing is kept on failure: every field here is a password.
    if ($result === 'wrong') {
        back_with_error('/account/password', 'Your current password isn’t right.', []);
    }
    if (strlen($new) < STAFF_PASSWORD_MIN) {
        back_with_error('/account/password', 'Your new password needs at least ' . STAFF_PASSWORD_MIN . ' characters.', []);
    }
    if ($new !== $confirm) {
        back_with_error('/account/password', 'The two new passwords don’t match.', []);
    }
    transaction(fn() => set_password($user, $new, 'Changed their own password'));
    // A fresh session id under the new session_version keeps you, and only you, signed in. Only the id:
    // $user still holds the old version.
    login_user(['id' => $user['id']]);
    flash('Password changed. You’re still signed in here; anywhere else will need the new one.');
    redirect('/');
}
