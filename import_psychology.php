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

$unmatched_students = [];
$matched_count = 0;

foreach ($data as $student) {
    $name = trim($student['name']);
    
    // Attempt to match by name in database
    // Some names might have prefix/suffix or slightly different spelling.
    // Try exact match first
    $stmt = $pdo->prepare("SELECT id FROM students WHERE name || ' ' || surname = ? OR name = ?");
    $stmt->execute([$name, $name]);
    $db_student = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$db_student) {
        // Try LIKE match if exact match fails
        $parts = explode(' ', $name);
        if (count($parts) >= 2) {
            // First word and last word match
            $first = $parts[0];
            $last = end($parts);
            $stmt = $pdo->prepare("SELECT id, name, surname FROM students WHERE (name LIKE ? AND surname LIKE ?) OR (name LIKE ?)");
            $stmt->execute(["%{$first}%", "%{$last}%", "%{$name}%"]);
            $db_student = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    }
    
    if ($db_student) {
        $student_id = $db_student['id'];
        
        // Insert or Replace into student_psychology
        $stmt = $pdo->prepare("INSERT OR REPLACE INTO student_psychology (
            student_id, hermans_score, hermans_grade,
            scl90_so, scl90_ob, scl90_is, scl90_de, scl90_an, scl90_ag, scl90_ph, scl90_pa, scl90_ps, scl90_gsi, scl90_pst, scl90_psdi, scl90_risk,
            mi_total, mi_grade, eq_total, eq_grade, final_grade, recommendation
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $scl90 = $student['scl90'] ?? [];
        
        $stmt->execute([
            $student_id,
            $student['hermans_score'],
            $student['hermans_grade'],
            $scl90['SO'] ?? null,
            $scl90['OB'] ?? null,
            $scl90['IS'] ?? null,
            $scl90['DE'] ?? null,
            $scl90['AN'] ?? null,
            $scl90['AG'] ?? null,
            $scl90['PH'] ?? null,
            $scl90['PA'] ?? null,
            $scl90['PS'] ?? null,
            $scl90['GSI'] ?? null,
            $scl90['PST'] ?? null,
            $scl90['PSDI'] ?? null,
            $scl90['risk'] ?? null,
            $student['mi_total'] ?? null,
            $student['mi_grade'] ?? null,
            $student['eq_total'] ?? null,
            $student['eq_grade'] ?? null,
            $student['final_grade'] ?? null,
            $student['recommendation'] ?? null
        ]);
        
        $matched_count++;
    } else {
        $unmatched_students[] = $name;
    }
}

echo "Successfully matched and imported: $matched_count students.\n";
if (count($unmatched_students) > 0) {
    echo "Unmatched students (Not found in DB):\n";
    foreach ($unmatched_students as $uname) {
        echo "- $uname\n";
    }
}
?>
