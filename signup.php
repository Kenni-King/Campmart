<?php session_start();

if (isset($_GET['redirect'])) {
    $_SESSION['login_redirect'] = $_GET['redirect'];
}

include_once 'includes/controller.php';
?>
<!DOCTYPE html>
<html class="light" lang="en">

<head>
    <meta charset="utf-8" />
    <base href="<?php echo SITE_URL; ?>">
    <meta content="width=device-width, initial-scale=1.0" name="viewport" />
    <title>CampMart | Create Account</title>
    <script src="tailwind34.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&amp;display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#064E3B",
                        "secondary": "#F97316",
                        "accent": "#BEF264",
                        "surface": "#F8FAFC",
                    },
                    fontFamily: {
                        "display": ["Plus Jakarta Sans", "sans-serif"]
                    },
                },
            },
        }
    </script>
    <style type="text/tailwindcss">
        @layer components {
            .input-field {
                @apply w-full px-4 py-3 rounded-xl border border-slate-200 focus:border-primary focus:ring-2 focus:ring-primary/10 transition-all outline-none text-slate-700 placeholder:text-slate-400;
            }
            .btn-primary {
                @apply w-full bg-primary text-white px-6 py-3.5 rounded-xl font-bold hover:bg-opacity-90 transition-all flex items-center justify-center gap-2 shadow-lg shadow-primary/20;
            }
            .btn-outline {
                @apply w-full border border-slate-200 text-slate-700 px-6 py-3.5 rounded-xl font-bold hover:bg-slate-50 transition-all flex items-center justify-center gap-3;
            }
        }
        
        /* Select2 custom styling */
        .select2-container--default .select2-selection--single {
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            height: 3rem;
            padding: 0.5rem 1rem;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            line-height: 2rem;
            color: #334155;
            padding-left: 0;
        }
        
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 3rem;
            right: 0.5rem;
        }
        
        .select2-container--default.select2-container--focus .select2-selection--single {
            border-color: #064E3B;
            box-shadow: 0 0 0 2px rgba(6, 78, 59, 0.1);
        }
        
        .select2-dropdown {
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: #064E3B;
        }
        
        .select2-container--default .select2-search--dropdown .select2-search__field {
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 0.5rem 0.75rem;
        }
        
        .select2-container--default .select2-search--dropdown .select2-search__field:focus {
            border-color: #064E3B;
            outline: none;
        }
    </style>

</head>

<body class="bg-white font-display text-slate-900 antialiased">
    <div class="flex min-h-screen max-h-screen">
        <div class="hidden lg:flex lg:w-1/2 relative overflow-hidden bg-primary h-screen sticky top-0">
            <img alt="Campus Life" class="absolute inset-0 w-full h-full object-cover opacity-40 mix-blend-overlay" src="https://lh3.googleusercontent.com/aida-public/AB6AXuBodIM_L8NUfhwfjs4RVRVGOI3fao1AQPY-zFphNZLuozcnBGgv8k_NfXaM3kforNwf7_9_0_kk8WEJfxb9Vp3jBtYLKdFTfjhKOF3u4j1dpnPrL5qSHRH3dmC6NQisFpT_CkZm_MXotk0R1Lg5HXgi-5v6EU5F7nNpZccMCDlGFcZ_69VEf43WMLhLTkChM32fNBS2amc6Wgsi2ZBP3s0xbu8X3Ejn7nIKJEjDTER6-plThcGv4U-XUxKT1WyU-tea5bPmFZJ0U3t8" />
            <div class="absolute inset-0 bg-gradient-to-tr from-primary via-primary/80 to-transparent"></div>
            <div class="relative z-10 p-16 flex flex-col justify-between w-full">
                <a class="flex items-center gap-3" href="/">
                    <div class="size-12 flex items-center justify-center bg-white text-primary rounded-xl shadow-xl">
                        <span class="material-symbols-outlined font-bold text-3xl">shopping_bag</span>
                    </div>
                    <span class="text-3xl font-extrabold tracking-tight text-white">CampMart</span>
                </a>
                <div class="max-w-md">
                    <span class="inline-flex items-center gap-2 px-4 py-1.5 bg-secondary/20 border border-secondary/30 text-secondary rounded-full text-xs font-black uppercase tracking-widest mb-6">
                        <span class="material-symbols-outlined text-sm">verified_user</span> Campus Restricted
                    </span>
                    <h2 class="text-5xl font-extrabold text-white leading-tight mb-6">
                        Join your <br /><span class="text-secondary">campus marketplace.</span>
                    </h2>
                    <p class="text-xl text-slate-200/90 font-medium">
                        Connect with thousands of verified students. Buy, sell, and trade safely within your campus community.
                    </p>
                </div>
                <div class="flex items-center gap-8 text-white/60">
                    <div class="flex flex-col">
                        <span class="text-2xl font-bold text-white">15k+</span>
                        <span class="text-xs uppercase tracking-wider font-bold">Active Students</span>
                    </div>
                    <div class="h-8 w-px bg-white/20"></div>
                    <div class="flex flex-col">
                        <span class="text-2xl font-bold text-white">50+</span>
                        <span class="text-xs uppercase tracking-wider font-bold">Campus Hubs</span>
                    </div>
                </div>
            </div>
        </div>
        <div class="w-full lg:w-1/2 flex justify-center p-8 md:p-12 lg:p-16 bg-white relative overflow-y-auto max-h-screen">
            <div class="absolute top-8 left-8 lg:hidden">
                <a class="flex items-center gap-2" href="/">
                    <div class="size-10 flex items-center justify-center bg-primary text-white rounded-lg">
                        <span class="material-symbols-outlined font-bold text-2xl">shopping_bag</span>
                    </div>
                    <span class="text-xl font-extrabold text-primary">CampMart</span>
                </a>
            </div>
            <div class="w-full max-w-md pt-16 pb-20 lg:py-8">
                <div class="mb-8">
                    <h1 class="text-3xl font-extrabold text-slate-900 mb-2">Create Your Account</h1>
                    <p class="text-slate-500 font-medium">Join your campus community marketplace today.</p>
                </div>
                
                <?php if(isset($_SESSION['error'])): ?>
                <div class="p-4 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm font-medium mb-6">
                    <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
                <?php endif; ?>
                
                <?php if(isset($_SESSION['success'])): ?>
                <div class="p-4 bg-green-50 border border-green-200 rounded-xl text-green-700 text-sm font-medium mb-6">
                    <?= $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
                <?php endif; ?>
                
                <form class="space-y-5" method="POST">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2" for="firstName">First Name</label>
                            <input class="input-field" id="firstName" name="firstName" placeholder="John" required type="text" />
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-slate-700 mb-2" for="lastName">Last Name</label>
                            <input class="input-field" id="lastName" name="lastName" placeholder="Doe" required type="text" />
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2" for="email">Email</label>
                        <input class="input-field" id="email" name="email" placeholder="youremail@gmail.com" required type="email" />
                        <p class="text-xs text-slate-500 mt-1.5">Use your most active email address</p>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2" for="phone">Phone Number</label>
                        <input class="input-field" id="phone" name="phone" placeholder="+234 801 234 5678" required type="tel" />
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2" for="password">Password</label>
                        <div class="relative">
                            <input class="input-field pr-12" id="password" name="password" placeholder="••••••••" required type="password" />
                            <button class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600" onclick="togglePassword('password')" type="button">
                                <span class="material-symbols-outlined text-xl" id="passwordIcon">visibility</span>
                            </button>
                        </div>
                        <p class="text-xs text-slate-500 mt-1.5">Minimum 8 characters with letters and numbers</p>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2" for="confirmPassword">Confirm Password</label>
                        <div class="relative">
                            <input class="input-field pr-12" id="confirmPassword" name="confirmPassword" placeholder="••••••••" required type="password" />
                            <button class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600" onclick="togglePassword('confirmPassword')" type="button">
                                <span class="material-symbols-outlined text-xl" id="confirmPasswordIcon">visibility</span>
                            </button>
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2" for="university">Campus</label>
                        <select class="input-field" id="university" name="university" required>
                            <option value="">Select your campus</option>
                            <?php
                            $universities = $db->query("SELECT * FROM universities WHERE status = 'active' ORDER BY name ASC");
                            while($uni = $universities->fetch_assoc()):
                            ?>
                                <option value="<?= $uni['id'] ?>"><?= htmlspecialchars($uni['name']) ?></option>
                            <?php endwhile; ?>
                        </select>
                        <p class="text-xs text-slate-500 mt-1.5">
                            Don’t see your campus?
                            <a class="text-primary font-bold hover:underline" href="add-campus.php">Add your campus</a>
                        </p>
                    </div>
                    
                    <?php if(isset($_GET['ref'])): ?>
                    <input type="hidden" name="referral_code" value="<?= htmlspecialchars($_GET['ref']) ?>" />
                    <div class="p-4 bg-accent/20 border border-accent/50 rounded-xl">
                        <p class="text-sm font-medium text-slate-700">
                            <span class="material-symbols-outlined text-lg align-middle text-primary">card_giftcard</span>
                            You're signing up with a referral code!
                        </p>
                    </div>
                    <?php else: ?>
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2" for="referral_code">Referral Code (Optional)</label>
                        <input class="input-field" id="referral_code" name="referral_code" placeholder="Enter referral code if you have one" type="text" />
                        <p class="text-xs text-slate-500 mt-1.5">Have a referral code? Enter it to get special benefits</p>
                    </div>
                    <?php endif; ?>
                    
                    <div id="errorMessage" class="hidden p-4 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm font-medium">
                    </div>
                    <div id="successMessage" class="hidden p-4 bg-green-50 border border-green-200 rounded-xl text-green-700 text-sm font-medium">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-2">Account Type</label>
                        <div class="grid grid-cols-2 gap-3">
                            <label class="relative flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3.5 cursor-pointer transition hover:border-primary/50 has-[:checked]:border-primary has-[:checked]:bg-primary/5">
                                <input class="sr-only peer" type="radio" name="role" value="user" checked>
                                <span class="material-symbols-outlined text-xl text-slate-400 peer-checked:text-primary">person</span>
                                <div>
                                    <span class="block text-sm font-bold text-slate-700 peer-checked:text-primary">Buyer / Seller</span>
                                    <span class="text-xs text-slate-500">Buy & sell on campus</span>
                                </div>
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 size-4 rounded-full border-2 border-slate-300 has-[:checked]:border-primary has-[:checked]:bg-primary">
                                    <span class="hidden size-2 bg-white rounded-full absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 peer-checked:block"></span>
                                </span>
                            </label>
                            <label class="relative flex items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3.5 cursor-pointer transition hover:border-orange-400/50 has-[:checked]:border-orange-500 has-[:checked]:bg-orange-50">
                                <input class="sr-only peer" type="radio" name="role" value="rider">
                                <span class="material-symbols-outlined text-xl text-slate-400 peer-checked:text-orange-500">motorcycle</span>
                                <div>
                                    <span class="block text-sm font-bold text-slate-700 peer-checked:text-orange-600">Platform Rider</span>
                                    <span class="text-xs text-slate-500">Deliver orders & earn</span>
                                </div>
                                <span class="absolute right-3 top-1/2 -translate-y-1/2 size-4 rounded-full border-2 border-slate-300 has-[:checked]:border-orange-500 has-[:checked]:bg-orange-500">
                                    <span class="hidden size-2 bg-white rounded-full absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 peer-checked:block"></span>
                                </span>
                            </label>
                        </div>
                    </div>
                    <div class="flex items-start gap-3">
                        <input class="size-5 rounded border-slate-300 text-primary focus:ring-primary cursor-pointer mt-0.5" id="terms" name="terms" required type="checkbox" />
                        <label class="text-sm font-medium text-slate-600 cursor-pointer" for="terms">
                            I agree to CampMart's <a class="text-primary font-bold hover:underline" target="_blank" href="terms-of-service.php">Terms of Service</a> and <a class="text-primary font-bold hover:underline"  target="_blank" href="privacy-policy.php">Privacy Policy</a>
                        </label>
                    </div>
                    <button class="btn-primary" name="SignupUser" type="submit">
                        Create Account
                        <span class="material-symbols-outlined text-xl">arrow_forward</span>
                    </button>
                </form>
                <div class="mt-8 text-center">
                    <p class="text-slate-500 font-medium">
                        Already have an account?
                        <a class="text-primary font-bold hover:underline underline-offset-4 ml-1" href="login.php">Sign In</a>
                    </p>
                </div>
                <div class="h-28"></div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = document.getElementById(fieldId + 'Icon');

            if (field.type === 'password') {
                field.type = 'text';
                icon.textContent = 'visibility_off';
            } else {
                field.type = 'password';
                icon.textContent = 'visibility';
            }
        }
        
        // Initialize Select2 for university dropdown
        $(document).ready(function() {
            $('#university').select2({
                placeholder: 'Search for your campus...',
                allowClear: true,
                width: '100%',
                dropdownAutoWidth: true,
                minimumResultsForSearch: 0 // Always show search box
            });
        });
        
        <?php if(isset($_SESSION['success']) && isset($_SESSION['user_id'])): ?>
        // Redirect to index after successful signup
        setTimeout(function() {
            window.location.href = 'index.php';
        }, 2000);
        <?php endif; ?>
    </script>
</body>

</html>