-- =============================================================================
--  Shree Mahaveer Collections — Final Database Schema
--  Database : schoolbags_db
--  Engine   : InnoDB | Charset : utf8mb4_unicode_ci
--  Run this file on a clean MySQL instance to set up the full schema.
-- =============================================================================

CREATE DATABASE IF NOT EXISTS `schoolbags_db`
    DEFAULT CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `schoolbags_db`;

-- -----------------------------------------------------------------------------
-- 1. admins
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `admins` (
    `id`         INT            NOT NULL AUTO_INCREMENT,
    `name`       VARCHAR(100)   NOT NULL,
    `email`      VARCHAR(150)   NOT NULL,
    `password`   VARCHAR(255)   NOT NULL,
    `role`       ENUM('admin','super_admin') NOT NULL DEFAULT 'admin',
    `is_active`  TINYINT(1)     NOT NULL DEFAULT 1,
    `last_login` TIMESTAMP      NULL     DEFAULT NULL,
    `created_at` TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_admins_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 2. users
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id`               INT           NOT NULL AUTO_INCREMENT,
    `user_unique_id`   VARCHAR(20)   NOT NULL,               -- e.g. SMC-USR-0001
    `first_name`       VARCHAR(100)  DEFAULT NULL,
    `last_name`        VARCHAR(100)  DEFAULT NULL,
    `email`            VARCHAR(150)  NOT NULL,
    `phone_number`     VARCHAR(15)   NOT NULL,
    `city`             VARCHAR(100)  DEFAULT NULL,
    `state`            VARCHAR(100)  DEFAULT NULL,
    `country`          VARCHAR(100)  DEFAULT NULL,
    `landmark_address` TEXT          DEFAULT NULL,
    `password`         VARCHAR(255)  NOT NULL,
    `status`           TINYINT(1)    NOT NULL DEFAULT 1,
    `created_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email`        (`email`),
    UNIQUE KEY `uq_users_phone`        (`phone_number`),
    UNIQUE KEY `uq_users_unique_id`    (`user_unique_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 3. categories
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `categories` (
    `id`          INT           NOT NULL AUTO_INCREMENT,
    `category_id` VARCHAR(100)  NOT NULL,                    -- e.g. SMC-CATE-001
    `name`        VARCHAR(150)  NOT NULL,
    `description` TEXT          DEFAULT NULL,
    `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_categories_category_id` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 4. products
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `products` (
    `id`                      INT            NOT NULL AUTO_INCREMENT,
    `product_id`              VARCHAR(100)   NOT NULL,        -- e.g. SMC-PROD-0001

    -- Basic info
    `product_name`            VARCHAR(255)   NOT NULL,
    `generic_name`            VARCHAR(255)   DEFAULT NULL,
    `brand`                   VARCHAR(255)   DEFAULT NULL,
    `category_id`             INT            DEFAULT NULL,    -- FK → categories.id

    -- Colour & appearance
    `color`                   VARCHAR(255)   DEFAULT NULL,    -- comma-separated labels
    `color_hex`               VARCHAR(50)    DEFAULT NULL,    -- primary hex e.g. #000000
    `selected_colors`         TEXT           DEFAULT NULL,    -- JSON: [{"label":"Black","hex":"#000"}]
    `material`                VARCHAR(255)   DEFAULT NULL,
    `pattern`                 VARCHAR(255)   DEFAULT NULL,

    -- Product attributes
    `character_name`          VARCHAR(255)   DEFAULT NULL,
    `gender`                  VARCHAR(50)    DEFAULT NULL,
    `class_type`              VARCHAR(100)   DEFAULT NULL,
    `backpack_style`          VARCHAR(100)   DEFAULT NULL,
    `capacity`                VARCHAR(100)   DEFAULT NULL,
    `net_quantity`            VARCHAR(100)   DEFAULT NULL,
    `recommended_age`         VARCHAR(100)   DEFAULT NULL,
    `size`                    VARCHAR(100)   DEFAULT NULL,
    `country_of_origin`       VARCHAR(255)   DEFAULT NULL,
    `net_weight`              VARCHAR(100)   DEFAULT NULL,

    -- Pricing
    `price`                   DECIMAL(10,2)  NOT NULL DEFAULT 0.00,   -- base / calculated price
    `mrp`                     DECIMAL(10,2)  NOT NULL DEFAULT 0.00,   -- maximum retail price
    `selling_price`           DECIMAL(10,2)  NOT NULL DEFAULT 0.00,   -- actual selling price
    `actual_cost_price`       DECIMAL(10,2)  NOT NULL DEFAULT 0.00,   -- internal cost
    `discount_price`          DECIMAL(10,2)  NOT NULL DEFAULT 0.00,   -- discounted selling price

    -- Inventory
    `stock`                   INT            NOT NULL DEFAULT 0,

    -- Content
    `features`                TEXT           DEFAULT NULL,    -- JSON array of {title, description}
    `short_description`       TEXT           DEFAULT NULL,
    `full_description`        LONGTEXT       DEFAULT NULL,
    `description`             TEXT           DEFAULT NULL,    -- legacy / fallback

    -- Offer / promotion
    `is_on_offer`             TINYINT(1)     NOT NULL DEFAULT 0,
    `is_discounted`           TINYINT(1)     NOT NULL DEFAULT 0,
    `discount_type`           VARCHAR(50)    DEFAULT NULL,    -- 'percentage' | 'flat'
    `offer_title`             VARCHAR(255)   DEFAULT NULL,
    `offer_description`       TEXT           DEFAULT NULL,
    `offer_start_date`        DATE           DEFAULT NULL,
    `offer_end_date`          DATE           DEFAULT NULL,
    `offer_active`            TINYINT(1)     NOT NULL DEFAULT 0,

    -- Homepage / hero banner
    `homepage_banner_enabled` TINYINT(1)     NOT NULL DEFAULT 0,
    `hero_banner_title`       VARCHAR(255)   DEFAULT NULL,
    `hero_banner_subtitle`    VARCHAR(255)   DEFAULT NULL,
    `hero_banner_cta`         VARCHAR(255)   DEFAULT NULL,
    `hero_banner_url`         VARCHAR(500)   DEFAULT NULL,

    -- Visibility flags
    `is_live`                 TINYINT(1)     NOT NULL DEFAULT 1,
    `is_new_arrival`          TINYINT(1)     NOT NULL DEFAULT 0,
    `show_in_card_slider`     TINYINT(1)     NOT NULL DEFAULT 0,
    `is_published`            TINYINT(1)     NOT NULL DEFAULT 1,
    `is_visible_on_website`   TINYINT(1)     NOT NULL DEFAULT 1,

    -- Status
    `status`                  ENUM('active','inactive','draft','published') NOT NULL DEFAULT 'active',

    `created_at`              TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`              TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_products_product_id` (`product_id`),
    KEY `idx_products_category`         (`category_id`),
    KEY `idx_products_status`           (`status`),
    KEY `idx_products_is_live`          (`is_live`),

    CONSTRAINT `fk_products_category`
        FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 5. product_images
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `product_images` (
    `id`          INT           NOT NULL AUTO_INCREMENT,
    `product_id`  INT           NOT NULL,
    `image_url`   VARCHAR(500)  NOT NULL,
    `is_main`     TINYINT(1)    NOT NULL DEFAULT 0,
    `color_label` VARCHAR(100)  DEFAULT NULL,   -- e.g. "Pink"
    `color_hex`   VARCHAR(20)   DEFAULT NULL,   -- e.g. "#ec407a"
    `created_at`  TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_product_images_product` (`product_id`),
    CONSTRAINT `fk_product_images_product`
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 6. orders
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `orders` (
    `id`               INT            NOT NULL AUTO_INCREMENT,
    `order_id`         VARCHAR(100)   NOT NULL,               -- e.g. SMC-ODR-00001
    `user_id`          INT            DEFAULT NULL,           -- NULL = guest checkout
    `customer_name`    VARCHAR(200)   DEFAULT NULL,
    `customer_email`   VARCHAR(200)   DEFAULT NULL,
    `customer_phone`   VARCHAR(50)    NOT NULL,

    -- Financials
    `subtotal`         DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    `tax`              DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    `shipping_cost`    DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    `total_amount`     DECIMAL(10,2)  NOT NULL DEFAULT 0.00,

    -- Payment
    `payment_method`   VARCHAR(50)    NOT NULL DEFAULT 'COD', -- 'COD' | 'ONLINE'
    `payment_status`   VARCHAR(50)    NOT NULL DEFAULT 'pending', -- pending | paid | failed | refunded

    -- Fulfilment
    `order_status`     VARCHAR(50)    NOT NULL DEFAULT 'pending', -- pending | confirmed | shipped | delivered | cancelled

    -- Shipping address
    `shipping_address` TEXT           DEFAULT NULL,
    `city`             VARCHAR(100)   DEFAULT NULL,
    `state`            VARCHAR(100)   DEFAULT NULL,
    `country`          VARCHAR(100)   DEFAULT NULL,
    `pincode`          VARCHAR(20)    DEFAULT NULL,

    -- Status (legacy — kept for backward compat)
    `status`           VARCHAR(50)    NOT NULL DEFAULT 'pending',

    `created_at`       TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`       TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_orders_order_id`     (`order_id`),
    KEY `idx_orders_user`               (`user_id`),
    KEY `idx_orders_payment_status`     (`payment_status`),
    KEY `idx_orders_order_status`       (`order_status`),

    CONSTRAINT `fk_orders_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- 7. order_items
-- -----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `order_items` (
    `id`             INT            NOT NULL AUTO_INCREMENT,
    `order_id`       INT            NOT NULL,
    `product_id`     INT            DEFAULT NULL,
    `product_sku`    VARCHAR(100)   DEFAULT NULL,
    `product_name`   VARCHAR(255)   DEFAULT NULL,
    `quantity`       INT            NOT NULL DEFAULT 1,
    `unit_price`     DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    `total_price`    DECIMAL(10,2)  NOT NULL DEFAULT 0.00,
    `selected_color` VARCHAR(100)   DEFAULT NULL,
    `selected_size`  VARCHAR(100)   DEFAULT NULL,
    `image_url`      VARCHAR(500)   DEFAULT NULL,
    `created_at`     TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_order_items_order`   (`order_id`),
    KEY `idx_order_items_product` (`product_id`),
    CONSTRAINT `fk_order_items_order`
        FOREIGN KEY (`order_id`)   REFERENCES `orders`   (`id`) ON DELETE CASCADE  ON UPDATE CASCADE,
    CONSTRAINT `fk_order_items_product`
        FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================================
--  Seed Data
-- =============================================================================

-- Admin seed (password should be updated to a bcrypt hash in production)
INSERT INTO `admins` (`name`, `email`, `password`, `role`, `is_active`)
VALUES
    ('Super Admin',  'superadmin@shreemahaveercollections.com', 'Admin@123', 'super_admin', 1),
    ('Rajesh Sharma','rajesh@shreemahaveercollections.com',     'Admin@123', 'admin',       1),
    ('Priya Singh',  'priya@shreemahaveercollections.com',      'Admin@123', 'admin',       1),
    ('Amit Patel',   'amit@shreemahaveercollections.com',       'Admin@123', 'admin',       1),
    ('Neha Verma',   'neha@shreemahaveercollections.com',       'Admin@123', 'admin',       1)
ON DUPLICATE KEY UPDATE
    `name`      = VALUES(`name`),
    `role`      = VALUES(`role`),
    `is_active` = VALUES(`is_active`);

-- Category seed
INSERT INTO `categories` (`category_id`, `name`, `description`)
VALUES ('SMC-CATE-001', 'Kids School Bags', 'Durable, comfortable backpacks for primary school kids.')
ON DUPLICATE KEY UPDATE
    `name`        = VALUES(`name`),
    `description` = VALUES(`description`);

-- =============================================================================
--  Useful Queries (reference — not run automatically)
-- =============================================================================

/*
-- Full order detail with items and product names:
SELECT
    o.order_id,
    o.customer_name,
    o.customer_phone,
    o.total_amount,
    o.payment_method,
    o.payment_status,
    o.order_status,
    oi.product_name,
    oi.product_sku,
    oi.quantity,
    oi.unit_price,
    oi.total_price,
    oi.selected_color,
    oi.selected_size
FROM orders o
JOIN order_items oi ON o.id = oi.order_id
WHERE o.order_id = 'SMC-ODR-00001';

-- Products with their main image:
SELECT
    p.product_id,
    p.product_name,
    p.selling_price,
    p.mrp,
    p.stock,
    p.status,
    pi.image_url  AS main_image
FROM products p
LEFT JOIN product_images pi
       ON pi.product_id = p.id
      AND pi.is_main = 1
WHERE p.is_live = 1
  AND p.is_visible_on_website = 1
ORDER BY p.created_at DESC;

-- Product with all colour images:
SELECT
    p.product_id,
    p.product_name,
    pi.image_url,
    pi.color_label,
    pi.color_hex,
    pi.is_main
FROM products p
JOIN product_images pi ON pi.product_id = p.id
WHERE p.product_id = 'SMC-PROD-0001'
ORDER BY pi.is_main DESC, pi.id ASC;
*/



ALTER TABLE `orders`
ADD COLUMN `merchant_id` VARCHAR(100) NULL AFTER `user_id`;

CREATE TABLE IF NOT EXISTS shipments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    
    order_id VARCHAR(50) NOT NULL,
    
    customer_name VARCHAR(255),
    phone VARCHAR(20),
    shipping_address TEXT,
    city VARCHAR(100),
    state VARCHAR(100),
    country VARCHAR(100),
    pincode VARCHAR(20),
    
    tracking_id VARCHAR(100),
    
    shipment_status ENUM('pending', 'shipped', 'in_transit', 'delivered', 'cancelled') DEFAULT 'pending',
    
    shipping_charge DECIMAL(10,2) DEFAULT 0,
    shipping_charge_status ENUM('pending', 'success', 'failed') DEFAULT 'pending',
    
    cod_amount DECIMAL(10,2) DEFAULT 0,
    cod_status ENUM('pending', 'collected', 'failed') DEFAULT 'pending',
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (order_id) REFERENCES orders(order_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


ALTER TABLE orders
ADD COLUMN merchant_id VARCHAR(255) AFTER user_id,
ADD COLUMN customer_email VARCHAR(255) AFTER customer_name,
ADD COLUMN status VARCHAR(50) DEFAULT 'pending' AFTER total_amount,
ADD COLUMN subtotal DECIMAL(10,2) DEFAULT 0 AFTER pincode,
ADD COLUMN tax DECIMAL(10,2) DEFAULT 0 AFTER subtotal,
ADD COLUMN shipping_cost DECIMAL(10,2) DEFAULT 0 AFTER tax;