-- Adds item_tags, the DM's labels on catalog items ("simple", "ranged", "heavy"), which players filter shops by.
-- The app runs this itself. Importing it by hand via phpMyAdmin > Import is safe to repeat.

-- The app lowercases tags before saving. A binary collation then keeps tags apart exactly as the app does,
-- where utf8mb4_unicode_ci would call "café" and "cafe" the same tag and refuse to save both on one item.
CREATE TABLE IF NOT EXISTS item_tags (
  item_id INT UNSIGNED NOT NULL,
  tag     VARCHAR(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  PRIMARY KEY (item_id, tag),
  CONSTRAINT fk_item_tags_item FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
