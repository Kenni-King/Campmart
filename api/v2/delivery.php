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

$pro->EnsureDeliveryTables();
$statusLabels = $pro->DeliveryStatusLabels();

function deliveryInput() {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $_POST;
}

function deliveryCleanOrderIds($ids) {
    if (is_string($ids)) {
        $ids = explode(',', $ids);
    }
    $clean = [];
    foreach ((array)$ids as $id) {
        $id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$id);
        if ($id !== '') {
            $clean[] = $id;
        }
    }
    return array_values(array_unique($clean));
}

function deliveryOrderPayload($salesid) {
    global $db, $statusLabels;
    $safeSalesId = $db->real_escape_string($salesid);
    $order = [];
    foreach (['salesorder', 'sales'] as $table) {
        $orderSql = $db->query("SELECT * FROM `$table` WHERE salesid='$safeSalesId' LIMIT 1");
        if ($orderSql && $orderSql->num_rows) {
            $order = $orderSql->fetch_assoc();
            $order['_source_type'] = $table;
            break;
        }
    }
    if (!$order) {
        return null;
    }

    $bid = (int)$order['bid'];
    $deliverySql = $db->query("SELECT d.*, r.name AS rider_name, r.phone AS rider_phone, r.vehicle, r.plate_no
        FROM deliveries d
        LEFT JOIN delivery_riders r ON r.id=d.rider_id
        WHERE d.bid='$bid' AND d.salesid='$safeSalesId'
        LIMIT 1");
    $delivery = ($deliverySql && $deliverySql->num_rows) ? $deliverySql->fetch_assoc() : [];
    $logs = [];
    if ($delivery) {
        $deliveryId = (int)$delivery['id'];
        $logSql = $db->query("SELECT status,note,actor,created FROM delivery_logs WHERE delivery_id='$deliveryId' ORDER BY id ASC");
        while ($logSql && $log = $logSql->fetch_assoc()) {
            $logs[] = [
                'status' => $log['status'],
                'label' => $statusLabels[$log['status']] ?? $log['status'],
                'note' => $log['note'],
                'actor' => $log['actor'],
                'timestamp' => $log['created']
            ];
        }
    }

    $orderData = json_decode($order['data'] ?? '', true);
    if (!is_array($orderData)) {
        $orderData = [];
    }
    $deliveryDetails = $orderData['deliveryDetails'] ?? [];
    if (!is_array($deliveryDetails)) {
        $deliveryDetails = [];
    }

    return [
        'salesID' => $order['salesid'],
        'sourceType' => $order['_source_type'],
        'business' => sqLx('business', 'id', $bid, 'name'),
        'customer' => $order['customer'] ?? '',
        'phone' => $order['phone'] ?? '',
        'total' => (float)($order['total'] ?? 0),
        'orderStatus' => (int)($order['status'] ?? 1),
        'orderDate' => $order['salesdate'] ?? $order['created'] ?? '',
        'cart' => $orderData['cart'] ?? $orderData,
        'deliveryDetails' => [
            'requested' => !empty($deliveryDetails['requested']),
            'address' => $delivery['address'] ?? ($deliveryDetails['address'] ?? ''),
            'note' => $deliveryDetails['note'] ?? ''
        ],
        'delivery' => $delivery ? [
            'status' => $delivery['status'],
            'label' => $statusLabels[$delivery['status']] ?? $delivery['status'],
            'address' => $delivery['address'] ?? '',
            'rider' => [
                'name' => $delivery['rider_name'] ?? '',
                'phone' => $delivery['rider_phone'] ?? '',
                'vehicle' => $delivery['vehicle'] ?? '',
                'plateNo' => $delivery['plate_no'] ?? ''
            ],
            'receivedAt' => $delivery['received_at'] ?? '',
            'reviewStars' => $delivery['review_stars'] ? (int)$delivery['review_stars'] : null,
            'reviewNote' => $delivery['review_note'] ?? '',
            'reviewedAt' => $delivery['reviewed_at'] ?? ''
        ] : [
            'status' => 'pending_assignment',
            'label' => $statusLabels['pending_assignment'],
            'address' => '',
            'rider' => ['name'=>'', 'phone'=>'', 'vehicle'=>'', 'plateNo'=>''],
            'receivedAt' => '',
            'reviewStars' => null,
            'reviewNote' => '',
            'reviewedAt' => ''
        ],
        'logs' => $logs
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && in_array($_GET['action'], ['received', 'review'], true)) {
    $_POST = $_GET;
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $ids = deliveryCleanOrderIds($_GET['orderIds'] ?? ($_GET['salesIDs'] ?? ($_GET['salesID'] ?? '')));
    $orders = [];
    foreach ($ids as $id) {
        $payload = deliveryOrderPayload($id);
        if ($payload) {
            $orders[] = $payload;
        }
    }
    echo json_encode([
        'status' => 'success',
        'message' => 'Delivery history loaded.',
        'orders' => $orders
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !isset($_POST['action'])) {
    http_response_code(405);
    echo json_encode(['status'=>'error', 'message'=>'Invalid request method.']);
    exit;
}

$data = deliveryInput();
$action = $data['action'] ?? 'history';
$salesid = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)($data['salesID'] ?? $data['salesid'] ?? ''));

if ($action === 'history') {
    $ids = deliveryCleanOrderIds($data['orderIds'] ?? $data['salesIDs'] ?? []);
    $orders = [];
    foreach ($ids as $id) {
        $payload = deliveryOrderPayload($id);
        if ($payload) {
            $orders[] = $payload;
        }
    }
    echo json_encode(['status'=>'success', 'message'=>'Delivery history loaded.', 'orders'=>$orders]);
    exit;
}

if ($salesid === '') {
    http_response_code(400);
    echo json_encode(['status'=>'error', 'message'=>'Order ID is required.']);
    exit;
}

$payload = deliveryOrderPayload($salesid);
if (!$payload) {
    http_response_code(404);
    echo json_encode(['status'=>'error', 'message'=>'Order was not found.']);
    exit;
}

if ($action === 'received' || $action === 'review') {
    $safeSalesId = $db->real_escape_string($salesid);
    $orderBid = 0;
    foreach (['salesorder', 'sales'] as $table) {
        $orderSql = $db->query("SELECT bid, customer, phone FROM `$table` WHERE salesid='$safeSalesId' LIMIT 1");
        if ($orderSql && $orderSql->num_rows) {
            $orderRow = $orderSql->fetch_assoc();
            $orderBid = (int)$orderRow['bid'];
            break;
        }
    }
    $deliverySql = $db->query("SELECT * FROM deliveries WHERE bid='$orderBid' AND salesid='$safeSalesId' LIMIT 1");
    if (!$deliverySql || $deliverySql->num_rows == 0) {
        http_response_code(404);
        echo json_encode(['status'=>'error', 'message'=>'Delivery has not been assigned yet.']);
        exit;
    }
    $delivery = $deliverySql->fetch_assoc();
    $deliveryId = (int)$delivery['id'];
    $currentStatus = (string)($delivery['status'] ?? '');

    if ($action === 'received') {
        if (!in_array($currentStatus, ['assigned', 'picked_up', 'in_transit', 'out_for_delivery', 'delivered', 'received'], true)) {
            http_response_code(409);
            echo json_encode(['status'=>'error', 'message'=>'This package must be assigned to a rider before buyer receipt can be confirmed.']);
            exit;
        }
        $updated = $db->query("UPDATE deliveries SET status='received', received_at=NOW() WHERE id='$deliveryId'");
        if (!$updated) {
            http_response_code(500);
            echo json_encode(['status'=>'error', 'message'=>'Unable to save received status.']);
            exit;
        }
        $db->query("INSERT INTO delivery_logs (bid,delivery_id,salesid,status,note,uid,actor) VALUES ('$orderBid','$deliveryId','$safeSalesId','received','Buyer marked package as received',0,'buyer')");
    }

    if ($action === 'review') {
        if (!in_array($currentStatus, ['delivered', 'received', 'reviewed'], true)) {
            http_response_code(409);
            echo json_encode(['status'=>'error', 'message'=>'Review is available only after the order is delivered or received.']);
            exit;
        }
        $stars = max(1, min(5, (int)($data['stars'] ?? 0)));
        $note = $db->real_escape_string(trim((string)($data['review'] ?? $data['note'] ?? '')));
        if ($stars < 1) {
            http_response_code(400);
            echo json_encode(['status'=>'error', 'message'=>'Select a star rating.']);
            exit;
        }
        $updated = $db->query("UPDATE deliveries SET status='reviewed', received_at=COALESCE(received_at, NOW()), review_stars='$stars', review_note='$note', reviewed_at=NOW() WHERE id='$deliveryId'");
        if (!$updated) {
            http_response_code(500);
            echo json_encode(['status'=>'error', 'message'=>'Unable to save review.']);
            exit;
        }
        $db->query("INSERT INTO delivery_logs (bid,delivery_id,salesid,status,note,uid,actor) VALUES ('$orderBid','$deliveryId','$safeSalesId','reviewed','Buyer submitted delivery review',0,'buyer')");
    }

    echo json_encode([
        'status' => 'success',
        'message' => $action === 'review' ? 'Review submitted.' : 'Delivery marked as received.',
        'order' => deliveryOrderPayload($salesid)
    ]);
    exit;
}

echo json_encode([
    'status' => 'success',
    'message' => 'Delivery loaded.',
    'order' => $payload
]);
?>
