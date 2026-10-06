<?php
// api-diamond-check.php - Check Candidate Eligibility Prior to Starting Diamond Test
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/includes/db.php';

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'درخواست نامعتبر است.']);
    exit;
}

// Read input from JSON body or POST form
$input_raw = file_get_contents('php://input');
if (empty($input_raw)) {
    $input_raw = @file_get_contents('php://stdin');
}
$data = json_decode($input_raw, true);
if (!$data) {
    $data = $_POST;
}

$national_id = trim($data['national_id'] ?? '');
$phone = trim($data['phone'] ?? '');

// Convert Persian/Arabic digits to English
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
function is_valid_national_id_check($code) {
    if (!preg_match('/^[0-9]{10}$/', $code)) {
        return false;
    }
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

if (!empty($national_id) && !is_valid_national_id_check($national_id)) {
    echo json_encode([
        'success' => false,
        'field' => 'national_id',
        'message' => 'کد ملی وارد شده نامعتبر است. لطفاً کد ملی صحیح ۱۰ رقمی منطبق با ثبت‌احوال را وارد کنید.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!empty($phone) && !preg_match('/^09[0-9]{9}$/', $phone)) {
    echo json_encode([
        'success' => false,
        'field' => 'phone',
        'message' => 'شماره موبایل وارد شده نامعتبر است. لطفاً شماره معتبر ۱۱ رقمی (مانند ۰۹۱۲۳۴۵۶۷۸۹) وارد کنید.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (empty($national_id) && empty($phone)) {
    echo json_encode([
        'success' => false,
        'message' => 'لطفاً کد ملی یا شماره همراه را وارد کنید.'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Check database for existing participation
$where_clauses = [];
$params = [];

if (!empty($national_id)) {
    $where_clauses[] = "national_id = ?";
    $params[] = $national_id;
}

if (!empty($phone)) {
    $where_clauses[] = "(phone = ? OR phone LIKE ?)";
    $params[] = $phone;
    $params[] = '%' . substr($phone, -10);
}

$sql = "SELECT id, full_name, national_id, phone, score, total_questions, tier, created_at 
        FROM diamond_candidates 
        WHERE " . implode(' OR ', $where_clauses) . " 
        ORDER BY id DESC LIMIT 1";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$existing = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing) {
    $matched_reason = (!empty($existing['national_id']) && $existing['national_id'] === $national_id) 
        ? "کد ملی ({$national_id})" 
        : "شماره همراه ({$phone})";
    $date_formatted = formatJalaliDateTime($existing['created_at']);
    $cand_name = htmlspecialchars($existing['full_name']);
    $score_val = toFarsiDigits($existing['score']);
    $total_val = toFarsiDigits($existing['total_questions'] ?: 15);
    $tier_label = htmlspecialchars($existing['tier'] ?: 'ثبت‌شده');

    $msg = "داوطلب گرامی {$cand_name}؛ شما قبلاً در تاریخ {$date_formatted} با این {$matched_reason} در آزمون پویش گنج‌های پنهان شرکت کرده‌اید (نمره ثبت‌شده شما: {$score_val} از {$total_val} - سطح: «{$tier_label}»). طبق ضوابط بنیاد حکمت، هر شخص فقط یک‌بار مجاز به شرکت در چالش است و امکان آزمون مجدد وجود ندارد.";

    echo json_encode([
        'success' => false,
        'eligible' => false,
        'already_participated' => true,
        'candidate_name' => $existing['full_name'],
        'score' => $existing['score'],
        'total_questions' => $existing['total_questions'] ?: 15,
        'tier' => $existing['tier'],
        'created_at' => $existing['created_at'],
        'created_at_fa' => $date_formatted,
        'message' => $msg
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// All clear - candidate is eligible!
echo json_encode([
    'success' => true,
    'eligible' => true,
    'already_participated' => false,
    'message' => 'داوطلب مجاز به شرکت در آزمون است.'
], JSON_UNESCAPED_UNICODE);
exit;
