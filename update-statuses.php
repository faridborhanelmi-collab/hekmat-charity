<?php
session_start();
require_once 'includes/db.php';

// Access Control
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    die("دسترسی غیرمجاز. لطفا ابتدا لاگین کنید.");
}

echo "<!DOCTYPE html><html lang='fa' dir='rtl'><head><meta charset='UTF-8'><title>بروزرسانی وضعیت مددجویان</title>
    <!-- iOS PWA/Homescreen Setup -->
    <link rel="apple-touch-icon" href="logo.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="بنیاد حکمت">
    <link rel="icon" type="image/png" href="logo.png">
    <link rel="manifest" href="manifest.json">
</head><body style='font-family: Tahoma, sans-serif; padding: 40px;'>";
echo "<h2>عملیات بروزرسانی وضعیت مددجویان</h2>";

try {
    // 1. Find all active students who did NOT receive a bursary in the latest month (1405/03/01 onwards)
    // First, find all student IDs who have expenses from 1405/03/01
    $stmt = $pdo->query("SELECT DISTINCT student_id FROM expenses WHERE expense_date >= '1405/03/01'");
    $active_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($active_ids)) {
        echo "<p style='color: red;'>هیچ پرداختی در ماه اخیر (۱۴۰۵/۰۳) یافت نشد. برای جلوگیری از غیرفعال شدن تمام مددجویان، عملیات لغو شد.</p>";
    } else {
        echo "<p>تعداد مددجویانی که در ماه اخیر بورسیه دریافت کرده‌اند: " . count($active_ids) . " نفر.</p>";
        
        // 2. Set everyone else who is 'active' to 'exited'
        $placeholders = str_repeat('?,', count($active_ids) - 1) . '?';
        $sql = "UPDATE students SET status = 'exited' WHERE status = 'active' AND id NOT IN ($placeholders) AND code != 'GENERAL'";
        
        $updateStmt = $pdo->prepare($sql);
        $updateStmt->execute($active_ids);
        
        $deactivated_count = $updateStmt->rowCount();
        
        echo "<p style='color: green;'>عملیات با موفقیت انجام شد! <b>$deactivated_count</b> نفر که در لیست بورسیه اخیر نبودند، به وضعیت «خروج از بورس» تغییر یافتند.</p>";
        
        echo "<br><br><a href='people-list.php' style='padding: 10px 20px; background: #14b8a6; color: white; text-decoration: none; border-radius: 8px;'>بازگشت به لیست مددجویان</a>";
    }

} catch (Exception $e) {
    echo "<p style='color: red;'>خطا در دیتابیس: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</body></html>";
?>
