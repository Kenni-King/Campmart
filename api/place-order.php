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
// Fetch all cart items for this user
$cart_query = $db->query("
    SELECT c.*, p.price, p.user_id as seller_id, p.title, p.availability, p.metadata
    FROM cart c
    JOIN products p ON c.product_id = p.id
    WHERE c.user_id = $userId
");

if ($cart_query->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Your cart is empty']);
    exit;
}

// Get user data for phone and address
$user_query = $db->query("SELECT phone, location FROM users WHERE id = $userId");
$user = $user_query->fetch_assoc();

$buyer_phone = $user['phone'] ?? '';

// Platform fee configuration
$platform_fee_rate = 0.05; // 5% platform fee
$min_platform_fee = 500;

// Start transaction
$db->begin_transaction();

try {
    $orders_created = [];
    $update_stock = $db->prepare("UPDATE products SET metadata = ?, availability = ? WHERE id = ?");
    
    while ($cart_item = $cart_query->fetch_assoc()) {
        // Generate unique order number
        $order_number = generateOrderNumber();
        $currentStock = getProductStockQuantity($cart_item);
        $remainingStock = $currentStock - (int) $cart_item['quantity'];
        $deliveryOption = $cart_item['delivery_option'] ?? 'pickup';
        $delivery_fee = getProductDeliveryFee($cart_item, $deliveryOption);
        $delivery_location = $deliveryOption === 'riders'
            ? ($user['location'] ?? 'Campus')
            : 'Seller Store - Pickup';

        if (($cart_item['availability'] ?? '') !== 'available' || $remainingStock < 0) {
            throw new Exception('Insufficient stock for ' . $cart_item['title']);
        }
        
        // Calculate fees
        $item_price = $cart_item['price'] * $cart_item['quantity'];
        $service_fee = max($item_price * $platform_fee_rate, $min_platform_fee);
        $total_amount = $item_price + $service_fee + $delivery_fee;
        
        // Insert order
        $insert_order = $db->prepare("
            INSERT INTO orders (
                order_number, buyer_id, seller_id, product_id, 
                payment_method, delivery_location, buyer_phone, delivery_option, delivery_fee,
                item_price, service_fee, total_amount,
                status, payment_status, delivery_status,
                created_at
            ) VALUES (?, ?, ?, ?, 'cash', ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', 'pending', NOW())
        ");
        
        $insert_order->bind_param(
            "siissssddd",
            $order_number,
            $userId,
            $cart_item['seller_id'],
            $cart_item['product_id'],
            $delivery_location,
            $buyer_phone,
            $deliveryOption,
            $delivery_fee,
            $item_price,
            $service_fee,
            $total_amount
        );
        
        if (!$insert_order->execute()) {
            throw new Exception('Failed to create order for ' . $cart_item['title']);
        }

        $updatedMetadata = setProductStockQuantity($cart_item['metadata'] ?? null, $remainingStock);
        $nextAvailability = $remainingStock > 0 ? 'available' : 'sold';
        $update_stock->bind_param("ssi", $updatedMetadata, $nextAvailability, $cart_item['product_id']);
        $update_stock->execute();
        
        $orders_created[] = $order_number;
    }
    
    // Clear cart after successful order creation
    $clear_cart = $db->query("DELETE FROM cart WHERE user_id = $userId");
    
    if (!$clear_cart) {
        throw new Exception('Failed to clear cart');
    }
    
    // Commit transaction
    $db->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Order(s) placed successfully',
        'order_number' => $orders_created[0], // Return first order number
        'orders_count' => count($orders_created)
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $db->rollback();
    
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
