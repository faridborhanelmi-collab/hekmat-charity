<!DOCTYPE html>
<html lang="fa" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>درباره ما | بنیاد نیکوکاری حکمت</title>
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
    <nav
        class="fixed w-full z-50 transition-all duration-300 bg-white/90 backdrop-blur-md border-b border-gray-100 shadow-sm">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">

            <div class="flex items-center gap-6">
                <a href="login.php"
                    class="hidden md:flex items-center gap-2 px-6 py-2 bg-gradient-to-r from-teal-400 to-teal-600 text-white rounded-full font-bold shadow-lg hover:shadow-teal-500/30 transition-all transform hover:-translate-y-1">
                    <span>ورود / ثبت‌نام</span>
                </a>

                <div class="hidden md:flex gap-8 text-gray-700">
                    <a href="index.php" class="hover:text-teal-600 transition-colors font-medium">صفحه اصلی</a>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <div class="text-right">
                    <div class="text-xl font-black text-gray-800 tracking-tight">بنیاد نیکوکاری <span
                            class="text-teal-500">حکمت</span></div>
                </div>
                <div
                    class="relative w-12 h-12 flex items-center justify-center bg-gradient-to-br from-teal-400 to-teal-600 rounded-2xl shadow-inner overflow-hidden">
                    <span class="text-2xl font-black text-white">م</span>
                </div>
            </div>

        </div>
    </nav>

    <!-- Hero Header -->
    <header class="relative h-[60vh] flex items-center justify-center bg-primary-900 text-white overflow-hidden pt-20">
        <div class="absolute inset-0 bg-cover bg-center opacity-40 fixed-bg"
            style="background-image: url('https://images.unsplash.com/photo-1544027993-37dbfe43562a?q=80&w=2670');">
        </div>
        <div class="relative z-10 text-center px-4 max-w-4xl mx-auto">
            <h1 class="text-4xl md:text-6xl font-black mb-6 leading-tight">حامی شکوفایی کودکان مستعد و <span
                    class="text-teal-300">توانمند ایران‌زمین</span></h1>
            <p class="text-xl text-teal-100/90 leading-relaxed font-light">داستان ما، داستان تبدیل مهربانی به آینده‌ای
                روشن است.</p>
        </div>
    </header>

    <!-- Our Story -->
    <section class="py-24 container mx-auto px-6 max-w-5xl">
        <div class="grid md:grid-cols-2 gap-12 items-center">
            <div class="mb-10 text-center md:text-right">
                <span class="text-teal-500 font-bold tracking-widest text-sm uppercase mb-2 block">Our Story</span>
                <h2 class="text-4xl font-black text-gray-900 mb-8 leading-tight">۳۵ سال رفاقت؛<br>داستان یک عهد جاودانه
                </h2>
                <p class="text-gray-600 leading-loose text-lg text-justify">
                    داستان ما از یک قاب عکس قدیمی شروع می‌شود؛ از روزهای پرشور مدرسه و عهدی که میان جمعی از دانش‌آموزان
                    دبیرستان حکمت بسته شد. ۳۵ سال پیش، ما با هم پیمان بستیم که روزی، دست در دست هم، سقفی از مهربانی برای
                    کودکانی بسازیم که تنها جرمشان فقر است. امروز، آن نهال کوچک به درختی تنومند تبدیل شده است که سایه‌اش
                    پناهگاهی امن برای صدها دانش‌آموز مستعد این سرزمین است.
                </p>
            </div>
            <div class="relative group perspective-1000">
                <div
                    class="absolute inset-0 bg-teal-200 rounded-[3rem] rotate-6 opacity-30 group-hover:rotate-12 transition-all duration-700">
                </div>
                <!-- Founders Photo -->
                <img src="founders.jpg" alt="بنیان‌گذاران بنیاد حکمت"
                    class="relative rounded-[3rem] shadow-2xl w-full object-cover h-[400px] border-8 border-white transform transition-all duration-700 group-hover:scale-105 group-hover:-rotate-2 mb-8">

                <!-- Video Player -->
                <div
                    class="relative rounded-[2rem] overflow-hidden shadow-2xl border-4 border-white transform transition-all hover:scale-105">
                    <video controls class="w-full bg-black">
                        <source src="briefing.mp4" type="video/mp4">
                        مرورگر شما از پخش ویدئو پشتیبانی نمی‌کند.
                    </video>
                    <div
                        class="absolute bottom-0 left-0 w-full p-4 bg-gradient-to-t from-black/80 to-transparent text-white">
                        <p class="font-bold text-sm">🎥 ویدیو: ۳۵ سال رفاقت (داستان ما)</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Mission & Identity Cards -->
    <section class="py-20 bg-gray-100">
        <div class="container mx-auto px-6 max-w-6xl">
            <div class="grid md:grid-cols-2 gap-8">
                <!-- Identity -->
                <div
                    class="bg-white p-10 rounded-[2.5rem] shadow-xl border border-gray-100 hover:-translate-y-2 transition-transform duration-500">
                    <div
                        class="w-16 h-16 bg-blue-100 text-blue-600 rounded-2xl flex items-center justify-center text-3xl mb-6">
                        🆔</div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4">هویت ما</h3>
                    <p class="text-gray-500 leading-loose text-justify">
                        بنیاد نیکوکاری حکمت (شماره ثبت ۷۷۰۷) یک کانون غیردولتی و مردم‌نهاد با مرکزیت شهر مشهد است. تمرکز
                        اصلی ما شناسایی دانش‌آموزان مستعد در خانواده‌های کم‌برخوردار و تأمین نیازهای آموزشی و پشتیبانی
                        آن‌هاست تا زمینه رشد و شکوفایی استعدادهایشان فراهم شود. ما باور داریم که هیچ استعدادی نباید به
                        دلیل فقر خاموش بماند.
                    </p>
                </div>

                <!-- Vision -->
                <div
                    class="bg-gradient-to-br from-teal-900 to-primary-900 text-white p-10 rounded-[2.5rem] shadow-xl hover:-translate-y-2 transition-transform duration-500 relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-bl-full"></div>
                    <div
                        class="w-16 h-16 bg-white/20 text-white rounded-2xl flex items-center justify-center text-3xl mb-6 backdrop-blur-md">
                        👁️</div>
                    <h3 class="text-2xl font-bold mb-4">چشم‌انداز ما (Vision)</h3>
                    <p class="text-teal-50 leading-loose text-justify font-light">
                        آرمان ما روزی است که <span class="font-bold text-white">«هیچ دانش‌آموز مستعد و توانمندی در
                            ایران‌زمین، به دلیل فقر و نیازمندی از تحصیل و شکوفایی استعدادهای خود بازنماند»</span>. ما
                        می‌کوشیم تا با بسیج نیروهای نیکوکار، نسلی را پرورش دهیم که روزی خود دستگیر نیازمندان دیگر باشند.
                    </p>
                </div>
            </div>
        </div>
    </section>

    <!-- Mission Bullets -->
    <section class="py-24 container mx-auto px-6 max-w-5xl">
        <h2 class="text-3xl font-black text-center text-gray-900 mb-16">مأموریت ما (Mission)</h2>
        <div class="grid md:grid-cols-4 gap-6 text-center">
            <div class="group">
                <div
                    class="w-20 h-20 mx-auto bg-green-50 text-green-600 rounded-full flex items-center justify-center text-3xl mb-4 group-hover:scale-110 transition-transform shadow-sm border border-green-100">
                    🔍</div>
                <h4 class="font-bold text-lg mb-2">شناسایی دقیق</h4>
                <p class="text-sm text-gray-500 leading-relaxed px-2">یافتن دانش‌آموزان مستعد نیازمند از طریق مسیرهای
                    مطمئن.</p>
            </div>
            <div class="group">
                <div
                    class="w-20 h-20 mx-auto bg-red-50 text-red-500 rounded-full flex items-center justify-center text-3xl mb-4 group-hover:scale-110 transition-transform shadow-sm border border-red-100">
                    🛡️</div>
                <h4 class="font-bold text-lg mb-2">حمایت جامع</h4>
                <p class="text-sm text-gray-500 leading-relaxed px-2">رفع دغدغه‌های تحصیلی، پزشکی و معیشتی.</p>
            </div>
            <div class="group">
                <div
                    class="w-20 h-20 mx-auto bg-blue-50 text-blue-500 rounded-full flex items-center justify-center text-3xl mb-4 group-hover:scale-110 transition-transform shadow-sm border border-blue-100">
                    🚀</div>
                <h4 class="font-bold text-lg mb-2">توانمندسازی</h4>
                <p class="text-sm text-gray-500 leading-relaxed px-2">کمک به شکوفایی استعدادها جهت رسیدن به آینده‌ای
                    روشن.</p>
            </div>
            <div class="group">
                <div
                    class="w-20 h-20 mx-auto bg-purple-50 text-purple-500 rounded-full flex items-center justify-center text-3xl mb-4 group-hover:scale-110 transition-transform shadow-sm border border-purple-100">
                    💎</div>
                <h4 class="font-bold text-lg mb-2">شفافیت</h4>
                <p class="text-sm text-gray-500 leading-relaxed px-2">ایجاد اطمینان خاطر برای خیرین با قابلیت رصد
                    کمک‌ها.</p>
            </div>
        </div>
    </section>

    <!-- Values & Services Grid -->
    <section class="py-24 bg-gray-900 text-white relative overflow-hidden">
        <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/cubes.png')] opacity-10">
        </div>
        <div class="container mx-auto px-6 max-w-6xl relative z-10">
            <div class="grid md:grid-cols-2 gap-16">
                <!-- Values -->
                <div>
                    <h2 class="text-3xl font-black mb-10 text-teal-400">ارزش‌های بنیادین</h2>
                    <ul class="space-y-8">
                        <li class="flex gap-4">
                            <div
                                class="w-12 h-12 bg-white/10 rounded-xl flex items-center justify-center text-2xl shrink-0">
                                🎣</div>
                            <div>
                                <h4 class="font-bold text-lg mb-1">ماهیگیری یاد می‌دهیم</h4>
                                <p class="text-gray-400 text-sm leading-relaxed">به جای صرفاً ماهی دادن، بر آموزش و
                                    مهارت‌آموزی تمرکز داریم تا چرخه فقر شکسته شود.</p>
                            </div>
                        </li>
                        <li class="flex gap-4">
                            <div
                                class="w-12 h-12 bg-white/10 rounded-xl flex items-center justify-center text-2xl shrink-0">
                                🤝</div>
                            <div>
                                <h4 class="font-bold text-lg mb-1">حفظ کرامت</h4>
                                <p class="text-gray-400 text-sm leading-relaxed">تمامی خدمات با حفظ احترام و کرامت
                                    دانش‌آموزان و خانواده‌هایشان ارائه می‌شود.</p>
                            </div>
                        </li>
                        <li class="flex gap-4">
                            <div
                                class="w-12 h-12 bg-white/10 rounded-xl flex items-center justify-center text-2xl shrink-0">
                                ⚖️</div>
                            <div>
                                <h4 class="font-bold text-lg mb-1">بی‌طرفی</h4>
                                <p class="text-gray-400 text-sm leading-relaxed">خدمات ما فارغ از ملیت، قومیت و
                                    گرایش‌های مذهبی به دانش‌آموزان مستعد ارائه می‌گردد.</p>
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- Achievements -->
                <div class="bg-white/5 p-10 rounded-[3rem] border border-white/10 backdrop-blur-sm">
                    <h2 class="text-3xl font-black mb-10 text-accent-400">دستاوردها</h2>
                    <div class="space-y-6">
                        <div class="flex items-center gap-4">
                            <span class="text-3xl">🎓</span>
                            <p class="text-lg">قبولی در رشته‌های برتر (پزشکی، مهندسی، حقوق) دانشگاه‌های تهران، شهید
                                بهشتی و...</p>
                        </div>
                        <div class="w-full h-px bg-white/10"></div>
                        <div class="flex items-center gap-4">
                            <span class="text-3xl">🥇</span>
                            <p class="text-lg">کسب مدال‌های طلا و برنز در المپیادهای علمی (زیست‌شناسی و سواد رسانه‌ای).
                            </p>
                        </div>
                        <div class="w-full h-px bg-white/10"></div>
                        <div class="flex items-center gap-4">
                            <span class="text-3xl">📈</span>
                            <p class="text-lg">پوشش ده‌ها دانش‌آموز مستعد (دختر و پسر).</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Student Achievements (Hall of Fame) -->
    <section class="py-24 bg-teal-900 text-white relative overflow-hidden">
        <div class="absolute inset-0 bg-[url('https://www.transparenttextures.com/patterns/cubes.png')] opacity-10">
        </div>
        <div class="container mx-auto px-6 max-w-6xl relative z-10">
            <div class="text-center mb-16">
                <span class="text-teal-400 font-bold tracking-widest text-sm uppercase mb-2 block">Hall of Fame</span>
                <h2 class="text-3xl md:text-5xl font-black mb-6">ستارگان حکمت</h2>
                <p class="text-teal-100 text-lg max-w-2xl mx-auto leading-relaxed">
                    افتخارآفرینانی که با تلاش خود و حمایت شما، سقف‌های شیشه‌ای را شکستند و به قله‌های موفقیت رسیدند.
                </p>
            </div>

            <!-- Top Ranks (Hero Cards) -->
            <div class="grid md:grid-cols-2 gap-8 mb-12">
                <!-- Card 1 -->
                <div
                    class="bg-gradient-to-br from-white/10 to-white/5 backdrop-blur-md p-8 rounded-[2.5rem] border border-white/10 flex items-center gap-6 hover:transform hover:scale-105 transition-all duration-300 shadow-2xl relative overflow-hidden group">
                    <div
                        class="absolute -right-10 -top-10 w-32 h-32 bg-yellow-400/20 rounded-full blur-3xl group-hover:bg-yellow-400/30 transition-all">
                    </div>
                    <div
                        class="w-24 h-24 bg-white rounded-full flex items-center justify-center p-2 shadow-lg shrink-0 z-10">
                        <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/c/c3/University_of_Tehran_logo.svg/1024px-University_of_Tehran_logo.svg.png"
                            alt="University of Tehran" class="w-full h-full object-contain">
                    </div>
                    <div>
                        <div class="text-yellow-400 font-black text-3xl mb-1">رتبه ۱۹ کنکور</div>
                        <h3 class="text-2xl font-bold mb-2">متین</h3>
                        <p class="text-teal-200 text-sm">دانشجوی حقوق دانشگاه تهران</p>
                    </div>
                </div>

                <!-- Card 2 -->
                <div
                    class="bg-gradient-to-br from-white/10 to-white/5 backdrop-blur-md p-8 rounded-[2.5rem] border border-white/10 flex items-center gap-6 hover:transform hover:scale-105 transition-all duration-300 shadow-2xl relative overflow-hidden group">
                    <div
                        class="absolute -right-10 -top-10 w-32 h-32 bg-teal-400/20 rounded-full blur-3xl group-hover:bg-teal-400/30 transition-all">
                    </div>
                    <div
                        class="w-24 h-24 bg-white rounded-full flex items-center justify-center p-2 shadow-lg shrink-0 z-10">
                        <img src="https://upload.wikimedia.org/wikipedia/fa/8/87/SBU_Logo.png"
                            alt="Shahid Beheshti University" class="w-full h-full object-contain">
                    </div>
                    <div>
                        <div class="text-teal-400 font-black text-3xl mb-1">رتبه ۹۶ کنکور</div>
                        <h3 class="text-2xl font-bold mb-2">احمد</h3>
                        <p class="text-teal-200 text-sm">دانشجوی حقوق دانشگاه شهید بهشتی</p>
                    </div>
                </div>
            </div>

            <!-- Other Achievers Grid -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                <!-- Item -->
                <div
                    class="bg-white/5 hover:bg-white/10 p-6 rounded-3xl border border-white/5 text-center transition-all group">
                    <div
                        class="h-16 w-16 mx-auto bg-white rounded-2xl flex items-center justify-center mb-4 text-3xl shadow-lg">
                        ⚖️</div>
                    <h4 class="font-bold text-lg mb-1">الهام و مائده</h4>
                    <p class="text-xs text-teal-200/70">حقوق فردوسی</p>
                </div>
                <!-- Item -->
                <div
                    class="bg-white/5 hover:bg-white/10 p-6 rounded-3xl border border-white/5 text-center transition-all group">
                    <div
                        class="h-16 w-16 mx-auto bg-white rounded-2xl flex items-center justify-center mb-4 text-3xl shadow-lg">
                        🧠</div>
                    <h4 class="font-bold text-lg mb-1">امیرحسین</h4>
                    <p class="text-xs text-teal-200/70">روانشناسی فردوسی</p>
                </div>
                <!-- Item -->
                <div
                    class="bg-white/5 hover:bg-white/10 p-6 rounded-3xl border border-white/5 text-center transition-all group">
                    <div
                        class="h-16 w-16 mx-auto bg-white rounded-2xl flex items-center justify-center mb-4 text-3xl shadow-lg">
                        🧪</div>
                    <h4 class="font-bold text-lg mb-1">کوثر</h4>
                    <p class="text-xs text-teal-200/70">مهندسی شیمی</p>
                </div>
                <!-- Item -->
                <div
                    class="bg-white/5 hover:bg-white/10 p-6 rounded-3xl border border-white/5 text-center transition-all group">
                    <div
                        class="h-16 w-16 mx-auto bg-white rounded-2xl flex items-center justify-center mb-4 text-3xl shadow-lg">
                        🦷</div>
                    <h4 class="font-bold text-lg mb-1">نازیلا</h4>
                    <p class="text-xs text-teal-200/70">دندانپزشکی مشهد</p>
                </div>
                <!-- Item -->
                <div
                    class="bg-white/5 hover:bg-white/10 p-6 rounded-3xl border border-white/5 text-center transition-all group">
                    <div
                        class="h-16 w-16 mx-auto bg-white rounded-2xl flex items-center justify-center mb-4 text-3xl shadow-lg">
                        💊</div>
                    <h4 class="font-bold text-lg mb-1">صادق</h4>
                    <p class="text-xs text-teal-200/70">داروسازی مشهد</p>
                </div>
                <!-- Item -->
                <div
                    class="bg-white/5 hover:bg-white/10 p-6 rounded-3xl border border-white/5 text-center transition-all group">
                    <div
                        class="h-16 w-16 mx-auto bg-white rounded-2xl flex items-center justify-center mb-4 text-3xl shadow-lg">
                        🚑</div>
                    <h4 class="font-bold text-lg mb-1">امیرحسین</h4>
                    <p class="text-xs text-teal-200/70">دندانپزشکی (طلا المپیاد)</p>
                </div>
                <!-- Item -->
                <div
                    class="bg-white/5 hover:bg-white/10 p-6 rounded-3xl border border-white/5 text-center transition-all group">
                    <div
                        class="h-16 w-16 mx-auto bg-white rounded-2xl flex items-center justify-center mb-4 text-3xl shadow-lg">
                        ⚙️</div>
                    <h4 class="font-bold text-lg mb-1">امیرپارسا</h4>
                    <p class="text-xs text-teal-200/70">مهندسی مکانیک</p>
                </div>
                <!-- Item -->
                <div
                    class="bg-white/5 hover:bg-white/10 p-6 rounded-3xl border border-white/5 text-center transition-all group">
                    <div
                        class="h-16 w-16 mx-auto bg-white rounded-2xl flex items-center justify-center mb-4 text-3xl shadow-lg">
                        🎓</div>
                    <h4 class="font-bold text-lg mb-1">محمدحسام</h4>
                    <p class="text-xs text-teal-200/70">فلسفه دانشگاه شیراز</p>
                </div>
            </div>

            <div class="mt-12 text-center">
                <p class="text-sm opacity-50">و ده‌ها ستاره‌ی دیگر که آسمان ایران را روشن کرده‌اند...</p>
            </div>
        </div>
    </section>

    <!-- Final CTA -->
    <section class="py-24 container mx-auto px-6 text-center">
        <div
            class="max-w-3xl mx-auto bg-gradient-to-r from-teal-500 to-primary-600 rounded-[3rem] p-12 md:p-20 shadow-2xl text-white relative overflow-hidden">
            <div
                class="absolute top-0 left-0 w-full h-full bg-[url('https://www.transparenttextures.com/patterns/stardust.png')] opacity-20">
            </div>

            <h2 class="text-3xl md:text-5xl font-black mb-8 relative z-10">همراه ما باشید</h2>
            <p class="text-lg md:text-xl text-teal-50 mb-12 leading-relaxed relative z-10 font-light">
                شما نیز می‌توانید با نیت خیر خود، در ساختن فردای این کودکان سهیم باشید.
                <br>
                <span class="font-bold text-white block mt-4">با دست مهربان خود امروز، فردای کودکی را بسازید.</span>
            </p>

            <a href="index.php#donate"
                class="inline-block px-12 py-5 bg-white text-teal-600 text-xl font-black rounded-full shadow-xl hover:shadow-2xl hover:scale-105 transition-all transform relative z-10">
                حمایت می‌کنم ❤️
            </a>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-12 text-center border-t border-white/5">
        <p class="opacity-50">© 1403 بنیاد نیکوکاری حکمت.</p>
    </footer>

</body>

</html>