<?php
session_start();
include_once 'includes/controller.php';
checkLogin();
$userId = $_SESSION['userAppId'];
$current_user = $db->query("SELECT full_name, username FROM users WHERE id = $userId")->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <base href="<?php echo SITE_URL; ?>">
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Getting Started | CampMart</title>
    <script src="tailwind34.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet" />
    <script id="tailwind-config">
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        "primary": "#f48c25",
                        "brand-green": "#064E3B",
                        "brand-green-light": "#F0FDF4",
                        "background-main": "#F9FAFB",
                        "surface-white": "#FFFFFF",
                        "text-dark": "#1F2937",
                    },
                    fontFamily: { "display": ["Inter"] },
                    borderRadius: { "DEFAULT": "0.25rem", "lg": "0.5rem", "xl": "0.75rem", "2xl": "1rem", "3xl": "1.5rem", "full": "9999px" },
                },
            },
        }
    </script>
    <style>
        .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }
        body { font-family: 'Inter', sans-serif; color: #1F2937; }
        .video-container { position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; border-radius: 0.75rem; }
        .video-container iframe { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }
    </style>
</head>
<body class="bg-background-main min-h-screen text-text-dark">
    <?php include_once 'includes/user-nav.php'; ?>
    <main class="flex-1 overflow-y-auto bg-background-main p-4 md:p-6 lg:p-8">
        <div class="max-w-4xl mx-auto space-y-8">
            <div>
                <h1 class="text-3xl font-extrabold tracking-tight text-brand-green">Getting Started</h1>
                <p class="text-slate-500 mt-1">Watch these quick tutorials to learn how to upload items and services on CampMart.</p>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white shadow-sm overflow-hidden">
                <div class="bg-gradient-to-r from-brand-green to-emerald-800 px-6 py-5">
                    <div class="flex items-center gap-3">
                        <span class="material-symbols-outlined text-3xl text-white/90">play_circle</span>
                        <div>
                            <h2 class="text-xl font-bold text-white">Video Tutorials</h2>
                            <p class="text-emerald-200 text-sm">Follow along with step-by-step screen recordings.</p>
                        </div>
                    </div>
                </div>

                <div class="p-6 space-y-10">
                    <section>
                        <div class="flex items-center gap-3 mb-4">
                            <span class="flex items-center justify-center size-10 rounded-2xl bg-primary/10 text-primary">
                                <span class="material-symbols-outlined text-2xl">shopping_bag</span>
                            </span>
                            <div>
                                <h3 class="text-lg font-bold text-text-dark">How to Upload a Product</h3>
                                <p class="text-sm text-slate-500">Sell textbooks, electronics, dorm gear, and more.</p>
                            </div>
                        </div>
                        <div class="video-container bg-slate-100 mb-4">
                            <iframe src="https://www.youtube.com/embed/" title="How to upload a product on CampMart" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen class="absolute top-0 left-0 w-full h-full"></iframe>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
                            <div class="flex items-start gap-2 rounded-2xl bg-slate-50 border border-slate-200 p-3">
                                <span class="material-symbols-outlined text-primary text-lg shrink-0">looks_one</span>
                                <span>Click <strong>Post Sale</strong> on your dashboard or go to <strong>My Products</strong>.</span>
                            </div>
                            <div class="flex items-start gap-2 rounded-2xl bg-slate-50 border border-slate-200 p-3">
                                <span class="material-symbols-outlined text-primary text-lg shrink-0">looks_two</span>
                                <span>Fill in title, category, price, description, and upload clear photos.</span>
                            </div>
                            <div class="flex items-start gap-2 rounded-2xl bg-slate-50 border border-slate-200 p-3">
                                <span class="material-symbols-outlined text-primary text-lg shrink-0">looks_3</span>
                                <span>Submit for review. Once approved, your item is live on campus!</span>
                            </div>
                        </div>
                    </section>

                    <hr class="border-slate-200" />

                    <section>
                        <div class="flex items-center gap-3 mb-4">
                            <span class="flex items-center justify-center size-10 rounded-2xl bg-brand-green/10 text-brand-green">
                                <span class="material-symbols-outlined text-2xl">work</span>
                            </span>
                            <div>
                                <h3 class="text-lg font-bold text-text-dark">How to Upload a Service</h3>
                                <p class="text-sm text-slate-500">Offer tutoring, graphic design, delivery help, and more.</p>
                            </div>
                        </div>
                        <div class="video-container bg-slate-100 mb-4">
                            <iframe src="https://www.youtube.com/embed/" title="How to upload a service on CampMart" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen class="absolute top-0 left-0 w-full h-full"></iframe>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
                            <div class="flex items-start gap-2 rounded-2xl bg-slate-50 border border-slate-200 p-3">
                                <span class="material-symbols-outlined text-brand-green text-lg shrink-0">looks_one</span>
                                <span>Click <strong>Post Service</strong> on your dashboard or go to <strong>My Services</strong>.</span>
                            </div>
                            <div class="flex items-start gap-2 rounded-2xl bg-slate-50 border border-slate-200 p-3">
                                <span class="material-symbols-outlined text-brand-green text-lg shrink-0">looks_two</span>
                                <span>Describe your service, set your price (hourly or fixed), and add portfolio images.</span>
                            </div>
                            <div class="flex items-start gap-2 rounded-2xl bg-slate-50 border border-slate-200 p-3">
                                <span class="material-symbols-outlined text-brand-green text-lg shrink-0">looks_3</span>
                                <span>Publish your service and start getting bookings from campus clients.</span>
                            </div>
                        </div>
                    </section>
                </div>
            </div>

            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="text-xl font-bold text-brand-green mb-4">Need More Help?</h2>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <a href="help-center.php" class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 hover:border-primary hover:bg-primary/5 transition-colors">
                        <span class="material-symbols-outlined text-primary">help</span>
                        <div>
                            <p class="font-semibold text-text-dark text-sm">Visit Help Center</p>
                            <p class="text-xs text-slate-500">FAQs and troubleshooting guides.</p>
                        </div>
                    </a>
                    <a href="my-profile.php" class="flex items-center gap-3 rounded-2xl border border-slate-200 bg-slate-50 p-4 hover:border-primary hover:bg-primary/5 transition-colors">
                        <span class="material-symbols-outlined text-primary">person</span>
                        <div>
                            <p class="font-semibold text-text-dark text-sm">Complete Your Profile</p>
                            <p class="text-xs text-slate-500">Build trust with a full profile.</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </main>

    <script>
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        function toggleSidebar() {
            if (sidebar) sidebar.classList.toggle('-translate-x-full');
            if (sidebarOverlay) sidebarOverlay.classList.toggle('hidden');
            document.body.classList.toggle('overflow-hidden');
        }

        if (menuToggle) menuToggle.addEventListener('click', toggleSidebar);
        if (sidebarOverlay) sidebarOverlay.addEventListener('click', toggleSidebar);

        if (sidebar) {
            sidebar.querySelectorAll('a').forEach(link => {
                link.addEventListener('click', () => {
                    if (window.innerWidth < 1024) toggleSidebar();
                });
            });
        }

        window.addEventListener('resize', () => {
            if (window.innerWidth >= 1024 && sidebar && !sidebar.classList.contains('-translate-x-full')) {
                sidebar.classList.remove('-translate-x-full');
                if (sidebarOverlay) sidebarOverlay.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }
        });
    </script>
</body>
</html>
