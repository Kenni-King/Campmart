<?php
session_start();
include_once 'includes/controller.php';

$userId = $_SESSION['userAppId'] ?? 0;

if (!$userId) {
    header('Location: login.php');
    exit;
}

$user_query = $db->query("
    SELECT u.*, uni.name AS university_name, uni.code AS university_code, uni.location AS university_location
    FROM users u
    LEFT JOIN universities uni ON u.university_id = uni.id
    WHERE u.id = '$userId'
    LIMIT 1
");
$user = $user_query ? $user_query->fetch_assoc() : null;

if (!$user) {
    $_SESSION['error'] = 'Unable to load your profile right now.';
    header('Location: dashboard.php');
    exit;
}

$total_products = $db->query("SELECT COUNT(*) as count FROM products WHERE user_id = '$userId'")->fetch_assoc()['count'];
$total_services = $db->query("SELECT COUNT(*) as count FROM services WHERE user_id = '$userId'")->fetch_assoc()['count'];
$total_orders = $db->query("SELECT COUNT(*) as count FROM orders WHERE seller_id = '$userId'")->fetch_assoc()['count'];
$total_revenue = $db->query("SELECT SUM(total_amount) as total FROM orders WHERE seller_id = '$userId' AND status = 'completed'")->fetch_assoc()['total'] ?? 0;

$profile_checks = [
    'Profile photo' => !empty($user['profile_image']),
    'Bio' => !empty($user['bio']),
    'Phone' => !empty($user['phone']),
    'Location' => !empty($user['location']),
    'Department' => !empty($user['department']),
    'Level' => !empty($user['level']),
    'University' => !empty($user['university_name']),
];

$completed_fields = count(array_filter($profile_checks));
$total_fields = count($profile_checks);
$profile_completion = $total_fields > 0 ? round(($completed_fields / $total_fields) * 100) : 0;
$missing_items = array_keys(array_filter($profile_checks, fn($is_complete) => !$is_complete));

$display_name = trim((string) ($user['full_name'] ?? ''));
if ($display_name === '') {
    $display_name = trim(($user['firstname'] ?? '') . ' ' . ($user['lastname'] ?? ''));
}
if ($display_name === '') {
    $display_name = $user['username'] ?? 'User';
}

$email_verified = !empty($user['email_verified']);
$phone_verified = !empty($user['phone_verified']);
$is_verified_seller = !empty($user['is_verified_seller']);
$user_initial = strtoupper(substr($display_name, 0, 1));
$level_display = !empty($user['level']) ? ucwords(str_replace('-', ' ', (string) $user['level'])) : null;
$joined_display = !empty($user['created_at']) ? date('M Y', strtotime($user['created_at'])) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <base href="<?php echo SITE_URL; ?>">
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>My Profile | CampMart</title>
    <script src="tailwind34.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />
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
                        "2xl": "1rem",
                        "3xl": "1.5rem",
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

        details summary::-webkit-details-marker {
            display: none;
        }
    </style>
</head>
<body class="bg-background-main min-h-screen text-text-dark">
    <?php include_once 'includes/user-nav.php'; ?>

    <main class="flex-1 overflow-y-auto bg-background-main p-4 md:p-6 lg:p-8">
        <div class="max-w-6xl mx-auto space-y-6">
            <section class="relative overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,_rgba(244,140,37,0.16),_transparent_28%),radial-gradient(circle_at_bottom_left,_rgba(6,78,59,0.14),_transparent_30%)]"></div>
                <div class="relative p-5 sm:p-6 lg:p-8">
                    <div class="flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                        <div class="flex flex-col gap-5 sm:flex-row sm:items-start">
                            <form method="POST" enctype="multipart/form-data" id="profileImageForm" class="shrink-0">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>" />
                                <input type="hidden" name="UploadProfileImage" value="1" />
                                <div class="relative mx-auto sm:mx-0">
                                    <div class="size-24 sm:size-28 lg:size-32 rounded-3xl border-4 border-white bg-slate-100 overflow-hidden shadow-xl">
                                        <?php if (!empty($user['profile_image'])): ?>
                                            <img src="<?= htmlspecialchars($user['profile_image']) ?>" alt="Profile photo" class="w-full h-full object-cover" />
                                        <?php else: ?>
                                            <div class="w-full h-full flex items-center justify-center bg-gradient-to-br from-primary/20 to-brand-green/20 text-brand-green text-4xl sm:text-5xl font-black">
                                                <?= htmlspecialchars($user_initial) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <button type="button" onclick="document.getElementById('profileImageInput').click()" class="absolute -bottom-1 -right-1 inline-flex items-center justify-center size-10 rounded-full bg-primary text-white shadow-lg hover:bg-primary/90 transition-colors">
                                        <span class="material-symbols-outlined text-lg">photo_camera</span>
                                    </button>
                                    <input type="file" id="profileImageInput" name="profile_image" class="hidden" accept="image/*" onchange="document.getElementById('profileImageForm').submit()">
                                </div>
                            </form>

                            <div class="min-w-0 text-center sm:text-left">
                                <div class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                                    <h1 class="text-3xl sm:text-4xl font-extrabold tracking-tight text-brand-green"><?= htmlspecialchars($display_name) ?></h1>
                                    <div class="flex flex-wrap items-center justify-center sm:justify-start gap-2">
                                        <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">
                                            <span class="material-symbols-outlined text-sm">alternate_email</span>
                                            <?= htmlspecialchars($user['username']) ?>
                                        </span>
                                        <?php if (!empty($user['is_verified'])): ?>
                                            <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700 border border-blue-200">
                                                <span class="material-symbols-outlined text-sm fill-1">verified</span>
                                                Verified Account
                                            </span>
                                        <?php endif; ?>
                                        <span class="inline-flex items-center gap-1 rounded-full bg-brand-green-light px-3 py-1 text-xs font-bold text-brand-green border border-emerald-200">
                                            <span class="material-symbols-outlined text-sm">donut_small</span>
                                            <?= $profile_completion ?>% complete
                                        </span>
                                    </div>
                                </div>

                                <p class="mt-3 max-w-2xl text-sm sm:text-base text-slate-600">
                                    <?= !empty($user['bio']) ? htmlspecialchars($user['bio']) : 'Complete your profile so buyers and sellers can trust you faster, recognize your campus identity, and connect with confidence.' ?>
                                </p>

                                <div class="mt-4 flex flex-wrap items-center justify-center sm:justify-start gap-2.5">
                                    <span class="inline-flex items-center gap-2 rounded-full bg-slate-50 px-3 py-2 text-xs sm:text-sm text-slate-700 border border-slate-200">
                                        <span class="material-symbols-outlined text-base text-primary">mail</span>
                                        <?= htmlspecialchars($user['email']) ?>
                                    </span>
                                    <?php if (!empty($user['phone'])): ?>
                                        <span class="inline-flex items-center gap-2 rounded-full bg-slate-50 px-3 py-2 text-xs sm:text-sm text-slate-700 border border-slate-200">
                                            <span class="material-symbols-outlined text-base text-primary">call</span>
                                            <?= htmlspecialchars($user['phone']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($user['location'])): ?>
                                        <span class="inline-flex items-center gap-2 rounded-full bg-slate-50 px-3 py-2 text-xs sm:text-sm text-slate-700 border border-slate-200">
                                            <span class="material-symbols-outlined text-base text-primary">location_on</span>
                                            <?= htmlspecialchars($user['location']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($user['university_code'])): ?>
                                        <span class="inline-flex items-center gap-2 rounded-full bg-slate-50 px-3 py-2 text-xs sm:text-sm text-slate-700 border border-slate-200">
                                            <span class="material-symbols-outlined text-base text-primary">school</span>
                                            <?= htmlspecialchars($user['university_code']) ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($joined_display): ?>
                                        <span class="inline-flex items-center gap-2 rounded-full bg-slate-50 px-3 py-2 text-xs sm:text-sm text-slate-700 border border-slate-200">
                                            <span class="material-symbols-outlined text-base text-primary">calendar_today</span>
                                            Joined <?= htmlspecialchars($joined_display) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="w-full lg:w-[280px] shrink-0">
                            <div class="rounded-2xl border border-slate-200 bg-slate-50/80 p-4">
                                <div class="flex items-center justify-between gap-3 mb-3">
                                    <div>
                                        <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400">Profile Strength</p>
                                        <p class="text-2xl font-extrabold text-brand-green"><?= $profile_completion ?>%</p>
                                    </div>
                                    <span class="material-symbols-outlined text-4xl text-primary">shield_person</span>
                                </div>
                                <div class="h-2.5 rounded-full bg-slate-200 overflow-hidden">
                                    <div class="h-full rounded-full bg-gradient-to-r from-brand-green to-primary" style="width: <?= $profile_completion ?>%"></div>
                                </div>
                                <p class="mt-3 text-sm text-slate-600">
                                    <?= empty($missing_items) ? 'Everything important is filled in. Your profile looks great.' : 'Still missing: ' . htmlspecialchars(implode(', ', $missing_items)) . '.' ?>
                                </p>
                            </div>

                            <button onclick="toggleEditMode()" id="editToggleBtn" class="mt-4 w-full inline-flex items-center justify-center gap-2 rounded-2xl bg-primary px-4 py-3 text-sm font-bold text-white hover:bg-primary/90 transition-colors">
                                <span class="material-symbols-outlined text-lg">edit</span>
                                <span>Edit Profile</span>
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <?php if (isset($_SESSION['success'])): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-2xl flex items-center gap-3" id="successMessage">
                <span class="material-symbols-outlined text-emerald-600">check_circle</span>
                <p class="flex-1"><?= htmlspecialchars($_SESSION['success']) ?></p>
                <button onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-800">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <?php unset($_SESSION['success']); endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
            <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-2xl flex items-center gap-3" id="errorMessage">
                <span class="material-symbols-outlined text-red-600">error</span>
                <p class="flex-1"><?= htmlspecialchars($_SESSION['error']) ?></p>
                <button onclick="this.parentElement.remove()" class="text-red-600 hover:text-red-800">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <?php unset($_SESSION['error']); endif; ?>

            <div id="profileForm" class="hidden">
                <form method="POST" class="rounded-3xl border border-slate-200 bg-white shadow-sm p-5 sm:p-6 lg:p-8 space-y-8">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>" />

                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 class="text-2xl font-extrabold text-brand-green">Edit Profile</h2>
                            <p class="text-sm text-slate-500">Update your public details, campus identity, and contact information.</p>
                        </div>
                        <button type="button" onclick="toggleEditMode()" class="inline-flex items-center justify-center gap-2 rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-200 transition-colors">
                            <span class="material-symbols-outlined text-lg">close</span>
                            Close
                        </button>
                    </div>

                    <section class="space-y-4">
                        <div>
                            <h3 class="text-lg font-bold text-text-dark">Personal Information</h3>
                            <p class="text-sm text-slate-500">These details shape how your store and profile appear across CampMart.</p>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-bold text-text-dark mb-2">First Name</label>
                                <input type="text" name="firstname" value="<?= htmlspecialchars($user['firstname'] ?? '') ?>" required class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-colors" />
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-text-dark mb-2">Last Name</label>
                                <input type="text" name="lastname" value="<?= htmlspecialchars($user['lastname'] ?? '') ?>" required class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-colors" />
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-text-dark mb-2">Username</label>
                                <input type="text" name="username" value="<?= htmlspecialchars($user['username'] ?? '') ?>" required class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-colors" placeholder="Choose your unique username" />
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-text-dark mb-2">Email Address</label>
                                <input type="email" name="email" value="<?= htmlspecialchars($user['email'] ?? '') ?>" readonly required class="w-full rounded-2xl border border-slate-200 bg-slate-100 px-4 py-3 text-slate-500 outline-none" />
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-text-dark mb-2">Phone</label>
                                <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-colors" placeholder="Add a contact number" />
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-text-dark mb-2">Location</label>
                                <input type="text" name="location" value="<?= htmlspecialchars($user['location'] ?? '') ?>" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-colors" placeholder="e.g., Main Campus, Akure" />
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-bold text-text-dark mb-2">Bio</label>
                                <textarea name="bio" rows="4" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-colors" placeholder="Tell buyers and collaborators what you do, what you sell, or how you help."><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                            </div>
                        </div>
                    </section>

                    <section class="space-y-4 border-t border-slate-100 pt-8">
                        <div>
                            <h3 class="text-lg font-bold text-text-dark">Academic Details</h3>
                            <p class="text-sm text-slate-500">Add the campus context that makes your profile feel real and trustworthy.</p>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-bold text-text-dark mb-2">University</label>
                                <input type="text" value="<?= htmlspecialchars($user['university_name'] ?? 'Not available') ?>" readonly class="w-full rounded-2xl border border-slate-200 bg-slate-100 px-4 py-3 text-slate-500 outline-none" />
                            </div>
                            <div>
                                <label class="block text-sm font-bold text-text-dark mb-2">Level</label>
                                <select name="level" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-colors">
                                    <option value="">Select level</option>
                                    <?php $current_level = (string) ($user['level'] ?? ''); ?>
                                    <option value="predegree" <?= $current_level === 'predegree' ? 'selected' : '' ?>>Predegree</option>
                                    <option value="100" <?= $current_level === '100' ? 'selected' : '' ?>>100</option>
                                    <option value="200" <?= $current_level === '200' ? 'selected' : '' ?>>200</option>
                                    <option value="300" <?= $current_level === '300' ? 'selected' : '' ?>>300</option>
                                    <option value="400" <?= $current_level === '400' ? 'selected' : '' ?>>400</option>
                                    <option value="500" <?= $current_level === '500' ? 'selected' : '' ?>>500</option>
                                    <option value="600" <?= $current_level === '600' ? 'selected' : '' ?>>600</option>
                                    <option value="700" <?= $current_level === '700' ? 'selected' : '' ?>>700</option>
                                    <option value="800" <?= $current_level === '800' ? 'selected' : '' ?>>800</option>
                                    <option value="pg" <?= $current_level === 'pg' ? 'selected' : '' ?>>PG</option>
                                    <option value="lecturer" <?= $current_level === 'lecturer' ? 'selected' : '' ?>>Lecturer</option>
                                    <option value="non-student" <?= $current_level === 'non-student' ? 'selected' : '' ?>>Non-student</option>
                                </select>
                            </div>
                            <div class="md:col-span-2">
                                <label class="block text-sm font-bold text-text-dark mb-2">Department</label>
                                <input type="text" name="department" value="<?= htmlspecialchars($user['department'] ?? '') ?>" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-colors" placeholder="e.g., Computer Science" />
                            </div>
                        </div>
                    </section>

                    <div class="flex flex-col sm:flex-row gap-3 pt-2">
                        <button type="submit" name="UpdateProfile" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-primary px-6 py-3 text-sm font-bold text-white hover:bg-primary/90 transition-colors">
                            <span class="material-symbols-outlined text-lg">save</span>
                            Save Changes
                        </button>
                        <button type="button" onclick="toggleEditMode()" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-slate-100 px-6 py-3 text-sm font-bold text-slate-700 hover:bg-slate-200 transition-colors">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>

            <section class="grid grid-cols-2 xl:grid-cols-4 gap-4">
                <div class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 shadow-sm">
                    <div class="flex items-center justify-between mb-3">
                        <span class="inline-flex size-11 items-center justify-center rounded-2xl bg-primary/10 text-primary">
                            <span class="material-symbols-outlined">shopping_bag</span>
                        </span>
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-[0.18em]">Products</span>
                    </div>
                    <p class="text-2xl sm:text-3xl font-extrabold text-text-dark"><?= number_format($total_products) ?></p>
                    <p class="text-sm text-slate-500 mt-1">Items you have listed</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 shadow-sm">
                    <div class="flex items-center justify-between mb-3">
                        <span class="inline-flex size-11 items-center justify-center rounded-2xl bg-brand-green/10 text-brand-green">
                            <span class="material-symbols-outlined">work</span>
                        </span>
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-[0.18em]">Services</span>
                    </div>
                    <p class="text-2xl sm:text-3xl font-extrabold text-text-dark"><?= number_format($total_services) ?></p>
                    <p class="text-sm text-slate-500 mt-1">Service offers you run</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 shadow-sm">
                    <div class="flex items-center justify-between mb-3">
                        <span class="inline-flex size-11 items-center justify-center rounded-2xl bg-blue-50 text-blue-600">
                            <span class="material-symbols-outlined">receipt_long</span>
                        </span>
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-[0.18em]">Orders</span>
                    </div>
                    <p class="text-2xl sm:text-3xl font-extrabold text-text-dark"><?= number_format($total_orders) ?></p>
                    <p class="text-sm text-slate-500 mt-1">Transactions completed or in motion</p>
                </div>
                <div class="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5 shadow-sm">
                    <div class="flex items-center justify-between mb-3">
                        <span class="inline-flex size-11 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600">
                            <span class="material-symbols-outlined">payments</span>
                        </span>
                        <span class="text-xs font-bold text-slate-400 uppercase tracking-[0.18em]">Revenue</span>
                    </div>
                    <p class="text-2xl sm:text-3xl font-extrabold text-text-dark">NGN <?= number_format((float) $total_revenue, 2) ?></p>
                    <p class="text-sm text-slate-500 mt-1">Completed sales income</p>
                </div>
            </section>

            <section class="grid grid-cols-1 xl:grid-cols-[1.15fr_0.85fr] gap-6">
                <div class="space-y-6">
                    <div class="rounded-3xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
                        <div class="flex items-start justify-between gap-4 mb-5">
                            <div>
                                <h2 class="text-xl font-extrabold text-brand-green">Profile Snapshot</h2>
                                <p class="text-sm text-slate-500 mt-1">The details buyers and classmates will notice first.</p>
                            </div>
                            <span class="inline-flex items-center gap-2 rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600">
                                <span class="material-symbols-outlined text-sm">visibility</span>
                                Public view
                            </span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400 mb-2">Full Name</p>
                                <p class="font-semibold text-slate-800"><?= htmlspecialchars($display_name) ?></p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400 mb-2">Username</p>
                                <p class="font-semibold text-slate-800">@<?= htmlspecialchars($user['username']) ?></p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400 mb-2">Campus</p>
                                <p class="font-semibold text-slate-800"><?= htmlspecialchars($user['university_name'] ?? 'Not added yet') ?></p>
                                <?php if (!empty($user['university_location'])): ?>
                                    <p class="text-xs text-slate-500 mt-1"><?= htmlspecialchars($user['university_location']) ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400 mb-2">Department</p>
                                <p class="font-semibold text-slate-800"><?= htmlspecialchars($user['department'] ?? 'Add your department') ?></p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400 mb-2">Level</p>
                                <p class="font-semibold text-slate-800"><?= htmlspecialchars($level_display ?? 'Select your level') ?></p>
                            </div>
                            <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                <p class="text-xs font-bold uppercase tracking-[0.18em] text-slate-400 mb-2">Location</p>
                                <p class="font-semibold text-slate-800"><?= htmlspecialchars($user['location'] ?? 'Add your location') ?></p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
                        <div class="mb-5">
                            <h2 class="text-xl font-extrabold text-brand-green">Account Verification</h2>
                            <p class="text-sm text-slate-500 mt-1">Verification helps you earn trust faster and unlocks a safer marketplace experience.</p>
                        </div>

                        <div class="space-y-4">
                            <div class="rounded-2xl border p-4 <?= $email_verified ? 'bg-emerald-50 border-emerald-200' : 'bg-yellow-50 border-yellow-200' ?>">
                                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                    <div class="flex items-start gap-3">
                                        <span class="material-symbols-outlined <?= $email_verified ? 'text-emerald-600' : 'text-yellow-600' ?>">
                                            <?= $email_verified ? 'check_circle' : 'warning' ?>
                                        </span>
                                        <div>
                                            <p class="font-semibold <?= $email_verified ? 'text-emerald-900' : 'text-yellow-900' ?>">Email Verification</p>
                                            <p class="text-sm <?= $email_verified ? 'text-emerald-700' : 'text-yellow-700' ?>">
                                                <?= $email_verified ? 'Your email is verified and trusted on the platform.' : 'Please verify your email address to secure your account and improve buyer confidence.' ?>
                                            </p>
                                        </div>
                                    </div>
                                    <?php if (!$email_verified): ?>
                                        <form method="POST" class="shrink-0">
                                            <input type="hidden" name="ResendVerification" value="1">
                                            <button type="submit" class="w-full sm:w-auto rounded-xl bg-primary px-4 py-2.5 text-sm font-bold text-white hover:bg-primary/90 transition-colors">
                                                Verify Now
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-2 rounded-xl bg-white/70 px-3 py-2 text-xs font-bold text-emerald-700 border border-emerald-200">
                                            <span class="material-symbols-outlined text-sm">task_alt</span>
                                            Completed
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                    <div class="flex items-center gap-3 mb-2">
                                        <span class="material-symbols-outlined <?= $phone_verified ? 'text-emerald-600' : 'text-slate-400' ?>">
                                            <?= $phone_verified ? 'check_circle' : 'radio_button_unchecked' ?>
                                        </span>
                                        <p class="font-semibold text-slate-800">Phone Verification</p>
                                    </div>
                                    <p class="text-sm text-slate-500">
                                        <?= $phone_verified ? 'Your phone number is verified.' : 'Add and verify your phone number for extra trust and faster contact.' ?>
                                    </p>
                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-slate-50 p-4">
                                    <div class="flex items-center gap-3 mb-2">
                                        <span class="material-symbols-outlined <?= $is_verified_seller ? 'text-blue-600 fill-1' : 'text-slate-400' ?>">
                                            <?= $is_verified_seller ? 'verified' : 'radio_button_unchecked' ?>
                                        </span>
                                        <p class="font-semibold text-slate-800">Verified Seller Badge</p>
                                    </div>
                                    <p class="text-sm text-slate-500">
                                        <?= $is_verified_seller ? 'You currently carry the verified seller badge.' : 'Seller verification can help your listings feel safer and more credible.' ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="space-y-6">
                    <div class="rounded-3xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
                        <div class="mb-5">
                            <h2 class="text-xl font-extrabold text-brand-green">Completion Checklist</h2>
                            <p class="text-sm text-slate-500 mt-1">Small improvements here make your public profile look much stronger on mobile and desktop.</p>
                        </div>
                        <div class="space-y-3">
                            <?php foreach ($profile_checks as $label => $is_complete): ?>
                                <div class="flex items-center justify-between gap-3 rounded-2xl border px-4 py-3 <?= $is_complete ? 'border-emerald-200 bg-emerald-50' : 'border-slate-200 bg-slate-50' ?>">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <span class="material-symbols-outlined <?= $is_complete ? 'text-emerald-600' : 'text-slate-400' ?>">
                                            <?= $is_complete ? 'check_circle' : 'radio_button_unchecked' ?>
                                        </span>
                                        <p class="text-sm font-semibold <?= $is_complete ? 'text-emerald-900' : 'text-slate-700' ?>"><?= htmlspecialchars($label) ?></p>
                                    </div>
                                    <span class="text-xs font-bold uppercase tracking-wide <?= $is_complete ? 'text-emerald-700' : 'text-slate-500' ?>">
                                        <?= $is_complete ? 'Done' : 'Pending' ?>
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
                        <div class="flex items-center justify-between gap-4 mb-4">
                            <div>
                                <h2 class="text-xl font-extrabold text-brand-green">Security Settings</h2>
                                <p class="text-sm text-slate-500 mt-1">Change your password any time to keep your account protected.</p>
                            </div>
                            <button onclick="togglePasswordForm()" id="passwordToggleBtn" class="shrink-0 inline-flex items-center justify-center gap-2 rounded-xl bg-slate-100 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-200 transition-colors">
                                <span class="material-symbols-outlined text-lg">lock</span>
                                <span>Change Password</span>
                            </button>
                        </div>

                        <div id="passwordForm" class="hidden">
                            <form method="POST" class="space-y-4 border-t border-slate-100 pt-5">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>" />

                                <div>
                                    <label class="block text-sm font-bold text-text-dark mb-2">Current Password</label>
                                    <div class="relative">
                                        <input type="password" id="current_password" name="current_password" required class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 pr-12 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-colors" placeholder="Enter your current password">
                                        <button type="button" onclick="togglePasswordVisibility('current_password')" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                                            <span class="material-symbols-outlined text-xl">visibility</span>
                                        </button>
                                    </div>
                                </div>

                                <div>
                                    <label class="block text-sm font-bold text-text-dark mb-2">New Password</label>
                                    <div class="relative">
                                        <input type="password" id="new_password" name="new_password" required minlength="8" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 pr-12 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-colors" placeholder="Enter a new password (minimum 8 characters)" oninput="checkPasswordMatch()">
                                        <button type="button" onclick="togglePasswordVisibility('new_password')" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                                            <span class="material-symbols-outlined text-xl">visibility</span>
                                        </button>
                                    </div>
                                    <p class="mt-1 text-xs text-slate-500">Use at least 8 characters for better account protection.</p>
                                </div>

                                <div>
                                    <label class="block text-sm font-bold text-text-dark mb-2">Confirm New Password</label>
                                    <div class="relative">
                                        <input type="password" id="confirm_password" name="confirm_password" required minlength="8" class="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 pr-12 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none transition-colors" placeholder="Re-enter your new password" oninput="checkPasswordMatch()">
                                        <button type="button" onclick="togglePasswordVisibility('confirm_password')" class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600">
                                            <span class="material-symbols-outlined text-xl">visibility</span>
                                        </button>
                                    </div>
                                    <p id="passwordMatchMessage" class="text-xs mt-1 hidden"></p>
                                </div>

                                <div class="flex flex-col sm:flex-row gap-3 pt-2">
                                    <button type="submit" name="ChangePassword" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-primary px-6 py-3 text-sm font-bold text-white hover:bg-primary/90 transition-colors">
                                        <span class="material-symbols-outlined text-lg">lock_reset</span>
                                        Update Password
                                    </button>
                                    <button type="button" onclick="togglePasswordForm()" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-slate-100 px-6 py-3 text-sm font-bold text-slate-700 hover:bg-slate-200 transition-colors">
                                        Cancel
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-white p-5 sm:p-6 shadow-sm">
                        <h2 class="text-xl font-extrabold text-brand-green mb-4">Quick Tips</h2>
                        <div class="space-y-3 text-sm text-slate-600">
                            <div class="rounded-2xl bg-slate-50 border border-slate-200 px-4 py-3">
                                Add a short bio that tells people what you sell or what services you offer.
                            </div>
                            <div class="rounded-2xl bg-slate-50 border border-slate-200 px-4 py-3">
                                Keep your phone number and location current so transactions move faster.
                            </div>
                            <div class="rounded-2xl bg-slate-50 border border-slate-200 px-4 py-3">
                                A complete profile usually makes your listings feel more trustworthy on campus.
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    </main>

    <script>
        setTimeout(() => {
            const successMsg = document.getElementById('successMessage');
            const errorMsg = document.getElementById('errorMessage');
            if (successMsg) successMsg.remove();
            if (errorMsg) errorMsg.remove();
        }, 5000);

        function toggleEditMode() {
            const form = document.getElementById('profileForm');
            const btn = document.getElementById('editToggleBtn');

            if (form.classList.contains('hidden')) {
                form.classList.remove('hidden');
                btn.innerHTML = '<span class="material-symbols-outlined text-lg">close</span><span>Close Editor</span>';
                form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            } else {
                form.classList.add('hidden');
                btn.innerHTML = '<span class="material-symbols-outlined text-lg">edit</span><span>Edit Profile</span>';
            }
        }

        function togglePasswordForm() {
            const form = document.getElementById('passwordForm');
            const btn = document.getElementById('passwordToggleBtn');

            if (form.classList.contains('hidden')) {
                form.classList.remove('hidden');
                btn.innerHTML = '<span class="material-symbols-outlined text-lg">close</span><span>Close</span>';
                btn.classList.remove('bg-slate-100', 'text-slate-700');
                btn.classList.add('bg-red-100', 'text-red-700');
            } else {
                form.classList.add('hidden');
                btn.innerHTML = '<span class="material-symbols-outlined text-lg">lock</span><span>Change Password</span>';
                btn.classList.remove('bg-red-100', 'text-red-700');
                btn.classList.add('bg-slate-100', 'text-slate-700');
                document.getElementById('passwordForm').querySelector('form').reset();
                document.getElementById('passwordMatchMessage').classList.add('hidden');
            }
        }

        function togglePasswordVisibility(fieldId) {
            const field = document.getElementById(fieldId);
            const button = field.nextElementSibling;
            const icon = button.querySelector('.material-symbols-outlined');

            if (field.type === 'password') {
                field.type = 'text';
                icon.textContent = 'visibility_off';
            } else {
                field.type = 'password';
                icon.textContent = 'visibility';
            }
        }

        function checkPasswordMatch() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const message = document.getElementById('passwordMatchMessage');

            if (confirmPassword.length === 0) {
                message.classList.add('hidden');
                return;
            }

            if (newPassword === confirmPassword) {
                message.textContent = 'Passwords match';
                message.classList.remove('text-red-600');
                message.classList.add('text-emerald-600');
                message.classList.remove('hidden');
            } else {
                message.textContent = 'Passwords do not match';
                message.classList.remove('text-emerald-600');
                message.classList.add('text-red-600');
                message.classList.remove('hidden');
            }
        }

        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        function toggleSidebar() {
            if (sidebar) sidebar.classList.toggle('-translate-x-full');
            if (sidebarOverlay) sidebarOverlay.classList.toggle('hidden');
            document.body.classList.toggle('overflow-hidden');
        }

        if (menuToggle) menuToggle.addEventListener('click', toggleSidebar);
        if (sidebarOverlay) sidebarOverlay.addEventListener('click', toggleSidebar);

        if (sidebar) {
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
                if (sidebarOverlay) sidebarOverlay.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }
        });
    </script>
</body>
</html>
