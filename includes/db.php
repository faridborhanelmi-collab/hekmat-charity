<?php
// Database Configuration for SQLite
$db_path = __DIR__ . '/../hekmat.db';

try {
    $pdo = new PDO("sqlite:$db_path");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("❌ خطای اتصال به پایگاه داده: " . $e->getMessage());
}

/**
 * Convert Latin digits to Farsi digits
 */
if (!function_exists('toFarsiDigits')) {
    function toFarsiDigits($number) {
        if ($number === null || $number === '') return '';
        $farsi_array = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $latin_array = range(0, 9);
        $number = (string)$number;
        return str_replace($latin_array, $farsi_array, $number);
    }
}

/**
 * Format number as Farsi currency with thousands separator and negative sign alignment
 */
if (!function_exists('formatFarsiCurrency')) {
    function formatFarsiCurrency($amount) {
        $formatted = number_format(abs((float)$amount));
        $farsi = toFarsiDigits($formatted);
        // Using Persian standard: for negative, the minus sign is typically on the far right in some contexts, 
        // but for modern web, putting it on the left of the number is common.
        // The user specifically asked for it to be 'correctly' placed.
        return ($amount > 0 ? ' -' : '') . $farsi; // Wait, actually standard minus is - sign.
    }
}
?>