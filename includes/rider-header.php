<?php
$headerUnread = 0;
if (isset($_SESSION['userAppId'])) {
    $user_id = (int) $_SESSION['userAppId'];
    $headerUnreadResult = $db->query("SELECT COUNT(*) as cnt FROM messages m 
        JOIN conversations c ON m.conversation_id = c.id 
        WHERE (c.user1_id = '$user_id' OR c.user2_id = '$user_id') 
        AND m.sender_id != '$user_id' AND m.is_read = 0");
    if ($headerUnreadResult) {
        $headerUnread = $headerUnreadResult->fetch_assoc()['cnt'] ?? 0;
    }
}
?>
<header class="sticky top-0 z-50 bg-white border-b border-slate-100">
    <div class="max-w-[1440px] mx-auto px-6 h-16 flex items-center justify-between gap-8">
        <a class="flex items-center gap-2 shrink-0" href="<?= SITE_URL ?>rider/dashboard.php">
            <div class="size-8 flex items-center justify-center bg-primary text-accent rounded-lg shadow-inner">
                <span class="material-symbols-outlined font-bold text-xl">motorcycle</span>
            </div>
            <span class="text-xl font-extrabold tracking-tight text-primary">CampMart</span>
            <span class="hidden sm:inline-flex items-center rounded-full bg-orange-100 px-2.5 py-0.5 text-xs font-bold text-orange-700">Rider</span>
        </a>

        <nav class="hidden md:flex items-center gap-6">
            <a href="<?= SITE_URL ?>rider/dashboard.php" class="text-sm font-semibold text-slate-700 hover:text-primary transition-colors">Dashboard</a>
            <a href="<?= SITE_URL ?>rider/available-orders.php" class="text-sm font-semibold text-slate-700 hover:text-primary transition-colors">Available Orders</a>
            <a href="<?= SITE_URL ?>rider/my-tasks.php" class="text-sm font-semibold text-slate-700 hover:text-primary transition-colors">My Deliveries</a>
        </nav>

        <div class="flex items-center gap-0.5 sm:gap-1">
            <a href="<?= SITE_URL ?>messages.php" class="p-1 sm:p-2 text-slate-500 hover:text-primary hover:bg-slate-50 rounded-full relative">
                <span class="material-symbols-outlined text-lg sm:text-xl">chat</span>
                <?php if ($headerUnread > 0): ?>
                <span class="absolute -top-1 -right-1 bg-primary text-white text-xs font-bold rounded-full min-w-[18px] h-[18px] flex items-center justify-center px-1 shadow-sm border border-white"><?= $headerUnread > 99 ? '99+' : $headerUnread ?></span>
                <?php endif; ?>
            </a>
            <button onclick="window.location.href='<?= SITE_URL ?>rider/dashboard.php'" class="flex items-center gap-1 pl-1 pr-0.5 py-0.5 rounded-full border border-slate-200 hover:border-primary transition-all">
                <span class="text-xs font-bold text-slate-700 hidden md:inline">My Account</span>
                <div class="w-7 h-7 sm:w-8 sm:h-8 rounded-full overflow-hidden border border-slate-100">
                    <img alt="User profile" class="w-full h-full object-cover" src="<?= isset($currentUser['profile_image']) ? SITE_URL . htmlspecialchars($currentUser['profile_image']) : 'https://lh3.googleusercontent.com/aida-public/AB6AXuAjdTN-7iGUrMSaOC0E033hZX2t2y0cK-akq4Axba-3aH-8KjmvHJJDWTkGKagW6Gu58FgOFK6iHXRGb2rpaoCKtoTJUlAo1G1IiExKzdrPntaHyhBTwtvzxjDmqUg2X-T7M041GqciVdC6b7LlBfZEh_T6u6l68EzSw_63KG7L6DKHhZ2deqzDPMoHIFYWcnF3yye_FNWf_afEWlz1Vyic5MATIHnq88O1aMvlbZstIW8NKOPKv1zfhI7Medu8Sgmqn9vfx2QVpKNf';?>" />
                </div>
            </button>
        </div>
    </div>
</header>

<!-- Email Verification Banner for logged-in non-verified users -->
<?php if(isset($_SESSION['userAppId'])):
    $currentUserData = dbSelect('users', ['id' => $_SESSION['userAppId']])->fetch_assoc();
    if($currentUserData && isset($currentUserData['email_verified']) && !$currentUserData['email_verified']):
?>
<div class="bg-gradient-to-r from-yellow-50 to-amber-50 border-b border-yellow-200 px-4 md:px-6 py-3 relative">
    <div class="max-w-[1440px] mx-auto flex items-center justify-between gap-4">
        <div class="flex items-center gap-4 flex-1">
            <div class="flex-shrink-0">
                <span class="material-symbols-outlined text-2xl text-yellow-600">mark_email_unread</span>
            </div>
            <div class="flex-1">
                <p class="text-sm font-bold text-yellow-900">Verify your email address</p>
                <p class="text-xs text-yellow-700 mt-0.5 hidden sm:block">Unlock all features and build trust in the CampMart community.</p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <form method="POST" class="inline">
                <input type="hidden" name="ResendVerification" value="1">
                <button type="submit" class="px-3 sm:px-4 py-2 bg-primary text-white rounded-lg text-xs sm:text-sm font-semibold hover:bg-primary/90 transition-colors whitespace-nowrap flex items-center gap-1">
                    <span class="material-symbols-outlined text-sm">verified_user</span>
                    <span>Verify Now</span>
                </button>
            </form>
            <button onclick="this.closest('div').parentElement.parentElement.remove()" class="p-1 text-yellow-600 hover:text-yellow-800 transition-colors hidden lg:block">
                <span class="material-symbols-outlined text-xl">close</span>
            </button>
        </div>
    </div>
</div>
<?php endif; endif; ?>
