-- Adds the admin login, gives every campaign an owning DM, and adds the account log.
-- Existing campaigns go to the oldest DM login. The admin account itself is made in the app afterwards:
-- sign in as your DM and follow "Create the admin account" in the sidebar.
-- The app runs this itself and finishes it if it was interrupted. Importing it by hand via
-- phpMyAdmin > Import works once; a second import stops at the column that already exists.

SET NAMES utf8mb4;

ALTER TABLE users MODIFY role ENUM('admin','dm','player') NOT NULL;

ALTER TABLE campaigns
  ADD COLUMN owner_user_id INT UNSIGNED NULL AFTER id,
  ADD KEY ix_campaigns_owner (owner_user_id),
  ADD CONSTRAINT fk_campaigns_owner FOREIGN KEY (owner_user_id) REFERENCES users(id);
UPDATE campaigns SET owner_user_id = (SELECT MIN(id) FROM users WHERE role = 'dm') WHERE owner_user_id IS NULL;

-- The app checks for this table to tell whether the upgrade is done, so it comes last.
CREATE TABLE IF NOT EXISTS user_log (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  actor_user_id INT UNSIGNED NULL,
  actor_name    VARCHAR(50)  NOT NULL DEFAULT '',
  action        VARCHAR(20)  NOT NULL,
  user_id       INT UNSIGNED NULL,
  username      VARCHAR(50)  NOT NULL DEFAULT '',
  role          VARCHAR(10)  NOT NULL DEFAULT '',
  detail        VARCHAR(255) NOT NULL DEFAULT '',
  created_at    DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
