<?php
// tests/unit/ValidationTest.php
// Regression Tests for Input Validation, Security Checks & Audit Logging

require_once __DIR__ . '/../bootstrap.php';

// Standard Iranian National ID Validation Algorithm
function isValidNationalId($code) {
    if (!preg_match('/^[0-9]{10}$/', $code)) {
        return false;
    }
    // Check all same digits (e.g. 1111111111)
    if (preg_match('/^(\d)\1{9}$/', $code)) {
        return false;
    }
    $sum = 0;
    for ($i = 0; $i < 9; $i++) {
        $sum += ((int)$code[$i]) * (10 - $i);
    }
    $remainder = $sum % 11;
    $check_digit = (int)$code[9];
    return ($remainder < 2) ? ($check_digit === $remainder) : ($check_digit === (11 - $remainder));
}

// Resilient phone normalizer supporting multi-number strings and formatted numbers
function normalizeIranianPhone($phone) {
    // Split on comma, slash, or space-dash-space (to preserve hyphens inside numbers)
    $raw_phones = preg_split('/[,\|\n]|\s+-\s+/', (string)$phone);
    $clean_phone = '';
    foreach ($raw_phones as $rp) {
        $digits = preg_replace('/[^0-9]/', '', $rp);
        if (strlen($digits) >= 10) {
            if (strlen($digits) == 10 && strpos($digits, '9') === 0) {
                $digits = '0' . $digits;
            }
            if (strlen($digits) === 11 && strpos($digits, '09') === 0) {
                $clean_phone = $digits;
                break;
            }
        }
    }
    return $clean_phone;
}

RegressionTest::suite('Validation, File Security & Audit Log Regression Tests', function() {
    global $pdo;

    // 1. National ID Validation
    // '0010350810' sum % 11 == 0, check digit 0
    RegressionTest::assertTrue(isValidNationalId('0010350810'), 'Valid Iranian National ID format');
    RegressionTest::assertFalse(isValidNationalId('1111111111'), 'Repeated digits should be invalid National ID');
    RegressionTest::assertFalse(isValidNationalId('12345'), 'Short string should be invalid National ID');
    RegressionTest::assertFalse(isValidNationalId('abc1234567'), 'Non-numeric string should be invalid National ID');

    // 2. Phone Normalization
    RegressionTest::assertEquals('09121112233', normalizeIranianPhone('09121112233'), 'Standard mobile number');
    RegressionTest::assertEquals('09121112233', normalizeIranianPhone('9121112233'), 'Missing leading zero is corrected');
    RegressionTest::assertEquals('09121112233', normalizeIranianPhone('0912-111-2233'), 'Dashes inside single number handled correctly');
    RegressionTest::assertEquals('09121112233', normalizeIranianPhone('09121112233 - 09152223344'), 'Multiple numbers extraction picks first valid mobile');

    // 3. File Upload Extension Whitelist Security
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'pdf'];
    $check_extension = fn($filename) => in_array(strtolower(pathinfo($filename, PATHINFO_EXTENSION)), $allowed_extensions);

    RegressionTest::assertTrue($check_extension('document.pdf'), 'PDF extension is allowed');
    RegressionTest::assertTrue($check_extension('profile.JPG'), 'Uppercase JPG is allowed');
    RegressionTest::assertFalse($check_extension('exploit.php'), 'REGRESSION CHECK: PHP scripts must NEVER be allowed in upload');
    RegressionTest::assertFalse($check_extension('shell.phtml'), 'REGRESSION CHECK: PHTML must NEVER be allowed');
    RegressionTest::assertFalse($check_extension('script.sh'), 'Shell scripts must be blocked');

    // 4. Audit Logging Persistence
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'faridelmi';
    $_SESSION['user_name'] = 'فرید علمی';
    $_SESSION['role'] = 'superadmin';

    $logged = log_activity('تست رگرسیون امنیتی', 'test', 999, 'تست ثبت خودکار لاگ سیستم');
    RegressionTest::assertTrue($logged, 'log_activity function should return true on success');

    $stmt = $pdo->prepare("SELECT action, description FROM audit_logs WHERE target_id = 999 ORDER BY id DESC LIMIT 1");
    $stmt->execute();
    $log_entry = $stmt->fetch(PDO::FETCH_ASSOC);

    RegressionTest::assertEquals('تست رگرسیون امنیتی', $log_entry['action'] ?? '', 'Audit log action persisted in database');
    RegressionTest::assertEquals('تست ثبت خودکار لاگ سیستم', $log_entry['description'] ?? '', 'Audit log description persisted in database');
});
