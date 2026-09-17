<?php session_start();
include_once 'includes/controller.php';
include_once 'api/dashboard/lib/controller.php';

checkLogin();
$userId = $_SESSION['userAppId'];

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');

$qrStoreId = '';
$qrStoreUrl = '';
$businessId = 0;
$hasProducts = false;
$businessName = '';

$existingBiz = $db->query("SELECT id, name FROM business WHERE user_id='$userId' LIMIT 1");
if ($existingBiz && $existingBiz->num_rows) {
    $biz = $existingBiz->fetch_assoc();
    $businessId = (int)$biz['id'];
    $businessName = $biz['name'];
    $staffCheck = $db->query("SELECT pub FROM staff WHERE bid='$businessId' AND role='owner' LIMIT 1");
    if ($staffCheck && $staffCheck->num_rows) {
        $staff = $staffCheck->fetch_assoc();
        $qrStoreId = $staff['pub'];
    } else {
        $qrStoreId = win_hashs(6);
        $db->query("INSERT INTO staff (bid, pub, role, name, status) VALUES ('$businessId', '$qrStoreId', 'owner', '".$db->real_escape_string($currentUser['username'])."', 'active')");
    }
    $prodCheck = $db->query("SELECT COUNT(*) as cnt FROM products WHERE user_id='$userId' AND status='approved'");
    if ($prodCheck) {
        $hasProducts = ($prodCheck->fetch_assoc()['cnt'] > 0);
    }
} else {
    $anyProducts = $db->query("SELECT COUNT(*) as cnt FROM products WHERE user_id='$userId'")->fetch_assoc()['cnt'];
    if ($anyProducts > 0) {
        $username = $currentUser['username'] ?? 'store_'.$userId;
        $bizName = $currentUser['full_name'] ?: $username;
        $db->query("INSERT INTO business (user_id, name, phone, email, status) VALUES ('$userId', '".$db->real_escape_string($bizName)."', '".$db->real_escape_string($currentUser['phone'] ?? '')."', '".$db->real_escape_string($currentUser['email'] ?? '')."', 'active')");
        $businessId = (int)$db->insert_id;
        $businessName = $bizName;
        $qrStoreId = $currentUser['pubkey'] ?: win_hashs(22);
        if (!$currentUser['pubkey']) {
            $db->query("UPDATE users SET pubkey='$qrStoreId' WHERE id='$userId'");
        }
        $db->query("INSERT INTO staff (bid, pub, role, name, status) VALUES ('$businessId', '$qrStoreId', 'owner', '".$db->real_escape_string($username)."', 'active')");
    }
}

if ($qrStoreId) {
    $qrStoreUrl = $scheme.'://'.$host.$base.'/qrstore/'.$qrStoreId;
    $qrApiUrl = $scheme.'://'.$host.$base.'/api/qr.php?store='.$qrStoreId;
}

$total_products = $db->query("SELECT COUNT(*) as cnt FROM products WHERE user_id='$userId' AND status='approved'")->fetch_assoc()['cnt'];
$total_services = $db->query("SELECT COUNT(*) as cnt FROM services WHERE user_id='$userId' AND status='active'")->fetch_assoc()['cnt'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <base href="<?php echo SITE_URL; ?>">
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>My QR Store - CampMart</title>
    <script src="tailwind34.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        "primary": "#f48c25",
                        "brand-green": "#064E3B",
                        "background-main": "#F9FAFB",
                        "text-dark": "#1F2937",
                    },
                    fontFamily: { "display": ["Inter"] },
                },
            },
        }
    </script>
</head>
<body class="bg-background-main min-h-screen text-text-dark">
<?php include_once 'includes/user-nav.php'; ?>
<main class="flex-1 overflow-y-auto bg-background-main p-4 md:p-6 lg:p-8">
    <div class="max-w-4xl mx-auto space-y-6">

        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold tracking-tight text-brand-green">My QR Store</h1>
                <p class="text-slate-500 text-sm mt-1">Share your store link to promote your products anywhere.</p>
            </div>
            <?php if ($qrStoreId): ?>
            <a href="<?php echo htmlspecialchars($qrStoreUrl); ?>" target="_blank" class="inline-flex items-center gap-2 px-4 py-2 bg-primary hover:bg-primary/90 rounded-lg text-white text-sm font-bold transition-colors shadow-sm">
                <span class="material-symbols-outlined text-lg">open_in_new</span>
                Preview Store
            </a>
            <?php endif; ?>
        </div>

        <?php if (!$qrStoreId): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-6 text-center">
            <span class="material-symbols-outlined text-4xl text-amber-500 mb-3">info</span>
            <h2 class="text-lg font-bold text-amber-800">No Store Yet</h2>
            <p class="text-sm text-amber-700 mt-1">Create and publish a product first to get your unique QR store.</p>
            <a href="my-products.php" class="inline-flex items-center gap-2 mt-4 px-5 py-2.5 bg-primary hover:bg-primary/90 rounded-lg text-white text-sm font-bold transition-colors">
                <span class="material-symbols-outlined text-lg">add_circle</span>
                Add Product
            </a>
        </div>
        <?php else: ?>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="md:col-span-1">
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm text-center">
                    <div id="qrcode" class="mx-auto w-48 h-48 flex items-center justify-center"></div>
                    <p class="text-xs text-slate-500 mt-3">Scan with your phone to view store</p>
                    <div class="mt-4 flex flex-col gap-2">
                        <a id="downloadBtn" href="#" class="flex items-center justify-center gap-2 w-full py-2.5 bg-primary hover:bg-primary/90 rounded-lg text-white text-sm font-bold transition-colors">
                            <span class="material-symbols-outlined text-lg">download</span>
                            Download QR Code
                        </a>
                        <button onclick="copyLink('<?php echo htmlspecialchars($qrStoreUrl); ?>')" class="flex items-center justify-center gap-2 w-full py-2.5 border border-slate-200 hover:bg-slate-50 rounded-lg text-slate-700 text-sm font-bold transition-colors">
                            <span class="material-symbols-outlined text-lg">content_copy</span>
                            Copy Store Link
                        </button>
                    </div>
                </div>
            </div>
            <div class="md:col-span-2 space-y-4">
                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-bold text-brand-green mb-4">Store Details</h2>
                    <div class="space-y-3">
                        <div class="flex items-center justify-between py-2 border-b border-slate-100">
                            <span class="text-sm text-slate-500">Store Name</span>
                            <span class="text-sm font-bold"><?php echo htmlspecialchars($businessName ?: 'Your Store'); ?></span>
                        </div>
                        <div class="flex items-center justify-between py-2 border-b border-slate-100">
                            <span class="text-sm text-slate-500">Store Link</span>
                            <span class="text-sm font-bold text-primary truncate max-w-[250px]"><?php echo htmlspecialchars($qrStoreUrl); ?></span>
                        </div>
                        <div class="flex items-center justify-between py-2 border-b border-slate-100">
                            <span class="text-sm text-slate-500">Store ID</span>
                            <span class="text-sm font-mono font-bold bg-slate-100 px-2 py-0.5 rounded"><?php echo htmlspecialchars($qrStoreId); ?></span>
                        </div>
                        <div class="flex items-center justify-between py-2 border-b border-slate-100">
                            <span class="text-sm text-slate-500">Approved Products</span>
                            <span class="text-sm font-bold"><?php echo $total_products; ?></span>
                        </div>
                        <div class="flex items-center justify-between py-2">
                            <span class="text-sm text-slate-500">Active Services</span>
                            <span class="text-sm font-bold"><?php echo $total_services; ?></span>
                        </div>
                    </div>
                </div>

                <?php if (!$hasProducts): ?>
                <div class="rounded-xl border border-amber-200 bg-amber-50 p-5">
                    <div class="flex items-start gap-3">
                        <span class="material-symbols-outlined text-amber-600">info</span>
                        <div>
                            <h3 class="text-sm font-bold text-amber-800">No approved products yet</h3>
                            <p class="text-xs text-amber-700 mt-1">Your QR store will show products once they are approved. Add products and ensure they get approved to activate your store.</p>
                            <a href="my-products.php" class="inline-flex items-center gap-1 mt-3 text-sm font-bold text-amber-800 hover:text-amber-900">
                                <span class="material-symbols-outlined text-base">arrow_forward</span>
                                Go to My Products
                            </a>
                        </div>
                    </div>
                </div>
                <?php else: ?>
                <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-5">
                    <div class="flex items-start gap-3">
                        <span class="material-symbols-outlined text-emerald-600">check_circle</span>
                        <div>
                            <h3 class="text-sm font-bold text-emerald-800">Store is active</h3>
                            <p class="text-xs text-emerald-700 mt-1">Your QR store is live with <?php echo $total_products; ?> product(s). Share your QR code or store link to start receiving orders.</p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 class="text-lg font-bold text-brand-green mb-4">How to Share</h2>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="text-center p-4 rounded-lg bg-slate-50">
                            <span class="material-symbols-outlined text-3xl text-primary">download</span>
                            <h3 class="text-sm font-bold mt-2">Download QR</h3>
                            <p class="text-xs text-slate-500 mt-1">Save the QR image and print it on flyers, posters, or business cards.</p>
                        </div>
                        <div class="text-center p-4 rounded-lg bg-slate-50">
                            <span class="material-symbols-outlined text-3xl text-primary">share</span>
                            <h3 class="text-sm font-bold mt-2">Share Link</h3>
                            <p class="text-xs text-slate-500 mt-1">Copy your store link and share on WhatsApp, Instagram, or Twitter.</p>
                        </div>
                        <div class="text-center p-4 rounded-lg bg-slate-50">
                            <span class="material-symbols-outlined text-3xl text-primary">qr_code_scanner</span>
                            <h3 class="text-sm font-bold mt-2">Display QR</h3>
                            <p class="text-xs text-slate-500 mt-1">Show the QR code at your shop or stall for customers to scan.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</main>

<script>
var STORE_URL = '<?php echo htmlspecialchars($qrStoreUrl, ENT_QUOTES, 'UTF-8'); ?>';

document.addEventListener('DOMContentLoaded', function() {
    var qrEl = document.getElementById('qrcode');
    if (qrEl && typeof QRCode !== 'undefined' && STORE_URL) {
        new QRCode(qrEl, {
            text: STORE_URL,
            width: 192,
            height: 192,
            colorDark: '#1F2937',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.H
        });
    }
    var dlBtn = document.getElementById('downloadBtn');
    if (dlBtn && STORE_URL) {
        dlBtn.addEventListener('click', function(e) {
            var cvs = qrEl.querySelector('canvas');
            if (cvs) {
                e.preventDefault();
                var link = document.createElement('a');
                link.download = 'qrcode-<?php echo htmlspecialchars($qrStoreId); ?>.png';
                link.href = cvs.toDataURL('image/png');
                link.click();
            }
        });
    }
});

function copyLink(url) {
    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(() => showToast('Store link copied!')).catch(() => fallbackCopy(url));
    } else {
        fallbackCopy(url);
    }
}
function fallbackCopy(text) {
    const ta = document.createElement('textarea');
    ta.value = text;
    ta.style.position = 'fixed';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.select();
    try { document.execCommand('copy'); showToast('Store link copied!'); } catch (e) { alert('Copy manually: ' + text); }
    document.body.removeChild(ta);
}
function showToast(msg) {
    const old = document.getElementById('qrToast');
    if (old) old.remove();
    const t = document.createElement('div');
    t.id = 'qrToast';
    t.textContent = msg;
    t.className = 'fixed bottom-6 left-1/2 -translate-x-1/2 z-50 bg-brand-green text-white px-5 py-3 rounded-xl text-sm font-bold shadow-2xl animate-bounce';
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 3000);
}

// Mobile sidebar toggle
const menuToggle = document.getElementById('menuToggle');
const sidebar = document.getElementById('sidebar');
const sidebarOverlay = document.getElementById('sidebarOverlay');
if (menuToggle) {
    menuToggle.addEventListener('click', () => {
        sidebar.classList.toggle('-translate-x-full');
        sidebarOverlay.classList.toggle('hidden');
        document.body.classList.toggle('overflow-hidden');
    });
    sidebarOverlay.addEventListener('click', () => {
        sidebar.classList.add('-translate-x-full');
        sidebarOverlay.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    });
}
</script>
</body>
</html>
