<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';

if (($_SESSION['role'] ?? '') === 'data_operator') {
    header("Location: library.php");
    exit();
}

// Access Control
require_role(['superadmin', 'secretary', 'education_deputy', 'board_member', 'admin']);

$can_finance = can_access_financial();
$can_edit_finance = can_edit_financial();
$can_view_logs = can_view_audit_logs();
$is_read_only = is_read_only();

// وقایع زنده سیستم برای پایش میز کار مدیرعامل
$recent_logs = $can_view_logs ? $pdo->query("SELECT * FROM audit_logs ORDER BY id DESC LIMIT 10")->fetchAll() : [];
$today_logs_count = $can_view_logs ? ($pdo->query("SELECT COUNT(*) FROM audit_logs WHERE created_at LIKE '" . date('Y-m-d') . "%'")->fetchColumn() ?: 0) : 0;

// 1. Total Stats
$active_students = $pdo->query("SELECT COUNT(*) FROM students WHERE status IN ('active', 'university')")->fetchColumn();
$total_students = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$total_donors = $pdo->query("SELECT COUNT(*) FROM donors")->fetchColumn();
$total_collected = $pdo->query("SELECT SUM(total_donated) FROM donors")->fetchColumn();

// 2. Inactive Donors (> 90 days) - Exclude CEO/Management from inactive list
$inactive_query = "
    SELECT d.id, d.name, d.surname, MAX(dn.date) as last_date
    FROM donors d
    LEFT JOIN donations dn ON d.id = dn.donor_id
    WHERE d.id != 1305 AND (d.name NOT LIKE '%فرید%' OR d.surname NOT LIKE '%برهان%')
    GROUP BY d.id
    HAVING last_date < '1404/10/01' OR last_date IS NULL
    LIMIT 5
";
$inactive_donors = $pdo->query($inactive_query)->fetchAll();

// 3. Real Birthday Engine (Today & Upcoming within 30 days)
$persian_months_map = [
    1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد',
    4 => 'تیر', 5 => 'مرداد', 6 => 'شهریور',
    7 => 'مهر', 8 => 'آبان', 9 => 'آذر',
    10 => 'دی', 11 => 'بهمن', 12 => 'اسفند'
];

$j_today = gregorian_to_jalali((int)date('Y'), (int)date('m'), (int)date('d'));
$cur_jy = $j_today[0];
$cur_jm = (int)$j_today[1];
$cur_jd = (int)$j_today[2];

if (!function_exists('calc_jalali_doy')) {
    function calc_jalali_doy($m, $d) {
        if ($m <= 6) return ($m - 1) * 31 + $d;
        return 6 * 31 + ($m - 7) * 30 + $d;
    }
}
$today_doy = calc_jalali_doy($cur_jm, $cur_jd);

$people_st = $pdo->query("SELECT id, name, surname, birthday, 'student' as type FROM students WHERE birthday IS NOT NULL AND TRIM(birthday) != ''")->fetchAll();
$people_dn = $pdo->query("SELECT id, name, surname, birthday, 'donor' as type FROM donors WHERE birthday IS NOT NULL AND TRIM(birthday) != ''")->fetchAll();
$all_birthday_people = array_merge($people_st, $people_dn);

$today_birthdays = [];
$upcoming_birthdays = [];

foreach ($all_birthday_people as $p) {
    $parts = explode('/', str_replace('-', '/', trim($p['birthday'])));
    if (count($parts) === 3) {
        $bm = (int)$parts[1];
        $bd = (int)$parts[2];
        if ($bm >= 1 && $bm <= 12 && $bd >= 1 && $bd <= 31) {
            $b_doy = calc_jalali_doy($bm, $bd);
            $diff = $b_doy - $today_doy;
            if ($diff < 0) $diff += 365;
            
            $item = [
                'id' => $p['id'],
                'name' => $p['name'],
                'surname' => $p['surname'],
                'type' => $p['type'],
                'birthday' => $p['birthday'],
                'month_name' => $persian_months_map[$bm] ?? '',
                'day' => $bd,
                'days_left' => $diff
            ];
            
            if ($diff === 0) {
                $today_birthdays[] = $item;
            } elseif ($diff <= 30) {
                $upcoming_birthdays[] = $item;
            }
        }
    }
}
usort($upcoming_birthdays, fn($a, $b) => $a['days_left'] <=> $b['days_left']);
$total_students_with_bday = count($people_st);
$total_donors_with_bday = count($people_dn);

// 4. Diamond Campaign Stats
$diamond_total = $pdo->query("SELECT COUNT(*) FROM diamond_candidates")->fetchColumn() ?: 0;
$diamond_top = $pdo->query("SELECT COUNT(*) FROM diamond_candidates WHERE score >= 12")->fetchColumn() ?: 0;

// 4.1 Interview Psychometric Tests Stats
$interview_total = $pdo->query("SELECT COUNT(*) FROM interview_tests")->fetchColumn() ?: 0;
$interview_completed = $pdo->query("SELECT COUNT(*) FROM interview_tests WHERE status = 'completed'")->fetchColumn() ?: 0;

// 5. User Portals Data for Manager & Supervisor Quick Access
$portal_students = $pdo->query("SELECT id, code, name, surname, grade, status FROM students ORDER BY CASE WHEN status = 'active' THEN 1 WHEN status = 'university' THEN 2 WHEN status = 'graduated' THEN 3 ELSE 4 END, name ASC, surname ASC")->fetchAll(PDO::FETCH_ASSOC);
$portal_donors = $pdo->query("SELECT id, name, surname, phone, total_donated FROM donors ORDER BY total_donated DESC, name ASC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
$total_sponsorships_count = $pdo->query("SELECT COUNT(*) FROM sponsorships WHERE status = 'active'")->fetchColumn() ?: 0;

// 6. Library & Books Stats
$library_total_books = $pdo->query("SELECT COUNT(*) FROM library_books")->fetchColumn() ?: 0;
$library_available_books = $pdo->query("SELECT COUNT(*) FROM library_books WHERE status = 'available'")->fetchColumn() ?: 0;
$library_borrowed_books = $pdo->query("SELECT COUNT(*) FROM library_books WHERE status = 'borrowed'")->fetchColumn() ?: 0;
$library_pending_requests = $pdo->query("SELECT COUNT(*) FROM library_books WHERE status = 'requested'")->fetchColumn() ?: 0;
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل مدیریت عالی | بنیاد حکمت</title>
<style>
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:100;font-display:swap;src:url('/assets/fonts/Vazirmatn-100.woff2') format('woff2')}
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:300;font-display:swap;src:url('/assets/fonts/Vazirmatn-300.woff2') format('woff2')}
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:400;font-display:swap;src:url('/assets/fonts/Vazirmatn-400.woff2') format('woff2')}
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:500;font-display:swap;src:url('/assets/fonts/Vazirmatn-500.woff2') format('woff2')}
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:700;font-display:swap;src:url('/assets/fonts/Vazirmatn-700.woff2') format('woff2')}
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:900;font-display:swap;src:url('/assets/fonts/Vazirmatn-900.woff2') format('woff2')}
</style>
<link rel="stylesheet" href="/assets/tailwind.min.css">
<script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Vazirmatn', 'sans-serif'] },
                    colors: { primary: { 900: '#00141e', 800: '#115e59', 600: '#14b8a6' } }
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
<body class="bg-gray-50 font-sans text-gray-800 antialiased overflow-x-hidden">

    <?php 
    $base_url = '../';
    include '../includes/dashboard-nav.php'; 
    ?>
    <main class="container mx-auto px-6 py-12">
        <div class="flex flex-col lg:flex-row gap-8">
            
            <!-- Main Dashboard Area -->
            <div class="flex-1 space-y-8">
                
                <?php 
                $cur_username = strtolower($_SESSION['username'] ?? '');
                $is_behnam = in_array($cur_username, ['behnam', 'بهنام']);
                if ($is_behnam): 
                ?>
                <!-- VIP Welcome Banner for Board Chairman Mr. Behnam Bahremand -->
                <div class="mb-8 p-6 md:p-8 rounded-[2.5rem] bg-gradient-to-r from-emerald-900 via-slate-900 to-teal-950 border border-emerald-500/30 text-white shadow-2xl relative overflow-hidden flex flex-col md:flex-row items-center justify-between gap-6">
                    <div class="absolute -right-10 -bottom-10 w-48 h-48 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
                    <div class="flex items-center gap-5 relative z-10">
                        <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-emerald-500 to-teal-400 p-0.5 shadow-lg shadow-emerald-500/20 flex-shrink-0">
                            <div class="w-full h-full bg-slate-900 rounded-[14px] flex items-center justify-center text-3xl">
                                🏛️
                            </div>
                        </div>
                        <div>
                            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/20 text-emerald-300 text-xs font-bold mb-2 border border-emerald-500/30">
                                <span>⭐</span>
                                <span>ریاست محترم هیئت مدیره بنیاد نیکوکاری حکمت</span>
                            </div>
                            <h2 class="text-xl md:text-2xl font-black text-white">
                                جناب آقای بهنام بهرمن، به سامانه جامع بنیاد حکمت خوش آمدید
                            </h2>
                            <p class="text-xs text-gray-300 mt-1">
                                کلیه شاخص‌های عملکرد، آمار دانش‌آموزان نخبه و ترازهای مالی بنیاد به صورت شفاف در اختیار شماست.
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 relative z-10 w-full md:w-auto justify-end">
                        <button onclick="openChangePasswordModal()" class="px-5 py-2.5 bg-white/10 hover:bg-emerald-600 border border-white/20 hover:border-emerald-400 text-white text-xs font-bold rounded-2xl transition-all shadow-md flex items-center gap-2">
                            <span>🔑</span>
                            <span>تغییر رمز عبور</span>
                        </button>
                    </div>
                </div>

                <?php elseif ($is_read_only): ?>
                <!-- VIP Welcome Banner for Board Members -->
                <div class="mb-8 p-6 md:p-8 rounded-[2.5rem] bg-gradient-to-r from-slate-900 via-teal-950 to-slate-900 border border-teal-500/30 text-white shadow-2xl relative overflow-hidden flex flex-col md:flex-row items-center justify-between gap-6">
                    <div class="absolute -right-10 -bottom-10 w-48 h-48 bg-teal-500/10 rounded-full blur-3xl pointer-events-none"></div>
                    <div class="flex items-center gap-5 relative z-10">
                        <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-teal-500 to-emerald-400 p-0.5 shadow-lg shadow-teal-500/20 flex-shrink-0">
                            <div class="w-full h-full bg-slate-900 rounded-[14px] flex items-center justify-center text-3xl">
                                ✨
                            </div>
                        </div>
                        <div>
                            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-teal-500/20 text-teal-300 text-xs font-bold mb-2 border border-teal-500/30">
                                <span>🏛️</span>
                                <span>عضو محترم هیئت مدیره بنیاد نیکوکاری حکمت</span>
                            </div>
                            <h2 class="text-xl md:text-2xl font-black text-white">
                                <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'عضو محترم'); ?>، به سامانه جامع بنیاد حکمت خوش آمدید
                            </h2>
                            <p class="text-xs text-gray-300 mt-1">
                                گزارش‌های زنده بنیاد، وضعیت تحصیلی نخبگان و شفافیت منابع به صورت لحظه‌ای در دسترس شماست.
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 relative z-10 w-full md:w-auto justify-end">
                        <button onclick="openChangePasswordModal()" class="px-5 py-2.5 bg-white/10 hover:bg-teal-600 border border-white/20 hover:border-teal-400 text-white text-xs font-bold rounded-2xl transition-all shadow-md flex items-center gap-2">
                            <span>🔑</span>
                            <span>تغییر رمز عبور</span>
                        </button>
                    </div>
                </div>

                <?php elseif (($_SESSION['role'] ?? '') === 'education_deputy'): ?>
                <div class="mb-8 p-6 md:p-8 rounded-[2.5rem] bg-gradient-to-r from-blue-900 via-indigo-950 to-slate-900 border border-blue-500/30 text-white shadow-xl flex flex-col md:flex-row items-center justify-between gap-6">
                    <div class="flex items-center gap-5">
                        <div class="w-16 h-16 rounded-2xl bg-blue-500/20 border border-blue-400/40 flex items-center justify-center text-3xl flex-shrink-0">📚</div>
                        <div>
                            <div class="text-xs font-bold text-blue-300 mb-1">معاونت محترم آموزش بنیاد نیکوکاری حکمت</div>
                            <h2 class="text-xl font-black text-white">سرکار خانم فرتاش، به پنل مدیریت آموزشی خوش آمدید</h2>
                        </div>
                    </div>
                    <button onclick="openChangePasswordModal()" class="px-5 py-2.5 bg-white/10 hover:bg-blue-600 border border-white/20 text-white text-xs font-bold rounded-2xl transition-all flex items-center gap-2">
                        <span>🔑</span>
                        <span>تغییر رمز عبور</span>
                    </button>
                </div>

                <?php elseif (($_SESSION['role'] ?? '') === 'secretary'): ?>
                <!-- Secretary (Mrs. Abbasi) Banner -->
                <div class="mb-8 p-6 md:p-8 rounded-[2.5rem] bg-gradient-to-r from-teal-900 via-primary-900 to-slate-900 border border-teal-500/30 text-white shadow-xl flex flex-col md:flex-row items-center justify-between gap-6">
                    <div class="flex items-center gap-5">
                        <div class="w-16 h-16 rounded-2xl bg-teal-500/20 border border-teal-400/40 flex items-center justify-center text-3xl flex-shrink-0">📋</div>
                        <div>
                            <div class="text-xs font-bold text-teal-300 mb-1">دبیرخانه و امور اداری بنیاد نیکوکاری حکمت</div>
                            <h2 class="text-xl md:text-2xl font-black text-white">سرکار خانم عباسی، به میز کار اداری خوش آمدید</h2>
                            <p class="text-xs text-gray-300 mt-1">مدیریت پرونده نخبگان، امور دفتری و ثبت مددجویان در اختیار شماست.</p>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <a href="../people-list.php?open_modal=1" class="px-5 py-2.5 bg-teal-500 hover:bg-teal-400 text-white text-xs font-black rounded-2xl transition-all shadow-lg flex items-center gap-2 hover:-translate-y-0.5">
                            <span class="text-base leading-none">+</span>
                            <span>افزودن مددجوی جدید</span>
                        </a>
                        <button onclick="openChangePasswordModal()" class="px-5 py-2.5 bg-white/10 hover:bg-teal-600 border border-white/20 text-white text-xs font-bold rounded-2xl transition-all flex items-center gap-2">
                            <span>🔑</span>
                            <span>تغییر رمز عبور</span>
                        </button>
                    </div>
                </div>

                <?php else: ?>
                <!-- CEO / Superadmin (Mr. Farid Elmi) Banner -->
                <div class="mb-8 p-6 md:p-8 rounded-[2.5rem] bg-gradient-to-r from-primary-900 via-slate-900 to-teal-950 border border-teal-500/30 text-white shadow-xl flex flex-col md:flex-row items-center justify-between gap-6">
                    <div class="flex items-center gap-5">
                        <div class="w-16 h-16 rounded-2xl bg-teal-500/20 border border-teal-400/40 flex items-center justify-center text-3xl flex-shrink-0">👑</div>
                        <div>
                            <div class="text-xs font-bold text-teal-300 mb-1">مدیریت عامل بنیاد نیکوکاری حکمت</div>
                            <h2 class="text-xl md:text-2xl font-black text-white">جناب آقای فرید برهان علمی، به میز کار جامع مدیریت خوش آمدید</h2>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-3">
                        <a href="../people-list.php?open_modal=1" class="px-5 py-2.5 bg-teal-500 hover:bg-teal-400 text-white text-xs font-black rounded-2xl transition-all shadow-lg flex items-center gap-2 hover:-translate-y-0.5">
                            <span class="text-base leading-none">+</span>
                            <span>افزودن مددجوی جدید</span>
                        </a>
                        <button onclick="openChangePasswordModal()" class="px-5 py-2.5 bg-white/10 hover:bg-teal-600 border border-white/20 text-white text-xs font-bold rounded-2xl transition-all flex items-center gap-2">
                            <span>🔑</span>
                            <span>تغییر رمز عبور</span>
                        </button>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Stats Overview -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-gray-100 flex flex-col items-center">
                        <div class="w-12 h-12 bg-teal-50 text-teal-600 rounded-2xl flex items-center justify-center text-2xl mb-4">🎓</div>
                        <div class="text-3xl font-black text-primary-900"><?php echo toFarsiDigits($active_students); ?> <span class="text-xs font-normal text-gray-400">/ <?php echo toFarsiDigits($total_students); ?></span></div>
                        <div class="text-[10px] text-gray-400 font-bold">دانش‌آموز تحت پوشش (فعال)</div>
                    </div>
                    <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-gray-100 flex flex-col items-center">
                        <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center text-2xl mb-4">🤝</div>
                        <div class="text-3xl font-black text-primary-900"><?php echo toFarsiDigits($total_donors); ?></div>
                        <div class="text-[10px] text-gray-400 font-bold">نیکوکار فعال</div>
                    </div>
                    <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-gray-100 flex flex-col items-center">
                        <div class="w-12 h-12 bg-green-50 text-green-600 rounded-2xl flex items-center justify-center text-2xl mb-4">💰</div>
                        <div class="text-2xl font-black text-primary-900"><?php echo toFarsiDigits(number_format($total_collected / 1000000000, 1)); ?> <span class="text-xs">میلیارد</span></div>
                        <div class="text-[10px] text-gray-400 font-bold">جذب سرمایه (ریال)</div>
                    </div>
                </div>

                <!-- Live Psychometrics & Interview Engine Banner -->
                <div class="p-6 md:p-8 rounded-[2.5rem] bg-gradient-to-r from-teal-950 via-slate-900 to-emerald-950 border-2 border-teal-400/50 text-white shadow-2xl relative overflow-hidden flex flex-col md:flex-row items-center justify-between gap-6 ring-2 ring-teal-500/20">
                    <div class="absolute -right-10 -bottom-10 w-48 h-48 bg-teal-500/15 rounded-full blur-3xl pointer-events-none"></div>
                    <div class="flex items-center gap-5 relative z-10">
                        <div class="w-16 h-16 rounded-2xl bg-teal-500/20 border border-teal-300/40 flex items-center justify-center text-3xl shadow-lg shadow-teal-500/30 flex-shrink-0 animate-pulse">
                            🧠
                        </div>
                        <div>
                            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-teal-500/20 text-teal-300 text-xs font-black mb-2 border border-teal-400/30">
                                <span>سامانه جدید مصاحبه آنلاین و روانسنجی</span>
                                <span class="w-1.5 h-1.5 rounded-full bg-teal-400 animate-ping"></span>
                            </div>
                            <h3 class="text-base md:text-lg font-black text-white leading-relaxed">
                                صدور لینک پیامکی آزمون‌های روانسنجی و مصاحبه (گاردنر، هوش هیجانی، سلامت روان و انگیزه پیشرفت)
                            </h3>
                            <p class="text-xs text-teal-100/80 mt-1 max-w-xl leading-relaxed">
                                ارسال لینک اختصاصی زمان‌دار بدون نیاز به نرم‌افزار ویندوز، با تایمرهای تنظیم‌شده برای هر سوال و ثبت خودکار کارنامه در پرونده داوطلب.
                            </p>
                            <div class="flex items-center gap-3 mt-2 text-[11px] text-teal-300 font-bold">
                                <span>📊 آزمون‌های صادر شده: <strong class="text-white font-mono text-xs"><?php echo toFarsiDigits($interview_total); ?></strong></span>
                                <span>•</span>
                                <span>✅ تکمیل‌شده: <strong class="text-emerald-300 font-mono text-xs"><?php echo toFarsiDigits($interview_completed); ?></strong></span>
                            </div>
                        </div>
                    </div>
                    <div class="flex flex-col sm:flex-row items-center gap-3 relative z-10 w-full md:w-auto justify-end flex-shrink-0">
                        <a href="interview-tests.php" class="w-full sm:w-auto px-6 py-3.5 bg-gradient-to-r from-teal-400 to-emerald-500 hover:from-teal-300 hover:to-emerald-400 text-slate-950 font-black text-xs rounded-2xl transition-all shadow-xl shadow-teal-500/30 flex items-center justify-center gap-2">
                            <span>ورود به سامانه و صدور لینک آزمون</span>
                            <span>←</span>
                        </a>
                        <a href="../interview-test.php?demo=1" target="_blank" class="w-full sm:w-auto px-4 py-3.5 bg-white/10 hover:bg-white/20 border border-white/20 text-white font-bold text-xs rounded-2xl transition-all flex items-center justify-center gap-1.5">
                            <span>پیش‌نمایش آزمون</span>
                            <span>👁️</span>
                        </a>
                    </div>
                </div>

                <!-- Live Diamond Campaign Quick Monitor -->
                <div class="p-6 md:p-8 rounded-[2.5rem] bg-gradient-to-r from-emerald-950 via-slate-900 to-teal-950 border-2 border-emerald-500/40 text-white shadow-xl relative overflow-hidden flex flex-col md:flex-row items-center justify-between gap-6">
                    <div class="absolute -right-10 -bottom-10 w-48 h-48 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>
                    <div class="flex items-center gap-5 relative z-10">
                        <div class="w-16 h-16 rounded-2xl bg-emerald-500/20 border border-emerald-400/40 flex items-center justify-center text-3xl shadow-lg shadow-emerald-500/20 flex-shrink-0">
                            💎
                        </div>
                        <div>
                            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-emerald-500/20 text-emerald-300 text-xs font-black mb-2 border border-emerald-500/30">
                                <span>نتایج زنده پویش گنج‌های پنهان</span>
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-ping"></span>
                            </div>
                            <h3 class="text-base md:text-lg font-black text-white leading-relaxed">
                                تاکنون <span class="text-emerald-300 font-mono text-xl"><?php echo number_format($diamond_total); ?></span> نفر در آزمون شرکت کرده‌اند و <span class="text-amber-300 font-mono text-xl"><?php echo number_format($diamond_top); ?></span> نخبه و گنج پنهان (نمره ۱۲ به بالا) شناسایی شد.
                            </h3>
                            <p class="text-xs text-gray-300 mt-1">
                                کلیه کارنامه‌ها با احراز کد ملی، زمان پاسخ‌دهی و سطح شناختی ثبت شده‌اند.
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 relative z-10 w-full md:w-auto justify-end flex-shrink-0">
                        <a href="diamond-candidates.php" class="px-6 py-3 bg-gradient-to-r from-emerald-500 to-teal-500 hover:from-emerald-400 hover:to-teal-400 text-slate-950 font-black text-xs rounded-2xl transition-all shadow-lg shadow-emerald-500/30 flex items-center gap-2">
                            <span>مشاهده نتایج و کارنامه‌ها</span>
                            <span>←</span>
                        </a>
                    </div>
                </div>

                <!-- Live Library & Books Bank Monitor -->
                <div class="p-6 md:p-8 rounded-[2.5rem] bg-gradient-to-r from-amber-950 via-slate-900 to-amber-900 border-2 border-amber-500/40 text-white shadow-xl relative overflow-hidden flex flex-col lg:flex-row items-center justify-between gap-6">
                    <div class="absolute -right-10 -bottom-10 w-48 h-48 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>
                    <div class="flex items-center gap-5 relative z-10">
                        <div class="w-16 h-16 rounded-2xl bg-amber-500/20 border border-amber-400/40 flex items-center justify-center text-3xl shadow-lg shadow-amber-500/20 flex-shrink-0">
                            📚
                        </div>
                        <div>
                            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-500/20 text-amber-300 text-xs font-black mb-2 border border-amber-500/30">
                                <span>بانک کتب و جزوات بنیاد حکمت</span>
                                <?php if ($library_pending_requests > 0): ?>
                                    <span class="px-2 py-0.5 rounded-full bg-rose-500 text-white text-[10px] animate-pulse font-bold"><?php echo toFarsiDigits($library_pending_requests); ?> درخواست در انتظار</span>
                                <?php endif; ?>
                            </div>
                            <h3 class="text-base md:text-lg font-black text-white leading-relaxed">
                                مجموعاً <span class="text-amber-300 font-mono text-xl"><?php echo toFarsiDigits($library_total_books); ?></span> جلد کتاب و جزوه در بنیاد ثبت شده است (<span class="text-emerald-300 font-mono text-xl"><?php echo toFarsiDigits($library_available_books); ?></span> در مخزن و <span class="text-blue-300 font-mono text-xl"><?php echo toFarsiDigits($library_borrowed_books); ?></span> در دست امانت).
                            </h3>
                            <p class="text-xs text-gray-300 mt-1">
                                کارتابل اختصاص و امانت توسط خانم فرتاش و خانم عباسی مدیریت شده و برای اعضای محترم هیئت مدیره قابل نظارت است.
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3 relative z-10 w-full lg:w-auto justify-end flex-shrink-0">
                        <a href="/admin/library.php" class="px-6 py-3 bg-gradient-to-r from-amber-400 to-amber-500 hover:from-amber-300 hover:to-amber-400 text-slate-950 font-black text-xs rounded-2xl transition-all shadow-lg shadow-amber-500/30 flex items-center gap-2">
                            <span>ورود به بانک کتاب و امانات</span>
                            <span>←</span>
                        </a>
                    </div>
                </div>

                <!-- Live Portals Access Hub (Admin / Supervisor View) -->
                <div class="bg-white rounded-[3rem] p-8 md:p-10 shadow-xl border border-gray-100">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8 pb-6 border-b border-gray-100">
                        <div>
                            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-indigo-50 text-indigo-700 text-xs font-black mb-2 border border-indigo-100">
                                <span>🌐</span>
                                <span>دیدگاه ناظر و مدیرعامل</span>
                                <span class="w-1.5 h-1.5 rounded-full bg-indigo-500 animate-pulse"></span>
                            </div>
                            <h3 class="text-xl md:text-2xl font-black text-primary-900 flex items-center gap-3">
                                <span>👁️</span> رصد زنده و ورود به پورتال‌های کاربران
                            </h3>
                            <p class="text-xs text-gray-500 mt-1">
                                دسترسی مستقیم و بدون واسطه به پرتال اختصاصی هر یک از دانش‌آموزان و خیرین، دقیقاً از دید خود کاربر
                            </p>
                        </div>
                        <div class="flex items-center gap-3">
                            <a href="sponsorships.php" class="px-5 py-2.5 bg-teal-50 hover:bg-teal-100 text-teal-700 border border-teal-200 text-xs font-black rounded-2xl transition-all flex items-center gap-2 shadow-sm">
                                <span>🤝</span>
                                <span>مدیریت پیوند بورس‌ها (<?php echo toFarsiDigits($total_sponsorships_count); ?> فعال)</span>
                            </a>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                        
                        <!-- CARD 1: STUDENT PORTAL -->
                        <div class="bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900 text-white rounded-[2.5rem] p-8 shadow-xl border border-indigo-500/30 flex flex-col justify-between relative overflow-hidden group">
                            <div class="absolute -right-10 -top-10 w-40 h-40 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>
                            <div>
                                <div class="flex items-center justify-between mb-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-12 h-12 rounded-2xl bg-indigo-500/20 border border-indigo-400/30 flex items-center justify-center text-2xl shadow-inner">
                                            🎒
                                        </div>
                                        <div>
                                            <h4 class="text-lg font-black text-white">پورتال اختصاصی دانش‌آموزان</h4>
                                            <span class="text-[10px] text-indigo-300 font-bold">دید اختصاصی دانش‌آموز (کارنامه‌ها، کتاب‌ها، گزارش رشد)</span>
                                        </div>
                                    </div>
                                    <span class="text-[10px] bg-indigo-500/30 text-indigo-200 px-2.5 py-1 rounded-full font-black border border-indigo-400/20">
                                        <?php echo toFarsiDigits(count($portal_students)); ?> دانش‌آموز
                                    </span>
                                </div>
                                <p class="text-xs text-gray-300 leading-relaxed mb-6">
                                    می‌توانید هر یک از دانش‌آموزان را انتخاب نمایید تا وارد پورتال اختصاصی وی شوید و کارنامه‌های ثبت‌شده، ملزومات درسی و پیام‌های منتورینگ را بررسی کنید.
                                </p>
                                
                                <div class="space-y-3">
                                    <label class="block text-[11px] font-bold text-indigo-200">انتخاب دانش‌آموز مورد نظر:</label>
                                    <div class="flex flex-col sm:flex-row gap-2">
                                        <select id="select_student_dashboard" class="flex-1 bg-black/40 border border-white/20 rounded-2xl px-4 py-3 text-xs text-white font-bold focus:outline-none focus:ring-2 focus:ring-indigo-400">
                                            <?php
                                            $curr_group = '';
                                            $group_labels = [
                                                'active' => '🟢 دانش‌آموزان تحت پوشش (فعال)',
                                                'university' => '🔵 دانشجویان تحت پوشش (آموزش عالی)',
                                                'graduated' => '🟣 فارغ‌التحصیلان',
                                                'exited' => '🔴 خروج از بورس (غیرفعال)'
                                            ];
                                            foreach ($portal_students as $pst):
                                                $st_status = $pst['status'] ?: 'active';
                                                if ($curr_group !== $st_status):
                                                    if ($curr_group !== '') echo '</optgroup>';
                                                    $curr_group = $st_status;
                                                    echo '<optgroup label="' . ($group_labels[$st_status] ?? 'سایر وضعیت‌ها') . '" class="bg-slate-900 text-teal-300 font-black">';
                                                endif;
                                            ?>
                                            <option value="<?php echo $pst['id']; ?>" class="bg-slate-800 text-white py-1">
                                                <?php echo htmlspecialchars($pst['name'] . ' ' . $pst['surname'] . ' (کد ' . ($pst['code'] ?: $pst['id']) . ' - ' . ($pst['grade'] ?: '---') . ')'); ?>
                                            </option>
                                            <?php endforeach; ?>
                                            <?php if ($curr_group !== '') echo '</optgroup>'; ?>
                                        </select>
                                        <button type="button" onclick="goToStudentPortal()" class="px-5 py-3 bg-gradient-to-r from-indigo-500 to-teal-500 hover:from-indigo-600 hover:to-teal-600 text-white font-black text-xs rounded-2xl transition-all shadow-lg flex items-center justify-center gap-1.5 shrink-0">
                                            <span>ورود به پرتال</span>
                                            <span>←</span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="pt-6 border-t border-white/10 mt-6 flex flex-wrap items-center justify-between gap-3 text-[11px] text-gray-400">
                                <?php if (can_add_student()): ?>
                                <a href="../people-list.php?open_modal=1" class="px-4 py-2.5 bg-gradient-to-r from-teal-500 to-emerald-500 hover:from-teal-400 hover:to-emerald-400 text-white font-black text-xs rounded-xl shadow-lg transition-all flex items-center gap-1.5 hover:-translate-y-0.5">
                                    <span class="text-base leading-none">+</span>
                                    <span>افزودن مددجوی جدید (تکی و گروهی)</span>
                                </a>
                                <?php endif; ?>
                                <a href="../people-list.php" class="text-teal-300 hover:text-white font-bold text-xs flex items-center gap-1">
                                    <span>لیست کامل مددجویان (<?php echo toFarsiDigits($total_students); ?>)</span>
                                    <span>←</span>
                                </a>
                            </div>
                        </div>

                        <!-- CARD 2: DONOR PORTAL -->
                        <div class="bg-gradient-to-br from-teal-950 via-slate-900 to-emerald-950 text-white rounded-[2.5rem] p-8 shadow-xl border border-teal-500/30 flex flex-col justify-between relative overflow-hidden group">
                            <div class="absolute -right-10 -top-10 w-40 h-40 bg-teal-500/10 rounded-full blur-3xl pointer-events-none"></div>
                            <div>
                                <div class="flex items-center justify-between mb-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-12 h-12 rounded-2xl bg-teal-500/20 border border-teal-400/30 flex items-center justify-center text-2xl shadow-inner">
                                            💎
                                        </div>
                                        <div>
                                            <h4 class="text-lg font-black text-white">پورتال اختصاصی خیرین و حامیان</h4>
                                            <span class="text-[10px] text-teal-300 font-bold">دید اختصاصی حامی (با حفظ کامل محرمانگی نخبگان)</span>
                                        </div>
                                    </div>
                                    <span class="text-[10px] bg-teal-500/30 text-teal-200 px-2.5 py-1 rounded-full font-black border border-teal-400/20">
                                        <?php echo toFarsiDigits(count($portal_donors)); ?> نیکوکار
                                    </span>
                                </div>
                                <p class="text-xs text-gray-300 leading-relaxed mb-6">
                                    مشاهده دقیق صفحه خیر شامل پرونده دانش‌آموزان تحت پوشش با نام مستعار، کارنامه‌های تحصیلی، خلاصه واریزی‌ها و کانال پیام‌رسانی مشاوره‌ای.
                                </p>
                                
                                <div class="space-y-3">
                                    <label class="block text-[11px] font-bold text-teal-200">انتخاب خیر / حامی بورس:</label>
                                    <div class="flex flex-col sm:flex-row gap-2">
                                        <select id="select_donor_dashboard" class="flex-1 bg-black/40 border border-white/20 rounded-2xl px-4 py-3 text-xs text-white font-bold focus:outline-none focus:ring-2 focus:ring-teal-400">
                                            <?php foreach ($portal_donors as $pdn): ?>
                                            <option value="<?php echo $pdn['id']; ?>">
                                                <?php echo htmlspecialchars($pdn['name'] . ' ' . $pdn['surname'] . (!empty($pdn['phone']) ? ' (' . $pdn['phone'] . ')' : '')); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="button" onclick="goToDonorPortal()" class="px-5 py-3 bg-gradient-to-r from-teal-500 to-emerald-500 hover:from-teal-600 hover:to-emerald-600 text-slate-950 font-black text-xs rounded-2xl transition-all shadow-lg flex items-center justify-center gap-1.5 shrink-0">
                                            <span>ورود به پرتال</span>
                                            <span>←</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="pt-6 border-t border-white/10 mt-6 flex items-center justify-between text-[11px] text-gray-400">
                                <span class="flex items-center gap-1">
                                    <span>🛡️</span>
                                    <span>محرمانگی: عدم نمایش مشخصات واقعی، تلفن و آدرس</span>
                                </span>
                                <a href="../donor-dashboard.php" class="text-emerald-300 hover:underline font-bold flex items-center gap-1">
                                    <span>مشاهده پورتال نمونه</span>
                                    <span>↗</span>
                                </a>
                            </div>
                        </div>

                    </div>
                </div>

                <script>
                function goToStudentPortal() {
                    var sel = document.getElementById('select_student_dashboard');
                    if (sel && sel.value) {
                        window.location.href = '../student-dashboard.php?student_id=' + sel.value;
                    }
                }
                function goToDonorPortal() {
                    var sel = document.getElementById('select_donor_dashboard');
                    if (sel && sel.value) {
                        window.location.href = '../donor-dashboard.php?donor_id=' + sel.value;
                    }
                }
                </script>

                <!-- Navigation Hub -->
                <div class="bg-primary-900 rounded-[3.5rem] p-12 text-white shadow-2xl relative overflow-hidden">
                    <div class="absolute -right-20 -top-20 w-80 h-80 bg-teal-500/10 rounded-full blur-3xl"></div>
                    <h2 class="text-3xl font-black mb-8 relative z-10">مدیریت مستقیم</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-6 relative z-10">
                        <a href="../people-list.php" class="bg-white/10 hover:bg-white/20 p-8 rounded-[2rem] border border-white/5 transition-all group">
                            <div class="text-3xl mb-4 group-hover:scale-110 transition-transform">📋</div>
                            <h4 class="text-xl font-bold">لیست مددجویان</h4>
                            <p class="text-[10px] text-white/50 mt-2">ویرایش پرونده‌ها و مدیریت نمرات</p>
                        </a>
                        <?php if ($can_finance): ?>
                        <a href="financial.php" class="bg-white/10 hover:bg-white/20 p-8 rounded-[2rem] border border-white/5 transition-all group">
                            <div class="text-3xl mb-4 group-hover:scale-110 transition-transform">⚖️</div>
                            <h4 class="text-xl font-bold">دفتر حسابداری مالی</h4>
                            <p class="text-[10px] text-white/50 mt-2">تراز مالی، واریزی‌ها و فیش دیجیتال</p>
                        </a>
                        <?php endif; ?>
                        <a href="sponsorships.php" class="bg-white/10 hover:bg-white/20 p-8 rounded-[2rem] border border-white/10 transition-all group">
                            <div class="text-3xl mb-4 group-hover:scale-110 transition-transform">🤝</div>
                            <h4 class="text-xl font-bold">بورس و منتورینگ</h4>
                            <p class="text-[10px] text-white/50 mt-2">تخصیص حامی، کارنامه و تایید پیام‌ها</p>
                        </a>
                        <a href="diamond-candidates.php" class="bg-emerald-500/15 hover:bg-emerald-500/25 p-8 rounded-[2rem] border border-emerald-500/40 transition-all group relative">
                            <div class="absolute top-4 left-4 bg-emerald-500 text-black text-[8px] font-black px-2 py-0.5 rounded-full uppercase tracking-tighter animate-pulse">استعدادیابی</div>
                            <div class="text-3xl mb-4 group-hover:scale-110 transition-transform">💎</div>
                            <h4 class="text-xl font-bold text-emerald-300">گنج‌های پنهان</h4>
                            <p class="text-[10px] text-white/50 mt-2">غربالگری هوش، داوطلبان و نمرات آزمون</p>
                        </a>
                        <?php if ($can_edit_finance): ?>
                        <a href="bursary-payments.php" class="bg-white/10 hover:bg-white/20 p-8 rounded-[2rem] border border-teal-500/30 transition-all group relative">
                            <div class="absolute top-4 left-4 bg-teal-500 text-[8px] font-black px-2 py-0.5 rounded-full uppercase tracking-tighter animate-pulse">جدید</div>
                            <div class="text-3xl mb-4 group-hover:scale-110 transition-transform">💳</div>
                            <h4 class="text-xl font-bold">پرداخت بورسیه</h4>
                            <p class="text-[10px] text-white/50 mt-2">کسورات اقساط و امضای الکترونیک ماهانه</p>
                        </a>
                        <a href="parsian-settings.php" class="bg-white/10 hover:bg-white/20 p-8 rounded-[2rem] border border-red-500/30 transition-all group relative">
                            <div class="absolute top-4 left-4 bg-red-500 text-[8px] font-black px-2 py-0.5 rounded-full uppercase tracking-tighter">بانک</div>
                            <div class="text-3xl mb-4 group-hover:scale-110 transition-transform">🏦</div>
                            <h4 class="text-xl font-bold">وب‌سرویس پارسیان</h4>
                            <p class="text-[10px] text-white/50 mt-2">اتصال مستقیم API و بچ پرداخت گروهی</p>
                        </a>
                        <?php endif; ?>
                        <a href="donor-analytics.php" class="bg-white/10 hover:bg-white/20 p-8 rounded-[2rem] border border-teal-400/40 transition-all group relative">
                            <div class="absolute top-4 left-4 bg-emerald-500 text-[8px] font-black px-2 py-0.5 rounded-full uppercase tracking-tighter">هوش مالی</div>
                            <div class="text-3xl mb-4 group-hover:scale-110 transition-transform">📊</div>
                            <h4 class="text-xl font-bold">تحلیل رفتار خیرین</h4>
                            <p class="text-[10px] text-white/50 mt-2">رصد روند افزایشی/کاهشی و حامیان راکد</p>
                        </a>
                        <a href="donor-reminders.php" class="bg-teal-500/15 hover:bg-teal-500/25 p-8 rounded-[2rem] border border-teal-500/30 transition-all group relative">
                            <div class="absolute top-4 left-4 bg-teal-500 text-white text-[8px] font-black px-2 py-0.5 rounded-full uppercase tracking-tighter">پیامک خودکار</div>
                            <div class="text-3xl mb-4 group-hover:scale-110 transition-transform">🔔</div>
                            <h4 class="text-xl font-bold text-teal-200">یادآوری دوره‌ای خیرین</h4>
                            <p class="text-[10px] text-white/50 mt-2">زمان‌بندی پیامک ماهانه/فصلی و ارسال خودکار</p>
                        </a>
                        <a href="../donors-list.php" class="bg-white/10 hover:bg-white/20 p-8 rounded-[2rem] border border-white/5 transition-all group">
                            <div class="text-3xl mb-4 group-hover:scale-110 transition-transform">💎</div>
                            <h4 class="text-xl font-bold">پورتال نیکوکاران</h4>
                            <p class="text-[10px] text-white/50 mt-2">رتبه‌بندی حامیان و تعامل مالی</p>
                        </a>
                        <a href="../expenses-list.php" class="bg-white/10 hover:bg-white/20 p-8 rounded-[2rem] border border-white/5 transition-all group">
                            <div class="text-3xl mb-4 group-hover:scale-110 transition-transform">💸</div>
                            <h4 class="text-xl font-bold">گزارش ریز هزینه‌ها</h4>
                            <p class="text-[10px] text-white/50 mt-2">هزینه‌کرد مددجویان و سرفصل‌ها</p>
                        </a>
                        <a href="/admin/library.php" class="bg-teal-500/15 hover:bg-teal-500/25 p-8 rounded-[2rem] border border-teal-500/30 transition-all group relative">
                            <div class="absolute top-4 left-4 bg-teal-500 text-white text-[8px] font-black px-2 py-0.5 rounded-full uppercase tracking-tighter">کتابخانه</div>
                            <div class="text-3xl mb-4 group-hover:scale-110 transition-transform">📚</div>
                            <h4 class="text-xl font-bold text-teal-200">بانک کتاب و امانات (۲۰۱ جلد)</h4>
                            <p class="text-[10px] text-white/50 mt-2">مخزن کتب، امانات و صف‌های انتظار هوشمند</p>
                        </a>
                        <?php if ($can_view_logs): ?>
                        <a href="audit-logs.php" class="bg-amber-500/15 hover:bg-amber-500/25 p-8 rounded-[2rem] border border-amber-500/30 transition-all group relative">
                            <div class="absolute top-4 left-4 bg-amber-500 text-black text-[8px] font-black px-2 py-0.5 rounded-full uppercase tracking-tighter">نظارتی</div>
                            <div class="text-3xl mb-4 group-hover:scale-110 transition-transform">📜</div>
                            <h4 class="text-xl font-bold text-amber-300">گزارش لاگ و رهگیری</h4>
                            <p class="text-[10px] text-white/50 mt-2">رهگیری تغییرات، منشی و کاربران</p>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if ($can_view_logs): ?>
                <!-- Live Audit & Activity Monitor on CEO Dashboard -->
                <div class="bg-white rounded-[3rem] p-8 md:p-10 shadow-xl border border-gray-100">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 pb-6 border-b border-gray-100">
                        <div class="flex items-center gap-4">
                            <div class="w-12 h-12 rounded-2xl bg-amber-50 text-amber-600 border border-amber-200 flex items-center justify-center text-2xl shadow-sm">
                                📡
                            </div>
                            <div>
                                <h3 class="text-xl font-black text-slate-900 flex items-center gap-2">
                                    میز پایش زنده و رخدادهای اخیر سامانه
                                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-ping"></span>
                                </h3>
                                <p class="text-xs text-gray-500 font-bold mt-1">
                                    ثبت و رهگیری زنده ورودها، ویرایش پرونده‌ها، درخواست کتاب و فعالیت‌های کادر بنیاد
                                </p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-xs font-black bg-teal-50 text-teal-700 px-3.5 py-1.5 rounded-xl border border-teal-100 shadow-sm">
                                ⚡ <?php echo toFarsiDigits($today_logs_count); ?> فعالیت امروز
                            </span>
                            <a href="audit-logs.php" class="text-xs font-black bg-slate-900 hover:bg-slate-800 text-white px-4 py-2 rounded-xl transition-all shadow-md flex items-center gap-1.5">
                                <span>مدیریت و فیلتر کامل لاگ‌ها</span>
                                <span>←</span>
                            </a>
                        </div>
                    </div>

                    <?php if (empty($recent_logs)): ?>
                        <div class="text-center py-10 text-gray-400 text-xs font-bold">
                            هنوز رخدادی ثبت نشده است.
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full text-right text-xs">
                                <thead>
                                    <tr class="text-gray-400 border-b border-gray-100 text-[11px]">
                                        <th class="py-3 px-3">زمان</th>
                                        <th class="py-3 px-3">کاربر و سمت</th>
                                        <th class="py-3 px-3">اقدام</th>
                                        <th class="py-3 px-3">شرح جزئیات رخداد</th>
                                        <th class="py-3 px-3 text-center">آی‌پی</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-50">
                                    <?php foreach ($recent_logs as $rl): ?>
                                    <tr class="hover:bg-gray-50/80 transition-colors">
                                        <td class="py-3.5 px-3 text-gray-600 font-bold whitespace-nowrap">
                                            <span class="text-emerald-700 font-black"><?php echo time_ago_fa($rl['created_at']); ?></span>
                                            <span class="text-[10px] text-gray-400 block font-mono"><?php echo formatJalaliDateTime($rl['created_at']); ?></span>
                                        </td>
                                        <td class="py-3.5 px-3 whitespace-nowrap">
                                            <div class="flex items-center gap-2">
                                                <span class="font-black text-slate-800"><?php echo htmlspecialchars($rl['user_name'] ?: $rl['username']); ?></span>
                                                <?php echo get_role_badge_html($rl['role'], $rl['username']); ?>
                                            </div>
                                            <span class="text-[10px] text-gray-400 font-mono">@<?php echo htmlspecialchars($rl['username']); ?></span>
                                        </td>
                                        <td class="py-3.5 px-3 font-black text-slate-800 whitespace-nowrap">
                                            <?php echo htmlspecialchars($rl['action']); ?>
                                        </td>
                                        <td class="py-3.5 px-3 text-gray-600 leading-relaxed max-w-xs">
                                            <?php echo htmlspecialchars($rl['description']); ?>
                                        </td>
                                        <td class="py-3.5 px-3 text-center text-[10px] text-gray-400 font-mono" dir="ltr">
                                            <?php echo htmlspecialchars($rl['ip_address'] ?: '127.0.0.1'); ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Inactive Alerts Area -->
            <div class="w-full lg:w-96 space-y-6">
                <div class="bg-rose-50 rounded-[3rem] p-8 border border-rose-100 shadow-sm relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-full h-1 bg-red-400"></div>
                    <div class="flex items-center gap-3 mb-6">
                        <span class="text-2xl">⚠️</span>
                        <h3 class="text-lg font-black text-rose-900">نیکوکاران غیرفعال</h3>
                    </div>
                    <p class="text-xs text-rose-700 leading-relaxed mb-6 font-bold">افرادی که بیش از ۳ ماه است واریزی نداشته‌اند:</p>
                    
                    <div class="space-y-4">
                        <?php foreach ($inactive_donors as $idr): ?>
                        <div class="bg-white p-4 rounded-2xl flex items-center justify-between border border-rose-100 shadow-sm">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 bg-rose-100 rounded-lg flex items-center justify-center text-xs">👤</div>
                                <div class="text-[10px] font-black text-rose-900"><?php echo $idr['name'] . ' ' . $idr['surname']; ?></div>
                            </div>
                            <a href="../donor-detail.php?id=<?php echo $idr['id']; ?>" class="text-[10px] text-teal-600 font-bold hover:underline">پیگیری</a>
                        </div>
                        <?php endforeach; ?>
                        
                        <button class="w-full py-4 bg-rose-500 text-white text-[10px] font-black rounded-2xl shadow-lg shadow-rose-200 mt-4">ارسال پیامک یادآوری به همه</button>
                    </div>
                </div>

                <div class="bg-white rounded-[3rem] p-8 border border-gray-100 shadow-sm text-center flex flex-col justify-between">
                    <div>
                        <div class="text-4xl mb-3 text-teal-600">🎂</div>
                        <h3 class="text-sm font-black text-gray-900 mb-1">تقویم و یادآور هوشمند تولدها</h3>
                        <p class="text-[10px] text-gray-400 font-bold mb-4">امروز: <?php echo toFarsiDigits($cur_jd) . ' ' . ($persian_months_map[$cur_jm] ?? '') . ' ' . toFarsiDigits($cur_jy); ?></p>
                        
                        <div class="space-y-3 text-right">
                            <?php if (!empty($today_birthdays)): ?>
                                <div class="bg-gradient-to-r from-teal-50 to-emerald-50 border border-teal-200 rounded-2xl p-3 text-center mb-3">
                                    <span class="text-xs font-black text-teal-800 block mb-2">🎉 تولدهای امروز:</span>
                                    <?php foreach ($today_birthdays as $b): ?>
                                    <div class="flex items-center justify-between bg-white p-2.5 rounded-xl shadow-xs border border-teal-100 mb-1">
                                        <div class="flex items-center gap-2">
                                            <span><?php echo $b['type'] === 'student' ? '👧' : '💎'; ?></span>
                                            <strong class="text-xs text-primary-900"><?php echo htmlspecialchars($b['name'] . ' ' . $b['surname']); ?></strong>
                                        </div>
                                        <span class="text-[9px] bg-teal-600 text-white px-2.5 py-0.5 rounded-full font-bold">تولد مبارک 🎈</span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="bg-gray-50 border border-gray-100 rounded-2xl p-3 text-center">
                                    <p class="text-[10px] text-gray-500 font-bold">امروز تولد ثبت‌شده‌ای در سامانه وجود ندارد.</p>
                                </div>
                            <?php endif; ?>

                            <!-- Upcoming Birthdays in next 30 days -->
                            <?php if (!empty($upcoming_birthdays)): ?>
                                <div class="pt-2">
                                    <span class="text-[11px] font-black text-gray-700 block mb-2">⏳ نزدیک‌ترین تولدهای پیش‌رو (۳۰ روز آینده):</span>
                                    <div class="space-y-2">
                                        <?php foreach (array_slice($upcoming_birthdays, 0, 4) as $ub): ?>
                                        <div class="flex items-center justify-between bg-slate-50 hover:bg-teal-50/50 p-2.5 rounded-xl border border-gray-100 transition-colors">
                                            <div class="flex items-center gap-2">
                                                <span class="text-sm"><?php echo $ub['type'] === 'student' ? '👧' : '💎'; ?></span>
                                                <div>
                                                    <a href="<?php echo $ub['type'] === 'student' ? 'person-detail.php?id=' . $ub['id'] : 'donor-detail.php?id=' . $ub['id']; ?>" class="text-xs font-black text-primary-900 hover:text-teal-600 hover:underline block">
                                                        <?php echo htmlspecialchars($ub['name'] . ' ' . $ub['surname']); ?>
                                                    </a>
                                                    <span class="text-[9px] text-gray-400 font-bold"><?php echo toFarsiDigits($ub['day']) . ' ' . $ub['month_name']; ?></span>
                                                </div>
                                            </div>
                                            <span class="text-[9px] bg-indigo-50 text-indigo-700 border border-indigo-100 px-2 py-0.5 rounded-full font-bold">
                                                <?php echo toFarsiDigits($ub['days_left']); ?> روز دیگر
                                            </span>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mt-4 pt-4 border-t border-gray-100 text-[9px] text-gray-400 leading-relaxed text-center">
                        <span class="block font-bold text-gray-500 mb-0.5">وضعیت ثبت تاریخ تولد در پایگاه داده:</span>
                        <span>مددجویان: <strong class="text-teal-700"><?php echo toFarsiDigits($total_students_with_bday); ?> نفر</strong></span>
                        <span class="mx-1">•</span>
                        <span>خیرین: <strong class="text-rose-600"><?php echo toFarsiDigits($total_donors_with_bday); ?> نفر</strong> (ثبت نشده)</span>
                    </div>
                </div>
            </div>

        </div>
    </main>


    <!-- Change Password Modal -->
    <div id="change-pwd-modal" class="fixed inset-0 bg-black/75 backdrop-blur-md z-[200] hidden items-center justify-center p-4">
        <div class="bg-slate-900 border border-white/15 rounded-[2.5rem] max-w-md w-full p-8 text-white shadow-2xl relative">
            <button onclick="closeChangePasswordModal()" class="absolute top-6 left-6 text-gray-400 hover:text-white text-xl font-bold bg-white/5 rounded-full w-9 h-9 flex items-center justify-center transition-colors">✕</button>
            <div class="text-center mb-6">
                <div class="w-14 h-14 bg-teal-500/20 text-teal-400 rounded-2xl flex items-center justify-center text-2xl mx-auto mb-3 border border-teal-500/30">
                    🔑
                </div>
                <h3 class="text-xl font-black">تغییر رمز عبور</h3>
                <p class="text-xs text-gray-400 mt-1">رمز عبور حساب کاربری خود را به‌روزرسانی کنید</p>
            </div>
            <form id="change-pwd-form" onsubmit="handlePasswordChange(event)" class="space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-300 mb-1.5 text-right">رمز عبور فعلی</label>
                    <input type="password" id="current-pwd" required class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-teal-400 transition-colors text-left dir-ltr" style="direction: ltr;" placeholder="رمز عبور فعلی...">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-300 mb-1.5 text-right">رمز عبور جدید</label>
                    <input type="password" id="new-pwd" required minlength="4" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-teal-400 transition-colors text-left dir-ltr" style="direction: ltr;" placeholder="حداقل ۴ کاراکتر...">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-300 mb-1.5 text-right">تکرار رمز عبور جدید</label>
                    <input type="password" id="confirm-pwd" required minlength="4" class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-white focus:outline-none focus:border-teal-400 transition-colors text-left dir-ltr" style="direction: ltr;" placeholder="تکرار رمز عبور جدید...">
                </div>
                <div id="pwd-msg" class="text-xs text-center hidden p-3 rounded-xl"></div>
                <button type="submit" id="pwd-submit-btn" class="w-full py-3.5 bg-gradient-to-r from-teal-500 to-emerald-600 hover:from-teal-600 hover:to-emerald-700 text-white font-black text-sm rounded-xl transition-all shadow-lg shadow-teal-500/20">
                    ذخیره رمز عبور جدید
                </button>
            </form>
        </div>
    </div>

<script>
    function openChangePasswordModal() {
        const modal = document.getElementById('change-pwd-modal');
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.getElementById('pwd-msg').classList.add('hidden');
        document.getElementById('change-pwd-form').reset();
    }

    function closeChangePasswordModal() {
        const modal = document.getElementById('change-pwd-modal');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }

    async function handlePasswordChange(e) {
        e.preventDefault();
        const current_password = document.getElementById('current-pwd').value;
        const new_password = document.getElementById('new-pwd').value;
        const confirm_password = document.getElementById('confirm-pwd').value;
        const msgBox = document.getElementById('pwd-msg');
        const submitBtn = document.getElementById('pwd-submit-btn');

        if (new_password !== confirm_password) {
            msgBox.className = 'text-xs text-center p-3 rounded-xl bg-red-500/20 text-red-300 border border-red-500/30';
            msgBox.innerText = 'رمز عبور جدید و تکرار آن یکسان نیستند.';
            msgBox.classList.remove('hidden');
            return;
        }

        submitBtn.disabled = true;
        submitBtn.innerText = 'در حال ذخیره...';

        try {
            const res = await fetch('../api-change-password.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ current_password, new_password, confirm_password })
            });
            const data = await res.json();

            if (data.success) {
                msgBox.className = 'text-xs text-center p-3 rounded-xl bg-emerald-500/20 text-emerald-300 border border-emerald-500/30';
                msgBox.innerText = data.message;
                msgBox.classList.remove('hidden');
                setTimeout(() => {
                    closeChangePasswordModal();
                }, 1800);
            } else {
                msgBox.className = 'text-xs text-center p-3 rounded-xl bg-red-500/20 text-red-300 border border-red-500/30';
                msgBox.innerText = data.message || 'خطایی رخ داد.';
                msgBox.classList.remove('hidden');
            }
        } catch (err) {
            msgBox.className = 'text-xs text-center p-3 rounded-xl bg-red-500/20 text-red-300 border border-red-500/30';
            msgBox.innerText = 'خطای ارتباط با سرور. لطفاً دوباره تلاش کنید.';
            msgBox.classList.remove('hidden');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerText = 'ذخیره رمز عبور جدید';
        }
    }

  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/sw.js');
    });
  }
</script>

</body>
</html>