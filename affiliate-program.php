<?php
session_start();
include_once "includes/controller.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Affiliate Program | CampMart</title>
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
                <span class="material-symbols-outlined text-5xl text-primary">groups</span>
            </div>
            <h1 class="text-4xl md:text-5xl font-extrabold text-brand-green mb-4">CampMart Affiliate Program</h1>
            <p class="text-xl text-slate-600 max-w-3xl mx-auto">Earn while you promote! Join our affiliate program and earn commission for every successful referral.</p>
        </div>

        <!-- Benefits -->
        <div class="bg-gradient-to-br from-primary/10 to-brand-green/10 rounded-2xl p-8 mb-12 border-2 border-primary/20">
            <h2 class="text-2xl font-bold text-brand-green mb-6 text-center">Why Join Our Affiliate Program?</h2>
            <div class="grid md:grid-cols-3 gap-6">
                <div class="bg-white rounded-xl p-6 text-center">
                    <span class="material-symbols-outlined text-5xl text-primary mb-4 block">trending_up</span>
                    <h3 class="font-bold text-lg text-slate-800 mb-2">Earn 10% Commission</h3>
                    <p class="text-sm text-slate-600">Get 10% of every successful transaction from your referrals</p>
                </div>
                <div class="bg-white rounded-xl p-6 text-center">
                    <span class="material-symbols-outlined text-5xl text-emerald-600 mb-4 block">account_balance</span>
                    <h3 class="font-bold text-lg text-slate-800 mb-2">Weekly Payouts</h3>
                    <p class="text-sm text-slate-600">Receive your earnings every week directly to your account</p>
                </div>
                <div class="bg-white rounded-xl p-6 text-center">
                    <span class="material-symbols-outlined text-5xl text-blue-600 mb-4 block">dashboard</span>
                    <h3 class="font-bold text-lg text-slate-800 mb-2">Real-Time Tracking</h3>
                    <p class="text-sm text-slate-600">Monitor your referrals and earnings with our dashboard</p>
                </div>
            </div>
        </div>

        <!-- How It Works -->
        <div class="mb-12">
            <h2 class="text-3xl font-bold text-brand-green mb-8 text-center">How It Works</h2>
            <div class="grid md:grid-cols-4 gap-6">
                <div class="text-center">
                    <div class="w-16 h-16 rounded-full bg-blue-100 flex items-center justify-center mx-auto mb-4">
                        <span class="text-2xl font-black text-blue-600">1</span>
                    </div>
                    <h3 class="font-bold text-slate-800 mb-2">Sign Up</h3>
                    <p class="text-sm text-slate-600">Register for the affiliate program and get your unique referral link</p>
                </div>
                <div class="text-center">
                    <div class="w-16 h-16 rounded-full bg-emerald-100 flex items-center justify-center mx-auto mb-4">
                        <span class="text-2xl font-black text-emerald-600">2</span>
                    </div>
                    <h3 class="font-bold text-slate-800 mb-2">Share</h3>
                    <p class="text-sm text-slate-600">Promote CampMart using your link on social media, blogs, or groups</p>
                </div>
                <div class="text-center">
                    <div class="w-16 h-16 rounded-full bg-amber-100 flex items-center justify-center mx-auto mb-4">
                        <span class="text-2xl font-black text-amber-600">3</span>
                    </div>
                    <h3 class="font-bold text-slate-800 mb-2">Track</h3>
                    <p class="text-sm text-slate-600">Monitor clicks, signups, and transactions from your dashboard</p>
                </div>
                <div class="text-center">
                    <div class="w-16 h-16 rounded-full bg-purple-100 flex items-center justify-center mx-auto mb-4">
                        <span class="text-2xl font-black text-purple-600">4</span>
                    </div>
                    <h3 class="font-bold text-slate-800 mb-2">Earn</h3>
                    <p class="text-sm text-slate-600">Get paid weekly for every successful transaction from your referrals</p>
                </div>
            </div>
        </div>

        <!-- Commission Structure -->
        <div class="bg-white rounded-xl border border-slate-200 p-8 mb-12">
            <h2 class="text-2xl font-bold text-brand-green mb-6">Commission Structure</h2>
            <div class="space-y-4">
                <div class="flex items-center justify-between p-4 bg-slate-50 rounded-lg">
                    <div>
                        <h3 class="font-bold text-slate-800">Product Sales</h3>
                        <p class="text-sm text-slate-600">Commission on product transactions</p>
                    </div>
                    <span class="text-2xl font-black text-primary">10%</span>
                </div>
                <div class="flex items-center justify-between p-4 bg-slate-50 rounded-lg">
                    <div>
                        <h3 class="font-bold text-slate-800">Service Bookings</h3>
                        <p class="text-sm text-slate-600">Commission on service orders</p>
                    </div>
                    <span class="text-2xl font-black text-primary">10%</span>
                </div>
                <div class="flex items-center justify-between p-4 bg-emerald-50 rounded-lg border border-emerald-200">
                    <div>
                        <h3 class="font-bold text-emerald-900">Bonus: Top Performer</h3>
                        <p class="text-sm text-emerald-700">Top 10 affiliates each month</p>
                    </div>
                    <span class="text-2xl font-black text-emerald-600">+5%</span>
                </div>
            </div>
        </div>

        <!-- Requirements -->
        <div class="bg-white rounded-xl border border-slate-200 p-8 mb-12">
            <h2 class="text-2xl font-bold text-brand-green mb-6">Program Requirements</h2>
            <div class="grid md:grid-cols-2 gap-6">
                <div>
                    <h3 class="font-bold text-slate-800 mb-3 flex items-center gap-2">
                        <span class="material-symbols-outlined text-emerald-600">check_circle</span>
                        Eligibility
                    </h3>
                    <ul class="space-y-2 text-slate-600 text-sm ml-8">
                        <li>• Active CampMart account</li>
                        <li>• Verified student status</li>
                        <li>• Good standing (no violations)</li>
                        <li>• Minimum 18 years old</li>
                    </ul>
                </div>
                <div>
                    <h3 class="font-bold text-slate-800 mb-3 flex items-center gap-2">
                        <span class="material-symbols-outlined text-blue-600">payments</span>
                        Payment Terms
                    </h3>
                    <ul class="space-y-2 text-slate-600 text-sm ml-8">
                        <li>• Minimum payout: ₦5,000</li>
                        <li>• Weekly payment schedule</li>
                        <li>• Bank transfer or wallet</li>
                        <li>• 30-day cookie tracking</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Marketing Materials -->
        <div class="bg-white rounded-xl border border-slate-200 p-8 mb-12">
            <h2 class="text-2xl font-bold text-brand-green mb-6">Marketing Materials Provided</h2>
            <div class="grid md:grid-cols-4 gap-4">
                <div class="text-center p-4 border border-slate-200 rounded-lg">
                    <span class="material-symbols-outlined text-4xl text-primary mb-2 block">link</span>
                    <p class="text-sm font-bold text-slate-700">Custom Referral Links</p>
                </div>
                <div class="text-center p-4 border border-slate-200 rounded-lg">
                    <span class="material-symbols-outlined text-4xl text-primary mb-2 block">image</span>
                    <p class="text-sm font-bold text-slate-700">Banner Images</p>
                </div>
                <div class="text-center p-4 border border-slate-200 rounded-lg">
                    <span class="material-symbols-outlined text-4xl text-primary mb-2 block">article</span>
                    <p class="text-sm font-bold text-slate-700">Pre-written Content</p>
                </div>
                <div class="text-center p-4 border border-slate-200 rounded-lg">
                    <span class="material-symbols-outlined text-4xl text-primary mb-2 block">share</span>
                    <p class="text-sm font-bold text-slate-700">Social Media Posts</p>
                </div>
            </div>
        </div>

        <!-- CTA -->
        <div class="bg-gradient-to-r from-brand-green to-primary rounded-xl p-8 text-center text-white">
            <h2 class="text-2xl font-bold mb-3">Ready to Start Earning?</h2>
            <p class="text-slate-100 mb-6 max-w-2xl mx-auto">Join hundreds of students already earning through our affiliate program</p>
            <button onclick="window.location.href='affiliate-center.php'" class="bg-white text-brand-green px-8 py-3 rounded-lg font-bold hover:bg-slate-100 transition-all">
                Join Affiliate Program
            </button>
        </div>
    </main>

    <?php include_once 'includes/footer.php'; ?>
</body>
</html>
