<?php
session_start();
include_once "includes/controller.php";

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $subject = trim($_POST['subject'] ?? '');
    $message = trim($_POST['message'] ?? '');

    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } else {
        // Here you would typically save to database or send email
        $success = 'Thank you for contacting us! We\'ll get back to you within 24 hours.';
        // Clear form
        $name = $email = $subject = $message = '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Contact Us | CampMart</title>
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
                        "background-main": "#F9FAFB",
                    },
                    fontFamily: { "display": ["Inter"] }
                }
            }
        }
    </script>
    <style>
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
    </style>
</head>
<body class="bg-background-main">
    <?php include_once 'includes/header.php'; ?>

    <main class="max-w-[1200px] mx-auto px-6 py-12">
        <!-- Hero -->
        <div class="text-center mb-12">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-primary/10 mb-6">
                <span class="material-symbols-outlined text-5xl text-primary">support_agent</span>
            </div>
            <h1 class="text-4xl font-extrabold text-brand-green mb-4">Get in Touch</h1>
            <p class="text-xl text-slate-600">We're here to help! Reach out to our support team.</p>
        </div>

        <div class="grid lg:grid-cols-3 gap-8">
            <!-- Contact Form -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-xl border border-slate-200 p-8">
                    <h2 class="text-2xl font-bold text-brand-green mb-6">Send Us a Message</h2>
                    
                    <?php if ($success): ?>
                    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 p-4 rounded-lg mb-6">
                        <?= htmlspecialchars($success) ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($error): ?>
                    <div class="bg-red-50 border border-red-200 text-red-800 p-4 rounded-lg mb-6">
                        <?= htmlspecialchars($error) ?>
                    </div>
                    <?php endif; ?>

                    <form method="POST" class="space-y-6">
                        <div class="grid md:grid-cols-2 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-2">Your Name *</label>
                                <input type="text" name="name" value="<?= htmlspecialchars($name ?? '') ?>" required class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20" />
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-slate-700 mb-2">Email Address *</label>
                                <input type="email" name="email" value="<?= htmlspecialchars($email ?? '') ?>" required class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Subject *</label>
                            <select name="subject" required class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20">
                                <option value="">Select a subject</option>
                                <option value="account">Account Issues</option>
                                <option value="technical">Technical Support</option>
                                <option value="billing">Billing Question</option>
                                <option value="report">Report a Problem</option>
                                <option value="feedback">Feedback</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-slate-700 mb-2">Message *</label>
                            <textarea name="message" rows="6" required class="w-full px-4 py-3 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20" placeholder="Describe your issue or question..."><?= htmlspecialchars($message ?? '') ?></textarea>
                        </div>
                        <button type="submit" class="w-full bg-primary text-white px-8 py-3 rounded-lg font-bold hover:bg-primary/90 transition-all">
                            Send Message
                        </button>
                    </form>
                </div>
            </div>

            <!-- Contact Info -->
            <div class="space-y-6">
                <div class="bg-white rounded-xl border border-slate-200 p-6">
                    <div class="w-12 h-12 rounded-lg bg-blue-100 flex items-center justify-center mb-4">
                        <span class="material-symbols-outlined text-2xl text-blue-600">mail</span>
                    </div>
                    <h3 class="font-bold text-slate-800 mb-2">Email Us</h3>
                    <p class="text-slate-600 text-sm mb-3">Our team typically responds within 24 hours</p>
                    <a href="mailto:support@campmart.ng" class="text-primary font-semibold hover:underline">support@campmart.ng</a>
                </div>

                <div class="bg-white rounded-xl border border-slate-200 p-6">
                    <div class="w-12 h-12 rounded-lg bg-emerald-100 flex items-center justify-center mb-4">
                        <span class="material-symbols-outlined text-2xl text-emerald-600">schedule</span>
                    </div>
                    <h3 class="font-bold text-slate-800 mb-2">Support Hours</h3>
                    <div class="text-sm text-slate-600 space-y-1">
                        <p><strong>Monday - Friday:</strong> 8am - 8pm</p>
                        <p><strong>Saturday:</strong> 10am - 6pm</p>
                        <p><strong>Sunday:</strong> 10am - 4pm</p>
                    </div>
                </div>

                <div class="bg-white rounded-xl border border-slate-200 p-6">
                    <div class="w-12 h-12 rounded-lg bg-purple-100 flex items-center justify-center mb-4">
                        <span class="material-symbols-outlined text-2xl text-purple-600">forum</span>
                    </div>
                    <h3 class="font-bold text-slate-800 mb-2">Community Forum</h3>
                    <p class="text-slate-600 text-sm mb-3">Connect with other students and get quick answers</p>
                    <a href="#" class="text-primary font-semibold hover:underline">Visit Forum →</a>
                </div>

                <div class="bg-gradient-to-br from-primary/10 to-brand-green/10 rounded-xl border-2 border-primary/20 p-6">
                    <h3 class="font-bold text-slate-800 mb-3 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">lightbulb</span>
                        Quick Links
                    </h3>
                    <ul class="space-y-2 text-sm">
                        <li><a href="help-center.php" class="text-slate-700 hover:text-primary">→ Help Center</a></li>
                        <li><a href="safety-guide.php" class="text-slate-700 hover:text-primary">→ Safety Guide</a></li>
                        <li><a href="terms-of-service.php" class="text-slate-700 hover:text-primary">→ Terms of Service</a></li>
                        <li><a href="privacy-policy.php" class="text-slate-700 hover:text-primary">→ Privacy Policy</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </main>

    <?php include_once 'includes/footer.php'; ?>
</body>
</html>
