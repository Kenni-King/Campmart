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

$availableCount = 0;
$rAvailable = $db->query("
    SELECT COUNT(*) as cnt FROM orders o
    WHERE o.delivery_option = 'riders' AND o.status NOT IN ('cancelled','refunded')
    AND NOT EXISTS (
        SELECT 1 FROM delivery_tasks dt 
        WHERE dt.order_id = o.id AND dt.status IN ('assigned','picked_up','delivered','completed')
    )
");
if ($rAvailable) $availableCount = $rAvailable->fetch_assoc()['cnt'];

$activeTasks = 0;
$rActive = $db->query("
    SELECT COUNT(*) as cnt FROM delivery_tasks 
    WHERE rider_id = $userId AND status IN ('interested','assigned','picked_up')
");
if ($rActive) $activeTasks = $rActive->fetch_assoc()['cnt'];

$completedTasks = 0;
$rComp = $db->query("
    SELECT COUNT(*) as cnt FROM delivery_tasks 
    WHERE rider_id = $userId AND status IN ('delivered','completed')
");
if ($rComp) $completedTasks = $rComp->fetch_assoc()['cnt'];

$recentTasks = $db->query("
    SELECT dt.*, o.order_number, o.delivery_location, o.total_amount,
           p.title as product_title, p.slug as product_slug,
           u.full_name as seller_name
    FROM delivery_tasks dt
    JOIN orders o ON dt.order_id = o.id
    JOIN products p ON o.product_id = p.id
    JOIN users u ON o.seller_id = u.id
    WHERE dt.rider_id = $userId
    ORDER BY dt.updated_at DESC LIMIT 10
");
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<base href="<?php echo SITE_URL; ?>rider/">
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Rider Dashboard | CampMart</title>
<script src="../tailwind34.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
<script>
tailwind.config = {
    darkMode: "class",
    theme: {
        extend: {
            colors: {
                primary: "#064E3B",
                secondary: "#F97316",
                accent: "#BEF264",
                surface: "#F8FAFC",
                "brand-green": "#064E3B",
                "brand-green-light": "#F0FDF4",
                "background-main": "#F9FAFB",
                "surface-white": "#FFFFFF",
                "text-dark": "#1F2937",
            },
            fontFamily: {
                display: ["Plus Jakarta Sans", "sans-serif"]
            }
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
        <span class="inline-flex items-center gap-2 rounded-full bg-orange-100 px-3 py-1 text-xs font-semibold text-orange-700 mb-3">
            <span class="material-symbols-outlined text-sm">motorcycle</span>
            Platform Rider
        </span>
        <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-primary">Rider Dashboard</h1>
        <p class="text-sm text-slate-500 mt-1">Manage your delivery tasks and find new orders to deliver.</p>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <section class="grid grid-cols-3 gap-4 mb-8">
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs uppercase tracking-wider text-slate-400 font-bold">Available</p>
            <p class="mt-2 text-3xl font-extrabold text-orange-600"><?php echo $availableCount; ?></p>
            <p class="text-xs text-slate-500 mt-1">orders need a rider</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs uppercase tracking-wider text-slate-400 font-bold">Active</p>
            <p class="mt-2 text-3xl font-extrabold text-blue-600"><?php echo $activeTasks; ?></p>
            <p class="text-xs text-slate-500 mt-1">tasks in progress</p>
        </div>
        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs uppercase tracking-wider text-slate-400 font-bold">Completed</p>
            <p class="mt-2 text-3xl font-extrabold text-emerald-600"><?php echo $completedTasks; ?></p>
            <p class="text-xs text-slate-500 mt-1">deliveries done</p>
        </div>
    </section>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8">
        <a href="available-orders.php" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm hover:border-orange-300 hover:shadow-md transition-all group">
            <div class="flex items-center gap-4">
                <div class="size-12 rounded-xl bg-orange-100 flex items-center justify-center group-hover:bg-orange-200 transition-colors">
                    <span class="material-symbols-outlined text-orange-600">explore</span>
                </div>
                <div>
                    <h3 class="font-bold text-slate-900">Available Orders</h3>
                    <p class="text-sm text-slate-500">Browse orders needing a rider</p>
                </div>
                <span class="material-symbols-outlined ml-auto text-slate-300 group-hover:text-orange-500">arrow_forward</span>
            </div>
        </a>
        <a href="my-tasks.php" class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm hover:border-blue-300 hover:shadow-md transition-all group">
            <div class="flex items-center gap-4">
                <div class="size-12 rounded-xl bg-blue-100 flex items-center justify-center group-hover:bg-blue-200 transition-colors">
                    <span class="material-symbols-outlined text-blue-600">assignment</span>
                </div>
                <div>
                    <h3 class="font-bold text-slate-900">My Deliveries</h3>
                    <p class="text-sm text-slate-500">View your current and past tasks</p>
                </div>
                <span class="material-symbols-outlined ml-auto text-slate-300 group-hover:text-blue-500">arrow_forward</span>
            </div>
        </a>
    </div>

    <?php if ($recentTasks && $recentTasks->num_rows > 0): ?>
    <section>
        <h2 class="text-lg font-bold text-slate-900 mb-4">Recent Activity</h2>
        <div class="space-y-3">
            <?php while ($task = $recentTasks->fetch_assoc()): ?>
                <?php
                $statusColors = [
                    'interested' => 'bg-amber-50 text-amber-700 border-amber-200',
                    'assigned' => 'bg-blue-50 text-blue-700 border-blue-200',
                    'picked_up' => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                    'delivered' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                    'completed' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                    'cancelled' => 'bg-red-50 text-red-700 border-red-200',
                ];
                $sc = $statusColors[$task['status']] ?? 'bg-slate-100 text-slate-700 border-slate-200';
                ?>
                <div class="rounded-2xl border border-slate-200 bg-white p-4 flex items-center justify-between gap-4">
                    <div class="flex items-center gap-3 min-w-0">
                        <span class="size-2 rounded-full shrink-0 <?php echo $task['status'] === 'interested' ? 'bg-amber-400' : ($task['status'] === 'assigned' ? 'bg-blue-400' : ($task['status'] === 'completed' ? 'bg-emerald-400' : 'bg-slate-300')); ?>"></span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-900 truncate"><?php echo htmlspecialchars($task['product_title']); ?></p>
                            <p class="text-xs text-slate-500">#<?php echo htmlspecialchars($task['order_number']); ?> &middot; <?php echo htmlspecialchars($task['seller_name']); ?></p>
                        </div>
                    </div>
                    <span class="shrink-0 rounded-full border px-3 py-1 text-xs font-semibold <?php echo $sc; ?>"><?php echo ucfirst($task['status']); ?></span>
                </div>
            <?php endwhile; ?>
        </div>
    </section>
    <?php endif; ?>
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
