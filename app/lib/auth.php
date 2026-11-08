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

/** Minimum password length for admins and DMs (players' is set by their DM's form). */
const STAFF_PASSWORD_MIN = 8;

/**
 * Options for password_hash() and password_needs_rehash(): PHP's default cost unless the config sets
 * 'password_cost'. Only the end-to-end tests set it, low, since every test seeds logins and signs in.
 */
function password_options(): array
{
    $cost = config('password_cost');
    return is_int($cost) ? ['cost' => $cost] : [];
}

function hash_password(string $password): string
{
    return password_hash($password, PASSWORD_DEFAULT, password_options());
}

/**
 * A session remembers the login's session_version from when it signed in. Bumping the version
 * (set_password does) ends every session that has the old one. A session with no version at all
 * signed in before versions existed, and is signed out too, so it can't outlive a reset.
 */
function current_user(): ?array
{
    static $user = false;
    if ($user === false) {
        $id = $_SESSION['user_id'] ?? null;
        $user = $id ? q_one('SELECT id, username, role, session_version FROM users WHERE id = ?', [$id]) : null;
        if ($id && (!$user || (int)$user['session_version'] !== ($_SESSION['session_version'] ?? null))) {
            // The login was deleted, or signed out by a new password. Drop everything it left, such as
            // kept form input, so the next login here can't see it.
            $user = null;
            $_SESSION = [];
        }
    }
    return $user;
}

/**
 * Sign in under a fresh session id, recording the login's current session_version. Also used after you
 * change your own password, so a copy of the old cookie is signed out along with every other session.
 * $user needs its id; pass its session_version too when you have it, to save a query.
 */
function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['session_version'] = (int)($user['session_version'] ?? q_val('SELECT session_version FROM users WHERE id = ?', [$user['id']]));
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

/**
 * A DM's pages show their current campaign, so a DM with none yet is sent to Campaigns to start one and pick its
 * system. The pages that work without one pass $needsCampaign false.
 */
function require_dm(bool $needsCampaign = true): array
{
    $user = require_login();
    if ($user['role'] !== 'dm') {
        abort(403, 'Only the DM can do that.');
    }
    if ($needsCampaign && !current_campaign()) {
        flash('Start a campaign first: give it a name and pick the system it plays.');
        redirect('/dm/campaigns');
    }
    return $user;
}

/** The admin manages logins only; it runs no campaigns. */
function require_admin(): array
{
    $user = require_login();
    if ($user['role'] !== 'admin') {
        abort(403, 'Only the admin can do that.');
    }
    return $user;
}

// Wrong passwords, at sign-in or on the change-password form, slow guessing down. A name can be tried
// SIGN_IN_TRIES_PER_NAME times from one address, and an address can make SIGN_IN_TRIES_PER_ADDRESS wrong
// tries in all, per SIGN_IN_WINDOW. Counting a name per address means a stranger can't lock a DM out of
// signing in from home just by guessing their name somewhere else.
const SIGN_IN_WINDOW = 15 * 60;
const SIGN_IN_TRIES_PER_NAME = 5;
const SIGN_IN_TRIES_PER_ADDRESS = 20;

/**
 * The visitor's address. Behind a proxy or CDN, REMOTE_ADDR is the proxy's, shared by every visitor,
 * so config.php's client_ip_header names the header the proxy puts the real address in (for
 * Cloudflare, 'HTTP_CF_CONNECTING_IP'). Set it only behind such a proxy: anyone can send that header
 * straight to a site without one. For a list (X-Forwarded-For), the last entry is the one the proxy added.
 */
function client_address(): string
{
    $header = config('client_ip_header');
    if (is_string($header) && $header !== '' && isset($_SERVER[$header])) {
        $parts = explode(',', (string)$_SERVER[$header]);
        $ip = trim(end($parts));
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return $ip;
        }
    }
    return substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
}

function wrong_password_pause_message(): string
{
    return 'Too many wrong passwords. Wait ' . intdiv(SIGN_IN_WINDOW, 60) . ' minutes and try again.';
}

/**
 * Check $password for the login $user (the row with password_hash, or null when no login has the name
 * $username), counting wrong ones against the limits. 'blocked' means this address has to wait; it's
 * checked first, so more tries tell a guesser nothing. $addressLimit false applies only the per-name
 * limit, for someone already signed in: other people's mistakes at a shared address shouldn't pause them.
 * $where says where the password was typed, for the account log.
 * @return 'ok'|'wrong'|'blocked'
 */
function check_password(string $username, ?array $user, string $password, string $where, bool $addressLimit = true): string
{
    $address = client_address();
    $tries = q_one(
        'SELECT COUNT(*) AS address_tries, COALESCE(SUM(CASE WHEN username = ? THEN 1 ELSE 0 END), 0) AS name_tries
           FROM login_attempts WHERE ip = ? AND created_at > ?',
        [$username, $address, gmdate('Y-m-d H:i:s', time() - SIGN_IN_WINDOW)]
    );
    $nameTries = (int)$tries['name_tries'];
    $addressTries = (int)$tries['address_tries'];
    $blocked = fn(int $name, int $all) => $name >= SIGN_IN_TRIES_PER_NAME || ($addressLimit && $all >= SIGN_IN_TRIES_PER_ADDRESS);
    if ($blocked($nameTries, $addressTries)) {
        return 'blocked';
    }

    if ($user && password_verify($password, $user['password_hash'])) {
        q('DELETE FROM login_attempts WHERE username = ? AND ip = ?', [$username, $address]);
        return 'ok';
    }
    if (!$user) {
        // Spend as long as checking a real login's password does, so how fast the answer comes back doesn't
        // tell a guesser which names exist. Hashing at the configured cost takes as long as verifying a hash
        // made at it (which every login's is after its next sign-in).
        hash_password($password);
    }

    q('DELETE FROM login_attempts WHERE created_at < ?', [gmdate('Y-m-d H:i:s', time() - SIGN_IN_WINDOW)]);
    q('INSERT INTO login_attempts (username, ip, created_at) VALUES (?, ?, ?)', [$username, $address, now()]);
    // Each pause goes in the account log once, as it starts: a run of guesses is often the sign of a
    // stolen session or a leaked name, and guesses spread over many names show up as the address's pause.
    $from = 'from ' . ($address ?: 'an unknown address') . '; paused for ' . intdiv(SIGN_IN_WINDOW, 60) . ' minutes';
    if ($nameTries + 1 === SIGN_IN_TRIES_PER_NAME && $user) {
        log_user('lockout', (int)$user['id'], $user['username'], $user['role'], "Too many wrong passwords {$where} {$from}");
    }
    if ($addressTries + 1 === SIGN_IN_TRIES_PER_ADDRESS) {
        // Only names that are real logins are written down. Anything else may be a password typed into the
        // username box by mistake, so it's only counted.
        $logins = array_column(q_all(
            'SELECT DISTINCT u.username FROM login_attempts a JOIN users u ON u.username = a.username WHERE a.ip = ? ORDER BY u.username',
            [$address]
        ), 'username');
        $unknown = (int)q_val('SELECT COUNT(DISTINCT username) FROM login_attempts WHERE ip = ?', [$address]) - count($logins);
        $for = $logins ? 'for: ' . implode(', ', $logins) : 'for no real login';
        if ($unknown > 0) {
            $for .= ($logins ? ', and ' : ', but ') . $unknown . ($unknown === 1 ? ' name with no login' : ' names with no login');
        }
        log_user('lockout', null, '', '', SIGN_IN_TRIES_PER_ADDRESS . " wrong passwords {$from}, {$for}");
    }
    return $blocked($nameTries + 1, $addressTries + 1) ? 'blocked' : 'wrong';
}

/** Admins and DMs: the logins that set their own passwords. */
function require_staff(): array
{
    $user = require_login();
    if (!in_array($user['role'], ['admin', 'dm'], true)) {
        abort(403, 'Ask your DM to change your password.');
    }
    return $user;
}

/** Returns the logged-in player's character. */
function require_player(): array
{
    $user = require_login();
    if ($user['role'] !== 'player') {
        redirect('/');
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
