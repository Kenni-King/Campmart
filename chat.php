<?php
session_start();
require_once 'includes/controller.php';

// Must be logged in
if (!isset($_SESSION['userAppId'])) {
    header('Location: ' . SITE_URL . 'login.php');
    exit;
}

$userId = $_SESSION['userAppId'];
$sellerId = isset($_GET['seller_id']) ? intval($_GET['seller_id']) : 0;
$productId = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
$serviceId = isset($_GET['service_id']) ? intval($_GET['service_id']) : 0;

if ($sellerId <= 0 || $sellerId == $userId) {
    header('Location: ' . SITE_URL . 'messages.php');
    exit;
}

global $db;

// Check if conversation already exists
$existing = $db->query("SELECT id FROM conversations WHERE 
    (user1_id = '$userId' AND user2_id = '$sellerId') OR 
    (user1_id = '$sellerId' AND user2_id = '$userId')")->fetch_assoc();

if ($existing) {
    header('Location: ' . SITE_URL . 'messages.php?id=' . $existing['id']);
    exit;
}

// Create new conversation with context
$insertData = [
    'user1_id' => $userId,
    'user2_id' => $sellerId,
    'created_at' => date('Y-m-d H:i:s'),
    'updated_at' => date('Y-m-d H:i:s')
];

if ($productId > 0) {
    $insertData['product_id'] = $productId;
}

if ($serviceId > 0) {
    $insertData['service_id'] = $serviceId;
}

$convId = dbInsert('conversations', $insertData);

if ($convId) {
    header('Location: ' . SITE_URL . 'messages.php?id=' . $convId);
} else {
    header('Location: ' . SITE_URL . 'messages.php');
}
exit;
