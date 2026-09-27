<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';

// فقط مدیرعامل (فرید علمی) به این صفحه دسترسی دارد
require_role('superadmin');

// فیلترها و جستجو
$filter_user = trim($_GET['user'] ?? '');
$filter_role = trim($_GET['role'] ?? '');
$filter_action = trim($_GET['action_type'] ?? '');
$search = trim($_GET['q'] ?? '');

$where_clauses = ["1=1"];
$params = [];

if ($filter_user !== '') {
    $where_clauses[] = "username = ?";
    $params[] = $filter_user;
}

if ($filter_role !== '') {
    $where_clauses[] = "role = ?";
    $params[] = $filter_role;
}

if ($filter_action !== '') {
    $where_clauses[] = "action LIKE ?";
    $params[] = "%$filter_action%";
}

if ($search !== '') {
    $where_clauses[] = "(description LIKE ? OR user_name LIKE ? OR action LIKE ? OR ip_address LIKE ? OR username LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_sql = implode(" AND ", $where_clauses);

// آمار کلی
$total_logs = $pdo->query("SELECT COUNT(*) FROM audit_logs")->fetchColumn() ?: 0;
$today_str = date('Y-m-d');
$today_logs = $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE created_at LIKE '$today_str%'")->fetchColumn() ?: 0;
$board_staff_logs = $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE role IN ('superadmin', 'secretary', 'education_deputy', 'board_member')")->fetchColumn() ?: 0;
$student_donor_logs = $pdo->query("SELECT COUNT(*) FROM audit_logs WHERE role IN ('student', 'benefactor', 'data_operator')")->fetchColumn() ?: 0;

// دریافت لیست لاگ‌ها (حداکثر ۱۵۰ مورد آخر)
$query = "SELECT * FROM audit_logs WHERE $where_sql ORDER BY id DESC LIMIT 150";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$logs = $stmt->fetchAll();

// لیست هوشمند کاربران متمایز از جدول لاگ و کاربران فعال برای فیلتر
$user_list_query = "
    SELECT DISTINCT username, user_name, role 
    FROM audit_logs 
    WHERE username != '' AND username != 'سیستم'
    ORDER BY role ASC, user_name ASC
";
$user_list = $pdo->query($user_list_query)->fetchAll();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>رهگیری و لاگ سیستم (Audit Trail) | بنیاد حکمت</title>
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
                colors: {
                    primary: { 900: '#00141e', 800: '#115e59', 600: '#14b8a6' }
                }
            }
        }
    }
</script>
<style>
    body { font-family: 'Vazirmatn', sans-serif; }
</style>
</head>
<body class="bg-gray-50 text-gray-800 antialiased min-h-screen">

    <?php 
    $base_url = '../';
    include '../includes/dashboard-nav.php'; 
    ?>

    <main class="container mx-auto px-4 md:px-6 py-8">
        
        <!-- Header Banner -->
        <div class="bg-gradient-to-r from-primary-900 via-slate-900 to-teal-950 rounded-[3rem] p-8 md:p-12 text-white shadow-xl relative overflow-hidden mb-8">
            <div class="absolute -right-16 -top-16 w-64 h-64 bg-amber-500/10 rounded-full blur-3xl"></div>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 relative z-10">
                <div class="space-y-2">
                    <div class="inline-flex items-center gap-2 bg-amber-500/20 text-amber-300 border border-amber-500/30 px-3 py-1 rounded-full text-xs font-bold">
                        <span>🛡️</span> پایش امنیتی و نظارتی اختصاصی مدیرعامل
                    </div>
                    <h1 class="text-2xl md:text-3xl font-black">گزارش و رهگیری زنده فعالیت‌های سامانه (Audit Trail)</h1>
                    <p class="text-xs md:text-sm text-slate-300 max-w-2xl leading-relaxed">
                        مشاهده لحظه‌ای کلیه ورودها و خروج‌ها، ثبت و ویرایش پرونده‌ها، درخواست کتاب، بارگذاری کارنامه‌ها و تراکنش‌های بنیاد توسط تمام کاربران، مدیران، اعضای هیئت مدیره و دانش‌آموزان.
                    </p>
                </div>
                <div class="flex items-center gap-3">
                    <a href="index.php" class="bg-white/10 hover:bg-white/20 text-white text-xs font-bold py-3 px-5 rounded-2xl border border-white/15 transition-all flex items-center gap-2 shrink-0">
                        ← بازگشت به میز کار
                    </a>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-8">
            <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 bg-blue-50 text-blue-600 rounded-2xl flex items-center justify-center text-2xl shrink-0">📜</div>
                <div>
                    <div class="text-2xl font-black text-slate-900"><?php echo toFarsiDigits($total_logs); ?></div>
                    <div class="text-[11px] text-gray-400 font-bold">کل وقایع ثبت‌شده</div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center text-2xl shrink-0">⚡</div>
                <div>
                    <div class="text-2xl font-black text-emerald-700"><?php echo toFarsiDigits($today_logs); ?></div>
                    <div class="text-[11px] text-gray-400 font-bold">فعالیت‌های امروز</div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 bg-purple-50 text-purple-600 rounded-2xl flex items-center justify-center text-2xl shrink-0">🏛️</div>
                <div>
                    <div class="text-2xl font-black text-purple-700"><?php echo toFarsiDigits($board_staff_logs); ?></div>
                    <div class="text-[11px] text-gray-400 font-bold">مدیران و هیئت مدیره</div>
                </div>
            </div>
            <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm flex items-center gap-4">
                <div class="w-12 h-12 bg-teal-50 text-teal-600 rounded-2xl flex items-center justify-center text-2xl shrink-0">🎒</div>
                <div>
                    <div class="text-2xl font-black text-teal-700"><?php echo toFarsiDigits($student_donor_logs); ?></div>
                    <div class="text-[11px] text-gray-400 font-bold">دانش‌آموزان و نیکوکاران</div>
                </div>
            </div>
        </div>

        <!-- Filter & Search Controls -->
        <div class="bg-white rounded-3xl p-6 border border-gray-100 shadow-sm mb-8">
            <form method="GET" action="" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 items-end">
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-2">فیلتر کاربر</label>
                    <select name="user" class="w-full bg-gray-50 border border-gray-200 rounded-2xl px-4 py-3 text-xs font-bold focus:outline-none focus:border-teal-500">
                        <option value="">همه کاربران</option>
                        <?php foreach ($user_list as $u): ?>
                            <option value="<?php echo htmlspecialchars($u['username']); ?>" <?php echo $filter_user === $u['username'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($u['user_name'] ?: $u['username']); ?> (@<?php echo htmlspecialchars($u['username']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-2">فیلتر نقش سازمانی</label>
                    <select name="role" class="w-full bg-gray-50 border border-gray-200 rounded-2xl px-4 py-3 text-xs font-bold focus:outline-none focus:border-teal-500">
                        <option value="">همه نقش‌ها</option>
                        <option value="superadmin" <?php echo $filter_role === 'superadmin' ? 'selected' : ''; ?>>👑 مدیرعامل</option>
                        <option value="secretary" <?php echo $filter_role === 'secretary' ? 'selected' : ''; ?>>👩‍💼 منشی بنیاد</option>
                        <option value="education_deputy" <?php echo $filter_role === 'education_deputy' ? 'selected' : ''; ?>>🎓 معاونت آموزش (خانم فرتاش)</option>
                        <option value="board_member" <?php echo $filter_role === 'board_member' ? 'selected' : ''; ?>>🏛️ اعضای هیئت مدیره</option>
                        <option value="data_operator" <?php echo $filter_role === 'data_operator' ? 'selected' : ''; ?>>💻 اپراتور دیتابیس</option>
                        <option value="student" <?php echo $filter_role === 'student' ? 'selected' : ''; ?>>🎒 دانش‌آموزان</option>
                        <option value="benefactor" <?php echo $filter_role === 'benefactor' ? 'selected' : ''; ?>>💎 خیرین و نیکوکاران</option>
                        <option value="security" <?php echo $filter_role === 'security' ? 'selected' : ''; ?>>🚨 هشدارهای امنیتی</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-2">نوع عملیات</label>
                    <input type="text" name="action_type" value="<?php echo htmlspecialchars($filter_action); ?>" placeholder="مثلاً: ورود، ویرایش، کتاب، آپلود..."
                        class="w-full bg-gray-50 border border-gray-200 rounded-2xl px-4 py-3 text-xs focus:outline-none focus:border-teal-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 mb-2">جستجو در شرح تغییرات</label>
                    <input type="text" name="q" value="<?php echo htmlspecialchars($search); ?>" placeholder="جستجوی متن، آی‌پی، نام..."
                        class="w-full bg-gray-50 border border-gray-200 rounded-2xl px-4 py-3 text-xs focus:outline-none focus:border-teal-500">
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="flex-1 bg-teal-600 hover:bg-teal-700 text-white font-bold py-3 px-4 rounded-2xl text-xs transition-all shadow-md">
                        اعمال فیلتر
                    </button>
                    <?php if ($filter_user || $filter_role || $filter_action || $search): ?>
                        <a href="audit-logs.php" class="bg-gray-100 hover:bg-gray-200 text-gray-600 font-bold py-3 px-4 rounded-2xl text-xs transition-all flex items-center justify-center">
                            ✕
                        </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Logs Table -->
        <div class="bg-white rounded-[2.5rem] border border-gray-100 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-gray-100 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                <h3 class="font-black text-slate-900 text-base flex items-center gap-2">
                    <span>📋</span> آخرین رخدادهای ثبت‌شده در سیستم
                </h3>
                <div class="flex items-center gap-3">
                    <span class="text-xs text-gray-400 font-bold">نمایش <?php echo toFarsiDigits(count($logs)); ?> رخداد اخیر</span>
                    <button onclick="location.reload()" class="text-[11px] font-bold bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-1.5 rounded-xl transition-all flex items-center gap-1">
                        <span>🔄</span> بازخوانی زنده
                    </button>
                </div>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-right text-xs">
                    <thead>
                        <tr class="bg-gray-50/80 text-gray-400 font-bold border-b border-gray-100 uppercase">
                            <th class="py-4 px-5 text-center w-14">ردیف</th>
                            <th class="py-4 px-5">زمان و تاریخ</th>
                            <th class="py-4 px-5">کاربر و سمت</th>
                            <th class="py-4 px-5">عملیات انجام‌شده</th>
                            <th class="py-4 px-5">شناسه / مرجع</th>
                            <th class="py-4 px-5">شرح جزئیات رخداد</th>
                            <th class="py-4 px-5 text-center">آدرس IP</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 font-medium">
                        <?php if (empty($logs)): ?>
                            <tr>
                                <td colspan="7" class="py-16 text-center text-gray-400">
                                    <div class="text-4xl mb-3 opacity-30">🔍</div>
                                    <p class="font-bold text-sm">هیچ رخدادی با این شرایط یافت نشد.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($logs as $idx => $lg): ?>
                                <?php
                                $action_color = 'text-slate-800';
                                if (strpos($lg['action'], 'ورود') !== false) {
                                    $action_color = 'text-blue-600 font-bold';
                                } elseif (strpos($lg['action'], 'حذف') !== false || strpos($lg['action'], 'ناموفق') !== false) {
                                    $action_color = 'text-rose-600 font-bold';
                                } elseif (strpos($lg['action'], 'ویرایش') !== false || strpos($lg['action'], 'به‌روزرسانی') !== false) {
                                    $action_color = 'text-amber-700 font-bold';
                                } elseif (strpos($lg['action'], 'ثبت') !== false || strpos($lg['action'], 'آپلود') !== false || strpos($lg['action'], 'کتاب') !== false) {
                                    $action_color = 'text-emerald-700 font-bold';
                                }
                                ?>
                                <tr class="hover:bg-gray-50/80 transition-colors">
                                    <td class="py-4 px-5 text-center text-gray-400 font-bold"><?php echo toFarsiDigits($idx + 1); ?></td>
                                    <td class="py-4 px-5 whitespace-nowrap">
                                        <div class="font-black text-emerald-700 text-xs"><?php echo time_ago_fa($lg['created_at']); ?></div>
                                        <div class="text-[10px] text-gray-400 font-mono"><?php echo formatJalaliDateTime($lg['created_at']); ?></div>
                                    </td>
                                    <td class="py-4 px-5 whitespace-nowrap">
                                        <div class="flex items-center gap-2">
                                            <span class="font-black text-slate-800"><?php echo htmlspecialchars($lg['user_name'] ?: $lg['username']); ?></span>
                                            <?php echo get_role_badge_html($lg['role'], $lg['username']); ?>
                                        </div>
                                        <span class="text-[10px] text-gray-400 font-mono">@<?php echo htmlspecialchars($lg['username']); ?></span>
                                    </td>
                                    <td class="py-4 px-5 <?php echo $action_color; ?> whitespace-nowrap">
                                        <?php echo htmlspecialchars($lg['action']); ?>
                                    </td>
                                    <td class="py-4 px-5 whitespace-nowrap">
                                        <?php if ($lg['target_type'] === 'student' && $lg['target_id'] > 0): ?>
                                            <a href="../person-detail.php?id=<?php echo $lg['target_id']; ?>" target="_blank" class="text-teal-600 hover:text-teal-800 underline font-bold">
                                                دانش‌آموز #<?php echo toFarsiDigits($lg['target_id']); ?>
                                            </a>
                                        <?php elseif ($lg['target_type'] === 'donor' && $lg['target_id'] > 0): ?>
                                            <a href="../donor-detail.php?id=<?php echo $lg['target_id']; ?>" target="_blank" class="text-blue-600 hover:text-blue-800 underline font-bold">
                                                نیکوکار #<?php echo toFarsiDigits($lg['target_id']); ?>
                                            </a>
                                        <?php elseif ($lg['target_type'] === 'student_book_requests' || $lg['target_type'] === 'library_books'): ?>
                                            <a href="library.php" target="_blank" class="text-indigo-600 hover:text-indigo-800 underline font-bold">
                                                کتابخانه #<?php echo toFarsiDigits($lg['target_id']); ?>
                                            </a>
                                        <?php elseif (!empty($lg['target_type'])): ?>
                                            <span class="text-gray-500 font-mono text-[11px]"><?php echo htmlspecialchars($lg['target_type']); ?> #<?php echo toFarsiDigits($lg['target_id']); ?></span>
                                        <?php else: ?>
                                            <span class="text-gray-300">---</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-4 px-5 text-gray-700 leading-relaxed max-w-md">
                                        <?php echo htmlspecialchars($lg['description']); ?>
                                    </td>
                                    <td class="py-4 px-5 text-center text-gray-400 font-mono text-[11px]" dir="ltr">
                                        <?php echo htmlspecialchars($lg['ip_address'] ?: '127.0.0.1'); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

</body>
</html>
