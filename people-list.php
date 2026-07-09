<?php
session_start();
require_once 'includes/db.php';

// Access Control
if (!isset($_SESSION['is_admin']) || !$_SESSION['is_admin']) {
    header("Location: login.php");
    exit();
}

$query = "SELECT * FROM students ORDER BY id ASC";
$stmt = $pdo->query($query);
$students = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>مدیریت مددجویان | بنیاد حکمت</title>
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

    <?php include 'includes/navbar.php'; ?>

    <header class="bg-primary-900 text-white py-12">
        <div class="container mx-auto px-6 flex flex-col md:flex-row justify-between items-center gap-8">
            <div class="text-right">
                <h1 class="text-3xl font-black mb-2">مدیریت افراد تحت پوشش</h1>
                <p class="text-teal-100 opacity-70 text-sm">لیست هوشمند مددجویان و نخبگان تحت حمایت بنیاد.</p>
            </div>
            <button onclick="alert('قابلیت درج دانش‌آموز جدید در فاز نهایی فعال می‌شود.')" 
                    class="bg-teal-500 hover:bg-teal-400 text-white font-black px-8 py-3 rounded-2xl shadow-xl transition-all transform hover:-translate-y-1">
                + افزودن مددجوی جدید
            </button>
        </div>
    </header>

    <main class="container mx-auto px-6 -mt-8 pb-20">
        <!-- Search & Stats Bar -->
        <div class="bg-white rounded-3xl shadow-xl p-6 mb-8 flex flex-col md:flex-row justify-between items-center gap-6 border border-gray-100">
            <div class="flex flex-col md:flex-row gap-4 w-full md:w-auto flex-1">
                <div class="relative w-full md:w-80">
                    <input type="text" id="searchInput" placeholder="جستجوی نام، کد مددجو ..." 
                        class="w-full bg-gray-50 border border-gray-200 rounded-2xl px-12 py-3 focus:outline-none focus:ring-2 focus:ring-primary-600 transition-all text-sm">
                    <span class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400">🔍</span>
                </div>
                <div class="relative w-full md:w-48">
                    <select id="statusFilter" class="w-full bg-gray-50 border border-gray-200 rounded-2xl px-4 py-3 focus:outline-none focus:ring-2 focus:ring-primary-600 transition-all text-sm appearance-none text-gray-600 font-bold cursor-pointer">
                        <option value="all">همه مددجویان</option>
                        <option value="active">تحت پوشش (فعال)</option>
                        <option value="graduated">فارغ‌التحصیل</option>
                        <option value="university">دانشجو (آموزش عالی)</option>
                        <option value="exited">خروج از بورس</option>
                    </select>
                </div>
            </div>
            <div class="flex gap-10">
                <div class="text-center">
                    <div class="text-2xl font-black text-primary-900"><?php echo count($students); ?></div>
                    <div class="text-[10px] text-gray-400 font-bold">تعداد کل مددجویان</div>
                </div>
                <div class="w-px h-10 bg-gray-100"></div>
                <div class="text-center">
                    <div class="text-2xl font-black text-teal-600">۱۰۰٪</div>
                    <div class="text-[10px] text-gray-400 font-bold">دقت داده‌های اکسل</div>
                </div>
            </div>
        </div>

        <!-- Listing Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-6" id="peopleGrid">
            <?php foreach ($students as $p): 
                $status = $p['status'] ?: 'active';
                $is_inactive = in_array($status, ['exited', 'graduated']);
                $card_style = $is_inactive ? 'bg-gray-100 opacity-60 grayscale border-gray-300' : 'bg-white border-gray-100';
                $icon_style = $is_inactive ? 'bg-gray-200 text-gray-500' : 'bg-teal-50 text-teal-600';
            ?>
            <div class="people-card <?php echo $card_style; ?> rounded-[2.5rem] p-8 shadow-sm hover:shadow-xl transition-all group relative overflow-hidden" 
                 data-status="<?php echo $status; ?>" 
                 data-search="<?php echo htmlspecialchars($p['name'] . ' ' . $p['surname'] . ' ' . $p['code'] . ' ' . $p['national_id']); ?>">
                <div class="absolute top-0 right-0 w-24 h-24 bg-teal-500/5 rounded-full -mr-10 -mt-10 group-hover:scale-150 transition-transform"></div>
                
                <div class="flex flex-col items-center text-center">
                    <div class="w-20 h-20 <?php echo $icon_style; ?> rounded-[1.5rem] flex items-center justify-center text-3xl font-black mb-6 shadow-inner">
                        <?php echo mb_substr($p['name'], 0, 1); ?>
                    </div>
                    
                    <h3 class="text-lg font-black text-primary-900 mb-1"><?php echo $p['name'] . ' ' . $p['surname']; ?></h3>
                    <p class="text-[10px] text-gray-400 font-bold mb-6">کد مددجو: #<?php echo $p['code']; ?></p>
                    
                    <div class="w-full grid grid-cols-2 gap-3 mb-6">
                        <div class="bg-gray-50 p-3 rounded-2xl text-right">
                            <div class="text-[8px] text-gray-400 font-bold">نام پدر</div>
                            <div class="text-[10px] font-black text-primary-900"><?php echo $p['father_name'] ?: '---'; ?></div>
                        </div>
                        <div class="bg-gray-50 p-3 rounded-2xl text-right">
                            <div class="text-[8px] text-gray-400 font-bold">پایه تحصیلی</div>
                            <div class="text-[10px] font-black text-primary-900"><?php echo $p['grade'] ?: '---'; ?></div>
                        </div>
                    </div>

                    <div class="flex w-full gap-2">
                        <a href="person-detail.php?id=<?php echo $p['id']; ?>" class="flex-1 bg-primary-900 text-white py-3 rounded-2xl text-[10px] font-black shadow-lg hover:bg-teal-600 transition-all text-center">مشاهده پرونده</a>
                        <button onclick="confirmDelete(<?php echo $p['id']; ?>, 'student')" class="w-12 bg-red-50 text-red-500 rounded-2xl flex items-center justify-center hover:bg-red-500 hover:text-white transition-all">🗑️</button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- No Results -->
        <div id="noResults" class="hidden text-center py-20">
            <div class="text-6xl mb-4">🔦</div>
            <h3 class="text-xl font-bold text-gray-400">مددجویی با این مشخصات یافت نشد.</h3>
        </div>
    </main>

    <script>
        const searchInput = document.getElementById('searchInput');
        const statusFilter = document.getElementById('statusFilter');
        const peopleGrid = document.getElementById('peopleGrid');
        const cards = document.querySelectorAll('.people-card');
        const noResults = document.getElementById('noResults');

        function filterCards() {
            const query = searchInput.value.toLowerCase().trim();
            const selectedStatus = statusFilter.value;
            let hasResults = false;

            cards.forEach(card => {
                const searchData = (card.getAttribute('data-search') || '').toLowerCase();
                const cardStatus = card.getAttribute('data-status');
                
                const matchesSearch = searchData.includes(query);
                const matchesStatus = (selectedStatus === 'all') || (cardStatus === selectedStatus);

                if (matchesSearch && matchesStatus) {
                    card.style.display = 'block';
                    hasResults = true;
                } else {
                    card.style.display = 'none';
                }
            });

            noResults.classList.toggle('hidden', hasResults);
        }

        searchInput.addEventListener('input', filterCards);
        statusFilter.addEventListener('change', filterCards);

        function confirmDelete(id) {
            if (confirm('آیا از حذف این پرونده اطمینان دارید؟ این عمل غیرقابل بازگشت است.')) {
                alert('در نسخه دمو حذف واقعی انجام نمی‌شود، اما دیتابیس آماده پذیرش دستور است.');
            }
        }
    </script>

</body>
</html>
