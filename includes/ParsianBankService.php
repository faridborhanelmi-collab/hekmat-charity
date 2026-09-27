<?php
/**
 * Parsian Bank Corporate API & Batch Payment Service
 * بنیاد خیریه و نیکوکاری حکمت
 */

class ParsianBankService {
    private $pdo;
    private $settings;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->loadSettings();
    }

    /**
     * Load settings from bank_api_settings table
     */
    public function loadSettings() {
        $stmt = $this->pdo->query("SELECT * FROM bank_api_settings WHERE bank_name = 'parsian' LIMIT 1");
        $this->settings = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$this->settings) {
            // Default fallback
            $this->settings = [
                'bank_name' => 'parsian',
                'is_active' => 1,
                'is_sandbox' => 1,
                'api_url' => 'https://api.parsian-bank.ir/v1',
                'client_id' => '',
                'client_secret' => '',
                'api_key' => '',
                'source_account' => '0100234567890',
                'source_iban' => 'IR120540100234567890123456',
                'token' => '',
                'token_expires_at' => ''
            ];
        }
    }

    public function getSettings() {
        return $this->settings;
    }

    /**
     * Test connection / health check
     */
    public function testConnection() {
        if ($this->settings['is_sandbox']) {
            return [
                'success' => true,
                'message' => 'ارتباط با سرویس شبیه‌ساز (Sandbox) بانک پارسیان با موفقیت برقرار شد.',
                'mode' => 'sandbox',
                'latency_ms' => rand(45, 120),
                'server_time' => date('Y-m-d H:i:s')
            ];
        }

        // Live API Health Check
        try {
            $url = rtrim($this->settings['api_url'], '/') . '/health';
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: application/json',
                'X-API-Key: ' . $this->settings['api_key']
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300) {
                return [
                    'success' => true,
                    'message' => 'اتصال به وب‌سرویس اصلی بانک پارسیان با موفقیت برقرار است.',
                    'mode' => 'live'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'پاسخ ناموفق از سرور بانک پارسیان (کد خطا: ' . $httpCode . ')',
                    'mode' => 'live'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'خطا در برقراری اتصال با سرور بانک: ' . $e->getMessage(),
                'mode' => 'live'
            ];
        }
    }

    /**
     * Submit batch transfer (بچ پرداخت گروهی) to Parsian Bank
     *
     * @param int $list_id
     * @param array $items Array of selected items
     * @return array [success => bool, tracking_code => string, batch_id => string, message => string]
     */
    public function submitBatchTransfer($list_id, array $items) {
        if (empty($items)) {
            return ['success' => false, 'message' => 'هیچ ردیفی برای ارسال به بانک انتخاب نشده است.'];
        }

        $total_amount = 0;
        $transfer_records = [];

        foreach ($items as $idx => $item) {
            $amt = (int)$item['final_amount'];
            if ($amt <= 0) continue;
            
            $total_amount += $amt;
            $transfer_records[] = [
                'row' => $idx + 1,
                'student_id' => $item['student_id'],
                'student_name' => $item['student_name'],
                'account_number' => $item['account_number'] ?? '',
                'iban' => $item['iban'] ?? '',
                'amount_rials' => $amt,
                'description' => 'بورسیه حکمت - ' . $item['student_name']
            ];
        }

        if (empty($transfer_records)) {
            return ['success' => false, 'message' => 'مبلغ کل پرداختی‌های انتخاب‌شده صفر ریال است.'];
        }

        // 1. Simulation / Sandbox Mode
        if ($this->settings['is_sandbox']) {
            $batch_id = 'PRS-BATCH-' . date('Ymd') . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
            $tracking_code = 'TRK' . date('His') . rand(1000, 9999);
            
            $result_data = [
                'batch_id' => $batch_id,
                'tracking_code' => $tracking_code,
                'source_account' => $this->settings['source_account'],
                'source_iban' => $this->settings['source_iban'],
                'total_count' => count($transfer_records),
                'total_amount' => $total_amount,
                'status' => 'pending_signatures',
                'status_fa' => 'در انتظار امضای صاحبان امضا در سامانه پارسیان',
                'created_at' => date('Y/m/d H:i:s'),
                'records' => $transfer_records
            ];

            // Save to database
            $this->recordBatchSubmission($list_id, $batch_id, $tracking_code, 'pending_signatures', json_encode($result_data, JSON_UNESCAPED_UNICODE));

            return [
                'success' => true,
                'batch_id' => $batch_id,
                'tracking_code' => $tracking_code,
                'total_count' => count($transfer_records),
                'total_amount' => $total_amount,
                'status' => 'pending_signatures',
                'message' => 'بچ پرداخت گروهی با موفقیت در وب‌سرویس بانک پارسیان ثبت شد و در کارتابل امضای اینترنت‌بانک قرار گرفت.'
            ];
        }

        // 2. Live API Call to Parsian Bank
        try {
            $url = rtrim($this->settings['api_url'], '/') . '/corporate/batch-transfer';
            $payload = [
                'source_account' => $this->settings['source_account'],
                'source_iban' => $this->settings['source_iban'],
                'transfer_type' => 'PAYA',
                'reference_id' => 'HEKMAT-LIST-' . $list_id . '-' . time(),
                'items' => $transfer_records
            ];

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Accept: application/json',
                'Authorization: Bearer ' . $this->settings['token'],
                'X-API-Key: ' . $this->settings['api_key']
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if ($err) {
                return ['success' => false, 'message' => 'خطای ارتباط با سرور بانک: ' . $err];
            }

            $resJson = json_decode($response, true);
            if ($httpCode >= 200 && $httpCode < 300 && isset($resJson['batch_id'])) {
                $batch_id = $resJson['batch_id'];
                $tracking_code = $resJson['tracking_code'] ?? ('TRK' . time());
                $status = $resJson['status'] ?? 'pending_signatures';

                $this->recordBatchSubmission($list_id, $batch_id, $tracking_code, $status, $response);

                return [
                    'success' => true,
                    'batch_id' => $batch_id,
                    'tracking_code' => $tracking_code,
                    'status' => $status,
                    'message' => 'بچ با موفقیت به وب‌سرویس بانک ارسال شد.'
                ];
            } else {
                $errMsg = $resJson['message'] ?? ('خطای شماره ' . $httpCode . ' از بانک');
                return ['success' => false, 'message' => 'پاسخ ناموفق بانک: ' . $errMsg];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'استثنا در ارسال: ' . $e->getMessage()];
        }
    }

    /**
     * Record submission status in DB
     */
    private function recordBatchSubmission($list_id, $batch_id, $tracking_code, $status, $response_json) {
        $now = date('Y/m/d H:i:s');
        $stmt = $this->pdo->prepare("
            UPDATE monthly_bursary_lists 
            SET bank_batch_id = ?, 
                bank_tracking_code = ?, 
                bank_status = ?, 
                bank_submitted_at = ?, 
                bank_response_json = ? 
            WHERE id = ?
        ");
        $stmt->execute([$batch_id, $tracking_code, $status, $now, $response_json, $list_id]);
    }

    /**
     * Inquire Batch Status from Parsian API
     */
    public function checkBatchStatus($list_id, $batch_id) {
        if ($this->settings['is_sandbox']) {
            $stmt = $this->pdo->prepare("SELECT signed_bahraman, signed_sanobari FROM monthly_bursary_lists WHERE id = ?");
            $stmt->execute([$list_id]);
            $list = $stmt->fetch();

            if ($list && $list['signed_bahraman'] == 1 && $list['signed_sanobari'] == 1) {
                $status = 'executed';
                $status_fa = 'تایید و امضا شده - واریز با موفقیت انجام شد';
            } else {
                $status = 'pending_signatures';
                $status_fa = 'در انتظار امضای صاحبان امضا در سامانه پارسیان';
            }

            return [
                'success' => true,
                'batch_id' => $batch_id,
                'status' => $status,
                'status_fa' => $status_fa,
                'updated_at' => date('Y/m/d H:i:s')
            ];
        }

        // Live API inquiry
        try {
            $url = rtrim($this->settings['api_url'], '/') . '/corporate/batch-status/' . urlencode($batch_id);
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Accept: application/json',
                'Authorization: Bearer ' . $this->settings['token'],
                'X-API-Key: ' . $this->settings['api_key']
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            $response = curl_exec($ch);
            curl_close($ch);

            $resJson = json_decode($response, true);
            return [
                'success' => true,
                'batch_id' => $batch_id,
                'status' => $resJson['status'] ?? 'unknown',
                'status_fa' => $resJson['status_fa'] ?? $resJson['status'] ?? 'در حال پردازش',
                'raw' => $resJson
            ];
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Generate Parsian Standard Batch File for manual upload
     */
    public function generateParsianStandardBatchFile($list, array $items) {
        $filename = "parsian_batch_" . $list['year'] . "_" . $list['month'] . ".csv";
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        // Output UTF-8 BOM for Persian Excel compatibility
        echo "\xEF\xBB\xBF";
        
        $output = fopen('php://output', 'w');
        
        // Parsian Corporate Batch Standard Headers
        fputcsv($output, [
            'ردیف',
            'شماره حساب / شبا مقصد',
            'نام صاحب حساب',
            'مبلغ (ریال)',
            'شناسه واریز / کد پیگیری',
            'شرح تراکنش',
            'نوع انتقال (پایا/داخلی)'
        ]);
        
        $idx = 1;
        foreach ($items as $item) {
            if (isset($item['is_selected']) && $item['is_selected'] == 0) {
                continue;
            }
            $amt = (int)$item['final_amount'];
            if ($amt <= 0) continue;

            $desc = "بورسیه تحصیلی " . $list['month'] . " " . $list['year'] . " بنیاد حکمت";
            $acc_or_iban = !empty($item['account_number']) ? $item['account_number'] : ($item['iban'] ?? '');
            
            $type = (strpos(strtoupper($acc_or_iban), 'IR') === 0) ? 'پایا' : 'داخلی پارسیان';

            fputcsv($output, [
                $idx++,
                $acc_or_iban,
                $item['student_name'],
                $amt,
                '1405' . str_pad($item['student_id'], 4, '0', STR_PAD_LEFT),
                $desc,
                $type
            ]);
        }
        
        fclose($output);
        exit();
    }
}
