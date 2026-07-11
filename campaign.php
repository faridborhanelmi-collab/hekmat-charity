<?php
session_start();
require_once 'includes/db.php';

// Fetch students for sponsorship grid
$stmt = $pdo->query("
    SELECT s.id, s.alias_name, s.name, s.grade, s.field_of_study, s.address, s.talents, s.notes, s.explanations,
           (SELECT count(*) FROM sponsorships spon WHERE spon.student_id = s.id AND spon.status = 'active') as is_sponsored,
           sp.final_grade, sp.hermans_grade
    FROM students s
    LEFT JOIN student_psychology sp ON s.id = sp.student_id
    WHERE s.status = 'active' AND s.bursary_eligible = 1
    ORDER BY is_sponsored ASC, s.id ASC
");
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getGenderFromName($name) {
    // A simple heuristic based on common Iranian names to show something.
    // User can always update this later.
    $female_names = ['فاطمه', 'زهرا', 'مریم', 'سارا', 'زینب', 'ریحانه', 'مهسا', 'الهام', 'معصومه', 'نیایش', 'نازنین', 'کوثر', 'لیلا', 'شقایق', 'سارینا', 'سحر', 'یلدا', 'نازیلا', 'ویانا', 'فرخنده', 'فرزانه'];
    $first_name = explode(' ', trim($name))[0];
    if (in_array($first_name, $female_names) || mb_substr($first_name, -1) == 'ه' || mb_substr($first_name, -1) == 'ا') {
        return 'دختر';
    }
    return 'پسر';
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پویش هر حکمتی، یک دانش‌آموز | بنیاد نیکوکاری حکمت</title>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@100;400;700;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Vazirmatn', 'sans-serif'] },
                    colors: {
                        primary: { 900: '#0c4a6e', 800: '#075985', 600: '#0284c7' },
                        teal: { 400: '#2dd4bf', 500: '#14b8a6', 600: '#0d9488' },
                        gold: { 400: '#fbbf24', 500: '#f59e0b', 600: '#d97706' }
                    }
                }
            }
        }
    </script>
    <style>
        .glass {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .gradient-text {
            background: linear-gradient(to right, #2dd4bf, #fbbf24);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .fade-in-up {
            animation: fadeInUp 1s ease-out forwards;
            opacity: 0;
            transform: translateY(20px);
        }
        @keyframes fadeInUp {
            to { opacity: 1; transform: translateY(0); }
        }
        .delay-1 { animation-delay: 0.2s; }
        .delay-2 { animation-delay: 0.4s; }
        .delay-3 { animation-delay: 0.6s; }
    </style>

    <!-- iOS PWA/Homescreen Setup -->
    <link rel="apple-touch-icon" href="logo.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="بنیاد حکمت">
    <link rel="icon" type="image/png" href="logo.png">
    <link rel="manifest" href="manifest.json">
</head>
<body class="bg-gray-900 text-gray-100 font-sans antialiased overflow-x-hidden selection:bg-teal-500 selection:text-white">

    <!-- Navigation -->
    <?php include 'includes/navbar.php'; ?>

    <!-- Hero Section -->
    <div class="relative min-h-screen flex items-center justify-center pt-20" style="background-image: url('assets/images/campaign_hero.jpg?v=3'); background-size: cover; background-position: center; background-attachment: fixed;">
        <div class="absolute inset-0 bg-gray-900/80 mix-blend-multiply"></div>
        <div class="absolute inset-0 bg-gradient-to-t from-gray-900 via-gray-900/40 to-transparent"></div>
        
        <div class="relative z-10 max-w-7xl mx-auto px-6 lg:px-8 text-center flex flex-col items-center">
            <span class="inline-flex items-center gap-2 px-4 py-2 rounded-full bg-teal-500/10 border border-teal-500/20 text-teal-400 text-sm font-bold mb-6 fade-in-up">
                <span class="w-2 h-2 rounded-full bg-teal-400 animate-pulse"></span>
                پویش ملی بنیاد حکمت
            </span>
            <h1 class="text-5xl md:text-7xl font-black mb-6 leading-tight fade-in-up delay-1">
                هر حکمتی، <br class="md:hidden" />
                <span class="gradient-text">یک حکمت یار</span>
            </h1>
            <p class="mt-4 text-xl md:text-2xl text-gray-200 max-w-3xl mb-10 leading-relaxed font-light fade-in-up delay-2">
                با پذیرش هزینه‌های بورس، فرزندی مستعد را در خانواده خود پذیرا باشید و زیر چتر مهر خود، همراه رشد و بالندگی‌اش شوید.
            </p>
            <div class="flex flex-col sm:flex-row gap-4 fade-in-up delay-3">
                <a href="#join" class="bg-gradient-to-r from-teal-500 to-primary-600 hover:from-teal-400 hover:to-primary-500 text-white font-bold py-4 px-8 rounded-full shadow-lg shadow-teal-500/30 transform hover:-translate-y-1 transition-all duration-300 text-lg">
                    همین حالا حامی شوید
                </a>
                <a href="#how-it-works" class="glass hover:bg-white/10 text-white font-bold py-4 px-8 rounded-full transition-all duration-300 text-lg border border-white/20">
                    بیشتر بدانید
                </a>
            </div>
        </div>
        
        <!-- Scroll indicator -->
        <div class="absolute bottom-10 left-1/2 transform -translate-x-1/2 animate-bounce">
            <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3"></path>
            </svg>
        </div>
    </div>

    <!-- About the Connection Feature -->
    <section id="about" class="py-24 relative">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <div class="grid md:grid-cols-2 gap-16 items-center">
                <div>
                    <h2 class="text-3xl md:text-4xl font-black mb-6 text-white">ارتباطی عمیق، اما <span class="text-teal-400">ناشناس</span></h2>
                    <p class="text-gray-400 text-lg leading-relaxed mb-6">
                        برخلاف کمک‌های مالی معمول، در این پویش ما شما را مستقیماً به دانش‌آموزانی که تحت حمایت می‌گیرید متصل می‌کنیم. 
                    </p>
                    <ul class="space-y-4">
                        <li class="flex items-start gap-4">
                            <div class="flex-shrink-0 w-10 h-10 rounded-full bg-teal-500/20 flex items-center justify-center mt-1">
                                <svg class="w-5 h-5 text-teal-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            </div>
                            <div>
                                <h4 class="text-white font-bold text-lg">داشبورد اختصاصی حامیان</h4>
                                <p class="text-gray-400 text-sm mt-1">با ورود به پنل خود، پروفایل، علایق و آرزوهای دانش‌آموزان خود را می‌بینید.</p>
                            </div>
                        </li>
                        <li class="flex items-start gap-4">
                            <div class="flex-shrink-0 w-10 h-10 rounded-full bg-gold-500/20 flex items-center justify-center mt-1">
                                <svg class="w-5 h-5 text-gold-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                            </div>
                            <div>
                                <h4 class="text-white font-bold text-lg">مشاهده پیشرفت تحصیلی</h4>
                                <p class="text-gray-400 text-sm mt-1">کارنامه‌ها و گزارش‌های پیشرفت دانش‌آموز مستقیماً برای شما ارسال می‌شود.</p>
                            </div>
                        </li>
                        <li class="flex items-start gap-4">
                            <div class="flex-shrink-0 w-10 h-10 rounded-full bg-primary-500/20 flex items-center justify-center mt-1">
                                <svg class="w-5 h-5 text-primary-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                            </div>
                            <div>
                                <h4 class="text-white font-bold text-lg">حفظ کرامت و حریم خصوصی</h4>
                                <p class="text-gray-400 text-sm mt-1">دانش‌آموزان هویت شما را نخواهند شناخت. کرامت و عزت نفس آن‌ها خط قرمز ماست.</p>
                            </div>
                        </li>
                    </ul>
                </div>
                <div class="relative">
                    <div class="absolute inset-0 bg-gradient-to-br from-teal-500/30 to-primary-600/30 blur-3xl rounded-full"></div>
                    <div class="relative glass rounded-3xl p-8 border border-white/10 shadow-2xl">
                        <div class="flex items-center justify-between border-b border-white/10 pb-4 mb-4">
                            <h3 class="font-bold text-white text-lg">داشبورد حامی (پیش‌نمایش)</h3>
                            <span class="px-2 py-1 bg-green-500/20 text-green-400 text-xs rounded-lg">آنلاین</span>
                        </div>
                        <div class="space-y-4">
                            <div class="flex items-center gap-4 bg-white/5 p-4 rounded-2xl border border-white/5">
                                <div class="w-12 h-12 rounded-full bg-gray-700 flex items-center justify-center text-xl">👦</div>
                                <div>
                                    <div class="text-white font-bold">نام مستعار: علی</div>
                                    <div class="text-gray-400 text-xs">کلاس نهم • علاقه‌مند به برنامه‌نویسی</div>
                                </div>
                                <div class="mr-auto">
                                    <button class="text-xs bg-teal-500/20 text-teal-400 px-3 py-1 rounded-full">مشاهده کارنامه</button>
                                </div>
                            </div>
                            <div class="flex items-center gap-4 bg-white/5 p-4 rounded-2xl border border-white/5">
                                <div class="w-12 h-12 rounded-full bg-gray-700 flex items-center justify-center text-xl">👧</div>
                                <div>
                                    <div class="text-white font-bold">نام مستعار: سارا</div>
                                    <div class="text-gray-400 text-xs">کلاس دوازدهم • آرزو: پزشک</div>
                                </div>
                                <div class="mr-auto">
                                    <button class="text-xs bg-teal-500/20 text-teal-400 px-3 py-1 rounded-full">پیام معلم</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Pricing/Donation Mechanics -->
    <section id="how-it-works" class="py-24 bg-gray-800/50 relative">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <h2 class="text-3xl md:text-4xl font-black text-white mb-6">ساختار حکمت‌یار چگونه است؟</h2>
                <p class="text-gray-300 text-lg leading-relaxed mb-4">
                    با مبلغی بسیار کمتر از آنچه تصور می‌کنید (متوسط <strong class="text-teal-400">۲.۵ میلیون تومان</strong> در ماه برای هر نفر)، می‌توانید مسیر زندگی یک انسان را تغییر دهید.
                </p>
                <p class="text-gray-400 text-base">
                    شما می‌توانید با تقبل ضریبی از این مبلغ، یک یا چند فرزند مستعد را در خانواده خود پذیرا باشید و تأثیری شگرف در آینده آن‌ها و جامعه بگذارید.
                </p>
            </div>

            <div class="grid md:grid-cols-3 gap-8">
                <!-- 1 Student -->
                <div class="glass rounded-3xl p-8 border border-white/10 hover:border-teal-500/50 transition-all duration-300 relative group overflow-hidden">
                    <div class="absolute inset-0 bg-gradient-to-br from-teal-500/0 to-teal-500/0 group-hover:from-teal-500/10 group-hover:to-transparent transition-all duration-500"></div>
                    <div class="relative z-10">
                        <div class="w-14 h-14 bg-white/10 rounded-2xl flex items-center justify-center text-2xl mb-6">1️⃣</div>
                        <h3 class="text-2xl font-black text-white mb-2">حامی ۱ دانش‌آموز</h3>
                        <div class="text-3xl font-black text-teal-400 mb-6">۲.۵ <span class="text-lg font-normal text-gray-400">میلیون تومان / ماه</span></div>
                        <p class="text-gray-400 mb-8 text-sm leading-relaxed">
                            تأمین آرامش خاطر یک دانش‌آموز مستعد. با این تصمیم، دغدغه‌های مالی را از دوش او برمی‌دارید تا تنها به فردایی روشن بیندیشد.
                        </p>
                        <a href="#join" class="block w-full text-center bg-white/10 hover:bg-teal-500 text-white font-bold py-3 rounded-xl transition-colors border border-white/10 hover:border-teal-400">
                            پذیرش این افتخار
                        </a>
                    </div>
                </div>

                <!-- 2 Students -->
                <div class="glass rounded-3xl p-8 border border-teal-500 shadow-2xl shadow-teal-500/20 transform md:-translate-y-4 relative overflow-hidden group">
                    <div class="absolute top-0 right-0 bg-teal-500 text-white text-xs font-bold px-3 py-1 rounded-bl-lg z-20">پیشنهاد ویژه</div>
                    <div class="absolute inset-0 bg-gradient-to-br from-teal-500/10 to-primary-600/10 z-0"></div>
                    <div class="relative z-10">
                        <div class="w-14 h-14 bg-teal-500/20 text-teal-400 rounded-2xl flex items-center justify-center text-2xl mb-6">2️⃣</div>
                        <h3 class="text-2xl font-black text-white mb-2">حامی ۲ دانش‌آموز</h3>
                        <div class="text-3xl font-black text-teal-400 mb-6">۵.۰ <span class="text-lg font-normal text-gray-400">میلیون تومان / ماه</span></div>
                        <p class="text-gray-400 mb-8 text-sm leading-relaxed">
                            ایجاد تغییر بنیادین در مسیر زندگی دو آینده‌ساز. تأثیرگذاری مضاعف شما، امیدی است که در دل دو خانواده جوانه می‌زند.
                        </p>
                        <a href="#join" class="block w-full text-center bg-gradient-to-r from-teal-500 to-primary-600 hover:from-teal-400 hover:to-primary-500 text-white font-bold py-3 rounded-xl transition-all shadow-lg shadow-teal-500/30">
                            پذیرش این افتخار
                        </a>
                    </div>
                </div>

                <!-- 4 Students -->
                <div class="glass rounded-3xl p-8 border border-white/10 hover:border-gold-500/50 transition-all duration-300 relative group overflow-hidden">
                    <div class="absolute inset-0 bg-gradient-to-br from-gold-500/0 to-gold-500/0 group-hover:from-gold-500/10 group-hover:to-transparent transition-all duration-500"></div>
                    <div class="relative z-10">
                        <div class="w-14 h-14 bg-white/10 rounded-2xl flex items-center justify-center text-2xl mb-6">4️⃣</div>
                        <h3 class="text-2xl font-black text-white mb-2">حامی ۴ دانش‌آموز</h3>
                        <div class="text-3xl font-black text-gold-400 mb-6">۱۰ <span class="text-lg font-normal text-gray-400">میلیون تومان / ماه</span></div>
                        <p class="text-gray-400 mb-8 text-sm leading-relaxed">
                            پوشش کامل یک خانواده بزرگ از دانش‌آموزان حکمت. شما بانی شکل‌گیری نسلی از نخبگان خواهید بود.
                        </p>
                        <a href="#join" class="block w-full text-center bg-white/10 hover:bg-gold-500 text-white font-bold py-3 rounded-xl transition-colors border border-white/10 hover:border-gold-400">
                            پذیرش این افتخار
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="mt-12 text-center text-gray-400 text-sm">
                * امکان حمایت با مبالغ دلخواه نیز وجود دارد. سیستم به طور خودکار به ازای هر ۲.۵ میلیون تومان، پروفایل یک دانش‌آموز را به شما اختصاص خواهد داد.
            </div>
        </div>
    </section>

    <!-- Students Grid Section -->
    <section id="students" class="py-24 relative">
        <div class="max-w-7xl mx-auto px-6 lg:px-8">
            <div class="text-center max-w-3xl mx-auto mb-16">
                <h2 class="text-3xl md:text-4xl font-black text-white mb-6">دانش‌آموزان چشم‌انتظار حمایت شما</h2>
                <p class="text-gray-400 text-lg leading-relaxed">
                    با کلیک روی دکمه «انتخاب برای حمایت»، می‌توانید مسیر زندگی یکی از این نخبگان را تغییر دهید.
                    (برای حفظ حریم خصوصی، نام‌های واقعی نمایش داده نمی‌شوند)
                </p>
            </div>

            <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($students as $student): 
                    $is_sponsored = $student['is_sponsored'] > 0;
                    $gender = getGenderFromName($student['name']);
                    $avatar = $gender == 'پسر' ? '👦' : '👧';
                    $bg_class = $is_sponsored ? 'opacity-60 grayscale cursor-not-allowed border-gray-700' : 'hover:border-teal-500 hover:shadow-teal-500/20 transform hover:-translate-y-1 transition-all duration-300 border-white/10';
                ?>
                <div class="glass rounded-3xl p-6 border <?php echo $bg_class; ?> relative overflow-hidden group">
                    <?php if ($is_sponsored): ?>
                        <div class="absolute inset-0 bg-gray-900/40 z-10 flex items-center justify-center backdrop-blur-[2px]">
                            <div class="bg-red-500/80 text-white px-6 py-2 rounded-full font-black text-lg rotate-12 border-2 border-red-400/50 shadow-xl">
                                حامی دارد
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="flex items-start justify-between mb-4 relative z-0">
                        <div class="flex items-center gap-4">
                            <div class="w-14 h-14 rounded-full bg-gray-800 flex items-center justify-center text-3xl shadow-inner border border-gray-700">
                                <?php echo $avatar; ?>
                            </div>
                            <div>
                                <h3 class="text-xl font-black text-white"><?php echo htmlspecialchars((string)($student['alias_name'] ?: 'حکمت‌جو')); ?></h3>
                                <div class="text-teal-400 text-sm font-bold mt-1"><?php echo htmlspecialchars((string)$student['grade']); ?> • <?php echo $gender; ?></div>
                            </div>
                        </div>
                        <?php if ($student['final_grade']): ?>
                            <div class="bg-primary-900/50 border border-primary-500/30 text-primary-300 px-2 py-1 rounded-lg text-xs font-black">
                                <?php echo htmlspecialchars((string)$student['final_grade']); ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="space-y-3 mb-6 relative z-0">
                        <?php if (!empty($student['field_of_study'])): ?>
                        <div class="flex items-center gap-2 text-sm text-gray-300">
                            <span class="text-gray-500">📚 رشته/پایه:</span>
                            <span class="font-bold text-gray-200"><?php echo htmlspecialchars((string)$student['field_of_study']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($student['address'])): 
                            // Extract just the first few words or general area for privacy
                            $area = mb_substr($student['address'], 0, 30) . '...';
                        ?>
                        <div class="flex items-center gap-2 text-sm text-gray-300">
                            <span class="text-gray-500">📍 محدوده:</span>
                            <span class="font-bold text-gray-200"><?php echo htmlspecialchars((string)$area); ?></span>
                        </div>
                        <?php endif; ?>

                        <?php if (!empty($student['talents'])): ?>
                        <div class="flex items-start gap-2 text-sm text-gray-300">
                            <span class="text-gray-500 mt-1">✨ استعداد/افتخارات:</span>
                            <span class="font-bold text-gold-400 leading-relaxed"><?php echo htmlspecialchars((string)$student['talents']); ?></span>
                        </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($student['explanations'])): ?>
                        <div class="flex items-start gap-2 text-sm text-gray-300">
                            <span class="text-gray-500 mt-1">💬 نظر مدیر:</span>
                            <span class="font-medium text-gray-300 leading-relaxed italic border-r-2 border-primary-500/50 pr-2"><?php echo htmlspecialchars((string)mb_substr($student['explanations'], 0, 80)) . '...'; ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="relative z-0">
                        <?php if (!$is_sponsored): ?>
                            <a href="sponsor-student.php?id=<?php echo $student['id']; ?>" class="block w-full text-center bg-teal-600 hover:bg-teal-500 text-white font-black py-3 rounded-xl transition-all shadow-lg hover:shadow-teal-500/20">
                                انتخاب برای حمایت
                            </a>
                        <?php else: ?>
                            <button disabled class="block w-full text-center bg-gray-800 text-gray-500 font-black py-3 rounded-xl cursor-not-allowed">
                                در حال حاضر امکان پذیر نیست
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Join CTA Form -->
    <section id="join" class="py-24 relative overflow-hidden">
        <div class="absolute top-1/2 left-1/2 transform -translate-x-1/2 -translate-y-1/2 w-full max-w-4xl h-96 bg-primary-600/20 blur-[100px] rounded-full"></div>
        <div class="max-w-4xl mx-auto px-6 lg:px-8 relative z-10">
            <div class="glass rounded-3xl p-8 md:p-12 border border-white/10 shadow-2xl text-center">
                <h2 class="text-3xl font-black text-white mb-4">به جمع حامیان اختصاصی بپیوندید</h2>
                <p class="text-gray-400 mb-10">برای اعلام آمادگی و پیوستن به این پویش، کافیست اطلاعات خود را در فرم زیر وارد کنید تا همکاران ما در بنیاد حکمت در اسرع وقت با شما تماس بگیرند.</p>
                
                <form class="max-w-2xl mx-auto space-y-6" onsubmit="event.preventDefault(); alert('فرم با موفقیت ثبت شد! به زودی با شما تماس می‌گیریم.');">
                    <div class="grid md:grid-cols-2 gap-6">
                        <div>
                            <input type="text" placeholder="نام و نام خانوادگی" required class="w-full bg-gray-900/50 border border-white/10 text-white rounded-xl px-5 py-4 focus:outline-none focus:border-teal-500 transition-colors">
                        </div>
                        <div>
                            <input type="tel" placeholder="شماره تماس" required class="w-full bg-gray-900/50 border border-white/10 text-white rounded-xl px-5 py-4 focus:outline-none focus:border-teal-500 transition-colors">
                        </div>
                    </div>
                    <div>
                        <select required class="w-full bg-gray-900/50 border border-white/10 text-gray-300 rounded-xl px-5 py-4 focus:outline-none focus:border-teal-500 transition-colors appearance-none">
                            <option value="" disabled selected>تعداد دانش‌آموزان مورد حمایت...</option>
                            <option value="1">۱ دانش‌آموز (۲.۵ میلیون در ماه)</option>
                            <option value="2">۲ دانش‌آموز (۵ میلیون در ماه)</option>
                            <option value="4">۴ دانش‌آموز (۱۰ میلیون در ماه)</option>
                            <option value="custom">مبلغ دلخواه (تماس بگیرید)</option>
                        </select>
                    </div>
                    <button type="submit" class="w-full bg-gradient-to-r from-teal-500 to-primary-600 hover:from-teal-400 hover:to-primary-500 text-white font-black py-4 rounded-xl shadow-lg hover:shadow-teal-500/30 transform hover:-translate-y-1 transition-all duration-300 text-lg">
                        ثبت درخواست حمایت
                    </button>
                </form>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="border-t border-white/10 py-12 text-center text-gray-500 text-sm">
        <p>© 1405 بنیاد نیکوکاری حکمت. تمامی حقوق محفوظ است.</p>
    </footer>

    <script>
        // Simple intersection observer for animations
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('fade-in-up');
                    entry.target.style.opacity = 1;
                }
            });
        });

        document.querySelectorAll('.glass').forEach((el) => observer.observe(el));
    </script>

<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/sw.js');
    });
  }
</script>

</body>
</html>
