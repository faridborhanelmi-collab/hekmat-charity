<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/SmsService.php';

// Access Control - Financial Only
require_financial_access();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch Donor Data
$stmt = $pdo->prepare("SELECT * FROM donors WHERE id = ?");
$stmt->execute([$id]);
$donor = $stmt->fetch();

if (!$donor) {
    die("اطلاعات نیکوکار یافت نشد.");
}

// Access Control
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
$can_edit = can_edit_financial();

// Benefactors can only see their own profile
if ($_SESSION['role'] === 'benefactor' && $_SESSION['related_id'] != $id) {
    die("شما دسترسی به این پرونده را ندارید.");
}

// Fetch Donations & Documents
$dn_stmt = $pdo->prepare("SELECT * FROM donations WHERE donor_id = ? ORDER BY date DESC");
$dn_stmt->execute([$id]);
$donations = $dn_stmt->fetchAll();

$doc_stmt = $pdo->prepare("SELECT * FROM documents WHERE owner_type = 'donor' AND owner_id = ? ORDER BY upload_date DESC");
$doc_stmt->execute([$id]);
$documents = $doc_stmt->fetchAll();

// Fetch Sponsored Students for this donor
$spon_stmt = $pdo->prepare("
    SELECT s.id as spon_id, s.shares_count, s.start_date, st.id as student_id, st.name as st_name, st.surname as st_surname, st.code as st_code, st.alias_name, st.grade
    FROM sponsorships s
    JOIN students st ON s.student_id = st.id
    WHERE s.donor_id = ? AND s.status = 'active'
    ORDER BY s.id DESC
");
$spon_stmt->execute([$id]);
$sponsored_students = $spon_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch SMS Reminder Data & Logs
$smsService = new SmsService($pdo);
$sms_settings = $smsService->getSettings();
$today_jalali = SmsService::getCurrentJalaliDate();

$sms_log_stmt = $pdo->prepare("
    SELECT l.*, COALESCE(u.full_name, u.username) as sent_by_name 
    FROM donor_sms_logs l 
    LEFT JOIN users u ON l.sent_by_user_id = u.id 
    WHERE l.donor_id = ? 
    ORDER BY l.id DESC 
    LIMIT 20
");
$sms_log_stmt->execute([$id]);
$sms_logs = $sms_log_stmt->fetchAll(PDO::FETCH_ASSOC);

$default_sms_preview = $smsService->renderTemplate(
    !empty($donor['custom_sms_text']) ? $donor['custom_sms_text'] : $sms_settings['default_template'],
    $donor
);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پرونده نیکوکار: <?php echo $donor['name']; ?> | بنیاد حکمت</title>
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
                    colors: { primary: { 900: '#00141e', 800: '#115e59', 600: '#14b8a6' } }
                }
            }
        }
    </script>

    <!-- iOS PWA/Homescreen Setup -->
    <link rel="apple-touch-icon" href="logo.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="بنیاد حکمت">
    <link rel="icon" type="image/png" href="logo.png">
    <link rel="manifest" href="manifest.json">
</head>
<body class="bg-gray-50 font-sans text-gray-800 antialiased" x-data="{ showEditModal: false, showDocModal: false, showDonationModal: false, donationForm: {id: '', amount: '', month: '', year: '', date: '', description: '', receipt_no: ''} }">

    <?php include 'includes/navbar.php'; ?>

    <main class="container mx-auto px-6 py-12 max-w-6xl">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
            
            <!-- Profile Sidebar -->
            <div class="lg:col-span-1 space-y-6">
                <div class="bg-white rounded-[3rem] p-10 shadow-xl border border-gray-100 text-center relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-full h-2 bg-gradient-to-l from-indigo-500 to-teal-500"></div>
                    
                    <div class="relative w-28 h-28 mx-auto mb-6 group cursor-pointer overflow-hidden rounded-[2.5rem] border-4 border-white shadow-xl bg-gray-50">
                        <img id="donorImg" src="<?php echo $donor['photo_path'] ?: 'https://ui-avatars.com/api/?name=' . $donor['name'] . '&background=00141e&color=fff&size=200'; ?>" 
                             class="relative w-full h-full object-cover">
                        
                        <?php if ($can_edit): ?>
                        <div class="absolute inset-0 bg-black/40 text-white flex flex-col items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                            <span class="text-2xl">📸</span>
                            <input type="file" @change="uploadPhoto($event)" class="absolute inset-0 opacity-0 cursor-pointer">
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <h1 class="text-2xl font-black text-primary-900 mb-2"><?php echo $donor['name'] . ' ' . $donor['surname']; ?></h1>
                    <p class="text-teal-600 font-bold text-xs">حامی طرح‌های بنیاد حکمت</p>
                    
                    <div class="mt-8 space-y-4 text-right">
                        <div class="bg-gray-50 p-4 rounded-2xl">
                            <p class="text-[10px] text-gray-400 font-bold mb-1">شماره تماس</p>
                            <p class="text-sm font-mono font-bold text-gray-700 dir-ltr text-left"><?php echo $donor['phone']; ?></p>
                        </div>
                        <?php if ($can_edit): ?>
                            <button @click="showEditModal = true" class="w-full py-3 bg-primary-900 text-white rounded-2xl text-[10px] font-black shadow-lg hover:bg-teal-600 transition-all">ویرایش اطلاعات حامی</button>
                        <?php elseif (is_read_only()): ?>
                            <div class="p-3 bg-amber-50 rounded-2xl text-center border border-amber-200">
                                <span class="text-[10px] text-amber-800 font-bold">👁️ نظارت هیئت مدیره (فقط خواندنی)</span>
                            </div>
                        <?php endif; ?>
                        <?php if ($is_admin): ?>
                        <a href="donor-dashboard.php?donor_id=<?php echo $donor['id']; ?>" target="_blank"
                           class="w-full py-3 bg-gradient-to-r from-teal-600 to-emerald-600 hover:from-teal-500 hover:to-emerald-500 text-white rounded-2xl text-[10px] font-black shadow-md transition-all flex items-center justify-center gap-1.5 transform hover:-translate-y-0.5">
                            <span>👁️</span> مشاهده پورتال خیر (دید حامی)
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bg-primary-900 rounded-[3rem] p-10 shadow-xl text-white">
                    <h2 class="text-3xl font-black text-teal-400 mb-2"><?php echo number_format($donor['total_donated']); ?></h2>
                    <p class="text-white/50 text-xs font-bold mb-8">مجموع حمایت‌های مالی (ریال)</p>
                    
                    <div class="space-y-4">
                        <h4 class="text-xs font-black text-white/40 mb-4 border-b border-white/5 pb-2">مکاتبات و اسناد پشتیبانی</h4>
                        <div class="space-y-2">
                        <?php foreach ($documents as $doc): ?>
                            <div class="bg-white/5 p-2 rounded-lg flex items-center justify-between text-[10px]">
                                <span class="font-bold"><?php echo mb_strimwidth($doc['file_name'], 0, 15, "..."); ?></span>
                                <div class="flex gap-1">
                                    <a href="<?php echo $doc['file_path']; ?>" target="_blank" class="w-6 h-6 flex items-center justify-center bg-white/10 rounded">📥</a>
                                    <?php if ($is_admin): ?>
                                    <button @click="deleteDoc(<?php echo $doc['id']; ?>)" class="w-6 h-6 flex items-center justify-center bg-red-500/20 text-red-400 rounded">🗑️</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                        <?php if ($is_admin): ?>
                        <button @click="showDocModal = true" class="w-full py-4 border border-dashed border-white/20 rounded-2xl text-[10px] font-bold hover:bg-white/5 transition-all">+ بارگذاری سند/مکاتبه</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Transaction History & Sponsored Students -->
            <div class="lg:col-span-2 space-y-10">

                <!-- Sponsored Students Hub -->
                <div class="bg-white rounded-[3.5rem] p-10 shadow-xl border border-gray-100">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 pb-4 border-b border-gray-100">
                        <div>
                            <h3 class="text-xl font-black text-primary-900 flex items-center gap-3">
                                <span class="w-10 h-10 bg-teal-50 text-teal-600 rounded-2xl flex items-center justify-center text-lg">🎓</span>
                                دانش‌پژوهان تحت حمایت این خیر (بورس تحصیلی)
                            </h3>
                            <p class="text-xs text-gray-400 font-bold mt-1">با رعایت کامل محرمانگی (کد و نام مستعار در پرتال خیر)</p>
                        </div>
                        <?php if ($can_edit): ?>
                        <a href="admin/sponsorships.php?donor_id=<?php echo $donor['id']; ?>" class="py-2.5 px-4 bg-teal-600 hover:bg-teal-500 text-white rounded-xl text-xs font-black shadow-md transition-all flex items-center gap-1.5 shrink-0">
                            <span>+</span> پیوند دانش‌آموز جدید
                        </a>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($sponsored_students)): ?>
                        <div class="p-8 border-2 border-dashed border-gray-100 rounded-3xl text-center text-xs text-gray-400 font-bold">
                            در حال حاضر دانش‌آموزی برای این خیر تعریف نشده است.
                            <?php if ($can_edit): ?>
                            <div class="mt-3">
                                <a href="admin/sponsorships.php?donor_id=<?php echo $donor['id']; ?>" class="text-teal-600 underline hover:text-teal-700">کلیک کنید تا اولین دانش‌پژوه را به این خیر متصل نمایید.</a>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php foreach ($sponsored_students as $st): ?>
                            <div class="bg-gray-50 border border-gray-100 p-5 rounded-3xl flex items-center justify-between hover:bg-teal-50/30 transition-all">
                                <div class="flex items-center gap-3">
                                    <div class="w-12 h-12 rounded-2xl bg-teal-500/10 text-teal-600 flex items-center justify-center text-xl font-black">
                                        🎓
                                    </div>
                                    <div>
                                        <div class="text-sm font-black text-gray-900 flex items-center gap-2">
                                            <a href="person-detail.php?id=<?php echo $st['student_id']; ?>" class="hover:text-teal-600">
                                                <?php echo htmlspecialchars($st['st_name'] . ' ' . $st['st_surname']); ?>
                                            </a>
                                            <span class="text-[9px] bg-gray-200 text-gray-700 px-2 py-0.5 rounded-full font-bold">کد <?php echo toFarsiDigits($st['st_code']); ?></span>
                                        </div>
                                        <div class="text-[10px] text-gray-500 mt-1">
                                            نام در دید خیر (محرمانه): <strong class="text-teal-700"><?php echo htmlspecialchars($st['alias_name'] ?: 'حکمت‌جو #' . $st['student_id']); ?></strong>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px] bg-teal-100 text-teal-700 px-2.5 py-1 rounded-xl font-bold"><?php echo toFarsiDigits($st['shares_count']); ?> سهم بورس</span>
                                    <a href="student-dashboard.php?student_id=<?php echo $st['student_id']; ?>" target="_blank" title="مشاهده پورتال دانش‌آموز" class="w-8 h-8 rounded-xl bg-white border border-gray-200 flex items-center justify-center text-xs hover:bg-indigo-600 hover:text-white transition-all shadow-sm">
                                        👁️
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Periodic SMS Reminder Management Card -->
                <div class="bg-white rounded-[3.5rem] p-10 shadow-xl border border-gray-100">
                    <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 pb-4 border-b border-gray-100">
                        <div>
                            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-50 text-amber-800 text-xs font-bold mb-2 border border-amber-200">
                                <span>📱</span>
                                <span>سامانه یادآوری دوره‌ای نیکوکار</span>
                            </div>
                            <h3 class="text-xl font-black text-primary-900 flex items-center gap-3">
                                <span>⏰</span> زمان‌بندی و ارسال پیامک‌های دوره‌ای
                            </h3>
                            <p class="text-xs text-gray-400 font-bold mt-1">تنظیم دوره ارسال (ماهانه، فصلی، سالانه)، تاریخ سررسید و متن پیامک اختصاصی</p>
                        </div>

                        <?php if ($donor['reminder_active']): ?>
                            <?php $is_due = (!empty($donor['next_reminder_date']) && $donor['next_reminder_date'] <= $today_jalali); ?>
                            <span class="px-3.5 py-1.5 rounded-full text-xs font-black <?php echo $is_due ? 'bg-rose-50 border border-rose-300 text-rose-700 animate-pulse' : 'bg-teal-50 border border-teal-200 text-teal-800'; ?>">
                                <?php echo $is_due ? '⚠️ سررسید ارسال پیامک فرا رسیده!' : '🔔 یادآوری فعال (' . SmsService::getIntervalLabel($donor['reminder_interval_months']) . ')'; ?>
                            </span>
                        <?php else: ?>
                            <span class="px-3 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-500 border border-gray-200">
                                🔕 یادآوری خاموش
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if ($can_edit): ?>
                    <form id="donorReminderForm" class="space-y-6">
                        <input type="hidden" name="action" value="save_donor_reminder">
                        <input type="hidden" name="donor_id" value="<?php echo $donor['id']; ?>">

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <!-- Switch -->
                            <div class="bg-gray-50 p-5 rounded-3xl border border-gray-100 flex flex-col justify-between">
                                <div>
                                    <label class="text-xs font-black text-gray-800 block mb-1">وضعیت یادآوری</label>
                                    <p class="text-[11px] text-gray-500 mb-4">آیا پیامک دوره‌ای به این خیر ارسال شود؟</p>
                                </div>
                                <label class="relative inline-flex items-center cursor-pointer">
                                    <input type="checkbox" name="reminder_active" id="detail_reminder_active" value="1" <?php echo !empty($donor['reminder_active']) ? 'checked' : ''; ?> class="sr-only peer">
                                    <div class="w-12 h-6 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-teal-600"></div>
                                    <span class="mr-3 text-xs font-black text-gray-700">فعال‌سازی سیستم</span>
                                </label>
                            </div>

                            <!-- Interval -->
                            <div class="bg-gray-50 p-5 rounded-3xl border border-gray-100 space-y-2">
                                <label class="text-xs font-black text-gray-800 block">دوره تکرار یادآوری</label>
                                <p class="text-[11px] text-gray-500">هر چند وقت یک‌بار پیامک ارسال شود؟</p>
                                <select name="reminder_interval_months" id="detail_reminder_interval" onchange="calcNextDateDetail()" class="w-full bg-white border border-gray-200 rounded-2xl px-4 py-3 text-xs font-bold text-gray-800 focus:outline-none focus:border-teal-500">
                                    <option value="1" <?php echo ($donor['reminder_interval_months'] == 1) ? 'selected' : ''; ?>>ماهانه (هر ۱ ماه یک‌بار)</option>
                                    <option value="2" <?php echo ($donor['reminder_interval_months'] == 2) ? 'selected' : ''; ?>>هر ۲ ماه یک‌بار</option>
                                    <option value="3" <?php echo ($donor['reminder_interval_months'] == 3) ? 'selected' : ''; ?>>فصلی (هر ۳ ماه یک‌بار)</option>
                                    <option value="4" <?php echo ($donor['reminder_interval_months'] == 4) ? 'selected' : ''; ?>>هر ۴ ماه یک‌بار</option>
                                    <option value="6" <?php echo ($donor['reminder_interval_months'] == 6) ? 'selected' : ''; ?>>شش ماه یک‌بار (هر ۶ ماه)</option>
                                    <option value="12" <?php echo ($donor['reminder_interval_months'] == 12) ? 'selected' : ''; ?>>سالانه (هر ۱۲ ماه یک‌بار)</option>
                                </select>
                            </div>

                            <!-- Phone -->
                            <div class="bg-gray-50 p-5 rounded-3xl border border-gray-100 space-y-2">
                                <label class="text-xs font-black text-gray-800 block">شماره همراه دریافت پیامک</label>
                                <p class="text-[11px] text-gray-500">شماره معتبر موبایل (مثلاً ۰۹۱۲...)</p>
                                <input type="text" name="sms_phone" id="detail_sms_phone" value="<?php echo htmlspecialchars($donor['sms_phone'] ?: $donor['phone']); ?>" placeholder="۰۹۱۲۳۴۵۶۷۸۹" dir="ltr" class="w-full bg-white border border-gray-200 rounded-2xl px-4 py-3 text-xs text-left font-mono font-bold focus:outline-none focus:border-teal-500">
                            </div>
                        </div>

                        <!-- Dates Row -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 bg-amber-50/40 p-6 rounded-3xl border border-amber-100">
                            <div>
                                <label class="text-xs font-black text-gray-800 block mb-1">تاریخ موعد ارسال بعدی (شمسی)</label>
                                <div class="flex gap-2">
                                    <input type="text" name="next_reminder_date" id="detail_next_reminder_date" value="<?php echo htmlspecialchars($donor['next_reminder_date'] ?? ''); ?>" placeholder="۱۴۰۵/۰۷/۰۱" dir="ltr" class="flex-1 bg-white border border-gray-200 rounded-2xl px-4 py-3 text-xs text-left font-mono font-bold focus:outline-none focus:border-teal-500">
                                    <button type="button" onclick="calcNextDateDetail(true)" class="px-4 py-2 bg-amber-100 hover:bg-amber-200 text-amber-900 rounded-2xl text-xs font-bold transition-colors shrink-0">
                                        محاسبه از امروز
                                    </button>
                                </div>
                                <span class="text-[10px] text-gray-500 mt-1 block">امکان تغییر دستی تاریخ نیز وجود دارد.</span>
                            </div>

                            <div>
                                <label class="text-xs font-black text-gray-800 block mb-1">تاریخ آخرین پیامک ارسالی</label>
                                <div class="bg-white border border-gray-200 rounded-2xl px-4 py-3 text-xs font-mono font-bold text-gray-700">
                                    <?php echo toFarsiDigits($donor['last_reminder_date'] ?: 'تاکنون پیامکی ارسال نشده است'); ?>
                                </div>
                                <span class="text-[10px] text-gray-500 mt-1 block">این تاریخ پس از ارسال موفق خودکار یا دستی ثبت می‌شود.</span>
                            </div>
                        </div>

                        <!-- Template & Live Preview -->
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                            <div>
                                <label class="text-xs font-black text-gray-800 block mb-1">قالب پیامک سفارشی (اختیاری)</label>
                                <p class="text-[11px] text-gray-500 mb-2">در صورت خالی گذاشتن، پیامک استاندارد بنیاد حکمت ارسال می‌شود.</p>
                                <textarea name="custom_sms_text" id="detail_custom_sms_text" rows="5" placeholder="قالب پیامک اختصاصی برای این خیر..." class="w-full bg-gray-50 border border-gray-200 rounded-2xl p-4 text-xs leading-relaxed text-gray-800 focus:outline-none focus:border-teal-500"><?php echo htmlspecialchars($donor['custom_sms_text'] ?? ''); ?></textarea>
                                <p class="text-[10px] text-gray-400 mt-1">متغیرها: {name}, {interval}, {card_number}, {link}, {total_donated}</p>
                            </div>

                            <div>
                                <label class="text-xs font-black text-gray-800 block mb-1">پیش‌نمایش پیامک نهایی خیر</label>
                                <p class="text-[11px] text-gray-500 mb-2">شبیه‌سازی متن واقعی که روی گوشی ارسال می‌شود:</p>
                                <div class="bg-slate-900 text-slate-100 p-5 rounded-2xl text-xs leading-loose font-sans border border-slate-800 shadow-inner relative whitespace-pre-line" id="liveSmsPreview"><?php echo htmlspecialchars($default_sms_preview); ?></div>
                            </div>
                        </div>

                        <!-- Alert Box -->
                        <div id="detailReminderAlert" class="hidden p-4 rounded-2xl text-xs font-bold text-center"></div>

                        <!-- Actions -->
                        <div class="flex items-center justify-between gap-4 pt-4 border-t border-gray-100 flex-wrap">
                            <button type="button" onclick="saveDonorReminderDetail()" class="py-3 px-8 bg-teal-600 hover:bg-teal-700 text-white rounded-2xl text-xs font-black shadow-lg shadow-teal-500/20 transition-all flex items-center gap-2">
                                <span>💾</span>
                                <span>ذخیره تنظیمات یادآوری</span>
                            </button>

                            <button type="button" onclick="sendTestSmsDetail()" class="py-3 px-6 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 border border-indigo-200 rounded-2xl text-xs font-bold transition-all flex items-center gap-2">
                                <span>📱</span>
                                <span>ارسال آزمایشی پیامک همین الان</span>
                            </button>
                        </div>
                    </form>
                    <?php else: ?>
                    <div class="p-6 bg-gray-50 rounded-2xl text-center text-xs font-bold text-gray-500">
                        وضعیت یادآوری: <?php echo $donor['reminder_active'] ? 'فعال (' . SmsService::getIntervalLabel($donor['reminder_interval_months']) . ')' : 'غیرفعال'; ?>
                    </div>
                    <?php endif; ?>

                    <!-- SMS Logs Table -->
                    <div class="mt-8 pt-6 border-t border-gray-100">
                        <h4 class="text-sm font-black text-gray-800 mb-4 flex items-center gap-2">
                            <span>📜</span> تاریخچه پیامک‌های ارسال‌شده به این خیر (<?php echo count($sms_logs); ?> پیامک اخیر)
                        </h4>
                        <?php if (empty($sms_logs)): ?>
                            <div class="p-6 bg-gray-50 rounded-2xl text-center text-xs text-gray-400 font-bold">
                                تاکنون پیامکی در سیستم برای این خیر ثبت نشده است.
                            </div>
                        <?php else: ?>
                            <div class="overflow-x-auto">
                                <table class="w-full text-right text-xs">
                                    <thead>
                                        <tr class="text-[11px] text-gray-400 border-b border-gray-100 pb-2">
                                            <th class="pb-3 font-bold">تاریخ</th>
                                            <th class="pb-3 font-bold">شماره مقصد</th>
                                            <th class="pb-3 font-bold w-1/2">متن پیامک</th>
                                            <th class="pb-3 font-bold">ارسال‌کننده</th>
                                            <th class="pb-3 font-bold text-center">وضعیت</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100">
                                        <?php foreach ($sms_logs as $log): ?>
                                            <tr class="hover:bg-gray-50/50">
                                                <td class="py-3 font-mono text-gray-600"><?php echo toFarsiDigits($log['sent_date']); ?></td>
                                                <td class="py-3 font-mono text-gray-800 dir-ltr text-right"><?php echo htmlspecialchars($log['phone']); ?></td>
                                                <td class="py-3 text-[11px] text-gray-600 leading-relaxed max-w-md truncate" title="<?php echo htmlspecialchars($log['message']); ?>"><?php echo htmlspecialchars($log['message']); ?></td>
                                                <td class="py-3 text-gray-500"><?php echo htmlspecialchars($log['sent_by_name'] ?: 'سیستم خودکار'); ?></td>
                                                <td class="py-3 text-center">
                                                    <?php if ($log['status'] === 'sent'): ?>
                                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">ارسال موفق</span>
                                                    <?php else: ?>
                                                        <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-rose-50 text-rose-700 border border-rose-200">ناموفق</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bg-white rounded-[3.5rem] p-12 shadow-xl border border-gray-100">
                    <div class="flex justify-between items-center mb-10">
                        <h3 class="text-2xl font-black text-primary-900 flex items-center gap-4">
                            <span class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center">💳</span>
                            تاریخچه واریزی‌ها و حمایت‌ها
                        </h3>
                        <?php if ($can_edit): ?>
                        <button @click="donationForm = {id: '', amount: '', month: '', year: '', date: '', description: '', receipt_no: ''}; showDonationModal = true;" class="py-2 px-4 bg-teal-600 text-white rounded-xl text-xs font-black shadow-lg hover:bg-primary-900 transition-all">+ ثبت واریزی جدید</button>
                        <?php endif; ?>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-right">
                            <thead>
                                <tr class="text-[11px] text-gray-400 uppercase tracking-wider border-b border-gray-50">
                                    <th class="pb-6 font-bold">بابت ماه/سال</th>
                                    <th class="pb-6 font-bold">مبلغ (ریال)</th>
                                    <th class="pb-6 font-bold w-1/3">بابت (توضیحات)</th>
                                    <th class="pb-6 font-bold">تاریخ ثبت</th>
                                    <?php if ($can_edit): ?><th class="pb-6 font-bold text-center">عملیات</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody class="text-sm text-gray-700">
                                <?php foreach ($donations as $dn): ?>
                                <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                                    <td class="py-6 font-bold text-gray-900"><?php echo $dn['month'] . ' ' . $dn['year']; ?></td>
                                    <td class="py-6 font-black text-teal-600"><?php echo number_format($dn['amount']); ?></td>
                                    <td class="py-6 text-[11px] text-gray-500 leading-relaxed"><?php echo $dn['description'] ?: '---'; ?></td>
                                    <td class="py-6 text-[11px] text-gray-400"><?php echo $dn['date'] ?: '---'; ?></td>
                                    <?php if ($can_edit): ?>
                                    <td class="py-6 flex gap-2 justify-center">
                                        <button @click="donationForm = {id: '<?php echo $dn['id']; ?>', amount: '<?php echo $dn['amount']; ?>', month: '<?php echo $dn['month']; ?>', year: '<?php echo $dn['year']; ?>', date: '<?php echo $dn['date']; ?>', description: '<?php echo htmlspecialchars($dn['description'] ?? '', ENT_QUOTES); ?>', receipt_no: '<?php echo htmlspecialchars($dn['receipt_no'] ?? '', ENT_QUOTES); ?>'}; showDonationModal = true;" class="w-8 h-8 flex items-center justify-center bg-indigo-50 text-indigo-600 hover:bg-indigo-500 hover:text-white rounded-xl transition-all">✏️</button>
                                        <button @click="deleteDonation(<?php echo $dn['id']; ?>)" class="w-8 h-8 flex items-center justify-center bg-red-50 text-red-500 hover:bg-red-500 hover:text-white rounded-xl transition-all">🗑️</button>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- EDIT MODAL -->
    <div x-show="showEditModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-primary-900/40 backdrop-blur-sm p-4">
        <div @click.away="showEditModal = false" class="bg-white w-full max-w-lg rounded-[3rem] shadow-2xl p-8">
            <h3 class="text-xl font-black text-primary-900 mb-8 border-b pb-4">ویرایش اطلاعات حامی</h3>
            <form id="editDonorForm" class="space-y-6">
                <input type="hidden" name="id" value="<?php echo $donor['id']; ?>">
                <input type="hidden" name="action" value="update_donor">
                <div class="grid grid-cols-2 gap-4">
                    <input type="text" name="name" value="<?php echo $donor['name']; ?>" placeholder="نام" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm">
                    <input type="text" name="surname" value="<?php echo $donor['surname']; ?>" placeholder="نام خانوادگی" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm">
                </div>
                <input type="text" name="phone" value="<?php echo $donor['phone']; ?>" placeholder="تلفن" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm">
                <input type="text" name="birthday" value="<?php echo $donor['birthday']; ?>" placeholder="تاریخ تولد" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm">
                <textarea name="description" placeholder="توضیحات" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm h-32"><?php echo $donor['description']; ?></textarea>
                <button type="button" @click="saveDonor()" class="w-full py-4 bg-teal-600 text-white rounded-2xl font-black shadow-xl">ذخیره تغییرات</button>
            </form>
        </div>
    </div>

    <!-- DOC MODAL -->
    <div x-show="showDocModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-primary-900/40 backdrop-blur-sm p-4">
        <div @click.away="showDocModal = false" class="bg-white w-full max-w-md rounded-[3rem] shadow-2xl p-10">
            <h3 class="text-xl font-black text-primary-900 mb-8 border-b pb-4">بارگذاری سند جدید</h3>
            <form id="uploadDocForm" class="space-y-6">
                <input type="hidden" name="action" value="upload_document">
                <input type="hidden" name="owner_type" value="donor">
                <input type="hidden" name="owner_id" value="<?php echo $donor['id']; ?>">
                <input type="file" name="document" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm">
                <input type="text" name="description" placeholder="توضیح سند" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm">
                <button type="button" @click="saveDoc()" class="w-full py-4 bg-indigo-600 text-white rounded-2xl font-black">بارگذاری</button>
            </form>
        </div>
    </div>

    <!-- DONATION MODAL -->
    <div x-show="showDonationModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-primary-900/40 backdrop-blur-sm p-4">
        <div @click.away="showDonationModal = false" class="bg-white w-full max-w-lg rounded-[3rem] shadow-2xl p-8">
            <h3 class="text-xl font-black text-primary-900 mb-8 border-b pb-4" x-text="donationForm.id ? 'ویرایش واریزی' : 'ثبت واریزی جدید'"></h3>
            <form id="donationFormElement" class="space-y-6">
                <input type="hidden" name="action" :value="donationForm.id ? 'edit_donation' : 'add_donation'">
                <input type="hidden" name="id" :value="donationForm.id">
                <?php if (can_reassign_donation()): ?>
                <div x-show="donationForm.id" class="p-3 bg-amber-50 rounded-2xl border border-amber-200">
                    <label class="text-[11px] font-bold text-amber-900 mb-1 block">انتقال این واریزی به حساب نیکوکار دیگر:</label>
                    <select name="donor_id" class="w-full bg-white border border-amber-200 rounded-xl px-3 py-2 text-xs font-bold outline-none focus:ring-2 focus:ring-teal-500">
                        <option value="<?php echo $donor['id']; ?>">همین نیکوکار (<?php echo htmlspecialchars($donor['name'] . ' ' . $donor['surname']); ?>)</option>
                        <?php 
                        $all_other_donors = $pdo->query("SELECT id, name, surname, phone FROM donors WHERE id != {$donor['id']} ORDER BY name ASC")->fetchAll();
                        foreach ($all_other_donors as $od): ?>
                        <option value="<?php echo $od['id']; ?>">انتقال به: <?php echo htmlspecialchars($od['name'] . ' ' . $od['surname'] . ($od['phone'] ? ' (' . $od['phone'] . ')' : '')); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <template x-if="!donationForm.id">
                    <input type="hidden" name="donor_id" value="<?php echo $donor['id']; ?>">
                </template>
                <?php else: ?>
                <input type="hidden" name="donor_id" value="<?php echo $donor['id']; ?>">
                <?php endif; ?>
                <div class="grid grid-cols-2 gap-4">
                    <input type="text" name="amount" x-model="donationForm.amount" placeholder="مبلغ (ریال)" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm font-bold text-left" dir="ltr">
                    <input type="text" name="date" x-model="donationForm.date" placeholder="تاریخ ثبت" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm text-left" dir="ltr">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <input type="text" name="month" x-model="donationForm.month" placeholder="ماه پرداختی" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm">
                    <input type="text" name="year" x-model="donationForm.year" placeholder="سال پرداختی" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm">
                </div>
                <input type="text" name="receipt_no" x-model="donationForm.receipt_no" placeholder="شماره پیگیری/فیش" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm text-left" dir="ltr">
                <textarea name="description" x-model="donationForm.description" placeholder="بابت / توضیحات" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm h-24"></textarea>
                <button type="button" @click="saveDonation()" class="w-full py-4 bg-teal-600 text-white rounded-2xl font-black shadow-xl" x-text="donationForm.id ? 'ذخیره تغییرات' : 'ثبت واریزی'"></button>
            </form>
        </div>
    </div>

    <script>
        async function uploadPhoto(e) {
            const formData = new FormData();
            formData.append('photo', e.target.files[0]);
            formData.append('owner_type', 'donor');
            formData.append('owner_id', '<?php echo $donor['id']; ?>');
            formData.append('action', 'upload_photo');

            const res = await fetch('admin-request-handler.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                document.getElementById('donorImg').src = data.path + '?' + Date.now();
            } else {
                alert(data.message);
            }
        }

        async function saveDonor() {
            const form = document.getElementById('editDonorForm');
            const res = await fetch('admin-request-handler.php', { method: 'POST', body: new FormData(form) });
            const data = await res.json();
            if (data.success) location.reload();
        }

        async function saveDoc() {
            const form = document.getElementById('uploadDocForm');
            const res = await fetch('admin-request-handler.php', { method: 'POST', body: new FormData(form) });
            const data = await res.json();
            if (data.success) location.reload();
        }

        async function deleteDoc(id) {
            if (!confirm('حذف شود؟')) return;
            const formData = new FormData();
            formData.append('id', id);
            formData.append('action', 'delete_document');
            const res = await fetch('admin-request-handler.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) location.reload();
        }

        async function saveDonation() {
            const form = document.getElementById('donationFormElement');
            const res = await fetch('admin-request-handler.php', { method: 'POST', body: new FormData(form) });
            const data = await res.json();
            if (data.success) location.reload();
        }

        async function deleteDonation(id) {
            if (!confirm('آیا از حذف این تراکنش اطمینان دارید؟')) return;
            const formData = new FormData();
            formData.append('id', id);
            formData.append('action', 'delete_donation');
            const res = await fetch('admin-request-handler.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) location.reload();
        }

        // Reminder logic for donor-detail
        const detailAlert = document.getElementById('detailReminderAlert');
        const donorId = <?php echo (int)$donor['id']; ?>;
        const donorName = <?php echo json_encode($donor['name']); ?>;
        const defaultTemplate = <?php echo json_encode($sms_settings['default_template'] ?? ''); ?>;

        function calcNextDateDetail(force = false) {
            const intervalEl = document.getElementById('detail_reminder_interval');
            const nextDateEl = document.getElementById('detail_next_reminder_date');
            if (!intervalEl || !nextDateEl) return;
            const interval = parseInt(intervalEl.value) || 1;
            const today = '<?php echo $today_jalali; ?>';
            const parts = today.split('/');
            let y = parseInt(parts[0]);
            let m = parseInt(parts[1]);
            let d = parseInt(parts[2]);

            let totalMonths = (y * 12) + (m - 1) + interval;
            let newY = Math.floor(totalMonths / 12);
            let newM = (totalMonths % 12) + 1;
            let newD = Math.min(d, (newM <= 6 ? 31 : 30));

            const calcDate = `${newY}/${String(newM).padStart(2, '0')}/${String(newD).padStart(2, '0')}`;
            if (force || !nextDateEl.value) {
                nextDateEl.value = calcDate;
            }
            updateDetailPreview();
        }

        function updateDetailPreview() {
            const previewEl = document.getElementById('liveSmsPreview');
            const customTextEl = document.getElementById('detail_custom_sms_text');
            const intervalEl = document.getElementById('detail_reminder_interval');
            if (!previewEl) return;

            let tmpl = (customTextEl && customTextEl.value.trim()) ? customTextEl.value.trim() : defaultTemplate;
            const intervalText = intervalEl ? intervalEl.options[intervalEl.selectedIndex].text : '';
            
            let replaced = tmpl
                .replace(/{name}/g, donorName)
                .replace(/{full_name}/g, donorName)
                .replace(/{interval}/g, intervalText)
                .replace(/{card_number}/g, '۶۰۳۷-۹۹۷۹-۵۰۱۴-۲۲۳۴')
                .replace(/{link}/g, 'hekmat.neromoda.ir')
                .replace(/{today}/g, '<?php echo $today_jalali; ?>');
                
            previewEl.innerText = replaced;
        }

        const customInput = document.getElementById('detail_custom_sms_text');
        if (customInput) {
            customInput.addEventListener('input', updateDetailPreview);
        }

        async function saveDonorReminderDetail() {
            const form = document.getElementById('donorReminderForm');
            if (!form) return;
            const formData = new FormData(form);

            if (detailAlert) {
                detailAlert.className = 'p-4 rounded-2xl text-xs font-bold text-center bg-blue-50 text-blue-700 block';
                detailAlert.innerText = 'در حال ذخیره‌سازی تنظیمات یادآوری...';
            }

            try {
                const res = await fetch('admin-request-handler.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    if (detailAlert) {
                        detailAlert.className = 'p-4 rounded-2xl text-xs font-bold text-center bg-emerald-50 text-emerald-700 border border-emerald-200 block';
                        detailAlert.innerText = '✓ ' + (data.message || 'تنظیمات یادآوری با موفقیت ذخیره شد.');
                    }
                    setTimeout(() => location.reload(), 1200);
                } else {
                    if (detailAlert) {
                        detailAlert.className = 'p-4 rounded-2xl text-xs font-bold text-center bg-rose-50 text-rose-700 border border-rose-200 block';
                        detailAlert.innerText = '✕ ' + (data.message || 'خطا در ذخیره اطلاعات');
                    }
                }
            } catch (err) {
                if (detailAlert) {
                    detailAlert.className = 'p-4 rounded-2xl text-xs font-bold text-center bg-rose-50 text-rose-700 border border-rose-200 block';
                    detailAlert.innerText = 'خطا در ارتباط با سرور.';
                }
            }
        }

        async function sendTestSmsDetail() {
            if (!confirm('آیا از ارسال یک پیامک آزمایشی به این نیکوکار اطمینان دارید؟')) return;

            if (detailAlert) {
                detailAlert.className = 'p-4 rounded-2xl text-xs font-bold text-center bg-blue-50 text-blue-700 block';
                detailAlert.innerText = 'در حال ارسال پیامک آزمایشی...';
            }

            const formData = new FormData();
            formData.append('action', 'send_donor_test_sms');
            formData.append('donor_id', donorId);

            try {
                const res = await fetch('admin-request-handler.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    if (detailAlert) {
                        detailAlert.className = 'p-4 rounded-2xl text-xs font-bold text-center bg-emerald-50 text-emerald-700 border border-emerald-200 block';
                        detailAlert.innerText = '✓ ' + (data.message || 'پیامک با موفقیت ارسال شد و در تاریخچه ثبت گردید.');
                    }
                    setTimeout(() => location.reload(), 1500);
                } else {
                    if (detailAlert) {
                        detailAlert.className = 'p-4 rounded-2xl text-xs font-bold text-center bg-rose-50 text-rose-700 border border-rose-200 block';
                        detailAlert.innerText = '✕ ' + (data.message || 'خطا در ارسال پیامک');
                    }
                }
            } catch (err) {
                if (detailAlert) {
                    detailAlert.className = 'p-4 rounded-2xl text-xs font-bold text-center bg-rose-50 text-rose-700 border border-rose-200 block';
                    detailAlert.innerText = 'خطا در برقراری ارتباط با سرور.';
                }
            }
        }
    </script>


<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/sw.js');
    });
  }
</script>

</body>
</html>
