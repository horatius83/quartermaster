<?php
declare(strict_types=1);

// Shared queries used by several screens.
// Characters, stores, carts and history belong to a campaign. The lookups below only see the current one
// (the DM's chosen campaign, or a player's own), so an id from another campaign reads as "not found".

function campaigns(): array
{
    return q_all(
        'SELECT c.*,
                (SELECT COUNT(*) FROM characters ch WHERE ch.campaign_id = c.id) AS character_count,
                (SELECT COUNT(*) FROM stores s WHERE s.campaign_id = c.id) AS store_count
           FROM campaigns c ORDER BY c.name, c.id'
    );
}

function campaign(int $id): ?array
{
    return q_one('SELECT * FROM campaigns WHERE id = ?', [$id]);
}

function create_campaign(string $name): int
{
    return insert('INSERT INTO campaigns (name, created_at) VALUES (?, ?)', [$name, now()]);
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
    $campaign = campaign((int)($_SESSION['campaign_id'] ?? 0))
        ?? q_one('SELECT * FROM campaigns ORDER BY id LIMIT 1')
        ?? campaign(create_campaign('My Campaign'));
    $_SESSION['campaign_id'] = (int)$campaign['id'];
    return $cached = $campaign;
}

function campaign_id(): int
{
    return (int)(current_campaign()['id'] ?? 0);
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

function item_by_name(string $name): ?array
{
    return q_one('SELECT * FROM items WHERE LOWER(name) = LOWER(?)', [$name]);
}

/** Find a catalog item by name, or create it with the given details. */
function find_or_create_item(string $name, int $categoryId, float $weight, int $priceCp): array
{
    $item = item_by_name($name);
    if ($item) {
        return $item;
    }
    if (!category_by_id($categoryId)) {
        $categoryId = (int)categories()[0]['id'];
    }
    $id = insert(
        'INSERT INTO items (name, category_id, weight, price_cp) VALUES (?, ?, ?, ?)',
        [$name, $categoryId, max(0, $weight), max(0, $priceCp)]
    );
    return q_one('SELECT * FROM items WHERE id = ?', [$id]);
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

function store_stock_rows(int $storeId, string $categorySlug = ''): array
{
    $sql = 'SELECT st.id, st.store_id, st.price_cp, st.quantity, i.id AS item_id, i.name, i.weight,
                   cat.name AS category, cat.slug AS category_slug
              FROM store_stock st
              JOIN items i ON i.id = st.item_id
              JOIN categories cat ON cat.id = i.category_id
             WHERE st.store_id = ?';
    $params = [$storeId];
    if ($categorySlug !== '') {
        $sql .= ' AND cat.slug = ?';
        $params[] = $categorySlug;
    }
    $sql .= ' ORDER BY cat.sort_order, i.name';
    return q_all($sql, $params);
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
