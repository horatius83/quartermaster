-- Adds the pack history table to a database created before it existed.
-- Import once via phpMyAdmin > Import. Safe to run again.

SET NAMES utf8mb4;

-- Every change to a pack or purse. quantity and coin_cp are signed deltas.
-- character_name, item_name and actor_name are copied in so the history survives renames and deletions.
CREATE TABLE IF NOT EXISTS inventory_log (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id  INT UNSIGNED NULL,
  character_name VARCHAR(100) NOT NULL DEFAULT '',
  actor_user_id INT UNSIGNED NULL,
  actor_name    VARCHAR(50)  NOT NULL DEFAULT '',
  action        VARCHAR(20)  NOT NULL,
  item_id       INT UNSIGNED NULL,
  item_name     VARCHAR(150) NOT NULL DEFAULT '',
  notes         VARCHAR(255) NOT NULL DEFAULT '',
  quantity      INT NOT NULL DEFAULT 0,
  coin_cp       INT NOT NULL DEFAULT 0,
  detail        VARCHAR(255) NOT NULL DEFAULT '',
  created_at    DATETIME NOT NULL,
  KEY ix_inventory_log_character (character_id, id),
  CONSTRAINT fk_inventory_log_character FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
