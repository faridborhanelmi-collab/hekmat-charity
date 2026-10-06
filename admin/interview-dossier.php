<?php
// ارجاع مستقیم به صفحه پرونده و کارنامه تحلیلی روانسنجی
$qs = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header("Location: /interview-dossier.php" . $qs, true, 301);
exit();
