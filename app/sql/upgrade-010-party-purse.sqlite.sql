-- Adds the party purse: a campaign can pool its party's coin in one purse, and keep a stash of items any of them can use.
-- The app runs this itself. By hand: sqlite3 storage/quartermaster.sqlite < app/sql/upgrade-010-party-purse.sqlite.sql   (run once)

ALTER TABLE campaigns ADD COLUMN shared_purse INTEGER NOT NULL DEFAULT 0;
ALTER TABLE campaigns ADD COLUMN purse_cp INTEGER NOT NULL DEFAULT 0;
ALTER TABLE inventory_log ADD COLUMN party INTEGER NOT NULL DEFAULT 0;

-- The app checks for this table to tell whether the upgrade is done, so it comes last.
CREATE TABLE IF NOT EXISTS party_items (
  id          INTEGER PRIMARY KEY AUTOINCREMENT,
  campaign_id INTEGER NOT NULL REFERENCES campaigns(id) ON DELETE CASCADE,
  item_id     INTEGER NOT NULL REFERENCES items(id),
  quantity    INTEGER NOT NULL DEFAULT 1,
  notes       TEXT NOT NULL DEFAULT ''
);
CREATE INDEX IF NOT EXISTS ix_party_items_campaign ON party_items (campaign_id)
