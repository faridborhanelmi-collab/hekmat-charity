<?php
session_start();
require_once '../includes/db.php';

// Access Control
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: ../login.php");
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch Donation Details
$query = "
    SELECT d.*, dn.name as donor_name, dn.surname as donor_surname, dn.phone as donor_phone 
    FROM donations d 
    LEFT JOIN donors dn ON d.donor_id = dn.id 
    WHERE d.id = ?
";
$stmt = $pdo->prepare($query);
$stmt->execute([$id]);
$donation = $stmt->fetch();

if (!$donation) {
    die("رسید پرداخت یافت نشد.");
}

// Format Phone for WhatsApp (e.g. 09123456789 -> 989123456789)
$phone = trim($donation['donor_phone']);
$wa_phone = '';
if (!empty($phone)) {
    if (str_starts_with($phone, '0')) {
        $wa_phone = '98' . substr($phone, 1);
    } elseif (str_starts_with($phone, '+98')) {
        $wa_phone = substr($phone, 1);
    } elseif (str_starts_with($phone, '98')) {
        $wa_phone = $phone;
    } else {
        $wa_phone = '98' . $phone;
    }
}

// Format Farsi Currency and Numbers
$farsi_amount = formatFarsiCurrency($donation['amount']);
$farsi_date = toFarsiDigits($donation['date']);
$farsi_receipt = toFarsiDigits($donation['receipt_no']);

// Generate Share Message
$message = "جناب آقای / سرکار خانم " . $donation['donor_name'] . " " . $donation['donor_surname'] . " گرامی،\n";
$message .= "با سلام و دعای خیر،\n";
$message .= "رسید مهر و همدلی شما به بنیاد نیکوکاری حکمت صادر گردید:\n\n";
$message .= "🔹 مبلغ کمک: " . $farsi_amount . " ریال\n";
$message .= "🔹 تاریخ ثبت: " . $farsi_date . "\n";
$message .= "🔹 بابت: " . ($donation['description'] ?: 'کمک‌های عمومی') . "\n";
if (!empty($donation['receipt_no'])) {
    $message .= "🔹 شماره فیش/سند: " . $farsi_receipt . "\n";
}
$message .= "\nسهم ارزشمند شما در توانمندسازی و ساختن آینده روشن فرزندان نخبه میهن گرامی باد. 🌸\n";
$message .= "بنیاد نیکوکاری حکمت - ثبت ۷۷۰۷";

$encoded_msg = urlencode($message);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>رسید دیجیتال مهربانی | بنیاد حکمت</title>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@100;400;700;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
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
    <style>
        @media print {
            body { background: white; color: black; }
            .no-print { display: none !important; }
            .receipt-card { border: none !important; box-shadow: none !important; margin: 0 !important; width: 100% !important; }
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
<body class="bg-gray-100 font-sans text-gray-800 antialiased min-h-screen flex flex-col justify-between">

    <!-- Top Navigation (No Print) -->
    <nav class="bg-white/80 backdrop-blur-md border-b py-4 no-print">
        <div class="container mx-auto px-6 flex justify-between items-center">
            <a href="financial.php" class="text-primary-900 font-bold text-xs flex items-center gap-2">
                <span>←</span> بازگشت به دفتر حسابداری
            </a>
            <span class="font-black text-xs text-gray-400">فیش مهربانی</span>
        </div>
    </nav>

    <!-- Receipt Center Area -->
    <main class="flex-1 flex items-center justify-center p-6">
        <div class="w-full max-w-lg space-y-6">
            
            <!-- Receipt Card -->
            <div class="receipt-card bg-white rounded-[3rem] p-10 shadow-2xl border border-gray-100 relative overflow-hidden text-center">
                <!-- Top Decorative Banner -->
                <div class="absolute top-0 left-0 w-full h-3 bg-gradient-to-l from-emerald-500 via-teal-500 to-indigo-500"></div>
                
                <!-- Logo -->
                <div class="flex justify-center mb-6">
                    <div class="w-16 h-16 bg-gradient-to-tr from-teal-500 to-teal-400 rounded-full flex items-center justify-center text-white text-2xl font-bold shadow-md">ح</div>
                </div>
                
                <h2 class="text-xl font-black text-teal-700 mb-1">لوح سپاس و رسید دیجیتال</h2>
                <p class="text-[10px] text-gray-400 font-bold">شماره ثبت موسسه: ۷۷۰۷</p>
                
                <div class="w-full h-px bg-gray-100 my-6"></div>
                
                <!-- Details Grid -->
                <div class="space-y-4 text-right">
                    <div class="flex justify-between items-center py-2 border-b border-gray-50">
                        <span class="text-[10px] text-gray-400 font-bold">نام نیکوکار:</span>
                        <span class="text-xs font-black text-gray-900"><?php echo $donation['donor_name'] . ' ' . $donation['donor_surname']; ?></span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-gray-50">
                        <span class="text-[10px] text-gray-400 font-bold">مبلغ مشارکت:</span>
                        <span class="text-sm font-black text-emerald-600" dir="ltr"><?php echo $farsi_amount; ?> ریال</span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-b border-gray-50">
                        <span class="text-[10px] text-gray-400 font-bold">تاریخ ثبت سند:</span>
                        <span class="text-xs font-bold text-gray-700"><?php echo $farsi_date; ?></span>
                    </div>
                    <?php if (!empty($donation['receipt_no'])): ?>
                    <div class="flex justify-between items-center py-2 border-b border-gray-50">
                        <span class="text-[10px] text-gray-400 font-bold">شماره فیش/سند:</span>
                        <span class="text-xs font-mono font-bold text-gray-700" dir="ltr">#<?php echo $farsi_receipt; ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="py-2">
                        <span class="text-[10px] text-gray-400 font-bold block mb-1">بابت/شرح کمک:</span>
                        <p class="text-xs font-bold text-gray-700 bg-gray-50 p-4 rounded-2xl leading-relaxed"><?php echo $donation['description'] ?: 'کمک‌های عمومی جهت حمایت از نخبگان مستعد'; ?></p>
                    </div>
                </div>

                <div class="w-full h-px bg-gray-100 my-6"></div>

                <!-- Footer thank you note -->
                <p class="text-[11px] text-gray-500 leading-relaxed font-bold italic">«نیکوکاری، بذری است که امروز می‌کارید و فردا جنگلی از امید می‌شود.»</p>
            </div>

            <!-- Action buttons (No Print) -->
            <div class="grid grid-cols-3 gap-4 no-print">
                <!-- WhatsApp -->
                <a href="https://api.whatsapp.com/send?phone=<?php echo $wa_phone; ?>&text=<?php echo $encoded_msg; ?>" target="_blank" 
                   class="flex flex-col items-center justify-center p-4 bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-100 rounded-3xl transition-all shadow-sm">
                    <span class="text-2xl mb-1">💬</span>
                    <span class="text-[9px] font-black">ارسال واتساپ</span>
                </a>
                <!-- Telegram -->
                <a href="https://t.me/share/url?url=https://hekmat.neromoda.ir&text=<?php echo $encoded_msg; ?>" target="_blank" 
                   class="flex flex-col items-center justify-center p-4 bg-sky-50 hover:bg-sky-100 text-sky-700 border border-sky-100 rounded-3xl transition-all shadow-sm">
                    <span class="text-2xl mb-1">✈️</span>
                    <span class="text-[9px] font-black">ارسال تلگرام</span>
                </a>
                <!-- Print -->
                <button onclick="window.print()" 
                        class="flex flex-col items-center justify-center p-4 bg-gray-50 hover:bg-gray-100 text-gray-700 border border-gray-200 rounded-3xl transition-all shadow-sm">
                    <span class="text-2xl mb-1">🖨️</span>
                    <span class="text-[9px] font-black">چاپ رسید</span>
                </button>
            </div>

        </div>
    </main>

    <!-- Bottom Footer (No Print) -->
    <footer class="bg-white border-t py-4 text-center text-[10px] text-gray-400 font-bold no-print">
        بنیاد نیکوکاری حکمت © 2026 - طراحی بومی حسابداری
    </footer>

</body>
</html>
