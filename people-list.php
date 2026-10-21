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

// Fetch students with their active sponsors
$query = "
    SELECT st.*,
           COUNT(s.id) as sponsors_count,
           GROUP_CONCAT(s.id || ':::' || d.id || ':::' || d.name || ' ' || COALESCE(d.surname, '') || ':::' || s.shares_count, '|||') as sponsors_raw
    FROM students st
    LEFT JOIN sponsorships s ON s.student_id = st.id AND s.status = 'active'
    LEFT JOIN donors d ON s.donor_id = d.id
    GROUP BY st.id
    ORDER BY st.id ASC
";
$stmt = $pdo->query($query);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch All Donors for Assignment Modal (Those with fewer students first)
$all_donors_for_modal = $pdo->query("
    SELECT d.id, d.name, d.surname, d.phone,
           (SELECT COUNT(*) FROM sponsorships s WHERE s.donor_id = d.id AND s.status = 'active') as active_students_count
    FROM donors d
    ORDER BY active_students_count ASC, d.total_donated DESC
")->fetchAll(PDO::FETCH_ASSOC);

$count_all = count($students);
$count_active = 0;
$count_graduated = 0;
$count_university = 0;
$count_exited = 0;
$count_sponsored = 0;
$count_unsponsored = 0;

foreach ($students as $s) {
    $st = $s['status'] ?: 'active';
    if ($st === 'active') $count_active++;
    elseif ($st === 'graduated') $count_graduated++;
    elseif ($st === 'university') $count_university++;
    elseif ($st === 'exited') $count_exited++;
    else $count_active++;

    if ((int)($s['sponsors_count'] ?? 0) > 0) {
        $count_sponsored++;
    } else {
        $count_unsponsored++;
    }
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

    <?php 
    $page_nav_title = 'مدیریت مددجویان و دانش‌آموزان';
    include 'includes/admin-navbar.php'; 
    ?>

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
        <!-- Status & Sponsorship Filter Tabs / Pills -->
        <div class="flex flex-wrap items-center justify-between gap-3 mb-6 bg-white/80 backdrop-blur-md p-3 rounded-3xl shadow-sm border border-gray-100">
            <div class="flex flex-wrap items-center gap-2">
                <button onclick="setFilterTab('all')" data-tab="all" class="filter-tab active px-4 py-2 rounded-2xl text-xs font-black transition-all bg-primary-900 text-white shadow-md">
                    همه مددجویان <span class="opacity-80 font-normal mr-1">(<?php echo toFarsiDigits($count_all); ?>)</span>
                </button>
                <button onclick="setFilterTab('active')" data-tab="active" class="filter-tab px-4 py-2 rounded-2xl text-xs font-black transition-all bg-gray-100 text-gray-600 hover:bg-teal-50 hover:text-teal-700">
                    دانش‌آموزان <span class="opacity-80 font-normal mr-1">(<?php echo toFarsiDigits($count_active); ?>)</span>
                </button>
                <button onclick="setFilterTab('university')" data-tab="university" class="filter-tab px-4 py-2 rounded-2xl text-xs font-black transition-all bg-gray-100 text-gray-600 hover:bg-blue-50 hover:text-blue-700">
                    دانشجویان <span class="opacity-80 font-normal mr-1">(<?php echo toFarsiDigits($count_university); ?>)</span>
                </button>
                <button onclick="setFilterTab('graduated')" data-tab="graduated" class="filter-tab px-4 py-2 rounded-2xl text-xs font-black transition-all bg-gray-100 text-gray-600 hover:bg-purple-50 hover:text-purple-700">
                    فارغ‌التحصیلان <span class="opacity-80 font-normal mr-1">(<?php echo toFarsiDigits($count_graduated); ?>)</span>
                </button>
                <button onclick="setFilterTab('exited')" data-tab="exited" class="filter-tab px-4 py-2 rounded-2xl text-xs font-black transition-all bg-gray-100 text-gray-600 hover:bg-rose-50 hover:text-rose-700">
                    خروج از بورس <span class="opacity-80 font-normal mr-1">(<?php echo toFarsiDigits($count_exited); ?>)</span>
                </button>
            </div>

            <!-- Quick Sponsor Status Buttons -->
            <div class="flex items-center gap-2 border-r pr-3 border-gray-200">
                <button type="button" onclick="setSponsorQuickFilter('sponsored', this)" class="sponsor-filter-btn px-3 py-1.5 rounded-xl text-xs font-black transition-all bg-emerald-50 text-emerald-800 hover:bg-emerald-100 border border-emerald-200 shrink-0">
                    💚 دارای حامی (<?php echo toFarsiDigits($count_sponsored); ?>)
                </button>
                <button type="button" onclick="setSponsorQuickFilter('unsponsored', this)" class="sponsor-filter-btn px-3 py-1.5 rounded-xl text-xs font-black transition-all bg-amber-50 text-amber-800 hover:bg-amber-100 border border-amber-200 shrink-0">
                    ⏳ نیازمند اسپانسر (<?php echo toFarsiDigits($count_unsponsored); ?>)
                </button>
            </div>
        </div>

        <!-- Search & Stats Bar -->
        <div class="bg-white rounded-3xl shadow-xl p-6 mb-8 flex flex-col md:flex-row justify-between items-center gap-6 border border-gray-100">
            <div class="flex flex-col md:flex-row gap-3 w-full md:w-auto flex-1">
                <div class="relative w-full md:w-72">
                    <input type="text" id="searchInput" placeholder="جستجوی نام، کد مددجو یا حامی..." 
                        class="w-full bg-gray-50 border border-gray-200 rounded-2xl px-12 py-3 focus:outline-none focus:ring-2 focus:ring-primary-600 transition-all text-xs font-bold">
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400">🔍</span>
                </div>
                <div class="relative w-full md:w-48">
                    <select id="statusFilter" class="w-full bg-gray-50 border border-gray-200 rounded-2xl px-3 py-3 focus:outline-none focus:ring-2 focus:ring-primary-600 transition-all text-xs appearance-none text-gray-700 font-bold cursor-pointer">
                        <option value="all">وضعیت تحصیلی (همه)</option>
                        <option value="active">دانش‌آموزان تحت پوشش (<?php echo toFarsiDigits($count_active); ?>)</option>
                        <option value="university">دانشجویان تحت پوشش (<?php echo toFarsiDigits($count_university); ?>)</option>
                        <option value="graduated">فارغ‌التحصیلان (<?php echo toFarsiDigits($count_graduated); ?>)</option>
                        <option value="exited">خروج از بورس (<?php echo toFarsiDigits($count_exited); ?>)</option>
                    </select>
                </div>
                <div class="relative w-full md:w-48">
                    <select id="sponsorFilter" class="w-full bg-gray-50 border border-gray-200 rounded-2xl px-3 py-3 focus:outline-none focus:ring-2 focus:ring-primary-600 transition-all text-xs appearance-none text-gray-700 font-bold cursor-pointer">
                        <option value="all">وضعیت حامی (همه)</option>
                        <option value="sponsored">💚 دارای حامی مالی (<?php echo toFarsiDigits($count_sponsored); ?>)</option>
                        <option value="unsponsored">⏳ بدون حامی / نیازمند (<?php echo toFarsiDigits($count_unsponsored); ?>)</option>
                    </select>
                </div>
                <div class="relative w-full md:w-36">
                    <select id="genderFilter" class="w-full bg-gray-50 border border-gray-200 rounded-2xl px-3 py-3 focus:outline-none focus:ring-2 focus:ring-primary-600 transition-all text-xs appearance-none text-gray-700 font-bold cursor-pointer">
                        <option value="all">جنسیت (همه)</option>
                        <option value="Male">پسران</option>
                        <option value="Female">دختران</option>
                    </select>
                </div>
            </div>
            
            <div class="flex flex-wrap gap-5 items-center justify-center border-t md:border-t-0 md:border-r pt-4 md:pt-0 pr-0 md:pr-6 border-gray-100">
                <div class="text-center">
                    <div class="text-xl md:text-2xl font-black text-emerald-600 font-mono"><?php echo toFarsiDigits($count_sponsored); ?></div>
                    <div class="text-[9px] text-gray-400 font-bold">دارای حامی مالی</div>
                </div>
                <div class="w-px h-8 bg-gray-100"></div>
                <div class="text-center">
                    <div class="text-xl md:text-2xl font-black text-amber-500 font-mono"><?php echo toFarsiDigits($count_unsponsored); ?></div>
                    <div class="text-[9px] text-gray-400 font-bold">نیازمند حامی</div>
                </div>
                <div class="w-px h-8 bg-gray-100"></div>
                <div class="text-center">
                    <div class="text-xl md:text-2xl font-black text-teal-600 font-mono"><?php echo toFarsiDigits($count_active + $count_university); ?></div>
                    <div class="text-[9px] text-gray-400 font-bold">کل تحت پوشش</div>
                </div>
                <div class="w-px h-8 bg-gray-100"></div>
                <div class="text-center">
                    <div class="text-xl md:text-2xl font-black text-primary-900 font-mono"><?php echo toFarsiDigits($count_all); ?></div>
                    <div class="text-[9px] text-gray-400 font-bold">کل پرونده‌ها</div>
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

                // Parse sponsors
                $sponsors_count = (int)($p['sponsors_count'] ?? 0);
                $sponsors = [];
                $sponsor_names = [];
                if (!empty($p['sponsors_raw'])) {
                    $sp_items = explode('|||', $p['sponsors_raw']);
                    foreach ($sp_items as $item) {
                        $parts = explode(':::', $item);
                        if (count($parts) >= 4) {
                            $sponsors[] = [
                                'sponsorship_id' => (int)$parts[0],
                                'donor_id' => (int)$parts[1],
                                'name' => trim($parts[2]),
                                'shares' => (int)$parts[3]
                            ];
                            $sponsor_names[] = trim($parts[2]);
                        }
                    }
                }
                $is_sponsored = ($sponsors_count > 0);
                $student_full_name = trim($p['name'] . ' ' . $p['surname']);
            ?>
            <div class="people-card <?php echo $card_style; ?> rounded-[2.5rem] p-6 shadow-sm hover:shadow-xl transition-all group relative overflow-hidden flex flex-col justify-between" 
                 data-status="<?php echo $status; ?>" 
                 data-gender="<?php echo htmlspecialchars($p['gender'] ?? 'all'); ?>"
                 data-sponsored="<?php echo $is_sponsored ? '1' : '0'; ?>"
                 data-search="<?php echo htmlspecialchars($student_full_name . ' ' . $p['code'] . ' ' . $p['national_id'] . ' ' . ($p['school'] ?? '') . ' ' . ($p['language_class'] ?? '') . ' ' . implode(' ', $sponsor_names)); ?>">
                <div class="absolute top-0 right-0 w-24 h-24 bg-teal-500/5 rounded-full -mr-10 -mt-10 group-hover:scale-150 transition-transform"></div>
                
                <div>
                    <div class="flex items-center justify-between mb-4 relative z-10 w-full">
                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-black border <?php echo $curr_meta['badge_class']; ?>">
                            <span class="w-1.5 h-1.5 rounded-full <?php echo $curr_meta['dot']; ?>"></span>
                            <?php echo $curr_meta['label']; ?>
                        </span>
                        <span class="text-[10px] text-gray-400 font-mono font-bold">#<?php echo toFarsiDigits($p['code']); ?></span>
                    </div>

                    <div class="flex flex-col items-center text-center">
                        <?php 
                        $has_photo = !empty($p['photo_path']) && (file_exists(__DIR__ . '/' . $p['photo_path']) || file_exists($p['photo_path']));
                        $initial_letter = mb_substr(trim($p['name'] ?: ($p['surname'] ?: '؟')), 0, 1);
                        ?>
                        <a href="person-detail.php?id=<?php echo $p['id']; ?>" class="block group/avatar mb-3 relative shrink-0" title="مشاهده پرونده <?php echo htmlspecialchars($student_full_name); ?>">
                            <?php if ($has_photo): ?>
                                <div class="w-20 h-20 rounded-[1.5rem] overflow-hidden shadow-md border-2 border-white ring-2 ring-teal-500/20 group-hover/avatar:ring-teal-500 group-hover/avatar:scale-105 transition-all bg-teal-50 relative shrink-0">
                                    <img src="<?php echo htmlspecialchars($p['photo_path']); ?>" 
                                         alt="<?php echo htmlspecialchars($student_full_name); ?>" 
                                         class="w-full h-full object-cover <?php echo $is_inactive ? 'grayscale opacity-75' : ''; ?>"
                                         loading="lazy"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div class="hidden w-full h-full <?php echo $icon_style; ?> items-center justify-center text-3xl font-black">
                                        <?php echo $initial_letter; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="w-20 h-20 <?php echo $icon_style; ?> rounded-[1.5rem] flex items-center justify-center text-3xl font-black shadow-inner group-hover/avatar:scale-105 transition-transform shrink-0">
                                    <?php echo $initial_letter; ?>
                                </div>
                            <?php endif; ?>
                        </a>
                        
                        <a href="person-detail.php?id=<?php echo $p['id']; ?>" class="hover:text-teal-600 transition-colors">
                            <h3 class="text-base font-black text-primary-900 mb-0.5 hover:text-teal-600 transition-colors"><?php echo htmlspecialchars($student_full_name); ?></h3>
                        </a>
                        <?php if (!empty($p['alias_name'])): ?>
                        <p class="text-[10px] text-teal-600 font-bold mb-2 bg-teal-50/80 px-3 py-0.5 rounded-full"><?php echo htmlspecialchars($p['alias_name']); ?></p>
                        <?php else: ?>
                        <div class="mb-2"></div>
                        <?php endif; ?>

                        <?php if (!empty($p['school'])): ?>
                        <div class="text-[10px] text-gray-500 font-bold mb-3 flex items-center justify-center gap-1">
                            <span>🏫</span> <span><?php echo htmlspecialchars($p['school']); ?></span>
                            <?php if (!empty($p['language_class'])): ?>
                            <span class="text-[9px] bg-teal-50 text-teal-700 font-bold px-2 py-0.5 rounded-full border border-teal-200 mr-1">🗣️ زبان</span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="w-full grid grid-cols-2 gap-2 mb-3">
                            <div class="bg-gray-50 p-2.5 rounded-2xl text-right">
                                <div class="text-[8px] text-gray-400 font-bold">نام پدر</div>
                                <div class="text-[10px] font-black text-primary-900 truncate"><?php echo htmlspecialchars($p['father_name'] ?: '---'); ?></div>
                            </div>
                            <div class="bg-gray-50 p-2.5 rounded-2xl text-right">
                                <div class="text-[8px] text-gray-400 font-bold">پایه / رشته</div>
                                <div class="text-[10px] font-black text-primary-900 truncate"><?php echo htmlspecialchars(($p['grade'] ? $p['grade'] . ' ' : '') . ($p['field_of_study'] ?: '')); ?></div>
                            </div>
                        </div>

                        <!-- SPONSORSHIP STATUS BOX (کی به کی وصله) -->
                        <div class="w-full mb-3 text-right">
                            <?php if ($is_sponsored): ?>
                                <div class="bg-gradient-to-r from-emerald-50/90 to-teal-50/90 border border-emerald-200/90 rounded-2xl p-2.5">
                                    <div class="flex items-center justify-between mb-1.5">
                                        <span class="text-[10px] font-black text-emerald-900 flex items-center gap-1">
                                            <span>💚</span>
                                            <span>حامی معنوی:</span>
                                        </span>
                                        <button type="button" 
                                                onclick='openStudentAssignModal(<?php echo $p["id"]; ?>, "<?php echo addslashes($student_full_name); ?>")' 
                                                class="text-[9px] font-bold text-teal-700 hover:text-teal-900 bg-white/80 hover:bg-white px-2 py-0.5 rounded-lg border border-teal-200 transition-all">
                                            + حامی دیگر
                                        </button>
                                    </div>
                                    <div class="flex flex-wrap gap-1">
                                        <?php foreach ($sponsors as $sp): ?>
                                            <a href="donor-detail.php?id=<?php echo $sp['donor_id']; ?>" 
                                               class="inline-flex items-center gap-1 bg-white px-2 py-1 rounded-xl border border-emerald-200 text-[10px] font-black text-emerald-800 hover:text-teal-600 shadow-xs transition-colors"
                                               title="مشاهده پرونده نیکوکار">
                                                <span>🤝</span>
                                                <span><?php echo htmlspecialchars($sp['name']); ?></span>
                                                <?php if ($sp['shares'] > 1): ?>
                                                    <span class="text-[8px] bg-emerald-100 text-emerald-800 font-black px-1 rounded-md font-mono"><?php echo toFarsiDigits($sp['shares']); ?> سهم</span>
                                                <?php endif; ?>
                                            </a>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="bg-amber-50/70 border border-amber-200/90 rounded-2xl p-2.5 flex items-center justify-between">
                                    <div>
                                        <div class="text-[10px] font-black text-amber-900 flex items-center gap-1">
                                            <span>⏳</span>
                                            <span>بدون حامی مالی</span>
                                        </div>
                                        <div class="text-[8px] text-amber-700 font-bold">نیازمند اختصاص اسپانسر</div>
                                    </div>
                                    <button type="button" 
                                            onclick='openStudentAssignModal(<?php echo $p["id"]; ?>, "<?php echo addslashes($student_full_name); ?>")'
                                            class="bg-amber-500 hover:bg-amber-600 text-slate-950 font-black text-[9px] px-2.5 py-1.5 rounded-xl shadow-xs transition-all flex items-center gap-1">
                                        <span>+</span>
                                        <span>تخصیص حامی</span>
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="flex w-full gap-2 pt-2 border-t border-gray-100">
                    <a href="person-detail.php?id=<?php echo $p['id']; ?>" class="flex-1 bg-primary-900 text-white py-2.5 rounded-xl text-xs font-black shadow-sm hover:bg-teal-600 transition-all text-center">مشاهده پرونده</a>
                    <button onclick="confirmDelete(<?php echo $p['id']; ?>)" class="w-10 bg-red-50 text-red-500 rounded-xl flex items-center justify-center hover:bg-red-500 hover:text-white transition-all text-xs" title="حذف پرونده">🗑️</button>
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
        const sponsorFilter = document.getElementById('sponsorFilter');
        const genderFilter = document.getElementById('genderFilter');
        const peopleGrid = document.getElementById('peopleGrid');
        const cards = document.querySelectorAll('.people-card');
        const noResults = document.getElementById('noResults');
        const filterTabs = document.querySelectorAll('.filter-tab');

        function setFilterTab(status) {
            statusFilter.value = status;
            filterTabs.forEach(tab => {
                if (tab.getAttribute('data-tab') === status) {
                    tab.className = 'filter-tab active px-4 py-2 rounded-2xl text-xs font-black transition-all bg-primary-900 text-white shadow-md';
                } else {
                    tab.className = 'filter-tab px-4 py-2 rounded-2xl text-xs font-black transition-all bg-gray-100 text-gray-600 hover:bg-gray-200';
                }
            });
            filterCards();
        }

        function setSponsorQuickFilter(type, btn) {
            if (sponsorFilter.value === type) {
                sponsorFilter.value = 'all';
                btn.classList.remove('ring-2', 'ring-primary-900', 'shadow-md');
            } else {
                sponsorFilter.value = type;
                document.querySelectorAll('.sponsor-filter-btn').forEach(b => b.classList.remove('ring-2', 'ring-primary-900', 'shadow-md'));
                btn.classList.add('ring-2', 'ring-primary-900', 'shadow-md');
            }
            filterCards();
        }

        function filterCards() {
            const query = searchInput.value.toLowerCase().trim();
            const selectedStatus = statusFilter.value;
            const selectedSponsor = sponsorFilter ? sponsorFilter.value : 'all';
            const selectedGender = genderFilter ? genderFilter.value : 'all';
            let hasResults = false;

            // Sync tab active state
            filterTabs.forEach(tab => {
                if (tab.getAttribute('data-tab') === selectedStatus) {
                    tab.className = 'filter-tab active px-4 py-2 rounded-2xl text-xs font-black transition-all bg-primary-900 text-white shadow-md';
                } else {
                    tab.className = 'filter-tab px-4 py-2 rounded-2xl text-xs font-black transition-all bg-gray-100 text-gray-600 hover:bg-gray-200';
                }
            });

            cards.forEach(card => {
                const searchData = (card.getAttribute('data-search') || '').toLowerCase();
                const cardStatus = card.getAttribute('data-status');
                const cardGender = card.getAttribute('data-gender');
                const isSponsored = card.getAttribute('data-sponsored') === '1';
                
                const matchesSearch = !query || searchData.includes(query);
                const matchesStatus = (selectedStatus === 'all') || (cardStatus === selectedStatus);
                const matchesGender = (selectedGender === 'all') || (cardGender === selectedGender);
                
                let matchesSponsor = true;
                if (selectedSponsor === 'sponsored') matchesSponsor = isSponsored;
                else if (selectedSponsor === 'unsponsored') matchesSponsor = !isSponsored;

                if (matchesSearch && matchesStatus && matchesGender && matchesSponsor) {
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
        if(sponsorFilter) sponsorFilter.addEventListener('change', () => {
            document.querySelectorAll('.sponsor-filter-btn').forEach(b => b.classList.remove('ring-2', 'ring-primary-900', 'shadow-md'));
            filterCards();
        });
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

        // ==========================================
        // ASSIGN SPONSOR TO STUDENT MODAL LOGIC (کی به کی وصله)
        // ==========================================
        const studentAssignModal = document.getElementById('student-assign-modal');
        const studentAssignAlert = document.getElementById('student-assign-alert');

        function openStudentAssignModal(studentId, studentName) {
            document.getElementById('assign-student-id-field').value = studentId;
            document.getElementById('assign-student-name-disp').innerText = 'دانش‌آموز: ' + studentName;
            document.getElementById('assign-donor-id-field').value = '';
            document.getElementById('assign-shares-field').value = '1';
            studentAssignAlert.classList.add('hidden');
            studentAssignModal.classList.remove('hidden');
        }

        function closeStudentAssignModal() {
            studentAssignModal.classList.add('hidden');
        }

        async function submitStudentAssign() {
            const donorId = document.getElementById('assign-donor-id-field').value;
            const studentId = document.getElementById('assign-student-id-field').value;
            const shares = document.getElementById('assign-shares-field').value;

            if (!donorId) {
                studentAssignAlert.className = 'p-3 rounded-xl text-xs font-bold text-center bg-rose-50 text-rose-700 border border-rose-200 block';
                studentAssignAlert.innerText = 'لطفاً یک نیکوکار/حامی را انتخاب کنید.';
                return;
            }

            const btn = document.getElementById('btnSubmitStudentAssign');
            btn.disabled = true;
            btn.innerText = 'در حال ثبت اتصال...';

            const formData = new FormData();
            formData.append('action', 'assign_student_to_donor');
            formData.append('donor_id', donorId);
            formData.append('student_id', studentId);
            formData.append('shares', shares);

            try {
                const res = await fetch('admin-request-handler.php', { method: 'POST', body: formData });
                const data = await res.json();
                if (data.success) {
                    studentAssignAlert.className = 'p-3 rounded-xl text-xs font-bold text-center bg-emerald-50 text-emerald-700 border border-emerald-200 block';
                    studentAssignAlert.innerText = '✓ ' + data.message;
                    setTimeout(() => location.reload(), 1200);
                } else {
                    studentAssignAlert.className = 'p-3 rounded-xl text-xs font-bold text-center bg-rose-50 text-rose-700 border border-rose-200 block';
                    studentAssignAlert.innerText = '✕ ' + (data.message || 'خطا در تخصیص');
                    btn.disabled = false;
                    btn.innerText = 'ثبت و اتصال حامی مالی';
                }
            } catch (err) {
                studentAssignAlert.className = 'p-3 rounded-xl text-xs font-bold text-center bg-rose-50 text-rose-700 border border-rose-200 block';
                studentAssignAlert.innerText = 'خطا در ارتباط با سرور.';
                btn.disabled = false;
                btn.innerText = 'ثبت و اتصال حامی مالی';
            }
        }
    </script>

    <!-- Modal: تخصیص حامی مالی به دانش‌آموز -->
    <div id="student-assign-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm p-4 hidden">
        <div class="bg-white w-full max-w-md rounded-[2.5rem] shadow-2xl p-7 border border-gray-100 relative max-h-[90vh] overflow-y-auto">
            <div class="flex justify-between items-center mb-5 pb-4 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl font-black">
                        🤝
                    </div>
                    <div>
                        <h3 class="text-base font-black text-gray-900">اتصال حامی مالی به دانش‌آموز</h3>
                        <p class="text-[11px] text-gray-500" id="assign-student-name-disp">---</p>
                    </div>
                </div>
                <button type="button" onclick="closeStudentAssignModal()" class="text-gray-400 hover:text-gray-700 text-xl font-bold">✕</button>
            </div>

            <form id="studentAssignForm" class="space-y-4">
                <input type="hidden" id="assign-student-id-field" value="">

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">انتخاب نیکوکار / حامی مالی</label>
                    <select id="assign-donor-id-field" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-3 py-2.5 text-xs font-bold text-gray-800 focus:outline-none focus:border-emerald-500">
                        <option value="">-- لطفاً نیکوکار مورد نظر را انتخاب کنید --</option>
                        <?php foreach ($all_donors_for_modal as $dn_opt): ?>
                            <?php 
                                $st_count = (int)$dn_opt['active_students_count'];
                                $dn_badge = ($st_count === 0) ? '⭐ ظرفیت آزاد (بدون دانش‌آموز)' : "({$st_count} دانش‌آموز)";
                                $phone_show = $dn_opt['phone'] ? ' - ' . $dn_opt['phone'] : '';
                            ?>
                            <option value="<?php echo $dn_opt['id']; ?>">
                                <?php echo htmlspecialchars($dn_opt['name'] . ' ' . $dn_opt['surname'] . $phone_show); ?> 
                                [<?php echo $dn_badge; ?>]
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">تعداد سهم حمایت مالی</label>
                    <input type="number" id="assign-shares-field" value="1" min="1" max="100" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-xs font-mono font-bold text-center focus:outline-none focus:border-emerald-500">
                    <p class="text-[10px] text-gray-400 mt-1">هر سهم معادل شهریه بورس ماهانه یک دانش‌آموز است.</p>
                </div>

                <!-- Response Alert -->
                <div id="student-assign-alert" class="hidden p-3 rounded-xl text-xs font-bold text-center"></div>

                <!-- Action Buttons -->
                <div class="flex items-center gap-2 pt-2 border-t border-gray-100">
                    <button type="button" id="btnSubmitStudentAssign" onclick="submitStudentAssign()" class="flex-1 py-3 px-4 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-black transition-all shadow-md">
                        ثبت و اتصال حامی مالی
                    </button>
                    <button type="button" onclick="closeStudentAssignModal()" class="py-3 px-4 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl text-xs font-bold transition-all">
                        انصراف
                    </button>
                </div>
            </form>
        </div>
    </div>

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
