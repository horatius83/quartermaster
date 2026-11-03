<?php
declare(strict_types=1);

// The admin login manages every login: it adds DMs (and other admins), deletes logins, hands campaigns
// between DMs, and reads the account log. It runs no campaigns. Players are still added by their DM.

const ADMIN_LOG_PAGE_SIZE = 50;

function admin_users(): void
{
    $me = require_admin();
    $users = q_all(
        "SELECT u.id, u.username, u.role, u.created_at,
                ch.name AS character_name, cp.name AS campaign_name, owner.username AS dm_name
           FROM users u
           LEFT JOIN characters ch ON ch.user_id = u.id
           LEFT JOIN campaigns cp ON cp.id = ch.campaign_id
           LEFT JOIN users owner ON owner.id = cp.owner_user_id
          ORDER BY CASE u.role WHEN 'admin' THEN 0 WHEN 'dm' THEN 1 ELSE 2 END, u.username"
    );
    $campaigns = q_all(
        'SELECT c.id, c.name, c.owner_user_id,
                (SELECT COUNT(*) FROM characters ch WHERE ch.campaign_id = c.id) AS character_count
           FROM campaigns c ORDER BY c.name, c.id'
    );
    $campaignsByOwner = [];
    foreach ($campaigns as $c) {
        $campaignsByOwner[(int)$c['owner_user_id']][] = $c['name'];
    }
    page('admin/users', [
        'active'           => 'users',
        'users'            => $users,
        'campaigns'        => $campaigns,
        'campaignsByOwner' => $campaignsByOwner,
        'dms'              => array_values(array_filter($users, fn($u) => $u['role'] === 'dm')),
        'me'               => $me,
    ], 'Users');
}

function admin_user_create(): void
{
    require_admin();
    $username = post_str('username', 50);
    $password = (string)($_POST['password'] ?? '');
    $role     = (string)($_POST['role'] ?? '');

    // Never the password; a taken username is left out where it's the problem.
    $keep = ['username', 'role'];
    $taken = fn() => back_with_error('/admin/users', "The username “{$username}” is already taken.", ['role']);

    if (!in_array($role, ['dm', 'admin'], true)) {
        back_with_error('/admin/users', 'Choose whether the new login is a DM or an admin.', $keep);
    }
    if ($username === '' || strlen($password) < 8) {
        back_with_error('/admin/users', 'Pick a username and a password of at least 8 characters.', $keep);
    }
    if (username_taken($username)) {
        $taken();
    }

    try {
        transaction(fn() => create_login($username, $password, $role));
    } catch (PDOException $e) {
        // Someone claimed the name between the check above and the insert.
        if (!username_taken($username)) {
            throw $e;
        }
        $taken();
    }

    flash($role === 'dm' ? "{$username} can now sign in and start a campaign." : "{$username} is now an admin.");
    redirect('/admin/users');
}

/**
 * Delete a login. A player's character stays in its campaign without a login. A DM goes only when their
 * campaigns are empty (no characters or stores), and the empty campaigns go with them, so deleting a DM
 * can't wipe out a party; hand a campaign to another DM first to keep it. The checks run with the DM's
 * campaigns locked, so nothing can be added meanwhile. The last admin always stays.
 */
function admin_user_delete(int $id): void
{
    $me = require_admin();
    if ($id === (int)$me['id']) {
        flash('You can’t delete the login you’re signed in with.', 'error');
        redirect('/admin/users');
    }
    try {
        // Two admins deleting each other at once would each see the other's row still there; the lock
        // makes the second wait, and then see it's the last admin.
        $user = with_lock('admins', fn() => transaction(function () use ($id) {
            $user = q_one('SELECT id, username, role FROM users WHERE id = ?' . for_update(), [$id]);
            if (!$user) {
                throw new DomainException('That login is already gone.');
            }
            $detail = '';
            if ($user['role'] === 'admin') {
                if ((int)q_val("SELECT COUNT(*) FROM users WHERE role = 'admin'") <= 1) {
                    throw new DomainException("{$user['username']} is the last admin, so it stays.");
                }
            } elseif ($user['role'] === 'dm') {
                $campaigns = q_all('SELECT id, name FROM campaigns WHERE owner_user_id = ?' . for_update(), [$id]);
                foreach ($campaigns as $c) {
                    if (q_val('SELECT 1 FROM characters WHERE campaign_id = ? UNION ALL SELECT 1 FROM stores WHERE campaign_id = ? LIMIT 1', [$c['id'], $c['id']])) {
                        throw new DomainException("{$user['username']} still runs {$c['name']}. Hand it to another DM, or they need to remove its characters and stores first.");
                    }
                }
                q('DELETE FROM campaigns WHERE owner_user_id = ?', [$id]);
                if ($campaigns) {
                    $detail = 'Empty campaigns deleted too: ' . implode(', ', array_column($campaigns, 'name'));
                }
            } else {
                $char = q_one('SELECT ch.name, cp.name AS campaign_name FROM characters ch JOIN campaigns cp ON cp.id = ch.campaign_id WHERE ch.user_id = ?', [$id]);
                if ($char) {
                    $detail = "{$char['name']} stays in {$char['campaign_name']} without a login";
                }
            }
            delete_login($user, $detail);
            return $user;
        }));
    } catch (DomainException $e) {
        flash($e->getMessage(), 'error');
        redirect('/admin/users');
    }
    flash("Deleted {$user['username']}.");
    redirect('/admin/users');
}

/**
 * Hand a campaign, with its party, stores and history, to another DM. Fixes up an upgraded install, where
 * every campaign went to the oldest DM, and lets a DM be deleted without losing their party.
 */
function admin_campaign_owner(int $campaignId): void
{
    require_admin();
    $ownerId = post_int('owner_user_id');
    try {
        $moved = transaction(function () use ($campaignId, $ownerId) {
            $campaign = q_one(
                'SELECT c.id, c.name, c.owner_user_id, u.username AS old_owner
                   FROM campaigns c LEFT JOIN users u ON u.id = c.owner_user_id
                  WHERE c.id = ?' . for_update(),
                [$campaignId]
            ) ?? throw new DomainException('That campaign is gone.');
            $dm = q_one("SELECT id, username FROM users WHERE id = ? AND role = 'dm'" . for_update(), [$ownerId])
                ?? throw new DomainException('Choose a DM to hand the campaign to.');
            if ((int)$campaign['owner_user_id'] === (int)$dm['id']) {
                throw new DomainException("{$dm['username']} already runs {$campaign['name']}.");
            }
            q('UPDATE campaigns SET owner_user_id = ? WHERE id = ?', [$dm['id'], $campaignId]);
            log_user('move', (int)$dm['id'], $dm['username'], 'dm',
                "{$campaign['name']}, from " . ($campaign['old_owner'] !== null ? "DM {$campaign['old_owner']}" : 'no DM'));
            return ['campaign' => $campaign['name'], 'dm' => $dm['username']];
        });
    } catch (DomainException $e) {
        flash($e->getMessage(), 'error');
        redirect('/admin/users');
    }
    flash("{$moved['dm']} now runs {$moved['campaign']}.");
    redirect('/admin/users');
}

/** The account log, newest first, one page at a time; "Show older" asks for just the next rows. */
function admin_log(): void
{
    require_admin();
    $sql = 'SELECT * FROM user_log';
    $params = [];
    $beforeId = max(0, (int)($_GET['before'] ?? 0));
    if ($beforeId > 0) {
        $sql .= ' WHERE id < ?';
        $params[] = $beforeId;
    }
    $rows = q_all($sql . ' ORDER BY id DESC LIMIT ' . (ADMIN_LOG_PAGE_SIZE + 1), $params);
    $rowData = ['rows' => array_slice($rows, 0, ADMIN_LOG_PAGE_SIZE), 'hasMore' => count($rows) > ADMIN_LOG_PAGE_SIZE];
    if (htmx_target() === 'log-more') {
        fragment('admin/log_rows', $rowData);
    }
    page('admin/log', $rowData + ['active' => 'log'], 'Account log');
}

/**
 * Installs from before the admin login, and fresh ones (whose setup makes the first DM), have no admin.
 * Until there is one, a DM can make it here. After that this page is closed.
 */
function admin_account_form(): void
{
    require_dm();
    if (admin_exists()) {
        redirect('/dm');
    }
    page('admin/create_first', ['active' => 'admin-account'], 'Create the admin account');
}

function admin_account_submit(): void
{
    $dm = require_dm();
    $username = post_str('username', 50);
    $password = (string)($_POST['password'] ?? '');
    if ($username === '' || strlen($password) < 8) {
        back_with_error('/dm/admin-account', 'Pick a username and a password of at least 8 characters.', ['username']);
    }
    if (username_taken($username)) {
        back_with_error('/dm/admin-account', "The username “{$username}” is already taken.", []);
    }
    try {
        // The lock makes a second submit wait for the first, and then find the admin it made.
        with_lock('admins', fn() => transaction(function () use ($username, $password, $dm) {
            if (q_val("SELECT 1 FROM users WHERE role = 'admin' LIMIT 1")) {
                throw new DomainException('There is already an admin account.');
            }
            create_login($username, $password, 'admin', "First admin, made by DM {$dm['username']}");
        }));
    } catch (DomainException $e) {
        flash($e->getMessage(), 'error');
        redirect('/dm');
    } catch (PDOException $e) {
        if (!username_taken($username)) {
            throw $e;
        }
        back_with_error('/dm/admin-account', "The username “{$username}” is already taken.", []);
    }
    flash("Admin account {$username} created. Sign out and sign in as {$username} to manage logins.");
    redirect('/dm');
}
