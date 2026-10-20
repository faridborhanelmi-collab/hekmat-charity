<?php
// Ensure session is started if not already
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
require_once __DIR__ . '/auth.php';

$user_name = $_SESSION['user_name'] ?? 'مدیر سیستم';
if ($user_name === 'فرید علمی (مدیرعامل)' || $user_name === 'فرید علمی' || ($_SESSION['username'] ?? '') === 'faridelmi') {
    $user_name = 'فرید برهان علمی (مدیرعامل)';
    $_SESSION['user_name'] = $user_name;
}
$is_admin = $_SESSION['is_admin'] ?? false;
$role = $_SESSION['role'] ?? '';
$page_nav_title = $page_nav_title ?? '';
?>
<!-- Dedicated Admin Top Navigation Bar -->
<nav class="fixed top-0 left-0 right-0 w-full z-[100] bg-white/95 backdrop-blur-xl border-b border-gray-200/80 shadow-sm transition-all">
    <div class="container mx-auto px-4 sm:px-6 h-16 flex justify-between items-center relative">
        
        <!-- Right: Logo & Branding linking to Admin Dashboard -->
        <div class="flex items-center gap-3">
            <a href="/admin/index.php" title="بازگشت به پنل مدیریت بنیاد حکمت" class="group flex items-center gap-2.5 bg-gray-50 hover:bg-teal-50/60 border border-gray-200 hover:border-teal-300 px-3 py-1.5 rounded-2xl transition-all">
                <img src="/assets/logo-512.webp" alt="لوگو بنیاد حکمت" class="h-8 w-auto object-contain transition-transform group-hover:scale-105">
                <div class="text-right flex flex-col">
                    <span class="text-xs font-black text-gray-900 group-hover:text-teal-700 transition-colors leading-tight">بنیاد نیکوکاری حکمت</span>
                    <span class="text-[10px] text-teal-600 font-bold leading-tight">پنل مدیریت عالی</span>
                </div>
            </a>
            
            <?php if (!empty($page_nav_title)): ?>
                <div class="h-6 w-px bg-gray-200 hidden md:block"></div>
                <span class="text-xs font-bold text-gray-500 hidden md:inline-block"><?php echo htmlspecialchars($page_nav_title); ?></span>
            <?php endif; ?>
        </div>

        <!-- Center: Clean Minimalist Foundation Badge -->
        <div class="hidden lg:flex items-center gap-2 px-4 py-1.5 rounded-full bg-slate-50 border border-gray-200 text-xs font-bold text-gray-600">
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
            <span>سامانه جامع مدیریت و حامیان بنیاد حکمت</span>
        </div>

        <!-- Left: User Greeting, Admin Panel Shortcut & Logout -->
        <div class="flex items-center gap-2 sm:gap-3">
            <div class="hidden sm:flex flex-col text-left pl-2">
                <span class="text-[10px] text-gray-400 font-bold">حساب کاربری:</span>
                <span class="text-xs font-black text-gray-800"><?php echo htmlspecialchars($user_name); ?></span>
            </div>
            
            <a href="/admin/index.php" class="bg-teal-600 hover:bg-teal-700 text-white text-xs font-bold px-3.5 sm:px-4 py-2 rounded-xl shadow-sm transition-all flex items-center gap-1.5">
                <span>🎛️</span>
                <span>پنل مدیریت</span>
            </a>

            <a href="/admin-logout.php" class="bg-red-50 hover:bg-red-100 text-red-600 hover:text-red-700 border border-red-200 text-xs font-bold px-3 py-2 rounded-xl transition-colors flex items-center gap-1">
                <span>خروج</span>
            </a>
        </div>
    </div>
</nav>
<div class="h-16"></div> <!-- Top spacer to prevent content overlap -->
