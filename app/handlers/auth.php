<?php
declare(strict_types=1);

function home(): void
{
    if ((int)q_val('SELECT COUNT(*) FROM users') === 0) {
        redirect('/setup');
    }
    $user = require_login();
    redirect($user['role'] === 'dm' ? '/dm' : '/shop');
}

function login_form(): void
{
    if (current_user()) {
        redirect('/');
    }
    page('auth/login', ['error' => null, 'username' => ''], 'Sign in');
}

function login_submit(): void
{
    $username = post_str('username', 50);
    $password = (string)($_POST['password'] ?? '');
    $user = q_one('SELECT * FROM users WHERE username = ?', [$username]);

    if (!$user || !password_verify($password, $user['password_hash'])) {
        page('auth/login', ['error' => 'That name and password don’t match our ledger.', 'username' => $username], 'Sign in');
    }
    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $user['id']]);
    }
    login_user($user);
    redirect('/');
}

function logout_submit(): void
{
    logout_user();
    redirect('/login');
}

function setup_form(): void
{
    if ((int)q_val('SELECT COUNT(*) FROM users') > 0) {
        redirect('/login');
    }
    page('auth/setup', ['error' => null], 'Set up Quartermaster');
}

function setup_submit(): void
{
    if ((int)q_val('SELECT COUNT(*) FROM users') > 0) {
        redirect('/login');
    }
    $username = post_str('username', 50);
    $password = (string)($_POST['password'] ?? '');
    if ($username === '' || strlen($password) < 8) {
        page('auth/setup', ['error' => 'Pick a username and a password of at least 8 characters.'], 'Set up Quartermaster');
    }
    $id = insert(
        "INSERT INTO users (username, password_hash, role, created_at) VALUES (?, ?, 'dm', ?)",
        [$username, password_hash($password, PASSWORD_DEFAULT), now()]
    );
    login_user(['id' => $id]);
    flash('Welcome, Dungeon Master. Add your players to get started.');
    redirect('/dm/party');
}
