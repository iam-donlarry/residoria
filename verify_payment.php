<?php
// verify_payment.php - Secure Payment Verification Handler
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/PaymentGateway.php';

$reference = sanitize($_GET['reference'] ?? $_GET['trxref'] ?? '');
$invoice_id = intval($_GET['invoice_id'] ?? 0);
$estate_id = get_estate_id();
$db = Database::getInstance();

if (empty($reference) || $invoice_id <= 0) {
    die("Invalid payment verification request.");
}

try {
    $gateway = PaymentGateway::createDriver('paystack', $estate_id);
    $result = $gateway->verifyPayment($reference);

    if ($result['status'] === true) {
        $invoice = $db->fetchOne("SELECT * FROM invoices WHERE id = ? AND estate_id = ?", [$invoice_id, $estate_id]);
        if ($invoice) {
            $db->beginTransaction();

            // 1. Update Invoice Status
            $db->query("UPDATE invoices SET status = 'paid' WHERE id = ?", [$invoice_id]);

            // 2. Insert Payment Record
            $db->query("INSERT INTO payments (estate_id, user_id, invoice_id, amount, type, payment_method, status, transaction_ref, transaction_date) 
                        VALUES (?, ?, ?, ?, 'invoice_payment', 'paystack', 'paid', ?, CURDATE())", [
                $estate_id, $invoice['user_id'], $invoice_id, $invoice['amount'], $reference
            ]);
            $payment_id = $db->lastInsertId();

            // 3. Insert Payment Allocation
            $db->query("INSERT INTO payment_allocations (payment_id, invoice_id, allocated_amount) VALUES (?, ?, ?)", [
                $payment_id, $invoice_id, $invoice['amount']
            ]);

            $db->commit();

            logAudit($conn, "Verify Payment", "Payments", "Verified payment of " . formatMoney($invoice['amount']) . " for invoice #" . $invoice['id']);

            header("Location: admin/receipt.php?id=" . $invoice_id . "&success=1");
            exit;
        }
    }

    echo "<h3>Payment Verification Warning</h3><p>We could not automatically verify the transaction reference with Paystack. If funds were debited, please contact administration with reference: <code>" . e($reference) . "</code>.</p>";
    echo "<p><a href='pay_invoice.php?id=" . $invoice_id . "'>Return to Invoice</a></p>";

} catch (Exception $e) {
    echo "<h3>Payment Verification Error</h3><p>" . e($e->getMessage()) . "</p>";
}
