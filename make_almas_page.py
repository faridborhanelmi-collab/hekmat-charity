import re

almas_code = """<?php
session_start();
require_once 'includes/db.php';

$page_title = 'پویش گنج‌های پنهان | چالش هوش و استعدادیابی بنیاد حکمت';
$page_desc = 'پویش کشف نخبگان و گنج‌های پنهان سراسر کشور. اگر حس می‌کنی باهوشی اما مدرسه و نمرات فرصت درخشش به تو نداده، در چالش ۱۵ دقیقه‌ای هوش و حل مسئله شرکت کن و بورسیه شو!';
?>
<!DOCTYPE html>
<html lang="fa-IR" dir="rtl" class="scroll-smooth">

<head>
    <?php include 'includes/head.php'; ?>
    <style>
        .diamond-gradient {
            background: linear-gradient(135deg, #042f2e 0%, #064e3b 40%, #0f172a 100%);
        }
        .diamond-card {
            background: rgba(255, 255, 255, 0.04);
            backdrop-filter: blur(16px);
            border: 1px solid rgba(20, 184, 166, 0.25);
        }
        .diamond-glow {
            box-shadow: 0 0 35px -5px rgba(20, 184, 166, 0.35);
        }
        .pulse-timer {
            animation: pulse-ring 1s cubic-bezier(0.215, 0.61, 0.355, 1) infinite;
        }
        @keyframes pulse-ring {
            0% { transform: scale(0.95); opacity: 0.8; }
            50% { transform: scale(1.05); opacity: 1; }
            100% { transform: scale(0.95); opacity: 0.8; }
        }
        .option-btn:hover {
            border-color: #14b8a6;
            background-color: rgba(20, 184, 166, 0.08);
            transform: translateY(-2px);
        }
        .option-btn.selected {
            border-color: #0d9488;
            background-color: rgba(13, 148, 136, 0.2);
            box-shadow: 0 0 15px rgba(13, 148, 136, 0.4);
        }
    </style>
</head>

<body class="bg-[#02111b] text-gray-100 font-sans antialiased min-h-screen selection:bg-teal-500 selection:text-white">

    <?php include 'includes/navbar.php'; ?>

    <!-- Main Hero & Manifesto -->
    <header class="relative pt-32 pb-20 overflow-hidden diamond-gradient border-b border-teal-500/20">
        <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-teal-500/15 via-transparent to-transparent pointer-events-none"></div>
        <div class="container mx-auto px-4 relative z-10 text-center max-w-4xl">
            
            <div class="inline-flex items-center gap-2 px-4 py-1.5 rounded-full bg-teal-500/15 border border-teal-400/30 text-teal-300 text-xs md:text-sm font-bold mb-6 shadow-inner animate-pulse">
                <span class="w-2.5 h-2.5 rounded-full bg-teal-400 shadow-[0_0_10px_#2dd4bf]"></span>
                پویش ملی استعدادیابی بنیاد نیکوکاری حکمت
            </div>

            <h1 class="text-4xl md:text-6xl font-black text-white mb-6 leading-tight tracking-tight drop-shadow-md">
                گنج‌های <span class="bg-gradient-to-r from-teal-300 via-emerald-300 to-cyan-400 bg-clip-text text-transparent">پنهان</span>
            </h1>

            <p class="text-xl md:text-2xl font-bold text-teal-100/90 mb-6">
                هوش در کارنامه مدرسه پنهان نمی‌ماند!
            </p>

            <p class="text-base md:text-lg text-gray-300 leading-relaxed max-w-3xl mx-auto mb-10 text-justify md:text-center">
                آیا احساس می‌کنی توانایی ذهنی بالایی داری، اما نمرات کلاسی و امتحانات تستی فرصت درخشش به تو نداده‌اند؟ 
                بسیاری از نوابغ دنیا در مدارس معمولی نمرات متوسطی داشتند. 
                <strong class="text-teal-300 font-black">بنیاد حکمت</strong> به دنبال دانش‌آموزان باهوش و بااراده‌ای است که به خاطر فقر مالی یا نبود امکانات آموزشی دیده نشده‌اند. 
                این چالش ۱۵ دقیقه‌ای بدون نیاز به کتاب درسی، توانایی تحلیلی خام مغز تو را می‌سنجد.
            </p>

            <div class="flex flex-wrap justify-center gap-4">
                <a href="#test-section" class="px-8 py-4 bg-gradient-to-r from-teal-500 to-emerald-600 hover:from-teal-400 hover:to-emerald-500 text-white rounded-2xl font-black text-lg shadow-[0_0_25px_rgba(20,184,166,0.5)] transition-all transform hover:-translate-y-1 flex items-center gap-3">
                    <svg class="w-6 h-6 animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
                    </svg>
                    ورود به چالش و شرکت در آزمون
                </a>
                <a href="burs-hekmat.php" class="px-6 py-4 bg-white/5 hover:bg-white/10 text-teal-200 border border-teal-500/30 rounded-2xl font-bold text-base transition-all">
                    آشنایی با مراحل و بورس حکمت
                </a>
            </div>

            <!-- Fast Stats Badges -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mt-16 text-center">
                <div class="diamond-card p-4 rounded-2xl">
                    <div class="text-2xl md:text-3xl font-black text-teal-300 mb-1">۱۵ سوال</div>
                    <div class="text-xs text-gray-400 font-medium">پازل‌های ۱۰۰٪ تصویری</div>
                </div>
                <div class="diamond-card p-4 rounded-2xl">
                    <div class="text-2xl md:text-3xl font-black text-teal-300 mb-1">۱۵ دقیقه</div>
                    <div class="text-xs text-gray-400 font-medium">۴۵ ثانیه برای هر سوال</div>
                </div>
                <div class="diamond-card p-4 rounded-2xl">
                    <div class="text-2xl md:text-3xl font-black text-teal-300 mb-1">صفر ریال</div>
                    <div class="text-xs text-gray-400 font-medium">شرکت کاملاً رایگان</div>
                </div>
                <div class="diamond-card p-4 rounded-2xl">
                    <div class="text-2xl md:text-3xl font-black text-teal-300 mb-1">۱۰۰٪ بورس</div>
                    <div class="text-xs text-gray-400 font-medium">حمایت مالی و تحصیلی کامل</div>
                </div>
            </div>

        </div>
    </header>

    <!-- Why This Campaign & What is Hekmat Bursary? -->
    <section id="about-campaign" class="py-20 bg-[#041926]/90 border-b border-teal-500/10">
        <div class="container mx-auto px-4 max-w-5xl">
            
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-black text-white mb-4">
                    چرا «گنج‌های پنهان»؟
                </h2>
                <div class="w-20 h-1.5 bg-gradient-to-r from-teal-400 to-emerald-500 mx-auto rounded-full mb-6"></div>
                <p class="text-gray-300 max-w-2xl mx-auto leading-relaxed text-sm md:text-base">
                    در نظام آموزشی، موفقیت به شدت وابسته به توان مالی خانواده برای خرید کتاب‌های گران‌قیمت و کلاس‌های کنکور شده است. ما این معادله ناعادلانه را به هم می‌زنیم.
                </p>
            </div>

            <div class="grid md:grid-cols-3 gap-8 mb-16">
                
                <div class="diamond-card p-6 rounded-3xl relative overflow-hidden group hover:border-teal-400/50 transition-all">
                    <div class="w-14 h-14 rounded-2xl bg-teal-500/10 border border-teal-400/30 flex items-center justify-center text-teal-300 mb-6 text-2xl font-black shadow-lg">
                        ۱
                    </div>
                    <h3 class="text-xl font-black text-white mb-3">معدل پایین، دلیل بی‌استعدادی نیست</h3>
                    <p class="text-gray-400 text-sm leading-relaxed">
                        بسیاری از دانش‌آموزان به دلیل کمبود آرامش در خانه، فقر مالی یا تدریس ضعیف مدرسه نمره خوبی نمی‌گیرند، در حالی که مغز آن‌ها مثل ساعت کار می‌کند و قدرت یادگیری شگفت‌انگیزی دارد.
                    </p>
                </div>

                <div class="diamond-card p-6 rounded-3xl relative overflow-hidden group hover:border-teal-400/50 transition-all">
                    <div class="w-14 h-14 rounded-2xl bg-teal-500/10 border border-teal-400/30 flex items-center justify-center text-teal-300 mb-6 text-2xl font-black shadow-lg">
                        ۲
                    </div>
                    <h3 class="text-xl font-black text-white mb-3">سنجش هوش بدون نیاز به حفظیات</h3>
                    <p class="text-gray-400 text-sm leading-relaxed">
                        سوالات این آزمون بر مبنای تست‌های استاندارد بین‌المللی ریون و ناکلیری طراحی شده‌اند. شما نیاز به هیچ فرمول ریاضی یا حفظیات عربی و فارسی ندارید؛ فقط الگوهای تصویری را کشف می‌کنید.
                    </p>
                </div>

                <div class="diamond-card p-6 rounded-3xl relative overflow-hidden group hover:border-teal-400/50 transition-all">
                    <div class="w-14 h-14 rounded-2xl bg-teal-500/10 border border-teal-400/30 flex items-center justify-center text-teal-300 mb-6 text-2xl font-black shadow-lg">
                        ۳
                    </div>
                    <h3 class="text-xl font-black text-white mb-3">مسیر بورس و بالندگی همه‌جانبه</h3>
                    <p class="text-gray-400 text-sm leading-relaxed">
                        دانش‌آموزانی که در این آزمون درخشیده و در مصاحبه پذیرفته شوند، تحت پوشش کامل بورس بنیاد حکمت قرار می‌گیرند: کمک‌هزینه ماهانه، کلاس‌های اساتید کشوری و منتورینگ اختصاصی.
                    </p>
                </div>

            </div>

            <!-- The 3 Steps Roadmap -->
            <div class="bg-gradient-to-br from-teal-950/60 to-slate-900/80 rounded-3xl p-8 border border-teal-500/20">
                <h3 class="text-2xl font-black text-teal-300 mb-6 text-center">نقشه راه ۳ مرحله‌ای ورود به بورس بنیاد حکمت</h3>
                
                <div class="grid md:grid-cols-3 gap-6 relative">
                    <div class="p-4 rounded-2xl bg-white/5 border border-white/10">
                        <div class="text-xs font-bold text-teal-400 mb-2">مرحله اول (هم‌اکنون)</div>
                        <h4 class="text-lg font-bold text-white mb-2">چالش آنلاین ۱۵ دقیقه‌ای</h4>
                        <p class="text-xs text-gray-300 leading-relaxed">
                            پاسخ به ۱۵ سوال تصویری در همین صفحه. هر سوال ۴۵ ثانیه زمان دارد تا فرصت کمک گرفتن یا مشورت نباشد و هوش واقعی شما سنجیده شود.
                        </p>
                    </div>

                    <div class="p-4 rounded-2xl bg-white/5 border border-white/10">
                        <div class="text-xs font-bold text-teal-400 mb-2">مرحله دوم (دعوت برگزیدگان)</div>
                        <h4 class="text-lg font-bold text-white mb-2">روز کشف استعداد و راستی‌آزمایی</h4>
                        <p class="text-xs text-gray-300 leading-relaxed">
                            پذیرفته‌شدگان آزمون آنلاین به یک رویداد نیم‌روزه شاداب دعوت می‌شوند تا در کارگاه حل مسئله و آزمون راستی‌آزمایی حضوری شرکت کنند.
                        </p>
                    </div>

                    <div class="p-4 rounded-2xl bg-white/5 border border-white/10">
                        <div class="text-xs font-bold text-teal-400 mb-2">مرحله سوم (انتخاب نهایی)</div>
                        <h4 class="text-lg font-bold text-white mb-2">مصاحبه، مددکاری و بورس کامل</h4>
                        <p class="text-xs text-gray-300 leading-relaxed">
                            ارزیابی انگیزه درونی، سرسختی و بررسی وضعیت معیشتی خانواده برای اهدای بورس کامل تحصیلی و آغاز همراهی چندساله با بنیاد حکمت.
                        </p>
                    </div>
                </div>

            </div>

        </div>
    </section>

    <!-- Interactive Test Engine Section -->
    <section id="test-section" class="py-20 relative overflow-hidden">
        <div class="container mx-auto px-4 max-w-4xl relative z-10">

            <!-- Step 1: Candidate Registration Box -->
            <div id="registration-card" class="diamond-card p-8 md:p-12 rounded-3xl shadow-2xl border border-teal-500/30">
                <div class="text-center mb-8">
                    <span class="inline-block p-3 rounded-2xl bg-teal-500/20 text-teal-300 mb-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        </svg>
                    </span>
                    <h3 class="text-2xl md:text-3xl font-black text-white mb-2">مشخصات داوطلب برای صدور کارنامه</h3>
                    <p class="text-gray-400 text-sm">لطفاً اطلاعات زیر را دقیق وارد کنید تا در صورت قبولی، بتوانیم با شما تماس بگیریم.</p>
                </div>

                <form id="reg-form" onsubmit="startTest(event)" class="space-y-6 max-w-xl mx-auto">
                    <div>
                        <label class="block text-sm font-bold text-teal-200 mb-2">نام و نام خانوادگی <span class="text-rose-400">*</span></label>
                        <input type="text" id="cand_name" required placeholder="مثال: علی رضایی" class="w-full bg-[#031b28] border border-teal-500/40 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-teal-400 text-sm">
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-teal-200 mb-2">شماره تلفن همراه (جهت پیامک نتایج) <span class="text-rose-400">*</span></label>
                        <input type="tel" id="cand_phone" required dir="ltr" placeholder="09123456789" class="w-full bg-[#031b28] border border-teal-500/40 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-teal-400 text-sm text-right">
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-bold text-teal-200 mb-2">پایه تحصیلی <span class="text-rose-400">*</span></label>
                            <select id="cand_grade" required class="w-full bg-[#031b28] border border-teal-500/40 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-teal-400 text-sm">
                                <option value="">انتخاب کنید...</option>
                                <option value="ششم ابتدایی">پایه ششم ابتدایی</option>
                                <option value="هفتم متوسطه اول">پایه هفتم (متوسطه اول)</option>
                                <option value="هشتم متوسطه اول">پایه هشتم (متوسطه اول)</option>
                                <option value="نهم متوسطه اول">پایه نهم (متوسطه اول)</option>
                                <option value="دهم متوسطه دوم">پایه دهم (متوسطه دوم)</option>
                                <option value="یازدهم متوسطه دوم">پایه یازدهم (متوسطه دوم)</option>
                                <option value="دوازدهم">پایه دوازدهم</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-bold text-teal-200 mb-2">شهر / استان <span class="text-rose-400">*</span></label>
                            <input type="text" id="cand_city" required placeholder="مثال: مشهد، تهران، حاشیه شهر..." class="w-full bg-[#031b28] border border-teal-500/40 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-teal-400 text-sm">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-teal-200 mb-2">نام مدرسه (اختیاری)</label>
                        <input type="text" id="cand_school" placeholder="مثال: شهید بهشتی، ابوریحان بیرونی..." class="w-full bg-[#031b28] border border-teal-500/40 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-teal-400 text-sm">
                    </div>

                    <!-- Integrity Warning Alert -->
                    <div class="p-4 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-200 text-xs leading-relaxed flex items-start gap-3">
                        <svg class="w-5 h-5 flex-shrink-0 text-amber-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                        <span>
                            <strong>توجه بسیار مهم:</strong> لطفاً آزمون را به تنهایی و بدون کمک دیگران حل کنید. با توجه به اینکه تمامی پذیرفته‌شدگان در مرحله بعد مورد آزمون راستی‌آزمایی حضوری قرار می‌گیرند، کمک دیگران باعث حذف قطعی خواهد شد.
                        </span>
                    </div>

                    <button type="submit" class="w-full py-4 bg-gradient-to-r from-teal-500 to-emerald-600 hover:from-teal-400 hover:to-emerald-500 text-white rounded-xl font-black text-lg shadow-lg transition-all transform hover:-translate-y-0.5">
                        شروع چالش ۱۵ دقیقه‌ای 💎
                    </button>
                </form>
            </div>

            <!-- Step 2: The Interactive Quiz Room (Hidden by default) -->
            <div id="quiz-card" class="hidden diamond-card p-6 md:p-10 rounded-3xl shadow-2xl border border-teal-500/40">
                
                <!-- Quiz Top Header: Timer & Progress -->
                <div class="flex items-center justify-between border-b border-teal-500/20 pb-6 mb-8">
                    <div>
                        <div class="text-xs text-gray-400 font-bold mb-1">پیشرفت آزمون</div>
                        <div class="text-lg md:text-xl font-black text-white">
                            سوال <span id="current-q-num" class="text-teal-400">۱</span> از <span class="text-gray-400">۱۵</span>
                        </div>
                    </div>

                    <!-- Per-Question Circular/Box Countdown -->
                    <div class="flex items-center gap-3">
                        <div class="text-right">
                            <div class="text-xs text-gray-400 font-bold">زمان این سوال</div>
                            <div id="timer-text" class="text-2xl font-black text-teal-300 font-mono tracking-wider">۴۵ ثانیه</div>
                        </div>
                        <div class="w-12 h-12 rounded-full border-4 border-teal-500/30 flex items-center justify-center relative overflow-hidden">
                            <div id="timer-indicator" class="absolute inset-0 bg-teal-500/20"></div>
                            <svg class="w-6 h-6 text-teal-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Progress Bar Line -->
                <div class="w-full bg-slate-800 h-2 rounded-full mb-8 overflow-hidden">
                    <div id="progress-bar-fill" class="bg-gradient-to-r from-teal-400 to-emerald-500 h-full w-0 transition-all duration-300"></div>
                </div>

                <!-- Question Prompt & Category -->
                <div class="text-center mb-8">
                    <span id="question-category" class="inline-block px-3 py-1 rounded-full bg-teal-500/10 text-teal-300 text-xs font-bold mb-3 border border-teal-500/20">
                        استدلال ماتریسی
                    </span>
                    <h4 id="question-title" class="text-lg md:text-xl font-bold text-white leading-relaxed">
                        کدام گزینه منطقاً جای علامت سوال (?) را کامل می‌کند؟
                    </h4>
                </div>

                <!-- Graphic/Visual Puzzle Display Container (SVGs) -->
                <div id="question-graphic-container" class="bg-[#031722] border border-teal-500/30 rounded-2xl p-6 mb-8 flex items-center justify-center min-h-[220px]">
                    <!-- Injected by JS -->
                </div>

                <!-- 4 Options Grid -->
                <div id="options-container" class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
                    <!-- Injected by JS -->
                </div>

                <!-- Action Button -->
                <div class="flex justify-between items-center pt-4 border-t border-teal-500/20">
                    <div class="text-xs text-gray-400">
                        * با پایان زمان، سوال بعدی به صورت خودکار نمایش داده می‌شود.
                    </div>
                    <button id="next-btn" onclick="submitCurrentAnswer()" class="px-8 py-3 bg-teal-600 hover:bg-teal-500 text-white rounded-xl font-black text-sm transition-all shadow-md">
                        ثبت و سوال بعدی &larr;
                    </button>
                </div>

            </div>

            <!-- Step 3: Result & Certificate Card (Hidden by default) -->
            <div id="result-card" class="hidden diamond-card p-8 md:p-12 rounded-3xl shadow-2xl border border-teal-500/40 text-center">
                <div class="w-20 h-20 rounded-full bg-gradient-to-tr from-teal-500 to-emerald-400 flex items-center justify-center mx-auto mb-6 text-white shadow-[0_0_30px_rgba(20,184,166,0.6)]">
                    <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                </div>

                <div class="inline-block px-4 py-1 rounded-full bg-teal-500/20 text-teal-300 text-xs font-bold mb-3 border border-teal-400/30">
                    کارنامه رسمی آزمون غربالگری
                </div>

                <h3 class="text-2xl md:text-4xl font-black text-white mb-2" id="result-cand-name">
                    دانش‌آموز گرامی
                </h3>

                <div class="text-4xl md:text-6xl font-black text-transparent bg-clip-text bg-gradient-to-r from-teal-300 via-emerald-300 to-cyan-400 my-6">
                    <span id="result-score">۰</span> <span class="text-2xl text-gray-400 font-normal">از ۱۵</span>
                </div>

                <div class="text-lg md:text-xl font-bold text-teal-300 mb-4" id="result-tier">
                    سطح پتانسیل شناختی
                </div>

                <p class="text-gray-300 text-sm md:text-base leading-relaxed max-w-xl mx-auto mb-8 text-justify md:text-center" id="result-message">
                    پاسخ‌های شما با موفقیت در سیستم مرکزی بنیاد حکمت ذخیره شد.
                </p>

                <!-- Next Steps Notification -->
                <div class="p-6 rounded-2xl bg-teal-950/40 border border-teal-500/30 max-w-xl mx-auto mb-8 text-right">
                    <h5 class="text-sm font-bold text-teal-300 mb-2 flex items-center gap-2">
                        <svg class="w-5 h-5 text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        گام بعدی چیست؟
                    </h5>
                    <p class="text-xs text-gray-300 leading-relaxed">
                        تیم سنجش و استعدادیابی بنیاد حکمت نتایج تمامی شرکت‌کنندگان را بررسی خواهد کرد. داوطلبانی که بالاترین عملکرد را داشته باشند، از طریق پیامک و تماس تلفنی برای حضور در <strong>«روز کشف استعداد و کارگاه حضوری»</strong> دعوت خواهند شد.
                    </p>
                </div>

                <!-- Share Buttons for Social Media -->
                <div class="border-t border-teal-500/20 pt-6">
                    <p class="text-xs text-gray-400 mb-4 font-bold">لینک این چالش را برای دوستان باهوش و بااستعدادت بفرست:</p>
                    <div class="flex flex-wrap justify-center gap-3">
                        <a href="https://eitaa.com/share/url?url=https://hekmatfoundation.org/almas.php&text=پویش ملی گنج‌های پنهان بنیاد حکمت؛ اگر حس می‌کنی باهوشی خودت رو در این چالش ۱۵ دقیقه‌ای بسنج و بورس شو!" target="_blank" class="px-4 py-2 bg-orange-600/80 hover:bg-orange-500 text-white rounded-xl text-xs font-bold transition-all">
                            اشتراک در ایتا
                        </a>
                        <a href="https://t.me/share/url?url=https://hekmatfoundation.org/almas.php&text=پویش ملی گنج‌های پنهان بنیاد حکمت" target="_blank" class="px-4 py-2 bg-blue-600/80 hover:bg-blue-500 text-white rounded-xl text-xs font-bold transition-all">
                            اشتراک در تلگرام
                        </a>
                        <button onclick="copyShareLink()" class="px-4 py-2 bg-slate-700 hover:bg-slate-600 text-white rounded-xl text-xs font-bold transition-all">
                            کپی لینک پویش
                        </button>
                    </div>
                </div>

            </div>

        </div>
    </section>

    <!-- Quiz Engine Script -->
    <script>
        // Standard 15 Pure Non-Verbal / Visual Questions
        const questionsData = [
            {
                id: 1,
                category: "استدلال ماتریسی ۲x۲ (رنگ و پرشدگی)",
                title: "کدام شکل رابطه سطر بالا را در سطر پایین کامل می‌کند؟",
                graphic: `<svg viewBox="0 0 320 160" class="w-full max-w-sm">
                    <rect x="20" y="20" width="120" height="120" rx="10" fill="#0f172a" stroke="#14b8a6" stroke-width="2"/>
                    <circle cx="80" cy="80" r="35" fill="none" stroke="#38bdf8" stroke-width="6"/>
                    <rect x="180" y="20" width="120" height="120" rx="10" fill="#0f172a" stroke="#14b8a6" stroke-width="2"/>
                    <circle cx="240" cy="80" r="35" fill="#38bdf8"/>
                </svg>
                <div class="my-3 text-center text-teal-400 font-black text-xl">↓</div>
                <svg viewBox="0 0 320 160" class="w-full max-w-sm">
                    <rect x="20" y="20" width="120" height="120" rx="10" fill="#0f172a" stroke="#14b8a6" stroke-width="2"/>
                    <rect x="45" y="45" width="70" height="70" fill="none" stroke="#f59e0b" stroke-width="6"/>
                    <rect x="180" y="20" width="120" height="120" rx="10" fill="#0f172a" stroke="#14b8a6" stroke-width="2" stroke-dasharray="4"/>
                    <text x="240" y="90" fill="#2dd4bf" font-size="40" font-weight="bold" text-anchor="middle">?</text>
                </svg>`,
                options: [
                    `<svg viewBox="0 0 100 100" class="w-16 h-16 mx-auto"><rect x="20" y="20" width="60" height="60" fill="none" stroke="#f59e0b" stroke-width="4"/></svg>`,
                    `<svg viewBox="0 0 100 100" class="w-16 h-16 mx-auto"><rect x="20" y="20" width="60" height="60" fill="#f59e0b"/></svg>`,
                    `<svg viewBox="0 0 100 100" class="w-16 h-16 mx-auto"><circle cx="50" cy="50" r="30" fill="#f59e0b"/></svg>`,
                    `<svg viewBox="0 0 100 100" class="w-16 h-16 mx-auto"><line x1="20" y1="20" x2="80" y2="80" stroke="#f59e0b" stroke-width="6"/></svg>`
                ],
                correct: 2
            },
            {
                id: 2,
                category: "توالی عددی هندسی (تعداد عناصر)",
                title: "تعداد لوزی‌ها در خانه مجهول باید چند عدد باشد؟",
                graphic: `<svg viewBox="0 0 320 160" class="w-full max-w-sm">
                    <rect x="20" y="20" width="120" height="120" rx="10" fill="#0f172a" stroke="#14b8a6" stroke-width="2"/>
                    <circle cx="80" cy="80" r="14" fill="#34d399"/>
                    <rect x="180" y="20" width="120" height="120" rx="10" fill="#0f172a" stroke="#14b8a6" stroke-width="2"/>
                    <circle cx="225" cy="80" r="14" fill="#34d399"/>
                    <circle cx="255" cy="80" r="14" fill="#34d399"/>
                </svg>
                <div class="my-3 text-center text-teal-400 font-black text-xl">↓</div>
                <svg viewBox="0 0 320 160" class="w-full max-w-sm">
                    <rect x="20" y="20" width="120" height="120" rx="10" fill="#0f172a" stroke="#14b8a6" stroke-width="2"/>
                    <polygon points="65,80 80,65 95,80 80,95" fill="#a855f7"/>
                    <polygon points="95,80 110,65 125,80 110,95" fill="#a855f7"/>
                    <rect x="180" y="20" width="120" height="120" rx="10" fill="#0f172a" stroke="#14b8a6" stroke-width="2" stroke-dasharray="4"/>
                    <text x="240" y="90" fill="#2dd4bf" font-size="40" font-weight="bold" text-anchor="middle">?</text>
                </svg>`,
                options: [
                    `<span class="text-sm font-bold text-gray-200">۱ لوزی بنفش</span>`,
                    `<span class="text-sm font-bold text-gray-200">۲ لوزی بنفش</span>`,
                    `<span class="text-sm font-bold text-gray-200">۳ لوزی بنفش</span>`,
                    `<span class="text-sm font-bold text-gray-200">۴ لوزی بنفش</span>`
                ],
                correct: 3
            },
            {
                id: 3,
                category: "رشد تعداد اضلاع (هوش فضایی)",
                title: "کدام شکل در دنباله زیر، گام چهارم است؟",
                graphic: `<svg viewBox="0 0 400 100" class="w-full max-w-md">
                    <g transform="translate(10, 10)">
                        <rect width="80" height="80" rx="8" fill="#0f172a" stroke="#14b8a6"/>
                        <polygon points="50,20 20,70 80,70" fill="#38bdf8"/>
                    </g>
                    <g transform="translate(110, 10)">
                        <rect width="80" height="80" rx="8" fill="#0f172a" stroke="#14b8a6"/>
                        <rect x="25" y="25" width="50" height="50" fill="#38bdf8"/>
                    </g>
                    <g transform="translate(210, 10)">
                        <rect width="80" height="80" rx="8" fill="#0f172a" stroke="#14b8a6"/>
                        <polygon points="50,15 80,38 68,75 32,75 20,38" fill="#38bdf8"/>
                    </g>
                    <g transform="translate(310, 10)">
                        <rect width="80" height="80" rx="8" fill="#0f172a" stroke="#14b8a6" stroke-dasharray="4"/>
                        <text x="50" y="55" fill="#2dd4bf" font-size="36" font-weight="bold" text-anchor="middle">?</text>
                    </g>
                </svg>`,
                options: [
                    `<svg viewBox="0 0 100 100" class="w-16 h-16 mx-auto"><circle cx="50" cy="50" r="30" fill="#38bdf8"/></svg>`,
                    `<svg viewBox="0 0 100 100" class="w-16 h-16 mx-auto"><polygon points="50,20 20,70 80,70" fill="#38bdf8"/></svg>`,
                    `<span class="text-xs font-bold text-gray-200">هشت‌ضلعی</span>`,
                    `<svg viewBox="0 0 100 100" class="w-16 h-16 mx-auto"><polygon points="50,15 80,32 80,68 50,85 20,68 20,32" fill="#38bdf8"/></svg>`
                ],
                correct: 4
            },
            {
                id: 4,
                category: "دوران فضایی در جهت عقربه‌های ساعت",
                title: "با چرخش ۹۰ درجه ساعتگرد فلش و نقطه، شکل چهارم کدام است؟",
                graphic: `<svg viewBox="0 0 400 100" class="w-full max-w-md">
                    <!-- Step 1: Up -->
                    <g transform="translate(10, 10)">
                        <rect width="80" height="80" rx="8" fill="#0f172a" stroke="#14b8a6"/>
                        <line x1="50" y1="65" x2="50" y2="25" stroke="#f43f5e" stroke-width="6" stroke-linecap="round"/>
                        <polygon points="50,15 38,30 62,30" fill="#f43f5e"/>
                        <circle cx="70" cy="50" r="6" fill="#facc15"/>
                    </g>
                    <!-- Step 2: Right -->
                    <g transform="translate(110, 10)">
                        <rect width="80" height="80" rx="8" fill="#0f172a" stroke="#14b8a6"/>
                        <line x1="25" y1="50" x2="65" y2="50" stroke="#f43f5e" stroke-width="6" stroke-linecap="round"/>
                        <polygon points="75,50 60,38 60,62" fill="#f43f5e"/>
                        <circle cx="50" cy="70" r="6" fill="#facc15"/>
                    </g>
                    <!-- Step 3: Down -->
                    <g transform="translate(210, 10)">
                        <rect width="80" height="80" rx="8" fill="#0f172a" stroke="#14b8a6"/>
                        <line x1="50" y1="25" x2="50" y2="65" stroke="#f43f5e" stroke-width="6" stroke-linecap="round"/>
                        <polygon points="50,75 38,60 62,60" fill="#f43f5e"/>
                        <circle cx="30" cy="50" r="6" fill="#facc15"/>
                    </g>
                    <!-- Step 4: Question -->
                    <g transform="translate(310, 10)">
                        <rect width="80" height="80" rx="8" fill="#0f172a" stroke="#14b8a6" stroke-dasharray="4"/>
                        <text x="50" y="55" fill="#2dd4bf" font-size="36" font-weight="bold" text-anchor="middle">?</text>
                    </g>
                </svg>`,
                options: [
                    `<svg viewBox="0 0 100 100" class="w-16 h-16 mx-auto"><line x1="75" y1="50" x2="35" y2="50" stroke="#f43f5e" stroke-width="6"/><polygon points="25,50 40,38 40,62" fill="#f43f5e"/><circle cx="50" cy="30" r="6" fill="#facc15"/></svg>`,
                    `<svg viewBox="0 0 100 100" class="w-16 h-16 mx-auto"><line x1="75" y1="50" x2="35" y2="50" stroke="#f43f5e" stroke-width="6"/><polygon points="25,50 40,38 40,62" fill="#f43f5e"/><circle cx="50" cy="70" r="6" fill="#facc15"/></svg>`,
                    `<svg viewBox="0 0 100 100" class="w-16 h-16 mx-auto"><line x1="50" y1="65" x2="50" y2="25" stroke="#f43f5e" stroke-width="6"/><polygon points="50,15 38,30 62,30" fill="#f43f5e"/></svg>`,
                    `<svg viewBox="0 0 100 100" class="w-16 h-16 mx-auto"><line x1="25" y1="50" x2="65" y2="50" stroke="#f43f5e" stroke-width="6"/><polygon points="75,50 60,38 60,62" fill="#f43f5e"/></svg>`
                ],
                correct: 1
            },
            {
                id: 5,
                category: "ترکیب منطقی خطوط (Overlay)",
                title: "اگر دو خط مورب با هم ترکیب شوند، حاصل کدام است؟",
                graphic: `<div class="flex items-center gap-4 text-white text-2xl font-black">
                    <svg viewBox="0 0 80 80" class="w-16 h-16"><rect width="80" height="80" rx="8" fill="#0f172a" stroke="#14b8a6"/><line x1="15" y1="65" x2="65" y2="15" stroke="#38bdf8" stroke-width="6"/></svg>
                    <span>+</span>
                    <svg viewBox="0 0 80 80" class="w-16 h-16"><rect width="80" height="80" rx="8" fill="#0f172a" stroke="#14b8a6"/><line x1="15" y1="15" x2="65" y2="65" stroke="#38bdf8" stroke-width="6"/></svg>
                    <span>=</span>
                    <svg viewBox="0 0 80 80" class="w-16 h-16"><rect width="80" height="80" rx="8" fill="#0f172a" stroke="#14b8a6" stroke-dasharray="4"/><text x="40" y="52" fill="#2dd4bf" font-size="34" font-weight="bold" text-anchor="middle">?</text></svg>
                </div>`,
                options: [
                    `<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><line x1="40" y1="10" x2="40" y2="70" stroke="#38bdf8" stroke-width="6"/><line x1="10" y1="40" x2="70" y2="40" stroke="#38bdf8" stroke-width="6"/></svg>`,
                    `<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><circle cx="40" cy="40" r="28" fill="none" stroke="#38bdf8" stroke-width="6"/></svg>`,
                    `<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><line x1="15" y1="15" x2="65" y2="65" stroke="#38bdf8" stroke-width="6"/><line x1="15" y1="65" x2="65" y2="15" stroke="#38bdf8" stroke-width="6"/></svg>`,
                    `<svg viewBox="0 0 80 80" class="w-16 h-16 mx-auto"><rect x="15" y="15" width="50" height="50" fill="none" stroke="#38bdf8" stroke-width="6"/></svg>`
                ],
                correct: 3
            },
            {
                id: 6,
                category: "حرکت قطری در شبکه ۳x۳",
                title: "خانه رنگ‌شده در جدول سوم در کدام نقطه قرار می‌گیرد؟",
                graphic: `<svg viewBox="0 0 360 110" class="w-full max-w-sm">
                    <!-- Grid 1 -->
                    <g transform="translate(10, 10)">
                        <rect width="90" height="90" fill="#0f172a" stroke="#14b8a6" stroke-width="2"/>
                        <line x1="30" y1="0" x2="30" y2="90" stroke="#334155"/>
                        <line x1="60" y1="0" x2="60" y2="90" stroke="#334155"/>
                        <line x1="0" y1="30" x2="90" y2="30" stroke="#334155"/>
                        <line x1="0" y1="60" x2="90" y2="60" stroke="#334155"/>
                        <rect x="0" y="0" width="30" height="30" fill="#2dd4bf"/>
                    </g>
                    <!-- Grid 2 -->
                    <g transform="translate(135, 10)">
                        <rect width="90" height="90" fill="#0f172a" stroke="#14b8a6" stroke-width="2"/>
                        <line x1="30" y1="0" x2="30" y2="90" stroke="#334155"/>
                        <line x1="60" y1="0" x2="60" y2="90" stroke="#334155"/>
                        <line x1="0" y1="30" x2="90" y2="30" stroke="#334155"/>
                        <line x1="0" y1="60" x2="90" y2="60" stroke="#334155"/>
                        <rect x="30" y="30" width="30" height="30" fill="#2dd4bf"/>
                    </g>
                    <!-- Grid 3 (Target) -->
                    <g transform="translate(260, 10)">
                        <rect width="90" height="90" fill="#0f172a" stroke="#14b8a6" stroke-width="2" stroke-dasharray="4"/>
                        <text x="45" y="55" fill="#2dd4bf" font-size="34" font-weight="bold" text-anchor="middle">?</text>
                    </g>
                </svg>`,
                options: [
                    `<span class="text-xs font-bold text-gray-200">گوشه بالا-راست</span>`,
                    `<span class="text-xs font-bold text-gray-200">گوشه پایین-راست</span>`,
                    `<span class="text-xs font-bold text-gray-200">گوشه پایین-چپ</span>`,
                    `<span class="text-xs font-bold text-gray-200">مرکز بالا</span>`
                ],
                correct: 2
            },
            {
                id: 7,
                category: "وارونگی تو در تو (Concentric Inversion)",
                title: "اگر دایره در مربع تبدیل به مربع در دایره شود، لوزی در مثلث به چه تبدیل می‌شود؟",
                graphic: `<div class="flex items-center gap-4 text-white text-xl font-bold">
                    <svg viewBox="0 0 80 80" class="w-16 h-16"><rect x="10" y="10" width="60" height="60" fill="none" stroke="#38bdf8" stroke-width="4"/><circle cx="40" cy="40" r="16" fill="#f43f5e"/></svg>
                    <span>→</span>
                    <svg viewBox="0 0 80 80" class="w-16 h-16"><circle cx="40" cy="40" r="30" fill="none" stroke="#f43f5e" stroke-width="4"/><rect x="25" y="25" width="30" height="30" fill="#38bdf8"/></svg>
                    <span class="text-teal-400">|</span>
                    <svg viewBox="0 0 80 80" class="w-16 h-16"><polygon points="40,10 10,70 70,70" fill="none" stroke="#a855f7" stroke-width="4"/><polygon points="40,32 52,47 40,62 28,47" fill="#facc15"/></svg>
                    <span>→</span>
                    <span class="text-teal-300 text-3xl font-black">?</span>
                </div>`,
                options: [
                    `<span class="text-xs font-bold text-gray-200">مثلث در دایره</span>`,
                    `<span class="text-xs font-bold text-gray-200">مربع در لوزی</span>`,
                    `<span class="text-xs font-bold text-gray-200">دایره در مثلث</span>`,
                    `<span class="text-xs font-bold text-gray-200">مثلث درون لوزی</span>`
                ],
                correct: 4
            },
            {
                id: 8,
                category: "تقارن آینه‌ای عمودی (Mirror Symmetry)",
                title: "تصویر آینه‌ای این پرچم کدام گزینه است؟",
                graphic: `<svg viewBox="0 0 200 120" class="w-48 h-28 mx-auto">
                    <!-- Object -->
                    <line x1="70" y1="20" x2="70" y2="100" stroke="#f8fafc" stroke-width="4"/>
                    <polygon points="70,20 120,40 70,60" fill="#38bdf8"/>
                    <circle cx="85" cy="85" r="8" fill="#facc15"/>
                    <!-- Mirror Line -->
                    <line x1="145" y1="10" x2="145" y2="110" stroke="#64748b" stroke-width="2" stroke-dasharray="4"/>
                    <text x="175" y="70" fill="#2dd4bf" font-size="34" font-weight="bold">?</text>
                </svg>`,
                options: [
                    `<svg viewBox="0 0 100 100" class="w-16 h-16 mx-auto"><line x1="60" y1="15" x2="60" y2="85" stroke="#fff" stroke-width="4"/><polygon points="60,15 15,35 60,55" fill="#38bdf8"/><circle cx="45" cy="72" r="7" fill="#facc15"/></svg>`,
                    `<svg viewBox="0 0 100 100" class="w-16 h-16 mx-auto"><line x1="40" y1="15" x2="40" y2="85" stroke="#fff" stroke-width="4"/><polygon points="40,15 85,35 40,55" fill="#38bdf8"/><circle cx="55" cy="72" r="7" fill="#facc15"/></svg>`,
                    `<svg viewBox="0 0 100 100" class="w-16 h-16 mx-auto"><line x1="60" y1="15" x2="60" y2="85" stroke="#fff" stroke-width="4"/><polygon points="60,85 15,65 60,45" fill="#38bdf8"/></svg>`,
                    `<svg viewBox="0 0 100 100" class="w-16 h-16 mx-auto"><line x1="50" y1="15" x2="50" y2="85" stroke="#fff" stroke-width="4"/><circle cx="50" cy="50" r="20" fill="#facc15"/></svg>`
                ],
                correct: 1
            },
            {
                id: 9,
                category: "منطق تعادل و وزن شهودی (Scales)",
                title: "اگر ۱ دایره = ۲ مثلث، و ۱ مثلث = ۲ مربع باشد؛ ۱ دایره با چند مربع تعادل دارد؟",
                graphic: `<div class="space-y-2 text-white text-sm md:text-base font-bold text-center">
                    <div class="p-2 rounded-xl bg-slate-900 border border-teal-500/20">ترازوی ۱: 🔵 = 🔺 🔺</div>
                    <div class="p-2 rounded-xl bg-slate-900 border border-teal-500/20">ترازوی ۲: 🔺 = 🟩 🟩</div>
                    <div class="p-2 rounded-xl bg-teal-950/60 border border-teal-400 text-teal-300">ترازوی ۳: 🔵 = ؟</div>
                </div>`,
                options: [
                    `<span class="text-sm font-bold text-gray-200">۲ مربع (🟩🟩)</span>`,
                    `<span class="text-sm font-bold text-gray-200">۳ مربع (🟩🟩🟩)</span>`,
                    `<span class="text-sm font-bold text-gray-200">۴ مربع (🟩🟩🟩🟩)</span>`,
                    `<span class="text-sm font-bold text-gray-200">۶ مربع (🟩🟩🟩🟩🟩🟩)</span>`
                ],
                correct: 3
            },
            {
                id: 10,
                category: "استدلال تا کردن مکعب سه‌بعدی",
                title: "اگر یک مکعب بازشده شامل دایره، ستاره و مربع را تا کنیم، کدام مکعب ساخته می‌شود؟",
                graphic: `<svg viewBox="0 0 160 120" class="w-36 h-28 mx-auto">
                    <rect x="50" y="10" width="30" height="30" fill="#1e293b" stroke="#38bdf8"/>
                    <circle cx="65" cy="25" r="8" fill="#facc15"/>
                    <rect x="20" y="40" width="30" height="30" fill="#1e293b" stroke="#38bdf8"/>
                    <text x="35" y="60" fill="#fff" font-size="14" text-anchor="middle">★</text>
                    <rect x="50" y="40" width="30" height="30" fill="#1e293b" stroke="#38bdf8"/>
                    <rect x="58" y="48" width="14" height="14" fill="#38bdf8"/>
                    <rect x="80" y="40" width="30" height="30" fill="#1e293b" stroke="#38bdf8"/>
                    <rect x="50" y="70" width="30" height="30" fill="#1e293b" stroke="#38bdf8"/>
                </svg>`,
                options: [
                    `<span class="text-xs font-bold text-gray-200">مکعبی که دایره و ستاره روبروی هم باشند</span>`,
                    `<span class="text-xs font-bold text-gray-200">مکعبی با ۳ وجه مجاور (دایره، مربع، ستاره)</span>`,
                    `<span class="text-xs font-bold text-gray-200">مکعبی بدون وجوه خالی</span>`,
                    `<span class="text-xs font-bold text-gray-200">مکعبی با دو ستاره</span>`
                ],
                correct: 2
            },
            {
                id: 11,
                category: "ماتریس پیشرفته ۳x۳ (اندازه و بافت)",
                title: "کدام شکل در گوشه پایین ماتریس ۳x۳ قرار می‌گیرد؟",
                graphic: `<div class="grid grid-cols-3 gap-2 w-48 mx-auto bg-slate-900 p-3 rounded-xl border border-teal-500/30">
                    <div class="h-12 flex items-center justify-center border border-slate-700 rounded"><circle cx="10" cy="10" r="6" fill="#38bdf8"/></div>
                    <div class="h-12 flex items-center justify-center border border-slate-700 rounded"><circle cx="10" cy="10" r="10" fill="#38bdf8"/></div>
                    <div class="h-12 flex items-center justify-center border border-slate-700 rounded"><circle cx="10" cy="10" r="14" fill="#38bdf8"/></div>
                    
                    <div class="h-12 flex items-center justify-center border border-slate-700 rounded"><polygon points="20,10 10,25 30,25" fill="#f43f5e"/></div>
                    <div class="h-12 flex items-center justify-center border border-slate-700 rounded"><polygon points="20,5 5,30 35,30" fill="#f43f5e"/></div>
                    <div class="h-12 flex items-center justify-center border border-slate-700 rounded"><polygon points="20,2 2,36 38,36" fill="#f43f5e"/></div>
                    
                    <div class="h-12 flex items-center justify-center border border-slate-700 rounded"><rect x="14" y="14" width="12" height="12" fill="#2dd4bf"/></div>
                    <div class="h-12 flex items-center justify-center border border-slate-700 rounded"><rect x="10" y="10" width="20" height="20" fill="#2dd4bf"/></div>
                    <div class="h-12 flex items-center justify-center border border-teal-400 bg-teal-500/20 rounded font-black text-teal-300 text-xl">?</div>
                </div>`,
                options: [
                    `<span class="text-xs font-bold text-gray-200">مربع کوچک</span>`,
                    `<span class="text-xs font-bold text-gray-200">دایره بزرگ</span>`,
                    `<span class="text-xs font-bold text-gray-200">مثلث متوسط</span>`,
                    `<span class="text-xs font-bold text-gray-200">مربع بزرگ</span>`
                ],
                correct: 4
            },
            {
                id: 12,
                category: "تشخیص ناهماهنگ (Odd-One-Out)",
                title: "کدام گزینه از نظر پیوستگی خطوط با بقیه متفاوت است؟",
                graphic: `<div class="text-gray-300 text-sm">۴ شکل با یک خط پیوسته بسته رسم می‌شوند، اما یکی از آن‌ها دارای دو شکل مجزا و جدا از هم است.</div>`,
                options: [
                    `<span class="text-xs font-bold text-gray-200">دایره ساده</span>`,
                    `<span class="text-xs font-bold text-gray-200">مثلث بسته</span>`,
                    `<span class="text-xs font-bold text-teal-300">دو مربع کاملاً جدا از هم</span>`,
                    `<span class="text-xs font-bold text-gray-200">ستاره ۵ پر بسته</span>`
                ],
                correct: 3
            },
            {
                id: 13,
                category: "جهت دوران چرخ‌دنده‌ها (Mechanical Logic)",
                title: "اگر چرخ‌دنده ۱ در جهت ساعتگرد بچرخد و به چرخ‌دنده ۲ وصل باشد، و چرخ‌دنده ۲ به ۳ وصل باشد؛ چرخ‌دنده ۳ چگونه می‌چرخد؟",
                graphic: `<div class="flex items-center justify-center gap-3 text-white text-base font-bold">
                    <div class="p-3 bg-slate-900 border border-teal-500/40 rounded-xl">⚙️ ۱ (ساعتگرد)</div>
                    <span>→</span>
                    <div class="p-3 bg-slate-900 border border-slate-700 rounded-xl">⚙️ ۲</div>
                    <span>→</span>
                    <div class="p-3 bg-teal-950 border border-teal-400 rounded-xl text-teal-300">⚙️ ۳ (؟)</div>
                </div>`,
                options: [
                    `<span class="text-xs font-bold text-gray-200">در جهت عقربه‌های ساعت (ساعتگرد)</span>`,
                    `<span class="text-xs font-bold text-gray-200">خلاف جهت عقربه‌های ساعت (پادساعتگرد)</span>`,
                    `<span class="text-xs font-bold text-gray-200">اصلاً نمی‌چرخد</span>`,
                    `<span class="text-xs font-bold text-gray-200">جهت آن متناوباً تغییر می‌کند</span>`
                ],
                correct: 1
            },
            {
                id: 14,
                category: "عملیات حذف خطوط مشترک (XOR Matrix)",
                title: "در ماتریس ریون، خطوط مشترک در دو شکل حذف می‌شوند. شکل نهایی کدام است؟",
                graphic: `<div class="flex items-center justify-center gap-3 text-white text-xl font-bold">
                    <div class="p-3 bg-slate-900 border border-slate-700 rounded-xl">شکل ۱: [ + و | ]</div>
                    <span>XOR</span>
                    <div class="p-3 bg-slate-900 border border-slate-700 rounded-xl">شکل ۲: [ | ]</div>
                    <span>=</span>
                    <div class="p-3 bg-teal-950 border border-teal-400 rounded-xl text-teal-300">؟</div>
                </div>`,
                options: [
                    `<span class="text-xs font-bold text-gray-200">علامت مثبت کامل (+)</span>`,
                    `<span class="text-xs font-bold text-gray-200">خط عمودی (|)</span>`,
                    `<span class="text-xs font-bold text-gray-200">مربع خالی</span>`,
                    `<span class="text-xs font-bold text-gray-200">تنها خط افقی (-)</span>`
                ],
                correct: 4
            },
            {
                id: 15,
                category: "تبدیل چندمتغیره سطح پیشرفته",
                title: "قانون: ۱۸۰ درجه دوران + معکوس شدن رنگ‌ها (سیاه به سفید و برعکس). گزینه درست کدام است؟",
                graphic: `<div class="flex items-center justify-center gap-4 text-white text-base font-bold">
                    <svg viewBox="0 0 100 100" class="w-20 h-20"><circle cx="50" cy="50" r="40" fill="#0f172a" stroke="#14b8a6" stroke-width="4"/><polygon points="50,20 30,50 70,50" fill="#facc15"/><circle cx="50" cy="70" r="8" fill="#fff"/></svg>
                    <span class="text-2xl text-teal-400">→</span>
                    <div class="w-20 h-20 rounded-xl border-2 border-dashed border-teal-400 flex items-center justify-center text-teal-300 font-black text-2xl">?</div>
                </div>`,
                options: [
                    `<span class="text-xs font-bold text-gray-200">همان شکل بدون تغییر</span>`,
                    `<span class="text-xs font-bold text-teal-300">مثلث وارونه در پایین با دایره سفید در بالا</span>`,
                    `<span class="text-xs font-bold text-gray-200">تنها تغییر رنگ بدون چرخش</span>`,
                    `<span class="text-xs font-bold text-gray-200">حذف مثلث</span>`
                ],
                correct: 2
            }
        ];

        let candidateInfo = {};
        let currentQuestionIdx = 0;
        let selectedOption = null;
        let userAnswers = {};
        let questionTimerInterval = null;
        let questionSecondsLeft = 45;
        let totalTestStartTime = null;

        function startTest(e) {
            e.preventDefault();
            candidateInfo = {
                full_name: document.getElementById('cand_name').value.trim(),
                phone: document.getElementById('cand_phone').value.trim(),
                grade: document.getElementById('cand_grade').value,
                city: document.getElementById('cand_city').value.trim(),
                school_name: document.getElementById('cand_school').value.trim()
            };

            // Switch view
            document.getElementById('registration-card').classList.add('hidden');
            document.getElementById('quiz-card').classList.remove('hidden');

            totalTestStartTime = Date.now();
            currentQuestionIdx = 0;
            loadQuestion(0);

            // Smooth scroll to top of quiz card
            document.getElementById('test-section').scrollIntoView({ behavior: 'smooth' });
        }

        function loadQuestion(idx) {
            if (idx >= questionsData.length) {
                finishQuiz();
                return;
            }

            currentQuestionIdx = idx;
            const q = questionsData[idx];

            document.getElementById('current-q-num').innerText = (idx + 1);
            document.getElementById('question-category').innerText = q.category;
            document.getElementById('question-title').innerText = q.title;
            document.getElementById('question-graphic-container').innerHTML = q.graphic;

            // Progress bar
            const percent = ((idx) / questionsData.length) * 100;
            document.getElementById('progress-bar-fill').style.width = percent + '%';

            // Options
            const optsContainer = document.getElementById('options-container');
            optsContainer.innerHTML = '';
            selectedOption = null;

            q.options.forEach((optContent, optIdx) => {
                const optNum = optIdx + 1;
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'option-btn p-4 rounded-2xl bg-slate-900/90 border border-slate-700 text-center transition-all flex flex-col items-center justify-center min-h-[90px] relative';
                btn.innerHTML = `
                    <div class="absolute top-2 right-3 text-xs font-black text-gray-400">گزینه ${optNum}</div>
                    <div class="mt-4">${optContent}</div>
                `;
                btn.onclick = () => selectOption(optNum, btn);
                optsContainer.appendChild(btn);
            });

            // Reset Timer (45s per question)
            resetPerQuestionTimer();
        }

        function selectOption(optNum, element) {
            selectedOption = optNum;
            document.querySelectorAll('.option-btn').forEach(b => b.classList.remove('selected'));
            element.classList.add('selected');
        }

        function resetPerQuestionTimer() {
            if (questionTimerInterval) clearInterval(questionTimerInterval);
            questionSecondsLeft = 45;
            updateTimerDisplay();

            questionTimerInterval = setInterval(() => {
                questionSecondsLeft--;
                updateTimerDisplay();

                if (questionSecondsLeft <= 0) {
                    clearInterval(questionTimerInterval);
                    submitCurrentAnswer(); // Auto advance when time runs out!
                }
            }, 1000);
        }

        function updateTimerDisplay() {
            const timerEl = document.getElementById('timer-text');
            timerEl.innerText = questionSecondsLeft + ' ثانیه';
            
            if (questionSecondsLeft <= 10) {
                timerEl.classList.remove('text-teal-300');
                timerEl.classList.add('text-rose-400', 'pulse-timer');
            } else {
                timerEl.classList.add('text-teal-300');
                timerEl.classList.remove('text-rose-400', 'pulse-timer');
            }
        }

        function submitCurrentAnswer() {
            if (questionTimerInterval) clearInterval(questionTimerInterval);

            // Record answer (1-indexed question ID)
            const qId = questionsData[currentQuestionIdx].id;
            userAnswers[qId] = selectedOption !== null ? selectedOption : 0;

            currentQuestionIdx++;
            loadQuestion(currentQuestionIdx);
        }

        function finishQuiz() {
            if (questionTimerInterval) clearInterval(questionTimerInterval);

            const totalSecondsSpent = Math.round((Date.now() - totalTestStartTime) / 1000);

            // Show loading state
            document.getElementById('quiz-card').innerHTML = `
                <div class="py-16 text-center">
                    <div class="w-16 h-16 border-4 border-teal-400 border-t-transparent rounded-full animate-spin mx-auto mb-6"></div>
                    <h3 class="text-2xl font-bold text-white mb-2">در حال تحلیل هوش و پردازش پاسخ‌ها...</h3>
                    <p class="text-gray-400 text-sm">لطفاً چند لحظه شکیبا باشید.</p>
                </div>
            `;

            const payload = {
                full_name: candidateInfo.full_name,
                phone: candidateInfo.phone,
                grade: candidateInfo.grade,
                city: candidateInfo.city,
                school_name: candidateInfo.school_name,
                time_spent_seconds: totalSecondsSpent,
                answers: userAnswers
            };

            fetch('api-diamond-submit.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(data => {
                document.getElementById('quiz-card').classList.add('hidden');
                document.getElementById('result-card').classList.remove('hidden');

                if (data.success) {
                    document.getElementById('result-cand-name').innerText = candidateInfo.full_name + ' عزیز';
                    document.getElementById('result-score').innerText = data.score;
                    document.getElementById('result-tier').innerText = data.tier;
                    document.getElementById('result-message').innerText = data.message;
                } else {
                    document.getElementById('result-message').innerText = data.message || 'خطا در ثبت نهایی.';
                }
                
                document.getElementById('test-section').scrollIntoView({ behavior: 'smooth' });
            })
            .catch(err => {
                console.error(err);
                document.getElementById('quiz-card').classList.add('hidden');
                document.getElementById('result-card').classList.remove('hidden');
                document.getElementById('result-message').innerText = 'خطای ارتباط با سرور. نتیجه در حافظه ذخیره شد.';
            });
        }

        function copyShareLink() {
            const url = window.location.origin + '/almas.php';
            navigator.clipboard.writeText(url).then(() => {
                alert('پیوند پویش با موفقیت کپی شد! می‌توانید آن را در شاد یا سایر پیام‌رسان‌ها بفرستید.');
            });
        }
    </script>

    <!-- Footer -->
    <footer class="bg-black/90 text-gray-400 py-12 border-t border-teal-500/20 text-center text-xs">
        <div class="container mx-auto px-4">
            <p class="mb-2 font-bold text-gray-200">بنیاد نیکوکاری حکمت (شماره ثبت ۷۷۰۷)</p>
            <p class="opacity-70">طرح کشف گنج‌های پنهان؛ حامی دانش‌آموزان بااستعداد و کم‌برخوردار سراسر ایران.</p>
        </div>
    </footer>

</body>
</html>
"""

with open('almas.php', 'w', encoding='utf-8') as f:
    f.write(almas_code)

print("Created almas.php successfully!")
