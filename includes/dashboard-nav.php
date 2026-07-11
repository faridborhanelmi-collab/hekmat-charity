<?php
// Ensure session is started if not already
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$base_url = isset($base_url) ? $base_url : '';
$user_name = $_SESSION['user_name'] ?? 'کاربر گرامی';
$is_admin = $_SESSION['is_admin'] ?? false;
$role = $_SESSION['role'] ?? '';
$related_id = $_SESSION['related_id'] ?? 0;
?>

<nav class="fixed top-0 w-full z-[100] bg-white/85 backdrop-blur-xl border-b border-gray-100 shadow-sm">
    <div class="container mx-auto px-4 md:px-6 py-3 flex justify-between items-center">
        <!-- Brand & Greeting -->
        <div class="flex items-center gap-3">
            <button id="dashboard-menu-btn" class="lg:hidden text-primary-900 hover:text-primary-600 focus:outline-none p-2 bg-gray-50 rounded-lg border border-gray-200 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
            <div class="hidden lg:flex items-center gap-2 border-l pl-4 border-gray-200">
                <div class="w-10 h-10 bg-gradient-to-tr from-primary-600 to-teal-400 rounded-full flex items-center justify-center text-white font-bold text-xl shadow-md">ح</div>
                <a href="<?php echo $base_url; ?>index.php" class="text-lg font-black text-primary-900 hover:text-teal-600 transition-colors">بنیاد حکمت</a>
            </div>
            
            <div class="flex flex-col text-right pr-2">
                <span class="text-[10px] text-gray-500 font-bold">خوش آمدید،</span>
                <span class="text-sm font-black text-primary-900 truncate max-w-[150px] sm:max-w-xs"><?php echo htmlspecialchars($user_name); ?></span>
            </div>
        </div>

        <!-- Desktop Action Links -->
        <div class="hidden lg:flex items-center gap-4 text-sm font-bold text-gray-600">
            <a href="<?php echo $base_url; ?>index.php" class="hover:text-primary-600 transition-colors">خانه</a>
            
            <?php if ($is_admin): ?>
                <a href="<?php echo $base_url; ?>admin/index.php" class="text-teal-600 bg-teal-50 px-4 py-1.5 rounded-lg border border-teal-100">میز کار مدیریت</a>
            <?php elseif ($role === 'student'): ?>
                <a href="<?php echo $base_url; ?>student-dashboard.php" class="text-blue-600 bg-blue-50 px-4 py-1.5 rounded-lg border border-blue-100">داشبورد من</a>
            <?php elseif ($role === 'benefactor'): ?>
                <a href="<?php echo $base_url; ?>donor-dashboard.php" class="text-teal-600 bg-teal-50 px-4 py-1.5 rounded-lg border border-teal-100">پورتال پشتیبان</a>
            <?php endif; ?>
            
            <a href="<?php echo $base_url; ?>admin-logout.php" class="bg-red-50 text-red-500 hover:bg-red-500 hover:text-white px-4 py-1.5 rounded-lg transition-colors flex items-center gap-1">خروج</a>
        </div>
        
        <!-- Mobile Logout Shortcut -->
        <div class="lg:hidden">
            <a href="<?php echo $base_url; ?>admin-logout.php" class="bg-red-50 text-red-500 hover:bg-red-500 hover:text-white p-2 rounded-lg transition-colors inline-block" title="خروج">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
            </a>
        </div>
    </div>

    <!-- Mobile Slide-out Menu Overlay -->
    <div id="dashboard-mobile-overlay" class="fixed inset-0 bg-black/50 z-40 hidden backdrop-blur-sm transition-opacity"></div>
    
    <!-- Mobile Slide-out Menu Panel -->
    <div id="dashboard-mobile-menu" class="fixed top-0 right-0 h-full w-72 bg-white z-50 shadow-2xl transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col">
        <div class="p-6 bg-gray-50 border-b border-gray-100 flex justify-between items-center">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-gradient-to-tr from-primary-600 to-teal-400 rounded-full flex items-center justify-center text-white font-bold text-xl">ح</div>
                <h2 class="font-black text-primary-900">منوی دسترسی</h2>
            </div>
            <button id="close-dashboard-menu" class="text-gray-400 hover:text-red-500 bg-white rounded-full p-1 border border-gray-200">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        
        <div class="flex-1 overflow-y-auto py-4 px-4 space-y-2 font-bold text-gray-700 text-sm">
            <!-- Common Links -->
            <a href="<?php echo $base_url; ?>index.php" class="flex items-center gap-3 p-3 hover:bg-gray-50 rounded-xl transition-colors">
                <span class="text-xl">🏠</span> صفحه اصلی سایت
            </a>
            
            <hr class="my-4 border-gray-100">
            
            <!-- Admin Links -->
            <?php if ($is_admin): ?>
                <div class="text-[10px] text-gray-400 mb-2 px-3 uppercase tracking-wider">مدیریت پلتفرم</div>
                <a href="<?php echo $base_url; ?>admin/index.php" class="flex items-center gap-3 p-3 hover:bg-teal-50 text-teal-700 rounded-xl transition-colors bg-teal-50/50">
                    <span class="text-xl">🎛️</span> داشبورد کلان مدیریت
                </a>
                <a href="<?php echo $base_url; ?>people-list.php" class="flex items-center gap-3 p-3 hover:bg-gray-50 rounded-xl transition-colors">
                    <span class="text-xl">📋</span> لیست مددجویان
                </a>
                <a href="<?php echo $base_url; ?>admin/financial.php" class="flex items-center gap-3 p-3 hover:bg-gray-50 rounded-xl transition-colors">
                    <span class="text-xl">⚖️</span> دفتر حسابداری مالی
                </a>
                <a href="<?php echo $base_url; ?>admin/sponsorships.php" class="flex items-center gap-3 p-3 hover:bg-gray-50 rounded-xl transition-colors">
                    <span class="text-xl">🤝</span> بورس و منتورینگ
                </a>
                <a href="<?php echo $base_url; ?>admin/bursary-payments.php" class="flex items-center gap-3 p-3 hover:bg-gray-50 rounded-xl transition-colors">
                    <span class="text-xl">💳</span> پرداخت بورسیه
                </a>
                <a href="<?php echo $base_url; ?>donors-list.php" class="flex items-center gap-3 p-3 hover:bg-gray-50 rounded-xl transition-colors">
                    <span class="text-xl">💎</span> پورتال نیکوکاران
                </a>
                <a href="<?php echo $base_url; ?>expenses-list.php" class="flex items-center gap-3 p-3 hover:bg-gray-50 rounded-xl transition-colors">
                    <span class="text-xl">💸</span> گزارش هزینه‌ها
                </a>
            
            <!-- Student Links -->
            <?php elseif ($role === 'student'): ?>
                <div class="text-[10px] text-gray-400 mb-2 px-3 uppercase tracking-wider">بخش دانش‌آموز</div>
                <a href="<?php echo $base_url; ?>student-dashboard.php" class="flex items-center gap-3 p-3 hover:bg-blue-50 text-blue-700 rounded-xl transition-colors bg-blue-50/50">
                    <span class="text-xl">🎓</span> داشبورد من
                </a>
                <!-- You can add jump links here if needed -->
                
            <!-- Donor Links -->
            <?php elseif ($role === 'benefactor'): ?>
                <div class="text-[10px] text-gray-400 mb-2 px-3 uppercase tracking-wider">بخش حامیان</div>
                <a href="<?php echo $base_url; ?>donor-dashboard.php" class="flex items-center gap-3 p-3 hover:bg-teal-50 text-teal-700 rounded-xl transition-colors bg-teal-50/50">
                    <span class="text-xl">💎</span> پورتال پشتیبان
                </a>
            <?php endif; ?>
            
            <hr class="my-4 border-gray-100">
            <a href="<?php echo $base_url; ?>admin-logout.php" class="flex items-center gap-3 p-3 hover:bg-red-50 text-red-500 rounded-xl transition-colors">
                <span class="text-xl">🚪</span> خروج از حساب کاربری
            </a>
        </div>
    </div>
</nav>

<!-- Push content down to account for fixed navbar -->
<div class="h-16 lg:h-20 w-full shrink-0"></div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const mobileBtn = document.getElementById('dashboard-menu-btn');
    const closeMenuBtn = document.getElementById('close-dashboard-menu');
    const mobileMenu = document.getElementById('dashboard-mobile-menu');
    const mobileOverlay = document.getElementById('dashboard-mobile-overlay');
    
    function toggleMenu() {
        const isHidden = mobileMenu.classList.contains('translate-x-full');
        if (isHidden) {
            mobileOverlay.classList.remove('hidden');
            // small delay to allow display:block to apply before opacity transition
            setTimeout(() => {
                mobileMenu.classList.remove('translate-x-full');
                mobileMenu.classList.add('translate-x-0');
            }, 10);
        } else {
            mobileMenu.classList.add('translate-x-full');
            mobileMenu.classList.remove('translate-x-0');
            setTimeout(() => {
                mobileOverlay.classList.add('hidden');
            }, 300); // match transition duration
        }
    }

    if (mobileBtn && mobileMenu) {
        mobileBtn.addEventListener('click', toggleMenu);
        closeMenuBtn.addEventListener('click', toggleMenu);
        mobileOverlay.addEventListener('click', toggleMenu);
    }
});
</script>
