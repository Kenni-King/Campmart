<?php
session_start();
require_once 'includes/controller.php';

if (!isset($_SESSION['userAppId'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['userAppId'];
$reference = $_GET['reference'] ?? $_POST['tx_ref'] ?? $_SESSION['payment_reference'] ?? '';
$orderId = (int) ($_GET['order_id'] ?? $_SESSION['payment_order_id'] ?? 0);

if (empty($reference) && $orderId < 1) {
    $_SESSION['error'] = 'Invalid payment callback. No reference or order ID found.';
    header('Location: my-orders.php');
    exit;
}

if (!empty($reference)) {
    $stmt = $db->prepare("
        SELECT o.*, b.email AS buyer_email, b.full_name AS buyer_name
        FROM orders o
        JOIN users b ON b.id = o.buyer_id
        WHERE o.gateway_transaction_ref = ? AND o.buyer_id = ?
        LIMIT 1
    ");
    $stmt->bind_param('si', $reference, $userId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
} else {
    $stmt = $db->prepare("
        SELECT o.*, b.email AS buyer_email, b.full_name AS buyer_name
        FROM orders o
        JOIN users b ON b.id = o.buyer_id
        WHERE o.id = ? AND o.buyer_id = ?
        LIMIT 1
    ");
    $stmt->bind_param('ii', $orderId, $userId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
}

if (!$order) {
    $_SESSION['error'] = 'Order not found or access denied.';
    header('Location: my-orders.php');
    exit;
}

$gateway = $order['payment_gateway'] ?? '';
$success = false;

if ($gateway === 'paystack') {
    $ref = $order['gateway_transaction_ref'] ?? $reference;
    $result = verifyPaystackPayment($ref);
    if ($result['status']) {
        $success = true;
    }
} elseif ($gateway === 'flutterwave') {
    $transactionId = $_GET['transaction_id'] ?? 0;
    if ($transactionId) {
        $result = verifyFlutterwavePayment($transactionId);
        if ($result['status']) {
            $success = true;
        }
    }
}

if ($success) {
    $db->begin_transaction();
    try {
        $updateStmt = $db->prepare("
            UPDATE orders 
            SET payment_status = 'paid',
                gateway_response = ?,
                status = 'confirmed'
            WHERE id = ? AND buyer_id = ?
        ");
        $fullResponse = $result['full_response'] ?? '';
        $updateStmt->bind_param('sii', $fullResponse, $order['id'], $userId);
        $updateStmt->execute();

        $notifStmt = $db->prepare("
            INSERT INTO notifications (user_id, title, message, type, related_id, related_type, action_url)
            VALUES (?, 'Payment confirmed', ?, 'transaction', ?, 'order', 'my-orders.php')
        ");
        $gatewayName = ucfirst($gateway);
        $msg = "Payment of " . formatCurrency((float) $order['total_amount']) . " via {$gatewayName} confirmed for order #{$order['order_number']}.";
        $notifStmt->bind_param('isi', $order['seller_id'], $msg, $order['id']);
        $notifStmt->execute();

        $db->commit();

        unset($_SESSION['pending_payment_order_ids'], $_SESSION['pending_payment_gateway'], $_SESSION['payment_reference'], $_SESSION['payment_order_id']);

        $_SESSION['success'] = 'Payment confirmed successfully! Your order is now being processed.';
        header('Location: my-orders.php');
        exit;
    } catch (Throwable $e) {
        $db->rollback();
        $_SESSION['error'] = 'Failed to update payment status: ' . $e->getMessage();
        header('Location: my-orders.php');
        exit;
    }
} else {
    $_SESSION['error'] = 'Payment verification failed. Please try again or contact support.';
    unset($_SESSION['pending_payment_order_ids'], $_SESSION['pending_payment_gateway']);
    header('Location: my-orders.php');
    exit;
}
