<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/db.php';
require_once 'includes/psychology_questions.php';

// Handle AJAX requests for live saving and submission
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json; charset=utf-8');
    $token = trim($_POST['token'] ?? '');

    if (empty($token)) {
        echo json_encode(['success' => false, 'message' => 'توکن آزمون نامعتبر است.']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT * FROM interview_tests WHERE token = ?");
    $stmt->execute([$token]);
    $test = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$test) {
        echo json_encode(['success' => false, 'message' => 'آزمون یافت نشد.']);
        exit;
    }

    if ($test['status'] === 'completed') {
        echo json_encode(['success' => false, 'message' => 'این آزمون قبلاً به پایان رسیده و ثبت نهایی شده است.']);
        exit;
    }

    $action = $_POST['ajax_action'];

    if ($action === 'start_test') {
        $now = date('Y-m-d H:i:s');
        $up = $pdo->prepare("UPDATE interview_tests SET status = 'in_progress', started_at = COALESCE(started_at, ?) WHERE id = ?");
        $up->execute([$now, $test['id']]);
        echo json_encode(['success' => true]);
        exit;
    }

    if ($action === 'sync_answers') {
        $answers_raw = $_POST['answers'] ?? '{}';
        $incoming = json_decode($answers_raw, true);
        if (is_array($incoming) && !empty($incoming)) {
            $existing = json_decode($test['answers_json'] ?: '{}', true);
            if (!is_array($existing)) $existing = [];
            
            $merged = array_replace_recursive($existing, $incoming);
            $count = 0;
            foreach ($merged as $s_answers) {
                if (is_array($s_answers)) $count += count($s_answers);
                else $count++;
            }
            
            $up = $pdo->prepare("UPDATE interview_tests SET answers_json = ?, current_question = ?, status = 'in_progress' WHERE id = ?");
            $up->execute([json_encode($merged, JSON_UNESCAPED_UNICODE), $count, $test['id']]);
            
            echo json_encode(['success' => true, 'synced_count' => $count]);
            exit;
        }
        echo json_encode(['success' => false, 'message' => 'داده‌ای برای همگام‌سازی ارسال نشد.']);
        exit;
    }

    if ($action === 'save_answer') {
        $stage = trim($_POST['stage'] ?? '');
        $question_id = intval($_POST['question_id'] ?? 0);
        $answer_val = intval($_POST['answer'] ?? 0);
        $current_q = intval($_POST['current_question'] ?? $question_id);

        $answers = json_decode($test['answers_json'] ?: '{}', true);
        if (!is_array($answers)) $answers = [];

        if (!empty($stage)) {
            if (!isset($answers[$stage]) || !is_array($answers[$stage])) {
                $answers[$stage] = [];
            }
            $answers[$stage][(string)$question_id] = $answer_val;
        } else {
            $answers[(string)$question_id] = $answer_val;
        }

        $up = $pdo->prepare("UPDATE interview_tests SET answers_json = ?, current_question = ?, status = 'in_progress' WHERE id = ?");
        $up->execute([json_encode($answers, JSON_UNESCAPED_UNICODE), $current_q, $test['id']]);

        echo json_encode(['success' => true, 'saved_count' => count($answers)]);
        exit;
    }

    if ($action === 'submit_test') {
        $answers_raw = $_POST['answers'] ?? '{}';
        $answers = json_decode($answers_raw, true);
        if (!is_array($answers) || empty($answers)) {
            $answers = json_decode($test['answers_json'] ?: '{}', true) ?: [];
        }

        // محاسبه هوشمند نمرات بر اساس نوع آزمون
        $test_type = $test['test_type'] ?: 'comprehensive_battery';
        $scores = calculateTestScores($test_type, $answers);
        $now = date('Y-m-d H:i:s');

        $up = $pdo->prepare("UPDATE interview_tests SET answers_json = ?, scores_json = ?, status = 'completed', completed_at = ? WHERE id = ?");
        $up->execute([
            json_encode($answers, JSON_UNESCAPED_UNICODE),
            json_encode($scores, JSON_UNESCAPED_UNICODE),
            $now,
            $test['id']
        ]);

        // همگام‌سازی مستقیم با جدول پرونده روانشناسی دانش‌آموز (student_psychology)
        if (!empty($test['student_id'])) {
            syncInterviewScoresToStudentPsychology($pdo, $test['student_id'], $test_type, $scores);
        }

        echo json_encode([
            'success' => true,
            'message' => 'آزمون با موفقیت ثبت نهایی شد.',
            'scores' => $scores
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'عملیات نامعتبر است.']);
    exit;
}

// Get Token from GET parameter
$token = trim($_GET['token'] ?? '');
$test_record = null;
$error_message = '';

if (!empty($token)) {
    $stmt = $pdo->prepare("SELECT * FROM interview_tests WHERE token = ?");
    $stmt->execute([$token]);
    $test_record = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$test_record) {
        $error_message = 'لینک آزمون معتبر نیست یا منقضی شده است. لطفاً با پشتیبانی بنیاد حکمت تماس بگیرید.';
    }
} else {
    // اگر توکن در URL نبود، بررسی شماره موبایل وارد شده توسط کاربر
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['search_mobile'])) {
        $mobile = trim($_POST['mobile'] ?? '');
        $mobile_clean = preg_replace('/[^0-9]/', '', toEnglishDigits($mobile));
        if (!empty($mobile_clean)) {
            $stmt = $pdo->prepare("SELECT * FROM interview_tests WHERE mobile LIKE ? OR (national_id IS NOT NULL AND national_id != '' AND national_id = ?) ORDER BY id DESC LIMIT 1");
            $stmt->execute(['%' . substr($mobile_clean, -10), $mobile_clean]);
            $found = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($found) {
                header("Location: interview-test.php?token=" . urlencode($found['token']));
                exit;
            } else {
                $error_message = 'هیچ آزمون فعالی برای این شماره همراه یا کدملی در لیست داوطلبان مجاز ثبت نشده است. لطفاً با پشتیبانی بنیاد حکمت تماس بگیرید.';
            }
        }
    }
}

// نوع آزمون درخواستی
$requested_test = trim($_GET['test'] ?? 'comprehensive_battery');

// بارگذاری ساختار آزمون متناسب با test_type
$active_test_type = $test_record ? ($test_record['test_type'] ?: 'comprehensive_battery') : $requested_test;
$test_def = getTestDefinition($active_test_type);
$is_battery = ($active_test_type === 'comprehensive_battery');

// ساختار مراحل آزمون (Stages)
if ($is_battery) {
    $stages = [
        [
            'id' => 'gardner_mit',
            'stage_num' => 1,
            'title' => 'بخش ۱: هوش‌های چندگانه گاردنر',
            'short_title' => 'هوش‌های چندگانه',
            'icon' => '🧠',
            'badge' => 'استعدادیابی شناختی (MIT)',
            'questions' => getGardnerMITQuestions(),
            'default_options' => getGardnerMITOptions(),
            'time_limit' => 20,
            'intro_note' => '۸۰ پرسش جهت شناسایی استعدادها و نقاط قوت هوش‌های هشت‌گانه شما.',
            'intermission_msg' => 'بسیار عالی! بخش اول (هوش‌های چندگانه گاردنر) با موفقیت ثبت شد. 🌿 کمی آب بنوشید، چند ثانیه چشمانتان را ببندید و یک نفس عمیق بکشید.'
        ],
        [
            'id' => 'emotional_seiq',
            'stage_num' => 2,
            'title' => 'بخش ۲: هوش هیجانی (SEIQ)',
            'short_title' => 'هوش هیجانی',
            'icon' => '❤️',
            'badge' => 'مدیریت احساسات و خودآگاهی',
            'questions' => getSEIQQuestions(),
            'default_options' => getSEIQDefaultOptions(),
            'time_limit' => 25,
            'intro_note' => '۳۳ پرسش درباره نحوه برخورد با احساسات، خودمدیریتی، همدلی و روابط اجتماعی.',
            'intermission_msg' => 'آفرین به شما! بخش دوم (هوش هیجانی) نیز به پایان رسید. 🌟 چند نفس عمیق بکشید تا برای بخش سوم آماده شوید.'
        ],
        [
            'id' => 'hermans_amq',
            'stage_num' => 3,
            'title' => 'بخش ۳: انگیزه پیشرفت هرمنس (AMQ)',
            'short_title' => 'انگیزه پیشرفت',
            'icon' => '🚀',
            'badge' => 'پشتکار و اراده پیشرفت',
            'questions' => getAMQQuestions(),
            'default_options' => [],
            'time_limit' => 30,
            'intro_note' => '۲۹ پرسش تستی جهت ارزیابی سطح آرزو، پشتکار و اشتیاق برای رسیدن به اهداف.',
            'intermission_msg' => 'فوق‌العاده پیش رفتی! بخش سوم (انگیزه پیشرفت) هم ثبت شد. 🎯 تنها یک مرحله نهایی تا اتمام آزمون باقی مانده است.'
        ],
        [
            'id' => 'scl90_mental',
            'stage_num' => 4,
            'title' => 'بخش ۴: سلامت و بهزیستی روان (SCL-90)',
            'short_title' => 'سلامت و بهزیستی روان',
            'icon' => '🛡️',
            'badge' => 'پایش آرامش و بهزیستی درونی',
            'questions' => getSCL90Questions(),
            'default_options' => getSCL90Options(),
            'time_limit' => 15,
            'intro_note' => '۹۰ پرسش کوتاه درباره حالات و احساسات شما طی یک هفته گذشته تا امروز.',
            'intermission_msg' => ''
        ]
    ];
} else {
    $stages = [
        [
            'id' => $active_test_type,
            'stage_num' => 1,
            'title' => $test_def['title'],
            'short_title' => $test_def['short_title'] ?? $test_def['title'],
            'icon' => $test_def['icon'] ?? '📝',
            'badge' => $test_def['badge'] ?? 'ارزیابی آنلاین',
            'questions' => $test_def['questions'],
            'default_options' => $test_def['default_options'] ?? [],
            'time_limit' => $test_def['time_per_question'] ?? 20,
            'intro_note' => $test_def['intro_text'] ?? '',
            'intermission_msg' => ''
        ]
    ];
}

$total_stages = count($stages);
$total_questions_all = 0;
foreach ($stages as $stg) {
    $total_questions_all += count($stg['questions']);
}

$page_title = ($test_def['title'] ?? 'سامانه آزمون‌های آنلاین') . ' | بنیاد نیکوکاری حکمت';
$page_desc = $test_def['subtitle'] ?? '';
$is_private_page = true;
?>
<!DOCTYPE html>
<html lang="fa-IR" dir="rtl" class="scroll-smooth">
<head>
    <?php include 'includes/head.php'; ?>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#03171d">
    <style>
        /* Mobile-First Zen Architecture & Anti-Cheat Styles */
        body {
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
            -webkit-touch-callout: none;
            -webkit-tap-highlight-color: transparent;
            touch-action: manipulation;
        }
        .zen-bg {
            background: radial-gradient(circle at 50% 10%, #062e2c 0%, #03171d 60%, #010b0e 100%);
        }
        .glass-panel {
            background: rgba(8, 38, 43, 0.9);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(45, 212, 191, 0.25);
        }
        .option-card {
            transition: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
            touch-action: manipulation;
            -webkit-tap-highlight-color: transparent;
        }
        .option-card:hover {
            border-color: #2dd4bf;
            background: rgba(20, 184, 166, 0.2);
        }
        .option-card:active {
            transform: scale(0.98);
            background: rgba(20, 184, 166, 0.35) !important;
            border-color: #2dd4bf !important;
        }
        .timer-circle {
            transition: stroke-dashoffset 1s linear, stroke 0.3s ease;
        }
        @keyframes gentle-pulse {
            0%, 100% { opacity: 0.9; transform: scale(1); }
            50% { opacity: 1; transform: scale(1.02); }
        }
        .zen-pulse {
            animation: gentle-pulse 3s infinite ease-in-out;
        }
        @keyframes zenBreath {
            0%, 100% { transform: scale(1); opacity: 0.85; }
            50% { transform: scale(1.12); opacity: 1; }
        }
        .zen-breath {
            animation: zenBreath 4s infinite ease-in-out;
        }
        .fade-in {
            animation: fadeIn 0.25s ease-out forwards;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(5px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="zen-bg text-gray-100 font-sans min-h-[100dvh] flex flex-col justify-between selection:bg-teal-500 selection:text-white antialiased" oncontextmenu="return false;">

    <!-- Top App Navigation Bar (Responsive for Mobile & Desktop) -->
    <header class="w-full border-b border-teal-500/15 bg-gray-950/80 backdrop-blur-md sticky top-0 py-2.5 px-3 sm:px-6 md:px-8 flex items-center justify-between z-30">
        <div class="flex items-center gap-2.5">
            <img src="/logo.png" alt="بنیاد نیکوکاری حکمت" class="h-8 sm:h-9 w-auto">
            <div>
                <div class="flex items-center gap-1.5">
                    <span class="text-xs sm:text-sm font-black text-teal-300">نرومایند</span>
                    <span class="text-[10px] sm:text-xs text-gray-400 font-medium">| بنیاد حکمت</span>
                </div>
                <span class="text-[10px] text-gray-400 hidden sm:block">سامانه آزمون‌های آنلاین و استعدادیابی داوطلبان</span>
            </div>
        </div>
        
        <div class="flex items-center gap-2">
            <?php if ($test_record): ?>
            <!-- Candidate Chip -->
            <div class="flex items-center gap-1.5 bg-teal-950/70 border border-teal-500/30 px-2.5 py-1 rounded-full text-xs">
                <span class="w-2 h-2 rounded-full bg-teal-400 animate-ping shrink-0"></span>
                <span class="text-gray-400 hidden xs:inline">داوطلب:</span>
                <strong class="text-teal-200 truncate max-w-[110px] sm:max-w-[180px]"><?php echo htmlspecialchars($test_record['candidate_name']); ?></strong>
            </div>
            <?php endif; ?>
        </div>
    </header>

    <!-- Main Container (Mobile Full-Height Friendly) -->
    <main class="flex-1 flex flex-col items-center justify-start sm:justify-center p-3 sm:p-5 md:p-6 relative">
        <div class="w-full max-w-2xl relative z-10 my-auto">

            <?php if (!empty($error_message)): ?>
                <!-- Error Screen -->
                <div class="glass-panel rounded-2xl sm:rounded-3xl p-6 sm:p-8 text-center shadow-2xl border-rose-500/30">
                    <div class="w-16 h-16 bg-rose-500/20 text-rose-400 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    </div>
                    <h2 class="text-xl font-bold text-white mb-2">لینک معتبر یافت نشد</h2>
                    <p class="text-gray-300 text-sm mb-6 leading-relaxed"><?php echo htmlspecialchars($error_message); ?></p>
                    <a href="interview-test.php" class="inline-block px-6 py-3 bg-teal-600 hover:bg-teal-500 text-white rounded-xl font-bold text-sm transition shadow-lg shadow-teal-600/30">
                        تلاش مجدد با شماره موبایل
                    </a>
                </div>

            <?php elseif (!$test_record): ?>
                <!-- Enter Mobile Screen if opened directly -->
                <div class="glass-panel rounded-2xl sm:rounded-3xl p-6 sm:p-10 shadow-2xl text-center">
                    <div class="w-16 h-16 sm:w-20 sm:h-20 bg-teal-500/10 text-teal-300 rounded-3xl flex items-center justify-center mx-auto mb-5 border border-teal-500/20 shadow-inner">
                        <span class="text-3xl sm:text-4xl">🧠</span>
                    </div>
                    <h1 class="text-xl sm:text-2xl font-black text-white mb-1.5">سامانه هوشمند استعدادیابی نرومایند</h1>
                    <p class="text-teal-400 text-xs font-bold mb-4">بنیاد نیکوکاری حکمت | NeroMind Assessment</p>
                    <p class="text-gray-300 text-xs sm:text-sm mb-6 leading-relaxed max-w-md mx-auto">
                        داوطلب گرامی، جهت ورود به آزمون و مصاحبه استعدادیابی، شماره موبایل یا کدملی خود را در کادر زیر وارد فرمایید:
                    </p>
                    <form method="POST" class="max-w-sm mx-auto space-y-4">
                        <div class="relative">
                            <input type="tel" inputmode="numeric" name="mobile" required placeholder="مثال: ۰۹۱۲۳۴۵۶۷۸۹"
                                   class="w-full text-center tracking-widest text-lg sm:text-xl font-mono py-3.5 sm:py-4 px-4 bg-gray-900/90 border border-teal-500/30 rounded-2xl text-white placeholder-gray-500 focus:outline-none focus:border-teal-400 focus:ring-2 focus:ring-teal-400 shadow-inner">
                        </div>
                        <button type="submit" name="search_mobile" value="1"
                                class="w-full py-3.5 sm:py-4 bg-gradient-to-r from-teal-500 to-emerald-600 hover:from-teal-400 hover:to-emerald-500 text-white font-black text-base rounded-2xl shadow-lg shadow-teal-500/25 active:scale-95 transition-all">
                            ورود و شروع ارزیابی
                        </button>
                    </form>
                    <div class="mt-6 pt-5 border-t border-teal-500/10 text-center space-y-3">
                        <p class="text-[11px] sm:text-xs text-gray-400">لینک اختصاصی آزمون برای داوطلبان مجاز پیامک شده است.</p>
                        <a href="tel:09926744850" class="inline-flex items-center justify-center gap-2 px-4 py-2.5 bg-gray-900/80 hover:bg-teal-950 border border-teal-500/30 rounded-xl text-teal-300 font-mono text-xs font-bold transition">
                            <span>📞</span>
                            <span>پشتیبانی فنی آزمون: ۰۹۹۲۶۷۴۴۸۵۰</span>
                        </a>
                    </div>
                </div>

            <?php elseif ($test_record['status'] === 'completed'): ?>
                <!-- Already Completed Screen -->
                <div class="glass-panel rounded-2xl sm:rounded-3xl p-6 sm:p-12 text-center shadow-2xl">
                    <div class="w-16 h-16 sm:w-20 sm:h-20 bg-emerald-500/20 text-emerald-400 rounded-3xl flex items-center justify-center mx-auto mb-5 border border-emerald-500/30 shadow-lg shadow-emerald-500/20">
                        <svg class="w-8 h-8 sm:w-10 sm:h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                    <h2 class="text-xl sm:text-2xl font-black text-white mb-2">آزمون شما با موفقیت ثبت شده است</h2>
                    <p class="text-gray-300 text-xs sm:text-sm mb-5 leading-relaxed max-w-md mx-auto">
                        داوطلب گرامی <strong class="text-teal-300"><?php echo htmlspecialchars($test_record['candidate_name']); ?></strong>؛
                        تمام پاسخ‌های شما با موفقیت در سرور بنیاد حکمت ثبت شده و کارنامه هوشمند شما صادر گردیده است.
                    </p>
                    <div class="inline-flex items-center gap-2 px-3.5 py-1.5 bg-teal-950/70 border border-teal-500/30 rounded-full text-xs text-teal-300 mb-6 font-mono">
                        <span>تاریخ تکمیل: <?php echo formatJalaliDateTime($test_record['completed_at']); ?></span>
                    </div>
                    <div>
                        <a href="interview-dossier.php?token=<?php echo urlencode($test_record['token']); ?>" class="inline-flex items-center justify-center gap-2 w-full sm:w-auto px-6 py-3.5 bg-gradient-to-r from-teal-500 to-emerald-600 hover:from-teal-400 hover:to-emerald-500 text-white rounded-xl font-bold text-sm shadow-lg shadow-teal-500/20 active:scale-95 transition">
                            <span>📋</span>
                            <span>مشاهده پرونده تحلیلی و رادار چارت</span>
                        </a>
                    </div>
                </div>

            <?php else: ?>
                <!-- Active Test Interface -->

                <!-- 1. Zen Welcome & Calming Onboarding Screen -->
                <div id="screen-intro" class="glass-panel rounded-2xl sm:rounded-3xl p-5 sm:p-8 md:p-10 shadow-2xl">
                    <div class="text-center mb-5 sm:mb-6">
                        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-teal-500/10 border border-teal-500/20 text-teal-300 text-xs font-bold mb-3">
                            <span><?php echo $test_def['icon'] ?? '🌟'; ?></span>
                            <span><?php echo htmlspecialchars($test_def['badge'] ?? 'ارزیابی آنلاین بنیاد حکمت'); ?></span>
                        </span>
                        <h1 class="text-xl sm:text-2xl md:text-3xl font-black text-white mb-1.5">
                            سلام <span class="text-teal-300"><?php echo htmlspecialchars($test_record['candidate_name']); ?></span> عزیز 👋
                        </h1>
                        <p class="text-gray-300 text-xs sm:text-sm font-medium"><?php echo htmlspecialchars($test_def['title']); ?></p>
                        <p class="text-gray-400 text-[11px] sm:text-xs mt-0.5"><?php echo htmlspecialchars($test_def['subtitle'] ?? ''); ?></p>
                    </div>

                    <?php if ($is_battery): ?>
                    <!-- Mobile Roadmap Stack (< sm) -->
                    <div class="sm:hidden space-y-2 mb-4">
                        <?php foreach ($stages as $idx => $stg): ?>
                        <div class="bg-gray-900/80 border border-teal-500/20 rounded-2xl p-2.5 flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <span class="w-8 h-8 rounded-xl bg-teal-500/15 border border-teal-500/30 text-teal-300 flex items-center justify-center text-base shrink-0">
                                    <?php echo $stg['icon']; ?>
                                </span>
                                <div class="text-right">
                                    <strong class="text-white text-xs font-bold block"><?php echo htmlspecialchars($stg['short_title']); ?></strong>
                                    <span class="text-[10px] text-gray-400 font-mono"><?php echo toFarsiDigits(count($stg['questions'])); ?> سوال • <?php echo toFarsiDigits($stg['time_limit']); ?> ثانیه</span>
                                </div>
                            </div>
                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full <?php echo $idx === 0 ? 'bg-teal-500/20 text-teal-300 border border-teal-500/40' : 'bg-gray-800 text-gray-400'; ?>">
                                <?php echo $idx === 0 ? 'مرحله ۱' : 'مرحله ' . toFarsiDigits($idx + 1); ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Desktop / Tablet Grid (sm:grid) -->
                    <div class="hidden sm:grid grid-cols-2 gap-3 mb-6">
                        <?php foreach ($stages as $stg): ?>
                        <div class="bg-gray-900/70 border border-teal-500/20 rounded-2xl p-3.5 text-right">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-lg"><?php echo $stg['icon']; ?></span>
                                <strong class="text-white text-xs font-bold"><?php echo htmlspecialchars($stg['short_title']); ?></strong>
                            </div>
                            <span class="text-[11px] text-teal-300 font-mono block">
                                <?php echo toFarsiDigits(count($stg['questions'])); ?> سوال • <?php echo toFarsiDigits($stg['time_limit']); ?> ثانیه هر سوال
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <div class="bg-teal-950/40 border border-teal-500/20 rounded-2xl p-4 sm:p-5 mb-5 text-xs sm:text-sm text-gray-200 leading-relaxed text-justify space-y-2.5">
                        <div class="flex items-start gap-2.5">
                            <span class="text-teal-400 text-base mt-0.5 shrink-0">🕊️</span>
                            <p><?php echo htmlspecialchars($test_def['intro_text'] ?? 'این پرسشنامه جهت کشف استعدادها و توانمندی‌های بالقوه شما آماده شده است.'); ?></p>
                        </div>
                        <div class="flex items-start gap-2.5">
                            <span class="text-teal-400 text-base mt-0.5 shrink-0">✨</span>
                            <p><strong>اینجا هیچ پاسخ درست یا غلطی وجود ندارد!</strong> با آرامش کامل و اولین احساسی که به نظرتان می‌رسد گزینه‌ها را لمس کنید.</p>
                        </div>
                        <div class="flex items-start gap-2.5">
                            <span class="text-teal-400 text-base mt-0.5 shrink-0">⏱️</span>
                            <p>پرسش‌ها به صورت <strong>یکی‌یکی و زمان‌دار</strong> نمایش داده می‌شوند تا با تمرکز کامل پیش بروید. <?php if ($is_battery): ?>بین مراحل فرصت استراحت و تنفس کوتاه دارید.<?php endif; ?></p>
                        </div>
                        <div class="flex items-start gap-2.5 p-2.5 rounded-xl bg-amber-500/10 border border-amber-500/20 text-amber-200 text-[11px] sm:text-xs leading-relaxed">
                            <span class="text-amber-400 text-sm mt-0.5 shrink-0">💡</span>
                            <div>
                                <strong>توصیه‌های کاربردی برای تجربه روان در موبایل:</strong>
                                <ul class="list-disc list-inside mt-1 space-y-0.5 text-gray-300">
                                    <li>پیشنهاد می‌شود <strong>فیلترشکن (VPN) خود را خاموش</strong> کنید.</li>
                                    <li>پاسخ‌ها به صورت خودکار در لحظه ذخیره می‌شوند و حتی در صورت قطعی اینترنت محفوظ هستند.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <?php
                    $saved_answers_data = !empty($test_record['answers_json']) ? json_decode($test_record['answers_json'], true) : [];
                    $total_saved_count = 0;
                    if (is_array($saved_answers_data)) {
                        foreach ($saved_answers_data as $s_answers) {
                            if (is_array($s_answers)) {
                                $total_saved_count += count($s_answers);
                            } else {
                                $total_saved_count++;
                            }
                        }
                    }
                    $is_resuming = ($total_saved_count > 0 && ($test_record['status'] ?? '') !== 'completed');
                    ?>

                    <?php if ($is_resuming): ?>
                    <!-- Resume Notice Banner -->
                    <div class="mb-5 p-3.5 sm:p-4 rounded-2xl bg-emerald-950/80 border border-emerald-500/40 text-right shadow-lg">
                        <div class="flex items-center gap-2 mb-1.5">
                            <span class="text-xl">💾</span>
                            <strong class="text-emerald-300 text-xs sm:text-sm">پاسخ‌های قبلی شما با موفقیت ذخیره است!</strong>
                        </div>
                        <p class="text-[11px] sm:text-xs text-gray-200 leading-relaxed mb-2">
                            داوطلب گرامی، شما پیش‌تر به <strong class="text-emerald-400 font-mono font-black"><?php echo toFarsiDigits($total_saved_count); ?> سوال</strong> پاسخ داده‌اید. سیستم دقیقاً از <strong>اولین سوال باقیمانده</strong> ادامه خواهد یافت.
                        </p>
                        <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-gray-900/80 border border-emerald-500/20 text-[11px] text-emerald-300 font-medium">
                            <span>✓ پیشرفت قبلی شما در پایگاه‌داده محفوظ است</span>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Fast Stat Pills -->
                    <div class="grid grid-cols-3 gap-2 sm:gap-3 mb-6 text-center">
                        <div class="bg-gray-900/60 border border-teal-500/10 p-2.5 rounded-2xl">
                            <span class="text-[10px] sm:text-xs text-gray-400 block mb-0.5">تعداد کل</span>
                            <strong class="text-teal-300 text-xs sm:text-base font-black"><?php echo toFarsiDigits($total_questions_all); ?> سوال</strong>
                        </div>
                        <div class="bg-gray-900/60 border border-teal-500/10 p-2.5 rounded-2xl">
                            <span class="text-[10px] sm:text-xs text-gray-400 block mb-0.5"><?php echo $is_battery ? 'ساختار' : 'زمان'; ?></span>
                            <strong class="text-teal-300 text-xs sm:text-base font-black"><?php echo $is_battery ? '۴ مرحله' : toFarsiDigits($stages[0]['time_limit']) . ' ثانیه'; ?></strong>
                        </div>
                        <div class="bg-gray-900/60 border border-teal-500/10 p-2.5 rounded-2xl">
                            <span class="text-[10px] sm:text-xs text-gray-400 block mb-0.5">تخمین کل</span>
                            <strong class="text-emerald-400 text-xs sm:text-base font-black"><?php echo $is_battery ? '۴۵ دقیقه' : '۱۵ دقیقه'; ?></strong>
                        </div>
                    </div>

                    <!-- Action Button Container -->
                    <div class="text-center">
                        <?php if ($is_resuming): ?>
                        <button id="btn-start-test" class="w-full py-4 px-6 bg-gradient-to-r from-emerald-500 to-teal-600 hover:from-emerald-400 hover:to-teal-500 text-white font-black text-base sm:text-lg rounded-2xl shadow-xl shadow-emerald-500/30 active:scale-95 transition-all flex items-center justify-center gap-2 cursor-pointer">
                            <span>ادامه آزمون از سوال <?php echo toFarsiDigits($total_saved_count + 1); ?></span>
                            <svg class="w-5 h-5 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                        </button>
                        <?php else: ?>
                        <p class="text-[11px] sm:text-xs text-gray-400 mb-3">یک نفس عمیق بکش و با آرامش دکمه زیر را لمس کن:</p>
                        <button id="btn-start-test" class="w-full py-4 px-6 bg-gradient-to-r from-teal-500 to-emerald-600 hover:from-teal-400 hover:to-emerald-500 text-white font-black text-base sm:text-lg rounded-2xl shadow-xl shadow-teal-500/30 active:scale-95 transition-all flex items-center justify-center gap-2 cursor-pointer">
                            <span>نفس عمیق کشیدم؛ آماده‌ام و شروع می‌کنم</span>
                            <svg class="w-5 h-5 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                        </button>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- 2. Question View (One Question at a Time, Mobile Optimized) -->
                <div id="screen-question" class="hidden glass-panel rounded-2xl sm:rounded-3xl p-4 sm:p-6 md:p-8 shadow-2xl relative overflow-hidden transition-all">
                    
                    <!-- Network Offline / Syncing Warning Banner (Dynamic Island style) -->
                    <div id="net-warning-banner" class="hidden mb-3 p-3 rounded-xl bg-amber-500/20 border border-amber-500/40 text-amber-200 text-xs flex items-center justify-between transition-all">
                        <div class="flex items-center gap-2">
                            <span class="animate-pulse text-base">⚠️</span>
                            <span>اتصال اینترنت ناپایدار است؛ پاسخ در گوشی ذخیره شد و تایمر موقتاً متوقف گردید.</span>
                        </div>
                        <span id="net-retry-status" class="text-[11px] font-mono text-amber-300 font-bold shrink-0 mr-2">در حال تلاش مجدد...</span>
                    </div>

                    <!-- Progress Bar & Question Counter & Timer Header -->
                    <div class="flex items-center justify-between mb-3 pb-3 border-b border-teal-500/10">
                        <div class="text-right">
                            <?php if ($total_stages > 1): ?>
                            <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-teal-500/20 text-teal-300 text-[10px] sm:text-[11px] font-bold mb-1">
                                <span>بخش <span id="lbl-current-stage-num">۱</span> از <?php echo toFarsiDigits($total_stages); ?>:</span>
                                <span id="lbl-current-stage-title">هوش‌های چندگانه</span>
                            </div>
                            <?php endif; ?>
                            <div class="text-xs sm:text-sm font-black text-white flex flex-wrap items-center gap-1 sm:gap-2">
                                <span>سوال کل: <span id="lbl-global-q" class="text-teal-300 font-mono text-sm sm:text-base font-black">۱</span> از <span class="text-gray-400 font-mono"><?php echo toFarsiDigits($total_questions_all); ?></span></span>
                                <span class="text-[11px] sm:text-xs text-gray-400 font-normal">(این بخش: <span id="lbl-current-q" class="font-mono text-teal-200">۱</span> از <span id="lbl-total-q" class="font-mono">۸۰</span>)</span>
                            </div>
                        </div>

                        <!-- Timer: Dual Display (Desktop SVG Ring + Mobile Badge) -->
                        <div class="flex items-center gap-2 sm:gap-3">
                            <!-- Mobile Compact Pill Timer -->
                            <div id="mobile-timer-pill" class="inline-flex sm:hidden items-center gap-1.5 px-3 py-1 rounded-full bg-teal-950/80 border border-teal-500/30 text-teal-300 font-mono text-xs font-black shadow-sm">
                                <span>⏱️</span>
                                <span id="timer-text-mobile">۲۰</span>s
                            </div>

                            <!-- Desktop Circular Countdown Timer -->
                            <div class="hidden sm:flex relative w-12 h-12 items-center justify-center shrink-0">
                                <svg class="w-12 h-12 transform -rotate-90">
                                    <circle cx="24" cy="24" r="20" stroke="currentColor" stroke-width="3" class="text-teal-950" fill="transparent" />
                                    <circle id="timer-ring" cx="24" cy="24" r="20" stroke="currentColor" stroke-width="3"
                                            class="text-teal-400 timer-circle" fill="transparent"
                                            stroke-dasharray="125.6" stroke-dashoffset="0" stroke-linecap="round" />
                                </svg>
                                <span id="timer-text" class="absolute font-mono font-black text-sm text-white">۲۰</span>
                            </div>
                            <span class="text-xs text-gray-400 hidden md:inline">ثانیه</span>
                        </div>
                    </div>

                    <!-- Progress Line -->
                    <div class="w-full bg-gray-900/80 h-1.5 rounded-full mb-3 sm:mb-4 overflow-hidden">
                        <div id="progress-bar" class="bg-gradient-to-r from-teal-400 via-emerald-400 to-teal-300 h-full transition-all duration-300" style="width: 2%;"></div>
                    </div>

                    <!-- Question Dimension Badge -->
                    <div class="mb-2">
                        <span id="q-dimension" class="inline-block px-2.5 py-0.5 rounded-md bg-teal-500/15 border border-teal-400/20 text-teal-300 text-[11px] font-bold">
                            ابعاد ارزیابی
                        </span>
                    </div>

                    <!-- Question Text -->
                    <div class="min-h-[50px] sm:min-h-[70px] flex items-center mb-4 sm:mb-6">
                        <h2 id="q-text" class="text-base sm:text-lg md:text-xl font-bold text-white leading-relaxed select-none text-right">
                            متن سوال در اینجا لود می‌شود...
                        </h2>
                    </div>

                    <!-- Touch Options Cards Container -->
                    <div id="options-container" class="space-y-2.5 sm:space-y-3 mb-4 sm:mb-6">
                        <!-- Rendered dynamically by JavaScript -->
                    </div>

                    <!-- Footer Note -->
                    <div class="text-center text-[11px] sm:text-xs text-gray-400">
                        <span>با انتخاب هر گزینه، پاسخ فوراً ذخیره شده و سوال بعدی نمایش می‌یابد.</span>
                    </div>
                </div>

                <!-- 3. Intermission Rest Screen (Between Stages, Zen Breathing Room) -->
                <div id="screen-intermission" class="hidden glass-panel rounded-2xl sm:rounded-3xl p-6 sm:p-10 shadow-2xl text-center">
                    <div class="w-20 h-20 sm:w-24 sm:h-24 rounded-full bg-teal-500/10 border-2 border-teal-400/30 flex items-center justify-center mx-auto mb-4 zen-breath relative">
                        <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-full bg-teal-500/20 flex items-center justify-center text-3xl">
                            🌿
                        </div>
                    </div>
                    <span class="inline-block px-3.5 py-1 rounded-full bg-teal-500/10 border border-teal-500/20 text-teal-300 text-xs font-bold mb-2">
                        اتاق تنفس و آرامش ذهن
                    </span>
                    <h2 id="intermission-title" class="text-xl sm:text-2xl font-black text-white mb-2">بسیار عالی! این بخش با موفقیت ثبت شد ✨</h2>
                    <p id="intermission-msg" class="text-gray-200 text-xs sm:text-sm mb-5 leading-relaxed max-w-md mx-auto text-justify sm:text-center">
                        یک جرعه آب بنوشید، چند ثانیه چشمانتان را ببندید و یک نفس عمیق بکشید.
                    </p>

                    <!-- Next Stage Preview Box -->
                    <div class="bg-gray-900/80 border border-teal-500/20 rounded-2xl p-4 sm:p-5 max-w-md mx-auto mb-6 text-right">
                        <span class="text-[10px] sm:text-[11px] text-gray-400 block mb-1">مرحله بعدی در انتظار شماست:</span>
                        <h3 id="intermission-next-title" class="text-sm sm:text-base font-black text-teal-300 mb-0.5">بخش ۲: هوش هیجانی</h3>
                        <p id="intermission-next-badge" class="text-xs text-gray-300 mb-1.5">مدیریت عواطف و خودآگاهی</p>
                        <span id="intermission-next-info" class="text-xs text-emerald-400 font-mono font-bold block">۳۳ سوال • ۲۵ ثانیه برای هر سوال</span>
                    </div>

                    <button id="btn-next-stage" class="w-full sm:w-auto px-10 py-3.5 sm:py-4 bg-gradient-to-r from-teal-500 to-emerald-600 hover:from-teal-400 hover:to-emerald-500 text-white font-black text-base rounded-2xl shadow-xl shadow-teal-500/30 active:scale-95 transition flex items-center justify-center gap-2 mx-auto cursor-pointer">
                        <span>آماده‌ام؛ شروع <span id="lbl-next-stage-name">مرحله بعد</span></span>
                        <svg class="w-5 h-5 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </button>
                </div>

                <!-- 4. Final Completion Screen -->
                <div id="screen-completed" class="hidden glass-panel rounded-2xl sm:rounded-3xl p-6 sm:p-12 text-center shadow-2xl">
                    <div class="w-16 h-16 sm:w-20 sm:h-20 bg-teal-500/20 text-teal-300 rounded-3xl flex items-center justify-center mx-auto mb-5 border border-teal-500/30">
                        <svg class="w-8 h-8 sm:w-10 sm:h-10 animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <h2 class="text-xl sm:text-2xl font-black text-white mb-2">تبریک! تمام مراحل آزمون به پایان رسید 🎉</h2>
                    <p class="text-gray-200 text-xs sm:text-sm mb-5 leading-relaxed max-w-md mx-auto">
                        داوطلب گرامی <strong class="text-teal-300"><?php echo htmlspecialchars($test_record['candidate_name']); ?></strong>؛
                        از صبر و تمرکز شما صمیمانه سپاسگزاریم. پاسخ‌های شما تحلیل شده و کارنامه هوشمند شما صادر گردید.
                    </p>
                    <div class="mt-4">
                        <a id="btn-view-dossier" href="interview-dossier.php?token=<?php echo urlencode($test_record['token']); ?>" class="inline-flex items-center justify-center gap-2 w-full sm:w-auto px-8 py-3.5 bg-gradient-to-r from-teal-500 to-emerald-600 hover:from-teal-400 hover:to-emerald-500 text-white rounded-2xl font-black text-sm shadow-xl shadow-teal-500/30 active:scale-95 transition">
                            <span>📊</span>
                            <span>مشاهده پرونده تحلیلی و رادار چارت</span>
                        </a>
                    </div>
                </div>

            <?php endif; ?>

        </div>
    </main>

    <!-- Footer Note -->
    <footer class="w-full border-t border-teal-500/10 py-2.5 px-4 text-center text-[10px] sm:text-xs text-gray-500">
        <span>سامانه ارزیابی هوش و روانسنجی بنیاد نیکوکاری حکمت (NeroMind) • تمامی حقوق محفوظ است</span>
    </footer>

    <?php if ($test_record && $test_record['status'] !== 'completed'): ?>
    <!-- Application Logic & State Machine -->
    <script>
        const testToken = <?php echo json_encode($test_record['token']); ?>;
        const testType = <?php echo json_encode($active_test_type); ?>;
        const isBattery = <?php echo $is_battery ? 'true' : 'false'; ?>;
        const stages = <?php echo json_encode($stages, JSON_UNESCAPED_UNICODE); ?>;
        const totalStages = stages.length;

        let savedAnswers = <?php echo $test_record['answers_json'] ?: '{}'; ?>;
        if (typeof savedAnswers !== 'object' || savedAnswers === null) {
            savedAnswers = {};
        }

        // پشتیبان‌گیری دو لایه در حافظه مرورگر داوطلب (مقاوم در برابر قطعی اینترنت موبایل)
        const storageKey = 'hekmat_test_' + testToken;
        try {
            const localSavedStr = localStorage.getItem(storageKey);
            if (localSavedStr) {
                const localSaved = JSON.parse(localSavedStr);
                if (isBattery && typeof localSaved === 'object') {
                    for (const [stgId, stgMap] of Object.entries(localSaved)) {
                        if (!savedAnswers[stgId]) savedAnswers[stgId] = {};
                        if (typeof stgMap === 'object') {
                            for (const [qid, val] of Object.entries(stgMap)) {
                                if (savedAnswers[stgId][qid] === undefined) {
                                    savedAnswers[stgId][qid] = val;
                                }
                            }
                        }
                    }
                } else if (typeof localSaved === 'object') {
                    for (const [qid, val] of Object.entries(localSaved)) {
                        if (savedAnswers[qid] === undefined) {
                            savedAnswers[qid] = val;
                        }
                    }
                }
            }
        } catch(e) {}

        // همگام‌سازی خودکار حافظه محلی با پایگاه‌داده سرور در بدو ورود
        try {
            if (typeof savedAnswers === 'object' && Object.keys(savedAnswers).length > 0) {
                fetch('interview-test.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        ajax_action: 'sync_answers',
                        token: testToken,
                        answers: JSON.stringify(savedAnswers)
                    })
                }).catch(() => {});
            }
        } catch(e) {}

        let currentStageIdx = 0;
        let currentQuestionIdx = 0;
        let timerInterval = null;
        let timeLeft = 20;
        let currentQuestionTimeLimit = 20;
        const circumference = 2 * Math.PI * 20; // 125.66

        // Persian digits conversion helper
        function toFarsiNum(n) {
            const f = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
            return String(n).replace(/[0-9]/g, w => f[+w]);
        }

        const screenIntro = document.getElementById('screen-intro');
        const screenQuestion = document.getElementById('screen-question');
        const screenIntermission = document.getElementById('screen-intermission');
        const screenCompleted = document.getElementById('screen-completed');

        const lblCurrentStageNum = document.getElementById('lbl-current-stage-num');
        const lblCurrentStageTitle = document.getElementById('lbl-current-stage-title');
        const lblCurrentQ = document.getElementById('lbl-current-q');
        const lblTotalQ = document.getElementById('lbl-total-q');
        const progressBar = document.getElementById('progress-bar');
        const qDimension = document.getElementById('q-dimension');
        const qText = document.getElementById('q-text');
        const timerText = document.getElementById('timer-text');
        const timerTextMobile = document.getElementById('timer-text-mobile');
        const mobileTimerPill = document.getElementById('mobile-timer-pill');
        const timerRing = document.getElementById('timer-ring');
        const optionsContainer = document.getElementById('options-container');

        // Resume finder
        function findResumePosition() {
            for (let s = 0; s < stages.length; s++) {
                const stage = stages[s];
                const stageAnswers = isBattery ? (savedAnswers[stage.id] || {}) : savedAnswers;
                const qs = stage.questions;
                for (let q = 0; q < qs.length; q++) {
                    const qid = String(qs[q].id);
                    if (stageAnswers[qid] === undefined || stageAnswers[qid] === null) {
                        return { stageIdx: s, questionIdx: q };
                    }
                }
            }
            return { stageIdx: 0, questionIdx: 0 };
        }

        // Start Test Button
        document.getElementById('btn-start-test').addEventListener('click', function() {
            fetch('interview-test.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    ajax_action: 'start_test',
                    token: testToken
                })
            });

            const resumePos = findResumePosition();
            currentStageIdx = resumePos.stageIdx;
            currentQuestionIdx = resumePos.questionIdx;

            screenIntro.classList.add('hidden');
            screenQuestion.classList.remove('hidden');
            window.scrollTo({ top: 0, behavior: 'instant' });
            loadQuestion(currentQuestionIdx);
        });

        // Next Stage Button from Intermission Screen
        const btnNextStage = document.getElementById('btn-next-stage');
        if (btnNextStage) {
            btnNextStage.addEventListener('click', function() {
                screenIntermission.classList.add('hidden');
                screenQuestion.classList.remove('hidden');
                window.scrollTo({ top: 0, behavior: 'instant' });
                currentStageIdx++;
                currentQuestionIdx = 0;
                loadQuestion(0);
            });
        }

        // Load specific question in active stage
        function loadQuestion(index) {
            const currentStage = stages[currentStageIdx];
            const questions = currentStage.questions;

            if (index >= questions.length) {
                // End of this stage!
                if (currentStageIdx < totalStages - 1) {
                    showIntermission(currentStageIdx);
                } else {
                    finishTest();
                }
                return;
            }

            currentQuestionIdx = index;
            const q = questions[index];

            // محاسبه شماره سوال در کل آزمون
            let prevStagesQuestionsCount = 0;
            for (let s = 0; s < currentStageIdx; s++) {
                prevStagesQuestionsCount += stages[s].questions.length;
            }
            const globalQNum = prevStagesQuestionsCount + index + 1;
            const lblGlobalQ = document.getElementById('lbl-global-q');
            if (lblGlobalQ) lblGlobalQ.textContent = toFarsiNum(globalQNum);

            if (lblCurrentStageNum) lblCurrentStageNum.textContent = toFarsiNum(currentStageIdx + 1);
            if (lblCurrentStageTitle) lblCurrentStageTitle.textContent = currentStage.short_title || currentStage.title;
            if (lblCurrentQ) lblCurrentQ.textContent = toFarsiNum(index + 1);
            if (lblTotalQ) lblTotalQ.textContent = toFarsiNum(questions.length);

            if (progressBar) progressBar.style.width = ((index + 1) / questions.length * 100) + '%';
            if (qDimension) qDimension.textContent = q.dimension_fa || currentStage.badge || 'ارزیابی';
            if (qText) qText.textContent = q.text;

            // Options: per-question or stage default
            const opts = (q.options && q.options.length > 0) ? q.options : currentStage.default_options;
            optionsContainer.innerHTML = '';

            opts.forEach((opt, optIdx) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'option-card w-full text-right py-3 px-3.5 sm:py-3.5 sm:px-4 rounded-2xl bg-gray-900/70 border border-teal-500/20 hover:border-teal-400 active:border-teal-300 active:bg-teal-500/25 flex items-center justify-between group cursor-pointer transition-all select-none';
                btn.setAttribute('data-val', opt.value);

                const subHtml = opt.sub ? `<span class="text-[11px] sm:text-xs text-gray-400 block mt-0.5">${opt.sub}</span>` : '';

                btn.innerHTML = `
                    <div class="flex items-center gap-3">
                        <span class="w-7 h-7 sm:w-8 sm:h-8 rounded-xl bg-teal-500/15 text-teal-300 border border-teal-500/30 flex items-center justify-center text-xs sm:text-sm font-black shrink-0 group-hover:bg-teal-500 group-hover:text-white transition">
                            ${toFarsiNum(optIdx + 1)}
                        </span>
                        <div class="text-right">
                            <strong class="text-white text-sm sm:text-base font-bold block group-hover:text-teal-200 transition leading-snug">${opt.label}</strong>
                            ${subHtml}
                        </div>
                    </div>
                    <div class="w-5 h-5 rounded-full border border-teal-500/40 flex items-center justify-center shrink-0 group-hover:border-teal-400">
                        <span class="w-2.5 h-2.5 rounded-full bg-teal-400 opacity-0 group-hover:opacity-100 group-active:opacity-100 transition"></span>
                    </div>
                `;

                btn.addEventListener('click', function() {
                    if ('vibrate' in navigator) {
                        try { navigator.vibrate(15); } catch(e) {}
                    }
                    btn.classList.add('bg-teal-600/40', 'border-teal-400', 'ring-2', 'ring-teal-400/50');
                    selectAnswer(currentStage.id, q.id, opt.value);
                });

                optionsContainer.appendChild(btn);
            });

            currentQuestionTimeLimit = q.time_limit || currentStage.time_limit || 20;
            resetTimer(currentQuestionTimeLimit);
        }

        // Network Resilience State
        let isNetworkPaused = false;
        const netWarningBanner = document.getElementById('net-warning-banner');
        const netRetryStatus = document.getElementById('net-retry-status');

        function triggerNetworkPause(stageId, questionId, val, nextQNum) {
            if (isNetworkPaused) return;
            isNetworkPaused = true;
            clearInterval(timerInterval);
            if (netWarningBanner) netWarningBanner.classList.remove('hidden');
            if (netRetryStatus) netRetryStatus.textContent = 'در حال تلاش مجدد...';

            let attempts = 0;
            const retryInterval = setInterval(() => {
                attempts++;
                if (netRetryStatus) netRetryStatus.textContent = `تلاش مجدد (${toFarsiNum(attempts)})...`;

                fetch('interview-test.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: new URLSearchParams({
                        ajax_action: 'save_answer',
                        token: testToken,
                        stage: isBattery ? stageId : '',
                        question_id: questionId,
                        answer: val,
                        current_question: nextQNum
                    })
                }).then(r => r.json()).then(res => {
                    if (res && res.success) {
                        clearInterval(retryInterval);
                        isNetworkPaused = false;
                        if (netWarningBanner) {
                            netWarningBanner.className = 'mb-3 p-3 rounded-xl bg-emerald-500/20 border border-emerald-500/40 text-emerald-200 text-xs flex items-center justify-between transition-all';
                            netWarningBanner.innerHTML = '<span>✓ اتصال اینترنت برقرار شد و پاسخ شما با موفقیت ثبت شد.</span>';
                            setTimeout(() => {
                                netWarningBanner.classList.add('hidden');
                                netWarningBanner.className = 'hidden mb-3 p-3 rounded-xl bg-amber-500/20 border border-amber-500/40 text-amber-200 text-xs flex items-center justify-between transition-all';
                            }, 2500);
                        }
                        resetTimer(timeLeft > 0 ? timeLeft : currentQuestionTimeLimit);
                        loadQuestion(currentQuestionIdx + 1);
                    }
                }).catch(() => {
                    // Retry continues
                });
            }, 3000);
        }

        // Timer reset and handling
        function resetTimer(timeLimit) {
            clearInterval(timerInterval);
            timeLeft = timeLimit;
            updateTimerDisplay();

            timerInterval = setInterval(() => {
                if (isNetworkPaused) return;
                timeLeft--;
                updateTimerDisplay();

                if (timeLeft <= 0) {
                    clearInterval(timerInterval);
                    autoAdvanceOnTimeout();
                }
            }, 1000);
        }

        function updateTimerDisplay() {
            if (timerText) timerText.textContent = toFarsiNum(timeLeft);
            if (timerTextMobile) timerTextMobile.textContent = toFarsiNum(timeLeft);
            
            const fraction = timeLeft / currentQuestionTimeLimit;
            const offset = circumference * (1 - fraction);
            if (timerRing) timerRing.style.strokeDashoffset = offset;

            if (timeLeft <= 5) {
                if (timerRing) {
                    timerRing.classList.remove('text-teal-400', 'text-amber-400');
                    timerRing.classList.add('text-rose-400');
                }
                if (mobileTimerPill) {
                    mobileTimerPill.className = 'inline-flex sm:hidden items-center gap-1.5 px-3 py-1 rounded-full bg-rose-950/80 border border-rose-500/40 text-rose-300 font-mono text-xs font-black shadow-sm animate-pulse';
                }
            } else if (timeLeft <= 10) {
                if (timerRing) {
                    timerRing.classList.remove('text-teal-400', 'text-rose-400');
                    timerRing.classList.add('text-amber-400');
                }
                if (mobileTimerPill) {
                    mobileTimerPill.className = 'inline-flex sm:hidden items-center gap-1.5 px-3 py-1 rounded-full bg-amber-950/80 border border-amber-500/40 text-amber-300 font-mono text-xs font-black shadow-sm';
                }
            } else {
                if (timerRing) {
                    timerRing.classList.remove('text-amber-400', 'text-rose-400');
                    timerRing.classList.add('text-teal-400');
                }
                if (mobileTimerPill) {
                    mobileTimerPill.className = 'inline-flex sm:hidden items-center gap-1.5 px-3 py-1 rounded-full bg-teal-950/80 border border-teal-500/30 text-teal-300 font-mono text-xs font-bold shadow-sm';
                }
            }
        }

        // Action when an option is tapped
        function selectAnswer(stageId, questionId, val) {
            clearInterval(timerInterval);

            if (isBattery) {
                if (!savedAnswers[stageId]) savedAnswers[stageId] = {};
                savedAnswers[stageId][String(questionId)] = val;
            } else {
                savedAnswers[String(questionId)] = val;
            }

            // ذخیره بلادرنگ در حافظه محلی مرورگر جهت مقاومت کامل در برابر قطعی اینترنت
            try {
                localStorage.setItem(storageKey, JSON.stringify(savedAnswers));
            } catch(e) {}

            // Instant scroll reset so the next question is positioned perfectly at top on mobile
            window.scrollTo({ top: 0, behavior: 'instant' });
            const screenQ = document.getElementById('screen-question');
            if (screenQ) screenQ.scrollTop = 0;

            // AJAX auto-save
            fetch('interview-test.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    ajax_action: 'save_answer',
                    token: testToken,
                    stage: isBattery ? stageId : '',
                    question_id: questionId,
                    answer: val,
                    current_question: currentQuestionIdx + 1
                })
            }).then(r => r.json()).catch(err => {
                console.warn('Network issue during auto-save, answer preserved locally:', err);
                triggerNetworkPause(stageId, questionId, val, currentQuestionIdx + 1);
            });

            // Smooth advance only if network is healthy
            setTimeout(() => {
                if (!isNetworkPaused) {
                    loadQuestion(currentQuestionIdx + 1);
                }
            }, 180);
        }

        function autoAdvanceOnTimeout() {
            if (isNetworkPaused) return;
            const currentStage = stages[currentStageIdx];
            const q = currentStage.questions[currentQuestionIdx];
            const stageAnswers = isBattery ? (savedAnswers[currentStage.id] || {}) : savedAnswers;

            if (stageAnswers[String(q.id)] === undefined) {
                selectAnswer(currentStage.id, q.id, 0);
            } else {
                loadQuestion(currentQuestionIdx + 1);
            }
        }

        // Show Intermission screen between stages
        function showIntermission(completedStageIdx) {
            clearInterval(timerInterval);
            screenQuestion.classList.add('hidden');

            const completedStage = stages[completedStageIdx];
            const nextStage = stages[completedStageIdx + 1];

            document.getElementById('intermission-title').textContent = `${completedStage.title} با موفقیت پایان یافت ✨`;
            document.getElementById('intermission-msg').textContent = completedStage.intermission_msg;
            document.getElementById('intermission-next-title').textContent = nextStage.title;
            document.getElementById('intermission-next-badge').textContent = nextStage.badge;
            document.getElementById('intermission-next-info').textContent = `${toFarsiNum(nextStage.questions.length)} سوال • ${toFarsiNum(nextStage.time_limit)} ثانیه برای هر سوال`;
            document.getElementById('lbl-next-stage-name').textContent = nextStage.short_title;

            screenIntermission.classList.remove('hidden');
            window.scrollTo({ top: 0, behavior: 'instant' });
        }

        // Finish Test & Submit
        function finishTest() {
            clearInterval(timerInterval);
            screenQuestion.classList.add('hidden');
            if (screenIntermission) screenIntermission.classList.add('hidden');
            screenCompleted.classList.remove('hidden');
            window.scrollTo({ top: 0, behavior: 'instant' });

            try {
                localStorage.removeItem(storageKey);
            } catch(e) {}

            fetch('interview-test.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({
                    ajax_action: 'submit_test',
                    token: testToken,
                    answers: JSON.stringify(savedAnswers)
                })
            }).then(r => r.json()).then(data => {
                if (data.success) {
                    console.log('Test submitted successfully', data.scores);
                    const btnDossier = document.getElementById('btn-view-dossier');
                    if (btnDossier) {
                        btnDossier.href = 'interview-dossier.php?token=' + encodeURIComponent(testToken);
                    }
                }
            });
        }

        // Anti-cheat protections
        document.addEventListener('contextmenu', e => e.preventDefault());
        document.addEventListener('keydown', e => {
            if (e.key === 'PrintScreen' || e.keyCode === 44 ||
                (e.ctrlKey && (e.key === 'u' || e.key === 's' || e.key === 'p' || e.key === 'c')) ||
                (e.metaKey && (e.key === 's' || e.key === 'p' || e.key === 'c')) ||
                e.key === 'F12') {
                e.preventDefault();
                return false;
            }
        });
    </script>
    <?php endif; ?>

</body>
</html>
