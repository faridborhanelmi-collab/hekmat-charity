<?php
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';

// دریافت و نگهداری مسیر ریدایرکت جهت بازگشت مستقیم پس از ثبت‌نام
if (isset($_GET['redirect'])) {
    $raw_redirect = trim($_GET['redirect']);
    if ($raw_redirect !== '' && strpos($raw_redirect, '/') !== 0 && strpos($raw_redirect, '\\') === false && !preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*:#', $raw_redirect)) {
        $raw_redirect = '/' . $raw_redirect;
    }
    if (strpos($raw_redirect, '/') === 0 && strpos($raw_redirect, '//') !== 0 && strpos($raw_redirect, '\\') === false && !preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*:#', $raw_redirect)) {
        $_SESSION['redirect_after_login'] = $raw_redirect;
    }
}

// اگر قبلاً لاگین کرده باشد، بر اساس نقش یا مسیر بازگشت هدایت شود
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['redirect_after_login']) && ($_SESSION['role'] ?? '') !== 'data_operator') {
        $redirect = $_SESSION['redirect_after_login'];
        unset($_SESSION['redirect_after_login']);
        if (strpos($redirect, '/') === 0 && strpos($redirect, '//') !== 0 && strpos($redirect, '\\') === false && !preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*:#', $redirect)) {
            header("Location: " . $redirect);
            exit();
        }
    }
    if (in_array($_SESSION['role'] ?? '', ['superadmin', 'secretary', 'admin'])) {
        header("Location: admin/index.php");
    } elseif (($_SESSION['role'] ?? '') === 'data_operator') {
        header("Location: admin/library.php");
    } elseif (($_SESSION['role'] ?? '') === 'student') {
        header("Location: student-dashboard.php");
    } else {
        header("Location: donor-dashboard.php");
    }
    exit();
}

$error = '';
$success_msg = '';
$step = isset($_POST['step']) ? (int)$_POST['step'] : 1;
$reg_type = $_POST['reg_type'] ?? 'donor'; // 'donor' or 'student'
$phone = trim($_POST['phone'] ?? '');
$name = trim($_POST['name'] ?? '');
$surname = trim($_POST['surname'] ?? '');
$national_id = trim($_POST['national_id'] ?? '');

// تمیزکاری ارقام فارسی به انگلیسی
function normalizeNumbers($str) {
    $farsi = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹','٠','١','٢','٣','٤','٥','٦','٧','٨','٩'];
    $latin = ['0','1','2','3','4','5','6','7','8','9','0','1','2','3','4','5','6','7','8','9'];
    return str_replace($farsi, $latin, $str);
}

$phone = normalizeNumbers($phone);
$national_id = normalizeNumbers($national_id);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if ($step == 1) {
        if ($reg_type === 'student') {
            // ثبت‌نام یا فعال‌سازی حساب دانش‌آموز تحت پوشش
            if (empty($national_id) && empty($phone)) {
                $error = 'لطفاً کد ملی یا شماره همراه خود را وارد نمایید.';
            } else {
                $st_stmt = $pdo->prepare("SELECT * FROM students WHERE (national_id = ? AND national_id != '') OR (phone = ? AND phone != '') OR (guardian_phone = ? AND guardian_phone != '') LIMIT 1");
                $st_stmt->execute([$national_id, $phone, $phone]);
                $student = $st_stmt->fetch();

                if ($student) {
                    $_SESSION['reg_student_id'] = $student['id'];
                    $_SESSION['reg_student_name'] = $student['name'] . ' ' . $student['surname'];
                    $_SESSION['reg_type'] = 'student';
                    
                    $target_phone = $student['phone'] ?: ($student['guardian_phone'] ?: '');
                    $_SESSION['reg_phone'] = $target_phone;
                    $otp = (string)rand(1000, 9999);
                    $_SESSION['simulated_code'] = $otp;
                    
                    if (!empty($target_phone)) {
                        require_once __DIR__ . '/includes/SmsService.php';
                        $sms_service = new SmsService($pdo);
                        $sms_service->sendOtp($target_phone, $otp);
                    }
                    $step = 2;
                } else {
                    $error = 'دانش‌آموزی با این مشخصات در سامانه پرونده‌های بنیاد حکمت یافت نشد. در صورتی که تحت پوشش هستید، با دفتر بنیاد تماس بگیرید.';
                }
            }
        } else {
            // ثبت‌نام حامی و نیکوکار
            if (strlen($phone) < 10) {
                $error = 'لطفاً شماره همراه ۱۰ یا ۱۱ رقمی معتبر وارد نمایید.';
            } elseif (empty($name) || empty($surname)) {
                $error = 'لطفاً نام و نام خانوادگی خود را کامل وارد کنید.';
            } else {
                // بررسی اینکه شماره قبلاً در users ثبت شده یا خیر
                $chk_u = $pdo->prepare("SELECT id, role FROM users WHERE phone_number = ? AND phone_number != ''");
                $chk_u->execute([$phone]);
                if ($chk_u->fetch()) {
                    $error = 'این شماره همراه قبلاً در سامانه ثبت شده است. لطفاً از بخش ورود وارد شوید.';
                } else {
                    $_SESSION['reg_name'] = $name;
                    $_SESSION['reg_surname'] = $surname;
                    $_SESSION['reg_phone'] = $phone;
                    $_SESSION['reg_type'] = 'donor';
                    
                    $otp = (string)rand(1000, 9999);
                    $_SESSION['simulated_code'] = $otp;
                    
                    require_once __DIR__ . '/includes/SmsService.php';
                    $sms_service = new SmsService($pdo);
                    $sms_service->sendOtp($phone, $otp);
                    
                    $step = 2;
                }
            }
        }
    } elseif ($step == 2) {
        $code = normalizeNumbers(trim($_POST['code'] ?? ''));
        $saved_code = $_SESSION['simulated_code'] ?? '1234';

        if ($code === $saved_code || $code === '1234') {
            $reg_type = $_SESSION['reg_type'] ?? 'donor';

            if ($reg_type === 'student') {
                $student_id = (int)($_SESSION['reg_student_id'] ?? 0);
                $st_stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
                $st_stmt->execute([$student_id]);
                $student = $st_stmt->fetch();

                if ($student) {
                    $_SESSION['user_id'] = $student['id'];
                    $_SESSION['username'] = $student['national_id'] ?: $student['phone'];
                    $_SESSION['role'] = 'student';
                    $_SESSION['user_name'] = $student['name'] . ' ' . $student['surname'];
                    $_SESSION['related_id'] = $student['id'];
                    $_SESSION['is_admin'] = false;
                    $_SESSION['is_superadmin'] = false;

                    // ایجاد یا به‌روزرسانی یوزر سیستمی برای این دانش‌آموز
                    $ins = $pdo->prepare("INSERT OR IGNORE INTO users (username, phone_number, role, full_name, related_id) VALUES (?, ?, 'student', ?, ?)");
                    $ins->execute([$student['national_id'] ?: $student['phone'], $student['phone'] ?: '', $_SESSION['user_name'], $student['id']]);

                    log_activity('فعال‌سازی و ورود دانش‌آموز', 'student', $student['id'], "ورود اولیه دانش‌آموز {$_SESSION['user_name']} به پنل تحصیلی");

                    unset($_SESSION['simulated_code']);
                    unset($_SESSION['reg_student_id']);
                    unset($_SESSION['reg_student_name']);
                    unset($_SESSION['reg_type']);

                    if (isset($_SESSION['redirect_after_login'])) {
                        $redirect = $_SESSION['redirect_after_login'];
                        unset($_SESSION['redirect_after_login']);
                        if (strpos($redirect, '/') === 0 && strpos($redirect, '//') !== 0 && strpos($redirect, '\\') === false && !preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*:#', $redirect)) {
                            header("Location: " . $redirect);
                            exit();
                        }
                    }

                    header("Location: student-dashboard.php");
                    exit();
                } else {
                    $error = 'خطا در بارگذاری پرونده دانش‌آموز.';
                    $step = 1;
                }
            } else {
                // ثبت‌نام نیکوکار جدید
                $name = $_SESSION['reg_name'] ?? '';
                $surname = $_SESSION['reg_surname'] ?? '';
                $phone = $_SESSION['reg_phone'] ?? '';

                // بررسی وجود در جدول donors
                $chk_d = $pdo->prepare("SELECT id FROM donors WHERE phone = ?");
                $chk_d->execute([$phone]);
                $existing_donor = $chk_d->fetch();

                if ($existing_donor) {
                    $donor_id = $existing_donor['id'];
                } else {
                    $ins_d = $pdo->prepare("INSERT INTO donors (name, surname, phone, total_donated) VALUES (?, ?, ?, 0)");
                    $ins_d->execute([$name, $surname, $phone]);
                    $donor_id = $pdo->lastInsertId();
                }

                // ثبت در جدول users
                $ins_u = $pdo->prepare("INSERT INTO users (username, phone_number, role, full_name, related_id) VALUES (?, ?, 'benefactor', ?, ?)");
                $ins_u->execute([$phone, $phone, $name . ' ' . $surname, $donor_id]);
                $user_id = $pdo->lastInsertId();

                $_SESSION['user_id'] = $donor_id;
                $_SESSION['username'] = $phone;
                $_SESSION['role'] = 'benefactor';
                $_SESSION['user_name'] = $name . ' ' . $surname;
                $_SESSION['related_id'] = $donor_id;
                $_SESSION['is_admin'] = false;
                $_SESSION['is_superadmin'] = false;

                log_activity('ثبت‌نام نیکوکار جدید', 'donor', $donor_id, "ثبت‌نام نیکوکار {$name} {$surname} با شماره {$phone}");

                unset($_SESSION['simulated_code']);
                unset($_SESSION['reg_name']);
                unset($_SESSION['reg_surname']);
                unset($_SESSION['reg_phone']);
                unset($_SESSION['reg_type']);

                if (isset($_SESSION['redirect_after_login'])) {
                    $redirect = $_SESSION['redirect_after_login'];
                    unset($_SESSION['redirect_after_login']);
                    if (strpos($redirect, '/') === 0 && strpos($redirect, '//') !== 0 && strpos($redirect, '\\') === false && !preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*:#', $redirect)) {
                        header("Location: " . $redirect);
                        exit();
                    }
                }

                header("Location: donor-dashboard.php");
                exit();
            }
        } else {
            $error = 'کد تایید وارد شده اشتباه است. (برای آزمایشی: ۱۲۳۴)';
            $step = 2;
        }
    }
}

$page_title = 'عضویت در سامانه | بنیاد نیکوکاری حکمت';
$is_private_page = true;
?>
<!DOCTYPE html>
<html lang="fa-IR" dir="rtl">
<head>
    <?php include 'includes/head.php'; ?>
    <style>
        body { font-family: 'Vazirmatn', sans-serif; }
    </style>
</head>
<body class="bg-gray-900 min-h-[100dvh] flex items-center justify-center p-4 selection:bg-teal-500 selection:text-white">
    <div class="absolute inset-0 bg-gradient-to-br from-primary-900 via-gray-900 to-black opacity-90"></div>
    <div class="relative z-10 w-full max-w-md bg-white/10 backdrop-blur-2xl rounded-[3rem] shadow-2xl border border-white/10 overflow-hidden p-8 md:p-10 text-white">
        
        <!-- Logo & Title -->
        <div class="text-center mb-6">
            <div class="w-16 h-16 bg-white/10 rounded-2xl flex items-center justify-center text-3xl mx-auto mb-3 border border-white/10 shadow-lg">
                🤝
            </div>
            <h2 class="text-2xl font-black text-white">عضویت در پورتال حکمت</h2>
            <p class="text-xs text-teal-300 mt-1">سامانه خدمات دانش‌آموزان و همراهان بنیاد</p>
        </div>

        <?php if ($error): ?>
            <div class="bg-red-500/20 border border-red-500/40 text-red-200 p-4 rounded-2xl mb-6 text-xs text-center font-bold">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <?php if ($step == 1): ?>
            <!-- Type Selector Tabs -->
            <div class="flex bg-white/5 p-1 rounded-2xl mb-6 border border-white/10">
                <button type="button" onclick="setRegType('donor')" id="tab-donor" class="flex-1 py-2.5 rounded-xl text-xs font-black transition-all bg-teal-600 text-white shadow-md">
                    💎 نیکوکار و حامی
                </button>
                <button type="button" onclick="setRegType('student')" id="tab-student" class="flex-1 py-2.5 rounded-xl text-xs font-bold transition-all text-white/60 hover:text-white">
                    🎓 دانش‌آموز تحت پوشش
                </button>
            </div>

            <form method="POST" action="" id="reg-form">
                <input type="hidden" name="step" value="1">
                <input type="hidden" name="reg_type" id="reg_type" value="donor">

                <!-- Donor Fields -->
                <div id="donor-fields" class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="block text-[11px] font-bold text-white/80 mb-1">نام</label>
                            <input type="text" name="name" value="<?php echo htmlspecialchars($name); ?>"
                                class="w-full bg-white/5 border border-white/15 text-white rounded-2xl px-4 py-3 text-sm focus:border-teal-400 focus:outline-none transition-all font-bold">
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-white/80 mb-1">نام خانوادگی</label>
                            <input type="text" name="surname" value="<?php echo htmlspecialchars($surname); ?>"
                                class="w-full bg-white/5 border border-white/15 text-white rounded-2xl px-4 py-3 text-sm focus:border-teal-400 focus:outline-none transition-all font-bold">
                        </div>
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-white/80 mb-1">شماره همراه</label>
                        <input type="text" name="phone" value="<?php echo htmlspecialchars($phone); ?>" placeholder="0912..."
                            class="w-full bg-white/5 border border-white/15 text-white rounded-2xl px-4 py-3 text-sm focus:border-teal-400 focus:outline-none transition-all dir-ltr text-center font-bold" style="direction: ltr;">
                    </div>
                </div>

                <!-- Student Fields -->
                <div id="student-fields" class="space-y-4 hidden">
                    <div class="bg-teal-500/10 border border-teal-500/30 p-4 rounded-2xl text-[11px] leading-relaxed text-teal-200">
                        دانش‌پژوه گرامی؛ در صورتی که پرونده شما در بنیاد ثبت شده است، با وارد کردن کد ملی یا شماره همراه ثبت‌شده پرونده خود را فعال کنید.
                    </div>
                    <div>
                        <label class="block text-[11px] font-bold text-white/80 mb-1">کد ملی ۱۰ رقمی دانش‌آموز</label>
                        <input type="text" name="national_id" value="<?php echo htmlspecialchars($national_id); ?>" placeholder="مثلاً: 0123456789"
                            class="w-full bg-white/5 border border-white/15 text-white rounded-2xl px-4 py-3 text-sm focus:border-teal-400 focus:outline-none transition-all dir-ltr text-center font-bold" style="direction: ltr;" maxlength="10">
                    </div>
                </div>

                <button type="submit" class="w-full mt-6 bg-gradient-to-r from-teal-500 to-teal-700 hover:from-teal-400 hover:to-teal-600 text-white font-black py-4 rounded-2xl shadow-xl hover:shadow-teal-500/30 transition-all text-sm">
                    دریافت کد تایید و ادامه ←
                </button>
            </form>
        <?php else: ?>
            <!-- Step 2: Verification Code -->
            <form method="POST" action="">
                <input type="hidden" name="step" value="2">
                
                <?php if (!empty($_SESSION['reg_student_name'])): ?>
                    <div class="bg-teal-500/10 border border-teal-500/30 p-4 rounded-2xl text-center mb-6">
                        <span class="text-[10px] text-teal-300 font-bold block">پرونده دانش‌آموز شناسایی شد:</span>
                        <span class="text-base font-black text-white mt-1 block"><?php echo htmlspecialchars($_SESSION['reg_student_name']); ?></span>
                    </div>
                <?php endif; ?>

                <div class="mb-6">
                    <label class="block text-xs font-bold text-white/80 mb-2 text-center">
                        کد تایید پیامک‌شده
                        <?php if (!empty($_SESSION['reg_phone'])): ?>
                            <span class="block text-[11px] text-teal-300 font-mono font-normal mt-1 dir-ltr">(به شماره <?php echo htmlspecialchars($_SESSION['reg_phone']); ?>)</span>
                        <?php endif; ?>
                    </label>
                    <input type="text" name="code" required autofocus 
                        class="w-full text-center tracking-[1rem] bg-white/5 border border-white/15 text-white text-3xl font-black rounded-2xl px-5 py-4 focus:border-teal-400 focus:outline-none dir-ltr font-mono" 
                        style="direction: ltr;" maxlength="4" placeholder="----">
                    <p class="text-[10px] text-teal-300/70 mt-2 text-center">کد پیامکی ارسال شد (کد تستی سامانه: ۱۲۳۴)</p>
                </div>

                <button type="submit" class="w-full bg-gradient-to-r from-teal-500 to-teal-700 hover:from-teal-400 hover:to-teal-600 text-white font-black py-4 rounded-2xl shadow-xl hover:shadow-teal-500/30 transition-all text-sm">
                    تکمیل فعال‌سازی و ورود به پنل
                </button>
                <div class="mt-4 text-center">
                    <a href="register.php" class="text-xs text-white/50 hover:text-white transition-colors">← بازگشت و اصلاح اطلاعات</a>
                </div>
            </form>
        <?php endif; ?>

        <div class="mt-8 text-center border-t border-white/10 pt-4 flex justify-between items-center text-xs">
            <a href="login.php<?php echo !empty($_SESSION['redirect_after_login']) ? '?redirect=' . urlencode($_SESSION['redirect_after_login']) : ''; ?>" class="text-teal-300 hover:text-white font-bold transition-colors">قبلاً ثبت‌نام کرده‌اید؟ ورود</a>
            <a href="index.php" class="text-white/40 hover:text-white transition-colors">صفحه اصلی سایت</a>
        </div>
    </div>

    <script>
        function setRegType(type) {
            document.getElementById('reg_type').value = type;
            const donorFields = document.getElementById('donor-fields');
            const studentFields = document.getElementById('student-fields');
            const tabDonor = document.getElementById('tab-donor');
            const tabStudent = document.getElementById('tab-student');

            if (type === 'donor') {
                donorFields.classList.remove('hidden');
                studentFields.classList.add('hidden');
                tabDonor.classList.add('bg-teal-600', 'text-white', 'shadow-md');
                tabDonor.classList.remove('text-white/60');
                tabStudent.classList.remove('bg-teal-600', 'text-white', 'shadow-md');
                tabStudent.classList.add('text-white/60');
            } else {
                donorFields.classList.add('hidden');
                studentFields.classList.remove('hidden');
                tabStudent.classList.add('bg-teal-600', 'text-white', 'shadow-md');
                tabStudent.classList.remove('text-white/60');
                tabDonor.classList.remove('bg-teal-600', 'text-white', 'shadow-md');
                tabDonor.classList.add('text-white/60');
            }
        }
    </script>
</body>
</html>
