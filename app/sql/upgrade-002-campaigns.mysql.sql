-- Adds campaigns to a database created before they existed. Everything already in the database
-- (party, stores, history) moves into one campaign, which you can rename under Campaigns.
-- Import once via phpMyAdmin > Import. Unlike upgrade-001, this one is NOT safe to run twice.

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS campaigns (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(100) NOT NULL,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO campaigns (id, name, created_at)
  SELECT 1, 'My Campaign', UTC_TIMESTAMP() FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM campaigns);

ALTER TABLE characters
  ADD COLUMN campaign_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id,
  ADD KEY ix_characters_campaign (campaign_id),
  ADD CONSTRAINT fk_characters_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id);
ALTER TABLE characters ALTER COLUMN campaign_id DROP DEFAULT;

ALTER TABLE stores
  ADD COLUMN campaign_id INT UNSIGNED NOT NULL DEFAULT 1 AFTER id,
  ADD KEY ix_stores_campaign (campaign_id),
  ADD CONSTRAINT fk_stores_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id);
ALTER TABLE stores ALTER COLUMN campaign_id DROP DEFAULT;

ALTER TABLE inventory_log ADD COLUMN campaign_id INT UNSIGNED NULL AFTER id;
UPDATE inventory_log SET campaign_id = 1;
ALTER TABLE inventory_log
  ADD KEY ix_inventory_log_campaign (campaign_id, id),
  ADD CONSTRAINT fk_inventory_log_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE;
