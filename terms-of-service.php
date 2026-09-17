<?php
session_start();
include_once "includes/controller.php";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Terms of Service | CampMart</title>
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
            <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-brand-green/10 mb-6">
                <span class="material-symbols-outlined text-5xl text-brand-green">gavel</span>
            </div>
            <h1 class="text-4xl font-extrabold text-brand-green mb-4">Terms of Service</h1>
            <p class="text-slate-600">Last updated: <?= date('F d, Y') ?></p>
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-8 space-y-8">
            <!-- Introduction -->
            <section>
                <h2 class="text-2xl font-bold text-brand-green mb-4">1. Agreement to Terms</h2>
                <p class="text-slate-600 mb-3">
                    By accessing or using CampMart, you agree to be bound by these Terms of Service and all applicable laws and regulations. If you do not agree with any of these terms, you are prohibited from using this platform.
                </p>
                <p class="text-slate-600">
                    CampMart is a peer-to-peer marketplace exclusively for verified university students. We facilitate connections between buyers and sellers but are not party to the actual transactions.
                </p>
            </section>

            <!-- Eligibility -->
            <section>
                <h2 class="text-2xl font-bold text-brand-green mb-4">2. Eligibility</h2>
                <p class="text-slate-600 mb-3">To use CampMart, you must:</p>
                <ul class="list-disc list-inside text-slate-600 space-y-2 ml-4">
                    <li>Be at least 18 years old or have parental consent</li>
                    <li>Be a currently enrolled university student with a valid .edu email address</li>
                    <li>Provide accurate and complete registration information</li>
                    <li>Maintain the security of your account credentials</li>
                    <li>Not have been previously banned from CampMart</li>
                </ul>
            </section>

            <!-- User Accounts -->
            <section>
                <h2 class="text-2xl font-bold text-brand-green mb-4">3. User Accounts</h2>
                <p class="text-slate-600 mb-3">
                    You are responsible for maintaining the confidentiality of your account and password. You agree to accept responsibility for all activities that occur under your account.
                </p>
                <p class="text-slate-600">
                    We reserve the right to refuse service, terminate accounts, or remove content at our sole discretion, particularly if we believe you have violated these terms.
                </p>
            </section>

            <!-- Listing Rules -->
            <section>
                <h2 class="text-2xl font-bold text-brand-green mb-4">4. Listing and Selling</h2>
                <div class="bg-slate-50 border border-slate-200 rounded-lg p-6 mb-4">
                    <h3 class="font-bold text-slate-800 mb-3">Permitted Items:</h3>
                    <ul class="list-disc list-inside text-slate-600 space-y-1 ml-4">
                        <li>Textbooks, study materials, and academic resources</li>
                        <li>Electronics, furniture, and dorm essentials</li>
                        <li>Clothing, accessories, and personal items</li>
                        <li>Services such as tutoring, design, or technical assistance</li>
                    </ul>
                </div>
                <div class="bg-red-50 border border-red-200 rounded-lg p-6 mb-4">
                    <h3 class="font-bold text-red-800 mb-3">Prohibited Items:</h3>
                    <ul class="list-disc list-inside text-red-700 space-y-1 ml-4">
                        <li>Weapons, explosives, or hazardous materials</li>
                        <li>Illegal drugs, alcohol, or tobacco (for underage users)</li>
                        <li>Stolen or counterfeit goods</li>
                        <li>Academic work for plagiarism (essays, assignments)</li>
                        <li>Adult content or services</li>
                        <li>Animals or live organisms</li>
                    </ul>
                </div>
                <p class="text-slate-600">
                    Sellers must provide accurate descriptions, fair pricing, and clear photos. Misrepresentation of items may result in account suspension.
                </p>
            </section>

            <!-- Transactions -->
            <section>
                <h2 class="text-2xl font-bold text-brand-green mb-4">5. Transactions and Payments</h2>
                <p class="text-slate-600 mb-3">
                    All transactions are conducted directly between buyers and sellers. CampMart facilitates the connection but is not responsible for payment processing, delivery, or fulfillment.
                </p>
                <p class="text-slate-600 mb-3">
                    <strong>Commission:</strong> CampMart charges a 5% commission on completed sales. This fee helps maintain and improve the platform.
                </p>
                <p class="text-slate-600">
                    We strongly recommend meeting in safe, public locations on campus and inspecting items before payment.
                </p>
            </section>

            <!-- Safety & Conduct -->
            <section>
                <h2 class="text-2xl font-bold text-brand-green mb-4">6. User Conduct and Safety</h2>
                <p class="text-slate-600 mb-3">Users must:</p>
                <ul class="list-disc list-inside text-slate-600 space-y-2 ml-4">
                    <li>Communicate respectfully with other users</li>
                    <li>Honor commitments made to buyers or sellers</li>
                    <li>Meet in safe, public locations (see our <a href="safety-guide.php" class="text-primary hover:underline">Safety Guide</a>)</li>
                    <li>Report suspicious activity or policy violations</li>
                    <li>Not engage in harassment, discrimination, or fraud</li>
                </ul>
            </section>

            <!-- Intellectual Property -->
            <section>
                <h2 class="text-2xl font-bold text-brand-green mb-4">7. Intellectual Property</h2>
                <p class="text-slate-600 mb-3">
                    The CampMart platform, including its design, logo, features, and content, is protected by copyright and trademark laws. You may not reproduce, distribute, or create derivative works without express written permission.
                </p>
                <p class="text-slate-600">
                    By posting content on CampMart, you grant us a non-exclusive, worldwide license to use, display, and distribute your content for the purpose of operating the platform.
                </p>
            </section>

            <!-- Liability -->
            <section>
                <h2 class="text-2xl font-bold text-brand-green mb-4">8. Limitation of Liability</h2>
                <p class="text-slate-600 mb-3">
                    CampMart is provided "as is" without warranties of any kind. We are not liable for:
                </p>
                <ul class="list-disc list-inside text-slate-600 space-y-2 ml-4">
                    <li>Quality, safety, or legality of items listed</li>
                    <li>Accuracy of user-generated content or listings</li>
                    <li>Actions or conduct of users on or off the platform</li>
                    <li>Unauthorized access to or alteration of your data</li>
                    <li>Interruptions or errors in service</li>
                </ul>
                <p class="text-slate-600 mt-3">
                    In no event shall CampMart be liable for any indirect, incidental, or consequential damages arising from your use of the platform.
                </p>
            </section>

            <!-- Dispute Resolution -->
            <section>
                <h2 class="text-2xl font-bold text-brand-green mb-4">9. Dispute Resolution</h2>
                <p class="text-slate-600 mb-3">
                    For disputes between users, we encourage direct communication and resolution. If needed, CampMart may provide mediation assistance.
                </p>
                <p class="text-slate-600">
                    Any disputes between you and CampMart shall be resolved through binding arbitration in accordance with Nigerian law, rather than in court.
                </p>
            </section>

            <!-- Modifications -->
            <section>
                <h2 class="text-2xl font-bold text-brand-green mb-4">10. Changes to Terms</h2>
                <p class="text-slate-600">
                    We reserve the right to modify these terms at any time. Changes will be posted on this page with an updated "Last updated" date. Continued use of CampMart after changes constitutes acceptance of the modified terms.
                </p>
            </section>

            <!-- Contact -->
            <section>
                <h2 class="text-2xl font-bold text-brand-green mb-4">11. Contact Information</h2>
                <p class="text-slate-600 mb-3">
                    If you have questions about these Terms of Service, please contact us:
                </p>
                <div class="bg-slate-50 border border-slate-200 rounded-lg p-4">
                    <p class="text-slate-600">
                        <strong>Email:</strong> <a href="mailto:legal@campmart.ng" class="text-primary hover:underline">legal@campmart.ng</a><br />
                        <strong>Support:</strong> <a href="contact-us.php" class="text-primary hover:underline">Contact Us Page</a>
                    </p>
                </div>
            </section>

            <!-- Acceptance -->
            <div class="bg-brand-green text-white rounded-lg p-6 text-center">
                <p class="font-semibold">
                    By using CampMart, you acknowledge that you have read, understood, and agree to be bound by these Terms of Service.
                </p>
            </div>
        </div>
    </main>

    <?php include_once 'includes/footer.php'; ?>
</body>
</html>
