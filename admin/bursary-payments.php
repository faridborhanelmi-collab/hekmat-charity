<?php
session_start();
require_once '../includes/db.php';

// Access Control
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: ../login.php");
    exit();
}

// ----------------------------------------------------
// BANK CSV DOWNLOAD HANDLER (Must run before HTML)
// ----------------------------------------------------
if (isset($_GET['action']) && $_GET['action'] === 'download_csv' && isset($_GET['id'])) {
    $list_id = (int)$_GET['id'];
    
    // Fetch list info
    $stmt = $pdo->prepare("SELECT * FROM monthly_bursary_lists WHERE id = ?");
    $stmt->execute([$list_id]);
    $list = $stmt->fetch();
    
    if ($list && ($list['status'] === 'signed' || $list['status'] === 'paid')) {
        // Fetch items
        $stmt = $pdo->prepare("SELECT * FROM monthly_bursary_items WHERE list_id = ? ORDER BY id ASC");
        $stmt->execute([$list_id]);
        $items = $stmt->fetchAll();
        
        $filename = "bursary_payment_" . $list['year'] . "_" . $list['month'] . ".csv";
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        // Output UTF-8 BOM for Excel compatibility
        echo "\xEF\xBB\xBF";
        
        $output = fopen('php://output', 'w');
        
        // Headers
        fputcsv($output, ['ردیف', 'نام و نام خانوادگی', 'شماره حساب', 'مبلغ پایه (ریال)', 'قسط کامپیوتر (ریال)', 'قسط وام (ریال)', 'سایر کسورات (ریال)', 'مبلغ خالص پرداختی (ریال)', 'شرح پرداخت']);
        
        $idx = 1;
        foreach ($items as $item) {
            $desc = "بورسیه " . $list['month'] . " " . $list['year'];
            if (!empty($item['deductions_desc'])) {
                $desc .= " - کسورات: " . $item['deductions_desc'];
            }
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

// ----------------------------------------------------
// AJAX HANDLERS
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json');
    $ajax_action = $_POST['ajax_action'];
    
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
    
    if ($ajax_action === 'add_student_to_list') {
        $list_id = (int)$_POST['list_id'];
        $student_id = (int)$_POST['student_id'];
        
        try {
            // Check if already in list
            $chk = $pdo->prepare("SELECT id FROM monthly_bursary_items WHERE list_id = ? AND student_id = ?");
            $chk->execute([$list_id, $student_id]);
            if ($chk->fetch()) {
                echo json_encode(['success' => false, 'message' => 'این دانش‌آموز قبلاً در لیست این ماه ثبت شده است.']);
                exit();
            }
            
            // Get student default values
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
                
                $ins = $pdo->prepare("INSERT INTO monthly_bursary_items (list_id, student_id, student_name, account_number, base_amount, computer_installment, loan_installment, other_deductions, deductions_desc, final_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
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
            // Check duplicate list
            $stmt = $pdo->prepare("SELECT id FROM monthly_bursary_lists WHERE year = ? AND month = ?");
            $stmt->execute([$year, $month]);
            if ($stmt->fetch()) {
                $message = "لیست پرداخت بورسیه برای $month $year قبلاً ایجاد شده است.";
                $message_type = "error";
            } else {
                try {
                    $pdo->beginTransaction();
                    
                    // 1. Create list record
                    $created_at = date('Y/m/d H:i:s');
                    $ins = $pdo->prepare("INSERT INTO monthly_bursary_lists (year, month, status, created_at) VALUES (?, ?, 'draft', ?)");
                    $ins->execute([$year, $month, $created_at]);
                    $list_id = $pdo->lastInsertId();
                    
                    // 2. Fetch eligible students
                    $students = $pdo->query("SELECT id, name, surname, account_number, base_bursary, computer_installment, loan_installment, other_deductions, deductions_desc FROM students WHERE status = 'active' AND bursary_eligible = 1")->fetchAll();
                    
                    // 3. Insert items
                    $ins_item = $pdo->prepare("INSERT INTO monthly_bursary_items (list_id, student_id, student_name, account_number, base_amount, computer_installment, loan_installment, other_deductions, deductions_desc, final_amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    
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
                    
                    $_GET['view_id'] = $list_id; // auto redirect view
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
}

// ----------------------------------------------------
// FETCH PAGE DATA
// ----------------------------------------------------
// Fetch all lists
$lists = $pdo->query("SELECT * FROM monthly_bursary_lists ORDER BY year DESC, month DESC")->fetchAll();

// Active viewing list
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

// Fetch all active students NOT in active list for adding option
$available_students = [];
if ($active_list && $active_list['status'] === 'draft') {
    $stmt = $pdo->prepare("
        SELECT id, name, surname, code 
        FROM students 
        WHERE status = 'active' 
          AND id NOT IN (SELECT student_id FROM monthly_bursary_items WHERE list_id = ?)
        ORDER BY name ASC
    ");
    $stmt->execute([$view_id]);
    $available_students = $stmt->fetchAll();
}

function toFarsi($str) {
    $farsi = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    $latin = ['0','1','2','3','4','5','6','7','8','9'];
    return str_replace($latin, $farsi, $str);
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت پرداخت بورسیه ماهیانه | بنیاد حکمت</title>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@100;400;700;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
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

    <!-- iOS PWA/Homescreen Setup -->
    <link rel="apple-touch-icon" href="logo.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="بنیاد حکمت">
    <link rel="icon" type="image/png" href="logo.png">
    <link rel="manifest" href="manifest.json">
</head>
<body class="bg-gray-50 font-sans text-gray-800 antialiased" x-data="bursaryPage()">

    <!-- Navigation -->
    <nav class="bg-white/80 backdrop-blur-md border-b sticky top-0 z-50">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <div class="flex items-center gap-4">
                <a href="index.php" class="text-primary-900 font-bold text-sm flex items-center gap-2 group">
                    <span class="group-hover:translate-x-1 transition-transform">→</span>
                    بازگشت به میز کار مدیریت
                </a>
                <span class="text-gray-300">/</span>
                <span class="font-bold text-gray-900">لیست پرداخت‌های ماهیانه</span>
            </div>
        </div>
    </nav>

    <main class="container mx-auto px-6 py-12 max-w-7xl">
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
                            <input type="text" name="year" required placeholder="مثلاً 1405" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-xs font-bold focus:outline-none focus:ring-1 focus:ring-teal-500">
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
                        <span>📂</span> لیست‌های پرداخت شده یا جاری
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
                            <a href="bursary-payments.php?view_id=<?php echo $ls['id']; ?>" class="block p-3 rounded-xl border border-gray-50 hover:bg-gray-50 flex justify-between items-center transition-colors <?php echo $view_id === (int)$ls['id'] ? 'bg-teal-50/50 border-teal-100' : 'bg-white'; ?>">
                                <div class="text-right">
                                    <h4 class="text-xs font-black text-primary-900"><?php echo $ls['month'] . ' ' . toFarsi($ls['year']); ?></h4>
                                    <span class="text-[8px] text-gray-400 font-bold block mt-1"><?php echo toFarsi(date('Y/m/d', strtotime($ls['created_at']))); ?></span>
                                </div>
                                <div>
                                    <?php echo $status_badge; ?>
                                </div>
                            </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Panel: View & Edit Active Payment Sheet -->
            <div class="lg:col-span-3 space-y-6">
                <?php if ($message): ?>
                <div class="<?php echo $message_type === 'success' ? 'bg-emerald-50 border-emerald-100 text-emerald-700' : 'bg-rose-50 border-rose-100 text-rose-700'; ?> border px-6 py-4 rounded-2xl text-xs font-bold">
                    <?php echo $message; ?>
                </div>
                <?php endif; ?>

                <?php if (!$active_list): ?>
                    <div class="bg-white rounded-[3rem] p-12 text-center border border-gray-100 shadow-sm flex flex-col items-center justify-center min-h-[350px]">
                        <span class="text-5xl mb-6">📊</span>
                        <h2 class="text-xl font-black text-primary-900 mb-2">مدیریت مالی بورسیه نخبگان</h2>
                        <p class="text-xs text-gray-400 font-bold max-w-md leading-relaxed">لطفاً یکی از لیست‌های قبلی را از منوی سمت راست انتخاب کنید یا یک لیست جدید برای ماه جاری ایجاد نمایید.</p>
                    </div>
                <?php else: 
                    $total_base = 0;
                    $total_comp = 0;
                    $total_loan = 0;
                    $total_other = 0;
                    $total_net = 0;
                    foreach ($active_items as $item) {
                        $total_base += $item['base_amount'];
                        $total_comp += $item['computer_installment'];
                        $total_loan += $item['loan_installment'];
                        $total_other += $item['other_deductions'];
                        $total_net += $item['final_amount'];
                    }
                ?>
                    <div class="bg-white rounded-[3rem] p-8 shadow-xl border border-gray-100 relative overflow-hidden">
                        <div class="absolute top-0 left-0 w-full h-2 bg-gradient-to-l from-teal-500 to-indigo-500"></div>
                        
                        <!-- List Title & Status Badges -->
                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 mb-8 border-b pb-6 border-gray-50">
                            <div>
                                <h2 class="text-2xl font-black text-primary-900 mb-1 flex items-center gap-2">
                                    <span>سند مالی بورسیه:</span>
                                    <span class="text-teal-600"><?php echo $active_list['month'] . ' ' . toFarsi($active_list['year']); ?></span>
                                </h2>
                                <p class="text-[10px] text-gray-400 font-bold">تاریخ ایجاد: <?php echo toFarsi(date('Y/m/d H:i', strtotime($active_list['created_at']))); ?></p>
                            </div>
                            
                            <div class="flex flex-wrap items-center gap-4">
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
                                
                                <!-- Action Buttons based on status -->
                                <?php if ($active_list['status'] === 'draft'): ?>
                                    <form action="bursary-payments.php?view_id=<?php echo $view_id; ?>" method="POST" onsubmit="return confirm('آیا از صحت لیست اطمینان دارید؟ با ارسال به مدیریت، دیگر امکان حذف/اضافه مددجو وجود ندارد.')">
                                        <input type="hidden" name="action" value="submit_to_admin">
                                        <input type="hidden" name="list_id" value="<?php echo $view_id; ?>">
                                        <button type="submit" class="px-6 py-3 bg-teal-600 hover:bg-primary-900 text-white text-xs font-black rounded-xl shadow-lg transition-colors">
                                            🚀 ارسال به مدیریت
                                        </button>
                                    </form>
                                <?php elseif ($active_list['status'] === 'pending_admin'): ?>
                                    <form action="bursary-payments.php?view_id=<?php echo $view_id; ?>" method="POST">
                                        <input type="hidden" name="action" value="admin_approve">
                                        <input type="hidden" name="list_id" value="<?php echo $view_id; ?>">
                                        <button type="submit" class="px-6 py-3 bg-teal-600 hover:bg-primary-900 text-white text-xs font-black rounded-xl shadow-lg transition-colors">
                                            ✓ تایید و ارسال جهت امضا
                                        </button>
                                    </form>
                                <?php elseif ($active_list['status'] === 'signed'): ?>
                                    <div class="flex gap-2">
                                        <a href="bursary-payments.php?action=download_csv&id=<?php echo $view_id; ?>" class="px-6 py-3 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black rounded-xl shadow-lg transition-colors flex items-center gap-2">
                                            📥 دانلود لیست پرداخت بانکی (CSV)
                                        </a>
                                        <form action="bursary-payments.php?view_id=<?php echo $view_id; ?>" method="POST" onsubmit="return confirm('آیا از پرداخت نهایی و آرشیو سند اطمینان دارید؟')">
                                            <input type="hidden" name="action" value="archive_list">
                                            <input type="hidden" name="list_id" value="<?php echo $view_id; ?>">
                                            <button type="submit" class="px-6 py-3 bg-teal-600 hover:bg-primary-900 text-white text-xs font-black rounded-xl shadow-lg transition-colors">
                                                📦 بایگانی و اعلام پرداخت
                                            </button>
                                        </form>
                                    </div>
                                <?php elseif ($active_list['status'] === 'paid'): ?>
                                    <a href="bursary-payments.php?action=download_csv&id=<?php echo $view_id; ?>" class="px-6 py-3 bg-teal-600 hover:bg-primary-900 text-white text-xs font-black rounded-xl shadow-lg transition-colors">
                                        📥 دانلود مجدد لیست بانکی
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Board Signatures Tracking -->
                        <?php if ($active_list['status'] === 'pending_signatures' || $active_list['status'] === 'signed' || $active_list['status'] === 'paid'): ?>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-8 bg-gray-50 p-6 rounded-3xl border border-gray-100">
                            <!-- Bahraman -->
                            <div class="flex items-center justify-between bg-white p-4 rounded-2xl shadow-inner border border-gray-50">
                                <div class="flex items-center gap-3">
                                    <span class="text-2xl">✒️</span>
                                    <div>
                                        <h4 class="text-xs font-black text-gray-700">امضای آقای بهنام بهرمن</h4>
                                        <span class="text-[8px] text-gray-400 font-bold block mt-0.5">عضو هیئت مدیره</span>
                                    </div>
                                </div>
                                <div>
                                    <?php if ($active_list['signed_bahraman']): ?>
                                        <span class="text-[10px] font-black text-emerald-700 bg-emerald-50 border border-emerald-100 px-3 py-1.5 rounded-full flex items-center gap-1.5">
                                            <span>✓</span> امضا شده در <?php echo toFarsi(date('Y/m/d H:i', strtotime($active_list['signed_bahraman_at']))); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-[10px] font-black text-amber-700 bg-amber-50 border border-amber-100 px-3 py-1.5 rounded-full flex items-center gap-1.5 animate-pulse">
                                            <span>⌛</span> در انتظار امضا
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
                                        <span class="text-[8px] text-gray-400 font-bold block mt-0.5">عضو هیئت مدیره</span>
                                    </div>
                                </div>
                                <div>
                                    <?php if ($active_list['signed_sanobari']): ?>
                                        <span class="text-[10px] font-black text-emerald-700 bg-emerald-50 border border-emerald-100 px-3 py-1.5 rounded-full flex items-center gap-1.5">
                                            <span>✓</span> امضا شده در <?php echo toFarsi(date('Y/m/d H:i', strtotime($active_list['signed_sanobari_at']))); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-[10px] font-black text-amber-700 bg-amber-50 border border-amber-100 px-3 py-1.5 rounded-full flex items-center gap-1.5 animate-pulse">
                                            <span>⌛</span> در انتظار امضا
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- List Summary Stats Cards -->
                        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
                            <div class="bg-gray-50/50 p-4 rounded-2xl text-center border">
                                <div class="text-[9px] text-gray-400 font-bold uppercase mb-1">تعداد ردیف‌ها</div>
                                <div class="text-sm font-black text-primary-900"><?php echo toFarsi(count($active_items)); ?> دانش‌آموز</div>
                            </div>
                            <div class="bg-gray-50/50 p-4 rounded-2xl text-center border">
                                <div class="text-[9px] text-gray-400 font-bold uppercase mb-1">مبلغ کل پایه</div>
                                <div class="text-sm font-black text-primary-900"><?php echo toFarsi(number_format($total_base)); ?> <span class="text-[8px] font-bold text-gray-400">ریال</span></div>
                            </div>
                            <div class="bg-gray-50/50 p-4 rounded-2xl text-center border">
                                <div class="text-[9px] text-gray-400 font-bold uppercase mb-1">کل اقساط کسرشده</div>
                                <div class="text-sm font-black text-rose-600"><?php echo toFarsi(number_format($total_comp + $total_loan)); ?> <span class="text-[8px] font-bold text-gray-400">ریال</span></div>
                            </div>
                            <div class="bg-gray-50/50 p-4 rounded-2xl text-center border">
                                <div class="text-[9px] text-gray-400 font-bold uppercase mb-1">سایر کسورات</div>
                                <div class="text-sm font-black text-rose-600"><?php echo toFarsi(number_format($total_other)); ?> <span class="text-[8px] font-bold text-gray-400">ریال</span></div>
                            </div>
                            <div class="bg-teal-50/40 p-4 rounded-2xl text-center border border-teal-100 col-span-2 md:col-span-1">
                                <div class="text-[9px] text-teal-600 font-black uppercase mb-1">خالص کل پرداختی</div>
                                <div class="text-sm font-black text-teal-700"><?php echo toFarsi(number_format($total_net)); ?> <span class="text-[8px] font-bold text-teal-500">ریال</span></div>
                            </div>
                        </div>
                        
                        <!-- Add Student Widget (Draft only) -->
                        <?php if ($active_list['status'] === 'draft' && !empty($available_students)): ?>
                        <div class="flex items-center gap-4 bg-teal-50/40 p-4 rounded-2xl border border-teal-100/50 mb-8" x-data="{ addingStudentId: '' }">
                            <span class="text-teal-600 text-xs font-black">➕ افزودن موردی دانش‌آموز به لیست این ماه:</span>
                            <select x-model="addingStudentId" class="bg-white border rounded-xl px-4 py-2 text-xs font-bold cursor-pointer text-gray-700">
                                <option value="">انتخاب دانش‌آموز...</option>
                                <?php foreach ($available_students as $as): ?>
                                <option value="<?php echo $as['id']; ?>"><?php echo $as['name'] . ' ' . $as['surname'] . ' (#' . $as['code'] . ')'; ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button @click="addStudentToList(<?php echo $view_id; ?>, addingStudentId)" :disabled="!addingStudentId" class="px-6 py-2 bg-teal-600 hover:bg-primary-900 text-white text-[10px] font-black rounded-xl disabled:opacity-30 transition-colors">
                                افزودن به لیست
                            </button>
                        </div>
                        <?php endif; ?>

                        <!-- Items Table -->
                        <div class="overflow-x-auto">
                            <table class="w-full text-right">
                                <thead>
                                    <tr class="text-[9px] text-gray-400 uppercase border-b font-bold">
                                        <th class="pb-4">نام دانش‌آموز</th>
                                        <th class="pb-4">شماره حساب</th>
                                        <th class="pb-4">بورسیه پایه (ریال)</th>
                                        <th class="pb-4">قسط کامپیوتر (ریال)</th>
                                        <th class="pb-4">قسط وام (ریال)</th>
                                        <th class="pb-4">سایر کسورات (ریال)</th>
                                        <th class="pb-4">بابت سایر کسورات</th>
                                        <th class="pb-4">مبلغ خالص (ریال)</th>
                                        <?php if ($active_list['status'] === 'draft'): ?>
                                            <th class="pb-4 text-center">عملیات</th>
                                        <?php endif; ?>
                                    </tr>
                                </thead>
                                <tbody class="text-xs text-gray-700">
                                    <?php foreach ($active_items as $itm): ?>
                                    <tr class="border-b last:border-b-0" id="row-<?php echo $itm['id']; ?>"
                                        x-data="{
                                            isEditing: false,
                                            baseAmt: <?php echo $itm['base_amount']; ?>,
                                            compInst: <?php echo $itm['computer_installment']; ?>,
                                            loanInst: <?php echo $itm['loan_installment']; ?>,
                                            otherDeduct: <?php echo $itm['other_deductions']; ?>,
                                            deductDesc: '<?php echo htmlspecialchars($itm['deductions_desc'] ?? ''); ?>',
                                            netAmt: <?php echo $itm['final_amount']; ?>,
                                            saving: false,
                                            
                                            calcNet() {
                                                this.netAmt = this.baseAmt - this.compInst - this.loanInst - this.otherDeduct;
                                            },
                                            async saveItem() {
                                                this.saving = true;
                                                const formData = new FormData();
                                                formData.append('ajax_action', 'update_item');
                                                formData.append('item_id', <?php echo $itm['id']; ?>);
                                                formData.append('base_amount', this.baseAmt);
                                                formData.append('computer_installment', this.compInst);
                                                formData.append('loan_installment', this.loanInst);
                                                formData.append('other_deductions', this.otherDeduct);
                                                formData.append('deductions_desc', this.deductDesc);
                                                
                                                try {
                                                    const res = await fetch('bursary-payments.php', { method: 'POST', body: formData });
                                                    const data = await res.json();
                                                    if (data.success) {
                                                        this.netAmt = data.final_amount;
                                                        this.isEditing = false;
                                                    } else {
                                                        alert('خطا در ذخیره‌سازی: ' + data.message);
                                                    }
                                                } catch (e) {
                                                    alert('خطا در ارتباط با سرور.');
                                                } finally {
                                                    this.saving = false;
                                                }
                                            }
                                        }">
                                        <td class="py-4 font-bold text-gray-900"><?php echo $itm['student_name']; ?></td>
                                        <td class="py-4 font-mono text-[10px] text-gray-500"><?php echo $itm['account_number'] ?: '<span class="text-rose-400 font-bold text-[8px]">بدون حساب</span>'; ?></td>
                                        
                                        <!-- Editable Columns -->
                                        <td class="py-4">
                                            <template x-if="isEditing">
                                                <input type="number" x-model.number="baseAmt" @input="calcNet()" class="w-24 bg-gray-50 border rounded-lg px-2 py-1 text-xs font-bold focus:ring-1 focus:ring-teal-500">
                                            </template>
                                            <template x-if="!isEditing">
                                                <span x-text="baseAmt.toLocaleString()"></span>
                                            </template>
                                        </td>
                                        
                                        <td class="py-4">
                                            <template x-if="isEditing">
                                                <input type="number" x-model.number="compInst" @input="calcNet()" class="w-20 bg-gray-50 border rounded-lg px-2 py-1 text-xs font-bold focus:ring-1 focus:ring-teal-500 text-rose-600">
                                            </template>
                                            <template x-if="!isEditing">
                                                <span :class="compInst > 0 ? 'text-rose-600 font-bold' : 'text-gray-400'" x-text="compInst > 0 ? compInst.toLocaleString() : '۰'"></span>
                                            </template>
                                        </td>
                                        
                                        <td class="py-4">
                                            <template x-if="isEditing">
                                                <input type="number" x-model.number="loanInst" @input="calcNet()" class="w-20 bg-gray-50 border rounded-lg px-2 py-1 text-xs font-bold focus:ring-1 focus:ring-teal-500 text-rose-600">
                                            </template>
                                            <template x-if="!isEditing">
                                                <span :class="loanInst > 0 ? 'text-rose-600 font-bold' : 'text-gray-400'" x-text="loanInst > 0 ? loanInst.toLocaleString() : '۰'"></span>
                                            </template>
                                        </td>
                                        
                                        <td class="py-4">
                                            <template x-if="isEditing">
                                                <input type="number" x-model.number="otherDeduct" @input="calcNet()" class="w-20 bg-gray-50 border rounded-lg px-2 py-1 text-xs font-bold focus:ring-1 focus:ring-teal-500 text-rose-600">
                                            </template>
                                            <template x-if="!isEditing">
                                                <span :class="otherDeduct > 0 ? 'text-rose-600 font-bold' : 'text-gray-400'" x-text="otherDeduct > 0 ? otherDeduct.toLocaleString() : '۰'"></span>
                                            </template>
                                        </td>
                                        
                                        <td class="py-4 text-gray-500 text-[10px]">
                                            <template x-if="isEditing">
                                                <input type="text" x-model="deductDesc" class="w-32 bg-gray-50 border rounded-lg px-2 py-1 text-xs focus:ring-1 focus:ring-teal-500">
                                            </template>
                                            <template x-if="!isEditing">
                                                <span x-text="deductDesc || '-'"></span>
                                            </template>
                                        </td>
                                        
                                        <!-- Final Amount (calculated) -->
                                        <td class="py-4 text-emerald-600 font-black text-sm" x-text="netAmt.toLocaleString()" dir="ltr"></td>
                                        
                                        <!-- Actions -->
                                        <?php if ($active_list['status'] === 'draft'): ?>
                                        <td class="py-4 text-center">
                                            <div class="flex items-center justify-center gap-2">
                                                <template x-if="!isEditing">
                                                    <button @click="isEditing = true" class="px-3 py-1 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-lg font-bold text-[10px]">
                                                        ⚙️ ویرایش
                                                    </button>
                                                </template>
                                                <template x-if="isEditing">
                                                    <div class="flex gap-1">
                                                        <button @click="saveItem()" :disabled="saving" class="px-3 py-1 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-bold text-[10px] disabled:opacity-50">
                                                            ✓ ذخیره
                                                        </button>
                                                        <button @click="isEditing = false" class="px-3 py-1 bg-rose-600 hover:bg-rose-700 text-white rounded-lg font-bold text-[10px]">
                                                            ✕ لغو
                                                        </button>
                                                    </div>
                                                </template>
                                                <button @click="deleteItem(<?php echo $itm['id']; ?>)" class="px-2 py-1 bg-rose-50 hover:bg-rose-100 text-rose-600 rounded-lg font-bold text-[10px]">
                                                    🗑️ حذف
                                                </button>
                                            </div>
                                        </td>
                                        <?php endif; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
        </div>
    </main>

    <script>
        function bursaryPage() {
            return {
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
                            document.getElementById('row-' + itemId).remove();
                            // Optional: reload to refresh aggregate sums on top card
                            location.reload();
                        } else {
                            alert(data.message || 'خطا در حذف');
                        }
                    } catch (e) {
                        alert('خطا در ارتباط با سرور.');
                    }
                }
            }
        }
    </script>

<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/sw.js');
    });
  }
</script>

</body>
</html>
