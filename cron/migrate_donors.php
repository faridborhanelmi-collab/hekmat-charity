<?php
/**
 * هماهنگ‌سازی و مهاجرت دیتابیس سامانه یادآوری دوره‌ای خیرین بنیاد حکمت
 * این اسکریپت فیلدهای reminder_day، reminder_channel، reminder_shares را در جدول donors ایجاد کرده
 * و ۱۳ نیکوکار اعلام‌شده را با روزهای سررسید، کانال‌ها و سهم‌هایشان پیکربندی می‌کند.
 */

$base_dir = dirname(__DIR__);
require_once $base_dir . '/includes/db.php';
require_once $base_dir . '/includes/SmsService.php';

echo "=== شروع به‌روزرسانی و مهاجرت ساختار سامانه یادآوری خیرین ===\n";

// ۱. افزودن ستون‌ها در صورت عدم وجود
$cols = $pdo->query("PRAGMA table_info(donors)")->fetchAll(PDO::FETCH_ASSOC);
$col_names = array_column($cols, 'name');

if (!in_array('reminder_day', $col_names)) {
    $pdo->exec("ALTER TABLE donors ADD COLUMN reminder_day INTEGER DEFAULT 1");
    echo "✓ ستون reminder_day با موفقیت افزوده شد.\n";
}
if (!in_array('reminder_channel', $col_names)) {
    $pdo->exec("ALTER TABLE donors ADD COLUMN reminder_channel TEXT DEFAULT 'sms'");
    echo "✓ ستون reminder_channel با موفقیت افزوده شد.\n";
}
if (!in_array('reminder_shares', $col_names)) {
    $pdo->exec("ALTER TABLE donors ADD COLUMN reminder_shares INTEGER DEFAULT 1");
    echo "✓ ستون reminder_shares با موفقیت افزوده شد.\n";
}

$today = SmsService::getCurrentJalaliDate();
$parts = explode('/', $today);
$cur_y = (int)$parts[0];
$cur_m = (int)$parts[1];
$cur_d = (int)$parts[2];

// ۱۳ خیر مشخص‌شده
$donor_configs = [
    [
        'name_match' => 'حمید',
        'surname_match' => 'قربانی',
        'first_name' => 'حمید',
        'last_name' => 'قربانی',
        'phone' => '09121111111',
        'reminder_day' => 5,
        'interval' => 1,
        'channel' => 'sms',
        'shares' => 1
    ],
    [
        'name_match' => 'مسعود',
        'surname_match' => 'توکلی',
        'first_name' => 'مسعود',
        'last_name' => 'توکلی',
        'phone' => '09122222222',
        'reminder_day' => 1,
        'interval' => 3,
        'channel' => 'sms',
        'shares' => 1
    ],
    [
        'name_match' => 'رضا',
        'surname_match' => 'دانشمند',
        'first_name' => 'رضا',
        'last_name' => 'دانشمند',
        'phone' => '09123333333',
        'reminder_day' => 1,
        'interval' => 1,
        'channel' => 'sms',
        'shares' => 1
    ],
    [
        'name_match' => 'حامد',
        'surname_match' => 'حیدری',
        'first_name' => 'حامد',
        'last_name' => 'حیدری',
        'phone' => '09124444444',
        'reminder_day' => 3,
        'interval' => 1,
        'channel' => 'sms',
        'shares' => 1
    ],
    [
        'name_match' => 'امید',
        'surname_match' => 'امینیان',
        'first_name' => 'امید',
        'last_name' => 'امینیان',
        'phone' => '09125555555',
        'reminder_day' => 1,
        'interval' => 1,
        'channel' => 'sms',
        'shares' => 1
    ],
    [
        'name_match' => 'مجتبی',
        'surname_match' => 'اسدی',
        'first_name' => 'مجتبی',
        'last_name' => 'اسدی',
        'phone' => '09126666666',
        'reminder_day' => 15,
        'interval' => 1,
        'channel' => 'sms',
        'shares' => 1
    ],
    [
        'name_match' => 'علی',
        'surname_match' => 'کارآموز',
        'first_name' => 'علی',
        'last_name' => 'کارآموز',
        'phone' => '09127777777',
        'reminder_day' => 1,
        'interval' => 1,
        'channel' => 'sms',
        'shares' => 1
    ],
    [
        'name_match' => 'مجتبی',
        'surname_match' => 'موسسی',
        'first_name' => 'مجتبی',
        'last_name' => 'موسسی',
        'phone' => '09128888888',
        'reminder_day' => 21,
        'interval' => 1,
        'channel' => 'whatsapp',
        'shares' => 1
    ],
    [
        'name_match' => 'منشی',
        'surname_match' => 'ثقه‌الاسلامی',
        'first_name' => 'منشی محمد',
        'last_name' => 'ثقه‌الاسلامی',
        'phone' => '09129999999',
        'reminder_day' => 5,
        'interval' => 1,
        'channel' => 'sms',
        'shares' => 1
    ],
    [
        'name_match' => '',
        'surname_match' => 'سروش',
        'first_name' => 'خانم',
        'last_name' => 'سروش',
        'phone' => '09123937359',
        'reminder_day' => 2,
        'interval' => 1,
        'channel' => 'sms',
        'shares' => 1
    ],
    [
        'name_match' => 'رضا',
        'surname_match' => 'حسینی',
        'first_name' => 'رضا',
        'last_name' => 'حسینی',
        'phone' => '09120000001',
        'reminder_day' => 1,
        'interval' => 1,
        'channel' => 'sms',
        'shares' => 1
    ],
    [
        'name_match' => 'مهران',
        'surname_match' => 'محرمیان',
        'first_name' => 'مهران',
        'last_name' => 'محرمیان',
        'phone' => '09120000002',
        'reminder_day' => 1,
        'interval' => 1,
        'channel' => 'sms',
        'shares' => 1
    ],
    [
        'name_match' => 'مهدی',
        'surname_match' => 'تولیت',
        'first_name' => 'مهدی',
        'last_name' => 'تولیت',
        'phone' => '09120000003',
        'reminder_day' => 1,
        'interval' => 1,
        'channel' => 'sms',
        'shares' => 20
    ],
];

foreach ($donor_configs as $cfg) {
    // جستجو بر اساس نام و نام خانوادگی یا شماره تلفن
    if (!empty($cfg['name_match'])) {
        $stmt = $pdo->prepare("SELECT * FROM donors WHERE name LIKE ? AND surname LIKE ? LIMIT 1");
        $stmt->execute(['%' . $cfg['name_match'] . '%', '%' . $cfg['surname_match'] . '%']);
    } else {
        $stmt = $pdo->prepare("SELECT * FROM donors WHERE (surname = ? OR phone = ? OR sms_phone = ?) AND surname != 'ثقفی' LIMIT 1");
        $stmt->execute([$cfg['surname_match'], $cfg['phone'], $cfg['phone']]);
    }
    $donor = $stmt->fetch(PDO::FETCH_ASSOC);

    // محاسبه تاریخ سررسید بعدی
    if ($cfg['reminder_day'] >= $cur_d) {
        $next_date = sprintf('%04d/%02d/%02d', $cur_y, $cur_m, min($cfg['reminder_day'], $cur_m <= 6 ? 31 : 30));
    } else {
        $next_date = SmsService::calculateNextReminderDate($cfg['interval'], $cfg['reminder_day'], $today);
    }

    if ($donor) {
        $upd = $pdo->prepare("
            UPDATE donors 
            SET reminder_active = 1,
                reminder_day = ?,
                reminder_interval_months = ?,
                reminder_channel = ?,
                reminder_shares = ?,
                next_reminder_date = COALESCE(NULLIF(next_reminder_date, ''), ?)
            WHERE id = ?
        ");
        $upd->execute([
            $cfg['reminder_day'],
            $cfg['interval'],
            $cfg['channel'],
            $cfg['shares'],
            $next_date,
            $donor['id']
        ]);
        echo "✓ خیر شناسه {$donor['id']} ({$donor['name']} {$donor['surname']}): سررسید روز {$cfg['reminder_day']} | دوره {$cfg['interval']} ماهه | درگاه {$cfg['channel']} | {$cfg['shares']} سهم به‌روزرسانی شد.\n";
    } else {
        $ins = $pdo->prepare("
            INSERT INTO donors (
                name, surname, phone, sms_phone, join_date, reminder_active,
                reminder_day, reminder_interval_months, reminder_channel, reminder_shares,
                next_reminder_date
            ) VALUES (?, ?, ?, ?, ?, 1, ?, ?, ?, ?, ?)
        ");
        $ins->execute([
            $cfg['first_name'],
            $cfg['last_name'],
            $cfg['phone'],
            $cfg['phone'],
            $today,
            $cfg['reminder_day'],
            $cfg['interval'],
            $cfg['channel'],
            $cfg['shares'],
            $next_date
        ]);
        $new_id = $pdo->lastInsertId();
        echo "+ خیر جدید ثبت گردید: شناسه {$new_id} ({$cfg['first_name']} {$cfg['last_name']}) | سررسید روز {$cfg['reminder_day']} | درگاه {$cfg['channel']}.\n";
    }
}

echo "=== پایان موفقیت‌آمیز مهاجرت و تنظیم خیرین ===\n";
