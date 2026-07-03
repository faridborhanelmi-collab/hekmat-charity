<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>خدمات مشاوره | بنیاد نیکوکاری حکمت</title>
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
    <nav class="fixed w-full z-50 transition-all duration-300 bg-white/10 backdrop-blur-md border-b border-white/20">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <div class="flex items-center gap-6">
                <a href="login.php"
                    class="hidden md:flex items-center gap-2 px-6 py-2 bg-gradient-to-r from-teal-400 to-teal-600 text-white rounded-full font-bold shadow-lg hover:shadow-teal-500/30 transition-all transform hover:-translate-y-1">ورود
                    / ثبت‌نام</a>
                <div class="hidden md:flex gap-8 text-white/90">
                    <a href="index.php" class="hover:text-teal-300 transition-colors font-medium">صفحه اصلی</a>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <div class="text-right">
                    <div class="text-xl font-black text-white tracking-tight">بنیاد نیکوکاری <span
                            class="text-teal-300">حکمت</span></div>
                </div>
                <div
                    class="relative w-12 h-12 flex items-center justify-center bg-gradient-to-br from-teal-400 to-teal-600 rounded-2xl shadow-inner border border-white/20 overflow-hidden">
                    <span class="text-2xl font-black text-white">م</span>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Header -->
    <header class="relative h-[60vh] flex items-center justify-center bg-primary-900 text-white overflow-hidden">
        <div class="absolute inset-0 bg-cover bg-center opacity-30"
            style="background-image: url('https://images.unsplash.com/photo-1529333166437-7750a6dd5a70?q=80&w=2669');">
        </div>
        <div class="relative z-10 text-center">
            <h1 class="text-5xl font-black mb-4">خدمات مشاوره</h1>
            <p class="text-xl text-teal-100">آرامش ذهن، کلید موفقیت در تمام مراحل زندگی است</p>
        </div>
    </header>

    <!-- Content Section -->
    <section class="py-20 container mx-auto px-6">
        <div class="bg-white rounded-3xl shadow-xl p-10 md:p-16 leading-loose text-lg text-gray-700">

            <div class="grid md:grid-cols-2 gap-12 mb-16 items-center">
                <div class="border-r-4 border-teal-500 pr-6">
                    <h2 class="text-3xl font-bold text-gray-900 mb-6">همراهی در لحظات سخت</h2>
                    <p class="text-gray-500 mb-4 text-justify">
                        ما در بنیاد حکمت معتقدیم که حمایت عاطفی و روانی به اندازه حمایت مالی اهمیت دارد. تیم مشاوران
                        خبره ما آماده‌اند تا در کنار دانش‌آموزان و خانواده‌هایشان باشند.
                    </p>
                    <div class="bg-blue-50 border border-blue-200 p-4 rounded-lg text-sm text-blue-800">
                        <span class="font-bold block mb-2">محل درج متن اصلی:</span>
                        لطفاً توضیحات کامل خدمات مشاوره (فردی، گروهی، خانواده) را از فایل PDF کپی کرده و در اینجا قرار
                        دهید.
                    </div>
                </div>

                <!-- Generated Image Display -->
                <div class="relative group">
                    <div
                        class="absolute -inset-1 bg-gradient-to-r from-teal-600 to-blue-600 rounded-2xl blur opacity-25 group-hover:opacity-75 transition duration-1000 group-hover:duration-200">
                    </div>
                    <img src="counseling_session_1768832961834.png" alt="جلسه مشاوره"
                        class="relative rounded-2xl shadow-xl w-full h-auto object-cover ring-4 ring-white">
                </div>
            </div>

            <div class="bg-indigo-50 p-8 rounded-2xl">
                <h3 class="text-xl font-bold text-indigo-900 mb-4">سرفصل‌های خدمات مشاوره</h3>
                <ul class="list-disc list-inside space-y-2 text-indigo-800">
                    <li>مشاوره تحصیلی و برنامه‌ریزی درسی</li>
                    <li>مشاوره روانشناختی و کنترل استرس</li>
                    <li>کارگاه‌های مهارت‌های زندگی</li>
                    <li>(سایر موارد را اضافه کنید...)</li>
                </ul>
            </div>

        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-12 text-center">
        <p class="opacity-50">© 1403 بنیاد نیکوکاری حکمت.</p>
    </footer>

</body>

</html>