<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/ParsianBankService.php';

// Access Control - Financial Only
require_financial_access();

$bankService = new ParsianBankService($pdo);
$bankSettings = $bankService->getSettings();

// ----------------------------------------------------
// BANK BATCH / CSV DOWNLOAD HANDLERS (Before HTML)
// ----------------------------------------------------
if (isset($_GET['action']) && isset($_GET['id'])) {
    $list_id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM monthly_bursary_lists WHERE id = ?");
    $stmt->execute([$list_id]);
    $list = $stmt->fetch();
    
    if ($list) {
        $stmt = $pdo->prepare("SELECT * FROM monthly_bursary_items WHERE list_id = ? ORDER BY id ASC");
        $stmt->execute([$list_id]);
        $items = $stmt->fetchAll();

        // 1. Parsian Standard Batch File Download
        if ($_GET['action'] === 'download_parsian_csv') {
            $bankService->generateParsianStandardBatchFile($list, $items);
            exit();
        }

        // 2. Standard CSV Download
        if ($_GET['action'] === 'download_csv' && ($list['status'] === 'signed' || $list['status'] === 'paid')) {
            $filename = "bursary_payment_" . $list['year'] . "_" . $list['month'] . ".csv";
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            echo "\xEF\xBB\xBF";
            $output = fopen('php://output', 'w');
            fputcsv($output, ['ردیف', 'نام و نام خانوادگی', 'شماره حساب', 'مبلغ پایه (ریال)', 'قسط کامپیوتر (ریال)', 'قسط وام (ریال)', 'سایر کسورات (ریال)', 'مبلغ خالص پرداختی (ریال)', 'شرح پرداخت']);
            $idx = 1;
            foreach ($items as $item) {
                if (isset($item['is_selected']) && $item['is_selected'] == 0) continue;
                $desc = "بورسیه " . $list['month'] . " " . $list['year'];
                if (!empty($item['deductions_desc'])) $desc .= " - کسورات: " . $item['deductions_desc'];
                fputcsv($output, [
                    $idx++,
                    $item['student_name'],
                    $item['account_number'],
                    $item['base_amount'],
                    $item['computer_installment'],
                    $item['loan_installment'],
                    $item['other_deductions'],
                    $item['final_amount'],
                    $desc
                ]);
            }
            fclose($output);
            exit();
        }
    }
}

// ----------------------------------------------------
// AJAX HANDLERS
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $ajax_action = $_POST['ajax_action'];
    
    // 1. Update item values inline
    if ($ajax_action === 'update_item') {
        $item_id = (int)$_POST['item_id'];
        $base = (int)$_POST['base_amount'];
        $comp = (int)$_POST['computer_installment'];
        $loan = (int)$_POST['loan_installment'];
        $other = (int)$_POST['other_deductions'];
        $desc = trim($_POST['deductions_desc'] ?? '');
        $final = $base - $comp - $loan - $other;
        
        try {
            $stmt = $pdo->prepare("UPDATE monthly_bursary_items SET base_amount = ?, computer_installment = ?, loan_installment = ?, other_deductions = ?, deductions_desc = ?, final_amount = ? WHERE id = ?");
            $stmt->execute([$base, $comp, $loan, $other, $desc, $final, $item_id]);
            echo json_encode(['success' => true, 'final_amount' => $final]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }
    
    // 2. Toggle row selection (تیک بغل هر ردیف)
    if ($ajax_action === 'toggle_selection') {
        $item_id = (int)$_POST['item_id'];
        $is_selected = (int)$_POST['is_selected'];
        try {
            $stmt = $pdo->prepare("UPDATE monthly_bursary_items SET is_selected = ? WHERE id = ?");
            $stmt->execute([$is_selected, $item_id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }

    // 3. Toggle all selection
    if ($ajax_action === 'toggle_all_selection') {
        $list_id = (int)$_POST['list_id'];
        $is_selected = (int)$_POST['is_selected'];
        try {
            $stmt = $pdo->prepare("UPDATE monthly_bursary_items SET is_selected = ? WHERE list_id = ?");
            $stmt->execute([$is_selected, $list_id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }

    // 4. Submit Batch directly to Parsian Bank API
    if ($ajax_action === 'submit_to_parsian_bank') {
        $list_id = (int)$_POST['list_id'];
        try {
            $stmt = $pdo->prepare("SELECT * FROM monthly_bursary_items WHERE list_id = ? AND (is_selected = 1 OR is_selected IS NULL) ORDER BY id ASC");
            $stmt->execute([$list_id]);
            $selected_items = $stmt->fetchAll();

            if (empty($selected_items)) {
                echo json_encode(['success' => false, 'message' => 'هیچ ردیفی برای ارسال به بانک انتخاب نشده است.']);
                exit();
            }

            // Call Parsian Service
            $res = $bankService->submitBatchTransfer($list_id, $selected_items);
            if ($res['success']) {
                // Update list status to pending_signatures so it appears in board signers cartable
                $stmt = $pdo->prepare("UPDATE monthly_bursary_lists SET status = 'pending_signatures' WHERE id = ?");
                $stmt->execute([$list_id]);
            }
            echo json_encode($res);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'خطا در ارسال به بانک: ' . $e->getMessage()]);
        }
        exit();
    }

    // 5. Inquire batch status from Parsian Bank
    if ($ajax_action === 'inquire_bank_status') {
        $list_id = (int)$_POST['list_id'];
        $batch_id = trim($_POST['batch_id'] ?? '');
        $res = $bankService->checkBatchStatus($list_id, $batch_id);
        echo json_encode($res);
        exit();
    }

    // 6. Add student to list
    if ($ajax_action === 'add_student_to_list') {
        $list_id = (int)$_POST['list_id'];
        $student_id = (int)$_POST['student_id'];
        
        try {
            $chk = $pdo->prepare("SELECT id FROM monthly_bursary_items WHERE list_id = ? AND student_id = ?");
            $chk->execute([$list_id, $student_id]);
            if ($chk->fetch()) {
                echo json_encode(['success' => false, 'message' => 'این دانش‌آموز قبلاً در لیست این ماه ثبت شده است.']);
                exit();
            }
            
            $stmt = $pdo->prepare("SELECT name, surname, account_number, base_bursary, computer_installment, loan_installment, other_deductions, deductions_desc FROM students WHERE id = ?");
            $stmt->execute([$student_id]);
            $st = $stmt->fetch();
            
            if ($st) {
                $base = $st['base_bursary'] ?? 20000000;
                $comp = $st['computer_installment'] ?? 0;
                $loan = $st['loan_installment'] ?? 0;
                $other = $st['other_deductions'] ?? 0;
                $final = $base - $comp - $loan - $other;
                $fullname = $st['name'] . ' ' . $st['surname'];
                
                $ins = $pdo->prepare("INSERT INTO monthly_bursary_items (list_id, student_id, student_name, account_number, base_amount, computer_installment, loan_installment, other_deductions, deductions_desc, final_amount, is_selected) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
                $ins->execute([$list_id, $student_id, $fullname, $st['account_number'], $base, $comp, $loan, $other, $st['deductions_desc'], $final]);
                
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'message' => 'دانش‌آموز یافت نشد.']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }
    
    // 7. Delete item
    if ($ajax_action === 'delete_item') {
        $item_id = (int)$_POST['item_id'];
        try {
            $stmt = $pdo->prepare("DELETE FROM monthly_bursary_items WHERE id = ?");
            $stmt->execute([$item_id]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }

    // 8. Sync all items from student profiles
    if ($ajax_action === 'sync_from_profiles') {
        $list_id = (int)$_POST['list_id'];
        try {
            $stmt = $pdo->prepare("SELECT id, student_id FROM monthly_bursary_items WHERE list_id = ?");
            $stmt->execute([$list_id]);
            $list_items = $stmt->fetchAll();

            $stmt_st = $pdo->prepare("SELECT account_number, base_bursary, computer_installment, loan_installment, other_deductions, deductions_desc FROM students WHERE id = ?");
            $stmt_upd = $pdo->prepare("UPDATE monthly_bursary_items SET account_number = ?, base_amount = ?, computer_installment = ?, loan_installment = ?, other_deductions = ?, deductions_desc = ?, final_amount = ? WHERE id = ?");

            foreach ($list_items as $itm) {
                $stmt_st->execute([$itm['student_id']]);
                $st = $stmt_st->fetch();
                if ($st) {
                    $base = $st['base_bursary'] ?? 20000000;
                    $comp = $st['computer_installment'] ?? 0;
                    $loan = $st['loan_installment'] ?? 0;
                    $other = $st['other_deductions'] ?? 0;
                    $final = $base - $comp - $loan - $other;
                    $stmt_upd->execute([$st['account_number'], $base, $comp, $loan, $other, $st['deductions_desc'], $final, $itm['id']]);
                }
            }
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit();
    }
}

// ----------------------------------------------------
// STANDARD POST ACTION HANDLERS
// ----------------------------------------------------
$message = '';
$message_type = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax_action'])) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_list') {
        $year = trim($_POST['year'] ?? '');
        $month = trim($_POST['month'] ?? '');
        
        if (empty($year) || empty($month)) {
            $message = "لطفاً سال و ماه را مشخص کنید.";
            $message_type = "error";
        } else {
            $stmt = $pdo->prepare("SELECT id FROM monthly_bursary_lists WHERE year = ? AND month = ?");
            $stmt->execute([$year, $month]);
            if ($stmt->fetch()) {
                $message = "لیست پرداخت بورسیه برای $month $year قبلاً ایجاد شده است.";
                $message_type = "error";
            } else {
                try {
                    $pdo->beginTransaction();
                    $created_at = date('Y/m/d H:i:s');
                    $ins = $pdo->prepare("INSERT INTO monthly_bursary_lists (year, month, status, created_at) VALUES (?, ?, 'draft', ?)");
                    $ins->execute([$year, $month, $created_at]);
                    $list_id = $pdo->lastInsertId();
                    
                    // Fetch eligible students
                    $students = $pdo->query("SELECT id, name, surname, account_number, base_bursary, computer_installment, loan_installment, other_deductions, deductions_desc FROM students WHERE status IN ('active', 'university') AND bursary_eligible = 1")->fetchAll();
                    
                    $ins_item = $pdo->prepare("INSERT INTO monthly_bursary_items (list_id, student_id, student_name, account_number, base_amount, computer_installment, loan_installment, other_deductions, deductions_desc, final_amount, is_selected) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
                    
                    foreach ($students as $st) {
                        $base = $st['base_bursary'] ?? 20000000;
                        $comp = $st['computer_installment'] ?? 0;
                        $loan = $st['loan_installment'] ?? 0;
                        $other = $st['other_deductions'] ?? 0;
                        $final = $base - $comp - $loan - $other;
                        $fullname = $st['name'] . ' ' . $st['surname'];
                        
                        $ins_item->execute([$list_id, $st['id'], $fullname, $st['account_number'], $base, $comp, $loan, $other, $st['deductions_desc'], $final]);
                    }
                    
                    $pdo->commit();
                    $message = "لیست پرداخت بورسیه برای $month $year با موفقیت پیش‌نویس شد.";
                    $message_type = "success";
                    $_GET['view_id'] = $list_id;
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $message = "خطا در ایجاد لیست: " . $e->getMessage();
                    $message_type = "error";
                }
            }
        }
    }
    
    if ($action === 'submit_to_admin') {
        $list_id = (int)$_POST['list_id'];
        $stmt = $pdo->prepare("UPDATE monthly_bursary_lists SET status = 'pending_admin' WHERE id = ? AND status = 'draft'");
        if ($stmt->execute([$list_id])) {
            $message = "لیست جهت بررسی و تایید برای مدیریت ارسال شد.";
            $message_type = "success";
        }
    }
    
    if ($action === 'admin_approve') {
        $list_id = (int)$_POST['list_id'];
        $now = date('Y/m/d H:i:s');
        $stmt = $pdo->prepare("UPDATE monthly_bursary_lists SET status = 'pending_signatures', admin_approved_at = ? WHERE id = ? AND status = 'pending_admin'");
        if ($stmt->execute([$now, $list_id])) {
            $message = "لیست با موفقیت تایید و جهت امضا به پورتال هیئت مدیره ارسال گردید.";
            $message_type = "success";
        }
    }
    
    if ($action === 'archive_list') {
        $list_id = (int)$_POST['list_id'];
        $stmt = $pdo->prepare("UPDATE monthly_bursary_lists SET status = 'paid' WHERE id = ? AND status = 'signed'");
        if ($stmt->execute([$list_id])) {
            $message = "لیست پرداخت شده اعلام و بایگانی گردید.";
            $message_type = "success";
        }
    }

    if ($action === 'delete_list') {
        $list_id = (int)$_POST['list_id'];
        try {
            $pdo->beginTransaction();
            $stmt_items = $pdo->prepare("DELETE FROM monthly_bursary_items WHERE list_id = ?");
            $stmt_items->execute([$list_id]);
            
            $stmt_list = $pdo->prepare("DELETE FROM monthly_bursary_lists WHERE id = ?");
            $stmt_list->execute([$list_id]);
            $pdo->commit();
            
            $message = "پیش‌نویس لیست پرداخت با موفقیت حذف گردید.";
            $message_type = "success";
            $view_id = 0;
            unset($_GET['view_id']);
        } catch (Exception $e) {
            $pdo->rollBack();
            $message = "خطا در حذف پیش‌نویس: " . $e->getMessage();
            $message_type = "error";
        }
    }
}

// ----------------------------------------------------
// FETCH PAGE DATA
// ----------------------------------------------------
$lists = $pdo->query("SELECT * FROM monthly_bursary_lists ORDER BY year DESC, month DESC")->fetchAll();

$active_list = null;
$active_items = [];
$view_id = (int)($_GET['view_id'] ?? ($_POST['list_id'] ?? 0));

if ($view_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM monthly_bursary_lists WHERE id = ?");
    $stmt->execute([$view_id]);
    $active_list = $stmt->fetch();
    
    if ($active_list) {
        $stmt = $pdo->prepare("SELECT * FROM monthly_bursary_items WHERE list_id = ? ORDER BY id ASC");
        $stmt->execute([$view_id]);
        $active_items = $stmt->fetchAll();
    }
}

$available_students = [];
if ($active_list && $active_list['status'] === 'draft') {
    $stmt = $pdo->prepare("
        SELECT id, name, surname, code 
        FROM students 
        WHERE status IN ('active', 'university') 
          AND id NOT IN (SELECT student_id FROM monthly_bursary_items WHERE list_id = ?)
        ORDER BY name ASC
    ");
    $stmt->execute([$view_id]);
    $available_students = $stmt->fetchAll();
}

function toFarsi($str) {
    $farsi = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    $latin = ['0','1','2','3','4','5','6','7','8','9'];
    return str_replace($latin, $farsi, (string)$str);
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت پرداخت بورسیه و وب‌سرویس بانک پارسیان | بنیاد حکمت</title>
<style>
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:100;font-display:swap;src:url('/assets/fonts/Vazirmatn-100.woff2') format('woff2')}
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:300;font-display:swap;src:url('/assets/fonts/Vazirmatn-300.woff2') format('woff2')}
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:400;font-display:swap;src:url('/assets/fonts/Vazirmatn-400.woff2') format('woff2')}
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:500;font-display:swap;src:url('/assets/fonts/Vazirmatn-500.woff2') format('woff2')}
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:700;font-display:swap;src:url('/assets/fonts/Vazirmatn-700.woff2') format('woff2')}
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:900;font-display:swap;src:url('/assets/fonts/Vazirmatn-900.woff2') format('woff2')}
</style>
<link rel="stylesheet" href="/assets/tailwind.min.css">
<script defer src="/assets/alpine.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Vazirmatn', 'sans-serif'] },
                    colors: { primary: { 900: '#00141e', 800: '#115e59', 600: '#14b8a6' } }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 font-sans text-gray-800 antialiased min-h-screen pb-20" x-data="bursaryManager(<?php echo htmlspecialchars(json_encode(array_map(function($i) {
    return [
        'id' => (int)$i['id'],
        'student_id' => (int)$i['student_id'],
        'student_name' => $i['student_name'],
        'account_number' => $i['account_number'] ?? '',
        'base_amount' => (int)$i['base_amount'],
        'computer_installment' => (int)$i['computer_installment'],
        'loan_installment' => (int)$i['loan_installment'],
        'other_deductions' => (int)$i['other_deductions'],
        'deductions_desc' => $i['deductions_desc'] ?? '',
        'final_amount' => (int)$i['final_amount'],
        'is_selected' => isset($i['is_selected']) ? (int)$i['is_selected'] : 1
    ];
}, $active_items))); ?>)">

    <!-- Navigation Header -->
    <nav class="bg-white/80 backdrop-blur-md border-b sticky top-0 z-50">
        <div class="container mx-auto px-6 py-4 flex flex-col sm:flex-row justify-between items-center gap-4">
            <div class="flex items-center gap-4">
                <a href="index.php" class="text-primary-900 font-bold text-sm flex items-center gap-2 group">
                    <span class="group-hover:translate-x-1 transition-transform">→</span>
                    میز کار مدیریت
                </a>
                <span class="text-gray-300">/</span>
                <span class="font-bold text-gray-900">لیست پرداخت‌های ماهیانه و اتصال بانک پارسیان</span>
            </div>
            <div class="flex items-center gap-3">
                <a href="parsian-settings.php" class="px-4 py-2 bg-red-50 hover:bg-red-100 text-red-700 text-xs font-black rounded-2xl border border-red-200 transition flex items-center gap-2">
                    <span>🏦</span> تنظیمات وب‌سرویس بانک پارسیان
                    <span class="w-2 h-2 rounded-full <?php echo ($bankSettings['is_sandbox'] ?? 1) ? 'bg-amber-500' : 'bg-emerald-500 animate-pulse'; ?>"></span>
                </a>
            </div>
        </div>
    </nav>

    <main class="container mx-auto px-6 py-10 max-w-7xl">
        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            
            <!-- Left Panel: Create & Lists History -->
            <div class="lg:col-span-1 space-y-6">
                <!-- Create Form -->
                <div class="bg-white rounded-[2rem] p-6 shadow-sm border border-gray-100">
                    <h3 class="text-sm font-black text-primary-900 mb-4 flex items-center gap-2">
                        <span>➕</span> ایجاد لیست بورسیه جدید
                    </h3>
                    <form action="bursary-payments.php" method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="create_list">
                        <div>
                            <label class="text-[10px] font-bold text-gray-400 block mb-1 px-1">سال شمسی</label>
                            <input type="text" name="year" required value="1405" placeholder="مثلاً 1405" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-xs font-bold focus:outline-none focus:ring-1 focus:ring-teal-500">
                        </div>
                        <div>
                            <label class="text-[10px] font-bold text-gray-400 block mb-1 px-1">ماه شمسی</label>
                            <select name="month" required class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-xs font-bold focus:outline-none focus:ring-1 focus:ring-teal-500 cursor-pointer">
                                <option value="فروردین">فروردین</option>
                                <option value="اردیبهشت">اردیبهشت</option>
                                <option value="خرداد">خرداد</option>
                                <option value="تیر">تیر</option>
                                <option value="مرداد">مرداد</option>
                                <option value="شهریور">شهریور</option>
                                <option value="مهر">مهر</option>
                                <option value="آبان">آبان</option>
                                <option value="آذر">آذر</option>
                                <option value="دی">دی</option>
                                <option value="بهمن">بهمن</option>
                                <option value="اسفند">اسفند</option>
                            </select>
                        </div>
                        <button type="submit" class="w-full py-3 bg-teal-600 hover:bg-primary-900 text-white text-xs font-black rounded-xl shadow-lg transition-colors">
                            ایجاد و استخراج خودکار
                        </button>
                    </form>
                </div>
                
                <!-- History Lists -->
                <div class="bg-white rounded-[2rem] p-6 shadow-sm border border-gray-100">
                    <h3 class="text-sm font-black text-primary-900 mb-4 flex items-center gap-2">
                        <span>📂</span> سوابق دوره‌های پرداخت
                    </h3>
                    <div class="space-y-3 max-h-96 overflow-y-auto pr-1">
                        <?php if (empty($lists)): ?>
                            <p class="text-[10px] text-gray-400 text-center py-4 font-bold">هیچ لیست پرداختی ثبت نشده است.</p>
                        <?php else: ?>
                            <?php foreach ($lists as $ls): 
                                $status_badge = '';
                                if ($ls['status'] === 'draft') $status_badge = '<span class="bg-gray-100 text-gray-600 text-[8px] font-bold px-2 py-0.5 rounded-full">پیش‌نویس</span>';
                                elseif ($ls['status'] === 'pending_admin') $status_badge = '<span class="bg-amber-100 text-amber-700 text-[8px] font-bold px-2 py-0.5 rounded-full">بررسی مدیر</span>';
                                elseif ($ls['status'] === 'pending_signatures') $status_badge = '<span class="bg-blue-100 text-blue-700 text-[8px] font-bold px-2 py-0.5 rounded-full">امضای اعضا</span>';
                                elseif ($ls['status'] === 'signed') $status_badge = '<span class="bg-emerald-100 text-emerald-700 text-[8px] font-bold px-2 py-0.5 rounded-full">امضا شده</span>';
                                elseif ($ls['status'] === 'paid') $status_badge = '<span class="bg-teal-100 text-teal-700 text-[8px] font-bold px-2 py-0.5 rounded-full">بایگانی/پرداخت‌شده</span>';
                            ?>
                            <div class="block p-3 rounded-xl border border-gray-50 hover:bg-gray-50 flex justify-between items-center transition-colors <?php echo $view_id === (int)$ls['id'] ? 'bg-teal-50/50 border-teal-100' : 'bg-white'; ?>">
                                <a href="bursary-payments.php?view_id=<?php echo $ls['id']; ?>" class="text-right flex-1">
                                    <h4 class="text-xs font-black text-primary-900"><?php echo $ls['month'] . ' ' . toFarsi($ls['year']); ?></h4>
                                    <span class="text-[8px] text-gray-400 font-bold block mt-1"><?php echo toFarsi(date('Y/m/d', strtotime($ls['created_at']))); ?></span>
                                </a>
                                <div class="flex items-center gap-2">
                                    <?php echo $status_badge; ?>
                                    <?php if ($ls['status'] !== 'paid'): ?>
                                    <form action="bursary-payments.php" method="POST" onsubmit="return confirm('آیا از حذف پیش‌نویس دوره <?php echo $ls['month'] . ' ' . $ls['year']; ?> اطمینان دارید؟ تمام ردیف‌های این دوره حذف خواهند شد.')" class="inline">
                                        <input type="hidden" name="action" value="delete_list">
                                        <input type="hidden" name="list_id" value="<?php echo $ls['id']; ?>">
                                        <button type="submit" class="text-gray-300 hover:text-rose-600 p-1 transition" title="حذف این پیش‌نویس">
                                            🗑️
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Panel: View & Edit Active Payment Sheet -->
            <div class="lg:col-span-3 space-y-6">
                <?php if ($message): ?>
                <div class="<?php echo $message_type === 'success' ? 'bg-emerald-50 border-emerald-100 text-emerald-700' : 'bg-rose-50 border-rose-100 text-rose-700'; ?> border px-6 py-4 rounded-2xl text-xs font-bold flex items-center gap-2">
                    <span><?php echo $message_type === 'success' ? '✅' : '⚠️'; ?></span>
                    <span><?php echo htmlspecialchars($message); ?></span>
                </div>
                <?php endif; ?>

                <?php if (!$active_list): ?>
                    <div class="bg-white rounded-[3rem] p-12 text-center border border-gray-100 shadow-sm flex flex-col items-center justify-center min-h-[350px]">
                        <span class="text-5xl mb-6">📊</span>
                        <h2 class="text-xl font-black text-primary-900 mb-2">مدیریت مالی و اتصال بانکی بورسیه نخبگان</h2>
                        <p class="text-xs text-gray-400 font-bold max-w-md leading-relaxed">لطفاً یکی از لیست‌های قبلی را از منوی سمت راست انتخاب کنید یا یک لیست جدید برای ماه جاری ایجاد نمایید.</p>
                    </div>
                <?php else: ?>
                    <div class="bg-white rounded-[3rem] p-8 shadow-xl border border-gray-100 relative overflow-hidden">
                        <div class="absolute top-0 left-0 w-full h-2 bg-gradient-to-l from-red-500 via-teal-500 to-indigo-500"></div>
                        
                        <!-- List Title & Action Bar -->
                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 mb-8 border-b pb-6 border-gray-50">
                            <div>
                                <h2 class="text-2xl font-black text-primary-900 mb-1 flex items-center gap-3">
                                    <span>سند بورسیه:</span>
                                    <span class="text-teal-600"><?php echo $active_list['month'] . ' ' . toFarsi($active_list['year']); ?></span>
                                    <span class="text-xs font-bold px-3 py-1 bg-gray-100 text-gray-600 rounded-full">شناسه: #<?php echo $active_list['id']; ?></span>
                                </h2>
                                <p class="text-[10px] text-gray-400 font-bold">تاریخ ایجاد: <?php echo toFarsi(date('Y/m/d H:i', strtotime($active_list['created_at']))); ?></p>
                            </div>
                            
                            <div class="flex flex-wrap items-center gap-3">
                                <!-- Status indicator -->
                                <div class="flex items-center gap-2 px-4 py-2 bg-gray-50 rounded-2xl border">
                                    <span class="text-[10px] text-gray-400 font-bold">وضعیت:</span>
                                    <?php if ($active_list['status'] === 'draft'): ?>
                                        <span class="text-xs font-black text-gray-600 bg-gray-100 px-3 py-1 rounded-xl">پیش‌نویس منشی</span>
                                    <?php elseif ($active_list['status'] === 'pending_admin'): ?>
                                        <span class="text-xs font-black text-amber-700 bg-amber-100 px-3 py-1 rounded-xl">در انتظار تایید مدیرعامل</span>
                                    <?php elseif ($active_list['status'] === 'pending_signatures'): ?>
                                        <span class="text-xs font-black text-blue-700 bg-blue-100 px-3 py-1 rounded-xl">در انتظار امضای هیئت مدیره</span>
                                    <?php elseif ($active_list['status'] === 'signed'): ?>
                                        <span class="text-xs font-black text-emerald-700 bg-emerald-100 px-3 py-1 rounded-xl animate-pulse">✓ امضا شده و نهایی</span>
                                    <?php elseif ($active_list['status'] === 'paid'): ?>
                                        <span class="text-xs font-black text-teal-700 bg-teal-100 px-3 py-1 rounded-xl">📦 پرداخت شده/بایگانی</span>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Primary Action: Send to Parsian API -->
                                <button type="button" @click="confirmAndSubmitParsian(<?php echo $view_id; ?>)" :disabled="submittingToBank || selectedCount === 0" class="px-6 py-3 bg-red-600 hover:bg-red-700 text-white text-xs font-black rounded-2xl shadow-xl transition-all flex items-center gap-2 disabled:opacity-50 transform hover:-translate-y-0.5">
                                    <span x-show="!submittingToBank">🚀 ارسال بچ به وب‌سرویس بانک پارسیان</span>
                                    <span x-show="submittingToBank" class="animate-spin">⌛</span>
                                    <span x-show="submittingToBank">در حال ارسال به بانک...</span>
                                </button>

                                <!-- Parsian Batch File Download -->
                                <a href="bursary-payments.php?action=download_parsian_csv&id=<?php echo $view_id; ?>" class="px-4 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-black rounded-2xl transition flex items-center gap-2" title="دانلود فرمت استاندارد بارگذاری در اینترنت‌بانک پارسیان">
                                    <span>📥</span> فایل بچ پارسیان (CSV)
                                </a>

                                <!-- Delete Draft Button -->
                                <?php if ($active_list['status'] !== 'paid'): ?>
                                    <form action="bursary-payments.php" method="POST" onsubmit="return confirm('آیا از حذف کامل پیش‌نویس دوره <?php echo $active_list['month'] . ' ' . $active_list['year']; ?> اطمینان دارید؟ تمام ردیف‌های این دوره حذف خواهند شد.')" class="inline">
                                        <input type="hidden" name="action" value="delete_list">
                                        <input type="hidden" name="list_id" value="<?php echo $view_id; ?>">
                                        <button type="submit" class="px-4 py-3 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-xs font-black rounded-2xl transition flex items-center gap-1.5 shadow-sm">
                                            <span>🗑️</span> حذف این پیش‌نویس
                                        </button>
                                    </form>
                                <?php endif; ?>

                                <?php if ($active_list['status'] === 'signed'): ?>
                                    <form action="bursary-payments.php?view_id=<?php echo $view_id; ?>" method="POST" onsubmit="return confirm('آیا از پرداخت نهایی و آرشیو سند اطمینان دارید؟')">
                                        <input type="hidden" name="action" value="archive_list">
                                        <input type="hidden" name="list_id" value="<?php echo $view_id; ?>">
                                        <button type="submit" class="px-5 py-3 bg-teal-600 hover:bg-teal-700 text-white text-xs font-black rounded-2xl shadow-lg transition-colors">
                                            📦 بایگانی نهایی
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Parsian Bank Batch Info Box (if submitted) -->
                        <?php if (!empty($active_list['bank_batch_id'])): ?>
                        <div class="bg-red-50/60 border border-red-200 p-5 rounded-3xl mb-8 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-red-600 text-white rounded-2xl flex items-center justify-center text-lg font-black shadow-md">
                                    🏦
                                </div>
                                <div>
                                    <h4 class="text-xs font-black text-red-950 flex items-center gap-2">
                                        بچ انتقال وجه در سامانه بانک پارسیان ثبت شد
                                        <span class="text-[9px] px-2 py-0.5 bg-red-100 text-red-700 rounded-full font-mono">
                                            <?php echo htmlspecialchars($active_list['bank_batch_id']); ?>
                                        </span>
                                    </h4>
                                    <p class="text-[10px] text-red-700/80 mt-0.5">
                                        کد رهگیری: <span class="font-mono font-bold"><?php echo htmlspecialchars($active_list['bank_tracking_code'] ?? '-'); ?></span>
                                        | تاریخ ارسال: <?php echo toFarsi(date('Y/m/d H:i', strtotime($active_list['bank_submitted_at'] ?? 'now'))); ?>
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2">
                                <button type="button" @click="inquireBankStatus(<?php echo $view_id; ?>, '<?php echo htmlspecialchars($active_list['bank_batch_id']); ?>')" class="px-4 py-2 bg-white text-red-800 hover:bg-red-100 border border-red-200 text-xs font-bold rounded-xl transition shadow-sm flex items-center gap-1.5">
                                    <span>🔄</span> استعلام وضعیت از بانک
                                </button>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Board Signatures Tracking -->
                        <?php if ($active_list['status'] === 'pending_signatures' || $active_list['status'] === 'signed' || $active_list['status'] === 'paid'): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8 bg-gray-50 p-6 rounded-3xl border border-gray-100">
                            <!-- Bahraman -->
                            <div class="flex items-center justify-between bg-white p-4 rounded-2xl shadow-inner border border-gray-50">
                                <div class="flex items-center gap-3">
                                    <span class="text-2xl">✒️</span>
                                    <div>
                                        <h4 class="text-xs font-black text-gray-700">امضای آقای بهنام بهرمن</h4>
                                        <span class="text-[8px] text-gray-400 font-bold block mt-0.5">صاحب امضای مجاز</span>
                                    </div>
                                </div>
                                <div>
                                    <?php if ($active_list['signed_bahraman']): ?>
                                        <span class="text-[10px] font-black text-emerald-700 bg-emerald-50 border border-emerald-100 px-3 py-1.5 rounded-full flex items-center gap-1.5">
                                            <span>✓</span> امضا شده در <?php echo toFarsi(date('Y/m/d H:i', strtotime($active_list['signed_bahraman_at']))); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-[10px] font-black text-amber-700 bg-amber-50 border border-amber-100 px-3 py-1.5 rounded-full flex items-center gap-1.5 animate-pulse">
                                            <span>⌛</span> در انتظار امضا در اینترنت‌بانک
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <!-- Sanobari -->
                            <div class="flex items-center justify-between bg-white p-4 rounded-2xl shadow-inner border border-gray-50">
                                <div class="flex items-center gap-3">
                                    <span class="text-2xl">✒️</span>
                                    <div>
                                        <h4 class="text-xs font-black text-gray-700">امضای آقای مهدی صنوبری</h4>
                                        <span class="text-[8px] text-gray-400 font-bold block mt-0.5">صاحب امضای مجاز</span>
                                    </div>
                                </div>
                                <div>
                                    <?php if ($active_list['signed_sanobari']): ?>
                                        <span class="text-[10px] font-black text-emerald-700 bg-emerald-50 border border-emerald-100 px-3 py-1.5 rounded-full flex items-center gap-1.5">
                                            <span>✓</span> امضا شده در <?php echo toFarsi(date('Y/m/d H:i', strtotime($active_list['signed_sanobari_at']))); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-[10px] font-black text-amber-700 bg-amber-50 border border-amber-100 px-3 py-1.5 rounded-full flex items-center gap-1.5 animate-pulse">
                                            <span>⌛</span> در انتظار امضا در اینترنت‌بانک
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- List Dynamic Summary Stats Cards (Driven by Selected Items) -->
                        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
                            <div class="bg-gray-50/70 p-4 rounded-2xl text-center border border-gray-100">
                                <div class="text-[9px] text-gray-400 font-bold uppercase mb-1">واریزی‌های انتخاب‌شده</div>
                                <div class="text-sm font-black text-primary-900">
                                    <span x-text="selectedCount"></span> از <span x-text="items.length"></span> نفر
                                </div>
                            </div>
                            <div class="bg-gray-50/70 p-4 rounded-2xl text-center border border-gray-100">
                                <div class="text-[9px] text-gray-400 font-bold uppercase mb-1">مبلغ کل پایه انتخاب‌شده</div>
                                <div class="text-sm font-black text-primary-900">
                                    <span x-text="totalSelectedBase.toLocaleString()"></span> <span class="text-[8px] font-bold text-gray-400">ریال</span>
                                </div>
                            </div>
                            <div class="bg-gray-50/70 p-4 rounded-2xl text-center border border-gray-100">
                                <div class="text-[9px] text-gray-400 font-bold uppercase mb-1">کل اقساط کسرشده</div>
                                <div class="text-sm font-black text-rose-600">
                                    <span x-text="totalSelectedDeductions.toLocaleString()"></span> <span class="text-[8px] font-bold text-gray-400">ریال</span>
                                </div>
                            </div>
                            <div class="bg-gray-50/70 p-4 rounded-2xl text-center border border-gray-100">
                                <div class="text-[9px] text-gray-400 font-bold uppercase mb-1">معادل به تومان</div>
                                <div class="text-sm font-black text-indigo-600">
                                    <span x-text="Math.round(totalSelectedNet / 10).toLocaleString()"></span> <span class="text-[8px] font-bold text-indigo-400">تومان</span>
                                </div>
                            </div>
                            <div class="bg-teal-50/70 p-4 rounded-2xl text-center border border-teal-200 col-span-2 md:col-span-1 shadow-sm">
                                <div class="text-[9px] text-teal-600 font-black uppercase mb-1">خالص ارسالی به بانک</div>
                                <div class="text-sm font-black text-teal-700">
                                    <span x-text="totalSelectedNet.toLocaleString()"></span> <span class="text-[8px] font-bold text-teal-500">ریال</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Draft Action Toolbar (Add Student & Sync from Profiles) -->
                        <?php if ($active_list['status'] === 'draft'): ?>
                        <div class="flex flex-col lg:flex-row items-stretch lg:items-center justify-between gap-4 bg-teal-50/40 p-4 rounded-2xl border border-teal-100/50 mb-8" x-data="{ addingStudentId: '' }">
                            <?php if (!empty($available_students)): ?>
                            <div class="flex flex-col sm:flex-row items-center gap-3 flex-1">
                                <span class="text-teal-800 text-xs font-black whitespace-nowrap">➕ افزودن دانش‌آموز به لیست:</span>
                                <select x-model="addingStudentId" class="bg-white border rounded-xl px-4 py-2 text-xs font-bold cursor-pointer text-gray-700 w-full sm:w-auto flex-1">
                                    <option value="">انتخاب از بین سایر مددجویان فعال...</option>
                                    <?php foreach ($available_students as $as): ?>
                                    <option value="<?php echo $as['id']; ?>"><?php echo $as['name'] . ' ' . $as['surname'] . ' (#' . $as['code'] . ')'; ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button @click="addStudentToList(<?php echo $view_id; ?>, addingStudentId)" :disabled="!addingStudentId" class="px-5 py-2 bg-teal-600 hover:bg-primary-900 text-white text-xs font-black rounded-xl disabled:opacity-30 transition-colors shadow whitespace-nowrap">
                                    افزودن
                                </button>
                            </div>
                            <?php else: ?>
                            <div class="text-xs text-gray-500 font-bold">
                                تمامی دانش‌آموزان مشمول در این لیست درج شده‌اند.
                            </div>
                            <?php endif; ?>

                            <div class="flex items-center gap-2">
                                <button type="button" @click="syncFromProfiles(<?php echo $view_id; ?>)" :disabled="syncing" class="px-4 py-2 bg-white hover:bg-indigo-50 text-indigo-700 border border-indigo-200 text-xs font-black rounded-xl transition flex items-center gap-1.5 shadow-sm">
                                    <span x-show="!syncing">🔄 بازخوانی ارقام از پرونده‌ها</span>
                                    <span x-show="syncing" class="animate-spin">⌛</span>
                                    <span x-show="syncing">در حال بروزرسانی...</span>
                                </button>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Items Table -->
                        <div class="overflow-x-auto">
                            <table class="w-full text-right border-collapse">
                                <thead>
                                    <tr class="text-[10px] text-gray-500 uppercase border-b border-gray-200 font-black bg-gray-50/50">
                                        <th class="py-3 px-3 text-center w-12">
                                            <input type="checkbox" :checked="isAllSelected" @change="toggleAllSelection(<?php echo $view_id; ?>, $event.target.checked)" class="w-4 h-4 text-teal-600 rounded focus:ring-teal-500 cursor-pointer">
                                        </th>
                                        <th class="py-3 px-3">نام دانش‌آموز (دوبار کلیک: پرونده)</th>
                                        <th class="py-3 px-3">شماره حساب / شبا</th>
                                        <th class="py-3 px-3">بورسیه پایه (ریال)</th>
                                        <th class="py-3 px-3">قسط کامپیوتر</th>
                                        <th class="py-3 px-3">قسط وام</th>
                                        <th class="py-3 px-3">سایر کسورات</th>
                                        <th class="py-3 px-3">بابت کسورات</th>
                                        <th class="py-3 px-3">خالص پرداختی (ریال)</th>
                                        <th class="py-3 px-3 text-center">عملیات</th>
                                    </tr>
                                </thead>
                                <tbody class="text-xs text-gray-700 divide-y divide-gray-100">
                                    <template x-for="(itm, index) in items" :key="itm.id">
                                        <tr class="hover:bg-gray-50/80 transition-colors" :class="itm.is_selected ? 'bg-white' : 'bg-gray-100/60 opacity-60'">
                                            <!-- Checkbox -->
                                            <td class="py-3.5 px-3 text-center">
                                                <input type="checkbox" :checked="itm.is_selected" @change="toggleRowSelection(itm, $event.target.checked)" class="w-4 h-4 text-teal-600 rounded focus:ring-teal-500 cursor-pointer">
                                            </td>

                                            <!-- Student Name with Double-Click and Direct Link -->
                                            <td class="py-3.5 px-3 font-bold text-gray-900 select-none group/name cursor-pointer" 
                                                @dblclick="openStudentProfile(itm.student_id)" 
                                                title="دوبار کلیک برای باز شدن پرونده شخصی در تب جدید">
                                                <div class="flex items-center gap-2">
                                                    <span x-text="itm.student_name" class="hover:text-teal-600 transition-colors"></span>
                                                    <a :href="'../person-detail.php?id=' + itm.student_id" target="_blank" class="text-[9px] font-bold text-teal-700 bg-teal-50 hover:bg-teal-100 px-2 py-0.5 rounded-lg border border-teal-200/60 transition flex items-center gap-1 opacity-70 group-hover/name:opacity-100" title="مشاهده و ویرایش پروفایل">
                                                        <span>پروفایل</span>
                                                        <span>↗️</span>
                                                    </a>
                                                </div>
                                            </td>

                                            <!-- Account Number -->
                                            <td class="py-3.5 px-3 font-mono text-[11px] text-gray-600">
                                                <span x-text="itm.account_number || 'ثبت نشده'" :class="!itm.account_number ? 'text-rose-500 font-bold text-[9px]' : ''"></span>
                                            </td>

                                            <!-- Base Amount -->
                                            <td class="py-3.5 px-3">
                                                <template x-if="itm.isEditing">
                                                    <input type="number" x-model.number="itm.base_amount" @input="calcRowNet(itm)" class="w-24 bg-gray-50 border rounded-lg px-2 py-1 text-xs font-bold focus:ring-1 focus:ring-teal-500">
                                                </template>
                                                <template x-if="!itm.isEditing">
                                                    <span x-text="itm.base_amount.toLocaleString()"></span>
                                                </template>
                                            </td>

                                            <!-- Computer Installment -->
                                            <td class="py-3.5 px-3">
                                                <template x-if="itm.isEditing">
                                                    <input type="number" x-model.number="itm.computer_installment" @input="calcRowNet(itm)" class="w-20 bg-gray-50 border rounded-lg px-2 py-1 text-xs font-bold focus:ring-1 focus:ring-teal-500 text-rose-600">
                                                </template>
                                                <template x-if="!itm.isEditing">
                                                    <span :class="itm.computer_installment > 0 ? 'text-rose-600 font-bold' : 'text-gray-400'" x-text="itm.computer_installment > 0 ? itm.computer_installment.toLocaleString() : '۰'"></span>
                                                </template>
                                            </td>

                                            <!-- Loan Installment -->
                                            <td class="py-3.5 px-3">
                                                <template x-if="itm.isEditing">
                                                    <input type="number" x-model.number="itm.loan_installment" @input="calcRowNet(itm)" class="w-20 bg-gray-50 border rounded-lg px-2 py-1 text-xs font-bold focus:ring-1 focus:ring-teal-500 text-rose-600">
                                                </template>
                                                <template x-if="!itm.isEditing">
                                                    <span :class="itm.loan_installment > 0 ? 'text-rose-600 font-bold' : 'text-gray-400'" x-text="itm.loan_installment > 0 ? itm.loan_installment.toLocaleString() : '۰'"></span>
                                                </template>
                                            </td>

                                            <!-- Other Deductions -->
                                            <td class="py-3.5 px-3">
                                                <template x-if="itm.isEditing">
                                                    <input type="number" x-model.number="itm.other_deductions" @input="calcRowNet(itm)" class="w-20 bg-gray-50 border rounded-lg px-2 py-1 text-xs font-bold focus:ring-1 focus:ring-teal-500 text-rose-600">
                                                </template>
                                                <template x-if="!itm.isEditing">
                                                    <span :class="itm.other_deductions > 0 ? 'text-rose-600 font-bold' : 'text-gray-400'" x-text="itm.other_deductions > 0 ? itm.other_deductions.toLocaleString() : '۰'"></span>
                                                </template>
                                            </td>

                                            <!-- Deductions Desc -->
                                            <td class="py-3.5 px-3 text-gray-500 text-[10px]">
                                                <template x-if="itm.isEditing">
                                                    <input type="text" x-model="itm.deductions_desc" class="w-32 bg-gray-50 border rounded-lg px-2 py-1 text-xs focus:ring-1 focus:ring-teal-500">
                                                </template>
                                                <template x-if="!itm.isEditing">
                                                    <span x-text="itm.deductions_desc || '-'"></span>
                                                </template>
                                            </td>

                                            <!-- Final Amount -->
                                            <td class="py-3.5 px-3 text-emerald-600 font-black text-sm font-mono" x-text="itm.final_amount.toLocaleString()" dir="ltr"></td>

                                            <!-- Row Actions -->
                                            <td class="py-3.5 px-3 text-center">
                                                <div class="flex items-center justify-center gap-1.5">
                                                    <template x-if="!itm.isEditing">
                                                        <button @click="itm.isEditing = true" class="px-2.5 py-1 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg font-bold text-[10px]">
                                                            ✏️ ویرایش
                                                        </button>
                                                    </template>
                                                    <template x-if="itm.isEditing">
                                                        <div class="flex gap-1">
                                                            <button @click="saveRowItem(itm)" :disabled="itm.saving" class="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-bold text-[10px]">
                                                                ✓ ذخیره
                                                            </button>
                                                            <button @click="itm.isEditing = false" class="px-2 py-1 bg-gray-200 text-gray-700 rounded-lg font-bold text-[10px]">
                                                                ✕
                                                            </button>
                                                        </div>
                                                    </template>
                                                    <button @click="deleteItem(itm.id)" class="px-2 py-1 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-lg font-bold text-[10px]">
                                                        🗑️
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
        </div>
    </main>

    <script>
        function bursaryManager(initialItems) {
            return {
                items: initialItems.map(i => ({ ...i, isEditing: false, saving: false })),
                submittingToBank: false,
                syncing: false,

                get isAllSelected() {
                    return this.items.length > 0 && this.items.every(i => i.is_selected);
                },

                get selectedCount() {
                    return this.items.filter(i => i.is_selected).length;
                },

                get totalSelectedBase() {
                    return this.items.filter(i => i.is_selected).reduce((acc, i) => acc + (i.base_amount || 0), 0);
                },

                get totalSelectedDeductions() {
                    return this.items.filter(i => i.is_selected).reduce((acc, i) => acc + (i.computer_installment || 0) + (i.loan_installment || 0) + (i.other_deductions || 0), 0);
                },

                get totalSelectedNet() {
                    return this.items.filter(i => i.is_selected).reduce((acc, i) => acc + (i.final_amount || 0), 0);
                },

                calcRowNet(itm) {
                    itm.final_amount = (itm.base_amount || 0) - (itm.computer_installment || 0) - (itm.loan_installment || 0) - (itm.other_deductions || 0);
                },

                async toggleRowSelection(itm, checked) {
                    itm.is_selected = checked ? 1 : 0;
                    const formData = new FormData();
                    formData.append('ajax_action', 'toggle_selection');
                    formData.append('item_id', itm.id);
                    formData.append('is_selected', itm.is_selected);
                    try {
                        await fetch('bursary-payments.php', { method: 'POST', body: formData });
                    } catch (e) {
                        console.error(e);
                    }
                },

                async toggleAllSelection(listId, checked) {
                    const val = checked ? 1 : 0;
                    this.items.forEach(i => i.is_selected = val);
                    const formData = new FormData();
                    formData.append('ajax_action', 'toggle_all_selection');
                    formData.append('list_id', listId);
                    formData.append('is_selected', val);
                    try {
                        await fetch('bursary-payments.php', { method: 'POST', body: formData });
                    } catch (e) {
                        console.error(e);
                    }
                },

                async saveRowItem(itm) {
                    itm.saving = true;
                    const formData = new FormData();
                    formData.append('ajax_action', 'update_item');
                    formData.append('item_id', itm.id);
                    formData.append('base_amount', itm.base_amount);
                    formData.append('computer_installment', itm.computer_installment);
                    formData.append('loan_installment', itm.loan_installment);
                    formData.append('other_deductions', itm.other_deductions);
                    formData.append('deductions_desc', itm.deductions_desc || '');
                    
                    try {
                        const res = await fetch('bursary-payments.php', { method: 'POST', body: formData });
                        const data = await res.json();
                        if (data.success) {
                            itm.final_amount = data.final_amount;
                            itm.isEditing = false;
                        } else {
                            alert('خطا در ذخیره‌سازی: ' + data.message);
                        }
                    } catch (e) {
                        alert('خطا در برقراری ارتباط با سرور.');
                    } finally {
                        itm.saving = false;
                    }
                },

                async confirmAndSubmitParsian(listId) {
                    const count = this.selectedCount;
                    const totalRials = this.totalSelectedNet.toLocaleString();
                    const totalTomans = Math.round(this.totalSelectedNet / 10).toLocaleString();

                    const promptMsg = `آیا از ارسال بچ واریز گروهی به وب‌سرویس بانک پارسیان اطمینان دارید؟\n\n- تعداد افراد انتخابی: ${count} نفر\n- مبلغ کل واریزی: ${totalRials} ریال (${totalTomans} تومان)\n\nپس از ارسال، دستور پرداخت در سامانه بانک پارسیان ثبت و جهت امضا به پورتال اعضای مجاز هیئت مدیره ارسال خواهد شد.`;
                    
                    if (!confirm(promptMsg)) return;

                    this.submittingToBank = true;
                    const formData = new FormData();
                    formData.append('ajax_action', 'submit_to_parsian_bank');
                    formData.append('list_id', listId);

                    try {
                        const res = await fetch('bursary-payments.php', { method: 'POST', body: formData });
                        const data = await res.json();
                        if (data.success) {
                            alert('✅ ' + data.message + '\n\nشناسه بچ: ' + data.batch_id + '\nکد پیگیری: ' + data.tracking_code);
                            location.reload();
                        } else {
                            alert('❌ خطا در ارسال به بانک: ' + data.message);
                        }
                    } catch (e) {
                        alert('خطا در ارتباط با سرور.');
                    } finally {
                        this.submittingToBank = false;
                    }
                },

                async inquireBankStatus(listId, batchId) {
                    const formData = new FormData();
                    formData.append('ajax_action', 'inquire_bank_status');
                    formData.append('list_id', listId);
                    formData.append('batch_id', batchId);

                    try {
                        const res = await fetch('bursary-payments.php', { method: 'POST', body: formData });
                        const data = await res.json();
                        if (data.success) {
                            alert('📡 آخرین وضعیت استعلام از وب‌سرویس بانک:\n\nشناسه بچ: ' + data.batch_id + '\nوضعیت: ' + data.status_fa);
                        } else {
                            alert('خطا در استعلام: ' + data.message);
                        }
                    } catch (e) {
                        alert('خطا در ارتباط با سرور.');
                    }
                },

                async addStudentToList(listId, studentId) {
                    if (!studentId) return;
                    const formData = new FormData();
                    formData.append('ajax_action', 'add_student_to_list');
                    formData.append('list_id', listId);
                    formData.append('student_id', studentId);
                    
                    try {
                        const res = await fetch('bursary-payments.php', { method: 'POST', body: formData });
                        const data = await res.json();
                        if (data.success) {
                            location.reload();
                        } else {
                            alert(data.message || 'خطا در افزودن دانش‌آموز');
                        }
                    } catch (e) {
                        alert('خطا در ارتباط با سرور.');
                    }
                },
                
                async deleteItem(itemId) {
                    if (!confirm('آیا از حذف این دانش‌آموز از لیست پرداخت این ماه اطمینان دارید؟')) return;
                    const formData = new FormData();
                    formData.append('ajax_action', 'delete_item');
                    formData.append('item_id', itemId);
                    
                    try {
                        const res = await fetch('bursary-payments.php', { method: 'POST', body: formData });
                        const data = await res.json();
                        if (data.success) {
                            this.items = this.items.filter(i => i.id !== itemId);
                        } else {
                            alert(data.message || 'خطا در حذف');
                        }
                    } catch (e) {
                        alert('خطا در ارتباط با سرور.');
                    }
                },

                openStudentProfile(studentId) {
                    if (!studentId) return;
                    window.open('../person-detail.php?id=' + studentId, '_blank');
                },

                async syncFromProfiles(listId) {
                    if (!confirm('آیا مایلید مبالغ بورسیه و اقساط این لیست با آخرین اطلاعات ثبت‌شده در پرونده دانش‌آموزان همگام‌سازی شود؟')) return;
                    this.syncing = true;
                    const formData = new FormData();
                    formData.append('ajax_action', 'sync_from_profiles');
                    formData.append('list_id', listId);

                    try {
                        const res = await fetch('bursary-payments.php', { method: 'POST', body: formData });
                        const data = await res.json();
                        if (data.success) {
                            alert('✅ تمامی ارقام، شماره حساب‌ها و اقساط با موفقیت از پرونده دانش‌آموزان بازخوانی و به‌روز شدند.');
                            location.reload();
                        } else {
                            alert('خطا در همگام‌سازی: ' + data.message);
                        }
                    } catch (e) {
                        alert('خطا در ارتباط با سرور.');
                    } finally {
                        this.syncing = false;
                    }
                }
            }
        }
    </script>
</body>
</html>
