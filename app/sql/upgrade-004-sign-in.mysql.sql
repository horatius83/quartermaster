-- Adds users.session_version, which goes up with every new password so sessions signed in before it are
-- signed out (everyone signs in again once after this upgrade), and login_attempts, which limits wrong passwords.
-- The app runs this itself and finishes it if it was interrupted. Importing it by hand via
-- phpMyAdmin > Import works once; a second import stops because the column already exists.

ALTER TABLE users ADD COLUMN session_version INT UNSIGNED NOT NULL DEFAULT 0 AFTER role;

-- The app checks for this table to tell whether the upgrade is done, so it comes last.
CREATE TABLE IF NOT EXISTS login_attempts (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username   VARCHAR(50) NOT NULL,
  ip         VARCHAR(45) NOT NULL,
  created_at DATETIME NOT NULL,
  KEY ix_login_attempts_created (created_at),
  KEY ix_login_attempts_ip (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
