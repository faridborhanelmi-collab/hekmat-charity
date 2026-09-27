<?php
// tests/fixtures/setup_test_db.php
// Creates an isolated test database for safe regression testing

$test_db_dir = __DIR__;
$test_db_path = $test_db_dir . '/test_hekmat.db';
$source_db_path = __DIR__ . '/../../hekmat.db';

if (file_exists($test_db_path)) {
    unlink($test_db_path);
}

$source_pdo = new PDO("sqlite:$source_db_path");
$source_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$test_pdo = new PDO("sqlite:$test_db_path");
$test_pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Copy schema from source database
$tables = $source_pdo->query("SELECT sql FROM sqlite_master WHERE type IN ('table', 'index') AND sql IS NOT NULL AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN);

foreach ($tables as $sql) {
    try {
        $test_pdo->exec($sql);
    } catch (Exception $e) {
        // Ignore duplicate index errors if any
    }
}

// Seed Users for all RBAC roles
$default_hash = password_hash('123456', PASSWORD_DEFAULT);
$users = [
    [1, 'faridelmi', 'فرید علمی (مدیرعامل)', 'superadmin', $default_hash, 1],
    [2, 'abbasi', 'پروانه عباسی (منشی بنیاد)', 'secretary', $default_hash, 1],
    [3, 'fartash', 'خانم فرتاش (معاونت آموزش)', 'education_deputy', $default_hash, 1],
    [4, 'viana', 'ویانا وحیدی (اپراتور دیتابیس)', 'data_operator', $default_hash, 1],
    [5, 'behnam', 'بهنام بهرمن (هیئت مدیره ناظر)', 'board_member', $default_hash, 1],
];

$stmt = $test_pdo->prepare("INSERT INTO users (id, username, full_name, role, password, is_active) VALUES (?, ?, ?, ?, ?, ?)");
foreach ($users as $u) {
    $stmt->execute($u);
}

// Seed Test Students
$students = [
    [
        1, 'ALI101', 'علی', 'اکبری', '0123456789', '09121112233', 
        'رضا', '1385/05/10', 'مشهد، بلوار سجاد', 'دبیرستان شهید بهشتی', 'دوازدهم', 'active',
        'مریم', 'مشهد', 'ریاضی', '09121112234', 'لپ‌تاپ', 'دکتر حسینی', 'توضیحات تست',
        'کارمند', 'خانه‌دار', 20000000, 2000000, 1, $default_hash
    ],
    [
        2, 'FAT102', 'فاطمه', 'رضایی', '9876543210', '09351112233', 
        'حسین', '1386/02/15', 'تهران، نیاوران', 'فرزانگان', 'یازدهم', 'active',
        'زهرا', 'تهران', 'تجربی', '09351112234', '', 'دکتر علوی', '',
        'آزاد', 'معلم', 15000000, 0, 1, $default_hash
    ],
    [
        3, 'MOH103', 'محمد', 'کاظمی', '1234567890', '09151112233', 
        'احمد', '1384/09/20', 'مشهد، وکیل‌آباد', 'فردوسی', 'فارغ‌التحصیل', 'graduated',
        'فاطمه', 'مشهد', 'انسانی', '09151112234', '', '', '',
        'بازنشسته', 'خانه‌دار', 0, 0, 0, $default_hash
    ]
];

$stmt = $test_pdo->prepare("INSERT INTO students (
    id, code, name, surname, national_id, phone, 
    father_name, birthday, address, school, grade, status,
    mother_name, birth_place, field_of_study, guardian_phone, items_given, counselor, explanations, 
    father_job, mother_job, base_bursary, computer_installment, bursary_eligible, password
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

foreach ($students as $s) {
    $stmt->execute($s);
}

// Seed Test Donors
$stmt = $test_pdo->prepare("INSERT INTO donors (id, name, surname, phone, reminder_active) VALUES (?, ?, ?, ?, ?)");
$stmt->execute([1, 'حاج حسن', 'خیرخواه', '09129998877', 1]);

// Seed Test Expense Categories & Expenses
$stmt = $test_pdo->prepare("INSERT INTO expense_categories (id, name) VALUES (?, ?)");
$stmt->execute([1, 'بورسیه دانش‌آموزی']);
$stmt->execute([2, 'هزینه‌های جاری اداری']);

$stmt = $test_pdo->prepare("INSERT INTO expenses (id, category_id, amount, expense_date, description) VALUES (?, ?, ?, ?, ?)");
$stmt->execute([1, 1, 35000000, '1403/06/01', 'پرداخت بورسیه شهریور']);

echo "✅ Test database successfully created at: $test_db_path\n";
