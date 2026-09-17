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

$user_id = $_SESSION['userAppId'];
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'update_quantity':
            $cart_id = intval($_POST['cart_id'] ?? 0);
            $quantity = intval($_POST['quantity'] ?? 1);

            if ($cart_id <= 0 || $quantity < 1) {
                echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
                exit;
            }

            // Verify cart item belongs to user and check current stock
            $check_query = "
                SELECT c.id, p.metadata, p.availability
                FROM cart c
                JOIN products p ON p.id = c.product_id
                WHERE c.id = ? AND c.user_id = ?
            ";
            $stmt = $db->prepare($check_query);
            $stmt->bind_param("ii", $cart_id, $user_id);
            $stmt->execute();
            $cart_item = $stmt->get_result()->fetch_assoc();
            
            if (!$cart_item) {
                echo json_encode(['success' => false, 'message' => 'Cart item not found']);
                exit;
            }

            $availableStock = getProductStockQuantity($cart_item);
            if (($cart_item['availability'] ?? '') !== 'available' || $availableStock < $quantity) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Only ' . $availableStock . ' unit(s) currently available for this product'
                ]);
                exit;
            }

            // Update quantity
            $update_query = "UPDATE cart SET quantity = ?, updated_at = NOW() WHERE id = ? AND user_id = ?";
            $stmt = $db->prepare($update_query);
            $stmt->bind_param("iii", $quantity, $cart_id, $user_id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Quantity updated']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update quantity']);
            }
            break;

        case 'update_delivery':
            $cart_id = intval($_POST['cart_id'] ?? 0);
            $delivery_option = $_POST['delivery_option'] ?? 'pickup';

            if ($cart_id <= 0 || !in_array($delivery_option, ['pickup', 'riders'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
                exit;
            }

            // Verify cart item belongs to user
            $check_query = "SELECT id FROM cart WHERE id = ? AND user_id = ?";
            $stmt = $db->prepare($check_query);
            $stmt->bind_param("ii", $cart_id, $user_id);
            $stmt->execute();
            
            if ($stmt->get_result()->num_rows === 0) {
                echo json_encode(['success' => false, 'message' => 'Cart item not found']);
                exit;
            }

            // Update delivery option
            $update_query = "UPDATE cart SET delivery_option = ?, updated_at = NOW() WHERE id = ? AND user_id = ?";
            $stmt = $db->prepare($update_query);
            $stmt->bind_param("sii", $delivery_option, $cart_id, $user_id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Delivery option updated']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update delivery option']);
            }
            break;

        case 'remove':
            $cart_id = intval($_POST['cart_id'] ?? 0);

            if ($cart_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid cart item']);
                exit;
            }

            // Delete cart item
            $delete_query = "DELETE FROM cart WHERE id = ? AND user_id = ?";
            $stmt = $db->prepare($delete_query);
            $stmt->bind_param("ii", $cart_id, $user_id);
            
            if ($stmt->execute()) {
                // Get updated cart count
                $count_query = "SELECT COUNT(*) as count FROM cart WHERE user_id = ?";
                $stmt = $db->prepare($count_query);
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $count_result = $stmt->get_result()->fetch_assoc();
                
                echo json_encode([
                    'success' => true, 
                    'message' => 'Item removed from cart',
                    'cart_count' => $count_result['count']
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to remove item']);
            }
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
?>
