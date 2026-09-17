
    <footer class="bg-[#031d16] text-white pt-12 pb-10 border-t border-white/5 mt-auto">
        <div class="max-w-[1440px] mx-auto px-6">
            <div class="flex flex-col md:flex-row items-center justify-between gap-6 mb-8">
                <a class="flex items-center gap-2" href="<?= SITE_URL ?>rider/dashboard.php">
                    <div class="size-10 flex items-center justify-center bg-white/10 text-secondary rounded-lg">
                        <span class="material-symbols-outlined font-bold text-2xl">motorcycle</span>
                    </div>
                    <span class="text-2xl font-extrabold tracking-tight text-white">CampMart</span>
                    <span class="rounded-full bg-orange-100 px-2.5 py-0.5 text-xs font-bold text-orange-700">Rider</span>
                </a>
                <nav class="flex flex-wrap items-center justify-center gap-x-8 gap-y-3 text-sm text-slate-400">
                    <a class="hover:text-secondary transition-colors" href="<?= SITE_URL ?>rider/dashboard.php">Dashboard</a>
                    <a class="hover:text-secondary transition-colors" href="<?= SITE_URL ?>rider/available-orders.php">Available Orders</a>
                    <a class="hover:text-secondary transition-colors" href="<?= SITE_URL ?>rider/my-tasks.php">My Deliveries</a>
                    <a class="hover:text-secondary transition-colors" href="<?= SITE_URL ?>messages.php">Messages</a>
                </nav>
            </div>
            <div class="pt-8 border-t border-white/5 flex flex-col md:flex-row justify-between items-center gap-4">
                <p class="text-xs text-slate-500 font-medium">© <?php echo date("Y"); ?> CampMart Technologies. Built for Students, by Students.</p>
                <div class="flex gap-8 text-xs text-slate-500 font-medium">
                    <a class="hover:text-white" href="<?= SITE_URL ?>help-center.php">Help Center</a>
                    <a class="hover:text-white" href="<?= SITE_URL ?>terms-of-service.php">Terms</a>
                    <a class="hover:text-white" href="<?= SITE_URL ?>privacy-policy.php">Privacy</a>
                </div>
            </div>
        </div>
    </footer>
