<?php
session_start();
require_once 'includes/db.php';

// Access Control
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: login.php");
    exit();
}

// Fetch all categories for the filter
$categories = $pdo->query("SELECT * FROM expense_categories ORDER BY id ASC")->fetchAll();

// Fetch all expenses linked to student info and categories
$query = "
    SELECT e.*, s.name as student_name, s.surname as student_surname, s.code as student_code,
           ec.name as category_name
    FROM expenses e
    LEFT JOIN students s ON e.student_id = s.id
    LEFT JOIN expense_categories ec ON e.category_id = ec.id
    ORDER BY e.id DESC
";
$stmt = $pdo->query($query);
$expenses = $stmt->fetchAll();
$total_count = count($expenses);

// Fetch all students for the search modal
$all_students = $pdo->query("SELECT id, name, surname, code FROM students ORDER BY name ASC")->fetchAll();

// Global Stats by Category
$cat_totals = [];
$global_total = 0;
foreach ($categories as $cat) {
    $sum = $pdo->prepare("SELECT SUM(amount) FROM expenses WHERE category_id = ?");
    $sum->execute([$cat['id']]);
    $cat_val = $sum->fetchColumn() ?: 0;
    $cat_totals[$cat['id']] = $cat_val;
    $global_total += $cat_val;
}

// Convert totals to Farsi for JS
$js_totals = ['all' => formatFarsiCurrency($global_total)];
foreach ($cat_totals as $id => $val) {
    $js_totals[$id] = formatFarsiCurrency($val);
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>گزارش کل هزینه‌ها | بنیاد حکمت</title>
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
</head>
<body class="bg-gray-50 font-sans text-gray-800 antialiased" 
    x-data="{ 
        searchQuery: '', 
        activeCategory: 'all', 
        totals: <?php echo htmlspecialchars(json_encode($js_totals)); ?>,
        showStudentModal: false,
        studentSearch: '',
        targetExpenseId: null,
        targetCategoryId: null,
        students: <?php echo htmlspecialchars(json_encode($all_students)); ?>,
        get filteredStudents() {
            if (!this.studentSearch) return this.students.slice(0, 10);
            return this.students.filter(s => 
                (s.name + ' ' + s.surname).includes(this.studentSearch) || 
                s.code.includes(this.studentSearch)
            ).slice(0, 10);
        }
    }">

    <nav class="bg-white/80 backdrop-blur-md border-b sticky top-0 z-50">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <div class="flex items-center gap-4">
                <a href="index.php" class="text-primary-900 font-black text-xl flex items-center gap-2">
                    <div class="w-8 h-8 bg-teal-600 rounded-full flex items-center justify-center text-white text-sm">م</div>
                    بنیاد حکمت
                </a>
                <span class="text-gray-300">/</span>
                <span class="font-bold text-gray-600">گزارش کل هزینه‌ها</span>
            </div>
            <div class="flex items-center gap-4">
                <a href="admin/index.php" class="text-primary-900 text-xs font-bold hover:bg-gray-100 px-4 py-2 rounded-lg transition-all">داشبورد مدیریت</a>
                <a href="admin-logout.php" class="text-red-500 text-xs font-bold hover:bg-red-50 px-4 py-2 rounded-lg transition-all">خروج</a>
            </div>
        </div>
    </nav>

    <header class="bg-gradient-to-l from-primary-900 to-indigo-900 text-white py-16">
        <div class="container mx-auto px-6 flex flex-col md:flex-row justify-between items-center gap-10">
            <div class="text-right">
                <h1 class="text-4xl font-black mb-4">گزارش شفافِ هزینه‌کرد و خروجی‌ها</h1>
                <p class="text-teal-100 opacity-80 max-w-xl">رهگیری تمام هزینه‌های انجام شده برای دانش‌آموزان به صورت تجمیعی. پول‌های خیریه برای چه افرادی و در چه سرفصل‌هایی پرداخت شده است.</p>
            </div>
            <div class="bg-white/10 backdrop-blur-xl p-8 rounded-[3rem] border border-white/20 text-center min-w-[250px]">
                <div class="text-3xl font-black text-red-400" x-text="totals[activeCategory]"></div>
                <div class="text-[10px] text-white/60 font-bold mt-1">مجموع هزینه‌های <span x-text="activeCategory === 'all' ? 'کل' : 'این سرفصل'"></span> (ریال)</div>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-6 -mt-10 pb-20">
        <!-- Filter Tabs & Search Bar -->
        <div class="flex flex-col md:flex-row gap-6 mb-10 items-stretch">
            <div class="bg-white rounded-[2.5rem] shadow-xl p-2 border border-gray-100 flex flex-wrap items-center gap-1 flex-1">
                <button @click="activeCategory = 'all'" :class="activeCategory === 'all' ? 'bg-primary-900 text-white shadow-lg' : 'text-gray-500 hover:bg-gray-100'" 
                        class="px-4 py-3 rounded-2xl text-[10px] font-black transition-all flex-1 min-w-[100px]">همه تراکنش‌ها</button>
                <?php foreach ($categories as $cat): ?>
                <button @click="activeCategory = '<?php echo $cat['id']; ?>'" :class="activeCategory === '<?php echo $cat['id']; ?>' ? 'bg-primary-900 text-white shadow-lg' : 'text-gray-500 hover:bg-gray-100'" 
                        class="px-4 py-3 rounded-2xl text-[10px] font-black transition-all flex-1 text-center min-w-[100px]">
                    <?php echo $cat['name']; ?>
                </button>
                <?php endforeach; ?>
            </div>

            <div class="bg-white rounded-[2rem] shadow-xl p-2 border border-gray-100 flex items-center relative min-w-[300px]">
                <input type="text" x-model="searchQuery" placeholder="جستجو در شرح، کد یا نام..." 
                    class="w-full bg-transparent border-none rounded-2xl px-12 py-3 focus:outline-none text-sm font-bold">
                <span class="absolute right-6 top-1/2 -translate-y-1/2 text-xl">🔍</span>
            </div>
        </div>

        <!-- Master Expenses Table -->
        <div class="bg-white rounded-[3.5rem] p-10 shadow-xl border border-gray-100 overflow-x-auto">
            <table class="w-full text-right whitespace-nowrap">
                <thead>
                    <tr class="text-[11px] text-gray-400 uppercase tracking-wider border-b border-gray-50">
                        <th class="pb-6 font-bold w-12 text-center">ردیف</th>
                        <th class="pb-6 font-bold">مددجو / سرفصل</th>
                        <th class="pb-6 font-bold">شرح هزینه / بابت</th>
                        <th class="pb-6 font-bold">تاریخ و فیش</th>
                        <th class="pb-6 font-bold">مبلغ هزینه (ریال)</th>
                        <th class="pb-6 font-bold text-center">جزئیات بیش‌تر</th>
                    </tr>
                </thead>
                <tbody class="text-sm text-gray-700">
                    <?php if (empty($expenses)): ?>
                    <tr>
                        <td colspan="6" class="py-10 text-center text-gray-400 font-bold">هیچ هزینه‌ای ثبت نشده است.</td>
                    </tr>
                    <?php endif; ?>
                    
                    <?php foreach ($expenses as $index => $ex): ?>
                    <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors" 
                        x-show="(activeCategory === 'all' || activeCategory === '<?php echo $ex['category_id']; ?>') && (searchQuery === '' || '<?php echo htmlspecialchars($ex['description'] . ' ' . $ex['student_name'] . ' ' . $ex['student_surname'] . ' ' . $ex['student_code']); ?>'.includes(searchQuery))">
                        <td class="py-4 text-center text-xs text-gray-400 font-bold"><?php echo toFarsiDigits($total_count - $index); ?></td>
                        
                        <td class="py-4">
                            <div class="flex items-center gap-3 group transition-colors">
                                <div class="w-10 h-10 bg-teal-50 text-teal-600 rounded-xl flex items-center justify-center font-black text-xs shadow-sm">
                                    <?php echo $ex['student_code'] ?: '-'; ?>
                                </div>
                                <div class="relative">
                                    <a href="person-detail.php?id=<?php echo $ex['student_id']; ?>" class="font-bold text-gray-900 hover:text-teal-600 block">
                                        <?php 
                                            if ($ex['student_code'] === 'GENERAL') {
                                                echo $ex['student_name'] . ' (' . $ex['category_name'] . ')';
                                            } else {
                                                echo $ex['student_name'] . ' ' . $ex['student_surname']; 
                                            }
                                        ?>
                                    </a>
                                    <div class="flex items-center gap-2 mt-1">
                                        <select 
                                            @change="
                                                if ($event.target.value == '3') {
                                                    targetExpenseId = <?php echo $ex['id']; ?>;
                                                    targetCategoryId = 3;
                                                    showStudentModal = true;
                                                } else {
                                                    updateCategory(<?php echo $ex['id']; ?>, $event.target.value);
                                                    location.reload();
                                                }
                                            "
                                            class="text-[9px] bg-gray-100/50 border-none rounded-full px-2 py-0.5 font-black uppercase tracking-tighter text-gray-500 cursor-pointer focus:ring-0 focus:bg-white transition-all hover:bg-teal-50 hover:text-teal-600">
                                            <?php foreach ($categories as $cat): ?>
                                            <option value="<?php echo $cat['id']; ?>" <?php echo $ex['category_id'] == $cat['id'] ? 'selected' : ''; ?>>
                                                <?php echo $cat['name']; ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <a href="person-detail.php?id=<?php echo $ex['student_id']; ?>" class="text-[9px] text-teal-500/30 hover:text-teal-500 font-bold">پرونده 🔗</a>
                                    </div>
                                </div>
                            </div>
                        </td>
                        
                        <td class="py-4">
                            <div class="text-xs font-bold text-gray-800 break-words whitespace-normal leading-relaxed min-w-[200px]"><?php echo $ex['description'] ?: '---'; ?></div>
                            <?php if (!empty($ex['notes'])): ?>
                            <div class="text-[10px] text-gray-400 font-normal mt-1 break-words whitespace-normal"><?php echo $ex['notes']; ?></div>
                            <?php endif; ?>
                        </td>
                        
                        <td class="py-4">
                            <span class="inline-block px-3 py-1 bg-gray-100 text-gray-600 rounded-lg text-[10px] font-bold mb-1"><?php echo toFarsiDigits($ex['expense_date'] ?: 'ندارد'); ?></span>
                            <?php if (!empty($ex['receipt_no'])): ?>
                            <div class="text-[10px] text-gray-400 font-mono mt-1" dir="ltr">#<?php echo toFarsiDigits($ex['receipt_no']); ?></div>
                            <?php endif; ?>
                        </td>
                        
                        <td class="py-4 text-lg font-black <?php echo $ex['amount'] < 0 ? 'text-green-500' : 'text-red-500'; ?> " dir="ltr">
                            <?php echo formatFarsiCurrency($ex['amount']); ?>
                        </td>
                        
                        <td class="py-4 text-center">
                            <a href="person-detail.php?id=<?php echo $ex['student_id']; ?>" class="inline-flex items-center justify-center bg-indigo-50 text-indigo-600 hover:bg-indigo-600 hover:text-white px-4 py-2 rounded-xl text-[10px] font-black transition-all">
                                مدیریت در پرونده
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </main>

    <script>
        async function updateCategory(id, category_id, student_id = null) {
            const formData = new FormData();
            formData.append('id', id);
            formData.append('category_id', category_id);
            if (student_id) formData.append('student_id', student_id);
            formData.append('action', 'update_expense_category');
            
            try {
                const res = await fetch('admin-request-handler.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (!data.success) alert('خطا در بروزرسانی سرفصل');
                else if (student_id) location.reload(); // Explicit reload for student re-link
            } catch (e) {
                alert('خطا در ارتباط با سرور');
            }
        }
    </script>

    <!-- Student Search Modal -->
    <div x-show="showStudentModal" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-6 bg-primary-900/40 backdrop-blur-sm">
        <div class="bg-white rounded-[2.5rem] w-full max-w-lg p-10 shadow-2xl border border-gray-100 transform transition-all" @click.away="showStudentModal = false">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-black text-primary-900 flex items-center gap-3">
                    <span class="w-10 h-10 bg-teal-50 text-teal-600 rounded-xl flex items-center justify-center text-lg">🔍</span>
                    انتخاب مددجو برای سرفصل بورس
                </h3>
                <button @click="showStudentModal = false" class="text-gray-400 hover:text-red-500 transition-colors text-2xl">✕</button>
            </div>
            
            <p class="text-xs text-gray-400 font-bold mb-6 leading-relaxed">این هزینه تغییر ماهیت یافت و اکنون به عنوان بورس پرداختی ثبت می‌شود. لطفاً مشخص کنید این مبلغ برای کدام دانش‌آموز هزینه شده است.</p>
            
            <div class="relative mb-6">
                <input type="text" x-model="studentSearch" placeholder="جستجوی نام یا کد مددجو..." 
                    class="w-full bg-gray-50 border-2 border-gray-100 rounded-2xl px-6 py-4 text-sm focus:ring-2 focus:ring-teal-500 focus:bg-white transition-all outline-none">
            </div>

            <div class="space-y-1 max-h-64 overflow-y-auto custom-scrollbar pr-2">
                <template x-for="s in filteredStudents" :key="s.id">
                    <button @click="updateCategory(targetExpenseId, targetCategoryId, s.id); showStudentModal = false;" 
                        class="w-full text-right p-4 rounded-[1.5rem] hover:bg-teal-50 hover:text-teal-700 border border-transparent hover:border-teal-100 transition-all flex items-center justify-between group">
                        <div class="flex items-center gap-4">
                            <div class="w-10 h-10 bg-gray-50 text-gray-400 group-hover:bg-white group-hover:text-teal-600 rounded-xl flex items-center justify-center font-black text-[10px]">#<span x-text="s.code"></span></div>
                            <div class="font-black text-sm" x-text="s.name + ' ' + s.surname"></div>
                        </div>
                        <span class="opacity-0 group-hover:opacity-100 transition-opacity">←</span>
                    </button>
                </template>
            </div>
        </div>
    </div>
</body>
</html>
