<?php
// tests/unit/AuthRbacTest.php
// Regression Tests for RBAC and Permissions

require_once __DIR__ . '/../bootstrap.php';

RegressionTest::suite('Role-Based Access Control (RBAC) Security & Regression Tests', function() {
    // 1. Test Superadmin (فرید علمی)
    $_SESSION['user_id'] = 1;
    $_SESSION['username'] = 'faridelmi';
    $_SESSION['role'] = 'superadmin';
    $_SESSION['is_admin'] = true;
    $_SESSION['is_superadmin'] = true;

    RegressionTest::assertTrue(can_view_financial(), 'Superadmin must have permission to view financial records');
    RegressionTest::assertTrue(can_edit_financial(), 'Superadmin must have permission to edit financial records');
    RegressionTest::assertTrue(can_reassign_donation(), 'Superadmin must have permission to reassign donations between donors');
    RegressionTest::assertTrue(can_view_psychology(), 'Superadmin (faridelmi) must have access to psychology files');
    RegressionTest::assertTrue(can_view_admin_notes(), 'Superadmin must have permission to view/write admin notes');
    RegressionTest::assertTrue(can_add_student(), 'Superadmin must have permission to add students');
    RegressionTest::assertFalse(is_read_only(), 'Superadmin must NOT be read-only');
    RegressionTest::assertEquals('مدیرعامل بنیاد', get_role_title('superadmin'), 'Role title for superadmin must be correct');

    // 2. Test Secretary (خانم عباسی)
    $_SESSION['user_id'] = 2;
    $_SESSION['username'] = 'abbasi';
    $_SESSION['role'] = 'secretary';
    $_SESSION['is_admin'] = true;
    $_SESSION['is_superadmin'] = false;

    RegressionTest::assertTrue(can_view_financial(), 'Secretary must have permission to view financial records');
    RegressionTest::assertTrue(can_edit_financial(), 'Secretary must have permission to edit financial records');
    RegressionTest::assertTrue(can_reassign_donation(), 'Secretary must have permission to reassign donations between donors');
    RegressionTest::assertFalse(can_view_psychology(), 'REGRESSION CHECK: Secretary must NEVER access confidential psychology records');
    RegressionTest::assertTrue(can_view_admin_notes(), 'Secretary must have permission to view/write admin notes');
    RegressionTest::assertTrue(can_add_student(), 'Secretary (Mrs. Abbasi) must have permission to add students');
    RegressionTest::assertFalse(is_read_only(), 'Secretary must NOT be read-only');

    // 3. Test Education Deputy (خانم فرتاش)
    $_SESSION['user_id'] = 3;
    $_SESSION['username'] = 'fartash';
    $_SESSION['role'] = 'education_deputy';
    $_SESSION['is_admin'] = true;
    $_SESSION['is_superadmin'] = false;

    RegressionTest::assertFalse(can_view_financial(), 'REGRESSION CHECK: Education deputy must NOT view financial records');
    RegressionTest::assertFalse(can_edit_financial(), 'REGRESSION CHECK: Education deputy must NOT edit financial records');
    RegressionTest::assertFalse(can_reassign_donation(), 'REGRESSION CHECK: Education deputy must NOT reassign donations');
    RegressionTest::assertFalse(can_view_psychology(), 'REGRESSION CHECK: Education deputy must NOT access psychology records');
    RegressionTest::assertTrue(can_view_admin_notes(), 'Education deputy must have permission to view/write admin notes');
    RegressionTest::assertFalse(can_add_student(), 'REGRESSION CHECK: Education deputy must NOT add students');

    // 4. Test Data Operator (ویانا وحیدی)
    $_SESSION['user_id'] = 4;
    $_SESSION['username'] = 'viana';
    $_SESSION['role'] = 'data_operator';
    $_SESSION['is_admin'] = true;
    $_SESSION['is_superadmin'] = false;

    RegressionTest::assertFalse(can_view_financial(), 'REGRESSION CHECK: Data operator must NEVER view financial records');
    RegressionTest::assertFalse(can_edit_financial(), 'REGRESSION CHECK: Data operator must NEVER edit financial records');
    RegressionTest::assertFalse(can_reassign_donation(), 'REGRESSION CHECK: Data operator must NEVER reassign donations');
    RegressionTest::assertFalse(can_view_psychology(), 'REGRESSION CHECK: Data operator must NEVER access psychology records');
    RegressionTest::assertFalse(can_view_admin_notes(), 'REGRESSION CHECK: Data operator must NOT view confidential admin notes');
    RegressionTest::assertFalse(can_add_student(), 'REGRESSION CHECK: Data operator must NOT add students');
    RegressionTest::assertFalse(is_read_only(), 'Data operator is not board_member');

    // 5. Test Board Member (اعضای هیئت مدیره - ناظر فقط‌خواندنی)
    $_SESSION['user_id'] = 5;
    $_SESSION['username'] = 'behnam';
    $_SESSION['role'] = 'board_member';
    $_SESSION['is_admin'] = true;
    $_SESSION['is_superadmin'] = false;

    RegressionTest::assertTrue(can_view_financial(), 'Board members must be able to view financial records as auditors');
    RegressionTest::assertFalse(can_edit_financial(), 'REGRESSION CHECK: Board members must NEVER have edit rights on financial records');
    RegressionTest::assertFalse(can_reassign_donation(), 'REGRESSION CHECK: Board members must NEVER reassign donations');
    RegressionTest::assertFalse(can_view_psychology(), 'REGRESSION CHECK: Board members must NOT access psychology records');
    RegressionTest::assertFalse(can_add_student(), 'REGRESSION CHECK: Board members must NOT add students');
    RegressionTest::assertTrue(is_read_only(), 'REGRESSION CHECK: Board members must be strictly flagged as Read-Only');

    // 6. Test Unauthenticated Guest
    unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role']);
    RegressionTest::assertFalse(is_logged_in(), 'Guest must not be logged in');
    RegressionTest::assertFalse(can_view_financial(), 'Guest must not view financial records');
    RegressionTest::assertFalse(can_edit_financial(), 'Guest must not edit financial records');
    RegressionTest::assertFalse(can_reassign_donation(), 'Guest must not reassign donations');
    RegressionTest::assertFalse(can_view_psychology(), 'Guest must not view psychology records');
    RegressionTest::assertFalse(can_add_student(), 'Guest must not add students');
});
