<?php
session_start();
require_once 'includes/controller.php';

// Get product slug from URL
$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    header("Location: " . SITE_URL . "index.php");
    exit;
}

// Fetch product details with all related data
$product_query = "
    SELECT p.*, 
           u.username as seller_username, 
           u.is_verified, 
           u.full_name as seller_name,
           u.profile_image as seller_image,
           u.rating as seller_rating,
           u.total_sales as seller_total_sales,
           u.location as seller_location,
           u.created_at as seller_created_at,
           c.name as category_name,
           c.icon as category_icon,
           (SELECT COUNT(*) FROM bookmarks WHERE product_id = p.id) as bookmark_count,
           0 as review_count,
           0 as avg_rating
    FROM products p
    JOIN users u ON p.user_id = u.id
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.slug = ? 
    AND p.status = 'approved'
    LIMIT 1
";

$stmt = $db->prepare($product_query);
$stmt->bind_param("s", $slug);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();

if (!$product) {
    header("Location: " . SITE_URL . "index.php");
    exit;
}

// Get product images
$images_query = "SELECT * FROM product_images WHERE product_id = ? ORDER BY is_primary DESC, display_order ASC";
$stmt = $db->prepare($images_query);
$stmt->bind_param("i", $product['id']);
$stmt->execute();
$images_result = $stmt->get_result();
$images = [];
while ($img = $images_result->fetch_assoc()) {
    $images[] = $img;
}

// Get primary image or use placeholder
$primary_image = !empty($images) ? $images[0]['image_url'] : 'https://via.placeholder.com/800x600?text=No+Image';

// Get related products (same category, exclude current)
$related_query = "
    SELECT p.*, pi.image_url,
           (SELECT COUNT(*) FROM bookmarks WHERE product_id = p.id) as bookmark_count
    FROM products p
    LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = TRUE
    WHERE p.category_id = ? 
    AND p.id != ?
    AND p.status = 'approved'
    AND p.availability = 'available'
    ORDER BY p.created_at DESC
    LIMIT 6
";
$stmt = $db->prepare($related_query);
$stmt->bind_param("ii", $product['category_id'], $product['id']);
$stmt->execute();
$related_products = $stmt->get_result();

// Get seller's other products
$seller_products_query = "
    SELECT p.*, pi.image_url
    FROM products p
    LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = TRUE
    WHERE p.user_id = ? 
    AND p.id != ?
    AND p.status = 'approved'
    AND p.availability = 'available'
    ORDER BY p.created_at DESC
    LIMIT 4
";
$stmt = $db->prepare($seller_products_query);
$stmt->bind_param("ii", $product['user_id'], $product['id']);
$stmt->execute();
$seller_products = $stmt->get_result();

// Track unique logged-in viewers (exclude the seller)
$viewerUserId = $_SESSION['userAppId'] ?? null;
if ($viewerUserId && $viewerUserId != $product['user_id']) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? null;
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $check_view = "SELECT id FROM product_views WHERE product_id = ? AND user_id = ? LIMIT 1";
    $stmt = $db->prepare($check_view);
    $stmt->bind_param("ii", $product['id'], $viewerUserId);
    $stmt->execute();
    $existing_view = $stmt->get_result()->fetch_assoc();

    if ($existing_view) {
        $update_view = "UPDATE product_views SET ip_address = ?, user_agent = ?, viewed_at = NOW() WHERE id = ?";
        $stmt = $db->prepare($update_view);
        $stmt->bind_param("ssi", $ip, $userAgent, $existing_view['id']);
        $stmt->execute();
    } else {
        $log_view = "INSERT INTO product_views (product_id, user_id, ip_address, user_agent) VALUES (?, ?, ?, ?)";
        $stmt = $db->prepare($log_view);
        $stmt->bind_param("iiss", $product['id'], $viewerUserId, $ip, $userAgent);
        $stmt->execute();
    }

    $refresh_views = "
        UPDATE products
        SET views_count = (
            SELECT COUNT(DISTINCT pv.user_id)
            FROM product_views pv
            WHERE pv.product_id = ?
            AND pv.user_id IS NOT NULL
        )
        WHERE id = ?
    ";
    $stmt = $db->prepare($refresh_views);
    $stmt->bind_param("ii", $product['id'], $product['id']);
    $stmt->execute();
}

// Check if user has bookmarked this product
$is_bookmarked = false;
if (isset($_SESSION['userAppId'])) {
    $bookmark_check = "SELECT id FROM bookmarks WHERE user_id = ? AND product_id = ?";
    $currentUserId = (int) $_SESSION['userAppId'];
    $stmt = $db->prepare($bookmark_check);
    $stmt->bind_param("ii", $currentUserId, $product['id']);
    $stmt->execute();
    $is_bookmarked = $stmt->get_result()->num_rows > 0;
}

// SEO metadata
$page_title = htmlspecialchars($product['title']) . ' - CampMart';
$page_description = substr(strip_tags($product['description']), 0, 160);

// Generate absolute URLs for social media
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$page_url = SITE_URL . 'product/' . htmlspecialchars($product['slug']);
$canonical_url = $page_url;
$seller_store_url = storeUrl($product['seller_username']);

// Ensure primary image is absolute URL
$primary_image_absolute = $primary_image;
if (!filter_var($primary_image, FILTER_VALIDATE_URL)) {
    $primary_image_absolute = $protocol . $host . '/' . ltrim($primary_image, '/');
}

// Structured data for SEO
$structured_data = [
    "@context" => "https://schema.org/",
    "@type" => "Product",
    "name" => $product['title'],
    "image" => $primary_image_absolute,
    "description" => strip_tags($product['description']),
    "sku" => $product['id'],
    "brand" => [
        "@type" => "Brand",
        "name" => $product['seller_name']
    ],
    "offers" => [
        "@type" => "Offer",
        "url" => $page_url,
        "priceCurrency" => "NGN",
        "price" => $product['price'],
        "priceValidUntil" => $product['expires_at'] ?? date('Y-m-d', strtotime('+30 days')),
        "itemCondition" => "https://schema.org/" . ucfirst($product['condition_type']) . "Condition",
        "availability" => $product['availability'] == 'available' ? "https://schema.org/InStock" : "https://schema.org/OutOfStock",
        "seller" => [
            "@type" => "Organization",
            "name" => $product['seller_name']
        ]
    ]
];

if ($product['avg_rating']) {
    $structured_data["aggregateRating"] = [
        "@type" => "AggregateRating",
        "ratingValue" => $product['avg_rating'],
        "reviewCount" => $product['review_count']
    ];
}
?>
<!DOCTYPE html>
<html class="light" lang="en">

<head>
    <meta charset="utf-8" />
    <base href="<?php echo SITE_URL; ?>">
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    
    <!-- SEO Meta Tags -->
    <title><?php echo htmlspecialchars($product['title']); ?> - Buy <?php echo ucfirst($product['condition_type']); ?> | CampMart</title>
    <meta name="description" content="<?php echo htmlspecialchars($page_description); ?>. Listed by <?php echo htmlspecialchars($product['seller_name']); ?> on CampMart - Nigeria's trusted campus marketplace." />
    <meta name="keywords" content="<?php echo htmlspecialchars($product['title']); ?>, <?php echo htmlspecialchars($product['category_name']); ?>, <?php echo $product['condition_type']; ?>, buy online, campus marketplace, student marketplace, Nigeria" />
    <meta name="author" content="<?php echo htmlspecialchars($product['seller_name']); ?>" />
    <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1" />
    <link rel="canonical" href="<?php echo htmlspecialchars($canonical_url); ?>" />
    
    <!-- Open Graph / Facebook Meta Tags -->
    <meta property="og:type" content="product" />
    <meta property="og:url" content="<?php echo htmlspecialchars($page_url); ?>" />
    <meta property="og:title" content="<?php echo htmlspecialchars($product['title']); ?>" />
    <meta property="og:description" content="<?php echo htmlspecialchars($page_description); ?>" />
    <meta property="og:image" content="<?php echo htmlspecialchars($primary_image_absolute); ?>" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />
    <meta property="og:site_name" content="CampMart" />
    <meta property="og:locale" content="en_NG" />
    <meta property="product:price:amount" content="<?php echo $product['price']; ?>" />
    <meta property="product:price:currency" content="NGN" />
    <meta property="product:condition" content="<?php echo $product['condition_type']; ?>" />
    <meta property="product:availability" content="<?php echo $product['availability'] == 'available' ? 'in stock' : 'out of stock'; ?>" />
    <meta property="product:brand" content="<?php echo htmlspecialchars($product['seller_name']); ?>" />
    <meta property="product:category" content="<?php echo htmlspecialchars($product['category_name']); ?>" />
    
    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:url" content="<?php echo htmlspecialchars($page_url); ?>" />
    <meta name="twitter:title" content="<?php echo htmlspecialchars($product['title']); ?> - ₦<?php echo number_format($product['price'], 0); ?>" />
    <meta name="twitter:description" content="<?php echo htmlspecialchars($page_description); ?>" />
    <meta name="twitter:image" content="<?php echo htmlspecialchars($primary_image_absolute); ?>" />
    <meta name="twitter:label1" content="Price" />
    <meta name="twitter:data1" content="₦<?php echo number_format($product['price'], 0); ?>" />
    <meta name="twitter:label2" content="Condition" />
    <meta name="twitter:data2" content="<?php echo ucfirst($product['condition_type']); ?>" />
    
    <!-- Additional SEO Meta Tags -->
    <meta name="theme-color" content="#064E3B" />
    <meta name="mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-capable" content="yes" />
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent" />
    
    <!-- Structured Data (JSON-LD) -->
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
                        "secondary": "#F97316",
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
            .product-card {
                @apply bg-white rounded-xl border border-slate-200 overflow-hidden flex flex-col hover:shadow-lg transition-shadow duration-300;
            }
            .btn-primary {
                @apply bg-primary text-white px-6 py-3 rounded-lg font-bold hover:bg-opacity-90 transition-all flex items-center justify-center gap-2;
            }
            .btn-secondary {
                @apply bg-secondary text-white px-6 py-3 rounded-lg font-bold hover:bg-opacity-90 transition-all flex items-center justify-center gap-2;
            }
            .btn-outline {
                @apply border border-slate-200 text-slate-700 px-6 py-3 rounded-lg font-bold hover:bg-slate-50 transition-all flex items-center justify-center gap-2;
            }
            .tab-active {
                @apply border-b-2 border-primary text-primary font-bold;
            }
            .tab-inactive {
                @apply text-slate-500 hover:text-slate-800 transition-colors;
            }
            .scrollbar-hide::-webkit-scrollbar {
                display: none;
            }
            .scrollbar-hide {
                -ms-overflow-style: none;
                scrollbar-width: none;
            }
            input[type='number']::-webkit-inner-spin-button,
            input[type='number']::-webkit-outer-spin-button {
                -webkit-appearance: none;
                margin: 0;
            }
        }
    </style>
</head>

<body class="bg-white font-display text-slate-900 antialiased">
    <?php include_once 'includes/header.php'; ?>
    <main class="max-w-[1440px] mx-auto px-6 py-4">
        <nav class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-4">
            <a class="hover:text-primary" href="<?php echo SITE_URL; ?>index.php">Home</a>
            <span class="material-symbols-outlined text-sm">chevron_right</span>
            <a class="hover:text-primary" href="<?php echo SITE_URL; ?>products.php?category=<?php echo $product['category_id']; ?>"><?php echo htmlspecialchars($product['category_name']); ?></a>
            <span class="material-symbols-outlined text-xs sm:text-sm">chevron_right</span>
            <span class="text-xs sm:text-sm text-slate-900"><?php echo htmlspecialchars($product['title']); ?></span>
        </nav>
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-12">
            <div class="lg:col-span-7">
                <div class="aspect-[4/3] rounded-2xl overflow-hidden bg-slate-100 border border-slate-100 mb-4">
                    <img alt="<?php echo htmlspecialchars($product['title']); ?>" class="w-full h-full object-cover" src="<?php echo htmlspecialchars($primary_image); ?>" />
                </div>

                <div class="mt-6">
                    <div class="flex border-b border-slate-200 mb-6">
                        <button class="px-4 sm:px-8 py-2 sm:py-4 text-sm sm:text-base tab-active">Description</button>
                        <!-- <button class="px-8 py-4 tab-inactive">Specifications</button> -->
                        <!-- <button class="px-8 py-4 tab-inactive">Seller Reviews</button> -->
                    </div>
                    <div class="prose prose-sm sm:prose prose-slate max-w-none">
                        <p class="text-xs sm:text-sm md:text-base text-slate-600 leading-relaxed mb-6">
                            <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="lg:col-span-5 space-y-6">
                <div class="border-b border-slate-100 pb-6">
                   
                    <h3 class="text-lg sm:text-xl md:text-2xl font-extrabold text-slate-900 mb-4"><?php echo htmlspecialchars($product['title']); ?></h3>
                    <!-- Product Tags -->
                    <div class="flex flex-wrap gap-2 mb-4">
                        <span class="inline-flex items-center gap-1 px-2 sm:px-3 py-0.5 sm:py-1 bg-primary/5 text-primary text-[11px] sm:text-xs font-semibold rounded-full border border-primary/10">
                            <span class="material-symbols-outlined text-xs sm:text-sm"><?php echo htmlspecialchars($product['category_icon'] ?: 'sell'); ?></span>
                            <?php echo htmlspecialchars($product['category_name']); ?>
                        </span>
                        <span class="inline-flex items-center gap-1 px-2 sm:px-3 py-0.5 sm:py-1 bg-slate-100 text-slate-700 text-[11px] sm:text-xs font-semibold rounded-full">
                            <span class="material-symbols-outlined text-xs sm:text-sm">info</span>
                            <?php echo ucfirst(str_replace('_', ' ', $product['condition_type'])); ?>
                        </span>
                        <?php if ($product['is_featured']): ?>
                            <span class="inline-flex items-center gap-1 px-2 sm:px-3 py-0.5 sm:py-1 bg-amber-50 text-amber-700 text-[11px] sm:text-xs font-semibold rounded-full border border-amber-200">
                                <span class="material-symbols-outlined text-xs sm:text-sm">workspace_premium</span>
                                Featured
                            </span>
                        <?php endif; ?>
                        <?php if ($product['is_urgent']): ?>
                            <span class="inline-flex items-center gap-1 px-2 sm:px-3 py-0.5 sm:py-1 bg-red-50 text-red-700 text-[11px] sm:text-xs font-semibold rounded-full border border-red-200">
                                <span class="material-symbols-outlined text-xs sm:text-sm">priority_high</span>
                                Urgent
                            </span>
                        <?php endif; ?>

                         <div class="flex items-center gap-1 sm:gap-2">
                            <button onclick="shareProduct()" class="px-1 sm:px-2 rounded-lg text-slate-400 hover:text-primary hover:bg-primary/5 transition-colors" title="Share this product">
                                <span class="material-symbols-outlined text-lg sm:text-xl">share</span>
                            </button>
                            <button onclick="toggleBookmark(<?php echo $product['id']; ?>)" id="bookmarkBtn" class="px-1 sm:px-2 rounded-lg <?php echo $is_bookmarked ? 'text-red-500' : 'text-slate-400'; ?> hover:text-red-500 hover:bg-red-50 transition-colors" title="Add to favorites">
                                <span class="material-symbols-outlined text-lg sm:text-xl <?php echo $is_bookmarked ? 'fill-1' : ''; ?>">favorite</span>
                            </button>
                        </div>
                    </div>
                    <div class="flex items-end gap-3">
                        <?php if ($product['price'] > 0): ?>
                            <span class="text-lg sm:text-xl md:text-2xl font-black text-primary">₦<?php echo number_format($product['price'], 0); ?></span>
                            <?php if ($product['original_price'] && $product['original_price'] > $product['price']): ?>
                                <span class="text-slate-400 line-through mb-1 text-xs sm:text-sm md:text-lg">₦<?php echo number_format($product['original_price'], 0); ?></span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-lg sm:text-xl md:text-2xl font-black text-primary">FREE</span>
                        <?php endif; ?>
                    </div>
                    
 
                </div>
                 <div class="flex items-center gap-2 sm:gap-6 py-2">
                    <span class="text-xs sm:text-sm font-bold text-slate-700 uppercase tracking-wider">Quantity</span>
                    <div class="flex items-center border border-slate-200 rounded-lg p-0.5 sm:p-1 bg-slate-50">
                        <button onclick="decreaseQuantity()" class="size-8 sm:size-10 flex items-center justify-center rounded-md hover:bg-white hover:shadow-sm text-slate-600 transition-all">
                            <span class="material-symbols-outlined text-base sm:text-lg">remove</span>
                        </button>
                        <input id="quantityInput" class="w-8 sm:w-12 text-center bg-transparent border-none focus:ring-0 font-bold text-slate-900 text-sm" min="1" type="number" value="1" readonly />
                        <button onclick="increaseQuantity()" class="size-8 sm:size-10 flex items-center justify-center rounded-md hover:bg-white hover:shadow-sm text-slate-600 transition-all">
                            <span class="material-symbols-outlined text-base sm:text-lg">add</span>
                        </button>
                    </div>
                </div>
                <div class="bg-white rounded-2xl p-3 sm:p-6 border-2 border-slate-100 shadow-sm">
                    <div class="space-y-3">
                        <div class="grid grid-cols-2 gap-2 sm:gap-4">
                            <button onclick="buyNow()" class="btn-secondary !bg-secondary w-full h-9 sm:h-10 md:h-[52px] text-xs sm:text-sm" id="buyNowBtn">
                                <span class="material-symbols-outlined text-base sm:text-lg md:text-xl">shopping_cart_checkout</span>
                                <span class="">Buy Now</span>
                            </button>
                            <button onclick="addToCart()" class="btn-primary w-full h-9 sm:h-10 md:h-[52px] text-xs sm:text-sm" id="addToCartBtn">
                                <span class="material-symbols-outlined text-base sm:text-lg md:text-xl">add_shopping_cart</span>
                                <span class="">Add to Cart</span>
                            </button>
                        </div>
                        <p class="text-center text-[8px] sm:text-[10px] text-slate-400 uppercase tracking-widest font-bold">Secure Campus Transaction Guaranteed</p>
                    </div>
                </div> 
                <div class="bg-slate-50 rounded-2xl p-2 border border-slate-100">
                    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 sm:gap-2 mb-2">
                        <a href="<?php echo htmlspecialchars($seller_store_url); ?>" class="flex items-center gap-4 group min-w-0">
                            <div class="size-14 rounded-full overflow-hidden border-2 border-white shadow-sm shrink-0 bg-primary text-white flex items-center justify-center text-2xl font-bold">
                                <?php if ($product['seller_image']): ?>
                                    <img alt="<?php echo htmlspecialchars($product['seller_name']); ?>" class="w-full h-full object-cover" src="<?php echo htmlspecialchars($product['seller_image']); ?>" />
                                <?php else: ?>
                                    <?php echo strtoupper(substr($product['seller_name'], 0, 1)); ?>
                                <?php endif; ?>
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-1 mb-0.5">
                                    <h4 class="font-bold text-slate-900 group-hover:text-primary transition-colors truncate text-sm sm:text-base"><?php echo htmlspecialchars($product['seller_name']); ?></h4>
                                    <?php if ($product['is_verified']): ?>
                                        <span class="material-symbols-outlined text-blue-500 text-xs sm:text-sm fill-1 shrink-0">verified</span>
                                    <?php endif; ?>
                                </div>
                                <div class="flex items-center gap-1 text-xs text-slate-500">
                                    <?php if ($product['seller_rating']): ?>
                                        <span class="material-symbols-outlined text-xs text-orange-400 fill-1">star</span>
                                        <span class="font-bold"><?php echo number_format($product['seller_rating'], 1); ?></span>
                                    <?php endif; ?>
                                    <span>(<?php echo $product['seller_total_sales']; ?> sales)</span>
                                </div>
                            </div>
                        </a>
                        <div class="flex flex-wrap items-center gap-2 sm:gap-1">
                            <a href="<?php echo htmlspecialchars($seller_store_url); ?>" class="inline-flex items-center gap-1 rounded-lg bg-white px-2 sm:px-3 py-1 sm:py-2 text-[11px] sm:text-xs font-bold text-primary border border-slate-200 hover:border-primary/40 hover:bg-primary/5 transition-colors">
                                <span class="material-symbols-outlined text-xs sm:text-sm">storefront</span>
                                <span class="">View Store</span>
                            </a>
                            <a href="<?php echo SITE_URL; ?>chat.php?seller_id=<?php echo $product['user_id']; ?>&product_id=<?php echo $product['id']; ?>" class="inline-flex items-center gap-1 rounded-lg bg-white px-2 sm:px-3 py-1 sm:py-2 text-[11px] sm:text-xs font-bold text-primary border border-slate-200 hover:border-primary/40 hover:bg-primary/5 transition-colors">
                                <span class="material-symbols-outlined text-xs sm:text-sm">chat</span>
                                <span class="">Chat Seller</span>
                            </a>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 sm:gap-4 border-t border-slate-200 pt-2 sm:pt-3">
                        <div class="flex flex-col gap-1">
                            <span class="text-[9px] sm:text-[10px] font-bold text-slate-400 uppercase tracking-wider">Member since</span>
                            <span class="text-xs sm:text-sm font-semibold text-slate-700"><?php echo date('F Y', strtotime($product['seller_created_at'])); ?></span>
                        </div>
                        <div class="flex flex-col gap-1">
                            <span class="text-[9px] sm:text-[10px] font-bold text-slate-400 uppercase tracking-wider">Avg. Response</span>
                            <span class="text-xs sm:text-sm font-semibold text-slate-700">Under 1 hour</span>
                        </div>
                        <div class="flex flex-col gap-1">
                            <span class="text-[9px] sm:text-[10px] font-bold text-slate-400 uppercase tracking-wider">Location</span>
                            <span class="text-xs sm:text-sm font-semibold text-slate-700 truncate"><?php echo htmlspecialchars($product['seller_location'] ?? 'Not specified'); ?></span>
                        </div>
                    </div>
                    
                </div>
            </div>
        </div>
        <section class="mt-24">
            <div class="flex items-center justify-between mb-8">
                <h2 class="text-lg sm:text-xl font-extrabold text-primary">Similar Items You Might Like</h2>
                <div class="flex gap-2">
                    <button class="p-1 sm:p-2 border border-slate-200 rounded-full hover:bg-slate-50"><span class="material-symbols-outlined text-base sm:text-lg">chevron_left</span></button>
                    <button class="p-1 sm:p-2 border border-slate-200 rounded-full hover:bg-slate-50"><span class="material-symbols-outlined text-base sm:text-lg">chevron_right</span></button>
                </div>
            </div>
            <div class="flex gap-6 overflow-x-auto pb-6 scrollbar-hide">
                <?php if ($related_products && $related_products->num_rows > 0): ?> 
                    <?php while($related = $related_products->fetch_assoc()): 
                        $related_image = $related['image_url'] ?: 'https://via.placeholder.com/400x400?text=No+Image';
                        $related_price = $related['price'] == 0 ? 'FREE' : '₦' . number_format($related['price'], 0);
                    ?>
                    <div class="min-w-[220px] sm:min-w-[280px] product-card cursor-pointer hover:shadow-lg transition-shadow duration-300" onclick="window.location.href='<?php echo SITE_URL; ?>product/<?php echo $related['slug']; ?>'">
                        <div class="relative aspect-square overflow-hidden rounded-t-xl bg-slate-100">
                            <img alt="<?php echo htmlspecialchars($related['title']); ?>" class="w-full h-full object-cover" src="<?php echo htmlspecialchars($related_image); ?>" />
                            <?php if ($related['condition_type']): ?>
                            <div class="absolute top-2 sm:top-3 right-2 sm:right-3 bg-primary/90 text-white px-2 sm:px-2.5 py-0.5 sm:py-1 rounded-lg text-[10px] sm:text-xs font-bold">
                                <?php echo ucfirst(str_replace('_', ' ', $related['condition_type'])); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="p-2 sm:p-4 bg-white rounded-b-xl border border-t-0 border-slate-100">
                            <p class="text-base sm:text-lg font-extrabold text-primary mb-1"><?php echo $related_price; ?></p>
                            <h4 class="text-slate-800 font-semibold text-xs sm:text-sm mb-2 sm:mb-3 line-clamp-2"><?php echo htmlspecialchars($related['title']); ?></h4>
                            <div class="flex items-center justify-between text-[10px] sm:text-xs text-slate-400">
                                <span class="flex items-center gap-0.5 sm:gap-1">
                                    <span class="material-symbols-outlined text-xs sm:text-sm">visibility</span>
                                    <span class="hidden sm:inline"><?php echo number_format($related['views_count'] ?? 0); ?> views</span>
                                    <span class="sm:hidden"><?php echo number_format($related['views_count'] ?? 0); ?></span>
                                </span>
                                <span class="flex items-center gap-0.5 sm:gap-1">
                                    <span class="material-symbols-outlined text-xs sm:text-sm">bookmark</span>
                                    <?php echo number_format($related['bookmark_count'] ?? 0); ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="w-full text-center py-8">
                        <span class="material-symbols-outlined text-4xl text-slate-300 mb-2">search_off</span>
                        <p class="text-slate-500">No similar items found</p>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
    <?php include 'includes/footer.php'; ?>

    <script>
        const loginUrl = '<?php echo SITE_URL; ?>login.php';
        const cartPageUrl = '<?php echo SITE_URL; ?>cart.php';
        const bookmarkApiUrl = '<?php echo SITE_URL; ?>api/bookmark.php';
        const addToCartApiUrl = '<?php echo SITE_URL; ?>api/add-to-cart.php';

        // Delivery option selection
        let selectedDeliveryOption = 'pickup'; // Default to personal pickup
        const deliveryFee = 1500; // ₦1,500 for personal delivery

        // Share product
        function shareProduct() {
            const productTitle = "<?php echo addslashes($product['title']); ?>";
            const productUrl = window.location.href;
            const productPrice = "₦<?php echo number_format($product['price'], 0); ?>";

            if (navigator.share) {
                // Use native share API if available (mobile) for ${productPrice}
                navigator.share({
                    title: productTitle,
                    text: `Check out this ${productTitle} on CampMart!`,
                    url: productUrl
                }).catch(err => console.log('Share cancelled'));
            } else {
                // Fallback: copy to clipboard
                navigator.clipboard.writeText(productUrl).then(() => {
                    // Show success message
                    const btn = event.target.closest('button');
                    const originalIcon = btn.querySelector('.material-symbols-outlined').textContent;
                    btn.querySelector('.material-symbols-outlined').textContent = 'check_circle';
                    btn.classList.add('text-green-500');

                    setTimeout(() => {
                        btn.querySelector('.material-symbols-outlined').textContent = originalIcon;
                        btn.classList.remove('text-green-500');
                        btn.classList.add('text-slate-400');
                    }, 2000);
                }).catch(err => {
                    console.error('Failed to copy:', err);
                    alert('Failed to copy link');
                });
            }
        }

        // Tab switching
        function switchTab(tabName) {
            // Hide all content
            document.getElementById('descriptionContent').classList.add('hidden');
            document.getElementById('specsContent').classList.add('hidden');
            document.getElementById('sellerContent').classList.add('hidden');

            // Reset all tabs
            document.getElementById('descriptionTab').classList.remove('tab-active');
            document.getElementById('descriptionTab').classList.add('tab-inactive');
            document.getElementById('specsTab').classList.remove('tab-active');
            document.getElementById('specsTab').classList.add('tab-inactive');
            document.getElementById('sellerTab').classList.remove('tab-active');
            document.getElementById('sellerTab').classList.add('tab-inactive');

            // Show selected content and tab
            if (tabName === 'description') {
                document.getElementById('descriptionContent').classList.remove('hidden');
                document.getElementById('descriptionTab').classList.add('tab-active');
                document.getElementById('descriptionTab').classList.remove('tab-inactive');
            } else if (tabName === 'specs') {
                document.getElementById('specsContent').classList.remove('hidden');
                document.getElementById('specsTab').classList.add('tab-active');
                document.getElementById('specsTab').classList.remove('tab-inactive');
            } else if (tabName === 'seller') {
                document.getElementById('sellerContent').classList.remove('hidden');
                document.getElementById('sellerTab').classList.add('tab-active');
                document.getElementById('sellerTab').classList.remove('tab-inactive');
            }
        }

        // Quantity controls
        function increaseQuantity() {
            const input = document.getElementById('quantityInput');
            if (!input) return;
            input.value = parseInt(input.value) + 1;
        }

        function decreaseQuantity() {
            const input = document.getElementById('quantityInput');
            if (!input) return;
            if (parseInt(input.value) > 1) {
                input.value = parseInt(input.value) - 1;
            }
        }

        // Show notification
        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 px-6 py-4 rounded-lg shadow-lg border flex items-center gap-3 animate-slide-in ${
                type === 'success' ? 'bg-emerald-50 border-emerald-200 text-emerald-800' : 'bg-red-50 border-red-200 text-red-800'
            }`;
            notification.innerHTML = `
                <span class="material-symbols-outlined">${type === 'success' ? 'check_circle' : 'error'}</span>
                <span class="font-semibold">${message}</span>
            `;
            document.body.appendChild(notification);

            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transform = 'translateX(100%)';
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }

        async function toggleBookmark(productId) {
            const button = document.getElementById('bookmarkBtn');
            const icon = button ? button.querySelector('.material-symbols-outlined') : null;

            <?php if (!isset($_SESSION['userAppId'])): ?>
                showNotification('Please login to bookmark products', 'error');
                setTimeout(() => window.location.href = loginUrl, 1500);
                return;
            <?php endif; ?>

            try {
                const response = await fetch(bookmarkApiUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        product_id: productId,
                        action: 'toggle'
                    })
                });

                const data = await response.json();

                if (!data.success) {
                    showNotification(data.message || 'Failed to update bookmark', 'error');
                    return;
                }

                if (button && icon) {
                    button.classList.toggle('text-red-500', Boolean(data.bookmarked));
                    button.classList.toggle('text-slate-400', !data.bookmarked);
                    icon.classList.toggle('fill-1', Boolean(data.bookmarked));
                }

                showNotification(data.message, 'success');
            } catch (error) {
                console.error('Error:', error);
                showNotification('Failed to update bookmark. Please try again.', 'error');
            }
        }

        // Add to Cart function
        async function addToCart() {
            const btn = document.getElementById('addToCartBtn');
            const originalContent = btn ? btn.innerHTML : '';

            // Check if user is logged in
            <?php if (!isset($_SESSION['userAppId'])): ?>
                showNotification('Please login to add items to cart', 'error');
                setTimeout(() => window.location.href = loginUrl, 1500);
                return;
            <?php endif; ?>

            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="material-symbols-outlined animate-spin">sync</span>Adding...';
            }

            const formData = new FormData();
            formData.append('product_id', <?php echo $product['id']; ?>);
            formData.append('quantity', document.getElementById('quantityInput')?.value || 1);
            formData.append('buy_now', 'false');

            try {
                const response = await fetch(addToCartApiUrl, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showNotification(data.message, 'success');
                    if (btn) {
                        btn.innerHTML = '<span class="material-symbols-outlined">check</span>Added!';
                    }

                    // Update cart count in header if exists
                    const cartCount = document.querySelector('.cart-count');
                    if (cartCount && data.cart_count) {
                        cartCount.textContent = data.cart_count;
                    }

                    setTimeout(() => {
                        if (btn) {
                            btn.disabled = false;
                            btn.innerHTML = originalContent;
                        }
                    }, 2000);
                } else {
                    showNotification(data.message, 'error');
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = originalContent;
                    }
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Failed to add to cart. Please try again.', 'error');
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = originalContent;
                }
            }
        }

        // Buy Now function
        async function buyNow() {
            const btn = document.getElementById('buyNowBtn');
            const originalContent = btn ? btn.innerHTML : '';

            // Check if user is logged in
            <?php if (!isset($_SESSION['userAppId'])): ?>
                showNotification('Please login to continue', 'error');
                setTimeout(() => window.location.href = loginUrl, 1500);
                return;
            <?php endif; ?>

            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="material-symbols-outlined animate-spin">sync</span>Processing...';
            }

            const formData = new FormData();
            formData.append('product_id', <?php echo $product['id']; ?>);
            formData.append('quantity', document.getElementById('quantityInput')?.value || 1);
            formData.append('buy_now', 'true');

            try {
                const response = await fetch(addToCartApiUrl, {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showNotification('Redirecting to cart...', 'success');
                    setTimeout(() => window.location.href = cartPageUrl, 1000);
                } else {
                    showNotification(data.message, 'error');
                    if (btn) {
                        btn.disabled = false;
                        btn.innerHTML = originalContent;
                    }
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Failed to process. Please try again.', 'error');
                if (btn) {
                    btn.disabled = false;
                    btn.innerHTML = originalContent;
                }
            }
        }

        // Add animation styles
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slide-in {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            .animate-slide-in {
                animation: slide-in 0.3s ease-out;
            }
            .animate-spin {
                animation: spin 1s linear infinite;
            }
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>

</html>
