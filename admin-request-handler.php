<?php
session_start();
require_once 'includes/db.php';

// Access Control
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    die(json_encode(['success' => false, 'message' => 'دسترسی غیرمجاز']));
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

// --- HELPER: Secure File Upload ---
function handleFileUpload($file, $targetDir, $allowedTypes = ['jpg', 'jpeg', 'png', 'pdf']) {
    $fileName = time() . '_' . basename($file['name']);
    $targetPath = $targetDir . $fileName;
    $fileType = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));

    if (!in_array($fileType, $allowedTypes)) {
        return ['success' => false, 'message' => 'نوع فایل غیرمجاز است.'];
    }

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return ['success' => true, 'path' => $targetPath, 'name' => basename($file['name'])];
    }
    return ['success' => false, 'message' => 'خطا در بارگذاری فایل.'];
}

switch ($action) {
    case 'update_student':
        $id = (int)$_POST['id'];
        $bursary_eligible = isset($_POST['bursary_eligible']) ? 1 : 0;
        $base_bursary = (int)($_POST['base_bursary'] ?? 20000000);
        $computer_installment = (int)($_POST['computer_installment'] ?? 0);
        $loan_installment = (int)($_POST['loan_installment'] ?? 0);
        $other_deductions = (int)($_POST['other_deductions'] ?? 0);
        $deductions_desc = trim($_POST['deductions_desc'] ?? '');

        try {
            $sql = "UPDATE students SET 
                    name = ?, surname = ?, phone = ?, birthday = ?, national_id = ?, 
                    father_name = ?, mother_name = ?, birth_place = ?, school = ?, 
                    grade = ?, field_of_study = ?, guardian_phone = ?, address = ?, 
                    items_given = ?, counselor = ?, explanations = ?, notes = ?, status = ?,
                    father_job = ?, mother_job = ?, account_number = ?,
                    bursary_eligible = ?, base_bursary = ?, computer_installment = ?, 
                    loan_installment = ?, other_deductions = ?, deductions_desc = ?
                    WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $success = $stmt->execute([
                $_POST['name'] ?? '', $_POST['surname'] ?? '', $_POST['phone'] ?? '', $_POST['birthday'] ?? '', $_POST['national_id'] ?? '', 
                $_POST['father_name'] ?? '', $_POST['mother_name'] ?? '', $_POST['birth_place'] ?? '', $_POST['school'] ?? '', 
                $_POST['grade'] ?? '', $_POST['field_of_study'] ?? '', $_POST['guardian_phone'] ?? '', $_POST['address'] ?? '', 
                $_POST['items_given'] ?? '', $_POST['counselor'] ?? '', $_POST['explanations'] ?? '', $_POST['notes'] ?? '', $_POST['status'] ?? 'active', 
                $_POST['father_job'] ?? '', $_POST['mother_job'] ?? '', $_POST['account_number'] ?? '',
                $bursary_eligible, $base_bursary, $computer_installment, 
                $loan_installment, $other_deductions, $deductions_desc,
                $id
            ]);
            
            if ($stmt->rowCount() === 0) {
                // Check if the record actually exists
                $check = $pdo->prepare("SELECT id FROM students WHERE id = ?");
                $check->execute([$id]);
                if (!$check->fetch()) {
                    echo json_encode(['success' => false, 'message' => 'مددجویی با این شناسه یافت نشد.']);
                    break;
                }
            }
            
            echo json_encode(['success' => $success]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => 'خطای دیتابیس: ' . $e->getMessage()]);
        }
        break;

    case 'update_donor':
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("UPDATE donors SET name = ?, surname = ?, phone = ?, birthday = ?, description = ? WHERE id = ?");
        $success = $stmt->execute([
            $_POST['name'], $_POST['surname'], $_POST['phone'], $_POST['birthday'], $_POST['description'], $id
        ]);
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
            echo json_encode(['success' => true, 'path' => $res['path']]);
        } else {
            echo json_encode($res);
        }
        break;

    case 'upload_document':
        $owner_type = $_POST['owner_type'];
        $owner_id = (int)$_POST['owner_id'];
        $desc = $_POST['description'];
        $res = handleFileUpload($_FILES['document'], 'uploads/docs/', ['jpg', 'jpeg', 'png', 'pdf', 'docx']);
        
        if ($res['success']) {
            $stmt = $pdo->prepare("INSERT INTO documents (owner_type, owner_id, file_path, file_name, upload_date, description) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$owner_type, $owner_id, $res['path'], $res['name'], date('Y-m-d H:i:s'), $desc]);
            echo json_encode(['success' => true]);
        } else {
            echo json_encode($res);
        }
        break;

    case 'delete_document':
        $doc_id = (int)$_POST['id'];
        $stmt = $pdo->prepare("SELECT file_path FROM documents WHERE id = ?");
        $stmt->execute([$doc_id]);
        $doc = $stmt->fetch();
        
        if ($doc) {
            @unlink($doc['file_path']);
            $pdo->prepare("DELETE FROM documents WHERE id = ?")->execute([$doc_id]);
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
        
        echo json_encode(['success' => true]);
        break;

    case 'add_donation':
        $donor_id = (int)$_POST['donor_id'];
        $amount = (int)str_replace(',', '', $_POST['amount']);
        $year = $_POST['year'];
        $month = $_POST['month'];
        $date = $_POST['date'];
        $receipt = $_POST['receipt_no'];
        $desc = $_POST['description'];

        $stmt = $pdo->prepare("INSERT INTO donations (donor_id, amount, date, month, year, receipt_no, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $success = $stmt->execute([$donor_id, $amount, $date, $month, $year, $receipt, $desc]);
        echo json_encode(['success' => $success]);
        break;

    case 'edit_donation':
        $id = (int)$_POST['id'];
        $amount = (int)str_replace(',', '', $_POST['amount']);
        $year = $_POST['year'];
        $month = $_POST['month'];
        $date = $_POST['date'];
        $receipt = $_POST['receipt_no'];
        $desc = $_POST['description'];

        $stmt = $pdo->prepare("UPDATE donations SET amount=?, date=?, month=?, year=?, receipt_no=?, description=? WHERE id=?");
        $success = $stmt->execute([$amount, $date, $month, $year, $receipt, $desc, $id]);
        echo json_encode(['success' => $success]);
        break;

    case 'delete_donation':
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM donations WHERE id=?");
        $success = $stmt->execute([$id]);
        echo json_encode(['success' => $success]);
        break;

    case 'add_expense':
        $student_id = (int)$_POST['student_id'];
        $amount = (int)str_replace(',', '', $_POST['amount']);
        $date = $_POST['expense_date'];
        $receipt = $_POST['receipt_no'];
        $desc = $_POST['description'];
        $notes = $_POST['notes'];
        $cat_id = (int)($_POST['category_id'] ?? 1);

        $stmt = $pdo->prepare("INSERT INTO expenses (student_id, amount, description, expense_date, receipt_no, notes, category_id) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $success = $stmt->execute([$student_id, $amount, $desc, $date, $receipt, $notes, $cat_id]);
        echo json_encode(['success' => $success]);
        break;

    case 'edit_expense':
        $id = (int)$_POST['id'];
        $amount = (int)str_replace(',', '', $_POST['amount']);
        $date = $_POST['expense_date'];
        $receipt = $_POST['receipt_no'];
        $desc = $_POST['description'];
        $notes = $_POST['notes'];
        $cat_id = (int)($_POST['category_id'] ?? 1);

        $stmt = $pdo->prepare("UPDATE expenses SET amount=?, description=?, expense_date=?, receipt_no=?, notes=?, category_id=? WHERE id=?");
        $success = $stmt->execute([$amount, $desc, $date, $receipt, $notes, $cat_id, $id]);
        echo json_encode(['success' => $success]);
        break;

    case 'delete_expense':
        $id = (int)$_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM expenses WHERE id=?");
        $success = $stmt->execute([$id]);
        echo json_encode(['success' => $success]);
        break;
        
    case 'update_student_notes':
        $id = (int)$_POST['id'];
        $notes = $_POST['notes'] ?? '';
        $stmt = $pdo->prepare("UPDATE students SET notes = ? WHERE id = ?");
        $success = $stmt->execute([$notes, $id]);
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
        echo json_encode(['success' => $success]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'عملیات نامعتبر']);
        break;
}
?>
