<?php session_start(); 
include_once 'includes/controller.php';

checkLogin();
$userId = $_SESSION['userAppId'];

// Get user stats
$user_stats = $db->query("SELECT 
    rating, 
    total_sales, 
    is_verified
    FROM users WHERE id = $userId")->fetch_assoc();

// Calculate profile completion percentage
$profile_fields = $db->query("SELECT 
    full_name, bio, phone, profile_image, university_id, department 
    FROM users WHERE id = $userId")->fetch_assoc();
$completed_fields = 0;
$total_fields = 6;
foreach($profile_fields as $field) {
    if (!empty($field)) $completed_fields++;
}
$profile_completion = round(($completed_fields / $total_fields) * 100);

// Get total sales amount
$total_sales_query = $db->query("SELECT COALESCE(SUM(total_amount), 0) as total_sales 
    FROM orders 
    WHERE seller_id = $userId AND status IN ('completed', 'delivered')");
$total_sales = $total_sales_query->fetch_assoc()['total_sales'];

// Get active listings count
$active_listings = $db->query("SELECT 
    (SELECT COUNT(*) FROM products WHERE user_id = $userId AND status = 'approved' AND availability = 'available') + 
    (SELECT COUNT(*) FROM services WHERE user_id = $userId AND status = 'active') as total")->fetch_assoc()['total'];

// Get pending items count
$pending_items = $db->query("SELECT COUNT(*) as total FROM products WHERE user_id = $userId AND status = 'pending'")->fetch_assoc()['total'];

// Get total listings (products + services) for welcome guide logic
$total_listings = $db->query("SELECT 
    (SELECT COUNT(*) FROM products WHERE user_id = $userId) + 
    (SELECT COUNT(*) FROM services WHERE user_id = $userId) as total")->fetch_assoc()['total'];

// Welcome guide logic - show if user has no listings OR profile is incomplete
$hide_welcome = isset($_GET['hide_welcome']) ? true : (isset($_SESSION['hide_welcome_guide']) ? $_SESSION['hide_welcome_guide'] : false);
$show_welcome_guide = !$hide_welcome && ($total_listings == 0 || $profile_completion < 80);
if (isset($_GET['hide_welcome'])) {
    $_SESSION['hide_welcome_guide'] = true;
    $show_welcome_guide = false;
}

// Get affiliate earnings
$affiliate_earnings = $db->query("SELECT COALESCE(SUM(amount), 0) as total 
    FROM affiliate_earnings 
    WHERE user_id = $userId AND status = 'paid'")->fetch_assoc()['total'];

// Get active guide videos
$guide_videos = $db->query("SELECT * FROM guide_videos WHERE is_active = 1 ORDER BY display_order ASC");

// Get recent activities
$recent_activities = $db->query("
    SELECT 'sale' as type, o.created_at, o.total_amount, p.title as item_name, u.username as other_user
    FROM orders o
    JOIN products p ON o.product_id = p.id
    JOIN users u ON o.buyer_id = u.id
    WHERE o.seller_id = $userId AND o.status IN ('completed', 'delivered')
    
    UNION ALL
    
    SELECT 'message' as type, m.created_at, 0 as total_amount, '' as item_name, u.username as other_user
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE m.receiver_id = $userId AND m.is_read = 0
    
    ORDER BY created_at DESC
    LIMIT 5
");

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <base href="<?php echo SITE_URL; ?>">
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>CampMart User Account Dashboard</title>
    <script src="tailwind34.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet" />
    <script id="tailwind-config">
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        "primary": "#f48c25", // Orange
                        "brand-green": "#064E3B", // Dark Green
                        "brand-green-light": "#F0FDF4", // Very light brand green
                        "background-main": "#F9FAFB", // Soft off-white/light gray
                        "surface-white": "#FFFFFF",
                        "text-dark": "#1F2937", // Deep charcoal gray
                    },
                    fontFamily: {
                        "display": ["Inter"]
                    },
                    borderRadius: {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "full": "9999px"
                    },
                },
            },
        }
    </script>
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        body {
            font-family: 'Inter', sans-serif;
            color: #1F2937;
        }
    </style>
</head>

<body class="bg-background-main min-h-screen text-text-dark">
<?php include_once 'includes/user-nav.php'; ?>
        <main class="flex-1 overflow-y-auto bg-background-main p-4 md:p-6 lg:p-8">
            <div class="max-w-6xl mx-auto space-y-6 md:space-y-8">

                <?php if (isset($accountStatus) && $accountStatus === 'suspended'): ?>
                <div class="rounded-xl border border-red-200 bg-red-50 p-5 md:p-6">
                    <div class="flex items-start gap-4">
                        <div class="flex items-center justify-center size-12 rounded-xl bg-red-100 text-red-600 shrink-0">
                            <span class="material-symbols-outlined text-3xl">block</span>
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-red-800">Account Suspended</h2>
                            <p class="text-sm text-red-700 mt-1">Your account has been suspended by an administrator. You cannot create listings, place orders, send messages, or perform any other actions until your suspension is lifted.</p>
                            <p class="text-sm text-red-600 mt-2">If you believe this is a mistake, please contact support for assistance.</p>
                        </div>
                    </div>
                </div>
                <?php elseif (isset($accountStatus) && $accountStatus === 'banned'): ?>
                <div class="rounded-xl border border-red-300 bg-red-100 p-5 md:p-6">
                    <div class="flex items-start gap-4">
                        <div class="flex items-center justify-center size-12 rounded-xl bg-red-200 text-red-700 shrink-0">
                            <span class="material-symbols-outlined text-3xl">gpp_bad</span>
                        </div>
                        <div>
                            <h2 class="text-lg font-bold text-red-900">Account Banned</h2>
                            <p class="text-sm text-red-800 mt-1">Your account has been permanently banned. You will not be able to access any features on this platform.</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-brand-green">Dashboard Overview</h1>
                    <p class="text-slate-500 mt-1">Check your performance and manage your campus activity.</p>
                </div>
                <a href="index.php" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-slate-200 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50">
                    <span class="material-symbols-outlined text-base">home</span>
                    Back to site
                </a>
                </div>

                <?php if ($show_welcome_guide): ?>
                <div class="relative overflow-hidden rounded-2xl border border-primary/20 bg-gradient-to-br from-orange-50 via-white to-emerald-50 p-5 md:p-6 shadow-sm">
                    <button onclick="window.location.href='?hide_welcome=1'" class="absolute top-3 right-3 p-1 rounded-full text-slate-400 hover:text-slate-600 hover:bg-white/60 transition-colors" title="Dismiss">
                        <span class="material-symbols-outlined text-xl">close</span>
                    </button>
                    <div class="flex items-start gap-4 mb-4">
                        <div class="flex items-center justify-center size-12 rounded-2xl bg-primary/10 text-primary shrink-0">
                            <span class="material-symbols-outlined text-3xl">celebration</span>
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-brand-green">Welcome to CampMart!</h2>
                            <p class="text-slate-600 text-sm mt-0.5">Get started in 3 quick steps. Set up your profile, make your first post, and watch our tutorials.</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <a href="my-profile.php" class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 hover:border-primary hover:shadow-sm transition-all group">
                            <span class="flex items-center justify-center size-10 rounded-lg bg-primary/10 text-primary group-hover:bg-primary group-hover:text-white transition-colors shrink-0">
                                <span class="material-symbols-outlined text-xl">person</span>
                            </span>
                            <div>
                                <p class="text-sm font-bold text-text-dark">Complete Profile</p>
                                <p class="text-xs text-slate-500">Add your photo, bio, and campus info.</p>
                            </div>
                            <span class="material-symbols-outlined text-slate-300 ml-auto">chevron_right</span>
                        </a>
                        <a href="my-products.php" class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 hover:border-primary hover:shadow-sm transition-all group">
                            <span class="flex items-center justify-center size-10 rounded-lg bg-primary/10 text-primary group-hover:bg-primary group-hover:text-white transition-colors shrink-0">
                                <span class="material-symbols-outlined text-xl">add_circle</span>
                            </span>
                            <div>
                                <p class="text-sm font-bold text-text-dark">Make a Post</p>
                                <p class="text-xs text-slate-500">Sell a product or offer a service.</p>
                            </div>
                            <span class="material-symbols-outlined text-slate-300 ml-auto">chevron_right</span>
                        </a>
                        <a href="#guide-videos" onclick="document.querySelector('#guide-videos').scrollIntoView({behavior:'smooth'}); return false;" class="flex items-center gap-3 rounded-xl border border-slate-200 bg-white p-4 hover:border-primary hover:shadow-sm transition-all group">
                            <span class="flex items-center justify-center size-10 rounded-lg bg-primary/10 text-primary group-hover:bg-primary group-hover:text-white transition-colors shrink-0">
                                <span class="material-symbols-outlined text-xl">play_circle</span>
                            </span>
                            <div>
                                <p class="text-sm font-bold text-text-dark">Watch Tutorials</p>
                                <p class="text-xs text-slate-500">Screen recordings right on this page.</p>
                            </div>
                            <span class="material-symbols-outlined text-slate-300 ml-auto">chevron_right</span>
                        </a>
                    </div>
                </div>
                <?php endif; ?>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4">
                    <div class="flex flex-col gap-2 rounded-xl p-4 md:p-6 bg-surface-white border border-slate-200 shadow-sm">
                        <div class="flex items-center justify-between">
                            <p class="text-slate-500 text-sm font-medium">Total Sales</p>
                            <span class="material-symbols-outlined text-brand-green/30">payments</span>
                        </div>
                        <p class="text-text-dark text-2xl font-bold tracking-tight">₦<?php echo number_format($total_sales, 2); ?></p>
                        <div class="flex items-center gap-1 text-emerald-600 text-xs font-bold">
                            <span class="material-symbols-outlined text-xs">trending_up</span>
                            <span>From completed orders</span>
                        </div>
                    </div>
                    <div class="flex flex-col gap-2 rounded-xl p-6 bg-surface-white border border-slate-200 shadow-sm">
                        <div class="flex items-center justify-between">
                            <p class="text-slate-500 text-sm font-medium">Active Listings</p>
                            <span class="material-symbols-outlined text-brand-green/30">list_alt</span>
                        </div>
                        <p class="text-text-dark text-2xl font-bold tracking-tight"><?php echo $active_listings; ?></p>
                        <p class="text-slate-400 text-xs font-normal mt-1"><?php echo $pending_items; ?> items pending review</p>
                    </div>
                    <div class="flex flex-col gap-2 rounded-xl p-6 bg-surface-white border border-slate-200 shadow-sm">
                        <div class="flex items-center justify-between">
                            <p class="text-slate-500 text-sm font-medium">Affiliate Earnings</p>
                            <span class="material-symbols-outlined text-brand-green/30">account_balance_wallet</span>
                        </div>
                        <p class="text-text-dark text-2xl font-bold tracking-tight">₦<?php echo number_format($affiliate_earnings, 2); ?></p>
                        <div class="flex items-center gap-1 text-emerald-600 text-xs font-bold">
                            <span class="material-symbols-outlined text-xs">account_balance</span>
                            <span>Total earned</span>
                        </div>
                    </div>
                    <div class="flex flex-col gap-2 rounded-xl p-6 bg-surface-white border border-slate-200 shadow-sm">
                        <div class="flex items-center justify-between">
                            <p class="text-slate-500 text-sm font-medium">Account Rating</p>
                            <span class="material-symbols-outlined text-primary">star</span>
                        </div>
                        <p class="text-text-dark text-2xl font-bold tracking-tight"><?php echo number_format($user_stats['rating'], 1); ?>/5</p>
                        <p class="text-slate-400 text-xs font-normal mt-1">Based on <?php echo $user_stats['total_sales']; ?> transactions</p>
                    </div>
                </div>

                <div id="guide-videos" class="rounded-2xl border border-primary/20 bg-gradient-to-br from-orange-50 via-white to-emerald-50 p-5 md:p-6 shadow-sm">
                    <div class="flex items-center gap-3 mb-5">
                        <span class="flex items-center justify-center size-12 rounded-xl bg-primary/10 text-primary">
                            <span class="material-symbols-outlined text-3xl">play_circle</span>
                        </span>
                        <div>
                            <h2 class="text-xl font-bold text-brand-green">Guide Videos</h2>
                            <p class="text-sm text-slate-500">Watch quick tutorials to learn how to use CampMart.</p>
                        </div>
                    </div>
                    <?php if ($guide_videos && $guide_videos->num_rows > 0): ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 md:gap-6">
                        <?php while($video = $guide_videos->fetch_assoc()): 
                            $videoId = getYouTubeVideoId($video['video_url']);
                            $iconColors = [
                                'primary' => 'bg-primary/10 text-primary',
                                'brand-green' => 'bg-brand-green/10 text-brand-green',
                                'blue-600' => 'bg-blue-100 text-blue-600',
                                'purple-600' => 'bg-purple-100 text-purple-600',
                                'pink-600' => 'bg-pink-100 text-pink-600',
                                'red-600' => 'bg-red-100 text-red-600',
                                'emerald-600' => 'bg-emerald-100 text-emerald-600',
                            ];
                            $colorClass = $iconColors[$video['icon_color']] ?? 'bg-primary/10 text-primary';
                        ?>
                        <div class="rounded-xl border border-slate-200 bg-surface-white overflow-hidden shadow-sm hover:shadow-lg transition-all group">
                            <div class="relative aspect-video bg-slate-100 overflow-hidden">
                                <?php if ($videoId): ?>
                                <iframe class="w-full h-full" src="https://www.youtube.com/embed/<?php echo htmlspecialchars($videoId); ?>" title="<?php echo htmlspecialchars($video['title']); ?>" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                                <?php else: ?>
                                <div class="flex items-center justify-center h-full">
                                    <span class="material-symbols-outlined text-4xl text-slate-300">videocam</span>
                                </div>
                                <?php endif; ?>
                            </div>
                            <div class="p-4 md:p-5">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="flex items-center justify-center size-8 rounded-lg <?php echo $colorClass; ?>">
                                        <span class="material-symbols-outlined text-lg"><?php echo htmlspecialchars($video['icon']); ?></span>
                                    </span>
                                    <h3 class="font-bold text-text-dark"><?php echo htmlspecialchars($video['title']); ?></h3>
                                </div>
                                <?php if (!empty($video['description'])): ?>
                                <p class="text-sm text-slate-500"><?php echo htmlspecialchars($video['description']); ?></p>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-8">
                        <span class="material-symbols-outlined text-4xl text-slate-300 mb-2">videocam_off</span>
                        <p class="text-slate-400 text-sm">No tutorial videos available yet.</p>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <div class="lg:col-span-2 space-y-6 md:space-y-8">
                        <div>
                            <h2 class="text-lg md:text-xl font-bold text-brand-green mb-4">Quick Actions</h2>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 md:gap-4">
                                <a href="my-products.php" class="group flex items-start gap-4 rounded-xl border border-slate-200 bg-surface-white p-5 cursor-pointer hover:border-primary hover:shadow-md transition-all">
                                    <div class="flex items-center justify-center size-12 rounded-lg bg-primary/10 text-primary group-hover:bg-primary group-hover:text-white transition-colors">
                                        <span class="material-symbols-outlined text-[28px]">add_circle</span>
                                    </div>
                                    <div class="flex flex-col">
                                        <h3 class="text-text-dark font-bold">Post Sale</h3>
                                        <p class="text-slate-500 text-sm">Sell textbooks, dorm gear, or electronics.</p>
                                    </div>
                                </a>
                                <a href="my-services.php" class="group flex items-start gap-4 rounded-xl border border-slate-200 bg-surface-white p-5 cursor-pointer hover:border-primary hover:shadow-md transition-all">
                                    <div class="flex items-center justify-center size-12 rounded-lg bg-primary/10 text-primary group-hover:bg-primary group-hover:text-white transition-colors">
                                        <span class="material-symbols-outlined text-[28px]">work</span>
                                    </div>
                                    <div class="flex flex-col">
                                        <h3 class="text-text-dark font-bold">Post Service</h3>
                                        <p class="text-slate-500 text-sm">Offer tutoring, moving, or editing help.</p>
                                    </div>
                                </a>
                                <a href="my-lost-found.php" class="group flex items-start gap-4 rounded-xl border border-slate-200 bg-surface-white p-5 cursor-pointer hover:border-primary hover:shadow-md transition-all">
                                    <div class="flex items-center justify-center size-12 rounded-lg bg-primary/10 text-primary group-hover:bg-primary group-hover:text-white transition-colors">
                                        <span class="material-symbols-outlined text-[28px]">search_check</span>
                                    </div>
                                    <div class="flex flex-col">
                                        <h3 class="text-text-dark font-bold">Post Lost/Found</h3>
                                        <p class="text-slate-500 text-sm">Help return an item to its owner.</p>
                                    </div>
                                </a>
                                <a href="my-ads.php" class="group flex items-start gap-4 rounded-xl border border-slate-200 bg-surface-white p-5 cursor-pointer hover:border-primary hover:shadow-md transition-all">
                                    <div class="flex items-center justify-center size-12 rounded-lg bg-primary/10 text-primary group-hover:bg-primary group-hover:text-white transition-colors">
                                        <span class="material-symbols-outlined text-[28px]">campaign</span>
                                    </div>
                                    <div class="flex flex-col">
                                        <h3 class="text-text-dark font-bold">Create Ad</h3>
                                        <p class="text-slate-500 text-sm">Boost visibility for your listings.</p>
                                    </div>
                                </a>
                            </div>
                        </div>
                        <div>
                            <div class="flex items-center justify-between mb-4">
                                <h2 class="text-lg md:text-xl font-bold text-brand-green">Recent Activity</h2>
                                <button class="text-primary text-sm font-semibold hover:underline">View All</button>
                            </div>
                            <div class="rounded-xl border border-slate-200 bg-surface-white shadow-sm overflow-hidden">
                                <div class="divide-y divide-slate-100">
                                    <?php if ($recent_activities && $recent_activities->num_rows > 0):
                                        while($activity = $recent_activities->fetch_assoc()): 
                                            if ($activity['type'] == 'sale'): ?>
                                    <div class="flex items-center gap-4 p-4 hover:bg-slate-50 transition-colors">
                                        <div class="size-10 rounded-full bg-emerald-100 text-emerald-600 flex items-center justify-center shrink-0">
                                            <span class="material-symbols-outlined text-[20px]">check_circle</span>
                                        </div>
                                        <div class="flex-1">
                                            <p class="text-sm font-medium text-text-dark">Sold: <span class="text-brand-green font-bold"><?php echo htmlspecialchars($activity['item_name']); ?></span> to <?php echo htmlspecialchars($activity['other_user']); ?></p>
                                            <p class="text-xs text-slate-400"><?php echo date('M d, g:i A', strtotime($activity['created_at'])); ?></p>
                                        </div>
                                        <p class="text-sm font-bold text-emerald-600">+₦<?php echo number_format($activity['total_amount'], 2); ?></p>
                                    </div>
                                    <?php elseif ($activity['type'] == 'message'): ?>
                                    <div class="flex items-center gap-4 p-4 hover:bg-slate-50 transition-colors">
                                        <div class="size-10 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center shrink-0">
                                            <span class="material-symbols-outlined text-[20px]">chat</span>
                                        </div>
                                        <div class="flex-1">
                                            <p class="text-sm font-medium text-text-dark">New message from <span class="text-brand-green font-bold"><?php echo htmlspecialchars($activity['other_user']); ?></span></p>
                                            <p class="text-xs text-slate-400"><?php echo date('M d, g:i A', strtotime($activity['created_at'])); ?></p>
                                        </div>
                                        <span class="material-symbols-outlined text-slate-300">chevron_right</span>
                                    </div>
                                    <?php endif; endwhile; else: ?>
                                    <div class="flex items-center justify-center p-8 text-slate-400">
                                        <p class="text-sm">No recent activity</p>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="space-y-6">
                        <div class="rounded-xl p-6 bg-brand-green text-white shadow-lg shadow-brand-green/10">
                            <h3 class="text-lg font-bold mb-2">Seller Tips</h3>
                            <p class="text-white/80 text-sm leading-relaxed mb-4">
                                Listings with high-quality photos sell 40% faster on CampMart.
                            </p>
                            <button class="w-full py-2 bg-primary hover:bg-primary/90 rounded-lg text-white text-sm font-bold transition-colors shadow-sm">
                                Improve Listings
                            </button>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-surface-white p-6 shadow-sm">
                            <h3 class="text-brand-green font-bold mb-4">Account Health</h3>
                            <div class="space-y-4">
                                <div>
                                    <div class="flex justify-between text-xs mb-1.5">
                                        <span class="text-slate-500">Response Rate</span>
                                        <span class="font-bold text-brand-green">98%</span>
                                    </div>
                                    <div class="h-2 w-full bg-slate-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-brand-green" style="width: 98%"></div>
                                    </div>
                                </div>
                                <div>
                                    <div class="flex justify-between text-xs mb-1.5">
                                        <span class="text-slate-500">Profile Completion</span>
                                        <span class="font-bold text-brand-green"><?php echo $profile_completion; ?>%</span>
                                    </div>
                                    <div class="h-2 w-full bg-slate-100 rounded-full overflow-hidden">
                                        <div class="h-full bg-primary" style="width: <?php echo $profile_completion; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="rounded-xl border border-slate-200 bg-surface-white p-6 shadow-sm">
                            <h3 class="text-brand-green font-bold mb-4">Active Promotions</h3>
                            <div class="flex items-center gap-4 bg-orange-50 p-3 rounded-lg border border-orange-100">
                                <span class="material-symbols-outlined text-primary">local_fire_department</span>
                                <div>
                                    <p class="text-xs font-bold text-text-dark">Double Earnings Week</p>
                                    <p class="text-[10px] text-slate-500">2x points on book sales</p>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Mobile menu toggle
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        function toggleSidebar() {
            sidebar.classList.toggle('-translate-x-full');
            sidebarOverlay.classList.toggle('hidden');
            document.body.classList.toggle('overflow-hidden');
        }

        menuToggle.addEventListener('click', toggleSidebar);
        sidebarOverlay.addEventListener('click', toggleSidebar);

        // Close sidebar when clicking a link on mobile
        const sidebarLinks = sidebar.querySelectorAll('a');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 1024) {
                    toggleSidebar();
                }
            });
        });

        // Close sidebar on window resize if open on mobile
        window.addEventListener('resize', () => {
            if (window.innerWidth >= 1024 && !sidebar.classList.contains('-translate-x-full')) {
                sidebar.classList.remove('-translate-x-full');
                sidebarOverlay.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }
        });
    </script>
</body>

</html>