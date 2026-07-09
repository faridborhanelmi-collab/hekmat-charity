<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>خدمات آموزشی | بنیاد نیکوکاری حکمت</title>
    <link href="https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css" rel="stylesheet"
        type="text/css" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Vazirmatn', 'sans-serif'],
                    },
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            900: '#0c4a6e',
                        },
                        accent: {
                            500: '#f43f5e',
                            600: '#e11d48',
                        }
                    }
                }
            }
        }
    </script>
</head>

<body class="bg-gray-50 font-sans text-gray-800">

    <!-- Navbar -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Hero Header -->
    <header class="relative h-[60vh] flex items-center justify-center bg-primary-900 text-white overflow-hidden">
        <div class="absolute inset-0 bg-cover bg-center opacity-30"
            style="background-image: url('https://images.unsplash.com/photo-1503676260728-1c00da094a0b?q=80&w=2022&auto=format&fit=crop');">
        </div>
        <div class="relative z-10 text-center">
            <h1 class="text-5xl font-black mb-4">خدمات آموزشی</h1>
            <p class="text-xl text-teal-100">سرمایه‌گذاری بر روی دانش، بهترین سود را دارد</p>
        </div>
    </header>

    <!-- Content Section -->
    <section class="py-20 container mx-auto px-6">
        <div class="bg-white rounded-3xl shadow-xl p-10 md:p-16 leading-loose text-lg text-gray-700">
            <div class="border-r-4 border-teal-500 pr-6 mb-8">
                <h2 class="text-3xl font-bold text-gray-900 mb-2">شرح خدمات آموزشی</h2>
                <p class="text-gray-500 text-sm">لطفاً متن مربوطه را در اینجا جایگذاری کنید</p>
            </div>

            <!-- Placeholder Content Area -->
            <div
                class="bg-yellow-50 border-2 border-dashed border-yellow-300 p-8 rounded-xl text-center text-yellow-700 mb-10">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto mb-4 opacity-50" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <p class="font-bold">محتوای متنی خدمات آموزشی از فایل PDF کپی شود.</p>
                <p class="text-sm mt-2 opacity-80">(شامل کلاس‌های تقویتی، مشاوره تحصیلی و تامین منابع درسی)</p>
            </div>

            <!-- Example Static Content (Can be replaced) -->
            <div class="grid md:grid-cols-2 gap-8">
                <img src="https://images.unsplash.com/photo-1509062522246-3755977927d7?q=80&w=2604" alt="دانش‌آموزان"
                    class="rounded-2xl shadow-lg w-full h-64 object-cover">
                <div class="flex flex-col justify-center">
                    <h3 class="text-2xl font-bold mb-4 text-primary-600">هدف ما</h3>
                    <p>ما متعهد هستیم که هیچ دانش‌آموز مستعدی به دلیل مشکلات مالی از تحصیل باز نماند. خدمات ما شامل
                        برگزاری کلاس‌های کنکور، اهدای کتاب‌های کمک‌آموزشی و نظارت مستمر بر پیشرفت تحصیلی است.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-12 text-center">
        <p class="opacity-50">© 1403 بنیاد نیکوکاری حکمت. تمامی حقوق محفوظ است.</p>
    </footer>

</body>

</html>