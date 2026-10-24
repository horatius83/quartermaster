-- SQLite schema for local development only (set 'driver' => 'sqlite' in config.php).
-- Mirrors schema.mysql.sql.

CREATE TABLE users (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  username      TEXT NOT NULL UNIQUE COLLATE NOCASE,
  password_hash TEXT NOT NULL,
  role          TEXT NOT NULL CHECK (role IN ('dm','player')),
  created_at    TEXT NOT NULL
);

CREATE TABLE characters (
  id             INTEGER PRIMARY KEY AUTOINCREMENT,
  user_id        INTEGER NULL UNIQUE REFERENCES users(id) ON DELETE SET NULL,
  name           TEXT NOT NULL,
  class_label    TEXT NOT NULL DEFAULT '',
  coin_cp        INTEGER NOT NULL DEFAULT 0,
  carry_capacity REAL NOT NULL DEFAULT 150,
  created_at     TEXT NOT NULL
);

CREATE TABLE categories (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  name       TEXT NOT NULL UNIQUE,
  slug       TEXT NOT NULL,
  sort_order INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE items (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  name        TEXT NOT NULL UNIQUE COLLATE NOCASE,
  category_id INTEGER NOT NULL REFERENCES categories(id),
  weight      REAL NOT NULL DEFAULT 0,
  price_cp    INTEGER NOT NULL DEFAULT 0,
  description TEXT NULL
);

CREATE TABLE inventory (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  character_id INTEGER NOT NULL REFERENCES characters(id) ON DELETE CASCADE,
  item_id      INTEGER NOT NULL REFERENCES items(id),
  quantity     INTEGER NOT NULL DEFAULT 1,
  notes        TEXT NOT NULL DEFAULT ''
);

CREATE TABLE stores (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  name       TEXT NOT NULL,
  kind       TEXT NOT NULL DEFAULT '',
  blurb      TEXT NOT NULL DEFAULT '',
  is_open    INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL
);

CREATE TABLE store_access (
  store_id     INTEGER NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
  character_id INTEGER NOT NULL REFERENCES characters(id) ON DELETE CASCADE,
  PRIMARY KEY (store_id, character_id)
);

CREATE TABLE store_stock (
  id       INTEGER PRIMARY KEY AUTOINCREMENT,
  store_id INTEGER NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
  item_id  INTEGER NOT NULL REFERENCES items(id),
  price_cp INTEGER NOT NULL DEFAULT 0,
  quantity INTEGER NULL,
  UNIQUE (store_id, item_id)
);

CREATE TABLE carts (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  character_id INTEGER NOT NULL REFERENCES characters(id) ON DELETE CASCADE,
  store_id     INTEGER NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
  status       TEXT NOT NULL DEFAULT 'open' CHECK (status IN ('open','submitted','approved','declined')),
  player_note  TEXT NOT NULL DEFAULT '',
  dm_note      TEXT NOT NULL DEFAULT '',
  created_at   TEXT NOT NULL,
  submitted_at TEXT NULL,
  decided_at   TEXT NULL
);

CREATE TABLE cart_items (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  cart_id       INTEGER NOT NULL REFERENCES carts(id) ON DELETE CASCADE,
  item_id       INTEGER NOT NULL REFERENCES items(id),
  quantity      INTEGER NOT NULL DEFAULT 1,
  unit_price_cp INTEGER NOT NULL DEFAULT 0,
  UNIQUE (cart_id, item_id)
);

-- Every change to a pack or purse. quantity and coin_cp are signed deltas.
-- character_name, item_name and actor_name are copied in so the history survives renames and deletions.
CREATE TABLE inventory_log (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  character_id  INTEGER NULL REFERENCES characters(id) ON DELETE SET NULL,
  character_name TEXT NOT NULL DEFAULT '',
  actor_user_id INTEGER NULL,
  actor_name    TEXT NOT NULL DEFAULT '',
  action        TEXT NOT NULL,
  item_id       INTEGER NULL,
  item_name     TEXT NOT NULL DEFAULT '',
  notes         TEXT NOT NULL DEFAULT '',
  quantity      INTEGER NOT NULL DEFAULT 0,
  coin_cp       INTEGER NOT NULL DEFAULT 0,
  detail        TEXT NOT NULL DEFAULT '',
  created_at    TEXT NOT NULL
);
CREATE INDEX ix_inventory_log_character ON inventory_log (character_id, id);

INSERT INTO categories (name, slug, sort_order) VALUES
  ('Weapon', 'weapon', 1),
  ('Armor', 'armor', 2),
  ('Ammunition', 'ammunition', 3),
  ('Consumable', 'consumable', 4),
  ('Adventuring Gear', 'gear', 5),
  ('Tool', 'tool', 6),
  ('Magic Item', 'magic', 7),
  ('Treasure', 'treasure', 8);
