<?php
session_start();
require_once 'includes/db.php';

// Access Control
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: login.php");
    exit();
}

// Fetch Donors ordered by total contribution
$query = "SELECT * FROM donors ORDER BY total_donated DESC";
$stmt = $pdo->query($query);
$donors = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پورتال نیکوکاران | بنیاد حکمت</title>
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

    <!-- iOS PWA/Homescreen Setup -->
    <link rel="apple-touch-icon" href="logo.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="بنیاد حکمت">
    <link rel="icon" type="image/png" href="logo.png">
    <link rel="manifest" href="manifest.json">
</head>
<body class="bg-gray-50 font-sans text-gray-800 antialiased">

    <?php include 'includes/navbar.php'; ?>

    <header class="bg-gradient-to-l from-primary-900 to-indigo-900 text-white py-16">
        <div class="container mx-auto px-6 flex flex-col md:flex-row justify-between items-center gap-10">
            <div class="text-right">
                <h1 class="text-4xl font-black mb-4">پورتال مدیریت نیکوکاران</h1>
                <p class="text-teal-100 opacity-80 max-w-xl">مدیریت تعامل با خیرین، مشاهده تاریخچه واریزی‌ها و رتبه‌بندی حامیان بر اساس میزان مشارکت در طرح‌های بنیاد حکمت.</p>
            </div>
            <div class="bg-white/10 backdrop-blur-xl p-8 rounded-[3rem] border border-white/20 text-center min-w-[250px]">
                <?php 
                    $global_total = $pdo->query("SELECT SUM(amount) FROM donations")->fetchColumn();
                    $global_total = $global_total ? $global_total : 0;
                ?>
                <div class="text-3xl font-black text-teal-400"><?php echo number_format((float)$global_total); ?></div>
                <div class="text-[10px] text-white/60 font-bold mt-1">مجموع جذب سرمایه (ریال)</div>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-6 -mt-10 pb-20">
        <!-- Search & Filter Bar -->
        <div class="bg-white rounded-3xl shadow-xl p-6 mb-10 flex flex-col md:flex-row justify-between items-center gap-6 border border-gray-100">
            <div class="relative w-full md:w-96">
                <input type="text" id="donorSearch" placeholder="جستجوی نام نیکوکار..." 
                    class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-12 py-4 focus:outline-none focus:ring-2 focus:ring-primary-600 transition-all text-sm">
                <span class="absolute right-5 top-1/2 -translate-y-1/2 text-gray-400">🔍</span>
            </div>
            <div class="flex items-center gap-4">
                 <span class="text-xs font-bold text-gray-400">مرتب‌سازی:</span>
                 <select class="bg-gray-50 border-none text-xs font-bold text-gray-700 px-4 py-2 rounded-xl focus:ring-0">
                     <option>بیشترین حمایت مالی</option>
                     <option>جدیدترین همکاران</option>
                 </select>
            </div>
        </div>

        <!-- Donors Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8" id="donorsGrid">
            <?php foreach ($donors as $index => $d): ?>
            <div class="donor-card bg-white rounded-[2.5rem] p-8 shadow-sm hover:shadow-2xl border border-gray-50 transition-all duration-500 relative group overflow-hidden"
                 data-name="<?php echo $d['name'] . ' ' . $d['surname']; ?>">
                
                <!-- Rank Badge -->
                <div class="absolute left-6 top-6 w-8 h-8 bg-primary-900 text-white rounded-full flex items-center justify-center text-[10px] font-black shadow-lg">
                    <?php echo $index + 1; ?>
                </div>

                <div class="flex flex-col items-center text-center">
                    <div class="w-24 h-24 bg-gray-50 rounded-[2rem] flex items-center justify-center text-4xl mb-6 border-4 border-white shadow-xl group-hover:scale-110 transition-transform">
                        🤝
                    </div>
                    <h3 class="text-xl font-black text-primary-900 mb-1"><?php echo $d['name'] . ' ' . $d['surname']; ?></h3>
                    <p class="text-xs text-gray-400 font-bold mb-6">شروع همکاری: <?php echo $d['join_date'] ?: '---'; ?></p>
                    
                    <div class="w-full bg-primary-50 rounded-2xl p-4 mb-6">
                        <div class="text-[10px] text-primary-600 font-bold mb-1">مجموع واریزی</div>
                        <div class="text-xl font-black text-primary-900"><?php echo number_format($d['total_donated']); ?> <span class="text-[10px]">ریال</span></div>
                    </div>

                    <div class="flex w-full gap-2">
                        <a href="donor-detail.php?id=<?php echo $d['id']; ?>" 
                           class="flex-1 bg-primary-900 group-hover:bg-teal-600 text-white py-3 rounded-2xl text-[10px] font-black transition-colors shadow-lg text-center">
                            مشاهده تراکنش‌ها
                        </a>
                        <button onclick="confirmDelete(<?php echo $d['id']; ?>)" 
                                class="w-12 bg-red-50 hover:bg-red-500 text-red-500 hover:text-white flex items-center justify-center rounded-2xl transition-all">
                            🗑️
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </main>

    <script>
        const donorSearch = document.getElementById('donorSearch');
        const donorCards = document.querySelectorAll('.donor-card');

        donorSearch.addEventListener('input', (e) => {
            const query = e.target.value.toLowerCase().trim();
            donorCards.forEach(card => {
                const name = card.getAttribute('data-name').toLowerCase();
                card.style.display = name.includes(query) ? 'block' : 'none';
            });
        });

        function confirmDelete(id) {
            if (confirm('آیا از حذف این پرونده اطمینان دارید؟')) {
                alert('در نسخه دمو حذف واقعی انجام نمی‌شود، اما دیتابیس آماده پذیرش دستور است.');
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
