<?php
declare(strict_types=1);

// Shared queries used by several screens.

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
          GROUP BY c.id, c.user_id, c.name, c.class_label, c.coin_cp, c.carry_capacity, c.created_at, u.username
          ORDER BY c.name'
    );
}

function character(int $id): ?array
{
    return q_one('SELECT * FROM characters WHERE id = ?', [$id]);
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
          WHERE inv.id = ?',
        [$rowId]
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

/**
 * Record a change to a character's pack or purse. $qty and $coinCp are signed (negative = taken away).
 * Repeated −/+ clicks by the same person on the same stack fold into one 'adjust' entry.
 */
function log_inventory(int $characterId, string $action, ?array $item = null, int $qty = 0, int $coinCp = 0, string $notes = '', string $detail = ''): void
{
    if ($qty === 0 && $coinCp === 0) {
        return;
    }
    $actor = current_user();
    $itemId = $item ? (int)$item['item_id'] : null;

    if ($action === 'adjust') {
        $last = q_one('SELECT * FROM inventory_log WHERE character_id = ? ORDER BY id DESC LIMIT 1', [$characterId]);
        if ($last && $last['action'] === 'adjust' && (int)$last['item_id'] === $itemId && $last['notes'] === $notes
            && (int)$last['actor_user_id'] === (int)($actor['id'] ?? 0)
            && strtotime($last['created_at'] . ' UTC') > time() - 300) {
            $merged = (int)$last['quantity'] + $qty;
            if ($merged === 0) {
                q('DELETE FROM inventory_log WHERE id = ?', [$last['id']]);
            } else {
                q('UPDATE inventory_log SET quantity = ?, created_at = ? WHERE id = ?', [$merged, now(), $last['id']]);
            }
            return;
        }
    }

    q(
        'INSERT INTO inventory_log (character_id, actor_user_id, actor_name, action, item_id, item_name, notes, quantity, coin_cp, detail, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$characterId, $actor['id'] ?? null, $actor['username'] ?? '', $action, $itemId, $item['name'] ?? '',
         $notes, $qty, $coinCp, mb_substr($detail, 0, 255), now()]
    );
}

/** Pack history, newest first. $characterId null = the whole party. */
function inventory_log_rows(?int $characterId, int $beforeId = 0, int $limit = 50): array
{
    $sql = 'SELECT l.*, c.name AS character_name FROM inventory_log l JOIN characters c ON c.id = l.character_id WHERE 1 = 1';
    $params = [];
    if ($characterId !== null) {
        $sql .= ' AND l.character_id = ?';
        $params[] = $characterId;
    }
    if ($beforeId > 0) {
        $sql .= ' AND l.id < ?';
        $params[] = $beforeId;
    }
    return q_all($sql . ' ORDER BY l.id DESC LIMIT ' . (int)$limit, $params);
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
           FROM stores s ORDER BY s.name'
    );
}

function store(int $id): ?array
{
    return q_one('SELECT * FROM stores WHERE id = ?', [$id]);
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
          WHERE st.id = ?',
        [$id]
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
            AND (NOT EXISTS (SELECT 1 FROM store_access a WHERE a.store_id = s.id)
                 OR EXISTS (SELECT 1 FROM store_access a WHERE a.store_id = s.id AND a.character_id = ?))
          ORDER BY s.name',
        [$characterId]
    );
}

function pending_cart_count(): int
{
    return (int)q_val("SELECT COUNT(*) FROM carts WHERE status = 'submitted'");
}

/** A cart with its lines, total, character and store. */
function cart_detail(int $cartId): ?array
{
    $cart = q_one(
        'SELECT c.*, ch.name AS character_name, ch.coin_cp, s.name AS store_name
           FROM carts c
           JOIN characters ch ON ch.id = c.character_id
           JOIN stores s ON s.id = c.store_id
          WHERE c.id = ?',
        [$cartId]
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
