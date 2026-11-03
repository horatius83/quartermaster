<?php
declare(strict_types=1);

function dm_dashboard(): void
{
    require_dm();
    $characters = characters_with_load();
    $pending = q_all(
        "SELECT c.id, ch.name AS character_name, s.name AS store_name, c.submitted_at,
                COALESCE(SUM(ci.quantity * ci.unit_price_cp), 0) AS total_cp,
                COALESCE(SUM(ci.quantity), 0) AS unit_count
           FROM carts c
           JOIN characters ch ON ch.id = c.character_id
           JOIN stores s ON s.id = c.store_id
           LEFT JOIN cart_items ci ON ci.cart_id = c.id
          WHERE c.status = 'submitted' AND ch.campaign_id = ?
          GROUP BY c.id, ch.name, s.name, c.submitted_at
          ORDER BY c.submitted_at",
        [campaign_id()]
    );
    $stores = stores_summary();
    $partyCoin = array_sum(array_map(fn($c) => (int)$c['coin_cp'], $characters));

    page('dm/dashboard', [
        'active'     => 'dashboard',
        'characters' => $characters,
        'pending'    => $pending,
        'stores'     => $stores,
        'openStores' => count(array_filter($stores, fn($s) => (int)$s['is_open'] === 1)),
        'partyCoin'  => $partyCoin,
        'campaign'   => current_campaign(),
    ], 'Dashboard');
}

function dm_party(): void
{
    require_dm();
    page('dm/party', [
        'active'     => 'party',
        'characters' => characters_with_load(),
    ], 'Party & logins');
}

function dm_party_create(): void
{
    require_dm();
    $name     = post_str('name', 100);
    $class    = post_str('class_label', 100);
    $username = post_str('username', 50);
    $password = (string)($_POST['password'] ?? '');
    $capacity = post_float('carry_capacity', 150);

    // What a failed submit brings back. Never the password; a taken username is left out where it's the problem.
    $keep = ['name', 'class_label', 'carry_capacity', 'username'];
    $taken = fn() => back_with_error('/dm/party', "The username “{$username}” is already taken.", array_diff($keep, ['username']));

    if ($name === '') {
        back_with_error('/dm/party', 'Give the character a name.', $keep);
    }
    if ($username !== '' && strlen($password) < 6) {
        back_with_error('/dm/party', 'Player passwords need at least 6 characters.', $keep);
    }
    // A quick check saves hashing a password for a name we already know is taken; the unique key below
    // still has the final say when two submits race for the same username.
    if ($username !== '' && username_taken($username)) {
        $taken();
    }

    try {
        transaction(function () use ($name, $class, $username, $password, $capacity) {
            $userId = null;
            if ($username !== '') {
                $userId = create_login($username, $password, 'player', "Player for {$name} in " . current_campaign()['name']);
            }
            insert(
                'INSERT INTO characters (campaign_id, user_id, name, class_label, coin_cp, carry_capacity, created_at) VALUES (?, ?, ?, ?, 0, ?, ?)',
                [campaign_id(), $userId, $name, $class, max(0, $capacity), now()]
            );
        });
    } catch (PDOException $e) {
        if ($username === '' || !username_taken($username)) {
            throw $e;
        }
        $taken();
    }

    flash("{$name} has joined the party.");
    redirect('/dm/party');
}

function dm_party_password(int $characterId): void
{
    require_dm();
    $char = character($characterId) ?? abort(404, 'No such character.');
    $username = post_str('username', 50);
    $password = (string)($_POST['password'] ?? '');

    if (strlen($password) < 6) {
        htmx_fail('Passwords need at least 6 characters.');
    }

    if ($char['user_id']) {
        $login = q_one('SELECT id, username, role FROM users WHERE id = ?', [$char['user_id']])
            ?? htmx_fail("{$char['name']}’s login was just deleted. Reload the page to give them a new one.");
        transaction(fn() => set_password($login, $password, "Reset by their DM, for {$char['name']}"));
        toast("Password updated for {$char['name']}.");
    } else {
        if ($username === '') {
            htmx_fail('Enter a username for this player.');
        }
        if (username_taken($username)) {
            htmx_fail("The username “{$username}” is already taken.");
        }
        try {
            transaction(function () use ($username, $password, $char) {
                $characterId = (int)$char['id'];
                $userId = create_login($username, $password, 'player', "Player for {$char['name']} in " . current_campaign()['name']);
                // Only link a character that still has no login, so a second submit racing this one
                // can't leave the first login behind with no character.
                $linked = q('UPDATE characters SET user_id = ? WHERE id = ? AND user_id IS NULL', [$userId, $characterId]);
                if ($linked->rowCount() === 0) {
                    throw new DomainException('Character already has a login.'); // rolls back the new user
                }
            });
            toast("Login created for {$char['name']}.");
        } catch (DomainException) {
            // Show the row as it is now, with the login the other submit made.
            toast("{$char['name']} already has a login. Use the form to reset its password.", 'error');
        } catch (PDOException $e) {
            // Someone claimed the name between the check above and the insert.
            if (!username_taken($username)) {
                throw $e;
            }
            htmx_fail("The username “{$username}” is already taken.");
        }
    }

    $row = null;
    foreach (characters_with_load() as $c) {
        if ((int)$c['id'] === $characterId) {
            $row = $c;
        }
    }
    fragment('partials/party_row', ['c' => $row]);
}

function dm_party_delete(int $characterId): void
{
    require_dm();
    $char = character($characterId) ?? abort(404, 'No such character.');
    transaction(function () use ($char) {
        q('DELETE FROM characters WHERE id = ?', [$char['id']]);
        $login = $char['user_id'] ? q_one("SELECT id, username, role FROM users WHERE id = ? AND role = 'player'", [$char['user_id']]) : null;
        if ($login) {
            delete_login($login, "{$char['name']} left " . current_campaign()['name']);
        }
    });
    toast("{$char['name']} has left the party.");
    echo '';
    exit;
}
