<?php
session_start();
require_once 'includes/controller.php';

if (!isset($_SESSION['userAppId'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['userAppId'];
$productId = (int) ($_GET['id'] ?? 0);

if ($productId < 1) {
    $_SESSION['error'] = 'Product not found.';
    header('Location: my-products.php');
    exit;
}

function productMetricBadge(string $label, string $value, string $icon, string $tone = 'slate'): string
{
    $toneMap = [
        'slate' => 'bg-slate-100 text-slate-700',
        'green' => 'bg-emerald-100 text-emerald-700',
        'blue' => 'bg-sky-100 text-sky-700',
        'orange' => 'bg-orange-100 text-orange-700',
        'purple' => 'bg-violet-100 text-violet-700',
    ];

    $classes = $toneMap[$tone] ?? $toneMap['slate'];

    return '<div class="rounded-[24px] border border-slate-200 bg-white p-4 sm:p-5 shadow-sm">'
        . '<div class="flex items-center justify-between gap-3">'
        . '<p class="text-xs uppercase tracking-[0.18em] text-slate-400 font-bold">' . htmlspecialchars($label) . '</p>'
        . '<span class="material-symbols-outlined rounded-2xl px-2.5 py-2 text-base ' . $classes . '">' . htmlspecialchars($icon) . '</span>'
        . '</div>'
        . '<p class="mt-4 text-2xl font-extrabold text-slate-900">' . htmlspecialchars($value) . '</p>'
        . '</div>';
}

function productProfileStatusClasses(string $status): string
{
    $map = [
        'available' => 'bg-emerald-100 text-emerald-700',
        'reserved' => 'bg-amber-100 text-amber-700',
        'sold' => 'bg-slate-200 text-slate-700',
        'unavailable' => 'bg-red-100 text-red-700',
        'approved' => 'bg-blue-100 text-blue-700',
        'pending' => 'bg-amber-100 text-amber-700',
        'confirmed' => 'bg-sky-100 text-sky-700',
        'processing' => 'bg-indigo-100 text-indigo-700',
        'completed' => 'bg-emerald-100 text-emerald-700',
        'cancelled' => 'bg-red-100 text-red-700',
        'rejected' => 'bg-red-100 text-red-700',
    ];

    return $map[$status] ?? 'bg-slate-100 text-slate-700';
}

function productProfileStars(float $rating): string
{
    $rounded = (int) round($rating);
    $html = '<div class="flex items-center gap-1 text-amber-400">';
    for ($i = 1; $i <= 5; $i++) {
        $html .= '<span class="material-symbols-outlined text-[18px]">' . ($i <= $rounded ? 'star' : 'star_outline') . '</span>';
    }
    $html .= '</div>';
    return $html;
}

$productQuery = "
    SELECT
        p.*,
        c.name AS category_name,
        pi.image_url AS primary_image,
        COALESCE(view_stats.unique_viewers, 0) AS unique_viewers,
        COALESCE(view_stats.total_view_events, 0) AS total_view_events,
        COALESCE(cart_stats.cart_users, 0) AS cart_users,
        COALESCE(cart_stats.cart_quantity, 0) AS cart_quantity,
        COALESCE(bookmark_stats.bookmark_users, 0) AS bookmark_users,
        COALESCE(order_stats.total_orders, 0) AS total_orders,
        COALESCE(order_stats.pending_orders, 0) AS pending_orders,
        COALESCE(order_stats.processing_orders, 0) AS processing_orders,
        COALESCE(order_stats.completed_orders, 0) AS completed_orders,
        COALESCE(order_stats.cancelled_orders, 0) AS cancelled_orders,
        COALESCE(order_stats.gross_revenue, 0) AS gross_revenue,
        COALESCE(order_stats.completed_revenue, 0) AS completed_revenue,
        COALESCE(review_stats.avg_rating, 0) AS avg_rating,
        COALESCE(review_stats.review_count, 0) AS review_count
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
    LEFT JOIN (
        SELECT product_id, COUNT(DISTINCT user_id) AS unique_viewers, COUNT(*) AS total_view_events
        FROM product_views
        GROUP BY product_id
    ) view_stats ON view_stats.product_id = p.id
    LEFT JOIN (
        SELECT product_id, COUNT(*) AS cart_users, COALESCE(SUM(quantity), 0) AS cart_quantity
        FROM cart
        GROUP BY product_id
    ) cart_stats ON cart_stats.product_id = p.id
    LEFT JOIN (
        SELECT product_id, COUNT(*) AS bookmark_users
        FROM bookmarks
        WHERE bookmark_type = 'product'
        GROUP BY product_id
    ) bookmark_stats ON bookmark_stats.product_id = p.id
    LEFT JOIN (
        SELECT
            product_id,
            COUNT(*) AS total_orders,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_orders,
            SUM(CASE WHEN status IN ('confirmed', 'processing') THEN 1 ELSE 0 END) AS processing_orders,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_orders,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_orders,
            SUM(CASE WHEN status <> 'cancelled' THEN total_amount ELSE 0 END) AS gross_revenue,
            SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END) AS completed_revenue
        FROM orders
        GROUP BY product_id
    ) order_stats ON order_stats.product_id = p.id
    LEFT JOIN (
        SELECT product_id, AVG(rating) AS avg_rating, COUNT(*) AS review_count
        FROM reviews
        WHERE review_type = 'seller' AND status = 'approved'
        GROUP BY product_id
    ) review_stats ON review_stats.product_id = p.id
    WHERE p.id = ? AND p.user_id = ?
    LIMIT 1
";
$productStmt = $db->prepare($productQuery);
$productStmt->bind_param('ii', $productId, $userId);
$productStmt->execute();
$product = $productStmt->get_result()->fetch_assoc();

if (!$product) {
    $_SESSION['error'] = 'Product not found or access denied.';
    header('Location: my-products.php');
    exit;
}

$viewersStmt = $db->prepare("
    SELECT
        u.id,
        u.username,
        u.full_name,
        u.firstname,
        u.lastname,
        u.profile_image,
        MAX(pv.viewed_at) AS last_viewed_at
    FROM product_views pv
    JOIN users u ON u.id = pv.user_id
    WHERE pv.product_id = ? AND pv.user_id IS NOT NULL
    GROUP BY u.id, u.username, u.full_name, u.firstname, u.lastname, u.profile_image
    ORDER BY last_viewed_at DESC
");
$viewersStmt->bind_param('i', $productId);
$viewersStmt->execute();
$viewersResult = $viewersStmt->get_result();
$viewers = [];
while ($row = $viewersResult->fetch_assoc()) {
    $viewers[] = $row;
}

$cartUsersStmt = $db->prepare("
    SELECT
        c.quantity,
        c.delivery_option,
        c.updated_at,
        u.id,
        u.username,
        u.full_name,
        u.firstname,
        u.lastname,
        u.profile_image
    FROM cart c
    JOIN users u ON u.id = c.user_id
    WHERE c.product_id = ?
    ORDER BY c.updated_at DESC
");
$cartUsersStmt->bind_param('i', $productId);
$cartUsersStmt->execute();
$cartUsersResult = $cartUsersStmt->get_result();
$cartUsers = [];
while ($row = $cartUsersResult->fetch_assoc()) {
    $cartUsers[] = $row;
}

$bookmarkUsersStmt = $db->prepare("
    SELECT
        b.created_at,
        u.id,
        u.username,
        u.full_name,
        u.firstname,
        u.lastname,
        u.profile_image
    FROM bookmarks b
    JOIN users u ON u.id = b.user_id
    WHERE b.product_id = ? AND b.bookmark_type = 'product'
    ORDER BY b.created_at DESC
");
$bookmarkUsersStmt->bind_param('i', $productId);
$bookmarkUsersStmt->execute();
$bookmarkUsersResult = $bookmarkUsersStmt->get_result();
$bookmarkUsers = [];
while ($row = $bookmarkUsersResult->fetch_assoc()) {
    $bookmarkUsers[] = $row;
}

$ordersStmt = $db->prepare("
    SELECT
        o.*,
        u.username AS buyer_username,
        u.full_name AS buyer_full_name,
        u.firstname AS buyer_firstname,
        u.lastname AS buyer_lastname,
        u.profile_image AS buyer_profile_image
    FROM orders o
    JOIN users u ON u.id = o.buyer_id
    WHERE o.product_id = ? AND o.seller_id = ?
    ORDER BY o.created_at DESC
");
$ordersStmt->bind_param('ii', $productId, $userId);
$ordersStmt->execute();
$ordersResult = $ordersStmt->get_result();
$orders = [];
while ($row = $ordersResult->fetch_assoc()) {
    $orders[] = $row;
}

$reviewsStmt = $db->prepare("
    SELECT
        r.*,
        u.username,
        u.full_name,
        u.firstname,
        u.lastname,
        u.profile_image
    FROM reviews r
    JOIN users u ON u.id = r.reviewer_id
    WHERE r.product_id = ? AND r.reviewed_user_id = ? AND r.review_type = 'seller' AND r.status = 'approved'
    ORDER BY r.created_at DESC
");
$reviewsStmt->bind_param('ii', $productId, $userId);
$reviewsStmt->execute();
$reviewsResult = $reviewsStmt->get_result();
$reviews = [];
while ($row = $reviewsResult->fetch_assoc()) {
    $reviews[] = $row;
}

// Get quantity sold from completed orders
$qtySoldStmt = $db->prepare("
    SELECT COALESCE(SUM(quantity), 0) AS quantity_sold
    FROM orders
    WHERE product_id = ? AND status = 'completed'
");
$qtySoldStmt->bind_param('i', $productId);
$qtySoldStmt->execute();
$qtySoldResult = $qtySoldStmt->get_result()->fetch_assoc();
$quantitySold = (int) ($qtySoldResult['quantity_sold'] ?? 0);

$productImage = $product['primary_image'] ?: 'https://via.placeholder.com/480x480?text=No+Image';
$availableStock = getProductStockQuantity($product);
$deliveryFee = getProductDeliveryFee($product, 'riders');
$conversionRate = (float) $product['unique_viewers'] > 0 ? (((float) $product['total_orders']) / ((float) $product['unique_viewers'])) * 100 : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Product Profile | CampMart</title>
    <script src="tailwind34.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />
    <script id="tailwind-config">
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#F48C25',
                        'brand-green': '#064E3B',
                        'background-main': '#F9FAFB',
                        'surface-white': '#FFFFFF',
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
<body class="bg-background-main min-h-screen text-slate-900">
    <?php include_once 'includes/user-nav.php'; ?>

    <main class="flex-1 overflow-y-auto p-4 md:p-6 lg:p-8">
        <div class="max-w-7xl mx-auto space-y-6">
            <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-5">
                <div>
                    <a class="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 hover:text-brand-green mb-3" href="my-products.php">
                        <span class="material-symbols-outlined text-base">arrow_back</span>
                        Back to My Products
                    </a>
                    <h1 class="text-3xl md:text-4xl font-extrabold tracking-tight text-brand-green">Product Profile</h1>
                    <p class="text-sm sm:text-base text-slate-500 mt-2 max-w-3xl">
                        Detailed management and performance view for this product, including buyer interest, carts, orders, views, bookmarks, and customer reviews.
                    </p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a class="inline-flex items-center gap-2 rounded-2xl bg-white px-4 py-3 text-sm font-semibold text-slate-700 border border-slate-200 hover:border-primary/50 transition" href="product/<?php echo htmlspecialchars($product['slug']); ?>">
                        <span class="material-symbols-outlined text-base">visibility</span>
                        View live page
                    </a>
                    <a class="inline-flex items-center gap-2 rounded-2xl bg-primary px-4 py-3 text-sm font-bold text-white hover:bg-primary/90 transition" href="my-products.php?edit=<?php echo (int) $product['id']; ?>">
                        <span class="material-symbols-outlined text-base">edit</span>
                        Edit product
                    </a>
                </div>
            </div>

            <section class="grid grid-cols-1 xl:grid-cols-[minmax(0,1.2fr)_360px] gap-6">
                <div class="rounded-[28px] border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
                    <div class="flex flex-col lg:flex-row gap-5">
                        <img src="<?php echo htmlspecialchars($productImage); ?>" alt="<?php echo htmlspecialchars($product['title']); ?>" class="w-full lg:w-48 h-56 lg:h-48 rounded-[24px] object-cover border border-slate-200" />
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-3">
                                <span class="px-3 py-1 text-xs font-bold rounded-full <?php echo productProfileStatusClasses((string) $product['availability']); ?>">
                                    <?php echo htmlspecialchars(ucfirst((string) $product['availability'])); ?>
                                </span>
                                <span class="px-3 py-1 text-xs font-bold rounded-full <?php echo productProfileStatusClasses((string) $product['status']); ?>">
                                    <?php echo htmlspecialchars(ucfirst((string) $product['status'])); ?>
                                </span>
                            </div>
                            <h2 class="text-2xl font-extrabold text-slate-900 leading-tight"><?php echo htmlspecialchars($product['title']); ?></h2>
                            <p class="text-sm text-slate-500 mt-2"><?php echo htmlspecialchars($product['category_name'] ?: 'Uncategorized'); ?></p>

                            <div class="flex flex-wrap items-end gap-3 mt-4">
                                <p class="text-3xl font-extrabold text-brand-green"><?php echo formatCurrency((float) $product['price']); ?></p>
                                <?php if (!empty($product['original_price'])): ?>
                                    <p class="text-base text-slate-400 line-through"><?php echo formatCurrency((float) $product['original_price']); ?></p>
                                <?php endif; ?>
                            </div>

                            <p class="mt-4 text-sm leading-6 text-slate-600"><?php echo nl2br(htmlspecialchars((string) $product['description'])); ?></p>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-5 text-sm">
                                <div class="rounded-2xl bg-slate-50 px-4 py-3">
                                    <p class="text-xs uppercase tracking-wide text-slate-400 font-bold mb-1">Created</p>
                                    <p class="text-slate-700"><?php echo date('M j, Y g:i A', strtotime((string) $product['created_at'])); ?></p>
                                </div>
                                <div class="rounded-2xl bg-slate-50 px-4 py-3">
                                    <p class="text-xs uppercase tracking-wide text-slate-400 font-bold mb-1">Negotiable</p>
                                    <p class="text-slate-700"><?php echo !empty($product['negotiable']) ? 'Yes' : 'No'; ?></p>
                                </div>
                                <div class="rounded-2xl bg-slate-50 px-4 py-3 sm:col-span-2">
                                    <p class="text-xs uppercase tracking-wide text-slate-400 font-bold mb-1">Personal Delivery Fee</p>
                                    <p class="text-slate-700"><?php echo formatCurrency($deliveryFee); ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="rounded-[28px] border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <p class="text-sm font-semibold text-slate-500">Customer rating</p>
                            <p class="text-3xl font-extrabold text-slate-900 mt-2"><?php echo number_format((float) $product['avg_rating'], 1); ?></p>
                        </div>
                        <div class="rounded-2xl bg-amber-50 px-3 py-2 text-amber-500">
                            <span class="material-symbols-outlined">hotel_class</span>
                        </div>
                    </div>
                    <div class="mt-3">
                        <?php echo productProfileStars((float) $product['avg_rating']); ?>
                    </div>
                    <p class="text-sm text-slate-500 mt-2"><?php echo number_format((int) $product['review_count']); ?> review<?php echo (int) $product['review_count'] === 1 ? '' : 's'; ?></p>

                    <div class="mt-5 space-y-3">
                        <div class="rounded-2xl bg-slate-50 px-4 py-3">
                            <p class="text-xs uppercase tracking-wide text-slate-400 font-bold mb-1">Completed revenue</p>
                            <p class="text-lg font-bold text-brand-green"><?php echo formatCurrency((float) $product['completed_revenue']); ?></p>
                        </div>
                        <div class="rounded-2xl bg-slate-50 px-4 py-3">
                            <p class="text-xs uppercase tracking-wide text-slate-400 font-bold mb-1">View-to-order conversion</p>
                            <p class="text-lg font-bold text-slate-900"><?php echo number_format($conversionRate, 1); ?>%</p>
                        </div>
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-2 xl:grid-cols-4 gap-4">
                <?php
                echo productMetricBadge('Available Stock', number_format($availableStock), 'inventory_2', 'green');
                echo productMetricBadge('Unique Views', number_format((int) $product['unique_viewers']), 'visibility', 'blue');
                echo productMetricBadge('Active Carts', number_format((int) $product['cart_users']), 'shopping_cart', 'orange');
                echo productMetricBadge('Bookmarks', number_format((int) $product['bookmark_users']), 'bookmark', 'purple');
                echo productMetricBadge('Total Orders', number_format((int) $product['total_orders']), 'receipt_long', 'green');
                echo productMetricBadge('Quantity Sold', number_format($quantitySold), 'sell', 'green');
                echo productMetricBadge('Completed Sales', number_format((int) $product['completed_orders']), 'paid', 'green');
                echo productMetricBadge('Pending Orders', number_format((int) $product['pending_orders']), 'schedule', 'orange');
                echo productMetricBadge('Cart Quantity', number_format((int) $product['cart_quantity']), 'inventory_2', 'slate');
                echo productMetricBadge('Gross Value', formatCurrency((float) $product['gross_revenue']), 'payments', 'blue');
                ?>
            </section>

            <section class="grid grid-cols-1 2xl:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)] gap-6">
                <div class="rounded-[28px] border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-4 mb-5">
                        <div>
                            <h2 class="text-xl font-bold text-slate-900">Sales Pipeline</h2>
                            <p class="text-sm text-slate-500 mt-1">Every order created for this product with its buyer, amount, and fulfillment state.</p>
                        </div>
                        <a class="text-sm font-semibold text-primary hover:underline" href="my-orders.php">Open full orders page</a>
                    </div>

                    <?php if (empty($orders)): ?>
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-center text-sm text-slate-500">
                            No orders yet for this product.
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($orders as $order): ?>
                                <?php
                                $buyerName = trim((string) ($order['buyer_full_name'] ?: trim(($order['buyer_firstname'] ?? '') . ' ' . ($order['buyer_lastname'] ?? ''))));
                                $buyerName = $buyerName !== '' ? $buyerName : $order['buyer_username'];
                                ?>
                                <div class="rounded-[24px] border border-slate-200 bg-slate-50/80 p-4">
                                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap gap-2 mb-2">
                                                <span class="rounded-full bg-white px-3 py-1 text-[11px] font-bold text-slate-600 border border-slate-200">
                                                    <?php echo htmlspecialchars($order['order_number'] ?: ('#' . $order['id'])); ?>
                                                </span>
                                                <span class="rounded-full border px-3 py-1 text-[11px] font-semibold <?php echo productProfileStatusClasses((string) $order['status']); ?>">
                                                    <?php echo htmlspecialchars(ucfirst((string) $order['status'])); ?>
                                                </span>
                                            </div>
                                            <p class="text-sm font-bold text-slate-900"><?php echo htmlspecialchars($buyerName); ?></p>
                                            <p class="text-xs text-slate-500 mt-1">@<?php echo htmlspecialchars((string) $order['buyer_username']); ?></p>
                                            <p class="text-sm text-slate-600 mt-3"><?php echo htmlspecialchars((string) $order['delivery_location']); ?></p>
                                        </div>
                                        <div class="sm:text-right">
                                            <p class="text-lg font-extrabold text-brand-green"><?php echo formatCurrency((float) $order['total_amount']); ?></p>
                                            <p class="text-xs text-slate-500 mt-1"><?php echo date('M j, Y g:i A', strtotime((string) $order['created_at'])); ?></p>
                                            <p class="text-xs text-slate-500 mt-1">
                                                Payment: <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string) $order['payment_status']))); ?> |
                                                Delivery: <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', (string) $order['delivery_status']))); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="space-y-6">
                    <div class="rounded-[28px] border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
                        <div class="flex items-center justify-between gap-4 mb-4">
                            <div>
                                <h2 class="text-xl font-bold text-slate-900">Customers in Cart</h2>
                                <p class="text-sm text-slate-500 mt-1">People actively holding this product in their cart right now.</p>
                            </div>
                        </div>

                        <?php if (empty($cartUsers)): ?>
                            <p class="text-sm text-slate-500">No active cart holders yet.</p>
                        <?php else: ?>
                            <div class="space-y-3">
                                <?php foreach ($cartUsers as $person): ?>
                                    <?php $name = trim((string) ($person['full_name'] ?: trim(($person['firstname'] ?? '') . ' ' . ($person['lastname'] ?? '')))); ?>
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <div class="size-11 rounded-full overflow-hidden bg-primary/10 text-primary flex items-center justify-center font-bold shrink-0">
                                                <?php if (!empty($person['profile_image'])): ?>
                                                    <img src="<?php echo htmlspecialchars((string) $person['profile_image']); ?>" alt="<?php echo htmlspecialchars($name ?: $person['username']); ?>" class="w-full h-full object-cover" />
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars(strtoupper(substr($name ?: (string) $person['username'], 0, 1))); ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-sm font-semibold text-slate-800 truncate"><?php echo htmlspecialchars($name ?: (string) $person['username']); ?></p>
                                                <p class="text-xs text-slate-500 truncate">@<?php echo htmlspecialchars((string) $person['username']); ?></p>
                                            </div>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <p class="text-sm font-semibold text-slate-700">Qty <?php echo (int) $person['quantity']; ?></p>
                                            <p class="text-xs text-slate-500"><?php echo htmlspecialchars(ucfirst((string) $person['delivery_option'])); ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="rounded-[28px] border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
                        <h2 class="text-xl font-bold text-slate-900 mb-4">Saved by Customers</h2>
                        <?php if (empty($bookmarkUsers)): ?>
                            <p class="text-sm text-slate-500">No bookmarks yet.</p>
                        <?php else: ?>
                            <div class="space-y-3">
                                <?php foreach ($bookmarkUsers as $person): ?>
                                    <?php $name = trim((string) ($person['full_name'] ?: trim(($person['firstname'] ?? '') . ' ' . ($person['lastname'] ?? '')))); ?>
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <div class="size-11 rounded-full overflow-hidden bg-brand-green/10 text-brand-green flex items-center justify-center font-bold shrink-0">
                                                <?php if (!empty($person['profile_image'])): ?>
                                                    <img src="<?php echo htmlspecialchars((string) $person['profile_image']); ?>" alt="<?php echo htmlspecialchars($name ?: $person['username']); ?>" class="w-full h-full object-cover" />
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars(strtoupper(substr($name ?: (string) $person['username'], 0, 1))); ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-sm font-semibold text-slate-800 truncate"><?php echo htmlspecialchars($name ?: (string) $person['username']); ?></p>
                                                <p class="text-xs text-slate-500 truncate">@<?php echo htmlspecialchars((string) $person['username']); ?></p>
                                            </div>
                                        </div>
                                        <p class="text-xs text-slate-500 shrink-0"><?php echo date('M j', strtotime((string) $person['created_at'])); ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <section class="grid grid-cols-1 2xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] gap-6">
                <div class="rounded-[28px] border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
                    <h2 class="text-xl font-bold text-slate-900 mb-4">Unique Viewers</h2>
                    <p class="text-sm text-slate-500 mb-5">Logged-in users who viewed this product, ordered by their latest visit.</p>

                    <?php if (empty($viewers)): ?>
                        <p class="text-sm text-slate-500">No logged-in viewers yet.</p>
                    <?php else: ?>
                        <div class="space-y-3">
                            <?php foreach ($viewers as $person): ?>
                                <?php $name = trim((string) ($person['full_name'] ?: trim(($person['firstname'] ?? '') . ' ' . ($person['lastname'] ?? '')))); ?>
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="size-11 rounded-full overflow-hidden bg-primary/10 text-primary flex items-center justify-center font-bold shrink-0">
                                            <?php if (!empty($person['profile_image'])): ?>
                                                <img src="<?php echo htmlspecialchars((string) $person['profile_image']); ?>" alt="<?php echo htmlspecialchars($name ?: $person['username']); ?>" class="w-full h-full object-cover" />
                                            <?php else: ?>
                                                <?php echo htmlspecialchars(strtoupper(substr($name ?: (string) $person['username'], 0, 1))); ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-slate-800 truncate"><?php echo htmlspecialchars($name ?: (string) $person['username']); ?></p>
                                            <p class="text-xs text-slate-500 truncate">@<?php echo htmlspecialchars((string) $person['username']); ?></p>
                                        </div>
                                    </div>
                                    <p class="text-xs text-slate-500 shrink-0"><?php echo date('M j, g:i A', strtotime((string) $person['last_viewed_at'])); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="rounded-[28px] border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-4 mb-4">
                        <div>
                            <h2 class="text-xl font-bold text-slate-900">Ratings & Comments</h2>
                            <p class="text-sm text-slate-500 mt-1">Verified buyer feedback submitted after completed transactions.</p>
                        </div>
                        <div class="rounded-2xl bg-amber-50 px-3 py-2 text-amber-500">
                            <span class="material-symbols-outlined">reviews</span>
                        </div>
                    </div>

                    <?php if (empty($reviews)): ?>
                        <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-center text-sm text-slate-500">
                            No customer ratings yet for this product.
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($reviews as $review): ?>
                                <?php $name = trim((string) ($review['full_name'] ?: trim(($review['firstname'] ?? '') . ' ' . ($review['lastname'] ?? '')))); ?>
                                <div class="rounded-[24px] border border-slate-200 bg-slate-50/80 p-4">
                                    <div class="flex items-start justify-between gap-4">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <div class="size-11 rounded-full overflow-hidden bg-brand-green/10 text-brand-green flex items-center justify-center font-bold shrink-0">
                                                <?php if (!empty($review['profile_image'])): ?>
                                                    <img src="<?php echo htmlspecialchars((string) $review['profile_image']); ?>" alt="<?php echo htmlspecialchars($name ?: $review['username']); ?>" class="w-full h-full object-cover" />
                                                <?php else: ?>
                                                    <?php echo htmlspecialchars(strtoupper(substr($name ?: (string) $review['username'], 0, 1))); ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-sm font-semibold text-slate-800 truncate"><?php echo htmlspecialchars($name ?: (string) $review['username']); ?></p>
                                                <p class="text-xs text-slate-500 truncate">@<?php echo htmlspecialchars((string) $review['username']); ?></p>
                                            </div>
                                        </div>
                                        <p class="text-xs text-slate-500 shrink-0"><?php echo date('M j, Y', strtotime((string) $review['created_at'])); ?></p>
                                    </div>

                                    <div class="mt-3 flex items-center gap-3">
                                        <?php echo productProfileStars((float) $review['rating']); ?>
                                        <?php if (!empty($review['is_verified_purchase'])): ?>
                                            <span class="rounded-full bg-emerald-100 px-3 py-1 text-[11px] font-bold text-emerald-700">Verified purchase</span>
                                        <?php endif; ?>
                                    </div>

                                    <p class="mt-3 text-sm leading-6 text-slate-600"><?php echo nl2br(htmlspecialchars((string) $review['review_text'])); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
    </main>
</body>
</html>
