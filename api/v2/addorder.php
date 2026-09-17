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

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['payload'])) {
    $_POST['store'] = $_GET['store'] ?? $_GET['staff'] ?? '';
    $_POST['payload'] = $_GET['payload'];
} elseif ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request method: '.$_SERVER['REQUEST_METHOD'],
    ]);
    exit;
}

$authorization_header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$bearer_token = str_replace('Bearer ', '', $authorization_header);

if ($bearer_token === '') {
    $bearer_token = $_POST['store'] ?? $_POST['staff'] ?? '';
}

if (sqL1('staff', 'pub', $bearer_token) == 0) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid Api Key.',
    ]);
    exit;
}

$json = file_get_contents('php://input');
$data = json_decode($json, true);

if (!$data && isset($_POST['payload'])) {
    $json = $_POST['payload'];
    $data = json_decode($json, true);
}

if (!$data || !is_array($data)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid order payload.',
    ]);
    exit;
}

$sid = sqLx('staff', 'pub', $bearer_token, 'id');
$bid = sqLx('staff', 'pub', $bearer_token, 'bid');

$salesID = $data['salesID'] ?? '';
$total = $data['total'] ?? 0;
$name = $data['name'] ?? 'Customer';
$phone = $data['phone'] ?? '';
$payMethod = $data['payMethod'] ?? '';
$timestamp = $data['timestamp'] ?? date('Y-m-d H:i:s');
$cid = '';
$json = json_encode($data);

dbInsert('salesorder', [
    'salesid' => $salesID,
    'total' => $total,
    'uid' => $sid,
    'bid' => $bid,
    'cid' => $cid,
    'customer' => $name,
    'mode' => $payMethod,
    'phone' => $phone,
    'app' => 'qrcode',
    'data' => $json,
    'salesdate' => date('Y-m-d H:i:s', strtotime($timestamp))
]);

$deliveryDetails = $data['deliveryDetails'] ?? [];
if (is_array($deliveryDetails) && !empty($deliveryDetails['requested'])) {
    $pro->EnsureDeliveryTables();
    $safeSalesId = $db->real_escape_string($salesID);
    $safeName = $db->real_escape_string($name);
    $safePhone = $db->real_escape_string($phone);
    $safeAddress = $db->real_escape_string(trim((string)($deliveryDetails['address'] ?? '')));
    $safeNote = $db->real_escape_string(trim((string)($deliveryDetails['note'] ?? 'Delivery requested from QR Store')));
    $exists = $db->query("SELECT id FROM deliveries WHERE bid='$bid' AND salesid='$safeSalesId' LIMIT 1");
    if (!$exists || $exists->num_rows == 0) {
        $db->query("INSERT INTO deliveries (bid,salesid,source_type,customer,phone,address,status) VALUES ('$bid','$safeSalesId','salesorder','$safeName','$safePhone','$safeAddress','pending_assignment')");
        $deliveryId = (int)$db->insert_id;
        if ($deliveryId > 0) {
            $db->query("INSERT INTO delivery_logs (bid,delivery_id,salesid,status,note,uid,actor) VALUES ('$bid','$deliveryId','$safeSalesId','pending_assignment','$safeNote',0,'buyer')");
        }
    }
}

http_response_code(200);
echo json_encode([
    'status' => 'success',
    'message' => 'Order Successfully Submitted',
    'salesID' => $salesID,
    'tracking' => [
        'orderId' => $salesID,
        'storageKey' => 'salespro_qrstore_orders',
        'historyEndpoint' => 'v2/delivery.php',
        'trackUrl' => 'v2/delivery.php?salesID='.urlencode($salesID)
    ]
]);
?>
