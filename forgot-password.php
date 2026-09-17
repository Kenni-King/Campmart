<?php 
session_start();
require_once 'includes/controller.php';

$success_message = '';
$error_message = '';

// Handle password reset request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_reset'])) {
    $email = trim($_POST['email'] ?? '');
    
    if (empty($email)) {
        $error_message = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } else {
        // Check if user exists
        $check_query = "SELECT id, username, email FROM users WHERE email = ? LIMIT 1";
        $stmt = $db->prepare($check_query);
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $token_hash = hash('sha256', $token);
            $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Store token in database
            $insert_query = "INSERT INTO password_resets (user_id, email, token, expires_at) 
                            VALUES (?, ?, ?, ?) 
                            ON DUPLICATE KEY UPDATE token = ?, expires_at = ?, created_at = NOW()";
            $stmt = $db->prepare($insert_query);
            $stmt->bind_param("isssss", $user['id'], $email, $token_hash, $expires_at, $token_hash, $expires_at);
            
            if ($stmt->execute()) {
                // Create reset link
                $reset_link = SITE_URL . "reset-password.php?token=" . $token;
                
                // Send email (simplified version - you should use proper email library like PHPMailer)
                $to = $email;
                $subject = "Password Reset Request - CampMart";
                $message = "Hi " . htmlspecialchars($user['username']) . ",\n\n";
                $message .= "You requested a password reset for your CampMart account.\n\n";
                $message .= "Click the link below to reset your password:\n";
                $message .= $reset_link . "\n\n";
                $message .= "This link will expire in 1 hour.\n\n";
                $message .= "If you didn't request this, please ignore this email.\n\n";
                $message .= "Best regards,\nThe CampMart Team";
                
                $headers = "From: noreply@campmart.ng\r\n";
                $headers .= "Reply-To: support@campmart.ng\r\n";
                $headers .= "X-Mailer: PHP/" . phpversion();
                
                // Attempt to send email
                if (@mail($to, $subject, $message, $headers)) {
                    $success_message = 'Password reset instructions have been sent to your email address.';
                } else {
                    // For development: show the reset link directly
                    $success_message = 'Password reset link generated. (Email not configured)<br>Reset link: <a href="' . $reset_link . '" class="underline">' . $reset_link . '</a>';
                }
            } else {
                $error_message = 'Failed to generate reset token. Please try again.';
            }
        } else {
            // For security, show success message even if email doesn't exist
            $success_message = 'If an account exists with this email, you will receive password reset instructions.';
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
    <title>Forgot Password - CampMart</title>
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
                <h1 class="text-3xl font-extrabold text-slate-900 mb-2">Forgot Password?</h1>
                <p class="text-slate-500 font-medium">Enter your email to receive reset instructions</p>
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

                <form method="POST" class="space-y-6">
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2" for="email">Email Address</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-400 material-symbols-outlined">email</span>
                            <input class="input-field pl-12" id="email" name="email" placeholder="student@university.edu" type="email" required/>
                        </div>
                    </div>

                    <button class="btn-primary" name="request_reset" type="submit">
                        Send Reset Instructions
                        <span class="material-symbols-outlined text-xl">send</span>
                    </button>
                </form>

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
</body>
</html>
