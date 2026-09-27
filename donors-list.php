<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/SmsService.php';

// Access Control - Financial Only
require_financial_access();

// Fetch Donors ordered by total contribution
$query = "SELECT * FROM donors ORDER BY total_donated DESC";
$stmt = $pdo->query($query);
$donors = $stmt->fetchAll(PDO::FETCH_ASSOC);

$today_jalali = SmsService::getCurrentJalaliDate();
$active_reminders_count = count(array_filter($donors, fn($d) => !empty($d['reminder_active'])));
$due_reminders_count = count(array_filter($donors, fn($d) => !empty($d['reminder_active']) && !empty($d['next_reminder_date']) && $d['next_reminder_date'] <= $today_jalali));
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پورتال نیکوکاران و سامانه یادآوری | بنیاد حکمت</title>
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
<body class="bg-gray-50 font-sans text-gray-800 antialiased">

    <?php include 'includes/navbar.php'; ?>

    <header class="bg-gradient-to-l from-primary-900 via-slate-900 to-indigo-950 text-white py-14 shadow-md">
        <div class="container mx-auto px-6 flex flex-col md:flex-row justify-between items-center gap-8">
            <div class="text-right">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-teal-500/20 text-teal-300 text-xs font-bold mb-3 border border-teal-500/30">
                    <span>💎</span>
                    <span>پورتال جامعه حامیان بنیاد حکمت</span>
                </div>
                <h1 class="text-3xl md:text-4xl font-black mb-3 text-white">مدیریت نیکوکاران و یادآوری‌های دوره‌ای</h1>
                <p class="text-teal-100/80 text-xs md:text-sm max-w-xl leading-relaxed">مدیریت تعامل با خیرین، ثبت زمان‌بندی پیامک‌های دوره‌ای (ماهانه، فصلی، سالانه)، تاریخچه واریزی‌ها و رتبه‌بندی حامیان نخبگان.</p>
            </div>
            <div class="flex flex-col sm:flex-row gap-4">
                <div class="bg-white/10 backdrop-blur-xl p-6 rounded-3xl border border-white/20 text-center min-w-[200px]">
                    <?php 
                        $global_total = $pdo->query("SELECT SUM(amount) FROM donations")->fetchColumn();
                        $global_total = $global_total ? $global_total : 0;
                    ?>
                    <div class="text-2xl md:text-3xl font-black text-teal-400 font-mono"><?php echo toFarsiDigits(number_format((float)$global_total)); ?></div>
                    <div class="text-[10px] text-white/70 font-bold mt-1">مجموع جذب سرمایه (ریال)</div>
                </div>
                <div class="bg-amber-500/20 backdrop-blur-xl p-6 rounded-3xl border border-amber-500/30 text-center min-w-[180px]">
                    <div class="text-2xl md:text-3xl font-black text-amber-300 font-mono"><?php echo toFarsiDigits($active_reminders_count); ?> <span class="text-xs font-normal text-white/80">خیر</span></div>
                    <div class="text-[10px] text-amber-200 font-bold mt-1">دارای یادآوری دوره‌ای فعال</div>
                </div>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-6 -mt-6 pb-20 max-w-7xl">
        <!-- Search, Filter & Quick Action Bar -->
        <div class="bg-white rounded-3xl shadow-xl p-6 mb-8 border border-gray-100 flex flex-col lg:flex-row justify-between items-stretch lg:items-center gap-4">
            <div class="relative flex-1 max-w-md">
                <input type="text" id="donorSearch" placeholder="جستجوی نام، نام خانوادگی، شماره تماس یا دوره..." 
                    class="w-full bg-gray-50 border border-gray-200 rounded-2xl px-12 py-3.5 focus:outline-none focus:ring-2 focus:ring-primary-600 transition-all text-xs font-bold">
                <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400">🔍</span>
            </div>

            <!-- Reminder Filter Tabs -->
            <div class="flex items-center gap-1.5 overflow-x-auto pb-1 text-xs font-bold" id="filterTabs">
                <button type="button" onclick="setFilter('all', this)" class="filter-tab px-4 py-2.5 rounded-xl bg-primary-900 text-white transition-all shrink-0">
                    همه خیرین (<?php echo toFarsiDigits(count($donors)); ?>)
                </button>
                <button type="button" onclick="setFilter('active', this)" class="filter-tab px-4 py-2.5 rounded-xl bg-gray-100 text-gray-600 hover:bg-teal-50 hover:text-teal-700 transition-all shrink-0">
                    🔔 دارای یادآوری فعال (<?php echo toFarsiDigits($active_reminders_count); ?>)
                </button>
                <button type="button" onclick="setFilter('due', this)" class="filter-tab px-4 py-2.5 rounded-xl bg-gray-100 text-gray-600 hover:bg-rose-50 hover:text-rose-700 transition-all shrink-0">
                    ⚠️ سررسید پیامک (<?php echo toFarsiDigits($due_reminders_count); ?>)
                </button>
            </div>

            <!-- Quick Action Links -->
            <div class="flex items-center gap-2 flex-wrap">
                <a href="admin/donor-reminders.php" class="bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-black px-4 py-2.5 rounded-xl text-xs transition-all shadow-md flex items-center gap-1.5 shrink-0">
                    <span>📱</span>
                    <span>سامانه پیامک‌های دوره‌ای</span>
                    <?php if ($due_reminders_count > 0): ?>
                        <span class="bg-rose-600 text-white text-[10px] px-2 py-0.5 rounded-full font-mono animate-pulse"><?php echo toFarsiDigits($due_reminders_count); ?> سررسید</span>
                    <?php endif; ?>
                </a>
                <a href="admin/donor-analytics.php" class="bg-teal-50 hover:bg-teal-100 text-teal-800 border border-teal-200 px-4 py-2.5 rounded-xl text-xs font-black transition-all flex items-center gap-1.5 shrink-0">
                    <span>📊</span> تحلیل رفتار خیرین
                </a>
            </div>
        </div>

        <!-- Donors Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="donorsGrid">
            <?php foreach ($donors as $index => $d): ?>
                <?php 
                    $has_reminder = !empty($d['reminder_active']);
                    $is_due = ($has_reminder && !empty($d['next_reminder_date']) && $d['next_reminder_date'] <= $today_jalali);
                    $target_phone = $d['sms_phone'] ?: $d['phone'];
                ?>
                <div class="donor-card bg-white rounded-[2.5rem] p-7 shadow-sm hover:shadow-xl border border-gray-100 transition-all duration-300 relative group flex flex-col justify-between"
                     data-name="<?php echo htmlspecialchars($d['name'] . ' ' . $d['surname'] . ' ' . $target_phone); ?>"
                     data-active="<?php echo $has_reminder ? '1' : '0'; ?>"
                     data-due="<?php echo $is_due ? '1' : '0'; ?>">
                    
                    <!-- Top Bar: Rank & Reminder Badge -->
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-8 h-8 bg-primary-900 text-white rounded-xl flex items-center justify-center text-xs font-black font-mono shadow-sm">
                            #<?php echo toFarsiDigits($index + 1); ?>
                        </div>
                        <?php if ($has_reminder): ?>
                            <?php if ($is_due): ?>
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-black bg-rose-50 text-rose-700 border border-rose-200 animate-pulse">
                                    <span>⚠️</span> موعد ارسال پیامک!
                                </span>
                            <?php else: ?>
                                <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-black bg-teal-50 text-teal-700 border border-teal-200">
                                    <span>🔔</span> <?php echo SmsService::getIntervalLabel($d['reminder_interval_months'] ?? 1); ?>
                                </span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="text-[10px] font-bold text-gray-400 bg-gray-100 px-2 py-0.5 rounded-lg">
                                🔕 بدون یادآوری
                            </span>
                        <?php endif; ?>
                    </div>

                    <!-- Donor Info Body -->
                    <div class="flex flex-col items-center text-center">
                        <div class="w-20 h-20 bg-gradient-to-br from-teal-50 to-indigo-50 rounded-2xl flex items-center justify-center text-3xl mb-4 border-2 border-white shadow-md group-hover:scale-105 transition-transform overflow-hidden">
                            <?php if (!empty($d['photo_path'])): ?>
                                <img src="<?php echo htmlspecialchars($d['photo_path']); ?>" alt="پرتره" class="w-full h-full object-cover">
                            <?php else: ?>
                                <span>🤝</span>
                            <?php endif; ?>
                        </div>
                        <h3 class="text-lg font-black text-primary-900 mb-1 leading-snug">
                            <?php echo htmlspecialchars($d['name'] . ' ' . $d['surname']); ?>
                        </h3>
                        <p class="text-[11px] text-gray-400 font-bold mb-4 font-mono dir-ltr">
                            <?php echo htmlspecialchars($target_phone ?: 'بدون تلفن ثبت‌شده'); ?>
                        </p>
                        
                        <!-- Total Donated Box -->
                        <div class="w-full bg-primary-50/60 rounded-2xl p-3.5 mb-4 border border-primary-100/50">
                            <div class="text-[10px] text-primary-700 font-bold mb-0.5">مجموع حمایت‌های مالی</div>
                            <div class="text-lg font-black text-primary-900 font-mono">
                                <?php echo toFarsiDigits(number_format($d['total_donated'])); ?> <span class="text-[10px] font-normal text-gray-500">ریال</span>
                            </div>
                        </div>

                        <!-- Reminder Status Strip -->
                        <?php if ($has_reminder): ?>
                            <div class="w-full p-2.5 rounded-xl text-xs mb-4 flex items-center justify-between border <?php echo $is_due ? 'bg-rose-50/80 border-rose-200 text-rose-800' : 'bg-gray-50 border-gray-200 text-gray-700'; ?>">
                                <span class="text-[11px] font-bold">موعد یادآوری بعدی:</span>
                                <span class="font-mono font-black text-[11px] <?php echo $is_due ? 'text-rose-700' : 'text-teal-700'; ?>">
                                    <?php echo toFarsiDigits($d['next_reminder_date'] ?: 'تعیین‌نشده'); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Action Buttons -->
                    <div class="flex w-full gap-2 pt-2 border-t border-gray-100">
                        <a href="donor-detail.php?id=<?php echo $d['id']; ?>" 
                           class="flex-1 bg-primary-900 hover:bg-teal-600 text-white py-2.5 rounded-xl text-xs font-black transition-colors shadow-sm text-center">
                            مشاهده پرونده
                        </a>
                        <button type="button" onclick='openQuickReminderModal(<?php echo json_encode([
                            "id" => $d["id"],
                            "name" => $d["name"] . " " . $d["surname"],
                            "phone" => $target_phone,
                            "active" => (int)($d["reminder_active"] ?? 0),
                            "interval" => (int)($d["reminder_interval_months"] ?? 1),
                            "day" => (int)($d["reminder_day"] ?? 1),
                            "channel" => $d["reminder_channel"] ?? "sms",
                            "shares" => (int)($d["reminder_shares"] ?? 1),
                            "next_date" => $d["next_reminder_date"] ?? "",
                            "custom_text" => $d["custom_sms_text"] ?? ""
                        ], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>)' 
                                class="px-3 py-2 bg-amber-50 hover:bg-amber-500 text-amber-800 hover:text-white rounded-xl transition-all text-xs font-bold flex items-center justify-center gap-1 border border-amber-200 shadow-sm"
                                title="تنظیم زمان‌بندی پیامک یادآوری">
                            <span>⏰</span>
                            <span class="hidden sm:inline">یادآوری</span>
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <!-- QUICK REMINDER MODAL -->
    <div id="quick-reminder-modal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 hidden">
        <div class="bg-white w-full max-w-lg rounded-[2.5rem] shadow-2xl p-8 border border-gray-100 relative max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-6 pb-4 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl font-black">
                        ⏰
                    </div>
                    <div>
                        <h3 class="text-base font-black text-gray-900">تنظیم زمان‌بندی پیامک یادآوری</h3>
                        <p class="text-[11px] text-gray-500" id="modal-donor-name">---</p>
                    </div>
                </div>
                <button type="button" onclick="closeQuickReminderModal()" class="text-gray-400 hover:text-gray-700 text-xl font-bold">✕</button>
            </div>

            <form id="quickReminderForm" class="space-y-5">
                <input type="hidden" name="action" value="save_donor_reminder">
                <input type="hidden" name="donor_id" id="modal-donor-id" value="">

                <!-- Active Toggle Switch -->
                <div class="bg-gray-50 p-4 rounded-2xl flex items-center justify-between border border-gray-100">
                    <div>
                        <label for="modal-reminder-active" class="text-xs font-black text-gray-800 block cursor-pointer">ارسال خودکار پیامک یادآوری</label>
                        <span class="text-[10px] text-gray-500">در صورت فعال بودن، در مواعد مقرر پیامک ارسال خواهد شد.</span>
                    </div>
                    <input type="checkbox" name="reminder_active" id="modal-reminder-active" value="1" class="w-5 h-5 text-teal-600 rounded focus:ring-teal-500">
                </div>

                <!-- Phone Number -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1.5">شماره همراه جهت ارسال پیامک (فرمت ۰۹۱۲...)</label>
                    <input type="text" name="sms_phone" id="modal-sms-phone" placeholder="۰۹۱۲۳۴۵۶۷۸۹" dir="ltr" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-xs text-left font-mono font-bold focus:outline-none focus:border-teal-500">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Day Selection -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1.5">روز سررسید در ماه</label>
                        <select name="reminder_day" id="modal-reminder-day" onchange="autoCalculateNextDate(true)" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-xs font-bold text-gray-800 focus:outline-none focus:border-teal-500">
                            <?php for($i=1; $i<=31; $i++): ?>
                                <option value="<?php echo $i; ?>">روز <?php echo toFarsiDigits($i); ?>ام ماه</option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <!-- Interval Selection -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1.5">دوره تکرار یادآوری</label>
                        <select name="reminder_interval_months" id="modal-reminder-interval" onchange="autoCalculateNextDate(true)" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-xs font-bold text-gray-800 focus:outline-none focus:border-teal-500">
                            <option value="1">ماهانه (هر ۱ ماه)</option>
                            <option value="2">هر ۲ ماه یک‌بار</option>
                            <option value="3" selected>فصلی (هر ۳ ماه یک‌بار)</option>
                            <option value="4">هر ۴ ماه یک‌بار</option>
                            <option value="6">شش ماه یک‌بار</option>
                            <option value="12">سالانه (هر ۱۲ ماه)</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Channel Selection -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1.5">درگاه ارسال</label>
                        <select name="reminder_channel" id="modal-reminder-channel" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-xs font-bold text-gray-800 focus:outline-none focus:border-teal-500">
                            <option value="sms">📱 پیامک خودکار (SMS)</option>
                            <option value="whatsapp">💬 پیام در واتس‌اپ (WhatsApp)</option>
                            <option value="call">📞 تماس تلفنی و هماهنگی</option>
                        </select>
                    </div>

                    <!-- Shares / Student Count -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1.5">تعداد دانش‌آموزان (سهم)</label>
                        <input type="number" name="reminder_shares" id="modal-reminder-shares" value="1" min="1" max="1000" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-xs font-mono font-bold text-center focus:outline-none focus:border-teal-500">
                    </div>
                </div>

                <!-- Next Reminder Date -->
                <div>
                    <div class="flex justify-between items-center mb-1.5">
                        <label class="text-xs font-bold text-gray-700">موعد ارسال بعدی (تاریخ شمسی)</label>
                        <button type="button" onclick="autoCalculateNextDate(true)" class="text-[10px] text-teal-600 hover:text-teal-800 font-bold underline">
                            محاسبه از امروز
                        </button>
                    </div>
                    <input type="text" name="next_reminder_date" id="modal-next-date" placeholder="مثلاً: ۱۴۰۵/۰۷/۰۱" dir="ltr" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-xs text-left font-mono font-bold focus:outline-none focus:border-teal-500">
                </div>

                <!-- Custom Text (Optional) -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1.5">متن سفارشی پیامک (اختیاری)</label>
                    <textarea name="custom_sms_text" id="modal-custom-text" rows="3" placeholder="در صورت خالی گذاشتن، متن استاندارد بنیاد حکمت ارسال خواهد شد..." class="w-full bg-gray-50 border border-gray-200 rounded-xl p-3 text-xs text-gray-700 focus:outline-none focus:border-teal-500"></textarea>
                    <p class="text-[10px] text-gray-400 mt-1">متغیرهای مجاز: {name}, {interval}, {card_number}, {link}</p>
                </div>

                <!-- Response Alert -->
                <div id="modal-alert" class="hidden p-3 rounded-xl text-xs font-bold text-center"></div>

                <!-- Buttons -->
                <div class="flex items-center gap-2 pt-3 border-t border-gray-100 flex-wrap">
                    <button type="button" onclick="saveQuickReminder()" class="flex-1 py-2.5 px-4 bg-teal-600 hover:bg-teal-700 text-white rounded-xl text-xs font-black transition-all shadow-md">
                        ذخیره تنظیمات
                    </button>
                    <button type="button" onclick="sendInstantReminderNow()" class="py-2.5 px-4 bg-gradient-to-r from-teal-500 to-emerald-600 hover:from-teal-600 hover:to-emerald-700 text-white rounded-xl text-xs font-black transition-all shadow-sm">
                        ⚡ ارسال آنی
                    </button>
                    <button type="button" onclick="sendTestSmsNow()" class="py-2.5 px-3 bg-indigo-50 hover:bg-indigo-100 text-indigo-700 rounded-xl text-xs font-bold transition-all">
                        تست
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Real-time Search
        const donorSearch = document.getElementById('donorSearch');
        const donorCards = document.querySelectorAll('.donor-card');
        let currentFilter = 'all';

        donorSearch.addEventListener('input', applyFilters);

        function setFilter(filterType, btn) {
            currentFilter = filterType;
            document.querySelectorAll('#filterTabs .filter-tab').forEach(b => {
                b.classList.remove('bg-primary-900', 'text-white');
                b.classList.add('bg-gray-100', 'text-gray-600');
            });
            btn.classList.add('bg-primary-900', 'text-white');
            btn.classList.remove('bg-gray-100', 'text-gray-600');
            applyFilters();
        }

        function applyFilters() {
            const query = (donorSearch.value || '').toLowerCase().trim();
            donorCards.forEach(card => {
                const name = card.getAttribute('data-name').toLowerCase();
                const isActive = card.getAttribute('data-active') === '1';
                const isDue = card.getAttribute('data-due') === '1';

                const matchesQuery = !query || name.includes(query);
                let matchesFilter = true;

                if (currentFilter === 'active') matchesFilter = isActive;
                if (currentFilter === 'due') matchesFilter = isDue;

                card.style.display = (matchesQuery && matchesFilter) ? 'flex' : 'none';
            });
        }

        // Quick Reminder Modal Logic
        let activeDonorData = null;
        const modal = document.getElementById('quick-reminder-modal');
        const alertBox = document.getElementById('modal-alert');

        function openQuickReminderModal(donorData) {
            activeDonorData = donorData;
            document.getElementById('modal-donor-id').value = donorData.id;
            document.getElementById('modal-donor-name').innerText = 'تنظیمات یادآوری برای: ' + donorData.name;
            document.getElementById('modal-sms-phone').value = donorData.phone || '';
            document.getElementById('modal-reminder-active').checked = (donorData.active === 1);
            document.getElementById('modal-reminder-interval').value = donorData.interval || 1;
            document.getElementById('modal-reminder-day').value = donorData.day || 1;
            document.getElementById('modal-reminder-channel').value = donorData.channel || 'sms';
            document.getElementById('modal-reminder-shares').value = donorData.shares || 1;
            document.getElementById('modal-next-date').value = donorData.next_date || '';
            document.getElementById('modal-custom-text').value = donorData.custom_text || '';
            alertBox.classList.add('hidden');
            modal.classList.remove('hidden');

            if (!donorData.next_date && donorData.active === 1) {
                autoCalculateNextDate(true);
            }
        }

        function closeQuickReminderModal() {
            modal.classList.add('hidden');
        }

        function autoCalculateNextDate(force = false) {
            const interval = parseInt(document.getElementById('modal-reminder-interval').value) || 1;
            const targetDay = parseInt(document.getElementById('modal-reminder-day').value) || 1;
            const today = '<?php echo $today_jalali; ?>';
            const parts = today.split('/');
            let y = parseInt(parts[0]);
            let m = parseInt(parts[1]);

            let totalMonths = (y * 12) + (m - 1) + interval;
            let newY = Math.floor(totalMonths / 12);
            let newM = (totalMonths % 12) + 1;
            let maxDays = (newM <= 6 ? 31 : (newM <= 11 ? 30 : 29));
            let newD = Math.min(targetDay, maxDays);

            const calcDate = `${newY}/${String(newM).padStart(2, '0')}/${String(newD).padStart(2, '0')}`;
            if (force || !document.getElementById('modal-next-date').value) {
                document.getElementById('modal-next-date').value = calcDate;
            }
        }

        async function sendInstantReminderNow() {
            if (!activeDonorData || !activeDonorData.id) return;
            if (!confirm(`آیا مطمئن هستید که می‌خواهید پیامک یادآوری رسمی برای «${activeDonorData.name}» هم‌اکنون ارسال شود و موعد بعدی در سامانه ثبت گردد؟`)) return;

            alertBox.className = 'p-3 rounded-xl text-xs font-bold text-center bg-blue-50 text-blue-700 block';
            alertBox.innerText = 'در حال ارسال آنی پیامک رسمی...';

            const formData = new FormData();
            formData.append('action', 'send_donor_instant_reminder');
            formData.append('donor_id', activeDonorData.id);

            try {
                const res = await fetch('admin-request-handler.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    if (data.whatsapp_url) {
                        window.open(data.whatsapp_url, '_blank');
                    }
                    alertBox.className = 'p-3 rounded-xl text-xs font-bold text-center bg-emerald-50 text-emerald-700 border border-emerald-200 block';
                    alertBox.innerText = '✓ پیامک یادآوری ارسال گردید و موعد بعدی ثبت شد.';
                    setTimeout(() => location.reload(), 1200);
                } else {
                    alertBox.className = 'p-3 rounded-xl text-xs font-bold text-center bg-rose-50 text-rose-700 border border-rose-200 block';
                    alertBox.innerText = '✕ ' + (data.message || 'خطا در ارسال آنی');
                }
            } catch (err) {
                alertBox.className = 'p-3 rounded-xl text-xs font-bold text-center bg-rose-50 text-rose-700 border border-rose-200 block';
                alertBox.innerText = 'خطا در ارتباط با سرور.';
            }
        }

        async function saveQuickReminder() {
            const form = document.getElementById('quickReminderForm');
            const formData = new FormData(form);
            alertBox.className = 'p-3 rounded-xl text-xs font-bold text-center bg-blue-50 text-blue-700 block';
            alertBox.innerText = 'در حال ذخیره‌سازی...';

            try {
                const res = await fetch('admin-request-handler.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    alertBox.className = 'p-3 rounded-xl text-xs font-bold text-center bg-emerald-50 text-emerald-700 border border-emerald-200 block';
                    alertBox.innerText = '✓ ' + (data.message || 'تنظیمات ذخیره شد');
                    setTimeout(() => location.reload(), 1000);
                } else {
                    alertBox.className = 'p-3 rounded-xl text-xs font-bold text-center bg-rose-50 text-rose-700 border border-rose-200 block';
                    alertBox.innerText = '✕ ' + (data.message || 'خطا در ذخیره');
                }
            } catch (err) {
                alertBox.className = 'p-3 rounded-xl text-xs font-bold text-center bg-rose-50 text-rose-700 border border-rose-200 block';
                alertBox.innerText = 'خطا در ارتباط با سرور.';
            }
        }

        async function sendTestSmsNow() {
            if (!activeDonorData || !activeDonorData.id) return;
            if (!confirm('آیا مایل به ارسال یک پیامک آزمایشی برای این خیر هستید؟')) return;

            alertBox.className = 'p-3 rounded-xl text-xs font-bold text-center bg-blue-50 text-blue-700 block';
            alertBox.innerText = 'در حال ارسال پیامک آزمایشی...';

            const formData = new FormData();
            formData.append('action', 'send_donor_test_sms');
            formData.append('donor_id', activeDonorData.id);

            try {
                const res = await fetch('admin-request-handler.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    alertBox.className = 'p-3 rounded-xl text-xs font-bold text-center bg-emerald-50 text-emerald-700 border border-emerald-200 block';
                    alertBox.innerText = '✓ پیامک با موفقیت ارسال شد (در کارتابل ثبت گردید).';
                } else {
                    alertBox.className = 'p-3 rounded-xl text-xs font-bold text-center bg-rose-50 text-rose-700 border border-rose-200 block';
                    alertBox.innerText = '✕ ' + (data.message || 'خطا در ارسال پیامک');
                }
            } catch (err) {
                alertBox.className = 'p-3 rounded-xl text-xs font-bold text-center bg-rose-50 text-rose-700 border border-rose-200 block';
                alertBox.innerText = 'خطا در ارتباط با سرور.';
            }
        }

        function confirmDelete(id) {
            if (confirm('آیا از حذف این پرونده اطمینان دارید؟')) {
                alert('جهت حفظ امنیت اسناد مالی، حذف مستقیم از این بخش غیرفعال است.');
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
