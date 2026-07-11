<?php
session_start();
require_once 'includes/db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch Donor Data
$stmt = $pdo->prepare("SELECT * FROM donors WHERE id = ?");
$stmt->execute([$id]);
$donor = $stmt->fetch();

if (!$donor) {
    die("اطلاعات نیکوکار یافت نشد.");
}

// Access Control
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$is_admin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'];

// Benefactors can only see their own profile
if ($_SESSION['role'] === 'benefactor' && $_SESSION['related_id'] != $id) {
    die("شما دسترسی به این پرونده را ندارید.");
}

// Fetch Donations & Documents
$dn_stmt = $pdo->prepare("SELECT * FROM donations WHERE donor_id = ? ORDER BY date DESC");
$dn_stmt->execute([$id]);
$donations = $dn_stmt->fetchAll();

$doc_stmt = $pdo->prepare("SELECT * FROM documents WHERE owner_type = 'donor' AND owner_id = ? ORDER BY upload_date DESC");
$doc_stmt->execute([$id]);
$documents = $doc_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پرونده نیکوکار: <?php echo $donor['name']; ?> | بنیاد حکمت</title>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@100;400;700;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
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
<body class="bg-gray-50 font-sans text-gray-800 antialiased" x-data="{ showEditModal: false, showDocModal: false, showDonationModal: false, donationForm: {id: '', amount: '', month: '', year: '', date: '', description: '', receipt_no: ''} }">

    <?php include 'includes/navbar.php'; ?>

    <main class="container mx-auto px-6 py-12 max-w-6xl">
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-10">
            
            <!-- Profile Sidebar -->
            <div class="lg:col-span-1 space-y-6">
                <div class="bg-white rounded-[3rem] p-10 shadow-xl border border-gray-100 text-center relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-full h-2 bg-gradient-to-l from-indigo-500 to-teal-500"></div>
                    
                    <div class="relative w-28 h-28 mx-auto mb-6 group cursor-pointer overflow-hidden rounded-[2.5rem] border-4 border-white shadow-xl bg-gray-50">
                        <img id="donorImg" src="<?php echo $donor['photo_path'] ?: 'https://ui-avatars.com/api/?name=' . $donor['name'] . '&background=00141e&color=fff&size=200'; ?>" 
                             class="relative w-full h-full object-cover">
                        
                        <?php if ($is_admin): ?>
                        <div class="absolute inset-0 bg-black/40 text-white flex flex-col items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                            <span class="text-2xl">📸</span>
                            <input type="file" @change="uploadPhoto($event)" class="absolute inset-0 opacity-0 cursor-pointer">
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <h1 class="text-2xl font-black text-primary-900 mb-2"><?php echo $donor['name'] . ' ' . $donor['surname']; ?></h1>
                    <p class="text-teal-600 font-bold text-xs">حامی طرح‌های بنیاد حکمت</p>
                    
                    <div class="mt-8 space-y-4 text-right">
                        <div class="bg-gray-50 p-4 rounded-2xl">
                            <p class="text-[10px] text-gray-400 font-bold mb-1">شماره تماس</p>
                            <p class="text-sm font-mono font-bold text-gray-700 dir-ltr text-left"><?php echo $donor['phone']; ?></p>
                        </div>
                        <?php if ($is_admin): ?>
                            <button @click="showEditModal = true" class="w-full py-3 bg-primary-900 text-white rounded-2xl text-[10px] font-black shadow-lg hover:bg-teal-600 transition-all">ویرایش اطلاعات حامی</button>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bg-primary-900 rounded-[3rem] p-10 shadow-xl text-white">
                    <h2 class="text-3xl font-black text-teal-400 mb-2"><?php echo number_format($donor['total_donated']); ?></h2>
                    <p class="text-white/50 text-xs font-bold mb-8">مجموع حمایت‌های مالی (ریال)</p>
                    
                    <div class="space-y-4">
                        <h4 class="text-xs font-black text-white/40 mb-4 border-b border-white/5 pb-2">مکاتبات و اسناد پشتیبانی</h4>
                        <div class="space-y-2">
                        <?php foreach ($documents as $doc): ?>
                            <div class="bg-white/5 p-2 rounded-lg flex items-center justify-between text-[10px]">
                                <span class="font-bold"><?php echo mb_strimwidth($doc['file_name'], 0, 15, "..."); ?></span>
                                <div class="flex gap-1">
                                    <a href="<?php echo $doc['file_path']; ?>" target="_blank" class="w-6 h-6 flex items-center justify-center bg-white/10 rounded">📥</a>
                                    <?php if ($is_admin): ?>
                                    <button @click="deleteDoc(<?php echo $doc['id']; ?>)" class="w-6 h-6 flex items-center justify-center bg-red-500/20 text-red-400 rounded">🗑️</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        </div>
                        <?php if ($is_admin): ?>
                        <button @click="showDocModal = true" class="w-full py-4 border border-dashed border-white/20 rounded-2xl text-[10px] font-bold hover:bg-white/5 transition-all">+ بارگذاری سند/مکاتبه</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Transaction History -->
            <div class="lg:col-span-2 space-y-10">
                <div class="bg-white rounded-[3.5rem] p-12 shadow-xl border border-gray-100">
                    <div class="flex justify-between items-center mb-10">
                        <h3 class="text-2xl font-black text-primary-900 flex items-center gap-4">
                            <span class="w-12 h-12 bg-indigo-50 text-indigo-600 rounded-2xl flex items-center justify-center">💳</span>
                            تاریخچه واریزی‌ها و حمایت‌ها
                        </h3>
                        <?php if ($is_admin): ?>
                        <button @click="donationForm = {id: '', amount: '', month: '', year: '', date: '', description: '', receipt_no: ''}; showDonationModal = true;" class="py-2 px-4 bg-teal-600 text-white rounded-xl text-xs font-black shadow-lg hover:bg-primary-900 transition-all">+ ثبت واریزی جدید</button>
                        <?php endif; ?>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-right">
                            <thead>
                                <tr class="text-[11px] text-gray-400 uppercase tracking-wider border-b border-gray-50">
                                    <th class="pb-6 font-bold">بابت ماه/سال</th>
                                    <th class="pb-6 font-bold">مبلغ (ریال)</th>
                                    <th class="pb-6 font-bold w-1/3">بابت (توضیحات)</th>
                                    <th class="pb-6 font-bold">تاریخ ثبت</th>
                                    <?php if ($is_admin): ?><th class="pb-6 font-bold text-center">عملیات</th><?php endif; ?>
                                </tr>
                            </thead>
                            <tbody class="text-sm text-gray-700">
                                <?php foreach ($donations as $dn): ?>
                                <tr class="border-b border-gray-50 hover:bg-gray-50/50 transition-colors">
                                    <td class="py-6 font-bold text-gray-900"><?php echo $dn['month'] . ' ' . $dn['year']; ?></td>
                                    <td class="py-6 font-black text-teal-600"><?php echo number_format($dn['amount']); ?></td>
                                    <td class="py-6 text-[11px] text-gray-500 leading-relaxed"><?php echo $dn['description'] ?: '---'; ?></td>
                                    <td class="py-6 text-[11px] text-gray-400"><?php echo $dn['date'] ?: '---'; ?></td>
                                    <?php if ($is_admin): ?>
                                    <td class="py-6 flex gap-2 justify-center">
                                        <button @click="donationForm = {id: '<?php echo $dn['id']; ?>', amount: '<?php echo $dn['amount']; ?>', month: '<?php echo $dn['month']; ?>', year: '<?php echo $dn['year']; ?>', date: '<?php echo $dn['date']; ?>', description: '<?php echo htmlspecialchars($dn['description'] ?? '', ENT_QUOTES); ?>', receipt_no: '<?php echo htmlspecialchars($dn['receipt_no'] ?? '', ENT_QUOTES); ?>'}; showDonationModal = true;" class="w-8 h-8 flex items-center justify-center bg-indigo-50 text-indigo-600 hover:bg-indigo-500 hover:text-white rounded-xl transition-all">✏️</button>
                                        <button @click="deleteDonation(<?php echo $dn['id']; ?>)" class="w-8 h-8 flex items-center justify-center bg-red-50 text-red-500 hover:bg-red-500 hover:text-white rounded-xl transition-all">🗑️</button>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- EDIT MODAL -->
    <div x-show="showEditModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-primary-900/40 backdrop-blur-sm p-4">
        <div @click.away="showEditModal = false" class="bg-white w-full max-w-lg rounded-[3rem] shadow-2xl p-8">
            <h3 class="text-xl font-black text-primary-900 mb-8 border-b pb-4">ویرایش اطلاعات حامی</h3>
            <form id="editDonorForm" class="space-y-6">
                <input type="hidden" name="id" value="<?php echo $donor['id']; ?>">
                <input type="hidden" name="action" value="update_donor">
                <div class="grid grid-cols-2 gap-4">
                    <input type="text" name="name" value="<?php echo $donor['name']; ?>" placeholder="نام" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm">
                    <input type="text" name="surname" value="<?php echo $donor['surname']; ?>" placeholder="نام خانوادگی" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm">
                </div>
                <input type="text" name="phone" value="<?php echo $donor['phone']; ?>" placeholder="تلفن" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm">
                <input type="text" name="birthday" value="<?php echo $donor['birthday']; ?>" placeholder="تاریخ تولد" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm">
                <textarea name="description" placeholder="توضیحات" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm h-32"><?php echo $donor['description']; ?></textarea>
                <button type="button" @click="saveDonor()" class="w-full py-4 bg-teal-600 text-white rounded-2xl font-black shadow-xl">ذخیره تغییرات</button>
            </form>
        </div>
    </div>

    <!-- DOC MODAL -->
    <div x-show="showDocModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-primary-900/40 backdrop-blur-sm p-4">
        <div @click.away="showDocModal = false" class="bg-white w-full max-w-md rounded-[3rem] shadow-2xl p-10">
            <h3 class="text-xl font-black text-primary-900 mb-8 border-b pb-4">بارگذاری سند جدید</h3>
            <form id="uploadDocForm" class="space-y-6">
                <input type="hidden" name="action" value="upload_document">
                <input type="hidden" name="owner_type" value="donor">
                <input type="hidden" name="owner_id" value="<?php echo $donor['id']; ?>">
                <input type="file" name="document" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm">
                <input type="text" name="description" placeholder="توضیح سند" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm">
                <button type="button" @click="saveDoc()" class="w-full py-4 bg-indigo-600 text-white rounded-2xl font-black">بارگذاری</button>
            </form>
        </div>
    </div>

    <!-- DONATION MODAL -->
    <div x-show="showDonationModal" class="fixed inset-0 z-[100] flex items-center justify-center bg-primary-900/40 backdrop-blur-sm p-4">
        <div @click.away="showDonationModal = false" class="bg-white w-full max-w-lg rounded-[3rem] shadow-2xl p-8">
            <h3 class="text-xl font-black text-primary-900 mb-8 border-b pb-4" x-text="donationForm.id ? 'ویرایش واریزی' : 'ثبت واریزی جدید'"></h3>
            <form id="donationFormElement" class="space-y-6">
                <input type="hidden" name="action" :value="donationForm.id ? 'edit_donation' : 'add_donation'">
                <input type="hidden" name="id" :value="donationForm.id">
                <input type="hidden" name="donor_id" value="<?php echo $donor['id']; ?>">
                <div class="grid grid-cols-2 gap-4">
                    <input type="text" name="amount" x-model="donationForm.amount" placeholder="مبلغ (ریال)" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm font-bold text-left" dir="ltr">
                    <input type="text" name="date" x-model="donationForm.date" placeholder="تاریخ ثبت" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm text-left" dir="ltr">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <input type="text" name="month" x-model="donationForm.month" placeholder="ماه پرداختی" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm">
                    <input type="text" name="year" x-model="donationForm.year" placeholder="سال پرداختی" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm">
                </div>
                <input type="text" name="receipt_no" x-model="donationForm.receipt_no" placeholder="شماره پیگیری/فیش" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm text-left" dir="ltr">
                <textarea name="description" x-model="donationForm.description" placeholder="بابت / توضیحات" class="w-full bg-gray-50 border border-gray-100 rounded-2xl px-4 py-3 text-sm h-24"></textarea>
                <button type="button" @click="saveDonation()" class="w-full py-4 bg-teal-600 text-white rounded-2xl font-black shadow-xl" x-text="donationForm.id ? 'ذخیره تغییرات' : 'ثبت واریزی'"></button>
            </form>
        </div>
    </div>

    <script>
        async function uploadPhoto(e) {
            const formData = new FormData();
            formData.append('photo', e.target.files[0]);
            formData.append('owner_type', 'donor');
            formData.append('owner_id', '<?php echo $donor['id']; ?>');
            formData.append('action', 'upload_photo');

            const res = await fetch('admin-request-handler.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) {
                document.getElementById('donorImg').src = data.path + '?' + Date.now();
            } else {
                alert(data.message);
            }
        }

        async function saveDonor() {
            const form = document.getElementById('editDonorForm');
            const res = await fetch('admin-request-handler.php', { method: 'POST', body: new FormData(form) });
            const data = await res.json();
            if (data.success) location.reload();
        }

        async function saveDoc() {
            const form = document.getElementById('uploadDocForm');
            const res = await fetch('admin-request-handler.php', { method: 'POST', body: new FormData(form) });
            const data = await res.json();
            if (data.success) location.reload();
        }

        async function deleteDoc(id) {
            if (!confirm('حذف شود؟')) return;
            const formData = new FormData();
            formData.append('id', id);
            formData.append('action', 'delete_document');
            const res = await fetch('admin-request-handler.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) location.reload();
        }

        async function saveDonation() {
            const form = document.getElementById('donationFormElement');
            const res = await fetch('admin-request-handler.php', { method: 'POST', body: new FormData(form) });
            const data = await res.json();
            if (data.success) location.reload();
        }

        async function deleteDonation(id) {
            if (!confirm('آیا از حذف این تراکنش اطمینان دارید؟')) return;
            const formData = new FormData();
            formData.append('id', id);
            formData.append('action', 'delete_donation');
            const res = await fetch('admin-request-handler.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) location.reload();
        }
    </script>

</body>
</html>
