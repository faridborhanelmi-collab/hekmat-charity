<?php
// Database Configuration for SQLite (Supports isolated test database via HEKMAT_DB_PATH)
$db_path = getenv('HEKMAT_DB_PATH') ?: (__DIR__ . '/../hekmat.db');

try {
    $pdo = new PDO("sqlite:$db_path");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Performance & Concurrency PRAGMAs
    $pdo->setAttribute(PDO::ATTR_TIMEOUT, 5);
    $pdo->exec("PRAGMA busy_timeout = 5000;");
    $pdo->exec("PRAGMA journal_mode = WAL;");
    $pdo->exec("PRAGMA synchronous = NORMAL;");
    $pdo->exec("PRAGMA cache_size = -32000;");
    $pdo->exec("PRAGMA temp_store = MEMORY;");
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
 * Convert Farsi and Arabic digits to Latin/English digits
 */
if (!function_exists('toEnglishDigits')) {
    function toEnglishDigits($number) {
        if ($number === null || $number === '') return '';
        $farsi_array = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic_array = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $latin_array = range(0, 9);
        $number = (string)$number;
        $number = str_replace($farsi_array, $latin_array, $number);
        return str_replace($arabic_array, $latin_array, $number);
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
        return ($amount < 0 ? '-' : '') . $farsi;
    }
}

/**
 * تبدیل تاریخ میلادی به شمسی
 */
if (!function_exists('gregorian_to_jalali')) {
    function gregorian_to_jalali($gy, $gm, $gd) {
        $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        $gy2 = ($gm > 2) ? ($gy + 1) : $gy;
        $days = 355666 + (365 * $gy) + (int)(($gy2 + 3) / 4) - (int)(($gy2 + 99) / 100) + (int)(($gy2 + 399) / 400) + $gd + $g_d_m[$gm - 1];
        $jy = -1595 + (33 * (int)($days / 12053));
        $days %= 12053;
        $jy += 4 * (int)($days / 1461);
        $days %= 1461;
        if ($days > 365) {
            $jy += (int)(($days - 1) / 365);
            $days = ($days - 1) % 365;
        }
        if ($days < 186) {
            $jm = 1 + (int)($days / 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + (int)(($days - 186) / 30);
            $jd = 1 + (($days - 186) % 30);
        }
        return [$jy, $jm, $jd];
    }
}

/**
 * فرمت‌بندی تاریخ و ساعت شمسی
 */
if (!function_exists('formatJalaliDateTime')) {
    function formatJalaliDateTime($datetime) {
        if (!$datetime) return '---';
        $ts = strtotime($datetime);
        if (!$ts) return $datetime;
        $j = gregorian_to_jalali((int)date('Y', $ts), (int)date('m', $ts), (int)date('d', $ts));
        $formatted = sprintf('%04d/%02d/%02d ساعت %s', $j[0], $j[1], $j[2], date('H:i', $ts));
        return toFarsiDigits($formatted);
    }
}

/**
 * نمایش زمان به صورت نسبی (مثلاً چند دقیقه پیش، دیروز و...)
 */
if (!function_exists('time_ago_fa')) {
    function time_ago_fa($datetime) {
        if (!$datetime) return '---';
        $ts = strtotime($datetime);
        if (!$ts) return $datetime;
        $diff = time() - $ts;
        if ($diff < 60) {
            return 'لحظاتی پیش';
        } elseif ($diff < 3600) {
            $mins = max(1, floor($diff / 60));
            return toFarsiDigits($mins) . ' دقیقه پیش';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return toFarsiDigits($hours) . ' ساعت پیش';
        } elseif ($diff < 172800) {
            return 'دیروز ' . toFarsiDigits(date('H:i', $ts));
        } else {
            $days = floor($diff / 86400);
            if ($days < 30) {
                return toFarsiDigits($days) . ' روز پیش';
            } else {
                return formatJalaliDateTime($datetime);
            }
        }
    }
}
?>