<?php
session_start();
require_once '../includes/db.php';

// Access Control
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: ../login.php");
    exit();
}

// 1. Total Stats
$total_students = $pdo->query("SELECT COUNT(*) FROM students")->fetchColumn();
$total_donors = $pdo->query("SELECT COUNT(*) FROM donors")->fetchColumn();
$total_collected = $pdo->query("SELECT SUM(total_donated) FROM donors")->fetchColumn();

// 2. Inactive Donors (> 90 days)
$inactive_query = "
    SELECT d.id, d.name, d.surname, MAX(dn.date) as last_date
    FROM donors d
    LEFT JOIN donations dn ON d.id = dn.donor_id
    GROUP BY d.id
    HAVING last_date < '1404/10/01' OR last_date IS NULL
    LIMIT 5
";
$inactive_donors = $pdo->query($inactive_query)->fetchAll();

// 3. Today's Birthdays (Students & Donors)
// Simulation: Let's assume today is a certain date in the DB to show alerts
$today_md = "01/12"; // Simplified month/day search
$birthdays_students = $pdo->query("SELECT id, name, surname, 'student' as type FROM students WHERE birthday LIKE '%$today_md%'")->fetchAll();
$birthdays_donors = $pdo->query("SELECT id, name, surname, 'donor' as type FROM donors WHERE birthday LIKE '%$today_md%'")->fetchAll();
$all_birthdays = array_merge($birthdays_students, $birthdays_donors);
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پنل مدیریت عالی | بنیاد حکمت</title>
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
</head>
<body class="bg-gray-50 font-sans text-gray-800 antialiased">

    <nav class="bg-white/80 backdrop-blur-md border-b sticky top-0 z-50">
        <div class="container mx-auto px-6 py-4 flex justify-between items-center">
            <div class="flex items-center gap-4">
                <a href="../index.php" class="text-primary-900 font-black text-xl">بنیاد حکمت</a>
                <span class="text-gray-300">/</span>
                <span class="font-bold text-gray-600 underline decoration-teal-400 decoration-2">میز کار مدیریت</span>
            </div>
            <div class="flex items-center gap-6">
                <div class="flex flex-col text-left">
                    <span class="text-[10px] text-gray-400 font-bold">کاربر فعال</span>
                    <span class="text-xs font-black text-primary-900">فرید برهان علمی - مدیرعامل</span>
                </div>
                <a href="../admin-logout.php" class="bg-red-50 text-red-500 p-2 rounded-xl hover:bg-red-500 hover:text-white transition-all" title="خروج">🚪</a>
            </div>
        </div>
    </nav>

    <main class="container mx-auto px-6 py-12">
        <div class="flex flex-col lg:flex-row gap-8">
            
            <!-- Main Dashboard Area -->
            <div class="flex-1 space-y-8">
                
                <!-- Stats Overview -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-gray-100 flex flex-col items-center">
                        <div class="w-12 h-12 bg-teal-50 text-teal-600 rounded-2xl flex items-center justify-center text-2xl mb-4">🎓</div>
                        <div class="text-3xl font-black text-primary-900"><?php echo toFarsiDigits($total_students); ?></div>
                        <div class="text-[10px] text-gray-400 font-bold">دانش‌آموز تحت پوشش</div>
                    </div>
                    <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-gray-100 flex flex-col items-center">
                        <div class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center text-2xl mb-4">🤝</div>
                        <div class="text-3xl font-black text-primary-900"><?php echo toFarsiDigits($total_donors); ?></div>
                        <div class="text-[10px] text-gray-400 font-bold">نیکوکار فعال</div>
                    </div>
                    <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-gray-100 flex flex-col items-center">
                        <div class="w-12 h-12 bg-green-50 text-green-600 rounded-2xl flex items-center justify-center text-2xl mb-4">💰</div>
                        <div class="text-2xl font-black text-primary-900"><?php echo toFarsiDigits(number_format($total_collected / 1000000000, 1)); ?> <span class="text-xs">میلیارد</span></div>
                        <div class="text-[10px] text-gray-400 font-bold">جذب سرمایه (ریال)</div>
                    </div>
                </div>

                <!-- Navigation Hub -->
                <div class="bg-primary-900 rounded-[3.5rem] p-12 text-white shadow-2xl relative overflow-hidden">
                    <div class="absolute -right-20 -top-20 w-80 h-80 bg-teal-500/10 rounded-full blur-3xl"></div>
                    <h2 class="text-3xl font-black mb-8 relative z-10">مدیریت مستقیم</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-6 relative z-10">
                        <a href="../people-list.php" class="bg-white/10 hover:bg-white/20 p-8 rounded-[2rem] border border-white/5 transition-all group">
                            <div class="text-3xl mb-4 group-hover:scale-110 transition-transform">📋</div>
                            <h4 class="text-xl font-bold">لیست مددجویان</h4>
                            <p class="text-[10px] text-white/50 mt-2">ویرایش پرونده‌ها و مدیریت نمرات</p>
                        </a>
                        <a href="financial.php" class="bg-white/10 hover:bg-white/20 p-8 rounded-[2rem] border border-white/5 transition-all group">
                            <div class="text-3xl mb-4 group-hover:scale-110 transition-transform">⚖️</div>
                            <h4 class="text-xl font-bold">دفتر حسابداری مالی</h4>
                            <p class="text-[10px] text-white/50 mt-2">تراز مالی، واریزی‌ها و فیش دیجیتال</p>
                        </a>
                        <a href="sponsorships.php" class="bg-white/10 hover:bg-white/20 p-8 rounded-[2rem] border border-white/10 transition-all group">
                            <div class="text-3xl mb-4 group-hover:scale-110 transition-transform">🤝</div>
                            <h4 class="text-xl font-bold">بورس و منتورینگ</h4>
                            <p class="text-[10px] text-white/50 mt-2">تخصیص حامی، کارنامه و تایید پیام‌ها</p>
                        </a>
                        <a href="bursary-payments.php" class="bg-white/10 hover:bg-white/20 p-8 rounded-[2rem] border border-teal-500/30 transition-all group relative">
                            <div class="absolute top-4 left-4 bg-teal-500 text-[8px] font-black px-2 py-0.5 rounded-full uppercase tracking-tighter animate-pulse">جدید</div>
                            <div class="text-3xl mb-4 group-hover:scale-110 transition-transform">💳</div>
                            <h4 class="text-xl font-bold">پرداخت بورسیه</h4>
                            <p class="text-[10px] text-white/50 mt-2">کسورات اقساط و امضای الکترونیک ماهانه</p>
                        </a>
                        <a href="../donors-list.php" class="bg-white/10 hover:bg-white/20 p-8 rounded-[2rem] border border-white/5 transition-all group">
                            <div class="text-3xl mb-4 group-hover:scale-110 transition-transform">💎</div>
                            <h4 class="text-xl font-bold">پورتال نیکوکاران</h4>
                            <p class="text-[10px] text-white/50 mt-2">رتبه‌بندی حامیان و تعامل مالی</p>
                        </a>
                        <a href="../expenses-list.php" class="bg-white/10 hover:bg-white/20 p-8 rounded-[2rem] border border-white/5 transition-all group">
                            <div class="text-3xl mb-4 group-hover:scale-110 transition-transform">💸</div>
                            <h4 class="text-xl font-bold">گزارش ریز هزینه‌ها</h4>
                            <p class="text-[10px] text-white/50 mt-2">هزینه‌کرد مددجویان و سرفصل‌ها</p>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Inactive Alerts Area -->
            <div class="w-full lg:w-96 space-y-6">
                <div class="bg-rose-50 rounded-[3rem] p-8 border border-rose-100 shadow-sm relative overflow-hidden">
                    <div class="absolute top-0 left-0 w-full h-1 bg-red-400"></div>
                    <div class="flex items-center gap-3 mb-6">
                        <span class="text-2xl">⚠️</span>
                        <h3 class="text-lg font-black text-rose-900">نیکوکاران غیرفعال</h3>
                    </div>
                    <p class="text-xs text-rose-700 leading-relaxed mb-6 font-bold">افرادی که بیش از ۳ ماه است واریزی نداشته‌اند:</p>
                    
                    <div class="space-y-4">
                        <?php foreach ($inactive_donors as $idr): ?>
                        <div class="bg-white p-4 rounded-2xl flex items-center justify-between border border-rose-100 shadow-sm">
                            <div class="flex items-center gap-3">
                                <div class="w-8 h-8 bg-rose-100 rounded-lg flex items-center justify-center text-xs">👤</div>
                                <div class="text-[10px] font-black text-rose-900"><?php echo $idr['name'] . ' ' . $idr['surname']; ?></div>
                            </div>
                            <a href="../donor-detail.php?id=<?php echo $idr['id']; ?>" class="text-[10px] text-teal-600 font-bold hover:underline">پیگیری</a>
                        </div>
                        <?php endforeach; ?>
                        
                        <button class="w-full py-4 bg-rose-500 text-white text-[10px] font-black rounded-2xl shadow-lg shadow-rose-200 mt-4">ارسال پیامک یادآوری به همه</button>
                    </div>
                </div>

                <div class="bg-white rounded-[3rem] p-8 border border-gray-100 shadow-sm text-center">
                    <div class="text-4xl mb-4 text-teal-600">🎂</div>
                    <h3 class="text-sm font-black text-gray-900 mb-4">تقویم تولدها</h3>
                    
                    <div class="space-y-3">
                        <?php if (empty($all_birthdays)): ?>
                            <p class="text-[10px] text-gray-400 font-bold px-4 leading-relaxed">امروز تولد هیچ یک از حامیان یا مددجویان نیست.</p>
                        <?php else: ?>
                            <?php foreach ($all_birthdays as $b): ?>
                            <div class="flex items-center justify-between bg-gray-50 p-3 rounded-xl">
                                <div class="flex items-center gap-2">
                                    <span class="text-[10px]"><?php echo $b['type'] === 'student' ? '👧' : '💎'; ?></span>
                                    <span class="text-[10px] font-black text-primary-900"><?php echo $b['name'] . ' ' . $b['surname']; ?></span>
                                </div>
                                <span class="text-[8px] bg-teal-100 text-teal-700 px-2 py-0.5 rounded-full font-bold">تبریک</span>
                            </div>
                            <?php endforeach; ?>
                            <button class="w-full py-3 bg-teal-600 text-white text-[9px] font-black rounded-xl mt-2 shadow-lg">ارسال تبریک هوشمند</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

        </div>
    </main>

</body>
</html>