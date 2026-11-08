-- Lets the catalog hold each edition's own version of an item, and gives every campaign an edition.
-- Existing items and campaigns count as 5e.
-- The app runs this itself. By hand: sqlite3 storage/quartermaster.sqlite < app/sql/upgrade-006-rulesets.sqlite.sql   (run once)

-- SQLite can't drop the UNIQUE on items.name, so the table is rebuilt with the same ids. The app runs upgrades in a
-- transaction with foreign keys on, and can't turn them off there. Dropping the table deletes its rows first, so
-- deferring the checks lets packs, stock and carts wait for the rows to come back, and the tags, which would
-- cascade away, are set aside and put back. (By hand, the sqlite3 tool has foreign keys off and the tags stay, hence
-- OR IGNORE.) Dropping the table also forgets the highest id ever used, which is kept so deleted ids aren't reused.
PRAGMA defer_foreign_keys = ON;
CREATE TEMP TABLE items_before_rulesets AS SELECT id, name, category_id, weight, price_cp, description FROM items;
CREATE TEMP TABLE item_tags_before_rulesets AS SELECT item_id, tag FROM item_tags;
CREATE TEMP TABLE items_sequence_before_rulesets AS SELECT seq FROM sqlite_sequence WHERE name = 'items';
DROP TABLE items;
CREATE TABLE items (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  ruleset     TEXT NOT NULL DEFAULT '5e',
  name        TEXT NOT NULL COLLATE NOCASE,
  category_id INTEGER NOT NULL REFERENCES categories(id),
  weight      REAL NOT NULL DEFAULT 0,
  price_cp    INTEGER NOT NULL DEFAULT 0,
  description TEXT NULL,
  UNIQUE (ruleset, name)
);
INSERT INTO items (id, name, category_id, weight, price_cp, description)
  SELECT id, name, category_id, weight, price_cp, description FROM items_before_rulesets;
INSERT OR IGNORE INTO item_tags (item_id, tag) SELECT item_id, tag FROM item_tags_before_rulesets;
DELETE FROM sqlite_sequence WHERE name = 'items';
INSERT INTO sqlite_sequence (name, seq) SELECT 'items', seq FROM items_sequence_before_rulesets;
DROP TABLE items_before_rulesets;
DROP TABLE item_tags_before_rulesets;
DROP TABLE items_sequence_before_rulesets;

-- The app checks for this column to tell whether the upgrade is done, so it comes last.
ALTER TABLE campaigns ADD COLUMN ruleset TEXT NOT NULL DEFAULT '5e';
