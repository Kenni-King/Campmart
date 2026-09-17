<?php
session_start();
require_once '../includes/constant.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$product_id = intval($data['product_id'] ?? 0);
$payment_method = $data['payment_method'] ?? '';
$delivery_location = trim($data['delivery_location'] ?? '');
$phone = trim($data['phone'] ?? '');
$notes = trim($data['notes'] ?? '');

// Validation
if (!$product_id || !$payment_method || !$delivery_location || !$phone) {
    echo json_encode(['success' => false, 'message' => 'All required fields must be filled']);
    exit;
}

// Check if buyer's account is blocked
$buyer_check = $db->query("SELECT status FROM users WHERE id = " . intval($_SESSION['user_id']));
$buyer = $buyer_check->fetch_assoc();
if ($buyer && in_array($buyer['status'], ['suspended', 'banned'])) {
    echo json_encode(['success' => false, 'message' => 'Your account has been suspended or banned. You cannot perform this action.']);
    exit;
}

// Get product details
$product_query = "SELECT * FROM products WHERE id = ? AND status = 'approved' AND availability = 'available'";
$stmt = $db->prepare($product_query);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();

if (!$product) {
    echo json_encode(['success' => false, 'message' => 'Product not available']);
    exit;
}

// Check if buyer is not the seller
if ($product['user_id'] == $_SESSION['user_id']) {
    echo json_encode(['success' => false, 'message' => 'You cannot buy your own product']);
    exit;
}

// Calculate total with service fee (2%)
$service_fee = $product['price'] * 0.02;
$total_amount = $product['price'] + $service_fee;

// Create order
$order_query = "
    INSERT INTO orders (
        buyer_id, 
        seller_id, 
        product_id, 
        payment_method, 
        delivery_location, 
        buyer_phone, 
        notes, 
        item_price, 
        service_fee, 
        total_amount, 
        status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')
";

$stmt = $db->prepare($order_query);
$stmt->bind_param(
    "iiissssddds",
    $_SESSION['user_id'],
    $product['user_id'],
    $product_id,
    $payment_method,
    $delivery_location,
    $phone,
    $notes,
    $product['price'],
    $service_fee,
    $total_amount,
    $status
);

if ($stmt->execute()) {
    $order_id = $db->insert_id;
    
    // Update product availability to reserved
    $update_product = "UPDATE products SET availability = 'reserved' WHERE id = ?";
    $stmt = $db->prepare($update_product);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    
    // Create notification for seller
    $notification_query = "
        INSERT INTO notifications (user_id, type, title, message, related_id)
        VALUES (?, 'new_order', 'New Order Received', ?, ?)
    ";
    $message = "You have a new order for " . $product['title'];
    $stmt = $db->prepare($notification_query);
    $stmt->bind_param("isi", $product['user_id'], $message, $order_id);
    $stmt->execute();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Order placed successfully',
        'order_id' => $order_id
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to create order: ' . $db->error]);
}
?>
