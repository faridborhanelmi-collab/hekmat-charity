<?php
session_start();
if (isset($_SESSION['user_id'])) {
    require_once __DIR__ . '/includes/db.php';
    require_once __DIR__ . '/includes/auth.php';
    log_activity('خروج از سیستم', 'user', $_SESSION['user_id'], 'خروج کاربر از سامانه');
}
session_unset();
session_destroy();
header("Location: login.php");
exit();
?>
