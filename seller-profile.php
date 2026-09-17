<?php
session_start();
require_once 'includes/controller.php';

$username = $_GET['username'] ?? '';

if (empty($username)) {
    header('Location: services.php');
    exit;
}

$sellerId = tableRowItem('users', ['username' => $username], 'id');

$seller_query = "
    SELECT u.*,
           COUNT(DISTINCT s.id) as total_services,
           COUNT(DISTINCT p.id) as total_products
    FROM users u
    LEFT JOIN services s ON u.id = s.user_id AND s.status = 'active'
    LEFT JOIN products p ON u.id = p.user_id AND p.status = 'approved'
    WHERE u.id = ?
    LIMIT 1
";

$stmt = $db->prepare($seller_query);
$stmt->bind_param("i", $sellerId);
$stmt->execute();
$result = $stmt->get_result();
$seller = $result->fetch_assoc();

if (!$seller) {
    header('Location: index.php');
    exit;
}

// Get seller's services
$services_query = "
    SELECT s.*, sc.name as category_name, sc.icon as category_icon
    FROM services s
    LEFT JOIN service_categories sc ON s.service_category_id = sc.id
    WHERE s.user_id = ? AND s.status = 'active'
    ORDER BY s.is_featured DESC, s.total_orders DESC
    LIMIT 12
";
$stmt = $db->prepare($services_query);
$stmt->bind_param("i", $sellerId);
$stmt->execute();
$services_result = $stmt->get_result();

// Get seller's products
$products_query = "
    SELECT p.id, p.title, p.slug, p.description, p.price, p.is_featured,
           c.name as category_name,
           pi.image_url
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
    WHERE p.user_id = ? AND p.status = 'approved' AND p.availability = 'available'
    ORDER BY p.is_featured DESC, p.created_at DESC
    LIMIT 12
";
$stmt = $db->prepare($products_query);
$stmt->bind_param("i", $sellerId);
$stmt->execute();
$products_result = $stmt->get_result();

$page_title = htmlspecialchars($seller['full_name'] ?: $seller['username']) . ' - Seller Profile | CampMart';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8"/>
    <base href="<?php echo SITE_URL; ?>">
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title><?php echo $page_title; ?></title>
    <script src="tailwind34.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        "primary": "#f48c25",
                        "secondary": "#FF6B35",
                        "brand-green": "#064E3B",
                        "background-main": "#F9FAFB",
                    },
                    fontFamily: {
                        "display": ["Inter"]
                    },
                },
            },
        }
    </script>
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
    </style>
</head>
<body class="bg-background-main font-display">
    <?php include_once 'includes/header.php'; ?>

    <main class="max-w-[920px] mx-auto px-4 sm:px-6 py-6 sm:py-10">

        <?php if ($seller['status'] === 'suspended'): ?>
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-5 mb-6 flex items-start gap-4">
            <div class="flex items-center justify-center size-12 rounded-xl bg-rose-100 text-rose-600 shrink-0">
                <span class="material-symbols-outlined text-3xl">pause_circle</span>
            </div>
            <div>
                <h2 class="text-lg font-bold text-rose-800">Account Suspended</h2>
                <p class="text-sm text-rose-700 mt-1">This seller's account has been suspended by an administrator. Their listings are temporarily unavailable.</p>
            </div>
        </div>
        <?php elseif ($seller['status'] === 'banned'): ?>
        <div class="rounded-2xl border border-red-300 bg-red-50 p-5 mb-6 flex items-start gap-4">
            <div class="flex items-center justify-center size-12 rounded-xl bg-red-100 text-red-600 shrink-0">
                <span class="material-symbols-outlined text-3xl">block</span>
            </div>
            <div>
                <h2 class="text-lg font-bold text-red-800">Account Banned</h2>
                <p class="text-sm text-red-700 mt-1">This seller's account has been permanently banned from the platform. Their listings are no longer available.</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Profile Card -->
        <section class="bg-white rounded-2xl border border-slate-200 shadow-sm overflow-hidden mb-8">
            <!-- Cover -->
            <div class="h-32 sm:h-44 bg-gradient-to-br from-primary via-primary/80 to-brand-green"></div>

            <!-- Profile Body -->
            <div class="px-5 sm:px-8 pb-8">
                <div class="flex flex-col items-center text-center -mt-14 sm:-mt-16">

                    <!-- Avatar -->
                    <div class="size-28 sm:size-32 rounded-full overflow-hidden border-4 border-white shadow-xl bg-white mb-4">
                        <?php if (!empty($seller['profile_image'])): ?>
                            <img alt="<?php echo htmlspecialchars($seller['full_name'] ?: $seller['username']); ?>"
                                 class="w-full h-full object-cover"
                                 src="<?php echo htmlspecialchars($seller['profile_image']); ?>"/>
                        <?php else: ?>
                            <div class="w-full h-full bg-gradient-to-br from-primary to-brand-green text-white flex items-center justify-center text-5xl font-bold">
                                <?php echo strtoupper(substr($seller['username'], 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Name + Verified -->
                    <div class="flex items-center gap-2 mb-1">
                        <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900">
                            <?php echo htmlspecialchars($seller['full_name'] ?: $seller['username']); ?>
                        </h1>
                        <?php if ($seller['is_verified']): ?>
                            <span class="material-symbols-outlined text-blue-500 text-2xl fill-1" title="Verified Seller">verified</span>
                        <?php endif; ?>
                    </div>

                    <!-- Username + Share -->
                    <p class="text-slate-500 text-sm mb-3 flex items-center gap-2">
                        @<?php echo htmlspecialchars($seller['username']); ?>
                        <span onclick="shareProfile()" class="cursor-pointer inline-flex items-center justify-center size-7 rounded-full bg-slate-100 text-slate-500 hover:bg-slate-200 hover:text-slate-700 transition">
                            <span class="material-symbols-outlined text-sm">share</span>
                        </span>
                    </p>

                    <!-- Bio -->
                    <?php if (!empty($seller['bio'])): ?>
                        <p class="text-slate-600 text-sm sm:text-base max-w-lg leading-relaxed mb-4">
                            <?php echo nl2br(htmlspecialchars($seller['bio'])); ?>
                        </p>
                    <?php endif; ?>

                    <!-- Rating -->
                    <?php if (!empty($seller['rating'])): ?>
                        <div class="inline-flex items-center gap-1.5 bg-amber-50 border border-amber-200 text-amber-700 rounded-full px-3 py-1.5 text-xs font-bold mb-5">
                            <span class="material-symbols-outlined text-sm fill-1">star</span>
                            <?php echo number_format($seller['rating'], 1); ?> rating
                            <span class="text-amber-300">&middot;</span>
                            <?php echo number_format($seller['total_sales']); ?> sales
                        </div>
                    <?php endif; ?>

                    <!-- Stats Row -->
                    <div class="flex items-center justify-center gap-6 sm:gap-10 mb-5">
                        <div class="text-center">
                            <p class="text-xl sm:text-2xl font-extrabold text-slate-900"><?php echo (int)$seller['total_products']; ?></p>
                            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Products</p>
                        </div>
                        <div class="w-px h-8 bg-slate-200"></div>
                        <div class="text-center">
                            <p class="text-xl sm:text-2xl font-extrabold text-slate-900"><?php echo (int)$seller['total_services']; ?></p>
                            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Services</p>
                        </div>
                        <div class="w-px h-8 bg-slate-200"></div>
                        <div class="text-center">
                            <p class="text-xl sm:text-2xl font-extrabold text-slate-900"><?php echo number_format($seller['total_sales']); ?></p>
                            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Sales</p>
                        </div>
                        <div class="w-px h-8 bg-slate-200"></div>
                        <div class="text-center">
                            <p class="text-sm sm:text-base font-bold text-slate-900"><?php echo date('M Y', strtotime($seller['created_at'])); ?></p>
                            <p class="text-xs font-semibold text-slate-500 uppercase tracking-wide">Joined</p>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <?php if ($seller['status'] !== 'suspended' && $seller['status'] !== 'banned'): ?>
                    <div class="flex items-center gap-3">
                        <a href="<?php echo SITE_URL; ?>chat.php?seller_id=<?php echo $seller['id']; ?>"
                           class="inline-flex items-center gap-2 bg-primary text-white px-6 py-2.5 rounded-xl font-bold text-sm hover:bg-primary/90 transition">
                            <span class="material-symbols-outlined text-lg">chat</span>
                            Chat Seller
                        </a>
                        <a href="store.php?username=<?php echo htmlspecialchars($seller['username']); ?>"
                           class="inline-flex items-center gap-2 bg-white border border-slate-200 text-slate-700 px-6 py-2.5 rounded-xl font-bold text-sm hover:border-primary/50 hover:text-primary transition">
                            <span class="material-symbols-outlined text-lg">storefront</span>
                            Visit Store
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <?php if ($seller['status'] !== 'suspended' && $seller['status'] !== 'banned'): ?>
        <!-- Tabs -->
        <div class="mb-6">
            <div class="border-b border-slate-200">
                <nav class="flex gap-6 sm:gap-8" role="tablist">
                    <button onclick="switchTab('services')" id="tab-services"
                            class="tab-button active py-3 px-1 font-bold text-primary border-b-2 border-primary transition-colors text-sm sm:text-base">
                        Services (<?php echo (int)$seller['total_services']; ?>)
                    </button>
                    <button onclick="switchTab('products')" id="tab-products"
                            class="tab-button py-3 px-1 font-bold text-slate-500 border-b-2 border-transparent hover:text-slate-900 transition-colors text-sm sm:text-base">
                        Products (<?php echo (int)$seller['total_products']; ?>)
                    </button>
                </nav>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($seller['status'] !== 'suspended' && $seller['status'] !== 'banned'): ?>
        <!-- Services Tab -->
        <div id="content-services" class="tab-content">
            <?php if ($services_result && $services_result->num_rows > 0): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    <?php while($service = $services_result->fetch_assoc()):
                        $price_display = $service['pricing_type'] == 'hourly' ? '₦' . number_format($service['price']) . '/hr' : 'From ₦' . number_format($service['price']);
                        $portfolio_images = json_decode($service['portfolio_images'], true);
                        $first_image = !empty($portfolio_images) ? $portfolio_images[0] : null;
                    ?>
                    <a href="<?php echo serviceUrl($service['slug']); ?>"
                       class="bg-white rounded-xl border border-slate-200 overflow-hidden hover:shadow-lg transition-all block">
                        <div class="relative h-40 overflow-hidden bg-gradient-to-br from-primary/10 to-brand-green/10">
                            <?php if($first_image): ?>
                                <img src="<?= htmlspecialchars($first_image) ?>" alt="<?= htmlspecialchars($service['title']) ?>" class="w-full h-full object-cover" />
                            <?php else: ?>
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <span class="material-symbols-outlined text-6xl text-primary/20">
                                        <?php echo htmlspecialchars($service['category_icon'] ?: 'work'); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                            <?php if ($service['is_featured']): ?>
                                <div class="absolute top-3 right-3">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-amber-50 text-amber-700 rounded-full text-xs font-bold border border-amber-200">
                                        <span class="material-symbols-outlined text-sm">workspace_premium</span>
                                        Featured
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="p-4">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-primary/5 text-primary text-xs font-semibold rounded-full mb-2">
                                <span class="material-symbols-outlined text-xs"><?php echo htmlspecialchars($service['category_icon'] ?: 'category'); ?></span>
                                <?php echo htmlspecialchars($service['category_name']); ?>
                            </span>
                            <h4 class="text-slate-800 font-bold text-sm mb-1 line-clamp-2">
                                <?php echo htmlspecialchars($service['title']); ?>
                            </h4>
                            <?php if ($service['rating']): ?>
                                <div class="flex items-center gap-1 text-xs text-slate-500 mb-3">
                                    <span class="material-symbols-outlined text-xs text-orange-400 fill-1">star</span>
                                    <span class="font-bold text-slate-900"><?php echo number_format($service['rating'], 1); ?></span>
                                    <span>(<?php echo $service['total_ratings']; ?>)</span>
                                </div>
                            <?php endif; ?>
                            <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                                <p class="text-lg font-extrabold text-primary"><?php echo $price_display; ?></p>
                                <span class="text-xs font-bold text-primary">View &rarr;</span>
                            </div>
                        </div>
                    </a>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-14 bg-white rounded-xl border border-slate-200">
                    <span class="material-symbols-outlined text-7xl text-slate-300 mb-3">work_off</span>
                    <h3 class="text-xl font-bold text-slate-700 mb-1">No Services Yet</h3>
                    <p class="text-slate-500 text-sm">This seller hasn't listed any services.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Products Tab -->
        <div id="content-products" class="tab-content hidden">
            <?php if ($products_result && $products_result->num_rows > 0): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-5">
                    <?php while($product = $products_result->fetch_assoc()): ?>
                    <a href="<?php echo productUrl($product['slug']); ?>"
                       class="bg-white rounded-xl border border-slate-200 overflow-hidden hover:shadow-lg transition-all block">
                        <div class="relative h-40 overflow-hidden bg-slate-100">
                            <?php if (!empty($product['image_url'])): ?>
                                <img src="<?php echo htmlspecialchars($product['image_url']); ?>"
                                     alt="<?php echo htmlspecialchars($product['title']); ?>"
                                     class="w-full h-full object-cover"/>
                            <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center">
                                    <span class="material-symbols-outlined text-6xl text-slate-300">inventory_2</span>
                                </div>
                            <?php endif; ?>
                            <?php if ($product['is_featured']): ?>
                                <div class="absolute top-3 right-3">
                                    <span class="px-2.5 py-1 bg-amber-50 text-amber-700 rounded-full text-xs font-bold border border-amber-200">Featured</span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="p-4">
                            <span class="inline-block px-2 py-0.5 bg-slate-100 text-slate-600 text-xs font-semibold rounded-full mb-2">
                                <?php echo htmlspecialchars($product['category_name']); ?>
                            </span>
                            <h4 class="text-slate-800 font-bold text-sm mb-1 line-clamp-2">
                                <?php echo htmlspecialchars($product['title']); ?>
                            </h4>
                            <p class="text-xs text-slate-500 mb-3 line-clamp-2">
                                <?php echo htmlspecialchars(substr($product['description'], 0, 80)); ?>...
                            </p>
                            <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                                <p class="text-lg font-extrabold text-primary">₦<?php echo number_format($product['price'], 2); ?></p>
                                <span class="text-xs font-bold text-primary">View &rarr;</span>
                            </div>
                        </div>
                    </a>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-14 bg-white rounded-xl border border-slate-200">
                    <span class="material-symbols-outlined text-7xl text-slate-300 mb-3">inventory_2</span>
                    <h3 class="text-xl font-bold text-slate-700 mb-1">No Products Yet</h3>
                    <p class="text-slate-500 text-sm">This seller hasn't listed any products.</p>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </main>

    <?php include_once 'includes/footer.php'; ?>

    <script>
        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));
            document.querySelectorAll('.tab-button').forEach(b => {
                b.classList.remove('active', 'text-primary', 'border-primary');
                b.classList.add('text-slate-500', 'border-transparent');
            });
            document.getElementById('content-' + tabName).classList.remove('hidden');
            const active = document.getElementById('tab-' + tabName);
            active.classList.add('active', 'text-primary', 'border-primary');
            active.classList.remove('text-slate-500', 'border-transparent');
        }

        function shareProfile() {
            const url = window.location.href;
            const name = "<?php echo addslashes($seller['full_name'] ?: $seller['username']); ?>";
            if (navigator.share) {
                navigator.share({ title: name + ' - CampMart', text: `Check out ${name}'s profile on CampMart!`, url }).catch(() => {});
            } else {
                navigator.clipboard.writeText(url).then(() => alert('Profile link copied!')).catch(() => {});
            }
        }
    </script>
</body>
</html>
