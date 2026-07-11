<?php
session_start();
require_once 'includes/db.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if ($_SESSION['role'] === 'student') {
        header("Location: student-dashboard.php");
        exit();
    }
}

$error = '';
$step = isset($_POST['step']) ? (int)$_POST['step'] : 1;
$national_id = $_POST['national_id'] ?? '';
$phone = $_POST['phone'] ?? '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if ($step == 1) {
        // Search in students table ONLY
        $stmt = $pdo->prepare("SELECT * FROM students WHERE national_id = ? AND (phone LIKE ? OR guardian_phone LIKE ?)");
        $stmt->execute([$national_id, "%$phone%", "%$phone%"]);
        $student = $stmt->fetch();
        
        if ($student) {
            $user = [
                'id' => $student['id'],
                'username' => $student['national_id'],
                'phone_number' => $student['phone'],
                'role' => 'student',
                'related_id' => $student['id']
            ];
            $_SESSION['temp_student'] = $user;
            $_SESSION['simulated_code'] = '1234'; // Simulated SMS code
            $step = 2;
        } else {
            $error = 'اطلاعات وارد شده با مشخصات ثبت‌نامی شما در آکادمی مطابقت ندارد. لطفاً با پشتیبانی بنیاد تماس بگیرید.';
        }
    } elseif ($step == 2) {
        $code = $_POST['code'] ?? '';
        if ($code === $_SESSION['simulated_code']) {
            $user = $_SESSION['temp_student'];
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['is_admin'] = false;
            
            // Get name
            $s = $pdo->prepare("SELECT name, surname FROM students WHERE id = ?");
            $s->execute([$user['related_id']]);
            $student = $s->fetch();
            if ($student) {
                $_SESSION['user_name'] = $student['name'] . ' ' . $student['surname'];
            } else {
                $_SESSION['user_name'] = 'دانش‌پژوه گرامی';
            }

            unset($_SESSION['temp_student']);
            unset($_SESSION['simulated_code']);
            
            header("Location: student-dashboard.php");
            exit();
        } else {
            $error = 'کد تایید وارد شده صحیح نیست.';
            $step = 2;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود به آکادمی استعدادهای حکمت</title>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@100;400;700;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Vazirmatn', 'sans-serif'] },
                    colors: {
                        academy: {
                            900: '#0f172a', // dark slate
                            800: '#1e293b',
                            600: '#475569',
                            teal: '#14b8a6'
                        }
                    }
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
<body class="bg-academy-900 min-h-screen flex items-center justify-center relative overflow-hidden">

    <!-- Background Elements -->
    <div class="absolute inset-0 z-0">
        <div class="absolute inset-0 bg-gradient-to-br from-indigo-950 via-slate-900 to-black opacity-90"></div>
        <div class="absolute inset-0 bg-[url('https://images.unsplash.com/photo-1516321318423-f06f85e504b3?q=80&w=2670')] bg-cover bg-center opacity-10 mix-blend-overlay"></div>
    </div>

    <!-- Glass Card -->
    <div class="relative z-10 w-full max-w-md bg-white/5 backdrop-blur-2xl rounded-[3rem] shadow-2xl border border-white/10 overflow-hidden m-4">
        
        <div class="p-10 text-white">
            <div class="text-center mb-10">
                <div class="inline-flex items-center gap-2 mb-4 bg-teal-500/10 px-4 py-2 rounded-full backdrop-blur-md border border-teal-500/20">
                    <div class="w-8 h-8 bg-gradient-to-tr from-teal-400 to-cyan-500 rounded-full flex items-center justify-center font-bold text-white shadow-lg">🎓</div>
                    <span class="font-bold text-sm tracking-wide text-teal-300">سامانه آکادمی استعدادهای حکمت</span>
                </div>
                <h2 class="text-3xl font-black mb-2"><?php echo $step == 1 ? 'ورود دانش‌پژوهان' : 'تایید شماره همراه'; ?></h2>
                <p class="text-white/60 text-sm"><?php echo $step == 1 ? 'لطفاً کد ملی و شماره همراه خود را وارد کنید.' : 'کد ۴ رقمی پیامک شده را وارد کنید.'; ?></p>
            </div>

            <?php if ($error): ?>
                <div class="bg-red-500/20 border border-red-500/50 text-red-200 p-4 rounded-xl mb-6 text-xs text-center backdrop-blur-sm">
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="step" value="<?php echo $step; ?>">
                
                <?php if ($step == 1): ?>
                    <div class="mb-5">
                        <label class="block text-xs font-bold text-white/80 mb-2 mr-1">کد ملی</label>
                        <input type="text" name="national_id" required value="<?php echo htmlspecialchars($national_id); ?>"
                            class="w-full bg-white/5 border border-white/10 text-white placeholder-white/30 rounded-2xl px-5 py-4 focus:outline-none focus:bg-white/10 focus:border-teal-400 transition-all text-left dir-ltr"
                            placeholder="---" style="direction: ltr;">
                    </div>

                    <div class="mb-8">
                        <label class="block text-xs font-bold text-white/80 mb-2 mr-1">شماره همراه</label>
                        <input type="text" name="phone" required value="<?php echo htmlspecialchars($phone); ?>"
                            class="w-full bg-white/5 border border-white/10 text-white placeholder-white/30 rounded-2xl px-5 py-4 focus:outline-none focus:bg-white/10 focus:border-teal-400 transition-all text-left dir-ltr"
                            placeholder="0912..." style="direction: ltr;">
                    </div>
                <?php else: ?>
                    <input type="hidden" name="national_id" value="<?php echo htmlspecialchars($national_id); ?>">
                    <input type="hidden" name="phone" value="<?php echo htmlspecialchars($phone); ?>">
                    <div class="mb-8">
                        <label class="block text-xs font-bold text-white/80 mb-2 mr-1 text-center">کد تایید (تست: ۱۲۳۴)</label>
                        <div class="flex justify-center gap-3">
                            <input type="text" name="code" required maxlength="4" autofocus
                                class="w-40 bg-white/5 border border-white/10 text-white text-3xl font-black rounded-2xl px-5 py-4 focus:outline-none focus:bg-white/10 focus:border-teal-400 transition-all text-center tracking-[1rem] dir-ltr"
                                placeholder="----" style="direction: ltr;">
                        </div>
                    </div>
                <?php endif; ?>

                <button type="submit"
                    class="w-full bg-gradient-to-r from-teal-500 to-cyan-600 hover:from-teal-400 hover:to-cyan-500 text-white font-black py-4 rounded-2xl shadow-lg hover:shadow-teal-500/30 transform hover:-translate-y-1 transition-all duration-300">
                    <?php echo $step == 1 ? 'دریافت کد ورود' : 'ورود به آکادمی'; ?>
                </button>
            </form>

            <div class="mt-10 text-center border-t border-white/10 pt-6">
                <a href="index.php" class="inline-flex items-center gap-2 text-white/50 hover:text-white transition-colors text-sm group">
                    <span>بازگشت به سایت اصلی</span>
                    <span class="group-hover:-translate-x-1 transition-transform">←</span>
                </a>
            </div>
        </div>

    </div>


<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
      navigator.serviceWorker.register('/sw.js');
    });
  }
</script>

</body>
</html>
