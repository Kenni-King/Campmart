<?php
session_start();
include_once "includes/controller.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Privacy Policy | CampMart</title>
    <script src="tailwind34.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&amp;display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet" />
    <script id="tailwind-config">
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        "primary": "#064E3B",
                        "secondary": "#F97316",
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

    <main class="max-w-[900px] mx-auto px-6 py-12">
        <!-- Hero -->
        <div class="text-center mb-12">
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-blue-100 mb-6">
                <span class="material-symbols-outlined text-5xl text-blue-600">shield</span>
            </div>
            <h1 class="text-4xl font-extrabold text-brand-green mb-4">Privacy Policy</h1>
            <p class="text-slate-600">Last updated: <?= date('F d, Y') ?></p>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-8 space-y-8">
            <!-- Introduction -->
            <section>
                <h2 class="text-2xl font-bold text-brand-green mb-4">1. Introduction</h2>
                <p class="text-slate-600 mb-3">
                    At CampMart, we take your privacy seriously. This Privacy Policy explains how we collect, use, disclose, and protect your personal information when you use our platform.
                </p>
                <p class="text-slate-600">
                    By using CampMart, you consent to the data practices described in this policy. If you do not agree with this policy, please discontinue use of our services.
                </p>
            </section>

            <!-- Information Collection -->
            <section>
                <h2 class="text-2xl font-bold text-brand-green mb-4">2. Information We Collect</h2>
                
                <h3 class="text-lg font-bold text-slate-800 mb-3">2.1 Information You Provide</h3>
                <ul class="list-disc list-inside text-slate-600 space-y-2 ml-4 mb-4">
                    <li><strong>Account Information:</strong> Name, email address, university affiliation, phone number</li>
                    <li><strong>Profile Data:</strong> Profile picture, bio, location, verification documents</li>
                    <li><strong>Listing Information:</strong> Product/service descriptions, images, pricing</li>
                    <li><strong>Communication Data:</strong> Messages sent through our platform</li>
                    <li><strong>Payment Information:</strong> Transaction history, commission payments (we do not store full payment card details)</li>
                </ul>

                <h3 class="text-lg font-bold text-slate-800 mb-3">2.2 Automatically Collected Information</h3>
                <ul class="list-disc list-inside text-slate-600 space-y-2 ml-4 mb-4">
                    <li><strong>Device Information:</strong> IP address, browser type, operating system</li>
                    <li><strong>Usage Data:</strong> Pages viewed, time spent, search queries, click patterns</li>
                    <li><strong>Location Data:</strong> General location based on IP address (not precise GPS)</li>
                    <li><strong>Cookies:</strong> Session cookies, preference cookies, analytics cookies</li>
                </ul>

                <h3 class="text-lg font-bold text-slate-800 mb-3">2.3 Information from Third Parties</h3>
                <p class="text-slate-600 ml-4">
                    We may receive information from university verification systems, social media platforms (if you connect them), and fraud prevention services.
                </p>
            </section>

            <!-- How We Use Information -->
            <section>
                <h2 class="text-2xl font-bold text-brand-green mb-4">3. How We Use Your Information</h2>
                <p class="text-slate-600 mb-3">We use collected information for:</p>
                <div class="grid md:grid-cols-2 gap-4">
                    <div class="bg-slate-50 border border-slate-200 rounded-lg p-4">
                        <h4 class="font-bold text-slate-800 mb-2">✓ Platform Operation</h4>
                        <ul class="text-sm text-slate-600 space-y-1">
                            <li>• Creating and managing accounts</li>
                            <li>• Processing transactions</li>
                            <li>• Facilitating communication</li>
                            <li>• Providing customer support</li>
                        </ul>
                    </div>
                    <div class="bg-slate-50 border border-slate-200 rounded-lg p-4">
                        <h4 class="font-bold text-slate-800 mb-2">✓ Safety & Security</h4>
                        <ul class="text-sm text-slate-600 space-y-1">
                            <li>• Verifying student status</li>
                            <li>• Detecting fraud and abuse</li>
                            <li>• Enforcing terms of service</li>
                            <li>• Investigating violations</li>
                        </ul>
                    </div>
                    <div class="bg-slate-50 border border-slate-200 rounded-lg p-4">
                        <h4 class="font-bold text-slate-800 mb-2">✓ Improvement</h4>
                        <ul class="text-sm text-slate-600 space-y-1">
                            <li>• Analyzing usage patterns</li>
                            <li>• Testing new features</li>
                            <li>• Personalizing experience</li>
                            <li>• Improving search results</li>
                        </ul>
                    </div>
                    <div class="bg-slate-50 border border-slate-200 rounded-lg p-4">
                        <h4 class="font-bold text-slate-800 mb-2">✓ Communication</h4>
                        <ul class="text-sm text-slate-600 space-y-1">
                            <li>• Sending notifications</li>
                            <li>• Marketing messages (opt-in)</li>
                            <li>• Platform updates</li>
                            <li>• Responding to inquiries</li>
                        </ul>
                    </div>
                </div>
            </section>

            <!-- Information Sharing -->
            <section>
                <h2 class="text-2xl font-bold text-brand-green mb-4">4. Information Sharing and Disclosure</h2>
                <p class="text-slate-600 mb-3">We share your information in limited circumstances:</p>
                
                <div class="space-y-4">
                    <div class="border-l-4 border-primary pl-4">
                        <h4 class="font-bold text-slate-800 mb-1">With Other Users</h4>
                        <p class="text-slate-600 text-sm">Your public profile, listings, and ratings are visible to other users. We never share your email or phone number without your consent.</p>
                    </div>
                    <div class="border-l-4 border-emerald-600 pl-4">
                        <h4 class="font-bold text-slate-800 mb-1">With Service Providers</h4>
                        <p class="text-slate-600 text-sm">We work with third-party services for hosting, analytics, email delivery, and payment processing. These providers are bound by confidentiality agreements.</p>
                    </div>
                    <div class="border-l-4 border-blue-600 pl-4">
                        <h4 class="font-bold text-slate-800 mb-1">For Legal Reasons</h4>
                        <p class="text-slate-600 text-sm">We may disclose information to comply with laws, respond to legal requests, or protect rights and safety.</p>
                    </div>
                    <div class="border-l-4 border-purple-600 pl-4">
                        <h4 class="font-bold text-slate-800 mb-1">In Business Transfers</h4>
                        <p class="text-slate-600 text-sm">If CampMart is acquired or merged, your information may be transferred to the new entity.</p>
                    </div>
                </div>
            </section>

            <!-- Data Security -->
            <section>
                <h2 class="text-2xl font-bold text-brand-green mb-4">5. Data Security</h2>
                <p class="text-slate-600 mb-3">
                    We implement industry-standard security measures to protect your information:
                </p>
                <ul class="list-disc list-inside text-slate-600 space-y-2 ml-4">
                    <li>SSL/TLS encryption for data transmission</li>
                    <li>Encrypted storage of sensitive data</li>
                    <li>Regular security audits and updates</li>
                    <li>Access controls and authentication</li>
                    <li>Employee training on data protection</li>
                </ul>
                <p class="text-slate-600 mt-3">
                    However, no method of transmission over the Internet is 100% secure. We cannot guarantee absolute security.
                </p>
            </section>

            <!-- Your Rights -->
            <section>
                <h2 class="text-2xl font-bold text-brand-green mb-4">6. Your Rights and Choices</h2>
                <p class="text-slate-600 mb-3">You have the following rights regarding your data:</p>
                
                <div class="bg-slate-50 border border-slate-200 rounded-lg p-6">
                    <ul class="space-y-3">
                        <li class="flex items-start gap-3">
                            <span class="material-symbols-outlined text-primary mt-1">visibility</span>
                            <div>
                                <strong class="text-slate-800">Access:</strong>
                                <span class="text-slate-600"> Request a copy of your personal data</span>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="material-symbols-outlined text-primary mt-1">edit</span>
                            <div>
                                <strong class="text-slate-800">Correction:</strong>
                                <span class="text-slate-600"> Update or correct inaccurate information</span>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="material-symbols-outlined text-primary mt-1">delete</span>
                            <div>
                                <strong class="text-slate-800">Deletion:</strong>
                                <span class="text-slate-600"> Request deletion of your account and data</span>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="material-symbols-outlined text-primary mt-1">download</span>
                            <div>
                                <strong class="text-slate-800">Portability:</strong>
                                <span class="text-slate-600"> Export your data in a portable format</span>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <span class="material-symbols-outlined text-primary mt-1">block</span>
                            <div>
                                <strong class="text-slate-800">Opt-Out:</strong>
                                <span class="text-slate-600"> Unsubscribe from marketing communications</span>
                            </div>
                        </li>
                    </ul>
                </div>
                
                <p class="text-slate-600 mt-4">
                    To exercise these rights, contact us at <a href="mailto:privacy@campmart.ng" class="text-primary hover:underline">privacy@campmart.ng</a> or visit your account settings.
                </p>
            </section>

            <!-- Cookies -->
            <section>
                <h2 class="text-2xl font-bold text-brand-green mb-4">7. Cookies and Tracking</h2>
                <p class="text-slate-600 mb-3">
                    We use cookies and similar technologies to enhance your experience. You can control cookie preferences through your browser settings.
                </p>
                <div class="grid md:grid-cols-3 gap-4">
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <h4 class="font-bold text-blue-800 mb-2">Essential Cookies</h4>
                        <p class="text-sm text-blue-700">Required for basic site functionality</p>
                    </div>
                    <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
                        <h4 class="font-bold text-purple-800 mb-2">Performance Cookies</h4>
                        <p class="text-sm text-purple-700">Help us understand usage patterns</p>
                    </div>
                    <div class="bg-emerald-50 border border-emerald-200 rounded-lg p-4">
                        <h4 class="font-bold text-emerald-800 mb-2">Preference Cookies</h4>
                        <p class="text-sm text-emerald-700">Remember your settings and choices</p>
                    </div>
                </div>
            </section>

            <!-- Data Retention -->
            <section>
                <h2 class="text-2xl font-bold text-brand-green mb-4">8. Data Retention</h2>
                <p class="text-slate-600">
                    We retain your information for as long as your account is active or as needed to provide services. After account deletion, we may retain certain data for legal compliance, dispute resolution, and fraud prevention (typically 90 days to 2 years).
                </p>
            </section>

            <!-- Children -->
            <section>
                <h2 class="text-2xl font-bold text-brand-green mb-4">9. Children's Privacy</h2>
                <p class="text-slate-600">
                    CampMart is intended for users 18 and older. We do not knowingly collect information from children under 18. If we discover we have collected such information, we will delete it immediately.
                </p>
            </section>

            <!-- Changes -->
            <section>
                <h2 class="text-2xl font-bold text-brand-green mb-4">10. Changes to This Policy</h2>
                <p class="text-slate-600">
                    We may update this Privacy Policy periodically. We will notify you of significant changes via email or prominent notice on the platform. Your continued use after changes indicates acceptance.
                </p>
            </section>

            <!-- Contact -->
            <section>
                <h2 class="text-2xl font-bold text-brand-green mb-4">11. Contact Us</h2>
                <p class="text-slate-600 mb-3">
                    For questions or concerns about this Privacy Policy or our data practices, contact:
                </p>
                <div class="bg-slate-50 border border-slate-200 rounded-lg p-4">
                    <p class="text-slate-600">
                        <strong>Email:</strong> <a href="mailto:privacy@campmart.ng" class="text-primary hover:underline">privacy@campmart.ng</a><br />
                        <strong>Support:</strong> <a href="contact-us.php" class="text-primary hover:underline">Contact Us Page</a><br />
                        <strong>Data Protection Officer:</strong> <a href="mailto:dpo@campmart.ng" class="text-primary hover:underline">dpo@campmart.ng</a>
                    </p>
                </div>
            </section>

            <!-- Commitment -->
            <div class="bg-gradient-to-r from-blue-600 to-brand-green text-white rounded-lg p-6 text-center">
                <span class="material-symbols-outlined text-5xl mb-3 block">verified_user</span>
                <p class="font-semibold text-lg">
                    Your privacy and trust are paramount to us. We are committed to protecting your personal information and being transparent about our practices.
                </p>
            </div>
        </div>
    </main>

    <?php include_once 'includes/footer.php'; ?>
</body>
</html>
