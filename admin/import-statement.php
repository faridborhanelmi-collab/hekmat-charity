<?php
session_start();
require_once '../includes/db.php';

// Access Control
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: ../login.php");
    exit();
}

// Fetch all donors for the manual assignment dropdown
$donors = $pdo->query("SELECT id, name, surname, phone, card_number FROM donors ORDER BY name ASC")->fetchAll();

$transactions = [];
$error_msg = '';
$success_msg = '';

// Create upload directory if not exists
@mkdir(__DIR__ . '/../uploads/statements', 0777, true);

// ----------------------------------------------------
// UPLOAD & PARSE PROCESS
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['statement_pdf'])) {
    $file = $_FILES['statement_pdf'];
    $fileName = time() . '_' . basename($file['name']);
    $targetPath = __DIR__ . '/../uploads/statements/' . $fileName;
    $fileType = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));

    if ($fileType !== 'pdf') {
        $error_msg = 'فقط فایل‌های PDF صورتحساب بانکی مجاز هستند.';
    } else {
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            // Determine Python script path
            $python_script = '/Users/faridborhanelmi/.gemini/antigravity/brain/0fba12aa-c01d-4fb6-842a-191b81ebddc7/scratch/parse_statement.py';
            if (!file_exists($python_script)) {
                $python_script = __DIR__ . '/parse_statement.py';
            }
            
            $db_path = __DIR__ . '/../hekmat.db';
            
            // Execute the script
            $cmd = "python3 " . escapeshellarg($python_script) . " " . escapeshellarg($targetPath) . " " . escapeshellarg($db_path);
            $output = shell_exec($cmd);
            $result = json_decode($output, true);
            
            if ($result && isset($result['success']) && $result['success']) {
                $all_tx = $result['transactions'];
                $transactions = [];
                $auto_registered_count = 0;
                
                foreach ($all_tx as $tr) {
                    $receipt_no = $tr['receipt_no'];
                    $amount = $tr['amount'];
                    $date = $tr['date'];
                    $description = $tr['description'];
                    $donor_id = $tr['donor_id'];
                    
                    // Check if receipt_no already exists in donations
                    $stmt = $pdo->prepare("SELECT id FROM donations WHERE receipt_no = ?");
                    $stmt->execute([$receipt_no]);
                    if ($stmt->fetch()) {
                        continue;
                    }
                    
                    if ($donor_id) {
                        $parts = explode('/', $date);
                        $year = $parts[0] ?? '';
                        $months = [
                            '01' => 'فروردین', '02' => 'اردیبهشت', '03' => 'خرداد',
                            '04' => 'تیر', '05' => 'مرداد', '06' => 'شهریور',
                            '07' => 'مهر', '08' => 'آبان', '09' => 'آذر',
                            '10' => 'دی', '11' => 'بهمن', '12' => 'اسفند'
                        ];
                        $month_num = $parts[1] ?? '';
                        $month = $months[$month_num] ?? 'سایر';
                        
                        $ins = $pdo->prepare("INSERT INTO donations (donor_id, amount, date, month, year, receipt_no, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $ins->execute([$donor_id, $amount, $date, $month, $year, $receipt_no, $description]);
                        $auto_registered_count++;
                    } else {
                        $transactions[] = $tr;
                    }
                }
                
                if ($auto_registered_count > 0) {
                    $success_msg = "تعداد " . toFarsiDigits($auto_registered_count) . " کمک مالی جدید به صورت خودکار شناسایی و در سیستم ثبت شد.";
                } else {
                    $success_msg = "پردازش صورتحساب با موفقیت انجام شد.";
                }
                if (empty($transactions)) {
                    $success_msg .= " تمام تراکنش‌ها با موفقیت ثبت شدند و هیچ موردی نیاز به تطبیق دستی ندارد!";
                } else {
                    $success_msg .= " تعداد " . toFarsiDigits(count($transactions)) . " تراکنش شناسایی نشدند و نیاز به تطبیق دستی دارند.";
                }
            } else {
                $error_msg = 'خطا در تحلیل فایل PDF: ' . ($result['error'] ?? 'خروجی نامعتبر از مفسر.');
            }
            
            // Clean up statement file after parsing
            @unlink($targetPath);
        } else {
            $error_msg = 'خطا در بارگذاری فایل به سرور.';
        }
    }
}

// ----------------------------------------------------
// AJAX HANDLER FOR AUTO-SAVING CARD & REGISTERING
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'register_matched') {
    header('Content-Type: application/json');
    $donor_id = (int)$_POST['donor_id'];
    $amount = (int)$_POST['amount'];
    $date = $_POST['date']; // YYYY/MM/DD
    $description = $_POST['description'];
    $receipt_no = $_POST['receipt_no'] ?? '';
    $save_card = isset($_POST['save_card']) && $_POST['save_card'] === '1';
    $card_number = $_POST['card_number'] ?? '';
    
    // Parse Persian date
    $parts = explode('/', $date);
    $year = $parts[0] ?? '';
    // Map numerical month to Persian name
    $months = [
        '01' => 'فروردین', '02' => 'اردیبهشت', '03' => 'خرداد',
        '04' => 'تیر', '05' => 'مرداد', '06' => 'شهریور',
        '07' => 'مهر', '08' => 'آبان', '09' => 'آذر',
        '10' => 'دی', '11' => 'بهمن', '12' => 'اسفند'
    ];
    $month_num = $parts[1] ?? '';
    $month = $months[$month_num] ?? 'سایر';

    try {
        $pdo->beginTransaction();
        
        // 1. If save card checked, update donor card_number
        if ($save_card && !empty($card_number) && $donor_id > 0) {
            $stmt = $pdo->prepare("UPDATE donors SET card_number = ? WHERE id = ?");
            $stmt->execute([$card_number, $donor_id]);
        }
        
        // 2. Insert into donations
        $stmt = $pdo->prepare("INSERT INTO donations (donor_id, amount, date, month, year, receipt_no, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$donor_id, $amount, $date, $month, $year, $receipt_no, $description]);
        $new_donation_id = $pdo->lastInsertId();
        
        $pdo->commit();
        echo json_encode(['success' => true, 'donation_id' => $new_donation_id]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
    exit();
}

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>اسکن هوشمند صورتحساب بانکی | بنیاد حکمت</title>
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
<body class="bg-gray-50 font-sans text-gray-800 antialiased"
    x-data="{
        donors: <?php echo htmlspecialchars(json_encode($donors)); ?>,
        registeringId: null,
        
        async registerTransaction(idx, donorId, amount, date, description, receiptNo, cardNo, saveCard) {
            this.registeringId = idx;
            const formData = new FormData();
            formData.append('ajax_action', 'register_matched');
            formData.append('donor_id', donorId);
            formData.append('amount', amount);
            formData.append('date', date);
            formData.append('description', description);
            formData.append('receipt_no', receiptNo);
            formData.append('card_number', cardNo);
            formData.append('save_card', saveCard ? '1' : '0');
            
            try {
                const res = await fetch('import-statement.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    alert('کمک مالی با موفقیت ثبت شد!');
                    // Remove row from UI
                    document.getElementById('row-' + idx).remove();
                } else {
                    alert('خطا در ثبت: ' + data.message);
                }
            } catch (e) {
                alert('خطا در ارتباط با سرور.');
            } finally {
                this.registeringId = null;
            }
        }
    }">

    <!-- Navigation -->
    <nav class="bg-white/80 backdrop-blur-md border-b sticky top-0 z-50">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <div class="flex items-center gap-4">
                <a href="financial.php" class="text-primary-900 font-bold text-sm flex items-center gap-2 group">
                    <span class="group-hover:translate-x-1 transition-transform">→</span>
                    بازگشت به دفتر حسابداری
                </a>
                <span class="text-gray-300">/</span>
                <span class="font-bold text-gray-900">اسکن صورتحساب بانکی</span>
            </div>
        </div>
    </nav>

    <main class="container mx-auto px-6 py-12 max-w-5xl">
        <div class="space-y-8">
            
            <!-- Intro & Upload Card -->
            <div class="bg-white rounded-[3rem] p-10 shadow-xl border border-gray-100 relative overflow-hidden">
                <div class="absolute top-0 left-0 w-full h-2 bg-gradient-to-l from-indigo-500 to-teal-500"></div>
                <h1 class="text-2xl font-black text-primary-900 mb-4">اسکنر و تطبیق هوشمند صورتحساب بانکی</h1>
                <p class="text-xs text-gray-500 leading-relaxed mb-8">با آپلود فایل PDF صورتحساب کارت یا حساب بانکی خیریه، تراکنش‌ها به صورت خودکار تحلیل شده و حامیان بر اساس شماره کارت یا نام استخراج شده شناسایی می‌شوند. شما می‌توانید تراکنش‌ها را با یک کلیک تایید و ثبت نمایید.</p>
                
                <?php if ($error_msg): ?>
                <div class="bg-rose-50 border border-rose-100 text-rose-700 px-6 py-4 rounded-2xl text-xs font-bold mb-6"><?php echo $error_msg; ?></div>
                <?php endif; ?>

                <?php if ($success_msg): ?>
                <div class="bg-emerald-50 border border-emerald-100 text-emerald-700 px-6 py-4 rounded-2xl text-xs font-bold mb-6"><?php echo $success_msg; ?></div>
                <?php endif; ?>

                <!-- Upload Form -->
                <form action="import-statement.php" method="POST" enctype="multipart/form-data" class="flex flex-col md:flex-row items-center gap-6 bg-gray-50 p-6 rounded-3xl border border-gray-100">
                    <div class="flex-1 w-full">
                        <label class="text-[10px] font-bold text-gray-400 mb-2 block">فایل PDF صورتحساب بانکی</label>
                        <input type="file" name="statement_pdf" required accept="application/pdf" class="w-full bg-white border border-gray-200 rounded-xl px-4 py-3 text-xs font-bold outline-none cursor-pointer">
                    </div>
                    <button type="submit" class="w-full md:w-auto px-8 py-4 bg-teal-600 hover:bg-primary-900 text-white font-black rounded-2xl shadow-xl transition-all self-end">شروع تحلیل و پردازش</button>
                </form>
            </div>

            <!-- Parsed Transactions Table -->
            <?php if (!empty($transactions)): ?>
            <div class="bg-white rounded-[3rem] p-8 shadow-xl border border-gray-100">
                <h3 class="text-lg font-black text-primary-900 mb-6 flex items-center gap-2">
                    <span>📋</span> لیست تراکنش‌های استخراج شده
                </h3>
                
                <div class="overflow-x-auto">
                    <table class="w-full text-right">
                        <thead>
                            <tr class="text-[10px] text-gray-400 uppercase border-b border-gray-50">
                                <th class="pb-4 font-bold">تاریخ</th>
                                <th class="pb-4 font-bold">شرح تراکنش بانکی</th>
                                <th class="pb-4 font-bold">مبلغ (ریال)</th>
                                <th class="pb-4 font-bold">تطبیق و حامی</th>
                                <th class="pb-4 font-bold text-center">ثبت در سیستم</th>
                            </tr>
                        </thead>
                        <tbody class="text-xs text-gray-700">
                            <?php foreach ($transactions as $idx => $tr): 
                                // Extract card number if present in description for learning
                                $card_match = re_search_card($tr['description']);
                            ?>
                            <tr class="border-b border-gray-50 last:border-b-0" id="row-<?php echo $idx; ?>"
                                x-data="{
                                    donorId: '<?php echo $tr['donor_id'] ?: ''; ?>',
                                    cardNo: '<?php echo $card_match ?: ''; ?>',
                                    saveCard: <?php echo $card_match ? 'true' : 'false'; ?>
                                }">
                                <td class="py-4">
                                    <span class="px-2 py-0.5 bg-gray-100 rounded font-bold"><?php echo toFarsiDigits($tr['date']); ?></span>
                                </td>
                                <td class="py-4">
                                    <div class="font-bold text-gray-800 break-words whitespace-normal leading-relaxed max-w-xs"><?php echo $tr['description']; ?></div>
                                </td>
                                <td class="py-4 text-emerald-600 font-black text-sm" dir="ltr">
                                    <?php echo toFarsiDigits(number_format($tr['amount'])); ?>
                                </td>
                                <td class="py-4">
                                    <?php if ($tr['donor_id']): ?>
                                        <div class="flex items-center gap-2 text-emerald-700 bg-emerald-50 px-3 py-1.5 rounded-xl font-bold">
                                            <span>✓</span>
                                            <span>شناسایی خودکار: <?php echo $tr['donor_name']; ?></span>
                                            <span class="text-[9px] opacity-60">(از طریق <?php echo $tr['match_method'] === 'card' ? 'شماره کارت' : 'نام'; ?>)</span>
                                        </div>
                                    <?php else: ?>
                                        <div class="space-y-2">
                                            <select x-model="donorId" class="bg-amber-50 border-none rounded-xl px-3 py-1.5 font-bold text-amber-900 cursor-pointer focus:ring-0">
                                                <option value="">تخصیص به حامی...</option>
                                                <?php foreach ($donors as $dn): ?>
                                                <option value="<?php echo $dn['id']; ?>"><?php echo $dn['name'] . ' ' . $dn['surname'] . ' (' . ($dn['card_number'] ? 'دارد' : 'بدون کارت') . ')'; ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            
                                            <?php if ($card_match): ?>
                                            <label class="flex items-center gap-2 cursor-pointer text-[10px] text-gray-400 font-bold">
                                                <input type="checkbox" x-model="saveCard" class="rounded border-gray-300 text-teal-600 focus:ring-teal-500">
                                                <span>ذخیره شماره کارت (<?php echo $card_match; ?>) برای آینده</span>
                                            </label>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 text-center">
                                    <button 
                                        @click="registerTransaction(
                                            <?php echo $idx; ?>, 
                                            donorId, 
                                            <?php echo $tr['amount']; ?>, 
                                            '<?php echo $tr['date']; ?>', 
                                            '<?php echo htmlspecialchars($tr['description']); ?>', 
                                            '<?php echo $tr['receipt_no']; ?>', 
                                            cardNo, 
                                            saveCard
                                        )"
                                        :disabled="!donorId || registeringId !== null"
                                        class="px-4 py-2 bg-teal-600 text-white rounded-xl font-bold shadow-md hover:bg-teal-700 disabled:opacity-30 transition-all text-[10px]">
                                        <span x-show="registeringId === <?php echo $idx; ?>">در حال ثبت...</span>
                                        <span x-show="registeringId !== <?php echo $idx; ?>">تایید و ثبت فیش</span>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </main>


<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/sw.js');
    });
  }
</script>

</body>
</html>
<?php
// Helper function to extract 16 digit card number or 4 groups of 4 digits
function re_search_card($text) {
    // Normalizing numbers
    $farsi_digits = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    $latin_digits = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
    $text = str_replace($farsi_digits, $latin_digits, $text);
    
    // Look for 16 consecutive digits or 4 groups separated by space or dash
    if (preg_match('/(\b\d{4}[-\s]?\d{4}[-\s]?\d{4}[-\s]?\d{4}\b)/', $text, $matches)) {
        return str_replace(['-', ' '], '', $matches[1]);
    }
    // Look for last 4 digits patterns like "کارت...1234"
    if (preg_match('/(?:\*|\b)(\d{4})\b/', $text, $matches)) {
        return $matches[1];
    }
    return '';
}
?>
