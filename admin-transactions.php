<?php
session_start();
require_once 'includes/controller.php';

$user_query = "SELECT role FROM users WHERE id = ?";
$stmt = $db->prepare($user_query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();

if (!$user || ($user['role'] !== 'admin' && $user['role'] !== 'superadmin')) {
    header('Location: index.php');
    exit;
}

$success_message = '';
$error_message = '';

// Handle escrow release
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['release_escrow'])) {
    $orderId = (int) ($_POST['order_id'] ?? 0);

    $stmt = $db->prepare("
        SELECT o.*, p.title AS product_title,
               b.username AS buyer_username, s.username AS seller_username
        FROM orders o
        JOIN products p ON p.id = o.product_id
        JOIN users b ON b.id = o.buyer_id
        JOIN users s ON s.id = o.seller_id
        WHERE o.id = ? AND o.escrow_status = 'held' AND o.payment_status = 'paid'
        LIMIT 1
    ");
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();

    if (!$order) {
        $error_message = 'Order not found or not eligible for escrow release.';
    } elseif (empty($order['buyer_completed']) || empty($order['seller_completed'])) {
        $error_message = 'Both buyer and seller must mark the order as completed before releasing payment.';
    } else {
        $db->begin_transaction();
        try {
            $releaseStmt = $db->prepare("
                UPDATE orders 
                SET escrow_status = 'released', 
                    disbursed_at = NOW(),
                    status = 'completed',
                    completed_at = NOW()
                WHERE id = ?
            ");
            $releaseStmt->bind_param('i', $orderId);
            $releaseStmt->execute();

            // Notify seller
            $sellerNotif = $db->prepare("
                INSERT INTO notifications (user_id, title, message, type, related_id, related_type, action_url)
                VALUES (?, 'Payment Released', ?, 'transaction', ?, 'order', 'my-orders.php')
            ");
            $sellerMsg = "Payment of " . formatCurrency((float) $order['total_amount']) . " for {$order['product_title']} has been released to your account.";
            $sellerNotif->bind_param('isi', $order['seller_id'], $sellerMsg, $orderId);
            $sellerNotif->execute();

            // Notify buyer
            $buyerNotif = $db->prepare("
                INSERT INTO notifications (user_id, title, message, type, related_id, related_type, action_url)
                VALUES (?, 'Payment Released', ?, 'transaction', ?, 'order', 'my-orders.php')
            ");
            $buyerMsg = "Payment for {$order['product_title']} has been released to the seller. Transaction complete.";
            $buyerNotif->bind_param('isi', $order['buyer_id'], $buyerMsg, $orderId);
            $buyerNotif->execute();

            $db->commit();
            $success_message = "Payment of " . formatCurrency((float) $order['total_amount']) . " for order #{$order['order_number']} has been released to {$order['seller_username']}.";
        } catch (Throwable $e) {
            $db->rollback();
            $error_message = 'Failed to release payment: ' . $e->getMessage();
        }
    }
}

// Handle escrow refund
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['refund_escrow'])) {
    $orderId = (int) ($_POST['order_id'] ?? 0);

    $stmt = $db->prepare("
        SELECT o.*, p.title AS product_title
        FROM orders o
        JOIN products p ON p.id = o.product_id
        WHERE o.id = ? AND o.escrow_status = 'held' AND o.payment_status = 'paid'
        LIMIT 1
    ");
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();

    if (!$order) {
        $error_message = 'Order not found or not eligible for refund.';
    } else {
        $refundStmt = $db->prepare("
            UPDATE orders 
            SET escrow_status = 'refunded',
                payment_status = 'refunded',
                status = 'refunded'
            WHERE id = ?
        ");
        $refundStmt->bind_param('i', $orderId);
        if ($refundStmt->execute()) {
            $success_message = "Payment for order #{$order['order_number']} has been refunded to the buyer.";
        } else {
            $error_message = 'Failed to process refund.';
        }
    }
}

// Fetch transactions
$searchQuery = trim($_GET['search'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$whereConditions = [];
$params = [];
$types = '';

if (!empty($searchQuery)) {
    $whereConditions[] = "(o.order_number LIKE ? OR p.title LIKE ? OR b.username LIKE ? OR s.username LIKE ?)";
    $searchParam = '%' . $searchQuery . '%';
    $params = array_merge($params, [$searchParam, $searchParam, $searchParam, $searchParam]);
    $types .= 'ssss';
}

if ($statusFilter === 'held') {
    $whereConditions[] = "o.escrow_status = 'held'";
} elseif ($statusFilter === 'released') {
    $whereConditions[] = "o.escrow_status = 'released'";
} elseif ($statusFilter === 'pending') {
    $whereConditions[] = "(o.escrow_status IS NULL OR o.escrow_status = '')";
}

$whereSQL = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

$countQuery = "SELECT COUNT(*) as total FROM orders o 
               JOIN products p ON p.id = o.product_id 
               JOIN users b ON b.id = o.buyer_id 
               JOIN users s ON s.id = o.seller_id 
               $whereSQL";
$countResult = $db->query($countQuery);
$totalOrders = $countResult->fetch_assoc()['total'];
$totalPages = ceil($totalOrders / $perPage);

$query = "SELECT o.*, p.title AS product_title, p.slug AS product_slug,
          b.username AS buyer_username, b.full_name AS buyer_full_name,
          s.username AS seller_username, s.full_name AS seller_full_name
          FROM orders o
          JOIN products p ON p.id = o.product_id
          JOIN users b ON b.id = o.buyer_id
          JOIN users s ON s.id = o.seller_id
          $whereSQL
          ORDER BY o.created_at DESC
          LIMIT $perPage OFFSET $offset";

$result = $db->query($query);
$orders = [];
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transaction Management - CampMart Admin</title>
    <script src="tailwind34.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" />
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#f48c25',
                        secondary: '#FF6B35',
                        accent: '#FFE66D',
                        'brand-green': '#064E3B',
                        'background-main': '#F9FAFB',
                        'surface-white': '#FFFFFF',
                        'text-dark': '#1F2937'
                    },
                    fontFamily: { display: ['Inter'] }
                }
            }
        }
    </script>
    <style>.material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }</style>
</head>
<body class="bg-background-main min-h-screen text-text-dark">
    <?php include_once 'includes/user-nav.php'; ?>
    <main class="flex-1 overflow-y-auto bg-background-main p-4 md:p-6 lg:p-8">
        <div class="max-w-7xl mx-auto space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-brand-green">Transaction Management</h1>
                    <p class="text-sm text-slate-500 mt-1">Manage escrow payments, release or refund held funds.</p>
                </div>
            </div>

            <?php if ($success_message): ?>
            <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-800 flex items-center gap-2">
                <span class="material-symbols-outlined">check_circle</span>
                <span><?= htmlspecialchars($success_message) ?></span>
            </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-800 flex items-center gap-2">
                <span class="material-symbols-outlined">error</span>
                <span><?= htmlspecialchars($error_message) ?></span>
            </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
                <form method="GET" class="flex flex-wrap items-center gap-4">
                    <div class="flex-1 min-w-[200px]">
                        <input type="text" name="search" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="Search order, product, buyer, seller..." class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:ring-2 focus:ring-primary/30 focus:border-primary text-sm" />
                    </div>
                    <select name="status" class="px-3 py-2 rounded-lg border border-slate-200 focus:ring-2 focus:ring-primary/30 focus:border-primary text-sm">
                        <option value="">All Statuses</option>
                        <option value="held" <?= $statusFilter === 'held' ? 'selected' : '' ?>>Escrow Held</option>
                        <option value="released" <?= $statusFilter === 'released' ? 'selected' : '' ?>>Escrow Released</option>
                        <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending / No Escrow</option>
                    </select>
                    <button type="submit" class="px-4 py-2 bg-brand-green text-white rounded-lg font-semibold text-sm hover:bg-brand-green/90 transition-colors">Filter</button>
                    <a href="admin-transactions.php" class="px-4 py-2 border border-slate-200 rounded-lg text-sm text-slate-600 hover:bg-slate-50 transition-colors">Reset</a>
                </form>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <?php
                $heldCount = $db->query("SELECT COUNT(*) FROM orders WHERE escrow_status = 'held' AND payment_status = 'paid'")->fetch_row()[0];
                $readyCount = $db->query("SELECT COUNT(*) FROM orders WHERE escrow_status = 'held' AND payment_status = 'paid' AND buyer_completed = 1 AND seller_completed = 1")->fetch_row()[0];
                $releasedCount = $db->query("SELECT COUNT(*) FROM orders WHERE escrow_status = 'released'")->fetch_row()[0];
                $totalEscrow = $db->query("SELECT COALESCE(SUM(total_amount), 0) FROM orders WHERE escrow_status = 'held' AND payment_status = 'paid'")->fetch_row()[0];
                ?>
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
                    <p class="text-xs uppercase tracking-wide text-slate-400 font-bold">Held in Escrow</p>
                    <p class="mt-2 text-2xl font-bold text-amber-600"><?= $heldCount ?></p>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
                    <p class="text-xs uppercase tracking-wide text-slate-400 font-bold">Ready to Release</p>
                    <p class="mt-2 text-2xl font-bold text-emerald-600"><?= $readyCount ?></p>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
                    <p class="text-xs uppercase tracking-wide text-slate-400 font-bold">Released</p>
                    <p class="mt-2 text-2xl font-bold text-blue-600"><?= $releasedCount ?></p>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
                    <p class="text-xs uppercase tracking-wide text-slate-400 font-bold">Total Held (₦)</p>
                    <p class="mt-2 text-2xl font-bold text-brand-green"><?= number_format($totalEscrow, 2) ?></p>
                </div>
            </div>

            <!-- Transactions List -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <?php if (empty($orders)): ?>
                <div class="p-8 text-center">
                    <span class="material-symbols-outlined text-5xl text-slate-300">receipt_long</span>
                    <h3 class="mt-4 text-lg font-bold text-slate-900">No transactions found</h3>
                    <p class="mt-2 text-sm text-slate-500">Orders with online payments will appear here.</p>
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="text-left px-4 py-3 font-semibold text-slate-600">Order</th>
                                <th class="text-left px-4 py-3 font-semibold text-slate-600">Product</th>
                                <th class="text-left px-4 py-3 font-semibold text-slate-600">Buyer</th>
                                <th class="text-left px-4 py-3 font-semibold text-slate-600">Seller</th>
                                <th class="text-left px-4 py-3 font-semibold text-slate-600">Amount</th>
                                <th class="text-left px-4 py-3 font-semibold text-slate-600">Gateway</th>
                                <th class="text-left px-4 py-3 font-semibold text-slate-600">Status</th>
                                <th class="text-left px-4 py-3 font-semibold text-slate-600">Escrow</th>
                                <th class="text-left px-4 py-3 font-semibold text-slate-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($orders as $order): ?>
                            <tr class="hover:bg-slate-50/50">
                                <td class="px-4 py-3">
                                    <span class="font-mono text-xs"><?= htmlspecialchars($order['order_number'] ?: '#' . $order['id']) ?></span>
                                    <p class="text-xs text-slate-400"><?= date('M j, Y', strtotime($order['created_at'])) ?></p>
                                </td>
                                <td class="px-4 py-3">
                                    <a href="product/<?= htmlspecialchars($order['product_slug']) ?>" class="font-medium text-brand-green hover:underline">
                                        <?= htmlspecialchars($order['product_title']) ?>
                                    </a>
                                </td>
                                <td class="px-4 py-3"><?= htmlspecialchars($order['buyer_username']) ?></td>
                                <td class="px-4 py-3"><?= htmlspecialchars($order['seller_username']) ?></td>
                                <td class="px-4 py-3 font-semibold"><?= formatCurrency((float) $order['total_amount']) ?></td>
                                <td class="px-4 py-3">
                                    <?php if ($order['payment_gateway']): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold <?= $order['payment_gateway'] === 'paystack' ? 'bg-blue-50 text-blue-700' : 'bg-purple-50 text-purple-700' ?>">
                                            <?= htmlspecialchars(ucfirst($order['payment_gateway'])) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-xs text-slate-400">POD</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold
                                        <?= $order['payment_status'] === 'paid' ? 'bg-emerald-50 text-emerald-700' : ($order['payment_status'] === 'refunded' ? 'bg-red-50 text-red-700' : 'bg-amber-50 text-amber-700') ?>">
                                        <?= htmlspecialchars(ucfirst($order['payment_status'])) ?>
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    <?php if ($order['escrow_status'] === 'held'): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700">
                                            <span class="material-symbols-outlined text-xs">lock</span>
                                            Held
                                        </span>
                                        <?php if ($order['buyer_completed'] && $order['seller_completed']): ?>
                                            <p class="text-xs text-emerald-600 mt-1">Both confirmed</p>
                                        <?php else: ?>
                                            <p class="text-xs text-slate-400 mt-1">
                                                B: <?= $order['buyer_completed'] ? 'Yes' : 'No' ?>
                                                S: <?= $order['seller_completed'] ? 'Yes' : 'No' ?>
                                            </p>
                                        <?php endif; ?>
                                    <?php elseif ($order['escrow_status'] === 'released'): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700">
                                            <span class="material-symbols-outlined text-xs">lock_open</span>
                                            Released
                                        </span>
                                        <?php if ($order['disbursed_at']): ?>
                                            <p class="text-xs text-slate-400 mt-1"><?= date('M j, Y', strtotime($order['disbursed_at'])) ?></p>
                                        <?php endif; ?>
                                    <?php elseif ($order['escrow_status'] === 'refunded'): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-red-50 text-red-700">
                                            Refunded
                                        </span>
                                    <?php else: ?>
                                        <span class="text-xs text-slate-400">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-4 py-3">
                                    <div class="flex flex-wrap gap-2">
                                        <?php if ($order['escrow_status'] === 'held'): ?>
                                            <?php if ($order['buyer_completed'] && $order['seller_completed']): ?>
                                                <form method="POST" onsubmit="return confirm('Release payment of <?= formatCurrency((float) $order['total_amount']) ?> to seller?')">
                                                    <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                                    <button type="submit" name="release_escrow" class="inline-flex items-center gap-1 px-3 py-1.5 bg-emerald-600 text-white rounded-lg text-xs font-semibold hover:bg-emerald-700 transition-colors">
                                                        <span class="material-symbols-outlined text-xs">lock_open</span>
                                                        Release
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-xs text-slate-400 italic">Awaiting both confirmations</span>
                                            <?php endif; ?>
                                            <form method="POST" onsubmit="return confirm('Refund this payment to the buyer? This cannot be undone.')">
                                                <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                                                <button type="submit" name="refund_escrow" class="inline-flex items-center gap-1 px-3 py-1.5 bg-red-500 text-white rounded-lg text-xs font-semibold hover:bg-red-600 transition-colors">
                                                    <span class="material-symbols-outlined text-xs">undo</span>
                                                    Refund
                                                </button>
                                            </form>
                                        <?php elseif ($order['escrow_status'] === 'released'): ?>
                                            <span class="text-xs text-emerald-600">Released <?= $order['disbursed_at'] ? date('M j, Y', strtotime($order['disbursed_at'])) : '' ?></span>
                                        <?php else: ?>
                                            <span class="text-xs text-slate-400">No escrow</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($totalPages > 1): ?>
                <div class="px-4 py-3 border-t border-slate-200 flex items-center justify-between">
                    <p class="text-sm text-slate-500">Page <?= $page ?> of <?= $totalPages ?></p>
                    <div class="flex gap-2">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($searchQuery) ?>&status=<?= urlencode($statusFilter) ?>" class="px-3 py-1.5 border border-slate-200 rounded-lg text-sm hover:bg-slate-50">Previous</a>
                        <?php endif; ?>
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($searchQuery) ?>&status=<?= urlencode($statusFilter) ?>" class="px-3 py-1.5 border border-slate-200 rounded-lg text-sm hover:bg-slate-50">Next</a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>
</body>
</html>
