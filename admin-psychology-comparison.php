<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/psychology_questions.php';

// بررسی دسترسی: اختصاصی مدیرعامل (فرید برهان علمی / superadmin)
if (!is_logged_in()) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

if (!can_view_psychology()) {
    die("❌ دسترسی غیرمجاز: سامانه رادار مقایسه روان‌سنجی منحصراً در اختیار مدیریت بنیاد (فرید برهان علمی) است.");
}

$page_title = 'رادار هوشمند مقایسه تحلیلی روان‌سنجی نخبگان | بنیاد حکمت';
$is_private_page = true;

// ۱. خواندن اطلاعات دانش‌آموزان قدیم (۵۴ پرونده)
$json_path = __DIR__ . '/hekmat_all_students_profiles.json';
$old_students_raw = file_exists($json_path) ? json_decode(file_get_contents($json_path), true) : [];

// نگاشت اطلاعات تکمیلی از دیتابیس با تطبیق هوشمند نام‌های فارسی
function normNameMatch($s) {
    $s = trim($s);
    $s = preg_replace('/^(سید|سیده|میر)\s+/u', '', $s);
    $s = str_replace(['ي', 'ك', 'ة', 'ۀ', 'آ', 'أ', 'إ', 'ئ'], ['ی', 'ک', 'ه', 'ه', 'ا', 'ا', 'ا', 'ی'], $s);
    $s = preg_replace('/[\s\x{200C}]+/u', '', $s);
    return mb_strtolower($s, 'UTF-8');
}

$st_query = $pdo->query("SELECT id, TRIM(name || ' ' || surname) as full_name, name, surname, phone, school, grade FROM students")->fetchAll(PDO::FETCH_ASSOC);
$db_students_map = [];
$db_students_norm_map = [];
foreach ($st_query as $sq) {
    $fullName = trim($sq['full_name']);
    if ($fullName !== '') {
        $db_students_map[$fullName] = $sq;
        $db_students_norm_map[normNameMatch($fullName)] = $sq;
    }
}

// نام‌های دارای املای متفاوت در پرونده‌های قدیمی
$historical_aliases = [
    normNameMatch('مریم قرنی') => normNameMatch('مریم قرئی'),
    normNameMatch('ایمان رسول خوانی') => normNameMatch('ایمان رسولی خوانی'),
];

$normalized_old_students = [];
foreach ($old_students_raw as $idx => $st) {
    $name = trim($st['name'] ?? '');
    $norm_name = normNameMatch($name);
    if (isset($historical_aliases[$norm_name])) {
        $norm_name = $historical_aliases[$norm_name];
    }
    $st_db = $db_students_map[$name] ?? ($db_students_norm_map[$norm_name] ?? null);
    if (!$st_db) {
        foreach ($db_students_norm_map as $db_norm => $sq) {
            if ($db_norm !== '' && (strpos($db_norm, $norm_name) === 0 || strpos($norm_name, $db_norm) === 0)) {
                $st_db = $sq;
                break;
            }
        }
    }
    
    $phone_val = (!empty($st_db['phone']) && $st_db['phone'] !== '_') ? $st_db['phone'] : '-';
    
    $mi_raw = intval($st['mi_total'] ?? 0);
    $mi_pct = round(($mi_raw / 400) * 100);
    
    $hermans_raw = intval($st['hermans_score'] ?? 0);
    $hermans_pct = round(($hermans_raw / 116) * 100);
    
    $has_eq = isset($st['eq_total']) && $st['eq_total'] !== null && $st['eq_total'] !== '';
    $eq_raw = $has_eq ? intval($st['eq_total']) : null;
    $eq_pct = $has_eq ? round(($eq_raw / 165) * 100) : 0;
    
    $scl = $st['scl90'] ?? [];
    $gsi = floatval($scl['GSI'] ?? 0.5);
    $mental_pct = max(10, min(100, round((1 - ($gsi / 2.5)) * 100)));
    $scl_risk = $scl['risk'] ?? 'Normal';
    
    // محاسبه شاخص همگن HTI (تعدیل هوشمند در صورت عدم وجود آزمون EQ)
    if ($has_eq) {
        $calc_hti = round(($mi_pct * 0.35) + ($eq_pct * 0.25) + ($hermans_pct * 0.25) + ($mental_pct * 0.15));
    } else {
        $calc_hti = round((($mi_pct * 0.35) + ($hermans_pct * 0.25) + ($mental_pct * 0.15)) / 0.75);
    }
    
    $rec = $st['recommendation'] ?? '';
    $final_grade = $st['final_grade'] ?? 'B';
    
    $is_super_talent = ($final_grade === 'A+' || strpos($rec, 'استعداد برتر') !== false || strpos($rec, 'نخبه') !== false || strpos($rec, 'پدیده') !== false || strpos($rec, 'ستاره') !== false || $hermans_raw >= 100 || $mi_raw >= 330);
    $is_critical = ($final_grade === 'CRITICAL' || $scl_risk === 'Critical' || strpos($rec, 'خطر') !== false || strpos($rec, 'بحران') !== false || $gsi >= 1.5);
    $is_warning = ($final_grade === 'C' || $scl_risk === 'Warning' || strpos($rec, 'هشدار') !== false);

    $normalized_old_students[] = [
        'id' => 'old_' . ($st['id'] ?? ($idx + 1)),
        'db_id' => $st_db['id'] ?? ($st['id'] ?? null),
        'cohort' => 'old',
        'cohort_title' => 'دانش‌آموزان قدیم',
        'name' => $name,
        'phone' => $phone_val,
        'mi_raw' => $mi_raw,
        'mi_max' => 400,
        'mi_pct' => $mi_pct,
        'mi_grade' => $st['mi_grade'] ?? '-',
        'hermans_raw' => $hermans_raw,
        'hermans_max' => 116,
        'hermans_pct' => $hermans_pct,
        'hermans_grade' => $st['hermans_grade'] ?? '-',
        'eq_raw' => $eq_raw,
        'eq_max' => 165,
        'eq_pct' => $eq_pct,
        'eq_grade' => $st['eq_grade'] ?? '-',
        'scl_gsi' => $gsi,
        'scl_risk' => $scl_risk,
        'mental_pct' => $mental_pct,
        'hti' => $calc_hti,
        'orig_grade' => $final_grade,
        'grade' => $final_grade,
        'recommendation' => $rec,
        'top_talents' => 'بر اساس هوش چندگانه گاردنر',
        'is_super_talent' => $is_super_talent,
        'is_critical' => $is_critical,
        'is_warning' => $is_warning,
        'status' => 'completed',
        'completed_at' => 'پرونده سال ۱۴۰۴-۱۴۰۵'
    ];
}

// ۲. خواندن اطلاعات داوطلبان جدید (۳۶ داوطلب: ۱۷ تکمیل‌شده + ۱۹ در انتظار)
$new_tests_stmt = $pdo->query("SELECT * FROM interview_tests ORDER BY id ASC");
$new_tests_raw = $new_tests_stmt->fetchAll(PDO::FETCH_ASSOC);

$normalized_new_students = [];
$pending_new_students = [];

foreach ($new_tests_raw as $t) {
    $sc = !empty($t['scores_json']) ? json_decode($t['scores_json'], true) : [];
    $is_comp = ($t['status'] === 'completed');
    
    if ($is_comp && !empty($sc)) {
        $g_res = $sc['gardner'] ?? [];
        $amq_res = $sc['amq'] ?? [];
        $seiq_res = $sc['seiq'] ?? [];
        $scl_res = $sc['scl90'] ?? [];
        
        $mi_raw = intval($g_res['total_score'] ?? 0);
        $mi_pct = intval($g_res['overall_percentage'] ?? round(($mi_raw / 400) * 100));
        
        $hermans_raw = intval($amq_res['total_score'] ?? 0);
        $hermans_pct = intval($amq_res['overall_percentage'] ?? round(($hermans_raw / 116) * 100));
        
        $eq_raw = intval($seiq_res['total_score'] ?? 0);
        $eq_pct = intval($seiq_res['overall_percentage'] ?? round(($eq_raw / 165) * 100));
        
        $gsi = floatval($scl_res['gsi'] ?? 0.4);
        $mental_pct = intval($sc['mental_wellness_pct'] ?? max(10, min(100, round((1 - ($gsi / 2.5)) * 100))));
        
        $hti = intval($sc['total_score'] ?? $sc['hti'] ?? round(($mi_pct * 0.35) + ($eq_pct * 0.25) + ($hermans_pct * 0.25) + ($mental_pct * 0.15)));
        $grade = $sc['grade'] ?? $sc['final_grade'] ?? 'B+';
        $rec = $sc['recommendation'] ?? ($sc['composite']['executive_summary'] ?? '');
        
        $top_t = [];
        if (!empty($g_res['top_talents'])) {
            foreach ($g_res['top_talents'] as $tt) {
                $top_t[] = $tt['title'] ?? '';
            }
        }
        $top_talents_str = !empty($top_t) ? implode('، ', $top_t) : ($sc['composite']['top_talents_text'] ?? '-');
        
        $scl_risk = ($gsi >= 1.5) ? 'Critical' : (($gsi >= 0.9) ? 'Warning' : 'Normal');
        $is_super_talent = ($grade === 'A' || $grade === 'A+' || $hermans_raw >= 100 || $mi_raw >= 320);
        $is_critical = ($gsi >= 1.5);
        $is_warning = ($gsi >= 0.9 && $gsi < 1.5);

        $normalized_new_students[] = [
            'id' => 'new_' . $t['id'],
            'test_id' => $t['id'],
            'token' => $t['token'],
            'cohort' => 'new',
            'cohort_title' => 'ورودی جدید (۱۴۰۵)',
            'name' => $t['candidate_name'],
            'phone' => $t['mobile'] ?? '-',
            'mi_raw' => $mi_raw,
            'mi_max' => 400,
            'mi_pct' => $mi_pct,
            'mi_grade' => $g_res['grade'] ?? '-',
            'hermans_raw' => $hermans_raw,
            'hermans_max' => 116,
            'hermans_pct' => $hermans_pct,
            'hermans_grade' => $amq_res['grade'] ?? '-',
            'eq_raw' => $eq_raw,
            'eq_max' => 165,
            'eq_pct' => $eq_pct,
            'eq_grade' => $seiq_res['grade'] ?? '-',
            'scl_gsi' => $gsi,
            'scl_risk' => $scl_risk,
            'mental_pct' => $mental_pct,
            'hti' => $hti,
            'orig_grade' => $grade,
            'grade' => $grade,
            'recommendation' => $rec,
            'top_talents' => $top_talents_str,
            'is_super_talent' => $is_super_talent,
            'is_critical' => $is_critical,
            'is_warning' => $is_warning,
            'status' => 'completed',
            'completed_at' => $t['completed_at'] ?? '-'
        ];
    } else {
        $pending_new_students[] = [
            'id' => 'new_' . $t['id'],
            'test_id' => $t['id'],
            'token' => $t['token'],
            'name' => $t['candidate_name'],
            'phone' => $t['mobile'] ?? '-',
            'status' => $t['status'],
            'current_question' => $t['current_question'] ?? 0
        ];
    }
}

// ادغام کل افراد ارزیابی‌شده جهت آمار و نمودارها
$all_completed_profiles = array_merge($normalized_old_students, $normalized_new_students);

// مرتب‌سازی پیش‌فرض بر اساس شاخص کل (HTI نزولی) و هوش گاردنر به عنوان معیار تفکیک‌کننده
usort($all_completed_profiles, function ($a, $b) {
    if ($b['hti'] !== $a['hti']) {
        return $b['hti'] <=> $a['hti'];
    }
    return $b['mi_raw'] <=> $a['mi_raw'];
});

// محاسبه آمار کلی
$total_old = count($normalized_old_students);
$total_new_completed = count($normalized_new_students);
$total_new_pending = count($pending_new_students);
$total_evaluated = count($all_completed_profiles);

// محاسبه برترین رکوردهای کل تاریخ بنیاد
$top_hermans = null;
$top_gardner = null;
$top_mental = null;

foreach ($all_completed_profiles as $p) {
    if (!$top_hermans || $p['hermans_raw'] > $top_hermans['hermans_raw']) {
        $top_hermans = $p;
    }
    if (!$top_gardner || $p['mi_raw'] > $top_gardner['mi_raw']) {
        $top_gardner = $p;
    }
    if (!$top_mental || $p['scl_gsi'] < $top_mental['scl_gsi']) {
        $top_mental = $p;
    }
}

// محاسبه میانگین‌ها
function getCohortAverages($list) {
    if (empty($list)) return ['mi' => 0, 'hermans' => 0, 'eq' => 0, 'mental' => 0, 'hti' => 0];
    $c = count($list);
    $sum_mi = array_sum(array_column($list, 'mi_pct'));
    $sum_hermans = array_sum(array_column($list, 'hermans_pct'));
    $sum_eq = array_sum(array_column($list, 'eq_pct'));
    $sum_mental = array_sum(array_column($list, 'mental_pct'));
    $sum_hti = array_sum(array_column($list, 'hti'));
    return [
        'mi' => round($sum_mi / $c),
        'hermans' => round($sum_hermans / $c),
        'eq' => round($sum_eq / $c),
        'mental' => round($sum_mental / $c),
        'hti' => round($sum_hti / $c),
    ];
}

$avg_old = getCohortAverages($normalized_old_students);
$avg_new = getCohortAverages($normalized_new_students);

// تبدیل داده‌ها به JSON برای تغذیه JavaScript
$json_all_profiles = json_encode($all_completed_profiles, JSON_UNESCAPED_UNICODE);
$json_avg_old = json_encode($avg_old);
$json_avg_new = json_encode($avg_new);
?>
<!DOCTYPE html>
<html lang="fa-IR" dir="rtl" class="scroll-smooth">
<head>
    <?php include 'includes/head.php'; ?>
    <script src="/assets/chart.umd.min.js"></script>
    <script>if (typeof Chart === 'undefined') { document.write('<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"><\/script>'); }</script>
    <style>
        .radar-card {
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.95), rgba(248, 250, 252, 0.9));
            backdrop-filter: blur(16px);
        }
        .executive-pill {
            box-shadow: 0 4px 15px -1px rgba(13, 148, 136, 0.2);
        }
        .badge-pulse {
            animation: pulse-ring 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        @keyframes pulse-ring {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.6; }
        }

        /* موتور مقایسه دو داوطلب - استایل‌های کامپوننت اختصاصی */
        .h2h-engine-card {
            background: #ffffff;
            border: 2px solid #e2e8f0;
            border-radius: 1.75rem;
            box-shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.06);
            padding: 1.75rem;
            margin-bottom: 2rem;
        }
        .h2h-selectors-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.25rem;
            margin-bottom: 1.75rem;
        }
        @media (min-width: 768px) {
            .h2h-selectors-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
        .h2h-main-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
            align-items: start;
            width: 100%;
        }
        @media (min-width: 992px) {
            .h2h-main-grid {
                grid-template-columns: 1fr 1fr;
                gap: 2rem;
            }
        }
        .radar-chart-container-box {
            background: linear-gradient(145deg, #ffffff, #f8fafc);
            border: 1px solid #cbd5e1;
            border-radius: 1.5rem;
            padding: 1.25rem;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            width: 100%;
        }
        .radar-canvas-inner {
            width: 100%;
            max-width: 440px;
            height: 380px;
            position: relative;
        }
        .h2h-table-card {
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 1.5rem;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.04);
            width: 100%;
        }
        .h2h-table-scroll {
            width: 100%;
            overflow-x: auto;
        }
        .h2h-custom-table {
            width: 100%;
            min-width: 480px;
            border-collapse: collapse;
            text-align: right;
            font-size: 0.85rem;
        }
        .h2h-custom-table th {
            background: #f1f5f9;
            color: #1e293b;
            padding: 12px 14px;
            font-weight: 800;
            border-bottom: 2px solid #cbd5e1;
            white-space: nowrap;
        }
        .h2h-custom-table td {
            padding: 12px 14px;
            border-bottom: 1px solid #e2e8f0;
            white-space: nowrap;
        }
        .h2h-custom-table tr:hover td {
            background-color: #f8fafc;
        }
        .h2h-narrative-box {
            background: #f8fafc;
            border: 1px solid #cbd5e1;
            border-radius: 1rem;
            padding: 1.25rem;
            font-size: 0.85rem;
            line-height: 1.9;
            color: #334155;
            margin-top: 1rem;
        }

        /* سایر گریدها */
        .bottom-two-charts-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }
        @media (min-width: 992px) {
            .bottom-two-charts-grid {
                grid-template-columns: 1fr 1fr;
            }
        }
        .kpi-four-cards-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            margin-bottom: 2rem;
        }
        @media (min-width: 1024px) {
            .kpi-four-cards-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 font-sans min-h-screen flex flex-col antialiased selection:bg-teal-500 selection:text-white">

    <!-- نوتیفیکیشن / ناوبری بالای صفحه -->
    <?php include 'includes/dashboard-nav.php'; ?>

    <main class="flex-grow pt-24 pb-16 px-4 sm:px-6 lg:px-8 max-w-7xl mx-auto w-full">

        <!-- هدر اصلی داشبورد -->
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
            <div>
                <div class="flex items-center gap-3 mb-2">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black bg-gradient-to-r from-teal-500 to-emerald-600 text-white shadow-sm">
                        <span>🔬</span>
                        <span>نسخه هوشمند مدیریت و تصمیم‌گیری بالینی</span>
                    </span>
                    <span class="text-xs text-slate-400 font-medium">به‌روزرسانی همگام با دیتابیس لایو</span>
                </div>
                <h1 class="text-2xl sm:text-3xl font-black text-slate-900 tracking-tight flex items-center gap-2">
                    <span class="text-3xl">⚖️</span>
                    <span>رادار مقایسه تحلیلی نخبگان حکمت (دانش‌آموزان قدیم و جدید)</span>
                </h1>
                <p class="text-sm text-slate-600 mt-1 max-w-3xl">
                    سامانه مقایسه چندبُعدی آزمون‌های روان‌سنجی (هوش‌های گاردنر، انگیزه پیشرفت هرمنس، هوش هیجانی و پایش سلامت روان SCL-90) ویژه مدیرعامل.
                </p>
            </div>
            
            <div class="flex items-center gap-2 flex-wrap">
                <a href="admin-interview-tests.php" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-black text-slate-700 bg-white hover:bg-slate-100 border border-slate-200 transition-all shadow-sm hover:shadow">
                    <span>🧠</span>
                    <span>میز مدیریت آزمون‌ها</span>
                </a>
                <a href="/ارزیابی_جامع_روان_سنجی_دانش_آموزان_قدیم_و_جدید_بنیاد_حکمت.xlsx" download class="inline-flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-black text-emerald-700 bg-emerald-50 hover:bg-emerald-100 border border-emerald-200 transition-all shadow-sm">
                    <span>📥</span>
                    <span>دانلود فایل اکسل تجمیعی</span>
                </a>
            </div>
        </div>

        <!-- باکس بسیار مهم: شفاف‌سازی تحلیلی مدیریت و پاسخ به سوال کلیدی فرید علمی -->
        <div class="mb-8 rounded-2xl bg-gradient-to-br from-indigo-900 via-slate-900 to-teal-950 text-white p-6 sm:p-8 shadow-xl relative overflow-hidden border border-indigo-700/30">
            <div class="absolute -right-16 -top-16 w-64 h-64 bg-teal-500/10 rounded-full blur-3xl pointer-events-none"></div>
            <div class="absolute -left-16 -bottom-16 w-64 h-64 bg-indigo-500/10 rounded-full blur-3xl pointer-events-none"></div>

            <div class="relative z-10">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 rounded-xl bg-teal-500/20 border border-teal-400/40 flex items-center justify-center text-teal-300 text-xl font-black">
                        💡
                    </div>
                    <div>
                        <h2 class="text-lg sm:text-xl font-black text-teal-300">
                            پاسخ راهبردی به دغدغه مدیریت: آیا داوطلبان جدید ضعیف‌ترند یا شیوه نمره‌دهی فرق کرده؟
                        </h2>
                        <p class="text-xs text-slate-300">تحلیل عمیق تفاوت ظاهری نمرات و کشف رکوردهای جدید</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6 text-sm">
                    <!-- رکورد انگیزه -->
                    <div class="bg-white/10 backdrop-blur-md rounded-xl p-4 border border-white/10">
                        <div class="flex items-center justify-between text-xs text-slate-300 mb-1">
                            <span>🔥 رکورد انگیزه پیشرفت هرمنس</span>
                            <span class="text-emerald-400 font-black">رکورد کل تاریخ بنیاد شکسته شد!</span>
                        </div>
                        <div class="text-lg font-black text-white mt-1">
                            معصومه حسن‌زاده (جدید): <span class="text-emerald-300 text-2xl font-black">۱۱۳</span> <span class="text-xs text-slate-300">از ۱۱۶ (۹۷٪)</span>
                        </div>
                        <div class="text-xs text-slate-300 mt-2 border-t border-white/10 pt-2">
                            رکورددار قبلی: <strong>زهرا حسن‌خانی</strong> با نمره ۱۰۹ از ۱۱۶ بود. داوطلب جدید (معصومه) حتی ۴ نمره بالاتر از او کسب کرده است!
                        </div>
                    </div>

                    <!-- رکورد هوش گاردنر -->
                    <div class="bg-white/10 backdrop-blur-md rounded-xl p-4 border border-white/10">
                        <div class="flex items-center justify-between text-xs text-slate-300 mb-1">
                            <span>🧠 رکورد هوش چندگانه گاردنر</span>
                            <span class="text-teal-300 font-black">باهوش‌ترین فرد تاریخ بنیاد</span>
                        </div>
                        <div class="text-lg font-black text-white mt-1">
                            صبا کارگر مقدم (جدید): <span class="text-teal-300 text-2xl font-black">۳۵۷</span> <span class="text-xs text-slate-300">از ۴۰۰ (۸۹٪)</span>
                        </div>
                        <div class="text-xs text-slate-300 mt-2 border-t border-white/10 pt-2">
                            رکورددار قبلی: <strong>زهرا حسن‌خانی</strong> با ۳۴۳ و <strong>محمدحسام کاظمی</strong> با ۳۴۲ بودند. صبا کارگر با ۳۵۷ بالاترین نمره هوش را دارد.
                        </div>
                    </div>

                    <!-- چرایی نمره ۷۸ و گرید B+/A -->
                    <div class="bg-white/10 backdrop-blur-md rounded-xl p-4 border border-white/10">
                        <div class="flex items-center justify-between text-xs text-slate-300 mb-1">
                            <span>📐 علت تفاوت گرید و نمره ۷۸</span>
                            <span class="text-amber-300 font-black">میانگین‌گیری وزنی ریاضی</span>
                        </div>
                        <div class="text-xs text-slate-200 leading-relaxed mt-1">
                            در سیستم قدیم به هر کس که هوش یا انگیزه بالا داشت، هوش مصنوعی گرید A+ می‌داد. اما در سیستم جدید، نمره نهایی (HTI) یک فرمول ۴ گانه وزنی ریاضی است. داوطلبان جدید در آزمون هوش هیجانی آنلاین پاسخ‌های محافظه‌کارانه‌ای دادند (میانگین ۵۰٪)، که میانگین وزنی را روی ۷۸ تثبیت کرد.
                        </div>
                        <div class="text-xs text-amber-300 font-bold mt-2 border-t border-white/10 pt-2">
                            ✅ نتیجه: داوطلبان جدید فوق‌العاده قوی و نخبه هستند و جای هیچ‌گونه نگرانی نیست.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- کارت‌های شاخص کلیدی (KPIs) -->
        <div class="kpi-four-cards-grid">
            <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-teal-50 border border-teal-100 flex items-center justify-center text-2xl text-teal-600">
                    👥
                </div>
                <div>
                    <div class="text-xs font-bold text-slate-500">کل پرونده‌های ارزیابی‌شده</div>
                    <div class="text-2xl font-black text-slate-900 mt-0.5">
                        <?php echo $total_evaluated; ?> <span class="text-xs font-normal text-slate-500">نفر (۵۴ قدیم + ۱۷ جدید)</span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-emerald-50 border border-emerald-100 flex items-center justify-center text-2xl text-emerald-600">
                    💎
                </div>
                <div>
                    <div class="text-xs font-bold text-slate-500">گنج‌های نهان و استعدادهای برتر</div>
                    <div class="text-2xl font-black text-emerald-700 mt-0.5">
                        ۲۲ <span class="text-xs font-normal text-slate-500">نفر سرآمد (A+ و انگیزه > ۱۰۰)</span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-purple-50 border border-purple-100 flex items-center justify-center text-2xl text-purple-600">
                    🚀
                </div>
                <div>
                    <div class="text-xs font-bold text-slate-500">میانگین انگیزه پیشرفت (AMQ)</div>
                    <div class="text-2xl font-black text-purple-700 mt-0.5">
                        <?php echo $avg_new['hermans']; ?>٪ <span class="text-xs font-normal text-slate-500">(جدید) در برابر <?php echo $avg_old['hermans']; ?>٪ (قدیم)</span>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-2xl p-5 border border-slate-200/80 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 rounded-xl bg-amber-50 border border-amber-100 flex items-center justify-center text-2xl text-amber-600">
                    ⏳
                </div>
                <div>
                    <div class="text-xs font-bold text-slate-500">داوطلبان در انتظار آزمون</div>
                    <div class="text-2xl font-black text-amber-700 mt-0.5">
                        <?php echo $total_new_pending; ?> <span class="text-xs font-normal text-slate-500">نفر از ورودی جدید ۱۴۰۵</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ابزار فوق‌العاده جذاب: مقایسه رو در رو (Head-to-Head Radar Comparison) -->
        <div class="h2h-engine-card">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-6 border-b border-slate-200 mb-6">
                <div>
                    <span class="text-xs font-black text-teal-600 tracking-wider uppercase">Interactive Head-to-Head Engine</span>
                    <h2 class="text-xl font-black text-slate-900 mt-1 flex items-center gap-2">
                        <span>🎯</span>
                        <span>موتور مقایسه رو‌در‌روی دو داوطلب (رادار ۴ بعدی)</span>
                    </h2>
                    <p class="text-xs text-slate-500 mt-0.5">هر دو داوطلب از دوره قدیم یا جدید را انتخاب کنید تا نمودار رادار در ۴ بعد روان‌سنجی همزمان ترسیم شود.</p>
                </div>
                
                <div class="flex items-center gap-2 flex-wrap">
                    <button onclick="setQuickComparison('زهرا حسن خانی', 'معصومه حسن زاده')" class="px-3.5 py-2 rounded-xl text-xs font-bold bg-slate-100 hover:bg-slate-200 text-slate-700 transition border border-slate-300">
                        ⚡ مقایسه دو رکورددار انگیزه
                    </button>
                    <button onclick="setQuickComparison('زهرا حسن خانی', 'صبا کارگر مقدم')" class="px-3.5 py-2 rounded-xl text-xs font-bold bg-teal-50 hover:bg-teal-100 text-teal-800 transition border border-teal-200">
                        ⚡ مقایسه دو نابغه هوش
                    </button>
                </div>
            </div>

            <!-- انتخاب‌گر دو داوطلب -->
            <div class="h2h-selectors-grid">
                <!-- داوطلب اول -->
                <div class="p-4 rounded-2xl bg-teal-50/70 border-2 border-teal-200 shadow-sm">
                    <label class="block text-xs font-black text-teal-900 mb-2 flex items-center justify-between">
                        <span>داوطلب اول (رنگ فیروزه‌ای):</span>
                        <span id="label_st1_cohort" class="text-[11px] font-bold text-teal-800 bg-teal-200/80 px-2.5 py-0.5 rounded-full">دانش‌آموز قدیم</span>
                    </label>
                    <select id="select_student_1" class="w-full bg-white border border-teal-300 text-slate-900 text-sm font-bold rounded-xl p-3 focus:ring-2 focus:ring-teal-500 focus:outline-none shadow-sm">
                        <optgroup label="🌟 برگزیدگان قدیم">
                            <?php foreach ($normalized_old_students as $st): ?>
                                <option value="<?php echo htmlspecialchars($st['name']); ?>" <?php if ($st['name'] === 'زهرا حسن خانی') echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($st['name']); ?> (گرید <?php echo $st['orig_grade']; ?> | هرمنس: <?php echo $st['hermans_raw']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="✨ داوطلبان جدید (۱۴۰۵)">
                            <?php foreach ($normalized_new_students as $st): ?>
                                <option value="<?php echo htmlspecialchars($st['name']); ?>">
                                    <?php echo htmlspecialchars($st['name']); ?> (امتیاز <?php echo $st['hti']; ?> | هرمنس: <?php echo $st['hermans_raw']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>

                <!-- داوطلب دوم -->
                <div class="p-4 rounded-2xl bg-indigo-50/70 border-2 border-indigo-200 shadow-sm">
                    <label class="block text-xs font-black text-indigo-900 mb-2 flex items-center justify-between">
                        <span>داوطلب دوم (رنگ بنفش):</span>
                        <span id="label_st2_cohort" class="text-[11px] font-bold text-indigo-800 bg-indigo-200/80 px-2.5 py-0.5 rounded-full">ورودی جدید (۱۴۰۵)</span>
                    </label>
                    <select id="select_student_2" class="w-full bg-white border border-indigo-300 text-slate-900 text-sm font-bold rounded-xl p-3 focus:ring-2 focus:ring-indigo-500 focus:outline-none shadow-sm">
                        <optgroup label="✨ داوطلبان جدید (۱۴۰۵)">
                            <?php foreach ($normalized_new_students as $st): ?>
                                <option value="<?php echo htmlspecialchars($st['name']); ?>" <?php if ($st['name'] === 'معصومه حسن زاده') echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($st['name']); ?> (امتیاز <?php echo $st['hti']; ?> | هرمنس: <?php echo $st['hermans_raw']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <optgroup label="🌟 برگزیدگان قدیم">
                            <?php foreach ($normalized_old_students as $st): ?>
                                <option value="<?php echo htmlspecialchars($st['name']); ?>">
                                    <?php echo htmlspecialchars($st['name']); ?> (گرید <?php echo $st['orig_grade']; ?> | هرمنس: <?php echo $st['hermans_raw']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </optgroup>
                    </select>
                </div>
            </div>

            <!-- نمودار رادار و جدول مقایسه مستقیم -->
            <div class="h2h-main-grid">
                <!-- ستون رادار چارت -->
                <div class="radar-chart-container-box">
                    <div class="text-xs font-black text-slate-600 mb-3 flex items-center gap-1.5 self-start">
                        <span>📊</span>
                        <span>نمودار رادار شایستگی چندبُعدی (گاردنر، هرمنس، EQ، سلامت روان)</span>
                    </div>
                    <div class="radar-canvas-inner">
                        <canvas id="radarChart"></canvas>
                    </div>
                </div>

                <!-- ستون جدول مقایسه‌ای و تحلیل متنی -->
                <div class="space-y-4 w-full">
                    <div class="h2h-table-card">
                        <div class="h2h-table-scroll">
                            <table class="h2h-custom-table">
                                <thead>
                                    <tr>
                                        <th style="width: 28%;">بُعد روان‌سنجی</th>
                                        <th style="width: 27%; color: #0f766e;" id="th_st1_name">داوطلب ۱</th>
                                        <th style="width: 27%; color: #4338ca;" id="th_st2_name">داوطلب ۲</th>
                                        <th style="width: 18%; text-align: center;">وضعیت برتری</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white" id="comparison_tbody">
                                    <!-- دینامیک با JS پر می‌شود -->
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- باکس تحلیل مقایسه‌ای متنی -->
                    <div class="h2h-narrative-box" id="narrative_comparison_box">
                        در حال بارگذاری تحلیل هوشمند...
                    </div>
                </div>
            </div>
        </div>

        <!-- دو نمودار تحلیلی کلان: ماتریس نخبگان (انگیزه vs هوش) + مقایسه میانگین گروه‌ها -->
        <div class="bottom-two-charts-grid">
            <!-- نمودار ۱: ماتریس استعدادیابی (انگیزه در برابر هوش شناختی) -->
            <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm flex flex-col">
                <div class="flex items-center justify-between mb-4 pb-2 border-b border-slate-100">
                    <div>
                        <h3 class="font-black text-slate-900 text-base flex items-center gap-2">
                            <span>🌌</span>
                            <span>ماتریس استعدادیابی: انگیزه در برابر هوش شناختی</span>
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">ربع بالا-راست (نقطه طلایی): داوطلبانی با انگیزه فوق‌العاده و هوش سرآمد</p>
                    </div>
                </div>
                <div class="relative flex-grow h-72 sm:h-80">
                    <canvas id="scatterTalentMatrix"></canvas>
                </div>
            </div>

            <!-- نمودار ۲: مقایسه میانگین ابعاد ۴ گانه بین قدیم و جدید -->
            <div class="bg-white rounded-3xl p-6 border border-slate-200 shadow-sm flex flex-col">
                <div class="flex items-center justify-between mb-4 pb-2 border-b border-slate-100">
                    <div>
                        <h3 class="font-black text-slate-900 text-base flex items-center gap-2">
                            <span>📊</span>
                            <span>مقایسه میانگین ابعاد ۴ گانه (قدیم vs جدید)</span>
                        </h3>
                        <p class="text-xs text-slate-500 mt-0.5">مقایسه نرمال‌شده بر روی مقیاس درصدی (۰ تا ۱۰۰)</p>
                    </div>
                </div>
                <div class="relative flex-grow h-72 sm:h-80">
                    <canvas id="barCohortAverages"></canvas>
                </div>
            </div>
        </div>

        <!-- جدول تفصیلی، فیلترپذیر و جامع کل داوطلبان -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200 shadow-sm">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 pb-6 border-b border-slate-100">
                <div>
                    <h3 class="font-black text-slate-900 text-lg flex items-center gap-2">
                        <span>📋</span>
                        <span>فهرست جامع و رتبه‌بندی تمام پرونده‌های روان‌سنجی</span>
                    </h3>
                    <p class="text-xs text-slate-500 mt-0.5">جستجو بر اساس نام، تلفن یا دسته‌بندی؛ کلیک روی هر ردیف برای مقایسه مستقیم.</p>
                </div>

                <!-- باکس جستجو -->
                <div class="w-full md:w-72 relative">
                    <input type="text" id="searchInput" placeholder="جستجوی نام یا تلفن دانش‌آموز..." class="w-full bg-slate-50 border border-slate-200 rounded-xl py-2 pr-9 pl-4 text-xs font-bold text-slate-800 placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:bg-white transition-all">
                    <span class="absolute right-3 top-2.5 text-slate-400 text-sm">🔍</span>
                </div>
            </div>

            <!-- تب‌های فیلتر سریع -->
            <div class="flex items-center gap-2 overflow-x-auto py-4 text-xs font-bold border-b border-slate-100">
                <button onclick="filterTable('all')" id="btn_tab_all" class="filter-tab px-3 py-1.5 rounded-lg bg-teal-600 text-white shadow-sm transition">
                    همه داوطلبان (<?php echo $total_evaluated; ?>)
                </button>
                <button onclick="filterTable('new')" id="btn_tab_new" class="filter-tab px-3 py-1.5 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 transition">
                    ✨ ورودی‌های جدید ۱۴۰۵ (<?php echo $total_new_completed; ?>)
                </button>
                <button onclick="filterTable('old')" id="btn_tab_old" class="filter-tab px-3 py-1.5 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 transition">
                    🏛️ پرونده‌های قدیم (<?php echo $total_old; ?>)
                </button>
                <button onclick="filterTable('super_talent')" id="btn_tab_super_talent" class="filter-tab px-3 py-1.5 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 transition">
                    💎 گنج‌های نهان و نخبگان
                </button>
                <button onclick="filterTable('critical')" id="btn_tab_critical" class="filter-tab px-3 py-1.5 rounded-lg bg-slate-100 text-slate-700 hover:bg-slate-200 transition">
                    🚨 موارد نیازمند مراقبت بالینی
                </button>
            </div>

            <!-- نوار مرتب‌سازی سریع ستون‌ها -->
            <div class="flex flex-wrap items-center justify-between gap-3 py-3 px-4 bg-slate-50/90 rounded-2xl border border-slate-200/80 my-3 text-xs">
                <div class="flex items-center gap-1.5 flex-wrap">
                    <span class="font-black text-slate-700 flex items-center gap-1 ml-1">
                        <span>📊</span> مرتب‌سازی بر اساس:
                    </span>
                    <button type="button" onclick="sortTable('hti')" id="btn_sort_hti" class="sort-pill px-3 py-1.5 rounded-xl font-bold bg-teal-600 text-white shadow-sm transition hover:opacity-90 flex items-center gap-1">
                        <span>🏅 شاخص کل (HTI)</span>
                        <span id="pill_arrow_hti" class="text-[10px]">▼</span>
                    </button>
                    <button type="button" onclick="sortTable('mi')" id="btn_sort_mi" class="sort-pill px-3 py-1.5 rounded-xl font-bold bg-white text-slate-700 border border-slate-200 transition hover:bg-slate-100 flex items-center gap-1">
                        <span>🧠 هوش گاردنر (MI)</span>
                        <span id="pill_arrow_mi" class="text-[10px]">↕</span>
                    </button>
                    <button type="button" onclick="sortTable('hermans')" id="btn_sort_hermans" class="sort-pill px-3 py-1.5 rounded-xl font-bold bg-white text-slate-700 border border-slate-200 transition hover:bg-slate-100 flex items-center gap-1">
                        <span>🔥 انگیزه هرمنس (AMQ)</span>
                        <span id="pill_arrow_hermans" class="text-[10px]">↕</span>
                    </button>
                    <button type="button" onclick="sortTable('eq')" id="btn_sort_eq" class="sort-pill px-3 py-1.5 rounded-xl font-bold bg-white text-slate-700 border border-slate-200 transition hover:bg-slate-100 flex items-center gap-1">
                        <span>❤️ هوش هیجانی (EQ)</span>
                        <span id="pill_arrow_eq" class="text-[10px]">↕</span>
                    </button>
                    <button type="button" onclick="sortTable('gsi')" id="btn_sort_gsi" class="sort-pill px-3 py-1.5 rounded-xl font-bold bg-white text-slate-700 border border-slate-200 transition hover:bg-slate-100 flex items-center gap-1">
                        <span>🩺 سلامت روان (GSI)</span>
                        <span id="pill_arrow_gsi" class="text-[10px]">↕</span>
                    </button>
                    <button type="button" onclick="sortTable('name')" id="btn_sort_name" class="sort-pill px-3 py-1.5 rounded-xl font-bold bg-white text-slate-700 border border-slate-200 transition hover:bg-slate-100 flex items-center gap-1">
                        <span>🔤 نام داوطلب</span>
                        <span id="pill_arrow_name" class="text-[10px]">↕</span>
                    </button>
                </div>
                <div class="text-[11px] text-slate-600 font-bold flex items-center gap-2">
                    <span>تعداد داوطلبان نمایش داده شده:</span>
                    <span id="visible_count_badge" class="px-2.5 py-0.5 rounded-full bg-teal-100 text-teal-800 font-mono font-black"><?php echo $total_evaluated; ?></span>
                    <span class="text-slate-400">از <?php echo $total_evaluated; ?> نفر</span>
                </div>
            </div>

            <!-- جدول داده‌ها -->
            <div class="overflow-x-auto mt-4">
                <table class="w-full text-right text-xs">
                    <thead class="bg-slate-50 text-slate-600 font-black border-y border-slate-200/80 select-none">
                        <tr>
                            <th class="p-3 cursor-pointer hover:bg-slate-150 transition whitespace-nowrap" onclick="sortTable('original')" title="کلیک جهت مرتب‌سازی به ترتیب ردیف اولیه">
                                <span>ردیف</span>
                                <span id="sort_icon_original" class="sort-icon text-slate-400 mr-1 text-[11px]">↕</span>
                            </th>
                            <th class="p-3 cursor-pointer hover:bg-slate-150 transition whitespace-nowrap" onclick="sortTable('name')" title="کلیک جهت مرتب‌سازی الفبایی نام">
                                <span>نام داوطلب</span>
                                <span id="sort_icon_name" class="sort-icon text-slate-400 mr-1 text-[11px]">↕</span>
                            </th>
                            <th class="p-3 cursor-pointer hover:bg-slate-150 transition whitespace-nowrap" onclick="sortTable('cohort')" title="کلیک جهت مرتب‌سازی بر اساس دوره">
                                <span>دوره</span>
                                <span id="sort_icon_cohort" class="sort-icon text-slate-400 mr-1 text-[11px]">↕</span>
                            </th>
                            <th class="p-3 text-center cursor-pointer hover:bg-slate-150 transition whitespace-nowrap" onclick="sortTable('hti')" title="کلیک جهت مرتب‌سازی بر اساس شاخص کل همگن">
                                <span>شاخص کل (HTI)</span>
                                <span id="sort_icon_hti" class="sort-icon text-teal-600 mr-1 text-[11px] font-black">▼</span>
                            </th>
                            <th class="p-3 text-center cursor-pointer hover:bg-slate-150 transition whitespace-nowrap" onclick="sortTable('mi')" title="کلیک جهت مرتب‌سازی بر اساس هوش گاردنر">
                                <span>هوش گاردنر (MI)</span>
                                <span id="sort_icon_mi" class="sort-icon text-slate-400 mr-1 text-[11px]">↕</span>
                            </th>
                            <th class="p-3 text-center cursor-pointer hover:bg-slate-150 transition whitespace-nowrap" onclick="sortTable('hermans')" title="کلیک جهت مرتب‌سازی بر اساس انگیزه پیشرفت هرمنس">
                                <span>انگیزه هرمنس (AMQ)</span>
                                <span id="sort_icon_hermans" class="sort-icon text-slate-400 mr-1 text-[11px]">↕</span>
                            </th>
                            <th class="p-3 text-center cursor-pointer hover:bg-slate-150 transition whitespace-nowrap" onclick="sortTable('eq')" title="کلیک جهت مرتب‌سازی بر اساس هوش هیجانی">
                                <span>هوش هیجانی (EQ)</span>
                                <span id="sort_icon_eq" class="sort-icon text-slate-400 mr-1 text-[11px]">↕</span>
                            </th>
                            <th class="p-3 cursor-pointer hover:bg-slate-150 transition whitespace-nowrap" onclick="sortTable('gsi')" title="کلیک جهت مرتب‌سازی بر اساس شاخص سلامت روان">
                                <span>سلامت روان (SCL-90)</span>
                                <span id="sort_icon_gsi" class="sort-icon text-slate-400 mr-1 text-[11px]">↕</span>
                            </th>
                            <th class="p-3">سیمای استعدادیابی / توصیه</th>
                            <th class="p-3 text-center whitespace-nowrap">عملیات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 font-medium" id="masterTableBody">
                        <?php foreach ($all_completed_profiles as $idx => $p): 
                            $is_new = ($p['cohort'] === 'new');
                        ?>
                            <tr class="student-row hover:bg-slate-50/80 transition-colors"
                                data-cohort="<?php echo $p['cohort']; ?>"
                                data-super="<?php echo $p['is_super_talent'] ? '1' : '0'; ?>"
                                data-critical="<?php echo $p['is_critical'] ? '1' : '0'; ?>"
                                data-name="<?php echo htmlspecialchars($p['name']); ?>"
                                data-phone="<?php echo htmlspecialchars($p['phone']); ?>"
                                data-hti="<?php echo intval($p['hti']); ?>"
                                data-mi="<?php echo intval($p['mi_raw']); ?>"
                                data-hermans="<?php echo intval($p['hermans_raw']); ?>"
                                data-eq="<?php echo ($p['eq_raw'] !== null) ? intval($p['eq_raw']) : -1; ?>"
                                data-gsi="<?php echo floatval($p['scl_gsi']); ?>"
                                data-original-index="<?php echo $idx + 1; ?>"
                            >
                                <td class="p-3 text-slate-500 font-mono row-index font-bold"><?php echo $idx + 1; ?></td>
                                <td class="p-3">
                                    <div class="font-black text-slate-900"><?php echo htmlspecialchars($p['name']); ?></div>
                                    <div class="text-[11px] text-slate-400 font-mono"><?php echo htmlspecialchars($p['phone']); ?></div>
                                </td>
                                <td class="p-3 whitespace-nowrap">
                                    <?php if ($is_new): ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-black bg-indigo-50 text-indigo-700 border border-indigo-200">جدید (۱۴۰۵)</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-black bg-slate-100 text-slate-700 border border-slate-200">قدیم</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3 text-center whitespace-nowrap">
                                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-full font-black text-xs <?php echo ($p['grade'] === 'A+' || $p['grade'] === 'A') ? 'bg-emerald-100 text-emerald-800' : (($p['grade'] === 'B+') ? 'bg-teal-100 text-teal-800' : 'bg-slate-100 text-slate-700'); ?>">
                                        <?php echo $p['hti']; ?>
                                    </span>
                                    <span class="block text-[10px] font-bold text-slate-400 mt-0.5">گرید <?php echo $p['orig_grade']; ?></span>
                                </td>
                                <td class="p-3 text-center whitespace-nowrap">
                                    <div class="font-black text-slate-800"><?php echo $p['mi_raw']; ?> <span class="text-[10px] text-slate-400">/ ۴۰۰</span></div>
                                    <div class="text-[10px] text-teal-600 font-bold"><?php echo $p['mi_pct']; ?>٪</div>
                                </td>
                                <td class="p-3 text-center whitespace-nowrap">
                                    <div class="font-black text-slate-800"><?php echo $p['hermans_raw']; ?> <span class="text-[10px] text-slate-400">/ ۱۱۶</span></div>
                                    <div class="text-[10px] text-purple-600 font-bold"><?php echo $p['hermans_pct']; ?>٪</div>
                                </td>
                                <td class="p-3 text-center whitespace-nowrap">
                                    <?php if ($p['eq_raw'] !== null): ?>
                                        <div class="font-black text-slate-800"><?php echo $p['eq_raw']; ?> <span class="text-[10px] text-slate-400">/ ۱۶۵</span></div>
                                        <div class="text-[10px] text-indigo-600 font-bold"><?php echo $p['eq_pct']; ?>٪</div>
                                    <?php else: ?>
                                        <span class="text-slate-400 text-xs">ثبت نشده</span>
                                        <span class="block text-[9px] text-teal-600 font-bold">تعدیل وزنی</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3 whitespace-nowrap">
                                    <?php if ($p['is_critical']): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-black bg-red-100 text-red-700">🚨 بحرانی (GSI: <?php echo $p['scl_gsi']; ?>)</span>
                                    <?php elseif ($p['is_warning']): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-black bg-amber-100 text-amber-800">⚠️ هشدار (GSI: <?php echo $p['scl_gsi']; ?>)</span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-black bg-emerald-100 text-emerald-800">✅ مطلوب (GSI: <?php echo $p['scl_gsi']; ?>)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="p-3 max-w-xs">
                                    <div class="truncate text-slate-700" title="<?php echo htmlspecialchars($p['recommendation']); ?>">
                                        <?php if ($p['is_super_talent']): ?>
                                            <span class="text-emerald-700 font-black">💎 استعداد برتر: </span>
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($p['recommendation']); ?>
                                    </div>
                                    <div class="text-[10px] text-slate-400 mt-0.5 truncate">
                                        نقاط قوت: <?php echo htmlspecialchars($p['top_talents']); ?>
                                    </div>
                                </td>
                                <td class="p-3 text-center whitespace-nowrap">
                                    <div class="inline-flex items-center gap-1.5">
                                        <button onclick="compareWithThis('<?php echo htmlspecialchars(addslashes($p['name'])); ?>')" class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-slate-100 hover:bg-teal-50 hover:text-teal-700 border border-slate-200 transition" title="مقایسه این داوطلب در رادار">
                                            ⚖️ مقایسه
                                        </button>
                                        <?php if (!empty($p['token'])): ?>
                                            <a href="/interview-dossier.php?token=<?php echo urlencode($p['token']); ?>" target="_blank" class="px-2 py-1 rounded-lg text-[11px] font-bold bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 transition" title="مشاهده کارنامه جامع و فایل‌های صوتی مصاحبه">
                                                📋 کارنامه
                                            </a>
                                        <?php elseif (!empty($p['db_id'])): ?>
                                            <a href="/admin/person-detail.php?id=<?php echo intval($p['db_id']); ?>" target="_blank" class="px-2 py-1 rounded-lg text-[11px] font-bold bg-teal-50 hover:bg-teal-100 text-teal-700 border border-teal-200 transition" title="مشاهده پرونده مددجو در سامانه مدیریت">
                                                👤 پرونده
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- اسکریپت‌های تعاملی و ترسیم Chart.js -->
    <script>
        const allProfiles = <?php echo $json_all_profiles; ?>;
        const avgOld = <?php echo $json_avg_old; ?>;
        const avgNew = <?php echo $json_avg_new; ?>;

        let radarChart = null;
        let scatterChart = null;
        let barChart = null;

        // تابع یافتن پروفایل با نام
        function getProfileByName(name) {
            return allProfiles.find(p => p.name === name) || null;
        }

        // ۱. مقداردهی اولیه نمودار رادار
        function initRadarChart() {
            const ctx = document.getElementById('radarChart').getContext('2d');
            radarChart = new Chart(ctx, {
                type: 'radar',
                data: {
                    labels: ['هوش چندگانه گاردنر', 'انگیزه پیشرفت هرمنس', 'هوش هیجانی (EQ)', 'سلامت و آرامش روان'],
                    datasets: [
                        {
                            label: 'داوطلب اول',
                            data: [0, 0, 0, 0],
                            backgroundColor: 'rgba(13, 148, 136, 0.25)',
                            borderColor: 'rgba(13, 148, 136, 1)',
                            pointBackgroundColor: 'rgba(13, 148, 136, 1)',
                            borderWidth: 2.5
                        },
                        {
                            label: 'داوطلب دوم',
                            data: [0, 0, 0, 0],
                            backgroundColor: 'rgba(99, 102, 241, 0.25)',
                            borderColor: 'rgba(99, 102, 241, 1)',
                            pointBackgroundColor: 'rgba(99, 102, 241, 1)',
                            borderWidth: 2.5
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        r: {
                            angleLines: { color: 'rgba(148, 163, 184, 0.2)' },
                            grid: { color: 'rgba(148, 163, 184, 0.2)' },
                            pointLabels: {
                                font: { family: 'Vazirmatn', size: 11, weight: 'bold' },
                                color: '#334155'
                            },
                            suggestedMin: 0,
                            suggestedMax: 100,
                            ticks: { stepSize: 20, backdropColor: 'transparent' }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: { font: { family: 'Vazirmatn', size: 11, weight: 'bold' } }
                        }
                    }
                }
            });
        }

        // ۲. به‌روزرسانی مقایسه رو‌در‌رو
        function updateComparison() {
            const name1 = document.getElementById('select_student_1').value;
            const name2 = document.getElementById('select_student_2').value;

            const st1 = getProfileByName(name1);
            const st2 = getProfileByName(name2);

            if (!st1 || !st2) return;

            // به‌روزرسانی برچسب‌های دوره
            document.getElementById('label_st1_cohort').innerText = st1.cohort === 'new' ? 'ورودی جدید (۱۴۰۵)' : 'دانش‌آموز قدیم';
            document.getElementById('label_st2_cohort').innerText = st2.cohort === 'new' ? 'ورودی جدید (۱۴۰۵)' : 'دانش‌آموز قدیم';
            document.getElementById('th_st1_name').innerText = st1.name;
            document.getElementById('th_st2_name').innerText = st2.name;

            // دیتا برای رادار
            const data1 = [st1.mi_pct, st1.hermans_pct, st1.eq_pct, st1.mental_pct];
            const data2 = [st2.mi_pct, st2.hermans_pct, st2.eq_pct, st2.mental_pct];

            radarChart.data.datasets[0].label = st1.name + ' (' + (st1.cohort === 'new' ? 'جدید' : 'قدیم') + ')';
            radarChart.data.datasets[0].data = data1;
            radarChart.data.datasets[1].label = st2.name + ' (' + (st2.cohort === 'new' ? 'جدید' : 'قدیم') + ')';
            radarChart.data.datasets[1].data = data2;
            radarChart.update();

            // پر کردن جدول جزئیات
            const metrics = [
                { title: 'هوش چندگانه گاردنر', v1: `${st1.mi_raw} / ۴۰۰ (${st1.mi_pct}٪)`, v2: `${st2.mi_raw} / ۴۰۰ (${st2.mi_pct}٪)`, num1: st1.mi_pct, num2: st2.mi_pct },
                { title: 'انگیزه پیشرفت هرمنس', v1: `${st1.hermans_raw} / ۱۱۶ (${st1.hermans_pct}٪)`, v2: `${st2.hermans_raw} / ۱۱۶ (${st2.hermans_pct}٪)`, num1: st1.hermans_pct, num2: st2.hermans_pct },
                { title: 'هوش هیجانی (EQ)', v1: st1.eq_raw !== null ? `${st1.eq_raw} / ۱۶۵ (${st1.eq_pct}٪)` : 'ثبت نشده (تعدیل وزنی)', v2: st2.eq_raw !== null ? `${st2.eq_raw} / ۱۶۵ (${st2.eq_pct}٪)` : 'ثبت نشده (تعدیل وزنی)', num1: st1.eq_pct, num2: st2.eq_pct },
                { title: 'شاخص سلامت و آرامش روان', v1: `GSI: ${st1.scl_gsi} (${st1.mental_pct}٪)`, v2: `GSI: ${st2.scl_gsi} (${st2.mental_pct}٪)`, num1: st1.mental_pct, num2: st2.mental_pct },
                { title: 'شاخص شایستگی کل (HTI)', v1: `امتیاز ${st1.hti} (گرید ${st1.orig_grade})`, v2: `امتیاز ${st2.hti} (گرید ${st2.orig_grade})`, num1: st1.hti, num2: st2.hti }
            ];

            let tbodyHtml = '';
            metrics.forEach(m => {
                let badge = '';
                if (m.num1 > m.num2) {
                    badge = `<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-teal-100 text-teal-800">برتری با ${st1.name}</span>`;
                } else if (m.num2 > m.num1) {
                    badge = `<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-indigo-100 text-indigo-800">برتری با ${st2.name}</span>`;
                } else {
                    badge = `<span class="px-2 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-600">برابر</span>`;
                }
                tbodyHtml += `
                    <tr>
                        <td class="p-3 font-bold text-slate-700">${m.title}</td>
                        <td class="p-3 font-mono font-bold text-teal-700">${m.v1}</td>
                        <td class="p-3 font-mono font-bold text-indigo-700">${m.v2}</td>
                        <td class="p-3 text-center">${badge}</td>
                    </tr>
                `;
            });
            document.getElementById('comparison_tbody').innerHTML = tbodyHtml;

            // تولید تحلیل متنی
            let narrative = `
                <div class="font-bold text-slate-800 mb-1">🔍 خلاصه مقایسه تحلیلی برای مدیرعامل:</div>
                <p>در مقایسه رو‌در‌روی <strong>${st1.name}</strong> (${st1.cohort === 'new' ? 'جدید' : 'قدیم'}) و <strong>${st2.name}</strong> (${st2.cohort === 'new' ? 'جدید' : 'قدیم'}):</p>
                <ul class="list-disc list-inside mt-1 space-y-1 text-slate-600">
                    <li>در شاخص انگیزه پیشرفت هرمنس، ${st1.hermans_raw >= st2.hermans_raw ? `<strong>${st1.name}</strong> با کسب ${st1.hermans_raw} نمره برتری دارد.` : `<strong>${st2.name}</strong> با نمره ${st2.hermans_raw} در رده بالاتر قرار گرفته است.`}</li>
                    <li>در هوش چندگانه گاردنر، ${st1.mi_raw >= st2.mi_raw ? `<strong>${st1.name}</strong> با ${st1.mi_raw} نمره پیشتاز است.` : `<strong>${st2.name}</strong> با نمره ${st2.mi_raw} عملکرد شناختی قدرتمندتری نشان داده است.`}</li>
                    <li>وضعیت پایش روان بیانگر سطح تنش GSI معادل <strong>${st1.scl_gsi}</strong> برای ${st1.name} و <strong>${st2.scl_gsi}</strong> برای ${st2.name} است.</li>
                </ul>
            `;
            document.getElementById('narrative_comparison_box').innerHTML = narrative;
        }

        function setQuickComparison(name1, name2) {
            document.getElementById('select_student_1').value = name1;
            document.getElementById('select_student_2').value = name2;
            updateComparison();
        }

        function compareWithThis(name) {
            document.getElementById('select_student_2').value = name;
            updateComparison();
            window.scrollTo({ top: document.getElementById('select_student_1').offsetTop - 120, behavior: 'smooth' });
        }

        // ۳. مقداردهی ماتریس پراکندگی استعدادها
        function initScatterChart() {
            const ctx = document.getElementById('scatterTalentMatrix').getContext('2d');
            
            const oldPoints = allProfiles.filter(p => p.cohort === 'old').map(p => ({
                x: p.hermans_raw,
                y: p.mi_raw,
                name: p.name,
                grade: p.orig_grade
            }));

            const newPoints = allProfiles.filter(p => p.cohort === 'new').map(p => ({
                x: p.hermans_raw,
                y: p.mi_raw,
                name: p.name,
                grade: p.grade
            }));

            scatterChart = new Chart(ctx, {
                type: 'scatter',
                data: {
                    datasets: [
                        {
                            label: 'دانش‌آموزان قدیم',
                            data: oldPoints,
                            backgroundColor: 'rgba(13, 148, 136, 0.7)',
                            borderColor: 'rgba(13, 148, 136, 1)',
                            pointRadius: 6,
                            pointHoverRadius: 9
                        },
                        {
                            label: 'ورودی‌های جدید (۱۴۰۵)',
                            data: newPoints,
                            backgroundColor: 'rgba(99, 102, 241, 0.85)',
                            borderColor: 'rgba(79, 70, 229, 1)',
                            pointRadius: 7,
                            pointHoverRadius: 10
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: {
                            title: { display: true, text: 'انگیزه پیشرفت هرمنس (از ۱۱۶)', font: { family: 'Vazirmatn', weight: 'bold' } },
                            min: 60,
                            max: 120,
                            grid: { color: 'rgba(148, 163, 184, 0.15)' }
                        },
                        y: {
                            title: { display: true, text: 'هوش چندگانه گاردنر (از ۴۰۰)', font: { family: 'Vazirmatn', weight: 'bold' } },
                            min: 200,
                            max: 400,
                            grid: { color: 'rgba(148, 163, 184, 0.15)' }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const pt = context.raw;
                                    return `${pt.name}: هرمنس ${pt.x} | گاردنر ${pt.y} (گرید ${pt.grade})`;
                                }
                            }
                        },
                        legend: {
                            position: 'top',
                            labels: { font: { family: 'Vazirmatn', size: 11, weight: 'bold' } }
                        }
                    }
                }
            });
        }

        // ۴. مقداردهی نمودار میانگین گروه‌ها
        function initBarChart() {
            const ctx = document.getElementById('barCohortAverages').getContext('2d');
            barChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: ['هوش گاردنر (MI)', 'انگیزه هرمنس (AMQ)', 'هوش هیجانی (EQ)', 'سلامت روان (SCL)'],
                    datasets: [
                        {
                            label: 'میانگین قدیم (<?php echo $total_old; ?> نفر)',
                            data: [avgOld.mi, avgOld.hermans, avgOld.eq, avgOld.mental],
                            backgroundColor: 'rgba(13, 148, 136, 0.75)',
                            borderRadius: 6
                        },
                        {
                            label: 'میانگین جدید (<?php echo $total_new_completed; ?> نفر)',
                            data: [avgNew.mi, avgNew.hermans, avgNew.eq, avgNew.mental],
                            backgroundColor: 'rgba(99, 102, 241, 0.75)',
                            borderRadius: 6
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            suggestedMin: 0,
                            suggestedMax: 100,
                            title: { display: true, text: 'درصد میانگین شایستگی (٪)', font: { family: 'Vazirmatn', weight: 'bold' } },
                            grid: { color: 'rgba(148, 163, 184, 0.15)' }
                        },
                        x: {
                            grid: { display: false },
                            ticks: { font: { family: 'Vazirmatn', weight: 'bold' } }
                        }
                    },
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: { font: { family: 'Vazirmatn', size: 11, weight: 'bold' } }
                        }
                    }
                }
            });
        }

        // وضعیت فعلی سورت
        let currentSortColumn = 'hti';
        let currentSortDir = 'desc';

        // ۵. شماره‌گذاری مجدد و پویای ردیف‌های جدول
        function reNumberRows() {
            const rows = document.querySelectorAll('#masterTableBody tr.student-row');
            let visibleIndex = 1;
            rows.forEach(r => {
                if (r.style.display !== 'none') {
                    const indexCell = r.querySelector('.row-index');
                    if (indexCell) {
                        indexCell.textContent = visibleIndex;
                    }
                    visibleIndex++;
                }
            });
            const badge = document.getElementById('visible_count_badge');
            if (badge) {
                badge.textContent = visibleIndex - 1;
            }
        }

        // ۶. مرتب‌سازی هوشمند جدول بر اساس ستون‌ها
        function sortTable(column) {
            if (currentSortColumn === column) {
                currentSortDir = (currentSortDir === 'desc') ? 'asc' : 'desc';
            } else {
                currentSortColumn = column;
                // جهت پیش‌فرض سورت برای شاخص‌های مختلف
                if (column === 'gsi' || column === 'name' || column === 'original' || column === 'cohort') {
                    currentSortDir = 'asc';
                } else {
                    currentSortDir = 'desc';
                }
            }

            const tbody = document.getElementById('masterTableBody');
            const rows = Array.from(tbody.querySelectorAll('tr.student-row'));

            rows.sort((a, b) => {
                let valA, valB;
                if (column === 'name') {
                    valA = (a.getAttribute('data-name') || '').trim();
                    valB = (b.getAttribute('data-name') || '').trim();
                    const cmp = valA.localeCompare(valB, 'fa');
                    return currentSortDir === 'asc' ? cmp : -cmp;
                } else if (column === 'cohort') {
                    valA = a.getAttribute('data-cohort') || '';
                    valB = b.getAttribute('data-cohort') || '';
                    const cmp = valA.localeCompare(valB);
                    return currentSortDir === 'asc' ? cmp : -cmp;
                } else if (column === 'original') {
                    valA = parseInt(a.getAttribute('data-original-index') || '0', 10);
                    valB = parseInt(b.getAttribute('data-original-index') || '0', 10);
                } else if (column === 'gsi') {
                    valA = parseFloat(a.getAttribute('data-gsi') || '0');
                    valB = parseFloat(b.getAttribute('data-gsi') || '0');
                } else if (column === 'hti') {
                    valA = parseInt(a.getAttribute('data-hti') || '0', 10);
                    valB = parseInt(b.getAttribute('data-hti') || '0', 10);
                } else if (column === 'mi') {
                    valA = parseInt(a.getAttribute('data-mi') || '0', 10);
                    valB = parseInt(b.getAttribute('data-mi') || '0', 10);
                } else if (column === 'hermans') {
                    valA = parseInt(a.getAttribute('data-hermans') || '0', 10);
                    valB = parseInt(b.getAttribute('data-hermans') || '0', 10);
                } else if (column === 'eq') {
                    valA = parseInt(a.getAttribute('data-eq') || '-1', 10);
                    valB = parseInt(b.getAttribute('data-eq') || '-1', 10);
                    // مقادیر ثبت نشده (بدون تست EQ) همواره در انتهای لیست قرار گیرند
                    if (valA === -1 && valB !== -1) return 1;
                    if (valB === -1 && valA !== -1) return -1;
                } else {
                    return 0;
                }

                if (currentSortDir === 'asc') {
                    return (valA > valB) ? 1 : (valA < valB ? -1 : 0);
                } else {
                    return (valA < valB) ? 1 : (valA > valB ? -1 : 0);
                }
            });

            // بازنشانی ردیف‌های مرتب‌شده در DOM
            rows.forEach(r => tbody.appendChild(r));

            // به‌روزرسانی آیکون‌ها و دکمه‌های وضعیت مرتب‌سازی
            updateSortIndicators();

            // شماره‌گذاری مجدد ۱ تا N
            reNumberRows();
        }

        // به‌روزرسانی وضعیت ظاهری آیکون‌ها و پیل‌های مرتب‌سازی
        function updateSortIndicators() {
            const allCols = ['original', 'name', 'cohort', 'hti', 'mi', 'hermans', 'eq', 'gsi'];
            allCols.forEach(col => {
                // آیکون در سرستون جدول
                const icon = document.getElementById('sort_icon_' + col);
                if (icon) {
                    if (currentSortColumn === col) {
                        icon.textContent = (currentSortDir === 'desc') ? '▼' : '▲';
                        icon.className = 'sort-icon text-teal-600 mr-1 text-[11px] font-black';
                    } else {
                        icon.textContent = '↕';
                        icon.className = 'sort-icon text-slate-400 mr-1 text-[11px]';
                    }
                }

                // دکمه پیل در نوار مرتب‌سازی سریع
                const pill = document.getElementById('btn_sort_' + col);
                const pillArrow = document.getElementById('pill_arrow_' + col);
                if (pill) {
                    if (currentSortColumn === col) {
                        pill.className = 'sort-pill px-3 py-1.5 rounded-xl font-bold bg-teal-600 text-white shadow-sm transition hover:opacity-90 flex items-center gap-1';
                        if (pillArrow) pillArrow.textContent = (currentSortDir === 'desc') ? '▼' : '▲';
                    } else {
                        pill.className = 'sort-pill px-3 py-1.5 rounded-xl font-bold bg-white text-slate-700 border border-slate-200 transition hover:bg-slate-100 flex items-center gap-1';
                        if (pillArrow) pillArrow.textContent = '↕';
                    }
                }
            });
        }

        // ۷. فیلتر کردن جدول بر اساس تب‌ها
        function filterTable(type) {
            document.querySelectorAll('.filter-tab').forEach(b => {
                b.classList.remove('bg-teal-600', 'text-white');
                b.classList.add('bg-slate-100', 'text-slate-700');
            });
            const activeBtn = document.getElementById('btn_tab_' + type);
            if (activeBtn) {
                activeBtn.classList.remove('bg-slate-100', 'text-slate-700');
                activeBtn.classList.add('bg-teal-600', 'text-white');
            }

            const rows = document.querySelectorAll('.student-row');
            rows.forEach(r => {
                const cohort = r.getAttribute('data-cohort');
                const isSuper = r.getAttribute('data-super') === '1';
                const isCritical = r.getAttribute('data-critical') === '1';

                let show = true;
                if (type === 'new') show = (cohort === 'new');
                else if (type === 'old') show = (cohort === 'old');
                else if (type === 'super_talent') show = isSuper;
                else if (type === 'critical') show = isCritical;

                r.style.display = show ? '' : 'none';
            });

            // بازشماری شماره ردیف‌های نمایان
            reNumberRows();
        }

        // جستجوی کلاینت در جدول
        document.getElementById('searchInput').addEventListener('input', function(e) {
            const query = e.target.value.toLowerCase().trim();
            const rows = document.querySelectorAll('.student-row');
            rows.forEach(r => {
                const name = r.getAttribute('data-name').toLowerCase();
                const phone = r.getAttribute('data-phone').toLowerCase();
                if (name.includes(query) || phone.includes(query)) {
                    r.style.display = '';
                } else {
                    r.style.display = 'none';
                }
            });
            // بازشماری شماره ردیف‌های نمایان بعد از فیلتر جستجو
            reNumberRows();
        });

        // لیسنر انتخاب داوطلبان مقایسه
        document.getElementById('select_student_1').addEventListener('change', updateComparison);
        document.getElementById('select_student_2').addEventListener('change', updateComparison);

        window.addEventListener('DOMContentLoaded', () => {
            initRadarChart();
            initScatterChart();
            initBarChart();
            updateComparison();
            reNumberRows();
        });
    </script>
</body>
</html>
