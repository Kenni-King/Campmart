<?php
session_start();
include_once 'includes/controller.php';

if (!isset($userId) || !isset($currentUser) || !in_array($currentUser['role'], ['admin', 'superadmin'])) {
    header('Location: index.php');
    exit;
}

$search = trim($_GET['search'] ?? '');
$type = trim($_GET['type'] ?? '');
$status = trim($_GET['status'] ?? '');
$sort = $_GET['sort'] ?? 'newest';

$conditions = [];
$params = [];
$types = '';

if ($search !== '') {
    $conditions[] = "(l.title LIKE CONCAT('%', ?, '%') OR l.description LIKE CONCAT('%', ?, '%') OR l.category LIKE CONCAT('%', ?, '%') OR l.location_lost_found LIKE CONCAT('%', ?, '%') OR l.contact_info LIKE CONCAT('%', ?, '%'))";
    $params = array_merge($params, array_fill(0, 5, $search));
    $types .= 'sssss';
}

if ($type !== '') {
    $conditions[] = 'l.type = ?';
    $params[] = $type;
    $types .= 's';
}

if ($status !== '') {
    $conditions[] = 'l.status = ?';
    $params[] = $status;
    $types .= 's';
}

$whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$order_by = $sort === 'oldest' ? 'l.created_at ASC' : 'l.created_at DESC';

$query = "
    SELECT l.*, u.full_name, u.username, c.full_name AS claimer_name, c.username AS claimer_username
    FROM lost_found_items l
    LEFT JOIN users u ON l.user_id = u.id
    LEFT JOIN users c ON l.claimed_by = c.id
    $whereSql
    ORDER BY $order_by
    LIMIT 200
";

$stmt = $db->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$items = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Manage Lost & Found | CampMart Admin</title>
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
                    <p class="text-sm text-slate-500">Admin / Lost & Found</p>
                    <h1 class="text-3xl font-bold text-brand-green">Manage Lost & Found</h1>
                    <p class="text-slate-500">Review reports, statuses, and ownership.</p>
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
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Title, description, category, location, contact" class="w-full pl-10 pr-3 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm" />
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">search</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Type</label>
                        <select name="type" class="w-full px-3 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm">
                            <option value="">Any</option>
                            <option value="lost" <?= $type === 'lost' ? 'selected' : '' ?>>Lost</option>
                            <option value="found" <?= $type === 'found' ? 'selected' : '' ?>>Found</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                        <select name="status" class="w-full px-3 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm">
                            <option value="">Any</option>
                            <option value="open" <?= $status === 'open' ? 'selected' : '' ?>>Open</option>
                            <option value="claimed" <?= $status === 'claimed' ? 'selected' : '' ?>>Claimed</option>
                            <option value="closed" <?= $status === 'closed' ? 'selected' : '' ?>>Closed</option>
                        </select>
                    </div>
                    <div class="md:col-span-4 grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Sort</label>
                            <select name="sort" class="w-full px-3 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm">
                                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
                                <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
                            </select>
                        </div>
                        <div class="flex justify-end gap-2 md:col-span-2 md:justify-end">
                            <a href="manage-lost-found.php" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-lg text-sm font-medium hover:bg-slate-200">Reset</a>
                            <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-semibold hover:bg-primary/90">Apply</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-100 text-sm text-slate-600 flex items-center justify-between">
                    <span>Showing <?= $items ? $items->num_rows : 0 ?> items (max 200)</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-slate-700">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">Item</th>
                                <th class="px-4 py-3 text-left font-semibold">Type</th>
                                <th class="px-4 py-3 text-left font-semibold">Status</th>
                                <th class="px-4 py-3 text-left font-semibold">Reporter</th>
                                <th class="px-4 py-3 text-left font-semibold">Contact</th>
                                <th class="px-4 py-3 text-left font-semibold">Location/Date</th>
                                <th class="px-4 py-3 text-left font-semibold">Created</th>
                                <th class="px-4 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if ($items && $items->num_rows > 0): ?>
                                <?php while($item = $items->fetch_assoc()): ?>
                                    <?php
                                        $typeColor = $item['type'] === 'found' ? 'bg-blue-100 text-blue-700' : 'bg-amber-100 text-amber-700';
                                        $statusColor = [
                                            'open' => 'bg-emerald-100 text-emerald-700',
                                            'claimed' => 'bg-blue-100 text-blue-700',
                                            'closed' => 'bg-slate-100 text-slate-700'
                                        ];
                                    ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-4 py-3 align-top">
                                            <div class="flex items-start gap-3">
                                                <?php if (!empty($item['image_url'])): ?>
                                                    <img src="<?= htmlspecialchars($item['image_url']) ?>" class="w-12 h-12 rounded-md object-cover border border-slate-200" alt="<?= htmlspecialchars($item['title']) ?>" />
                                                <?php else: ?>
                                                    <span class="material-symbols-outlined text-slate-400">image</span>
                                                <?php endif; ?>
                                                <div class="min-w-0">
                                                    <div class="font-semibold text-text-dark break-words"><?= htmlspecialchars($item['title']) ?></div>
                                                    <?php if (!empty($item['description'])): ?>
                                                        <div class="text-xs text-slate-500 mt-1">"<?= htmlspecialchars(substr($item['description'], 0, 80)) ?><?= strlen($item['description']) > 80 ? '…' : '' ?>"</div>
                                                    <?php endif; ?>
                                                    <?php if (!empty($item['category'])): ?>
                                                        <div class="text-xs text-slate-500 mt-1">Category: <?= htmlspecialchars($item['category']) ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold <?= $typeColor ?>"><?= ucfirst($item['type']) ?></span>
                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold <?= $statusColor[$item['status']] ?? 'bg-slate-100 text-slate-700' ?>"><?= ucfirst($item['status']) ?></span>
                                            <?php if($item['status'] === 'claimed' && $item['claimer_name']): ?>
                                                <div class="text-[11px] text-slate-500 mt-1">Claimed by <?= htmlspecialchars($item['claimer_name']) ?> (<?= htmlspecialchars($item['claimer_username'] ?? '—') ?>)</div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 align-top text-xs text-slate-600">
                                            <?= htmlspecialchars($item['full_name'] ?? 'Unknown') ?><br>
                                            <span class="text-[11px] text-slate-500">@<?= htmlspecialchars($item['username'] ?? '—') ?></span>
                                        </td>
                                        <td class="px-4 py-3 align-top text-xs text-slate-600 whitespace-pre-wrap">
                                            <?= htmlspecialchars($item['contact_info'] ?: '—') ?>
                                        </td>
                                        <td class="px-4 py-3 align-top text-xs text-slate-600">
                                            <?php if(!empty($item['location_lost_found'])): ?>
                                                <div><?= htmlspecialchars($item['location_lost_found']) ?></div>
                                            <?php endif; ?>
                                            <?php if(!empty($item['date_lost_found'])): ?>
                                                <div class="text-[11px] text-slate-500">Date: <?= date('M d, Y', strtotime($item['date_lost_found'])) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 align-top text-xs text-slate-600">
                                            <?= date('M d, Y', strtotime($item['created_at'])) ?>
                                        </td>
                                        <td class="px-4 py-3 align-top text-right space-x-2">
                                            <button class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-white border border-slate-200 text-xs font-semibold text-slate-700 opacity-50 cursor-not-allowed" type="button">
                                                <span class="material-symbols-outlined text-sm">visibility</span>
                                                View (wire)
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="px-4 py-6 text-center text-slate-500">No lost & found items match the current filters.</td>
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
