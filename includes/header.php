<?php
$universityCode = isLoggedIn()==false ? 'ALL' : tableRowItem('universities', ['id'=>$universityId], 'code');

// Get cart count for logged-in users
$cart_count = 0;
$headerUnread = 0;
if (isset($_SESSION['userAppId'])) {
    $user_id = $_SESSION['userAppId'];
    $cart_count_query = "SELECT COUNT(*) as count FROM cart WHERE user_id = ?";
    $stmt = $db->prepare($cart_count_query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $cart_count_result = $stmt->get_result()->fetch_assoc();
    $cart_count = $cart_count_result['count'] ?? 0;
    
    $headerUnreadResult = $db->query("SELECT COUNT(*) as cnt FROM messages m 
        JOIN conversations c ON m.conversation_id = c.id 
        WHERE (c.user1_id = '$user_id' OR c.user2_id = '$user_id') 
        AND m.sender_id != '$user_id' AND m.is_read = 0");
    if ($headerUnreadResult) {
        $headerUnread = $headerUnreadResult->fetch_assoc()['cnt'] ?? 0;
    }
}

// Determine search target based on current page
$current_page = basename($_SERVER['PHP_SELF']);
$search_target = ($current_page === 'services.php') ? 'services.php' : 'products.php';
$search_placeholder = ($current_page === 'services.php') ? 'Search for services...' : 'Search for textbooks, tech, fashion...';
?>
 <header class="sticky top-0 z-50 bg-white border-b border-slate-100">
        <div class="max-w-[1440px] mx-auto px-6 h-16 flex items-center justify-between gap-8">
            <a class="flex items-center gap-2 shrink-0" href="./">
                <div class="size-8 flex items-center justify-center bg-primary text-accent rounded-lg shadow-inner">
                    <span class="material-symbols-outlined font-bold text-xl">shopping_bag</span>
                </div>
                <span class="text-xl font-extrabold tracking-tight text-primary">CampMart</span>
                </a>

                            <!-- Navigation Links -->
                            <nav class="hidden md:flex items-center gap-6">
                                <a href="<?= SITE_URL ?>products.php" class="text-sm font-semibold text-slate-700 hover:text-primary transition-colors">Products</a>
                                <a href="<?= SITE_URL ?>services.php" class="text-sm font-semibold text-slate-700 hover:text-primary transition-colors">Services</a>
                                <a href="<?= SITE_URL ?>my-products.php" class="text-sm font-semibold text-slate-700 hover:text-primary transition-colors">Sell</a>
                            </nav>

                            <form method="GET" action="<?= SITE_URL ?><?= $search_target ?>" data-search-scope="<?= ($search_target === 'services.php') ? 'services' : 'products' ?>" class="hidden lg:flex flex-1 max-w-2xl items-center bg-slate-50 rounded-full border border-slate-200 p-0.5 focus-within:ring-2 focus-within:ring-primary/10 focus-within:bg-white transition-all">
                                <div class="flex items-center gap-1.5 px-3 py-1.5 border-r border-slate-200 cursor-pointer hover:bg-slate-100 rounded-l-full">
                                    <span class="material-symbols-outlined text-lg text-primary">location_on</span>
                                    <span class="text-xs font-bold text-slate-700"><?php echo htmlspecialchars($universityCode); ?></span>
                                    <span class="material-symbols-outlined text-sm text-slate-400">expand_more</span>
                                    <!-- <input type="hidden" name="location" value="FUTA" /> -->
                                </div>
                                <div class="flex-1 flex items-center px-3">
                                    <span class="material-symbols-outlined text-lg text-slate-400 mr-2">search</span>
                                    <input name="search" class="w-full bg-transparent border-none focus:ring-0 text-xs placeholder:text-slate-400" placeholder="<?= $search_placeholder ?>" type="text" required autocomplete="off" />
                                </div>
                                <button type="submit" class="bg-primary text-white p-1.5 rounded-full mr-0.5">
                                    <span class="material-symbols-outlined text-lg">search</span>
                                </button>
                            </form>
            <div class="flex items-center gap-0.5 sm:gap-1">
                <!-- Desktop Notification Icon -->
                <button class="hidden md:block p-1 sm:p-2 text-slate-500 hover:text-primary hover:bg-slate-50 rounded-full relative">
                    <span class="material-symbols-outlined text-lg sm:text-xl">notifications</span>
                    <span class="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full border border-white"></span>
                </button>
                <a href="<?= SITE_URL ?>messages.php" class="p-1 sm:p-2 text-slate-500 hover:text-primary hover:bg-slate-50 rounded-full relative">
                    <span class="material-symbols-outlined text-lg sm:text-xl">chat</span>
                    <?php if ($headerUnread > 0): ?>
                    <span class="absolute -top-1 -right-1 bg-primary text-white text-xs font-bold rounded-full min-w-[18px] h-[18px] flex items-center justify-center px-1 shadow-sm border border-white"><?= $headerUnread > 99 ? '99+' : $headerUnread ?></span>
                    <?php endif; ?>
                </a>
                <button onclick="window.location.href='cart.php'" class="p-1 sm:p-2 text-slate-500 hover:text-primary hover:bg-slate-50 rounded-full relative">
                    <span class="material-symbols-outlined text-lg sm:text-xl">shopping_cart</span>
                    <?php if ($cart_count > 0): ?>
                    <span class="absolute -top-1 -right-1 bg-secondary text-white text-xs font-bold rounded-full min-w-[18px] h-[18px] flex items-center justify-center px-1 shadow-sm border border-white"><?php echo $cart_count > 99 ? '99+' : $cart_count; ?></span>
                    <?php endif; ?>
                </button>

                                <button onclick="window.location.href='<?= SITE_URL ?>dashboard.php'" class="flex items-center gap-1 pl-1 pr-0.5 py-0.5 rounded-full border border-slate-200 hover:border-primary transition-all">
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
            <div class="flex items-center gap-3 flex-1">
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

    <!-- Mobile Search Bar (Visible by default) -->
    <div id="mobileSearchBar" class="md:hidden bg-white border-b border-slate-100 px-4 py-3">
        <div class="max-w-[1440px] mx-auto">
            <form method="GET" action="<?= SITE_URL ?><?= $search_target ?>" data-search-scope="<?= ($search_target === 'services.php') ? 'services' : 'products' ?>" class="flex items-center bg-slate-50 rounded-full border border-slate-200 p-0.5 focus-within:ring-2 focus-within:ring-primary/10 focus-within:bg-white transition-all">
                <div class="flex items-center gap-1.5 px-3 py-1.5 border-r border-slate-200 cursor-pointer hover:bg-slate-100 rounded-l-full">
                    <span class="material-symbols-outlined text-lg text-primary">location_on</span>
                    <span class="text-xs font-bold text-slate-700"><?php echo htmlspecialchars($universityCode); ?></span>
                </div>
                <div class="flex-1 flex items-center px-2">
                    <span class="material-symbols-outlined text-lg text-slate-400 mr-0">search</span>
                    <input name="search" class="w-full bg-transparent border-none focus:ring-0 text-xs placeholder:text-slate-400" placeholder="<?= $search_placeholder ?>" type="text" required autocomplete="off" />
                </div>
                <button type="submit" class="bg-primary text-white p-1 rounded-full mr-0.5">
                    <span class="material-symbols-outlined text-lg">search</span>
                </button>
            </form>
        </div>
    </div>

    <script src="<?= SITE_URL ?>assets/js/ai-autocomplete.js" defer></script>