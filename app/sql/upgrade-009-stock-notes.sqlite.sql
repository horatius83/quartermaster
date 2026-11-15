-- Gives shelf lines and cart lines notes ("Spell: Magic Missile"), so a store can stock the same item more than once,
-- each with its own notes, and a bought line lands in the pack with them.
-- The app runs this itself. By hand: sqlite3 storage/quartermaster.sqlite < app/sql/upgrade-009-stock-notes.sqlite.sql   (run once)

-- SQLite can't change a UNIQUE, so both tables are rebuilt with the same ids. Nothing refers to their rows, so the
-- old ones can simply go. Dropping a table also forgets the highest id ever used, which is kept so deleted ids
-- aren't reused.
CREATE TEMP TABLE cart_items_before_notes AS SELECT id, cart_id, item_id, quantity, unit_price_cp FROM cart_items;
CREATE TEMP TABLE cart_items_sequence_before_notes AS SELECT seq FROM sqlite_sequence WHERE name = 'cart_items';
DROP TABLE cart_items;
CREATE TABLE cart_items (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  cart_id       INTEGER NOT NULL REFERENCES carts(id) ON DELETE CASCADE,
  item_id       INTEGER NOT NULL REFERENCES items(id),
  notes         TEXT NOT NULL DEFAULT '',
  quantity      INTEGER NOT NULL DEFAULT 1,
  unit_price_cp INTEGER NOT NULL DEFAULT 0,
  UNIQUE (cart_id, item_id, notes)
);
INSERT INTO cart_items (id, cart_id, item_id, quantity, unit_price_cp)
  SELECT id, cart_id, item_id, quantity, unit_price_cp FROM cart_items_before_notes;
DELETE FROM sqlite_sequence WHERE name = 'cart_items';
INSERT INTO sqlite_sequence (name, seq) SELECT 'cart_items', seq FROM cart_items_sequence_before_notes;
DROP TABLE cart_items_before_notes;
DROP TABLE cart_items_sequence_before_notes;

-- The app checks for store_stock.notes to tell whether the upgrade is done, so it comes last.
CREATE TEMP TABLE store_stock_before_notes AS SELECT id, store_id, item_id, price_cp, quantity FROM store_stock;
CREATE TEMP TABLE store_stock_sequence_before_notes AS SELECT seq FROM sqlite_sequence WHERE name = 'store_stock';
DROP TABLE store_stock;
CREATE TABLE store_stock (
  id       INTEGER PRIMARY KEY AUTOINCREMENT,
  store_id INTEGER NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
  item_id  INTEGER NOT NULL REFERENCES items(id),
  notes    TEXT NOT NULL DEFAULT '',
  price_cp INTEGER NOT NULL DEFAULT 0,
  quantity INTEGER NULL,
  UNIQUE (store_id, item_id, notes)
);
INSERT INTO store_stock (id, store_id, item_id, price_cp, quantity)
  SELECT id, store_id, item_id, price_cp, quantity FROM store_stock_before_notes;
DELETE FROM sqlite_sequence WHERE name = 'store_stock';
INSERT INTO sqlite_sequence (name, seq) SELECT 'store_stock', seq FROM store_stock_sequence_before_notes;
DROP TABLE store_stock_before_notes;
DROP TABLE store_stock_sequence_before_notes;
