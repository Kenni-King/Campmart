<?php
session_start();
include_once 'includes/controller.php';

// Admin & superadmin only
$stmt = $db->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $userId);
$stmt->execute();
$result = $stmt->get_result();
$currentUser = $result->fetch_assoc();

if (!$currentUser || ($currentUser['role'] !== 'admin' && $currentUser['role'] !== 'superadmin')) {
    header('Location: index.php');
    exit;
}

// Filters
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$roleFilter = isset($_GET['role']) ? trim($_GET['role']) : '';

$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = "(u.username LIKE CONCAT('%', ?, '%') OR u.email LIKE CONCAT('%', ?, '%') OR u.full_name LIKE CONCAT('%', ?, '%'))";
    $params[] = $search;
    $params[] = $search;
    $params[] = $search;
    $types .= 'sss';
}

if ($status !== '') {
    $where[] = "u.status = ?";
    $params[] = $status;
    $types .= 's';
}

if ($roleFilter !== '') {
    $where[] = "u.role = ?";
    $params[] = $roleFilter;
    $types .= 's';
}

$whereSql = count($where) ? ('WHERE ' . implode(' AND ', $where)) : '';

$query = "
    SELECT u.id, u.username, u.email, u.full_name, u.phone, u.status, u.created_at, u.last_login,
           COALESCE(u.total_sales, 0) AS total_sales, COALESCE(u.total_purchases, 0) AS total_purchases,
           u.rating, u.total_ratings, u.role
    FROM users u
    $whereSql
    ORDER BY u.created_at DESC
    LIMIT 200
";

$stmt = $db->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$users = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Manage Users | CampMart Admin</title>
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
    <style>
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        .modal-overlay { background: rgba(0,0,0,0.5); }
    </style>
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

            <?php if (isset($_SESSION['error'])): ?>
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl flex items-center gap-3">
                    <span class="material-symbols-outlined text-red-600">error</span>
                    <p class="flex-1 text-sm"><?= htmlspecialchars($_SESSION['error']) ?></p>
                    <button onclick="this.parentElement.remove()" class="text-red-600 hover:text-red-800"><span class="material-symbols-outlined">close</span></button>
                </div>
            <?php unset($_SESSION['error']); endif; ?>

            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500">Admin / Users</p>
                    <h1 class="text-3xl font-bold text-brand-green">Manage Users</h1>
                    <p class="text-slate-500">Search, filter, and review user accounts.</p>
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
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Search by name, username, or email" class="w-full pl-10 pr-3 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm" />
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">search</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                        <select name="status" class="w-full px-3 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm">
                            <option value="">Any</option>
                            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="pending" <?= $status === 'pending' ? 'selected' : '' ?>>Pending</option>
                            <option value="suspended" <?= $status === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                            <option value="banned" <?= $status === 'banned' ? 'selected' : '' ?>>Banned</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Role</label>
                        <select name="role" class="w-full px-3 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm">
                            <option value="">Any</option>
                            <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="seller" <?= $roleFilter === 'seller' ? 'selected' : '' ?>>Seller</option>
                            <option value="user" <?= $roleFilter === 'user' ? 'selected' : '' ?>>Buyer</option>
                            <option value="affiliate" <?= $roleFilter === 'affiliate' ? 'selected' : '' ?>>Affiliate</option>
                        </select>
                    </div>
                    <div class="md:col-span-4 flex justify-end gap-2">
                        <a href="manage-users.php" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-lg text-sm font-medium hover:bg-slate-200">Reset</a>
                        <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-semibold hover:bg-primary/90">Apply Filters</button>
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-slate-700">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">User</th>
                                <th class="px-4 py-3 text-left font-semibold">Contact</th>
                                <th class="px-4 py-3 text-left font-semibold">Role</th>
                                <th class="px-4 py-3 text-left font-semibold">Status</th>
                                <th class="px-4 py-3 text-left font-semibold">Sales</th>
                                <th class="px-4 py-3 text-left font-semibold">Purchases</th>
                                <th class="px-4 py-3 text-left font-semibold">Rating</th>
                                <th class="px-4 py-3 text-left font-semibold">Joined</th>
                                <th class="px-4 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if ($users->num_rows > 0): ?>
                                <?php while($u = $users->fetch_assoc()): ?>
                                <tr class="hover:bg-slate-50">
                                    <td class="px-4 py-3">
                                        <div class="font-semibold text-text-dark flex items-center gap-2">
                                            <span class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-primary/10 text-primary font-bold">
                                                <?= strtoupper(substr($u['full_name'] ?? $u['username'], 0, 1)) ?>
                                            </span>
                                            <div>
                                                <p><?= htmlspecialchars($u['full_name'] ?: '—') ?></p>
                                                <p class="text-xs text-slate-500">@<?= htmlspecialchars($u['username']) ?></p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600">
                                        <p><?= htmlspecialchars($u['email']) ?></p>
                                        <p class="text-xs text-slate-500"><?= htmlspecialchars($u['phone'] ?: 'N/A') ?></p>
                                    </td>
                                    <td class="px-4 py-3">
                                        <?php
                                            $roleColors = [
                                                'admin' => 'bg-purple-100 text-purple-700',
                                                'superadmin' => 'bg-purple-100 text-purple-700',
                                                'seller' => 'bg-blue-100 text-blue-700',
                                                'user' => 'bg-slate-100 text-slate-700',
                                                'affiliate' => 'bg-amber-100 text-amber-700',
                                            ];
                                            $roleCls = $roleColors[$u['role']] ?? 'bg-slate-100 text-slate-700';
                                        ?>
                                        <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold <?= $roleCls ?>">
                                            <?= $u['role'] === 'user' ? 'Buyer' : htmlspecialchars(ucfirst($u['role'])) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3">
                                        <?php
                                            $statusColor = [
                                                'active' => 'bg-emerald-100 text-emerald-700',
                                                'pending' => 'bg-amber-100 text-amber-700',
                                                'suspended' => 'bg-rose-100 text-rose-700',
                                                'banned' => 'bg-red-100 text-red-700'
                                            ];
                                            $cls = $statusColor[$u['status']] ?? 'bg-slate-100 text-slate-700';
                                        ?>
                                        <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold <?= $cls ?>">
                                            <?= htmlspecialchars($u['status']) ?>
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 font-semibold text-text-dark">
                                        <?= (int)$u['total_sales'] ?>
                                    </td>
                                    <td class="px-4 py-3 font-semibold text-text-dark">
                                        <?= (int)$u['total_purchases'] ?>
                                    </td>
                                    <td class="px-4 py-3 text-text-dark">
                                        <?= number_format($u['rating'], 2) ?>
                                        <span class="text-xs text-slate-500">(<?= (int)$u['total_ratings'] ?>)</span>
                                    </td>
                                    <td class="px-4 py-3 text-slate-600 text-xs">
                                        <?= date('M d, Y', strtotime($u['created_at'])) ?><br>
                                        <span class="text-[11px] text-slate-500">Last login: <?= $u['last_login'] ? date('M d, Y', strtotime($u['last_login'])) : '—' ?></span>
                                    </td>
                                    <td class="px-4 py-3 text-right space-x-2">
                                        <a href="admin-user.php?id=<?= (int)$u['id'] ?>" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-white border border-slate-200 text-xs font-semibold text-slate-700 hover:border-primary/60 hover:text-primary">
                                            <span class="material-symbols-outlined text-sm">visibility</span>
                                            View
                                        </a>
                                    </td>
                                </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="px-4 py-6 text-center text-slate-500">No users found for the current filters.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="px-4 py-3 text-xs text-slate-500 border-t border-slate-100">
                    Showing up to 200 recent users. Add pagination if your dataset grows.
                </div>
            </div>
        </div>
    </main>
</body>
</html>
