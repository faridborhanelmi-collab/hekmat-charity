<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';

// مسدودسازی دسترسی اپراتور دیتا (ویانا وحیدی) به لیست مددجویان و هدایت خودکار به مخزن کتابخانه
if (($_SESSION['role'] ?? '') === 'data_operator') {
    header("Location: admin/library.php");
    exit();
}

// Access Control
require_role(['superadmin', 'secretary', 'education_deputy', 'board_member', 'admin']);

$query = "SELECT * FROM students ORDER BY id ASC";
$stmt = $pdo->query($query);
$students = $stmt->fetchAll();

$count_all = count($students);
$count_active = 0;
$count_graduated = 0;
$count_university = 0;
$count_exited = 0;

foreach ($students as $s) {
    $st = $s['status'] ?: 'active';
    if ($st === 'active') $count_active++;
    elseif ($st === 'graduated') $count_graduated++;
    elseif ($st === 'university') $count_university++;
    elseif ($st === 'exited') $count_exited++;
    else $count_active++;
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت مددجویان | بنیاد حکمت</title>
<style>
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:100;font-display:swap;src:url('/assets/fonts/Vazirmatn-100.woff2') format('woff2')}
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:300;font-display:swap;src:url('/assets/fonts/Vazirmatn-300.woff2') format('woff2')}
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:400;font-display:swap;src:url('/assets/fonts/Vazirmatn-400.woff2') format('woff2')}
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:500;font-display:swap;src:url('/assets/fonts/Vazirmatn-500.woff2') format('woff2')}
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:700;font-display:swap;src:url('/assets/fonts/Vazirmatn-700.woff2') format('woff2')}
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:900;font-display:swap;src:url('/assets/fonts/Vazirmatn-900.woff2') format('woff2')}
</style>
<link rel="stylesheet" href="/assets/tailwind.min.css">
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
<body class="bg-gray-50 font-sans text-gray-800 antialiased">

    <?php include 'includes/navbar.php'; ?>

    <header class="bg-primary-900 text-white py-12">
        <div class="container mx-auto px-6 flex flex-col md:flex-row justify-between items-center gap-8">
            <div class="text-right">
                <h1 class="text-3xl font-black mb-2">مدیریت افراد تحت پوشش</h1>
                <p class="text-teal-100 opacity-70 text-sm">لیست هوشمند مددجویان و نخبگان تحت حمایت بنیاد.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <a href="admin/library.php" class="bg-teal-600 hover:bg-teal-500 text-white font-black px-6 py-3 rounded-2xl shadow-xl transition-all transform hover:-translate-y-1 flex items-center gap-2 text-sm">
                    <span>📚</span>
                    <span>بانک کتاب‌ها و امانات (۲۰۱ جلد)</span>
                </a>
                <?php if (can_add_student() || in_array($_SESSION['role'] ?? '', ['superadmin', 'secretary', 'admin'])): ?>
                <button onclick="openAddStudentModal()" 
                        class="bg-teal-500 hover:bg-teal-400 text-white font-black px-6 py-3 rounded-2xl shadow-xl transition-all transform hover:-translate-y-1 text-sm flex items-center gap-2 cursor-pointer">
                    <span class="text-lg leading-none">+</span>
                    <span>افزودن مددجوی جدید</span>
                </button>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-6 -mt-8 pb-20">
        <!-- Status Filter Tabs / Pills -->
        <div class="flex flex-wrap items-center gap-3 mb-6 bg-white/80 backdrop-blur-md p-3 rounded-3xl shadow-sm border border-gray-100">
            <button onclick="setFilterTab('all')" data-tab="all" class="filter-tab active px-5 py-2.5 rounded-2xl text-xs font-black transition-all bg-primary-900 text-white shadow-md">
                همه مددجویان <span class="opacity-80 font-normal mr-1">(<?php echo toFarsiDigits($count_all); ?>)</span>
            </button>
            <button onclick="setFilterTab('active')" data-tab="active" class="filter-tab px-5 py-2.5 rounded-2xl text-xs font-black transition-all bg-gray-100 text-gray-600 hover:bg-teal-50 hover:text-teal-700">
                دانش‌آموزان تحت پوشش <span class="opacity-80 font-normal mr-1">(<?php echo toFarsiDigits($count_active); ?>)</span>
            </button>
            <button onclick="setFilterTab('university')" data-tab="university" class="filter-tab px-5 py-2.5 rounded-2xl text-xs font-black transition-all bg-gray-100 text-gray-600 hover:bg-blue-50 hover:text-blue-700">
                دانشجویان تحت پوشش <span class="opacity-80 font-normal mr-1">(<?php echo toFarsiDigits($count_university); ?>)</span>
            </button>
            <button onclick="setFilterTab('graduated')" data-tab="graduated" class="filter-tab px-5 py-2.5 rounded-2xl text-xs font-black transition-all bg-gray-100 text-gray-600 hover:bg-purple-50 hover:text-purple-700">
                فارغ‌التحصیلان <span class="opacity-80 font-normal mr-1">(<?php echo toFarsiDigits($count_graduated); ?>)</span>
            </button>
            <button onclick="setFilterTab('exited')" data-tab="exited" class="filter-tab px-5 py-2.5 rounded-2xl text-xs font-black transition-all bg-gray-100 text-gray-600 hover:bg-rose-50 hover:text-rose-700">
                خروج از بورس <span class="opacity-80 font-normal mr-1">(<?php echo toFarsiDigits($count_exited); ?>)</span>
            </button>
        </div>

        <!-- Search & Stats Bar -->
        <div class="bg-white rounded-3xl shadow-xl p-6 mb-8 flex flex-col md:flex-row justify-between items-center gap-6 border border-gray-100">
            <div class="flex flex-col md:flex-row gap-4 w-full md:w-auto flex-1">
                <div class="relative w-full md:w-80">
                    <input type="text" id="searchInput" placeholder="جستجوی نام، کد مددجو ..." 
                        class="w-full bg-gray-50 border border-gray-200 rounded-2xl px-12 py-3 focus:outline-none focus:ring-2 focus:ring-primary-600 transition-all text-sm">
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400">🔍</span>
                </div>
                <div class="relative w-full md:w-64">
                    <select id="statusFilter" class="w-full bg-gray-50 border border-gray-200 rounded-2xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-600 transition-all text-sm appearance-none text-gray-700 font-bold cursor-pointer">
                        <option value="all">همه مددجویان (<?php echo toFarsiDigits($count_all); ?>)</option>
                        <option value="active">دانش‌آموزان تحت پوشش (<?php echo toFarsiDigits($count_active); ?>)</option>
                        <option value="university">دانشجویان تحت پوشش (<?php echo toFarsiDigits($count_university); ?>)</option>
                        <option value="graduated">فارغ‌التحصیلان (<?php echo toFarsiDigits($count_graduated); ?>)</option>
                        <option value="exited">خروج از بورس (<?php echo toFarsiDigits($count_exited); ?>)</option>
                    </select>
                </div>
                <div class="relative w-full md:w-48">
                    <select id="genderFilter" class="w-full bg-gray-50 border border-gray-200 rounded-2xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-600 transition-all text-sm appearance-none text-gray-700 font-bold cursor-pointer">
                        <option value="all">جنسیت (همه)</option>
                        <option value="Male">پسران</option>
                        <option value="Female">دختران</option>
                    </select>
                </div>
            </div>
            <div class="flex gap-10">
                <div class="text-center">
                    <div class="text-2xl font-black text-teal-600"><?php echo toFarsiDigits($count_active + $count_university); ?></div>
                    <div class="text-[10px] text-gray-400 font-bold">کل تحت پوشش فعال</div>
                </div>
                <div class="w-px h-10 bg-gray-100"></div>
                <div class="text-center">
                    <div class="text-2xl font-black text-primary-900"><?php echo toFarsiDigits($count_all); ?></div>
                    <div class="text-[10px] text-gray-400 font-bold">کل پرونده‌ها</div>
                </div>
            </div>
        </div>

        <!-- Listing Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6" id="peopleGrid">
            <?php 
            $status_meta = [
                'active' => ['label' => 'دانش‌آموز تحت پوشش', 'badge_class' => 'bg-teal-50 text-teal-700 border-teal-200', 'dot' => 'bg-teal-500 animate-pulse'],
                'university' => ['label' => 'دانشجوی تحت پوشش', 'badge_class' => 'bg-blue-50 text-blue-700 border-blue-200', 'dot' => 'bg-blue-500 animate-pulse'],
                'graduated' => ['label' => 'فارغ‌التحصیل', 'badge_class' => 'bg-purple-50 text-purple-700 border-purple-200', 'dot' => 'bg-purple-500'],
                'exited' => ['label' => 'خروج از بورس', 'badge_class' => 'bg-rose-50 text-rose-700 border-rose-200', 'dot' => 'bg-rose-500']
            ];

            foreach ($students as $p): 
                $status = $p['status'] ?: 'active';
                $is_inactive = ($status === 'exited');
                $card_style = $is_inactive ? 'bg-gray-100 opacity-60 grayscale border-gray-300' : 'bg-white border-gray-100';
                $icon_style = $is_inactive ? 'bg-gray-200 text-gray-500' : 'bg-teal-50 text-teal-600';
                $curr_meta = $status_meta[$status] ?? $status_meta['active'];
            ?>
            <div class="people-card <?php echo $card_style; ?> rounded-[2.5rem] p-8 shadow-sm hover:shadow-xl transition-all group relative overflow-hidden flex flex-col justify-between" 
                 data-status="<?php echo $status; ?>" 
                 data-gender="<?php echo htmlspecialchars($p['gender'] ?? 'all'); ?>"
                 data-search="<?php echo htmlspecialchars($p['name'] . ' ' . $p['surname'] . ' ' . $p['code'] . ' ' . $p['national_id'] . ' ' . ($p['school'] ?? '') . ' ' . ($p['language_class'] ?? '')); ?>">
                <div class="absolute top-0 right-0 w-24 h-24 bg-teal-500/5 rounded-full -mr-10 -mt-10 group-hover:scale-150 transition-transform"></div>
                
                <div class="flex items-center justify-between mb-4 relative z-10 w-full">
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black border <?php echo $curr_meta['badge_class']; ?>">
                        <span class="w-1.5 h-1.5 rounded-full <?php echo $curr_meta['dot']; ?>"></span>
                        <?php echo $curr_meta['label']; ?>
                    </span>
                    <span class="text-[10px] text-gray-400 font-mono font-bold">#<?php echo toFarsiDigits($p['code']); ?></span>
                </div>

                <div class="flex flex-col items-center text-center">
                    <div class="w-20 h-20 <?php echo $icon_style; ?> rounded-[1.5rem] flex items-center justify-center text-3xl font-black mb-4 shadow-inner">
                        <?php echo mb_substr($p['name'], 0, 1); ?>
                    </div>
                    
                    <h3 class="text-lg font-black text-primary-900 mb-1"><?php echo $p['name'] . ' ' . $p['surname']; ?></h3>
                    <?php if (!empty($p['alias_name'])): ?>
                    <p class="text-[10px] text-teal-600 font-bold mb-3 bg-teal-50/80 px-3 py-0.5 rounded-full"><?php echo htmlspecialchars($p['alias_name']); ?></p>
                    <?php else: ?>
                    <div class="mb-3"></div>
                    <?php endif; ?>

                    <?php if (!empty($p['school'])): ?>
                    <div class="text-[10px] text-gray-500 font-bold mb-3 flex items-center justify-center gap-1">
                        <span>🏫</span> <span><?php echo htmlspecialchars($p['school']); ?></span>
                        <?php if (!empty($p['language_class'])): ?>
                        <span class="text-[9px] bg-teal-50 text-teal-700 font-bold px-2 py-0.5 rounded-full border border-teal-200 mr-1">🗣️ زبان</span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="w-full grid grid-cols-2 gap-3 mb-6">
                        <div class="bg-gray-50 p-3 rounded-2xl text-right">
                            <div class="text-[8px] text-gray-400 font-bold">نام پدر</div>
                            <div class="text-[10px] font-black text-primary-900 truncate"><?php echo $p['father_name'] ?: '---'; ?></div>
                        </div>
                        <div class="bg-gray-50 p-3 rounded-2xl text-right">
                            <div class="text-[8px] text-gray-400 font-bold">پایه / رشته</div>
                            <div class="text-[10px] font-black text-primary-900 truncate"><?php echo ($p['grade'] ? $p['grade'] . ' ' : '') . ($p['field_of_study'] ?: ''); ?></div>
                        </div>
                    </div>

                    <div class="flex w-full gap-2 mt-auto">
                        <a href="person-detail.php?id=<?php echo $p['id']; ?>" class="flex-1 bg-primary-900 text-white py-3 rounded-2xl text-[10px] font-black shadow-lg hover:bg-teal-600 transition-all text-center">مشاهده پرونده</a>
                        <button onclick="confirmDelete(<?php echo $p['id']; ?>)" class="w-12 bg-red-50 text-red-500 rounded-2xl flex items-center justify-center hover:bg-red-500 hover:text-white transition-all">🗑️</button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- No Results -->
        <div id="noResults" class="hidden text-center py-20">
            <div class="text-6xl mb-4">🔦</div>
            <h3 class="text-xl font-bold text-gray-400">مددجویی با این مشخصات یافت نشد.</h3>
        </div>
    </main>

    <script>
        const searchInput = document.getElementById('searchInput');
        const statusFilter = document.getElementById('statusFilter');
        const genderFilter = document.getElementById('genderFilter');
        const peopleGrid = document.getElementById('peopleGrid');
        const cards = document.querySelectorAll('.people-card');
        const noResults = document.getElementById('noResults');
        const filterTabs = document.querySelectorAll('.filter-tab');

        function setFilterTab(status) {
            statusFilter.value = status;
            filterTabs.forEach(tab => {
                if (tab.getAttribute('data-tab') === status) {
                    tab.className = 'filter-tab active px-5 py-2.5 rounded-2xl text-xs font-black transition-all bg-primary-900 text-white shadow-md';
                } else {
                    tab.className = 'filter-tab px-5 py-2.5 rounded-2xl text-xs font-black transition-all bg-gray-100 text-gray-600 hover:bg-gray-200';
                }
            });
            filterCards();
        }

        function filterCards() {
            const query = searchInput.value.toLowerCase().trim();
            const selectedStatus = statusFilter.value;
            const selectedGender = genderFilter ? genderFilter.value : 'all';
            let hasResults = false;

            // Sync tab active state
            filterTabs.forEach(tab => {
                if (tab.getAttribute('data-tab') === selectedStatus) {
                    tab.className = 'filter-tab active px-5 py-2.5 rounded-2xl text-xs font-black transition-all bg-primary-900 text-white shadow-md';
                } else {
                    tab.className = 'filter-tab px-5 py-2.5 rounded-2xl text-xs font-black transition-all bg-gray-100 text-gray-600 hover:bg-gray-200';
                }
            });

            cards.forEach(card => {
                const searchData = (card.getAttribute('data-search') || '').toLowerCase();
                const cardStatus = card.getAttribute('data-status');
                const cardGender = card.getAttribute('data-gender');
                
                const matchesSearch = searchData.includes(query);
                const matchesStatus = (selectedStatus === 'all') || (cardStatus === selectedStatus);
                const matchesGender = (selectedGender === 'all') || (cardGender === selectedGender);

                if (matchesSearch && matchesStatus && matchesGender) {
                    card.style.display = 'flex';
                    hasResults = true;
                } else {
                    card.style.display = 'none';
                }
            });

            noResults.classList.toggle('hidden', hasResults);
        }

        searchInput.addEventListener('input', filterCards);
        statusFilter.addEventListener('change', filterCards);
        if(genderFilter) genderFilter.addEventListener('change', filterCards);

        async function confirmDelete(id) {
            if (!confirm('آیا از حذف این پرونده اطمینان دارید؟ این عمل غیرقابل بازگشت است.')) return;
            const formData = new FormData();
            formData.append('action', 'delete_record');
            formData.append('type', 'student');
            formData.append('id', id);
            
            try {
                const res = await fetch('admin-request-handler.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    location.reload();
                } else {
                    alert(data.message || 'خطا در حذف پرونده');
                }
            } catch (e) {
                alert('خطا در ارتباط با سرور.');
            }
        }
    </script>

    <?php if (can_add_student() || in_array($_SESSION['role'] ?? '', ['superadmin', 'secretary', 'admin'])): ?>
    <!-- Modal: افزودن مددجوی جدید (تکی و گروهی) -->
    <div id="add-student-modal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 flex items-center justify-center p-4 hidden">
        <div class="bg-white rounded-3xl shadow-2xl max-w-3xl w-full max-h-[92vh] overflow-hidden border border-gray-100 flex flex-col animate-fade-in text-gray-800">
            <!-- Modal Header -->
            <div class="px-6 py-5 bg-gradient-to-l from-primary-900 via-primary-800 to-teal-800 text-white flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-white/10 flex items-center justify-center text-xl">
                        👤
                    </div>
                    <div>
                        <h3 class="text-lg font-black leading-tight">افزودن مددجوی جدید به بنیاد</h3>
                        <p class="text-xs text-teal-100 opacity-80 mt-0.5">ثبت انفرادی یا بارگذاری لیست دسته‌جمعی دانش‌آموزان</p>
                    </div>
                </div>
                <button type="button" onclick="closeAddStudentModal()" class="w-9 h-9 rounded-xl bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition-colors text-lg">
                    ✕
                </button>
            </div>

            <!-- Tabs -->
            <div class="flex border-b border-gray-100 bg-gray-50/80 px-6 pt-3 gap-2">
                <button type="button" id="tab-btn-single" onclick="switchStudentTab('single')" class="pb-3 px-4 text-xs font-black border-b-2 border-teal-600 text-teal-700 flex items-center gap-2 transition-all">
                    <span>✏️</span>
                    <span>ثبت تکی پرونده (فرم دستی)</span>
                </button>
                <button type="button" id="tab-btn-batch" onclick="switchStudentTab('batch')" class="pb-3 px-4 text-xs font-bold border-b-2 border-transparent text-gray-500 hover:text-gray-700 flex items-center gap-2 transition-all">
                    <span>📑</span>
                    <span>بارگذاری دسته‌جمعی (فایل اکسل / CSV)</span>
                </button>
            </div>

            <!-- Modal Body (Scrollable) -->
            <div class="p-6 overflow-y-auto flex-1">
                <!-- Single Form -->
                <form id="form-add-student" onsubmit="submitSingleStudent(event)" class="space-y-4">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">نام <span class="text-rose-500">*</span></label>
                            <input type="text" name="name" required placeholder="مثال: فاطمه" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:bg-white focus:border-teal-500 focus:ring-2 focus:ring-teal-100 outline-none transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">نام خانوادگی <span class="text-rose-500">*</span></label>
                            <input type="text" name="surname" required placeholder="مثال: محمدی" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:bg-white focus:border-teal-500 focus:ring-2 focus:ring-teal-100 outline-none transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">کد ملی</label>
                            <input type="text" name="national_id" maxlength="10" placeholder="۱۰ رقم کد ملی" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:bg-white focus:border-teal-500 focus:ring-2 focus:ring-teal-100 outline-none transition-all dir-ltr text-right">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">کد پرونده بنیاد (اختیاری)</label>
                            <input type="text" name="code" placeholder="خالی بگذارید تا خودکار ایجاد شود" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:bg-white focus:border-teal-500 focus:ring-2 focus:ring-teal-100 outline-none transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">جنسیت</label>
                            <select name="gender" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:bg-white focus:border-teal-500 outline-none">
                                <option value="دختر" selected>دختر</option>
                                <option value="پسر">پسر</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">وضعیت پرونده</label>
                            <select name="status" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:bg-white focus:border-teal-500 outline-none">
                                <option value="active" selected>دانش‌آموز تحت پوشش (فعال)</option>
                                <option value="university">دانشجوی تحت پوشش</option>
                                <option value="graduated">فارغ‌التحصیل</option>
                                <option value="exited">خروج از پوشش</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">پایه / مقطع تحصیلی</label>
                            <input type="text" name="grade" placeholder="مثال: دهم، دوازدهم، کارشناسی..." class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:bg-white focus:border-teal-500 focus:ring-2 focus:ring-teal-100 outline-none transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">رشته تحصیلی</label>
                            <input type="text" name="field_of_study" placeholder="مثال: ریاضی فیزیک، تجربی، کامپیوتر..." class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:bg-white focus:border-teal-500 focus:ring-2 focus:ring-teal-100 outline-none transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">نام مدرسه / دانشگاه</label>
                            <input type="text" name="school" placeholder="نام آموزشگاه یا دانشگاه" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:bg-white focus:border-teal-500 focus:ring-2 focus:ring-teal-100 outline-none transition-all">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">شماره تماس دانش‌آموز</label>
                            <input type="text" name="phone" placeholder="09xxxxxxxxx" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:bg-white focus:border-teal-500 outline-none dir-ltr text-right">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">شماره تماس ولی / سرپرست</label>
                            <input type="text" name="guardian_phone" placeholder="09xxxxxxxxx" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:bg-white focus:border-teal-500 outline-none dir-ltr text-right">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">نام پدر</label>
                            <input type="text" name="father_name" placeholder="نام پدر" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:bg-white focus:border-teal-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">نام مادر</label>
                            <input type="text" name="mother_name" placeholder="نام مادر" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:bg-white focus:border-teal-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">تاریخ تولد</label>
                            <input type="text" name="birthday" placeholder="مثال: ۱۳۸۶/۰۲/۱۵" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:bg-white focus:border-teal-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">مشاور یا مددکار پرونده</label>
                            <input type="text" name="counselor" placeholder="نام مشاور مربوطه" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:bg-white focus:border-teal-500 outline-none">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">شماره حساب یا شبا (جهت واریز بورسیه)</label>
                            <input type="text" name="account_number" placeholder="شماره حساب یا IR..." class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:bg-white focus:border-teal-500 outline-none dir-ltr text-right">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-700 mb-1">مبلغ پایه بورس ماهانه (ریال)</label>
                            <input type="text" name="base_bursary" value="20,000,000" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:bg-white focus:border-teal-500 outline-none dir-ltr text-right">
                        </div>
                        <div class="flex items-center pt-6">
                            <label class="inline-flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="bursary_eligible" value="1" checked class="w-4 h-4 rounded text-teal-600 focus:ring-teal-500">
                                <span class="text-xs font-bold text-gray-800">مشمول دریافت بورسیه ماهیانه بنیاد</span>
                            </label>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">نشانی محل سکونت</label>
                        <input type="text" name="address" placeholder="شهر، منطقه، خیابان..." class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:bg-white focus:border-teal-500 outline-none">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-700 mb-1">یادداشت و ملاحظات پرونده</label>
                        <textarea name="notes" rows="2" placeholder="توضیحات تکمیلی یا وضعیت خاص دانش‌آموز..." class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2 text-sm focus:bg-white focus:border-teal-500 outline-none"></textarea>
                    </div>

                    <div id="single-error" class="hidden p-3 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-700 font-bold"></div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-gray-100">
                        <button type="button" onclick="closeAddStudentModal()" class="px-5 py-2.5 rounded-xl text-xs font-bold bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors">
                            انصراف
                        </button>
                        <button type="submit" id="btn-submit-single" class="px-6 py-2.5 rounded-xl text-xs font-black bg-teal-600 hover:bg-teal-700 text-white shadow-md transition-all flex items-center gap-2">
                            <span>✓</span>
                            <span>ثبت و تشکیل پرونده مددجو</span>
                        </button>
                    </div>
                </form>

                <!-- Batch Form -->
                <form id="form-batch-students" onsubmit="submitBatchStudents(event)" class="hidden space-y-5">
                    <div class="p-4 bg-teal-50/60 rounded-2xl border border-teal-100 text-teal-900 text-xs leading-relaxed space-y-2">
                        <div class="font-black flex items-center gap-2 text-sm text-teal-800">
                            <span>💡</span>
                            <span>راهنمای بارگذاری گروهی مددجویان</span>
                        </div>
                        <p>شما می‌توانید فایل‌های با فرمت اکسل (<strong>.xlsx</strong>) یا فایل متنی (<strong>.csv</strong>) حاوی فهرست دانش‌آموزان را مستقیماً بارگذاری نمایید.</p>
                        <div class="bg-white/80 p-3 rounded-xl border border-teal-200/60">
                            <strong>ستون‌های پیشنهادی در فایل اکسل:</strong>
                            <p class="text-teal-700 mt-1">نام | نام خانوادگی | کد ملی | شماره تماس | مقطع تحصیلی | رشته | مدرسه</p>
                        </div>
                        <p class="text-gray-600 text-[11px]">توجه: سیستم به‌طور هوشمند سرستون‌ها را تشخیص می‌دهد و از ورود رکوردهای با کد ملی تکراری پیشگیری می‌کند.</p>
                    </div>

                    <div class="border-2 border-dashed border-gray-300 rounded-2xl p-6 text-center hover:border-teal-500 bg-gray-50 transition-colors cursor-pointer" onclick="document.getElementById('batch-file-input').click()">
                        <div class="text-3xl mb-2">📊</div>
                        <p class="text-xs font-bold text-gray-700">برای انتخاب فایل اکسل یا CSV اینجا کلیک کنید</p>
                        <p class="text-[11px] text-gray-400 mt-1">فرمت‌های مجاز: .xlsx , .csv</p>
                        <input type="file" id="batch-file-input" name="file" accept=".xlsx,.csv,.txt" class="hidden" onchange="updateBatchFileName(this)">
                        <div id="batch-file-name" class="mt-3 text-xs font-black text-teal-700 hidden"></div>
                    </div>

                    <div id="batch-error" class="hidden p-3 bg-rose-50 border border-rose-200 rounded-xl text-xs text-rose-700 font-bold"></div>
                    <div id="batch-success" class="hidden p-3 bg-teal-50 border border-teal-200 rounded-xl text-xs text-teal-800 font-bold"></div>

                    <div class="flex items-center justify-end gap-3 pt-3 border-t border-gray-100">
                        <button type="button" onclick="closeAddStudentModal()" class="px-5 py-2.5 rounded-xl text-xs font-bold bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors">
                            انصراف
                        </button>
                        <button type="submit" id="btn-submit-batch" class="px-6 py-2.5 rounded-xl text-xs font-black bg-teal-600 hover:bg-teal-700 text-white shadow-md transition-all flex items-center gap-2">
                            <span>🚀</span>
                            <span>شروع پردازش و بارگذاری لیست</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openAddStudentModal() {
            const modal = document.getElementById('add-student-modal');
            if (modal) {
                modal.classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            }
        }

        function closeAddStudentModal() {
            const modal = document.getElementById('add-student-modal');
            if (modal) {
                modal.classList.add('hidden');
                document.body.style.overflow = '';
            }
        }

        function switchStudentTab(tab) {
            const btnSingle = document.getElementById('tab-btn-single');
            const btnBatch = document.getElementById('tab-btn-batch');
            const formSingle = document.getElementById('form-add-student');
            const formBatch = document.getElementById('form-batch-students');

            if (tab === 'single') {
                btnSingle.className = 'pb-3 px-4 text-xs font-black border-b-2 border-teal-600 text-teal-700 flex items-center gap-2 transition-all';
                btnBatch.className = 'pb-3 px-4 text-xs font-bold border-b-2 border-transparent text-gray-500 hover:text-gray-700 flex items-center gap-2 transition-all';
                formSingle.classList.remove('hidden');
                formBatch.classList.add('hidden');
            } else {
                btnBatch.className = 'pb-3 px-4 text-xs font-black border-b-2 border-teal-600 text-teal-700 flex items-center gap-2 transition-all';
                btnSingle.className = 'pb-3 px-4 text-xs font-bold border-b-2 border-transparent text-gray-500 hover:text-gray-700 flex items-center gap-2 transition-all';
                formBatch.classList.remove('hidden');
                formSingle.classList.add('hidden');
            }
        }

        function updateBatchFileName(input) {
            const nameEl = document.getElementById('batch-file-name');
            if (input.files && input.files[0]) {
                nameEl.textContent = 'فایل انتخاب شده: ' + input.files[0].name + ' (' + Math.round(input.files[0].size / 1024) + ' KB)';
                nameEl.classList.remove('hidden');
            } else {
                nameEl.classList.add('hidden');
            }
        }

        async function submitSingleStudent(event) {
            event.preventDefault();
            const btn = document.getElementById('btn-submit-single');
            const errEl = document.getElementById('single-error');
            errEl.classList.add('hidden');

            const form = event.target;
            const formData = new FormData(form);
            formData.append('action', 'add_student');

            btn.disabled = true;
            btn.innerHTML = '<span>⏳</span><span>در حال ذخیره و ثبت...</span>';

            try {
                const res = await fetch('admin-request-handler.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    alert(data.message || 'مددجوی جدید با موفقیت ثبت شد.');
                    window.location.reload();
                } else {
                    errEl.textContent = data.message || 'خطا در ثبت اطلاعات.';
                    errEl.classList.remove('hidden');
                }
            } catch (err) {
                errEl.textContent = 'خطا در برقراری ارتباط با سرور.';
                errEl.classList.remove('hidden');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<span>✓</span><span>ثبت و تشکیل پرونده مددجو</span>';
            }
        }

        async function submitBatchStudents(event) {
            event.preventDefault();
            const btn = document.getElementById('btn-submit-batch');
            const errEl = document.getElementById('batch-error');
            const successEl = document.getElementById('batch-success');
            const fileInput = document.getElementById('batch-file-input');

            errEl.classList.add('hidden');
            successEl.classList.add('hidden');

            if (!fileInput.files || fileInput.files.length === 0) {
                errEl.textContent = 'لطفاً ابتدا یک فایل اکسل یا CSV انتخاب نمایید.';
                errEl.classList.remove('hidden');
                return;
            }

            const formData = new FormData();
            formData.append('action', 'import_students_list');
            formData.append('file', fileInput.files[0]);

            btn.disabled = true;
            btn.innerHTML = '<span>⏳</span><span>در حال پردازش فایل و افزودن به سامانه...</span>';

            try {
                const res = await fetch('admin-request-handler.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await res.json();
                if (data.success) {
                    successEl.textContent = data.message || 'لیست مددجویان با موفقیت بارگذاری شد.';
                    successEl.classList.remove('hidden');
                    setTimeout(() => {
                        window.location.reload();
                    }, 1200);
                } else {
                    errEl.textContent = data.message || 'خطا در پردازش فایل.';
                    errEl.classList.remove('hidden');
                }
            } catch (err) {
                errEl.textContent = 'خطا در برقراری ارتباط با سرور.';
                errEl.classList.remove('hidden');
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<span>🚀</span><span>شروع پردازش و بارگذاری لیست</span>';
            }
        }

        // Auto-open modal if requested via URL parameters
        window.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('open_modal') === '1' || urlParams.get('action') === 'add') {
                openAddStudentModal();
            }
        });
    </script>
    <?php endif; ?>

<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/sw.js');
    });
  }
</script>

</body>
</html>
