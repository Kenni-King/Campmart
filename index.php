<?php
session_start();
include_once "includes/controller.php";

// Get logged-in user's university_id for filtering
$current_user_university_id = null;
if (isLoggedIn()==true) {
        $current_user_university_id = $universityId;
}


// Build university filter condition
$university_filter = $current_user_university_id ? "AND p.university_id = $current_user_university_id" : "";
$university_filter_s = $current_user_university_id ? "AND s.university_id = $current_user_university_id" : "";
$university_filter_lf = $current_user_university_id ? "AND lf.university_id = $current_user_university_id" : "";

// Get active categories
$categories_query = "SELECT * FROM categories WHERE parent_id IS NULL AND is_active = TRUE ORDER BY display_order ASC";
$categories_result = $db->query($categories_query);

// Get new arrivals (latest 10 products)
$new_arrivals_query = "
    SELECT p.*, u.full_name as seller_name, u.rating as seller_rating, u.is_verified,
           c.name as category_name, pi.image_url
    FROM products p
    JOIN users u ON p.user_id = u.id
    JOIN categories c ON p.category_id = c.id
    LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = TRUE
    WHERE p.status = 'approved' AND p.availability = 'available'
    $university_filter
    ORDER BY p.created_at DESC
    LIMIT 10
";
$new_arrivals = $db->query($new_arrivals_query);

// Get related/tech products
$related_query = "
    SELECT p.*, u.full_name as seller_name, u.is_verified, pi.image_url
    FROM products p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = TRUE
    WHERE p.status = 'approved' AND p.availability = 'available'
    AND p.category_id IN (1, 10, 11, 12)
    $university_filter
    ORDER BY p.views_count DESC
    LIMIT 8
";
$related_products = $db->query($related_query);

// Get trending products
$trending_query = "
    SELECT p.*, u.full_name as seller_name, u.is_verified, pi.image_url,
           COUNT(DISTINCT pv.id) as recent_views
    FROM products p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = TRUE
    LEFT JOIN product_views pv ON p.id = pv.product_id 
        AND pv.viewed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    WHERE p.status = 'approved' AND p.availability = 'available'
    $university_filter
    GROUP BY p.id
    ORDER BY recent_views DESC, p.bookmarks_count DESC
    LIMIT 10
";
$trending_products = $db->query($trending_query);

// Personalized AI recommendations (logged-in users only)
$recommended_products = null;
if (isLoggedIn()) {
    include_once 'includes/ai/recommend.php';
    $rec_ranked = ai_recommend_products($userId, ['limit' => 8, 'universityId' => $current_user_university_id]);
    if (!empty($rec_ranked)) {
        $rec_ids = implode(',', array_map('intval', array_keys($rec_ranked)));
        $rec_query = "
            SELECT p.*, u.full_name as seller_name, u.is_verified, pi.image_url
            FROM products p
            JOIN users u ON p.user_id = u.id
            LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = TRUE
            WHERE p.id IN ($rec_ids)
        ";
        $recommended_products = $db->query($rec_query);
    }
}

// Get featured services  //AND s.is_featured = TRUE
$services_query = "
    SELECT s.*, u.full_name as provider_name, u.profile_image,
           sc.name as category_name, sc.color_code
    FROM services s
    JOIN users u ON s.user_id = u.id
    JOIN service_categories sc ON s.service_category_id = sc.id
    WHERE s.status = 'active' 
    $university_filter_s
    ORDER BY s.total_orders DESC
    LIMIT 3
";
$services = $db->query($services_query);

// Get lost & found items
$lost_found_query = "
    SELECT lf.*, u.full_name as reporter_name, u.id as reporter_user_id
    FROM lost_found_items lf
    JOIN users u ON lf.user_id = u.id
    WHERE lf.status = 'open'
    $university_filter_lf
    ORDER BY lf.created_at DESC
    LIMIT 6
";
$lost_found = $db->query($lost_found_query);

// Get sponsored content
$sponsored_query = "
    SELECT * FROM sponsored_content
    WHERE status = 'active'
    AND start_date <= CURDATE()
    AND end_date >= CURDATE()
    AND placement = 'sidebar'
    AND university_id = '$universityId'
    ORDER BY RAND()
    LIMIT 2
";
$sponsored_sidebar = $db->query($sponsored_query);

// Get sponsored grid ads
$sponsored_grid_query = "
    SELECT * FROM sponsored_content
    WHERE status = 'active'
    AND start_date <= CURDATE()
    AND end_date >= CURDATE()
    AND placement = 'grid'
    AND university_id = '$universityId'
    ORDER BY RAND()
    LIMIT 4
";
$sponsored_grid = $db->query($sponsored_grid_query);

// Get free items
$free_items_query = "
    SELECT p.*, u.full_name as donor_name, pi.image_url
    FROM products p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = TRUE
    WHERE p.price = 0 AND p.status = 'approved' AND p.availability = 'available'
    $university_filter
    ORDER BY p.created_at DESC
    LIMIT 6
";
$free_items = $db->query($free_items_query);

// Get platform statistics
$stats_query = "SELECT COUNT(*) as total FROM products WHERE status='approved' AND availability='available'";
$stats_result = $db->query($stats_query);
$stats = $stats_result->fetch_assoc();
$total_listings = $stats['total'];

// Get active flash sale (featured on homepage)
$flash_sale_query = "
    SELECT fs.*, 
           COUNT(DISTINCT fsp.id) as product_count,
           SUM(fsp.sold_count) as total_sold,
           TIMESTAMPDIFF(SECOND, NOW(), fs.end_time) as seconds_remaining
    FROM flash_sales fs
    LEFT JOIN flash_sale_products fsp ON fs.id = fsp.flash_sale_id
    WHERE fs.status = 'active' 
    AND fs.is_featured = TRUE
    AND fs.start_time <= NOW()
    AND fs.end_time >= NOW()
    GROUP BY fs.id
    ORDER BY fs.created_at DESC
    LIMIT 1
";
$flash_sale_result = $db->query($flash_sale_query);
$active_flash_sale = $flash_sale_result && $flash_sale_result->num_rows > 0 ? $flash_sale_result->fetch_assoc() : null;

// Get flash sale products if there's an active sale
$flash_sale_products = null;
if ($active_flash_sale) {
    $flash_products_query = "
        SELECT fsp.*, p.title, p.description, pi.image_url,
               p.condition_type as product_condition,
               (fsp.stock_limit - fsp.sold_count) as remaining_stock
        FROM flash_sale_products fsp
        JOIN products p ON fsp.product_id = p.id
        LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = TRUE
        WHERE fsp.flash_sale_id = " . $active_flash_sale['id'] . "
        AND fsp.is_active = TRUE
        AND (fsp.stock_limit IS NULL OR fsp.sold_count < fsp.stock_limit)
        ORDER BY fsp.discount_percentage DESC
        LIMIT 8
    ";
    $flash_sale_products = $db->query($flash_products_query);
}

// SEO Metadata
$page_title = "CampMart | The Premium Campus Marketplace - Buy, Sell & Trade on Campus";
$page_description = "CampMart is Nigeria's leading campus marketplace connecting students and staff. Buy and sell products, access campus services, find lost items, and discover exclusive student deals. Safe, trusted, and campus-verified.";
$page_keywords = "campus marketplace, student marketplace Nigeria, buy sell campus, campus services, student deals, university marketplace, campus trading, student products, campus classifieds, Nigerian student marketplace";

// URL and image setup
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$page_url = SITE_URL;

// Fetch social preview image from settings
$preview_query = "SELECT setting_value FROM system_settings WHERE setting_key = 'social_preview_image' LIMIT 1";
$preview_result = $db->query($preview_query);
if ($preview_result && $preview_result->num_rows > 0) {
    $preview_row = $preview_result->fetch_assoc();
    $preview_image = SITE_URL . $preview_row['setting_value'];
} else {
    $preview_image = SITE_URL . 'uploads/campmart-social-preview.jpg'; // Fallback
}

// Fetch site logo from settings
$logo_query = "SELECT setting_value FROM system_settings WHERE setting_key = 'site_logo' LIMIT 1";
$logo_result = $db->query($logo_query);
if ($logo_result && $logo_result->num_rows > 0) {
    $logo_row = $logo_result->fetch_assoc();
    $site_logo = SITE_URL . $logo_row['setting_value'];
} else {
    $site_logo = SITE_URL . 'uploads/campmart-logo.png'; // Fallback
}

// Ensure preview image is absolute URL
if (!filter_var($preview_image, FILTER_VALIDATE_URL)) {
    $preview_image = $protocol . $host . '/' . ltrim($preview_image, '/');
}

// Structured data for Organization and WebSite
$structured_data = [
    "@context" => "https://schema.org",
    "@graph" => [
        [
            "@type" => "Organization",
            "name" => "CampMart",
            "url" => SITE_URL,
            "logo" => $site_logo,
            "description" => $page_description,
            "sameAs" => [
                // Add social media profiles here when available
            ]
        ],
        [
            "@type" => "WebSite",
            "name" => "CampMart",
            "url" => SITE_URL,
            "potentialAction" => [
                "@type" => "SearchAction",
                "target" => SITE_URL . "products?search={search_term_string}",
                "query-input" => "required name=search_term_string"
            ]
        ]
    ]
];
?>
<!DOCTYPE html>
<html class="light" lang="en">

<head>
    <meta charset="utf-8" />
    <base href="<?php echo SITE_URL; ?>">
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    
    <!-- Primary Meta Tags -->
    <title><?php echo htmlspecialchars($page_title); ?></title>
    <meta name="title" content="<?php echo htmlspecialchars($page_title); ?>" />
    <meta name="description" content="<?php echo htmlspecialchars($page_description); ?>" />
    <meta name="keywords" content="<?php echo htmlspecialchars($page_keywords); ?>" />
    <meta name="author" content="CampMart" />
    <meta name="robots" content="index, follow" />
    <meta name="language" content="English" />
    <link rel="canonical" href="<?php echo htmlspecialchars($page_url); ?>" />
    
    <!-- Open Graph / Facebook -->
    <meta property="og:type" content="website" />
    <meta property="og:url" content="<?php echo htmlspecialchars($page_url); ?>" />
    <meta property="og:title" content="<?php echo htmlspecialchars($page_title); ?>" />
    <meta property="og:description" content="<?php echo htmlspecialchars($page_description); ?>" />
    <meta property="og:image" content="<?php echo htmlspecialchars($preview_image); ?>" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />
    <meta property="og:locale" content="en_NG" />
    <meta property="og:site_name" content="CampMart" />
    
    <!-- Twitter -->
    <meta property="twitter:card" content="summary_large_image" />
    <meta property="twitter:url" content="<?php echo htmlspecialchars($page_url); ?>" />
    <meta property="twitter:title" content="<?php echo htmlspecialchars($page_title); ?>" />
    <meta property="twitter:description" content="<?php echo htmlspecialchars($page_description); ?>" />
    <meta property="twitter:image" content="<?php echo htmlspecialchars($preview_image); ?>" />
    
    <!-- WhatsApp -->
    <meta property="og:image:alt" content="CampMart - The Premium Campus Marketplace" />
    
    <!-- Mobile Optimization -->
    <meta name="theme-color" content="#064E3B" />
    <meta name="apple-mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
    
    <!-- Structured Data -->
    <script type="application/ld+json">
    <?php echo json_encode($structured_data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT); ?>
    </script>
    
    <script src="tailwind34.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&amp;display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet" />
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#064E3B",
                        "secondary": "#F97316", // Brand Orange
                        "accent": "#BEF264",
                        "surface": "#F8FAFC",
                    },
                    fontFamily: {
                        "display": ["Plus Jakarta Sans", "sans-serif"]
                    },
                },
            },
        }
    </script>
    <style type="text/tailwindcss">
        @layer components {
            .nav-item {
                @apply flex items-center gap-2 px-4 py-2 text-sm font-semibold text-slate-600 hover:text-primary transition-colors;
            }
            .category-card {
                @apply flex flex-col items-center gap-3 p-4 rounded-2xl border border-slate-100 bg-white hover:border-primary hover:shadow-md transition-all cursor-pointer min-w-[120px] flex-shrink-0;
            }
            .product-grid {
                @apply grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-3;
            }
            .product-row {
                @apply flex gap-6 overflow-x-auto pb-6 scrollbar-hide snap-x;
            }
            .product-card-small {
                @apply bg-white rounded-xl border border-slate-200 overflow-hidden flex flex-col hover:shadow-lg transition-shadow duration-300 min-w-[240px] max-w-[280px] snap-start;
            }
            .product-card {
                @apply bg-white rounded-xl border border-slate-200 overflow-hidden flex flex-col hover:shadow-lg transition-shadow duration-300;
            }
            .btn-primary {
                @apply bg-primary text-white px-6 py-3 rounded-lg font-bold hover:bg-opacity-90 transition-all flex items-center justify-center gap-2;
            }
            .btn-secondary {
                @apply bg-secondary text-white px-6 py-3 rounded-lg font-bold hover:brightness-105 transition-all flex items-center justify-center gap-2;
            }
            .btn-accent {
                @apply bg-accent text-primary px-6 py-3 rounded-lg font-bold hover:brightness-105 transition-all flex items-center justify-center gap-2;
            }
            .btn-outline {
                @apply border border-slate-200 text-slate-700 px-6 py-3 rounded-lg font-bold hover:bg-slate-50 transition-all flex items-center justify-center gap-2;
            }
            .scrollbar-hide::-webkit-scrollbar {
                display: none;
            }
            .scrollbar-hide {
                -ms-overflow-style: none;
                scrollbar-width: none;
            }
            .badge-orange {
                @apply bg-secondary/10 border border-secondary/20 text-secondary px-2.5 py-1 rounded-lg text-xs font-black uppercase;
            }
        }
    </style>
</head>

<body class="bg-white font-display text-slate-900 antialiased">
    <?php include_once 'includes/header.php'; ?>
    <main>
        <section class="relative bg-primary overflow-hidden min-h-[450px] flex items-center justify-center text-center">
            <img alt="Campus Life" class="absolute inset-0 w-full h-full object-cover mix-blend-overlay opacity-50" src="https://lh3.googleusercontent.com/aida-public/AB6AXuBodIM_L8NUfhwfjs4RVRVGOI3fao1AQPY-zFphNZLuozcnBGgv8k_NfXaM3kforNwf7_9_0_kk8WEJfxb9Vp3jBtYLKdFTfjhKOF3u4j1dpnPrL5qSHRH3dmC6NQisFpT_CkZm_MXotk0R1Lg5HXgi-5v6EU5F7nNpZccMCDlGFcZ_69VEf43WMLhLTkChM32fNBS2amc6Wgsi2ZBP3s0xbu8X3Ejn7nIKJEjDTER6-plThcGv4U-XUxKT1WyU-tea5bPmFZJ0U3t8" />
            <div class="absolute inset-0 bg-gradient-to-b from-primary/50 via-primary/60 to-primary/70"></div>
            <div class="relative z-10 px-6 max-w-3xl flex flex-col items-center">
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-secondary/20 border border-secondary/30 text-secondary rounded-full text-[10px] font-bold uppercase tracking-widest mb-5">
                    <span class="material-symbols-outlined text-xs">verified_user</span> Verified Campus Hub
                </span>
                <h1 class="text-4xl md:text-5xl font-extrabold text-white leading-tight mb-5">
                    Elevate your <br /><span class="text-secondary">Campus Style.</span>
                </h1>
                <p class="text-base text-slate-200/90 mb-8 max-w-xl">
                    The exclusive peer-to-peer marketplace designed for university students. Join <?php echo number_format($total_listings); ?>+ active listings from verified students.
                </p>
                <div class="flex flex-col sm:flex-row flex-wrap gap-3 justify-center">
                    <button class="btn-secondary px-8 py-2.5 text-base" onclick="window.location.href='my-listings.php'">Start Selling</button>
                    <button class="bg-white/10 text-white border border-white/20 px-8 py-2.5 text-base rounded-lg font-bold hover:bg-white/20 backdrop-blur-sm transition-all" onclick="window.location.href='products.php'">Browse Products</button>
                    <button class="bg-white/10 text-white border border-white/20 px-8 py-2.5 text-base rounded-lg font-bold hover:bg-white/20 backdrop-blur-sm transition-all" onclick="window.location.href='services.php'">Browse Services</button>
                </div>
            </div>
        </section>
        <div class="max-w-[1440px] mx-auto px-6 py-8">
            <section class="mb-12">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-extrabold text-primary flex items-center gap-2">
                        <span class="material-symbols-outlined">grid_view</span> Categories
                    </h3>
                </div>
                <div class="flex gap-4 overflow-x-auto scrollbar-hide">
                    <?php
                    if ($categories_result && $categories_result->num_rows > 0):
                        while ($category = $categories_result->fetch_assoc()):
                    ?>
                            <div onclick="window.location.href='products.php?category=<?php echo urlencode($category['id']); ?>'" class="category-card p-2 min-w-[86px] gap-2 border-slate-200">
                                <div class="size-14 rounded-xl bg-slate-100 flex items-center justify-center text-primary">
                                    <span class="material-symbols-outlined text-2xl"><?php echo htmlspecialchars($category['icon']); ?></span>
                                </div>
                                <span class="text-xs font-bold"><?php echo htmlspecialchars($category['name']); ?></span>
                            </div>
                    <?php
                        endwhile;
                    endif;
                    ?>
                </div>
            </section>
            <section class="mb-12">
                <div class="flex items-end justify-between mb-6">
                    <div>
                        <h2 class="text-xl font-extrabold text-primary">New Arrivals</h2>
                        <p class="text-slate-500 text-sm mt-1">Freshly posted listings from your campus community</p>
                    </div>
                    <a class="text-sm font-bold text-primary flex items-center gap-1 hover:underline" href="products.php?sort=newest">
                        View all <span class="material-symbols-outlined text-base">arrow_forward</span>
                    </a>
                </div>
                <div class="product-grid">
                    <?php
                    if ($new_arrivals && $new_arrivals->num_rows > 0):
                        while ($product = $new_arrivals->fetch_assoc()):
                            $image_url = $product['image_url'] ?: 'https://via.placeholder.com/400x400?text=No+Image';
                            $price_display = $product['price'] == 0 ? 'FREE' : '₦' . number_format($product['price']);
                    ?>
                            <div class="product-card" onclick="window.location.href='product/<?php echo $product['slug']; ?>'">
                                <div class="relative aspect-square overflow-hidden bg-slate-100">
                                    <img alt="<?php echo htmlspecialchars($product['title']); ?>" class="w-full h-full object-cover" src="<?php echo htmlspecialchars($image_url); ?>" />
                                    <!-- <div class="absolute top-3 left-3 bg-white/95 px-2.5 py-1 rounded-lg text-xs font-black text-primary shadow-sm"><?php echo strtoupper(htmlspecialchars($product['category_name'])); ?></div> -->
                                </div>
                                <div class="p-1 md:p-2 flex flex-col flex-1">
                                    <div class="flex items-center justify-between mb-1">
                                        <p class="text-xl font-extrabold text-primary"><?php echo $price_display; ?></p>
                                        <button onclick="bookmarkProduct(event, <?php echo $product['id']; ?>)" class="text-slate-400 hover:text-primary transition-colors" type="button">
                                            <span class="material-symbols-outlined text-lg">bookmark</span>
                                        </button>
                                    </div>
                                    <h4 class="text-slate-800 font-semibold text-sm mb-1"><?php echo htmlspecialchars($product['title']); ?></h4>
                                    <p class="text-xs text-slate-500 mb-3">Seller: <span class="font-medium text-slate-700"><?php echo htmlspecialchars($product['seller_name']); ?></span>
                                        <?php if (!empty($product['is_verified'])): ?><span class="material-symbols-outlined text-blue-500 text-xs fill-1 align-middle" title="Verified Seller">verified</span><?php endif; ?>
                                    </p>
                                </div>
                            </div>
                        <?php
                        endwhile;
                    else:
                        ?>
                        <div class="col-span-full text-center py-8 text-slate-500">
                            <p>No products available at the moment.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </section>
            <?php if ($active_flash_sale):
                // Calculate time remaining
                $seconds = $active_flash_sale['seconds_remaining'];
                $hours = floor($seconds / 3600);
                $minutes = floor(($seconds % 3600) / 60);
                $secs = $seconds % 60;
            ?>
                <section class="mb-12">
                    <div class="mb-16 rounded-3xl bg-gradient-to-br from-primary via-primary to-primary/90 shadow-2xl overflow-hidden relative" id="flash-sale-banner" data-end-time="<?php echo strtotime($active_flash_sale['end_time']); ?>">
                        <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/cubes.png')] opacity-10"></div>
                        <div class="flex flex-col md:flex-row relative z-10">
                            <div class="p-6 md:p-10 md:w-2/3 flex flex-col justify-center">
                                <div class="flex items-center gap-2 text-accent mb-3">
                                    <span class="material-symbols-outlined text-xl md:text-2xl">bolt</span>
                                    <span class="text-xs md:text-sm font-extrabold uppercase tracking-widest">Flash Sale</span>
                                </div>
                                <h2 class="text-2xl md:text-4xl font-extrabold text-white mb-3 md:mb-4 leading-tight"><?php echo htmlspecialchars($active_flash_sale['title']); ?> <br /><span class="text-secondary">Ending Soon</span></h2>
                                <p class="text-slate-100 mb-6 md:mb-8 max-w-md text-sm md:text-base"><?php echo htmlspecialchars($active_flash_sale['description'] ?: 'Grab up to ' . number_format($active_flash_sale['discount_percentage']) . '% off on selected items before time runs out!'); ?></p>
                                <div class="flex gap-2 md:gap-3">
                                    <div class="flex flex-col items-center justify-center gap-0.5 md:gap-1 rounded-xl bg-white/10 backdrop-blur-sm border border-white/20 p-2 md:p-4 w-16 md:w-20 shadow-lg">
                                        <span class="text-2xl md:text-3xl font-extrabold text-white font-mono" id="flash-hours"><?php echo str_pad($hours, 2, '0', STR_PAD_LEFT); ?></span>
                                        <span class="text-[9px] md:text-[10px] uppercase font-bold text-slate-200 tracking-wider">Hours</span>
                                    </div>
                                    <div class="flex items-center text-white/40 font-bold text-xl md:text-2xl">:</div>
                                    <div class="flex flex-col items-center justify-center gap-0.5 md:gap-1 rounded-xl bg-white/10 backdrop-blur-sm border border-white/20 p-2 md:p-4 w-16 md:w-20 shadow-lg">
                                        <span class="text-2xl md:text-3xl font-extrabold text-white font-mono" id="flash-minutes"><?php echo str_pad($minutes, 2, '0', STR_PAD_LEFT); ?></span>
                                        <span class="text-[9px] md:text-[10px] uppercase font-bold text-slate-200 tracking-wider">Mins</span>
                                    </div>
                                    <div class="flex items-center text-white/40 font-bold text-xl md:text-2xl">:</div>
                                    <div class="flex flex-col items-center justify-center gap-0.5 md:gap-1 rounded-xl bg-secondary p-2 md:p-4 w-16 md:w-20 shadow-lg ring-2 md:ring-4 ring-accent/30">
                                        <span class="text-2xl md:text-3xl font-extrabold text-white font-mono" id="flash-seconds"><?php echo str_pad($secs, 2, '0', STR_PAD_LEFT); ?></span>
                                        <span class="text-[9px] md:text-[10px] uppercase font-bold text-white/90 tracking-wider">Secs</span>
                                    </div>
                                </div>
                                <?php if ($active_flash_sale['product_count'] > 0): ?>
                                    <div class="mt-6 flex items-center gap-4 text-xs text-slate-200">
                                        <span class="flex items-center gap-1">
                                            <span class="material-symbols-outlined text-sm">inventory_2</span>
                                            <?php echo $active_flash_sale['product_count']; ?> Items
                                        </span>
                                        <span class="flex items-center gap-1">
                                            <span class="material-symbols-outlined text-sm">local_fire_department</span>
                                            <?php echo $active_flash_sale['total_sold']; ?> Sold
                                        </span>
                                        <span class="flex items-center gap-1">
                                            <span class="material-symbols-outlined text-sm">percent</span>
                                            Up to <?php echo number_format($active_flash_sale['discount_percentage']); ?>% Off
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="md:w-1/3 bg-white/5 backdrop-blur-sm relative min-h-[200px] md:min-h-[280px] flex items-center justify-center">
                                <div class="absolute inset-0 bg-gradient-to-br from-secondary/20 to-transparent"></div>
                                <div class="absolute inset-0 flex items-center justify-center">
                                    <span class="material-symbols-outlined text-accent/20 text-[120px] md:text-[200px] rotate-12">local_offer</span>
                                </div>
                                <div class="absolute bottom-4 right-4 md:bottom-8 md:right-8">
                                    <button class="group flex items-center gap-2 rounded-full bg-white pl-4 md:pl-5 pr-1.5 md:pr-2 py-2 md:py-2.5 text-xs md:text-sm font-extrabold text-primary shadow-xl hover:shadow-2xl hover:scale-105 transition-all" onclick="window.location.href='flash-sale.php?id=<?php echo $active_flash_sale['id']; ?>'">
                                        View All Deals
                                        <div class="flex size-7 md:size-9 items-center justify-center rounded-full bg-secondary text-white group-hover:scale-110 transition-transform">
                                            <span class="material-symbols-outlined text-base md:text-lg">arrow_forward</span>
                                        </div>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            <?php endif;
            
              if ($related_products && $related_products->num_rows > 0): ?>
            <section class="mb-12">
                <div class="flex items-end justify-between mb-6">
                    <div>
                        <h2 class="text-xl font-extrabold text-primary">Related to your search</h2>
                        <p class="text-slate-500 text-sm mt-1">Based on your recent interest</p>
                    </div>
                    <a class="text-sm font-bold text-primary flex items-center gap-1 hover:underline" href="products.php">View all</a>
                </div>
                <div class="product-row">
                    <?php
                  
                        while ($product = $related_products->fetch_assoc()):
                            $image_url = $product['image_url'] ?: 'https://via.placeholder.com/400x400?text=No+Image';
                            $price_display = $product['price'] == 0 ? 'FREE' : '₦' . number_format($product['price']);
                    ?>
                            <div class="product-card-small" onclick="window.location.href='product/<?php echo $product['slug']; ?>'">
                                <div class="relative aspect-square overflow-hidden bg-slate-100">
                                    <img alt="<?php echo htmlspecialchars($product['title']); ?>" class="w-full h-full object-cover" src="<?php echo htmlspecialchars($image_url); ?>" />
                                    <!-- <div class="absolute top-3 left-3 badge-orange">TECH</div> -->
                                </div>
                                <div class="p-4">
                                    <div class="flex items-center justify-between mb-1">
                                        <p class="text-lg font-extrabold text-primary"><?php echo $price_display; ?></p>
                                        <button onclick="bookmarkProduct(event, <?php echo $product['id']; ?>)" class="text-slate-400 hover:text-primary transition-colors" type="button">
                                            <span class="material-symbols-outlined text-lg">bookmark</span>
                                        </button>
                                    </div>
                                    <h4 class="text-slate-800 font-semibold text-sm mb-1 line-clamp-1"><?php echo htmlspecialchars($product['title']); ?></h4>
                                    <p class="text-xs text-slate-500 mb-3">Seller: <span class="font-medium text-slate-700"><?php echo htmlspecialchars($product['seller_name']); ?></span>
                                        <?php if (!empty($product['is_verified'])): ?><span class="material-symbols-outlined text-blue-500 text-xs fill-1 align-middle" title="Verified Seller">verified</span><?php endif; ?>
                                    </p>
                                 
                                </div>
                            </div>
                    <?php endwhile; ?>
                </div>
            </section>
            <?php endif;  ?>
            <?php  if ($trending_products && $trending_products->num_rows > 0): ?>
            <section class="mb-12">
                <div class="flex items-end justify-between mb-6">
                    <div>
                        <h2 class="text-xl font-extrabold text-primary">Trending on Campus</h2>
                        <p class="text-slate-500 text-sm mt-1">What everyone is talking about this week</p>
                    </div>
                    <a class="text-sm font-bold text-primary flex items-center gap-1 hover:underline" href="products.php?sort=popular">View all</a>
                </div>
                <div class="product-row">
                    <?php
                   
                        while ($product = $trending_products->fetch_assoc()):
                            $image_url = $product['image_url'] ?: 'https://via.placeholder.com/400x400?text=No+Image';
                            $price_display = $product['price'] == 0 ? 'FREE' : '₦' . number_format($product['price']);
                    ?>
                            <div class="product-card-small" onclick="window.location.href='product/<?php echo $product['slug']; ?>'">
                                <div class="relative aspect-square overflow-hidden bg-slate-100">
                                    <img alt="Sweatshirt" class="w-full h-full object-cover" src="<?php echo htmlspecialchars($image_url); ?>" />
                                    <!-- <div class="absolute top-3 left-3 badge-orange">FASHION</div> -->
                                </div>
                                <div class="p-4">
                                    <div class="flex items-center justify-between mb-1">
                                        <p class="text-lg font-extrabold text-primary"><?php echo $price_display; ?></p>
                                        <button onclick="bookmarkProduct(event, <?php echo $product['id']; ?>)" class="text-slate-400 hover:text-primary transition-colors" type="button">
                                            <span class="material-symbols-outlined text-lg">bookmark</span>
                                        </button>
                                    </div>
                                    <h4 class="text-slate-800 font-semibold text-sm mb-1 line-clamp-1"><?php echo htmlspecialchars($product['title']); ?></h4>
                                    <p class="text-xs text-slate-500 mb-3">Seller: <span class="font-medium text-slate-700"><?php echo htmlspecialchars($product['seller_name']); ?></span>
                                        <?php if (!empty($product['is_verified'])): ?><span class="material-symbols-outlined text-blue-500 text-xs fill-1 align-middle" title="Verified Seller">verified</span><?php endif; ?>
                                    </p>
                                 
                                </div>
                            </div>
                         
                    <?php endwhile;
                   ?>
                </div>
            </section>
            <?php  endif; 
            if ($recommended_products && $recommended_products->num_rows > 0): ?>
            <section class="mb-12">
                <div class="flex items-end justify-between mb-6">
                    <div class="flex items-center gap-3">
                        <div>
                            <h2 class="text-xl font-extrabold text-primary">Recommended for You</h2>
                            <p class="text-slate-500 text-sm mt-1">Picked by AI based on what you've viewed, saved and searched</p>
                        </div>
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-gradient-to-r from-primary/10 to-blue-500/10 border border-primary/20 text-primary text-[11px] font-bold">
                            <span class="material-symbols-outlined text-sm">auto_awesome</span> AI
                        </span>
                    </div>
                    <a class="text-sm font-bold text-primary flex items-center gap-1 hover:underline" href="products.php?sort=popular">View all</a>
                </div>
                <div class="product-row">
                    <?php
                    while ($product = $recommended_products->fetch_assoc()):
                        $image_url = $product['image_url'] ?: 'https://via.placeholder.com/400x400?text=No+Image';
                        $price_display = $product['price'] == 0 ? 'FREE' : '₦' . number_format($product['price']);
                    ?>
                        <div class="product-card-small" onclick="window.location.href='product/<?php echo $product['slug']; ?>'">
                            <div class="relative aspect-square overflow-hidden bg-slate-100">
                                <img alt="<?php echo htmlspecialchars($product['title']); ?>" class="w-full h-full object-cover" src="<?php echo htmlspecialchars($image_url); ?>" />
                            </div>
                            <div class="p-4">
                                <div class="flex items-center justify-between mb-1">
                                    <p class="text-lg font-extrabold text-primary"><?php echo $price_display; ?></p>
                                    <button onclick="bookmarkProduct(event, <?php echo $product['id']; ?>)" class="text-slate-400 hover:text-primary transition-colors" type="button">
                                        <span class="material-symbols-outlined text-lg">bookmark</span>
                                    </button>
                                </div>
                                <h4 class="text-slate-800 font-semibold text-sm mb-1 line-clamp-1"><?php echo htmlspecialchars($product['title']); ?></h4>
                                <p class="text-xs text-slate-500 mb-3">Seller: <span class="font-medium text-slate-700"><?php echo htmlspecialchars($product['seller_name']); ?></span>
                                    <?php if (!empty($product['is_verified'])): ?><span class="material-symbols-outlined text-blue-500 text-xs fill-1 align-middle" title="Verified Seller">verified</span><?php endif; ?>
                                </p>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            </section>
            <?php endif;
            if (isLoggedIn()): 
                // Get bookmarked items for logged-in user
                $bookmarks_query = "
                    SELECT p.*, u.full_name as seller_name, u.is_verified, pi.image_url, c.name as category_name
                    FROM bookmarks b
                    JOIN products p ON b.product_id = p.id
                    JOIN users u ON p.user_id = u.id
                    LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = TRUE
                    LEFT JOIN categories c ON p.category_id = c.id
                    WHERE b.user_id = {$userId} 
                    AND p.status = 'approved' 
                    AND p.availability = 'available'
                    $university_filter
                    ORDER BY b.created_at DESC
                    LIMIT 10
                ";
                $bookmarks = $db->query($bookmarks_query);
                
                if ($bookmarks && $bookmarks->num_rows > 0):
            ?>
            <section class="mb-12">
                <div class="flex items-end justify-between mb-6">
                    <div>
                        <h2 class="text-xl font-extrabold text-primary">Saved Items</h2>
                        <p class="text-slate-500 text-sm mt-1">Things you've kept an eye on</p>
                    </div>
                    <a class="text-sm font-bold text-primary flex items-center gap-1 hover:underline" href="bookmarks.php">
                        View all <span class="material-symbols-outlined text-base">arrow_forward</span>
                    </a>
                </div>
                <div class="product-row">
                    <?php while ($product = $bookmarks->fetch_assoc()): 
                        $image_url = $product['image_url'] ?: 'https://via.placeholder.com/400x400?text=No+Image';
                        $price_display = $product['price'] == 0 ? 'FREE' : '₦' . number_format($product['price']);
                    ?>
                    <div class="product-card-small" onclick="window.location.href='product/<?php echo $product['slug']; ?>'">
                        <div class="relative aspect-square overflow-hidden bg-slate-100">
                            <img alt="<?php echo htmlspecialchars($product['title']); ?>" class="w-full h-full object-cover" src="<?php echo htmlspecialchars($image_url); ?>" />
                            <!-- <div class="absolute top-3 left-3 badge-orange"><?php echo strtoupper(htmlspecialchars($product['category_name'])); ?></div> -->
                        </div>
                        <div class="p-4">
                            <div class="flex items-center justify-between mb-1">
                                <p class="text-lg font-extrabold text-primary"><?php echo $price_display; ?></p>
                                <button onclick="bookmarkProduct(event, <?php echo $product['id']; ?>)" class="text-primary transition-colors" type="button">
                                    <span class="material-symbols-outlined text-lg" style="font-variation-settings:'FILL' 1">bookmark</span>
                                </button>
                            </div>
                            <h4 class="text-slate-800 font-semibold text-sm mb-1 line-clamp-1"><?php echo htmlspecialchars($product['title']); ?></h4>
                            <p class="text-xs text-slate-500 mb-3">Seller: <span class="font-medium text-slate-700"><?php echo htmlspecialchars($product['seller_name']); ?></span>
                                <?php if (!empty($product['is_verified'])): ?><span class="material-symbols-outlined text-blue-500 text-xs fill-1 align-middle" title="Verified Seller">verified</span><?php endif; ?>
                            </p>
                           
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </section>
            <?php endif; endif;
            
                 if ($services && $services->num_rows > 0):
                 ?>
            <section class="mb-12">
                <div class="flex items-end justify-between mb-6">
                    <div>
                        <h2 class="text-xl font-extrabold text-primary">Campus Services</h2>
                        <p class="text-slate-500 text-sm mt-1">Talented students offering professional help</p>
                    </div>
                    <a class="text-sm font-bold text-primary flex items-center gap-1 hover:underline" href="services.php">
                        View all <span class="material-symbols-outlined text-base">arrow_forward</span>
                    </a>
                </div>
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                    <?php
               
                        $service_colors = ['primary', 'slate-900', 'secondary'];
                        $badge_colors = ['accent', 'blue-500', 'white'];
                        $i = 0;
                        while ($service = $services->fetch_assoc()):
                            $bg_color = $service_colors[$i % 3];
                            $badge_color = $badge_colors[$i % 3];
                            $base_price = $service['price'] ?? 0;
                            $pricing_type = $service['pricing_type'] ?? 'fixed';
                            $price_display = $pricing_type == 'hourly' ? '₦' . number_format($base_price) . '/hr' : 'From ₦' . number_format($base_price);
                            $i++;
                    ?>
                            <div class="relative overflow-hidden bg-<?php echo $bg_color; ?> rounded-3xl p-4 text-white group cursor-pointer hover:scale-105 transition-transform" onclick="window.location.href='<?php echo serviceUrl($service['slug']); ?>'">
                                <div class="absolute top-0 right-0 w-64 h-64 bg-accent/10 rounded-full -mr-20 -mt-20 blur-3xl group-hover:bg-accent/20 transition-all"></div>
                                <div class="relative z-10 flex items-start justify-between mb-2">
                                    <div class="size-16 rounded-2xl overflow-hidden border-2 border-white/20">
                                        <img alt="<?php echo htmlspecialchars($service['title']); ?>" class="w-full h-full object-cover" src="<?php echo htmlspecialchars($service['profile_image'] ?: 'https://via.placeholder.com/100'); ?>" />
                                    </div>
                                    <!-- <span class="bg-<?php echo $badge_color; ?> text-<?php echo $bg_color == 'secondary' ? 'secondary' : 'primary'; ?> px-4 py-1.5 rounded-full text-[10px] font-black tracking-widest uppercase"><?php echo htmlspecialchars($service['category_name']); ?></span> -->
                                </div>
                                <div class="relative z-10">
                                    <h3 class="text-xl font-bold mb-3"><?php echo htmlspecialchars($service['title']); ?></h3>
                                    <p class="text-white text-sm mb-4 max-w-sm"><?php echo htmlspecialchars(substr($service['description'], 0, 120)); ?>...</p>
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <p class="text-[10px] text-slate-100 font-bold uppercase tracking-wider mb-1"><?php echo $service['pricing_type'] == 'hourly' ? 'Rate' : 'Starting from'; ?></p>
                                            <p class="text-2xl font-black text-accent"><?php echo $price_display; ?></p>
                                        </div>
                                        <!-- <button class="bg-white text-primary px-4 py-3 rounded-xl font-bold hover:bg-accent transition-colors" onclick="event.stopPropagation(); window.location.href='<?php echo serviceUrl($service['slug']); ?>'">View Details</button> -->
                                    </div>
                                </div>
                            </div>
                    <?php endwhile;
                    ?>
                </div>
            </section>
            <?php  endif;
              if ($lost_found && $lost_found->num_rows > 0): ?>
            <section class="mb-12">
                <div class="flex items-end justify-between mb-6">
                    <div>
                        <h2 class="text-xl font-extrabold text-primary">Lost &amp; Found</h2>
                        <p class="text-slate-500 text-sm mt-1">Reuniting students with their belongings</p>
                    </div>
                    <a class="text-sm font-bold text-secondary flex items-center gap-1 hover:underline" href="#">View all</a>
                </div>
                <div class="product-row">
                    <?php
                  
                        while ($item = $lost_found->fetch_assoc()):
                            $item_type = $item['type'] ?? 'lost';
                            $status_badge = $item_type == 'found' ? 'FOUND' : 'LOST';
                            $badge_color = $item_type == 'found' ? 'bg-green-500' : 'bg-red-500';
                            $image_url = $item['image_url'] ?: 'https://via.placeholder.com/400x400?text=No+Image';
                    ?>
                            <div class="product-card-small">
                                <div class="relative aspect-square overflow-hidden bg-slate-100">
                                    <img alt="<?php echo htmlspecialchars($item['title']); ?>" class="w-full h-full object-cover" src="<?php echo htmlspecialchars($image_url); ?>" />
                                    <div class="absolute top-3 left-3 <?php echo $badge_color; ?> text-white px-2.5 py-1 rounded-lg text-xs font-black uppercase"><?php echo $status_badge; ?></div>
                                </div>
                                <div class="p-4">
                                    <p class="text-lg font-extrabold text-primary">FREE</p>
                                    <h4 class="text-slate-800 font-semibold text-sm mb-1 line-clamp-1"><?php echo htmlspecialchars($item['title']); ?></h4>
                                    <p class="text-xs text-slate-500 mb-3"><?php echo $item_type == 'found' ? 'Found' : 'Lost'; ?> by: <span class="font-medium text-slate-700"><?php echo htmlspecialchars($item['reporter_name']); ?></span></p>
                                    <div class="grid grid-cols-2 gap-2">
                                        <a href="<?php echo SITE_URL; ?>chat.php?seller_id=<?php echo $item['reporter_user_id']; ?>" class="bg-primary text-white py-2 rounded-lg text-xs font-bold col-span-2 hover:bg-opacity-90 transition-all flex items-center justify-center gap-1 no-underline">
                                            <span class="material-symbols-outlined text-sm">chat</span>
                                            Chat <?php echo $item_type == 'found' ? 'Finder' : 'Reporter'; ?>
                                        </a>
                                    </div>
                                </div>
                            </div>
                    <?php endwhile;
                    ?>
                </div>
            </section>
            <?php endif; 
              if ($sponsored_sidebar && $sponsored_sidebar->num_rows > 0): ?>
            <section class="mb-12" style="display: none;">
                <div class="flex items-end justify-between mb-6">
                    <div>
                        <h2 class="text-xl font-extrabold text-primary">Top Sellers</h2>
                        <p class="text-slate-500 text-sm mt-1">Special offers from local campus partners</p>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                    <?php
                  
                        while ($sponsor = $sponsored_sidebar->fetch_assoc()):
                    ?>
                            <div class="bg-slate-50 rounded-2xl p-8 border border-slate-200 flex flex-col md:flex-row gap-6 items-center">
                                <div class="w-full md:w-48 h-32 rounded-xl overflow-hidden shrink-0">
                                    <img alt="Sponsor" class="w-full h-full object-cover" src="<?php echo htmlspecialchars($sponsor['image_url'] ?: 'https://via.placeholder.com/300x150'); ?>" />
                                </div>
                                <div>
                                    <span class="text-[10px] font-black tracking-widest text-slate-400 uppercase">Featured Merchant</span>
                                    <h3 class="text-xl font-bold text-primary mt-1"><?php echo htmlspecialchars($sponsor['title']); ?></h3>
                                    <p class="text-slate-600 text-sm mt-2"><?php echo htmlspecialchars(substr($sponsor['description'], 0, 100)); ?>...</p>
                                    <button class="mt-4 text-secondary font-bold text-sm flex items-center gap-1" onclick="window.location.href='<?php echo htmlspecialchars($sponsor['target_url']); ?>'">Learn More <span class="material-symbols-outlined text-sm">open_in_new</span></button>
                                </div>
                            </div>
                    <?php endwhile;
                    ?>
                </div>
            </section>
            <?php  endif; 
             if ($free_items && $free_items->num_rows > 0): ?>
            <section class="mb-12 bg-slate-50 -mx-6 px-6 py-10 border-y border-slate-100">
                <div class="max-w-[1440px] mx-auto">
                    <div class="flex items-end justify-between mb-6">
                        <div>
                            <h2 class="text-xl font-extrabold text-primary">Free for Students</h2>
                            <p class="text-slate-500 text-sm mt-1">Community support: items available at zero cost</p>
                        </div>
                    </div>
                    <div class="product-grid">
                        <?php
                      
                            while ($item = $free_items->fetch_assoc()):
                                $image_url = $item['image_url'] ?: 'https://via.placeholder.com/400x300?text=No+Image';
                        ?>
                                <div class="product-card border-none shadow-sm" onclick="window.location.href='product/<?php echo $item['slug']; ?>'">
                                    <div class="relative aspect-video overflow-hidden">
                                        <img alt="<?php echo htmlspecialchars($item['title']); ?>" class="w-full h-full object-cover" src="<?php echo htmlspecialchars($image_url); ?>" />
                                        <div class="absolute inset-0 bg-primary/10"></div>
                                        <div class="absolute top-3 left-3 bg-accent text-primary px-3 py-1 rounded-lg text-[10px] font-black tracking-widest uppercase">Free Gift</div>
                                    </div>
                                    <div class="p-1 md:p-2">
                                        <h4 class="font-bold text-slate-800 mb-1"><?php echo htmlspecialchars($item['title']); ?></h4>
                                        <p class="text-xs text-slate-500 mb-3">Donated by: <span class="font-medium text-slate-700"><?php echo htmlspecialchars($item['donor_name']); ?></span></p>
                                        <p class="text-xs text-slate-500 flex items-center gap-1 mb-4">
                                            <span class="material-symbols-outlined text-sm">location_on</span> <?php echo htmlspecialchars($item['location'] ?: 'Campus'); ?>
                                        </p>
                                        <!-- <button class="w-full btn-primary text-sm py-2.5">Claim This Item</button> -->
                                    </div>
                                </div>
                        <?php endwhile;
                        ?>
                    </div>
                </div>
            </section>
            <?php  endif; ?>
            <section class="mb-16">
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                    <div class="relative group overflow-hidden rounded-3xl bg-slate-100 p-6 transition-all hover:shadow-xl">
                        <div class="relative z-10">
                            <h3 class="text-3xl font-extrabold text-primary mb-4">Looking for something?</h3>
                            <p class="text-slate-600 mb-8 max-w-sm">Discover thousands of unique items from your peers at unbeatable student prices.</p>
                            <button class="btn-primary"  onclick="window.location.href='products.php'">Browse All Items</button>
                        </div>
                        <span class="material-symbols-outlined absolute -bottom-8 -right-8 text-[120px] text-primary/5 group-hover:scale-110 transition-transform">search</span>
                    </div>
                    <div class="relative group overflow-hidden rounded-3xl bg-primary p-6 transition-all hover:shadow-xl">
                        <div class="relative z-10">
                            <h3 class="text-3xl font-extrabold text-white mb-4">Have something to sell?</h3>
                            <p class="text-slate-200 mb-8 max-w-sm">Turn your unused gadgets, textbooks, or clothes into extra cash today. It's fast and easy.</p>
                            <button class="btn-secondary" onclick="window.location.href='my-products.php'">Post a Listing</button>
                        </div>
                        <span class="material-symbols-outlined absolute -bottom-8 -right-8 text-[120px] text-white/5 group-hover:scale-110 transition-transform">sell</span>
                    </div>
                </div>
            </section>

<?php  if ($sponsored_grid && $sponsored_grid->num_rows > 0):  ?>
            <section class="mb-12">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-sm font-bold text-slate-400 tracking-widest uppercase">Sponsored Partners</h3>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <?php
                   
                        while ($ad = $sponsored_grid->fetch_assoc()):
                            $image_url = $ad['image_url'] ?: 'https://via.placeholder.com/400x225';
                    ?>
                            <div class="relative group cursor-pointer" onclick="window.location.href='<?php echo htmlspecialchars($ad['target_url']); ?>'">
                                <div class="absolute top-2 left-2 z-10 bg-slate-900/40 backdrop-blur-md text-[9px] font-black text-white px-2 py-0.5 rounded border border-white/10 uppercase tracking-tighter">Ad</div>
                                <div class="aspect-[16/9] rounded-2xl overflow-hidden mb-3">
                                    <img alt="<?php echo htmlspecialchars($ad['title']); ?>" class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500" src="<?php echo htmlspecialchars($image_url); ?>" />
                                </div>
                                <h4 class="font-bold text-slate-800 text-sm"><?php echo htmlspecialchars($ad['title']); ?></h4>
                                <p class="text-xs text-slate-500"><?php echo htmlspecialchars(substr($ad['description'], 0, 50)); ?>...</p>
                            </div>
                    <?php endwhile;
                   ?>
                </div>
            </section>
            <?php  endif;  ?>
        </div>
    </main>

    <?php include 'includes/footer.php'; ?>

    <script>
        // Bookmark product function
        function bookmarkProduct(event, productId) {
            event.preventDefault();
            event.stopPropagation();

            const button = event.currentTarget;
            const icon = button.querySelector('.material-symbols-outlined');

            fetch('api/bookmark.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ product_id: productId, action: 'toggle' })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        if (data.bookmarked) {
                            button.classList.remove('text-slate-400');
                            button.classList.add('text-primary');
                            icon.style.fontVariationSettings = "'FILL' 1";
                        } else {
                            button.classList.remove('text-primary');
                            button.classList.add('text-slate-400');
                            icon.style.fontVariationSettings = "'FILL' 0";
                        }
                        showToast(data.bookmarked ? 'Product bookmarked!' : 'Bookmark removed', data.bookmarked ? 'success' : 'info');
                    } else {
                        showToast(data.message || 'Please login to bookmark products', 'error');
                    }
                })
                .catch(() => showToast('Failed to update bookmark. Please try again.', 'error'));
        }

        function showToast(message, type) {
            const existing = document.querySelector('.toast-notification');
            if (existing) existing.remove();

            const colors = { success: 'bg-emerald-600', error: 'bg-red-600', info: 'bg-slate-700' };
            const icons = { success: 'check_circle', error: 'error', info: 'info' };

            const toast = document.createElement('div');
            toast.className = 'toast-notification fixed top-5 right-5 z-[9999] flex items-center gap-3 px-5 py-3 rounded-2xl text-white text-sm font-semibold shadow-2xl ' + (colors[type] || 'bg-slate-700');
            toast.style.transform = 'translateX(120%)';
            toast.style.transition = 'transform 0.3s ease';
            toast.innerHTML = '<span class="material-symbols-outlined text-lg">' + (icons[type] || 'info') + '</span><span>' + message + '</span>';

            document.body.appendChild(toast);
            requestAnimationFrame(() => { toast.style.transform = 'translateX(0)'; });

            setTimeout(() => {
                toast.style.transform = 'translateX(120%)';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // Track product view
        function trackView(productId) {
            fetch('api/track-view.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    product_id: productId
                })
            });
        }

        // Track ad click
        function trackAdClick(adId) {
            fetch('api/track-ad-click.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    ad_id: adId
                })
            });
        }

        // Flash Sale Countdown Timer
        const flashSaleBanner = document.getElementById('flash-sale-banner');
        if (flashSaleBanner) {
            const endTime = parseInt(flashSaleBanner.dataset.endTime) * 1000; // Convert to milliseconds

            function updateCountdown() {
                const now = new Date().getTime();
                const distance = endTime - now;

                if (distance < 0) {
                    // Sale has ended, hide banner or show expired message
                    flashSaleBanner.innerHTML = '<div class="p-10 text-center"><p class="text-white text-2xl font-bold">Flash Sale Has Ended</p><p class="text-slate-200 mt-2">Check back soon for more amazing deals!</p></div>';
                    return;
                }

                const hours = Math.floor((distance % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((distance % (1000 * 60)) / 1000);

                document.getElementById('flash-hours').textContent = String(hours).padStart(2, '0');
                document.getElementById('flash-minutes').textContent = String(minutes).padStart(2, '0');
                document.getElementById('flash-seconds').textContent = String(seconds).padStart(2, '0');
            }

            // Update immediately and then every second
            updateCountdown();
            setInterval(updateCountdown, 1000);
        }
    </script>

</body>

</html>