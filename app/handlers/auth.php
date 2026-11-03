<?php
declare(strict_types=1);

/**
 * Setup is open until someone can run the place: no admin and no DM yet. That's a fresh install, or an
 * upgraded one left with only player logins, whose campaigns then have no DM until setup makes one.
 */
function needs_setup(): bool
{
    return !q_val("SELECT 1 FROM users WHERE role IN ('admin','dm') LIMIT 1");
}

function home(): void
{
    if (needs_setup()) {
        redirect('/setup');
    }
    $user = require_login();
    redirect(match ($user['role']) { 'admin' => '/admin/users', 'dm' => '/dm', default => '/shop' });
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
    $result = check_password($username, $user, $password, 'at sign-in');
    if ($result !== 'ok') {
        $error = $result === 'blocked' ? wrong_password_pause_message() : 'That name and password don’t match our ledger.';
        page('auth/login', ['error' => $error, 'username' => $username], 'Sign in');
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
    if (!needs_setup()) {
        redirect('/login');
    }
    page('auth/setup', [], 'Set up Quartermaster');
}

function setup_submit(): void
{
    if (!needs_setup()) {
        redirect('/login');
    }
    $username = post_str('username', 50);
    $password = (string)($_POST['password'] ?? '');
    $campaign = post_str('campaign', 100);
    if ($username === '' || strlen($password) < STAFF_PASSWORD_MIN || $campaign === '') {
        back_with_error('/setup', 'Pick a username, a password of at least ' . STAFF_PASSWORD_MIN . ' characters and a campaign name.', ['username', 'campaign']);
    }
    if (username_taken($username)) {
        back_with_error('/setup', "The username “{$username}” is already taken.", ['campaign']);
    }
    [$id, $campaignId] = transaction(function () use ($username, $password, $campaign) {
        $id = create_login($username, $password, 'dm', 'First DM, at setup');
        // An upgraded database already has campaigns with no DM: the new DM runs them all, and names the first.
        $campaignId = (int)q_val('SELECT id FROM campaigns WHERE owner_user_id IS NULL ORDER BY id LIMIT 1');
        if ($campaignId) {
            q('UPDATE campaigns SET owner_user_id = ? WHERE owner_user_id IS NULL', [$id]);
            q('UPDATE campaigns SET name = ? WHERE id = ?', [$campaign, $campaignId]);
        } else {
            $campaignId = create_campaign($campaign, $id);
        }
        return [$id, $campaignId];
    });
    login_user(['id' => $id]);
    switch_campaign($campaignId);
    flash('Welcome, Dungeon Master. Add your players to get started.');
    redirect('/dm/party');
}
