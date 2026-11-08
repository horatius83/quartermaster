-- Lets the catalog hold each edition's own version of an item, and gives every campaign an edition.
-- Existing items and campaigns count as 5e; change them in the app afterwards if they're not.
-- The app runs this itself and finishes it if it was interrupted. Importing it by hand via
-- phpMyAdmin > Import works once; a second import stops at the column that already exists.

ALTER TABLE items ADD COLUMN ruleset VARCHAR(10) NOT NULL DEFAULT '5e' AFTER id;
-- The new key goes in first, so names are never left unprotected if this stops partway.
ALTER TABLE items ADD UNIQUE KEY uq_items_ruleset_name (ruleset, name);
ALTER TABLE items DROP INDEX uq_items_name;

-- The app checks for this column to tell whether the upgrade is done, so it comes last.
ALTER TABLE campaigns ADD COLUMN ruleset VARCHAR(10) NOT NULL DEFAULT '5e' AFTER name;
