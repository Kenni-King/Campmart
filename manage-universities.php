<?php
session_start();
include_once 'includes/controller.php';

// Admin-only access
if (!isset($userId) || !isset($currentUser) || !in_array($currentUser['role'], ['admin', 'superadmin'])) {
    header('Location: index.php');
    exit;
}

// Handle Add University
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_university') {
    $name = trim($_POST['name'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $country = trim($_POST['country'] ?? 'Nigeria');
    $logo_url = trim($_POST['logo_url'] ?? '');
    $status = isset($_POST['status']) && $_POST['status'] === 'active' ? 'active' : 'inactive';

    if (empty($name)) {
        $message = 'University name is required.';
        $messageType = 'error';
    } elseif (empty($code)) {
        $message = 'University code is required.';
        $messageType = 'error';
    } else {
        // Check if code already exists
        $check_stmt = $db->prepare("SELECT id FROM universities WHERE code = ?");
        $check_stmt->bind_param('s', $code);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $message = 'University code already exists. Please use a different code.';
            $messageType = 'error';
        } else {
            // Insert university
            $insert_stmt = $db->prepare("INSERT INTO universities (name, code, location, country, logo_url, status) VALUES (?, ?, ?, ?, ?, ?)");
            $insert_stmt->bind_param('ssssss', $name, $code, $location, $country, $logo_url, $status);
            
            if ($insert_stmt->execute()) {
                $message = 'University added successfully!';
                $messageType = 'success';
                // Redirect to clear POST data
                header('Location: manage-universities.php?added=1');
                exit;
            } else {
                $message = 'Failed to add university. Please try again.';
                $messageType = 'error';
            }
        }
    }
}

if (isset($_GET['added'])) {
    $message = 'University added successfully!';
    $messageType = 'success';
}

// Handle Edit University
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_university') {
    $id = (int)$_POST['id'];
    $name = trim($_POST['name'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $country = trim($_POST['country'] ?? 'Nigeria');
    $logo_url = trim($_POST['logo_url'] ?? '');
    $status = isset($_POST['status']) && $_POST['status'] === 'active' ? 'active' : 'inactive';

    if (empty($name)) {
        $message = 'University name is required.';
        $messageType = 'error';
    } elseif (empty($code)) {
        $message = 'University code is required.';
        $messageType = 'error';
    } else {
        // Check if code already exists for a different university
        $check_stmt = $db->prepare("SELECT id FROM universities WHERE code = ? AND id != ?");
        $check_stmt->bind_param('si', $code, $id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $message = 'University code already exists. Please use a different code.';
            $messageType = 'error';
        } else {
            // Update university
            $update_stmt = $db->prepare("UPDATE universities SET name = ?, code = ?, location = ?, country = ?, logo_url = ?, status = ? WHERE id = ?");
            $update_stmt->bind_param('ssssssi', $name, $code, $location, $country, $logo_url, $status, $id);
            
            if ($update_stmt->execute()) {
                $message = 'University updated successfully!';
                $messageType = 'success';
                // Redirect to clear POST data
                header('Location: manage-universities.php?updated=1');
                exit;
            } else {
                $message = 'Failed to update university. Please try again.';
                $messageType = 'error';
            }
        }
    }
}

if (isset($_GET['updated'])) {
    $message = 'University updated successfully!';
    $messageType = 'success';
}

$search = trim($_GET['search'] ?? '');
$status = trim($_GET['status'] ?? '');
$country = trim($_GET['country'] ?? '');
$sort = $_GET['sort'] ?? 'newest';

$conditions = [];
$params = [];
$types = '';

if ($search !== '') {
    $conditions[] = "(u.name LIKE CONCAT('%', ?, '%') OR u.code LIKE CONCAT('%', ?, '%') OR u.location LIKE CONCAT('%', ?, '%'))";
    $params = array_merge($params, array_fill(0, 3, $search));
    $types .= 'sss';
}

if ($status !== '') {
    $conditions[] = 'u.status = ?';
    $params[] = $status;
    $types .= 's';
}

if ($country !== '') {
    $conditions[] = 'u.country = ?';
    $params[] = $country;
    $types .= 's';
}

$whereSql = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$order_by_map = [
    'newest' => 'u.created_at DESC',
    'oldest' => 'u.created_at ASC',
    'name_asc' => 'u.name ASC',
    'name_desc' => 'u.name DESC',
    'users' => 'user_count DESC'
];
$order_by = $order_by_map[$sort] ?? $order_by_map['newest'];

$query = "
    SELECT u.id, u.name, u.code, u.location, u.country, u.logo_url, u.status, u.created_at,
           COUNT(users.id) as user_count
    FROM universities u
    LEFT JOIN users ON u.id = users.university_id
    $whereSql
    GROUP BY u.id
    ORDER BY $order_by
    LIMIT 200
";

$stmt = $db->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$universities = $stmt->get_result();

// Get distinct countries for filter
$countries_result = $db->query("SELECT DISTINCT country FROM universities WHERE country IS NOT NULL ORDER BY country");
$countries = [];
while ($row = $countries_result->fetch_assoc()) {
    $countries[] = $row['country'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Manage Universities | CampMart Admin</title>
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
                    <h1 class="text-3xl font-bold text-brand-green">Manage Universities</h1>
                    <p class="text-slate-500">Add, edit, and manage campus institutions.</p>
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

            <!-- Add University Form -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 md:p-6">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-bold text-text-dark flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">add_circle</span>
                        Add New University
                    </h2>
                </div>
                <form method="POST" class="grid grid-cols-1 md:grid-cols-12 gap-3">
                    <input type="hidden" name="action" value="add_university" />
                    
                    <div class="md:col-span-3">
                        <label class="block text-xs font-medium text-slate-700 mb-1">Name *</label>
                        <input type="text" name="name" required placeholder="University of Lagos" class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm" />
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-slate-700 mb-1">Code *</label>
                        <input type="text" name="code" required placeholder="UNILAG" class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm" />
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-slate-700 mb-1">Location</label>
                        <input type="text" name="location" placeholder="Lagos" class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm" />
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-xs font-medium text-slate-700 mb-1">Country</label>
                        <input type="text" name="country" value="Nigeria" placeholder="Nigeria" class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm" />
                    </div>
                    
                    <div class="md:col-span-1 flex items-end">
                        <label class="flex items-center gap-2 text-xs font-medium text-slate-700 cursor-pointer">
                            <input type="checkbox" name="status" value="active" checked class="rounded text-primary" />
                            Active
                        </label>
                    </div>
                    
                    <div class="md:col-span-2 flex items-end">
                        <button type="submit" class="w-full px-4 py-2 bg-primary text-white rounded-lg text-sm font-semibold hover:bg-primary/90 flex items-center justify-center gap-2">
                            <span class="material-symbols-outlined text-base">add</span>
                            Add University
                        </button>
                    </div>
                </form>
            </div>

            <!-- Filter Section -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 md:p-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-1">Search</label>
                        <div class="relative">
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="Name, code, or location" class="w-full pl-10 pr-3 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm" />
                            <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">search</span>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                        <select name="status" class="w-full px-3 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm">
                            <option value="">Any</option>
                            <?php foreach (['active','inactive'] as $opt): ?>
                                <option value="<?= $opt ?>" <?= $status === $opt ? 'selected' : '' ?>><?= ucfirst($opt) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-1">Country</label>
                        <select name="country" class="w-full px-3 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm">
                            <option value="">Any</option>
                            <?php foreach ($countries as $c): ?>
                                <option value="<?= htmlspecialchars($c) ?>" <?= $country === $c ? 'selected' : '' ?>><?= htmlspecialchars($c) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="md:col-span-4 grid grid-cols-1 md:grid-cols-3 gap-3 items-end">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Sort</label>
                            <select name="sort" class="w-full px-3 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 text-sm">
                                <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
                                <option value="oldest" <?= $sort === 'oldest' ? 'selected' : '' ?>>Oldest</option>
                                <option value="name_asc" <?= $sort === 'name_asc' ? 'selected' : '' ?>>Name: A to Z</option>
                                <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name: Z to A</option>
                                <option value="users" <?= $sort === 'users' ? 'selected' : '' ?>>Most Users</option>
                            </select>
                        </div>
                        <div class="flex justify-end gap-2 md:col-span-2 md:justify-end">
                            <a href="manage-universities.php" class="px-4 py-2 bg-slate-100 text-slate-700 rounded-lg text-sm font-medium hover:bg-slate-200">Reset</a>
                            <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-semibold hover:bg-primary/90">Apply</button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-4 py-3 border-b border-slate-100 text-sm text-slate-600 flex items-center justify-between">
                    <span>Showing <?= $universities ? $universities->num_rows : 0 ?> universities (max 200)</span>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50 text-slate-700">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">University</th>
                                <th class="px-4 py-3 text-left font-semibold">Code</th>
                                <th class="px-4 py-3 text-left font-semibold">Location</th>
                                <!-- <th class="px-4 py-3 text-left font-semibold">Country</th> -->
                                <th class="px-4 py-3 text-left font-semibold">Status</th>
                                <th class="px-4 py-3 text-left font-semibold">Users</th>
                                <th class="px-4 py-3 text-left font-semibold">Created</th>
                                <th class="px-4 py-3 text-right font-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php if ($universities && $universities->num_rows > 0): ?>
                                <?php while($uni = $universities->fetch_assoc()): ?>
                                    <?php
                                        $statusColor = [
                                            'active' => 'bg-emerald-100 text-emerald-700',
                                            'inactive' => 'bg-slate-100 text-slate-700'
                                        ];
                                    ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="px-4 py-3 align-top">
                                            <div class="flex items-start gap-3">
                                                <?php if (!empty($uni['logo_url'])): ?>
                                                    <img src="<?= htmlspecialchars($uni['logo_url']) ?>" class="w-12 h-12 rounded-md object-cover border border-slate-200" alt="<?= htmlspecialchars($uni['name']) ?>" />
                                                <?php else: ?>
                                                    <div class="w-12 h-12 rounded-md bg-slate-100 flex items-center justify-center border border-slate-200">
                                                        <span class="material-symbols-outlined text-slate-400">school</span>
                                                    </div>
                                                <?php endif; ?>
                                                <div class="min-w-0">
                                                    <div class="font-semibold text-text-dark break-words"><?= htmlspecialchars($uni['name']) ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-blue-100 text-blue-700">
                                                <?= htmlspecialchars($uni['code']) ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 align-top text-sm text-text-dark">
                                            <?= htmlspecialchars($uni['location'] ?? '—') ?>
                                        </td>
                                        <!-- <td class="px-4 py-3 align-top text-sm text-text-dark">
                                            <?= htmlspecialchars($uni['country'] ?? '—') ?>
                                        </td> -->
                                        <td class="px-4 py-3 align-top">
                                            <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold <?= $statusColor[$uni['status']] ?? 'bg-slate-100 text-slate-700' ?>">
                                                <?= ucfirst($uni['status']) ?>
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 align-top">
                                            <div class="font-semibold text-text-dark"><?= number_format((int)$uni['user_count']) ?></div>
                                            <div class="text-xs text-slate-500">registered</div>
                                        </td>
                                        <td class="px-4 py-3 align-top text-xs text-slate-600">
                                            <?= date('M d, Y', strtotime($uni['created_at'])) ?>
                                        </td>
                                        <td class="px-4 py-3 align-top text-right space-x-2">
                                            <button onclick="openEditModal(<?= (int)$uni['id'] ?>, '<?= htmlspecialchars(addslashes($uni['name'])) ?>', '<?= htmlspecialchars(addslashes($uni['code'])) ?>', '<?= htmlspecialchars(addslashes($uni['location'] ?? '')) ?>', '<?= htmlspecialchars(addslashes($uni['country'] ?? '')) ?>', '<?= htmlspecialchars(addslashes($uni['logo_url'] ?? '')) ?>', '<?= $uni['status'] ?>')" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-white border border-slate-200 text-xs font-semibold text-slate-700 hover:border-primary/60 hover:text-primary">
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
                                    <td colspan="8" class="px-4 py-6 text-center text-slate-500">No universities found for the current filters.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Edit University Modal -->
    <div id="editModal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/50">
        <div class="bg-white rounded-xl shadow-2xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
            <div class="sticky top-0 bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between">
                <h2 class="text-xl font-bold text-brand-green flex items-center gap-2">
                    <span class="material-symbols-outlined">edit</span>
                    Edit University
                </h2>
                <button onclick="closeEditModal()" class="p-2 hover:bg-slate-100 rounded-lg transition-colors">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <form method="POST" class="p-6 space-y-4">
                <input type="hidden" name="action" value="edit_university" />
                <input type="hidden" id="edit_id" name="id" />
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-2">University Name *</label>
                        <input type="text" id="edit_name" name="name" required placeholder="University of Lagos" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20" />
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Code *</label>
                        <input type="text" id="edit_code" name="code" required placeholder="UNILAG" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20" />
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Country</label>
                        <input type="text" id="edit_country" name="country" placeholder="Nigeria" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20" />
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-2">Location</label>
                        <input type="text" id="edit_location" name="location" placeholder="Lagos" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20" />
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-slate-700 mb-2">Logo URL</label>
                        <input type="text" id="edit_logo_url" name="logo_url" placeholder="https://example.com/logo.png" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20" />
                    </div>
                    
                    <div class="md:col-span-2">
                        <label class="flex items-center gap-3 text-sm font-medium text-slate-700 cursor-pointer">
                            <input type="checkbox" id="edit_status" name="status" value="active" class="rounded text-primary w-5 h-5" />
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
        function openEditModal(id, name, code, location, country, logoUrl, status) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_name').value = name;
            document.getElementById('edit_code').value = code;
            document.getElementById('edit_location').value = location;
            document.getElementById('edit_country').value = country;
            document.getElementById('edit_logo_url').value = logoUrl;
            document.getElementById('edit_status').checked = status === 'active';
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
