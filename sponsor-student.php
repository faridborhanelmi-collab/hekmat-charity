<?php
session_start();
require_once 'includes/db.php';

$student_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($student_id <= 0) {
    die("دانش‌آموز نامعتبر است.");
}

// Check login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?redirect=" . urlencode("sponsor-student.php?id=" . $student_id));
    exit();
}

// Check role
if ($_SESSION['role'] !== 'benefactor' && $_SESSION['role'] !== 'admin') {
    die("فقط حامیان می‌توانند دانش‌آموزی را برای حمایت انتخاب کنند. لطفاً با حساب کاربری حامی وارد شوید.");
}

$donor_id = $_SESSION['role'] === 'admin' ? 1 : $_SESSION['related_id'];

// Check if already sponsored
$stmt = $pdo->prepare("SELECT count(*) FROM sponsorships WHERE student_id = ? AND status = 'active'");
$stmt->execute([$student_id]);
if ($stmt->fetchColumn() > 0) {
    die("این دانش‌آموز در حال حاضر تحت حمایت قرار گرفته است.");
}

// Fetch student details (alias name)
$stmt = $pdo->prepare("SELECT alias_name FROM students WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();
if (!$student) {
    die("دانش‌آموز یافت نشد.");
}
$alias = $student['alias_name'] ?: 'حکمت‌جو';

$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['confirm_sponsor'])) {
        // Create pending sponsorship
        $stmt = $pdo->prepare("INSERT INTO sponsorships (donor_id, student_id, start_date, status) VALUES (?, ?, date('now'), 'pending')");
        $stmt->execute([$donor_id, $student_id]);
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تایید حمایت | بنیاد حکمت</title>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;700;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Vazirmatn', 'sans-serif'] },
                    colors: { primary: { 900: '#0c4a6e', 600: '#0284c7' }, teal: { 500: '#14b8a6', 600: '#0d9488' } }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-50 flex items-center justify-center min-h-screen p-4">
    <div class="bg-white rounded-3xl shadow-xl p-8 max-w-md w-full text-center">
        <?php if ($success): ?>
            <div class="w-20 h-20 bg-green-100 text-green-500 rounded-full flex items-center justify-center text-4xl mx-auto mb-6">
                ✅
            </div>
            <h2 class="text-2xl font-black text-gray-800 mb-4">درخواست ثبت شد</h2>
            <p class="text-gray-600 mb-8 leading-relaxed">
                درخواست شما برای حمایت از <strong><?php echo htmlspecialchars($alias); ?></strong> با موفقیت ثبت شد. 
                همکاران ما در بنیاد حکمت به زودی برای تکمیل فرآیند و هماهنگی‌های لازم با شما تماس خواهند گرفت.
            </p>
            <a href="<?php echo $_SESSION['role'] === 'admin' ? 'admin/index.php' : 'donor-dashboard.php'; ?>" class="block w-full bg-teal-600 hover:bg-teal-700 text-white font-bold py-3 rounded-xl transition-colors">
                بازگشت به داشبورد
            </a>
        <?php else: ?>
            <div class="w-20 h-20 bg-teal-50 text-teal-500 rounded-full flex items-center justify-center text-4xl mx-auto mb-6">
                🤝
            </div>
            <h2 class="text-2xl font-black text-gray-800 mb-2">تایید حمایت</h2>
            <p class="text-gray-600 mb-8 leading-relaxed">
                آیا از انتخاب <strong><?php echo htmlspecialchars($alias); ?></strong> برای حمایت مالی و معنوی در پویش حکمت‌یار اطمینان دارید؟
            </p>
            
            <form method="POST" class="space-y-4">
                <button type="submit" name="confirm_sponsor" class="w-full bg-teal-600 hover:bg-teal-700 text-white font-black py-4 rounded-xl shadow-lg hover:shadow-teal-500/30 transition-all transform hover:-translate-y-1">
                    بله، می‌خواهم حامی این فرزند شوم
                </button>
                <a href="campaign.php#students" class="block w-full bg-gray-100 hover:bg-gray-200 text-gray-600 font-bold py-3 rounded-xl transition-colors">
                    انصراف و بازگشت
                </a>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>
