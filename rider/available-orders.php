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

$orders = $db->query("
    SELECT o.*, p.title as product_title, p.slug as product_slug,
           p.metadata, p.price, u.full_name as seller_name, u.phone as seller_phone,
           (SELECT COUNT(*) FROM delivery_tasks WHERE order_id = o.id AND status = 'interested') as interest_count
    FROM orders o
    JOIN products p ON o.product_id = p.id
    JOIN users u ON o.seller_id = u.id
    WHERE o.delivery_option = 'riders'
      AND o.status NOT IN ('cancelled', 'refunded', 'completed')
      AND NOT EXISTS (
          SELECT 1 FROM delivery_tasks dt 
          WHERE dt.order_id = o.id AND dt.status IN ('assigned','picked_up','delivered','completed')
      )
      AND NOT EXISTS (
          SELECT 1 FROM delivery_tasks dt 
          WHERE dt.order_id = o.id AND dt.rider_id = $userId AND dt.status = 'interested'
      )
    ORDER BY o.created_at DESC
");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['signal_interest'])) {
    $orderId = (int) ($_POST['order_id'] ?? 0);
    $check = $db->query("
        SELECT 1 FROM orders o
        WHERE o.id = $orderId AND o.delivery_option = 'riders'
          AND o.status NOT IN ('cancelled','refunded','completed')
          AND NOT EXISTS (
              SELECT 1 FROM delivery_tasks dt 
              WHERE dt.order_id = o.id AND dt.status IN ('assigned','picked_up','delivered','completed')
          )
          AND NOT EXISTS (
              SELECT 1 FROM delivery_tasks dt 
              WHERE dt.order_id = o.id AND dt.rider_id = $userId AND dt.status = 'interested'
          )
    ");
    if ($check && $check->num_rows) {
        $db->query("INSERT INTO delivery_tasks (order_id, rider_id, status) VALUES ($orderId, $userId, 'interested')");
        $sellerId = $db->query("SELECT seller_id FROM orders WHERE id = $orderId")->fetch_assoc()['seller_id'];
        $db->query("INSERT INTO notifications (user_id, title, message, type, related_id, related_type, created_at) 
                    VALUES ($sellerId, 'Rider Available', 'A rider is interested in delivering your order #" . (int)$orderId . "', 'system', $orderId, 'order', NOW())");
        $_SESSION['success'] = 'You have signaled your availability! The seller will be notified.';
    } else {
        $_SESSION['error'] = 'This order is no longer available.';
    }
    header('Location: available-orders.php');
    exit;
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<base href="<?php echo SITE_URL; ?>rider/">
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>Available Orders | Rider | CampMart</title>
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
        <h1 class="text-2xl font-extrabold text-primary">Available Orders</h1>
        <p class="text-sm text-slate-500 mt-1">Orders that need a rider for delivery. Signal your interest to the seller.</p>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <?php if ($orders && $orders->num_rows > 0): ?>
        <div class="space-y-4">
            <?php while ($order = $orders->fetch_assoc()): ?>
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">#<?php echo htmlspecialchars($order['order_number']); ?></span>
                                <span class="rounded-full bg-orange-50 border border-orange-200 px-3 py-1 text-xs font-semibold text-orange-700"><?php echo (int)$order['interest_count']; ?> interested</span>
                            </div>
                            <h3 class="text-lg font-bold text-slate-900"><?php echo htmlspecialchars($order['product_title']); ?></h3>
                            <div class="mt-2 grid grid-cols-2 gap-3 text-sm text-slate-600">
                                <div>
                                    <p class="text-xs text-slate-400 font-semibold uppercase tracking-wide">Seller</p>
                                    <p class="font-medium"><?php echo htmlspecialchars($order['seller_name']); ?></p>
                                </div>
                                <div>
                                    <p class="text-xs text-slate-400 font-semibold uppercase tracking-wide">Delivery Location</p>
                                    <p class="font-medium"><?php echo htmlspecialchars($order['delivery_location']); ?></p>
                                </div>
                                <div>
                                    <p class="text-xs text-slate-400 font-semibold uppercase tracking-wide">Amount</p>
                                    <p class="font-bold text-primary"><?php echo formatCurrency((float)$order['total_amount']); ?></p>
                                </div>
                                <div>
                                    <p class="text-xs text-slate-400 font-semibold uppercase tracking-wide">Placed</p>
                                    <p><?php echo date('M j, Y', strtotime($order['created_at'])); ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="shrink-0">
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">
                                <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
                                <button type="submit" name="signal_interest" class="inline-flex items-center gap-2 rounded-2xl bg-orange-600 px-5 py-3 text-sm font-bold text-white hover:bg-orange-700 transition-all">
                                    <span class="material-symbols-outlined text-base">hand_gesture</span>
                                    Signal Availability
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center">
            <span class="material-symbols-outlined text-5xl text-slate-300">motorcycle</span>
            <h3 class="mt-4 text-lg font-bold text-slate-900">No orders available</h3>
            <p class="mt-2 text-sm text-slate-500">All orders requiring a rider have been claimed. Check back soon!</p>
        </div>
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
