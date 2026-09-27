<?php
session_start();
require_once '../includes/auth.php';

// هدایت یکپارچه به سامانه جامع کتابخانه، مخزن ۲۰۱ کتاب و صف‌های انتظار
header("Location: library.php", true, 302);
require_once __DIR__ . '/library.php';
exit();

// Handle Status & Admin Note Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($is_read_only) {
        $msg = 'اعضای محترم هیئت مدیره دارای دسترسی نظارتی و فقط خواندنی (Read-Only) می‌باشند.';
        $msg_type = 'error';
    } elseif ($_POST['action'] === 'update_request') {
        $req_id = (int)$_POST['req_id'];
        $new_status = $_POST['status'] ?? 'pending';
        $admin_notes = trim($_POST['admin_notes'] ?? '');
        
        $valid_statuses = ['pending', 'in_progress', 'fulfilled', 'rejected'];
        if (in_array($new_status, $valid_statuses, true)) {
            $stmt = $pdo->prepare("UPDATE student_book_requests SET status = ?, admin_notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$new_status, $admin_notes, $req_id]);
            
            $status_fa = [
                'pending' => 'در انتظار بررسی',
                'in_progress' => 'در حال تهیه و خرید',
                'fulfilled' => 'تهیه و تحویل شد',
                'rejected' => 'عدم تایید / ناموجود'
            ][$new_status] ?? $new_status;
            
            log_activity('به‌روزرسانی وضعیت کتاب', 'student_book_requests', $req_id, "تغییر وضعیت به [{$status_fa}]" . ($admin_notes ? " | یادداشت: {$admin_notes}" : ""));
            
            $msg = 'وضعیت درخواست کتاب با موفقیت به‌روزرسانی شد.';
            $msg_type = 'success';
        }
    } elseif ($_POST['action'] === 'delete_request') {
        $req_id = (int)$_POST['req_id'];
        $stmt = $pdo->prepare("DELETE FROM student_book_requests WHERE id = ?");
        $stmt->execute([$req_id]);
        
        log_activity('حذف درخواست کتاب', 'student_book_requests', $req_id, "حذف درخواست شماره {$req_id}");
        
        $msg = 'درخواست مورد نظر حذف شد.';
        $msg_type = 'success';
    }
}

// Filter and Search
$filter_status = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$sql = "SELECT r.*, s.name as student_name, s.surname as student_surname, s.grade as student_grade, s.phone as student_phone, s.guardian_phone 
        FROM student_book_requests r 
        JOIN students s ON r.student_id = s.id 
        WHERE 1=1";
$params = [];

if ($filter_status !== 'all') {
    $sql .= " AND r.status = ?";
    $params[] = $filter_status;
}

if (!empty($search)) {
    $sql .= " AND (r.book_title LIKE ? OR r.publisher LIKE ? OR s.name LIKE ? OR s.surname LIKE ?)";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
}

$sql .= " ORDER BY CASE r.priority WHEN 'urgent' THEN 1 ELSE 2 END, r.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Counts
$count_all = $pdo->query("SELECT COUNT(*) FROM student_book_requests")->fetchColumn();
$count_pending = $pdo->query("SELECT COUNT(*) FROM student_book_requests WHERE status = 'pending'")->fetchColumn();
$count_progress = $pdo->query("SELECT COUNT(*) FROM student_book_requests WHERE status = 'in_progress'")->fetchColumn();
$count_fulfilled = $pdo->query("SELECT COUNT(*) FROM student_book_requests WHERE status = 'fulfilled'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="fa-IR" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت درخواست‌های کتاب و ملزومات | بنیاد حکمت</title>
<style>
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:100;font-display:swap;src:url('/assets/fonts/Vazirmatn-100.woff2') format('woff2')}
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:300;font-display:swap;src:url('/assets/fonts/Vazirmatn-300.woff2') format('woff2')}
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:400;font-display:swap;src:url('/assets/fonts/Vazirmatn-400.woff2') format('woff2')}
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:500;font-display:swap;src:url('/assets/fonts/Vazirmatn-500.woff2') format('woff2')}
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:700;font-display:swap;src:url('/assets/fonts/Vazirmatn-700.woff2') format('woff2')}
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:900;font-display:swap;src:url('/assets/fonts/Vazirmatn-900.woff2') format('woff2')}
</style>
<link rel="stylesheet" href="/assets/tailwind.min.css">
<style>
        body { font-family: 'Vazirmatn', sans-serif; }
    </style>
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen">

    <!-- Header Navigation -->
    <header class="bg-slate-900 border-b border-white/10 sticky top-0 z-40">
        <div class="container mx-auto px-4 py-4 flex flex-col sm:flex-row justify-between items-center gap-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-teal-500/20 text-teal-400 border border-teal-500/30 rounded-xl flex items-center justify-center text-xl">
                    📚
                </div>
                <div>
                    <h1 class="text-base sm:text-lg font-black text-white">مدیریت درخواست‌های کتاب و ملزومات درسی</h1>
                    <p class="text-[11px] text-teal-300">معاونت آموزشی بنیاد (خانم فرتاش) و مدیریت محترم (آقای علمی)</p>
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-2.5">
                <a href="library.php" class="bg-amber-600 hover:bg-amber-500 text-white text-xs font-bold px-4 py-2 rounded-xl transition-all shadow-md flex items-center gap-1.5">
                    <span>🏛️</span> مخزن کتابخانه و صف انتظار
                </a>
                <?php if (($_SESSION['role'] ?? '') === 'data_operator' || strtolower($_SESSION['username'] ?? '') === 'viana'): ?>
                    <a href="../switch-role.php?target=student" class="bg-indigo-600 hover:bg-indigo-500 text-white text-xs font-bold px-3.5 py-2 rounded-xl transition-all shadow-md flex items-center gap-1.5">
                        <span>🎒</span> پورتال دانش‌آموزی ویانا
                    </a>
                <?php endif; ?>
                <?php if (($_SESSION['role'] ?? '') !== 'data_operator'): ?>
                    <a href="../people-list.php" class="bg-white/5 hover:bg-white/10 text-white border border-white/10 text-xs font-bold px-4 py-2 rounded-xl transition-all">
                        لیست دانش‌آموزان
                    </a>
                    <a href="index.php" class="bg-teal-600 hover:bg-teal-500 text-white text-xs font-bold px-4 py-2 rounded-xl transition-all shadow-md">
                        ← بازگشت به داشبورد مدیریت
                    </a>
                <?php else: ?>
                    <a href="../admin-logout.php" class="bg-rose-500/20 hover:bg-rose-500 text-rose-300 hover:text-white border border-rose-500/30 text-xs font-bold px-3 py-2 rounded-xl transition-all">
                        خروج
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8 max-w-7xl">
        
        <?php if ($msg): ?>
            <div class="mb-6 p-4 rounded-2xl border text-sm font-bold text-center <?php echo $msg_type === 'success' ? 'bg-emerald-500/15 border-emerald-500/30 text-emerald-300' : 'bg-rose-500/15 border-rose-500/30 text-rose-300'; ?>">
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <!-- Stats Overview Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
            <div class="bg-slate-900/80 border border-white/5 p-5 rounded-3xl text-center">
                <div class="text-[11px] text-slate-400 font-bold mb-1">کل درخواست‌ها</div>
                <div class="text-3xl font-black text-white font-mono"><?php echo $count_all; ?></div>
            </div>
            <div class="bg-amber-500/10 border border-amber-500/20 p-5 rounded-3xl text-center">
                <div class="text-[11px] text-amber-300 font-bold mb-1">در انتظار بررسی</div>
                <div class="text-3xl font-black text-amber-300 font-mono"><?php echo $count_pending; ?></div>
            </div>
            <div class="bg-blue-500/10 border border-blue-500/20 p-5 rounded-3xl text-center">
                <div class="text-[11px] text-blue-300 font-bold mb-1">در حال تهیه و خرید</div>
                <div class="text-3xl font-black text-blue-300 font-mono"><?php echo $count_progress; ?></div>
            </div>
            <div class="bg-emerald-500/10 border border-emerald-500/20 p-5 rounded-3xl text-center">
                <div class="text-[11px] text-emerald-300 font-bold mb-1">تهیه و تحویل‌شده</div>
                <div class="text-3xl font-black text-emerald-300 font-mono"><?php echo $count_fulfilled; ?></div>
            </div>
        </div>

        <!-- Filter & Search Bar -->
        <div class="bg-slate-900/90 border border-white/10 p-5 rounded-3xl mb-8 flex flex-col md:flex-row justify-between items-center gap-4">
            
            <!-- Status Tabs -->
            <div class="flex flex-wrap gap-2 text-xs font-bold w-full md:w-auto">
                <a href="book-requests.php?status=all<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
                   class="px-4 py-2 rounded-xl transition-all <?php echo $filter_status === 'all' ? 'bg-teal-600 text-white shadow-md' : 'bg-white/5 text-slate-400 hover:text-white'; ?>">
                    همه (<?php echo $count_all; ?>)
                </a>
                <a href="book-requests.php?status=pending<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
                   class="px-4 py-2 rounded-xl transition-all <?php echo $filter_status === 'pending' ? 'bg-amber-600 text-white shadow-md' : 'bg-white/5 text-slate-400 hover:text-white'; ?>">
                    در انتظار بررسی (<?php echo $count_pending; ?>)
                </a>
                <a href="book-requests.php?status=in_progress<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
                   class="px-4 py-2 rounded-xl transition-all <?php echo $filter_status === 'in_progress' ? 'bg-blue-600 text-white shadow-md' : 'bg-white/5 text-slate-400 hover:text-white'; ?>">
                    در حال تهیه (<?php echo $count_progress; ?>)
                </a>
                <a href="book-requests.php?status=fulfilled<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" 
                   class="px-4 py-2 rounded-xl transition-all <?php echo $filter_status === 'fulfilled' ? 'bg-emerald-600 text-white shadow-md' : 'bg-white/5 text-slate-400 hover:text-white'; ?>">
                    تحویل‌شده (<?php echo $count_fulfilled; ?>)
                </a>
            </div>

            <!-- Search Form -->
            <form method="GET" action="" class="w-full md:w-80 flex gap-2">
                <input type="hidden" name="status" value="<?php echo htmlspecialchars($filter_status); ?>">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="جستجوی کتاب یا دانش‌آموز..."
                    class="flex-1 bg-white/5 border border-white/10 text-white placeholder-slate-500 rounded-xl px-4 py-2 text-xs focus:outline-none focus:border-teal-500">
                <button type="submit" class="bg-teal-600 hover:bg-teal-500 text-white px-4 py-2 rounded-xl text-xs font-bold transition-colors">
                    جستجو
                </button>
                <?php if ($search): ?>
                    <a href="book-requests.php?status=<?php echo htmlspecialchars($filter_status); ?>" class="bg-white/10 hover:bg-white/20 text-slate-300 px-3 py-2 rounded-xl text-xs font-bold">✕</a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Requests List Table -->
        <?php if (empty($requests)): ?>
            <div class="text-center py-16 bg-slate-900/50 rounded-3xl border border-white/5">
                <div class="text-5xl mb-4">📚</div>
                <div class="text-base font-bold text-white mb-1">درخواستی با این مشخصات یافت نشد.</div>
                <p class="text-xs text-slate-500">دانش‌آموزان از طریق پورتال شخصی خود می‌توانند کتب مورد نیاز را ثبت کنند.</p>
            </div>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($requests as $req): ?>
                    <?php 
                    $st = $req['status'];
                    $stBadge = 'bg-amber-500/15 text-amber-300 border-amber-500/30';
                    $stName = 'در انتظار بررسی';
                    if ($st === 'in_progress') {
                        $stBadge = 'bg-blue-500/15 text-blue-300 border-blue-500/30';
                        $stName = 'در حال تهیه و خرید';
                    } elseif ($st === 'fulfilled') {
                        $stBadge = 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30';
                        $stName = 'تهیه و تحویل شد';
                    } elseif ($st === 'rejected') {
                        $stBadge = 'bg-rose-500/15 text-rose-300 border-rose-500/30';
                        $stName = 'عدم تایید / ناموجود';
                    }
                    ?>
                    <div class="bg-slate-900/80 border border-white/10 rounded-3xl p-6 transition-all hover:border-teal-500/30">
                        <div class="flex flex-col lg:flex-row justify-between items-start lg:items-center gap-4 mb-4 pb-4 border-b border-white/5">
                            <div>
                                <div class="flex items-center gap-3 mb-1">
                                    <h3 class="text-lg font-black text-white"><?php echo htmlspecialchars($req['book_title']); ?></h3>
                                    <?php if ($req['priority'] === 'urgent'): ?>
                                        <span class="bg-rose-500/20 text-rose-300 border border-rose-500/40 text-[10px] font-black px-2.5 py-0.5 rounded-full animate-pulse">
                                            🚨 فوری / کنکوری
                                        </span>
                                    <?php else: ?>
                                        <span class="bg-white/5 text-slate-400 text-[10px] font-bold px-2 py-0.5 rounded-full">
                                            عادی
                                        </span>
                                    <?php endif; ?>
                                    <span class="text-[11px] font-bold px-3 py-1 rounded-full border <?php echo $stBadge; ?>">
                                        <?php echo $stName; ?>
                                    </span>
                                </div>
                                <div class="flex flex-wrap items-center gap-4 text-xs text-slate-400">
                                    <span>دانش‌پژوه: <strong class="text-white"><?php echo htmlspecialchars($req['student_name'] . ' ' . $req['student_surname']); ?></strong> (پایه <?php echo htmlspecialchars($req['student_grade'] ?: '---'); ?>)</span>
                                    <span>ناشر: <strong class="text-teal-300"><?php echo htmlspecialchars($req['publisher'] ?: 'نامشخص'); ?></strong></span>
                                    <span>رشته/درس: <strong class="text-slate-200"><?php echo htmlspecialchars($req['subject_area'] ?: 'عمومی'); ?></strong></span>
                                    <span>تاریخ: <span class="font-mono text-slate-300" dir="ltr"><?php echo substr($req['created_at'], 0, 10); ?></span></span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 shrink-0">
                                <a href="../student-dashboard.php?student_id=<?php echo $req['student_id']; ?>" target="_blank" class="bg-teal-500/20 hover:bg-teal-500/30 text-teal-300 border border-teal-500/30 px-3 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5">
                                    <span>👁️</span> مشاهده پورتال
                                </a>
                                <a href="../person-detail.php?id=<?php echo $req['student_id']; ?>" target="_blank" class="bg-white/5 hover:bg-white/10 text-slate-300 border border-white/10 px-3 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5">
                                    <span>📄</span> پرونده اداری
                                </a>
                            </div>
                        </div>

                        <!-- Notes by Student -->
                        <?php if (!empty($req['notes'])): ?>
                            <div class="text-xs text-slate-300 bg-black/30 p-3 rounded-2xl mb-4 border border-white/5">
                                <span class="text-slate-500 text-[10px] block mb-0.5">یادداشت و آدرس دانش‌آموز:</span>
                                <?php echo htmlspecialchars($req['notes']); ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!$is_read_only): ?>
                        <!-- Status & Admin Notes Form -->
                        <form method="POST" action="" class="bg-white/5 p-4 rounded-2xl border border-white/5 flex flex-col md:flex-row items-stretch md:items-center gap-3">
                            <input type="hidden" name="action" value="update_request">
                            <input type="hidden" name="req_id" value="<?php echo $req['id']; ?>">

                            <div class="w-full md:w-56">
                                <label class="text-[10px] text-slate-400 font-bold block mb-1">وضعیت تهیه:</label>
                                <select name="status" class="w-full bg-slate-800 border border-white/20 text-white rounded-xl px-3 py-2 text-xs font-bold focus:outline-none focus:border-teal-500">
                                    <option value="pending" <?php echo $st === 'pending' ? 'selected' : ''; ?>>🟡 در انتظار بررسی</option>
                                    <option value="in_progress" <?php echo $st === 'in_progress' ? 'selected' : ''; ?>>🔵 در حال تهیه و خرید</option>
                                    <option value="fulfilled" <?php echo $st === 'fulfilled' ? 'selected' : ''; ?>>🟢 تهیه و تحویل شد</option>
                                    <option value="rejected" <?php echo $st === 'rejected' ? 'selected' : ''; ?>>🔴 عدم تایید / ناموجود</option>
                                </select>
                            </div>

                            <div class="flex-1">
                                <label class="text-[10px] text-slate-400 font-bold block mb-1">یادداشت اداری / پیام به دانش‌آموز (کد مرسوله، هماهنگی یا توضیح):</label>
                                <input type="text" name="admin_notes" value="<?php echo htmlspecialchars($req['admin_notes'] ?? ''); ?>" placeholder="مثلاً: از نمایشگاه کتاب خریداری شد و تا دوشنبه تحویل می‌گردد..."
                                    class="w-full bg-slate-800 border border-white/20 text-white placeholder-slate-500 rounded-xl px-4 py-2 text-xs focus:outline-none focus:border-teal-500">
                            </div>

                            <div class="flex items-end gap-2 pt-2 md:pt-4">
                                <button type="submit" class="bg-teal-600 hover:bg-teal-500 text-white font-bold px-5 py-2 rounded-xl text-xs transition-colors shadow-md shrink-0">
                                    💾 ذخیره تغییرات
                                </button>
                            </div>
                        </form>
                        <?php else: ?>
                            <?php if (!empty($req['admin_notes'])): ?>
                                <div class="bg-white/5 p-3.5 rounded-2xl border border-white/5 text-xs text-teal-200">
                                    <span class="text-teal-400 font-bold block text-[10px] mb-1">پاسخ ثبت‌شده مدیریت / خانم فرتاش:</span>
                                    <?php echo htmlspecialchars($req['admin_notes']); ?>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

    </main>

</body>
</html>
