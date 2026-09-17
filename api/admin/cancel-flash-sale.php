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

if (!$sale_id) {
    echo json_encode(['success' => false, 'message' => 'Invalid input']);
    exit;
}

// Update sale status to cancelled
$update_query = "UPDATE flash_sales SET status = 'cancelled' WHERE id = ?";
$stmt = $db->prepare($update_query);
$stmt->bind_param("i", $sale_id);

if ($stmt->execute()) {
    echo json_encode(['success' => true, 'message' => 'Flash sale cancelled']);
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to cancel flash sale: ' . $db->error]);
}
?>
