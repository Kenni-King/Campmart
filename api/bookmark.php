<?php
header('Content-Type: application/json');
session_start();
require_once '../includes/controller.php';

if (!isset($_SESSION['userAppId'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to bookmark products']);
    exit;
}

if (isAccountBlocked()) {
    echo json_encode(['success' => false, 'message' => 'Your account has been suspended or banned. You cannot perform this action.']);
    exit;
}

$requestData = $_POST;
if (empty($requestData)) {
    $jsonBody = json_decode(file_get_contents('php://input'), true);
    if (is_array($jsonBody)) {
        $requestData = $jsonBody;
    }
}

$productId = (int) ($requestData['product_id'] ?? 0);
$action = $requestData['action'] ?? 'toggle';
$userId = (int) $_SESSION['userAppId'];

if ($productId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
    exit;
}

if ($action !== 'toggle') {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit;
}

try {
    $productCheck = $db->prepare("SELECT id FROM products WHERE id = ? AND status = 'approved'");
    $productCheck->bind_param("i", $productId);
    $productCheck->execute();

    if ($productCheck->get_result()->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        exit;
    }

    $checkQuery = "SELECT id FROM bookmarks WHERE user_id = ? AND product_id = ? AND bookmark_type = 'product'";
    $stmt = $db->prepare($checkQuery);
    $stmt->bind_param("ii", $userId, $productId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $deleteQuery = "DELETE FROM bookmarks WHERE user_id = ? AND product_id = ? AND bookmark_type = 'product'";
        $stmt = $db->prepare($deleteQuery);
        $stmt->bind_param("ii", $userId, $productId);
        $stmt->execute();

        echo json_encode([
            'success' => true,
            'bookmarked' => false,
            'message' => 'Removed from bookmarks'
        ]);
        exit;
    }

    $insertQuery = "INSERT INTO bookmarks (user_id, product_id, service_id, bookmark_type) VALUES (?, ?, NULL, 'product')";
    $stmt = $db->prepare($insertQuery);
    $stmt->bind_param("ii", $userId, $productId);
    $stmt->execute();

    echo json_encode([
        'success' => true,
        'bookmarked' => true,
        'message' => 'Added to bookmarks'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
