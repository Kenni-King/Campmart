<?php
session_start();
require_once '../includes/controller.php';

if (!isset($_SESSION['userAppId'])) {
    header('Location: ../login.php');
    exit;
}
if (($currentUser['role'] ?? '') !== 'rider') {
    header('Location: ../index.php');
    exit;
}

$userId = (int) $_SESSION['userAppId'];

$activeTasks = $db->query("
    SELECT dt.*, o.order_number, o.delivery_location, o.total_amount, o.buyer_phone,
           o.status as order_status, o.seller_id, o.buyer_id,
           p.title as product_title, p.slug as product_slug,
           p.metadata,
           seller.full_name as seller_name, seller.phone as seller_phone,
           buyer.full_name as buyer_name, buyer.phone as buyer_phone
    FROM delivery_tasks dt
    JOIN orders o ON dt.order_id = o.id
    JOIN products p ON o.product_id = p.id
    JOIN users seller ON o.seller_id = seller.id
    JOIN users buyer ON o.buyer_id = buyer.id
    WHERE dt.rider_id = $userId AND dt.status IN ('interested','assigned','picked_up')
    ORDER BY dt.updated_at DESC
");

$completedTasks = $db->query("
    SELECT dt.*, o.order_number, o.delivery_location, o.total_amount,
           p.title as product_title, p.slug as product_slug,
           seller.full_name as seller_name
    FROM delivery_tasks dt
    JOIN orders o ON dt.order_id = o.id
    JOIN products p ON o.product_id = p.id
    JOIN users seller ON o.seller_id = seller.id
    WHERE dt.rider_id = $userId AND dt.status IN ('delivered','completed','cancelled')
    ORDER BY dt.updated_at DESC LIMIT 20
");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_task_status'])) {
    $taskId = (int) ($_POST['task_id'] ?? 0);
    $newStatus = $_POST['new_status'] ?? '';
    $allowed = ['picked_up', 'delivered'];
    if (in_array($newStatus, $allowed)) {
        $check = $db->query("SELECT 1 FROM delivery_tasks WHERE id = $taskId AND rider_id = $userId AND status IN ('assigned','picked_up')");
        if ($check && $check->num_rows) {
            $db->query("UPDATE delivery_tasks SET status = '$newStatus' WHERE id = $taskId");
            $_SESSION['success'] = 'Task status updated.';
        } else {
            $_SESSION['error'] = 'Cannot update this task.';
        }
    }
    header('Location: my-tasks.php');
    exit;
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<base href="<?php echo SITE_URL; ?>rider/">
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>My Deliveries | Rider | CampMart</title>
<script src="../tailwind34.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script>
tailwind.config = {
    darkMode: "class",
    theme: {
        extend: {
            colors: { primary: "#064E3B", secondary: "#F97316", accent: "#BEF264", surface: "#F8FAFC", "brand-green": "#064E3B", "brand-green-light": "#F0FDF4", "background-main": "#F9FAFB", "surface-white": "#FFFFFF", "text-dark": "#1F2937" },
            fontFamily: { display: ["Plus Jakarta Sans", "sans-serif"] }
        }
    }
}
</script>
<style>
body { font-family: 'Plus Jakarta Sans', sans-serif; }
.material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 450, 'GRAD' 0, 'opsz' 24; }
</style>
</head>
<body class="bg-background-main min-h-screen text-text-dark">
<?php include_once '../includes/user-nav.php'; ?>

<main class="flex-1 overflow-y-auto bg-background-main p-4 md:p-6 lg:p-8">
    <div class="max-w-[1200px] mx-auto">
    <div class="mb-6">
        <a href="dashboard.php" class="inline-flex items-center gap-1 text-sm text-slate-500 hover:text-primary mb-3">
            <span class="material-symbols-outlined text-base">arrow_back</span>
            Back to Dashboard
        </a>
        <h1 class="text-2xl font-extrabold text-primary">My Deliveries</h1>
        <p class="text-sm text-slate-500 mt-1">Track your active and completed delivery tasks.</p>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <section class="mb-8">
        <h2 class="text-lg font-bold text-slate-900 mb-4">
            Active Tasks
            <?php if ($activeTasks): ?><span class="text-sm font-normal text-slate-500">(<?php echo $activeTasks->num_rows; ?>)</span><?php endif; ?>
        </h2>

        <?php if ($activeTasks && $activeTasks->num_rows > 0): ?>
            <div class="space-y-4">
                <?php while ($task = $activeTasks->fetch_assoc()): ?>
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div class="flex items-center gap-3 mb-3">
                            <span class="rounded-full border px-3 py-1 text-xs font-semibold
                                <?php echo $task['status'] === 'interested' ? 'bg-amber-50 text-amber-700 border-amber-200' : ($task['status'] === 'assigned' ? 'bg-blue-50 text-blue-700 border-blue-200' : 'bg-indigo-50 text-indigo-700 border-indigo-200'); ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                            </span>
                            <span class="text-xs text-slate-400">#<?php echo htmlspecialchars($task['order_number']); ?></span>
                        </div>
                        <h3 class="font-bold text-slate-900"><?php echo htmlspecialchars($task['product_title']); ?></h3>
                        <div class="mt-2 grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm text-slate-600">
                            <div>
                                <p class="text-xs text-slate-400 font-semibold">Seller</p>
                                <p class="font-medium"><?php echo htmlspecialchars($task['seller_name']); ?></p>
                                <p class="text-xs"><?php echo htmlspecialchars($task['seller_phone']); ?></p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-400 font-semibold">Buyer</p>
                                <p class="font-medium"><?php echo htmlspecialchars($task['buyer_name']); ?></p>
                                <p class="text-xs"><?php echo htmlspecialchars($task['buyer_phone']); ?></p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-400 font-semibold">Pickup / Location</p>
                                <p class="font-medium"><?php echo htmlspecialchars($task['delivery_location']); ?></p>
                            </div>
                            <div>
                                <p class="text-xs text-slate-400 font-semibold">Pay</p>
                                <p class="font-bold text-primary"><?php echo formatCurrency((float)$task['total_amount']); ?></p>
                            </div>
                        </div>
                        <?php if ($task['status'] === 'assigned'): ?>
                            <form method="POST" class="mt-4">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                                <input type="hidden" name="task_id" value="<?php echo (int)$task['id']; ?>">
                                <input type="hidden" name="new_status" value="picked_up">
                                <button type="submit" name="update_task_status" class="inline-flex items-center gap-2 rounded-2xl bg-indigo-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-indigo-700 transition-all">
                                    <span class="material-symbols-outlined text-base">package_2</span>
                                    Mark as Picked Up
                                </button>
                            </form>
                        <?php elseif ($task['status'] === 'picked_up'): ?>
                            <form method="POST" class="mt-4">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                                <input type="hidden" name="task_id" value="<?php echo (int)$task['id']; ?>">
                                <input type="hidden" name="new_status" value="delivered">
                                <button type="submit" name="update_task_status" class="inline-flex items-center gap-2 rounded-2xl bg-emerald-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-emerald-700 transition-all">
                                    <span class="material-symbols-outlined text-base">check_circle</span>
                                    Mark as Delivered
                                </button>
                            </form>
                        <?php elseif ($task['status'] === 'interested'): ?>
                            <div class="mt-4 rounded-2xl bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-amber-800">
                                <span class="material-symbols-outlined text-sm align-middle">info</span>
                                Waiting for the seller to assign you. You'll be notified once assigned.
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-8 text-center">
                <span class="material-symbols-outlined text-4xl text-slate-300">assignment</span>
                <p class="mt-3 text-sm text-slate-500">No active deliveries. Browse available orders to get started.</p>
                <a href="available-orders.php" class="mt-3 inline-flex items-center gap-2 rounded-2xl bg-primary px-5 py-2.5 text-sm font-bold text-white hover:bg-primary/90 transition-all">
                    <span class="material-symbols-outlined text-base">explore</span>
                    Find Orders
                </a>
            </div>
        <?php endif; ?>
    </section>

    <section>
        <h2 class="text-lg font-bold text-slate-900 mb-4">Completed History</h2>
        <?php if ($completedTasks && $completedTasks->num_rows > 0): ?>
            <div class="space-y-3">
                <?php while ($task = $completedTasks->fetch_assoc()): ?>
                    <div class="rounded-2xl border border-slate-200 bg-white p-4 flex items-center justify-between gap-4">
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-900"><?php echo htmlspecialchars($task['product_title']); ?></p>
                            <p class="text-xs text-slate-500">#<?php echo htmlspecialchars($task['order_number']); ?> &middot; <?php echo htmlspecialchars($task['seller_name']); ?></p>
                        </div>
                        <span class="shrink-0 rounded-full border px-3 py-1 text-xs font-semibold
                            <?php echo $task['status'] === 'cancelled' ? 'bg-red-50 text-red-700 border-red-200' : 'bg-emerald-50 text-emerald-700 border-emerald-200'; ?>">
                            <?php echo ucfirst($task['status']); ?>
                        </span>
                    </div>
                <?php endwhile; ?>
            </div>
        <?php else: ?>
            <p class="text-sm text-slate-500">No completed deliveries yet.</p>
        <?php endif; ?>
    </section>
    </div>
</main>

<script>
const menuToggle = document.getElementById('menuToggle');
const sidebar = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');

function toggleSidebar() {
    if(sidebar) sidebar.classList.toggle('-translate-x-full');
    if(sidebarOverlay) sidebarOverlay.classList.toggle('hidden');
    document.body.classList.toggle('overflow-hidden');
}

if(menuToggle) menuToggle.addEventListener('click', toggleSidebar);
if(sidebarOverlay) sidebarOverlay.addEventListener('click', toggleSidebar);

if(sidebar) {
    const sidebarLinks = sidebar.querySelectorAll('a');
    sidebarLinks.forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth < 1024) {
                toggleSidebar();
            }
        });
    });
}

window.addEventListener('resize', () => {
    if (window.innerWidth >= 1024 && sidebar && !sidebar.classList.contains('-translate-x-full')) {
        sidebar.classList.remove('-translate-x-full');
        if(sidebarOverlay) sidebarOverlay.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }
});
</script>
</body>
</html>
