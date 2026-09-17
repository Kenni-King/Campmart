   <?php
    checkLogin();
    $userId = $pro->userId();

    // Get current page filename
    $current_page = basename($_SERVER['PHP_SELF']);

    // Determine search target based on current page
    $search_target = ($current_page === 'services.php') ? 'services.php' : 'products.php';

    // Get unread message count
    $navUnread = 0;
    $navUnreadResult = $db->query("SELECT COUNT(*) as cnt FROM messages m 
        JOIN conversations c ON m.conversation_id = c.id 
        WHERE (c.user1_id = '$userId' OR c.user2_id = '$userId') 
        AND m.sender_id != '$userId' AND m.is_read = 0");
    if ($navUnreadResult) {
        $navUnread = $navUnreadResult->fetch_assoc()['cnt'] ?? 0;
    }

    // Helper function to check if menu item is active
    function isActive($page)
    {
        global $current_page;
        return $current_page === $page ? 'flex items-center gap-3 px-3 py-2.5 rounded-lg bg-brand-green text-white font-medium shadow-sm shadow-brand-green/20' : 'flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-600 hover:bg-brand-green-light hover:text-brand-green transition-colors font-medium';
    }
    ?>
    <style>
        body { overflow: hidden; height: 100vh; display: flex; flex-direction: column; }
        .page-scroll { flex: 1; display: flex; overflow: hidden; }
    </style>
    <header class="sticky top-0 z-50 flex items-center justify-between border-b border-slate-200 bg-brand-green px-4 md:px-6 py-3 text-white">
       <div class="flex items-center gap-3 md:gap-8">
           <!-- Mobile Menu Button -->
           <button id="menuToggle" class="lg:hidden p-2 rounded-lg hover:bg-white/10 transition-colors">
               <span class="material-symbols-outlined">menu</span>
           </button>
<div class="flex items-center gap-2 md:gap-3"> <div class="size-8 flex items-center justify-center bg-primary text-accent rounded-lg shadow-inner"> <span class="material-symbols-outlined font-bold text-xl">shopping_bag</span> </div> <h2 class="text-lg md:text-xl font-bold tracking-tight"><a href="<?= SITE_URL . (($currentUser['role'] ?? '') == 'rider' ? 'rider/dashboard.php' : '') ?>" >CampMart</a></h2> </div> <?php if(($currentUser['role'] ?? '') != 'rider'): ?> <label class="hidden md:flex flex-col min-w-[300px] !h-10"> <div class="flex w-full flex-1 items-stretch rounded-lg h-full bg-white/10 overflow-hidden border border-white/5 focus-within:bg-white/20 transition-all"> <div class="flex items-center justify-center pl-3 text-white/70"> <span class="material-symbols-outlined text-[20px]">search</span> </div> 
<form method="get" action="<?= SITE_URL . $search_target ?>">
    <input name="search" class="w-full bg-transparent border-none focus:ring-0 text-sm placeholder:text-white/60 px-3" placeholder="Search campus listings..." value="" />
</form>
 </div> </label> <?php endif; ?> </div> <div class="flex items-center gap-2 md:gap-4"> <button class="relative p-2 rounded-lg bg-white/10 hover:bg-white/20 transition-colors"> <span class="material-symbols-outlined text-xl">notifications</span> <span class="absolute top-1.5 right-1.5 size-2 bg-primary rounded-full ring-2 ring-brand-green"></span> </button>
            <a href="<?= SITE_URL ?>messages.php" class="hidden sm:flex p-2 rounded-lg bg-white/10 hover:bg-white/20 transition-colors relative">
                <span class="material-symbols-outlined text-xl">chat</span>
                <?php if ($navUnread > 0): ?>
                <span class="absolute -top-1 -right-1 bg-primary text-white text-[10px] font-bold rounded-full min-w-[16px] h-[16px] flex items-center justify-center px-0.5"><?= $navUnread > 99 ? '99+' : $navUnread ?></span>
                <?php endif; ?>
            </a>
           <div class="hidden sm:block h-8 w-[1px] bg-white/20 mx-2"></div>
           <div class="flex items-center gap-3">
               <div class="text-right hidden lg:block">
                   <p class="text-xs font-semibold"><?= htmlspecialchars($currentUser['full_name'] ?? 'User') ?></p>
                   <p class="text-[10px] text-white/70">@<?= htmlspecialchars($currentUser['username'] ?? 'user') ?></p>
               </div>
               <div class="size-10 rounded-full bg-primary/20 border-2 border-primary/40 bg-center bg-cover" style='background-image: url("<?= $currentUser['profile_image'] ? SITE_URL . htmlspecialchars($currentUser['profile_image']) : "https://lh3.googleusercontent.com/aida-public/AB6AXuDa0WfkpeNaxdvAvHh6TzRKtDwTRJL3BCzuBHKeW0mko0rFZpDLYADfcy2CZFsCqMKQwn1t0SGPKKYuvwppkVsBk14nu1wTFryhyUZzfX1aGYWn2BtJHNEGOsmHTTkPmrCQJDaucohew1MIq3Z04Z3uUGzG0BticdH8M1xGI9e88M4RDWuDx-57dwkENPSBFSbQlv0Qr6BctnVjcuzi1cKDhPDxcsogyseom13A6798ubq7YjPHF9JhTv3In42wixHYbLPWpeGepZK0" ?>");'></div>
           </div>
       </div>
   </header>
   
   <!-- Email Verification Banner -->
   <?php if(isset($currentUser['email_verified']) && !$currentUser['email_verified']): ?>
   <div class="bg-yellow-50 border-b border-yellow-200 px-4 md:px-6 py-3 relative">
       <div class="max-w-7xl mx-auto flex items-center justify-between gap-4">
           <div class="flex items-center gap-3 flex-1">
               <div class="flex-shrink-0">
                   <span class="material-symbols-outlined text-2xl text-yellow-600">info</span>
               </div>
               <div class="flex-1">
                   <p class="text-sm font-semibold text-yellow-900">Verify your email address</p>
                   <p class="text-xs text-yellow-700 mt-0.5">Please verify your email to access all features and build trust with buyers and sellers.</p>
               </div>
           </div>
           <div class="flex items-center gap-2">
               <form method="POST" class="inline">
                   <input type="hidden" name="ResendVerification" value="1">
                   <button type="submit" class="px-4 py-2 bg-yellow-600 text-white rounded-lg text-sm font-semibold hover:bg-yellow-700 transition-colors whitespace-nowrap">
                       Verify Now
                   </button>
               </form>
               <button onclick="this.closest('div').parentElement.parentElement.remove()" class="p-1 text-yellow-600 hover:text-yellow-800 transition-colors lg:block hidden">
                   <span class="material-symbols-outlined text-xl">close</span>
               </button>
           </div>
       </div>
   </div>
   <?php endif; ?>
   
   <!-- Mobile Overlay -->
   <div id="sidebarOverlay" class="hidden fixed inset-0 bg-black/50 z-40 lg:hidden"></div>
    <div class="flex page-scroll relative">
        <aside id="sidebar" class="fixed lg:static inset-y-0 left-0 transform -translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out z-50 w-64 flex-shrink-0 flex flex-col justify-between border-r border-slate-200 bg-white py-4 pl-1 h-[calc(100vh-65px)] lg:h-auto top-[65px]">
           <div class="flex-1 overflow-y-auto pr-0 flex flex-col gap-6">
    <?php if($currentUser['role']=='admin'){ ?>
               <div class="px-0">
                   <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-4 pl-4">Admin Operations</p>
                   <nav class="flex flex-col gap-1">
                       <a class="<?= isActive('admin-dashboard.php') ?>" href="<?= SITE_URL ?>admin-dashboard.php">
                           <span class="material-symbols-outlined">admin_panel_settings</span>
                           <span class="text-sm">Admin Dashboard</span>
                       </a>
                       <a class="<?= isActive('manage-users.php') ?>" href="<?= SITE_URL ?>manage-users.php">
                           <span class="material-symbols-outlined">group</span>
                           <span class="text-sm">Manage Users</span>
                       </a>
                       
                       <a class="<?= isActive('manage-products.php') ?>" href="<?= SITE_URL ?>manage-products.php">
                           <span class="material-symbols-outlined">inventory</span>
                           <span class="text-sm">Manage Products</span>
                       </a>
                       <a class="<?= isActive('manage-services.php') ?>" href="<?= SITE_URL ?>manage-services.php">
                           <span class="material-symbols-outlined">work</span>
                           <span class="text-sm">Manage Services</span>
                       </a>
                       <a class="<?= isActive('manage-lost-found.php') ?>" href="<?= SITE_URL ?>manage-lost-found.php">
                           <span class="material-symbols-outlined">work</span>
                           <span class="text-sm">Manage Lost & Found</span>
                       </a>
                       <a class="<?= isActive('manage-ads.php') ?>" href="<?= SITE_URL ?>manage-ads.php">
                           <span class="material-symbols-outlined">ads_click</span>
                           <span class="text-sm">Manage Ads</span>
                       </a>
                       <a class="<?= isActive('reported-content.php') ?>" href="<?= SITE_URL ?>reported-content.php">
                           <span class="material-symbols-outlined">flag</span>
                           <span class="text-sm">Reported Content</span>
                       </a>
                       <a class="<?= isActive('manage-universities.php') ?>" href="<?= SITE_URL ?>manage-universities.php">
                           <span class="material-symbols-outlined">school</span>
                           <span class="text-sm">Manage Universities</span>
                       </a>
                       <a class="<?= isActive('manage-categories.php') ?>" href="<?= SITE_URL ?>manage-categories.php">
                           <span class="material-symbols-outlined">category</span>
                           <span class="text-sm">Manage Categories</span>
                       </a>
                       <a class="<?= isActive('manage-service-categories.php') ?>" href="<?= SITE_URL ?>manage-service-categories.php">
                           <span class="material-symbols-outlined">support</span>
                           <span class="text-sm">Manage Service Categories</span>
                       </a>


                       <a class="<?= isActive('flash-sales.php') ?>" href="<?= SITE_URL ?>flash-sales.php">
                           <span class="material-symbols-outlined">admin_panel_settings</span>
                           <span class="text-sm">Flash Sales</span>
                       </a>
                       <a class="<?= isActive('create-flash-sale.php') ?>" href="<?= SITE_URL ?>create-flash-sale.php">
                           <span class="material-symbols-outlined">bolt</span>
                           <span class="text-sm">Create Flash Sale</span>
                       </a>

                        <a class="<?= isActive('manage-featured.php') ?>" href="<?= SITE_URL ?>manage-featured.php">
                            <span class="material-symbols-outlined">workspace_premium</span>
                            <span class="text-sm">Featured Listings</span>
                        </a>
                        <a class="<?= isActive('manage-plans.php') ?>" href="<?= SITE_URL ?>manage-plans.php">
                            <span class="material-symbols-outlined">card_membership</span>
                            <span class="text-sm">Subscription Plans</span>
                        </a>
                        <a class="<?= isActive('admin-transactions.php') ?>" href="<?= SITE_URL ?>admin-transactions.php">
                            <span class="material-symbols-outlined">payments</span>
                            <span class="text-sm">Transactions</span>
                        </a>
                        <a class="<?= isActive('site-settings.php') ?>" href="<?= SITE_URL ?>site-settings.php">
                            <span class="material-symbols-outlined">tune</span>
                            <span class="text-sm">Site Settings</span>
                        </a>
                   </nav>
               </div>
         <?php }elseif($currentUser['role']=='rider'){ ?>
                <div class="px-0">
                    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-4 pl-4">Rider Operations</p>
                    <nav class="flex flex-col gap-1">
                        <a class="<?= isActive('dashboard.php') ?>" href="<?= SITE_URL ?>rider/dashboard.php">
                            <span class="material-symbols-outlined">dashboard</span>
                            <span class="text-sm">Rider Dashboard</span>
                        </a>
                        <a class="<?= isActive('available-orders.php') ?>" href="<?= SITE_URL ?>rider/available-orders.php">
                            <span class="material-symbols-outlined">explore</span>
                            <span class="text-sm">Available Orders</span>
                        </a>
                        <a class="<?= isActive('my-tasks.php') ?>" href="<?= SITE_URL ?>rider/my-tasks.php">
                            <span class="material-symbols-outlined">assignment</span>
                            <span class="text-sm">My Deliveries</span>
                        </a>
                    </nav>
                </div>
     <?php } ?>           
            <div class="px-0">
                    <?php if(($currentUser['role'] ?? '') != 'rider'): ?>
                    <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-4 pl-4">Main Menu</p>
                    <?php endif; ?>
                   <nav class="flex flex-col gap-1">
                        <?php if(($currentUser['role'] ?? '') != 'rider'): ?>
                        <a class="<?= isActive('dashboard.php') ?>" href="<?= SITE_URL ?>dashboard.php">
                            <span class="material-symbols-outlined">dashboard</span>
                            <span class="text-sm">Dashboard</span>
                        </a>
                        <a class="<?= isActive('getting-started.php') ?>" href="<?= SITE_URL ?>getting-started.php">
                            <span class="material-symbols-outlined">school</span>
                            <span class="text-sm">Getting Started</span>
                        </a>
                        <?php endif; ?>
                        <a class="<?= isActive('my-profile.php') ?>" href="<?= SITE_URL ?>my-profile.php">
                           <span class="material-symbols-outlined">person</span>
                           <span class="text-sm">My Profile</span>
                       </a>
                        <?php if(($currentUser['role'] ?? '') != 'rider'): ?>
                       <a class="<?= isActive('my-bookmarks.php') ?>" href="<?= SITE_URL ?>my-bookmarks.php">
                           <span class="material-symbols-outlined">bookmark</span>
                           <span class="text-sm">My Bookmarks</span>
                       </a>
                       <a class="<?= isActive('my-orders.php') ?>" href="<?= SITE_URL ?>my-orders.php">
                           <span class="material-symbols-outlined">receipt_long</span>
                           <span class="text-sm">My Orders</span>
                       </a>
                       <!-- <a class="<?= isActive('my-listings.php') ?>" href="<?= SITE_URL ?>my-listings.php">
                           <span class="material-symbols-outlined">format_list_bulleted</span>
                           <span class="text-sm">My Listings</span>
                       </a> -->
                       <a class="<?= isActive('my-products.php') ?>" href="<?= SITE_URL ?>my-products.php">
                           <span class="material-symbols-outlined">shopping_bag</span>
                           <span class="text-sm">My Products</span>
                       </a>
                       <a class="<?= isActive('my-services.php') ?>" href="<?= SITE_URL ?>my-services.php">
                           <span class="material-symbols-outlined">business_center</span>
                           <span class="text-sm">My Services</span>
                       </a>
                       <!-- <a class="<?= isActive('my-lost-found.php') ?>" href="<?= SITE_URL ?>my-lost-found.php">
                           <span class="material-symbols-outlined">location_searching</span>
                           <span class="text-sm">Lost &amp; Found</span>
                       </a>
                       <a class="<?= isActive('my-ads.php') ?>" href="<?= SITE_URL ?>my-ads.php">
                           <span class="material-symbols-outlined">campaign</span>
                           <span class="text-sm">Create Ad</span>
                       </a> -->
                        <a class="<?= isActive('my-qrstore.php') ?>" href="<?= SITE_URL ?>my-qrstore.php">
                             <span class="material-symbols-outlined">qr_code_scanner</span>
                             <span class="text-sm">My QR Store</span>
                         </a>
                         <a class="<?= isActive('affiliate-center.php') ?>" href="<?= SITE_URL ?>affiliate-center.php">
                             <span class="material-symbols-outlined">trending_up</span>
                             <span class="text-sm">Affiliate Center</span>
                         </a>
                        <?php endif; ?>
                        <a class="<?= isActive('messages.php') ?>" href="<?= SITE_URL ?>messages.php">
                            <span class="material-symbols-outlined">chat</span>
                            <span class="text-sm">Messages</span>
                            <?php if ($navUnread > 0): ?>
                            <span class="ml-auto bg-primary text-white px-1.5 py-0.5 rounded text-[10px] font-bold"><?= $navUnread > 99 ? '99+' : $navUnread ?></span>
                            <?php endif; ?>
                        </a>
                       <a  class="<?= isActive('logout.php') ?> mb-6" href="<?= SITE_URL ?>logout.php">
                           <span class="material-symbols-outlined">logout</span>
                           <span class="text-sm">Logout</span>
                       </a>
                   
                   </nav>
               </div>

               <!-- <div class="px-2">
                   <p class="text-[11px] font-bold text-slate-400 uppercase tracking-widest mb-4">Support &amp; Account</p>
                   <nav class="flex flex-col gap-1">
                       <a class="<?= isActive('messages.php') ?>" href="<?= SITE_URL ?>messages.php">
                           <span class="material-symbols-outlined">mail</span>
                           <span class="text-sm">Messages</span>
                           <span class="ml-auto bg-primary text-white px-1.5 py-0.5 rounded text-[10px] font-bold">3</span>
                       </a>
                       <a class="<?= isActive('support-tickets.php') ?>" href="<?= SITE_URL ?>support-tickets.php">
                           <span class="material-symbols-outlined">support_agent</span>
                           <span class="text-sm">Support Tickets</span>
                           <span class="ml-auto bg-primary text-white px-1.5 py-0.5 rounded text-[10px] font-bold">1</span>
                       </a>
                   </nav>
               </div> -->
           </div>
           <div class="pt-4 border-t border-slate-100">
               
           </div>
       </aside>
