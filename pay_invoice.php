<?php
// pay_invoice.php - Standalone Paystack Invoice Checkout View
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/PaymentGateway.php';

if (!isset($_GET['id'])) {
    die("<h3>Invalid Request</h3><p>No invoice ID provided.</p>");
}

$invoice_id = decodeId($_GET['id']);
$estate_id = get_estate_id();
$db = Database::getInstance();

$invoice = $db->fetchOne("SELECT i.*, u.name, u.email 
                         FROM invoices i 
                         JOIN users u ON i.user_id = u.id 
                         WHERE i.id = ? AND i.estate_id = ?", [$invoice_id, $estate_id]);

if (!$invoice) {
    die("<h3>Invoice Not Found</h3><p>The invoice you are looking for does not exist.</p>");
}

$gateway = PaymentGateway::createDriver('paystack', $estate_id);
$paystackKey = $gateway->getPublicKey();
$amountInKobo = $invoice['amount'] * 100;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pay Invoice #<?= e($invoice['invoice_number'] ?? sprintf('%04d', $invoice['id'])) ?> - <?= e(APP_NAME) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://js.paystack.co/v1/inline.js"></script>
    <link rel="stylesheet" href="assets/css/style.css?v=<?= APP_VERSION ?>">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            margin: 0;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            color: #1e293b;
        }
        .payment-card {
            background: #ffffff;
            border-radius: var(--radius-xl);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 460px;
            padding: 3rem 2.25rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .payment-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 6px;
            background: var(--primary);
        }
        .amount-display {
            font-size: 3rem;
            font-weight: 700;
            color: var(--text-primary);
            margin: 1.5rem 0;
        }
    </style>
</head>
<body>
    <div class="payment-card">
        <div style="width: 56px; height: 56px; background: var(--primary-light); color: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 1.25rem;">
            <i data-lucide="receipt" style="width: 32px; height: 32px;"></i>
        </div>
        
        <span class="badge <?= $invoice['status'] === 'paid' ? 'badge-success' : 'badge-danger' ?>">
            <?= e($invoice['status']) ?>
        </span>

        <h1 style="font-size: 1.5rem; font-weight: 700; margin-top: 1rem; color: var(--text-primary);">
            Invoice #<?= e($invoice['invoice_number'] ?? sprintf('%04d', $invoice['id'])) ?>
        </h1>
        <p style="color: var(--text-muted); font-size: 1rem;"><?= e($invoice['title']) ?></p>

        <div class="amount-display">
            <?= formatMoney($invoice['amount']) ?>
        </div>

        <div style="background: #f8fafc; padding: 1.25rem; border-radius: var(--radius-lg); text-align: left; margin-bottom: 2rem; font-size: 0.9rem; border: 1px solid var(--border-color);">
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                <span style="color: var(--text-muted);">Billed To:</span>
                <span style="font-weight: 600; color: var(--text-primary);"><?= e($invoice['name']) ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                <span style="color: var(--text-muted);">Email:</span>
                <span style="font-weight: 500; color: var(--text-primary);"><?= e($invoice['email']) ?></span>
            </div>
            <div style="display: flex; justify-content: space-between;">
                <span style="color: var(--text-muted);">Due Date:</span>
                <span style="font-weight: 600; color: var(--danger);"><?= formatDate($invoice['due_date']) ?></span>
            </div>
        </div>

        <?php if ($invoice['status'] === 'unpaid'): ?>
            <button type="button" id="paystack-btn" class="btn btn-primary" style="width: 100%; padding: 1rem; font-size: 1.05rem;">
                <i data-lucide="credit-card" style="width: 20px; height: 20px;"></i> Pay Now Online
            </button>
        <?php else: ?>
            <div class="badge badge-success" style="padding: 0.75rem 1.5rem; font-size: 0.9rem;">
                ✓ Payment Received
            </div>
            <div style="margin-top: 1.5rem;">
                <a href="admin/receipt.php?id=<?= $invoice['id'] ?>" class="btn btn-secondary btn-sm">Download Receipt</a>
            </div>
        <?php endif; ?>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (window.lucide) window.lucide.createIcons();

            const payBtn = document.getElementById('paystack-btn');
            if (payBtn) {
                payBtn.addEventListener('click', function() {
                    const handler = PaystackPop.setup({
                        key: '<?= e($paystackKey) ?>',
                        email: '<?= e($invoice['email']) ?>',
                        amount: <?= $amountInKobo ?>,
                        currency: 'NGN',
                        ref: 'INV_PAY_' + <?= $invoice['id'] ?> + '_' + Math.floor((Math.random() * 1000000000) + 1),
                        callback: function(response) {
                            window.location.href = 'verify_payment.php?reference=' + response.reference + '&invoice_id=<?= $invoice['id'] ?>';
                        },
                        onClose: function() {
                            alert('Transaction cancelled.');
                        }
                    });
                    handler.openIframe();
                });
            }
        });
    </script>
</body>
</html>
