<?php
/**
 * Dynamic Sponsorship Allocation Engine
 * Connects unsponsored active students to donors who have declared support (reminder_shares > current active sponsorships).
 */
require_once __DIR__ . '/../includes/db.php';

function auto_allocate_sponsorships($pdo, $target_donor_id = null) {
    $results = [];

    // 1. Find donors needing students
    $sql = "
        SELECT 
            d.id, 
            d.name || ' ' || d.surname as donor_name, 
            d.reminder_shares,
            COUNT(s.id) as current_count
        FROM donors d
        LEFT JOIN sponsorships s ON d.id = s.donor_id AND s.status = 'active'
        WHERE d.reminder_active = 1
    ";
    if ($target_donor_id) {
        $sql .= " AND d.id = " . (int)$target_donor_id;
    }
    $sql .= " GROUP BY d.id HAVING (d.reminder_shares - COUNT(s.id)) > 0 ORDER BY d.id ASC";

    $donors_needing = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

    if (empty($donors_needing)) {
        return ['success' => true, 'allocated' => 0, 'details' => [], 'message' => 'تمام خیرین فعال دارای تعداد کافی دانش‌آموز بورسیه می‌باشند.'];
    }

    $total_allocated = 0;
    $details = [];

    foreach ($donors_needing as $dn) {
        $donor_id = (int)$dn['id'];
        $donor_name = $dn['donor_name'];
        $needed = (int)$dn['reminder_shares'] - (int)$dn['current_count'];

        if ($needed <= 0) continue;

        // Fetch random unsponsored active students
        $st_query = $pdo->prepare("
            SELECT st.id, st.name || ' ' || st.surname as st_name, st.code, st.alias_name, st.grade
            FROM students st
            WHERE st.status IN ('active', 'university')
              AND st.id NOT IN (SELECT student_id FROM sponsorships WHERE status = 'active')
            ORDER BY RANDOM()
            LIMIT ?
        ");
        $st_query->execute([$needed]);
        $available_students = $st_query->fetchAll(PDO::FETCH_ASSOC);

        if (empty($available_students)) {
            $details[] = [
                'donor_id' => $donor_id,
                'donor_name' => $donor_name,
                'status' => 'no_students_available',
                'allocated' => 0
            ];
            continue;
        }

        $allocated_students_for_donor = [];
        $ins = $pdo->prepare("
            INSERT INTO sponsorships (donor_id, student_id, shares_count, start_date, status)
            VALUES (?, ?, 1, '۱۴۰۵/۰۷/۰۱', 'active')
        ");

        foreach ($available_students as $student) {
            $ins->execute([$donor_id, $student['id']]);
            $total_allocated++;
            $allocated_students_for_donor[] = [
                'student_id' => $student['id'],
                'student_name' => $student['st_name'],
                'code' => $student['code'],
                'alias' => $student['alias_name'] ?: 'حکمت‌جو #' . $student['id'],
                'grade' => $student['grade']
            ];
        }

        $details[] = [
            'donor_id' => $donor_id,
            'donor_name' => $donor_name,
            'needed' => $needed,
            'allocated' => count($allocated_students_for_donor),
            'students' => $allocated_students_for_donor
        ];
    }

    return [
        'success' => true,
        'allocated' => $total_allocated,
        'details' => $details,
        'message' => "تعداد $total_allocated دانش‌پژوه با موفقیت به خیرین متصل شدند."
    ];
}

// If run from CLI:
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['SCRIPT_FILENAME'])) {
    $target_id = isset($argv[1]) ? (int)$argv[1] : null;
    $res = auto_allocate_sponsorships($pdo, $target_id);
    echo json_encode($res, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
}
