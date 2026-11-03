-- Adds users.session_version, which goes up with every new password so sessions signed in before it are
-- signed out (everyone signs in again once after this upgrade), and login_attempts, which limits wrong passwords.
-- The app runs this itself. By hand: sqlite3 storage/quartermaster.sqlite < app/sql/upgrade-004-sign-in.sqlite.sql   (run once)

ALTER TABLE users ADD COLUMN session_version INTEGER NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS login_attempts (
  id         INTEGER PRIMARY KEY AUTOINCREMENT,
  username   TEXT NOT NULL COLLATE NOCASE,
  ip         TEXT NOT NULL,
  created_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS ix_login_attempts_created ON login_attempts (created_at);
CREATE INDEX IF NOT EXISTS ix_login_attempts_ip ON login_attempts (ip, created_at);
