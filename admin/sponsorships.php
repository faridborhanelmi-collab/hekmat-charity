<?php
session_start();
require_once '../includes/db.php';

// Access Control
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: ../login.php");
    exit();
}

$message_status = '';
$message_type = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'create_sponsorship') {
        $donor_id = (int)($_POST['donor_id'] ?? 0);
        $student_id = (int)($_POST['student_id'] ?? 0);
        $shares = (int)($_POST['shares'] ?? 1);
        $start_date = trim($_POST['start_date'] ?? '');
        
        if ($donor_id > 0 && $student_id > 0) {
            $chk = $pdo->prepare("SELECT id FROM sponsorships WHERE donor_id = ? AND student_id = ? AND status = 'active'");
            $chk->execute([$donor_id, $student_id]);
            if ($chk->fetch()) {
                $message_status = 'این حامی در حال حاضر پشتیبان این دانش‌پژوه می‌باشد.';
                $message_type = 'error';
            } else {
                $ins = $pdo->prepare("INSERT INTO sponsorships (donor_id, student_id, shares_count, start_date, status) VALUES (?, ?, ?, ?, 'active')");
                $ins->execute([$donor_id, $student_id, $shares, $start_date]);
                
                $message_status = 'بورس تحصیلی با موفقیت تعریف و ثبت گردید.';
                $message_type = 'success';
            }
        } else {
            $message_status = 'لطفاً حامی و دانش‌پژوه را به درستی انتخاب کنید.';
            $message_type = 'error';
        }
    } elseif ($_POST['action'] === 'approve_message') {
        $msg_id = (int)($_POST['message_id'] ?? 0);
        if ($msg_id > 0) {
            $upd = $pdo->prepare("UPDATE sponsorship_messages SET status = 'approved' WHERE id = ?");
            $upd->execute([$msg_id]);
            $message_status = 'پیام با موفقیت تایید و ارسال شد.';
            $message_type = 'success';
        }
    } elseif ($_POST['action'] === 'reject_message') {
        $msg_id = (int)($_POST['message_id'] ?? 0);
        if ($msg_id > 0) {
            $upd = $pdo->prepare("UPDATE sponsorship_messages SET status = 'rejected' WHERE id = ?");
            $upd->execute([$msg_id]);
            $message_status = 'پیام رد و حذف گردید.';
            $message_type = 'success';
        }
    } elseif ($_POST['action'] === 'update_student_profile') {
        $student_id = (int)($_POST['student_id'] ?? 0);
        $alias = trim($_POST['alias_name'] ?? '');
        $avatar = trim($_POST['avatar_url'] ?? '');
        $talents = trim($_POST['talents'] ?? '');
        $dreams = trim($_POST['dreams'] ?? '');
        
        if ($student_id > 0) {
            $upd = $pdo->prepare("UPDATE students SET alias_name = ?, avatar_url = ?, talents = ?, dreams = ? WHERE id = ?");
            $upd->execute([$alias, $avatar, $talents, $dreams, $student_id]);
            $message_status = 'پروفایل حریم خصوصی دانش‌پژوه با موفقیت به‌روزرسانی شد.';
            $message_type = 'success';
        }
    }
}

// Fetch all sponsorships
$sponsorships = $pdo->query("
    SELECT s.id as spon_id, s.shares_count, s.start_date, d.name as d_name, d.surname as d_surname, st.name as st_name, st.surname as st_surname, st.alias_name
    FROM sponsorships s
    JOIN donors d ON s.donor_id = d.id
    JOIN students st ON s.student_id = st.id
    WHERE s.status = 'active'
    ORDER BY s.id DESC
")->fetchAll();

// Fetch Pending Messages
$pending_messages = $pdo->query("
    SELECT m.id as msg_id, m.sender_type, m.message_text, m.created_at,
           d.name as d_name, d.surname as d_surname,
           st.name as st_name, st.surname as st_surname, st.alias_name
    FROM sponsorship_messages m
    JOIN sponsorships s ON m.sponsorship_id = s.id
    JOIN donors d ON s.donor_id = d.id
    JOIN students st ON s.student_id = st.id
    WHERE m.status = 'pending'
    ORDER BY m.id ASC
")->fetchAll();

// Donors & Students list for Autocomplete
$donors = $pdo->query("SELECT id, name, surname, phone FROM donors ORDER BY name ASC")->fetchAll();
$students = $pdo->query("SELECT id, name, surname, code, alias_name, avatar_url, talents, dreams FROM students ORDER BY name ASC")->fetchAll();

// Helper functions
function toFarsi($str) {
    $farsi = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    $latin = ['0','1','2','3','4','5','6','7','8','9'];
    return str_replace($latin, $farsi, $str);
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت بورس‌ها و منتورینگ | بنیاد حکمت</title>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@100;300;400;500;700;900&display=swap" rel="stylesheet">
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

    <!-- iOS PWA/Homescreen Setup -->
    <link rel="apple-touch-icon" href="logo.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="بنیاد حکمت">
    <link rel="icon" type="image/png" href="logo.png">
</head>
<body class="bg-gray-50 font-sans text-gray-800 antialiased"
    x-data="{
        showCreateModal: false,
        showEditStudentModal: false,
        
        donorSearch: '',
        selectedDonorId: '',
        selectedDonorName: '',
        donors: <?php echo htmlspecialchars(json_encode($donors)); ?>,
        
        studentSearch: '',
        selectedStudentId: '',
        selectedStudentName: '',
        students: <?php echo htmlspecialchars(json_encode($students)); ?>,
        
        // Student Profile Edit fields
        editStudentId: '',
        editAliasName: '',
        editAvatarUrl: '',
        editTalents: '',
        editDreams: '',

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
        },
        
        openEditProfile(student) {
            this.editStudentId = student.id;
            this.editAliasName = student.alias_name || '';
            this.editAvatarUrl = student.avatar_url || '';
            this.editTalents = student.talents || '';
            this.editDreams = student.dreams || '';
            this.showEditStudentModal = true;
        }
    }">

    <!-- Navigation -->
    <nav class="bg-white/80 backdrop-blur-md border-b sticky top-0 z-50">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <div class="flex items-center gap-4">
                <a href="index.php" class="text-primary-900 font-black text-xl">بنیاد حکمت</a>
                <span class="text-gray-300">/</span>
                <a href="index.php" class="text-gray-600 font-bold hover:underline">میز کار مدیریت</a>
                <span class="text-gray-300">/</span>
                <span class="font-bold text-gray-600 underline decoration-teal-400 decoration-2">مدیریت بورس‌ها و منتورینگ</span>
            </div>
            
            <a href="../admin-logout.php" class="bg-red-50 text-red-500 p-2 rounded-xl hover:bg-red-500 hover:text-white transition-all">🚪 خروج</a>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="container mx-auto px-6 py-12 max-w-6xl">
        
        <?php if ($message_status): ?>
            <div class="mb-8 p-4 rounded-2xl border text-center text-xs font-bold <?php echo $message_type === 'success' ? 'bg-teal-50 border-teal-200 text-teal-700' : 'bg-red-50 border-red-200 text-red-700'; ?>">
                <?php echo htmlspecialchars($message_status); ?>
            </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start mb-12">
            
            <!-- Statistics Card -->
            <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-gray-100 flex flex-col justify-between">
                <div>
                    <h3 class="text-sm font-bold text-gray-400 mb-2">تعداد بورس‌های تحصیلی فعال</h3>
                    <div class="text-4xl font-black text-primary-900"><?php echo toFarsi(count($sponsorships)); ?> <span class="text-sm text-gray-400">بورس</span></div>
                </div>
                <button @click="showCreateModal = true" class="w-full mt-8 py-3 bg-teal-600 hover:bg-teal-500 text-white font-bold rounded-2xl transition-all shadow-md">
                    + تعریف بورس تحصیلی جدید
                </button>
            </div>

            <!-- Message Queue Vetting Summary -->
            <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-gray-100 flex flex-col justify-between">
                <div>
                    <h3 class="text-sm font-bold text-gray-400 mb-2">پیام‌های منتورینگ در انتظار تایید</h3>
                    <div class="text-4xl font-black text-amber-500"><?php echo toFarsi(count($pending_messages)); ?> <span class="text-sm text-gray-400">پیام</span></div>
                </div>
                <a href="#vetting-section" class="w-full mt-8 py-3 bg-amber-50 text-amber-600 hover:bg-amber-100 font-bold rounded-2xl transition-all border border-amber-200 text-center">
                    🔍 بررسی صف پیام‌ها
                </a>
            </div>

            <!-- Fast student profiles edit helper -->
            <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-gray-100 flex flex-col justify-between">
                <div>
                    <h3 class="text-sm font-bold text-gray-400 mb-2">مدیریت حریم خصوصی مددجو</h3>
                    <p class="text-xs text-gray-500 mt-2 leading-relaxed">تعریف آواتارها و نام‌های مستعار برای نوجوانان جهت نمایش محرمانه و آبرومندانه به خیرین.</p>
                </div>
                <button @click="showEditStudentModal = true" class="w-full mt-6 py-3 bg-primary-900 text-white hover:bg-primary-800 font-bold rounded-2xl transition-all">
                    👤 ویرایش پروفایل حریم خصوصی
                </button>
            </div>
        </div>

        <!-- 1. Vetting Queue Section -->
        <section id="vetting-section" class="bg-white p-8 rounded-[2.5rem] border border-gray-100 shadow-sm mb-12">
            <h2 class="text-lg font-black text-primary-900 mb-6 flex items-center gap-2">
                <span class="w-2.5 h-2.5 bg-amber-500 rounded-full"></span> صف تایید پیام‌های منتورینگ و مکاتبه
            </h2>

            <?php if (empty($pending_messages)): ?>
                <p class="text-xs text-gray-400 font-bold text-center py-12">هیچ پیامی در صف تایید قرار ندارد. مکاتبات حریم خصوصی دانش‌پژوهان و خیرین منظم است.</p>
            <?php else: ?>
                <div class="space-y-6">
                    <?php foreach ($pending_messages as $msg): ?>
                    <div class="border border-gray-100 rounded-3xl p-6 bg-gray-50 flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                        <div class="space-y-3 flex-1">
                            <div class="flex items-center gap-3">
                                <?php if ($msg['sender_type'] === 'student'): ?>
                                    <span class="bg-teal-100 text-teal-700 px-3 py-1 rounded-full text-[10px] font-bold">ارسال کننده: دانش‌پژوه (<?php echo htmlspecialchars($msg['st_name'] . ' ' . $msg['st_surname']); ?> - مستعار: <?php echo htmlspecialchars($msg['alias_name'] ?: 'ندارد'); ?>)</span>
                                <?php else: ?>
                                    <span class="bg-indigo-100 text-indigo-700 px-3 py-1 rounded-full text-[10px] font-bold">ارسال کننده: خیر (<?php echo htmlspecialchars($msg['d_name'] . ' ' . $msg['d_surname']); ?>)</span>
                                <?php endif; ?>
                                <span class="text-[9px] text-gray-400 font-bold"><?php echo toFarsi($msg['created_at']); ?></span>
                            </div>
                            <div class="text-xs text-gray-800 leading-relaxed font-bold bg-white p-4 rounded-xl border border-gray-100">
                                <?php echo htmlspecialchars($msg['message_text']); ?>
                            </div>
                        </div>
                        <div class="flex gap-2 shrink-0">
                            <!-- Approve Form -->
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="approve_message">
                                <input type="hidden" name="message_id" value="<?php echo $msg['msg_id']; ?>">
                                <button type="submit" class="bg-emerald-500 hover:bg-emerald-600 text-white font-bold px-4 py-2 rounded-xl text-xs transition-all shadow-md">✓ تایید و ارسال</button>
                            </form>
                            <!-- Reject Form -->
                            <form method="POST" action="">
                                <input type="hidden" name="action" value="reject_message">
                                <input type="hidden" name="message_id" value="<?php echo $msg['msg_id']; ?>">
                                <button type="submit" class="bg-rose-500 hover:bg-rose-600 text-white font-bold px-4 py-2 rounded-xl text-xs transition-all shadow-md">✕ رد و حذف</button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <!-- 2. Active Sponsorships Section -->
        <section class="bg-white p-8 rounded-[2.5rem] border border-gray-100 shadow-sm">
            <h2 class="text-lg font-black text-primary-900 mb-6 flex items-center gap-2">
                <span class="w-2.5 h-2.5 bg-teal-500 rounded-full"></span> لیست بورس‌های تحصیلی فعال (اسپانسرشیپ)
            </h2>

            <div class="overflow-x-auto">
                <table class="w-full text-right border-collapse">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100 text-xs font-bold text-gray-500">
                            <th class="p-4">خیر (پشتیبان)</th>
                            <th class="p-4">دانش‌پژوه واقعی</th>
                            <th class="p-4">نام مستعار دانش‌پژوه</th>
                            <th class="p-4">تعداد سهام بورس</th>
                            <th class="p-4">تاریخ شروع بورس</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50 text-xs text-gray-700">
                        <?php if (empty($sponsorships)): ?>
                            <tr>
                                <td colspan="5" class="p-8 text-center text-gray-400 font-bold">بورسیه‌ای ثبت نشده است.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($sponsorships as $sp): ?>
                            <tr class="hover:bg-gray-50 transition-all">
                                <td class="p-4 font-bold text-primary-900"><?php echo htmlspecialchars($sp['d_name'] . ' ' . $sp['d_surname']); ?></td>
                                <td class="p-4"><?php echo htmlspecialchars($sp['st_name'] . ' ' . $sp['st_surname']); ?></td>
                                <td class="p-4 font-black text-teal-600"><?php echo htmlspecialchars($sp['alias_name'] ?: 'تعریف نشده'); ?></td>
                                <td class="p-4 font-bold text-gray-800"><?php echo toFarsi($sp['shares_count']); ?> سهم</td>
                                <td class="p-4 text-gray-400"><?php echo toFarsi($sp['start_date'] ?: '---'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <!-- CREATE SPONSORSHIP MODAL -->
        <div id="create-modal" x-show="showCreateModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/65 backdrop-blur-sm p-4" x-cloak>
            <div class="bg-white w-full max-w-lg rounded-[2.5rem] p-8 shadow-2xl border border-gray-100" @click.away="showCreateModal = false">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-black text-primary-900">تعریف بورس تحصیلی جدید (تخصیص حامی)</h3>
                    <button @click="showCreateModal = false" class="text-gray-400 hover:text-black text-xl">✕</button>
                </div>
                
                <form method="POST" action="" class="space-y-6">
                    <input type="hidden" name="action" value="create_sponsorship">
                    
                    <!-- Donor selection (Autocomplete) -->
                    <div class="relative">
                        <label class="block text-xs font-bold text-gray-400 mb-2">انتخاب خیر / حامی بورس</label>
                        <input type="text" x-model="donorSearch" placeholder="نام حامی یا تلفن را تایپ کنید..."
                            class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-xs focus:outline-none focus:border-teal-500">
                        <input type="hidden" name="donor_id" :value="selectedDonorId">
                        
                        <div x-show="donorSearch && !selectedDonorId" class="absolute z-10 w-full bg-white border border-gray-100 rounded-xl shadow-lg mt-1 max-h-40 overflow-y-auto">
                            <template x-for="d in filteredDonors" :key="d.id">
                                <button type="button" @click="selectedDonorId = d.id; selectedDonorName = d.name + ' ' + d.surname; donorSearch = selectedDonorName"
                                    class="w-full text-right px-4 py-3 text-xs hover:bg-gray-50 border-b border-gray-100 block">
                                    <span x-text="d.name + ' ' + d.surname"></span> (<span x-text="d.phone"></span>)
                                </button>
                            </template>
                        </div>
                        <div x-show="selectedDonorId" class="mt-2 text-[10px] text-teal-600 font-bold flex items-center justify-between bg-teal-50 p-2 rounded-lg">
                            <span>منتخب: <strong x-text="selectedDonorName"></strong></span>
                            <button type="button" @click="selectedDonorId = ''; selectedDonorName = ''; donorSearch = ''" class="text-red-500">حذف</button>
                        </div>
                    </div>

                    <!-- Student selection (Autocomplete) -->
                    <div class="relative">
                        <label class="block text-xs font-bold text-gray-400 mb-2">انتخاب مددجو / دانش‌پژوه بورس</label>
                        <input type="text" x-model="studentSearch" placeholder="نام دانش‌پژوه یا کد را تایپ کنید..."
                            class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-xs focus:outline-none focus:border-teal-500">
                        <input type="hidden" name="student_id" :value="selectedStudentId">
                        
                        <div x-show="studentSearch && !selectedStudentId" class="absolute z-10 w-full bg-white border border-gray-100 rounded-xl shadow-lg mt-1 max-h-40 overflow-y-auto">
                            <template x-for="s in filteredStudents" :key="s.id">
                                <button type="button" @click="selectedStudentId = s.id; selectedStudentName = s.name + ' ' + s.surname; studentSearch = selectedStudentName"
                                    class="w-full text-right px-4 py-3 text-xs hover:bg-gray-50 border-b border-gray-100 block">
                                    <span x-text="s.name + ' ' + s.surname"></span> (<span x-text="s.code"></span>)
                                </button>
                            </template>
                        </div>
                        <div x-show="selectedStudentId" class="mt-2 text-[10px] text-teal-600 font-bold flex items-center justify-between bg-teal-50 p-2 rounded-lg">
                            <span>منتخب: <strong x-text="selectedStudentName"></strong></span>
                            <button type="button" @click="selectedStudentId = ''; selectedStudentName = ''; studentSearch = ''" class="text-red-500">حذف</button>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-400 mb-2">تعداد سهام بورس (۱ تا ۵)</label>
                            <select name="shares" class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-xs focus:outline-none focus:border-teal-500">
                                <option value="1">۱ سهم بورس</option>
                                <option value="2">۲ سهم بورس</option>
                                <option value="3">۳ سهم بورس</option>
                                <option value="4">۴ سهم بورس</option>
                                <option value="5">۵ سهم (بورس کامل)</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-400 mb-2">تاریخ شروع بورس</label>
                            <input type="text" name="start_date" placeholder="۱۴۰۴/۰۱/۰۱" value="۱۴۰۴/۰۱/۰۱" required
                                class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-xs text-center focus:outline-none focus:border-teal-500">
                        </div>
                    </div>

                    <button type="submit" class="w-full py-3 bg-teal-600 hover:bg-teal-500 text-white font-bold rounded-2xl transition-all shadow-md">
                        ثبت و راه‌اندازی بورس تحصیلی
                    </button>
                </form>
            </div>
        </div>

        <!-- EDIT STUDENT PROFILE MODAL -->
        <div id="edit-student-modal" x-show="showEditStudentModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/65 backdrop-blur-sm p-4" x-cloak>
            <div class="bg-white w-full max-w-lg rounded-[2.5rem] p-8 shadow-2xl border border-gray-100" @click.away="showEditStudentModal = false">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-black text-primary-900">تنظیم پروفایل حریم خصوصی مددجویان</h3>
                    <button @click="showEditStudentModal = false" class="text-gray-400 hover:text-black text-xl">✕</button>
                </div>
                
                <!-- Quick Student Selection -->
                <div class="mb-6" x-show="!editStudentId">
                    <label class="block text-xs font-bold text-gray-400 mb-2">دانش‌پژوه را انتخاب کنید</label>
                    <select class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-xs focus:outline-none"
                        @change="let selected = students.find(s => s.id == $event.target.value); if(selected) openEditProfile(selected)">
                        <option value="">-- انتخاب کنید --</option>
                        <template x-for="s in students" :key="s.id">
                            <option :value="s.id" x-text="s.name + ' ' + s.surname"></option>
                        </template>
                    </select>
                </div>

                <form method="POST" action="" class="space-y-5" x-show="editStudentId">
                    <input type="hidden" name="action" value="update_student_profile">
                    <input type="hidden" name="student_id" :value="editStudentId">
                    
                    <div>
                        <label class="block text-xs font-bold text-gray-400 mb-2">نام مستعار (برای حفظ حریم خصوصی در پرتال خیر)</label>
                        <input type="text" name="alias_name" x-model="editAliasName" required placeholder="مثال: امید، باران..."
                            class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-xs focus:outline-none focus:border-teal-500">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-400 mb-2">آدرس آواتار انتزاعی (یا خالی بگذارید برای تولید خودکار)</label>
                        <input type="text" name="avatar_url" x-model="editAvatarUrl" placeholder="https://api.dicebear.com/..."
                            class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-xs focus:outline-none focus:border-teal-500">
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-400 mb-2">استعدادها و مهارت‌های ویژه</label>
                        <textarea name="talents" x-model="editTalents" placeholder="مثال: برنامه‌نویسی پایتون، حافظ کل قرآن، المپیاد شیمی..." rows="2"
                            class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-xs focus:outline-none focus:border-teal-500 resize-none"></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-400 mb-2">آرزوها و رویاهای شغلی مددجو</label>
                        <textarea name="dreams" x-model="editDreams" placeholder="مثال: مهندس نرم‌افزار، کارآفرینی در حوزه پزشکی..." rows="2"
                            class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-xs focus:outline-none focus:border-teal-500 resize-none"></textarea>
                    </div>

                    <div class="flex gap-2">
                        <button type="submit" class="flex-1 py-3 bg-teal-600 hover:bg-teal-500 text-white font-bold rounded-2xl transition-all shadow-md">
                            ذخیره پروفایل حریم خصوصی
                        </button>
                        <button type="button" @click="editStudentId = ''; showEditStudentModal = false" class="py-3 px-6 bg-gray-100 hover:bg-gray-200 text-gray-600 font-bold rounded-2xl transition-all">
                            انصراف
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </main>

</body>
</html>
