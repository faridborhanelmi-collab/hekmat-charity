<?php
// Ensure session is started if not already
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
require_once __DIR__ . '/auth.php';
?>
<!-- Dedicated Admin Top Navigation Bar (Clean & Minimal) -->
<nav class="fixed top-0 left-0 right-0 w-full z-[100] bg-white/95 backdrop-blur-xl border-b border-gray-200/80 shadow-xs transition-all">
    <div class="container mx-auto px-4 sm:px-6 h-16 flex justify-between items-center relative">
        
        <!-- Right side (Kept clean & empty) -->
        <div class="flex items-center"></div>

        <!-- Center: Pure Logo linking to Admin Dashboard -->
        <div class="absolute left-1/2 -translate-x-1/2 flex items-center justify-center">
            <a href="/admin/index.php" title="ورود به پنل مدیریت بنیاد حکمت" class="group flex items-center justify-center p-1.5 rounded-2xl hover:bg-teal-50/60 transition-all">
                <img src="/assets/logo-512.webp" alt="لوگو بنیاد حکمت" class="h-10 w-auto object-contain transition-transform group-hover:scale-105">
            </a>
        </div>

        <!-- Left: Logout button to return to Website -->
        <div class="flex items-center">
            <a href="/admin-logout.php?redirect=/index.php" title="خروج و رفتن به وب‌سایت" class="bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-xs font-black px-4 py-2 rounded-xl transition-all shadow-xs flex items-center gap-1.5">
                <span>خروج</span>
            </a>
        </div>
    </div>
</nav>
<div class="h-16"></div> <!-- Top spacer to prevent content overlap -->
