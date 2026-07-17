<?php
// CreateOrderOnline.php - COD pays only Shipping Charge via PhonePe

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once '../../../db/db_con.php';

// Token
$accessToken = null;
$possibleTokenPaths = ['../../../accessToken.php', '../accessToken.php', 'accessToken.php', '../../accessToken.php'];
foreach ($possibleTokenPaths as $path) {
    if (file_exists($path)) {
        include $path;
        break;
    }
}

if (empty($accessToken)) {
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => 'Payment configuration error']);
    exit;
}

// Parse Input
$body = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$paymentMethod = strtoupper(trim($body['payment_method'] ?? 'ONLINE'));
$user_id       = isset($body['user_id']) && is_numeric($body['user_id']) ? (int)$body['user_id'] : null;

$total_amount  = (float)($body['total_amount'] ?? 0);
$subtotal      = (float)($body['subtotal'] ?? 0);
$tax           = (float)($body['tax'] ?? 0);
$shipping_cost = (float)($body['shipping_cost'] ?? 0);

$items    = $body['items'] ?? [];
$shipping = $body['shipping'] ?? [];

$morderid = 'ODR' . time();

// Shipping Data
$customer_name = trim(($shipping['firstName'] ?? '') . ' ' . ($shipping['lastName'] ?? ''));
if (empty($customer_name)) $customer_name = trim($body['customer_name'] ?? 'Guest');

$shipping_address = trim($shipping['address'] ?? $body['shipping_address'] ?? '');
$city    = trim($shipping['city'] ?? $body['city'] ?? '');
$state   = trim($shipping['state'] ?? $body['state'] ?? '');
$country = trim($shipping['country'] ?? $body['country'] ?? 'India');
$pincode = trim($shipping['zip'] ?? $body['pincode'] ?? '');
$phone   = trim($shipping['phone'] ?? $body['phone'] ?? '');

try {
    $pdo->beginTransaction();

    $order_ref = generateOrderId($pdo);

    // Insert Order
    $stmt = $pdo->prepare('
        INSERT INTO orders (order_id, user_id, merchant_id, subtotal, tax, shipping_cost, total_amount,
            payment_method, payment_status, order_status, customer_name, customer_email,
            customer_phone, shipping_address, city, state, country, pincode, status)
        VALUES (:order_id, :user_id, :merchant_id, :subtotal, :tax, :shipping_cost, :total_amount,
            :payment_method, :payment_status, :order_status, :customer_name, :customer_email,
            :customer_phone, :shipping_address, :city, :state, :country, :pincode, :status)
    ');

    $stmt->execute([
        ':order_id'         => $order_ref,
        ':user_id'          => $user_id,
        ':merchant_id'      => $morderid,
        ':subtotal'         => $subtotal,
        ':tax'              => $tax,
        ':shipping_cost'    => $shipping_cost,
        ':total_amount'     => $total_amount,
        ':payment_method'   => $paymentMethod,
        ':payment_status'   => 'pending',
        ':order_status'     => 'pending',
        ':customer_name'    => $customer_name,
        ':customer_email'   => $shipping['email'] ?? null,
        ':customer_phone'   => $phone,
        ':shipping_address' => $shipping_address,
        ':city'             => $city,
        ':state'            => $state,
        ':country'          => $country,
        ':pincode'          => $pincode,
        ':status'           => 'pending'
    ]);

    $order_db_id = (int)$pdo->lastInsertId();

    // Insert Items (same as before)
   // ==================== INSERT ORDER ITEMS ====================
if (!empty($items)) {
    $itemStmt = $pdo->prepare('
        INSERT INTO order_items (
            order_id, product_id, product_sku, product_name, quantity,
            unit_price, total_price, selected_color, selected_size, image_url
        ) VALUES (
            :order_id, :product_id, :product_sku, :product_name, :quantity,
            :unit_price, :total_price, :selected_color, :selected_size, :image_url
        )
    ');

    foreach ($items as $it) {
        $identifier = $it['product_id'] ?? $it['id'] ?? null;
        if (!$identifier) continue;

        $qty = max(1, (int)($it['quantity'] ?? 1));

        // Find product safely
        $prodStmt = $pdo->prepare('
            SELECT id, product_id AS sku, product_name, price, discount_price 
            FROM products 
            WHERE (id = :id OR product_id = :sku) 
            LIMIT 1
        ');
        $prodStmt->execute([
            ':id' => is_numeric($identifier) ? (int)$identifier : null,
            ':sku' => (string)$identifier
        ]);
        $prod = $prodStmt->fetch(PDO::FETCH_ASSOC);

        if (!$prod) {
            $pdo->rollBack();
            echo json_encode([
                'status' => false,
                'message' => 'Product not found: ' . htmlspecialchars($identifier)
            ]);
            exit;
        }

        $unit_price = $it['price'] ?? $it['unit_price']
            ?? (($prod['discount_price'] ?? 0) > 0 ? $prod['discount_price'] : $prod['price']);

        $total_price = $it['total_price'] ?? ($unit_price * $qty);

        $itemStmt->execute([
            ':order_id'       => $order_db_id,
            ':product_id'     => $prod['id'],           // ← This must exist in products.id
            ':product_sku'    => $prod['sku'] ?? $identifier,
            ':product_name'   => $prod['product_name'] ?? ($it['name'] ?? ''),
            ':quantity'       => $qty,
            ':unit_price'     => (float)$unit_price,
            ':total_price'    => (float)$total_price,
            ':selected_color' => $it['selectedColor'] ?? null,
            ':selected_size'  => $it['selectedSize'] ?? null,
            ':image_url'      => $it['image'] ?? null
        ]);
    }
}

    // COD Shipment Record
    if ($paymentMethod === 'COD') {
        $tracking_id = 'TRK-' . date('YmdHis') . rand(100, 999);

        $shipStmt = $pdo->prepare('
            INSERT INTO shipments (order_id, customer_name, phone, shipping_address, city, state, country, pincode,
                tracking_id, shipment_status, shipping_charge, shipping_charge_status, cod_amount, cod_status)
            VALUES (:order_id, :customer_name, :phone, :shipping_address, :city, :state, :country, :pincode,
                :tracking_id, "pending", :shipping_charge, "pending", :cod_amount, "pending")
        ');

        $shipStmt->execute([
            ':order_id'          => $order_ref,
            ':customer_name'     => $customer_name,
            ':phone'             => $phone,
            ':shipping_address'  => $shipping_address,
            ':city'              => $city,
            ':state'             => $state,
            ':country'           => $country,
            ':pincode'           => $pincode,
            ':tracking_id'       => $tracking_id,
            ':shipping_charge'   => $shipping_cost,
            ':cod_amount'        => $total_amount
        ]);
    }

    $pdo->commit();

    // ==================== PHONEPE CALL (Always for shipping charge in COD) ====================
    $amountToPay = ($paymentMethod === 'COD') ? $shipping_cost : $total_amount;
<<<<<<< HEAD
    // https://shreemahaveercollections.com/
    $redirectUrl = 'https://shreemahaveercollections.com/payment/loading?morderid=' . urlencode($morderid) . '&UserId=' . $user_id.'&paymentMethod='.$paymentMethod;
=======

    $redirectUrl = 'http://localhost:5173/payment/loading?morderid=' . urlencode($morderid) . '&UserId=' . $user_id.'&paymentMethod='.$paymentMethod;
>>>>>>> f05ba20b4d3983e4a5f4e5f55eafed24b3821601

    $payload = [
        'merchantOrderId' => $morderid,
        'amount' => (int)($amountToPay * 100),
        'expireAfter' => 1200,
        'metaInfo' => ['udf1' => $order_ref, 'udf2' => (string)$user_id],
        'paymentFlow' => [
            'type' => 'PG_CHECKOUT',
            'message' => 'Payment for Order ' . $order_ref,
            'merchantUrls' => [
                'redirectUrl' => $redirectUrl,
                'webhookUrl'  => 'https://webhook.site/your-url'
            ]
        ]
    ];

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://api-preprod.phonepe.com/apis/pg-sandbox/checkout/v2/pay',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: O-Bearer ' . trim($accessToken)
        ]
    ]);

    $response = curl_exec($curl);
    curl_close($curl);

    $data = json_decode($response, true);

    if (!empty($data['redirectUrl'])) {
        session_start();
        $_SESSION['merchantOrderId'] = $morderid;

        echo json_encode([
            'status' => true,
            'message' => 'Order created successfully',
            'order_id' => $order_ref,
            'redirect_url' => $data['redirectUrl'],
            'payment_for' => ($paymentMethod === 'COD') ? 'shipping_charge' : 'full_amount'
        ]);
        exit;
    } else {
        throw new Exception('Payment initiation failed');
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log($e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => false, 'message' => $e->getMessage()]);
}

function generateOrderId($pdo) {
    $stmt = $pdo->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_NAME = 'orders' AND TABLE_SCHEMA = DATABASE()");
    $next = $stmt->fetchColumn();
    return 'SMC-ODR-' . str_pad($next ?: time(), 5, '0', STR_PAD_LEFT);
}
?>