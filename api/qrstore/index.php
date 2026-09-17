<?php
session_start();

$requestPath = trim(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH), '/');
$parts = explode('/', $requestPath);
$storeId = '';
for ($i = 0; $i < count($parts); $i++) {
    if ($parts[$i] === 'qrstore' && isset($parts[$i + 1])) {
        $storeId = $parts[$i + 1];
        break;
    }
}
$storeId = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['store'] ?? $storeId);

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? '';

$docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');
$scriptFile = str_replace('\\', '/', __FILE__);
$scriptRelPath = str_replace($docRoot, '', $scriptFile);
$scriptDir = dirname($scriptRelPath);

$siteRoot = dirname(dirname(dirname($scriptRelPath)));
$apiRoot = $siteRoot . '/api';

$apiBaseUrl = rtrim($scheme . '://' . $host . $apiRoot, '/');
$siteBaseUrl = rtrim($scheme . '://' . $host . $siteRoot, '/');

if (!isset($_SESSION['userAppId'])) {
    header('Location: '.$apiBaseUrl.'/qrstore/auth.php?store='.$storeId);
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>QR Store</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            brand: {
              50: '#fff7ed',
              100: '#ffedd5',
              500: '#f97316',
              600: '#ea580c',
              700: '#c2410c'
            }
          },
          boxShadow: {
            soft: '0 12px 30px rgba(15, 23, 42, 0.08)'
          }
        }
      }
    }
  </script>
  <style>
    html { scroll-behavior: smooth; }
    body { font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; }
    .no-scrollbar::-webkit-scrollbar { display: none; }
    .no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
  </style>
</head>
<body class="bg-orange-50/40 text-slate-900 antialiased">
  <header class="sticky top-0 z-30 border-b border-orange-200 bg-gradient-to-br from-orange-600 via-orange-500 to-amber-400 text-white shadow-soft">
    <div class="mx-auto flex w-full max-w-6xl items-center justify-between gap-3 px-3 py-3 sm:px-4">
      <div class="flex min-w-0 items-center gap-3">
        <img id="businessLogo" class="h-12 w-12 shrink-0 rounded-lg border border-white/50 bg-white/15 object-cover" alt="">
        <div class="min-w-0">
          <h1 id="businessName" class="truncate text-lg font-black leading-tight tracking-normal sm:text-2xl">QR Store</h1>
          <p id="businessMeta" class="truncate text-xs font-semibold text-white/85 sm:text-sm">Loading store...</p>
        </div>
      </div>
      <div class="flex shrink-0 items-center gap-2">
        <button class="flex h-7 w-7 items-center justify-center rounded-full bg-white/95 text-xl text-orange-700 shadow-sm ring-1 ring-white/50" type="button" data-open-history="1" aria-label="Order history">
          <i class="bi bi-clock-history"></i>
        </button>
        <button class="relative flex h-7 w-7 items-center justify-center rounded-full bg-white text-xl text-orange-700 shadow-sm ring-1 ring-white/50" type="button" data-show-cart="1" aria-label="Open cart">
          <i class="bi bi-cart-check-fill"></i>
          <span id="cartCount" class="absolute -right-1 -top-1 flex h-5 min-w-5 items-center justify-center rounded-full bg-slate-950 px-1 text-[10px] font-black text-white">0</span>
        </button>
      </div>
    </div>
  </header>

  <main class="mx-auto w-full max-w-6xl px-3 pb-24 sm:px-4 lg:pb-8">
    <div id="status" class="py-12 text-center text-sm font-semibold text-slate-500">Loading products...</div>
    <div id="storeContent" class="hidden">
      <section class="sticky top-[77px] z-20 -mx-3 border-b border-orange-100 bg-orange-50/95 px-3 py-2 backdrop-blur sm:-mx-4 sm:px-4 md:top-[65px]">
        <div id="flowSteps" class="hidden grid-cols-4 gap-1.5 lg:hidden"></div>
        <nav id="categoryStrip" class="no-scrollbar mt-2 flex gap-2 overflow-x-auto" aria-label="Product categories"></nav>
      </section>
      <div class="grid gap-4 py-4 lg:grid-cols-[minmax(0,1fr)_360px]">
        <section id="storeScreen">
          <div id="productGrid" class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4"></div>
        </section>
        <aside id="cartPanel" class="overflow-hidden rounded-lg border border-orange-100 bg-white shadow-soft lg:sticky lg:top-24 lg:h-fit">
          <div id="cartScreen">
            <div class="flex items-center justify-between border-b border-orange-100 px-3 py-3">
              <h2 class="text-sm font-black">Your Order</h2>
              <button class="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-bold text-slate-600" type="button" onclick="clearCart()">Clear</button>
            </div>
            <div id="cartItems" class="max-h-80 overflow-y-auto"></div>
            <div class="flex items-center justify-between bg-orange-50 px-3 py-3 text-base font-black">
              <span>Total</span>
              <span id="cartTotal">NGN 0</span>
            </div>
            <div class="border-t border-orange-100 p-3 lg:hidden">
              <button type="button" data-go-payment="1" class="flex h-11 w-full items-center justify-center rounded-lg bg-orange-600 px-4 text-sm font-black text-white shadow-sm">
                Continue to payment
              </button>
            </div>
          </div>
          <div id="paymentScreen" class="p-3">
            <div class="mb-3 hidden items-center gap-2 lg:hidden" id="paymentBackRow">
              <button type="button" data-go-cart="1" class="rounded-lg border border-orange-200 px-3 py-1.5 text-xs font-black text-orange-700">
                <i class="bi bi-arrow-left"></i> Cart
              </button>
              <span class="text-xs font-bold text-slate-500">Payment & checkout</span>
            </div>
            <label class="mb-1 mt-2 block text-[11px] font-black uppercase text-slate-500" for="customerName">Name</label>
            <input id="customerName" class="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-100" type="text" placeholder="Your name" required>
            <label class="mb-1 mt-2 block text-[11px] font-black uppercase text-slate-500" for="customerPhone">Phone</label>
            <input id="customerPhone" class="h-10 w-full rounded-lg border border-slate-200 px-3 text-sm outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-100" type="tel" placeholder="Phone number" required>
            <div id="deliveryRequestWrap" class="mt-3 hidden rounded-lg border border-orange-100 bg-orange-50 p-2.5">
              <label class="flex items-start gap-2 text-[11px] font-black text-slate-800" for="requestDelivery">
                <input id="requestDelivery" class="mt-0.5 h-4 w-4 rounded border-orange-300 text-orange-600 focus:ring-orange-500" type="checkbox">
                <span>I want this order delivered</span>
              </label>
              <div id="deliveryDetails" class="mt-2.5 hidden">
                <label class="mb-1 block text-[11px] font-black uppercase text-slate-500" for="deliveryAddress">Delivery address</label>
                <textarea id="deliveryAddress" class="min-h-20 w-full rounded-lg border border-slate-200 px-3 py-2 text-xs outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-100" placeholder="House number, street, area, city"></textarea>
                <label class="mb-1 mt-2 block text-[11px] font-black uppercase text-slate-500" for="deliveryNote">Delivery note</label>
                <textarea id="deliveryNote" class="min-h-16 w-full rounded-lg border border-slate-200 px-3 py-2 text-xs outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-100" placeholder="Landmark, preferred time, instructions"></textarea>
              </div>
            </div>
            <label class="mb-1 mt-3 block text-[11px] font-black uppercase text-slate-500">Pay To</label>
            <div id="paymentMethods"></div>
            <div id="paidCheckWrap" class="mt-3 flex items-start gap-2 rounded-lg bg-orange-50 p-2">
              <input id="paidCheck" class="mt-1 h-4 w-4 rounded border-orange-300 text-orange-600 focus:ring-orange-500" type="checkbox" required>
              <label id="paidCheckLabel" class="text-xs font-semibold text-slate-700" for="paidCheck">I have made payment to the selected account</label>
            </div>
            <button id="checkoutButton" class="mt-3 flex h-11 w-full items-center justify-center rounded-lg bg-orange-600 px-4 text-sm font-black text-white shadow-sm transition hover:bg-orange-700" type="button" data-checkout="1">
              <i class="bi bi-check2-circle me-1"></i> Mark Paid & Checkout
            </button>
          </div>
        </aside>
      </div>
    </div>
  </main>

  <button id="mobileCartButton" class="fixed inset-x-3 bottom-3 z-40 hidden items-center justify-between rounded-lg bg-slate-950 px-4 py-3 text-sm font-black text-white shadow-2xl lg:hidden" type="button" data-show-cart="1">
    <span><i class="bi bi-cart-check-fill mr-1"></i><span id="mobileCartCount">0</span> items</span>
    <span id="mobileCartTotal">NGN 0</span>
  </button>

  <div id="qtyModal" class="fixed inset-0 z-50 hidden items-end justify-center bg-slate-950/55 p-3 sm:items-center">
    <div class="w-full max-w-sm rounded-2xl bg-white shadow-2xl">
      <div class="flex items-start justify-between border-b border-orange-100 p-4">
        <div class="min-w-0">
          <p class="text-[11px] font-black uppercase text-orange-600">Add item</p>
          <h2 id="modalItemName" class="mt-1 line-clamp-2 text-base font-black leading-tight text-slate-950">Item</h2>
          <p id="modalItemPrice" class="mt-1 text-sm font-black text-slate-700">NGN 0</p>
        </div>
        <button type="button" class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 text-slate-600" data-close-qty-modal="1" aria-label="Close">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
      <div class="p-4">
        <label class="mb-2 block text-[11px] font-black uppercase text-slate-500" for="modalQty">Quantity</label>
        <div class="flex items-center gap-3 rounded-2xl bg-orange-50 p-2">
          <button type="button" class="flex h-11 w-11 items-center justify-center rounded-xl bg-white text-lg font-black text-slate-800 shadow-sm" data-modal-qty="-1">-</button>
          <input id="modalQty" type="number" min="1" value="1" class="h-11 min-w-0 flex-1 rounded-xl border border-orange-100 bg-white text-center text-lg font-black outline-none focus:border-orange-500 focus:ring-2 focus:ring-orange-100">
          <button type="button" class="flex h-11 w-11 items-center justify-center rounded-xl bg-white text-lg font-black text-slate-800 shadow-sm" data-modal-qty="1">+</button>
        </div>
        <label class="mt-3 flex items-start gap-2 rounded-xl bg-slate-50 p-3 text-xs font-bold text-slate-700">
          <input id="continueShoppingCheck" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-orange-300 text-orange-600 focus:ring-orange-500" checked>
          <span>Continue shopping after adding this item</span>
        </label>
        <button type="button" class="mt-4 flex h-12 w-full items-center justify-center rounded-xl bg-orange-600 text-sm font-black text-white shadow-sm" data-confirm-qty="1">
          <i class="bi bi-bag-plus mr-2"></i>Add to cart
        </button>
      </div>
    </div>
  </div>

  <div id="successModal" class="fixed inset-0 z-50 hidden items-center justify-center bg-slate-950/60 p-4">
    <div class="w-full max-w-sm rounded-2xl bg-white p-5 text-center shadow-2xl">
      <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-orange-100 text-3xl text-orange-600">
        <i class="bi bi-check2-circle"></i>
      </div>
      <h2 class="mt-4 text-lg font-black text-slate-950">Order received</h2>
      <p id="successMessage" class="mt-2 text-sm font-semibold leading-relaxed text-slate-600">Your order has been sent.</p>
      <button type="button" class="mt-5 h-11 w-full rounded-xl bg-orange-600 text-sm font-black text-white" data-close-success="1">Continue shopping</button>
      <button type="button" class="mt-2 h-11 w-full rounded-xl border border-orange-200 bg-white text-sm font-black text-orange-700" data-open-history="1">Track order</button>
    </div>
  </div>

  <div id="historyModal" class="fixed inset-0 z-50 hidden items-stretch justify-center bg-slate-950/60 p-0 sm:items-center sm:p-3">
    <div class="flex h-full max-h-none w-full max-w-none flex-col overflow-hidden rounded-none bg-white shadow-2xl sm:h-auto sm:max-h-[90vh] sm:max-w-2xl sm:rounded-2xl">
      <div class="flex items-center justify-between border-b border-orange-100 p-3 sm:p-4">
        <div>
          <p class="text-[10px] font-black uppercase text-orange-600">Delivery tracking</p>
          <h2 class="text-base font-black text-slate-950 sm:text-lg">Recent orders</h2>
        </div>
        <button type="button" class="flex h-9 w-9 items-center justify-center rounded-full bg-slate-100 text-slate-600" data-close-history="1" aria-label="Close">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
      <div id="historyNotice" class="hidden border-b border-orange-100 bg-orange-50 px-3 py-2 text-xs font-bold text-orange-800"></div>
      <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
        <div class="border-b border-orange-100 bg-orange-50/60 p-2 sm:p-3">
          <div id="historyList" class="no-scrollbar flex gap-2 overflow-x-auto"></div>
        </div>
        <div id="historyDetail" class="min-h-0 flex-1 overflow-y-auto p-3 sm:p-4">
          <div class="rounded-xl border border-dashed border-orange-200 p-6 text-center text-xs font-semibold text-slate-500">Select an order to view delivery progress.</div>
        </div>
      </div>
    </div>
  </div>

  <script>
    const API_BASE_URL = <?= json_encode($apiBaseUrl) ?>;
    const SITE_BASE_URL = <?= json_encode($siteBaseUrl) ?>;
    const STORE_ID = <?= json_encode($storeId) ?>;
    const currency = new Intl.NumberFormat('en-NG', { style: 'currency', currency: 'NGN', maximumFractionDigits: 0 });
    const cartKey = `qrstore_cart_${STORE_ID}`;
    const customerKey = `qrstore_customer_${STORE_ID}`;
    const deliveryProfileKey = `qrstore_delivery_${STORE_ID}`;
    const orderHistoryKey = 'salespro_qrstore_orders';
    let storeData = null;
    let products = [];
    let categories = [];
    let activeCategory = 'All';
    let cart = JSON.parse(localStorage.getItem(cartKey) || '[]');
    let currentStep = 'store';
    let modalProductId = null;
    const flowSteps = [
      { key: 'store', label: 'Store', icon: 'bi-shop' },
      { key: 'cart', label: 'Cart', icon: 'bi-cart-check-fill' },
      { key: 'payment', label: 'Payment', icon: 'bi-credit-card' },
      { key: 'done', label: 'Checkout', icon: 'bi-check2-circle' }
    ];

    function money(value) {
      return currency.format(Number(value || 0));
    }

    function safeText(value, fallback = '') {
      return value === null || value === undefined || value === '' ? fallback : String(value);
    }

    function escapeHtml(value) {
      return safeText(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
    }

    function saveCart() {
      localStorage.setItem(cartKey, JSON.stringify(cart));
      renderCart();
    }

    function loadCustomerProfile() {
      try {
        const profile = JSON.parse(localStorage.getItem(customerKey) || '{}');
        const nameInput = document.getElementById('customerName');
        const phoneInput = document.getElementById('customerPhone');
        if (nameInput && profile.name) nameInput.value = profile.name;
        if (phoneInput && profile.phone) phoneInput.value = profile.phone;
      } catch (error) {
        localStorage.removeItem(customerKey);
      }
    }

    function saveCustomerProfile() {
      const name = document.getElementById('customerName')?.value.trim() || '';
      const phone = document.getElementById('customerPhone')?.value.trim() || '';
      if (!name && !phone) return;
      localStorage.setItem(customerKey, JSON.stringify({ name, phone }));
    }

    function loadDeliveryProfile() {
      try {
        const profile = JSON.parse(localStorage.getItem(deliveryProfileKey) || '{}');
        const requestInput = document.getElementById('requestDelivery');
        const addressInput = document.getElementById('deliveryAddress');
        const noteInput = document.getElementById('deliveryNote');
        if (requestInput) requestInput.checked = !!profile.requestDelivery;
        if (addressInput && profile.address) addressInput.value = profile.address;
        if (noteInput && profile.note) noteInput.value = profile.note;
        updateDeliveryRequestUi();
      } catch (error) {
        localStorage.removeItem(deliveryProfileKey);
      }
    }

    function saveDeliveryProfile() {
      const requestDelivery = document.getElementById('requestDelivery')?.checked || false;
      const address = document.getElementById('deliveryAddress')?.value.trim() || '';
      const note = document.getElementById('deliveryNote')?.value.trim() || '';
      localStorage.setItem(deliveryProfileKey, JSON.stringify({ requestDelivery, address, note }));
    }

    function deliveryServiceAvailable() {
      const value = storeData?.deliveryService?.available;
      return value === true || value === 1 || value === '1' || value === 'true';
    }

    function updateDeliveryRequestUi() {
      const available = deliveryServiceAvailable();
      const wrap = document.getElementById('deliveryRequestWrap');
      const details = document.getElementById('deliveryDetails');
      const requestInput = document.getElementById('requestDelivery');
      if (!wrap || !details || !requestInput) return;
      wrap.classList.toggle('hidden', !available);
      if (!available) {
        requestInput.checked = false;
      }
      const showDetails = available && requestInput.checked;
      details.classList.toggle('hidden', !showDetails);
    }

    function orderId() {
      return `${Date.now()}${Math.floor(Math.random() * 900) + 100}`;
    }

    function getOrderHistory() {
      try {
        const history = JSON.parse(localStorage.getItem(orderHistoryKey) || '[]');
        return Array.isArray(history)
          ? history.sort((a, b) => new Date(b.created || b.orderDate || 0) - new Date(a.created || a.orderDate || 0))
          : [];
      } catch (error) {
        localStorage.removeItem(orderHistoryKey);
        return [];
      }
    }

    function saveTrackedOrder(order) {
      const history = getOrderHistory().filter(item => String(item.salesID) !== String(order.salesID));
      history.unshift(order);
      localStorage.setItem(orderHistoryKey, JSON.stringify(history.slice(0, 30)));
    }

    function mergeTrackedOrder(order) {
      if (!order || !order.salesID) return;
      const history = getOrderHistory();
      const existing = history.find(item => String(item.salesID) === String(order.salesID)) || {};
      saveTrackedOrder({
        ...existing,
        salesID: order.salesID,
        storeId: existing.storeId || STORE_ID,
        business: order.business || existing.business || storeData?.business || 'QR Store',
        total: order.total ?? existing.total ?? 0,
        customer: order.customer || existing.customer || '',
        phone: order.phone || existing.phone || '',
        created: order.orderDate || existing.created || new Date().toISOString(),
        deliveryDetails: order.deliveryDetails || existing.deliveryDetails || null,
        delivery: order.delivery || existing.delivery || null,
        logs: order.logs || existing.logs || []
      });
    }

    function renderHistoryList(orders = getOrderHistory()) {
      const list = document.getElementById('historyList');
      if (!orders.length) {
        list.innerHTML = '<div class="w-full rounded-xl border border-dashed border-orange-200 bg-white p-3 text-center text-xs font-bold text-slate-500">No saved orders on this device yet.</div>';
        return;
      }
      list.innerHTML = orders.map(order => `
        <button type="button" data-track-order="${escapeHtml(String(order.salesID))}" class="block w-40 shrink-0 rounded-xl border border-orange-100 bg-white p-2 text-left shadow-sm transition hover:border-orange-300 sm:w-48">
          <span class="block truncate text-[11px] font-black text-slate-950">#${escapeHtml(order.salesID)}</span>
          <span class="mt-0.5 block truncate text-[10px] font-bold text-slate-500">${escapeHtml(order.business || 'QR Store')}</span>
          <span class="mt-1.5 flex items-center justify-between gap-1 text-[10px] font-black">
            <span class="text-orange-700">${money(order.total || 0)}</span>
            <span class="max-w-20 truncate rounded-full bg-orange-50 px-1.5 py-0.5 text-orange-700">${escapeHtml(order.delivery?.label || 'Pending')}</span>
          </span>
        </button>
      `).join('');
    }

    function renderOrderDetail(order) {
      const logs = Array.isArray(order.logs) ? order.logs : [];
      const delivery = order.delivery || {};
      const deliveryDetails = order.deliveryDetails || {};
      const deliveryStatus = delivery.status || 'pending_assignment';
      const canMarkReceived = ['assigned', 'picked_up', 'in_transit', 'out_for_delivery', 'delivered'].includes(deliveryStatus);
      const canReview = deliveryStatus === 'delivered' || deliveryStatus === 'received' || deliveryStatus === 'reviewed';
      const hasReviewed = Number(delivery.reviewStars || 0) > 0;
      document.getElementById('historyDetail').innerHTML = `
        <div class="mb-3 rounded-xl border border-orange-100 bg-orange-50 p-3">
          <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
              <p class="text-[10px] font-black uppercase text-orange-700">Order #${escapeHtml(order.salesID)}</p>
              <h3 class="mt-1 text-sm font-black text-slate-950">${escapeHtml(order.business || 'QR Store')}</h3>
              <p class="text-[11px] font-bold text-slate-500">${escapeHtml(order.customer || '')} ${order.phone ? '- ' + escapeHtml(order.phone) : ''}</p>
            </div>
            <span class="rounded-full bg-white px-2.5 py-1 text-[11px] font-black text-orange-700">${escapeHtml(delivery.label || 'Pending')}</span>
          </div>
          <div class="mt-3 grid gap-2 text-[11px] font-bold text-slate-600 sm:grid-cols-2">
            <div class="rounded-lg bg-white p-2"><span class="block text-[10px] font-black uppercase text-slate-400">Total</span>${money(order.total || 0)}</div>
            <div class="rounded-lg bg-white p-2"><span class="block text-[10px] font-black uppercase text-slate-400">Rider</span>${escapeHtml(delivery.rider?.name || 'Not assigned yet')} ${delivery.rider?.phone ? '(' + escapeHtml(delivery.rider.phone) + ')' : ''}</div>
            ${deliveryDetails.requested ? `<div class="rounded-lg bg-white p-2 sm:col-span-2"><span class="block text-[10px] font-black uppercase text-slate-400">Delivery address</span>${escapeHtml(deliveryDetails.address || delivery.address || '')}${deliveryDetails.note ? `<br><span class="text-slate-500">${escapeHtml(deliveryDetails.note)}</span>` : ''}</div>` : ''}
          </div>
        </div>
        <div class="mb-3">
          <h4 class="mb-2 text-xs font-black text-slate-950">Progress logs</h4>
          ${logs.length ? `<div class="relative pl-8">${logs.map((log, index) => `
            <div class="relative pb-4 last:pb-0">
              ${index < logs.length - 1 ? '<span class="absolute -left-[21px] top-7 h-full w-0.5 bg-orange-200"></span>' : ''}
              <span class="absolute -left-8 top-0 flex h-7 w-7 items-center justify-center rounded-full border-2 border-orange-200 bg-white text-[11px] font-black text-orange-700 shadow-sm">${index + 1}</span>
              <div class="rounded-xl border border-orange-100 bg-white p-2.5 shadow-sm">
                <div class="flex items-start justify-between gap-2">
                  <strong class="text-xs text-slate-950">${escapeHtml(log.label || log.status)}</strong>
                  <span class="shrink-0 text-[10px] font-bold text-slate-400">${escapeHtml(String(log.timestamp || '').slice(0, 16))}</span>
                </div>
                <p class="mt-1 text-[11px] font-semibold text-slate-600">${escapeHtml(log.note || '')}</p>
              </div>
            </div>
          `).join('')}</div>` : '<div class="rounded-xl border border-dashed border-orange-200 p-4 text-center text-xs font-bold text-slate-500">No delivery update has been posted yet.</div>'}
        </div>
        <div class="rounded-xl border border-orange-100 bg-white p-3">
          <h4 class="text-xs font-black text-slate-950">Buyer update</h4>
          <p class="mt-1 text-[11px] font-semibold text-slate-500">${canReview ? 'Rate the delivery experience.' : (canMarkReceived ? 'You can confirm once you have received the package. Review opens after receipt.' : 'Receipt and review will be available after a rider is assigned.')}</p>
          ${canMarkReceived ? `<button type="button" data-mark-received="${escapeHtml(order.salesID)}" class="mt-3 h-10 w-full rounded-lg bg-orange-600 text-xs font-black text-white">Mark Received</button>` : ''}
          ${hasReviewed ? `
          <div class="mt-3 rounded-xl border border-green-100 bg-green-50 p-3">
            <div class="flex items-center justify-between gap-2">
              <strong class="text-xs font-black text-green-800">Review submitted</strong>
              <span class="rounded-full bg-white px-2 py-1 text-[11px] font-black text-green-700">${Number(delivery.reviewStars || 0)}/5 stars</span>
            </div>
            <p class="mt-2 text-[11px] font-semibold text-green-800">${escapeHtml(delivery.reviewNote || 'No written review was added.')}</p>
            ${delivery.reviewedAt ? `<p class="mt-1 text-[10px] font-bold text-green-700">${escapeHtml(String(delivery.reviewedAt).slice(0, 16))}</p>` : ''}
          </div>
          ` : canReview ? `
          <div class="mt-3 grid gap-2 sm:grid-cols-[110px_minmax(0,1fr)]">
            <select id="reviewStars" class="h-10 rounded-lg border border-slate-200 px-2 text-xs font-bold">
              <option value="5">5 stars</option>
              <option value="4">4 stars</option>
              <option value="3">3 stars</option>
              <option value="2">2 stars</option>
              <option value="1">1 star</option>
            </select>
            <input id="reviewNote" class="h-10 rounded-lg border border-slate-200 px-3 text-xs" placeholder="Optional review">
          </div>
          <button type="button" data-submit-review="${escapeHtml(order.salesID)}" class="mt-2 h-10 w-full rounded-lg border border-orange-200 bg-orange-50 text-xs font-black text-orange-700">Submit Review</button>
          ` : ''}
        </div>
      `;
    }

    async function loadDeliveryHistory(openSalesID = '') {
      const localOrders = getOrderHistory();
      renderHistoryList(localOrders);
      if (!localOrders.length) return;
      const ids = localOrders.map(order => order.salesID).filter(Boolean);
      try {
        const response = await fetch(`${API_BASE_URL}/v2/delivery.php?orderIds=${encodeURIComponent(ids.join(','))}`);
        const data = await response.json();
        if (response.ok && data.status === 'success' && Array.isArray(data.orders)) {
          data.orders.forEach(mergeTrackedOrder);
          renderHistoryList(getOrderHistory());
        }
      } catch (error) {
        console.warn('Delivery history unavailable', error);
      }
      const target = openSalesID || ids[0];
      const order = getOrderHistory().find(item => String(item.salesID) === String(target));
      if (order) renderOrderDetail(order);
    }

    function openHistory(openSalesID = '') {
      document.getElementById('historyModal').classList.remove('hidden');
      document.getElementById('historyModal').classList.add('flex');
      loadDeliveryHistory(openSalesID);
    }

    function closeHistory() {
      document.getElementById('historyModal').classList.add('hidden');
      document.getElementById('historyModal').classList.remove('flex');
    }

    function showHistoryNotice(message, ok = true) {
      const notice = document.getElementById('historyNotice');
      if (!notice) return;
      notice.textContent = message;
      notice.classList.remove('hidden', 'bg-orange-50', 'text-orange-800', 'bg-green-50', 'text-green-800', 'bg-red-50', 'text-red-700');
      notice.classList.add(ok ? 'bg-green-50' : 'bg-red-50', ok ? 'text-green-800' : 'text-red-700');
      setTimeout(() => notice.classList.add('hidden'), 3200);
    }

    async function refreshOneOrder(salesID) {
      const response = await fetch(`${API_BASE_URL}/v2/delivery.php?salesID=${encodeURIComponent(salesID)}`);
      const data = await response.json();
      if (!response.ok || data.status !== 'success' || !data.orders?.[0]) {
        throw new Error(data.message || 'Unable to load delivery.');
      }
      mergeTrackedOrder(data.orders[0]);
      const order = getOrderHistory().find(item => String(item.salesID) === String(salesID));
      if (order) renderOrderDetail(order);
      renderHistoryList(getOrderHistory());
    }

    async function postDeliveryAction(salesID, payload) {
      const query = new URLSearchParams({ salesID, ...payload });
      const response = await fetch(`${API_BASE_URL}/v2/delivery.php?${query.toString()}`, { method: 'GET' });
      const text = await response.text();
      let data;
      try {
        data = JSON.parse(text);
      } catch (error) {
        throw new Error('Delivery update failed. The server returned an invalid response.');
      }
      if (!response.ok || data.status !== 'success') {
        throw new Error(data.message || 'Unable to update delivery.');
      }
      if (data.order) mergeTrackedOrder(data.order);
      const order = getOrderHistory().find(item => String(item.salesID) === String(salesID));
      if (order) renderOrderDetail(order);
      renderHistoryList(getOrderHistory());
      showHistoryNotice(data.message || 'Updated successfully.');
    }

    function renderFlowSteps() {
      document.getElementById('flowSteps').innerHTML = flowSteps.map((step, index) => {
        const activeIndex = flowSteps.findIndex(item => item.key === currentStep);
        const isActive = step.key === currentStep;
        const isDone = index < activeIndex;
        return `
          <button type="button" data-flow-step="${step.key}" class="flex min-w-0 flex-col items-center rounded-xl border px-1.5 py-2 transition ${isActive ? 'border-orange-600 bg-orange-600 text-white shadow-sm' : isDone ? 'border-orange-200 bg-orange-100 text-orange-700' : 'border-orange-100 bg-white text-slate-500'}">
            <i class="bi ${step.icon} text-base leading-none"></i>
            <span class="mt-1 truncate text-[10px] font-black">${step.label}</span>
          </button>
        `;
      }).join('');
    }

    function setFlowStep(step) {
      currentStep = step;
      renderFlowSteps();
      applyMobileScreens();
    }

    function scrollToCart() {
      setFlowStep('cart');
      document.getElementById('cartPanel').scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    function setMobileVisible(element, visible) {
      if (!element) return;
      element.classList.toggle('hidden', !visible);
      element.classList.toggle('lg:block', !visible);
    }

    function applyMobileScreens() {
      const storeScreen = document.getElementById('storeScreen');
      const cartPanel = document.getElementById('cartPanel');
      const cartScreen = document.getElementById('cartScreen');
      const paymentScreen = document.getElementById('paymentScreen');
      const paymentBackRow = document.getElementById('paymentBackRow');
      const categoryStrip = document.getElementById('categoryStrip');
      const flowRail = document.getElementById('flowSteps');

      setMobileVisible(storeScreen, currentStep === 'store');
      setMobileVisible(cartPanel, currentStep !== 'store');
      setMobileVisible(cartScreen, currentStep === 'cart');
      setMobileVisible(paymentScreen, currentStep === 'payment' || currentStep === 'done');
      if (flowRail) {
        flowRail.classList.toggle('hidden', currentStep === 'store');
        flowRail.classList.toggle('grid', currentStep !== 'store');
      }
      if (categoryStrip) {
        categoryStrip.classList.toggle('hidden', currentStep !== 'store');
        categoryStrip.classList.toggle('lg:flex', currentStep !== 'store');
      }

      if (paymentBackRow) {
        paymentBackRow.classList.toggle('hidden', currentStep !== 'payment');
        paymentBackRow.classList.toggle('flex', currentStep === 'payment');
      }
    }

    async function loadStore() {
      if (!STORE_ID) {
        showError('Store ID is missing. Use a link like /qrstore/658944.');
        return;
      }

      try {
        const response = await fetch(`${API_BASE_URL}/v2/businessqr.php?store=${encodeURIComponent(STORE_ID)}`);
        const data = await response.json();
        if (!response.ok || data.status !== 'success') {
          throw new Error(data.message || 'Unable to load store.');
        }

        storeData = data;
        await loadDeliverySettings();
        products = Array.isArray(data.data) ? data.data : [];
        categories = ['All', ...new Set(products.map(product => safeText(product.category, 'Uncategorized')))];
        renderStore();
      } catch (error) {
        showError(error.message);
      }
    }

    async function loadDeliverySettings() {
      try {
        const response = await fetch(`${API_BASE_URL}/v2/deliverysettings.php?store=${encodeURIComponent(STORE_ID)}`);
        const settings = await response.json();
        if (response.ok && settings.status === 'success') {
          storeData.qrPaymentOption = settings.qrPaymentOption || storeData.qrPaymentOption;
          storeData.qrPaymentSettings = settings.qrPaymentSettings || storeData.qrPaymentSettings;
          storeData.deliveryService = settings.deliveryService || storeData.deliveryService;
        }
      } catch (error) {
        console.warn('Delivery settings unavailable', error);
      }
    }

    function renderStore() {
      document.getElementById('status').classList.add('hidden');
      document.getElementById('storeContent').classList.remove('hidden');
      document.getElementById('businessName').textContent = safeText(storeData.business, 'QR Store');
      document.getElementById('businessMeta').textContent = [storeData.businessaddress, storeData.businessphone].filter(Boolean).join(' - ');
      const logo = document.getElementById('businessLogo');
      if (storeData.businesslogo) {
        logo.src = storeData.businesslogo;
        logo.onerror = () => { logo.style.display = 'none'; };
      } else {
        logo.style.display = 'none';
      }
      renderCategories();
      renderFlowSteps();
      renderProducts();
      renderPayments();
      loadCustomerProfile();
      loadDeliveryProfile();
      renderCart();
      applyMobileScreens();
    }

    function renderCategories() {
      document.getElementById('categoryStrip').innerHTML = categories.map((category, index) => `
        <button type="button" data-category-index="${index}" class="shrink-0 rounded-full border px-3 py-2 text-xs font-black transition ${category === activeCategory ? 'border-orange-600 bg-orange-600 text-white shadow-sm' : 'border-orange-200 bg-white text-slate-600'}">
          ${escapeHtml(category)}
        </button>
      `).join('');
    }

    function setCategory(category) {
      activeCategory = category;
      setFlowStep('store');
      renderCategories();
      renderProducts();
    }

    function renderProducts() {
      const visible = products.filter(product => activeCategory === 'All' || safeText(product.category, 'Uncategorized') === activeCategory);
      document.getElementById('productGrid').innerHTML = visible.length ? visible.map(product => `
        <article class="group overflow-hidden rounded-lg border border-orange-100 bg-white shadow-soft transition hover:-translate-y-0.5 hover:border-orange-200">
          <div class="relative bg-orange-50">
            <img class="aspect-[1.1/1] w-full object-cover" src="${escapeHtml(product.photo)}" alt="${escapeHtml(product.title)}" onerror="this.style.display='none'">
            <span class="absolute left-1.5 top-1.5 max-w-[82%] truncate rounded-full bg-white/95 px-2 py-0.5 text-[9px] font-black text-orange-700 shadow-sm">${escapeHtml(safeText(product.category, 'Item'))}</span>
          </div>
          <div class="p-2">
            <h3 class="line-clamp-1 text-[12px] font-black leading-tight text-slate-900 sm:text-[13px]">${escapeHtml(safeText(product.title, 'Untitled product'))}</h3>
            <p class="mt-0.5 line-clamp-1 text-[10px] font-semibold leading-tight text-slate-500">${escapeHtml(safeText(product.note, safeText(product.category, '')))}</p>
            <div class="mt-1.5 flex items-center justify-between gap-1.5">
              <span class="truncate text-[12px] font-black text-slate-950 sm:text-sm">${money(product.price)}</span>
              <button type="button" data-add-product="${escapeHtml(String(product.id))}" class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-orange-600 text-xs font-black text-white shadow-sm transition hover:bg-orange-700" title="Add to cart">
                <i class="bi bi-plus-lg"></i>
              </button>
            </div>
          </div>
        </article>
      `).join('') : '<div class="col-span-full rounded-lg border border-dashed border-orange-200 bg-white p-8 text-center text-sm font-semibold text-slate-500">No products in this category.</div>';
    }

    function renderPayments() {
      const settings = storeData.qrPaymentSettings || {};
      const allowBank = settings.allowBank !== false && storeData.qrPaymentOption !== 'delivery';
      const allowDelivery = settings.allowDelivery === true || storeData.qrPaymentOption === 'delivery' || storeData.qrPaymentOption === 'both';
      const methods = allowBank && Array.isArray(storeData.paymentMethods) ? storeData.paymentMethods : [];
      const paymentOptions = [];

      methods.forEach((method) => {
        paymentOptions.push(`
        <label class="mb-2 block cursor-pointer rounded-lg border border-orange-100 bg-white p-2.5 text-xs shadow-sm has-[:checked]:border-orange-500 has-[:checked]:bg-orange-50">
          <span class="flex items-center gap-2">
            <input class="h-4 w-4 border-orange-300 text-orange-600 focus:ring-orange-500" type="radio" name="paymentMethod" value="${safeText(method.id)}" data-payment-type="bank" required>
            <strong class="text-sm text-slate-900">${escapeHtml(safeText(method.title, 'Payment method'))}</strong>
          </span>
          <span class="ml-6 mt-1 block font-bold text-slate-600">${escapeHtml(safeText(method.bankName, 'Bank not specified'))}</span>
          <span class="ml-6 block text-slate-500">${escapeHtml(safeText(method.accountName, ''))}</span>
          <span class="ml-6 block font-black tracking-wide text-orange-700">${escapeHtml(safeText(method.accountNumber, ''))}</span>
          ${method.note ? `<span class="ml-6 block text-slate-500">${escapeHtml(safeText(method.note))}</span>` : ''}
        </label>
        `);
      });

      if (allowDelivery) {
        paymentOptions.push(`
          <label class="mb-2 block cursor-pointer rounded-lg border border-orange-100 bg-white p-2.5 text-xs shadow-sm has-[:checked]:border-orange-500 has-[:checked]:bg-orange-50">
            <span class="flex items-center gap-2">
              <input class="h-4 w-4 border-orange-300 text-orange-600 focus:ring-orange-500" type="radio" name="paymentMethod" value="pay_on_delivery" data-payment-type="delivery" required>
              <strong class="text-sm text-slate-900">Pay on delivery</strong>
            </span>
            <span class="ml-6 mt-1 block font-semibold text-slate-600">Submit your order now and pay when it is delivered.</span>
          </label>
        `);
      }

      document.getElementById('paymentMethods').innerHTML = paymentOptions.length ? paymentOptions.join('') : '<div class="rounded-lg border border-dashed border-orange-200 bg-white p-4 text-center text-xs font-semibold text-slate-500">No payment option is available for this store.</div>';
      const firstOption = document.querySelector('input[name="paymentMethod"]');
      if (firstOption) firstOption.checked = true;
      updatePaymentModeUi();
    }

    function updatePaymentModeUi() {
      const payment = document.querySelector('input[name="paymentMethod"]:checked');
      const isDelivery = payment?.dataset.paymentType === 'delivery';
      const paidWrap = document.getElementById('paidCheckWrap');
      const paidCheck = document.getElementById('paidCheck');
      const checkoutButton = document.getElementById('checkoutButton');
      if (paidWrap) {
        paidWrap.classList.toggle('hidden', isDelivery || !payment);
        paidWrap.classList.toggle('flex', !isDelivery && !!payment);
      }
      if (paidCheck) {
        paidCheck.required = !isDelivery;
        if (isDelivery) paidCheck.checked = false;
      }
      if (checkoutButton) {
        checkoutButton.innerHTML = isDelivery
          ? '<i class="bi bi-truck me-1"></i> Place Order'
          : '<i class="bi bi-check2-circle me-1"></i> Mark Paid & Checkout';
      }
    }

    function openQtyModal(id) {
      const product = products.find(item => String(item.id) === String(id));
      if (!product) return;
      modalProductId = String(id);
      document.getElementById('modalItemName').textContent = safeText(product.title, 'Item');
      document.getElementById('modalItemPrice').textContent = money(product.price);
      document.getElementById('modalQty').value = 1;
      document.getElementById('continueShoppingCheck').checked = true;
      document.getElementById('qtyModal').classList.remove('hidden');
      document.getElementById('qtyModal').classList.add('flex');
      setTimeout(() => document.getElementById('modalQty').focus(), 50);
    }

    function closeQtyModal() {
      modalProductId = null;
      document.getElementById('qtyModal').classList.add('hidden');
      document.getElementById('qtyModal').classList.remove('flex');
    }

    function adjustModalQty(delta) {
      const input = document.getElementById('modalQty');
      const next = Math.max(1, (parseInt(input.value, 10) || 1) + delta);
      input.value = next;
    }

    function confirmQtyAdd() {
      const qty = parseInt(document.getElementById('modalQty').value, 10);
      if (!Number.isFinite(qty) || qty < 1) {
        alert('Enter a valid quantity.');
        return;
      }
      addToCart(modalProductId, qty);
      const continueShopping = document.getElementById('continueShoppingCheck').checked;
      closeQtyModal();
      if (continueShopping) {
        setFlowStep('store');
      } else {
        scrollToCart();
      }
    }

    function addToCart(id, qty = 1) {
      const product = products.find(item => String(item.id) === String(id));
      if (!product) return;
      const existing = cart.find(item => String(item.id) === String(id));
      if (existing) {
        existing.qty += qty;
        existing.amount = existing.qty * existing.price;
      } else {
        cart.push({
          id: String(product.id),
          title: safeText(product.title),
          note: safeText(product.note),
          category: safeText(product.category),
          price: Number(product.price || 0),
          qty: qty,
          barcode: safeText(product.barcode),
          photo: safeText(product.photo),
          amount: Number(product.price || 0) * qty
        });
      }
      saveCart();
      setFlowStep('cart');
    }

    function changeQty(id, delta) {
      const item = cart.find(product => String(product.id) === String(id));
      if (!item) return;
      item.qty += delta;
      if (item.qty <= 0) {
        cart = cart.filter(product => String(product.id) !== String(id));
      } else {
        item.amount = item.qty * item.price;
      }
      saveCart();
    }

    function clearCart() {
      cart = [];
      localStorage.removeItem(cartKey);
      renderCart();
    }

    function renderCart() {
      const totalQty = cart.reduce((sum, item) => sum + Number(item.qty || 0), 0);
      const total = cart.reduce((sum, item) => sum + Number(item.amount || 0), 0);
      document.getElementById('cartCount').textContent = totalQty;
      document.getElementById('cartTotal').textContent = money(total);
      document.getElementById('mobileCartCount').textContent = totalQty;
      document.getElementById('mobileCartTotal').textContent = money(total);
      document.getElementById('mobileCartButton').classList.toggle('hidden', totalQty === 0);
      document.getElementById('mobileCartButton').classList.toggle('flex', totalQty > 0);
      document.getElementById('cartItems').innerHTML = cart.length ? cart.map(item => `
        <div class="grid grid-cols-[minmax(0,1fr)_auto] gap-2 border-b border-orange-100 px-3 py-2.5">
          <div class="min-w-0">
            <strong class="line-clamp-1 text-xs font-black text-slate-900">${escapeHtml(safeText(item.title))}</strong>
            <small class="text-[11px] font-semibold text-slate-500">${money(item.price)} each</small>
            <div class="mt-2 inline-flex items-center gap-2 rounded-lg bg-slate-50 p-1">
              <button class="flex h-7 w-7 items-center justify-center rounded-md border border-slate-200 bg-white text-sm font-black" type="button" data-cart-id="${escapeHtml(String(item.id))}" data-cart-delta="-1">-</button>
              <span class="min-w-5 text-center text-xs font-black">${item.qty}</span>
              <button class="flex h-7 w-7 items-center justify-center rounded-md border border-slate-200 bg-white text-sm font-black" type="button" data-cart-id="${escapeHtml(String(item.id))}" data-cart-delta="1">+</button>
            </div>
          </div>
          <strong class="text-xs font-black text-slate-950">${money(item.amount)}</strong>
        </div>
      `).join('') : '<div class="p-8 text-center text-xs font-semibold text-slate-500">Your cart is empty.</div>';
    }

    async function checkout() {
      if (!cart.length) {
        alert('Your cart is empty.');
        return;
      }

      const customerName = document.getElementById('customerName').value.trim();
      const customerPhone = document.getElementById('customerPhone').value.trim();
      if (!customerName || !customerPhone) {
        alert('Enter your name and phone number.');
        return;
      }

      const payment = document.querySelector('input[name="paymentMethod"]:checked');
      if (!payment) {
        alert('Select a payment method.');
        return;
      }
      const isDelivery = payment.dataset.paymentType === 'delivery';
      const selectedPaymentMethod = isDelivery
        ? null
        : (Array.isArray(storeData.paymentMethods) ? storeData.paymentMethods.find(method => String(method.id) === String(payment.value)) : null);
      if (!isDelivery && !document.getElementById('paidCheck').checked) {
        alert('Confirm that you have made payment.');
        return;
      }
      const wantsDelivery = deliveryServiceAvailable() && document.getElementById('requestDelivery')?.checked;
      const deliveryAddress = document.getElementById('deliveryAddress')?.value.trim() || '';
      const deliveryNote = document.getElementById('deliveryNote')?.value.trim() || '';
      if (wantsDelivery && !deliveryAddress) {
        alert('Enter your delivery address.');
        return;
      }
      setFlowStep('payment');
      saveCustomerProfile();
      saveDeliveryProfile();

      const button = document.getElementById('checkoutButton');
      button.disabled = true;
      button.textContent = 'Submitting...';

      const total = cart.reduce((sum, item) => sum + Number(item.amount || 0), 0);
      const payload = {
        servedBy: STORE_ID,
        payMethod: payment.value,
        paymentDetails: isDelivery ? {
          type: 'delivery',
          title: 'Pay on delivery',
          status: 'Payment due on delivery',
          customerConfirmedPayment: false
        } : {
          type: 'bank',
          id: payment.value,
          title: safeText(selectedPaymentMethod?.title, 'Bank payment'),
          bankName: safeText(selectedPaymentMethod?.bankName),
          accountName: safeText(selectedPaymentMethod?.accountName),
          accountNumber: safeText(selectedPaymentMethod?.accountNumber),
          note: safeText(selectedPaymentMethod?.note),
          status: 'Customer marked payment as made',
          customerConfirmedPayment: true
        },
        sent: true,
        paid: !isDelivery,
        paymentStatus: isDelivery ? 'pay_on_delivery' : 'paid',
        deliveryDetails: {
          requested: !!wantsDelivery,
          address: wantsDelivery ? deliveryAddress : '',
          note: wantsDelivery ? deliveryNote : ''
        },
        name: customerName,
        phone: customerPhone,
        cart: cart,
        total: total,
        salesID: orderId(),
        storeName: storeData.business,
        timestamp: new Date().toISOString()
      };

      try {
        const query = new URLSearchParams({
          store: STORE_ID,
          payload: JSON.stringify(payload)
        });
        const response = await fetch(`${API_BASE_URL}/v2/addorder.php?${query.toString()}`, {
          method: 'GET'
        });
        const data = await response.json();
        if (!response.ok || data.status !== 'success') {
          throw new Error(data.message || 'Unable to submit order.');
        }
        setFlowStep('done');
        const successText = isDelivery
          ? `Thank you, ${payload.name || 'customer'}. Your order has been sent successfully. Pay on delivery is selected. Order ID: ${payload.salesID}.`
          : `Thank you, ${payload.name || 'customer'}. Your paid order has been sent successfully. Order ID: ${payload.salesID}.`;
        saveTrackedOrder({
          salesID: data.salesID || payload.salesID,
          storeId: STORE_ID,
          business: storeData.business,
          total: payload.total,
          customer: payload.name,
          phone: payload.phone,
          created: payload.timestamp,
          delivery: { status: 'pending_assignment', label: 'Pending assignment' },
          deliveryDetails: payload.deliveryDetails,
          logs: []
        });
        showSuccess(successText);
        clearCart();
        document.getElementById('paidCheck').checked = false;
      } catch (error) {
        alert(error.message);
      } finally {
        button.disabled = false;
        updatePaymentModeUi();
      }
    }

    function showError(message) {
      document.getElementById('status').className = 'py-12 text-center text-sm font-semibold text-red-600';
      document.getElementById('status').textContent = message;
    }

    function showSuccess(message) {
      document.getElementById('successMessage').textContent = message;
      document.getElementById('successModal').classList.remove('hidden');
      document.getElementById('successModal').classList.add('flex');
    }

    function closeSuccess() {
      document.getElementById('successModal').classList.add('hidden');
      document.getElementById('successModal').classList.remove('flex');
      setFlowStep('store');
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    document.addEventListener('click', function(event) {
      const categoryButton = event.target.closest('[data-category-index]');
      if (categoryButton) {
        const category = categories[parseInt(categoryButton.dataset.categoryIndex, 10)];
        if (category !== undefined) setCategory(category);
        return;
      }

      const addButton = event.target.closest('[data-add-product]');
      if (addButton) {
        openQtyModal(addButton.dataset.addProduct);
        return;
      }

      const qtyButton = event.target.closest('[data-cart-id][data-cart-delta]');
      if (qtyButton) {
        changeQty(qtyButton.dataset.cartId, parseInt(qtyButton.dataset.cartDelta, 10));
        return;
      }

      if (event.target.closest('[data-checkout]')) {
        checkout();
        return;
      }

      if (event.target.closest('[data-show-cart]')) {
        scrollToCart();
        return;
      }

      if (event.target.closest('[data-go-payment]')) {
        if (!cart.length) {
          alert('Your cart is empty.');
          return;
        }
        setFlowStep('payment');
        document.getElementById('cartPanel').scrollIntoView({ behavior: 'smooth', block: 'start' });
        return;
      }

      if (event.target.closest('[data-go-cart]')) {
        setFlowStep('cart');
        return;
      }

      const flowButton = event.target.closest('[data-flow-step]');
      if (flowButton) {
        const step = flowButton.dataset.flowStep;
        setFlowStep(step === 'done' ? currentStep : step);
        if (step === 'cart' || step === 'payment') scrollToCart();
        if (step === 'store') window.scrollTo({ top: 0, behavior: 'smooth' });
        return;
      }

      const modalQty = event.target.closest('[data-modal-qty]');
      if (modalQty) {
        adjustModalQty(parseInt(modalQty.dataset.modalQty, 10));
        return;
      }

      if (event.target.closest('[data-confirm-qty]')) {
        confirmQtyAdd();
        return;
      }

      if (event.target.closest('[data-close-qty-modal]') || event.target.id === 'qtyModal') {
        closeQtyModal();
        return;
      }

      if (event.target.closest('[data-close-success]') || event.target.id === 'successModal') {
        closeSuccess();
        return;
      }

      const openHistoryButton = event.target.closest('[data-open-history]');
      if (openHistoryButton) {
        closeSuccess();
        openHistory();
        return;
      }

      if (event.target.closest('[data-close-history]') || event.target.id === 'historyModal') {
        closeHistory();
        return;
      }

      const trackButton = event.target.closest('[data-track-order]');
      if (trackButton) {
        refreshOneOrder(trackButton.dataset.trackOrder).catch(error => alert(error.message));
        return;
      }

      const receivedButton = event.target.closest('[data-mark-received]');
      if (receivedButton) {
        receivedButton.disabled = true;
        receivedButton.textContent = 'Updating...';
        postDeliveryAction(receivedButton.dataset.markReceived, { action: 'received' }).catch(error => {
          showHistoryNotice(error.message, false);
          receivedButton.disabled = false;
          receivedButton.textContent = 'Mark Received';
        });
        return;
      }

      const reviewButton = event.target.closest('[data-submit-review]');
      if (reviewButton) {
        const stars = document.getElementById('reviewStars')?.value || 5;
        const review = document.getElementById('reviewNote')?.value || '';
        reviewButton.disabled = true;
        reviewButton.textContent = 'Submitting...';
        postDeliveryAction(reviewButton.dataset.submitReview, { action: 'review', stars, review }).catch(error => {
          showHistoryNotice(error.message, false);
          reviewButton.disabled = false;
          reviewButton.textContent = 'Submit Review';
        });
      }
    });

    document.addEventListener('change', function(event) {
      if (event.target.matches('input[name="paymentMethod"], #paidCheck')) {
        setFlowStep('payment');
        updatePaymentModeUi();
      }
      if (event.target.matches('#requestDelivery')) {
        setFlowStep('payment');
        updateDeliveryRequestUi();
        saveDeliveryProfile();
      }
    });

    document.addEventListener('input', function(event) {
      if (event.target.matches('#customerName, #customerPhone')) {
        saveCustomerProfile();
      }
      if (event.target.matches('#deliveryAddress, #deliveryNote')) {
        saveDeliveryProfile();
      }
    });

    loadStore();
  </script>
</body>
</html>
