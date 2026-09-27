<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/SmsService.php';

// دسترسی: مدیران و مسئولین مالی
require_financial_access();

$smsService = new SmsService($pdo);
$today_jalali = SmsService::getCurrentJalaliDate();
$settings = $smsService->getSettings();

// آمار کلی
$stats_active = (int)$pdo->query("SELECT COUNT(*) FROM donors WHERE reminder_active = 1")->fetchColumn();
$stmt_due = $pdo->prepare("SELECT COUNT(*) FROM donors WHERE reminder_active = 1 AND next_reminder_date IS NOT NULL AND TRIM(next_reminder_date) != '' AND next_reminder_date <= ?");
$stmt_due->execute([$today_jalali]);
$stats_due = (int)$stmt_due->fetchColumn();

$stats_sent = (int)$pdo->query("SELECT COUNT(*) FROM donor_sms_logs WHERE status = 'sent'")->fetchColumn();
$stats_failed = (int)$pdo->query("SELECT COUNT(*) FROM donor_sms_logs WHERE status != 'sent'")->fetchColumn();

// دریافت لیست کامل خیرین همراه با اطلاعات یادآوری
$donors_query = "
    SELECT id, name, surname, phone, sms_phone, reminder_active, 
           reminder_interval_months, reminder_day, reminder_channel, reminder_shares,
           next_reminder_date, last_reminder_date, 
           custom_sms_text, total_donated
    FROM donors
    ORDER BY reminder_active DESC, 
             CASE WHEN next_reminder_date IS NULL OR TRIM(next_reminder_date) = '' THEN 1 ELSE 0 END ASC,
             next_reminder_date ASC, 
             id DESC
";
$donors = $pdo->query($donors_query)->fetchAll(PDO::FETCH_ASSOC);

function getPersianOrdinalDay($d) {
    $d = (int)$d;
    $ordinals = [
        1 => 'اول', 2 => 'دوم', 3 => 'سوم', 4 => 'چهارم', 5 => 'پنجم',
        6 => 'ششم', 7 => 'هفتم', 8 => 'هشتم', 9 => 'نهم', 10 => 'دهم',
        11 => 'یازدهم', 12 => 'دوازدهم', 13 => 'سیزدهم', 14 => 'چهاردهم', 15 => 'پانزدهم',
        16 => 'شانزدهم', 17 => 'هفدهم', 18 => 'هجدهم', 19 => 'نوزدهم', 20 => 'بیستم',
        21 => 'بیست‌ویکم', 22 => 'بیست‌ودوم', 23 => 'بیست‌وسوم', 24 => 'بیست‌وچهارم', 25 => 'بیست‌وپنجم',
        26 => 'بیست‌وششم', 27 => 'بیست‌وهفتم', 28 => 'بیست‌وهشتم', 29 => 'بیست‌ونهم', 30 => 'سی‌ام', 31 => 'سی‌ویکم'
    ];
    return $ordinals[$d] ?? ($d . 'ام');
}

// دریافت ۱۰۰ لاگ اخیر پیامک
$logs_query = "
    SELECT l.*, d.name as donor_name, d.surname as donor_surname,
           COALESCE(u.full_name, u.username) as sent_by_name
    FROM donor_sms_logs l
    LEFT JOIN donors d ON l.donor_id = d.id
    LEFT JOIN users u ON l.sent_by_user_id = u.id
    ORDER BY l.id DESC
    LIMIT 100
";
$logs = $pdo->query($logs_query)->fetchAll(PDO::FETCH_ASSOC);

// آماده‌سازی برچسب‌های دوره
$interval_labels = [
    1 => 'ماهانه (هر ۱ ماه)',
    2 => 'هر ۲ ماه یک‌بار',
    3 => 'فصلی (هر ۳ ماه)',
    4 => 'هر ۴ ماه یک‌بار',
    6 => 'شش‌ماهه (هر ۶ ماه)',
    12 => 'سالانه (هر ۱۲ ماه)'
];
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سامانه یادآوری دوره‌ای خیرین | بنیاد حکمت</title>
    <style>
        @font-face{font-family:'Vazirmatn';font-style:normal;font-weight:100;font-display:swap;src:url('/assets/fonts/Vazirmatn-100.woff2') format('woff2')}
        @font-face{font-family:'Vazirmatn';font-style:normal;font-weight:300;font-display:swap;src:url('/assets/fonts/Vazirmatn-300.woff2') format('woff2')}
        @font-face{font-family:'Vazirmatn';font-style:normal;font-weight:400;font-display:swap;src:url('/assets/fonts/Vazirmatn-400.woff2') format('woff2')}
        @font-face{font-family:'Vazirmatn';font-style:normal;font-weight:500;font-display:swap;src:url('/assets/fonts/Vazirmatn-500.woff2') format('woff2')}
        @font-face{font-family:'Vazirmatn';font-style:normal;font-weight:700;font-display:swap;src:url('/assets/fonts/Vazirmatn-700.woff2') format('woff2')}
        @font-face{font-family:'Vazirmatn';font-style:normal;font-weight:900;font-display:swap;src:url('/assets/fonts/Vazirmatn-900.woff2') format('woff2')}
    </style>
    <link rel="stylesheet" href="/assets/tailwind.min.css">
    <script defer src="/assets/alpine.min.js"></script>
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
</head>
<body class="bg-gray-50 font-sans text-gray-800 antialiased min-h-screen pb-24" x-data="{ 
    activeTab: 'donors',
    searchQuery: '',
    statusFilter: 'all',
    showBatchModal: false,
    showEditModal: false,
    showAddModal: false,
    currentDonor: {},
    batchLoading: false,
    batchResult: null,
    settingsLoading: false,
    settingsAlert: null
}">

    <!-- Top Header -->
    <header class="bg-primary-900 text-white shadow-xl">
        <div class="container mx-auto px-6 py-6 flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 bg-teal-500/20 border border-teal-500/30 rounded-2xl flex items-center justify-center text-3xl shadow-inner">
                    🔔
                </div>
                <div>
                    <h1 class="text-2xl font-black flex items-center gap-3">
                        سامانه پیامک‌های دوره‌ای خیرین
                        <span class="text-xs px-3 py-1 bg-teal-500/20 text-teal-300 rounded-full font-bold border border-teal-500/30">اتوماسیون مالی</span>
                    </h1>
                    <p class="text-teal-200/70 text-xs mt-1">زمان‌بندی ماهانه، فصلی و سالانه یادآوری حمایت و گزارش‌دهی به نیکوکاران بنیاد حکمت</p>
                </div>
            </div>

            <div class="flex items-center gap-3 flex-wrap">
                <button @click="showAddModal = true" class="py-2.5 px-5 bg-teal-500 hover:bg-teal-600 text-white rounded-xl text-xs font-black shadow-lg shadow-teal-500/20 transition-all flex items-center gap-2">
                    <span>➕</span>
                    <span>ثبت نیکوکار جدید در یادآوری</span>
                </button>

                <button @click="showBatchModal = true" class="py-2.5 px-5 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-white rounded-xl text-xs font-black shadow-lg shadow-amber-500/20 transition-all flex items-center gap-2">
                    <span>🚀</span>
                    <span>ارسال دسته‌جمعی به سررسیدهای امروز (<?php echo $stats_due; ?>)</span>
                </button>

                <a href="../donors-list.php" class="py-2.5 px-4 bg-white/10 hover:bg-white/20 text-white rounded-xl text-xs font-bold transition-colors flex items-center gap-2">
                    <span>👥</span>
                    <span>پورتال نیکوکاران</span>
                </a>

                <a href="index.php" class="py-2.5 px-4 bg-white/10 hover:bg-white/20 text-white rounded-xl text-xs font-bold transition-colors flex items-center gap-2">
                    <span>🏠</span>
                    <span>میز مدیریت</span>
                </a>
            </div>
        </div>

        <!-- Navigation Tabs -->
        <div class="container mx-auto px-6 flex gap-3 border-t border-white/10 pt-3">
            <button @click="activeTab = 'donors'" :class="activeTab === 'donors' ? 'bg-white text-primary-900 border-b-2 border-teal-500 font-black' : 'text-white/70 hover:text-white font-bold'" class="py-3 px-6 rounded-t-2xl text-xs transition-all flex items-center gap-2">
                <span>📋</span>
                <span>کارتابل زمان‌بندی خیرین</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] bg-teal-100 text-teal-800 font-mono font-bold"><?php echo count($donors); ?></span>
            </button>

            <button @click="activeTab = 'logs'" :class="activeTab === 'logs' ? 'bg-white text-primary-900 border-b-2 border-teal-500 font-black' : 'text-white/70 hover:text-white font-bold'" class="py-3 px-6 rounded-t-2xl text-xs transition-all flex items-center gap-2">
                <span>📜</span>
                <span>تاریخچه و لاگ ارسال‌ها</span>
                <span class="px-2 py-0.5 rounded-full text-[10px] bg-indigo-100 text-indigo-800 font-mono font-bold"><?php echo count($logs); ?></span>
            </button>

            <button @click="activeTab = 'settings'" :class="activeTab === 'settings' ? 'bg-white text-primary-900 border-b-2 border-teal-500 font-black' : 'text-white/70 hover:text-white font-bold'" class="py-3 px-6 rounded-t-2xl text-xs transition-all flex items-center gap-2">
                <span>⚙️</span>
                <span>تنظیمات درگاه پیامک و کران‌جاب</span>
            </button>
        </div>
    </header>

    <main class="container mx-auto px-6 py-8">

        <!-- KPI Cards Row -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs text-gray-400 font-bold block mb-1">یادآوری‌های فعال</span>
                    <div class="text-3xl font-black text-gray-900 font-mono"><?php echo toFarsiDigits($stats_active); ?> <span class="text-xs font-bold text-gray-400">نیکوکار</span></div>
                </div>
                <div class="w-12 h-12 bg-teal-50 text-teal-600 rounded-2xl flex items-center justify-center text-2xl font-bold">
                    🔔
                </div>
            </div>

            <div class="bg-white p-6 rounded-3xl border border-amber-100 shadow-sm flex items-center justify-between <?php echo $stats_due > 0 ? 'ring-2 ring-amber-400/50 bg-amber-50/20' : ''; ?>">
                <div>
                    <span class="text-xs text-amber-700 font-bold block mb-1">سررسیدهای آماده ارسال امروز</span>
                    <div class="text-3xl font-black text-amber-600 font-mono"><?php echo toFarsiDigits($stats_due); ?> <span class="text-xs font-bold text-gray-400">نیکوکار</span></div>
                </div>
                <div class="w-12 h-12 bg-amber-100 text-amber-600 rounded-2xl flex items-center justify-center text-2xl font-bold">
                    ⚠️
                </div>
            </div>

            <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs text-gray-400 font-bold block mb-1">کل پیامک‌های ارسالی موفق</span>
                    <div class="text-3xl font-black text-emerald-600 font-mono"><?php echo toFarsiDigits($stats_sent); ?></div>
                </div>
                <div class="w-12 h-12 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center text-2xl font-bold">
                    ✓
                </div>
            </div>

            <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm flex items-center justify-between">
                <div>
                    <span class="text-xs text-gray-400 font-bold block mb-1">وضعیت درگاه پیامکی</span>
                    <div class="text-sm font-black text-gray-800 flex items-center gap-2 mt-1">
                        <?php if ($settings['provider'] === 'simulator'): ?>
                            <span class="w-2.5 h-2.5 rounded-full bg-blue-500 animate-pulse"></span>
                            <span>شبیه‌ساز (Simulator)</span>
                        <?php else: ?>
                            <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            <span class="capitalize"><?php echo htmlspecialchars($settings['provider']); ?></span>
                        <?php endif; ?>
                    </div>
                    <span class="text-[10px] text-gray-400 font-mono mt-1 block">امروز: <?php echo toFarsiDigits($today_jalali); ?></span>
                </div>
                <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center text-2xl font-bold">
                    📡
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- TAB 1: کارتابل خیرین و زمان‌بندی پیامک‌ها -->
        <!-- ========================================================================= -->
        <div x-show="activeTab === 'donors'" class="space-y-6">
            <!-- Filter & Search Toolbar -->
            <div class="bg-white p-5 rounded-3xl border border-gray-100 shadow-sm flex flex-col md:flex-row justify-between items-center gap-4">
                <!-- Search -->
                <div class="relative w-full md:w-96">
                    <input type="text" x-model="searchQuery" placeholder="جستجوی نام یا شماره همراه نیکوکار..." class="w-full bg-gray-50 border border-gray-200 rounded-2xl pr-11 pl-4 py-3 text-xs font-bold text-gray-800 focus:outline-none focus:border-teal-500">
                    <span class="absolute right-4 top-3 text-gray-400">🔍</span>
                </div>

                <!-- Status Filter Tabs -->
                <div class="flex items-center gap-2 p-1 bg-gray-100 rounded-2xl w-full md:w-auto overflow-x-auto">
                    <button @click="statusFilter = 'all'" :class="statusFilter === 'all' ? 'bg-white shadow text-primary-900 font-black' : 'text-gray-500 font-bold'" class="py-2 px-4 rounded-xl text-xs transition-all">همه خیرین</button>
                    <button @click="statusFilter = 'due'" :class="statusFilter === 'due' ? 'bg-amber-500 text-white shadow font-black' : 'text-gray-500 font-bold'" class="py-2 px-4 rounded-xl text-xs transition-all flex items-center gap-1.5">
                        <span>سررسیدشده‌ها</span>
                        <span class="px-1.5 py-0.2 rounded-full text-[10px] bg-white/30 text-white font-mono"><?php echo $stats_due; ?></span>
                    </button>
                    <button @click="statusFilter = 'active'" :class="statusFilter === 'active' ? 'bg-white shadow text-teal-700 font-black' : 'text-gray-500 font-bold'" class="py-2 px-4 rounded-xl text-xs transition-all">دارای یادآوری فعال</button>
                    <button @click="statusFilter = 'inactive'" :class="statusFilter === 'inactive' ? 'bg-white shadow text-gray-700 font-black' : 'text-gray-500 font-bold'" class="py-2 px-4 rounded-xl text-xs transition-all">غیرفعال</button>
                </div>
            </div>

            <!-- Donors Table -->
            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-right text-xs">
                        <thead>
                            <tr class="bg-gray-50/80 text-gray-400 border-b border-gray-100 text-[11px] font-bold">
                                <th class="p-4 pr-6">نیکوکار</th>
                                <th class="p-4">شماره همراه و درگاه</th>
                                <th class="p-4 text-center">وضعیت یادآوری</th>
                                <th class="p-4">زمان‌بندی سررسید</th>
                                <th class="p-4">موعد بعدی</th>
                                <th class="p-4">آخرین ارسال</th>
                                <th class="p-4 text-center">عملیات هوشمند</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($donors as $d): 
                                $phone_display = !empty($d['sms_phone']) ? $d['sms_phone'] : $d['phone'];
                                $clean_phone = SmsService::cleanPhoneNumber($phone_display);
                                $is_active = (bool)$d['reminder_active'];
                                $is_due = ($is_active && !empty($d['next_reminder_date']) && $d['next_reminder_date'] <= $today_jalali);
                                $interval = (int)($d['reminder_interval_months'] ?: 1);
                                $reminder_day = (int)($d['reminder_day'] ?: 1);
                                $channel = $d['reminder_channel'] ?: 'sms';
                                $shares = (int)($d['reminder_shares'] ?: 1);
                                $day_text = getPersianOrdinalDay($reminder_day);
                            ?>
                            <tr class="hover:bg-gray-50/60 transition-colors donor-row"
                                id="donor-row-<?php echo $d['id']; ?>"
                                data-name="<?php echo htmlspecialchars($d['name'] . ' ' . $d['surname']); ?>"
                                data-phone="<?php echo htmlspecialchars($phone_display); ?>"
                                data-active="<?php echo $is_active ? '1' : '0'; ?>"
                                data-due="<?php echo $is_due ? '1' : '0'; ?>"
                                x-show="(statusFilter === 'all' || 
                                        (statusFilter === 'due' && '<?php echo $is_due ? '1' : '0'; ?>' === '1') || 
                                        (statusFilter === 'active' && '<?php echo $is_active ? '1' : '0'; ?>' === '1') || 
                                        (statusFilter === 'inactive' && '<?php echo $is_active ? '1' : '0'; ?>' === '0')) &&
                                        ('<?php echo htmlspecialchars($d['name'] . ' ' . $d['surname']); ?>'.toLowerCase().includes(searchQuery.toLowerCase()) || 
                                         '<?php echo htmlspecialchars($phone_display); ?>'.includes(searchQuery))">
                                
                                <td class="p-4 pr-6">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-xl bg-teal-50 text-teal-800 font-black flex items-center justify-center text-sm shrink-0 border border-teal-100">
                                            <?php echo mb_substr($d['name'], 0, 1, 'utf-8'); ?>
                                        </div>
                                        <div>
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <a href="../donor-detail.php?id=<?php echo $d['id']; ?>" class="font-black text-gray-900 hover:text-teal-600 transition-colors text-xs block">
                                                    <?php echo htmlspecialchars($d['name'] . ' ' . $d['surname']); ?>
                                                </a>
                                                <?php if ($shares > 1): ?>
                                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black bg-purple-50 text-purple-700 border border-purple-200">
                                                        حامی <?php echo toFarsiDigits($shares); ?> دانش‌آموز
                                                    </span>
                                                <?php else: ?>
                                                    <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-gray-50 text-gray-500 border border-gray-200">
                                                        ۱ دانش‌آموز
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <span class="text-[10px] text-gray-400 font-mono mt-0.5 block">شناسه: <?php echo $d['id']; ?></span>
                                        </div>
                                    </div>
                                </td>

                                <td class="p-4">
                                    <div class="space-y-1">
                                        <?php if ($clean_phone): ?>
                                            <span class="font-mono font-bold text-gray-700 dir-ltr text-right inline-block bg-gray-50 px-2.5 py-1 rounded-lg border border-gray-200 text-xs">
                                                <?php echo htmlspecialchars($clean_phone); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-rose-500 font-bold text-[11px] flex items-center gap-1">
                                                <span>⚠️</span> فاقد شماره معتبر
                                            </span>
                                        <?php endif; ?>
                                        
                                        <div>
                                            <?php if ($channel === 'whatsapp'): ?>
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-black bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                    <span>💬</span> واتس‌اپ
                                                </span>
                                            <?php elseif ($channel === 'call'): ?>
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-black bg-amber-50 text-amber-700 border border-amber-200">
                                                    <span>📞</span> تماس تلفنی
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-black bg-blue-50 text-blue-700 border border-blue-200">
                                                    <span>📱</span> پیامک SMS
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>

                                <td class="p-4 text-center">
                                    <div class="flex flex-col items-center justify-center gap-1">
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" 
                                                   onchange="toggleDonorActive(<?php echo $d['id']; ?>, this.checked)"
                                                   <?php echo $is_active ? 'checked' : ''; ?> 
                                                   class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-teal-600"></div>
                                        </label>
                                        <span class="text-[10px] font-bold <?php echo $is_active ? 'text-teal-700' : 'text-gray-400'; ?>" id="status-text-<?php echo $d['id']; ?>">
                                            <?php echo $is_active ? 'فعال' : 'غیرفعال'; ?>
                                        </span>
                                    </div>
                                </td>

                                <td class="p-4">
                                    <div class="space-y-0.5">
                                        <div class="font-black text-gray-900 text-xs">
                                            <?php if ($interval === 1): ?>
                                                <?php echo $day_text; ?> هر ماه
                                            <?php elseif ($interval === 3): ?>
                                                <?php echo $day_text; ?> هر ۳ ماه (فصلی)
                                            <?php else: ?>
                                                <?php echo $day_text; ?> هر <?php echo toFarsiDigits($interval); ?> ماه
                                            <?php endif; ?>
                                        </div>
                                        <div class="text-[10px] text-gray-400 font-mono">
                                            روز <?php echo toFarsiDigits($reminder_day); ?>ام • <?php echo $interval_labels[$interval] ?? ($interval . ' ماهه'); ?>
                                        </div>
                                    </div>
                                </td>

                                <td class="p-4">
                                    <?php if ($is_due): ?>
                                        <span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-xl text-[11px] font-black bg-amber-100 text-amber-900 border border-amber-300 animate-pulse shadow-sm">
                                            <span>⚠️ سررسید:</span>
                                            <span class="font-mono"><?php echo toFarsiDigits($d['next_reminder_date']); ?></span>
                                        </span>
                                    <?php elseif (!empty($d['next_reminder_date'])): ?>
                                        <span class="font-mono font-bold text-gray-700 bg-gray-50 px-2.5 py-1 rounded-lg border border-gray-200 inline-block text-xs" id="next-date-<?php echo $d['id']; ?>">
                                            <?php echo toFarsiDigits($d['next_reminder_date']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-gray-400 text-[11px]" id="next-date-<?php echo $d['id']; ?>">تعیین‌نشده</span>
                                    <?php endif; ?>
                                </td>

                                <td class="p-4 text-gray-500 font-mono text-[11px]">
                                    <?php echo !empty($d['last_reminder_date']) ? toFarsiDigits($d['last_reminder_date']) : '---'; ?>
                                </td>

                                <td class="p-4 text-center">
                                    <div class="flex items-center justify-center gap-1.5 flex-wrap">
                                        <!-- ارسال آنی -->
                                        <?php if ($channel === 'whatsapp'): ?>
                                            <button onclick="sendInstantWhatsApp(<?php echo $d['id']; ?>, '<?php echo htmlspecialchars($d['name'] . ' ' . $d['surname']); ?>')" title="ارسال پیام در واتس‌اپ و ثبت سررسید بعدی" class="py-1.5 px-3 bg-emerald-50 hover:bg-emerald-600 hover:text-white text-emerald-700 border border-emerald-200 rounded-xl text-xs font-black transition-all flex items-center gap-1 shadow-sm">
                                                <span>💬</span>
                                                <span>ارسال واتس‌اپ</span>
                                            </button>
                                        <?php else: ?>
                                            <button onclick="sendInstantReminder(<?php echo $d['id']; ?>, '<?php echo htmlspecialchars($d['name'] . ' ' . $d['surname']); ?>')" title="ارسال آنی پیامک رسمی و بروزرسانی نوبت سررسید بعدی" class="py-1.5 px-3 bg-gradient-to-r from-teal-500 to-emerald-600 hover:from-teal-600 hover:to-emerald-700 text-white rounded-xl text-xs font-black transition-all flex items-center gap-1 shadow-sm">
                                                <span>⚡</span>
                                                <span>ارسال آنی</span>
                                            </button>
                                        <?php endif; ?>

                                        <!-- تست پیامک -->
                                        <button onclick="sendSingleTest(<?php echo $d['id']; ?>, '<?php echo htmlspecialchars($d['name'] . ' ' . $d['surname']); ?>')" title="ارسال پیامک آزمایشی بدون جابجایی نوبت سررسید" class="py-1.5 px-2.5 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-xl text-xs font-bold transition-all flex items-center gap-1">
                                            <span>📱</span>
                                            <span>تست</span>
                                        </button>

                                        <!-- تنظیمات کامل -->
                                        <button @click='openEditModal(<?php echo json_encode([
                                            'id' => $d['id'],
                                            'name' => $d['name'] . ' ' . $d['surname'],
                                            'phone' => $phone_display,
                                            'active' => $is_active ? 1 : 0,
                                            'interval' => $interval,
                                            'day' => $reminder_day,
                                            'channel' => $channel,
                                            'shares' => $shares,
                                            'next_date' => $d['next_reminder_date'] ?? '',
                                            'custom_text' => $d['custom_sms_text'] ?? ''
                                        ]); ?>)' title="تنظیم پیشرفته زمان‌بندی و کانال" class="py-1.5 px-2.5 bg-gray-50 hover:bg-gray-100 text-gray-700 border border-gray-200 rounded-xl text-xs font-bold transition-all flex items-center gap-1">
                                            <span>✏️</span>
                                            <span>تنظیم</span>
                                        </button>

                                        <!-- مشاهده پرونده -->
                                        <a href="../donor-detail.php?id=<?php echo $d['id']; ?>" title="مشاهده پرونده خیر" class="py-1.5 px-2 bg-gray-50 hover:bg-gray-100 text-gray-600 border border-gray-200 rounded-xl text-xs font-bold transition-all">
                                            👤
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- TAB 2: تاریخچه و لاگ ارسال‌های پیامک -->
        <!-- ========================================================================= -->
        <div x-show="activeTab === 'logs'" class="space-y-6">
            <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm flex justify-between items-center">
                <div>
                    <h3 class="text-base font-black text-gray-900">تاریخچه پیامک‌های ارسال‌شده به خیرین</h3>
                    <p class="text-xs text-gray-500 mt-1">نمایش ۱۰۰ پیامک اخیر ثبت‌شده در سیستم همراه با وضعیت و پاسخ درگاه</p>
                </div>
                <span class="text-xs font-bold px-3 py-1 bg-gray-100 text-gray-600 rounded-xl">کل ثبت‌شده: <?php echo toFarsiDigits(count($logs)); ?></span>
            </div>

            <div class="bg-white rounded-3xl border border-gray-100 shadow-sm overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-right text-xs">
                        <thead>
                            <tr class="bg-gray-50/80 text-gray-400 border-b border-gray-100 text-[11px] font-bold">
                                <th class="p-4 pr-6">شناسه / تاریخ</th>
                                <th class="p-4">نیکوکار</th>
                                <th class="p-4">شماره مقصد</th>
                                <th class="p-4 w-1/3">متن پیامک</th>
                                <th class="p-4">ارسال‌کننده</th>
                                <th class="p-4 text-center">وضعیت</th>
                                <th class="p-4">پاسخ وب‌سرویس</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="7" class="p-8 text-center text-gray-400 font-bold text-xs">
                                        تاکنون هیچ پیامکی در سیستم ثبت نشده است.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $l): ?>
                                <tr class="hover:bg-gray-50/60 transition-colors">
                                    <td class="p-4 pr-6">
                                        <span class="font-mono font-bold text-gray-800 text-xs block">#<?php echo $l['id']; ?></span>
                                        <span class="text-[10px] text-gray-400 font-mono"><?php echo toFarsiDigits($l['sent_date']); ?></span>
                                    </td>
                                    <td class="p-4 font-bold text-gray-900">
                                        <?php if (!empty($l['donor_name'])): ?>
                                            <a href="../donor-detail.php?id=<?php echo $l['donor_id']; ?>" class="hover:text-teal-600">
                                                <?php echo htmlspecialchars($l['donor_name'] . ' ' . $l['donor_surname']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-gray-400">بدون پرونده (تست مستقیم)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4 font-mono font-bold text-gray-700 dir-ltr text-right">
                                        <?php echo htmlspecialchars($l['phone']); ?>
                                    </td>
                                    <td class="p-4 leading-relaxed text-gray-700 max-w-sm">
                                        <div class="bg-gray-50 p-3 rounded-xl border border-gray-200/70 text-[11px] whitespace-pre-line select-all">
                                            <?php echo htmlspecialchars($l['message']); ?>
                                        </div>
                                    </td>
                                    <td class="p-4 text-gray-600 font-bold">
                                        <?php echo htmlspecialchars($l['sent_by_name'] ?: 'کران‌جاب / خودکار'); ?>
                                    </td>
                                    <td class="p-4 text-center">
                                        <?php if ($l['status'] === 'sent'): ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-black bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                ✓ ارسال موفق
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-black bg-rose-50 text-rose-700 border border-rose-200">
                                                ✕ خطا
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4 font-mono text-[10px] text-gray-400 max-w-xs truncate" title="<?php echo htmlspecialchars($l['provider_response'] ?? ''); ?>">
                                        <?php echo htmlspecialchars($l['provider_response'] ?? '---'); ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- ========================================================================= -->
        <!-- TAB 3: تنظیمات درگاه پیامک و قالب‌ها -->
        <!-- ========================================================================= -->
        <div x-show="activeTab === 'settings'" class="space-y-8">
            <!-- Settings Form Card -->
            <div class="bg-white p-8 rounded-3xl border border-gray-100 shadow-sm">
                <div class="flex items-center gap-4 mb-6 pb-6 border-b border-gray-100">
                    <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-2xl">
                        ⚙️
                    </div>
                    <div>
                        <h2 class="text-xl font-black text-gray-900">پیکربندی درگاه پیامک و قالب استاندارد</h2>
                        <p class="text-xs text-gray-500 mt-1">تنظیم وب‌سرویس پیامکی فعال و متن پیش‌فرض پیامک‌های ارسالی به خیرین</p>
                    </div>
                </div>

                <!-- Alert Box -->
                <div x-show="settingsAlert" :class="settingsAlert?.type === 'success' ? 'bg-emerald-50 text-emerald-800 border-emerald-200' : 'bg-rose-50 text-rose-800 border-rose-200'" class="p-4 rounded-2xl text-xs font-bold mb-6 border" x-text="settingsAlert?.message"></div>

                <form id="smsSettingsForm" @submit.prevent="saveSmsSettings" class="space-y-6">
                    <input type="hidden" name="action" value="save_sms_settings">

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <!-- Provider Selection -->
                        <div class="space-y-2">
                            <label class="text-xs font-black text-gray-800 block">انتخاب ارائه‌دهنده وب‌سرویس</label>
                            <select name="provider" id="setting_provider" class="w-full bg-gray-50 border border-gray-200 rounded-2xl px-4 py-3 text-xs font-bold text-gray-800 focus:outline-none focus:border-teal-500">
                                <option value="melipayamak" <?php echo ($settings['provider'] === 'melipayamak') ? 'selected' : ''; ?>>ملی‌پیامک (MeliPayamak Rest API)</option>
                                <option value="simulator" <?php echo ($settings['provider'] === 'simulator') ? 'selected' : ''; ?>>شبیه‌ساز هوشمند داخلی (Sandbox / رایگان)</option>
                                <option value="kavenegar" <?php echo ($settings['provider'] === 'kavenegar') ? 'selected' : ''; ?>>کاوه‌نگار (Kavenegar API)</option>
                                <option value="farapayamak" <?php echo ($settings['provider'] === 'farapayamak') ? 'selected' : ''; ?>>فراپیامک (FaraPayamak)</option>
                            </select>
                            <span class="text-[10px] text-gray-400 block">پیش‌فرض روی ملی‌پیامک فعال است.</span>
                        </div>

                        <!-- Username -->
                        <div class="space-y-2">
                            <label class="text-xs font-black text-gray-800 block">نام کاربری ملی‌پیامک</label>
                            <input type="text" name="username" id="setting_username" value="<?php echo htmlspecialchars($settings['username'] ?? '9153103060'); ?>" placeholder="مثلاً: 9153103060" dir="ltr" class="w-full bg-gray-50 border border-gray-200 rounded-2xl px-4 py-3 text-xs font-mono font-bold focus:outline-none focus:border-teal-500 text-left">
                            <span class="text-[10px] text-gray-400 block">شماره موبایل یا یوزر ورود به پنل</span>
                        </div>

                        <!-- API Key -->
                        <div class="space-y-2">
                            <label class="text-xs font-black text-gray-800 block">رمز عبور یا کلید دسترسی (API Key)</label>
                            <input type="text" name="api_key" id="setting_api_key" value="<?php echo htmlspecialchars($settings['api_key'] ?? ''); ?>" placeholder="کلید وب‌سرویس ملی‌پیامک" dir="ltr" class="w-full bg-gray-50 border border-gray-200 rounded-2xl px-4 py-3 text-xs font-mono font-bold focus:outline-none focus:border-teal-500 text-left">
                            <span class="text-[10px] text-gray-400 block">کلید API یا رمز وب‌سرویس</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2">
                        <!-- Sender Number -->
                        <div class="space-y-2">
                            <label class="text-xs font-black text-gray-800 block">شماره خط فرستنده</label>
                            <input type="text" name="sender_number" id="setting_sender" value="<?php echo htmlspecialchars($settings['sender_number'] ?? '50004001103060'); ?>" placeholder="50004001103060" dir="ltr" class="w-full bg-gray-50 border border-gray-200 rounded-2xl px-4 py-3 text-xs font-mono font-bold focus:outline-none focus:border-teal-500 text-left">
                            <span class="text-[10px] text-gray-400 block">شماره سرشماره پیامکی</span>
                        </div>

                        <!-- Pattern ID (BaseServiceNumber) -->
                        <div class="space-y-2">
                            <label class="text-xs font-black text-gray-800 block">کد الگوی وب‌سرویس خدماتی (Pattern BodyId)</label>
                            <input type="text" name="pattern_id" id="setting_pattern_id" value="<?php echo htmlspecialchars($settings['pattern_id'] ?? ''); ?>" placeholder="اختیاری: مثلاً 12345" dir="ltr" class="w-full bg-gray-50 border border-gray-200 rounded-2xl px-4 py-3 text-xs font-mono font-bold focus:outline-none focus:border-teal-500 text-left">
                            <span class="text-[10px] text-gray-400 block">جهت ارسال فوق‌سریع و عبور قطعی از بلک‌لیست مخابرات</span>
                        </div>
                    </div>

                    <!-- Default Template -->
                    <div class="space-y-2 pt-4 border-t border-gray-100">
                        <div class="flex justify-between items-center">
                            <label class="text-xs font-black text-gray-800 block">قالب پیش‌فرض پیامک یادآوری دوره‌ای</label>
                            <span class="text-[11px] text-teal-600 font-bold">برای تمام خیرینی که متن اختصاصی ندارند اعمال می‌شود</span>
                        </div>
                        <textarea name="default_template" id="setting_template" rows="5" class="w-full bg-gray-50 border border-gray-200 rounded-2xl p-4 text-xs font-sans leading-relaxed text-gray-800 focus:outline-none focus:border-teal-500"><?php echo htmlspecialchars($settings['default_template'] ?? ''); ?></textarea>
                        
                        <div class="p-4 bg-indigo-50/60 rounded-2xl border border-indigo-100">
                            <span class="text-xs font-black text-indigo-900 block mb-2">📌 متغیرهای هوشمند قابل استفاده در متن پیامک:</span>
                            <div class="flex flex-wrap gap-2 text-[11px] font-mono font-bold text-indigo-700">
                                <span class="bg-white px-2.5 py-1 rounded-lg border border-indigo-200 cursor-pointer" onclick="insertTag('{name}')">{name} (نام نیکوکار)</span>
                                <span class="bg-white px-2.5 py-1 rounded-lg border border-indigo-200 cursor-pointer" onclick="insertTag('{interval}')">{interval} (دوره تکرار)</span>
                                <span class="bg-white px-2.5 py-1 rounded-lg border border-indigo-200 cursor-pointer" onclick="insertTag('{card_number}')">{card_number} (شماره کارت حکمت)</span>
                                <span class="bg-white px-2.5 py-1 rounded-lg border border-indigo-200 cursor-pointer" onclick="insertTag('{link}')">{link} (لینک پورتال)</span>
                                <span class="bg-white px-2.5 py-1 rounded-lg border border-indigo-200 cursor-pointer" onclick="insertTag('{today}')">{today} (تاریخ امروز)</span>
                                <span class="bg-white px-2.5 py-1 rounded-lg border border-indigo-200 cursor-pointer" onclick="insertTag('{total_donated}')">{total_donated} (مجموع حمایت‌ها)</span>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="pt-4 flex justify-end">
                        <button type="submit" :disabled="settingsLoading" class="py-3 px-8 bg-teal-600 hover:bg-teal-700 text-white rounded-2xl text-xs font-black shadow-lg shadow-teal-500/20 transition-all flex items-center gap-2">
                            <span>💾</span>
                            <span x-text="settingsLoading ? 'در حال ذخیره‌سازی...' : 'ذخیره تنظیمات درگاه پیامک'"></span>
                        </button>
                    </div>
                </form>
            </div>

            <!-- Cron Job Instruction Card -->
            <div class="bg-slate-900 text-slate-100 p-8 rounded-3xl border border-slate-800 shadow-xl">
                <div class="flex items-center gap-4 mb-4">
                    <div class="w-12 h-12 rounded-2xl bg-amber-500/20 text-amber-400 flex items-center justify-center text-2xl border border-amber-500/30">
                        ⏱️
                    </div>
                    <div>
                        <h3 class="text-base font-black text-white">تنظیم ارسال خودکار روزانه (Crontab Server)</h3>
                        <p class="text-xs text-slate-400 mt-1">جهت بررسی و ارسال کاملاً خودکار سررسیدها در رأس ساعت مشخص در روز</p>
                    </div>
                </div>

                <p class="text-xs text-slate-300 leading-relaxed mb-4">
                    دستور زیر را در <code class="bg-slate-800 px-2 py-0.5 rounded text-amber-300 font-mono">crontab -e</code> سرور لینوکس قرار دهید تا هر روز ساعت ۱۰:۰۰ صبح سررسیدها به طور خودکار بررسی و پیامک‌ها ارسال شوند:
                </p>

                <div class="bg-black/60 p-4 rounded-2xl border border-slate-800 font-mono text-xs text-emerald-400 dir-ltr text-left select-all overflow-x-auto mb-4">
                    0 10 * * * /usr/bin/php /home/hekmat.neromoda.ir/public_html/cron/send_reminders.php >> /home/hekmat.neromoda.ir/public_html/cron/cron_sms.log 2>&1
                </div>

                <div class="text-xs text-slate-400 flex items-center gap-2">
                    <span>🌐</span>
                    <span>یا لینک وب امن جهت اجرای دوره‌ای با مانیتورینگ آنلاین:</span>
                    <a href="https://hekmat.neromoda.ir/cron/send_reminders.php?token=hekmat_secure_cron_token_2026" target="_blank" class="text-cyan-400 underline font-mono text-[11px] dir-ltr">
                        https://hekmat.neromoda.ir/cron/send_reminders.php?token=...
                    </a>
                </div>
            </div>
        </div>

    </main>

    <!-- ========================================================================= -->
    <!-- ========================================================================= -->
    <!-- MODAL: ویرایش سریع زمان‌بندی پیامک یک خیر -->
    <!-- ========================================================================= -->
    <div x-show="showEditModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4" style="display: none;">
        <div @click.away="showEditModal = false" class="bg-white rounded-3xl p-8 max-w-xl w-full shadow-2xl border border-gray-100 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6 pb-4 border-b border-gray-100">
                <div>
                    <h3 class="text-base font-black text-gray-900" x-text="'تنظیم زمان‌بندی: ' + (currentDonor.name || '')"></h3>
                    <p class="text-xs text-gray-500 mt-1">روز سررسید، دوره ارسال، درگاه و تعداد دانش‌آموزان را تعیین فرمایید</p>
                </div>
                <button @click="showEditModal = false" class="w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-500 flex items-center justify-center font-bold">✕</button>
            </div>

            <div id="modalEditAlert" class="hidden p-3 rounded-xl text-xs font-bold text-center mb-4"></div>

            <form id="editReminderModalForm" class="space-y-4">
                <input type="hidden" name="action" value="save_donor_reminder">
                <input type="hidden" name="donor_id" :value="currentDonor.id">

                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-2xl border border-gray-200">
                    <div>
                        <span class="text-xs font-black text-gray-800 block">فعال بودن یادآوری دوره‌ای</span>
                        <span class="text-[10px] text-gray-500">ارسال یادآوری برای این خیر در موعد سررسید فعال باشد</span>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="reminder_active" id="modal_reminder_active" value="1" :checked="currentDonor.active === 1" class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-teal-600"></div>
                    </label>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-black text-gray-800 block mb-1">روز سررسید در ماه</label>
                        <select name="reminder_day" id="modal_reminder_day" @change="calculateModalNextDate(true)" class="w-full bg-gray-50 border border-gray-200 rounded-xl p-3 text-xs font-bold">
                            <?php for($i=1; $i<=31; $i++): ?>
                                <option value="<?php echo $i; ?>" :selected="currentDonor.day == <?php echo $i; ?>">
                                    روز <?php echo toFarsiDigits($i); ?> ماه (<?php echo getPersianOrdinalDay($i); ?>)
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div>
                        <label class="text-xs font-black text-gray-800 block mb-1">دوره تکرار</label>
                        <select name="reminder_interval_months" id="modal_reminder_interval" @change="calculateModalNextDate(true)" class="w-full bg-gray-50 border border-gray-200 rounded-xl p-3 text-xs font-bold">
                            <option value="1" :selected="currentDonor.interval == 1">ماهانه (هر ۱ ماه)</option>
                            <option value="2" :selected="currentDonor.interval == 2">هر ۲ ماه یک‌بار</option>
                            <option value="3" :selected="currentDonor.interval == 3">فصلی (هر ۳ ماه)</option>
                            <option value="4" :selected="currentDonor.interval == 4">هر ۴ ماه یک‌بار</option>
                            <option value="6" :selected="currentDonor.interval == 6">شش‌ماهه (هر ۶ ماه)</option>
                            <option value="12" :selected="currentDonor.interval == 12">سالانه (هر ۱۲ ماه)</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="text-xs font-black text-gray-800 block mb-1">شماره همراه پیامک</label>
                        <input type="text" name="sms_phone" id="modal_sms_phone" :value="currentDonor.phone" placeholder="۰۹۱۲۳۴۵۶۷۸۹" dir="ltr" class="w-full bg-gray-50 border border-gray-200 rounded-xl p-3 text-xs font-mono font-bold text-left">
                    </div>

                    <div>
                        <label class="text-xs font-black text-gray-800 block mb-1">درگاه ارتباطی</label>
                        <select name="reminder_channel" id="modal_reminder_channel" class="w-full bg-gray-50 border border-gray-200 rounded-xl p-3 text-xs font-bold">
                            <option value="sms" :selected="currentDonor.channel === 'sms'">📱 پیامک SMS</option>
                            <option value="whatsapp" :selected="currentDonor.channel === 'whatsapp'">💬 واتس‌اپ</option>
                            <option value="call" :selected="currentDonor.channel === 'call'">📞 تماس تلفنی</option>
                        </select>
                    </div>

                    <div>
                        <label class="text-xs font-black text-gray-800 block mb-1">تعداد دانش‌آموزان</label>
                        <input type="number" name="reminder_shares" id="modal_reminder_shares" :value="currentDonor.shares || 1" min="1" max="1000" class="w-full bg-gray-50 border border-gray-200 rounded-xl p-3 text-xs font-mono font-bold text-center">
                    </div>
                </div>

                <div>
                    <label class="text-xs font-black text-gray-800 block mb-1">موعد سررسید بعدی (شمسی)</label>
                    <div class="flex gap-2">
                        <input type="text" name="next_reminder_date" id="modal_next_date" :value="currentDonor.next_date" placeholder="۱۴۰۵/۰۷/۰۱" dir="ltr" class="flex-1 bg-gray-50 border border-gray-200 rounded-xl p-3 text-xs font-mono font-bold text-left">
                        <button type="button" @click="calculateModalNextDate(true)" class="py-2 px-3 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-bold shrink-0">محاسبه از تاریخ امروز</button>
                    </div>
                </div>

                <div>
                    <label class="text-xs font-black text-gray-800 block mb-1">قالب پیامک سفارشی (اختیاری)</label>
                    <textarea name="custom_sms_text" id="modal_custom_text" rows="3" :value="currentDonor.custom_text" placeholder="در صورت خالی بودن، قالب عمومی ارسال می‌شود..." class="w-full bg-gray-50 border border-gray-200 rounded-xl p-3 text-xs font-sans"></textarea>
                </div>

                <div class="flex items-center justify-between pt-4 border-t border-gray-100 gap-3">
                    <button type="button" @click="saveModalReminder()" class="py-2.5 px-6 bg-teal-600 hover:bg-teal-700 text-white rounded-xl text-xs font-black shadow transition-all">ذخیره تنظیمات</button>
                    <button type="button" @click="showEditModal = false" class="py-2.5 px-4 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-bold transition-all">انصراف</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL: ثبت نیکوکار جدید در سامانه یادآوری -->
    <!-- ========================================================================= -->
    <div x-show="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4" style="display: none;">
        <div @click.away="showAddModal = false" class="bg-white rounded-3xl p-8 max-w-xl w-full shadow-2xl border border-gray-100 max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6 pb-4 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-teal-50 text-teal-600 flex items-center justify-center text-xl font-bold">
                        ➕
                    </div>
                    <div>
                        <h3 class="text-base font-black text-gray-900">ثبت نیکوکار جدید در سامانه یادآوری</h3>
                        <p class="text-xs text-gray-500 mt-0.5">مشخصات خیر و موعد دوره‌ای همیاری را وارد نمایید</p>
                    </div>
                </div>
                <button @click="showAddModal = false" class="w-8 h-8 rounded-full bg-gray-100 hover:bg-gray-200 text-gray-500 flex items-center justify-center font-bold">✕</button>
            </div>

            <div id="modalAddAlert" class="hidden p-3 rounded-xl text-xs font-bold text-center mb-4"></div>

            <form id="addDonorReminderForm" class="space-y-4">
                <input type="hidden" name="action" value="add_new_donor_reminder">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-black text-gray-800 block mb-1">نام <span class="text-rose-500">*</span></label>
                        <input type="text" name="name" required placeholder="مثلاً: حامد" class="w-full bg-gray-50 border border-gray-200 rounded-xl p-3 text-xs font-bold">
                    </div>
                    <div>
                        <label class="text-xs font-black text-gray-800 block mb-1">نام خانوادگی</label>
                        <input type="text" name="surname" placeholder="مثلاً: حیدری" class="w-full bg-gray-50 border border-gray-200 rounded-xl p-3 text-xs font-bold">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="text-xs font-black text-gray-800 block mb-1">شماره تلفن همراه <span class="text-rose-500">*</span></label>
                        <input type="text" name="phone" required placeholder="۰۹۱۲۳۴۵۶۷۸۹" dir="ltr" class="w-full bg-gray-50 border border-gray-200 rounded-xl p-3 text-xs font-mono font-bold text-left">
                    </div>
                    <div>
                        <label class="text-xs font-black text-gray-800 block mb-1">درگاه ارتباطی / کانال</label>
                        <select name="reminder_channel" class="w-full bg-gray-50 border border-gray-200 rounded-xl p-3 text-xs font-bold">
                            <option value="sms" selected>📱 پیامک خودکار (SMS)</option>
                            <option value="whatsapp">💬 پیام در واتس‌اپ (WhatsApp)</option>
                            <option value="call">📞 تماس تلفنی و هماهنگی</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="text-xs font-black text-gray-800 block mb-1">روز سررسید در ماه</label>
                        <select name="reminder_day" class="w-full bg-gray-50 border border-gray-200 rounded-xl p-3 text-xs font-bold">
                            <?php for($i=1; $i<=31; $i++): ?>
                                <option value="<?php echo $i; ?>" <?php echo $i === 1 ? 'selected' : ''; ?>>
                                    روز <?php echo toFarsiDigits($i); ?> (<?php echo getPersianOrdinalDay($i); ?>)
                                </option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div>
                        <label class="text-xs font-black text-gray-800 block mb-1">دوره تکرار</label>
                        <select name="reminder_interval_months" class="w-full bg-gray-50 border border-gray-200 rounded-xl p-3 text-xs font-bold">
                            <option value="1" selected>ماهانه (هر ۱ ماه)</option>
                            <option value="2">هر ۲ ماه یک‌بار</option>
                            <option value="3">فصلی (هر ۳ ماه)</option>
                            <option value="4">هر ۴ ماه یک‌بار</option>
                            <option value="6">شش‌ماهه (هر ۶ ماه)</option>
                            <option value="12">سالانه (هر ۱۲ ماه)</option>
                        </select>
                    </div>

                    <div>
                        <label class="text-xs font-black text-gray-800 block mb-1">تعداد دانش‌آموزان</label>
                        <input type="number" name="reminder_shares" value="1" min="1" max="1000" class="w-full bg-gray-50 border border-gray-200 rounded-xl p-3 text-xs font-mono font-bold text-center">
                    </div>
                </div>

                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-2xl border border-gray-200">
                    <div>
                        <span class="text-xs font-black text-gray-800 block">فعال‌سازی فوری یادآوری</span>
                        <span class="text-[10px] text-gray-500">یادآوری دوره‌ای بلافاصله در سامانه فعال گردد</span>
                    </div>
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="reminder_active" value="1" checked class="sr-only peer">
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-teal-600"></div>
                    </label>
                </div>

                <div>
                    <label class="text-xs font-black text-gray-800 block mb-1">قالب پیامک سفارشی (اختیاری)</label>
                    <textarea name="custom_sms_text" rows="2" placeholder="در صورت خالی بودن، متن عمومی بنیاد ارسال می‌شود..." class="w-full bg-gray-50 border border-gray-200 rounded-xl p-3 text-xs font-sans"></textarea>
                </div>

                <div class="flex items-center justify-between pt-4 border-t border-gray-100 gap-3">
                    <button type="button" @click="saveAddDonor()" class="py-2.5 px-6 bg-teal-600 hover:bg-teal-700 text-white rounded-xl text-xs font-black shadow transition-all">ثبت نیکوکار</button>
                    <button type="button" @click="showAddModal = false" class="py-2.5 px-4 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-bold transition-all">انصراف</button>
                </div>
            </form>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL: ارسال دسته‌جمعی به سررسیدهای امروز -->
    <!-- ========================================================================= -->
    <div x-show="showBatchModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4" style="display: none;">
        <div @click.away="showBatchModal = false" class="bg-white rounded-3xl p-8 max-w-lg w-full shadow-2xl border border-gray-100">
            <div class="text-center mb-6">
                <div class="w-16 h-16 rounded-full bg-amber-50 text-amber-600 mx-auto flex items-center justify-center text-3xl mb-3">
                    🚀
                </div>
                <h3 class="text-lg font-black text-gray-900">ارسال پیامک به تمام خیرین سررسیدشده</h3>
                <p class="text-xs text-gray-500 mt-2 leading-relaxed">
                    تعداد <strong class="text-amber-600 font-mono"><?php echo $stats_due; ?></strong> نیکوکار موعد ارسال یادآوری‌شان فرا رسیده است. آیا مایلید پیامک‌ها هم‌اکنون ارسال شوند؟
                </p>
            </div>

            <!-- Batch Result Box -->
            <div x-show="batchResult" class="p-4 rounded-2xl text-xs font-bold mb-6 bg-emerald-50 text-emerald-800 border border-emerald-200 text-center" x-text="batchResult"></div>

            <div class="flex items-center justify-center gap-3">
                <button type="button" @click="executeBatchSend()" :disabled="batchLoading || <?php echo $stats_due; ?> === 0" class="py-3 px-8 bg-amber-500 hover:bg-amber-600 disabled:opacity-50 text-white rounded-2xl text-xs font-black shadow-lg shadow-amber-500/20 transition-all flex items-center gap-2">
                    <span x-show="!batchLoading">تایید و ارسال همگانی</span>
                    <span x-show="batchLoading">در حال ارسال پیامک‌ها...</span>
                </button>
                <button type="button" @click="showBatchModal = false" class="py-3 px-6 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-2xl text-xs font-bold transition-all">بستن</button>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        const todayJalali = '<?php echo $today_jalali; ?>';

        function openEditModal(donor) {
            Alpine.store = Alpine.store || {};
            const app = document.querySelector('[x-data]').__x.$data;
            app.currentDonor = donor;
            app.showEditModal = true;
            const alertBox = document.getElementById('modalEditAlert');
            if (alertBox) alertBox.classList.add('hidden');

            setTimeout(() => {
                const dayEl = document.getElementById('modal_reminder_day');
                if (dayEl && donor.day) dayEl.value = donor.day;
                const chanEl = document.getElementById('modal_reminder_channel');
                if (chanEl && donor.channel) chanEl.value = donor.channel;
                const sharesEl = document.getElementById('modal_reminder_shares');
                if (sharesEl) sharesEl.value = donor.shares || 1;
            }, 50);
        }

        function calculateModalNextDate(force = false) {
            const interval = parseInt(document.getElementById('modal_reminder_interval')?.value) || 1;
            const dayEl = document.getElementById('modal_reminder_day');
            const targetDay = dayEl ? (parseInt(dayEl.value) || 1) : 1;

            const parts = todayJalali.split('/');
            let y = parseInt(parts[0]);
            let m = parseInt(parts[1]);

            let totalMonths = (y * 12) + (m - 1) + interval;
            let newY = Math.floor(totalMonths / 12);
            let newM = (totalMonths % 12) + 1;
            let maxDays = newM <= 6 ? 31 : (newM <= 11 ? 30 : 29);
            let newD = Math.min(targetDay, maxDays);

            const calcDate = `${newY}/${String(newM).padStart(2, '0')}/${String(newD).padStart(2, '0')}`;
            const targetEl = document.getElementById('modal_next_date');
            if (targetEl && (force || !targetEl.value)) {
                targetEl.value = calcDate;
            }
        }

        async function toggleDonorActive(donorId, isChecked) {
            const statusText = document.getElementById('status-text-' + donorId);
            const nextDateEl = document.getElementById('next-date-' + donorId);
            const rowEl = document.getElementById('donor-row-' + donorId);
            if (statusText) {
                statusText.innerText = isChecked ? 'فعال' : 'غیرفعال';
                statusText.className = isChecked ? 'text-[10px] font-bold text-teal-700' : 'text-[10px] font-bold text-gray-400';
            }
            if (rowEl) {
                rowEl.setAttribute('data-active', isChecked ? '1' : '0');
            }

            const formData = new FormData();
            formData.append('action', 'toggle_donor_reminder_active');
            formData.append('donor_id', donorId);
            formData.append('active', isChecked ? 1 : 0);

            try {
                const res = await fetch('../admin-request-handler.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success && data.next_reminder_date && nextDateEl) {
                    nextDateEl.innerText = data.next_reminder_date;
                }
            } catch (e) {
                console.error('Error toggling donor active status:', e);
            }
        }

        async function sendInstantReminder(donorId, donorName) {
            if (!confirm(`آیا مطمئن هستید که می‌خواهید پیامک یادآوری رسمی هم‌اکنون برای «${donorName}» ارسال شده و نوبت سررسید بعدی در سامانه ثبت گردد؟`)) return;

            const formData = new FormData();
            formData.append('action', 'send_donor_instant_reminder');
            formData.append('donor_id', donorId);

            try {
                const res = await fetch('../admin-request-handler.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    alert(`✓ پیامک یادآوری رسمی با موفقیت برای «${donorName}» ارسال شد.\nموعد سررسید بعدی: ${data.next_reminder_date || 'ثبت گردید'}`);
                    location.reload();
                } else {
                    alert('✕ خطا در ارسال: ' + (data.message || 'ارسال ناموفق بود.'));
                }
            } catch (e) {
                alert('خطا در برقراری ارتباط با سرور.');
            }
        }

        async function sendInstantWhatsApp(donorId, donorName) {
            if (!confirm(`آیا مایلید نوبت یادآوری برای «${donorName}» ثبت شده و صفحه چت واتس‌اپ باز شود؟`)) return;

            const formData = new FormData();
            formData.append('action', 'send_donor_instant_reminder');
            formData.append('donor_id', donorId);

            try {
                const res = await fetch('../admin-request-handler.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.whatsapp_url) {
                    window.open(data.whatsapp_url, '_blank');
                    alert(`✓ نوبت یادآوری برای «${donorName}» ثبت شد و صفحه واتس‌اپ باز گردید.\nموعد بعدی: ${data.next_reminder_date || 'ثبت گردید'}`);
                    location.reload();
                } else {
                    alert('✓ یادآوری ثبت شد: ' + (data.message || 'انجام گردید.'));
                    location.reload();
                }
            } catch (e) {
                alert('خطا در برقراری ارتباط با سرور.');
            }
        }

        async function saveAddDonor() {
            const form = document.getElementById('addDonorReminderForm');
            const alertBox = document.getElementById('modalAddAlert');
            alertBox.className = 'p-3 rounded-xl text-xs font-bold text-center bg-blue-50 text-blue-700 block';
            alertBox.innerText = 'در حال ثبت نیکوکار جدید در سامانه...';

            try {
                const res = await fetch('../admin-request-handler.php', { method: 'POST', body: new FormData(form) });
                const data = await res.json();
                if (data.success) {
                    alertBox.className = 'p-3 rounded-xl text-xs font-bold text-center bg-emerald-50 text-emerald-700 border border-emerald-200 block';
                    alertBox.innerText = '✓ ' + (data.message || 'نیکوکار جدید با موفقیت ثبت شد.');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    alertBox.className = 'p-3 rounded-xl text-xs font-bold text-center bg-rose-50 text-rose-700 border border-rose-200 block';
                    alertBox.innerText = '✕ ' + (data.message || 'خطا در ثبت نیکوکار.');
                }
            } catch (err) {
                alertBox.className = 'p-3 rounded-xl text-xs font-bold text-center bg-rose-50 text-rose-700 border border-rose-200 block';
                alertBox.innerText = 'خطا در برقراری ارتباط با سرور.';
            }
        }

        async function saveModalReminder() {
            const form = document.getElementById('editReminderModalForm');
            const alertBox = document.getElementById('modalEditAlert');
            alertBox.className = 'p-3 rounded-xl text-xs font-bold text-center bg-blue-50 text-blue-700 block';
            alertBox.innerText = 'در حال ذخیره‌سازی...';

            try {
                const res = await fetch('../admin-request-handler.php', { method: 'POST', body: new FormData(form) });
                const data = await res.json();
                if (data.success) {
                    alertBox.className = 'p-3 rounded-xl text-xs font-bold text-center bg-emerald-50 text-emerald-700 border border-emerald-200 block';
                    alertBox.innerText = '✓ ' + (data.message || 'تنظیمات ذخیره گردید.');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    alertBox.className = 'p-3 rounded-xl text-xs font-bold text-center bg-rose-50 text-rose-700 border border-rose-200 block';
                    alertBox.innerText = '✕ ' + (data.message || 'خطا در ذخیره.');
                }
            } catch (err) {
                alertBox.className = 'p-3 rounded-xl text-xs font-bold text-center bg-rose-50 text-rose-700 border border-rose-200 block';
                alertBox.innerText = 'خطا در برقراری ارتباط با سرور.';
            }
        }

        async function sendSingleTest(donorId, donorName) {
            if (!confirm(`آیا مایل به ارسال یک پیامک آزمایشی برای ${donorName} هستید؟`)) return;

            const formData = new FormData();
            formData.append('action', 'send_donor_test_sms');
            formData.append('donor_id', donorId);

            try {
                const res = await fetch('../admin-request-handler.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    alert(`✓ پیامک با موفقیت برای ${donorName} ارسال شد.`);
                    location.reload();
                } else {
                    alert('✕ خطا: ' + (data.message || 'ارسال ناموفق بود.'));
                }
            } catch (err) {
                alert('خطا در ارتباط با سرور.');
            }
        }

        async function executeBatchSend() {
            const app = document.querySelector('[x-data]').__x.$data;
            app.batchLoading = true;
            app.batchResult = null;

            const formData = new FormData();
            formData.append('action', 'send_all_due_sms');

            try {
                const res = await fetch('../admin-request-handler.php', { method: 'POST', body: formData });
                const data = await res.json();
                app.batchLoading = false;
                if (data.success) {
                    app.batchResult = '✓ ' + (data.message || 'پیامک‌ها با موفقیت ارسال شدند.');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    alert('✕ خطا: ' + (data.message || 'خطا در ارسال دسته‌جمعی.'));
                }
            } catch (err) {
                app.batchLoading = false;
                alert('خطا در ارتباط با سرور.');
            }
        }

        async function saveSmsSettings() {
            const app = document.querySelector('[x-data]').__x.$data;
            app.settingsLoading = true;
            app.settingsAlert = null;

            const form = document.getElementById('smsSettingsForm');
            try {
                const res = await fetch('../admin-request-handler.php', { method: 'POST', body: new FormData(form) });
                const data = await res.json();
                app.settingsLoading = false;
                if (data.success) {
                    app.settingsAlert = { type: 'success', message: data.message || 'تنظیمات با موفقیت ذخیره شد.' };
                } else {
                    app.settingsAlert = { type: 'error', message: data.message || 'خطا در ذخیره تنظیمات.' };
                }
            } catch (err) {
                app.settingsLoading = false;
                app.settingsAlert = { type: 'error', message: 'خطا در برقراری ارتباط با سرور.' };
            }
        }

        function insertTag(tag) {
            const textarea = document.getElementById('setting_template');
            if (!textarea) return;
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const text = textarea.value;
            textarea.value = text.substring(0, start) + tag + text.substring(end);
            textarea.focus();
            textarea.selectionStart = textarea.selectionEnd = start + tag.length;
        }
    </script>
</body>
</html>
