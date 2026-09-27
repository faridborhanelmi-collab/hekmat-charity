<?php
// tests/run_unit_tests.php
// Master Runner for all PHP Regression Unit Tests

require_once __DIR__ . '/bootstrap.php';

echo "\n\033[1;36m============================================================\033[0m\n";
echo "\033[1;36m       HEKMAT CHARITY ANTI-REGRESSION UNIT TEST RUNNER      \033[0m\n";
echo "\033[1;36m============================================================\033[0m\n";

require_once __DIR__ . '/unit/AuthRbacTest.php';
require_once __DIR__ . '/unit/HelpersTest.php';
require_once __DIR__ . '/unit/BursaryLogicTest.php';
require_once __DIR__ . '/unit/ValidationTest.php';
require_once __DIR__ . '/unit/SmsServiceTest.php';
require_once __DIR__ . '/unit/CampaignSponsorshipFlowTest.php';

$status = RegressionTest::summary();
exit($status);
