<?php session_start();
include_once 'includes/controller.php';

$userId = $_SESSION['userAppId'] ?? 0;

// Fetch user data
$user_query = $db->query("SELECT * FROM users WHERE id = '$userId'");
$user = $user_query->fetch_assoc();

// Fetch user settings/preferences
$settings_query = $db->query("SELECT * FROM user_settings WHERE user_id = '$userId'");
$settings = $settings_query->fetch_assoc();

// If no settings exist, create default
if(!$settings) {
    $settings = [
        'email_notifications' => 1,
        'push_notifications' => 1,
        'sms_notifications' => 0,
        'notify_new_message' => 1,
        'notify_order_update' => 1,
        'notify_product_sold' => 1,
        'notify_new_review' => 1,
        'notify_price_change' => 0,
        'profile_visibility' => 'public',
        'show_email' => 0,
        'show_phone' => 0,
        'show_location' => 1,
        'allow_messages' => 1,
        'two_factor_enabled' => 0,
        'language' => 'en',
        'timezone' => 'Africa/Lagos',
        'currency' => 'NGN'
    ];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Settings | CampMart</title>
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
            background-color: #fff7ed;
        }
    </style>
</head>

<body class="bg-background-main min-h-screen text-text-dark">
    <?php include_once 'includes/user-nav.php'; ?>
    <main class="flex-1 overflow-y-auto bg-background-main p-4 md:p-6 lg:p-8">
        <div class="max-w-5xl mx-auto space-y-6">
            <!-- Header -->
            <div>
                <h1 class="text-3xl font-bold tracking-tight text-brand-green">Settings</h1>
                <p class="text-slate-500 mt-1">Manage your account preferences and configurations</p>
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

            <!-- Settings Navigation -->
            <div class="bg-surface-white rounded-xl border border-slate-200 shadow-sm">
                <div class="border-b border-slate-200 px-6 py-4">
                    <div class="flex gap-2 overflow-x-auto">
                        <button onclick="switchTab('notifications')" class="tab-button active px-4 py-2 text-sm font-medium border-b-2 border-transparent hover:text-primary transition-colors whitespace-nowrap">
                            <span class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-lg">notifications</span>
                                Notifications
                            </span>
                        </button>
                        <button onclick="switchTab('privacy')" class="tab-button px-4 py-2 text-sm font-medium border-b-2 border-transparent hover:text-primary transition-colors whitespace-nowrap">
                            <span class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-lg">privacy_tip</span>
                                Privacy
                            </span>
                        </button>
                        <button onclick="switchTab('security')" class="tab-button px-4 py-2 text-sm font-medium border-b-2 border-transparent hover:text-primary transition-colors whitespace-nowrap">
                            <span class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-lg">security</span>
                                Security
                            </span>
                        </button>
                        <button onclick="switchTab('preferences')" class="tab-button px-4 py-2 text-sm font-medium border-b-2 border-transparent hover:text-primary transition-colors whitespace-nowrap">
                            <span class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-lg">tune</span>
                                Preferences
                            </span>
                        </button>
                    </div>
                </div>

                <!-- Notifications Tab -->
                <div id="notifications-tab" class="tab-content p-6">
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>" />
                        
                        <div>
                            <h3 class="text-lg font-bold text-text-dark mb-4">Notification Channels</h3>
                            <div class="space-y-4">
                                <label class="flex items-center justify-between p-4 rounded-lg border border-slate-200 hover:border-primary/50 transition-colors cursor-pointer">
                                    <div class="flex items-center gap-3">
                                        <span class="material-symbols-outlined text-primary">email</span>
                                        <div>
                                            <p class="font-medium text-text-dark">Email Notifications</p>
                                            <p class="text-sm text-slate-500">Receive notifications via email</p>
                                        </div>
                                    </div>
                                    <input type="checkbox" name="email_notifications" <?= $settings['email_notifications'] ? 'checked' : '' ?> class="toggle-switch size-5 rounded text-primary focus:ring-primary">
                                </label>

                                <label class="flex items-center justify-between p-4 rounded-lg border border-slate-200 hover:border-primary/50 transition-colors cursor-pointer">
                                    <div class="flex items-center gap-3">
                                        <span class="material-symbols-outlined text-brand-green">notifications_active</span>
                                        <div>
                                            <p class="font-medium text-text-dark">Push Notifications</p>
                                            <p class="text-sm text-slate-500">Receive push notifications in browser</p>
                                        </div>
                                    </div>
                                    <input type="checkbox" name="push_notifications" <?= $settings['push_notifications'] ? 'checked' : '' ?> class="toggle-switch size-5 rounded text-primary focus:ring-primary">
                                </label>

                                <label class="flex items-center justify-between p-4 rounded-lg border border-slate-200 hover:border-primary/50 transition-colors cursor-pointer">
                                    <div class="flex items-center gap-3">
                                        <span class="material-symbols-outlined text-blue-600">sms</span>
                                        <div>
                                            <p class="font-medium text-text-dark">SMS Notifications</p>
                                            <p class="text-sm text-slate-500">Receive important updates via SMS</p>
                                        </div>
                                    </div>
                                    <input type="checkbox" name="sms_notifications" <?= $settings['sms_notifications'] ? 'checked' : '' ?> class="toggle-switch size-5 rounded text-primary focus:ring-primary">
                                </label>
                            </div>
                        </div>

                        <div class="border-t border-slate-200 pt-6">
                            <h3 class="text-lg font-bold text-text-dark mb-4">Notification Types</h3>
                            <div class="space-y-3">
                                <label class="flex items-center justify-between p-3 rounded-lg hover:bg-slate-50 transition-colors cursor-pointer">
                                    <span class="text-sm font-medium text-text-dark">New messages</span>
                                    <input type="checkbox" name="notify_new_message" <?= $settings['notify_new_message'] ? 'checked' : '' ?> class="size-4 rounded text-primary focus:ring-primary">
                                </label>
                                <label class="flex items-center justify-between p-3 rounded-lg hover:bg-slate-50 transition-colors cursor-pointer">
                                    <span class="text-sm font-medium text-text-dark">Order updates</span>
                                    <input type="checkbox" name="notify_order_update" <?= $settings['notify_order_update'] ? 'checked' : '' ?> class="size-4 rounded text-primary focus:ring-primary">
                                </label>
                                <label class="flex items-center justify-between p-3 rounded-lg hover:bg-slate-50 transition-colors cursor-pointer">
                                    <span class="text-sm font-medium text-text-dark">Product sold</span>
                                    <input type="checkbox" name="notify_product_sold" <?= $settings['notify_product_sold'] ? 'checked' : '' ?> class="size-4 rounded text-primary focus:ring-primary">
                                </label>
                                <label class="flex items-center justify-between p-3 rounded-lg hover:bg-slate-50 transition-colors cursor-pointer">
                                    <span class="text-sm font-medium text-text-dark">New reviews</span>
                                    <input type="checkbox" name="notify_new_review" <?= $settings['notify_new_review'] ? 'checked' : '' ?> class="size-4 rounded text-primary focus:ring-primary">
                                </label>
                                <label class="flex items-center justify-between p-3 rounded-lg hover:bg-slate-50 transition-colors cursor-pointer">
                                    <span class="text-sm font-medium text-text-dark">Price changes</span>
                                    <input type="checkbox" name="notify_price_change" <?= $settings['notify_price_change'] ? 'checked' : '' ?> class="size-4 rounded text-primary focus:ring-primary">
                                </label>
                            </div>
                        </div>

                        <div class="flex justify-end pt-4">
                            <button type="submit" name="UpdateNotifications" class="px-6 py-3 bg-primary text-white rounded-lg font-bold hover:bg-primary/90 transition-colors">
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Privacy Tab -->
                <div id="privacy-tab" class="tab-content hidden p-6">
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>" />
                        
                        <div>
                            <h3 class="text-lg font-bold text-text-dark mb-4">Profile Visibility</h3>
                            <div class="space-y-3">
                                <label class="flex items-center gap-3 p-4 rounded-lg border border-slate-200 hover:border-primary/50 transition-colors cursor-pointer">
                                    <input type="radio" name="profile_visibility" value="public" <?= $settings['profile_visibility'] == 'public' ? 'checked' : '' ?> class="size-4 text-primary focus:ring-primary">
                                    <div>
                                        <p class="font-medium text-text-dark">Public</p>
                                        <p class="text-sm text-slate-500">Anyone can view your profile</p>
                                    </div>
                                </label>
                                <label class="flex items-center gap-3 p-4 rounded-lg border border-slate-200 hover:border-primary/50 transition-colors cursor-pointer">
                                    <input type="radio" name="profile_visibility" value="private" <?= $settings['profile_visibility'] == 'private' ? 'checked' : '' ?> class="size-4 text-primary focus:ring-primary">
                                    <div>
                                        <p class="font-medium text-text-dark">Private</p>
                                        <p class="text-sm text-slate-500">Only you can view your full profile</p>
                                    </div>
                                </label>
                                <label class="flex items-center gap-3 p-4 rounded-lg border border-slate-200 hover:border-primary/50 transition-colors cursor-pointer">
                                    <input type="radio" name="profile_visibility" value="students_only" <?= $settings['profile_visibility'] == 'students_only' ? 'checked' : '' ?> class="size-4 text-primary focus:ring-primary">
                                    <div>
                                        <p class="font-medium text-text-dark">Students Only</p>
                                        <p class="text-sm text-slate-500">Only verified students can view</p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="border-t border-slate-200 pt-6">
                            <h3 class="text-lg font-bold text-text-dark mb-4">Contact Information</h3>
                            <div class="space-y-3">
                                <label class="flex items-center justify-between p-3 rounded-lg hover:bg-slate-50 transition-colors cursor-pointer">
                                    <span class="text-sm font-medium text-text-dark">Show email address on profile</span>
                                    <input type="checkbox" name="show_email" <?= $settings['show_email'] ? 'checked' : '' ?> class="size-4 rounded text-primary focus:ring-primary">
                                </label>
                                <label class="flex items-center justify-between p-3 rounded-lg hover:bg-slate-50 transition-colors cursor-pointer">
                                    <span class="text-sm font-medium text-text-dark">Show phone number on profile</span>
                                    <input type="checkbox" name="show_phone" <?= $settings['show_phone'] ? 'checked' : '' ?> class="size-4 rounded text-primary focus:ring-primary">
                                </label>
                                <label class="flex items-center justify-between p-3 rounded-lg hover:bg-slate-50 transition-colors cursor-pointer">
                                    <span class="text-sm font-medium text-text-dark">Show location on profile</span>
                                    <input type="checkbox" name="show_location" <?= $settings['show_location'] ? 'checked' : '' ?> class="size-4 rounded text-primary focus:ring-primary">
                                </label>
                            </div>
                        </div>

                        <div class="border-t border-slate-200 pt-6">
                            <h3 class="text-lg font-bold text-text-dark mb-4">Communication</h3>
                            <label class="flex items-center justify-between p-4 rounded-lg border border-slate-200 hover:border-primary/50 transition-colors cursor-pointer">
                                <div>
                                    <p class="font-medium text-text-dark">Allow direct messages</p>
                                    <p class="text-sm text-slate-500">Let other users send you messages</p>
                                </div>
                                <input type="checkbox" name="allow_messages" <?= $settings['allow_messages'] ? 'checked' : '' ?> class="size-5 rounded text-primary focus:ring-primary">
                            </label>
                        </div>

                        <div class="flex justify-end pt-4">
                            <button type="submit" name="UpdatePrivacy" class="px-6 py-3 bg-primary text-white rounded-lg font-bold hover:bg-primary/90 transition-colors">
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Security Tab -->
                <div id="security-tab" class="tab-content hidden p-6">
                    <div class="space-y-6">
                        <!-- Change Password -->
                        <form method="POST" class="p-6 rounded-lg border border-slate-200">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>" />
                            <h3 class="text-lg font-bold text-text-dark mb-4">Change Password</h3>
                            <div class="space-y-4">
                                <div>
                                    <label class="block text-sm font-bold text-text-dark mb-2">Current Password</label>
                                    <input type="password" name="current_password" required class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-text-dark mb-2">New Password</label>
                                    <input type="password" name="new_password" required class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-text-dark mb-2">Confirm New Password</label>
                                    <input type="password" name="confirm_password" required class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                                </div>
                                <button type="submit" name="ChangePassword" class="px-6 py-3 bg-primary text-white rounded-lg font-bold hover:bg-primary/90 transition-colors">
                                    Update Password
                                </button>
                            </div>
                        </form>

                        <!-- Two-Factor Authentication -->
                        <div class="p-6 rounded-lg border border-slate-200">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <h3 class="text-lg font-bold text-text-dark mb-2">Two-Factor Authentication</h3>
                                    <p class="text-sm text-slate-600 mb-4">Add an extra layer of security to your account</p>
                                    <?php if($settings['two_factor_enabled']): ?>
                                    <div class="flex items-center gap-2 text-sm text-emerald-700 bg-emerald-50 px-3 py-2 rounded-lg inline-flex">
                                        <span class="material-symbols-outlined text-sm">check_circle</span>
                                        Enabled
                                    </div>
                                    <?php else: ?>
                                    <div class="flex items-center gap-2 text-sm text-slate-600 bg-slate-100 px-3 py-2 rounded-lg inline-flex">
                                        <span class="material-symbols-outlined text-sm">info</span>
                                        Not enabled
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <button onclick="toggle2FA()" class="px-4 py-2 <?= $settings['two_factor_enabled'] ? 'bg-red-600 hover:bg-red-700' : 'bg-brand-green hover:bg-brand-green/90' ?> text-white rounded-lg font-medium transition-colors">
                                    <?= $settings['two_factor_enabled'] ? 'Disable' : 'Enable' ?>
                                </button>
                            </div>
                        </div>

                        <!-- Active Sessions -->
                        <div class="p-6 rounded-lg border border-slate-200">
                            <h3 class="text-lg font-bold text-text-dark mb-4">Active Sessions</h3>
                            <div class="space-y-3">
                                <div class="flex items-center justify-between p-3 rounded-lg bg-slate-50">
                                    <div class="flex items-center gap-3">
                                        <span class="material-symbols-outlined text-primary">devices</span>
                                        <div>
                                            <p class="font-medium text-text-dark">Current Device</p>
                                            <p class="text-sm text-slate-500">Windows • Chrome • <?= date('M d, Y h:i A') ?></p>
                                        </div>
                                    </div>
                                    <span class="text-xs text-emerald-700 bg-emerald-100 px-2 py-1 rounded-full">Active</span>
                                </div>
                            </div>
                            <button class="mt-4 text-sm text-red-600 hover:underline font-medium">
                                Sign out of all other sessions
                            </button>
                        </div>

                        <!-- Delete Account -->
                        <div class="p-6 rounded-lg border border-red-200 bg-red-50">
                            <h3 class="text-lg font-bold text-red-900 mb-2">Delete Account</h3>
                            <p class="text-sm text-red-700 mb-4">Permanently delete your account and all associated data. This action cannot be undone.</p>
                            <button onclick="confirmDeleteAccount()" class="px-4 py-2 bg-red-600 text-white rounded-lg font-medium hover:bg-red-700 transition-colors">
                                Delete My Account
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Preferences Tab -->
                <div id="preferences-tab" class="tab-content hidden p-6">
                    <form method="POST" class="space-y-6">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>" />
                        
                        <div>
                            <h3 class="text-lg font-bold text-text-dark mb-4">Regional Settings</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-bold text-text-dark mb-2">Language</label>
                                    <select name="language" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                                        <option value="en" <?= $settings['language'] == 'en' ? 'selected' : '' ?>>English</option>
                                        <option value="yo" <?= $settings['language'] == 'yo' ? 'selected' : '' ?>>Yoruba</option>
                                        <option value="ig" <?= $settings['language'] == 'ig' ? 'selected' : '' ?>>Igbo</option>
                                        <option value="ha" <?= $settings['language'] == 'ha' ? 'selected' : '' ?>>Hausa</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-text-dark mb-2">Timezone</label>
                                    <select name="timezone" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                                        <option value="Africa/Lagos" <?= $settings['timezone'] == 'Africa/Lagos' ? 'selected' : '' ?>>Lagos (WAT)</option>
                                        <option value="UTC" <?= $settings['timezone'] == 'UTC' ? 'selected' : '' ?>>UTC</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-sm font-bold text-text-dark mb-2">Currency</label>
                                    <select name="currency" class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none">
                                        <option value="NGN" <?= $settings['currency'] == 'NGN' ? 'selected' : '' ?>>Nigerian Naira (₦)</option>
                                        <option value="USD" <?= $settings['currency'] == 'USD' ? 'selected' : '' ?>>US Dollar ($)</option>
                                        <option value="GBP" <?= $settings['currency'] == 'GBP' ? 'selected' : '' ?>>British Pound (£)</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="border-t border-slate-200 pt-6">
                            <h3 class="text-lg font-bold text-text-dark mb-4">Display Preferences</h3>
                            <div class="space-y-4">
                                <label class="flex items-center justify-between p-4 rounded-lg border border-slate-200 hover:border-primary/50 transition-colors cursor-pointer">
                                    <div>
                                        <p class="font-medium text-text-dark">Dark Mode</p>
                                        <p class="text-sm text-slate-500">Use dark theme across the platform</p>
                                    </div>
                                    <input type="checkbox" name="dark_mode" class="size-5 rounded text-primary focus:ring-primary">
                                </label>
                                <label class="flex items-center justify-between p-4 rounded-lg border border-slate-200 hover:border-primary/50 transition-colors cursor-pointer">
                                    <div>
                                        <p class="font-medium text-text-dark">Compact View</p>
                                        <p class="text-sm text-slate-500">Show more items per page</p>
                                    </div>
                                    <input type="checkbox" name="compact_view" class="size-5 rounded text-primary focus:ring-primary">
                                </label>
                            </div>
                        </div>

                        <div class="flex justify-end pt-4">
                            <button type="submit" name="UpdatePreferences" class="px-6 py-3 bg-primary text-white rounded-lg font-bold hover:bg-primary/90 transition-colors">
                                Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Auto-dismiss messages
        setTimeout(() => {
            const successMsg = document.getElementById('successMessage');
            const errorMsg = document.getElementById('errorMessage');
            if(successMsg) successMsg.remove();
            if(errorMsg) errorMsg.remove();
        }, 5000);

        // Tab switching
        function switchTab(tabName) {
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.add('hidden');
            });
            
            document.querySelectorAll('.tab-button').forEach(btn => {
                btn.classList.remove('active');
            });
            
            document.getElementById(tabName + '-tab').classList.remove('hidden');
            event.target.closest('.tab-button').classList.add('active');
        }

        // Toggle 2FA
        function toggle2FA() {
            if(confirm('Are you sure you want to change your two-factor authentication settings?')) {
                // Implementation for 2FA toggle
                alert('Two-factor authentication feature will be implemented in the backend.');
            }
        }

        // Confirm account deletion
        function confirmDeleteAccount() {
            const confirmed = confirm('⚠️ WARNING: This will permanently delete your account and all data.\n\nType "DELETE" to confirm:');
            if(confirmed) {
                const verification = prompt('Type DELETE to confirm account deletion:');
                if(verification === 'DELETE') {
                    // Submit delete account request
                    alert('Account deletion will be processed. You will receive a confirmation email.');
                }
            }
        }

        // Mobile menu toggle
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        function toggleSidebar() {
            if(sidebar) sidebar.classList.toggle('-translate-x-full');
            if(sidebarOverlay) sidebarOverlay.classList.toggle('hidden');
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
    </script>
</body>

</html>
