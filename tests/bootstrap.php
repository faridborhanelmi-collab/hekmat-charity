<?php
// tests/bootstrap.php
// Bootstrap environment for running regression unit tests

$fixtures_db = __DIR__ . '/fixtures/test_hekmat.db';
if (!file_exists($fixtures_db)) {
    require_once __DIR__ . '/fixtures/setup_test_db.php';
}

putenv("HEKMAT_DB_PATH={$fixtures_db}");
$_ENV['HEKMAT_DB_PATH'] = $fixtures_db;

// Ensure clean session environment
if (session_status() === PHP_SESSION_NONE) {
    // Avoid header errors in CLI
    @session_start();
}

require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';

/**
 * Lightweight Anti-Regression Assertion Framework
 */
class RegressionTest {
    public static int $passed = 0;
    public static int $failed = 0;
    public static array $errors = [];

    public static function assert($condition, string $message = ''): void {
        if ($condition) {
            self::$passed++;
            echo "  \033[32m✔ PASS\033[0m: {$message}\n";
        } else {
            self::$failed++;
            self::$errors[] = $message;
            echo "  \033[31m✖ FAIL (REGRESSION DETECTED)\033[0m: {$message}\n";
        }
    }

    public static function assertEquals($expected, $actual, string $message = ''): void {
        $detail = " [Expected: " . json_encode($expected, JSON_UNESCAPED_UNICODE) . ", Got: " . json_encode($actual, JSON_UNESCAPED_UNICODE) . "]";
        self::assert($expected === $actual, $message . ($expected !== $actual ? $detail : ''));
    }

    public static function assertTrue($condition, string $message = ''): void {
        self::assert($condition === true, $message);
    }

    public static function assertFalse($condition, string $message = ''): void {
        self::assert($condition === false, $message);
    }

    public static function suite(string $name, callable $fn): void {
        echo "\n\033[1;34m▶ Testing Suite: {$name}\033[0m\n";
        $fn();
    }

    public static function summary(): int {
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "\033[1mREGRESSION TEST SUMMARY:\033[0m\n";
        echo "  Passed: \033[32m" . self::$passed . "\033[0m\n";
        echo "  Failed: \033[31m" . self::$failed . "\033[0m\n";
        if (self::$failed > 0) {
            echo "\n\033[1;31m❌ REGRESSION DETECTED! The following assertions failed:\033[0m\n";
            foreach (self::$errors as $idx => $err) {
                echo "  " . ($idx + 1) . ") " . $err . "\n";
            }
            echo "\n";
            return 1;
        } else {
            echo "\n\033[1;32m✅ ALL REGRESSION TESTS PASSED! Safe to deploy.\033[0m\n\n";
            return 0;
        }
    }
}
