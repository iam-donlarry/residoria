<?php
// receipt.php - Payment Receipt View & Printable Invoice Document
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/helpers.php';

if (!isset($_GET['id'])) {
    die("Invalid Receipt Request.");
}

$invoice_id = decodeId($_GET['id']);
$db = Database::getInstance();

$invoice = $db->fetchOne("
    SELECT i.*, 
           CONCAT(p.first_name, ' ', p.last_name) as resident_name, 
           p.email as resident_email, 
           p.primary_phone as resident_phone, 
           u.unit_number, 
           u.unit_code,
           b.name as building_name,
           z.name as street_name
    FROM invoices i 
    LEFT JOIN property_units u ON i.unit_id = u.id 
    LEFT JOIN buildings_blocks b ON u.building_id = b.id
    LEFT JOIN zones_streets z ON b.zone_id = z.id
    LEFT JOIN persons p ON i.payer_person_id = p.id 
    WHERE i.id = ?
", [$invoice_id]);

if (!$invoice) {
    die("<h3>Receipt Not Found</h3><p>The requested invoice payment receipt does not exist or has been removed.</p>");
}

$payment = $db->fetchOne("SELECT * FROM payments WHERE invoice_id = ? AND status = 'successful' ORDER BY id DESC LIMIT 1", [$invoice_id]);
if (!$payment) {
    $payment = $db->fetchOne("SELECT * FROM payments WHERE invoice_id = ? ORDER BY id DESC LIMIT 1", [$invoice_id]);
}

$items = $db->fetchAll("SELECT * FROM invoice_items WHERE invoice_id = ?", [$invoice_id]);

$sys_name = $db->fetchOne("SELECT setting_value FROM system_settings WHERE setting_key = 'estate_name'");
$estate_name = !empty($sys_name['setting_value']) ? $sys_name['setting_value'] : 'Residoria Estate';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?= e($invoice['invoice_number'] ?? sprintf('%04d', $invoice['id'])) ?> - <?= e(APP_NAME) ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Outfit', sans-serif;
            background: #f8fafc;
            color: #0f172a;
            padding: 2rem;
            margin: 0;
        }
        .receipt-card {
            background: #ffffff;
            max-width: 680px;
            margin: 0 auto;
            border-radius: 1rem;
            box-shadow: 0 10px 25px rgba(0,0,0,0.08);
            border: 1px solid #e2e8f0;
            padding: 3rem;
            position: relative;
        }
        .receipt-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 1.5rem;
            margin-bottom: 2rem;
        }
        .receipt-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: #12B1B0;
        }
        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
            font-size: 0.95rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 2rem 0;
        }
        th, td {
            padding: 0.85rem 1rem;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }
        th {
            background: #f8fafc;
            color: #64748b;
            font-size: 0.8rem;
            text-transform: uppercase;
        }
        .total-box {
            text-align: right;
            font-size: 1.25rem;
            font-weight: 700;
            color: #12B1B0;
            margin-top: 1.5rem;
        }
        @media print {
            body { background: #ffffff; padding: 0; }
            .receipt-card { box-shadow: none; border: none; padding: 0; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <div class="no-print" style="max-width: 680px; margin: 0 auto 1rem; text-align: right;">
        <button onclick="window.print()" style="padding: 0.6rem 1.2rem; background: #12B1B0; color: #fff; border: none; border-radius: 0.5rem; font-weight: 600; cursor: pointer;">
            Print Official Receipt
        </button>
    </div>

    <div class="receipt-card">
        <div class="receipt-header">
            <div>
                <div class="receipt-title">PAYMENT RECEIPT</div>
                <div style="color: #64748b; font-size: 0.9rem; margin-top: 0.25rem;">Receipt #REC-<?= sprintf('%05d', $invoice['id']) ?></div>
            </div>
            <div style="text-align: right;">
                <div style="font-weight: 700; font-size: 1.1rem; color: #0f172a;"><?= e($estate_name) ?></div>
                <div style="font-size: 0.85rem; color: #64748b;"><?= formatDate($payment['transaction_date'] ?? $invoice['created_at']) ?></div>
            </div>
        </div>

        <div class="meta-grid">
            <div>
                <strong style="color: #64748b; display: block; margin-bottom: 0.25rem;">BILLED TO:</strong>
                <div style="font-weight: 700; font-size: 1.05rem;"><?= e($invoice['resident_name'] ?: 'Property Occupant') ?></div>
                <div style="color: #64748b;"><?= e($invoice['resident_email'] ?: 'N/A') ?></div>
                <?php if (!empty($invoice['unit_number'])): ?>
                    <div style="color: #64748b; margin-top: 0.25rem;"><?= e($invoice['building_name']) ?> &bull; Unit <?= e($invoice['unit_number']) ?> (<code><?= e($invoice['unit_code']) ?></code>)</div>
                <?php endif; ?>
            </div>
            <div style="text-align: right;">
                <strong style="color: #64748b; display: block; margin-bottom: 0.25rem;">PAYMENT DETAILS:</strong>
                <div>Invoice #: <strong><?= e($invoice['invoice_number'] ?? sprintf('%04d', $invoice['id'])) ?></strong></div>
                <div>Method: <strong><?= e(strtoupper(str_replace('_', ' ', $payment['payment_method'] ?? 'manual'))) ?></strong></div>
                <div>Status: <span style="color: #10b981; font-weight: 700;"><?= strtoupper(e($invoice['status'])) ?> ✓</span></div>
                <?php if (!empty($payment['payment_reference'])): ?>
                    <div style="font-size: 0.8rem; color: #64748b; margin-top: 0.25rem;">Ref: <code><?= e($payment['payment_reference']) ?></code></div>
                <?php endif; ?>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th style="text-align: right;">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($items)): ?>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td style="font-weight: 500;"><?= e($item['description']) ?></td>
                        <td style="text-align: right; font-weight: 600;"><?= formatMoney($item['amount']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td style="font-weight: 500;"><?= e($invoice['title']) ?></td>
                        <td style="text-align: right; font-weight: 600;"><?= formatMoney($invoice['net_amount']) ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="total-box">
            Total Net Amount: <?= formatMoney($invoice['net_amount']) ?>
        </div>

        <div style="margin-top: 3rem; text-align: center; border-top: 1px solid #e2e8f0; padding-top: 1.5rem; color: #94a3b8; font-size: 0.85rem;">
            Thank you for your payment. This is an official digital receipt generated by <?= e($estate_name) ?>.
        </div>
    </div>
</body>
</html>
