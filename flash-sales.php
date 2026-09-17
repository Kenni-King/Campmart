<?php
session_start();
include_once 'includes/controller.php';

// Admin gate
if (!isset($userId) || !isset($currentUser) || !in_array($currentUser['role'], ['admin','superadmin'])) {
    header('Location: index.php');
    exit;
}

$status_filter = $_GET['status'] ?? 'all';
$allowed_status = ['all','active','scheduled','expired','cancelled'];
if (!in_array($status_filter, $allowed_status)) {
    $status_filter = 'all';
}

$status_sql = $status_filter !== 'all' ? "WHERE fs.status = '" . $db->real_escape_string($status_filter) . "'" : '';

$flash_sales_query = "
    SELECT fs.*, 
           u.full_name as creator_name,
           COUNT(DISTINCT fsp.id) as product_count,
           SUM(fsp.sold_count) as total_sold,
           COUNT(DISTINCT fsv.id) as total_views
    FROM flash_sales fs
    LEFT JOIN users u ON fs.created_by = u.id
    LEFT JOIN flash_sale_products fsp ON fs.id = fsp.flash_sale_id
    LEFT JOIN flash_sale_views fsv ON fs.id = fsv.flash_sale_id
    $status_sql
    GROUP BY fs.id
    ORDER BY fs.created_at DESC
";
$flash_sales = $db->query($flash_sales_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Flash Sales | CampMart Admin</title>
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
                    fontFamily: {
                        display: ['Inter']
                    }
                }
            }
        }
    </script>
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
    </style>
</head>
<body class="bg-background-main min-h-screen text-text-dark">
    <?php include_once 'includes/user-nav.php'; ?>
    <main class="flex-1 overflow-y-auto bg-background-main p-4 md:p-6 lg:p-8">
        <div class="max-w-7xl mx-auto space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-brand-green">Flash Sales</h1>
                    <p class="text-slate-500">Create and manage time-limited promotional sales.</p>
                </div>
                <div class="flex gap-2">
                    <a href="index.php" class="px-4 py-2 bg-white border border-slate-200 text-slate-700 rounded-lg font-medium hover:bg-slate-50">View Site</a>
                    <a href="create-flash-sale.php" class="flex items-center gap-2 px-4 py-2 bg-primary text-white rounded-lg font-semibold hover:bg-primary/90">
                        <span class="material-symbols-outlined text-xl">add</span>
                        Create Flash Sale
                    </a>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="border-b border-slate-200">
                <nav class="flex gap-6 text-sm font-semibold">
                    <?php foreach(['all'=>'All','active'=>'Active','scheduled'=>'Scheduled','expired'=>'Expired','cancelled'=>'Cancelled'] as $key=>$label): ?>
                        <a href="?status=<?= $key ?>" class="py-3 px-1 border-b-2 <?= $status_filter === $key ? 'border-primary text-primary' : 'border-transparent text-slate-500 hover:text-slate-700' ?>">
                            <?= $label ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>

            <!-- Flash Sales List -->
            <?php if ($flash_sales && $flash_sales->num_rows > 0): ?>
                <div class="space-y-4">
                    <?php while ($sale = $flash_sales->fetch_assoc()): 
                        $now = new DateTime();
                        $start = new DateTime($sale['start_time']);
                        $end = new DateTime($sale['end_time']);
                        $is_active = $now >= $start && $now <= $end;
                        $time_remaining = $is_active ? $end->diff($now)->format('%h hrs %i mins') : '';
                        $status_colors = [
                            'active' => 'bg-emerald-100 text-emerald-700',
                            'scheduled' => 'bg-blue-100 text-blue-700',
                            'expired' => 'bg-slate-100 text-slate-700',
                            'cancelled' => 'bg-red-100 text-red-700'
                        ];
                        $badge_color = $status_colors[$sale['status']] ?? 'bg-slate-100 text-slate-700';
                    ?>
                    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 hover:shadow-md transition">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1 space-y-3">
                                <div class="flex items-center gap-3 flex-wrap">
                                    <h3 class="text-xl font-bold text-text-dark"><?= htmlspecialchars($sale['title']) ?></h3>
                                    <span class="px-3 py-1 rounded-full text-xs font-semibold <?= $badge_color ?>"><?= strtoupper($sale['status']) ?></span>
                                    <?php if ($sale['is_featured']): ?>
                                        <span class="px-3 py-1 rounded-full text-xs font-semibold bg-secondary/20 text-secondary">Featured</span>
                                    <?php endif; ?>
                                </div>
                                <?php if(!empty($sale['description'])): ?>
                                <p class="text-slate-600 max-w-2xl"><?= htmlspecialchars($sale['description']) ?></p>
                                <?php endif; ?>
                                <div class="grid grid-cols-2 md:grid-cols-5 gap-4 text-sm">
                                    <div>
                                        <p class="text-xs text-slate-500">Discount</p>
                                        <p class="text-lg font-bold text-secondary"><?= number_format($sale['discount_percentage']) ?>%</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-slate-500">Products</p>
                                        <p class="text-lg font-bold text-text-dark"><?= (int)$sale['product_count'] ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-slate-500">Sold</p>
                                        <p class="text-lg font-bold text-emerald-600"><?= (int)($sale['total_sold'] ?? 0) ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-slate-500">Views</p>
                                        <p class="text-lg font-bold text-text-dark"><?= (int)($sale['total_views'] ?? 0) ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-slate-500">Created</p>
                                        <p class="text-sm font-semibold text-text-dark"><?= date('M d, Y', strtotime($sale['created_at'])) ?></p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-4 text-sm text-slate-600">
                                    <span class="flex items-center gap-1">
                                        <span class="material-symbols-outlined text-base">schedule</span>
                                        <?= date('M d, Y g:i A', strtotime($sale['start_time'])) ?>
                                    </span>
                                    <span>→</span>
                                    <span class="flex items-center gap-1">
                                        <span class="material-symbols-outlined text-base">event</span>
                                        <?= date('M d, Y g:i A', strtotime($sale['end_time'])) ?>
                                    </span>
                                    <?php if($is_active): ?>
                                    <span class="flex items-center gap-1 text-emerald-700 font-semibold">
                                        <span class="material-symbols-outlined text-base">timer</span>
                                        <?= $time_remaining ?> left
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-xs text-slate-500">Created by <?= htmlspecialchars($sale['creator_name'] ?? 'Unknown') ?></p>
                            </div>
                            <div class="flex flex-col gap-2">
                                <a href="flash-sale-products.php?sale_id=<?= $sale['id'] ?>" class="p-2 text-slate-600 hover:bg-slate-100 rounded-lg transition" title="Manage Products">
                                    <span class="material-symbols-outlined">inventory_2</span>
                                </a>
                                <a href="create-flash-sale.php?id=<?= $sale['id'] ?>" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition" title="Edit">
                                    <span class="material-symbols-outlined">edit</span>
                                </a>
                                <?php if ($sale['status'] !== 'cancelled'): ?>
                                <button onclick="cancelSale(<?= $sale['id'] ?>)" class="p-2 text-red-600 hover:bg-red-50 rounded-lg transition" title="Cancel Sale">
                                    <span class="material-symbols-outlined">cancel</span>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="bg-white rounded-xl border border-slate-200 p-12 text-center">
                    <span class="material-symbols-outlined text-6xl text-slate-300 mb-4">local_offer</span>
                    <h3 class="text-xl font-bold text-text-dark mb-2">No Flash Sales Found</h3>
                    <p class="text-slate-600 mb-6">Get started by creating your first flash sale.</p>
                    <a href="create-flash-sale.php" class="inline-flex items-center gap-2 px-6 py-3 bg-primary text-white rounded-lg hover:bg-primary/90 transition">
                        <span class="material-symbols-outlined">add</span>
                        Create Flash Sale
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        function cancelSale(saleId) {
            if (!confirm('Cancel this flash sale? This cannot be undone.')) return;
            fetch('api/admin/cancel-flash-sale.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ sale_id: saleId })
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert('Flash sale cancelled');
                    location.reload();
                } else {
                    alert(data.message || 'Failed to cancel sale');
                }
            })
            .catch(err => {
                console.error(err);
                alert('An error occurred');
            });
        }
    </script>
</body>
</html>
