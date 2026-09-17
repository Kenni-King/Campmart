<?php
session_start();
include_once 'includes/controller.php';

if (!isset($userId) || !isset($currentUser) || !in_array($currentUser['role'], ['admin', 'superadmin'])) {
    header('Location: index.php');
    exit;
}

$serviceId = (int) ($_GET['id'] ?? 0);
if ($serviceId < 1) {
    $_SESSION['error'] = 'Service not found.';
    header('Location: manage-services.php');
    exit;
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'update_status' && isset($_POST['new_status'])) {
        $validStatuses = ['active', 'paused', 'inactive'];
        $newStatus = $_POST['new_status'];
        if (in_array($newStatus, $validStatuses, true)) {
            $stmt = $db->prepare("UPDATE services SET status = ? WHERE id = ?");
            $stmt->bind_param('si', $newStatus, $serviceId);
            $stmt->execute();
            $_SESSION['success'] = "Service status changed to " . ucfirst($newStatus) . ".";
        }
    } elseif ($action === 'update_availability' && isset($_POST['new_availability'])) {
        $validAvail = ['available', 'busy', 'unavailable'];
        $newAvail = $_POST['new_availability'];
        if (in_array($newAvail, $validAvail, true)) {
            $stmt = $db->prepare("UPDATE services SET availability = ? WHERE id = ?");
            $stmt->bind_param('si', $newAvail, $serviceId);
            $stmt->execute();
            $_SESSION['success'] = "Availability changed to " . ucfirst($newAvail) . ".";
        }
    } elseif ($action === 'toggle_featured') {
        $stmt = $db->prepare("UPDATE services SET is_featured = NOT is_featured WHERE id = ?");
        $stmt->bind_param('i', $serviceId);
        $stmt->execute();
        $_SESSION['success'] = "Featured flag updated.";
    } elseif ($action === 'delete') {
        $delStmt = $db->prepare("DELETE FROM services WHERE id = ?");
        $delStmt->bind_param('i', $serviceId);
        $delStmt->execute();
        $_SESSION['success'] = "Service deleted successfully.";
        header('Location: manage-services.php');
        exit;
    } elseif ($action === 'cancel_featured') {
        $subId = (int) ($_POST['subscription_id'] ?? 0);
        if ($subId > 0) {
            $stmt = $db->prepare("UPDATE featured_subscriptions SET status = 'cancelled' WHERE id = ? AND entity_type = 'service' AND entity_id = ?");
            $stmt->bind_param('ii', $subId, $serviceId);
            $stmt->execute();
            syncFeaturedStatus($db, 'service', $serviceId);
            $_SESSION['success'] = "Featured subscription cancelled.";
        }
    } elseif ($action === 'activate_featured') {
        $planId = (int) ($_POST['plan_id'] ?? 0);
        if ($planId > 0) {
            $planStmt = $db->prepare("SELECT duration_days, price FROM subscription_plans WHERE id = ? AND is_active = 1");
            $planStmt->bind_param('i', $planId);
            $planStmt->execute();
            $plan = $planStmt->get_result()->fetch_assoc();
            if ($plan) {
                $startDate = date('Y-m-d H:i:s');
                $endDate = date('Y-m-d H:i:s', strtotime($startDate . " +{$plan['duration_days']} days"));
                dbInsert('featured_subscriptions', [
                    'user_id' => $service['user_id'],
                    'entity_type' => 'service',
                    'entity_id' => $serviceId,
                    'plan_id' => $planId,
                    'start_date' => $startDate,
                    'end_date' => $endDate,
                    'amount_paid' => $plan['price'],
                    'status' => 'active',
                ]);
                syncFeaturedStatus($db, 'service', $serviceId);
                $_SESSION['success'] = "Featured subscription activated.";
            }
        }
    }
    header('Location: admin-service.php?id=' . $serviceId);
    exit;
}

// Fetch service with analytics
$serviceQuery = "
    SELECT s.*,
        u.id AS provider_id, u.username AS provider_username, u.full_name AS provider_name,
        u.profile_image AS provider_image, u.is_verified AS provider_verified,
        u.rating AS provider_rating, u.total_sales AS provider_total_sales,
        u.location AS provider_location, u.created_at AS provider_created_at,
        sc.name AS category_name, sc.icon AS category_icon, sc.color_code AS category_color,
        COALESCE(bookmark_stats.bookmark_users, 0) AS bookmark_users
    FROM services s
    LEFT JOIN users u ON s.user_id = u.id
    LEFT JOIN service_categories sc ON s.service_category_id = sc.id
    LEFT JOIN (
        SELECT service_id, COUNT(*) AS bookmark_users
        FROM bookmarks WHERE bookmark_type = 'service' AND service_id IS NOT NULL
        GROUP BY service_id
    ) bookmark_stats ON bookmark_stats.service_id = s.id
    WHERE s.id = ?
    LIMIT 1
";
$serviceStmt = $db->prepare($serviceQuery);
$serviceStmt->bind_param('i', $serviceId);
$serviceStmt->execute();
$service = $serviceStmt->get_result()->fetch_assoc();

if (!$service) {
    $_SESSION['error'] = 'Service not found.';
    header('Location: manage-services.php');
    exit;
}

// Bookmark users
$bookmarkUsersStmt = $db->prepare("
    SELECT b.created_at, u.id, u.username, u.full_name, u.firstname, u.lastname, u.profile_image
    FROM bookmarks b
    JOIN users u ON u.id = b.user_id
    WHERE b.service_id = ? AND b.bookmark_type = 'service'
    ORDER BY b.created_at DESC
");
$bookmarkUsersStmt->bind_param('i', $serviceId);
$bookmarkUsersStmt->execute();
$bookmarkUsers = [];
while ($row = $bookmarkUsersStmt->get_result()->fetch_assoc()) {
    $bookmarkUsers[] = $row;
}

// Decode portfolio images and skills
$portfolioImages = json_decode($service['portfolio_images'] ?? '[]', true);
$skills = json_decode($service['skills'] ?? '[]', true);

// Helpers
function svcStatusBadge(string $status): string {
    $map = [
        'active' => 'bg-emerald-100 text-emerald-700',
        'paused' => 'bg-amber-100 text-amber-700',
        'inactive' => 'bg-slate-100 text-slate-700',
        'available' => 'bg-emerald-100 text-emerald-700',
        'busy' => 'bg-blue-100 text-blue-700',
        'unavailable' => 'bg-slate-100 text-slate-700',
    ];
    return $map[$status] ?? 'bg-slate-100 text-slate-700';
}

function svcStars(float $rating): string {
    $rounded = (int) round($rating);
    $html = '<div class="flex items-center gap-0.5 text-amber-400">';
    for ($i = 1; $i <= 5; $i++) {
        $html .= '<span class="material-symbols-outlined text-sm">' . ($i <= $rounded ? 'star' : 'star_outline') . '</span>';
    }
    $html .= '</div>';
    return $html;
}

function svcMetricCard(string $label, string $value, string $icon, string $tone = 'slate'): string {
    $toneMap = [
        'slate' => 'bg-slate-100 text-slate-600',
        'green' => 'bg-emerald-100 text-emerald-600',
        'blue' => 'bg-sky-100 text-sky-600',
        'orange' => 'bg-orange-100 text-orange-600',
        'purple' => 'bg-violet-100 text-violet-600',
    ];
    $classes = $toneMap[$tone] ?? $toneMap['slate'];
    return '<div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">'
        . '<div class="flex items-center justify-between mb-3">'
        . '<p class="text-[11px] uppercase tracking-widest text-slate-400 font-bold">' . htmlspecialchars($label) . '</p>'
        . '<span class="material-symbols-outlined text-lg px-2 py-1.5 rounded-xl ' . $classes . '">' . htmlspecialchars($icon) . '</span>'
        . '</div>'
        . '<p class="text-xl font-extrabold text-slate-900">' . htmlspecialchars($value) . '</p>'
        . '</div>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Service Profile | CampMart Admin</title>
    <script src="tailwind34.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#f48c25',
                        secondary: '#FF6B35',
                        accent: '#FFE66D',
                        'brand-green': '#064E3B',
                        'background-main': '#F9FAFB',
                    },
                    fontFamily: { display: ['Inter'] }
                }
            }
        }
    </script>
    <style>
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        .scrollbar-hide::-webkit-scrollbar { display: none; }
        .scrollbar-hide { -ms-overflow-style: none; scrollbar-width: none; }
    </style>
</head>
<body class="bg-background-main min-h-screen text-slate-900">
    <?php include_once 'includes/user-nav.php'; ?>

    <main class="flex-1 overflow-y-auto p-4 md:p-6 lg:p-8">
        <div class="max-w-7xl mx-auto space-y-6">

            <?php if (isset($_SESSION['success'])): ?>
                <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl flex items-center gap-3">
                    <span class="material-symbols-outlined text-emerald-600">check_circle</span>
                    <p class="flex-1 text-sm font-medium"><?= htmlspecialchars($_SESSION['success']) ?></p>
                    <button onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-800"><span class="material-symbols-outlined">close</span></button>
                </div>
            <?php unset($_SESSION['success']); endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl flex items-center gap-3">
                    <span class="material-symbols-outlined text-red-600">error</span>
                    <p class="flex-1 text-sm font-medium"><?= htmlspecialchars($_SESSION['error']) ?></p>
                    <button onclick="this.parentElement.remove()" class="text-red-600 hover:text-red-800"><span class="material-symbols-outlined">close</span></button>
                </div>
            <?php unset($_SESSION['error']); endif; ?>

            <!-- Header -->
            <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
                <div>
                    <a class="inline-flex items-center gap-2 text-sm font-semibold text-slate-500 hover:text-brand-green mb-3" href="manage-services.php">
                        <span class="material-symbols-outlined text-base">arrow_back</span>
                        Back to Manage Services
                    </a>
                    <h1 class="text-3xl font-bold text-brand-green">Service Profile</h1>
                    <p class="text-sm text-slate-500 mt-1">Full admin view for this service. ID: <?= $serviceId ?></p>
                </div>
                <div class="flex flex-wrap gap-3">
                    <a href="service.php?slug=<?= htmlspecialchars($service['slug']) ?>" class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-semibold text-slate-700 hover:border-primary/50 transition">
                        <span class="material-symbols-outlined text-base">visibility</span>
                        View live page
                    </a>
                    <a href="manage-services.php" class="inline-flex items-center gap-2 px-4 py-2.5 bg-white border border-slate-200 rounded-xl text-sm font-semibold text-slate-700 hover:border-primary/50 transition">
                        <span class="material-symbols-outlined text-base">arrow_back</span>
                        Back to list
                    </a>
                </div>
            </div>

            <!-- Service Overview + Provider Info -->
            <section class="grid grid-cols-1 xl:grid-cols-[minmax(0,1.2fr)_340px] gap-6">
                <!-- Service Details -->
                <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
                    <!-- Portfolio Images -->
                    <?php if (!empty($portfolioImages) && is_array($portfolioImages)): ?>
                        <div class="mb-5">
                            <div class="relative rounded-xl overflow-hidden bg-slate-100 aspect-[4/3] border border-slate-200">
                                <img id="mainImage" src="<?= htmlspecialchars($portfolioImages[0]) ?>" alt="<?= htmlspecialchars($service['title']) ?>" class="w-full h-full object-cover" />
                            </div>
                            <?php if (count($portfolioImages) > 1): ?>
                                <div class="flex gap-2 mt-3 overflow-x-auto scrollbar-hide pb-1">
                                    <?php foreach ($portfolioImages as $idx => $img): ?>
                                        <button onclick="document.getElementById('mainImage').src='<?= htmlspecialchars($img) ?>'; document.querySelectorAll('.thumb-ring').forEach(e=>e.classList.remove('ring-2','ring-primary')); this.querySelector('.thumb-ring').classList.add('ring-2','ring-primary');" class="shrink-0">
                                            <div class="thumb-ring w-16 h-16 rounded-lg overflow-hidden border border-slate-200 <?= $idx > 0 ? '' : 'ring-2 ring-primary' ?>">
                                                <img src="<?= htmlspecialchars($img) ?>" class="w-full h-full object-cover" alt="Thumbnail" />
                                            </div>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Badges -->
                    <div class="flex flex-wrap items-center gap-2 mb-3">
                        <span class="px-3 py-1 text-xs font-bold rounded-full <?= svcStatusBadge($service['status']) ?>">
                            <?= ucfirst($service['status']) ?>
                        </span>
                        <span class="px-3 py-1 text-xs font-bold rounded-full <?= svcStatusBadge($service['availability']) ?>">
                            <?= ucfirst($service['availability']) ?>
                        </span>
                        <?php if ($service['is_featured']): ?>
                            <span class="px-3 py-1 text-xs font-bold rounded-full bg-amber-100 text-amber-700">Featured</span>
                        <?php endif; ?>
                        <span class="px-3 py-1 text-xs font-bold rounded-full bg-slate-100 text-slate-600">
                            <?= ucfirst(str_replace('_', ' ', $service['pricing_type'])) ?>
                        </span>
                    </div>

                    <h2 class="text-2xl font-extrabold text-slate-900 leading-tight"><?= htmlspecialchars($service['title']) ?></h2>
                    <p class="text-sm text-slate-500 mt-1">
                        <span class="material-symbols-outlined text-sm align-middle"><?= htmlspecialchars($service['category_icon'] ?: 'work') ?></span>
                        <?= htmlspecialchars($service['category_name'] ?: 'Uncategorized') ?>
                    </p>

                    <!-- Price -->
                    <div class="flex items-end gap-3 mt-4">
                        <p class="text-3xl font-extrabold text-brand-green"><?= formatCurrency((float) $service['price']) ?></p>
                        <span class="text-sm text-slate-500 mb-1">/ <?= htmlspecialchars(str_replace('_', ' ', $service['pricing_type'])) ?></span>
                    </div>

                    <!-- Short Description -->
                    <?php if (!empty($service['short_description'])): ?>
                        <div class="mt-4 rounded-xl bg-primary/5 border border-primary/10 px-4 py-3">
                            <p class="text-sm font-semibold text-slate-700"><?= htmlspecialchars($service['short_description']) ?></p>
                        </div>
                    <?php endif; ?>

                    <!-- Description -->
                    <div class="mt-5">
                        <h3 class="text-sm font-bold text-slate-700 mb-2">Description</h3>
                        <p class="text-sm text-slate-600 leading-relaxed whitespace-pre-line"><?= htmlspecialchars($service['description']) ?></p>
                    </div>

                    <!-- Skills -->
                    <?php if (!empty($skills) && is_array($skills)): ?>
                        <div class="mt-5">
                            <h3 class="text-sm font-bold text-slate-700 mb-2">Skills</h3>
                            <div class="flex flex-wrap gap-2">
                                <?php foreach ($skills as $skill): ?>
                                    <span class="px-3 py-1 bg-slate-100 text-slate-700 text-xs font-semibold rounded-full"><?= htmlspecialchars($skill) ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Service Meta -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 mt-5">
                        <div class="rounded-xl bg-slate-50 px-4 py-3">
                            <p class="text-[11px] uppercase tracking-widest text-slate-400 font-bold mb-1">Delivery Time</p>
                            <p class="text-sm font-semibold text-slate-700"><?= htmlspecialchars($service['delivery_time'] ?: '—') ?></p>
                        </div>
                        <div class="rounded-xl bg-slate-50 px-4 py-3">
                            <p class="text-[11px] uppercase tracking-widest text-slate-400 font-bold mb-1">Created</p>
                            <p class="text-sm font-semibold text-slate-700"><?= date('M j, Y g:i A', strtotime($service['created_at'])) ?></p>
                        </div>
                        <div class="rounded-xl bg-slate-50 px-4 py-3">
                            <p class="text-[11px] uppercase tracking-widest text-slate-400 font-bold mb-1">Updated</p>
                            <p class="text-sm font-semibold text-slate-700"><?= date('M j, Y g:i A', strtotime($service['updated_at'])) ?></p>
                        </div>
                        <div class="rounded-xl bg-slate-50 px-4 py-3 sm:col-span-2">
                            <p class="text-[11px] uppercase tracking-widest text-slate-400 font-bold mb-1">Slug</p>
                            <p class="text-sm font-semibold text-slate-700 truncate" title="<?= htmlspecialchars($service['slug']) ?>"><?= htmlspecialchars($service['slug']) ?></p>
                        </div>
                    </div>
                </div>

                <!-- Provider Info + Admin Actions -->
                <div class="space-y-6">
                    <!-- Provider Info -->
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
                        <h3 class="text-sm font-bold text-slate-700 mb-4">Provider Information</h3>
                        <div class="flex items-center gap-3 mb-4">
                            <div class="size-14 rounded-full overflow-hidden bg-brand-green text-white flex items-center justify-center text-xl font-bold shrink-0">
                                <?php if (!empty($service['provider_image'])): ?>
                                    <img src="<?= htmlspecialchars($service['provider_image']) ?>" alt="<?= htmlspecialchars($service['provider_name']) ?>" class="w-full h-full object-cover" />
                                <?php else: ?>
                                    <?= strtoupper(substr($service['provider_name'] ?? 'U', 0, 1)) ?>
                                <?php endif; ?>
                            </div>
                            <div class="min-w-0">
                                <div class="flex items-center gap-1">
                                    <p class="font-bold text-slate-900 truncate"><?= htmlspecialchars($service['provider_name'] ?: 'Unknown') ?></p>
                                    <?php if ($service['provider_verified']): ?>
                                        <span class="material-symbols-outlined text-blue-500 text-sm fill-1">verified</span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-xs text-slate-500">@<?= htmlspecialchars($service['provider_username'] ?: '—') ?></p>
                            </div>
                        </div>
                        <div class="space-y-2 text-sm">
                            <div class="flex items-center justify-between">
                                <span class="text-slate-500">Rating</span>
                                <div class="flex items-center gap-1">
                                    <?= svcStars((float) $service['provider_rating']) ?>
                                    <span class="font-semibold text-slate-700"><?= number_format((float) $service['provider_rating'], 1) ?></span>
                                </div>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-slate-500">Total Sales</span>
                                <span class="font-semibold text-slate-700"><?= (int) $service['provider_total_sales'] ?></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-slate-500">Location</span>
                                <span class="font-semibold text-slate-700 truncate ml-4"><?= htmlspecialchars($service['provider_location'] ?: '—') ?></span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-slate-500">Member Since</span>
                                <span class="font-semibold text-slate-700"><?= date('M Y', strtotime($service['provider_created_at'])) ?></span>
                            </div>
                        </div>
                        <div class="flex gap-2 mt-4">
                            <a href="admin-user.php?id=<?= (int) $service['provider_id'] ?>" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-slate-100 text-xs font-bold text-slate-700 hover:bg-slate-200 transition">
                                <span class="material-symbols-outlined text-sm">person</span>
                                User Profile
                            </a>
                            <a href="store.php?username=<?= htmlspecialchars($service['provider_username']) ?>" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-slate-100 text-xs font-bold text-slate-700 hover:bg-slate-200 transition">
                                <span class="material-symbols-outlined text-sm">storefront</span>
                                Store
                            </a>
                        </div>
                    </div>

                    <!-- Admin Actions -->
                    <div class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
                        <h3 class="text-sm font-bold text-slate-700 mb-4">Admin Actions</h3>

                        <!-- Status Update -->
                        <form method="POST" class="space-y-3" onsubmit="return confirm('Change service status?')">
                            <input type="hidden" name="action" value="update_status">
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Status</label>
                            <select name="new_status" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm font-semibold focus:border-primary focus:ring-2 focus:ring-primary/20">
                                <?php foreach (['active','paused','inactive'] as $opt): ?>
                                    <option value="<?= $opt ?>" <?= $service['status'] === $opt ? 'selected' : '' ?>><?= ucfirst($opt) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl bg-primary text-white text-sm font-bold hover:bg-primary/90 transition">
                                <span class="material-symbols-outlined text-sm">swap_horiz</span>
                                Update Status
                            </button>
                        </form>

                        <hr class="my-4 border-slate-100">

                        <!-- Availability Update -->
                        <form method="POST" class="space-y-3" onsubmit="return confirm('Change availability?')">
                            <input type="hidden" name="action" value="update_availability">
                            <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider">Availability</label>
                            <select name="new_availability" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm font-semibold focus:border-primary focus:ring-2 focus:ring-primary/20">
                                <?php foreach (['available','busy','unavailable'] as $opt): ?>
                                    <option value="<?= $opt ?>" <?= $service['availability'] === $opt ? 'selected' : '' ?>><?= ucfirst($opt) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl bg-white border border-slate-200 text-slate-700 text-sm font-bold hover:bg-slate-50 transition">
                                <span class="material-symbols-outlined text-sm">inventory</span>
                                Update Availability
                            </button>
                        </form>

                        <hr class="my-4 border-slate-100">

                        <!-- Featured Subscription -->
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-3">Featured Subscription</label>
                        <?php
                            $featStmt = $db->prepare("
                                SELECT fs.*, sp.name AS plan_name FROM featured_subscriptions fs
                                JOIN subscription_plans sp ON fs.plan_id = sp.id
                                WHERE fs.entity_type = 'service' AND fs.entity_id = ? AND fs.status = 'active' AND fs.end_date > NOW()
                                ORDER BY fs.end_date DESC LIMIT 1
                            ");
                            $featStmt->bind_param('i', $serviceId);
                            $featStmt->execute();
                            $activeFeat = $featStmt->get_result()->fetch_assoc();

                            $plansRes = $db->query("SELECT id, name, price, duration_days FROM subscription_plans WHERE is_active = 1 AND entity_type IN ('all', 'service') ORDER BY sort_order ASC");
                            $featPlans = [];
                            if ($plansRes) { while ($pr = $plansRes->fetch_assoc()) { $featPlans[] = $pr; } }
                        ?>
                        <?php if ($activeFeat): ?>
                            <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 mb-3">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="material-symbols-outlined text-amber-600 text-sm">workspace_premium</span>
                                    <p class="text-sm font-bold text-amber-800"><?= htmlspecialchars($activeFeat['plan_name']) ?></p>
                                </div>
                                <p class="text-xs text-amber-700">Expires: <?= date('M d, Y g:i A', strtotime($activeFeat['end_date'])) ?></p>
                                <p class="text-xs text-amber-600">Paid: <?= formatCurrency((float) $activeFeat['amount_paid']) ?></p>
                            </div>
                            <form method="POST" onsubmit="return confirm('Cancel this featured subscription?')">
                                <input type="hidden" name="action" value="cancel_featured">
                                <input type="hidden" name="subscription_id" value="<?= $activeFeat['id'] ?>">
                                <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl bg-red-50 border border-red-200 text-red-600 text-sm font-bold hover:bg-red-100 hover:border-red-300 transition">
                                    <span class="material-symbols-outlined text-sm">block</span>
                                    Cancel Featured
                                </button>
                            </form>
                        <?php else: ?>
                            <?php if (!empty($featPlans)): ?>
                                <form method="POST" class="space-y-3" onsubmit="return confirm('Activate featured subscription for this service?')">
                                    <input type="hidden" name="action" value="activate_featured">
                                    <select name="plan_id" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm font-semibold focus:border-primary focus:ring-2 focus:ring-primary/20">
                                        <?php foreach ($featPlans as $fp): ?>
                                            <option value="<?= $fp['id'] ?>"><?= htmlspecialchars($fp['name']) ?> — <?= formatCurrency((float) $fp['price']) ?> (<?= $fp['duration_days'] ?>d)</option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl bg-white border border-amber-300 text-amber-700 text-sm font-bold hover:bg-amber-50 transition">
                                        <span class="material-symbols-outlined text-sm">workspace_premium</span>
                                        Activate Featured
                                    </button>
                                </form>
                            <?php else: ?>
                                <p class="text-xs text-slate-500">No active plans available. <a href="manage-plans.php" class="text-primary hover:underline">Create one</a>.</p>
                            <?php endif; ?>
                        <?php endif; ?>

                        <hr class="my-4 border-slate-100">

                        <!-- Delete -->
                        <form method="POST" onsubmit="return confirm('DELETE this service permanently? This cannot be undone.')">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl bg-red-50 border border-red-200 text-red-600 text-sm font-bold hover:bg-red-100 hover:border-red-300 transition">
                                <span class="material-symbols-outlined text-sm">delete_forever</span>
                                Delete Service
                            </button>
                        </form>
                    </div>
                </div>
            </section>

            <!-- Metrics Grid -->
            <section class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-4">
                <?= svcMetricCard('Rating', number_format((float) $service['rating'], 2) . ' (' . (int) $service['total_ratings'] . ')', 'star', 'orange') ?>
                <?= svcMetricCard('Total Orders', number_format((int) $service['total_orders']), 'receipt_long', 'green') ?>
                <?= svcMetricCard('Views', number_format((int) $service['views_count']), 'visibility', 'blue') ?>
                <?= svcMetricCard('Bookmarks', number_format((int) $service['bookmarks_count']), 'bookmark', 'purple') ?>
                <?= svcMetricCard('Unique Bookmarks', number_format((int) $service['bookmark_users']), 'people', 'slate') ?>
            </section>

            <!-- Bookmark Users -->
            <section class="rounded-2xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h2 class="text-xl font-bold text-slate-900">Saved by Customers</h2>
                        <p class="text-sm text-slate-500 mt-1">Users who bookmarked this service.</p>
                    </div>
                </div>
                <?php if (empty($bookmarkUsers)): ?>
                    <p class="text-sm text-slate-500">No bookmarks yet.</p>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($bookmarkUsers as $person): ?>
                            <?php $name = trim($person['full_name'] ?: trim(($person['firstname'] ?? '') . ' ' . ($person['lastname'] ?? ''))); ?>
                            <div class="flex items-center justify-between gap-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <div class="size-10 rounded-full overflow-hidden bg-brand-green/10 text-brand-green flex items-center justify-center font-bold text-xs shrink-0">
                                        <?php if (!empty($person['profile_image'])): ?>
                                            <img src="<?= htmlspecialchars($person['profile_image']) ?>" class="w-full h-full object-cover" alt="" />
                                        <?php else: ?>
                                            <?= strtoupper(substr($name ?: $person['username'], 0, 1)) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-slate-800 truncate"><?= htmlspecialchars($name ?: $person['username']) ?></p>
                                        <p class="text-xs text-slate-500">@<?= htmlspecialchars($person['username']) ?></p>
                                    </div>
                                </div>
                                <p class="text-xs text-slate-500 shrink-0"><?= date('M j', strtotime($person['created_at'])) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

        </div>
    </main>
</body>
</html>
