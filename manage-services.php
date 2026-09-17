<?php
session_start();
include_once 'includes/controller.php';

// Admin gate
if (!isset($userId) || !isset($currentUser) || !in_array($currentUser['role'], ['admin', 'superadmin'])) {
    header('Location: index.php');
    exit;
}

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$availability = trim($_GET['availability'] ?? '');
$featured = isset($_GET['featured']) ? 1 : null;
$sort = $_GET['sort'] ?? 'newest';

$conditions = [];
$params = [];
$types = '';

if ($search !== '') {
    $conditions[] = "(s.title LIKE CONCAT('%', ?, '%') OR s.description LIKE CONCAT('%', ?, '%'))";
    $params[] = $search;
    $params[] = $search;
    $types .= 'ss';
}

if ($status !== '') {
    $conditions[] = 's.status = ?';
    $params[] = $status;
    $types .= 's';
}

if ($availability !== '') {
    $conditions[] = 's.availability = ?';
    $params[] = $availability;
    $types .= 's';
}

if ($featured !== null) {
    $conditions[] = 's.is_featured = 1';
}

$whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$order_by_map = [
    'newest' => 's.created_at DESC',
    'oldest' => 's.created_at ASC',
    'price_high' => 's.price DESC',
    'price_low' => 's.price ASC',
    'rating' => 's.rating DESC, s.total_ratings DESC',
    'orders' => 's.total_orders DESC',
    'views' => 's.views_count DESC',
    'bookmarks' => 's.bookmarks_count DESC',
];
$order_by = $order_by_map[$sort] ?? $order_by_map['newest'];

$query = "
    SELECT s.id, s.title, s.slug, s.price, s.pricing_type, s.availability, s.status, s.is_featured,
           s.rating, s.total_ratings, s.total_orders, s.views_count, s.bookmarks_count, s.created_at,
           u.full_name, u.username, u.profile_image,
           sc.name AS category_name
    FROM services s
    LEFT JOIN users u ON s.user_id = u.id
    LEFT JOIN service_categories sc ON s.service_category_id = sc.id
    $whereSql
    ORDER BY $order_by
    LIMIT 200
";

$stmt = $db->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$services = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Manage Services | CampMart Admin</title>
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
            <?php if (isset($_SESSION['success'])): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl flex items-center gap-3">
                    <span class="material-symbols-outlined text-emerald-600">check_circle</span>
                    <p class="flex-1 text-sm"><?= htmlspecialchars($_SESSION['success']) ?></p>
                    <button onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-800"><span class="material-symbols-outlined">close</span></button>
                </div>
            <?php unset($_SESSION['success']); endif; ?>
            <?php if (isset($_SESSION['info'])): ?>
                <div class="bg-blue-50 border border-blue-200 text-blue-800 px-4 py-3 rounded-xl flex items-center gap-3">
                    <span class="material-symbols-outlined text-blue-600">info</span>
                    <p class="flex-1 text-sm"><?= htmlspecialchars($_SESSION['info']) ?></p>
                    <button onclick="this.parentElement.remove()" class="text-blue-600 hover:text-blue-800"><span class="material-symbols-outlined">close</span></button>
                </div>
            <?php unset($_SESSION['info']); endif; ?>

            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500">Admin / Services</p>
                    <h1 class="text-3xl font-bold text-brand-green">Manage Services</h1>
                    <p class="text-slate-500">Review and monitor service listings.</p>
                </div>
                <a href="index.php" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-slate-200 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50">
                    <span class="material-symbols-outlined text-base">home</span>
                    Back to site
                </a>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 md:p-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Search</label>
                        <div class="relative">
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Title or description" class="w-full pl-10 pr-3 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm" />
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">search</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                        <select name="status" class="w-full px-3 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm">
                            <option value="">Any</option>
                            <?php foreach (['active','paused','inactive'] as $opt): ?>
                                <option value="<?= $opt ?>" <?= $status === $opt ? 'selected' : '' ?>><?= ucfirst($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Availability</label>
                        <select name="availability" class="w-full px-3 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm">
                            <option value="">Any</option>
                            <?php foreach (['available','busy','unavailable'] as $opt): ?>
                                <option value="<?= $opt ?>" <?= $availability === $opt ? 'selected' : '' ?>><?= ucfirst($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="md:col-span-4 grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                        <label class="inline-flex items-center gap-2 text-sm font-medium text-slate-700">
                            <input type="checkbox" name="featured" class="rounded text-primary" <?= $featured ? 'checked' : '' ?> />
                            Featured only
                        </label>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Sort</label>
                            <select name="sort" class="w-full px-3 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm">
                                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
                                <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
                                <option value="price_high" <?= $sort === 'price_high' ? 'selected' : '' ?>>Price: High to Low</option>
                                <option value="price_low" <?= $sort === 'price_low' ? 'selected' : '' ?>>Price: Low to High</option>
                                <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>Highest Rated</option>
                                <option value="orders" <?= $sort === 'orders' ? 'selected' : '' ?>>Most Orders</option>
                                <option value="views" <?= $sort === 'views' ? 'selected' : '' ?>>Most Viewed</option>
                                <option value="bookmarks" <?= $sort === 'bookmarks' ? 'selected' : '' ?>>Most Bookmarked</option>
                            </select>
                        </div>
                        <div class="flex justify-end gap-2 md:col-span-1 md:justify-end">
                            <a href="manage-services.php" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-lg text-sm font-medium hover:bg-slate-200">Reset</a>
                            <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-semibold hover:bg-primary/90">Apply</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-100 text-sm text-slate-600 flex items-center justify-between">
                    <span>Showing <?= $services ? $services->num_rows : 0 ?> services (max 200)</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-slate-700">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">Service</th>
                                <th class="px-4 py-3 text-left font-semibold">Pricing</th>
                                <th class="px-4 py-3 text-left font-semibold">Availability</th>
                                <th class="px-4 py-3 text-left font-semibold">Status</th>
                                <th class="px-4 py-3 text-left font-semibold">Flags</th>
                                <th class="px-4 py-3 text-left font-semibold">Stats</th>
                                <th class="px-4 py-3 text-left font-semibold">Created</th>
                                <th class="px-4 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if ($services && $services->num_rows > 0): ?>
                                <?php while($s = $services->fetch_assoc()): ?>
                                    <?php
                                        $statusColor = [
                                            'active' => 'bg-emerald-100 text-emerald-700',
                                            'paused' => 'bg-amber-100 text-amber-700',
                                            'inactive' => 'bg-slate-100 text-slate-700'
                                        ];
                                        $availabilityColor = [
                                            'available' => 'bg-emerald-100 text-emerald-700',
                                            'busy' => 'bg-blue-100 text-blue-700',
                                            'unavailable' => 'bg-slate-100 text-slate-700'
                                        ];
                                    ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-4 py-3 align-top">
                                            <div class="font-semibold text-text-dark"><?= htmlspecialchars($s['title']) ?></div>
                                            <div class="text-xs text-slate-500">Category: <?= htmlspecialchars($s['category_name'] ?? '—') ?></div>
                                            <div class="flex items-center gap-2 text-xs text-slate-500 mt-1">
                                                <?php if (!empty($s['profile_image'])): ?>
                                                    <img src="<?= htmlspecialchars($s['profile_image']) ?>" class="w-6 h-6 rounded-full object-cover border border-slate-200" alt="<?= htmlspecialchars($s['full_name'] ?? 'Unknown') ?>" />
                                                <?php else: ?>
                                                    <span class="material-symbols-outlined text-slate-400 text-base">account_circle</span>
                                                <?php endif; ?>
                                                <span>Seller: <?= htmlspecialchars($s['full_name'] ?? 'Unknown') ?> (<?= htmlspecialchars($s['username'] ?? '—') ?>)</span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            <div class="font-semibold text-text-dark">₦<?= number_format($s['price'], 2) ?></div>
                                            <div class="text-xs text-slate-500">Pricing: <?= htmlspecialchars(ucwords(str_replace('_',' ', $s['pricing_type']))) ?></div>
                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold <?= $availabilityColor[$s['availability']] ?? 'bg-slate-100 text-slate-700' ?>">
                                                <?= htmlspecialchars(ucfirst($s['availability'])) ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold <?= $statusColor[$s['status']] ?? 'bg-slate-100 text-slate-700' ?>">
                                                <?= htmlspecialchars(ucfirst($s['status'])) ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 align-top space-x-1">
                                            <?php if ($s['is_featured']): ?><span class="inline-flex px-2 py-0.5 rounded-full bg-secondary/15 text-secondary text-xs font-semibold">Featured</span><?php endif; ?>
                                            <?php if (!$s['is_featured']): ?><span class="text-xs text-slate-500">None</span><?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 align-top text-sm text-text-dark">
                                            <div class="font-semibold">Rating: <?= number_format($s['rating'], 2) ?> (<?= (int)$s['total_ratings'] ?>)</div>
                                            <div class="text-xs text-slate-500">Orders: <?= (int)$s['total_orders'] ?> · Views: <?= (int)$s['views_count'] ?> · Bookmarks: <?= (int)$s['bookmarks_count'] ?></div>
                                        </td>
                                        <td class="px-4 py-3 align-top text-xs text-slate-600">
                                            <?= date('M d, Y', strtotime($s['created_at'])) ?>
                                        </td>
                                        <td class="px-4 py-3 align-top text-right space-x-2">
                                            <a href="admin-service.php?id=<?= (int)$s['id'] ?>" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-white border border-slate-200 text-xs font-semibold text-slate-700 hover:border-primary/60 hover:text-primary">
                                                <span class="material-symbols-outlined text-sm">visibility</span>
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="px-4 py-6 text-center text-slate-500">No services found for the current filters.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</body>
</html>
