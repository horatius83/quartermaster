-- Adds sales, the offers players make to sell things from their packs, and the DM's answers to them.
-- The app runs this itself. By hand: sqlite3 storage/quartermaster.sqlite < app/sql/upgrade-008-sales.sqlite.sql   (safe to run again)

CREATE TABLE IF NOT EXISTS sales (
  id           INTEGER PRIMARY KEY AUTOINCREMENT,
  character_id INTEGER NOT NULL REFERENCES characters(id) ON DELETE CASCADE,
  item_id      INTEGER NOT NULL REFERENCES items(id),
  notes        TEXT NOT NULL DEFAULT '',
  quantity     INTEGER NOT NULL DEFAULT 1,
  asking_cp    INTEGER NOT NULL DEFAULT 0,
  offer_cp     INTEGER NULL,
  sold_cp      INTEGER NULL,
  status       TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','countered','sold','rejected','declined')),
  player_note  TEXT NOT NULL DEFAULT '',
  dm_note      TEXT NOT NULL DEFAULT '',
  created_at   TEXT NOT NULL,
  countered_at TEXT NULL,
  decided_at   TEXT NULL
);
CREATE INDEX IF NOT EXISTS ix_sales_character ON sales (character_id, status);
