<?php
session_start();
require_once '../includes/db.php';

// Access Control
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: ../login.php");
    exit();
}

// ----------------------------------------------------
// EXPORT TO EXCEL (CSV)
// ----------------------------------------------------
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Hekmat_Financial_Report_' . date('Y-m-d') . '.csv');
    
    // Add UTF-8 BOM for Excel Farsi support
    echo "\xEF\xBB\xBF";
    
    $output = fopen('php://output', 'w');
    fputcsv($output, ['ردیف', 'نوع تراکنش', 'نام شخص (حامی/مددجو)', 'سرفصل', 'بابت/شرح', 'تاریخ', 'شماره سند/فیش', 'مبلغ (ریال)']);
    
    // Fetch all donations
    $donations = $pdo->query("
        SELECT d.amount, d.date, d.receipt_no, d.description, dn.name, dn.surname 
        FROM donations d 
        LEFT JOIN donors dn ON d.donor_id = dn.id 
        ORDER BY d.date DESC
    ")->fetchAll();
    
    // Fetch all expenses
    $expenses = $pdo->query("
        SELECT e.amount, e.expense_date, e.receipt_no, e.description, s.name, s.surname, ec.name as cat_name 
        FROM expenses e 
        LEFT JOIN students s ON e.student_id = s.id 
        LEFT JOIN expense_categories ec ON e.category_id = ec.id 
        ORDER BY e.expense_date DESC
    ")->fetchAll();
    
    $ledger = [];
    foreach ($donations as $dn) {
        $ledger[] = [
            'date' => $dn['date'],
            'type' => 'دریافتی (ورودی)',
            'name' => $dn['name'] . ' ' . $dn['surname'],
            'category' => 'کمک‌های مردمی',
            'desc' => $dn['description'],
            'receipt' => $dn['receipt_no'],
            'amount' => $dn['amount']
        ];
    }
    foreach ($expenses as $ex) {
        $ledger[] = [
            'date' => $ex['expense_date'],
            'type' => 'هزینه‌کرد (خروجی)',
            'name' => ($ex['name'] ? $ex['name'] . ' ' . $ex['surname'] : 'عمومی خیریه'),
            'category' => $ex['cat_name'],
            'desc' => $ex['description'],
            'receipt' => $ex['receipt_no'],
            'amount' => -$ex['amount']
        ];
    }
    
    // Sort by date desc
    usort($ledger, function($a, $b) {
        return strcmp($b['date'], $a['date']);
    });
    
    foreach ($ledger as $index => $item) {
        fputcsv($output, [
            $index + 1,
            $item['type'],
            $item['name'],
            $item['category'],
            $item['desc'],
            $item['date'],
            $item['receipt'],
            $item['amount']
        ]);
    }
    fclose($output);
    exit();
}

// ----------------------------------------------------
// FETCH DATA FOR VIEW
// ----------------------------------------------------
// 1. Stats
$total_income = $pdo->query("SELECT SUM(amount) FROM donations")->fetchColumn() ?: 0;
$total_expense = $pdo->query("SELECT SUM(amount) FROM expenses")->fetchColumn() ?: 0;
$balance = $total_income - $total_expense;

// 2. Expense Categories
$categories = $pdo->query("SELECT * FROM expense_categories ORDER BY id ASC")->fetchAll();

// 3. Donors & Students for Autocomplete
$donors = $pdo->query("SELECT id, name, surname, phone FROM donors ORDER BY name ASC")->fetchAll();
$students = $pdo->query("SELECT id, name, surname, code FROM students ORDER BY name ASC")->fetchAll();

// 4. Ledger Data (Donations & Expenses merged)
$donations_raw = $pdo->query("
    SELECT d.id, d.amount, d.date, d.receipt_no, d.description, dn.id as donor_id, dn.name, dn.surname 
    FROM donations d 
    LEFT JOIN donors dn ON d.donor_id = dn.id
")->fetchAll();

$expenses_raw = $pdo->query("
    SELECT e.id, e.amount, e.expense_date as date, e.receipt_no, e.description, e.category_id, s.id as student_id, s.name, s.surname, s.code, ec.name as cat_name 
    FROM expenses e 
    LEFT JOIN students s ON e.student_id = s.id 
    LEFT JOIN expense_categories ec ON e.category_id = ec.id
")->fetchAll();

$ledger = [];
foreach ($donations_raw as $dn) {
    $ledger[] = [
        'id' => $dn['id'],
        'type' => 'income',
        'type_label' => 'کمک دریافتی',
        'person_id' => $dn['donor_id'],
        'person_name' => $dn['name'] . ' ' . $dn['surname'],
        'category_id' => 'income',
        'category_name' => 'کمک‌های مردمی',
        'description' => $dn['description'],
        'date' => $dn['date'],
        'receipt_no' => $dn['receipt_no'],
        'amount' => $dn['amount']
    ];
}
foreach ($expenses_raw as $ex) {
    $ledger[] = [
        'id' => $ex['id'],
        'type' => 'expense',
        'type_label' => 'هزینه‌کرد',
        'person_id' => $ex['student_id'],
        'person_name' => ($ex['name'] ? $ex['name'] . ' ' . $ex['surname'] : 'هزینه عمومی'),
        'category_id' => $ex['category_id'],
        'category_name' => $ex['cat_name'],
        'description' => $ex['description'],
        'date' => $ex['date'],
        'receipt_no' => $ex['receipt_no'],
        'amount' => -$ex['amount']
    ];
}

// Sort ledger by date desc
usort($ledger, function($a, $b) {
    return strcmp($b['date'], $a['date']);
});

// Category stats for charts
$cat_stats = [];
foreach ($categories as $cat) {
    $sum = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE category_id = ?");
    $sum->execute([$cat['id']]);
    $sum_val = $sum->fetchColumn() ?: 0;
    if ($sum_val > 0) {
        $cat_stats[] = [
            'name' => $cat['name'],
            'value' => (int)$sum_val
        ];
    }
}
// Add income to chart stats
if ($total_income > 0) {
    $cat_stats[] = [
        'name' => 'ورودی (کمک‌ها)',
        'value' => (int)$total_income
    ];
}

?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>دفتر حسابداری و مدیریت مالی | بنیاد حکمت</title>
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
    <style>
        @media print {
            body { background: white; color: black; }
            nav, header, .no-print, button, form, .modal { display: none !important; }
            .print-only { display: block !important; }
            .card { border: none !important; box-shadow: none !important; padding: 0 !important; }
            table { width: 100% !important; border-collapse: collapse; }
            th, td { border: 1px solid #ddd !important; padding: 8px !important; font-size: 10px !important; }
        }
    </style>

    <!-- iOS PWA/Homescreen Setup -->
    <link rel="apple-touch-icon" href="logo.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="بنیاد حکمت">
    <link rel="icon" type="image/png" href="logo.png">
</head>
<body class="bg-gray-50 font-sans text-gray-800 antialiased"
    x-data="{
        showIncomeModal: false,
        showExpenseModal: false,
        activeTab: 'all', // 'all', 'income', 'expense'
        activeCategory: 'all',
        searchQuery: '',
        donorSearch: '',
        selectedDonorId: '',
        selectedDonorName: '',
        donors: <?php echo htmlspecialchars(json_encode($donors)); ?>,
        studentSearch: '',
        selectedStudentId: '',
        selectedStudentName: '',
        students: <?php echo htmlspecialchars(json_encode($students)); ?>,
        
        get filteredDonors() {
            if (!this.donorSearch) return [];
            return this.donors.filter(d => 
                (d.name + ' ' + d.surname).includes(this.donorSearch) || 
                d.phone.includes(this.donorSearch)
            ).slice(0, 5);
        },
        get filteredStudents() {
            if (!this.studentSearch) return [];
            return this.students.filter(s => 
                (s.name + ' ' + s.surname).includes(this.studentSearch) || 
                s.code.includes(this.studentSearch)
            ).slice(0, 5);
        }
    }">

    <!-- Navigation (No Print) -->
    <nav class="bg-white/80 backdrop-blur-md border-b sticky top-0 z-50 no-print">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <div class="flex items-center gap-4">
                <a href="../index.php" class="text-primary-900 font-black text-xl flex items-center gap-2">
                    <div class="w-8 h-8 bg-teal-600 rounded-full flex items-center justify-center text-white text-sm">م</div>
                    بنیاد حکمت
                </a>
                <span class="text-gray-300">/</span>
                <span class="font-bold text-gray-600">دفتر حسابداری مالی</span>
            </div>
            <div class="flex items-center gap-4">
                <a href="import-statement.php" class="bg-teal-50 text-teal-700 hover:bg-teal-100 px-4 py-2 rounded-xl text-xs font-bold transition-all flex items-center gap-2">
                    📄 اسکن صورتحساب بانکی (PDF)
                </a>
                <a href="index.php" class="text-primary-900 text-xs font-bold hover:bg-gray-100 px-4 py-2 rounded-lg transition-all">داشبورد ادمین</a>
            </div>
        </div>
    </nav>

    <!-- Header (No Print) -->
    <header class="bg-gradient-to-l from-primary-900 to-indigo-900 text-white py-16 no-print">
        <div class="container mx-auto px-6 flex flex-col md:flex-row justify-between items-center gap-10">
            <div class="text-right">
                <h1 class="text-4xl font-black mb-4">مدیریت حسابداری و شفافیت مالی</h1>
                <p class="text-teal-100 opacity-80 max-w-xl">ثبت و رهگیری مستقیم تمام کمک‌های دریافتی حامیان و مخارج تحصیلی، درمانی و معیشتی دانش‌آموزان به صورت کاملاً یکپارچه.</p>
            </div>
            <div class="flex gap-4">
                <button @click="showIncomeModal = true" class="px-6 py-4 bg-emerald-500 hover:bg-emerald-600 text-white font-black rounded-2xl shadow-xl transition-all hover:-translate-y-0.5">+ ثبت واریزی (درآمد)</button>
                <button @click="showExpenseModal = true" class="px-6 py-4 bg-rose-500 hover:bg-rose-600 text-white font-black rounded-2xl shadow-xl transition-all hover:-translate-y-0.5">+ ثبت هزینه جدید</button>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="container mx-auto px-6 pb-24 -mt-10">
        
        <!-- Stats Summary -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-10">
            <!-- Income Card -->
            <div class="bg-white p-8 rounded-[2.5rem] shadow-lg border border-gray-100 flex items-center justify-between">
                <div>
                    <p class="text-[10px] text-gray-400 font-bold mb-1">مجموع کمک‌های دریافتی (ریال)</p>
                    <h3 class="text-2xl font-black text-emerald-600" dir="ltr"><?php echo toFarsiDigits(number_format($total_income)); ?></h3>
                </div>
                <div class="w-14 h-14 bg-emerald-50 text-emerald-600 rounded-2xl flex items-center justify-center text-2xl">📥</div>
            </div>
            <!-- Expense Card -->
            <div class="bg-white p-8 rounded-[2.5rem] shadow-lg border border-gray-100 flex items-center justify-between">
                <div>
                    <p class="text-[10px] text-gray-400 font-bold mb-1">مجموع هزینه‌کرد مددجویان (ریال)</p>
                    <h3 class="text-2xl font-black text-rose-600" dir="ltr"><?php echo toFarsiDigits(number_format($total_expense)); ?></h3>
                </div>
                <div class="w-14 h-14 bg-rose-50 text-rose-600 rounded-2xl flex items-center justify-center text-2xl">📤</div>
            </div>
            <!-- Balance Card -->
            <div class="bg-white p-8 rounded-[2.5rem] shadow-lg border border-gray-100 flex items-center justify-between">
                <div>
                    <p class="text-[10px] text-gray-400 font-bold mb-1">موجودی صندوق بنیاد (تراز)</p>
                    <h3 class="text-2xl font-black <?php echo $balance >= 0 ? 'text-teal-600' : 'text-red-600'; ?>" dir="ltr">
                        <?php echo ($balance < 0 ? '-' : '') . toFarsiDigits(number_format(abs($balance))); ?>
                    </h3>
                </div>
                <div class="w-14 h-14 bg-teal-50 text-teal-600 rounded-2xl flex items-center justify-center text-2xl">⚖️</div>
            </div>
        </div>

        <!-- Print-only Title -->
        <div class="hidden print-only mb-8 text-center">
            <h1 class="text-2xl font-black">گزارش مالی و ترازنامه بنیاد نیکوکاری حکمت</h1>
            <p class="text-xs text-gray-500 mt-2">تاریخ گزارش: <?php echo toFarsiDigits(date('Y/m/d')); ?></p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-8">
            <!-- Left Side: Interactive SVG Charts (No Print) -->
            <div class="lg:col-span-1 space-y-6 no-print">
                <!-- Expenses by Category -->
                <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-gray-100">
                    <h3 class="font-black text-gray-900 mb-6 text-sm">تفکیک مصارف مالی</h3>
                    <div class="space-y-4">
                        <?php foreach ($cat_stats as $stat): ?>
                        <div>
                            <div class="flex justify-between text-xs font-bold mb-2">
                                <span><?php echo $stat['name']; ?></span>
                                <span dir="ltr"><?php echo toFarsiDigits(number_format($stat['value'])); ?> ریال</span>
                            </div>
                            <div class="w-full bg-gray-100 h-2 rounded-full overflow-hidden">
                                <?php 
                                    $pct = $total_income > 0 ? ($stat['value'] / $total_income) * 100 : 0;
                                    $color = str_contains($stat['name'], 'ورودی') ? 'bg-emerald-500' : 'bg-teal-500';
                                ?>
                                <div class="<?php echo $color; ?> h-full" style="width: <?php echo min(100, $pct); ?>%"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Right Side: Master Ledger -->
            <div class="lg:col-span-3 space-y-6">
                <!-- Ledger Header Controls (No Print) -->
                <div class="bg-white p-6 rounded-[2.5rem] shadow-sm border border-gray-100 flex flex-col md:flex-row justify-between items-center gap-6 no-print">
                    <!-- Filters -->
                    <div class="flex items-center gap-2 bg-gray-100 p-1.5 rounded-2xl w-full md:w-auto">
                        <button @click="activeTab = 'all'" :class="activeTab === 'all' ? 'bg-white text-primary-900 shadow-sm' : 'text-gray-500'" class="px-4 py-2 rounded-xl text-xs font-black transition-all flex-1 md:flex-none">همه</button>
                        <button @click="activeTab = 'income'" :class="activeTab === 'income' ? 'bg-white text-primary-900 shadow-sm' : 'text-gray-500'" class="px-4 py-2 rounded-xl text-xs font-black transition-all flex-1 md:flex-none">ورودی‌ها</button>
                        <button @click="activeTab = 'expense'" :class="activeTab === 'expense' ? 'bg-white text-primary-900 shadow-sm' : 'text-gray-500'" class="px-4 py-2 rounded-xl text-xs font-black transition-all flex-1 md:flex-none">خروجی‌ها</button>
                    </div>

                    <!-- Search and Export -->
                    <div class="flex items-center gap-4 w-full md:w-auto">
                        <div class="relative flex-1 md:flex-none">
                            <input type="text" x-model="searchQuery" placeholder="جستجو در شرح یا نام..." class="bg-gray-50 border border-gray-100 rounded-xl px-10 py-2.5 text-xs focus:outline-none focus:ring-2 focus:ring-teal-500 w-full md:w-60 font-bold">
                            <span class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs">🔍</span>
                        </div>
                        <a href="financial.php?export=excel" class="bg-indigo-50 text-indigo-700 hover:bg-indigo-100 p-3 rounded-xl transition-all" title="دانلود خروجی اکسل">
                            📊 اکسل
                        </a>
                        <button onclick="window.print()" class="bg-gray-100 text-gray-700 hover:bg-gray-200 p-3 rounded-xl transition-all" title="چاپ یا ذخیره PDF">
                            🖨️ چاپ
                        </button>
                    </div>
                </div>

                <!-- Ledger Table -->
                <div class="bg-white rounded-[3rem] p-8 shadow-lg border border-gray-100 overflow-x-auto card">
                    <table class="w-full text-right whitespace-nowrap">
                        <thead>
                            <tr class="text-[11px] text-gray-400 uppercase tracking-wider border-b border-gray-50">
                                <th class="pb-6 font-bold w-12 text-center">ردیف</th>
                                <th class="pb-6 font-bold">نوع</th>
                                <th class="pb-6 font-bold">نام حامی/مددجو</th>
                                <th class="pb-6 font-bold">بابت/شرح سند</th>
                                <th class="pb-6 font-bold">تاریخ و سند</th>
                                <th class="pb-6 font-bold">مبلغ (ریال)</th>
                                <th class="pb-6 font-bold text-center no-print">عملیات</th>
                            </tr>
                        </thead>
                        <tbody class="text-xs text-gray-700">
                            <?php if (empty($ledger)): ?>
                            <tr>
                                <td colspan="7" class="py-10 text-center text-gray-400 font-bold">هیچ تراکنشی یافت نشد.</td>
                            </tr>
                            <?php endif; ?>

                            <?php foreach ($ledger as $idx => $item): ?>
                            <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors"
                                x-show="(activeTab === 'all' || activeTab === '<?php echo $item['type']; ?>') && (searchQuery === '' || '<?php echo htmlspecialchars($item['description'] . ' ' . $item['person_name'] . ' ' . $item['category_name']); ?>'.includes(searchQuery))">
                                <td class="py-4 text-center text-gray-400 font-bold"><?php echo toFarsiDigits($idx + 1); ?></td>
                                <td class="py-4">
                                    <span class="px-2.5 py-1 rounded-full text-[9px] font-black <?php echo $item['type'] === 'income' ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'; ?>">
                                        <?php echo $item['type_label']; ?>
                                    </span>
                                </td>
                                <td class="py-4 font-bold text-gray-900">
                                    <?php if ($item['type'] === 'income'): ?>
                                        <a href="../donor-detail.php?id=<?php echo $item['person_id']; ?>" class="hover:text-teal-600"><?php echo $item['person_name']; ?></a>
                                    <?php else: ?>
                                        <?php if ($item['person_id']): ?>
                                            <a href="../person-detail.php?id=<?php echo $item['person_id']; ?>" class="hover:text-teal-600"><?php echo $item['person_name']; ?></a>
                                        <?php else: ?>
                                            <span class="text-gray-400">عمومی خیریه</span>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                                <td class="py-4">
                                    <div class="font-bold text-gray-800 break-words whitespace-normal leading-relaxed max-w-xs"><?php echo $item['description']; ?></div>
                                    <div class="text-[9px] text-gray-400 mt-1">سرفصل: <?php echo $item['category_name']; ?></div>
                                </td>
                                <td class="py-4">
                                    <span class="px-2 py-0.5 bg-gray-100 rounded text-[9px] font-bold"><?php echo toFarsiDigits($item['date']); ?></span>
                                    <?php if ($item['receipt_no']): ?>
                                    <div class="text-[9px] text-gray-400 mt-1 font-mono">سند: #<?php echo toFarsiDigits($item['receipt_no']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 text-sm font-black <?php echo $item['amount'] > 0 ? 'text-emerald-600' : 'text-rose-600'; ?>" dir="ltr">
                                    <?php echo ($item['amount'] < 0 ? '-' : '') . toFarsiDigits(number_format(abs($item['amount']))); ?>
                                </td>
                                <td class="py-4 text-center no-print">
                                    <?php if ($item['type'] === 'income'): ?>
                                    <a href="receipt.php?id=<?php echo $item['id']; ?>" class="text-[10px] text-teal-600 font-bold hover:underline">📄 فیش</a>
                                    <?php else: ?>
                                    <span class="text-gray-300">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </main>

    <!-- Modal: Register Income (No Print) -->
    <div x-show="showIncomeModal" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-6 bg-primary-900/40 backdrop-blur-sm no-print">
        <div class="bg-white rounded-[2.5rem] w-full max-w-lg p-10 shadow-2xl border border-gray-100 transform transition-all" @click.away="showIncomeModal = false">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-black text-primary-900 flex items-center gap-3">
                    <span class="w-10 h-10 bg-emerald-50 text-emerald-600 rounded-xl flex items-center justify-center text-lg">📥</span>
                    ثبت کمک دریافتی (درآمد)
                </h3>
                <button @click="showIncomeModal = false" class="text-gray-400 hover:text-red-500 transition-colors text-2xl">✕</button>
            </div>

            <form action="../admin-request-handler.php" method="POST" class="space-y-4" @submit.prevent="
                const fd = new FormData($el);
                fd.append('action', 'add_donation');
                fd.append('donor_id', selectedDonorId);
                const res = await fetch('../admin-request-handler.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    alert('با موفقیت ثبت شد.');
                    location.reload();
                } else {
                    alert('خطا در ثبت اطلاعات.');
                }
            ">
                <!-- Donor Selection -->
                <div class="relative">
                    <label class="text-[10px] font-bold text-gray-400 mb-1 block">نام نیکوکار/حامی</label>
                    <input type="text" x-model="donorSearch" placeholder="جستجوی نام نیکوکار..." class="w-full bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 text-xs font-bold outline-none focus:ring-2 focus:ring-emerald-500">
                    <input type="hidden" :value="selectedDonorId">
                    <!-- Dropdown Auto-Complete -->
                    <div x-show="filteredDonors.length > 0" class="absolute left-0 right-0 mt-1 bg-white border rounded-xl shadow-lg z-50 overflow-hidden">
                        <template x-for="d in filteredDonors" :key="d.id">
                            <button type="button" @click="selectedDonorId = d.id; donorSearch = d.name + ' ' + d.surname; selectedDonorName = d.name + ' ' + d.surname;" class="w-full text-right px-4 py-2 hover:bg-emerald-50 text-xs font-bold border-b last:border-b-0 block">
                                <span x-text="d.name + ' ' + d.surname"></span> <span class="text-gray-400 text-[10px]" x-text="'(' + d.phone + ')'"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-[10px] font-bold text-gray-400 mb-1 block">مبلغ (ریال)</label>
                        <input type="text" name="amount" required placeholder="مثال: ۵,۰۰۰,۰۰۰" class="w-full bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 text-xs font-bold outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="text-[10px] font-bold text-gray-400 mb-1 block">شماره سند/فیش</label>
                        <input type="text" name="receipt_no" placeholder="مثال: ۱۲۳۴۵۶" class="w-full bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 text-xs font-bold outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <label class="text-[10px] font-bold text-gray-400 mb-1 block">روز/ماه/سال</label>
                        <input type="text" name="date" required placeholder="۱۴۰۴/۰۱/۱۵" class="w-full bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 text-xs font-bold outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="text-[10px] font-bold text-gray-400 mb-1 block">ماه (حروف فارسی)</label>
                        <input type="text" name="month" required placeholder="فروردین" class="w-full bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 text-xs font-bold outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>
                    <div>
                        <label class="text-[10px] font-bold text-gray-400 mb-1 block">سال (عدد)</label>
                        <input type="text" name="year" required placeholder="۱۴۰۴" class="w-full bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 text-xs font-bold outline-none focus:ring-2 focus:ring-emerald-500">
                    </div>
                </div>

                <div>
                    <label class="text-[10px] font-bold text-gray-400 mb-1 block">بابت/شرح کمک</label>
                    <textarea name="description" rows="2" placeholder="مثال: بورس تحصیلی فروردین ماه دانش‌آموز رضا رضایی" class="w-full bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 text-xs font-bold outline-none focus:ring-2 focus:ring-emerald-500"></textarea>
                </div>

                <button type="submit" class="w-full py-4 bg-emerald-500 hover:bg-emerald-600 text-white font-black rounded-2xl shadow-xl transition-all mt-4">ثبت سند و تشکر حامی</button>
            </form>
        </div>
    </div>

    <!-- Modal: Register Expense (No Print) -->
    <div x-show="showExpenseModal" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-6 bg-primary-900/40 backdrop-blur-sm no-print">
        <div class="bg-white rounded-[2.5rem] w-full max-w-lg p-10 shadow-2xl border border-gray-100 transform transition-all" @click.away="showExpenseModal = false">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-black text-primary-900 flex items-center gap-3">
                    <span class="w-10 h-10 bg-rose-50 text-rose-600 rounded-xl flex items-center justify-center text-lg">📤</span>
                    ثبت هزینه‌کرد جدید
                </h3>
                <button @click="showExpenseModal = false" class="text-gray-400 hover:text-red-500 transition-colors text-2xl">✕</button>
            </div>

            <form action="../admin-request-handler.php" method="POST" class="space-y-4" @submit.prevent="
                const fd = new FormData($el);
                fd.append('action', 'add_expense');
                fd.append('student_id', selectedStudentId);
                const res = await fetch('../admin-request-handler.php', { method: 'POST', body: fd });
                const data = await res.json();
                if (data.success) {
                    alert('هزینه با موفقیت ثبت شد.');
                    location.reload();
                } else {
                    alert('خطا در ثبت اطلاعات.');
                }
            ">
                <!-- Student Selection -->
                <div class="relative">
                    <label class="text-[10px] font-bold text-gray-400 mb-1 block">نام مددجو (دانش‌آموز) - خالی بگذارید برای هزینه عمومی</label>
                    <input type="text" x-model="studentSearch" placeholder="جستجوی نام مددجو..." class="w-full bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 text-xs font-bold outline-none focus:ring-2 focus:ring-rose-500">
                    <input type="hidden" :value="selectedStudentId">
                    <!-- Dropdown Auto-Complete -->
                    <div x-show="filteredStudents.length > 0" class="absolute left-0 right-0 mt-1 bg-white border rounded-xl shadow-lg z-50 overflow-hidden">
                        <template x-for="s in filteredStudents" :key="s.id">
                            <button type="button" @click="selectedStudentId = s.id; studentSearch = s.name + ' ' + s.surname; selectedStudentName = s.name + ' ' + s.surname;" class="w-full text-right px-4 py-2 hover:bg-rose-50 text-xs font-bold border-b last:border-b-0 block">
                                <span x-text="s.name + ' ' + s.surname"></span> <span class="text-gray-400 text-[10px]" x-text="'(' + s.code + ')'"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-[10px] font-bold text-gray-400 mb-1 block">سرفصل هزینه</label>
                        <select name="category_id" required class="w-full bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 text-xs font-bold outline-none focus:ring-2 focus:ring-rose-500">
                            <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo $cat['name']; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="text-[10px] font-bold text-gray-400 mb-1 block">مبلغ هزینه (ریال)</label>
                        <input type="text" name="amount" required placeholder="مثال: ۱,۲۰۰,۰۰۰" class="w-full bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 text-xs font-bold outline-none focus:ring-2 focus:ring-rose-500">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-[10px] font-bold text-gray-400 mb-1 block">تاریخ هزینه</label>
                        <input type="text" name="expense_date" required placeholder="۱۴۰۴/۰۱/۱۵" class="w-full bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 text-xs font-bold outline-none focus:ring-2 focus:ring-rose-500">
                    </div>
                    <div>
                        <label class="text-[10px] font-bold text-gray-400 mb-1 block">شماره فیش/پیگیری</label>
                        <input type="text" name="receipt_no" placeholder="مثال: ۸۷۶۵۴۳" class="w-full bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 text-xs font-bold outline-none focus:ring-2 focus:ring-rose-500">
                    </div>
                </div>

                <div>
                    <label class="text-[10px] font-bold text-gray-400 mb-1 block">بابت/شرح هزینه</label>
                    <input type="text" name="description" required placeholder="مثال: خرید کتاب درسی سال جدید" class="w-full bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 text-xs font-bold outline-none focus:ring-2 focus:ring-rose-500">
                </div>

                <div>
                    <label class="text-[10px] font-bold text-gray-400 mb-1 block">یادداشت‌های اضافی</label>
                    <textarea name="notes" rows="2" placeholder="جزئیات بیشتر..." class="w-full bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 text-xs font-bold outline-none focus:ring-2 focus:ring-rose-500"></textarea>
                </div>

                <button type="submit" class="w-full py-4 bg-rose-500 hover:bg-rose-600 text-white font-black rounded-2xl shadow-xl transition-all mt-4">ثبت هزینه در حساب پرونده</button>
            </form>
        </div>
    </div>

</body>
</html>
