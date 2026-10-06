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

// دریافت لیست کل آزمون‌های صادر شده
$tests_stmt = $pdo->query("
    SELECT t.*, s.name as st_name, s.surname as st_surname
    FROM interview_tests t
    LEFT JOIN students s ON t.student_id = s.id
    ORDER BY t.id DESC
");
$all_tests = $tests_stmt->fetchAll(PDO::FETCH_ASSOC);

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

    <?php include 'includes/navbar.php'; ?>

    <main class="container mx-auto px-4 py-8 max-w-7xl">

        <!-- Top Header & Breadcrumb -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
            <div>
                <div class="flex items-center gap-2 text-xs text-gray-400 mb-2">
                    <a href="people-list.php" class="hover:text-teal-400 transition">داشبورد مدیریت</a>
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
            
            <!-- Quick Demo Buttons -->
            <div class="flex flex-wrap items-center gap-2">
                <a href="interview-test.php?demo=1&test=gardner_mit" target="_blank"
                   class="px-3 py-2 bg-gray-900 hover:bg-gray-800 border border-teal-500/30 text-teal-300 rounded-xl text-xs font-bold transition flex items-center gap-1.5">
                    <span>🧠</span>
                    <span>پیش‌نمایش گاردنر (۸۰ س)</span>
                </a>
                <a href="interview-test.php?demo=1&test=emotional_seiq" target="_blank"
                   class="px-3 py-2 bg-gray-900 hover:bg-gray-800 border border-teal-500/30 text-teal-300 rounded-xl text-xs font-bold transition flex items-center gap-1.5">
                    <span>❤️</span>
                    <span>پیش‌نمایش هوش هیجانی (۳۳ س)</span>
                </a>
                <a href="interview-test.php?demo=1&test=scl90_mental" target="_blank"
                   class="px-3 py-2 bg-gray-900 hover:bg-gray-800 border border-teal-500/30 text-teal-300 rounded-xl text-xs font-bold transition flex items-center gap-1.5">
                    <span>🛡️</span>
                    <span>پیش‌نمایش سلامت روان (۹۰ س)</span>
                </a>
                <a href="interview-test.php?demo=1&test=hermans_amq" target="_blank"
                   class="px-3 py-2 bg-gray-900 hover:bg-gray-800 border border-teal-500/30 text-teal-300 rounded-xl text-xs font-bold transition flex items-center gap-1.5">
                    <span>🚀</span>
                    <span>پیش‌نمایش انگیزه پیشرفت (۲۹ س)</span>
                </a>
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
            <div class="p-6 border-b border-teal-500/10 flex items-center justify-between">
                <h2 class="text-lg font-black text-white flex items-center gap-2">
                    <span>📋</span>
                    <span>لیست آزمون‌های صادر شده (<?php echo toFarsiDigits(count($all_tests)); ?> مورد)</span>
                </h2>
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
                    <tbody class="divide-y divide-teal-500/5">
                        <?php if (empty($all_tests)): ?>
                            <tr>
                                <td colspan="8" class="text-center py-10 text-gray-500">
                                    هنوز هیچ آزمونی صادر نشده است. با فرم بالا اولین لینک را صادر نمایید.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($all_tests as $t): 
                                $scores = !empty($t['scores_json']) ? json_decode($t['scores_json'], true) : null;
                                $link = $base_test_url . $t['token'];
                                $ttype = $t['test_type'] ?? 'gardner_mit';
                                $tmeta = $available_tests[$ttype] ?? ['title' => $ttype, 'icon' => '📝'];
                            ?>
                            <tr class="hover:bg-teal-500/5 transition">
                                <td class="py-4 px-4 font-mono text-xs text-gray-400">#<?php echo $t['id']; ?></td>
                                <td class="py-4 px-4 font-bold text-white">
                                    <?php echo htmlspecialchars($t['candidate_name']); ?>
                                    <?php if (!empty($t['student_id'])): ?>
                                        <span class="text-xs font-normal text-teal-400 block">دانش‌آموز کد <?php echo $t['student_id']; ?></span>
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
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-amber-500/15 border border-amber-500/30 text-amber-300 text-xs font-bold animate-pulse">
                                            ⏳ در حال پاسخ
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-gray-800 border border-gray-700 text-gray-400 text-xs font-bold">
                                            در انتظار شروع
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 px-4">
                                    <?php if ($scores): ?>
                                        <div class="flex items-center gap-2">
                                            <span class="px-2 py-0.5 rounded bg-teal-500/20 text-teal-300 font-bold font-mono text-xs border border-teal-500/30">
                                                گرید <?php echo htmlspecialchars($scores['grade'] ?? '-'); ?>
                                            </span>
                                            <span class="text-xs text-gray-300">
                                                نمره: <?php echo toFarsiDigits($scores['total_score'] ?? 0); ?>
                                            </span>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-gray-500 text-xs">---</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 px-4 text-xs text-gray-400">
                                    <?php echo formatJalaliDateTime($t['created_at']); ?>
                                </td>
                                <td class="py-4 px-4 text-center">
                                    <div class="flex items-center justify-center gap-2">
                                        <!-- Copy Link Button -->
                                        <button onclick="copyToClipboard('<?php echo htmlspecialchars($link); ?>')"
                                                title="کپی لینک اختصاصی برای ارسال به داوطلب"
                                                class="p-2 bg-gray-800 hover:bg-teal-600/30 text-teal-300 rounded-lg text-xs transition border border-teal-500/20">
                                            🔗 کپی لینک
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

                                        <?php if ($scores): ?>
                                        <!-- View Report Card Button -->
                                        <button onclick='showReportModal(<?php echo json_encode($t, JSON_UNESCAPED_UNICODE); ?>, <?php echo json_encode($scores, JSON_UNESCAPED_UNICODE); ?>)'
                                                class="px-2.5 py-1.5 bg-gradient-to-r from-teal-500 to-emerald-600 hover:from-teal-400 hover:to-emerald-500 text-white font-bold rounded-lg text-xs shadow transition">
                                            📊 کارنامه
                                        </button>
                                        <!-- Full Dossier & Radar Chart Link -->
                                        <a href="interview-dossier.php?token=<?php echo urlencode($t['token']); ?>" target="_blank"
                                           title="مشاهده پرونده تحلیلی تفصیلی و رادار چارت (آماده پرینت A4)"
                                           class="px-2.5 py-1.5 bg-teal-950/80 hover:bg-teal-900 border border-teal-500/40 text-teal-300 font-bold rounded-lg text-xs shadow transition flex items-center gap-1">
                                            📋 پرونده
                                        </a>
                                        <?php endif; ?>
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
    </script>

</body>
</html>
