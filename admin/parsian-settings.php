<?php
session_start();
require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/ParsianBankService.php';

// Access Control - Superadmin Only (مدیرعامل)
require_role('superadmin');

$bankService = new ParsianBankService($pdo);
$message = '';
$message_type = '';

// Handle AJAX connection test
if (isset($_POST['ajax_action']) && $_POST['ajax_action'] === 'test_connection') {
    header('Content-Type: application/json');
    $result = $bankService->testConnection();
    echo json_encode($result);
    exit();
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['ajax_action'])) {
    $is_sandbox = isset($_POST['is_sandbox']) ? 1 : 0;
    $api_url = trim($_POST['api_url'] ?? '');
    $source_account = trim($_POST['source_account'] ?? '');
    $source_iban = trim($_POST['source_iban'] ?? '');
    $client_id = trim($_POST['client_id'] ?? '');
    $client_secret = trim($_POST['client_secret'] ?? '');
    $api_key = trim($_POST['api_key'] ?? '');
    $now = date('Y-m-d H:i:s');

    try {
        $stmt = $pdo->prepare("
            UPDATE bank_api_settings 
            SET is_sandbox = ?, 
                api_url = ?, 
                source_account = ?, 
                source_iban = ?, 
                client_id = ?, 
                client_secret = ?, 
                api_key = ?, 
                updated_at = ? 
            WHERE bank_name = 'parsian'
        ");
        $stmt->execute([$is_sandbox, $api_url, $source_account, $source_iban, $client_id, $client_secret, $api_key, $now]);
        $message = "تنظیمات وب‌سرویس بانک پارسیان با موفقیت ذخیره شد.";
        $message_type = "success";
        
        $bankService->loadSettings();
    } catch (Exception $e) {
        $message = "خطا در ذخیره تنظیمات: " . $e->getMessage();
        $message_type = "error";
    }
}

$settings = $bankService->getSettings();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تنظیمات وب‌سرویس بانک پارسیان | بنیاد حکمت</title>
<style>
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:100;font-display:swap;src:url('/assets/fonts/Vazirmatn-100.woff2') format('woff2')}
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:300;font-display:swap;src:url('/assets/fonts/Vazirmatn-300.woff2') format('woff2')}
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:400;font-display:swap;src:url('/assets/fonts/Vazirmatn-400.woff2') format('woff2')}
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:500;font-display:swap;src:url('/assets/fonts/Vazirmatn-500.woff2') format('woff2')}
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:700;font-display:swap;src:url('/assets/fonts/Vazirmatn-700.woff2') format('woff2')}
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:900;font-display:swap;src:url('/assets/fonts/Vazirmatn-900.woff2') format('woff2')}
</style>
<link rel="stylesheet" href="/assets/tailwind.min.css">
<script defer src="/assets/alpine.min.js"></script>
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
</head>
<body class="bg-gray-50 font-sans text-gray-800 antialiased min-h-screen pb-20">

    <!-- Top Navigation Header -->
    <header class="bg-primary-900 text-white shadow-xl">
        <div class="container mx-auto px-6 py-6 flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="flex items-center gap-4">
                <div class="w-14 h-14 bg-red-600/20 border border-red-500/30 rounded-2xl flex items-center justify-center text-3xl shadow-inner">
                    🏦
                </div>
                <div>
                    <h1 class="text-2xl font-black flex items-center gap-3">
                        تنظیمات وب‌سرویس بانک پارسیان
                        <span class="text-xs px-3 py-1 bg-red-500/20 text-red-300 rounded-full font-bold">بانکداری شرکتی</span>
                    </h1>
                    <p class="text-teal-200/70 text-xs mt-1">مدیریت اتصال به API پایا و انتقال وجه گروهی (بچ) بنیاد حکمت</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <a href="bursary-payments.php" class="px-5 py-2.5 bg-teal-600 hover:bg-teal-500 text-white text-xs font-bold rounded-2xl transition shadow-lg flex items-center gap-2">
                    <span>💳</span> مدیریت پرداخت بورسیه
                </a>
                <a href="index.php" class="px-5 py-2.5 bg-white/10 hover:bg-white/20 text-white text-xs font-bold rounded-2xl transition">
                    داشبورد ادمین
                </a>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-6 mt-8 max-w-5xl" x-data="parsianSettings()">

        <?php if ($message): ?>
        <div class="mb-6 p-4 rounded-2xl text-sm font-bold flex items-center gap-3 <?php echo $message_type === 'success' ? 'bg-emerald-50 text-emerald-800 border border-emerald-200' : 'bg-rose-50 text-rose-800 border border-rose-200'; ?>">
            <span><?php echo $message_type === 'success' ? '✅' : '⚠️'; ?></span>
            <span><?php echo htmlspecialchars($message); ?></span>
        </div>
        <?php endif; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            
            <!-- Main Form (2 cols) -->
            <div class="lg:col-span-2 space-y-6">
                <form method="POST" class="bg-white rounded-3xl p-8 shadow-xl border border-gray-100 space-y-6">
                    <h2 class="text-lg font-black text-primary-900 flex items-center gap-3 border-b border-gray-100 pb-4">
                        <span class="text-xl">⚙️</span> پیکربندی فنی اتصال
                    </h2>

                    <!-- Mode Toggle (Sandbox vs Live) -->
                    <div class="bg-amber-50/70 border border-amber-200 p-5 rounded-2xl flex items-center justify-between">
                        <div>
                            <span class="font-bold text-sm text-amber-900 block mb-0.5">حالت شبیه‌ساز (Sandbox / Testing)</span>
                            <span class="text-xs text-amber-700/80">در حالت فعال بودن، بدون کسر وجه واقعی، فرآیند ارسال بچ و صدور کد رهگیری شبیه‌سازی می‌شود.</span>
                        </div>
                        <label class="relative inline-flex items-center cursor-pointer">
                            <input type="checkbox" name="is_sandbox" value="1" <?php echo ($settings['is_sandbox'] ?? 1) ? 'checked' : ''; ?> class="sr-only peer">
                            <div class="w-14 h-7 bg-gray-200 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-0.5 after:start-[4px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-6 after:w-6 after:transition-all peer-checked:bg-amber-500"></div>
                        </label>
                    </div>

                    <!-- Source Accounts -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-600 mb-2">شماره حساب مبدأ بنیاد (پارسیان)</label>
                            <input type="text" name="source_account" value="<?php echo htmlspecialchars((string)($settings['source_account'] ?? '')); ?>" placeholder="مثال: 0100234567890" dir="ltr" class="w-full bg-gray-50 border border-gray-200 rounded-2xl px-4 py-3 text-sm font-mono text-gray-800 focus:outline-none focus:ring-2 focus:ring-primary-600">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-600 mb-2">شماره شبای مبدأ بنیاد (IR...)</label>
                            <input type="text" name="source_iban" value="<?php echo htmlspecialchars((string)($settings['source_iban'] ?? '')); ?>" placeholder="مثال: IR120540100234567890123456" dir="ltr" class="w-full bg-gray-50 border border-gray-200 rounded-2xl px-4 py-3 text-sm font-mono text-gray-800 focus:outline-none focus:ring-2 focus:ring-primary-600">
                        </div>
                    </div>

                    <!-- API URL -->
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-2">آدرس وب‌سرویس بانک پارسیان (Base URL)</label>
                        <input type="url" name="api_url" value="<?php echo htmlspecialchars((string)($settings['api_url'] ?? 'https://api.parsian-bank.ir/v1')); ?>" dir="ltr" class="w-full bg-gray-50 border border-gray-200 rounded-2xl px-4 py-3 text-sm font-mono text-gray-800 focus:outline-none focus:ring-2 focus:ring-primary-600">
                    </div>

                    <!-- Credentials -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-600 mb-2">شناسه کلاینت (Client ID)</label>
                            <input type="text" name="client_id" value="<?php echo htmlspecialchars((string)($settings['client_id'] ?? '')); ?>" placeholder="دریافتی از بانک" dir="ltr" class="w-full bg-gray-50 border border-gray-200 rounded-2xl px-4 py-3 text-sm font-mono text-gray-800 focus:outline-none focus:ring-2 focus:ring-primary-600">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-600 mb-2">رمز کلاینت (Client Secret)</label>
                            <input type="password" name="client_secret" value="<?php echo htmlspecialchars((string)($settings['client_secret'] ?? '')); ?>" placeholder="دریافتی از بانک" dir="ltr" class="w-full bg-gray-50 border border-gray-200 rounded-2xl px-4 py-3 text-sm font-mono text-gray-800 focus:outline-none focus:ring-2 focus:ring-primary-600">
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-2">کلید دسترسی اختصاصی (API Key / Token)</label>
                        <input type="password" name="api_key" value="<?php echo htmlspecialchars((string)($settings['api_key'] ?? '')); ?>" placeholder="کلید امنیتی API" dir="ltr" class="w-full bg-gray-50 border border-gray-200 rounded-2xl px-4 py-3 text-sm font-mono text-gray-800 focus:outline-none focus:ring-2 focus:ring-primary-600">
                    </div>

                    <div class="flex items-center justify-between pt-4 border-t border-gray-100">
                        <button type="button" @click="testConnection()" :disabled="testing" class="px-6 py-3 bg-gray-100 hover:bg-gray-200 text-gray-700 text-xs font-black rounded-2xl transition flex items-center gap-2">
                            <span x-show="!testing">⚡ تست آنلاین اتصال</span>
                            <span x-show="testing" class="animate-spin">⌛</span>
                            <span x-show="testing">در حال بررسی...</span>
                        </button>
                        <button type="submit" class="px-8 py-3 bg-primary-900 hover:bg-teal-700 text-white text-xs font-black rounded-2xl transition shadow-xl">
                            ذخیره تنظیمات
                        </button>
                    </div>
                </form>
            </div>

            <!-- Side Info & Health Check (1 col) -->
            <div class="space-y-6">
                <!-- Status Box -->
                <div class="bg-white rounded-3xl p-6 shadow-xl border border-gray-100">
                    <h3 class="text-sm font-black text-primary-900 mb-4 flex items-center gap-2">
                        <span>📡</span> وضعیت فعلی اتصال
                    </h3>
                    
                    <div class="space-y-3 text-xs">
                        <div class="flex justify-between items-center py-2 border-b border-gray-50">
                            <span class="text-gray-400">بانک طرف قرارداد:</span>
                            <span class="font-bold text-red-600">بانک پارسیان</span>
                        </div>
                        <div class="flex justify-between items-center py-2 border-b border-gray-50">
                            <span class="text-gray-400">حالت سامانه:</span>
                            <span class="font-bold <?php echo ($settings['is_sandbox'] ?? 1) ? 'text-amber-600' : 'text-emerald-600'; ?>">
                                <?php echo ($settings['is_sandbox'] ?? 1) ? '🧪 شبیه‌ساز (تست)' : '🟢 متصل به بانک (واقعی)'; ?>
                            </span>
                        </div>
                        <div class="flex justify-between items-center py-2 border-b border-gray-50">
                            <span class="text-gray-400">نوع انتقال:</span>
                            <span class="font-bold text-gray-700">پایا گروهی / داخلی پارسیان</span>
                        </div>
                        <div class="flex justify-between items-center py-2">
                            <span class="text-gray-400">آخرین بروزرسانی:</span>
                            <span class="font-mono text-gray-600"><?php echo htmlspecialchars((string)($settings['updated_at'] ?? 'ثبت نشده')); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Test Result Modal / Alert -->
                <div x-show="testResult" x-cloak class="p-5 rounded-3xl text-xs space-y-2 border transition-all"
                     :class="testResult && testResult.success ? 'bg-emerald-50 text-emerald-900 border-emerald-200' : 'bg-rose-50 text-rose-900 border-rose-200'">
                    <div class="font-black flex items-center gap-2">
                        <span x-text="testResult && testResult.success ? '✅' : '❌'"></span>
                        <span x-text="testResult && testResult.message"></span>
                    </div>
                    <div x-show="testResult && testResult.latency_ms" class="text-[11px] opacity-80">
                        زمان پاسخ سرور: <span class="font-mono" x-text="testResult.latency_ms"></span> میلی‌ثانیه
                    </div>
                </div>

                <!-- Guide Box -->
                <div class="bg-primary-900 text-white rounded-3xl p-6 shadow-xl relative overflow-hidden">
                    <div class="absolute -left-10 -bottom-10 w-40 h-40 bg-teal-500/10 rounded-full blur-2xl"></div>
                    <h3 class="text-sm font-black text-teal-400 mb-3 flex items-center gap-2">
                        <span>ℹ️</span> راهنمای دریافت API از بانک
                    </h3>
                    <p class="text-xs text-gray-300 leading-relaxed space-y-2">
                        برای فعال‌سازی وب‌سرویس پایا و انتقال وجه گروهی:
                        <br>۱. فرم درخواست «بانکداری باز / خدمات وب‌سرویس شرکتی» را در شعبه پارسیان تکمیل فرمایید.
                        <br>۲. کلاینت‌آیدی و کلید دریافتی را در فیلدهای روبه‌رو وارد کنید.
                        <br>۳. در صورت نیاز به پرداخت فوری، منشی می‌تواند فایل استاندارد اکسل را از بخش پرداخت دانلود و در اینترنت‌بانک آپلود کند.
                    </p>
                </div>
            </div>

        </div>

    </main>

    <script>
        function parsianSettings() {
            return {
                testing: false,
                testResult: null,
                async testConnection() {
                    this.testing = true;
                    this.testResult = null;
                    const formData = new FormData();
                    formData.append('ajax_action', 'test_connection');

                    try {
                        const res = await fetch('parsian-settings.php', { method: 'POST', body: formData });
                        const data = await res.json();
                        this.testResult = data;
                    } catch (e) {
                        this.testResult = { success: false, message: 'خطا در برقراری ارتباط با وب‌سرور.' };
                    } finally {
                        this.testing = false;
                    }
                }
            }
        }
    </script>
</body>
</html>
