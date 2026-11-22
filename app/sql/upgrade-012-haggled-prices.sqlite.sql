-- Lets the DM change a cart line's price for that cart alone, after haggling: list_price_cp keeps the shelf price the
-- line was added at, and is NULL while the line costs that.
-- The app runs this itself. By hand: sqlite3 storage/quartermaster.sqlite < app/sql/upgrade-012-haggled-prices.sqlite.sql   (run once)

ALTER TABLE cart_items ADD COLUMN list_price_cp INTEGER NULL
