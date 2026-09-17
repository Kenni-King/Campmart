<?php
session_start();
include_once 'includes/controller.php';

if (!isset($userId) || !isset($currentUser) || !in_array($currentUser['role'], ['admin', 'superadmin'])) {
    header('Location: index.php');
    exit;
}

$targetId = (int) ($_GET['id'] ?? 0);
if ($targetId < 1) {
    $_SESSION['error'] = 'User not found.';
    header('Location: manage-users.php');
    exit;
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'update_status' && isset($_POST['new_status'])) {
        $validStatuses = ['active', 'suspended', 'banned'];
        $newStatus = $_POST['new_status'];
        if ((int)$userId === $targetId) {
            $_SESSION['error'] = 'You cannot change your own status.';
        } elseif (in_array($newStatus, $validStatuses, true)) {
            $stmt = $db->prepare("UPDATE users SET status = ? WHERE id = ?");
            $stmt->bind_param('si', $newStatus, $targetId);
            $stmt->execute();
            $_SESSION['success'] = "User status changed to " . ucfirst($newStatus) . ".";
        }
    } elseif ($action === 'update_role' && isset($_POST['new_role'])) {
        $validRoles = ['user', 'admin', 'seller'];
        $newRole = $_POST['new_role'];
        if (in_array($newRole, $validRoles, true)) {
            $stmt = $db->prepare("UPDATE users SET role = ? WHERE id = ?");
            $stmt->bind_param('si', $newRole, $targetId);
            $stmt->execute();
            $_SESSION['success'] = "User role changed to " . ucfirst($newRole) . ".";
        }
    } elseif ($action === 'cancel_featured') {
        $subId = (int) ($_POST['subscription_id'] ?? 0);
        if ($subId > 0) {
            $stmt = $db->prepare("UPDATE featured_subscriptions SET status = 'cancelled' WHERE id = ? AND entity_type = 'profile' AND entity_id = ?");
            $stmt->bind_param('ii', $subId, $targetId);
            $stmt->execute();
            syncFeaturedStatus($db, 'profile', $targetId);
            $_SESSION['success'] = "Featured profile subscription cancelled.";
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
                    'user_id' => $targetId,
                    'entity_type' => 'profile',
                    'entity_id' => $targetId,
                    'plan_id' => $planId,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'amount_paid' => $plan['price'],
                    'status' => 'active',
                ]);
                syncFeaturedStatus($db, 'profile', $targetId);
                $_SESSION['success'] = "Featured profile subscription activated.";
            }
        }
    }
    header('Location: admin-user.php?id=' . $targetId);
    exit;
}

// Fetch user
$userStmt = $db->prepare("
    SELECT id, username, email, full_name, phone, role, status,
           profile_image, bio, location, university_id, student_id, department, level,
           is_verified, is_featured, rating, total_ratings, total_sales, total_purchases,
           account_balance, created_at, last_login, last_seen
    FROM users WHERE id = ? LIMIT 1
");
$userStmt->bind_param('i', $targetId);
$userStmt->execute();
$user = $userStmt->get_result()->fetch_assoc();

if (!$user) {
    $_SESSION['error'] = 'User not found.';
    header('Location: manage-users.php');
    exit;
}

// University name
$uniName = '—';
if ($user['university_id']) {
    $uniStmt = $db->prepare("SELECT name FROM universities WHERE id = ? LIMIT 1");
    $uniStmt->bind_param('i', $user['university_id']);
    $uniStmt->execute();
    $uniRow = $uniStmt->get_result()->fetch_assoc();
    $uniName = $uniRow['name'] ?? '—';
}

// Products count + recent
$prodCountStmt = $db->prepare("SELECT COUNT(*) AS cnt FROM products WHERE user_id = ?");
$prodCountStmt->bind_param('i', $targetId);
$prodCountStmt->execute();
$productCount = (int) $prodCountStmt->get_result()->fetch_assoc()['cnt'];

$recentProductsStmt = $db->prepare("
    SELECT p.id, p.title, p.slug, p.price, p.status, p.availability, p.created_at,
           pi.image_url
    FROM products p
    LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
    WHERE p.user_id = ?
    ORDER BY p.created_at DESC LIMIT 10
");
$recentProductsStmt->bind_param('i', $targetId);
$recentProductsStmt->execute();
$recentProducts = $recentProductsStmt->get_result();

// Services count + recent
$servCountStmt = $db->prepare("SELECT COUNT(*) AS cnt FROM services WHERE user_id = ?");
$servCountStmt->bind_param('i', $targetId);
$servCountStmt->execute();
$serviceCount = (int) $servCountStmt->get_result()->fetch_assoc()['cnt'];

$recentServicesStmt = $db->prepare("
    SELECT s.id, s.title, s.slug, s.price, s.status, s.availability, s.created_at
    FROM services s
    WHERE s.user_id = ?
    ORDER BY s.created_at DESC LIMIT 10
");
$recentServicesStmt->bind_param('i', $targetId);
$recentServicesStmt->execute();
$recentServices = $recentServicesStmt->get_result();

// Orders as buyer
$buyerOrderCountStmt = $db->prepare("SELECT COUNT(*) AS cnt FROM orders WHERE buyer_id = ?");
$buyerOrderCountStmt->bind_param('i', $targetId);
$buyerOrderCountStmt->execute();
$buyerOrderCount = (int) $buyerOrderCountStmt->get_result()->fetch_assoc()['cnt'];

$recentBuyerOrdersStmt = $db->prepare("
    SELECT o.id, o.order_number, o.total_amount, o.status, o.created_at,
           p.title AS product_title
    FROM orders o
    LEFT JOIN products p ON o.product_id = p.id
    WHERE o.buyer_id = ?
    ORDER BY o.created_at DESC LIMIT 10
");
$recentBuyerOrdersStmt->bind_param('i', $targetId);
$recentBuyerOrdersStmt->execute();
$recentBuyerOrders = $recentBuyerOrdersStmt->get_result();

// Orders as seller
$sellerOrderCountStmt = $db->prepare("SELECT COUNT(*) AS cnt FROM orders WHERE seller_id = ?");
$sellerOrderCountStmt->bind_param('i', $targetId);
$sellerOrderCountStmt->execute();
$sellerOrderCount = (int) $sellerOrderCountStmt->get_result()->fetch_assoc()['cnt'];

$recentSellerOrdersStmt = $db->prepare("
    SELECT o.id, o.order_number, o.total_amount, o.status, o.created_at,
           p.title AS product_title, u.username AS buyer_username
    FROM orders o
    LEFT JOIN products p ON o.product_id = p.id
    LEFT JOIN users u ON o.buyer_id = u.id
    WHERE o.seller_id = ?
    ORDER BY o.created_at DESC LIMIT 10
");
$recentSellerOrdersStmt->bind_param('i', $targetId);
$recentSellerOrdersStmt->execute();
$recentSellerOrders = $recentSellerOrdersStmt->get_result();

// Reviews received
$reviewCountStmt = $db->prepare("SELECT COUNT(*) AS cnt FROM reviews WHERE reviewed_user_id = ? AND review_type = 'seller'");
$reviewCountStmt->bind_param('i', $targetId);
$reviewCountStmt->execute();
$reviewCount = (int) $reviewCountStmt->get_result()->fetch_assoc()['cnt'];

$recentReviewsStmt = $db->prepare("
    SELECT r.rating, r.review_text, r.created_at, r.is_verified_purchase,
           u.username AS reviewer_username, u.full_name AS reviewer_name, u.profile_image AS reviewer_image
    FROM reviews r
    JOIN users u ON r.reviewer_id = u.id
    WHERE r.reviewed_user_id = ? AND r.review_type = 'seller' AND r.status = 'approved'
    ORDER BY r.created_at DESC LIMIT 10
");
$recentReviewsStmt->bind_param('i', $targetId);
$recentReviewsStmt->execute();
$recentReviews = $recentReviewsStmt->get_result();

// Bookmarks count
$bookmarkStmt = $db->prepare("SELECT COUNT(*) AS cnt FROM bookmarks WHERE user_id = ?");
$bookmarkStmt->bind_param('i', $targetId);
$bookmarkStmt->execute();
$bookmarkCount = (int) $bookmarkStmt->get_result()->fetch_assoc()['cnt'];

// Helpers
function userStatusBadge(string $status): string {
    $map = [
        'active' => 'bg-emerald-100 text-emerald-700',
        'pending' => 'bg-amber-100 text-amber-700',
        'suspended' => 'bg-rose-100 text-rose-700',
        'banned' => 'bg-red-100 text-red-700',
    ];
    return $map[$status] ?? 'bg-slate-100 text-slate-700';
}

function userRoleBadge(string $role): string {
    $map = [
        'admin' => 'bg-purple-100 text-purple-700',
        'superadmin' => 'bg-purple-100 text-purple-700',
        'seller' => 'bg-blue-100 text-blue-700',
        'user' => 'bg-slate-100 text-slate-700',
    ];
    return $map[$role] ?? 'bg-slate-100 text-slate-700';
}

function userStars(float $rating): string {
    $rounded = (int) round($rating);
    $html = '<div class="flex items-center gap-0.5 text-amber-400">';
    for ($i = 1; $i <= 5; $i++) {
        $html .= '<span class="material-symbols-outlined text-sm">' . ($i <= $rounded ? 'star' : 'star_outline') . '</span>';
    }
    $html .= '</div>';
    return $html;
}

function userMetricCard(string $label, string $value, string $icon, string $tone = 'slate'): string {
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

function productStatusBadge(string $status): string {
    $map = [
        'approved' => 'bg-emerald-100 text-emerald-700',
        'pending' => 'bg-amber-100 text-amber-700',
        'rejected' => 'bg-rose-100 text-rose-700',
        'draft' => 'bg-slate-100 text-slate-700',
        'sold' => 'bg-blue-100 text-blue-700',
        'available' => 'bg-emerald-100 text-emerald-700',
        'reserved' => 'bg-amber-100 text-amber-700',
        'unavailable' => 'bg-red-100 text-red-700',
        'active' => 'bg-emerald-100 text-emerald-700',
        'paused' => 'bg-amber-100 text-amber-700',
        'inactive' => 'bg-slate-100 text-slate-700',
        'busy' => 'bg-blue-100 text-blue-700',
        'confirmed' => 'bg-sky-100 text-sky-700',
        'processing' => 'bg-indigo-100 text-indigo-700',
        'completed' => 'bg-emerald-100 text-emerald-700',
        'cancelled' => 'bg-red-100 text-red-700',
        'pending' => 'bg-amber-100 text-amber-700',
    ];
    return $map[$status] ?? 'bg-slate-100 text-slate-700';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>User Profile | CampMart Admin</title>
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
                    <a class="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 hover:text-brand-green mb-3" href="manage-users.php">
                        <span class="material-symbols-outlined text-base">arrow_back</span>
                        Back to Manage Users
                    </a>
                    <h1 class="text-3xl font-bold text-brand-green">User Profile</h1>
                    <p class="text-sm text-slate-500 mt-1">Account details and activity for this user. ID: <?= $targetId ?></p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="seller-profile.php?username=<?= htmlspecialchars($user['username']) ?>" class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-semibold text-slate-700 hover:border-primary/50 transition">
                        <span class="material-symbols-outlined text-base">person</span>
                        Public Profile
                    </a>
                    <a href="store.php?username=<?= htmlspecialchars($user['username']) ?>" class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-semibold text-slate-700 hover:border-primary/50 transition">
                        <span class="material-symbols-outlined text-base">storefront</span>
                        Store
                    </a>
                </div>
            </div>

            <!-- Profile + Actions -->
            <section class="grid grid-cols-1 xl:grid-cols-[minmax(0,1.2fr)_340px] gap-6">
                <!-- Profile Details -->
                <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
                    <div class="flex flex-col sm:flex-row gap-5">
                        <div class="size-20 rounded-full overflow-hidden bg-brand-green text-white flex items-center justify-center text-3xl font-bold shrink-0">
                            <?php if (!empty($user['profile_image'])): ?>
                                <img src="<?= htmlspecialchars($user['profile_image']) ?>" alt="<?= htmlspecialchars($user['full_name']) ?>" class="w-full h-full object-cover" />
                            <?php else: ?>
                                <?= strtoupper(substr($user['full_name'] ?? 'U', 0, 1)) ?>
                            <?php endif; ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                <span class="px-3 py-1 text-xs font-bold rounded-full <?= userStatusBadge($user['status']) ?>">
                                    <?= ucfirst($user['status']) ?>
                                </span>
                                <span class="px-3 py-1 text-xs font-bold rounded-full <?= userRoleBadge($user['role']) ?>">
                                    <?= $user['role'] === 'user' ? 'Buyer' : ucfirst($user['role']) ?>
                                </span>
                                <?php if ($user['is_verified']): ?>
                                    <span class="px-3 py-1 text-xs font-bold rounded-full bg-blue-100 text-blue-700">Verified</span>
                                <?php endif; ?>
                                <?php if (!empty($user['is_featured'])): ?>
                                    <span class="px-3 py-1 text-xs font-bold rounded-full bg-amber-100 text-amber-700">Featured</span>
                                <?php endif; ?>
                            </div>
                            <h2 class="text-2xl font-extrabold text-slate-900 leading-tight"><?= htmlspecialchars($user['full_name'] ?: $user['username']) ?></h2>
                            <p class="text-sm text-slate-500">@<?= htmlspecialchars($user['username']) ?></p>

                            <?php if (!empty($user['bio'])): ?>
                                <p class="mt-3 text-sm text-slate-600 leading-relaxed"><?= htmlspecialchars($user['bio']) ?></p>
                            <?php endif; ?>

                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mt-5">
                                <div class="rounded-xl bg-slate-50 px-4 py-3">
                                    <p class="text-[11px] uppercase tracking-widest text-slate-400 font-bold mb-1">Email</p>
                                    <p class="text-sm font-semibold text-slate-700 break-all"><?= htmlspecialchars($user['email']) ?></p>
                                </div>
                                <div class="rounded-xl bg-slate-50 px-4 py-3">
                                    <p class="text-[11px] uppercase tracking-widest text-slate-400 font-bold mb-1">Phone</p>
                                    <p class="text-sm font-semibold text-slate-700"><?= htmlspecialchars($user['phone'] ?: '—') ?></p>
                                </div>
                                <div class="rounded-xl bg-slate-50 px-4 py-3">
                                    <p class="text-[11px] uppercase tracking-widest text-slate-400 font-bold mb-1">Location</p>
                                    <p class="text-sm font-semibold text-slate-700"><?= htmlspecialchars($user['location'] ?: '—') ?></p>
                                </div>
                                <div class="rounded-xl bg-slate-50 px-4 py-3">
                                    <p class="text-[11px] uppercase tracking-widest text-slate-400 font-bold mb-1">Department</p>
                                    <p class="text-sm font-semibold text-slate-700"><?= htmlspecialchars($user['department'] ?: '—') ?></p>
                                </div>
                                <div class="rounded-xl bg-slate-50 px-4 py-3">
                                    <p class="text-[11px] uppercase tracking-widest text-slate-400 font-bold mb-1">Level</p>
                                    <p class="text-sm font-semibold text-slate-700"><?= htmlspecialchars($user['level'] ?: '—') ?></p>
                                </div>
                                <div class="rounded-xl bg-slate-50 px-4 py-3">
                                    <p class="text-[11px] uppercase tracking-widest text-slate-400 font-bold mb-1">Student ID</p>
                                    <p class="text-sm font-semibold text-slate-700"><?= htmlspecialchars($user['student_id'] ?: '—') ?></p>
                                </div>
                                <div class="rounded-xl bg-slate-50 px-4 py-3">
                                    <p class="text-[11px] uppercase tracking-widest text-slate-400 font-bold mb-1">University</p>
                                    <p class="text-sm font-semibold text-slate-700"><?= htmlspecialchars($uniName) ?></p>
                                </div>
                                <div class="rounded-xl bg-slate-50 px-4 py-3">
                                    <p class="text-[11px] uppercase tracking-widest text-slate-400 font-bold mb-1">Joined</p>
                                    <p class="text-sm font-semibold text-slate-700"><?= date('M j, Y', strtotime($user['created_at'])) ?></p>
                                </div>
                                <div class="rounded-xl bg-slate-50 px-4 py-3">
                                    <p class="text-[11px] uppercase tracking-widest text-slate-400 font-bold mb-1">Last Login</p>
                                    <p class="text-sm font-semibold text-slate-700"><?= $user['last_login'] ? date('M j, Y g:i A', strtotime($user['last_login'])) : '—' ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Admin Actions -->
                <div class="space-y-6">
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
                        <h3 class="text-sm font-bold text-slate-700 mb-4">Admin Actions</h3>

                        <!-- Status Update -->
                        <form method="POST" class="space-y-3" onsubmit="return confirm('Change this user\'s status?')">
                            <input type="hidden" name="action" value="update_status">
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Account Status</label>
                            <select name="new_status" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm font-semibold focus:border-primary focus:ring-2 focus:ring-primary/20">
                                <?php foreach (['active','suspended','banned'] as $opt): ?>
                                    <option value="<?= $opt ?>" <?= $user['status'] === $opt ? 'selected' : '' ?>><?= ucfirst($opt) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl bg-primary text-white text-sm font-bold hover:bg-primary/90 transition">
                                <span class="material-symbols-outlined text-sm">swap_horiz</span>
                                Update Status
                            </button>
                        </form>

                        <hr class="my-4 border-slate-100">

                        <!-- Role Update -->
                        <form method="POST" class="space-y-3" onsubmit="return confirm('Change this user\'s role?')">
                            <input type="hidden" name="action" value="update_role">
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Role</label>
                            <select name="new_role" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm font-semibold focus:border-primary focus:ring-2 focus:ring-primary/20">
                                <?php foreach (['user','seller','admin'] as $opt): ?>
                                    <option value="<?= $opt ?>" <?= $user['role'] === $opt ? 'selected' : '' ?>><?= $opt === 'user' ? 'Buyer' : ucfirst($opt) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl bg-white border border-slate-200 text-slate-700 text-sm font-bold hover:bg-slate-50 transition">
                                <span class="material-symbols-outlined text-sm">admin_panel_settings</span>
                                Update Role
                            </button>
                        </form>

                        <hr class="my-4 border-slate-100">

                        <!-- Featured Subscription -->
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">Featured Profile</label>
                        <?php
                            $featStmt = $db->prepare("
                                SELECT fs.*, sp.name AS plan_name FROM featured_subscriptions fs
                                JOIN subscription_plans sp ON fs.plan_id = sp.id
                                WHERE fs.entity_type = 'profile' AND fs.entity_id = ? AND fs.status = 'active' AND fs.end_date > NOW()
                                ORDER BY fs.end_date DESC LIMIT 1
                            ");
                            $featStmt->bind_param('i', $targetId);
                            $featStmt->execute();
                            $activeFeat = $featStmt->get_result()->fetch_assoc();

                            $plansRes = $db->query("SELECT id, name, price, duration_days FROM subscription_plans WHERE is_active = 1 AND entity_type IN ('all', 'profile') ORDER BY sort_order ASC");
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
                            <form method="POST" onsubmit="return confirm('Cancel this featured profile subscription?')">
                                <input type="hidden" name="action" value="cancel_featured">
                                <input type="hidden" name="subscription_id" value="<?= $activeFeat['id'] ?>">
                                <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl bg-red-50 border border-red-200 text-red-600 text-sm font-bold hover:bg-red-100 hover:border-red-300 transition">
                                    <span class="material-symbols-outlined text-sm">block</span>
                                    Cancel Featured
                                </button>
                            </form>
                        <?php else: ?>
                            <?php if (!empty($featPlans)): ?>
                                <form method="POST" class="space-y-3" onsubmit="return confirm('Activate featured profile subscription for this user?')">
                                    <input type="hidden" name="action" value="activate_featured">
                                    <select name="plan_id" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm font-semibold focus:border-primary focus:ring-2 focus:ring-primary/20">
                                        <?php foreach ($featPlans as $fp): ?>
                                            <option value="<?= $fp['id'] ?>"><?= htmlspecialchars($fp['name']) ?> — <?= formatCurrency((float) $fp['price']) ?> (<?= $fp['duration_days'] ?>d)</option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl bg-white border border-amber-300 text-amber-700 text-sm font-bold hover:bg-amber-50 transition">
                                        <span class="material-symbols-outlined text-sm">workspace_premium</span>
                                        Activate Featured Profile
                                    </button>
                                </form>
                            <?php else: ?>
                                <p class="text-xs text-slate-500">No active plans available. <a href="manage-plans.php" class="text-primary hover:underline">Create one</a>.</p>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Status Banner -->
                    <?php if ($user['status'] === 'suspended'): ?>
                        <div class="rounded-2xl bg-rose-50 border border-rose-200 p-4 flex items-center gap-3">
                            <span class="material-symbols-outlined text-rose-600">pause_circle</span>
                            <div>
                                <p class="font-bold text-rose-800 text-sm">Suspended</p>
                                <p class="text-xs text-rose-600">This user cannot perform any actions on the platform.</p>
                            </div>
                        </div>
                    <?php elseif ($user['status'] === 'banned'): ?>
                        <div class="rounded-2xl bg-red-50 border border-red-200 p-4 flex items-center gap-3">
                            <span class="material-symbols-outlined text-red-600">block</span>
                            <div>
                                <p class="font-bold text-red-800 text-sm">Banned</p>
                                <p class="text-xs text-red-600">This user is permanently banned from the platform.</p>
                            </div>
                        </div>
                    <?php elseif ($user['status'] === 'active'): ?>
                        <div class="rounded-2xl bg-emerald-50 border border-emerald-200 p-4 flex items-center gap-3">
                            <span class="material-symbols-outlined text-emerald-600">check_circle</span>
                            <div>
                                <p class="font-bold text-emerald-800 text-sm">Active</p>
                                <p class="text-xs text-emerald-600">This account is in good standing.</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Metrics Grid -->
            <section class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-4">
                <?= userMetricCard('Total Sales', number_format((int) $user['total_sales']), 'sell', 'green') ?>
                <?= userMetricCard('Total Purchases', number_format((int) $user['total_purchases']), 'shopping_bag', 'blue') ?>
                <?= userMetricCard('Account Balance', formatCurrency((float) $user['account_balance']), 'account_balance_wallet', 'orange') ?>
                <?= userMetricCard('Rating', number_format((float) $user['rating'], 2) . ' (' . (int) $user['total_ratings'] . ')', 'star', 'slate') ?>
                <?= userMetricCard('Products Listed', number_format($productCount), 'inventory', 'green') ?>
                <?= userMetricCard('Services Listed', number_format($serviceCount), 'work', 'blue') ?>
                <?= userMetricCard('Orders as Buyer', number_format($buyerOrderCount), 'receipt_long', 'orange') ?>
                <?= userMetricCard('Orders as Seller', number_format($sellerOrderCount), 'point_of_sale', 'green') ?>
                <?= userMetricCard('Bookmarks', number_format($bookmarkCount), 'bookmark', 'purple') ?>
                <?= userMetricCard('Reviews Received', number_format($reviewCount), 'reviews', 'slate') ?>
            </section>

            <!-- Recent Products -->
            <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-xl font-bold text-slate-900">Products (<?= $productCount ?>)</h2>
                        <p class="text-sm text-slate-500 mt-1">Most recent product listings by this user.</p>
                    </div>
                    <a href="manage-products.php?search=<?= urlencode($user['username']) ?>" class="text-sm font-semibold text-primary hover:underline">View all</a>
                </div>
                <?php if ($recentProducts->num_rows === 0): ?>
                    <p class="text-sm text-slate-500">No products listed yet.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-slate-600">
                                <tr>
                                    <th class="px-4 py-2 text-left font-semibold">Product</th>
                                    <th class="px-4 py-2 text-left font-semibold">Price</th>
                                    <th class="px-4 py-2 text-left font-semibold">Status</th>
                                    <th class="px-4 py-2 text-left font-semibold">Availability</th>
                                    <th class="px-4 py-2 text-left font-semibold">Created</th>
                                    <th class="px-4 py-2 text-right font-semibold">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php while ($p = $recentProducts->fetch_assoc()): ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-4 py-2.5">
                                            <div class="flex items-center gap-3">
                                                <?php if (!empty($p['image_url'])): ?>
                                                    <img src="<?= htmlspecialchars($p['image_url']) ?>" class="w-9 h-9 rounded-lg object-cover border border-slate-200" alt="" />
                                                <?php else: ?>
                                                    <span class="material-symbols-outlined text-slate-400 text-lg">image</span>
                                                <?php endif; ?>
                                                <span class="font-semibold text-slate-800 truncate max-w-[200px]"><?= htmlspecialchars($p['title']) ?></span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-2.5 font-semibold text-slate-700"><?= formatCurrency((float) $p['price']) ?></td>
                                        <td class="px-4 py-2.5"><span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= productStatusBadge($p['status']) ?>"><?= ucfirst($p['status']) ?></span></td>
                                        <td class="px-4 py-2.5"><span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= productStatusBadge($p['availability']) ?>"><?= ucfirst($p['availability']) ?></span></td>
                                        <td class="px-4 py-2.5 text-xs text-slate-500"><?= date('M j, Y', strtotime($p['created_at'])) ?></td>
                                        <td class="px-4 py-2.5 text-right"><a href="admin-product.php?id=<?= (int) $p['id'] ?>" class="text-xs font-semibold text-primary hover:underline">View</a></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Recent Services -->
            <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-xl font-bold text-slate-900">Services (<?= $serviceCount ?>)</h2>
                        <p class="text-sm text-slate-500 mt-1">Most recent service listings by this user.</p>
                    </div>
                    <a href="manage-services.php?search=<?= urlencode($user['username']) ?>" class="text-sm font-semibold text-primary hover:underline">View all</a>
                </div>
                <?php if ($recentServices->num_rows === 0): ?>
                    <p class="text-sm text-slate-500">No services listed yet.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-slate-200 text-sm">
                            <thead class="bg-slate-50 text-slate-600">
                                <tr>
                                    <th class="px-4 py-2 text-left font-semibold">Service</th>
                                    <th class="px-4 py-2 text-left font-semibold">Price</th>
                                    <th class="px-4 py-2 text-left font-semibold">Status</th>
                                    <th class="px-4 py-2 text-left font-semibold">Availability</th>
                                    <th class="px-4 py-2 text-left font-semibold">Created</th>
                                    <th class="px-4 py-2 text-right font-semibold">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                <?php while ($s = $recentServices->fetch_assoc()): ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-4 py-2.5 font-semibold text-slate-800 truncate max-w-[240px]"><?= htmlspecialchars($s['title']) ?></td>
                                        <td class="px-4 py-2.5 font-semibold text-slate-700"><?= formatCurrency((float) $s['price']) ?></td>
                                        <td class="px-4 py-2.5"><span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= productStatusBadge($s['status']) ?>"><?= ucfirst($s['status']) ?></span></td>
                                        <td class="px-4 py-2.5"><span class="px-2 py-0.5 rounded-full text-xs font-semibold <?= productStatusBadge($s['availability']) ?>"><?= ucfirst($s['availability']) ?></span></td>
                                        <td class="px-4 py-2.5 text-xs text-slate-500"><?= date('M j, Y', strtotime($s['created_at'])) ?></td>
                                        <td class="px-4 py-2.5 text-right"><a href="admin-service.php?id=<?= (int) $s['id'] ?>" class="text-xs font-semibold text-primary hover:underline">View</a></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Orders as Buyer + Seller -->
            <section class="grid grid-cols-1 2xl:grid-cols-2 gap-6">
                <!-- Buyer Orders -->
                <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h2 class="text-xl font-bold text-slate-900">Orders as Buyer (<?= $buyerOrderCount ?>)</h2>
                            <p class="text-sm text-slate-500 mt-1">Recent purchase orders.</p>
                        </div>
                    </div>
                    <?php if ($recentBuyerOrders->num_rows === 0): ?>
                        <p class="text-sm text-slate-500">No purchase orders yet.</p>
                    <?php else: ?>
                        <div class="space-y-3 max-h-[400px] overflow-y-auto">
                            <?php while ($o = $recentBuyerOrders->fetch_assoc()): ?>
                                <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-4">
                                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap gap-2 mb-1">
                                                <span class="rounded-full bg-white px-2.5 py-0.5 text-[11px] font-bold text-slate-600 border border-slate-200"><?= htmlspecialchars($o['order_number'] ?: ('#' . $o['id'])) ?></span>
                                                <span class="rounded-full border px-2.5 py-0.5 text-[11px] font-semibold <?= productStatusBadge($o['status']) ?>"><?= ucfirst($o['status']) ?></span>
                                            </div>
                                            <p class="text-sm font-semibold text-slate-800 truncate"><?= htmlspecialchars($o['product_title'] ?: 'Unknown product') ?></p>
                                        </div>
                                        <div class="shrink-0 sm:text-right">
                                            <p class="text-base font-extrabold text-brand-green"><?= formatCurrency((float) $o['total_amount']) ?></p>
                                            <p class="text-xs text-slate-500"><?= date('M j, Y', strtotime($o['created_at'])) ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Seller Orders -->
                <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
                    <div class="flex items-center justify-between mb-4">
                        <div>
                            <h2 class="text-xl font-bold text-slate-900">Orders as Seller (<?= $sellerOrderCount ?>)</h2>
                            <p class="text-sm text-slate-500 mt-1">Recent orders received from buyers.</p>
                        </div>
                    </div>
                    <?php if ($recentSellerOrders->num_rows === 0): ?>
                        <p class="text-sm text-slate-500">No seller orders yet.</p>
                    <?php else: ?>
                        <div class="space-y-3 max-h-[400px] overflow-y-auto">
                            <?php while ($o = $recentSellerOrders->fetch_assoc()): ?>
                                <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-4">
                                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-2">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap gap-2 mb-1">
                                                <span class="rounded-full bg-white px-2.5 py-0.5 text-[11px] font-bold text-slate-600 border border-slate-200"><?= htmlspecialchars($o['order_number'] ?: ('#' . $o['id'])) ?></span>
                                                <span class="rounded-full border px-2.5 py-0.5 text-[11px] font-semibold <?= productStatusBadge($o['status']) ?>"><?= ucfirst($o['status']) ?></span>
                                            </div>
                                            <p class="text-sm font-semibold text-slate-800 truncate"><?= htmlspecialchars($o['product_title'] ?: 'Unknown product') ?></p>
                                            <p class="text-xs text-slate-500">Buyer: @<?= htmlspecialchars($o['buyer_username'] ?: '—') ?></p>
                                        </div>
                                        <div class="shrink-0 sm:text-right">
                                            <p class="text-base font-extrabold text-brand-green"><?= formatCurrency((float) $o['total_amount']) ?></p>
                                            <p class="text-xs text-slate-500"><?= date('M j, Y', strtotime($o['created_at'])) ?></p>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Reviews -->
            <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-xl font-bold text-slate-900">Reviews (<?= $reviewCount ?>)</h2>
                        <p class="text-sm text-slate-500 mt-1">Verified buyer feedback for this seller.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <?= userStars((float) $user['rating']) ?>
                        <span class="text-sm font-bold text-slate-700"><?= number_format((float) $user['rating'], 1) ?></span>
                    </div>
                </div>
                <?php if ($recentReviews->num_rows === 0): ?>
                    <p class="text-sm text-slate-500">No reviews yet.</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php while ($r = $recentReviews->fetch_assoc()): ?>
                            <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <div class="size-10 rounded-full overflow-hidden bg-primary/10 text-primary flex items-center justify-center font-bold text-xs shrink-0">
                                            <?php if (!empty($r['reviewer_image'])): ?>
                                                <img src="<?= htmlspecialchars($r['reviewer_image']) ?>" class="w-full h-full object-cover" alt="" />
                                            <?php else: ?>
                                                <?= strtoupper(substr($r['reviewer_name'] ?? $r['reviewer_username'], 0, 1)) ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="min-w-0">
                                            <p class="text-sm font-semibold text-slate-800 truncate"><?= htmlspecialchars($r['reviewer_name'] ?: $r['reviewer_username']) ?></p>
                                            <p class="text-xs text-slate-500">@<?= htmlspecialchars($r['reviewer_username']) ?></p>
                                        </div>
                                    </div>
                                    <p class="text-xs text-slate-500 shrink-0"><?= date('M j, Y', strtotime($r['created_at'])) ?></p>
                                </div>
                                <div class="mt-2 flex items-center gap-2">
                                    <?= userStars((float) $r['rating']) ?>
                                    <?php if ($r['is_verified_purchase']): ?>
                                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-700">Verified purchase</span>
                                    <?php endif; ?>
                                </div>
                                <p class="mt-2 text-sm text-slate-600 leading-relaxed"><?= nl2br(htmlspecialchars($r['review_text'])) ?></p>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php endif; ?>
            </section>

        </div>
    </main>
</body>
</html>
