<?php
class CartController {

    public static function get() {
        global $db;

        $userId = $GLOBALS['api_user']['id'];

        $stmt = $db->prepare("SELECT c.*, p.title, p.price, p.availability, p.metadata,
            u.full_name as seller_name, u.username as seller_username,
            pi.image_url as product_image
            FROM cart c
            JOIN products p ON c.product_id = p.id
            JOIN users u ON p.user_id = u.id
            LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = TRUE
            WHERE c.user_id = ?
            ORDER BY c.created_at DESC");

        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();

        $items = [];
        $subtotal = 0;
        $totalDeliveryFee = 0;

        while ($row = $result->fetch_assoc()) {
            $metadata = json_decode($row['metadata'] ?? '{}', true) ?: [];
            $deliveryOption = $row['delivery_option'] ?? 'pickup';
            $deliveryFee = ($deliveryOption === 'riders') ? ($metadata['delivery_fee'] ?? 1500.0) : 0;
            $itemTotal = $row['price'] * $row['quantity'];
            $subtotal += $itemTotal;
            $totalDeliveryFee += $deliveryFee;

            $items[] = [
                'id' => (int) $row['id'],
                'product_id' => (int) $row['product_id'],
                'quantity' => (int) $row['quantity'],
                'delivery_option' => $deliveryOption,
                'title' => $row['title'],
                'price' => (float) $row['price'],
                'item_total' => $itemTotal,
                'delivery_fee' => $deliveryFee,
                'availability' => $row['availability'],
                'stock_quantity' => $metadata['stock_quantity'] ?? 1,
                'product_image' => $row['product_image'],
                'seller' => [
                    'name' => $row['seller_name'],
                    'username' => $row['seller_username']
                ]
            ];
        }
        $stmt->close();

        $platformFee = max($subtotal * 0.05, 500);
        $total = $subtotal + $platformFee + $totalDeliveryFee;

        paginatedResponse($items, [
            'current_page' => 1,
            'per_page' => count($items),
            'total_items' => count($items),
            'total_pages' => 1,
            'has_next' => false,
            'has_prev' => false
        ], 'Cart fetched');
    }

    public static function add() {
        global $db;

        $userId = $GLOBALS['api_user']['id'];
        $data = getJsonInput();

        $productId = (int) ($data['product_id'] ?? 0);
        $quantity = max(1, (int) ($data['quantity'] ?? 1));
        $deliveryOption = in_array($data['delivery_option'] ?? '', ['riders', 'pickup']) ? $data['delivery_option'] : 'pickup';

        if (!$productId) {
            errorResponse('product_id is required', 422);
        }

        $stmt = $db->prepare("SELECT id, availability, metadata, price, user_id FROM products WHERE id = ?");
        $stmt->bind_param("i", $productId);
        $stmt->execute();
        $product = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$product) {
            errorResponse('Product not found', 404);
        }

        if ($product['availability'] !== 'available') {
            errorResponse('Product is not available', 400);
        }

        if ($product['user_id'] == $userId) {
            errorResponse('Cannot add your own product to cart', 400);
        }

        $metadata = json_decode($product['metadata'] ?? '{}', true) ?: [];
        $stock = $metadata['stock_quantity'] ?? 1;

        if ($quantity > $stock) {
            errorResponse('Insufficient stock. Available: ' . $stock, 400);
        }

        $stmt = $db->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
        $stmt->bind_param("ii", $userId, $productId);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($existing) {
            $newQty = $existing['quantity'] + $quantity;
            if ($newQty > $stock) {
                errorResponse('Total quantity exceeds stock. Available: ' . $stock, 400);
            }
            dbUpdate('cart', [
                'quantity' => $newQty,
                'delivery_option' => $deliveryOption
            ], ['id' => $existing['id']]);
            $cartId = $existing['id'];
        } else {
            $cartId = dbInsert('cart', [
                'user_id' => $userId,
                'product_id' => $productId,
                'quantity' => $quantity,
                'delivery_option' => $deliveryOption
            ]);
        }

        if (!$cartId) {
            errorResponse('Failed to add to cart', 500);
        }

        $stmt = $db->prepare("SELECT COUNT(*) as total FROM cart WHERE user_id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $cartCount = $stmt->get_result()->fetch_assoc()['total'];
        $stmt->close();

        successResponse(['cart_id' => (int) $cartId, 'cart_count' => (int) $cartCount], 'Added to cart', 201);
    }

    public static function update($id) {
        global $db;

        $cartId = (int) $id;
        $userId = $GLOBALS['api_user']['id'];
        $data = getJsonInput();

        $stmt = $db->prepare("SELECT c.*, p.metadata, p.availability FROM cart c JOIN products p ON c.product_id = p.id WHERE c.id = ? AND c.user_id = ?");
        $stmt->bind_param("ii", $cartId, $userId);
        $stmt->execute();
        $cartItem = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$cartItem) {
            errorResponse('Cart item not found', 404);
        }

        $updateData = [];

        if (isset($data['quantity'])) {
            $quantity = max(1, (int) $data['quantity']);
            $metadata = json_decode($cartItem['metadata'] ?? '{}', true) ?: [];
            $stock = $metadata['stock_quantity'] ?? 1;

            if ($quantity > $stock) {
                errorResponse('Quantity exceeds stock. Available: ' . $stock, 400);
            }

            $updateData['quantity'] = $quantity;
        }

        if (isset($data['delivery_option'])) {
            $updateData['delivery_option'] = in_array($data['delivery_option'], ['riders', 'pickup']) ? $data['delivery_option'] : 'pickup';
        }

        if (empty($updateData)) {
            errorResponse('No fields to update', 422);
        }

        dbUpdate('cart', $updateData, ['id' => $cartId]);
        successResponse(null, 'Cart updated');
    }

    public static function remove($id) {
        global $db;

        $cartId = (int) $id;
        $userId = $GLOBALS['api_user']['id'];

        $stmt = $db->prepare("SELECT id FROM cart WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $cartId, $userId);
        $stmt->execute();
        if ($stmt->get_result()->num_rows === 0) {
            $stmt->close();
            errorResponse('Cart item not found', 404);
        }
        $stmt->close();

        $result = dbDelete('cart', ['id' => $cartId]);
        if ($result !== 'Successfully Deleted') {
            errorResponse('Failed to remove item', 500);
        }

        successResponse(null, 'Item removed from cart');
    }
}
