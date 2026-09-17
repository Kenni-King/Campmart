<?php
// API endpoint for tracking product views
header('Content-Type: application/json');
require_once '../includes/constant.php';

session_start();

$data = json_decode(file_get_contents('php://input'), true);
$product_id = intval($data['product_id'] ?? 0);

// Get user ID if logged in, otherwise use null
$user_id = $_SESSION['user_id'] ?? null;

if ($product_id <= 0) {
    echo json_encode(['success' => false]);
    exit;
}

try {
    // Insert view record
    $query = "INSERT INTO product_views (product_id, user_id, ip_address) VALUES (?, ?, ?)";
    $stmt = $db->prepare($query);
    $ip = $_SERVER['REMOTE_ADDR'];
    $stmt->bind_param("iis", $product_id, $user_id, $ip);
    $stmt->execute();
    
    // Update product views count
    $update_query = "UPDATE products SET views_count = views_count + 1 WHERE id = ?";
    $stmt = $db->prepare($update_query);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
