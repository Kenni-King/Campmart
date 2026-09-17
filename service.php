<?php
session_start();
require_once 'includes/controller.php';

// Get service slug from URL
$slug = $_GET['slug'] ?? '';

if (empty($slug)) {
    header('Location: services.php');
    exit;
}

// Fetch service details with all related data
$service_query = "
    SELECT s.*, 
           u.username as provider_username, 
           u.is_verified, 
           u.full_name as provider_name,
           u.profile_image as provider_image,
           u.rating as provider_rating,
           u.total_sales as provider_total_sales,
           u.location as provider_location,
           sc.name as category_name,
           sc.icon as category_icon,
           sc.color_code as category_color
    FROM services s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN service_categories sc ON s.service_category_id = sc.id
    WHERE s.slug = ? 
    AND s.status = 'active'
    LIMIT 1
";

$stmt = $db->prepare($service_query);
$stmt->bind_param("s", $slug);
$stmt->execute();
$result = $stmt->get_result();
$service = $result->fetch_assoc();

if (!$service) {
    header('Location: services.php');
    exit;
}

// Get related services (same category, exclude current)
$related_query = "
    SELECT s.*, u.username as provider_name, u.profile_image,
           sc.name as category_name, sc.icon as category_icon
    FROM services s
    JOIN users u ON s.user_id = u.id
    LEFT JOIN service_categories sc ON s.service_category_id = sc.id
    WHERE s.service_category_id = ? 
    AND s.id != ?
    AND s.status = 'active'
    AND s.availability != 'unavailable'
    ORDER BY s.total_orders DESC
    LIMIT 6
";
$stmt = $db->prepare($related_query);
$stmt->bind_param("ii", $service['service_category_id'], $service['id']);
$stmt->execute();
$related_services = $stmt->get_result();

// Get provider's other services
$provider_services_query = "
    SELECT s.*, sc.name as category_name
    FROM services s
    LEFT JOIN service_categories sc ON s.service_category_id = sc.id
    WHERE s.user_id = ? 
    AND s.id != ?
    AND s.status = 'active'
    ORDER BY s.total_orders DESC
    LIMIT 4
";
$stmt = $db->prepare($provider_services_query);
$stmt->bind_param("ii", $service['user_id'], $service['id']);
$stmt->execute();
$provider_services = $stmt->get_result();

// Track view (only if not the provider)
if (!isset($_SESSION['user_id']) || $_SESSION['user_id'] != $service['user_id']) {
    $update_views = "UPDATE services SET views_count = views_count + 1 WHERE id = ?";
    $stmt = $db->prepare($update_views);
    $stmt->bind_param("i", $service['id']);
    $stmt->execute();
}

// Format pricing display
$price_display = match($service['pricing_type']) {
    'hourly' => '₦' . number_format($service['price']) . '/hour',
    'per_project' => '₦' . number_format($service['price']) . '/project',
    default => '₦' . number_format($service['price'])
};

// Availability badge
$availability_config = [
    'available' => ['bg' => 'bg-emerald-50', 'text' => 'text-emerald-700', 'border' => 'border-emerald-200', 'label' => 'Available Now'],
    'busy' => ['bg' => 'bg-amber-50', 'text' => 'text-amber-700', 'border' => 'border-amber-200', 'label' => 'Currently Busy'],
    'unavailable' => ['bg' => 'bg-slate-50', 'text' => 'text-slate-700', 'border' => 'border-slate-200', 'label' => 'Unavailable']
];
$avail = $availability_config[$service['availability']] ?? $availability_config['available'];

// SEO metadata
$page_title = htmlspecialchars($service['title']) . ' - CampMart Services';
$page_description = $service['short_description'] 
    ? substr(strip_tags($service['short_description']), 0, 160) 
    : substr(strip_tags($service['description']), 0, 160);

// Generate absolute URLs for social media
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'];
$page_url = SITE_URL . 'service/' . htmlspecialchars($service['slug']);
$canonical_url = $page_url;

// Get portfolio image for social preview
$portfolio_images = json_decode($service['portfolio_images'], true);
$preview_image = !empty($portfolio_images) && is_array($portfolio_images) 
    ? $portfolio_images[0] 
    : SITE_URL . 'uploads/default-service.jpg';

// Ensure preview image is absolute URL
if (!filter_var($preview_image, FILTER_VALIDATE_URL)) {
    $preview_image = $protocol . $host . '/' . ltrim($preview_image, '/');
}

// Structured data for SEO
$structured_data = [
    "@context" => "https://schema.org/",
    "@type" => "Service",
    "name" => $service['title'],
    "image" => $preview_image,
    "description" => strip_tags($service['description']),
    "provider" => [
        "@type" => "Person",
        "name" => $service['provider_name']
    ],
    "serviceType" => $service['category_name'],
    "areaServed" => [
        "@type" => "Place",
        "name" => "Nigeria"
    ],
    "offers" => [
        "@type" => "Offer",
        "url" => $page_url,
        "priceCurrency" => "NGN",
        "price" => $service['price'],
        "priceSpecification" => [
            "@type" => "UnitPriceSpecification",
            "price" => $service['price'],
            "priceCurrency" => "NGN",
            "unitText" => $service['pricing_type']
        ],
        "availability" => $service['availability'] == 'available' 
            ? "https://schema.org/InStock" 
            : "https://schema.org/OutOfStock"
    ]
];

if ($service['rating']) {
    $structured_data["aggregateRating"] = [
        "@type" => "AggregateRating",
        "ratingValue" => $service['rating'],
        "reviewCount" => $service['total_ratings'],
        "bestRating" => "5",
        "worstRating" => "1"
    ];
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <base href="<?php echo SITE_URL; ?>">
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    
    <!-- SEO Meta Tags -->
    <title><?php echo htmlspecialchars($service['title']); ?> | Professional <?php echo htmlspecialchars($service['category_name']); ?> Services</title>
    <meta name="description" content="<?php echo htmlspecialchars($page_description); ?>. Offered by <?php echo htmlspecialchars($service['provider_name']); ?> on CampMart - Nigeria's trusted campus services platform." />
    <meta name="keywords" content="<?php echo htmlspecialchars($service['title']); ?>, <?php echo htmlspecialchars($service['category_name']); ?>, <?php echo $service['pricing_type']; ?>, hire, freelance, campus services, student services, Nigeria" />
    <meta name="author" content="<?php echo htmlspecialchars($service['provider_name']); ?>" />
    <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1" />
    <link rel="canonical" href="<?php echo htmlspecialchars($canonical_url); ?>" />
    
    <!-- Open Graph / Facebook Meta Tags -->
    <meta property="og:type" content="product" />
    <meta property="og:url" content="<?php echo htmlspecialchars($page_url); ?>" />
    <meta property="og:title" content="<?php echo htmlspecialchars($service['title']); ?>" />
    <meta property="og:description" content="<?php echo htmlspecialchars($page_description); ?>" />
    <meta property="og:image" content="<?php echo htmlspecialchars($preview_image); ?>" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />
    <meta property="og:site_name" content="CampMart" />
    <meta property="og:locale" content="en_NG" />
    <meta property="product:price:amount" content="<?php echo $service['price']; ?>" />
    <meta property="product:price:currency" content="NGN" />
    <meta property="product:availability" content="<?php echo $service['availability'] == 'available' ? 'in stock' : 'out of stock'; ?>" />
    <meta property="product:brand" content="<?php echo htmlspecialchars($service['provider_name']); ?>" />
    <meta property="product:category" content="<?php echo htmlspecialchars($service['category_name']); ?>" />
    
    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:url" content="<?php echo htmlspecialchars($page_url); ?>" />
    <meta name="twitter:title" content="<?php echo htmlspecialchars($service['title']); ?> - <?php echo $price_display; ?>" />
    <meta name="twitter:description" content="<?php echo htmlspecialchars($page_description); ?>" />
    <meta name="twitter:image" content="<?php echo htmlspecialchars($preview_image); ?>" />
    <meta name="twitter:label1" content="Price" />
    <meta name="twitter:data1" content="<?php echo $price_display; ?>" />
    <meta name="twitter:label2" content="Provider" />
    <meta name="twitter:data2" content="<?php echo htmlspecialchars($service['provider_name']); ?>" />
    
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
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
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
            .btn-primary {
                @apply bg-primary text-white px-6 py-3 rounded-lg font-bold hover:bg-opacity-90 transition-all flex items-center justify-center gap-2;
            }
            .btn-secondary {
                @apply bg-secondary text-white px-6 py-3 rounded-lg font-bold hover:bg-opacity-90 transition-all flex items-center justify-center gap-2;
            }
            .btn-outline {
                @apply border-2 border-slate-200 text-slate-700 px-6 py-3 rounded-lg font-bold hover:bg-slate-50 transition-all flex items-center justify-center gap-2;
            }
        }
    </style>
</head>
<body class="bg-white font-display text-slate-900 antialiased">
    <?php include_once 'includes/header.php'; ?>

    <main class="max-w-[1440px] mx-auto px-6 py-8">
        <!-- Breadcrumb -->
        <nav class="flex items-center gap-2 text-xs font-medium text-slate-500 mb-6">
            <a class="hover:text-primary" href="index.php">Home</a>
            <span class="material-symbols-outlined text-sm">chevron_right</span>
            <a class="hover:text-primary" href="services.php">Services</a>
            <span class="material-symbols-outlined text-sm">chevron_right</span>
            <span class="text-slate-900"><?php echo htmlspecialchars($service['category_name']); ?></span>
        </nav>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8">
            <!-- Main Content -->
            <div class="lg:col-span-8">
                <!-- Service Header -->
                <div class="bg-gradient-to-br from-primary/10 via-primary/5 to-transparent rounded-2xl p-8 mb-8 border border-slate-100">
                    <div class="flex items-start justify-between mb-4">
                        <span class="inline-flex items-center gap-1 px-3 py-1.5 <?php echo $avail['bg'] . ' ' . $avail['text'] . ' border ' . $avail['border']; ?> rounded-full text-xs font-bold">
                            <span class="material-symbols-outlined text-sm">schedule</span>
                            <?php echo $avail['label']; ?>
                        </span>
                        <div class="flex items-center gap-2">
                            <?php if ($service['is_featured']): ?>
                            <span class="inline-flex items-center gap-1 px-3 py-1 bg-amber-50 text-amber-700 rounded-full text-xs font-bold border border-amber-200">
                                <span class="material-symbols-outlined text-sm">workspace_premium</span>
                                Featured
                            </span>
                            <?php endif; ?>
                            <button onclick="shareService()" class="p-2 rounded-lg text-slate-400 hover:text-primary hover:bg-primary/5 transition-colors">
                                <span class="material-symbols-outlined">share</span>
                            </button>
                        </div>
                    </div>

                    <h1 class="text-4xl font-extrabold text-slate-900 mb-3"><?php echo htmlspecialchars($service['title']); ?></h1>
                    
                    <div class="flex flex-wrap items-center gap-4 mb-6">
                        <span class="inline-flex items-center gap-1 px-3 py-1 bg-white text-primary text-sm font-semibold rounded-full border border-primary/20">
                            <span class="material-symbols-outlined text-base" style="<?php echo !empty($service['category_color']) ? 'color: ' . htmlspecialchars($service['category_color']) : ''; ?>">
                                <?php echo htmlspecialchars($service['category_icon'] ?: 'work'); ?>
                            </span>
                            <?php echo htmlspecialchars($service['category_name']); ?>
                        </span>
                        
                        <?php if ($service['rating']): ?>
                        <div class="flex items-center gap-1 text-sm">
                            <span class="material-symbols-outlined text-orange-400 fill-1">star</span>
                            <span class="font-bold text-slate-900"><?php echo number_format($service['rating'], 1); ?></span>
                            <span class="text-slate-500">(<?php echo $service['total_ratings']; ?> reviews)</span>
                        </div>
                        <?php endif; ?>
                        
                        <div class="flex items-center gap-1 text-sm text-slate-500">
                            <span class="material-symbols-outlined text-base">visibility</span>
                            <span><?php echo number_format($service['views_count']); ?> views</span>
                        </div>
                    </div>

                    <div class="flex items-end gap-3">
                        <span class="text-3xl font-black text-primary"><?php echo $price_display; ?></span>
                        <span class="text-slate-500 mb-1"><?php echo ucfirst(str_replace('_', ' ', $service['pricing_type'])); ?></span>
                    </div>
                </div>

                <!-- Portfolio Gallery -->
                <?php 
                $portfolio_images = json_decode($service['portfolio_images'], true);
                if (!empty($portfolio_images) && is_array($portfolio_images)): 
                ?>
                <div class="bg-white rounded-2xl border border-slate-200 p-6 md:p-8 mb-8">
                    <h2 class="text-2xl font-bold text-slate-900 mb-6 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">collections</span>
                        Portfolio Gallery
                    </h2>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                        <?php foreach($portfolio_images as $img_path): ?>
                        <div class="aspect-square rounded-xl overflow-hidden bg-slate-100 hover:opacity-90 transition-opacity cursor-pointer" onclick="viewImage('<?= htmlspecialchars($img_path) ?>')">
                            <img src="<?= htmlspecialchars($img_path) ?>" alt="Portfolio image" class="w-full h-full object-cover" />
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Service Description -->
                <div class="bg-white rounded-2xl border border-slate-200 p-6 md:p-8 mb-8">
                    <h2 class="text-2xl font-bold text-slate-900 mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">description</span>
                        About This Service
                    </h2>
                    
                    <?php if ($service['short_description']): ?>
                    <p class="text-lg font-medium text-slate-700 mb-4 pb-4 border-b border-slate-100">
                        <?php echo nl2br(htmlspecialchars($service['short_description'])); ?>
                    </p>
                    <?php endif; ?>
                    
                    <div class="prose prose-slate max-w-none">
                        <p class="text-slate-600 leading-relaxed whitespace-pre-line">
                            <?php echo nl2br(htmlspecialchars($service['description'])); ?>
                        </p>
                    </div>
                </div>

                <!-- Service Details -->
                <div class="bg-white rounded-2xl border border-slate-200 p-6 md:p-8 mb-8">
                    <h2 class="text-2xl font-bold text-slate-900 mb-6 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">info</span>
                        Service Details
                    </h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <?php if ($service['delivery_time']): ?>
                        <div class="flex items-start gap-3">
                            <div class="size-10 rounded-lg bg-primary/10 flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-primary">schedule</span>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Delivery Time</p>
                                <p class="text-base font-semibold text-slate-900"><?php echo htmlspecialchars($service['delivery_time']); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="flex items-start gap-3">
                            <div class="size-10 rounded-lg bg-primary/10 flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-primary">shopping_bag</span>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Orders Completed</p>
                                <p class="text-base font-semibold text-slate-900"><?php echo number_format($service['total_orders']); ?> orders</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start gap-3">
                            <div class="size-10 rounded-lg bg-primary/10 flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-primary">payments</span>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Pricing Type</p>
                                <p class="text-base font-semibold text-slate-900"><?php echo ucfirst(str_replace('_', ' ', $service['pricing_type'])); ?></p>
                            </div>
                        </div>
                        
                        <div class="flex items-start gap-3">
                            <div class="size-10 rounded-lg bg-primary/10 flex items-center justify-center shrink-0">
                                <span class="material-symbols-outlined text-primary">event_available</span>
                            </div>
                            <div>
                                <p class="text-xs font-bold text-slate-500 uppercase tracking-wider mb-1">Availability</p>
                                <p class="text-base font-semibold text-slate-900"><?php echo ucfirst($service['availability']); ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="lg:col-span-4 space-y-6">
                <!-- Action Card -->
                <div class="bg-white rounded-2xl border-2 border-slate-100 shadow-sm p-6 sticky top-6">
                    <div class="mb-6">
                        <p class="text-sm text-slate-500 mb-2">Service Price</p>
                        <p class="text-4xl font-black text-primary mb-1"><?php echo $price_display; ?></p>
                        <p class="text-xs text-slate-500"><?php echo ucfirst(str_replace('_', ' ', $service['pricing_type'])); ?></p>
                    </div>
                    
                    <div class="space-y-3 mb-6">
                        <a href="<?php echo SITE_URL; ?>chat.php?seller_id=<?php echo $service['user_id']; ?>&service_id=<?php echo $service['id']; ?>" class="btn-secondary w-full text-center no-underline">
                            <span class="material-symbols-outlined">chat</span>
                            Chat Seller
                        </a>
                    </div>
                                  
                    <p class="text-center text-[10px] text-slate-400 uppercase tracking-widest font-bold">
                        Secure Campus Transaction
                    </p>
                </div>

                <!-- Provider Card -->
                <div class="bg-slate-50 rounded-2xl border border-slate-100 p-6">
                    <h3 class="text-sm font-bold text-slate-500 uppercase tracking-wider mb-4">Service Provider</h3>
                    
                    <div class="flex items-start gap-4 mb-6">
                        <div class="size-16 rounded-full overflow-hidden border-2 border-white shadow-sm shrink-0">
                            <?php if ($service['provider_image']): ?>
                            <img alt="<?php echo htmlspecialchars($service['provider_name']); ?>" 
                                 class="w-full h-full object-cover" 
                                 src="<?php echo htmlspecialchars($service['provider_image']); ?>"/>
                            <?php else: ?>
                            <div class="w-full h-full bg-primary text-white flex items-center justify-center text-2xl font-bold">
                                <?php echo strtoupper(substr($service['provider_name'], 0, 1)); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-1 mb-1">
                                <h4 class="font-bold text-slate-900 truncate"><?php echo htmlspecialchars($service['provider_name']); ?></h4>
                                <?php if ($service['is_verified']): ?>
                                <span class="material-symbols-outlined text-blue-500 text-base fill-1">verified</span>
                                <?php endif; ?>
                            </div>
                            <?php if ($service['provider_rating']): ?>
                            <div class="flex items-center gap-1 text-xs text-slate-500 mb-2">
                                <span class="material-symbols-outlined text-xs text-orange-400 fill-1">star</span>
                                <span class="font-bold"><?php echo number_format($service['provider_rating'], 1); ?></span>
                                <span>(<?php echo $service['provider_total_sales']; ?> sales)</span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-3 gap-2 py-4 border-t border-slate-200">
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Response Time</p>
                            <p class="text-sm font-semibold text-slate-700">Under 1 hour</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Member Since</p>
                            <p class="text-sm font-semibold text-slate-700"><?php echo date('M Y', strtotime($service['created_at'])); ?></p>
                        </div>
                        <div>
                                <p class="text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1">Location</p>
                                <p class="text-sm font-semibold text-slate-700"><?php echo htmlspecialchars($service['provider_location']); ?></p>
                            </div>
                    </div>
                    
                   
                    
                    <!-- <a class="mt-4 flex items-center justify-center gap-2 py-3 px-4 bg-white border border-slate-200 rounded-xl text-sm font-bold text-slate-600 hover:border-primary hover:text-primary transition-all group" 
                       href="seller-profile.php?username=<?php echo urlencode($service['provider_username']); ?>">
                        View Provider's Profile
                        <span class="material-symbols-outlined text-lg group-hover:translate-x-1 transition-transform">arrow_right_alt</span>
                    </a> -->
                </div>
            </div>
        </div>

        <!-- Related Services -->
        <?php if ($related_services && $related_services->num_rows > 0): ?>
        <section class="mt-16">
            <div class="flex items-center justify-between mb-8">
                <h2 class="text-xl font-extrabold text-primary">Similar Services</h2>
                <a href="services.php?category=<?php echo $service['service_category_id']; ?>" 
                   class="text-sm font-bold text-primary flex items-center gap-1 hover:underline">
                    View all <span class="material-symbols-outlined text-base">arrow_forward</span>
                </a>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
                <?php while($related = $related_services->fetch_assoc()): 
                    $rel_price = $related['pricing_type'] == 'hourly' ? '₦' . number_format($related['price']) . '/hr' : 'From ₦' . number_format($related['price']);
                 $portfolio_images = json_decode($related['portfolio_images'], true);
                $first_image = !empty($portfolio_images) ? $portfolio_images[0] : null;
                ?>
                <div class="bg-white rounded-xl border border-slate-200 overflow-hidden hover:shadow-lg transition-shadow" onclick="window.location.href='<?php echo serviceUrl($related['slug']); ?>'">
                    <div class="relative h-40 bg-gradient-to-br from-primary/10 to-primary/5 flex items-center justify-center overflow-hidden">
                        <?php if (!empty($first_image)): ?>
                            <img src="<?php echo htmlspecialchars($first_image); ?>" alt="<?php echo htmlspecialchars($related['title']); ?>" class="w-full h-full object-cover" />
                        <?php else: ?>
                            <span class="material-symbols-outlined text-6xl text-primary/20">
                                <?php echo htmlspecialchars($related['category_icon'] ?: 'work'); ?>
                            </span>
                        <?php endif; ?>
                    </div>
                    <div class="p-4">
                        <div class="flex items-center gap-2 mb-3">
                            <div class="size-8 rounded-full overflow-hidden">
                                <img alt="<?php echo htmlspecialchars($related['provider_name']); ?>" 
                                     class="w-full h-full object-cover" 
                                     src="<?php echo htmlspecialchars($related['profile_image'] ?: 'https://via.placeholder.com/100'); ?>"/>
                            </div>
                            <span class="text-xs font-semibold text-slate-600"><?php echo htmlspecialchars($related['provider_name']); ?></span>
                        </div>
                        
                        <h4 class="text-sm font-bold text-slate-800 mb-2 line-clamp-2">
                            <?php echo htmlspecialchars($related['title']); ?>
                        </h4>
                        <p class="text-xs text-slate-500 mb-4 line-clamp-2">
                        <?php echo htmlspecialchars($service['short_description'] ?: substr($service['description'], 0, 80)); ?>
                    </p>
                        
                        <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                            <p class="text-lg font-extrabold text-primary"><?php echo $rel_price; ?></p>
                            
                        </div>
                    </div>
                </div>
                <?php endwhile; ?>
            </div>
        </section>
        <?php endif; ?>
    </main>

    <?php include 'includes/footer.php'; ?>

    <!-- Image Viewer Modal -->
    <div id="imageModal" class="hidden fixed inset-0 bg-black/90 z-50 flex items-center justify-center p-4" onclick="closeImageModal()">
        <button onclick="closeImageModal()" class="absolute top-4 right-4 text-white hover:text-gray-300">
            <span class="material-symbols-outlined text-4xl">close</span>
        </button>
        <img id="modalImage" src="" alt="Portfolio image" class="max-w-full max-h-[90vh] object-contain rounded-lg" onclick="event.stopPropagation()" />
    </div>

    <script>
        function shareService() {
            const serviceTitle = "<?php echo addslashes($service['title']); ?>";
            const serviceUrl = window.location.href;
            const servicePrice = "<?php echo addslashes($price_display); ?>";
            
            if (navigator.share) {
                navigator.share({
                    title: serviceTitle,
                    text: `Check out this service: ${serviceTitle} on CampMart!`,
                    url: serviceUrl
                }).catch(err => console.log('Share cancelled'));
            } else {
                navigator.clipboard.writeText(serviceUrl).then(() => {
                    const btn = event.target.closest('button');
                    const originalIcon = btn.querySelector('.material-symbols-outlined').textContent;
                    btn.querySelector('.material-symbols-outlined').textContent = 'check_circle';
                    btn.classList.add('text-green-500');
                    
                    setTimeout(() => {
                        btn.querySelector('.material-symbols-outlined').textContent = originalIcon;
                        btn.classList.remove('text-green-500');
                    }, 2000);
                }).catch(err => {
                    console.error('Failed to copy:', err);
                });
            }
        }

        function viewImage(imagePath) {
            document.getElementById('modalImage').src = imagePath;
            document.getElementById('imageModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeImageModal() {
            document.getElementById('imageModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeImageModal();
        });
    </script>
</body>
</html>
