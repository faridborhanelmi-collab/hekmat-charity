<?php
session_start();
require_once 'includes/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch Student Data
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$id]);
$person = $stmt->fetch();

if (!$person) {
    die("پرونده مورد نظر یافت نشد.");
}

$psychology_stmt = $pdo->prepare("SELECT * FROM student_psychology WHERE student_id = ?");
$psychology_stmt->execute([$id]);
$psychology = $psychology_stmt->fetch();

// Access Control
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];

// Students can only see their own profile
if ($_SESSION['role'] === 'student' && $_SESSION['related_id'] != $id) {
    die("شما دسترسی به این پرونده را ندارید.");
}

// Fetch Documents
$doc_stmt = $pdo->prepare("SELECT * FROM documents WHERE owner_type = 'student' AND owner_id = ? ORDER BY upload_date DESC");
$doc_stmt->execute([$id]);
$documents = $doc_stmt->fetchAll();

// Fetch Expenses
$exp_stmt = $pdo->prepare("SELECT * FROM expenses WHERE student_id = ? ORDER BY expense_date DESC");
$exp_stmt->execute([$id]);
$expenses = $exp_stmt->fetchAll();

$total_expenses = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE student_id = ?");
$total_expenses->execute([$id]);
$total_expense_sum = $total_expenses->fetchColumn() ?: 0;
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پرونده پشتیبانی <?php echo $person['name']; ?> | بنیاد حکمت</title>
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
        [x-cloak] { display: none !important; }
        .custom-scrollbar::-webkit-scrollbar { width: 5px; }
        .custom-scrollbar::-webkit-scrollbar-track { background: rgba(255,255,255,0.05); border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb { background: rgba(20,184,166,0.3); border-radius: 10px; }
        .custom-scrollbar::-webkit-scrollbar-thumb:hover { background: rgba(20,184,166,0.5); }
    </style>

    <!-- iOS PWA/Homescreen Setup -->
    <link rel="apple-touch-icon" href="logo.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="بنیاد حکمت">
    <link rel="icon" type="image/png" href="logo.png">
    <link rel="manifest" href="manifest.json">
</head>
<body class="bg-gray-50 font-sans text-gray-800 antialiased" x-data="{ showEditModal: false, showDocModal: false, showExpenseModal: false, activeCat: 'all', activeCatLabel: 'همه', expenseForm: {id: '', amount: '', description: '', expense_date: '', receipt_no: '', notes: '', category_id: '1'} }">

    <?php include 'includes/navbar.php'; ?>

    <main class="container mx-auto px-6 py-12 max-w-6xl">
        <!-- Row 1: Top Widgets (Documents & Profile) -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 mb-12">
            
            <!-- (Right) Profile Details -->
            <div class="order-1">
                <div class="bg-white rounded-[3rem] p-10 shadow-xl border border-gray-100 flex flex-col justify-center text-center relative overflow-hidden group h-full">
                    <div class="absolute -left-20 -bottom-20 w-64 h-64 bg-teal-50 rounded-full blur-3xl group-hover:scale-110 transition-transform"></div>
                    
                    <div class="relative w-48 h-48 mx-auto mb-8 group/img cursor-pointer overflow-hidden rounded-[2.8rem] border-4 border-white shadow-2xl">
                        <div class="absolute inset-0 bg-gradient-to-tr from-primary-600 to-primary-400 rotate-3 opacity-20"></div>
                        <img id="profileImg" src="<?php echo $person['photo_path'] ?: 'https://ui-avatars.com/api/?name=' . $person['name'] . '&background=14b8a6&color=fff&size=200'; ?>" 
                             class="relative w-full h-full object-cover">
                        
                        <?php if ($is_admin): ?>
                        <div class="absolute inset-0 bg-black/40 text-white flex flex-col items-center justify-center opacity-0 group-hover/img:opacity-100 transition-opacity">
                            <span class="text-3xl mb-1">📸</span>
                            <span class="text-[10px] font-bold text-white">تغییر تصویر</span>
                            <input type="file" @change="uploadPhoto($event)" class="absolute inset-0 opacity-0 cursor-pointer">
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <h1 class="text-3xl font-black text-primary-900 mb-2 leading-tight"><?php echo htmlspecialchars((string)$person['name'] . ' ' . $person['surname']); ?></h1>
                    <p class="text-teal-600 font-bold text-sm tracking-wide mb-8"><?php echo ($person['code'] === 'GENERAL') ? 'مدیریت هزینه‌های عمومی بنیاد' : 'دانش‌آموز بورس حکمت'; ?></p>
                    
                    <div class="grid grid-cols-2 gap-4 pt-10 border-t border-gray-50 mt-auto">
                        <div class="bg-gray-50/80 p-4 rounded-3xl text-right">
                            <span class="text-[9px] text-gray-400 font-bold block mb-1">وضعیت تحصیلی</span>
                            <span class="text-[11px] font-black text-teal-600 flex items-center gap-2">
                                <span class="w-2 h-2 bg-teal-500 rounded-full animate-pulse shadow-[0_0_8px_rgba(20,184,166,0.5)]"></span>
                                <?php 
                                    $statuses = [
                                        'active' => 'تحت پوشش',
                                        'graduated' => 'فارغ‌التحصیل',
                                        'university' => 'دانشجو',
                                        'exited' => 'خروج از بورس'
                                    ];
                                    echo $statuses[$person['status']] ?: 'نامشخص';
                                ?>
                            </span>
                        </div>
                        <?php if ($is_admin): ?>
                        <button @click="showEditModal = true" class="py-4 bg-primary-900 text-white rounded-3xl text-[11px] font-black shadow-xl hover:bg-teal-600 transition-all transform hover:-translate-y-1">ویرایش اطلاعات پایه</button>
                        <?php else: ?>
                        <div class="bg-gray-50/80 p-4 rounded-3xl text-right">
                            <span class="text-[9px] text-gray-400 font-bold block mb-1">کد مددجو</span>
                            <span class="text-[12px] font-black text-primary-900 leading-none">#<?php echo toFarsiDigits($person['code']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- (Left) Documents & Stats Section -->
            <div class="order-2">
                <div class="bg-primary-900 rounded-[3rem] p-10 shadow-xl text-white h-full flex flex-col justify-between border border-white/5 relative overflow-hidden group">
                    <div class="absolute -right-20 -top-20 w-64 h-64 bg-teal-500/10 rounded-full blur-3xl group-hover:scale-110 transition-transform"></div>
                    
                    <div>
                        <h3 class="font-bold mb-8 flex items-center gap-4">
                            <span class="w-12 h-12 bg-white/10 text-teal-400 rounded-2xl flex items-center justify-center text-xl">📊</span>
                            <?php echo ($person['code'] === 'GENERAL') ? 'اسناد و فاکتورهای مربوطه' : 'اسناد و مدارک آموزشی'; ?>
                        </h3>
                        
                        <div class="space-y-4 max-h-80 overflow-y-auto custom-scrollbar pr-2 mb-8">
                            <?php if (empty($documents)): ?>
                            <div class="py-16 border-2 border-dashed border-white/5 rounded-[2rem] text-center text-xs text-white/20 font-bold tracking-widest">
                                مدرکی ثبت نشده است.
                            </div>
                            <?php endif; ?>
                            <?php foreach ($documents as $doc): ?>
                            <div class="bg-white/5 border border-white/10 p-5 rounded-[1.5rem] flex items-center justify-between hover:bg-white/10 transition-all group/item shadow-sm">
                                <div class="flex items-center gap-4">
                                    <span class="text-3xl opacity-70 filter drop-shadow-md"><?php echo strpos($doc['file_name'], '.pdf') !== false ? '📕' : '📄'; ?></span>
                                    <div>
                                        <div class="text-[11px] font-black tracking-tight mb-1"><?php echo mb_strimwidth($doc['file_name'], 0, 25, "..."); ?></div>
                                        <div class="text-[9px] opacity-40 font-bold"><?php echo htmlspecialchars((string)$doc['description']); ?></div>
                                    </div>
                                </div>
                                <div class="flex gap-2">
                                    <a href="<?php echo $doc['file_path']; ?>" target="_blank" class="w-10 h-10 bg-white/10 rounded-xl flex items-center justify-center text-sm hover:bg-teal-500 transition-all shadow-lg active:scale-95">📥</a>
                                    <?php if ($is_admin): ?>
                                    <button @click="deleteDoc(<?php echo $doc['id']; ?>)" class="w-10 h-10 bg-red-500/20 text-red-100 rounded-xl flex items-center justify-center text-sm hover:bg-red-500 transition-all shadow-lg active:scale-95">🗑️</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if ($is_admin): ?>
                        <button @click="showDocModal = true" class="w-full py-4 border-2 border-dashed border-white/10 rounded-[1.5rem] text-[11px] text-white/40 font-black hover:border-teal-500 hover:text-teal-400 hover:bg-teal-500/5 transition-all mb-4">+ بارگذاری مدرک جدید</button>
                        <?php endif; ?>
                    </div>

                    <div class="mt-8 pt-10 border-t border-white/10 flex flex-col items-center">
                        <div class="text-[10px] text-teal-300 font-bold tracking-[0.3em] mb-3 uppercase opacity-70">مجموع هزینه‌کرد برای دانش‌آموز</div>
                        <div class="text-5xl font-black text-white flex items-baseline gap-3 tracking-tighter">
                            <?php echo formatFarsiCurrency($total_expense_sum); ?> 
                            <span class="text-xs text-white/40 font-normal">ریال</span>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Row 2 & 3: Wide Full-width Lists -->
        <div class="space-y-10">
            
            <!-- Identity & Education Info -->
            <div class="bg-white rounded-[3.5rem] p-12 shadow-xl border border-gray-100" <?php echo ($person['code'] === 'GENERAL') ? 'style="display:none;"' : ''; ?>>
                <h3 class="text-xl font-black text-primary-900 mb-10 border-b pb-6 flex items-center gap-4">
                    <span class="w-12 h-12 bg-teal-50 text-teal-600 rounded-2xl flex items-center justify-center text-2xl">📝</span>
                    اطلاعات هویتی و تحصیلی
                </h3>
                
                <div class="grid md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-x-12 gap-y-12 mb-16">
                    <?php 
                    function renderFieldPremium($label, $value, $is_ltr = false) {
                        $isEmpty = empty(trim((string)$value));
                        $displayVal = $isEmpty ? '<span class="text-rose-400 text-[10px] font-bold">ثبت نشده</span>' : toFarsiDigits(htmlspecialchars((string)$value));
                        $classes = $is_ltr ? "dir-ltr text-right" : "";
                        
                        return '
                        <div class="space-y-2.5">
                            <p class="text-[10px] text-gray-400 font-black uppercase tracking-wider">' . $label . '</p>
                            <p class="text-[13px] font-black text-gray-800 leading-tight ' . $classes . '">' . $displayVal . '</p>
                        </div>';
                    }
                    
                    echo renderFieldPremium('نام پدر', $person['father_name']);
                    echo renderFieldPremium('شغل پدر', $person['father_job']);
                    echo renderFieldPremium('نام مادر', $person['mother_name']);
                    echo renderFieldPremium('شغل مادر', $person['mother_job']);
                    echo renderFieldPremium('کد ملی', $person['national_id']);
                    echo renderFieldPremium('محل تولد', $person['birth_place']);
                    echo renderFieldPremium('تاریخ تولد', $person['birthday']);
                    echo renderFieldPremium('پایه تحصیلی', $person['grade']);
                    echo renderFieldPremium('رشته تحصیلی', $person['field_of_study']);
                    echo renderFieldPremium('مدرسه محل تحصیل', $person['school']);
                    echo renderFieldPremium('شماره تماس دانش‌آموز', $person['phone'], true);
                    echo renderFieldPremium('شماره تماس ولی', $person['guardian_phone'], true);
                    echo renderFieldPremium('مشاور تحصیلی', $person['counselor']);
                    echo renderFieldPremium('شماره حساب', $person['account_number'], true);
                    
                    $is_eligible = $person['bursary_eligible'] ?? 1;
                    echo renderFieldPremium('وضعیت بورسیه', $is_eligible ? 'مشمول بورسیه' : 'غیرمشمول');
                    if ($is_eligible) {
                        echo renderFieldPremium('بورسیه پایه', number_format($person['base_bursary'] ?? 20000000) . ' ریال');
                        if (($person['computer_installment'] ?? 0) > 0) {
                            echo renderFieldPremium('قسط کامپیوتر', number_format($person['computer_installment']) . ' ریال');
                        }
                        if (($person['loan_installment'] ?? 0) > 0) {
                            echo renderFieldPremium('قسط وام', number_format($person['loan_installment']) . ' ریال');
                        }
                        if (($person['other_deductions'] ?? 0) > 0) {
                            echo renderFieldPremium('سایر کسورات', number_format($person['other_deductions']) . ' ریال (' . htmlspecialchars((string)$person['deductions_desc']) . ')');
                        }
                        $net_val = ($person['base_bursary'] ?? 20000000) - ($person['computer_installment'] ?? 0) - ($person['loan_installment'] ?? 0) - ($person['other_deductions'] ?? 0);
                        echo renderFieldPremium('خالص پرداختی ماهیانه', number_format($net_val) . ' ریال', true);
                    }
                    ?>

                    <div class="md:col-span-2">
                        <p class="text-[10px] text-gray-400 font-black uppercase tracking-wider mb-2.5">نشانی منزل</p>
                        <p class="text-[13px] font-bold text-gray-800 leading-relaxed max-w-lg"><?php echo htmlspecialchars((string)$person['address']) ?: '<span class="text-rose-400 font-bold text-[10px]">ثبت نشده</span>'; ?></p>
                    </div>
                </div>

                <div class="grid md:grid-cols-2 gap-8 pt-10 border-t border-gray-50">
                    <div class="bg-teal-50/40 p-8 rounded-[2.5rem] border border-teal-100/50 group hover:bg-teal-50/60 transition-colors">
                        <p class="text-[10px] text-teal-600 mb-4 font-black flex items-center gap-3">
                            <span class="text-xl">🎁</span> خدمات و کالاهای اهدایی
                        </p>
                        <p class="text-xs font-black text-gray-700 leading-relaxed"><?php echo htmlspecialchars((string)$person['items_given']) ?: 'موردی ثبت نشده است.'; ?></p>
                    </div>
                    <div class="bg-gray-50 p-8 rounded-[2.5rem] border border-gray-100 group hover:bg-gray-100/50 transition-colors">
                        <p class="text-[10px] text-gray-400 mb-4 font-black flex items-center gap-3 uppercase tracking-wider">
                            <span class="text-xl opacity-40">ℹ️</span> توضیحات تکمیلی مدیر
                        </p>
                        <p class="text-xs font-black text-gray-700 leading-relaxed"><?php echo htmlspecialchars((string)$person['explanations']) ?: 'توضیحاتی ثبت نشده است.'; ?></p>
                    </div>
                </div>
            </div>

            <!-- Expenses History -->
            <div class="bg-white rounded-[3.5rem] p-12 shadow-xl border border-gray-100 overflow-hidden relative">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-12 gap-6 px-1">
                    <h3 class="text-xl font-black text-primary-900 flex items-center gap-4">
                        <span class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center text-2xl shadow-sm">🗓️</span>
                        تاریخچه هزینه‌کرد و واریزی
                    </h3>
                    <div class="flex gap-4">
                        <?php if ($person['code'] === 'GENERAL'): ?>
                        <div x-data="{ open: false }" class="relative">
                            <button @click="open = !open" class="py-4 px-8 bg-indigo-50 text-indigo-600 rounded-2xl text-[11px] font-black shadow-sm hover:bg-indigo-100 transition-all flex items-center gap-2">
                                <span>📁</span> فیلتر سرفصل: <span x-text="activeCatLabel">همه</span>
                            </button>
                            <div x-show="open" @click.away="open = false" class="absolute top-full right-0 mt-2 w-56 bg-white rounded-2xl shadow-2xl border border-gray-100 z-50 p-2 overflow-hidden" x-transition>
                                <button @click="activeCat = 'all'; activeCatLabel = 'همه'; open = false" class="w-full text-right px-4 py-3 text-xs font-bold hover:bg-gray-50 rounded-xl transition-all">همه سرفصل‌ها</button>
                                <?php foreach ($categories as $cat): ?>
                                <button @click="activeCat = '<?php echo $cat['id']; ?>'; activeCatLabel = '<?php echo $cat['name']; ?>'; open = false" class="w-full text-right px-4 py-3 text-xs font-bold hover:bg-gray-50 rounded-xl transition-all">
                                    <?php echo $cat['name']; ?>
                                </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        <?php if ($is_admin): ?>
                        <button @click="expenseForm = {id: '', amount: '', description: '', expense_date: '', receipt_no: '', notes: '', category_id: '1'}; showExpenseModal = true;" class="py-4 px-8 bg-teal-600 text-white rounded-2xl text-[11px] font-black shadow-xl hover:bg-primary-900 transition-all transform hover:-translate-y-1 active:scale-95 flex items-center gap-2">+ ثبت بورس / هزینه جدید</button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-right border-collapse">
                        <thead>
                            <tr class="text-[10px] text-gray-400 font-black uppercase tracking-[0.2em] border-b border-gray-100">
                                <th class="pb-6 font-black w-16 text-center">ردیف</th>
                                <th class="pb-6 font-black"><?php echo ($person['code'] === 'GENERAL') ? 'سرفصل هزینه / بابت' : 'شرح هزینه / عنوان بورس'; ?></th>
                                <th class="pb-6 font-black text-center">مبلغ پرداختی (ریال)</th>
                                <th class="pb-6 font-black text-center">تاریخ تراکنش / فیش</th>
                                <th class="pb-6 font-black text-center w-32">عملیات</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm text-gray-700">
                            <?php if (empty($expenses)): ?>
                            <tr>
                                <td colspan="5" class="py-24 text-center">
                                    <div class="text-6xl mb-6 opacity-10">🎫</div>
                                    <h4 class="text-base font-black text-gray-300">هیچ تراکنش مالی برای این مددجو ثبت نشده است.</h4>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php foreach ($expenses as $index => $ex): ?>
                            <tr class="group hover:bg-gray-50/80 transition-all border-b border-gray-50 last:border-0"
                                x-show="activeCat === 'all' || activeCat == '<?php echo $ex['category_id'] ?? 1; ?>'">
                                <td class="py-6 text-center text-xs text-gray-400 font-black opacity-40"><?php echo toFarsiDigits(count($expenses) - $index); ?></td>
                                <td class="py-6">
                                    <div class="flex items-center gap-3">
                                        <div class="text-sm font-black text-primary-900 mb-1 leading-tight"><?php echo htmlspecialchars((string)$ex['description']) ?: '---'; ?></div>
                                        <?php if ($person['code'] === 'GENERAL' && $is_admin): ?>
                                        <select @change="updateCategory(<?php echo $ex['id']; ?>, $event.target.value)" class="text-[9px] bg-gray-50 border-none rounded-lg px-2 py-1 font-black text-gray-400 focus:ring-0 focus:bg-white transition-all">
                                            <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo $cat['id']; ?>" <?php echo ($ex['category_id'] == $cat['id']) ? 'selected' : ''; ?>><?php echo $cat['name']; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (!empty($ex['notes'])): ?>
                                    <div class="text-[10px] text-gray-400 font-bold leading-relaxed max-w-md"><?php echo htmlspecialchars((string)$ex['notes']); ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="py-6 text-center">
                                    <span class="inline-block py-2 px-6 bg-teal-50 text-teal-700 rounded-2xl font-black text-sm shadow-sm ring-1 ring-teal-600/10" dir="ltr">
                                        <?php echo formatFarsiCurrency($ex['amount']); ?>
                                    </span>
                                </td>
                                <td class="py-6 text-center">
                                    <div class="text-[11px] text-gray-800 font-black mb-1"><?php echo toFarsiDigits(htmlspecialchars((string)$ex['expense_date']) ?: '---'); ?></div>
                                    <div class="text-[10px] text-gray-400 font-mono tracking-tighter opacity-70" dir="ltr"><?php echo toFarsiDigits(htmlspecialchars((string)$ex['receipt_no']) ?: 'پیگیری ندارد'); ?></div>
                                </td>
                                <td class="py-6">
                                    <div class="flex gap-3 justify-center items-center">
                                        <?php if ($is_admin): ?>
                                        <button @click="expenseForm = {id: '<?php echo $ex['id']; ?>', amount: '<?php echo $ex['amount']; ?>', description: '<?php echo htmlspecialchars($ex['description'] ?? '', ENT_QUOTES); ?>', expense_date: '<?php echo $ex['expense_date']; ?>', receipt_no: '<?php echo htmlspecialchars($ex['receipt_no'] ?? '', ENT_QUOTES); ?>', notes: '<?php echo htmlspecialchars($ex['notes'] ?? '', ENT_QUOTES); ?>'}; showExpenseModal = true;" class="w-10 h-10 flex items-center justify-center bg-white border border-gray-100 text-indigo-600 hover:bg-indigo-600 hover:text-white rounded-xl shadow-sm transition-all hover:scale-110 active:scale-95">✏️</button>
                                        <button @click="deleteExpense(<?php echo $ex['id']; ?>)" class="w-10 h-10 flex items-center justify-center bg-white border border-gray-100 text-red-500 hover:bg-red-500 hover:text-white rounded-xl shadow-sm transition-all hover:scale-110 active:scale-95">🗑️</button>
                                        <?php else: ?>
                                        <span class="w-2 h-2 bg-gray-200 rounded-full opacity-0"></span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Psychology Profile -->
            <?php if ($is_admin && $psychology): ?>
            <div class="bg-blue-50/40 rounded-[3.5rem] p-12 shadow-sm border border-blue-100/50 group mb-12">
                <h3 class="text-xl font-black text-blue-900 mb-8 flex items-center gap-4">
                    <span class="w-12 h-12 bg-blue-100 text-blue-600 rounded-2xl flex items-center justify-center text-2xl group-hover:rotate-12 transition-transform shadow-sm">🧠</span>
                    پروفایل روانشناختی و استعدادیابی
                </h3>
                
                <div class="grid md:grid-cols-3 gap-6 mb-8">
                    <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm text-center">
                        <div class="text-xs text-gray-500 font-bold mb-2">تست انگیزه پیشرفت هرمنس</div>
                        <div class="text-3xl font-black text-blue-600 mb-1" dir="ltr"><?php echo htmlspecialchars((string)$psychology['hermans_score']); ?></div>
                        <div class="text-sm font-bold text-gray-700">گرید: <span class="text-blue-500"><?php echo htmlspecialchars((string)$psychology['hermans_grade']); ?></span></div>
                    </div>
                    
                    <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm text-center">
                        <div class="text-xs text-gray-500 font-bold mb-2">وضعیت ریسک روانی SCL-90</div>
                        <?php 
                        $risk = $psychology['scl90_risk'];
                        $riskClass = 'text-green-500';
                        if ($risk === 'Warning') $riskClass = 'text-yellow-500';
                        if ($risk === 'Critical') $riskClass = 'text-red-500';
                        ?>
                        <div class="text-2xl font-black <?php echo $riskClass; ?> mb-1 mt-2" dir="ltr"><?php echo htmlspecialchars((string)$risk); ?></div>
                        <div class="text-xs font-bold text-gray-400 mt-2" dir="ltr">GSI: <?php echo htmlspecialchars((string)$psychology['scl90_gsi']); ?></div>
                    </div>

                    <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm text-center">
                        <div class="text-xs text-gray-500 font-bold mb-2">گرید نهایی ارزیابی</div>
                        <div class="text-4xl font-black text-indigo-600 mb-1 mt-1" dir="ltr"><?php echo htmlspecialchars((string)$psychology['final_grade']); ?></div>
                    </div>
                </div>

                <?php if (!empty($psychology['recommendation'])): ?>
                <div class="bg-white p-6 rounded-3xl border border-gray-100 shadow-sm">
                    <div class="text-sm font-black text-gray-800 mb-2">توصیه و ملاحظات بالینی:</div>
                    <div class="text-sm text-gray-600 leading-relaxed font-bold"><?php echo htmlspecialchars((string)$psychology['recommendation']); ?></div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Admin Observations -->
            <?php if ($is_admin): ?>
            <div class="bg-indigo-50/40 rounded-[3.5rem] p-12 shadow-sm border border-indigo-100/50 group">
                <h3 class="text-xl font-black text-indigo-900 mb-8 flex items-center gap-4">
                    <span class="w-12 h-12 bg-indigo-100 text-indigo-600 rounded-2xl flex items-center justify-center text-2xl group-hover:rotate-12 transition-transform shadow-sm">🗨️</span>
                    یادداشت‌های اختصاصی مدیر
                </h3>
                <textarea id="notesArea" class="w-full h-48 bg-white border border-indigo-100 rounded-[2.5rem] p-10 text-sm font-bold text-gray-700 focus:outline-none focus:ring-4 focus:ring-indigo-100 transition-all placeholder-indigo-300 shadow-inner leading-relaxed" 
                          placeholder="تجربیات، پیشرفت تحصیلی و نکات مهم دانش‌آموز را اینجا ثبت کنید..."><?php echo htmlspecialchars((string)$person['notes']); ?></textarea>
                <div class="mt-8 flex justify-end">
                    <button @click="updateNotes()" class="px-12 py-5 bg-indigo-600 text-white font-black rounded-[1.5rem] shadow-xl hover:bg-indigo-700 transition-all transform hover:-translate-y-1 active:scale-95 flex items-center gap-2">
                        <span>💾</span> بروزرسانی یادداشت‌های مدیریتی
                    </button>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </main>

    <!-- EDIT MODAL -->
    <div x-show="showEditModal" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center bg-primary-900/80 backdrop-blur-sm p-4" x-transition>
        <div @click.away="showEditModal = false" class="bg-white w-full max-w-2xl rounded-[3rem] shadow-2xl p-8 max-h-[90vh] overflow-y-auto custom-scrollbar">
            <div class="flex justify-between items-center mb-8 border-b pb-4">
                <h3 class="text-xl font-black text-primary-900">ویرایش اطلاعات پایه</h3>
                <button @click="showEditModal = false" class="text-gray-400 hover:text-red-500">✕</button>
            </div>
            
            <form id="editStudentForm" class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <input type="hidden" name="id" value="<?php echo $person['id']; ?>">
                <input type="hidden" name="action" value="update_student">
                
                <div>
                    <label class="text-[10px] font-bold text-gray-400 block mb-1 px-2">نام</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars((string)$person['name']); ?>" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-teal-500">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-gray-400 block mb-1 px-2">نام خانوادگی</label>
                    <input type="text" name="surname" value="<?php echo htmlspecialchars((string)$person['surname']); ?>" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-teal-500">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-gray-400 block mb-1 px-2">شماره تماس</label>
                    <input type="text" name="phone" value="<?php echo htmlspecialchars((string)$person['phone']); ?>" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-teal-500">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-gray-400 block mb-1 px-2">کد ملی</label>
                    <input type="text" name="national_id" value="<?php echo htmlspecialchars((string)$person['national_id']); ?>" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-teal-500">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-gray-400 block mb-1 px-2">نام پدر</label>
                    <input type="text" name="father_name" value="<?php echo htmlspecialchars((string)$person['father_name']); ?>" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-teal-500">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-gray-400 block mb-1 px-2">شغل پدر</label>
                    <input type="text" name="father_job" value="<?php echo htmlspecialchars((string)$person['father_job']); ?>" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-teal-500">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-gray-400 block mb-1 px-2">نام مادر</label>
                    <input type="text" name="mother_name" value="<?php echo htmlspecialchars((string)$person['mother_name']); ?>" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-teal-500">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-gray-400 block mb-1 px-2">شغل مادر</label>
                    <input type="text" name="mother_job" value="<?php echo htmlspecialchars((string)$person['mother_job']); ?>" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-teal-500">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-gray-400 block mb-1 px-2">تاریخ تولد</label>
                    <input type="text" name="birthday" value="<?php echo htmlspecialchars((string)$person['birthday']); ?>" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-teal-500">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-gray-400 block mb-1 px-2">محل تولد</label>
                    <input type="text" name="birth_place" value="<?php echo htmlspecialchars((string)$person['birth_place']); ?>" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-teal-500">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-gray-400 block mb-1 px-2">مدرسه</label>
                    <input type="text" name="school" value="<?php echo htmlspecialchars((string)$person['school']); ?>" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-teal-500">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-gray-400 block mb-1 px-2">پایه تحصیلی</label>
                    <input type="text" name="grade" value="<?php echo htmlspecialchars((string)$person['grade']); ?>" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-teal-500">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-gray-400 block mb-1 px-2">رشته تحصیلی</label>
                    <input type="text" name="field_of_study" value="<?php echo htmlspecialchars((string)$person['field_of_study']); ?>" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-teal-500">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-gray-400 block mb-1 px-2">مشاور تحصیلی</label>
                    <input type="text" name="counselor" value="<?php echo htmlspecialchars((string)$person['counselor']); ?>" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-teal-500">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-gray-400 block mb-1 px-2">شماره تماس ولی</label>
                    <input type="text" name="guardian_phone" value="<?php echo htmlspecialchars((string)$person['guardian_phone']); ?>" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-teal-500">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-gray-400 block mb-1 px-2">شماره حساب</label>
                    <input type="text" name="account_number" value="<?php echo htmlspecialchars((string)$person['account_number']); ?>" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-teal-500">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-gray-400 block mb-1 px-2">وضعیت تحصیلی</label>
                    <select name="status" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-teal-500">
                        <option value="active" <?php echo $person['status'] == 'active' ? 'selected' : ''; ?>>تحت پوشش (فعال)</option>
                        <option value="graduated" <?php echo $person['status'] == 'graduated' ? 'selected' : ''; ?>>فارغ‌التحصیل</option>
                        <option value="university" <?php echo $person['status'] == 'university' ? 'selected' : ''; ?>>دانشجو (تحصیلات عالی)</option>
                        <option value="exited" <?php echo $person['status'] == 'exited' ? 'selected' : ''; ?>>خروج از بورس</option>
                    </select>
                </div>
                
                <div class="md:col-span-2 border-t border-gray-100 pt-6 mt-4">
                    <h4 class="text-xs font-black text-primary-900 mb-2 flex items-center gap-2">
                        <span>💰</span> تنظیمات بورسیه ماهیانه و کسورات
                    </h4>
                </div>
                <div class="flex items-center gap-3 pt-6">
                    <input type="checkbox" name="bursary_eligible" value="1" id="bursary_eligible" <?php echo ($person['bursary_eligible'] ?? 1) ? 'checked' : ''; ?> class="rounded border-gray-300 text-teal-600 focus:ring-teal-500 w-5 h-5 cursor-pointer">
                    <label for="bursary_eligible" class="text-xs font-bold text-gray-700 cursor-pointer">مشمول بورسیه ماهیانه</label>
                </div>
                <div>
                    <label class="text-[10px] font-bold text-gray-400 block mb-1 px-2">مبلغ بورسیه پایه (ریال)</label>
                    <input type="number" name="base_bursary" value="<?php echo htmlspecialchars((string)($person['base_bursary'] ?? 20000000)); ?>" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-teal-500">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-gray-400 block mb-1 px-2">قسط کامپیوتر (ریال)</label>
                    <input type="number" name="computer_installment" value="<?php echo htmlspecialchars((string)($person['computer_installment'] ?? 0)); ?>" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-teal-500">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-gray-400 block mb-1 px-2">قسط وام (ریال)</label>
                    <input type="number" name="loan_installment" value="<?php echo htmlspecialchars((string)($person['loan_installment'] ?? 0)); ?>" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-teal-500">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-gray-400 block mb-1 px-2">سایر کسورات (ریال)</label>
                    <input type="number" name="other_deductions" value="<?php echo htmlspecialchars((string)($person['other_deductions'] ?? 0)); ?>" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-teal-500">
                </div>
                <div class="md:col-span-2">
                    <label class="text-[10px] font-bold text-gray-400 block mb-1 px-2">بابت/شرح سایر کسورات</label>
                    <input type="text" name="deductions_desc" value="<?php echo htmlspecialchars((string)($person['deductions_desc'] ?? '')); ?>" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-teal-500">
                </div>
                <div class="md:col-span-2">
                    <label class="text-[10px] font-bold text-gray-400 block mb-1 px-2">نشانی منزل</label>
                    <textarea name="address" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-teal-500 h-24"><?php echo htmlspecialchars((string)$person['address']); ?></textarea>
                </div>
                <div class="md:col-span-2">
                    <label class="text-[10px] font-bold text-gray-400 block mb-1 px-2">خدمات و کالاهای اهدایی</label>
                    <textarea name="items_given" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-teal-500 h-24"><?php echo htmlspecialchars((string)$person['items_given']); ?></textarea>
                </div>
                <div class="md:col-span-2">
                    <label class="text-[10px] font-bold text-gray-400 block mb-1 px-2">توضیحات تکمیلی</label>
                    <textarea name="explanations" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-teal-500 h-24"><?php echo htmlspecialchars((string)$person['explanations']); ?></textarea>
                </div>
                
                <div class="md:col-span-2">
                    <button type="button" @click="saveProfile()" class="w-full py-4 bg-teal-600 text-white rounded-2xl font-black shadow-xl hover:bg-teal-700 transition-all">ذخیره تغییرات</button>
                </div>
            </form>
        </div>
    </div>

    <!-- DOC MODAL -->
    <div x-show="showDocModal" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center bg-primary-900/80 backdrop-blur-sm p-4" x-transition>
        <div @click.away="showDocModal = false" class="bg-white w-full max-w-lg rounded-[3rem] shadow-2xl p-10">
            <div class="flex justify-between items-center mb-8 border-b pb-4">
                <h3 class="text-xl font-black text-primary-900">بارگذاری مدرک جدید</h3>
                <button @click="showDocModal = false" class="text-gray-400 hover:text-red-500">✕</button>
            </div>
            
            <form id="uploadDocForm" class="space-y-6">
                <input type="hidden" name="action" value="upload_document">
                <input type="hidden" name="owner_type" value="student">
                <input type="hidden" name="owner_id" value="<?php echo $person['id']; ?>">
                
                <div>
                    <label class="text-[10px] font-bold text-gray-400 block mb-1 px-2">انتخاب فایل (PDF یا تصویر)</label>
                    <input type="file" name="document" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-teal-500">
                </div>
                <div class="md:col-span-2">
                    <label class="text-[10px] font-bold text-gray-400 block mb-1 px-2">توضیح کوتاه</label>
                    <input type="text" name="description" placeholder="مثلاً: کارنامه ترم اول" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-teal-500">
                </div>
                
                <button type="button" @click="saveDoc()" class="w-full py-4 bg-indigo-600 text-white rounded-2xl font-black shadow-xl hover:bg-indigo-700 transition-all">بارگذاری اسناد</button>
            </form>
        </div>
    </div>

    <!-- EXPENSE MODAL -->
    <div x-show="showExpenseModal" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center bg-primary-900/80 backdrop-blur-sm p-4" x-transition>
        <div @click.away="showExpenseModal = false" class="bg-white w-full max-w-lg rounded-[3rem] shadow-2xl p-8 max-h-[90vh] overflow-y-auto custom-scrollbar">
            <h3 class="text-xl font-black text-primary-900 mb-8 border-b pb-4" x-text="expenseForm.id ? 'ویرایش هزینه‌کرد' : 'ثبت هزینه جدید'"></h3>
            <form id="expenseFormElement" class="space-y-6">
                <input type="hidden" name="action" :value="expenseForm.id ? 'edit_expense' : 'add_expense'">
                <input type="hidden" name="id" :value="expenseForm.id">
                <input type="hidden" name="student_id" value="<?php echo $person['id']; ?>">
                
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="text-[10px] font-bold text-gray-400 block mb-1 px-2">مبلغ (ریال)</label>
                        <input type="number" name="amount" x-model="expenseForm.amount" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm font-bold text-left focus:ring-2 focus:ring-teal-500" dir="ltr">
                    </div>
                    <div>
                        <label class="text-[10px] font-bold text-gray-400 block mb-1 px-2">تاریخ هزینه</label>
                        <input type="text" name="expense_date" x-model="expenseForm.expense_date" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm text-left focus:ring-2 focus:ring-teal-500" dir="ltr" placeholder="1403/xx/xx">
                    </div>
                </div>
                
                <div>
                    <label class="text-[10px] font-bold text-gray-400 block mb-1 px-2">شرح هزینه</label>
                    <input type="text" name="description" x-model="expenseForm.description" placeholder="مانند: پرداخت شهریه مدرسه..." class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-teal-500">
                </div>

                <div>
                    <label class="text-[10px] font-bold text-gray-400 block mb-1 px-2">شماره فیش / پیگیری</label>
                    <input type="text" name="receipt_no" x-model="expenseForm.receipt_no" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm text-left focus:ring-2 focus:ring-teal-500" dir="ltr">
                </div>
                
                <div>
                    <label class="text-[10px] font-bold text-gray-400 block mb-1 px-2">توضیحات تکمیلی</label>
                    <textarea name="notes" x-model="expenseForm.notes" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm h-24 focus:ring-2 focus:ring-teal-500"></textarea>
                </div>

                <div>
                    <label class="text-[10px] font-bold text-gray-400 block mb-1 px-2">سرفصل هزینه</label>
                    <select name="category_id" x-model="expenseForm.category_id" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm font-bold focus:ring-2 focus:ring-teal-500">
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?php echo $cat['id']; ?>"><?php echo $cat['name']; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <button type="button" @click="saveExpense()" class="w-full py-4 bg-teal-600 text-white rounded-2xl font-black shadow-xl hover:bg-teal-700 transition-all" x-text="expenseForm.id ? 'ذخیره تغییرات' : 'ثبت هزینه'"></button>
            </form>
        </div>
    </div>

    <script>
        async function uploadPhoto(e) {
            if (!e.target.files[0]) return;
            const formData = new FormData();
            formData.append('photo', e.target.files[0]);
            formData.append('owner_type', 'student');
            formData.append('owner_id', '<?php echo $person['id']; ?>');
            formData.append('action', 'upload_photo');

            const res = await fetch('admin-request-handler.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                document.getElementById('profileImg').src = data.path + '?' + Date.now();
            } else {
                alert(data.message || 'خطا در بارگذاری تصویر');
            }
        }

        async function saveProfile() {
            try {
                const form = document.getElementById('editStudentForm');
                const formData = new FormData(form);
                const notesArea = document.getElementById('notesArea');
                if (notesArea) {
                    formData.set('notes', notesArea.value);
                }
                const res = await fetch('admin-request-handler.php', { method: 'POST', body: formData });
                const text = await res.text();
                try {
                    const data = JSON.parse(text);
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message || 'خطا در ذخیره‌سازی');
                    }
                } catch (e) {
                    console.error("Server returned non-JSON response:", text);
                    alert('خطای سمت سرور رخ داد. لطفا کنسول مرورگر را بررسی کنید.');
                }
            } catch (err) {
                console.error("Fetch error:", err);
                alert('خطا در برقراری ارتباط با سرور.');
            }
        }

        async function saveDoc() {
            const form = document.getElementById('uploadDocForm');
            const res = await fetch('admin-request-handler.php', { method: 'POST', body: new FormData(form) });
            const data = await res.json();
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'خطا در بارگذاری');
            }
        }

        async function deleteDoc(id) {
            if (!confirm('آیا از حذف این فایل بازگشت ناپذیر اطمینان دارید؟')) return;
            const formData = new FormData();
            formData.append('id', id);
            formData.append('action', 'delete_document');
            const res = await fetch('admin-request-handler.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) location.reload();
        }

        async function updateNotes() {
            const notes = document.getElementById('notesArea').value;
            const formData = new FormData();
            formData.append('id', '<?php echo $person['id']; ?>');
            formData.append('notes', notes);
            formData.append('action', 'update_student_notes'); // New specific action for notes
            
            const res = await fetch('admin-request-handler.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                alert('یادداشت با موفقیت بروز شد.');
            } else {
                alert('خطا در بروزرسانی یادداشت');
            }
        }

        async function saveExpense() {
            const form = document.getElementById('expenseFormElement');
            const res = await fetch('admin-request-handler.php', { method: 'POST', body: new FormData(form) });
            const data = await res.json();
            if (data.success) {
                location.reload();
            } else {
                alert(data.message || 'خطا در ثبت هزینه');
            }
        }

        async function deleteExpense(id) {
            if (!confirm('آیا از حذف این هزینه‌کرد اطمینان دارید؟')) return;
            const formData = new FormData();
            formData.append('id', id);
            formData.append('action', 'delete_expense');
            const res = await fetch('admin-request-handler.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) location.reload();
        }

        async function updateCategory(id, category_id) {
            const formData = new FormData();
            formData.append('id', id);
            formData.append('category_id', category_id);
            formData.append('action', 'update_expense_category');
            
            const res = await fetch('admin-request-handler.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (!data.success) alert('خطا در بروزرسانی سرفصل');
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
