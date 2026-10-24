<?php
declare(strict_types=1);

function start_session(): void
{
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    session_name('qm_session');
    session_set_cookie_params([
        'lifetime' => 60 * 60 * 24 * 30,
        'path'     => base_path() ?: '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $sent = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($sent) || !hash_equals(csrf_token(), $sent)) {
        abort(419, 'Your session expired. Reload the page and try again.');
    }
}

function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $id = $_SESSION['user_id'] ?? null;
        $user = $id ? q_one('SELECT id, username, role FROM users WHERE id = ?', [$id]) : null;
    }
    return $user;
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
}

function logout_user(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        redirect('/login');
    }
    return $user;
}

function require_dm(): array
{
    $user = require_login();
    if ($user['role'] !== 'dm') {
        abort(403, 'Only the DM can do that.');
    }
    return $user;
}

/** Returns the logged-in player's character. */
function require_player(): array
{
    $user = require_login();
    if ($user['role'] !== 'player') {
        redirect('/dm');
    }
    $char = q_one('SELECT * FROM characters WHERE user_id = ?', [$user['id']]);
    if (!$char) {
        abort(403, 'Your login is not linked to a character yet. Ask your DM.');
    }
    return $char;
}

function is_dm(): bool
{
    return (current_user()['role'] ?? '') === 'dm';
}
