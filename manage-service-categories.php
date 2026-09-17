<?php
session_start();
include_once 'includes/controller.php';

// Admin-only access
if (!isset($userId) || !isset($currentUser) || !in_array($currentUser['role'], ['admin', 'superadmin'])) {
    header('Location: index.php');
    exit;
}

// Handle Add Service Category
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_category') {
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $icon = trim($_POST['icon'] ?? '');
    $color_code = trim($_POST['color_code'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (empty($name)) {
        $message = 'Category name is required.';
        $messageType = 'error';
    } elseif (empty($slug)) {
        $message = 'Category slug is required.';
        $messageType = 'error';
    } else {
        // Check if slug already exists
        $check_stmt = $db->prepare("SELECT id FROM service_categories WHERE slug = ?");
        $check_stmt->bind_param('s', $slug);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $message = 'Slug already exists. Please use a different slug.';
            $messageType = 'error';
        } else {
            // Insert category
            $insert_stmt = $db->prepare("INSERT INTO service_categories (name, slug, description, icon, color_code, is_active) VALUES (?, ?, ?, ?, ?, ?)");
            $insert_stmt->bind_param('sssssi', $name, $slug, $description, $icon, $color_code, $is_active);
            
            if ($insert_stmt->execute()) {
                $message = 'Service category added successfully!';
                $messageType = 'success';
                // Redirect to clear POST data
                header('Location: manage-service-categories.php?added=1');
                exit;
            } else {
                $message = 'Failed to add service category. Please try again.';
                $messageType = 'error';
            }
        }
    }
}

if (isset($_GET['added'])) {
    $message = 'Service category added successfully!';
    $messageType = 'success';
}

// Handle Edit Service Category
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_category') {
    $id = (int)$_POST['id'];
    $name = trim($_POST['name'] ?? '');
    $slug = trim($_POST['slug'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $icon = trim($_POST['icon'] ?? '');
    $color_code = trim($_POST['color_code'] ?? '');
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    if (empty($name)) {
        $message = 'Category name is required.';
        $messageType = 'error';
    } elseif (empty($slug)) {
        $message = 'Category slug is required.';
        $messageType = 'error';
    } else {
        // Check if slug already exists for a different category
        $check_stmt = $db->prepare("SELECT id FROM service_categories WHERE slug = ? AND id != ?");
        $check_stmt->bind_param('si', $slug, $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $message = 'Slug already exists. Please use a different slug.';
            $messageType = 'error';
        } else {
            // Update category
            $update_stmt = $db->prepare("UPDATE service_categories SET name = ?, slug = ?, description = ?, icon = ?, color_code = ?, is_active = ? WHERE id = ?");
            $update_stmt->bind_param('sssssii', $name, $slug, $description, $icon, $color_code, $is_active, $id);
            
            if ($update_stmt->execute()) {
                $message = 'Service category updated successfully!';
                $messageType = 'success';
                // Redirect to clear POST data
                header('Location: manage-service-categories.php?updated=1');
                exit;
            } else {
                $message = 'Failed to update service category. Please try again.';
                $messageType = 'error';
            }
        }
    }
}

if (isset($_GET['updated'])) {
    $message = 'Service category updated successfully!';
    $messageType = 'success';
}

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$sort = $_GET['sort'] ?? 'name_asc';

$conditions = [];
$params = [];
$types = '';

if ($search !== '') {
    $conditions[] = "(sc.name LIKE CONCAT('%', ?, '%') OR sc.slug LIKE CONCAT('%', ?, '%') OR sc.description LIKE CONCAT('%', ?, '%'))";
    $params = array_merge($params, array_fill(0, 3, $search));
    $types .= 'sss';
}

if ($status !== '') {
    if ($status === 'active') {
        $conditions[] = 'sc.is_active = 1';
    } else {
        $conditions[] = 'sc.is_active = 0';
    }
}

$whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$order_by_map = [
    'name_asc' => 'sc.name ASC',
    'name_desc' => 'sc.name DESC',
    'newest' => 'sc.created_at DESC',
    'oldest' => 'sc.created_at ASC',
    'services' => 'service_count DESC'
];
$order_by = $order_by_map[$sort] ?? $order_by_map['name_asc'];

$query = "
    SELECT sc.id, sc.name, sc.slug, sc.description, sc.icon, sc.color_code, sc.is_active, sc.created_at,
           COUNT(DISTINCT s.id) as service_count
    FROM service_categories sc
    LEFT JOIN services s ON sc.id = s.service_category_id
    $whereSql
    GROUP BY sc.id
    ORDER BY $order_by
    LIMIT 200
";

$stmt = $db->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$categories = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Manage Service Categories | CampMart Admin</title>
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
    <style>.material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }</style>
</head>
<body class="bg-background-main min-h-screen text-text-dark">
    <?php include_once 'includes/user-nav.php'; ?>
    <main class="flex-1 overflow-y-auto bg-background-main p-4 md:p-6 lg:p-8">
        <div class="max-w-7xl mx-auto space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-brand-green">Manage Service Categories</h1>
                    <p class="text-slate-500">Organize service offerings into categories.</p>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="bg-white rounded-xl border-2 <?= $messageType === 'success' ? 'border-emerald-500 bg-emerald-50' : 'border-red-500 bg-red-50' ?> p-4">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-2xl <?= $messageType === 'success' ? 'text-emerald-600' : 'text-red-600' ?>">
                            <?= $messageType === 'success' ? 'check_circle' : 'error' ?>
                        </span>
                        <p class="font-medium <?= $messageType === 'success' ? 'text-emerald-900' : 'text-red-900' ?>"><?= htmlspecialchars($message) ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Add Service Category Form -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 md:p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold text-text-dark flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">add_circle</span>
                        Add New Service Category
                    </h2>
                </div>
                <form method="POST" class="grid grid-cols-1 md:grid-cols-12 gap-3">
                    <input type="hidden" name="action" value="add_category" />
                    
                    <div class="md:col-span-3">
                        <label class="block text-xs font-medium text-slate-700 mb-1">Name *</label>
                        <input type="text" name="name" required placeholder="Creative Services" class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm" />
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-slate-700 mb-1">Slug *</label>
                        <input type="text" name="slug" required placeholder="creative-services" class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm" />
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-slate-700 mb-1">Icon</label>
                        <input type="text" name="icon" placeholder="design_services" class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm" />
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-slate-700 mb-1">Color Code</label>
                        <input type="text" name="color_code" placeholder="#FF6B35" class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm" />
                    </div>
                    
                    <div class="md:col-span-1 flex items-end">
                        <label class="flex items-center gap-2 text-xs font-medium text-slate-700 cursor-pointer">
                            <input type="checkbox" name="is_active" checked class="rounded text-primary" />
                            Active
                        </label>
                    </div>
                    
                    <div class="md:col-span-2 flex items-end">
                        <button type="submit" class="w-full px-4 py-2 bg-primary text-white rounded-lg text-sm font-semibold hover:bg-primary/90 flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-base">add</span>
                            Add Category
                        </button>
                    </div>
                </form>
            </div>

            <!-- Filter Section -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 md:p-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Search</label>
                        <div class="relative">
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Name, slug, or description" class="w-full pl-10 pr-3 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm" />
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">search</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                        <select name="status" class="w-full px-3 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm">
                            <option value="">Any</option>
                            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Sort</label>
                        <select name="sort" class="w-full px-3 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm">
                            <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name: A to Z</option>
                            <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name: Z to A</option>
                            <option value="services" <?= $sort === 'services' ? 'selected' : '' ?>>Most Services</option>
                            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
                            <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
                        </select>
                    </div>
                    <div class="md:col-span-3 flex justify-end gap-2">
                        <a href="manage-service-categories.php" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-lg text-sm font-medium hover:bg-slate-200">Reset</a>
                        <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-semibold hover:bg-primary/90">Apply</button>
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-100 text-sm text-slate-600 flex items-center justify-between">
                    <span>Showing <?= $categories ? $categories->num_rows : 0 ?> categories (max 200)</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-slate-700">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">Category</th>
                                <th class="px-4 py-3 text-left font-semibold">Slug</th>
                                <th class="px-4 py-3 text-left font-semibold">Color</th>
                                <th class="px-4 py-3 text-left font-semibold">Services</th>
                                <th class="px-4 py-3 text-left font-semibold">Status</th>
                                <th class="px-4 py-3 text-left font-semibold">Created</th>
                                <th class="px-4 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if ($categories && $categories->num_rows > 0): ?>
                                <?php while($cat = $categories->fetch_assoc()): ?>
                                    <?php
                                        $statusColor = $cat['is_active'] ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-700';
                                    ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-4 py-3 align-top">
                                            <div class="flex items-start gap-3">
                                                <?php if (!empty($cat['icon'])): ?>
                                                    <span class="material-symbols-outlined text-2xl text-primary" style="<?= !empty($cat['color_code']) ? 'color: ' . htmlspecialchars($cat['color_code']) : '' ?>"><?= htmlspecialchars($cat['icon']) ?></span>
                                                <?php else: ?>
                                                    <span class="material-symbols-outlined text-2xl text-slate-400">category</span>
                                                <?php endif; ?>
                                                <div class="min-w-0">
                                                    <div class="font-semibold text-text-dark break-words"><?= htmlspecialchars($cat['name']) ?></div>
                                                    <?php if (!empty($cat['description'])): ?>
                                                        <div class="text-xs text-slate-500 mt-1"><?= htmlspecialchars(substr($cat['description'], 0, 80)) ?><?= strlen($cat['description']) > 80 ? '…' : '' ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            <code class="text-xs bg-slate-100 px-2 py-1 rounded text-slate-700"><?= htmlspecialchars($cat['slug']) ?></code>
                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            <?php if (!empty($cat['color_code'])): ?>
                                                <div class="flex items-center gap-2">
                                                    <div class="w-6 h-6 rounded border border-slate-200" style="background-color: <?= htmlspecialchars($cat['color_code']) ?>"></div>
                                                    <code class="text-xs text-slate-600"><?= htmlspecialchars($cat['color_code']) ?></code>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-slate-400">—</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            <div class="font-semibold text-text-dark"><?= number_format((int)$cat['service_count']) ?></div>
                                            <div class="text-xs text-slate-500">services</div>
                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold <?= $statusColor ?>">
                                                <?= $cat['is_active'] ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 align-top text-xs text-slate-600">
                                            <?= date('M d, Y', strtotime($cat['created_at'])) ?>
                                        </td>
                                        <td class="px-4 py-3 align-top text-right space-x-2">
                                            <button onclick="openEditModal(<?= (int)$cat['id'] ?>, '<?= htmlspecialchars(addslashes($cat['name'])) ?>', '<?= htmlspecialchars(addslashes($cat['slug'])) ?>', '<?= htmlspecialchars(addslashes($cat['description'] ?? '')) ?>', '<?= htmlspecialchars(addslashes($cat['icon'] ?? '')) ?>', '<?= htmlspecialchars(addslashes($cat['color_code'] ?? '')) ?>', <?= $cat['is_active'] ? 'true' : 'false' ?>)" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-white border border-slate-200 text-xs font-semibold text-slate-700 hover:border-primary/60 hover:text-primary">
                                                <span class="material-symbols-outlined text-sm">edit</span>
                                                Edit
                                            </button>
                                            <button class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-white border border-slate-200 text-xs font-semibold text-slate-700 hover:border-red-500/60 hover:text-red-600">
                                                <span class="material-symbols-outlined text-sm">delete</span>
                                                Delete
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="px-4 py-6 text-center text-slate-500">No service categories found for the current filters.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Edit Service Category Modal -->
    <div id="editModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between">
                <h2 class="text-xl font-bold text-brand-green flex items-center gap-2">
                    <span class="material-symbols-outlined">edit</span>
                    Edit Service Category
                </h2>
                <button onclick="closeEditModal()" class="p-2 hover:bg-slate-100 rounded-lg transition-colors">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" value="edit_category" />
                <input type="hidden" id="edit_id" name="id" />
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Name *</label>
                        <input type="text" id="edit_name" name="name" required placeholder="Creative Services" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20" />
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Slug *</label>
                        <input type="text" id="edit_slug" name="slug" required placeholder="creative-services" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20" />
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-2">Description</label>
                        <textarea id="edit_description" name="description" rows="3" placeholder="Category description..." class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20"></textarea>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Icon</label>
                        <input type="text" id="edit_icon" name="icon" placeholder="design_services" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20" />
                        <p class="text-xs text-slate-500 mt-1">Material Symbols icon name</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Color Code</label>
                        <input type="text" id="edit_color_code" name="color_code" placeholder="#FF6B35" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20" />
                        <p class="text-xs text-slate-500 mt-1">Hex color code</p>
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="flex items-center gap-3 text-sm font-medium text-slate-700 cursor-pointer">
                            <input type="checkbox" id="edit_is_active" name="is_active" class="rounded text-primary w-5 h-5" />
                            <span>Active</span>
                        </label>
                    </div>
                </div>
                
                <div class="flex justify-end gap-3 pt-4 border-t border-slate-200">
                    <button type="button" onclick="closeEditModal()" class="px-4 py-2 rounded-lg border border-slate-200 text-slate-700 font-medium hover:bg-slate-50">
                        Cancel
                    </button>
                    <button type="submit" class="px-4 py-2 rounded-lg bg-primary text-white font-semibold hover:bg-primary/90 flex items-center gap-2">
                        <span class="material-symbols-outlined text-base">save</span>
                        Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Mobile menu toggle
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        menuToggle?.addEventListener('click', () => {
            sidebar.classList.toggle('-translate-x-full');
            sidebarOverlay.classList.toggle('hidden');
        });

        sidebarOverlay?.addEventListener('click', () => {
            sidebar.classList.add('-translate-x-full');
            sidebarOverlay.classList.add('hidden');
        });

        // Edit Modal Functions
        function openEditModal(id, name, slug, description, icon, colorCode, isActive) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_slug').value = slug;
            document.getElementById('edit_description').value = description;
            document.getElementById('edit_icon').value = icon;
            document.getElementById('edit_color_code').value = colorCode;
            document.getElementById('edit_is_active').checked = isActive;
            document.getElementById('editModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        // Close modal on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeEditModal();
            }
        });

        // Close modal on backdrop click
        document.getElementById('editModal')?.addEventListener('click', (e) => {
            if (e.target.id === 'editModal') {
                closeEditModal();
            }
        });
    </script>
</body>
</html>
