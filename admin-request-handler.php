<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/SmsService.php';

// بررسی دسترسی عمومی ادمین/پرسنل
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    die(json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز. لطفاً وارد سیستم شوید.']));
}

$action = isset($_POST['action']) ? $_POST['action'] : '';
$is_financial_edit_allowed = can_edit_financial();

// مسدودسازی کلیه عملیات‌های ویرایشی برای اعضای هیئت مدیره (ناظر فقط خواندنی)
if (has_role(['board_member'])) {
    die(json_encode([
        'success' => false, 
        'message' => 'اعضای محترم هیئت مدیره دارای دسترسی نظارتی و فقط خواندنی (Read-Only) می‌باشند و امکان ویرایش یا ثبت اطلاعات را ندارند.'
    ]));
}

// مسدودسازی اکشن‌های مالی و حذفی برای معاونت آموزش، اپراتور دیتای بچه‌ها و سایرین
$financial_actions = [
    'add_donation', 'edit_donation', 'delete_donation',
    'add_expense', 'edit_expense', 'delete_expense',
    'update_expense_category', 'update_donor', 'delete_record',
    'save_donor_reminder', 'send_donor_test_sms', 'send_all_due_sms', 'save_sms_settings',
    'toggle_donor_reminder_active', 'send_donor_instant_reminder', 'add_new_donor_reminder'
];

if (in_array($action, $financial_actions, true) && !$is_financial_edit_allowed) {
    die(json_encode([
        'success' => false, 
        'message' => 'شما به دلیل محدودیت دسترسی سازمانی مجاز به عملیات‌های مالی، تغییرات خیرین و حذف پرونده نیستید.'
    ]));
}

// --- HELPER: Secure File Upload ---
function handleFileUpload($file, $targetDir, $allowedTypes = ['jpg', 'jpeg', 'png', 'pdf']) {
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    $origName = basename($file['name']);
    $fileType = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

    if (!in_array($fileType, $allowedTypes, true)) {
        return ['success' => false, 'message' => 'نوع فایل غیرمجاز است. فقط فایل‌های مجاز پذیرفته می‌شوند.'];
    }

    // نام‌گذاری کاملاً امن و تصادفی برای جلوگیری از Path Traversal و دورزدن پسوندها
    $safeFileName = time() . '_' . bin2hex(random_bytes(8)) . '.' . $fileType;
    $targetPath = rtrim($targetDir, '/') . '/' . $safeFileName;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => true, 'path' => $targetPath, 'name' => htmlspecialchars($origName, ENT_QUOTES, 'UTF-8')];
    }
    return ['success' => false, 'message' => 'خطا در بارگذاری فایل روی سرور.'];
}

function cleanNumber($val) {
    if (empty($val)) return 0;
    $farsi = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹','٬',',',' '];
    $latin = ['0','1','2','3','4','5','6','7','8','9','','',''];
    $cleaned = str_replace($farsi, $latin, (string)$val);
    return (int)preg_replace('/[^0-9]/', '', $cleaned);
}

switch ($action) {
    case 'add_student':
        if (!can_add_student()) {
            die(json_encode([
                'success' => false,
                'message' => 'سطح دسترسی شما مجاز نیست. منحصراً مدیرعامل (آقای فرید علمی) و منشی بنیاد (سرکار خانم عباسی) مجاز به ثبت مددجوی جدید می‌باشند.'
            ]));
        }

        $name = trim($_POST['name'] ?? '');
        $surname = trim($_POST['surname'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $national_id = preg_replace('/[^0-9]/', '', str_replace(
            ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹','٠','١','٢','٣','٤','٥','٦','٧','٨','٩'],
            ['0','1','2','3','4','5','6','7','8','9','0','1','2','3','4','5','6','7','8','9'],
            trim($_POST['national_id'] ?? '')
        ));
        $phone = trim($_POST['phone'] ?? '');
        $guardian_phone = trim($_POST['guardian_phone'] ?? '');
        $grade = trim($_POST['grade'] ?? '');
        $field_of_study = trim($_POST['field_of_study'] ?? '');
        $school = trim($_POST['school'] ?? '');
        $father_name = trim($_POST['father_name'] ?? '');
        $mother_name = trim($_POST['mother_name'] ?? '');
        $birthday = trim($_POST['birthday'] ?? '');
        $gender = trim($_POST['gender'] ?? 'دختر');
        $address = trim($_POST['address'] ?? '');
        $status = trim($_POST['status'] ?? 'active');
        $counselor = trim($_POST['counselor'] ?? '');
        $notes = trim($_POST['notes'] ?? '');
        $account_number = trim($_POST['account_number'] ?? '');
        $base_bursary = isset($_POST['base_bursary']) ? cleanNumber($_POST['base_bursary']) : 20000000;
        $bursary_eligible = isset($_POST['bursary_eligible']) ? 1 : 0;

        if (empty($name) || empty($surname)) {
            echo json_encode(['success' => false, 'message' => 'وارد کردن نام و نام خانوادگی مددجو الزامی است.']);
            exit();
        }

        if (!empty($national_id)) {
            $chk = $pdo->prepare("SELECT id, name, surname FROM students WHERE national_id = ? LIMIT 1");
            $chk->execute([$national_id]);
            $existing = $chk->fetch();
            if ($existing) {
                echo json_encode([
                    'success' => false, 
                    'message' => "دانش‌آموزی با این کد ملی قبلاً در سامانه ثبت شده است ({$existing['name']} {$existing['surname']} - پرونده #{$existing['id']})."
                ]);
                exit();
            }
        }

        if (empty($code)) {
            $max_code = $pdo->query("SELECT MAX(CAST(code AS INTEGER)) FROM students WHERE code GLOB '[0-9]*'")->fetchColumn();
            $code = $max_code ? (string)($max_code + 1) : '101';
        }

        try {
            $sql = "INSERT INTO students (
                        code, name, surname, national_id, phone, guardian_phone, 
                        grade, field_of_study, school, father_name, mother_name, 
                        birthday, gender, address, status, counselor, notes, 
                        account_number, base_bursary, bursary_eligible
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                $code, $name, $surname, $national_id, $phone, $guardian_phone,
                $grade, $field_of_study, $school, $father_name, $mother_name,
                $birthday, $gender, $address, $status, $counselor, $notes,
                $account_number, $base_bursary, $bursary_eligible
            ]);
            $new_id = $pdo->lastInsertId();

            log_activity('افزودن مددجوی جدید', 'student', $new_id, "ثبت مددجوی جدید: {$name} {$surname} (کد پرونده: {$code}) توسط {$_SESSION['user_name']}");

            echo json_encode([
                'success' => true,
                'id' => $new_id,
                'message' => "مددجوی جدید «{$name} {$surname}» با موفقیت در سامانه ثبت شد."
            ]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'خطا در ثبت اطلاعات در پایگاه داده: ' . $e->getMessage()]);
        }
        exit();

    case 'import_students_list':
        if (!can_add_student()) {
            die(json_encode([
                'success' => false,
                'message' => 'سطح دسترسی شما مجاز نیست. منحصراً مدیرعامل (آقای فرید علمی) و منشی بنیاد (سرکار خانم عباسی) مجاز به بارگذاری لیست مددجویان می‌باشند.'
            ]));
        }

        if (empty($_FILES['file']['tmp_name'])) {
            echo json_encode(['success' => false, 'message' => 'لطفاً فایل اکسل (.xlsx) یا متنی (.csv) را انتخاب کنید.']);
            exit();
        }

        $tmp_file = $_FILES['file']['tmp_name'];
        $orig_name = basename($_FILES['file']['name']);
        $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));

        if (!in_array($ext, ['xlsx', 'csv', 'txt'])) {
            echo json_encode(['success' => false, 'message' => 'فقط فایل‌های اکسل (.xlsx) یا فایل متنی (.csv) مجاز هستند.']);
            exit();
        }

        $added_count = 0;

        if ($ext === 'csv' || $ext === 'txt') {
            $handle = fopen($tmp_file, "r");
            $first_row = true;
            while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
                if (count($row) === 1 && strpos($row[0], ';') !== false) {
                    $row = explode(';', $row[0]);
                }
                if ($first_row) {
                    $first_row = false;
                    continue;
                }
                $r_name = trim($row[0] ?? '');
                $r_surname = trim($row[1] ?? '');
                if (empty($r_name) && empty($r_surname)) continue;
                
                if (empty($r_surname) && strpos($r_name, ' ') !== false) {
                    $parts = explode(' ', $r_name, 2);
                    $r_name = $parts[0];
                    $r_surname = $parts[1];
                }

                $r_nid = preg_replace('/[^0-9]/', '', trim($row[2] ?? ''));
                $r_phone = trim($row[3] ?? '');
                $r_grade = trim($row[4] ?? '');
                $r_field = trim($row[5] ?? '');
                $r_school = trim($row[6] ?? '');

                if (!empty($r_nid)) {
                    $chk = $pdo->prepare("SELECT id FROM students WHERE national_id = ? LIMIT 1");
                    $chk->execute([$r_nid]);
                    if ($chk->fetch()) continue;
                }

                $max_c = $pdo->query("SELECT MAX(CAST(code AS INTEGER)) FROM students WHERE code GLOB '[0-9]*'")->fetchColumn();
                $r_code = $max_c ? (string)($max_c + 1) : '101';

                $stmt = $pdo->prepare("INSERT INTO students (code, name, surname, national_id, phone, grade, field_of_study, school, status, bursary_eligible, base_bursary) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', 1, 20000000)");
                $stmt->execute([$r_code, $r_name, $r_surname, $r_nid, $r_phone, $r_grade, $r_field, $r_school]);
                $added_count++;
            }
            fclose($handle);
        } else {
            $cmd = "python3 " . escapeshellarg(__DIR__ . '/scratch/parse_excel_students.py') . " " . escapeshellarg($tmp_file) . " " . escapeshellarg(__DIR__ . '/hekmat.db');
            $output = shell_exec($cmd);
            $parsed = json_decode($output, true);
            if ($parsed && isset($parsed['success']) && $parsed['success']) {
                $added_count = $parsed['count'] ?? 0;
            } else {
                echo json_encode(['success' => false, 'message' => $parsed['message'] ?? 'خطا در پردازش فایل اکسل.']);
                exit();
            }
        }

        log_activity('بارگذاری لیست مددجویان', 'students', 0, "بارگذاری فایل {$orig_name} و ثبت {$added_count} مددجوی جدید توسط {$_SESSION['user_name']}");
        echo json_encode([
            'success' => true,
            'count' => $added_count,
            'message' => "تعداد {$added_count} مددجوی جدید با موفقیت از فایل اضافه شدند."
        ]);
        exit();

    case 'update_student':
        $id = (int)$_POST['id'];
        $st_name = trim($_POST['name'] ?? '');
        $st_surname = trim($_POST['surname'] ?? '');

        try {
            if (!$is_financial_edit_allowed) {
                // اپراتور دیتا (ویانا وحیدی) فقط مجاز به به‌روزرسانی فیلدهای هویتی و تحصیلی است
                $sql = "UPDATE students SET 
                        name = ?, surname = ?, phone = ?, birthday = ?, national_id = ?, 
                        father_name = ?, mother_name = ?, birth_place = ?, school = ?, 
                        grade = ?, field_of_study = ?, guardian_phone = ?, address = ?, 
                        items_given = ?, counselor = ?, explanations = ?, language_class = ?, status = ?,
                        father_job = ?, mother_job = ?
                        WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $success = $stmt->execute([
                    $st_name, $st_surname, $_POST['phone'] ?? '', $_POST['birthday'] ?? '', $_POST['national_id'] ?? '', 
                    $_POST['father_name'] ?? '', $_POST['mother_name'] ?? '', $_POST['birth_place'] ?? '', $_POST['school'] ?? '', 
                    $_POST['grade'] ?? '', $_POST['field_of_study'] ?? '', $_POST['guardian_phone'] ?? '', $_POST['address'] ?? '', 
                    $_POST['items_given'] ?? '', $_POST['counselor'] ?? '', $_POST['explanations'] ?? '', $_POST['language_class'] ?? '', $_POST['status'] ?? 'active', 
                    $_POST['father_job'] ?? '', $_POST['mother_job'] ?? '',
                    $id
                ]);
                log_activity('ویرایش اطلاعات دانش‌آموز', 'student', $id, "ویرایش اطلاعات هویتی و تحصیلی: {$st_name} {$st_surname}");
            } else {
                // مدیرعامل یا منشی بنیاد (دسترسی کامل شامل مبالغ بورس و اقساط)
                $status = $_POST['status'] ?? 'active';
                if (in_array($status, ['exited', 'graduated'])) {
                    $bursary_eligible = 0;
                } else {
                    $bursary_eligible = isset($_POST['bursary_eligible']) ? 1 : 0;
                }
                $base_bursary = cleanNumber($_POST['base_bursary'] ?? 20000000);
                $computer_installment = cleanNumber($_POST['computer_installment'] ?? 0);
                $loan_installment = cleanNumber($_POST['loan_installment'] ?? 0);
                $other_deductions = cleanNumber($_POST['other_deductions'] ?? 0);
                $deductions_desc = trim($_POST['deductions_desc'] ?? '');

                $sql = "UPDATE students SET 
                        name = ?, surname = ?, phone = ?, birthday = ?, national_id = ?, 
                        father_name = ?, mother_name = ?, birth_place = ?, school = ?, 
                        grade = ?, field_of_study = ?, guardian_phone = ?, address = ?, 
                        items_given = ?, counselor = ?, explanations = ?, language_class = ?, notes = ?, status = ?,
                        father_job = ?, mother_job = ?, account_number = ?,
                        bursary_eligible = ?, base_bursary = ?, computer_installment = ?, 
                        loan_installment = ?, other_deductions = ?, deductions_desc = ?
                        WHERE id = ?";
                $stmt = $pdo->prepare($sql);
                $success = $stmt->execute([
                    $st_name, $st_surname, $_POST['phone'] ?? '', $_POST['birthday'] ?? '', $_POST['national_id'] ?? '', 
                    $_POST['father_name'] ?? '', $_POST['mother_name'] ?? '', $_POST['birth_place'] ?? '', $_POST['school'] ?? '', 
                    $_POST['grade'] ?? '', $_POST['field_of_study'] ?? '', $_POST['guardian_phone'] ?? '', $_POST['address'] ?? '', 
                    $_POST['items_given'] ?? '', $_POST['counselor'] ?? '', $_POST['explanations'] ?? '', $_POST['language_class'] ?? '', $_POST['notes'] ?? '', $_POST['status'] ?? 'active', 
                    $_POST['father_job'] ?? '', $_POST['mother_job'] ?? '', $_POST['account_number'] ?? '',
                    $bursary_eligible, $base_bursary, $computer_installment, 
                    $loan_installment, $other_deductions, $deductions_desc,
                    $id
                ]);
                log_activity('ویرایش کامل پرونده دانش‌آموز', 'student', $id, "ویرایش کامل پرونده و بخش مالی دانش‌آموز: {$st_name} {$st_surname}");
            }
            
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'خطای دیتابیس: ' . $e->getMessage()]);
        }
        break;

    case 'update_donor':
        $id = (int)$_POST['id'];
        $name = $_POST['name'] ?? '';
        $surname = $_POST['surname'] ?? '';
        $stmt = $pdo->prepare("UPDATE donors SET name = ?, surname = ?, phone = ?, birthday = ?, description = ? WHERE id = ?");
        $success = $stmt->execute([
            $name, $surname, $_POST['phone'], $_POST['birthday'], $_POST['description'], $id
        ]);
        log_activity('ویرایش اطلاعات خیر', 'donor', $id, "ویرایش اطلاعات نیکوکار: {$name} {$surname}");
        echo json_encode(['success' => $success]);
        break;

    case 'upload_photo':
        $type = $_POST['owner_type']; // 'student' or 'donor'
        $id = (int)$_POST['owner_id'];
        $res = handleFileUpload($_FILES['photo'], 'uploads/photos/', ['jpg', 'jpeg', 'png']);
        
        if ($res['success']) {
            $table = ($type === 'student') ? 'students' : 'donors';
            $stmt = $pdo->prepare("UPDATE $table SET photo_path = ? WHERE id = ?");
            $stmt->execute([$res['path'], $id]);
            log_activity('آپلود تصویر پرتره', $type, $id, "بارگذاری عکس پرتره برای {$type} شناسه {$id}");
            echo json_encode(['success' => true, 'path' => $res['path']]);
        } else {
            echo json_encode($res);
        }
        break;

    case 'upload_document':
        $owner_type = $_POST['owner_type'];
        $owner_id = (int)$_POST['owner_id'];
        $desc = $_POST['description'] ?? 'مدرک پیوست';
        $res = handleFileUpload($_FILES['document'], 'uploads/docs/', ['jpg', 'jpeg', 'png', 'pdf', 'docx']);
        
        if ($res['success']) {
            $stmt = $pdo->prepare("INSERT INTO documents (owner_type, owner_id, file_path, file_name, upload_date, description) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$owner_type, $owner_id, $res['path'], $res['name'], date('Y-m-d H:i:s'), $desc]);
            log_activity('آپلود مدرک/کارنامه', $owner_type, $owner_id, "آپلود فایل {$res['name']} ({$desc})");
            echo json_encode(['success' => true]);
        } else {
            echo json_encode($res);
        }
        break;

    case 'delete_document':
        $doc_id = (int)$_POST['id'];
        $stmt = $pdo->prepare("SELECT file_path, file_name, owner_type, owner_id FROM documents WHERE id = ?");
        $stmt->execute([$doc_id]);
        $doc = $stmt->fetch();
        
        if ($doc) {
            @unlink($doc['file_path']);
            $pdo->prepare("DELETE FROM documents WHERE id = ?")->execute([$doc_id]);
            log_activity('حذف مدرک', 'document', $doc_id, "حذف فایل {$doc['file_name']} از پرونده {$doc['owner_type']} #{$doc['owner_id']}");
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'سند یافت نشد.']);
        }
        break;

    case 'delete_record':
        $type = $_POST['type'];
        $id = (int)$_POST['id'];
        $table = ($type === 'student') ? 'students' : 'donors';
        
        // Cleanup files
        $docs = $pdo->prepare("SELECT file_path FROM documents WHERE owner_type = ? AND owner_id = ?");
        $docs->execute([$type, $id]);
        foreach ($docs->fetchAll() as $d) @unlink($d['file_path']);
        
        $pdo->prepare("DELETE FROM documents WHERE owner_type = ? AND owner_id = ?")->execute([$type, $id]);
        $pdo->prepare("DELETE FROM $table WHERE id = ?")->execute([$id]);
        
        log_activity('حذف پرونده', $type, $id, "حذف دائم پرونده {$type} شناسه {$id} به همراه کلیه مدارک");
        echo json_encode(['success' => true]);
        break;

    case 'add_donation':
        $donor_id = (int)$_POST['donor_id'];
        $amount = cleanNumber($_POST['amount'] ?? 0);
        $year = $_POST['year'] ?? '';
        $month = $_POST['month'] ?? '';
        $date = $_POST['date'] ?? '';
        $receipt = $_POST['receipt_no'] ?? '';
        $desc = $_POST['description'] ?? '';

        $stmt = $pdo->prepare("INSERT INTO donations (donor_id, amount, date, month, year, receipt_no, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $success = $stmt->execute([$donor_id, $amount, $date, $month, $year, $receipt, $desc]);
        log_activity('ثبت واریزی خیر', 'donation', $donor_id, "ثبت واریزی به مبلغ " . number_format($amount) . " ریال بابت {$desc}");
        echo json_encode(['success' => $success]);
        break;

    case 'edit_donation':
        if (!can_edit_financial()) {
            echo json_encode(['success' => false, 'message' => 'شما دسترسی لازم برای ویرایش اسناد مالی را ندارید.']);
            exit();
        }
        $id = (int)$_POST['id'];
        $amount = cleanNumber($_POST['amount'] ?? 0);
        $year = $_POST['year'] ?? '';
        $month = $_POST['month'] ?? '';
        $date = $_POST['date'] ?? '';
        $receipt = $_POST['receipt_no'] ?? '';
        $desc = $_POST['description'] ?? '';
        $new_donor_id = isset($_POST['donor_id']) ? (int)$_POST['donor_id'] : 0;

        if ($new_donor_id > 0) {
            $stmt = $pdo->prepare("UPDATE donations SET donor_id=?, amount=?, date=?, month=?, year=?, receipt_no=?, description=? WHERE id=?");
            $success = $stmt->execute([$new_donor_id, $amount, $date, $month, $year, $receipt, $desc, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE donations SET amount=?, date=?, month=?, year=?, receipt_no=?, description=? WHERE id=?");
            $success = $stmt->execute([$amount, $date, $month, $year, $receipt, $desc, $id]);
        }
        log_activity('ویرایش واریزی خیر', 'donation', $id, "ویرایش واریزی به مبلغ " . number_format($amount) . " ریال");
        echo json_encode(['success' => $success]);
        break;

    case 'reassign_donation':
        if (!can_reassign_donation()) {
            echo json_encode([
                'success' => false, 
                'message' => 'دسترسی غیرمجاز. این قابلیت منحصراً برای مدیریت بنیاد (آقای فرید علمی) و منشی بنیاد (سرکار خانم عباسی) فعال است.'
            ]);
            exit();
        }

        $donation_id = (int)($_POST['donation_id'] ?? 0);
        $target_donor_id = (int)($_POST['target_donor_id'] ?? 0);
        $payer_name = trim($_POST['payer_name'] ?? '');
        $reassign_reason = trim($_POST['reassign_reason'] ?? '');

        if ($donation_id <= 0 || $target_donor_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'شناسه سند واریزی و نیکوکار مقصد نامعتبر است.']);
            exit();
        }

        $d_stmt = $pdo->prepare("SELECT d.*, dn.name as old_name, dn.surname as old_surname FROM donations d LEFT JOIN donors dn ON d.donor_id = dn.id WHERE d.id = ?");
        $d_stmt->execute([$donation_id]);
        $donation = $d_stmt->fetch();
        if (!$donation) {
            echo json_encode(['success' => false, 'message' => 'سند واریزی مورد نظر یافت نشد.']);
            exit();
        }

        $t_stmt = $pdo->prepare("SELECT id, name, surname FROM donors WHERE id = ?");
        $t_stmt->execute([$target_donor_id]);
        $target_donor = $t_stmt->fetch();
        if (!$target_donor) {
            echo json_encode(['success' => false, 'message' => 'نیکوکار مقصد یافت نشد.']);
            exit();
        }

        $old_fullname = trim(($donation['old_name'] ?? '') . ' ' . ($donation['old_surname'] ?? '')) ?: "پرونده #{$donation['donor_id']}";
        $new_fullname = trim($target_donor['name'] . ' ' . $target_donor['surname']);

        $current_desc = $donation['description'] ?? '';
        $note = "[انتقال واریزی از طرف: " . ($payer_name ?: $old_fullname) . " به حساب: {$new_fullname}";
        if (!empty($reassign_reason)) {
            $note .= " | علت: {$reassign_reason}";
        }
        $note .= " | اقدام: " . ($_SESSION['user_name'] ?? 'مدیریت') . "]";
        $updated_desc = trim($current_desc . ' ' . $note);

        $up_stmt = $pdo->prepare("UPDATE donations SET donor_id = ?, description = ? WHERE id = ?");
        $success = $up_stmt->execute([$target_donor_id, $updated_desc, $donation_id]);

        if ($success) {
            log_activity(
                'انتقال واریزی بین نیکوکاران', 
                'donation', 
                $donation_id, 
                "انتقال سند واریزی #{$donation_id} (مبلغ: " . number_format($donation['amount']) . " ریال) از «{$old_fullname}» به «{$new_fullname}»" . ($payer_name ? " (واریزکننده اصلی: {$payer_name})" : "")
            );
            echo json_encode([
                'success' => true,
                'message' => "واریزی با موفقیت به حساب نیکوکار «{$new_fullname}» منظور گردید.",
                'new_donor_name' => $new_fullname,
                'new_donor_id' => $target_donor_id
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'خطا در ثبت تغییرات در پایگاه داده.']);
        }
        exit();

    case 'delete_donation':
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM donations WHERE id=?");
        $success = $stmt->execute([$id]);
        log_activity('حذف واریزی خیر', 'donation', $id, "حذف سند واریزی خیر");
        echo json_encode(['success' => $success]);
        break;

    case 'add_expense':
        $student_id = (int)$_POST['student_id'];
        $amount = cleanNumber($_POST['amount'] ?? 0);
        $date = $_POST['expense_date'] ?? '';
        $receipt = $_POST['receipt_no'] ?? '';
        $desc = $_POST['description'] ?? '';
        $notes = $_POST['notes'] ?? '';
        $cat_id = (int)($_POST['category_id'] ?? 1);

        $stmt = $pdo->prepare("INSERT INTO expenses (student_id, amount, description, expense_date, receipt_no, notes, category_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $success = $stmt->execute([$student_id, $amount, $desc, $date, $receipt, $notes, $cat_id]);
        log_activity('ثبت هزینه/بورسیه', 'expense', $student_id, "ثبت هزینه به مبلغ " . number_format($amount) . " ریال بابت {$desc}");
        echo json_encode(['success' => $success]);
        break;

    case 'edit_expense':
        $id = (int)$_POST['id'];
        $amount = cleanNumber($_POST['amount'] ?? 0);
        $date = $_POST['expense_date'] ?? '';
        $receipt = $_POST['receipt_no'] ?? '';
        $desc = $_POST['description'] ?? '';
        $notes = $_POST['notes'] ?? '';
        $cat_id = (int)($_POST['category_id'] ?? 1);

        $stmt = $pdo->prepare("UPDATE expenses SET amount=?, description=?, expense_date=?, receipt_no=?, notes=?, category_id=? WHERE id=?");
        $success = $stmt->execute([$amount, $desc, $date, $receipt, $notes, $cat_id, $id]);
        log_activity('ویرایش هزینه/بورسیه', 'expense', $id, "ویرایش هزینه به مبلغ " . number_format($amount) . " ریال");
        echo json_encode(['success' => $success]);
        break;

    case 'delete_expense':
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM expenses WHERE id=?");
        $success = $stmt->execute([$id]);
        log_activity('حذف هزینه/بورسیه', 'expense', $id, "حذف رکورد هزینه");
        echo json_encode(['success' => $success]);
        break;
        
    case 'update_student_notes':
        if (!can_view_admin_notes()) {
            die(json_encode(['success' => false, 'message' => 'شما مجاز به مشاهده یا ویرایش یادداشت‌های محرمانه مدیریتی نیستید.']));
        }
        $id = (int)$_POST['id'];
        $notes = $_POST['notes'] ?? '';
        $stmt = $pdo->prepare("UPDATE students SET notes = ? WHERE id = ?");
        $success = $stmt->execute([$notes, $id]);
        log_activity('ویرایش یادداشت پرونده', 'student', $id, "به‌روزرسانی یادداشت‌های پرونده مددجو");
        echo json_encode(['success' => $success]);
        break;

    case 'update_expense_category':
        $id = (int)$_POST['id'];
        $cat_id = (int)$_POST['category_id'];
        $sid = isset($_POST['student_id']) ? (int)$_POST['student_id'] : null;
        
        if ($sid) {
            $stmt = $pdo->prepare("UPDATE expenses SET category_id = ?, student_id = ? WHERE id = ?");
            $success = $stmt->execute([$cat_id, $sid, $id]);
        } else {
            $stmt = $pdo->prepare("UPDATE expenses SET category_id = ? WHERE id = ?");
            $success = $stmt->execute([$cat_id, $id]);
        }
        log_activity('تغییر سرفصل هزینه', 'expense', $id, "تغییر سرفصل به دسته {$cat_id}");
        echo json_encode(['success' => $success]);
        break;

    case 'save_donor_reminder':
        $donor_id = (int)($_POST['donor_id'] ?? 0);
        $reminder_active = !empty($_POST['reminder_active']) ? 1 : 0;
        $reminder_interval_months = max(1, (int)($_POST['reminder_interval_months'] ?? 1));
        $reminder_day = max(1, min(31, (int)($_POST['reminder_day'] ?? 1)));
        $reminder_channel = in_array($_POST['reminder_channel'] ?? '', ['sms', 'whatsapp', 'call']) ? $_POST['reminder_channel'] : 'sms';
        $reminder_shares = max(1, (int)($_POST['reminder_shares'] ?? 1));
        $sms_phone = trim($_POST['sms_phone'] ?? '');
        $custom_sms_text = trim($_POST['custom_sms_text'] ?? '');
        $next_reminder_date = trim($_POST['next_reminder_date'] ?? '');

        if ($reminder_active && empty($next_reminder_date)) {
            $next_reminder_date = SmsService::calculateNextReminderDate($reminder_interval_months, $reminder_day, SmsService::getCurrentJalaliDate());
        }

        $stmt = $pdo->prepare("
            UPDATE donors 
            SET reminder_active = ?, 
                reminder_interval_months = ?, 
                reminder_day = ?,
                reminder_channel = ?,
                reminder_shares = ?,
                next_reminder_date = ?, 
                sms_phone = ?, 
                custom_sms_text = ? 
            WHERE id = ?
        ");
        $success = $stmt->execute([
            $reminder_active,
            $reminder_interval_months,
            $reminder_day,
            $reminder_channel,
            $reminder_shares,
            $next_reminder_date ?: null,
            $sms_phone,
            $custom_sms_text ?: null,
            $donor_id
        ]);

        $interval_label = SmsService::getIntervalLabel($reminder_interval_months);
        $status_label = $reminder_active ? "فعال ({$interval_label} - روز {$reminder_day})" : 'غیرفعال';
        log_activity('تنظیم یادآوری پیامک خیر', 'donor', $donor_id, "تنظیم وضعیت یادآوری: {$status_label} - کانال: {$reminder_channel} - موعد: {$next_reminder_date}");

        echo json_encode([
            'success' => $success,
            'message' => 'تنظیمات زمان‌بندی یادآوری با موفقیت ذخیره شد.',
            'next_reminder_date' => $next_reminder_date,
            'reminder_active' => $reminder_active,
            'reminder_day' => $reminder_day,
            'reminder_channel' => $reminder_channel,
            'reminder_shares' => $reminder_shares,
            'interval_label' => $interval_label
        ]);
        break;

    case 'toggle_donor_reminder_active':
        $donor_id = (int)($_POST['donor_id'] ?? 0);
        $active = !empty($_POST['active']) ? 1 : 0;
        
        $stmt = $pdo->prepare("SELECT * FROM donors WHERE id = ?");
        $stmt->execute([$donor_id]);
        $donor = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$donor) {
            die(json_encode(['success' => false, 'message' => 'خیر یافت نشد.']));
        }
        
        $next_date = $donor['next_reminder_date'];
        if ($active && empty($next_date)) {
            $day = (int)($donor['reminder_day'] ?: 1);
            $interval = (int)($donor['reminder_interval_months'] ?: 1);
            $next_date = SmsService::calculateNextReminderDate($interval, $day, SmsService::getCurrentJalaliDate());
        }
        
        $upd = $pdo->prepare("UPDATE donors SET reminder_active = ?, next_reminder_date = ? WHERE id = ?");
        $success = $upd->execute([$active, $next_date, $donor_id]);
        
        log_activity('تغییر وضعیت یادآوری خیر', 'donor', $donor_id, ($active ? 'فعال‌سازی' : 'غیرفعال‌سازی') . " یادآوری برای {$donor['name']} {$donor['surname']}");
        echo json_encode([
            'success' => $success,
            'active' => $active,
            'next_reminder_date' => $next_date,
            'message' => $active ? 'یادآوری برای این خیر فعال شد.' : 'یادآوری برای این خیر غیرفعال شد.'
        ]);
        break;

    case 'send_donor_instant_reminder':
        $donor_id = (int)($_POST['donor_id'] ?? 0);
        $smsService = new SmsService($pdo);
        // ارسال آنی پیامک واقعی (is_test = false) و به‌روزرسانی خودکار تاریخ سررسید بعدی
        $result = $smsService->sendDonorReminder($donor_id, false, $_SESSION['user_id'] ?? null);
        
        // اگر کانال واتس‌اپ بود، لینک ارسال پیام در واتس‌اپ را هم ایجاد می‌کنیم
        $stmt = $pdo->prepare("SELECT phone, sms_phone, reminder_channel FROM donors WHERE id = ?");
        $stmt->execute([$donor_id]);
        $donor = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($donor && ($donor['reminder_channel'] ?? '') === 'whatsapp') {
            $raw_phone = !empty($donor['sms_phone']) ? $donor['sms_phone'] : $donor['phone'];
            $clean_phone = SmsService::cleanPhoneNumber($raw_phone);
            if ($clean_phone) {
                $wa_phone = preg_replace('/^0/', '98', $clean_phone);
                $wa_text = urlencode($result['rendered_message'] ?? '');
                $result['whatsapp_url'] = "https://api.whatsapp.com/send?phone={$wa_phone}&text={$wa_text}";
            }
        }
        
        echo json_encode($result);
        break;

    case 'add_new_donor_reminder':
        $name = trim($_POST['name'] ?? '');
        $surname = trim($_POST['surname'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $reminder_interval_months = max(1, (int)($_POST['reminder_interval_months'] ?? 1));
        $reminder_day = max(1, min(31, (int)($_POST['reminder_day'] ?? 1)));
        $reminder_channel = in_array($_POST['reminder_channel'] ?? '', ['sms', 'whatsapp', 'call']) ? $_POST['reminder_channel'] : 'sms';
        $reminder_shares = max(1, (int)($_POST['reminder_shares'] ?? 1));
        $reminder_active = !empty($_POST['reminder_active']) ? 1 : 0;
        $custom_sms_text = trim($_POST['custom_sms_text'] ?? '');

        if (empty($name)) {
            die(json_encode(['success' => false, 'message' => 'نام نیکوکار الزامی است.']));
        }
        $clean_phone = SmsService::cleanPhoneNumber($phone);
        if (!$clean_phone) {
            die(json_encode(['success' => false, 'message' => 'شماره همراه معتبر الزامی است (فرمت ۰۹...).']));
        }

        $today = SmsService::getCurrentJalaliDate();
        $parts = explode('/', $today);
        $cur_y = (int)$parts[0];
        $cur_m = (int)$parts[1];
        $cur_d = (int)$parts[2];
        if ($reminder_day >= $cur_d) {
            $next_date = sprintf('%04d/%02d/%02d', $cur_y, $cur_m, min($reminder_day, $cur_m <= 6 ? 31 : 30));
        } else {
            $next_date = SmsService::calculateNextReminderDate($reminder_interval_months, $reminder_day, $today);
        }

        $stmt = $pdo->prepare("
            INSERT INTO donors (
                name, surname, phone, sms_phone, join_date, reminder_active,
                reminder_interval_months, reminder_day, reminder_channel, reminder_shares,
                next_reminder_date, custom_sms_text
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $success = $stmt->execute([
            $name, $surname, $clean_phone, $clean_phone, $today, $reminder_active,
            $reminder_interval_months, $reminder_day, $reminder_channel, $reminder_shares,
            $next_date, $custom_sms_text ?: null
        ]);
        $new_id = $pdo->lastInsertId();

        log_activity('افزودن خیر جدید با زمان‌بندی یادآوری', 'donor', $new_id, "ثبت خیر جدید {$name} {$surname} با سررسید روز {$reminder_day} هر {$reminder_interval_months} ماه");
        echo json_encode([
            'success' => $success,
            'donor_id' => $new_id,
            'message' => 'نیکوکار جدید با موفقیت به سامانه یادآوری اضافه گردید.'
        ]);
        break;

    case 'send_donor_test_sms':
        $donor_id = (int)($_POST['donor_id'] ?? 0);
        $smsService = new SmsService($pdo);
        $result = $smsService->sendDonorReminder($donor_id, true, $_SESSION['user_id'] ?? null);
        echo json_encode($result);
        break;

    case 'send_all_due_sms':
        $smsService = new SmsService($pdo);
        $result = $smsService->processDueReminders($_SESSION['user_id'] ?? null);
        echo json_encode([
            'success' => true,
            'report' => $result,
            'message' => "تعداد {$result['sent']} پیامک یادآوری با موفقیت ارسال گردید."
        ]);
        break;

    case 'save_sms_settings':
        $provider = trim($_POST['provider'] ?? 'melipayamak');
        $username = trim($_POST['username'] ?? '9153103060');
        $api_key = trim($_POST['api_key'] ?? '');
        $sender_number = trim($_POST['sender_number'] ?? '');
        $pattern_id = trim($_POST['pattern_id'] ?? '');
        $default_template = trim($_POST['default_template'] ?? '');
        $is_active = !empty($_POST['is_active']) ? 1 : 0;

        $smsService = new SmsService($pdo);
        $success = $smsService->updateSettings($provider, $api_key, $sender_number, $default_template, $is_active, $username, $pattern_id);
        log_activity('به‌روزرسانی تنظیمات درگاه پیامک', 'system', 0, "تنظیم پنل پیامکی: {$provider}");
        echo json_encode(['success' => $success, 'message' => 'تنظیمات درگاه پیامک با موفقیت ذخیره شد.']);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'عملیات نامعتبر']);
        break;
}
?>
