<?php
// tests/run_e2e_http_tests.php
// Automated End-to-End Regression HTTP Flow Runner for Hekmat Charity

$test_db_path = realpath(__DIR__ . '/fixtures/test_hekmat.db');
if (!file_exists($test_db_path)) {
    require_once __DIR__ . '/fixtures/setup_test_db.php';
    $test_db_path = realpath(__DIR__ . '/fixtures/test_hekmat.db');
}

$port = 8089;
$server_url = "http://127.0.0.1:{$port}";
$cmd = sprintf(
    'HEKMAT_DB_PATH=%s php -S 127.0.0.1:%d -t %s > /dev/null 2>&1 & echo $!',
    escapeshellarg($test_db_path),
    $port,
    escapeshellarg(realpath(__DIR__ . '/..'))
);

echo "\n\033[1;36m============================================================\033[0m\n";
echo "\033[1;36m      HEKMAT CHARITY AUTOMATED E2E REGRESSION HTTP TEST     \033[0m\n";
echo "\033[1;36m============================================================\033[0m\n";

$pid = trim(shell_exec($cmd));
usleep(500000); // 500ms wait for server to bind

register_shutdown_function(function() use ($pid) {
    if (!empty($pid) && is_numeric($pid)) {
        @exec("kill -9 {$pid} > /dev/null 2>&1");
    }
});

// HTTP Helper using cURL with cookie jar
class HttpSession {
    private string $cookie_file;
    private string $base_url;

    public function __construct(string $base_url) {
        $this->base_url = rtrim($base_url, '/');
        $this->cookie_file = tempnam(sys_get_temp_dir(), 'hekmat_cookie_');
    }

    public function request(string $method, string $path, array $data = []): array {
        $ch = curl_init();
        $url = $this->base_url . $path;
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookie_file);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookie_file);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_PROXY, '');
        curl_setopt($ch, CURLOPT_NOPROXY, '*');
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        if (strtoupper($method) === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $header_size);
        if (PHP_VERSION_ID < 80500) {
            @curl_close($ch);
        }

        return [
            'code' => $http_code,
            'headers' => $headers,
            'body' => $body
        ];
    }

    public function __destruct() {
        if (file_exists($this->cookie_file)) {
            @unlink($this->cookie_file);
        }
    }
}

$passed = 0;
$failed = 0;
$errors = [];

$assert_flow = function(bool $condition, string $description) use (&$passed, &$failed, &$errors) {
    if ($condition) {
        $passed++;
        echo "  \033[32m✔ PASS\033[0m: {$description}\n";
    } else {
        $failed++;
        $errors[] = $description;
        echo "  \033[31m✖ FAIL (REGRESSION DETECTED)\033[0m: {$description}\n";
    }
};

// 1. Test Public Pages
echo "\n\033[1;34m▶ [Flow 1] Public Website Availability & Health\033[0m\n";
$guest = new HttpSession($server_url);

$res = $guest->request('GET', '/index.php');
$assert_flow($res['code'] === 200, 'Homepage (index.php) returns HTTP 200 OK');
$assert_flow(str_contains($res['body'], 'حکمت'), 'Homepage contains charity branding');

$res = $guest->request('GET', '/about.php');
$assert_flow($res['code'] === 200, 'About page (about.php) returns HTTP 200 OK');

$res = $guest->request('GET', '/almas.php');
$assert_flow($res['code'] === 200, 'Diamond project (almas.php) returns HTTP 200 OK');

// 2. Test Superadmin Login & Dashboard
echo "\n\033[1;34m▶ [Flow 2] Superadmin Authentication & Executive Flow\033[0m\n";
$admin_session = new HttpSession($server_url);

$step1 = $admin_session->request('POST', '/login.php', ['step' => 1, 'username' => 'faridelmi']);
$assert_flow($step1['code'] === 200, 'Superadmin step 1 username validated successfully');

$step2 = $admin_session->request('POST', '/login.php', ['step' => 2, 'password' => '123456']);
$assert_flow($step2['code'] === 302, 'Superadmin login successful with HTTP 302 redirect');
$assert_flow(str_contains($step2['headers'], 'admin/index.php'), 'Superadmin redirected to admin/index.php');

$dashboard = $admin_session->request('GET', '/admin/index.php');
$assert_flow($dashboard['code'] === 200, 'Admin dashboard loads with HTTP 200 OK');

$bursary = $admin_session->request('GET', '/admin/bursary-payments.php');
$assert_flow($bursary['code'] === 200, 'Bursary payments page loads with HTTP 200 OK');

$financial = $admin_session->request('GET', '/admin/financial.php');
$assert_flow($financial['code'] === 200, 'Financial ledger page loads with HTTP 200 OK');

// 3. Test Data Operator & RBAC 403 Enforcement (REGRESSION CHECK)
echo "\n\033[1;34m▶ [Flow 3] Data Operator RBAC Restrictions (REGRESSION CHECK)\033[0m\n";
$operator_session = new HttpSession($server_url);

$step1 = $operator_session->request('POST', '/login.php', ['step' => 1, 'username' => 'viana']);
$assert_flow($step1['code'] === 200, 'Data operator step 1 username validated');

$step2 = $operator_session->request('POST', '/login.php', ['step' => 2, 'password' => '123456']);
$assert_flow($step2['code'] === 302, 'Data operator login successful with redirect');

// REGRESSION CHECK: Operator MUST receive 403 on financial page
$blocked = $operator_session->request('GET', '/admin/financial.php');
$assert_flow($blocked['code'] === 403, 'REGRESSION CHECK: Data operator strictly blocked with HTTP 403 from financial.php');
$assert_flow(str_contains($blocked['body'], 'دسترسی مسدود است'), 'Blocked page displays access denied message');

// 4. Test Student Detail View & API Handler
echo "\n\033[1;34m▶ [Flow 4] Student Management & API Handler\033[0m\n";
$student_view = $admin_session->request('GET', '/person-detail.php?id=1');
$assert_flow($student_view['code'] === 200, 'Student detail (person-detail.php?id=1) loads with HTTP 200 OK');
$assert_flow(str_contains($student_view['body'], 'علی'), 'Student record contains student name');

// Summary
echo "\n" . str_repeat('=', 60) . "\n";
echo "\033[1mE2E AUTOMATED REGRESSION SUMMARY:\033[0m\n";
echo "  Passed: \033[32m{$passed}\033[0m\n";
echo "  Failed: \033[31m{$failed}\033[0m\n";

if ($failed > 0) {
    echo "\n\033[1;31m❌ REGRESSION DETECTED in Automated E2E Flows!\033[0m\n";
    exit(1);
} else {
    echo "\n\033[1;32m✅ ALL " . ($passed) . " AUTOMATED E2E FLOWS PASSED! Zero Regressions Found.\033[0m\n\n";
    exit(0);
}
