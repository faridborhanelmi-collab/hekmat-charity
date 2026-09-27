<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Auth Guard - Accessible by all board members, CEO, education deputy, admin
require_role(['superadmin', 'secretary', 'education_deputy', 'board_member', 'admin']);

$msg = '';
$msg_type = '';
$is_read_only = is_read_only();

// Handle Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($is_read_only) {
        $msg = 'دسترسی فقط خواندنی است.';
        $msg_type = 'error';
    } elseif ($_POST['action'] === 'update_status') {
        $cand_id = (int)$_POST['cand_id'];
        $new_status = $_POST['status'] ?? 'pending';
        
        $valid_statuses = ['pending', 'invited_stage2', 'interview_stage3', 'accepted_bursary', 'rejected'];
        if (in_array($new_status, $valid_statuses, true)) {
            $stmt = $pdo->prepare("UPDATE diamond_candidates SET status = ? WHERE id = ?");
            $stmt->execute([$new_status, $cand_id]);
            $msg = 'وضعیت داوطلب با موفقیت به‌روزرسانی شد.';
            $msg_type = 'success';
        }
    } elseif ($_POST['action'] === 'delete_candidate') {
        $cand_id = (int)$_POST['cand_id'];
        $stmt = $pdo->prepare("DELETE FROM diamond_candidates WHERE id = ?");
        $stmt->execute([$cand_id]);
        $msg = 'رکورد داوطلب با موفقیت حذف شد.';
        $msg_type = 'success';
    }
}

// Filters & Search
$filter_status = $_GET['status'] ?? 'all';
$search = trim($_GET['search'] ?? '');

$sql = "SELECT * FROM diamond_candidates WHERE 1=1";
$params = [];

if ($filter_status === 'diamonds') {
    $sql .= " AND score >= 12";
} elseif ($filter_status !== 'all') {
    $sql .= " AND status = ?";
    $params[] = $filter_status;
}

if (!empty($search)) {
    $sql .= " AND (full_name LIKE ? OR national_id LIKE ? OR phone LIKE ? OR city LIKE ? OR school_name LIKE ?)";
    $term = "%{$search}%";
    $params = array_merge($params, [$term, $term, $term, $term, $term]);
}

$sql .= " ORDER BY score DESC, created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$candidates = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Realtime Counts
$count_all = $pdo->query("SELECT COUNT(*) FROM diamond_candidates")->fetchColumn() ?: 0;
$count_diamonds = $pdo->query("SELECT COUNT(*) FROM diamond_candidates WHERE score >= 12")->fetchColumn() ?: 0;
$count_invited = $pdo->query("SELECT COUNT(*) FROM diamond_candidates WHERE status = 'invited_stage2'")->fetchColumn() ?: 0;
$count_accepted = $pdo->query("SELECT COUNT(*) FROM diamond_candidates WHERE status = 'accepted_bursary'")->fetchColumn() ?: 0;
$avg_score = $pdo->query("SELECT ROUND(AVG(score), 1) FROM diamond_candidates")->fetchColumn() ?: 0;

// Prepare candidate detailed data map for frontend modal
$candidates_map = [];
foreach ($candidates as $c) {
    $ans = json_decode($c['answers_detail'] ?? '{}', true) ?: [];
    $candidates_map[$c['id']] = [
        'id' => (int)$c['id'],
        'full_name' => $c['full_name'],
        'national_id' => $c['national_id'],
        'phone' => $c['phone'],
        'grade' => $c['grade'],
        'city' => $c['city'],
        'school_name' => $c['school_name'] ?? '',
        'score' => (int)$c['score'],
        'total_questions' => (int)($c['total_questions'] ?? 15),
        'percentage' => $c['percentage'],
        'tier' => $c['tier'],
        'time_spent_seconds' => (int)$c['time_spent_seconds'],
        'created_at' => $c['created_at'],
        'answers' => $ans
    ];
}

$questions_json_path = __DIR__ . '/../assets/diamond-questions.json';
$questions_json_content = file_exists($questions_json_path) ? file_get_contents($questions_json_path) : '[]';
?>
<!DOCTYPE html>
<html lang="fa-IR" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سامانه رصد گنج‌های پنهان | بنیاد نیکوکاری حکمت</title>
    
    <!-- Self-hosted Vazirmatn -->
    <style>
    @font-face{font-family:'Vazirmatn';font-style:normal;font-weight:400;font-display:swap;src:url('/assets/fonts/Vazirmatn-400.woff2') format('woff2')}
    @font-face{font-family:'Vazirmatn';font-style:normal;font-weight:700;font-display:swap;src:url('/assets/fonts/Vazirmatn-700.woff2') format('woff2')}
    @font-face{font-family:'Vazirmatn';font-style:normal;font-weight:900;font-display:swap;src:url('/assets/fonts/Vazirmatn-900.woff2') format('woff2')}
    body { font-family: 'Vazirmatn', sans-serif; }
    </style>
    <link rel="stylesheet" href="/assets/tailwind.min.css">
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen">

    <!-- Top Navigation Header -->
    <header class="bg-slate-900/90 backdrop-blur-xl border-b border-white/10 sticky top-0 z-40 shadow-xl">
        <div class="container mx-auto px-4 py-4 flex flex-col sm:flex-row justify-between items-center gap-4">
            <div class="flex items-center gap-4">
                <div class="w-12 h-12 bg-gradient-to-tr from-emerald-600 to-teal-400 text-white rounded-2xl flex items-center justify-center text-2xl shadow-lg shadow-emerald-500/20">
                    💎
                </div>
                <div>
                    <div class="inline-flex items-center gap-2 text-xs font-black text-emerald-400">
                        <span>پویش ملی استعدادیابی</span>
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-ping"></span>
                    </div>
                    <h1 class="text-xl font-black text-white">سامانه رصد کارنامه‌ها و گنج‌های پنهان</h1>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <a href="../almas.php" target="_blank" class="px-4 py-2 rounded-xl bg-emerald-500/20 hover:bg-emerald-500/30 border border-emerald-400/40 text-emerald-300 text-xs font-bold transition-all flex items-center gap-1.5 shadow-sm">
                    <span>مشاهده صفحه آزمون آنلاین</span>
                    <span>↗</span>
                </a>
                <a href="index.php" class="px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-300 text-xs font-bold transition-all border border-slate-700">
                    بازگشت به میز کار مدیریت
                </a>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8">
        
        <?php if (!empty($msg)): ?>
            <div class="mb-6 p-4 rounded-2xl <?php echo $msg_type === 'success' ? 'bg-emerald-500/15 border border-emerald-500/40 text-emerald-200' : 'bg-rose-500/15 border border-rose-500/40 text-rose-200'; ?> text-sm flex items-center gap-3 shadow-lg">
                <span class="text-lg"><?php echo $msg_type === 'success' ? '✅' : '⚠️'; ?></span>
                <span><?php echo htmlspecialchars($msg); ?></span>
            </div>
        <?php endif; ?>

        <!-- Executive Statistics Dashboard -->
        <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
            
            <!-- Card 1: Total Candidates -->
            <div class="bg-slate-900 border border-slate-800 p-5 rounded-3xl shadow-lg relative overflow-hidden">
                <div class="text-xs font-bold text-slate-400 mb-2">کل شرکت‌کنندگان</div>
                <div class="text-3xl font-black text-white"><?php echo number_format($count_all); ?> <span class="text-xs text-slate-500 font-normal">نفر</span></div>
                <div class="text-[10px] text-slate-500 mt-2">با ثبت یکتای کد ملی</div>
            </div>

            <!-- Card 2: Diamonds (High Scorers 12+) - Highlighted -->
            <div class="col-span-2 sm:col-span-1 bg-gradient-to-br from-emerald-950 via-slate-900 to-teal-950 border-2 border-emerald-500/60 p-5 rounded-3xl shadow-xl shadow-emerald-950/50 relative overflow-hidden group">
                <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-emerald-500/15 rounded-full blur-xl pointer-events-none"></div>
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-black text-emerald-400">گنج‌های کشف‌شده</span>
                    <span class="text-lg animate-bounce">💎</span>
                </div>
                <div class="text-3xl font-black text-emerald-300 drop-shadow-[0_0_12px_rgba(52,211,153,0.5)]">
                    <?php echo number_format($count_diamonds); ?>
                    <span class="text-xs text-emerald-400/80 font-normal">نخبه (۱۲+)</span>
                </div>
                <div class="text-[10px] text-emerald-400/70 mt-2 font-bold">اولویت طلایی بورس حکمت</div>
            </div>

            <!-- Card 3: Average Score -->
            <div class="bg-slate-900 border border-slate-800 p-5 rounded-3xl shadow-lg">
                <div class="text-xs font-bold text-slate-400 mb-2">میانگین نمره آزمون</div>
                <div class="text-3xl font-black text-cyan-300"><?php echo $avg_score; ?> <span class="text-xs text-slate-500 font-normal">از ۱۵</span></div>
                <div class="text-[10px] text-slate-500 mt-2">تراز کل داوطلبان</div>
            </div>

            <!-- Card 4: Invited to Stage 2 -->
            <div class="bg-slate-900 border border-slate-800 p-5 rounded-3xl shadow-lg">
                <div class="text-xs font-bold text-amber-400 mb-2">دعوت به مصاحبه حضوری</div>
                <div class="text-3xl font-black text-amber-300"><?php echo number_format($count_invited); ?></div>
                <div class="text-[10px] text-slate-500 mt-2">مرحله راستی‌آزمایی</div>
            </div>

            <!-- Card 5: Bursary Awarded -->
            <div class="bg-slate-900 border border-slate-800 p-5 rounded-3xl shadow-lg">
                <div class="text-xs font-bold text-teal-400 mb-2">بورس قطعی حکمت</div>
                <div class="text-3xl font-black text-teal-300"><?php echo number_format($count_accepted); ?> <span class="text-xs text-slate-500 font-normal">دانش‌آموز</span></div>
                <div class="text-[10px] text-teal-400/60 mt-2 font-bold">تحت پوشش حمایت مالی</div>
            </div>

        </div>

        <!-- Filter & Search Controls -->
        <div class="bg-slate-900 border border-slate-800 p-5 rounded-3xl mb-8 shadow-xl">
            <form method="GET" class="flex flex-col lg:flex-row gap-4 justify-between items-center">
                
                <!-- Quick Filter Tabs -->
                <div class="flex flex-wrap gap-2 w-full lg:w-auto">
                    <a href="?status=all" class="px-4 py-2 rounded-2xl text-xs font-bold transition-all <?php echo $filter_status === 'all' ? 'bg-slate-100 text-slate-900 shadow-md' : 'bg-slate-800 text-slate-400 hover:text-white'; ?>">
                        همه کارنامه‌ها (<?php echo $count_all; ?>)
                    </a>
                    <a href="?status=diamonds" class="px-4 py-2 rounded-2xl text-xs font-black transition-all border <?php echo $filter_status === 'diamonds' ? 'bg-emerald-500 text-slate-950 border-emerald-400 shadow-lg shadow-emerald-500/20' : 'bg-emerald-950/40 text-emerald-400 border-emerald-500/30 hover:bg-emerald-900/60'; ?>">
                        💎 فقط گنج‌های پنهان (نمرات ۱۲ به بالا: <?php echo $count_diamonds; ?>)
                    </a>
                    <a href="?status=pending" class="px-4 py-2 rounded-2xl text-xs font-bold transition-all <?php echo $filter_status === 'pending' ? 'bg-teal-600 text-white shadow-md' : 'bg-slate-800 text-slate-400 hover:text-white'; ?>">
                        در انتظار بررسی
                    </a>
                    <a href="?status=invited_stage2" class="px-4 py-2 rounded-2xl text-xs font-bold transition-all <?php echo $filter_status === 'invited_stage2' ? 'bg-amber-600 text-white shadow-md' : 'bg-slate-800 text-slate-400 hover:text-white'; ?>">
                        دعوت به مرحله ۲
                    </a>
                    <a href="?status=accepted_bursary" class="px-4 py-2 rounded-2xl text-xs font-bold transition-all <?php echo $filter_status === 'accepted_bursary' ? 'bg-emerald-600 text-white shadow-md' : 'bg-slate-800 text-slate-400 hover:text-white'; ?>">
                        پذیرفته نهایی بورس 🎓
                    </a>
                </div>

                <!-- Search Input -->
                <div class="flex gap-2 w-full lg:w-96">
                    <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="جستجوی نام، کد ملی، تلفن، شهر..." class="w-full bg-slate-950 border border-slate-700 rounded-2xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-emerald-500 font-sans">
                    <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-black rounded-2xl transition-all shadow-md">
                        جستجو
                    </button>
                </div>

            </form>
        </div>

        <!-- Candidate Results Table -->
        <div class="bg-slate-900 border border-slate-800 rounded-3xl overflow-hidden shadow-2xl">
            <div class="overflow-x-auto">
                <table class="w-full text-right text-xs">
                    <thead class="bg-slate-950 text-slate-400 border-b border-slate-800 font-bold">
                        <tr>
                            <th class="p-4">#</th>
                            <th class="p-4">نام و نشان داوطلب</th>
                            <th class="p-4">کد ملی (ثبت‌احوال)</th>
                            <th class="p-4">تلفن تماس</th>
                            <th class="p-4">پایه و شهر</th>
                            <th class="p-4 text-center">نمره خام</th>
                            <th class="p-4 text-center">درصد</th>
                            <th class="p-4 text-center">پاسخ‌نامه و تحلیل</th>
                            <th class="p-4">سطح شناختی و تحلیل هوش</th>
                            <th class="p-4 text-center">مدت زمان</th>
                            <th class="p-4">وضعیت فرآیند</th>
                            <th class="p-4 text-center">عملیات</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800/70 text-slate-300">
                        <?php if (empty($candidates)): ?>
                            <tr>
                                <td colspan="12" class="p-12 text-center text-slate-500">
                                    <div class="text-4xl mb-3">🔍</div>
                                    <div class="font-bold text-base text-slate-400">هیچ داوطلبی با فیلتر انتخابی یافت نشد.</div>
                                    <p class="text-xs text-slate-600 mt-1">با شرکت اولین داوطلبان در آزمون آنلاین، نتایج به طور زنده در این جدول نمایان خواهد شد.</p>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($candidates as $cand): 
                                $is_diamond = ($cand['score'] >= 12);
                                $row_class = $is_diamond 
                                    ? 'bg-gradient-to-r from-emerald-950/70 via-slate-900 to-slate-900 border-r-4 border-r-emerald-400 shadow-sm' 
                                    : 'hover:bg-slate-800/40 transition-colors';
                                
                                $ans_data = json_decode($cand['answers_detail'] ?? '{}', true) ?: [];
                                $wrong_questions = [];
                                foreach ($ans_data as $q_num => $q_val) {
                                    if (empty($q_val['is_correct'])) {
                                        $wrong_questions[] = $q_num;
                                    }
                                }
                            ?>
                                <tr class="<?php echo $row_class; ?>">
                                    
                                    <!-- ID -->
                                    <td class="p-4 font-mono text-slate-500"><?php echo $cand['id']; ?></td>
                                    
                                    <!-- Name + Diamond Badge -->
                                    <td class="p-4">
                                        <div class="flex items-center gap-2">
                                            <?php if ($is_diamond): ?>
                                                <span class="text-base animate-pulse" title="گنج پنهان کشف‌شده">💎</span>
                                            <?php endif; ?>
                                            <span class="font-black text-sm <?php echo $is_diamond ? 'text-emerald-200' : 'text-white'; ?>">
                                                <?php echo htmlspecialchars($cand['full_name']); ?>
                                            </span>
                                        </div>
                                        <?php if ($is_diamond): ?>
                                            <div class="inline-flex items-center gap-1 mt-1 px-2 py-0.5 rounded-full bg-emerald-500/20 text-emerald-300 text-[10px] font-black border border-emerald-400/30">
                                                <span>⭐ گنج پنهان</span>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- National ID -->
                                    <td class="p-4 font-mono text-slate-300 tracking-wider">
                                        <?php echo htmlspecialchars($cand['national_id'] ?? 'ثبت نشده'); ?>
                                    </td>

                                    <!-- Phone -->
                                    <td class="p-4 font-mono">
                                        <a href="tel:<?php echo htmlspecialchars($cand['phone']); ?>" class="text-teal-400 hover:text-teal-300 hover:underline">
                                            <?php echo htmlspecialchars($cand['phone']); ?>
                                        </a>
                                    </td>

                                    <!-- Grade & City -->
                                    <td class="p-4">
                                        <div class="text-white font-bold"><?php echo htmlspecialchars($cand['grade']); ?></div>
                                        <div class="text-[11px] text-slate-400"><?php echo htmlspecialchars($cand['city']); ?> <?php if(!empty($cand['school_name'])) echo ' | ' . htmlspecialchars($cand['school_name']); ?></div>
                                    </td>

                                    <!-- Score (Highlighted) -->
                                    <td class="p-4 text-center">
                                        <?php if ($is_diamond): ?>
                                            <div class="inline-block px-3 py-1 rounded-xl bg-emerald-500/20 border border-emerald-400/50 text-emerald-300 font-black text-lg shadow-[0_0_12px_rgba(52,211,153,0.3)]">
                                                <?php echo $cand['score']; ?> <span class="text-xs text-slate-400 font-normal">/ ۱۵</span>
                                            </div>
                                        <?php elseif ($cand['score'] >= 9): ?>
                                            <span class="text-base font-black text-cyan-300"><?php echo $cand['score']; ?> <span class="text-xs text-slate-400 font-normal">/ ۱۵</span></span>
                                        <?php else: ?>
                                            <span class="text-sm font-bold text-slate-400"><?php echo $cand['score']; ?> <span class="text-xs text-slate-500 font-normal">/ ۱۵</span></span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Percentage -->
                                    <td class="p-4 text-center font-mono font-bold text-slate-200">
                                        <?php echo $cand['percentage']; ?>٪
                                    </td>

                                    <!-- Answers Analysis & Modal Trigger -->
                                    <td class="p-4 text-center">
                                        <button type="button" onclick="openCandidateModal(<?php echo $cand['id']; ?>)" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-cyan-500/20 hover:bg-cyan-500/30 text-cyan-300 border border-cyan-500/40 text-xs font-bold transition-all shadow-sm">
                                            <span>📋 بررسی پاسخ‌ها</span>
                                        </button>
                                        <?php if (empty($wrong_questions)): ?>
                                            <div class="text-[10px] font-black text-emerald-400 mt-1">✨ ۱۰۰٪ بدون اشتباه</div>
                                        <?php else: ?>
                                            <div class="text-[10px] font-black text-rose-400 mt-1">
                                                ❌ اشتباه در: سوال <?php echo implode(' و ', $wrong_questions); ?>
                                            </div>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Tier / Cognitive Potential -->
                                    <td class="p-4">
                                        <?php if ($is_diamond): ?>
                                            <span class="px-3 py-1 rounded-xl text-[11px] font-black bg-gradient-to-r from-emerald-500/30 to-teal-500/30 text-emerald-200 border border-emerald-400/40 inline-block shadow-sm">
                                                ✨ <?php echo htmlspecialchars($cand['tier'] ?? 'درخشان و استثنایی'); ?>
                                            </span>
                                        <?php elseif ($cand['score'] >= 10): ?>
                                            <span class="px-2.5 py-1 rounded-xl text-[10px] font-bold bg-teal-500/20 text-teal-300 border border-teal-500/30 inline-block">
                                                <?php echo htmlspecialchars($cand['tier'] ?? 'بسیار مستعد'); ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="px-2 py-0.5 rounded-lg text-[10px] text-slate-400 bg-slate-800">
                                                <?php echo htmlspecialchars($cand['tier'] ?? 'عادی'); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>

                                    <!-- Time Spent -->
                                    <td class="p-4 text-center font-mono text-slate-400 text-[11px]">
                                        <?php 
                                        $mins = floor($cand['time_spent_seconds'] / 60);
                                        $secs = $cand['time_spent_seconds'] % 60;
                                        echo sprintf('%02d:%02d', $mins, $secs);
                                        ?>
                                    </td>

                                    <!-- Process Status Dropdown -->
                                    <td class="p-4">
                                        <form method="POST" class="inline">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="cand_id" value="<?php echo $cand['id']; ?>">
                                            <select name="status" onchange="this.form.submit()" class="bg-slate-950 border border-slate-700 text-[11px] font-bold rounded-xl px-2.5 py-1.5 text-slate-200 focus:outline-none focus:border-emerald-400 transition-all">
                                                <option value="pending" <?php if($cand['status'] === 'pending') echo 'selected'; ?>>⏳ در انتظار بررسی</option>
                                                <option value="invited_stage2" <?php if($cand['status'] === 'invited_stage2') echo 'selected'; ?>>📞 دعوت به مرحله ۲ حضوری</option>
                                                <option value="interview_stage3" <?php if($cand['status'] === 'interview_stage3') echo 'selected'; ?>>🔍 مصاحبه و مددکاری مرحله ۳</option>
                                                <option value="accepted_bursary" <?php if($cand['status'] === 'accepted_bursary') echo 'selected'; ?>>🎓 پذیرفته بورس حکمت</option>
                                                <option value="rejected" <?php if($cand['status'] === 'rejected') echo 'selected'; ?>>❌ عدم پذیرش</option>
                                            </select>
                                        </form>
                                    </td>

                                    <!-- Actions -->
                                    <td class="p-4 text-center">
                                        <form method="POST" onsubmit="return confirm('آیا از حذف پرونده این داوطلب اطمینان دارید؟');" class="inline">
                                            <input type="hidden" name="action" value="delete_candidate">
                                            <input type="hidden" name="cand_id" value="<?php echo $cand['id']; ?>">
                                            <button type="submit" class="text-rose-400 hover:text-rose-300 text-xs px-2 py-1 rounded-lg hover:bg-rose-500/10 transition-colors" title="حذف رکورد">
                                                حذف
                                            </button>
                                        </form>
                                    </td>

                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </main>

    <!-- Answer Sheet & Question Breakdown Modal -->
    <div id="answerModal" class="fixed inset-0 z-50 hidden bg-slate-950/85 backdrop-blur-md overflow-y-auto p-3 sm:p-6 flex items-center justify-center transition-all" onclick="if(event.target === this) closeAnswerModal();">
        <div class="bg-slate-900 border border-slate-700 w-full max-w-4xl rounded-3xl shadow-2xl overflow-hidden my-6 transform transition-all text-right">
            
            <!-- Modal Header -->
            <div class="bg-slate-950 p-5 sm:p-6 border-b border-slate-800 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-cyan-600 to-teal-400 text-white flex items-center justify-center text-2xl font-black shadow-lg shadow-cyan-500/20">
                        📋
                    </div>
                    <div>
                        <div class="text-xs font-black text-cyan-400">کارنامه تحلیلی و پاسخ‌نامه داوطلب</div>
                        <h2 id="modalCandidateName" class="text-xl font-black text-white">نام داوطلب</h2>
                        <div id="modalCandidateMeta" class="text-xs text-slate-400 mt-0.5">کد ملی | شهر | مدرسه</div>
                    </div>
                </div>
                <button onclick="closeAnswerModal()" class="w-10 h-10 rounded-2xl bg-slate-800 hover:bg-slate-700 text-slate-400 hover:text-white flex items-center justify-center text-lg font-bold transition-colors">
                    ✕
                </button>
            </div>

            <!-- Candidate Score & Summary Bar -->
            <div class="p-4 sm:p-5 bg-slate-900/90 border-b border-slate-800 grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="bg-slate-950 p-3 rounded-2xl border border-slate-800 text-center sm:text-right">
                    <div class="text-[11px] text-slate-400 font-bold">نمره خام</div>
                    <div id="modalScore" class="text-2xl font-black text-emerald-400 mt-0.5">۱۳ / ۱۵</div>
                </div>
                <div class="bg-slate-950 p-3 rounded-2xl border border-slate-800 text-center sm:text-right">
                    <div class="text-[11px] text-slate-400 font-bold">درصد نهایی</div>
                    <div id="modalPercentage" class="text-2xl font-black text-cyan-400 mt-0.5">۸۶.۷٪</div>
                </div>
                <div class="bg-slate-950 p-3 rounded-2xl border border-slate-800 text-center sm:text-right">
                    <div class="text-[11px] text-slate-400 font-bold">مدت زمان پاسخگویی</div>
                    <div id="modalTime" class="text-xl font-black text-amber-300 mt-1">۰۳:۰۶</div>
                </div>
                <div class="bg-slate-950 p-3 rounded-2xl border border-slate-800 text-center sm:text-right">
                    <div class="text-[11px] text-slate-400 font-bold">سطح شناختی</div>
                    <div id="modalTier" class="text-xs font-bold text-emerald-300 mt-1.5 truncate">درخشان و استثنایی 💎</div>
                </div>
            </div>

            <!-- Filter tabs inside modal -->
            <div class="px-5 sm:px-6 py-3.5 bg-slate-950 border-b border-slate-800 flex flex-wrap gap-3 items-center justify-between">
                <div class="text-xs font-bold text-slate-400">فیلتر بررسی سوالات:</div>
                <div class="flex flex-wrap gap-2">
                    <button type="button" id="btnFilterAll" onclick="filterModalQuestions('all')" class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-slate-800 text-white transition-all shadow-sm">
                        همه سوالات (۱۵)
                    </button>
                    <button type="button" id="btnFilterWrong" onclick="filterModalQuestions('wrong')" class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-rose-500/20 text-rose-300 border border-rose-500/40 hover:bg-rose-500/30 transition-all">
                        ❌ فقط سوالات اشتباه (<span id="modalWrongCount">۰</span>)
                    </button>
                    <button type="button" id="btnFilterCorrect" onclick="filterModalQuestions('correct')" class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 hover:bg-emerald-500/30 transition-all">
                        ✅ فقط سوالات صحیح (<span id="modalCorrectCount">۱۵</span>)
                    </button>
                </div>
            </div>

            <!-- Questions List Container -->
            <div id="modalQuestionsContainer" class="p-5 sm:p-6 space-y-6 max-h-[60vh] overflow-y-auto">
                <!-- Dynamic Question Cards will be injected here -->
            </div>

            <!-- Modal Footer -->
            <div class="p-4 bg-slate-950 border-t border-slate-800 flex justify-between items-center text-xs text-slate-400">
                <span class="flex items-center gap-1.5">
                    <span>💎 سامانه رصد گنج‌های پنهان</span>
                    <span>| بنیاد نیکوکاری حکمت</span>
                </span>
                <button onclick="closeAnswerModal()" class="px-5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-white font-bold transition-colors">
                    بستن پنجره
                </button>
            </div>
        </div>
    </div>

    <script>
        const questionsData = <?php echo $questions_json_content; ?>;
        const candidatesMap = <?php echo json_encode($candidates_map, JSON_UNESCAPED_UNICODE); ?>;
        let currentActiveCandidate = null;
        let currentFilter = 'all';

        function openCandidateModal(candId) {
            const cand = candidatesMap[candId];
            if (!cand) return;

            currentActiveCandidate = cand;
            currentFilter = 'all';

            document.getElementById('modalCandidateName').innerText = cand.full_name;
            document.getElementById('modalCandidateMeta').innerText = 
                `کد ملی: ${cand.national_id || '---'} | شهر: ${cand.city || '---'} | پایه: ${cand.grade || '---'}` + 
                (cand.school_name ? ` | مدرسه: ${cand.school_name}` : '') + 
                (cand.phone ? ` | شماره تماس: ${cand.phone}` : '');
            
            document.getElementById('modalScore').innerHTML = `${cand.score} <span class="text-xs text-slate-500 font-normal">/ ${cand.total_questions}</span>`;
            document.getElementById('modalPercentage').innerText = `${cand.percentage}٪`;
            
            const mins = Math.floor(cand.time_spent_seconds / 60);
            const secs = cand.time_spent_seconds % 60;
            document.getElementById('modalTime').innerText = `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
            document.getElementById('modalTier').innerText = cand.tier || 'عادی';

            // Calculate counts
            let wrongCount = 0;
            let correctCount = 0;
            questionsData.forEach(q => {
                const ans = cand.answers ? cand.answers[q.id] : null;
                if (ans && ans.is_correct) {
                    correctCount++;
                } else {
                    wrongCount++;
                }
            });

            document.getElementById('modalWrongCount').innerText = wrongCount;
            document.getElementById('modalCorrectCount').innerText = correctCount;

            updateFilterButtons();
            renderQuestionsList();

            const modal = document.getElementById('answerModal');
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeAnswerModal() {
            const modal = document.getElementById('answerModal');
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAnswerModal();
            }
        });

        function filterModalQuestions(filterType) {
            currentFilter = filterType;
            updateFilterButtons();
            renderQuestionsList();
        }

        function updateFilterButtons() {
            const btnAll = document.getElementById('btnFilterAll');
            const btnWrong = document.getElementById('btnFilterWrong');
            const btnCorrect = document.getElementById('btnFilterCorrect');

            btnAll.className = (currentFilter === 'all')
                ? 'px-3.5 py-1.5 rounded-xl text-xs font-black bg-white text-slate-950 shadow-md transition-all'
                : 'px-3.5 py-1.5 rounded-xl text-xs font-bold bg-slate-800 text-slate-300 hover:text-white transition-all';

            btnWrong.className = (currentFilter === 'wrong')
                ? 'px-3.5 py-1.5 rounded-xl text-xs font-black bg-rose-500 text-white shadow-md shadow-rose-500/20 transition-all'
                : 'px-3.5 py-1.5 rounded-xl text-xs font-bold bg-rose-500/20 text-rose-300 border border-rose-500/40 hover:bg-rose-500/30 transition-all';

            btnCorrect.className = (currentFilter === 'correct')
                ? 'px-3.5 py-1.5 rounded-xl text-xs font-black bg-emerald-500 text-slate-950 shadow-md shadow-emerald-500/20 transition-all'
                : 'px-3.5 py-1.5 rounded-xl text-xs font-bold bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 hover:bg-emerald-500/30 transition-all';
        }

        function renderQuestionsList() {
            const container = document.getElementById('modalQuestionsContainer');
            if (!currentActiveCandidate) return;

            const cand = currentActiveCandidate;
            let html = '';

            questionsData.forEach(q => {
                const ans = cand.answers ? cand.answers[q.id] : null;
                const isCorrect = ans ? Boolean(ans.is_correct) : false;
                const selected = ans ? ans.selected : null;
                const isUnanswered = (selected === null || selected === 0);

                if (currentFilter === 'wrong' && isCorrect) return;
                if (currentFilter === 'correct' && !isCorrect) return;

                let statusBadge = '';
                let cardBorder = '';
                let cardBg = '';

                if (isCorrect) {
                    statusBadge = '<span class="px-3 py-1 rounded-xl bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 text-xs font-black shadow-sm">✅ پاسخ صحیح داوطلب</span>';
                    cardBorder = 'border-slate-800 hover:border-emerald-500/30';
                    cardBg = 'bg-slate-950/70';
                } else if (isUnanswered) {
                    statusBadge = '<span class="px-3 py-1 rounded-xl bg-amber-500/20 text-amber-300 border border-amber-500/40 text-xs font-black shadow-sm">⏳ بدون پاسخ / عبور</span>';
                    cardBorder = 'border-amber-500/40';
                    cardBg = 'bg-amber-950/20';
                } else {
                    statusBadge = '<span class="px-3 py-1 rounded-xl bg-rose-500/20 text-rose-300 border border-rose-500/40 text-xs font-black shadow-sm">❌ پاسخ نادرست داوطلب</span>';
                    cardBorder = 'border-rose-500/40';
                    cardBg = 'bg-rose-950/25';
                }

                let optionsHtml = '<div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-4">';
                q.options.forEach((optSvg, idx) => {
                    const optNum = idx + 1;
                    const isSelectedByUser = (selected === optNum);
                    const isOfficialCorrect = (q.correct === optNum);

                    let optClass = 'bg-slate-900 border-slate-800 text-slate-400';
                    let badge = '';

                    if (isSelectedByUser && isOfficialCorrect) {
                        optClass = 'bg-emerald-950/50 border-2 border-emerald-400 text-emerald-200 shadow-md shadow-emerald-500/20';
                        badge = '<span class="text-[10px] font-black text-emerald-300 mt-1 block">گزینه انتخابی (درست ✅)</span>';
                    } else if (isSelectedByUser && !isOfficialCorrect) {
                        optClass = 'bg-rose-950/50 border-2 border-rose-500 text-rose-200 shadow-md shadow-rose-500/20';
                        badge = '<span class="text-[10px] font-black text-rose-300 mt-1 block">گزینه انتخابی (نادرست ❌)</span>';
                    } else if (isOfficialCorrect) {
                        optClass = 'bg-emerald-950/40 border-2 border-dashed border-emerald-400 text-emerald-300';
                        badge = '<span class="text-[10px] font-black text-emerald-400 mt-1 block">پاسخ صحیح سیستم ✅</span>';
                    }

                    optionsHtml += `
                        <div class="p-3 rounded-2xl border ${optClass} flex flex-col items-center justify-center text-center transition-all">
                            <span class="text-xs font-bold text-slate-400 mb-2">گزینه ${optNum}</span>
                            <div class="w-16 h-16 flex items-center justify-center">${optSvg}</div>
                            ${badge}
                        </div>
                    `;
                });
                optionsHtml += '</div>';

                html += `
                    <div class="p-5 rounded-3xl border ${cardBorder} ${cardBg} transition-all shadow-lg">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-2 mb-3 pb-3 border-b border-white/5">
                            <div>
                                <span class="text-xs font-black text-cyan-400 tracking-wide">سوال شماره ${q.id}</span>
                                <h3 class="text-sm font-bold text-white mt-0.5">${q.category}</h3>
                            </div>
                            <div>${statusBadge}</div>
                        </div>

                        <!-- Puzzle Graphic -->
                        <div class="my-4 flex flex-col items-center justify-center bg-slate-900/90 p-4 rounded-2xl border border-slate-800">
                            <div class="text-xs text-slate-300 mb-3 font-bold">${q.title}</div>
                            <div class="scale-90 sm:scale-100 transform">${q.graphic}</div>
                        </div>

                        <!-- Options Grid -->
                        <div>
                            <div class="text-xs font-bold text-slate-400 mb-1">گزینه‌های پاسخ و وضعیت انتخاب:</div>
                            ${optionsHtml}
                        </div>
                    </div>
                `;
            });

            if (html === '') {
                html = `
                    <div class="p-12 text-center text-slate-500">
                        <div class="text-3xl mb-2">✨</div>
                        <div class="text-sm font-bold text-slate-400">موردی با این فیلتر وجود ندارد.</div>
                    </div>
                `;
            }

            container.innerHTML = html;
        }
    </script>
</body>
</html>
