<?php
session_start();
include_once 'includes/controller.php';

if (!isset($userId) || !isset($currentUser) || !in_array($currentUser['role'], ['admin', 'superadmin'])) {
    header('Location: index.php');
    exit;
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'create_plan') {
        $name = trim($_POST['name'] ?? '');
        $price = (float) ($_POST['price'] ?? 0);
        $duration = (int) ($_POST['duration_days'] ?? 7);
        $entityType = $_POST['entity_type'] ?? 'all';
        $description = trim($_POST['description'] ?? '');
        $sortOrder = (int) ($_POST['sort_order'] ?? 0);

        if ($name === '' || $price <= 0 || $duration <= 0) {
            $_SESSION['error'] = 'Please fill in all required fields with valid values.';
        } else {
            $slug = slugify($name);
            dbInsert('subscription_plans', [
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
                'price' => $price,
                'duration_days' => $duration,
                'entity_type' => $entityType,
                'sort_order' => $sortOrder,
            ]);
            $_SESSION['success'] = "Plan \"{$name}\" created successfully.";
        }
    } elseif ($action === 'update_plan') {
        $planId = (int) ($_POST['plan_id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        $price = (float) ($_POST['price'] ?? 0);
        $duration = (int) ($_POST['duration_days'] ?? 7);
        $entityType = $_POST['entity_type'] ?? 'all';
        $description = trim($_POST['description'] ?? '');
        $sortOrder = (int) ($_POST['sort_order'] ?? 0);

        if ($planId > 0 && $name !== '' && $price > 0 && $duration > 0) {
            dbUpdate('subscription_plans', [
                'name' => $name,
                'price' => $price,
                'duration_days' => $duration,
                'entity_type' => $entityType,
                'description' => $description,
                'sort_order' => $sortOrder,
            ], ['id' => $planId]);
            $_SESSION['success'] = "Plan updated successfully.";
        }
    } elseif ($action === 'toggle_plan') {
        $planId = (int) ($_POST['plan_id'] ?? 0);
        if ($planId > 0) {
            $stmt = $db->prepare("UPDATE subscription_plans SET is_active = NOT is_active WHERE id = ?");
            $stmt->bind_param('i', $planId);
            $stmt->execute();
            $_SESSION['success'] = "Plan status toggled.";
        }
    } elseif ($action === 'delete_plan') {
        $planId = (int) ($_POST['plan_id'] ?? 0);
        if ($planId > 0) {
            $check = $db->prepare("SELECT COUNT(*) as cnt FROM featured_subscriptions WHERE plan_id = ?");
            $check->bind_param('i', $planId);
            $check->execute();
            $cnt = (int) $check->get_result()->fetch_assoc()['cnt'];
            if ($cnt > 0) {
                $_SESSION['error'] = "Cannot delete a plan that has existing subscriptions. Deactivate it instead.";
            } else {
                dbDelete('subscription_plans', ['id' => $planId]);
                $_SESSION['success'] = "Plan deleted.";
            }
        }
    }

    header('Location: manage-plans.php');
    exit;
}

// Fetch all plans
$plansResult = $db->query("SELECT * FROM subscription_plans ORDER BY sort_order ASC, id ASC");
$plans = [];
if ($plansResult) {
    while ($row = $plansResult->fetch_assoc()) {
        $plans[] = $row;
    }
}

// Count active subscriptions per plan
$subCounts = [];
$countResult = $db->query("SELECT plan_id, COUNT(*) as cnt FROM featured_subscriptions WHERE status = 'active' GROUP BY plan_id");
if ($countResult) {
    while ($row = $countResult->fetch_assoc()) {
        $subCounts[$row['plan_id']] = (int) $row['cnt'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Manage Plans | CampMart Admin</title>
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
                        accent: '#FFE6D0',
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
    <style>.material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }</style>
</head>
<body class="bg-background-main min-h-screen text-text-dark">
    <?php include_once 'includes/user-nav.php'; ?>
    <main class="flex-1 overflow-y-auto bg-background-main p-4 md:p-6 lg:p-8">
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
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-500">Admin / Featured</p>
                    <h1 class="text-3xl font-bold text-brand-green">Subscription Plans</h1>
                    <p class="text-slate-500">Manage featured subscription plans and pricing.</p>
                </div>
                <div class="flex gap-3">
                    <a href="manage-featured.php" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-slate-200 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50">
                        <span class="material-symbols-outlined text-base">workspace_premium</span>
                        Featured Listings
                    </a>
                    <button onclick="document.getElementById('createPlanModal').classList.remove('hidden')" class="inline-flex items-center gap-2 px-4 py-2 bg-primary text-white rounded-lg text-sm font-semibold hover:bg-primary/90">
                        <span class="material-symbols-outlined text-base">add</span>
                        Create Plan
                    </button>
                </div>
            </div>

            <!-- Plans Grid -->
            <?php if (empty($plans)): ?>
                <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-12 text-center">
                    <span class="material-symbols-outlined text-5xl text-slate-300 mb-3">card_membership</span>
                    <p class="text-slate-500 font-medium">No subscription plans yet.</p>
                    <p class="text-sm text-slate-400 mt-1">Create your first plan to get started.</p>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                    <?php foreach ($plans as $plan): ?>
                        <div class="bg-white rounded-2xl border border-slate-200 shadow-sm p-6 flex flex-col <?= $plan['is_active'] ? '' : 'opacity-60' ?>">
                            <div class="flex items-start justify-between mb-4">
                                <div>
                                    <h3 class="text-lg font-bold text-slate-900"><?= htmlspecialchars($plan['name']) ?></h3>
                                    <p class="text-xs text-slate-500 mt-0.5"><?= $plan['duration_days'] ?> days · <?= ucfirst($plan['entity_type']) ?></p>
                                </div>
                                <span class="px-2.5 py-1 rounded-full text-xs font-bold <?= $plan['is_active'] ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' ?>">
                                    <?= $plan['is_active'] ? 'Active' : 'Inactive' ?>
                                </span>
                            </div>

                            <div class="mb-4">
                                <p class="text-3xl font-extrabold text-brand-green"><?= formatCurrency((float) $plan['price']) ?></p>
                                <p class="text-sm text-slate-500">for <?= $plan['duration_days'] ?> days</p>
                            </div>

                            <?php if (!empty($plan['description'])): ?>
                                <p class="text-sm text-slate-600 mb-4 flex-1"><?= htmlspecialchars($plan['description']) ?></p>
                            <?php else: ?>
                                <div class="flex-1"></div>
                            <?php endif; ?>

                            <div class="flex items-center justify-between text-xs text-slate-500 mb-4 pt-4 border-t border-slate-100">
                                <span>Active subscriptions: <strong class="text-slate-700"><?= $subCounts[$plan['id']] ?? 0 ?></strong></span>
                                <span>Sort: <?= $plan['sort_order'] ?></span>
                            </div>

                            <div class="flex gap-2">
                                <button onclick="openEditPlan(<?= htmlspecialchars(json_encode($plan)) ?>)" class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-slate-100 text-xs font-bold text-slate-700 hover:bg-slate-200 transition">
                                    <span class="material-symbols-outlined text-sm">edit</span>
                                    Edit
                                </button>
                                <form method="POST" onsubmit="return confirm('Toggle plan active status?')">
                                    <input type="hidden" name="action" value="toggle_plan">
                                    <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
                                    <button type="submit" class="inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl text-xs font-bold border transition <?= $plan['is_active'] ? 'bg-amber-50 border-amber-300 text-amber-700 hover:bg-amber-100' : 'bg-emerald-50 border-emerald-300 text-emerald-700 hover:bg-emerald-100' ?>">
                                        <span class="material-symbols-outlined text-sm"><?= $plan['is_active'] ? 'pause' : 'play_arrow' ?></span>
                                        <?= $plan['is_active'] ? 'Deactivate' : 'Activate' ?>
                                    </button>
                                </form>
                                <form method="POST" onsubmit="return confirm('Delete this plan? This cannot be undone.')">
                                    <input type="hidden" name="action" value="delete_plan">
                                    <input type="hidden" name="plan_id" value="<?= $plan['id'] ?>">
                                    <button type="submit" class="inline-flex items-center justify-center px-3 py-2 rounded-xl bg-red-50 border border-red-200 text-red-600 text-xs font-bold hover:bg-red-100 transition">
                                        <span class="material-symbols-outlined text-sm">delete</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Create Plan Modal -->
    <div id="createPlanModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 p-6">
            <div class="flex items-center justify-between mb-5">
                <h2 class="text-xl font-bold text-slate-900">Create Subscription Plan</h2>
                <button onclick="document.getElementById('createPlanModal').classList.add('hidden')" class="p-1 rounded-lg hover:bg-slate-100">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="create_plan">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Plan Name *</label>
                    <input type="text" name="name" required placeholder="e.g. Basic Featured" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Price (₦) *</label>
                        <input type="number" name="price" required min="0" step="0.01" placeholder="500.00" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Duration (days) *</label>
                        <input type="number" name="duration_days" required min="1" placeholder="7" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Entity Type</label>
                        <select name="entity_type" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20">
                            <option value="all">All (Products, Services, Profiles)</option>
                            <option value="product">Products Only</option>
                            <option value="service">Services Only</option>
                            <option value="profile">Profiles Only</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Sort Order</label>
                        <input type="number" name="sort_order" min="0" value="0" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Description</label>
                    <textarea name="description" rows="2" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20 resize-none" placeholder="Optional plan description..."></textarea>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="document.getElementById('createPlanModal').classList.add('hidden')" class="px-5 py-2.5 rounded-xl border border-slate-200 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-primary text-white text-sm font-bold hover:bg-primary/90 transition">Create Plan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Plan Modal -->
    <div id="editPlanModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 p-6">
            <div class="flex items-center justify-between mb-5">
                <h2 class="text-xl font-bold text-slate-900">Edit Subscription Plan</h2>
                <button onclick="document.getElementById('editPlanModal').classList.add('hidden')" class="p-1 rounded-lg hover:bg-slate-100">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="update_plan">
                <input type="hidden" name="plan_id" id="edit_plan_id">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Plan Name *</label>
                    <input type="text" name="name" id="edit_plan_name" required class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Price (₦) *</label>
                        <input type="number" name="price" id="edit_plan_price" required min="0" step="0.01" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20">
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Duration (days) *</label>
                        <input type="number" name="duration_days" id="edit_plan_duration" required min="1" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Entity Type</label>
                        <select name="entity_type" id="edit_plan_entity_type" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20">
                            <option value="all">All</option>
                            <option value="product">Products Only</option>
                            <option value="service">Services Only</option>
                            <option value="profile">Profiles Only</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-1">Sort Order</label>
                        <input type="number" name="sort_order" id="edit_plan_sort" min="0" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-1">Description</label>
                    <textarea name="description" id="edit_plan_description" rows="2" class="w-full px-3 py-2.5 rounded-xl border border-slate-200 text-sm focus:border-primary focus:ring-2 focus:ring-primary/20 resize-none"></textarea>
                </div>
                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="document.getElementById('editPlanModal').classList.add('hidden')" class="px-5 py-2.5 rounded-xl border border-slate-200 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button type="submit" class="px-5 py-2.5 rounded-xl bg-primary text-white text-sm font-bold hover:bg-primary/90 transition">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openEditPlan(plan) {
        document.getElementById('edit_plan_id').value = plan.id;
        document.getElementById('edit_plan_name').value = plan.name;
        document.getElementById('edit_plan_price').value = plan.price;
        document.getElementById('edit_plan_duration').value = plan.duration_days;
        document.getElementById('edit_plan_entity_type').value = plan.entity_type;
        document.getElementById('edit_plan_sort').value = plan.sort_order;
        document.getElementById('edit_plan_description').value = plan.description || '';
        document.getElementById('editPlanModal').classList.remove('hidden');
    }
    </script>
</body>
</html>
