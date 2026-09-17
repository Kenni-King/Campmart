<?php 
session_start(); 
include_once 'includes/controller.php';

$message = '';
$messageType = '';

// Handle campus submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_campus'])) {
    $name = trim($_POST['name'] ?? '');
    $code = trim($_POST['code'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $country = trim($_POST['country'] ?? 'Nigeria');
    $website = trim($_POST['website'] ?? '');
    $submitter_name = trim($_POST['submitter_name'] ?? '');
    $submitter_email = trim($_POST['submitter_email'] ?? '');
    $additional_info = trim($_POST['additional_info'] ?? '');

    if (empty($name)) {
        $message = 'University name is required.';
        $messageType = 'error';
    } elseif (empty($code)) {
        $message = 'University code/abbreviation is required.';
        $messageType = 'error';
    } elseif (empty($submitter_name)) {
        $message = 'Your name is required.';
        $messageType = 'error';
    } elseif (empty($submitter_email) || !filter_var($submitter_email, FILTER_VALIDATE_EMAIL)) {
        $message = 'A valid email address is required.';
        $messageType = 'error';
    } else {
        // Check if university already exists
        $check_stmt = $db->prepare("SELECT id FROM universities WHERE code = ? OR name = ?");
        $check_stmt->bind_param('ss', $code, $name);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $message = 'This campus already exists in our database.';
            $messageType = 'error';
        } else {
            // Insert as pending university (status = inactive by default)
            $insert_stmt = $db->prepare("INSERT INTO universities (name, code, location, country, website, status, created_at) VALUES (?, ?, ?, ?, ?, 'inactive', NOW())");
            $insert_stmt->bind_param('sssss', $name, $code, $location, $country, $website);
            
            if ($insert_stmt->execute()) {
                $university_id = $db->insert_id;
                
                // Store submission details in a submissions table or send notification to admin
                // For now, we'll just log the submission info
                $log_stmt = $db->prepare("INSERT INTO campus_submissions (university_id, submitter_name, submitter_email, additional_info, submitted_at) VALUES (?, ?, ?, ?, NOW())");
                $log_stmt->bind_param('isss', $university_id, $submitter_name, $submitter_email, $additional_info);
                $log_stmt->execute();
                
                $message = 'Thank you! Your campus has been submitted for review. We\'ll verify and add it shortly.';
                $messageType = 'success';
                
                // Clear form on success
                $_POST = array();
            } else {
                $message = 'Failed to submit campus. Please try again.';
                $messageType = 'error';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Add Your Campus | CampMart</title>
    <script src="tailwind34.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&amp;display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet" />
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

<body class="bg-gradient-to-br from-surface via-white to-primary/5 font-display text-slate-900 antialiased min-h-screen">
    <div class="container mx-auto px-4 py-8 md:py-12">
        <!-- Header -->
        <div class="text-center mb-8">
            <a class="inline-flex items-center gap-3 mb-6" href="/">
                <div class="size-12 flex items-center justify-center bg-primary text-white rounded-xl shadow-xl">
                    <span class="material-symbols-outlined font-bold text-3xl">shopping_bag</span>
                </div>
                <span class="text-2xl font-extrabold tracking-tight text-primary">CampMart</span>
            </a>
            <h1 class="text-3xl md:text-4xl font-extrabold text-slate-900 mb-3">Add Your Campus</h1>
            <p class="text-slate-600 font-medium max-w-2xl mx-auto">
                Don't see your campus in our list? Help us expand by submitting your institution's details.
                We'll review and add it within 24-48 hours.
            </p>
        </div>

        <?php if ($message): ?>
            <div class="max-w-2xl mx-auto mb-6">
                <div class="p-4 <?= $messageType === 'success' ? 'bg-emerald-50 border border-emerald-200' : 'bg-red-50 border border-red-200' ?> rounded-xl flex items-center gap-3">
                    <span class="material-symbols-outlined text-2xl <?= $messageType === 'success' ? 'text-emerald-600' : 'text-red-600' ?>">
                        <?= $messageType === 'success' ? 'check_circle' : 'error' ?>
                    </span>
                    <p class="font-medium <?= $messageType === 'success' ? 'text-emerald-900' : 'text-red-900' ?>"><?= htmlspecialchars($message) ?></p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Form Container -->
        <div class="max-w-2xl mx-auto bg-white rounded-2xl shadow-xl border border-slate-100 overflow-hidden">
            <div class="bg-gradient-to-r from-primary to-primary/80 p-6 text-white">
                <div class="flex items-center gap-3">
                    <span class="material-symbols-outlined text-3xl">school</span>
                    <div>
                        <h2 class="text-xl font-bold">Campus Information</h2>
                        <p class="text-sm text-white/80">Fill in the details about your campus</p>
                    </div>
                </div>
            </div>

            <form method="POST" class="p-6 md:p-8 space-y-6">
                <!-- Campus Details -->
                <div class="space-y-5">
                    <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">info</span>
                        Campus Details
                    </h3>
                    
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2" for="name">
                            University/College Name <span class="text-red-500">*</span>
                        </label>
                        <input 
                            class="input-field" 
                            id="name" 
                            name="name" 
                            placeholder="e.g., University of Lagos" 
                            required 
                            type="text"
                            value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" />
                        <p class="text-xs text-slate-500 mt-1.5">Enter the full official name</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2" for="code">
                                Code/Abbreviation <span class="text-red-500">*</span>
                            </label>
                            <input 
                                class="input-field" 
                                id="code" 
                                name="code" 
                                placeholder="e.g., UNILAG" 
                                required 
                                type="text"
                                value="<?= htmlspecialchars($_POST['code'] ?? '') ?>" />
                            <p class="text-xs text-slate-500 mt-1.5">Common abbreviation</p>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2" for="location">
                                City/Location <span class="text-red-500">*</span>
                            </label>
                            <input 
                                class="input-field" 
                                id="location" 
                                name="location" 
                                placeholder="e.g., Lagos" 
                                required 
                                type="text"
                                value="<?= htmlspecialchars($_POST['location'] ?? '') ?>" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2" for="country">
                                Country <span class="text-red-500">*</span>
                            </label>
                            <input 
                                class="input-field" 
                                id="country" 
                                name="country" 
                                placeholder="e.g., Nigeria" 
                                required 
                                type="text"
                                value="<?= htmlspecialchars($_POST['country'] ?? 'Nigeria') ?>" />
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2" for="website">
                                Official Website (Optional)
                            </label>
                            <input 
                                class="input-field" 
                                id="website" 
                                name="website" 
                                placeholder="e.g., https://unilag.edu.ng" 
                                type="url"
                                value="<?= htmlspecialchars($_POST['website'] ?? '') ?>" />
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2" for="additional_info">
                            Additional Information (Optional)
                        </label>
                        <textarea 
                            class="input-field" 
                            id="additional_info" 
                            name="additional_info" 
                            rows="3" 
                            placeholder="Any additional details that might help us verify this campus..."><?= htmlspecialchars($_POST['additional_info'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- Submitter Details -->
                <div class="space-y-5 pt-6 border-t border-slate-200">
                    <h3 class="text-lg font-bold text-slate-900 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">person</span>
                        Your Contact Information
                    </h3>
                    
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2" for="submitter_name">
                            Your Name <span class="text-red-500">*</span>
                        </label>
                        <input 
                            class="input-field" 
                            id="submitter_name" 
                            name="submitter_name" 
                            placeholder="John Doe" 
                            required 
                            type="text"
                            value="<?= htmlspecialchars($_POST['submitter_name'] ?? '') ?>" />
                        <p class="text-xs text-slate-500 mt-1.5">We'll contact you if we need clarification</p>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2" for="submitter_email">
                            Your Email <span class="text-red-500">*</span>
                        </label>
                        <input 
                            class="input-field" 
                            id="submitter_email" 
                            name="submitter_email" 
                            placeholder="youremail@example.com" 
                            required 
                            type="email"
                            value="<?= htmlspecialchars($_POST['submitter_email'] ?? '') ?>" />
                    </div>
                </div>

                <!-- Info Box -->
                <div class="bg-blue-50 border border-blue-200 rounded-xl p-4 flex gap-3">
                    <span class="material-symbols-outlined text-blue-600 text-xl">info</span>
                    <div class="text-sm text-blue-900">
                        <p class="font-semibold mb-1">What happens next?</p>
                        <ul class="list-disc list-inside space-y-1 text-blue-800">
                            <li>Your submission will be reviewed by our admin team</li>
                            <li>We'll verify the campus information</li>
                            <li>Once approved, the campus will be available for registration</li>
                            <li>You'll receive an email notification about the status</li>
                        </ul>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="pt-4">
                    <button class="btn-primary" name="submit_campus" type="submit">
                        <span class="material-symbols-outlined">send</span>
                        Submit Campus for Review
                    </button>
                </div>

                <div class="text-center">
                    <a href="signup.php" class="text-sm text-primary font-semibold hover:underline flex items-center justify-center gap-1">
                        <span class="material-symbols-outlined text-base">arrow_back</span>
                        Back to Sign Up
                    </a>
                </div>
            </form>
        </div>

        <!-- Footer -->
        <div class="text-center mt-8 text-slate-500 text-sm">
            <p>&copy; <?= date('Y') ?> CampMart. All rights reserved.</p>
        </div>
    </div>

    <script>
        // Auto-clear success message and redirect after 5 seconds
        <?php if ($messageType === 'success'): ?>
        setTimeout(function() {
            window.location.href = 'signup.php';
        }, 5000);
        <?php endif; ?>
    </script>
</body>
</html>
