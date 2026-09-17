<?php
session_start();
require_once __DIR__ . '/../includes/controller.php';

header('Content-Type: application/json');

if (!isset($_SESSION['userAppId'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Only admins can view user details
$userId = (int) $_SESSION['userAppId'];
$me = $db->query("SELECT role FROM users WHERE id = $userId")->fetch_assoc();
if (!$me || ($me['role'] !== 'admin' && $me['role'] !== 'superadmin')) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$targetId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($targetId < 1) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid user ID']);
    exit;
}

$stmt = $db->prepare("
    SELECT id, username, email, full_name, phone, role, status,
           total_sales, total_purchases, rating, total_ratings,
           created_at, last_login
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmt->bind_param('i', $targetId);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    http_response_code(404);
    echo json_encode(['error' => 'User not found']);
    exit;
}

echo json_encode($user);
