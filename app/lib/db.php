<?php
declare(strict_types=1);

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $cfg = $GLOBALS['config'];
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    if (($cfg['driver'] ?? 'mysql') === 'sqlite') {
        $path = $cfg['sqlite']['path'];
        $isNew = !is_file($path);
        if ($isNew && !is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        $pdo = new PDO('sqlite:' . $path, null, null, $options);
        $pdo->exec('PRAGMA foreign_keys = ON');
        if ($isNew) {
            $pdo->exec(file_get_contents(APP_DIR . '/sql/schema.sqlite.sql'));
        }
    } else {
        $m = $cfg['mysql'];
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $m['host'], (int)($m['port'] ?? 3306), $m['database']);
        $pdo = new PDO($dsn, $m['username'], $m['password'], $options);
        $pdo->exec("SET time_zone = '+00:00'");
    }
    apply_upgrades($pdo);
    return $pdo;
}

/**
 * Upgrades in app/sql/upgrade-<name>.<driver>.sql, oldest first, each with a query that only
 * succeeds once that upgrade has been applied.
 */
const UPGRADES = [
    '001-inventory-log' => 'SELECT 1 FROM inventory_log LIMIT 1',
    '002-campaigns'     => 'SELECT campaign_id FROM inventory_log LIMIT 1',
];

/**
 * Bring a database created by an older version up to date by running the upgrades it is missing.
 * Up-to-date databases cost one probe query per request.
 */
function apply_upgrades(PDO $pdo): void
{
    if (upgrade_applied($pdo, UPGRADES[array_key_last(UPGRADES)])) {
        return;
    }
    foreach (UPGRADES as $name => $probe) {
        if (upgrade_applied($pdo, $probe)) {
            continue;
        }
        $sql = preg_replace('/^\s*--.*$/m', '', file_get_contents(APP_DIR . "/sql/upgrade-{$name}." . db_driver() . '.sql'));
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            $pdo->exec($statement);
        }
        // The upgrade names the campaign holding the existing data "My Campaign"; use the name config.php used to show.
        if ($name === '002-campaigns' && is_string(config('campaign_name')) && config('campaign_name') !== '') {
            $pdo->prepare('UPDATE campaigns SET name = ? WHERE id = 1')->execute([mb_substr(config('campaign_name'), 0, 100)]);
        }
    }
}

function upgrade_applied(PDO $pdo, string $probe): bool
{
    try {
        $pdo->query($probe);
        return true;
    } catch (PDOException) {
        return false;
    }
}

function db_driver(): string
{
    return $GLOBALS['config']['driver'] ?? 'mysql';
}

/** Row-locking suffix for SELECTs inside a transaction (SQLite locks the whole file instead). */
function for_update(): string
{
    return db_driver() === 'mysql' ? ' FOR UPDATE' : '';
}

/** Run a query and return the statement. */
function q(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function q_all(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

function q_one(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();
    return $row === false ? null : $row;
}

function q_val(string $sql, array $params = []): mixed
{
    $val = q($sql, $params)->fetchColumn();
    return $val === false ? null : $val;
}

function insert(string $sql, array $params = []): int
{
    q($sql, $params);
    return (int)db()->lastInsertId();
}

/** Run $fn inside a transaction; rolls back and rethrows on any exception. */
function transaction(callable $fn): mixed
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $result = $fn();
        $pdo->commit();
        return $result;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function now(): string
{
    return gmdate('Y-m-d H:i:s');
}
