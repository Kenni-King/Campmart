<?php
session_start();
include_once 'includes/controller.php';

// Admin-only access
if (!isset($userId) || !isset($currentUser) || !in_array($currentUser['role'], ['admin', 'superadmin'])) {
    header('Location: index.php');
    exit;
}

// Fetch key statistics
$stats = [];

// Users stats
$result = $db->query("SELECT COUNT(*) as total, 
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) as new_week
    FROM users");
$stats['users'] = $result->fetch_assoc();

// Products stats
$result = $db->query("SELECT COUNT(*) as total,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN availability = 'available' THEN 1 ELSE 0 END) as available
    FROM products");
$stats['products'] = $result->fetch_assoc();

// Services stats
$result = $db->query("SELECT COUNT(*) as total,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN availability = 'available' THEN 1 ELSE 0 END) as available
    FROM services");
$stats['services'] = $result->fetch_assoc();

// Sponsored content stats
$result = $db->query("SELECT COUNT(*) as total,
    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
    SUM(CASE WHEN status = 'paused' THEN 1 ELSE 0 END) as paused,
    COALESCE(SUM(view_count), 0) as total_views,
    COALESCE(SUM(click_count), 0) as total_clicks
    FROM sponsored_content");
$stats['ads'] = $result->fetch_assoc();

// Lost & Found stats
$result = $db->query("SELECT COUNT(*) as total,
    SUM(CASE WHEN status = 'open' THEN 1 ELSE 0 END) as open,
    SUM(CASE WHEN status = 'claimed' THEN 1 ELSE 0 END) as claimed
    FROM lost_found_items");
$stats['lostfound'] = $result->fetch_assoc();

// Reports stats
$result = $db->query("SELECT COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'under_review' THEN 1 ELSE 0 END) as under_review,
    SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved
    FROM reports");
$stats['reports'] = $result->fetch_assoc();

// Transactions stats
$result = $db->query("SELECT COUNT(*) as total,
    COALESCE(SUM(amount), 0) as total_amount,
    SUM(CASE WHEN payment_status = 'paid' THEN 1 ELSE 0 END) as paid,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed
    FROM transactions WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
$stats['transactions'] = $result->fetch_assoc();

// Recent activity - last 10 products
$recent_products = $db->query("SELECT p.id, p.title, p.slug, p.status, p.created_at, u.username, u.full_name
    FROM products p
    LEFT JOIN users u ON p.user_id = u.id
    ORDER BY p.created_at DESC LIMIT 10");

// Recent reports - last 10 pending
$recent_reports = $db->query("SELECT r.id, r.report_type, r.reason, r.status, r.created_at,
    reporter.username as reporter_username, reporter.full_name as reporter_name
    FROM reports r
    LEFT JOIN users reporter ON r.reporter_id = reporter.id
    WHERE r.status IN ('pending', 'under_review')
    ORDER BY r.created_at DESC LIMIT 10");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <base href="<?php echo SITE_URL; ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Admin Dashboard | CampMart</title>
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
                        accent: '#FFE66D',
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
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500">Admin</p>
                    <h1 class="text-3xl font-bold text-brand-green">Dashboard</h1>
                    <p class="text-slate-500">Overview of platform activity and statistics.</p>
                </div>
                <a href="index.php" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-slate-200 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50">
                    <span class="material-symbols-outlined text-base">home</span>
                    Back to site
                </a>
            </div>

            <!-- Stats Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <!-- Users -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                    <div class="flex items-center justify-between mb-3">
                        <span class="material-symbols-outlined text-blue-600 text-3xl">group</span>
                        <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-700">+<?= (int)$stats['users']['new_week'] ?> this week</span>
                    </div>
                    <h3 class="text-2xl font-bold text-text-dark"><?= number_format((int)$stats['users']['total']) ?></h3>
                    <p class="text-sm text-slate-600">Total Users</p>
                    <div class="mt-3 pt-3 border-t border-slate-100 flex justify-between text-xs">
                        <span class="text-emerald-600 font-semibold"><?= number_format((int)$stats['users']['active']) ?> Active</span>
                        <span class="text-amber-600 font-semibold"><?= number_format((int)$stats['users']['pending']) ?> Pending</span>
                    </div>
                </div>

                <!-- Products -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                    <div class="flex items-center justify-between mb-3">
                        <span class="material-symbols-outlined text-purple-600 text-3xl">inventory_2</span>
                        <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-purple-100 text-purple-700"><?= number_format((int)$stats['products']['available']) ?> Available</span>
                    </div>
                    <h3 class="text-2xl font-bold text-text-dark"><?= number_format((int)$stats['products']['total']) ?></h3>
                    <p class="text-sm text-slate-600">Total Products</p>
                    <div class="mt-3 pt-3 border-t border-slate-100 flex justify-between text-xs">
                        <span class="text-emerald-600 font-semibold"><?= number_format((int)$stats['products']['approved']) ?> Approved</span>
                        <span class="text-amber-600 font-semibold"><?= number_format((int)$stats['products']['pending']) ?> Pending</span>
                    </div>
                </div>

                <!-- Services -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                    <div class="flex items-center justify-between mb-3">
                        <span class="material-symbols-outlined text-pink-600 text-3xl">build</span>
                        <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-pink-100 text-pink-700"><?= number_format((int)$stats['services']['available']) ?> Available</span>
                    </div>
                    <h3 class="text-2xl font-bold text-text-dark"><?= number_format((int)$stats['services']['total']) ?></h3>
                    <p class="text-sm text-slate-600">Total Services</p>
                    <div class="mt-3 pt-3 border-t border-slate-100 flex justify-between text-xs">
                        <span class="text-emerald-600 font-semibold"><?= number_format((int)$stats['services']['active']) ?> Active</span>
                    </div>
                </div>

                <!-- Reports -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                    <div class="flex items-center justify-between mb-3">
                        <span class="material-symbols-outlined text-red-600 text-3xl">flag</span>
                        <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-red-100 text-red-700"><?= number_format((int)$stats['reports']['pending']) ?> Pending</span>
                    </div>
                    <h3 class="text-2xl font-bold text-text-dark"><?= number_format((int)$stats['reports']['total']) ?></h3>
                    <p class="text-sm text-slate-600">Total Reports</p>
                    <div class="mt-3 pt-3 border-t border-slate-100 flex justify-between text-xs">
                        <span class="text-blue-600 font-semibold"><?= number_format((int)$stats['reports']['under_review']) ?> In Review</span>
                        <span class="text-emerald-600 font-semibold"><?= number_format((int)$stats['reports']['resolved']) ?> Resolved</span>
                    </div>
                </div>

                <!-- Sponsored Ads -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                    <div class="flex items-center justify-between mb-3">
                        <span class="material-symbols-outlined text-amber-600 text-3xl">campaign</span>
                        <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-amber-100 text-amber-700"><?= number_format((int)$stats['ads']['active']) ?> Active</span>
                    </div>
                    <h3 class="text-2xl font-bold text-text-dark"><?= number_format((int)$stats['ads']['total']) ?></h3>
                    <p class="text-sm text-slate-600">Sponsored Ads</p>
                    <div class="mt-3 pt-3 border-t border-slate-100 flex justify-between text-xs">
                        <span class="text-slate-600 font-semibold"><?= number_format((int)$stats['ads']['total_views']) ?> Views</span>
                        <span class="text-slate-600 font-semibold"><?= number_format((int)$stats['ads']['total_clicks']) ?> Clicks</span>
                    </div>
                </div>

                <!-- Lost & Found -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                    <div class="flex items-center justify-between mb-3">
                        <span class="material-symbols-outlined text-indigo-600 text-3xl">search</span>
                        <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-700"><?= number_format((int)$stats['lostfound']['open']) ?> Open</span>
                    </div>
                    <h3 class="text-2xl font-bold text-text-dark"><?= number_format((int)$stats['lostfound']['total']) ?></h3>
                    <p class="text-sm text-slate-600">Lost & Found</p>
                    <div class="mt-3 pt-3 border-t border-slate-100 flex justify-between text-xs">
                        <span class="text-emerald-600 font-semibold"><?= number_format((int)$stats['lostfound']['claimed']) ?> Claimed</span>
                    </div>
                </div>

                <!-- Transactions (Last 30 Days) -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 md:col-span-2">
                    <div class="flex items-center justify-between mb-3">
                        <span class="material-symbols-outlined text-emerald-600 text-3xl">payments</span>
                        <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">Last 30 Days</span>
                    </div>
                    <h3 class="text-2xl font-bold text-text-dark">₦<?= number_format((float)$stats['transactions']['total_amount'], 2) ?></h3>
                    <p class="text-sm text-slate-600">Transaction Volume</p>
                    <div class="mt-3 pt-3 border-t border-slate-100 flex justify-between text-xs">
                        <span class="text-slate-600 font-semibold"><?= number_format((int)$stats['transactions']['total']) ?> Total</span>
                        <span class="text-emerald-600 font-semibold"><?= number_format((int)$stats['transactions']['paid']) ?> Paid</span>
                        <span class="text-blue-600 font-semibold"><?= number_format((int)$stats['transactions']['completed']) ?> Completed</span>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
                <h2 class="text-lg font-bold text-text-dark mb-4">Quick Actions</h2>
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-3">
                    <a href="manage-products.php" class="flex flex-col items-center gap-2 p-4 rounded-lg border border-slate-200 hover:border-primary/60 hover:bg-primary/5">
                        <span class="material-symbols-outlined text-2xl text-primary">inventory_2</span>
                        <span class="text-xs font-semibold text-text-dark">Products</span>
                    </a>
                    <a href="manage-services.php" class="flex flex-col items-center gap-2 p-4 rounded-lg border border-slate-200 hover:border-primary/60 hover:bg-primary/5">
                        <span class="material-symbols-outlined text-2xl text-primary">build</span>
                        <span class="text-xs font-semibold text-text-dark">Services</span>
                    </a>
                    <a href="manage-ads.php" class="flex flex-col items-center gap-2 p-4 rounded-lg border border-slate-200 hover:border-primary/60 hover:bg-primary/5">
                        <span class="material-symbols-outlined text-2xl text-primary">campaign</span>
                        <span class="text-xs font-semibold text-text-dark">Ads</span>
                    </a>
                    <a href="manage-lost-found.php" class="flex flex-col items-center gap-2 p-4 rounded-lg border border-slate-200 hover:border-primary/60 hover:bg-primary/5">
                        <span class="material-symbols-outlined text-2xl text-primary">search</span>
                        <span class="text-xs font-semibold text-text-dark">Lost & Found</span>
                    </a>
                    <a href="reported-content.php" class="flex flex-col items-center gap-2 p-4 rounded-lg border border-slate-200 hover:border-primary/60 hover:bg-primary/5">
                        <span class="material-symbols-outlined text-2xl text-primary">flag</span>
                        <span class="text-xs font-semibold text-text-dark">Reports</span>
                    </a>
                    <a href="manage-videos.php" class="flex flex-col items-center gap-2 p-4 rounded-lg border border-slate-200 hover:border-primary/60 hover:bg-primary/5">
                        <span class="material-symbols-outlined text-2xl text-primary">videocam</span>
                        <span class="text-xs font-semibold text-text-dark">Videos</span>
                    </a>
                    <a href="index.php" class="flex flex-col items-center gap-2 p-4 rounded-lg border border-slate-200 hover:border-primary/60 hover:bg-primary/5">
                        <span class="material-symbols-outlined text-2xl text-primary">settings</span>
                        <span class="text-xs font-semibold text-text-dark">Settings</span>
                    </a>
                </div>
            </div>

            <!-- Two Column Layout -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- Recent Products -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                        <h2 class="text-lg font-bold text-text-dark">Recent Products</h2>
                        <a href="manage-products.php" class="text-xs font-semibold text-primary hover:text-primary/80">View All →</a>
                    </div>
                    <div class="divide-y divide-slate-100">
                        <?php if ($recent_products && $recent_products->num_rows > 0): ?>
                            <?php while($prod = $recent_products->fetch_assoc()): ?>
                                <?php
                                    $statusColor = [
                                        'approved' => 'bg-emerald-100 text-emerald-700',
                                        'pending' => 'bg-amber-100 text-amber-700',
                                        'rejected' => 'bg-rose-100 text-rose-700',
                                        'draft' => 'bg-slate-100 text-slate-700'
                                    ];
                                ?>
                                <div class="px-6 py-3 hover:bg-slate-50">
                                    <div class="flex items-center justify-between">
                                        <div class="min-w-0 flex-1">
                                            <a href="<?= productUrl($prod['slug']) ?>" class="font-semibold text-sm text-text-dark hover:text-primary">
                                                <?= htmlspecialchars($prod['title']) ?>
                                            </a>
                                            <div class="text-xs text-slate-500">
                                                By <?= htmlspecialchars($prod['full_name'] ?? 'Unknown') ?> · <?= date('M d, Y', strtotime($prod['created_at'])) ?>
                                            </div>
                                        </div>
                                        <span class="ml-2 inline-flex px-2.5 py-1 rounded-full text-xs font-semibold <?= $statusColor[$prod['status']] ?? 'bg-slate-100 text-slate-700' ?>">
                                            <?= ucfirst($prod['status']) ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="px-6 py-8 text-center text-sm text-slate-500">No recent products</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Reports -->
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                        <h2 class="text-lg font-bold text-text-dark">Recent Reports</h2>
                        <a href="reported-content.php" class="text-xs font-semibold text-primary hover:text-primary/80">View All →</a>
                    </div>
                    <div class="divide-y divide-slate-100">
                        <?php if ($recent_reports && $recent_reports->num_rows > 0): ?>
                            <?php while($rep = $recent_reports->fetch_assoc()): ?>
                                <?php
                                    $statusColor = [
                                        'pending' => 'bg-amber-100 text-amber-700',
                                        'under_review' => 'bg-blue-100 text-blue-700'
                                    ];
                                    $typeColor = [
                                        'user' => 'bg-indigo-100 text-indigo-700',
                                        'product' => 'bg-purple-100 text-purple-700',
                                        'service' => 'bg-pink-100 text-pink-700',
                                        'message' => 'bg-slate-100 text-slate-700'
                                    ];
                                ?>
                                <div class="px-6 py-3 hover:bg-slate-50">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2 mb-1">
                                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold <?= $typeColor[$rep['report_type']] ?? 'bg-slate-100 text-slate-700' ?>">
                                                    <?= ucfirst($rep['report_type']) ?>
                                                </span>
                                                <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-700">
                                                    <?= ucfirst($rep['reason']) ?>
                                                </span>
                                            </div>
                                            <div class="text-xs text-slate-500">
                                                By <?= htmlspecialchars($rep['reporter_name'] ?? 'Unknown') ?> · <?= date('M d, Y', strtotime($rep['created_at'])) ?>
                                            </div>
                                        </div>
                                        <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold <?= $statusColor[$rep['status']] ?? 'bg-slate-100 text-slate-700' ?>">
                                            <?= ucwords(str_replace('_', ' ', $rep['status'])) ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="px-6 py-8 text-center text-sm text-slate-500">No pending reports</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
