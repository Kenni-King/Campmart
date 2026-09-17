<?php
include_once '../dashboard/lib/controller.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function dsEnsureBusinessColumn($column, $definition) {
    global $db;
    $column = $db->real_escape_string($column);
    $exists = $db->query("SHOW COLUMNS FROM business LIKE '$column'");
    if ($exists && $exists->num_rows == 0) {
        $db->query("ALTER TABLE business ADD COLUMN $column $definition");
    }
}

function dsBool($value) {
    return $value === true || $value === 1 || $value === '1' || $value === 'true' || $value === 'on';
}

function dsPayload($bid, $store = '') {
    $bid = (int)$bid;
    dsEnsureBusinessColumn('qr_payment_option', "VARCHAR(20) NOT NULL DEFAULT 'both'");
    dsEnsureBusinessColumn('qr_delivery_available', 'INT(2) NOT NULL DEFAULT 0');

    $paymentOption = sqLx('business', 'id', $bid, 'qr_payment_option') ?: 'both';
    if (!in_array($paymentOption, ['bank', 'delivery', 'both'], true)) {
        $paymentOption = 'both';
    }

    $deliveryAvailable = (int)(sqLx('business', 'id', $bid, 'qr_delivery_available') ?: 0) === 1;

    return [
        'status' => 'success',
        'message' => 'QR Store delivery settings loaded.',
        'businessid' => $bid,
        'staffStoreId' => $store,
        'qrPaymentOption' => $paymentOption,
        'qrPaymentSettings' => [
            'allowBank' => in_array($paymentOption, ['bank', 'both'], true),
            'allowDelivery' => in_array($paymentOption, ['delivery', 'both'], true)
        ],
        'deliveryService' => [
            'available' => $deliveryAvailable
        ]
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $store = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['store'] ?? $_GET['staff'] ?? '');
    if ($store === '' || sqL1('staff', 'pub', $store) == 0) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Store not found.']);
        exit;
    }

    $bid = sqLx('staff', 'pub', $store, 'bid');
    echo json_encode(dsPayload($bid, $store));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $authorizationHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    $bearerToken = str_replace('Bearer ', '', $authorizationHeader);
    if ($bearerToken === '') {
        $bearerToken = $_POST['store'] ?? $_POST['staff'] ?? '';
    }

    if ($bearerToken === '' || sqL1('staff', 'pub', $bearerToken) == 0) {
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Invalid API key.']);
        exit;
    }

    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    if (!is_array($data)) {
        $data = $_POST;
    }

    $bid = (int)sqLx('staff', 'pub', $bearerToken, 'bid');
    $paymentOption = $data['qrPaymentOption'] ?? $data['qr_payment_option'] ?? 'both';
    if (!in_array($paymentOption, ['bank', 'delivery', 'both'], true)) {
        $paymentOption = 'both';
    }
    $deliveryAvailable = dsBool($data['deliveryAvailable'] ?? $data['qr_delivery_available'] ?? false) ? 1 : 0;
    $paymentOptionSql = $db->real_escape_string($paymentOption);

    dsEnsureBusinessColumn('qr_payment_option', "VARCHAR(20) NOT NULL DEFAULT 'both'");
    dsEnsureBusinessColumn('qr_delivery_available', 'INT(2) NOT NULL DEFAULT 0');
    $db->query("UPDATE business SET qr_payment_option='$paymentOptionSql', qr_delivery_available='$deliveryAvailable' WHERE id='$bid'");

    echo json_encode(dsPayload($bid, $bearerToken));
    exit;
}

http_response_code(405);
echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
?>
