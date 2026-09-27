<?php
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
require_once __DIR__ . '/db.php';

/**
 * دریافت اطلاعات کاربر جاری
 */
function auth_user() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    return [
        'id' => $_SESSION['user_id'],
        'username' => $_SESSION['username'] ?? '',
        'full_name' => $_SESSION['user_name'] ?? 'کاربر',
        'role' => $_SESSION['role'] ?? '',
        'is_admin' => !empty($_SESSION['is_admin']),
        'is_superadmin' => !empty($_SESSION['is_superadmin'])
    ];
}

/**
 * بررسی اینکه آیا کاربر لاگین است یا خیر
 */
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

/**
 * بررسی دسترسی بر اساس نقش
 */
function has_role($roles) {
    if (!is_logged_in()) {
        return false;
    }
    if (!is_array($roles)) {
        $roles = [$roles];
    }
    $current_role = $_SESSION['role'] ?? '';
    return in_array($current_role, $roles, true);
}

/**
 * بررسی اینکه آیا کاربر جاری ویانا وحیدی (دارای نقش دوگانه دانش‌آموز و اپراتور دیتابیس) است یا خیر
 */
function is_viana() {
    if (!is_logged_in()) {
        return false;
    }
    $username = strtolower($_SESSION['username'] ?? '');
    $uid = (int)($_SESSION['user_id'] ?? 0);
    $related_id = (int)($_SESSION['related_id'] ?? 0);
    $student_id = (int)($_SESSION['student_id'] ?? 0);
    $role = $_SESSION['role'] ?? '';
    
    return (
        $username === 'viana' ||
        $username === '0950305588' ||
        $username === '09121110094' ||
        $uid === 226 ||
        $uid === 745 ||
        $related_id === 745 ||
        $student_id === 745 ||
        ($role === 'data_operator' && $username === 'viana')
    );
}

/**
 * آیا کاربر دسترسی مشاهده بخش‌ها، دفاتر و اسناد مالی را دارد؟
 * superadmin (فرید علمی)، secretary (خانم عباسی) و board_member (اعضای محترم هیئت مدیره - ناظر فقط خواندنی)
 * education_deputy (خانم فرتاش)، data_operator (ویانا وحیدی) و دانش‌آموزان به هیچ عنوان دسترسی ندارند.
 */
function can_view_financial() {
    return has_role(['superadmin', 'secretary', 'board_member', 'admin']);
}

/**
 * آیا کاربر دسترسی ویرایش یا ثبت تراکنش‌های مالی دارد؟
 * فقط superadmin (فرید علمی) و secretary (خانم عباسی).
 * اعضای هیئت مدیره (board_member) کاملاً فقط خواندنی (Read-Only) هستند.
 */
function can_edit_financial() {
    return has_role(['superadmin', 'secretary', 'admin']);
}

/**
 * بررسی دسترسی مالی (مشاهده دفاتر و اسناد)
 */
function can_access_financial() {
    return can_view_financial();
}

/**
 * آیا کاربر دسترسی مشاهده لاگ‌ها را دارد؟
 * فقط فرید علمی (superadmin)
 */
function can_view_audit_logs() {
    return has_role(['superadmin']);
}

/**
 * آیا کاربر دسترسی به پرونده و تست‌های روانشناسی را دارد؟
 * منحصراً فقط آقای فرید علمی (مدیرعامل / superadmin)
 * منشی، معاونت آموزش، اعضای هیئت مدیره، اپراتور دیتا و سایر کاربران به هیچ عنوان دسترسی ندارند.
 */
function can_view_psychology() {
    if (!is_logged_in()) {
        return false;
    }
    $username = strtolower($_SESSION['username'] ?? '');
    $role = $_SESSION['role'] ?? '';
    return ($username === 'faridelmi' || $role === 'superadmin');
}

/**
 * آیا کاربر دسترسی به مشاهده و ثبت یادداشت‌های پرونده را دارد؟
 * آقای فرید علمی (superadmin)، سرکار خانم عباسی (secretary) و سرکار خانم فرتاش (education_deputy).
 * اعضای هیئت مدیره، اپراتور دیتا، دانش‌آموزان و سایرین مجاز نیستند.
 */
function can_view_admin_notes() {
    return has_role(['superadmin', 'secretary', 'education_deputy']);
}

/**
 * آیا کاربر در حالت فقط خواندنی (Read-Only) قرار دارد؟
 * اعضای هیئت مدیره (board_member) کاملاً ناظر و بدون حق ویرایش هستند.
 */
function is_read_only() {
    return has_role(['board_member']);
}

function can_edit_records() {
    return !has_role(['board_member', 'student', 'benefactor']);
}

/**
 * آیا کاربر دسترسی افزودن مددجوی جدید به سیستم را دارد؟
 * منحصراً فقط superadmin (آقای فرید علمی) و secretary (سرکار خانم عباسی)
 */
function can_add_student() {
    return has_role(['superadmin', 'secretary', 'admin']);
}

/**
 * عنوان فارسی نقش سازمانی
 */
function get_role_title($role = null) {
    if (!$role) {
        $role = $_SESSION['role'] ?? '';
    }
    $titles = [
        'superadmin' => 'مدیرعامل بنیاد',
        'secretary' => 'منشی بنیاد',
        'education_deputy' => 'معاونت آموزش بنیاد',
        'board_member' => 'عضو هیئت مدیره (ناظر)',
        'data_operator' => 'اپراتور اطلاعات',
        'student' => 'دانش‌پژوه',
        'benefactor' => 'نیکوکار'
    ];
    return $titles[$role] ?? 'همکار بنیاد';
}

/**
 * مسدودسازی صفحه در صورت عدم لاگین
 */
function require_login() {
    if (!is_logged_in()) {
        header("Location: /login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
        exit();
    }
}

/**
 * الزام دسترسی به نقش خاص یا نمایش صفحه خطای ۴۰۳ راست‌چین
 */
function require_role($roles) {
    require_login();
    if (!has_role($roles)) {
        if (is_viana() && in_array('data_operator', (array)$roles, true)) {
            return;
        }
        render_access_denied_page("شما مجوز لازم برای ورود به این بخش را ندارید.");
        exit();
    }
}

/**
 * الزام دسترسی مالی (مسدودسازی کامل برای اپراتورهای دیتا/دانش‌آموز)
 */
function require_financial_access() {
    require_login();
    if (!can_access_financial()) {
        render_access_denied_page("بخش‌های مالی، بانکی و حسابداری بنیاد برای نقش شما غیرفعال و مسدود است.");
        exit();
    }
}

/**
 * ثبت فعالیت‌ها در جدول لاگ سیستم (Audit Logging)
 */
function log_activity($action, $target_type = '', $target_id = 0, $description = '', $custom_user = null) {
    global $pdo;
    try {
        $user_id = $custom_user['id'] ?? ($_SESSION['user_id'] ?? 0);
        $username = $custom_user['username'] ?? ($_SESSION['username'] ?? 'مهمان/سیستم');
        $user_name = $custom_user['user_name'] ?? ($_SESSION['user_name'] ?? 'مهمان/سیستم');
        $role = $custom_user['role'] ?? ($_SESSION['role'] ?? 'guest');
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        if (strpos($ip, ',') !== false) {
            $ip = trim(explode(',', $ip)[0]);
        }
        $created_at = date('Y-m-d H:i:s');

        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, username, user_name, role, action, target_type, target_id, description, ip_address, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $user_id,
            $username,
            $user_name,
            $role,
            $action,
            $target_type,
            (int)$target_id,
            $description,
            $ip,
            $created_at
        ]);
        return true;
    } catch (Exception $e) {
        // عدم توقف برنامه در صورت بروز خطای لاگ، ثبت در ارورلاگ
        error_log("خطا در ثبت لاگ: " . $e->getMessage());
        return false;
    }
}

/**
 * تولید نشان و بج گرافیکی فارسی برای نقش‌های مختلف کاربران
 */
function get_role_badge_html($role, $username = '') {
    if ($role === 'superadmin' || $username === 'faridelmi') {
        return '<span class="bg-amber-100 text-amber-900 border border-amber-300 px-2.5 py-0.5 rounded-full text-[10px] font-black inline-flex items-center gap-1">👑 مدیرعامل</span>';
    } elseif ($role === 'secretary' || $username === 'abbasi') {
        return '<span class="bg-purple-100 text-purple-900 border border-purple-300 px-2.5 py-0.5 rounded-full text-[10px] font-black inline-flex items-center gap-1">👩‍💼 منشی بنیاد</span>';
    } elseif ($role === 'education_deputy' || $username === 'fartash') {
        return '<span class="bg-blue-100 text-blue-900 border border-blue-300 px-2.5 py-0.5 rounded-full text-[10px] font-black inline-flex items-center gap-1">🎓 معاونت آموزش (خانم فرتاش)</span>';
    } elseif ($role === 'board_member') {
        return '<span class="bg-indigo-100 text-indigo-900 border border-indigo-300 px-2.5 py-0.5 rounded-full text-[10px] font-black inline-flex items-center gap-1">🏛️ هیئت مدیره</span>';
    } elseif ($role === 'data_operator' || $username === 'viana') {
        return '<span class="bg-teal-100 text-teal-900 border border-teal-300 px-2.5 py-0.5 rounded-full text-[10px] font-black inline-flex items-center gap-1">💻 اپراتور دیتابیس</span>';
    } elseif ($role === 'student') {
        return '<span class="bg-emerald-100 text-emerald-900 border border-emerald-300 px-2.5 py-0.5 rounded-full text-[10px] font-black inline-flex items-center gap-1">🎒 دانش‌آموز</span>';
    } elseif ($role === 'benefactor') {
        return '<span class="bg-cyan-100 text-cyan-900 border border-cyan-300 px-2.5 py-0.5 rounded-full text-[10px] font-black inline-flex items-center gap-1">💎 نیکوکار</span>';
    } elseif ($role === 'security') {
        return '<span class="bg-rose-100 text-rose-900 border border-rose-300 px-2.5 py-0.5 rounded-full text-[10px] font-black inline-flex items-center gap-1">🚨 هشدار امنیتی</span>';
    } else {
        return '<span class="bg-gray-100 text-gray-800 border border-gray-200 px-2.5 py-0.5 rounded-full text-[10px] font-bold inline-flex items-center gap-1">' . htmlspecialchars($role ?: 'کاربر') . '</span>';
    }
}

/**
 * رندر صفحه اختصاصی خطای ۴۰۳
 */
function render_access_denied_page($message = "شما به این صفحه دسترسی ندارید.") {
    http_response_code(403);
    $user_name = $_SESSION['user_name'] ?? 'کاربر مهمان';
    $role = $_SESSION['role'] ?? 'نامشخص';
    
    $role_farsi = [
        'superadmin' => 'مدیرعامل',
        'secretary' => 'منشی بنیاد',
        'data_operator' => 'اپراتور دیتابیس (دانش‌آموز)',
        'student' => 'دانش‌آموز',
        'benefactor' => 'نیکوکار'
    ][$role] ?? $role;

    ?>
    <!DOCTYPE html>
    <html lang="fa" dir="rtl">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>دسترسی غیرمجاز | بنیاد حکمت</title>
<style>
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:100;font-display:swap;src:url('/assets/fonts/Vazirmatn-100.woff2') format('woff2')}
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:300;font-display:swap;src:url('/assets/fonts/Vazirmatn-300.woff2') format('woff2')}
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:400;font-display:swap;src:url('/assets/fonts/Vazirmatn-400.woff2') format('woff2')}
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:500;font-display:swap;src:url('/assets/fonts/Vazirmatn-500.woff2') format('woff2')}
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:700;font-display:swap;src:url('/assets/fonts/Vazirmatn-700.woff2') format('woff2')}
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:900;font-display:swap;src:url('/assets/fonts/Vazirmatn-900.woff2') format('woff2')}
</style>
<link rel="stylesheet" href="/assets/tailwind.min.css">
<style>body { font-family: 'Vazirmatn', sans-serif; }</style>
    </head>
    <body class="bg-slate-950 text-slate-100 flex items-center justify-center min-h-screen p-4">
        <div class="max-w-md w-full bg-slate-900 border border-red-500/30 rounded-3xl p-8 text-center shadow-2xl space-y-6">
            <div class="w-20 h-20 bg-red-500/10 text-red-400 border border-red-500/20 rounded-2xl flex items-center justify-center text-4xl mx-auto">
                🔒
            </div>
            <div class="space-y-2">
                <h1 class="text-2xl font-black text-red-400">دسترسی مسدود است</h1>
                <p class="text-sm text-slate-300 leading-relaxed"><?php echo htmlspecialchars($message); ?></p>
            </div>
            <div class="bg-slate-800/80 rounded-2xl p-4 text-xs text-slate-400 space-y-2 text-right border border-white/5">
                <div class="flex justify-between items-center">
                    <span class="text-slate-500">کاربر لاگین‌شده:</span>
                    <span class="font-bold text-slate-200"><?php echo htmlspecialchars($user_name); ?></span>
                </div>
                <div class="flex justify-between items-center">
                    <span class="text-slate-500">سطح دسترسی شما:</span>
                    <span class="font-bold text-amber-400"><?php echo htmlspecialchars($role_farsi); ?></span>
                </div>
            </div>
            <div class="flex flex-col sm:flex-row gap-3">
                <?php if (is_viana() || $role === 'data_operator'): ?>
                    <a href="/admin/library.php" class="flex-1 bg-teal-600 hover:bg-teal-500 text-white font-bold py-3 px-4 rounded-xl text-xs transition-all shadow-lg text-center">
                        📚 ورود به مخزن کتابخانه و صف انتظار
                    </a>
                    <a href="/student-dashboard.php?student_id=745&tab=books" class="flex-1 bg-indigo-600 hover:bg-indigo-500 text-white font-bold py-3 px-4 rounded-xl text-xs transition-all shadow-lg text-center">
                        🎒 پورتال دانش‌آموزی ویانا
                    </a>
                <?php elseif ($role === 'student'): ?>
                    <a href="/student-dashboard.php?tab=books" class="flex-1 bg-teal-600 hover:bg-teal-500 text-white font-bold py-3 px-4 rounded-xl text-xs transition-all shadow-lg text-center">
                        🎒 بازگشت به پورتال دانش‌آموزی
                    </a>
                <?php else: ?>
                    <a href="/people-list.php" class="flex-1 bg-teal-600 hover:bg-teal-500 text-white font-bold py-3 px-4 rounded-xl text-xs transition-all shadow-lg text-center">
                        بازگشت به لیست دانش‌آموزان
                    </a>
                <?php endif; ?>
                <a href="/login.php?action=logout" class="bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold py-3 px-4 rounded-xl text-xs transition-all border border-white/10 text-center">
                    خروج
                </a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}
