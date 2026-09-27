<?php
$page_title = 'خدمات آموزشی و کمک‌تحصیلی | بنیاد نیکوکاری حکمت';
$page_desc = 'تامین کتب کمک‌آموزشی، برگزاری کلاس‌های کنکور و المپیاد، آزمون‌های آزمایشی و تامین تجهیزات دیجیتال برای دانش‌آموزان مستعد مناطق محروم در خیریه حکمت.';
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
            style="background-image: url('https://images.unsplash.com/photo-1503676260728-1c00da094a0b?q=80&w=2022&auto=format&fit=crop');">
        </div>
        <div class="relative z-10 text-center px-4">
            <h1 class="text-4xl md:text-5xl font-black mb-4">خدمات آموزشی و کمک‌تحصیلی</h1>
            <p class="text-lg md:text-xl text-teal-100">سرمایه‌گذاری بر روی دانش نخبگان، بهترین تضمین برای آینده ایران است</p>
        </div>
    </header>

    <!-- Content Section -->
    <section class="py-20 container mx-auto px-6 max-w-5xl">
        <div class="bg-white rounded-3xl shadow-xl p-8 md:p-14 leading-loose text-lg text-gray-700">
            <div class="border-r-4 border-teal-500 pr-6 mb-8">
                <h2 class="text-2xl md:text-3xl font-bold text-gray-900 mb-2">توانمندسازی علمی دانش‌آموزان کوشا</h2>
                <p class="text-gray-500 text-sm">ارائه خدمات آموزشی باکیفیت و برابر برای پرورش استعدادهای درخشان</p>
            </div>

            <div class="grid md:grid-cols-2 gap-10 mb-12 items-center">
                <div>
                    <h3 class="text-xl font-bold mb-4 text-primary-900">رسالت آموزشی بنیاد حکمت</h3>
                    <p class="text-gray-600 text-justify mb-4">
                        ما متعهد هستیم که هیچ دانش‌آموز بااستعدادی به دلیل عدم تمکن مالی یا نبود امکانات آموزشی از ادامه تحصیل و دستیابی به قله‌های علمی بازنماند. بنیاد حکمت بستری جامع از معلمان برتر، منابع آزمایشی معتبر و پشتیبانی مستمر تحصیلی را برای دانش‌آموزان بورسیه فراهم آورده است.
                    </p>
                </div>
                <div>
                    <img src="https://images.unsplash.com/photo-1509062522246-3755977927d7?q=80&w=800&auto=format&fit=crop" 
                         alt="دانش‌آموزان بورسیه بنیاد نیکوکاری حکمت"
                         width="600" 
                         height="400"
                         loading="lazy" 
                         decoding="async"
                         class="rounded-2xl shadow-lg w-full h-64 object-cover">
                </div>
            </div>

            <div class="bg-teal-50/70 border border-teal-100 p-8 rounded-2xl">
                <h3 class="text-xl font-bold text-teal-900 mb-4">بسته‌های حمایتی آموزشی بنیاد</h3>
                <ul class="grid md:grid-cols-2 gap-4 text-teal-950 text-sm font-medium">
                    <li class="flex items-center gap-2">
                        <span class="w-2 h-2 bg-teal-500 rounded-full"></span>
                        تهیه و ارسال کتاب‌های کمک‌آموزشی استاندارد برای پایه‌های دهم تا دوازدهم
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="w-2 h-2 bg-teal-500 rounded-full"></span>
                        ثبت‌نام در آزمون‌های آزمایشی کشوری (قلم‌چی، سنجش، گاج)
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="w-2 h-2 bg-teal-500 rounded-full"></span>
                        برگزاری کلاس‌های آنلاین رفع اشکال با رتبه‌های برتر کنکور
                    </li>
                    <li class="flex items-center gap-2">
                        <span class="w-2 h-2 bg-teal-500 rounded-full"></span>
                        اهدای ابزارهای هوشمند تحصیلی و تبلت به دانش‌آموزان مستعد روستایی
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