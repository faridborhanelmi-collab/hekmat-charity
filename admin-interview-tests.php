<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/psychology_questions.php';
require_once 'includes/SmsService.php';

// بررسی دسترسی: فقط مدیرعامل (فرید علمی / superadmin)
if (!is_logged_in()) {
    header('Location: login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

if (!can_view_psychology()) {
    die("❌ دسترسی غیرمجاز: پرونده و سامانه آزمون‌های روانسنجی منحصراً در اختیار مدیریت بنیاد است.");
}

$smsService = new SmsService($pdo);
$message = '';
$message_type = 'info';
$available_tests = getAllAvailableTests();

// اطمینان از وجود جدول آزمون‌های مصاحبه در دیتابیس
$pdo->exec("
    CREATE TABLE IF NOT EXISTS interview_tests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        token TEXT UNIQUE NOT NULL,
        student_id INTEGER,
        candidate_name TEXT NOT NULL,
        mobile TEXT,
        national_id TEXT,
        test_type TEXT DEFAULT 'comprehensive_battery',
        status TEXT DEFAULT 'pending',
        current_question INTEGER DEFAULT 0,
        answers_json TEXT DEFAULT '{}',
        scores_json TEXT DEFAULT '{}',
        total_time_spent INTEGER DEFAULT 0,
        started_at TEXT,
        completed_at TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id)
    )
");

// ایجاد آزمون جدید
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_test') {
    $student_id = !empty($_POST['student_id']) ? intval($_POST['student_id']) : null;
    $candidate_name = trim($_POST['candidate_name'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $test_type = trim($_POST['test_type'] ?? 'comprehensive_battery');
    $send_sms = !empty($_POST['send_sms']);

    // اعتبارسنجی نوع آزمون
    if (!isset($available_tests[$test_type])) {
        $test_type = 'comprehensive_battery';
    }

    // اگر دانش‌آموز انتخاب شده بود و نام وارد نشده بود، نام او را از دیتابیس می‌خوانیم
    if ($student_id && empty($candidate_name)) {
        $st = $pdo->prepare("SELECT name, surname, phone FROM students WHERE id = ?");
        $st->execute([$student_id]);
        $student_data = $st->fetch(PDO::FETCH_ASSOC);
        if ($student_data) {
            $candidate_name = trim($student_data['name'] . ' ' . ($student_data['surname'] ?? ''));
            if (empty($mobile)) {
                $mobile = $student_data['phone'] ?? '';
            }
        }
    }

    if (empty($candidate_name)) {
        $message = 'لطفاً نام داوطلب را وارد نمایید یا دانش‌آموزی را انتخاب کنید.';
        $message_type = 'error';
    } else {
        // ایجاد توکن یکتای امن
        $token = bin2hex(random_bytes(6));

        $stmt = $pdo->prepare("
            INSERT INTO interview_tests (token, student_id, candidate_name, mobile, test_type, status)
            VALUES (?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->execute([$token, $student_id, $candidate_name, $mobile, $test_type]);
        $test_id = $pdo->lastInsertId();

        // پروتکل و دامنه برای ساخت لینک کامل
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'hekmatfoundation.org';
        $test_link = "{$protocol}://{$host}/interview-test.php?token={$token}";

        // ارسال پیامک دعوت در صورت انتخاب
        $sms_sent = false;
        if ($send_sms && !empty($mobile)) {
            $test_title = $available_tests[$test_type]['short_title'] ?? 'ارزیابی آنلاین';
            $sms_text = "داوطلب گرامی {$candidate_name} عزیز،\nبا سلام؛\nجهت انجام «{$test_title}» بنیاد حکمت پیش از جلسه مصاحبه، لطفاً روی پیوند زیر کلیک فرمایید:\n{$test_link}\nبنیاد نیکوکاری حکمت";
            $res = $smsService->sendMelipayamakDirect($mobile, $sms_text);
            $sms_sent = $res['success'] ?? false;
        }

        $test_name = $available_tests[$test_type]['title'] ?? $test_type;
        $message = "آزمون «{$test_name}» برای {$candidate_name} با موفقیت صادر شد." . ($sms_sent ? " پیامک حاوی لینک نیز به شماره {$mobile} ارسال گردید." : "");
        $message_type = 'success';
    }
}

// ارسال مجدد پیامک لینک آزمون
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'resend_sms') {
    $test_id = intval($_POST['test_id'] ?? 0);
    $stmt = $pdo->prepare("SELECT * FROM interview_tests WHERE id = ?");
    $stmt->execute([$test_id]);
    $t = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($t && !empty($t['mobile'])) {
        $protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'hekmatfoundation.org';
        $test_link = "{$protocol}://{$host}/interview-test.php?token={$t['token']}";

        $test_type = $t['test_type'] ?? 'gardner_mit';
        $test_title = $available_tests[$test_type]['short_title'] ?? 'ارزیابی آنلاین';
        $sms_text = "داوطلب گرامی {$t['candidate_name']} عزیز،\nجهت تکمیل «{$test_title}» بنیاد حکمت، روی لینک زیر کلیک کنید:\n{$test_link}\nبنیاد نیکوکاری حکمت";
        $res = $smsService->sendMelipayamakDirect($t['mobile'], $sms_text);
        if ($res['success']) {
            $message = "پیامک حاوی لینک آزمون مجدداً برای {$t['candidate_name']} ارسال شد.";
            $message_type = 'success';
        } else {
            $message = "خطا در ارسال پیامک: " . ($res['message'] ?? 'نامشخص');
            $message_type = 'error';
        }
    }
}

// حذف آزمون توسط مدیر
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_test') {
    $test_id = intval($_POST['test_id'] ?? 0);
    if ($test_id > 0) {
        $del = $pdo->prepare("DELETE FROM interview_tests WHERE id = ?");
        $del->execute([$test_id]);
        $message = "آزمون مورد نظر با موفقیت حذف گردید.";
        $message_type = 'success';
    }
}

// نهایی‌سازی دستی آزمون و محاسبه کارنامه توسط مدیر
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'finalize_test') {
    $test_id = intval($_POST['test_id'] ?? 0);
    if ($test_id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM interview_tests WHERE id = ?");
        $stmt->execute([$test_id]);
        $t = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($t) {
            $now = date('Y-m-d H:i:s');
            $t_ans = json_decode($t['answers_json'] ?: '{}', true);
            $ttype = $t['test_type'] ?: 'comprehensive_battery';
            $calc_scores = calculateTestScores($ttype, $t_ans);
            $up = $pdo->prepare("UPDATE interview_tests SET status = 'completed', completed_at = ?, scores_json = ? WHERE id = ?");
            $up->execute([$now, json_encode($calc_scores, JSON_UNESCAPED_UNICODE), $test_id]);
            
            if (!empty($t['student_id'])) {
                syncInterviewScoresToStudentPsychology($pdo, $t['student_id'], $ttype, $calc_scores);
            }
            
            $message = "آزمون داوطلب «{$t['candidate_name']}» با موفقیت نهایی و کارنامه وی ثبت گردید.";
            $message_type = 'success';
        }
    }
}

// بازنشانی آزمون برای شرکت مجدد داوطلب (پاک کردن آزمون نیمه‌کاره)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_test') {
    $test_id = intval($_POST['test_id'] ?? 0);
    if ($test_id > 0) {
        $rst = $pdo->prepare("UPDATE interview_tests SET status = 'pending', started_at = NULL, completed_at = NULL, current_question = 0, answers_json = '{}', scores_json = '{}' WHERE id = ?");
        $rst->execute([$test_id]);
        $message = "آزمون داوطلب با موفقیت بازنشانی شد و داوطلب می‌تواند از ابتدا در آزمون شرکت نماید.";
        $message_type = 'success';
    }
}

// حالت مرتب‌سازی (پیش‌فرض: بر اساس شرکت‌کنندگان و بالاترین امتیاز)
$sort_mode = trim($_GET['sort'] ?? 'score');
if ($sort_mode === 'date') {
    $order_clause = "t.id DESC";
} elseif ($sort_mode === 'name') {
    $order_clause = "t.candidate_name ASC";
} else {
    // مرتب‌سازی هوشمند: تکمیل‌شده‌ها بر اساس بالاترین امتیاز مکتسبه، سپس در حال آزمون، سپس در انتظار
    $order_clause = "
        CASE t.status 
            WHEN 'completed' THEN 1 
            WHEN 'in_progress' THEN 2 
            ELSE 3 
        END ASC,
        CAST(json_extract(t.scores_json, '$.total_score') AS INTEGER) DESC,
        t.id DESC
    ";
}

// دریافت لیست کل آزمون‌های صادر شده با قابلیت استخراج نمره کل
$tests_stmt = $pdo->query("
    SELECT t.*, s.name as st_name, s.surname as st_surname,
           CAST(json_extract(t.scores_json, '$.total_score') AS INTEGER) as extracted_score
    FROM interview_tests t
    LEFT JOIN students s ON t.student_id = s.id
    ORDER BY {$order_clause}
");
$all_tests = $tests_stmt->fetchAll(PDO::FETCH_ASSOC);

// آمار وضعیت داوطلبان
$total_count = count($all_tests);
$completed_count = count(array_filter($all_tests, fn($t) => ($t['status'] ?? '') === 'completed'));
$pending_count = count(array_filter($all_tests, fn($t) => ($t['status'] ?? '') === 'pending'));

// تفکیک دقیق آزمون‌های واقعاً آنلاین در برابر نیمه‌کاره رها شده
$incomplete_count = 0;
$active_online_count = 0;
$now_ts = time();
foreach ($all_tests as $t) {
    if (($t['status'] ?? '') === 'in_progress') {
        $st_ts = !empty($t['started_at']) ? strtotime($t['started_at']) : 0;
        if ($st_ts > 0 && ($now_ts - $st_ts) > 5400) { // بیش از ۱.۵ ساعت قبل شروع شده و تمام نشده
            $incomplete_count++;
        } else {
            $active_online_count++;
        }
    }
}

// دریافت لیست دانش‌آموزان برای دراپ‌داون
$students_stmt = $pdo->query("SELECT id, name, surname, phone FROM students ORDER BY name ASC, surname ASC");
$students_list = $students_stmt->fetchAll(PDO::FETCH_ASSOC);

$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'hekmatfoundation.org';
$base_test_url = "{$protocol}://{$host}/interview-test.php?token=";

$page_title = 'مدیریت آزمون‌های روانسنجی و مصاحبه آنلاین | بنیاد حکمت';
$is_private_page = true;
?>
<!DOCTYPE html>
<html lang="fa-IR" dir="rtl" class="scroll-smooth">
<head>
    <?php include 'includes/head.php'; ?>
    <style>
        .glass-box {
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(12px);
            border: 1px solid rgba(45, 212, 191, 0.15);
        }
    </style>
</head>
<body class="bg-gray-950 text-gray-100 font-sans min-h-screen">

    <?php 
    $page_nav_title = 'سامانه آزمون‌های روانسنجی و مصاحبه';
    include 'includes/admin-navbar.php'; 
    ?>

    <main class="container mx-auto px-4 py-8 max-w-7xl">

        <!-- Top Header & Breadcrumb -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <div class="flex items-center gap-2 text-xs text-gray-400 mb-2">
                    <a href="/admin/index.php" class="hover:text-teal-400 transition">داشبورد مدیریت</a>
                    <span>/</span>
                    <span class="text-teal-400 font-bold">سامانه آزمون‌های روانسنجی و مصاحبه</span>
                </div>
                <h1 class="text-2xl md:text-3xl font-black text-white flex items-center gap-3">
                    <span class="p-2.5 bg-teal-500/10 text-teal-400 rounded-2xl border border-teal-500/20">🧠</span>
                    مدیریت و صدور آزمون‌های روانسنجی بنیاد حکمت
                </h1>
                <p class="text-gray-400 text-sm mt-1">
                    صدور لینک‌های اختصاصی پیامکی زمان‌دار از روی بسته استاندارد روانسنجی (هوش‌های چندگانه، هوش هیجانی، سلامت روان و انگیزه پیشرفت)
                </p>
            </div>
            
            <!-- Live Status KPI Badges & Comparison Link -->
            <div class="flex flex-wrap items-center gap-2.5">
                <a href="admin-psychology-comparison.php" class="inline-flex items-center gap-2 px-4 py-2 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white rounded-2xl text-xs font-black shadow-lg shadow-indigo-500/25 transition-all hover:scale-105 border border-indigo-400/30">
                    <span class="text-base">⚖️</span>
                    <span>رادار مقایسه نخبگان (قدیم و جدید)</span>
                </a>
                <div class="px-3.5 py-2 bg-gray-900/90 border border-teal-500/20 rounded-2xl flex items-center gap-2">
                    <span class="text-xs text-gray-400">کل داوطلبان:</span>
                    <strong class="text-sm font-black text-white font-mono"><?php echo toFarsiDigits($total_count); ?></strong>
                </div>
                <div class="px-3.5 py-2 bg-emerald-950/60 border border-emerald-500/30 rounded-2xl flex items-center gap-2">
                    <span class="text-xs text-emerald-300">✓ تکمیل شده:</span>
                    <strong class="text-sm font-black text-emerald-400 font-mono"><?php echo toFarsiDigits($completed_count); ?></strong>
                </div>
                <?php if ($active_online_count > 0): ?>
                <div class="px-3.5 py-2 bg-amber-950/60 border border-amber-500/30 rounded-2xl flex items-center gap-2">
                    <span class="text-xs text-amber-300">⏳ در حال پاسخ آنلاین:</span>
                    <strong class="text-sm font-black text-amber-400 font-mono animate-pulse"><?php echo toFarsiDigits($active_online_count); ?></strong>
                </div>
                <?php endif; ?>
                <?php if ($incomplete_count > 0): ?>
                <div class="px-3.5 py-2 bg-rose-950/60 border border-rose-500/30 rounded-2xl flex items-center gap-2">
                    <span class="text-xs text-rose-300">⚠️ نیمه‌کاره رها شده:</span>
                    <strong class="text-sm font-black text-rose-400 font-mono"><?php echo toFarsiDigits($incomplete_count); ?></strong>
                </div>
                <?php endif; ?>
                <div class="px-3.5 py-2 bg-gray-900/90 border border-gray-700 rounded-2xl flex items-center gap-2">
                    <span class="text-xs text-gray-400">در انتظار شروع:</span>
                    <strong class="text-sm font-black text-gray-300 font-mono"><?php echo toFarsiDigits($pending_count); ?></strong>
                </div>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="mb-6 p-4 rounded-2xl flex items-center gap-3 text-sm font-bold <?php echo $message_type === 'success' ? 'bg-emerald-950/80 border border-emerald-500/40 text-emerald-200' : 'bg-rose-950/80 border border-rose-500/40 text-rose-200'; ?>">
                <span><?php echo $message_type === 'success' ? '✅' : '⚠️'; ?></span>
                <span><?php echo htmlspecialchars($message); ?></span>
            </div>
        <?php endif; ?>

        <!-- Create Test Link Card -->
        <div class="glass-box rounded-3xl p-6 md:p-8 mb-8 shadow-xl">
            <h2 class="text-lg font-black text-teal-300 mb-4 flex items-center gap-2">
                <span>➕</span>
                <span>صدور لینک آزمون پیامکی برای داوطلب جدید</span>
            </h2>

            <form method="POST" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                <input type="hidden" name="action" value="create_test">

                <div>
                    <label class="block text-xs font-bold text-gray-300 mb-2">دانش‌آموز موجود (اختیاری):</label>
                    <select name="student_id" id="sel_student"
                            class="w-full py-2.5 px-3 bg-gray-900 border border-teal-500/20 rounded-xl text-sm text-gray-200 focus:outline-none focus:border-teal-400">
                        <option value="">-- متقاضی آزاد / جدید --</option>
                        <?php foreach ($students_list as $st): ?>
                            <option value="<?php echo $st['id']; ?>" data-name="<?php echo htmlspecialchars($st['name'] . ' ' . ($st['surname'] ?? '')); ?>" data-phone="<?php echo htmlspecialchars($st['phone'] ?? ''); ?>">
                                <?php echo htmlspecialchars($st['name'] . ' ' . ($st['surname'] ?? '')); ?> (کد: <?php echo $st['id']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-300 mb-2">نام و نام خانوادگی داوطلب:</label>
                    <input type="text" name="candidate_name" id="inp_candidate_name" required placeholder="مثال: علی رضایی"
                           class="w-full py-2.5 px-3 bg-gray-900 border border-teal-500/20 rounded-xl text-sm text-white focus:outline-none focus:border-teal-400">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-300 mb-2">شماره تلفن همراه داوطلب:</label>
                    <input type="text" name="mobile" id="inp_mobile" placeholder="۰۹۱۲۳۴۵۶۷۸۹"
                           class="w-full py-2.5 px-3 bg-gray-900 border border-teal-500/20 rounded-xl text-sm text-white focus:outline-none focus:border-teal-400 text-center font-mono">
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-300 mb-2">نوع آزمون روانسنجی:</label>
                    <select name="test_type" id="sel_test_type"
                            class="w-full py-2.5 px-3 bg-gray-900 border border-teal-500/20 rounded-xl text-sm text-gray-200 focus:outline-none focus:border-teal-400">
                        <?php foreach ($available_tests as $key => $tdef): ?>
                            <option value="<?php echo $key; ?>">
                                <?php echo $tdef['icon'] . ' ' . htmlspecialchars($tdef['title']); ?> (<?php echo toFarsiDigits($tdef['total_questions']); ?> سوال)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="flex items-center gap-3">
                    <label class="flex items-center gap-2 cursor-pointer bg-gray-900/80 px-3 py-2.5 rounded-xl border border-teal-500/20 w-full text-xs text-teal-200">
                        <input type="checkbox" name="send_sms" value="1" checked class="w-4 h-4 text-teal-500 rounded border-gray-700 bg-gray-800">
                        <span>ارسال پیامک لینک</span>
                    </label>
                    <button type="submit"
                            class="py-2.5 px-5 bg-gradient-to-r from-teal-500 to-emerald-600 hover:from-teal-400 hover:to-emerald-500 text-white rounded-xl text-sm font-bold whitespace-nowrap shadow-lg shadow-teal-500/20 transition">
                        صدور لینک ✨
                    </button>
                </div>
            </form>
        </div>

        <!-- Table of Issued Tests -->
        <div class="glass-box rounded-3xl overflow-hidden shadow-xl">
            <div class="p-6 border-b border-teal-500/10 flex flex-col xl:flex-row xl:items-center justify-between gap-4">
                <h2 class="text-lg font-black text-white flex items-center gap-2">
                    <span>📋</span>
                    <span>لیست آزمون‌های داوطلبان واقعی (<?php echo toFarsiDigits($total_count); ?> نفر)</span>
                </h2>
                
                <div class="flex flex-wrap items-center gap-3">
                    <!-- Filter Tabs -->
                    <div class="flex flex-wrap items-center gap-1.5 text-xs">
                        <button type="button" onclick="filterTable('all')" id="btn-filter-all"
                                class="filter-btn px-3 py-1.5 rounded-xl bg-teal-500 text-white font-bold transition shadow-sm">
                            همه (<?php echo toFarsiDigits($total_count); ?>)
                        </button>
                        <button type="button" onclick="filterTable('completed')" id="btn-filter-completed"
                                class="filter-btn px-3 py-1.5 rounded-xl bg-gray-900 border border-teal-500/20 text-gray-300 hover:text-white transition">
                            ✓ تکمیل شده (<?php echo toFarsiDigits($completed_count); ?>)
                        </button>
                        <button type="button" onclick="filterTable('in_progress')" id="btn-filter-in_progress"
                                class="filter-btn px-3 py-1.5 rounded-xl bg-gray-900 border border-teal-500/20 text-gray-300 hover:text-white transition">
                            ⏳ در حال آزمون (<?php echo toFarsiDigits($in_progress_count); ?>)
                        </button>
                        <button type="button" onclick="filterTable('pending')" id="btn-filter-pending"
                                class="filter-btn px-3 py-1.5 rounded-xl bg-gray-900 border border-teal-500/20 text-gray-300 hover:text-white transition">
                            در انتظار شروع (<?php echo toFarsiDigits($pending_count); ?>)
                        </button>
                    </div>

                    <!-- Sort Controls -->
                    <div class="flex items-center gap-1 bg-gray-900/90 p-1 rounded-xl border border-teal-500/20 text-xs">
                        <span class="text-gray-500 px-2 font-bold text-[11px]">ترتیب:</span>
                        <a href="?sort=score" class="px-2.5 py-1 rounded-lg font-bold transition <?php echo $sort_mode === 'score' ? 'bg-teal-500 text-white shadow-sm' : 'text-gray-400 hover:text-teal-300'; ?>">
                            🏆 بالاترین امتیاز
                        </a>
                        <a href="?sort=date" class="px-2.5 py-1 rounded-lg font-bold transition <?php echo $sort_mode === 'date' ? 'bg-teal-500 text-white shadow-sm' : 'text-gray-400 hover:text-teal-300'; ?>">
                            🕒 تاریخ صدور
                        </a>
                        <a href="?sort=name" class="px-2.5 py-1 rounded-lg font-bold transition <?php echo $sort_mode === 'name' ? 'bg-teal-500 text-white shadow-sm' : 'text-gray-400 hover:text-teal-300'; ?>">
                            🔤 نام داوطلب
                        </a>
                    </div>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-right text-sm">
                    <thead class="bg-gray-900/90 text-gray-400 text-xs border-b border-teal-500/10">
                        <tr>
                            <th class="py-4 px-4 font-bold">شناسه</th>
                            <th class="py-4 px-4 font-bold">نام داوطلب</th>
                            <th class="py-4 px-4 font-bold">شماره همراه</th>
                            <th class="py-4 px-4 font-bold">نوع آزمون</th>
                            <th class="py-4 px-4 font-bold">وضعیت</th>
                            <th class="py-4 px-4 font-bold">نتیجه و گرید</th>
                            <th class="py-4 px-4 font-bold">تاریخ صدور</th>
                            <th class="py-4 px-4 font-bold text-center">عملیات</th>
                        </tr>
                    </thead>
                    <tbody id="tests-table-body" class="divide-y divide-teal-500/5">
                        <?php if (empty($all_tests)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-10 text-gray-500">
                                    هنوز هیچ آزمونی صادر نشده است. با فرم بالا اولین لینک را صادر نمایید.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php 
                            $rank_counter = 1;
                            foreach ($all_tests as $t): 
                                $scores = !empty($t['scores_json']) ? json_decode($t['scores_json'], true) : null;
                                $link = $base_test_url . $t['token'];
                                $ttype = $t['test_type'] ?? 'comprehensive_battery';
                                $tmeta = $available_tests[$ttype] ?? ['title' => $ttype, 'icon' => '📝'];

                                // شمارش کل پاسخ‌های ذخیره شده
                                $ans_obj = !empty($t['answers_json']) ? json_decode($t['answers_json'], true) : [];
                                $total_ans = 0;
                                if (is_array($ans_obj)) {
                                    foreach ($ans_obj as $stg_ans) {
                                        if (is_array($stg_ans)) $total_ans += count($stg_ans);
                                        else $total_ans++;
                                    }
                                }

                                // اگر نمره هنوز در دیتابیس ثبت نشده اما پاسخ‌هایی وجود دارد، محاسبه لحظه‌ای
                                $is_estimated_score = false;
                                if (!$scores && $total_ans > 0) {
                                    $scores = calculateTestScores($ttype, $ans_obj);
                                    $is_estimated_score = true;
                                }
                            ?>
                            <tr data-status="<?php echo htmlspecialchars($t['status'] ?? 'pending'); ?>" class="hover:bg-teal-500/5 transition">
                                <td class="py-4 px-4 font-mono text-xs text-gray-400">#<?php echo $t['id']; ?></td>
                                <td class="py-4 px-4">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-white"><?php echo htmlspecialchars($t['candidate_name']); ?></span>
                                        <?php if ($t['status'] === 'completed' && $sort_mode === 'score'): ?>
                                            <?php if ($rank_counter === 1): ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-500/20 text-amber-300 border border-amber-500/40">🥇 رتبه ۱</span>
                                            <?php elseif ($rank_counter === 2): ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black bg-slate-300/20 text-slate-200 border border-slate-300/40">🥈 رتبه ۲</span>
                                            <?php elseif ($rank_counter === 3): ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-700/20 text-amber-400 border border-amber-700/40">🥉 رتبه ۳</span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black bg-teal-500/10 text-teal-300 border border-teal-500/20">🎖️ رتبه <?php echo toFarsiDigits($rank_counter); ?></span>
                                            <?php endif; ?>
                                            <?php $rank_counter++; ?>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($t['student_id'])): ?>
                                        <span class="text-xs font-normal text-teal-400 block mt-0.5">دانش‌آموز کد <?php echo $t['student_id']; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 px-4 font-mono text-xs text-gray-300">
                                    <?php echo !empty($t['mobile']) ? htmlspecialchars($t['mobile']) : '<span class="text-gray-600">ثبت‌نشده</span>'; ?>
                                </td>
                                <td class="py-4 px-4">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-teal-950/60 border border-teal-500/20 text-teal-300 text-xs font-bold">
                                        <span><?php echo $tmeta['icon'] ?? '📝'; ?></span>
                                        <span><?php echo htmlspecialchars($tmeta['short_title'] ?? $tmeta['title']); ?></span>
                                    </span>
                                </td>
                                <td class="py-4 px-4">
                                    <?php if ($t['status'] === 'completed'): ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-emerald-500/15 border border-emerald-500/30 text-emerald-300 text-xs font-bold">
                                            ✓ تکمیل شده
                                        </span>
                                    <?php elseif ($t['status'] === 'in_progress'): ?>
                                        <?php 
                                        $started_time = !empty($t['started_at']) ? strtotime($t['started_at']) : 0;
                                        $elapsed_hours = $started_time > 0 ? (time() - $started_time) / 3600 : 0;
                                        $is_abandoned = ($elapsed_hours > 2.0);
                                        ?>
                                        <?php if ($is_abandoned): ?>
                                            <div>
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-rose-500/15 border border-rose-500/30 text-rose-300 text-xs font-bold">
                                                    ⚠️ نیمه‌کاره رها شده
                                                </span>
                                                <span class="text-[11px] text-gray-400 block mt-1">
                                                    <?php echo toFarsiDigits($total_ans); ?> پاسخ ذخیره
                                                </span>
                                            </div>
                                        <?php else: ?>
                                            <div>
                                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-amber-500/15 border border-amber-500/30 text-amber-300 text-xs font-bold animate-pulse">
                                                    ⏳ در حال پاسخ آنلاین
                                                </span>
                                                <?php if ($total_ans > 0): ?>
                                                <span class="text-[11px] text-teal-300 block mt-1">
                                                    <?php echo toFarsiDigits($total_ans); ?> پاسخ ذخیره شده
                                                </span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-gray-800 border border-gray-700 text-gray-400 text-xs font-bold">
                                            در انتظار شروع
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 px-4">
                                    <?php if ($scores): ?>
                                        <div class="flex flex-col gap-1">
                                            <div class="flex items-center gap-2">
                                                <span class="px-2 py-0.5 rounded <?php echo $is_estimated_score ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30' : 'bg-teal-500/20 text-teal-300 border border-teal-500/30'; ?> font-bold font-mono text-xs">
                                                    گرید <?php echo htmlspecialchars($scores['grade'] ?? '-'); ?>
                                                    <?php if ($is_estimated_score): ?><span class="text-[10px] font-normal">(اولیه)</span><?php endif; ?>
                                                </span>
                                                <span class="text-xs font-mono font-bold text-emerald-400">
                                                    <?php echo toFarsiDigits($scores['total_score'] ?? 0); ?> / ۱۰۰
                                                </span>
                                            </div>
                                            <?php if ($is_estimated_score): ?>
                                                <span class="text-[10px] text-amber-300/80">بر مبنای <?php echo toFarsiDigits($total_ans); ?> پاسخ اولیه</span>
                                            <?php endif; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-gray-500 text-xs">---</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 px-4 text-xs text-gray-400">
                                    <?php echo formatJalaliDateTime($t['created_at']); ?>
                                </td>
                                <td class="py-4 px-4 text-center">
                                    <div class="flex items-center justify-center gap-2 flex-wrap">
                                        <!-- Finalize Test Button for In-Progress with answers -->
                                        <?php if ($t['status'] === 'in_progress' && $total_ans > 0): ?>
                                        <form method="POST" class="inline" onsubmit="return confirm('آیا می‌خواهید آزمون «<?php echo htmlspecialchars($t['candidate_name']); ?>» با همین <?php echo toFarsiDigits($total_ans); ?> پاسخ نهایی و در سامانه تکمیل شود؟');">
                                            <input type="hidden" name="action" value="finalize_test">
                                            <input type="hidden" name="test_id" value="<?php echo $t['id']; ?>">
                                            <button type="submit" title="ثبت نهایی کارنامه با پاسخ‌های فعلی"
                                                    class="px-2.5 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-lg text-xs shadow transition flex items-center gap-1">
                                                <span>✓</span>
                                                <span>ثبت نهایی</span>
                                            </button>
                                        </form>
                                        <?php endif; ?>

                                        <!-- Copy Link Button -->
                                        <button onclick="copyToClipboard('<?php echo htmlspecialchars($link); ?>')"
                                                title="کپی لینک اختصاصی برای ارسال به داوطلب"
                                                class="p-2 bg-gray-800 hover:bg-teal-600/30 text-teal-300 rounded-lg text-xs transition border border-teal-500/20">
                                            🔗 کپی
                                        </button>

                                        <?php if (!empty($t['mobile'])): ?>
                                        <!-- Resend SMS Form -->
                                        <form method="POST" class="inline" onsubmit="return confirm('پیامک لینک آزمون به شماره <?php echo $t['mobile']; ?> ارسال شود؟');">
                                            <input type="hidden" name="action" value="resend_sms">
                                            <input type="hidden" name="test_id" value="<?php echo $t['id']; ?>">
                                            <button type="submit" title="ارسال پیامک مجدد"
                                                    class="p-2 bg-gray-800 hover:bg-gray-700 text-amber-300 rounded-lg text-xs transition border border-amber-500/20">
                                                📱 پیامک
                                            </button>
                                        </form>
                                        <?php endif; ?>

                                        <?php if ($t['status'] === 'in_progress'): ?>
                                        <!-- Reset Test Form -->
                                        <form method="POST" class="inline" onsubmit="return confirm('آیا از بازنشانی آزمون برای «<?php echo htmlspecialchars($t['candidate_name']); ?>» اطمینان دارید؟\nتمام پاسخ‌های ناقص قبلی پاک شده و داوطلب می‌تواند از ابتدا شرکت کند.');">
                                            <input type="hidden" name="action" value="reset_test">
                                            <input type="hidden" name="test_id" value="<?php echo $t['id']; ?>">
                                            <button type="submit" title="بازنشانی آزمون برای شروع مجدد از ابتدا"
                                                    class="p-2 bg-gray-800 hover:bg-amber-900/60 text-amber-300 rounded-lg text-xs transition border border-amber-500/20">
                                                🔄 بازنشانی
                                            </button>
                                        </form>
                                        <?php endif; ?>

                                        <?php if ($scores): ?>
                                        <!-- View Report Card Button -->
                                        <button onclick='showReportModal(<?php echo json_encode($t, JSON_UNESCAPED_UNICODE); ?>, <?php echo json_encode($scores, JSON_UNESCAPED_UNICODE); ?>)'
                                                class="px-2.5 py-1.5 bg-gradient-to-r from-teal-500 to-emerald-600 hover:from-teal-400 hover:to-emerald-500 text-white font-bold rounded-lg text-xs shadow transition">
                                            📊 کارنامه
                                        </button>
                                        <?php endif; ?>

                                        <!-- Full Dossier & Radar Chart Link (Always accessible) -->
                                        <a href="/interview-dossier.php?token=<?php echo urlencode($t['token']); ?>" target="_blank"
                                           title="مشاهده پرونده تحلیلی تفصیلی و رادار چارت (آماده پرینت A4)"
                                           class="px-2.5 py-1.5 bg-teal-950/80 hover:bg-teal-900 border border-teal-500/40 text-teal-300 font-bold rounded-lg text-xs shadow transition flex items-center gap-1">
                                            📋 پرونده
                                        </a>

                                        <!-- Live Interview & Audio Recording Studio Link (Always accessible) -->
                                        <a href="/interview-dossier.php?token=<?php echo urlencode($t['token']); ?>#interview-recorder" target="_blank"
                                           title="ورود به استودیوی مصاحبه حضوری، سوالات اختصاصی و ضبط صدای داوطلب"
                                           class="px-2.5 py-1.5 bg-gradient-to-r from-rose-600 to-red-600 hover:from-rose-500 hover:to-red-500 text-white font-bold rounded-lg text-xs shadow-md shadow-rose-600/25 transition flex items-center gap-1">
                                            🎙️ مصاحبه
                                        </a>

                                        <!-- Delete Test Form -->
                                        <form method="POST" class="inline" onsubmit="return confirm('آیا از حذف آزمون «<?php echo htmlspecialchars($t['candidate_name']); ?>» اطمینان کامل دارید؟');">
                                            <input type="hidden" name="action" value="delete_test">
                                            <input type="hidden" name="test_id" value="<?php echo $t['id']; ?>">
                                            <button type="submit" title="حذف آزمون"
                                                    class="p-2 bg-gray-800 hover:bg-rose-950/80 text-rose-400 hover:text-rose-200 rounded-lg text-xs transition border border-rose-500/20">
                                                🗑️
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- Report Card Modal (Supports Gardner, SEIQ, SCL-90, Hermans AMQ) -->
    <div id="report-modal" class="fixed inset-0 bg-black/80 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="glass-box rounded-3xl p-6 md:p-8 max-w-3xl w-full max-h-[90vh] overflow-y-auto border-teal-500/40 relative">
            <button onclick="closeReportModal()" class="absolute top-6 left-6 text-gray-400 hover:text-white text-xl">✕</button>
            
            <div class="border-b border-teal-500/20 pb-4 mb-6">
                <span id="modal-test-title" class="text-xs text-teal-400 font-bold block mb-1">کارنامه تحلیلی ارزیابی</span>
                <h3 id="modal-candidate-name" class="text-2xl font-black text-white">نام داوطلب</h3>
            </div>

            <!-- Grade & Summary Cards -->
            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6 text-center">
                <div class="bg-gray-900/80 p-3 rounded-2xl border border-teal-500/20">
                    <span class="text-xs text-gray-400 block mb-1">گرید ارزیابی</span>
                    <strong id="modal-grade" class="text-xl font-black text-teal-300">A</strong>
                </div>
                <div class="bg-gray-900/80 p-3 rounded-2xl border border-teal-500/20">
                    <span class="text-xs text-gray-400 block mb-1">نمره کل مکتسبه</span>
                    <strong id="modal-total-score" class="text-xl font-black text-white">120 / 160</strong>
                </div>
                <div class="bg-gray-900/80 p-3 rounded-2xl border border-teal-500/20">
                    <span class="text-xs text-gray-400 block mb-1">درصد پوشش</span>
                    <strong id="modal-percentage" class="text-xl font-black text-emerald-300">75%</strong>
                </div>
                <div class="bg-gray-900/80 p-3 rounded-2xl border border-teal-500/20">
                    <span class="text-xs text-gray-400 block mb-1">وضعیت ارزیابی</span>
                    <strong class="text-xl font-black text-teal-400">تکمیل شده</strong>
                </div>
            </div>

            <!-- Dimension Bars -->
            <div class="mb-6">
                <h4 id="modal-dimensions-title" class="text-sm font-bold text-teal-300 mb-3">تفکیک ابعاد ارزیابی:</h4>
                <div id="modal-dimensions-list" class="space-y-3">
                    <!-- Dynamic Bars -->
                </div>
            </div>

            <!-- Interview Recommendation -->
            <div class="bg-teal-950/60 border border-teal-500/30 rounded-2xl p-4 mb-6">
                <h4 class="text-xs font-bold text-teal-300 mb-2 flex items-center gap-1.5">
                    <span>💡</span>
                    <span>راهنمای ویژه تیم مصاحبه‌کننده بنیاد حکمت:</span>
                </h4>
                <p id="modal-recommendation" class="text-xs md:text-sm text-gray-200 leading-relaxed text-justify">
                    توصیه تحلیلی در اینجا لود می‌شود...
                </p>
            </div>

            <div class="flex flex-wrap items-center justify-center gap-3">
                <a id="modal-dossier-btn" href="#" target="_blank" class="px-5 py-2.5 bg-gradient-to-r from-teal-500 to-emerald-600 hover:from-teal-400 hover:to-emerald-500 text-white rounded-xl text-xs font-bold transition flex items-center gap-2 shadow-lg shadow-teal-500/20">
                    <span>📋</span>
                    <span>مشاهده پرونده تفصیلی و رادار چارت (آماده چاپ A4)</span>
                </a>
                <button onclick="closeReportModal()" class="px-5 py-2.5 bg-gray-800 hover:bg-gray-700 text-white rounded-xl text-xs font-bold transition">
                    بستن کارنامه
                </button>
            </div>
        </div>
    </div>

    <script>
        // Copy to clipboard helper
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert('لینک اختصاصی آزمون با موفقیت کپی شد:\n' + text);
            }).catch(err => {
                prompt('لینک آزمون:', text);
            });
        }

        // Student dropdown autofill
        const selStudent = document.getElementById('sel_student');
        const inpName = document.getElementById('inp_candidate_name');
        const inpMobile = document.getElementById('inp_mobile');

        selStudent.addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            if (this.value) {
                inpName.value = opt.getAttribute('data-name') || '';
                inpMobile.value = opt.getAttribute('data-phone') || '';
            }
        });

        // Report Modal Logic
        const reportModal = document.getElementById('report-modal');
        function showReportModal(test, scores) {
            document.getElementById('modal-candidate-name').textContent = test.candidate_name;
            document.getElementById('modal-test-title').textContent = scores.test_title || 'کارنامه تحلیلی ارزیابی';
            document.getElementById('modal-grade').textContent = scores.grade || '-';
            const dossierBtn = document.getElementById('modal-dossier-btn');
            if (dossierBtn) {
                dossierBtn.href = 'interview-dossier.php?token=' + encodeURIComponent(test.token);
            }
            
            const maxScore = scores.total_max ? ' از ' + scores.total_max : '';
            document.getElementById('modal-total-score').textContent = (scores.total_score || 0) + maxScore;
            document.getElementById('modal-percentage').textContent = (scores.overall_percentage || 0) + '%';
            document.getElementById('modal-recommendation').textContent = scores.recommendation || '';

            const list = document.getElementById('modal-dimensions-list');
            list.innerHTML = '';

            if (scores.dimensions) {
                for (const [key, dim] of Object.entries(scores.dimensions)) {
                    const row = document.createElement('div');
                    row.className = 'bg-gray-900/60 p-3 rounded-xl border border-teal-500/10';
                    const maxLabel = dim.max ? `از ${dim.max} ` : '';
                    const rawVal = dim.raw !== undefined ? dim.raw : (dim.score !== undefined ? dim.score : '');
                    row.innerHTML = `
                        <div class="flex justify-between items-center text-xs mb-1.5">
                            <span class="font-bold text-gray-200">${dim.title}</span>
                            <span class="text-teal-300 font-mono font-bold">${rawVal} ${maxLabel}(${dim.percentage}%)</span>
                        </div>
                        <div class="w-full bg-gray-800 h-2 rounded-full overflow-hidden">
                            <div class="bg-gradient-to-r from-teal-500 to-emerald-400 h-full rounded-full transition-all duration-500" style="width: ${dim.percentage}%"></div>
                        </div>
                    `;
                    list.appendChild(row);
                }
            }

            reportModal.classList.remove('hidden');
        }

        function closeReportModal() {
            reportModal.classList.add('hidden');
        }

        // فیلتر کردن ردیف‌های جدول بر اساس وضعیت آزمون
        function filterTable(status) {
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('bg-teal-500', 'text-white', 'font-bold', 'shadow-sm');
                btn.classList.add('bg-gray-900', 'border', 'border-teal-500/20', 'text-gray-300');
            });
            const activeBtn = document.getElementById('btn-filter-' + status);
            if (activeBtn) {
                activeBtn.classList.remove('bg-gray-900', 'border', 'border-teal-500/20', 'text-gray-300');
                activeBtn.classList.add('bg-teal-500', 'text-white', 'font-bold', 'shadow-sm');
            }
            document.querySelectorAll('#tests-table-body tr[data-status]').forEach(row => {
                if (status === 'all' || row.getAttribute('data-status') === status) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
    </script>

</body>
</html>
