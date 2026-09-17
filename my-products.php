<?php session_start();
include_once 'includes/controller.php';

// Handle Mark as Sold
if(isset($_POST['mark_as_sold']) && isset($_POST['product_id'])) {
    header('Content-Type: application/json');
    $product_id = (int)$_POST['product_id'];
    
    // Verify ownership
    $check = $db->query("SELECT id, metadata FROM products WHERE id = '$product_id' AND user_id = '$userId'");
    if($check->num_rows > 0) {
        $product = $check->fetch_assoc();
        $updatedMetadata = $db->real_escape_string(setProductStockQuantity($product['metadata'] ?? null, 0));
        $update = $db->query("UPDATE products SET availability = 'sold', metadata = '$updatedMetadata' WHERE id = '$product_id'");
        if($update) {
            echo json_encode(['success' => true, 'message' => 'Product marked as sold']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database update failed']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Product not found or access denied']);
    }
    exit;
}

// Handle Mark as Available
if(isset($_POST['mark_as_available']) && isset($_POST['product_id'])) {
    header('Content-Type: application/json');
    $product_id = (int)$_POST['product_id'];
    
    // Verify ownership
    $check = $db->query("SELECT id, metadata, availability FROM products WHERE id = '$product_id' AND user_id = '$userId'");
    if($check->num_rows > 0) {
        $product = $check->fetch_assoc();
        $currentStock = getProductStockQuantity($product);
        $newStock = $currentStock > 0 ? $currentStock : 1;
        $updatedMetadata = $db->real_escape_string(setProductStockQuantity($product['metadata'] ?? null, $newStock));
        $update = $db->query("UPDATE products SET availability = 'available', metadata = '$updatedMetadata' WHERE id = '$product_id'");
        if($update) {
            echo json_encode(['success' => true, 'message' => 'Product marked as available']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database update failed']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Product not found or access denied']);
    }
    exit;
}

// Handle stock adjustments
if(isset($_POST['adjust_stock']) && isset($_POST['product_id'])) {
    header('Content-Type: application/json');
    $product_id = (int) $_POST['product_id'];
    $mode = $_POST['mode'] ?? '';
    $quantity = max(1, (int) ($_POST['quantity'] ?? 0));

    if (!in_array($mode, ['add', 'remove'], true)) {
        echo json_encode(['success' => false, 'message' => 'Invalid stock action']);
        exit;
    }

    $check = $db->query("SELECT id, metadata, availability FROM products WHERE id = '$product_id' AND user_id = '$userId'");
    if($check->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Product not found or access denied']);
        exit;
    }

    $product = $check->fetch_assoc();
    $currentStock = getProductStockQuantity($product);
    $newStock = $mode === 'add' ? ($currentStock + $quantity) : ($currentStock - $quantity);

    if ($newStock < 0) {
        echo json_encode(['success' => false, 'message' => 'You cannot remove more stock than is currently available']);
        exit;
    }

    $availability = $newStock > 0 ? 'available' : 'sold';
    $updatedMetadata = $db->real_escape_string(setProductStockQuantity($product['metadata'] ?? null, $newStock));

    $update = $db->query("UPDATE products SET metadata = '$updatedMetadata', availability = '$availability' WHERE id = '$product_id'");
    if ($update) {
        echo json_encode([
            'success' => true,
            'message' => $mode === 'add' ? 'Stock updated successfully' : 'Stock reduced successfully',
            'new_quantity' => $newStock
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update stock']);
    }
    exit;
}

// Handle Delete Product
if(isset($_POST['delete_product']) && isset($_POST['product_id'])) {
    header('Content-Type: application/json');
    $product_id = (int)$_POST['product_id'];
    
    // Verify ownership
    $check = $db->query("SELECT id FROM products WHERE id = '$product_id' AND user_id = '$userId'");
    if($check->num_rows > 0) {
        // Delete product images first
        $db->query("DELETE FROM product_images WHERE product_id = '$product_id'");
        // Delete the product
        $delete = $db->query("DELETE FROM products WHERE id = '$product_id'");
        if($delete) {
            echo json_encode(['success' => true, 'message' => 'Product deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Database delete failed']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Product not found or access denied']);
    }
    exit;
}

// Handle Edit Product - Load product data if edit parameter is present
$editProduct = null;
if(isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $edit_query = $db->query("SELECT p.*, 
        (SELECT GROUP_CONCAT(image_url) FROM product_images WHERE product_id = p.id) as all_images
        FROM products p 
        WHERE p.id = '$edit_id' AND p.user_id = '$userId'");
    if($edit_query->num_rows > 0) {
        $editProduct = $edit_query->fetch_assoc();
        $editProduct['available_quantity'] = getProductStockQuantity($editProduct);
        $editProduct['delivery_fee'] = getProductDeliveryFee($editProduct);
    }
}

// Handle Update Product
if(isset($_POST['UpdateProduct'])) {
    $product_id = (int)$_POST['product_id'];
    $title = $db->real_escape_string($_POST['title']);
    $category_id = (int)$_POST['category_id'];
    $price = (float)$_POST['price'];
    $original_price = $_POST['original_price'] ? (float)$_POST['original_price'] : null;
    $condition_type = $db->real_escape_string($_POST['condition_type']);
    $description = $db->real_escape_string($_POST['description']);
    // $location = $db->real_escape_string($_POST['location']);
    $negotiable = isset($_POST['negotiable']) ? 1 : 0;
    $available_quantity = max(0, (int) ($_POST['available_quantity'] ?? 0));
    $delivery_fee = max(0, (float) ($_POST['delivery_fee'] ?? 1500));
    
    // Verify ownership
    $check = $db->query("SELECT id, metadata FROM products WHERE id = '$product_id' AND user_id = '$userId'");
    if($check->num_rows > 0) {
        $existingProduct = $check->fetch_assoc();
        $original_price_sql = $original_price ? "'$original_price'" : "NULL";
        $metadataWithStock = setProductStockQuantity($existingProduct['metadata'] ?? null, $available_quantity);
        $metadataWithDelivery = setProductDeliveryFee($metadataWithStock, $delivery_fee);
        $updatedMetadata = $db->real_escape_string($metadataWithDelivery);
        $availability = $available_quantity > 0 ? 'available' : 'sold';
        
        // Handle product image uploads
        if(isset($_FILES['images']) && !empty($_FILES['images']['name'][0])) {
            $upload_dir = 'uploads/products/';
            
            // Create directory if it doesn't exist
            if(!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
            $max_file_size = 5 * 1024 * 1024; // 5MB
            
            // If replace mode, delete existing images
            if(isset($_POST['replace_images']) && $_POST['replace_images'] == '1') {
                // Get existing images to delete files
                $existing_imgs = $db->query("SELECT image_url FROM product_images WHERE product_id = '$product_id'");
                while($img_row = $existing_imgs->fetch_assoc()) {
                    if(file_exists($img_row['image_url'])) {
                        unlink($img_row['image_url']);
                    }
                }
                // Delete from database
                $db->query("DELETE FROM product_images WHERE product_id = '$product_id'");
            }
            
            // Get current image count
            $current_count_result = $db->query("SELECT COUNT(*) as count FROM product_images WHERE product_id = '$product_id'");
            $current_count = $current_count_result->fetch_assoc()['count'];
            
            $uploaded_count = 0;
            $first_image = true;
            
            foreach($_FILES['images']['tmp_name'] as $key => $tmp_name) {
                if(empty($tmp_name)) continue;
                if($current_count + $uploaded_count >= 5) break; // Max 5 images
                
                $file_name = $_FILES['images']['name'][$key];
                $file_size = $_FILES['images']['size'][$key];
                $file_type = $_FILES['images']['type'][$key];
                $file_error = $_FILES['images']['error'][$key];
                
                // Validate file
                if($file_error !== UPLOAD_ERR_OK) continue;
                if(!in_array($file_type, $allowed_types)) continue;
                if($file_size > $max_file_size) continue;
                
                // Generate unique filename
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                $unique_name = 'product_' . $product_id . '_' . uniqid() . '.' . $file_ext;
                $target_path = $upload_dir . $unique_name;
                
                // Move uploaded file
                if(move_uploaded_file($tmp_name, $target_path)) {
                    // Set first uploaded image as primary if no images exist
                    $is_primary = ($current_count == 0 && $first_image) ? 1 : 0;
                    $first_image = false;
                    
                    // Insert into product_images table
                    $db->query("INSERT INTO product_images (product_id, image_url, is_primary) 
                               VALUES ('$product_id', '$target_path', '$is_primary')");
                    $uploaded_count++;
                }
            }
        }
        
        $update = $db->query("UPDATE products SET 
            title = '$title',
            category_id = '$category_id',
            price = '$price',
            original_price = $original_price_sql,
            condition_type = '$condition_type',
            description = '$description',
            negotiable = '$negotiable',
            availability = '$availability',
            metadata = '$updatedMetadata'
            WHERE id = '$product_id'");
        
        if($update) {
            // AI: refresh the semantic embedding after an edit.
            include_once 'includes/ai/search.php';
            ai_index_product_safe((int) $product_id);
            $_SESSION['success'] = 'Product updated successfully!';
            header('Location: my-products.php');
            exit;
        } else {
            $_SESSION['error'] = 'Failed to update product';
        }
    } else {
        $_SESSION['error'] = 'Product not found or access denied';
    }
}

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>My Products | CampMart</title>
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
    <?php include_once 'includes/user-nav.php';
    

$products_query = $db->query("
    SELECT p.*, c.name as category_name,
        (SELECT image_url FROM product_images WHERE product_id = p.id AND is_primary = 1 LIMIT 1) as primary_image,
        COALESCE(v.unique_views_count, 0) as unique_views_count
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    LEFT JOIN (
        SELECT product_id, COUNT(DISTINCT user_id) as unique_views_count
        FROM product_views
        WHERE user_id IS NOT NULL
        GROUP BY product_id
    ) v ON p.id = v.product_id
    WHERE p.user_id = '$userId'
    ORDER BY p.created_at DESC
");

$products = [];
while ($product_row = $products_query->fetch_assoc()) {
    $products[] = $product_row;
}

$products_count = count($products);
$total_views = 0;
$total_stock = 0;
$product_viewers = [];
$productsWithStock = [];

foreach ($products as $product_row) {
    $total_views += (int) ($product_row['unique_views_count'] ?? 0);
    $product_row['available_quantity'] = getProductStockQuantity($product_row);
    $product_row['delivery_fee'] = getProductDeliveryFee($product_row);
    $total_stock = ($total_stock ?? 0) + (int) $product_row['available_quantity'];
    $productsWithStock[] = $product_row;
}

$products = $productsWithStock ?? [];
$total_stock = $total_stock ?? 0;

if ($products_count > 0) {
    $product_ids = array_map('intval', array_column($products, 'id'));
    $product_ids_sql = implode(',', $product_ids);
    $viewer_query = $db->query("
        SELECT pv.product_id,
               u.id as viewer_id,
               u.full_name,
               u.username,
               u.profile_image,
               MAX(pv.viewed_at) as last_viewed_at
        FROM product_views pv
        INNER JOIN users u ON u.id = pv.user_id
        WHERE pv.product_id IN ($product_ids_sql)
        GROUP BY pv.product_id, u.id, u.full_name, u.username, u.profile_image
        ORDER BY last_viewed_at DESC
    ");

    while ($viewer = $viewer_query->fetch_assoc()) {
        $product_viewers[$viewer['product_id']][] = $viewer;
    }
}

?>
    <main class="flex-1 overflow-y-auto bg-background-main p-4 md:p-6 lg:p-8">
        <div class="max-w-7xl mx-auto space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-brand-green">My Products</h1>
                    <p class="text-slate-500 mt-1">Manage your product listings and keep everything in one place.</p>
                </div>
                <button onclick="toggleNewListingForm()" id="toggleFormBtn" class="flex items-center gap-2 px-4 py-2.5 bg-primary text-white rounded-lg font-medium hover:bg-primary/90 transition-colors">
                    <span class="material-symbols-outlined text-xl">add</span>
                    <span>New Product</span>
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
            <div id="newListingForm" class="<?= $editProduct ? '' : 'hidden' ?> bg-surface-white rounded-xl border border-slate-200 shadow-lg p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-xl font-bold text-brand-green"><?= $editProduct ? 'Edit Product' : 'Create New Product Listing' ?></h2>
                    <button onclick="cancelEdit()" class="p-2 hover:bg-slate-100 rounded-lg transition-colors">
                        <span class="material-symbols-outlined text-slate-400">close</span>
                    </button>
                </div>

                <form id="productForm" class="listing-form space-y-4" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>" />
                    <?php if($editProduct): ?>
                    <input type="hidden" name="product_id" value="<?= $editProduct['id'] ?>" />
                    <?php endif; ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-text-dark mb-2">Product Title</label>
                            <input type="text" name="title" required value="<?= $editProduct ? htmlspecialchars($editProduct['title']) : '' ?>" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="e.g., iPhone 13 Pro Max 256GB">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-text-dark mb-2">Category</label>
                            <select name="category_id" required class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                                <option value="">Select category</option>
                                <?php 
                                $categories = $db->query("SELECT * FROM categories WHERE is_active = 1 AND parent_id IS NULL ORDER BY name");
                                while($cat = $categories->fetch_assoc()): 
                                ?>
                                <option value="<?= $cat['id'] ?>" <?= ($editProduct && $editProduct['category_id'] == $cat['id']) ? 'selected' : '' ?>><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-text-dark mb-2">Price (₦)</label>
                            <input type="number" name="price" required step="0.01" min="0" value="<?= $editProduct ? $editProduct['price'] : '' ?>" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="0.00">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-text-dark mb-2">Original Price (Optional)</label>
                            <input type="number" name="original_price" step="0.01" min="0" value="<?= $editProduct ? $editProduct['original_price'] : '' ?>" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="0.00">
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-text-dark mb-2">Condition</label>
                            <select name="condition_type" required class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                                <option value="new" <?= ($editProduct && $editProduct['condition_type'] == 'new') ? 'selected' : '' ?>>New</option>
                                <option value="used" <?= ($editProduct && $editProduct['condition_type'] == 'used') ? 'selected' : '' ?>>Used</option>
                                <!-- <option value="like_new" <?= ($editProduct && $editProduct['condition_type'] == 'like_new') ? 'selected' : '' ?>>Like New</option>
                                <option value="good" <?= ($editProduct && $editProduct['condition_type'] == 'good') ? 'selected' : '' ?>>Good</option>
                                <option value="fair" <?= ($editProduct && $editProduct['condition_type'] == 'fair') ? 'selected' : '' ?>>Fair</option>
                                <option value="for_parts" <?= ($editProduct && $editProduct['condition_type'] == 'for_parts') ? 'selected' : '' ?>>For Parts</option> -->
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-text-dark mb-2">Available Quantity</label>
                            <input type="number" name="available_quantity" required min="0" step="1" value="<?= $editProduct ? (int) ($editProduct['available_quantity'] ?? 0) : 1 ?>" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="1">
                            <p class="text-xs text-slate-500 mt-1">This is the stock you currently have ready to sell.</p>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-text-dark mb-2">Delivery Fee (₦)</label>
                            <input type="number" name="delivery_fee" required min="0" step="0.01" value="<?= $editProduct ? (float) ($editProduct['delivery_fee'] ?? 1500) : 1500 ?>" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="1500">
                            <p class="text-xs text-slate-500 mt-1">Applied only when the buyer chooses personal delivery.</p>
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-text-dark mb-2">Description</label>
                            <textarea name="description" required rows="4" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="Describe your product in detail..."><?= $editProduct ? htmlspecialchars($editProduct['description']) : '' ?></textarea>
                        </div>
                        <!-- <div>
                            <label class="block text-sm font-bold text-text-dark mb-2">Location</label>
                            <input type="text" name="location" required value="<?= $editProduct ? htmlspecialchars($editProduct['location']) : '' ?>" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="e.g., Main Campus, Hostel A">
                        </div> -->
                        <div class="md:col-span-2">
                            <label class="block text-sm font-bold text-text-dark mb-2">Product Images</label>
                            
                            <?php if($editProduct && !empty($editProduct['all_images'])): 
                                $current_images = explode(',', $editProduct['all_images']);
                                if(!empty($current_images)):
                            ?>
                            <div class="mb-4">
                                <p class="text-sm text-slate-600 mb-2">Current Images (<?= count($current_images) ?>):</p>
                                <div class="grid grid-cols-3 md:grid-cols-5 gap-3">
                                    <?php foreach($current_images as $img): ?>
                                    <div class="relative group aspect-square rounded-lg overflow-hidden border-2 border-slate-200">
                                        <img src="<?= htmlspecialchars($img) ?>" alt="Product image" class="w-full h-full object-cover">
                                        <div class="absolute inset-0 bg-black/50 opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center">
                                            <span class="material-symbols-outlined text-white text-sm">image</span>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="flex items-center gap-2 text-sm text-slate-600 cursor-pointer">
                                    <input type="checkbox" checked name="replace_images" value="1" class="rounded border-slate-300 text-primary focus:ring-primary">
                                    <span>Replace all existing images with new uploads</span>
                                </label>
                                <p class="text-xs text-slate-500 mt-1 ml-6">If unchecked, new images will be added to existing ones (max 5 total)</p>
                            </div>
                            <?php endif; endif; ?>
                            
                            <input type="file" name="images[]" multiple accept="image/*" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-primary/10 file:text-primary hover:file:bg-primary/20">
                            <p class="text-xs text-slate-500 mt-1"><?= $editProduct ? 'Upload new images (optional)' : 'Upload up to 5 images (Max 5MB each)' ?></p>
                        </div>
                        </div>
                        <div class="md:col-span-2">
                            <label class="flex items-center gap-2">
                                <input type="checkbox" name="negotiable" value="1" <?= (!$editProduct || $editProduct['negotiable']) ? 'checked' : '' ?> class="size-4 rounded border-slate-300 text-primary focus:ring-primary">
                                <span class="text-sm font-medium text-slate-700">Price is negotiable</span>
                            </label>
                        </div>
                    
                    <div class="flex gap-3 pt-4">
                        <button type="submit" name="<?= $editProduct ? 'UpdateProduct' : 'CreateProduct' ?>" class="flex-1 px-6 py-3 bg-primary text-white rounded-lg font-bold hover:bg-primary/90 transition-colors">
                            <?= $editProduct ? 'Update Product' : 'Create Product' ?>
                        </button>
                        <button type="button" onclick="cancelEdit()" class="px-6 py-3 bg-slate-100 text-slate-700 rounded-lg font-bold hover:bg-slate-200 transition-colors">
                            Cancel
                        </button>
                    </div>
                </form>
            </div></div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 my-3">
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
                        <p class="text-slate-500 text-sm font-medium">Total Views</p>
                        <span class="material-symbols-outlined text-purple-600">visibility</span>
                    </div>
                    <p class="text-text-dark text-2xl font-bold tracking-tight">
                        <?= number_format($total_views) ?>
                    </p>
                    <p class="text-slate-400 text-xs font-normal mt-1">Unique logged-in viewers across all listings</p>
                </div>
                <div class="flex flex-col gap-2 rounded-xl p-6 bg-surface-white border border-slate-200 shadow-sm">
                    <div class="flex items-center justify-between">
                        <p class="text-slate-500 text-sm font-medium">Total Stock</p>
                        <span class="material-symbols-outlined text-emerald-600">inventory_2</span>
                    </div>
                    <p class="text-text-dark text-2xl font-bold tracking-tight"><?= number_format($total_stock) ?></p>
                    <p class="text-slate-400 text-xs font-normal mt-1">Units currently available to buy</p>
                </div>
            </div>

            <!-- Products List -->

                    <?php if($products_count > 0): ?>
                    <div class="space-y-4">
                        <?php foreach($products as $product): 
                            $viewers = $product_viewers[$product['id']] ?? [];
                        ?>
                        <div class="flex flex-col sm:flex-row bg-white gap-4 p-2 border border-slate-200 rounded-lg hover:border-primary/50 hover:shadow-md transition-all">
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
                                        <h3 class="font-bold text-text-dark"><?= htmlspecialchars($product['title']) ?></h3>
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
                                        <?= number_format($product['unique_views_count']) ?> unique views
                                    </span>
                                    <span class="text-xs text-slate-500 flex items-center gap-1">
                                        <span class="material-symbols-outlined text-sm">bookmark</span>
                                        <?= number_format($product['bookmarks_count']) ?> saved
                                    </span>
                                    <span class="text-xs text-slate-500 flex items-center gap-1">
                                        <span class="material-symbols-outlined text-sm">inventory_2</span>
                                        <?= number_format((int) ($product['available_quantity'] ?? 0)) ?> in stock
                                    </span>
                                    <span class="text-xs text-slate-500 flex items-center gap-1">
                                        <span class="material-symbols-outlined text-sm">local_shipping</span>
                                        <?= formatCurrency((float) ($product['delivery_fee'] ?? 1500)) ?> delivery fee
                                    </span>
                                </div>
                                <div class="flex flex-wrap gap-2 mt-4">
                                    <button onclick="editProduct(<?= $product['id'] ?>)" class="text-sm font-medium text-brand-green hover:underline">Edit</button>
                                    <button onclick="openProductProfile(<?= $product['id'] ?>)" class="text-sm font-medium text-indigo-600 hover:underline">Profile</button>
                                    <button onclick="viewProduct('<?= $product['slug'] ?>')" class="text-sm font-medium text-primary hover:underline">View</button>
                                    <button onclick="adjustStock(<?= $product['id'] ?>, 'add')" class="text-sm font-medium text-emerald-600 hover:underline">Restock</button>
                                    <button onclick="adjustStock(<?= $product['id'] ?>, 'remove')" class="text-sm font-medium text-amber-600 hover:underline">Remove Stock</button>
                                    <?php if($product['availability'] == 'available'): ?>
                                    <button onclick="markAsSold(<?= $product['id'] ?>)" class="text-sm font-medium text-slate-600 hover:underline">Mark as Sold</button>
                                    <?php elseif($product['availability'] == 'sold'): ?>
                                    <button onclick="markAsAvailable(<?= $product['id'] ?>)" class="text-sm font-medium text-emerald-600 hover:underline">Mark as Available</button>
                                    <?php endif; ?>
                                    <button onclick="deleteProduct(<?= $product['id'] ?>)" class="text-sm font-medium text-red-600 hover:underline">Delete</button>
                                </div>

                            </div>
                        </div>
                        <?php endforeach; ?>
                    
                    <?php else: ?>
                    <div class="text-center py-12">
                        <span class="material-symbols-outlined text-6xl text-slate-300">inventory_2</span>
                        <p class="text-slate-500 mt-4">No products listed yet</p>
                        <button class="mt-4 px-4 py-2 bg-primary text-white rounded-lg font-medium hover:bg-primary/90" onclick="toggleNewListingForm()">
                            Create Your First Product
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


            function toggleSidebar() {
            sidebar.classList.toggle('-translate-x-full');
            sidebarOverlay.classList.toggle('hidden');
            document.body.classList.toggle('overflow-hidden');
        }

        menuToggle.addEventListener('click', toggleSidebar);
        sidebarOverlay.addEventListener('click', toggleSidebar);

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

        document.getElementById('productForm').addEventListener('submit', function(e) {
            if(!validateProductForm(this)) {
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
                btn.innerHTML = '<span class="material-symbols-outlined text-xl">add</span><span>New Product</span>';
            }
        }

        // Cancel edit and return to main view
        function cancelEdit() {
            window.location.href = 'my-products.php';
        }

        // View product details
        function viewProduct(productSlug) {
            window.location.href = 'product/' + productSlug;
        }

        // Edit product
        function editProduct(productId) {
            window.location.href = 'my-products.php?edit=' + productId;
        }

        function openProductProfile(productId) {
            window.location.href = 'product-profile.php?id=' + productId;
        }

        function adjustStock(productId, mode) {
            const actionText = mode === 'add' ? 'restock' : 'remove from';
            const rawValue = prompt(`How many units do you want to ${actionText} the available stock?`);

            if (rawValue === null) {
                return;
            }

            const quantity = parseInt(rawValue, 10);
            if (!Number.isInteger(quantity) || quantity <= 0) {
                alert('Please enter a whole number greater than zero.');
                return;
            }

            fetch('my-products.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `adjust_stock=1&product_id=${productId}&mode=${encodeURIComponent(mode)}&quantity=${quantity}`
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'Failed to update stock');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while updating stock');
            });
        }

        // Mark product as sold
        function markAsSold(productId) {
            if(confirm('Are you sure you want to mark this product as sold?')) {
                fetch('my-products.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'mark_as_sold=1&product_id=' + productId
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        location.reload();
                    } else {
                        alert(data.message || 'Failed to update product status');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while updating product status');
                });
            }
        }

        // Mark product as available
        function markAsAvailable(productId) {
            if(confirm('Are you sure you want to mark this product as available again?')) {
                fetch('my-products.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'mark_as_available=1&product_id=' + productId
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        location.reload();
                    } else {
                        alert(data.message || 'Failed to update product status');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while updating product status');
                });
            }
        }

        // Delete product
        function deleteProduct(productId) {
            if(confirm('Are you sure you want to delete this product? This action cannot be undone.')) {
                fetch('my-products.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: 'delete_product=1&product_id=' + productId
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        location.reload();
                    } else {
                        alert(data.message || 'Failed to delete product');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred while deleting product');
                });
            }
        }
    </script>
</body>

</html>
