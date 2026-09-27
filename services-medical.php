<?php
$page_title = 'خدمات درمانی و سلامت نخبگان | بنیاد نیکوکاری حکمت';
$page_desc = 'پوشش کامل هزینه‌های پزشکی، دندانپزشکی، بینایی‌سنجی و غربالگری سلامت جسمانی دانش‌آموزان تحت پوشش و خانواده‌های آنان در خیریه حکمت مشهد.';
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
    <header class="relative h-[60vh] flex items-center justify-center bg-teal-900 text-white overflow-hidden pt-20">
        <div class="absolute inset-0 bg-cover bg-center opacity-30"
            style="background-image: url('https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?q=80&w=2670');">
        </div>
        <div class="relative z-10 text-center px-4">
            <h1 class="text-4xl md:text-5xl font-black mb-4">خدمات درمانی و سلامت</h1>
            <p class="text-lg md:text-xl text-teal-100">سلامت جسم و آرامش تن، پیش‌نیاز پرورش ذهن و استعداد است</p>
        </div>
    </header>

    <!-- Content Section -->
    <section class="py-20 container mx-auto px-6 max-w-5xl">
        <div class="bg-white rounded-3xl shadow-xl p-8 md:p-14 leading-loose text-lg text-gray-700">
            <div class="border-r-4 border-teal-500 pr-6 mb-8">
                <h2 class="text-2xl md:text-3xl font-bold text-gray-900 mb-2">پوشش جامع بهداشت و درمان دانش‌آموزان</h2>
                <p class="text-gray-500 text-sm">هیچ دردی نباید مانع از تمرکز دانش‌آموز نخبه بر یادگیری شود</p>
            </div>

            <p class="text-gray-600 text-justify mb-8">
                بنیاد نیکوکاری حکمت علاوه بر بورسیه تحصیلی، سلامت همه‌جانبه دانش‌پژوهان را زیر نظر دارد. بیماری‌های درمان‌نشده، مشکلات دندانپزشکی، ضعف بینایی یا سوءتغذیه می‌توانند کارایی ذهنی دانش‌آموز را به شدت کاهش دهند. به همین منظور، شبکه پزشکان و مراکز درمانی همکار با بنیاد به ارائه خدمات درمانی رایگان به مددجویان می‌پردازند.
            </p>

            <div class="bg-rose-50/70 border border-rose-100 p-8 rounded-2xl mb-8">
                <h3 class="text-xl font-bold text-rose-900 mb-4">خدمات واحد بهداشت و سلامت بنیاد حکمت</h3>
                <ul class="grid md:grid-cols-2 gap-4 text-rose-950 text-sm font-medium">
                    <li class="flex items-center gap-2">
                        <span class="w-2 h-2 bg-rose-500 rounded-full"></span>
                        غربالگری دوره‌ای سلامت عمومی، شنوایی‌سنجی و بینایی‌سنجی
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="w-2 h-2 bg-rose-500 rounded-full"></span>
                        پوشش کامل خدمات دندانپزشکی و ترمیم
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="w-2 h-2 bg-rose-500 rounded-full"></span>
                        تامین داروهای تخصصی و عینک‌های طبی برای دانش‌آموزان
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="w-2 h-2 bg-rose-500 rounded-full"></span>
                        ارجاع به پزشکان متخصص همکار و جراحی‌های ضروری در صورت لزوم
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