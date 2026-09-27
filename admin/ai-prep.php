<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';

// Auth Guard: Superadmin, Secretary, Education Deputy, Data Operator, Admin
require_role(['superadmin', 'secretary', 'education_deputy', 'data_operator', 'admin']);

$msg = '';
$msg_type = '';

// ایجاد جدول ذخیره منابع هوش مصنوعی در صورت عدم وجود
$pdo->exec("CREATE TABLE IF NOT EXISTS ai_textbook_sources (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    grade TEXT NOT NULL,
    subject TEXT NOT NULL,
    chapter_title TEXT NOT NULL,
    file_path TEXT,
    notes TEXT,
    status TEXT DEFAULT 'ready',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// ثبت منبع یا فصل جدید
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'add_source') {
        $grade = trim($_POST['grade'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $chapter = trim($_POST['chapter_title'] ?? '');
        $notes = trim($_POST['notes'] ?? '');

        if (!empty($grade) && !empty($subject) && !empty($chapter)) {
            $stmt = $pdo->prepare("INSERT INTO ai_textbook_sources (grade, subject, chapter_title, notes) VALUES (?, ?, ?, ?)");
            $stmt->execute([$grade, $subject, $chapter, $notes]);
            $msg = 'منبع کتاب درسی جهت پردازش هوش مصنوعی با موفقیت ثبت شد.';
            $msg_type = 'success';
            log_activity('ثبت منبع هوش مصنوعی', 'ai_source', $pdo->lastInsertId(), "ثبت کتاب {$subject} {$grade} - {$chapter}");
        } else {
            $msg = 'لطفاً تمام فیلدهای الزامی را تکمیل کنید.';
            $msg_type = 'error';
        }
    } elseif ($_POST['action'] === 'delete_source') {
        $id = (int)$_POST['source_id'];
        $stmt = $pdo->prepare("DELETE FROM ai_textbook_sources WHERE id = ?");
        $stmt->execute([$id]);
        $msg = 'منبع مورد نظر حذف گردید.';
        $msg_type = 'success';
    }
}

$sources = $pdo->query("SELECT * FROM ai_textbook_sources ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$total_sources = count($sources);

// تعداد کل درخواست‌های کتاب
$total_books_requested = $pdo->query("SELECT COUNT(*) FROM student_book_requests")->fetchColumn() ?: 0;
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سامانه آماده‌سازی هوش مصنوعی و کتب درسی | بنیاد حکمت</title>
    <style>
    @font-face{font-family:'Vazirmatn';font-style:normal;font-weight:100;font-display:swap;src:url('/assets/fonts/Vazirmatn-100.woff2') format('woff2')}
    @font-face{font-family:'Vazirmatn';font-style:normal;font-weight:400;font-display:swap;src:url('/assets/fonts/Vazirmatn-400.woff2') format('woff2')}
    @font-face{font-family:'Vazirmatn';font-style:normal;font-weight:700;font-display:swap;src:url('/assets/fonts/Vazirmatn-700.woff2') format('woff2')}
    @font-face{font-family:'Vazirmatn';font-style:normal;font-weight:900;font-display:swap;src:url('/assets/fonts/Vazirmatn-900.woff2') format('woff2')}
    </style>
    <link rel="stylesheet" href="/assets/tailwind.min.css">
    <script src="/assets/alpine.min.js" defer></script>
</head>
<body class="bg-slate-950 text-slate-100 font-sans min-h-screen">

    <!-- Top Navigation -->
    <header class="bg-slate-900 border-b border-white/10 sticky top-0 z-50 backdrop-blur-md">
        <div class="container mx-auto px-6 py-4 flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="flex items-center gap-4">
                <div class="w-10 h-10 bg-indigo-600 rounded-2xl flex items-center justify-center text-xl shadow-lg shadow-indigo-500/20">
                    🤖
                </div>
                <div>
                    <h1 class="text-lg font-black text-white flex items-center gap-2">
                        آزمایشگاه آماده‌سازی هوش مصنوعی و کتب درسی
                        <span class="text-[10px] font-bold bg-indigo-500/20 text-indigo-300 border border-indigo-500/30 px-2.5 py-0.5 rounded-full">AI Learning Lab</span>
                    </h1>
                    <p class="text-xs text-slate-400">محیط کار تخصصی پردازش داده‌ها، پیوند کتب درسی و الگوریتم‌های آزمون‌ساز هوشمند</p>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <a href="library.php" class="bg-teal-600 hover:bg-teal-500 text-white font-bold px-4 py-2 rounded-xl text-xs transition-colors flex items-center gap-1.5 shadow-md">
                    <span>📚</span>
                    <span>بانک کتابخانه و امانات (۲۰۱ جلد)</span>
                </a>
                <a href="../admin-logout.php" class="bg-red-500/15 hover:bg-red-500/25 text-red-300 border border-red-500/30 px-4 py-2 rounded-xl text-xs transition-colors">
                    خروج
                </a>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-6 py-8 space-y-8" x-data="{
        activeTab: 'sources',
        showSimulator: false,
        testSubject: 'زیست‌شناسی دهم',
        testChapter: 'فصل دوم: گوارش و جذب مواد',
        testCount: 3,
        testLevel: 'متوسط',
        simulating: false,
        simulatedQuestions: []
    }">

        <!-- Notice Banner -->
        <?php if ($msg): ?>
            <div class="p-4 rounded-2xl text-xs font-bold text-center <?php echo $msg_type === 'success' ? 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/30' : 'bg-rose-500/15 text-rose-300 border border-rose-500/30'; ?>">
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <!-- Welcome Banner for Viana -->
        <div class="bg-gradient-to-r from-indigo-900/60 via-purple-900/40 to-slate-900 p-8 rounded-3xl border border-indigo-500/30 relative overflow-hidden">
            <div class="max-w-3xl space-y-3 relative z-10">
                <span class="text-[11px] font-black uppercase tracking-wider text-indigo-400 bg-indigo-500/15 px-3 py-1 rounded-full border border-indigo-500/30 inline-block">
                    دستور مدیرعامل | محیط فنی ویانا وحیدی
                </span>
                <h2 class="text-2xl font-black text-white">پروژه استخراج هوشمند سوالات کتب درسی با پایتون و هوش مصنوعی</h2>
                <p class="text-xs text-slate-300 leading-relaxed">
                    هدف این سامانه، آماده‌سازی منابع کتب درسی (مشابه سیستم NotebookLM) برای دانش‌آموزان بنیاد حکمت است. شما در این بخش می‌توانید کتب درسی، سرفصل‌ها و سوالات هوش مصنوعی را مدیریت کرده و خروجی اسکریپت‌های پایتون را جهت آزمون‌ساز هوشمند مستقر نمایید.
                </p>
            </div>
        </div>

        <!-- Metric Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-slate-900/80 p-6 rounded-3xl border border-white/10 flex items-center justify-between">
                <div>
                    <span class="text-xs text-slate-400 font-bold block mb-1">فصول و منابع ثبت‌شده</span>
                    <span class="text-2xl font-black text-indigo-400"><?php echo $total_sources; ?> سرفصل</span>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-indigo-500/15 text-indigo-400 flex items-center justify-center text-xl">📖</div>
            </div>

            <div class="bg-slate-900/80 p-6 rounded-3xl border border-white/10 flex items-center justify-between">
                <div>
                    <span class="text-xs text-slate-400 font-bold block mb-1">کتاب‌های مورد نیاز بچه‌ها</span>
                    <span class="text-2xl font-black text-blue-400"><?php echo $total_books_requested; ?> جلد</span>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-blue-500/15 text-blue-400 flex items-center justify-center text-xl">📚</div>
            </div>

            <div class="bg-slate-900/80 p-6 rounded-3xl border border-white/10 flex items-center justify-between">
                <div>
                    <span class="text-xs text-slate-400 font-bold block mb-1">وضعیت موتور هوش مصنوعی</span>
                    <span class="text-2xl font-black text-emerald-400">آماده به کار (RAG)</span>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-emerald-500/15 text-emerald-400 flex items-center justify-center text-xl">⚡</div>
            </div>
        </div>

        <!-- Workspace Tabs -->
        <div class="flex items-center gap-3 border-b border-white/10 pb-4">
            <button @click="activeTab = 'sources'" :class="activeTab === 'sources' ? 'bg-indigo-600 text-white shadow-lg' : 'bg-slate-900 text-slate-400 hover:bg-slate-800'" class="px-5 py-2.5 rounded-2xl text-xs font-black transition-all">
                📚 منابع و کتب درسی ثبت‌شده
            </button>
            <button @click="activeTab = 'new_source'" :class="activeTab === 'new_source' ? 'bg-indigo-600 text-white shadow-lg' : 'bg-slate-900 text-slate-400 hover:bg-slate-800'" class="px-5 py-2.5 rounded-2xl text-xs font-black transition-all">
                + افزودن کتاب / فصل جدید
            </button>
            <button @click="activeTab = 'simulator'" :class="activeTab === 'simulator' ? 'bg-indigo-600 text-white shadow-lg' : 'bg-slate-900 text-slate-400 hover:bg-slate-800'" class="px-5 py-2.5 rounded-2xl text-xs font-black transition-all">
                🧪 شبیه‌ساز آزمون‌ساز هوش مصنوعی
            </button>
            <button @click="activeTab = 'python_guide'" :class="activeTab === 'python_guide' ? 'bg-indigo-600 text-white shadow-lg' : 'bg-slate-900 text-slate-400 hover:bg-slate-800'" class="px-5 py-2.5 rounded-2xl text-xs font-black transition-all">
                🐍 راهنمای کدهای پایتون (ویژه ویانا)
            </button>
        </div>

        <!-- TAB 1: Sources List -->
        <div x-show="activeTab === 'sources'" class="space-y-4">
            <div class="bg-slate-900 rounded-3xl p-6 border border-white/10">
                <h3 class="text-sm font-black text-white mb-4">فهرست فصول کتب بارگذاری‌شده برای هوش مصنوعی</h3>

                <?php if (empty($sources)): ?>
                    <div class="text-center py-12 text-slate-500 text-xs font-bold border border-dashed border-white/10 rounded-2xl">
                        هنوز فصلی برای هوش مصنوعی ثبت نشده است. از تب «+ افزودن کتاب / فصل جدید» شروع کنید.
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-right text-xs">
                            <thead class="text-slate-400 border-b border-white/10">
                                <tr>
                                    <th class="py-3 px-4">ردیف</th>
                                    <th class="py-3 px-4">پایه تحصیلی</th>
                                    <th class="py-3 px-4">عنوان درس</th>
                                    <th class="py-3 px-4">فصل / موضوع</th>
                                    <th class="py-3 px-4">توضیحات و منبع</th>
                                    <th class="py-3 px-4">وضعیت</th>
                                    <th class="py-3 px-4 text-center">عملیات</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                <?php foreach ($sources as $idx => $s): ?>
                                    <tr class="hover:bg-white/5 transition-colors">
                                        <td class="py-3 px-4 text-slate-500"><?php echo $idx + 1; ?></td>
                                        <td class="py-3 px-4 font-bold text-indigo-300"><?php echo htmlspecialchars($s['grade']); ?></td>
                                        <td class="py-3 px-4 font-black text-white"><?php echo htmlspecialchars($s['subject']); ?></td>
                                        <td class="py-3 px-4 font-bold text-slate-200"><?php echo htmlspecialchars($s['chapter_title']); ?></td>
                                        <td class="py-3 px-4 text-slate-400 text-[11px]"><?php echo htmlspecialchars($s['notes'] ?: '---'); ?></td>
                                        <td class="py-3 px-4">
                                            <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-500/15 text-emerald-400 border border-emerald-500/30">
                                                آماده استخراج
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <form method="POST" onsubmit="return confirm('آیا از حذف این منبع اطمینان دارید؟');" class="inline">
                                                <input type="hidden" name="action" value="delete_source">
                                                <input type="hidden" name="source_id" value="<?php echo $s['id']; ?>">
                                                <button type="submit" class="text-rose-400 hover:text-rose-300 text-xs font-bold">حذف</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- TAB 2: Add New Source -->
        <div x-show="activeTab === 'new_source'" class="max-w-2xl mx-auto">
            <div class="bg-slate-900 rounded-3xl p-8 border border-white/10 space-y-6">
                <h3 class="text-base font-black text-white flex items-center gap-2">
                    <span>➕</span>
                    <span>ثبت کتاب یا سرفصل درسی جدید</span>
                </h3>

                <form method="POST" action="" class="space-y-4">
                    <input type="hidden" name="action" value="add_source">

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="text-xs text-slate-400 font-bold block mb-1">پایه تحصیلی:</label>
                            <select name="grade" required class="w-full bg-slate-800 border border-white/15 text-white rounded-xl px-4 py-2.5 text-xs focus:outline-none focus:border-indigo-500">
                                <option value="دهم تجربی">دهم تجربی</option>
                                <option value="دهم ریاضی">دهم ریاضی</option>
                                <option value="یازدهم تجربی">یازدهم تجربی</option>
                                <option value="یازدهم ریاضی">یازدهم ریاضی</option>
                                <option value="دوازدهم تجربی">دوازدهم تجربی</option>
                                <option value="دوازدهم ریاضی">دوازدهم ریاضی</option>
                                <option value="دوازدهم انسانی">دوازدهم انسانی</option>
                                <option value="متوسطه اول (هفتم/هشتم/نهم)">متوسطه اول (هفتم/هشتم/نهم)</option>
                                <option value="کنکور و عمومی">کنکور و عمومی</option>
                            </select>
                        </div>

                        <div>
                            <label class="text-xs text-slate-400 font-bold block mb-1">نام درس:</label>
                            <input type="text" name="subject" required placeholder="مثلاً: زیست‌شناسی، شیمی، فیزیک، حسابان" class="w-full bg-slate-800 border border-white/15 text-white rounded-xl px-4 py-2.5 text-xs focus:outline-none focus:border-indigo-500">
                        </div>
                    </div>

                    <div>
                        <label class="text-xs text-slate-400 font-bold block mb-1">عنوان فصل یا مبحث درسی:</label>
                        <input type="text" name="chapter_title" required placeholder="مثلاً: فصل دوم - ساختار اتم و پیوندهای شیمیایی" class="w-full bg-slate-800 border border-white/15 text-white rounded-xl px-4 py-2.5 text-xs focus:outline-none focus:border-indigo-500">
                    </div>

                    <div>
                        <label class="text-xs text-slate-400 font-bold block mb-1">توضیحات و نکات تکمیلی (مثلاً صفحات کتاب یا منبع):</label>
                        <textarea name="notes" rows="3" placeholder="مثلاً: از صفحه ۲۵ تا ۵۰ کتاب وزارتی چاپ ۱۴۰۴" class="w-full bg-slate-800 border border-white/15 text-white rounded-xl px-4 py-2.5 text-xs focus:outline-none focus:border-indigo-500"></textarea>
                    </div>

                    <button type="submit" class="w-full py-3 bg-indigo-600 hover:bg-indigo-500 text-white font-black rounded-2xl text-xs transition-colors shadow-lg shadow-indigo-600/30">
                        💾 ذخیره سرفصل در پایگاه هوش مصنوعی
                    </button>
                </form>
            </div>
        </div>

        <!-- TAB 3: AI Question Generator Simulator -->
        <div x-show="activeTab === 'simulator'" class="space-y-6">
            <div class="bg-slate-900 rounded-3xl p-8 border border-white/10 space-y-6">
                <div>
                    <h3 class="text-base font-black text-white flex items-center gap-2 mb-1">
                        <span>🧪</span>
                        <span>محیط آزمون‌ساز زنده هوش مصنوعی (شبیه‌ساز RAG / NotebookLM)</span>
                    </h3>
                    <p class="text-xs text-slate-400">
                        در این بخش می‌توانید نحوه ساخت سوال هوشمند از روی کتاب درسی را تست کرده و خروجی را بررسی فرمایید.
                    </p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 bg-slate-950 p-4 rounded-2xl border border-white/5">
                    <div>
                        <label class="text-[10px] text-slate-400 font-bold block mb-1">درس انتخابی:</label>
                        <input type="text" x-model="testSubject" class="w-full bg-slate-800 border border-white/15 text-white rounded-xl px-3 py-2 text-xs">
                    </div>
                    <div>
                        <label class="text-[10px] text-slate-400 font-bold block mb-1">فصل مورد سنجش:</label>
                        <input type="text" x-model="testChapter" class="w-full bg-slate-800 border border-white/15 text-white rounded-xl px-3 py-2 text-xs">
                    </div>
                    <div>
                        <label class="text-[10px] text-slate-400 font-bold block mb-1">تعداد سوال تستی:</label>
                        <select x-model="testCount" class="w-full bg-slate-800 border border-white/15 text-white rounded-xl px-3 py-2 text-xs">
                            <option value="2">۲ سوال</option>
                            <option value="3">۳ سوال (استاندارد)</option>
                            <option value="5">۵ سوال</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-[10px] text-slate-400 font-bold block mb-1">سطح دشواری:</label>
                        <select x-model="testLevel" class="w-full bg-slate-800 border border-white/15 text-white rounded-xl px-3 py-2 text-xs">
                            <option value="ساده">ساده (مفاهیم پایه)</option>
                            <option value="متوسط">متوسط (امتحانات نهایی)</option>
                            <option value="کنکوری">کنکوری و ترکیبی</option>
                        </select>
                    </div>
                </div>

                <button @click="
                    simulating = true;
                    simulatedQuestions = [];
                    setTimeout(() => {
                        simulating = false;
                        simulatedQuestions = [
                            {
                                id: 1,
                                q: 'در فرآیند گوارش مکانیکی و شیمیایی انسان، آنزیم پپسین برای فعالیت بهینه به کدام محیط اسیدی با چه ماده‌ای ترشح‌شده از سلول‌های حاشیه‌ای معده نیاز دارد؟',
                                options: ['اسید هیدروکلریک (HCl)', 'اسید سولفوریک', 'اسید سیتریک', 'اسید استیک'],
                                correct: 0,
                                analysis: 'سلول‌های حاشیه‌ای غدد معده اسید هیدروکلریک ترشح می‌کنند که باعث فعال شدن پپسینوژن و تبدیل آن به پپسین فعال می‌شود.'
                            },
                            {
                                id: 2,
                                q: 'کدام بخش از دستگاه گوارش انسان بیشترین سطح جذب مواد غذایی را به دلیل وجود چین‌ها، پرزها و ریزپرزها به خود اختصاص داده است؟',
                                options: ['معده', 'روده باریک (دوازدهه و تهی‌روده)', 'روده بزرگ', 'کیسه صفرا'],
                                correct: 1,
                                analysis: 'سطح داخلی روده باریک به علت داشتن چین‌های حلقوی و پرزها سطحی معادل یک زمین تنیس برای جذب ایجاد می‌کند.'
                            },
                            {
                                id: 3,
                                q: 'کدام ویتامین برای جذب در انتهای روده باریک، به فاکتور داخلی مترشحه از معده وابسته است؟',
                                options: ['ویتامین C', 'ویتامین D', 'ویتامین B12', 'ویتامین A'],
                                correct: 2,
                                analysis: 'فاکتور داخلی معده به ویتامین B12 متصل شده و آن را در برابر آنزیم‌های گوارشی تا رسیدن به انتهای تهی‌روده محافظت می‌کند.'
                            }
                        ].slice(0, testCount);
                    }, 1000);
                " class="w-full py-3 bg-gradient-to-r from-indigo-600 to-purple-600 hover:from-indigo-500 hover:to-purple-500 text-white font-black rounded-2xl text-xs transition-all shadow-lg">
                    ✨ اجرای شبیه‌ساز و استخراج سوالات هوشمند از کتاب
                </button>

                <!-- Loading State -->
                <div x-show="simulating" class="text-center py-8 space-y-3">
                    <div class="inline-block animate-spin text-2xl">⚙️</div>
                    <p class="text-xs text-indigo-300 font-bold">هوش مصنوعی در حال بازخوانی متن کتاب و فرمول‌بندی سوالات تستی و پاسخنامه تشریحی است...</p>
                </div>

                <!-- Simulation Output -->
                <div x-show="!simulating && simulatedQuestions.length > 0" class="space-y-4 pt-4 border-t border-white/10">
                    <h4 class="text-xs font-black text-emerald-400 flex items-center gap-1.5">
                        <span>✅</span> خروجی موفق هوش مصنوعی (آماده نمایش به دانش‌آموز):
                    </h4>

                    <template x-for="(item, qIndex) in simulatedQuestions" :key="item.id">
                        <div class="bg-slate-950 p-6 rounded-2xl border border-white/10 space-y-3">
                            <div class="flex items-start gap-2">
                                <span class="bg-indigo-600 text-white font-bold text-[11px] px-2 py-0.5 rounded-md" x-text="'سوال ' + (qIndex + 1)"></span>
                                <p class="text-xs font-bold text-white leading-relaxed" x-text="item.q"></p>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-2">
                                <template x-for="(opt, optIndex) in item.options" :key="optIndex">
                                    <div class="p-2.5 rounded-xl border text-[11px] font-bold"
                                         :class="optIndex === item.correct ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300' : 'bg-slate-900 border-white/5 text-slate-400'">
                                        <span x-text="'گزینه ' + (optIndex + 1) + ') '"></span>
                                        <span x-text="opt"></span>
                                    </div>
                                </template>
                            </div>

                            <div class="bg-indigo-500/10 p-3 rounded-xl border border-indigo-500/20 text-[11px] text-indigo-300">
                                <strong>پاسخ تشریحی هوش مصنوعی:</strong>
                                <span x-text="item.analysis"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </div>
        </div>

        <!-- TAB 4: Python Guide for Viana -->
        <div x-show="activeTab === 'python_guide'" class="max-w-3xl mx-auto space-y-6">
            <div class="bg-slate-900 rounded-3xl p-8 border border-white/10 space-y-6">
                <div class="flex items-center gap-3">
                    <div class="w-10 h-10 bg-yellow-500/20 text-yellow-400 rounded-xl flex items-center justify-center text-xl font-bold">🐍</div>
                    <div>
                        <h3 class="text-sm font-black text-white">راهنمای فنی برنامه‌نویسی پایتون (ویژه سرکار خانم ویانا وحیدی)</h3>
                        <p class="text-[11px] text-slate-400">ساختار داده‌های استاندارد برای اتصال به موتور Gemini / NotebookLM</p>
                    </div>
                </div>

                <div class="bg-slate-950 p-6 rounded-2xl border border-white/10 space-y-4 text-xs leading-relaxed text-slate-300">
                    <h4 class="font-bold text-white text-xs">معماری سیستم آزمون‌یار هوشمند بنیاد:</h4>
                    <ol class="list-decimal list-inside space-y-2 text-slate-400">
                        <li><strong>گام ۱ (ورودی کتاب):</strong> متن PDF کتاب درسی یا جزوه فصل مورد نظر با کتابخانه <code class="text-indigo-400 font-mono">pypdf</code> یا <code class="text-indigo-400 font-mono">pdfplumber</code> خوانده می‌شود.</li>
                        <li><strong>گام ۲ (پرامپت مهندسی‌شده):</strong> متن فصل همراه با ساختار خروجی استاندارد به API هوش مصنوعی ارسال می‌شود.</li>
                        <li><strong>گام ۳ (خروجی JSON ساختاریافته):</strong> هوش مصنوعی پاسخ را در قالب JSON استاندارد بازمی‌گرداند تا در پایگاه داده ذخیره و در پنل دانش‌آموز نمایش داده شود.</li>
                    </ol>

                    <div class="bg-slate-900 p-4 rounded-xl font-mono text-[11px] text-emerald-400 overflow-x-auto dir-ltr text-left">
# نمونه کد پایتون جهت ساخت سوال از فصل کتاب
import json
import google.generativeai as genai

genai.configure(api_key="YOUR_GEMINI_API_KEY")
model = genai.GenerativeModel('gemini-1.5-flash')

prompt = """
بر اساس متن فصل پیوست‌شده، دقیقاً ۳ سوال چهارگزینه‌ای مفهومی طراحی کن.
خروجی فقط و فقط به صورت JSON معتبر باشد:
[
  {
    "question": "متن سوال",
    "options": ["گزینه ۱", "گزینه ۲", "گزینه ۳", "گزینه ۴"],
    "correct_index": 0,
    "explanation": "پاسخ تشریحی کامل مستند به صفحه کتاب"
  }
]
"""
                    </div>
                </div>
            </div>
        </div>

    </main>

</body>
</html>
