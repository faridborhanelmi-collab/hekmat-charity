<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Auth Guard - Either donor/benefactor or management supervisor (superadmin / secretary / education_deputy / board_member / admin)
$is_admin_viewer = has_role(['superadmin', 'secretary', 'education_deputy', 'board_member', 'admin']) || (!empty($_SESSION['is_admin']));

if (!$is_admin_viewer && (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'benefactor')) {
    header("Location: login.php");
    exit();
}

$all_donors_list = [];
if ($is_admin_viewer) {
    $all_donors_list = $pdo->query("SELECT id, name, surname, phone, total_donated FROM donors ORDER BY name ASC, surname ASC")->fetchAll(PDO::FETCH_ASSOC);
    
    if (isset($_GET['donor_id']) && (int)$_GET['donor_id'] > 0) {
        $donor_id = (int)$_GET['donor_id'];
    } elseif (isset($_SESSION['view_donor_id']) && (int)$_SESSION['view_donor_id'] > 0) {
        $donor_id = (int)$_SESSION['view_donor_id'];
    } else {
        $first_spon_donor = $pdo->query("SELECT donor_id FROM sponsorships WHERE status = 'active' LIMIT 1")->fetchColumn();
        $donor_id = $first_spon_donor ? (int)$first_spon_donor : (int)($all_donors_list[0]['id'] ?? 1306);
    }
    $_SESSION['view_donor_id'] = $donor_id;
} else {
    $donor_id = (int)($_SESSION['related_id'] ?? $_SESSION['user_id']);
}

// Get Donor Details
$stmt = $pdo->prepare("SELECT * FROM donors WHERE id = ?");
$stmt->execute([$donor_id]);
$donor = $stmt->fetch();

if (!$donor) {
    if ($is_admin_viewer && !empty($all_donors_list)) {
        $donor_id = (int)$all_donors_list[0]['id'];
        $_SESSION['view_donor_id'] = $donor_id;
        $stmt->execute([$donor_id]);
        $donor = $stmt->fetch();
    }
    if (!$donor) {
        session_destroy();
        header("Location: login.php");
        exit();
    }
}

// Helper to sanitize Persian/English numbers
if (!function_exists('cleanFaNumber')) {
    function cleanFaNumber($val) {
        if (empty($val)) return 0;
        $farsi = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹','٬',',',' '];
        $latin = ['0','1','2','3','4','5','6','7','8','9','','',''];
        $cleaned = str_replace($farsi, $latin, (string)$val);
        return (int)preg_replace('/[^0-9]/', '', $cleaned);
    }
}

// Handle message and donation submissions
$message_status = '';
$message_type = '';

$is_board_member = ($donor_id === 1306 || $donor_id === 1310);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // 1. انتخاب فوری فرزند معنوی مستقیماً از داخل داشبورد
    if ($_POST['action'] === 'quick_sponsor') {
        $student_id = (int)($_POST['student_id'] ?? 0);
        if ($student_id > 0) {
            $chk_sp = $pdo->prepare("SELECT id FROM sponsorships WHERE student_id = ? AND status = 'active'");
            $chk_sp->execute([$student_id]);
            if ($chk_sp->fetch()) {
                $message_status = 'این دانش‌پژوه در حال حاضر تحت حمایت قرار گرفته است.';
                $message_type = 'error';
            } else {
                $ins_sp = $pdo->prepare("INSERT INTO sponsorships (donor_id, student_id, shares_count, start_date, status) VALUES (?, ?, 1, date('now'), 'pending')");
                $ins_sp->execute([$donor_id, $student_id]);
                
                $st_name_row = $pdo->query("SELECT alias_name FROM students WHERE id = {$student_id}")->fetch();
                $st_alias_disp = $st_name_row['alias_name'] ?? 'دانش‌پژوه';
                
                log_activity('ثبت مستقیم حمایت از داشبورد', 'sponsorship', $student_id, "ثبت درخواست حمایت از {$st_alias_disp} توسط {$donor['name']} {$donor['surname']} از داشبورد");
                
                $message_status = "درخواست سرپرستی معنوی {$st_alias_disp} با موفقیت ثبت شد! به جمع حامیان پرمهر بنیاد حکمت خوش آمدید.";
                $message_type = 'success';
            }
        }
    }

    // 2. پرداخت آنلاین / ثبت فیش واریزی
    if ($_POST['action'] === 'submit_donation') {
        $raw_amount = cleanFaNumber($_POST['amount'] ?? '0');
        $amount_unit = $_POST['amount_unit'] ?? 'toman';
        $amount_rial = ($amount_unit === 'toman') ? ($raw_amount * 10) : $raw_amount;
        $receipt_no = trim($_POST['receipt_no'] ?? '');
        $description = trim($_POST['description'] ?? 'مشارکت در بورس تحصیلی نخبگان');
        $payment_method = $_POST['payment_method'] ?? 'card_to_card';
        
        if ($amount_rial >= 500000) { // حداقل ۵۰ هزار تومان
            list($jy, $jm, $jd) = gregorian_to_jalali((int)date('Y'), (int)date('m'), (int)date('d'));
            $fa_months = [1=>'فروردین', 2=>'اردیبهشت', 3=>'خرداد', 4=>'تیر', 5=>'مرداد', 6=>'شهریور', 7=>'مهر', 8=>'آبان', 9=>'آذر', 10=>'دی', 11=>'بهمن', 12=>'اسفند'];
            $cur_date_fa = sprintf('%04d/%02d/%02d', $jy, $jm, $jd);
            $cur_month_fa = $fa_months[$jm] ?? 'مهر';
            $cur_year_fa = (string)$jy;
            
            if ($payment_method === 'online_gateway') {
                $ref = 'PARSIAN-' . date('YmdHis') . '-' . rand(1000, 9999);
                $full_desc = $description . ' (درگاه پرداخت آنلاین بانک پارسیان)';
            } else {
                $ref = !empty($receipt_no) ? $receipt_no : ('شتاب-' . date('Ymd-His'));
                $full_desc = $description . ' (واریز کارت‌به‌کارت)';
            }
            
            $ins_d = $pdo->prepare("INSERT INTO donations (donor_id, amount, date, month, year, receipt_no, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $ins_d->execute([$donor_id, $amount_rial, $cur_date_fa, $cur_month_fa, $cur_year_fa, $ref, $full_desc]);
            
            $upd_d = $pdo->prepare("UPDATE donors SET total_donated = total_donated + ? WHERE id = ?");
            $upd_d->execute([$amount_rial, $donor_id]);
            
            // Re-fetch donor details to update balance card immediately
            $stmt_d = $pdo->prepare("SELECT * FROM donors WHERE id = ?");
            $stmt_d->execute([$donor_id]);
            $donor = $stmt_d->fetch();
            
            log_activity('ثبت مشارکت مالی حامی', 'financial', $donor_id, "ثبت مبلغ " . number_format($amount_rial) . " ریال توسط {$donor['name']} {$donor['surname']} (پیگیری: {$ref})");
            
            $toman_disp = toFarsi(number_format($amount_rial / 10));
            $message_status = "مشارکت مالی شما با مبلغ {$toman_disp} تومان با موفقیت ثبت شد و به موجودی پرونده شما اضافه گردید. صمیمانه سپاسگزاریم!";
            $message_type = 'success';
        } else {
            $message_status = 'حداقل مبلغ مشارکت ۵۰,۰۰۰ تومان (۵۰۰,۰۰۰ ریال) می‌باشد.';
            $message_type = 'error';
        }
    }

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
    WHERE donor_id = ? AND status IN ('active', 'pending')
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
    SELECT s.id as spon_id, s.shares_count, s.start_date, s.status as spon_status, st.id as student_id, st.name as st_real_name, st.surname as st_real_surname, st.code as st_code, st.alias_name, st.avatar_url, st.talents, st.dreams, st.grade, st.field_of_study 
    FROM sponsorships s 
    JOIN students st ON s.student_id = st.id 
    WHERE s.donor_id = ? AND s.status IN ('active', 'pending') AND st.status IN ('active', 'university')
    ORDER BY CASE WHEN s.status = 'pending' THEN 0 ELSE 1 END, s.id DESC
");
$students_stmt->execute([$donor_id]);
$sponsored_students = $students_stmt->fetchAll();

// Helper to get Farsi digits
function toFarsi($str) {
    $farsi = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
    $latin = ['0','1','2','3','4','5','6','7','8','9'];
    return str_replace($latin, $farsi, (string)$str);
}

// Helper to determine student gender accurately
function getStudentGender($student) {
    $g = trim($student['gender'] ?? '');
    if (in_array(strtolower($g), ['female', 'دختر', 'زن', 'f'])) return 'دختر';
    if (in_array(strtolower($g), ['male', 'پسر', 'مرد', 'm'])) return 'پسر';
    
    $name = trim($student['name'] ?? '');
    $female_names = [
        'ثنا', 'کوثر', 'حانیه', 'فاطمه', 'شقایق', 'فرزانه', 'فرخنده', 'سحر', 'سارینا', 'ثمین', 
        'زهرا', 'الهام', 'آیدا', 'ستایش', 'نرگس', 'محمودی', 'جواهری', 'مریم', 'اسما', 'بهاره', 'مهسا',
        'سوگند', 'مبینا', 'نازنین', 'یکتا', 'یسنا', 'ریحانه', 'حنانه', 'محدثه', 'نگین', 'ملیکا',
        'هلیا', 'روژان', 'پریسا', 'شیما', 'هستی', 'حدیث', 'مهدیه', 'غزل', 'ندا', 'سمیه', 'طاهره',
        'مطهره', 'معصومه', 'نیایش', 'ویانا', 'نازیلا', 'الینا', 'تسنیم', 'باران', 'شیدا', 'مهناز',
        'بهناز', 'عطیه', 'نیوشا', 'پریناز'
    ];
    $first_name = explode(' ', $name)[0] ?? '';
    if (in_array($first_name, $female_names) || mb_substr($first_name, -1) == 'ه' || mb_substr($first_name, -1) == 'ا') {
        return 'دختر';
    }
    return 'پسر';
}

// Helper to format educational grade and field nicely
function formatGradeLabel($grade, $field = '') {
    $grade = trim((string)$grade);
    $field = trim((string)$field);
    if ($grade === '' || $grade === 'A') {
        $res = 'دبیرستان (متوسطه دوم)';
    } elseif ($grade === '12' || $grade === 'دوازدهم' || strpos($grade, 'دوازدهم') !== false) {
        $res = 'پایه دوازدهم (کنکور)';
    } elseif ($grade === 'یازدهم' || strpos($grade, 'یازدهم') !== false) {
        $res = 'پایه یازدهم';
    } elseif ($grade === 'دهم' || strpos($grade, 'دهم') !== false) {
        $res = 'پایه دهم';
    } elseif ($grade === 'نهم') {
        $res = 'پایه نهم (متوسطه اول)';
    } elseif ($grade === 'هشتم') {
        $res = 'پایه هشتم (متوسطه اول)';
    } elseif (strpos($grade, 'دبستان') !== false) {
        $res = $grade;
    } elseif ($grade === 'دیپلم') {
        $res = 'دیپلم / آموزش عالی';
    } else {
        $res = 'پایه ' . $grade;
    }
    if ($field !== '' && $field !== 'ندارد' && $field !== 'عمومی') {
        $res .= ' • ' . $field;
    }
    return $res;
}

// Fetch all unsponsored students with their latest report card file from documents table
$available_students_stmt = $pdo->query("
    SELECT s.id, s.code, s.name, s.surname, s.gender, s.grade, s.field_of_study, s.talents, s.dreams, s.alias_name,
           d.file_path as report_card_path, d.file_name as report_card_name,
           sp.final_grade
    FROM students s
    LEFT JOIN (
        SELECT owner_id, file_path, file_name, ROW_NUMBER() OVER (PARTITION BY owner_id ORDER BY id DESC) as rn
        FROM documents
        WHERE owner_type = 'student'
    ) d ON s.id = d.owner_id AND d.rn = 1
    LEFT JOIN student_psychology sp ON s.id = sp.student_id
    WHERE s.status IN ('active', 'university') 
      AND s.bursary_eligible = 1
      AND (s.code IS NULL OR s.code != 'GENERAL')
      AND (s.name IS NULL OR s.name != 'بنیاد')
      AND (SELECT count(*) FROM sponsorships spon WHERE spon.student_id = s.id AND spon.status = 'active') = 0
    ORDER BY 
      CASE WHEN d.file_path IS NOT NULL THEN 0 ELSE 1 END,
      s.id ASC
");
$available_students = $available_students_stmt->fetchAll(PDO::FETCH_ASSOC);

// Process student records for fast client-side filtering and preview modal
$processed_students = [];
$total_available_girls = 0;
$total_available_boys = 0;
$total_available_reports = 0;
$total_available_seniors = 0;
$total_available_juniors = 0;

foreach ($available_students as $st) {
    $gender = getStudentGender($st);
    if ($gender === 'دختر') $total_available_girls++; else $total_available_boys++;
    if (!empty($st['report_card_path'])) $total_available_reports++;

    $code_num = !empty($st['code']) && is_numeric($st['code']) ? $st['code'] : $st['id'];
    $formal_alias = 'حکمت‌جوی ' . toFarsi((string)$code_num);
    $grade_disp = formatGradeLabel($st['grade'], $st['field_of_study']);
    
    $grade_cat = 'senior'; // دبیرستان
    if (strpos((string)$st['grade'], 'هشتم') !== false || strpos((string)$st['grade'], 'نهم') !== false) {
        $grade_cat = 'junior'; // متوسطه اول
        $total_available_juniors++;
    } elseif (strpos((string)$st['grade'], 'دبستان') !== false) {
        $grade_cat = 'primary';
    } elseif ($st['grade'] === 'دیپلم') {
        $grade_cat = 'higher';
    } else {
        $total_available_seniors++;
    }
    
    $processed_students[] = [
        'id' => (int)$st['id'],
        'code' => (string)$code_num,
        'code_fa' => toFarsi((string)$code_num),
        'alias' => $formal_alias,
        'gender' => $gender,
        'grade' => $st['grade'] ?: '',
        'field' => $st['field_of_study'] ?: '',
        'grade_disp' => $grade_disp,
        'grade_cat' => $grade_cat,
        'talents' => $st['talents'] ?: 'دارای استعداد درخشان تحصیلی و تعهد بالا به رشد علمی',
        'dreams' => $st['dreams'] ?: 'کسب مدارج عالی علمی، ورود به دانشگاه‌های برتر و خدمت به میهن',
        'has_report' => !empty($st['report_card_path']),
        'report_path' => $st['report_card_path'] ?: '',
        'report_name' => $st['report_card_name'] ?: 'کارنامه تحصیلی تایید شده بنیاد',
        'final_grade' => $st['final_grade'] ?: ''
    ];
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پورتال پشتیبانان بورس | بنیاد حکمت</title>

    <!-- Local Preloaded Vazirmatn Fonts -->
    <link rel="preload" href="/assets/fonts/Vazirmatn-400.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="/assets/fonts/Vazirmatn-700.woff2" as="font" type="font/woff2" crossorigin>

    <style>
    @font-face{font-family:'Vazirmatn';font-style:normal;font-weight:300;font-display:swap;src:url('/assets/fonts/Vazirmatn-300.woff2') format('woff2')}
    @font-face{font-family:'Vazirmatn';font-style:normal;font-weight:400;font-display:swap;src:url('/assets/fonts/Vazirmatn-400.woff2') format('woff2')}
    @font-face{font-family:'Vazirmatn';font-style:normal;font-weight:500;font-display:swap;src:url('/assets/fonts/Vazirmatn-500.woff2') format('woff2')}
    @font-face{font-family:'Vazirmatn';font-style:normal;font-weight:700;font-display:swap;src:url('/assets/fonts/Vazirmatn-700.woff2') format('woff2')}
    @font-face{font-family:'Vazirmatn';font-style:normal;font-weight:900;font-display:swap;src:url('/assets/fonts/Vazirmatn-900.woff2') format('woff2')}

    :root {
        --font-sans: 'Vazirmatn', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    }
    *, html, body, button, input, select, textarea {
        font-family: 'Vazirmatn', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
    }
    .bank-section-grid {
        display: grid !important;
        grid-template-columns: 1fr !important;
        gap: 2rem !important;
        width: 100% !important;
        align-items: stretch !important;
    }
    @media (min-width: 1024px) {
        .bank-section-grid {
            grid-template-columns: 5fr 7fr !important;
        }
    }
    .bank-card-box, .donation-form-box {
        width: 100% !important;
        min-width: 0 !important;
        box-sizing: border-box !important;
    }
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
    [x-cloak] { display: none !important; }
    </style>

    <link rel="stylesheet" href="/assets/tailwind.min.css">
    <script defer src="/assets/alpine.min.js"></script>
    <script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('donorDashboard', () => ({
            activeSponId: '<?php echo !empty($sponsored_students) ? $sponsored_students[0]['spon_id'] : ''; ?>',
            activeStudentId: '<?php echo !empty($sponsored_students) ? $sponsored_students[0]['student_id'] : ''; ?>',
            showDonationModal: false,
            donationAmount: '3000000',
            activeTab: 'gateway',
            students: <?php echo json_encode($processed_students, JSON_UNESCAPED_UNICODE); ?>,
            searchQuery: '',
            activeFilter: 'all',
            displayLimit: 12,
            selectedStudent: null,
            showStudentModal: false,
            get filteredStudents() {
                return this.students.filter(st => {
                    if (this.activeFilter === 'female' && st.gender !== 'دختر') return false;
                    if (this.activeFilter === 'male' && st.gender !== 'پسر') return false;
                    if (this.activeFilter === 'has_report' && !st.has_report) return false;
                    if (this.activeFilter === 'senior' && st.grade_cat !== 'senior') return false;
                    if (this.activeFilter === 'junior' && st.grade_cat !== 'junior') return false;
                    if (this.searchQuery && this.searchQuery.trim() !== '') {
                        const q = this.searchQuery.trim().toLowerCase();
                        const matchCode = st.code.toLowerCase().includes(q) || st.code_fa.includes(q);
                        const matchAlias = st.alias.toLowerCase().includes(q);
                        const matchGrade = st.grade_disp.toLowerCase().includes(q);
                        const matchTalents = (st.talents || '').toLowerCase().includes(q);
                        if (!matchCode && !matchAlias && !matchGrade && !matchTalents) return false;
                    }
                    return true;
                });
            },
            get paginatedStudents() {
                return this.filteredStudents.slice(0, this.displayLimit);
            },
            countByFilter(flt) {
                if (flt === 'female') return this.students.filter(s => s.gender === 'دختر').length;
                if (flt === 'male') return this.students.filter(s => s.gender === 'پسر').length;
                if (flt === 'has_report') return this.students.filter(s => s.has_report).length;
                if (flt === 'senior') return this.students.filter(s => s.grade_cat === 'senior').length;
                if (flt === 'junior') return this.students.filter(s => s.grade_cat === 'junior').length;
                return this.students.length;
            }
        }));
    });
    </script>

    <!-- iOS PWA/Homescreen Setup -->
    <link rel="apple-touch-icon" href="logo.png">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="بنیاد حکمت">
    <link rel="icon" type="image/png" href="logo.png">
    <link rel="manifest" href="manifest.json">
</head>
<body class="bg-gray-50 text-gray-800 font-sans antialiased overflow-x-hidden" x-data="donorDashboard">

    <!-- Admin Manager Supervision Bar -->
    <?php if ($is_admin_viewer): ?>
    <div class="bg-gradient-to-r from-emerald-800 via-teal-900 to-slate-950 text-white px-4 py-3.5 shadow-2xl border-b border-teal-500/30 sticky top-0 z-[110]">
        <div class="container mx-auto flex flex-col md:flex-row justify-between items-center gap-3 text-xs font-bold">
            <div class="flex items-center gap-3">
                <?php
                $viewer_badge = 'حالت نظارت مدیرعامل';
                if (($_SESSION['role'] ?? '') === 'education_deputy') {
                    $viewer_badge = 'نظارت معاونت آموزش (خانم فرتاش)';
                } elseif (($_SESSION['role'] ?? '') === 'board_member') {
                    $viewer_badge = 'نظارت عضو هیئت مدیره (ناظر)';
                } elseif (($_SESSION['role'] ?? '') === 'secretary') {
                    $viewer_badge = 'حالت نظارت منشی بنیاد';
                }
                ?>
                <span class="bg-black/40 text-teal-300 border border-teal-400/40 px-3 py-1 rounded-full text-[11px] font-black tracking-wide flex items-center gap-1.5 shadow-inner">
                    <span>👁️</span> <?php echo $viewer_badge; ?>
                </span>
                <span class="text-white/90">
                    رصد پورتال خیر: <strong class="text-white text-sm underline decoration-teal-400 mr-1"><?php echo htmlspecialchars($donor['name'] . ' ' . $donor['surname']); ?></strong>
                    <span class="text-teal-200 text-[11px] mr-1">(تلفن: <?php echo htmlspecialchars($donor['phone'] ?: '---'); ?>)</span>
                </span>
            </div>
            <div class="flex flex-wrap items-center gap-2.5">
                <label class="text-[11px] text-white/80 shrink-0">تغییر حامی:</label>
                <select onchange="window.location.href='donor-dashboard.php?donor_id=' + this.value" class="bg-black/50 border border-white/25 text-white rounded-xl px-3 py-1.5 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-teal-400 max-w-[220px]">
                    <?php foreach ($all_donors_list as $dn_opt): ?>
                        <option value="<?php echo $dn_opt['id']; ?>" <?php echo $dn_opt['id'] == $donor_id ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($dn_opt['name'] . ' ' . $dn_opt['surname'] . (!empty($dn_opt['phone']) ? ' (' . $dn_opt['phone'] . ')' : '')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <a href="donor-detail.php?id=<?php echo $donor_id; ?>" class="bg-white/15 hover:bg-white/25 text-white border border-white/20 px-3 py-1.5 rounded-xl transition-colors flex items-center gap-1">
                    <span>📄</span> پرونده اداری خیر
                </a>
                <a href="student-dashboard.php" class="bg-indigo-600/80 hover:bg-indigo-700 text-white px-3 py-1.5 rounded-xl transition-colors flex items-center gap-1">
                    <span>🎒</span> پورتال دانش‌آموزان
                </a>
                <a href="admin/index.php" class="bg-rose-500/80 hover:bg-rose-600 text-white px-3 py-1.5 rounded-xl transition-colors flex items-center gap-1">
                    <span>←</span> پنل مدیریت
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

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
                    <div class="bg-white/10 w-12 h-12 rounded-2xl flex items-center justify-center text-teal-300 font-bold text-xs border border-white/10">
                        بورس
                    </div>
                </div>
            </div>

            <!-- Stats Block -->
            <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-gray-100 flex flex-col justify-between">
                <div>
                    <h3 class="text-sm font-bold text-gray-400 mb-4">مجموع مشارکت مالی ثبت شده</h3>
                    <div class="text-3xl font-black text-primary-900" dir="ltr"><?php echo toFarsi(number_format($donor['total_donated'] ?: 0)); ?> <span class="text-xs font-bold text-gray-400">ریال</span></div>
                </div>
                <div class="border-t border-gray-100 pt-4 mt-4 space-y-2">
                    <button @click="showDonationModal = true" class="w-full py-2.5 px-4 bg-teal-50 hover:bg-teal-100 text-teal-800 border border-teal-200 rounded-2xl text-xs font-black transition-all flex items-center justify-center gap-2 shadow-sm">
                        واریز کمک مالی / پرداخت آنلاین
                    </button>
                    <div class="text-[10px] text-teal-600 font-bold flex items-center gap-1.5 justify-center">
                        <span>شامل تمام کمک‌های واریزی و معیشتی</span>
                    </div>
                </div>
            </div>

            <!-- Recent Ledger -->
            <div class="bg-white p-8 rounded-[2.5rem] shadow-sm border border-gray-100">
                <h3 class="text-sm font-bold text-gray-700 mb-6 flex items-center gap-2">تراکنش‌های مالی اخیر</h3>
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
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3 mb-6">
            <h2 class="text-xl font-black text-primary-900 flex items-center gap-3">
                <span class="w-2.5 h-6 bg-teal-600 rounded-full inline-block"></span> فرزندان معنوی تحت حمایت شما
            </h2>
            <div class="inline-flex items-center gap-1.5 px-3 py-1 bg-emerald-50 text-emerald-800 border border-emerald-200 rounded-full text-[11px] font-bold">
                پورتال منطبق بر اصل کرامت نخبگان (حفظ کامل اطلاعات هویتی و محرمانگی)
            </div>
        </div>

        <?php if (empty($sponsored_students)): ?>
            <div class="space-y-8">
                <!-- Warm Welcome & Immediate Action Banner -->
                <div class="bg-gradient-to-br from-teal-900 via-primary-900 to-slate-900 text-white p-8 md:p-10 rounded-[3rem] shadow-2xl relative overflow-hidden border border-teal-500/20">
                    <div class="absolute -right-20 -bottom-20 w-80 h-80 bg-teal-500/10 rounded-full blur-3xl pointer-events-none"></div>
                    <div class="relative z-10 max-w-3xl">
                        <span class="bg-teal-500/20 text-teal-300 border border-teal-500/30 px-4 py-1.5 rounded-full text-xs font-black inline-flex items-center gap-2 mb-4">
                            مسیر تحول‌آفرین و روشن
                        </span>
                        <h3 class="text-2xl md:text-3xl font-black mb-3 leading-snug">
                            امیدهای درخشان فردا، چشم‌انتظار همراهی پرمهر شما هستند
                        </h3>
                        <p class="text-teal-100/90 text-xs md:text-sm leading-relaxed mb-6 font-medium">
                            جناب آقای <?php echo htmlspecialchars($donor['name']); ?> گرامی؛ در حال حاضر فرزندی به پرونده شما منتسب نشده است. شما می‌توانید <strong>همین‌جا با یک کلیک</strong>، سرپرستی آموزشی یکی از نخبگان مستعد زیر را انتخاب کنید تا پرونده او بلافاصله در داشبورد شما فعال شود، یا از طریق درگاه پرداخت آنلاین و شماره کارت بنیاد، در بورس تحصیلی مشارکت فرمایید.
                        </p>
                        <div class="flex flex-wrap items-center gap-3">
                            <a href="#quick-students" class="px-6 py-3.5 bg-gradient-to-r from-teal-500 to-teal-600 hover:from-teal-400 hover:to-teal-500 text-white font-black text-xs rounded-2xl shadow-lg hover:shadow-teal-500/30 transition-all flex items-center gap-2">
                                انتخاب فرزند معنوی (در همین صفحه) ↓
                            </a>
                            <button @click="showDonationModal = true" class="px-6 py-3.5 bg-gradient-to-r from-amber-400 to-amber-500 hover:from-amber-300 hover:to-amber-400 text-amber-950 font-black text-xs rounded-2xl shadow-lg hover:shadow-amber-500/30 transition-all flex items-center gap-2">
                                پرداخت آنلاین یا شماره کارت رسمی بنیاد
                            </button>
                            <?php if ($is_admin_viewer): ?>
                            <a href="admin/sponsorships.php?donor_id=<?php echo $donor_id; ?>" class="px-5 py-3.5 bg-white/10 hover:bg-white/20 text-white font-bold text-xs rounded-2xl border border-white/20 transition-all flex items-center gap-2">
                                پیوند دستی (پنل مدیریت)
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Quick Direct Student Selection Grid -->
                <div id="quick-students" class="bg-white p-6 md:p-10 rounded-[3rem] border border-gray-100 shadow-sm">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6 pb-6 border-b border-gray-100">
                        <div>
                            <h3 class="text-xl font-black text-gray-900 flex items-center gap-2">
                                <span class="w-2.5 h-6 bg-teal-600 rounded-full inline-block"></span>
                                دانش‌آموزان مستعد چشم‌انتظار حامی (انتخاب مستقیم)
                            </h3>
                            <p class="text-xs text-gray-500 mt-1 font-medium">با انتخاب هر دانش‌پژوه، سرپرستی معنوی او بلافاصله به پرونده شما افزوده می‌شود. جهت شفافیت و اطمینان، می‌توانید کارنامه تحصیلی و سوابق هر دانش‌پژوه را مشاهده فرمایید.</p>
                        </div>
                        <a href="campaign.php#students" class="text-xs text-teal-700 hover:text-teal-800 font-bold shrink-0 bg-teal-50 px-3.5 py-2 rounded-xl border border-teal-200 transition-colors">
                            مشاهده تمام پرونده‌ها در پویش حکمت‌یار ←
                        </a>
                    </div>

                    <!-- Search and Filter Bar -->
                    <div class="space-y-4 mb-8">
                        <!-- Live Search Input -->
                        <div class="relative">
                            <input type="text" x-model="searchQuery"
                                placeholder="جستجو بر اساس کد حکمت‌جو (مثلاً ۳۵ یا ۶۱)، رشته یا مقطع تحصیلی..."
                                class="w-full bg-gray-50 border border-gray-200 rounded-2xl px-4 py-3 pr-11 text-xs focus:outline-none focus:border-teal-500 transition-all font-medium">
                            <div class="absolute right-4 top-3.5 text-gray-400">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                            </div>
                            <button type="button" x-show="searchQuery" @click="searchQuery = ''" class="absolute left-4 top-3 text-xs text-gray-400 hover:text-gray-600 font-bold" x-cloak>پاک کردن</button>
                        </div>

                        <!-- Filter Tabs -->
                        <div class="flex items-center gap-2 overflow-x-auto pb-2 scrollbar-none text-xs">
                            <button type="button" @click="activeFilter = 'all'"
                                :class="activeFilter === 'all' ? 'bg-teal-700 text-white font-black shadow-md' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 font-bold'"
                                class="px-4 py-2 rounded-xl transition-all shrink-0">
                                همه حکمت‌جویان (<?php echo toFarsi(count($processed_students)); ?>)
                            </button>
                            <button type="button" @click="activeFilter = 'female'"
                                :class="activeFilter === 'female' ? 'bg-rose-700 text-white font-black shadow-md' : 'bg-rose-50 text-rose-800 hover:bg-rose-100 border border-rose-200 font-bold'"
                                class="px-4 py-2 rounded-xl transition-all shrink-0">
                                دختران (<?php echo toFarsi($total_available_girls); ?>)
                            </button>
                            <button type="button" @click="activeFilter = 'male'"
                                :class="activeFilter === 'male' ? 'bg-teal-900 text-white font-black shadow-md' : 'bg-teal-50 text-teal-800 hover:bg-teal-100 border border-teal-200 font-bold'"
                                class="px-4 py-2 rounded-xl transition-all shrink-0">
                                پسران (<?php echo toFarsi($total_available_boys); ?>)
                            </button>
                            <button type="button" @click="activeFilter = 'has_report'"
                                :class="activeFilter === 'has_report' ? 'bg-emerald-700 text-white font-black shadow-md' : 'bg-emerald-50 text-emerald-800 hover:bg-emerald-100 border border-emerald-200 font-bold'"
                                class="px-4 py-2 rounded-xl transition-all shrink-0">
                                دارای کارنامه رسمی (<?php echo toFarsi($total_available_reports); ?>)
                            </button>
                            <button type="button" @click="activeFilter = 'senior'"
                                :class="activeFilter === 'senior' ? 'bg-gray-800 text-white font-black shadow-md' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 font-bold'"
                                class="px-4 py-2 rounded-xl transition-all shrink-0">
                                متوسطه دوم / دبیرستان (<?php echo toFarsi($total_available_seniors); ?>)
                            </button>
                            <button type="button" @click="activeFilter = 'junior'"
                                :class="activeFilter === 'junior' ? 'bg-gray-800 text-white font-black shadow-md' : 'bg-gray-100 text-gray-600 hover:bg-gray-200 font-bold'"
                                class="px-4 py-2 rounded-xl transition-all shrink-0">
                                متوسطه اول (<?php echo toFarsi($total_available_juniors); ?>)
                            </button>
                        </div>
                    </div>

                    <!-- No Results Message -->
                    <template x-if="filteredStudents.length === 0">
                        <div class="p-12 text-center text-gray-400 font-bold text-xs bg-gray-50 rounded-3xl border border-gray-100">
                            دانش‌پژوهی با مشخصات جستجو شده یافت نشد. لطفاً فیلترها را تغییر دهید.
                        </div>
                    </template>

                    <!-- Student Cards Grid (Dignified - Zero Emojis & Zero Cartoon Avatars) -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <template x-for="st in paginatedStudents" :key="st.id">
                            <div class="bg-gradient-to-b from-gray-50/60 to-white p-6 rounded-3xl border border-gray-200/90 hover:border-teal-600 hover:shadow-xl hover:shadow-teal-600/10 transition-all flex flex-col justify-between group">
                                <div>
                                    <!-- Card Header -->
                                    <div class="flex items-start justify-between gap-3 mb-4">
                                        <div class="flex items-center gap-3">
                                            <!-- Dignified Code Badge (No Cartoon Robots) -->
                                            <div class="w-13 h-13 rounded-2xl flex flex-col items-center justify-center font-black transition-transform group-hover:scale-105 shadow-sm shrink-0 p-2"
                                                 :class="st.gender === 'دختر' ? 'bg-gradient-to-br from-rose-50 to-pink-100 text-rose-900 border border-rose-200' : 'bg-gradient-to-br from-slate-100 to-teal-100 text-teal-950 border border-teal-200'">
                                                <span class="text-[9px] opacity-75 font-normal">کد</span>
                                                <span class="text-sm font-black font-mono -mt-0.5" x-text="st.code_fa"></span>
                                            </div>
                                            <div>
                                                <h4 class="font-black text-sm text-gray-900" x-text="st.alias"></h4>
                                                <div class="flex flex-wrap items-center gap-1.5 mt-1">
                                                    <!-- Gender Badge -->
                                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold"
                                                          :class="st.gender === 'دختر' ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-blue-50 text-blue-700 border border-blue-200'"
                                                          x-text="st.gender"></span>
                                                    <!-- Grade Label -->
                                                    <span class="text-[11px] text-gray-500 font-bold" x-text="st.grade_disp"></span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Report Card Indicator Badge -->
                                        <template x-if="st.has_report">
                                            <span class="px-2 py-0.5 rounded-md text-[9px] font-bold bg-emerald-50 text-emerald-800 border border-emerald-200 shrink-0">
                                                کارنامه تایید شده
                                            </span>
                                        </template>
                                    </div>

                                    <!-- Bursary & Talents Info Box -->
                                    <div class="space-y-2 mb-6 text-xs text-gray-600 bg-white p-4 rounded-2xl border border-gray-100">
                                        <div class="flex items-center justify-between pb-2 border-b border-gray-100">
                                            <span class="text-gray-400 text-[11px]">ارزش بورس ماهانه:</span>
                                            <span class="font-black text-teal-800 font-mono text-[11px]">۳,۰۰۰,۰۰۰ تومان</span>
                                        </div>
                                        <div class="text-[11px] text-gray-600 pt-1 line-clamp-2 leading-relaxed">
                                            <strong class="text-gray-800 font-bold">استعداد و علایق:</strong> <span x-text="st.talents"></span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div class="space-y-2 pt-2 border-t border-gray-100">
                                    <button type="button" @click="selectedStudent = st; showStudentModal = true"
                                        class="w-full bg-white hover:bg-teal-50/60 text-teal-900 border border-teal-200 hover:border-teal-400 py-2.5 px-3 rounded-2xl text-xs font-bold transition-all flex items-center justify-center gap-1.5 shadow-sm">
                                        مشاهده پرونده و کارنامه تحصیلی
                                    </button>
                                    
                                    <form method="POST" action="" :onsubmit="'return confirm(\'آیا مایلید سرپرستی معنوی \' + st.alias + \' را بر عهده بگیرید؟\')'">
                                        <input type="hidden" name="action" value="quick_sponsor">
                                        <input type="hidden" name="student_id" :value="st.id">
                                        <button type="submit" class="w-full bg-gradient-to-r from-teal-700 to-teal-800 hover:from-teal-600 hover:to-teal-700 text-white font-black py-2.5 px-4 rounded-2xl shadow-md transition-all text-xs flex items-center justify-center gap-2 group-hover:shadow-teal-700/20">
                                            انتخاب به عنوان فرزند معنوی
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- Pagination / Show More -->
                    <template x-if="filteredStudents.length > displayLimit">
                        <div class="mt-8 text-center">
                            <button type="button" @click="displayLimit += 12"
                                class="px-8 py-3.5 bg-gray-100 hover:bg-gray-200 text-gray-800 font-bold text-xs rounded-2xl transition-all shadow-sm">
                                نمایش موارد بیشتر (<span x-text="filteredStudents.length - displayLimit"></span> حکمت‌جوی دیگر)...
                            </button>
                        </div>
                    </template>
                </div>

                <!-- Student Dossier & Report Card Modal -->
                <div x-show="showStudentModal" x-cloak class="fixed inset-0 z-[130] flex items-center justify-center p-4 bg-black/75 backdrop-blur-sm"
                    @keydown.escape.window="showStudentModal = false">
                    <div class="bg-white rounded-[2.5rem] shadow-2xl max-w-2xl w-full p-6 md:p-8 relative border border-gray-100 max-h-[92vh] overflow-y-auto"
                        @click.away="showStudentModal = false">
                        
                        <!-- Modal Header -->
                        <div class="flex items-center justify-between pb-4 border-b border-gray-100 mb-6">
                            <div class="flex items-center gap-3">
                                <div class="w-12 h-12 rounded-2xl flex flex-col items-center justify-center font-black shadow-sm p-1"
                                     :class="selectedStudent?.gender === 'دختر' ? 'bg-rose-50 text-rose-800 border border-rose-200' : 'bg-teal-50 text-teal-900 border border-teal-200'">
                                    <span class="text-[9px] opacity-75 font-normal">کد</span>
                                    <span class="text-sm font-black -mt-0.5 font-mono" x-text="selectedStudent?.code_fa"></span>
                                </div>
                                <div>
                                    <h3 class="text-lg font-black text-gray-900" x-text="'پرونده تحصیلی ' + (selectedStudent?.alias || '')"></h3>
                                    <div class="flex items-center gap-2 mt-1">
                                        <span class="px-2.5 py-0.5 rounded-md text-xs font-bold"
                                              :class="selectedStudent?.gender === 'دختر' ? 'bg-rose-50 text-rose-700 border border-rose-200' : 'bg-blue-50 text-blue-700 border border-blue-200'"
                                              x-text="selectedStudent?.gender"></span>
                                        <span class="text-xs text-teal-800 font-bold" x-text="selectedStudent?.grade_disp"></span>
                                    </div>
                                </div>
                            </div>
                            <button type="button" @click="showStudentModal = false" class="w-8 h-8 rounded-full bg-gray-100 text-gray-500 hover:text-gray-800 flex items-center justify-center font-bold text-sm">✕</button>
                        </div>

                        <!-- Modal Body -->
                        <div class="space-y-6">
                            <!-- Highlights Grid -->
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div class="bg-gray-50 p-4 rounded-2xl border border-gray-100">
                                    <span class="text-[11px] text-gray-400 block mb-1">مقطع و وضعیت تحصیلی:</span>
                                    <span class="text-xs font-black text-gray-800" x-text="selectedStudent?.grade_disp"></span>
                                </div>
                                <div class="bg-gray-50 p-4 rounded-2xl border border-gray-100">
                                    <span class="text-[11px] text-gray-400 block mb-1">ارزش بورس آموزشی ماهانه:</span>
                                    <span class="text-xs font-black text-teal-800 font-mono">۳,۰۰۰,۰۰۰ تومان</span>
                                </div>
                            </div>

                            <!-- Talents -->
                            <div class="bg-gray-50 p-4 rounded-2xl border border-gray-100">
                                <span class="text-[11px] text-gray-400 block mb-1.5 font-bold">استعدادها و سوابق علمی:</span>
                                <p class="text-xs text-gray-700 leading-relaxed font-medium" x-text="selectedStudent?.talents"></p>
                            </div>

                            <!-- Dreams -->
                            <div class="bg-gray-50 p-4 rounded-2xl border border-gray-100">
                                <span class="text-[11px] text-gray-400 block mb-1.5 font-bold">آرمان‌ها و اهداف آینده:</span>
                                <p class="text-xs text-gray-700 leading-relaxed font-medium" x-text="selectedStudent?.dreams"></p>
                            </div>

                            <!-- Official Report Card Section -->
                            <div class="p-5 rounded-2xl border" :class="selectedStudent?.has_report ? 'bg-emerald-50/70 border-emerald-200' : 'bg-gray-50 border-gray-200'">
                                <div class="flex items-center justify-between mb-3">
                                    <h4 class="text-xs font-black text-gray-900">سند کارنامه تحصیلی رسمی</h4>
                                    <template x-if="selectedStudent?.has_report">
                                        <span class="text-[10px] bg-emerald-100 text-emerald-800 px-2 py-0.5 rounded font-bold border border-emerald-300">موجود و تایید شده</span>
                                    </template>
                                    <template x-if="!selectedStudent?.has_report">
                                        <span class="text-[10px] bg-gray-200 text-gray-600 px-2 py-0.5 rounded font-bold">در صف دریافت از مدرسه</span>
                                    </template>
                                </div>

                                <template x-if="selectedStudent?.has_report">
                                    <div class="space-y-3">
                                        <p class="text-[11px] text-emerald-950 leading-relaxed">
                                            کارنامه تحصیلی این دانش‌پژوه در دبیرخانه بنیاد ثبت و اعتبار نمرات آن به تایید معاونت آموزش رسیده است.
                                        </p>
                                        <div>
                                            <a :href="selectedStudent?.report_path" target="_blank"
                                               class="inline-flex items-center gap-2 bg-emerald-700 hover:bg-emerald-800 text-white font-black px-4 py-2.5 rounded-xl text-xs shadow-md transition-all">
                                                مشاهده و دریافت فایل کارنامه رسمی ←
                                            </a>
                                        </div>
                                        <!-- Image Preview if JPG/PNG -->
                                        <template x-if="selectedStudent?.report_path && (selectedStudent?.report_path.endsWith('.jpg') || selectedStudent?.report_path.endsWith('.jpeg') || selectedStudent?.report_path.endsWith('.png'))">
                                            <div class="mt-3 bg-white p-2 rounded-xl border border-emerald-200 text-center">
                                                <img :src="selectedStudent?.report_path" alt="پیش‌نمایش کارنامه" class="max-h-72 w-auto mx-auto rounded-lg shadow-sm">
                                            </div>
                                        </template>
                                    </div>
                                </template>

                                <template x-if="!selectedStudent?.has_report">
                                    <p class="text-[11px] text-gray-500 leading-relaxed">
                                        پرونده این دانش‌پژوه اخیراً به سامانه افزوده شده و نسخه رسمی کارنامه توسط کارشناسان آموزشی بنیاد در حال دریافت و بررسی نهایی است.
                                    </p>
                                </template>
                            </div>

                            <!-- Modal Action: Sponsor Button -->
                            <form method="POST" action="" :onsubmit="'return confirm(\'آیا مایلید سرپرستی معنوی \' + (selectedStudent?.alias || '') + \' را بر عهده بگیرید؟\')'">
                                <input type="hidden" name="action" value="quick_sponsor">
                                <input type="hidden" name="student_id" :value="selectedStudent?.id">
                                <button type="submit" class="w-full bg-gradient-to-r from-teal-700 to-teal-800 hover:from-teal-600 hover:to-teal-700 text-white font-black py-4 px-6 rounded-2xl shadow-xl transition-all text-xs flex items-center justify-center gap-2">
                                    تایید و ثبت سرپرستی معنوی این دانش‌پژوه
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

                <!-- Charity Bank Card & Online Donation Section Directly on Page (Full Width Responsive Grid) -->
                <div class="bank-section-grid">
                    <!-- Official Charity Bank Card -->
                    <div class="bank-card-box bg-gradient-to-br from-slate-900 via-primary-900 to-teal-950 text-white p-8 rounded-[3rem] shadow-xl relative overflow-hidden flex flex-col justify-between border border-teal-500/30">
                        <div class="absolute -right-20 -bottom-20 w-60 h-60 bg-teal-500/10 rounded-full blur-2xl pointer-events-none"></div>
                        <div>
                            <div class="flex justify-between items-start mb-6">
                                <div>
                                    <span class="bg-teal-500/20 text-teal-300 border border-teal-500/30 px-3 py-1 rounded-full text-[10px] font-bold inline-block mb-1">کارت رسمی بنیاد</span>
                                    <h4 class="text-base font-black text-white">بنیاد خیریه و نیکوکاری حکمت</h4>
                                    <span class="text-[10px] text-white/60">شناسه ملی / ثبت رسمی: ۷۷۰۷</span>
                                </div>
                                <div class="w-10 h-10 rounded-2xl bg-teal-500/20 text-teal-300 flex items-center justify-center font-bold text-xs border border-teal-400/30">
                                    حکمت
                                </div>
                            </div>

                                <div class="space-y-4 my-6">
                                <div>
                                    <span class="text-[10px] text-white/50 block mb-1">شماره کارت شتاب جهت واریز:</span>
                                    <div class="flex items-center justify-between bg-black/40 p-3 px-4 rounded-2xl border border-white/10">
                                        <span class="font-mono text-base font-bold tracking-widest text-teal-200 dir-ltr" style="direction: ltr;">۶۲۲۱ - ۰۶۱۲ - ۳۹۳۳ - ۶۳۴۲</span>
                                        <button type="button" onclick="copyCardNumber('6221061239336342', this)" class="text-[10px] bg-teal-500/20 hover:bg-teal-500/30 text-teal-300 px-3 py-1.5 rounded-xl border border-teal-400/30 transition-all font-bold">
                                            کپی کارت
                                        </button>
                                    </div>
                                </div>

                                <div class="text-[11px] text-white/70 space-y-1.5 bg-white/5 p-3 rounded-2xl border border-white/5">
                                    <div class="flex justify-between">
                                        <span>بانک:</span>
                                        <strong class="text-white">پارسیان</strong>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>صاحب حساب:</span>
                                        <strong class="text-white">کانون حامی کودکان مستعد و توانمند (بنیاد حکمت)</strong>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>شماره حساب:</span>
                                        <span class="font-mono text-white dir-ltr font-bold">۰۲۱۷۷۴۷۰۰۱۴۳۰۰۸۲۶۰۱</span>
                                    </div>
                                    <div class="flex justify-between">
                                        <span>شماره شبا:</span>
                                        <span class="font-mono text-white dir-ltr font-bold text-[10px]">IR34 0540 2177 4700 1430 0826 01</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="text-[10px] text-teal-300/80 pt-4 border-t border-white/10">
                            تمامی واریزها مستقیماً به حساب رسمی و حقوقی بنیاد نیکوکاری حکمت انجام می‌پذیرد.
                        </div>
                    </div>

                    <!-- Fast Online Donation Form Directly on Dashboard -->
                    <div class="donation-form-box bg-white p-8 rounded-[3rem] border border-gray-100 shadow-sm flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-4 pb-3 border-b border-gray-100">
                                <h3 class="text-base font-black text-gray-900 flex items-center gap-2">
                                    پرداخت آنلاین یا ثبت فیش واریز
                                </h3>
                                <span class="text-[10px] text-teal-700 bg-teal-50 px-2.5 py-1 rounded-full font-bold border border-teal-200">
                                    افزایش آنی موجودی مشارکت
                                </span>
                            </div>

                            <form method="POST" action="" class="space-y-4">
                                <input type="hidden" name="action" value="submit_donation">
                                
                                <div>
                                    <label class="block text-xs font-bold text-gray-700 mb-2">مبلغ مشارکت تحصیلی (تومان):</label>
                                    <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 mb-3">
                                        <button type="button" @click="donationAmount = '3000000'" :class="donationAmount == '3000000' ? 'bg-teal-700 text-white font-black border-teal-700 shadow' : 'bg-gray-50 text-gray-700 border-gray-200'" class="py-2.5 px-2 rounded-xl border text-[11px] font-bold transition-all text-center">
                                            ۳ میلیون (۱ بورس)
                                        </button>
                                        <button type="button" @click="donationAmount = '6000000'" :class="donationAmount == '6000000' ? 'bg-teal-700 text-white font-black border-teal-700 shadow' : 'bg-gray-50 text-gray-700 border-gray-200'" class="py-2.5 px-2 rounded-xl border text-[11px] font-bold transition-all text-center">
                                            ۶ میلیون (۲ بورس)
                                        </button>
                                        <button type="button" @click="donationAmount = '1000000'" :class="donationAmount == '1000000' ? 'bg-teal-700 text-white font-black border-teal-700 shadow' : 'bg-gray-50 text-gray-700 border-gray-200'" class="py-2.5 px-2 rounded-xl border text-[11px] font-bold transition-all text-center">
                                            ۱ میلیون تومان
                                        </button>
                                        <button type="button" @click="donationAmount = '500000'" :class="donationAmount == '500000' ? 'bg-teal-700 text-white font-black border-teal-700 shadow' : 'bg-gray-50 text-gray-700 border-gray-200'" class="py-2.5 px-2 rounded-xl border text-[11px] font-bold transition-all text-center">
                                            ۵۰۰ هزار تومان
                                        </button>
                                    </div>
                                    <div class="relative">
                                        <input type="text" name="amount" x-model="donationAmount" required
                                            class="w-full bg-gray-50 border border-gray-200 rounded-2xl px-4 py-3 text-base font-black text-center text-primary-900 focus:outline-none focus:border-teal-500 font-mono"
                                            placeholder="مبلغ دلخواه به تومان">
                                        <span class="absolute left-4 top-3.5 text-xs text-gray-400 font-bold">تومان</span>
                                    </div>
                                </div>

                                <div class="flex bg-gray-100 p-1 rounded-2xl">
                                    <button type="button" @click="activeTab = 'gateway'" :class="activeTab === 'gateway' ? 'bg-white shadow text-teal-800 font-black' : 'text-gray-500 font-bold'" class="flex-1 py-2 rounded-xl text-xs transition-all">
                                        درگاه پرداخت آنلاین اینترنتی
                                    </button>
                                    <button type="button" @click="activeTab = 'receipt'" :class="activeTab === 'receipt' ? 'bg-white shadow text-teal-800 font-black' : 'text-gray-500 font-bold'" class="flex-1 py-2 rounded-xl text-xs transition-all">
                                        ثبت فیش کارت‌به‌کارت
                                    </button>
                                </div>

                                <div x-show="activeTab === 'gateway'" class="space-y-3">
                                    <input type="hidden" name="payment_method" value="online_gateway">
                                    <button type="submit" class="w-full bg-gradient-to-r from-emerald-600 to-teal-700 hover:from-emerald-500 hover:to-teal-600 text-white font-black py-4 rounded-2xl shadow-xl hover:shadow-teal-700/20 transition-all text-xs flex items-center justify-center gap-2">
                                        اتصال به درگاه پرداخت آنلاین و ثبت آنی
                                    </button>
                                </div>

                                <div x-show="activeTab === 'receipt'" class="space-y-3" x-cloak>
                                    <input type="hidden" name="payment_method" value="card_to_card">
                                    <div>
                                        <label class="block text-[11px] font-bold text-gray-700 mb-1">شماره پیگیری / ارجاع یا ۴ رقم آخر کارت:</label>
                                        <input type="text" name="receipt_no" placeholder="مثلاً: 123456 یا از کارت 9876"
                                            class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-xs focus:outline-none focus:border-teal-500 font-mono">
                                    </div>
                                    <button type="submit" class="w-full bg-primary-900 hover:bg-primary-800 text-white font-black py-4 rounded-2xl shadow-xl transition-all text-xs">
                                        ثبت فیش واریز کارت‌به‌کارت ←
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="sponsored-layout-grid">
                
                <!-- Students Sidebar selection -->
                <div class="space-y-4">
                    <?php foreach ($sponsored_students as $st): 
                        $st_code_clean = !empty($st['st_code']) && is_numeric($st['st_code']) ? $st['st_code'] : $st['student_id'];
                        $st_alias = !empty($st['alias_name']) ? $st['alias_name'] : ('حکمت‌جوی ' . toFarsi($st_code_clean));
                    ?>
                    <button @click="activeSponId = '<?php echo $st['spon_id']; ?>'; activeStudentId = '<?php echo $st['student_id']; ?>'"
                        :class="activeSponId == '<?php echo $st['spon_id']; ?>' ? 'border-teal-500 bg-teal-50/20' : 'border-gray-100 hover:border-gray-200'"
                        class="w-full text-right p-5 bg-white border-2 rounded-3xl transition-all flex items-center gap-4">
                        <div class="w-11 h-11 rounded-2xl bg-teal-50 text-teal-900 border border-teal-200 flex flex-col items-center justify-center font-black shrink-0">
                            <span class="text-[8px] opacity-75 font-normal">کد</span>
                            <span class="text-xs font-black font-mono -mt-0.5"><?php echo toFarsi($st_code_clean); ?></span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="font-black text-sm text-gray-900 truncate"><?php echo htmlspecialchars($st_alias); ?></h4>
                            <p class="text-[10px] text-gray-400 mt-0.5">پایه: <?php echo htmlspecialchars($st['grade'] ?: 'متوسطه'); ?></p>
                            <?php if ($is_admin_viewer): ?>
                            <div class="text-[9px] text-amber-700 bg-amber-50 px-1.5 py-0.5 rounded mt-1 truncate border border-amber-200">
                                نظارت مدیر: <?php echo htmlspecialchars($st['st_real_name'] . ' ' . $st['st_real_surname']); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php if (($st['spon_status'] ?? '') === 'pending'): ?>
                            <span class="text-[9px] bg-amber-100 text-amber-800 px-3 py-1 rounded-full font-bold shrink-0">در انتظار تایید</span>
                        <?php else: ?>
                            <span class="text-[9px] bg-teal-100 text-teal-700 px-3 py-1 rounded-full font-bold font-black shrink-0"><?php echo toFarsi($st['shares_count']); ?> سهم بورس</span>
                        <?php endif; ?>
                    </button>
                    <?php endforeach; ?>
                </div>

                <!-- Active Student Detail & Mentorship Room -->
                <div class="space-y-8">
                    <?php foreach ($sponsored_students as $st): 
                        $st_code_clean = !empty($st['st_code']) && is_numeric($st['st_code']) ? $st['st_code'] : $st['student_id'];
                        $st_alias = !empty($st['alias_name']) ? $st['alias_name'] : ('حکمت‌جوی ' . toFarsi($st_code_clean));
                    ?>
                    <div x-show="activeSponId == '<?php echo $st['spon_id']; ?>'" class="space-y-8">
                        
                        <?php if (($st['spon_status'] ?? '') === 'pending'): ?>
                        <div class="bg-amber-50 border border-amber-200/80 text-amber-900 p-5 rounded-3xl flex items-center gap-4 text-xs font-bold shadow-sm">
                            <div class="w-10 h-10 rounded-2xl bg-amber-200/60 text-amber-950 flex items-center justify-center font-black shrink-0 text-xs">
                                بررسی
                            </div>
                            <div class="leading-relaxed">
                                <span class="font-black block text-amber-950 text-sm mb-0.5">درخواست حمایت شما با موفقیت ثبت شد</span>
                                همکاران ما در واحد آموزش بنیاد حکمت در حال بررسی نهایی پرونده و برقراری ارتباط با دانش‌پژوه هستند. به زودی گزارش‌ها و کارنامه‌ها فعال خواهد شد.
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Student Profile Card (Dignity-first) -->
                        <div class="bg-white p-8 rounded-[2.5rem] border border-gray-100 shadow-sm flex flex-col md:flex-row gap-6 items-center relative overflow-hidden">
                            <div class="w-20 h-20 rounded-2xl bg-teal-50 text-teal-900 border border-teal-200 flex flex-col items-center justify-center font-black shadow-sm shrink-0">
                                <span class="text-[10px] opacity-75 font-normal">کد پرونده</span>
                                <span class="text-xl font-black font-mono mt-0.5"><?php echo toFarsi($st_code_clean); ?></span>
                            </div>
                            <div class="text-center md:text-right space-y-2 flex-1">
                                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                    <h3 class="text-lg font-black text-gray-900"><?php echo htmlspecialchars($st_alias); ?> (شناسه مستعار)</h3>
                                    <?php if ($is_admin_viewer): ?>
                                    <span class="text-[10px] text-amber-800 bg-amber-100 border border-amber-300 px-3 py-1 rounded-full font-bold self-start">
                                        دیدگاه مدیریت: پرونده <?php echo htmlspecialchars($st['st_real_name'] . ' ' . $st['st_real_surname']); ?> (کد <?php echo toFarsi($st_code_clean); ?>)
                                    </span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-xs text-gray-500 leading-relaxed font-bold">پایه تحصیلی: <span class="text-teal-600 font-black"><?php echo htmlspecialchars(($st['grade'] ?: '---') . ' - ' . ($st['field_of_study'] ?: 'عمومی')); ?></span></p>
                                <p class="text-xs text-gray-500 leading-relaxed">استعدادها: <span class="text-gray-700 font-bold"><?php echo htmlspecialchars($st['talents'] ?: 'علاقه‌مند به ریاضی، فناوری و علوم تجربی'); ?></span></p>
                                <p class="text-xs text-gray-500 leading-relaxed">آرزوی تحصیلی/شغلی: <span class="text-gray-700 font-bold"><?php echo htmlspecialchars($st['dreams'] ?: 'کسب تخصص و موفقیت در آموزش عالی'); ?></span></p>
                            </div>
                        </div>

                        <!-- Grade Reports & Carname (Anonymous) -->
                        <div class="bg-white p-8 rounded-[2.5rem] border border-gray-100 shadow-sm">
                            <div class="flex items-center justify-between mb-4 pb-3 border-b border-gray-100">
                                <h3 class="text-sm font-bold text-gray-800 flex items-center gap-2">
                                    کارنامه‌ها و پرونده علمی تحصیلی
                                </h3>
                                <span class="text-[10px] text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-full font-bold border border-emerald-200">
                                    محرمانه و اختصاصی
                                </span>
                            </div>
                            <?php
                            $reports_stmt = $pdo->prepare("SELECT * FROM documents WHERE owner_type = 'student' AND owner_id = ? ORDER BY id DESC");
                            $reports_stmt->execute([$st['student_id']]);
                            $reports = $reports_stmt->fetchAll();
                            ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <?php if (empty($reports)): ?>
                                    <p class="text-xs text-gray-400 font-bold col-span-2 py-6 text-center bg-gray-50 rounded-2xl">کارنامه‌ای تاکنون برای این دانش‌پژوه بارگذاری نشده است.</p>
                                <?php else: ?>
                                    <?php foreach ($reports as $rp): 
                                        $is_pdf = (strpos(strtolower($rp['file_path']), '.pdf') !== false);
                                    ?>
                                    <div class="bg-gray-50 hover:bg-teal-50/40 transition-colors p-4 rounded-2xl flex items-center justify-between border border-gray-100">
                                        <div class="flex items-center gap-3">
                                            <div class="w-8 h-8 rounded-xl bg-teal-50 text-teal-800 border border-teal-200 flex items-center justify-center font-black text-xs font-mono">
                                                <?php echo $is_pdf ? 'PDF' : 'IMG'; ?>
                                            </div>
                                            <div>
                                                <span class="text-xs font-bold text-gray-800 block"><?php echo htmlspecialchars($rp['description'] ?: 'کارنامه تحصیلی'); ?></span>
                                                <span class="text-[9px] text-gray-400 block mt-0.5">ثبت: <?php echo toFarsi($rp['upload_date']); ?></span>
                                            </div>
                                        </div>
                                        <a href="<?php echo htmlspecialchars($rp['file_path']); ?>" target="_blank" class="bg-white border border-gray-200 text-teal-600 hover:bg-teal-600 hover:text-white px-3 py-1.5 rounded-xl text-[10px] font-bold transition-all shadow-sm flex items-center gap-1">
                                            مشاهده سند
                                        </a>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Mentorship Chat Room -->
                        <div class="bg-white rounded-[2.5rem] border border-gray-100 shadow-sm overflow-hidden flex flex-col h-[480px]">
                            <div class="bg-gray-50 px-6 py-4 border-b border-gray-100 flex items-center justify-between">
                                <h3 class="text-sm font-black text-gray-800 flex items-center gap-2">کانال ارتباطی و مشاوره‌ای (منتورینگ)</h3>
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
                                            <div class="w-8 h-8 rounded-full bg-teal-50 text-teal-900 border border-teal-200 flex items-center justify-center text-[10px] font-black shrink-0 font-mono">
                                                <?php echo toFarsi($st_code_clean); ?>
                                            </div>
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

    <!-- Global Donation & Official Charity Card Modal -->
    <div x-show="showDonationModal" x-cloak class="fixed inset-0 z-[120] flex items-center justify-center p-4 bg-black/70 backdrop-blur-sm"
        @keydown.escape.window="showDonationModal = false">
        <div class="bg-white rounded-[2.5rem] shadow-2xl max-w-lg w-full p-6 md:p-8 relative border border-gray-100 max-h-[90vh] overflow-y-auto"
            @click.away="showDonationModal = false">
            
            <div class="flex items-center justify-between pb-4 border-b border-gray-100 mb-6">
                <div>
                    <h3 class="text-base font-black text-gray-900">مشارکت در بورس نخبگان حکمت</h3>
                    <p class="text-[11px] text-gray-400">پرداخت اینترنتی یا واریز کارت‌به‌کارت</p>
                </div>
                <button type="button" @click="showDonationModal = false" class="w-8 h-8 rounded-full bg-gray-100 text-gray-400 hover:text-gray-700 flex items-center justify-center font-bold text-sm">✕</button>
            </div>

            <!-- Official VIP Charity Card inside modal -->
            <div class="bg-gradient-to-br from-slate-900 via-primary-900 to-teal-950 text-white p-6 rounded-3xl shadow-xl relative overflow-hidden mb-6 border border-teal-500/30">
                <div class="flex justify-between items-start mb-6">
                    <div>
                        <span class="text-[10px] text-teal-300 font-bold block">حساب رسمی بنیاد نیکوکاری حکمت (شماره ثبت ۷۷۰۷)</span>
                        <span class="text-xs text-white/70 block mt-0.5">بانک پارسیان - شعبه مرکزی</span>
                    </div>
                    <div class="w-8 h-8 rounded-xl bg-teal-500/20 text-teal-300 flex items-center justify-center font-bold text-xs border border-teal-400/30">
                        حکمت
                    </div>
                </div>

                <!-- Card Number -->
                <div class="mb-4">
                    <span class="text-[10px] text-white/50 block mb-1">شماره کارت شتاب جهت واریز:</span>
                    <div class="flex items-center justify-between bg-black/40 p-2.5 px-4 rounded-xl border border-white/10">
                        <span class="font-mono text-base md:text-lg font-bold tracking-widest text-teal-200 dir-ltr" style="direction: ltr;">۶۲۲۱ - ۰۶۱۲ - ۳۹۳۳ - ۶۳۴۲</span>
                        <button type="button" onclick="copyCardNumber('6221061239336342', this)" class="text-[11px] bg-teal-500/20 hover:bg-teal-500/30 text-teal-300 px-2.5 py-1 rounded-lg border border-teal-400/30 transition-all font-bold">
                            کپی کارت
                        </button>
                    </div>
                </div>

                <!-- IBAN / Sheba -->
                <div class="space-y-1 text-[10px] text-white/70 border-t border-white/10 pt-3">
                    <div class="flex justify-between">
                        <span>شماره شبا:</span>
                        <span class="font-mono text-white dir-ltr font-bold text-[9px]">IR34 0540 2177 4700 1430 0826 01</span>
                    </div>
                    <div class="flex justify-between">
                        <span>شماره حساب:</span>
                        <span class="font-mono text-white dir-ltr font-bold text-[9px]">۰۲۱۷۷۴۷۰۰۱۴۳۰۰۸۲۶۰۱</span>
                    </div>
                    <div class="flex justify-between">
                        <span>به نام:</span>
                        <strong class="text-white">کانون حامی کودکان مستعد و توانمند (بنیاد حکمت)</strong>
                    </div>
                </div>
            </div>

            <!-- Donation Form -->
            <form method="POST" action="" class="space-y-4">
                <input type="hidden" name="action" value="submit_donation">
                
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-2">مبلغ مشارکت (تومان):</label>
                    <div class="grid grid-cols-2 gap-2 mb-3">
                        <button type="button" @click="donationAmount = '3000000'" :class="donationAmount == '3000000' ? 'bg-teal-700 text-white font-black border-teal-700 shadow' : 'bg-gray-50 text-gray-700 border-gray-200'" class="py-2.5 px-3 rounded-xl border text-xs font-bold transition-all text-center">
                            ۳,۰۰۰,۰۰۰ (بورس ۱ ماه)
                        </button>
                        <button type="button" @click="donationAmount = '6000000'" :class="donationAmount == '6000000' ? 'bg-teal-700 text-white font-black border-teal-700 shadow' : 'bg-gray-50 text-gray-700 border-gray-200'" class="py-2.5 px-3 rounded-xl border text-xs font-bold transition-all text-center">
                            ۶,۰۰۰,۰۰۰ (۲ دانش‌آموز)
                        </button>
                        <button type="button" @click="donationAmount = '1000000'" :class="donationAmount == '1000000' ? 'bg-teal-700 text-white font-black border-teal-700 shadow' : 'bg-gray-50 text-gray-700 border-gray-200'" class="py-2.5 px-3 rounded-xl border text-xs font-bold transition-all text-center">
                            ۱,۰۰۰,۰۰۰ (حمایت آزاد)
                        </button>
                        <button type="button" @click="donationAmount = '500000'" :class="donationAmount == '500000' ? 'bg-teal-700 text-white font-black border-teal-700 shadow' : 'bg-gray-50 text-gray-700 border-gray-200'" class="py-2.5 px-3 rounded-xl border text-xs font-bold transition-all text-center">
                            ۵۰۰,۰۰۰ (کتاب و نوشت‌افزار)
                        </button>
                    </div>
                    <div class="relative">
                        <input type="text" name="amount" x-model="donationAmount" required
                            class="w-full bg-gray-50 border border-gray-200 rounded-2xl px-4 py-3 text-base font-black text-center text-primary-900 focus:outline-none focus:border-teal-500 font-mono"
                            placeholder="مبلغ دلخواه به تومان">
                        <span class="absolute left-4 top-3.5 text-xs text-gray-400 font-bold">تومان</span>
                    </div>
                </div>

                <!-- Payment Mode Selector -->
                <div class="flex bg-gray-100 p-1 rounded-2xl">
                    <button type="button" @click="activeTab = 'gateway'" :class="activeTab === 'gateway' ? 'bg-white shadow text-teal-800 font-black' : 'text-gray-500 font-bold'" class="flex-1 py-2 rounded-xl text-xs transition-all">
                        پرداخت آنلاین اینترنتی
                    </button>
                    <button type="button" @click="activeTab = 'receipt'" :class="activeTab === 'receipt' ? 'bg-white shadow text-teal-800 font-black' : 'text-gray-500 font-bold'" class="flex-1 py-2 rounded-xl text-xs transition-all">
                        ثبت فیش کارت‌به‌کارت
                    </button>
                </div>

                <div x-show="activeTab === 'gateway'" class="space-y-3">
                    <input type="hidden" name="payment_method" value="online_gateway">
                    <button type="submit" class="w-full bg-gradient-to-r from-emerald-600 to-teal-700 hover:from-emerald-500 hover:to-teal-600 text-white font-black py-4 rounded-2xl shadow-xl hover:shadow-teal-700/20 transition-all text-xs flex items-center justify-center gap-2">
                        اتصال به درگاه پرداخت آنلاین و ثبت آنی
                    </button>
                </div>

                <div x-show="activeTab === 'receipt'" class="space-y-3" x-cloak>
                    <input type="hidden" name="payment_method" value="card_to_card">
                    <div>
                        <label class="block text-[11px] font-bold text-gray-700 mb-1">شماره پیگیری یا ۴ رقم آخر کارت:</label>
                        <input type="text" name="receipt_no" placeholder="مثلاً: 123456 یا از کارت 9876"
                            class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-2.5 text-xs focus:outline-none focus:border-teal-500 font-mono">
                    </div>
                    <button type="submit" class="w-full bg-primary-900 hover:bg-primary-800 text-white font-black py-4 rounded-2xl shadow-xl transition-all text-xs">
                        ثبت فیش واریز کارت‌به‌کارت ←
                    </button>
                </div>
            </form>
        </div>
    </div>

<script>
function copyCardNumber(number, btn) {
    if (navigator.clipboard) {
        navigator.clipboard.writeText(number).then(() => {
            const originalText = btn.innerHTML;
            btn.innerHTML = 'کپی شد! ✓';
            btn.classList.add('bg-emerald-500', 'text-white');
            setTimeout(() => {
                btn.innerHTML = originalText;
                btn.classList.remove('bg-emerald-500', 'text-white');
            }, 2500);
        });
    } else {
        const tempInput = document.createElement('input');
        tempInput.value = number;
        document.body.appendChild(tempInput);
        tempInput.select();
        document.execCommand('copy');
        document.body.removeChild(tempInput);
        btn.innerHTML = 'کپی شد! ✓';
    }
}
</script>
</body>
</html>
