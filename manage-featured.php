<?php
session_start();
include_once 'includes/controller.php';

if (!isset($userId) || !isset($currentUser) || !in_array($currentUser['role'], ['admin', 'superadmin'])) {
    header('Location: index.php');
    exit;
}

// Expire old subscriptions on page load
expireOldSubscriptions($db);

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'cancel_subscription') {
        $subId = (int) ($_POST['subscription_id'] ?? 0);
        if ($subId > 0) {
            $stmt = $db->prepare("SELECT entity_type, entity_id FROM featured_subscriptions WHERE id = ? AND status = 'active'");
            $stmt->bind_param('i', $subId);
            $stmt->execute();
            $sub = $stmt->get_result()->fetch_assoc();
            if ($sub) {
                $stmt2 = $db->prepare("UPDATE featured_subscriptions SET status = 'cancelled' WHERE id = ?");
                $stmt2->bind_param('i', $subId);
                $stmt2->execute();
                syncFeaturedStatus($db, $sub['entity_type'], (int) $sub['entity_id']);
                $_SESSION['success'] = "Subscription cancelled.";
            }
        }
    } elseif ($action === 'activate_subscription') {
        $subId = (int) ($_POST['subscription_id'] ?? 0);
        if ($subId > 0) {
            $stmt = $db->prepare("SELECT entity_type, entity_id FROM featured_subscriptions WHERE id = ? AND status = 'cancelled'");
            $stmt->bind_param('i', $subId);
            $stmt->execute();
            $sub = $stmt->get_result()->fetch_assoc();
            if ($sub) {
                $stmt2 = $db->prepare("UPDATE featured_subscriptions SET status = 'active' WHERE id = ?");
                $stmt2->bind_param('i', $subId);
                $stmt2->execute();
                syncFeaturedStatus($db, $sub['entity_type'], (int) $sub['entity_id']);
                $_SESSION['success'] = "Subscription reactivated.";
            }
        }
    } elseif ($action === 'extend_subscription') {
        $subId = (int) ($_POST['subscription_id'] ?? 0);
        $newPlanId = (int) ($_POST['new_plan_id'] ?? 0);
        if ($subId > 0 && $newPlanId > 0) {
            $planResult = $db->prepare("SELECT duration_days, price FROM subscription_plans WHERE id = ?");
            $planResult->bind_param('i', $newPlanId);
            $planResult->execute();
            $plan = $planResult->get_result()->fetch_assoc();
            if ($plan) {
                $stmt = $db->prepare("SELECT end_date, entity_type, entity_id FROM featured_subscriptions WHERE id = ?");
                $stmt->bind_param('i', $subId);
                $stmt->execute();
                $sub = $stmt->get_result()->fetch_assoc();
                if ($sub) {
                    $baseDate = (strtotime($sub['end_date']) > time()) ? $sub['end_date'] : date('Y-m-d H:i:s');
                    $newEnd = date('Y-m-d H:i:s', strtotime($baseDate . " +{$plan['duration_days']} days"));
                    $stmt2 = $db->prepare("UPDATE featured_subscriptions SET plan_id = ?, end_date = ?, amount_paid = amount_paid + ?, status = 'active' WHERE id = ?");
                    $stmt2->bind_param('sidii', $newPlanId, $newEnd, $plan['price'], $subId);
                    $stmt2->execute();
                    syncFeaturedStatus($db, $sub['entity_type'], (int) $sub['entity_id']);
                    $_SESSION['success'] = "Subscription extended.";
                }
            }
        }
    }

    header('Location: manage-featured.php' . ($_SERVER['QUERY_STRING'] ? '?' . $_SERVER['QUERY_STRING'] : ''));
    exit;
}

// Filters
$search = trim($_GET['search'] ?? '');
$entityType = trim($_GET['entity_type'] ?? '');
$status = trim($_GET['status'] ?? '');
$sort = $_GET['sort'] ?? 'newest';

$conditions = [];
$params = [];
$types = '';

if ($search !== '') {
    $conditions[] = "(u.full_name LIKE CONCAT('%', ?, '%') OR u.username LIKE CONCAT('%', ?, '%'))";
    $params[] = $search;
    $params[] = $search;
    $types .= 'ss';
}

if ($entityType !== '' && in_array($entityType, ['product', 'service', 'profile'])) {
    $conditions[] = 'fs.entity_type = ?';
    $params[] = $entityType;
    $types .= 's';
}

if ($status !== '' && in_array($status, ['active', 'expired', 'cancelled', 'refunded'])) {
    $conditions[] = 'fs.status = ?';
    $params[] = $status;
    $types .= 's';
}

$whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$orderMap = [
    'newest'         => 'fs.created_at DESC',
    'oldest'         => 'fs.created_at ASC',
    'expiring_soon'  => 'fs.end_date ASC',
    'amount_high'    => 'fs.amount_paid DESC',
    'amount_low'     => 'fs.amount_paid ASC',
];
$orderSql = $orderMap[$sort] ?? $orderMap['newest'];

$query = "
    SELECT fs.id, fs.user_id, fs.entity_type, fs.entity_id, fs.plan_id,
           fs.start_date, fs.end_date, fs.amount_paid, fs.status, fs.created_at,
           u.full_name, u.username, u.profile_image,
           sp.name AS plan_name, sp.duration_days,
           CASE fs.entity_type
               WHEN 'product' THEN (SELECT title FROM products WHERE id = fs.entity_id)
               WHEN 'service' THEN (SELECT title FROM services WHERE id = fs.entity_id)
               WHEN 'profile' THEN (SELECT full_name FROM users WHERE id = fs.entity_id)
           END AS entity_title
    FROM featured_subscriptions fs
    JOIN users u ON fs.user_id = u.id
    JOIN subscription_plans sp ON fs.plan_id = sp.id
    $whereSql
    ORDER BY $orderSql
    LIMIT 200
";

$stmt = $db->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$subscriptions = $stmt->get_result();

// Stats
$activeCount = (int) $db->query("SELECT COUNT(*) as cnt FROM featured_subscriptions WHERE status = 'active' AND end_date > NOW()")->fetch_assoc()['cnt'];
$expiringCount = (int) $db->query("SELECT COUNT(*) as cnt FROM featured_subscriptions WHERE status = 'active' AND end_date > NOW() AND end_date <= DATE_ADD(NOW(), INTERVAL 3 DAY)")->fetch_assoc()['cnt'];
$totalRevenue = (float) $db->query("SELECT COALESCE(SUM(amount_paid), 0) as total FROM featured_subscriptions")->fetch_assoc()['total'];

// Fetch plans for extend dropdown
$plansResult = $db->query("SELECT id, name, price, duration_days FROM subscription_plans WHERE is_active = 1 ORDER BY sort_order ASC");
$activePlans = [];
if ($plansResult) {
    while ($row = $plansResult->fetch_assoc()) {
        $activePlans[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Featured Listings | CampMart Admin</title>
    <script src="tailwind34.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />
    <script id="tailwind-config">
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#f48c25',
                        secondary: '#FF6B35',
                        accent: '#FFE6D0',
                        'brand-green': '#064E3B',
                        'background-main': '#F9FAFB',
                        'surface-white': '#FFFFFF',
                        'text-dark': '#1F2937',
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
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500">Admin / Featured</p>
                    <h1 class="text-3xl font-bold text-brand-green">Featured Listings</h1>
                    <p class="text-slate-500">Manage active featured subscriptions for products, services, and profiles.</p>
                </div>
                <a href="manage-plans.php" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-slate-200 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50">
                    <span class="material-symbols-outlined text-base">card_membership</span>
                    Manage Plans
                </a>
            </div>

            <!-- Stats -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-[11px] uppercase tracking-widest text-slate-400 font-bold">Active</p>
                        <span class="material-symbols-outlined text-lg px-2 py-1.5 rounded-xl bg-amber-100 text-amber-600">workspace_premium</span>
                    </div>
                    <p class="text-2xl font-extrabold text-slate-900"><?= number_format($activeCount) ?></p>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-[11px] uppercase tracking-widest text-slate-400 font-bold">Expiring Soon (3d)</p>
                        <span class="material-symbols-outlined text-lg px-2 py-1.5 rounded-xl bg-red-100 text-red-600">timer</span>
                    </div>
                    <p class="text-2xl font-extrabold text-slate-900"><?= number_format($expiringCount) ?></p>
                </div>
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                    <div class="flex items-center justify-between mb-2">
                        <p class="text-[11px] uppercase tracking-widest text-slate-400 font-bold">Total Revenue</p>
                        <span class="material-symbols-outlined text-lg px-2 py-1.5 rounded-xl bg-emerald-100 text-emerald-600">payments</span>
                    </div>
                    <p class="text-2xl font-extrabold text-slate-900"><?= formatCurrency($totalRevenue) ?></p>
                </div>
            </div>

            <!-- Filters -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 md:p-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Search User</label>
                        <div class="relative">
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Name or username" class="w-full pl-10 pr-3 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm" />
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">search</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Entity Type</label>
                        <select name="entity_type" class="w-full px-3 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm">
                            <option value="">All Types</option>
                            <option value="product" <?= $entityType === 'product' ? 'selected' : '' ?>>Product</option>
                            <option value="service" <?= $entityType === 'service' ? 'selected' : '' ?>>Service</option>
                            <option value="profile" <?= $entityType === 'profile' ? 'selected' : '' ?>>Profile</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                        <select name="status" class="w-full px-3 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm">
                            <option value="">All Status</option>
                            <?php foreach (['active','expired','cancelled','refunded'] as $opt): ?>
                                <option value="<?= $opt ?>" <?= $status === $opt ? 'selected' : '' ?>><?= ucfirst($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="md:col-span-4 grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Sort</label>
                            <select name="sort" class="w-full px-3 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm">
                                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
                                <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
                                <option value="expiring_soon" <?= $sort === 'expiring_soon' ? 'selected' : '' ?>>Expiring Soon</option>
                                <option value="amount_high" <?= $sort === 'amount_high' ? 'selected' : '' ?>>Amount: High to Low</option>
                                <option value="amount_low" <?= $sort === 'amount_low' ? 'selected' : '' ?>>Amount: Low to High</option>
                            </select>
                        </div>
                        <div class="flex justify-end gap-2 md:col-span-2 md:justify-end">
                            <a href="manage-featured.php" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-lg text-sm font-medium hover:bg-slate-200">Reset</a>
                            <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-semibold hover:bg-primary/90">Apply</button>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Table -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-100 text-sm text-slate-600 flex items-center justify-between">
                    <span>Showing <?= $subscriptions ? $subscriptions->num_rows : 0 ?> subscriptions (max 200)</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-slate-700">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">User</th>
                                <th class="px-4 py-3 text-left font-semibold">Type</th>
                                <th class="px-4 py-3 text-left font-semibold">Entity</th>
                                <th class="px-4 py-3 text-left font-semibold">Plan</th>
                                <th class="px-4 py-3 text-left font-semibold">Amount</th>
                                <th class="px-4 py-3 text-left font-semibold">Period</th>
                                <th class="px-4 py-3 text-left font-semibold">Status</th>
                                <th class="px-4 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if ($subscriptions && $subscriptions->num_rows > 0): ?>
                                <?php while ($sub = $subscriptions->fetch_assoc()):
                                    $daysRemaining = (int) ((strtotime($sub['end_date']) - time()) / 86400);
                                    $isExpired = strtotime($sub['end_date']) <= time();
                                    $statusColors = [
                                        'active'    => 'bg-emerald-100 text-emerald-700',
                                        'expired'   => 'bg-slate-100 text-slate-600',
                                        'cancelled' => 'bg-red-100 text-red-700',
                                        'refunded'  => 'bg-amber-100 text-amber-700',
                                    ];
                                    $entityColors = [
                                        'product' => 'bg-purple-100 text-purple-700',
                                        'service' => 'bg-blue-100 text-blue-700',
                                        'profile' => 'bg-pink-100 text-pink-700',
                                    ];
                                    $entityLinks = [
                                        'product' => 'admin-product.php?id=' . $sub['entity_id'],
                                        'service' => 'admin-service.php?id=' . $sub['entity_id'],
                                        'profile' => 'admin-user.php?id=' . $sub['entity_id'],
                                    ];
                                ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-4 py-3 align-top">
                                            <div class="flex items-center gap-3">
                                                <div class="size-9 rounded-full overflow-hidden bg-brand-green text-white flex items-center justify-center text-xs font-bold shrink-0">
                                                    <?php if (!empty($sub['profile_image'])): ?>
                                                        <img src="<?= htmlspecialchars($sub['profile_image']) ?>" class="w-full h-full object-cover" alt="" />
                                                    <?php else: ?>
                                                        <?= strtoupper(substr($sub['full_name'] ?? 'U', 0, 1)) ?>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="min-w-0">
                                                    <p class="font-semibold text-slate-800 truncate"><?= htmlspecialchars($sub['full_name'] ?: $sub['username']) ?></p>
                                                    <p class="text-xs text-slate-500">@<?= htmlspecialchars($sub['username']) ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold <?= $entityColors[$sub['entity_type']] ?? 'bg-slate-100 text-slate-700' ?>">
                                                <?= ucfirst($sub['entity_type']) ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            <a href="<?= $entityLinks[$sub['entity_type']] ?? '#' ?>" class="text-sm font-semibold text-primary hover:underline truncate max-w-[200px] block">
                                                <?= htmlspecialchars($sub['entity_title'] ?? 'ID: ' . $sub['entity_id']) ?>
                                            </a>
                                        </td>
                                        <td class="px-4 py-3 align-top text-sm text-slate-700">
                                            <?= htmlspecialchars($sub['plan_name']) ?>
                                            <div class="text-xs text-slate-500"><?= $sub['duration_days'] ?>d</div>
                                        </td>
                                        <td class="px-4 py-3 align-top text-sm font-semibold text-slate-700">
                                            <?= formatCurrency((float) $sub['amount_paid']) ?>
                                        </td>
                                        <td class="px-4 py-3 align-top text-xs text-slate-600">
                                            <?= date('M d, Y', strtotime($sub['start_date'])) ?><br>
                                            → <?= date('M d, Y', strtotime($sub['end_date'])) ?>
                                            <?php if ($sub['status'] === 'active' && !$isExpired): ?>
                                                <div class="text-[11px] text-emerald-600 font-semibold mt-0.5"><?= $daysRemaining ?>d remaining</div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold <?= $statusColors[$sub['status']] ?? 'bg-slate-100 text-slate-700' ?>">
                                                <?= ucfirst($sub['status']) ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 align-top text-right">
                                            <div class="flex items-center justify-end gap-1.5">
                                                <?php if ($sub['status'] === 'active'): ?>
                                                    <form method="POST" onsubmit="return confirm('Cancel this subscription?')">
                                                        <input type="hidden" name="action" value="cancel_subscription">
                                                        <input type="hidden" name="subscription_id" value="<?= $sub['id'] ?>">
                                                        <button type="submit" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-white border border-slate-200 text-xs font-semibold text-red-600 hover:border-red-300 hover:bg-red-50">
                                                            <span class="material-symbols-outlined text-sm">block</span>
                                                            Cancel
                                                        </button>
                                                    </form>
                                                    <button onclick="openExtendModal(<?= $sub['id'] ?>, '<?= htmlspecialchars(addslashes($sub['end_date'])) ?>')" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-white border border-slate-200 text-xs font-semibold text-slate-700 hover:border-primary/60 hover:text-primary">
                                                        <span class="material-symbols-outlined text-sm">schedule</span>
                                                        Extend
                                                    </button>
                                                <?php elseif ($sub['status'] === 'cancelled'): ?>
                                                    <form method="POST" onsubmit="return confirm('Reactivate this subscription?')">
                                                        <input type="hidden" name="action" value="activate_subscription">
                                                        <input type="hidden" name="subscription_id" value="<?= $sub['id'] ?>">
                                                        <button type="submit" class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg bg-white border border-slate-200 text-xs font-semibold text-emerald-600 hover:border-emerald-300 hover:bg-emerald-50">
                                                            <span class="material-symbols-outlined text-sm">play_arrow</span>
                                                            Reactivate
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="px-4 py-8 text-center text-slate-500">No featured subscriptions found.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Extend Modal -->
    <div id="extendModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-md mx-4 p-6">
            <div class="flex items-center justify-between mb-5">
                <h2 class="text-xl font-bold text-slate-900">Extend Subscription</h2>
                <button onclick="document.getElementById('extendModal').classList.add('hidden')" class="p-1 rounded-lg hover:bg-slate-100">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="extend_subscription">
                <input type="hidden" name="subscription_id" id="extend_sub_id">
                <div>
                    <p class="text-sm text-slate-500 mb-1">Current end date:</p>
                    <p class="text-sm font-semibold text-slate-700" id="extend_current_end"></p>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Select Plan to Add</label>
                    <select name="new_plan_id" required class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20">
                        <?php foreach ($activePlans as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['name']) ?> — <?= formatCurrency((float) $p['price']) ?> (<?= $p['duration_days'] ?> days)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="document.getElementById('extendModal').classList.add('hidden')" class="px-5 py-2.5 rounded-xl border border-slate-200 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-primary text-white text-sm font-bold hover:bg-primary/90 transition">Extend</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openExtendModal(subId, endDate) {
        document.getElementById('extend_sub_id').value = subId;
        document.getElementById('extend_current_end').textContent = endDate;
        document.getElementById('extendModal').classList.remove('hidden');
    }
    </script>
</body>
</html>
