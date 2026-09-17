<?php session_start();
include_once 'includes/controller.php';

$userId = $_SESSION['userAppId'] ?? 0;

// Handle Delete Service
if(isset($_POST['delete_service']) && isset($_POST['service_id'])) {
    header('Content-Type: application/json');
    $service_id = (int)$_POST['service_id'];
    
    // Verify ownership
    $check = $db->query("SELECT id FROM services WHERE id = '$service_id' AND user_id = '$userId'");
    if($check->num_rows > 0) {
        $delete = $db->query("DELETE FROM services WHERE id = '$service_id'");
        if($delete) {
            echo json_encode(['success' => true, 'message' => 'Service deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to delete service']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Service not found or access denied']);
    }
    exit;
}

// Handle Toggle Service Availability (Activate/Deactivate)
if(isset($_POST['toggle_service']) && isset($_POST['service_id'])) {
    header('Content-Type: application/json');
    $service_id = (int)$_POST['service_id'];
    
    // Verify ownership and get current status
    $check = $db->query("SELECT id, availability FROM services WHERE id = '$service_id' AND user_id = '$userId'");
    if($check->num_rows > 0) {
        $service = $check->fetch_assoc();
        // Toggle between available and unavailable
        $new_status = ($service['availability'] === 'available') ? 'unavailable' : 'available';
        $action = ($new_status === 'available') ? 'activated' : 'deactivated';
        
        $update = $db->query("UPDATE services SET availability = '$new_status' WHERE id = '$service_id'");
        if($update) {
            echo json_encode([
                'success' => true, 
                'message' => 'Service ' . $action . ' successfully',
                'new_status' => $new_status,
                'action' => $action
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to update service status']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Service not found or access denied']);
    }
    exit;
}

// Handle Edit Service - Load service data if edit parameter is present
$editService = null;
if(isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $edit_query = $db->query("SELECT s.* FROM services s WHERE s.id = '$edit_id' AND s.user_id = '$userId'");
    if($edit_query->num_rows > 0) {
        $editService = $edit_query->fetch_assoc();
    }
}

// Handle Update Service
if(isset($_POST['UpdateService'])) {
    $service_id = (int)$_POST['service_id'];
    $title = $db->real_escape_string($_POST['title']);
    $service_category_id = (int)$_POST['service_category_id'];
    $price = (float)$_POST['price'];
    $pricing_type = $db->real_escape_string($_POST['pricing_type']);
    $delivery_time = $db->real_escape_string($_POST['delivery_time']);
    $short_description = $db->real_escape_string($_POST['short_description']);
    $description = $db->real_escape_string($_POST['description']);
    
    // Verify ownership and get current service data
    $check = $db->query("SELECT id, portfolio_images FROM services WHERE id = '$service_id' AND user_id = '$userId'");
    if($check->num_rows > 0) {
        $current_service = $check->fetch_assoc();
        $existing_images = json_decode($current_service['portfolio_images'], true) ?: [];
        
        // Handle portfolio image uploads
        $portfolio_images = $existing_images; // Start with existing images
        
        if(isset($_FILES['portfolio_images']) && !empty($_FILES['portfolio_images']['name'][0])) {
            $upload_dir = 'uploads/services/';
            
            // Create directory if it doesn't exist
            if(!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
            $max_file_size = 5 * 1024 * 1024; // 5MB
            $new_images = [];
            
            foreach($_FILES['portfolio_images']['tmp_name'] as $key => $tmp_name) {
                if(empty($tmp_name)) continue;
                
                $file_name = $_FILES['portfolio_images']['name'][$key];
                $file_size = $_FILES['portfolio_images']['size'][$key];
                $file_type = $_FILES['portfolio_images']['type'][$key];
                $file_error = $_FILES['portfolio_images']['error'][$key];
                
                // Validate file
                if($file_error !== UPLOAD_ERR_OK) continue;
                if(!in_array($file_type, $allowed_types)) continue;
                if($file_size > $max_file_size) continue;
                
                // Generate unique filename
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $unique_name = 'service_' . $service_id . '_' . uniqid() . '.' . $file_ext;
                $target_path = $upload_dir . $unique_name;
                
                // Move uploaded file
                if(move_uploaded_file($tmp_name, $target_path)) {
                    $new_images[] = $target_path;
                }
                
                // Limit to 5 total images
                if(count($portfolio_images) + count($new_images) >= 5) break;
            }
            
            // Merge new images with existing (new images replace if checkbox was checked)
            if(!empty($new_images)) {
                // If user wants to replace all images, clear existing first
                if(isset($_POST['replace_images']) && $_POST['replace_images'] == '1') {
                    $portfolio_images = $new_images;
                } else {
                    // Add new images to existing
                    $portfolio_images = array_merge($existing_images, $new_images);
                    $portfolio_images = array_slice($portfolio_images, 0, 5); // Keep only 5
                }
            }
        }
        
        $portfolio_json = json_encode($portfolio_images);
        $portfolio_json_escaped = $db->real_escape_string($portfolio_json);
        
        $update = $db->query("UPDATE services SET 
            title = '$title',
            service_category_id = '$service_category_id',
            price = '$price',
            pricing_type = '$pricing_type',
            delivery_time = '$delivery_time',
            short_description = '$short_description',
            description = '$description',
            portfolio_images = '$portfolio_json_escaped'
            WHERE id = '$service_id'");
        
        if($update) {
            include_once 'includes/ai/search.php';
            ai_index_service((int) $service_id);
            $_SESSION['success'] = 'Service updated successfully!';
            header('Location: my-services.php');
            exit;
        } else {
            $_SESSION['error'] = 'Failed to update service';
        }
    } else {
        $_SESSION['error'] = 'Service not found or access denied';
    }
}

$services_query = $db->query("SELECT s.*, sc.name as category_name 
    FROM services s 
    LEFT JOIN service_categories sc ON s.service_category_id = sc.id 
    WHERE s.user_id = '$userId' 
    ORDER BY s.created_at DESC");

$services_count = $services_query->num_rows;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <base href="<?php echo SITE_URL; ?>">
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>My Services | CampMart</title>
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
                    <h1 class="text-3xl font-bold tracking-tight text-brand-green">My Services</h1>
                    <p class="text-slate-500 mt-1">Manage your service offerings and keep everything organized.</p>
                </div>
                <button onclick="toggleNewListingForm()" id="toggleFormBtn" class="flex items-center gap-2 px-4 py-2.5 bg-primary text-white rounded-lg font-medium hover:bg-primary/90 transition-colors">
                    <span class="material-symbols-outlined text-xl">add</span>
                    <span>New Service</span>
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

            <!-- New Service Form (Hidden by default) -->
            <div id="newListingForm" class="<?= $editService ? '' : 'hidden' ?> bg-surface-white rounded-xl border border-slate-200 shadow-lg p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold text-brand-green"><?= $editService ? 'Edit Service' : 'Create New Service Listing' ?></h2>
                    <button onclick="cancelEdit()" class="p-2 hover:bg-slate-100 rounded-lg transition-colors">
                        <span class="material-symbols-outlined text-slate-400">close</span>
                    </button>
                </div>

                <form id="serviceForm" class="listing-form space-y-4" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>" />
                    <?php if($editService): ?>
                    <input type="hidden" name="service_id" value="<?= $editService['id'] ?>" />
                    <?php endif; ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-text-dark mb-2">Service Title</label>
                            <input type="text" name="title" required value="<?= $editService ? htmlspecialchars($editService['title']) : '' ?>" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="e.g., Professional Photography Services">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-text-dark mb-2">Category</label>
                            <select name="service_category_id" required class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                                <option value="">Select category</option>
                                <?php 
                                $categories = $db->query("SELECT * FROM service_categories WHERE is_active = 1 ORDER BY name");
                                while($cat = $categories->fetch_assoc()): 
                                ?>
                                <option value="<?= $cat['id'] ?>" <?= ($editService && $editService['service_category_id'] == $cat['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-text-dark mb-2">Pricing Type</label>
                            <select name="pricing_type" required class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                                <option value="fixed" <?= ($editService && $editService['pricing_type'] == 'fixed') ? 'selected' : '' ?>>Fixed Price</option>
                                <option value="hourly" <?= ($editService && $editService['pricing_type'] == 'hourly') ? 'selected' : '' ?>>Hourly Rate</option>
                                <option value="daily" <?= ($editService && $editService['pricing_type'] == 'daily') ? 'selected' : '' ?>>Daily Rate</option>
                                <option value="project" <?= ($editService && $editService['pricing_type'] == 'project') ? 'selected' : '' ?>>Per Project</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-text-dark mb-2">Price (₦)</label>
                            <input type="number" name="price" required step="0.01" min="0" value="<?= $editService ? $editService['price'] : '' ?>" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="0.00">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-text-dark mb-2">Delivery Time</label>
                            <input type="text" name="delivery_time" value="<?= $editService ? htmlspecialchars($editService['delivery_time']) : '' ?>" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="e.g., 2-3 days">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-text-dark mb-2">Short Description</label>
                            <input type="text" name="short_description" maxlength="500" value="<?= $editService ? htmlspecialchars($editService['short_description']) : '' ?>" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="Brief summary of your service">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-text-dark mb-2">Full Description</label>
                            <textarea name="description" required rows="4" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="Describe your service, what you offer, and your experience..."><?= $editService ? htmlspecialchars($editService['description']) : '' ?></textarea>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-text-dark mb-2">Portfolio Images</label>
                            
                            <?php if($editService && !empty($editService['portfolio_images'])): 
                                $current_images = json_decode($editService['portfolio_images'], true);
                                if(!empty($current_images) && is_array($current_images)):
                            ?>
                            <div class="mb-4">
                                <p class="text-sm text-slate-600 mb-2">Current Images (<?= count($current_images) ?>):</p>
                                <div class="grid grid-cols-3 md:grid-cols-5 gap-3">
                                    <?php foreach($current_images as $img): ?>
                                    <div class="relative group aspect-square rounded-lg overflow-hidden border-2 border-slate-200">
                                        <img src="<?= htmlspecialchars($img) ?>" alt="Portfolio image" class="w-full h-full object-cover">
                                        <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                            <span class="material-symbols-outlined text-white text-sm">image</span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer">
                                    <input type="checkbox" name="replace_images" value="1" class="rounded border-slate-300 text-primary focus:ring-primary">
                                    <span>Replace all existing images with new uploads</span>
                                </label>
                                <p class="text-xs text-slate-500 mt-1 ml-6">If unchecked, new images will be added to existing ones (max 5 total)</p>
                            </div>
                            <?php endif; endif; ?>
                            
                            <input type="file" name="portfolio_images[]" multiple accept="image/*" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary/10 file:text-primary hover:file:bg-primary/20">
                            <p class="text-xs text-slate-500 mt-1"><?= $editService ? 'Upload new images (optional)' : 'Upload up to 5 images to showcase your work (Max 5MB each)' ?></p>
                        </div>
                    </div>
                    <div class="flex gap-3 pt-4">
                        <button type="submit" name="<?= $editService ? 'UpdateService' : 'CreateService' ?>" class="flex-1 px-6 py-3 bg-primary text-white rounded-lg font-bold hover:bg-primary/90 transition-colors">
                            <?= $editService ? 'Update Service' : 'Create Service' ?>
                        </button>
                        <button type="button" onclick="cancelEdit()" class="px-6 py-3 bg-slate-100 text-slate-700 rounded-lg font-bold hover:bg-slate-200 transition-colors">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="flex flex-col gap-2 rounded-xl p-6 bg-surface-white border border-slate-200 shadow-sm">
                    <div class="flex items-center justify-between">
                        <p class="text-slate-500 text-sm font-medium">Services</p>
                        <span class="material-symbols-outlined text-primary">work</span>
                    </div>
                    <p class="text-text-dark text-2xl font-bold tracking-tight"><?= $services_count ?></p>
                    <p class="text-slate-400 text-xs font-normal mt-1">Active offerings</p>
                </div>
                <div class="flex flex-col gap-2 rounded-xl p-6 bg-surface-white border border-slate-200 shadow-sm">
                    <div class="flex items-center justify-between">
                        <p class="text-slate-500 text-sm font-medium">Total Views</p>
                        <span class="material-symbols-outlined text-purple-600">visibility</span>
                    </div>
                    <p class="text-text-dark text-2xl font-bold tracking-tight">
                        <?php 
                        $total_views = 0;
                        $services_query->data_seek(0);
                        while($s = $services_query->fetch_assoc()) {
                            $total_views += $s['views_count'];
                        }
                        echo number_format($total_views);
                        ?>
                    </p>
                    <p class="text-slate-400 text-xs font-normal mt-1">Across all services</p>
                </div>
            </div>

            <!-- Services List -->
            <div class="bg-surface-white rounded-xl border border-slate-200 shadow-sm">

                    <?php if($services_count > 0): ?>
                    <div class="space-y-4">
                        <?php 
                        $services_query->data_seek(0);
                        while($service = $services_query->fetch_assoc()): 
                        ?>
                        <div class="flex flex-col sm:flex-row gap-4 p-4 border border-slate-200 rounded-lg hover:border-primary/50 hover:shadow-md transition-all">
                            <?php 
                            $portfolio_images = json_decode($service['portfolio_images'], true);
                            $first_image = !empty($portfolio_images) ? $portfolio_images[0] : null;
                            ?>
                            <?php if($first_image): ?>
                            <div class="w-full sm:w-32 h-32 flex-shrink-0 rounded-lg overflow-hidden bg-slate-100">
                                <img src="<?= htmlspecialchars($first_image) ?>" alt="<?= htmlspecialchars($service['title']) ?>" class="w-full h-full object-cover">
                            </div>
                            <?php else: ?>
                            <div class="w-full sm:w-32 h-32 flex-shrink-0 rounded-lg bg-slate-100 flex items-center justify-center">
                                <span class="material-symbols-outlined text-5xl text-slate-300">image</span>
                            </div>
                            <?php endif; ?>
                            <div class="flex-1">
                                <div class="flex items-start justify-between gap-4">
                                    <div>
                                        <h3 class="font-bold text-text-dark text-lg"><?= htmlspecialchars($service['title']) ?></h3>
                                        <p class="text-sm text-slate-500 mt-1"><?= htmlspecialchars($service['category_name']) ?></p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-xl font-bold text-brand-green">₦<?= number_format($service['price'], 2) ?></p>
                                        <p class="text-xs text-slate-500"><?= ucfirst($service['pricing_type']) ?></p>
                                    </div>
                                </div>
                                <p class="text-sm text-slate-600 mt-2 line-clamp-2"><?= htmlspecialchars($service['short_description'] ?? substr($service['description'], 0, 150)) ?></p>
                                <div class="flex flex-wrap items-center gap-3 mt-3">
                                    <span class="px-2.5 py-1 text-xs font-medium rounded-full <?= $service['availability'] == 'available' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-700' ?>">
                                        <?= ucfirst($service['availability']) ?>
                                    </span>
                                    <span class="text-xs text-slate-500 flex items-center gap-1">
                                        <span class="material-symbols-outlined text-sm">star</span>
                                        <?= number_format($service['rating'], 1) ?> (<?= $service['total_ratings'] ?>)
                                    </span>
                                    <span class="text-xs text-slate-500 flex items-center gap-1">
                                        <span class="material-symbols-outlined text-sm">shopping_cart</span>
                                        <?= number_format($service['total_orders']) ?> orders
                                    </span>
                                </div>
                                <div class="flex gap-2 mt-4">
                                    <button onclick="editService(<?= $service['id'] ?>)" class="text-sm font-medium text-brand-green hover:underline">Edit</button>
                                    <button onclick="viewService('<?= $service['slug'] ?>')" class="text-sm font-medium text-primary hover:underline">View</button>
                                    <button onclick="toggleService(<?= $service['id'] ?>, '<?= $service['availability'] ?>')" class="text-sm font-medium <?= $service['availability'] == 'available' ? 'text-orange-600' : 'text-emerald-600' ?> hover:underline" id="toggle-btn-<?= $service['id'] ?>">
                                        <?= $service['availability'] == 'available' ? 'Deactivate' : 'Activate' ?>
                                    </button>
                                    <button onclick="deleteService(<?= $service['id'] ?>)" class="text-sm font-medium text-red-600 hover:underline">Delete</button>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center py-12">
                        <span class="material-symbols-outlined text-6xl text-slate-300">work</span>
                        <p class="text-slate-500 mt-4">No services listed yet</p>
                        <button class="mt-4 px-4 py-2 bg-primary text-white rounded-lg font-medium hover:bg-primary/90" onclick="toggleNewListingForm()">
                            Create Your First Service
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

        // Form validation
        function validateServiceForm(form) {
            const price = parseFloat(form.price.value);

            if(price <= 0) {
                alert('Price must be greater than 0');
                return false;
            }

            return true;
        }

        document.getElementById('serviceForm').addEventListener('submit', function(e) {
            if(!validateServiceForm(this)) {
                e.preventDefault();
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
                btn.innerHTML = '<span class="material-symbols-outlined text-xl">add</span><span>New Service</span>';
            }
        }

        // Cancel edit and return to main view
        function cancelEdit() {
            window.location.href = 'my-services.php';
        }

        // View service details
        function viewService(slug) {
            window.location.href = '<?php echo SITE_URL; ?>service/' + slug;
        }

        // Edit service
        function editService(serviceId) {
            window.location.href = 'my-services.php?edit=' + serviceId;
        }

        // Toggle service availability (Activate/Deactivate)
        function toggleService(serviceId, currentStatus) {
            const action = currentStatus === 'available' ? 'deactivate' : 'activate';
            const confirmMsg = currentStatus === 'available' 
                ? 'Are you sure you want to deactivate this service? It will no longer be visible to customers.' 
                : 'Are you sure you want to activate this service? It will be visible to customers.';
            
            if(confirm(confirmMsg)) {
                const button = document.getElementById('toggle-btn-' + serviceId);
                const originalText = button.textContent;
                button.textContent = 'Processing...';
                button.disabled = true;
                
                fetch('my-services.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'toggle_service=1&service_id=' + serviceId
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        // Show success message
                        const successDiv = document.createElement('div');
                        successDiv.className = 'bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg flex items-center gap-3 mb-6';
                        successDiv.innerHTML = `
                            <span class="material-symbols-outlined text-emerald-600">check_circle</span>
                            <p class="flex-1">${data.message}</p>
                            <button onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-800">
                                <span class="material-symbols-outlined">close</span>
                            </button>
                        `;
                        document.querySelector('.max-w-7xl').insertBefore(successDiv, document.querySelector('.max-w-7xl').firstChild);
                        
                        // Reload page after short delay to show updated status
                        setTimeout(() => {
                            location.reload();
                        }, 1000);
                    } else {
                        alert(data.message || 'Failed to toggle service status');
                        button.textContent = originalText;
                        button.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while updating service status');
                    button.textContent = originalText;
                    button.disabled = false;
                });
            }
        }

        // Delete service permanently
        function deleteService(serviceId) {
            if(confirm('Are you sure you want to permanently delete this service? This action cannot be undone and all service data will be lost.')) {
                // Double confirmation for delete
                if(confirm('This will PERMANENTLY delete the service. Are you absolutely sure?')) {
                    fetch('my-services.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'delete_service=1&service_id=' + serviceId
                    })
                    .then(response => response.json())
                    .then(data => {
                        if(data.success) {
                            // Show success message
                            const successDiv = document.createElement('div');
                            successDiv.className = 'bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg flex items-center gap-3 mb-6';
                            successDiv.innerHTML = `
                                <span class="material-symbols-outlined text-emerald-600">check_circle</span>
                                <p class="flex-1">${data.message}</p>
                                <button onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-800">
                                    <span class="material-symbols-outlined">close</span>
                                </button>
                            `;
                            document.querySelector('.max-w-7xl').insertBefore(successDiv, document.querySelector('.max-w-7xl').firstChild);
                            
                            // Reload page after short delay
                            setTimeout(() => {
                                location.reload();
                            }, 1000);
                        } else {
                            alert(data.message || 'Failed to delete service');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while deleting service');
                    });
                }
            }
        }
    </script>
</body>

</html>
