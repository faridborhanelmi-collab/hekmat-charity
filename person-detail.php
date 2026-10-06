<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';

// ۱. الزام لاگین و کنترل دسترسی نقشی در ابتدای پردازش
require_role(['superadmin', 'secretary', 'education_deputy', 'board_member', 'admin', 'student']);

// مسدودسازی دسترسی اپراتور دیتا (ویانا وحیدی) به پرونده‌های انفرادی و هدایت خودکار به مخزن کتابخانه
if (($_SESSION['role'] ?? '') === 'data_operator') {
    header("Location: admin/library.php");
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// ۲. جلوگیری از آسیب‌پذیری IDOR: دانش‌آموزان منحصراً فقط به پرونده شخصی خود دسترسی دارند
if (($_SESSION['role'] ?? '') === 'student') {
    $my_student_id = (int)($_SESSION['related_id'] ?? ($_SESSION['user_id'] ?? 0));
    if ($id <= 0 || $id !== $my_student_id) {
        $id = $my_student_id;
    }
}

if ($id <= 0) {
    die("شناسه پرونده نامعتبر است.");
}

// Fetch Student Data
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$id]);
$person = $stmt->fetch();

if (!$person) {
    die("پرونده مورد نظر یافت نشد.");
}

$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];
$can_finance = can_access_financial();
$can_psychology = can_view_psychology();
$can_admin_notes = can_view_admin_notes();
$is_read_only = is_read_only();

// پرونده روانشناسی فقط و فقط برای مدیرعامل (فرید علمی) لود می‌شود
$psychology = null;
if ($can_psychology) {
    $psychology_stmt = $pdo->prepare("SELECT * FROM student_psychology WHERE student_id = ?");
    $psychology_stmt->execute([$id]);
    $psychology = $psychology_stmt->fetch();
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

// Fetch Book Requests
$book_stmt = $pdo->prepare("SELECT * FROM student_book_requests WHERE student_id = ? ORDER BY id DESC");
$book_stmt->execute([$id]);
$student_books = $book_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Active Sponsorships (Donors linked to this student)
$spon_stmt = $pdo->prepare("
    SELECT s.id as spon_id, s.shares_count, s.start_date, d.id as donor_id, d.name as donor_name, d.surname as donor_surname, d.phone as donor_phone
    FROM sponsorships s
    JOIN donors d ON s.donor_id = d.id
    WHERE s.student_id = ? AND s.status = 'active'
    ORDER BY s.id DESC
");
$spon_stmt->execute([$id]);
$student_sponsors = $spon_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پرونده پشتیبانی <?php echo $person['name']; ?> | بنیاد حکمت</title>
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
<body class="bg-gray-50 font-sans text-gray-800 antialiased" x-data="{ 
    showEditModal: false, 
    showDocModal: false, 
    showExpenseModal: false, 
    activeCat: 'all', 
    activeCatLabel: 'همه', 
    expenseForm: {id: '', amount: '', description: '', expense_date: '', receipt_no: '', notes: '', category_id: '1'},
    baseBursary: <?php echo (int)($person['base_bursary'] ?? 20000000); ?>,
    computerInstallment: <?php echo (int)($person['computer_installment'] ?? 0); ?>,
    loanInstallment: <?php echo (int)($person['loan_installment'] ?? 0); ?>,
    otherDeductions: <?php echo (int)($person['other_deductions'] ?? 0); ?>,
    formatMoney(val) {
        if (!val && val !== 0) return '۰';
        const num = parseInt(String(val).replace(/[^0-9]/g, '')) || 0;
        return num.toLocaleString();
    },
    toTomans(val) {
        const num = parseInt(String(val).replace(/[^0-9]/g, '')) || 0;
        return Math.round(num / 10).toLocaleString();
    },
    calcNetBursary() {
        const b = parseInt(String(this.baseBursary).replace(/[^0-9]/g, '')) || 0;
        const c = parseInt(String(this.computerInstallment).replace(/[^0-9]/g, '')) || 0;
        const l = parseInt(String(this.loanInstallment).replace(/[^0-9]/g, '')) || 0;
        const o = parseInt(String(this.otherDeductions).replace(/[^0-9]/g, '')) || 0;
        return b - c - l - o;
    }
}">

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
                    <?php
                    $title_subtitle = 'دانش‌آموز تحت پوشش حکمت';
                    if ($person['code'] === 'GENERAL') {
                        $title_subtitle = 'مدیریت هزینه‌های عمومی بنیاد';
                    } elseif (($person['status'] ?? '') === 'exited') {
                        $title_subtitle = 'مددجو (خروج از بورس بنیاد)';
                    } elseif (($person['status'] ?? '') === 'graduated') {
                        $title_subtitle = 'فارغ‌التحصیل آکادمی حکمت';
                    } elseif (($person['status'] ?? '') === 'university') {
                        $title_subtitle = 'دانشجوی تحت پوشش حکمت';
                    }
                    ?>
                    <p class="<?php echo (($person['status'] ?? '') === 'exited') ? 'text-rose-500 font-black' : 'text-teal-600 font-bold'; ?> text-sm tracking-wide mb-8"><?php echo $title_subtitle; ?></p>
                    
                    <div class="grid grid-cols-2 gap-4 pt-10 border-t border-gray-50 mt-auto">
                        <div class="bg-gray-50/80 p-4 rounded-3xl text-right">
                            <span class="text-[9px] text-gray-400 font-bold block mb-1">وضعیت پرونده</span>
                            <?php 
                                $statuses_map = [
                                    'active' => ['label' => 'تحت پوشش (فعال)', 'color' => 'text-teal-600', 'dot' => 'bg-teal-500 animate-pulse'],
                                    'university' => ['label' => 'دانشجو (آموزش عالی)', 'color' => 'text-blue-600', 'dot' => 'bg-blue-500'],
                                    'graduated' => ['label' => 'فارغ‌التحصیل', 'color' => 'text-purple-600', 'dot' => 'bg-purple-500'],
                                    'exited' => ['label' => 'خروج از بورس (غیرفعال)', 'color' => 'text-rose-600', 'dot' => 'bg-rose-500']
                                ];
                                $cur_st = $statuses_map[$person['status'] ?? 'active'] ?? ['label' => 'نامشخص', 'color' => 'text-gray-500', 'dot' => 'bg-gray-400'];
                            ?>
                            <span class="text-[11px] font-black <?php echo $cur_st['color']; ?> flex items-center gap-2">
                                <span class="w-2 h-2 rounded-full <?php echo $cur_st['dot']; ?>"></span>
                                <?php echo $cur_st['label']; ?>
                            </span>
                        </div>
                        <?php if ($is_admin && !$is_read_only): ?>
                        <button @click="showEditModal = true" class="py-4 bg-primary-900 text-white rounded-3xl text-[11px] font-black shadow-xl hover:bg-teal-600 transition-all transform hover:-translate-y-1">ویرایش اطلاعات پایه</button>
                        <?php elseif ($is_read_only): ?>
                        <div class="bg-amber-50/80 p-4 rounded-3xl text-right border border-amber-200/80">
                            <span class="text-[9px] text-amber-700 font-bold block mb-1">دسترسی هیئت مدیره</span>
                            <span class="text-[11px] font-black text-amber-900 leading-none">👁️ فقط خواندنی (ناظر)</span>
                        </div>
                        <?php else: ?>
                        <div class="bg-gray-50/80 p-4 rounded-3xl text-right">
                            <span class="text-[9px] text-gray-400 font-bold block mb-1">کد مددجو</span>
                            <span class="text-[12px] font-black text-primary-900 leading-none">#<?php echo toFarsiDigits($person['code']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($is_admin && $person['code'] !== 'GENERAL'): ?>
                    <div class="mt-4 pt-2">
                        <a href="student-dashboard.php?student_id=<?php echo $person['id']; ?>" target="_blank" 
                           class="w-full py-3.5 bg-gradient-to-r from-teal-600 to-indigo-700 hover:from-teal-500 hover:to-indigo-600 text-white rounded-2xl text-xs font-black shadow-lg transition-all flex items-center justify-center gap-2 transform hover:-translate-y-0.5">
                            <span>👁️</span> مشاهده پورتال دانش‌آموز (ورود نظارتی مدیر)
                        </a>
                    </div>
                    <?php endif; ?>
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

                    <?php if ($can_finance): ?>
                    <div class="mt-8 pt-10 border-t border-white/10 flex flex-col items-center">
                        <div class="text-[10px] text-teal-300 font-bold tracking-[0.3em] mb-3 uppercase opacity-70">مجموع هزینه‌کرد برای دانش‌آموز</div>
                        <div class="text-5xl font-black text-white flex items-baseline gap-3 tracking-tighter">
                            <?php echo formatFarsiCurrency($total_expense_sum); ?> 
                            <span class="text-xs text-white/40 font-normal">ریال</span>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="mt-8 pt-10 border-t border-white/10 flex flex-col items-center text-center">
                        <div class="text-[10px] text-teal-300 font-bold tracking-[0.3em] mb-2 uppercase opacity-70">وضعیت تحصیلی دانش‌آموز</div>
                        <div class="text-xl font-black text-white">
                            <?php echo htmlspecialchars($person['grade'] . ' - ' . ($person['field_of_study'] ?: 'عمومی')); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- Sponsorship & Donor Linking Hub -->
        <?php if ($person['code'] !== 'GENERAL'): ?>
        <div class="bg-white rounded-[3rem] p-8 md:p-10 shadow-xl border border-gray-100 mb-10">
            <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6 pb-4 border-b border-gray-100">
                <div>
                    <h3 class="text-xl font-black text-primary-900 flex items-center gap-3">
                        <span class="w-10 h-10 bg-teal-50 text-teal-600 rounded-2xl flex items-center justify-center text-lg">🤝</span>
                        حامیان بورس و پشتیبانان مالی دانش‌آموز
                    </h3>
                    <div class="flex flex-wrap items-center gap-2 mt-1">
                        <span class="text-xs text-gray-500 font-bold">نام در دید خیر (محرمانه):</span>
                        <span class="text-xs font-black text-teal-700 bg-teal-50 px-2.5 py-0.5 rounded-lg border border-teal-200">
                            <?php echo htmlspecialchars($person['alias_name'] ?: 'حکمت‌جو #' . $person['id']); ?>
                        </span>
                        <span class="text-[10px] text-gray-400">(هویت واقعی، کد ملی، تلفن و آدرس به هیچ خیری نمایش داده نمی‌شود)</span>
                    </div>
                </div>
                <?php if ($is_admin && !$is_read_only): ?>
                <div class="flex items-center gap-2">
                    <a href="admin/sponsorships.php?student_id=<?php echo $person['id']; ?>" class="py-2.5 px-4 bg-teal-600 hover:bg-teal-500 text-white rounded-xl text-xs font-black shadow-md transition-all flex items-center gap-1.5 shrink-0">
                        <span>+</span> پیوند حامی جدید به این دانش‌آموز
                    </a>
                </div>
                <?php endif; ?>
            </div>

            <?php if (empty($student_sponsors)): ?>
                <div class="p-8 border-2 border-dashed border-gray-100 rounded-3xl text-center text-xs text-gray-400 font-bold">
                    در حال حاضر هیچ حامی بورسیه‌ای برای این دانش‌آموز ثبت نشده است.
                    <?php if ($is_admin && !$is_read_only): ?>
                    <div class="mt-3">
                        <a href="admin/sponsorships.php?student_id=<?php echo $person['id']; ?>" class="text-teal-600 underline hover:text-teal-700">کلیک کنید تا یک خیر را به عنوان حامی این دانش‌پژوه متصل نمایید.</a>
                    </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <?php foreach ($student_sponsors as $spon): ?>
                    <div class="bg-gray-50 border border-gray-100 p-5 rounded-3xl flex items-center justify-between hover:bg-teal-50/30 transition-all">
                        <div class="flex items-center gap-3">
                            <div class="w-12 h-12 rounded-2xl bg-teal-500/10 text-teal-600 flex items-center justify-center text-xl font-black">
                                💎
                            </div>
                            <div>
                                <a href="donor-detail.php?id=<?php echo $spon['donor_id']; ?>" class="text-sm font-black text-gray-900 hover:text-teal-600 block">
                                    <?php echo htmlspecialchars($spon['donor_name'] . ' ' . $spon['donor_surname']); ?>
                                </a>
                                <span class="text-[10px] text-gray-400 block mt-0.5">شروع: <?php echo toFarsiDigits($spon['start_date'] ?: '---'); ?></span>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] bg-teal-100 text-teal-700 px-2.5 py-1 rounded-xl font-bold"><?php echo toFarsiDigits($spon['shares_count']); ?> سهم بورس</span>
                            <a href="donor-dashboard.php?donor_id=<?php echo $spon['donor_id']; ?>" target="_blank" title="مشاهده پورتال این خیر" class="w-8 h-8 rounded-xl bg-white border border-gray-200 flex items-center justify-center text-xs hover:bg-teal-600 hover:text-white transition-all shadow-sm">
                                👁️
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

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
                    
                    $gender_label = ($person['gender'] === 'Female') ? 'دختر' : (($person['gender'] === 'Male') ? 'پسر' : '');
                    if (!empty($gender_label)) {
                        echo renderFieldPremium('جنسیت', $gender_label);
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
                    if (!empty($person['language_class'])) {
                        echo renderFieldPremium('کلاس زبان', '<span class="text-teal-700 bg-teal-50 px-2.5 py-1 rounded-xl border border-teal-200 font-black inline-block">' . htmlspecialchars((string)$person['language_class']) . '</span>');
                    }
                    if ($can_finance) {
                        echo renderFieldPremium('شماره حساب', $person['account_number'], true);
                        
                        $is_status_active = in_array($person['status'] ?? 'active', ['active', 'university']);
                        $is_eligible = $is_status_active && ((int)($person['bursary_eligible'] ?? 0) === 1);
                        
                        if (($person['status'] ?? '') === 'exited') {
                            echo renderFieldPremium('وضعیت بورسیه', '<span class="text-rose-600 font-black">غیرفعال (خروج از بورس)</span>');
                        } elseif (($person['status'] ?? '') === 'graduated') {
                            echo renderFieldPremium('وضعیت بورسیه', '<span class="text-purple-600 font-black">غیرفعال (فارغ‌التحصیل)</span>');
                        } elseif (!$is_status_active) {
                            echo renderFieldPremium('وضعیت بورسیه', '<span class="text-gray-500 font-black">غیرمشمول</span>');
                        } else {
                            echo renderFieldPremium('وضعیت بورسیه', $is_eligible ? '<span class="text-teal-600 font-black">مشمول بورسیه ماهیانه</span>' : '<span class="text-amber-600 font-black">غیرمشمول بورسیه نقدی (خدمات آموزشی)</span>');
                        }

                        if ($is_eligible) {
                            $base_b = (int)($person['base_bursary'] ?? 20000000);
                            $comp_i = (int)($person['computer_installment'] ?? 0);
                            $loan_i = (int)($person['loan_installment'] ?? 0);
                            $othr_d = (int)($person['other_deductions'] ?? 0);
                            $net_v = $base_b - $comp_i - $loan_i - $othr_d;
                            
                            echo renderFieldPremium('مبلغ بورسیه پایه', toFarsiDigits(number_format($base_b)) . ' ریال (' . toFarsiDigits(number_format(round($base_b / 10))) . ' تومان)');
                            if ($comp_i > 0) {
                                echo renderFieldPremium('قسط کامپیوتر', toFarsiDigits(number_format($comp_i)) . ' ریال (' . toFarsiDigits(number_format(round($comp_i / 10))) . ' تومان)');
                            }
                            if ($loan_i > 0) {
                                echo renderFieldPremium('قسط وام', toFarsiDigits(number_format($loan_i)) . ' ریال (' . toFarsiDigits(number_format(round($loan_i / 10))) . ' تومان)');
                            }
                            if ($othr_d > 0) {
                                echo renderFieldPremium('سایر کسورات', toFarsiDigits(number_format($othr_d)) . ' ریال (' . htmlspecialchars((string)$person['deductions_desc']) . ')');
                            }
                            echo renderFieldPremium('خالص پرداختی ماهیانه', toFarsiDigits(number_format($net_v)) . ' ریال (' . toFarsiDigits(number_format(round($net_v / 10))) . ' تومان)');
                        }
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
            <?php if ($can_finance): ?>
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
            <?php endif; ?>

            <!-- Psychology Profile (Confidential - Superadmin Only) -->
            <?php if ($can_psychology && $psychology): ?>
            <div class="bg-gradient-to-br from-slate-900 via-primary-900 to-teal-950 text-white rounded-[3.5rem] p-8 md:p-12 shadow-xl border border-teal-500/20 mb-12 relative overflow-hidden" x-data="{ showSclDetails: false }">
                <div class="absolute -right-20 -top-20 w-72 h-72 bg-teal-500/10 rounded-full blur-3xl pointer-events-none"></div>
                
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8 border-b border-white/10 pb-6 relative z-10">
                    <div class="flex items-center gap-4">
                        <div class="w-14 h-14 bg-teal-500/20 border border-teal-400/30 rounded-2xl flex items-center justify-center text-3xl shadow-inner shrink-0">🧠</div>
                        <div>
                            <div class="inline-flex items-center gap-2 bg-amber-500/20 text-amber-300 border border-amber-500/30 px-3 py-0.5 rounded-full text-[10px] font-black tracking-wide mb-1">
                                🔒 محرمانه پزشکی / روانی | منحصراً دسترسی مدیرعامل
                            </div>
                            <h3 class="text-xl md:text-2xl font-black text-white">پروفایل جامع روانشناختی و استعدادیابی</h3>
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <a href="interview-dossier.php?student_id=<?php echo $person['id']; ?>" target="_blank" class="text-xs text-white bg-gradient-to-r from-teal-500 to-emerald-600 hover:from-teal-400 hover:to-emerald-500 px-4 py-2 rounded-2xl border border-teal-400/30 transition flex items-center gap-1.5 font-bold shadow-lg shadow-teal-500/20">
                            <span>📋</span>
                            <span>پرونده تحلیلی و رادار چارت (A4)</span>
                        </a>
                        <a href="admin-interview-tests.php" class="text-xs text-teal-300 hover:text-white bg-teal-500/20 hover:bg-teal-500/40 px-3.5 py-2 rounded-2xl border border-teal-500/30 transition flex items-center gap-1.5 font-bold shadow-sm">
                            <span>📱</span>
                            <span>سامانه آزمون مصاحبه</span>
                        </a>
                        <span class="text-xs text-teal-300 font-bold bg-white/5 px-4 py-2 rounded-2xl border border-white/10">
                            گرید نهایی ارزیابی: <strong class="text-white text-base mr-1"><?php echo htmlspecialchars((string)($psychology['final_grade'] ?: '---')); ?></strong>
                        </span>
                    </div>
                </div>

                <!-- 4 Main Pillar Scores -->
                <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-8 relative z-10">
                    
                    <!-- 1. Hermans Achievement Motivation -->
                    <div class="bg-white/5 backdrop-blur-md p-6 rounded-3xl border border-white/10 text-center hover:bg-white/10 transition-colors">
                        <div class="text-[11px] text-teal-300 font-bold mb-1">🎯 انگیزه پیشرفت هرمنس</div>
                        <div class="text-3xl md:text-4xl font-black text-white my-2 font-mono" dir="ltr">
                            <?php echo $psychology['hermans_score'] !== null ? htmlspecialchars((string)$psychology['hermans_score']) : '---'; ?>
                        </div>
                        <div class="text-xs font-bold text-white/70">
                            گرید: <span class="text-amber-400 font-black text-sm"><?php echo htmlspecialchars((string)($psychology['hermans_grade'] ?: '---')); ?></span>
                        </div>
                    </div>

                    <!-- 2. Multiple Intelligences (MI) -->
                    <div class="bg-white/5 backdrop-blur-md p-6 rounded-3xl border border-white/10 text-center hover:bg-white/10 transition-colors">
                        <div class="text-[11px] text-teal-300 font-bold mb-1">💡 هوش‌های چندگانه گاردنر (MI)</div>
                        <div class="text-3xl md:text-4xl font-black text-white my-2 font-mono" dir="ltr">
                            <?php echo $psychology['mi_total'] !== null ? htmlspecialchars((string)$psychology['mi_total']) : '---'; ?>
                        </div>
                        <div class="text-xs font-bold text-white/70">
                            گرید: <span class="text-cyan-400 font-black text-sm"><?php echo htmlspecialchars((string)($psychology['mi_grade'] ?: '---')); ?></span>
                        </div>
                    </div>

                    <!-- 3. Emotional Intelligence (EQ) -->
                    <div class="bg-white/5 backdrop-blur-md p-6 rounded-3xl border border-white/10 text-center hover:bg-white/10 transition-colors">
                        <div class="text-[11px] text-teal-300 font-bold mb-1">❤️ هوش هیجانی بار-آن (EQ)</div>
                        <div class="text-3xl md:text-4xl font-black text-white my-2 font-mono" dir="ltr">
                            <?php echo $psychology['eq_total'] !== null ? htmlspecialchars((string)$psychology['eq_total']) : '---'; ?>
                        </div>
                        <div class="text-xs font-bold text-white/70">
                            گرید: <span class="text-emerald-400 font-black text-sm"><?php echo htmlspecialchars((string)($psychology['eq_grade'] ?: '---')); ?></span>
                        </div>
                    </div>

                    <!-- 4. Mental Health Risk (SCL-90) -->
                    <?php 
                    $risk = $psychology['scl90_risk'] ?: 'Normal';
                    $riskBadgeClass = 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30';
                    $riskIcon = '✅';
                    if ($risk === 'Warning') {
                        $riskBadgeClass = 'bg-amber-500/20 text-amber-300 border-amber-500/30';
                        $riskIcon = '⚠️';
                    } elseif ($risk === 'Critical') {
                        $riskBadgeClass = 'bg-rose-500/25 text-rose-300 border-rose-500/40';
                        $riskIcon = '🚨';
                    }
                    ?>
                    <div class="bg-white/5 backdrop-blur-md p-6 rounded-3xl border border-white/10 text-center hover:bg-white/10 transition-colors">
                        <div class="text-[11px] text-teal-300 font-bold mb-1">🩺 غربالگری سلامت روان SCL-90</div>
                        <div class="my-2">
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-black border <?php echo $riskBadgeClass; ?>">
                                <span><?php echo $riskIcon; ?></span> <?php echo htmlspecialchars($risk); ?>
                            </span>
                        </div>
                        <div class="text-xs font-bold text-white/70" dir="ltr">
                            GSI: <span class="text-white font-mono"><?php echo htmlspecialchars((string)($psychology['scl90_gsi'] !== null ? $psychology['scl90_gsi'] : '---')); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Clinical Recommendation -->
                <?php if (!empty($psychology['recommendation'])): ?>
                <div class="bg-white/10 backdrop-blur-md rounded-3xl p-6 border border-white/15 mb-6 relative z-10">
                    <div class="flex items-center gap-2 text-xs font-bold text-amber-300 mb-2">
                        <span>📋</span> توصیه و ارزیابی تحلیلی مشاور / روانشناس بنیاد:
                    </div>
                    <p class="text-sm text-white/90 leading-relaxed font-bold">
                        <?php echo htmlspecialchars((string)$psychology['recommendation']); ?>
                    </p>
                </div>
                <?php endif; ?>

                <!-- Toggle Detailed SCL-90 Breakdown -->
                <div class="pt-2 relative z-10">
                    <button type="button" @click="showSclDetails = !showSclDetails" class="text-xs text-teal-300 hover:text-white font-bold flex items-center gap-2 transition-colors bg-white/5 hover:bg-white/10 px-4 py-2 rounded-2xl border border-white/10">
                        <span x-text="showSclDetails ? '▲ بستن زیرمقیاس‌های ۹گانه' : '▼ مشاهده ریزنمرات ۹ مقیاس بالینی (SCL-90)'"></span>
                    </button>

                    <div x-show="showSclDetails" x-transition class="mt-6 bg-black/30 rounded-3xl p-6 border border-white/10">
                        <h4 class="text-xs font-bold text-white/80 mb-4 flex items-center gap-2">
                            <span>📊</span> نمرات ابعاد ۹ گانه چک‌لیست نشانه‌های اختلالات روانی (SCL-90-R):
                        </h4>
                        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-3 text-xs">
                            <div class="bg-white/5 p-3 rounded-2xl border border-white/5">
                                <span class="text-white/50 block text-[10px]">شکایت جسمانی (SO)</span>
                                <span class="font-mono font-black text-teal-300 text-sm"><?php echo htmlspecialchars((string)($psychology['scl90_so'] !== null ? $psychology['scl90_so'] : '---')); ?></span>
                            </div>
                            <div class="bg-white/5 p-3 rounded-2xl border border-white/5">
                                <span class="text-white/50 block text-[10px]">وسواس فکری (OB)</span>
                                <span class="font-mono font-black text-teal-300 text-sm"><?php echo htmlspecialchars((string)($psychology['scl90_ob'] !== null ? $psychology['scl90_ob'] : '---')); ?></span>
                            </div>
                            <div class="bg-white/5 p-3 rounded-2xl border border-white/5">
                                <span class="text-white/50 block text-[10px]">حساسیت بین‌فردی (IS)</span>
                                <span class="font-mono font-black text-teal-300 text-sm"><?php echo htmlspecialchars((string)($psychology['scl90_is'] !== null ? $psychology['scl90_is'] : '---')); ?></span>
                            </div>
                            <div class="bg-white/5 p-3 rounded-2xl border border-white/5">
                                <span class="text-white/50 block text-[10px]">افسردگی (DE)</span>
                                <span class="font-mono font-black text-teal-300 text-sm"><?php echo htmlspecialchars((string)($psychology['scl90_de'] !== null ? $psychology['scl90_de'] : '---')); ?></span>
                            </div>
                            <div class="bg-white/5 p-3 rounded-2xl border border-white/5">
                                <span class="text-white/50 block text-[10px]">اضطراب (AN)</span>
                                <span class="font-mono font-black text-teal-300 text-sm"><?php echo htmlspecialchars((string)($psychology['scl90_an'] !== null ? $psychology['scl90_an'] : '---')); ?></span>
                            </div>
                            <div class="bg-white/5 p-3 rounded-2xl border border-white/5">
                                <span class="text-white/50 block text-[10px]">پرخاشگری (AG)</span>
                                <span class="font-mono font-black text-teal-300 text-sm"><?php echo htmlspecialchars((string)($psychology['scl90_ag'] !== null ? $psychology['scl90_ag'] : '---')); ?></span>
                            </div>
                            <div class="bg-white/5 p-3 rounded-2xl border border-white/5">
                                <span class="text-white/50 block text-[10px]">فوبیا و ترس (PH)</span>
                                <span class="font-mono font-black text-teal-300 text-sm"><?php echo htmlspecialchars((string)($psychology['scl90_ph'] !== null ? $psychology['scl90_ph'] : '---')); ?></span>
                            </div>
                            <div class="bg-white/5 p-3 rounded-2xl border border-white/5">
                                <span class="text-white/50 block text-[10px]">افکار پارانوئید (PA)</span>
                                <span class="font-mono font-black text-teal-300 text-sm"><?php echo htmlspecialchars((string)($psychology['scl90_pa'] !== null ? $psychology['scl90_pa'] : '---')); ?></span>
                            </div>
                            <div class="bg-white/5 p-3 rounded-2xl border border-white/5">
                                <span class="text-white/50 block text-[10px]">روان‌پریشی (PS)</span>
                                <span class="font-mono font-black text-teal-300 text-sm"><?php echo htmlspecialchars((string)($psychology['scl90_ps'] !== null ? $psychology['scl90_ps'] : '---')); ?></span>
                            </div>
                            <div class="bg-white/5 p-3 rounded-2xl border border-white/5">
                                <span class="text-white/50 block text-[10px]">نشانه‌های مثبت (PST)</span>
                                <span class="font-mono font-black text-teal-300 text-sm"><?php echo htmlspecialchars((string)($psychology['scl90_pst'] !== null ? $psychology['scl90_pst'] : '---')); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Student Book Requests Card -->
            <?php if ($person['code'] !== 'GENERAL'): ?>
            <div class="bg-blue-50/50 rounded-[3.5rem] p-10 shadow-sm border border-blue-100/60">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
                    <h3 class="text-xl font-black text-blue-950 flex items-center gap-4">
                        <span class="w-12 h-12 bg-blue-100 text-blue-600 rounded-2xl flex items-center justify-center text-2xl shadow-sm">📚</span>
                        درخواست‌های کتاب و ملزومات تحصیلی
                    </h3>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'student'): ?>
                        <a href="student-dashboard.php?tab=books" class="text-xs font-bold bg-teal-600 text-white px-4 py-2 rounded-xl hover:bg-teal-700 transition-colors">
                            بانک جامع کتاب و امانات ←
                        </a>
                    <?php else: ?>
                        <a href="student-dashboard.php?student_id=<?php echo $id; ?>&tab=books" class="text-xs font-bold bg-teal-600 text-white px-4 py-2 rounded-xl hover:bg-teal-700 transition-colors">
                            بانک جامع کتاب و امانات دانش‌آموز ←
                        </a>
                    <?php endif; ?>
                </div>

                <?php if (empty($student_books)): ?>
                    <div class="text-center py-8 bg-white/70 rounded-3xl border border-dashed border-blue-200">
                        <p class="text-xs text-blue-900/60 font-bold">هنوز درخواست کتابی توسط این دانش‌آموز ثبت نشده است.</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach ($student_books as $b): ?>
                            <?php
                            $st = $b['status'];
                            $bBadge = 'bg-amber-100 text-amber-800 border-amber-200';
                            $bText = 'در انتظار بررسی';
                            if ($st === 'in_progress') {
                                $bBadge = 'bg-blue-100 text-blue-800 border-blue-200';
                                $bText = 'در حال تهیه و خرید';
                            } elseif ($st === 'fulfilled') {
                                $bBadge = 'bg-emerald-100 text-emerald-800 border-emerald-200';
                                $bText = 'تهیه و تحویل شد';
                            } elseif ($st === 'rejected') {
                                $bBadge = 'bg-rose-100 text-rose-800 border-rose-200';
                                $bText = 'عدم تایید / ناموجود';
                            }
                            ?>
                            <div class="bg-white p-5 rounded-3xl border border-blue-100 shadow-sm space-y-2">
                                <div class="flex justify-between items-start gap-2">
                                    <h4 class="text-sm font-black text-slate-800"><?php echo htmlspecialchars($b['book_title']); ?></h4>
                                    <span class="text-[10px] font-black px-2.5 py-0.5 rounded-full border <?php echo $bBadge; ?> shrink-0">
                                        <?php echo $bText; ?>
                                    </span>
                                </div>
                                <div class="text-[11px] text-slate-500 flex justify-between">
                                    <span>ناشر: <strong class="text-slate-700"><?php echo htmlspecialchars($b['publisher'] ?: 'نامشخص'); ?></strong></span>
                                    <span>اولویت: <strong class="<?php echo $b['priority'] === 'urgent' ? 'text-rose-600 font-bold' : 'text-slate-600'; ?>"><?php echo $b['priority'] === 'urgent' ? 'فوری / کنکوری' : 'عادی'; ?></strong></span>
                                </div>
                                <?php if (!empty($b['admin_notes'])): ?>
                                    <div class="text-[11px] text-teal-800 bg-teal-50 p-2.5 rounded-xl border border-teal-100 mt-2">
                                        <span class="font-bold block text-[10px] text-teal-600">پاسخ مدیریت / خانم فرتاش:</span>
                                        <?php echo htmlspecialchars($b['admin_notes']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Admin Observations (Superadmin & Secretary Only) -->
            <?php if ($can_admin_notes): ?>
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
                    <label class="text-[10px] font-bold text-gray-400 block mb-1 px-2">کلاس زبان</label>
                    <input type="text" name="language_class" value="<?php echo htmlspecialchars((string)($person['language_class'] ?? '')); ?>" placeholder="مثلاً: حضوری آقای صداقت یا آنلاین خانم ذوالفقاری" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-teal-500">
                </div>
                <div>
                    <label class="text-[10px] font-bold text-gray-400 block mb-1 px-2">شماره تماس ولی</label>
                    <input type="text" name="guardian_phone" value="<?php echo htmlspecialchars((string)$person['guardian_phone']); ?>" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-teal-500">
                </div>
                <?php if ($can_finance): ?>
                <div>
                    <label class="text-[10px] font-bold text-gray-400 block mb-1 px-2">شماره حساب</label>
                    <input type="text" name="account_number" value="<?php echo htmlspecialchars((string)$person['account_number']); ?>" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-teal-500">
                </div>
                <?php endif; ?>
                <div>
                    <label class="text-[10px] font-bold text-gray-400 block mb-1 px-2">وضعیت تحصیلی</label>
                    <select name="status" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-teal-500">
                        <option value="active" <?php echo $person['status'] == 'active' ? 'selected' : ''; ?>>تحت پوشش (فعال)</option>
                        <option value="graduated" <?php echo $person['status'] == 'graduated' ? 'selected' : ''; ?>>فارغ‌التحصیل</option>
                        <option value="university" <?php echo $person['status'] == 'university' ? 'selected' : ''; ?>>دانشجو (تحصیلات عالی)</option>
                        <option value="exited" <?php echo $person['status'] == 'exited' ? 'selected' : ''; ?>>خروج از بورس</option>
                    </select>
                </div>
                
                <?php if ($can_finance): ?>
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
                    <input type="number" name="base_bursary" x-model="baseBursary" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm font-bold focus:ring-2 focus:ring-teal-500" dir="ltr">
                    <div class="text-[11px] font-bold text-teal-700 mt-1.5 px-3 py-1.5 bg-teal-50/80 rounded-xl border border-teal-100/70 flex items-center justify-between">
                        <span>مبلغ به ریال: <strong class="font-mono text-teal-950" x-text="formatMoney(baseBursary) + ' ریال'"></strong></span>
                        <span class="text-[10px] text-gray-500 font-medium">معادل: <strong class="font-mono text-teal-900" x-text="toTomans(baseBursary) + ' تومان'"></strong></span>
                    </div>
                </div>
                <div>
                    <label class="text-[10px] font-bold text-gray-400 block mb-1 px-2">قسط کامپیوتر (ریال)</label>
                    <input type="number" name="computer_installment" x-model="computerInstallment" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm font-bold focus:ring-2 focus:ring-teal-500 text-rose-600" dir="ltr">
                    <div class="text-[11px] font-bold text-rose-700 mt-1.5 px-3 py-1.5 bg-rose-50/80 rounded-xl border border-rose-100/70 flex items-center justify-between">
                        <span>مبلغ قسط: <strong class="font-mono text-rose-950" x-text="formatMoney(computerInstallment) + ' ریال'"></strong></span>
                        <span class="text-[10px] text-gray-500 font-medium">معادل: <strong class="font-mono text-rose-900" x-text="toTomans(computerInstallment) + ' تومان'"></strong></span>
                    </div>
                </div>
                <div>
                    <label class="text-[10px] font-bold text-gray-400 block mb-1 px-2">قسط وام (ریال)</label>
                    <input type="number" name="loan_installment" x-model="loanInstallment" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm font-bold focus:ring-2 focus:ring-teal-500 text-rose-600" dir="ltr">
                    <div class="text-[11px] font-bold text-rose-700 mt-1.5 px-3 py-1.5 bg-rose-50/80 rounded-xl border border-rose-100/70 flex items-center justify-between">
                        <span>مبلغ قسط: <strong class="font-mono text-rose-950" x-text="formatMoney(loanInstallment) + ' ریال'"></strong></span>
                        <span class="text-[10px] text-gray-500 font-medium">معادل: <strong class="font-mono text-rose-900" x-text="toTomans(loanInstallment) + ' تومان'"></strong></span>
                    </div>
                </div>
                <div>
                    <label class="text-[10px] font-bold text-gray-400 block mb-1 px-2">سایر کسورات (ریال)</label>
                    <input type="number" name="other_deductions" x-model="otherDeductions" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm font-bold focus:ring-2 focus:ring-teal-500 text-rose-600" dir="ltr">
                    <div class="text-[11px] font-bold text-rose-700 mt-1.5 px-3 py-1.5 bg-rose-50/80 rounded-xl border border-rose-100/70 flex items-center justify-between">
                        <span>کسورات: <strong class="font-mono text-rose-950" x-text="formatMoney(otherDeductions) + ' ریال'"></strong></span>
                        <span class="text-[10px] text-gray-500 font-medium">معادل: <strong class="font-mono text-rose-900" x-text="toTomans(otherDeductions) + ' تومان'"></strong></span>
                    </div>
                </div>
                <div class="md:col-span-2">
                    <label class="text-[10px] font-bold text-gray-400 block mb-1 px-2">بابت/شرح سایر کسورات</label>
                    <input type="text" name="deductions_desc" value="<?php echo htmlspecialchars((string)($person['deductions_desc'] ?? '')); ?>" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm focus:ring-2 focus:ring-teal-500">
                </div>

                <!-- Live Net Summary Box in Modal -->
                <div class="md:col-span-2 bg-gradient-to-r from-primary-900 to-teal-900 text-white p-5 rounded-2xl flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 shadow-md border border-teal-500/20">
                    <div>
                        <span class="text-[10px] text-teal-300 font-bold block mb-1">خالص پرداختی ماهانه به دانش‌آموز:</span>
                        <span class="text-lg font-black font-mono text-white" x-text="formatMoney(calcNetBursary()) + ' ریال'"></span>
                    </div>
                    <div class="text-right sm:text-left">
                        <span class="text-[10px] text-teal-300 font-bold block mb-1">معادل پرداختی:</span>
                        <span class="text-sm font-black font-mono text-teal-200" x-text="toTomans(calcNetBursary()) + ' تومان'"></span>
                    </div>
                </div>
                <?php endif; ?>
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
                    <div class="col-span-2 sm:col-span-1">
                        <label class="text-[10px] font-bold text-gray-400 block mb-1 px-2">مبلغ هزینه (ریال)</label>
                        <input type="number" name="amount" x-model="expenseForm.amount" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm font-bold text-left focus:ring-2 focus:ring-teal-500" dir="ltr">
                        <div class="text-[11px] font-bold text-teal-700 mt-1.5 px-3 py-1 bg-teal-50/80 rounded-xl border border-teal-100/70 flex items-center justify-between" x-show="expenseForm.amount > 0">
                            <span>مبلغ: <strong class="font-mono text-teal-950" x-text="formatMoney(expenseForm.amount) + ' ریال'"></strong></span>
                            <span class="text-[10px] text-gray-500 font-medium">معادل: <strong class="font-mono text-teal-900" x-text="toTomans(expenseForm.amount) + ' تومان'"></strong></span>
                        </div>
                    </div>
                    <div class="col-span-2 sm:col-span-1">
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

        <?php if ($can_admin_notes): ?>
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
        <?php endif; ?>

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
