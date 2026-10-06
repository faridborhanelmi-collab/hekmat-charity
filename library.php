<?php
if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    session_start();
}
require_once __DIR__ . '/includes/auth.php';

// Intelligent role-based routing:
// If the user is logged in as a student, direct them immediately to the student book catalog & borrowing repository
if (isset($_SESSION['role']) && $_SESSION['role'] === 'student') {
    header("Location: /student-dashboard.php?tab=books");
    exit();
}

// Fallback routing to ensure admins, supervisors and library operators access the admin library
require_once __DIR__ . '/admin/library.php';

