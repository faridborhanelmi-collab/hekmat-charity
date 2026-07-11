<?php
session_start();
require_once 'includes/db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$error = '';
$step = isset($_POST['step']) ? (int)$_POST['step'] : 1;
$phone = $_POST['phone'] ?? '';
$name = $_POST['name'] ?? '';
$surname = $_POST['surname'] ?? '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if ($step == 1) {
        // Validate phone
        if (strlen($phone) < 10) {
            $error = 'شماره موبایل معتبر نیست.';
        } else {
            // Check if already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE phone_number = ?");
            $stmt->execute([$phone]);
            if ($stmt->fetch()) {
                $error = 'این شماره قبلا ثبت نام کرده است. لطفا وارد شوید.';
            } else {
                $_SESSION['simulated_code'] = '1234';
                $step = 2;
            }
        }
    } elseif ($step == 2) {
        $code = $_POST['code'] ?? '';
        if ($code === $_SESSION['simulated_code']) {
            // Register as general user
            $stmt = $pdo->prepare("INSERT INTO users (username, phone_number, role) VALUES (?, ?, ?)");
            $stmt->execute([$phone, $phone, 'user']);
            $user_id = $pdo->lastInsertId();
            
            $_SESSION['user_id'] = $user_id;
            $_SESSION['role'] = 'user';
            $_SESSION['user_name'] = $name . ' ' . $surname;
            $_SESSION['is_admin'] = false;
            
            unset($_SESSION['simulated_code']);
            header("Location: index.php");
            exit();
        } else {
            $error = 'کد تایید اشتباه است.';
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
    <title>ثبت نام | بنیاد نیکوکاری حکمت</title>
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
</head>
<body class="bg-gray-900 min-h-[100dvh] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-gradient-to-br from-primary-900 via-gray-900 to-black opacity-90"></div>
    <div class="relative z-10 w-full max-w-md bg-white/10 backdrop-blur-2xl rounded-[3rem] shadow-2xl border border-white/10 overflow-hidden p-10 text-white">
        <h2 class="text-3xl font-black mb-2 text-center">ثبت نام</h2>
        <p class="text-white/60 text-sm text-center mb-8">به خانواده بنیاد حکمت بپیوندید</p>
        
        <?php if ($error): ?>
            <div class="bg-red-500/20 text-red-200 p-4 rounded-xl mb-6 text-xs text-center"><?php echo $error; ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" name="step" value="<?php echo $step; ?>">
            <?php if ($step == 1): ?>
                <div class="mb-5">
                    <label class="block text-xs font-bold text-white/80 mb-2">نام</label>
                    <input type="text" name="name" required value="<?php echo htmlspecialchars($name); ?>" class="w-full bg-white/5 border border-white/10 text-white rounded-2xl px-5 py-4 focus:border-teal-400 focus:outline-none transition-all">
                </div>
                <div class="mb-5">
                    <label class="block text-xs font-bold text-white/80 mb-2">نام خانوادگی</label>
                    <input type="text" name="surname" required value="<?php echo htmlspecialchars($surname); ?>" class="w-full bg-white/5 border border-white/10 text-white rounded-2xl px-5 py-4 focus:border-teal-400 focus:outline-none transition-all">
                </div>
                <div class="mb-8">
                    <label class="block text-xs font-bold text-white/80 mb-2">شماره همراه</label>
                    <input type="text" name="phone" required value="<?php echo htmlspecialchars($phone); ?>" class="w-full bg-white/5 border border-white/10 text-white rounded-2xl px-5 py-4 focus:border-teal-400 focus:outline-none transition-all dir-ltr" style="direction: ltr;" placeholder="09...">
                </div>
                <button type="submit" class="w-full bg-gradient-to-r from-teal-500 to-primary-600 text-white font-black py-4 rounded-2xl shadow-lg hover:shadow-teal-500/30 transition-all">دریافت کد پیامکی</button>
                <div class="mt-6 text-center">
                    <a href="login.php" class="text-xs text-teal-300 hover:text-white">قبلاً ثبت‌نام کرده‌اید؟ ورود</a>
                </div>
            <?php else: ?>
                <input type="hidden" name="name" value="<?php echo htmlspecialchars($name); ?>">
                <input type="hidden" name="surname" value="<?php echo htmlspecialchars($surname); ?>">
                <input type="hidden" name="phone" value="<?php echo htmlspecialchars($phone); ?>">
                <div class="mb-8">
                    <label class="block text-xs font-bold text-white/80 mb-2 text-center">کد پیامک شده (تست: ۱۲۳۴)</label>
                    <input type="text" name="code" required class="w-full text-center tracking-[1rem] bg-white/5 border border-white/10 text-white text-3xl font-black rounded-2xl px-5 py-4 focus:border-teal-400 focus:outline-none dir-ltr" style="direction: ltr;">
                </div>
                <button type="submit" class="w-full bg-gradient-to-r from-teal-500 to-primary-600 text-white font-black py-4 rounded-2xl shadow-lg hover:shadow-teal-500/30 transition-all">تایید و عضویت</button>
            <?php endif; ?>
        </form>
    </div>
</body>
</html>
