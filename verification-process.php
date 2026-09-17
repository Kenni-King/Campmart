<?php
session_start();
include_once "includes/controller.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Verification Process | CampMart</title>
    <script src="tailwind34.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet" />
    <script id="tailwind-config">
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        "primary": "#f48c25",
                        "secondary": "#FF6B35",
                        "accent": "#FFE66D",
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
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-background-main min-h-screen">
    <?php include_once 'includes/header.php'; ?>

    <main class="max-w-[1200px] mx-auto px-6 py-12">
        <!-- Hero Section -->
        <div class="text-center mb-12">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-blue-100 mb-6">
                <span class="material-symbols-outlined text-5xl text-blue-600 fill-1">verified</span>
            </div>
            <h1 class="text-4xl md:text-5xl font-extrabold text-brand-green mb-4">Student Verification Process</h1>
            <p class="text-xl text-slate-600 max-w-3xl mx-auto">Building trust in our campus community through verified student accounts</p>
        </div>

        <!-- Why Verification Matters -->
        <div class="bg-gradient-to-br from-blue-50 to-primary/5 rounded-2xl p-8 mb-12 border-2 border-blue-200">
            <h2 class="text-2xl font-bold text-brand-green mb-6 flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">stars</span>
                Why Verification Matters
            </h2>
            <div class="grid md:grid-cols-3 gap-6">
                <div class="bg-white rounded-xl p-6 border border-blue-100">
                    <span class="material-symbols-outlined text-4xl text-blue-600 mb-4 block">security</span>
                    <h3 class="font-bold text-slate-800 mb-2">Enhanced Security</h3>
                    <p class="text-sm text-slate-600">Verified accounts reduce fraud and create a safer marketplace for everyone</p>
                </div>
                <div class="bg-white rounded-xl p-6 border border-blue-100">
                    <span class="material-symbols-outlined text-4xl text-emerald-600 mb-4 block">handshake</span>
                    <h3 class="font-bold text-slate-800 mb-2">Build Trust</h3>
                    <p class="text-sm text-slate-600">Buyers and sellers can transact confidently knowing they're dealing with real students</p>
                </div>
                <div class="bg-white rounded-xl p-6 border border-blue-100">
                    <span class="material-symbols-outlined text-4xl text-primary mb-4 block">workspace_premium</span>
                    <h3 class="font-bold text-slate-800 mb-2">Exclusive Benefits</h3>
                    <p class="text-sm text-slate-600">Access premium features and priority support with your verified status</p>
                </div>
            </div>
        </div>

        <!-- Verification Steps -->
        <div class="mb-12">
            <h2 class="text-3xl font-bold text-brand-green mb-8 text-center">How to Get Verified</h2>
            <div class="space-y-6">
                <!-- Step 1 -->
                <div class="bg-white rounded-xl border-2 border-slate-200 p-6 md:p-8 hover:border-primary transition-all">
                    <div class="flex items-start gap-6">
                        <div class="w-16 h-16 rounded-full bg-blue-100 flex items-center justify-center shrink-0">
                            <span class="text-2xl font-black text-blue-600">1</span>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-xl font-bold text-slate-800 mb-3">Create Your Account</h3>
                            <p class="text-slate-600 mb-4">Sign up for CampMart using your personal information. You'll need to provide:</p>
                            <ul class="space-y-2 text-slate-600 ml-4">
                                <li class="flex items-start gap-2">
                                    <span class="material-symbols-outlined text-primary text-sm mt-0.5">check_circle</span>
                                    <span>Full name (as it appears on your student ID)</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <span class="material-symbols-outlined text-primary text-sm mt-0.5">check_circle</span>
                                    <span>Valid email address</span>
                                </li>
                                <li class="flex items-start gap-2">
                                    <span class="material-symbols-outlined text-primary text-sm mt-0.5">check_circle</span>
                                    <span>Strong password</span>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Step 2 -->
                <div class="bg-white rounded-xl border-2 border-slate-200 p-6 md:p-8 hover:border-primary transition-all">
                    <div class="flex items-start gap-6">
                        <div class="w-16 h-16 rounded-full bg-emerald-100 flex items-center justify-center shrink-0">
                            <span class="text-2xl font-black text-emerald-600">2</span>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-xl font-bold text-slate-800 mb-3">Verify Your Email Address</h3>
                            <p class="text-slate-600 mb-4">Use the email address you check most often for faster and easier verification:</p>
                            <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4 mb-4">
                                <h4 class="font-bold text-emerald-900 mb-2 flex items-center gap-2">
                                    <span class="material-symbols-outlined">mail</span>
                                    Accepted Email Formats:
                                </h4>
                                <ul class="space-y-1 text-sm text-emerald-800">
                                    <li>• yourname@gmail.com</li>
                                    <li>• student.name@yahoo.com</li>
                                    <li>• yourname@university.edu</li>
                                </ul>
                            </div>
                            <div class="flex items-start gap-3 bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <span class="material-symbols-outlined text-blue-600">info</span>
                                <p class="text-sm text-blue-800"><strong>Tip:</strong> Use your most frequently used email address so you can quickly receive and confirm your verification message.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 3 -->
                <div class="bg-white rounded-xl border-2 border-slate-200 p-6 md:p-8 hover:border-primary transition-all">
                    <div class="flex items-start gap-6">
                        <div class="w-16 h-16 rounded-full bg-amber-100 flex items-center justify-center shrink-0">
                            <span class="text-2xl font-black text-amber-600">3</span>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-xl font-bold text-slate-800 mb-3">Confirm Email Verification</h3>
                            <p class="text-slate-600 mb-4">Check your university email inbox for our verification message:</p>
                            <div class="grid md:grid-cols-2 gap-4">
                                <div class="border border-slate-200 rounded-lg p-4">
                                    <h4 class="font-bold text-slate-800 mb-2 flex items-center gap-2">
                                        <span class="material-symbols-outlined text-primary">looks_one</span>
                                        Receive Email
                                    </h4>
                                    <p class="text-sm text-slate-600">You'll get a verification email within 2-3 minutes of registration</p>
                                </div>
                                <div class="border border-slate-200 rounded-lg p-4">
                                    <h4 class="font-bold text-slate-800 mb-2 flex items-center gap-2">
                                        <span class="material-symbols-outlined text-primary">looks_two</span>
                                        Click Link
                                    </h4>
                                    <p class="text-sm text-slate-600">Click the verification link in the email to confirm your address</p>
                                </div>
                            </div>
                            <div class="mt-4 bg-amber-50 border border-amber-200 rounded-lg p-4">
                                <p class="text-sm text-amber-800"><strong>Didn't receive the email?</strong> Check your spam folder or request a new verification email from your account settings.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 4 -->
                <div class="bg-white rounded-xl border-2 border-slate-200 p-6 md:p-8 hover:border-primary transition-all">
                    <div class="flex items-start gap-6">
                        <div class="w-16 h-16 rounded-full bg-purple-100 flex items-center justify-center shrink-0">
                            <span class="text-2xl font-black text-purple-600">4</span>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-xl font-bold text-slate-800 mb-3">Upload Student ID (Optional)</h3>
                            <p class="text-slate-600 mb-4">For enhanced verification, you can optionally upload a photo of your student ID card:</p>
                            <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                                <h4 class="font-bold text-purple-900 mb-3">📸 ID Photo Requirements:</h4>
                                <ul class="space-y-2 text-sm text-purple-800">
                                    <li class="flex items-start gap-2">
                                        <span class="material-symbols-outlined text-sm mt-0.5">check_circle</span>
                                        <span>Clear, readable photo showing your full student ID</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <span class="material-symbols-outlined text-sm mt-0.5">check_circle</span>
                                        <span>Must show your name, photo, and student number</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <span class="material-symbols-outlined text-sm mt-0.5">check_circle</span>
                                        <span>Accepted formats: JPG, PNG (max 5MB)</span>
                                    </li>
                                    <li class="flex items-start gap-2">
                                        <span class="material-symbols-outlined text-sm mt-0.5">check_circle</span>
                                        <span>Your ID information will be kept confidential</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Step 5 -->
                <div class="bg-white rounded-xl border-2 border-emerald-200 p-6 md:p-8 bg-gradient-to-br from-emerald-50 to-white">
                    <div class="flex items-start gap-6">
                        <div class="w-16 h-16 rounded-full bg-emerald-600 flex items-center justify-center shrink-0">
                            <span class="material-symbols-outlined text-3xl text-white fill-1">verified</span>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-xl font-bold text-emerald-900 mb-3">Get Verified! 🎉</h3>
                            <p class="text-slate-700 mb-4">Once approved, you'll see the verified badge on your profile:</p>
                            <div class="flex items-center gap-3 bg-white border-2 border-emerald-200 rounded-lg p-4 mb-4">
                                <div class="w-12 h-12 rounded-full bg-slate-200"></div>
                                <div>
                                    <p class="font-bold text-slate-800 flex items-center gap-2">
                                        Your Name
                                        <span class="material-symbols-outlined text-blue-600 fill-1">verified</span>
                                    </p>
                                    <p class="text-sm text-slate-600">Verified Student</p>
                                </div>
                            </div>
                            <p class="text-sm text-slate-600">Email verification is usually instant. Student ID verification takes 1-2 business days.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Verification Benefits -->
        <div class="bg-white rounded-xl border border-slate-200 p-8 mb-12">
            <h2 class="text-2xl font-bold text-brand-green mb-6 text-center">Benefits of Being Verified</h2>
            <div class="grid md:grid-cols-2 lg:grid-cols-4 gap-6">
                <div class="text-center">
                    <div class="w-16 h-16 rounded-full bg-blue-100 flex items-center justify-center mx-auto mb-4">
                        <span class="material-symbols-outlined text-2xl text-blue-600">badge</span>
                    </div>
                    <h3 class="font-bold text-slate-800 mb-2">Verified Badge</h3>
                    <p class="text-sm text-slate-600">Display the trusted blue checkmark on your profile</p>
                </div>
                <div class="text-center">
                    <div class="w-16 h-16 rounded-full bg-emerald-100 flex items-center justify-center mx-auto mb-4">
                        <span class="material-symbols-outlined text-2xl text-emerald-600">trending_up</span>
                    </div>
                    <h3 class="font-bold text-slate-800 mb-2">Higher Visibility</h3>
                    <p class="text-sm text-slate-600">Your listings appear higher in search results</p>
                </div>
                <div class="text-center">
                    <div class="w-16 h-16 rounded-full bg-purple-100 flex items-center justify-center mx-auto mb-4">
                        <span class="material-symbols-outlined text-2xl text-purple-600">support_agent</span>
                    </div>
                    <h3 class="font-bold text-slate-800 mb-2">Priority Support</h3>
                    <p class="text-sm text-slate-600">Get faster response from our support team</p>
                </div>
                <div class="text-center">
                    <div class="w-16 h-16 rounded-full bg-amber-100 flex items-center justify-center mx-auto mb-4">
                        <span class="material-symbols-outlined text-2xl text-amber-600">lock_open</span>
                    </div>
                    <h3 class="font-bold text-slate-800 mb-2">Exclusive Features</h3>
                    <p class="text-sm text-slate-600">Access to premium marketplace features</p>
                </div>
            </div>
        </div>

        <!-- FAQ Section -->
        <div class="bg-white rounded-xl border border-slate-200 p-8 mb-12">
            <h2 class="text-2xl font-bold text-brand-green mb-6">Frequently Asked Questions</h2>
            <div class="space-y-4">
                <div class="border-b border-slate-200 pb-4">
                    <h3 class="font-bold text-slate-800 mb-2">How long does verification take?</h3>
                    <p class="text-slate-600">Email verification is instant. If you submit a student ID, manual review takes 1-2 business days.</p>
                </div>
                <div class="border-b border-slate-200 pb-4">
                    <h3 class="font-bold text-slate-800 mb-2">Is verification mandatory?</h3>
                    <p class="text-slate-600">While not mandatory, verified accounts have access to more features and inspire greater trust among other users.</p>
                </div>
                <div class="border-b border-slate-200 pb-4">
                    <h3 class="font-bold text-slate-800 mb-2">What if my university email doesn't work?</h3>
                    <p class="text-slate-600">Contact our support team with your student ID or enrollment letter, and we'll manually verify your account.</p>
                </div>
                <div class="border-b border-slate-200 pb-4">
                    <h3 class="font-bold text-slate-800 mb-2">Can I change my verified email later?</h3>
                    <p class="text-slate-600">Yes, but you'll need to reverify with your new university email address.</p>
                </div>
                <div class="pb-4">
                    <h3 class="font-bold text-slate-800 mb-2">Is my student ID information kept secure?</h3>
                    <p class="text-slate-600">Absolutely. We use bank-level encryption and never share your ID information with third parties.</p>
                </div>
            </div>
        </div>

        <!-- CTA Section -->
        <div class="bg-gradient-to-r from-brand-green to-primary rounded-xl p-8 text-center text-white">
            <h2 class="text-2xl font-bold mb-3">Ready to Get Verified?</h2>
            <p class="text-slate-100 mb-6 max-w-2xl mx-auto">Join thousands of verified students buying and selling safely on CampMart</p>
            <div class="flex flex-wrap gap-4 justify-center">
                <button onclick="window.location.href='signup.php'" class="bg-white text-brand-green px-8 py-3 rounded-lg font-bold hover:bg-slate-100 transition-all">
                    Sign Up Now
                </button>
                <button onclick="window.location.href='login.php'" class="bg-transparent border-2 border-white text-white px-8 py-3 rounded-lg font-bold hover:bg-white/10 transition-all">
                    Already Have Account?
                </button>
            </div>
        </div>
    </main>

    <?php include_once 'includes/footer.php'; ?>
</body>
</html>
