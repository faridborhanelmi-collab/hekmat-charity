<?php
// tests/lint_php.php
// Fast In-Process PHP Syntax Integrity Linter (Zero-subprocess, instantaneous)

echo "\n\033[1;36m============================================================\033[0m\n";
echo "\033[1;36m         HEKMAT CHARITY PHP SYNTAX INTEGRITY LINTER         \033[0m\n";
echo "\033[1;36m============================================================\033[0m\n";

$root_dir = realpath(__DIR__ . '/..');
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($root_dir, RecursiveDirectoryIterator::SKIP_DOTS)
);

$php_files = [];
foreach ($iterator as $path => $file) {
    if (str_contains($path, '/node_modules/') || str_contains($path, '/.git/') || str_contains($path, '/.gemini/')) {
        continue;
    }
    if ($file->isFile() && $file->getExtension() === 'php') {
        $php_files[] = $path;
    }
}

sort($php_files);
$total = count($php_files);
$errors = [];

echo "🔍 Scanning {$total} PHP files for syntax integrity...\n";

foreach ($php_files as $file) {
    $rel_path = str_replace($root_dir . '/', '', $file);
    $code = file_get_contents($file);
    try {
        // TOKEN_PARSE tells PHP engine to validate full syntax grammar
        @token_get_all($code, TOKEN_PARSE);
    } catch (ParseError $e) {
        $errors[$rel_path] = $e->getMessage() . " on line " . $e->getLine();
        echo "  \033[31m✖ SYNTAX ERROR\033[0m: {$rel_path}: {$e->getMessage()}\n";
    } catch (Throwable $e) {
        $errors[$rel_path] = $e->getMessage();
        echo "  \033[31m✖ ERROR\033[0m: {$rel_path}: {$e->getMessage()}\n";
    }
}

if (empty($errors)) {
    echo "  \033[32m✔ All {$total} PHP files have 100% valid syntax!\033[0m\n\n";
    exit(0);
} else {
    echo "\n\033[1;31m❌ Syntax errors detected in " . count($errors) . " files:\033[0m\n";
    foreach ($errors as $f => $err) {
        echo "\nFile: {$f}\nError: {$err}\n";
    }
    exit(1);
}
