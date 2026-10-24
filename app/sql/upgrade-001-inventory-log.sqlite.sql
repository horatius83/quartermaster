-- Adds the pack history table to a local database created before it existed.
-- sqlite3 storage/quartermaster.sqlite < app/sql/upgrade-001-inventory-log.sqlite.sql

CREATE TABLE IF NOT EXISTS inventory_log (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  character_id  INTEGER NULL REFERENCES characters(id) ON DELETE SET NULL,
  character_name TEXT NOT NULL DEFAULT '',
  actor_user_id INTEGER NULL,
  actor_name    TEXT NOT NULL DEFAULT '',
  action        TEXT NOT NULL,
  item_id       INTEGER NULL,
  item_name     TEXT NOT NULL DEFAULT '',
  notes         TEXT NOT NULL DEFAULT '',
  quantity      INTEGER NOT NULL DEFAULT 0,
  coin_cp       INTEGER NOT NULL DEFAULT 0,
  detail        TEXT NOT NULL DEFAULT '',
  created_at    TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS ix_inventory_log_character ON inventory_log (character_id, id);
