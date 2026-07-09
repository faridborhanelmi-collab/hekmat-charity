<?php 
session_start();
require_once 'includes/db.php';

// Fetch sums for the services section
$bursary_sum = $pdo->query('SELECT SUM(amount) FROM expenses WHERE category_id = 3')->fetchColumn() ?: 0;
$medical_sum = $pdo->query('SELECT SUM(amount) FROM expenses WHERE category_id = 7')->fetchColumn() ?: 0;
$edu_sum = $pdo->query('SELECT SUM(amount) FROM expenses WHERE category_id = 6')->fetchColumn() ?: 0;
$consulting_sum = $pdo->query("SELECT SUM(amount) FROM expenses WHERE description LIKE '%مشاوره%'")->fetchColumn() ?: 0;
?>
<html lang="fa" dir="rtl" class="scroll-smooth">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>بنیاد نیکوکاری حکمت | حامی نخبگان مستعد</title>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@100;400;700;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Vazirmatn', 'sans-serif'] },
                    colors: { primary: { 900: '#00141e', 800: '#115e59', 600: '#14b8a6' }, accent: { 500: '#fb7185' } },
                    keyframes: { 'spin-y': { '0%, 80%': { transform: 'rotateY(0deg)' }, '100%': { transform: 'rotateY(360deg)' } } },
                    animation: { 'spin-y': 'spin-y 7s ease-in-out infinite' }
                }
            }
        }
    </script>
    <style>
        .glass-stats {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .perspective-1000 {
            perspective: 1000px;
        }

        .fixed-bg {
            background-attachment: fixed;
        }

        @keyframes shimmer {
            0% {
                transform: translateX(-150%) skewX(-15deg);
            }

            50%,
            100% {
                transform: translateX(150%) skewX(-15deg);
            }
        }

        .animate-shimmer {
            animation: shimmer 2.5s infinite;
        }
    </style>
</head>

<body class="bg-[#fafafa] text-gray-800 font-sans antialiased">

    <?php include 'includes/navbar.php'; ?>

    <header class="relative h-screen w-full flex flex-col items-center justify-center overflow-hidden pt-20 pb-48">
        <div class="absolute inset-0 bg-cover bg-center fixed-bg"
            style="background-image: url('https://images.unsplash.com/photo-1488521787991-ed7bbaae773c?q=80&w=2670');">
        </div>
        <div class="absolute inset-0 bg-gradient-to-b from-primary-900/90 via-primary-900/60 to-primary-900/95"></div>
        <div class="container mx-auto px-4 relative z-10 text-center text-white">

            <div class="mb-12 flex justify-center perspective-1000 group">
                <div class="relative transform transition-all duration-500 hover:scale-110 drop-shadow-2xl">
                    <!-- Shimmer Effect using Mask -->
                    <div class="absolute inset-0 z-10 w-full h-full pointer-events-none"
                        style="-webkit-mask-image: url('logo.png'); -webkit-mask-size: contain; -webkit-mask-repeat: no-repeat; -webkit-mask-position: center; mask-image: url('logo.png'); mask-size: contain; mask-repeat: no-repeat; mask-position: center;">
                        <div
                            class="w-[200%] h-full absolute top-0 left-[-100%] bg-gradient-to-r from-transparent via-white/40 to-transparent animate-shimmer">
                        </div>
                    </div>
                    <img src="logo.png" alt="لوگو بنیاد نیکوکاری حکمت" class="h-64 md:h-96 w-auto object-contain">
                </div>
            </div>
            <h1 class="text-5xl md:text-7xl font-black mb-6 leading-tight drop-shadow-2xl">بنیاد نیکوکاری <span
                    class="text-teal-300">حکمت</span></h1>
            <p class="text-lg md:text-xl font-light text-gray-100 max-w-2xl mx-auto mb-10 leading-loose">حامی نخبگان
                مستعد و توانمند؛ جایی که مهربانی شما به دانش و آینده تبدیل میشود.</p>
            <div class="flex justify-center gap-4 mb-20">
                <a href="#donate"
                    class="px-10 py-4 bg-accent-500 hover:bg-accent-600 text-white rounded-2xl font-black shadow-2xl transition-all border-b-4 border-accent-700 active:border-b-0 active:translate-y-1">حمایت
                    میکنم</a>
                <a href="about.php"
                    class="px-8 py-4 bg-white/10 hover:bg-white/20 backdrop-blur-md border border-white/30 text-white rounded-2xl font-black transition-all flex items-center gap-3 group">
                    داستان ما
                </a>
            </div>
        </div>
        <div class="absolute bottom-10 w-full max-w-4xl z-20">
            <div
                class="glass-stats rounded-3xl p-6 flex justify-around items-center text-white mx-auto border border-white/10">
                <div class="text-center">
                    <div class="text-3xl font-black">+۸۰</div>
                    <div class="text-xs opacity-80">کودک تحت پوشش</div>
                </div>
                <div class="w-px h-10 bg-white/20"></div>
                <div class="text-center">
                    <div class="text-3xl font-black">۱۰۰٪</div>
                    <div class="text-xs opacity-80">شفافیت مالی</div>
                </div>
                <div class="w-px h-10 bg-white/20"></div>
                <div class="text-center">
                    <div class="text-3xl font-black">۵ سال</div>
                    <div class="text-xs opacity-80">تجربه و اعتماد</div>
                </div>
            </div>
        </div>
    </header>

    <!-- Campaign Highlight Banner -->
    <section class="relative bg-gradient-to-br from-teal-900 to-primary-900 overflow-hidden py-24 lg:py-32">
        <div class="absolute inset-0 bg-teal-500/10 mix-blend-overlay"></div>
        <div class="container mx-auto px-6 lg:px-8 relative z-10">
            <div class="grid lg:grid-cols-2 gap-12 items-center">
                <div class="text-right">
                    <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-teal-500/20 border border-teal-500/30 text-teal-300 text-xs font-bold mb-6">
                        <span class="w-2 h-2 rounded-full bg-teal-400 animate-pulse"></span>
                        پویش جدید بنیاد حکمت
                    </span>
                    <h2 class="text-4xl md:text-5xl font-black text-white mb-6 leading-tight">
                        هر حکمتی، <br />
                        <span class="text-teal-400">یک حکمت یار</span>
                    </h2>
                    <p class="text-gray-200 text-xl mb-4 leading-relaxed font-light">
                        با پذیرش هزینه‌های بورس هر دانش‌آموز با مبلغی ماهیانه کمتر از آنچه می‌اندیشید، فرزندی مستعد را در خانواده خود پذیرا باشید و زیر چتر مهر خود، همراه رشد و بالندگی‌اش شوید.
                    </p>
                    <p class="text-teal-100 text-lg mb-10 leading-relaxed">
                        بنیاد حکمت در طول سال تحصیلی با گزارشات مستمر، شما را در جریان پیشرفت درسی فرزند تازه‌تان قرار می‌دهد.
                    </p>
                    <div class="flex flex-wrap gap-4">
                        <a href="campaign.php" class="bg-teal-500 hover:bg-teal-400 text-white px-8 py-4 rounded-xl font-black shadow-lg shadow-teal-500/30 transition-all transform hover:-translate-y-1">
                            مشاهده و پیوستن به پویش
                        </a>
                        <a href="campaign.php#how-it-works" class="bg-white/10 hover:bg-white/20 text-white px-8 py-4 rounded-xl font-bold border border-white/20 transition-all">
                            اطلاعات بیشتر
                        </a>
                    </div>
                </div>
                <div class="hidden lg:block relative">
                    <div class="absolute inset-0 bg-teal-500/30 blur-3xl rounded-full"></div>
                    <img src="assets/images/campaign_hero.jpg?v=3" alt="کمپین هر حکمتی یک دانش‌آموز" class="relative z-10 w-full h-[450px] object-cover rounded-3xl shadow-2xl border border-white/20 transform transition-transform duration-500 hover:scale-105">
                </div>
            </div>
        </div>
    </section>

    <section id="about" class="py-24 bg-white text-right">
        <div class="container mx-auto px-6">
            <h2 class="text-4xl font-black text-center text-primary-900 mb-20">خدمات بنیاد حکمت</h2>
            <div class="grid md:grid-cols-4 gap-6">
                <a href="services-educational.php"
                    class="p-8 bg-gray-50 rounded-3xl hover:shadow-lg transition-all border-t-4 border-accent-500 group hover:-translate-y-1 block">
                    <div class="text-3xl mb-4 group-hover:scale-110 transition-transform">📚</div>
                    <h3 class="font-bold mb-2 text-gray-800 group-hover:text-primary-600">آموزشی</h3>
                    <p class="text-sm text-gray-500">تهیه کتب، ثبت‌نام کلاس زبان و فضای مطالعه اختصاصی.</p>
                    <?php if ($edu_sum > 0): ?>
                        <div class="mt-4 text-xs font-bold text-accent-600 bg-accent-50 inline-block px-2 py-1 rounded">مجموع پرداختی: <?php echo number_format($edu_sum); ?> تومان</div>
                    <?php endif; ?>
                </a>
                <a href="burs-hekmat.php"
                    class="p-8 bg-gray-50 rounded-3xl hover:shadow-lg transition-all border-t-4 border-accent-500 hover:-translate-y-1 block">
                    <div class="text-3xl mb-4 hover:animate-pulse">💰</div>
                    <h3 class="font-bold mb-2 text-gray-800">بورس حکمت</h3>
                    <p class="text-sm text-gray-500">پرداخت کمک‌هزینه ماهانه و حمایت از نخبگان المپیادی.</p>
                    <?php if ($bursary_sum > 0): ?>
                        <div class="mt-4 text-xs font-bold text-accent-600 bg-accent-50 inline-block px-2 py-1 rounded">مجموع پرداختی: <?php echo number_format($bursary_sum); ?> تومان</div>
                    <?php endif; ?>
                </a>
                <a href="services-counseling.php"
                    class="p-8 bg-gray-50 rounded-3xl hover:shadow-lg transition-all border-t-4 border-accent-500 group hover:-translate-y-1 block">
                    <div class="text-3xl mb-4 group-hover:scale-110 transition-transform">🧠</div>
                    <h3 class="font-bold mb-2 text-gray-800 group-hover:text-blue-600">مشاوره</h3>
                    <p class="text-sm text-gray-500">خدمات روانشناسی و مشاوره تحصیلی تخصصی.</p>
                    <?php if ($consulting_sum > 0): ?>
                        <div class="mt-4 text-xs font-bold text-accent-600 bg-accent-50 inline-block px-2 py-1 rounded">مجموع پرداختی: <?php echo number_format($consulting_sum); ?> تومان</div>
                    <?php endif; ?>
                </a>
                <a href="services-medical.php"
                    class="p-8 bg-gray-50 rounded-3xl hover:shadow-lg transition-all border-t-4 border-accent-500 group hover:-translate-y-1 block">
                    <div class="text-3xl mb-4 group-hover:scale-110 transition-transform">🩺</div>
                    <h3 class="font-bold mb-2 text-gray-800 group-hover:text-red-500">درمانی</h3>
                    <p class="text-sm text-gray-500">پوشش کامل هزینه‌های پزشکی دانش‌آموزان تحت پوشش.</p>
                    <?php if ($medical_sum > 0): ?>
                        <div class="mt-4 text-xs font-bold text-accent-600 bg-accent-50 inline-block px-2 py-1 rounded">مجموع پرداختی: <?php echo number_format($medical_sum); ?> تومان</div>
                    <?php endif; ?>
                </a>
            </div>
        </div>
    </section>

    <section id="burs" class="py-24 bg-gray-50">
        <div class="container mx-auto px-6">
            <div class="bg-white rounded-[3rem] p-12 shadow-xl border border-gray-100">
                <div class="flex flex-col md:flex-row items-center gap-12">
                    <div class="md:w-1/2">
                        <span
                            class="bg-teal-100 text-teal-700 px-4 py-1 rounded-full text-xs font-bold mb-4 inline-block">فرآیند
                            اختصاصی</span>
                        <h2 class="text-4xl font-black text-primary-900 mb-6 leading-tight">بورس حکمت چیست؟</h2>
                        <p class="text-gray-600 leading-loose mb-8 text-lg">بورس حکمت یک مسیر هوشمندانه ۱۳ مرحله‌ای است
                            که دانش‌آموز را از شناسایی در مدارس مناطق محروم تا رسیدن به صندلی بهترین دانشگاه‌ها و ورود
                            به
                            بازار کار همراهی می‌کند.</p>
                        <ul class="space-y-4 text-gray-700">
                            <li class="flex items-center gap-3">
                                <div
                                    class="w-6 h-6 bg-accent-500 rounded-full flex items-center justify-center text-white text-xs">
                                    ✓</div> شناسایی نخبگان در پایش‌های منطقه‌ای
                            </li>
                            <li class="flex items-center gap-3">
                                <div
                                    class="w-6 h-6 bg-accent-500 rounded-full flex items-center justify-center text-white text-xs">
                                    ✓</div> اختصاص مربی و راهبر تحصیلی
                            </li>
                            <li class="flex items-center gap-3">
                                <div
                                    class="w-6 h-6 bg-accent-500 rounded-full flex items-center justify-center text-white text-xs">
                                    ✓</div> حمایت تا اشتغال کامل و پایدار
                            </li>
                        </ul>
                    </div>
                    <div class="md:w-1/2 flex justify-center items-center">
                        <div class="mt-8">
                            <a href="burs-hekmat.php"
                                class="inline-flex items-center gap-2 px-8 py-3 bg-teal-600 text-white rounded-xl hover:bg-teal-700 transition-all shadow-lg font-bold">
                                <span>مشاهده فرآیند بورس حکمت</span>
                                <span>←</span>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
    </section>

    <section id="donate" class="py-24 bg-primary-900 text-white overflow-hidden relative">
        <div class="container mx-auto px-6 relative z-10 flex flex-col lg:flex-row items-center gap-16">
            <div class="lg:w-1/2">
                <h2 class="text-4xl font-black mb-8 leading-tight">سهم شما در ساختن فردا</h2>
                <p class="text-teal-100 text-lg mb-10 leading-loose">هر کمک شما، آجری برای ساختن رویاهای یک کودک مستعد
                    است. شماره حساب زیر جهت واریز کمکهای نقدی اختصاص یافته است.</p>
                <div class="space-y-4">
                    <div class="flex items-center gap-4 bg-white/5 p-4 rounded-2xl border border-white/10">
                        <div class="w-10 h-10 bg-accent-500 rounded-full flex items-center justify-center font-bold">۱
                        </div><span>حمایت از نخبگان المپیادی</span>
                    </div>
                    <div class="flex items-center gap-4 bg-white/5 p-4 rounded-2xl border border-white/10">
                        <div class="w-10 h-10 bg-accent-500 rounded-full flex items-center justify-center font-bold">۲
                        </div><span>تامین تجهیزات دیجیتال آموزشی</span>
                    </div>
                </div>
            </div>
            <div class="lg:w-1/2 perspective-1000">
                <div class="w-full max-w-md transform hover:rotate-2 transition-all duration-500 hover:scale-105">
                    <img src="cart.png" alt="شماره کارت بنیاد نیکوکاری حکمت"
                        class="w-full h-auto rounded-3xl shadow-2xl border border-white/10">
                </div>
            </div>
        </div>
    </section>

    <section id="transparency" class="py-24 bg-[#fafafa]">
        <div class="container mx-auto px-6">
            <div class="text-center mb-16">
                <h2 class="text-3xl font-black text-primary-900 mb-6">شفافیت و اسناد قانونی</h2>
                <p class="text-gray-500 max-w-2xl mx-auto leading-loose">
                    اعتماد شما سرمایه ماست. تمام فعالیت‌های بنیاد حکمت با مجوز رسمی و تحت نظارت مراجع قانونی انجام
                    می‌شود.
                </p>
            </div>

            <div class="grid md:grid-cols-4 gap-6">
                <!-- License -->
                <div class="group relative cursor-pointer" onclick="window.open('doc_license.png', '_blank')">
                    <div class="aspect-[3/4] rounded-2xl overflow-hidden shadow-lg border border-gray-100 relative">
                        <div
                            class="absolute inset-0 bg-primary-900/0 group-hover:bg-primary-900/40 transition-all duration-300 z-10 flex items-center justify-center">
                            <span
                                class="opacity-0 group-hover:opacity-100 text-white font-bold bg-black/50 px-4 py-2 rounded-full backdrop-blur-md transition-all transform scale-90 group-hover:scale-100">مشاهده
                                سند</span>
                        </div>
                        <img src="doc_license.png" alt="پروانه فعالیت"
                            class="w-full h-full object-cover transform group-hover:scale-110 transition-transform duration-700">
                    </div>
                    <h3 class="text-center font-bold mt-4 text-gray-700">پروانه فعالیت</h3>
                </div>

                <!-- Gazette -->
                <div class="group relative cursor-pointer" onclick="window.open('doc_gazette.png', '_blank')">
                    <div class="aspect-[3/4] rounded-2xl overflow-hidden shadow-lg border border-gray-100 relative">
                        <div
                            class="absolute inset-0 bg-primary-900/0 group-hover:bg-primary-900/40 transition-all duration-300 z-10 flex items-center justify-center">
                            <span
                                class="opacity-0 group-hover:opacity-100 text-white font-bold bg-black/50 px-4 py-2 rounded-full backdrop-blur-md transition-all transform scale-90 group-hover:scale-100">مشاهده
                                سند</span>
                        </div>
                        <img src="doc_gazette.png" alt="روزنامه رسمی"
                            class="w-full h-full object-cover transform group-hover:scale-110 transition-transform duration-700">
                    </div>
                    <h3 class="text-center font-bold mt-4 text-gray-700">روزنامه رسمی (تاسیس)</h3>
                </div>

                <!-- Registration -->
                <div class="group relative cursor-pointer" onclick="window.open('doc_registration.png', '_blank')">
                    <div class="aspect-[3/4] rounded-2xl overflow-hidden shadow-lg border border-gray-100 relative">
                        <div
                            class="absolute inset-0 bg-primary-900/0 group-hover:bg-primary-900/40 transition-all duration-300 z-10 flex items-center justify-center">
                            <span
                                class="opacity-0 group-hover:opacity-100 text-white font-bold bg-black/50 px-4 py-2 rounded-full backdrop-blur-md transition-all transform scale-90 group-hover:scale-100">مشاهده
                                سند</span>
                        </div>
                        <img src="doc_registration.png" alt="آگهی ثبت"
                            class="w-full h-full object-cover transform group-hover:scale-110 transition-transform duration-700">
                    </div>
                    <h3 class="text-center font-bold mt-4 text-gray-700">آگهی ثبت تغییرات</h3>
                </div>

                <!-- Letter -->
                <div class="group relative cursor-pointer" onclick="window.open('doc_letter.png', '_blank')">
                    <div class="aspect-[3/4] rounded-2xl overflow-hidden shadow-lg border border-gray-100 relative">
                        <div
                            class="absolute inset-0 bg-primary-900/0 group-hover:bg-primary-900/40 transition-all duration-300 z-10 flex items-center justify-center">
                            <span
                                class="opacity-0 group-hover:opacity-100 text-white font-bold bg-black/50 px-4 py-2 rounded-full backdrop-blur-md transition-all transform scale-90 group-hover:scale-100">مشاهده
                                سند</span>
                        </div>
                        <img src="doc_letter.png" alt="نامه رسمی"
                            class="w-full h-full object-cover transform group-hover:scale-110 transition-transform duration-700">
                    </div>
                    <h3 class="text-center font-bold mt-4 text-gray-700">تاییدیه استانداری</h3>
                </div>
            </div>
        </div>
    </section>

    <!-- Organizational Chart Section -->
    <section id="org-chart" class="py-24 bg-white relative overflow-hidden">
        <div
            class="absolute inset-0 bg-[radial-gradient(#e5e7eb_1px,transparent_1px)] [background-size:16px_16px] opacity-30">
        </div>
        <div class="container mx-auto px-6 relative z-10">
            <h2
                class="text-4xl font-black text-center text-primary-900 mb-20 relative inline-block left-1/2 -translate-x-1/2">
                ساختار سازمانی بنیاد
                <div class="absolute -bottom-4 left-0 w-full h-2 bg-accent-500/20 rounded-full"></div>
            </h2>

            <div class="flex flex-col items-center gap-12 max-w-5xl mx-auto">

                <!-- Level 1: Board of Trustees -->
                <div class="flex justify-center">
                    <div onclick="showOrgModal('trustees')"
                        class="org-node bg-gradient-to-br from-[#0c4a6e] to-[#075985]">
                        <span class="text-white font-black text-xl">هیئت امنا</span>
                        <div class="node-icon">🏛️</div>
                    </div>
                </div>

                <!-- Level 2: Inspectors & Board of Directors -->
                <div class="flex flex-wrap justify-center gap-16 md:gap-32 relative">
                    <!-- Connector Lines would go here but CSS pseudo-elements are used on nodes -->

                    <div onclick="showOrgModal('directors')"
                        class="org-node bg-gradient-to-br from-[#0e7490] to-[#155e75]">
                        <span class="text-white font-black text-lg">هیئت مدیره</span>
                        <div class="node-icon">👥</div>
                    </div>

                    <div onclick="showOrgModal('inspectors')"
                        class="org-node bg-gradient-to-br from-[#65a30d] to-[#4d7c0f]">
                        <span class="text-white font-black text-lg">بازرسین</span>
                        <div class="node-icon">🔍</div>
                    </div>
                </div>

                <!-- Level 3: CEO & Advisors -->
                <div class="flex flex-wrap justify-center gap-16 md:gap-32">
                    <div onclick="showOrgModal('ceo')" class="org-node bg-gradient-to-br from-[#0891b2] to-[#06b6d4]">
                        <span class="text-white font-black text-lg">مدیر عامل</span>
                        <div class="node-icon">👔</div>
                    </div>
                    <div onclick="showOrgModal('advisors')"
                        class="org-node bg-gradient-to-br from-[#84cc16] to-[#65a30d]">
                        <span class="text-white font-black text-lg">مشاورین</span>
                        <div class="node-icon">💡</div>
                    </div>
                </div>

                <!-- Level 4: Deputies -->
                <div class="flex flex-wrap justify-center gap-6 mt-8">
                    <div onclick="showOrgModal('deputy_education')"
                        class="org-node-sm bg-gradient-to-br from-[#84cc16] to-[#65a30d]">
                        <span class="text-white font-bold">معاونت<br>آموزشی</span>
                    </div>
                    <div onclick="showOrgModal('deputy_executive')"
                        class="org-node-sm bg-gradient-to-br from-[#0891b2] to-[#06b6d4]">
                        <span class="text-white font-bold">معاونت<br>اجرایی</span>
                    </div>
                    <div onclick="showOrgModal('deputy_finance')"
                        class="org-node-sm bg-gradient-to-br from-[#0e7490] to-[#155e75]">
                        <span class="text-white font-bold">معاونت<br>مالی</span>
                    </div>
                    <div onclick="showOrgModal('deputy_participation')"
                        class="org-node-sm bg-gradient-to-br from-[#0c4a6e] to-[#075985]">
                        <span class="text-white font-bold">معاونت<br>مشارکت‌ها</span>
                    </div>
                    <div onclick="showOrgModal('deputy_ads')"
                        class="org-node-sm bg-gradient-to-br from-[#2dd4bf] to-[#0d9488]">
                        <span class="text-white font-bold">معاونت<br>تبلیغات</span>
                    </div>
                </div>

            </div>
        </div>

        <style>
            .org-node {
                width: 160px;
                height: 160px;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                border-radius: 5px 60px 5px 60px;
                box-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.3);
                cursor: pointer;
                transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                position: relative;
                z-index: 10;
            }

            .org-node:hover {
                transform: scale(1.1) translateY(-10px);
                box-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.4);
                z-index: 20;
                border-radius: 60px 5px 60px 5px;
            }

            .org-node-sm {
                width: 110px;
                height: 110px;
                display: flex;
                align-items: center;
                justify-content: center;
                text-align: center;
                border-radius: 5px 40px 5px 40px;
                box-shadow: 0 5px 15px -5px rgba(0, 0, 0, 0.2);
                cursor: pointer;
                transition: all 0.3s ease;
            }

            .org-node-sm:hover {
                transform: scale(1.1) translateY(-5px);
                box-shadow: 0 15px 25px -5px rgba(0, 0, 0, 0.3);
                border-radius: 40px 5px 40px 5px;
                z-index: 20;
            }

            .node-icon {
                font-size: 2rem;
                margin-top: 0.5rem;
                opacity: 0.8;
            }
        </style>
    </section>

    <!-- Org Chart Details Modal -->
    <div id="orgModal"
        class="fixed inset-0 z-[150] bg-black/80 backdrop-blur-sm hidden flex items-center justify-center p-4 opacity-0 transition-opacity duration-300"
        onclick="if(event.target === this) closeOrgModal()">
        <div class="bg-white w-full max-w-2xl rounded-3xl overflow-hidden shadow-2xl transform scale-95 transition-transform duration-300"
            id="orgModalContent">
            <div
                class="h-32 bg-gradient-to-r from-primary-800 to-primary-600 relative flex items-center justify-center">
                <button onclick="closeOrgModal()"
                    class="absolute top-4 right-4 text-white/70 hover:text-white bg-white/10 hover:bg-white/20 rounded-full w-10 h-10 flex items-center justify-center transition-all">✕</button>
                <h3 id="modalTitle" class="text-3xl font-black text-white drop-shadow-md">عنوان</h3>
                <!-- Decorative Circles -->
                <div class="absolute -bottom-10 -left-10 w-32 h-32 bg-white/10 rounded-full blur-2xl"></div>
                <div class="absolute -top-10 -right-10 w-32 h-32 bg-white/10 rounded-full blur-2xl"></div>
            </div>

            <div class="p-8 max-h-[70vh] overflow-y-auto custom-scrollbar">
                <div id="modalDescription"
                    class="mb-8 text-gray-500 leading-relaxed text-center border-b border-gray-100 pb-6"></div>
                <div id="modalPeople" class="grid gap-4">
                    <!-- People cards will be injected here -->
                </div>
            </div>

            <div class="bg-gray-50 p-4 text-center border-t border-gray-100">
                <button onclick="closeOrgModal()"
                    class="text-primary-600 font-bold hover:bg-primary-50 px-6 py-2 rounded-xl transition-all">بستن</button>
            </div>
        </div>
    </div>

    <script>
        const orgData = {
            'trustees': {
                title: 'هیئت امنا',
                desc: 'بالاترین رکن تصمیم‌گیری بنیاد که وظیفه تعیین سیاست‌های کلی و نظارت عالیه را بر عهده دارد.',
                count: 5,
                people: [
                    { name: 'بهنام بهرمن', role: 'عضو هیئت امنا', img: 'uploads/board_behnam.png' },
                    { name: 'فرزاد فرشید', role: 'عضو هیئت امنا', img: 'uploads/board_farzad.png' },
                    { name: 'فرید برهان علمی', role: 'عضو هیئت امنا', img: 'uploads/board_farid.jpg' },
                    { name: 'مهدی صنوبری قراتی', role: 'عضو هیئت امنا', img: 'uploads/board_mehdi.png' },
                    { name: 'محمد امینی', role: 'عضو هیئت امنا', img: 'uploads/board_mohammad.png' }
                ]
            },
            'directors': {
                title: 'هیئت مدیره',
                desc: 'مسئولیت اجرای مصوبات هیئت امنا و اداره امور جاری بنیاد بر عهده این گروه است.',
                people: [
                    { name: 'بهنام بهرمن', role: 'رئیس هیئت مدیره و عضو اصلی', img: 'uploads/board_behnam.png' },
                    { name: 'فرزاد فرشید', role: 'نایب رئیس هیئت مدیره و عضو اصلی', img: 'uploads/board_farzad.png' },
                    { name: 'فرید برهان علمی', role: 'مدیر عامل و عضو اصلی', img: 'uploads/board_farid.jpg' },
                    { name: 'مهدی صنوبری قراتی', role: 'خزانه‌دار و عضو اصلی', img: 'uploads/board_mehdi.png' },
                    { name: 'محمد امینی', role: 'عضو اصلی', img: 'uploads/board_mohammad.png' },
                    { name: 'مسعود شفیعی خازنی', role: 'عضو علی‌البدل', img: 'uploads/board_shafiei.png' },
                    { name: 'نوید محراب زاده', role: 'عضو علی‌البدل', img: 'uploads/board_mehrabzadeh.png' }
                ]
            },
            'inspectors': {
                title: 'بازرسین',
                desc: 'نظارت بر عملکرد مالی و اداری بنیاد و ارائه گزارش‌های دوره‌ای.',
                people: [
                    { name: 'مسعود مهدیزاده', role: 'بازرس اصلی', img: 'https://ui-avatars.com/api/?name=مسعود+مهدیزاده&background=4d7c0f&color=fff' },
                    { name: 'مجتبی حسن آبادی', role: 'بازرس علی‌البدل', img: 'https://ui-avatars.com/api/?name=مجتبی+حسن+آبادی&background=4d7c0f&color=fff' }
                ]
            },
            'ceo': {
                title: 'مدیر عامل',
                desc: 'مسئول اجرایی تمامی فعالیت‌های بنیاد و نماینده حقوقی سازمان.',
                people: [
                    { name: 'فرید برهان علمی', role: 'مدیر عامل', img: 'uploads/board_farid.jpg' }
                ]
            },
            'advisors': {
                title: 'مشاورین',
                desc: 'گروهی از متخصصین که در حوزه‌های مختلف به مدیرعامل و هیئت مدیره مشاوره می‌دهند.',
                people: [
                    { name: 'نام مشاور ۱', role: 'مشاور آموزشی', img: 'https://ui-avatars.com/api/?name=مشاور+یک&background=84cc16&color=fff' },
                    { name: 'نام مشاور ۲', role: 'مشاور مالی', img: 'https://ui-avatars.com/api/?name=مشاور+دو&background=84cc16&color=fff' }
                ]
            },
            'deputy_education': { title: 'معاونت آموزشی', desc: 'مدیریت کلیه برنامه‌های آموزشی و بورسیه دانش‌آموزان.', people: [{ name: 'نام معاون', role: 'معاون آموزشی', img: 'https://ui-avatars.com/api/?name=معاون+آموزشی&background=84cc16&color=fff' }] },
            'deputy_executive': { title: 'معاونت اجرایی', desc: 'پشتیبانی و تدارکات کلیه واحدها و پروژه‌ها.', people: [{ name: 'نام معاون', role: 'معاون اجرایی', img: 'https://ui-avatars.com/api/?name=معاون+اجرایی&background=06b6d4&color=fff' }] },
            'deputy_finance': { title: 'معاونت مالی', desc: 'مدیریت منابع مالی، حسابداری و بودجه‌ریزی.', people: [{ name: 'نام معاون', role: 'معاون مالی', img: 'https://ui-avatars.com/api/?name=معاون+مالی&background=155e75&color=fff' }] },
            'deputy_participation': { title: 'معاونت مشارکت‌ها', desc: 'جذب مشارکت‌های مردمی و مدیریت ارتباط با خیرین.', people: [{ name: 'نام معاون', role: 'معاون مشارکت‌ها', img: 'https://ui-avatars.com/api/?name=معاون+مشارکت&background=0c4a6e&color=fff' }] },
            'deputy_ads': { title: 'معاونت تبلیغات', desc: 'مدیریت روابط عمومی، رسانه و تبلیغات بنیاد.', people: [{ name: 'نام معاون', role: 'معاون تبلیغات', img: 'https://ui-avatars.com/api/?name=معاون+تبیلغات&background=0d9488&color=fff' }] },
        };

        function showOrgModal(key) {
            const data = orgData[key];
            if (!data) return;

            document.getElementById('modalTitle').textContent = data.title;
            document.getElementById('modalDescription').textContent = data.desc;
            
            const peopleContainer = document.getElementById('modalPeople');
            peopleContainer.innerHTML = '';
            
            data.people.forEach((person, index) => {
                const delay = index * 100;
                const card = `
                    <div class="flex items-center gap-4 p-4 bg-white border border-gray-100 rounded-2xl hover:shadow-lg transition-all transform opacity-0 translate-y-4 animate-slide-up" style="animation-delay: ${delay}ms; animation-fill-mode: forwards;">
                        <img src="${person.img}" class="w-16 h-16 rounded-full border-2 border-primary-100 object-cover">
                        <div>
                            <h4 class="font-bold text-lg text-primary-900">${person.name}</h4>
                            <p class="text-sm text-gray-500">${person.role}</p>
                        </div>
                    </div>
                `;
                peopleContainer.innerHTML += card;
            });

            const modal = document.getElementById('orgModal');
            modal.classList.remove('hidden');
            // Trigger reflow
            void modal.offsetWidth;
            modal.classList.remove('opacity-0');
            document.getElementById('orgModalContent').classList.remove('scale-95');
            document.getElementById('orgModalContent').classList.add('scale-100');
        }

        function closeOrgModal() {
            const modal = document.getElementById('orgModal');
            modal.classList.add('opacity-0');
            document.getElementById('orgModalContent').classList.remove('scale-100');
            document.getElementById('orgModalContent').classList.add('scale-95');
            
            setTimeout(() => {
                modal.classList.add('hidden');
            }, 300);
        }
    </script>

    <style>
        @keyframes slide-up {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .animate-slide-up {
            animation: slide-up 0.5s ease-out forwards;
        }
    </style>


    <footer class="bg-gray-900 text-gray-500 py-16 text-center border-t border-gray-800">
        <div class="container mx-auto px-6 mb-12">
            <div class="flex flex-wrap justify-center gap-6">
                <a href="https://instagram.com/Hekmattoos_" target="_blank"
                    class="flex items-center gap-3 px-6 py-3 bg-gradient-to-tr from-pink-500 via-red-500 to-yellow-500 text-white rounded-2xl font-bold shadow-lg hover:shadow-pink-500/20 hover:-translate-y-1 transition-all">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                        <path
                            d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z" />
                    </svg>
                    اینستاگرام ما
                </a>
                <a href="https://linkedin.com/company/hekmat-charity-foundation" target="_blank"
                    class="flex items-center gap-3 px-6 py-3 bg-[#0a66c2] text-white rounded-2xl font-bold shadow-lg hover:shadow-blue-500/20 hover:-translate-y-1 transition-all">
                    <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
                        <path
                            d="M19 0h-14c-2.761 0-5 2.239-5 5v14c0 2.761 2.239 5 5 5h14c2.762 0 5-2.239 5-5v-14c0-2.761-2.238-5-5-5zm-11 19h-3v-11h3v11zm-1.5-12.268c-.966 0-1.75-.79-1.75-1.764s.784-1.764 1.75-1.764 1.75.79 1.75 1.764-.783 1.764-1.75 1.764zm13.5 12.268h-3v-5.604c0-3.368-4-3.113-4 0v5.604h-3v-11h3v1.765c1.396-2.586 7-2.777 7 2.476v6.759z" />
                    </svg>
                    لینکدین
                </a>
                <a href="tel:09926724850"
                    class="flex items-center gap-3 px-6 py-3 bg-teal-600 text-white rounded-2xl font-bold shadow-lg hover:shadow-teal-500/20 hover:-translate-y-1 transition-all">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z">
                        </path>
                    </svg>
                    تماس با ما
                </a>
            </div>
        </div>
        <p class="text-white font-black text-xl mb-4">بنیاد نیکوکاری حکمت</p>
        <p class="text-sm max-w-md mx-auto leading-loose px-6">تلاشی برای ساختن دنیایی که در آن هیچ دانش‌آموزی از تحصیل
            باز نماند. شماره ثبت: ۷۷۰۷</p>
        <div class="mt-8 text-[10px] opacity-30 italic">© 2026 Neromoda Charity Division.</div>
    </footer>

    <!-- Video Modal -->
    <div id="videoModal"
        class="fixed inset-0 z-[60] bg-black/90 backdrop-blur-xl hidden flex items-center justify-center p-4 transition-all duration-300"
        onclick="if(event.target === this) { this.classList.add('hidden'); document.getElementById('heroVideo').pause(); }">
        <div class="relative w-full max-w-5xl bg-black rounded-3xl overflow-hidden shadow-2xl border border-white/10">
            <button
                onclick="document.getElementById('videoModal').classList.add('hidden'); document.getElementById('heroVideo').pause()"
                class="absolute top-4 right-4 z-10 w-10 h-10 bg-white/10 hover:bg-white/30 text-white rounded-full flex items-center justify-center transition-all backdrop-blur-md">✕</button>
            <video id="heroVideo" controls class="w-full h-auto aspect-video">
                <source src="briefing.mp4" type="video/mp4">
            </video>
        </div>
    </div>

</body>

</html>