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
            $stmt = $pdo->prepare("SELECT * FROM interview_tests WHERE mobile LIKE ? ORDER BY id DESC LIMIT 1");
            $stmt->execute(['%' . substr($mobile_clean, -10)]);
            $found = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($found) {
                header("Location: interview-test.php?token=" . urlencode($found['token']));
                exit;
            } else {
                $error_message = 'هیچ آزمون فعالی برای این شماره همراه ثبت نشده است.';
            }
        }
    }
}

// اگر درخواست نسخه آزمایشی (Demo) باشد
$is_demo = isset($_GET['demo']);
$requested_test = trim($_GET['test'] ?? 'comprehensive_battery');

if (!$test_record && $is_demo) {
    $demo_token = 'demo_' . substr(md5(uniqid()), 0, 8);
    $ins = $pdo->prepare("INSERT INTO interview_tests (token, candidate_name, mobile, test_type, status) VALUES (?, 'داوطلب گرامی بنیاد حکمت', '09120000000', ?, 'pending')");
    $ins->execute([$demo_token, $requested_test]);
    header("Location: interview-test.php?token=" . $demo_token);
    exit;
}

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
    <style>
        /* Anti-cheat and secure view styles */
        body {
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }
        .zen-bg {
            background: radial-gradient(circle at 50% 10%, #062e2c 0%, #03171d 60%, #010b0e 100%);
        }
        .glass-panel {
            background: rgba(8, 38, 43, 0.85);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(45, 212, 191, 0.25);
        }
        .option-card {
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .option-card:hover {
            transform: translateY(-2px);
            border-color: #2dd4bf;
            background: rgba(20, 184, 166, 0.2);
        }
        .option-card:active {
            transform: scale(0.98);
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
        .fade-in {
            animation: fadeIn 0.3s ease-out forwards;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body class="zen-bg text-gray-100 font-sans min-h-screen flex flex-col justify-between selection:bg-teal-500 selection:text-white" oncontextmenu="return false;">

    <!-- Top Minimal Header -->
    <header class="w-full border-b border-teal-500/10 py-3 px-4 md:px-8 flex items-center justify-between z-20">
        <div class="flex items-center gap-3">
            <img src="/logo.png" alt="بنیاد نیکوکاری حکمت" class="h-9 w-auto">
            <div>
                <span class="text-sm font-black text-teal-300 block">بنیاد نیکوکاری حکمت</span>
                <span class="text-xs text-gray-400">سامانه آزمون‌های آنلاین و مصاحبه استعدادیابی</span>
            </div>
        </div>
        <?php if ($test_record): ?>
        <div class="flex items-center gap-2 bg-teal-950/60 border border-teal-500/30 px-3 py-1.5 rounded-full text-xs">
            <span class="w-2 h-2 rounded-full bg-teal-400 animate-ping"></span>
            <span class="text-gray-300">داوطلب:</span>
            <strong class="text-teal-200"><?php echo htmlspecialchars($test_record['candidate_name']); ?></strong>
        </div>
        <?php endif; ?>
    </header>

    <!-- Main Container -->
    <main class="flex-1 flex items-center justify-center p-4 md:p-6 relative">
        <div class="w-full max-w-2xl relative z-10">

            <?php if (!empty($error_message)): ?>
                <!-- Error Screen -->
                <div class="glass-panel rounded-3xl p-8 text-center shadow-2xl border-rose-500/30">
                    <div class="w-16 h-16 bg-rose-500/20 text-rose-400 rounded-2xl flex items-center justify-center mx-auto mb-4">
                        <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    </div>
                    <h2 class="text-xl font-bold text-white mb-2">لینک معتبر یافت نشد</h2>
                    <p class="text-gray-300 text-sm mb-6 leading-relaxed"><?php echo htmlspecialchars($error_message); ?></p>
                    <a href="interview-test.php" class="inline-block px-6 py-2.5 bg-teal-600 hover:bg-teal-500 text-white rounded-xl font-bold text-sm transition">
                        تلاش مجدد با شماره موبایل
                    </a>
                </div>

            <?php elseif (!$test_record): ?>
                <!-- Enter Mobile Screen if opened directly -->
                <div class="glass-panel rounded-3xl p-8 md:p-10 shadow-2xl text-center">
                    <div class="w-20 h-20 bg-teal-500/10 text-teal-300 rounded-3xl flex items-center justify-center mx-auto mb-6 border border-teal-500/20">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                    </div>
                    <h1 class="text-2xl font-black text-white mb-3">ورود به سامانه آزمون آنلاین</h1>
                    <p class="text-gray-300 text-sm mb-8 leading-relaxed max-w-md mx-auto">
                        اگر لینک اختصاصی از طرف بنیاد حکمت برای شما پیامک شده است، شماره همراه خود را وارد کنید تا آزمون بارگذاری شود.
                    </p>
                    <form method="POST" class="max-w-sm mx-auto space-y-4">
                        <input type="text" name="mobile" required placeholder="مثال: ۰۹۱۲۳۴۵۶۷۸۹"
                               class="w-full text-center tracking-widest text-lg font-mono py-3.5 px-4 bg-gray-900/80 border border-teal-500/30 rounded-2xl text-white placeholder-gray-500 focus:outline-none focus:border-teal-400 focus:ring-1 focus:ring-teal-400">
                        <button type="submit" name="search_mobile" value="1"
                                class="w-full py-3.5 bg-gradient-to-r from-teal-500 to-emerald-600 hover:from-teal-400 hover:to-emerald-500 text-white font-bold rounded-2xl shadow-lg shadow-teal-500/20 transition-all">
                            بررسی و شروع آزمون
                        </button>
                    </form>
                    <div class="mt-8 pt-6 border-t border-teal-500/10 flex flex-wrap justify-center gap-3 text-xs text-gray-400">
                        <span class="block w-full text-gray-500 font-bold">ورود به پیش‌نمایش آزمایشی آزمون‌ها:</span>
                        <a href="interview-test.php?demo=1&test=comprehensive_battery" class="text-emerald-400 hover:underline font-bold">🌟 پکیج جامع ۴ آزمون (توصیه بنیاد)</a>
                        <span>•</span>
                        <a href="interview-test.php?demo=1&test=gardner_mit" class="text-teal-400 hover:underline">🧠 هوش‌های گاردنر</a>
                        <span>•</span>
                        <a href="interview-test.php?demo=1&test=emotional_seiq" class="text-teal-400 hover:underline">❤️ هوش هیجانی</a>
                        <span>•</span>
                        <a href="interview-test.php?demo=1&test=hermans_amq" class="text-teal-400 hover:underline">🚀 انگیزه پیشرفت</a>
                        <span>•</span>
                        <a href="interview-test.php?demo=1&test=scl90_mental" class="text-teal-400 hover:underline">🛡️ سلامت روان</a>
                    </div>
                </div>

            <?php elseif ($test_record['status'] === 'completed'): ?>
                <!-- Already Completed Screen -->
                <div class="glass-panel rounded-3xl p-8 md:p-12 text-center shadow-2xl">
                    <div class="w-20 h-20 bg-emerald-500/20 text-emerald-400 rounded-3xl flex items-center justify-center mx-auto mb-6 border border-emerald-500/30">
                        <svg class="w-10 h-10" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                    <h2 class="text-2xl font-black text-white mb-3">آزمون شما با موفقیت ثبت شده است</h2>
                    <p class="text-gray-300 text-sm mb-6 leading-relaxed max-w-md mx-auto">
                        داوطلب گرامی <strong class="text-teal-300"><?php echo htmlspecialchars($test_record['candidate_name']); ?></strong>؛
                        پاسخ‌های شما با موفقیت در سامانه ذخیره شده و پرونده تحلیلی شما آماده و در اختیار تیم ارزیابی بنیاد حکمت قرار گرفته است.
                    </p>
                    <div class="inline-flex items-center gap-2 px-4 py-2 bg-teal-950/70 border border-teal-500/30 rounded-full text-xs text-teal-300 mb-6">
                        <span>تاریخ تکمیل: <?php echo formatJalaliDateTime($test_record['completed_at']); ?></span>
                    </div>
                    <div>
                        <a href="interview-dossier.php?token=<?php echo urlencode($test_record['token']); ?>" class="inline-flex items-center gap-2 px-6 py-3 bg-gradient-to-r from-teal-500 to-emerald-600 hover:from-teal-400 hover:to-emerald-500 text-white rounded-xl font-bold text-sm shadow-lg shadow-teal-500/20 transition">
                            <span>📋</span>
                            <span>مشاهده پرونده تحلیلی و رادار چارت</span>
                        </a>
                    </div>
                </div>

            <?php else: ?>
                <!-- Active Test Interface -->

                <!-- 1. Zen Welcome & Calming Onboarding Screen -->
                <div id="screen-intro" class="glass-panel rounded-3xl p-6 md:p-10 shadow-2xl">
                    <div class="text-center mb-6">
                        <span class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-teal-500/10 border border-teal-500/20 text-teal-300 text-xs font-bold mb-4">
                            <span><?php echo $test_def['icon'] ?? '🌟'; ?></span>
                            <span><?php echo htmlspecialchars($test_def['badge'] ?? 'ارزیابی آنلاین بنیاد حکمت'); ?></span>
                        </span>
                        <h1 class="text-2xl md:text-3xl font-black text-white mb-2">
                            سلام <span class="text-teal-300"><?php echo htmlspecialchars($test_record['candidate_name']); ?></span> عزیز
                        </h1>
                        <p class="text-gray-300 text-sm font-medium"><?php echo htmlspecialchars($test_def['title']); ?></p>
                        <p class="text-gray-400 text-xs mt-1"><?php echo htmlspecialchars($test_def['subtitle'] ?? ''); ?></p>
                    </div>

                    <?php if ($is_battery): ?>
                    <!-- Stages Overview Cards for Battery -->
                    <div class="grid grid-cols-2 gap-3 mb-6">
                        <?php foreach ($stages as $stg): ?>
                        <div class="bg-gray-900/70 border border-teal-500/20 rounded-2xl p-3 text-right">
                            <div class="flex items-center gap-2 mb-1">
                                <span class="text-lg"><?php echo $stg['icon']; ?></span>
                                <strong class="text-white text-xs font-bold"><?php echo htmlspecialchars($stg['short_title']); ?></strong>
                            </div>
                            <span class="text-[11px] text-teal-300 font-mono block">
                                <?php echo toFarsiDigits(count($stg['questions'])); ?> سوال • <?php echo toFarsiDigits($stg['time_limit']); ?> ثانیه
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <div class="bg-teal-950/40 border border-teal-500/20 rounded-2xl p-5 mb-6 text-sm text-gray-200 leading-relaxed text-justify space-y-3">
                        <div class="flex items-start gap-3">
                            <span class="text-teal-400 text-lg mt-0.5">🕊️</span>
                            <p><?php echo htmlspecialchars($test_def['intro_text'] ?? 'این پرسشنامه جهت کشف استعدادها و توانمندی‌های بالقوه شما آماده شده است.'); ?></p>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="text-teal-400 text-lg mt-0.5">✨</span>
                            <p><strong>اینجا هیچ پاسخ درست یا غلطی وجود ندارد!</strong> با آرامش کامل، صداقت و اولین احساسی که به نظرتان می‌رسد گزینه‌ها را انتخاب کنید تا توانمندی‌های درونی شما شناسایی شود.</p>
                        </div>
                        <div class="flex items-start gap-3">
                            <span class="text-teal-400 text-lg mt-0.5">⏱️</span>
                            <p>پرسش‌ها به صورت <strong>یکی‌یکی و زمان‌دار</strong> نمایش داده می‌شوند تا ذهن شما بدون استرس و با تمرکز پیش برود. <?php if ($is_battery): ?>بین مراحل فرصت استراحت کوتاه دارید.<?php endif; ?></p>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-3 mb-8 text-center">
                        <div class="bg-gray-900/60 border border-teal-500/10 p-3 rounded-2xl">
                            <span class="text-xs text-gray-400 block mb-1">تعداد پرسش‌ها</span>
                            <strong class="text-teal-300 text-base md:text-lg font-black"><?php echo toFarsiDigits($total_questions_all); ?> سوال</strong>
                        </div>
                        <div class="bg-gray-900/60 border border-teal-500/10 p-3 rounded-2xl">
                            <span class="text-xs text-gray-400 block mb-1"><?php echo $is_battery ? 'مراحل آزمون' : 'زمان هر سوال'; ?></span>
                            <strong class="text-teal-300 text-base md:text-lg font-black"><?php echo $is_battery ? '۴ بخش مجزا' : toFarsiDigits($stages[0]['time_limit']) . ' ثانیه'; ?></strong>
                        </div>
                        <div class="bg-gray-900/60 border border-teal-500/10 p-3 rounded-2xl">
                            <span class="text-xs text-gray-400 block mb-1">زمان تخمینی کل</span>
                            <strong class="text-teal-300 text-base md:text-lg font-black"><?php echo htmlspecialchars($test_def['estimated_time'] ?? '۳۰ تا ۴۵ دقیقه'); ?></strong>
                        </div>
                    </div>

                    <div class="text-center">
                        <p class="text-xs text-gray-400 mb-4">یک لیوان آب کنار دستت بگذار، چند نفس عمیق بکش و با خیال راحت دکمه زیر را لمس کن:</p>
                        <button id="btn-start-test" class="w-full md:w-auto px-10 py-4 bg-gradient-to-r from-teal-500 to-emerald-600 hover:from-teal-400 hover:to-emerald-500 text-white font-black text-lg rounded-2xl shadow-xl shadow-teal-500/30 transition-all transform hover:-translate-y-1 flex items-center justify-center gap-3 mx-auto cursor-pointer">
                            <span>نفس عمیق کشیدم؛ آماده‌ام و شروع می‌کنم</span>
                            <svg class="w-5 h-5 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                        </button>
                    </div>
                </div>

                <!-- 2. Question View (One Question at a Time) -->
                <div id="screen-question" class="hidden glass-panel rounded-3xl p-6 md:p-8 shadow-2xl relative overflow-hidden">
                    
                    <!-- Progress Bar & Question Counter & Timer Header -->
                    <div class="flex items-center justify-between mb-4 border-b border-teal-500/10 pb-4">
                        <div>
                            <?php if ($total_stages > 1): ?>
                            <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-teal-500/20 text-teal-300 text-[11px] font-bold mb-1">
                                <span>بخش <span id="lbl-current-stage-num">۱</span> از <?php echo toFarsiDigits($total_stages); ?>:</span>
                                <span id="lbl-current-stage-title">هوش‌های چندگانه</span>
                            </div>
                            <?php endif; ?>
                            <div class="text-sm font-black text-white">
                                سوال <span id="lbl-current-q" class="text-teal-300 font-mono text-base">۱</span> از <span id="lbl-total-q" class="text-gray-400 font-mono">۸۰</span>
                            </div>
                        </div>

                        <!-- Circular Countdown Timer -->
                        <div class="flex items-center gap-3">
                            <div class="relative w-12 h-12 flex items-center justify-center">
                                <svg class="w-12 h-12 transform -rotate-90">
                                    <circle cx="24" cy="24" r="20" stroke="currentColor" stroke-width="3" class="text-teal-950" fill="transparent" />
                                    <circle id="timer-ring" cx="24" cy="24" r="20" stroke="currentColor" stroke-width="3"
                                            class="text-teal-400 timer-circle" fill="transparent"
                                            stroke-dasharray="125.6" stroke-dashoffset="0" stroke-linecap="round" />
                                </svg>
                                <span id="timer-text" class="absolute font-mono font-black text-sm text-white">۲۰</span>
                            </div>
                            <span class="text-xs text-gray-400 hidden sm:inline">ثانیه</span>
                        </div>
                    </div>

                    <!-- Progress line -->
                    <div class="w-full bg-gray-900/80 h-1.5 rounded-full mb-6 overflow-hidden">
                        <div id="progress-bar" class="bg-gradient-to-r from-teal-400 to-emerald-400 h-full transition-all duration-300" style="width: 2%;"></div>
                    </div>

                    <!-- Question Dimension Badge -->
                    <div class="mb-3">
                        <span id="q-dimension" class="inline-block px-3 py-1 rounded-lg bg-teal-500/15 border border-teal-400/20 text-teal-300 text-xs font-bold">
                            ابعاد ارزیابی
                        </span>
                    </div>

                    <!-- Question Text -->
                    <div class="min-h-[85px] flex items-center mb-6">
                        <h2 id="q-text" class="text-lg md:text-xl font-bold text-white leading-relaxed select-none">
                            متن سوال در اینجا لود می‌شود...
                        </h2>
                    </div>

                    <!-- Touch Options Cards (Dynamic Container) -->
                    <div id="options-container" class="space-y-3 mb-6">
                        <!-- Rendered dynamically by JavaScript -->
                    </div>

                    <!-- Footer Note -->
                    <div class="text-center text-xs text-gray-400">
                        <span>با انتخاب هر گزینه، پاسخ بلافاصله ذخیره شده و سوال بعدی نمایش داده می‌شود.</span>
                    </div>
                </div>

                <!-- 3. Intermission Rest Screen (Between Stages) -->
                <div id="screen-intermission" class="hidden glass-panel rounded-3xl p-8 md:p-10 shadow-2xl text-center">
                    <div class="w-20 h-20 bg-teal-500/20 text-teal-300 rounded-3xl flex items-center justify-center mx-auto mb-6 border border-teal-500/30">
                        <span class="text-4xl animate-bounce">☕</span>
                    </div>
                    <span class="inline-block px-4 py-1.5 rounded-full bg-teal-500/10 border border-teal-500/20 text-teal-300 text-xs font-bold mb-3">
                        استراحت و تنفس کوتاه بین مراحل
                    </span>
                    <h2 id="intermission-title" class="text-2xl font-black text-white mb-3">بسیار عالی! این بخش با موفقیت ثبت شد</h2>
                    <p id="intermission-msg" class="text-gray-200 text-sm mb-6 leading-relaxed max-w-md mx-auto text-justify md:text-center">
                        یک جرعه آب بنوشید، چند ثانیه چشمانتان را ببندید و یک نفس عمیق بکشید.
                    </p>

                    <!-- Next Stage Preview Box -->
                    <div class="bg-gray-900/80 border border-teal-500/20 rounded-2xl p-5 max-w-md mx-auto mb-8 text-right">
                        <span class="text-[11px] text-gray-400 block mb-1">مرحله بعدی در انتظار شماست:</span>
                        <h3 id="intermission-next-title" class="text-base font-black text-teal-300 mb-1">بخش ۲: هوش هیجانی</h3>
                        <p id="intermission-next-badge" class="text-xs text-gray-300 mb-2">مدیریت عواطف و خودآگاهی</p>
                        <span id="intermission-next-info" class="text-xs text-emerald-400 font-mono font-bold block">۳۳ سوال • ۲۵ ثانیه برای هر سوال</span>
                    </div>

                    <button id="btn-next-stage" class="w-full md:w-auto px-10 py-4 bg-gradient-to-r from-teal-500 to-emerald-600 hover:from-teal-400 hover:to-emerald-500 text-white font-black text-base rounded-2xl shadow-xl shadow-teal-500/30 transition flex items-center justify-center gap-3 mx-auto cursor-pointer">
                        <span>آماده‌ام؛ شروع <span id="lbl-next-stage-name">مرحله بعد</span></span>
                        <svg class="w-5 h-5 rotate-180" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    </button>
                </div>

                <!-- 4. Final Completion Screen -->
                <div id="screen-completed" class="hidden glass-panel rounded-3xl p-8 md:p-12 text-center shadow-2xl">
                    <div class="w-20 h-20 bg-teal-500/20 text-teal-300 rounded-3xl flex items-center justify-center mx-auto mb-6 border border-teal-500/30">
                        <svg class="w-10 h-10 animate-bounce" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <span class="inline-block px-4 py-1.5 rounded-full bg-teal-500/10 border border-teal-500/20 text-teal-300 text-xs font-bold mb-3">
                        پایان موفقیت‌آمیز آزمون
                    </span>
                    <h2 class="text-2xl md:text-3xl font-black text-white mb-4">خسته نباشی عزیز دل! ✨</h2>
                    <p class="text-gray-200 text-base mb-6 leading-relaxed max-w-lg mx-auto text-justify md:text-center">
                        تمام پاسخ‌های شما با موفقیت در پرونده بنیاد حکمت ثبت و تحلیل گردید. گزارش روانسنجی و استعدادیابی شما آماده شده و در اختیار تیم ارزیابی قرار گرفت.
                    </p>
                    <div class="bg-teal-950/50 border border-teal-500/20 rounded-2xl p-4 max-w-md mx-auto mb-8 text-sm text-teal-200">
                        مشتاقانه منتظر دیدار و گفت‌وگو با شما در جلسه مصاحبه حضوری هستیم. موفق و سرافراز باشی! 🌱
                    </div>

                    <div class="flex flex-wrap items-center justify-center gap-3">
                        <a id="btn-view-dossier" href="interview-dossier.php?token=<?php echo urlencode($test_record['token']); ?>" class="px-6 py-3 bg-gradient-to-r from-teal-500 to-emerald-600 hover:from-teal-400 hover:to-emerald-500 text-white rounded-xl font-bold text-sm shadow-lg shadow-teal-500/20 transition flex items-center gap-2">
                            <span>📋</span>
                            <span>مشاهده پرونده تحلیلی و رادار چارت</span>
                        </a>
                        <a href="/" class="px-6 py-3 bg-gray-800 hover:bg-gray-700 text-gray-200 rounded-xl font-bold text-sm transition">
                            صفحه اصلی بنیاد حکمت
                        </a>
                    </div>
                </div>

            <?php endif; ?>

        </div>
    </main>

    <!-- Minimal Secure Footer -->
    <footer class="py-4 text-center text-xs text-gray-400 border-t border-teal-500/10 z-20">
        <span>بنیاد نیکوکاری حکمت &copy; تمامی حقوق این سامانه ارزیابی روانسنجی محفوظ است.</span>
    </footer>

    <?php if ($test_record && $test_record['status'] !== 'completed'): ?>
    <!-- Application Logic (Multi-stage Battery, Dynamic Options, Timer, Auto-save) -->
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
            loadQuestion(currentQuestionIdx);
        });

        // Next Stage Button from Intermission Screen
        const btnNextStage = document.getElementById('btn-next-stage');
        if (btnNextStage) {
            btnNextStage.addEventListener('click', function() {
                screenIntermission.classList.add('hidden');
                screenQuestion.classList.remove('hidden');
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

            if (lblCurrentStageNum) lblCurrentStageNum.textContent = toFarsiNum(currentStageIdx + 1);
            if (lblCurrentStageTitle) lblCurrentStageTitle.textContent = currentStage.short_title || currentStage.title;
            lblCurrentQ.textContent = toFarsiNum(index + 1);
            lblTotalQ.textContent = toFarsiNum(questions.length);

            progressBar.style.width = ((index + 1) / questions.length * 100) + '%';
            qDimension.textContent = q.dimension_fa || currentStage.badge || 'ارزیابی';
            qText.textContent = q.text;

            // Options: per-question or stage default
            const opts = (q.options && q.options.length > 0) ? q.options : currentStage.default_options;
            optionsContainer.innerHTML = '';

            opts.forEach((opt, optIdx) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'option-card w-full text-right p-4 rounded-2xl bg-gray-900/60 border border-teal-500/20 hover:border-teal-400 flex items-center justify-between group cursor-pointer';
                btn.setAttribute('data-val', opt.value);

                const subHtml = opt.sub ? `<span class="text-xs text-gray-400 block">${opt.sub}</span>` : '';

                btn.innerHTML = `
                    <div class="flex items-center gap-3">
                        <span class="w-7 h-7 rounded-xl bg-teal-500/10 text-teal-300 border border-teal-500/20 flex items-center justify-center text-xs font-bold group-hover:bg-teal-500 group-hover:text-white transition">
                            ${toFarsiNum(optIdx + 1)}
                        </span>
                        <div>
                            <strong class="text-white text-base block group-hover:text-teal-200 transition">${opt.label}</strong>
                            ${subHtml}
                        </div>
                    </div>
                    <div class="w-5 h-5 rounded-full border border-teal-500/30 flex items-center justify-center group-hover:border-teal-400">
                        <span class="w-2.5 h-2.5 rounded-full bg-teal-400 opacity-0 group-hover:opacity-100 transition"></span>
                    </div>
                `;

                btn.addEventListener('click', function() {
                    btn.classList.add('bg-teal-600/40', 'border-teal-400');
                    selectAnswer(currentStage.id, q.id, opt.value);
                });

                optionsContainer.appendChild(btn);
            });

            currentQuestionTimeLimit = q.time_limit || currentStage.time_limit || 20;
            resetTimer(currentQuestionTimeLimit);
        }

        // Timer reset and handling
        function resetTimer(timeLimit) {
            clearInterval(timerInterval);
            timeLeft = timeLimit;
            updateTimerDisplay();

            timerInterval = setInterval(() => {
                timeLeft--;
                updateTimerDisplay();

                if (timeLeft <= 0) {
                    clearInterval(timerInterval);
                    autoAdvanceOnTimeout();
                }
            }, 1000);
        }

        function updateTimerDisplay() {
            timerText.textContent = toFarsiNum(timeLeft);
            const fraction = timeLeft / currentQuestionTimeLimit;
            const offset = circumference * (1 - fraction);
            timerRing.style.strokeDashoffset = offset;

            if (timeLeft <= 5) {
                timerRing.classList.remove('text-teal-400', 'text-amber-400');
                timerRing.classList.add('text-rose-400');
            } else if (timeLeft <= 10) {
                timerRing.classList.remove('text-teal-400', 'text-rose-400');
                timerRing.classList.add('text-amber-400');
            } else {
                timerRing.classList.remove('text-amber-400', 'text-rose-400');
                timerRing.classList.add('text-teal-400');
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
            });

            // Smooth advance
            setTimeout(() => {
                loadQuestion(currentQuestionIdx + 1);
            }, 180);
        }

        function autoAdvanceOnTimeout() {
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
        }

        // Finish Test & Submit
        function finishTest() {
            clearInterval(timerInterval);
            screenQuestion.classList.add('hidden');
            if (screenIntermission) screenIntermission.classList.add('hidden');
            screenCompleted.classList.remove('hidden');

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
