<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/SmsService.php';

// Access Control - Financial Only
require_financial_access();

// Fetch Donors ordered by total contribution with sponsored student details
$query = "
    SELECT d.*,
           COUNT(s.id) as sponsored_count,
           GROUP_CONCAT(s.id || ':::' || st.id || ':::' || st.name || ' ' || COALESCE(st.surname, '') || ':::' || s.shares_count, '|||') as sponsored_students_raw
    FROM donors d
    LEFT JOIN sponsorships s ON s.donor_id = d.id AND s.status = 'active'
    LEFT JOIN students st ON s.student_id = st.id
    GROUP BY d.id
    ORDER BY d.total_donated DESC
";
$stmt = $pdo->query($query);
$donors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch All Students for Assignment Modal (Unsponsored first)
$students_modal_query = "
    SELECT st.id, st.name, st.surname, st.code, st.grade,
           (SELECT COUNT(*) FROM sponsorships s WHERE s.student_id = st.id AND s.status = 'active') as active_sponsors
    FROM students st
    WHERE st.status != 'exited' OR st.status IS NULL
    ORDER BY active_sponsors ASC, st.name ASC
";
$all_students_for_modal = $pdo->query($students_modal_query)->fetchAll(PDO::FETCH_ASSOC);

$today_jalali = SmsService::getCurrentJalaliDate();
$active_reminders_count = count(array_filter($donors, fn($d) => !empty($d['reminder_active'])));
$due_reminders_count = count(array_filter($donors, fn($d) => !empty($d['reminder_active']) && !empty($d['next_reminder_date']) && $d['next_reminder_date'] <= $today_jalali));
$donors_with_students_count = count(array_filter($donors, fn($d) => ((int)$d['sponsored_count']) > 0));
$donors_without_students_count = count($donors) - $donors_with_students_count;
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
                    colors: { 
                        primary: { 900: '#00141e', 800: '#115e59', 600: '#14b8a6' },
                        whatsapp: '#25D366'
                    }
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

    <?php 
    $page_nav_title = 'مدیریت نیکوکاران و یادآوری‌ها';
    include 'includes/admin-navbar.php'; 
    ?>

    <header class="bg-gradient-to-l from-primary-900 via-slate-900 to-indigo-950 text-white py-12 shadow-md">
        <div class="container mx-auto px-6 flex flex-col md:flex-row justify-between items-center gap-6">
            <div class="text-right">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-teal-500/20 text-teal-300 text-xs font-bold mb-3 border border-teal-500/30">
                    <span>💎</span>
                    <span>پورتال جامعه حامیان بنیاد حکمت</span>
                </div>
                <h1 class="text-2xl md:text-3xl font-black mb-2 text-white">مدیریت نیکوکاران، تخصیص فرزندان و یادآوری‌ها</h1>
                <p class="text-teal-100/80 text-xs md:text-sm max-w-2xl leading-relaxed">ارتباط مستقیم با خیرین از طریق واتس‌اپ و ملی‌پیامک، شفافیت اتصال حامیان به دانش‌آموزان و زمان‌بندی یادآوری‌های دوره‌ای.</p>
            </div>
            <div class="grid grid-cols-3 gap-3 w-full md:w-auto">
                <div class="bg-white/10 backdrop-blur-xl p-4 rounded-2xl border border-white/20 text-center min-w-[130px]">
                    <?php 
                        $global_total = $pdo->query("SELECT SUM(amount) FROM donations")->fetchColumn();
                        $global_total = $global_total ? $global_total : 0;
                    ?>
                    <div class="text-lg md:text-xl font-black text-teal-400 font-mono"><?php echo toFarsiDigits(number_format((float)$global_total)); ?></div>
                    <div class="text-[9px] text-white/70 font-bold mt-0.5">مجموع جذب (ریال)</div>
                </div>
                <div class="bg-emerald-500/20 backdrop-blur-xl p-4 rounded-2xl border border-emerald-500/30 text-center min-w-[130px]">
                    <div class="text-lg md:text-xl font-black text-emerald-300 font-mono"><?php echo toFarsiDigits($donors_with_students_count); ?> <span class="text-[10px] font-normal text-white/80">خیر</span></div>
                    <div class="text-[9px] text-emerald-200 font-bold mt-0.5">دارای دانش‌آموز معنوی</div>
                </div>
                <div class="bg-amber-500/20 backdrop-blur-xl p-4 rounded-2xl border border-amber-500/30 text-center min-w-[130px]">
                    <div class="text-lg md:text-xl font-black text-amber-300 font-mono"><?php echo toFarsiDigits($active_reminders_count); ?> <span class="text-[10px] font-normal text-white/80">خیر</span></div>
                    <div class="text-[9px] text-amber-200 font-bold mt-0.5">یادآوری فعال</div>
                </div>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-6 -mt-6 pb-20 max-w-7xl">
        <!-- Search, Filter & Quick Action Bar -->
        <div class="bg-white rounded-3xl shadow-xl p-6 mb-8 border border-gray-100 flex flex-col lg:flex-row justify-between items-stretch lg:items-center gap-4">
            <div class="relative flex-1 max-w-md">
                <input type="text" id="donorSearch" placeholder="جستجوی نام خیر، شماره، دوره یا نام دانش‌آموز تحت پوشش..." 
                    class="w-full bg-gray-50 border border-gray-200 rounded-2xl px-12 py-3.5 focus:outline-none focus:ring-2 focus:ring-primary-600 transition-all text-xs font-bold">
                <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400">🔍</span>
            </div>

            <!-- Reminder & Sponsorship Filter Tabs -->
            <div class="flex items-center gap-1.5 overflow-x-auto pb-1 text-xs font-bold" id="filterTabs">
                <button type="button" onclick="setFilter('all', this)" class="filter-tab px-3.5 py-2 rounded-xl bg-primary-900 text-white transition-all shrink-0">
                    همه خیرین (<?php echo toFarsiDigits(count($donors)); ?>)
                </button>
                <button type="button" onclick="setFilter('with_students', this)" class="filter-tab px-3.5 py-2 rounded-xl bg-gray-100 text-gray-600 hover:bg-emerald-50 hover:text-emerald-700 transition-all shrink-0">
                    🎓 دارای دانش‌آموز (<?php echo toFarsiDigits($donors_with_students_count); ?>)
                </button>
                <button type="button" onclick="setFilter('without_students', this)" class="filter-tab px-3.5 py-2 rounded-xl bg-gray-100 text-gray-600 hover:bg-amber-50 hover:text-amber-700 transition-all shrink-0">
                    👥 بدون دانش‌آموز / ظرفیت آزاد (<?php echo toFarsiDigits($donors_without_students_count); ?>)
                </button>
                <button type="button" onclick="setFilter('active', this)" class="filter-tab px-3.5 py-2 rounded-xl bg-gray-100 text-gray-600 hover:bg-teal-50 hover:text-teal-700 transition-all shrink-0">
                    🔔 دارای یادآوری (<?php echo toFarsiDigits($active_reminders_count); ?>)
                </button>
                <button type="button" onclick="setFilter('due', this)" class="filter-tab px-3.5 py-2 rounded-xl bg-gray-100 text-gray-600 hover:bg-rose-50 hover:text-rose-700 transition-all shrink-0">
                    ⚠️ سررسید پیامک (<?php echo toFarsiDigits($due_reminders_count); ?>)
                </button>
            </div>

            <!-- Quick Action Links -->
            <div class="flex items-center gap-2 flex-wrap">
                <a href="people-list.php" class="bg-primary-50 hover:bg-primary-100 text-primary-900 border border-primary-200 px-3.5 py-2 rounded-xl text-xs font-black transition-all flex items-center gap-1.5 shrink-0">
                    <span>🎒</span> لیست دانش‌آموزان
                </a>
                <a href="admin/donor-reminders.php" class="bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-black px-3.5 py-2 rounded-xl text-xs transition-all shadow-md flex items-center gap-1.5 shrink-0">
                    <span>📱</span>
                    <span>پیامک‌های دوره‌ای</span>
                    <?php if ($due_reminders_count > 0): ?>
                        <span class="bg-rose-600 text-white text-[10px] px-2 py-0.5 rounded-full font-mono animate-pulse"><?php echo toFarsiDigits($due_reminders_count); ?> سررسید</span>
                    <?php endif; ?>
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
                    $sponsored_count = (int)$d['sponsored_count'];
                    
                    // Parse sponsored students list
                    $sponsored_students = [];
                    $sponsored_students_names = [];
                    if (!empty($d['sponsored_students_raw'])) {
                        $items = explode('|||', $d['sponsored_students_raw']);
                        foreach ($items as $item) {
                            $p = explode(':::', $item);
                            if (count($p) >= 4) {
                                $sponsored_students[] = [
                                    'sponsorship_id' => (int)$p[0],
                                    'student_id' => (int)$p[1],
                                    'name' => trim($p[2]),
                                    'shares' => (int)$p[3]
                                ];
                                $sponsored_students_names[] = trim($p[2]);
                            }
                        }
                    }

                    // Format WhatsApp Link
                    $clean_p = preg_replace('/[^0-9]/', '', $target_phone);
                    $wa_phone = '';
                    if (!empty($clean_p)) {
                        if (str_starts_with($clean_p, '09')) {
                            $wa_phone = '98' . substr($clean_p, 1);
                        } elseif (str_starts_with($clean_p, '989')) {
                            $wa_phone = $clean_p;
                        } else {
                            $wa_phone = '98' . $clean_p;
                        }
                    }

                    $donor_full_name = trim($d['name'] . ' ' . $d['surname']);
                    if ($sponsored_count > 0) {
                        $st_names_str = implode('، ', array_slice($sponsored_students_names, 0, 3));
                        if (count($sponsored_students_names) > 3) $st_names_str .= ' و...';
                        $wa_default_msg = "سلام و درود خدمت نیکوکار گرامی، جناب آقای/سرکار خانم {$donor_full_name}\nاز همراهی و حمایت‌های معنوی شما از فرزندان حکمت ({$st_names_str}) سپاسگزاریم.\nبنیاد خیریه حکمت";
                    } else {
                        $wa_default_msg = "سلام و درود خدمت نیکوکار گرامی، جناب آقای/سرکار خانم {$donor_full_name}\nاز همراهی ارزشمند شما با نخبگان بنیاد خیریه حکمت صمیمانه سپاسگزاریم.\nبنیاد خیریه حکمت";
                    }
                    $wa_url = !empty($wa_phone) ? "https://wa.me/{$wa_phone}?text=" . rawurlencode($wa_default_msg) : '';
                ?>
                <div class="donor-card bg-white rounded-[2.5rem] p-6 shadow-sm hover:shadow-xl border border-gray-100 transition-all duration-300 relative group flex flex-col justify-between"
                     data-name="<?php echo htmlspecialchars($donor_full_name . ' ' . $target_phone . ' ' . implode(' ', $sponsored_students_names)); ?>"
                     data-has-students="<?php echo $sponsored_count > 0 ? '1' : '0'; ?>"
                     data-active="<?php echo $has_reminder ? '1' : '0'; ?>"
                     data-due="<?php echo $is_due ? '1' : '0'; ?>">
                    
                    <!-- Top Bar: Rank & Badges -->
                    <div>
                        <div class="flex items-center justify-between mb-4">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 bg-primary-900 text-white rounded-xl flex items-center justify-center text-xs font-black font-mono shadow-sm">
                                    #<?php echo toFarsiDigits($index + 1); ?>
                                </div>
                                <?php if ($sponsored_count > 0): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-black bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <span>🎓</span> <?php echo toFarsiDigits($sponsored_count); ?> دانش‌آموز
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                        <span>⚠️</span> بدون دانش‌آموز
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div>
                                <?php if ($has_reminder): ?>
                                    <?php if ($is_due): ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-black bg-rose-50 text-rose-700 border border-rose-200 animate-pulse">
                                            <span>⚠️</span> موعد پیامک!
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[10px] font-black bg-teal-50 text-teal-700 border border-teal-200">
                                            <span>🔔</span> <?php echo SmsService::getIntervalLabel($d['reminder_interval_months'] ?? 1); ?>
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-[9px] font-bold text-gray-400 bg-gray-100 px-2 py-0.5 rounded-lg">
                                        🔕 بدون یادآوری
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Donor Profile Header (بدون علامت دست) -->
                        <div class="mb-4 text-right">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex-1 min-w-0">
                                    <h3 class="text-base font-black text-primary-900 leading-snug mb-1.5 break-words">
                                        <?php echo htmlspecialchars($donor_full_name); ?>
                                    </h3>
                                    <div class="flex items-center gap-2 flex-wrap text-[11px] font-bold text-gray-500">
                                        <span class="font-mono dir-ltr text-gray-600 bg-gray-50 px-2.5 py-0.5 rounded-lg border border-gray-100">
                                            <?php echo htmlspecialchars($target_phone ?: 'بدون تلفن ثبت‌شده'); ?>
                                        </span>
                                        <span class="text-teal-700 bg-teal-50/60 px-2.5 py-0.5 rounded-lg border border-teal-100">
                                            مجموع: <span class="font-mono font-black text-xs text-primary-900"><?php echo toFarsiDigits(number_format($d['total_donated'])); ?></span> ریال
                                        </span>
                                    </div>
                                </div>
                                <?php if (!empty($d['photo_path'])): ?>
                                    <div class="w-12 h-12 rounded-2xl overflow-hidden border border-gray-200 shadow-sm shrink-0">
                                        <img src="<?php echo htmlspecialchars($d['photo_path']); ?>" alt="پرتره" class="w-full h-full object-cover">
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- SPONSORSHIP STATUS BOX (کی به کی وصله) -->
                        <div class="w-full mb-4">
                            <?php if ($sponsored_count > 0): ?>
                                <div class="bg-gradient-to-r from-emerald-50/80 to-teal-50/80 border border-emerald-200/80 rounded-2xl p-3">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="text-[11px] font-black text-emerald-900 flex items-center gap-1.5">
                                            <span>💚</span>
                                            <span>فرزندان معنوی تحت پوشش:</span>
                                        </div>
                                        <button type="button" 
                                                onclick='openAssignModal(<?php echo $d["id"]; ?>, "<?php echo addslashes($donor_full_name); ?>")'
                                                class="text-[10px] font-black text-teal-700 hover:text-teal-900 bg-white/80 hover:bg-white px-2 py-0.5 rounded-lg border border-teal-200 shadow-xs transition-all">
                                            + سهم جدید
                                        </button>
                                    </div>
                                    <div class="flex flex-wrap gap-1.5 max-h-24 overflow-y-auto">
                                        <?php foreach ($sponsored_students as $st): ?>
                                            <div class="inline-flex items-center gap-1 bg-white px-2.5 py-1 rounded-xl border border-emerald-200 text-[11px] shadow-xs">
                                                <a href="person-detail.php?id=<?php echo $st['student_id']; ?>" 
                                                   class="font-black text-emerald-800 hover:text-teal-600 transition-colors flex items-center gap-1"
                                                   title="مشاهده پرونده تحصیلی دانش‌آموز">
                                                    <span>🎒</span>
                                                    <span><?php echo htmlspecialchars($st['name']); ?></span>
                                                </a>
                                                <?php if ($st['shares'] > 1): ?>
                                                    <span class="text-[9px] bg-emerald-100 text-emerald-800 font-black px-1.5 rounded-md font-mono">
                                                        <?php echo toFarsiDigits($st['shares']); ?> سهم
                                                    </span>
                                                <?php endif; ?>
                                                <button type="button" 
                                                        onclick="removeSponsorship(<?php echo $st['sponsorship_id']; ?>, '<?php echo addslashes($st['name']); ?>')" 
                                                        class="text-gray-300 hover:text-rose-500 font-bold text-[12px] pr-0.5" 
                                                        title="لغو این تخصیص">×</button>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="bg-amber-50/60 border border-amber-200/80 rounded-2xl p-3 flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="text-base">⚠️</span>
                                        <div>
                                            <div class="text-[11px] font-black text-amber-900">بدون دانش‌آموز اختصاصی</div>
                                            <div class="text-[9px] text-amber-700">ظرفیت پذیرش فرزند معنوی آزاد است</div>
                                        </div>
                                    </div>
                                    <button type="button" 
                                            onclick='openAssignModal(<?php echo $d["id"]; ?>, "<?php echo addslashes($donor_full_name); ?>")'
                                            class="bg-amber-600 hover:bg-amber-700 text-white text-[10px] font-black px-2.5 py-1.5 rounded-xl transition-all shadow-xs flex items-center gap-1">
                                        <span>+</span>
                                        <span>تخصیص دانش‌آموز</span>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Reminder Status Strip (If Active) -->
                        <?php if ($has_reminder): ?>
                            <div class="w-full p-2 rounded-xl text-xs mb-4 flex items-center justify-between border <?php echo $is_due ? 'bg-rose-50/80 border-rose-200 text-rose-800' : 'bg-gray-50 border-gray-200 text-gray-700'; ?>">
                                <span class="text-[10px] font-bold">موعد یادآوری بعدی:</span>
                                <span class="font-mono font-black text-[11px] <?php echo $is_due ? 'text-rose-700' : 'text-teal-700'; ?>">
                                    <?php echo toFarsiDigits($d['next_reminder_date'] ?: 'تعیین‌نشده'); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- ACTION BUTTONS: WhatsApp, Direct SMS, Reminder & Profile -->
                    <div class="pt-3 border-t border-gray-100 flex flex-col gap-2">
                        <!-- Direct Communication Row (WhatsApp + Melipayamak) -->
                        <div class="grid grid-cols-2 gap-2">
                            <?php if (!empty($wa_url)): ?>
                                <a href="<?php echo $wa_url; ?>" target="_blank" 
                                   class="bg-emerald-600 hover:bg-emerald-700 text-white py-2 px-3 rounded-xl text-xs font-black transition-all flex items-center justify-center gap-1.5 shadow-sm"
                                   title="ارسال پیام و احوال‌پرسی در واتس‌اپ">
                                    <span class="text-sm">💬</span>
                                    <span>واتس‌اپ</span>
                                </a>
                            <?php else: ?>
                                <button type="button" disabled class="bg-gray-100 text-gray-400 py-2 px-3 rounded-xl text-xs font-bold flex items-center justify-center gap-1.5 cursor-not-allowed">
                                    <span>💬</span>
                                    <span>فاقد شماره</span>
                                </button>
                            <?php endif; ?>

                            <button type="button" 
                                    onclick='openDirectSmsModal(<?php echo json_encode([
                                        "id" => $d["id"],
                                        "name" => $donor_full_name,
                                        "phone" => $target_phone,
                                        "students" => $sponsored_students_names
                                    ], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>)'
                                    class="bg-indigo-600 hover:bg-indigo-700 text-white py-2 px-3 rounded-xl text-xs font-black transition-all flex items-center justify-center gap-1.5 shadow-sm"
                                    title="ارسال پیامک از طریق سامانه ملی‌پیامک">
                                <span class="text-sm">✉️</span>
                                <span>ملی‌پیامک</span>
                            </button>
                        </div>

                        <!-- Management Row (Profile & Schedule) -->
                        <div class="flex items-center gap-2">
                            <a href="donor-detail.php?id=<?php echo $d['id']; ?>" 
                               class="flex-1 bg-primary-900 hover:bg-teal-600 text-white py-2 rounded-xl text-xs font-black transition-colors shadow-sm text-center">
                                مشاهده پرونده
                            </a>
                            <button type="button" onclick='openQuickReminderModal(<?php echo json_encode([
                                "id" => $d["id"],
                                "name" => $donor_full_name,
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
                                    title="تنظیم زمان‌بندی یادآوری دوره‌ای">
                                <span>⏰</span>
                                <span>یادآوری</span>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </main>

    <!-- 1. DIRECT MELIPAYAMAK SMS MODAL -->
    <div id="direct-sms-modal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 hidden">
        <div class="bg-white w-full max-w-lg rounded-[2.5rem] shadow-2xl p-7 border border-gray-100 relative max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-5 pb-4 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-indigo-50 text-indigo-600 flex items-center justify-center text-xl font-black">
                        ✉️
                    </div>
                    <div>
                        <h3 class="text-base font-black text-gray-900">ارسال پیامک با درگاه ملی‌پیامک</h3>
                        <p class="text-[11px] text-gray-500" id="sms-modal-donor-info">---</p>
                    </div>
                </div>
                <button type="button" onclick="closeDirectSmsModal()" class="text-gray-400 hover:text-gray-700 text-xl font-bold">✕</button>
            </div>

            <form id="directSmsForm" class="space-y-4">
                <input type="hidden" name="action" value="send_custom_donor_sms">
                <input type="hidden" name="donor_id" id="sms-modal-donor-id" value="">

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">شماره همراه مقصد</label>
                    <input type="text" name="phone" id="sms-modal-phone" placeholder="۰۹۱۲..." dir="ltr" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-xs text-left font-mono font-bold focus:outline-none focus:border-indigo-500">
                </div>

                <!-- Template Selector -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">انتخاب قالب متن آماده</label>
                    <div class="grid grid-cols-2 gap-2 text-[11px]">
                        <button type="button" onclick="applySmsTemplate('thanks')" class="p-2 rounded-xl bg-gray-50 hover:bg-indigo-50 border border-gray-200 hover:border-indigo-300 text-right font-bold text-gray-700 transition-all">
                            🙏 تشکر و قدردانی
                        </button>
                        <button type="button" onclick="applySmsTemplate('reminder')" class="p-2 rounded-xl bg-gray-50 hover:bg-indigo-50 border border-gray-200 hover:border-indigo-300 text-right font-bold text-gray-700 transition-all">
                            🔔 یادآوری واریز دوره
                        </button>
                        <button type="button" onclick="applySmsTemplate('bank')" class="p-2 rounded-xl bg-gray-50 hover:bg-indigo-50 border border-gray-200 hover:border-indigo-300 text-right font-bold text-gray-700 transition-all">
                            💳 ارسال شماره کارت بنیاد
                        </button>
                        <button type="button" onclick="applySmsTemplate('report')" class="p-2 rounded-xl bg-gray-50 hover:bg-indigo-50 border border-gray-200 hover:border-indigo-300 text-right font-bold text-gray-700 transition-all">
                            📊 احوال‌پرسی و گزارش تحصیلی
                        </button>
                    </div>
                </div>

                <!-- SMS Textarea -->
                <div>
                    <div class="flex justify-between items-center mb-1">
                        <label class="text-xs font-bold text-gray-700">متن پیامک ارسالی</label>
                        <span class="text-[10px] text-gray-400 font-mono" id="sms-char-counter">۰ کاراکتر (۱ صفحه)</span>
                    </div>
                    <textarea name="message" id="sms-modal-message" rows="5" oninput="updateCharCount()" 
                        placeholder="متن پیامک را اینجا بنویسید یا یکی از قالب‌های بالا را انتخاب کنید..." 
                        class="w-full bg-gray-50 border border-gray-200 rounded-xl p-3 text-xs text-gray-800 leading-relaxed focus:outline-none focus:border-indigo-500"></textarea>
                </div>

                <!-- Response Alert -->
                <div id="sms-modal-alert" class="hidden p-3 rounded-xl text-xs font-bold text-center"></div>

                <!-- Action Buttons -->
                <div class="flex items-center gap-2 pt-2 border-t border-gray-100">
                    <button type="button" id="btnSendSms" onclick="submitDirectSms()" class="flex-1 py-3 px-4 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-black transition-all shadow-md flex items-center justify-center gap-2">
                        <span>🚀</span>
                        <span>ارسال پیامک از طریق ملی‌پیامک</span>
                    </button>
                    <button type="button" onclick="closeDirectSmsModal()" class="py-3 px-4 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-bold transition-all">
                        انصراف
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 2. ASSIGN STUDENT TO DONOR MODAL (کی به کی وصله) -->
    <div id="assign-student-modal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 hidden">
        <div class="bg-white w-full max-w-md rounded-[2.5rem] shadow-2xl p-7 border border-gray-100 relative max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-5 pb-4 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl font-black">
                        🎓
                    </div>
                    <div>
                        <h3 class="text-base font-black text-gray-900">تخصیص فرزند معنوی به خیر</h3>
                        <p class="text-[11px] text-gray-500" id="assign-modal-donor-name">---</p>
                    </div>
                </div>
                <button type="button" onclick="closeAssignModal()" class="text-gray-400 hover:text-gray-700 text-xl font-bold">✕</button>
            </div>

            <form id="assignStudentForm" class="space-y-4">
                <input type="hidden" name="action" value="assign_student_to_donor">
                <input type="hidden" name="donor_id" id="assign-modal-donor-id" value="">

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">انتخاب دانش‌آموز (دانش‌آموزان بدون حامی در اولویت هستند)</label>
                    <select name="student_id" id="assign-modal-student-id" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-xs font-bold text-gray-800 focus:outline-none focus:border-emerald-500">
                        <option value="">-- لطفاً یک دانش‌آموز را انتخاب کنید --</option>
                        <?php foreach ($all_students_for_modal as $st_opt): ?>
                            <?php 
                                $s_count = (int)$st_opt['active_sponsors'];
                                $badge_text = ($s_count === 0) ? '⏳ بدون حامی' : "✓ دارای {$s_count} حامی";
                            ?>
                            <option value="<?php echo $st_opt['id']; ?>">
                                <?php echo htmlspecialchars($st_opt['name'] . ' ' . $st_opt['surname']); ?> 
                                (کد: <?php echo toFarsiDigits($st_opt['code']); ?> - <?php echo $badge_text; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">تعداد سهم حمایت مالی</label>
                    <input type="number" name="shares" id="assign-modal-shares" value="1" min="1" max="100" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-xs font-mono font-bold text-center focus:outline-none focus:border-emerald-500">
                    <p class="text-[10px] text-gray-400 mt-1">هر سهم معادل شهریه بورس ماهانه یک دانش‌آموز است.</p>
                </div>

                <!-- Response Alert -->
                <div id="assign-modal-alert" class="hidden p-3 rounded-xl text-xs font-bold text-center"></div>

                <!-- Action Buttons -->
                <div class="flex items-center gap-2 pt-2 border-t border-gray-100">
                    <button type="button" id="btnAssignSubmit" onclick="submitAssignStudent()" class="flex-1 py-3 px-4 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-black transition-all shadow-md">
                        ثبت و اتصال دانش‌آموز
                    </button>
                    <button type="button" onclick="closeAssignModal()" class="py-3 px-4 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-bold transition-all">
                        انصراف
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 3. QUICK REMINDER MODAL -->
    <div id="quick-reminder-modal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 hidden">
        <div class="bg-white w-full max-w-lg rounded-[2.5rem] shadow-2xl p-7 border border-gray-100 relative max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-5 pb-4 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl font-black">
                        ⏰
                    </div>
                    <div>
                        <h3 class="text-base font-black text-gray-900">تنظیم زمان‌بندی یادآوری دوره‌ای</h3>
                        <p class="text-[11px] text-gray-500" id="modal-donor-name">---</p>
                    </div>
                </div>
                <button type="button" onclick="closeQuickReminderModal()" class="text-gray-400 hover:text-gray-700 text-xl font-bold">✕</button>
            </div>

            <form id="quickReminderForm" class="space-y-4">
                <input type="hidden" name="action" value="save_donor_reminder">
                <input type="hidden" name="donor_id" id="modal-donor-id" value="">

                <!-- Active Toggle Switch -->
                <div class="bg-gray-50 p-3.5 rounded-2xl flex items-center justify-between border border-gray-100">
                    <div>
                        <label for="modal-reminder-active" class="text-xs font-black text-gray-800 block cursor-pointer">ارسال خودکار پیامک یادآوری</label>
                        <span class="text-[10px] text-gray-500">در صورت فعال بودن، در مواعد مقرر پیامک ارسال خواهد شد.</span>
                    </div>
                    <input type="checkbox" name="reminder_active" id="modal-reminder-active" value="1" class="w-5 h-5 text-teal-600 rounded focus:ring-teal-500">
                </div>

                <!-- Phone Number -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">شماره همراه جهت ارسال پیامک</label>
                    <input type="text" name="sms_phone" id="modal-sms-phone" placeholder="۰۹۱۲۳۴۵۶۷۸۹" dir="ltr" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-xs text-left font-mono font-bold focus:outline-none focus:border-teal-500">
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <!-- Day Selection -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">روز سررسید در ماه</label>
                        <select name="reminder_day" id="modal-reminder-day" onchange="autoCalculateNextDate(true)" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-xs font-bold text-gray-800 focus:outline-none focus:border-teal-500">
                            <?php for($i=1; $i<=31; $i++): ?>
                                <option value="<?php echo $i; ?>">روز <?php echo toFarsiDigits($i); ?>ام ماه</option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <!-- Interval Selection -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">دوره تکرار یادآوری</label>
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
                        <label class="block text-xs font-bold text-gray-700 mb-1">درگاه ارتباطی</label>
                        <select name="reminder_channel" id="modal-reminder-channel" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-xs font-bold text-gray-800 focus:outline-none focus:border-teal-500">
                            <option value="sms">📱 پیامک خودکار (SMS)</option>
                            <option value="whatsapp">💬 پیام در واتس‌اپ (WhatsApp)</option>
                            <option value="call">📞 تماس تلفنی و هماهنگی</option>
                        </select>
                    </div>

                    <!-- Shares / Student Count -->
                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">تعداد سهم بورسیه</label>
                        <input type="number" name="reminder_shares" id="modal-reminder-shares" value="1" min="1" max="1000" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-xs font-mono font-bold text-center focus:outline-none focus:border-teal-500">
                    </div>
                </div>

                <!-- Next Reminder Date -->
                <div>
                    <div class="flex justify-between items-center mb-1">
                        <label class="text-xs font-bold text-gray-700">موعد ارسال بعدی (تاریخ شمسی)</label>
                        <button type="button" onclick="autoCalculateNextDate(true)" class="text-[10px] text-teal-600 hover:text-teal-800 font-bold underline">
                            محاسبه از امروز
                        </button>
                    </div>
                    <input type="text" name="next_reminder_date" id="modal-next-date" placeholder="مثلاً: ۱۴۰۵/۰۷/۰۱" dir="ltr" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-xs text-left font-mono font-bold focus:outline-none focus:border-teal-500">
                </div>

                <!-- Custom Text (Optional) -->
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">متن سفارشی پیامک (اختیاری)</label>
                    <textarea name="custom_sms_text" id="modal-custom-text" rows="3" placeholder="در صورت خالی گذاشتن، متن استاندارد بنیاد حکمت ارسال خواهد شد..." class="w-full bg-gray-50 border border-gray-200 rounded-xl p-3 text-xs text-gray-700 focus:outline-none focus:border-teal-500"></textarea>
                    <p class="text-[10px] text-gray-400 mt-1">متغیرهای مجاز: {name}, {interval}, {card_number}, {link}</p>
                </div>

                <!-- Response Alert -->
                <div id="modal-alert" class="hidden p-3 rounded-xl text-xs font-bold text-center"></div>

                <!-- Buttons -->
                <div class="flex items-center gap-2 pt-2 border-t border-gray-100 flex-wrap">
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
        // Real-time Search and Filter Tabs
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
                const hasStudents = card.getAttribute('data-has-students') === '1';

                const matchesQuery = !query || name.includes(query);
                let matchesFilter = true;

                if (currentFilter === 'active') matchesFilter = isActive;
                else if (currentFilter === 'due') matchesFilter = isDue;
                else if (currentFilter === 'with_students') matchesFilter = hasStudents;
                else if (currentFilter === 'without_students') matchesFilter = !hasStudents;

                card.style.display = (matchesQuery && matchesFilter) ? 'flex' : 'none';
            });
        }

        // ==========================================
        // 1. DIRECT MELIPAYAMAK SMS MODAL LOGIC
        // ==========================================
        let currentSmsDonor = null;
        const directSmsModal = document.getElementById('direct-sms-modal');
        const smsAlertBox = document.getElementById('sms-modal-alert');
        const smsMessageInput = document.getElementById('sms-modal-message');

        function openDirectSmsModal(donorData) {
            currentSmsDonor = donorData;
            document.getElementById('sms-modal-donor-id').value = donorData.id;
            document.getElementById('sms-modal-donor-info').innerText = 'ارسال به: ' + donorData.name + ' (' + (donorData.phone || 'بدون شماره') + ')';
            document.getElementById('sms-modal-phone').value = donorData.phone || '';
            smsAlertBox.classList.add('hidden');
            
            // Set default text
            applySmsTemplate('thanks');
            directSmsModal.classList.remove('hidden');
        }

        function closeDirectSmsModal() {
            directSmsModal.classList.add('hidden');
        }

        function applySmsTemplate(type) {
            if (!currentSmsDonor) return;
            const name = currentSmsDonor.name;
            const students = (currentSmsDonor.students && currentSmsDonor.students.length > 0) 
                ? currentSmsDonor.students.join('، ') 
                : 'نخبگان بنیاد';

            let text = '';
            if (type === 'thanks') {
                text = `نیکوکار گرامی جناب آقای/سرکار خانم ${name} عزیز\nبا سلام و درود؛ از همراهی ارزشمند و حمایت‌های معنوی شما از فرزندان نخبگان بنیاد حکمت صمیمانه سپاسگزاریم.\nبنیاد خیریه حکمت`;
            } else if (type === 'reminder') {
                text = `نیکوکار گرامی جناب آقای/سرکار خانم ${name}\nبا سلام؛ موعد واریز دوره حمایت تحصیلی فرزند معنوی شما (${students}) فرا رسیده است. پیشاپیش از استمرار مهر و سخاوت شما سپاسگزاریم.\nشماره کارت بنیاد: ۶۰۳۷۹۹۷۹۵۰۰۰۱۲۳۴\nبنیاد حکمت`;
            } else if (type === 'bank') {
                text = `نیکوکار گرامی، مشخصات حساب بنیاد خیریه حکمت جهت واریز حمایت‌های تحصیلی:\nشماره کارت: ۶۰۳۷-۹۹۷۹-۵۰۰۰-۱۲۳۴\nشماره شبا: IR000000000000000000000000\nبه نام بنیاد خیریه حکمت\nسپاس از مهر پایدار شما`;
            } else if (type === 'report') {
                text = `نیکوکار گرامی جناب آقای/سرکار خانم ${name}\nگزارش تحصیلی و وضعیت پیشرفت فرزند معنوی شما (${students}) در کارتابل آموزشی بنیاد حکمت ثبت گردید. جهت مشاهده گزارش کامل یا در صورت تمایل به ارتباط بیشتر، همراه شماییم.\nبنیاد حکمت`;
            }
            smsMessageInput.value = text;
            updateCharCount();
        }

        function updateCharCount() {
            const len = smsMessageInput.value.length;
            const pages = Math.ceil(len / 70) || 1;
            document.getElementById('sms-char-counter').innerText = `${len} کاراکتر (${pages} صفحه پیامک)`;
        }

        async function submitDirectSms() {
            const form = document.getElementById('directSmsForm');
            const phone = document.getElementById('sms-modal-phone').value.trim();
            const msg = smsMessageInput.value.trim();

            if (!phone) {
                smsAlertBox.className = 'p-3 rounded-xl text-xs font-bold text-center bg-rose-50 text-rose-700 border border-rose-200 block';
                smsAlertBox.innerText = 'لطفاً شماره همراه معتبر وارد کنید.';
                return;
            }
            if (!msg) {
                smsAlertBox.className = 'p-3 rounded-xl text-xs font-bold text-center bg-rose-50 text-rose-700 border border-rose-200 block';
                smsAlertBox.innerText = 'متن پیامک نمی‌تواند خالی باشد.';
                return;
            }

            const btn = document.getElementById('btnSendSms');
            btn.disabled = true;
            btn.innerText = 'در حال ارسال پیامک با درگاه ملی‌پیامک...';

            smsAlertBox.className = 'p-3 rounded-xl text-xs font-bold text-center bg-blue-50 text-blue-700 block';
            smsAlertBox.innerText = 'ارتباط با وب‌سرویس ملی‌پیامک...';

            const formData = new FormData(form);

            try {
                const res = await fetch('admin-request-handler.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    smsAlertBox.className = 'p-3 rounded-xl text-xs font-bold text-center bg-emerald-50 text-emerald-700 border border-emerald-200 block';
                    smsAlertBox.innerText = '✓ ' + data.message;
                    setTimeout(() => {
                        closeDirectSmsModal();
                        btn.disabled = false;
                        btn.innerHTML = '<span>🚀</span><span>ارسال پیامک از طریق ملی‌پیامک</span>';
                    }, 1500);
                } else {
                    smsAlertBox.className = 'p-3 rounded-xl text-xs font-bold text-center bg-rose-50 text-rose-700 border border-rose-200 block';
                    smsAlertBox.innerText = '✕ ' + (data.message || 'خطا در ارسال پیامک');
                    btn.disabled = false;
                    btn.innerHTML = '<span>🚀</span><span>ارسال پیامک از طریق ملی‌پیامک</span>';
                }
            } catch (err) {
                smsAlertBox.className = 'p-3 rounded-xl text-xs font-bold text-center bg-rose-50 text-rose-700 border border-rose-200 block';
                smsAlertBox.innerText = 'خطا در ارتباط با سرور.';
                btn.disabled = false;
                btn.innerHTML = '<span>🚀</span><span>ارسال پیامک از طریق ملی‌پیامک</span>';
            }
        }

        // ==========================================
        // 2. ASSIGN STUDENT MODAL LOGIC (کی به کی وصله)
        // ==========================================
        const assignModal = document.getElementById('assign-student-modal');
        const assignAlertBox = document.getElementById('assign-modal-alert');

        function openAssignModal(donorId, donorName) {
            document.getElementById('assign-modal-donor-id').value = donorId;
            document.getElementById('assign-modal-donor-name').innerText = 'حامی: ' + donorName;
            document.getElementById('assign-modal-student-id').value = '';
            document.getElementById('assign-modal-shares').value = '1';
            assignAlertBox.classList.add('hidden');
            assignModal.classList.remove('hidden');
        }

        function closeAssignModal() {
            assignModal.classList.add('hidden');
        }

        async function submitAssignStudent() {
            const form = document.getElementById('assignStudentForm');
            const studentId = document.getElementById('assign-modal-student-id').value;
            if (!studentId) {
                assignAlertBox.className = 'p-3 rounded-xl text-xs font-bold text-center bg-rose-50 text-rose-700 border border-rose-200 block';
                assignAlertBox.innerText = 'لطفاً یک دانش‌آموز را انتخاب کنید.';
                return;
            }

            const btn = document.getElementById('btnAssignSubmit');
            btn.disabled = true;
            btn.innerText = 'در حال ثبت اتصال...';

            const formData = new FormData(form);

            try {
                const res = await fetch('admin-request-handler.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    assignAlertBox.className = 'p-3 rounded-xl text-xs font-bold text-center bg-emerald-50 text-emerald-700 border border-emerald-200 block';
                    assignAlertBox.innerText = '✓ ' + data.message;
                    setTimeout(() => location.reload(), 1200);
                } else {
                    assignAlertBox.className = 'p-3 rounded-xl text-xs font-bold text-center bg-rose-50 text-rose-700 border border-rose-200 block';
                    assignAlertBox.innerText = '✕ ' + (data.message || 'خطا در ثبت');
                    btn.disabled = false;
                    btn.innerText = 'ثبت و اتصال دانش‌آموز';
                }
            } catch (err) {
                assignAlertBox.className = 'p-3 rounded-xl text-xs font-bold text-center bg-rose-50 text-rose-700 border border-rose-200 block';
                assignAlertBox.innerText = 'خطا در برقراری ارتباط با سرور.';
                btn.disabled = false;
                btn.innerText = 'ثبت و اتصال دانش‌آموز';
            }
        }

        async function removeSponsorship(sponsorshipId, studentName) {
            if (!confirm(`آیا از لغو تخصیص و حمایت دانش‌آموز «${studentName}» از این خیر اطمینان دارید؟`)) return;

            const formData = new FormData();
            formData.append('action', 'remove_sponsorship');
            formData.append('sponsorship_id', sponsorshipId);

            try {
                const res = await fetch('admin-request-handler.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    alert('✓ ' + data.message);
                    location.reload();
                } else {
                    alert('✕ ' + (data.message || 'خطا در لغو'));
                }
            } catch (err) {
                alert('خطا در ارتباط با سرور.');
            }
        }

        // ==========================================
        // 3. QUICK REMINDER MODAL LOGIC
        // ==========================================
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
