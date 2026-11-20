-- Adds requests, the notes players send the DM (asking for an item, a question, a comment), and the DM's replies.
-- The app runs this itself. Importing it by hand via phpMyAdmin > Import is safe to repeat.

CREATE TABLE IF NOT EXISTS requests (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id  INT UNSIGNED NOT NULL,
  body          VARCHAR(2000) NOT NULL,
  status        ENUM('open','closed') NOT NULL DEFAULT 'open',
  dm_reply      VARCHAR(1000) NOT NULL DEFAULT '',
  created_at    DATETIME NOT NULL,
  closed_at     DATETIME NULL,
  reply_seen_at DATETIME NULL,
  KEY ix_requests_character (character_id, status),
  CONSTRAINT fk_requests_character FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
