<?php
/**
 * بنیاد خیریه حکمت - اسکریپت خودکار ارسال پیامک‌های یادآوری دوره‌ای به خیرین
 * این فایل می‌تواند از طریق Cron Job لینوکس به صورت روزانه اجرا شود،
 * یا از طریق فراخوانی وب همراه با کلید امنیتی (Secure Token).
 *
 * نمونه دستور در Crontab سرور (اجرای روزانه ساعت 10 صبح):
 * 0 10 * * * /usr/bin/php /home/hekmat.neromoda.ir/public_html/cron/send_reminders.php >> /home/hekmat.neromoda.ir/public_html/cron/cron_sms.log 2>&1
 */

// تعیین محیط اجرا (CLI یا Web)
$is_cli = (php_sapi_name() === 'cli' || empty($_SERVER['REMOTE_ADDR']));

// کلید امنیتی جهت فراخوانی از طریق وب
define('CRON_SECURITY_TOKEN', 'hekmat_secure_cron_token_2026');

if (!$is_cli) {
    $provided_token = $_GET['token'] ?? $_POST['token'] ?? '';
    if ($provided_token !== CRON_SECURITY_TOKEN) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'دسترسی غیرمجاز. کلید امنیتی معتبر نیست.'
        ], JSON_UNESCAPED_UNICODE);
        exit();
    }
}

// تنظیم منطقه زمانی و محدودیت حافظه/زمان
date_default_timezone_set('Asia/Tehran');
set_time_limit(300);
ini_set('memory_limit', '256M');

$base_dir = dirname(__DIR__);
require_once $base_dir . '/includes/db.php';
require_once $base_dir . '/includes/SmsService.php';

$smsService = new SmsService($pdo);
$today_jalali = SmsService::getCurrentJalaliDate();

$start_time = microtime(true);
$result = $smsService->processDueReminders();
$duration = round(microtime(true) - $start_time, 2);

$summary = [
    'timestamp' => date('Y-m-d H:i:s'),
    'jalali_date' => $today_jalali,
    'execution_duration_sec' => $duration,
    'total_due_found' => $result['total_due'],
    'success_sent' => $result['sent'],
    'failed_sent' => $result['failed'],
    'details' => $result['logs']
];

if ($is_cli) {
    echo "=========================================================\n";
    echo " بنیاد حکمت - سامانه خودکار یادآوری دوره‌ای نیکوکاران\n";
    echo " تاریخ امروز: " . $today_jalali . " | ساعت: " . date('H:i:s') . "\n";
    echo "=========================================================\n";
    echo " تعداد سررسیدهای امروز: " . $result['total_due'] . "\n";
    echo " ارسال‌های موفق: " . $result['sent'] . "\n";
    echo " ارسال‌های ناموفق: " . $result['failed'] . "\n";
    echo " زمان اجرا: " . $duration . " ثانیه\n";
    echo "---------------------------------------------------------\n";
    foreach ($result['logs'] as $item) {
        $status_mark = $item['success'] ? '[✓]' : '[✗]';
        echo " {$status_mark} خیر: {$item['donor_name']} | وضعیت: " . ($item['success'] ? 'موفق' : 'ناموفق') . " | {$item['message']}\n";
    }
    echo "=========================================================\n\n";
} else {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'success' => true,
        'message' => "عملیات ارسال پیامک‌های دوره‌ای سررسیدشده انجام شد.",
        'summary' => $summary
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
