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
$condition = isset($_GET['condition']) ? mysqli_real_escape_string($db, $_GET['condition']) : '';
$sort = isset($_GET['sort']) ? mysqli_real_escape_string($db, $_GET['sort']) : 'newest';
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

// Build the WHERE clause
$where_conditions = ["p.status = 'approved'", "p.availability = 'available'"];

// Add university filter
if ($current_user_university_id) {
    $where_conditions[] = "p.university_id = $current_user_university_id";
}

if ($category_id > 0) {
    $where_conditions[] = "p.category_id = $category_id";
}

$ai_id_list = '';
if (!empty($search)) {
    include_once 'includes/ai/search.php';
    $ai_ranked = ai_search_products($search, [
        'universityId' => $current_user_university_id,
        'categoryId' => $category_id,
        'limit' => 200,
    ]);
    if (is_array($ai_ranked) && !empty($ai_ranked)) {
        $ai_id_list = implode(',', array_map('intval', array_keys($ai_ranked)));
        $where_conditions[] = "p.id IN ($ai_id_list)";
    } else {
        $where_conditions[] = "(p.title LIKE '%$search%' OR p.description LIKE '%$search%')";
    }
}

if (!empty($condition)) {
    $where_conditions[] = "p.condition_type = '$condition'";
}

$where_clause = implode(' AND ', $where_conditions);

// Determine sort order
$order_by = match($sort) {
    'price_low' => 'p.price ASC',
    'price_high' => 'p.price DESC',
    'popular' => 'p.views_count DESC, p.bookmarks_count DESC',
    'oldest' => 'p.created_at ASC',
    default => 'p.created_at DESC', // newest
};

// AI semantic search ranks by relevance first.
if (!empty($search) && !empty($ai_id_list)) {
    $order_by = "FIELD(p.id, $ai_id_list)";
}

// Get total count for pagination
$count_query = "SELECT COUNT(*) as total FROM products p WHERE $where_clause";
$count_result = $db->query($count_query);
$total_products = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_products / $per_page);

// Log search for AI suggestions & recommendations
if (!empty($search)) {
    include_once 'includes/ai/search-logger.php';
    ai_log_search($search, (int) $total_products, [
        'category' => $category_id > 0 ? $category_id : null,
        'condition' => !empty($condition) ? $condition : null,
    ]);
}

// Get products
$products_query = "
    SELECT p.*, u.full_name as seller_name, u.rating as seller_rating, u.is_verified,
           c.name as category_name, pi.image_url
    FROM products p
    JOIN users u ON p.user_id = u.id
    JOIN categories c ON p.category_id = c.id
    LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = TRUE
    WHERE $where_clause
    ORDER BY $order_by
    LIMIT $per_page OFFSET $offset
";
$products_result = $db->query($products_query);

// Get all categories for filter
$categories_query = "SELECT * FROM categories WHERE parent_id IS NULL AND is_active = TRUE ORDER BY name ASC";
$categories_result = $db->query($categories_query);

// Get price range stats
$stats_query = "SELECT MIN(price) as min_price, MAX(price) as max_price FROM products p WHERE $where_clause";
$stats_result = $db->query($stats_query);
$price_stats = $stats_result->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <base href="<?php echo SITE_URL; ?>">
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>All Products | CampMart</title>
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

        .product-card {
            transition: all 0.3s ease;
        }

        .product-card:hover {
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
            <h1 class="text-4xl font-extrabold text-brand-green mb-2">All Products</h1>
            <p class="text-slate-600">Discover amazing deals from your campus community</p>
        </div>

        <!-- Search and Filter Bar -->
        <div class="bg-white rounded-xl border border-slate-200 p-2 md:p-6 mb-8">
            <form method="GET" action="products.php" class="space-y-4">
                <!-- Search and Filter Toggle -->
                <div class="flex gap-2 md:gap-4">
                 
                    <button type="button" onclick="toggleFilters()" class="md:hidden flex items-center gap-1 px-4 py-2.5 bg-slate-100 text-slate-700 rounded-lg font-medium hover:bg-slate-200" id="filterToggleBtn">
                        <span class="material-symbols-outlined text-lg">tune</span>
                        <span class="text-sm">Filters</span>
                    </button>
                  
                </div>

                <!-- Filters (Collapsible on mobile) -->
                <div id="filtersSection" class="hidden md:grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 pt-2">
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

                    <!-- Condition -->
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Condition</label>
                        <select name="condition" class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary">
                            <option value="">Any Condition</option>
                            <option value="new" <?php echo $condition == 'new' ? 'selected' : ''; ?>>New</option>
                            <option value="used" <?php echo $condition == 'used' ? 'selected' : ''; ?>>Used</option>
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
                        </select>
                    </div>
                </div>

                <!-- Filter Actions -->
                <div id="filterActions" class="hidden md:flex justify-between items-center pt-2">
                    <a href="products.php" class="text-sm text-slate-600 hover:text-primary font-medium">Clear Filters</a>
                    <button type="submit" class="hidden md:inline-block text-sm px-6 py-2 bg-slate-100 text-slate-700 rounded-lg font-medium hover:bg-slate-200">
                        Apply Filters
                    </button>
                </div>
            </form>
        </div>

        <!-- Results Header -->
        <div class="flex justify-between items-center mb-6">
            <p class="text-slate-600">
                Showing <span class="font-bold text-brand-green"><?php echo number_format($total_products); ?></span> products
                <?php if (!empty($search)): ?>
                    for "<span class="font-bold text-primary"><?php echo htmlspecialchars($search); ?></span>"
                <?php endif; ?>
            </p>
        </div>

        <!-- Products Grid -->
        <?php if ($products_result && $products_result->num_rows > 0): ?>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-4 mb-8">
            <?php while($product = $products_result->fetch_assoc()): 
                $image_url = $product['image_url'] ?: 'https://via.placeholder.com/400x400?text=No+Image';
                $price_display = $product['price'] == 0 ? 'FREE' : '₦' . number_format($product['price']);
            ?>
            <div class="product-card bg-white rounded-xl border border-slate-200 overflow-hidden" onclick="window.location.href='product/<?php echo $product['slug']; ?>'">
                <div class="relative aspect-square overflow-hidden bg-slate-100">
                    <img alt="<?php echo htmlspecialchars($product['title']); ?>" 
                         class="w-full h-full object-cover" 
                         src="<?php echo htmlspecialchars($image_url); ?>" />
                    <!-- <div class="absolute top-3 left-3 bg-white/95 px-2.5 py-1 rounded-lg text-xs font-black text-primary shadow-sm">
                        <?php echo strtoupper(htmlspecialchars($product['category_name'])); ?>
                    </div> 
                    <?php if ($product['condition_type']): ?>
                    <div class="absolute top-3 right-3 bg-brand-green text-white px-2.5 py-1 rounded-lg text-xs font-bold">
                        <?php echo ucfirst(str_replace('_', ' ', $product['condition_type'])); ?>
                    </div>
                    <?php endif; ?>
                    -->
                </div>
                <div class="p-4 flex flex-col flex-1">
                    <div class="flex items-center justify-between mb-1">
                        <p class="text-xl font-extrabold text-primary"><?php echo $price_display; ?></p>
                        <div class="flex items-center gap-2">
                            <button class="text-slate-400 hover:text-primary transition-colors" 
                                    onclick="bookmarkProduct(event, <?php echo $product['id']; ?>)">
                                <span class="material-symbols-outlined text-lg">bookmark</span>
                            </button>
                        </div>
                    </div>
                    <h4 class="text-slate-800 font-semibold text-sm mb-1 line-clamp-2">
                        <?php echo htmlspecialchars($product['title']); ?>
                    </h4>
                    <p class="text-xs text-slate-500 mb-3">
                        Seller: <span class="font-medium text-slate-700"><?php echo htmlspecialchars($product['seller_name']); ?></span>
                        <?php if (!empty($product['is_verified'])): ?><span class="material-symbols-outlined text-blue-500 text-xs fill-1 align-middle" title="Verified Seller">verified</span><?php endif; ?>
                    </p>
                 
                </div>
            </div>
            <?php endwhile; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="flex justify-center items-center gap-2">
            <?php if ($page > 1): ?>
            <a href="?page=<?php echo $page - 1; ?><?php echo $category_id > 0 ? '&category=' . $category_id : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($condition) ? '&condition=' . $condition : ''; ?>&sort=<?php echo $sort; ?>" 
               class="px-4 py-2 bg-white border border-slate-300 rounded-lg text-slate-700 font-medium hover:bg-slate-50">
                Previous
            </a>
            <?php endif; ?>

            <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
            <a href="?page=<?php echo $i; ?><?php echo $category_id > 0 ? '&category=' . $category_id : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($condition) ? '&condition=' . $condition : ''; ?>&sort=<?php echo $sort; ?>" 
               class="px-4 py-2 rounded-lg font-medium <?php echo $i == $page ? 'bg-primary text-white' : 'bg-white border border-slate-300 text-slate-700 hover:bg-slate-50'; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
            <a href="?page=<?php echo $page + 1; ?><?php echo $category_id > 0 ? '&category=' . $category_id : ''; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?><?php echo !empty($condition) ? '&condition=' . $condition : ''; ?>&sort=<?php echo $sort; ?>" 
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
            <h3 class="text-2xl font-bold text-slate-700 mb-2">No products found</h3>
            <p class="text-slate-500 mb-6">Try adjusting your filters or search terms</p>
            <a href="products.php" class="inline-block px-6 py-3 bg-primary text-white rounded-lg font-bold hover:bg-primary/90">
                View All Products
            </a>
        </div>
        <?php endif; ?>
    </main>

    <?php include_once 'includes/footer.php'; ?>

    <script>
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

        // Auto-submit form when sort changes
        document.querySelector('select[name="sort"]').addEventListener('change', function() {
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
