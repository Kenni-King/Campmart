<?php
session_start();
require_once 'includes/controller.php';

if (!isset($_SESSION['userAppId'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['userAppId'];
$orderId = isset($_GET['order_id']) ? (int) $_GET['order_id'] : 0;

if ($orderId < 1) {
    $_SESSION['error'] = 'Invalid order ID.';
    header('Location: my-orders.php');
    exit;
}

$stmt = $db->prepare("
    SELECT
        o.*,
        p.title AS product_title,
        p.slug AS product_slug,
        p.price AS product_price,
        p.metadata AS product_metadata,
        pi.image_url AS product_image,
        s.username AS seller_username,
        s.firstname AS seller_firstname,
        s.lastname AS seller_lastname,
        s.full_name AS seller_full_name,
        s.is_verified AS seller_is_verified,
        b.username AS buyer_username,
        b.firstname AS buyer_firstname,
        b.lastname AS buyer_lastname,
        b.full_name AS buyer_full_name,
        b.email AS buyer_email
    FROM orders o
    JOIN products p ON p.id = o.product_id
    LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
    JOIN users s ON s.id = o.seller_id
    JOIN users b ON b.id = o.buyer_id
    WHERE o.id = ? AND o.buyer_id = ?
    LIMIT 1
");
$stmt->bind_param('ii', $orderId, $userId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    $_SESSION['error'] = 'Order not found or access denied.';
    header('Location: my-orders.php');
    exit;
}

$productMeta = json_decode($order['product_metadata'] ?? 'null', true);
$deliveryFee = (float) ($productMeta['delivery_fee'] ?? 0);
$itemPrice = (float) $order['item_price'];
$serviceFee = (float) $order['service_fee'];
$totalAmount = (float) $order['total_amount'];
$quantity = getOrderQuantityFromNotes($order['notes'] ?? '');
$subtotal = $itemPrice * $quantity;

function statusBadge($status, $type = 'status'): string
{
    $map = [
        'status' => [
            'pending' => 'bg-amber-100 text-amber-800',
            'confirmed' => 'bg-blue-100 text-blue-800',
            'processing' => 'bg-indigo-100 text-indigo-800',
            'completed' => 'bg-emerald-100 text-emerald-800',
            'cancelled' => 'bg-red-100 text-red-800',
            'refunded' => 'bg-rose-100 text-rose-800',
        ],
        'payment' => [
            'pending' => 'bg-amber-100 text-amber-800',
            'paid' => 'bg-emerald-100 text-emerald-800',
            'failed' => 'bg-red-100 text-red-800',
            'refunded' => 'bg-rose-100 text-rose-800',
        ],
        'delivery' => [
            'pending' => 'bg-slate-100 text-slate-700',
            'in_transit' => 'bg-sky-100 text-sky-800',
            'delivered' => 'bg-emerald-100 text-emerald-800',
            'returned' => 'bg-red-100 text-red-800',
        ],
    ];
    $class = $map[$type][$status] ?? 'bg-slate-100 text-slate-700';
    return '<span class="inline-block rounded-full px-3 py-1 text-xs font-semibold ' . $class . '">' . ucwords(str_replace('_', ' ', $status)) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <base href="<?php echo SITE_URL; ?>">
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Invoice #<?php echo htmlspecialchars($order['order_number'] ?: $order['id']); ?> | CampMart</title>
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
                    },
                    fontFamily: { display: ['Inter'] }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Inter', sans-serif; color: #1F2937; }
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; }
            .print-bg { background: white !important; }
            @page { margin: 1.5cm; }
        }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">
    <div class="no-print bg-white border-b border-slate-200 px-4 py-3 sticky top-0 z-50">
        <div class="max-w-4xl mx-auto flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="size-8 flex items-center justify-center bg-primary text-white rounded-lg">
                    <span class="material-symbols-outlined font-bold text-xl">shopping_bag</span>
                </div>
                <span class="text-lg font-extrabold text-primary">CampMart</span>
            </div>
            <div class="flex items-center gap-3">
                <button onclick="window.print()" class="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-white hover:bg-primary/90 transition-colors">
                    <span class="material-symbols-outlined text-lg">print</span>
                    Print
                </button>
                <a href="my-orders.php" class="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-5 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50 transition-colors">
                    <span class="material-symbols-outlined text-lg">arrow_back</span>
                    Back
                </a>
            </div>
        </div>
    </div>

    <div class="max-w-4xl mx-auto px-4 py-8">
        <div class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden print-bg">
            <div class="bg-gradient-to-r from-primary to-emerald-800 px-6 md:px-10 py-6 md:py-8 no-print">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl md:text-3xl font-extrabold text-white">Invoice</h1>
                        <p class="text-emerald-200 text-sm mt-1">Order summary and transaction details</p>
                    </div>
                    <span class="material-symbols-outlined text-5xl text-white/20">receipt_long</span>
                </div>
            </div>

            <div class="px-6 md:px-10 py-6 md:py-8 space-y-8">
                <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                    <div>
                        <p class="text-xs uppercase tracking-widest font-bold text-slate-400 mb-1">Invoice Number</p>
                        <p class="text-xl font-extrabold text-slate-900"><?php echo htmlspecialchars($order['order_number'] ?: ('#' . $order['id'])); ?></p>
                    </div>
                    <div class="text-left sm:text-right">
                        <p class="text-xs uppercase tracking-widest font-bold text-slate-400 mb-1">Date Issued</p>
                        <p class="text-sm font-semibold text-slate-700"><?php echo date('F j, Y', strtotime($order['created_at'])); ?></p>
                        <p class="text-xs text-slate-500"><?php echo date('g:i A', strtotime($order['created_at'])); ?></p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                        <p class="text-xs uppercase tracking-widest font-bold text-slate-400 mb-3">Buyer</p>
                        <p class="font-bold text-slate-900"><?php echo htmlspecialchars($order['buyer_full_name'] ?: $order['buyer_firstname'] . ' ' . $order['buyer_lastname']); ?></p>
                        <p class="text-sm text-slate-500 mt-0.5">@<?php echo htmlspecialchars($order['buyer_username']); ?></p>
                        <p class="text-sm text-slate-500"><?php echo htmlspecialchars($order['buyer_email']); ?></p>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                        <p class="text-xs uppercase tracking-widest font-bold text-slate-400 mb-3">Seller</p>
                        <p class="font-bold text-slate-900"><?php echo htmlspecialchars($order['seller_full_name'] ?: $order['seller_firstname'] . ' ' . $order['seller_lastname']); ?>
                            <?php if (!empty($order['seller_is_verified'])): ?><span class="material-symbols-outlined text-blue-500 text-sm fill-1 align-middle" title="Verified Seller">verified</span><?php endif; ?>
                        </p>
                        <p class="text-sm text-slate-500 mt-0.5">@<?php echo htmlspecialchars($order['seller_username']); ?></p>
                    </div>
                </div>

                <div>
                    <p class="text-xs uppercase tracking-widest font-bold text-slate-400 mb-3">Product</p>
                    <div class="flex flex-col sm:flex-row gap-4 rounded-2xl border border-slate-200 bg-white p-4">
                        <img
                            alt="<?php echo htmlspecialchars($order['product_title']); ?>"
                            class="h-24 w-24 rounded-xl object-cover border border-slate-200"
                            src="<?php echo htmlspecialchars($order['product_image'] ?: 'https://via.placeholder.com/160?text=No+Image'); ?>"
                        />
                        <div class="flex-1 min-w-0">
                            <a class="text-lg font-bold text-slate-900 hover:text-primary" href="product/<?php echo htmlspecialchars($order['product_slug']); ?>">
                                <?php echo htmlspecialchars($order['product_title']); ?>
                            </a>
                            <p class="text-sm text-slate-500 mt-1"><?php echo htmlspecialchars($order['delivery_location']); ?></p>
                        </div>
                    </div>
                </div>

                <div>
                    <p class="text-xs uppercase tracking-widest font-bold text-slate-400 mb-3">Price Breakdown</p>
                    <div class="rounded-2xl border border-slate-200 divide-y divide-slate-100">
                        <div class="flex items-center justify-between px-5 py-4">
                            <span class="text-sm text-slate-600">Item Price (x<?php echo $quantity; ?>)</span>
                            <span class="font-semibold text-slate-900"><?php echo formatCurrency($subtotal); ?></span>
                        </div>
                        <?php if ($deliveryFee > 0): ?>
                        <div class="flex items-center justify-between px-5 py-4">
                            <span class="text-sm text-slate-600">Delivery Fee</span>
                            <span class="font-semibold text-slate-900"><?php echo formatCurrency($deliveryFee); ?></span>
                        </div>
                        <?php endif; ?>
                        <?php if ($serviceFee > 0): ?>
                        <div class="flex items-center justify-between px-5 py-4">
                            <span class="text-sm text-slate-600">Service Fee</span>
                            <span class="font-semibold text-slate-900"><?php echo formatCurrency($serviceFee); ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="flex items-center justify-between px-5 py-4 bg-primary/5">
                            <span class="text-base font-bold text-slate-900">Total</span>
                            <span class="text-xl font-extrabold text-primary"><?php echo formatCurrency($totalAmount); ?></span>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs uppercase tracking-widest font-bold text-slate-400 mb-2">Order Status</p>
                        <div><?php echo statusBadge($order['status'], 'status'); ?></div>
                        <?php if ($order['completed_at']): ?>
                            <p class="text-xs text-slate-500 mt-2">Completed <?php echo date('M j, Y', strtotime($order['completed_at'])); ?></p>
                        <?php endif; ?>
                        <?php if ($order['cancelled_at']): ?>
                            <p class="text-xs text-slate-500 mt-2">Cancelled <?php echo date('M j, Y', strtotime($order['cancelled_at'])); ?></p>
                        <?php endif; ?>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs uppercase tracking-widest font-bold text-slate-400 mb-2">Payment</p>
                        <div><?php echo statusBadge($order['payment_status'], 'payment'); ?></div>
                        <p class="text-xs text-slate-500 mt-2 capitalize"><?php echo htmlspecialchars(formatPaymentMethodDisplay($order)); ?></p>
                        <?php if ($order['payment_gateway']): ?>
                            <p class="text-xs text-slate-500 mt-1 font-mono">Ref: <?php echo htmlspecialchars($order['gateway_transaction_ref'] ?? 'N/A'); ?></p>
                        <?php endif; ?>
                        <?php if ($order['escrow_status']): ?>
                            <p class="text-xs font-semibold mt-1 <?php echo $order['escrow_status'] === 'released' ? 'text-emerald-600' : 'text-amber-600'; ?>">
                                Escrow: <?php echo htmlspecialchars(ucfirst($order['escrow_status'])); ?>
                            </p>
                        <?php endif; ?>
                        <?php if ($order['disbursed_at']): ?>
                            <p class="text-xs text-slate-500 mt-1">Released <?php echo date('M j, Y', strtotime($order['disbursed_at'])); ?></p>
                        <?php endif; ?>
                        <?php if ($order['store_pickup']): ?>
                            <p class="text-xs text-primary font-semibold mt-1">Physical Store Pickup</p>
                        <?php endif; ?>
                    </div>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                        <p class="text-xs uppercase tracking-widest font-bold text-slate-400 mb-2">Delivery</p>
                        <div><?php echo statusBadge($order['delivery_status'], 'delivery'); ?></div>
                        <p class="text-xs text-slate-500 mt-2"><?php echo htmlspecialchars($order['delivery_location']); ?></p>
                    </div>
                </div>

                <?php if (!empty($order['notes'])): ?>
                <div>
                    <p class="text-xs uppercase tracking-widest font-bold text-slate-400 mb-2">Order Notes</p>
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4 text-sm text-slate-600 whitespace-pre-line"><?php echo htmlspecialchars($order['notes']); ?></div>
                </div>
                <?php endif; ?>

                <div class="border-t border-slate-200 pt-6 text-center">
                    <p class="text-xs text-slate-400">Generated by CampMart on <?php echo date('F j, Y g:i A'); ?></p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
