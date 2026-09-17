<?php
session_start();
include_once 'includes/controller.php';

$userId = $_SESSION['userAppId'] ?? 0;

if (!$userId) {
    $_SESSION['error'] = 'Please login to view your bookmarks';
    header('Location: login.php');
    exit;
}

$bookmarked_query = "
    SELECT p.*,
           c.name AS category_name,
           u.full_name AS seller_name,
           u.username AS seller_username,
           u.is_verified,
           pi.image_url AS primary_image
    FROM products p
    INNER JOIN bookmarks b ON p.id = b.product_id
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN users u ON p.user_id = u.id
    LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
    WHERE b.user_id = ?
    AND b.bookmark_type = 'product'
    ORDER BY b.created_at DESC
";

$stmt = $db->prepare($bookmarked_query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$bookmarked_result = $stmt->get_result();
$bookmarked_products = [];

while ($row = $bookmarked_result->fetch_assoc()) {
    $bookmarked_products[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <base href="<?php echo SITE_URL; ?>">
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>My Bookmarks | CampMart</title>
    <script src="tailwind34.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet" />
    <script id="tailwind-config">
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        "primary": "#f48c25",
                        "brand-green": "#064E3B",
                        "brand-green-light": "#F0FDF4",
                        "background-main": "#F9FAFB",
                        "surface-white": "#FFFFFF",
                        "text-dark": "#1F2937",
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
        <div class="max-w-6xl mx-auto space-y-6">
            <div class="flex items-center gap-3 mb-6">
                <span class="material-symbols-outlined text-4xl text-primary">bookmark</span>
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-brand-green">My Bookmarks</h1>
                    <p class="text-slate-500 mt-1">Your saved products and items</p>
                </div>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg flex items-center gap-3" id="successMessage">
                <span class="material-symbols-outlined text-emerald-600">check_circle</span>
                <p class="flex-1"><?= htmlspecialchars($_SESSION['success']) ?></p>
                <button onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-800">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <?php unset($_SESSION['success']); endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg flex items-center gap-3" id="errorMessage">
                <span class="material-symbols-outlined text-red-600">error</span>
                <p class="flex-1"><?= htmlspecialchars($_SESSION['error']) ?></p>
                <button onclick="this.parentElement.remove()" class="text-red-600 hover:text-red-800">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <?php unset($_SESSION['error']); endif; ?>

            <?php if (count($bookmarked_products) === 0): ?>
            <div class="bg-surface-white rounded-xl border border-slate-200 shadow-sm p-12 text-center">
                <span class="material-symbols-outlined text-6xl text-slate-300 flex justify-center mb-4">bookmark_outline</span>
                <h2 class="text-2xl font-bold text-text-dark mb-2">No Bookmarks Yet</h2>
                <p class="text-slate-600 mb-6">Start bookmarking products to save them for later</p>
                <a href="products.php" class="inline-block px-6 py-3 bg-primary text-white rounded-lg font-bold hover:bg-primary/90 transition-colors">
                    Browse Products
                </a>
            </div>
            <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                <?php foreach ($bookmarked_products as $product):
                    $mainImage = $product['primary_image'] ?? '';
                    $price = (float) ($product['price'] ?? 0);
                    $priceDisplay = $price > 0 ? 'NGN ' . number_format($price, 0) : 'FREE';
                    $isAvailable = ($product['availability'] ?? '') === 'available';
                    $productUrl = !empty($product['slug']) ? productUrl($product['slug']) : '#';
                    $sellerInitial = strtoupper(substr($product['seller_name'] ?? 'S', 0, 1));
                ?>
                <div class="bg-surface-white rounded-lg border border-slate-200 shadow-sm hover:shadow-md transition-shadow overflow-hidden group">
                    <div class="relative h-48 bg-slate-100 overflow-hidden">
                        <?php if ($mainImage): ?>
                            <img src="<?= htmlspecialchars($mainImage) ?>" alt="<?= htmlspecialchars($product['title'] ?? 'Product') ?>" class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-300" />
                        <?php else: ?>
                            <div class="w-full h-full flex items-center justify-center bg-slate-200">
                                <span class="material-symbols-outlined text-4xl text-slate-400">image_not_supported</span>
                            </div>
                        <?php endif; ?>

                        <button onclick="removeBookmark(<?= (int) ($product['id'] ?? 0) ?>)" class="absolute top-2 right-2 p-2 bg-primary text-white rounded-full hover:bg-primary/90 shadow-lg transition-colors" title="Remove bookmark">
                            <span class="material-symbols-outlined">bookmark</span>
                        </button>
                    </div>

                    <div class="p-4">
                        <?php if (!empty($product['category_name'])): ?>
                        <span class="inline-block px-2 py-1 bg-brand-green-light text-brand-green text-xs font-bold rounded mb-2">
                            <?= htmlspecialchars($product['category_name']) ?>
                        </span>
                        <?php endif; ?>

                        <h3 class="font-bold text-text-dark mb-2 line-clamp-2">
                            <?= htmlspecialchars($product['title'] ?? 'Unnamed Product') ?>
                        </h3>

                        <p class="text-sm text-slate-600 mb-3 line-clamp-2">
                            <?= htmlspecialchars($product['description'] ?? 'No description') ?>
                        </p>

                        <div class="mb-4">
                            <p class="text-2xl font-bold text-primary"><?= htmlspecialchars($priceDisplay) ?></p>
                            <?php if ($isAvailable): ?>
                                <p class="text-xs text-emerald-600 font-medium">In Stock</p>
                            <?php else: ?>
                                <p class="text-xs text-red-600 font-medium">Unavailable</p>
                            <?php endif; ?>
                        </div>

                        <div class="flex items-center gap-2 mb-4 pb-4 border-b border-slate-200">
                            <div class="w-8 h-8 rounded-full bg-primary/20 flex items-center justify-center flex-shrink-0">
                                <span class="text-sm font-bold text-primary"><?= htmlspecialchars($sellerInitial) ?></span>
                            </div>
                            <div>
                                <p class="text-xs font-semibold text-text-dark">
                                    <?= htmlspecialchars($product['seller_name'] ?? 'Seller') ?>
                                    <?php if (!empty($product['is_verified'])): ?><span class="material-symbols-outlined text-blue-500 text-xs fill-1 align-middle" title="Verified Seller">verified</span><?php endif; ?>
                                </p>
                                <p class="text-xs text-slate-500">@<?= htmlspecialchars($product['seller_username'] ?? 'seller') ?></p>
                            </div>
                        </div>

                        <div class="flex gap-2">
                            <a href="<?= htmlspecialchars($productUrl) ?>" class="flex-1 px-3 py-2 bg-brand-green text-white rounded-lg text-sm font-bold hover:bg-brand-green/90 transition-colors text-center">
                                View Product
                            </a>
                            <button onclick="addToCart(<?= (int) ($product['id'] ?? 0) ?>)" class="flex-1 px-3 py-2 bg-primary text-white rounded-lg text-sm font-bold hover:bg-primary/90 transition-colors disabled:opacity-50 disabled:cursor-not-allowed" title="Add to cart" <?= $isAvailable ? '' : 'disabled' ?>>
                                <span class="material-symbols-outlined text-lg inline">shopping_cart</span>
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <div class="bg-surface-white rounded-lg border border-slate-200 shadow-sm p-4">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-slate-600">Total Bookmarks</p>
                        <p class="text-3xl font-bold text-primary"><?= count($bookmarked_products) ?></p>
                    </div>
                    <span class="material-symbols-outlined text-6xl text-slate-200">bookmark</span>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <script>
        setTimeout(() => {
            const successMsg = document.getElementById('successMessage');
            const errorMsg = document.getElementById('errorMessage');
            if (successMsg) successMsg.remove();
            if (errorMsg) errorMsg.remove();
        }, 5000);

        async function removeBookmark(productId) {
            if (!confirm('Remove this item from bookmarks?')) {
                return;
            }

            try {
                const response = await fetch('api/bookmark.php', {
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

                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Failed to remove bookmark');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to remove bookmark');
            }
        }

        async function addToCart(productId) {
            const formData = new FormData();
            formData.append('product_id', productId);
            formData.append('quantity', 1);

            try {
                const response = await fetch('api/add-to-cart.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    alert(data.message || 'Product added to cart!');
                } else {
                    alert(data.message || 'Error adding to cart');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Failed to add product to cart');
            }
        }
    </script>
</body>
</html>
