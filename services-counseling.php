<?php
$page_title = 'خدمات مشاوره تحصیلی و روانشناسی | بنیاد نیکوکاری حکمت';
$page_desc = 'ارائه مشاوره‌های تخصصی فردی، روانشناختی، هدایت تحصیلی و کارگاه‌های مهارت‌های زندگی برای توانمندسازی همه‌جانبه دانش‌آموزان تحت پوشش خیریه حکمت.';
?>
<!DOCTYPE html>
<html lang="fa-IR" dir="rtl">

<head>
    <?php include 'includes/head.php'; ?>
</head>

<body class="bg-gray-50 font-sans text-gray-800">

    <!-- Navbar -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Hero Header -->
    <header class="relative h-[60vh] flex items-center justify-center bg-primary-900 text-white overflow-hidden pt-20">
        <div class="absolute inset-0 bg-cover bg-center opacity-30"
            style="background-image: url('https://images.unsplash.com/photo-1529333166437-7750a6dd5a70?q=80&w=2669');">
        </div>
        <div class="relative z-10 text-center px-4">
            <h1 class="text-4xl md:text-5xl font-black mb-4">خدمات مشاوره و روانشناسی</h1>
            <p class="text-lg md:text-xl text-teal-100">آرامش ذهن و خودباوری، کلید شکوفایی استعدادهای درخشان</p>
        </div>
    </header>

    <!-- Content Section -->
    <section class="py-20 container mx-auto px-6 max-w-5xl">
        <div class="bg-white rounded-3xl shadow-xl p-8 md:p-14 leading-loose text-lg text-gray-700">

            <div class="grid md:grid-cols-2 gap-12 mb-16 items-center">
                <div class="border-r-4 border-teal-500 pr-6">
                    <h2 class="text-2xl md:text-3xl font-bold text-gray-900 mb-6">همراهی گام‌به‌گام در مسیر موفقیت</h2>
                    <p class="text-gray-600 mb-4 text-justify">
                        ما در بنیاد حکمت معتقدیم که حمایت عاطفی و روانشناختی به اندازه حمایت مالی اهمیت دارد. بسیاری از نخبگان مناطق محروم با موانع روانی، اضطراب کنکور و کمبود اعتمادبه‌نفس مواجه هستند. تیم مشاوران خبره ما با برگزاری جلسات منظم، فضایی امن برای رشد همه‌جانبه دانش‌آموزان فراهم می‌کنند.
                    </p>
                    <div class="bg-teal-50 border border-teal-200 p-4 rounded-2xl text-sm text-teal-900">
                        <span class="font-bold block mb-1">🎯 هدف واحد مشاوره:</span>
                        ارتقای سلامت روان، افزایش انگیزه پیشرفت تحصیلی و هدایت شغلی هدفمند متناسب با تیپ شخصیتی هر دانش‌پژوه.
                    </div>
                </div>

                <div class="relative group">
                    <img src="https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?q=80&w=800&auto=format&fit=crop" 
                         alt="جلسه مشاوره تحصیلی و روانشناسی دانش‌آموزان بنیاد حکمت"
                         width="600" 
                         height="400"
                         loading="lazy" 
                         decoding="async"
                         class="relative rounded-2xl shadow-xl w-full h-auto object-cover ring-4 ring-teal-100">
                </div>
            </div>

            <div class="bg-indigo-50/70 border border-indigo-100 p-8 rounded-2xl">
                <h3 class="text-xl font-bold text-indigo-900 mb-4">سرفصل‌های خدمات تخصصی مشاوره بنیاد</h3>
                <ul class="grid md:grid-cols-2 gap-4 text-indigo-900 text-sm font-medium">
                    <li class="flex items-center gap-2">
                        <span class="w-2 h-2 bg-indigo-500 rounded-full"></span>
                        مشاوره فردی تخصصی و برنامه‌ریزی درسی هفتگی
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="w-2 h-2 bg-indigo-500 rounded-full"></span>
                        آزمون‌های روان‌سنجی، استعدادیابی و هوش هرمان
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="w-2 h-2 bg-indigo-500 rounded-full"></span>
                        کارگاه‌های مدیریت استرس کنکور و فنون تست‌زنی
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="w-2 h-2 bg-indigo-500 rounded-full"></span>
                        جلسات توانمندسازی و گفتگوی همدلانه با اولیای دانش‌آموزان
                    </li>
                </ul>
            </div>

        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-12 text-center">
        <p class="opacity-70 text-sm">© ۱۴۰۳ بنیاد نیکوکاری حکمت (شماره ثبت ۷۷۰۷). تمامی حقوق محفوظ است.</p>
    </footer>

    <script>
      if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
          navigator.serviceWorker.register('/sw.js');
        });
      }
    </script>

</body>
</html>