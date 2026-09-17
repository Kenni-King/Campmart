<?php 
session_start(); 
include_once 'includes/controller.php';

// Check if token is provided
if (!isset($_GET['token']) || empty($_GET['token'])) {
    $_SESSION['error'] = 'Invalid verification link';
    header('Location: login.php');
    exit();
}

$token = sanitize($_GET['token']);

// Find user with this token
$user = dbSelect('users', ['email_verification_token' => $token])->fetch_assoc();

if (!$user) {
    $errorMessage = 'Invalid verification token. The link may be incorrect or has already been used.';
    $errorType = 'invalid';
} elseif ($user['email_verified']) {
    $errorMessage = 'Your email is already verified. You can now login.';
    $errorType = 'already_verified';
} elseif (strtotime($user['email_verification_expiry']) < time()) {
    $errorMessage = 'Verification link has expired. Please request a new verification email.';
    $errorType = 'expired';
    $userId = $user['id'];
} else {
    // Verify the email
    dbUpdate('users', [
        'email_verified' => 1,
        'email_verification_token' => null,
        'email_verification_expiry' => null
    ], ['id' => $user['id']]);
    
    $successMessage = 'Email verified successfully! You can now access all features.';
    $userName = $user['firstname'];
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8" />    
    <base href="<?php echo SITE_URL; ?>">    
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Email Verification | CampMart</title>
    <script src="tailwind34.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />
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
            .btn-primary {
                @apply bg-primary text-white px-6 py-3.5 rounded-xl font-bold hover:bg-opacity-90 transition-all flex items-center justify-center gap-2 shadow-lg shadow-primary/20;
            }
        }
    </style>
</head>
<body class="bg-surface font-display text-slate-900 antialiased">
    <div class="min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-md">
            <!-- Logo -->
            <div class="text-center mb-8">
                <a class="inline-flex items-center gap-3" href="/">
                    <div class="size-14 flex items-center justify-center bg-primary text-white rounded-xl shadow-xl">
                        <span class="material-symbols-outlined font-bold text-3xl">shopping_bag</span>
                    </div>
                    <span class="text-3xl font-extrabold text-primary">CampMart</span>
                </a>
            </div>

            <!-- Verification Result Card -->
            <div class="bg-white rounded-2xl shadow-xl border border-slate-200 p-8">
                <?php if (isset($successMessage)): ?>
                    <!-- Success State -->
                    <div class="text-center">
                        <div class="w-20 h-20 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-6">
                            <span class="material-symbols-outlined text-5xl text-green-600">check_circle</span>
                        </div>
                        <h1 class="text-2xl font-bold text-slate-900 mb-3">Email Verified!</h1>
                        <p class="text-slate-600 mb-6">
                            Welcome, <strong><?= htmlspecialchars($userName) ?></strong>! Your email has been successfully verified.
                        </p>
                        <div class="space-y-3">
                            <?php if (isset($_SESSION['userAppId'])): ?>
                                <a href="./" class="btn-primary w-full">
                                    Go to Dashboard
                                    <span class="material-symbols-outlined text-xl">arrow_forward</span>
                                </a>
                            <?php else: ?>
                                <a href="login.php" class="btn-primary w-full">
                                    Sign In Now
                                    <span class="material-symbols-outlined text-xl">login</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Error State -->
                    <div class="text-center">
                        <div class="w-20 h-20 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-6">
                            <span class="material-symbols-outlined text-5xl text-red-600">
                                <?= $errorType === 'expired' ? 'schedule' : 'error' ?>
                            </span>
                        </div>
                        <h1 class="text-2xl font-bold text-slate-900 mb-3">
                            <?= $errorType === 'already_verified' ? 'Already Verified' : 'Verification Failed' ?>
                        </h1>
                        <p class="text-slate-600 mb-6">
                            <?= htmlspecialchars($errorMessage) ?>
                        </p>
                        <div class="space-y-3">
                            <?php if ($errorType === 'expired' && isset($userId)): ?>
                                <form method="POST" action="includes/controller.php">
                                    <input type="hidden" name="ResendVerification" value="1">
                                    <input type="hidden" name="user_id" value="<?= $userId ?>">
                                    <button type="submit" class="btn-primary w-full">
                                        <span class="material-symbols-outlined text-xl">refresh</span>
                                        Resend Verification Email
                                    </button>
                                </form>
                            <?php endif; ?>
                            <a href="login.php" class="block w-full px-6 py-3.5 bg-slate-100 text-slate-700 rounded-xl font-bold hover:bg-slate-200 transition-all text-center">
                                Go to Login
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Help Text -->
            <div class="text-center mt-6">
                <p class="text-slate-500 text-sm">
                    Need help? <a href="contact-us.php" class="text-primary font-bold hover:underline">Contact Support</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
