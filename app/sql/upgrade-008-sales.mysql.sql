-- Adds sales, the offers players make to sell things from their packs, and the DM's answers to them.
-- The app runs this itself. Importing it by hand via phpMyAdmin > Import is safe to repeat.

CREATE TABLE IF NOT EXISTS sales (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id INT UNSIGNED NOT NULL,
  item_id      INT UNSIGNED NOT NULL,
  notes        VARCHAR(255) NOT NULL DEFAULT '',
  quantity     INT NOT NULL DEFAULT 1,
  asking_cp    INT NOT NULL DEFAULT 0,
  offer_cp     INT NULL,
  sold_cp      INT NULL,
  status       ENUM('pending','countered','sold','rejected','declined') NOT NULL DEFAULT 'pending',
  player_note  VARCHAR(500) NOT NULL DEFAULT '',
  dm_note      VARCHAR(500) NOT NULL DEFAULT '',
  created_at   DATETIME NOT NULL,
  countered_at DATETIME NULL,
  decided_at   DATETIME NULL,
  KEY ix_sales_character (character_id, status),
  CONSTRAINT fk_sales_character FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
  CONSTRAINT fk_sales_item FOREIGN KEY (item_id) REFERENCES items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
