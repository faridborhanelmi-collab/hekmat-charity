<?php
// Ensure session is started if not already and headers not sent
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
require_once __DIR__ . '/auth.php';
?>
<nav class="fixed top-0 w-full z-[100] bg-white/90 backdrop-blur-xl border-b border-gray-100 shadow-xs">
    <div class="container mx-auto px-6 py-3.5 flex justify-between items-center relative">
        <!-- Logo & Navigation Links -->
        <div class="flex items-center gap-8 lg:gap-10">
            <!-- Brand Logo -->
            <div class="flex items-center gap-2.5 border-l pl-5 border-gray-200">
                <div class="w-10 h-10 bg-gradient-to-tr from-teal-600 to-emerald-400 rounded-2xl flex items-center justify-center text-white font-black text-xl shadow-md shadow-teal-500/20">
                    ح
                </div>
                <a href="index.php" class="text-base font-black text-primary-900 hover:text-teal-700 transition-colors">بنیاد حکمت</a>
            </div>
            
            <!-- Desktop Links (کاملاً تفکیک‌شده و با فاصله عالی) -->
            <div class="hidden md:flex items-center text-sm font-bold text-gray-700">
                <a href="about.php" class="px-4 py-2 rounded-xl hover:text-teal-700 hover:bg-teal-50/70 transition-all" style="margin-left: 1.5rem;">داستان ما</a>
                <a href="burs-hekmat.php" class="px-4 py-2 rounded-xl hover:text-teal-700 hover:bg-teal-50/70 transition-all" style="margin-left: 1.5rem;">بورس حکمت</a>
                <a href="index.php#services" class="px-4 py-2 rounded-xl hover:text-teal-700 hover:bg-teal-50/70 transition-all">خدمات</a>
            </div>
        </div>

        <!-- Desktop Actions: Integrated Glassmorphism Island -->
        <div class="hidden md:block">
            <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin']): ?>
                <!-- جزیره یکپارچه، شیک و فانتزی پنل مدیریت و خروج -->
                <div class="flex items-center bg-gray-50/90 hover:bg-gray-100/90 p-1.5 rounded-2xl border border-gray-200/80 shadow-xs transition-all">
                    <!-- دکمه پرمیوم پنل مدیریت -->
                    <a href="admin/index.php" class="group flex items-center gap-2 bg-gradient-to-r from-teal-600 to-emerald-600 hover:from-teal-500 hover:to-emerald-500 text-white px-4 py-2 rounded-xl text-xs font-black shadow-sm transition-all transform hover:-translate-y-0.5">
                        <span class="w-2 h-2 rounded-full bg-white animate-pulse"></span>
                        <span>پنل مدیریت</span>
                        <svg class="w-3.5 h-3.5 text-teal-100 group-hover:-translate-x-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                        </svg>
                    </a>

                    <!-- خط حائل ظریف -->
                    <div class="w-px h-5 bg-gray-300 mx-1.5"></div>

                    <!-- دکمه خروج هماهنگ و مینیمال -->
                    <a href="admin-logout.php" class="group flex items-center gap-1.5 text-xs font-bold text-gray-500 hover:text-rose-600 px-3 py-2 rounded-xl hover:bg-rose-50/80 transition-all" title="خروج از حساب">
                        <svg class="w-4 h-4 text-gray-400 group-hover:text-rose-600 transition-colors" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        <span>خروج</span>
                    </a>
                </div>
            <?php elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'student'): ?>
                <div class="flex items-center bg-gray-50 p-1.5 rounded-2xl border border-gray-200/80 shadow-xs">
                    <a href="student-dashboard.php" class="flex items-center gap-2 bg-primary-900 hover:bg-teal-700 text-white px-4 py-2 rounded-xl text-xs font-black shadow-sm transition-all">
                        <span>🎓</span>
                        <span>میز کار دانش‌آموز</span>
                    </a>
                    <div class="w-px h-5 bg-gray-300 mx-1.5"></div>
                    <a href="admin-logout.php" class="flex items-center gap-1 text-xs font-bold text-gray-500 hover:text-rose-600 px-3 py-2 rounded-xl hover:bg-rose-50 transition-all">
                        <span>خروج</span>
                    </a>
                </div>
            <?php elseif (isset($_SESSION['user_id']) && ($_SESSION['role'] ?? '') === 'benefactor'): ?>
                <div class="flex items-center bg-gray-50 p-1.5 rounded-2xl border border-gray-200/80 shadow-xs">
                    <a href="donor-dashboard.php" class="flex items-center gap-2 bg-teal-700 hover:bg-teal-800 text-white px-4 py-2 rounded-xl text-xs font-black shadow-sm transition-all">
                        <span>💎</span>
                        <span>داشبورد حامیان</span>
                    </a>
                    <div class="w-px h-5 bg-gray-300 mx-1.5"></div>
                    <a href="login.php?action=logout" class="flex items-center gap-1 text-xs font-bold text-gray-500 hover:text-rose-600 px-3 py-2 rounded-xl hover:bg-rose-50 transition-all">
                        <span>خروج</span>
                    </a>
                </div>
            <?php else: ?>
                <a href="login.php" class="bg-gradient-to-l from-primary-900 via-primary-800 to-teal-800 hover:from-teal-700 hover:to-primary-900 text-white px-6 py-2.5 rounded-2xl font-black text-xs transition-all shadow-md hover:-translate-y-0.5 transform">
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
                        ورود به پنل مدیریت
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
