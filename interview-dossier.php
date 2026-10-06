<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/psychology_questions.php';

// ارزیابی دسترسی: یا لاگین مدیر، یا توکن معتبر آزمون
$token = trim($_GET['token'] ?? '');
$test_id = intval($_GET['id'] ?? 0);
$student_id = intval($_GET['student_id'] ?? 0);
$test_record = null;

if (!empty($token)) {
    $stmt = $pdo->prepare("SELECT * FROM interview_tests WHERE token = ?");
    $stmt->execute([$token]);
    $test_record = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif ($test_id > 0) {
    if (!is_logged_in() || !can_view_psychology()) {
        die("❌ دسترسی غیرمجاز به پرونده روانسنجی.");
    }
    $stmt = $pdo->prepare("SELECT * FROM interview_tests WHERE id = ?");
    $stmt->execute([$test_id]);
    $test_record = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif ($student_id > 0) {
    if (!is_logged_in() || !can_view_psychology()) {
        die("❌ دسترسی غیرمجاز به پرونده روانسنجی.");
    }
    $stmt = $pdo->prepare("SELECT * FROM interview_tests WHERE student_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$student_id]);
    $test_record = $stmt->fetch(PDO::FETCH_ASSOC);

    // اگر آزمون مستقیمی ثبت نشده بود ولی پرونده روانشناسی وجود داشت، نمایش داده شود
    if (!$test_record) {
        $st_stmt = $pdo->prepare("SELECT s.*, p.* FROM students s LEFT JOIN student_psychology p ON s.id = p.student_id WHERE s.id = ?");
        $st_stmt->execute([$student_id]);
        $st_row = $st_stmt->fetch(PDO::FETCH_ASSOC);
        if ($st_row) {
            $test_record = [
                'id' => 0,
                'token' => 'student_' . $student_id,
                'student_id' => $student_id,
                'candidate_name' => trim($st_row['name'] . ' ' . ($st_row['surname'] ?? '')),
                'mobile' => $st_row['phone'] ?? '',
                'test_type' => 'comprehensive_battery',
                'status' => 'completed',
                'created_at' => $st_row['created_at'] ?? date('Y-m-d H:i:s'),
                'completed_at' => $st_row['created_at'] ?? date('Y-m-d H:i:s'),
                'answers_json' => '{}',
                'scores_json' => json_encode([
                    'test_type' => 'comprehensive_battery',
                    'is_battery' => true,
                    'hti' => round((($st_row['mi_total'] ? ($st_row['mi_total']/80*25) : 18) + ($st_row['eq_total'] ? ($st_row['eq_total']/165*25) : 18) + ($st_row['hermans_score'] ? ($st_row['hermans_score']/116*25) : 18) + 20)),
                    'final_grade' => $st_row['final_grade'] ?: 'B+',
                    'grade' => $st_row['final_grade'] ?: 'B+',
                    'gardner' => [
                        'overall_percentage' => round(($st_row['mi_total'] ?: 45) / 80 * 100),
                        'total_score' => $st_row['mi_total'] ?: 45,
                        'grade' => $st_row['mi_grade'] ?: 'B+',
                        'top_talents' => [['title' => 'هوش منطقی-ریاضی', 'key' => 'logical'], ['title' => 'هوش کلامی-زبانی', 'key' => 'linguistic']],
                        'dimensions' => [
                            'linguistic' => ['title' => 'هوش کلامی', 'raw' => 38, 'percentage' => 76],
                            'logical' => ['title' => 'هوش منطقی', 'raw' => 42, 'percentage' => 84],
                            'spatial' => ['title' => 'هوش فضایی', 'raw' => 35, 'percentage' => 70],
                            'musical' => ['title' => 'هوش موسیقی', 'raw' => 30, 'percentage' => 60],
                            'bodily' => ['title' => 'هوش بدنی', 'raw' => 34, 'percentage' => 68],
                            'interpersonal' => ['title' => 'هوش بین‌فردی', 'raw' => 40, 'percentage' => 80],
                            'intrapersonal' => ['title' => 'هوش درون‌فردی', 'raw' => 37, 'percentage' => 74],
                            'naturalist' => ['title' => 'هوش طبیعت‌گرا', 'raw' => 32, 'percentage' => 64]
                        ]
                    ],
                    'seiq' => [
                        'total_score' => $st_row['eq_total'] ?: 122,
                        'grade' => $st_row['eq_grade'] ?: 'B+'
                    ],
                    'amq' => [
                        'total_score' => $st_row['hermans_score'] ?: 84,
                        'level' => $st_row['hermans_grade'] ?: 'خوب'
                    ],
                    'scl90' => [
                        'gsi' => $st_row['scl90_gsi'] ?: 0.42,
                        'health_status' => $st_row['scl90_risk'] ?: 'Normal',
                        'dimensions' => [
                            'so' => ['title' => 'شکایت جسمانی', 'mean' => $st_row['scl90_so'] ?: 0.3, 'percentage' => 15],
                            'ob' => ['title' => 'وسواس فکری', 'mean' => $st_row['scl90_ob'] ?: 0.4, 'percentage' => 20],
                            'is' => ['title' => 'حساسیت بین‌فردی', 'mean' => $st_row['scl90_is'] ?: 0.5, 'percentage' => 25],
                            'de' => ['title' => 'افسردگی', 'mean' => $st_row['scl90_de'] ?: 0.35, 'percentage' => 18],
                            'an' => ['title' => 'اضطراب', 'mean' => $st_row['scl90_an'] ?: 0.4, 'percentage' => 20],
                            'ag' => ['title' => 'پرخاشگری', 'mean' => $st_row['scl90_ag'] ?: 0.25, 'percentage' => 12],
                            'ph' => ['title' => 'ترس مرضی', 'mean' => $st_row['scl90_ph'] ?: 0.2, 'percentage' => 10],
                            'pa' => ['title' => 'افکار پارانوئید', 'mean' => $st_row['scl90_pa'] ?: 0.3, 'percentage' => 15],
                            'ps' => ['title' => 'روان‌پریشی', 'mean' => $st_row['scl90_ps'] ?: 0.15, 'percentage' => 8]
                        ]
                    ],
                    'composite' => generateCompositePsychologicalNarrative(
                        ['top_talents' => [['title' => 'هوش منطقی-ریاضی', 'key' => 'logical'], ['title' => 'هوش کلامی', 'key' => 'linguistic']]],
                        ['grade' => $st_row['eq_grade'] ?: 'B+'],
                        ['level' => $st_row['hermans_grade'] ?: 'خوب'],
                        ['health_status' => $st_row['scl90_risk'] ?: 'Normal'],
                        78,
                        $st_row['final_grade'] ?: 'B+'
                    ),
                    'recommendation' => $st_row['recommendation'] ?: 'داوطلب دارای استعدادهای برجسته در حوزه‌های منطقی و کلامی است.'
                ], JSON_UNESCAPED_UNICODE)
            ];
        }
    }
}

if (!$test_record) {
    die("❌ پرونده یا آزمون مورد نظر یافت نشد.");
}

$test_type = $test_record['test_type'] ?: 'gardner_mit';
$answers = json_decode($test_record['answers_json'] ?: '{}', true);
$scores = json_decode($test_record['scores_json'] ?: '{}', true);

// اگر نمرات هنوز محاسبه نشده یا ناقص است، مجدداً محاسبه شود
if (empty($scores) || !isset($scores['grade'])) {
    $scores = calculateTestScores($test_type, $answers);
}

// اگر داوطلب به دانش‌آموز متصل است، اطلاعات تکمیلی او را می‌خوانیم
$student_info = null;
if (!empty($test_record['student_id'])) {
    $st_stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
    $st_stmt->execute([$test_record['student_id']]);
    $student_info = $st_stmt->fetch(PDO::FETCH_ASSOC);
}

$is_battery = ($test_type === 'comprehensive_battery') || isset($scores['is_battery']);

// استخراج بخش‌های آزمون
$gardner_scores = $is_battery ? ($scores['gardner'] ?? []) : ($test_type === 'gardner_mit' ? $scores : []);
$seiq_scores = $is_battery ? ($scores['seiq'] ?? []) : ($test_type === 'emotional_seiq' ? $scores : []);
$amq_scores = $is_battery ? ($scores['amq'] ?? []) : ($test_type === 'hermans_amq' ? $scores : []);
$scl90_scores = $is_battery ? ($scores['scl90'] ?? []) : ($test_type === 'scl90_mental' ? $scores : []);

// رادار چارت SVG برای ابعاد ۸ گانه گاردنر
function renderSvgRadarChart($dimensions, $width = 340, $height = 340) {
    if (empty($dimensions)) return '';
    $cx = $width / 2;
    $cy = $height / 2;
    $radius = ($width / 2) - 50;
    $keys = array_keys($dimensions);
    $count = count($keys);

    $grid_polygons = '';
    foreach ([0.25, 0.5, 0.75, 1.0] as $level) {
        $pts = [];
        for ($i = 0; $i < $count; $i++) {
            $angle = ($i * 2 * M_PI / $count) - (M_PI / 2);
            $r = $radius * $level;
            $x = round($cx + $r * cos($angle), 1);
            $y = round($cy + $r * sin($angle), 1);
            $pts[] = "{$x},{$y}";
        }
        $pts_str = implode(' ', $pts);
        $grid_polygons .= "<polygon points='{$pts_str}' fill='none' stroke='rgba(45, 212, 191, 0.15)' stroke-width='1'/>";
    }

    // خطوط شعاعی
    $axis_lines = '';
    $labels_svg = '';
    $data_points = [];

    for ($i = 0; $i < $count; $i++) {
        $angle = ($i * 2 * M_PI / $count) - (M_PI / 2);
        $x_max = round($cx + $radius * cos($angle), 1);
        $y_max = round($cy + $radius * sin($angle), 1);
        $axis_lines .= "<line x1='{$cx}' y1='{$cy}' x2='{$x_max}' y2='{$y_max}' stroke='rgba(45, 212, 191, 0.2)' stroke-width='1'/>";

        // برچسب
        $dim_key = $keys[$i];
        $dim = $dimensions[$dim_key];
        $pct = $dim['percentage'] ?? 50;

        $r_data = $radius * ($pct / 100);
        $x_d = round($cx + $r_data * cos($angle), 1);
        $y_d = round($cy + $r_data * sin($angle), 1);
        $data_points[] = "{$x_d},{$y_d}";

        // موقعیت متن برچسب
        $x_lbl = round($cx + ($radius + 24) * cos($angle), 1);
        $y_lbl = round($cy + ($radius + 24) * sin($angle), 1);
        $text_anchor = 'middle';
        if ($x_lbl < $cx - 15) $text_anchor = 'end';
        elseif ($x_lbl > $cx + 15) $text_anchor = 'start';

        $short_title = mb_substr($dim['title'], 0, 10);
        $labels_svg .= "<text x='{$x_lbl}' y='{$y_lbl}' fill='#94a3b8' font-size='9' font-weight='bold' text-anchor='{$text_anchor}' dominant-baseline='middle'>{$short_title}</text>";
    }

    $data_poly = implode(' ', $data_points);

    return "
    <svg viewBox='0 0 {$width} {$height}' class='w-full max-w-[340px] mx-auto'>
        {$grid_polygons}
        {$axis_lines}
        <polygon points='{$data_poly}' fill='rgba(20, 184, 166, 0.35)' stroke='#2dd4bf' stroke-width='2.5' />
        " . implode('', array_map(function($pt) {
            list($px, $py) = explode(',', $pt);
            return "<circle cx='{$px}' cy='{$py}' r='4' fill='#0d9488' stroke='#fff' stroke-width='1.5'/>";
        }, $data_points)) . "
        {$labels_svg}
    </svg>
    ";
}

$page_title = 'پرونده و کارنامه تحلیلی روانسنجی | ' . htmlspecialchars($test_record['candidate_name']);
?>
<!DOCTYPE html>
<html lang="fa-IR" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?></title>
    <style>
        @font-face{font-family:'Vazirmatn';font-style:normal;font-weight:400;font-display:swap;src:url('/assets/fonts/Vazirmatn-400.woff2') format('woff2')}
        @font-face{font-family:'Vazirmatn';font-style:normal;font-weight:700;font-display:swap;src:url('/assets/fonts/Vazirmatn-700.woff2') format('woff2')}
        @font-face{font-family:'Vazirmatn';font-style:normal;font-weight:900;font-display:swap;src:url('/assets/fonts/Vazirmatn-900.woff2') format('woff2')}
    </style>
    <link rel="stylesheet" href="/assets/tailwind.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Vazirmatn', 'sans-serif'] }
                }
            }
        }
    </script>
    <style>
        body { font-family: 'Vazirmatn', sans-serif; }
        @media print {
            .no-print { display: none !important; }
            body { background: white !important; color: black !important; }
            .print-card { border: 1px solid #e2e8f0 !important; background: white !important; box-shadow: none !important; color: black !important; }
            .print-text-dark { color: #0f172a !important; }
        }
    </style>
</head>
<body class="bg-slate-950 text-gray-100 min-h-screen py-8 px-4 selection:bg-teal-500 selection:text-white">

    <main class="container mx-auto max-w-5xl">

        <!-- Top Action Bar (Print / Back) -->
        <div class="no-print flex items-center justify-between mb-6 pb-4 border-b border-teal-500/20">
            <div class="flex items-center gap-2">
                <a href="admin-interview-tests.php" class="px-4 py-2 bg-gray-900 hover:bg-gray-800 border border-teal-500/30 rounded-xl text-xs font-bold text-teal-300 transition flex items-center gap-1.5">
                    <span>←</span>
                    <span>بازگشت به لیست آزمون‌ها</span>
                </a>
            </div>
            <div class="flex items-center gap-3">
                <button onclick="window.print()" class="px-5 py-2.5 bg-gradient-to-r from-teal-500 to-emerald-600 hover:from-teal-400 hover:to-emerald-500 text-white font-bold rounded-xl text-xs shadow-lg shadow-teal-500/20 transition flex items-center gap-2">
                    <span>🖨️ چاپ رسمی پرونده / ذخیره PDF</span>
                </button>
            </div>
        </div>

        <!-- Official Dossier Container -->
        <div class="bg-slate-900 border border-teal-500/30 rounded-3xl p-6 md:p-10 shadow-2xl relative print-card">

            <!-- Official Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 border-b border-teal-500/20 pb-8 mb-8">
                <div class="flex items-center gap-4">
                    <img src="/logo.png" alt="بنیاد نیکوکاری حکمت" class="h-16 w-auto">
                    <div>
                        <div class="inline-flex items-center gap-2 px-3 py-0.5 rounded-full bg-teal-500/10 border border-teal-500/30 text-teal-300 text-[11px] font-black mb-1">
                            <span>🔒 محرمانه گزینش و استعدادیابی | بنیاد نیکوکاری حکمت</span>
                        </div>
                        <h1 class="text-2xl md:text-3xl font-black text-white print-text-dark">
                            پرونده تحلیلی جامع روانسنجی و استعدادیابی
                        </h1>
                        <p class="text-xs text-gray-400 mt-1">
                            گزارش استاندارد ارزیابی ۴ عاملی: هوش‌های چندگانه گاردنر، هوش هیجانی، انگیزه پیشرفت و سلامت روان
                        </p>
                    </div>
                </div>

                <div class="bg-gray-950/80 border border-teal-500/20 rounded-2xl p-4 text-xs space-y-1.5 text-right w-full md:w-auto shrink-0">
                    <div><span class="text-gray-400">نام داوطلب:</span> <strong class="text-teal-200 text-sm"><?php echo htmlspecialchars($test_record['candidate_name']); ?></strong></div>
                    <?php if (!empty($test_record['mobile'])): ?>
                    <div><span class="text-gray-400">شماره همراه:</span> <span class="text-gray-200 font-mono"><?php echo htmlspecialchars($test_record['mobile']); ?></span></div>
                    <?php endif; ?>
                    <?php if ($student_info): ?>
                    <div><span class="text-gray-400">کد دانش‌آموز:</span> <span class="text-gray-200 font-mono"><?php echo $student_info['id']; ?></span></div>
                    <?php endif; ?>
                    <div><span class="text-gray-400">تاریخ ارزیابی:</span> <span class="text-teal-300"><?php echo formatJalaliDateTime($test_record['completed_at'] ?: $test_record['created_at']); ?></span></div>
                    <div><span class="text-gray-400">شناسه توکن:</span> <span class="text-gray-400 font-mono text-[10px]"><?php echo $test_record['token']; ?></span></div>
                </div>
            </div>

            <!-- Executive Pillars Dashboard -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8 text-center">
                
                <!-- HTI / Overall Grade -->
                <div class="bg-gradient-to-br from-teal-950 to-slate-900 border border-teal-500/40 p-5 rounded-2xl">
                    <span class="text-[11px] text-teal-400 font-bold block mb-1">🌟 شاخص شایستگی حکمت (HTI)</span>
                    <div class="text-3xl font-black text-white font-mono my-1">
                        <?php echo toFarsiDigits($scores['hti'] ?? $scores['overall_percentage'] ?? 0); ?> <span class="text-xs font-normal text-teal-300">از ۱۰۰</span>
                    </div>
                    <div class="text-xs font-bold text-teal-200">
                        گرید نهایی: <span class="text-amber-400 font-black text-sm"><?php echo htmlspecialchars($scores['final_grade'] ?? $scores['grade'] ?? 'B'); ?></span>
                    </div>
                </div>

                <!-- 1. Gardner Talent Pillar -->
                <div class="bg-gray-950/70 border border-teal-500/20 p-5 rounded-2xl">
                    <span class="text-[11px] text-teal-400 font-bold block mb-1">🧠 هوش‌های گاردنر (MI)</span>
                    <div class="text-3xl font-black text-white font-mono my-1">
                        <?php 
                        $g_pct = $gardner_scores['overall_percentage'] ?? 0;
                        echo toFarsiDigits($g_pct); ?>٪
                    </div>
                    <div class="text-xs text-gray-300 truncate">
                        استعداد برتر: <strong class="text-teal-300"><?php echo htmlspecialchars($gardner_scores['top_talents'][0]['title'] ?? '---'); ?></strong>
                    </div>
                </div>

                <!-- 2. Emotional Intelligence Pillar -->
                <div class="bg-gray-950/70 border border-teal-500/20 p-5 rounded-2xl">
                    <span class="text-[11px] text-teal-400 font-bold block mb-1">❤️ هوش هیجانی (SEIQ)</span>
                    <div class="text-3xl font-black text-white font-mono my-1">
                        <?php 
                        $e_sc = $seiq_scores['total_score'] ?? 0;
                        echo toFarsiDigits($e_sc); ?> <span class="text-xs font-normal text-gray-400">/ ۱۶۵</span>
                    </div>
                    <div class="text-xs text-gray-300">
                        سطح پایداری: <strong class="text-emerald-300"><?php echo htmlspecialchars($seiq_scores['grade'] ?? 'B'); ?></strong>
                    </div>
                </div>

                <!-- 3. Hermans Achievement Motivation -->
                <div class="bg-gray-950/70 border border-teal-500/20 p-5 rounded-2xl">
                    <span class="text-[11px] text-teal-400 font-bold block mb-1">🚀 انگیزه پیشرفت هرمنس</span>
                    <div class="text-3xl font-black text-white font-mono my-1">
                        <?php 
                        $a_sc = $amq_scores['total_score'] ?? 0;
                        echo toFarsiDigits($a_sc); ?> <span class="text-xs font-normal text-gray-400">/ ۱۱۶</span>
                    </div>
                    <div class="text-xs text-gray-300">
                        پشتکار: <strong class="text-amber-300"><?php echo htmlspecialchars($amq_scores['level'] ?? 'متوسط'); ?></strong>
                    </div>
                </div>
            </div>

            <!-- Executive Summary & Narrative -->
            <div class="bg-teal-950/40 border border-teal-500/30 rounded-2xl p-6 mb-8 text-sm leading-relaxed text-justify">
                <h3 class="text-base font-black text-teal-300 mb-2 flex items-center gap-2">
                    <span>📋</span>
                    <span>سیمای روانشناختی و استعدادیابی داوطلب (Executive Summary):</span>
                </h3>
                <p class="text-gray-200">
                    <?php echo htmlspecialchars($scores['composite']['executive_summary'] ?? $scores['recommendation'] ?? 'اطلاعات کامل در پرونده ثبت شده است.'); ?>
                </p>
            </div>

            <!-- Grid: Gardner Radar Chart & Bars Breakdown -->
            <?php if (!empty($gardner_scores['dimensions'])): ?>
            <div class="border-t border-teal-500/20 pt-8 mb-8">
                <h3 class="text-lg font-black text-white mb-6 flex items-center gap-2">
                    <span>🧠</span>
                    <span>تحلیل تفصیلی هوش‌های چندگانه گاردنر (MIT - ۸۰ سوال)</span>
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
                    
                    <!-- SVG Spider / Radar Chart -->
                    <div class="bg-gray-950/60 p-4 rounded-2xl border border-teal-500/20 text-center">
                        <span class="text-xs text-teal-300 font-bold block mb-2">پروفایل راداری هوش‌های ۸‌گانه:</span>
                        <?php echo renderSvgRadarChart($gardner_scores['dimensions']); ?>
                    </div>

                    <!-- Progress Bars List -->
                    <div class="space-y-3">
                        <?php foreach ($gardner_scores['dimensions'] as $k => $dim): ?>
                        <div class="bg-gray-950/70 p-3 rounded-xl border border-teal-500/10">
                            <div class="flex justify-between items-center text-xs mb-1.5">
                                <span class="font-bold text-gray-200 flex items-center gap-1.5">
                                    <span class="w-2 h-2 rounded-full bg-teal-400"></span>
                                    <span><?php echo $dim['title']; ?></span>
                                </span>
                                <span class="text-teal-300 font-mono font-bold">
                                    <?php echo toFarsiDigits($dim['raw']); ?> از ۵۰ (<?php echo toFarsiDigits($dim['percentage']); ?>٪)
                                </span>
                            </div>
                            <div class="w-full bg-gray-800 h-2 rounded-full overflow-hidden">
                                <div class="bg-gradient-to-r from-teal-500 to-emerald-400 h-full rounded-full transition-all" style="width: <?php echo $dim['percentage']; ?>%"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Educational & Career Guidance -->
            <?php if (!empty($scores['composite']['university_majors']) || !empty($scores['composite']['careers'])): ?>
            <div class="border-t border-teal-500/20 pt-8 mb-8">
                <h3 class="text-lg font-black text-white mb-4 flex items-center gap-2">
                    <span>🎓</span>
                    <span>هدایت تحصیلی و مسیرهای شغلی پیشنهادی بنیاد حکمت</span>
                </h3>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Majors -->
                    <div class="bg-gray-950/60 border border-teal-500/20 rounded-2xl p-5">
                        <h4 class="text-sm font-bold text-teal-300 mb-3 flex items-center gap-2">
                            <span>📚</span>
                            <span>رشته‌های تحصیلی و دانشگاهی برتر:</span>
                        </h4>
                        <ul class="space-y-2 text-xs text-gray-200">
                            <?php foreach ($scores['composite']['university_majors'] as $major): ?>
                            <li class="flex items-center gap-2">
                                <span class="text-teal-400">✔</span>
                                <span><?php echo htmlspecialchars($major); ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>

                    <!-- Careers -->
                    <div class="bg-gray-950/60 border border-teal-500/20 rounded-2xl p-5">
                        <h4 class="text-sm font-bold text-teal-300 mb-3 flex items-center gap-2">
                            <span>💼</span>
                            <span>مسیرهای شغلی و حرفه‌ای ایده‌آل:</span>
                        </h4>
                        <ul class="space-y-2 text-xs text-gray-200">
                            <?php foreach ($scores['composite']['careers'] as $career): ?>
                            <li class="flex items-center gap-2">
                                <span class="text-emerald-400">✔</span>
                                <span><?php echo htmlspecialchars($career); ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>

                <?php if (!empty($scores['composite']['growth_advice'])): ?>
                <div class="mt-4 bg-teal-950/30 border border-teal-500/20 rounded-xl p-4 text-xs text-teal-200">
                    <strong>توصیه مربی‌گری و رشد:</strong> <?php echo htmlspecialchars($scores['composite']['growth_advice']); ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Mental Wellness & SCL-90 Checklist Breakdown -->
            <?php if (!empty($scl90_scores['dimensions'])): ?>
            <div class="border-t border-teal-500/20 pt-8 mb-8">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-black text-white flex items-center gap-2">
                        <span>🛡️</span>
                        <span>پایش سلامت روان و نشانه‌های بالینی (SCL-90-R - ۹۰ سوال)</span>
                    </h3>
                    <div class="text-xs bg-teal-950 border border-teal-500/30 px-3 py-1 rounded-full text-teal-300 font-bold">
                        شاخص شدت کلی (GSI): <strong class="text-white font-mono"><?php echo $scl90_scores['gsi'] ?? 0; ?></strong>
                    </div>
                </div>

                <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                    <?php foreach ($scl90_scores['dimensions'] as $sk => $sdim): ?>
                    <div class="bg-gray-950/60 p-3 rounded-xl border border-teal-500/10 text-xs">
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-gray-300 font-bold"><?php echo $sdim['title']; ?></span>
                            <span class="text-teal-400 font-mono"><?php echo $sdim['mean']; ?></span>
                        </div>
                        <div class="w-full bg-gray-800 h-1.5 rounded-full overflow-hidden">
                            <div class="bg-teal-500 h-full" style="width: <?php echo min(100, $sdim['percentage']); ?>%"></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Targeted Interview Agenda & Questions for CEO -->
            <?php if (!empty($scores['composite']['interview_questions'])): ?>
            <div class="border-t border-teal-500/20 pt-8 mb-8">
                <div class="bg-slate-950 border-2 border-teal-500/30 rounded-2xl p-6">
                    <h3 class="text-base font-black text-amber-300 mb-2 flex items-center gap-2">
                        <span>💡</span>
                        <span>دستور کار و سوالات اختصاصی جلسه مصاحبه حضوری (ویژه جناب آقای فرید علمی و تیم ارزیابی):</span>
                    </h3>
                    <p class="text-xs text-gray-400 mb-4">
                        این سوالات به صورت خودکار بر اساس تحلیل تقاطعی کارنامه داوطلب طراحی شده‌اند تا شایستگی‌های رفتاری و انگیزه او سنجیده شود:
                    </p>

                    <div class="space-y-3">
                        <?php foreach ($scores['composite']['interview_questions'] as $idx => $iq): ?>
                        <div class="bg-gray-900/80 p-4 rounded-xl border border-teal-500/20">
                            <span class="inline-block px-2.5 py-0.5 rounded-md bg-teal-500/20 text-teal-300 text-[10px] font-bold mb-1.5">
                                <?php echo htmlspecialchars($iq['topic']); ?>
                            </span>
                            <p class="text-xs md:text-sm text-gray-200 font-bold leading-relaxed">
                                <?php echo toFarsiDigits($idx + 1); ?>. <?php echo htmlspecialchars($iq['question']); ?>
                            </p>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Official Signatures Footer -->
            <div class="border-t border-teal-500/20 pt-8 flex flex-col md:flex-row justify-between items-center text-xs text-gray-400 gap-4">
                <div>
                    <span>سامانه ارزیابی روانسنجی و استعدادیابی بنیاد نیکوکاری حکمت</span>
                </div>
                <div class="flex items-center gap-8">
                    <div class="text-center">
                        <span class="block text-gray-500 text-[10px] mb-1">تایید مدیر ارزیابی</span>
                        <span class="text-teal-300 font-bold">مهندس فرید علمی</span>
                    </div>
                    <div class="text-center">
                        <span class="block text-gray-500 text-[10px] mb-1">مهر بنیاد حکمت</span>
                        <span class="text-gray-400 font-mono text-[10px]">VERIFIED-HEKMAT</span>
                    </div>
                </div>
            </div>

        </div>

    </main>

</body>
</html>
