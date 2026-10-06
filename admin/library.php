<?php
session_start();
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

// Auth Guard: Superadmin (Mr. Elmi), Secretary (Mrs. Abbasi), Education Deputy (Mrs. Fartash), Board Members (Read-Only), Admin, Data Operator (Ms. Viana)
if (!has_role(['superadmin', 'secretary', 'education_deputy', 'board_member', 'admin', 'data_operator']) && !is_viana()) {
    require_role(['superadmin', 'secretary', 'education_deputy', 'board_member', 'admin', 'data_operator']);
}

$msg = '';
$msg_type = '';
$is_read_only = is_read_only();
$current_user = auth_user();
$current_user_id = $current_user['id'] ?? null;

// Auto-ensure library tables exist to prevent any 500 errors
$pdo->exec("
    CREATE TABLE IF NOT EXISTS book_waitlist (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        book_id INTEGER NOT NULL,
        student_id INTEGER NOT NULL,
        status TEXT DEFAULT 'waiting',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        notified_at DATETIME NULL,
        admin_notes TEXT NULL,
        FOREIGN KEY (book_id) REFERENCES library_books(id),
        FOREIGN KEY (student_id) REFERENCES students(id)
    );
    CREATE INDEX IF NOT EXISTS idx_bw_book ON book_waitlist(book_id);
    CREATE INDEX IF NOT EXISTS idx_bw_student ON book_waitlist(student_id);
    CREATE INDEX IF NOT EXISTS idx_bw_status ON book_waitlist(status);
");

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($is_read_only) {
        $msg = 'اعضای محترم هیئت مدیره دارای دسترسی نظارتی و فقط خواندنی (Read-Only) می‌باشند.';
        $msg_type = 'error';
    } else {
        $action = $_POST['action'];

        // 1. ADD NEW BOOK
        if ($action === 'add_book') {
            $title = trim($_POST['title'] ?? '');
            $grade_level = trim($_POST['grade_level'] ?? '');
            $category = trim($_POST['category'] ?? '');
            $publish_year = trim($_POST['publish_year'] ?? '');
            $publisher = trim($_POST['publisher'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if (!empty($title)) {
                $max_id = $pdo->query("SELECT MAX(id) FROM library_books")->fetchColumn() ?: 0;
                $next_num = $max_id + 1;
                $book_code = sprintf("BK-%03d", $next_num);

                $stmt = $pdo->prepare("
                    INSERT INTO library_books (book_code, title, grade_level, category, publish_year, publisher, description, status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'available')
                ");
                $stmt->execute([$book_code, $title, $grade_level, $category, $publish_year, $publisher, $description]);
                $new_id = $pdo->lastInsertId();

                log_activity('افزودن کتاب به مخزن بنیاد', 'library_books', $new_id, "ثبت کتاب جدید: {$title} ({$book_code})");

                $msg = "کتاب «{$title}» با کد یکتای {$book_code} با موفقیت به مخزن کتابخانه بنیاد افزوده شد.";
                $msg_type = 'success';
            } else {
                $msg = 'عنوان کتاب نمی‌تواند خالی باشد.';
                $msg_type = 'error';
            }
        }

        // 2. EDIT BOOK
        elseif ($action === 'edit_book') {
            $book_id = (int)$_POST['book_id'];
            $title = trim($_POST['title'] ?? '');
            $grade_level = trim($_POST['grade_level'] ?? '');
            $category = trim($_POST['category'] ?? '');
            $publish_year = trim($_POST['publish_year'] ?? '');
            $publisher = trim($_POST['publisher'] ?? '');
            $description = trim($_POST['description'] ?? '');

            if ($book_id && !empty($title)) {
                $stmt = $pdo->prepare("
                    UPDATE library_books 
                    SET title = ?, grade_level = ?, category = ?, publish_year = ?, publisher = ?, description = ?
                    WHERE id = ?
                ");
                $stmt->execute([$title, $grade_level, $category, $publish_year, $publisher, $description, $book_id]);

                log_activity('ویرایش مشخصات کتاب', 'library_books', $book_id, "ویرایش اطلاعات کتاب شناسه {$book_id}");

                $msg = 'مشخصات کتاب با موفقیت به‌روزرسانی گردید.';
                $msg_type = 'success';
            }
        }

        // 3. DELETE BOOK
        elseif ($action === 'delete_book') {
            $book_id = (int)$_POST['book_id'];
            $check = $pdo->prepare("SELECT status, title, book_code FROM library_books WHERE id = ?");
            $check->execute([$book_id]);
            $bk = $check->fetch();

            if ($bk) {
                if ($bk['status'] === 'borrowed') {
                    $msg = 'کتاب در دست امانت است و تا زمان بازگشت به کتابخانه امکان حذف آن وجود ندارد.';
                    $msg_type = 'error';
                } else {
                    $del = $pdo->prepare("DELETE FROM library_books WHERE id = ?");
                    $del->execute([$book_id]);

                    log_activity('حذف کتاب از کتابخانه', 'library_books', $book_id, "حذف کتاب {$bk['title']} ({$bk['book_code']})");

                    $msg = 'کتاب با موفقیت از مخزن حذف گردید.';
                    $msg_type = 'success';
                }
            }
        }

        // 4. LOAN BOOK TO STUDENT (Manual or Approval)
        elseif ($action === 'loan_book') {
            $book_id = (int)$_POST['book_id'];
            $student_id = (int)$_POST['student_id'];
            $expected_return_date = trim($_POST['expected_return_date'] ?? '');
            $admin_notes = trim($_POST['admin_notes'] ?? '');
            
            $today_ts = time();
            $j = gregorian_to_jalali((int)date('Y', $today_ts), (int)date('m', $today_ts), (int)date('d', $today_ts));
            $borrow_date_fa = sprintf('%04d/%02d/%02d', $j[0], $j[1], $j[2]);

            if ($book_id && $student_id) {
                $b_stmt = $pdo->prepare("SELECT title, book_code FROM library_books WHERE id = ?");
                $b_stmt->execute([$book_id]);
                $book_info = $b_stmt->fetch();

                $s_stmt = $pdo->prepare("SELECT name, surname FROM students WHERE id = ?");
                $s_stmt->execute([$student_id]);
                $student_info = $s_stmt->fetch();

                if ($book_info && $student_info) {
                    $upd = $pdo->prepare("
                        UPDATE library_books 
                        SET status = 'borrowed', current_borrower_id = ?, borrowed_at = ?, expected_return_date = ?, admin_notes = ?
                        WHERE id = ?
                    ");
                    $upd->execute([$student_id, $borrow_date_fa, $expected_return_date, $admin_notes, $book_id]);

                    $log_stmt = $pdo->prepare("
                        INSERT INTO book_borrow_logs (book_id, student_id, action, action_by_user_id, action_date, notes)
                        VALUES (?, ?, 'approve_borrow', ?, ?, ?)
                    ");
                    $log_stmt->execute([$book_id, $student_id, $current_user_id, $borrow_date_fa, $admin_notes]);

                    $req_upd = $pdo->prepare("
                        UPDATE student_book_requests 
                        SET status = 'fulfilled', admin_notes = ?, updated_at = CURRENT_TIMESTAMP 
                        WHERE student_id = ? AND (book_title LIKE ? OR ? LIKE '%' || book_title || '%') AND status IN ('pending', 'in_progress')
                    ");
                    $req_upd->execute(["کتاب فیزیکی ({$book_info['book_code']}) در تاریخ {$borrow_date_fa} امانت داده شد.", $student_id, '%' . $book_info['title'] . '%', $book_info['title']]);

                    $full_sname = $student_info['name'] . ' ' . $student_info['surname'];
                    log_activity('ثبت امانت کتاب به دانش‌آموز', 'library_books', $book_id, "تحویل کتاب {$book_info['title']} ({$book_info['book_code']}) به {$full_sname}");

                    $msg = "کتاب «{$book_info['title']}» با موفقیت به دانش‌آموز {$full_sname} تحویل و وضعیت آن به «در امانت» تغییر یافت.";
                    $msg_type = 'success';
                }
            } else {
                $msg = 'لطفاً کتاب و دانش‌آموز را مشخص فرمایید.';
                $msg_type = 'error';
            }
        }

        // 5. RETURN BOOK
        elseif ($action === 'return_book') {
            $book_id = (int)$_POST['book_id'];
            $return_notes = trim($_POST['return_notes'] ?? '');

            $today_ts = time();
            $j = gregorian_to_jalali((int)date('Y', $today_ts), (int)date('m', $today_ts), (int)date('d', $today_ts));
            $return_date_fa = sprintf('%04d/%02d/%02d', $j[0], $j[1], $j[2]);

            $b_stmt = $pdo->prepare("SELECT b.*, s.name as s_name, s.surname as s_surname FROM library_books b LEFT JOIN students s ON b.current_borrower_id = s.id WHERE b.id = ?");
            $b_stmt->execute([$book_id]);
            $bk = $b_stmt->fetch();

            if ($bk) {
                $prev_borrower_id = $bk['current_borrower_id'];
                $s_name = ($bk['s_name'] ?? '') . ' ' . ($bk['s_surname'] ?? '');

                $upd = $pdo->prepare("
                    UPDATE library_books 
                    SET status = 'available', current_borrower_id = NULL, borrowed_at = NULL, expected_return_date = NULL, admin_notes = ?
                    WHERE id = ?
                ");
                $upd->execute([$return_notes, $book_id]);

                if ($prev_borrower_id) {
                    $log_stmt = $pdo->prepare("
                        INSERT INTO book_borrow_logs (book_id, student_id, action, action_by_user_id, action_date, notes)
                        VALUES (?, ?, 'return', ?, ?, ?)
                    ");
                    $log_stmt->execute([$book_id, $prev_borrower_id, $current_user_id, $return_date_fa, $return_notes]);
                }

                log_activity('ثبت بازگشت کتاب به بنیاد', 'library_books', $book_id, "بازگشت کتاب {$bk['title']} ({$bk['book_code']}) از {$s_name}");

                $msg = "کتاب «{$bk['title']}» با موفقیت دریافت و وضعیت آن به «موجود در کتابخانه بنیاد» بازگشت.";
                $msg_type = 'success';

                // بررسی صف انتظار و ارسال خودکار پیامک به نفر اول در نوبت
                $w_stmt = $pdo->prepare("
                    SELECT w.*, s.name as s_name, s.surname as s_surname, s.phone as s_phone, s.guardian_phone as s_gphone 
                    FROM book_waitlist w 
                    JOIN students s ON w.student_id = s.id 
                    WHERE w.book_id = ? AND w.status = 'waiting' 
                    ORDER BY w.id ASC LIMIT 1
                ");
                $w_stmt->execute([$book_id]);
                $next_student = $w_stmt->fetch(PDO::FETCH_ASSOC);

                if ($next_student) {
                    require_once __DIR__ . '/../includes/SmsService.php';
                    $sms_service = new SmsService($pdo);
                    $st_phone = !empty($next_student['s_phone']) ? $next_student['s_phone'] : $next_student['s_gphone'];
                    $st_name = trim($next_student['s_name'] . ' ' . $next_student['s_surname']);
                    
                    if (!empty($st_phone)) {
                        $sms_msg = "دانش‌آموز گرامی {$next_student['s_name']} عزیز،\nکتاب «{$bk['title']}» ({$bk['book_code']}) که در صف انتظار آن بودید به مخزن کتابخانه بنیاد حکمت عودت داده شد و اکنون آماده امانت است.\nجهت ثبت درخواست امانت به پنل خود مراجعه فرمایید:\nhekmatfoundation.org";
                        $sms_res = $sms_service->sendRawSms($st_phone, $sms_msg, null, $current_user_id);
                        
                        $upd_w = $pdo->prepare("UPDATE book_waitlist SET status = 'notified', notified_at = CURRENT_TIMESTAMP WHERE id = ?");
                        $upd_w->execute([$next_student['id']]);
                        
                        $log_w = $pdo->prepare("
                            INSERT INTO book_borrow_logs (book_id, student_id, action, action_by_user_id, action_date, notes)
                            VALUES (?, ?, 'notify_waitlist', ?, ?, ?)
                        ");
                        $log_w->execute([$book_id, $next_student['student_id'], $current_user_id, $return_date_fa, "ارسال خودکار پیامک اطلاع‌رسانی بازگشت کتاب به شماره {$st_phone}"]);
                        
                        $msg .= "<br>📱 <strong>پیامک اطلاع‌رسانی بازگشت کتاب برای دانش‌آموز در نوبت «{$st_name}» ({$st_phone}) با موفقیت ارسال شد.</strong>";
                    } else {
                        $msg .= "<br>⚠️ دانش‌آموز «{$st_name}» در صف انتظار این کتاب است، اما شماره تلفنی برای ایشان ثبت نشده است.";
                    }
                }
            }
        }

        // 6. REJECT LOAN REQUEST
        elseif ($action === 'reject_request') {
            $book_id = (int)$_POST['book_id'];
            $student_id = (int)$_POST['student_id'];
            $reason = trim($_POST['reason'] ?? '');

            $today_ts = time();
            $j = gregorian_to_jalali((int)date('Y', $today_ts), (int)date('m', $today_ts), (int)date('d', $today_ts));
            $reject_date_fa = sprintf('%04d/%02d/%02d', $j[0], $j[1], $j[2]);

            $b_stmt = $pdo->prepare("SELECT title, book_code FROM library_books WHERE id = ?");
            $b_stmt->execute([$book_id]);
            $bk = $b_stmt->fetch();

            $upd = $pdo->prepare("UPDATE library_books SET status = 'available', current_borrower_id = NULL WHERE id = ? AND status = 'requested'");
            $upd->execute([$book_id]);

            $log_stmt = $pdo->prepare("
                INSERT INTO book_borrow_logs (book_id, student_id, action, action_by_user_id, action_date, notes)
                VALUES (?, ?, 'reject', ?, ?, ?)
            ");
            $log_stmt->execute([$book_id, $student_id, $current_user_id, $reject_date_fa, $reason]);

            $msg = 'درخواست امانت کتاب رد شد و کتاب در وضعیت موجود قرار گرفت.';
            $msg_type = 'success';

            // بررسی صف انتظار و ارسال خودکار پیامک به نفر اول در نوبت
            $w_stmt = $pdo->prepare("
                SELECT w.*, s.name as s_name, s.surname as s_surname, s.phone as s_phone, s.guardian_phone as s_gphone 
                FROM book_waitlist w 
                JOIN students s ON w.student_id = s.id 
                WHERE w.book_id = ? AND w.status = 'waiting' 
                ORDER BY w.id ASC LIMIT 1
            ");
            $w_stmt->execute([$book_id]);
            $next_student = $w_stmt->fetch(PDO::FETCH_ASSOC);

            if ($next_student && $bk) {
                require_once __DIR__ . '/../includes/SmsService.php';
                $sms_service = new SmsService($pdo);
                $st_phone = !empty($next_student['s_phone']) ? $next_student['s_phone'] : $next_student['s_gphone'];
                $st_name = trim($next_student['s_name'] . ' ' . $next_student['s_surname']);
                
                if (!empty($st_phone)) {
                    $sms_msg = "دانش‌آموز گرامی {$next_student['s_name']} عزیز،\nکتاب «{$bk['title']}» ({$bk['book_code']}) در کتابخانه بنیاد حکمت آماده امانت است.\nجهت ثبت درخواست امانت به پنل خود مراجعه فرمایید:\nhekmatfoundation.org";
                    $sms_service->sendRawSms($st_phone, $sms_msg, null, $current_user_id);
                    
                    $pdo->prepare("UPDATE book_waitlist SET status = 'notified', notified_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$next_student['id']]);
                    $pdo->prepare("INSERT INTO book_borrow_logs (book_id, student_id, action, action_by_user_id, action_date, notes) VALUES (?, ?, 'notify_waitlist', ?, ?, ?)")
                        ->execute([$book_id, $next_student['student_id'], $current_user_id, $reject_date_fa, "ارسال پیامک اطلاع‌رسانی پس از رد درخواست قبلی به شماره {$st_phone}"]);
                    
                    $msg .= "<br>📱 <strong>پیامک اطلاع‌رسانی برای دانش‌آموز در نوبت «{$st_name}» ({$st_phone}) ارسال شد.</strong>";
                }
            }
        }

        // 7. NOTIFY WAITLIST STUDENT MANUALLY
        elseif ($action === 'notify_waitlist_manual') {
            $waitlist_id = (int)$_POST['waitlist_id'];
            $w_stmt = $pdo->prepare("
                SELECT w.*, b.title as book_title, b.book_code, s.name as student_name, s.surname as student_surname, s.phone, s.guardian_phone 
                FROM book_waitlist w 
                JOIN library_books b ON w.book_id = b.id 
                JOIN students s ON w.student_id = s.id 
                WHERE w.id = ?
            ");
            $w_stmt->execute([$waitlist_id]);
            $witem = $w_stmt->fetch(PDO::FETCH_ASSOC);
            if ($witem) {
                $phone = !empty($witem['phone']) ? $witem['phone'] : $witem['guardian_phone'];
                if (!empty($phone)) {
                    require_once __DIR__ . '/../includes/SmsService.php';
                    $sms_svc = new SmsService($pdo);
                    $sms_msg = "دانش‌آموز گرامی {$witem['student_name']} عزیز،\nکتاب «{$witem['book_title']}» ({$witem['book_code']}) در کتابخانه بنیاد حکمت آماده امانت است.\nجهت ثبت درخواست امانت به پنل خود مراجعه فرمایید:\nhekmatfoundation.org";
                    $sms_svc->sendRawSms($phone, $sms_msg, null, $current_user_id);
                    
                    $pdo->prepare("UPDATE book_waitlist SET status = 'notified', notified_at = CURRENT_TIMESTAMP WHERE id = ?")->execute([$waitlist_id]);
                    
                    $today_ts = time();
                    $j = gregorian_to_jalali((int)date('Y', $today_ts), (int)date('m', $today_ts), (int)date('d', $today_ts));
                    $today_fa = sprintf('%04d/%02d/%02d', $j[0], $j[1], $j[2]);
                    
                    $pdo->prepare("INSERT INTO book_borrow_logs (book_id, student_id, action, action_by_user_id, action_date, notes) VALUES (?, ?, 'notify_waitlist', ?, ?, ?)")
                        ->execute([$witem['book_id'], $witem['student_id'], $current_user_id, $today_fa, "ارسال دستی پیامک اطلاع‌رسانی به شماره {$phone}"]);
                    
                    $msg = "پیامک اطلاع‌رسانی برای دانش‌آموز {$witem['student_name']} {$witem['student_surname']} با موفقیت ارسال گردید.";
                    $msg_type = 'success';
                } else {
                    $msg = 'شماره تلفن معتبری برای این دانش‌آموز ثبت نشده است.';
                    $msg_type = 'error';
                }
            }
        }

        // 8. REMOVE FROM WAITLIST
        elseif ($action === 'remove_from_waitlist') {
            $waitlist_id = (int)$_POST['waitlist_id'];
            $pdo->prepare("UPDATE book_waitlist SET status = 'cancelled' WHERE id = ?")->execute([$waitlist_id]);
            $msg = 'نوبت دانش‌آموز از صف انتظار لغو گردید.';
            $msg_type = 'info';
        }
    }
}

// Fetch Filters
$search = trim($_GET['search'] ?? '');
$filter_grade = trim($_GET['grade'] ?? 'all');
$filter_category = trim($_GET['category'] ?? 'all');
$filter_status = trim($_GET['status'] ?? 'all');
$active_tab = $_GET['tab'] ?? 'inventory';

// Build Query for Books Inventory
$sql = "
    SELECT b.*, s.name as borrower_name, s.surname as borrower_surname, s.phone as borrower_phone, s.guardian_phone
    FROM library_books b
    LEFT JOIN students s ON b.current_borrower_id = s.id
    WHERE 1=1
";
$params = [];

if ($filter_status !== 'all') {
    $sql .= " AND b.status = ?";
    $params[] = $filter_status;
}

if ($filter_grade !== 'all') {
    $sql .= " AND b.grade_level LIKE ?";
    $params[] = "%{$filter_grade}%";
}

if ($filter_category !== 'all') {
    $sql .= " AND b.category = ?";
    $params[] = $filter_category;
}

if (!empty($search)) {
    $sql .= " AND (b.title LIKE ? OR b.publisher LIKE ? OR b.book_code LIKE ? OR b.description LIKE ?)";
    $s_term = "%{$search}%";
    $params[] = $s_term;
    $params[] = $s_term;
    $params[] = $s_term;
    $params[] = $s_term;
}

$sql .= " ORDER BY b.id ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$books = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Counts & Stats
$total_books = $pdo->query("SELECT COUNT(*) FROM library_books")->fetchColumn() ?: 0;
$available_books = $pdo->query("SELECT COUNT(*) FROM library_books WHERE status = 'available'")->fetchColumn() ?: 0;
$borrowed_books = $pdo->query("SELECT COUNT(*) FROM library_books WHERE status = 'borrowed'")->fetchColumn() ?: 0;
$requested_books = $pdo->query("SELECT COUNT(*) FROM library_books WHERE status = 'requested'")->fetchColumn() ?: 0;

// Fetch Currently Borrowed Books
$borrowed_list = $pdo->query("
    SELECT b.*, s.name as borrower_name, s.surname as borrower_surname, s.grade as borrower_grade, s.phone as borrower_phone, s.guardian_phone, s.school
    FROM library_books b
    JOIN students s ON b.current_borrower_id = s.id
    WHERE b.status = 'borrowed'
    ORDER BY b.borrowed_at DESC, b.id ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Pending Loan Requests
$pending_requests = $pdo->query("
    SELECT b.*, s.name as student_name, s.surname as student_surname, s.grade as student_grade, s.phone as student_phone, s.guardian_phone, s.school, l.action_date as request_date
    FROM library_books b
    JOIN students s ON b.current_borrower_id = s.id
    LEFT JOIN (
        SELECT book_id, student_id, MAX(action_date) as action_date 
        FROM book_borrow_logs 
        WHERE action = 'request' 
        GROUP BY book_id, student_id
    ) l ON l.book_id = b.id AND l.student_id = s.id
    WHERE b.status = 'requested'
    ORDER BY b.id DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Borrow Logs
$logs = $pdo->query("
    SELECT l.*, b.title as book_title, b.book_code, s.name as student_name, s.surname as student_surname, COALESCE(u.full_name, u.username) as actor_name
    FROM book_borrow_logs l
    LEFT JOIN library_books b ON l.book_id = b.id
    LEFT JOIN students s ON l.student_id = s.id
    LEFT JOIN users u ON l.action_by_user_id = u.id
    ORDER BY l.id DESC
    LIMIT 100
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch All Students for Manual Loan Dropdown
$all_students = $pdo->query("
    SELECT id, name, surname, grade, phone 
    FROM students 
    WHERE status = 'active' 
    ORDER BY name ASC, surname ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Fetch Active Waitlists (Queue and Notifications)
$waitlist_items = $pdo->query("
    SELECT w.*, b.book_code, b.title as book_title, b.status as book_status, b.expected_return_date,
           s.name as student_name, s.surname as student_surname, s.grade as student_grade, s.phone as student_phone, s.guardian_phone, s.school,
           (SELECT COUNT(*) FROM book_waitlist w2 WHERE w2.book_id = w.book_id AND w2.status = 'waiting' AND w2.id <= w.id) as queue_position
    FROM book_waitlist w
    JOIN library_books b ON w.book_id = b.id
    JOIN students s ON w.student_id = s.id
    WHERE w.status IN ('waiting', 'notified')
    ORDER BY w.status DESC, w.id ASC
")->fetchAll(PDO::FETCH_ASSOC);

$total_waiting = count(array_filter($waitlist_items, fn($w) => $w['status'] === 'waiting'));

// Map book_id => waitlist count
$book_waitlist_counts = [];
foreach ($waitlist_items as $wi) {
    if ($wi['status'] === 'waiting') {
        $book_waitlist_counts[$wi['book_id']] = ($book_waitlist_counts[$wi['book_id']] ?? 0) + 1;
    }
}

$categories_list = [
    'زیست‌شناسی', 'فیزیک', 'شیمی', 'ریاضی و حسابان', 
    'ادبیات و فارسی', 'عربی', 'دین و زندگی', 'زبان انگلیسی', 
    'جامع و آزمون', 'علوم تجربی', 'مطالعات اجتماعی', 'روانشناسی', 'تاریخ', 'جغرافیا', 'جامعه‌شناسی', 'اقتصاد', 'سایر کتب'
];

$grades_list = ['دهم', 'یازدهم', 'دوازدهم', 'نهم', 'هشتم', 'کنکور'];
?>
<!DOCTYPE html>
<html lang="fa-IR" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>سامانه جامع بانک کتاب و مدیریت امانات | بنیاد نیکوکاری حکمت</title>
    <style>
        @font-face{font-family:'Vazirmatn';font-style:normal;font-weight:100;font-display:swap;src:url('/assets/fonts/Vazirmatn-100.woff2') format('woff2')}
        @font-face{font-family:'Vazirmatn';font-style:normal;font-weight:300;font-display:swap;src:url('/assets/fonts/Vazirmatn-300.woff2') format('woff2')}
        @font-face{font-family:'Vazirmatn';font-style:normal;font-weight:400;font-display:swap;src:url('/assets/fonts/Vazirmatn-400.woff2') format('woff2')}
        @font-face{font-family:'Vazirmatn';font-style:normal;font-weight:500;font-display:swap;src:url('/assets/fonts/Vazirmatn-500.woff2') format('woff2')}
        @font-face{font-family:'Vazirmatn';font-style:normal;font-weight:700;font-display:swap;src:url('/assets/fonts/Vazirmatn-700.woff2') format('woff2')}
        @font-face{font-family:'Vazirmatn';font-style:normal;font-weight:900;font-display:swap;src:url('/assets/fonts/Vazirmatn-900.woff2') format('woff2')}
        body { font-family: 'Vazirmatn', sans-serif; }
    </style>
    <link rel="stylesheet" href="/assets/tailwind.min.css">
</head>
<body class="bg-slate-950 text-slate-100 min-h-screen">

    <!-- Header Navigation -->
    <header class="bg-slate-900/90 border-b border-white/10 sticky top-0 z-40 backdrop-blur-md">
        <div class="container mx-auto px-4 py-4 flex flex-col md:flex-row justify-between items-center gap-4">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 bg-teal-500/20 text-teal-400 border border-teal-500/30 rounded-2xl flex items-center justify-center text-2xl shadow-lg shadow-teal-500/10">
                    📚
                </div>
                <div>
                    <h1 class="text-base sm:text-lg font-black text-white flex items-center gap-2">
                        بانک کتاب و سامانه امانات بنیاد حکمت
                        <span class="bg-teal-500/20 text-teal-300 text-[10px] font-bold px-2.5 py-0.5 rounded-full border border-teal-500/30">
                            نسخه هوشمند
                        </span>
                    </h1>
                    <p class="text-[11px] text-teal-400">
                        معاونت آموزش (خانم فرتاش) • امور اداری و اجرایی (خانم عباسی) • مدیریت عامل (آقای علمی)
                    </p>
                </div>
            </div>
            
            <div class="flex items-center gap-2 sm:gap-3 flex-wrap justify-end">
                <?php if (!$is_read_only): ?>
                    <button onclick="document.getElementById('add-book-modal').classList.remove('hidden')" class="bg-gradient-to-r from-teal-500 to-teal-700 hover:from-teal-400 hover:to-teal-600 text-white font-bold px-4 py-2 rounded-xl text-xs flex items-center gap-1.5 shadow-lg shadow-teal-500/20 transition-all">
                        <span>+</span> افزودن کتاب جدید به مخزن
                    </button>
                    <button onclick="document.getElementById('manual-loan-modal').classList.remove('hidden')" class="bg-gradient-to-r from-blue-600 to-indigo-600 hover:from-blue-500 hover:to-indigo-500 text-white font-bold px-4 py-2 rounded-xl text-xs flex items-center gap-1.5 shadow-lg shadow-blue-500/20 transition-all">
                        <span>🤝</span> ثبت امانت فیزیکی
                    </button>
                <?php else: ?>
                    <span class="bg-amber-500/15 text-amber-300 border border-amber-500/30 text-xs px-3 py-1.5 rounded-xl font-bold">
                        👁️ حالت نظارتی هیئت مدیره (فقط خواندنی)
                    </span>
                <?php endif; ?>
                
                <a href="book-requests.php" class="bg-white/5 hover:bg-white/10 text-slate-300 hover:text-white border border-white/10 text-xs font-bold px-3 py-2 rounded-xl transition-all flex items-center gap-1.5">
                    <span>📚</span> درخواست‌های خرید کتاب
                </a>

                <?php if (is_viana() || ($_SESSION['role'] ?? '') === 'data_operator' || strtolower($_SESSION['username'] ?? '') === 'viana'): ?>
                    <a href="../switch-role.php?target=student" class="bg-indigo-600/30 hover:bg-indigo-600 text-indigo-200 hover:text-white border border-indigo-500/40 text-xs font-bold px-3.5 py-2 rounded-xl transition-all flex items-center gap-1.5 shadow-md">
                        <span>🎒</span> پورتال دانش‌آموزی ویانا (امانت برای خود)
                    </a>
                <?php endif; ?>
                
                <?php if (($_SESSION['role'] ?? '') !== 'data_operator'): ?>
                    <a href="index.php" class="bg-white/5 hover:bg-white/10 text-slate-300 hover:text-white border border-white/10 text-xs font-bold px-3.5 py-2 rounded-xl transition-all">
                        ← میز کار مدیریت
                    </a>
                <?php else: ?>
                    <a href="../admin-logout.php" class="bg-rose-500/20 hover:bg-rose-500 text-rose-300 hover:text-white border border-rose-500/30 text-xs font-bold px-3 py-2 rounded-xl transition-all">
                        خروج
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <main class="container mx-auto px-4 py-8 max-w-7xl space-y-8">

        <?php if (is_viana() || ($_SESSION['role'] ?? '') === 'data_operator' || strtolower($_SESSION['username'] ?? '') === 'viana'): ?>
            <div class="glass-panel p-5 rounded-[2rem] border border-indigo-500/30 bg-gradient-to-r from-indigo-950/40 via-slate-900/60 to-slate-900/80 flex flex-col sm:flex-row items-center justify-between gap-4 shadow-xl">
                <div class="flex items-center gap-3 text-right">
                    <span class="text-3xl">🎒</span>
                    <div>
                        <h4 class="text-xs font-black text-white">ویانا وحیدی گرامی (اپراتور دیتابیس و دانش‌آموز تحت پوشش بنیاد)</h4>
                        <p class="text-[11px] text-slate-300 mt-0.5">شما می‌توانید از این پنل امانات و نوبت‌های همه دانش‌آموزان را مدیریت کنید، یا به پورتال دانش‌آموزی خود رفته و برای خود کتاب امانت بگیرید.</p>
                    </div>
                </div>
                <a href="../switch-role.php?target=student" class="bg-indigo-600 hover:bg-indigo-500 text-white font-black px-4 py-2.5 rounded-xl text-xs transition-all shadow-md shrink-0 flex items-center gap-2">
                    <span>📖</span> ورود به پورتال دانش‌آموزی ویانا ←
                </a>
            </div>
        <?php endif; ?>

        <!-- Alerts -->
        <?php if ($msg): ?>
            <div class="p-4 rounded-2xl border text-sm font-bold text-center <?php echo $msg_type === 'success' ? 'bg-emerald-500/15 border-emerald-500/30 text-emerald-300' : 'bg-rose-500/15 border-rose-500/30 text-rose-300'; ?>">
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <!-- Stats Overview Cards -->
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
            <div class="bg-slate-900/80 border border-white/5 p-6 rounded-3xl relative overflow-hidden shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-xs text-slate-400 font-bold mb-1">کل کتب و جزوات بنیاد</div>
                        <div class="text-3xl font-black text-white font-mono"><?php echo toFarsiDigits($total_books); ?> <span class="text-xs font-normal text-slate-400">جلد</span></div>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-teal-500/15 border border-teal-500/30 flex items-center justify-center text-2xl text-teal-400">
                        📖
                    </div>
                </div>
                <div class="text-[10px] text-teal-400 font-bold mt-3">استخراج کامل از اسناد دست‌نویس بنیاد</div>
            </div>

            <div class="bg-emerald-500/10 border border-emerald-500/20 p-6 rounded-3xl relative overflow-hidden shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-xs text-emerald-300 font-bold mb-1">موجود در مخزن کتابخانه</div>
                        <div class="text-3xl font-black text-emerald-400 font-mono"><?php echo toFarsiDigits($available_books); ?> <span class="text-xs font-normal text-emerald-300">جلد</span></div>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-emerald-500/20 border border-emerald-500/40 flex items-center justify-center text-2xl text-emerald-400">
                        🟢
                    </div>
                </div>
                <div class="text-[10px] text-emerald-300 font-bold mt-3">آماده تخصیص و تحویل به دانش‌آموزان</div>
            </div>

            <div class="bg-blue-500/10 border border-blue-500/20 p-6 rounded-3xl relative overflow-hidden shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-xs text-blue-300 font-bold mb-1">کتاب‌های در دست امانت</div>
                        <div class="text-3xl font-black text-blue-400 font-mono"><?php echo toFarsiDigits($borrowed_books); ?> <span class="text-xs font-normal text-blue-300">جلد</span></div>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-blue-500/20 border border-blue-500/40 flex items-center justify-center text-2xl text-blue-400">
                        🤝
                    </div>
                </div>
                <div class="text-[10px] text-blue-300 font-bold mt-3">در اختیار دانش‌پژوهان تحت پوشش</div>
            </div>

            <div class="bg-amber-500/10 border border-amber-500/20 p-6 rounded-3xl relative overflow-hidden shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-xs text-amber-300 font-bold mb-1">درخواست‌های جدید امانت</div>
                        <div class="text-3xl font-black text-amber-400 font-mono"><?php echo toFarsiDigits($requested_books); ?> <span class="text-xs font-normal text-amber-300">مورد</span></div>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-amber-500/20 border border-amber-500/40 flex items-center justify-center text-2xl text-amber-400">
                        📥
                    </div>
                </div>
                <div class="text-[10px] text-amber-300 font-bold mt-3">بررسی خانم فرتاش یا عباسی</div>
            </div>

            <div class="bg-indigo-500/10 border border-indigo-500/20 p-6 rounded-3xl relative overflow-hidden shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <div class="text-xs text-indigo-300 font-bold mb-1">صف انتظار و نوبت‌ها</div>
                        <div class="text-3xl font-black text-indigo-400 font-mono"><?php echo toFarsiDigits($total_waiting); ?> <span class="text-xs font-normal text-indigo-300">نفر</span></div>
                    </div>
                    <div class="w-12 h-12 rounded-2xl bg-indigo-500/20 border border-indigo-500/40 flex items-center justify-center text-2xl text-indigo-400">
                        🔔
                    </div>
                </div>
                <div class="text-[10px] text-indigo-300 font-bold mt-3">ارسال خودکار پیامک هنگام عودت</div>
            </div>
        </div>

        <!-- Tab Switching Buttons -->
        <div class="flex items-center gap-2 border-b border-white/10 pb-4 overflow-x-auto text-xs font-black">
            <button onclick="switchTab('inventory')" id="tab-btn-inventory" class="tab-button px-5 py-3 rounded-2xl bg-teal-600 text-white transition-all flex items-center gap-2 shrink-0 shadow-md">
                <span>📚</span> مخزن و قفسه کتب بنیاد (<?php echo toFarsiDigits($total_books); ?>)
            </button>
            <button onclick="switchTab('requests')" id="tab-btn-requests" class="tab-button px-5 py-3 rounded-2xl bg-slate-900 text-slate-400 hover:text-white transition-all flex items-center gap-2 shrink-0 border border-white/5">
                <span>📥</span> کارتابل درخواست‌های جدید 
                <?php if ($requested_books > 0): ?>
                    <span class="bg-amber-500 text-slate-950 font-black px-2 py-0.5 rounded-full text-[10px]"><?php echo $requested_books; ?></span>
                <?php endif; ?>
            </button>
            <button onclick="switchTab('borrowed')" id="tab-btn-borrowed" class="tab-button px-5 py-3 rounded-2xl bg-slate-900 text-slate-400 hover:text-white transition-all flex items-center gap-2 shrink-0 border border-white/5">
                <span>🤝</span> کتاب‌های در دست امانت (<?php echo toFarsiDigits($borrowed_books); ?>)
            </button>
            <button onclick="switchTab('waitlist')" id="tab-btn-waitlist" class="tab-button px-5 py-3 rounded-2xl bg-slate-900 text-slate-400 hover:text-white transition-all flex items-center gap-2 shrink-0 border border-white/5">
                <span>🔔</span> صف‌های انتظار و رزرو
                <?php if ($total_waiting > 0): ?>
                    <span class="bg-indigo-500 text-white font-black px-2 py-0.5 rounded-full text-[10px]"><?php echo $total_waiting; ?></span>
                <?php endif; ?>
            </button>
            <button onclick="switchTab('logs')" id="tab-btn-logs" class="tab-button px-5 py-3 rounded-2xl bg-slate-900 text-slate-400 hover:text-white transition-all flex items-center gap-2 shrink-0 border border-white/5">
                <span>📜</span> دفترچه سوابق و تاریخچه امانات
            </button>
        </div>

        <!-- ================= TAB 1: INVENTORY ================= -->
        <div id="tab-inventory" class="tab-panel space-y-6">
            
            <!-- Filters Bar -->
            <form method="GET" class="bg-slate-900/90 border border-white/10 p-5 rounded-3xl space-y-4 shadow-xl">
                <input type="hidden" name="tab" value="inventory">
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-3">
                    <!-- Search -->
                    <div class="md:col-span-2">
                        <label class="block text-[11px] font-bold text-slate-400 mb-1">جستجوی عنوان، ناشر، استاد، یا کد کتاب:</label>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="مثلاً: خیلی سبز، سه‌سطحی، استاد مرادی، BK-015..." class="w-full bg-slate-950 border border-white/15 rounded-xl px-4 py-2.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-teal-500">
                    </div>

                    <!-- Filter Category -->
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 mb-1">دسته‌بندی درس:</label>
                        <select name="category" class="w-full bg-slate-950 border border-white/15 rounded-xl px-3 py-2.5 text-xs text-white focus:outline-none focus:border-teal-500">
                            <option value="all">همه دسته‌ها</option>
                            <?php foreach ($categories_list as $c): ?>
                                <option value="<?php echo $c; ?>" <?php echo $filter_category === $c ? 'selected' : ''; ?>><?php echo $c; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Filter Grade -->
                    <div>
                        <label class="block text-[11px] font-bold text-slate-400 mb-1">پایه تحصیلی:</label>
                        <select name="grade" class="w-full bg-slate-950 border border-white/15 rounded-xl px-3 py-2.5 text-xs text-white focus:outline-none focus:border-teal-500">
                            <option value="all">همه پایه‌ها</option>
                            <?php foreach ($grades_list as $g): ?>
                                <option value="<?php echo $g; ?>" <?php echo $filter_grade === $g ? 'selected' : ''; ?>><?php echo $g; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-white/5">
                    <!-- Status Filter Badges -->
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <span class="text-slate-400 text-[11px] font-bold">وضعیت کتاب:</span>
                        <a href="?tab=inventory&status=all&search=<?php echo urlencode($search); ?>&grade=<?php echo urlencode($filter_grade); ?>&category=<?php echo urlencode($filter_category); ?>" class="px-3 py-1 rounded-xl text-[11px] font-bold transition-all <?php echo $filter_status === 'all' ? 'bg-teal-500 text-slate-950' : 'bg-white/5 text-slate-400 hover:text-white'; ?>">همه (<?php echo $total_books; ?>)</a>
                        <a href="?tab=inventory&status=available&search=<?php echo urlencode($search); ?>&grade=<?php echo urlencode($filter_grade); ?>&category=<?php echo urlencode($filter_category); ?>" class="px-3 py-1 rounded-xl text-[11px] font-bold transition-all <?php echo $filter_status === 'available' ? 'bg-emerald-500 text-slate-950' : 'bg-emerald-500/10 text-emerald-300 hover:bg-emerald-500/20'; ?>">موجود در مخزن (<?php echo $available_books; ?>)</a>
                        <a href="?tab=inventory&status=borrowed&search=<?php echo urlencode($search); ?>&grade=<?php echo urlencode($filter_grade); ?>&category=<?php echo urlencode($filter_category); ?>" class="px-3 py-1 rounded-xl text-[11px] font-bold transition-all <?php echo $filter_status === 'borrowed' ? 'bg-blue-500 text-slate-950' : 'bg-blue-500/10 text-blue-300 hover:bg-blue-500/20'; ?>">در امانت (<?php echo $borrowed_books; ?>)</a>
                        <a href="?tab=inventory&status=requested&search=<?php echo urlencode($search); ?>&grade=<?php echo urlencode($filter_grade); ?>&category=<?php echo urlencode($filter_category); ?>" class="px-3 py-1 rounded-xl text-[11px] font-bold transition-all <?php echo $filter_status === 'requested' ? 'bg-amber-500 text-slate-950' : 'bg-amber-500/10 text-amber-300 hover:bg-amber-500/20'; ?>">درخواست‌شده (<?php echo $requested_books; ?>)</a>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="submit" class="bg-teal-600 hover:bg-teal-500 text-white font-bold px-5 py-2 rounded-xl text-xs transition-colors">
                            اعمال فیلتر
                        </button>
                        <a href="?tab=inventory" class="bg-white/5 hover:bg-white/10 text-slate-400 hover:text-white font-bold px-4 py-2 rounded-xl text-xs transition-colors">
                            پاک‌سازی
                        </a>
                    </div>
                </div>
            </form>

            <!-- Books Inventory Container -->
            <div class="bg-slate-900/80 border border-white/5 rounded-3xl overflow-hidden shadow-xl">
                <div class="p-5 border-b border-white/5 flex justify-between items-center">
                    <div class="flex items-center gap-2">
                        <span class="text-sm font-black text-white">قفسه کتب بنیاد حکمت</span>
                        <span class="bg-white/5 text-slate-400 text-xs px-2.5 py-0.5 rounded-full font-mono"><?php echo count($books); ?> عنوان</span>
                    </div>
                    <span class="text-slate-500 text-xs">ثبت‌شده بر اساس دفاتر مرجع بنیاد</span>
                </div>

                <!-- Desktop Table View (Hidden on Mobile) -->
                <div class="hidden md:block overflow-x-auto">
                    <table class="w-full text-right text-xs">
                        <thead class="bg-slate-950/50 text-slate-400 border-b border-white/5 font-black text-[11px]">
                            <tr>
                                <th class="py-3.5 px-4">کد کتاب</th>
                                <th class="py-3.5 px-4">عنوان کتاب / جزوه</th>
                                <th class="py-3.5 px-4">پایه و رشته</th>
                                <th class="py-3.5 px-4">دسته‌بندی</th>
                                <th class="py-3.5 px-4">سال چاپ</th>
                                <th class="py-3.5 px-4">ناشر / استاد</th>
                                <th class="py-3.5 px-4">توضیحات و جزئیات</th>
                                <th class="py-3.5 px-4">وضعیت</th>
                                <th class="py-3.5 px-4 text-center">اقدامات</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-white/5">
                            <?php if (empty($books)): ?>
                                <tr>
                                    <td colspan="9" class="py-12 text-center text-slate-500">
                                        هیچ کتابی مطابق فیلترهای جستجو یافت نشد.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($books as $b): ?>
                                    <tr class="hover:bg-white/[0.02] transition-colors">
                                        <td class="py-3.5 px-4 font-mono font-bold text-teal-400">
                                            <?php echo htmlspecialchars($b['book_code'] ?: ('BK-' . $b['id'])); ?>
                                        </td>
                                        <td class="py-3.5 px-4 font-black text-white">
                                            <?php echo htmlspecialchars($b['title']); ?>
                                        </td>
                                        <td class="py-3.5 px-4 text-slate-300">
                                            <?php echo htmlspecialchars($b['grade_level'] ?: 'عمومی'); ?>
                                        </td>
                                        <td class="py-3.5 px-4">
                                            <span class="bg-white/5 text-slate-300 px-2.5 py-1 rounded-lg text-[10px] font-bold border border-white/5">
                                                <?php echo htmlspecialchars($b['category'] ?: 'سایر کتب'); ?>
                                            </span>
                                        </td>
                                        <td class="py-3.5 px-4 font-mono text-slate-300">
                                            <?php echo htmlspecialchars($b['publish_year'] ? toFarsiDigits($b['publish_year']) : '—'); ?>
                                        </td>
                                        <td class="py-3.5 px-4 font-bold text-slate-200">
                                            <?php echo htmlspecialchars($b['publisher'] ?: '—'); ?>
                                        </td>
                                        <td class="py-3.5 px-4 text-slate-400 max-w-xs truncate" title="<?php echo htmlspecialchars($b['description']); ?>">
                                            <?php echo htmlspecialchars($b['description'] ?: '—'); ?>
                                        </td>
                                        <td class="py-3.5 px-4">
                                            <?php if ($b['status'] === 'available'): ?>
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-black bg-emerald-500/15 text-emerald-300 border border-emerald-500/30">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> موجود
                                                </span>
                                            <?php elseif ($b['status'] === 'borrowed'): ?>
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-black bg-blue-500/15 text-blue-300 border border-blue-500/30">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-blue-400"></span> در امانت
                                                </span>
                                                <div class="text-[10px] text-slate-400 mt-1">
                                                    <?php echo htmlspecialchars(($b['borrower_name'] ?? '') . ' ' . ($b['borrower_surname'] ?? '')); ?>
                                                </div>
                                                <?php if (!empty($book_waitlist_counts[$b['id']])): ?>
                                                    <button type="button" onclick="switchTab('waitlist')" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30 hover:bg-amber-500/30 transition-all mt-1">
                                                        🔔 <?php echo toFarsiDigits($book_waitlist_counts[$b['id']]); ?> در نوبت
                                                    </button>
                                                <?php endif; ?>
                                            <?php elseif ($b['status'] === 'requested'): ?>
                                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[10px] font-black bg-amber-500/15 text-amber-300 border border-amber-500/30">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span> در انتظار تحویل
                                                </span>
                                                <?php if (!empty($book_waitlist_counts[$b['id']])): ?>
                                                    <button type="button" onclick="switchTab('waitlist')" class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30 hover:bg-amber-500/30 transition-all mt-1">
                                                        🔔 <?php echo toFarsiDigits($book_waitlist_counts[$b['id']]); ?> در نوبت
                                                    </button>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="text-slate-500 text-[10px]"><?php echo htmlspecialchars($b['status']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3.5 px-4 text-center">
                                            <div class="flex items-center justify-center gap-1.5">
                                                <?php if (!$is_read_only): ?>
                                                    <?php if ($b['status'] === 'available'): ?>
                                                        <button onclick="openLoanModal(<?php echo $b['id']; ?>, '<?php echo addslashes($b['title']); ?>', '<?php echo addslashes($b['book_code']); ?>')" class="bg-blue-600/80 hover:bg-blue-600 text-white text-[11px] font-bold px-2.5 py-1 rounded-lg transition-colors" title="امانت دادن">
                                                            امانت دادن
                                                        </button>
                                                    <?php elseif ($b['status'] === 'borrowed'): ?>
                                                        <form method="POST" onsubmit="return confirm('آیا از ثبت بازگشت این کتاب به کتابخانه اطمینان دارید؟')">
                                                            <input type="hidden" name="action" value="return_book">
                                                            <input type="hidden" name="book_id" value="<?php echo $b['id']; ?>">
                                                            <button type="submit" class="bg-emerald-600 hover:bg-emerald-500 text-white text-[11px] font-bold px-2.5 py-1 rounded-lg transition-colors" title="ثبت بازگشت کتاب">
                                                                ثبت بازگشت
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>

                                                    <!-- Edit Button -->
                                                    <button onclick='openEditModal(<?php echo json_encode($b, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>)' class="bg-white/5 hover:bg-white/10 text-slate-300 hover:text-white p-1.5 rounded-lg transition-colors" title="ویرایش">
                                                        ✏️
                                                    </button>

                                                    <!-- Delete Button -->
                                                    <?php if ($b['status'] !== 'borrowed'): ?>
                                                        <form method="POST" onsubmit="return confirm('آیا از حذف این کتاب اطمینان دارید؟')">
                                                            <input type="hidden" name="action" value="delete_book">
                                                            <input type="hidden" name="book_id" value="<?php echo $b['id']; ?>">
                                                            <button type="submit" class="bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 p-1.5 rounded-lg transition-colors" title="حذف">
                                                                🗑️
                                                            </button>
                                                        </form>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="text-slate-600 text-[10px]">فقط مشاهده</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Mobile Card List View (Visible on Phones) -->
                <div class="block md:hidden divide-y divide-white/5">
                    <?php if (empty($books)): ?>
                        <div class="py-12 text-center text-slate-500 text-xs">
                            هیچ کتابی مطابق فیلترهای جستجو یافت نشد.
                        </div>
                    <?php else: ?>
                        <?php foreach ($books as $b): ?>
                            <div class="p-4 space-y-2.5">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex items-center gap-1.5">
                                        <span class="font-mono font-bold text-teal-400 text-xs bg-teal-500/15 px-2 py-0.5 rounded-md border border-teal-500/20">
                                            <?php echo htmlspecialchars($b['book_code'] ?: ('BK-' . $b['id'])); ?>
                                        </span>
                                        <span class="bg-white/5 text-slate-300 px-2 py-0.5 rounded text-[10px] font-bold border border-white/5">
                                            <?php echo htmlspecialchars($b['category'] ?: 'سایر کتب'); ?>
                                        </span>
                                    </div>
                                    <?php if ($b['status'] === 'available'): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-black bg-emerald-500/15 text-emerald-300 border border-emerald-500/30">
                                            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span> موجود
                                        </span>
                                    <?php elseif ($b['status'] === 'borrowed'): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-black bg-blue-500/15 text-blue-300 border border-blue-500/30">
                                            <span class="w-1.5 h-1.5 rounded-full bg-blue-400"></span> در امانت
                                        </span>
                                    <?php elseif ($b['status'] === 'requested'): ?>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-black bg-amber-500/15 text-amber-300 border border-amber-500/30">
                                            <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span> در انتظار تحویل
                                        </span>
                                    <?php endif; ?>
                                </div>

                                <div>
                                    <h4 class="text-sm font-black text-white leading-snug"><?php echo htmlspecialchars($b['title']); ?></h4>
                                    <div class="text-[11px] text-slate-400 mt-1 space-y-0.5">
                                        <div>پایه: <span class="text-slate-200 font-bold"><?php echo htmlspecialchars($b['grade_level'] ?: 'عمومی'); ?></span> • ناشر: <span class="text-slate-200"><?php echo htmlspecialchars($b['publisher'] ?: '—'); ?></span></div>
                                        <?php if (!empty($b['publish_year'])): ?>
                                            <div>سال چاپ: <span class="text-slate-300 font-mono"><?php echo toFarsiDigits($b['publish_year']); ?></span></div>
                                        <?php endif; ?>
                                        <?php if ($b['status'] === 'borrowed' && !empty($b['borrower_name'])): ?>
                                            <div class="text-blue-300 font-bold">امانت‌گیرنده: <?php echo htmlspecialchars($b['borrower_name'] . ' ' . $b['borrower_surname']); ?></div>
                                        <?php endif; ?>
                                        <?php if (!empty($b['description'])): ?>
                                            <div class="text-[10px] text-slate-400 bg-black/20 p-2 rounded-lg border border-white/5 mt-1"><?php echo htmlspecialchars($b['description']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="flex items-center gap-2 pt-2 border-t border-white/5">
                                    <?php if (!$is_read_only): ?>
                                        <?php if ($b['status'] === 'available'): ?>
                                            <button onclick="openLoanModal(<?php echo $b['id']; ?>, '<?php echo addslashes($b['title']); ?>', '<?php echo addslashes($b['book_code']); ?>')" class="flex-1 bg-blue-600 hover:bg-blue-500 text-white text-xs font-bold py-1.5 px-3 rounded-xl transition-colors text-center shadow">
                                                امانت دادن
                                            </button>
                                        <?php elseif ($b['status'] === 'borrowed'): ?>
                                            <form method="POST" onsubmit="return confirm('آیا از ثبت بازگشت این کتاب به کتابخانه اطمینان دارید؟')" class="flex-1">
                                                <input type="hidden" name="action" value="return_book">
                                                <input type="hidden" name="book_id" value="<?php echo $b['id']; ?>">
                                                <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold py-1.5 px-3 rounded-xl transition-colors text-center shadow">
                                                    ثبت بازگشت به مخزن
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                        <button onclick='openEditModal(<?php echo json_encode($b, JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_HEX_AMP); ?>)' class="bg-white/5 hover:bg-white/10 text-slate-300 p-2 rounded-xl text-xs" title="ویرایش">
                                            ✏️
                                        </button>
                                        <?php if ($b['status'] !== 'borrowed'): ?>
                                            <form method="POST" onsubmit="return confirm('آیا از حذف این کتاب اطمینان دارید؟')">
                                                <input type="hidden" name="action" value="delete_book">
                                                <input type="hidden" name="book_id" value="<?php echo $b['id']; ?>">
                                                <button type="submit" class="bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 p-2 rounded-xl text-xs" title="حذف">
                                                    🗑️
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-slate-600 text-xs">حالت نظارتی هیئت مدیره (فقط مشاهده)</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- ================= TAB 2: REQUESTS ================= -->
        <div id="tab-requests" class="tab-panel hidden space-y-6">
            <div class="bg-slate-900/80 border border-white/5 rounded-3xl p-6 shadow-xl">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h3 class="text-lg font-black text-white flex items-center gap-2">
                            <span>📥</span> کارتابل درخواست‌های جدید امانت کتاب (ارسالی توسط دانش‌آموزان)
                        </h3>
                        <p class="text-xs text-slate-400 mt-1">
                            خانم فرتاش و خانم عباسی گرامی؛ درخواست‌های زیر توسط دانش‌پژوهان ثبت گردیده و نیازمند تایید و تحویل کتاب فیزیکی می‌باشد.
                        </p>
                    </div>
                    <span class="bg-amber-500/15 text-amber-300 border border-amber-500/30 text-xs px-3 py-1 rounded-full font-bold">
                        <?php echo count($pending_requests); ?> درخواست در انتظار بررسی
                    </span>
                </div>

                <?php if (empty($pending_requests)): ?>
                    <div class="text-center py-16 bg-white/[0.02] rounded-2xl border border-dashed border-white/10">
                        <div class="text-4xl mb-3">✨</div>
                        <div class="text-sm font-bold text-slate-300">هیچ درخواست امانت جدیدی در حال حاضر در انتظار نیست.</div>
                        <p class="text-xs text-slate-500 mt-1">به محض ثبت درخواست امانت توسط دانش‌آموز در پورتال، اعلان آن در این بخش نمایش داده خواهد شد.</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php foreach ($pending_requests as $req): ?>
                            <div class="bg-slate-950 border border-amber-500/30 p-6 rounded-3xl space-y-4 relative overflow-hidden shadow-lg">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <span class="text-[10px] font-mono font-bold bg-teal-500/20 text-teal-300 px-2 py-0.5 rounded-md">
                                            <?php echo htmlspecialchars($req['book_code']); ?>
                                        </span>
                                        <h4 class="text-base font-black text-white mt-1"><?php echo htmlspecialchars($req['title']); ?></h4>
                                        <div class="text-xs text-slate-400 mt-0.5"><?php echo htmlspecialchars($req['grade_level']); ?> • ناشر: <?php echo htmlspecialchars($req['publisher'] ?: '—'); ?></div>
                                    </div>
                                    <span class="text-[10px] font-black px-2.5 py-1 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/40">
                                        🟡 در انتظار تایید
                                    </span>
                                </div>

                                <div class="bg-white/5 p-4 rounded-2xl border border-white/5 space-y-2 text-xs">
                                    <div class="flex justify-between text-slate-300">
                                        <span>دانش‌آموز متقاضی:</span>
                                        <strong class="text-white"><?php echo htmlspecialchars($req['student_name'] . ' ' . $req['student_surname']); ?></strong>
                                    </div>
                                    <div class="flex justify-between text-slate-300">
                                        <span>پایه و مدرسه:</span>
                                        <span class="text-slate-200"><?php echo htmlspecialchars($req['student_grade'] . ' • ' . ($req['school'] ?: 'نامشخص')); ?></span>
                                    </div>
                                    <div class="flex justify-between text-slate-300">
                                        <span>تلفن تماس:</span>
                                        <span class="text-slate-200 font-mono"><?php echo htmlspecialchars($req['student_phone'] ?: ($req['guardian_phone'] ?: '—')); ?></span>
                                    </div>
                                    <?php if (!empty($req['request_date'])): ?>
                                        <div class="flex justify-between text-slate-300">
                                            <span>تاریخ درخواست:</span>
                                            <span class="text-slate-400 font-mono"><?php echo htmlspecialchars($req['request_date']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <?php if (!$is_read_only): ?>
                                    <div class="flex items-center gap-3 pt-2">
                                        <button onclick="openLoanModal(<?php echo $req['id']; ?>, '<?php echo addslashes($req['title']); ?>', '<?php echo addslashes($req['book_code']); ?>', <?php echo $req['current_borrower_id']; ?>)" class="flex-1 bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-bold py-2.5 rounded-xl text-xs transition-all text-center shadow-md">
                                            ✓ تایید و تحویل فیزیکی کتاب
                                        </button>
                                        <form method="POST" onsubmit="return confirm('آیا از رد این درخواست امانت اطمینان دارید؟')" class="flex-shrink-0">
                                            <input type="hidden" name="action" value="reject_request">
                                            <input type="hidden" name="book_id" value="<?php echo $req['id']; ?>">
                                            <input type="hidden" name="student_id" value="<?php echo $req['current_borrower_id']; ?>">
                                            <button type="submit" class="bg-rose-500/10 hover:bg-rose-500/20 text-rose-300 border border-rose-500/20 font-bold py-2.5 px-4 rounded-xl text-xs transition-colors">
                                                ✕ رد درخواست
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ================= TAB 3: CURRENTLY BORROWED ================= -->
        <div id="tab-borrowed" class="tab-panel hidden space-y-6">
            <div class="bg-slate-900/80 border border-white/5 rounded-3xl p-6 shadow-xl">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h3 class="text-lg font-black text-white flex items-center gap-2">
                            <span>🤝</span> کتاب‌های در دست امانت دانش‌آموزان
                        </h3>
                        <p class="text-xs text-slate-400 mt-1">
                            پیگیری دقیق موقعیت فیزیکی کتاب‌ها، تاریخ تحویل، مهلت بازگشت و شماره تماس ولی دانش‌آموز
                        </p>
                    </div>
                    <span class="bg-blue-500/15 text-blue-300 border border-blue-500/30 text-xs px-3 py-1 rounded-full font-bold">
                        <?php echo count($borrowed_list); ?> جلد در دست امانت
                    </span>
                </div>

                <?php if (empty($borrowed_list)): ?>
                    <div class="text-center py-16 bg-white/[0.02] rounded-2xl border border-dashed border-white/10">
                        <div class="text-4xl mb-3">📚</div>
                        <div class="text-sm font-bold text-slate-300">در حال حاضر هیچ کتابی در دست امانت نیست.</div>
                        <p class="text-xs text-slate-500 mt-1">تمام کتاب‌های مخزن بنیاد موجود می‌باشند.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-right text-xs">
                            <thead class="bg-slate-950/50 text-slate-400 border-b border-white/5 font-black text-[11px]">
                                <tr>
                                    <th class="py-3.5 px-4">کد کتاب</th>
                                    <th class="py-3.5 px-4">عنوان کتاب</th>
                                    <th class="py-3.5 px-4">دانش‌آموز امانت‌گیرنده</th>
                                    <th class="py-3.5 px-4">پایه و مدرسه</th>
                                    <th class="py-3.5 px-4">شماره تماس دانش‌آموز / ولی</th>
                                    <th class="py-3.5 px-4">تاریخ تحویل</th>
                                    <th class="py-3.5 px-4">موعد بازگشت</th>
                                    <th class="py-3.5 px-4 text-center">اقدام</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                <?php foreach ($borrowed_list as $row): ?>
                                    <tr class="hover:bg-white/[0.02] transition-colors">
                                        <td class="py-3.5 px-4 font-mono font-bold text-teal-400"><?php echo htmlspecialchars($row['book_code']); ?></td>
                                        <td class="py-3.5 px-4 font-black text-white">
                                            <?php echo htmlspecialchars($row['title']); ?>
                                            <?php if (!empty($book_waitlist_counts[$row['id']])): ?>
                                                <button type="button" onclick="switchTab('waitlist')" class="inline-flex items-center gap-1 mt-1 text-[10px] text-amber-300 bg-amber-500/15 border border-amber-500/30 px-2 py-0.5 rounded-md font-bold">
                                                    🔔 <?php echo toFarsiDigits($book_waitlist_counts[$row['id']]); ?> نفر در صف انتظار
                                                </button>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3.5 px-4 font-bold text-teal-300"><?php echo htmlspecialchars($row['borrower_name'] . ' ' . $row['borrower_surname']); ?></td>
                                        <td class="py-3.5 px-4 text-slate-300"><?php echo htmlspecialchars($row['borrower_grade'] . ' • ' . ($row['school'] ?: 'نامشخص')); ?></td>
                                        <td class="py-3.5 px-4 font-mono text-slate-300">
                                            <?php echo htmlspecialchars($row['borrower_phone'] ?: ($row['guardian_phone'] ?: '—')); ?>
                                        </td>
                                        <td class="py-3.5 px-4 font-mono text-slate-300"><?php echo htmlspecialchars($row['borrowed_at'] ?: '—'); ?></td>
                                        <td class="py-3.5 px-4 font-mono text-amber-300"><?php echo htmlspecialchars($row['expected_return_date'] ?: 'پایان سال تحصیلی'); ?></td>
                                        <td class="py-3.5 px-4 text-center">
                                            <?php if (!$is_read_only): ?>
                                                <form method="POST" onsubmit="return confirm('آیا کتاب دریافت شد و بازگشت به مخزن تایید می‌شود؟')">
                                                    <input type="hidden" name="action" value="return_book">
                                                    <input type="hidden" name="book_id" value="<?php echo $row['id']; ?>">
                                                    <button type="submit" class="bg-emerald-600 hover:bg-emerald-500 text-white font-bold px-3 py-1.5 rounded-xl text-xs transition-colors shadow-md">
                                                        ثبت بازگشت کتاب
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-slate-600 text-[10px]">فقط مشاهده</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ================= TAB: WAITLIST & QUEUES ================= -->
        <div id="tab-waitlist" class="tab-panel hidden space-y-6">
            <div class="bg-slate-900/80 border border-white/5 rounded-3xl p-6 shadow-xl">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
                    <div>
                        <h3 class="text-lg font-black text-white flex items-center gap-2">
                            <span>🔔</span> صف‌های انتظار، رزرو کتب و سامانه پیامک خودکار
                        </h3>
                        <p class="text-xs text-slate-400 mt-1">
                            فهرست دانش‌آموزانی که در نوبت کتب امانت‌داده‌شده هستند. به محض ثبت بازگشت کتاب توسط شما، پیامک اطلاع‌رسانی به نفر اول صف به صورت هوشمند ارسال خواهد شد.
                        </p>
                    </div>
                    <span class="bg-indigo-500/15 text-indigo-300 border border-indigo-500/30 text-xs px-3 py-1 rounded-full font-bold">
                        <?php echo toFarsiDigits(count($waitlist_items)); ?> نوبت ثبت‌شده
                    </span>
                </div>

                <?php if (empty($waitlist_items)): ?>
                    <div class="text-center py-16 bg-white/[0.02] rounded-2xl border border-dashed border-white/10">
                        <div class="text-4xl mb-3">🔔</div>
                        <div class="text-sm font-bold text-slate-300">در حال حاضر هیچ نوبتی در صف انتظار ثبت نشده است.</div>
                        <p class="text-xs text-slate-500 mt-1">دانش‌آموزان با ورود به پورتال خود و کلیک روی دکمه «نوبت بگیر / به من اطلاع بده» در صف کتب امانت‌داده‌شده قرار می‌گیرند.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-right text-xs">
                            <thead class="bg-slate-950/50 text-slate-400 border-b border-white/5 font-black text-[11px]">
                                <tr>
                                    <th class="py-3.5 px-4">کد و عنوان کتاب</th>
                                    <th class="py-3.5 px-4">وضعیت فیزیکی کتاب</th>
                                    <th class="py-3.5 px-4">دانش‌آموز متقاضی</th>
                                    <th class="py-3.5 px-4">پایه و مدرسه</th>
                                    <th class="py-3.5 px-4">شماره تماس (پیامک)</th>
                                    <th class="py-3.5 px-4">جایگاه در نوبت</th>
                                    <th class="py-3.5 px-4">وضعیت نوبت</th>
                                    <th class="py-3.5 px-4 text-center">اقدامات مدیریت</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                <?php foreach ($waitlist_items as $w): ?>
                                    <?php 
                                    $phone_num = !empty($w['student_phone']) ? $w['student_phone'] : $w['guardian_phone'];
                                    ?>
                                    <tr class="hover:bg-white/[0.02] transition-colors">
                                        <td class="py-3.5 px-4 font-bold text-white">
                                            <span class="font-mono text-teal-400 text-xs ml-1.5"><?php echo htmlspecialchars($w['book_code']); ?></span>
                                            <?php echo htmlspecialchars($w['book_title']); ?>
                                        </td>
                                        <td class="py-3.5 px-4">
                                            <?php if ($w['book_status'] === 'available'): ?>
                                                <span class="bg-emerald-500/15 text-emerald-300 border border-emerald-500/30 px-2 py-0.5 rounded-full text-[10px] font-bold">🟢 موجود در مخزن</span>
                                            <?php elseif ($w['book_status'] === 'borrowed'): ?>
                                                <span class="bg-blue-500/15 text-blue-300 border border-blue-500/30 px-2 py-0.5 rounded-full text-[10px] font-bold">🤝 در دست امانت</span>
                                            <?php else: ?>
                                                <span class="bg-amber-500/15 text-amber-300 border border-amber-500/30 px-2 py-0.5 rounded-full text-[10px] font-bold">🟡 درخواست‌شده</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3.5 px-4 font-bold text-teal-300">
                                            <?php echo htmlspecialchars($w['student_name'] . ' ' . $w['student_surname']); ?>
                                        </td>
                                        <td class="py-3.5 px-4 text-slate-300">
                                            <?php echo htmlspecialchars($w['student_grade'] . ' • ' . ($w['school'] ?: 'نامشخص')); ?>
                                        </td>
                                        <td class="py-3.5 px-4 font-mono text-slate-300">
                                            <?php echo htmlspecialchars($phone_num ?: '—'); ?>
                                        </td>
                                        <td class="py-3.5 px-4 font-bold text-amber-300">
                                            نفر <?php echo toFarsiDigits($w['queue_position'] ?: 1); ?>
                                        </td>
                                        <td class="py-3.5 px-4">
                                            <?php if ($w['status'] === 'notified'): ?>
                                                <span class="bg-emerald-500/15 text-emerald-300 border border-emerald-500/30 px-2.5 py-1 rounded-full text-[10px] font-bold inline-flex items-center gap-1">
                                                    ✓ پیامک ارسال شد
                                                </span>
                                                <div class="text-[9px] text-slate-500 mt-0.5 font-mono"><?php echo htmlspecialchars(substr($w['notified_at'] ?? '', 0, 16)); ?></div>
                                            <?php else: ?>
                                                <span class="bg-amber-500/15 text-amber-300 border border-amber-500/30 px-2.5 py-1 rounded-full text-[10px] font-bold inline-flex items-center gap-1">
                                                    ⏳ در صف انتظار
                                                </span>
                                                <div class="text-[9px] text-slate-500 mt-0.5 font-mono"><?php echo htmlspecialchars(substr($w['created_at'], 0, 10)); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="py-3.5 px-4 text-center">
                                            <div class="flex items-center justify-center gap-1.5">
                                                <?php if (!$is_read_only): ?>
                                                    <!-- Loan directly button if book is available -->
                                                    <?php if ($w['book_status'] === 'available'): ?>
                                                        <button onclick="openLoanModal(<?php echo $w['book_id']; ?>, '<?php echo addslashes($w['book_title']); ?>', '<?php echo addslashes($w['book_code']); ?>', <?php echo $w['student_id']; ?>)" class="bg-blue-600 hover:bg-blue-500 text-white font-bold px-2.5 py-1 rounded-lg text-[10px] transition-colors shadow-sm" title="امانت دادن به این دانش‌آموز">
                                                            تحویل کتاب
                                                        </button>
                                                    <?php endif; ?>

                                                    <!-- Resend SMS button -->
                                                    <form method="POST" onsubmit="return confirm('آیا از ارسال پیامک اطلاع‌رسانی بازگشت کتاب به این دانش‌آموز اطمینان دارید؟')">
                                                        <input type="hidden" name="action" value="notify_waitlist_manual">
                                                        <input type="hidden" name="waitlist_id" value="<?php echo $w['id']; ?>">
                                                        <button type="submit" class="bg-purple-600/80 hover:bg-purple-600 text-white font-bold px-2.5 py-1 rounded-lg text-[10px] transition-colors shadow-sm flex items-center gap-1" title="ارسال پیامک اطلاع‌رسانی">
                                                            <span>📱</span> پیامک
                                                        </button>
                                                    </form>

                                                    <!-- Remove from waitlist -->
                                                    <form method="POST" onsubmit="return confirm('آیا از حذف این دانش‌آموز از صف انتظار اطمینان دارید؟')">
                                                        <input type="hidden" name="action" value="remove_from_waitlist">
                                                        <input type="hidden" name="waitlist_id" value="<?php echo $w['id']; ?>">
                                                        <button type="submit" class="bg-rose-500/10 hover:bg-rose-500/20 text-rose-300 font-bold px-2 py-1 rounded-lg text-[10px] transition-colors" title="حذف از نوبت">
                                                            ✕
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-slate-600 text-[10px]">فقط مشاهده</span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- ================= TAB 4: AUDIT LOGS ================= -->
        <div id="tab-logs" class="tab-panel hidden space-y-6">
            <div class="bg-slate-900/80 border border-white/5 rounded-3xl p-6 shadow-xl">
                <div class="flex justify-between items-center mb-6">
                    <div>
                        <h3 class="text-lg font-black text-white flex items-center gap-2">
                            <span>📜</span> دفترچه سوابق و تاریخچه گردش کتب (Audit Trail)
                        </h3>
                        <p class="text-xs text-slate-400 mt-1">
                            ثبت سیستمی کلیه وقایع شامل درخواست‌ها، تحویل فیزیکی، استرداد و کاربران اقدام‌کننده
                        </p>
                    </div>
                </div>

                <?php if (empty($logs)): ?>
                    <div class="text-center py-16 text-slate-500 text-xs">
                        هنوز سابقه‌ای در سیستم ثبت نگردیده است.
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-right text-xs">
                            <thead class="bg-slate-950/50 text-slate-400 border-b border-white/5 font-black text-[11px]">
                                <tr>
                                    <th class="py-3.5 px-4">شناسه</th>
                                    <th class="py-3.5 px-4">عنوان و کد کتاب</th>
                                    <th class="py-3.5 px-4">دانش‌آموز</th>
                                    <th class="py-3.5 px-4">نوع اقدام</th>
                                    <th class="py-3.5 px-4">ثبت‌کننده اقدام</th>
                                    <th class="py-3.5 px-4">تاریخ اقدام</th>
                                    <th class="py-3.5 px-4">یادداشت / توضیحات</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-white/5">
                                <?php foreach ($logs as $l): ?>
                                    <tr class="hover:bg-white/[0.02]">
                                        <td class="py-3 px-4 font-mono text-slate-500">#<?php echo $l['id']; ?></td>
                                        <td class="py-3 px-4 font-bold text-white">
                                            <?php echo htmlspecialchars($l['book_title'] ?: 'کتاب نامشخص'); ?>
                                            <span class="text-teal-400 font-mono text-[10px] mr-1">(<?php echo htmlspecialchars($l['book_code'] ?: ''); ?>)</span>
                                        </td>
                                        <td class="py-3 px-4 text-slate-300">
                                            <?php echo htmlspecialchars(($l['student_name'] ?? '') . ' ' . ($l['student_surname'] ?? '')); ?>
                                        </td>
                                        <td class="py-3 px-4">
                                            <?php 
                                            $act = $l['action'];
                                            if ($act === 'approve_borrow') {
                                                echo '<span class="bg-blue-500/15 text-blue-300 px-2.5 py-0.5 rounded-full text-[10px] font-bold border border-blue-500/30">🤝 تحویل و امانت</span>';
                                            } elseif ($act === 'return') {
                                                echo '<span class="bg-emerald-500/15 text-emerald-300 px-2.5 py-0.5 rounded-full text-[10px] font-bold border border-emerald-500/30">🟢 بازگشت به مخزن</span>';
                                            } elseif ($act === 'request') {
                                                echo '<span class="bg-amber-500/15 text-amber-300 px-2.5 py-0.5 rounded-full text-[10px] font-bold border border-amber-500/30">📥 درخواست دانش‌آموز</span>';
                                            } elseif ($act === 'reject') {
                                                echo '<span class="bg-rose-500/15 text-rose-300 px-2.5 py-0.5 rounded-full text-[10px] font-bold border border-rose-500/30">✕ رد درخواست</span>';
                                            } elseif ($act === 'notify_waitlist') {
                                                echo '<span class="bg-purple-500/15 text-purple-300 px-2.5 py-0.5 rounded-full text-[10px] font-bold border border-purple-500/30">📱 اطلاع‌رسانی پیامکی</span>';
                                            } elseif ($act === 'join_waitlist') {
                                                echo '<span class="bg-amber-500/15 text-amber-300 px-2.5 py-0.5 rounded-full text-[10px] font-bold border border-amber-500/30">🔔 ثبت در نوبت</span>';
                                            } elseif ($act === 'cancel_request') {
                                                echo '<span class="bg-slate-500/15 text-slate-300 px-2.5 py-0.5 rounded-full text-[10px] font-bold border border-slate-500/30">✕ انصراف درخواست</span>';
                                            } else {
                                                echo htmlspecialchars($act);
                                            }
                                            ?>
                                        </td>
                                        <td class="py-3 px-4 text-slate-400 font-bold">
                                            <?php echo htmlspecialchars($l['actor_name'] ?: 'سیستم'); ?>
                                        </td>
                                        <td class="py-3 px-4 font-mono text-slate-300"><?php echo htmlspecialchars($l['action_date'] ?: substr($l['created_at'], 0, 10)); ?></td>
                                        <td class="py-3 px-4 text-slate-400 max-w-xs truncate"><?php echo htmlspecialchars($l['notes'] ?: '—'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

    </main>

    <!-- ================= MODALS ================= -->

    <!-- 1. Add Book Modal -->
    <div id="add-book-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4 hidden">
        <div class="bg-slate-900 border border-white/10 w-full max-w-lg rounded-[2.5rem] shadow-2xl p-8 text-white relative">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-base font-black flex items-center gap-2">
                    <span>📚</span> افزودن کتاب جدید به مخزن کتابخانه بنیاد
                </h3>
                <button type="button" onclick="document.getElementById('add-book-modal').classList.add('hidden')" class="text-slate-400 hover:text-white text-lg">✕</button>
            </div>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="add_book">

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">عنوان کتاب / جزوه *</label>
                    <input type="text" name="title" required placeholder="مثلاً: زیست‌شناسی تصویری، سه‌سطحی شیمی..." class="w-full bg-slate-950 border border-white/15 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-teal-500">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1.5">پایه تحصیلی و رشته</label>
                        <input type="text" name="grade_level" placeholder="مثلاً: یازدهم تجربی، نهم..." class="w-full bg-slate-950 border border-white/15 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-teal-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1.5">دسته‌بندی موضوعی</label>
                        <select name="category" class="w-full bg-slate-950 border border-white/15 rounded-xl px-3 py-2.5 text-xs text-white focus:outline-none focus:border-teal-500">
                            <?php foreach ($categories_list as $cat): ?>
                                <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1.5">ناشر یا استاد گردآورنده</label>
                        <input type="text" name="publisher" placeholder="قلم‌چی، خیلی سبز، گاج، استاد مرادی..." class="w-full bg-slate-950 border border-white/15 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-teal-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1.5">سال چاپ</label>
                        <input type="text" name="publish_year" placeholder="1402، 1403..." class="w-full bg-slate-950 border border-white/15 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-teal-500">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">توضیحات تکمیلی (جلد، تعداد سوالات و...)</label>
                    <textarea name="description" rows="2" placeholder="مثلاً: جلد اول تست و پاسخنامه، ۸۰۰ سوال..." class="w-full bg-slate-950 border border-white/15 rounded-xl p-3 text-xs text-white focus:outline-none focus:border-teal-500"></textarea>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-white/10">
                    <button type="button" onclick="document.getElementById('add-book-modal').classList.add('hidden')" class="px-5 py-2.5 rounded-xl text-xs font-bold text-slate-400 hover:text-white transition-colors">انصراف</button>
                    <button type="submit" class="bg-teal-600 hover:bg-teal-500 text-white font-bold px-6 py-2.5 rounded-xl text-xs shadow-lg shadow-teal-500/20 transition-all">ثبت در کتابخانه</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 2. Edit Book Modal -->
    <div id="edit-book-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4 hidden">
        <div class="bg-slate-900 border border-white/10 w-full max-w-lg rounded-[2.5rem] shadow-2xl p-8 text-white relative">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-base font-black flex items-center gap-2">
                    <span>✏️</span> ویرایش مشخصات کتاب
                </h3>
                <button type="button" onclick="document.getElementById('edit-book-modal').classList.add('hidden')" class="text-slate-400 hover:text-white text-lg">✕</button>
            </div>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="edit_book">
                <input type="hidden" name="book_id" id="edit_book_id" value="">

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">عنوان کتاب / جزوه *</label>
                    <input type="text" name="title" id="edit_title" required class="w-full bg-slate-950 border border-white/15 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-teal-500">
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1.5">پایه تحصیلی و رشته</label>
                        <input type="text" name="grade_level" id="edit_grade_level" class="w-full bg-slate-950 border border-white/15 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-teal-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1.5">دسته‌بندی موضوعی</label>
                        <select name="category" id="edit_category" class="w-full bg-slate-950 border border-white/15 rounded-xl px-3 py-2.5 text-xs text-white focus:outline-none focus:border-teal-500">
                            <?php foreach ($categories_list as $cat): ?>
                                <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1.5">ناشر یا استاد گردآورنده</label>
                        <input type="text" name="publisher" id="edit_publisher" class="w-full bg-slate-950 border border-white/15 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-teal-500">
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-300 mb-1.5">سال چاپ</label>
                        <input type="text" name="publish_year" id="edit_publish_year" class="w-full bg-slate-950 border border-white/15 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-teal-500">
                    </div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">توضیحات تکمیلی</label>
                    <textarea name="description" id="edit_description" rows="2" class="w-full bg-slate-950 border border-white/15 rounded-xl p-3 text-xs text-white focus:outline-none focus:border-teal-500"></textarea>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-white/10">
                    <button type="button" onclick="document.getElementById('edit-book-modal').classList.add('hidden')" class="px-5 py-2.5 rounded-xl text-xs font-bold text-slate-400 hover:text-white transition-colors">انصراف</button>
                    <button type="submit" class="bg-teal-600 hover:bg-teal-500 text-white font-bold px-6 py-2.5 rounded-xl text-xs shadow-lg shadow-teal-500/20 transition-all">ذخیره تغییرات</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 3. Loan Book Modal (Quick & Manual) -->
    <div id="loan-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4 hidden">
        <div class="bg-slate-900 border border-white/10 w-full max-w-lg rounded-[2.5rem] shadow-2xl p-8 text-white relative">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-base font-black flex items-center gap-2">
                    <span>🤝</span> ثبت تحویل فیزیکی و امانت کتاب به دانش‌آموز
                </h3>
                <button type="button" onclick="document.getElementById('loan-modal').classList.add('hidden')" class="text-slate-400 hover:text-white text-lg">✕</button>
            </div>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="loan_book">
                <input type="hidden" name="book_id" id="loan_book_id" value="">

                <div class="bg-white/5 p-4 rounded-2xl border border-white/5">
                    <div class="text-[11px] text-slate-400">کتاب انتخابی:</div>
                    <div class="text-sm font-black text-teal-300 mt-0.5" id="loan_book_display">---</div>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">انتخاب دانش‌آموز تحویل‌گیرنده *</label>
                    <select name="student_id" id="loan_student_id" required class="w-full bg-slate-950 border border-white/15 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-teal-500">
                        <option value="">-- انتخاب دانش‌آموز --</option>
                        <?php foreach ($all_students as $st): ?>
                            <option value="<?php echo $st['id']; ?>">
                                <?php echo htmlspecialchars($st['name'] . ' ' . $st['surname'] . ' (' . ($st['grade'] ?: 'عمومی') . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">تاریخ سررسید و مهلت بازگشت (شمسی)</label>
                    <input type="text" name="expected_return_date" placeholder="مثلاً: ۱۴۰۳/۰۴/۱۵ یا پایان امتحانات خرداد" class="w-full bg-slate-950 border border-white/15 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-teal-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">یادداشت داخلی (خانم فرتاش / خانم عباسی)</label>
                    <textarea name="admin_notes" rows="2" placeholder="توضیحات تحویل، سلامت فیزیکی کتاب و..." class="w-full bg-slate-950 border border-white/15 rounded-xl p-3 text-xs text-white focus:outline-none focus:border-teal-500"></textarea>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-white/10">
                    <button type="button" onclick="document.getElementById('loan-modal').classList.add('hidden')" class="px-5 py-2.5 rounded-xl text-xs font-bold text-slate-400 hover:text-white transition-colors">انصراف</button>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white font-bold px-6 py-2.5 rounded-xl text-xs shadow-lg shadow-blue-500/20 transition-all">ثبت امانت و تحویل کتاب</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 4. Manual Loan Modal (Choose both book and student) -->
    <div id="manual-loan-modal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 backdrop-blur-sm p-4 hidden">
        <div class="bg-slate-900 border border-white/10 w-full max-w-lg rounded-[2.5rem] shadow-2xl p-8 text-white relative">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-base font-black flex items-center gap-2">
                    <span>🤝</span> امانت‌دهی مستقیم از مخزن کتابخانه
                </h3>
                <button type="button" onclick="document.getElementById('manual-loan-modal').classList.add('hidden')" class="text-slate-400 hover:text-white text-lg">✕</button>
            </div>

            <form method="POST" class="space-y-4">
                <input type="hidden" name="action" value="loan_book">

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">انتخاب کتاب از مخزن موجود *</label>
                    <select name="book_id" required class="w-full bg-slate-950 border border-white/15 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-teal-500">
                        <option value="">-- انتخاب کتاب موجود --</option>
                        <?php foreach ($books as $bk): ?>
                            <?php if ($bk['status'] === 'available'): ?>
                                <option value="<?php echo $bk['id']; ?>">
                                    <?php echo htmlspecialchars($bk['book_code'] . ' - ' . $bk['title'] . ' (' . ($bk['grade_level'] ?: 'عمومی') . ')'); ?>
                                </option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">انتخاب دانش‌آموز تحویل‌گیرنده *</label>
                    <select name="student_id" required class="w-full bg-slate-950 border border-white/15 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-teal-500">
                        <option value="">-- انتخاب دانش‌آموز --</option>
                        <?php foreach ($all_students as $st): ?>
                            <option value="<?php echo $st['id']; ?>">
                                <?php echo htmlspecialchars($st['name'] . ' ' . $st['surname'] . ' (' . ($st['grade'] ?: 'عمومی') . ')'); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">مهلت بازگشت (شمسی)</label>
                    <input type="text" name="expected_return_date" placeholder="مثلاً: پایان سال تحصیلی" class="w-full bg-slate-950 border border-white/15 rounded-xl px-4 py-2.5 text-xs text-white focus:outline-none focus:border-teal-500">
                </div>

                <div>
                    <label class="block text-xs font-bold text-slate-300 mb-1.5">یادداشت داخلی</label>
                    <textarea name="admin_notes" rows="2" placeholder="توضیحات و سلامت کتاب..." class="w-full bg-slate-950 border border-white/15 rounded-xl p-3 text-xs text-white focus:outline-none focus:border-teal-500"></textarea>
                </div>

                <div class="flex items-center justify-end gap-3 pt-4 border-t border-white/10">
                    <button type="button" onclick="document.getElementById('manual-loan-modal').classList.add('hidden')" class="px-5 py-2.5 rounded-xl text-xs font-bold text-slate-400 hover:text-white transition-colors">انصراف</button>
                    <button type="submit" class="bg-blue-600 hover:bg-blue-500 text-white font-bold px-6 py-2.5 rounded-xl text-xs shadow-lg shadow-blue-500/20 transition-all">ثبت امانت مستقیم</button>
                </div>
            </form>
        </div>
    </div>

    <!-- JavaScript for Tabs and Modals -->
    <script>
        function switchTab(tabId) {
            document.querySelectorAll('.tab-panel').forEach(p => p.classList.add('hidden'));
            document.querySelectorAll('.tab-button').forEach(b => {
                b.classList.remove('bg-teal-600', 'text-white');
                b.classList.add('bg-slate-900', 'text-slate-400');
            });

            const panel = document.getElementById('tab-' + tabId);
            if (panel) panel.classList.remove('hidden');

            const btn = document.getElementById('tab-btn-' + tabId);
            if (btn) {
                btn.classList.add('bg-teal-600', 'text-white');
                btn.classList.remove('bg-slate-900', 'text-slate-400');
            }

            const url = new URL(window.location);
            url.searchParams.set('tab', tabId);
            window.history.pushState({}, '', url);
        }

        function openLoanModal(bookId, bookTitle, bookCode, studentId = null) {
            document.getElementById('loan_book_id').value = bookId;
            document.getElementById('loan_book_display').innerText = bookCode + ' - ' + bookTitle;
            if (studentId) {
                document.getElementById('loan_student_id').value = studentId;
            } else {
                document.getElementById('loan_student_id').value = '';
            }
            document.getElementById('loan-modal').classList.remove('hidden');
        }

        function openEditModal(book) {
            document.getElementById('edit_book_id').value = book.id;
            document.getElementById('edit_title').value = book.title || '';
            document.getElementById('edit_grade_level').value = book.grade_level || '';
            document.getElementById('edit_category').value = book.category || 'سایر کتب';
            document.getElementById('edit_publisher').value = book.publisher || '';
            document.getElementById('edit_publish_year').value = book.publish_year || '';
            document.getElementById('edit_description').value = book.description || '';
            document.getElementById('edit-book-modal').classList.remove('hidden');
        }

        document.addEventListener('DOMContentLoaded', () => {
            const params = new URLSearchParams(window.location.search);
            const tab = params.get('tab') || '<?php echo $active_tab; ?>';
            if (tab && document.getElementById('tab-' + tab)) {
                switchTab(tab);
            }
        });
    </script>
</body>
</html>
