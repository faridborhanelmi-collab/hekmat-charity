import os

login_content = """<?php
session_start();
require_once 'includes/db.php';

if (isset($_GET['redirect'])) {
    $_SESSION['redirect_after_login'] = $_GET['redirect'];
}

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
$username = $_POST['username'] ?? '';
$auth_type = $_POST['auth_type'] ?? '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if ($step == 1) {
        if ($username === 'admin') {
            $_SESSION['temp_username'] = 'admin';
            $_SESSION['auth_type'] = 'admin';
            $step = 2;
        } elseif (strlen($username) == 10 && is_numeric($username) && strpos($username, '09') !== 0) {
            // Probably a national ID (Student)
            $stmt = $pdo->prepare("SELECT id FROM students WHERE national_id = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $_SESSION['temp_username'] = $username;
                $_SESSION['auth_type'] = 'student';
                $step = 2;
            } else {
                $error = 'دانش‌آموزی با این کد ملی یافت نشد.';
            }
        } else {
            // Probably a phone number (Donor/User)
            $stmt = $pdo->prepare("SELECT id FROM users WHERE phone_number = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $_SESSION['temp_username'] = $username;
                $_SESSION['auth_type'] = 'user';
                $_SESSION['simulated_code'] = '1234';
                $step = 2;
            } else {
                // Fallback to donors table
                $stmt = $pdo->prepare("SELECT id FROM donors WHERE phone = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    $_SESSION['temp_username'] = $username;
                    $_SESSION['auth_type'] = 'donor';
                    $_SESSION['simulated_code'] = '1234';
                    $step = 2;
                } else {
                    $error = 'کاربری با این شماره یافت نشد. لطفا ابتدا ثبت نام کنید.';
                }
            }
        }
    } elseif ($step == 2) {
        $username = $_SESSION['temp_username'];
        $auth_type = $_SESSION['auth_type'];
        
        if ($auth_type === 'admin') {
            $password = $_POST['password'] ?? '';
            if ($password === 'admin') { // Temporary simple check for admin
                $_SESSION['user_id'] = 0;
                $_SESSION['role'] = 'admin';
                $_SESSION['user_name'] = 'مدیریت بنیاد';
                $_SESSION['is_admin'] = true;
                $_SESSION['is_superadmin'] = true; // Superadmin access
                $success = true;
            } else {
                $error = 'رمز عبور اشتباه است.';
                $step = 2;
            }
        } elseif ($auth_type === 'student') {
            $password = $_POST['password'] ?? '';
            $stmt = $pdo->prepare("SELECT * FROM students WHERE national_id = ? AND password = ?");
            $stmt->execute([$username, $password]);
            $student = $stmt->fetch();
            if ($student) {
                $_SESSION['user_id'] = $student['id'];
                $_SESSION['role'] = 'student';
                $_SESSION['user_name'] = $student['name'] . ' ' . $student['surname'];
                $_SESSION['related_id'] = $student['id'];
                $_SESSION['is_admin'] = false;
                $success = true;
            } else {
                $error = 'کد عبور اختصاصی اشتباه است.';
                $step = 2;
            }
        } else {
            // User or Donor (OTP)
            $code = $_POST['code'] ?? '';
            if ($code === $_SESSION['simulated_code']) {
                if ($auth_type === 'user') {
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE phone_number = ?");
                    $stmt->execute([$username]);
                    $user = $stmt->fetch();
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['user_name'] = $user['username'];
                    $_SESSION['is_admin'] = ($user['role'] === 'admin');
                    $_SESSION['is_superadmin'] = false; // Only admin/admin is superadmin for now
                } else {
                    $stmt = $pdo->prepare("SELECT * FROM donors WHERE phone = ?");
                    $stmt->execute([$username]);
                    $donor = $stmt->fetch();
                    $_SESSION['user_id'] = $donor['id'];
                    $_SESSION['role'] = 'benefactor';
                    $_SESSION['user_name'] = $donor['name'] . ' ' . $donor['surname'];
                    $_SESSION['related_id'] = $donor['id'];
                    $_SESSION['is_admin'] = false;
                }
                $success = true;
            } else {
                $error = 'کد تایید اشتباه است.';
                $step = 2;
            }
        }
        
        if (isset($success) && $success) {
            unset($_SESSION['temp_username']);
            unset($_SESSION['auth_type']);
            unset($_SESSION['simulated_code']);
            
            if (isset($_SESSION['redirect_after_login'])) {
                $redirect = $_SESSION['redirect_after_login'];
                unset($_SESSION['redirect_after_login']);
                header("Location: " . $redirect);
            } elseif (isset($_SESSION['is_admin']) && $_SESSION['is_admin']) {
                header("Location: admin/index.php");
            } elseif ($_SESSION['role'] === 'student') {
                header("Location: student-dashboard.php");
            } elseif ($_SESSION['role'] === 'benefactor') {
                header("Location: donor-dashboard.php");
            } else {
                header("Location: index.php");
            }
            exit();
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
                    colors: { primary: { 900: '#0c4a6e', 800: '#075985', 600: '#0284c7' } }
                }
            }
        }
    </script>
    <link rel="apple-touch-icon" href="logo.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <link rel="manifest" href="manifest.json">
</head>
<body class="bg-gray-900 min-h-[100dvh] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-gradient-to-br from-primary-900 via-gray-900 to-black opacity-90"></div>
    <div class="relative z-10 w-full max-w-md bg-white/10 backdrop-blur-2xl rounded-[3rem] shadow-2xl border border-white/10 overflow-hidden p-10 text-white">
        <h2 class="text-3xl font-black mb-2 text-center">ورود به پورتال</h2>
        
        <?php if ($error): ?>
            <div class="bg-red-500/20 text-red-200 p-4 rounded-xl mb-6 text-xs text-center"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="step" value="<?php echo $step; ?>">
            
            <?php if ($step == 1): ?>
                <div class="mb-8">
                    <label class="block text-xs font-bold text-white/80 mb-2">شماره همراه یا کد ملی دانش‌آموز</label>
                    <input type="text" name="username" required value="<?php echo htmlspecialchars($username); ?>" class="w-full bg-white/5 border border-white/10 text-white rounded-2xl px-5 py-4 focus:border-teal-400 focus:outline-none transition-all dir-ltr" style="direction: ltr;" placeholder="09... / 1234567890">
                </div>
                <button type="submit" class="w-full bg-gradient-to-r from-teal-500 to-primary-600 text-white font-black py-4 rounded-2xl shadow-lg hover:shadow-teal-500/30 transition-all">مرحله بعد</button>
                <div class="mt-6 text-center">
                    <a href="register.php" class="text-xs text-teal-300 hover:text-white">حساب کاربری ندارید؟ ثبت‌نام</a>
                </div>
            <?php else: ?>
                <?php if ($_SESSION['auth_type'] === 'student' || $_SESSION['auth_type'] === 'admin'): ?>
                    <div class="mb-8">
                        <label class="block text-xs font-bold text-white/80 mb-2 text-center">رمز عبور</label>
                        <input type="password" name="password" required autofocus class="w-full bg-white/5 border border-white/10 text-white text-center rounded-2xl px-5 py-4 focus:border-teal-400 focus:outline-none dir-ltr" style="direction: ltr;">
                    </div>
                <?php else: ?>
                    <div class="mb-8">
                        <label class="block text-xs font-bold text-white/80 mb-2 text-center">کد تایید پیامک شده (تست: ۱۲۳۴)</label>
                        <input type="text" name="code" required autofocus class="w-full text-center tracking-[1rem] bg-white/5 border border-white/10 text-white text-3xl font-black rounded-2xl px-5 py-4 focus:border-teal-400 focus:outline-none dir-ltr" style="direction: ltr;" maxlength="4">
                    </div>
                <?php endif; ?>
                <button type="submit" class="w-full bg-gradient-to-r from-teal-500 to-primary-600 text-white font-black py-4 rounded-2xl shadow-lg hover:shadow-teal-500/30 transition-all">تایید و ورود</button>
                <div class="mt-4 text-center">
                    <a href="login.php" class="text-xs text-white/50 hover:text-white">تغییر اطلاعات ورود</a>
                </div>
            <?php endif; ?>
        </form>
        <div class="mt-10 text-center border-t border-white/10 pt-6">
            <a href="index.php" class="text-white/50 hover:text-white text-sm">بازگشت به خانه</a>
        </div>
    </div>
<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => { navigator.serviceWorker.register('/sw.js'); });
  }
</script>
</body>
</html>
"""

with open('login.php', 'w') as f:
    f.write(login_content)

