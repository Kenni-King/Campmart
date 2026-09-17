<?php
session_start();
require_once '../includes/controller.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['userAppId'])) {
    echo json_encode(['success' => false, 'message' => 'Please login to continue']);
    exit;
}

if (isAccountBlocked()) {
    echo json_encode(['success' => false, 'message' => 'Your account has been suspended or banned. You cannot perform this action.']);
    exit;
}

$userId = $_SESSION['userAppId'];
$requestData = $_POST;
if (empty($requestData)) {
    $jsonBody = json_decode(file_get_contents('php://input'), true);
    if (is_array($jsonBody)) {
        $requestData = $jsonBody;
    }
}

$productId = intval($requestData['product_id'] ?? 0);
$quantity = intval($requestData['quantity'] ?? 1);
$deliveryOption = $requestData['delivery_option'] ?? 'pickup'; // Default to pickup if not provided
$buyNow = filter_var($requestData['buy_now'] ?? false, FILTER_VALIDATE_BOOLEAN);

// Validate inputs
if ($productId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product']);
    exit;
}

if ($quantity <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid quantity']);
    exit;
}

// Check if product exists and is available
$product_check = $db->query("SELECT id, user_id, title, price, availability, metadata FROM products WHERE id = $productId AND status = 'approved'");
if ($product_check->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Product not found or unavailable']);
    exit;
}

$product = $product_check->fetch_assoc();

// Check if user is trying to add their own product
if ($product['user_id'] == $userId) {
    echo json_encode(['success' => false, 'message' => 'You cannot add your own product to cart']);
    exit;
}

// Check if product is available
if ($product['availability'] !== 'available') {
    echo json_encode(['success' => false, 'message' => 'This product is no longer available']);
    exit;
}

$availableStock = getProductStockQuantity($product);
if ($availableStock < 1) {
    echo json_encode(['success' => false, 'message' => 'This product is out of stock']);
    exit;
}

// Check if item already exists in cart
$existing_cart = $db->query("SELECT id, quantity FROM cart WHERE user_id = $userId AND product_id = $productId");

if ($existing_cart->num_rows > 0) {
    // Update quantity
    $cart_item = $existing_cart->fetch_assoc();
    $new_quantity = $cart_item['quantity'] + $quantity;

    if ($new_quantity > $availableStock) {
        echo json_encode([
            'success' => false,
            'message' => 'Only ' . $availableStock . ' unit(s) currently available for this product'
        ]);
        exit;
    }
    
    $update = $db->query("UPDATE cart SET quantity = $new_quantity, delivery_option = '$deliveryOption', updated_at = NOW() WHERE id = {$cart_item['id']}");
    
    if ($update) {
        $message = $buyNow ? 'Product updated in cart' : 'Product quantity updated in cart!';
        echo json_encode([
            'success' => true, 
            'message' => $message,
            'buy_now' => $buyNow,
            'cart_count' => getCartCount($userId, $db)
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update cart']);
    }
} else {
    if ($quantity > $availableStock) {
        echo json_encode([
            'success' => false,
            'message' => 'Only ' . $availableStock . ' unit(s) currently available for this product'
        ]);
        exit;
    }

    // Insert new item
    $insert = $db->query("INSERT INTO cart (user_id, product_id, quantity, delivery_option, created_at) VALUES ($userId, $productId, $quantity, '$deliveryOption', NOW())");
    
    if ($insert) {
        $message = $buyNow ? 'Product added to cart' : 'Product added to cart successfully!';
        echo json_encode([
            'success' => true, 
            'message' => $message,
            'buy_now' => $buyNow,
            'cart_count' => getCartCount($userId, $db)
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to add to cart']);
    }
}

function getCartCount($userId, $db) {
    $result = $db->query("SELECT COUNT(*) as count FROM cart WHERE user_id = $userId");
    $row = $result->fetch_assoc();
    return $row['count'];
}
?>
