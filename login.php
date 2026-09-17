<?php session_start();

if (isset($_GET['redirect'])) {
    $_SESSION['login_redirect'] = $_GET['redirect'];
}

include_once 'includes/controller.php';
?>
<!DOCTYPE html>
<html class="light" lang="en">
<head>
<meta charset="utf-8"/>
<base href="<?php echo SITE_URL; ?>">
<meta content="width=device-width, initial-scale=1.0" name="viewport"/>
<title>CampMart | Secure Login</title>
<script src="tailwind34.js"></script>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&amp;display=swap" rel="stylesheet"/>
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&amp;display=swap" rel="stylesheet"/>
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
    </style>

</head><body class="bg-white font-display text-slate-900 antialiased min-h-screen overflow-hidden">
<div class="flex min-h-screen">
<div class="hidden lg:flex lg:w-1/2 relative overflow-hidden bg-primary">
<img alt="Campus Life" class="absolute inset-0 w-full h-full object-cover opacity-40 mix-blend-overlay" src="https://lh3.googleusercontent.com/aida-public/AB6AXuBodIM_L8NUfhwfjs4RVRVGOI3fao1AQPY-zFphNZLuozcnBGgv8k_NfXaM3kforNwf7_9_0_kk8WEJfxb9Vp3jBtYLKdFTfjhKOF3u4j1dpnPrL5qSHRH3dmC6NQisFpT_CkZm_MXotk0R1Lg5HXgi-5v6EU5F7nNpZccMCDlGFcZ_69VEf43WMLhLTkChM32fNBS2amc6Wgsi2ZBP3s0xbu8X3Ejn7nIKJEjDTER6-plThcGv4U-XUxKT1WyU-tea5bPmFZJ0U3t8"/>
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
<span class="material-symbols-outlined text-sm">verified_user</span> University Restricted
                </span>
<h2 class="text-5xl font-extrabold text-white leading-tight mb-6">
                    Built for your <br/><span class="text-secondary">campus community.</span>
</h2>
<p class="text-xl text-slate-200/90 font-medium">
                    The safest way to buy, sell, and trade with fellow students at your university.
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
<div class="w-full lg:w-1/2 flex items-center justify-center p-8 md:p-16 lg:p-24 bg-white relative">
<div class="absolute top-8 left-8 lg:hidden">
<a class="flex items-center gap-2" href="/">
<div class="size-10 flex items-center justify-center bg-primary text-white rounded-lg">
<span class="material-symbols-outlined font-bold text-2xl">shopping_bag</span>
</div>
<span class="text-xl font-extrabold text-primary">CampMart</span>
</a>
</div>
<div class="w-full max-w-md">
<div class="mb-6">
<h1 class="text-3xl font-extrabold text-slate-900 mb-2">Welcome Back</h1>
<p class="text-slate-500 font-medium">Log in to your campus account to continue.</p>
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

<form class="space-y-6" method="POST">
<div id="errorMessage" class="hidden p-4 bg-red-50 border border-red-200 rounded-xl text-red-700 text-sm font-medium">
</div>
<div id="successMessage" class="hidden p-4 bg-green-50 border border-green-200 rounded-xl text-green-700 text-sm font-medium">
</div>
<div>
<label class="block text-sm font-bold text-slate-700 mb-2" for="email">Email or Phone</label>
<input class="input-field" id="emailOrPhone" name="emailOrPhone" placeholder="student@university.edu" type="text" required/>
</div>
<div>
<div class="flex justify-between items-center mb-2">
<label class="block text-sm font-bold text-slate-700" for="password">Password</label>
<a class="text-sm font-bold text-secondary hover:text-secondary/80" href="forgot-password.php">Forgot Password?</a>
</div>
<div class="relative">
<input class="input-field pr-12" id="password" name="password" placeholder="••••••••" type="password" required/>
<button class="absolute right-4 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600" type="button" onclick="togglePassword()">
<span class="material-symbols-outlined text-xl" id="passwordIcon">visibility</span>
</button>
</div>
</div>
<div class="flex items-center gap-3">
<input class="size-5 rounded border-slate-300 text-primary focus:ring-primary cursor-pointer" id="remember" name="remember" type="checkbox"/>
<label class="text-sm font-medium text-slate-600 cursor-pointer" for="remember">Keep me logged in</label>
</div>
<button class="btn-primary" name="LoginUser" type="submit">
                    Sign In
                    <span class="material-symbols-outlined text-xl">arrow_forward</span>
</button>

</form>
<div class="mt-12 text-center">
<p class="text-slate-500 font-medium">
                    New to CampMart? 
                    <a class="text-primary font-bold hover:underline underline-offset-4 ml-1" href="signup.php">Create an Account</a>
</p>
</div>
</div>
</div>
</div>

<script>
    function togglePassword() {
        const field = document.getElementById('password');
        const icon = document.getElementById('passwordIcon');
        
        if (field.type === 'password') {
            field.type = 'text';
            icon.textContent = 'visibility_off';
        } else {
            field.type = 'password';
            icon.textContent = 'visibility';
        }
    }
    
    <?php if(isset($_SESSION['success']) && isset($_SESSION['user_id'])): ?>
    // Redirect after successful login
    setTimeout(function() {
        <?php 
        $redirect = 'index.php';
        $role = $_SESSION['role'] ?? '';
        if($role == 'admin') {
            $redirect = 'admin/dashboard.php';
        } elseif($role == 'seller') {
            $redirect = 'dashboard.php';
        } elseif($role == 'rider') {
            $redirect = 'rider/dashboard.php';
        }
        ?>
        window.location.href = '<?= $redirect ?>';
    }, 1500);
    <?php endif; ?>
</script>
</body></html>