-- 1. Products (Master)
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id VARCHAR(50) UNIQUE NOT NULL,
    product_name VARCHAR(255) NOT NULL,
    generic_name VARCHAR(255),
    brand VARCHAR(100),
    category_id INT,
    material VARCHAR(100),
    pattern VARCHAR(100),
    character_name VARCHAR(100),
    gender VARCHAR(50),
    class_type VARCHAR(100),
    backpack_style VARCHAR(100),
    capacity VARCHAR(50),
    net_quantity VARCHAR(50),
    recommended_age VARCHAR(50),
    country_of_origin VARCHAR(100),
    net_weight DECIMAL(8,2),
    gst DECIMAL(5,2) DEFAULT 18.00,
    features JSON,
    description TEXT,
    short_description TEXT,
    full_description TEXT,
    homepage_banner_enabled TINYINT DEFAULT 0,
    hero_banner_title VARCHAR(255),
    hero_banner_subtitle VARCHAR(255),
    hero_banner_cta VARCHAR(100),
    hero_banner_url VARCHAR(255),
    is_on_offer TINYINT DEFAULT 0,
    is_discounted TINYINT DEFAULT 0,
    discount_type VARCHAR(50),
    offer_title VARCHAR(255),
    offer_description TEXT,
    offer_start_date DATE,
    offer_end_date DATE,
    offer_active TINYINT DEFAULT 0,
    is_live TINYINT DEFAULT 0,
    is_new_arrival TINYINT DEFAULT 0,
    show_in_card_slider TINYINT DEFAULT 0,
    is_published TINYINT DEFAULT 0,
    is_visible_on_website TINYINT DEFAULT 1,
    status ENUM('draft','published','archived') DEFAULT 'draft',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 2. Product Variants
CREATE TABLE product_variants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    variant_id VARCHAR(50) UNIQUE NOT NULL,
    product_id VARCHAR(50) NOT NULL,
    sku VARCHAR(100) UNIQUE,
    color_name VARCHAR(100),
    color_hex VARCHAR(20),
    size VARCHAR(50),
    mrp DECIMAL(10,2),
    selling_price DECIMAL(10,2),
    actual_cost_price DECIMAL(10,2),
    discount_price DECIMAL(10,2),
    stock INT DEFAULT 0,
    status TINYINT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE
);

-- 3. Product Images
CREATE TABLE product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id VARCHAR(50) NOT NULL,
    variant_id VARCHAR(50),
    image_url VARCHAR(255) NOT NULL,
    is_main TINYINT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    FOREIGN KEY (variant_id) REFERENCES product_variants(variant_id) ON DELETE CASCADE
);

-- Indexes
CREATE INDEX idx_variants_product ON product_variants(product_id);
CREATE INDEX idx_images_variant ON product_images(variant_id);




CREATE TABLE IF NOT EXISTS order_actions (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    order_id        INT NOT NULL,
    action_type     ENUM('approved', 'rejected', 'status_changed') NOT NULL,
    action_by       INT NOT NULL,           -- Admin ID
    reason          TEXT NULL,
    old_status      VARCHAR(50) NULL,
    new_status      VARCHAR(50) NOT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
);




ALTER TABLE orders 
ADD COLUMN last_action_at DATETIME NULL AFTER order_status; 