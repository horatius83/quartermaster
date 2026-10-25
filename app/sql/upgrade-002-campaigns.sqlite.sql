-- Adds campaigns to a local database created before they existed. Everything already there moves into one campaign.
-- sqlite3 storage/quartermaster.sqlite < app/sql/upgrade-002-campaigns.sqlite.sql   (run once)
-- SQLite can't add a NOT NULL foreign key column to an existing table, so the new columns stay nullable here.

CREATE TABLE IF NOT EXISTS campaigns (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  name       TEXT NOT NULL,
  created_at TEXT NOT NULL
);

INSERT INTO campaigns (id, name, created_at)
  SELECT 1, 'My Campaign', datetime('now') WHERE NOT EXISTS (SELECT 1 FROM campaigns);

ALTER TABLE characters ADD COLUMN campaign_id INTEGER NULL REFERENCES campaigns(id);
UPDATE characters SET campaign_id = 1;

ALTER TABLE stores ADD COLUMN campaign_id INTEGER NULL REFERENCES campaigns(id);
UPDATE stores SET campaign_id = 1;

ALTER TABLE inventory_log ADD COLUMN campaign_id INTEGER NULL REFERENCES campaigns(id) ON DELETE CASCADE;
UPDATE inventory_log SET campaign_id = 1;
CREATE INDEX IF NOT EXISTS ix_inventory_log_campaign ON inventory_log (campaign_id, id);
