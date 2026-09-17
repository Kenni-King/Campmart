<?php
session_start();
include_once 'includes/controller.php';

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
    $conditions[] = "(p.title LIKE CONCAT('%', ?, '%') OR p.description LIKE CONCAT('%', ?, '%') OR p.slug LIKE CONCAT('%', ?, '%'))";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $types .= 'sss';
}

if ($status !== '') {
    $conditions[] = 'p.status = ?';
    $params[] = $status;
    $types .= 's';
}

if ($availability !== '') {
    $conditions[] = 'p.availability = ?';
    $params[] = $availability;
    $types .= 's';
}

if ($featured !== null) {
    $conditions[] = 'p.is_featured = 1';
}

$whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$order_by = [
    'newest' => 'p.created_at DESC',
    'oldest' => 'p.created_at ASC',
    'price_high' => 'p.price DESC',
    'price_low' => 'p.price ASC',
    'views' => 'p.views_count DESC',
    'bookmarks' => 'p.bookmarks_count DESC',
][$sort] ?? 'p.created_at DESC';

$query = "
    SELECT p.id, p.title, p.slug, p.price, p.availability, p.status, p.condition_type,
           p.is_featured, p.is_trending, p.is_sponsored, p.is_urgent,
           p.views_count, p.bookmarks_count, p.created_at,
           u.full_name, u.username,
           c.name AS category_name,
           pi.image_url
    FROM products p
    LEFT JOIN users u ON p.user_id = u.id
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
    $whereSql
    ORDER BY $order_by
    LIMIT 200
";

$stmt = $db->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$products = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Manage Products | CampMart Admin</title>
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
                    <p class="text-sm text-slate-500">Admin / Products</p>
                    <h1 class="text-3xl font-bold text-brand-green">Manage Products</h1>
                    <p class="text-slate-500">Review and monitor all marketplace products.</p>
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
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Title, description, or slug" class="w-full pl-10 pr-3 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm" />
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">search</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                        <select name="status" class="w-full px-3 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm">
                            <option value="">Any</option>
                            <?php foreach (['pending','approved','rejected','draft','sold'] as $opt): ?>
                                <option value="<?= $opt ?>" <?= $status === $opt ? 'selected' : '' ?>><?= ucfirst($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Availability</label>
                        <select name="availability" class="w-full px-3 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm">
                            <option value="">Any</option>
                            <?php foreach (['available','reserved','sold','unavailable'] as $opt): ?>
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
                                <option value="views" <?= $sort === 'views' ? 'selected' : '' ?>>Most Viewed</option>
                                <option value="bookmarks" <?= $sort === 'bookmarks' ? 'selected' : '' ?>>Most Bookmarked</option>
                            </select>
                        </div>
                        <div class="flex justify-end gap-2 md:col-span-1 md:justify-end">
                            <a href="manage-products.php" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-lg text-sm font-medium hover:bg-slate-200">Reset</a>
                            <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-semibold hover:bg-primary/90">Apply</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-100 text-sm text-slate-600 flex items-center justify-between">
                    <span>Showing <?= $products ? $products->num_rows : 0 ?> products (max 200)</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-slate-700">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">Product</th>
                                <th class="px-4 py-3 text-left font-semibold">Price</th>
                                <th class="px-4 py-3 text-left font-semibold">Availability</th>
                                <th class="px-4 py-3 text-left font-semibold">Status</th>
                                <th class="px-4 py-3 text-left font-semibold">Flags</th>
                                <th class="px-4 py-3 text-left font-semibold">Stats</th>
                                <th class="px-4 py-3 text-left font-semibold">Created</th>
                                <th class="px-4 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if ($products && $products->num_rows > 0): ?>
                                <?php while($p = $products->fetch_assoc()): ?>
                                    <?php
                                        $statusColor = [
                                            'approved' => 'bg-emerald-100 text-emerald-700',
                                            'pending' => 'bg-amber-100 text-amber-700',
                                            'rejected' => 'bg-rose-100 text-rose-700',
                                            'draft' => 'bg-slate-100 text-slate-700',
                                            'sold' => 'bg-blue-100 text-blue-700'
                                        ];
                                        $availabilityColor = [
                                            'available' => 'bg-emerald-100 text-emerald-700',
                                            'reserved' => 'bg-amber-100 text-amber-700',
                                            'sold' => 'bg-blue-100 text-blue-700',
                                            'unavailable' => 'bg-slate-100 text-slate-700'
                                        ];
                                    ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-4 py-3 align-top">
                                            <div class="flex items-start gap-3">
                                                <?php if (!empty($p['image_url'])): ?>
                                                    <img src="<?= htmlspecialchars($p['image_url']) ?>" class="w-12 h-12 rounded-md object-cover border border-slate-200" alt="<?= htmlspecialchars($p['title']) ?>" />
                                                <?php else: ?>
                                                    <span class="material-symbols-outlined text-slate-400">image</span>
                                                <?php endif; ?>
                                                <div class="min-w-0">
                                                    <div class="font-semibold text-text-dark break-words"><?= htmlspecialchars($p['title']) ?></div>
                                                    <div class="text-xs text-slate-500">Slug: <?= htmlspecialchars($p['slug']) ?></div>
                                                    <div class="text-xs text-slate-500">Category: <?= htmlspecialchars($p['category_name'] ?? '—') ?></div>
                                                    <div class="text-xs text-slate-500">Seller: <?= htmlspecialchars($p['full_name'] ?? 'Unknown') ?> (<?= htmlspecialchars($p['username'] ?? '—') ?>)</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            <div class="font-semibold text-text-dark">₦<?= number_format($p['price'], 2) ?></div>
                                            <div class="text-xs text-slate-500">Condition: <?= htmlspecialchars(ucwords(str_replace('_',' ', $p['condition_type']))) ?></div>
                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold <?= $availabilityColor[$p['availability']] ?? 'bg-slate-100 text-slate-700' ?>">
                                                <?= htmlspecialchars(ucfirst($p['availability'])) ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold <?= $statusColor[$p['status']] ?? 'bg-slate-100 text-slate-700' ?>">
                                                <?= htmlspecialchars(ucfirst($p['status'])) ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 align-top space-x-1">
                                            <?php if ($p['is_featured']): ?><span class="inline-flex px-2 py-0.5 rounded-full bg-secondary/15 text-secondary text-xs font-semibold">Featured</span><?php endif; ?>
                                            <?php if ($p['is_trending']): ?><span class="inline-flex px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-xs font-semibold">Trending</span><?php endif; ?>
                                            <?php if ($p['is_sponsored']): ?><span class="inline-flex px-2 py-0.5 rounded-full bg-purple-100 text-purple-700 text-xs font-semibold">Sponsored</span><?php endif; ?>
                                            <?php if ($p['is_urgent']): ?><span class="inline-flex px-2 py-0.5 rounded-full bg-red-100 text-red-700 text-xs font-semibold">Urgent</span><?php endif; ?>
                                            <?php if (!$p['is_featured'] && !$p['is_trending'] && !$p['is_sponsored'] && !$p['is_urgent']): ?>
                                                <span class="text-xs text-slate-500">None</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            <div class="text-sm text-text-dark font-semibold">Views: <?= (int)$p['views_count'] ?></div>
                                            <div class="text-xs text-slate-500">Bookmarks: <?= (int)$p['bookmarks_count'] ?></div>
                                        </td>
                                        <td class="px-4 py-3 align-top text-xs text-slate-600">
                                            <?= date('M d, Y', strtotime($p['created_at'])) ?>
                                        </td>
                                        <td class="px-4 py-3 align-top text-right space-x-2">
                                            <a href="admin-product.php?id=<?= (int)$p['id'] ?>" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-white border border-slate-200 text-xs font-semibold text-slate-700 hover:border-primary/60 hover:text-primary">
                                                <span class="material-symbols-outlined text-sm">visibility</span>
                                                View
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="px-4 py-6 text-center text-slate-500">No products found for the current filters.</td>
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
