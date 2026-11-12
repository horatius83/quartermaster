<?php
declare(strict_types=1);

// Shared queries used by several screens.
// Characters, stores, carts and history belong to a campaign. The lookups below only see the current one
// (the DM's chosen campaign, or a player's own), so an id from another campaign reads as "not found".
// Each DM sees only the campaigns they own, so another DM's campaign reads as "not found" too.

/** The systems a campaign can play, as stored => shown. A campaign's is chosen when it starts and never changes (but see settle_campaign_system). */
const CAMPAIGN_RULESETS = ['5e' => '5e', '3.5e' => '3.5e', 'pf1e' => 'Pathfinder 1e', 'pf2e' => 'Pathfinder 2e'];
/** The systems with items to load, in app/data/srd-<system>.php. */
const SRD_RULESETS = ['5e', '3.5e'];
/**
 * The sets of SRD items each of those systems loads separately, each from app/data/srd-<system><file>.php: what its
 * dashboard section is titled, how its button's name ends ("Load 5e SRD gear"), and what one of its items is called.
 */
const SRD_SETS = [
    'gear'  => ['file' => '', 'title' => 'Weapons, armor and gear', 'button' => 'gear', 'noun' => 'gear item'],
    'magic' => ['file' => '-magic', 'title' => 'Magic items', 'button' => 'magic items', 'noun' => 'magic item'],
];
const DEFAULT_RULESET = '5e';

/** The signed-in user's id, which owns the campaigns a DM sees. */
function owner_id(): int
{
    return (int)(current_user()['id'] ?? 0);
}

function campaigns(): array
{
    return q_all(
        'SELECT c.*,
                (SELECT COUNT(*) FROM characters ch WHERE ch.campaign_id = c.id) AS character_count,
                (SELECT COUNT(*) FROM stores s WHERE s.campaign_id = c.id) AS store_count
           FROM campaigns c WHERE c.owner_user_id = ? ORDER BY c.name, c.id',
        [owner_id()]
    );
}

/** Id and name of every campaign this DM runs, for the sidebar's campaign menu. */
function campaign_names(): array
{
    return q_all('SELECT id, name FROM campaigns WHERE owner_user_id = ? ORDER BY name, id', [owner_id()]);
}

function campaign(int $id): ?array
{
    return q_one('SELECT * FROM campaigns WHERE id = ? AND owner_user_id = ?', [$id, owner_id()]);
}

function create_campaign(string $name, int $ownerId, string $ruleset = DEFAULT_RULESET): int
{
    return insert('INSERT INTO campaigns (owner_user_id, name, ruleset, ruleset_open, created_at) VALUES (?, ?, ?, 0, ?)', [$ownerId, $name, $ruleset, now()]);
}

/** The system picked on a form, if it's one a campaign can play. */
function posted_campaign_ruleset(): string
{
    $ruleset = post_str('ruleset', 10);
    return isset(CAMPAIGN_RULESETS[$ruleset]) ? $ruleset : DEFAULT_RULESET;
}

/** The campaign being viewed: the one the DM last picked (or the first), or the player's character's. */
function current_campaign(): ?array
{
    static $cached = false;
    if ($cached !== false) {
        return $cached;
    }
    $user = current_user();
    if (!$user) {
        return null; // not cached: login may happen later in this request
    }
    if ($user['role'] === 'player') {
        return $cached = q_one('SELECT cp.* FROM campaigns cp JOIN characters ch ON ch.campaign_id = cp.id WHERE ch.user_id = ?', [$user['id']]);
    }
    if ($user['role'] !== 'dm') {
        return $cached = null; // the admin runs no campaigns
    }
    $campaign = campaign((int)($_SESSION['campaign_id'] ?? 0))
        ?? q_one('SELECT * FROM campaigns WHERE owner_user_id = ? ORDER BY id LIMIT 1', [$user['id']]);
    if (!$campaign) {
        // A new DM login, or one whose campaigns the admin handed on, starts one under Campaigns and picks its system
        // there (see require_dm). Not cached, since that campaign may be created later in this request.
        return null;
    }
    $_SESSION['campaign_id'] = (int)$campaign['id'];
    return $cached = $campaign;
}

function campaign_id(): int
{
    return (int)(current_campaign()['id'] ?? 0);
}

/** The system the current campaign plays. */
function campaign_ruleset(): string
{
    return current_campaign()['ruleset'] ?? DEFAULT_RULESET;
}

/** A system's name as shown to people. */
function ruleset_label(string $ruleset): string
{
    return CAMPAIGN_RULESETS[$ruleset] ?? $ruleset;
}

/**
 * Say which system a campaign from before systems really plays, which can only happen once. Call inside a transaction.
 * The upgrade labelled everything 5e, so the items this campaign uses (in its packs, on its shelves, in its carts, its
 * sales and its history) are copied into the chosen system, and the campaign moves to the copies. Only those: the shared catalog
 * also holds other DMs' items, which aren't this campaign's to take along. The originals stay, since other campaigns
 * may really be 5e, and a name the chosen system already has means that item. Returns how many items were copied.
 */
function settle_campaign_system(array $campaign, string $ruleset): int
{
    $copied = 0;
    if ($ruleset !== $campaign['ruleset']) {
        $id = (int)$campaign['id'];
        $used = q_all(
            'SELECT * FROM items WHERE ruleset = ? AND id IN (
                 SELECT inv.item_id FROM inventory inv JOIN characters ch ON ch.id = inv.character_id WHERE ch.campaign_id = ?
                 UNION SELECT st.item_id FROM store_stock st JOIN stores s ON s.id = st.store_id WHERE s.campaign_id = ?
                 UNION SELECT ci.item_id FROM cart_items ci JOIN carts c ON c.id = ci.cart_id JOIN stores s ON s.id = c.store_id WHERE s.campaign_id = ?
                 UNION SELECT sa.item_id FROM sales sa JOIN characters ch ON ch.id = sa.character_id WHERE ch.campaign_id = ?
                 UNION SELECT l.item_id FROM inventory_log l WHERE l.campaign_id = ?)',
            [$campaign['ruleset'], $id, $id, $id, $id, $id]
        );
        foreach (with_item_tags($used) as $item) {
            $copy = item_by_name($item['name'], $ruleset);
            if (!$copy) {
                try {
                    $copy = create_item($item['name'], (int)$item['category_id'], (float)$item['weight'], (int)$item['price_cp'], $item['description'] ?? '', $ruleset);
                    set_item_tags((int)$copy['id'], $item['tags']);
                    $copied++;
                } catch (PDOException $e) {
                    // Another DM's campaign copied the same name just now: use theirs.
                    $copy = item_by_name($item['name'], $ruleset) ?? throw $e;
                }
            }
            $move = [(int)$copy['id'], (int)$item['id'], $id];
            q('UPDATE inventory SET item_id = ? WHERE item_id = ? AND character_id IN (SELECT id FROM characters WHERE campaign_id = ?)', $move);
            q('UPDATE store_stock SET item_id = ? WHERE item_id = ? AND store_id IN (SELECT id FROM stores WHERE campaign_id = ?)', $move);
            q('UPDATE cart_items SET item_id = ? WHERE item_id = ? AND cart_id IN (SELECT c.id FROM carts c JOIN stores s ON s.id = c.store_id WHERE s.campaign_id = ?)', $move);
            q('UPDATE sales SET item_id = ? WHERE item_id = ? AND character_id IN (SELECT id FROM characters WHERE campaign_id = ?)', $move);
            q('UPDATE inventory_log SET item_id = ? WHERE item_id = ? AND campaign_id = ?', $move);
        }
    }
    q('UPDATE campaigns SET ruleset = ?, ruleset_open = 0 WHERE id = ?', [$ruleset, $campaign['id']]);
    return $copied;
}

/** The DM picks which campaign the other DM screens show. */
function switch_campaign(int $id): void
{
    $_SESSION['campaign_id'] = $id;
}

function categories(): array
{
    static $cats = null;
    return $cats ??= q_all('SELECT id, name, slug FROM categories ORDER BY sort_order, name');
}

function category_by_id(int $id): ?array
{
    foreach (categories() as $c) {
        if ((int)$c['id'] === $id) {
            return $c;
        }
    }
    return null;
}

/** All characters with their carried weight and item counts. */
function characters_with_load(): array
{
    return q_all(
        'SELECT c.*, u.username,
                COALESCE(SUM(i.weight * inv.quantity), 0) AS load_weight,
                COALESCE(SUM(inv.quantity), 0) AS item_count
           FROM characters c
           LEFT JOIN users u ON u.id = c.user_id
           LEFT JOIN inventory inv ON inv.character_id = c.id
           LEFT JOIN items i ON i.id = inv.item_id
          WHERE c.campaign_id = ?
          GROUP BY c.id, c.campaign_id, c.user_id, c.name, c.class_label, c.coin_cp, c.carry_capacity, c.created_at, u.username
          ORDER BY c.name',
        [campaign_id()]
    );
}

/** Id and name of everyone in the current campaign's party, for pickers. */
function party_names(): array
{
    return q_all('SELECT id, name FROM characters WHERE campaign_id = ? ORDER BY name', [campaign_id()]);
}

/**
 * Record a change to a login in the account log, with who made it: $action is 'create', 'delete', 'password'
 * (a new password; never the password itself), 'lockout' (too many wrong passwords for it, or with no
 * $userId, from one address for any names), or 'move' (a campaign handed to the DM $userId). Call inside the transaction that makes the change.
 * $actor defaults to the signed-in user.
 */
function log_user(string $action, ?int $userId, string $username, string $role, string $detail = '', ?array $actor = null): void
{
    $actor ??= current_user();
    q(
        'INSERT INTO user_log (actor_user_id, actor_name, action, user_id, username, role, detail, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [$actor['id'] ?? null, $actor['username'] ?? '', $action, $userId, $username, $role, mb_substr($detail, 0, 255), now()]
    );
}

/**
 * Create a login and log it. Every login is made through here, so none can skip the account log.
 * With nobody signed in (the first DM, at setup) the new login is logged as having made itself.
 * Call inside a transaction. Throws PDOException if the username is taken. Returns the new id.
 */
function create_login(string $username, string $password, string $role, string $detail = ''): int
{
    $id = insert(
        'INSERT INTO users (username, password_hash, role, created_at) VALUES (?, ?, ?, ?)',
        [$username, hash_password($password), $role, now()]
    );
    log_user('create', $id, $username, $role, $detail, current_user() ?? ['id' => $id, 'username' => $username]);
    return $id;
}

/**
 * Give a login ($user needs id, username and role) a new password and log it. Bumping session_version
 * signs out every session of that login, so a reset locks out whoever else was using it; to stay signed
 * in after changing your own, call login_user() with just your id once this commits.
 * Call inside a transaction.
 */
function set_password(array $user, string $password, string $detail): void
{
    q('UPDATE users SET password_hash = ?, session_version = session_version + 1 WHERE id = ?', [hash_password($password), $user['id']]);
    log_user('password', (int)$user['id'], $user['username'], $user['role'], $detail);
}

/** Delete a login ($user needs id, username and role) and log it. Call inside a transaction. */
function delete_login(array $user, string $detail = ''): void
{
    q('DELETE FROM users WHERE id = ?', [$user['id']]);
    log_user('delete', (int)$user['id'], $user['username'], $user['role'], $detail);
}

/** Whether the admin login exists yet. Once it does it always will (the last admin can't be deleted), so the session remembers. */
function admin_exists(): bool
{
    if (!empty($_SESSION['admin_exists'])) {
        return true;
    }
    return $_SESSION['admin_exists'] = (bool)q_val("SELECT 1 FROM users WHERE role = 'admin' LIMIT 1");
}

/** Whether any login already uses $username (compared the way the unique key compares it). */
function username_taken(string $username): bool
{
    return (bool)q_val('SELECT id FROM users WHERE username = ?', [$username]);
}

function character(int $id): ?array
{
    return q_one('SELECT * FROM characters WHERE id = ? AND campaign_id = ?', [$id, campaign_id()]);
}

function inventory_rows(int $characterId, string $categorySlug = ''): array
{
    $sql = 'SELECT inv.id, inv.quantity, inv.notes, i.id AS item_id, i.name, i.weight, i.price_cp,
                   cat.name AS category, cat.slug AS category_slug
              FROM inventory inv
              JOIN items i ON i.id = inv.item_id
              JOIN categories cat ON cat.id = i.category_id
             WHERE inv.character_id = ?';
    $params = [$characterId];
    if ($categorySlug !== '') {
        $sql .= ' AND cat.slug = ?';
        $params[] = $categorySlug;
    }
    $sql .= ' ORDER BY cat.sort_order, i.name';
    return q_all($sql, $params);
}

function inventory_row(int $rowId): ?array
{
    return q_one(
        'SELECT inv.id, inv.character_id, inv.quantity, inv.notes, i.id AS item_id, i.name, i.weight, i.price_cp,
                cat.name AS category, cat.slug AS category_slug
           FROM inventory inv
           JOIN items i ON i.id = inv.item_id
           JOIN categories cat ON cat.id = i.category_id
           JOIN characters ch ON ch.id = inv.character_id
          WHERE inv.id = ? AND ch.campaign_id = ?',
        [$rowId, campaign_id()]
    );
}

function carried_weight(int $characterId): float
{
    return (float)q_val(
        'SELECT COALESCE(SUM(i.weight * inv.quantity), 0)
           FROM inventory inv JOIN items i ON i.id = inv.item_id
          WHERE inv.character_id = ?',
        [$characterId]
    );
}

/** Add items to a character's pack, stacking onto an existing row with the same item and notes. */
function add_to_inventory(int $characterId, int $itemId, int $qty, string $notes = ''): void
{
    $existing = q_one(
        'SELECT id FROM inventory WHERE character_id = ? AND item_id = ? AND notes = ?',
        [$characterId, $itemId, $notes]
    );
    if ($existing) {
        q('UPDATE inventory SET quantity = quantity + ? WHERE id = ?', [$qty, $existing['id']]);
    } else {
        q('INSERT INTO inventory (character_id, item_id, quantity, notes) VALUES (?, ?, ?, ?)', [$characterId, $itemId, $qty, $notes]);
    }
}

const HISTORY_PAGE_SIZE = 50;

/**
 * Record a change to a character's pack or purse. $qty and $coinCp are signed (negative = taken away).
 * Repeated clicks in the same direction by the same person on the same stack, within five minutes of
 * the first, fold into one 'adjust' entry. A − after a + starts a new entry, so nothing is ever erased.
 * Call inside the transaction that makes the change.
 */
function log_inventory(int $characterId, string $action, ?array $item = null, int $qty = 0, int $coinCp = 0, string $notes = '', string $detail = ''): void
{
    if ($qty === 0 && $coinCp === 0) {
        return;
    }
    $actor = current_user();
    $itemId = $item ? (int)$item['item_id'] : null;

    if ($action === 'adjust') {
        $last = q_one('SELECT * FROM inventory_log WHERE character_id = ? ORDER BY id DESC LIMIT 1' . for_update(), [$characterId]);
        if ($last && $last['action'] === 'adjust' && (int)$last['item_id'] === $itemId && $last['notes'] === $notes
            && (int)$last['actor_user_id'] === (int)($actor['id'] ?? 0)
            && ((int)$last['quantity'] > 0) === ($qty > 0)
            && strtotime($last['created_at'] . ' UTC') > time() - 300) {
            q('UPDATE inventory_log SET quantity = quantity + ? WHERE id = ?', [$qty, $last['id']]);
            return;
        }
    }

    q(
        'INSERT INTO inventory_log (campaign_id, character_id, character_name, actor_user_id, actor_name, action, item_id, item_name, notes, quantity, coin_cp, detail, created_at)
         SELECT campaign_id, id, name, ?, ?, ?, ?, ?, ?, ?, ?, ?, ? FROM characters WHERE id = ?',
        [$actor['id'] ?? null, $actor['username'] ?? '', $action, $itemId, $item['name'] ?? '',
         $notes, $qty, $coinCp, mb_substr($detail, 0, 255), now(), $characterId]
    );
}

/**
 * Pack history, newest first, one page at a time. $characterId null = the current campaign's whole party,
 * including characters who have since been deleted.
 * @return array{0: array, 1: bool} the rows, and whether older ones exist
 */
function inventory_log_page(?int $characterId, int $beforeId = 0): array
{
    $sql = 'SELECT * FROM inventory_log WHERE campaign_id = ?';
    $params = [campaign_id()];
    if ($characterId !== null) {
        $sql .= ' AND character_id = ?';
        $params[] = $characterId;
    }
    if ($beforeId > 0) {
        $sql .= ' AND id < ?';
        $params[] = $beforeId;
    }
    $rows = q_all($sql . ' ORDER BY id DESC LIMIT ' . (HISTORY_PAGE_SIZE + 1), $params);
    return [array_slice($rows, 0, HISTORY_PAGE_SIZE), count($rows) > HISTORY_PAGE_SIZE];
}

/** The item with this name in this system. Names only have to be unique within one. */
function item_by_name(string $name, string $ruleset): ?array
{
    return q_one('SELECT * FROM items WHERE LOWER(name) = LOWER(?) AND ruleset = ?', [$name, $ruleset]);
}

/** Find the current campaign's item by name, or create it in that campaign's system with the given details. */
function find_or_create_item(string $name, int $categoryId, float $weight, int $priceCp): array
{
    return item_by_name($name, campaign_ruleset()) ?? create_item($name, $categoryId, $weight, $priceCp);
}

/** Add an item to the catalog, by default in the current campaign's system. Throws PDOException if the name is taken there. */
function create_item(string $name, int $categoryId, float $weight, int $priceCp, string $description = '', ?string $ruleset = null): array
{
    if (!category_by_id($categoryId)) {
        $categoryId = (int)categories()[0]['id'];
    }
    $id = insert(
        'INSERT INTO items (ruleset, name, category_id, weight, price_cp, description) VALUES (?, ?, ?, ?, ?, ?)',
        [$ruleset ?? campaign_ruleset(), $name, $categoryId, max(0, $weight), max(0, $priceCp), $description === '' ? null : $description]
    );
    return q_one('SELECT * FROM items WHERE id = ?', [$id]);
}

const MAX_TAG_LENGTH = 30;

/** Replace an item's tags. Call inside a transaction. */
function set_item_tags(int $itemId, array $tags): void
{
    q('DELETE FROM item_tags WHERE item_id = ?', [$itemId]);
    foreach ($tags as $tag) {
        q('INSERT INTO item_tags (item_id, tag) VALUES (?, ?)', [$itemId, $tag]);
    }
}

/** Give each row a 'tags' list, looking up the item whose id is in $idKey. */
function with_item_tags(array $rows, string $idKey = 'id'): array
{
    if (!$rows) {
        return [];
    }
    $ids = array_map('intval', array_column($rows, $idKey));
    $found = q_all('SELECT item_id, tag FROM item_tags WHERE item_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY tag', $ids);
    $byItem = [];
    foreach ($found as $f) {
        $byItem[(int)$f['item_id']][] = $f['tag'];
    }
    foreach ($rows as &$row) {
        $row['tags'] = $byItem[(int)$row[$idKey]] ?? [];
    }
    return $rows;
}

/** The rows (from with_item_tags) carrying every one of $tags. */
function having_tags(array $rows, array $tags): array
{
    return array_values(array_filter($rows, fn($r) => !array_diff($tags, $r['tags'])));
}

/** Every tag in use anywhere in the catalog. */
function all_tags(): array
{
    return array_column(q_all(
        'SELECT DISTINCT t.tag FROM item_tags t JOIN items i ON i.id = t.item_id WHERE i.ruleset = ? ORDER BY t.tag',
        [campaign_ruleset()]
    ), 'tag');
}

function stores_summary(): array
{
    return q_all(
        'SELECT s.*, (SELECT COUNT(*) FROM store_stock st WHERE st.store_id = s.id) AS stock_count
           FROM stores s WHERE s.campaign_id = ? ORDER BY s.name',
        [campaign_id()]
    );
}

function store(int $id): ?array
{
    return q_one('SELECT * FROM stores WHERE id = ? AND campaign_id = ?', [$id, campaign_id()]);
}

function store_stock_rows(int $storeId): array
{
    $sql = 'SELECT st.id, st.store_id, st.price_cp, st.quantity, i.id AS item_id, i.name, i.weight,
                   cat.name AS category, cat.slug AS category_slug
              FROM store_stock st
              JOIN items i ON i.id = st.item_id
              JOIN categories cat ON cat.id = i.category_id
             WHERE st.store_id = ?
             ORDER BY cat.sort_order, i.name';
    return q_all($sql, [$storeId]);
}

function stock_row(int $id): ?array
{
    return q_one(
        'SELECT st.id, st.store_id, st.price_cp, st.quantity, i.id AS item_id, i.name, i.weight,
                cat.name AS category, cat.slug AS category_slug
           FROM store_stock st
           JOIN items i ON i.id = st.item_id
           JOIN categories cat ON cat.id = i.category_id
           JOIN stores s ON s.id = st.store_id
          WHERE st.id = ? AND s.campaign_id = ?',
        [$id, campaign_id()]
    );
}

/** Character ids allowed in a store; empty array = everyone. */
function store_access_ids(int $storeId): array
{
    return array_map('intval', array_column(q_all('SELECT character_id FROM store_access WHERE store_id = ?', [$storeId]), 'character_id'));
}

/** Open stores this character may shop in. */
function stores_for_character(int $characterId): array
{
    return q_all(
        'SELECT s.* FROM stores s
          WHERE s.is_open = 1
            AND s.campaign_id = (SELECT campaign_id FROM characters WHERE id = ?)
            AND (NOT EXISTS (SELECT 1 FROM store_access a WHERE a.store_id = s.id)
                 OR EXISTS (SELECT 1 FROM store_access a WHERE a.store_id = s.id AND a.character_id = ?))
          ORDER BY s.name',
        [$characterId, $characterId]
    );
}

function pending_cart_count(): int
{
    return (int)q_val(
        "SELECT COUNT(*) FROM carts c JOIN characters ch ON ch.id = c.character_id WHERE c.status = 'submitted' AND ch.campaign_id = ?",
        [campaign_id()]
    );
}

/** A cart with its lines, total, character and store. */
function cart_detail(int $cartId): ?array
{
    $cart = q_one(
        'SELECT c.*, ch.name AS character_name, ch.coin_cp, s.name AS store_name
           FROM carts c
           JOIN characters ch ON ch.id = c.character_id
           JOIN stores s ON s.id = c.store_id
          WHERE c.id = ? AND ch.campaign_id = ?',
        [$cartId, campaign_id()]
    );
    if (!$cart) {
        return null;
    }
    $cart['lines'] = q_all(
        'SELECT ci.id, ci.item_id, ci.quantity, ci.unit_price_cp, i.name, i.weight,
                cat.name AS category, cat.slug AS category_slug,
                st.quantity AS stock_left
           FROM cart_items ci
           JOIN items i ON i.id = ci.item_id
           JOIN categories cat ON cat.id = i.category_id
           LEFT JOIN store_stock st ON st.store_id = ? AND st.item_id = ci.item_id
          WHERE ci.cart_id = ?
          ORDER BY cat.sort_order, i.name',
        [$cart['store_id'], $cartId]
    );
    $cart['total_cp'] = 0;
    $cart['unit_count'] = 0;
    foreach ($cart['lines'] as $line) {
        $cart['total_cp'] += (int)$line['quantity'] * (int)$line['unit_price_cp'];
        $cart['unit_count'] += (int)$line['quantity'];
    }
    return $cart;
}

/** The most a sale can ask or be offered, so a purse can't run past what its column holds (10 million gp). */
const MAX_SALE_CP = 1_000_000_000;
/** Sales still waiting on someone: the DM (pending) or the player (countered). Their items count as on offer. */
const OPEN_SALE_STATUSES = "('pending','countered')";

/** Offers to sell waiting on the DM, for the sidebar badge. */
function pending_sale_count(): int
{
    return (int)q_val(
        "SELECT COUNT(*) FROM sales sa JOIN characters ch ON ch.id = sa.character_id WHERE sa.status = 'pending' AND ch.campaign_id = ?",
        [campaign_id()]
    );
}

/** A character's offers with a counter-offer waiting on them, for the player's badge. */
function countered_sale_count(int $characterId): int
{
    return (int)q_val("SELECT COUNT(*) FROM sales WHERE character_id = ? AND status = 'countered'", [$characterId]);
}

/**
 * What a sale sells for: what it sold for once sold, else the DM's counter-offer while one stands, else the asking price.
 * A DM who accepts the asking price after countering sells at the asking price, which sold_cp records.
 */
function sale_price(array $sale): int
{
    return (int)($sale['sold_cp'] ?? $sale['offer_cp'] ?? $sale['asking_cp']);
}

/**
 * The current campaign's sales matching $where (on sales sa and characters ch), each with its item, character, how many
 * of that stack the pack holds now ('held'), and 'price_cp' from sale_price().
 */
function sale_list(string $where, array $params = [], string $order = 'sa.created_at', int $limit = 0): array
{
    $sales = q_all(
        'SELECT sa.*, ch.name AS character_name, ch.coin_cp, i.name, i.price_cp AS list_price_cp,
                cat.name AS category, cat.slug AS category_slug,
                (SELECT COALESCE(SUM(inv.quantity), 0) FROM inventory inv
                  WHERE inv.character_id = sa.character_id AND inv.item_id = sa.item_id AND inv.notes = sa.notes) AS held
           FROM sales sa
           JOIN characters ch ON ch.id = sa.character_id
           JOIN items i ON i.id = sa.item_id
           JOIN categories cat ON cat.id = i.category_id
          WHERE ch.campaign_id = ? AND ' . $where . ' ORDER BY ' . $order . ', sa.id' . ($limit ? ' LIMIT ' . $limit : ''),
        [campaign_id(), ...$params]
    );
    return array_map(fn($s) => $s + ['price_cp' => sale_price($s)], $sales);
}

function sale_detail(int $saleId): ?array
{
    return sale_list('sa.id = ?', [$saleId])[0] ?? null;
}

/** The key offered_quantities() files a stack under. */
function stack_key(int $itemId, string $notes): string
{
    return $itemId . "\0" . $notes;
}

/**
 * How many of each of a character's stacks are already on offer, by stack_key(), so they can't be offered twice.
 * Call inside a transaction, after locking the stack, to rely on the count.
 */
function offered_quantities(int $characterId): array
{
    $rows = q_all(
        'SELECT item_id, notes, SUM(quantity) AS qty FROM sales
          WHERE character_id = ? AND status IN ' . OPEN_SALE_STATUSES . ' GROUP BY item_id, notes',
        [$characterId]
    );
    $offered = [];
    foreach ($rows as $r) {
        $offered[stack_key((int)$r['item_id'], $r['notes'])] = (int)$r['qty'];
    }
    return $offered;
}

/**
 * Close a sale: take its items from the pack, pay its price into the purse and log both, in one transaction.
 * The DM ($byPlayer false) accepts the asking price, of an offer pending or countered (changing their mind about the
 * counter); the player accepts the DM's counter-offer. With $seenPrice, it only sells at that price, so a counter-offer
 * the DM changed meanwhile isn't taken unseen. Throws RuntimeException, with a message to show, when it can't.
 */
function settle_sale(int $saleId, bool $byPlayer, ?int $seenPrice = null): void
{
    transaction(function () use ($saleId, $byPlayer, $seenPrice) {
        // Lock the sale, then the purse and the stack, so two clicks can't sell the same items twice.
        $sale = q_one('SELECT * FROM sales WHERE id = ?' . for_update(), [$saleId]);
        if (!$sale || !in_array($sale['status'], $byPlayer ? ['countered'] : ['pending', 'countered'], true)) {
            throw new RuntimeException('That offer has already been settled.');
        }
        $char = q_one('SELECT * FROM characters WHERE id = ?' . for_update(), [$sale['character_id']]);
        $item = q_one('SELECT id AS item_id, name FROM items WHERE id = ?', [$sale['item_id']]);
        $rows = q_all(
            'SELECT id, quantity FROM inventory WHERE character_id = ? AND item_id = ? AND notes = ? ORDER BY id' . for_update(),
            [$sale['character_id'], $sale['item_id'], $sale['notes']]
        );
        $qty = (int)$sale['quantity'];
        $held = array_sum(array_map('intval', array_column($rows, 'quantity')));
        if ($held < $qty) {
            throw new RuntimeException($held
                ? "{$char['name']} has only {$held} × {$item['name']} left, not {$qty}."
                : "{$char['name']} no longer has the {$item['name']}.");
        }
        $price = $byPlayer ? (int)$sale['offer_cp'] : (int)$sale['asking_cp'];
        if ($seenPrice !== null && $seenPrice !== $price) {
            throw new RuntimeException('Your DM changed their offer to ' . coins($price) . '. Take another look.');
        }
        if ((int)$char['coin_cp'] + $price > 2_147_483_647) {
            throw new RuntimeException("{$char['name']}’s purse can’t hold that much coin.");
        }

        $left = $qty;
        foreach ($rows as $r) {
            $take = min($left, (int)$r['quantity']);
            if ($take === (int)$r['quantity']) {
                q('DELETE FROM inventory WHERE id = ?', [$r['id']]);
            } else {
                q('UPDATE inventory SET quantity = quantity - ? WHERE id = ?', [$take, $r['id']]);
            }
            if (($left -= $take) === 0) {
                break;
            }
        }
        q('UPDATE characters SET coin_cp = coin_cp + ? WHERE id = ?', [$price, $char['id']]);
        log_inventory((int)$char['id'], 'sell', $item, -$qty, notes: $sale['notes'], detail: 'Sold for ' . coins($price));
        log_inventory((int)$char['id'], 'sell', coinCp: $price, detail: "For {$qty} × {$item['name']}");
        q("UPDATE sales SET status = 'sold', sold_cp = ?, decided_at = ? WHERE id = ?", [$price, now(), $saleId]);
    });
}

/** A name in the form the SRD import compares, since DMs usually type a straight apostrophe where the data has a curly one. */
function srd_name_key(string $name): string
{
    return mb_strtolower(str_replace('’', "'", $name));
}

/**
 * One set (a key of SRD_SETS) of the bundled SRD items for a system in SRD_RULESETS:
 * [name, category slug, weight, price, tags, description] rows.
 */
function srd_items(string $ruleset, string $set = 'gear'): array
{
    if (!isset(SRD_SETS[$set])) {
        throw new InvalidArgumentException("There is no SRD set called {$set}.");
    }
    return require APP_DIR . "/data/srd-{$ruleset}" . SRD_SETS[$set]['file'] . '.php';
}

/**
 * Sentences that earlier versions of a set's SRD data had, each pointing to a table the catalog doesn't include. Loading
 * the set takes them out of descriptions already in the catalog, so a catalog loaded before they were cut loses them too.
 */
const SRD_STALE_TEXT = [
    '5e' => ['gear' => [
        ' The table shows examples of the most common types of tools, each providing items related to a single craft.',
        'Several of the most common types of musical instruments are shown on the table as examples. ',
        ' A few common examples appear on the Tools table, but other kinds of gaming sets exist.',
    ]],
];

/**
 * A system's descriptions with a set's stale sentences taken out, as SQL and its parameters: REPLACE is exact in both
 * databases, so comparing its result with the description finds the rows it would change, whatever the collation.
 */
function srd_tidied_description(string $ruleset, string $set): ?array
{
    $stale = SRD_STALE_TEXT[$ruleset][$set] ?? [];
    if (!$stale) {
        return null;
    }
    $sql = 'description';
    foreach ($stale as $_) {
        $sql = "REPLACE({$sql}, ?, '')";
    }
    return [$sql, $stale];
}

/** How many of a system's descriptions still have one of a set's stale sentences. */
function srd_stale_count(string $ruleset, string $set): int
{
    if (!$tidied = srd_tidied_description($ruleset, $set)) {
        return 0;
    }
    [$sql, $params] = $tidied;
    return (int)q_val("SELECT COUNT(*) FROM items WHERE ruleset = ? AND description <> {$sql}", [$ruleset, ...$params]);
}

/** The name keys a system's catalog already has, to compare SRD rows against. */
function srd_taken_keys(string $ruleset): array
{
    return array_flip(array_map(srd_name_key(...), array_column(q_all('SELECT name FROM items WHERE ruleset = ?', [$ruleset]), 'name')));
}

/**
 * How much of one set of a system's SRD is in its catalog: ['total' => rows bundled, 'present' => how many of those it
 * has]. Pass the system's srd_taken_keys() when checking several sets, so the catalog is read once.
 */
function srd_status(string $ruleset, string $set = 'gear', ?array $taken = null): array
{
    $taken ??= srd_taken_keys($ruleset);
    $rows = srd_items($ruleset, $set);
    $present = count(array_filter($rows, fn($row) => isset($taken[srd_name_key($row[0])])));
    return ['total' => count($rows), 'present' => $present];
}

/**
 * Add one set of a system's SRD items to its catalog: ['added' => how many were, 'skipped' => how many it already
 * had, 'tidied' => how many descriptions lost a stale sentence]. A name that system already has is skipped, so a DM's
 * own prices and descriptions are never overwritten; only the sentences in SRD_STALE_TEXT are taken out of them.
 */
function import_srd(string $ruleset, string $set = 'gear'): array
{
    $categoryIds = array_column(categories(), 'id', 'slug');
    $rows = srd_items($ruleset, $set);
    // The database decides what counts as the same name, so its collation is what's asked when an insert fails, not PHP.
    $seen = srd_taken_keys($ruleset);
    return transaction(function () use ($ruleset, $set, $categoryIds, $rows, $seen) {
        $added = 0;
        foreach ($rows as [$name, $slug, $weight, $price, $tags, $description]) {
            if (isset($seen[srd_name_key($name)])) {
                continue;
            }
            try {
                $item = create_item($name, (int)$categoryIds[$slug], (float)$weight, parse_price($price) ?? 0, $description, $ruleset);
            } catch (PDOException $e) {
                // The database's own rules call it taken after all (an accented spelling, or someone adding it just now).
                if (!item_by_name($name, $ruleset)) {
                    throw $e;
                }
                continue;
            }
            set_item_tags((int)$item['id'], $tags);
            $added++;
        }
        $tidied = 0;
        if ($replace = srd_tidied_description($ruleset, $set)) {
            [$sql, $params] = $replace;
            $tidied = q(
                "UPDATE items SET description = {$sql} WHERE ruleset = ? AND description <> {$sql}",
                [...$params, $ruleset, ...$params]
            )->rowCount();
        }
        return ['added' => $added, 'skipped' => count($rows) - $added, 'tidied' => $tidied];
    });
}
