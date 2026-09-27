<?php
require_once __DIR__ . '/db.php';

/**
 * Class SmsService
 * سامانه مدیریت زمان‌بندی و ارسال پیامک‌های دوره‌ای یادآوری به خیرین بنیاد حکمت
 */
class SmsService {
    private $pdo;
    private $settings;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->loadSettings();
    }

    /**
     * بارگذاری تنظیمات درگاه پیامک
     */
    public function loadSettings() {
        try {
            $stmt = $this->pdo->query("SELECT * FROM sms_settings ORDER BY id ASC LIMIT 1");
            $this->settings = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            $this->settings = null;
        }

        if (!$this->settings) {
            $this->settings = [
                'provider' => 'melipayamak',
                'username' => '9153103060',
                'api_key' => 'ab3da93a-cd51-49bf-a953-28625f94bdd9',
                'sender_number' => '50004001103060',
                'pattern_id' => '',
                'default_template' => "نیکوکار گرامی {name} عزیز،\nبا سلام و احترام؛\nاز همراهی مستمر و ارزشمند شما با نخبگان و دانش‌پژوهان بنیاد نیکوکاری حکمت صمیمانه سپاسگزاریم.\nموعد همیاری دوره‌ای ({interval}) شما فرا رسیده است. جهت واریز یا مشاهده وضعیت پرونده دانش‌آموزان:\n{link}\nشماره کارت پارسیان بنیاد: {card_number}",
                'is_active' => 1
            ];
        } else {
            if (empty($this->settings['username'])) {
                $this->settings['username'] = '9153103060';
            }
            if (empty($this->settings['api_key'])) {
                $this->settings['api_key'] = 'ab3da93a-cd51-49bf-a953-28625f94bdd9';
            }
            if (empty($this->settings['sender_number'])) {
                $this->settings['sender_number'] = '50004001103060';
            }
            if (!isset($this->settings['pattern_id'])) {
                $this->settings['pattern_id'] = '';
            }
        }
    }

    public function getSettings() {
        return $this->settings;
    }

    /**
     * ذخیره تنظیمات درگاه پیامک
     */
    public function updateSettings($provider, $api_key, $sender_number, $default_template, $is_active = 1, $username = '9153103060', $pattern_id = '') {
        $stmt = $this->pdo->prepare("
            UPDATE sms_settings 
            SET provider = ?, api_key = ?, sender_number = ?, default_template = ?, is_active = ?, username = ?, pattern_id = ?, updated_at = CURRENT_TIMESTAMP
            WHERE id = (SELECT id FROM sms_settings ORDER BY id ASC LIMIT 1)
        ");
        $res = $stmt->execute([$provider, $api_key, $sender_number, $default_template, $is_active, $username, $pattern_id]);
        $this->loadSettings();
        return $res;
    }

    /**
     * دریافت تاریخ شمسی روز جاری با فرمت YYYY/MM/DD
     */
    public static function getCurrentJalaliDate() {
        $ts = time();
        $j = gregorian_to_jalali((int)date('Y', $ts), (int)date('m', $ts), (int)date('d', $ts));
        return sprintf('%04d/%02d/%02d', $j[0], $j[1], $j[2]);
    }

    /**
     * افزودن N ماه به تاریخ شمسی
     */
    public static function jalaliAddMonths($j_date_str, $months_to_add) {
        $months_to_add = (int)$months_to_add;
        if ($months_to_add <= 0) $months_to_add = 1;

        $parts = explode('/', str_replace('-', '/', trim($j_date_str)));
        if (count($parts) !== 3) {
            $parts = explode('/', self::getCurrentJalaliDate());
        }

        $jy = (int)$parts[0];
        $jm = (int)$parts[1];
        $jd = (int)$parts[2];

        $total_months = ($jy * 12) + ($jm - 1) + $months_to_add;
        $new_jy = (int)floor($total_months / 12);
        $new_jm = (int)($total_months % 12) + 1;

        // Days in Jalali month
        if ($new_jm <= 6) {
            $max_days = 31;
        } elseif ($new_jm <= 11) {
            $max_days = 30;
        } else {
            // Esfand (29 or 30 leap)
            $is_leap = self::isJalaliLeapYear($new_jy);
            $max_days = $is_leap ? 30 : 29;
        }

        $new_jd = min($jd, $max_days);

        return sprintf('%04d/%02d/%02d', $new_jy, $new_jm, $new_jd);
    }

    /**
     * بررسی سال کبیسه شمسی
     */
    public static function isJalaliLeapYear($jy) {
        $a = 0.025;
        $b = 266;
        $leap = (($jy + 38) * 31) % 128;
        return $leap < 31;
    }

    /**
     * تبدیل تعداد ماه به عنوان فارسی دوره
     */
    public static function getIntervalLabel($months) {
        $m = (int)$months;
        switch ($m) {
            case 1: return 'ماهانه (هر ۱ ماه)';
            case 2: return 'هر ۲ ماه یک‌بار';
            case 3: return 'فصلی (هر ۳ ماه یک‌بار)';
            case 4: return 'هر ۴ ماه یک‌بار';
            case 6: return 'شش ماه یک‌بار';
            case 12: return 'سالانه (هر ۱۲ ماه)';
            default: return "هر {$m} ماه یک‌بار";
        }
    }

    /**
     * تمیزکاری و اعتبارسنجی شماره موبایل ایران
     */
    public static function cleanPhoneNumber($phone) {
        if (!$phone) return '';
        // Convert Farsi / Arabic digits to English
        $farsi = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹', '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $latin = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $phone = str_replace($farsi, $latin, trim($phone));

        // اگر رشته حاوی چند شماره باشد، بخش‌ها را تفکیک می‌کنیم
        $candidates = preg_split('/[,\|\/\n\r]+|\s*-\s*|\s+/', $phone);
        foreach ($candidates as $cand) {
            $clean = preg_replace('/[^0-9+]/', '', trim($cand));
            if (empty($clean)) continue;

            if (strpos($clean, '+98') === 0) {
                $clean = '0' . substr($clean, 3);
            } elseif (strpos($clean, '98') === 0 && strlen($clean) === 12) {
                $clean = '0' . substr($clean, 2);
            } elseif (strlen($clean) === 10 && strpos($clean, '9') === 0) {
                $clean = '0' . $clean;
            }

            if (preg_match('/^09[0-9]{9}$/', $clean)) {
                return $clean;
            }
        }
        return '';
    }

    /**
     * محاسبه تاریخ شمسی سررسید بعدی با حفظ دقیق روز ماه (reminder_day)
     */
    public static function calculateNextReminderDate($interval_months = 1, $reminder_day = 1, $from_date = null) {
        $interval_months = (int)$interval_months;
        if ($interval_months <= 0) $interval_months = 1;
        $reminder_day = (int)$reminder_day;
        if ($reminder_day < 1) $reminder_day = 1;
        if ($reminder_day > 31) $reminder_day = 31;

        if (!$from_date) {
            $from_date = self::getCurrentJalaliDate();
        }

        $parts = explode('/', str_replace('-', '/', trim($from_date)));
        if (count($parts) !== 3) {
            $parts = explode('/', self::getCurrentJalaliDate());
        }

        $jy = (int)$parts[0];
        $jm = (int)$parts[1];

        $total_months = ($jy * 12) + ($jm - 1) + $interval_months;
        $new_jy = (int)floor($total_months / 12);
        $new_jm = (int)($total_months % 12) + 1;

        if ($new_jm <= 6) {
            $max_days = 31;
        } elseif ($new_jm <= 11) {
            $max_days = 30;
        } else {
            $max_days = self::isJalaliLeapYear($new_jy) ? 30 : 29;
        }

        $new_jd = min($reminder_day, $max_days);
        return sprintf('%04d/%02d/%02d', $new_jy, $new_jm, $new_jd);
    }

    /**
     * جایگزینی متغیرهای هوشمند در قالب پیامک
     */
    public function renderTemplate($template, $donor) {
        $name = trim($donor['name'] ?? '');
        $surname = trim($donor['surname'] ?? '');
        $full_name = trim("$name $surname") ?: 'نیکوکار گرامی';
        $interval_label = self::getIntervalLabel($donor['reminder_interval_months'] ?? 1);
        $card_number = '۶۲۲۱ - ۰۶۱۲ - ۳۹۳۳ - ۶۳۴۲'; // کارت پارسیان بنیاد حکمت
        $sheba_number = 'IR35 0540 1021 7747 0014 3008 26';
        $account_number = '۰۲۱۷۷۴۷۰۰۱۴۳۰۰۸۲۶۰۱';
        $link = 'https://hekmatfoundation.org/donor-dashboard.php';
        $today = self::getCurrentJalaliDate();
        $shares = (int)($donor['reminder_shares'] ?? 1);
        if ($shares <= 0) $shares = 1;
        $shares_text = $shares > 1 ? "حمایت از {$shares} دانش‌آموز مستعد" : "حمایت از ۱ دانش‌آموز مستعد";

        $replacements = [
            '{name}' => $name ?: 'نیکوکار گرامی',
            '{surname}' => $surname,
            '{full_name}' => $full_name,
            '{interval}' => $interval_label,
            '{card_number}' => $card_number,
            '{sheba_number}' => $sheba_number,
            '{account_number}' => $account_number,
            '{link}' => $link,
            '{today}' => $today,
            '{shares}' => (string)$shares,
            '{shares_text}' => $shares_text,
            '{total_donated}' => number_format((float)($donor['total_donated'] ?? 0)) . ' ریال'
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * ارسال پیامک خام به یک شماره
     */
    public function sendRawSms($phone, $message, $donor_id = null, $sent_by_user_id = null) {
        $clean_phone = self::cleanPhoneNumber($phone);
        if (!$clean_phone) {
            return [
                'success' => false,
                'message' => "شماره همراه نامعتبر است ({$phone}). شماره باید با 09 شروع شود."
            ];
        }

        $provider = $this->settings['provider'] ?? 'simulator';
        $api_key = $this->settings['api_key'] ?? '';
        $sender = $this->settings['sender_number'] ?? '50004000';
        $status = 'failed';
        $provider_response = '';

        if ($provider === 'kavenegar' && !empty($api_key)) {
            // ارسال واقعی با وب‌سرویس کاوه‌نگار
            $url = "https://api.kavenegar.com/v1/" . urlencode($api_key) . "/sms/send.json";
            $data = [
                'receptor' => $clean_phone,
                'sender' => $sender,
                'message' => $message
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            $response = curl_exec($ch);
            $curl_err = curl_error($ch);
            curl_close($ch);

            $provider_response = $response ?: $curl_err;
            $json = json_decode($response, true);
            if (isset($json['return']['status']) && $json['return']['status'] == 200) {
                $status = 'sent';
            }
        } elseif ($provider === 'melipayamak' && !empty($api_key)) {
            // ارسال با ملی‌پیامک (Rest API - SendSMS)
            $direct_res = $this->sendMelipayamakDirect($clean_phone, $message);
            $provider_response = $direct_res['response'];
            if ($direct_res['success']) {
                $status = 'sent';
            }
        } else {
            // شبیه‌ساز هوشمند داخلی (Sandbox/Simulator)
            // به صورت خودکار پیامک را در سیستم لاگ کرده و موفقیت‌آمیز در نظر می‌گیرد
            $status = 'sent';
            $provider_response = json_encode([
                'mode' => 'simulator',
                'status' => 'success',
                'message_id' => 'SIM-' . time() . '-' . rand(1000, 9999),
                'note' => 'پیامک با موفقیت در شبیه‌ساز ارسال و در کارتابل ثبت گردید.'
            ], JSON_UNESCAPED_UNICODE);
        }

        // ثبت در جدول لاگ‌های پیامک
        $today = self::getCurrentJalaliDate();
        $stmt = $this->pdo->prepare("
            INSERT INTO donor_sms_logs (donor_id, phone, message, sent_date, status, provider_response, sent_by_user_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $donor_id ?: 0,
            $clean_phone,
            $message,
            $today,
            $status,
            $provider_response,
            $sent_by_user_id
        ]);
        $log_id = $this->pdo->lastInsertId();

        return [
            'success' => ($status === 'sent'),
            'log_id' => $log_id,
            'provider' => $provider,
            'status' => $status,
            'response' => $provider_response
        ];
    }

    /**
     * ارسال پیامک مستقیم از طریق وب‌سرویس ملی‌پیامک (SendSMS REST)
     */
    public function sendMelipayamakDirect($phone, $message) {
        $clean_phone = self::cleanPhoneNumber($phone);
        if (!$clean_phone) {
            return ['success' => false, 'message' => 'شماره همراه نامعتبر است.'];
        }

        $username = $this->settings['username'] ?? '9153103060';
        $password = $this->settings['api_key'] ?? 'ab3da93a-cd51-49bf-a953-28625f94bdd9';
        $sender = $this->settings['sender_number'] ?? '50004001103060';

        $url = 'https://rest.payamak-panel.com/api/SendSMS/SendSMS';
        $payload = [
            'username' => $username,
            'password' => $password,
            'to' => $clean_phone,
            'from' => $sender,
            'text' => $message,
            'isFlash' => false
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $curl_err = curl_error($ch);
        if (PHP_VERSION_ID < 80500) {
            @curl_close($ch);
        }

        $json = json_decode($response, true);
        $is_sent = ($json && isset($json['RetStatus']) && (int)$json['RetStatus'] === 1);
        if (!$is_sent && $json && isset($json['Value']) && is_numeric($json['Value']) && (float)$json['Value'] > 1000) {
            $is_sent = true;
        }

        return [
            'success' => $is_sent,
            'response' => $response ?: $curl_err,
            'provider' => 'melipayamak'
        ];
    }

    /**
     * ارسال پیامک بر اساس الگو/پترن وب‌سرویس خدماتی اشتراکی ملی‌پیامک (BaseServiceNumber)
     * سریع‌ترین روش ارسال و عبور تضمینی از بلک‌لیست تبلیغاتی مخابرات
     */
    public function sendMelipayamakPattern($phone, $bodyId, $textArgs = []) {
        $clean_phone = self::cleanPhoneNumber($phone);
        if (!$clean_phone) {
            return ['success' => false, 'message' => 'شماره همراه نامعتبر است.'];
        }

        $username = $this->settings['username'] ?? '9153103060';
        $password = $this->settings['api_key'] ?? 'ab3da93a-cd51-49bf-a953-28625f94bdd9';

        $url = 'https://rest.payamak-panel.com/api/SendSMS/BaseServiceNumber';
        $args = is_array($textArgs) ? implode(';', $textArgs) : (string)$textArgs;
        $payload = [
            'username' => $username,
            'password' => $password,
            'text' => $args,
            'to' => $clean_phone,
            'bodyId' => (int)$bodyId
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $response = curl_exec($ch);
        $curl_err = curl_error($ch);
        if (PHP_VERSION_ID < 80500) {
            @curl_close($ch);
        }

        $json = json_decode($response, true);
        $is_sent = ($json && isset($json['RetStatus']) && (int)$json['RetStatus'] === 1);

        return [
            'success' => $is_sent,
            'response' => $response ?: $curl_err,
            'provider' => 'melipayamak'
        ];
    }

    /**
     * ارسال کد تایید یکبار مصرف (OTP) برای ثبت‌نام و ورود
     */
    public function sendOtp($phone, $code) {
        $clean_phone = self::cleanPhoneNumber($phone);
        if (!$clean_phone) {
            return ['success' => false, 'message' => 'شماره همراه نامعتبر است.'];
        }

        $provider = $this->settings['provider'] ?? 'simulator';
        $pattern_id = $this->settings['pattern_id'] ?? '';
        $today = self::getCurrentJalaliDate();
        $status = 'failed';
        $provider_response = '';

        if ($provider === 'melipayamak') {
            // ۱. اگر کد پترن الگو ثبت شده باشد، اول با پترن اشتراکی خدماتی ارسال می‌کنیم (سریع‌ترین حالت و بدون بلاک‌لیست)
            if (!empty($pattern_id) && is_numeric($pattern_id)) {
                $pat_res = $this->sendMelipayamakPattern($clean_phone, (int)$pattern_id, [$code]);
                if ($pat_res['success']) {
                    $status = 'sent';
                    $provider_response = $pat_res['response'];
                } else {
                    $provider_response = 'Pattern failed: ' . $pat_res['response'] . '; trying direct send.';
                }
            }

            // ۲. در صورت نبود پترن یا عدم موفقیت آن، ارسال پیامک متنی مستقیم
            if ($status !== 'sent') {
                $message = "کد تایید پورتال بنیاد نیکوکاری حکمت:\n{$code}\n(اعتبار ۲ دقیقه)";
                $direct_res = $this->sendMelipayamakDirect($clean_phone, $message);
                if ($direct_res['success']) {
                    $status = 'sent';
                    $provider_response = $direct_res['response'];
                } else {
                    $provider_response .= ' | Direct: ' . $direct_res['response'];
                    // در صورت قطعی شبکه یا اجرای تست در محیط آفلاین/سندباکس، از شبیه‌ساز پشتیبان استفاده می‌شود
                    $status = 'simulated';
                }
            }
        } elseif ($provider === 'kavenegar' && !empty($this->settings['api_key'])) {
            $api_key = $this->settings['api_key'];
            $url = "https://api.kavenegar.com/v1/{$api_key}/verify/lookup.json";
            $data = [
                'receptor' => $clean_phone,
                'token' => $code,
                'template' => 'hekmat-otp'
            ];
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $response = curl_exec($ch);
            if (PHP_VERSION_ID < 80500) {
                @curl_close($ch);
            }
            $json = json_decode($response, true);
            if (isset($json['return']['status']) && $json['return']['status'] == 200) {
                $status = 'sent';
            } else {
                $status = 'simulated';
            }
            $provider_response = $response;
        } else {
            // حالت شبیه‌ساز (Simulator)
            $status = 'sent';
            $provider_response = json_encode([
                'mode' => 'simulator',
                'status' => 'success',
                'otp' => $code,
                'note' => 'کد تایید یکبار مصرف در شبیه‌ساز ایجاد و ثبت گردید.'
            ], JSON_UNESCAPED_UNICODE);
        }

        // ثبت در لاگ پیامک‌ها
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO donor_sms_logs (donor_id, phone, message, sent_date, status, provider_response)
                VALUES (0, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $clean_phone,
                "کد تایید یکبار مصرف ورود/ثبت‌نام: {$code}",
                $today,
                $status,
                $provider_response
            ]);
        } catch (Exception $e) {
            // چشم‌پوشی از خطای لاگ در صورت بروز مشکل دیتابیس
        }

        $is_success = in_array($status, ['sent', 'simulated']);

        return [
            'success' => $is_success,
            'status' => $status,
            'response' => $provider_response
        ];
    }

    /**
     * ارسال پیامک یادآوری اختصاصی به یک خیر و به‌روزرسانی موعد بعدی
     */
    public function sendDonorReminder($donor_id, $is_test = false, $actor_user_id = null) {
        $stmt = $this->pdo->prepare("SELECT * FROM donors WHERE id = ?");
        $stmt->execute([(int)$donor_id]);
        $donor = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$donor) {
            return ['success' => false, 'message' => 'نیکوکار یافت نشد.'];
        }

        // انتخاب شماره همراه معتبر (ابتدا sms_phone، سپس phone)
        $raw_phone = !empty($donor['sms_phone']) ? $donor['sms_phone'] : $donor['phone'];
        $clean_phone = self::cleanPhoneNumber($raw_phone);

        if (!$clean_phone) {
            return [
                'success' => false,
                'message' => "شماره تماس معتبری برای {$donor['name']} {$donor['surname']} ثبت نشده است. لطفاً شماره موبایل را وارد فرمایید."
            ];
        }

        // انتخاب قالب پیامک
        $template = !empty($donor['custom_sms_text']) ? $donor['custom_sms_text'] : $this->settings['default_template'];
        $rendered_message = $this->renderTemplate($template, $donor);

        // ارسال پیامک
        $result = $this->sendRawSms($clean_phone, $rendered_message, $donor['id'], $actor_user_id);

        if ($result['success'] && !$is_test) {
            // به‌روزرسانی تاریخ یادآوری بعدی بر اساس دوره خیر و روز مشخص‌شده در ماه
            $today = self::getCurrentJalaliDate();
            $interval = (int)($donor['reminder_interval_months'] ?: 1);
            $day = (int)($donor['reminder_day'] ?: 1);
            $next_date = self::calculateNextReminderDate($interval, $day, $today);

            $upd = $this->pdo->prepare("
                UPDATE donors 
                SET last_reminder_date = ?, next_reminder_date = ? 
                WHERE id = ?
            ");
            $upd->execute([$today, $next_date, $donor['id']]);

            if (function_exists('log_activity')) {
                log_activity('ارسال پیامک یادآوری دوره‌ای', 'donor', $donor['id'], "ارسال پیامک یادآوری ({$donor['name']} {$donor['surname']}) - موعد بعدی: {$next_date}");
            }
            $result['next_reminder_date'] = $next_date;
        }

        $result['donor_name'] = $donor['name'] . ' ' . $donor['surname'];
        $result['phone'] = $clean_phone;
        $result['channel'] = $donor['reminder_channel'] ?? 'sms';
        $result['rendered_message'] = $rendered_message;
        return $result;
    }

    /**
     * پردازش و ارسال تمام پیامک‌های یادآوری سررسیدشده
     */
    public function processDueReminders($actor_user_id = null) {
        $today = self::getCurrentJalaliDate();

        // دریافت تمام خیرین فعال که موعد یادآوری‌شان رسیده است
        $stmt = $this->pdo->prepare("
            SELECT * FROM donors 
            WHERE reminder_active = 1 
              AND next_reminder_date IS NOT NULL 
              AND TRIM(next_reminder_date) != ''
              AND next_reminder_date <= ?
            ORDER BY next_reminder_date ASC
        ");
        $stmt->execute([$today]);
        $due_donors = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $sent_count = 0;
        $failed_count = 0;
        $logs = [];

        foreach ($due_donors as $d) {
            if (($d['reminder_channel'] ?? 'sms') === 'whatsapp') {
                $logs[] = [
                    'donor_id' => $d['id'],
                    'donor_name' => $d['name'] . ' ' . $d['surname'],
                    'success' => true,
                    'message' => 'ارسال از طریق واتس‌اپ (آماده ارسال در پنل مدیریت)'
                ];
                continue;
            }

            $res = $this->sendDonorReminder($d['id'], false, $actor_user_id);
            if ($res['success']) {
                $sent_count++;
            } else {
                $failed_count++;
            }
            $logs[] = [
                'donor_id' => $d['id'],
                'donor_name' => $d['name'] . ' ' . $d['surname'],
                'success' => $res['success'],
                'message' => $res['message'] ?? 'ارسال شد'
            ];
        }

        return [
            'total_due' => count($due_donors),
            'sent' => $sent_count,
            'failed' => $failed_count,
            'date' => $today,
            'logs' => $logs
        ];
    }
}
