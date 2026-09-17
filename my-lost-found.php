<?php session_start();
include_once 'includes/controller.php';

$userId = $_SESSION['userAppId'] ?? 0;

// Handle Delete Lost/Found Item
if(isset($_POST['delete_item']) && isset($_POST['item_id'])) {
    header('Content-Type: application/json');
    $item_id = (int)$_POST['item_id'];
    
    // Verify ownership
    $check = $db->query("SELECT id FROM lost_found_items WHERE id = '$item_id' AND user_id = '$userId'");
    if($check->num_rows > 0) {
        $delete = $db->query("DELETE FROM lost_found_items WHERE id = '$item_id'");
        if($delete) {
            echo json_encode(['success' => true, 'message' => 'Item deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database delete failed']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Item not found or access denied']);
    }
    exit;
}

// Handle Mark as Claimed
if(isset($_POST['mark_as_claimed']) && isset($_POST['item_id'])) {
    header('Content-Type: application/json');
    $item_id = (int)$_POST['item_id'];
    
    // Verify ownership
    $check = $db->query("SELECT id FROM lost_found_items WHERE id = '$item_id' AND user_id = '$userId'");
    if($check->num_rows > 0) {
        $update = $db->query("UPDATE lost_found_items SET status = 'claimed' WHERE id = '$item_id'");
        if($update) {
            echo json_encode(['success' => true, 'message' => 'Item marked as claimed']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database update failed']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Item not found or access denied']);
    }
    exit;
}

// Handle Mark as Open
if(isset($_POST['mark_as_open']) && isset($_POST['item_id'])) {
    header('Content-Type: application/json');
    $item_id = (int)$_POST['item_id'];
    
    // Verify ownership
    $check = $db->query("SELECT id FROM lost_found_items WHERE id = '$item_id' AND user_id = '$userId'");
    if($check->num_rows > 0) {
        $update = $db->query("UPDATE lost_found_items SET status = 'open' WHERE id = '$item_id'");
        if($update) {
            echo json_encode(['success' => true, 'message' => 'Item marked as open']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database update failed']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Item not found or access denied']);
    }
    exit;
}

// Handle Edit Item - Load item data if edit parameter is present
$editItem = null;
if(isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $edit_query = $db->query("SELECT * FROM lost_found_items WHERE id = '$edit_id' AND user_id = '$userId'");
    if($edit_query->num_rows > 0) {
        $editItem = $edit_query->fetch_assoc();
    }
}

// Handle Update Item
if(isset($_POST['UpdateLostFound'])) {
    $item_id = (int)$_POST['item_id'];
    $type = $db->real_escape_string($_POST['type']);
    $title = $db->real_escape_string($_POST['title']);
    $category = $db->real_escape_string($_POST['category']);
    $date_lost_found = $db->real_escape_string($_POST['date_lost_found']);
    $location_lost_found = $db->real_escape_string($_POST['location_lost_found']);
    $description = $db->real_escape_string($_POST['description']);
    $contact_info = $db->real_escape_string($_POST['contact_info']);
    
    // Verify ownership
    $check = $db->query("SELECT id FROM lost_found_items WHERE id = '$item_id' AND user_id = '$userId'");
    if($check->num_rows > 0) {
        $update = $db->query("UPDATE lost_found_items SET 
            type = '$type',
            title = '$title',
            category = '$category',
            date_lost_found = '$date_lost_found',
            location_lost_found = '$location_lost_found',
            description = '$description',
            contact_info = '$contact_info'
            WHERE id = '$item_id'");
        
        if($update) {
            include_once 'includes/ai/lostfound.php';
            ai_index_lost_found((int) $item_id);
            $_SESSION['success'] = 'Item updated successfully!';
            header('Location: my-lost-found.php');
            exit;
        } else {
            $_SESSION['error'] = 'Failed to update item';
        }
    } else {
        $_SESSION['error'] = 'Item not found or access denied';
    }
}

$lost_found_query = $db->query("SELECT * FROM lost_found_items 
    WHERE user_id = '$userId' 
    ORDER BY created_at DESC");

$lost_found_count = $lost_found_query->num_rows;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>My Lost & Found | CampMart</title>
    <script src="tailwind34.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet" />
    <script id="tailwind-config">
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        "primary": "#f48c25",
                        "brand-green": "#064E3B",
                        "brand-green-light": "#F0FDF4",
                        "background-main": "#F9FAFB",
                        "surface-white": "#FFFFFF",
                        "text-dark": "#1F2937",
                    },
                    fontFamily: {
                        "display": ["Inter"]
                    },
                    borderRadius: {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "full": "9999px"
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
            color: #1F2937;
        }
    </style>
</head>

<body class="bg-background-main min-h-screen text-text-dark">
    <?php include_once 'includes/user-nav.php'; ?>
    <main class="flex-1 overflow-y-auto bg-background-main p-4 md:p-6 lg:p-8">
        <div class="max-w-7xl mx-auto space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-brand-green">My Lost & Found</h1>
                    <p class="text-slate-500 mt-1">Help reunite lost items with their owners or find your missing belongings.</p>
                </div>
                <button onclick="toggleNewListingForm()" id="toggleFormBtn" class="flex items-center gap-2 px-4 py-2.5 bg-primary text-white rounded-lg font-medium hover:bg-primary/90 transition-colors">
                    <span class="material-symbols-outlined text-xl">add</span>
                    <span>New Item</span>
                </button>
            </div>

            <?php if(isset($_SESSION['success'])): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg flex items-center gap-3" id="successMessage">
                <span class="material-symbols-outlined text-emerald-600">check_circle</span>
                <p class="flex-1"><?= htmlspecialchars($_SESSION['success']) ?></p>
                <button onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-800">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <?php unset($_SESSION['success']); endif; ?>

            <?php if(isset($_SESSION['error'])): ?>
            <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg flex items-center gap-3" id="errorMessage">
                <span class="material-symbols-outlined text-red-600">error</span>
                <p class="flex-1"><?= htmlspecialchars($_SESSION['error']) ?></p>
                <button onclick="this.parentElement.remove()" class="text-red-600 hover:text-red-800">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <?php unset($_SESSION['error']); endif; ?>

            <!-- New Lost & Found Form (Hidden by default) -->
            <div id="newListingForm" class="<?= $editItem ? '' : 'hidden' ?> bg-surface-white rounded-xl border border-slate-200 shadow-lg p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold text-brand-green"><?= $editItem ? 'Edit Item' : 'Report Lost or Found Item' ?></h2>
                    <button onclick="cancelEdit()" class="p-2 hover:bg-slate-100 rounded-lg transition-colors">
                        <span class="material-symbols-outlined text-slate-400">close</span>
                    </button>
                </div>

                <form id="lostFoundForm" class="listing-form space-y-4" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>" />
                    <?php if($editItem): ?>
                    <input type="hidden" name="item_id" value="<?= $editItem['id'] ?>" />
                    <?php endif; ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-text-dark mb-2">Type</label>
                            <div class="flex gap-4">
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="type" value="lost" required <?= ($editItem && $editItem['type'] == 'lost') ? 'checked' : '' ?> class="size-4 text-primary focus:ring-primary">
                                    <span class="font-medium text-text-dark">Lost Item</span>
                                </label>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="radio" name="type" value="found" required <?= ($editItem && $editItem['type'] == 'found') ? 'checked' : '' ?> class="size-4 text-primary focus:ring-primary">
                                    <span class="font-medium text-text-dark">Found Item</span>
                                </label>
                            </div>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-text-dark mb-2">Item Title</label>
                            <input type="text" name="title" required value="<?= $editItem ? htmlspecialchars($editItem['title']) : '' ?>" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="e.g., Black Backpack with Laptop">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-text-dark mb-2">Category</label>
                            <input type="text" name="category" required value="<?= $editItem ? htmlspecialchars($editItem['category']) : '' ?>" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="e.g., Bags, Electronics">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-text-dark mb-2">Date Lost/Found</label>
                            <input type="date" name="date_lost_found" required value="<?= $editItem ? $editItem['date_lost_found'] : '' ?>" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-text-dark mb-2">Location</label>
                            <input type="text" name="location_lost_found" required value="<?= $editItem ? htmlspecialchars($editItem['location_lost_found']) : '' ?>" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="e.g., Library, 3rd Floor">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-text-dark mb-2">Description</label>
                            <textarea name="description" required rows="3" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="Describe the item in detail..."><?= $editItem ? htmlspecialchars($editItem['description']) : '' ?></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-text-dark mb-2">Contact Info</label>
                            <input type="text" name="contact_info" required value="<?= $editItem ? htmlspecialchars($editItem['contact_info']) : '' ?>" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="Phone or email">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-text-dark mb-2">Item Image</label>
                            <input type="file" name="image" accept="image/*" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                            <?php if($editItem): ?>
                            <p class="text-xs text-slate-500 mt-1">Upload new image (optional)</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="flex gap-3 pt-4">
                        <button type="submit" name="<?= $editItem ? 'UpdateLostFound' : 'CreateLostFound' ?>" class="flex-1 px-6 py-3 bg-primary text-white rounded-lg font-bold hover:bg-primary/90 transition-colors">
                            <?= $editItem ? 'Update Item' : 'Post Item' ?>
                        </button>
                        <button type="button" onclick="cancelEdit()" class="px-6 py-3 bg-slate-100 text-slate-700 rounded-lg font-bold hover:bg-slate-200 transition-colors">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="flex flex-col gap-2 rounded-xl p-6 bg-surface-white border border-slate-200 shadow-sm">
                    <div class="flex items-center justify-between">
                        <p class="text-slate-500 text-sm font-medium">Total Items</p>
                        <span class="material-symbols-outlined text-blue-600">location_searching</span>
                    </div>
                    <p class="text-text-dark text-2xl font-bold tracking-tight"><?= $lost_found_count ?></p>
                    <p class="text-slate-400 text-xs font-normal mt-1">Reports submitted</p>
                </div>
                <div class="flex flex-col gap-2 rounded-xl p-6 bg-surface-white border border-slate-200 shadow-sm">
                    <div class="flex items-center justify-between">
                        <p class="text-slate-500 text-sm font-medium">Lost Items</p>
                        <span class="material-symbols-outlined text-red-600">search</span>
                    </div>
                    <p class="text-text-dark text-2xl font-bold tracking-tight">
                        <?php 
                        $lost_count = 0;
                        if($lost_found_count > 0) {
                            $lost_found_query->data_seek(0);
                            while($item = $lost_found_query->fetch_assoc()) {
                                if(isset($item['type']) && $item['type'] == 'lost') $lost_count++;
                            }
                        }
                        echo $lost_count;
                        ?>
                    </p>
                    <p class="text-slate-400 text-xs font-normal mt-1">Searching for</p>
                </div>
                <div class="flex flex-col gap-2 rounded-xl p-6 bg-surface-white border border-slate-200 shadow-sm">
                    <div class="flex items-center justify-between">
                        <p class="text-slate-500 text-sm font-medium">Found Items</p>
                        <span class="material-symbols-outlined text-green-600">check_circle</span>
                    </div>
                    <p class="text-text-dark text-2xl font-bold tracking-tight">
                        <?php 
                        $found_count = 0;
                        if($lost_found_count > 0) {
                            $lost_found_query->data_seek(0);
                            while($item = $lost_found_query->fetch_assoc()) {
                                if(isset($item['type']) && $item['type'] == 'found') $found_count++;
                            }
                        }
                        echo $found_count;
                        ?>
                    </p>
                    <p class="text-slate-400 text-xs font-normal mt-1">Awaiting owners</p>
                </div>
            </div>

            <!-- Lost & Found Items List -->
            <div class="bg-surface-white rounded-xl border border-slate-200 shadow-sm">
               
                    <?php if($lost_found_count > 0): ?>
                    <div class="space-y-4">
                        <?php 
                        $lost_found_query->data_seek(0);
                        while($item = $lost_found_query->fetch_assoc()): 
                        ?>
                        <div class="flex flex-col sm:flex-row gap-4 p-4 border border-slate-200 rounded-lg hover:border-primary/50 hover:shadow-md transition-all">
                            <?php if(isset($item['image_url']) && $item['image_url']): ?>
                            <div class="w-full sm:w-32 h-32 bg-slate-100 rounded-lg overflow-hidden flex-shrink-0">
                                <img src="<?= htmlspecialchars($item['image_url']) ?>" alt="<?= htmlspecialchars($item['title']) ?>" class="w-full h-full object-cover" />
                            </div>
                            <?php endif; ?>
                            <div class="flex-1">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <span class="px-2 py-0.5 text-xs font-bold rounded-full <?= $item['type'] == 'lost' ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700' ?>">
                                                <?= strtoupper($item['type']) ?>
                                            </span>
                                            <h3 class="font-bold text-text-dark text-lg"><?= htmlspecialchars($item['title']) ?></h3>
                                        </div>
                                        <p class="text-sm text-slate-500 mt-1"><?= htmlspecialchars($item['category']) ?></p>
                                    </div>
                                    <span class="px-2.5 py-1 text-xs font-medium rounded-full <?= $item['status'] == 'open' ? 'bg-blue-100 text-blue-700' : 'bg-slate-100 text-slate-700' ?>">
                                        <?= ucfirst($item['status']) ?>
                                    </span>
                                </div>
                                <p class="text-sm text-slate-600 mt-2"><?= htmlspecialchars($item['description']) ?></p>
                                <div class="flex flex-wrap items-center gap-3 mt-3 text-xs text-slate-500">
                                    <span class="flex items-center gap-1">
                                        <span class="material-symbols-outlined text-sm">location_on</span>
                                        <?= htmlspecialchars($item['location_lost_found']) ?>
                                    </span>
                                    <span class="flex items-center gap-1">
                                        <span class="material-symbols-outlined text-sm">calendar_today</span>
                                        <?= date('M d, Y', strtotime($item['date_lost_found'])) ?>
                                    </span>
                                </div>
                                <div class="flex gap-2 mt-4">
                                    <button onclick="editItem(<?= $item['id'] ?>)" class="text-sm font-medium text-brand-green hover:underline">Edit</button>
                                    <?php if($item['status'] == 'open'): ?>
                                    <button onclick="markAsClaimed(<?= $item['id'] ?>)" class="text-sm font-medium text-primary hover:underline">Mark as Claimed</button>
                                    <?php elseif($item['status'] == 'claimed'): ?>
                                    <button onclick="markAsOpen(<?= $item['id'] ?>)" class="text-sm font-medium text-emerald-600 hover:underline">Mark as Open</button>
                                    <?php endif; ?>
                                    <button onclick="deleteItem(<?= $item['id'] ?>)" class="text-sm font-medium text-red-600 hover:underline">Delete</button>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-12">
                        <span class="material-symbols-outlined text-6xl text-slate-300">location_searching</span>
                        <p class="text-slate-500 mt-4">No lost & found items reported yet</p>
                        <button class="mt-4 px-4 py-2 bg-primary text-white rounded-lg font-medium hover:bg-primary/90" onclick="toggleNewListingForm()">
                            Report Your First Item
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
    </main>

    <script>
        // Auto-dismiss messages after 5 seconds
        setTimeout(() => {
            const successMsg = document.getElementById('successMessage');
            const errorMsg = document.getElementById('errorMessage');
            if(successMsg) successMsg.remove();
            if(errorMsg) errorMsg.remove();
        }, 5000);

        // Mobile menu toggle
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        function toggleSidebar() {
            sidebar.classList.toggle('-translate-x-full');
            sidebarOverlay.classList.toggle('hidden');
            document.body.classList.toggle('overflow-hidden');
        }

        if(menuToggle) menuToggle.addEventListener('click', toggleSidebar);
        if(sidebarOverlay) sidebarOverlay.addEventListener('click', toggleSidebar);

        if(sidebar) {
            const sidebarLinks = sidebar.querySelectorAll('a');
            sidebarLinks.forEach(link => {
                link.addEventListener('click', () => {
                    if (window.innerWidth < 1024) {
                        toggleSidebar();
                    }
                });
            });
        }

        window.addEventListener('resize', () => {
            if (window.innerWidth >= 1024 && sidebar && !sidebar.classList.contains('-translate-x-full')) {
                sidebar.classList.remove('-translate-x-full');
                if(sidebarOverlay) sidebarOverlay.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }
        });

        // Toggle new listing form
        function toggleNewListingForm() {
            const form = document.getElementById('newListingForm');
            const btn = document.getElementById('toggleFormBtn');

            if (form.classList.contains('hidden')) {
                form.classList.remove('hidden');
                btn.innerHTML = '<span class="material-symbols-outlined text-xl">close</span><span>Cancel</span>';
                form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } else {
                form.classList.add('hidden');
                btn.innerHTML = '<span class="material-symbols-outlined text-xl">add</span><span>New Item</span>';
            }
        }

        // Cancel edit and return to main view
        function cancelEdit() {
            window.location.href = 'my-lost-found.php';
        }

        // Edit item
        function editItem(itemId) {
            window.location.href = 'my-lost-found.php?edit=' + itemId;
        }

        // Mark item as claimed
        function markAsClaimed(itemId) {
            if(confirm('Are you sure you want to mark this item as claimed?')) {
                fetch('my-lost-found.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'mark_as_claimed=1&item_id=' + itemId
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        location.reload();
                    } else {
                        alert(data.message || 'Failed to update item status');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while updating item status');
                });
            }
        }

        // Mark item as open
        function markAsOpen(itemId) {
            if(confirm('Are you sure you want to mark this item as open again?')) {
                fetch('my-lost-found.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'mark_as_open=1&item_id=' + itemId
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        location.reload();
                    } else {
                        alert(data.message || 'Failed to update item status');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while updating item status');
                });
            }
        }

        // Delete item
        function deleteItem(itemId) {
            if(confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                fetch('my-lost-found.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'delete_item=1&item_id=' + itemId
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        location.reload();
                    } else {
                        alert(data.message || 'Failed to delete item');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting item');
                });
            }
        }
    </script>
</body>

</html>
