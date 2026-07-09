<?php
// Ensure session is started if not already
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<nav class="fixed top-0 w-full z-[100] bg-white/85 backdrop-blur-xl border-b border-white/20">
    <div class="container mx-auto px-6 py-4 flex justify-between items-center relative">
        <div class="flex items-center gap-6">
            <div class="flex items-center gap-2 border-l pl-4 border-gray-200">
                <div
                    class="w-10 h-10 bg-gradient-to-tr from-primary-600 to-primary-400 rounded-full flex items-center justify-center text-white font-bold text-xl shadow-md">
                    م</div>
                <a href="index.php" class="text-lg font-black text-primary-900 hover:text-primary-700">مهربانی</a>
            </div>
            <!-- Desktop Links -->
            <div class="hidden md:flex items-center space-x-reverse space-x-6 text-sm font-bold text-gray-600">
                <a href="about.php" class="hover:text-primary-600 transition-colors">داستان ما</a>
                <a href="burs-hekmat.php" class="hover:text-primary-600 transition-colors">بورس حکمت</a>
                <a href="campaign.php" class="text-teal-600 font-black hover:text-teal-700 bg-teal-50 px-3 py-1 rounded-lg shadow-sm border border-teal-100 transition-all">پویش حکمت‌یار</a>
                <a href="donors-list.php" class="hover:text-primary-600 transition-colors">نیکوکاران</a>
                <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                    <a href="admin/index.php" class="text-teal-600 hover:text-teal-700 bg-teal-50 px-3 py-1 rounded-lg">پنل مدیریت</a>
                    <a href="admin-logout.php" class="hover:text-red-500">خروج</a>
                <?php elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'student'): ?>
                    <a href="person-detail.php?id=<?php echo $_SESSION['related_id']; ?>" class="text-blue-600 hover:text-blue-700 bg-blue-50 px-3 py-1 rounded-lg">پرونده من</a>
                    <a href="admin-logout.php" class="hover:text-red-500">خروج</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Desktop Login Button -->
        <?php if (!isset($_SESSION['is_admin']) && (!isset($_SESSION['role']) || $_SESSION['role'] !== 'student')): ?>
        <div class="hidden md:block">
            <a href="login.php"
                class="bg-primary-800 text-white px-6 py-2.5 rounded-full font-bold text-sm hover:bg-primary-700 transition-all shadow-md inline-block">ورود
                به پنل کاربری</a>
        </div>
        <?php endif; ?>

        <!-- Mobile Hamburger Button -->
        <div class="md:hidden flex items-center">
            <button id="mobile-menu-btn" class="text-primary-900 hover:text-primary-600 focus:outline-none p-2 bg-gray-50 rounded-lg border border-gray-200">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
        </div>
    </div>

    <!-- Mobile Menu Drawer -->
    <div id="mobile-menu" class="hidden md:hidden bg-white/98 backdrop-blur-xl border-t border-gray-100 absolute w-full left-0 top-full shadow-2xl transition-all duration-300">
        <div class="flex flex-col px-6 py-4 space-y-4 text-center font-bold text-gray-700">
            <a href="about.php" class="hover:text-primary-600 py-3 border-b border-gray-50">داستان ما</a>
            <a href="burs-hekmat.php" class="hover:text-primary-600 py-3 border-b border-gray-50">بورس حکمت</a>
            <a href="campaign.php" class="text-teal-600 hover:text-teal-700 py-3 border-b border-gray-50">پویش حکمت‌یار</a>
            <a href="donors-list.php" class="hover:text-primary-600 py-3 border-b border-gray-50">نیکوکاران</a>
            
            <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                <a href="admin/index.php" class="text-teal-600 hover:text-teal-700 py-3 border-b border-gray-50">پنل مدیریت</a>
                <a href="admin-logout.php" class="text-red-500 hover:text-red-600 py-3 border-b border-gray-50">خروج</a>
            <?php elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'student'): ?>
                <a href="person-detail.php?id=<?php echo $_SESSION['related_id']; ?>" class="text-blue-600 hover:text-blue-700 py-3 border-b border-gray-50">پرونده من</a>
                <a href="admin-logout.php" class="text-red-500 hover:text-red-600 py-3 border-b border-gray-50">خروج</a>
            <?php else: ?>
                <div class="py-4">
                    <a href="login.php" class="bg-primary-800 text-white px-8 py-3 rounded-full font-bold text-base hover:bg-primary-700 transition-all shadow-md inline-block w-full max-w-xs">ورود به پنل کاربری</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</nav>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const mobileBtn = document.getElementById('mobile-menu-btn');
        const mobileMenu = document.getElementById('mobile-menu');
        
        if (mobileBtn && mobileMenu) {
            mobileBtn.addEventListener('click', function(e) {
                e.stopPropagation();
                mobileMenu.classList.toggle('hidden');
            });

            // Close menu when clicking outside
            document.addEventListener('click', function(e) {
                if (!mobileMenu.contains(e.target) && !mobileBtn.contains(e.target)) {
                    if (!mobileMenu.classList.contains('hidden')) {
                        mobileMenu.classList.add('hidden');
                    }
                }
            });
        }
    });
</script>
