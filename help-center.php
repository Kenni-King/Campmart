<?php
session_start();
include_once "includes/controller.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Help Center | CampMart</title>
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
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-blue-100 mb-6">
                <span class="material-symbols-outlined text-5xl text-blue-600">help</span>
            </div>
            <h1 class="text-4xl font-extrabold text-brand-green mb-4">Help Center</h1>
            <p class="text-xl text-slate-600">Find answers to your questions about using CampMart</p>
        </div>

        <!-- Search -->
        <div class="max-w-2xl mx-auto mb-12">
            <div class="relative">
                <input type="text" placeholder="Search for help..." class="w-full px-6 py-4 rounded-full border-2 border-slate-200 focus:border-primary pl-14" />
                <span class="material-symbols-outlined absolute left-5 top-1/2 -translate-y-1/2 text-slate-400 text-2xl">search</span>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="grid md:grid-cols-4 gap-4 mb-12">
            <a href="#getting-started" class="bg-white border border-slate-200 rounded-xl p-6 hover:border-primary transition-all text-center">
                <span class="material-symbols-outlined text-4xl text-primary mb-3 block">rocket_launch</span>
                <h3 class="font-bold text-slate-800">Getting Started</h3>
            </a>
            <a href="#buying" class="bg-white border border-slate-200 rounded-xl p-6 hover:border-primary transition-all text-center">
                <span class="material-symbols-outlined text-4xl text-emerald-600 mb-3 block">shopping_cart</span>
                <h3 class="font-bold text-slate-800">Buying</h3>
            </a>
            <a href="#selling" class="bg-white border border-slate-200 rounded-xl p-6 hover:border-primary transition-all text-center">
                <span class="material-symbols-outlined text-4xl text-blue-600 mb-3 block">sell</span>
                <h3 class="font-bold text-slate-800">Selling</h3>
            </a>
            <a href="#account" class="bg-white border border-slate-200 rounded-xl p-6 hover:border-primary transition-all text-center">
                <span class="material-symbols-outlined text-4xl text-purple-600 mb-3 block">account_circle</span>
                <h3 class="font-bold text-slate-800">Account</h3>
            </a>
        </div>

        <!-- FAQ Sections -->
        <div class="space-y-8">
            <!-- Getting Started -->
            <div id="getting-started" class="bg-white rounded-xl border border-slate-200 p-8">
                <h2 class="text-2xl font-bold text-brand-green mb-6">Getting Started</h2>
                <div class="space-y-4">
                    <details class="border-b border-slate-200 pb-4">
                        <summary class="font-bold text-slate-800 cursor-pointer hover:text-primary">How do I create an account?</summary>
                        <p class="text-slate-600 mt-3 ml-4">Click "Sign Up" in the top right corner, enter your details, and verify your university email address. You'll be able to start buying and selling immediately after verification.</p>
                    </details>
                    <details class="border-b border-slate-200 pb-4">
                        <summary class="font-bold text-slate-800 cursor-pointer hover:text-primary">Is CampMart free to use?</summary>
                        <p class="text-slate-600 mt-3 ml-4">Yes! Creating an account and browsing listings is completely free. We only charge a small commission (5%) on successful sales to maintain the platform.</p>
                    </details>
                    <details class="border-b border-slate-200 pb-4">
                        <summary class="font-bold text-slate-800 cursor-pointer hover:text-primary">How do I verify my student status?</summary>
                        <p class="text-slate-600 mt-3 ml-4">Use your official university email address during signup. We'll send a verification link to confirm you're a current student. For more details, visit our <a href="verification-process.php" class="text-primary hover:underline">Verification Process</a> page.</p>
                    </details>
                </div>
            </div>

            <!-- Buying -->
            <div id="buying" class="bg-white rounded-xl border border-slate-200 p-8">
                <h2 class="text-2xl font-bold text-brand-green mb-6">Buying on CampMart</h2>
                <div class="space-y-4">
                    <details class="border-b border-slate-200 pb-4">
                        <summary class="font-bold text-slate-800 cursor-pointer hover:text-primary">How do I purchase an item?</summary>
                        <p class="text-slate-600 mt-3 ml-4">Browse or search for items, click on a listing to view details, and click "Contact Seller" or "Chat" to arrange a meeting. Always meet in safe campus locations.</p>
                    </details>
                    <details class="border-b border-slate-200 pb-4">
                        <summary class="font-bold text-slate-800 cursor-pointer hover:text-primary">What payment methods are accepted?</summary>
                        <p class="text-slate-600 mt-3 ml-4">Payment is typically arranged between buyer and seller. We recommend cash payments after inspecting items in person. Never pay before seeing the item.</p>
                    </details>
                    <details class="border-b border-slate-200 pb-4">
                        <summary class="font-bold text-slate-800 cursor-pointer hover:text-primary">How can I tell if a seller is trustworthy?</summary>
                        <p class="text-slate-600 mt-3 ml-4">Look for the verified badge, check seller ratings and reviews, review their transaction history, and always meet in public campus locations.</p>
                    </details>
                    <details class="border-b border-slate-200 pb-4">
                        <summary class="font-bold text-slate-800 cursor-pointer hover:text-primary">What if I receive a defective item?</summary>
                        <p class="text-slate-600 mt-3 ml-4">Always thoroughly inspect items before payment. If you receive a defective item, contact the seller immediately through CampMart chat. If unresolved, report the issue to our support team.</p>
                    </details>
                </div>
            </div>

            <!-- Selling -->
            <div id="selling" class="bg-white rounded-xl border border-slate-200 p-8">
                <h2 class="text-2xl font-bold text-brand-green mb-6">Selling on CampMart</h2>
                <div class="space-y-4">
                    <details class="border-b border-slate-200 pb-4">
                        <summary class="font-bold text-slate-800 cursor-pointer hover:text-primary">How do I list an item for sale?</summary>
                        <p class="text-slate-600 mt-3 ml-4">Click "Start Selling" or go to "My Listings," click "New Listing," fill in the item details, upload photos, set a price, and publish. Your listing will go live after approval.</p>
                    </details>
                    <details class="border-b border-slate-200 pb-4">
                        <summary class="font-bold text-slate-800 cursor-pointer hover:text-primary">What fees does CampMart charge?</summary>
                        <p class="text-slate-600 mt-3 ml-4">CampMart charges a 5% commission on completed sales. There are no listing fees or upfront costs. You only pay when you successfully sell an item.</p>
                    </details>
                    <details class="border-b border-slate-200 pb-4">
                        <summary class="font-bold text-slate-800 cursor-pointer hover:text-primary">How do I take good product photos?</summary>
                        <p class="text-slate-600 mt-3 ml-4">Use natural lighting, capture multiple angles, show any defects honestly, keep backgrounds clean, and ensure photos are clear and in focus. Good photos increase sales!</p>
                    </details>
                    <details class="border-b border-slate-200 pb-4">
                        <summary class="font-bold text-slate-800 cursor-pointer hover:text-primary">How long does it take for my listing to be approved?</summary>
                        <p class="text-slate-600 mt-3 ml-4">Most listings are reviewed and approved within 24 hours. Make sure your listing follows our guidelines for faster approval.</p>
                    </details>
                </div>
            </div>

            <!-- Account & Settings -->
            <div id="account" class="bg-white rounded-xl border border-slate-200 p-8">
                <h2 class="text-2xl font-bold text-brand-green mb-6">Account & Settings</h2>
                <div class="space-y-4">
                    <details class="border-b border-slate-200 pb-4">
                        <summary class="font-bold text-slate-800 cursor-pointer hover:text-primary">How do I change my password?</summary>
                        <p class="text-slate-600 mt-3 ml-4">Go to "My Settings" → "Security" → "Change Password." Enter your current password and your new password twice to confirm the change.</p>
                    </details>
                    <details class="border-b border-slate-200 pb-4">
                        <summary class="font-bold text-slate-800 cursor-pointer hover:text-primary">Can I delete my account?</summary>
                        <p class="text-slate-600 mt-3 ml-4">Yes. Go to "My Settings" → "Account" → "Delete Account." Note that this action is permanent and cannot be undone. Complete all pending transactions first.</p>
                    </details>
                    <details class="border-b border-slate-200 pb-4">
                        <summary class="font-bold text-slate-800 cursor-pointer hover:text-primary">How do I update my profile information?</summary>
                        <p class="text-slate-600 mt-3 ml-4">Visit "My Profile" from your dashboard. You can update your name, profile picture, bio, and contact preferences. Changes are saved automatically.</p>
                    </details>
                    <details class="border-b border-slate-200 pb-4">
                        <summary class="font-bold text-slate-800 cursor-pointer hover:text-primary">How do notifications work?</summary>
                        <p class="text-slate-600 mt-3 ml-4">You'll receive notifications for messages, offers, listing updates, and more. Manage notification preferences in "My Settings" → "Notifications."</p>
                    </details>
                </div>
            </div>
        </div>

        <!-- Still Need Help -->
        <div class="mt-12 bg-brand-green rounded-xl p-8 text-center text-white">
            <h2 class="text-2xl font-bold mb-3">Still Need Help?</h2>
            <p class="text-slate-100 mb-6">Can't find what you're looking for? Our support team is here to help.</p>
            <button onclick="window.location.href='contact-us.php'" class="bg-white text-brand-green px-8 py-3 rounded-lg font-bold hover:bg-slate-100 transition-all">
                Contact Support
            </button>
        </div>
    </main>

    <?php include_once 'includes/footer.php'; ?>
</body>
</html>
