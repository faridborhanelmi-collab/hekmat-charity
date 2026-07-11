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

    <!-- iOS PWA/Homescreen Setup -->
    <link rel="apple-touch-icon" href="logo.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="بنیاد حکمت">
    <link rel="icon" type="image/png" href="logo.png">
    <link rel="manifest" href="manifest.json">
</head>

<body class="bg-gray-50 font-sans text-gray-800">

    <!-- Navbar -->
    <?php include 'includes/navbar.php'; ?>

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


<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/sw.js');
    });
  }
</script>

</body>

</html>