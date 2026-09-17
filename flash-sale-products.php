<?php
session_start();
include_once 'includes/controller.php';

if (!isset($userId) || !isset($currentUser) || !in_array($currentUser['role'], ['admin','superadmin'])) {
    header('Location: index.php');
    exit;
}

$sale_id = isset($_GET['sale_id']) ? (int)$_GET['sale_id'] : 0;
if (!$sale_id) {
    header('Location: flash-sales.php');
    exit;
}

$sale = $db->query("SELECT * FROM flash_sales WHERE id = $sale_id LIMIT 1")->fetch_assoc();
if (!$sale) {
    header('Location: flash-sales.php');
    exit;
}

$products = $db->query("SELECT id, title, price FROM products WHERE status = 'approved' ORDER BY created_at DESC LIMIT 200");
$sale_products = $db->query("SELECT fsp.*, p.title AS product_title, p.price FROM flash_sale_products fsp JOIN products p ON fsp.product_id = p.id WHERE fsp.flash_sale_id = $sale_id ORDER BY fsp.created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Flash Sale Products | CampMart Admin</title>
    <script src="tailwind34.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />
    <script id="tailwind-config">
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#f48c25',
                        secondary: '#FF6B35',
                        accent: '#FFE66D',
                        'brand-green': '#064E3B',
                        'background-main': '#F9FAFB',
                        'surface-white': '#FFFFFF',
                        'text-dark': '#1F2937',
                    },
                    fontFamily: { display: ['Inter'] }
                }
            }
        }
    </script>
    <style>
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    </style>
</head>
<body class="bg-background-main min-h-screen text-text-dark">
    <?php include_once 'includes/user-nav.php'; ?>
    <main class="flex-1 overflow-y-auto bg-background-main p-4 md:p-6 lg:p-8">
        <div class="max-w-7xl mx-auto space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500">Admin / Flash Sales</p>
                    <h1 class="text-3xl font-bold text-brand-green">Assign Products</h1>
                    <p class="text-slate-500">Sale: <?= htmlspecialchars($sale['title']) ?></p>
                </div>
                <div class="flex gap-2">
                    <a href="flash-sales.php" class="px-4 py-2 bg-white border border-slate-200 text-slate-700 rounded-lg font-medium hover:bg-slate-50">Back to Sales</a>
                </div>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <section class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 lg:col-span-2 space-y-4">
                    <div class="flex items-center justify-between">
                        <h2 class="text-lg font-semibold">Products in this Sale</h2>
                        <p class="text-sm text-slate-500">Total: <?= $sale_products ? $sale_products->num_rows : 0 ?></p>
                    </div>
                    <div class="divide-y divide-slate-100">
                        <?php if ($sale_products && $sale_products->num_rows > 0): ?>
                            <?php while($sp = $sale_products->fetch_assoc()): ?>
                                <div class="py-4 flex items-center justify-between gap-4">
                                    <div class="space-y-1">
                                        <p class="text-sm text-slate-500">Product ID: <?= (int)$sp['product_id'] ?></p>
                                        <p class="text-lg font-semibold text-text-dark"><?= htmlspecialchars($sp['product_title']) ?></p>
                                        <p class="text-sm text-slate-600">Base Price: ₦<?= number_format($sp['original_price']) ?> · Sale Price: ₦<?= number_format($sp['sale_price']) ?> (<?= number_format($sp['discount_percentage']) ?>% off)</p>
                                        <p class="text-xs text-slate-500">Sold: <?= (int)($sp['sold_count'] ?? 0) ?><?= $sp['stock_limit'] ? ' / ' . (int)$sp['stock_limit'] . ' limit' : '' ?></p>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button onclick="removeProduct(<?= $sp['id'] ?>)" class="p-2 text-red-600 hover:bg-red-50 rounded-lg">
                                            <span class="material-symbols-outlined">delete</span>
                                        </button>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="py-8 text-center text-slate-500">No products assigned yet.</p>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-4">
                    <div>
                        <h2 class="text-lg font-semibold">Add Product</h2>
                        <p class="text-sm text-slate-500">Select a product and set a discount.</p>
                    </div>
                    <form onsubmit="addProduct(event)" class="space-y-3" id="addProductForm">
                        <input type="hidden" name="flash_sale_id" value="<?= $sale_id ?>" />
                        <div>
                            <label class="text-sm font-medium text-slate-700">Product</label>
                            <select name="product_id" class="mt-1 block w-full rounded-lg border-slate-200 focus:border-primary focus:ring-primary" onchange="updatePreview()" required>
                                <?php if ($products && $products->num_rows > 0): ?>
                                    <?php while($p = $products->fetch_assoc()): ?>
                                        <option value="<?= $p['id'] ?>" data-price="<?= $p['price'] ?>"><?= htmlspecialchars($p['title']) ?> (₦<?= number_format($p['price']) ?>)</option>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <option disabled>No products available</option>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700">Discount %</label>
                            <input type="number" name="discount_percentage" min="1" max="100" required class="mt-1 block w-full rounded-lg border-slate-200 focus:border-primary focus:ring-primary" value="<?= htmlspecialchars($sale['discount_percentage']) ?>" oninput="updatePreview()" />
                        </div>
                        <div>
                            <label class="text-sm font-medium text-slate-700">Stock Limit (optional)</label>
                            <input type="number" name="stock_limit" min="0" class="mt-1 block w-full rounded-lg border-slate-200 focus:border-primary focus:ring-primary" placeholder="Leave empty for unlimited" />
                        </div>
                        <div id="pricePreview" class="hidden p-4 bg-slate-50 border border-slate-200 rounded-lg text-sm space-y-1">
                            <div class="flex justify-between"><span class="text-slate-600">Original price</span><span id="previewOriginal" class="font-semibold">₦0</span></div>
                            <div class="flex justify-between"><span class="text-slate-600">Discount</span><span id="previewDiscount" class="text-secondary font-semibold">-₦0</span></div>
                            <div class="flex justify-between border-t pt-1"><span class="font-semibold text-text-dark">Sale price</span><span id="previewSale" class="font-semibold text-secondary">₦0</span></div>
                        </div>
                        <button class="w-full inline-flex justify-center items-center gap-2 px-4 py-2 bg-primary text-white font-semibold rounded-lg hover:bg-primary/90">
                            <span class="material-symbols-outlined">add</span>
                            Add to Sale
                        </button>
                    </form>
                </section>
            </div>
        </div>
    </main>

    <script>
        function updatePreview() {
            const form = document.getElementById('addProductForm');
            const select = form.product_id;
            const option = select.options[select.selectedIndex];
            const price = parseFloat(option?.dataset.price || 0);
            const discount = parseFloat(form.discount_percentage.value || 0);
            if (!price || !discount) {
                document.getElementById('pricePreview').classList.add('hidden');
                return;
            }
            const discountAmount = price * (discount / 100);
            const salePrice = price - discountAmount;
            document.getElementById('previewOriginal').textContent = '₦' + price.toLocaleString();
            document.getElementById('previewDiscount').textContent = '-₦' + discountAmount.toLocaleString();
            document.getElementById('previewSale').textContent = '₦' + salePrice.toLocaleString();
            document.getElementById('pricePreview').classList.remove('hidden');
        }

        function addProduct(e) {
            e.preventDefault();
            const form = e.target;
            const payload = {
                sale_id: <?= $sale_id ?>,
                product_id: form.product_id.value,
                discount_percentage: form.discount_percentage.value,
                stock_limit: form.stock_limit.value
            };
            fetch('api/admin/add-product-to-sale.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    alert('Product added');
                    location.reload();
                } else {
                    alert(data.message || 'Unable to add product');
                }
            }).catch(err => {
                console.error(err);
                alert('An error occurred');
            });
        }

        function removeProduct(id) {
            if (!confirm('Remove this product from the sale?')) return;
            fetch('api/admin/remove-product-from-sale.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ flash_product_id: id })
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    alert('Removed');
                    location.reload();
                } else {
                    alert(data.message || 'Unable to remove');
                }
            }).catch(err => {
                console.error(err);
                alert('An error occurred');
            });
        }
    </script>
</body>
</html>
