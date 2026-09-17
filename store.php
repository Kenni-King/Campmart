<?php
session_start();
require_once 'includes/controller.php';

$username = trim($_GET['username'] ?? '');

if ($username === '') {
    header('Location: products.php');
    exit;
}

$sellerQuery = "
    SELECT u.*,
           COUNT(DISTINCT CASE WHEN s.status = 'active' THEN s.id END) AS total_services,
           COUNT(DISTINCT CASE WHEN p.status = 'approved' THEN p.id END) AS total_products
    FROM users u
    LEFT JOIN services s ON u.id = s.user_id
    LEFT JOIN products p ON u.id = p.user_id
    WHERE u.username = ?
    GROUP BY u.id
    LIMIT 1
";

$stmt = $db->prepare($sellerQuery);
$stmt->bind_param("s", $username);
$stmt->execute();
$sellerResult = $stmt->get_result();
$seller = $sellerResult->fetch_assoc();

if (!$seller) {
    header('Location: products.php');
    exit;
}

$servicesQuery = "
    SELECT s.*, sc.name AS category_name, sc.icon AS category_icon
    FROM services s
    LEFT JOIN service_categories sc ON s.service_category_id = sc.id
    WHERE s.user_id = ? AND s.status = 'active'
    ORDER BY s.is_featured DESC, s.total_orders DESC, s.created_at DESC
";
$stmt = $db->prepare($servicesQuery);
$stmt->bind_param("i", $seller['id']);
$stmt->execute();
$servicesResult = $stmt->get_result();

$productsQuery = "
    SELECT p.id, p.title, p.slug, p.description, p.price, p.is_featured, p.condition_type,
           c.name AS category_name,
           pi.image_url
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
    WHERE p.user_id = ? AND p.status = 'approved' AND p.availability = 'available'
    ORDER BY p.is_featured DESC, p.created_at DESC
";
$stmt = $db->prepare($productsQuery);
$stmt->bind_param("i", $seller['id']);
$stmt->execute();
$productsResult = $stmt->get_result();

$displayName = $seller['full_name'] ?: $seller['username'];
$pageTitle = htmlspecialchars($displayName) . ' Store | CampMart';
$sellerInitial = strtoupper(substr($seller['username'], 0, 1));
$memberSince = !empty($seller['created_at']) ? date('M Y', strtotime($seller['created_at'])) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <base href="<?php echo SITE_URL; ?>">
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title><?php echo $pageTitle; ?></title>
    <script src="tailwind34.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />
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

    <main class="max-w-[1440px] mx-auto px-4 sm:px-6 py-8">
        <nav class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-6">
            <a class="hover:text-primary" href="index.php">Home</a>
            <span class="material-symbols-outlined text-sm">chevron_right</span>
            <a class="hover:text-primary" href="products.php">Stores</a>
            <span class="material-symbols-outlined text-sm">chevron_right</span>
            <span class="text-slate-900">@<?php echo htmlspecialchars($seller['username']); ?></span>
        </nav>

        <section class="rounded-3xl border border-slate-200 bg-white p-5 sm:p-6 lg:p-8 shadow-sm mb-8">
            <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
                <div class="max-w-3xl">
                    <p class="text-xs font-bold uppercase tracking-[0.22em] text-slate-400 mb-3">Campus Storefront</p>
                    <div class="flex flex-wrap items-center gap-2 mb-3">
                        <h1 class="text-xl sm:text-xl font-extrabold text-brand-green"><?php echo htmlspecialchars($displayName); ?></h1>
                        <?php if (!empty($seller['is_verified'])): ?>
                            <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700 border border-blue-200">
                                <span class="material-symbols-outlined text-sm fill-1">verified</span>
                                Verified
                            </span>
                        <?php endif; ?>
                    </div>
                    <p class="text-slate-600 text-sm mb-4">
                        Browse everything this seller currently has available on CampMart, from listed products to campus services.
                    </p>
                    <div class="flex flex-wrap gap-3">
                        <span class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">
                            <span class="material-symbols-outlined text-base text-primary">alternate_email</span>
                            @<?php echo htmlspecialchars($seller['username']); ?>
                        </span>
                        <span class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">
                            <span class="material-symbols-outlined text-base text-primary">inventory_2</span>
                            <?php echo (int) $seller['total_products']; ?> products
                        </span>
                        <span class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700">
                            <span class="material-symbols-outlined text-base text-primary">work</span>
                            <?php echo (int) $seller['total_services']; ?> services
                        </span>
                    </div>
                </div>

                <div class="flex flex-col sm:flex-row gap-3">
                    <button onclick="shareStore()" class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white px-3 py-1 text-sm font-bold text-slate-700 hover:border-primary/40 hover:text-primary transition-colors">
                        <span class="material-symbols-outlined text-lg">share</span>
                        Share Store
                    </button>
                    <a href="<?php echo SITE_URL; ?>chat.php?seller_id=<?php echo $seller['id']; ?>" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-primary px-3 py-1 text-sm font-bold text-white hover:bg-primary/90 transition-colors">
                            <span class="material-symbols-outlined text-lg">chat</span>
                            Chat Seller
                        </a>
                </div>
            </div>
        </section>

        <section class="mb-10">
            <div class="flex items-end justify-between gap-4 mb-5">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400 mb-2">Store Inventory</p>
                    <h2 class="text-xl sm:text-2xl font-extrabold text-brand-green">Products</h2>
                    <p class="text-xs sm:text-sm text-slate-500 mt-1">Available items you can browse, compare, and contact the seller about.</p>
                </div>
                <span class="hidden sm:inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-2 text-sm font-bold text-slate-700">
                    <span class="material-symbols-outlined text-base text-primary">inventory_2</span>
                    <?php echo (int) $seller['total_products']; ?> listed
                </span>
            </div>

            <?php if ($productsResult && $productsResult->num_rows > 0): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <?php while ($product = $productsResult->fetch_assoc()): ?>
                        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden hover:shadow-lg transition-all">
                            <div class="relative h-48 overflow-hidden bg-slate-100">
                                <?php if (!empty($product['image_url'])): ?>
                                    <img src="<?php echo htmlspecialchars($product['image_url']); ?>" alt="<?php echo htmlspecialchars($product['title']); ?>" class="w-full h-full object-cover" />
                                <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center">
                                        <span class="material-symbols-outlined text-7xl text-slate-300">inventory_2</span>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($product['is_featured'])): ?>
                                    <div class="absolute top-3 right-3">
                                        <span class="px-3 py-1 bg-amber-50 text-amber-700 rounded-full text-xs font-bold border border-amber-200">Featured</span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="p-4">
                                <span class="inline-block px-2 py-1 bg-slate-100 text-slate-600 text-xs font-semibold rounded-full mb-2">
                                    <?php echo htmlspecialchars($product['category_name'] ?? 'Product'); ?>
                                </span>

                                <h4 class="text-slate-800 font-bold text-sm sm:text-base mb-2 line-clamp-2"><?php echo htmlspecialchars($product['title']); ?></h4>
                                <p class="text-xs text-slate-500 mb-3 line-clamp-2"><?php echo htmlspecialchars(substr($product['description'], 0, 90)); ?></p>
                                <?php if (!empty($product['condition_type'])): ?>
                                    <p class="text-xs text-slate-500 mb-4"><?php echo ucfirst(str_replace('_', ' ', $product['condition_type'])); ?></p>
                                <?php endif; ?>

                                <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                                    <div>
                                        <p class="text-xs text-slate-500 mb-0.5">Price</p>
                                        <p class="text-lg sm:text-xl font-extrabold text-primary">
                                            <?php echo $product['price'] > 0 ? 'NGN ' . number_format($product['price'], 0) : 'FREE'; ?>
                                        </p>
                                    </div>
                                    <a class="bg-primary text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-opacity-90" href="<?php echo productUrl($product['slug']); ?>">
                                        View
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-16 bg-white rounded-xl border border-slate-200">
                    <span class="material-symbols-outlined text-8xl text-slate-300 mb-4">inventory_2</span>
                    <h3 class="text-2xl font-bold text-slate-700 mb-2">No Products Yet</h3>
                    <p class="text-slate-500">This store has not listed any products yet.</p>
                </div>
            <?php endif; ?>
        </section>

        <section class="mb-10">
            <div class="flex items-end justify-between gap-4 mb-5">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400 mb-2">Service Offers</p>
                    <h2 class="text-xl sm:text-2xl font-extrabold text-brand-green">Services</h2>
                    <p class="text-xs sm:text-sm text-slate-500 mt-1">Professional or campus-based services this seller is currently offering.</p>
                </div>
                <span class="hidden sm:inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-2 text-sm font-bold text-slate-700">
                    <span class="material-symbols-outlined text-base text-primary">work</span>
                    <?php echo (int) $seller['total_services']; ?> active
                </span>
            </div>

            <?php if ($servicesResult && $servicesResult->num_rows > 0): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                    <?php while ($service = $servicesResult->fetch_assoc()):
                        $priceDisplay = $service['pricing_type'] === 'hourly'
                            ? 'NGN ' . number_format($service['price'], 0) . '/hr'
                            : 'From NGN ' . number_format($service['price'], 0);
                        $portfolioImages = json_decode($service['portfolio_images'] ?? '[]', true);
                        $firstImage = !empty($portfolioImages) ? $portfolioImages[0] : null;
                    ?>
                        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden hover:shadow-lg transition-all">
                            <div class="relative h-48 overflow-hidden bg-gradient-to-br from-primary/10 to-brand-green/10">
                                <?php if ($firstImage): ?>
                                    <img src="<?php echo htmlspecialchars($firstImage); ?>" alt="<?php echo htmlspecialchars($service['title']); ?>" class="w-full h-full object-cover" />
                                <?php else: ?>
                                    <div class="absolute inset-0 flex items-center justify-center">
                                        <span class="material-symbols-outlined text-7xl text-primary/20"><?php echo htmlspecialchars($service['category_icon'] ?: 'work'); ?></span>
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($service['is_featured'])): ?>
                                    <div class="absolute top-3 right-3">
                                        <span class="inline-flex items-center gap-1 px-3 py-1 bg-amber-50 text-amber-700 rounded-full text-xs font-bold border border-amber-200">
                                            <span class="material-symbols-outlined text-sm">workspace_premium</span>
                                            Featured
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="p-4">
                                <span class="inline-flex items-center gap-1 px-2 py-1 bg-primary/5 text-primary text-xs font-semibold rounded-full mb-2">
                                    <span class="material-symbols-outlined text-sm"><?php echo htmlspecialchars($service['category_icon'] ?: 'category'); ?></span>
                                    <?php echo htmlspecialchars($service['category_name'] ?? 'Service'); ?>
                                </span>

                                <h4 class="text-slate-800 font-bold text-sm sm:text-base mb-2 line-clamp-2"><?php echo htmlspecialchars($service['title']); ?></h4>
                                <p class="text-xs text-slate-500 mb-4 line-clamp-2">
                                    <?php echo htmlspecialchars($service['short_description'] ?: substr($service['description'], 0, 90)); ?>
                                </p>

                                <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                                    <div>
                                        <p class="text-xs text-slate-500 mb-0.5"><?php echo ucfirst(str_replace('_', ' ', $service['pricing_type'])); ?></p>
                                        <p class="text-lg sm:text-xl font-extrabold text-primary"><?php echo $priceDisplay; ?></p>
                                    </div>
                                    <a class="bg-primary text-white px-4 py-2 rounded-lg text-sm font-bold hover:bg-opacity-90" href="<?php echo serviceUrl($service['slug']); ?>">
                                        View
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-16 bg-white rounded-xl border border-slate-200">
                    <span class="material-symbols-outlined text-8xl text-slate-300 mb-4">work_off</span>
                    <h3 class="text-2xl font-bold text-slate-700 mb-2">No Services Yet</h3>
                    <p class="text-slate-500">This store has not listed any services yet.</p>
                </div>
            <?php endif; ?>
        </section>

        <section class="rounded-3xl border border-slate-200 bg-white p-5 sm:p-6 lg:p-8 shadow-sm">
            <div class="mb-6">
                <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400 mb-2">Seller Profile</p>
                <h2 class="text-xl sm:text-2xl font-extrabold text-brand-green">About This Seller</h2>
                <p class="text-xs sm:text-sm text-slate-500 mt-1">A simple snapshot of who runs this store and how to reach them.</p>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-[320px_1fr] gap-6 lg:gap-8">
                <div class="rounded-3xl bg-slate-50 border border-slate-200 p-6">
                    <div class="flex flex-col items-center text-center">
                        <div class="size-28 rounded-3xl overflow-hidden bg-white border border-slate-200 shadow-sm mb-4">
                            <?php if (!empty($seller['profile_image'])): ?>
                                <img alt="<?php echo htmlspecialchars($displayName); ?>" class="w-full h-full object-cover" src="<?php echo htmlspecialchars($seller['profile_image']); ?>" />
                            <?php else: ?>
                                <div class="w-full h-full bg-gradient-to-br from-primary to-brand-green text-white flex items-center justify-center text-4xl font-bold">
                                    <?php echo htmlspecialchars($sellerInitial); ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="flex flex-wrap items-center justify-center gap-2 mb-2">
                            <h3 class="text-lg sm:text-xl font-extrabold text-slate-900"><?php echo htmlspecialchars($displayName); ?></h3>
                            <?php if (!empty($seller['is_verified'])): ?>
                                <span class="material-symbols-outlined text-blue-500 text-xl fill-1" title="Verified Seller">verified</span>
                            <?php endif; ?>
                        </div>
                        <p class="text-sm text-slate-500 mb-4">@<?php echo htmlspecialchars($seller['username']); ?></p>

                        <?php if (!empty($seller['rating'])): ?>
                            <div class="inline-flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm font-semibold text-slate-700 border border-slate-200 mb-4">
                                <span class="material-symbols-outlined text-orange-400 text-base fill-1">star</span>
                                <?php echo number_format($seller['rating'], 1); ?> rating
                            </div>
                        <?php endif; ?>

                        <div class="grid grid-cols-2 gap-3 w-full">
                            <div class="rounded-2xl bg-white border border-slate-200 p-3">
                                <p class="text-xs font-bold uppercase tracking-wide text-slate-400 mb-1">Sales</p>
                                <p class="text-base sm:text-lg font-extrabold text-slate-900"><?php echo number_format($seller['total_sales']); ?></p>
                            </div>
                            <div class="rounded-2xl bg-white border border-slate-200 p-3">
                                <p class="text-xs font-bold uppercase tracking-wide text-slate-400 mb-1">Member</p>
                                <p class="text-base sm:text-lg font-extrabold text-slate-900"><?php echo htmlspecialchars($memberSince ?? 'New'); ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-5">
                    <div class="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400 mb-2">Bio</p>
                        <p class="text-slate-700 leading-relaxed">
                            <?php echo !empty($seller['bio']) ? nl2br(htmlspecialchars($seller['bio'])) : 'This seller has not added a public bio yet, but their products and services are available above.'; ?>
                        </p>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="rounded-2xl border border-slate-200 bg-white p-4">
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400 mb-2">Username</p>
                            <p class="font-semibold text-slate-800">@<?php echo htmlspecialchars($seller['username']); ?></p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-white p-4">
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400 mb-2">Location</p>
                            <p class="font-semibold text-slate-800"><?php echo !empty($seller['location']) ? htmlspecialchars($seller['location']) : 'Not specified'; ?></p>
                        </div>
                        <div class="rounded-2xl border border-slate-200 bg-white p-4">
                            <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400 mb-2">Store Activity</p>
                            <p class="font-semibold text-slate-800"><?php echo (int) $seller['total_products']; ?> products, <?php echo (int) $seller['total_services']; ?> services</p>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-3">
                        <a href="<?php echo SITE_URL; ?>chat.php?seller_id=<?php echo $seller['id']; ?>" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-primary px-3 py-1 text-sm font-bold text-white hover:bg-primary/90 transition-colors">
                            <span class="material-symbols-outlined text-lg">chat</span>
                            Chat Seller
                        </a>
                        <button onclick="shareStore()" class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white px-3 py-1 text-sm font-bold text-slate-700 hover:border-primary/40 hover:text-primary transition-colors">
                            <span class="material-symbols-outlined text-lg">share</span>
                            Share Store
                        </button>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <?php include_once 'includes/footer.php'; ?>

    <script>
        function shareStore() {
            const storeUrl = window.location.href;
            const storeName = "<?php echo addslashes($displayName); ?>";

            if (navigator.share) {
                navigator.share({
                    title: storeName + ' Store - CampMart',
                    text: `Check out ${storeName}'s store on CampMart!`,
                    url: storeUrl
                }).catch(err => console.log('Share cancelled'));
            } else {
                navigator.clipboard.writeText(storeUrl).then(() => {
                    alert('Store link copied to clipboard!');
                }).catch(err => {
                    console.error('Failed to copy:', err);
                });
            }
        }
    </script>
</body>
</html>
