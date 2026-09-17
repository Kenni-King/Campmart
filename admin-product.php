<?php
session_start();
include_once 'includes/controller.php';

if (!isset($userId) || !isset($currentUser) || !in_array($currentUser['role'], ['admin', 'superadmin'])) {
    header('Location: index.php');
    exit;
}

$productId = (int) ($_GET['id'] ?? 0);
if ($productId < 1) {
    $_SESSION['error'] = 'Product not found.';
    header('Location: manage-products.php');
    exit;
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'update_status' && isset($_POST['new_status'])) {
        $validStatuses = ['pending', 'approved', 'rejected', 'draft', 'sold'];
        $newStatus = $_POST['new_status'];
        if (in_array($newStatus, $validStatuses, true)) {
            $updateStmt = $db->prepare("UPDATE products SET status = ? WHERE id = ?");
            $updateStmt->bind_param('si', $newStatus, $productId);
            $updateStmt->execute();
            if ($newStatus === 'approved') {
                include_once __DIR__ . '/ai/search.php';
                ai_index_product_safe($productId);
            } elseif ($newStatus === 'rejected' || $newStatus === 'sold' || $newStatus === 'draft') {
                include_once __DIR__ . '/ai/vectors.php';
                ai_delete_product_embedding($productId);
            }
            $_SESSION['success'] = "Product status changed to " . ucfirst($newStatus) . ".";
        }
    } elseif ($action === 'toggle_flag' && isset($_POST['flag'])) {
        $validFlags = ['is_featured', 'is_trending', 'is_sponsored', 'is_urgent'];
        $flag = $_POST['flag'];
        if (in_array($flag, $validFlags, true)) {
            $flagStmt = $db->prepare("UPDATE products SET $flag = NOT $flag WHERE id = ?");
            $flagStmt->bind_param('i', $productId);
            $flagStmt->execute();
            $_SESSION['success'] = "Flag updated.";
        }
    } elseif ($action === 'update_availability' && isset($_POST['new_availability'])) {
        $validAvail = ['available', 'reserved', 'sold', 'unavailable'];
        $newAvail = $_POST['new_availability'];
        if (in_array($newAvail, $validAvail, true)) {
            $availStmt = $db->prepare("UPDATE products SET availability = ? WHERE id = ?");
            $availStmt->bind_param('si', $newAvail, $productId);
            $availStmt->execute();
            $_SESSION['success'] = "Availability changed to " . ucfirst($newAvail) . ".";
        }
    } elseif ($action === 'set_rejection_reason') {
        $reason = trim($_POST['rejection_reason'] ?? '');
        $reasonStmt = $db->prepare("UPDATE products SET rejection_reason = ? WHERE id = ?");
        $reasonStmt->bind_param('si', $reason, $productId);
        $reasonStmt->execute();
        $_SESSION['success'] = "Rejection reason saved.";
    } elseif ($action === 'delete') {
        $imgStmt = $db->prepare("SELECT image_url FROM product_images WHERE product_id = ?");
        $imgStmt->bind_param('i', $productId);
        $imgStmt->execute();
        $imgs = $imgStmt->get_result();
        while ($img = $imgs->fetch_assoc()) {
            $imgPath = __DIR__ . '/' . $img['image_url'];
            if (!empty($img['image_url']) && file_exists($imgPath)) {
                @unlink($imgPath);
            }
        }
        $delImgStmt = $db->prepare("DELETE FROM product_images WHERE product_id = ?");
        $delImgStmt->bind_param('i', $productId);
        $delImgStmt->execute();
        $delStmt = $db->prepare("DELETE FROM products WHERE id = ?");
        $delStmt->bind_param('i', $productId);
        $delStmt->execute();
        $_SESSION['success'] = "Product deleted successfully.";
        header('Location: manage-products.php');
        exit;
    } elseif ($action === 'cancel_featured') {
        $subId = (int) ($_POST['subscription_id'] ?? 0);
        if ($subId > 0) {
            $stmt = $db->prepare("UPDATE featured_subscriptions SET status = 'cancelled' WHERE id = ? AND entity_type = 'product' AND entity_id = ?");
            $stmt->bind_param('ii', $subId, $productId);
            $stmt->execute();
            syncFeaturedStatus($db, 'product', $productId);
            $_SESSION['success'] = "Featured subscription cancelled.";
        }
    } elseif ($action === 'activate_featured') {
        $planId = (int) ($_POST['plan_id'] ?? 0);
        if ($planId > 0) {
            $planStmt = $db->prepare("SELECT duration_days, price FROM subscription_plans WHERE id = ? AND is_active = 1");
            $planStmt->bind_param('i', $planId);
            $planStmt->execute();
            $plan = $planStmt->get_result()->fetch_assoc();
            if ($plan) {
                $startDate = date('Y-m-d H:i:s');
                $endDate = date('Y-m-d H:i:s', strtotime($startDate . " +{$plan['duration_days']} days"));
                dbInsert('featured_subscriptions', [
                    'user_id' => $product['user_id'],
                    'entity_type' => 'product',
                    'entity_id' => $productId,
                    'plan_id' => $planId,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'amount_paid' => $plan['price'],
                    'status' => 'active',
                ]);
                syncFeaturedStatus($db, 'product', $productId);
                $_SESSION['success'] = "Featured subscription activated.";
            }
        }
    }
    header('Location: admin-product.php?id=' . $productId);
    exit;
}

// Fetch product with all analytics
$productQuery = "
    SELECT
        p.*,
        u.id AS seller_id, u.username AS seller_username, u.full_name AS seller_name,
        u.profile_image AS seller_image, u.is_verified AS seller_verified,
        u.rating AS seller_rating, u.total_sales AS seller_total_sales,
        u.location AS seller_location, u.created_at AS seller_created_at,
        c.name AS category_name, c.icon AS category_icon,
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
        COALESCE(order_stats.completed_revenue, 0) AS completed_revenue
    FROM products p
    LEFT JOIN users u ON p.user_id = u.id
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
    LEFT JOIN (
        SELECT product_id, COUNT(DISTINCT user_id) AS unique_viewers, COUNT(*) AS total_view_events
        FROM product_views GROUP BY product_id
    ) view_stats ON view_stats.product_id = p.id
    LEFT JOIN (
        SELECT product_id, COUNT(*) AS cart_users, COALESCE(SUM(quantity), 0) AS cart_quantity
        FROM cart GROUP BY product_id
    ) cart_stats ON cart_stats.product_id = p.id
    LEFT JOIN (
        SELECT product_id, COUNT(*) AS bookmark_users
        FROM bookmarks WHERE bookmark_type = 'product' GROUP BY product_id
    ) bookmark_stats ON bookmark_stats.product_id = p.id
    LEFT JOIN (
        SELECT product_id,
            COUNT(*) AS total_orders,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_orders,
            SUM(CASE WHEN status IN ('confirmed','processing') THEN 1 ELSE 0 END) AS processing_orders,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_orders,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled_orders,
            SUM(CASE WHEN status <> 'cancelled' THEN total_amount ELSE 0 END) AS gross_revenue,
            SUM(CASE WHEN status = 'completed' THEN total_amount ELSE 0 END) AS completed_revenue
        FROM orders GROUP BY product_id
    ) order_stats ON order_stats.product_id = p.id
    WHERE p.id = ?
    LIMIT 1
";
$productStmt = $db->prepare($productQuery);
$productStmt->bind_param('i', $productId);
$productStmt->execute();
$product = $productStmt->get_result()->fetch_assoc();

if (!$product) {
    $_SESSION['error'] = 'Product not found.';
    header('Location: manage-products.php');
    exit;
}

// Fetch all product images
$imagesStmt = $db->prepare("SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, display_order ASC");
$imagesStmt->bind_param('i', $productId);
$imagesStmt->execute();
$imagesResult = $imagesStmt->get_result();
$images = [];
while ($img = $imagesResult->fetch_assoc()) {
    $images[] = $img;
}

// Viewers
$viewersStmt = $db->prepare("
    SELECT u.id, u.username, u.full_name, u.firstname, u.lastname, u.profile_image, MAX(pv.viewed_at) AS last_viewed_at
    FROM product_views pv
    JOIN users u ON u.id = pv.user_id
    WHERE pv.product_id = ? AND pv.user_id IS NOT NULL
    GROUP BY u.id, u.username, u.full_name, u.firstname, u.lastname, u.profile_image
    ORDER BY last_viewed_at DESC
");
$viewersStmt->bind_param('i', $productId);
$viewersStmt->execute();
$viewers = [];
while ($row = $viewersStmt->get_result()->fetch_assoc()) {
    $viewers[] = $row;
}

// Cart users
$cartUsersStmt = $db->prepare("
    SELECT c.quantity, c.delivery_option, c.updated_at,
        u.id, u.username, u.full_name, u.firstname, u.lastname, u.profile_image
    FROM cart c
    JOIN users u ON u.id = c.user_id
    WHERE c.product_id = ?
    ORDER BY c.updated_at DESC
");
$cartUsersStmt->bind_param('i', $productId);
$cartUsersStmt->execute();
$cartUsers = [];
while ($row = $cartUsersStmt->get_result()->fetch_assoc()) {
    $cartUsers[] = $row;
}

// Bookmark users
$bookmarkUsersStmt = $db->prepare("
    SELECT b.created_at, u.id, u.username, u.full_name, u.firstname, u.lastname, u.profile_image
    FROM bookmarks b
    JOIN users u ON u.id = b.user_id
    WHERE b.product_id = ? AND b.bookmark_type = 'product'
    ORDER BY b.created_at DESC
");
$bookmarkUsersStmt->bind_param('i', $productId);
$bookmarkUsersStmt->execute();
$bookmarkUsers = [];
while ($row = $bookmarkUsersStmt->get_result()->fetch_assoc()) {
    $bookmarkUsers[] = $row;
}

// Orders
$ordersStmt = $db->prepare("
    SELECT o.*, u.username AS buyer_username, u.full_name AS buyer_full_name,
        u.firstname AS buyer_firstname, u.lastname AS buyer_lastname, u.profile_image AS buyer_profile_image
    FROM orders o
    JOIN users u ON u.id = o.buyer_id
    WHERE o.product_id = ?
    ORDER BY o.created_at DESC
");
$ordersStmt->bind_param('i', $productId);
$ordersStmt->execute();
$orders = [];
while ($row = $ordersStmt->get_result()->fetch_assoc()) {
    $orders[] = $row;
}

// Reviews
$reviewsStmt = $db->prepare("
    SELECT r.*, u.username, u.full_name, u.firstname, u.lastname, u.profile_image
    FROM reviews r
    JOIN users u ON u.id = r.reviewer_id
    WHERE r.product_id = ? AND r.review_type = 'seller' AND r.status = 'approved'
    ORDER BY r.created_at DESC
");
$reviewsStmt->bind_param('i', $productId);
$reviewsStmt->execute();
$reviews = [];
while ($row = $reviewsStmt->get_result()->fetch_assoc()) {
    $reviews[] = $row;
}

// Quantity sold
$qtySoldStmt = $db->prepare("SELECT COALESCE(SUM(quantity), 0) AS quantity_sold FROM orders WHERE product_id = ? AND status = 'completed'");
$qtySoldStmt->bind_param('i', $productId);
$qtySoldStmt->execute();
$quantitySold = (int) ($qtySoldStmt->get_result()->fetch_assoc()['quantity_sold'] ?? 0);

$availableStock = getProductStockQuantity($product);
$deliveryFee = getProductDeliveryFee($product, 'riders');
$conversionRate = (float) $product['unique_viewers'] > 0 ? (((float) $product['total_orders']) / ((float) $product['unique_viewers'])) * 100 : 0;

// Helper functions
function adminStatusBadge(string $status): string {
    $map = [
        'approved' => 'bg-emerald-100 text-emerald-700',
        'pending' => 'bg-amber-100 text-amber-700',
        'rejected' => 'bg-rose-100 text-rose-700',
        'draft' => 'bg-slate-100 text-slate-700',
        'sold' => 'bg-blue-100 text-blue-700',
        'available' => 'bg-emerald-100 text-emerald-700',
        'reserved' => 'bg-amber-100 text-amber-700',
        'unavailable' => 'bg-red-100 text-red-700',
        'confirmed' => 'bg-sky-100 text-sky-700',
        'processing' => 'bg-indigo-100 text-indigo-700',
        'completed' => 'bg-emerald-100 text-emerald-700',
        'cancelled' => 'bg-red-100 text-red-700',
    ];
    return $map[$status] ?? 'bg-slate-100 text-slate-700';
}

function adminStars(float $rating): string {
    $rounded = (int) round($rating);
    $html = '<div class="flex items-center gap-0.5 text-amber-400">';
    for ($i = 1; $i <= 5; $i++) {
        $html .= '<span class="material-symbols-outlined text-sm">' . ($i <= $rounded ? 'star' : 'star_outline') . '</span>';
    }
    $html .= '</div>';
    return $html;
}

function adminMetricCard(string $label, string $value, string $icon, string $tone = 'slate'): string {
    $toneMap = [
        'slate' => 'bg-slate-100 text-slate-600',
        'green' => 'bg-emerald-100 text-emerald-600',
        'blue' => 'bg-sky-100 text-sky-600',
        'orange' => 'bg-orange-100 text-orange-600',
        'purple' => 'bg-violet-100 text-violet-600',
    ];
    $classes = $toneMap[$tone] ?? $toneMap['slate'];
    return '<div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">'
        . '<div class="flex items-center justify-between mb-3">'
        . '<p class="text-[11px] uppercase tracking-widest text-slate-400 font-bold">' . htmlspecialchars($label) . '</p>'
        . '<span class="material-symbols-outlined text-lg px-2 py-1.5 rounded-xl ' . $classes . '">' . htmlspecialchars($icon) . '</span>'
        . '</div>'
        . '<p class="text-xl font-extrabold text-slate-900">' . htmlspecialchars($value) . '</p>'
        . '</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Product Profile | CampMart</title>
    <script src="tailwind34.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />
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
                    },
                    fontFamily: { display: ['Inter'] }
                }
            }
        }
    </script>
    <style>
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-background-main min-h-screen text-slate-900">
    <?php include_once 'includes/user-nav.php'; ?>

    <main class="flex-1 overflow-y-auto p-4 md:p-6 lg:p-8">
        <div class="max-w-7xl mx-auto space-y-6">

            <?php if (isset($_SESSION['success'])): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl flex items-center gap-3">
                    <span class="material-symbols-outlined text-emerald-600">check_circle</span>
                    <p class="flex-1 text-sm font-medium"><?= htmlspecialchars($_SESSION['success']) ?></p>
                    <button onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-800"><span class="material-symbols-outlined">close</span></button>
                </div>
            <?php unset($_SESSION['success']); endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl flex items-center gap-3">
                    <span class="material-symbols-outlined text-red-600">error</span>
                    <p class="flex-1 text-sm font-medium"><?= htmlspecialchars($_SESSION['error']) ?></p>
                    <button onclick="this.parentElement.remove()" class="text-red-600 hover:text-red-800"><span class="material-symbols-outlined">close</span></button>
                </div>
            <?php unset($_SESSION['error']); endif; ?>

            <!-- Header -->
            <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
                <div>
                    <a class="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 hover:text-brand-green mb-3" href="manage-products.php">
                        <span class="material-symbols-outlined text-base">arrow_back</span>
                        Back to Manage Products
                    </a>
                    <h1 class="text-3xl font-bold text-brand-green">Product Profile</h1>
                    <p class="text-sm text-slate-500 mt-1">Full admin view for this product. ID: <?= $productId ?></p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="<?= productUrl($product['slug']) ?>" class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-semibold text-slate-700 hover:border-primary/50 transition">
                        <span class="material-symbols-outlined text-base">visibility</span>
                        View live page
                    </a>
                    <a href="manage-products.php" class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-semibold text-slate-700 hover:border-primary/50 transition">
                        <span class="material-symbols-outlined text-base">arrow_back</span>
                        Back to list
                    </a>
                </div>
            </div>

            <!-- Product Overview + Seller Info -->
            <section class="grid grid-cols-1 xl:grid-cols-[minmax(0,1.2fr)_340px] gap-6">
                <!-- Product Details -->
                <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
                    <!-- Image Gallery -->
                    <?php if (!empty($images)): ?>
                        <div class="mb-5">
                            <div class="relative rounded-xl overflow-hidden bg-slate-100 aspect-[4/3] border border-slate-200">
                                <img id="mainImage" src="<?= htmlspecialchars($images[0]['image_url']) ?>" alt="<?= htmlspecialchars($product['title']) ?>" class="w-full h-full object-cover" />
                            </div>
                            <?php if (count($images) > 1): ?>
                                <div class="flex gap-2 mt-3 overflow-x-auto scrollbar-hide pb-1">
                                    <?php foreach ($images as $idx => $img): ?>
                                        <button onclick="document.getElementById('mainImage').src='<?= htmlspecialchars($img['image_url']) ?>'; document.querySelectorAll('.thumb-ring').forEach(e=>e.classList.remove('ring-2','ring-primary')); this.querySelector('.thumb-ring').classList.add('ring-2','ring-primary');" class="shrink-0">
                                            <div class="thumb-ring w-16 h-16 rounded-lg overflow-hidden border border-slate-200 ring-2 ring-primary <?= $idx > 0 ? '' : '' ?>">
                                                <img src="<?= htmlspecialchars($img['image_url']) ?>" class="w-full h-full object-cover" alt="Thumbnail" />
                                            </div>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="mb-5 rounded-xl bg-slate-100 aspect-[4/3] flex items-center justify-center border border-slate-200">
                            <span class="material-symbols-outlined text-5xl text-slate-300">image</span>
                        </div>
                    <?php endif; ?>

                    <!-- Badges -->
                    <div class="flex flex-wrap items-center gap-2 mb-3">
                        <span class="px-3 py-1 text-xs font-bold rounded-full <?= adminStatusBadge($product['status']) ?>">
                            <?= ucfirst($product['status']) ?>
                        </span>
                        <span class="px-3 py-1 text-xs font-bold rounded-full <?= adminStatusBadge($product['availability']) ?>">
                            <?= ucfirst($product['availability']) ?>
                        </span>
                        <span class="px-3 py-1 text-xs font-bold rounded-full bg-slate-100 text-slate-600">
                            <?= ucfirst(str_replace('_', ' ', $product['condition_type'])) ?>
                        </span>
                        <?php if ($product['is_featured']): ?>
                            <span class="px-3 py-1 text-xs font-bold rounded-full bg-amber-100 text-amber-700">Featured</span>
                        <?php endif; ?>
                        <?php if ($product['is_trending']): ?>
                            <span class="px-3 py-1 text-xs font-bold rounded-full bg-emerald-100 text-emerald-700">Trending</span>
                        <?php endif; ?>
                        <?php if ($product['is_sponsored']): ?>
                            <span class="px-3 py-1 text-xs font-bold rounded-full bg-purple-100 text-purple-700">Sponsored</span>
                        <?php endif; ?>
                        <?php if ($product['is_urgent']): ?>
                            <span class="px-3 py-1 text-xs font-bold rounded-full bg-red-100 text-red-700">Urgent</span>
                        <?php endif; ?>
                    </div>

                    <h2 class="text-2xl font-extrabold text-slate-900 leading-tight"><?= htmlspecialchars($product['title']) ?></h2>
                    <p class="text-sm text-slate-500 mt-1">
                        <span class="material-symbols-outlined text-sm align-middle"><?= htmlspecialchars($product['category_icon'] ?: 'sell') ?></span>
                        <?= htmlspecialchars($product['category_name'] ?: 'Uncategorized') ?>
                    </p>

                    <!-- Price -->
                    <div class="flex items-end gap-3 mt-4">
                        <p class="text-3xl font-extrabold text-brand-green"><?= formatCurrency((float) $product['price']) ?></p>
                        <?php if (!empty($product['original_price']) && $product['original_price'] > $product['price']): ?>
                            <p class="text-base text-slate-400 line-through"><?= formatCurrency((float) $product['original_price']) ?></p>
                            <?php
                            $discountPct = round((1 - $product['price'] / $product['original_price']) * 100);
                            ?>
                            <span class="px-2 py-0.5 bg-red-100 text-red-700 text-xs font-bold rounded-full">-<?= $discountPct ?>%</span>
                        <?php endif; ?>
                    </div>

                    <!-- Description -->
                    <div class="mt-5">
                        <h3 class="text-sm font-bold text-slate-700 mb-2">Description</h3>
                        <p class="text-sm text-slate-600 leading-relaxed whitespace-pre-line"><?= htmlspecialchars($product['description']) ?></p>
                    </div>

                    <!-- Product Meta -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mt-5">
                        <div class="rounded-xl bg-slate-50 px-4 py-3">
                            <p class="text-[11px] uppercase tracking-widest text-slate-400 font-bold mb-1">Negotiable</p>
                            <p class="text-sm font-semibold text-slate-700"><?= !empty($product['negotiable']) ? 'Yes' : 'No' ?></p>
                        </div>
                        <div class="rounded-xl bg-slate-50 px-4 py-3">
                            <p class="text-[11px] uppercase tracking-widest text-slate-400 font-bold mb-1">Location</p>
                            <p class="text-sm font-semibold text-slate-700 truncate"><?= htmlspecialchars($product['location'] ?: '—') ?></p>
                        </div>
                        <div class="rounded-xl bg-slate-50 px-4 py-3">
                            <p class="text-[11px] uppercase tracking-widest text-slate-400 font-bold mb-1">Delivery Fee</p>
                            <p class="text-sm font-semibold text-slate-700"><?= formatCurrency($deliveryFee) ?></p>
                        </div>
                        <div class="rounded-xl bg-slate-50 px-4 py-3">
                            <p class="text-[11px] uppercase tracking-widest text-slate-400 font-bold mb-1">Created</p>
                            <p class="text-sm font-semibold text-slate-700"><?= date('M j, Y g:i A', strtotime($product['created_at'])) ?></p>
                        </div>
                        <div class="rounded-xl bg-slate-50 px-4 py-3">
                            <p class="text-[11px] uppercase tracking-widest text-slate-400 font-bold mb-1">Updated</p>
                            <p class="text-sm font-semibold text-slate-700"><?= date('M j, Y g:i A', strtotime($product['updated_at'])) ?></p>
                        </div>
                        <div class="rounded-xl bg-slate-50 px-4 py-3">
                            <p class="text-[11px] uppercase tracking-widest text-slate-400 font-bold mb-1">Slug</p>
                            <p class="text-sm font-semibold text-slate-700 truncate" title="<?= htmlspecialchars($product['slug']) ?>"><?= htmlspecialchars($product['slug']) ?></p>
                        </div>
                    </div>

                    <?php if ($product['status'] === 'rejected' && !empty($product['rejection_reason'])): ?>
                        <div class="mt-4 rounded-xl bg-red-50 border border-red-200 px-4 py-3">
                            <p class="text-xs font-bold text-red-700 uppercase tracking-wide mb-1">Rejection Reason</p>
                            <p class="text-sm text-red-600"><?= htmlspecialchars($product['rejection_reason']) ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Seller Info -->
                <div class="space-y-6">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
                        <h3 class="text-sm font-bold text-slate-700 mb-4">Seller Information</h3>
                        <div class="flex items-center gap-3 mb-4">
                            <div class="size-14 rounded-full overflow-hidden bg-brand-green text-white flex items-center justify-center text-xl font-bold shrink-0">
                                <?php if (!empty($product['seller_image'])): ?>
                                    <img src="<?= htmlspecialchars($product['seller_image']) ?>" alt="<?= htmlspecialchars($product['seller_name']) ?>" class="w-full h-full object-cover" />
                                <?php else: ?>
                                    <?= strtoupper(substr($product['seller_name'] ?? 'U', 0, 1)) ?>
                                <?php endif; ?>
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-1">
                                    <p class="font-bold text-slate-900 truncate"><?= htmlspecialchars($product['seller_name'] ?: 'Unknown') ?></p>
                                    <?php if ($product['seller_verified']): ?>
                                        <span class="material-symbols-outlined text-blue-500 text-sm fill-1">verified</span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-xs text-slate-500">@<?= htmlspecialchars($product['seller_username'] ?: '—') ?></p>
                            </div>
                        </div>
                        <div class="space-y-2 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="text-slate-500">Rating</span>
                                <div class="flex items-center gap-1">
                                    <?= adminStars((float) $product['seller_rating']) ?>
                                    <span class="font-semibold text-slate-700"><?= number_format((float) $product['seller_rating'], 1) ?></span>
                                </div>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-slate-500">Total Sales</span>
                                <span class="font-semibold text-slate-700"><?= (int) $product['seller_total_sales'] ?></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-slate-500">Location</span>
                                <span class="font-semibold text-slate-700 truncate ml-4"><?= htmlspecialchars($product['seller_location'] ?: '—') ?></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-slate-500">Member Since</span>
                                <span class="font-semibold text-slate-700"><?= date('M Y', strtotime($product['seller_created_at'])) ?></span>
                            </div>
                        </div>
                        <div class="flex gap-2 mt-4">
                            <a href="seller-profile.php?username=<?= htmlspecialchars($product['seller_username']) ?>" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-slate-100 text-xs font-bold text-slate-700 hover:bg-slate-200 transition">
                                <span class="material-symbols-outlined text-sm">person</span>
                                Seller Profile
                            </a>
                            <a href="store.php?username=<?= htmlspecialchars($product['seller_username']) ?>" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-slate-100 text-xs font-bold text-slate-700 hover:bg-slate-200 transition">
                                <span class="material-symbols-outlined text-sm">storefront</span>
                                Store
                            </a>
                        </div>
                    </div>

                    <!-- Admin Actions -->
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
                        <h3 class="text-sm font-bold text-slate-700 mb-4">Admin Actions</h3>

                        <!-- Status Update -->
                        <form method="POST" class="space-y-3" onsubmit="return confirm('Change product status?')">
                            <input type="hidden" name="action" value="update_status">
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Status</label>
                            <select name="new_status" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm font-semibold focus:border-primary focus:ring-2 focus:ring-primary/20">
                                <?php foreach (['pending','approved','rejected','draft','sold'] as $opt): ?>
                                    <option value="<?= $opt ?>" <?= $product['status'] === $opt ? 'selected' : '' ?>><?= ucfirst($opt) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl bg-primary text-white text-sm font-bold hover:bg-primary/90 transition">
                                <span class="material-symbols-outlined text-sm">swap_horiz</span>
                                Update Status
                            </button>
                        </form>

                        <hr class="my-4 border-slate-100">

                        <!-- Availability Update -->
                        <form method="POST" class="space-y-3" onsubmit="return confirm('Change availability?')">
                            <input type="hidden" name="action" value="update_availability">
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Availability</label>
                            <select name="new_availability" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm font-semibold focus:border-primary focus:ring-2 focus:ring-primary/20">
                                <?php foreach (['available','reserved','sold','unavailable'] as $opt): ?>
                                    <option value="<?= $opt ?>" <?= $product['availability'] === $opt ? 'selected' : '' ?>><?= ucfirst($opt) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl bg-white border border-slate-200 text-slate-700 text-sm font-bold hover:bg-slate-50 transition">
                                <span class="material-symbols-outlined text-sm">inventory</span>
                                Update Availability
                            </button>
                        </form>

                        <hr class="my-4 border-slate-100">

                        <!-- Featured Subscription -->
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">Featured Subscription</label>
                        <?php
                            $featStmt = $db->prepare("
                                SELECT fs.*, sp.name AS plan_name FROM featured_subscriptions fs
                                JOIN subscription_plans sp ON fs.plan_id = sp.id
                                WHERE fs.entity_type = 'product' AND fs.entity_id = ? AND fs.status = 'active' AND fs.end_date > NOW()
                                ORDER BY fs.end_date DESC LIMIT 1
                            ");
                            $featStmt->bind_param('i', $productId);
                            $featStmt->execute();
                            $activeFeat = $featStmt->get_result()->fetch_assoc();

                            $plansRes = $db->query("SELECT id, name, price, duration_days FROM subscription_plans WHERE is_active = 1 AND entity_type IN ('all', 'product') ORDER BY sort_order ASC");
                            $featPlans = [];
                            if ($plansRes) { while ($pr = $plansRes->fetch_assoc()) { $featPlans[] = $pr; } }
                        ?>
                        <?php if ($activeFeat): ?>
                            <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 mb-3">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="material-symbols-outlined text-amber-600 text-sm">workspace_premium</span>
                                    <p class="text-sm font-bold text-amber-800"><?= htmlspecialchars($activeFeat['plan_name']) ?></p>
                                </div>
                                <p class="text-xs text-amber-700">Expires: <?= date('M d, Y g:i A', strtotime($activeFeat['end_date'])) ?></p>
                                <p class="text-xs text-amber-600">Paid: <?= formatCurrency((float) $activeFeat['amount_paid']) ?></p>
                            </div>
                            <form method="POST" onsubmit="return confirm('Cancel this featured subscription?')">
                                <input type="hidden" name="action" value="cancel_featured">
                                <input type="hidden" name="subscription_id" value="<?= $activeFeat['id'] ?>">
                                <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl bg-red-50 border border-red-200 text-red-600 text-sm font-bold hover:bg-red-100 hover:border-red-300 transition">
                                    <span class="material-symbols-outlined text-sm">block</span>
                                    Cancel Featured
                                </button>
                            </form>
                        <?php else: ?>
                            <?php if (!empty($featPlans)): ?>
                                <form method="POST" class="space-y-3" onsubmit="return confirm('Activate featured subscription for this product?')">
                                    <input type="hidden" name="action" value="activate_featured">
                                    <select name="plan_id" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm font-semibold focus:border-primary focus:ring-2 focus:ring-primary/20">
                                        <?php foreach ($featPlans as $fp): ?>
                                            <option value="<?= $fp['id'] ?>"><?= htmlspecialchars($fp['name']) ?> — <?= formatCurrency((float) $fp['price']) ?> (<?= $fp['duration_days'] ?>d)</option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl bg-white border border-amber-300 text-amber-700 text-sm font-bold hover:bg-amber-50 transition">
                                        <span class="material-symbols-outlined text-sm">workspace_premium</span>
                                        Activate Featured
                                    </button>
                                </form>
                            <?php else: ?>
                                <p class="text-xs text-slate-500">No active plans available. <a href="manage-plans.php" class="text-primary hover:underline">Create one</a>.</p>
                            <?php endif; ?>
                        <?php endif; ?>

                        <hr class="my-4 border-slate-100">

                        <!-- Listing Flags -->
                        <div class="grid grid-cols-2 gap-2">
                            <form method="POST">
                                <input type="hidden" name="action" value="toggle_flag">
                                <input type="hidden" name="flag" value="is_featured">
                                <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold border transition <?= $product['is_featured'] ? 'bg-amber-50 border-amber-300 text-amber-700' : 'bg-white border-slate-200 text-slate-500 hover:border-amber-300 hover:text-amber-700' ?>">
                                    <span class="material-symbols-outlined text-sm">workspace_premium</span>
                                    Featured
                                </button>
                            </form>
                            <form method="POST">
                                <input type="hidden" name="action" value="toggle_flag">
                                <input type="hidden" name="flag" value="is_trending">
                                <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold border transition <?= $product['is_trending'] ? 'bg-emerald-50 border-emerald-300 text-emerald-700' : 'bg-white border-slate-200 text-slate-500 hover:border-emerald-300 hover:text-emerald-700' ?>">
                                    <span class="material-symbols-outlined text-sm">trending_up</span>
                                    Trending
                                </button>
                            </form>
                            <form method="POST">
                                <input type="hidden" name="action" value="toggle_flag">
                                <input type="hidden" name="flag" value="is_sponsored">
                                <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold border transition <?= $product['is_sponsored'] ? 'bg-purple-50 border-purple-300 text-purple-700' : 'bg-white border-slate-200 text-slate-500 hover:border-purple-300 hover:text-purple-700' ?>">
                                    <span class="material-symbols-outlined text-sm">ads_click</span>
                                    Sponsored
                                </button>
                            </form>
                            <form method="POST">
                                <input type="hidden" name="action" value="toggle_flag">
                                <input type="hidden" name="flag" value="is_urgent">
                                <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold border transition <?= $product['is_urgent'] ? 'bg-red-50 border-red-300 text-red-700' : 'bg-white border-slate-200 text-slate-500 hover:border-red-300 hover:text-red-700' ?>">
                                    <span class="material-symbols-outlined text-sm">priority_high</span>
                                    Urgent
                                </button>
                            </form>
                        </div>

                        <hr class="my-4 border-slate-100">

                        <!-- Rejection Reason -->
                        <form method="POST" class="space-y-3">
                            <input type="hidden" name="action" value="set_rejection_reason">
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Rejection Reason</label>
                            <textarea name="rejection_reason" rows="3" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20 resize-none" placeholder="Reason for rejection (shown to seller)..."><?= htmlspecialchars($product['rejection_reason'] ?? '') ?></textarea>
                            <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl bg-white border border-slate-200 text-slate-700 text-sm font-bold hover:bg-slate-50 transition">
                                <span class="material-symbols-outlined text-sm">edit_note</span>
                                Save Reason
                            </button>
                        </form>

                        <hr class="my-4 border-slate-100">

                        <!-- Delete -->
                        <form method="POST" onsubmit="return confirm('DELETE this product permanently? This cannot be undone.')">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl bg-red-50 border border-red-200 text-red-600 text-sm font-bold hover:bg-red-100 hover:border-red-300 transition">
                                <span class="material-symbols-outlined text-sm">delete_forever</span>
                                Delete Product
                            </button>
                        </form>
                    </div>
                </div>
            </section>

            <!-- Metrics Grid -->
            <section class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-4">
                <?= adminMetricCard('Available Stock', number_format($availableStock), 'inventory_2', 'green') ?>
                <?= adminMetricCard('Unique Views', number_format((int) $product['unique_viewers']), 'visibility', 'blue') ?>
                <?= adminMetricCard('Active Carts', number_format((int) $product['cart_users']), 'shopping_cart', 'orange') ?>
                <?= adminMetricCard('Bookmarks', number_format((int) $product['bookmark_users']), 'bookmark', 'purple') ?>
                <?= adminMetricCard('Total Orders', number_format((int) $product['total_orders']), 'receipt_long', 'green') ?>
                <?= adminMetricCard('Quantity Sold', number_format($quantitySold), 'sell', 'green') ?>
                <?= adminMetricCard('Completed Sales', number_format((int) $product['completed_orders']), 'paid', 'green') ?>
                <?= adminMetricCard('Pending Orders', number_format((int) $product['pending_orders']), 'schedule', 'orange') ?>
                <?= adminMetricCard('Gross Revenue', formatCurrency((float) $product['gross_revenue']), 'payments', 'blue') ?>
                <?= adminMetricCard('Conv. Rate', number_format($conversionRate, 1) . '%', 'analytics', 'slate') ?>
            </section>

            <!-- Orders Pipeline + Cart Holders -->
            <section class="grid grid-cols-1 2xl:grid-cols-[minmax(0,1.15fr)_minmax(0,0.85fr)] gap-6">
                <!-- Orders -->
                <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-4 mb-5">
                        <div>
                            <h2 class="text-xl font-bold text-slate-900">Sales Pipeline</h2>
                            <p class="text-sm text-slate-500 mt-1">All orders for this product.</p>
                        </div>
                    </div>
                    <?php if (empty($orders)): ?>
                        <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-center text-sm text-slate-500">
                            No orders yet for this product.
                        </div>
                    <?php else: ?>
                        <div class="space-y-3 max-h-[480px] overflow-y-auto">
                            <?php foreach ($orders as $order): ?>
                                <?php
                                $buyerName = trim($order['buyer_full_name'] ?: trim(($order['buyer_firstname'] ?? '') . ' ' . ($order['buyer_lastname'] ?? '')));
                                $buyerName = $buyerName !== '' ? $buyerName : $order['buyer_username'];
                                ?>
                                <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-4">
                                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap gap-2 mb-2">
                                                <span class="rounded-full bg-white px-3 py-1 text-[11px] font-bold text-slate-600 border border-slate-200">
                                                    <?= htmlspecialchars($order['order_number'] ?: ('#' . $order['id'])) ?>
                                                </span>
                                                <span class="rounded-full border px-3 py-1 text-[11px] font-semibold <?= adminStatusBadge($order['status']) ?>">
                                                    <?= ucfirst($order['status']) ?>
                                                </span>
                                            </div>
                                            <p class="text-sm font-bold text-slate-900"><?= htmlspecialchars($buyerName) ?></p>
                                            <p class="text-xs text-slate-500">@<?= htmlspecialchars($order['buyer_username']) ?></p>
                                        </div>
                                        <div class="sm:text-right shrink-0">
                                            <p class="text-lg font-extrabold text-brand-green"><?= formatCurrency((float) $order['total_amount']) ?></p>
                                            <p class="text-xs text-slate-500"><?= date('M j, Y g:i A', strtotime($order['created_at'])) ?></p>
                                            <p class="text-xs text-slate-500">
                                                Payment: <?= ucwords(str_replace('_', ' ', $order['payment_status'])) ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Cart Holders + Bookmarks -->
                <div class="space-y-6">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
                        <h3 class="text-xl font-bold text-slate-900 mb-4">Customers in Cart</h3>
                        <?php if (empty($cartUsers)): ?>
                            <p class="text-sm text-slate-500">No active cart holders.</p>
                        <?php else: ?>
                            <div class="space-y-3 max-h-[240px] overflow-y-auto">
                                <?php foreach ($cartUsers as $person): ?>
                                    <?php $name = trim($person['full_name'] ?: trim(($person['firstname'] ?? '') . ' ' . ($person['lastname'] ?? ''))); ?>
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <div class="size-10 rounded-full overflow-hidden bg-primary/10 text-primary flex items-center justify-center font-bold text-xs shrink-0">
                                                <?php if (!empty($person['profile_image'])): ?>
                                                    <img src="<?= htmlspecialchars($person['profile_image']) ?>" class="w-full h-full object-cover" alt="" />
                                                <?php else: ?>
                                                    <?= strtoupper(substr($name ?: $person['username'], 0, 1)) ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-sm font-semibold text-slate-800 truncate"><?= htmlspecialchars($name ?: $person['username']) ?></p>
                                                <p class="text-xs text-slate-500">@<?= htmlspecialchars($person['username']) ?></p>
                                            </div>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <p class="text-sm font-semibold text-slate-700">Qty <?= (int) $person['quantity'] ?></p>
                                            <p class="text-xs text-slate-500"><?= ucfirst($person['delivery_option']) ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
                        <h3 class="text-xl font-bold text-slate-900 mb-4">Saved by Customers</h3>
                        <?php if (empty($bookmarkUsers)): ?>
                            <p class="text-sm text-slate-500">No bookmarks yet.</p>
                        <?php else: ?>
                            <div class="space-y-3 max-h-[240px] overflow-y-auto">
                                <?php foreach ($bookmarkUsers as $person): ?>
                                    <?php $name = trim($person['full_name'] ?: trim(($person['firstname'] ?? '') . ' ' . ($person['lastname'] ?? ''))); ?>
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <div class="size-10 rounded-full overflow-hidden bg-brand-green/10 text-brand-green flex items-center justify-center font-bold text-xs shrink-0">
                                                <?php if (!empty($person['profile_image'])): ?>
                                                    <img src="<?= htmlspecialchars($person['profile_image']) ?>" class="w-full h-full object-cover" alt="" />
                                                <?php else: ?>
                                                    <?= strtoupper(substr($name ?: $person['username'], 0, 1)) ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-sm font-semibold text-slate-800 truncate"><?= htmlspecialchars($name ?: $person['username']) ?></p>
                                                <p class="text-xs text-slate-500">@<?= htmlspecialchars($person['username']) ?></p>
                                            </div>
                                        </div>
                                        <p class="text-xs text-slate-500 shrink-0"><?= date('M j', strtotime($person['created_at'])) ?></p>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </section>

            <!-- Viewers + Reviews -->
            <section class="grid grid-cols-1 2xl:grid-cols-[minmax(0,0.9fr)_minmax(0,1.1fr)] gap-6">
                <!-- Viewers -->
                <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
                    <h3 class="text-xl font-bold text-slate-900 mb-1">Unique Viewers</h3>
                    <p class="text-sm text-slate-500 mb-4">Logged-in users who viewed this product.</p>
                    <?php if (empty($viewers)): ?>
                        <p class="text-sm text-slate-500">No logged-in viewers yet.</p>
                    <?php else: ?>
                        <div class="space-y-3 max-h-[400px] overflow-y-auto">
                            <?php foreach ($viewers as $person): ?>
                                <?php $name = trim($person['full_name'] ?: trim(($person['firstname'] ?? '') . ' ' . ($person['lastname'] ?? ''))); ?>
                                <div class="flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="size-10 rounded-full overflow-hidden bg-primary/10 text-primary flex items-center justify-center font-bold text-xs shrink-0">
                                            <?php if (!empty($person['profile_image'])): ?>
                                                <img src="<?= htmlspecialchars($person['profile_image']) ?>" class="w-full h-full object-cover" alt="" />
                                            <?php else: ?>
                                                <?= strtoupper(substr($name ?: $person['username'], 0, 1)) ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-slate-800 truncate"><?= htmlspecialchars($name ?: $person['username']) ?></p>
                                            <p class="text-xs text-slate-500">@<?= htmlspecialchars($person['username']) ?></p>
                                        </div>
                                    </div>
                                    <p class="text-xs text-slate-500 shrink-0"><?= date('M j, g:i A', strtotime($person['last_viewed_at'])) ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Reviews -->
                <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
                    <div class="flex items-center justify-between gap-4 mb-4">
                        <div>
                            <h3 class="text-xl font-bold text-slate-900">Ratings & Reviews</h3>
                            <p class="text-sm text-slate-500 mt-1">Verified buyer feedback.</p>
                        </div>
                        <div class="rounded-xl bg-amber-50 px-3 py-2 text-amber-500">
                            <span class="material-symbols-outlined">reviews</span>
                        </div>
                    </div>
                    <?php if (empty($reviews)): ?>
                        <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-center text-sm text-slate-500">
                            No customer ratings yet.
                        </div>
                    <?php else: ?>
                        <div class="space-y-4 max-h-[480px] overflow-y-auto">
                            <?php foreach ($reviews as $review): ?>
                                <?php $name = trim($review['full_name'] ?: trim(($review['firstname'] ?? '') . ' ' . ($review['lastname'] ?? ''))); ?>
                                <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-4">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="flex items-center gap-3 min-w-0">
                                            <div class="size-10 rounded-full overflow-hidden bg-brand-green/10 text-brand-green flex items-center justify-center font-bold text-xs shrink-0">
                                                <?php if (!empty($review['profile_image'])): ?>
                                                    <img src="<?= htmlspecialchars($review['profile_image']) ?>" class="w-full h-full object-cover" alt="" />
                                                <?php else: ?>
                                                    <?= strtoupper(substr($name ?: $review['username'], 0, 1)) ?>
                                                <?php endif; ?>
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-sm font-semibold text-slate-800 truncate"><?= htmlspecialchars($name ?: $review['username']) ?></p>
                                                <p class="text-xs text-slate-500">@<?= htmlspecialchars($review['username']) ?></p>
                                            </div>
                                        </div>
                                        <p class="text-xs text-slate-500 shrink-0"><?= date('M j, Y', strtotime($review['created_at'])) ?></p>
                                    </div>
                                    <div class="mt-2 flex items-center gap-2">
                                        <?= adminStars((float) $review['rating']) ?>
                                        <?php if (!empty($review['is_verified_purchase'])): ?>
                                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-700">Verified purchase</span>
                                        <?php endif; ?>
                                    </div>
                                    <p class="mt-2 text-sm text-slate-600 leading-relaxed"><?= nl2br(htmlspecialchars($review['review_text'])) ?></p>
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
