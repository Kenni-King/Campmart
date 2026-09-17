<?php session_start(); 
include_once 'includes/controller.php';

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>My Listings | CampMart</title>
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
        
        .tab-button.active {
            border-color: #f48c25;
            color: #f48c25;
        }
    </style>
</head>

<body class="bg-background-main min-h-screen text-text-dark">
<?php include_once 'includes/user-nav.php';


// Fetch products
$products_query = $db->query("SELECT p.*, c.name as category_name, 
    (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image
    FROM products p 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE p.user_id = '$userId' 
    ORDER BY p.created_at DESC");

// Fetch services
$services_query = $db->query("SELECT s.*, sc.name as category_name 
    FROM services s 
    LEFT JOIN service_categories sc ON s.service_category_id = sc.id 
    WHERE s.user_id = '$userId' 
    ORDER BY s.created_at DESC");

// Fetch lost & found items
$lost_found_query = $db->query("SELECT * FROM lost_found_items 
    WHERE user_id = '$userId' 
    ORDER BY created_at DESC");

// Get counts
$products_count = $products_query->num_rows;
$services_count = $services_query->num_rows;
$lost_found_count = $lost_found_query->num_rows;

?>
        <main class="flex-1 overflow-y-auto bg-background-main p-4 md:p-6 lg:p-8">
            <div class="max-w-7xl mx-auto space-y-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold tracking-tight text-brand-green">My Listings</h1>
                        <p class="text-slate-500 mt-1">Manage your products, services, and lost & found items.</p>
                    </div>
                    <button onclick="toggleNewListingForm()" id="toggleFormBtn" class="flex items-center gap-2 px-4 py-2.5 bg-primary text-white rounded-lg font-medium hover:bg-primary/90 transition-colors">
                        <span class="material-symbols-outlined text-xl">add</span>
                        <span>New Listing</span>
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

                <!-- New Listing Form (Hidden by default) -->
                <div id="newListingForm" class="hidden bg-surface-white rounded-xl border border-slate-200 shadow-lg p-6">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-brand-green">Create New Listing</h2>
                        <button onclick="toggleNewListingForm()" class="p-2 hover:bg-slate-100 rounded-lg transition-colors">
                            <span class="material-symbols-outlined text-slate-400">close</span>
                        </button>
                    </div>

                    <!-- Listing Type Selection -->
                    <div class="mb-6">
                        <label class="block text-sm font-bold text-text-dark mb-3">Select Listing Type</label>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                            <button onclick="selectListingType('product')" class="listing-type-btn active p-4 border-2 border-primary bg-primary/5 rounded-lg hover:border-primary hover:bg-primary/10 transition-all">
                                <span class="material-symbols-outlined text-2xl text-primary block mb-2">shopping_bag</span>
                                <span class="font-bold text-text-dark">Product</span>
                            </button>
                            <button onclick="selectListingType('service')" class="listing-type-btn p-4 border-2 border-slate-200 rounded-lg hover:border-primary hover:bg-primary/10 transition-all">
                                <span class="material-symbols-outlined text-2xl text-brand-green block mb-2">work</span>
                                <span class="font-bold text-text-dark">Service</span>
                            </button>
                            <button onclick="selectListingType('lost-found')" class="listing-type-btn p-4 border-2 border-slate-200 rounded-lg hover:border-primary hover:bg-primary/10 transition-all">
                                <span class="material-symbols-outlined text-2xl text-blue-600 block mb-2">location_searching</span>
                                <span class="font-bold text-text-dark">Lost & Found</span>
                            </button>
                        </div>
                    </div>

                    <!-- Product Form -->
                    <form id="productForm" class="listing-form space-y-4" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>" />
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-bold text-text-dark mb-2">Product Title</label>
                                <input type="text" name="title" required class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="e.g., iPhone 13 Pro Max 256GB">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-text-dark mb-2">Category</label>
                                <select name="category_id" required class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                                    <option value="">Select category</option>
                                    <?php 
                                    $categories = $db->query("SELECT * FROM categories WHERE is_active = 1 AND parent_id IS NULL ORDER BY name");
                                    while($cat = $categories->fetch_assoc()): 
                                    ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-text-dark mb-2">Price (₦)</label>
                                <input type="number" name="price" required step="0.01" min="0" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="0.00">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-text-dark mb-2">Original Price (Optional)</label>
                                <input type="number" name="original_price" step="0.01" min="0" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="0.00">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-text-dark mb-2">Condition</label>
                                <select name="condition_type" required class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                                    <option value="new">New</option>
                                    <option value="like_new">Like New</option>
                                    <option value="good">Good</option>
                                    <option value="fair">Fair</option>
                                    <option value="for_parts">For Parts</option>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-bold text-text-dark mb-2">Description</label>
                                <textarea name="description" required rows="4" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="Describe your product in detail..."></textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-text-dark mb-2">Location</label>
                                <input type="text" name="location" required class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="e.g., Main Campus, Hostel A">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-text-dark mb-2">Product Images</label>
                                <input type="file" name="images[]" multiple accept="image/*" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                                <p class="text-xs text-slate-500 mt-1">Upload up to 5 images</p>
                            </div>
                            <div class="md:col-span-2">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" name="negotiable" value="1" checked class="size-4 rounded border-slate-300 text-primary focus:ring-primary">
                                    <span class="text-sm font-medium text-slate-700">Price is negotiable</span>
                                </label>
                            </div>
                        </div>
                        <div class="flex gap-3 pt-4">
                            <button type="submit" name="CreateProduct" class="flex-1 px-6 py-3 bg-primary text-white rounded-lg font-bold hover:bg-primary/90 transition-colors">
                                Create Product
                            </button>
                            <button type="button" onclick="toggleNewListingForm()" class="px-6 py-3 bg-slate-100 text-slate-700 rounded-lg font-bold hover:bg-slate-200 transition-colors">
                                Cancel
                            </button>
                        </div>
                    </form>

                    <!-- Service Form -->
                    <form id="serviceForm" class="listing-form hidden space-y-4" method="POST">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>" />
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-bold text-text-dark mb-2">Service Title</label>
                                <input type="text" name="title" required class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="e.g., Professional Photo Editing">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-text-dark mb-2">Category</label>
                                <select name="service_category_id" required class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                                    <option value="">Select category</option>
                                    <?php 
                                    $service_cats = $db->query("SELECT * FROM service_categories WHERE is_active = 1 ORDER BY name");
                                    while($scat = $service_cats->fetch_assoc()): 
                                    ?>
                                    <option value="<?= $scat['id'] ?>"><?= htmlspecialchars($scat['name']) ?></option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-text-dark mb-2">Pricing Type</label>
                                <select name="pricing_type" required class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                                    <option value="fixed">Fixed Price</option>
                                    <option value="hourly">Hourly Rate</option>
                                    <option value="daily">Daily Rate</option>
                                    <option value="project">Per Project</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-text-dark mb-2">Price (₦)</label>
                                <input type="number" name="price" required step="0.01" min="0" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="0.00">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-text-dark mb-2">Delivery Time</label>
                                <input type="text" name="delivery_time" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="e.g., 2-3 days">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-bold text-text-dark mb-2">Short Description</label>
                                <input type="text" name="short_description" maxlength="500" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="Brief summary of your service">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-bold text-text-dark mb-2">Full Description</label>
                                <textarea name="description" required rows="4" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="Describe your service, what you offer, and your experience..."></textarea>
                            </div>
                        </div>
                        <div class="flex gap-3 pt-4">
                            <button type="submit" name="CreateService" class="flex-1 px-6 py-3 bg-primary text-white rounded-lg font-bold hover:bg-primary/90 transition-colors">
                                Create Service
                            </button>
                            <button type="button" onclick="toggleNewListingForm()" class="px-6 py-3 bg-slate-100 text-slate-700 rounded-lg font-bold hover:bg-slate-200 transition-colors">
                                Cancel
                            </button>
                        </div>
                    </form>

                    <!-- Lost & Found Form -->
                    <form id="lostFoundForm" class="listing-form hidden space-y-4" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>" />
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="md:col-span-2">
                                <label class="block text-sm font-bold text-text-dark mb-2">Type</label>
                                <div class="flex gap-4">
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" name="type" value="lost" required class="size-4 text-primary focus:ring-primary">
                                        <span class="font-medium text-text-dark">Lost Item</span>
                                    </label>
                                    <label class="flex items-center gap-2 cursor-pointer">
                                        <input type="radio" name="type" value="found" required class="size-4 text-primary focus:ring-primary">
                                        <span class="font-medium text-text-dark">Found Item</span>
                                    </label>
                                </div>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-bold text-text-dark mb-2">Item Title</label>
                                <input type="text" name="title" required class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="e.g., Black Backpack with Laptop">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-text-dark mb-2">Category</label>
                                <input type="text" name="category" required class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="e.g., Bags, Electronics">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-text-dark mb-2">Date Lost/Found</label>
                                <input type="date" name="date_lost_found" required class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-bold text-text-dark mb-2">Location</label>
                                <input type="text" name="location_lost_found" required class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="e.g., Library, 3rd Floor">
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-bold text-text-dark mb-2">Description</label>
                                <textarea name="description" required rows="3" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="Describe the item in detail..."></textarea>
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-text-dark mb-2">Contact Info</label>
                                <input type="text" name="contact_info" required class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="Phone or email">
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-text-dark mb-2">Item Image</label>
                                <input type="file" name="image" accept="image/*" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                            </div>
                        </div>
                        <div class="flex gap-3 pt-4">
                            <button type="submit" name="CreateLostFound" class="flex-1 px-6 py-3 bg-primary text-white rounded-lg font-bold hover:bg-primary/90 transition-colors">
                                Post Item
                            </button>
                            <button type="button" onclick="toggleNewListingForm()" class="px-6 py-3 bg-slate-100 text-slate-700 rounded-lg font-bold hover:bg-slate-200 transition-colors">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Statistics Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                    <div class="flex flex-col gap-2 rounded-xl p-6 bg-surface-white border border-slate-200 shadow-sm">
                        <div class="flex items-center justify-between">
                            <p class="text-slate-500 text-sm font-medium">Products</p>
                            <span class="material-symbols-outlined text-primary">shopping_bag</span>
                        </div>
                        <p class="text-text-dark text-2xl font-bold tracking-tight"><?= $products_count ?></p>
                        <p class="text-slate-400 text-xs font-normal mt-1">Active listings</p>
                    </div>
                    <div class="flex flex-col gap-2 rounded-xl p-6 bg-surface-white border border-slate-200 shadow-sm">
                        <div class="flex items-center justify-between">
                            <p class="text-slate-500 text-sm font-medium">Services</p>
                            <span class="material-symbols-outlined text-brand-green">work</span>
                        </div>
                        <p class="text-text-dark text-2xl font-bold tracking-tight"><?= $services_count ?></p>
                        <p class="text-slate-400 text-xs font-normal mt-1">Offered services</p>
                    </div>
                    <div class="flex flex-col gap-2 rounded-xl p-6 bg-surface-white border border-slate-200 shadow-sm">
                        <div class="flex items-center justify-between">
                            <p class="text-slate-500 text-sm font-medium">Lost & Found</p>
                            <span class="material-symbols-outlined text-blue-600">location_searching</span>
                        </div>
                        <p class="text-text-dark text-2xl font-bold tracking-tight"><?= $lost_found_count ?></p>
                        <p class="text-slate-400 text-xs font-normal mt-1">Open items</p>
                    </div>
                    <div class="flex flex-col gap-2 rounded-xl p-6 bg-surface-white border border-slate-200 shadow-sm">
                        <div class="flex items-center justify-between">
                            <p class="text-slate-500 text-sm font-medium">Total Views</p>
                            <span class="material-symbols-outlined text-purple-600">visibility</span>
                        </div>
                        <p class="text-text-dark text-2xl font-bold tracking-tight">
                            <?php 
                            $total_views = 0;
                            $products_query->data_seek(0);
                            while($p = $products_query->fetch_assoc()) {
                                $total_views += $p['views_count'];
                            }
                            echo number_format($total_views);
                            ?>
                        </p>
                        <p class="text-slate-400 text-xs font-normal mt-1">Across all listings</p>
                    </div>
                </div>

                <!-- Tabs -->
                <div class="bg-surface-white rounded-xl border border-slate-200 shadow-sm">
                    <div class="border-b border-slate-200 px-6 py-4">
                        <div class="flex gap-2 overflow-x-auto">
                            <button onclick="switchTab('products')" class="tab-button active px-4 py-2 text-sm font-medium border-b-2 border-transparent hover:text-primary transition-colors whitespace-nowrap">
                                Products (<?= $products_count ?>)
                            </button>
                            <button onclick="switchTab('services')" class="tab-button px-4 py-2 text-sm font-medium border-b-2 border-transparent hover:text-primary transition-colors whitespace-nowrap">
                                Services (<?= $services_count ?>)
                            </button>
                            <button onclick="switchTab('lost-found')" class="tab-button px-4 py-2 text-sm font-medium border-b-2 border-transparent hover:text-primary transition-colors whitespace-nowrap">
                                Lost & Found (<?= $lost_found_count ?>)
                            </button>
                        </div>
                    </div>

                    <!-- Products Tab -->
                    <div id="products-tab" class="tab-content p-6">
                        <?php if($products_count > 0): ?>
                        <div class="space-y-4">
                            <?php 
                            $products_query->data_seek(0);
                            while($product = $products_query->fetch_assoc()): 
                            ?>
                            <div class="flex flex-col sm:flex-row gap-4 p-4 border border-slate-200 rounded-lg hover:border-primary/50 hover:shadow-md transition-all">
                                <div class="w-full sm:w-32 h-32 bg-slate-100 rounded-lg overflow-hidden flex-shrink-0">
                                    <?php if($product['primary_image']): ?>
                                    <img src="<?= htmlspecialchars($product['primary_image']) ?>" alt="<?= htmlspecialchars($product['title']) ?>" class="w-full h-full object-cover" />
                                    <?php else: ?>
                                    <div class="w-full h-full flex items-center justify-center text-slate-400">
                                        <span class="material-symbols-outlined text-4xl">image</span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="flex-1">
                                    <div class="flex items-start justify-between gap-4">
                                        <div>
                                            <h3 class="font-bold text-text-dark text-lg"><?= htmlspecialchars($product['title']) ?></h3>
                                            <p class="text-sm text-slate-500 mt-1"><?= htmlspecialchars($product['category_name']) ?></p>
                                        </div>
                                        <div class="text-right">
                                            <p class="text-xl font-bold text-brand-green">₦<?= number_format($product['price'], 2) ?></p>
                                            <?php if($product['original_price']): ?>
                                            <p class="text-sm text-slate-400 line-through">₦<?= number_format($product['original_price'], 2) ?></p>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-3 mt-3">
                                        <span class="px-2.5 py-1 text-xs font-medium rounded-full <?= $product['availability'] == 'available' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-700' ?>">
                                            <?= ucfirst($product['availability']) ?>
                                        </span>
                                        <span class="px-2.5 py-1 text-xs font-medium rounded-full <?= $product['status'] == 'approved' ? 'bg-blue-100 text-blue-700' : 'bg-yellow-100 text-yellow-700' ?>">
                                            <?= ucfirst($product['status']) ?>
                                        </span>
                                        <span class="text-xs text-slate-500 flex items-center gap-1">
                                            <span class="material-symbols-outlined text-sm">visibility</span>
                                            <?= number_format($product['views_count']) ?> views
                                        </span>
                                        <span class="text-xs text-slate-500 flex items-center gap-1">
                                            <span class="material-symbols-outlined text-sm">bookmark</span>
                                            <?= number_format($product['bookmarks_count']) ?> saved
                                        </span>
                                    </div>
                                    <div class="flex gap-2 mt-4">
                                        <button class="text-sm font-medium text-brand-green hover:underline">Edit</button>
                                        <button class="text-sm font-medium text-primary hover:underline">View</button>
                                        <?php if($product['availability'] == 'available'): ?>
                                        <button class="text-sm font-medium text-slate-600 hover:underline">Mark as Sold</button>
                                        <?php endif; ?>
                                        <button class="text-sm font-medium text-red-600 hover:underline">Delete</button>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-12">
                            <span class="material-symbols-outlined text-6xl text-slate-300">inventory_2</span>
                            <p class="text-slate-500 mt-4">No products listed yet</p>
                            <button class="mt-4 px-4 py-2 bg-primary text-white rounded-lg font-medium hover:bg-primary/90">
                                Create Your First Product
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Services Tab -->
                    <div id="services-tab" class="tab-content hidden p-6">
                        <?php if($services_count > 0): ?>
                        <div class="space-y-4">
                            <?php 
                            $services_query->data_seek(0);
                            while($service = $services_query->fetch_assoc()): 
                            ?>
                            <div class="flex flex-col sm:flex-row gap-4 p-4 border border-slate-200 rounded-lg hover:border-primary/50 hover:shadow-md transition-all">
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
                                        <button class="text-sm font-medium text-brand-green hover:underline">Edit</button>
                                        <button class="text-sm font-medium text-primary hover:underline">View</button>
                                        <button class="text-sm font-medium text-red-600 hover:underline">Delete</button>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-12">
                            <span class="material-symbols-outlined text-6xl text-slate-300">work</span>
                            <p class="text-slate-500 mt-4">No services listed yet</p>
                            <button class="mt-4 px-4 py-2 bg-primary text-white rounded-lg font-medium hover:bg-primary/90">
                                Create Your First Service
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Lost & Found Tab -->
                    <div id="lost-found-tab" class="tab-content hidden p-6">
                        <?php if($lost_found_count > 0): ?>
                        <div class="space-y-4">
                            <?php while($item = $lost_found_query->fetch_assoc()): ?>
                            <div class="flex flex-col sm:flex-row gap-4 p-4 border border-slate-200 rounded-lg hover:border-primary/50 hover:shadow-md transition-all">
                                <?php if($item['image_url']): ?>
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
                                        <button class="text-sm font-medium text-brand-green hover:underline">Edit</button>
                                        <?php if($item['status'] == 'open'): ?>
                                        <button class="text-sm font-medium text-primary hover:underline">Mark as Claimed</button>
                                        <?php endif; ?>
                                        <button class="text-sm font-medium text-red-600 hover:underline">Delete</button>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                        <?php else: ?>
                        <div class="text-center py-12">
                            <span class="material-symbols-outlined text-6xl text-slate-300">location_searching</span>
                            <p class="text-slate-500 mt-4">No lost & found items posted yet</p>
                            <button class="mt-4 px-4 py-2 bg-primary text-white rounded-lg font-medium hover:bg-primary/90">
                                Post Lost/Found Item
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

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

        menuToggle.addEventListener('click', toggleSidebar);
        sidebarOverlay.addEventListener('click', toggleSidebar);

        const sidebarLinks = sidebar.querySelectorAll('a');
        sidebarLinks.forEach(link => {
            link.addEventListener('click', () => {
                if (window.innerWidth < 1024) {
                    toggleSidebar();
                }
            });
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth >= 1024 && !sidebar.classList.contains('-translate-x-full')) {
                sidebar.classList.remove('-translate-x-full');
                sidebarOverlay.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }
        });

        // Form validation
        function validateProductForm(form) {
            const price = parseFloat(form.price.value);
            const originalPrice = form.original_price.value ? parseFloat(form.original_price.value) : 0;
            
            if(price <= 0) {
                alert('Price must be greater than 0');
                return false;
            }
            
            if(originalPrice > 0 && originalPrice < price) {
                alert('Original price must be greater than current price');
                return false;
            }
            
            return true;
        }

        function validateServiceForm(form) {
            const price = parseFloat(form.price.value);
            
            if(price <= 0) {
                alert('Price must be greater than 0');
                return false;
            }
            
            return true;
        }

        document.getElementById('productForm').addEventListener('submit', function(e) {
            if(!validateProductForm(this)) {
                e.preventDefault();
            }
        });

        document.getElementById('serviceForm').addEventListener('submit', function(e) {
            if(!validateServiceForm(this)) {
                e.preventDefault();
            }
        });

        // Tab switching
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.add('hidden');
            });
            
            // Remove active class from all buttons
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + '-tab').classList.remove('hidden');
            
            // Add active class to clicked button
            event.target.classList.add('active');
        }

        // Toggle new listing form
        function toggleNewListingForm() {
            const form = document.getElementById('newListingForm');
            const btn = document.getElementById('toggleFormBtn');
            
            if (form.classList.contains('hidden')) {
                form.classList.remove('hidden');
                btn.innerHTML = '<span class="material-symbols-outlined text-xl">close</span><span>Cancel</span>';
                // Scroll to form
                form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } else {
                form.classList.add('hidden');
                btn.innerHTML = '<span class="material-symbols-outlined text-xl">add</span><span>New Listing</span>';
            }
        }

        // Select listing type
        function selectListingType(type) {
            // Hide all forms
            document.querySelectorAll('.listing-form').forEach(form => {
                form.classList.add('hidden');
            });
            
            // Remove active class from all buttons
            document.querySelectorAll('.listing-type-btn').forEach(btn => {
                btn.classList.remove('active', 'border-primary', 'bg-primary/5');
                btn.classList.add('border-slate-200');
            });
            
            // Show selected form
            if (type === 'product') {
                document.getElementById('productForm').classList.remove('hidden');
            } else if (type === 'service') {
                document.getElementById('serviceForm').classList.remove('hidden');
            } else if (type === 'lost-found') {
                document.getElementById('lostFoundForm').classList.remove('hidden');
            }
            
            // Add active class to clicked button
            event.target.closest('.listing-type-btn').classList.add('active', 'border-primary', 'bg-primary/5');
            event.target.closest('.listing-type-btn').classList.remove('border-slate-200');
        }
    </script>
</body>

</html>
