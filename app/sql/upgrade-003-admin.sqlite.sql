-- Adds the admin login, gives every campaign an owning DM, and adds the account log, on a local database
-- created before them. Existing campaigns go to the oldest DM login.
-- The app runs this itself. By hand: sqlite3 storage/quartermaster.sqlite < app/sql/upgrade-003-admin.sqlite.sql   (run once)

-- SQLite can't alter a CHECK constraint, so widen it in the stored schema instead. This is safe because
-- every existing row already passes the new check (see "Making Other Kinds Of Table Schema Changes" in
-- the SQLite ALTER TABLE docs). RESET makes this connection reread the schema.
PRAGMA writable_schema = ON;
UPDATE sqlite_master SET sql = replace(sql, 'CHECK (role IN (''dm'',''player''))', 'CHECK (role IN (''admin'',''dm'',''player''))')
 WHERE type = 'table' AND name = 'users';
PRAGMA writable_schema = RESET;
-- If the users table was written differently, the replace above changed nothing. Then this admin row breaks
-- the old check, and the upgrade stops with that error instead of counting as done.
INSERT INTO users (username, password_hash, role, created_at) VALUES ('~upgrade-003-check~', '', 'admin', '');
DELETE FROM users WHERE username = '~upgrade-003-check~';

ALTER TABLE campaigns ADD COLUMN owner_user_id INTEGER NULL REFERENCES users(id);
UPDATE campaigns SET owner_user_id = (SELECT MIN(id) FROM users WHERE role = 'dm') WHERE owner_user_id IS NULL;

CREATE TABLE IF NOT EXISTS user_log (
  id            INTEGER PRIMARY KEY AUTOINCREMENT,
  actor_user_id INTEGER NULL,
  actor_name    TEXT NOT NULL DEFAULT '',
  action        TEXT NOT NULL,
  user_id       INTEGER NULL,
  username      TEXT NOT NULL DEFAULT '',
  role          TEXT NOT NULL DEFAULT '',
  detail        TEXT NOT NULL DEFAULT '',
  created_at    TEXT NOT NULL
);
