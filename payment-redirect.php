<?php
session_start();
require_once 'includes/controller.php';

if (!isset($_SESSION['userAppId'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['userAppId'];
$orderIds = $_SESSION['pending_payment_order_ids'] ?? [];
$gateway = $_SESSION['pending_payment_gateway'] ?? '';

if (empty($orderIds) || empty($gateway)) {
    $_SESSION['error'] = 'No pending payment found.';
    header('Location: my-orders.php');
    exit;
}

$placeholders = implode(',', array_fill(0, count($orderIds), '?'));
$types = str_repeat('i', count($orderIds));

$stmt = $db->prepare("
    SELECT o.*, b.email AS buyer_email, b.full_name AS buyer_name
    FROM orders o
    JOIN users b ON b.id = o.buyer_id
    WHERE o.id IN ($placeholders) AND o.buyer_id = ? AND o.payment_status = 'pending'
    ORDER BY o.id ASC
    LIMIT 1
");

$params = array_merge($orderIds, [$userId]);
$types .= 'i';
$stmt->bind_param($types, ...$params);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    $_SESSION['error'] = 'Order not found or already paid.';
    unset($_SESSION['pending_payment_order_ids'], $_SESSION['pending_payment_gateway']);
    header('Location: my-orders.php');
    exit;
}

$callbackUrl = SITE_URL . 'payment-callback.php';

if ($gateway === 'paystack') {
    $result = initiatePaystackPayment($order, $callbackUrl);
} elseif ($gateway === 'flutterwave') {
    $result = initiateFlutterwavePayment($order, $callbackUrl);
} else {
    $_SESSION['error'] = 'Invalid payment gateway.';
    unset($_SESSION['pending_payment_order_ids'], $_SESSION['pending_payment_gateway']);
    header('Location: my-orders.php');
    exit;
}

if (!$result['status']) {
    $_SESSION['error'] = 'Payment initiation failed: ' . ($result['message'] ?? 'Unknown error');
    unset($_SESSION['pending_payment_order_ids'], $_SESSION['pending_payment_gateway']);
    header('Location: my-orders.php');
    exit;
}

$ref = $result['reference'];
$updateStmt = $db->prepare("UPDATE orders SET gateway_transaction_ref = ? WHERE id = ?");
$updateStmt->bind_param('si', $ref, $order['id']);
$updateStmt->execute();

$_SESSION['payment_reference'] = $ref;
$_SESSION['payment_order_id'] = $order['id'];

header('Location: ' . $result['authorization_url']);
exit;
