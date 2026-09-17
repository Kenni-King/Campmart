<?php
session_start();
require_once 'includes/controller.php';

if (!isset($_SESSION['userAppId'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['userAppId'];

$cart_query = "
    SELECT c.*, 
           p.title, p.price, p.slug, p.condition_type, p.availability, p.metadata,
           p.user_id as seller_id,
           pi.image_url,
           u.username as seller_name,
           u.is_verified
    FROM cart c
    JOIN products p ON c.product_id = p.id
    LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = TRUE
    JOIN users u ON p.user_id = u.id
    WHERE c.user_id = ?
    ORDER BY c.created_at DESC
";

$stmt = $db->prepare($cart_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cart_result = $stmt->get_result();
$cart_items = [];
while ($item = $cart_result->fetch_assoc()) {
    $cart_items[] = $item;
}

$subtotal = 0;
$delivery_fee = 0;
$total_items = 0;

foreach ($cart_items as $item) {
    $subtotal += $item['price'] * $item['quantity'];
    $total_items += $item['quantity'];
    if ($item['delivery_option'] == 'riders') {
        $delivery_fee += getProductDeliveryFee($item, 'riders');
    }
}

$total = $subtotal + $delivery_fee;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <base href="<?php echo SITE_URL; ?>">
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Shopping Cart | CampMart</title>
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
    </style>
</head>

<body class="bg-background-main min-h-screen">
    <?php include_once 'includes/header.php'; ?>

    <main class="max-w-[1440px] mx-auto px-4 sm:px-6 py-6 sm:py-8">
        <nav class="flex items-center gap-1.5 sm:gap-2 text-xs sm:text-sm font-medium text-slate-500 mb-5 sm:mb-6">
            <a class="hover:text-primary" href="index.php">Home</a>
            <span class="material-symbols-outlined text-sm">chevron_right</span>
            <span class="text-slate-900">Shopping Cart</span>
        </nav>

        <div class="mb-6 sm:mb-8">
            <h1 class="text-2xl sm:text-3xl font-extrabold text-brand-green mb-2">Shopping Cart</h1>
            <p class="text-sm sm:text-base text-slate-600"><?php echo $total_items; ?> item<?php echo $total_items != 1 ? 's' : ''; ?> in your cart</p>
        </div>

        <?php if (empty($cart_items)): ?>
            <div class="bg-white rounded-2xl border border-slate-200 p-8 sm:p-12 text-center">
                <span class="material-symbols-outlined text-6xl sm:text-8xl text-slate-300 mb-4">shopping_cart</span>
                <h3 class="text-xl sm:text-2xl font-bold text-slate-700 mb-2">Your cart is empty</h3>
                <p class="text-sm sm:text-base text-slate-500 mb-6">Start shopping and add items to your cart!</p>
                <a href="products.php" class="inline-flex items-center gap-2 px-6 py-3 bg-primary text-white rounded-xl font-bold hover:bg-primary/90 transition-all">
                    <span class="material-symbols-outlined">storefront</span>
                    Browse Products
                </a>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-5 lg:gap-8">
                <div class="lg:col-span-2 space-y-4">
                    <?php foreach ($cart_items as $item): 
                        $image_url = $item['image_url'] ?: 'https://via.placeholder.com/400x400?text=No+Image';
                        $is_available = $item['availability'] == 'available';
                        $item_delivery_fee = getProductDeliveryFee($item, 'riders');
                    ?>
                    <div class="bg-white rounded-2xl border border-slate-200 p-4 sm:p-5 lg:p-6 <?php echo !$is_available ? 'opacity-60' : ''; ?>" data-cart-item="<?php echo $item['id']; ?>">
                        <div class="flex flex-col sm:flex-row gap-4 sm:gap-5 lg:gap-6">
                            <a href="product/<?php echo $item['slug']; ?>" class="shrink-0 self-start w-full sm:w-auto">
                                <img src="<?php echo htmlspecialchars($image_url); ?>" 
                                     alt="<?php echo htmlspecialchars($item['title']); ?>" 
                                     class="w-full sm:w-32 h-48 sm:h-32 object-cover rounded-xl border border-slate-100" />
                            </a>

                            <div class="flex-1 min-w-0">
                                <div class="flex items-start justify-between gap-3 mb-3">
                                    <div class="flex-1 min-w-0">
                                        <a href="product/<?php echo $item['slug']; ?>" class="text-base sm:text-lg font-bold text-slate-900 hover:text-primary mb-1 block leading-snug">
                                            <?php echo htmlspecialchars($item['title']); ?>
                                        </a>
                                        <p class="text-xs sm:text-sm text-slate-500 mb-2">
                                            Seller: <span class="font-medium text-slate-700"><?php echo htmlspecialchars($item['seller_name']); ?></span>
                                            <?php if (!empty($item['is_verified'])): ?><span class="material-symbols-outlined text-blue-500 text-xs fill-1 align-middle" title="Verified Seller">verified</span><?php endif; ?>
                                        </p>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="inline-flex items-center px-2 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded">
                                                <?php echo ucfirst(str_replace('_', ' ', $item['condition_type'])); ?>
                                            </span>
                                            <?php if (!$is_available): ?>
                                            <span class="inline-flex items-center px-2 py-1 bg-red-100 text-red-700 text-xs font-semibold rounded">
                                                <span class="material-symbols-outlined text-xs mr-1">info</span>
                                                Not Available
                                            </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <button onclick="removeFromCart(<?php echo $item['id']; ?>)" 
                                            class="shrink-0 text-slate-400 hover:text-red-500 transition-colors p-2 rounded-lg hover:bg-red-50"
                                            title="Remove from cart">
                                        <span class="material-symbols-outlined">delete</span>
                                    </button>
                                </div>

                                <div class="mb-4">
                                    <label class="block text-sm font-medium text-slate-700 mb-2">Delivery Option</label>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                        <label class="cursor-pointer">
                                            <input type="radio" 
                                                   name="delivery_<?php echo $item['id']; ?>" 
                                                   value="pickup" 
                                                   <?php echo $item['delivery_option'] == 'pickup' ? 'checked' : ''; ?>
                                                   onchange="updateDeliveryOption(<?php echo $item['id']; ?>, 'pickup')"
                                                   class="peer sr-only" />
                                            <div class="border-2 border-slate-200 rounded-xl p-3 peer-checked:border-primary peer-checked:bg-primary/5 transition-all">
                                                <div class="flex items-center gap-2">
                                                    <span class="material-symbols-outlined text-sm">storefront</span>
                                                    <span class="font-semibold text-sm">Personal Pickup</span>
                                                </div>
                                                <p class="text-xs text-slate-500 mt-1">Free</p>
                                            </div>
                                        </label>
                                        <label class="cursor-pointer">
                                            <input type="radio" 
                                                   name="delivery_<?php echo $item['id']; ?>" 
                                                   value="riders" 
                                                   <?php echo $item['delivery_option'] == 'riders' ? 'checked' : ''; ?>
                                                   onchange="updateDeliveryOption(<?php echo $item['id']; ?>, 'riders')"
                                                   class="peer sr-only" />
                                            <div class="border-2 border-slate-200 rounded-xl p-3 peer-checked:border-primary peer-checked:bg-primary/5 transition-all">
                                                <div class="flex items-center gap-2">
                                                    <span class="material-symbols-outlined text-sm">motorcycle</span>
                                                    <span class="font-semibold text-sm">Platform Riders</span>
                                                </div>
                                                <p class="text-xs text-slate-500 mt-1"><?php echo formatCurrency($item_delivery_fee); ?></p>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                                    <div class="flex flex-col gap-2">
                                        <span class="text-sm font-medium text-slate-700">Quantity</span>
                                        <div class="inline-flex items-center self-start border border-slate-200 rounded-xl overflow-hidden bg-white">
                                            <button onclick="updateQuantity(<?php echo $item['id']; ?>, -1)" 
                                                    class="px-3 py-2.5 hover:bg-slate-50 transition-colors">
                                                <span class="material-symbols-outlined text-sm">remove</span>
                                            </button>
                                            <span class="min-w-12 px-4 py-2.5 text-center font-bold text-slate-900 border-x border-slate-200" id="qty-<?php echo $item['id']; ?>">
                                                <?php echo $item['quantity']; ?>
                                            </span>
                                            <button onclick="updateQuantity(<?php echo $item['id']; ?>, 1)" 
                                                    class="px-3 py-2.5 hover:bg-slate-50 transition-colors">
                                                <span class="material-symbols-outlined text-sm">add</span>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="sm:text-right">
                                        <p class="text-xl sm:text-2xl font-extrabold text-primary"><?php echo formatCurrency($item['price'] * $item['quantity']); ?></p>
                                        <?php if ($item['quantity'] > 1): ?>
                                        <p class="text-xs text-slate-500"><?php echo formatCurrency($item['price']); ?> each</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="lg:col-span-1">
                    <div class="bg-white rounded-2xl border border-slate-200 p-5 sm:p-6 lg:sticky lg:top-6">
                        <h2 class="text-lg sm:text-xl font-bold text-slate-900 mb-5 sm:mb-6">Order Summary</h2>
                        
                        <div class="space-y-3 mb-6">
                            <div class="flex justify-between gap-4 text-sm sm:text-base text-slate-600">
                                <span>Subtotal (<?php echo $total_items; ?> items)</span>
                                <span class="font-semibold"><?php echo formatCurrency($subtotal); ?></span>
                            </div>
                            <div class="flex justify-between gap-4 text-sm sm:text-base text-slate-600">
                                <span>Delivery Fee</span>
                                <span class="font-semibold" id="deliveryFeeDisplay"><?php echo formatCurrency($delivery_fee); ?></span>
                            </div>
                            <div class="border-t border-slate-200 pt-3">
                                <div class="flex justify-between items-center">
                                    <span class="text-base sm:text-lg font-bold text-slate-900">Total</span>
                                    <span class="text-xl sm:text-2xl font-extrabold text-primary" id="totalDisplay"><?php echo formatCurrency($total); ?></span>
                                </div>
                            </div>
                        </div>

                        <button onclick="proceedToCheckout()" 
                                class="w-full flex items-center justify-center gap-2 px-5 py-3.5 bg-secondary text-white rounded-xl font-bold hover:bg-secondary/90 transition-all mb-3 text-sm sm:text-base">
                            <span class="material-symbols-outlined">shopping_cart_checkout</span>
                            Proceed to Checkout
                        </button>

                        <a href="products.php" class="w-full flex items-center justify-center gap-2 px-5 py-3 border border-slate-200 text-slate-700 rounded-xl font-medium hover:bg-slate-50 transition-all text-sm sm:text-base">
                            <span class="material-symbols-outlined">arrow_back</span>
                            Continue Shopping
                        </a>

                        <div class="mt-5 sm:mt-6 pt-5 sm:pt-6 border-t border-slate-200">
                            <div class="flex items-center gap-2 text-xs sm:text-sm text-slate-600 mb-2">
                                <span class="material-symbols-outlined text-green-600">verified_user</span>
                                <span>Secure Campus Transaction</span>
                            </div>
                            <div class="flex items-center gap-2 text-xs sm:text-sm text-slate-600">
                                <span class="material-symbols-outlined text-green-600">support_agent</span>
                                <span>24/7 Support Available</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </main>

    <?php include_once 'includes/footer.php'; ?>

    <script>
        async function updateQuantity(cartId, change) {
            const qtyElement = document.getElementById(`qty-${cartId}`);
            const currentQty = parseInt(qtyElement.textContent);
            const newQty = currentQty + change;

            if (newQty < 1) return;

            try {
                const formData = new FormData();
                formData.append('cart_id', cartId);
                formData.append('quantity', newQty);
                formData.append('action', 'update_quantity');

                const response = await fetch('api/update-cart.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    location.reload();
                } else {
                    showNotification(data.message || 'Failed to update quantity', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Failed to update quantity', 'error');
            }
        }

        async function updateDeliveryOption(cartId, option) {
            try {
                const formData = new FormData();
                formData.append('cart_id', cartId);
                formData.append('delivery_option', option);
                formData.append('action', 'update_delivery');

                const response = await fetch('api/update-cart.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    location.reload();
                } else {
                    showNotification(data.message || 'Failed to update delivery option', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Failed to update delivery option', 'error');
            }
        }

        async function removeFromCart(cartId) {
            if (!confirm('Are you sure you want to remove this item from your cart?')) {
                return;
            }

            try {
                const formData = new FormData();
                formData.append('cart_id', cartId);
                formData.append('action', 'remove');

                const response = await fetch('api/update-cart.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    showNotification('Item removed from cart', 'success');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(data.message || 'Failed to remove item', 'error');
                }
            } catch (error) {
                console.error('Error:', error);
                showNotification('Failed to remove item', 'error');
            }
        }

        function proceedToCheckout() {
            const unavailableItems = document.querySelectorAll('[data-cart-item].opacity-60');
            if (unavailableItems.length > 0) {
                showNotification('Please remove unavailable items before checkout', 'error');
                return;
            }

            window.location.href = 'checkout.php';
        }

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
                transition: opacity 0.3s, transform 0.3s;
            }
        `;
        document.head.appendChild(style);
    </script>
</body>

</html>
