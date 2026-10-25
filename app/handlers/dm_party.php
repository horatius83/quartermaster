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

    // On a validation error, bring back what the DM typed (never the password, nor a username that's taken).
    $kept = ['name' => $name, 'class_label' => $class, 'carry_capacity' => $capacity, 'username' => $username];

    if ($name === '') {
        remember_input($kept);
        flash('Give the character a name.', 'error');
        redirect('/dm/party');
    }
    if ($username !== '' && strlen($password) < 6) {
        remember_input($kept);
        flash('Player passwords need at least 6 characters.', 'error');
        redirect('/dm/party');
    }
    if ($username !== '' && q_val('SELECT id FROM users WHERE username = ?', [$username])) {
        remember_input(['username' => ''] + $kept);
        flash("The username “{$username}” is already taken.", 'error');
        redirect('/dm/party');
    }

    transaction(function () use ($name, $class, $username, $password, $capacity) {
        $userId = null;
        if ($username !== '') {
            $userId = insert(
                "INSERT INTO users (username, password_hash, role, created_at) VALUES (?, ?, 'player', ?)",
                [$username, password_hash($password, PASSWORD_DEFAULT), now()]
            );
        }
        insert(
            'INSERT INTO characters (campaign_id, user_id, name, class_label, coin_cp, carry_capacity, created_at) VALUES (?, ?, ?, ?, 0, ?, ?)',
            [campaign_id(), $userId, $name, $class, max(0, $capacity), now()]
        );
    });

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
        q('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $char['user_id']]);
        toast("Password updated for {$char['name']}.");
    } else {
        if ($username === '') {
            htmx_fail('Enter a username for this player.');
        }
        if (q_val('SELECT id FROM users WHERE username = ?', [$username])) {
            htmx_fail("The username “{$username}” is already taken.");
        }
        transaction(function () use ($username, $password, $characterId) {
            $userId = insert(
                "INSERT INTO users (username, password_hash, role, created_at) VALUES (?, ?, 'player', ?)",
                [$username, password_hash($password, PASSWORD_DEFAULT), now()]
            );
            q('UPDATE characters SET user_id = ? WHERE id = ?', [$userId, $characterId]);
        });
        toast("Login created for {$char['name']}.");
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
        if ($char['user_id']) {
            q("DELETE FROM users WHERE id = ? AND role = 'player'", [$char['user_id']]);
        }
    });
    toast("{$char['name']} has left the party.");
    echo '';
    exit;
}
