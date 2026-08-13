<?php
// classes/PaystackDriver.php - Paystack Payment Gateway Driver

require_once __DIR__ . '/PaymentGateway.php';
require_once __DIR__ . '/Database.php';

class PaystackDriver implements PaymentGatewayInterface {
    private $db;
    private $estateId;
    private $publicKey;
    private $secretKey;

    public function __construct($estateId = 1) {
        $this->db = Database::getInstance();
        $this->estateId = $estateId;
        $this->loadKeys();
    }

    private function loadKeys() {
        $rows = $this->db->fetchAll("SELECT setting_key, setting_value FROM system_settings WHERE estate_id = ?", [$this->estateId]);
        $sys = [];
        foreach ($rows as $r) {
            $sys[$r['setting_key']] = $r['setting_value'];
        }
        $this->publicKey = $sys['paystack_public_key'] ?? '';
        $this->secretKey = $sys['paystack_secret_key'] ?? '';
    }

    public function getPublicKey() {
        return $this->publicKey;
    }

    public function initializePayment($invoiceId, $amount, $email, $callbackUrl) {
        return [
            'key' => $this->publicKey,
            'email' => $email,
            'amount' => $amount * 100, // In kobo
            'ref' => 'PAY_' . time() . '_' . rand(1000, 9999),
            'callback_url' => $callbackUrl
        ];
    }

    public function verifyPayment($reference) {
        if (empty($this->secretKey)) {
            return ['status' => false, 'message' => 'Paystack secret key is missing.'];
        }

        $url = "https://api.paystack.co/transaction/verify/" . rawurlencode($reference);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer " . $this->secretKey,
            "Cache-Control: no-cache"
        ]);

        $result = curl_exec($ch);
        curl_close($ch);

        if ($result) {
            $resData = json_decode($result, true);
            if (isset($resData['data']) && $resData['data']['status'] === 'success') {
                return [
                    'status' => true,
                    'amount' => $resData['data']['amount'] / 100,
                    'reference' => $reference,
                    'raw' => $resData['data']
                ];
            }
        }
        return ['status' => false, 'message' => 'Verification failed.'];
    }
}
