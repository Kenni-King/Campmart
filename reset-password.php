<?php 
session_start();
require_once 'includes/controller.php';

$success_message = '';
$error_message = '';
$token_valid = false;
$user_id = null;

// Get and verify token
$token = $_GET['token'] ?? '';

if (!empty($token)) {
    $token_hash = hash('sha256', $token);
    
    // Check if token exists and is valid
    $check_query = "SELECT pr.*, u.email, u.username 
                    FROM password_resets pr 
                    JOIN users u ON pr.user_id = u.id 
                    WHERE pr.token = ? 
                    AND pr.expires_at > NOW() 
                    AND pr.used_at IS NULL 
                    LIMIT 1";
    $stmt = $db->prepare($check_query);
    $stmt->bind_param("s", $token_hash);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $token_data = $result->fetch_assoc();
        $token_valid = true;
        $user_id = $token_data['user_id'];
    } else {
        $error_message = 'This password reset link is invalid or has expired. Please request a new one.';
    }
}

// Handle password reset submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password']) && $token_valid) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($password)) {
        $error_message = 'Please enter a new password.';
    } elseif (strlen($password) < 8) {
        $error_message = 'Password must be at least 8 characters long.';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
    } else {
        // Hash the new password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        
        // Update user password
        $update_query = "UPDATE users SET password_hash = ? WHERE id = ?";
        $stmt = $db->prepare($update_query);
        $stmt->bind_param("si", $password_hash, $user_id);
        
        if ($stmt->execute()) {
            // Mark token as used
            $mark_used = "UPDATE password_resets SET used_at = NOW() WHERE token = ?";
            $stmt = $db->prepare($mark_used);
            $stmt->bind_param("s", $token_hash);
            $stmt->execute();
            
            $_SESSION['success'] = 'Your password has been reset successfully. You can now log in.';
            header('Location: login.php');
            exit;
        } else {
            $error_message = 'Failed to reset password. Please try again.';
        }
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8"/>
    <base href="<?php echo SITE_URL; ?>">
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <title>Reset Password - CampMart</title>
    <script src="tailwind34.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#064E3B",
                        "secondary": "#F97316",
                        "accent": "#BEF264",
                        "surface": "#F8FAFC",
                    },
                    fontFamily: {
                        "display": ["Plus Jakarta Sans", "sans-serif"]
                    },
                },
            },
        }
    </script>
    <style type="text/tailwindcss">
        @layer components {
            .input-field {
                @apply w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/10 transition-all outline-none text-slate-700 placeholder:text-slate-400;
            }
            .btn-primary {
                @apply w-full bg-primary text-white px-6 py-3.5 rounded-xl font-bold hover:bg-opacity-90 transition-all flex items-center justify-center gap-2 shadow-lg shadow-primary/20;
            }
        }
    </style>
</head>
<body class="bg-white font-display text-slate-900 antialiased min-h-screen">
    <div class="min-h-screen flex items-center justify-center p-6 bg-gradient-to-br from-primary/5 via-white to-secondary/5">
        <div class="w-full max-w-md">
            <div class="text-center mb-8">
                <a class="inline-flex items-center gap-2 mb-6" href="index.php">
                    <div class="size-12 flex items-center justify-center bg-primary text-white rounded-xl shadow-lg">
                        <span class="material-symbols-outlined font-bold text-2xl">shopping_bag</span>
                    </div>
                    <span class="text-2xl font-extrabold text-primary">CampMart</span>
                </a>
                <h1 class="text-3xl font-extrabold text-slate-900 mb-2">Reset Password</h1>
                <p class="text-slate-500 font-medium">Enter your new password below</p>
            </div>

            <div class="bg-white rounded-2xl border border-slate-200 shadow-xl p-8">
                <?php if ($success_message): ?>
                <div class="p-4 bg-green-50 border border-green-200 rounded-xl text-green-700 text-sm font-medium mb-6 flex items-start gap-3">
                    <span class="material-symbols-outlined text-xl">check_circle</span>
                    <span><?= $success_message ?></span>
                </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                <div class="p-4 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm font-medium mb-6 flex items-start gap-3">
                    <span class="material-symbols-outlined text-xl">error</span>
                    <span><?= $error_message ?></span>
                </div>
                <?php endif; ?>

                <?php if ($token_valid): ?>
                <form method="POST" class="space-y-6">
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2" for="password">New Password</label>
                        <div class="relative">
                            <input class="input-field pr-12" id="password" name="password" placeholder="••••••••" type="password" required minlength="8"/>
                            <button class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600" type="button" onclick="togglePassword('password', 'passwordIcon')">
                                <span class="material-symbols-outlined text-xl" id="passwordIcon">visibility</span>
                            </button>
                        </div>
                        <p class="text-xs text-slate-500 mt-2">Must be at least 8 characters long</p>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2" for="confirm_password">Confirm Password</label>
                        <div class="relative">
                            <input class="input-field pr-12" id="confirm_password" name="confirm_password" placeholder="••••••••" type="password" required minlength="8"/>
                            <button class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600" type="button" onclick="togglePassword('confirm_password', 'confirmIcon')">
                                <span class="material-symbols-outlined text-xl" id="confirmIcon">visibility</span>
                            </button>
                        </div>
                    </div>

                    <button class="btn-primary" name="reset_password" type="submit">
                        Reset Password
                        <span class="material-symbols-outlined text-xl">lock_reset</span>
                    </button>
                </form>
                <?php else: ?>
                <div class="text-center py-8">
                    <span class="material-symbols-outlined text-6xl text-red-400 mb-4">error_outline</span>
                    <h3 class="text-lg font-bold text-slate-900 mb-2">Invalid Reset Link</h3>
                    <p class="text-slate-600 mb-6">This password reset link is invalid or has expired.</p>
                    <a href="forgot-password.php" class="inline-flex items-center gap-2 px-6 py-3 bg-primary text-white rounded-xl font-bold hover:bg-opacity-90 transition-all">
                        <span class="material-symbols-outlined">refresh</span>
                        Request New Link
                    </a>
                </div>
                <?php endif; ?>

                <div class="mt-6 pt-6 border-t border-slate-100 text-center">
                    <p class="text-slate-500 font-medium text-sm">
                        Remember your password? 
                        <a class="text-primary font-bold hover:underline underline-offset-4 ml-1" href="login.php">Sign In</a>
                    </p>
                </div>
            </div>

            <div class="mt-6 text-center">
                <p class="text-xs text-slate-400">
                    Protected by CampMart Security
                </p>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(fieldId, iconId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById(iconId);
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.textContent = 'visibility_off';
            } else {
                field.type = 'password';
                icon.textContent = 'visibility';
            }
        }

        // Real-time password match validation
        const password = document.getElementById('password');
        const confirm = document.getElementById('confirm_password');
        
        if (password && confirm) {
            confirm.addEventListener('input', function() {
                if (this.value && password.value !== this.value) {
                    this.setCustomValidity('Passwords do not match');
                } else {
                    this.setCustomValidity('');
                }
            });
        }
    </script>
</body>
</html>
