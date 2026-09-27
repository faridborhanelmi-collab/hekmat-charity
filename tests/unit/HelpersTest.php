<?php
// tests/unit/HelpersTest.php
// Regression Tests for Helper Functions, Currency Formatting & Jalali Dates

require_once __DIR__ . '/../bootstrap.php';

// Include cleanNumber helper if not already defined
if (!function_exists('cleanNumber')) {
    function cleanNumber($val) {
        if (empty($val)) return 0;
        $farsi = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹','٬',',',' '];
        $latin = ['0','1','2','3','4','5','6','7','8','9','','',''];
        $cleaned = str_replace($farsi, $latin, (string)$val);
        return (int)preg_replace('/[^0-9]/', '', $cleaned);
    }
}

RegressionTest::suite('Formatting, Currency & Jalali Date Regression Tests', function() {
    // 1. toFarsiDigits
    RegressionTest::assertEquals('۱۲۳۴۵۶۷۸۹۰', toFarsiDigits('1234567890'), 'Converting latin digits to Farsi');
    RegressionTest::assertEquals('', toFarsiDigits(''), 'Empty string should return empty string');
    RegressionTest::assertEquals('', toFarsiDigits(null), 'Null input should return empty string');

    // 2. formatFarsiCurrency (Regression check for positive and negative values)
    $positive_formatted = formatFarsiCurrency(25000000);
    RegressionTest::assertEquals('۲۵,۰۰۰,۰۰۰', $positive_formatted, 'Positive currency must not have a minus sign');

    $negative_formatted = formatFarsiCurrency(-5000000);
    RegressionTest::assertEquals('-۵,۰۰۰,۰۰۰', $negative_formatted, 'REGRESSION CHECK: Negative currency must have a minus sign prefix');

    $zero_formatted = formatFarsiCurrency(0);
    RegressionTest::assertEquals('۰', $zero_formatted, 'Zero currency should be formatted as ۰');

    // 3. cleanNumber (Parsing Persian user inputs into valid integers)
    RegressionTest::assertEquals(20000000, cleanNumber('۲۰,۰۰۰,۰۰۰'), 'Parsing Farsi numbers with commas');
    RegressionTest::assertEquals(15000000, cleanNumber('15,000,000'), 'Parsing Latin numbers with commas');
    RegressionTest::assertEquals(30000000, cleanNumber('۳۰٬۰۰۰٬۰۰۰'), 'Parsing Farsi numbers with Persian thousands separator');
    RegressionTest::assertEquals(0, cleanNumber(''), 'Empty string returns 0');

    // 4. gregorian_to_jalali
    $j_date = gregorian_to_jalali(2024, 3, 20); // 1403/01/01
    RegressionTest::assertEquals([1403, 1, 1], $j_date, 'Gregorian 2024/03/20 corresponds to Jalali 1403/01/01');

    $j_date2 = gregorian_to_jalali(2025, 3, 20); // 1403/12/30 (or 1404)
    RegressionTest::assertTrue($j_date2[0] === 1403 || $j_date2[0] === 1404, 'Jalali year conversion for March 20');

    // 5. formatJalaliDateTime
    $formatted_dt = formatJalaliDateTime('2024-03-20 10:30:00');
    RegressionTest::assertTrue(str_contains($formatted_dt, '۱۴۰۳/۰۱/۰۱'), 'Formatted datetime contains Jalali date');
    RegressionTest::assertTrue(str_contains($formatted_dt, '۱۰:۳۰'), 'Formatted datetime contains Farsi time');
    RegressionTest::assertEquals('---', formatJalaliDateTime(''), 'Empty datetime returns placeholder');

    // 6. time_ago_fa
    $just_now = date('Y-m-d H:i:s', time() - 10);
    RegressionTest::assertEquals('لحظاتی پیش', time_ago_fa($just_now), 'Recent timestamp should display لحظاتی پیش');

    $few_mins = date('Y-m-d H:i:s', time() - 300);
    RegressionTest::assertTrue(str_contains(time_ago_fa($few_mins), 'دقیقه پیش'), 'Timestamp 5 mins ago displays دقیقه پیش');
});
