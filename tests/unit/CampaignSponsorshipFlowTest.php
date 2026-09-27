<?php
// tests/unit/CampaignSponsorshipFlowTest.php
// Regression tests for Campaign, Registration, Redirect and Sponsorship Lifecycle

RegressionTest::suite("Campaign & Sponsorship Flow Regression Tests", function() use ($pdo) {

    // Helper function mimicking the normalized redirect sanitizer in login.php & register.php
    $sanitize_redirect = function($raw) {
        $raw = trim($raw);
        if ($raw !== '' && strpos($raw, '/') !== 0 && strpos($raw, '\\') === false && !preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*:#', $raw)) {
            $raw = '/' . $raw;
        }
        if (strpos($raw, '/') === 0 && strpos($raw, '//') !== 0 && strpos($raw, '\\') === false && !preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*:#', $raw)) {
            return $raw;
        }
        return false;
    };

    // 1. Redirect Sanitization & Preservation
    RegressionTest::assertEquals('/sponsor-student.php?id=42', $sanitize_redirect('sponsor-student.php?id=42'), 'Relative redirect without leading slash must be normalized with /');
    RegressionTest::assertEquals('/sponsor-student.php?id=42', $sanitize_redirect('/sponsor-student.php?id=42'), 'Relative redirect with leading slash must remain valid');
    RegressionTest::assertEquals('/campaign.php#students', $sanitize_redirect('campaign.php#students'), 'Hash anchor redirect should be normalized');
    RegressionTest::assertFalse($sanitize_redirect('https://evil.com/phishing'), 'External http/https URLs must be strictly rejected');
    RegressionTest::assertFalse($sanitize_redirect('//evil.com/phishing'), 'Protocol-relative // URLs must be strictly rejected');
    RegressionTest::assertFalse($sanitize_redirect('\\\\evil.com\\phishing'), 'Backslash URLs must be strictly rejected');
    RegressionTest::assertFalse($sanitize_redirect('javascript:alert(1)'), 'Javascript scheme URLs must be strictly rejected');

    // 2. Database Sponsorship Flow: Pending -> Active
    // Create temporary test donor and test student
    $pdo->exec("INSERT INTO donors (name, surname, phone, total_donated) VALUES ('تستی', 'حامی‌آزمایشی', '09990001122', 0)");
    $test_donor_id = (int)$pdo->lastInsertId();

    $pdo->exec("INSERT INTO students (code, name, surname, national_id, phone, status, bursary_eligible) VALUES ('TEST_9999', 'علی', 'تست‌پور', '0099999999', '09990003344', 'active', 1)");
    $test_student_id = (int)$pdo->lastInsertId();

    // Check campaign query: student is not yet sponsored
    $cmp_stmt = $pdo->prepare("
        SELECT (SELECT count(*) FROM sponsorships spon WHERE spon.student_id = s.id AND spon.status = 'active') as is_sponsored
        FROM students s WHERE s.id = ?
    ");
    $cmp_stmt->execute([$test_student_id]);
    $cmp_res = $cmp_stmt->fetch();
    RegressionTest::assertEquals(0, (int)$cmp_res['is_sponsored'], 'Initial student must not be sponsored');

    // Step 1: Sponsor Student creates a pending sponsorship
    $ins_sp = $pdo->prepare("INSERT INTO sponsorships (donor_id, student_id, shares_count, start_date, status) VALUES (?, ?, 1, date('now'), 'pending')");
    $ins_sp->execute([$test_donor_id, $test_student_id]);
    $spon_id = (int)$pdo->lastInsertId();

    // Step 2: Donor Dashboard Query must retrieve pending sponsorship
    $dash_stmt = $pdo->prepare("
        SELECT s.id as spon_id, s.status as spon_status, st.id as student_id
        FROM sponsorships s
        JOIN students st ON s.student_id = st.id
        WHERE s.donor_id = ? AND s.status IN ('active', 'pending')
    ");
    $dash_stmt->execute([$test_donor_id]);
    $dash_rows = $dash_stmt->fetchAll();
    RegressionTest::assertEquals(1, count($dash_rows), 'Donor dashboard must find the pending sponsorship');
    RegressionTest::assertEquals('pending', $dash_rows[0]['spon_status'], 'Sponsorship status in donor dashboard must be pending');

    // Step 3: Admin sponsorships query must list in pending queue
    $admin_pending_stmt = $pdo->prepare("
        SELECT s.id as spon_id, d.name as d_name, st.name as st_name
        FROM sponsorships s
        JOIN donors d ON s.donor_id = d.id
        JOIN students st ON s.student_id = st.id
        WHERE s.status = 'pending' AND s.id = ?
    ");
    $admin_pending_stmt->execute([$spon_id]);
    $admin_pending = $admin_pending_stmt->fetch();
    RegressionTest::assertTrue(!empty($admin_pending), 'Admin pending queue must include the new sponsorship');

    // Step 4: Admin approves the sponsorship
    $upd_sp = $pdo->prepare("UPDATE sponsorships SET status = 'active' WHERE id = ?");
    $upd_sp->execute([$spon_id]);

    // Step 5: Campaign now detects student as actively sponsored
    $cmp_stmt->execute([$test_student_id]);
    $cmp_res2 = $cmp_stmt->fetch();
    RegressionTest::assertEquals(1, (int)$cmp_res2['is_sponsored'], 'Campaign must now mark student as actively sponsored');

    // Clean up test data
    $pdo->exec("DELETE FROM sponsorships WHERE id = {$spon_id}");
    $pdo->exec("DELETE FROM students WHERE id = {$test_student_id}");
    $pdo->exec("DELETE FROM donors WHERE id = {$test_donor_id}");
});
