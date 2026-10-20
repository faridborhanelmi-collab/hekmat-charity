<?php
/**
 * افزودن دو داوطلب جدید (سوگند صالحی محتشم و ساجده هاشمی) به آزمون‌های مصاحبه
 */

require_once __DIR__ . '/../includes/db.php';

echo "🚀 افزودن داوطلبان جدید به سامانه آزمون‌های مصاحبه بنیاد حکمت...\n";

// اطمینان از وجود جدول
$pdo->exec("
    CREATE TABLE IF NOT EXISTS interview_tests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        token TEXT UNIQUE NOT NULL,
        student_id INTEGER,
        candidate_name TEXT NOT NULL,
        mobile TEXT,
        national_id TEXT,
        test_type TEXT DEFAULT 'comprehensive_battery',
        status TEXT DEFAULT 'pending',
        current_question INTEGER DEFAULT 0,
        answers_json TEXT DEFAULT '{}',
        scores_json TEXT DEFAULT '{}',
        total_time_spent INTEGER DEFAULT 0,
        started_at TEXT,
        completed_at TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id)
    )
");

$candidates = [
    [
        'name' => 'سوگند صالحی محتشم',
        'mobile' => '09387512351',
        'test_type' => 'comprehensive_battery'
    ],
    [
        'name' => 'ساجده هاشمی',
        'mobile' => '09157365328',
        'test_type' => 'comprehensive_battery'
    ]
];

$results = [];

foreach ($candidates as $cand) {
    $name = $cand['name'];
    $mobile = $cand['mobile'];
    $test_type = $cand['test_type'];

    // بررسی وجود رکوردهای قبلی برای این شماره یا نام
    $stmt = $pdo->prepare("SELECT id, token, status, candidate_name FROM interview_tests WHERE mobile = ? OR candidate_name = ?");
    $stmt->execute([$mobile, $name]);
    $existing = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($existing)) {
        // اگر رکوردهای پندینگ قبلی وجود دارند، رکوردهای تکراری اضافه را پاک کرده و یکی را فعال نگه می‌داریم
        $first_record = $existing[0];
        $token = $first_record['token'];
        $test_id = $first_record['id'];

        // بروزرسانی نام و ریست به حالت تمیز
        $pdo->prepare("
            UPDATE interview_tests SET
                candidate_name = ?,
                mobile = ?,
                student_id = NULL,
                test_type = ?,
                status = 'pending',
                current_question = 0,
                answers_json = '{}',
                scores_json = '{}'
            WHERE id = ?
        ")->execute([$name, $mobile, $test_type, $test_id]);

        // پاک کردن رکوردهای تکراری بیش از یکی
        if (count($existing) > 1) {
            for ($i = 1; $i < count($existing); $i++) {
                $dup_id = $existing[$i]['id'];
                $pdo->prepare("DELETE FROM interview_tests WHERE id = ?")->execute([$dup_id]);
                echo "  🗑️ رکورد تکراری قبلی با شناسه {$dup_id} حذف شد.\n";
            }
        }

        echo "  🔄 رکورد موجود برای {$name} بروزرسانی و فعال شد (ID: {$test_id}, Token: {$token})\n";
    } else {
        // ایجاد رکورد کاملاً جدید با توکن یکتا
        $token = bin2hex(random_bytes(6));
        $ins = $pdo->prepare("
            INSERT INTO interview_tests (token, candidate_name, mobile, test_type, status, current_question, answers_json, scores_json)
            VALUES (?, ?, ?, ?, 'pending', 0, '{}', '{}')
        ");
        $ins->execute([$token, $name, $mobile, $test_type]);
        $test_id = $pdo->lastInsertId();
        echo "  ✨ داوطلب جدید {$name} با موفقیت ثبت شد (ID: {$test_id}, Token: {$token})\n";
    }

    $link = "https://hekmatfoundation.org/interview-test.php?token={$token}";
    $dossier_link = "https://hekmatfoundation.org/interview-dossier.php?token={$token}";

    $results[] = [
        'id' => $test_id,
        'name' => $name,
        'mobile' => $mobile,
        'token' => $token,
        'test_link' => $link,
        'dossier_link' => $dossier_link
    ];
}

echo "\n📋 خلاصه پیوندها و اطلاعات داوطلبان جدید:\n";
foreach ($results as $res) {
    echo "----------------------------------------\n";
    echo "داوطلب: {$res['name']}\n";
    echo "شماره همراه: {$res['mobile']}\n";
    echo "شناسه آزمون: {$res['id']} | توکن: {$res['token']}\n";
    echo "لینک آزمون آنلاین:\n{$res['test_link']}\n";
    echo "لینک پرونده ارزیابی و مصاحبه:\n{$res['dossier_link']}\n";
}
echo "----------------------------------------\n";
echo "✅ عملیات با موفقیت پایان یافت.\n";
