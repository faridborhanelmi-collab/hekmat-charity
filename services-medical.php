<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>خدمات درمانی | بنیاد نیکوکاری حکمت</title>
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

    <!-- Navbar (Consistent) -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Hero Header -->
    <header class="relative h-[60vh] flex items-center justify-center bg-teal-900 text-white overflow-hidden">
        <div class="absolute inset-0 bg-cover bg-center opacity-30"
            style="background-image: url('https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?q=80&w=2670');">
        </div>
        <div class="relative z-10 text-center">
            <h1 class="text-5xl font-black mb-4">خدمات درمانی</h1>
            <p class="text-xl text-teal-100">سلامت جسم، پیش‌نیاز پرورش روح و ذهن است</p>
        </div>
    </header>

    <!-- Content Section -->
    <section class="py-20 container mx-auto px-6">
        <div class="bg-white rounded-3xl shadow-xl p-10 md:p-16 leading-loose text-lg text-gray-700">
            <div class="border-r-4 border-teal-500 pr-6 mb-8">
                <h2 class="text-3xl font-bold text-gray-900 mb-2">حمایت‌های پزشکی و بهداشتی</h2>
                <p class="text-gray-500 text-sm">لطفاً متن مربوطه را در اینجا جایگذاری کنید</p>
            </div>

            <div class="bg-red-50 border-2 border-dashed border-red-300 p-8 rounded-xl text-center text-red-700 mb-10">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-12 w-12 mx-auto mb-4 opacity-50" fill="none"
                    viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z" />
                </svg>
                <p class="font-bold">محتوای متنی خدمات درمانی از فایل PDF کپی شود.</p>
                <p class="text-sm mt-2 opacity-80">(شامل هزینه‌های درمان، غربالگری سلامت و دندانپزشکی)</p>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-12 text-center">
        <p class="opacity-50">© 1403 بنیاد نیکوکاری حکمت.</p>
    </footer>

</body>

</html>