-- Lets the DM change a cart line's price for that cart alone, after haggling: list_price_cp keeps the shelf price the
-- line was added at, and is NULL while the line costs that.
-- The app runs this itself. Importing it by hand via phpMyAdmin > Import works once; a second import stops at the
-- column that already exists.

ALTER TABLE cart_items ADD COLUMN list_price_cp INT NULL AFTER unit_price_cp;
