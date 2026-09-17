<?php
session_start();
require_once 'includes/controller.php';

$user_query = "SELECT role FROM users WHERE id = ?";
$stmt = $db->prepare($user_query);
$stmt->bind_param("i", $userId);
$stmt->execute();
$user_result = $stmt->get_result();
$user = $user_result->fetch_assoc();

if (!$user || ($user['role'] !== 'admin' && $user['role'] !== 'superadmin')) {
    header('Location: index.php');
    exit;
}

$success_message = '';
$error_message = '';

// Handle general settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_general_settings'])) {
    $site_name = trim($_POST['site_name'] ?? '');
    $site_tagline = trim($_POST['site_tagline'] ?? '');
    $support_email = trim($_POST['support_email'] ?? '');
    $support_phone = trim($_POST['support_phone'] ?? '');
    $default_campus = trim($_POST['default_campus'] ?? '');
    $meta_description = trim($_POST['meta_description'] ?? '');
    
    // Validate inputs
    if (empty($site_name)) {
        $error_message .= 'Site name is required. ';
    }
    if (!empty($support_email) && !filter_var($support_email, FILTER_VALIDATE_EMAIL)) {
        $error_message .= 'Invalid email address. ';
    }
    
    if (empty($error_message)) {
        $settings_to_update = [
            'site_name' => ['value' => $site_name, 'desc' => 'Website name'],
            'site_tagline' => ['value' => $site_tagline, 'desc' => 'Website tagline'],
            'support_email' => ['value' => $support_email, 'desc' => 'Support email address'],
            'support_phone' => ['value' => $support_phone, 'desc' => 'Support phone number'],
            'default_campus' => ['value' => $default_campus, 'desc' => 'Default campus/university'],
            'meta_description' => ['value' => $meta_description, 'desc' => 'Default meta description']
        ];
        
        $update_success = true;
        foreach ($settings_to_update as $key => $data) {
            $check_query = "SELECT id FROM system_settings WHERE setting_key = ?";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bind_param("s", $key);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $update_stmt = $db->prepare("UPDATE system_settings SET setting_value = ?, updated_by = ? WHERE setting_key = ?");
                $update_stmt->bind_param("sis", $data['value'], $userId, $key);
                if (!$update_stmt->execute()) {
                    $update_success = false;
                }
            } else {
                $insert_stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_type, description, is_public, updated_by) VALUES (?, ?, 'string', ?, 1, ?)");
                $insert_stmt->bind_param("sssi", $key, $data['value'], $data['desc'], $userId);
                if (!$insert_stmt->execute()) {
                    $update_success = false;
                }
            }
        }
        
        if ($update_success) {
            $success_message .= 'General settings updated successfully. ';
        } else {
            $error_message .= 'Failed to update some settings. ';
        }
    }
}

// Handle toggle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_toggles'])) {
    $toggle_settings = [
        'maintenance_mode' => isset($_POST['maintenance_mode']) ? '1' : '0',
        'allow_signups' => isset($_POST['allow_signups']) ? '1' : '0',
        'require_verification' => isset($_POST['require_verification']) ? '1' : '0',
        'enable_chat' => isset($_POST['enable_chat']) ? '1' : '0',
        'auto_approve_listings' => isset($_POST['auto_approve_listings']) ? '1' : '0',
        'email_notifications' => isset($_POST['email_notifications']) ? '1' : '0',
        'allow_guest_browsing' => isset($_POST['allow_guest_browsing']) ? '1' : '0',
        'enable_wishlist' => isset($_POST['enable_wishlist']) ? '1' : '0',
        'payment_option_paystack' => isset($_POST['payment_option_paystack']) ? '1' : '0',
        'payment_option_flutterwave' => isset($_POST['payment_option_flutterwave']) ? '1' : '0',
        'payment_option_pod' => isset($_POST['payment_option_pod']) ? '1' : '0'
    ];
    
    $toggle_descriptions = [
        'maintenance_mode' => 'Enable maintenance mode',
        'allow_signups' => 'Allow new user signups',
        'require_verification' => 'Require email verification for listings',
        'enable_chat' => 'Enable buyer-seller chat',
        'auto_approve_listings' => 'Auto-approve listings without review',
        'email_notifications' => 'Send email notifications for orders',
        'allow_guest_browsing' => 'Allow guest browsing without login',
        'enable_wishlist' => 'Enable wishlist/bookmark feature',
        'payment_option_paystack' => 'Enable Paystack online payment gateway',
        'payment_option_flutterwave' => 'Enable Flutterwave online payment gateway',
        'payment_option_pod' => 'Enable Pay on Delivery (Physical Store Pickup)'
    ];
    
    $update_success = true;
    foreach ($toggle_settings as $key => $value) {
        $check_query = "SELECT id FROM system_settings WHERE setting_key = ?";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bind_param("s", $key);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $update_stmt = $db->prepare("UPDATE system_settings SET setting_value = ?, updated_by = ? WHERE setting_key = ?");
            $update_stmt->bind_param("sis", $value, $userId, $key);
            if (!$update_stmt->execute()) {
                $update_success = false;
            }
        } else {
            $insert_stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_type, description, is_public, updated_by) VALUES (?, ?, 'boolean', ?, 0, ?)");
            $insert_stmt->bind_param("sssi", $key, $value, $toggle_descriptions[$key], $userId);
            if (!$insert_stmt->execute()) {
                $update_success = false;
            }
        }
    }
    
    if ($update_success) {
        $success_message .= 'Toggle settings updated successfully. ';
    } else {
        $error_message .= 'Failed to update some toggle settings. ';
    }
}

// Handle payment gateway API key update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_payment_keys'])) {
    $payment_keys = [
        'paystack_public_key' => ['value' => trim($_POST['paystack_public_key'] ?? ''), 'desc' => 'Paystack public key'],
        'paystack_secret_key' => ['value' => trim($_POST['paystack_secret_key'] ?? ''), 'desc' => 'Paystack secret key'],
        'flutterwave_public_key' => ['value' => trim($_POST['flutterwave_public_key'] ?? ''), 'desc' => 'Flutterwave public key'],
        'flutterwave_secret_key' => ['value' => trim($_POST['flutterwave_secret_key'] ?? ''), 'desc' => 'Flutterwave secret key'],
        'flutterwave_encryption_key' => ['value' => trim($_POST['flutterwave_encryption_key'] ?? ''), 'desc' => 'Flutterwave encryption key']
    ];

    $update_success = true;
    foreach ($payment_keys as $key => $data) {
        $check_query = "SELECT id FROM system_settings WHERE setting_key = ?";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bind_param("s", $key);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows > 0) {
            $update_stmt = $db->prepare("UPDATE system_settings SET setting_value = ?, updated_by = ? WHERE setting_key = ?");
            $update_stmt->bind_param("sis", $data['value'], $userId, $key);
            if (!$update_stmt->execute()) {
                $update_success = false;
            }
        } else {
            $insert_stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_type, description, is_public, updated_by) VALUES (?, ?, 'string', ?, 0, ?)");
            $insert_stmt->bind_param("sssi", $key, $data['value'], $data['desc'], $userId);
            if (!$insert_stmt->execute()) {
                $update_success = false;
            }
        }
    }

    if ($update_success) {
        $success_message .= 'Payment gateway API keys updated successfully. ';
    } else {
        $error_message .= 'Failed to update some payment gateway keys. ';
    }
}

// Handle image upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_images'])) {
    $upload_dir = 'uploads/';
    
    // Ensure uploads directory exists
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Handle social preview image upload
    if (isset($_FILES['social_preview']) && $_FILES['social_preview']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['social_preview'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
        
        if (in_array($file['type'], $allowed_types)) {
            if ($file['size'] <= 5 * 1024 * 1024) { // 5MB limit
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'campmart-social-preview.' . $extension;
                $filepath = $upload_dir . $filename;
                
                // Delete old file if exists
                $old_files = glob($upload_dir . 'campmart-social-preview.*');
                foreach ($old_files as $old_file) {
                    if (file_exists($old_file)) {
                        unlink($old_file);
                    }
                }
                
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    // Update or insert setting in database
                    $check_query = "SELECT id FROM system_settings WHERE setting_key = 'social_preview_image'";
                    $check_result = $db->query($check_query);
                    
                    if ($check_result->num_rows > 0) {
                        $update_stmt = $db->prepare("UPDATE system_settings SET setting_value = ?, updated_by = ? WHERE setting_key = 'social_preview_image'");
                        $update_stmt->bind_param("si", $filepath, $userId);
                        $update_stmt->execute();
                    } else {
                        $insert_stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_type, description, is_public, updated_by) VALUES ('social_preview_image', ?, 'string', 'Social media preview image', 1, ?)");
                        $insert_stmt->bind_param("si", $filepath, $userId);
                        $insert_stmt->execute();
                    }
                    
                    $success_message .= 'Social preview image uploaded successfully. ';
                } else {
                    $error_message .= 'Failed to upload social preview image. ';
                }
            } else {
                $error_message .= 'Social preview image must be less than 5MB. ';
            }
        } else {
            $error_message .= 'Social preview image must be JPEG, PNG, or WebP. ';
        }
    }
    
    // Handle logo upload
    if (isset($_FILES['logo_image']) && $_FILES['logo_image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['logo_image'];
        $allowed_types = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp', 'image/svg+xml'];
        
        if (in_array($file['type'], $allowed_types)) {
            if ($file['size'] <= 2 * 1024 * 1024) { // 2MB limit
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'campmart-logo.' . $extension;
                $filepath = $upload_dir . $filename;
                
                // Delete old file if exists
                $old_files = glob($upload_dir . 'campmart-logo.*');
                foreach ($old_files as $old_file) {
                    if (file_exists($old_file)) {
                        unlink($old_file);
                    }
                }
                
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    // Update or insert setting in database
                    $check_query = "SELECT id FROM system_settings WHERE setting_key = 'site_logo'";
                    $check_result = $db->query($check_query);
                    
                    if ($check_result->num_rows > 0) {
                        $update_stmt = $db->prepare("UPDATE system_settings SET setting_value = ?, updated_by = ? WHERE setting_key = 'site_logo'");
                        $update_stmt->bind_param("si", $filepath, $userId);
                        $update_stmt->execute();
                    } else {
                        $insert_stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_type, description, is_public, updated_by) VALUES ('site_logo', ?, 'string', 'Site logo image', 1, ?)");
                        $insert_stmt->bind_param("si", $filepath, $userId);
                        $insert_stmt->execute();
                    }
                    
                    $success_message .= 'Logo uploaded successfully. ';
                } else {
                    $error_message .= 'Failed to upload logo. ';
                }
            } else {
                $error_message .= 'Logo must be less than 2MB. ';
            }
        } else {
            $error_message .= 'Logo must be JPEG, PNG, WebP, or SVG. ';
        }
    }
}

// Fetch current settings
$settings = [];
$settings_query = "SELECT setting_key, setting_value FROM system_settings WHERE setting_key IN ('social_preview_image', 'site_logo', 'site_name', 'site_tagline', 'support_email', 'support_phone', 'default_campus', 'meta_description', 'maintenance_mode', 'allow_signups', 'require_verification', 'enable_chat', 'auto_approve_listings', 'email_notifications', 'allow_guest_browsing', 'enable_wishlist', 'payment_option_paystack', 'payment_option_flutterwave', 'payment_option_pod', 'paystack_public_key', 'paystack_secret_key', 'flutterwave_public_key', 'flutterwave_secret_key', 'flutterwave_encryption_key')";
$settings_result = $db->query($settings_query);
while ($row = $settings_result->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$current_social_preview = $settings['social_preview_image'] ?? 'uploads/campmart-social-preview.jpg';
$current_logo = $settings['site_logo'] ?? 'uploads/campmart-logo.png';

// General settings with defaults
$site_name = $settings['site_name'] ?? 'CampMart';
$site_tagline = $settings['site_tagline'] ?? 'The Premium Campus Marketplace';
$support_email = $settings['support_email'] ?? 'support@campmart.ng';
$support_phone = $settings['support_phone'] ?? '+234 800 000 0000';
$default_campus = $settings['default_campus'] ?? 'FUTA';
$meta_description = $settings['meta_description'] ?? 'Marketplace for students to buy, sell, and swap items on campus.';

// Toggle settings with defaults
$maintenance_mode = ($settings['maintenance_mode'] ?? '0') == '1';
$allow_signups = ($settings['allow_signups'] ?? '1') == '1';
$require_verification = ($settings['require_verification'] ?? '1') == '1';
$enable_chat = ($settings['enable_chat'] ?? '1') == '1';
$auto_approve_listings = ($settings['auto_approve_listings'] ?? '0') == '1';
$email_notifications = ($settings['email_notifications'] ?? '1') == '1';
$allow_guest_browsing = ($settings['allow_guest_browsing'] ?? '1') == '1';
$enable_wishlist = ($settings['enable_wishlist'] ?? '1') == '1';

// Payment option toggles
$payment_option_paystack = ($settings['payment_option_paystack'] ?? '0') == '1';
$payment_option_flutterwave = ($settings['payment_option_flutterwave'] ?? '0') == '1';
$payment_option_pod = ($settings['payment_option_pod'] ?? '1') == '1';

// Payment gateway API keys
$paystack_public_key = $settings['paystack_public_key'] ?? '';
$paystack_secret_key = $settings['paystack_secret_key'] ?? '';
$flutterwave_public_key = $settings['flutterwave_public_key'] ?? '';
$flutterwave_secret_key = $settings['flutterwave_secret_key'] ?? '';
$flutterwave_encryption_key = $settings['flutterwave_encryption_key'] ?? '';

$notice = 'All settings are now fully functional and saved to the database.';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Site Settings - CampMart Admin</title>
    <script src="tailwind34.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" />
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#f48c25',
                        secondary: '#FF6B35',
                        accent: '#FFE66D',
                        slate: {
                            50: '#f8fafc',
                            100: '#e2e8f0',
                            200: '#cbd5e1',
                            300: '#94a3b8',
                            500: '#64748b',
                            700: '#334155',
                        },
                        'brand-green': '#064E3B',
                        'background-main': '#F9FAFB',
                        'surface-white': '#FFFFFF',
                        'text-dark': '#1F2937'
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
        <div class="max-w-6xl mx-auto space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-brand-green">Site Settings</h1>
                    <p class="text-sm text-slate-500 mt-1">Manage global configuration for CampMart.</p>
                </div>
                
            </div>

            <div class="mb-2 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-amber-800 flex items-center gap-2">
                <span class="material-symbols-outlined">info</span>
                <span><?= htmlspecialchars($notice) ?></span>
            </div>

            <?php if ($success_message): ?>
            <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-green-800 flex items-center gap-2">
                <span class="material-symbols-outlined">check_circle</span>
                <span><?= htmlspecialchars($success_message) ?></span>
            </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-800 flex items-center gap-2">
                <span class="material-symbols-outlined">error</span>
                <span><?= htmlspecialchars($error_message) ?></span>
            </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- General Settings -->
                <form method="POST" class="lg:col-span-2 bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <h2 class="text-xl font-semibold text-text-dark">General</h2>
                            <p class="text-sm text-slate-500">Brand details and primary contact.</p>
                        </div>
                        <span class="text-xs px-2 py-1 rounded-full bg-green-100 text-green-600">Active</span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Site Name</label>
                            <input type="text" name="site_name" value="<?= htmlspecialchars($site_name) ?>" required class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:ring-2 focus:ring-primary/30 focus:border-primary" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Site Tagline</label>
                            <input type="text" name="site_tagline" value="<?= htmlspecialchars($site_tagline) ?>" class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:ring-2 focus:ring-primary/30 focus:border-primary" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Support Email</label>
                            <input type="email" name="support_email" value="<?= htmlspecialchars($support_email) ?>" class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:ring-2 focus:ring-primary/30 focus:border-primary" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Support Phone</label>
                            <input type="text" name="support_phone" value="<?= htmlspecialchars($support_phone) ?>" class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:ring-2 focus:ring-primary/30 focus:border-primary" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Default Campus</label>
                            <input type="text" name="default_campus" value="<?= htmlspecialchars($default_campus) ?>" class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:ring-2 focus:ring-primary/30 focus:border-primary" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-slate-700 mb-2">Meta Description</label>
                        <textarea name="meta_description" rows="3" class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:ring-2 focus:ring-primary/30 focus:border-primary"><?= htmlspecialchars($meta_description) ?></textarea>
                    </div>

                    <div class="flex justify-end pt-4 border-t border-slate-200">
                        <button type="submit" name="update_general_settings" class="px-6 py-2.5 bg-brand-green text-white rounded-lg font-semibold shadow-sm hover:bg-brand-green/90 transition-colors flex items-center gap-2">
                            <span class="material-symbols-outlined text-sm">save</span>
                            Update General Settings
                        </button>
                    </div>
                </form>

                <!-- Toggles -->
                <form method="POST" id="toggleForm" class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-4">
                    <div class="flex items-center justify-between mb-3">
                        <div>
                            <h3 class="text-lg font-semibold text-text-dark">Feature Toggles</h3>
                            <p class="text-xs text-slate-500">Changes save automatically</p>
                        </div>
                        <span class="text-xs px-2 py-1 rounded-full bg-green-100 text-green-600">Active</span>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-semibold text-text-dark">Maintenance Mode</p>
                            <p class="text-xs text-slate-500">Temporarily hide the marketplace.</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="maintenance_mode" <?= $maintenance_mode ? 'checked' : '' ?> class="sr-only peer toggle-switch">
                            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-primary after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
                        </label>
                    </div>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-semibold text-text-dark">Allow New Signups</p>
                            <p class="text-xs text-slate-500">Enable/disable account creation.</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="allow_signups" <?= $allow_signups ? 'checked' : '' ?> class="sr-only peer toggle-switch">
                            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-primary after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
                        </label>
                    </div>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-semibold text-text-dark">Require Verification</p>
                            <p class="text-xs text-slate-500">Only verified students can list items.</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="require_verification" <?= $require_verification ? 'checked' : '' ?> class="sr-only peer toggle-switch">
                            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-primary after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
                        </label>
                    </div>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-semibold text-text-dark">Enable Buyer-Seller Chat</p>
                            <p class="text-xs text-slate-500">Allow direct messaging between users.</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="enable_chat" <?= $enable_chat ? 'checked' : '' ?> class="sr-only peer toggle-switch">
                            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-primary after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
                        </label>
                    </div>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-semibold text-text-dark">Auto-Approve Listings</p>
                            <p class="text-xs text-slate-500">Publish listings instantly without admin review.</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="auto_approve_listings" <?= $auto_approve_listings ? 'checked' : '' ?> class="sr-only peer toggle-switch">
                            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-primary after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
                        </label>
                    </div>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-semibold text-text-dark">Email Order Notifications</p>
                            <p class="text-xs text-slate-500">Send email alerts for purchases and inquiries.</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="email_notifications" <?= $email_notifications ? 'checked' : '' ?> class="sr-only peer toggle-switch">
                            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-primary after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
                        </label>
                    </div>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-semibold text-text-dark">Allow Guest Browsing</p>
                            <p class="text-xs text-slate-500">Let visitors browse listings before signup.</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="allow_guest_browsing" <?= $allow_guest_browsing ? 'checked' : '' ?> class="sr-only peer toggle-switch">
                            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-primary after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
                        </label>
                    </div>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-semibold text-text-dark">Enable Wishlist</p>
                            <p class="text-xs text-slate-500">Allow users to save products for later.</p>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="enable_wishlist" <?= $enable_wishlist ? 'checked' : '' ?> class="sr-only peer toggle-switch">
                            <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-primary after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
                        </label>
                    </div>

                    <div class="pt-3 border-t border-slate-200">
                        <p class="text-sm font-bold text-text-dark mb-3">Payment Options</p>

                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-sm font-semibold text-text-dark">Pay on Delivery (Physical Pickup)</p>
                                <p class="text-xs text-slate-500">Let buyers pay with cash at physical store pickup.</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="payment_option_pod" <?= $payment_option_pod ? 'checked' : '' ?> class="sr-only peer toggle-switch">
                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-primary after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
                            </label>
                        </div>

                        <div class="flex items-center justify-between mt-3">
                            <div>
                                <p class="text-sm font-semibold text-text-dark">Paystack</p>
                                <p class="text-xs text-slate-500">Enable online payments via Paystack (card, USSD, bank transfer).</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="payment_option_paystack" <?= $payment_option_paystack ? 'checked' : '' ?> class="sr-only peer toggle-switch">
                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-primary after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
                            </label>
                        </div>

                        <div class="flex items-center justify-between mt-3">
                            <div>
                                <p class="text-sm font-semibold text-text-dark">Flutterwave</p>
                                <p class="text-xs text-slate-500">Enable online payments via Flutterwave (card, USSD, mobile money).</p>
                            </div>
                            <label class="relative inline-flex items-center cursor-pointer">
                                <input type="checkbox" name="payment_option_flutterwave" <?= $payment_option_flutterwave ? 'checked' : '' ?> class="sr-only peer toggle-switch">
                                <div class="w-11 h-6 bg-slate-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:bg-primary after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:rounded-full after:h-5 after:w-5 after:transition-all"></div>
                            </label>
                        </div>
                    </div>
                    <input type="hidden" name="update_toggles" value="1">
                </form>
            </div>



            <!-- SEO Images Section -->
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-text-dark">SEO & Social Media Images</h2>
                        <p class="text-sm text-slate-500">Upload images for social media sharing and search engines.</p>
                    </div>
                    <span class="text-xs px-2 py-1 rounded-full bg-green-100 text-green-600">Active</span>
                </div>

                <form method="POST" enctype="multipart/form-data" class="space-y-5">
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Social Preview Image -->
                        <div class="space-y-3">
                            <label class="block text-sm font-medium text-slate-700">Social Media Preview Image</label>
                            <p class="text-xs text-slate-500 mb-2">Used when your site is shared on Facebook, Twitter, WhatsApp, etc. Recommended size: 1200x630px</p>
                            
                            <?php if (file_exists($current_social_preview)): ?>
                            <div class="mb-3 rounded-lg border border-slate-200 p-3">
                                <p class="text-xs font-medium text-slate-600 mb-2">Current Image:</p>
                                <img src="<?= htmlspecialchars($current_social_preview) ?>" alt="Current social preview" class="w-full h-32 object-cover rounded-lg border border-slate-200" />
                                <p class="text-xs text-slate-500 mt-2"><?= htmlspecialchars($current_social_preview) ?></p>
                            </div>
                            <?php endif; ?>

                            <div class="flex items-center gap-3">
                                <label class="flex-1 cursor-pointer">
                                    <div class="flex items-center justify-center gap-2 px-4 py-2 border-2 border-dashed border-slate-300 rounded-lg hover:border-primary transition-colors">
                                        <span class="material-symbols-outlined text-slate-500">upload_file</span>
                                        <span class="text-sm text-slate-600">Choose Image</span>
                                    </div>
                                    <input type="file" name="social_preview" accept="image/jpeg,image/png,image/jpg,image/webp" class="hidden" onchange="displayFileName(this, 'social-preview-name')" />
                                </label>
                            </div>
                            <p class="text-xs text-slate-500" id="social-preview-name">Max 5MB • JPEG, PNG, WebP</p>
                        </div>

                        <!-- Logo Image -->
                        <div class="space-y-3">
                            <label class="block text-sm font-medium text-slate-700">Site Logo</label>
                            <p class="text-xs text-slate-500 mb-2">Your site's logo for structured data and branding. Transparent PNG recommended.</p>
                            
                            <?php if (file_exists($current_logo)): ?>
                            <div class="mb-3 rounded-lg border border-slate-200 p-3">
                                <p class="text-xs font-medium text-slate-600 mb-2">Current Logo:</p>
                                <div class="bg-slate-50 rounded-lg p-4 flex items-center justify-center">
                                    <img src="<?= htmlspecialchars($current_logo) ?>" alt="Current logo" class="max-h-24 object-contain" />
                                </div>
                                <p class="text-xs text-slate-500 mt-2"><?= htmlspecialchars($current_logo) ?></p>
                            </div>
                            <?php endif; ?>

                            <div class="flex items-center gap-3">
                                <label class="flex-1 cursor-pointer">
                                    <div class="flex items-center justify-center gap-2 px-4 py-2 border-2 border-dashed border-slate-300 rounded-lg hover:border-primary transition-colors">
                                        <span class="material-symbols-outlined text-slate-500">upload_file</span>
                                        <span class="text-sm text-slate-600">Choose Logo</span>
                                    </div>
                                    <input type="file" name="logo_image" accept="image/jpeg,image/png,image/jpg,image/webp,image/svg+xml" class="hidden" onchange="displayFileName(this, 'logo-name')" />
                                </label>
                            </div>
                            <p class="text-xs text-slate-500" id="logo-name">Max 2MB • JPEG, PNG, WebP, SVG</p>
                        </div>
                    </div>

                    <div class="flex justify-end pt-4 border-t border-slate-200">
                        <button type="submit" name="upload_images" class="px-6 py-2.5 bg-brand-green text-white rounded-lg font-semibold shadow-sm hover:bg-brand-green/90 transition-colors flex items-center gap-2">
                            <span class="material-symbols-outlined text-sm">upload</span>
                            Upload Images
                        </button>
                    </div>
                </form>
            </div>

            <!-- Payment Gateway Configuration -->
            <form method="POST" class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-5">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-xl font-semibold text-text-dark">Payment Gateway API Keys</h2>
                        <p class="text-sm text-slate-500">Configure API keys for online payment gateways. Keys are stored securely.</p>
                    </div>
                    <span class="text-xs px-2 py-1 rounded-full <?= ($payment_option_paystack || $payment_option_flutterwave) ? 'bg-green-100 text-green-600' : 'bg-slate-100 text-slate-500' ?>">
                        <?= $payment_option_paystack ? 'Paystack Active' : '' ?>
                        <?= ($payment_option_paystack && $payment_option_flutterwave) ? ' + ' : '' ?>
                        <?= $payment_option_flutterwave ? 'Flutterwave Active' : '' ?>
                        <?= (!$payment_option_paystack && !$payment_option_flutterwave) ? 'No online payment active' : '' ?>
                    </span>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Paystack Keys -->
                    <div class="space-y-4 p-4 rounded-lg border border-slate-200 <?= $payment_option_paystack ? 'bg-green-50/30' : 'bg-slate-50' ?>">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary">account_balance</span>
                            <h3 class="text-lg font-semibold text-text-dark">Paystack</h3>
                            <?php if ($payment_option_paystack): ?>
                                <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-600">Enabled</span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Public Key</label>
                            <input type="text" name="paystack_public_key" value="<?= htmlspecialchars($paystack_public_key) ?>" placeholder="pk_live_..." class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:ring-2 focus:ring-primary/30 focus:border-primary text-sm font-mono" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Secret Key</label>
                            <input type="password" name="paystack_secret_key" value="<?= htmlspecialchars($paystack_secret_key) ?>" placeholder="sk_live_..." class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:ring-2 focus:ring-primary/30 focus:border-primary text-sm font-mono" />
                        </div>
                        <p class="text-xs text-slate-500">Get your keys from <a href="https://dashboard.paystack.com" target="_blank" class="text-primary underline">Paystack Dashboard</a></p>
                    </div>

                    <!-- Flutterwave Keys -->
                    <div class="space-y-4 p-4 rounded-lg border border-slate-200 <?= $payment_option_flutterwave ? 'bg-green-50/30' : 'bg-slate-50' ?>">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-secondary">payments</span>
                            <h3 class="text-lg font-semibold text-text-dark">Flutterwave</h3>
                            <?php if ($payment_option_flutterwave): ?>
                                <span class="text-xs px-2 py-0.5 rounded-full bg-green-100 text-green-600">Enabled</span>
                            <?php endif; ?>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Public Key</label>
                            <input type="text" name="flutterwave_public_key" value="<?= htmlspecialchars($flutterwave_public_key) ?>" placeholder="FLWPUBK-..." class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:ring-2 focus:ring-primary/30 focus:border-primary text-sm font-mono" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Secret Key</label>
                            <input type="password" name="flutterwave_secret_key" value="<?= htmlspecialchars($flutterwave_secret_key) ?>" placeholder="FLWSECK-..." class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:ring-2 focus:ring-primary/30 focus:border-primary text-sm font-mono" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-1">Encryption Key</label>
                            <input type="password" name="flutterwave_encryption_key" value="<?= htmlspecialchars($flutterwave_encryption_key) ?>" placeholder="FLWSECK_enc..." class="w-full px-3 py-2 rounded-lg border border-slate-200 focus:ring-2 focus:ring-primary/30 focus:border-primary text-sm font-mono" />
                        </div>
                        <p class="text-xs text-slate-500">Get your keys from <a href="https://dashboard.flutterwave.com" target="_blank" class="text-primary underline">Flutterwave Dashboard</a></p>
                    </div>
                </div>

                <div class="flex justify-end pt-4 border-t border-slate-200">
                    <button type="submit" name="update_payment_keys" class="px-6 py-2.5 bg-brand-green text-white rounded-lg font-semibold shadow-sm hover:bg-brand-green/90 transition-colors flex items-center gap-2">
                        <span class="material-symbols-outlined text-sm">save</span>
                        Save API Keys
                    </button>
                </div>
            </form>
        </div>
    </main>
    <script>
    function displayFileName(input, targetId) {
        const target = document.getElementById(targetId);
        if (input.files && input.files[0]) {
            const fileName = input.files[0].name;
            const fileSize = (input.files[0].size / (1024 * 1024)).toFixed(2);
            target.textContent = fileName + ' (' + fileSize + 'MB)';
        }
    }
    
    // Auto-save toggle settings
    document.addEventListener('DOMContentLoaded', function() {
        const toggleSwitches = document.querySelectorAll('.toggle-switch');
        const toggleForm = document.getElementById('toggleForm');
        
        toggleSwitches.forEach(toggle => {
            toggle.addEventListener('change', function() {
                // Submit the form
                toggleForm.submit();
            });
        });
    });
    </script>
</body>
</html>
