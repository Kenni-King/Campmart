<?php
include_once '../dashboard/lib/controller.php';

// Enable CORS
header('Access-Control-Allow-Origin: *'); 
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS'); 
header('Access-Control-Allow-Headers: Content-Type, Authorization'); // Specify allowed headers
header('Content-Type: application/json'); 

function v2BaseUrl() {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $base = preg_replace('#/api/v[12]$#', '', $script);
    return rtrim($scheme.'://'.$host.$base, '/');
}

function v2EnsureBusinessColumn($column, $definition) {
    global $db;
    $column = $db->real_escape_string($column);
    $exists = $db->query("SHOW COLUMNS FROM business LIKE '$column'");
    if ($exists && $exists->num_rows == 0) {
        $db->query("ALTER TABLE business ADD COLUMN $column $definition");
    }
}

function v2BusinessPayload($pro, $bid, $staffPub = '') {
    $baseUrl = v2BaseUrl();
    v2EnsureBusinessColumn('qr_payment_option', "VARCHAR(20) NOT NULL DEFAULT 'both'");
    v2EnsureBusinessColumn('qr_delivery_available', 'INT(2) NOT NULL DEFAULT 0');
    $qrPaymentOption = sqLx('business', 'id', $bid, 'qr_payment_option') ?: 'both';
    if (!in_array($qrPaymentOption, ['bank', 'delivery', 'both'], true)) {
        $qrPaymentOption = 'both';
    }
    $allowBankPayment = in_array($qrPaymentOption, ['bank', 'both'], true);
    $allowDeliveryPayment = in_array($qrPaymentOption, ['delivery', 'both'], true);
    $deliveryAvailable = (int)(sqLx('business', 'id', $bid, 'qr_delivery_available') ?: 0) === 1;
    $businessLogo = sqLx('business', 'id', $bid, 'logo');
    $businessPhoto = sqLx('business', 'id', $bid, 'photo');
    $products = $pro->getProducts($bid);
    $paymentMethods = $allowBankPayment ? $pro->getPayMethod($bid) : [];
    $categories = [];
    foreach ($products as $product) {
        $category = $product['category'] ?: 'Uncategorized';
        $categories[$category] = $category;
    }

    return [
        'status' => 'success',
        'message' => 'Operation Successful',
        'business' => sqLx('business', 'id', $bid, 'name'),
        'businessid' => $bid,
        'staffStoreId' => $staffPub,
        'businesslogo' => $businessLogo ? $baseUrl.'/dashboard/docs/business/'.$businessLogo : '',
        'businessphoto' => $businessPhoto ? $baseUrl.'/dashboard/docs/business/'.$businessPhoto : '',
        'businessaddress' => sqLx('business', 'id', $bid, 'address'),
        'businessphone' => sqLx('business', 'id', $bid, 'phone'),
        'businessemail' => sqLx('business', 'id', $bid, 'email'),
        'businessdescription' => sqLx('business', 'id', $bid, 'description'),
        'qrPaymentOption' => $qrPaymentOption,
        'qrPaymentSettings' => [
            'allowBank' => $allowBankPayment,
            'allowDelivery' => $allowDeliveryPayment
        ],
        'deliveryService' => [
            'available' => $deliveryAvailable
        ],
        'deliveryTracking' => [
            'storageKey' => 'salespro_qrstore_orders',
            'historyEndpoint' => $baseUrl.'/v2/delivery.php',
            'trackEndpoint' => $baseUrl.'/v2/delivery.php?salesID={salesID}'
        ],
        'categories' => array_values($categories),
        'paymentMethods' => $paymentMethods,
        'paymentAccounts' => $paymentMethods,
        'bankdata' => $paymentMethods,
        'data' => $products
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204); // No Content
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $store = $_GET['store'] ?? $_GET['staff'] ?? '';
    if ($store === '' || sqL1('staff', 'pub', $store) == 0) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => 'Store not found.',
        ]);
        exit;
    }

    $bid = sqLx('staff', 'pub', $store, 'bid');
    echo json_encode(v2BusinessPayload($pro, $bid, $store));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method.',
    ]);
    exit;
}

// Extract authorization header
$authorization_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$bearer_token = str_replace('Bearer ', '', $authorization_header);

if (sqL1('staff', 'pub', $bearer_token) == 0) {
    http_response_code(401); // Unauthorized
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid API Key.',
    ]);
    exit;
}

$bid = sqLx('staff', 'pub', $bearer_token, 'bid');
echo json_encode(v2BusinessPayload($pro, $bid, $bearer_token));
?>
