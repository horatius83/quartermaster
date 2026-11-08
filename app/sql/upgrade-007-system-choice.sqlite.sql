-- Lets each campaign that existed before systems say which one it really plays, once (Campaigns page).
-- The default marks every existing campaign open. New campaigns set it to 0 themselves (create_campaign).
-- The app runs this itself. By hand: sqlite3 storage/quartermaster.sqlite < app/sql/upgrade-007-system-choice.sqlite.sql   (run once)

ALTER TABLE campaigns ADD COLUMN ruleset_open INTEGER NOT NULL DEFAULT 1;
