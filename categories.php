<?php
session_start();
include_once "includes/controller.php";

// Get all active categories
$categories_query = "
    SELECT c.*, 
           COUNT(DISTINCT p.id) as product_count
    FROM categories c
    LEFT JOIN products p ON c.id = p.category_id 
    WHERE c.is_active = TRUE
    GROUP BY c.id
    ORDER BY c.display_order ASC, c.name ASC
";
$categories_result = $db->query($categories_query);
$categories = [];
while ($cat = $categories_result->fetch_assoc()) {
    $categories[] = $cat;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Categories | CampMart</title>
    <script src="tailwind34.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />
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

        .category-card {
            transition: all 0.3s ease;
        }

        .category-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .category-icon {
            font-size: 3rem;
            transition: transform 0.3s ease;
        }

        .category-card:hover .category-icon {
            transform: scale(1.1);
        }
    </style>
</head>

<body class="bg-background-main min-h-screen pb-20 lg:pb-0">
    <?php include_once 'includes/header.php'; ?>

    <main class="max-w-[1440px] mx-auto px-6 py-8">
        <!-- Header Section -->
        <div class="mb-12">
            <h1 class="text-4xl lg:text-5xl font-extrabold text-brand-green mb-3">Shop by Category</h1>
            <p class="text-slate-600 text-lg">Explore our wide range of products from textbooks to tech gear</p>
        </div>

        <!-- Categories Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6">
            <?php foreach ($categories as $category): ?>
            <a href="products.php?category=<?php echo $category['id']; ?>" class="category-card">
                <div class="bg-white rounded-2xl border border-slate-200 p-8 h-full flex flex-col items-center justify-center text-center hover:border-primary hover:shadow-xl transition-all">
                    <div class="category-icon text-primary mb-4">
                        <span class="material-symbols-outlined text-5xl">
                            <?php echo htmlspecialchars($category['icon'] ?? 'category'); ?>
                        </span>
                    </div>
                    <h3 class="text-xl font-bold text-slate-900 mb-2">
                        <?php echo htmlspecialchars($category['name']); ?>
                    </h3>
                    <p class="text-sm text-slate-600 mb-4">
                        <?php echo number_format($category['product_count']); ?> item<?php echo $category['product_count'] != 1 ? 's' : ''; ?>
                    </p>
                    <div class="flex items-center gap-2 text-primary font-semibold text-sm">
                        <span>Browse</span>
                        <span class="material-symbols-outlined text-sm">arrow_right_alt</span>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>

        <!-- Featured Section -->
        <?php if (!empty($categories)): ?>
        <section class="mt-20 pt-12 border-t border-slate-200">
            <div class="bg-gradient-to-r from-primary/5 to-secondary/5 rounded-3xl border border-primary/10 p-12">
                <div class="max-w-2xl">
                    <h2 class="text-3xl font-extrabold text-brand-green mb-4">Can't find what you're looking for?</h2>
                    <p class="text-slate-600 mb-8">Use our search feature to discover exactly what you need from campus sellers.</p>
                    <form method="GET" action="products.php" class="flex gap-3">
                        <input type="text" name="search" placeholder="Search for textbooks, tech, furniture..." 
                               class="flex-1 px-6 py-3 rounded-lg border border-slate-300 focus:ring-2 focus:ring-primary/20 focus:border-primary" />
                        <button type="submit" class="px-8 py-3 bg-primary text-white font-bold rounded-lg hover:bg-primary/90 transition-all flex items-center gap-2">
                            <span class="material-symbols-outlined">search</span>
                            Search
                        </button>
                    </form>
                </div>
            </div>
        </section>
        <?php endif; ?>
    </main>

    <?php include_once 'includes/footer.php'; ?>

    <script>
        // Smooth scroll to category on load if hash is present
        if (window.location.hash) {
            const categoryName = window.location.hash.substring(1);
            const categoryElement = document.querySelector(`[data-category="${categoryName}"]`);
            if (categoryElement) {
                categoryElement.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
    </script>
</body>

</html>
