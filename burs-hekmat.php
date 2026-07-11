<!DOCTYPE html>
<html lang="fa" dir="rtl" class="scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بورس حکمت: نقشه راه | بنیاد نیکوکاری حکمت</title>
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
                        primary: '#0e7490', // Cyan-700
                        secondary: '#f59e0b', // Amber-500
                    }
                }
            }
        }
    </script>
    <style>
        /* Custom Snake Path Connectors for Desktop */
        @media (min-width: 1024px) {
            .snake-row:nth-child(odd) {
                flex-direction: row;
            }

            .snake-row:nth-child(even) {
                flex-direction: row-reverse;
            }

            /* Horizontal Line Connector */
            .connector-h::after {
                content: '';
                position: absolute;
                top: 50%;
                left: -50%;
                width: 100%;
                height: 4px;
                background: #e5e7eb;
                z-index: -1;
                margin-top: -2px;
            }

            .snake-row:nth-child(even) .connector-h::after {
                left: auto;
                right: -50%;
            }

            /* Curve Connectors */
            .curve-right {
                position: absolute;
                right: -2rem;
                top: 50%;
                width: 4rem;
                height: 100%;
                border-right: 4px solid #e5e7eb;
                border-top: 4px solid #e5e7eb;
                border-top-right-radius: 2rem;
                border-bottom-right-radius: 2rem;
                z-index: -1;
            }

            .curve-left {
                position: absolute;
                left: -2rem;
                top: 50%;
                width: 4rem;
                height: 100%;
                border-left: 4px solid #e5e7eb;
                border-top: 4px solid #e5e7eb;
                border-top-left-radius: 2rem;
                border-bottom-left-radius: 2rem;
                z-index: -1;
            }
        }

        /* Offset for fixed navbar scrolling */
        .step-anchor {
            scroll-margin-top: 15rem;
        }
    </style>

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

    <!-- Header -->
    <header class="pt-32 pb-12 text-center container mx-auto px-6">
        <h1 class="text-4xl md:text-5xl font-black text-gray-900 mb-6">مسیر بورس حکمت</h1>
        <p class="text-xl text-gray-500">نقشه راه ۱۳ مرحله‌ای حمایت از نخبگان<br><span
                class="text-sm text-teal-600 mt-2 block">(برای مشاهده جزئیات، روی هر مرحله کلیک کنید)</span></p>
    </header>

    <!-- Roadmap Container -->
    <div class="container mx-auto px-6 pb-20 max-w-6xl">

        <!-- Row 1: Steps 1-4 (L->R) -->
        <div class="snake-row flex flex-col lg:flex-row gap-8 lg:gap-0 relative mb-8 lg:mb-24">

            <!-- Step 1 -->
            <div class="w-full lg:w-1/4 relative group md:px-4">
                <div class="connector-h hidden lg:block"></div>
                <a href="#step-1"
                    class="block bg-white p-6 rounded-3xl shadow-lg border-2 border-transparent group-hover:border-teal-500 transition-all text-center h-full relative z-10 cursor-pointer transform group-hover:-translate-y-2">
                    <div
                        class="w-16 h-16 mx-auto bg-teal-100 text-teal-600 rounded-full flex items-center justify-center text-3xl mb-4 group-hover:scale-110 transition-transform">
                        🔍</div>
                    <div
                        class="absolute -top-3 -right-3 w-8 h-8 bg-gray-900 text-white rounded-full flex items-center justify-center font-bold text-sm">
                        1</div>
                    <h3 class="font-bold text-lg mb-2">انتخاب مدارس</h3>
                    <p class="text-sm text-gray-500">پایش مدارس مناطق هدف</p>
                </a>
            </div>

            <!-- Step 2 -->
            <div class="w-full lg:w-1/4 relative group md:px-4">
                <div class="connector-h hidden lg:block"></div>
                <a href="#step-2"
                    class="block bg-white p-6 rounded-3xl shadow-lg border-2 border-transparent group-hover:border-teal-500 transition-all text-center h-full relative z-10 cursor-pointer transform group-hover:-translate-y-2">
                    <div
                        class="w-16 h-16 mx-auto bg-teal-100 text-teal-600 rounded-full flex items-center justify-center text-3xl mb-4 group-hover:scale-110 transition-transform">
                        📞</div>
                    <div
                        class="absolute -top-3 -right-3 w-8 h-8 bg-gray-900 text-white rounded-full flex items-center justify-center font-bold text-sm">
                        2</div>
                    <h3 class="font-bold text-lg mb-2">ارتباط با مدیران</h3>
                    <p class="text-sm text-gray-500">جلسه توجیهی با مدیران</p>
                </a>
            </div>

            <!-- Step 3 -->
            <div class="w-full lg:w-1/4 relative group md:px-4">
                <div class="connector-h hidden lg:block"></div>
                <a href="#step-3"
                    class="block bg-white p-6 rounded-3xl shadow-lg border-2 border-transparent group-hover:border-teal-500 transition-all text-center h-full relative z-10 cursor-pointer transform group-hover:-translate-y-2">
                    <div
                        class="w-16 h-16 mx-auto bg-teal-100 text-teal-600 rounded-full flex items-center justify-center text-3xl mb-4 group-hover:scale-110 transition-transform">
                        📝</div>
                    <div
                        class="absolute -top-3 -right-3 w-8 h-8 bg-gray-900 text-white rounded-full flex items-center justify-center font-bold text-sm">
                        3</div>
                    <h3 class="font-bold text-lg mb-2">معرفی دانش‌آموز</h3>
                    <p class="text-sm text-gray-500">دریافت لیست اولیه</p>
                </a>
            </div>

            <!-- Step 4 -->
            <div class="w-full lg:w-1/4 relative group md:px-4">
                <div class="curve-left hidden lg:block"></div>
                <a href="#step-4"
                    class="block bg-white p-6 rounded-3xl shadow-lg border-2 border-transparent group-hover:border-teal-500 transition-all text-center h-full relative z-10 cursor-pointer transform group-hover:-translate-y-2">
                    <div
                        class="w-16 h-16 mx-auto bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-3xl mb-4 group-hover:scale-110 transition-transform">
                        👨‍👩‍👧</div>
                    <div
                        class="absolute -top-3 -right-3 w-8 h-8 bg-gray-900 text-white rounded-full flex items-center justify-center font-bold text-sm">
                        4</div>
                    <h3 class="font-bold text-lg mb-2">ارتباط با خانواده</h3>
                    <p class="text-sm text-gray-500">تکمیل پرونده اولیه</p>
                </a>
            </div>

        </div>

        <!-- Row 2: Steps 5-8 (R->L) -->
        <div class="snake-row flex flex-col lg:flex-row gap-8 lg:gap-0 relative mb-8 lg:mb-24">

            <!-- Step 5 -->
            <div class="w-full lg:w-1/4 relative group md:px-4">
                <div class="connector-h hidden lg:block"></div>
                <a href="#step-5"
                    class="block bg-white p-6 rounded-3xl shadow-lg border-2 border-transparent group-hover:border-blue-500 transition-all text-center h-full relative z-10 cursor-pointer transform group-hover:-translate-y-2">
                    <div
                        class="w-16 h-16 mx-auto bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-3xl mb-4 group-hover:scale-110 transition-transform">
                        🧠</div>
                    <div
                        class="absolute -top-3 -right-3 w-8 h-8 bg-gray-900 text-white rounded-full flex items-center justify-center font-bold text-sm">
                        5</div>
                    <h3 class="font-bold text-lg mb-2">آزمون هوش</h3>
                    <p class="text-sm text-gray-500">سنجش توانمندی ذهنی</p>
                </a>
            </div>

            <!-- Step 6 -->
            <div class="w-full lg:w-1/4 relative group md:px-4">
                <div class="connector-h hidden lg:block"></div>
                <a href="#step-6"
                    class="block bg-white p-6 rounded-3xl shadow-lg border-2 border-transparent group-hover:border-blue-500 transition-all text-center h-full relative z-10 cursor-pointer transform group-hover:-translate-y-2">
                    <div
                        class="w-16 h-16 mx-auto bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-3xl mb-4 group-hover:scale-110 transition-transform">
                        🗣️</div>
                    <div
                        class="absolute -top-3 -right-3 w-8 h-8 bg-gray-900 text-white rounded-full flex items-center justify-center font-bold text-sm">
                        6</div>
                    <h3 class="font-bold text-lg mb-2">مصاحبه</h3>
                    <p class="text-sm text-gray-500">بررسی انگیزه پیشرفت</p>
                </a>
            </div>

            <!-- Step 7 -->
            <div class="w-full lg:w-1/4 relative group md:px-4">
                <div class="connector-h hidden lg:block"></div>
                <a href="#step-7"
                    class="block bg-white p-6 rounded-3xl shadow-lg border-2 border-transparent group-hover:border-blue-500 transition-all text-center h-full relative z-10 cursor-pointer transform group-hover:-translate-y-2">
                    <div
                        class="w-16 h-16 mx-auto bg-blue-100 text-blue-600 rounded-full flex items-center justify-center text-3xl mb-4 group-hover:scale-110 transition-transform">
                        🔎</div>
                    <div
                        class="absolute -top-3 -right-3 w-8 h-8 bg-gray-900 text-white rounded-full flex items-center justify-center font-bold text-sm">
                        7</div>
                    <h3 class="font-bold text-lg mb-2">تحقیقات</h3>
                    <p class="text-sm text-gray-500">پایش محلی وضعیت</p>
                </a>
            </div>

            <!-- Step 8 -->
            <div class="w-full lg:w-1/4 relative group md:px-4">
                <div class="curve-right hidden lg:block"></div>
                <a href="#step-8"
                    class="block bg-white p-6 rounded-3xl shadow-lg border-2 border-transparent group-hover:border-blue-500 transition-all text-center h-full relative z-10 cursor-pointer transform group-hover:-translate-y-2">
                    <div
                        class="w-16 h-16 mx-auto bg-green-100 text-green-600 rounded-full flex items-center justify-center text-3xl mb-4 group-hover:scale-110 transition-transform">
                        🤝</div>
                    <div
                        class="absolute -top-3 -right-3 w-8 h-8 bg-gray-900 text-white rounded-full flex items-center justify-center font-bold text-sm">
                        8</div>
                    <h3 class="font-bold text-lg mb-2">پیمان‌نامه</h3>
                    <p class="text-sm text-gray-500">آغاز رسمی حمایت</p>
                </a>
            </div>

        </div>

        <!-- Row 3: Steps 9-12 (L->R) -->
        <div class="snake-row flex flex-col lg:flex-row gap-8 lg:gap-0 relative mb-8 lg:mb-24">

            <!-- Step 9 -->
            <div class="w-full lg:w-1/4 relative group md:px-4">
                <div class="connector-h hidden lg:block"></div>
                <a href="#step-9"
                    class="block bg-white p-6 rounded-3xl shadow-lg border-2 border-transparent group-hover:border-amber-500 transition-all text-center h-full relative z-10 cursor-pointer transform group-hover:-translate-y-2">
                    <div
                        class="w-16 h-16 mx-auto bg-amber-100 text-amber-600 rounded-full flex items-center justify-center text-3xl mb-4 group-hover:scale-110 transition-transform">
                        💳</div>
                    <div
                        class="absolute -top-3 -right-3 w-8 h-8 bg-gray-900 text-white rounded-full flex items-center justify-center font-bold text-sm">
                        9</div>
                    <h3 class="font-bold text-lg mb-2">بورسیه</h3>
                    <p class="text-sm text-gray-500">پرداخت مستمری ماهانه</p>
                </a>
            </div>

            <!-- Step 10 -->
            <div class="w-full lg:w-1/4 relative group md:px-4">
                <div class="connector-h hidden lg:block"></div>
                <a href="#step-10"
                    class="block bg-white p-6 rounded-3xl shadow-lg border-2 border-transparent group-hover:border-amber-500 transition-all text-center h-full relative z-10 cursor-pointer transform group-hover:-translate-y-2">
                    <div
                        class="w-16 h-16 mx-auto bg-amber-100 text-amber-600 rounded-full flex items-center justify-center text-3xl mb-4 group-hover:scale-110 transition-transform">
                        📚</div>
                    <div
                        class="absolute -top-3 -right-3 w-8 h-8 bg-gray-900 text-white rounded-full flex items-center justify-center font-bold text-sm">
                        10</div>
                    <h3 class="font-bold text-lg mb-2">مشاوره</h3>
                    <p class="text-sm text-gray-500">هدایت تحصیلی مستمر</p>
                </a>
            </div>

            <!-- Step 11 -->
            <div class="w-full lg:w-1/4 relative group md:px-4">
                <div class="connector-h hidden lg:block"></div>
                <a href="#step-11"
                    class="block bg-white p-6 rounded-3xl shadow-lg border-2 border-transparent group-hover:border-amber-500 transition-all text-center h-full relative z-10 cursor-pointer transform group-hover:-translate-y-2">
                    <div
                        class="w-16 h-16 mx-auto bg-amber-100 text-amber-600 rounded-full flex items-center justify-center text-3xl mb-4 group-hover:scale-110 transition-transform">
                        🩺</div>
                    <div
                        class="absolute -top-3 -right-3 w-8 h-8 bg-gray-900 text-white rounded-full flex items-center justify-center font-bold text-sm">
                        11</div>
                    <h3 class="font-bold text-lg mb-2">پشتیبانی</h3>
                    <p class="text-sm text-gray-500">خدمات درمانی و رفاهی</p>
                </a>
            </div>

            <!-- Step 12 -->
            <div class="w-full lg:w-1/4 relative group md:px-4">
                <div class="curve-left hidden lg:block"></div>
                <a href="#step-12"
                    class="block bg-white p-6 rounded-3xl shadow-lg border-2 border-transparent group-hover:border-indigo-500 transition-all text-center h-full relative z-10 cursor-pointer transform group-hover:-translate-y-2">
                    <div
                        class="w-16 h-16 mx-auto bg-indigo-100 text-indigo-600 rounded-full flex items-center justify-center text-3xl mb-4 group-hover:scale-110 transition-transform">
                        🎓</div>
                    <div
                        class="absolute -top-3 -right-3 w-8 h-8 bg-gray-900 text-white rounded-full flex items-center justify-center font-bold text-sm">
                        12</div>
                    <h3 class="font-bold text-lg mb-2">دانشگاه</h3>
                    <p class="text-sm text-gray-500">ورود به مراکز عالی</p>
                </a>
            </div>

        </div>

        <!-- Finale: Step 13 -->
        <div class="flex justify-center relative">
            <div class="w-full lg:w-1/2 relative group">
                <a href="#step-13"
                    class="block bg-gradient-to-r from-gray-900 to-gray-800 text-white p-8 rounded-[2rem] shadow-2xl shadow-teal-500/20 text-center transform hover:scale-105 transition-all cursor-pointer">
                    <div
                        class="w-20 h-20 mx-auto bg-teal-500 text-white rounded-full flex items-center justify-center text-4xl mb-6 shadow-lg animate-pulse">
                        🚀</div>
                    <div
                        class="absolute -top-4 -right-4 w-10 h-10 bg-white text-gray-900 border-4 border-teal-500 rounded-full flex items-center justify-center font-bold text-lg">
                        13</div>
                    <h3 class="font-black text-2xl mb-2">اشتغال و استقلال</h3>
                    <p class="text-gray-400">ورود به بازار کار و خودکفایی کامل</p>
                </a>
            </div>
        </div>

    </div>

    <!-- Detailed Content Section -->
    <section class="bg-white py-24 border-t border-gray-100">
        <div class="container mx-auto px-6 max-w-4xl">
            <h2 class="text-3xl font-black text-center mb-16 text-gray-900">شرح دقیق مراحل</h2>

            <!-- Phase 1 -->
            <div class="mb-20">
                <h3 class="text-2xl font-black text-teal-600 mb-8 flex items-center gap-2">
                    <span class="w-2 h-8 bg-teal-600 rounded-full"></span>
                    فاز اول: کشف و شناسایی (Discovery)
                </h3>

                <div id="step-1"
                    class="step-anchor mb-12 p-8 bg-gray-50 rounded-3xl border border-gray-100 hover:shadow-md transition-shadow">
                    <div class="flex items-start gap-4">
                        <div
                            class="w-10 h-10 bg-teal-100 text-teal-600 rounded-xl flex items-center justify-center font-bold shrink-0 mt-1">
                            1</div>
                        <div>
                            <h4 class="text-xl font-bold text-gray-900 mb-4">پایش و انتخاب مدارس هدف</h4>
                            <p class="text-gray-600 leading-loose text-justify">ما به دنبال استعدادهایی هستیم که در
                                مناطق کم‌برخوردار پنهان مانده‌اند. در اولین قدم، تیم کارشناسی بنیاد با بررسی دقیق
                                شاخص‌های اقتصادی و آموزشی مناطق حاشیه شهر، مدارس هدف را شناسایی می‌کند. اولویت ما مدارسی
                                است که علیرغم محرومیت، دانش‌آموزان مستعد و باانگیزه‌ای دارند.</p>
                        </div>
                    </div>
                </div>

                <div id="step-2"
                    class="step-anchor mb-12 p-8 bg-gray-50 rounded-3xl border border-gray-100 hover:shadow-md transition-shadow">
                    <div class="flex items-start gap-4">
                        <div
                            class="w-10 h-10 bg-teal-100 text-teal-600 rounded-xl flex items-center justify-center font-bold shrink-0 mt-1">
                            2</div>
                        <div>
                            <h4 class="text-xl font-bold text-gray-900 mb-4">ارتباط با مدیران مدارس (جلسات توجیهی)</h4>
                            <p class="text-gray-600 leading-loose text-justify">مدیران مدارس، امین و چشم بینای ما هستند.
                                ما با برگزاری جلسات حضوری با مدیران و مشاوران مدارس منتخب، اهداف و استانداردهای «بورس
                                حکمت» را تشریح می‌کنیم. از آنها می‌خواهیم دانش‌آموزانی را معرفی کنند که هم از نظر هوشی
                                سرآمد باشند و هم از نظر مالی واقعاً نیازمند حمایت.</p>
                        </div>
                    </div>
                </div>

                <div id="step-3"
                    class="step-anchor mb-12 p-8 bg-gray-50 rounded-3xl border border-gray-100 hover:shadow-md transition-shadow">
                    <div class="flex items-start gap-4">
                        <div
                            class="w-10 h-10 bg-teal-100 text-teal-600 rounded-xl flex items-center justify-center font-bold shrink-0 mt-1">
                            3</div>
                        <div>
                            <h4 class="text-xl font-bold text-gray-900 mb-4">معرفی دانش‌آموزان از سوی مدارس</h4>
                            <p class="text-gray-600 leading-loose text-justify">در این مرحله، مدرسه لیستی از دانش‌آموزان
                                واجد شرایط را به بنیاد معرفی می‌کند. این معرفی‌نامه شامل سوابق تحصیلی و تأییدیه اولیه
                                وضعیت معیشتی خانواده است. ما به شناخت کادر مدرسه اعتماد می‌کنیم تا مطمئن شویم کسانی وارد
                                پروسه می‌شوند که واقعاً به این حمایت نیاز دارند.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Phase 2 -->
            <div class="mb-20">
                <h3 class="text-2xl font-black text-blue-600 mb-8 flex items-center gap-2">
                    <span class="w-2 h-8 bg-blue-600 rounded-full"></span>
                    فاز دوم: غربالگری و راستی‌آزمایی (The Filter)
                </h3>

                <div id="step-4"
                    class="step-anchor mb-12 p-8 bg-gray-50 rounded-3xl border border-gray-100 hover:shadow-md transition-shadow">
                    <div class="flex items-start gap-4">
                        <div
                            class="w-10 h-10 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center font-bold shrink-0 mt-1">
                            4</div>
                        <div>
                            <h4 class="text-xl font-bold text-gray-900 mb-4">ارتباط اولیه با خانواده و دریافت مدارک</h4>
                            <p class="text-gray-600 leading-loose text-justify">پس از دریافت لیست، کارشناسان ما با
                                خانواده دانش‌آموز تماس می‌گیرند تا ضمن توضیح فرآیند، مدارک لازم (هویتی، تحصیلی و مستندات
                                معیشتی) را دریافت کنند. در این مرحله، حفظ کرامت خانواده و دانش‌آموز خط قرمز و اصل اساسی
                                ماست.</p>
                        </div>
                    </div>
                </div>

                <div id="step-5"
                    class="step-anchor mb-12 p-8 bg-gray-50 rounded-3xl border border-gray-100 hover:shadow-md transition-shadow">
                    <div class="flex items-start gap-4">
                        <div
                            class="w-10 h-10 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center font-bold shrink-0 mt-1">
                            5</div>
                        <div>
                            <h4 class="text-xl font-bold text-gray-900 mb-4">سنجش جامع هوش و استعدادهای چندگانه</h4>
                            <p class="text-gray-600 leading-loose text-justify">ما به هوش ریاضی یا نمره مدرسه اکتفا
                                نمی‌کنیم. دانش‌آموزان در این مرحله تحت ارزیابی‌های دقیق و همه‌جانبه‌ای قرار می‌گیرند که
                                شامل تست‌های انگیزشی، هوش چندگانه، هوش اجتماعی و هوش هیجانی (EQ) است. هدف ما شناخت عمیق
                                و ۳۶۰ درجه از پتانسیل‌های منحصربه‌فرد هر دانش‌آموز است تا مسیر رشد، دقیقاً متناسب با
                                توانمندی‌های واقعی او ترسیم شود.</p>
                        </div>
                    </div>
                </div>

                <div id="step-6"
                    class="step-anchor mb-12 p-8 bg-gray-50 rounded-3xl border border-gray-100 hover:shadow-md transition-shadow">
                    <div class="flex items-start gap-4">
                        <div
                            class="w-10 h-10 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center font-bold shrink-0 mt-1">
                            6</div>
                        <div>
                            <h4 class="text-xl font-bold text-gray-900 mb-4">مصاحبه انگیزشی</h4>
                            <p class="text-gray-600 leading-loose text-justify">هوش به تنهایی کافی نیست؛ «اشتیاق» موتور
                                محرک موفقیت است. مشاوران باتجربه بنیاد در یک جلسه صمیمانه با دانش‌آموز گفتگو می‌کنند تا
                                میزان انگیزه، رویاها و جدیت او برای پیشرفت را بسنجند. ما به دنبال کسانی هستیم که
                                می‌خواهند آینده خود را تغییر دهند.</p>
                        </div>
                    </div>
                </div>

                <div id="step-7"
                    class="step-anchor mb-12 p-8 bg-gray-50 rounded-3xl border border-gray-100 hover:shadow-md transition-shadow">
                    <div class="flex items-start gap-4">
                        <div
                            class="w-10 h-10 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center font-bold shrink-0 mt-1">
                            7</div>
                        <div>
                            <h4 class="text-xl font-bold text-gray-900 mb-4">تحقیقات میدانی و پایش (Vetting)</h4>
                            <p class="text-gray-600 leading-loose text-justify">برای اطمینان از اینکه کمک‌ها دقیقاً به
                                دست نیازمندان واقعی می‌رسد، تیم تحقیقات بنیاد بررسی‌های محلی محرمانه‌ای انجام می‌دهد.
                                این مرحله حساس‌ترین بخش کار است تا شفافیت مالی و امانتداری ما نزد خیرین حفظ شود و عدالت
                                در توزیع منابع رعایت گردد.</p>
                        </div>
                    </div>
                </div>

                <div id="step-8"
                    class="step-anchor mb-12 p-8 bg-gray-50 rounded-3xl border border-gray-100 hover:shadow-md transition-shadow">
                    <div class="flex items-start gap-4">
                        <div
                            class="w-10 h-10 bg-blue-100 text-blue-600 rounded-xl flex items-center justify-center font-bold shrink-0 mt-1">
                            8</div>
                        <div>
                            <h4 class="text-xl font-bold text-gray-900 mb-4">سوگندنامه حکمت (The Oath)</h4>
                            <p class="text-gray-600 leading-loose text-justify">پس از قبولی در تمام مراحل، دانش‌آموز و
                                خانواده‌اش طی مراسمی دعوت می‌شوند تا «سوگندنامه بنیاد» را امضا کنند. این یک تعهد اخلاقی
                                دوطرفه است: بنیاد متعهد به حمایت همه‌جانبه می‌شود و دانش‌آموز متعهد به تلاش بی‌وقفه برای
                                تحصیل و اخلاق‌مداری.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Phase 3 -->
            <div class="mb-20">
                <h3 class="text-2xl font-black text-amber-500 mb-8 flex items-center gap-2">
                    <span class="w-2 h-8 bg-amber-500 rounded-full"></span>
                    فاز سوم: پشتیبانی و سوخت‌رسانی (Fueling)
                </h3>

                <div id="step-9"
                    class="step-anchor mb-12 p-8 bg-gray-50 rounded-3xl border border-gray-100 hover:shadow-md transition-shadow">
                    <div class="flex items-start gap-4">
                        <div
                            class="w-10 h-10 bg-amber-100 text-amber-600 rounded-xl flex items-center justify-center font-bold shrink-0 mt-1">
                            9</div>
                        <div>
                            <h4 class="text-xl font-bold text-gray-900 mb-4">آغاز پرداخت بورسیه</h4>
                            <p class="text-gray-600 leading-loose text-justify">از این لحظه، دانش‌آموز رسماً عضوی از
                                خانواده حکمت است. پرداخت کمک‌هزینه تحصیلی ماهانه به حساب دانش‌آموز آغاز می‌شود. این مبلغ
                                کمک می‌کند تا دغدغه‌های اولیه مالی برطرف شده و تمرکز دانش‌آموز صرفاً بر روی یادگیری
                                باشد.</p>
                        </div>
                    </div>
                </div>

                <div id="step-10"
                    class="step-anchor mb-12 p-8 bg-gray-50 rounded-3xl border border-gray-100 hover:shadow-md transition-shadow">
                    <div class="flex items-start gap-4">
                        <div
                            class="w-10 h-10 bg-amber-100 text-amber-600 rounded-xl flex items-center justify-center font-bold shrink-0 mt-1">
                            10</div>
                        <div>
                            <h4 class="text-xl font-bold text-gray-900 mb-4">پایش تحصیلی و مشاوره مستمر</h4>
                            <p class="text-gray-600 leading-loose text-justify">حمایت ما رها نمی‌شود. هر ۶ ماه یکبار،
                                وضعیت نمرات و پیشرفت تحصیلی دانش‌آموز توسط مشاوران آموزشی پایش می‌شود. اگر افتی مشاهده
                                شود، علت‌یابی شده و راهکارهای تقویتی ارائه می‌گردد تا دانش‌آموز در مسیر موفقیت باقی
                                بماند.</p>
                        </div>
                    </div>
                </div>

                <div id="step-11"
                    class="step-anchor mb-12 p-8 bg-gray-50 rounded-3xl border border-gray-100 hover:shadow-md transition-shadow">
                    <div class="flex items-start gap-4">
                        <div
                            class="w-10 h-10 bg-amber-100 text-amber-600 rounded-xl flex items-center justify-center font-bold shrink-0 mt-1">
                            11</div>
                        <div>
                            <h4 class="text-xl font-bold text-gray-900 mb-4">خدمات جامع (کتب، درمان، رفاه)</h4>
                            <p class="text-gray-600 leading-loose text-justify">ما باور داریم موفقیت نیازمند ابزار است.
                                بنیاد علاوه بر پول نقد، خدمات غیرنقدی گسترده‌ای ارائه می‌دهد: تأمین کتاب‌های کمک‌آموزشی،
                                کلاس‌های زبان، خدمات روانشناسی و حتی حمایت‌های درمانی و پزشکی تا هیچ مانعی سد راه
                                شکوفایی استعداد نشود.</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Phase 4 -->
            <div class="mb-20">
                <h3 class="text-2xl font-black text-indigo-600 mb-8 flex items-center gap-2">
                    <span class="w-2 h-8 bg-indigo-600 rounded-full"></span>
                    فاز چهارم: پرواز و ثمردهی (Liftoff)
                </h3>

                <div id="step-12"
                    class="step-anchor mb-12 p-8 bg-gray-50 rounded-3xl border border-gray-100 hover:shadow-md transition-shadow">
                    <div class="flex items-start gap-4">
                        <div
                            class="w-10 h-10 bg-indigo-100 text-indigo-600 rounded-xl flex items-center justify-center font-bold shrink-0 mt-1">
                            12</div>
                        <div>
                            <h4 class="text-xl font-bold text-gray-900 mb-4">حمایت دانشگاهی (تا یک سال)</h4>
                            <p class="text-gray-600 leading-loose text-justify">قبولی در دانشگاه پایان راه نیست. ما
                                دانش‌آموزانمان را تا یک سال پس از ورود به دانشگاه (ترم‌های اول که پرفشارترین زمان است)
                                حمایت می‌کنیم. این پشتیبانی شامل مشاوره برای تطبیق با محیط جدید و ادامه کمک‌هزینه‌هاست.
                            </p>
                        </div>
                    </div>
                </div>

                <div id="step-13"
                    class="step-anchor mb-12 p-8 bg-gray-50 rounded-3xl border border-gray-100 hover:shadow-md transition-shadow">
                    <div class="flex items-start gap-4">
                        <div
                            class="w-10 h-10 bg-indigo-100 text-indigo-600 rounded-xl flex items-center justify-center font-bold shrink-0 mt-1">
                            13</div>
                        <div>
                            <h4 class="text-xl font-bold text-gray-900 mb-4">مشاوره شغلی و بازار کار</h4>
                            <p class="text-gray-600 leading-loose text-justify">هدف نهایی ما «استقلال» است. در گام آخر،
                                با ارائه مشاوره‌های شغلی و معرفی فرصت‌های کارآموزی، به دانشجویان کمک می‌کنیم تا وارد
                                بازار کار شوند. روزی که آنها دستشان در جیب خودشان برود و بتوانند دستگیر دیگران باشند،
                                مأموریت ما به سرانجام رسیده است.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center mt-20">
                <a href="index.php#donate"
                    class="inline-block px-12 py-5 bg-teal-600 text-white text-xl font-black rounded-full shadow-2xl hover:bg-teal-700 hover:scale-105 transition-all">
                    به این چرخه نیکی بپیوندید ❤️
                </a>
            </div>

        </div>
    </section>

</body>

</html>