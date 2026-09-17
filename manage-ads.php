<?php
session_start();
include_once 'includes/controller.php';

// Admin-only access
if (!isset($userId) || !isset($currentUser) || !in_array($currentUser['role'], ['admin', 'superadmin'])) {
    header('Location: index.php');
    exit;
}

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$placement = trim($_GET['placement'] ?? '');
$sort = $_GET['sort'] ?? 'newest';

$conditions = [];
$params = [];
$types = '';

if ($search !== '') {
    $conditions[] = "(sc.title LIKE CONCAT('%', ?, '%') OR sc.description LIKE CONCAT('%', ?, '%') OR sc.sponsor_name LIKE CONCAT('%', ?, '%'))";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $types .= 'sss';
}

if ($status !== '') {
    $conditions[] = 'sc.status = ?';
    $params[] = $status;
    $types .= 's';
}

if ($placement !== '') {
    $conditions[] = 'sc.placement = ?';
    $params[] = $placement;
    $types .= 's';
}

$whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$order_by_map = [
    'newest' => 'sc.created_at DESC',
    'oldest' => 'sc.created_at ASC',
    'budget_high' => 'sc.budget DESC',
    'budget_low' => 'sc.budget ASC',
    'views' => 'COALESCE(sc.view_count, 0) DESC',
    'clicks' => 'COALESCE(sc.click_count, 0) DESC',
    'ctr' => '(CASE WHEN COALESCE(sc.view_count,0) = 0 THEN 0 ELSE (COALESCE(sc.click_count,0)/sc.view_count) END) DESC',
    'spend' => 'COALESCE(sc.spent, 0) DESC'
];
$order_by = $order_by_map[$sort] ?? $order_by_map['newest'];

$query = "
    SELECT sc.id, sc.title, sc.sponsor_name, sc.description, sc.image_url, sc.target_url,
           sc.placement, sc.budget, sc.spent, sc.status, sc.start_date, sc.end_date, sc.created_at,
            COALESCE(sc.view_count, 0) as view_count,
            COALESCE(sc.click_count, 0) as click_count,
           u.full_name, u.username
    FROM sponsored_content sc
    LEFT JOIN users u ON sc.user_id = u.id
    $whereSql
    ORDER BY $order_by
    LIMIT 200
";

$stmt = $db->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$ads = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Manage Ads | CampMart Admin</title>
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
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500">Admin / Ads</p>
                    <h1 class="text-3xl font-bold text-brand-green">Manage Sponsored Ads</h1>
                    <p class="text-slate-500">Monitor placements, performance, and budgets.</p>
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
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Title, sponsor, or description" class="w-full pl-10 pr-3 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm" />
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">search</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                        <select name="status" class="w-full px-3 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm">
                            <option value="">Any</option>
                            <?php foreach (['active','paused','ended'] as $opt): ?>
                                <option value="<?= $opt ?>" <?= $status === $opt ? 'selected' : '' ?>><?= ucfirst($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Placement</label>
                        <select name="placement" class="w-full px-3 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm">
                            <option value="">Any</option>
                            <?php foreach (['sidebar','grid','hero','inline'] as $opt): ?>
                                <option value="<?= $opt ?>" <?= $placement === $opt ? 'selected' : '' ?>><?= ucfirst($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="md:col-span-4 grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Sort</label>
                            <select name="sort" class="w-full px-3 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm">
                                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
                                <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
                                <option value="budget_high" <?= $sort === 'budget_high' ? 'selected' : '' ?>>Budget: High to Low</option>
                                <option value="budget_low" <?= $sort === 'budget_low' ? 'selected' : '' ?>>Budget: Low to High</option>
                                <option value="views" <?= $sort === 'views' ? 'selected' : '' ?>>Most Viewed</option>
                                <option value="clicks" <?= $sort === 'clicks' ? 'selected' : '' ?>>Most Clicked</option>
                                <option value="ctr" <?= $sort === 'ctr' ? 'selected' : '' ?>>Highest CTR</option>
                                <option value="spend" <?= $sort === 'spend' ? 'selected' : '' ?>>Highest Spend</option>
                            </select>
                        </div>
                        <div class="flex justify-end gap-2 md:col-span-2 md:justify-end">
                            <a href="manage-ads.php" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-lg text-sm font-medium hover:bg-slate-200">Reset</a>
                            <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-semibold hover:bg-primary/90">Apply</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-100 text-sm text-slate-600 flex items-center justify-between">
                    <span>Showing <?= $ads ? $ads->num_rows : 0 ?> ads (max 200)</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-slate-700">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">Ad</th>
                                <th class="px-4 py-3 text-left font-semibold">Placement</th>
                                <th class="px-4 py-3 text-left font-semibold">Budget</th>
                                <th class="px-4 py-3 text-left font-semibold">Performance</th>
                                <th class="px-4 py-3 text-left font-semibold">Dates</th>
                                <th class="px-4 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if ($ads && $ads->num_rows > 0): ?>
                                <?php while($ad = $ads->fetch_assoc()): ?>
                                    <?php
                                        $ctr = ($ad['view_count'] > 0) ? ($ad['click_count'] / $ad['view_count']) * 100 : 0;
                                        $statusColor = [
                                            'active' => 'bg-emerald-100 text-emerald-700',
                                            'paused' => 'bg-amber-100 text-amber-700',
                                            'ended' => 'bg-slate-100 text-slate-700'
                                        ];
                                        $placementColor = [
                                            'sidebar' => 'bg-blue-100 text-blue-700',
                                            'grid' => 'bg-purple-100 text-purple-700',
                                            'hero' => 'bg-pink-100 text-pink-700',
                                            'inline' => 'bg-slate-100 text-slate-700'
                                        ];
                                    ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-4 py-3 align-top">
                                            <div class="font-semibold text-text-dark flex items-center gap-2">
                                                <?php if(!empty($ad['image_url'])): ?>
                                                    <img src="<?= htmlspecialchars($ad['image_url']) ?>" class="w-10 h-10 rounded object-cover border border-slate-200" alt="<?= htmlspecialchars($ad['title']) ?>" />
                                                <?php else: ?>
                                                    <span class="material-symbols-outlined text-slate-400">image</span>
                                                <?php endif; ?>
                                                <span><?= htmlspecialchars($ad['title']) ?></span>
                                                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold <?= $statusColor[$ad['status']] ?? 'bg-slate-100 text-slate-700' ?>"><?= ucfirst($ad['status']) ?></span>
                                            </div>
                                            <div class="text-xs text-slate-500">Sponsor: <?= htmlspecialchars($ad['sponsor_name'] ?? 'Unknown') ?></div>
                                            <div class="text-xs text-slate-500">Seller: <?= htmlspecialchars($ad['full_name'] ?? 'Unknown') ?> (<?= htmlspecialchars($ad['username'] ?? '—') ?>)</div>
                                            <?php if (!empty($ad['description'])): ?>
                                                <div class="text-xs text-slate-500 mt-1">"<?= htmlspecialchars(substr($ad['description'], 0, 80)) ?><?= strlen($ad['description']) > 80 ? '…' : '' ?>"</div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold <?= $placementColor[$ad['placement']] ?? 'bg-slate-100 text-slate-700' ?>"><?= ucfirst($ad['placement']) ?></span>
                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            <div class="font-semibold text-text-dark">₦<?= number_format($ad['budget'], 2) ?></div>
                                            <div class="text-xs text-slate-500">Spent: ₦<?= number_format($ad['spent'] ?? 0, 2) ?></div>
                                        </td>
                                        <td class="px-4 py-3 align-top text-sm text-text-dark">
                                            <div class="font-semibold">Views: <?= number_format($ad['view_count']) ?> · Clicks: <?= number_format($ad['click_count']) ?></div>
                                            <div class="text-xs text-slate-500">CTR: <?= number_format($ctr, 2) ?>%</div>
                                        </td>
                                        <td class="px-4 py-3 align-top text-xs text-slate-600">
                                            <?= date('M d, Y', strtotime($ad['start_date'])) ?> - <?= date('M d, Y', strtotime($ad['end_date'])) ?><br>
                                            <span class="text-[11px] text-slate-500">Created <?= date('M d, Y', strtotime($ad['created_at'])) ?></span>
                                        </td>
                                        <td class="px-4 py-3 align-top text-right space-x-2">
                                            <?php if (!empty($ad['target_url'])): ?>
                                            <a href="<?= htmlspecialchars($ad['target_url']) ?>" target="_blank" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-white border border-slate-200 text-xs font-semibold text-slate-700 hover:border-primary/60 hover:text-primary">
                                                <span class="material-symbols-outlined text-sm">open_in_new</span>
                                                Visit
                                            </a>
                                            <?php endif; ?>
                                            <button class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-white border border-slate-200 text-xs font-semibold text-slate-700 opacity-50 cursor-not-allowed" type="button">
                                                <span class="material-symbols-outlined text-sm">edit</span>
                                                Edit (wire)
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="px-4 py-6 text-center text-slate-500">No ads found for the current filters.</td>
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
