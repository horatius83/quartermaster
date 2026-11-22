-- Lets the DM charge each character their own share of a store's prices, and keep shelf lines out of sight of some
-- characters. Everything starts at 100% and seen by everyone, as before. It also lets a store be closed to every
-- character: until now, unticking everyone under Who can shop here left it open to all.
-- The app runs this itself. By hand: sqlite3 storage/quartermaster.sqlite < app/sql/upgrade-012-store-bias.sqlite.sql   (run once)

ALTER TABLE stores ADD COLUMN restricted INTEGER NOT NULL DEFAULT 0;
-- A store with an access list keeps it. Safe to run again.
UPDATE stores SET restricted = 1 WHERE id IN (SELECT store_id FROM store_access);

ALTER TABLE store_stock ADD COLUMN restricted INTEGER NOT NULL DEFAULT 0;
-- The share a cart was sent at. Carts sent before now were at 100%.
ALTER TABLE carts ADD COLUMN price_pct INTEGER NOT NULL DEFAULT 100;

CREATE TABLE IF NOT EXISTS stock_viewers (
  stock_id     INTEGER NOT NULL REFERENCES store_stock(id) ON DELETE CASCADE,
  character_id INTEGER NOT NULL REFERENCES characters(id) ON DELETE CASCADE,
  PRIMARY KEY (stock_id, character_id)
);

-- The app checks for this table to tell whether the upgrade is done, so it comes last.
CREATE TABLE IF NOT EXISTS store_prices (
  store_id     INTEGER NOT NULL REFERENCES stores(id) ON DELETE CASCADE,
  character_id INTEGER NOT NULL REFERENCES characters(id) ON DELETE CASCADE,
  price_pct    INTEGER NOT NULL DEFAULT 100,
  PRIMARY KEY (store_id, character_id)
)
