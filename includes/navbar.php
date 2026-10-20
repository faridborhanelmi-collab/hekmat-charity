<?php
// Ensure session is started if not already and headers not sent
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
require_once __DIR__ . '/auth.php';
?>
<nav class="fixed top-0 w-full z-[100] bg-white/85 backdrop-blur-xl border-b border-gray-100 shadow-xs">
    <div class="container mx-auto px-6 py-3.5 flex justify-between items-center relative">
        <div class="flex items-center gap-6 lg:gap-8">
            <div class="flex items-center gap-2.5 border-l pl-4 border-gray-200">
                <div class="w-9 h-9 bg-gradient-to-tr from-teal-600 to-emerald-400 rounded-full flex items-center justify-center text-white font-bold text-lg shadow-sm">
                    ح
                </div>
                <a href="index.php" class="text-base font-black text-primary-900 hover:text-teal-700 transition-colors">بنیاد حکمت</a>
            </div>
            
            <!-- Desktop Links (تمیز و خلوت) -->
            <div class="hidden md:flex items-center space-x-reverse space-x-8 text-xs lg:text-sm font-bold text-gray-700">
                <a href="about.php" class="hover:text-teal-600 transition-colors py-1">داستان ما</a>
                <a href="burs-hekmat.php" class="hover:text-teal-600 transition-colors py-1">بورس حکمت</a>
                <a href="index.php#services" class="hover:text-teal-600 transition-colors py-1">خدمات</a>
            </div>
        </div>

        <!-- Desktop Login/Dashboard Button -->
        <div class="hidden md:block">
            <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                <div class="flex items-center gap-2.5">
                    <!-- فانتزی و مینیمال بدون اموجی گاز -->
                    <a href="admin/index.php" class="group relative inline-flex items-center gap-2 px-4 py-2 rounded-2xl bg-gradient-to-l from-slate-900 via-primary-900 to-teal-900 hover:from-teal-800 hover:to-slate-900 text-white text-xs font-black shadow-md shadow-teal-900/15 border border-teal-500/30 transition-all transform hover:-translate-y-0.5">
                        <span class="w-5 h-5 rounded-lg bg-teal-400/20 text-teal-300 flex items-center justify-center transition-transform group-hover:scale-110">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2L14.4 8.6L21 11L14.4 13.4L12 20L9.6 13.4L3 11L9.6 8.6L12 2Z"/>
                            </svg>
                        </span>
                        <span>پنل مدیریت</span>
                    </a>
                    <a href="admin-logout.php" class="text-xs text-rose-600 hover:text-white font-black bg-rose-50 hover:bg-rose-500 border border-rose-200/80 px-3.5 py-2 rounded-2xl transition-all shadow-xs flex items-center gap-1.5 hover:-translate-y-0.5">
                        <span>خروج</span>
                    </a>
                </div>
            <?php elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'student'): ?>
                <div class="flex items-center gap-2.5">
                    <a href="student-dashboard.php" class="bg-primary-900 hover:bg-teal-700 text-white px-4 py-2 rounded-2xl font-black text-xs transition-all shadow-sm flex items-center gap-1.5">
                        <span>🎓</span>
                        <span>میز کار دانش‌آموز</span>
                    </a>
                    <a href="admin-logout.php" class="text-xs text-rose-600 hover:text-white font-black bg-rose-50 hover:bg-rose-500 border border-rose-200/80 px-3.5 py-2 rounded-2xl transition-all shadow-xs">
                        <span>خروج</span>
                    </a>
                </div>
            <?php elseif (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'benefactor'): ?>
                <div class="flex items-center gap-2.5">
                    <a href="donor-dashboard.php" class="bg-teal-700 hover:bg-teal-800 text-white px-4 py-2 rounded-2xl font-black text-xs transition-all shadow-sm inline-flex items-center gap-1.5">
                        <span>💎</span>
                        <span>داشبورد حامیان</span>
                    </a>
                    <a href="login.php?action=logout" class="text-xs text-rose-600 hover:text-white font-black bg-rose-50 hover:bg-rose-500 border border-rose-200/80 px-3.5 py-2 rounded-2xl transition-all shadow-xs">
                        <span>خروج</span>
                    </a>
                </div>
            <?php else: ?>
                <a href="login.php" class="bg-gradient-to-l from-primary-900 to-teal-800 hover:from-teal-700 hover:to-primary-900 text-white px-5 py-2 rounded-2xl font-black text-xs transition-all shadow-sm">
                    ورود به پنل کاربری
                </a>
            <?php endif; ?>
        </div>

        <!-- Mobile Hamburger Button -->
        <div class="md:hidden flex items-center">
            <button id="mobile-menu-btn" aria-label="منوی اصلی سایت" class="text-primary-900 hover:text-teal-600 focus:outline-none p-2 bg-gray-50 rounded-xl border border-gray-200">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
        </div>
    </div>

    <!-- Mobile Menu Drawer -->
    <div id="mobile-menu" class="hidden md:hidden bg-slate-950/95 backdrop-blur-2xl border-t border-white/10 absolute w-full left-0 top-full h-[100dvh] max-h-[calc(100dvh-4.5rem)] overflow-y-auto shadow-2xl transition-all duration-300">
        <div class="flex flex-col px-6 py-6 space-y-3 text-center font-bold text-white">
            <a href="about.php" class="hover:text-teal-300 py-3 border-b border-white/10">داستان ما</a>
            <a href="burs-hekmat.php" class="hover:text-teal-300 py-3 border-b border-white/10">بورس حکمت</a>
            <a href="index.php#services" class="hover:text-teal-300 py-3 border-b border-white/10">خدمات بنیاد</a>
            
            <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                <div class="pt-4 border-t border-white/20 space-y-2">
                    <a href="admin/index.php" class="bg-gradient-to-r from-teal-500 to-emerald-600 text-slate-950 py-3 px-6 rounded-2xl block font-black shadow-lg">
                        ✨ ورود به پنل مدیریت
                    </a>
                    <a href="admin-logout.php" class="text-rose-400 py-2 block text-sm font-bold">خروج از حساب کاربری</a>
                </div>
            <?php elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'student'): ?>
                <a href="student-dashboard.php?tab=books" class="text-teal-300 hover:text-teal-200 py-3 border-b border-white/10 font-bold">📚 مخزن کتاب و امانات</a>
                <a href="person-detail.php?id=<?php echo $_SESSION['related_id'] ?? ($_SESSION['user_id'] ?? 745); ?>" class="text-blue-300 hover:text-blue-400 py-3 border-b border-white/10">پرونده من</a>
                <a href="admin-logout.php" class="text-red-400 hover:text-red-300 py-3 border-b border-white/10">خروج</a>
            <?php elseif (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'benefactor'): ?>
                <a href="donor-dashboard.php" class="text-teal-300 hover:text-teal-200 py-3 border-b border-white/10 font-bold flex items-center justify-center gap-2">
                    <span>💎</span>
                    <span>داشبورد حامیان بنیاد</span>
                </a>
                <div class="py-4">
                    <a href="login.php?action=logout" class="bg-red-500/20 text-red-300 hover:bg-red-500/30 px-6 py-2.5 rounded-2xl font-bold text-xs inline-block w-full max-w-xs border border-red-500/30">خروج از حساب</a>
                </div>
            <?php else: ?>
                <div class="py-6">
                    <a href="login.php" class="bg-teal-500 hover:bg-teal-400 text-slate-950 font-black px-8 py-3 rounded-2xl text-sm inline-block w-full max-w-xs shadow-lg">ورود به پنل کاربری</a>
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
