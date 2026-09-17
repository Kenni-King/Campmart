<?php
session_start();
include_once "includes/controller.php";

// Get logged-in user's university_id for filtering
$current_user_university_id = null;
if(isset($_SESSION['userAppId'])){
    $user_id = $_SESSION['userAppId'];
    $user_query = $db->query("SELECT university_id FROM users WHERE id = $user_id");
    if($user_query && $user_data = $user_query->fetch_assoc()){
        $current_user_university_id = $user_data['university_id'];
    }
}

// Get filter parameters
$category_id = isset($_GET['category']) ? intval($_GET['category']) : 0;
$search = isset($_GET['search']) ? mysqli_real_escape_string($db, $_GET['search']) : '';
$pricing_type = isset($_GET['pricing_type']) ? mysqli_real_escape_string($db, $_GET['pricing_type']) : '';
$availability = isset($_GET['availability']) ? mysqli_real_escape_string($db, $_GET['availability']) : '';
$sort = isset($_GET['sort']) ? mysqli_real_escape_string($db, $_GET['sort']) : 'newest';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build the WHERE clause
$where_conditions = ["s.status = 'active'"];

// Add university filter
if ($current_user_university_id) {
    $where_conditions[] = "s.university_id = $current_user_university_id";
}

if ($category_id > 0) {
    $where_conditions[] = "s.service_category_id = $category_id";
}

$ai_id_list = '';
if (!empty($search)) {
    include_once 'includes/ai/search.php';
    $ai_ranked = ai_search_services($search, [
        'universityId' => $current_user_university_id,
        'categoryId' => $category_id,
        'limit' => 200,
    ]);
    if (is_array($ai_ranked) && !empty($ai_ranked)) {
        $ai_id_list = implode(',', array_map('intval', array_keys($ai_ranked)));
        $where_conditions[] = "s.id IN ($ai_id_list)";
    } else {
        $where_conditions[] = "(s.title LIKE '%$search%' OR s.description LIKE '%$search%' OR s.short_description LIKE '%$search%')";
    }
}

if (!empty($pricing_type)) {
    $where_conditions[] = "s.pricing_type = '$pricing_type'";
}

if (!empty($availability)) {
    $where_conditions[] = "s.availability = '$availability'";
}

$where_clause = implode(' AND ', $where_conditions);

// Determine sort order
$order_by = match($sort) {
    'price_low' => 's.price ASC',
    'price_high' => 's.price DESC',
    'popular' => 's.views_count DESC, s.total_orders DESC',
    'rating' => 's.rating DESC',
    'oldest' => 's.created_at ASC',
    default => 's.created_at DESC', // newest
};

// AI semantic search ranks by relevance first.
if (!empty($search) && !empty($ai_id_list)) {
    $order_by = "FIELD(s.id, $ai_id_list)";
}

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM services s WHERE $where_clause";
$count_result = $db->query($count_query);
$total_services = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_services / $per_page);

// Log search for AI suggestions & recommendations
if (!empty($search)) {
    include_once 'includes/ai/search-logger.php';
    ai_log_search($search, (int) $total_services, [
        'category' => $category_id > 0 ? $category_id : null,
        'pricing_type' => !empty($pricing_type) ? $pricing_type : null,
    ]);
}

// Get services
$services_query = "
    SELECT s.*, u.full_name as provider_name, u.rating as provider_rating, u.profile_image,
           sc.name as category_name, sc.icon as category_icon, sc.color_code
    FROM services s
    JOIN users u ON s.user_id = u.id
    JOIN service_categories sc ON s.service_category_id = sc.id
    WHERE $where_clause
    ORDER BY $order_by
    LIMIT $per_page OFFSET $offset
";
$services_result = $db->query($services_query);

// Get all service categories for filter
$categories_query = "SELECT * FROM service_categories WHERE is_active = TRUE ORDER BY name ASC";
$categories_result = $db->query($categories_query);

// Get price range stats
$stats_query = "SELECT MIN(price) as min_price, MAX(price) as max_price FROM services s WHERE $where_clause";
$stats_result = $db->query($stats_query);
$price_stats = $stats_result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <base href="<?php echo SITE_URL; ?>">
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>All Services | CampMart</title>
    <script src="tailwind34.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet" />
    <script id="tailwind-config">
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        "primary": "#064E3B",
                        "secondary": "#F97316", 
                        "accent": "#FFE66D",
                        "brand-green": "#064E3B",
                        "brand-green-light": "#F0FDF4",
                        "background-main": "#F9FAFB",
                        "surface-white": "#FFFFFF",
                        "text-dark": "#1F2937",
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

        body {
            font-family: 'Inter', sans-serif;
        }

        .service-card {
            transition: all 0.3s ease;
        }

        .service-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body class="bg-background-main min-h-screen">
    <?php include_once 'includes/header.php'; ?>

    <main class="max-w-[1440px] mx-auto px-6 py-8">
        <!-- Header Section -->
        <div class="mb-8">
            <h1 class="text-4xl font-extrabold text-brand-green mb-2">Campus Services</h1>
            <p class="text-slate-600">Connect with talented students offering professional services</p>
        </div>

        <!-- Search and Filter Bar -->
        <div class="bg-white rounded-xl border border-slate-200 p-2 md:p-6 mb-8">
            <form method="GET" action="services.php" class="space-y-4">
                <!-- Search and Filter Toggle -->
                <div class="flex gap-2 md:gap-4">
                    <button type="button" onclick="toggleFilters()" class="md:hidden flex items-center gap-1 px-4 py-2.5 bg-slate-100 text-slate-700 rounded-lg font-medium hover:bg-slate-200" id="filterToggleBtn">
                        <span class="material-symbols-outlined text-lg">tune</span>
                        <span class="text-sm">Filters</span>
                    </button>
                </div>

                <!-- Filters (Collapsible on mobile) -->
                <div id="filtersSection" class="hidden md:grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 pt-2">
                    <!-- Category -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Category</label>
                        <select name="category" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary">
                            <option value="0">All Categories</option>
                            <?php 
                            if ($categories_result && $categories_result->num_rows > 0):
                                while($cat = $categories_result->fetch_assoc()): 
                            ?>
                            <option value="<?php echo $cat['id']; ?>" <?php echo $category_id == $cat['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                            <?php endwhile; endif; ?>
                        </select>
                    </div>

                    <!-- Pricing Type -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Pricing Type</label>
                        <select name="pricing_type" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary">
                            <option value="">Any Type</option>
                            <option value="fixed" <?php echo $pricing_type == 'fixed' ? 'selected' : ''; ?>>Fixed Price</option>
                            <option value="hourly" <?php echo $pricing_type == 'hourly' ? 'selected' : ''; ?>>Hourly Rate</option>
                            <option value="per_project" <?php echo $pricing_type == 'per_project' ? 'selected' : ''; ?>>Per Project</option>
                        </select>
                    </div>

                    <!-- Availability -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Availability</label>
                        <select name="availability" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary">
                            <option value="">Any</option>
                            <option value="available" <?php echo $availability == 'available' ? 'selected' : ''; ?>>Available Now</option>
                            <option value="busy" <?php echo $availability == 'busy' ? 'selected' : ''; ?>>Busy</option>
                            <option value="unavailable" <?php echo $availability == 'unavailable' ? 'selected' : ''; ?>>Unavailable</option>
                        </select>
                    </div>

                    <!-- Sort -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Sort By</label>
                        <select name="sort" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary">
                            <option value="newest" <?php echo $sort == 'newest' ? 'selected' : ''; ?>>Newest First</option>
                            <option value="oldest" <?php echo $sort == 'oldest' ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="price_low" <?php echo $sort == 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                            <option value="price_high" <?php echo $sort == 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                            <option value="popular" <?php echo $sort == 'popular' ? 'selected' : ''; ?>>Most Popular</option>
                            <option value="rating" <?php echo $sort == 'rating' ? 'selected' : ''; ?>>Highest Rated</option>
                        </select>
                    </div>
                </div>

                <!-- Filter Actions -->
                <div id="filterActions" class="hidden md:flex justify-between items-center pt-2">
                    <a href="services.php" class="text-sm text-slate-600 hover:text-primary font-medium">Clear Filters</a>
                    <button type="submit" class="hidden md:inline-block text-sm px-6 py-2 bg-slate-100 text-slate-700 rounded-lg font-medium hover:bg-slate-200">
                        Apply Filters
                    </button>
                </div>
            </form>
        </div>

        <!-- Results Header -->
        <div class="flex justify-between items-center mb-6">
            <p class="text-slate-600">
                Showing <span class="font-bold text-brand-green"><?php echo number_format($total_services); ?></span> services
                <?php if (!empty($search)): ?>
                    for "<span class="font-bold text-primary"><?php echo htmlspecialchars($search); ?></span>"
                <?php endif; ?>
            </p>
        </div>

        <!-- Services Grid -->
        <?php if ($services_result && $services_result->num_rows > 0): ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6 mb-8">
            <?php while($service = $services_result->fetch_assoc()): 
                $profile_image = $service['profile_image'] ?: 'https://via.placeholder.com/100';
                $price_display = $service['pricing_type'] == 'hourly' ? '₦' . number_format($service['price']) . '/hr' : 'From ₦' . number_format($service['price']);
                $availability_badge = [
                    'available' => ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-700', 'label' => 'Available'],
                    'busy' => ['bg' => 'bg-amber-100', 'text' => 'text-amber-700', 'label' => 'Busy'],
                    'unavailable' => ['bg' => 'bg-slate-100', 'text' => 'text-slate-700', 'label' => 'Unavailable']
                ];
                $avail = $availability_badge[$service['availability']] ?? $availability_badge['available'];
            ?>
            <div  onclick="window.location.href='<?php echo serviceUrl($service['slug']); ?>'" class="service-card bg-white rounded-xl border border-slate-200 overflow-hidden">
                <?php 
                $portfolio_images = json_decode($service['portfolio_images'], true);
                $first_image = !empty($portfolio_images) ? $portfolio_images[0] : null;
                ?>
                <div class="relative h-48 overflow-hidden bg-gradient-to-br from-primary/10 to-brand-green/10">
                    <?php if($first_image): ?>
                    <img src="<?= htmlspecialchars($first_image) ?>" alt="<?= htmlspecialchars($service['title']) ?>" class="w-full h-full object-cover" />
                    <?php else: ?>
                    <div class="absolute inset-0 flex items-center justify-center">
                        <span class="material-symbols-outlined text-8xl text-primary/20">
                            <?php echo htmlspecialchars($service['category_icon'] ?: 'work'); ?>
                        </span>
                    </div>
                    <?php endif; ?>
                    <div class="absolute top-3 left-3">
                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold <?php echo $avail['bg'] . ' ' . $avail['text']; ?>">
                            <span class="material-symbols-outlined text-sm">schedule</span>
                            <?php echo $avail['label']; ?>
                        </span>
                    </div>
                    <?php if ($service['is_featured']): ?>
                    <div class="absolute top-3 right-3">
                        <span class="inline-flex items-center gap-1 px-3 py-1 bg-amber-50 text-amber-700 rounded-full text-xs font-bold border border-amber-200">
                            <span class="material-symbols-outlined text-sm">workspace_premium</span>
                            Featured
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="p-4">
                    <!-- Provider Info -->
                    <div class="flex items-center gap-3 mb-4">
                        <div class="size-10 rounded-full overflow-hidden border-2 border-slate-100">
                            <img alt="<?php echo htmlspecialchars($service['provider_name']); ?>" 
                                 class="w-full h-full object-cover" 
                                 src="<?php echo htmlspecialchars($profile_image); ?>" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-slate-800 truncate"><?php echo htmlspecialchars($service['provider_name']); ?></p>
                            <?php if ($service['provider_rating']): ?>
                            <div class="flex items-center gap-1 text-xs text-slate-500">
                                <span class="material-symbols-outlined text-xs text-orange-400 fill-1">star</span>
                                <span class="font-bold"><?php echo number_format($service['provider_rating'], 1); ?></span>
                                <span>(<?php echo $service['total_ratings']; ?>)</span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Service Details -->
                    <span class="inline-flex items-center gap-1 px-2 py-1 bg-primary/5 text-primary text-xs font-semibold rounded-full mb-2">
                        <span class="material-symbols-outlined text-sm"><?php echo htmlspecialchars($service['category_icon'] ?: 'category'); ?></span>
                        <?php echo htmlspecialchars($service['category_name']); ?>
                    </span>
                    
                    <h4 class="text-slate-800 font-bold text-base mb-2 line-clamp-2">
                        <?php echo htmlspecialchars($service['title']); ?>
                    </h4>
                    
                    <p class="text-xs text-slate-500 mb-4 line-clamp-2">
                        <?php echo htmlspecialchars($service['short_description'] ?: substr($service['description'], 0, 80)); ?>
                    </p>

                    <!-- Pricing & Actions -->
                    <div class="flex items-center justify-between pt-3 border-t border-slate-100">
                        <div>
                            <p class="text-xs text-slate-500 mb-0.5"><?php echo ucfirst(str_replace('_', ' ', $service['pricing_type'])); ?></p>
                            <p class="text-xl font-extrabold text-primary"><?php echo $price_display; ?></p>
                        </div>
                    
                    </div>
                </div>
            </div>
            <?php endwhile; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="flex justify-center items-center gap-2">
            <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?><?php echo $category_id > 0 ? '&category=' . $category_id : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($pricing_type) ? '&pricing_type=' . $pricing_type : ''; ?><?php echo !empty($availability) ? '&availability=' . $availability : ''; ?>&sort=<?php echo $sort; ?>" 
               class="px-4 py-2 bg-white border border-slate-300 rounded-lg text-slate-700 font-medium hover:bg-slate-50">
                Previous
            </a>
            <?php endif; ?>

            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
            <a href="?page=<?php echo $i; ?><?php echo $category_id > 0 ? '&category=' . $category_id : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($pricing_type) ? '&pricing_type=' . $pricing_type : ''; ?><?php echo !empty($availability) ? '&availability=' . $availability : ''; ?>&sort=<?php echo $sort; ?>" 
               class="px-4 py-2 rounded-lg font-medium <?php echo $i == $page ? 'bg-primary text-white' : 'bg-white border border-slate-300 text-slate-700 hover:bg-slate-50'; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
            <a href="?page=<?php echo $page + 1; ?><?php echo $category_id > 0 ? '&category=' . $category_id : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($pricing_type) ? '&pricing_type=' . $pricing_type : ''; ?><?php echo !empty($availability) ? '&availability=' . $availability : ''; ?>&sort=<?php echo $sort; ?>" 
               class="px-4 py-2 bg-white border border-slate-300 rounded-lg text-slate-700 font-medium hover:bg-slate-50">
                Next
            </a>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <!-- Empty State -->
        <div class="text-center py-16 bg-white rounded-xl border border-slate-200">
            <span class="material-symbols-outlined text-8xl text-slate-300 mb-4">search_off</span>
            <h3 class="text-2xl font-bold text-slate-700 mb-2">No services found</h3>
            <p class="text-slate-500 mb-6">Try adjusting your filters or search terms</p>
            <a href="services.php" class="inline-block px-6 py-3 bg-primary text-white rounded-lg font-bold hover:bg-primary/90">
                View All Services
            </a>
        </div>
        <?php endif; ?>
    </main>

    <?php include_once 'includes/footer.php'; ?>

    <script>
        // Auto-submit form when sort changes
        document.querySelector('select[name="sort"]')?.addEventListener('change', function() {
            this.form.submit();
        });

        // Toggle filters on mobile
        function toggleFilters() {
            const filtersSection = document.getElementById('filtersSection');
            const filterActions = document.getElementById('filterActions');
            const toggleBtn = document.getElementById('filterToggleBtn');
            
            if (filtersSection.classList.contains('hidden')) {
                filtersSection.classList.remove('hidden');
                filtersSection.classList.add('grid');
                filterActions.classList.remove('hidden');
                filterActions.classList.add('flex');
                toggleBtn.innerHTML = '<span class="material-symbols-outlined text-lg">close</span><span class="text-sm">Close</span>';
            } else {
                filtersSection.classList.add('hidden');
                filtersSection.classList.remove('grid');
                filterActions.classList.add('hidden');
                filterActions.classList.remove('flex');
                toggleBtn.innerHTML = '<span class="material-symbols-outlined text-lg">tune</span><span class="text-sm">Filters</span>';
            }
        }

        // Auto-submit form on mobile when filters change
        document.querySelectorAll('#filtersSection select').forEach(select => {
            select.addEventListener('change', function() {
                if (window.innerWidth < 768) {
                    this.form.submit();
                }
            });
        });
    </script>
</body>

</html>
