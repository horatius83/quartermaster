-- Adds the party purse: a campaign can pool its party's coin in one purse, and keep a stash of items any of them can use.
-- The app runs this itself and finishes it if it was interrupted. Importing it by hand via
-- phpMyAdmin > Import works once; a second import stops at the column that already exists.

ALTER TABLE campaigns
  ADD COLUMN shared_purse TINYINT(1) NOT NULL DEFAULT 0 AFTER ruleset_open,
  ADD COLUMN purse_cp INT NOT NULL DEFAULT 0 AFTER shared_purse;

ALTER TABLE inventory_log ADD COLUMN party TINYINT(1) NOT NULL DEFAULT 0 AFTER character_name;

-- The app checks for this table to tell whether the upgrade is done, so it comes last.
CREATE TABLE IF NOT EXISTS party_items (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  campaign_id INT UNSIGNED NOT NULL,
  item_id     INT UNSIGNED NOT NULL,
  quantity    INT NOT NULL DEFAULT 1,
  notes       VARCHAR(255) NOT NULL DEFAULT '',
  KEY ix_party_items_campaign (campaign_id),
  CONSTRAINT fk_party_items_campaign FOREIGN KEY (campaign_id) REFERENCES campaigns(id) ON DELETE CASCADE,
  CONSTRAINT fk_party_items_item FOREIGN KEY (item_id) REFERENCES items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
