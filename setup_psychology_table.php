<?php
require_once 'includes/db.php';
$pdo->exec("CREATE TABLE IF NOT EXISTS student_psychology (
    student_id INTEGER PRIMARY KEY,
    hermans_score INTEGER,
    hermans_grade TEXT,
    scl90_so REAL,
    scl90_ob REAL,
    scl90_is REAL,
    scl90_de REAL,
    scl90_an REAL,
    scl90_ag REAL,
    scl90_ph REAL,
    scl90_pa REAL,
    scl90_ps REAL,
    scl90_gsi REAL,
    scl90_pst INTEGER,
    scl90_psdi REAL,
    scl90_risk TEXT,
    mi_total INTEGER,
    mi_grade TEXT,
    eq_total INTEGER,
    eq_grade TEXT,
    final_grade TEXT,
    recommendation TEXT,
    FOREIGN KEY (student_id) REFERENCES students(id)
);");
echo "Table created.\n";
?>
