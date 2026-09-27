<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';

$target = $_GET['target'] ?? '';
$current_role = $_SESSION['role'] ?? '';
$current_uid = (int)($_SESSION['user_id'] ?? 0);
$current_related = (int)($_SESSION['related_id'] ?? 0);
$current_username = strtolower($_SESSION['username'] ?? '');

$is_viana = is_viana() || (strtolower($_SESSION['temp_username'] ?? '') === 'viana');
$is_privileged_admin = has_role(['superadmin', 'secretary', 'education_deputy', 'admin']);

if (!$is_viana && !$is_privileged_admin) {
    header("Location: login.php");
    exit();
}

if ($target === 'operator') {
    // Switch into Library Data Operator role
    $_SESSION['user_id'] = 226;
    $_SESSION['username'] = 'viana';
    $_SESSION['user_name'] = 'ویانا وحیدی (اپراتور دیتابیس)';
    $_SESSION['role'] = 'data_operator';
    $_SESSION['is_admin'] = true;
    $_SESSION['is_superadmin'] = false;
    $_SESSION['is_board_member'] = false;
    $_SESSION['is_education_deputy'] = false;
    $_SESSION['related_id'] = 745;
    
    log_activity('سوئیچ به پنل اپراتور کتابخانه', 'user', 226, 'سوئیچ موفق ویانا وحیدی به نقش اپراتور دیتابیس کتابخانه');
    header("Location: admin/library.php");
    exit();
} elseif ($target === 'student') {
    // Switch into Student role
    $_SESSION['user_id'] = 745;
    $_SESSION['related_id'] = 745;
    $_SESSION['username'] = '0950305588';
    $_SESSION['user_name'] = 'ویانا وحیدی';
    $_SESSION['role'] = 'student';
    $_SESSION['is_admin'] = false;
    $_SESSION['is_superadmin'] = false;
    $_SESSION['is_board_member'] = false;
    $_SESSION['is_education_deputy'] = false;
    
    log_activity('سوئیچ به پورتال دانش‌آموزی', 'student', 745, 'سوئیچ موفق ویانا وحیدی به پورتال دانش‌پژوهی');
    header("Location: student-dashboard.php?student_id=745&tab=books");
    exit();
} else {
    header("Location: index.php");
    exit();
}
