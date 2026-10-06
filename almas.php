<?php
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
                        <label class="block text-sm font-bold text-teal-200 mb-2">کد ملی ۱۰ رقمی (جهت احراز هویت و کارنامه یکتا) <span class="text-rose-400">*</span></label>
                        <input type="text" id="cand_national_id" required maxlength="10" dir="ltr" placeholder="مثال: 0123456789" class="w-full bg-[#031b28] border border-teal-500/40 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-teal-400 text-sm text-right font-mono tracking-widest">
                        <p id="nat-id-error" class="text-xs text-rose-400 mt-1.5 hidden font-bold">⚠️ کد ملی وارد شده نامعتبر است (باید ۱۰ رقم معتبر بر اساس ثبت‌احوال باشد).</p>
                    </div>

                    <div>
                        <label class="block text-sm font-bold text-teal-200 mb-2">شماره تلفن همراه (جهت پیامک نتایج) <span class="text-rose-400">*</span></label>
                        <input type="tel" id="cand_phone" required dir="ltr" placeholder="09123456789" class="w-full bg-[#031b28] border border-teal-500/40 rounded-xl px-4 py-3 text-white focus:outline-none focus:border-teal-400 text-sm text-right font-mono">
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

                    <!-- Already Participated Warning Alert (Shown if National ID / Phone already exists) -->
                    <div id="already-participated-card" class="hidden p-5 rounded-2xl bg-rose-500/20 border-2 border-rose-500/50 text-rose-200 text-sm leading-relaxed shadow-xl animate-pulse">
                        <div class="flex items-center gap-2.5 text-rose-300 font-black text-base mb-2">
                            <span class="text-xl">⛔</span>
                            <span>عدم امکان شرکت مجدد در آزمون</span>
                        </div>
                        <p id="already-participated-text" class="leading-relaxed font-bold text-justify"></p>
                        <div class="mt-3 pt-3 border-t border-rose-500/30 text-xs text-rose-300">
                            طبق آیین‌نامه پویش کشف گنج‌های پنهان بنیاد حکمت، هر داوطلب صرفاً ۱ بار مجاز به شرکت در چالش است.
                        </div>
                    </div>

                    <button type="submit" id="btn-start-quiz" class="w-full py-4 bg-gradient-to-r from-teal-500 to-emerald-600 hover:from-teal-400 hover:to-emerald-500 text-white rounded-xl font-black text-lg shadow-lg transition-all transform hover:-translate-y-0.5 cursor-pointer flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed">
                        <span id="btn-start-quiz-text">شروع چالش ۱۵ دقیقه‌ای 💎</span>
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
                        "id": 1,
                        "category": "ماتریس ۲×۲: تبدیل پوسته به پرشدگی",
                        "title": "کدام گزینه، جای خالی (؟) در ماتریس بالا را به درستی کامل می‌کند؟",
                        "graphic": "<div class=\"inline-grid grid-cols-2 gap-3 p-3 bg-slate-900/80 rounded-2xl border border-teal-500/30 shadow-inner max-w-xs mx-auto\">\n            <div class=\"w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center\">\n                <svg viewBox=\"0 0 80 80\" class=\"w-16 h-16\"><circle cx=\"40\" cy=\"40\" r=\"28\" fill=\"none\" stroke=\"#38bdf8\" stroke-width=\"5\"/></svg>\n            </div>\n            <div class=\"w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center\">\n                <svg viewBox=\"0 0 80 80\" class=\"w-16 h-16\"><circle cx=\"40\" cy=\"40\" r=\"28\" fill=\"#38bdf8\"/></svg>\n            </div>\n            <div class=\"w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center\">\n                <svg viewBox=\"0 0 80 80\" class=\"w-16 h-16\"><rect x=\"15\" y=\"15\" width=\"50\" height=\"50\" fill=\"none\" stroke=\"#fbbf24\" stroke-width=\"5\" rx=\"4\"/></svg>\n            </div>\n            <div class=\"w-24 h-24 bg-teal-950/40 rounded-xl border-2 border-dashed border-teal-400/60 flex items-center justify-center text-teal-300 text-3xl font-black shadow-[0_0_15px_rgba(45,212,191,0.2)]\">\n                ?\n            </div>\n        </div>",
                        "options": [
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><rect x=\"15\" y=\"15\" width=\"50\" height=\"50\" fill=\"none\" stroke=\"#fbbf24\" stroke-width=\"5\" rx=\"4\"/></svg>",
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><rect x=\"15\" y=\"15\" width=\"50\" height=\"50\" fill=\"#fbbf24\" rx=\"4\"/></svg>",
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><polygon points=\"40,15 15,65 65,65\" fill=\"#fbbf24\"/></svg>",
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><circle cx=\"40\" cy=\"40\" r=\"25\" fill=\"#fbbf24\"/></svg>"
                        ],
                        "correct": 2
            },
            {
                        "id": 2,
                        "category": "ماتریس ۲×۲: تصاعد شمارش عناصر",
                        "title": "کدام گزینه، جای خالی (؟) در ماتریس بالا را به درستی کامل می‌کند؟",
                        "graphic": "<div class=\"inline-grid grid-cols-2 gap-3 p-3 bg-slate-900/80 rounded-2xl border border-teal-500/30 shadow-inner max-w-xs mx-auto\">\n            <div class=\"w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center\">\n                <svg viewBox=\"0 0 80 80\" class=\"w-16 h-16\"><circle cx=\"40\" cy=\"40\" r=\"12\" fill=\"#34d399\"/></svg>\n            </div>\n            <div class=\"w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center\">\n                <svg viewBox=\"0 0 80 80\" class=\"w-16 h-16\"><circle cx=\"26\" cy=\"40\" r=\"10\" fill=\"#34d399\"/><circle cx=\"54\" cy=\"40\" r=\"10\" fill=\"#34d399\"/></svg>\n            </div>\n            <div class=\"w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center\">\n                <svg viewBox=\"0 0 80 80\" class=\"w-16 h-16\"><polygon points=\"26,30 36,40 26,50 16,40\" fill=\"#c084fc\"/><polygon points=\"54,30 64,40 54,50 44,40\" fill=\"#c084fc\"/></svg>\n            </div>\n            <div class=\"w-24 h-24 bg-teal-950/40 rounded-xl border-2 border-dashed border-teal-400/60 flex items-center justify-center text-teal-300 text-3xl font-black shadow-[0_0_15px_rgba(45,212,191,0.2)]\">\n                ?\n            </div>\n        </div>",
                        "options": [
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><polygon points=\"40,28 52,40 40,52 28,40\" fill=\"#c084fc\"/></svg>",
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><polygon points=\"26,30 36,40 26,50 16,40\" fill=\"#c084fc\"/><polygon points=\"54,30 64,40 54,50 44,40\" fill=\"#c084fc\"/></svg>",
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><polygon points=\"18,32 26,40 18,48 10,40\" fill=\"#c084fc\"/><polygon points=\"40,32 48,40 40,48 32,40\" fill=\"#c084fc\"/><polygon points=\"62,32 70,40 62,48 54,40\" fill=\"#c084fc\"/></svg>",
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><circle cx=\"20\" cy=\"40\" r=\"8\" fill=\"#c084fc\"/><circle cx=\"40\" cy=\"40\" r=\"8\" fill=\"#c084fc\"/><circle cx=\"60\" cy=\"40\" r=\"8\" fill=\"#c084fc\"/><circle cx=\"40\" cy=\"20\" r=\"8\" fill=\"#c084fc\"/></svg>"
                        ],
                        "correct": 3
            },
            {
                        "id": 3,
                        "category": "دنباله هندسی: افزایش اضلاع (Raven Set C)",
                        "title": "کدام شکل هندسی، گام چهارم از این دنباله قانون‌مند است؟",
                        "graphic": "<div class=\"grid grid-cols-4 gap-2 p-3 bg-slate-900/80 rounded-2xl border border-teal-500/30 max-w-sm mx-auto\">\n            <div class=\"h-20 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center\">\n                <svg viewBox=\"0 0 60 60\" class=\"w-12 h-12\"><polygon points=\"30,12 10,48 50,48\" fill=\"#38bdf8\"/></svg>\n            </div>\n            <div class=\"h-20 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center\">\n                <svg viewBox=\"0 0 60 60\" class=\"w-12 h-12\"><rect x=\"14\" y=\"14\" width=\"32\" height=\"32\" fill=\"#38bdf8\" rx=\"2\"/></svg>\n            </div>\n            <div class=\"h-20 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center\">\n                <svg viewBox=\"0 0 60 60\" class=\"w-12 h-12\"><polygon points=\"30,10 50,25 42,48 18,48 10,25\" fill=\"#38bdf8\"/></svg>\n            </div>\n            <div class=\"h-20 bg-teal-950/40 rounded-xl border-2 border-dashed border-teal-400/60 flex items-center justify-center text-teal-300 text-2xl font-black\">\n                ?\n            </div>\n        </div>",
                        "options": [
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><circle cx=\"40\" cy=\"40\" r=\"26\" fill=\"#38bdf8\"/></svg>",
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><polygon points=\"40,12 60,20 68,40 60,60 40,68 20,60 12,40 20,20\" fill=\"#38bdf8\"/></svg>",
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><path d=\"M30 15 H50 V30 H65 V50 H50 V65 H30 V50 H15 V30 H30 Z\" fill=\"#38bdf8\"/></svg>",
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><polygon points=\"40,12 64,26 64,54 40,68 16,54 16,26\" fill=\"#38bdf8\"/></svg>"
                        ],
                        "correct": 4
            },
            {
                        "id": 4,
                        "category": "دوران فضایی: چرخش ۹۰ درجه ساعتگرد",
                        "title": "کدام گزینه، جای خالی (؟) در ماتریس جهت‌ها را به درستی کامل می‌کند؟",
                        "graphic": "<div class=\"inline-grid grid-cols-2 gap-3 p-3 bg-slate-900/80 rounded-2xl border border-teal-500/30 shadow-inner max-w-xs mx-auto\">\n            <div class=\"w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center\">\n                <svg viewBox=\"0 0 80 80\" class=\"w-16 h-16\"><line x1=\"40\" y1=\"65\" x2=\"40\" y2=\"18\" stroke=\"#f43f5e\" stroke-width=\"6\" stroke-linecap=\"round\"/><polygon points=\"40,10 26,26 54,26\" fill=\"#f43f5e\"/></svg>\n            </div>\n            <div class=\"w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center\">\n                <svg viewBox=\"0 0 80 80\" class=\"w-16 h-16\"><line x1=\"15\" y1=\"40\" x2=\"62\" y2=\"40\" stroke=\"#f43f5e\" stroke-width=\"6\" stroke-linecap=\"round\"/><polygon points=\"70,40 54,26 54,54\" fill=\"#f43f5e\"/></svg>\n            </div>\n            <div class=\"w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center\">\n                <svg viewBox=\"0 0 80 80\" class=\"w-16 h-16\"><line x1=\"40\" y1=\"15\" x2=\"40\" y2=\"62\" stroke=\"#f43f5e\" stroke-width=\"6\" stroke-linecap=\"round\"/><polygon points=\"40,70 26,54 54,54\" fill=\"#f43f5e\"/></svg>\n            </div>\n            <div class=\"w-24 h-24 bg-teal-950/40 rounded-xl border-2 border-dashed border-teal-400/60 flex items-center justify-center text-teal-300 text-3xl font-black\">\n                ?\n            </div>\n        </div>",
                        "options": [
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><line x1=\"65\" y1=\"40\" x2=\"18\" y2=\"40\" stroke=\"#f43f5e\" stroke-width=\"6\" stroke-linecap=\"round\"/><polygon points=\"10,40 26,26 26,54\" fill=\"#f43f5e\"/></svg>",
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><line x1=\"15\" y1=\"40\" x2=\"62\" y2=\"40\" stroke=\"#f43f5e\" stroke-width=\"6\" stroke-linecap=\"round\"/><polygon points=\"70,40 54,26 54,54\" fill=\"#f43f5e\"/></svg>",
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><line x1=\"40\" y1=\"65\" x2=\"40\" y2=\"18\" stroke=\"#f43f5e\" stroke-width=\"6\" stroke-linecap=\"round\"/><polygon points=\"40,10 26,26 54,26\" fill=\"#f43f5e\"/></svg>",
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><line x1=\"60\" y1=\"20\" x2=\"25\" y2=\"55\" stroke=\"#f43f5e\" stroke-width=\"6\" stroke-linecap=\"round\"/><polygon points=\"20,60 35,55 25,45\" fill=\"#f43f5e\"/></svg>"
                        ],
                        "correct": 1
            },
            {
                        "id": 5,
                        "category": "اشکال هم‌مرکز و جای‌گیری درونی",
                        "title": "کدام گزینه، جای خالی (؟) در ماتریس بالا را به درستی کامل می‌کند؟",
                        "graphic": "<div class=\"inline-grid grid-cols-2 gap-3 p-3 bg-slate-900/80 rounded-2xl border border-teal-500/30 shadow-inner max-w-xs mx-auto\">\n            <div class=\"w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center\">\n                <svg viewBox=\"0 0 80 80\" class=\"w-16 h-16\"><rect x=\"14\" y=\"14\" width=\"52\" height=\"52\" fill=\"none\" stroke=\"#2dd4bf\" stroke-width=\"4\" rx=\"4\"/></svg>\n            </div>\n            <div class=\"w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center\">\n                <svg viewBox=\"0 0 80 80\" class=\"w-16 h-16\"><rect x=\"14\" y=\"14\" width=\"52\" height=\"52\" fill=\"none\" stroke=\"#2dd4bf\" stroke-width=\"4\" rx=\"4\"/><circle cx=\"40\" cy=\"40\" r=\"14\" fill=\"#38bdf8\"/></svg>\n            </div>\n            <div class=\"w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center\">\n                <svg viewBox=\"0 0 80 80\" class=\"w-16 h-16\"><polygon points=\"40,12 68,66 12,66\" fill=\"none\" stroke=\"#fbbf24\" stroke-width=\"4\"/></svg>\n            </div>\n            <div class=\"w-24 h-24 bg-teal-950/40 rounded-xl border-2 border-dashed border-teal-400/60 flex items-center justify-center text-teal-300 text-3xl font-black\">\n                ?\n            </div>\n        </div>",
                        "options": [
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><polygon points=\"40,12 68,66 12,66\" fill=\"none\" stroke=\"#fbbf24\" stroke-width=\"4\"/><rect x=\"30\" y=\"38\" width=\"20\" height=\"20\" fill=\"#38bdf8\"/></svg>",
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><circle cx=\"40\" cy=\"40\" r=\"26\" fill=\"none\" stroke=\"#fbbf24\" stroke-width=\"4\"/><polygon points=\"40,25 55,55 25,55\" fill=\"#38bdf8\"/></svg>",
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><polygon points=\"40,12 68,66 12,66\" fill=\"none\" stroke=\"#fbbf24\" stroke-width=\"4\"/><circle cx=\"40\" cy=\"48\" r=\"12\" fill=\"#38bdf8\"/></svg>",
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><polygon points=\"40,12 68,66 12,66\" fill=\"#fbbf24\"/></svg>"
                        ],
                        "correct": 3
            },
            {
                        "id": 6,
                        "category": "تقاطع و تکمیل متقارن خطوط",
                        "title": "کدام گزینه، جای خالی (؟) در ماتریس بالا را به درستی کامل می‌کند؟",
                        "graphic": "<div class=\"inline-grid grid-cols-2 gap-3 p-3 bg-slate-900/80 rounded-2xl border border-teal-500/30 shadow-inner max-w-xs mx-auto\">\n            <div class=\"w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center\">\n                <svg viewBox=\"0 0 80 80\" class=\"w-16 h-16\"><line x1=\"15\" y1=\"40\" x2=\"65\" y2=\"40\" stroke=\"#a78bfa\" stroke-width=\"6\" stroke-linecap=\"round\"/></svg>\n            </div>\n            <div class=\"w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center\">\n                <svg viewBox=\"0 0 80 80\" class=\"w-16 h-16\"><line x1=\"15\" y1=\"40\" x2=\"65\" y2=\"40\" stroke=\"#a78bfa\" stroke-width=\"6\" stroke-linecap=\"round\"/><line x1=\"40\" y1=\"15\" x2=\"40\" y2=\"65\" stroke=\"#a78bfa\" stroke-width=\"6\" stroke-linecap=\"round\"/></svg>\n            </div>\n            <div class=\"w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center\">\n                <svg viewBox=\"0 0 80 80\" class=\"w-16 h-16\"><line x1=\"18\" y1=\"18\" x2=\"62\" y2=\"62\" stroke=\"#34d399\" stroke-width=\"6\" stroke-linecap=\"round\"/></svg>\n            </div>\n            <div class=\"w-24 h-24 bg-teal-950/40 rounded-xl border-2 border-dashed border-teal-400/60 flex items-center justify-center text-teal-300 text-3xl font-black\">\n                ?\n            </div>\n        </div>",
                        "options": [
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><line x1=\"15\" y1=\"40\" x2=\"65\" y2=\"40\" stroke=\"#34d399\" stroke-width=\"6\"/><line x1=\"40\" y1=\"15\" x2=\"40\" y2=\"65\" stroke=\"#34d399\" stroke-width=\"6\"/></svg>",
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><line x1=\"18\" y1=\"18\" x2=\"62\" y2=\"62\" stroke=\"#34d399\" stroke-width=\"6\" stroke-linecap=\"round\"/><line x1=\"62\" y1=\"18\" x2=\"18\" y2=\"62\" stroke=\"#34d399\" stroke-width=\"6\" stroke-linecap=\"round\"/></svg>",
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><line x1=\"15\" y1=\"25\" x2=\"65\" y2=\"25\" stroke=\"#34d399\" stroke-width=\"6\"/><line x1=\"15\" y1=\"55\" x2=\"65\" y2=\"55\" stroke=\"#34d399\" stroke-width=\"6\"/></svg>",
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><line x1=\"62\" y1=\"18\" x2=\"18\" y2=\"62\" stroke=\"#34d399\" stroke-width=\"6\" stroke-linecap=\"round\"/></svg>"
                        ],
                        "correct": 2
            },
            {
                        "id": 7,
                        "category": "موقعیت ماهواره‌ای و انتقال نقطه‌ای در رئوس",
                        "title": "کدام گزینه، جای خالی (؟) در ماتریس بالا را به درستی کامل می‌کند؟",
                        "graphic": "<div class=\"inline-grid grid-cols-2 gap-3 p-3 bg-slate-900/80 rounded-2xl border border-teal-500/30 shadow-inner max-w-xs mx-auto\">\n            <div class=\"w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center\">\n                <svg viewBox=\"0 0 80 80\" class=\"w-16 h-16\"><rect x=\"18\" y=\"18\" width=\"44\" height=\"44\" fill=\"none\" stroke=\"#64748b\" stroke-width=\"3\"/><circle cx=\"18\" cy=\"18\" r=\"7\" fill=\"#f43f5e\"/></svg>\n            </div>\n            <div class=\"w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center\">\n                <svg viewBox=\"0 0 80 80\" class=\"w-16 h-16\"><rect x=\"18\" y=\"18\" width=\"44\" height=\"44\" fill=\"none\" stroke=\"#64748b\" stroke-width=\"3\"/><circle cx=\"62\" cy=\"18\" r=\"7\" fill=\"#f43f5e\"/></svg>\n            </div>\n            <div class=\"w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center\">\n                <svg viewBox=\"0 0 80 80\" class=\"w-16 h-16\"><rect x=\"18\" y=\"18\" width=\"44\" height=\"44\" fill=\"none\" stroke=\"#64748b\" stroke-width=\"3\"/><circle cx=\"18\" cy=\"62\" r=\"7\" fill=\"#f43f5e\"/></svg>\n            </div>\n            <div class=\"w-24 h-24 bg-teal-950/40 rounded-xl border-2 border-dashed border-teal-400/60 flex items-center justify-center text-teal-300 text-3xl font-black\">\n                ?\n            </div>\n        </div>",
                        "options": [
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><rect x=\"18\" y=\"18\" width=\"44\" height=\"44\" fill=\"none\" stroke=\"#64748b\" stroke-width=\"3\"/><circle cx=\"40\" cy=\"40\" r=\"7\" fill=\"#f43f5e\"/></svg>",
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><rect x=\"18\" y=\"18\" width=\"44\" height=\"44\" fill=\"none\" stroke=\"#64748b\" stroke-width=\"3\"/><circle cx=\"18\" cy=\"18\" r=\"7\" fill=\"#f43f5e\"/></svg>",
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><rect x=\"18\" y=\"18\" width=\"44\" height=\"44\" fill=\"none\" stroke=\"#64748b\" stroke-width=\"3\"/><circle cx=\"62\" cy=\"18\" r=\"7\" fill=\"#f43f5e\"/></svg>",
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><rect x=\"18\" y=\"18\" width=\"44\" height=\"44\" fill=\"none\" stroke=\"#64748b\" stroke-width=\"3\"/><circle cx=\"62\" cy=\"62\" r=\"7\" fill=\"#f43f5e\"/></svg>"
                        ],
                        "correct": 4
            },
            {
                        "id": 8,
                        "category": "انعکاس عمودی ۱۸۰ درجه و تغییر حالت سطح",
                        "title": "کدام گزینه، جای خالی (؟) در ماتریس بالا را به درستی کامل می‌کند؟",
                        "graphic": "<div class=\"inline-grid grid-cols-2 gap-3 p-3 bg-slate-900/80 rounded-2xl border border-teal-500/30 shadow-inner max-w-xs mx-auto\">\n            <div class=\"w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center\">\n                <svg viewBox=\"0 0 80 80\" class=\"w-16 h-16\"><polygon points=\"40,15 65,65 15,65\" fill=\"none\" stroke=\"#38bdf8\" stroke-width=\"4\"/></svg>\n            </div>\n            <div class=\"w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center\">\n                <svg viewBox=\"0 0 80 80\" class=\"w-16 h-16\"><polygon points=\"40,65 65,15 15,15\" fill=\"#38bdf8\"/></svg>\n            </div>\n            <div class=\"w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center\">\n                <svg viewBox=\"0 0 80 80\" class=\"w-16 h-16\"><polygon points=\"40,15 65,35 55,68 25,68 15,35\" fill=\"none\" stroke=\"#fbbf24\" stroke-width=\"4\"/></svg>\n            </div>\n            <div class=\"w-24 h-24 bg-teal-950/40 rounded-xl border-2 border-dashed border-teal-400/60 flex items-center justify-center text-teal-300 text-3xl font-black\">\n                ?\n            </div>\n        </div>",
                        "options": [
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><polygon points=\"40,65 65,45 55,12 25,12 15,45\" fill=\"#fbbf24\"/></svg>",
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><polygon points=\"40,15 65,35 55,68 25,68 15,35\" fill=\"#fbbf24\"/></svg>",
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><polygon points=\"40,65 65,45 55,12 25,12 15,45\" fill=\"none\" stroke=\"#fbbf24\" stroke-width=\"4\"/></svg>",
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><polygon points=\"40,65 65,15 15,15\" fill=\"#fbbf24\"/></svg>"
                        ],
                        "correct": 1
            },
            {
                        "id": 9,
                        "category": "ماتریس ۳×۳: گرادیان مقیاس و اندازه (Raven APM)",
                        "title": "کدام گزینه، خانه نهم از این ماتریس استاندارد را کامل می‌کند؟",
                        "graphic": "<div class=\"inline-grid grid-cols-3 gap-2 p-3 bg-slate-900/80 rounded-2xl border border-teal-500/30 max-w-xs mx-auto\">\n            <div class=\"w-16 h-16 bg-slate-800 rounded-lg flex items-center justify-center\"><svg viewBox=\"0 0 60 60\" class=\"w-10 h-10\"><circle cx=\"30\" cy=\"30\" r=\"8\" fill=\"#2dd4bf\"/></svg></div>\n            <div class=\"w-16 h-16 bg-slate-800 rounded-lg flex items-center justify-center\"><svg viewBox=\"0 0 60 60\" class=\"w-10 h-10\"><circle cx=\"30\" cy=\"30\" r=\"14\" fill=\"#2dd4bf\"/></svg></div>\n            <div class=\"w-16 h-16 bg-slate-800 rounded-lg flex items-center justify-center\"><svg viewBox=\"0 0 60 60\" class=\"w-10 h-10\"><circle cx=\"30\" cy=\"30\" r=\"22\" fill=\"#2dd4bf\"/></svg></div>\n            <div class=\"w-16 h-16 bg-slate-800 rounded-lg flex items-center justify-center\"><svg viewBox=\"0 0 60 60\" class=\"w-10 h-10\"><polygon points=\"30,20 20,40 40,40\" fill=\"#a78bfa\"/></svg></div>\n            <div class=\"w-16 h-16 bg-slate-800 rounded-lg flex items-center justify-center\"><svg viewBox=\"0 0 60 60\" class=\"w-10 h-10\"><polygon points=\"30,14 14,46 46,46\" fill=\"#a78bfa\"/></svg></div>\n            <div class=\"w-16 h-16 bg-slate-800 rounded-lg flex items-center justify-center\"><svg viewBox=\"0 0 60 60\" class=\"w-10 h-10\"><polygon points=\"30,8 6,52 54,52\" fill=\"#a78bfa\"/></svg></div>\n            <div class=\"w-16 h-16 bg-slate-800 rounded-lg flex items-center justify-center\"><svg viewBox=\"0 0 60 60\" class=\"w-10 h-10\"><rect x=\"22\" y=\"22\" width=\"16\" height=\"16\" fill=\"#fbbf24\"/></svg></div>\n            <div class=\"w-16 h-16 bg-slate-800 rounded-lg flex items-center justify-center\"><svg viewBox=\"0 0 60 60\" class=\"w-10 h-10\"><rect x=\"16\" y=\"16\" width=\"28\" height=\"28\" fill=\"#fbbf24\"/></svg></div>\n            <div class=\"w-16 h-16 bg-teal-950/40 rounded-lg border border-dashed border-teal-400 flex items-center justify-center text-teal-300 font-bold text-xl\">?</div>\n        </div>",
                        "options": [
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><rect x=\"28\" y=\"28\" width=\"24\" height=\"24\" fill=\"#fbbf24\"/></svg>",
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><rect x=\"20\" y=\"20\" width=\"40\" height=\"40\" fill=\"#fbbf24\"/></svg>",
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><rect x=\"10\" y=\"10\" width=\"60\" height=\"60\" fill=\"#fbbf24\" rx=\"4\"/></svg>",
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><circle cx=\"40\" cy=\"40\" r=\"30\" fill=\"#fbbf24\"/></svg>"
                        ],
                        "correct": 3
            },
            {
                        "id": 10,
                        "category": "ماتریس جمع و ادغام عناصر (A + B = C)",
                        "title": "کدام گزینه، حاصل انطباق و ترکیب دو شکل سطر دوم را نشان می‌دهد؟",
                        "graphic": "<div class=\"inline-grid grid-cols-2 gap-3 p-3 bg-slate-900/80 rounded-2xl border border-teal-500/30 shadow-inner max-w-xs mx-auto\">\n            <div class=\"w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center\">\n                <svg viewBox=\"0 0 80 80\" class=\"w-16 h-16\"><circle cx=\"40\" cy=\"40\" r=\"28\" fill=\"none\" stroke=\"#38bdf8\" stroke-width=\"4\"/><line x1=\"40\" y1=\"20\" x2=\"40\" y2=\"60\" stroke=\"#f43f5e\" stroke-width=\"4\"/><line x1=\"20\" y1=\"40\" x2=\"60\" y2=\"40\" stroke=\"#f43f5e\" stroke-width=\"4\"/></svg>\n            </div>\n            <div class=\"w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center\">\n                <svg viewBox=\"0 0 80 80\" class=\"w-16 h-16\"><circle cx=\"40\" cy=\"40\" r=\"28\" fill=\"none\" stroke=\"#38bdf8\" stroke-width=\"4\"/><line x1=\"40\" y1=\"20\" x2=\"40\" y2=\"60\" stroke=\"#f43f5e\" stroke-width=\"4\"/><line x1=\"20\" y1=\"40\" x2=\"60\" y2=\"40\" stroke=\"#f43f5e\" stroke-width=\"4\"/></svg>\n            </div>\n            <div class=\"w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center\">\n                <svg viewBox=\"0 0 80 80\" class=\"w-16 h-16\"><polygon points=\"40,12 68,40 40,68 12,40\" fill=\"none\" stroke=\"#34d399\" stroke-width=\"4\"/><line x1=\"26\" y1=\"26\" x2=\"54\" y2=\"54\" stroke=\"#fbbf24\" stroke-width=\"4\"/><line x1=\"54\" y1=\"26\" x2=\"26\" y2=\"54\" stroke=\"#fbbf24\" stroke-width=\"4\"/></svg>\n            </div>\n            <div class=\"w-24 h-24 bg-teal-950/40 rounded-xl border-2 border-dashed border-teal-400/60 flex items-center justify-center text-teal-300 text-3xl font-black\">\n                ?\n            </div>\n        </div>",
                        "options": [
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><circle cx=\"40\" cy=\"40\" r=\"28\" fill=\"none\" stroke=\"#34d399\" stroke-width=\"4\"/><line x1=\"26\" y1=\"26\" x2=\"54\" y2=\"54\" stroke=\"#fbbf24\" stroke-width=\"4\"/><line x1=\"54\" y1=\"26\" x2=\"26\" y2=\"54\" stroke=\"#fbbf24\" stroke-width=\"4\"/></svg>",
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><polygon points=\"40,12 68,40 40,68 12,40\" fill=\"none\" stroke=\"#34d399\" stroke-width=\"4\"/><line x1=\"26\" y1=\"26\" x2=\"54\" y2=\"54\" stroke=\"#fbbf24\" stroke-width=\"4\"/><line x1=\"54\" y1=\"26\" x2=\"26\" y2=\"54\" stroke=\"#fbbf24\" stroke-width=\"4\"/></svg>",
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><polygon points=\"40,12 68,40 40,68 12,40\" fill=\"none\" stroke=\"#34d399\" stroke-width=\"4\"/></svg>",
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><rect x=\"15\" y=\"15\" width=\"50\" height=\"50\" fill=\"none\" stroke=\"#34d399\" stroke-width=\"4\"/><line x1=\"26\" y1=\"26\" x2=\"54\" y2=\"54\" stroke=\"#fbbf24\" stroke-width=\"4\"/><line x1=\"54\" y1=\"26\" x2=\"26\" y2=\"54\" stroke=\"#fbbf24\" stroke-width=\"4\"/></svg>"
                        ],
                        "correct": 2
            },
            {
                        "id": 11,
                        "category": "ماتریس توزیع ویژگی‌ها (مربع لاتین - SPM Set E)",
                        "title": "با توجه به قانون سطرها و ستون‌ها، کدام قطعه در خانه (؟) قرار می‌گیرد؟",
                        "graphic": "<div class=\"inline-grid grid-cols-3 gap-2 p-3 bg-slate-900/80 rounded-2xl border border-teal-500/30 max-w-xs mx-auto\">\n            <div class=\"w-16 h-16 bg-slate-800 rounded-lg flex items-center justify-center\"><svg viewBox=\"0 0 60 60\" class=\"w-10 h-10\"><circle cx=\"30\" cy=\"30\" r=\"18\" fill=\"#38bdf8\"/></svg></div>\n            <div class=\"w-16 h-16 bg-slate-800 rounded-lg flex items-center justify-center\"><svg viewBox=\"0 0 60 60\" class=\"w-10 h-10\"><rect x=\"14\" y=\"14\" width=\"32\" height=\"32\" fill=\"none\" stroke=\"#38bdf8\" stroke-width=\"3\" stroke-dasharray=\"3\"/></svg></div>\n            <div class=\"w-16 h-16 bg-slate-800 rounded-lg flex items-center justify-center\"><svg viewBox=\"0 0 60 60\" class=\"w-10 h-10\"><polygon points=\"30,12 12,48 48,48\" fill=\"none\" stroke=\"#38bdf8\" stroke-width=\"3\"/></svg></div>\n            <div class=\"w-16 h-16 bg-slate-800 rounded-lg flex items-center justify-center\"><svg viewBox=\"0 0 60 60\" class=\"w-10 h-10\"><rect x=\"14\" y=\"14\" width=\"32\" height=\"32\" fill=\"none\" stroke=\"#38bdf8\" stroke-width=\"3\"/></svg></div>\n            <div class=\"w-16 h-16 bg-slate-800 rounded-lg flex items-center justify-center\"><svg viewBox=\"0 0 60 60\" class=\"w-10 h-10\"><polygon points=\"30,12 12,48 48,48\" fill=\"#38bdf8\"/></svg></div>\n            <div class=\"w-16 h-16 bg-slate-800 rounded-lg flex items-center justify-center\"><svg viewBox=\"0 0 60 60\" class=\"w-10 h-10\"><circle cx=\"30\" cy=\"30\" r=\"18\" fill=\"none\" stroke=\"#38bdf8\" stroke-width=\"3\" stroke-dasharray=\"3\"/></svg></div>\n            <div class=\"w-16 h-16 bg-slate-800 rounded-lg flex items-center justify-center\"><svg viewBox=\"0 0 60 60\" class=\"w-10 h-10\"><polygon points=\"30,12 12,48 48,48\" fill=\"none\" stroke=\"#38bdf8\" stroke-width=\"3\" stroke-dasharray=\"3\"/></svg></div>\n            <div class=\"w-16 h-16 bg-slate-800 rounded-lg flex items-center justify-center\"><svg viewBox=\"0 0 60 60\" class=\"w-10 h-10\"><circle cx=\"30\" cy=\"30\" r=\"18\" fill=\"none\" stroke=\"#38bdf8\" stroke-width=\"3\"/></svg></div>\n            <div class=\"w-16 h-16 bg-teal-950/40 rounded-lg border border-dashed border-teal-400 flex items-center justify-center text-teal-300 font-bold text-xl\">?</div>\n        </div>",
                        "options": [
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><rect x=\"18\" y=\"18\" width=\"44\" height=\"44\" fill=\"none\" stroke=\"#38bdf8\" stroke-width=\"4\"/></svg>",
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><rect x=\"18\" y=\"18\" width=\"44\" height=\"44\" fill=\"none\" stroke=\"#38bdf8\" stroke-width=\"4\" stroke-dasharray=\"4\"/></svg>",
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><polygon points=\"40,14 16,66 64,66\" fill=\"#38bdf8\"/></svg>",
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><rect x=\"18\" y=\"18\" width=\"44\" height=\"44\" fill=\"#38bdf8\" rx=\"2\"/></svg>"
                        ],
                        "correct": 4
            },
            {
                        "id": 12,
                        "category": "دوران زاویه‌ای قطاع‌های دایره‌ای (Pie Slices)",
                        "title": "کدام دایره قطاعی، جای خالی (؟) در ماتریس را به درستی کامل می‌کند؟",
                        "graphic": "<div class=\"inline-grid grid-cols-2 gap-3 p-3 bg-slate-900/80 rounded-2xl border border-teal-500/30 shadow-inner max-w-xs mx-auto\">\n            <div class=\"w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center\">\n                <svg viewBox=\"0 0 80 80\" class=\"w-16 h-16\"><circle cx=\"40\" cy=\"40\" r=\"28\" fill=\"none\" stroke=\"#64748b\" stroke-width=\"3\"/><path d=\"M40 40 L40 12 A28 28 0 0 1 68 40 Z\" fill=\"#2dd4bf\"/></svg>\n            </div>\n            <div class=\"w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center\">\n                <svg viewBox=\"0 0 80 80\" class=\"w-16 h-16\"><circle cx=\"40\" cy=\"40\" r=\"28\" fill=\"none\" stroke=\"#64748b\" stroke-width=\"3\"/><path d=\"M40 40 L68 40 A28 28 0 0 1 40 68 Z\" fill=\"#2dd4bf\"/></svg>\n            </div>\n            <div class=\"w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center\">\n                <svg viewBox=\"0 0 80 80\" class=\"w-16 h-16\"><circle cx=\"40\" cy=\"40\" r=\"28\" fill=\"none\" stroke=\"#64748b\" stroke-width=\"3\"/><path d=\"M40 40 L40 68 A28 28 0 0 1 12 40 Z\" fill=\"#2dd4bf\"/></svg>\n            </div>\n            <div class=\"w-24 h-24 bg-teal-950/40 rounded-xl border-2 border-dashed border-teal-400/60 flex items-center justify-center text-teal-300 text-3xl font-black\">\n                ?\n            </div>\n        </div>",
                        "options": [
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><circle cx=\"40\" cy=\"40\" r=\"28\" fill=\"none\" stroke=\"#64748b\" stroke-width=\"3\"/><path d=\"M40 40 L68 40 A28 28 0 0 1 40 68 Z\" fill=\"#2dd4bf\"/></svg>",
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><circle cx=\"40\" cy=\"40\" r=\"28\" fill=\"none\" stroke=\"#64748b\" stroke-width=\"3\"/><path d=\"M40 40 L40 68 A28 28 0 0 1 12 40 Z\" fill=\"#2dd4bf\"/></svg>",
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><circle cx=\"40\" cy=\"40\" r=\"28\" fill=\"none\" stroke=\"#64748b\" stroke-width=\"3\"/><path d=\"M40 40 L12 40 A28 28 0 0 1 40 12 Z\" fill=\"#2dd4bf\"/></svg>",
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><circle cx=\"40\" cy=\"40\" r=\"28\" fill=\"#2dd4bf\"/></svg>"
                        ],
                        "correct": 3
            },
            {
                        "id": 13,
                        "category": "برهم‌نهی منطقی خطوط (Raven Advanced APM)",
                        "title": "کدام گزینه، جای خالی (؟) در این ترکیب برهم‌نهی خطوط را پر می‌کند؟",
                        "graphic": "<div class=\"inline-grid grid-cols-2 gap-3 p-3 bg-slate-900/80 rounded-2xl border border-teal-500/30 shadow-inner max-w-xs mx-auto\">\n            <div class=\"w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center\">\n                <svg viewBox=\"0 0 80 80\" class=\"w-16 h-16\"><line x1=\"15\" y1=\"40\" x2=\"65\" y2=\"40\" stroke=\"#38bdf8\" stroke-width=\"6\"/><line x1=\"40\" y1=\"15\" x2=\"40\" y2=\"65\" stroke=\"#38bdf8\" stroke-width=\"6\"/></svg>\n            </div>\n            <div class=\"w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center\">\n                <svg viewBox=\"0 0 80 80\" class=\"w-16 h-16\"><line x1=\"15\" y1=\"40\" x2=\"65\" y2=\"40\" stroke=\"#38bdf8\" stroke-width=\"6\"/><line x1=\"40\" y1=\"15\" x2=\"40\" y2=\"65\" stroke=\"#38bdf8\" stroke-width=\"6\"/></svg>\n            </div>\n            <div class=\"w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center\">\n                <svg viewBox=\"0 0 80 80\" class=\"w-16 h-16\"><line x1=\"18\" y1=\"18\" x2=\"62\" y2=\"62\" stroke=\"#f59e0b\" stroke-width=\"6\"/><line x1=\"62\" y1=\"18\" x2=\"18\" y2=\"62\" stroke=\"#f59e0b\" stroke-width=\"6\"/></svg>\n            </div>\n            <div class=\"w-24 h-24 bg-teal-950/40 rounded-xl border-2 border-dashed border-teal-400/60 flex items-center justify-center text-teal-300 text-3xl font-black\">\n                ?\n            </div>\n        </div>",
                        "options": [
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><line x1=\"18\" y1=\"18\" x2=\"62\" y2=\"62\" stroke=\"#f59e0b\" stroke-width=\"6\"/><line x1=\"62\" y1=\"18\" x2=\"18\" y2=\"62\" stroke=\"#f59e0b\" stroke-width=\"6\"/></svg>",
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><line x1=\"15\" y1=\"40\" x2=\"65\" y2=\"40\" stroke=\"#f59e0b\" stroke-width=\"6\"/><line x1=\"40\" y1=\"15\" x2=\"40\" y2=\"65\" stroke=\"#f59e0b\" stroke-width=\"6\"/></svg>",
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><line x1=\"18\" y1=\"18\" x2=\"62\" y2=\"62\" stroke=\"#f59e0b\" stroke-width=\"6\"/></svg>",
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><rect x=\"20\" y=\"20\" width=\"40\" height=\"40\" fill=\"none\" stroke=\"#f59e0b\" stroke-width=\"4\"/></svg>"
                        ],
                        "correct": 1
            },
            {
                        "id": 14,
                        "category": "ماتریس تصاعد کمی نقاط (Naglieri NNAT3)",
                        "title": "کدام آرایش نقطه‌ای، جای خالی (؟) در ماتریس را به درستی کامل می‌کند؟",
                        "graphic": "<div class=\"inline-grid grid-cols-3 gap-2 p-3 bg-slate-900/80 rounded-2xl border border-teal-500/30 max-w-xs mx-auto\">\n            <div class=\"w-16 h-16 bg-slate-800 rounded-lg flex items-center justify-center\"><svg viewBox=\"0 0 60 60\" class=\"w-10 h-10\"><circle cx=\"30\" cy=\"30\" r=\"5\" fill=\"#38bdf8\"/></svg></div>\n            <div class=\"w-16 h-16 bg-slate-800 rounded-lg flex items-center justify-center\"><svg viewBox=\"0 0 60 60\" class=\"w-10 h-10\"><circle cx=\"20\" cy=\"30\" r=\"5\" fill=\"#38bdf8\"/><circle cx=\"40\" cy=\"30\" r=\"5\" fill=\"#38bdf8\"/></svg></div>\n            <div class=\"w-16 h-16 bg-slate-800 rounded-lg flex items-center justify-center\"><svg viewBox=\"0 0 60 60\" class=\"w-10 h-10\"><circle cx=\"16\" cy=\"30\" r=\"4.5\" fill=\"#38bdf8\"/><circle cx=\"30\" cy=\"30\" r=\"4.5\" fill=\"#38bdf8\"/><circle cx=\"44\" cy=\"30\" r=\"4.5\" fill=\"#38bdf8\"/></svg></div>\n            <div class=\"w-16 h-16 bg-slate-800 rounded-lg flex items-center justify-center\"><svg viewBox=\"0 0 60 60\" class=\"w-10 h-10\"><circle cx=\"20\" cy=\"30\" r=\"5\" fill=\"#34d399\"/><circle cx=\"40\" cy=\"30\" r=\"5\" fill=\"#34d399\"/></svg></div>\n            <div class=\"w-16 h-16 bg-slate-800 rounded-lg flex items-center justify-center\"><svg viewBox=\"0 0 60 60\" class=\"w-10 h-10\"><circle cx=\"16\" cy=\"30\" r=\"4.5\" fill=\"#34d399\"/><circle cx=\"30\" cy=\"30\" r=\"4.5\" fill=\"#34d399\"/><circle cx=\"44\" cy=\"30\" r=\"4.5\" fill=\"#34d399\"/></svg></div>\n            <div class=\"w-16 h-16 bg-slate-800 rounded-lg flex items-center justify-center\"><svg viewBox=\"0 0 60 60\" class=\"w-10 h-10\"><circle cx=\"20\" cy=\"20\" r=\"4.5\" fill=\"#34d399\"/><circle cx=\"40\" cy=\"20\" r=\"4.5\" fill=\"#34d399\"/><circle cx=\"20\" cy=\"40\" r=\"4.5\" fill=\"#34d399\"/><circle cx=\"40\" cy=\"40\" r=\"4.5\" fill=\"#34d399\"/></svg></div>\n            <div class=\"w-16 h-16 bg-slate-800 rounded-lg flex items-center justify-center\"><svg viewBox=\"0 0 60 60\" class=\"w-10 h-10\"><circle cx=\"16\" cy=\"30\" r=\"4.5\" fill=\"#c084fc\"/><circle cx=\"30\" cy=\"30\" r=\"4.5\" fill=\"#c084fc\"/><circle cx=\"44\" cy=\"30\" r=\"4.5\" fill=\"#c084fc\"/></svg></div>\n            <div class=\"w-16 h-16 bg-slate-800 rounded-lg flex items-center justify-center\"><svg viewBox=\"0 0 60 60\" class=\"w-10 h-10\"><circle cx=\"20\" cy=\"20\" r=\"4.5\" fill=\"#c084fc\"/><circle cx=\"40\" cy=\"20\" r=\"4.5\" fill=\"#c084fc\"/><circle cx=\"20\" cy=\"40\" r=\"4.5\" fill=\"#c084fc\"/><circle cx=\"40\" cy=\"40\" r=\"4.5\" fill=\"#c084fc\"/></svg></div>\n            <div class=\"w-16 h-16 bg-teal-950/40 rounded-lg border border-dashed border-teal-400 flex items-center justify-center text-teal-300 font-bold text-xl\">?</div>\n        </div>",
                        "options": [
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><circle cx=\"20\" cy=\"40\" r=\"6\" fill=\"#c084fc\"/><circle cx=\"40\" cy=\"40\" r=\"6\" fill=\"#c084fc\"/><circle cx=\"60\" cy=\"40\" r=\"6\" fill=\"#c084fc\"/></svg>",
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><circle cx=\"25\" cy=\"25\" r=\"6\" fill=\"#c084fc\"/><circle cx=\"55\" cy=\"25\" r=\"6\" fill=\"#c084fc\"/><circle cx=\"25\" cy=\"55\" r=\"6\" fill=\"#c084fc\"/><circle cx=\"55\" cy=\"55\" r=\"6\" fill=\"#c084fc\"/></svg>",
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><circle cx=\"25\" cy=\"20\" r=\"5\" fill=\"#c084fc\"/><circle cx=\"55\" cy=\"20\" r=\"5\" fill=\"#c084fc\"/><circle cx=\"25\" cy=\"40\" r=\"5\" fill=\"#c084fc\"/><circle cx=\"55\" cy=\"40\" r=\"5\" fill=\"#c084fc\"/><circle cx=\"25\" cy=\"60\" r=\"5\" fill=\"#c084fc\"/><circle cx=\"55\" cy=\"60\" r=\"5\" fill=\"#c084fc\"/></svg>",
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><circle cx=\"22\" cy=\"22\" r=\"5.5\" fill=\"#c084fc\"/><circle cx=\"58\" cy=\"22\" r=\"5.5\" fill=\"#c084fc\"/><circle cx=\"40\" cy=\"40\" r=\"5.5\" fill=\"#c084fc\"/><circle cx=\"22\" cy=\"58\" r=\"5.5\" fill=\"#c084fc\"/><circle cx=\"58\" cy=\"58\" r=\"5.5\" fill=\"#c084fc\"/></svg>"
                        ],
                        "correct": 4
            },
            {
                        "id": 15,
                        "category": "تقارن آینه‌ای و انعکاس مرکب فضایی (Raven APM)",
                        "title": "کدام شکل، تصویر آینه‌ای و متقارن دقیق شکل مجهول را نشان می‌دهد؟",
                        "graphic": "<div class=\"inline-grid grid-cols-2 gap-3 p-3 bg-slate-900/80 rounded-2xl border border-teal-500/30 shadow-inner max-w-xs mx-auto\">\n            <div class=\"w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center\">\n                <svg viewBox=\"0 0 80 80\" class=\"w-16 h-16\"><path d=\"M25 20 L25 60 L60 60\" fill=\"none\" stroke=\"#38bdf8\" stroke-width=\"5\" stroke-linecap=\"round\"/><circle cx=\"60\" cy=\"60\" r=\"8\" fill=\"#f43f5e\"/></svg>\n            </div>\n            <div class=\"w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center\">\n                <svg viewBox=\"0 0 80 80\" class=\"w-16 h-16\"><path d=\"M55 20 L55 60 L20 60\" fill=\"none\" stroke=\"#38bdf8\" stroke-width=\"5\" stroke-linecap=\"round\"/><circle cx=\"20\" cy=\"60\" r=\"8\" fill=\"#f43f5e\"/></svg>\n            </div>\n            <div class=\"w-24 h-24 bg-slate-800 rounded-xl border border-slate-700 flex items-center justify-center\">\n                <svg viewBox=\"0 0 80 80\" class=\"w-16 h-16\"><path d=\"M25 55 L25 25 L55 25\" fill=\"none\" stroke=\"#fbbf24\" stroke-width=\"5\" stroke-linecap=\"round\"/><polygon points=\"55,18 62,25 55,32 48,25\" fill=\"#34d399\"/></svg>\n            </div>\n            <div class=\"w-24 h-24 bg-teal-950/40 rounded-xl border-2 border-dashed border-teal-400/60 flex items-center justify-center text-teal-300 text-3xl font-black\">\n                ?\n            </div>\n        </div>",
                        "options": [
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><path d=\"M25 55 L25 25 L55 25\" fill=\"none\" stroke=\"#fbbf24\" stroke-width=\"5\" stroke-linecap=\"round\"/><polygon points=\"55,18 62,25 55,32 48,25\" fill=\"#34d399\"/></svg>",
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><path d=\"M55 55 L55 25 L25 25\" fill=\"none\" stroke=\"#fbbf24\" stroke-width=\"5\" stroke-linecap=\"round\"/><polygon points=\"25,18 32,25 25,32 18,25\" fill=\"#34d399\"/></svg>",
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><path d=\"M25 25 L25 55 L55 55\" fill=\"none\" stroke=\"#fbbf24\" stroke-width=\"5\" stroke-linecap=\"round\"/><polygon points=\"55,48 62,55 55,62 48,55\" fill=\"#34d399\"/></svg>",
                                    "<svg viewBox=\"0 0 80 80\" class=\"w-16 h-16 mx-auto\"><path d=\"M55 55 L55 25 L25 25\" fill=\"none\" stroke=\"#fbbf24\" stroke-width=\"5\" stroke-linecap=\"round\"/></svg>"
                        ],
                        "correct": 2
            }
];

        let currentQuestionIdx = 0;
        let selectedOption = null;
        let userAnswers = {};
        let questionTimerInterval = null;
        let questionSecondsLeft = 45;
        let totalTestStartTime = null;

        function isValidIranianNationalCode(input) {
            if (!input) return false;
            const p = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
            const a = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
            let code = input.toString().trim();
            for (let i = 0; i < 10; i++) {
                code = code.replaceAll(p[i], i.toString()).replaceAll(a[i], i.toString());
            }
            code = code.replace(/[^0-9]/g, '');
            if (code.length !== 10) return false;
            for (let i = 0; i <= 9; i++) {
                if (code === String(i).repeat(10)) return false;
            }
            let sum = 0;
            for (let i = 0; i < 9; i++) {
                sum += parseInt(code.charAt(i), 10) * (10 - i);
            }
            let rem = sum % 11;
            let check = parseInt(code.charAt(9), 10);
            return (rem < 2 && check === rem) || (rem >= 2 && check === (11 - rem));
        }

        function startTest(e) {
            e.preventDefault();

            const natIdInput = document.getElementById('cand_national_id').value.trim();
            const phoneInput = document.getElementById('cand_phone').value.trim();
            const errElem = document.getElementById('nat-id-error');
            const alertCard = document.getElementById('already-participated-card');
            const alertText = document.getElementById('already-participated-text');
            const btnStart = document.getElementById('btn-start-quiz');
            const btnText = document.getElementById('btn-start-quiz-text');

            if (alertCard) alertCard.classList.add('hidden');

            if (!isValidIranianNationalCode(natIdInput)) {
                errElem.classList.remove('hidden');
                document.getElementById('cand_national_id').focus();
                return;
            }
            errElem.classList.add('hidden');

            // Phone format normalization and check
            let cleanPhone = phoneInput.replace(/[^0-9۰-۹٠-٩]/g, '');
            const pDigits = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
            const aDigits = ['٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
            for (let i = 0; i < 10; i++) {
                cleanPhone = cleanPhone.replaceAll(pDigits[i], i.toString()).replaceAll(aDigits[i], i.toString());
            }

            if (!/^09[0-9]{9}$/.test(cleanPhone)) {
                alert('لطفاً شماره تلفن همراه معتبر ۱۱ رقمی (مانند ۰۹۱۲۳۴۵۶۷۸۹) وارد فرمایید.');
                document.getElementById('cand_phone').focus();
                return;
            }

            // Lock button and display loading status
            btnStart.disabled = true;
            const originalBtnHtml = btnText.innerHTML;
            btnText.innerHTML = `
                <svg class="animate-spin h-5 w-5 text-white inline-block ml-2" viewBox="0 0 24 24" fill="none">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                </svg>
                در حال استعلام سابقه شرکت در آزمون...
            `;

            // Asynchronous Pre-Check with server BEFORE starting the test!
            fetch('api-diamond-check.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    national_id: natIdInput,
                    phone: cleanPhone
                })
            })
            .then(res => res.json())
            .then(data => {
                btnStart.disabled = false;
                btnText.innerHTML = originalBtnHtml;

                if (data.already_participated) {
                    // STOP RIGHT HERE! DO NOT START TEST!
                    if (alertCard && alertText) {
                        alertText.innerText = data.message;
                        alertCard.classList.remove('hidden');
                        alertCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    } else {
                        alert(data.message);
                    }
                    return;
                }

                if (!data.success) {
                    alert(data.message || 'خطا در اعتبارسنجی اطلاعات. لطفاً دوباره بررسی کنید.');
                    return;
                }

                // Candidate is eligible! Proceed to start test!
                candidateInfo = {
                    full_name: document.getElementById('cand_name').value.trim(),
                    national_id: natIdInput,
                    phone: cleanPhone,
                    grade: document.getElementById('cand_grade').value,
                    city: document.getElementById('cand_city').value.trim(),
                    school_name: document.getElementById('cand_school').value.trim()
                };

                // Switch view to Quiz Card
                document.getElementById('registration-card').classList.add('hidden');
                document.getElementById('quiz-card').classList.remove('hidden');

                totalTestStartTime = Date.now();
                currentQuestionIdx = 0;
                loadQuestion(0);

                // Smooth scroll to top of quiz card
                document.getElementById('test-section').scrollIntoView({ behavior: 'smooth' });
            })
            .catch(err => {
                console.error(err);
                btnStart.disabled = false;
                btnText.innerHTML = originalBtnHtml;
                alert('خطا در برقراری ارتباط با سرور برای بررسی صلاحیت. لطفاً اتصال اینترنت خود را بررسی و مجدداً تلاش کنید.');
            });
        }

        // Instant check when national ID or phone is blurred
        function checkDuplicateOnBlur() {
            const natIdInput = document.getElementById('cand_national_id').value.trim();
            const phoneInput = document.getElementById('cand_phone').value.trim();
            const alertCard = document.getElementById('already-participated-card');
            const alertText = document.getElementById('already-participated-text');

            if (!natIdInput && !phoneInput) return;
            if (natIdInput && !isValidIranianNationalCode(natIdInput)) return;

            fetch('api-diamond-check.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    national_id: natIdInput,
                    phone: phoneInput
                })
            })
            .then(res => res.json())
            .then(data => {
                if (data.already_participated) {
                    if (alertCard && alertText) {
                        alertText.innerText = data.message;
                        alertCard.classList.remove('hidden');
                    }
                } else if (alertCard) {
                    alertCard.classList.add('hidden');
                }
            })
            .catch(() => {});
        }

        document.addEventListener('DOMContentLoaded', function() {
            const elNat = document.getElementById('cand_national_id');
            const elPhone = document.getElementById('cand_phone');
            if (elNat) elNat.addEventListener('blur', checkDuplicateOnBlur);
            if (elPhone) elPhone.addEventListener('blur', checkDuplicateOnBlur);
        });

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
                national_id: candidateInfo.national_id,
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
                } else if (data.already_participated) {
                    document.getElementById('result-cand-name').innerText = candidateInfo.full_name + ' گرامی';
                    document.getElementById('result-score').innerText = '⛔';
                    document.getElementById('result-tier').innerText = 'کارنامه قبلی شما در سیستم موجود است';
                    document.getElementById('result-message').innerHTML = '<div class="p-4 rounded-xl bg-amber-500/20 border border-amber-500/40 text-amber-200 text-sm leading-relaxed font-bold text-center">' + data.message + '</div>';
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
