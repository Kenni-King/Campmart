<?php
session_start();
require_once 'includes/controller.php';

if (!isset($_SESSION['userAppId'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['userAppId'];

function orderPersonName(array $row, string $prefix): string
{
    $fullName = trim((string) ($row[$prefix . '_full_name'] ?? ''));
    if ($fullName !== '') {
        return $fullName;
    }

    $firstName = trim((string) ($row[$prefix . '_firstname'] ?? ''));
    $lastName = trim((string) ($row[$prefix . '_lastname'] ?? ''));
    $fallback = trim($firstName . ' ' . $lastName);
    if ($fallback !== '') {
        return $fallback;
    }

    return (string) ($row[$prefix . '_username'] ?? 'User');
}

function orderStatusClasses(string $status, string $type = 'status'): string
{
    $map = [
        'status' => [
            'pending' => 'bg-amber-50 text-amber-700 border-amber-200',
            'confirmed' => 'bg-blue-50 text-blue-700 border-blue-200',
            'processing' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
            'completed' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'cancelled' => 'bg-red-50 text-red-700 border-red-200',
            'refunded' => 'bg-rose-50 text-rose-700 border-rose-200',
        ],
        'payment' => [
            'pending' => 'bg-amber-50 text-amber-700 border-amber-200',
            'paid' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'failed' => 'bg-red-50 text-red-700 border-red-200',
            'refunded' => 'bg-rose-50 text-rose-700 border-rose-200',
        ],
        'delivery' => [
            'pending' => 'bg-slate-100 text-slate-700 border-slate-200',
            'in_transit' => 'bg-sky-50 text-sky-700 border-sky-200',
            'delivered' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
            'returned' => 'bg-red-50 text-red-700 border-red-200',
        ],
    ];

    return $map[$type][$status] ?? 'bg-slate-100 text-slate-700 border-slate-200';
}

function formatOrderValue(string $value): string
{
    return ucwords(str_replace('_', ' ', $value));
}

function orderStars(int $rating): string
{
    $rating = max(0, min(5, $rating));
    $html = '';
    for ($i = 1; $i <= 5; $i++) {
        $html .= '<span class="material-symbols-outlined text-[18px]">' . ($i <= $rating ? 'star' : 'star_outline') . '</span>';
    }
    return $html;
}

$allowedOrderStatuses = ['pending', 'confirmed', 'processing', 'completed', 'cancelled', 'refunded'];
$allowedPaymentStatuses = ['pending', 'paid', 'failed', 'refunded'];
$allowedDeliveryStatuses = ['pending', 'in_transit', 'delivered', 'returned'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_order_review'])) {
    if (isAccountBlocked()) {
        $_SESSION['error'] = 'Your account has been suspended or banned. You cannot perform this action.';
        header('Location: my-orders.php');
        exit;
    }
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = 'Your session token is invalid. Please refresh the page and try again.';
        header('Location: my-orders.php');
        exit;
    }

    $orderId = (int) ($_POST['order_id'] ?? 0);
    $rating = (int) ($_POST['rating'] ?? 0);
    $reviewText = trim((string) ($_POST['review_text'] ?? ''));

    if ($orderId < 1 || $rating < 1 || $rating > 5 || $reviewText === '') {
        $_SESSION['error'] = 'Please choose a star rating and add a short comment about your purchase.';
        header('Location: my-orders.php');
        exit;
    }

    $reviewOrderStmt = $db->prepare("
        SELECT o.id, o.product_id, o.buyer_id, o.seller_id, o.status, o.delivery_status, p.title
        FROM orders o
        JOIN products p ON p.id = o.product_id
        WHERE o.id = ? AND o.buyer_id = ?
        LIMIT 1
    ");
    $reviewOrderStmt->bind_param('ii', $orderId, $userId);
    $reviewOrderStmt->execute();
    $reviewOrder = $reviewOrderStmt->get_result()->fetch_assoc();

    if (!$reviewOrder) {
        $_SESSION['error'] = 'Order not found or access denied.';
        header('Location: my-orders.php');
        exit;
    }

    $isCompletedPurchase = ($reviewOrder['status'] ?? '') === 'completed' || ($reviewOrder['delivery_status'] ?? '') === 'delivered';
    if (!$isCompletedPurchase) {
        $_SESSION['error'] = 'You can only rate a purchase after the transaction is completed.';
        header('Location: my-orders.php');
        exit;
    }

    $existingReviewStmt = $db->prepare("
        SELECT id
        FROM reviews
        WHERE reviewer_id = ? AND reviewed_user_id = ? AND product_id = ? AND review_type = 'seller'
        LIMIT 1
    ");
    $existingReviewStmt->bind_param('iii', $userId, $reviewOrder['seller_id'], $reviewOrder['product_id']);
    $existingReviewStmt->execute();
    $existingReview = $existingReviewStmt->get_result()->fetch_assoc();

    if ($existingReview) {
        $_SESSION['error'] = 'You have already reviewed this purchase.';
        header('Location: my-orders.php');
        exit;
    }

    $db->begin_transaction();

    try {
        $insertReviewStmt = $db->prepare("
            INSERT INTO reviews (
                reviewer_id,
                reviewed_user_id,
                product_id,
                review_type,
                rating,
                review_text,
                is_verified_purchase,
                status
            ) VALUES (?, ?, ?, 'seller', ?, ?, 1, 'approved')
        ");
        $insertReviewStmt->bind_param(
            'iiiis',
            $userId,
            $reviewOrder['seller_id'],
            $reviewOrder['product_id'],
            $rating,
            $reviewText
        );
        $insertReviewStmt->execute();

        $notificationTitle = 'New product review';
        $notificationMessage = 'A buyer left a ' . $rating . '-star review for ' . $reviewOrder['title'] . '.';
        $reviewNotificationStmt = $db->prepare("
            INSERT INTO notifications (
                user_id,
                title,
                message,
                type,
                related_id,
                related_type,
                action_url
            ) VALUES (?, ?, ?, 'product', ?, 'review', ?)
        ");
        $productProfileUrl = 'product-profile.php?id=' . (int) $reviewOrder['product_id'];
        $reviewedProductId = (int) $reviewOrder['product_id'];
        $reviewNotificationStmt->bind_param('issis', $reviewOrder['seller_id'], $notificationTitle, $notificationMessage, $reviewedProductId, $productProfileUrl);
        $reviewNotificationStmt->execute();

        $db->commit();
        $_SESSION['success'] = 'Thanks for rating your purchase. Your review is now visible to the vendor.';
    } catch (Throwable $exception) {
        $db->rollback();
        $_SESSION['error'] = 'Failed to save your review: ' . $exception->getMessage();
    }

    header('Location: my-orders.php');
    exit;
}

// Buyer marks order as completed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['buyer_mark_completed'])) {
    if (isAccountBlocked()) {
        $_SESSION['error'] = 'Your account has been suspended or banned.';
        header('Location: my-orders.php');
        exit;
    }
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = 'Your session token is invalid. Please refresh the page and try again.';
        header('Location: my-orders.php');
        exit;
    }

    $orderId = (int) ($_POST['order_id'] ?? 0);
    if ($orderId < 1) {
        $_SESSION['error'] = 'Invalid order.';
        header('Location: my-orders.php');
        exit;
    }

    $orderStmt = $db->prepare("
        SELECT o.*, p.title
        FROM orders o
        JOIN products p ON p.id = o.product_id
        WHERE o.id = ? AND o.buyer_id = ?
        LIMIT 1
    ");
    $orderStmt->bind_param('ii', $orderId, $userId);
    $orderStmt->execute();
    $theOrder = $orderStmt->get_result()->fetch_assoc();

    if (!$theOrder) {
        $_SESSION['error'] = 'Order not found or access denied.';
        header('Location: my-orders.php');
        exit;
    }

    $isOnlinePayment = in_array($theOrder['payment_gateway'] ?? '', ['paystack', 'flutterwave']);

    $db->begin_transaction();
    try {
        $db->query("UPDATE orders SET buyer_completed = 1, buyer_confirmation = 1 WHERE id = $orderId");

        // If seller already completed, notify admin for escrow release
        if (!empty($theOrder['seller_completed']) && $isOnlinePayment) {
            $notifStmt = $db->prepare("
                INSERT INTO notifications (user_id, title, message, type, related_id, related_type, action_url)
                VALUES (1, 'Escrow Release Ready', ?, 'transaction', ?, 'order', 'admin-transactions.php')
            ");
            $msg = "Both parties have completed order #{$theOrder['order_number']}. Escrow payment is ready for release.";
            $notifStmt->bind_param('si', $msg, $orderId);
            $notifStmt->execute();

            $_SESSION['success'] = 'You have marked this order as completed. Both parties have agreed, and admin has been notified to release the payment.';
        } elseif (!$isOnlinePayment) {
            // For POD, mark as completed directly
            $db->query("UPDATE orders SET status = 'completed', completed_at = NOW() WHERE id = $orderId AND status != 'completed'");
            $_SESSION['success'] = 'You have marked this order as completed. Thank you for your purchase!';
        } else {
            $_SESSION['success'] = 'You have marked this order as completed. Waiting for the seller to also mark it as completed.';
        }

        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        $_SESSION['error'] = 'Failed to update order: ' . $e->getMessage();
    }

    header('Location: my-orders.php');
    exit;
}

// Seller marks order as completed
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['seller_mark_completed'])) {
    if (isAccountBlocked()) {
        $_SESSION['error'] = 'Your account has been suspended or banned.';
        header('Location: my-orders.php');
        exit;
    }
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = 'Your session token is invalid. Please refresh the page and try again.';
        header('Location: my-orders.php');
        exit;
    }

    $orderId = (int) ($_POST['order_id'] ?? 0);
    if ($orderId < 1) {
        $_SESSION['error'] = 'Invalid order.';
        header('Location: my-orders.php');
        exit;
    }

    $orderStmt = $db->prepare("
        SELECT o.*, p.title
        FROM orders o
        JOIN products p ON p.id = o.product_id
        WHERE o.id = ? AND o.seller_id = ?
        LIMIT 1
    ");
    $orderStmt->bind_param('ii', $orderId, $userId);
    $orderStmt->execute();
    $theOrder = $orderStmt->get_result()->fetch_assoc();

    if (!$theOrder) {
        $_SESSION['error'] = 'Order not found or access denied.';
        header('Location: my-orders.php');
        exit;
    }

    $isOnlinePayment = in_array($theOrder['payment_gateway'] ?? '', ['paystack', 'flutterwave']);

    $db->begin_transaction();
    try {
        $db->query("UPDATE orders SET seller_completed = 1, seller_confirmation = 1 WHERE id = $orderId");

        // If buyer already completed, notify admin for escrow release
        if (!empty($theOrder['buyer_completed']) && $isOnlinePayment) {
            $notifStmt = $db->prepare("
                INSERT INTO notifications (user_id, title, message, type, related_id, related_type, action_url)
                VALUES (1, 'Escrow Release Ready', ?, 'transaction', ?, 'order', 'admin-transactions.php')
            ");
            $msg = "Both parties have completed order #{$theOrder['order_number']}. Escrow payment is ready for release.";
            $notifStmt->bind_param('si', $msg, $orderId);
            $notifStmt->execute();

            $_SESSION['success'] = 'Order completed. Both parties have agreed. Admin will release the payment shortly.';
        } elseif (!$isOnlinePayment) {
            $db->query("UPDATE orders SET status = 'completed', completed_at = NOW() WHERE id = $orderId AND status != 'completed'");
            $_SESSION['success'] = 'Order marked as completed.';
        } else {
            $_SESSION['success'] = 'Order marked as completed. Waiting for the buyer to also confirm.';
        }

        $db->commit();
    } catch (Throwable $e) {
        $db->rollback();
        $_SESSION['error'] = 'Failed to update order: ' . $e->getMessage();
    }

    header('Location: my-orders.php');
    exit;
}

// Seller assigns a rider
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_rider'])) {
    if (isAccountBlocked()) {
        $_SESSION['error'] = 'Your account has been suspended or banned.';
        header('Location: my-orders.php');
        exit;
    }
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = 'Invalid session token.';
        header('Location: my-orders.php');
        exit;
    }

    $orderId = (int) ($_POST['order_id'] ?? 0);
    $riderId = (int) ($_POST['rider_id'] ?? 0);

    $check = $db->query("
        SELECT 1 FROM orders WHERE id = $orderId AND seller_id = $userId
    ");
    if (!$check || !$check->num_rows) {
        $_SESSION['error'] = 'Order not found.';
        header('Location: my-orders.php');
        exit;
    }

    $db->begin_transaction();
    try {
        $db->query("UPDATE delivery_tasks SET status = 'assigned' WHERE order_id = $orderId AND rider_id = $riderId AND status = 'interested'");
        $db->query("UPDATE delivery_tasks SET status = 'cancelled' WHERE order_id = $orderId AND rider_id != $riderId AND status = 'interested'");
        $db->query("INSERT INTO notifications (user_id, title, message, type, related_id, related_type, created_at)
                    VALUES ($riderId, 'Delivery Assigned', 'You have been assigned to deliver order #$orderId', 'system', $orderId, 'order', NOW())");
        $db->commit();
        $_SESSION['success'] = 'Rider assigned successfully.';
    } catch (Throwable $e) {
        $db->rollback();
        $_SESSION['error'] = 'Failed to assign rider: ' . $e->getMessage();
    }

    header('Location: my-orders.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vendor_update_order'])) {
    if (isAccountBlocked()) {
        $_SESSION['error'] = 'Your account has been suspended or banned. You cannot perform this action.';
        header('Location: my-orders.php');
        exit;
    }
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $_SESSION['error'] = 'Your session token is invalid. Please refresh the page and try again.';
        header('Location: my-orders.php');
        exit;
    }

    $orderId = (int) ($_POST['order_id'] ?? 0);
    $orderStatus = (string) ($_POST['status'] ?? 'pending');
    $paymentStatus = (string) ($_POST['payment_status'] ?? 'pending');
    $deliveryStatus = (string) ($_POST['delivery_status'] ?? 'pending');
    $vendorNote = trim((string) ($_POST['vendor_note'] ?? ''));

    if (
        $orderId < 1 ||
        !in_array($orderStatus, $allowedOrderStatuses, true) ||
        !in_array($paymentStatus, $allowedPaymentStatuses, true) ||
        !in_array($deliveryStatus, $allowedDeliveryStatuses, true)
    ) {
        $_SESSION['error'] = 'Please choose valid order update values.';
        header('Location: my-orders.php');
        exit;
    }

    $orderStmt = $db->prepare("
        SELECT o.id, o.product_id, o.buyer_id, o.seller_id, o.notes, o.status AS current_status, p.title, p.metadata, p.availability
        FROM orders o
        JOIN products p ON p.id = o.product_id
        WHERE o.id = ? AND o.seller_id = ?
        LIMIT 1
    ");
    $orderStmt->bind_param('ii', $orderId, $userId);
    $orderStmt->execute();
    $managedOrder = $orderStmt->get_result()->fetch_assoc();

    if (!$managedOrder) {
        $_SESSION['error'] = 'Order not found or you do not have permission to update it.';
        header('Location: my-orders.php');
        exit;
    }

    $db->begin_transaction();

    try {
        $existingNotes = trim((string) ($managedOrder['notes'] ?? ''));
        $compiledNotes = $existingNotes;
        if ($vendorNote !== '') {
            $compiledNotes .= ($compiledNotes !== '' ? "\n" : '') . 'Vendor update: ' . $vendorNote;
        }

        $orderedQuantity = getOrderQuantityFromNotes($managedOrder['notes'] ?? '');
        $currentStock = getProductStockQuantity($managedOrder);

        $completedAt = null;
        $cancelledAt = null;
        $cancellationReason = null;

        if ($orderStatus === 'completed') {
            $completedAt = date('Y-m-d H:i:s');
        }

        if ($orderStatus === 'cancelled') {
            $cancelledAt = date('Y-m-d H:i:s');
            $cancellationReason = $vendorNote !== '' ? $vendorNote : 'Cancelled by vendor';
        }

        $updateStmt = $db->prepare("
            UPDATE orders
            SET
                status = ?,
                payment_status = ?,
                delivery_status = ?,
                notes = ?,
                seller_confirmation = 1,
                completed_at = ?,
                cancelled_at = ?,
                cancellation_reason = ?
            WHERE id = ? AND seller_id = ?
        ");
        $updateStmt->bind_param(
            'sssssssii',
            $orderStatus,
            $paymentStatus,
            $deliveryStatus,
            $compiledNotes,
            $completedAt,
            $cancelledAt,
            $cancellationReason,
            $orderId,
            $userId
        );
        $updateStmt->execute();

        if ($orderStatus === 'completed') {
            $remainingAvailability = $currentStock > 0 ? 'available' : 'sold';
            $productUpdate = $db->prepare("
                UPDATE products
                SET availability = ?, status = CASE WHEN ? = 'sold' THEN 'sold' ELSE status END, sold_at = CASE WHEN ? = 'sold' THEN NOW() ELSE sold_at END
                WHERE id = ?
            ");
            $productUpdate->bind_param('sssi', $remainingAvailability, $remainingAvailability, $remainingAvailability, $managedOrder['product_id']);
            $productUpdate->execute();
        } elseif ($orderStatus === 'cancelled') {
            $restoredStock = $currentStock;
            if (($managedOrder['current_status'] ?? '') !== 'cancelled') {
                $restoredStock += $orderedQuantity;
            }
            $restoredMetadata = setProductStockQuantity($managedOrder['metadata'] ?? null, $restoredStock);
            $productUpdate = $db->prepare("
                UPDATE products
                SET availability = 'available', sold_at = NULL, status = CASE WHEN status = 'sold' THEN 'approved' ELSE status END, metadata = ?
                WHERE id = ?
            ");
            $productUpdate->bind_param('si', $restoredMetadata, $managedOrder['product_id']);
            $productUpdate->execute();
        } else {
            $remainingAvailability = $currentStock > 0 ? 'available' : 'sold';
            $productUpdate = $db->prepare("
                UPDATE products
                SET availability = ?
                WHERE id = ?
            ");
            $productUpdate->bind_param('si', $remainingAvailability, $managedOrder['product_id']);
            $productUpdate->execute();
        }

        $notificationTitle = 'Order update';
        $notificationMessage = 'Your order for ' . $managedOrder['title'] . ' is now ' . formatOrderValue($orderStatus) . '.';
        $notificationStmt = $db->prepare("
            INSERT INTO notifications (
                user_id,
                title,
                message,
                type,
                related_id,
                related_type,
                action_url
            ) VALUES (?, ?, ?, 'transaction', ?, 'order', 'my-orders.php')
        ");
        $notificationStmt->bind_param('issi', $managedOrder['buyer_id'], $notificationTitle, $notificationMessage, $orderId);
        $notificationStmt->execute();

        $db->commit();

        $_SESSION['success'] = 'Order #' . $orderId . ' updated successfully.';
    } catch (Throwable $exception) {
        $db->rollback();
        $_SESSION['error'] = 'Failed to update order: ' . $exception->getMessage();
    }

    header('Location: my-orders.php');
    exit;
}

$flashSuccess = $_SESSION['success'] ?? null;
$flashError = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

$purchaseQuery = "
    SELECT
        o.*,
        p.title AS product_title,
        p.slug AS product_slug,
        pi.image_url AS product_image,
        s.username AS seller_username,
        s.firstname AS seller_firstname,
        s.lastname AS seller_lastname,
        s.full_name AS seller_full_name,
        s.is_verified AS seller_is_verified
    FROM orders o
    JOIN products p ON p.id = o.product_id
    LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
    JOIN users s ON s.id = o.seller_id
    WHERE o.buyer_id = ?
    ORDER BY o.created_at DESC
";
$purchaseStmt = $db->prepare($purchaseQuery);
$purchaseStmt->bind_param('i', $userId);
$purchaseStmt->execute();
$purchaseResult = $purchaseStmt->get_result();
$purchaseOrders = [];
while ($row = $purchaseResult->fetch_assoc()) {
    $purchaseOrders[] = $row;
}

$purchaseReviewMap = [];
if (!empty($purchaseOrders)) {
    $purchaseProductIds = array_values(array_unique(array_map(static fn($order) => (int) $order['product_id'], $purchaseOrders)));
    if (!empty($purchaseProductIds)) {
        $productIdsSql = implode(',', $purchaseProductIds);
        $existingPurchaseReviewsStmt = $db->prepare("
            SELECT reviewer_id, reviewed_user_id, product_id, rating, review_text, created_at
            FROM reviews
            WHERE reviewer_id = ? AND review_type = 'seller' AND product_id IN ($productIdsSql)
        ");
        $existingPurchaseReviewsStmt->bind_param('i', $userId);
        $existingPurchaseReviewsStmt->execute();
        $existingPurchaseReviewsResult = $existingPurchaseReviewsStmt->get_result();

        while ($reviewRow = $existingPurchaseReviewsResult->fetch_assoc()) {
            $purchaseReviewMap[(int) $reviewRow['product_id']] = $reviewRow;
        }
    }
}

$salesQuery = "
    SELECT
        o.*,
        p.title AS product_title,
        p.slug AS product_slug,
        pi.image_url AS product_image,
        b.username AS buyer_username,
        b.firstname AS buyer_firstname,
        b.lastname AS buyer_lastname,
        b.full_name AS buyer_full_name
    FROM orders o
    JOIN products p ON p.id = o.product_id
    LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
    JOIN users b ON b.id = o.buyer_id
    WHERE o.seller_id = ?
    ORDER BY o.created_at DESC
";
$salesStmt = $db->prepare($salesQuery);
$salesStmt->bind_param('i', $userId);
$salesStmt->execute();
$salesResult = $salesStmt->get_result();
$salesOrders = [];
$salesOrderIds = [];
while ($row = $salesResult->fetch_assoc()) {
    $salesOrders[] = $row;
    $salesOrderIds[] = (int) $row['id'];
}

$riderInterestMap = [];
$assignedRiderMap = [];
if (!empty($salesOrderIds)) {
    $idsSql = implode(',', $salesOrderIds);
    $riderData = $db->query("
        SELECT dt.*, u.full_name as rider_name, u.phone as rider_phone
        FROM delivery_tasks dt
        JOIN users u ON dt.rider_id = u.id
        WHERE dt.order_id IN ($idsSql) AND dt.status IN ('interested','assigned','picked_up','delivered')
        ORDER BY dt.created_at DESC
    ");
    if ($riderData) {
        while ($rd = $riderData->fetch_assoc()) {
            $oid = (int) $rd['order_id'];
            if ($rd['status'] === 'assigned' || $rd['status'] === 'picked_up' || $rd['status'] === 'delivered') {
                $assignedRiderMap[$oid] = $rd;
            } else {
                $riderInterestMap[$oid][] = $rd;
            }
        }
    }
}

$purchaseTotal = count($purchaseOrders);
$salesTotal = count($salesOrders);
$pendingSales = 0;
$deliveredPurchases = 0;

foreach ($salesOrders as $order) {
    if (($order['status'] ?? '') !== 'completed' && ($order['status'] ?? '') !== 'cancelled') {
        $pendingSales++;
    }
}

foreach ($purchaseOrders as $order) {
    if (($order['delivery_status'] ?? '') === 'delivered') {
        $deliveredPurchases++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <base href="<?php echo SITE_URL; ?>">
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>My Orders | CampMart</title>
    <script src="tailwind34.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />
    <script id="tailwind-config">
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#064E3B',
                        secondary: '#F97316',
                        canvas: '#F8FAFC',
                    },
                    fontFamily: {
                        display: ['Inter']
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 450, 'GRAD' 0, 'opsz' 24;
        }
    </style>
</head>
<body class="bg-canvas min-h-screen text-slate-900">
    <?php include_once 'includes/header.php'; ?>

    <main class="max-w-[1440px] mx-auto px-4 sm:px-6 py-6 sm:py-8">
        <div class="mb-6 sm:mb-8">
            <span class="inline-flex items-center gap-2 rounded-full bg-primary/10 px-3 py-1 text-[11px] sm:text-xs font-semibold text-primary mb-3">
                <span class="material-symbols-outlined text-sm">receipt_long</span>
                Customer and vendor order center
            </span>
            <h1 class="text-2xl sm:text-3xl lg:text-4xl font-extrabold tracking-tight text-primary">My Orders</h1>
            <p class="text-sm sm:text-base text-slate-600 mt-2 max-w-3xl">
                Track your purchases and manage every order you receive as a vendor from one place.
            </p>
        </div>

        <?php if ($flashSuccess): ?>
            <div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                <?php echo htmlspecialchars($flashSuccess); ?>
            </div>
        <?php endif; ?>

        <?php if ($flashError): ?>
            <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <?php echo htmlspecialchars($flashError); ?>
            </div>
        <?php endif; ?>

        <section class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6 sm:mb-8">
            <div class="rounded-[24px] border border-slate-200 bg-white p-4 sm:p-5 shadow-sm">
                <p class="text-xs uppercase tracking-[0.18em] text-slate-400 font-bold">Purchases</p>
                <p class="mt-3 text-2xl font-extrabold text-slate-900"><?php echo $purchaseTotal; ?></p>
            </div>
            <div class="rounded-[24px] border border-slate-200 bg-white p-4 sm:p-5 shadow-sm">
                <p class="text-xs uppercase tracking-[0.18em] text-slate-400 font-bold">Delivered</p>
                <p class="mt-3 text-2xl font-extrabold text-emerald-700"><?php echo $deliveredPurchases; ?></p>
            </div>
            <div class="rounded-[24px] border border-slate-200 bg-white p-4 sm:p-5 shadow-sm">
                <p class="text-xs uppercase tracking-[0.18em] text-slate-400 font-bold">Sales Orders</p>
                <p class="mt-3 text-2xl font-extrabold text-slate-900"><?php echo $salesTotal; ?></p>
            </div>
            <div class="rounded-[24px] border border-slate-200 bg-white p-4 sm:p-5 shadow-sm">
                <p class="text-xs uppercase tracking-[0.18em] text-slate-400 font-bold">Pending Sales</p>
                <p class="mt-3 text-2xl font-extrabold text-secondary"><?php echo $pendingSales; ?></p>
            </div>
        </section>

        <section class="mb-8">
            <div class="flex items-center justify-between gap-4 mb-4">
                <div>
                    <h2 class="text-xl sm:text-2xl font-bold text-slate-900">My Purchases</h2>
                    <p class="text-sm text-slate-500 mt-1">See the latest payment and delivery progress for the products you ordered.</p>
                </div>
            </div>

            <?php if (empty($purchaseOrders)): ?>
                <div class="rounded-[28px] border border-dashed border-slate-300 bg-white p-8 sm:p-10 text-center shadow-sm">
                    <span class="material-symbols-outlined text-5xl text-slate-300">shopping_bag</span>
                    <h3 class="mt-4 text-lg font-bold text-slate-900">No purchases yet</h3>
                    <p class="mt-2 text-sm text-slate-500">Once you complete checkout, your orders will appear here with live status updates from each vendor.</p>
                    <a class="mt-5 inline-flex items-center gap-2 rounded-2xl bg-primary px-5 py-3 text-sm font-bold text-white transition hover:bg-primary/90" href="products.php">
                        <span class="material-symbols-outlined text-base">storefront</span>
                        Browse products
                    </a>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($purchaseOrders as $order): ?>
                        <?php $orderImage = $order['product_image'] ?: 'https://via.placeholder.com/320x320?text=No+Image'; ?>
                        <?php
                        $existingReview = $purchaseReviewMap[(int) $order['product_id']] ?? null;
                        $canLeaveReview = (($order['status'] ?? '') === 'completed' || ($order['delivery_status'] ?? '') === 'delivered');
                        ?>
                        <article class="rounded-[28px] border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
                            <div class="flex flex-col lg:flex-row gap-5">
                                <img
                                    alt="<?php echo htmlspecialchars($order['product_title']); ?>"
                                    class="h-32 w-full lg:w-32 rounded-[22px] object-cover border border-slate-200"
                                    src="<?php echo htmlspecialchars($orderImage); ?>"
                                />
                                <div class="flex-1 min-w-0">
                                    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
                                        <div>
                                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                                <span class="rounded-full bg-slate-100 px-3 py-1 text-[11px] font-bold tracking-wide text-slate-600">
                                                    <?php echo htmlspecialchars($order['order_number'] ?: ('#' . $order['id'])); ?>
                                                </span>
                                                <span class="rounded-full border px-3 py-1 text-[11px] font-semibold <?php echo orderStatusClasses((string) $order['status'], 'status'); ?>">
                                                    <?php echo htmlspecialchars(formatOrderValue((string) $order['status'])); ?>
                                                </span>
                                                <span class="rounded-full border px-3 py-1 text-[11px] font-semibold <?php echo orderStatusClasses((string) $order['payment_status'], 'payment'); ?>">
                                                    Payment: <?php echo htmlspecialchars(formatOrderValue((string) $order['payment_status'])); ?>
                                                </span>
                                                <span class="rounded-full border px-3 py-1 text-[11px] font-semibold <?php echo orderStatusClasses((string) $order['delivery_status'], 'delivery'); ?>">
                                                    Delivery: <?php echo htmlspecialchars(formatOrderValue((string) $order['delivery_status'])); ?>
                                                </span>
                                            </div>

                                            <a class="block text-lg font-bold text-slate-900 hover:text-primary" href="product/<?php echo htmlspecialchars($order['product_slug']); ?>">
                                                <?php echo htmlspecialchars($order['product_title']); ?>
                                            </a>
                                            <p class="mt-1 text-sm text-slate-500">
                                                Vendor:
                                                <a class="font-semibold text-slate-700 hover:text-primary" href="store/<?php echo htmlspecialchars($order['seller_username']); ?>">
                                                    <?php echo htmlspecialchars(orderPersonName($order, 'seller')); ?>
                                                </a>
                                                <?php if (!empty($order['seller_is_verified'])): ?><span class="material-symbols-outlined text-blue-500 text-xs fill-1 align-middle" title="Verified Seller">verified</span><?php endif; ?>
                                            </p>
                                            <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm text-slate-600">
                                                <div class="rounded-2xl bg-slate-50 px-4 py-3">
                                                    <p class="text-xs uppercase tracking-wide text-slate-400 font-bold mb-1">Delivery location</p>
                                                    <p><?php echo htmlspecialchars($order['delivery_location']); ?></p>
                                                </div>
                                                <div class="rounded-2xl bg-slate-50 px-4 py-3">
                                                    <p class="text-xs uppercase tracking-wide text-slate-400 font-bold mb-1">Payment method</p>
                                                    <p><?php echo htmlspecialchars(formatPaymentMethodDisplay($order)); ?></p>
                                                    <?php if ($order['store_pickup']): ?>
                                                        <p class="text-xs text-primary font-semibold mt-1">Physical Store Pickup</p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="lg:text-right">
                                            <p class="text-sm text-slate-500">Total</p>
                                            <p class="text-2xl font-extrabold text-primary"><?php echo formatCurrency((float) $order['total_amount']); ?></p>
                                            <p class="mt-2 text-xs text-slate-500">Placed <?php echo date('M j, Y g:i A', strtotime((string) $order['created_at'])); ?></p>
                                            <a href="invoice.php?order_id=<?php echo (int) $order['id']; ?>" class="mt-3 inline-flex items-center gap-2 rounded-2xl border border-primary/30 bg-primary/5 px-4 py-2.5 text-sm font-bold text-primary hover:bg-primary hover:text-white transition-all">
                                                <span class="material-symbols-outlined text-lg">receipt_long</span>
                                                View Invoice
                                            </a>
                                        </div>
                                    </div>

                                    <?php if (!empty($order['gateway_transaction_ref'])): ?>
                                        <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm">
                                            <p class="text-xs uppercase tracking-wide text-slate-400 font-bold mb-1">Transaction Ref</p>
                                            <p class="text-slate-600 font-mono text-xs"><?php echo htmlspecialchars($order['gateway_transaction_ref']); ?></p>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($order['escrow_status']): ?>
                                        <div class="mt-4">
                                            <span class="inline-flex items-center gap-1 rounded-full border px-3 py-1 text-xs font-semibold 
                                                <?php echo $order['escrow_status'] === 'held' ? 'bg-amber-50 text-amber-700 border-amber-200' : ($order['escrow_status'] === 'released' ? 'bg-emerald-50 text-emerald-700 border-emerald-200' : 'bg-red-50 text-red-700 border-red-200'); ?>">
                                                <span class="material-symbols-outlined text-sm">
                                                    <?php echo $order['escrow_status'] === 'held' ? 'lock' : ($order['escrow_status'] === 'released' ? 'lock_open' : 'undo'); ?>
                                                </span>
                                                Escrow: <?php echo htmlspecialchars(ucfirst($order['escrow_status'])); ?>
                                            </span>
                                            <?php if ($order['buyer_completed'] && $order['seller_completed'] && $order['escrow_status'] === 'held'): ?>
                                                <span class="text-xs text-amber-600 ml-2">Awaiting admin release</span>
                                            <?php endif; ?>
                                            <?php if ($order['disbursed_at']): ?>
                                                <span class="text-xs text-slate-500 ml-2">Released <?php echo date('M j, Y', strtotime($order['disbursed_at'])); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (!empty($order['notes'])): ?>
                                        <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 whitespace-pre-line"><?php echo htmlspecialchars($order['notes']); ?></div>
                                    <?php endif; ?>

                                    <?php if (!$canLeaveReview && ($order['payment_gateway'] ?? '') && !$order['buyer_completed']): ?>
                                        <div class="mt-4">
                                            <form method="POST" class="inline">
                                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                                                <input type="hidden" name="order_id" value="<?php echo (int) $order['id']; ?>">
                                                <button type="submit" name="buyer_mark_completed" class="inline-flex items-center gap-2 rounded-2xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-700 transition-all">
                                                    <span class="material-symbols-outlined text-base">check_circle</span>
                                                    <?php echo $order['store_pickup'] ? 'I have picked up the item' : 'Mark as Received'; ?>
                                                </button>
                                            </form>
                                            <?php if ($order['seller_completed']): ?>
                                                <span class="text-xs text-emerald-600 ml-2">Seller has already confirmed completion.</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($order['buyer_completed'] && !$canLeaveReview && $order['store_pickup']): ?>
                                        <div class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                                            <span class="material-symbols-outlined text-sm align-middle">check_circle</span>
                                            You have marked this order as picked up. 
                                            <?php if ($order['seller_completed']): ?>
                                                Transaction complete. Thank you!
                                            <?php else: ?>
                                                Waiting for seller to confirm.
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if ($canLeaveReview): ?>
                                        <div class="mt-4 rounded-[24px] border border-slate-200 bg-slate-50/80 p-4 sm:p-5">
                                            <?php if ($existingReview): ?>
                                                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                                                    <div>
                                                        <h3 class="text-sm font-bold text-slate-900">Your review</h3>
                                                        <div class="flex items-center gap-1 text-amber-400 mt-2">
                                                            <?php echo orderStars((int) $existingReview['rating']); ?>
                                                        </div>
                                                    </div>
                                                    <p class="text-xs text-slate-500">Submitted <?php echo date('M j, Y', strtotime((string) $existingReview['created_at'])); ?></p>
                                                </div>
                                                <p class="mt-3 text-sm leading-6 text-slate-600"><?php echo nl2br(htmlspecialchars((string) $existingReview['review_text'])); ?></p>
                                            <?php else: ?>
                                                <h3 class="text-sm font-bold text-slate-900">Rate this purchase</h3>
                                                <p class="text-xs sm:text-sm text-slate-500 mt-1">Now that this order is complete, you can leave a star rating and comment for the vendor.</p>

                                                <form method="POST" class="mt-4 space-y-4">
                                                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                                                    <input type="hidden" name="submit_order_review" value="1">
                                                    <input type="hidden" name="order_id" value="<?php echo (int) $order['id']; ?>">

                                                    <div>
                                                        <span class="block text-sm font-semibold text-slate-700 mb-2">Star rating</span>
                                                        <div class="flex flex-wrap gap-2">
                                                            <?php for ($ratingOption = 5; $ratingOption >= 1; $ratingOption--): ?>
                                                                <label class="cursor-pointer">
                                                                    <input class="peer sr-only" type="radio" name="rating" value="<?php echo $ratingOption; ?>" <?php echo $ratingOption === 5 ? 'checked' : ''; ?>>
                                                                    <span class="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-600 transition peer-checked:border-amber-300 peer-checked:bg-amber-50 peer-checked:text-amber-700">
                                                                        <span class="material-symbols-outlined text-[18px]">star</span>
                                                                        <?php echo $ratingOption; ?>
                                                                    </span>
                                                                </label>
                                                            <?php endfor; ?>
                                                        </div>
                                                    </div>

                                                    <label class="block">
                                                        <span class="block text-sm font-semibold text-slate-700 mb-2">Comment</span>
                                                        <textarea
                                                            class="min-h-[110px] w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10"
                                                            name="review_text"
                                                            placeholder="How was the product quality, communication, and delivery experience?"
                                                            required
                                                        ></textarea>
                                                    </label>

                                                    <button class="inline-flex items-center gap-2 rounded-2xl bg-primary px-4 py-3 text-sm font-bold text-white transition hover:bg-primary/90" type="submit">
                                                        <span class="material-symbols-outlined text-base">rate_review</span>
                                                        Submit review
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section>
            <div class="flex items-center justify-between gap-4 mb-4">
                <div>
                    <h2 class="text-xl sm:text-2xl font-bold text-slate-900">Vendor Order Management</h2>
                    <p class="text-sm text-slate-500 mt-1">Review buyer details, track payment, and update delivery progress for every order you receive.</p>
                </div>
            </div>

            <?php if (empty($salesOrders)): ?>
                <div class="rounded-[28px] border border-dashed border-slate-300 bg-white p-8 sm:p-10 text-center shadow-sm">
                    <span class="material-symbols-outlined text-5xl text-slate-300">package_2</span>
                    <h3 class="mt-4 text-lg font-bold text-slate-900">No vendor orders yet</h3>
                    <p class="mt-2 text-sm text-slate-500">Orders from your customers will appear here as soon as they check out.</p>
                </div>
            <?php else: ?>
                <div class="space-y-5">
                    <?php foreach ($salesOrders as $order): ?>
                        <?php $orderImage = $order['product_image'] ?: 'https://via.placeholder.com/320x320?text=No+Image'; ?>
                        <article class="rounded-[28px] border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
                            <div class="flex flex-col xl:flex-row gap-6">
                                <div class="xl:w-[380px]">
                                    <div class="flex gap-4">
                                        <img
                                            alt="<?php echo htmlspecialchars($order['product_title']); ?>"
                                            class="h-28 w-28 rounded-[22px] object-cover border border-slate-200"
                                            src="<?php echo htmlspecialchars($orderImage); ?>"
                                        />
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap gap-2 mb-2">
                                                <span class="rounded-full bg-slate-100 px-3 py-1 text-[11px] font-bold tracking-wide text-slate-600">
                                                    <?php echo htmlspecialchars($order['order_number'] ?: ('#' . $order['id'])); ?>
                                                </span>
                                                <span class="rounded-full border px-3 py-1 text-[11px] font-semibold <?php echo orderStatusClasses((string) $order['status'], 'status'); ?>">
                                                    <?php echo htmlspecialchars(formatOrderValue((string) $order['status'])); ?>
                                                </span>
                                            </div>
                                            <a class="block text-base font-bold leading-snug text-slate-900 hover:text-primary" href="product/<?php echo htmlspecialchars($order['product_slug']); ?>">
                                                <?php echo htmlspecialchars($order['product_title']); ?>
                                            </a>
                                            <p class="mt-2 text-sm text-slate-500">
                                                Buyer:
                                                <span class="font-semibold text-slate-700"><?php echo htmlspecialchars(orderPersonName($order, 'buyer')); ?></span>
                                            </p>
                                        </div>
                                    </div>

                                    <div class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm">
                                        <div class="rounded-2xl bg-slate-50 px-4 py-3 text-slate-600">
                                            <p class="text-xs uppercase tracking-wide text-slate-400 font-bold mb-1">Buyer phone</p>
                                            <p><?php echo htmlspecialchars($order['buyer_phone']); ?></p>
                                        </div>
                                        <div class="rounded-2xl bg-slate-50 px-4 py-3 text-slate-600">
                                            <p class="text-xs uppercase tracking-wide text-slate-400 font-bold mb-1">Payment method</p>
                                            <p><?php echo htmlspecialchars(formatPaymentMethodDisplay($order)); ?></p>
                                            <?php if ($order['store_pickup']): ?>
                                                <p class="text-xs text-primary font-semibold mt-1">Physical Store Pickup</p>
                                            <?php endif; ?>
                                            <?php if ($order['payment_status'] === 'paid' && $order['escrow_status'] === 'held'): ?>
                                                <p class="text-xs text-amber-600 font-semibold mt-1">Payment held in escrow</p>
                                            <?php elseif ($order['escrow_status'] === 'released'): ?>
                                                <p class="text-xs text-emerald-600 font-semibold mt-1">Payment released</p>
                                            <?php endif; ?>
                                        </div>
                                        <div class="rounded-2xl bg-slate-50 px-4 py-3 text-slate-600 sm:col-span-2">
                                            <p class="text-xs uppercase tracking-wide text-slate-400 font-bold mb-1">Delivery location</p>
                                            <p><?php echo htmlspecialchars($order['delivery_location']); ?></p>
                                        </div>
                                        <div class="rounded-2xl bg-slate-50 px-4 py-3 text-slate-600 sm:col-span-2">
                                            <p class="text-xs uppercase tracking-wide text-slate-400 font-bold mb-1">Order value</p>
                                            <p class="font-bold text-primary"><?php echo formatCurrency((float) $order['total_amount']); ?></p>
                                        </div>
                                    </div>

                                    <?php if (!empty($order['notes'])): ?>
                                        <div class="mt-4 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600 whitespace-pre-line"><?php echo htmlspecialchars($order['notes']); ?></div>
                                    <?php endif; ?>
                                </div>

                                <div class="flex-1">
                                    <?php
                                    $isOnlineSale = in_array($order['payment_gateway'] ?? '', ['paystack', 'flutterwave']);
                                    $canSellerComplete = ($order['payment_status'] === 'paid' && !$isOnlineSale) || !$isOnlineSale;
                                    ?>
                                    <?php if (!$order['seller_completed'] && $canSellerComplete): ?>
                                        <form method="POST" class="mb-3">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                                            <input type="hidden" name="order_id" value="<?php echo (int) $order['id']; ?>">
                                            <button type="submit" name="seller_mark_completed" class="inline-flex items-center gap-2 rounded-2xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-700 transition-all">
                                                <span class="material-symbols-outlined text-base">check_circle</span>
                                                <?php echo $order['store_pickup'] ? 'Confirm Pickup & Complete' : 'Mark as Delivered & Complete'; ?>
                                            </button>
                                            <?php if ($order['buyer_completed']): ?>
                                                <span class="text-xs text-emerald-600 ml-2">Buyer already confirmed.</span>
                                            <?php endif; ?>
                                        </form>
                                    <?php elseif ($order['seller_completed']): ?>
                                        <div class="mb-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-800">
                                            <span class="material-symbols-outlined text-sm align-middle">check_circle</span>
                                            You have marked this order as completed.
                                            <?php if ($order['buyer_completed'] && $isOnlineSale && $order['escrow_status'] === 'held'): ?>
                                                <span class="font-semibold">Awaiting admin payment release.</span>
                                            <?php elseif ($order['buyer_completed']): ?>
                                                <span class="font-semibold">Transaction fully complete.</span>
                                            <?php else: ?>
                                                <span class="font-semibold">Waiting for buyer to confirm.</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>

                                    <?php if (($order['delivery_option'] ?? '') === 'riders'): ?>
                                        <?php $oid = (int) $order['id']; ?>
                                        <?php if (isset($assignedRiderMap[$oid])): ?>
                                            <?php $ar = $assignedRiderMap[$oid]; ?>
                                            <div class="mb-3 rounded-2xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm">
                                                <div class="flex items-center gap-2 mb-1">
                                                    <span class="material-symbols-outlined text-blue-600 text-base">motorcycle</span>
                                                    <span class="font-semibold text-blue-800">Rider Assigned</span>
                                                </div>
                                                <p class="text-blue-700"><?php echo htmlspecialchars($ar['rider_name']); ?> &middot; <?php echo htmlspecialchars($ar['rider_phone']); ?></p>
                                                <p class="text-blue-600 text-xs mt-0.5">Status: <?php echo ucfirst(str_replace('_', ' ', $ar['status'])); ?></p>
                                            </div>
                                        <?php elseif (!empty($riderInterestMap[$oid])): ?>
                                            <div class="mb-3 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3">
                                                <p class="text-xs font-bold text-amber-700 uppercase tracking-wide mb-2">Riders Interested</p>
                                                <div class="space-y-2">
                                                    <?php foreach ($riderInterestMap[$oid] as $ri): ?>
                                                        <form method="POST" class="flex items-center justify-between gap-3">
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                                                            <input type="hidden" name="order_id" value="<?php echo $oid; ?>">
                                                            <input type="hidden" name="rider_id" value="<?php echo (int) $ri['rider_id']; ?>">
                                                            <div class="flex items-center gap-2 min-w-0">
                                                                <span class="material-symbols-outlined text-amber-600 text-base">person</span>
                                                                <span class="text-sm font-medium text-amber-800"><?php echo htmlspecialchars($ri['rider_name']); ?></span>
                                                                <span class="text-xs text-amber-600"><?php echo htmlspecialchars($ri['rider_phone']); ?></span>
                                                            </div>
                                                            <button type="submit" name="assign_rider" class="shrink-0 rounded-xl bg-amber-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-amber-700 transition-all">
                                                                Assign
                                                            </button>
                                                        </form>
                                                    <?php endforeach; ?>
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="mb-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-500">
                                                <span class="material-symbols-outlined text-sm align-middle">info</span>
                                                Awaiting rider interest for this delivery.
                                            </div>
                                        <?php endif; ?>
                                    <?php endif; ?>

                                    <?php if ($order['escrow_status'] === 'released'): ?>
                                        <div class="mb-3 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm text-emerald-800">
                                            <span class="material-symbols-outlined text-sm align-middle">lock_open</span>
                                            Payment of <?php echo formatCurrency((float) $order['total_amount']); ?> has been released to you.
                                        </div>
                                    <?php endif; ?>

                                    <form method="POST" class="rounded-[24px] border border-slate-200 bg-slate-50/70 p-4 sm:p-5">
                                        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                                        <input type="hidden" name="vendor_update_order" value="1">
                                        <input type="hidden" name="order_id" value="<?php echo (int) $order['id']; ?>">

                                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                            <label class="block">
                                                <span class="block text-sm font-semibold text-slate-700 mb-2">Order status</span>
                                                <select class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10" name="status">
                                                    <?php foreach ($allowedOrderStatuses as $statusOption): ?>
                                                        <option value="<?php echo htmlspecialchars($statusOption); ?>" <?php echo $order['status'] === $statusOption ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars(formatOrderValue($statusOption)); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </label>

                                            <label class="block">
                                                <span class="block text-sm font-semibold text-slate-700 mb-2">Payment status</span>
                                                <select class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10" name="payment_status">
                                                    <?php foreach ($allowedPaymentStatuses as $statusOption): ?>
                                                        <option value="<?php echo htmlspecialchars($statusOption); ?>" <?php echo $order['payment_status'] === $statusOption ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars(formatOrderValue($statusOption)); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </label>

                                            <label class="block">
                                                <span class="block text-sm font-semibold text-slate-700 mb-2">Delivery status</span>
                                                <select class="w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10" name="delivery_status">
                                                    <?php foreach ($allowedDeliveryStatuses as $statusOption): ?>
                                                        <option value="<?php echo htmlspecialchars($statusOption); ?>" <?php echo $order['delivery_status'] === $statusOption ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars(formatOrderValue($statusOption)); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </label>
                                        </div>

                                        <label class="block mt-4">
                                            <span class="block text-sm font-semibold text-slate-700 mb-2">Vendor note</span>
                                            <textarea
                                                class="min-h-[120px] w-full rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-primary focus:ring-4 focus:ring-primary/10"
                                                name="vendor_note"
                                                placeholder="Add an update for the buyer, payment remark, dispatch note or cancellation reason"
                                            ></textarea>
                                        </label>

                                        <div class="mt-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                            <p class="text-xs sm:text-sm text-slate-500">
                                                Last updated <?php echo date('M j, Y g:i A', strtotime((string) $order['updated_at'])); ?>
                                            </p>
                                            <button class="inline-flex items-center justify-center gap-2 rounded-2xl bg-secondary px-5 py-3 text-sm font-bold text-white transition hover:bg-secondary/90" type="submit">
                                                <span class="material-symbols-outlined text-base">save</span>
                                                Update order
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
    </main>

    <?php include_once 'includes/footer.php'; ?>
</body>
</html>
