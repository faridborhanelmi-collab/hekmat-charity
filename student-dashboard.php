<?php
session_start();
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Auth Guard: Either student or management supervisor (superadmin / secretary / education_deputy / board_member / admin) or Viana (data_operator)
$is_management = has_role(['superadmin', 'secretary', 'education_deputy', 'board_member', 'admin']);
$is_admin_viewer = $is_management;
$is_operator_viana = is_viana() || has_role('data_operator') || ((int)($_SESSION['user_id'] ?? 0) === 745) || ((int)($_SESSION['related_id'] ?? 0) === 745) || (strtolower($_SESSION['username'] ?? '') === 'viana');

if (!$is_admin_viewer && !$is_operator_viana && (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student')) {
    header("Location: login.php");
    exit();
}

if ($is_admin_viewer) {
    $student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : (isset($_SESSION['view_student_id']) ? (int)$_SESSION['view_student_id'] : 0);
    if ($student_id <= 0) {
        $first_st = $pdo->query("SELECT id FROM students WHERE status = 'active' ORDER BY id ASC LIMIT 1")->fetch();
        $student_id = $first_st ? (int)$first_st['id'] : 767;
    }
    $_SESSION['view_student_id'] = $student_id;
    $all_students_list = $pdo->query("SELECT id, name, surname, grade, national_id, code, status, bursary_eligible FROM students ORDER BY CASE WHEN status = 'active' THEN 1 WHEN status = 'university' THEN 2 WHEN status = 'graduated' THEN 3 ELSE 4 END, name ASC, surname ASC")->fetchAll(PDO::FETCH_ASSOC);
} elseif ($is_operator_viana) {
    $student_id = 745;
    $all_students_list = [];
} else {
    $student_id = (int)($_SESSION['related_id'] ?? $_SESSION['user_id']);
    $all_students_list = [];
}

// Get Student Data
$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$student_id]);
$student = $stmt->fetch();

if (!$student) {
    if ($is_admin_viewer) {
        die("دانش‌آموز با شناسه {$student_id} یافت نشد. لطفاً از پنل مدیریت شناسه معتبر انتخاب کنید.");
    } else {
        session_destroy();
        header("Location: login.php");
        exit();
    }
}

// Set default alias and avatar if empty
$alias_name = $student['alias_name'] ?: 'دانش‌پژوه #' . $student['id'];
$avatar_url = $student['avatar_url'] ?: 'https://api.dicebear.com/7.x/bottts/svg?seed=' . urlencode($alias_name);

// Status and Bursary Evaluation
$student_status = $student['status'] ?: 'active';
$bursary_eligible = (int)($student['bursary_eligible'] ?? 0);
$is_bursary_active = in_array($student_status, ['active', 'university']) && ($bursary_eligible === 1);

// Configure dynamic display labels
$st_status_labels = [
    'active' => '🟢 تحت پوشش',
    'university' => '🔵 دانشجو',
    'graduated' => '🟣 فارغ‌التحصیل',
    'exited' => '🔴 خروج از بورس'
];

if ($student_status === 'exited') {
    $status_badge_class = 'bg-rose-500/20 text-rose-300 border border-rose-500/40';
    $status_badge_text = '🔴 خروج از بورس (غیرفعال)';
    $bursary_card_title = 'وضعیت بورس تحصیلی';
    $bursary_card_val = 'غیرفعال (خروج از بورس)';
    $bursary_card_color = 'text-rose-400';
    $bursary_card_icon = '❌';
    $mentor_card_val = 'غیرفعال (پرونده مختومه)';
    $hero_desc = 'این پرونده تحصیلی در حال حاضر در وضعیت «خروج از بورس بنیاد حکمت» قرار دارد و پرداخت هرگونه بورسیه یا خدمات آموزشی فعال برای آن متوقف گردیده است.';
} elseif ($student_status === 'graduated') {
    $status_badge_class = 'bg-purple-500/20 text-purple-300 border border-purple-500/40';
    $status_badge_text = '🎓 فارغ‌التحصیل آکادمی حکمت';
    $bursary_card_title = 'وضعیت تحصیلی';
    $bursary_card_val = 'فارغ‌التحصیل (تکمیل دوره)';
    $bursary_card_color = 'text-purple-400';
    $bursary_card_icon = '🎓';
    $mentor_card_val = 'مجمع فارغ‌التحصیلان';
    $hero_desc = 'تبریک! دوره تحصیلی تحت حمایت بنیاد حکمت را با موفقیت سپری کرده‌اید و به عنوان عضو افتخاری مجمع فارغ‌التحصیلان همراه ما هستید.';
} elseif ($student_status === 'university') {
    $status_badge_class = 'bg-blue-500/20 text-blue-300 border border-blue-500/40';
    $status_badge_text = '🏛️ دانشجوی تحت پوشش (آموزش عالی)';
    $bursary_card_title = 'وضعیت بورس تحصیلی';
    $bursary_card_val = $is_bursary_active ? 'فعال (بورس آموزش عالی)' : 'آموزش عالی (غیرمشمول نقدی)';
    $bursary_card_color = 'text-blue-400';
    $bursary_card_icon = '🏛️';
    $mentor_card_val = 'منتور آموزش عالی';
    $hero_desc = 'خوش آمدید! پرونده شما در آکادمی حکمت به عنوان دانشجوی تحصیلات عالی ثبت است و از برنامه‌های هدایت تخصصی بنیاد بهره‌مند هستید.';
} else { // active
    if ($is_bursary_active) {
        $status_badge_class = 'bg-teal-500/15 text-teal-300 border border-teal-500/30';
        $status_badge_text = '🏆 دانش‌پژوه بورس نخبگان حکمت';
        $bursary_card_title = 'وضعیت بورس تحصیلی';
        $bursary_card_val = 'فعال (بورس تحصیلی رشد)';
        $bursary_card_color = 'text-emerald-400';
        $bursary_card_icon = '🏆';
        $mentor_card_val = 'منتور رشد علمی';
        $hero_desc = 'خوش آمدید! در آکادمی حکمت شما با تکیه بر استعداد و تلاش خود بورس علمی دریافت کرده‌اید. برای افزایش رشد علمی، حتماً تکالیف، کارنامه‌ها و گزارش‌های درسی خود را مرتب ارسال کنید.';
    } else {
        $status_badge_class = 'bg-amber-500/20 text-amber-300 border border-amber-500/40';
        $status_badge_text = '📚 تحت پوشش خدمات آموزشی و مشاوره';
        $bursary_card_title = 'وضعیت بورس تحصیلی';
        $bursary_card_val = 'غیرمشمول بورس نقدی (خدمات آموزشی)';
        $bursary_card_color = 'text-amber-400';
        $bursary_card_icon = '📚';
        $mentor_card_val = 'مشاور آموزشی';
        $hero_desc = 'خوش آمدید! شما تحت پوشش خدمات مشاوره‌ای، هدایت تحصیلی و ملزومات درسی بنیاد حکمت قرار دارید.';
    }
}

// Handle POST actions
$message_status = '';
$message_type = ''; // 'success' or 'error'

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'upload_report') {
        $desc = trim($_POST['description'] ?? 'کارنامه تحصیلی');
        if (isset($_FILES['report_file']) && $_FILES['report_file']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['report_file']['tmp_name'];
            $orig_name = $_FILES['report_file']['name'];
            $ext = strtolower(pathinfo($orig_name, PATHINFO_EXTENSION));
            
            // Validate extension
            $allowed = ['pdf', 'png', 'jpg', 'jpeg'];
            if (!in_array($ext, $allowed)) {
                $message_status = 'فرمت فایل مجاز نیست. فقط فایل‌های PDF, PNG, JPG مجاز هستند.';
                $message_type = 'error';
            } else {
                $new_name = 'report_' . $student_id . '_' . time() . '.' . $ext;
                $dest = 'uploads/' . $new_name;
                
                if (!is_dir('uploads')) {
                    mkdir('uploads', 0777, true);
                }
                
                if (move_uploaded_file($file_tmp, $dest)) {
                    $ins = $pdo->prepare("INSERT INTO documents (owner_type, owner_id, file_path, file_name, upload_date, description) VALUES ('student', ?, ?, ?, ?, ?)");
                    $ins->execute([
                        $student_id,
                        $dest,
                        $orig_name,
                        date('Y/m/d'),
                        $desc
                    ]);
                    $message_status = 'کارنامه شما با موفقیت آپلود شد و در اختیار همکاران آموزش قرار گرفت.';
                    $message_type = 'success';
                    log_activity('آپلود کارنامه تحصیلی', 'student', $student_id, "بارگذاری فایل {$orig_name} ({$desc}) توسط دانش‌آموز");
                } else {
                    $message_status = 'خطا در ذخیره‌سازی کارنامه روی سرور رخ داد.';
                    $message_type = 'error';
                }
            }
        } else {
            $message_status = 'خطا در بارگذاری فایل. لطفاً مجدداً تلاش کنید.';
            $message_type = 'error';
        }
    } elseif ($_POST['action'] === 'send_message') {
        $msg_text = trim($_POST['message_text'] ?? '');
        $sponsorship_id = (int)($_POST['sponsorship_id'] ?? 0);
        
        if ($msg_text !== '' && $sponsorship_id > 0) {
            $ins = $pdo->prepare("INSERT INTO sponsorship_messages (sponsorship_id, sender_type, message_text, status, created_at) VALUES (?, 'student', ?, 'pending', ?)");
            $ins->execute([
                $sponsorship_id,
                $msg_text,
                date('Y/m/d H:i')
            ]);
            $message_status = 'گزارش رشد و پیام شما ثبت شد. پس از تایید مدیریت برای منتور ارسال خواهد شد.';
            $message_type = 'success';
            log_activity('ارسال گزارش به منتور', 'mentorship', $sponsorship_id, "ثبت گزارش رشد و پیام جدید به منتور توسط دانش‌آموز");
        } else {
            $message_status = 'متن گزارش رشد نمی‌تواند خالی باشد.';
            $message_type = 'error';
        }
    } elseif ($_POST['action'] === 'request_book') {
        $book_title = trim($_POST['book_title'] ?? '');
        $subject_area = trim($_POST['subject_area'] ?? '');
        $publisher = trim($_POST['publisher'] ?? '');
        $priority = in_array($_POST['priority'] ?? '', ['normal', 'urgent']) ? $_POST['priority'] : 'normal';
        $notes = trim($_POST['notes'] ?? '');
        
        if (!empty($book_title)) {
            $ins = $pdo->prepare("INSERT INTO student_book_requests (student_id, book_title, subject_area, publisher, priority, notes) VALUES (?, ?, ?, ?, ?, ?)");
            $ins->execute([$student_id, $book_title, $subject_area, $publisher, $priority, $notes]);
            $message_status = 'درخواست کتاب با موفقیت ثبت شد و برای معاونت محترم آموزش (خانم فرتاش) و مدیریت ارسال گردید.';
            $message_type = 'success';
            log_activity('درخواست کتاب درسی', 'student_book_requests', $student_id, "ثبت درخواست کتاب {$book_title} ({$publisher}) با اولویت {$priority}");
        } else {
            $message_status = 'لطفاً عنوان کتاب را وارد فرمایید.';
            $message_type = 'error';
        }
    } elseif ($_POST['action'] === 'request_library_book') {
        $book_id = (int)($_POST['book_id'] ?? 0);
        $check = $pdo->prepare("SELECT * FROM library_books WHERE id = ? AND status = 'available'");
        $check->execute([$book_id]);
        $bk = $check->fetch();
        
        if ($bk) {
            $today_ts = time();
            $j = gregorian_to_jalali((int)date('Y', $today_ts), (int)date('m', $today_ts), (int)date('d', $today_ts));
            $today_fa = sprintf('%04d/%02d/%02d', $j[0], $j[1], $j[2]);
            
            // Mark book requested by this student
            $upd = $pdo->prepare("UPDATE library_books SET status = 'requested', current_borrower_id = ? WHERE id = ?");
            $upd->execute([$student_id, $book_id]);
            
            // Add borrow log
            $lstmt = $pdo->prepare("INSERT INTO book_borrow_logs (book_id, student_id, action, action_date, notes) VALUES (?, ?, 'request', ?, ?)");
            $lstmt->execute([$book_id, $student_id, $today_fa, 'درخواست امانت کتاب فیزیکی از پورتال دانش‌آموز']);
            
            // Also add to student_book_requests so it is tracked in the main requests feed
            $ins_req = $pdo->prepare("INSERT INTO student_book_requests (student_id, book_title, subject_area, publisher, priority, notes, status) VALUES (?, ?, ?, ?, 'normal', ?, 'pending')");
            $ins_req->execute([$student_id, $bk['title'], $bk['category'], $bk['publisher'], "درخواست امانت کتاب موجود در مخزن (کد: {$bk['book_code']})"]);
            
            log_activity('درخواست امانت کتاب از مخزن', 'library_books', $book_id, "ثبت درخواست امانت کتاب {$bk['title']} ({$bk['book_code']}) توسط دانش‌آموز {$student['name']} {$student['surname']}");
            
            $message_status = "درخواست امانت کتاب «{$bk['title']}» با موفقیت ثبت شد و برای بررسی و تحویل به سرکار خانم فرتاش و خانم عباسی ارسال گردید.";
            $message_type = 'success';
        } else {
            $message_status = 'متاسفانه این کتاب در حال حاضر موجود نبوده یا توسط دانش‌آموز دیگری رزرو شده است.';
            $message_type = 'error';
        }
    } elseif ($_POST['action'] === 'join_book_waitlist') {
        $book_id = (int)($_POST['book_id'] ?? 0);
        $check = $pdo->prepare("SELECT * FROM library_books WHERE id = ?");
        $check->execute([$book_id]);
        $bk = $check->fetch();
        
        if ($bk) {
            if ($bk['status'] === 'available') {
                $message_status = 'این کتاب هم‌اکنون در مخزن بنیاد موجود است! می‌توانید مستقیماً دکمه «درخواست امانت این کتاب» را بزنید.';
                $message_type = 'info';
            } elseif ($bk['current_borrower_id'] == $student_id) {
                $message_status = 'این کتاب در حال حاضر در دست امانت یا در نوبت تحویل به خود شماست.';
                $message_type = 'info';
            } else {
                // بررسی عدم ثبت قبلی در صف انتظار
                $qcheck = $pdo->prepare("SELECT * FROM book_waitlist WHERE book_id = ? AND student_id = ? AND status = 'waiting'");
                $qcheck->execute([$book_id, $student_id]);
                if ($qcheck->fetch()) {
                    $message_status = 'شما پیش از این در صف انتظار کتاب «' . $bk['title'] . '» ثبت‌نام کرده‌اید و به محض عودت کتاب، پیامک دریافت خواهید کرد.';
                    $message_type = 'info';
                } else {
                    $ins_w = $pdo->prepare("INSERT INTO book_waitlist (book_id, student_id, status) VALUES (?, ?, 'waiting')");
                    $ins_w->execute([$book_id, $student_id]);
                    
                    // محاسبه جایگاه در صف انتظار
                    $pos_stmt = $pdo->prepare("SELECT COUNT(*) FROM book_waitlist WHERE book_id = ? AND status = 'waiting'");
                    $pos_stmt->execute([$book_id]);
                    $queue_pos = $pos_stmt->fetchColumn() ?: 1;
                    
                    $today_ts = time();
                    $j = gregorian_to_jalali((int)date('Y', $today_ts), (int)date('m', $today_ts), (int)date('d', $today_ts));
                    $today_fa = sprintf('%04d/%02d/%02d', $j[0], $j[1], $j[2]);
                    
                    $lstmt = $pdo->prepare("INSERT INTO book_borrow_logs (book_id, student_id, action, action_date, notes) VALUES (?, ?, 'join_waitlist', ?, ?)");
                    $lstmt->execute([$book_id, $student_id, $today_fa, "قرارگیری در نوبت (نفر {$queue_pos} در صف انتظار)"]);
                    
                    log_activity('رزرو نوبت کتاب', 'library_books', $book_id, "ثبت نوبت در صف انتظار کتاب {$bk['title']} ({$bk['book_code']}) توسط دانش‌آموز {$student['name']} {$student['surname']}");
                    
                    $message_status = "نوبت شما برای کتاب «{$bk['title']}» با موفقیت ثبت گردید (نفر " . toFarsiDigits($queue_pos) . " در صف انتظار). به محض بازگشت این کتاب به کتابخانه، پیامک اطلاع‌رسانی برای شما ارسال خواهد شد.";
                    $message_type = 'success';
                }
            }
        } else {
            $message_status = 'کتاب مورد نظر در مخزن یافت نشد.';
            $message_type = 'error';
        }
    } elseif ($_POST['action'] === 'leave_book_waitlist') {
        $book_id = (int)($_POST['book_id'] ?? 0);
        $waitlist_id = (int)($_POST['waitlist_id'] ?? 0);
        
        $stmt = $pdo->prepare("UPDATE book_waitlist SET status = 'cancelled' WHERE (id = ? OR (book_id = ? AND student_id = ?)) AND status = 'waiting'");
        $stmt->execute([$waitlist_id, $book_id, $student_id]);
        
        $message_status = 'نوبت شما در صف انتظار این کتاب با موفقیت لغو گردید.';
        $message_type = 'info';
        log_activity('انصراف از صف انتظار کتاب', 'library_books', $book_id, "انصراف از نوبت امانت توسط دانش‌آموز {$student['name']} {$student['surname']}");
    } elseif ($_POST['action'] === 'cancel_library_request') {
        $book_id = (int)($_POST['book_id'] ?? 0);
        $chk = $pdo->prepare("SELECT * FROM library_books WHERE id = ? AND current_borrower_id = ? AND status = 'requested'");
        $chk->execute([$book_id, $student_id]);
        $bk = $chk->fetch();
        if ($bk) {
            $upd = $pdo->prepare("UPDATE library_books SET status = 'available', current_borrower_id = NULL WHERE id = ?");
            $upd->execute([$book_id]);
            
            $today_ts = time();
            $j = gregorian_to_jalali((int)date('Y', $today_ts), (int)date('m', $today_ts), (int)date('d', $today_ts));
            $today_fa = sprintf('%04d/%02d/%02d', $j[0], $j[1], $j[2]);
            
            $lstmt = $pdo->prepare("INSERT INTO book_borrow_logs (book_id, student_id, action, action_date, notes) VALUES (?, ?, 'cancel_request', ?, ?)");
            $lstmt->execute([$book_id, $student_id, $today_fa, 'لغو درخواست امانت توسط دانش‌آموز']);
            
            // بررسی و اطلاع‌رسانی به نفر بعدی در صف انتظار
            $w_stmt = $pdo->prepare("
                SELECT w.*, s.name as s_name, s.surname as s_surname, s.phone as s_phone, s.guardian_phone as s_gphone 
                FROM book_waitlist w 
                JOIN students s ON w.student_id = s.id 
                WHERE w.book_id = ? AND w.status = 'waiting' 
                ORDER BY w.id ASC LIMIT 1
            ");
            $w_stmt->execute([$book_id]);
            $next_waiter = $w_stmt->fetch(PDO::FETCH_ASSOC);
            if ($next_waiter) {
                require_once __DIR__ . '/includes/SmsService.php';
                $sms_svc = new SmsService($pdo);
                $w_phone = !empty($next_waiter['s_phone']) ? $next_waiter['s_phone'] : $next_waiter['s_gphone'];
                if (!empty($w_phone)) {
                    $sms_text = "دانش‌آموز گرامی {$next_waiter['s_name']} عزیز، کتاب «{$bk['title']}» ({$bk['book_code']}) که در صف انتظار آن بودید در کتابخانه بنیاد حکمت آماده امانت شد. جهت ثبت درخواست امانت به پنل خود مراجعه فرمایید: hekmatfoundation.org";
                    $sms_svc->sendRawSms($w_phone, $sms_text, null, null);
                    $pdo->prepare("UPDATE book_waitlist SET status = 'notified', notified_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$next_waiter['id']]);
                    $pdo->prepare("INSERT INTO book_borrow_logs (book_id, student_id, action, action_date, notes) VALUES (?, ?, 'notify_waitlist', ?, ?)")->execute([$book_id, $next_waiter['student_id'], $today_fa, 'ارسال پیامک اطلاع‌رسانی پس از انصراف متقاضی قبلی']);
                }
            }
            
            $message_status = 'درخواست امانت کتاب «' . $bk['title'] . '» لغو گردید.';
            $message_type = 'info';
        }
    } elseif ($_POST['action'] === 'set_student_password') {
        $new_pwd = trim($_POST['student_password'] ?? '');
        if (strlen($new_pwd) >= 4) {
            $upd = $pdo->prepare("UPDATE students SET password = ? WHERE id = ?");
            $upd->execute([$new_pwd, $student_id]);
            $message_status = 'رمز عبور اختصاصی با موفقیت ثبت شد. از این پس می‌توانید با این رمز عبور نیز وارد شوید.';
            $message_type = 'success';
            log_activity('تغییر رمز عبور دانش‌آموز', 'student', $student_id, "تنظیم رمز عبور اختصاصی جدید توسط دانش‌آموز");
        } else {
            $message_status = 'رمز عبور باید حداقل ۴ کاراکتر باشد.';
            $message_type = 'error';
        }
    }
}

// تعیین تب فعال اولیه با قابلیت بازگشت به تب مربوطه پس از فرم‌ها (برای ویانا به صورت پیش‌فرض تب کتاب‌ها)
$default_active_tab = ($is_operator_viana || $student_id === 745 || is_viana()) ? 'books' : 'dashboard';
if (isset($_GET['tab']) && in_array($_GET['tab'], ['dashboard', 'books', 'courses', 'reports', 'mentorship', 'ai-tutor'])) {
    $default_active_tab = $_GET['tab'];
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['request_library_book', 'join_book_waitlist', 'leave_book_waitlist', 'cancel_library_request', 'request_book'])) {
    $default_active_tab = 'books';
}

// Fetch Student Book Requests (Custom Purchases)
$book_requests_stmt = $pdo->prepare("SELECT * FROM student_book_requests WHERE student_id = ? ORDER BY id DESC");
$book_requests_stmt->execute([$student_id]);
$book_requests = $book_requests_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Books Currently Borrowed by this Student from Foundation Library
$my_borrowed_stmt = $pdo->prepare("SELECT * FROM library_books WHERE current_borrower_id = ? AND status = 'borrowed' ORDER BY borrowed_at DESC, id DESC");
$my_borrowed_stmt->execute([$student_id]);
$my_borrowed_books = $my_borrowed_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch Books Requested by this Student (Pending Handover)
$my_requested_stmt = $pdo->prepare("SELECT * FROM library_books WHERE current_borrower_id = ? AND status = 'requested' ORDER BY id DESC");
$my_requested_stmt->execute([$student_id]);
$my_requested_books = $my_requested_stmt->fetchAll(PDO::FETCH_ASSOC);
$my_requested_book_ids = array_column($my_requested_books, 'id');

// Fetch Books in Waitlist for this Student (Queue and Notifications)
$my_waitlist_stmt = $pdo->prepare("
    SELECT w.id as waitlist_id, w.status as wait_status, w.created_at as wait_date, w.notified_at,
           b.id as book_id, b.book_code, b.title as book_title, b.category as book_category, b.grade_level as book_grade, b.publisher as book_publisher, b.status as book_status, b.expected_return_date,
           (SELECT COUNT(*) FROM book_waitlist w2 WHERE w2.book_id = w.book_id AND w2.status = 'waiting' AND w2.id <= w.id) as queue_position
    FROM book_waitlist w
    JOIN library_books b ON w.book_id = b.id
    WHERE w.student_id = ? AND w.status IN ('waiting', 'notified')
    ORDER BY w.id DESC
");
$my_waitlist_stmt->execute([$student_id]);
$my_waitlist_books = $my_waitlist_stmt->fetchAll(PDO::FETCH_ASSOC);

// ساخت نقشه برای دسترسی سریع به وضعیت نوبت هر کتاب
$my_waitlist_map = [];
foreach ($my_waitlist_books as $witem) {
    $my_waitlist_map[$witem['book_id']] = $witem;
}

// دریافت تعداد کل افراد در صف انتظار برای هر کتاب
$waitlist_counts = $pdo->query("SELECT book_id, COUNT(*) as wait_count FROM book_waitlist WHERE status = 'waiting' GROUP BY book_id")->fetchAll(PDO::FETCH_KEY_PAIR);

// Fetch All Foundation Library Books for Browsing with Borrower Details for Operator
$library_catalog = $pdo->query("
    SELECT b.*, 
           s.name as borrower_name, s.surname as borrower_surname, s.grade as borrower_grade, s.code as borrower_code
    FROM library_books b 
    LEFT JOIN students s ON b.current_borrower_id = s.id 
    ORDER BY CASE b.status WHEN 'available' THEN 1 WHEN 'requested' THEN 2 ELSE 3 END, b.category ASC, b.id ASC
")->fetchAll(PDO::FETCH_ASSOC);
$total_lib_count = count($library_catalog);
$available_lib_count = count(array_filter($library_catalog, fn($b) => $b['status'] === 'available'));

// Fetch Active Sponsorship
$sponsorship_stmt = $pdo->prepare("SELECT * FROM sponsorships WHERE student_id = ? AND status = 'active' LIMIT 1");
$sponsorship_stmt->execute([$student_id]);
$active_spon = $sponsorship_stmt->fetch();

$messages = [];
if ($active_spon) {
    // Show approved messages, or pending messages sent by the student themselves
    $msg_stmt = $pdo->prepare("SELECT * FROM sponsorship_messages WHERE sponsorship_id = ? AND (status = 'approved' OR sender_type = 'student') ORDER BY id ASC");
    $msg_stmt->execute([$active_spon['id']]);
    $messages = $msg_stmt->fetchAll();
}

// Fetch Grade Reports
$docs_stmt = $pdo->prepare("SELECT * FROM documents WHERE owner_type = 'student' AND owner_id = ? ORDER BY id DESC");
$docs_stmt->execute([$student_id]);
$documents = $docs_stmt->fetchAll();

// Logout
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: academy.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>آکادمی استعدادهای حکمت | پنل دانش‌پژوهان</title>
<style>
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:100;font-display:swap;src:url('/assets/fonts/Vazirmatn-100.woff2') format('woff2')}
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:300;font-display:swap;src:url('/assets/fonts/Vazirmatn-300.woff2') format('woff2')}
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:400;font-display:swap;src:url('/assets/fonts/Vazirmatn-400.woff2') format('woff2')}
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:500;font-display:swap;src:url('/assets/fonts/Vazirmatn-500.woff2') format('woff2')}
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:700;font-display:swap;src:url('/assets/fonts/Vazirmatn-700.woff2') format('woff2')}
@font-face{font-family:'Vazirmatn';font-style:normal;font-weight:900;font-display:swap;src:url('/assets/fonts/Vazirmatn-900.woff2') format('woff2')}
</style>
<link rel="stylesheet" href="/assets/tailwind.min.css">
<script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: { sans: ['Vazirmatn', 'sans-serif'] },
                    colors: {
                        academy: {
                            950: '#020617',
                            900: '#0f172a',
                            800: '#1e293b',
                            700: '#334155',
                            500: '#64748b',
                            teal: '#0d9488',
                            blue: '#2563eb'
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .glass-panel {
            background: rgba(30, 41, 59, 0.7);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        .tab-btn.active {
            background-color: #0d9488;
            color: white;
            box-shadow: 0 4px 12px rgba(13, 148, 136, 0.3);
        }
        .chat-container::-webkit-scrollbar {
            width: 4px;
        }
        .chat-container::-webkit-scrollbar-thumb {
            background-color: rgba(255, 255, 255, 0.1);
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
<body class="bg-academy-950 text-slate-100 font-sans min-h-screen overflow-x-hidden"
    x-data="{
        activeTab: 'dashboard',
        showUploadModal: false
    }">

    <!-- Admin Manager Supervision Bar -->
    <?php if ($is_admin_viewer): ?>
    <div class="bg-gradient-to-r from-amber-600 via-teal-700 to-indigo-900 text-white px-4 py-3.5 shadow-2xl border-b border-amber-400/30 sticky top-0 z-50">
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
                <span class="bg-black/40 text-amber-300 border border-amber-400/40 px-3 py-1 rounded-full text-[11px] font-black tracking-wide flex items-center gap-1.5 shadow-inner">
                    <span>👁️</span> <?php echo $viewer_badge; ?>
                </span>
                <span class="text-white/90 flex items-center gap-1.5 flex-wrap">
                    <span>رصد پورتال:</span>
                    <strong class="text-white text-sm underline decoration-amber-400"><?php echo htmlspecialchars($student['name'] . ' ' . $student['surname']); ?></strong>
                    <span class="text-teal-200 text-[11px]">(پایه <?php echo htmlspecialchars($student['grade'] ?: '---'); ?>)</span>
                    <span class="px-2 py-0.5 rounded-full text-[10px] font-black <?php echo ($student_status === 'exited') ? 'bg-rose-500/40 text-rose-200 border border-rose-400/50' : 'bg-white/20 text-white'; ?>">
                        <?php echo $st_status_labels[$student_status] ?? 'نامشخص'; ?>
                    </span>
                </span>
            </div>
            <div class="flex flex-wrap items-center gap-2.5">
                <label class="text-[11px] text-white/80 shrink-0">تغییر دانش‌آموز:</label>
                <select onchange="window.location.href='student-dashboard.php?student_id=' + this.value" class="bg-black/50 border border-white/25 text-white rounded-xl px-3 py-1.5 text-xs font-bold focus:outline-none focus:ring-2 focus:ring-amber-400 max-w-[240px]">
                    <?php foreach ($all_students_list as $st_opt): ?>
                        <option value="<?php echo $st_opt['id']; ?>" <?php echo $st_opt['id'] == $student_id ? 'selected' : ''; ?>>
                            <?php echo ($st_status_labels[$st_opt['status'] ?? 'active'] ?? '⚪') . ' ' . htmlspecialchars($st_opt['name'] . ' ' . $st_opt['surname'] . ' (کد ' . ($st_opt['code'] ?: $st_opt['id']) . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <a href="person-detail.php?id=<?php echo $student_id; ?>" class="bg-white/15 hover:bg-white/25 text-white border border-white/20 px-3 py-1.5 rounded-xl transition-colors flex items-center gap-1">
                    <span>📄</span> پرونده اداری
                </a>
                <?php if (can_add_student()): ?>
                <a href="people-list.php?open_modal=1" class="bg-emerald-500 hover:bg-emerald-400 text-white font-black px-3 py-1.5 rounded-xl transition-all shadow-md flex items-center gap-1">
                    <span class="text-sm font-black">+</span> افزودن مددجو
                </a>
                <?php endif; ?>
                <a href="donor-dashboard.php" class="bg-teal-600/80 hover:bg-teal-700 text-white px-3 py-1.5 rounded-xl transition-colors flex items-center gap-1">
                    <span>💎</span> پورتال خیرین
                </a>
                <a href="admin/index.php" class="bg-rose-500/80 hover:bg-rose-600 text-white px-3 py-1.5 rounded-xl transition-colors flex items-center gap-1">
                    <span>←</span> خروج به پنل مدیریت
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Top Navigation -->
    <?php include 'includes/dashboard-nav.php'; ?>

    <!-- Main Container -->
    <div class="container mx-auto px-4 py-8 lg:py-12">
        
        <?php if ($message_status): ?>
            <div class="max-w-4xl mx-auto mb-6 p-4 rounded-2xl border text-center text-sm font-bold backdrop-blur-sm <?php echo $message_type === 'success' ? 'bg-emerald-500/15 border-emerald-500/40 text-emerald-300' : 'bg-red-500/15 border-red-500/40 text-red-300'; ?>">
                <?php echo htmlspecialchars($message_status); ?>
            </div>
        <?php endif; ?>



        <div class="max-w-6xl mx-auto flex flex-col lg:flex-row gap-8">
            
            <!-- Side Navigation Menu -->
            <aside class="w-full lg:w-64 flex flex-row lg:flex-col gap-2 overflow-x-auto pb-4 lg:pb-0 shrink-0">
                <button onclick="changeTab('dashboard')" id="btn-dashboard" class="tab-btn <?php echo $default_active_tab === 'dashboard' ? 'active bg-teal-600 text-white' : 'text-slate-400 hover:bg-white/5'; ?> w-full px-5 py-4 rounded-2xl font-black text-right text-sm transition-all flex items-center gap-3">
                    <span>🏠</span> داشبورد من
                </button>
                <button onclick="changeTab('books')" id="btn-books" class="tab-btn <?php echo $default_active_tab === 'books' ? 'active bg-teal-600 text-white' : 'text-slate-400 hover:bg-white/5'; ?> w-full px-5 py-4 rounded-2xl font-black text-right text-sm transition-all flex items-center justify-between">
                    <span class="flex items-center gap-3">
                        <span>📚</span> کتاب‌ها و نیازهای درسی
                    </span>
                    <?php if (!empty($book_requests)): ?>
                        <span class="bg-teal-500/20 text-teal-300 text-[10px] px-2 py-0.5 rounded-full font-mono font-bold"><?php echo count($book_requests); ?></span>
                    <?php endif; ?>
                </button>
                <button onclick="changeTab('courses')" id="btn-courses" class="tab-btn <?php echo $default_active_tab === 'courses' ? 'active bg-teal-600 text-white' : 'text-slate-400 hover:bg-white/5'; ?> w-full px-5 py-4 rounded-2xl font-black text-right text-sm transition-all flex items-center gap-3">
                    <span>💻</span> کلاس‌ها و ویدیوها
                </button>
                <button onclick="changeTab('reports')" id="btn-reports" class="tab-btn <?php echo $default_active_tab === 'reports' ? 'active bg-teal-600 text-white' : 'text-slate-400 hover:bg-white/5'; ?> w-full px-5 py-4 rounded-2xl font-black text-right text-sm transition-all flex items-center gap-3">
                    <span>📊</span> کارنامه‌ها و مدارک
                </button>
                <button onclick="changeTab('mentorship')" id="btn-mentorship" class="tab-btn <?php echo $default_active_tab === 'mentorship' ? 'active bg-teal-600 text-white' : 'text-slate-400 hover:bg-white/5'; ?> w-full px-5 py-4 rounded-2xl font-black text-right text-sm transition-all flex items-center gap-3 relative">
                    <span>🤝</span> ارتباط با منتور
                    <?php if ($active_spon): ?>
                        <span class="absolute left-4 w-2 h-2 bg-teal-500 rounded-full animate-ping"></span>
                    <?php endif; ?>
                </button>
                <button onclick="changeTab('ai-tutor')" id="btn-ai-tutor" class="tab-btn <?php echo $default_active_tab === 'ai-tutor' ? 'active bg-teal-600 text-white' : 'text-slate-400 hover:bg-white/5'; ?> w-full px-5 py-4 rounded-2xl font-black text-right text-sm transition-all flex items-center gap-3">
                    <span>🤖</span> دستیار علمی هوش مصنوعی
                </button>
            </aside>

            <!-- Content Area -->
            <div class="flex-1 min-w-0">
                
                <!-- 1. DASHBOARD TAB -->
                <div id="tab-dashboard" class="tab-content <?php echo $default_active_tab === 'dashboard' ? '' : 'hidden'; ?> space-y-8 scroll-mt-24">
                    <!-- Profile Intro Hero -->
                    <div class="glass-panel rounded-[3rem] p-8 lg:p-12 relative overflow-hidden flex flex-col md:flex-row items-center gap-8 shadow-2xl">
                        <div class="absolute -right-20 -top-20 w-80 h-80 bg-teal-500/5 rounded-full blur-3xl"></div>
                        <div class="relative w-32 h-32 md:w-40 md:w-40 rounded-full overflow-hidden bg-slate-800/80 border-4 border-teal-500/20 p-2 shrink-0">
                            <img src="<?php echo $avatar_url; ?>" alt="آواتار" class="w-full h-full object-contain">
                        </div>
                        <div class="text-center md:text-right space-y-3 relative z-10 flex-1">
                            <span class="<?php echo $status_badge_class; ?> px-3 py-1 rounded-full text-xs font-bold"><?php echo $status_badge_text; ?></span>
                            <h2 class="text-3xl font-black text-slate-100"><?php echo htmlspecialchars($student['name'] . ' ' . $student['surname']); ?></h2>
                            <p class="text-slate-400 text-sm max-w-xl leading-relaxed">
                                <?php echo $hero_desc; ?>
                            </p>
                        </div>
                    </div>

                    <?php if ($student_status === 'exited'): ?>
                    <div class="glass-panel p-5 rounded-[2rem] border border-rose-500/40 bg-rose-500/10 text-rose-200 flex items-center gap-4">
                        <span class="text-2xl">⚠️</span>
                        <div class="text-xs font-bold leading-relaxed">
                            <strong class="text-rose-100 font-black">اطلاعیه وضعیت پرونده:</strong> این دانش‌آموز در سامانه بنیاد در وضعیت <span class="underline font-black text-white">خروج از بورس</span> ثبت گردیده است. خدمات پشتیبانی مالی و آموزشی برای این پرونده غیرفعال است.
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Cards Stats Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="glass-panel p-8 rounded-[2rem] text-center border border-white/5">
                            <div class="w-12 h-12 bg-white/5 rounded-xl flex items-center justify-center text-xl mx-auto mb-4"><?php echo $bursary_card_icon; ?></div>
                            <h4 class="text-xs font-bold text-slate-400"><?php echo $bursary_card_title; ?></h4>
                            <div class="text-lg font-black <?php echo $bursary_card_color; ?> mt-2"><?php echo $bursary_card_val; ?></div>
                        </div>
                        <div class="glass-panel p-8 rounded-[2rem] text-center border border-white/5">
                            <div class="w-12 h-12 bg-indigo-500/10 text-indigo-400 rounded-xl flex items-center justify-center text-xl mx-auto mb-4">📖</div>
                            <h4 class="text-xs font-bold text-slate-400">پایه و رشته تحصیلی</h4>
                            <div class="text-lg font-black text-slate-100 mt-2"><?php echo htmlspecialchars($student['grade'] . ' - ' . ($student['field_of_study'] ?: 'عمومی')); ?></div>
                        </div>
                        <div class="glass-panel p-8 rounded-[2rem] text-center border border-white/5">
                            <div class="w-12 h-12 bg-purple-500/10 text-purple-400 rounded-xl flex items-center justify-center text-xl mx-auto mb-4">📨</div>
                            <h4 class="text-xs font-bold text-slate-400">منتور رشد علمی</h4>
                            <div class="text-lg font-black text-slate-100 mt-2"><?php echo $mentor_card_val; ?></div>
                        </div>
                    </div>

                    <!-- Books Quick Access Banner on Main Tab -->
                    <div class="glass-panel p-6 rounded-[2.5rem] border border-teal-500/30 bg-gradient-to-r from-teal-950/50 via-slate-900/60 to-slate-900/80 shadow-xl flex flex-col sm:flex-row items-center justify-between gap-4">
                        <div class="flex items-center gap-4 text-right">
                            <div class="w-14 h-14 rounded-2xl bg-teal-500/20 text-teal-300 border border-teal-500/30 flex items-center justify-center text-3xl shrink-0 shadow-inner">
                                📚
                            </div>
                            <div>
                                <h4 class="text-base font-black text-white flex items-center gap-2">
                                    <span>بانک کتاب و امانات بنیاد حکمت</span>
                                    <span class="bg-teal-500/20 text-teal-300 text-[10px] px-2.5 py-0.5 rounded-full font-bold border border-teal-500/30">۲۰۱ جلد کتاب موجود</span>
                                </h4>
                                <p class="text-xs text-slate-300 mt-1">امکان جستجو در میان ۲۰۱ جلد کتب کمک‌آموزشی، تست و کنکور و ثبت امانت یا نوبت در صف انتظار</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <button onclick="changeTab('books')" class="bg-gradient-to-r from-teal-500 to-teal-600 hover:from-teal-400 hover:to-teal-500 text-white font-bold px-5 py-3 rounded-2xl text-xs shadow-lg shadow-teal-500/25 transition-all flex items-center gap-2">
                                <span>📖</span> مشاهده فهرست کتب و امانت ←
                            </button>
                        </div>
                    </div>

                    <!-- Personal Profile Details -->
                    <div class="glass-panel p-8 rounded-[2.5rem] border border-white/5">
                        <h3 class="text-xl font-bold mb-6 text-slate-100 flex items-center gap-3">
                            <span class="w-2 h-6 bg-teal-500 rounded-full"></span> بیوگرافی رشد تحصیلی من
                        </h3>
                        <div class="grid md:grid-cols-2 gap-6">
                            <div>
                                <label class="text-xs text-slate-400 font-bold block mb-1">نام مستعار در آکادمی (جهت حفظ حریم خصوصی):</label>
                                <div class="bg-white/5 border border-white/5 rounded-xl px-4 py-3 text-sm text-teal-300 font-black">
                                    <?php echo htmlspecialchars($alias_name); ?>
                                </div>
                            </div>
                            <div>
                                <label class="text-xs text-slate-400 font-bold block mb-1">استعدادها و علایق در دیتابیس آکادمی:</label>
                                <div class="bg-white/5 border border-white/5 rounded-xl px-4 py-3 text-sm text-slate-200">
                                    <?php echo htmlspecialchars($student['talents'] ?: 'هنوز ثبت نشده است (در قسمت کارنامه بنویسید)'); ?>
                                </div>
                            </div>
                            <div class="md:col-span-2">
                                <label class="text-xs text-slate-400 font-bold block mb-1">رویاها و اهداف شغلی:</label>
                                <div class="bg-white/5 border border-white/5 rounded-xl px-4 py-3 text-sm text-slate-200 leading-relaxed">
                                    <?php echo htmlspecialchars($student['dreams'] ?: 'هنوز ثبت نشده است.'); ?>
                                </div>
                            </div>
                            <?php if (!empty($student['language_class'])): ?>
                            <div>
                                <label class="text-xs text-slate-400 font-bold block mb-1">کلاس زبان تخصصی فعال:</label>
                                <div class="bg-teal-500/10 border border-teal-500/20 rounded-xl px-4 py-3 text-sm text-teal-300 font-bold flex items-center gap-2">
                                    <span>🌐</span>
                                    <span><?php echo htmlspecialchars($student['language_class']); ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($student['items_given'])): ?>
                            <div>
                                <label class="text-xs text-slate-400 font-bold block mb-1">خدمات و تسهیلات ویژه آموزشی:</label>
                                <div class="bg-indigo-500/10 border border-indigo-500/20 rounded-xl px-4 py-3 text-sm text-indigo-200 font-bold flex items-center gap-2">
                                    <span>🎁</span>
                                    <span><?php echo htmlspecialchars($student['items_given']); ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="grid md:grid-cols-2 gap-6">
                        
                        <!-- Book Requests Quick Card -->
                        <div class="glass-panel p-6 rounded-[2rem] border border-teal-500/20 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between mb-3">
                                    <span class="text-xs font-bold text-teal-300 flex items-center gap-2">
                                        <span>📚</span> ملزومات و کتاب‌های تحصیلی
                                    </span>
                                    <span class="text-[10px] bg-teal-500/20 text-teal-300 font-bold px-2 py-0.5 rounded-full">
                                        <?php echo count($book_requests); ?> درخواست
                                    </span>
                                </div>
                                <h4 class="text-base font-bold text-white mb-2">نیاز به کتاب درسی یا تست دارید؟</h4>
                                <p class="text-xs text-slate-400 leading-relaxed">
                                    عناوین کتاب‌های کمک‌آموزشی، تستی و درسی مورد نیاز خود را ثبت کنید تا توسط معاونت آموزشی بنیاد (خانم فرتاش) بررسی و تهیه شود.
                                </p>
                            </div>
                            <div class="mt-4 pt-4 border-t border-white/5 flex gap-2">
                                <button onclick="changeTab('books')" class="flex-1 py-2.5 bg-teal-600 hover:bg-teal-500 text-white font-bold rounded-xl text-xs transition-colors text-center">
                                    مشاهده و ثبت درخواست کتاب ←
                                </button>
                            </div>
                        </div>

                        <!-- Permanent Password Setting Card -->
                        <div class="glass-panel p-6 rounded-[2rem] border border-white/5 flex flex-col justify-between">
                            <div>
                                <div class="flex items-center justify-between mb-3">
                                    <span class="text-xs font-bold text-indigo-300 flex items-center gap-2">
                                        <span>🔑</span> امنیت و رمز عبور اختصاصی
                                    </span>
                                    <span class="text-[10px] <?php echo !empty($student['password']) ? 'bg-emerald-500/20 text-emerald-300' : 'bg-amber-500/20 text-amber-300'; ?> font-bold px-2 py-0.5 rounded-full">
                                        <?php echo !empty($student['password']) ? 'رمز فعال است' : 'رمز تنظیم نشده'; ?>
                                    </span>
                                </div>
                                <h4 class="text-base font-bold text-white mb-2">ورود سریع بدون پیامک</h4>
                                <p class="text-xs text-slate-400 leading-relaxed">
                                    می‌توانید یک رمز عبور شخصی برای خود انتخاب کنید تا در دفعات بعد بدون نیاز به کد پیامکی، با کد ملی و این رمز وارد شوید.
                                </p>
                            </div>
                            <form method="POST" action="" class="mt-4 pt-4 border-t border-white/5 flex gap-2">
                                <input type="hidden" name="action" value="set_student_password">
                                <input type="text" name="student_password" placeholder="حداقل ۴ رقم یا حرف..." required
                                    class="flex-1 bg-white/5 border border-white/10 text-white placeholder-white/30 rounded-xl px-3 py-2 text-xs focus:outline-none focus:border-indigo-400 font-mono" dir="ltr">
                                <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 text-white font-bold px-4 py-2 rounded-xl text-xs transition-colors shrink-0">
                                    ذخیره رمز
                                </button>
                            </form>
                        </div>

                    </div>
                </div>

                <!-- 2. COURSES TAB -->
                <div id="tab-courses" class="tab-content hidden space-y-6">
                    <h2 class="text-2xl font-black text-slate-100">ویدیوهای آموزشی فعال</h2>
                    <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <!-- Card 1 -->
                        <div class="glass-panel rounded-2xl overflow-hidden border border-white/5 hover:border-teal-500/30 transition-all group">
                            <div class="aspect-video bg-slate-800/80 relative flex items-center justify-center text-slate-500">
                                <span class="text-4xl group-hover:scale-125 transition-transform">▶</span>
                            </div>
                            <div class="p-5">
                                <span class="text-[10px] text-teal-400 font-bold uppercase tracking-wider">ریاضیات</span>
                                <h3 class="font-bold text-slate-100 mt-1 mb-2">آموزش ریاضی پایه دهم - فصل اول</h3>
                                <p class="text-slate-400 text-xs mb-4">مجموعه ها و احتمال، بازه‌ها و فرمول‌ها</p>
                                <button class="w-full py-2 bg-white/5 hover:bg-teal-600 hover:text-white rounded-lg text-xs font-bold transition-all text-slate-300">شروع یادگیری</button>
                            </div>
                        </div>

                        <!-- Card 2 -->
                        <div class="glass-panel rounded-2xl overflow-hidden border border-white/5 hover:border-teal-500/30 transition-all group">
                            <div class="aspect-video bg-slate-800/80 relative flex items-center justify-center text-slate-500">
                                <span class="text-4xl group-hover:scale-125 transition-transform">▶</span>
                            </div>
                            <div class="p-5">
                                <span class="text-[10px] text-teal-400 font-bold uppercase tracking-wider">فیزیک</span>
                                <h3 class="font-bold text-slate-100 mt-1 mb-2">قوانین نیرو و حرکت نیوتن</h3>
                                <p class="text-slate-400 text-xs mb-4">بررسی قانون اول، دوم و سوم با تست کنکور</p>
                                <button class="w-full py-2 bg-white/5 hover:bg-teal-600 hover:text-white rounded-lg text-xs font-bold transition-all text-slate-300">شروع یادگیری</button>
                            </div>
                        </div>

                        <!-- Card 3 -->
                        <div class="glass-panel rounded-2xl overflow-hidden border border-white/5 hover:border-teal-500/30 transition-all group">
                            <div class="aspect-video bg-slate-800/80 relative flex items-center justify-center text-slate-500">
                                <span class="text-4xl group-hover:scale-125 transition-transform">▶</span>
                            </div>
                            <div class="p-5">
                                <span class="text-[10px] text-teal-400 font-bold uppercase tracking-wider">شیمی</span>
                                <h3 class="font-bold text-slate-100 mt-1 mb-2">ساختار اتم و جدول تناوبی</h3>
                                <p class="text-slate-400 text-xs mb-4">آشنایی با آرایش الکترونی و خواص عناصر</p>
                                <button class="w-full py-2 bg-white/5 hover:bg-teal-600 hover:text-white rounded-lg text-xs font-bold transition-all text-slate-300">شروع یادگیری</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. REPORTS TAB -->
                <div id="tab-reports" class="tab-content hidden space-y-8">
                    <div class="flex justify-between items-center gap-4">
                        <h2 class="text-2xl font-black text-slate-100">کارنامه‌های تحصیلی من</h2>
                        <button onclick="toggleUploadModal(true)" class="bg-teal-600 hover:bg-teal-500 text-white font-bold px-4 py-2.5 rounded-xl text-xs shadow-lg shadow-teal-500/20 transition-all flex items-center gap-2">
                            📤 آپلود کارنامه جدید
                        </button>
                    </div>

                    <!-- Modal for upload -->
                    <div id="upload-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center bg-black/65 backdrop-blur-sm p-4">
                        <div class="bg-academy-900 border border-white/10 w-full max-w-md rounded-[2.5rem] overflow-hidden p-8 shadow-2xl">
                            <div class="flex justify-between items-center mb-6">
                                <h3 class="text-lg font-bold text-slate-100">آپلود سند یا کارنامه تحصیلی</h3>
                                <button onclick="toggleUploadModal(false)" class="text-slate-400 hover:text-white text-xl">✕</button>
                            </div>
                            <form method="POST" action="" enctype="multipart/form-data" class="space-y-6">
                                <input type="hidden" name="action" value="upload_report">
                                
                                <div>
                                    <label class="block text-xs font-bold text-slate-400 mb-2">توضیح کارنامه (مثال: کارنامه نوبت اول کلاس دهم)</label>
                                    <input type="text" name="description" required placeholder="کارنامه نوبت اول دی ماه..."
                                        class="w-full bg-white/5 border border-white/10 text-white placeholder-white/30 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-teal-500 transition-all">
                                </div>
                                
                                <div>
                                    <label class="block text-xs font-bold text-slate-400 mb-2">انتخاب فایل کارنامه (PDF یا عکس)</label>
                                    <div class="relative border-2 border-dashed border-white/10 rounded-2xl p-6 text-center hover:border-teal-500 transition-all cursor-pointer">
                                        <input type="file" name="report_file" required accept=".pdf,.png,.jpg,.jpeg" class="absolute inset-0 opacity-0 cursor-pointer">
                                        <div class="text-slate-400">
                                            <span class="block text-2xl mb-2">📄</span>
                                            <span class="text-xs">کلیک کنید یا فایل را بکشید اینجا</span>
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="submit" class="w-full py-3 bg-teal-600 hover:bg-teal-500 text-white rounded-xl font-bold text-sm transition-all shadow-lg">
                                    ارسال و تایید کارنامه
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- List of reports -->
                    <div class="glass-panel rounded-2xl overflow-hidden border border-white/5">
                        <table class="w-full text-right border-collapse">
                            <thead>
                                <tr class="bg-white/5 text-slate-300 text-xs font-bold border-b border-white/5">
                                    <th class="p-4">ردیف</th>
                                    <th class="p-4">عنوان / شرح کارنامه</th>
                                    <th class="p-4">تاریخ آپلود</th>
                                    <th class="p-4 text-left">عملیات</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5 text-sm text-slate-300">
                                <?php if (empty($documents)): ?>
                                    <tr>
                                        <td colspan="4" class="p-8 text-center text-xs text-slate-500 font-bold">هیچ کارنامه‌ای تاکنون آپلود نکرده‌اید.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($documents as $index => $doc): ?>
                                    <tr class="hover:bg-white/5 transition-colors">
                                        <td class="p-4 text-xs font-black text-slate-500"><?php echo $index + 1; ?></td>
                                        <td class="p-4 font-bold text-slate-200"><?php echo htmlspecialchars($doc['description']); ?></td>
                                        <td class="p-4 text-xs"><?php echo htmlspecialchars($doc['upload_date']); ?></td>
                                        <td class="p-4 text-left">
                                            <a href="<?php echo htmlspecialchars($doc['file_path']); ?>" target="_blank" class="bg-teal-500/10 text-teal-400 hover:bg-teal-500 hover:text-white px-3 py-1.5 rounded-lg text-xs font-bold transition-all inline-block">📄 دانلود فایل</a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- 4. MENTORSHIP TAB -->
                <div id="tab-mentorship" class="tab-content hidden space-y-6">
                    <h2 class="text-2xl font-black text-slate-100">ارتباط با منتور تحصیلی و رشد</h2>
                    
                    <?php if (!$active_spon): ?>
                        <div class="glass-panel p-8 rounded-[2rem] border border-white/5 text-center text-slate-400">
                            <span class="text-4xl block mb-4">⌛</span>
                            بخش منتورینگ به زودی و پس از بررسی مدارک تحصیلی شما فعال خواهد شد. شما بلافاصله از تخصیص منتور رشد مطلع خواهید شد.
                        </div>
                    <?php else: ?>
                        <div class="glass-panel rounded-[2rem] border border-white/5 overflow-hidden flex flex-col h-[550px]">
                            <!-- Chat Header -->
                            <div class="bg-white/5 p-4 border-b border-white/5 flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 bg-teal-500/10 rounded-full flex items-center justify-center text-xl">👤</div>
                                    <div>
                                        <h4 class="font-bold text-sm text-slate-200">منتور رشد علمی شما</h4>
                                        <p class="text-[10px] text-teal-400 font-bold">پشتیبان بورس حکمت</p>
                                    </div>
                                </div>
                                <span class="bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 px-3 py-1 rounded-full text-[10px] font-bold">ارتباط امن فعال</span>
                            </div>

                            <!-- Chat Messages list -->
                            <div class="flex-1 overflow-y-auto p-6 space-y-4 chat-container bg-slate-900/35">
                                <div class="bg-teal-500/10 border border-teal-500/20 text-teal-200 text-xs p-4 rounded-2xl leading-relaxed max-w-xl mx-auto text-center">
                                    خوش آمدید! در این بخش می‌توانید «گزارش برنامه‌ریزی ماهانه» یا «اهداف درسی» خود را بنویسید. منتور شما گزارش را مطالعه کرده و پاسخ‌های راهنمایی برایتان ارسال خواهد کرد. نامه‌ها پس از تایید مدیریت ارسال می‌شوند.
                                </div>

                                <?php foreach ($messages as $msg): ?>
                                    <?php if ($msg['sender_type'] === 'student'): ?>
                                        <!-- Student Message -->
                                        <div class="flex justify-end items-start gap-3">
                                            <div class="flex flex-col items-end gap-1 max-w-[75%]">
                                                <div class="bg-teal-700 text-white rounded-2xl rounded-tr-none px-4 py-3 text-sm leading-loose">
                                                    <?php echo htmlspecialchars($msg['message_text']); ?>
                                                </div>
                                                <div class="flex items-center gap-2 text-[8px] text-slate-500 font-bold px-1">
                                                    <span><?php echo htmlspecialchars($msg['created_at']); ?></span>
                                                    <?php if ($msg['status'] === 'pending'): ?>
                                                        <span class="text-amber-400 bg-amber-500/10 px-2 py-0.5 rounded-full">در انتظار تایید مدیریت</span>
                                                    <?php else: ?>
                                                        <span class="text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded-full">ارسال شده به منتور</span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="w-8 h-8 rounded-full bg-slate-800 text-xs flex items-center justify-center font-bold text-teal-300 border border-teal-500/20 shrink-0">من</div>
                                        </div>
                                    <?php else: ?>
                                        <!-- Mentor Message -->
                                        <div class="flex justify-start items-start gap-3">
                                            <div class="w-8 h-8 rounded-full bg-teal-500/10 text-xs flex items-center justify-center font-bold text-teal-400 shrink-0">م</div>
                                            <div class="flex flex-col items-start gap-1 max-w-[75%]">
                                                <div class="bg-slate-800 text-slate-200 rounded-2xl rounded-tl-none px-4 py-3 text-sm leading-loose border border-white/5">
                                                    <?php echo htmlspecialchars($msg['message_text']); ?>
                                                </div>
                                                <span class="text-[8px] text-slate-500 font-bold px-1"><?php echo htmlspecialchars($msg['created_at']); ?></span>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </div>

                            <!-- Chat Input Form -->
                            <form method="POST" action="" class="bg-white/5 p-4 border-t border-white/5 flex gap-3">
                                <input type="hidden" name="action" value="send_message">
                                <input type="hidden" name="sponsorship_id" value="<?php echo $active_spon['id']; ?>">
                                <textarea name="message_text" required placeholder="گزارش رشد تحصیلی، برنامه‌ریزی یا پیام خود را اینجا بنویسید..." rows="1"
                                    class="flex-1 bg-white/5 border border-white/10 text-white placeholder-white/30 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-teal-500 transition-all resize-none"></textarea>
                                <button type="submit" class="bg-teal-600 hover:bg-teal-500 text-white font-bold px-6 rounded-xl text-xs transition-all shadow-md">ارسال گزارش</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- 5. AI TUTOR TAB -->
                <div id="tab-ai-tutor" class="tab-content hidden space-y-6">
                    <div class="glass-panel rounded-[2rem] border border-white/5 overflow-hidden flex flex-col h-[550px]">
                        <!-- Tutor Header -->
                        <div class="bg-white/5 p-4 border-b border-white/5 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-teal-500/10 rounded-full flex items-center justify-center text-xl">🤖</div>
                                <div>
                                    <h4 class="font-bold text-sm text-slate-200">دستیار هوش علمی و تحصیلی حکمت</h4>
                                    <p class="text-[10px] text-teal-400 font-bold">پاسخگوی ۲۴ ساعته رفع اشکال درسی</p>
                                </div>
                            </div>
                            <span class="bg-teal-500/15 text-teal-300 px-3 py-1 rounded-full text-[10px] font-bold border border-teal-500/20">نسخه آزمایشی (بتا)</span>
                        </div>

                        <!-- Chat Area -->
                        <div id="ai-chat-box" class="flex-1 overflow-y-auto p-6 space-y-4 chat-container bg-slate-900/35">
                            <!-- Welcome -->
                            <div class="flex justify-start items-start gap-3">
                                <div class="w-8 h-8 rounded-full bg-teal-500/15 text-xs flex items-center justify-center text-teal-400 shrink-0">🤖</div>
                                <div class="flex flex-col items-start gap-1 max-w-[85%]">
                                    <div class="bg-slate-800 text-slate-200 rounded-2xl rounded-tl-none px-4 py-3 text-sm leading-loose border border-white/5">
                                        سلام! من دستیار هوشمند علمی آکادمی حکمت هستم. برای حل سوالات ریاضی، فیزیک، شیمی، عربی یا هر درس دیگری می‌توانید روی من حساب کنید. سوال درسی خود را بپرسید یا یکی از موضوعات آماده زیر را انتخاب کنید:
                                        
                                        <div class="mt-4 flex flex-wrap gap-2">
                                            <button onclick="askAI('فرمول شتاب نیوتن چیست؟')" class="bg-teal-500/10 hover:bg-teal-500 hover:text-white border border-teal-500/20 text-teal-300 text-xs px-3 py-1.5 rounded-full transition-all">🍎 فرمول شتاب نیوتن؟</button>
                                            <button onclick="askAI('قوانین فعل ماضی در عربی را توضیح بده')" class="bg-teal-500/10 hover:bg-teal-500 hover:text-white border border-teal-500/20 text-teal-300 text-xs px-3 py-1.5 rounded-full transition-all">📚 ماضی در عربی؟</button>
                                            <button onclick="askAI('نحوه حل کردن معادله درجه ۲')" class="bg-teal-500/10 hover:bg-teal-500 hover:text-white border border-teal-500/20 text-teal-300 text-xs px-3 py-1.5 rounded-full transition-all">📐 معادله درجه ۲؟</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Chat Input Form -->
                        <div class="bg-white/5 p-4 border-t border-white/5 flex gap-3">
                            <input id="ai-user-input" type="text" placeholder="سوال درسی خود را اینجا بپرسید..."
                                class="flex-1 bg-white/5 border border-white/10 text-white placeholder-white/30 rounded-xl px-4 py-3 text-sm focus:outline-none focus:border-teal-500 transition-all">
                            <button onclick="submitAICustom()" class="bg-teal-600 hover:bg-teal-500 text-white font-bold px-6 rounded-xl text-xs transition-all shadow-md">ارسال</button>
                        </div>
                    </div>
                </div>

                <!-- 6. BOOKS & EDUCATIONAL NEEDS TAB -->
                <div id="tab-books" class="tab-content <?php echo $default_active_tab === 'books' ? '' : 'hidden'; ?> space-y-8 scroll-mt-24">
                    
                    <!-- Header Card -->
                    <div class="glass-panel rounded-[2.5rem] p-8 lg:p-10 border border-teal-500/20 relative overflow-hidden shadow-xl">
                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-6 relative z-10">
                            <div>
                                <span class="bg-teal-500/15 text-teal-300 border border-teal-500/30 px-3 py-1 rounded-full text-xs font-bold inline-block mb-2">
                                    معاونت آموزشی بنیاد (سرکار خانم فرتاش) و امور اجرایی (سرکار خانم عباسی)
                                </span>
                                <h3 class="text-2xl font-black text-white flex items-center gap-3">
                                    <span>📚</span> سامانه جامع کتاب و امانات بنیاد حکمت
                                </h3>
                                <p class="text-slate-300 text-xs mt-2 leading-relaxed max-w-2xl">
                                    دانش‌پژوه گرامی؛ شما در این بخش می‌توانید بیش از ۲۰۰ عنوان از کتب کمک‌آموزشی، تست و کنکور موجود در مخزن بنیاد حکمت را مشاهده و امانت بگیرید. همچنین در صورت نیاز به کتابی که در مخزن موجود نیست، می‌توانید سفارش خرید آن را ثبت نمایید.
                                </p>
                            </div>
                            <button onclick="document.getElementById('new-book-modal').classList.remove('hidden')" class="bg-gradient-to-r from-teal-500 to-teal-700 hover:from-teal-400 hover:to-teal-600 text-white font-black px-6 py-3.5 rounded-2xl shadow-xl transition-all text-xs flex items-center gap-2 shrink-0">
                                <span>+</span> سفارش خرید کتاب خارج از مخزن
                            </button>
                        </div>
                    </div>

<!-- 2. Foundation Library Catalog (Search & Loan Request) -->
                    <div class="glass-panel rounded-[2.5rem] p-8 border border-white/5 space-y-6">
                        <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 border-b border-white/5 pb-4">
                            <div>
                                <h4 class="text-lg font-black text-white flex items-center gap-2">
                                    <span>🏛️</span> بانک کتاب‌های بنیاد حکمت (مخزن کتب موجود)
                                </h4>
                                <p class="text-xs text-slate-400 mt-1">
                                    کتاب مورد نیاز خود را جستجو کرده و با یک کلیک درخواست امانت آن را برای خانم فرتاش و خانم عباسی ارسال کنید.
                                </p>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="bg-emerald-500/15 text-emerald-300 border border-emerald-500/30 text-xs px-3 py-1 rounded-full font-bold">
                                    <?php echo toFarsiDigits($available_lib_count); ?> جلد کتاب آماده امانت
                                </span>
                                <span class="bg-white/5 text-slate-400 text-xs px-3 py-1 rounded-full font-bold">
                                    کل مخزن: <?php echo toFarsiDigits($total_lib_count); ?> جلد
                                </span>
                            </div>
                        </div>

                        <!-- Search & Fast Category Filter -->
                        <div class="space-y-3">
                            <div class="relative">
                                <input type="text" id="lib-search-input" onkeyup="filterLibraryCatalog()" placeholder="جستجوی عنوان کتاب، درس، ناشر یا نام استاد (مثلاً: خیلی سبز، شیمی، استاد مرادی، سه‌سطحی)..." class="w-full bg-slate-950 border border-white/15 rounded-2xl px-5 py-3.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-teal-500 shadow-inner">
                                <span class="absolute left-4 top-3.5 text-slate-500 text-sm">🔍</span>
                            </div>

                            <!-- Category Filter Pills -->
                            <div class="flex items-center gap-1.5 overflow-x-auto pb-2 text-[11px] font-bold" id="lib-cat-filters">
                                <button type="button" onclick="setLibCategory('all', this)" class="cat-pill px-3 py-1.5 rounded-xl bg-teal-600 text-white transition-all shrink-0">همه دسته‌ها</button>
                                <button type="button" onclick="setLibCategory('زیست‌شناسی', this)" class="cat-pill px-3 py-1.5 rounded-xl bg-white/5 text-slate-400 hover:text-white transition-all shrink-0">زیست‌شناسی</button>
                                <button type="button" onclick="setLibCategory('فیزیک', this)" class="cat-pill px-3 py-1.5 rounded-xl bg-white/5 text-slate-400 hover:text-white transition-all shrink-0">فیزیک</button>
                                <button type="button" onclick="setLibCategory('شیمی', this)" class="cat-pill px-3 py-1.5 rounded-xl bg-white/5 text-slate-400 hover:text-white transition-all shrink-0">شیمی</button>
                                <button type="button" onclick="setLibCategory('ریاضی و حسابان', this)" class="cat-pill px-3 py-1.5 rounded-xl bg-white/5 text-slate-400 hover:text-white transition-all shrink-0">ریاضی و حسابان</button>
                                <button type="button" onclick="setLibCategory('ادبیات و فارسی', this)" class="cat-pill px-3 py-1.5 rounded-xl bg-white/5 text-slate-400 hover:text-white transition-all shrink-0">ادبیات</button>
                                <button type="button" onclick="setLibCategory('عربی', this)" class="cat-pill px-3 py-1.5 rounded-xl bg-white/5 text-slate-400 hover:text-white transition-all shrink-0">عربی</button>
                                <button type="button" onclick="setLibCategory('دین و زندگی', this)" class="cat-pill px-3 py-1.5 rounded-xl bg-white/5 text-slate-400 hover:text-white transition-all shrink-0">دین و زندگی</button>
                                <button type="button" onclick="setLibCategory('زبان انگلیسی', this)" class="cat-pill px-3 py-1.5 rounded-xl bg-white/5 text-slate-400 hover:text-white transition-all shrink-0">زبان انگلیسی</button>
                                <button type="button" onclick="setLibCategory('جامع و آزمون', this)" class="cat-pill px-3 py-1.5 rounded-xl bg-white/5 text-slate-400 hover:text-white transition-all shrink-0">جامع و آزمون</button>
                            </div>
                        </div>

                        <!-- Catalog Books Grid -->
                        <div id="lib-books-grid" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 max-h-[600px] overflow-y-auto pr-1">
                            <?php foreach ($library_catalog as $bk): ?>
                                <?php 
                                $is_requested_by_me = in_array($bk['id'], $my_requested_book_ids);
                                $is_borrowed_by_me = ($bk['status'] === 'borrowed' && $bk['current_borrower_id'] == $student_id);
                                $is_available = ($bk['status'] === 'available');
                                $is_in_waitlist = isset($my_waitlist_map[$bk['id']]);
                                $wentry = $is_in_waitlist ? $my_waitlist_map[$bk['id']] : null;
                                $wcount = $waitlist_counts[$bk['id']] ?? 0;
                                ?>
                                <div class="lib-book-item bg-white/[0.03] hover:bg-white/[0.06] transition-all p-5 rounded-3xl border border-white/5 flex flex-col justify-between space-y-3"
                                     data-title="<?php echo htmlspecialchars($bk['title']); ?>"
                                     data-cat="<?php echo htmlspecialchars($bk['category'] ?: ''); ?>"
                                     data-publisher="<?php echo htmlspecialchars($bk['publisher'] ?: ''); ?>"
                                     data-grade="<?php echo htmlspecialchars($bk['grade_level'] ?: ''); ?>"
                                     data-code="<?php echo htmlspecialchars($bk['book_code'] ?: ''); ?>">
                                    
                                    <div class="space-y-2">
                                        <div class="flex justify-between items-start gap-2">
                                            <span class="text-[10px] font-mono font-bold bg-teal-500/20 text-teal-300 px-2 py-0.5 rounded-md">
                                                <?php echo htmlspecialchars($bk['book_code']); ?>
                                            </span>
                                            <span class="text-[10px] font-bold px-2 py-0.5 rounded-lg bg-white/5 text-slate-300 border border-white/5">
                                                <?php echo htmlspecialchars($bk['category'] ?: 'سایر کتب'); ?>
                                            </span>
                                        </div>

                                        <h5 class="text-sm font-black text-white leading-snug">
                                            <?php echo htmlspecialchars($bk['title']); ?>
                                        </h5>

                                        <div class="text-[11px] text-slate-400 space-y-1">
                                            <div>پایه: <strong class="text-slate-200"><?php echo htmlspecialchars($bk['grade_level'] ?: 'عمومی'); ?></strong></div>
                                            <div>ناشر / استاد: <strong class="text-slate-200"><?php echo htmlspecialchars($bk['publisher'] ?: '—'); ?></strong></div>
                                            <?php if (!empty($bk['publish_year'])): ?>
                                                <div>سال چاپ: <strong class="text-slate-300 font-mono"><?php echo toFarsiDigits($bk['publish_year']); ?></strong></div>
                                            <?php endif; ?>
                                            <?php if (!empty($bk['description'])): ?>
                                                <div class="text-slate-400 text-[10px] bg-black/20 p-2 rounded-xl border border-white/5">
                                                    <?php echo htmlspecialchars($bk['description']); ?>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($is_operator_viana && $bk['status'] === 'borrowed'): ?>
                                                <div class="bg-blue-500/15 border border-blue-500/30 text-blue-200 text-[10px] p-2.5 rounded-xl mt-1.5 space-y-0.5">
                                                    <div class="font-black text-blue-300 flex items-center justify-between">
                                                        <span>👤 امانت‌گیرنده:</span>
                                                        <span class="text-white"><?php echo htmlspecialchars(($bk['borrower_name'] ?? '') . ' ' . ($bk['borrower_surname'] ?? 'دانش‌آموز')); ?></span>
                                                    </div>
                                                    <div class="text-[9px] text-slate-300">
                                                        پایه: <?php echo htmlspecialchars($bk['borrower_grade'] ?: 'عمومی'); ?>
                                                        <?php if (!empty($bk['expected_return_date'])): ?>
                                                            | موعد عودت: <span class="text-amber-300 font-mono"><?php echo htmlspecialchars($bk['expected_return_date']); ?></span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php elseif ($is_operator_viana && $bk['status'] === 'requested' && !empty($bk['borrower_name'])): ?>
                                                <div class="bg-amber-500/15 border border-amber-500/30 text-amber-200 text-[10px] p-2 rounded-xl mt-1.5 flex items-center justify-between">
                                                    <span class="font-bold text-amber-300">درخواست امانت:</span>
                                                    <span class="text-white font-black"><?php echo htmlspecialchars($bk['borrower_name'] . ' ' . $bk['borrower_surname']); ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>

                                    <div class="pt-2 border-t border-white/5">
                                        <?php if ($is_available): ?>
                                            <form method="POST" onsubmit="return confirm('آیا مایل به ثبت درخواست امانت کتاب «<?php echo addslashes($bk['title']); ?>» هستید؟')">
                                                <input type="hidden" name="action" value="request_library_book">
                                                <input type="hidden" name="book_id" value="<?php echo $bk['id']; ?>">
                                                <button type="submit" class="w-full py-2.5 bg-teal-600 hover:bg-teal-500 text-white font-bold rounded-xl text-xs transition-colors shadow-md flex items-center justify-center gap-1.5">
                                                    <span>📖</span> درخواست امانت این کتاب
                                                </button>
                                            </form>
                                        <?php elseif ($is_requested_by_me): ?>
                                            <div class="space-y-2">
                                                <div class="w-full py-2 bg-amber-500/15 border border-amber-500/30 text-amber-300 font-bold rounded-xl text-[11px] text-center">
                                                    🟡 درخواست شما ثبت شد (در انتظار تحویل)
                                                </div>
                                                <form method="POST" onsubmit="return confirm('آیا مایل به لغو درخواست امانت این کتاب هستید؟')">
                                                    <input type="hidden" name="action" value="cancel_library_request">
                                                    <input type="hidden" name="book_id" value="<?php echo $bk['id']; ?>">
                                                    <button type="submit" class="w-full text-center text-[10px] text-rose-300 hover:text-rose-200 underline">
                                                        انصراف از درخواست
                                                    </button>
                                                </form>
                                            </div>
                                        <?php elseif ($is_borrowed_by_me): ?>
                                            <div class="w-full py-2 bg-blue-500/15 border border-blue-500/30 text-blue-300 font-bold rounded-xl text-[11px] text-center">
                                                🟢 در دست امانت شماست
                                                <?php if (!empty($bk['expected_return_date'])): ?>
                                                    <span class="block text-[10px] text-blue-200 mt-0.5 font-mono">مهلت بازگشت: <?php echo htmlspecialchars($bk['expected_return_date']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        <?php elseif ($is_in_waitlist && $wentry['wait_status'] === 'waiting'): ?>
                                            <div class="bg-amber-500/10 border border-amber-500/30 p-2.5 rounded-2xl space-y-2 text-center">
                                                <div class="text-amber-300 font-bold text-xs flex items-center justify-center gap-1.5">
                                                    <span>🔔</span> شما در نوبت هستید (نفر <?php echo toFarsiDigits($wentry['queue_position'] ?? 1); ?> در صف)
                                                </div>
                                                <?php if (!empty($bk['expected_return_date'])): ?>
                                                    <div class="text-[10px] text-slate-400 font-mono">موعد تقریبی بازگشت: <span class="text-white"><?php echo htmlspecialchars($bk['expected_return_date']); ?></span></div>
                                                <?php endif; ?>
                                                <form method="POST" onsubmit="return confirm('آیا از لغو نوبت خود در صف انتظار این کتاب اطمینان دارید؟')">
                                                    <input type="hidden" name="action" value="leave_book_waitlist">
                                                    <input type="hidden" name="book_id" value="<?php echo $bk['id']; ?>">
                                                    <input type="hidden" name="waitlist_id" value="<?php echo $wentry['waitlist_id']; ?>">
                                                    <button type="submit" class="text-[10px] text-rose-300 hover:text-rose-200 underline">
                                                        انصراف از صف انتظار
                                                    </button>
                                                </form>
                                            </div>
                                        <?php elseif ($is_in_waitlist && $wentry['wait_status'] === 'notified'): ?>
                                            <div class="bg-emerald-500/15 border border-emerald-500/30 p-2.5 rounded-2xl space-y-2 text-center">
                                                <div class="text-emerald-300 font-bold text-xs">
                                                    ✨ کتاب عودت شد و نوبت شماست!
                                                </div>
                                                <form method="POST">
                                                    <input type="hidden" name="action" value="request_library_book">
                                                    <input type="hidden" name="book_id" value="<?php echo $bk['id']; ?>">
                                                    <button type="submit" class="w-full py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-xs transition-colors shadow-md">
                                                        📖 ثبت درخواست امانت فوری
                                                    </button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <div class="space-y-2">
                                                <div class="w-full py-1.5 bg-white/5 text-slate-400 font-medium rounded-xl text-[10px] text-center flex items-center justify-center gap-1">
                                                    <span>🔒</span> در دست امانت سایرین
                                                    <?php if (!empty($bk['expected_return_date'])): ?>
                                                        <span class="text-slate-500 font-mono">(موعد عودت: <?php echo htmlspecialchars($bk['expected_return_date']); ?>)</span>
                                                    <?php endif; ?>
                                                </div>
                                                <form method="POST" onsubmit="return confirm('آیا مایل به قرارگیری در نوبت کتاب «<?php echo addslashes($bk['title']); ?>» هستید؟\nبه محض بازگشت این کتاب به کتابخانه بنیاد، پیامک اطلاع‌رسانی برای شما ارسال خواهد شد.')">
                                                    <input type="hidden" name="action" value="join_book_waitlist">
                                                    <input type="hidden" name="book_id" value="<?php echo $bk['id']; ?>">
                                                    <button type="submit" class="w-full py-2 bg-gradient-to-r from-amber-600 to-amber-700 hover:from-amber-500 hover:to-amber-600 text-white font-bold rounded-xl text-xs transition-all shadow-md flex items-center justify-center gap-1.5">
                                                        <span>🔔</span> نوبت بگیر / به من اطلاع بده
                                                        <?php if ($wcount > 0): ?>
                                                            <span class="bg-black/30 text-amber-200 text-[10px] px-1.5 py-0.5 rounded-full font-mono font-bold"><?php echo toFarsiDigits($wcount); ?> نفر در صف</span>
                                                        <?php endif; ?>
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- 1. Currently Borrowed Books by this Student -->
                    <div class="glass-panel rounded-[2.5rem] p-8 border border-blue-500/20 space-y-5 bg-gradient-to-br from-blue-950/20 via-slate-900/40 to-slate-900/80">
                        <div class="flex items-center justify-between">
                            <h4 class="text-base font-black text-white flex items-center gap-2">
                                <span>🤝</span> کتاب‌های در دست امانت شما (تحویل‌گرفته از بنیاد)
                            </h4>
                            <span class="bg-blue-500/20 text-blue-300 border border-blue-500/30 text-xs px-3 py-1 rounded-full font-bold">
                                <?php echo count($my_borrowed_books); ?> جلد کتاب در اختیار شما
                            </span>
                        </div>

                        <?php if (empty($my_borrowed_books)): ?>
                            <div class="text-center py-6 bg-white/[0.02] rounded-2xl border border-dashed border-white/10">
                                <div class="text-2xl mb-1">📖</div>
                                <div class="text-xs font-bold text-slate-300">در حال حاضر هیچ کتابی در دست امانت ندارید.</div>
                                <p class="text-[11px] text-slate-500 mt-1">از بخش «بانک کتاب‌های بنیاد حکمت» در زیر می‌توانید کتاب‌های موجود را مشاهده و امانت بگیرید.</p>
                            </div>
                        <?php else: ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <?php foreach ($my_borrowed_books as $mbk): ?>
                                    <div class="bg-slate-900/90 border border-blue-500/30 p-5 rounded-2xl space-y-3 relative overflow-hidden">
                                        <div class="flex justify-between items-start gap-2">
                                            <div>
                                                <span class="text-[10px] font-mono font-bold bg-teal-500/20 text-teal-300 px-2 py-0.5 rounded-md">
                                                    <?php echo htmlspecialchars($mbk['book_code']); ?>
                                                </span>
                                                <h5 class="text-sm font-black text-white mt-1"><?php echo htmlspecialchars($mbk['title']); ?></h5>
                                                <div class="text-[11px] text-slate-400 mt-0.5"><?php echo htmlspecialchars($mbk['grade_level']); ?> • ناشر: <?php echo htmlspecialchars($mbk['publisher'] ?: '—'); ?></div>
                                            </div>
                                            <span class="text-[10px] font-black px-2.5 py-1 rounded-full bg-blue-500/20 text-blue-300 border border-blue-500/30 shrink-0">
                                                🟢 در دست امانت
                                            </span>
                                        </div>

                                        <div class="grid grid-cols-2 gap-2 text-[11px] text-slate-300 bg-white/5 p-3 rounded-xl">
                                            <div>تاریخ تحویل: <strong class="text-white font-mono"><?php echo htmlspecialchars($mbk['borrowed_at'] ?: '—'); ?></strong></div>
                                            <div>موعد بازگشت: <strong class="text-amber-300 font-mono"><?php echo htmlspecialchars($mbk['expected_return_date'] ?: 'پایان ترم'); ?></strong></div>
                                        </div>

                                        <?php if (!empty($mbk['description'])): ?>
                                            <div class="text-[11px] text-slate-400">
                                                توضیحات: <?php echo htmlspecialchars($mbk['description']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- 1.5. Waitlist / Reservations by this Student -->
                    <div class="glass-panel rounded-[2.5rem] p-8 border border-amber-500/20 space-y-5 bg-gradient-to-br from-amber-950/20 via-slate-900/40 to-slate-900/80">
                        <div class="flex items-center justify-between">
                            <h4 class="text-base font-black text-white flex items-center gap-2">
                                <span>🔔</span> کتاب‌های در نوبت امانت شما (صف انتظار و رزرو)
                            </h4>
                            <span class="bg-amber-500/20 text-amber-300 border border-amber-500/30 text-xs px-3 py-1 rounded-full font-bold">
                                <?php echo count($my_waitlist_books); ?> کتاب در صف انتظار
                            </span>
                        </div>

                        <?php if (empty($my_waitlist_books)): ?>
                            <div class="text-center py-6 bg-white/[0.02] rounded-2xl border border-dashed border-white/10">
                                <div class="text-2xl mb-1">🔔</div>
                                <div class="text-xs font-bold text-slate-300">در حال حاضر در نوبت انتظار کتابی قرار ندارید.</div>
                                <p class="text-[11px] text-slate-500 mt-1">چنانچه کتابی در مخزن در دست امانت سایرین باشد، با زدن دکمه «نوبت بگیر / به من اطلاع بده» در صف قرار گرفته و به محض بازگشت با پیامک مطلع خواهید شد.</p>
                            </div>
                        <?php else: ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <?php foreach ($my_waitlist_books as $wbk): ?>
                                    <div class="bg-slate-900/90 border <?php echo $wbk['wait_status'] === 'notified' ? 'border-emerald-500/40' : 'border-amber-500/30'; ?> p-5 rounded-2xl space-y-3 relative overflow-hidden">
                                        <div class="flex justify-between items-start gap-2">
                                            <div>
                                                <span class="text-[10px] font-mono font-bold bg-teal-500/20 text-teal-300 px-2 py-0.5 rounded-md">
                                                    <?php echo htmlspecialchars($wbk['book_code']); ?>
                                                </span>
                                                <h5 class="text-sm font-black text-white mt-1"><?php echo htmlspecialchars($wbk['book_title']); ?></h5>
                                                <div class="text-[11px] text-slate-400 mt-0.5"><?php echo htmlspecialchars($wbk['book_grade']); ?> • ناشر: <?php echo htmlspecialchars($wbk['book_publisher'] ?: '—'); ?></div>
                                            </div>
                                            <?php if ($wbk['wait_status'] === 'notified'): ?>
                                                <span class="text-[10px] font-black px-2.5 py-1 rounded-full bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 shrink-0 animate-pulse">
                                                    ✨ پیامک ارسال شد (آماده تحویل)
                                                </span>
                                            <?php else: ?>
                                                <span class="text-[10px] font-black px-2.5 py-1 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/40 shrink-0">
                                                    ⏳ در صف (نفر <?php echo toFarsiDigits($wbk['queue_position'] ?: 1); ?>)
                                                </span>
                                            <?php endif; ?>
                                        </div>

                                        <div class="grid grid-cols-2 gap-2 text-[11px] text-slate-300 bg-white/5 p-3 rounded-xl">
                                            <div>موعد تقریبی بازگشت: <strong class="text-amber-300 font-mono"><?php echo htmlspecialchars($wbk['expected_return_date'] ?: 'در دست بررسی'); ?></strong></div>
                                            <div>تاریخ ثبت نوبت: <strong class="text-slate-300 font-mono"><?php echo htmlspecialchars(substr($wbk['wait_date'], 0, 10)); ?></strong></div>
                                        </div>

                                        <div class="pt-2 flex items-center justify-between gap-2 border-t border-white/5">
                                            <?php if ($wbk['book_status'] === 'available'): ?>
                                                <form method="POST" class="flex-1">
                                                    <input type="hidden" name="action" value="request_library_book">
                                                    <input type="hidden" name="book_id" value="<?php echo $wbk['book_id']; ?>">
                                                    <button type="submit" class="w-full py-2 bg-emerald-600 hover:bg-emerald-500 text-white font-bold rounded-xl text-xs transition-colors shadow-md">
                                                        📖 هم‌اکنون امانت بگیرید
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <form method="POST" onsubmit="return confirm('آیا از انصراف از نوبت این کتاب اطمینان دارید؟')" class="<?php echo $wbk['book_status'] === 'available' ? 'shrink-0' : 'w-full'; ?>">
                                                <input type="hidden" name="action" value="leave_book_waitlist">
                                                <input type="hidden" name="waitlist_id" value="<?php echo $wbk['waitlist_id']; ?>">
                                                <input type="hidden" name="book_id" value="<?php echo $wbk['book_id']; ?>">
                                                <button type="submit" class="w-full py-2 bg-white/5 hover:bg-white/10 text-rose-300 hover:text-rose-200 border border-white/10 font-bold rounded-xl text-xs transition-colors text-center">
                                                    ✕ انصراف از صف انتظار
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- 3. Book Requests List (Custom Purchases) -->
                    <div class="glass-panel rounded-[2.5rem] p-8 border border-white/5 space-y-6">
                        <h4 class="text-base font-black text-white flex items-center gap-2">
                            <span>📋</span> سفارش‌های تهیه و خرید کتاب (کتب اختصاصی خارج از مخزن)
                        </h4>

                        <?php if (empty($book_requests)): ?>
                            <div class="text-center py-8 bg-white/5 rounded-3xl border border-dashed border-white/10">
                                <div class="text-3xl mb-2">📝</div>
                                <div class="text-xs font-bold text-slate-300">سفارش خریدی ثبت نکرده‌اید.</div>
                                <p class="text-[11px] text-slate-500 mt-1">چنانچه کتابی در بانک کتاب بالا موجود نبود، با دکمه «ثبت درخواست کتاب جدید» می‌توانید آن را سفارش دهید.</p>
                            </div>
                        <?php else: ?>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <?php foreach ($book_requests as $req): ?>
                                    <?php 
                                    $st = $req['status'];
                                    $stBadge = 'bg-amber-500/15 text-amber-300 border-amber-500/30';
                                    $stText = '🟡 در انتظار بررسی مدیریت و خانم فرتاش';
                                    if ($st === 'in_progress') {
                                        $stBadge = 'bg-blue-500/15 text-blue-300 border-blue-500/30';
                                        $stText = '🔵 در حال تهیه و خریداری';
                                    } elseif ($st === 'fulfilled') {
                                        $stBadge = 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30';
                                        $stText = '🟢 تهیه و تحویل شد';
                                    } elseif ($st === 'rejected') {
                                        $stBadge = 'bg-rose-500/15 text-rose-300 border-rose-500/30';
                                        $stText = '🔴 عدم تایید / ناموجود';
                                    }
                                    ?>
                                    <div class="bg-white/5 hover:bg-white/10 transition-colors p-5 rounded-3xl border border-white/5 space-y-3">
                                        <div class="flex justify-between items-start gap-2">
                                            <h5 class="text-sm font-black text-white"><?php echo htmlspecialchars($req['book_title']); ?></h5>
                                            <span class="text-[10px] font-black px-2.5 py-1 rounded-full border <?php echo $stBadge; ?> shrink-0">
                                                <?php echo $stText; ?>
                                            </span>
                                        </div>
                                        <div class="grid grid-cols-2 gap-2 text-xs text-slate-400">
                                            <div>ناشر / انتشارات: <strong class="text-slate-200"><?php echo htmlspecialchars($req['publisher'] ?: 'نامشخص'); ?></strong></div>
                                            <div>رشته / مقطع: <strong class="text-slate-200"><?php echo htmlspecialchars($req['subject_area'] ?: 'عمومی'); ?></strong></div>
                                            <div>اولویت: 
                                                <?php if ($req['priority'] === 'urgent'): ?>
                                                    <span class="text-rose-400 font-bold">فوری / کنکوری</span>
                                                <?php else: ?>
                                                    <span class="text-slate-300">عادی</span>
                                                <?php endif; ?>
                                            </div>
                                            <div>تاریخ درخواست: <strong class="text-slate-200 font-mono text-[11px]"><?php echo substr($req['created_at'], 0, 10); ?></strong></div>
                                        </div>
                                        <?php if (!empty($req['notes'])): ?>
                                            <div class="text-xs text-slate-300 bg-black/20 p-3 rounded-xl border border-white/5">
                                                <span class="text-slate-500 text-[10px] block mb-0.5">یادداشت دانش‌آموز:</span>
                                                <?php echo htmlspecialchars($req['notes']); ?>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($req['admin_notes'])): ?>
                                            <div class="text-xs text-teal-200 bg-teal-500/10 p-3 rounded-xl border border-teal-500/20">
                                                <span class="text-teal-400 text-[10px] font-bold block mb-0.5">پاسخ مدیریت / خانم فرتاش:</span>
                                                <?php echo htmlspecialchars($req['admin_notes']); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>

                <script>
                    let currentLibCat = 'all';

                    function setLibCategory(cat, btn) {
                        currentLibCat = cat;
                        document.querySelectorAll('#lib-cat-filters .cat-pill').forEach(b => {
                            b.classList.remove('bg-teal-600', 'text-white');
                            b.classList.add('bg-white/5', 'text-slate-400');
                        });
                        btn.classList.add('bg-teal-600', 'text-white');
                        btn.classList.remove('bg-white/5', 'text-slate-400');
                        filterLibraryCatalog();
                    }

                    function filterLibraryCatalog() {
                        const term = (document.getElementById('lib-search-input').value || '').toLowerCase().trim();
                        const items = document.querySelectorAll('.lib-book-item');
                        
                        items.forEach(item => {
                            const title = (item.dataset.title || '').toLowerCase();
                            const cat = (item.dataset.cat || '').toLowerCase();
                            const pub = (item.dataset.publisher || '').toLowerCase();
                            const grade = (item.dataset.grade || '').toLowerCase();
                            const code = (item.dataset.code || '').toLowerCase();

                            const matchesSearch = !term || title.includes(term) || cat.includes(term) || pub.includes(term) || grade.includes(term) || code.includes(term);
                            const matchesCat = (currentLibCat === 'all') || cat.includes(currentLibCat.toLowerCase());

                            if (matchesSearch && matchesCat) {
                                item.style.display = 'flex';
                            } else {
                                item.style.display = 'none';
                            }
                        });
                    }
                </script>

            </div>

        </div>

    </div>

    <!-- New Book Request Modal -->
    <div id="new-book-modal" class="fixed inset-0 z-[100] flex items-center justify-center bg-black/80 backdrop-blur-sm p-4 hidden">
        <div class="bg-slate-900 border border-teal-500/30 w-full max-w-lg rounded-[2.5rem] shadow-2xl p-8 text-white relative">
            <div class="flex justify-between items-center mb-6 border-b border-white/10 pb-4">
                <h3 class="text-lg font-black text-white flex items-center gap-2">
                    <span>📚</span> ثبت درخواست کتاب یا ملزومات درسی
                </h3>
                <button type="button" onclick="document.getElementById('new-book-modal').classList.add('hidden')" class="text-slate-400 hover:text-white text-lg">✕</button>
            </div>

            <form method="POST" action="" class="space-y-4">
                <input type="hidden" name="action" value="request_book">
                
                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">عنوان دقیق کتاب و درس <span class="text-rose-400">*</span></label>
                    <input type="text" name="book_title" required placeholder="مثلاً: زیست‌شناسی جامع خیلی سبز پایه دوازدهم"
                        class="w-full bg-white/5 border border-white/15 text-white rounded-2xl px-4 py-3 text-sm focus:border-teal-400 focus:outline-none transition-all">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">پایه یا رشته تحصیلی</label>
                        <input type="text" name="subject_area" value="<?php echo htmlspecialchars($student['grade'] . ' - ' . ($student['field_of_study'] ?: '')); ?>"
                            class="w-full bg-white/5 border border-white/15 text-white rounded-2xl px-4 py-3 text-sm focus:border-teal-400 focus:outline-none transition-all">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1">انتشارات ترجیحی</label>
                        <input type="text" name="publisher" placeholder="خیلی سبز / گاج / مبتکران / الگو..."
                            class="w-full bg-white/5 border border-white/15 text-white rounded-2xl px-4 py-3 text-sm focus:border-teal-400 focus:outline-none transition-all">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">میزان ضرورت و اولویت</label>
                    <select name="priority" class="w-full bg-slate-800 border border-white/15 text-white rounded-2xl px-4 py-3 text-sm focus:border-teal-400 focus:outline-none">
                        <option value="normal">اولویت عادی (طول ترم تحصیلی)</option>
                        <option value="urgent">اولویت فوری و ضروری (کنکوری / امتحانات نهایی)</option>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1">توضیحات تکمیلی یا آدرس تحویل</label>
                    <textarea name="notes" rows="2" placeholder="اگر نیاز به جلد خاص، ویرایش سال جدید، یا نکته خاصی است اینجا بنویسید..."
                        class="w-full bg-white/5 border border-white/15 text-white rounded-2xl px-4 py-3 text-sm focus:border-teal-400 focus:outline-none transition-all resize-none"></textarea>
                </div>

                <div class="pt-4 flex justify-end gap-3">
                    <button type="button" onclick="document.getElementById('new-book-modal').classList.add('hidden')" class="px-5 py-3 rounded-xl text-xs font-bold text-slate-400 hover:text-white transition-colors">
                        انصراف
                    </button>
                    <button type="submit" class="bg-gradient-to-r from-teal-500 to-teal-700 hover:from-teal-400 hover:to-teal-600 text-white font-black px-6 py-3 rounded-xl text-xs shadow-lg transition-all">
                        ثبت و ارسال به بنیاد
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- JS Handling Tabs and Simulated AI Tutor -->
    <script>
        function changeTab(tabName, shouldScroll = true) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(el => el.classList.add('hidden'));
            // Remove active style from all buttons
            document.querySelectorAll('.tab-btn').forEach(el => {
                el.classList.remove('active', 'bg-teal-600', 'text-white');
                el.classList.add('text-slate-400');
            });

            // Show active tab
            const targetTab = document.getElementById('tab-' + tabName);
            if (targetTab) {
                targetTab.classList.remove('hidden');
                if (shouldScroll) {
                    const navHeight = 90;
                    const y = targetTab.getBoundingClientRect().top + window.pageYOffset - navHeight;
                    window.scrollTo({ top: Math.max(0, y), behavior: 'smooth' });
                }
            }

            // Add active style to active button
            const activeBtn = document.getElementById('btn-' + tabName);
            if (activeBtn) {
                activeBtn.classList.add('active', 'bg-teal-600', 'text-white');
                activeBtn.classList.remove('text-slate-400');
            }

            // Update browser URL query parameter without full reload
            if (window.history && window.history.replaceState) {
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set('tab', tabName);
                window.history.replaceState({}, '', currentUrl.toString());
            }
        }

        function toggleUploadModal(show) {
            const modal = document.getElementById('upload-modal');
            if (show) modal.classList.remove('hidden');
            else modal.classList.add('hidden');
        }

        document.addEventListener('DOMContentLoaded', function() {
            const initTab = '<?php echo $default_active_tab; ?>';
            if (initTab && initTab !== 'dashboard') {
                changeTab(initTab, false);
            }
        });

        // Mock AI Tutor Responses
        const mockResponses = {
            'فرمول شتاب نیوتن چیست؟': `**فرمول شتاب نیوتن (قانون دوم نیوتن):**\n\nطبق قانون دوم نیوتن، شتاب ($a$) یک جسم رابطه مستقیم با نیروی خالص ($F$) وارد بر آن و رابطه معکوس با جرم ($m$) جسم دارد.\n\nفرمول ریاضی آن به این صورت است:\n\n\\[F = m \\cdot a\\]\n\nیا به عبارتی:\n\n\\[a = \\frac{F}{m}\\]\n\n*   **F:** نیروی خالص بر حسب نیوتن ($N$)\n*   **m:** جرم جسم بر حسب کیلوگرم ($kg$)\n*   **a:** شتاب جسم بر حسب متر بر مجذور ثانیه ($m/s^2$)\n\n**مثال:** اگر نیروی ۲۰ نیوتن به جسمی با جرم ۵ کیلوگرم وارد شود، شتاب چقدر است؟\n\\[a = \\frac{20}{5} = 4\\text{ m/s}^2\\]`,
            
            'قوانین فعل ماضی در عربی را توضیح بده': `**قواعد فعل ماضی در زبان عربی:**\n\nفعل ماضی فعلی است که بر انجام کاری در زمان گذشته دلالت دارد. ریشه اصلی فعل ماضی معمولاً ۳ حرفی است (ثلاثی مجرد) مانند **«کَتَبَ»** (نوشت).\n\nفعل ماضی دارای **۱۴ صیغه** است که صیغه اول آن مبنای صرف بقیه است. تقسیم‌بندی صیغه‌ها:\n\n1.  **غائب (مذکر):** کَتَبَ (نوشت)، کَتَبا (نوشتند - ۲نفر)، کَتَبوا (نوشتند - جمع)\n2.  **غائبة (مونث):** کَتَبَتْ، کَتَبَتا، کَتَبْنَ\n3.  **مخاطب (مذکر):** کَتَبْتَ، کَتَبْتُما، کَتَبْتُمْ\n4.  **مخاطبة (مونث):** کَتَبْتِ، کَتَبْتُما، کَتَبْتُنَّ\n5.  **متکلم (گوینده):** کَتَبْتُ (نوشتم - متکلم وحده)، کَتَبْنا (نوشتیم - متکلم مع‌الغیر)\n\n**نکته مهم:** حروف انتهای فعل ماضی نشان‌دهنده فاعل (ضمیر متصل فاعلی) هستند. برای مثال در صیغه «کَتَبْنا»، ضمیر «نا» فاعل کار است.`,
            
            'نحوه حل کردن معادله درجه ۲': `**فرمول عمومی حل معادلات درجه دوم:**\n\nشکل کلی یک معادله درجه دوم به صورت زیر است:\n\n\\[ax^2 + bx + c = 0\\]\n\nکه در آن $a$ و $b$ و $c$ اعداد حقیقی هستند و $a \\neq 0$ است.\n\nبهترین راه حل استفاده از روش **دلتا (\\(\\Delta\\))** است:\n\n\\[\\Delta = b^2 - 4ac\\]\n\nسپس بر اساس مقدار دلتا سه حالت داریم:\n\n1.  **اگر \\(\\Delta > 0\\):** معادله دارای **دو ریشه حقیقی متمایز** است:\n    \\[x_{1,2} = \\frac{-b \\pm \\sqrt{\\Delta}}{2a}\\]\n2.  **اگر \\(\\Delta = 0\\):** معادله دارای **یک ریشه مضاعف** است:\n    \\[x = \\frac{-b}{2a}\\]\n3.  **اگر \\(\\Delta < 0\\):** معادله **ریشه حقیقی ندارد** (ریشه‌ها در اعداد مختلط هستند).`
        };

        function askAI(promptText) {
            const chatBox = document.getElementById('ai-chat-box');
            
            // 1. Add user message
            const userMsgHtml = `
                <div class="flex justify-end items-start gap-3">
                    <div class="flex flex-col items-end gap-1 max-w-[75%]">
                        <div class="bg-teal-700 text-white rounded-2xl rounded-tr-none px-4 py-3 text-sm leading-loose">
                            ${promptText}
                        </div>
                    </div>
                    <div class="w-8 h-8 rounded-full bg-slate-800 text-xs flex items-center justify-center font-bold text-teal-300 border border-teal-500/20 shrink-0">من</div>
                </div>
            `;
            chatBox.insertAdjacentHTML('beforeend', userMsgHtml);
            chatBox.scrollTop = chatBox.scrollHeight;

            // 2. Add AI Typing simulator
            const typingId = 'typing-' + Date.now();
            const aiTypingHtml = `
                <div id="${typingId}" class="flex justify-start items-start gap-3">
                    <div class="w-8 h-8 rounded-full bg-teal-500/15 text-xs flex items-center justify-center text-teal-400 shrink-0">🤖</div>
                    <div class="bg-slate-800 text-slate-400 rounded-2xl rounded-tl-none px-4 py-3 text-sm border border-white/5 animate-pulse">
                        در حال فکر کردن و فرمول‌نویسی...
                    </div>
                </div>
            `;
            chatBox.insertAdjacentHTML('beforeend', aiTypingHtml);
            chatBox.scrollTop = chatBox.scrollHeight;

            // 3. Generate response
            setTimeout(() => {
                const typingEl = document.getElementById(typingId);
                if (typingEl) typingEl.remove();

                const responseText = mockResponses[promptText] || `**پاسخ دستیار علمی:**\n\nسوال شما در مورد: *"${promptText}"* دریافت شد. \n\nاین یک سیستم هوشمند شبیه‌سازی در آکادمی حکمت است. در نسخه نهایی، هوش مصنوعی متصل به کتاب درسی گام به گام فرمول‌ها و مفاهیم ریاضی و تجربی را بر اساس سیستم آموزشی کشور برای شما تشریح خواهد کرد. جهت نمونه سوالات دیگر می‌توانید روی دکمه‌های آماده کلیک کنید.`;
                
                // Format text lines nicely
                const formattedText = responseText.replace(/\n/g, '<br>');

                const aiMsgHtml = `
                    <div class="flex justify-start items-start gap-3 animate-fade-in">
                        <div class="w-8 h-8 rounded-full bg-teal-500/15 text-xs flex items-center justify-center text-teal-400 shrink-0">🤖</div>
                        <div class="flex flex-col items-start gap-1 max-w-[85%]">
                            <div class="bg-slate-800 text-slate-200 rounded-2xl rounded-tl-none px-4 py-3 text-sm leading-loose border border-white/5">
                                ${formattedText}
                            </div>
                        </div>
                    </div>
                `;
                chatBox.insertAdjacentHTML('beforeend', aiMsgHtml);
                chatBox.scrollTop = chatBox.scrollHeight;
            }, 1200);
        }

        function submitAICustom() {
            const input = document.getElementById('ai-user-input');
            const val = input.value.trim();
            if (val) {
                askAI(val);
                input.value = '';
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