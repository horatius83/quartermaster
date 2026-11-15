-- Gives shelf lines and cart lines notes ("Spell: Magic Missile"), so a store can stock the same item more than once,
-- each with its own notes, and a bought line lands in the pack with them.
-- The app runs this itself and finishes it if it was interrupted. Importing it by hand via
-- phpMyAdmin > Import works once; a second import stops at the column that already exists.

-- Notes match exactly, as in SQLite: "Shield" and "shield" are two lines. 191 four-byte characters keep the unique
-- key within the 767 bytes older MySQL allows an indexed column.
-- Each table changes in one statement, so it's never left with the column but the old key.
ALTER TABLE cart_items
  ADD COLUMN notes VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT '' AFTER item_id,
  ADD UNIQUE KEY uq_cart_item_notes (cart_id, item_id, notes),
  DROP INDEX uq_cart_item;

-- The app checks for this column to tell whether the upgrade is done, so it comes last.
ALTER TABLE store_stock
  ADD COLUMN notes VARCHAR(191) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL DEFAULT '' AFTER item_id,
  ADD UNIQUE KEY uq_stock_store_item_notes (store_id, item_id, notes),
  DROP INDEX uq_stock_store_item;
