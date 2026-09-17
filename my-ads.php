<?php session_start();
include_once 'includes/controller.php';

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>My Ads | CampMart</title>
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
    
    // Handle form submission for creating new ad
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_ad'])) {
    if (isAccountBlocked()) {
        $_SESSION['error'] = 'Your account has been suspended or banned. You cannot perform this action.';
        header('Location: my-ads.php');
        exit;
    }
    $sponsor_name = mysqli_real_escape_string($db, $_POST['sponsor_name']);
    $title = mysqli_real_escape_string($db, $_POST['title']);
    $description = mysqli_real_escape_string($db, $_POST['description']);
    $target_url = mysqli_real_escape_string($db, $_POST['target_url']);
    $placement = mysqli_real_escape_string($db, $_POST['placement']);
    $budget = floatval($_POST['budget']);
    $start_date = mysqli_real_escape_string($db, $_POST['start_date']);
    $end_date = mysqli_real_escape_string($db, $_POST['end_date']);
    
    // Handle image upload
    $image_url = '';
    if (isset($_FILES['ad_image']) && $_FILES['ad_image']['error'] === 0) {
        $upload_dir = 'uploads/ads/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_extension = pathinfo($_FILES['ad_image']['name'], PATHINFO_EXTENSION);
        $file_name = uniqid('ad_') . '.' . $file_extension;
        $target_path = $upload_dir . $file_name;
        
        if (move_uploaded_file($_FILES['ad_image']['tmp_name'], $target_path)) {
            $image_url = $target_path;
        }
    }
    
    $insert_query = "INSERT INTO sponsored_content (sponsor_name, title, description, image_url, target_url, placement, budget, start_date, end_date, status) 
                     VALUES ('$sponsor_name', '$title', '$description', '$image_url', '$target_url', '$placement', '$budget', '$start_date', '$end_date', 'active')";
    
    if ($db->query($insert_query)) {
        $_SESSION['success'] = 'Advertisement created successfully!';
    } else {
        $_SESSION['error'] = 'Failed to create advertisement. Please try again.';
    }
    
}

// Handle ad status update
if (isset($_GET['action']) && isset($_GET['id'])) {
    $ad_id = intval($_GET['id']);
    $action = $_GET['action'];
    
    if ($action === 'pause') {
        $db->query("UPDATE sponsored_content SET status = 'paused' WHERE id = '$ad_id'");
        $_SESSION['success'] = 'Advertisement paused successfully.';
    } elseif ($action === 'activate') {
        $db->query("UPDATE sponsored_content SET status = 'active' WHERE id = '$ad_id'");
        $_SESSION['success'] = 'Advertisement activated successfully.';
    } elseif ($action === 'end') {
        $db->query("UPDATE sponsored_content SET status = 'ended' WHERE id = '$ad_id'");
        $_SESSION['success'] = 'Advertisement ended successfully.';
    } elseif ($action === 'delete') {
        $db->query("DELETE FROM sponsored_content WHERE id = '$ad_id'");
        $_SESSION['success'] = 'Advertisement deleted successfully.';
    }
    
 
}

// Fetch user's ads
$ads_query = $db->query("SELECT * FROM sponsored_content WHERE user_id = '$userId' ORDER BY created_at DESC");
$ads_count = $ads_query->num_rows;

// Calculate statistics
$stats = [
    'total_views' => 0,
    'total_clicks' => 0,
    'total_spent' => 0,
    'active_ads' => 0
];

if ($ads_count > 0) {
    $ads_query->data_seek(0);
    while ($ad = $ads_query->fetch_assoc()) {
        $stats['total_views'] += $ad['view_count'];
        $stats['total_clicks'] += $ad['click_count'];
        $stats['total_spent'] += $ad['spent'];
        if ($ad['status'] === 'active') {
            $stats['active_ads']++;
        }
    }
}
?>

    <main class="flex-1 overflow-y-auto bg-background-main p-4 md:p-6 lg:p-8">
        <div class="max-w-7xl mx-auto space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-brand-green">My Advertisements</h1>
                    <p class="text-slate-500 mt-1">Create and manage your sponsored content and promotional ads.</p>
                </div>
                <button onclick="toggleNewAdForm()" id="toggleFormBtn" class="flex items-center gap-2 px-4 py-2.5 bg-primary text-white rounded-lg font-medium hover:bg-primary/90 transition-colors">
                    <span class="material-symbols-outlined text-xl">add</span>
                    <span>New Ad</span>
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

            <!-- New Ad Form (Hidden by default) -->
            <div id="newAdForm" class="hidden bg-surface-white rounded-xl border border-slate-200 shadow-lg p-6">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-2xl font-bold text-brand-green">Create New Advertisement</h2>
                    <button onclick="toggleNewAdForm()" class="text-slate-400 hover:text-slate-600">
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>
                
                <form method="POST" enctype="multipart/form-data" id="adForm" class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Sponsor Name*</label>
                            <input type="text" name="sponsor_name" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="Your company or brand name" />
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Ad Title*</label>
                            <input type="text" name="title" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="Catchy ad title" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Description</label>
                        <textarea name="description" rows="3" class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="Brief description of your ad"></textarea>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Ad Image*</label>
                        <input type="file" name="ad_image" accept="image/*" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary" />
                        <p class="text-xs text-slate-500 mt-1">Upload a high-quality image (JPG, PNG) for your ad</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Target URL*</label>
                            <input type="url" name="target_url" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="https://example.com" />
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Placement*</label>
                            <select name="placement" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary">
                                <option value="hero">Hero Banner</option>
                                <option value="sidebar">Sidebar</option>
                                <option value="grid">Grid</option>
                                <option value="footer">Footer</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Budget (₦)*</label>
                            <input type="number" name="budget" step="0.01" min="0" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="10000" />
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Start Date*</label>
                            <input type="date" name="start_date" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary" />
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">End Date*</label>
                            <input type="date" name="end_date" required class="w-full px-4 py-2.5 border border-slate-300 rounded-lg focus:ring-2 focus:ring-primary/20 focus:border-primary" />
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-4 border-t">
                        <button type="button" onclick="toggleNewAdForm()" class="px-6 py-2.5 border border-slate-300 text-slate-700 rounded-lg font-medium hover:bg-slate-50">
                            Cancel
                        </button>
                        <button type="submit" name="create_ad" class="px-6 py-2.5 bg-primary text-white rounded-lg font-medium hover:bg-primary/90">
                            Create Advertisement
                        </button>
                    </div>
                </form>
            </div>

            <!-- Stats Overview -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="bg-white p-6 rounded-xl border border-slate-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-slate-500">Active Ads</p>
                            <p class="text-2xl font-bold text-brand-green mt-1"><?= $stats['active_ads'] ?></p>
                        </div>
                        <div class="w-12 h-12 bg-emerald-100 rounded-lg flex items-center justify-center">
                            <span class="material-symbols-outlined text-emerald-600">campaign</span>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-xl border border-slate-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-slate-500">Total Views</p>
                            <p class="text-2xl font-bold text-brand-green mt-1"><?= number_format($stats['total_views']) ?></p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                            <span class="material-symbols-outlined text-blue-600">visibility</span>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-xl border border-slate-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-slate-500">Total Clicks</p>
                            <p class="text-2xl font-bold text-brand-green mt-1"><?= number_format($stats['total_clicks']) ?></p>
                        </div>
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                            <span class="material-symbols-outlined text-purple-600">touch_app</span>
                        </div>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-xl border border-slate-200">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-slate-500">Total Spent</p>
                            <p class="text-2xl font-bold text-brand-green mt-1">₦<?= number_format($stats['total_spent'], 2) ?></p>
                        </div>
                        <div class="w-12 h-12 bg-orange-100 rounded-lg flex items-center justify-center">
                            <span class="material-symbols-outlined text-orange-600">payments</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Ads List -->
            <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200">
                    <h2 class="text-xl font-bold text-brand-green">Your Advertisements</h2>
                </div>

                <?php if($ads_count > 0): ?>
                <div class="divide-y divide-slate-200">
                    <?php 
                    $ads_query->data_seek(0);
                    while($ad = $ads_query->fetch_assoc()): 
                        $ctr = $ad['view_count'] > 0 ? ($ad['click_count'] / $ad['view_count']) * 100 : 0;
                        $budget_used_percent = $ad['budget'] > 0 ? ($ad['spent'] / $ad['budget']) * 100 : 0;
                    ?>
                    <div class="p-6 hover:bg-slate-50 transition-colors">
                        <div class="flex flex-col md:flex-row gap-6">
                            <!-- Ad Image -->
                            <div class="w-full md:w-48 h-32 bg-slate-100 rounded-lg overflow-hidden flex-shrink-0">
                                <?php if($ad['image_url']): ?>
                                <img src="<?= htmlspecialchars($ad['image_url']) ?>" alt="<?= htmlspecialchars($ad['title']) ?>" class="w-full h-full object-cover" />
                                <?php else: ?>
                                <div class="w-full h-full flex items-center justify-center text-slate-400">
                                    <span class="material-symbols-outlined text-4xl">image</span>
                                </div>
                                <?php endif; ?>
                            </div>

                            <!-- Ad Details -->
                            <div class="flex-1">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="flex-1">
                                        <div class="flex items-center gap-3">
                                            <h3 class="font-bold text-text-dark text-lg"><?= htmlspecialchars($ad['title']) ?></h3>
                                            <span class="px-2.5 py-1 text-xs font-medium rounded-full <?php
                                                echo $ad['status'] == 'active' ? 'bg-emerald-100 text-emerald-700' : 
                                                     ($ad['status'] == 'paused' ? 'bg-yellow-100 text-yellow-700' : 'bg-slate-100 text-slate-700');
                                            ?>">
                                                <?= ucfirst($ad['status']) ?>
                                            </span>
                                            <span class="px-2.5 py-1 text-xs font-medium rounded-full bg-blue-100 text-blue-700">
                                                <?= ucfirst($ad['placement']) ?>
                                            </span>
                                        </div>
                                        <p class="text-sm text-slate-600 mt-1"><?= htmlspecialchars($ad['sponsor_name']) ?></p>
                                        <?php if($ad['description']): ?>
                                        <p class="text-sm text-slate-500 mt-2"><?= htmlspecialchars(substr($ad['description'], 0, 100)) ?><?= strlen($ad['description']) > 100 ? '...' : '' ?></p>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Stats -->
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-4">
                                    <div>
                                        <p class="text-xs text-slate-500">Views</p>
                                        <p class="text-lg font-bold text-brand-green"><?= number_format($ad['view_count']) ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-slate-500">Clicks</p>
                                        <p class="text-lg font-bold text-brand-green"><?= number_format($ad['click_count']) ?></p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-slate-500">CTR</p>
                                        <p class="text-lg font-bold text-brand-green"><?= number_format($ctr, 2) ?>%</p>
                                    </div>
                                    <div>
                                        <p class="text-xs text-slate-500">Budget Used</p>
                                        <p class="text-lg font-bold text-brand-green"><?= number_format($budget_used_percent, 1) ?>%</p>
                                    </div>
                                </div>

                                <!-- Budget Bar -->
                                <div class="mt-4">
                                    <div class="flex justify-between text-xs text-slate-500 mb-1">
                                        <span>₦<?= number_format($ad['spent'], 2) ?> spent</span>
                                        <span>₦<?= number_format($ad['budget'], 2) ?> budget</span>
                                    </div>
                                    <div class="w-full bg-slate-200 rounded-full h-2">
                                        <div class="bg-primary rounded-full h-2" style="width: <?= min($budget_used_percent, 100) ?>%"></div>
                                    </div>
                                </div>

                                <!-- Dates -->
                                <div class="flex items-center gap-4 mt-3 text-xs text-slate-500">
                                    <span class="flex items-center gap-1">
                                        <span class="material-symbols-outlined text-sm">calendar_today</span>
                                        <?= date('M d, Y', strtotime($ad['start_date'])) ?> - <?= date('M d, Y', strtotime($ad['end_date'])) ?>
                                    </span>
                                </div>

                                <!-- Actions -->
                                <div class="flex flex-wrap gap-2 mt-4">
                                    <?php if($ad['status'] == 'active'): ?>
                                    <a href="?action=pause&id=<?= $ad['id'] ?>" class="text-sm font-medium text-yellow-600 hover:underline">Pause</a>
                                    <?php elseif($ad['status'] == 'paused'): ?>
                                    <a href="?action=activate&id=<?= $ad['id'] ?>" class="text-sm font-medium text-emerald-600 hover:underline">Activate</a>
                                    <?php endif; ?>
                                    
                                    <?php if($ad['target_url']): ?>
                                    <a href="<?= htmlspecialchars($ad['target_url']) ?>" target="_blank" class="text-sm font-medium text-primary hover:underline">View Target</a>
                                    <?php endif; ?>
                                    
                                    <?php if($ad['status'] != 'ended'): ?>
                                    <a href="?action=end&id=<?= $ad['id'] ?>" class="text-sm font-medium text-slate-600 hover:underline" onclick="return confirm('Are you sure you want to end this ad?')">End Campaign</a>
                                    <?php endif; ?>
                                    
                                    <a href="?action=delete&id=<?= $ad['id'] ?>" class="text-sm font-medium text-red-600 hover:underline" onclick="return confirm('Are you sure you want to delete this ad?')">Delete</a>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
                <?php else: ?>
                <div class="text-center py-12">
                    <span class="material-symbols-outlined text-6xl text-slate-300">campaign</span>
                    <p class="text-slate-500 mt-4">No advertisements created yet</p>
                    <button class="mt-4 px-4 py-2 bg-primary text-white rounded-lg font-medium hover:bg-primary/90" onclick="toggleNewAdForm()">
                        Create Your First Ad
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

        // Toggle new ad form
        function toggleNewAdForm() {
            const form = document.getElementById('newAdForm');
            const btn = document.getElementById('toggleFormBtn');

            if (form.classList.contains('hidden')) {
                form.classList.remove('hidden');
                btn.innerHTML = '<span class="material-symbols-outlined text-xl">close</span><span>Cancel</span>';
                form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } else {
                form.classList.add('hidden');
                btn.innerHTML = '<span class="material-symbols-outlined text-xl">add</span><span>New Ad</span>';
            }
        }

        // Form validation
        document.getElementById('adForm').addEventListener('submit', function(e) {
            const startDate = new Date(this.start_date.value);
            const endDate = new Date(this.end_date.value);
            const budget = parseFloat(this.budget.value);

            if(endDate <= startDate) {
                alert('End date must be after start date');
                e.preventDefault();
                return false;
            }

            if(budget <= 0) {
                alert('Budget must be greater than 0');
                e.preventDefault();
                return false;
            }

            return true;
        });

        // Set minimum date for start date (today)
        const today = new Date().toISOString().split('T')[0];
        document.querySelector('input[name="start_date"]').setAttribute('min', today);
        
        // Update end date minimum when start date changes
        document.querySelector('input[name="start_date"]').addEventListener('change', function() {
            document.querySelector('input[name="end_date"]').setAttribute('min', this.value);
        });
    </script>
</body>

</html>
