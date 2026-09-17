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
$type = trim($_GET['type'] ?? '');
$reason = trim($_GET['reason'] ?? '');
$sort = $_GET['sort'] ?? 'newest';

$conditions = [];
$params = [];
$types = '';

if ($search !== '') {
    $conditions[] = "(rp.description LIKE CONCAT('%', ?, '%')
        OR rp.resolution_notes LIKE CONCAT('%', ?, '%')
        OR reporter.username LIKE CONCAT('%', ?, '%')
        OR reporter.full_name LIKE CONCAT('%', ?, '%')
        OR reported.username LIKE CONCAT('%', ?, '%')
        OR reported.full_name LIKE CONCAT('%', ?, '%')
        OR p.title LIKE CONCAT('%', ?, '%')
        OR s.title LIKE CONCAT('%', ?, '%'))";
    $params = array_merge($params, array_fill(0, 8, $search));
    $types .= str_repeat('s', 8);
}

if ($status !== '') {
    $conditions[] = 'rp.status = ?';
    $params[] = $status;
    $types .= 's';
}

if ($type !== '') {
    $conditions[] = 'rp.report_type = ?';
    $params[] = $type;
    $types .= 's';
}

if ($reason !== '') {
    $conditions[] = 'rp.reason = ?';
    $params[] = $reason;
    $types .= 's';
}

$whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$order_by_map = [
    'newest' => 'rp.created_at DESC',
    'oldest' => 'rp.created_at ASC',
    'status' => 'rp.status ASC, rp.created_at DESC',
];
$order_by = $order_by_map[$sort] ?? $order_by_map['newest'];

$query = "
    SELECT rp.id, rp.report_type, rp.reason, rp.description, rp.status, rp.created_at, rp.resolved_at,
           rp.resolution_notes, rp.product_id, rp.service_id, rp.reporter_id, rp.reported_user_id, rp.reviewed_by,
           reporter.full_name AS reporter_name, reporter.username AS reporter_username,
           reported.full_name AS reported_name, reported.username AS reported_username,
           reviewer.full_name AS reviewer_name, reviewer.username AS reviewer_username,
           p.title AS product_title, s.title AS service_title
    FROM reports rp
    LEFT JOIN users reporter ON rp.reporter_id = reporter.id
    LEFT JOIN users reported ON rp.reported_user_id = reported.id
    LEFT JOIN users reviewer ON rp.reviewed_by = reviewer.id
    LEFT JOIN products p ON rp.product_id = p.id
    LEFT JOIN services s ON rp.service_id = s.id
    $whereSql
    ORDER BY $order_by
    LIMIT 200
";

$stmt = $db->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$reports = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Reported Content | CampMart Admin</title>
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
                    <p class="text-sm text-slate-500">Admin / Reports</p>
                    <h1 class="text-3xl font-bold text-brand-green">Reported Content</h1>
                    <p class="text-slate-500">Review abuse reports and take action.</p>
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
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Reporter, reported user, description" class="w-full pl-10 pr-3 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm" />
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">search</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                        <select name="status" class="w-full px-3 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm">
                            <option value="">Any</option>
                            <?php foreach (['pending','under_review','resolved','dismissed'] as $opt): ?>
                                <option value="<?= $opt ?>" <?= $status === $opt ? 'selected' : '' ?>><?= ucwords(str_replace('_',' ', $opt)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Type</label>
                        <select name="type" class="w-full px-3 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm">
                            <option value="">Any</option>
                            <?php foreach (['user','product','service','message'] as $opt): ?>
                                <option value="<?= $opt ?>" <?= $type === $opt ? 'selected' : '' ?>><?= ucfirst($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Reason</label>
                        <select name="reason" class="w-full px-3 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm">
                            <option value="">Any</option>
                            <?php foreach (['spam','inappropriate','fraud','duplicate','other'] as $opt): ?>
                                <option value="<?= $opt ?>" <?= $reason === $opt ? 'selected' : '' ?>><?= ucfirst($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="md:col-span-4 grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Sort</label>
                            <select name="sort" class="w-full px-3 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm">
                                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
                                <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
                                <option value="status" <?= $sort === 'status' ? 'selected' : '' ?>>Status</option>
                            </select>
                        </div>
                        <div class="flex justify-end gap-2 md:col-span-2 md:justify-end">
                            <a href="reported-content.php" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-lg text-sm font-medium hover:bg-slate-200">Reset</a>
                            <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-semibold hover:bg-primary/90">Apply</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-100 text-sm text-slate-600 flex items-center justify-between">
                    <span>Showing <?= $reports ? $reports->num_rows : 0 ?> reports (max 200)</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-slate-700">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">Subject</th>
                                <th class="px-4 py-3 text-left font-semibold">Reporter</th>
                                <th class="px-4 py-3 text-left font-semibold">Target</th>
                                <th class="px-4 py-3 text-left font-semibold">Status</th>
                                <th class="px-4 py-3 text-left font-semibold">Notes</th>
                                <th class="px-4 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if ($reports && $reports->num_rows > 0): ?>
                                <?php while($report = $reports->fetch_assoc()): ?>
                                    <?php
                                        $statusColor = [
                                            'pending' => 'bg-amber-100 text-amber-700',
                                            'under_review' => 'bg-blue-100 text-blue-700',
                                            'resolved' => 'bg-emerald-100 text-emerald-700',
                                            'dismissed' => 'bg-slate-100 text-slate-700'
                                        ];
                                        $typeColor = [
                                            'user' => 'bg-indigo-100 text-indigo-700',
                                            'product' => 'bg-purple-100 text-purple-700',
                                            'service' => 'bg-pink-100 text-pink-700',
                                            'message' => 'bg-slate-100 text-slate-700'
                                        ];
                                        $targetLabel = 'N/A';
                                        if ($report['report_type'] === 'user') {
                                            $targetLabel = 'User: ' . htmlspecialchars($report['reported_name'] ?? 'Unknown') . ' (' . htmlspecialchars($report['reported_username'] ?? '—') . ')';
                                        } elseif ($report['report_type'] === 'product') {
                                            $targetLabel = 'Product #' . (int)$report['product_id'] . ' — ' . htmlspecialchars($report['product_title'] ?? 'Unknown');
                                        } elseif ($report['report_type'] === 'service') {
                                            $targetLabel = 'Service #' . (int)$report['service_id'] . ' — ' . htmlspecialchars($report['service_title'] ?? 'Unknown');
                                        } elseif ($report['report_type'] === 'message') {
                                            $targetLabel = 'Message conversation';
                                        }
                                    ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-4 py-3 align-top">
                                            <div class="flex items-center gap-2">
                                                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold <?= $typeColor[$report['report_type']] ?? 'bg-slate-100 text-slate-700' ?>"><?= ucfirst($report['report_type']) ?></span>
                                                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-slate-100 text-slate-700"><?= ucfirst($report['reason']) ?></span>
                                            </div>
                                            <div class="text-sm font-semibold text-text-dark mt-1">#<?= (int)$report['id'] ?> · <?= htmlspecialchars(substr($report['description'], 0, 90)) ?><?= strlen($report['description']) > 90 ? '…' : '' ?></div>
                                            <div class="text-xs text-slate-500">Filed <?= date('M d, Y', strtotime($report['created_at'])) ?></div>
                                        </td>
                                        <td class="px-4 py-3 align-top text-sm text-text-dark">
                                            <div class="font-semibold"><?= htmlspecialchars($report['reporter_name'] ?? 'Unknown') ?></div>
                                            <div class="text-xs text-slate-500">@<?= htmlspecialchars($report['reporter_username'] ?? '—') ?></div>
                                        </td>
                                        <td class="px-4 py-3 align-top text-sm text-text-dark">
                                            <div class="font-semibold"><?= $targetLabel ?></div>
                                            <?php if (!empty($report['reported_name'])): ?>
                                                <div class="text-xs text-slate-500">Reported user: <?= htmlspecialchars($report['reported_name']) ?> (@<?= htmlspecialchars($report['reported_username'] ?? '—') ?>)</div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 align-top text-sm text-text-dark">
                                            <div class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold <?= $statusColor[$report['status']] ?? 'bg-slate-100 text-slate-700' ?>"><?= ucwords(str_replace('_',' ', $report['status'])) ?></div>
                                            <?php if (!empty($report['reviewer_name'])): ?>
                                                <div class="text-xs text-slate-500 mt-1">By <?= htmlspecialchars($report['reviewer_name']) ?> (@<?= htmlspecialchars($report['reviewer_username'] ?? '—') ?>)</div>
                                            <?php endif; ?>
                                            <?php if (!empty($report['resolved_at'])): ?>
                                                <div class="text-xs text-slate-500">Closed <?= date('M d, Y', strtotime($report['resolved_at'])) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 align-top text-xs text-slate-600">
                                            <?php if (!empty($report['resolution_notes'])): ?>
                                                <div class="text-sm text-text-dark">Notes: <?= htmlspecialchars(substr($report['resolution_notes'], 0, 120)) ?><?= strlen($report['resolution_notes']) > 120 ? '…' : '' ?></div>
                                            <?php else: ?>
                                                <div class="text-slate-500">No notes yet</div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 align-top text-right space-x-2">
                                            <button class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-white border border-slate-200 text-xs font-semibold text-slate-700 opacity-50 cursor-not-allowed" type="button">
                                                <span class="material-symbols-outlined text-sm">rule</span>
                                                Take action (wire)
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="px-4 py-6 text-center text-slate-500">No reports found for the current filters.</td>
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
