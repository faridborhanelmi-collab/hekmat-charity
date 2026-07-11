<?php
session_start();
require_once 'includes/db.php';

// Auth Guard - only students can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header("Location: academy.php");
    exit();
}

$student_id = $_SESSION['user_id'];

// Get Student Data
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    session_destroy();
    header("Location: academy.php");
    exit();
}

// Set default alias and avatar if empty
$alias_name = $student['alias_name'] ?: 'دانش‌پژوه #' . $student['id'];
$avatar_url = $student['avatar_url'] ?: 'https://api.dicebear.com/7.x/bottts/svg?seed=' . urlencode($alias_name);

// Handle POST actions
$message_status = '';
$message_type = ''; // 'success' or 'error'

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'upload_report') {
        $desc = trim($_POST['description'] ?? 'کارنامه تحصیلی');
        if (isset($_FILES['report_file']) && $_FILES['report_file']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['report_file']['tmp_name'];
            $orig_name = $_FILES['report_file']['name'];
            $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
            
            // Validate extension
            $allowed = ['pdf', 'png', 'jpg', 'jpeg'];
            if (!in_array($ext, $allowed)) {
                $message_status = 'فرمت فایل مجاز نیست. فقط فایل‌های PDF, PNG, JPG مجاز هستند.';
                $message_type = 'error';
            } else {
                $new_name = 'report_' . $student_id . '_' . time() . '.' . $ext;
                $dest = 'uploads/' . $new_name;
                
                if (!is_dir('uploads')) {
                    mkdir('uploads', 0777, true);
                }
                
                if (move_uploaded_file($file_tmp, $dest)) {
                    $ins = $pdo->prepare("INSERT INTO documents (owner_type, owner_id, file_path, file_name, upload_date, description) VALUES ('student', ?, ?, ?, ?, ?)");
                    $ins->execute([
                        $student_id,
                        $dest,
                        $orig_name,
                        date('Y/m/d'),
                        $desc
                    ]);
                    $message_status = 'کارنامه شما با موفقیت آپلود شد و در اختیار همکاران آموزش قرار گرفت.';
                    $message_type = 'success';
                } else {
                    $message_status = 'خطا در ذخیره‌سازی کارنامه روی سرور رخ داد.';
                    $message_type = 'error';
                }
            }
        } else {
            $message_status = 'خطا در بارگذاری فایل. لطفاً مجدداً تلاش کنید.';
            $message_type = 'error';
        }
    } elseif ($_POST['action'] === 'send_message') {
        $msg_text = trim($_POST['message_text'] ?? '');
        $sponsorship_id = (int)($_POST['sponsorship_id'] ?? 0);
        
        if ($msg_text !== '' && $sponsorship_id > 0) {
            $ins = $pdo->prepare("INSERT INTO sponsorship_messages (sponsorship_id, sender_type, message_text, status, created_at) VALUES (?, 'student', ?, 'pending', ?)");
            $ins->execute([
                $sponsorship_id,
                $msg_text,
                date('Y/m/d H:i')
            ]);
            $message_status = 'گزارش رشد و پیام شما ثبت شد. پس از تایید مدیریت برای منتور ارسال خواهد شد.';
            $message_type = 'success';
        } else {
            $message_status = 'متن گزارش رشد نمی‌تواند خالی باشد.';
            $message_type = 'error';
        }
    }
}

// Fetch Active Sponsorship
$sponsorship_stmt = $pdo->prepare("SELECT * FROM sponsorships WHERE student_id = ? AND status = 'active' LIMIT 1");
$sponsorship_stmt->execute([$student_id]);
$active_spon = $sponsorship_stmt->fetch();

$messages = [];
if ($active_spon) {
    // Show approved messages, or pending messages sent by the student themselves
    $msg_stmt = $pdo->prepare("SELECT * FROM sponsorship_messages WHERE sponsorship_id = ? AND (status = 'approved' OR sender_type = 'student') ORDER BY id ASC");
    $msg_stmt->execute([$active_spon['id']]);
    $messages = $msg_stmt->fetchAll();
}

// Fetch Grade Reports
$docs_stmt = $pdo->prepare("SELECT * FROM documents WHERE owner_type = 'student' AND owner_id = ? ORDER BY id DESC");
$docs_stmt->execute([$student_id]);
$documents = $docs_stmt->fetchAll();

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: academy.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>آکادمی استعدادهای حکمت | پنل دانش‌پژوهان</title>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@100;300;400;500;700;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Vazirmatn', 'sans-serif'] },
                    colors: {
                        academy: {
                            950: '#020617',
                            900: '#0f172a',
                            800: '#1e293b',
                            700: '#334155',
                            500: '#64748b',
                            teal: '#0d9488',
                            blue: '#2563eb'
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .glass-panel {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        .tab-btn.active {
            background-color: #0d9488;
            color: white;
            box-shadow: 0 4px 12px rgba(13, 148, 136, 0.3);
        }
        .chat-container::-webkit-scrollbar {
            width: 4px;
        }
        .chat-container::-webkit-scrollbar-thumb {
            background-color: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
        }
    </style>

    <!-- iOS PWA/Homescreen Setup -->
    <link rel="apple-touch-icon" href="logo.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="بنیاد حکمت">
    <link rel="icon" type="image/png" href="logo.png">
    <link rel="manifest" href="manifest.json">
</head>
<body class="bg-academy-950 text-slate-100 font-sans min-h-screen overflow-x-hidden"
    x-data="{
        activeTab: 'dashboard',
        showUploadModal: false
    }">

    <!-- Top Navigation -->
    <?php include 'includes/dashboard-nav.php'; ?>

    <!-- Main Container -->
    <div class="container mx-auto px-4 py-8 lg:py-12">
        
        <?php if ($message_status): ?>
            <div class="max-w-4xl mx-auto mb-6 p-4 rounded-2xl border text-center text-sm font-bold backdrop-blur-sm <?php echo $message_type === 'success' ? 'bg-emerald-500/15 border-emerald-500/40 text-emerald-300' : 'bg-red-500/15 border-red-500/40 text-red-300'; ?>">
                <?php echo htmlspecialchars($message_status); ?>
            </div>
        <?php endif; ?>

        <div class="max-w-6xl mx-auto flex flex-col lg:flex-row gap-8">
            
            <!-- Side Navigation Menu -->
            <aside class="w-full lg:w-64 flex flex-row lg:flex-col gap-2 overflow-x-auto pb-4 lg:pb-0 shrink-0">
                <button onclick="changeTab('dashboard')" id="btn-dashboard" class="tab-btn active w-full px-5 py-4 rounded-2xl font-black text-right text-sm transition-all flex items-center gap-3">
                    <span>🏠</span> داشبورد من
                </button>
                <button onclick="changeTab('courses')" id="btn-courses" class="tab-btn w-full px-5 py-4 rounded-2xl font-black text-right text-sm text-slate-400 hover:bg-white/5 transition-all flex items-center gap-3">
                    <span>📚</span> کلاس‌ها و ویدیوها
                </button>
                <button onclick="changeTab('reports')" id="btn-reports" class="tab-btn w-full px-5 py-4 rounded-2xl font-black text-right text-sm text-slate-400 hover:bg-white/5 transition-all flex items-center gap-3">
                    <span>📊</span> کارنامه‌ها و مدارک
                </button>
                <button onclick="changeTab('mentorship')" id="btn-mentorship" class="tab-btn w-full px-5 py-4 rounded-2xl font-black text-right text-sm text-slate-400 hover:bg-white/5 transition-all flex items-center gap-3 relative">
                    <span>🤝</span> ارتباط با منتور
                    <?php if ($active_spon): ?>
                        <span class="absolute left-4 w-2 h-2 bg-teal-500 rounded-full animate-ping"></span>
                    <?php endif; ?>
                </button>
                <button onclick="changeTab('ai-tutor')" id="btn-ai-tutor" class="tab-btn w-full px-5 py-4 rounded-2xl font-black text-right text-sm text-slate-400 hover:bg-white/5 transition-all flex items-center gap-3">
                    <span>🤖</span> دستیار علمی هوش مصنوعی
                </button>
            </aside>

            <!-- Content Area -->
            <div class="flex-1 min-w-0">
                
                <!-- 1. DASHBOARD TAB -->
                <div id="tab-dashboard" class="tab-content space-y-8">
                    <!-- Profile Intro Hero -->
                    <div class="glass-panel rounded-[3rem] p-8 lg:p-12 relative overflow-hidden flex flex-col md:flex-row items-center gap-8 shadow-2xl">
                        <div class="absolute -right-20 -top-20 w-80 h-80 bg-teal-500/5 rounded-full blur-3xl"></div>
                        <div class="relative w-32 h-32 md:w-40 md:w-40 rounded-full overflow-hidden bg-slate-800/80 border-4 border-teal-500/20 p-2 shrink-0">
                            <img src="<?php echo $avatar_url; ?>" alt="آواتار" class="w-full h-full object-contain">
                        </div>
                        <div class="text-center md:text-right space-y-3 relative z-10 flex-1">
                            <span class="bg-teal-500/10 text-teal-300 border border-teal-500/20 px-3 py-1 rounded-full text-xs font-bold">دانش‌پژوه بورس نخبگان حکمت</span>
                            <h2 class="text-3xl font-black text-slate-100"><?php echo htmlspecialchars($student['name'] . ' ' . $student['surname']); ?></h2>
                            <p class="text-slate-400 text-sm max-w-xl leading-relaxed">
                                خوش آمدید! در آکادمی حکمت شما با تکیه بر استعداد و تلاش خود بورس علمی دریافت کرده‌اید. برای افزایش رشد علمی، حتماً تکالیف، کارنامه‌ها و گزارش‌های درسی خود را مرتب ارسال کنید.
                            </p>
                        </div>
                    </div>

                    <!-- Cards Stats Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="glass-panel p-8 rounded-[2rem] text-center border border-white/5">
                            <div class="w-12 h-12 bg-teal-500/10 text-teal-400 rounded-xl flex items-center justify-center text-xl mx-auto mb-4">🏆</div>
                            <h4 class="text-xs font-bold text-slate-400">وضعیت بورس تحصیلی</h4>
                            <div class="text-lg font-black text-slate-100 mt-2">فعال (بورس تحصیلی رشد)</div>
                        </div>
                        <div class="glass-panel p-8 rounded-[2rem] text-center border border-white/5">
                            <div class="w-12 h-12 bg-indigo-500/10 text-indigo-400 rounded-xl flex items-center justify-center text-xl mx-auto mb-4">📖</div>
                            <h4 class="text-xs font-bold text-slate-400">پایه و رشته تحصیلی</h4>
                            <div class="text-lg font-black text-slate-100 mt-2"><?php echo htmlspecialchars($student['grade'] . ' - ' . ($student['field_of_study'] ?: 'عمومی')); ?></div>
                        </div>
                        <div class="glass-panel p-8 rounded-[2rem] text-center border border-white/5">
                            <div class="w-12 h-12 bg-purple-500/10 text-purple-400 rounded-xl flex items-center justify-center text-xl mx-auto mb-4">📨</div>
                            <h4 class="text-xs font-bold text-slate-400">منتور رشد علمی</h4>
                            <div class="text-lg font-black text-slate-100 mt-2"><?php echo $active_spon ? 'منتور رشد شما متصل است' : 'به زودی تخصیص می‌یابد'; ?></div>
                        </div>
                    </div>

                    <!-- Personal Profile Details -->
                    <div class="glass-panel p-8 rounded-[2.5rem] border border-white/5">
                        <h3 class="text-xl font-bold mb-6 text-slate-100 flex items-center gap-3">
                            <span class="w-2 h-6 bg-teal-500 rounded-full"></span> بیوگرافی رشد تحصیلی من
                        </h3>
                        <div class="grid md:grid-cols-2 gap-6">
                            <div>
                                <label class="text-xs text-slate-400 font-bold block mb-1">نام مستعار در آکادمی (جهت حفظ حریم خصوصی):</label>
                                <div class="bg-white/5 border border-white/5 rounded-xl px-4 py-3 text-sm text-teal-300 font-black">
                                    <?php echo htmlspecialchars($alias_name); ?>
                                </div>
                            </div>
                            <div>
                                <label class="text-xs text-slate-400 font-bold block mb-1">استعدادها و علایق در دیتابیس آکادمی:</label>
                                <div class="bg-white/5 border border-white/5 rounded-xl px-4 py-3 text-sm text-slate-200">
                                    <?php echo htmlspecialchars($student['talents'] ?: 'هنوز ثبت نشده است (در قسمت کارنامه بنویسید)'); ?>
                                </div>
                            </div>
                            <div class="md:col-span-2">
                                <label class="text-xs text-slate-400 font-bold block mb-1">رویاها و اهداف شغلی:</label>
                                <div class="bg-white/5 border border-white/5 rounded-xl px-4 py-3 text-sm text-slate-200 leading-relaxed">
                                    <?php echo htmlspecialchars($student['dreams'] ?: 'هنوز ثبت نشده است.'); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. COURSES TAB -->
                <div id="tab-courses" class="tab-content hidden space-y-6">
                    <h2 class="text-2xl font-black text-slate-100">ویدیوهای آموزشی فعال</h2>
                    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <!-- Card 1 -->
                        <div class="glass-panel rounded-2xl overflow-hidden border border-white/5 hover:border-teal-500/30 transition-all group">
                            <div class="aspect-video bg-slate-800/80 relative flex items-center justify-center text-slate-500">
                                <span class="text-4xl group-hover:scale-125 transition-transform">▶</span>
                            </div>
                            <div class="p-5">
                                <span class="text-[10px] text-teal-400 font-bold uppercase tracking-wider">ریاضیات</span>
                                <h3 class="font-bold text-slate-100 mt-1 mb-2">آموزش ریاضی پایه دهم - فصل اول</h3>
                                <p class="text-slate-400 text-xs mb-4">مجموعه ها و احتمال، بازه‌ها و فرمول‌ها</p>
                                <button class="w-full py-2 bg-white/5 hover:bg-teal-600 hover:text-white rounded-lg text-xs font-bold transition-all text-slate-300">شروع یادگیری</button>
                            </div>
                        </div>

                        <!-- Card 2 -->
                        <div class="glass-panel rounded-2xl overflow-hidden border border-white/5 hover:border-teal-500/30 transition-all group">
                            <div class="aspect-video bg-slate-800/80 relative flex items-center justify-center text-slate-500">
                                <span class="text-4xl group-hover:scale-125 transition-transform">▶</span>
                            </div>
                            <div class="p-5">
                                <span class="text-[10px] text-teal-400 font-bold uppercase tracking-wider">فیزیک</span>
                                <h3 class="font-bold text-slate-100 mt-1 mb-2">قوانین نیرو و حرکت نیوتن</h3>
                                <p class="text-slate-400 text-xs mb-4">بررسی قانون اول، دوم و سوم با تست کنکور</p>
                                <button class="w-full py-2 bg-white/5 hover:bg-teal-600 hover:text-white rounded-lg text-xs font-bold transition-all text-slate-300">شروع یادگیری</button>
                            </div>
                        </div>

                        <!-- Card 3 -->
                        <div class="glass-panel rounded-2xl overflow-hidden border border-white/5 hover:border-teal-500/30 transition-all group">
                            <div class="aspect-video bg-slate-800/80 relative flex items-center justify-center text-slate-500">
                                <span class="text-4xl group-hover:scale-125 transition-transform">▶</span>
                            </div>
                            <div class="p-5">
                                <span class="text-[10px] text-teal-400 font-bold uppercase tracking-wider">شیمی</span>
                                <h3 class="font-bold text-slate-100 mt-1 mb-2">ساختار اتم و جدول تناوبی</h3>
                                <p class="text-slate-400 text-xs mb-4">آشنایی با آرایش الکترونی و خواص عناصر</p>
                                <button class="w-full py-2 bg-white/5 hover:bg-teal-600 hover:text-white rounded-lg text-xs font-bold transition-all text-slate-300">شروع یادگیری</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. REPORTS TAB -->
                <div id="tab-reports" class="tab-content hidden space-y-8">
                    <div class="flex justify-between items-center gap-4">
                        <h2 class="text-2xl font-black text-slate-100">کارنامه‌های تحصیلی من</h2>
                        <button onclick="toggleUploadModal(true)" class="bg-teal-600 hover:bg-teal-500 text-white font-bold px-4 py-2.5 rounded-xl text-xs shadow-lg shadow-teal-500/20 transition-all flex items-center gap-2">
                            📤 آپلود کارنامه جدید
                        </button>
                    </div>

                    <!-- Modal for upload -->
                    <div id="upload-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/65 backdrop-blur-sm p-4">
                        <div class="bg-academy-900 border border-white/10 w-full max-w-md rounded-[2.5rem] overflow-hidden p-8 shadow-2xl">
                            <div class="flex justify-between items-center mb-6">
                                <h3 class="text-lg font-bold text-slate-100">آپلود سند یا کارنامه تحصیلی</h3>
                                <button onclick="toggleUploadModal(false)" class="text-slate-400 hover:text-white text-xl">✕</button>
                            </div>
                            <form method="POST" action="" enctype="multipart/form-data" class="space-y-6">
                                <input type="hidden" name="action" value="upload_report">
                                
                                <div>
                                    <label class="block text-xs font-bold text-slate-400 mb-2">توضیح کارنامه (مثال: کارنامه نوبت اول کلاس دهم)</label>
                                    <input type="text" name="description" required placeholder="کارنامه نوبت اول دی ماه..."
                                        class="w-full bg-white/5 border border-white/10 text-white placeholder-white/30 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-teal-500 transition-all">
                                </div>
                                
                                <div>
                                    <label class="block text-xs font-bold text-slate-400 mb-2">انتخاب فایل کارنامه (PDF یا عکس)</label>
                                    <div class="relative border-2 border-dashed border-white/10 rounded-2xl p-6 text-center hover:border-teal-500 transition-all cursor-pointer">
                                        <input type="file" name="report_file" required accept=".pdf,.png,.jpg,.jpeg" class="absolute inset-0 opacity-0 cursor-pointer">
                                        <div class="text-slate-400">
                                            <span class="block text-2xl mb-2">📄</span>
                                            <span class="text-xs">کلیک کنید یا فایل را بکشید اینجا</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" class="w-full py-3 bg-teal-600 hover:bg-teal-500 text-white rounded-xl font-bold text-sm transition-all shadow-lg">
                                    ارسال و تایید کارنامه
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- List of reports -->
                    <div class="glass-panel rounded-2xl overflow-hidden border border-white/5">
                        <table class="w-full text-right border-collapse">
                            <thead>
                                <tr class="bg-white/5 text-slate-300 text-xs font-bold border-b border-white/5">
                                    <th class="p-4">ردیف</th>
                                    <th class="p-4">عنوان / شرح کارنامه</th>
                                    <th class="p-4">تاریخ آپلود</th>
                                    <th class="p-4 text-left">عملیات</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5 text-sm text-slate-300">
                                <?php if (empty($documents)): ?>
                                    <tr>
                                        <td colspan="4" class="p-8 text-center text-xs text-slate-500 font-bold">هیچ کارنامه‌ای تاکنون آپلود نکرده‌اید.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($documents as $index => $doc): ?>
                                    <tr class="hover:bg-white/5 transition-colors">
                                        <td class="p-4 text-xs font-black text-slate-500"><?php echo $index + 1; ?></td>
                                        <td class="p-4 font-bold text-slate-200"><?php echo htmlspecialchars($doc['description']); ?></td>
                                        <td class="p-4 text-xs"><?php echo htmlspecialchars($doc['upload_date']); ?></td>
                                        <td class="p-4 text-left">
                                            <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank" class="bg-teal-500/10 text-teal-400 hover:bg-teal-500 hover:text-white px-3 py-1.5 rounded-lg text-xs font-bold transition-all inline-block">📄 دانلود فایل</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 4. MENTORSHIP TAB -->
                <div id="tab-mentorship" class="tab-content hidden space-y-6">
                    <h2 class="text-2xl font-black text-slate-100">ارتباط با منتور تحصیلی و رشد</h2>
                    
                    <?php if (!$active_spon): ?>
                        <div class="glass-panel p-8 rounded-[2rem] border border-white/5 text-center text-slate-400">
                            <span class="text-4xl block mb-4">⌛</span>
                            بخش منتورینگ به زودی و پس از بررسی مدارک تحصیلی شما فعال خواهد شد. شما بلافاصله از تخصیص منتور رشد مطلع خواهید شد.
                        </div>
                    <?php else: ?>
                        <div class="glass-panel rounded-[2rem] border border-white/5 overflow-hidden flex flex-col h-[550px]">
                            <!-- Chat Header -->
                            <div class="bg-white/5 p-4 border-b border-white/5 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-teal-500/10 rounded-full flex items-center justify-center text-xl">👤</div>
                                    <div>
                                        <h4 class="font-bold text-sm text-slate-200">منتور رشد علمی شما</h4>
                                        <p class="text-[10px] text-teal-400 font-bold">پشتیبان بورس حکمت</p>
                                    </div>
                                </div>
                                <span class="bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 px-3 py-1 rounded-full text-[10px] font-bold">ارتباط امن فعال</span>
                            </div>

                            <!-- Chat Messages list -->
                            <div class="flex-1 overflow-y-auto p-6 space-y-4 chat-container bg-slate-900/35">
                                <div class="bg-teal-500/10 border border-teal-500/20 text-teal-200 text-xs p-4 rounded-2xl leading-relaxed max-w-xl mx-auto text-center">
                                    خوش آمدید! در این بخش می‌توانید «گزارش برنامه‌ریزی ماهانه» یا «اهداف درسی» خود را بنویسید. منتور شما گزارش را مطالعه کرده و پاسخ‌های راهنمایی برایتان ارسال خواهد کرد. نامه‌ها پس از تایید مدیریت ارسال می‌شوند.
                                </div>

                                <?php foreach ($messages as $msg): ?>
                                    <?php if ($msg['sender_type'] === 'student'): ?>
                                        <!-- Student Message -->
                                        <div class="flex justify-end items-start gap-3">
                                            <div class="flex flex-col items-end gap-1 max-w-[75%]">
                                                <div class="bg-teal-700 text-white rounded-2xl rounded-tr-none px-4 py-3 text-sm leading-loose">
                                                    <?php echo htmlspecialchars($msg['message_text']); ?>
                                                </div>
                                                <div class="flex items-center gap-2 text-[8px] text-slate-500 font-bold px-1">
                                                    <span><?php echo htmlspecialchars($msg['created_at']); ?></span>
                                                    <?php if ($msg['status'] === 'pending'): ?>
                                                        <span class="text-amber-400 bg-amber-500/10 px-2 py-0.5 rounded-full">در انتظار تایید مدیریت</span>
                                                    <?php else: ?>
                                                        <span class="text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded-full">ارسال شده به منتور</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="w-8 h-8 rounded-full bg-slate-800 text-xs flex items-center justify-center font-bold text-teal-300 border border-teal-500/20 shrink-0">من</div>
                                        </div>
                                    <?php else: ?>
                                        <!-- Mentor Message -->
                                        <div class="flex justify-start items-start gap-3">
                                            <div class="w-8 h-8 rounded-full bg-teal-500/10 text-xs flex items-center justify-center font-bold text-teal-400 shrink-0">م</div>
                                            <div class="flex flex-col items-start gap-1 max-w-[75%]">
                                                <div class="bg-slate-800 text-slate-200 rounded-2xl rounded-tl-none px-4 py-3 text-sm leading-loose border border-white/5">
                                                    <?php echo htmlspecialchars($msg['message_text']); ?>
                                                </div>
                                                <span class="text-[8px] text-slate-500 font-bold px-1"><?php echo htmlspecialchars($msg['created_at']); ?></span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>

                            <!-- Chat Input Form -->
                            <form method="POST" action="" class="bg-white/5 p-4 border-t border-white/5 flex gap-3">
                                <input type="hidden" name="action" value="send_message">
                                <input type="hidden" name="sponsorship_id" value="<?php echo $active_spon['id']; ?>">
                                <textarea name="message_text" required placeholder="گزارش رشد تحصیلی، برنامه‌ریزی یا پیام خود را اینجا بنویسید..." rows="1"
                                    class="flex-1 bg-white/5 border border-white/10 text-white placeholder-white/30 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-teal-500 transition-all resize-none"></textarea>
                                <button type="submit" class="bg-teal-600 hover:bg-teal-500 text-white font-bold px-6 rounded-xl text-xs transition-all shadow-md">ارسال گزارش</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- 5. AI TUTOR TAB -->
                <div id="tab-ai-tutor" class="tab-content hidden space-y-6">
                    <div class="glass-panel rounded-[2rem] border border-white/5 overflow-hidden flex flex-col h-[550px]">
                        <!-- Tutor Header -->
                        <div class="bg-white/5 p-4 border-b border-white/5 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-teal-500/10 rounded-full flex items-center justify-center text-xl">🤖</div>
                                <div>
                                    <h4 class="font-bold text-sm text-slate-200">دستیار هوش علمی و تحصیلی حکمت</h4>
                                    <p class="text-[10px] text-teal-400 font-bold">پاسخگوی ۲۴ ساعته رفع اشکال درسی</p>
                                </div>
                            </div>
                            <span class="bg-teal-500/15 text-teal-300 px-3 py-1 rounded-full text-[10px] font-bold border border-teal-500/20">نسخه آزمایشی (بتا)</span>
                        </div>

                        <!-- Chat Area -->
                        <div id="ai-chat-box" class="flex-1 overflow-y-auto p-6 space-y-4 chat-container bg-slate-900/35">
                            <!-- Welcome -->
                            <div class="flex justify-start items-start gap-3">
                                <div class="w-8 h-8 rounded-full bg-teal-500/15 text-xs flex items-center justify-center text-teal-400 shrink-0">🤖</div>
                                <div class="flex flex-col items-start gap-1 max-w-[85%]">
                                    <div class="bg-slate-800 text-slate-200 rounded-2xl rounded-tl-none px-4 py-3 text-sm leading-loose border border-white/5">
                                        سلام! من دستیار هوشمند علمی آکادمی حکمت هستم. برای حل سوالات ریاضی، فیزیک، شیمی، عربی یا هر درس دیگری می‌توانید روی من حساب کنید. سوال درسی خود را بپرسید یا یکی از موضوعات آماده زیر را انتخاب کنید:
                                        
                                        <div class="mt-4 flex flex-wrap gap-2">
                                            <button onclick="askAI('فرمول شتاب نیوتن چیست؟')" class="bg-teal-500/10 hover:bg-teal-500 hover:text-white border border-teal-500/20 text-teal-300 text-xs px-3 py-1.5 rounded-full transition-all">🍎 فرمول شتاب نیوتن؟</button>
                                            <button onclick="askAI('قوانین فعل ماضی در عربی را توضیح بده')" class="bg-teal-500/10 hover:bg-teal-500 hover:text-white border border-teal-500/20 text-teal-300 text-xs px-3 py-1.5 rounded-full transition-all">📚 ماضی در عربی؟</button>
                                            <button onclick="askAI('نحوه حل کردن معادله درجه ۲')" class="bg-teal-500/10 hover:bg-teal-500 hover:text-white border border-teal-500/20 text-teal-300 text-xs px-3 py-1.5 rounded-full transition-all">📐 معادله درجه ۲؟</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Chat Input Form -->
                        <div class="bg-white/5 p-4 border-t border-white/5 flex gap-3">
                            <input id="ai-user-input" type="text" placeholder="سوال درسی خود را اینجا بپرسید..."
                                class="flex-1 bg-white/5 border border-white/10 text-white placeholder-white/30 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-teal-500 transition-all">
                            <button onclick="submitAICustom()" class="bg-teal-600 hover:bg-teal-500 text-white font-bold px-6 rounded-xl text-xs transition-all shadow-md">ارسال</button>
                        </div>
                    </div>
                </div>

            </div>

        </div>

    </div>

    <!-- JS Handling Tabs and Simulated AI Tutor -->
    <script>
        function changeTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            // Remove active style from all buttons
            document.querySelectorAll('.tab-btn').forEach(el => {
                el.classList.remove('active', 'bg-teal-600', 'text-white');
                el.classList.add('text-slate-400');
            });

            // Show active tab
            document.getElementById('tab-' + tabName).classList.remove('hidden');
            // Add active style to active button
            const activeBtn = document.getElementById('btn-' + tabName);
            activeBtn.classList.add('active', 'bg-teal-600', 'text-white');
            activeBtn.classList.remove('text-slate-400');
        }

        function toggleUploadModal(show) {
            const modal = document.getElementById('upload-modal');
            if (show) modal.classList.remove('hidden');
            else modal.classList.add('hidden');
        }

        // Mock AI Tutor Responses
        const mockResponses = {
            'فرمول شتاب نیوتن چیست؟': `**فرمول شتاب نیوتن (قانون دوم نیوتن):**\n\nطبق قانون دوم نیوتن، شتاب ($a$) یک جسم رابطه مستقیم با نیروی خالص ($F$) وارد بر آن و رابطه معکوس با جرم ($m$) جسم دارد.\n\nفرمول ریاضی آن به این صورت است:\n\n\\[F = m \\cdot a\\]\n\nیا به عبارتی:\n\n\\[a = \\frac{F}{m}\\]\n\n*   **F:** نیروی خالص بر حسب نیوتن ($N$)\n*   **m:** جرم جسم بر حسب کیلوگرم ($kg$)\n*   **a:** شتاب جسم بر حسب متر بر مجذور ثانیه ($m/s^2$)\n\n**مثال:** اگر نیروی ۲۰ نیوتن به جسمی با جرم ۵ کیلوگرم وارد شود، شتاب چقدر است؟\n\\[a = \\frac{20}{5} = 4\\text{ m/s}^2\\]`,
            
            'قوانین فعل ماضی در عربی را توضیح بده': `**قواعد فعل ماضی در زبان عربی:**\n\nفعل ماضی فعلی است که بر انجام کاری در زمان گذشته دلالت دارد. ریشه اصلی فعل ماضی معمولاً ۳ حرفی است (ثلاثی مجرد) مانند **«کَتَبَ»** (نوشت).\n\nفعل ماضی دارای **۱۴ صیغه** است که صیغه اول آن مبنای صرف بقیه است. تقسیم‌بندی صیغه‌ها:\n\n1.  **غائب (مذکر):** کَتَبَ (نوشت)، کَتَبا (نوشتند - ۲نفر)، کَتَبوا (نوشتند - جمع)\n2.  **غائبة (مونث):** کَتَبَتْ، کَتَبَتا، کَتَبْنَ\n3.  **مخاطب (مذکر):** کَتَبْتَ، کَتَبْتُما، کَتَبْتُمْ\n4.  **مخاطبة (مونث):** کَتَبْتِ، کَتَبْتُما، کَتَبْتُنَّ\n5.  **متکلم (گوینده):** کَتَبْتُ (نوشتم - متکلم وحده)، کَتَبْنا (نوشتیم - متکلم مع‌الغیر)\n\n**نکته مهم:** حروف انتهای فعل ماضی نشان‌دهنده فاعل (ضمیر متصل فاعلی) هستند. برای مثال در صیغه «کَتَبْنا»، ضمیر «نا» فاعل کار است.`,
            
            'نحوه حل کردن معادله درجه ۲': `**فرمول عمومی حل معادلات درجه دوم:**\n\nشکل کلی یک معادله درجه دوم به صورت زیر است:\n\n\\[ax^2 + bx + c = 0\\]\n\nکه در آن $a$ و $b$ و $c$ اعداد حقیقی هستند و $a \\neq 0$ است.\n\nبهترین راه حل استفاده از روش **دلتا (\\(\\Delta\\))** است:\n\n\\[\\Delta = b^2 - 4ac\\]\n\nسپس بر اساس مقدار دلتا سه حالت داریم:\n\n1.  **اگر \\(\\Delta > 0\\):** معادله دارای **دو ریشه حقیقی متمایز** است:\n    \\[x_{1,2} = \\frac{-b \\pm \\sqrt{\\Delta}}{2a}\\]\n2.  **اگر \\(\\Delta = 0\\):** معادله دارای **یک ریشه مضاعف** است:\n    \\[x = \\frac{-b}{2a}\\]\n3.  **اگر \\(\\Delta < 0\\):** معادله **ریشه حقیقی ندارد** (ریشه‌ها در اعداد مختلط هستند).`
        };

        function askAI(promptText) {
            const chatBox = document.getElementById('ai-chat-box');
            
            // 1. Add user message
            const userMsgHtml = `
                <div class="flex justify-end items-start gap-3">
                    <div class="flex flex-col items-end gap-1 max-w-[75%]">
                        <div class="bg-teal-700 text-white rounded-2xl rounded-tr-none px-4 py-3 text-sm leading-loose">
                            ${promptText}
                        </div>
                    </div>
                    <div class="w-8 h-8 rounded-full bg-slate-800 text-xs flex items-center justify-center font-bold text-teal-300 border border-teal-500/20 shrink-0">من</div>
                </div>
            `;
            chatBox.insertAdjacentHTML('beforeend', userMsgHtml);
            chatBox.scrollTop = chatBox.scrollHeight;

            // 2. Add AI Typing simulator
            const typingId = 'typing-' + Date.now();
            const aiTypingHtml = `
                <div id="${typingId}" class="flex justify-start items-start gap-3">
                    <div class="w-8 h-8 rounded-full bg-teal-500/15 text-xs flex items-center justify-center text-teal-400 shrink-0">🤖</div>
                    <div class="bg-slate-800 text-slate-400 rounded-2xl rounded-tl-none px-4 py-3 text-sm border border-white/5 animate-pulse">
                        در حال فکر کردن و فرمول‌نویسی...
                    </div>
                </div>
            `;
            chatBox.insertAdjacentHTML('beforeend', aiTypingHtml);
            chatBox.scrollTop = chatBox.scrollHeight;

            // 3. Generate response
            setTimeout(() => {
                const typingEl = document.getElementById(typingId);
                if (typingEl) typingEl.remove();

                const responseText = mockResponses[promptText] || `**پاسخ دستیار علمی:**\n\nسوال شما در مورد: *"${promptText}"* دریافت شد. \n\nاین یک سیستم هوشمند شبیه‌سازی در آکادمی حکمت است. در نسخه نهایی، هوش مصنوعی متصل به کتاب درسی گام به گام فرمول‌ها و مفاهیم ریاضی و تجربی را بر اساس سیستم آموزشی کشور برای شما تشریح خواهد کرد. جهت نمونه سوالات دیگر می‌توانید روی دکمه‌های آماده کلیک کنید.`;
                
                // Format text lines nicely
                const formattedText = responseText.replace(/\n/g, '<br>');

                const aiMsgHtml = `
                    <div class="flex justify-start items-start gap-3 animate-fade-in">
                        <div class="w-8 h-8 rounded-full bg-teal-500/15 text-xs flex items-center justify-center text-teal-400 shrink-0">🤖</div>
                        <div class="flex flex-col items-start gap-1 max-w-[85%]">
                            <div class="bg-slate-800 text-slate-200 rounded-2xl rounded-tl-none px-4 py-3 text-sm leading-loose border border-white/5">
                                ${formattedText}
                            </div>
                        </div>
                    </div>
                `;
                chatBox.insertAdjacentHTML('beforeend', aiMsgHtml);
                chatBox.scrollTop = chatBox.scrollHeight;
            }, 1200);
        }

        function submitAICustom() {
            const input = document.getElementById('ai-user-input');
            const val = input.value.trim();
            if (val) {
                askAI(val);
                input.value = '';
            }
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