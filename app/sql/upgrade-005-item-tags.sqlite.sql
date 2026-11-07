-- Adds item_tags, the DM's labels on catalog items ("simple", "ranged", "heavy"), which players filter shops by.
-- The app runs this itself. By hand: sqlite3 storage/quartermaster.sqlite < app/sql/upgrade-005-item-tags.sqlite.sql   (safe to run again)

CREATE TABLE IF NOT EXISTS item_tags (
  item_id INTEGER NOT NULL REFERENCES items(id) ON DELETE CASCADE,
  tag     TEXT NOT NULL,
  PRIMARY KEY (item_id, tag)
);
