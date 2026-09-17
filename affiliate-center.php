<?php session_start();
include_once 'includes/controller.php';

$userId = $_SESSION['userAppId'] ?? 0;

// Fetch user data with affiliate info
$user_query = $db->query("SELECT * FROM users WHERE id = '$userId'");
$user = $user_query->fetch_assoc();

// Get commission settings
$commission_rate = 1; // Default 1% commission
$min_withdrawal = 20000; // Minimum ₦20000 for withdrawal

// Get affiliate statistics
$total_referrals = $db->query("SELECT COUNT(*) as count FROM users WHERE referred_by = '$userId'")->fetch_assoc()['count'] ?? 0;
$active_referrals_result = $db->query("SELECT COUNT(*) as count FROM users WHERE referred_by = '$userId'");
$active_referrals = $active_referrals_result ? $active_referrals_result->fetch_assoc()['count'] : 0;

// Get earnings from affiliate_earnings table
$total_earnings_query = $db->query("SELECT SUM(amount) as total FROM affiliate_earnings WHERE user_id = '$userId' AND status IN ('approved', 'paid')");
$total_earnings = $total_earnings_query && $total_earnings_query->num_rows > 0 ? ($total_earnings_query->fetch_assoc()['total'] ?? 0) : 0;

$pending_earnings_query = $db->query("SELECT SUM(amount) as total FROM affiliate_earnings WHERE user_id = '$userId' AND status = 'pending'");
$pending_earnings = $pending_earnings_query && $pending_earnings_query->num_rows > 0 ? ($pending_earnings_query->fetch_assoc()['total'] ?? 0) : 0;

// Calculate available balance (approved but not yet paid)
$available_balance_query = $db->query("SELECT SUM(amount) as total FROM affiliate_earnings WHERE user_id = '$userId' AND status = 'approved'");
$available_balance = $available_balance_query && $available_balance_query->num_rows > 0 ? ($available_balance_query->fetch_assoc()['total'] ?? 0) : 0;

// Get recent referrals
$referrals_query = $db->query("SELECT u.*, 
    (SELECT COUNT(*) FROM orders WHERE affiliate_id = u.id) as total_orders,
    (SELECT SUM(total_amount) FROM orders WHERE affiliate_id = u.id AND status = 'completed') as total_spent,
    (SELECT SUM(amount) FROM affiliate_earnings WHERE referral_user_id = u.id AND user_id = '$userId') as total_earned
    FROM users u WHERE u.referred_by = '$userId' ORDER BY u.created_at DESC LIMIT 10");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>Affiliate Center | CampMart</title>
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
                        "brand-green-light": "#F0FDF4",
                        "background-main": "#F9FAFB",
                        "surface-white": "#FFFFFF",
                        "text-dark": "#1F2937",
                    },
                    fontFamily: {
                        "display": ["Inter"]
                    },
                    borderRadius: {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "full": "9999px"
                    },
                },
            },
        }
    </script>
    <style>
        .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }

        body {
            font-family: 'Inter', sans-serif;
            color: #1F2937;
        }
    </style>
</head>

<body class="bg-background-main min-h-screen text-text-dark">
    <?php include_once 'includes/user-nav.php'; ?>
    <main class="flex-1 overflow-y-auto bg-background-main p-4 md:p-6 lg:p-8">
        <div class="max-w-6xl mx-auto space-y-6">
            <!-- Header -->
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold tracking-tight text-brand-green">Affiliate Center</h1>
                    <p class="text-slate-500 mt-1">Earn money by referring friends to CampMart</p>
                </div>
            </div>

            <?php if(isset($_SESSION['success'])): ?>
            <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-lg flex items-center gap-3" id="successMessage">
                <span class="material-symbols-outlined text-emerald-600">check_circle</span>
                <p class="flex-1"><?= htmlspecialchars($_SESSION['success']) ?></p>
                <button onclick="this.parentElement.remove()" class="text-emerald-600 hover:text-emerald-800">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <?php unset($_SESSION['success']); endif; ?>

            <?php if(isset($_SESSION['error'])): ?>
            <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg flex items-center gap-3" id="errorMessage">
                <span class="material-symbols-outlined text-red-600">error</span>
                <p class="flex-1"><?= htmlspecialchars($_SESSION['error']) ?></p>
                <button onclick="this.parentElement.remove()" class="text-red-600 hover:text-red-800">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <?php unset($_SESSION['error']); endif; ?>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="flex flex-col gap-2 rounded-xl p-6 bg-surface-white border border-slate-200 shadow-sm">
                    <span class="material-symbols-outlined text-primary text-3xl">group</span>
                    <p class="text-2xl font-bold text-text-dark"><?= number_format($total_referrals) ?></p>
                    <p class="text-sm text-slate-500">Total Referrals</p>
                </div>
                <div class="flex flex-col gap-2 rounded-xl p-6 bg-surface-white border border-slate-200 shadow-sm">
                    <span class="material-symbols-outlined text-brand-green text-3xl">group_add</span>
                    <p class="text-2xl font-bold text-text-dark"><?= number_format($active_referrals) ?></p>
                    <p class="text-sm text-slate-500">Active Referrals</p>
                </div>
                <div class="flex flex-col gap-2 rounded-xl p-6 bg-surface-white border border-slate-200 shadow-sm">
                    <span class="material-symbols-outlined text-green-600 text-3xl">payments</span>
                    <p class="text-2xl font-bold text-text-dark">₦<?= number_format($total_earnings, 2) ?></p>
                    <p class="text-sm text-slate-500">Total Earnings</p>
                </div>
                <div class="flex flex-col gap-2 rounded-xl p-6 bg-surface-white border border-slate-200 shadow-sm">
                    <span class="material-symbols-outlined text-yellow-600 text-3xl">schedule</span>
                    <p class="text-2xl font-bold text-text-dark">₦<?= number_format($pending_earnings, 2) ?></p>
                    <p class="text-sm text-slate-500">Pending</p>
                </div>
            </div>

            <!-- Referral Link Section -->
            <div class="bg-gradient-to-br from-primary/10 to-brand-green/10 rounded-lg border border-primary/20 shadow-sm p-4">
                <div class="flex items-start gap-3">
                    <div class="flex-1">
                        <h3 class="text-lg font-bold text-text-dark mb-1">Your Referral Code</h3>
                        <p class="text-sm text-slate-600 mb-3">Share your unique code and earn <?= $commission_rate ?>% commission on every purchase your referrals make!</p>
                        
                        <div class="bg-surface-white rounded-md p-3 mb-3">
                            <div class="flex items-center gap-2">
                                <div class="flex-1">
                                    <p class="text-[11px] text-slate-500 mb-0.5">Your Referral Code</p>
                                    <p class="text-xl font-bold text-primary" id="referralCode"><?= htmlspecialchars($user['affiliate_code']) ?></p>
                                </div>
                                <button onclick="copyCode()" class="px-3 py-1.5 bg-primary text-white rounded-md text-sm font-medium hover:bg-primary/90 transition-colors flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-base">content_copy</span>
                                    <span>Copy</span>
                                </button>
                            </div>
                        </div>

                        <div class="bg-surface-white rounded-md p-3">
                            <div class="flex items-center gap-2">
                                <div class="flex-1">
                                    <p class="text-[11px] text-slate-500 mb-0.5">Your Referral Link</p>
                                    <p class="text-sm text-slate-700 break-all" id="referralLink"><?= htmlspecialchars($_SERVER['HTTP_HOST']) ?>/signup.php?ref=<?= htmlspecialchars($user['affiliate_code']) ?></p>
                                </div>
                                <button onclick="copyLink()" class="px-3 py-1.5 bg-brand-green text-white rounded-md text-sm font-medium hover:bg-brand-green/90 transition-colors flex items-center gap-1.5">
                                    <span class="material-symbols-outlined text-base">link</span>
                                    <span>Copy Link</span>
                                </button>
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-2 mt-3">
                            <button onclick="shareWhatsApp()" class="px-3 py-1.5 bg-green-600 text-white rounded-md text-sm font-medium hover:bg-green-700 transition-colors flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-base">chat</span>
                                <span>WhatsApp</span>
                            </button>
                            <button onclick="shareTwitter()" class="px-3 py-1.5 bg-blue-500 text-white rounded-md text-sm font-medium hover:bg-blue-600 transition-colors flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-base">share</span>
                                <span>Twitter</span>
                            </button>
                            <button onclick="shareFacebook()" class="px-3 py-1.5 bg-blue-700 text-white rounded-md text-sm font-medium hover:bg-blue-800 transition-colors flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-base">share</span>
                                <span>Facebook</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Commission Info & Withdrawal -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <!-- How It Works -->
                <div class="bg-surface-white rounded-xl border border-slate-200 shadow-sm p-6">
                    <h3 class="text-lg font-bold text-text-dark mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">info</span>
                        How It Works
                    </h3>
                    <div class="space-y-4">
                        <div class="flex gap-3">
                            <div class="flex-shrink-0 w-8 h-8 bg-primary/10 rounded-full flex items-center justify-center">
                                <span class="text-primary font-bold">1</span>
                            </div>
                            <div>
                                <p class="font-medium text-text-dark">Share Your Link</p>
                                <p class="text-sm text-slate-600">Share your referral code or link with friends</p>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <div class="flex-shrink-0 w-8 h-8 bg-primary/10 rounded-full flex items-center justify-center">
                                <span class="text-primary font-bold">2</span>
                            </div>
                            <div>
                                <p class="font-medium text-text-dark">They Sign Up</p>
                                <p class="text-sm text-slate-600">Your friends register using your referral code</p>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <div class="flex-shrink-0 w-8 h-8 bg-primary/10 rounded-full flex items-center justify-center">
                                <span class="text-primary font-bold">3</span>
                            </div>
                            <div>
                                <p class="font-medium text-text-dark">You Earn Commission</p>
                                <p class="text-sm text-slate-600">Get <?= $commission_rate ?>% commission on their purchases forever</p>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <div class="flex-shrink-0 w-8 h-8 bg-primary/10 rounded-full flex items-center justify-center">
                                <span class="text-primary font-bold">4</span>
                            </div>
                            <div>
                                <p class="font-medium text-text-dark">Withdraw Earnings</p>
                                <p class="text-sm text-slate-600">Request withdrawal once you reach ₦<?= number_format($min_withdrawal) ?></p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Withdrawal Section -->
                <div class="bg-surface-white rounded-xl border border-slate-200 shadow-sm p-6">
                    <h3 class="text-lg font-bold text-text-dark mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-brand-green">account_balance_wallet</span>
                        Withdraw Earnings
                    </h3>
                    
                    <div class="mb-4 p-4 bg-slate-50 rounded-lg">
                        <p class="text-sm text-slate-600 mb-1">Available Balance</p>
                        <p class="text-3xl font-bold text-brand-green">₦<?= number_format($available_balance, 2) ?></p>
                    </div>

                    <?php if($available_balance >= $min_withdrawal): ?>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>" />
                        
                        <div>
                            <label class="block text-sm font-bold text-text-dark mb-2">Withdrawal Amount</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-slate-500">₦</span>
                                <input type="number" name="amount" min="<?= $min_withdrawal ?>" max="<?= $available_balance ?>" step="0.01" required class="w-full pl-10 pr-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="Enter amount">
                            </div>
                            <p class="text-xs text-slate-500 mt-1">Minimum withdrawal: ₦<?= number_format($min_withdrawal) ?></p>
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-text-dark mb-2">Bank Name</label>
                            <input type="text" name="bank_name" required class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="e.g., First Bank">
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-text-dark mb-2">Account Number</label>
                            <input type="text" name="account_number" required class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="0123456789">
                        </div>

                        <div>
                            <label class="block text-sm font-bold text-text-dark mb-2">Account Name</label>
                            <input type="text" name="account_name" required class="w-full px-4 py-2.5 rounded-lg border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/20 outline-none" placeholder="Full name on account">
                        </div>

                        <button type="submit" name="RequestWithdrawal" class="w-full px-6 py-3 bg-brand-green text-white rounded-lg font-bold hover:bg-brand-green/90 transition-colors">
                            Request Withdrawal
                        </button>
                    </form>
                    <?php else: ?>
                    <div class="text-center py-8">
                        <span class="material-symbols-outlined text-slate-300 text-5xl mb-3">account_balance_wallet</span>
                        <p class="text-slate-600 mb-2">Minimum withdrawal amount not reached</p>
                        <p class="text-sm text-slate-500">You need ₦<?= number_format($min_withdrawal - $available_balance, 2) ?> more to withdraw</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Recent Referrals -->
            <div class="bg-surface-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-slate-200">
                    <h3 class="text-lg font-bold text-text-dark">Your Referrals</h3>
                </div>
                <div class="overflow-x-auto">
                    <?php if($referrals_query->num_rows > 0): ?>
                    <table class="w-full">
                        <thead class="bg-slate-50 border-b border-slate-200">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-bold text-slate-600 uppercase tracking-wider">User</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-slate-600 uppercase tracking-wider">Join Date</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-slate-600 uppercase tracking-wider">Orders</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-slate-600 uppercase tracking-wider">Total Spent</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-slate-600 uppercase tracking-wider">Your Earnings</th>
                                <th class="px-6 py-3 text-left text-xs font-bold text-slate-600 uppercase tracking-wider">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200">
                            <?php while($ref = $referrals_query->fetch_assoc()): 
                                $earnings = $ref['total_earned'] ?? 0;
                            ?>
                            <tr class="hover:bg-slate-50">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full bg-gradient-to-br from-primary/20 to-brand-green/20 flex items-center justify-center">
                                            <span class="text-sm font-bold text-brand-green"><?= strtoupper(substr($ref['full_name'] ?? $ref['username'], 0, 1)) ?></span>
                                        </div>
                                        <div>
                                            <p class="font-medium text-text-dark"><?= htmlspecialchars($ref['full_name'] ?? $ref['username']) ?></p>
                                            <p class="text-sm text-slate-500">@<?= htmlspecialchars($ref['username']) ?></p>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 text-sm text-slate-600">
                                    <?= date('M d, Y', strtotime($ref['created_at'])) ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="text-sm font-medium text-text-dark"><?= number_format($ref['total_orders']) ?></span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="text-sm font-medium text-text-dark">₦<?= number_format($ref['total_spent'] ?? 0, 2) ?></span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="text-sm font-bold text-green-600">₦<?= number_format($earnings, 2) ?></span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="px-3 py-1 text-xs font-medium rounded-full <?= $ref['status'] === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' ?>">
                                        <?= ucfirst($ref['status']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    <?php else: ?>
                    <div class="text-center py-12">
                        <span class="material-symbols-outlined text-slate-300 text-6xl mb-3">group_off</span>
                        <p class="text-slate-600 mb-2">No referrals yet</p>
                        <p class="text-sm text-slate-500">Start sharing your referral link to earn commissions</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <script>
        // Auto-dismiss messages
        setTimeout(() => {
            const successMsg = document.getElementById('successMessage');
            const errorMsg = document.getElementById('errorMessage');
            if(successMsg) successMsg.remove();
            if(errorMsg) errorMsg.remove();
        }, 5000);

        // Copy referral code
        function copyCode() {
            const code = document.getElementById('referralCode').textContent;
            navigator.clipboard.writeText(code).then(() => {
                showToast('Referral code copied!');
            });
        }

        // Copy referral link
        function copyLink() {
            const link = document.getElementById('referralLink').textContent;
            navigator.clipboard.writeText('https://' + link).then(() => {
                showToast('Referral link copied!');
            });
        }

        // Share on WhatsApp
        function shareWhatsApp() {
            const code = document.getElementById('referralCode').textContent;
            const message = `Join CampMart using my referral code: ${code} and get amazing deals on campus! https://<?= $_SERVER['HTTP_HOST'] ?>/signup.php?ref=${code}`;
            window.open(`https://wa.me/?text=${encodeURIComponent(message)}`, '_blank');
        }

        // Share on Twitter
        function shareTwitter() {
            const code = document.getElementById('referralCode').textContent;
            const message = `Join CampMart using my referral code: ${code}! https://<?= $_SERVER['HTTP_HOST'] ?>/signup.php?ref=${code}`;
            window.open(`https://twitter.com/intent/tweet?text=${encodeURIComponent(message)}`, '_blank');
        }

        // Share on Facebook
        function shareFacebook() {
            const link = `https://<?= $_SERVER['HTTP_HOST'] ?>/signup.php?ref=<?= htmlspecialchars($user['affiliate_code']) ?>`;
            window.open(`https://www.facebook.com/sharer/sharer.php?u=${encodeURIComponent(link)}`, '_blank');
        }

        // Show toast notification
        function showToast(message) {
            const toast = document.createElement('div');
            toast.className = 'fixed bottom-4 right-4 bg-brand-green text-white px-6 py-3 rounded-lg shadow-lg z-50 flex items-center gap-2';
            toast.innerHTML = `
                <span class="material-symbols-outlined">check_circle</span>
                <span>${message}</span>
            `;
            document.body.appendChild(toast);
            setTimeout(() => toast.remove(), 3000);
        }

        // Mobile menu toggle
        const menuToggle = document.getElementById('menuToggle');
        const sidebar = document.getElementById('sidebar');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        function toggleSidebar() {
            if(sidebar) sidebar.classList.toggle('-translate-x-full');
            if(sidebarOverlay) sidebarOverlay.classList.toggle('hidden');
            document.body.classList.toggle('overflow-hidden');
        }

        if(menuToggle) menuToggle.addEventListener('click', toggleSidebar);
        if(sidebarOverlay) sidebarOverlay.addEventListener('click', toggleSidebar);

        if(sidebar) {
            const sidebarLinks = sidebar.querySelectorAll('a');
            sidebarLinks.forEach(link => {
                link.addEventListener('click', () => {
                    if (window.innerWidth < 1024) {
                        toggleSidebar();
                    }
                });
            });
        }

        window.addEventListener('resize', () => {
            if (window.innerWidth >= 1024 && sidebar && !sidebar.classList.contains('-translate-x-full')) {
                sidebar.classList.remove('-translate-x-full');
                if(sidebarOverlay) sidebarOverlay.classList.add('hidden');
                document.body.classList.remove('overflow-hidden');
            }
        });
    </script>
</body>

</html>
