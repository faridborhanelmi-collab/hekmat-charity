<?php
require_once 'includes/db.php';

$json_file = 'hekmat_all_students_profiles.json';
if (!file_exists($json_file)) {
    die("Error: JSON file not found.\n");
}

$data = json_decode(file_get_contents($json_file), true);
if (!$data) {
    die("Error: Invalid JSON format.\n");
}

// نگاشت مستقیم اسامی با تفاوت‌های نگارشی خاص
$aliases = [
    "مریم قرنی" => 732,           // مریم قزئی
    "ایمان رسول خوانی" => 715,     // ایمان رسولی
    "حسنا عظیمی" => 718,          // حسنا عظیمی خراسانی
    "سیده فرزانه جواهری" => 740,   // فرزانه جواهری
    "سیده فاطمه جواهری" => 741     // فاطمه جواهری
];

$all_students = $pdo->query("SELECT id, name, surname FROM students")->fetchAll(PDO::FETCH_ASSOC);

function cleanName($str) {
    $str = preg_replace("/^(سید|سیده)\s+/", "", trim($str));
    $str = str_replace(["\u{200C}", "‌", " ", "ئ", "ي", "ك"], ["", "", "", "ی", "ی", "ک"], $str);
    return trim($str);
}

function numOrNull($val) {
    if ($val === null || $val === '' || $val === '-') return null;
    return is_numeric($val) ? $val : null;
}

$matched_count = 0;
$unmatched_students = [];

$stmt = $pdo->prepare("INSERT OR REPLACE INTO student_psychology (
    student_id, hermans_score, hermans_grade,
    scl90_so, scl90_ob, scl90_is, scl90_de, scl90_an, scl90_ag, scl90_ph, scl90_pa, scl90_ps, scl90_gsi, scl90_pst, scl90_psdi, scl90_risk,
    mi_total, mi_grade, eq_total, eq_grade, final_grade, recommendation
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

foreach ($data as $student) {
    $name = trim($student['name']);
    $cName = cleanName($name);
    $student_id = null;
    
    if (isset($aliases[$name])) {
        $student_id = $aliases[$name];
    } else {
        foreach ($all_students as $db_st) {
            $db_fullName = cleanName($db_st['name'] . $db_st['surname']);
            if ($db_fullName === $cName) {
                $student_id = $db_st['id'];
                break;
            }
        }
    }
    
    if ($student_id) {
        $scl90 = $student['scl90'] ?? [];
        
        $stmt->execute([
            $student_id,
            numOrNull($student['hermans_score'] ?? null),
            $student['hermans_grade'] ?? null,
            numOrNull($scl90['SO'] ?? null),
            numOrNull($scl90['OB'] ?? null),
            numOrNull($scl90['IS'] ?? null),
            numOrNull($scl90['DE'] ?? null),
            numOrNull($scl90['AN'] ?? null),
            numOrNull($scl90['AG'] ?? null),
            numOrNull($scl90['PH'] ?? null),
            numOrNull($scl90['PA'] ?? null),
            numOrNull($scl90['PS'] ?? null),
            numOrNull($scl90['GSI'] ?? null),
            numOrNull($scl90['PST'] ?? null),
            numOrNull($scl90['PSDI'] ?? null),
            $scl90['risk'] ?? null,
            numOrNull($student['mi_total'] ?? null),
            $student['mi_grade'] ?? null,
            numOrNull($student['eq_total'] ?? null),
            $student['eq_grade'] ?? null,
            $student['final_grade'] ?? null,
            $student['recommendation'] ?? null
        ]);
        
        $matched_count++;
    } else {
        $unmatched_students[] = $name;
    }
}

echo "Successfully matched and imported: $matched_count / " . count($data) . " students into student_psychology.\n";
if (!empty($unmatched_students)) {
    echo "Unmatched:\n";
    print_r($unmatched_students);
}
?>
