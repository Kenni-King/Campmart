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
$flash_product_id = intval($data['flash_product_id'] ?? 0);

if (!$flash_product_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

// Delete the product from flash sale
$delete_query = "DELETE FROM flash_sale_products WHERE id = ?";
$stmt = $db->prepare($delete_query);
$stmt->bind_param("i", $flash_product_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Product removed from flash sale']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to remove product: ' . $db->error]);
}
?>
