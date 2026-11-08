-- Lets each campaign that existed before systems say which one it really plays, once (Campaigns page).
-- One statement on purpose: the default marks every existing campaign open as the column appears, so stopping partway
-- can't leave them locked. New campaigns set it to 0 themselves (create_campaign).
-- The app runs this itself. Importing it by hand via phpMyAdmin > Import works once.

ALTER TABLE campaigns ADD COLUMN ruleset_open TINYINT(1) NOT NULL DEFAULT 1 AFTER ruleset;
