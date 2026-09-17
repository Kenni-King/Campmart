<?php
session_start();
require_once '../../includes/constant.php';

header('Content-Type: application/json');

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_query = "SELECT role FROM users WHERE id = ?";
$stmt = $db->prepare($user_query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();

if (!$user || $user['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);
$sale_id = intval($data['sale_id'] ?? 0);
$product_id = intval($data['product_id'] ?? 0);
$discount_percentage = floatval($data['discount_percentage'] ?? 0);
$stock_limit = isset($data['stock_limit']) && $data['stock_limit'] !== '' ? intval($data['stock_limit']) : null;

if (!$sale_id || !$product_id || $discount_percentage <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

// Get product original price
$price_query = "SELECT price FROM products WHERE id = ?";
$stmt = $db->prepare($price_query);
$stmt->bind_param("i", $product_id);
$stmt->execute();
$price_result = $stmt->get_result();
$product = $price_result->fetch_assoc();

if (!$product) {
    echo json_encode(['success' => false, 'message' => 'Product not found']);
    exit;
}

$original_price = $product['price'];
$sale_price = $original_price - ($original_price * ($discount_percentage / 100));

// Check if product already in sale
$check_query = "SELECT id FROM flash_sale_products WHERE flash_sale_id = ? AND product_id = ?";
$stmt = $db->prepare($check_query);
$stmt->bind_param("ii", $sale_id, $product_id);
$stmt->execute();
$check_result = $stmt->get_result();

if ($check_result->num_rows > 0) {
    echo json_encode(['success' => false, 'message' => 'Product already in this flash sale']);
    exit;
}

// Insert product into flash sale
$insert_query = "
    INSERT INTO flash_sale_products (flash_sale_id, product_id, original_price, sale_price, discount_percentage, stock_limit)
    VALUES (?, ?, ?, ?, ?, ?)
";
$stmt = $db->prepare($insert_query);
$stmt->bind_param("iidddi", $sale_id, $product_id, $original_price, $sale_price, $discount_percentage, $stock_limit);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Product added to flash sale']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to add product: ' . $db->error]);
}
?>
