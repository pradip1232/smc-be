<?php

// $servername = "localhost";
// $username = "u111746926_shreemahaveer";
// $password = "Khatushyam@#23";
// $dbname = "u111746926_smc_school_bag";
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "smc";

// IMPORTANT: Only one DB is used by this app: smc.
// We still need to ensure the DB exists before connecting.
try {
    $conn = new PDO("mysql:host=$servername", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $conn->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    //   echo "✅ Database '$dbname' is ready.<br>";
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}

$conn = null;

try {
    $pdo = new PDO("mysql:host=$servername;dbname={$dbname};charset=utf8mb4", $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    initializeSchema($pdo);
    seedDemoData($pdo);
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

function initializeSchema(PDO $pdo) {
    $pdo->exec("CREATE TABLE IF NOT EXISTS admins (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        email VARCHAR(150) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        role ENUM('admin','super_admin') DEFAULT 'admin',
        is_active BOOLEAN DEFAULT TRUE,
        last_login TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL,
        email VARCHAR(200) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        category_id VARCHAR(100) UNIQUE NOT NULL,
        name VARCHAR(150) NOT NULL,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id VARCHAR(100) UNIQUE NOT NULL,
        product_name VARCHAR(255) NOT NULL,
        generic_name VARCHAR(150),
        brand VARCHAR(100),
        category_id INT,
        color VARCHAR(50),
        material VARCHAR(100),
        pattern VARCHAR(100),
        character_name VARCHAR(100),
        gender VARCHAR(50),
        class_type VARCHAR(100),
        backpack_style VARCHAR(100),
        capacity VARCHAR(50),
        net_quantity INT,
        recommended_age VARCHAR(50),
        size VARCHAR(50),
        country_of_origin VARCHAR(100),
        net_weight VARCHAR(50),
        price DECIMAL(10,2) NOT NULL,
        discount_price DECIMAL(10,2),
        stock INT DEFAULT 0,
        description TEXT,
        status ENUM('draft','published') DEFAULT 'draft',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS product_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        image_url VARCHAR(255) NOT NULL,
        is_main BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Orders and order items for storing placed orders (including COD)
    $pdo->exec("CREATE TABLE IF NOT EXISTS orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id VARCHAR(100) UNIQUE NOT NULL,
        user_id INT NULL,
        customer_name VARCHAR(200) NOT NULL,
        customer_email VARCHAR(200) DEFAULT NULL,
        customer_phone VARCHAR(50) NOT NULL,
        shipping_address TEXT,
        payment_method VARCHAR(50) NOT NULL,
        total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        status ENUM('pending','confirmed','shipped','delivered','cancelled') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $pdo->exec("CREATE TABLE IF NOT EXISTS order_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        order_id INT NOT NULL,
        product_id INT NULL,
        product_sku VARCHAR(100) DEFAULT NULL,
        product_name VARCHAR(255) DEFAULT NULL,
        quantity INT DEFAULT 1,
        unit_price DECIMAL(10,2) NOT NULL,
        total_price DECIMAL(10,2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // Ensure payment/order metadata columns exist for online payments (PhonePe integration)
    $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS payment_status VARCHAR(50) DEFAULT 'pending'");
    $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS order_status VARCHAR(50) DEFAULT 'pending'");
    $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP");
    $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS city VARCHAR(100) DEFAULT NULL");
    $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS state VARCHAR(100) DEFAULT NULL");
    $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS country VARCHAR(100) DEFAULT NULL");
    $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS pincode VARCHAR(20) DEFAULT NULL");
    $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS subtotal DECIMAL(10,2) DEFAULT 0.00");
    $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS tax DECIMAL(10,2) DEFAULT 0.00");
    $pdo->exec("ALTER TABLE orders ADD COLUMN IF NOT EXISTS shipping_cost DECIMAL(10,2) DEFAULT 0.00");
    
    // Add item-level metadata columns if missing
    $pdo->exec("ALTER TABLE order_items ADD COLUMN IF NOT EXISTS selected_color VARCHAR(100) DEFAULT NULL");
    $pdo->exec("ALTER TABLE order_items ADD COLUMN IF NOT EXISTS selected_size VARCHAR(100) DEFAULT NULL");
    $pdo->exec("ALTER TABLE order_items ADD COLUMN IF NOT EXISTS image_url VARCHAR(255) DEFAULT NULL");

    // Add product_images color columns if missing
    $pdo->exec("ALTER TABLE product_images ADD COLUMN IF NOT EXISTS color_label VARCHAR(100) DEFAULT NULL");
    $pdo->exec("ALTER TABLE product_images ADD COLUMN IF NOT EXISTS color_hex VARCHAR(20) DEFAULT NULL");

    // Add products columns that may not exist in older schema versions
    $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS mrp DECIMAL(10,2) DEFAULT 0.00");
    $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS selling_price DECIMAL(10,2) DEFAULT 0.00");
    $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS actual_cost_price DECIMAL(10,2) DEFAULT 0.00");
    $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS features TEXT DEFAULT NULL");
    $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS is_on_offer TINYINT(1) DEFAULT 0");
    $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS is_discounted TINYINT(1) DEFAULT 0");
    $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS discount_type VARCHAR(50) DEFAULT NULL");
    $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS offer_title VARCHAR(255) DEFAULT NULL");
    $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS offer_description TEXT DEFAULT NULL");
    $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS offer_start_date DATE DEFAULT NULL");
    $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS offer_end_date DATE DEFAULT NULL");
    $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS offer_active TINYINT(1) DEFAULT 0");
    $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS short_description TEXT DEFAULT NULL");
    $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS full_description LONGTEXT DEFAULT NULL");
    $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS homepage_banner_enabled TINYINT(1) DEFAULT 0");
    $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS hero_banner_title VARCHAR(255) DEFAULT NULL");
    $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS hero_banner_subtitle VARCHAR(255) DEFAULT NULL");
    $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS hero_banner_cta VARCHAR(255) DEFAULT NULL");
    $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS hero_banner_url VARCHAR(500) DEFAULT NULL");
    $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS is_live TINYINT(1) DEFAULT 1");
    $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS is_new_arrival TINYINT(1) DEFAULT 0");
    $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS show_in_card_slider TINYINT(1) DEFAULT 0");
    $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS is_published TINYINT(1) DEFAULT 1");
    $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS is_visible_on_website TINYINT(1) DEFAULT 1");
    $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS selected_colors TEXT DEFAULT NULL");
    $pdo->exec("ALTER TABLE products ADD COLUMN IF NOT EXISTS color_hex VARCHAR(50) DEFAULT NULL");
}

function seedDemoData(PDO $pdo) {
    // Use deterministic demo credentials.
    // NOTE: Admin login code should be compatible with password_hash.
    $demoAdmin = [
        'name' => 'SMC Admin',
        'email' => 'admin@schoolbags.local',
        // PHP password_hash for plain text "demo123" at runtime is fine because we re-seed idempotently with email uniqueness.
        'password_plain' => 'demo123',
        'role' => 'admin'
    ];
    $demoPasswordHash = password_hash($demoAdmin['password_plain'], PASSWORD_BCRYPT);

    $stmt = $pdo->prepare('INSERT INTO admins (name, email, password, role, is_active) VALUES (?, ?, ?, ?, 1)'
        . ' ON DUPLICATE KEY UPDATE name=VALUES(name), password=VALUES(password), role=VALUES(role), is_active=VALUES(is_active)');
    $stmt->execute([$demoAdmin['name'], $demoAdmin['email'], $demoPasswordHash, $demoAdmin['role']]);

    // Seed 1 category
    $category = [
        'category_id' => 'SMC-CATE-001',
        'name' => 'Kids School Bags',
        'description' => 'Durable, comfortable backpacks for primary school kids.'
    ];
    $catStmt = $pdo->prepare('INSERT INTO categories (category_id, name, description) VALUES (?, ?, ?)'
        . ' ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description)');
    $catStmt->execute([$category['category_id'], $category['name'], $category['description']]);

    $categoryIdStmt = $pdo->prepare('SELECT id FROM categories WHERE category_id = ? LIMIT 1');
    $categoryIdStmt->execute([$category['category_id']]);
    $categoryRow = $categoryIdStmt->fetch(PDO::FETCH_ASSOC);
    $categoryDbId = (int)($categoryRow['id'] ?? 0);

    if ($categoryDbId <= 0) return;

    // Seed 3 products (published) - realistic values aligned to columns used by admin/product pages.
    $products = [
        [
            'product_id' => 'SMC-PROD-001',
            'product_name' => 'Galaxy Star Kids Backpack',
            'generic_name' => 'School Backpack',
            'brand' => 'SMC',
            'color' => 'Navy Blue',
            'material' => 'Polyester',
            'pattern' => 'Star',
            'character_name' => 'Galaxy',
            'gender' => 'Unisex',
            'class_type' => '1-4',
            'backpack_style' => 'Regular',
            'capacity' => '18L',
            'net_quantity' => 1,
            'recommended_age' => '6-10',
            'size' => '30 x 12 x 40 cm',
            'country_of_origin' => 'India',
            'net_weight' => '0.45 kg',
            'price' => 999.00,
            'discount_price' => 799.00,
            'stock' => 25,
            'description' => 'Lightweight kids backpack with spacious compartments and padded back support.',
            'status' => 'published'
        ],
        [
            'product_id' => 'SMC-PROD-002',
            'product_name' => 'Dino Adventure Lunch & Backpack Set',
            'generic_name' => 'School Backpack',
            'brand' => 'SMC',
            'color' => 'Green',
            'material' => 'Polyester',
            'pattern' => 'Dinosaur',
            'character_name' => 'Dino',
            'gender' => 'Boys',
            'class_type' => '1-4',
            'backpack_style' => 'Combo',
            'capacity' => '20L',
            'net_quantity' => 1,
            'recommended_age' => '6-10',
            'size' => '32 x 13 x 42 cm',
            'country_of_origin' => 'India',
            'net_weight' => '0.55 kg',
            'price' => 1099.00,
            'discount_price' => 899.00,
            'stock' => 18,
            'description' => 'Dino themed backpack with comfy straps and a handy front organizer.',
            'status' => 'published'
        ],
        [
            'product_id' => 'SMC-PROD-003',
            'product_name' => 'Princess Bloom Kids Backpack',
            'generic_name' => 'School Backpack',
            'brand' => 'SMC',
            'color' => 'Pink',
            'material' => 'Polyester',
            'pattern' => 'Floral',
            'character_name' => 'Princess',
            'gender' => 'Girls',
            'class_type' => '1-4',
            'backpack_style' => 'Regular',
            'capacity' => '19L',
            'net_quantity' => 1,
            'recommended_age' => '6-10',
            'size' => '31 x 12 x 41 cm',
            'country_of_origin' => 'India',
            'net_weight' => '0.48 kg',
            'price' => 1049.00,
            'discount_price' => 849.00,
            'stock' => 22,
            'description' => 'Soft pastel princess-themed backpack with durable zippers and reflective trims.',
            'status' => 'published'
        ],
    ];

    foreach ($products as $p) {
        $cols = [
            'product_id','product_name','generic_name','brand','category_id','color','material','pattern',
            'character_name','gender','class_type','backpack_style','capacity','net_quantity','recommended_age','size',
            'country_of_origin','net_weight','price','discount_price','stock','description','status'
        ];
        $ph = implode(',', array_fill(0, count($cols), '?'));
        $sql = 'INSERT INTO products (' . implode(',', $cols) . ') VALUES (' . $ph . ') '
            . 'ON DUPLICATE KEY UPDATE '
            . 'product_name=VALUES(product_name), generic_name=VALUES(generic_name), brand=VALUES(brand), '
            . 'category_id=VALUES(category_id), color=VALUES(color), material=VALUES(material), pattern=VALUES(pattern), '
            . 'character_name=VALUES(character_name), gender=VALUES(gender), class_type=VALUES(class_type), '
            . 'backpack_style=VALUES(backpack_style), capacity=VALUES(capacity), net_quantity=VALUES(net_quantity), '
            . 'recommended_age=VALUES(recommended_age), size=VALUES(size), country_of_origin=VALUES(country_of_origin), '
            . 'net_weight=VALUES(net_weight), price=VALUES(price), discount_price=VALUES(discount_price), stock=VALUES(stock), '
            . 'description=VALUES(description), status=VALUES(status)';

        $params = [
            $p['product_id'], $p['product_name'], $p['generic_name'], $p['brand'], $categoryDbId, $p['color'], $p['material'], $p['pattern'],
            $p['character_name'], $p['gender'], $p['class_type'], $p['backpack_style'], $p['capacity'], $p['net_quantity'], $p['recommended_age'], $p['size'],
            $p['country_of_origin'], $p['net_weight'], $p['price'], $p['discount_price'], $p['stock'], $p['description'], $p['status']
        ];

        $prodStmt = $pdo->prepare($sql);
        $prodStmt->execute($params);

        // Create a main product image entry. Use placeholder images if none exists.
        $productDbIdStmt = $pdo->prepare('SELECT id FROM products WHERE product_id = ? LIMIT 1');
        $productDbIdStmt->execute([$p['product_id']]);
        $productDbId = (int)($productDbIdStmt->fetchColumn() ?? 0);
        if ($productDbId <= 0) continue;

        // Prefer already present uploads if any; otherwise fallback to empty string path.
        $mainImagePath = suggestExistingImagePath();
        if (!$mainImagePath) {
            $mainImagePath = 'uploads/products/placeholder.png';
        }

        $imgStmt = $pdo->prepare('INSERT INTO product_images (product_id, image_url, is_main) VALUES (?, ?, 1)'
            . ' ON DUPLICATE KEY UPDATE image_url=VALUES(image_url), is_main=VALUES(is_main)');

        // There is no unique key on (product_id,image_url), so ON DUPLICATE KEY won't trigger.
        // We'll just ensure at least one main image exists.
        $hasMain = $pdo->prepare('SELECT 1 FROM product_images WHERE product_id = ? AND is_main = 1 LIMIT 1');
        $hasMain->execute([$productDbId]);
        $exists = (bool)$hasMain->fetchColumn();
        if (!$exists) {
            $insertImg = $pdo->prepare('INSERT INTO product_images (product_id, image_url, is_main) VALUES (?, ?, 1)');
            $insertImg->execute([$productDbId, $mainImagePath]);
        }
    }
}

function suggestExistingImagePath(): ?string {
    $dir = __DIR__ . '/../uploads/products';
    if (!is_dir($dir)) return null;
    $files = glob($dir . '/*.{png,jpg,jpeg,gif,webp}', GLOB_BRACE);
    if (!$files || count($files) === 0) return null;
    // pick first file for deterministic seed
    $file = $files[0];
    // Return relative DB path expected by frontend (see admin create products stores uploads/products/<file>)
    $basename = basename($file);
    return 'uploads/products/' . $basename;
}




// Admin API token (simple protection for admin endpoints).
// Set environment variable ADMIN_API_TOKEN in your Apache/PHP environment for production.
$adminToken = getenv('ADMIN_API_TOKEN') ?: 'change_me_admin_token';
if (!defined('ADMIN_API_TOKEN')) define('ADMIN_API_TOKEN', $adminToken);
?>





