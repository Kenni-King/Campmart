<?php
session_start();
include_once "includes/controller.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Campus Safety Guide | CampMart</title>
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
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-primary/10 mb-6">
                <span class="material-symbols-outlined text-5xl text-primary">verified_user</span>
            </div>
            <h1 class="text-4xl md:text-5xl font-extrabold text-brand-green mb-4">Campus Safety Guide</h1>
            <p class="text-xl text-slate-600 max-w-3xl mx-auto">Your security is our priority. Learn how to stay safe while buying and selling on CampMart.</p>
        </div>

        <!-- Quick Safety Tips -->
        <div class="bg-gradient-to-br from-primary/5 to-brand-green/5 rounded-2xl p-4 mb-12 border-2 border-primary/20">
            <h2 class="text-2xl font-bold text-brand-green mb-6 flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">speed</span>
                Quick Safety Tips
            </h2>
            <div class="grid md:grid-cols-2 gap-4">
                <div class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-2xl text-primary shrink-0">check_circle</span>
                    <p class="text-slate-700"><strong>Meet on Campus:</strong> Always arrange meetings in public campus locations</p>
                </div>
                <div class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-2xl text-primary shrink-0">check_circle</span>
                    <p class="text-slate-700"><strong>Verify Identity:</strong> Only trade with verified student accounts</p>
                </div>
                <div class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-2xl text-primary shrink-0">check_circle</span>
                    <p class="text-slate-700"><strong>Inspect Items:</strong> Thoroughly check products before payment</p>
                </div>
                <div class="flex items-start gap-3">
                    <span class="material-symbols-outlined text-2xl text-primary shrink-0">check_circle</span>
                    <p class="text-slate-700"><strong>Use CampMart Chat:</strong> Keep all communication on the platform</p>
                </div>
            </div>
        </div>

        <!-- Main Content Sections -->
        <div class="space-y-8">
            <!-- Safe Meeting Practices -->
            <div class="bg-white rounded-xl border border-slate-200 p-4">
                <div class="flex items-start gap-4 mb-6">
                    <div class="w-12 h-12 rounded-lg bg-primary/10 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-2xl text-primary">location_on</span>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold text-brand-green mb-2">Safe Meeting Locations</h2>
                        <p class="text-slate-600">Choose the right place for your campus transactions</p>
                    </div>
                </div>
                <div class="space-y-4 ml-0">
                    <div class="border-l-4 border-emerald-500 pl-4">
                        <h3 class="font-bold text-slate-800 mb-2">✅ Recommended Locations:</h3>
                        <ul class="space-y-2 text-slate-600">
                            <li>• Campus library main entrance</li>
                            <li>• Student union building common areas</li>
                            <li>• University cafeteria during busy hours</li>
                            <li>• Campus security office vicinity</li>
                            <li>• Well-lit academic building lobbies</li>
                        </ul>
                    </div>
                    <div class="border-l-4 border-red-500 pl-4">
                        <h3 class="font-bold text-slate-800 mb-2">❌ Avoid These Locations:</h3>
                        <ul class="space-y-2 text-slate-600">
                            <li>• Private dorm rooms or apartments</li>
                            <li>• Isolated parking lots after dark</li>
                            <li>• Off-campus locations</li>
                            <li>• Secluded campus areas</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Account Verification -->
            <div class="bg-white rounded-xl border border-slate-200 p-4">
                <div class="flex items-start gap-4 mb-6">
                    <div class="w-12 h-12 rounded-lg bg-blue-100 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-2xl text-blue-600">verified</span>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold text-brand-green mb-2">Verify Before You Buy</h2>
                        <p class="text-slate-600">How to identify trustworthy sellers and buyers</p>
                    </div>
                </div>
                <div class="space-y-4 ml-0">
                    <div class="bg-blue-50 rounded-lg p-4">
                        <div class="flex items-center gap-2 mb-2">
                            <span class="material-symbols-outlined text-blue-600 fill-1">verified</span>
                            <h3 class="font-bold text-blue-900">Verified Student Badge</h3>
                        </div>
                        <p class="text-blue-800 text-sm">Look for the blue verified badge next to usernames. This means the user has confirmed their student status with their university email.</p>
                    </div>
                    <div class="grid md:grid-cols-2 gap-4">
                        <div class="border border-slate-200 rounded-lg p-4">
                            <h4 class="font-bold text-slate-800 mb-2">Check User Profile:</h4>
                            <ul class="space-y-1 text-sm text-slate-600">
                                <li>• View seller ratings and reviews</li>
                                <li>• Check account creation date</li>
                                <li>• Review previous transactions</li>
                                <li>• Look for completed deals</li>
                            </ul>
                        </div>
                        <div class="border border-slate-200 rounded-lg p-4">
                            <h4 class="font-bold text-slate-800 mb-2">Red Flags to Watch:</h4>
                            <ul class="space-y-1 text-sm text-slate-600">
                                <li>• Brand new accounts (less than 1 week)</li>
                                <li>• No profile picture or information</li>
                                <li>• Poor ratings or negative reviews</li>
                                <li>• Pressure to transact quickly</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Payment Safety -->
            <div class="bg-white rounded-xl border border-slate-200 p-4">
                <div class="flex items-start gap-4 mb-6">
                    <div class="w-12 h-12 rounded-lg bg-emerald-100 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-2xl text-emerald-600">payments</span>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold text-brand-green mb-2">Secure Payment Practices</h2>
                        <p class="text-slate-600">Protect yourself during transactions</p>
                    </div>
                </div>
                <div class="space-y-4 ml-0">
                    <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-5">
                        <h3 class="font-bold text-emerald-900 mb-3 flex items-center gap-2">
                            <span class="material-symbols-outlined">lightbulb</span>
                            Best Practice: Inspect Before Paying
                        </h3>
                        <p class="text-emerald-800 mb-3">Always thoroughly inspect items before handing over payment. For electronics, ask to see them powered on and functioning.</p>
                        <div class="grid md:grid-cols-3 gap-3 mt-4">
                            <div class="bg-white rounded-lg p-3 text-center">
                                <span class="material-symbols-outlined text-3xl text-emerald-600 mb-2">search</span>
                                <p class="text-xs font-bold text-slate-700">1. Inspect Item</p>
                            </div>
                            <div class="bg-white rounded-lg p-3 text-center">
                                <span class="material-symbols-outlined text-3xl text-emerald-600 mb-2">task_alt</span>
                                <p class="text-xs font-bold text-slate-700">2. Verify Condition</p>
                            </div>
                            <div class="bg-white rounded-lg p-3 text-center">
                                <span class="material-symbols-outlined text-3xl text-emerald-600 mb-2">payments</span>
                                <p class="text-xs font-bold text-slate-700">3. Then Pay</p>
                            </div>
                        </div>
                    </div>
                    <div class="bg-amber-50 border border-amber-200 rounded-lg p-4">
                        <h3 class="font-bold text-amber-900 mb-2 flex items-center gap-2">
                            <span class="material-symbols-outlined">warning</span>
                            Payment Methods to Avoid
                        </h3>
                        <ul class="space-y-2 text-amber-800 text-sm">
                            <li>• ❌ Wire transfers or Western Union</li>
                            <li>• ❌ Cryptocurrency for campus transactions</li>
                            <li>• ❌ Payment before viewing the item</li>
                            <li>• ❌ Sharing bank account or card details</li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Communication Safety -->
            <div class="bg-white rounded-xl border border-slate-200 p-4">
                <div class="flex items-start gap-4 mb-6">
                    <div class="w-12 h-12 rounded-lg bg-purple-100 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-2xl text-purple-600">chat</span>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold text-brand-green mb-2">Safe Communication</h2>
                        <p class="text-slate-600">Protect your personal information</p>
                    </div>
                </div>
                <div class="space-y-4 ml-0">
                    <div class="grid md:grid-cols-2 gap-4">
                        <div class="border-2 border-emerald-200 rounded-lg p-4 bg-emerald-50">
                            <h3 class="font-bold text-emerald-900 mb-3">✅ Do This:</h3>
                            <ul class="space-y-2 text-emerald-800 text-sm">
                                <li>• Use CampMart's in-app messaging</li>
                                <li>• Keep conversations professional</li>
                                <li>• Document all agreements in chat</li>
                                <li>• Report suspicious behavior</li>
                                <li>• Share meeting details with a friend</li>
                            </ul>
                        </div>
                        <div class="border-2 border-red-200 rounded-lg p-4 bg-red-50">
                            <h3 class="font-bold text-red-900 mb-3">❌ Don't Do This:</h3>
                            <ul class="space-y-2 text-red-800 text-sm">
                                <li>• Share personal phone numbers early</li>
                                <li>• Give out your home address</li>
                                <li>• Accept friend requests immediately</li>
                                <li>• Move conversations off-platform</li>
                                <li>• Share financial information</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Scam Prevention -->
            <div class="bg-white rounded-xl border border-slate-200 p-4">
                <div class="flex items-start gap-4 mb-6">
                    <div class="w-12 h-12 rounded-lg bg-red-100 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-2xl text-red-600">shield</span>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold text-brand-green mb-2">Recognize & Avoid Scams</h2>
                        <p class="text-slate-600">Common scams and how to protect yourself</p>
                    </div>
                </div>
                <div class="space-y-4 ml-0">
                    <div class="bg-red-50 border-l-4 border-red-500 rounded-r-lg p-4">
                        <h3 class="font-bold text-red-900 mb-3">🚨 Common Scam Tactics:</h3>
                        <div class="space-y-3">
                            <div class="bg-white rounded-lg p-3">
                                <h4 class="font-bold text-slate-800 mb-1">The "Too Good to Be True" Deal</h4>
                                <p class="text-sm text-slate-600">Prices significantly below market value. Always research typical prices before buying.</p>
                            </div>
                            <div class="bg-white rounded-lg p-3">
                                <h4 class="font-bold text-slate-800 mb-1">The "Urgent" Buyer/Seller</h4>
                                <p class="text-sm text-slate-600">Pressure to complete transaction immediately without proper verification.</p>
                            </div>
                            <div class="bg-white rounded-lg p-3">
                                <h4 class="font-bold text-slate-800 mb-1">The "Payment First" Request</h4>
                                <p class="text-sm text-slate-600">Asking for payment before showing the actual item or meeting in person.</p>
                            </div>
                            <div class="bg-white rounded-lg p-3">
                                <h4 class="font-bold text-slate-800 mb-1">The "Off-Platform" Transaction</h4>
                                <p class="text-sm text-slate-600">Insisting on completing the deal outside of CampMart without protection.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Emergency Contacts -->
            <div class="bg-gradient-to-br from-red-50 to-orange-50 rounded-xl border-2 border-red-200 p-4">
                <div class="flex items-start gap-4 mb-6">
                    <div class="w-12 h-12 rounded-lg bg-red-100 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-2xl text-red-600">call</span>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold text-red-900 mb-2">Emergency & Support Contacts</h2>
                        <p class="text-red-700">Save these numbers and reach out if you feel unsafe</p>
                    </div>
                </div>
                <div class="grid md:grid-cols-2 gap-4 ml-0">
                    <div class="bg-white rounded-lg p-4 border border-red-200">
                        <h3 class="font-bold text-slate-800 mb-3">Campus Security</h3>
                        <p class="text-2xl font-bold text-primary mb-1">📞 Campus Extension: 911</p>
                        <p class="text-sm text-slate-600">Available 24/7 for emergencies</p>
                    </div>
                    <div class="bg-white rounded-lg p-4 border border-red-200">
                        <h3 class="font-bold text-slate-800 mb-3">CampMart Support</h3>
                        <p class="text-lg font-bold text-primary mb-1">📧 support@campmart.ng</p>
                        <p class="text-sm text-slate-600">Report suspicious activity or scams</p>
                    </div>
                </div>
            </div>

            <!-- Report an Issue CTA -->
            <div class="bg-brand-green rounded-xl p-8 text-center text-white">
                <h2 class="text-2xl font-bold mb-3">Encountered a Problem?</h2>
                <p class="text-slate-100 mb-6 max-w-2xl mx-auto">If you experience any suspicious activity, scams, or feel unsafe, report it immediately. Your safety and the safety of the CampMart community is our top priority.</p>
                <div class="flex flex-wrap gap-4 justify-center">
                    <button onclick="window.location.href='contact-us.php'" class="bg-white text-brand-green px-8 py-3 rounded-lg font-bold hover:bg-slate-100 transition-all">
                        Report an Issue
                    </button>
                    <button onclick="window.location.href='help-center.php'" class="bg-brand-green border-2 border-white text-white px-8 py-3 rounded-lg font-bold hover:bg-brand-green/90 transition-all">
                        Visit Help Center
                    </button>
                </div>
            </div>
        </div>
    </main>

    <?php include_once 'includes/footer.php'; ?>
</body>
</html>
