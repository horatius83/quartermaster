-- Lets the DM charge each character their own share of a store's prices, and keep shelf lines out of sight of some
-- characters. Everything starts at 100% and seen by everyone, as before. It also lets a store be closed to every
-- character: until now, unticking everyone under Who can shop here left it open to all.
-- The app runs this itself and finishes it if it was interrupted. Importing it by hand via
-- phpMyAdmin > Import works once; a second import stops at the column that already exists.

ALTER TABLE stores ADD COLUMN restricted TINYINT(1) NOT NULL DEFAULT 0 AFTER is_open;
-- A store with an access list keeps it. Safe to run again.
UPDATE stores SET restricted = 1 WHERE id IN (SELECT store_id FROM store_access);

ALTER TABLE store_stock ADD COLUMN restricted TINYINT(1) NOT NULL DEFAULT 0 AFTER quantity;
-- The share a cart was sent at. Carts sent before now were at 100%.
ALTER TABLE carts ADD COLUMN price_pct INT NOT NULL DEFAULT 100 AFTER decided_at;

CREATE TABLE IF NOT EXISTS stock_viewers (
  stock_id     INT UNSIGNED NOT NULL,
  character_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (stock_id, character_id),
  CONSTRAINT fk_viewer_stock FOREIGN KEY (stock_id) REFERENCES store_stock(id) ON DELETE CASCADE,
  CONSTRAINT fk_viewer_character FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- The app checks for this table to tell whether the upgrade is done, so it comes last.
CREATE TABLE IF NOT EXISTS store_prices (
  store_id     INT UNSIGNED NOT NULL,
  character_id INT UNSIGNED NOT NULL,
  price_pct    INT NOT NULL DEFAULT 100,
  PRIMARY KEY (store_id, character_id),
  CONSTRAINT fk_price_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
  CONSTRAINT fk_price_character FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
