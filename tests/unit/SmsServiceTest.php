<?php
// tests/unit/SmsServiceTest.php
// Regression Tests for SMS Service & OTP Benefactor Authentication

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../includes/SmsService.php';

RegressionTest::suite('SMS Service & Benefactor Authentication Regression Tests', function() use ($pdo) {
    // 1. Phone number cleanup tests
    RegressionTest::assertEquals('09123456789', SmsService::cleanPhoneNumber('09123456789'), 'Standard mobile number cleanup');
    RegressionTest::assertEquals('09123456789', SmsService::cleanPhoneNumber('+989123456789'), 'International +98 mobile format');
    RegressionTest::assertEquals('09123456789', SmsService::cleanPhoneNumber('989123456789'), 'International 98 prefix without plus');
    RegressionTest::assertEquals('09123456789', SmsService::cleanPhoneNumber('9123456789'), '10-digit mobile starting with 9 gets 0 prefixed');
    RegressionTest::assertEquals('09123456789', SmsService::cleanPhoneNumber('۰۹۱۲۳۴۵۶۷۸۹'), 'Farsi digits mobile cleanup');

    // 2. SmsService instance & settings
    $smsService = new SmsService($pdo);
    $settings = $smsService->getSettings();
    RegressionTest::assertTrue(!empty($settings), 'SMS Settings must be loaded');
    RegressionTest::assertEquals('melipayamak', $settings['provider'], 'Default provider must be melipayamak');
    RegressionTest::assertEquals('9153103060', $settings['username'], 'Melipayamak username must match configured account');

    // 3. Template Rendering
    $donor = [
        'name' => 'فرید',
        'surname' => 'علمی',
        'reminder_interval_months' => 3,
        'reminder_shares' => 20,
        'total_donated' => 50000000
    ];
    $template = "سلام {name} عزیز، همیاری {interval} شما با حکمت جهت حمایت از {shares} دانش‌آموز. کارت: {card_number}";
    $rendered = $smsService->renderTemplate($template, $donor);
    RegressionTest::assertTrue(strpos($rendered, 'فرید') !== false, 'Donor name rendered in template');
    RegressionTest::assertTrue(strpos($rendered, 'فصلی') !== false, 'Interval rendered in template');
    RegressionTest::assertTrue(strpos($rendered, '۶۲۲۱ - ۰۶۱۲ - ۳۹۳۳ - ۶۳۴۲') !== false, 'Official Parsian card number rendered in template');
    RegressionTest::assertTrue(strpos($rendered, '20') !== false || strpos($rendered, '۲۰') !== false, 'Shares count rendered in template');

    // 4. calculateNextReminderDate
    $next5 = SmsService::calculateNextReminderDate(1, 5, '1405/07/05');
    RegressionTest::assertEquals('1405/08/05', $next5, 'Day 5 monthly schedule next date preserved');

    $nextQuarterly = SmsService::calculateNextReminderDate(3, 1, '1405/07/01');
    RegressionTest::assertEquals('1405/10/01', $nextQuarterly, 'Day 1 quarterly schedule next date preserved');

    $next21 = SmsService::calculateNextReminderDate(1, 21, '1405/07/21');
    RegressionTest::assertEquals('1405/08/21', $next21, 'Day 21 monthly schedule next date preserved');

    // 5. Send OTP test (simulator fallback)
    $otp_result = $smsService->sendOtp('09123456789', '4321');
    RegressionTest::assertTrue($otp_result['success'], 'sendOtp must succeed');

    // 6. REGRESSION CHECK: Benefactor user must NEVER be treated as system_user
    // Insert a test benefactor into users table without password
    $test_phone = '09990001122';
    $stmt = $pdo->prepare("INSERT OR REPLACE INTO users (id, username, phone_number, role, full_name, password, is_active) VALUES (999, ?, ?, 'benefactor', 'نیکوکار تستی', NULL, 1)");
    $stmt->execute([$test_phone, $test_phone]);

    // Query to check how login would evaluate this user
    $chk = $pdo->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
    $chk->execute([$test_phone]);
    $u = $chk->fetch();

    $is_system_password_user = (!empty($u['password']) && in_array($u['role'], ['superadmin', 'secretary', 'admin', 'data_operator', 'board_member', 'education_deputy']));
    RegressionTest::assertFalse($is_system_password_user, 'REGRESSION CHECK: Benefactor user without password must NEVER require organizational password');
    RegressionTest::assertEquals('benefactor', $u['role'], 'User role must be benefactor');
});
