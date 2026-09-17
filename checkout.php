<?php
session_start();
require_once 'includes/controller.php';

if (!isset($_SESSION['userAppId'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['userAppId'];
$checkoutError = '';

function checkoutDeliveryFee(array $item): float
{
    return getProductDeliveryFee($item, (string) ($item['delivery_option'] ?? 'pickup'));
}

function checkoutDisplayName(array $user): string
{
    $fullName = trim((string) ($user['full_name'] ?? ''));
    if ($fullName !== '') {
        return $fullName;
    }

    $fallback = trim(((string) ($user['firstname'] ?? '')) . ' ' . ((string) ($user['lastname'] ?? '')));
    return $fallback !== '' ? $fallback : ((string) ($user['username'] ?? 'Customer'));
}

function fetchCheckoutCartItems(mysqli $db, int $userId): array
{
    $query = "
        SELECT
            c.id AS cart_id,
            c.product_id,
            c.quantity,
            c.delivery_option,
            p.title,
            p.slug,
            p.price,
            p.availability,
            p.status AS product_status,
            p.metadata,
            p.user_id AS seller_id,
            p.location AS product_location,
            pi.image_url,
            u.username AS seller_username,
            u.is_verified AS seller_is_verified,
            COALESCE(NULLIF(u.full_name, ''), NULLIF(CONCAT(u.firstname, ' ', u.lastname), ' '), u.username) AS seller_name
        FROM cart c
        JOIN products p ON c.product_id = p.id
        LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
        JOIN users u ON p.user_id = u.id
        WHERE c.user_id = ?
        ORDER BY c.created_at DESC
    ";

    $stmt = $db->prepare($query);
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }

    return $items;
}

function calculateCheckoutTotals(array $items): array
{
    $subtotal = 0.0;
    $deliveryFee = 0.0;
    $itemCount = 0;

    foreach ($items as $item) {
        $subtotal += ((float) $item['price']) * ((int) $item['quantity']);
        $deliveryFee += checkoutDeliveryFee($item);
        $itemCount += (int) $item['quantity'];
    }

    return [
        'subtotal' => $subtotal,
        'delivery_fee' => $deliveryFee,
        'grand_total' => $subtotal + $deliveryFee,
        'item_count' => $itemCount,
    ];
}

$userStmt = $db->prepare("
    SELECT id, username, firstname, lastname, full_name, email, phone, location, profile_image
    FROM users
    WHERE id = ?
    LIMIT 1
");
$userStmt->bind_param('i', $userId);
$userStmt->execute();
$currentBuyer = $userStmt->get_result()->fetch_assoc();

if (!$currentBuyer) {
    session_destroy();
    header('Location: login.php');
    exit;
}

$flashSuccess = $_SESSION['success'] ?? null;
$flashError = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

$prefillPhone = trim((string) ($currentBuyer['phone'] ?? ''));
$prefillLocation = trim((string) ($currentBuyer['location'] ?? ''));
$prefillNotes = '';
$selectedPaymentMethod = 'cash';
$selectedPaymentGateway = '';
$saveToProfile = true;

$availablePaymentMethods = getAvailablePaymentMethods();
if (empty($availablePaymentMethods)) {
    $checkoutError = 'No payment methods are currently available. Please contact support.';
}

$cartItems = fetchCheckoutCartItems($db, $userId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isAccountBlocked()) {
        $checkoutError = 'Your account has been suspended or banned. You cannot perform this action.';
    }
    if (!$checkoutError) {
    $prefillPhone = trim((string) ($_POST['phone'] ?? ''));
    $prefillLocation = trim((string) ($_POST['delivery_location'] ?? ''));
    $prefillNotes = trim((string) ($_POST['notes'] ?? ''));
    $selectedPaymentMethod = ($_POST['payment_method'] ?? 'cash');
    $selectedPaymentGateway = ($_POST['payment_gateway'] ?? '');
    $saveToProfile = isset($_POST['save_to_profile']);

    // Validate payment method is available
    $validMethods = array_keys($availablePaymentMethods);
    if (!in_array($selectedPaymentMethod, $validMethods)) {
        $selectedPaymentMethod = $validMethods[0] ?? 'cash';
    }

    // For online payments, payment gateway is required
    if (in_array($selectedPaymentMethod, ['paystack', 'flutterwave'])) {
        $selectedPaymentGateway = $selectedPaymentMethod;
    }

    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        $checkoutError = 'Your session token is invalid. Please refresh the page and try again.';
    } elseif (empty($cartItems)) {
        $checkoutError = 'Your cart is empty.';
    } elseif ($prefillPhone === '' || $prefillLocation === '') {
        $checkoutError = 'Phone number and delivery location are required.';
    } else {
        $unavailableTitles = [];
        foreach ($cartItems as $item) {
            $isAvailable = ($item['product_status'] ?? '') === 'approved' && ($item['availability'] ?? '') === 'available';
            $availableStock = getProductStockQuantity($item);
            if (!$isAvailable || $availableStock < (int) $item['quantity']) {
                $unavailableTitles[] = $item['title'];
            }
        }

        if (!empty($unavailableTitles)) {
            $checkoutError = 'Some cart items are no longer available: ' . implode(', ', $unavailableTitles) . '.';
        } else {
            $db->begin_transaction();

            try {
                if ($saveToProfile) {
                    $profileUpdate = $db->prepare("UPDATE users SET phone = ?, location = ? WHERE id = ?");
                    $profileUpdate->bind_param('ssi', $prefillPhone, $prefillLocation, $userId);
                    $profileUpdate->execute();
                }

                $isOnlinePayment = in_array($selectedPaymentMethod, ['paystack', 'flutterwave']);
                $isPod = ($selectedPaymentMethod === 'pod');
                $paymentMethodDb = $isOnlinePayment ? 'card' : 'cash';
                $paymentGatewayDb = $isOnlinePayment ? $selectedPaymentGateway : null;
                $escrowStatusDb = $isOnlinePayment ? 'held' : null;
                $storePickupDb = $isPod ? 1 : 0;

                $orderInsert = $db->prepare("
                    INSERT INTO orders (
                        order_number,
                        quantity,
                        buyer_id,
                        seller_id,
                        product_id,
                        payment_method,
                        payment_gateway,
                        delivery_location,
                        buyer_phone,
                        delivery_option,
                        notes,
                        item_price,
                        service_fee,
                        delivery_fee,
                        total_amount,
                        status,
                        payment_status,
                        escrow_status,
                        delivery_status,
                        store_pickup
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', ?, 'pending', ?)
                ");

                $reserveProduct = $db->prepare("
                    UPDATE products
                    SET metadata = ?, availability = ?
                    WHERE id = ?
                ");

                $notificationInsert = $db->prepare("
                    INSERT INTO notifications (
                        user_id,
                        title,
                        message,
                        type,
                        related_id,
                        related_type,
                        action_url
                    ) VALUES (?, ?, ?, 'transaction', ?, 'order', 'my-orders.php')
                ");

                $createdCount = 0;
                $firstOrderNumber = null;
                $createdOrderIds = [];

                foreach ($cartItems as $item) {
                    $quantity = max(1, (int) $item['quantity']);
                    $deliveryOption = (string) $item['delivery_option'];
                    $itemPrice = ((float) $item['price']) * $quantity;
                    $serviceFee = 0.0;
                    $deliveryFee = checkoutDeliveryFee($item);
                    $totalAmount = $itemPrice + $serviceFee + $deliveryFee;

                    $noteParts = [
                        'Quantity: ' . $quantity,
                        'Delivery option: ' . ucfirst($deliveryOption),
                    ];
                    if ($deliveryOption === 'pickup') {
                        $noteParts[] = 'Personal pickup at seller store: ' . $prefillLocation;
                    }
                    if ($prefillNotes !== '') {
                        $noteParts[] = 'Customer note: ' . $prefillNotes;
                    }
                    $orderNotes = implode("\n", $noteParts);

                    $sellerId = (int) $item['seller_id'];
                    $productId = (int) $item['product_id'];
                    $orderNumber = generateOrderNumber();
                    $currentStock = getProductStockQuantity($item);
                    $remainingStock = $currentStock - $quantity;

                    if ($sellerId === $userId) {
                        throw new Exception('You cannot place an order for your own product.');
                    }
                    if ($remainingStock < 0) {
                        throw new Exception('Not enough stock left for ' . $item['title'] . '.');
                    }

                    $orderInsert->bind_param(
                        'siiiissssssdddsis',
                        $orderNumber,
                        $quantity,
                        $userId,
                        $sellerId,
                        $productId,
                        $paymentMethodDb,
                        $paymentGatewayDb,
                        $prefillLocation,
                        $prefillPhone,
                        $deliveryOption,
                        $orderNotes,
                        $itemPrice,
                        $serviceFee,
                        $deliveryFee,
                        $totalAmount,
                        $escrowStatusDb,
                        $storePickupDb
                    );

                    if (!$orderInsert->execute()) {
                        throw new Exception('Failed to create an order for ' . $item['title'] . '.');
                    }

                    $orderId = (int) $db->insert_id;
                    $createdCount++;
                    $firstOrderNumber = $firstOrderNumber ?: $orderNumber;
                    $createdOrderIds[] = $orderId;

                    $updatedMetadata = setProductStockQuantity($item['metadata'] ?? null, $remainingStock);
                    $nextAvailability = $remainingStock > 0 ? 'available' : 'sold';
                    $reserveProduct->bind_param('ssi', $updatedMetadata, $nextAvailability, $productId);
                    $reserveProduct->execute();

                    $sellerTitle = 'New order received';
                    $sellerMessage = checkoutDisplayName($currentBuyer) . ' placed an order for ' . $item['title'] . '.';
                    $notificationInsert->bind_param('issi', $sellerId, $sellerTitle, $sellerMessage, $orderId);
                    $notificationInsert->execute();
                }

                $clearCart = $db->prepare("DELETE FROM cart WHERE user_id = ?");
                $clearCart->bind_param('i', $userId);
                $clearCart->execute();

                $db->commit();

                if ($isOnlinePayment) {
                    $_SESSION['pending_payment_order_ids'] = $createdOrderIds;
                    $_SESSION['pending_payment_gateway'] = $selectedPaymentGateway;
                    header('Location: payment-redirect.php');
                    exit;
                }

                $methodLabel = $isOnlinePayment ? ($selectedPaymentGateway === 'paystack' ? 'Paystack' : 'Flutterwave') . ' payment' : 'pay on delivery';
                $_SESSION['success'] = 'Checkout completed. ' . $createdCount . ' order(s) were sent to vendors using ' . $methodLabel . ($firstOrderNumber ? ' starting with ' . $firstOrderNumber . '.' : '.');
                header('Location: my-orders.php');
                exit;
            } catch (Throwable $exception) {
                $db->rollback();
                $checkoutError = $exception->getMessage();
            }
        }
    }
    } // end if (!$checkoutError)
}

if (empty($cartItems)) {
    $_SESSION['error'] = 'Your cart is empty. Add products before checking out.';
    header('Location: cart.php');
    exit;
}

$totals = calculateCheckoutTotals($cartItems);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <base href="<?php echo SITE_URL; ?>">
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Checkout | CampMart</title>
    <script src="tailwind34.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />
    <script id="tailwind-config">
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#064E3B',
                        secondary: '#F97316',
                        accent: '#FFE66D',
                        surface: '#FFFFFF',
                        canvas: '#F8FAFC',
                    },
                    fontFamily: {
                        display: ['Inter']
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }

        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 450, 'GRAD' 0, 'opsz' 24;
        }
    </style>
</head>
<body class="bg-canvas min-h-screen text-slate-900">
    <?php include_once 'includes/header.php'; ?>

    <main class="max-w-[1380px] mx-auto px-4 sm:px-6 py-6 sm:py-8">
        <nav class="flex items-center gap-2 text-xs sm:text-sm text-slate-500 mb-5">
            <a class="hover:text-primary" href="cart.php">Cart</a>
            <span class="material-symbols-outlined text-sm">chevron_right</span>
            <span class="text-slate-900 font-medium">Checkout</span>
        </nav>

        <div class="mb-6 sm:mb-8">
            <span class="inline-flex items-center gap-2 rounded-full bg-primary/10 px-3 py-1 text-[11px] sm:text-xs font-semibold text-primary mb-3">
                <span class="material-symbols-outlined text-sm">shopping_cart_checkout</span>
                Secure vendor checkout
            </span>
            <h1 class="text-2xl sm:text-3xl lg:text-4xl font-extrabold tracking-tight text-primary">Checkout</h1>
            <p class="text-sm sm:text-base text-slate-600 mt-2 max-w-2xl">
                Confirm your delivery details and payment preference. Each cart item will be turned into a vendor-facing order for processing.
            </p>
        </div>

        <?php if ($flashSuccess): ?>
            <div class="mb-5 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                <?php echo htmlspecialchars($flashSuccess); ?>
            </div>
        <?php endif; ?>

        <?php if ($flashError || $checkoutError): ?>
            <div class="mb-5 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <?php echo htmlspecialchars($checkoutError ?: $flashError); ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1.2fr)_380px] gap-6 lg:gap-8">
            <form method="POST" class="space-y-6">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(generateCSRFToken()); ?>">

                <section class="rounded-[28px] border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
                    <div class="flex items-start justify-between gap-4 mb-5">
                        <div>
                            <h2 class="text-lg sm:text-xl font-bold text-slate-900">Delivery details</h2>
                            <p class="text-sm text-slate-500 mt-1">Use your current profile details or set a new delivery location for this order.</p>
                        </div>
                        <div class="hidden sm:flex size-11 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                            <span class="material-symbols-outlined">location_on</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <label class="block">
                            <span class="block text-sm font-semibold text-slate-700 mb-2">Phone number</span>
                            <input
                                class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-primary focus:bg-white focus:ring-4 focus:ring-primary/10"
                                name="phone"
                                placeholder="080..."
                                type="text"
                                value="<?php echo htmlspecialchars($prefillPhone); ?>"
                                required
                            />
                        </label>

                        <label class="block">
                            <span class="block text-sm font-semibold text-slate-700 mb-2">Delivery location</span>
                            <input
                                class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-primary focus:bg-white focus:ring-4 focus:ring-primary/10"
                                name="delivery_location"
                                placeholder="Hostel, hall, department or preferred meetup point"
                                type="text"
                                value="<?php echo htmlspecialchars($prefillLocation); ?>"
                                required
                            />
                        </label>
                    </div>

                    <label class="block mt-4">
                        <span class="block text-sm font-semibold text-slate-700 mb-2">Order note</span>
                        <textarea
                            class="min-h-[110px] w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-primary focus:bg-white focus:ring-4 focus:ring-primary/10"
                            name="notes"
                            placeholder="Optional note for vendors, preferred pickup time, landmarks, or extra delivery instructions"
                        ><?php echo htmlspecialchars($prefillNotes); ?></textarea>
                    </label>

                    <label class="mt-4 inline-flex items-center gap-3 rounded-2xl bg-slate-50 px-4 py-3 text-sm text-slate-700">
                        <input class="size-4 rounded border-slate-300 text-primary focus:ring-primary" name="save_to_profile" type="checkbox" <?php echo $saveToProfile ? 'checked' : ''; ?> />
                        <span>Save this phone number and location to my profile for next time</span>
                    </label>
                </section>

                <section class="rounded-[28px] border border-slate-200 bg-white p-3 sm:p-6 shadow-sm">
                    <div class="flex items-start justify-between gap-4 mb-5">
                        <div>
                            <h2 class="text-lg sm:text-xl font-bold text-slate-900">Payment method</h2>
                            <p class="text-sm text-slate-500 mt-1">Choose how this order should be paid and tracked.</p>
                        </div>
                        <div class="hidden sm:flex size-11 items-center justify-center rounded-2xl bg-secondary/10 text-secondary">
                            <span class="material-symbols-outlined">payments</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 <?php echo count($availablePaymentMethods) > 1 ? 'md:grid-cols-2' : ''; ?> gap-4">
                        <?php foreach ($availablePaymentMethods as $methodKey => $method): ?>
                            <label class="cursor-pointer">
                                <input class="peer sr-only" name="payment_method" type="radio" value="<?php echo htmlspecialchars($methodKey); ?>" <?php echo $selectedPaymentMethod === $methodKey ? 'checked' : ''; ?> />
                                <div class="rounded-[24px] border-2 border-slate-200 bg-slate-50 p-4 transition peer-checked:border-<?php echo $method['color']; ?> peer-checked:bg-<?php echo $method['color']; ?>/[0.06]">
                                    <div class="flex items-center gap-3 mb-2">
                                        <div class="flex size-10 items-center justify-center rounded-2xl bg-white text-<?php echo $method['color']; ?>">
                                            <span class="material-symbols-outlined"><?php echo htmlspecialchars($method['icon']); ?></span>
                                        </div>
                                        <div>
                                            <h3 class="text-sm font-bold text-slate-900"><?php echo htmlspecialchars($method['label']); ?></h3>
                                            <p class="text-xs text-slate-500"><?php echo htmlspecialchars($method['desc']); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="payment_gateway" value="<?php echo htmlspecialchars($selectedPaymentGateway); ?>">
                </section>

                <section class="rounded-[28px] border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
                    <div class="flex items-start justify-between gap-4 mb-5">
                        <div>
                            <h2 class="text-lg sm:text-xl font-bold text-slate-900">Items being ordered</h2>
                            <p class="text-sm text-slate-500 mt-1">Each item is routed directly to the vendor that listed it.</p>
                        </div>
                        <div class="hidden sm:flex size-11 items-center justify-center rounded-2xl bg-slate-100 text-slate-700">
                            <span class="material-symbols-outlined">inventory_2</span>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <?php foreach ($cartItems as $item): ?>
                            <?php
                            $itemImage = $item['image_url'] ?: 'https://via.placeholder.com/320x320?text=No+Image';
                            $itemSubtotal = ((float) $item['price']) * ((int) $item['quantity']);
                            $itemDeliveryFee = checkoutDeliveryFee($item);
                            ?>
                            <div class="rounded-[24px] border border-slate-200 bg-slate-50/70 p-4">
                                <div class="flex flex-col sm:flex-row gap-4">
                                    <img
                                        alt="<?php echo htmlspecialchars($item['title']); ?>"
                                        class="h-28 w-full sm:w-28 rounded-2xl object-cover border border-slate-200"
                                        src="<?php echo htmlspecialchars($itemImage); ?>"
                                    />
                                    <div class="flex-1 min-w-0">
                                        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
                                            <div>
                                                <a class="block text-base font-bold leading-snug text-slate-900 hover:text-primary" href="product/<?php echo htmlspecialchars($item['slug']); ?>">
                                                    <?php echo htmlspecialchars($item['title']); ?>
                                                </a>
                                                <p class="mt-1 text-xs sm:text-sm text-slate-500">
                                                    Vendor:
                                                    <a class="font-semibold text-slate-700 hover:text-primary" href="store/<?php echo htmlspecialchars($item['seller_username']); ?>">
                                                        @<?php echo htmlspecialchars($item['seller_username']); ?>
                                                    </a>
                                                    <?php if (!empty($item['seller_is_verified'])): ?><span class="material-symbols-outlined text-blue-500 text-xs fill-1 align-middle" title="Verified Seller">verified</span><?php endif; ?>
                                                </p>
                                                <div class="mt-3 flex flex-wrap gap-2 text-xs">
                                                    <span class="rounded-full bg-white px-3 py-1 font-semibold text-slate-700 border border-slate-200">
                                                        Qty <?php echo (int) $item['quantity']; ?>
                                                    </span>
                                                    <span class="rounded-full bg-white px-3 py-1 font-semibold text-slate-700 border border-slate-200">
                                                        <?php echo ucfirst((string) $item['delivery_option']); ?>
                                                    </span>
                                                    <span class="rounded-full bg-white px-3 py-1 font-semibold text-slate-700 border border-slate-200">
                                                        Delivery fee <?php echo formatCurrency($itemDeliveryFee); ?>
                                                    </span>
                                                </div>
                                            </div>
                                            <div class="sm:text-right">
                                                <p class="text-lg font-extrabold text-primary"><?php echo formatCurrency($itemSubtotal + $itemDeliveryFee); ?></p>
                                                <p class="text-xs text-slate-500"><?php echo formatCurrency((float) $item['price']); ?> each</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <div class="flex flex-col-reverse sm:flex-row sm:items-center sm:justify-between gap-3">
                    <a class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50" href="cart.php">
                        <span class="material-symbols-outlined text-base">arrow_back</span>
                        Return to cart
                    </a>
                    <button class="inline-flex items-center justify-center gap-2 rounded-2xl bg-secondary px-5 py-3 text-sm font-bold text-white transition hover:bg-secondary/90" type="submit">
                        <span class="material-symbols-outlined text-base">check_circle</span>
                        Place order
                    </button>
                </div>
            </form>

            <aside class="xl:sticky xl:top-6 h-fit space-y-5">
                <section class="rounded-[28px] border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
                    <h2 class="text-lg font-bold text-slate-900 mb-5">Order summary</h2>

                    <div class="space-y-3 text-sm">
                        <div class="flex items-center justify-between gap-4 text-slate-600">
                            <span>Items (<?php echo (int) $totals['item_count']; ?>)</span>
                            <span class="font-semibold text-slate-900"><?php echo formatCurrency($totals['subtotal']); ?></span>
                        </div>
                        <div class="flex items-center justify-between gap-4 text-slate-600">
                            <span>Delivery fees</span>
                            <span class="font-semibold text-slate-900"><?php echo formatCurrency($totals['delivery_fee']); ?></span>
                        </div>
                        <div class="flex items-center justify-between gap-4 text-slate-600">
                            <span>Service fee</span>
                            <span class="font-semibold text-slate-900"><?php echo formatCurrency(0); ?></span>
                        </div>
                        <div class="border-t border-slate-200 pt-4 flex items-center justify-between gap-4">
                            <span class="text-base font-bold text-slate-900">Total</span>
                            <span class="text-2xl font-extrabold text-primary"><?php echo formatCurrency($totals['grand_total']); ?></span>
                        </div>
                    </div>

                    <div class="mt-5 rounded-[24px] bg-primary/[0.06] p-4">
                        <div class="flex items-start gap-3">
                            <div class="mt-0.5 flex size-10 items-center justify-center rounded-2xl bg-white text-primary">
                                <span class="material-symbols-outlined">storefront</span>
                            </div>
                            <div>
                                <h3 class="text-sm font-bold text-slate-900">Vendor-ready fulfillment</h3>
                                <p class="mt-1 text-xs leading-5 text-slate-600">
                                    Vendors will see these orders immediately and process them for delivery.
                                </p>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-[28px] border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="flex size-12 items-center justify-center overflow-hidden rounded-2xl border border-slate-200 bg-slate-100">
                            <?php if (!empty($currentBuyer['profile_image'])): ?>
                                <img alt="<?php echo htmlspecialchars(checkoutDisplayName($currentBuyer)); ?>" class="h-full w-full object-cover" src="<?php echo htmlspecialchars(SITE_URL . $currentBuyer['profile_image']); ?>" />
                            <?php else: ?>
                                <span class="text-sm font-bold text-slate-700"><?php echo htmlspecialchars(strtoupper(substr(checkoutDisplayName($currentBuyer), 0, 1))); ?></span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <p class="text-sm font-bold text-slate-900"><?php echo htmlspecialchars(checkoutDisplayName($currentBuyer)); ?></p>
                            <p class="text-xs text-slate-500">@<?php echo htmlspecialchars((string) $currentBuyer['username']); ?></p>
                        </div>
                    </div>

                    <div class="space-y-3 text-xs text-slate-600">
                        <div class="flex items-start gap-2">
                            <span class="material-symbols-outlined text-base text-slate-400">call</span>
                            <span><?php echo $prefillPhone !== '' ? htmlspecialchars($prefillPhone) : 'No phone number in profile yet'; ?></span>
                        </div>
                        <div class="flex items-start gap-2">
                            <span class="material-symbols-outlined text-base text-slate-400">place</span>
                            <span><?php echo $prefillLocation !== '' ? htmlspecialchars($prefillLocation) : 'No location saved yet'; ?></span>
                        </div>
                    </div>
                </section>
            </aside>
        </div>
    </main>

    <?php include_once 'includes/footer.php'; ?>
</body>
</html>
