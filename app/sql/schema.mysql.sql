-- Quartermaster schema for MySQL 8 / MariaDB 10.4+
-- Import this once (e.g. via phpMyAdmin > Import) into an empty database.

SET NAMES utf8mb4;

CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('dm','player') NOT NULL,
  created_at    DATETIME NOT NULL,
  UNIQUE KEY uq_users_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE characters (
  id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id        INT UNSIGNED NULL,
  name           VARCHAR(100) NOT NULL,
  class_label    VARCHAR(100) NOT NULL DEFAULT '',
  coin_cp        INT NOT NULL DEFAULT 0,
  carry_capacity DECIMAL(8,2) NOT NULL DEFAULT 150,
  created_at     DATETIME NOT NULL,
  UNIQUE KEY uq_characters_user (user_id),
  CONSTRAINT fk_characters_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(50) NOT NULL,
  slug       VARCHAR(50) NOT NULL,
  sort_order INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_categories_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE items (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(150) NOT NULL,
  category_id INT UNSIGNED NOT NULL,
  weight      DECIMAL(8,2) NOT NULL DEFAULT 0,
  price_cp    INT NOT NULL DEFAULT 0,
  description TEXT NULL,
  UNIQUE KEY uq_items_name (name),
  CONSTRAINT fk_items_category FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inventory (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id INT UNSIGNED NOT NULL,
  item_id      INT UNSIGNED NOT NULL,
  quantity     INT NOT NULL DEFAULT 1,
  notes        VARCHAR(255) NOT NULL DEFAULT '',
  KEY ix_inventory_character (character_id),
  CONSTRAINT fk_inventory_character FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
  CONSTRAINT fk_inventory_item FOREIGN KEY (item_id) REFERENCES items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stores (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name       VARCHAR(100) NOT NULL,
  kind       VARCHAR(100) NOT NULL DEFAULT '',
  blurb      VARCHAR(1000) NOT NULL DEFAULT '',
  is_open    TINYINT(1) NOT NULL DEFAULT 0,
  created_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- No rows for a store = every character may shop there.
CREATE TABLE store_access (
  store_id     INT UNSIGNED NOT NULL,
  character_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (store_id, character_id),
  CONSTRAINT fk_access_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
  CONSTRAINT fk_access_character FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- quantity NULL = unlimited stock.
CREATE TABLE store_stock (
  id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  store_id INT UNSIGNED NOT NULL,
  item_id  INT UNSIGNED NOT NULL,
  price_cp INT NOT NULL DEFAULT 0,
  quantity INT NULL,
  UNIQUE KEY uq_stock_store_item (store_id, item_id),
  CONSTRAINT fk_stock_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE,
  CONSTRAINT fk_stock_item FOREIGN KEY (item_id) REFERENCES items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE carts (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id INT UNSIGNED NOT NULL,
  store_id     INT UNSIGNED NOT NULL,
  status       ENUM('open','submitted','approved','declined') NOT NULL DEFAULT 'open',
  player_note  VARCHAR(500) NOT NULL DEFAULT '',
  dm_note      VARCHAR(500) NOT NULL DEFAULT '',
  created_at   DATETIME NOT NULL,
  submitted_at DATETIME NULL,
  decided_at   DATETIME NULL,
  KEY ix_carts_status (status),
  KEY ix_carts_character_store (character_id, store_id),
  CONSTRAINT fk_carts_character FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE,
  CONSTRAINT fk_carts_store FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cart_items (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cart_id       INT UNSIGNED NOT NULL,
  item_id       INT UNSIGNED NOT NULL,
  quantity      INT NOT NULL DEFAULT 1,
  unit_price_cp INT NOT NULL DEFAULT 0,
  UNIQUE KEY uq_cart_item (cart_id, item_id),
  CONSTRAINT fk_cart_items_cart FOREIGN KEY (cart_id) REFERENCES carts(id) ON DELETE CASCADE,
  CONSTRAINT fk_cart_items_item FOREIGN KEY (item_id) REFERENCES items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every change to a pack or purse. quantity and coin_cp are signed deltas.
-- item_name and actor_name are copied in so the history survives renames and deleted logins.
CREATE TABLE inventory_log (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  character_id  INT UNSIGNED NOT NULL,
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
  CONSTRAINT fk_inventory_log_character FOREIGN KEY (character_id) REFERENCES characters(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO categories (name, slug, sort_order) VALUES
  ('Weapon', 'weapon', 1),
  ('Armor', 'armor', 2),
  ('Ammunition', 'ammunition', 3),
  ('Consumable', 'consumable', 4),
  ('Adventuring Gear', 'gear', 5),
  ('Tool', 'tool', 6),
  ('Magic Item', 'magic', 7),
  ('Treasure', 'treasure', 8);
