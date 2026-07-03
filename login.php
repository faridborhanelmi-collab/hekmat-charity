<?php
session_start();
require_once 'includes/db.php';

if (isset($_GET['redirect'])) {
    $_SESSION['redirect_after_login'] = $_GET['redirect'];
}

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    if (isset($_SESSION['redirect_after_login'])) {
        $redirect = $_SESSION['redirect_after_login'];
        unset($_SESSION['redirect_after_login']);
        header("Location: " . $redirect);
    } elseif ($_SESSION['role'] === 'admin') {
        header("Location: admin/index.php");
    } else {
        header("Location: student-dashboard.php");
    }
    exit();
}

$error = '';
$step = isset($_POST['step']) ? (int)$_POST['step'] : 1;
$national_id = $_POST['national_id'] ?? '';
$phone = $_POST['phone'] ?? '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if ($step == 1) {
        // Step 1: Validate ID and Phone
        if ($national_id === 'admin' && $phone === 'admin') {
            // Shortcut for admin dev
            $_SESSION['user_id'] = 0;
            $_SESSION['role'] = 'admin';
            $_SESSION['user_name'] = 'فرید برهان علمی - مدیرعامل';
            $_SESSION['is_admin'] = true;
            header("Location: index.php");
            exit();
        }

        // Generic search (partial phone match because Excel data is messy)
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND phone_number LIKE ?");
        $stmt->execute([$national_id, "%$phone%"]);
        $user = $stmt->fetch();

        if (!$user) {
            // Fallback 1: Search in students table
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
            }
        }

        if (!$user) {
            // Fallback 2: Search in donors table
            $stmt = $pdo->prepare("SELECT * FROM donors WHERE phone = ? OR phone LIKE ?");
            $stmt->execute([$phone, "%$national_id%"]);
            $donor = $stmt->fetch();
            if ($donor) {
                $user = [
                    'id' => $donor['id'],
                    'username' => $donor['phone'],
                    'phone_number' => $donor['phone'],
                    'role' => 'benefactor',
                    'related_id' => $donor['id']
                ];
            }
        }

        if ($user) {
            $_SESSION['temp_user'] = $user;
            $_SESSION['simulated_code'] = '1234'; // Simulated SMS code
            $step = 2;
        } else {
            $error = 'اطلاعات وارد شده با سوابق ما مطابقت ندارد. لطفا مجددا تلاش کنید یا با بنیاد تماس بگیرید.';
        }
    } elseif ($step == 2) {
        // Step 2: Validate Simulated SMS Code
        $code = $_POST['code'] ?? '';
        if ($code === $_SESSION['simulated_code']) {
            $user = $_SESSION['temp_user'];
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['user_name'] = 'کاربر گرامی'; // Default if name not in users table
            $_SESSION['is_admin'] = ($user['role'] === 'admin');
            
            // Link to real name
            if ($user['role'] === 'student') {
                $s = $pdo->prepare("SELECT name, surname FROM students WHERE id = ?");
                $s->execute([$user['related_id']]);
                $student = $s->fetch();
                if ($student) $_SESSION['user_name'] = $student['name'] . ' ' . $student['surname'];
            } elseif ($user['role'] === 'benefactor') {
                $b = $pdo->prepare("SELECT name, surname FROM donors WHERE id = ?");
                $b->execute([$user['related_id']]);
                $donor = $b->fetch();
                if ($donor) $_SESSION['user_name'] = $donor['name'] . ' ' . $donor['surname'];
            }

            unset($_SESSION['temp_user']);
            unset($_SESSION['simulated_code']);
            
            // Dynamic Redirect based on role
            if (isset($_SESSION['redirect_after_login'])) {
                $redirect = $_SESSION['redirect_after_login'];
                unset($_SESSION['redirect_after_login']);
                header("Location: " . $redirect);
            } elseif ($_SESSION['is_admin']) {
                header("Location: admin/index.php");
            } elseif ($_SESSION['role'] === 'student') {
                header("Location: student-dashboard.php");
            } elseif ($_SESSION['role'] === 'benefactor') {
                header("Location: donor-dashboard.php");
            } else {
                header("Location: index.php");
            }
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
    <title>ورود | بنیاد نیکوکاری حکمت</title>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@100;400;700;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Vazirmatn', 'sans-serif'] },
                    colors: {
                        primary: { 900: '#0c4a6e', 800: '#075985', 600: '#0284c7' },
                        accent: { 500: '#f43f5e' }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-gray-900 min-h-screen flex items-center justify-center relative overflow-hidden">

    <!-- Background Elements -->
    <div class="absolute inset-0 z-0">
        <div class="absolute inset-0 bg-gradient-to-br from-primary-900 via-gray-900 to-black opacity-90"></div>
        <div class="absolute inset-0 bg-[url('https://images.unsplash.com/photo-1488521787991-ed7bbaae773c?q=80&w=2670')] bg-cover bg-center opacity-20 mix-blend-overlay"></div>
    </div>

    <!-- Glass Card -->
    <div class="relative z-10 w-full max-w-md bg-white/10 backdrop-blur-2xl rounded-[3rem] shadow-2xl border border-white/10 overflow-hidden m-4">
        
        <div class="p-10 text-white">
            <div class="text-center mb-10">
                <div class="inline-flex items-center gap-2 mb-4 bg-white/10 px-4 py-2 rounded-full backdrop-blur-md border border-white/5">
                    <div class="w-8 h-8 bg-gradient-to-tr from-teal-400 to-primary-500 rounded-full flex items-center justify-center font-bold text-white shadow-lg">م</div>
                    <span class="font-bold text-sm tracking-wide">بنیاد نیکوکاری حکمت</span>
                </div>
                <h2 class="text-3xl font-black mb-2"><?php echo $step == 1 ? 'ورود به پورتال' : 'تایید شماره همراه'; ?></h2>
                <p class="text-white/60 text-sm"><?php echo $step == 1 ? 'لطفاً کد ملی و شماره همراه خود را وارد کنید.' : 'کد ۴ رقمی ارسال شده را وارد کنید.'; ?></p>
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
                        <label class="block text-xs font-bold text-white/80 mb-2 mr-1">کد ملی / شناسه کاربری</label>
                        <input type="text" name="national_id" required value="<?php echo $national_id; ?>"
                            class="w-full bg-white/5 border border-white/10 text-white placeholder-white/30 rounded-2xl px-5 py-4 focus:outline-none focus:bg-white/10 focus:border-teal-400 transition-all text-left dir-ltr"
                            placeholder="---" style="direction: ltr;">
                    </div>

                    <div class="mb-8">
                        <label class="block text-xs font-bold text-white/80 mb-2 mr-1">شماره همراه</label>
                        <input type="text" name="phone" required value="<?php echo $phone; ?>"
                            class="w-full bg-white/5 border border-white/10 text-white placeholder-white/30 rounded-2xl px-5 py-4 focus:outline-none focus:bg-white/10 focus:border-teal-400 transition-all text-left dir-ltr"
                            placeholder="0912..." style="direction: ltr;">
                    </div>
                <?php else: ?>
                    <input type="hidden" name="national_id" value="<?php echo $national_id; ?>">
                    <input type="hidden" name="phone" value="<?php echo $phone; ?>">
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
                    class="w-full bg-gradient-to-r from-teal-500 to-primary-600 hover:from-teal-400 hover:to-primary-500 text-white font-black py-4 rounded-2xl shadow-lg hover:shadow-teal-500/30 transform hover:-translate-y-1 transition-all duration-300">
                    <?php echo $step == 1 ? 'دریافت کد تایید' : 'تایید و ورود'; ?>
                </button>
            </form>

            <?php if ($step == 2): ?>
                <div class="mt-6 text-center">
                    <a href="login.php" class="text-xs text-teal-300 hover:text-white transition-colors">ویرایش شماره همراه</a>
                </div>
            <?php endif; ?>

            <div class="mt-10 text-center border-t border-white/10 pt-6">
                <a href="index.php" class="inline-flex items-center gap-2 text-white/50 hover:text-white transition-colors text-sm group">
                    <span>بازگشت به خانه</span>
                    <span class="group-hover:-translate-x-1 transition-transform">←</span>
                </a>
            </div>
        </div>

    </div>

</body>
</html>