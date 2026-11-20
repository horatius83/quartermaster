-- Adds requests, the notes players send the DM (asking for an item, a question, a comment), and the DM's replies.
-- The app runs this itself. By hand: sqlite3 storage/quartermaster.sqlite < app/sql/upgrade-010-requests.sqlite.sql   (safe to run again)

CREATE TABLE IF NOT EXISTS requests (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  character_id  INTEGER NOT NULL REFERENCES characters(id) ON DELETE CASCADE,
  body          TEXT NOT NULL,
  status        TEXT NOT NULL DEFAULT 'open' CHECK (status IN ('open','closed')),
  dm_reply      TEXT NOT NULL DEFAULT '',
  created_at    TEXT NOT NULL,
  closed_at     TEXT NULL,
  reply_seen_at TEXT NULL
);
CREATE INDEX IF NOT EXISTS ix_requests_character ON requests (character_id, status);
