<?php
// Ensure session is started if not already and headers not sent
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
require_once __DIR__ . '/auth.php';
?>
<nav class="fixed top-0 w-full z-[100] bg-white/85 backdrop-blur-xl border-b border-white/20">
    <div class="container mx-auto px-6 py-4 flex justify-between items-center relative">
        <div class="flex items-center gap-6">
            <div class="flex items-center gap-2 border-l pl-4 border-gray-200">
                <div
                    class="w-10 h-10 bg-gradient-to-tr from-primary-600 to-primary-400 rounded-full flex items-center justify-center text-white font-bold text-xl shadow-md">
                    ح</div>
                <a href="index.php" class="text-lg font-black text-primary-900 hover:text-primary-700">بنیاد حکمت</a>
            </div>
            <!-- Desktop Links -->
            <div class="hidden md:flex items-center space-x-reverse space-x-6 text-sm font-bold text-gray-600">
                <a href="about.php" class="hover:text-primary-600 transition-colors">داستان ما</a>
                <a href="burs-hekmat.php" class="hover:text-primary-600 transition-colors">بورس حکمت</a>
                <a href="campaign.php" class="text-teal-600 font-black hover:text-teal-700 bg-teal-50 px-3 py-1 rounded-lg shadow-sm border border-teal-100 transition-all">پویش حکمت‌یار</a>
                <a href="almas.php" class="text-emerald-700 font-black hover:text-emerald-800 bg-emerald-50 hover:bg-emerald-100 px-3 py-1 rounded-lg shadow-sm border border-emerald-200 transition-all flex items-center gap-1.5">
                    <span class="text-xs">💎</span>
                    <span>گنج‌های پنهان</span>
                </a>
                <a href="index.php#services" class="hover:text-primary-600 transition-colors">خدمات</a>
                <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                    <?php if (($_SESSION['role'] ?? '') !== 'data_operator'): ?>
                        <a href="people-list.php" class="hover:text-primary-600 transition-colors">مددجویان</a>
                    <?php endif; ?>
                    <a href="admin/library.php" class="text-teal-700 font-bold hover:text-teal-800 bg-teal-50 px-3.5 py-1.5 rounded-lg border border-teal-200 flex items-center gap-1.5">📚 بانک کتاب و امانات (۲۰۱ جلد)</a>
                    <?php if (($_SESSION['role'] ?? '') === 'data_operator'): ?>
                        <a href="switch-role.php?target=student" class="text-indigo-600 font-bold hover:text-indigo-700 bg-indigo-50 px-3 py-1 rounded-lg">🎒 پورتال دانش‌آموزی من</a>
                    <?php else: ?>
                        <a href="admin/ai-prep.php" class="text-indigo-600 font-bold hover:text-indigo-700 bg-indigo-50 px-3 py-1 rounded-lg">🤖 آماده‌سازی هوش مصنوعی</a>
                    <?php endif; ?>
                    <?php if (function_exists('can_view_financial') && can_view_financial()): ?>
                        <a href="donors-list.php" class="hover:text-primary-600 transition-colors">نیکوکاران</a>
                    <?php endif; ?>
                    <?php if (($_SESSION['role'] ?? '') !== 'data_operator'): ?>
                        <a href="admin/index.php" class="text-teal-600 hover:text-teal-700 bg-teal-50 px-3 py-1 rounded-lg">پنل مدیریت</a>
                    <?php endif; ?>
                    <a href="admin-logout.php" class="hover:text-red-500">خروج</a>
                <?php elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'student'): ?>
                    <a href="student-dashboard.php?tab=books" class="text-teal-700 font-bold hover:text-teal-800 bg-teal-50 px-3.5 py-1.5 rounded-lg border border-teal-200 flex items-center gap-1.5">📚 بانک کتاب و امانات</a>
                    <?php if (function_exists('is_viana') && is_viana()): ?>
                        <a href="admin/library.php" class="text-indigo-700 font-bold hover:text-indigo-800 bg-indigo-50 px-3 py-1 rounded-lg border border-indigo-200">💻 میز کار اپراتور کتابخانه</a>
                    <?php endif; ?>
                    <a href="person-detail.php?id=<?php echo $_SESSION['related_id'] ?? ($_SESSION['user_id'] ?? 745); ?>" class="text-blue-600 hover:text-blue-700 bg-blue-50 px-3 py-1 rounded-lg">پرونده من</a>
                    <a href="admin-logout.php" class="hover:text-red-500">خروج</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Desktop Login/Dashboard Button -->
        <div class="hidden md:block">
            <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                <!-- Links in desktop nav -->
            <?php elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'student'): ?>
                <a href="student-dashboard.php" class="bg-primary-800 text-white px-6 py-2.5 rounded-full font-bold text-sm hover:bg-primary-700 transition-all shadow-md inline-block">میز کار دانش‌آموز</a>
            <?php elseif (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'benefactor'): ?>
                <div class="flex items-center gap-3">
                    <a href="donor-dashboard.php" class="bg-teal-700 text-white px-5 py-2.5 rounded-full font-bold text-sm hover:bg-teal-800 transition-all shadow-md inline-flex items-center gap-1.5">
                        <span>💎</span>
                        <span>داشبورد حامیان</span>
                    </a>
                    <a href="login.php?action=logout" class="text-xs text-gray-500 hover:text-red-600 font-bold transition-colors">خروج</a>
                </div>
            <?php else: ?>
                <a href="login.php"
                    class="bg-primary-800 text-white px-6 py-2.5 rounded-full font-bold text-sm hover:bg-primary-700 transition-all shadow-md inline-block">ورود
                    به پنل کاربری</a>
            <?php endif; ?>
        </div>

        <!-- Mobile Hamburger Button -->
        <div class="md:hidden flex items-center">
            <button id="mobile-menu-btn" aria-label="منوی اصلی سایت" class="text-primary-900 hover:text-primary-600 focus:outline-none p-2 bg-gray-50 rounded-lg border border-gray-200">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
        </div>
    </div>

    <!-- Mobile Menu Drawer -->
    <div id="mobile-menu" class="hidden md:hidden bg-black/90 backdrop-blur-2xl border-t border-white/10 absolute w-full left-0 top-full h-[100dvh] max-h-[calc(100dvh-4.5rem)] overflow-y-auto shadow-2xl transition-all duration-300">
        <div class="flex flex-col px-6 py-8 space-y-4 text-center font-bold text-white">
            <a href="about.php" class="hover:text-primary-300 py-4 border-b border-white/10">داستان ما</a>
            <a href="burs-hekmat.php" class="hover:text-primary-300 py-4 border-b border-white/10">بورس حکمت</a>
            <a href="campaign.php" class="text-teal-300 hover:text-teal-400 py-4 border-b border-white/10">پویش حکمت‌یار</a>
            <a href="almas.php" class="text-emerald-300 hover:text-emerald-200 py-4 border-b border-white/10 flex items-center justify-center gap-2 font-black">
                <span>💎</span>
                <span>پویش گنج‌های پنهان</span>
            </a>
            <a href="index.php#services" class="hover:text-primary-300 py-4 border-b border-white/10">خدمات بنیاد</a>
            
            <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                <?php if (($_SESSION['role'] ?? '') !== 'data_operator'): ?>
                    <a href="people-list.php" class="hover:text-primary-300 py-4 border-b border-white/10">مددجویان</a>
                <?php endif; ?>
                <a href="admin/library.php" class="text-teal-300 hover:text-teal-200 py-4 border-b border-white/10 font-bold">📚 مخزن کتاب و امانات (۲۰۱ جلد)</a>
                <?php if (($_SESSION['role'] ?? '') === 'data_operator'): ?>
                    <a href="switch-role.php?target=student" class="text-indigo-300 hover:text-indigo-200 py-4 border-b border-white/10 font-bold">🎒 پورتال دانش‌آموزی من</a>
                <?php else: ?>
                    <a href="admin/ai-prep.php" class="text-indigo-300 hover:text-indigo-200 py-4 border-b border-white/10 font-bold">🤖 آماده‌سازی هوش مصنوعی</a>
                <?php endif; ?>
                <?php if (function_exists('can_view_financial') && can_view_financial()): ?>
                    <a href="donors-list.php" class="hover:text-primary-300 py-4 border-b border-white/10">نیکوکاران</a>
                <?php endif; ?>
                <?php if (($_SESSION['role'] ?? '') !== 'data_operator'): ?>
                    <a href="admin/index.php" class="text-teal-300 hover:text-teal-400 py-4 border-b border-white/10">پنل مدیریت</a>
                <?php endif; ?>
                <a href="admin-logout.php" class="text-red-400 hover:text-red-300 py-4 border-b border-white/10">خروج</a>
            <?php elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'student'): ?>
                <a href="student-dashboard.php?tab=books" class="text-teal-300 hover:text-teal-200 py-4 border-b border-white/10 font-bold">📚 مخزن کتاب و امانات</a>
                <?php if (function_exists('is_viana') && is_viana()): ?>
                    <a href="admin/library.php" class="text-indigo-300 hover:text-indigo-200 py-4 border-b border-white/10 font-bold">💻 میز کار اپراتور کتابخانه</a>
                <?php endif; ?>
                <a href="person-detail.php?id=<?php echo $_SESSION['related_id'] ?? ($_SESSION['user_id'] ?? 745); ?>" class="text-blue-300 hover:text-blue-400 py-4 border-b border-white/10">پرونده من</a>
                <a href="admin-logout.php" class="text-red-400 hover:text-red-300 py-4 border-b border-white/10">خروج</a>
            <?php elseif (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'benefactor'): ?>
                <a href="donor-dashboard.php" class="text-teal-300 hover:text-teal-200 py-4 border-b border-white/10 font-bold flex items-center justify-center gap-2">
                    <span>💎</span>
                    <span>داشبورد حامیان بنیاد</span>
                </a>
                <div class="py-6">
                    <a href="login.php?action=logout" class="bg-red-500/20 text-red-300 hover:bg-red-500/30 px-6 py-3 rounded-full font-bold text-sm inline-block w-full max-w-xs border border-red-500/30">خروج از حساب کاربری</a>
                </div>
            <?php else: ?>
                <div class="py-8">
                    <a href="login.php" class="bg-primary-600 text-white px-8 py-4 rounded-full font-bold text-lg hover:bg-primary-500 transition-all shadow-[0_0_20px_rgba(255,255,255,0.2)] inline-block w-full max-w-xs">ورود به پنل کاربری</a>
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
