<?php
try {
    $pdo = new PDO('sqlite:hekmat.db');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("ALTER TABLE students ADD COLUMN password TEXT;");
    echo "Success";
} catch(Exception $e) {
    echo $e->getMessage();
}
?>
