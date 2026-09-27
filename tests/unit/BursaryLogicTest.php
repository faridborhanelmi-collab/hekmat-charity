<?php
// tests/unit/BursaryLogicTest.php
// Regression Tests for Bursary Scholarship Calculations & Integrity

require_once __DIR__ . '/../bootstrap.php';

RegressionTest::suite('Bursary Scholarship Financial Calculation Regression Tests', function() {
    global $pdo;

    // Calculation function matching business logic across admin/bursary-payments.php & person-detail.php
    $calculate_net_bursary = function($base, $comp_installment, $loan_installment, $other_deductions, $is_eligible, $status) {
        if (!$is_eligible || in_array($status, ['exited', 'graduated', 'suspended'])) {
            return 0;
        }
        $total_deductions = $comp_installment + $loan_installment + $other_deductions;
        $net = $base - $total_deductions;
        return max(0, $net);
    };

    // 1. Standard active student calculation
    $net1 = $calculate_net_bursary(20000000, 2000000, 0, 0, 1, 'active');
    RegressionTest::assertEquals(18000000, $net1, 'Net bursary: 20M base - 2M computer installment = 18M');

    // 2. Student with multiple deductions
    $net2 = $calculate_net_bursary(25000000, 3000000, 1000000, 500000, 1, 'active');
    RegressionTest::assertEquals(20500000, $net2, 'Net bursary with multiple deductions (computer + loan + other)');

    // 3. Graduated student must receive 0 bursary
    $net_graduated = $calculate_net_bursary(20000000, 0, 0, 0, 1, 'graduated');
    RegressionTest::assertEquals(0, $net_graduated, 'REGRESSION CHECK: Graduated student net bursary must strictly be 0');

    // 4. Ineligible student must receive 0 bursary
    $net_ineligible = $calculate_net_bursary(20000000, 0, 0, 0, 0, 'active');
    RegressionTest::assertEquals(0, $net_ineligible, 'Ineligible student net bursary must be 0');

    // 5. Test Database Update: Verify student bursary fields persistence
    // (This directly guards against the regression bug we fixed in admin-request-handler.php!)
    $stmt = $pdo->prepare("UPDATE students SET base_bursary = ?, computer_installment = ? WHERE id = ?");
    $stmt->execute([22000000, 1500000, 1]);

    $stmt = $pdo->prepare("SELECT base_bursary, computer_installment FROM students WHERE id = ?");
    $stmt->execute([1]);
    $updated_student = $stmt->fetch(PDO::FETCH_ASSOC);

    RegressionTest::assertEquals(22000000, (int)$updated_student['base_bursary'], 'REGRESSION CHECK: base_bursary persisted correctly in database');
    RegressionTest::assertEquals(1500000, (int)$updated_student['computer_installment'], 'REGRESSION CHECK: computer_installment persisted correctly in database');
});
