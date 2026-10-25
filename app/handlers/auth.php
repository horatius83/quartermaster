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
    page('auth/setup', [], 'Set up Quartermaster');
}

function setup_submit(): void
{
    if ((int)q_val('SELECT COUNT(*) FROM users') > 0) {
        redirect('/login');
    }
    $username = post_str('username', 50);
    $password = (string)($_POST['password'] ?? '');
    $campaign = post_str('campaign', 100);
    if ($username === '' || strlen($password) < 8 || $campaign === '') {
        back_with_error('/setup', 'Pick a username, a password of at least 8 characters and a campaign name.', ['username', 'campaign']);
    }
    [$id, $campaignId] = transaction(function () use ($username, $password, $campaign) {
        $id = insert(
            "INSERT INTO users (username, password_hash, role, created_at) VALUES (?, ?, 'dm', ?)",
            [$username, password_hash($password, PASSWORD_DEFAULT), now()]
        );
        // An upgraded database already has the campaign its data moved into: name that one.
        $campaignId = (int)q_val('SELECT id FROM campaigns ORDER BY id LIMIT 1');
        if ($campaignId) {
            q('UPDATE campaigns SET name = ? WHERE id = ?', [$campaign, $campaignId]);
        } else {
            $campaignId = create_campaign($campaign);
        }
        return [$id, $campaignId];
    });
    login_user(['id' => $id]);
    switch_campaign($campaignId);
    flash('Welcome, Dungeon Master. Add your players to get started.');
    redirect('/dm/party');
}
