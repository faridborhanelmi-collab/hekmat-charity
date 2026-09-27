<?php
// api-diamond-submit.php - Process & Save Diamond Test Submissions
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/includes/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'درخواست نامعتبر است.']);
    exit;
}

// Read JSON input or POST form
$input_raw = file_get_contents('php://input');
if (empty($input_raw)) {
    $input_raw = @file_get_contents('php://stdin');
}
$data = json_decode($input_raw, true);
if (!$data) {
    $data = $_POST;
}

$full_name = trim($data['full_name'] ?? '');
$national_id = trim($data['national_id'] ?? '');
$phone = trim($data['phone'] ?? '');
$grade = trim($data['grade'] ?? '');
$city = trim($data['city'] ?? '');
$school_name = trim($data['school_name'] ?? '');
$time_spent_seconds = intval($data['time_spent_seconds'] ?? 0);
$answers = $data['answers'] ?? [];

// Basic validation
if (empty($full_name) || empty($national_id) || empty($phone) || empty($grade) || empty($city)) {
    echo json_encode(['success' => false, 'message' => 'لطفاً تمامی اطلاعات ضروری (نام، کد ملی، شماره تماس، مقطع و شهر) را وارد نمایید.']);
    exit;
}

// Digits standardization (Persian/Arabic to English)
$persian_digits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
$arabic_digits  = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
$english_digits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

$national_id = str_replace($persian_digits, $english_digits, $national_id);
$national_id = str_replace($arabic_digits, $english_digits, $national_id);
$national_id = preg_replace('/[^0-9]/', '', $national_id);

$phone = str_replace($persian_digits, $english_digits, $phone);
$phone = str_replace($arabic_digits, $english_digits, $phone);
$phone = preg_replace('/[^0-9]/', '', $phone);

// Iranian National ID Checksum Algorithm
function is_valid_national_id($code) {
    if (!preg_match('/^[0-9]{10}$/', $code)) {
        return false;
    }
    // Reject repeated patterns like 0000000000, 1111111111, etc.
    for ($i = 0; $i <= 9; $i++) {
        if ($code === str_repeat((string)$i, 10)) {
            return false;
        }
    }
    $sum = 0;
    for ($i = 0; $i < 9; $i++) {
        $sum += ((int)$code[$i]) * (10 - $i);
    }
    $rem = $sum % 11;
    $check_digit = (int)$code[9];
    return ($rem < 2 && $check_digit === $rem) || ($rem >= 2 && $check_digit === (11 - $rem));
}

if (!is_valid_national_id($national_id)) {
    echo json_encode(['success' => false, 'message' => 'کد ملی وارد شده نامعتبر است. لطفاً کد ملی صحیح ۱۰ رقمی منطبق با ثبت‌احوال را وارد کنید.']);
    exit;
}

if (!preg_match('/^09[0-9]{9}$/', $phone)) {
    echo json_encode(['success' => false, 'message' => 'شماره موبایل وارد شده نامعتبر است. لطفاً شماره معتبر ۱۱ رقمی (مانند ۰۹۱۲۳۴۵۶۷۸۹) وارد کنید.']);
    exit;
}

// Check for single-attempt constraint (One attempt per National ID)
$dup_check = $pdo->prepare("SELECT id, score, total_questions, tier, created_at FROM diamond_candidates WHERE national_id = ?");
$dup_check->execute([$national_id]);
$previous_attempt = $dup_check->fetch(PDO::FETCH_ASSOC);

if ($previous_attempt) {
    echo json_encode([
        'success' => false,
        'already_participated' => true,
        'message' => "داوطلب گرامی، شما قبلاً با کد ملی {$national_id} در آزمون پویش گنج‌های پنهان شرکت کرده‌اید (نمره ثبت‌شده شما: {$previous_attempt['score']} از {$previous_attempt['total_questions']} - سطح: {$previous_attempt['tier']}). هر شخص فقط یک‌بار مجاز به شرکت است."
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Official Server-Side Answer Key for the 15 Standard Questions (1-indexed options: 1, 2, 3, 4)
$official_answer_key = [
    1 => 2,
    2 => 3,
    3 => 4,
    4 => 1,
    5 => 3,
    6 => 2,
    7 => 4,
    8 => 1,
    9 => 3,
    10 => 2,
    11 => 4,
    12 => 3,
    13 => 1,
    14 => 4,
    15 => 2
];

$total_questions = count($official_answer_key);
$score = 0;
$detailed_answers = [];

foreach ($official_answer_key as $q_num => $correct_opt) {
    $user_ans = isset($answers[$q_num]) ? intval($answers[$q_num]) : null;
    $is_correct = ($user_ans === $correct_opt);
    if ($is_correct) {
        $score++;
    }
    $detailed_answers[$q_num] = [
        'selected' => $user_ans,
        'correct' => $correct_opt,
        'is_correct' => $is_correct
    ];
}

$percentage = round(($score / $total_questions) * 100, 1);

// Psychological and cognitive tiering
if ($score >= 13) {
    $tier = 'درخشان و استثنایی';
    $message = 'تبریک صمیمانه! شما پتانسیل شناختی و سرعت درک الگوهای فوق‌العاده‌ای نشان دادید. با توجه به این نتیجه شگفت‌انگیز، نام شما در اولویت اول بررسی کارشناسان بنیاد حکمت برای دعوت به مرحله دوم حضوری و دریافت بورس تحصیلی کامل قرار گرفت.';
} elseif ($score >= 10) {
    $tier = 'بسیار مستعد و پیشرفته';
    $message = 'عالی بود! توانایی بالای شما در استدلال تصویری و حل مسئله نشان‌دهنده یک ذهن پویا و فعال است. پرونده شما برای بررسی جهت حضور در کارگاه استعدادیابی مرحله دوم ثبت شد.';
} elseif ($score >= 7) {
    $tier = 'مستعد و توانمند';
    $message = 'آفرین به تلاش شما! شما پتانسیل خوبی در حل پازل‌های منطقی دارید. عملکرد شما به همراه سایر داوطلبان توسط تیم مشاوره بنیاد حکمت ارزیابی خواهد شد.';
} else {
    $tier = 'در حال رشد و جویای تلاش';
    $message = 'خداقوت به شجاعت و شرکت شما در این چالش! هر آزمونی یک تمرین است. بنیاد حکمت همیشه همراه تمام فرزندان ایران در مسیر یادگیری است.';
}

$ip_address = $_SERVER['REMOTE_ADDR'] ?? '';

try {
    $stmt = $pdo->prepare("INSERT INTO diamond_candidates 
        (full_name, national_id, phone, grade, city, school_name, score, total_questions, percentage, time_spent_seconds, answers_detail, ip_address, status, tier) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
        
    $stmt->execute([
        $full_name,
        $national_id,
        $phone,
        $grade,
        $city,
        $school_name,
        $score,
        $total_questions,
        $percentage,
        $time_spent_seconds,
        json_encode($detailed_answers, JSON_UNESCAPED_UNICODE),
        $ip_address,
        $tier
    ]);

    $submission_id = $pdo->lastInsertId();

    echo json_encode([
        'success' => true,
        'submission_id' => $submission_id,
        'score' => $score,
        'total' => $total_questions,
        'percentage' => $percentage,
        'tier' => $tier,
        'message' => $message
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'خطای ثبت اطلاعات در سیستم: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
