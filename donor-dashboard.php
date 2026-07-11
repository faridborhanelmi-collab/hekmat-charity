<?php
session_start();
require_once 'includes/db.php';

// Auth Guard - only donors/benefactors can access
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'benefactor') {
    header("Location: login.php");
    exit();
}

$donor_id = $_SESSION['user_id'];

// Get Donor Details
$stmt = $pdo->prepare("SELECT * FROM donors WHERE id = ?");
$stmt->execute([$donor_id]);
$donor = $stmt->fetch();

if (!$donor) {
    session_destroy();
    header("Location: login.php");
    exit();
}

// Handle message submission
$message_status = '';
$message_type = '';

$is_board_member = ($donor_id === 1306 || $donor_id === 1310);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'send_message') {
        $msg_text = trim($_POST['message_text'] ?? '');
        $sponsorship_id = (int)($_POST['sponsorship_id'] ?? 0);
        
        if ($msg_text !== '' && $sponsorship_id > 0) {
            // Verify this donor owns this sponsorship
            $chk = $pdo->prepare("SELECT id FROM sponsorships WHERE id = ? AND donor_id = ?");
            $chk->execute([$sponsorship_id, $donor_id]);
            if ($chk->fetch()) {
                $ins = $pdo->prepare("INSERT INTO sponsorship_messages (sponsorship_id, sender_type, message_text, status, created_at) VALUES (?, 'donor', ?, 'pending', ?)");
                $ins->execute([
                    $sponsorship_id,
                    $msg_text,
                    date('Y/m/d H:i')
                ]);
                $message_status = 'پیام راهنمایی (منتورینگ) شما ثبت شد و پس از تایید نهایی توسط بنیاد، به دست دانش‌پژوه خواهد رسید.';
                $message_type = 'success';
            } else {
                $message_status = 'تراکنش غیرمجاز است.';
                $message_type = 'error';
            }
        } else {
            $message_status = 'متن پیام نمی‌تواند خالی باشد.';
            $message_type = 'error';
        }
    }
    
    // Board Member Signature Action
    if ($is_board_member && $_POST['action'] === 'sign_bursary_list') {
        $list_id = (int)($_POST['list_id'] ?? 0);
        $now_time = date('Y/m/d H:i:s');
        
        try {
            $pdo->beginTransaction();
            
            if ($donor_id === 1306) {
                $stmt = $pdo->prepare("UPDATE monthly_bursary_lists SET signed_bahraman = 1, signed_bahraman_at = ? WHERE id = ? AND status = 'pending_signatures'");
                $stmt->execute([$now_time, $list_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE monthly_bursary_lists SET signed_sanobari = 1, signed_sanobari_at = ? WHERE id = ? AND status = 'pending_signatures'");
                $stmt->execute([$now_time, $list_id]);
            }
            
            // Check if both signed
            $stmt_chk = $pdo->prepare("SELECT signed_bahraman, signed_sanobari FROM monthly_bursary_lists WHERE id = ?");
            $stmt_chk->execute([$list_id]);
            $list_chk = $stmt_chk->fetch();
            
            if ($list_chk && $list_chk['signed_bahraman'] == 1 && $list_chk['signed_sanobari'] == 1) {
                $stmt_upd = $pdo->prepare("UPDATE monthly_bursary_lists SET status = 'signed' WHERE id = ?");
                $stmt_upd->execute([$list_id]);
            }
            
            $pdo->commit();
            $message_status = 'سند مالی با موفقیت امضا و تایید الکترونیک گردید.';
            $message_type = 'success';
        } catch (Exception $e) {
            $pdo->rollBack();
            $message_status = 'خطا در ثبت امضا: ' . $e->getMessage();
            $message_type = 'error';
        }
    }
}

// Fetch pending monthly bursaries requiring board member's signature
$pending_board_lists = [];
$pending_items_by_list = [];
if ($is_board_member) {
    if ($donor_id === 1306) {
        $stmt_pl = $pdo->query("SELECT * FROM monthly_bursary_lists WHERE status = 'pending_signatures' AND signed_bahraman = 0 ORDER BY id ASC");
    } else {
        $stmt_pl = $pdo->query("SELECT * FROM monthly_bursary_lists WHERE status = 'pending_signatures' AND signed_sanobari = 0 ORDER BY id ASC");
    }
    $pending_board_lists = $stmt_pl->fetchAll();
    
    if (!empty($pending_board_lists)) {
        $list_ids = array_map(function($l) { return (int)$l['id']; }, $pending_board_lists);
        $in_clause = implode(',', $list_ids);
        $items_stmt = $pdo->query("SELECT * FROM monthly_bursary_items WHERE list_id IN ($in_clause) ORDER BY id ASC");
        $all_pending_items = $items_stmt->fetchAll();
        foreach ($all_pending_items as $item) {
            $pending_items_by_list[$item['list_id']][] = $item;
        }
    }
}

// Fetch Sponsoring Summary
$spon_summary = $pdo->prepare("
    SELECT COUNT(id) as total_students, SUM(shares_count) as total_shares 
    FROM sponsorships 
    WHERE donor_id = ? AND status = 'active'
");
$spon_summary->execute([$donor_id]);
$summary = $spon_summary->fetch();
$total_students = $summary['total_students'] ?: 0;
$total_shares = $summary['total_shares'] ?: 0;

// Fetch Recent Donations
$donations_stmt = $pdo->prepare("
    SELECT amount, date, receipt_no, description 
    FROM donations 
    WHERE donor_id = ? 
    ORDER BY date DESC 
    LIMIT 5
");
$donations_stmt->execute([$donor_id]);
$donations = $donations_stmt->fetchAll();

// Fetch Sponsored Students
$students_stmt = $pdo->prepare("
    SELECT s.id as spon_id, s.shares_count, s.start_date, st.id as student_id, st.alias_name, st.avatar_url, st.talents, st.dreams, st.grade, st.field_of_study 
    FROM sponsorships s 
    JOIN students st ON s.student_id = st.id 
    WHERE s.donor_id = ? AND s.status = 'active'
");
$students_stmt->execute([$donor_id]);
$sponsored_students = $students_stmt->fetchAll();

// Helper to get Farsi digits
function toFarsi($str) {
    $farsi = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    $latin = ['0','1','2','3','4','5','6','7','8','9'];
    return str_replace($latin, $farsi, $str);
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پورتال پشتیبانان بورس | بنیاد حکمت</title>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@100;300;400;500;700;900&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Vazirmatn', 'sans-serif'] },
                    colors: {
                        primary: { 900: '#00141e', 800: '#115e59', 600: '#14b8a6' }
                    }
                }
            }
        }
    </script>
    <style>
        .card-premium {
            background: linear-gradient(135deg, #115e59 0%, #00141e 100%);
        }
        .chat-scroll::-webkit-scrollbar {
            width: 4px;
        }
        .chat-scroll::-webkit-scrollbar-thumb {
            background-color: rgba(0, 0, 0, 0.1);
            border-radius: 4px;
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
<body class="bg-gray-50 text-gray-800 font-sans antialiased overflow-x-hidden"
    x-data="{
        activeSponId: '<?php echo !empty($sponsored_students) ? $sponsored_students[0]['spon_id'] : ''; ?>',
        activeStudentId: '<?php echo !empty($sponsored_students) ? $sponsored_students[0]['student_id'] : ''; ?>'
    }">

    <!-- Navbar -->
    <?php include 'includes/dashboard-nav.php'; ?>

    <!-- Main Content -->
    <main class="container mx-auto px-6 py-12 max-w-6xl">
        
        <?php if ($message_status): ?>
            <div class="mb-8 p-4 rounded-2xl border text-center text-xs font-bold <?php echo $message_type === 'success' ? 'bg-teal-50 border-teal-200 text-teal-700' : 'bg-red-50 border-red-200 text-red-700'; ?>">
                <?php echo htmlspecialchars($message_status); ?>
            </div>
        <?php endif; ?>

        <!-- BOARD SIGNATURE WORKSPACE -->
        <?php if ($is_board_member && !empty($pending_board_lists)): ?>
        <div class="bg-amber-50/50 rounded-[3rem] p-8 border border-amber-100 shadow-sm mb-12 relative overflow-hidden">
            <div class="absolute top-0 left-0 w-full h-1.5 bg-gradient-to-l from-amber-500 to-yellow-500"></div>
            <h3 class="text-lg font-black text-amber-900 mb-2 flex items-center gap-2">
                <span>✒️</span> کارتابل امضای الکترونیک اسناد مالی بورسیه
            </h3>
            <p class="text-xs text-amber-700 font-bold mb-8">لیست پرداخت‌های بورسیه ماهیانه زیر تایید و برای امضای شما ارسال شده است. لطفاً پس از بازبینی، امضا نمایید.</p>
            
            <div class="space-y-6">
                <?php foreach ($pending_board_lists as $p_list): 
                    $list_items = $pending_items_by_list[$p_list['id']] ?? [];
                    $total_net_pay = 0;
                    foreach ($list_items as $itm) {
                        $total_net_pay += $itm['final_amount'];
                    }
                ?>
                <div class="bg-white rounded-3xl p-6 shadow-sm border border-amber-100" x-data="{ showDetails: false }">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                        <div>
                            <h4 class="text-sm font-black text-gray-800">سند پرداخت بورسیه ماه <?php echo $p_list['month'] . ' ' . toFarsi($p_list['year']); ?></h4>
                            <div class="flex items-center gap-4 mt-2 text-[10px] text-gray-400 font-bold">
                                <span>تعداد مددجویان: <?php echo toFarsi((string)count($list_items)); ?> نفر</span>
                                <span class="w-1.5 h-1.5 bg-gray-200 rounded-full"></span>
                                <span>جمع مبلغ خالص: <strong class="text-teal-600 font-black"><?php echo toFarsi(number_format($total_net_pay)); ?> ریال</strong></span>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <button @click="showDetails = !showDetails" class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 rounded-xl font-bold text-[10px] transition-colors">
                                <span x-show="!showDetails">📋 مشاهده جزئیات پرداخت</span>
                                <span x-show="showDetails">✕ بستن جزئیات</span>
                            </button>
                            <form action="donor-dashboard.php" method="POST" onsubmit="return confirm('آیا از صحت لیست اطمینان دارید و آن را به صورت الکترونیک امضا می‌کنید؟')">
                                <input type="hidden" name="action" value="sign_bursary_list">
                                <input type="hidden" name="list_id" value="<?php echo $p_list['id']; ?>">
                                <button type="submit" class="px-6 py-2.5 bg-amber-600 hover:bg-amber-700 text-white font-black text-xs rounded-xl shadow-lg shadow-amber-100 transition-colors">
                                    ✍️ تایید و امضای الکترونیک
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Collapsible details table -->
                    <div x-show="showDetails" x-transition class="mt-6 border-t pt-6" x-cloak>
                        <div class="overflow-x-auto">
                            <table class="w-full text-right text-xs">
                                <thead>
                                    <tr class="text-[9px] text-gray-400 uppercase border-b font-bold pb-2">
                                        <th class="pb-2">نام دانش‌پژوه</th>
                                        <th class="pb-2">شماره حساب</th>
                                        <th class="pb-2">بورسیه پایه (ریال)</th>
                                        <th class="pb-2">کسورات (ریال)</th>
                                        <th class="pb-2">شرح کسورات</th>
                                        <th class="pb-2">خالص دریافتی (ریال)</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($list_items as $itm): 
                                        $deductions = $itm['computer_installment'] + $itm['loan_installment'] + $itm['other_deductions'];
                                    ?>
                                    <tr class="border-b last:border-b-0 py-2">
                                        <td class="py-2.5 font-bold text-gray-800"><?php echo $itm['student_name']; ?></td>
                                        <td class="py-2.5 font-mono text-[10px] text-gray-500"><?php echo $itm['account_number'] ?: '-'; ?></td>
                                        <td class="py-2.5"><?php echo toFarsi(number_format($itm['base_amount'])); ?></td>
                                        <td class="py-2.5 <?php echo $deductions > 0 ? 'text-rose-600 font-bold' : 'text-gray-400'; ?>"><?php echo $deductions > 0 ? toFarsi(number_format($deductions)) : '۰'; ?></td>
                                        <td class="py-2.5 text-gray-400 text-[10px]"><?php echo htmlspecialchars((string)$itm['deductions_desc']) ?: '-'; ?></td>
                                        <td class="py-2.5 font-black text-emerald-600"><?php echo toFarsi(number_format($itm['final_amount'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Donor Profile Overview -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 mb-12">
            
            <!-- Patron Premium Card -->
            <div class="card-premium text-white p-8 rounded-[2.5rem] shadow-xl flex flex-col justify-between relative overflow-hidden">
                <div class="absolute -right-20 -top-20 w-60 h-60 bg-teal-500/10 rounded-full blur-3xl"></div>
                <div>
                    <span class="bg-white/10 text-teal-300 px-3 py-1 rounded-full text-[10px] font-bold border border-white/5 inline-block mb-6">پشتیبان بورس حکمت</span>
                    <h2 class="text-2xl font-black mb-1"><?php echo htmlspecialchars($donor['name'] . ' ' . $donor['surname']); ?></h2>
                    <p class="text-white/60 text-[10px]">تاریخ عضویت: <?php echo toFarsi($donor['join_date'] ?: '---'); ?></p>
                </div>
                <div class="mt-8 border-t border-white/10 pt-6 flex justify-between items-center">
                    <div>
                        <span class="text-[9px] text-white/50 block">مجموع بورس‌های تحت حمایت شما</span>
                        <div class="text-2xl font-black mt-1"><?php echo toFarsi(number_format($total_shares)); ?> <span class="text-xs">سهم</span></div>
                    </div>
                    <div class="bg-white/10 w-12 h-12 rounded-2xl flex items-center justify-center text-xl">💎</div>
                </div>
            </div>

            <!-- Stats Block -->
            <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-gray-100 flex flex-col justify-between">
                <div>
                    <h3 class="text-sm font-bold text-gray-400 mb-6">مجموع مشارکت مالی ثبت شده</h3>
                    <div class="text-3xl font-black text-primary-900" dir="ltr"><?php echo toFarsi(number_format($donor['total_donated'] ?: 0)); ?> <span class="text-xs font-bold text-gray-400">ریال</span></div>
                </div>
                <div class="border-t border-gray-100 pt-4 mt-6 text-xs text-teal-600 font-bold flex items-center gap-2">
                    <span>✓ شامل تمام کمک‌های واریزی و معیشتی</span>
                </div>
            </div>

            <!-- Recent Ledger -->
            <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-gray-100">
                <h3 class="text-sm font-bold text-gray-500 mb-6 flex items-center gap-2">⏱ تراکنش‌های مالی اخیر</h3>
                <div class="space-y-4">
                    <?php if (empty($donations)): ?>
                        <p class="text-xs text-gray-400 font-bold text-center py-8">تراکنشی یافت نشد.</p>
                    <?php else: ?>
                        <?php foreach ($donations as $dn): ?>
                        <div class="flex justify-between items-center bg-gray-50 p-3 rounded-xl">
                            <div>
                                <span class="text-xs font-black text-gray-800"><?php echo toFarsi(number_format($dn['amount'])); ?> ریال</span>
                                <span class="text-[9px] text-gray-400 block mt-1"><?php echo htmlspecialchars($dn['description'] ?: 'کمک نقدی'); ?></span>
                            </div>
                            <span class="text-[9px] bg-white border border-gray-200 text-gray-500 px-2 py-0.5 rounded-lg font-bold"><?php echo toFarsi($dn['date']); ?></span>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Mentorship & Sponsored Students Panel -->
        <h2 class="text-xl font-black text-primary-900 mb-6 flex items-center gap-3">
            <span class="w-2 h-6 bg-teal-600 rounded-full"></span> فرزندان معنوی تحت حمایت شما
        </h2>

        <?php if (empty($sponsored_students)): ?>
            <div class="bg-white p-12 rounded-[3rem] border border-gray-100 shadow-sm text-center text-gray-500">
                <span class="text-4xl block mb-4">🤝</span>
                در حال حاضر دانش‌پژوهی به شما منتسب نشده است. پس از واریز اولین سهم بورس، فرآیند تطبیق دانش‌پژوهان از طرف بنیاد آغاز خواهد شد.
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">
                
                <!-- Students Sidebar selection -->
                <div class="lg:col-span-4 space-y-4">
                    <?php foreach ($sponsored_students as $st): ?>
                    <button @click="activeSponId = '<?php echo $st['spon_id']; ?>'; activeStudentId = '<?php echo $st['student_id']; ?>'"
                        :class="activeSponId == '<?php echo $st['spon_id']; ?>' ? 'border-teal-500 bg-teal-50/20' : 'border-gray-100 hover:border-gray-200'"
                        class="w-full text-right p-5 bg-white border-2 rounded-3xl transition-all flex items-center gap-4">
                        <img src="<?php echo $st['avatar_url'] ?: 'https://api.dicebear.com/7.x/bottts/svg?seed=' . urlencode($st['alias_name']); ?>" alt="آواتار" class="w-10 h-10 rounded-full bg-gray-100 shrink-0">
                        <div class="flex-1">
                            <h4 class="font-black text-sm text-gray-900"><?php echo htmlspecialchars($st['alias_name']); ?></h4>
                            <p class="text-[10px] text-gray-400 mt-1">پایه: <?php echo htmlspecialchars($st['grade']); ?></p>
                        </div>
                        <span class="text-[9px] bg-teal-100 text-teal-700 px-3 py-1 rounded-full font-bold font-black shrink-0"><?php echo toFarsi($st['shares_count']); ?> سهم بورس</span>
                    </button>
                    <?php endforeach; ?>
                </div>

                <!-- Active Student Detail & Mentorship Room -->
                <div class="lg:col-span-8 space-y-8">
                    <?php foreach ($sponsored_students as $st): ?>
                    <div x-show="activeSponId == '<?php echo $st['spon_id']; ?>'" class="space-y-8">
                        
                        <!-- Student Profile Card (Dignity-first) -->
                        <div class="bg-white p-8 rounded-[2.5rem] border border-gray-100 shadow-sm flex flex-col md:flex-row gap-6 items-center">
                            <img src="<?php echo $st['avatar_url'] ?: 'https://api.dicebear.com/7.x/bottts/svg?seed=' . urlencode($st['alias_name']); ?>" alt="آواتار" class="w-24 h-24 rounded-full bg-slate-100 p-1 border-2 border-teal-500/20">
                            <div class="text-center md:text-right space-y-2 flex-1">
                                <h3 class="text-lg font-black text-gray-900"><?php echo htmlspecialchars($st['alias_name']); ?> (مستعار)</h3>
                                <p class="text-xs text-gray-500 leading-relaxed font-bold">پایه تحصیلی: <span class="text-teal-600 font-black"><?php echo htmlspecialchars($st['grade'] . ' - ' . ($st['field_of_study'] ?: 'عمومی')); ?></span></p>
                                <p class="text-xs text-gray-500 leading-relaxed">استعدادها: <span class="text-gray-700 font-bold"><?php echo htmlspecialchars($st['talents'] ?: 'علاقه‌مند به ریاضی و علوم تجربی'); ?></span></p>
                                <p class="text-xs text-gray-500 leading-relaxed">آرزوی تحصیلی/شغلی: <span class="text-gray-700 font-bold"><?php echo htmlspecialchars($st['dreams'] ?: 'نامشخص'); ?></span></p>
                            </div>
                        </div>

                        <!-- Grade Reports & Carname (Anonymous) -->
                        <div class="bg-white p-8 rounded-[2.5rem] border border-gray-100 shadow-sm">
                            <h3 class="text-sm font-bold text-gray-800 mb-4 flex items-center gap-2">📊 پرونده علمی و کارنامه‌ها</h3>
                            <?php
                            $reports_stmt = $pdo->prepare("SELECT * FROM documents WHERE owner_type = 'student' AND owner_id = ? ORDER BY id DESC");
                            $reports_stmt->execute([$st['student_id']]);
                            $reports = $reports_stmt->fetchAll();
                            ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <?php if (empty($reports)): ?>
                                    <p class="text-xs text-gray-400 font-bold col-span-2 py-4 text-center">کارنامه‌ای تاکنون بارگذاری نشده است.</p>
                                <?php else: ?>
                                    <?php foreach ($reports as $rp): ?>
                                    <div class="bg-gray-50 p-4 rounded-2xl flex items-center justify-between border border-gray-100">
                                        <div>
                                            <span class="text-xs font-bold text-gray-800 block"><?php echo htmlspecialchars($rp['description']); ?></span>
                                            <span class="text-[9px] text-gray-400 block mt-1">آپلود: <?php echo toFarsi($rp['upload_date']); ?></span>
                                        </div>
                                        <a href="<?php echo htmlspecialchars($rp['file_path']); ?>" target="_blank" class="bg-white border border-gray-200 text-teal-600 hover:bg-teal-600 hover:text-white px-3 py-1.5 rounded-lg text-[10px] font-bold transition-all">📄 مشاهده سند</a>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Mentorship Chat Room -->
                        <div class="bg-white rounded-[2.5rem] border border-gray-100 shadow-sm overflow-hidden flex flex-col h-[480px]">
                            <div class="bg-gray-50 px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                                <h3 class="text-sm font-black text-gray-800 flex items-center gap-2">🤝 کانال مشاوره‌ای و منتورینگ</h3>
                                <span class="bg-teal-100 text-teal-700 px-3 py-0.5 rounded-full text-[9px] font-bold">تحت نظارت مدیریت</span>
                            </div>

                            <!-- Messages Area -->
                            <div class="flex-1 overflow-y-auto p-6 space-y-4 chat-scroll bg-gray-50/50">
                                <?php
                                // Fetch all approved messages or pending messages sent by this donor
                                $msgs_stmt = $pdo->prepare("
                                    SELECT * FROM sponsorship_messages 
                                    WHERE sponsorship_id = ? AND (status = 'approved' OR sender_type = 'donor') 
                                    ORDER BY id ASC
                                ");
                                $msgs_stmt->execute([$st['spon_id']]);
                                $spon_msgs = $msgs_stmt->fetchAll();
                                ?>
                                <div class="bg-teal-50 border border-teal-100 text-teal-700 text-[10px] p-4 rounded-2xl leading-relaxed max-w-xl mx-auto text-center font-bold">
                                    پشتیبان گرامی؛ پیام‌های راهنمایی، تحصیلی و مشاوره‌ای شما در این بخش رد و بدل می‌شوند. تمام پیام‌ها جهت حفظ حریم خصوصی نوجوانان، ابتدا توسط مددکاران بنیاد بررسی خواهند شد.
                                </div>

                                <?php foreach ($spon_msgs as $msg): ?>
                                    <?php if ($msg['sender_type'] === 'donor'): ?>
                                        <!-- Donor message -->
                                        <div class="flex justify-end items-start gap-3">
                                            <div class="flex flex-col items-end gap-1 max-w-[80%]">
                                                <div class="bg-teal-600 text-white rounded-2xl rounded-tr-none px-4 py-3 text-xs leading-loose">
                                                    <?php echo htmlspecialchars($msg['message_text']); ?>
                                                </div>
                                                <div class="flex items-center gap-2 text-[8px] text-gray-400 font-bold">
                                                    <span><?php echo toFarsi($msg['created_at']); ?></span>
                                                    <?php if ($msg['status'] === 'pending'): ?>
                                                        <span class="text-amber-600 bg-amber-50 px-2 py-0.5 rounded-full">در انتظار تایید مدیریت</span>
                                                    <?php else: ?>
                                                        <span class="text-teal-600 bg-teal-50 px-2 py-0.5 rounded-full">ارسال شده به دانش‌پژوه</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="w-8 h-8 rounded-full bg-teal-500 text-white text-xs flex items-center justify-center font-bold shrink-0">من</div>
                                        </div>
                                    <?php else: ?>
                                        <!-- Student message (approved only) -->
                                        <div class="flex justify-start items-start gap-3">
                                            <img src="<?php echo $st['avatar_url'] ?: 'https://api.dicebear.com/7.x/bottts/svg?seed=' . urlencode($st['alias_name']); ?>" alt="آواتار" class="w-8 h-8 rounded-full bg-slate-100 shrink-0">
                                            <div class="flex flex-col items-start gap-1 max-w-[80%]">
                                                <div class="bg-white text-gray-800 rounded-2xl rounded-tl-none px-4 py-3 text-xs leading-loose border border-gray-100">
                                                    <?php echo htmlspecialchars($msg['message_text']); ?>
                                                </div>
                                                <span class="text-[8px] text-gray-400 font-bold"><?php echo toFarsi($msg['created_at']); ?></span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>

                            <!-- Chat Input Form -->
                            <form method="POST" action="" class="bg-white p-4 border-t border-gray-100 flex gap-3">
                                <input type="hidden" name="action" value="send_message">
                                <input type="hidden" name="sponsorship_id" value="<?php echo $st['spon_id']; ?>">
                                <textarea name="message_text" required placeholder="پیام راهنمایی، مشاوره‌ای یا علمی خود را اینجا بنویسید..." rows="1"
                                    class="flex-1 bg-gray-50 border border-gray-200 text-gray-800 placeholder-gray-400 rounded-xl px-4 py-3 text-xs focus:outline-none focus:border-teal-500 transition-all resize-none"></textarea>
                                <button type="submit" class="bg-teal-600 hover:bg-teal-500 text-white font-bold px-6 rounded-xl text-xs transition-all shadow-md">ارسال پیام</button>
                            </form>
                        </div>

                    </div>
                    <?php endforeach; ?>
                </div>

            </div>
        <?php endif; ?>

    </main>

</body>
</html>
