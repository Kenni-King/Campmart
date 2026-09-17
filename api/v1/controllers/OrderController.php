<?php
class OrderController {

    public static function place() {
        global $db;

        $userId = $GLOBALS['api_user']['id'];

        $stmt = $db->prepare("SELECT c.*, p.price, p.user_id as seller_id, p.title, p.availability, p.metadata
            FROM cart c
            JOIN products p ON c.product_id = p.id
            WHERE c.user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $cartResult = $stmt->get_result();

        if ($cartResult->num_rows === 0) {
            $stmt->close();
            errorResponse('Your cart is empty', 400);
        }

        $stmt = $db->prepare("SELECT phone, location FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $buyerPhone = $user['phone'] ?? '';
        $platformFeeRate = 0.05;
        $minPlatformFee = 500;

        $db->begin_transaction();

        try {
            $ordersCreated = [];
            $updateStock = $db->prepare("UPDATE products SET metadata = ?, availability = ? WHERE id = ?");

            while ($cartItem = $cartResult->fetch_assoc()) {
                $orderNumber = generateOrderNumber();
                $metadata = json_decode($cartItem['metadata'] ?? '{}', true) ?: [];
                $currentStock = $metadata['stock_quantity'] ?? 1;
                $remainingStock = $currentStock - (int) $cartItem['quantity'];
                $deliveryOption = $cartItem['delivery_option'] ?? 'pickup';
                $deliveryFee = ($deliveryOption === 'riders') ? ($metadata['delivery_fee'] ?? 1500.0) : 0;
                $deliveryLocation = $deliveryOption === 'riders'
                    ? ($user['location'] ?? 'Campus')
                    : 'Seller Store - Pickup';

                if ($cartItem['availability'] !== 'available' || $remainingStock < 0) {
                    throw new Exception('Insufficient stock for ' . $cartItem['title']);
                }

                $itemPrice = $cartItem['price'] * $cartItem['quantity'];
                $serviceFee = max($itemPrice * $platformFeeRate, $minPlatformFee);
                $totalAmount = $itemPrice + $serviceFee + $deliveryFee;

                $insertOrder = $db->prepare("INSERT INTO orders (
                    order_number, buyer_id, seller_id, product_id,
                    payment_method, delivery_location, buyer_phone,
                    item_price, service_fee, total_amount,
                    status, payment_status, delivery_status, created_at
                ) VALUES (?, ?, ?, ?, 'cash', ?, ?, ?, ?, ?, 'pending', 'pending', 'pending', NOW())");

                $insertOrder->bind_param("siiissddd",
                    $orderNumber, $userId, $cartItem['seller_id'], $cartItem['product_id'],
                    $deliveryLocation, $buyerPhone, $itemPrice, $serviceFee, $totalAmount
                );

                if (!$insertOrder->execute()) {
                    throw new Exception('Failed to create order for ' . $cartItem['title']);
                }
                $insertOrder->close();

                $updatedMetadata = json_encode([
                    'stock_quantity' => max(0, $remainingStock),
                    'stock_updated_at' => date('Y-m-d H:i:s'),
                    'delivery_fee' => $metadata['delivery_fee'] ?? 1500.0,
                    'delivery_fee_updated_at' => $metadata['delivery_fee_updated_at'] ?? date('Y-m-d H:i:s')
                ]);
                $nextAvailability = $remainingStock > 0 ? 'available' : 'sold';
                $updateStock->bind_param("ssi", $updatedMetadata, $nextAvailability, $cartItem['product_id']);
                $updateStock->execute();

                $ordersCreated[] = $orderNumber;
            }
            $updateStock->close();

            dbDelete('cart', ['user_id' => $userId]);
            $db->commit();

            successResponse([
                'order_numbers' => $ordersCreated,
                'orders_count' => count($ordersCreated)
            ], 'Order(s) placed successfully', 201);

        } catch (Exception $e) {
            $db->rollback();
            errorResponse($e->getMessage(), 400);
        }
    }

    public static function list() {
        global $db;

        $userId = $GLOBALS['api_user']['id'];
        $role = $_GET['role'] ?? 'buyer';
        $pagination = getPaginationParams();

        if ($role === 'seller') {
            $countStmt = $db->prepare("SELECT COUNT(*) as total FROM orders WHERE seller_id = ?");
        } else {
            $countStmt = $db->prepare("SELECT COUNT(*) as total FROM orders WHERE buyer_id = ?");
        }
        $countStmt->bind_param("i", $userId);
        $countStmt->execute();
        $total = $countStmt->get_result()->fetch_assoc()['total'];
        $countStmt->close();

        if ($role === 'seller') {
            $stmt = $db->prepare("SELECT o.*, p.title as product_title, pi.image_url as product_image,
                u.full_name as buyer_name, u.username as buyer_username
                FROM orders o
                JOIN products p ON o.product_id = p.id
                LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = TRUE
                JOIN users u ON o.buyer_id = u.id
                WHERE o.seller_id = ?
                ORDER BY o.created_at DESC
                LIMIT ? OFFSET ?");
        } else {
            $stmt = $db->prepare("SELECT o.*, p.title as product_title, pi.image_url as product_image,
                u.full_name as seller_name, u.username as seller_username
                FROM orders o
                JOIN products p ON o.product_id = p.id
                LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = TRUE
                JOIN users u ON o.seller_id = u.id
                WHERE o.buyer_id = ?
                ORDER BY o.created_at DESC
                LIMIT ? OFFSET ?");
        }

        $stmt->bind_param("iii", $userId, $pagination['per_page'], $pagination['offset']);
        $stmt->execute();
        $result = $stmt->get_result();

        $orders = [];
        while ($row = $result->fetch_assoc()) {
            $order = [
                'id' => (int) $row['id'],
                'order_number' => $row['order_number'],
                'status' => $row['status'],
                'payment_status' => $row['payment_status'],
                'delivery_status' => $row['delivery_status'],
                'item_price' => (float) $row['item_price'],
                'service_fee' => (float) $row['service_fee'],
                'total_amount' => (float) $row['total_amount'],
                'payment_method' => $row['payment_method'],
                'delivery_location' => $row['delivery_location'],
                'product' => [
                    'title' => $row['product_title'],
                    'image' => $row['product_image']
                ],
                'created_at' => $row['created_at']
            ];

            if ($role === 'seller') {
                $order['buyer'] = [
                    'full_name' => $row['buyer_name'],
                    'username' => $row['buyer_username']
                ];
            } else {
                $order['seller'] = [
                    'full_name' => $row['seller_name'],
                    'username' => $row['seller_username']
                ];
            }

            $orders[] = $order;
        }
        $stmt->close();

        paginatedResponse($orders, paginate($total, $pagination['page'], $pagination['per_page']));
    }

    public static function detail($id) {
        global $db;

        $orderId = (int) $id;
        $userId = $GLOBALS['api_user']['id'];

        $stmt = $db->prepare("SELECT o.*, p.title as product_title, p.description as product_description,
            pi.image_url as product_image, p.slug as product_slug,
            buyer.full_name as buyer_name, buyer.username as buyer_username,
            seller.full_name as seller_name, seller.username as seller_username
            FROM orders o
            JOIN products p ON o.product_id = p.id
            LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = TRUE
            JOIN users buyer ON o.buyer_id = buyer.id
            JOIN users seller ON o.seller_id = seller.id
            WHERE o.id = ? AND (o.buyer_id = ? OR o.seller_id = ?)");

        $stmt->bind_param("iii", $orderId, $userId, $userId);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$order) {
            errorResponse('Order not found', 404);
        }

        successResponse([
            'id' => (int) $order['id'],
            'order_number' => $order['order_number'],
            'status' => $order['status'],
            'payment_status' => $order['payment_status'],
            'delivery_status' => $order['delivery_status'],
            'payment_method' => $order['payment_method'],
            'delivery_location' => $order['delivery_location'],
            'item_price' => (float) $order['item_price'],
            'service_fee' => (float) $order['service_fee'],
            'total_amount' => (float) $order['total_amount'],
            'product' => [
                'title' => $order['product_title'],
                'description' => $order['product_description'],
                'image' => $order['product_image'],
                'slug' => $order['product_slug']
            ],
            'buyer' => [
                'full_name' => $order['buyer_name'],
                'username' => $order['buyer_username']
            ],
            'seller' => [
                'full_name' => $order['seller_name'],
                'username' => $order['seller_username']
            ],
            'created_at' => $order['created_at']
        ]);
    }
}
