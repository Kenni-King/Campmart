<?php
session_start();

$storeId = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['store'] ?? '');

if (!$storeId) {
    header('HTTP/1.1 400 Bad Request');
    echo 'Store ID is missing.';
    exit;
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? '';

$docRoot = str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? '');
$scriptFile = str_replace('\\', '/', __FILE__);
$scriptRelPath = str_replace($docRoot, '', $scriptFile);

$siteRoot = dirname(dirname(dirname($scriptRelPath)));
$apiRoot = $siteRoot . '/api';

$apiBaseUrl = rtrim($scheme . '://' . $host . $apiRoot, '/');
$siteBaseUrl = rtrim($scheme . '://' . $host . $siteRoot, '/');

$loginUrl = $siteBaseUrl.'/login.php?redirect=qrstore/'.$storeId;
$signupUrl = $siteBaseUrl.'/signup.php?redirect=qrstore/'.$storeId;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Store Access - QR Store</title>
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
  </style>
</head>
<body class="bg-orange-50/40 text-slate-900 antialiased min-h-screen flex flex-col">
  <header class="sticky top-0 z-30 border-b border-orange-200 bg-gradient-to-br from-orange-600 via-orange-500 to-amber-400 text-white shadow-soft">
    <div class="mx-auto flex w-full max-w-6xl items-center justify-between gap-3 px-3 py-3 sm:px-4">
      <div class="flex min-w-0 items-center gap-3">
        <img id="businessLogo" class="h-12 w-12 shrink-0 rounded-lg border border-white/50 bg-white/15 object-cover" alt="">
        <div class="min-w-0">
          <h1 id="businessName" class="truncate text-lg font-black leading-tight tracking-normal sm:text-2xl">QR Store</h1>
          <p id="businessMeta" class="truncate text-xs font-semibold text-white/85 sm:text-sm">Loading store...</p>
        </div>
      </div>
    </div>
  </header>

  <main class="mx-auto flex w-full max-w-lg flex-1 items-center justify-center px-4 py-12">
    <div id="loadingState" class="text-center">
      <div class="mx-auto h-12 w-12 animate-spin rounded-full border-4 border-orange-200 border-t-orange-600"></div>
      <p class="mt-4 text-sm font-semibold text-slate-500">Loading store...</p>
    </div>

    <div id="authGate" class="hidden w-full">
      <div class="rounded-2xl border border-orange-100 bg-white p-8 shadow-soft text-center">
        <div class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-orange-100">
          <i class="bi bi-shop text-4xl text-orange-600"></i>
        </div>
        <h2 class="mt-5 text-xl font-black text-slate-900">Sign in to continue</h2>
        <p id="storeNameDisplay" class="mt-2 text-sm font-semibold text-slate-500">You need an account to browse <span class="font-bold text-orange-600">this store</span> and place orders.</p>

        <div class="mt-8 space-y-3">
          <a href="<?php echo htmlspecialchars($loginUrl); ?>" class="flex h-12 w-full items-center justify-center gap-2 rounded-xl bg-orange-600 text-sm font-black text-white shadow-sm transition hover:bg-orange-700">
            <i class="bi bi-box-arrow-in-right text-base"></i>
            Sign In
          </a>
          <a href="<?php echo htmlspecialchars($signupUrl); ?>" class="flex h-12 w-full items-center justify-center gap-2 rounded-xl border-2 border-orange-200 bg-white text-sm font-black text-orange-700 shadow-sm transition hover:bg-orange-50">
            <i class="bi bi-person-plus text-base"></i>
            Create Account
          </a>
        </div>

        <p class="mt-6 text-xs font-semibold text-slate-400">
          By continuing, you agree to CampMart's 
          <a href="<?php echo $siteBaseUrl; ?>/terms-of-service.php" class="text-orange-600 hover:underline" target="_blank">Terms</a>
          &amp;
          <a href="<?php echo $siteBaseUrl; ?>/privacy-policy.php" class="text-orange-600 hover:underline" target="_blank">Privacy Policy</a>
        </p>
      </div>
    </div>

    <div id="errorState" class="hidden w-full rounded-2xl border border-red-200 bg-red-50 p-8 text-center">
      <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-red-100">
        <i class="bi bi-exclamation-triangle text-3xl text-red-500"></i>
      </div>
      <h2 class="mt-4 text-lg font-black text-red-800">Store not found</h2>
      <p id="errorMessage" class="mt-2 text-sm font-semibold text-red-600">This store link is invalid or no longer available.</p>
      <a href="<?php echo $siteBaseUrl; ?>" class="mt-6 inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-orange-600 px-6 text-sm font-black text-white">
        Go to CampMart
      </a>
    </div>
  </main>

  <script>
    const API_BASE_URL = <?= json_encode($apiBaseUrl) ?>;
    const SITE_BASE_URL = <?= json_encode($siteBaseUrl) ?>;
    const STORE_ID = <?= json_encode($storeId) ?>;

    function escapeHtml(value) {
      if (value === null || value === undefined) return '';
      return String(value)
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
    }

    async function loadStoreInfo() {
      if (!STORE_ID) {
        document.getElementById('loadingState').classList.add('hidden');
        document.getElementById('errorState').classList.remove('hidden');
        document.getElementById('errorMessage').textContent = 'Store ID is missing.';
        return;
      }

      try {
        const response = await fetch(API_BASE_URL + '/v2/businessqr.php?store=' + encodeURIComponent(STORE_ID));
        const data = await response.json();

        if (!response.ok || data.status !== 'success') {
          throw new Error(data.message || 'Unable to load store.');
        }

        document.getElementById('loadingState').classList.add('hidden');
        document.getElementById('authGate').classList.remove('hidden');

        document.getElementById('businessName').textContent = escapeHtml(data.business || 'QR Store');
        document.getElementById('businessMeta').textContent = [data.businessaddress, data.businessphone].filter(Boolean).join(' - ');
        document.getElementById('storeNameDisplay').innerHTML = 'You need an account to browse <span class="font-bold text-orange-600">' + escapeHtml(data.business || 'this store') + '</span> and place orders.';

      const logo = document.getElementById('businessLogo');
      if (data.businesslogo) {
        logo.src = data.businesslogo;
        logo.onerror = function() { logo.style.display = 'none'; };
      } else {
        logo.style.display = 'none';
      }
      } catch (error) {
        document.getElementById('loadingState').classList.add('hidden');
        document.getElementById('errorState').classList.remove('hidden');
        document.getElementById('errorMessage').textContent = error.message;
      }
    }

    loadStoreInfo();
  </script>
</body>
</html>
