<?php
// classes/PaymentGateway.php - Generic Payment Processing Abstraction

interface PaymentGatewayInterface {
    public function getPublicKey();
    public function initializePayment($invoiceId, $amount, $email, $callbackUrl);
    public function verifyPayment($reference);
}

class PaymentGateway {
    public static function createDriver($driverName = 'paystack', $estateId = 1) {
        if ($driverName === 'paystack') {
            require_once __DIR__ . '/PaystackDriver.php';
            return new PaystackDriver($estateId);
        }
        throw new Exception("Unsupported payment driver: " . $driverName);
    }
}
