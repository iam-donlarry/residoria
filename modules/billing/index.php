<?php
// modules/billing/index.php - Finance, Idempotent Batch Billing Engine & Tariffs
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../classes/ReferenceGenerator.php';
require_once __DIR__ . '/../../classes/SystemConfig.php';

requirePermission('billing.view');

$db = Database::getInstance();
$config = new SystemConfig();
$message = "";
$error = "";

// 1. SAVE TARIFF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_tariff'])) {
    requirePermission('settings.manage');
    try {
        verifyCsrfToken();
        $name = sanitize($_POST['name']);
        $cat = sanitize($_POST['category'] ?? 'service_charge');
        $amt = floatval($_POST['amount']);
        $freq = sanitize($_POST['frequency'] ?? 'monthly');
        $due_days = intval($_POST['due_days'] ?? 30);
        $penalty = floatval($_POST['penalty_percent'] ?? 0.00);
        $payer_role = sanitize($_POST['default_payer_role'] ?? 'tenant');

        if (empty($name) || $amt <= 0) {
            $error = "Tariff name and valid amount are required.";
        } else {
            $db->query("INSERT INTO tariffs (name, category, amount, frequency, due_days, penalty_percent, is_active, default_payer_role) VALUES (?, ?, ?, ?, ?, ?, 1, ?) ON DUPLICATE KEY UPDATE name = VALUES(name), amount = VALUES(amount), default_payer_role = VALUES(default_payer_role)", [
                $name, $cat, $amt, $freq, $due_days, $penalty, $payer_role
            ]);
            $message = "Estate tariff fee saved successfully!";
            logAudit($conn, "Save Tariff", "Billing", "Saved tariff $name ($amt)");
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// 2. GENERATE SINGLE INVOICE FOR PROPERTY UNIT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_invoice'])) {
    requirePermission('billing.create');
    try {
        verifyCsrfToken();
        $unit_id = intval($_POST['unit_id']);
        $payer_p_id = !empty($_POST['payer_person_id']) ? intval($_POST['payer_person_id']) : null;
        $title = sanitize($_POST['title']);
        $tariff_id = intval($_POST['tariff_id'] ?? 0);
        $custom_amt = floatval($_POST['amount']);
        $due_date = sanitize($_POST['due_date'] ?? date('Y-m-d', strtotime('+30 days')));

        if ($unit_id <= 0 || empty($title) || $custom_amt <= 0) {
            $error = "Property unit, title, and valid invoice amount are required.";
        } else {
            $inv_num = ReferenceGenerator::generate('invoice');
            $db->query("INSERT INTO invoices (invoice_number, unit_id, payer_person_id, title, total_amount, penalty_amount, discount_amount, net_amount, status, due_date) VALUES (?, ?, ?, ?, ?, 0.00, 0.00, ?, 'unpaid', ?)", [
                $inv_num, $unit_id, $payer_p_id, $title, $custom_amt, $custom_amt, $due_date
            ]);
            $invId = intval($db->lastInsertId());

            $db->query("INSERT INTO invoice_items (invoice_id, tariff_id, description, amount) VALUES (?, ?, ?, ?)", [
                $invId, ($tariff_id > 0 ? $tariff_id : null), $title, $custom_amt
            ]);

            $message = "Invoice #$inv_num generated successfully for Unit ID #$unit_id!";
            logAudit($conn, "Generate Invoice", "Billing", "Generated invoice #$inv_num (Amount: ₦$custom_amt)");
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// 3. RUN IDEMPOTENT BATCH MONTHLY BILLING ENGINE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_batch_billing'])) {
    requirePermission('billing.create');
    try {
        verifyCsrfToken();
        $tariff_id = intval($_POST['tariff_id']);
        $target_group = sanitize($_POST['target_group'] ?? 'all_occupied');
        $b_start = sanitize($_POST['billing_period_start']);
        $b_end = sanitize($_POST['billing_period_end']);
        $due_date = sanitize($_POST['due_date']);

        $tariff = $db->fetchOne("SELECT * FROM tariffs WHERE id = ?", [$tariff_id]);
        if (!$tariff || empty($b_start) || empty($b_end) || empty($due_date)) {
            $error = "Valid tariff, billing period, and due date are required.";
        } else {
            $pdo = $db->getPdo();
            $pdo->beginTransaction();

            $batch_ref = 'BATCH-' . date('Ym') . '-' . strtoupper(substr(md5($tariff['name'] . time()), 0, 6));

            // Select qualifying units based on target group
            $sqlUnits = "SELECT u.id, u.usage_class FROM property_units u WHERE 1=1";
            if ($target_group === 'all_occupied') {
                $sqlUnits .= " AND u.status = 'occupied'";
            } elseif ($target_group === 'residential_only') {
                $sqlUnits .= " AND u.status = 'occupied' AND u.usage_class = 'residential'";
            } elseif ($target_group === 'commercial_only') {
                $sqlUnits .= " AND u.status = 'occupied' AND u.usage_class IN ('commercial', 'mixed')";
            }

            $targetUnits = $db->fetchAll($sqlUnits);

            $generatedCount = 0;
            $skippedDuplicates = 0;
            $noPayerCount = 0;

            foreach ($targetUnits as $target) {
                $uId = intval($target['id']);

                // IDEMPOTENCY CHECK: Skip if invoice already generated for this unit, tariff title & billing start date
                $checkDuplicate = $db->fetchOne("
                    SELECT id FROM invoices 
                    WHERE unit_id = ? AND title = ? AND billing_period_start = ?
                ", [$uId, $tariff['name'], $b_start]);

                if ($checkDuplicate) {
                    $skippedDuplicates++;
                    continue;
                }

                // PAYER RESOLUTION ALGORITHM
                $payer_person_id = null;
                $payerRole = $tariff['default_payer_role'] ?? 'tenant';

                if ($payerRole === 'tenant') {
                    // Try active Head of Household / Resident
                    $res = $db->fetchOne("SELECT person_id FROM residents WHERE unit_id = ? AND status = 'active' ORDER BY is_head_of_household DESC LIMIT 1", [$uId]);
                    if ($res) {
                        $payer_person_id = intval($res['person_id']);
                    } else {
                        // Fallback to Owner
                        $own = $db->fetchOne("SELECT person_id FROM property_ownership WHERE unit_id = ? AND is_current = 1 AND owner_type = 'individual' LIMIT 1", [$uId]);
                        if ($own) $payer_person_id = intval($own['person_id']);
                    }
                } else {
                    // Owner role tariff
                    $own = $db->fetchOne("SELECT person_id FROM property_ownership WHERE unit_id = ? AND is_current = 1 AND owner_type = 'individual' LIMIT 1", [$uId]);
                    if ($own) $payer_person_id = intval($own['person_id']);
                }

                if (!$payer_person_id) {
                    $noPayerCount++;
                }

                $inv_num = ReferenceGenerator::generate('invoice');
                $amt = (float)$tariff['amount'];

                $db->query("
                    INSERT INTO invoices (invoice_number, unit_id, payer_person_id, title, total_amount, penalty_amount, discount_amount, net_amount, status, due_date, billing_period_start, billing_period_end, batch_reference) 
                    VALUES (?, ?, ?, ?, ?, 0.00, 0.00, ?, 'unpaid', ?, ?, ?, ?)
                ", [
                    $inv_num, $uId, $payer_person_id, $tariff['name'], $amt, $amt, $due_date, $b_start, $b_end, $batch_ref
                ]);
                $invId = intval($db->lastInsertId());

                $db->query("INSERT INTO invoice_items (invoice_id, tariff_id, description, amount) VALUES (?, ?, ?, ?)", [
                    $invId, $tariff['id'], $tariff['name'], $amt
                ]);

                $generatedCount++;
            }

            $pdo->commit();
            $message = "BATCH BILLING RUN COMPLETED! Generated $generatedCount invoices under Ref: <strong>$batch_ref</strong> ($skippedDuplicates duplicates skipped).";
            logAudit($conn, "Run Batch Billing", "Billing", "Generated $generatedCount invoices under batch $batch_ref");
        }
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Batch Billing Error: " . $e->getMessage();
    }
}

// 4. CANCEL BILLING BATCH
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_batch'])) {
    requirePermission('billing.manage');
    try {
        verifyCsrfToken();
        $batch_ref = sanitize($_POST['batch_reference']);
        if (!empty($batch_ref)) {
            $affected = $db->execute("UPDATE invoices SET status = 'cancelled' WHERE batch_reference = ? AND status = 'unpaid'", [$batch_ref]);
            $message = "BATCH CANCELLED! Voided $affected unpaid invoices under batch reference <strong>" . e($batch_ref) . "</strong>.";
            logAudit($conn, "Cancel Billing Batch", "Billing", "Cancelled batch $batch_ref (Affected: $affected)");
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// 5. RECORD MANUAL PAYMENT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payment'])) {
    requirePermission('payments.record');
    try {
        verifyCsrfToken();
        $inv_id = intval($_POST['invoice_id']);
        $pay_amt = floatval($_POST['payment_amount']);
        $pay_method = sanitize($_POST['payment_method'] ?? 'bank_transfer');
        $notes = sanitize($_POST['notes'] ?? '');

        $inv = $db->fetchOne("SELECT * FROM invoices WHERE id = ?", [$inv_id]);
        if (!$inv || $pay_amt <= 0) {
            $error = "Invalid invoice or payment amount.";
        } else {
            $pay_ref = ReferenceGenerator::generate('payment');
            $db->query("INSERT INTO payments (payment_reference, invoice_id, payer_person_id, amount, payment_method, status, transaction_date, notes) VALUES (?, ?, ?, ?, ?, 'successful', NOW(), ?)", [
                $pay_ref, $inv_id, $inv['payer_person_id'], $pay_amt, $pay_method, $notes
            ]);
            $payId = intval($db->lastInsertId());

            $db->query("INSERT INTO payment_allocations (payment_id, invoice_id, allocated_amount) VALUES (?, ?, ?)", [
                $payId, $inv_id, $pay_amt
            ]);

            // Update invoice status
            $newPaidTotal = (float)$pay_amt;
            $newStatus = ($newPaidTotal >= (float)$inv['net_amount']) ? 'paid' : 'partial';
            $db->query("UPDATE invoices SET status = ? WHERE id = ?", [$newStatus, $inv_id]);

            $message = "Payment of " . formatMoney($pay_amt) . " recorded successfully (Ref: #$pay_ref)!";
            logAudit($conn, "Record Payment", "Payments", "Recorded payment $pay_ref for Invoice #{$inv['invoice_number']}");
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch Billing Summaries
$invoices = $db->fetchAll("
    SELECT inv.*, u.unit_number, u.unit_code, b.name as building_name,
           CONCAT(p.first_name, ' ', p.last_name) as payer_name
    FROM invoices inv
    JOIN property_units u ON inv.unit_id = u.id
    JOIN buildings_blocks b ON u.building_id = b.id
    LEFT JOIN persons p ON inv.payer_person_id = p.id
    ORDER BY inv.created_at DESC
");

$batchHistory = $db->fetchAll("
    SELECT batch_reference, title, COUNT(*) as invoice_count, SUM(net_amount) as total_batch_amount,
           MIN(created_at) as created_at,
           SUM(CASE WHEN status = 'paid' THEN 1 ELSE 0 END) as paid_count,
           SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_count
    FROM invoices
    WHERE batch_reference IS NOT NULL AND batch_reference != ''
    GROUP BY batch_reference, title
    ORDER BY created_at DESC
");

$tariffs = $db->fetchAll("SELECT * FROM tariffs ORDER BY name ASC");
$payments = $db->fetchAll("
    SELECT pay.*, inv.invoice_number, CONCAT(p.first_name, ' ', p.last_name) as payer_name
    FROM payments pay
    LEFT JOIN invoices inv ON pay.invoice_id = inv.id
    LEFT JOIN persons p ON pay.payer_person_id = p.id
    ORDER BY pay.transaction_date DESC
");

$units = $db->fetchAll("SELECT u.id, u.unit_number, u.unit_code, b.name as building_name FROM property_units u JOIN buildings_blocks b ON u.building_id = b.id ORDER BY b.name, u.unit_number ASC");
$persons = $db->fetchAll("SELECT id, first_name, last_name, primary_phone FROM persons ORDER BY first_name ASC");

$pageTitle = "Financial Ledgers & Batch Billing Engine";
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="content-wrapper">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h1 class="page-title" style="margin: 0; font-size: 1.75rem; font-weight: 700;">Financial Ledgers & Billing Engine</h1>
            <p class="text-subtitle" style="color: var(--text-secondary); margin-top: 0.25rem;">
                Manage tariffs, automated batch monthly service charges, property ledgers, and payment receipts.
            </p>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <?php if (hasPermission('billing.create')): ?>
                <button type="button" onclick="openOffcanvas('batchBillingModal')" class="btn btn-primary">
                    <i data-lucide="layers" style="width: 16px; height: 16px;"></i> Run Batch Monthly Billing
                </button>
                <button type="button" onclick="openOffcanvas('invoiceModal')" class="btn btn-outline">
                    <i data-lucide="plus-circle" style="width: 16px; height: 16px;"></i> Create Single Invoice
                </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($message)): ?>
    <div class="alert alert-success" style="margin-bottom: 1.5rem;">
        <i data-lucide="check-circle" style="width: 16px; height: 16px; vertical-align: middle;"></i> <?= $message ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
    <div class="alert alert-danger" style="margin-bottom: 1.5rem;">
        <i data-lucide="alert-circle" style="width: 16px; height: 16px; vertical-align: middle;"></i> <?= $error ?>
    </div>
    <?php endif; ?>

    <!-- Navigation Tabs -->
    <div class="tabs-container" style="background: white; border-radius: 1rem; border: 1px solid var(--border-color); padding: 0.5rem; margin-bottom: 1.5rem; display: flex; gap: 0.5rem;">
        <button type="button" class="tab-btn active" onclick="showBillingTab('tab-invoices', this)" style="padding: 0.6rem 1rem; border-radius: 0.75rem; border: none; background: var(--primary-light); color: var(--primary-color); font-weight: 600; cursor: pointer;">
            <i data-lucide="file-text" style="width: 16px; height: 16px; vertical-align: middle;"></i> Property Invoices (<?= count($invoices) ?>)
        </button>
        <button type="button" class="tab-btn" onclick="showBillingTab('tab-batches', this)" style="padding: 0.6rem 1rem; border-radius: 0.75rem; border: none; background: transparent; color: var(--text-secondary); font-weight: 600; cursor: pointer;">
            <i data-lucide="history" style="width: 16px; height: 16px; vertical-align: middle;"></i> Batch Billing History (<?= count($batchHistory) ?>)
        </button>
        <button type="button" class="tab-btn" onclick="showBillingTab('tab-tariffs', this)" style="padding: 0.6rem 1rem; border-radius: 0.75rem; border: none; background: transparent; color: var(--text-secondary); font-weight: 600; cursor: pointer;">
            <i data-lucide="tag" style="width: 16px; height: 16px; vertical-align: middle;"></i> Fee Tariffs (<?= count($tariffs) ?>)
        </button>
        <button type="button" class="tab-btn" onclick="showBillingTab('tab-payments', this)" style="padding: 0.6rem 1rem; border-radius: 0.75rem; border: none; background: transparent; color: var(--text-secondary); font-weight: 600; cursor: pointer;">
            <i data-lucide="credit-card" style="width: 16px; height: 16px; vertical-align: middle;"></i> Payment Logs (<?= count($payments) ?>)
        </button>
    </div>

    <!-- TAB 1: INVOICES -->
    <div id="tab-invoices" class="billing-tab-content" style="display: block;">
        <div class="card" style="background: white; border-radius: 1rem; border: 1px solid var(--border-color); padding: 1.5rem;">
            <div class="table-responsive">
                <table class="data-table" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Invoice No.</th>
                            <th>Property Unit</th>
                            <th>Title / Description</th>
                            <th>Payer Name</th>
                            <th>Net Amount</th>
                            <th>Due Date</th>
                            <th>Status</th>
                            <th>Batch Ref</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($invoices)): ?>
                            <?php foreach ($invoices as $inv): ?>
                            <tr>
                                <td><code><?= e($inv['invoice_number']) ?></code></td>
                                <td><strong style="color: var(--primary-color);"><?= e($inv['unit_code']) ?></strong> (Unit <?= e($inv['unit_number']) ?>)</td>
                                <td><strong><?= e($inv['title']) ?></strong></td>
                                <td><?= e($inv['payer_name'] ?: 'Unassigned Payer') ?></td>
                                <td><strong><?= formatMoney($inv['net_amount']) ?></strong></td>
                                <td><?= formatDate($inv['due_date']) ?></td>
                                <td>
                                    <span class="badge badge-<?= $inv['status'] === 'paid' ? 'success' : ($inv['status'] === 'partial' ? 'warning' : ($inv['status'] === 'cancelled' ? 'secondary' : 'danger')) ?>">
                                        <?= strtoupper(e($inv['status'])) ?>
                                    </span>
                                </td>
                                <td><code><?= e($inv['batch_reference'] ?: 'MANUAL') ?></code></td>
                                <td style="text-align: right;">
                                    <?php if (in_array($inv['status'], ['unpaid', 'partial']) && hasPermission('payments.record')): ?>
                                    <button type="button" class="btn btn-sm btn-success" onclick="openPaymentModal(<?= $inv['id'] ?>, '<?= e($inv['invoice_number']) ?>', <?= $inv['net_amount'] ?>)">
                                        <i data-lucide="dollar-sign" style="width: 14px; height: 14px;"></i> Record Payment
                                    </button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="9" class="empty-state">No invoices generated yet.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 2: BATCH HISTORY -->
    <div id="tab-batches" class="billing-tab-content" style="display: none;">
        <div class="card" style="background: white; border-radius: 1rem; border: 1px solid var(--border-color); padding: 1.5rem;">
            <h3 style="font-size: 1.125rem; font-weight: 700; margin-bottom: 1.25rem;">Batch Billing Run History & Reversal Controls</h3>
            <?php if (!empty($batchHistory)): ?>
            <div class="table-responsive">
                <table class="data-table" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Batch Reference</th>
                            <th>Tariff Title</th>
                            <th>Invoices Created</th>
                            <th>Total Batch Amount</th>
                            <th>Run Date</th>
                            <th>Paid Invoices</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($batchHistory as $bh): ?>
                        <tr>
                            <td><strong style="color: var(--primary-color); font-size: 1rem;"><?= e($bh['batch_reference']) ?></strong></td>
                            <td><strong><?= e($bh['title']) ?></strong></td>
                            <td><span class="badge badge-info"><?= $bh['invoice_count'] ?> Invoices</span></td>
                            <td><strong><?= formatMoney($bh['total_batch_amount']) ?></strong></td>
                            <td><?= formatDate($bh['created_at'], 'M j, Y g:i A') ?></td>
                            <td><?= $bh['paid_count'] ?> Paid / <?= $bh['cancelled_count'] ?> Cancelled</td>
                            <td style="text-align: right;">
                                <?php if ($bh['cancelled_count'] < $bh['invoice_count'] && hasPermission('billing.manage')): ?>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Cancel all unpaid invoices under batch <?= e($bh['batch_reference']) ?>?')">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="batch_reference" value="<?= e($bh['batch_reference']) ?>">
                                    <button type="submit" name="cancel_batch" class="btn btn-sm btn-danger">
                                        <i data-lucide="x-circle" style="width: 14px; height: 14px;"></i> Cancel Batch
                                    </button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-muted">No monthly batch billing runs performed yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB 3: TARIFFS -->
    <div id="tab-tariffs" class="billing-tab-content" style="display: none;">
        <div class="card" style="background: white; border-radius: 1rem; border: 1px solid var(--border-color); padding: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
                <h3 style="font-size: 1.125rem; font-weight: 700; margin: 0;">Configured Estate Tariffs</h3>
                <?php if (hasPermission('settings.manage')): ?>
                <button type="button" onclick="openModal('tariffModal')" class="btn btn-sm btn-primary">
                    <i data-lucide="plus" style="width: 14px; height: 14px;"></i> Add Tariff Fee
                </button>
                <?php endif; ?>
            </div>
            <div class="table-responsive">
                <table class="data-table" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Tariff Name</th>
                            <th>Category</th>
                            <th>Amount</th>
                            <th>Frequency</th>
                            <th>Default Payer</th>
                            <th>Due Days</th>
                            <th>Penalty %</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tariffs as $tr): ?>
                        <tr>
                            <td><strong><?= e($tr['name']) ?></strong></td>
                            <td><span class="badge badge-info"><?= ucfirst(e($tr['category'])) ?></span></td>
                            <td><strong style="color: var(--primary-color);"><?= formatMoney($tr['amount']) ?></strong></td>
                            <td><?= ucfirst(e($tr['frequency'])) ?></td>
                            <td><span class="badge badge-outline"><?= ucfirst(e($tr['default_payer_role'] ?? 'tenant')) ?></span></td>
                            <td><?= e($tr['due_days']) ?> Days</td>
                            <td><?= e($tr['penalty_percent']) ?>%</td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB 4: PAYMENTS -->
    <div id="tab-payments" class="billing-tab-content" style="display: none;">
        <div class="card" style="background: white; border-radius: 1rem; border: 1px solid var(--border-color); padding: 1.5rem;">
            <h3 style="font-size: 1.125rem; font-weight: 700; margin-bottom: 1.25rem;">Recorded Payments & Receipts</h3>
            <?php if (!empty($payments)): ?>
            <div class="table-responsive">
                <table class="data-table" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Payment Ref</th>
                            <th>Invoice No.</th>
                            <th>Payer Name</th>
                            <th>Amount Paid</th>
                            <th>Payment Method</th>
                            <th>Transaction Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $pay): ?>
                        <tr>
                            <td><code><?= e($pay['payment_reference']) ?></code></td>
                            <td><code><?= e($pay['invoice_number'] ?: 'N/A') ?></code></td>
                            <td><?= e($pay['payer_name'] ?: 'Unassigned Payer') ?></td>
                            <td><strong style="color: #16a34a;"><?= formatMoney($pay['amount']) ?></strong></td>
                            <td><span class="badge badge-outline"><?= strtoupper(str_replace('_', ' ', e($pay['payment_method']))) ?></span></td>
                            <td><?= formatDate($pay['transaction_date'], 'M j, Y g:i A') ?></td>
                            <td><span class="badge badge-success"><?= strtoupper(e($pay['status'])) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-muted">No payments logged yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- RUN BATCH MONTHLY BILLING OFFCANVAS PANEL -->
<div id="batchBillingModal" class="offcanvas-overlay">
    <div class="offcanvas-panel">
        <div class="offcanvas-header">
            <h3 style="margin: 0; font-weight: 700; font-size: 1.25rem;">Run Batch Monthly Service Charge Billing</h3>
            <button type="button" class="toast-close" onclick="closeOffcanvas('batchBillingModal')">&times;</button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <div class="offcanvas-body" style="padding: 1.5rem; flex: 1; overflow-y: auto;">
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Select Estate Tariff Fee *</label>
                    <select name="tariff_id" class="form-control" required>
                        <option value="">-- Choose Tariff Fee --</option>
                        <?php foreach ($tariffs as $tr): ?>
                        <option value="<?= $tr['id'] ?>">
                            <?= e($tr['name']) ?> - <?= formatMoney($tr['amount']) ?> (Default Payer: <?= ucfirst(e($tr['default_payer_role'] ?? 'tenant')) ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Target Units Group *</label>
                    <select name="target_group" class="form-control">
                        <option value="all_occupied">All Occupied Estate Units</option>
                        <option value="residential_only">Occupied Residential Units Only</option>
                        <option value="commercial_only">Occupied Commercial Units Only</option>
                        <option value="all_units">All Registered Property Units (Occupied + Vacant)</option>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Billing Period Start *</label>
                        <input type="date" name="billing_period_start" class="form-control" value="<?= date('Y-m-01') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Billing Period End *</label>
                        <input type="date" name="billing_period_end" class="form-control" value="<?= date('Y-m-t') ?>" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Invoice Due Date *</label>
                    <input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
                </div>
            </div>
            <div class="offcanvas-footer" style="padding: 1.25rem 1.75rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 0.75rem; background: var(--hover-bg);">
                <button type="button" class="btn btn-secondary" onclick="closeOffcanvas('batchBillingModal')">Cancel</button>
                <button type="submit" name="run_batch_billing" class="btn btn-primary">
                    <i data-lucide="play" style="width: 16px; height: 16px;"></i> Execute Batch Billing Run
                </button>
            </div>
        </form>
    </div>
</div>

<!-- SINGLE INVOICE OFFCANVAS PANEL -->
<div id="invoiceModal" class="offcanvas-overlay">
    <div class="offcanvas-panel">
        <div class="offcanvas-header">
            <h3 style="margin: 0; font-weight: 700; font-size: 1.25rem;">Create Single Property Invoice</h3>
            <button type="button" class="toast-close" onclick="closeOffcanvas('invoiceModal')">&times;</button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <div class="offcanvas-body" style="padding: 1.5rem; flex: 1; overflow-y: auto;">
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Property Unit *</label>
                    <select name="unit_id" class="form-control" required>
                        <option value="">-- Choose Unit --</option>
                        <?php foreach ($units as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= e($u['building_name']) ?> - Unit <?= e($u['unit_number']) ?> (<?= e($u['unit_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Invoice Title / Description *</label>
                    <input type="text" name="title" class="form-control" placeholder="e.g. Infrastructure Maintenance Levy" required>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Net Amount (₦) *</label>
                        <input type="number" step="0.01" name="amount" class="form-control" placeholder="15000.00" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Due Date *</label>
                        <input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" required>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Designated Payer Person</label>
                    <select name="payer_person_id" class="form-control">
                        <option value="">-- Auto-Assign Payer --</option>
                        <?php foreach ($persons as $p): ?>
                        <option value="<?= $p['id'] ?>"><?= e($p['first_name']) ?> <?= e($p['last_name']) ?> (<?= e($p['primary_phone']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="offcanvas-footer" style="padding: 1.25rem 1.75rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 0.75rem; background: var(--hover-bg);">
                <button type="button" class="btn btn-secondary" onclick="closeOffcanvas('invoiceModal')">Cancel</button>
                <button type="submit" name="generate_invoice" class="btn btn-primary">Generate Invoice</button>
            </div>
        </form>
    </div>
</div>

<!-- RECORD PAYMENT OFFCANVAS PANEL -->
<div id="paymentModal" class="offcanvas-overlay">
    <div class="offcanvas-panel">
        <div class="offcanvas-header">
            <h3 style="margin: 0; font-weight: 700; font-size: 1.25rem;">Record Manual Payment</h3>
            <button type="button" class="toast-close" onclick="closeOffcanvas('paymentModal')">&times;</button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="invoice_id" id="payInvoiceId">
            <div class="offcanvas-body" style="padding: 1.5rem; flex: 1; overflow-y: auto;">
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Target Invoice</label>
                    <input type="text" id="payInvoiceNum" class="form-control" readonly style="font-weight: 700;">
                </div>
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Payment Amount (₦) *</label>
                    <input type="number" step="0.01" name="payment_amount" id="payAmount" class="form-control" required>
                </div>
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Payment Method</label>
                    <select name="payment_method" class="form-control">
                        <option value="bank_transfer">Bank Transfer (NIBSS / Direct)</option>
                        <option value="pos">POS Terminal Machine</option>
                        <option value="cash">Cash Payment</option>
                        <option value="cheque">Cheque</option>
                        <option value="paystack">Paystack Gateway</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Notes / Transaction Ref</label>
                    <input type="text" name="notes" class="form-control" placeholder="e.g. Bank Teller / POS Receipt No.">
                </div>
            </div>
            <div class="offcanvas-footer" style="padding: 1.25rem 1.75rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 0.75rem; background: var(--hover-bg);">
                <button type="button" class="btn btn-secondary" onclick="closeOffcanvas('paymentModal')">Cancel</button>
                <button type="submit" name="record_payment" class="btn btn-success">Record Payment Receipt</button>
            </div>
        </form>
    </div>
</div>

<!-- ADD TARIFF OFFCANVAS PANEL -->
<div id="tariffModal" class="offcanvas-overlay">
    <div class="offcanvas-panel">
        <div class="offcanvas-header">
            <h3 style="margin: 0; font-weight: 700; font-size: 1.25rem;">Configure Estate Tariff Fee</h3>
            <button type="button" class="toast-close" onclick="closeOffcanvas('tariffModal')">&times;</button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <div class="offcanvas-body" style="padding: 1.5rem; flex: 1; overflow-y: auto;">
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Tariff Name *</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Monthly Estate Service Charge" required>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Amount (₦) *</label>
                        <input type="number" step="0.01" name="amount" class="form-control" placeholder="15000.00" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Default Payer Role</label>
                        <select name="default_payer_role" class="form-control">
                            <option value="tenant">Tenant / Head Resident</option>
                            <option value="owner">Property Owner / Landlord</option>
                        </select>
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Frequency</label>
                        <select name="frequency" class="form-control">
                            <option value="monthly">Monthly</option>
                            <option value="quarterly">Quarterly</option>
                            <option value="annually">Annually</option>
                            <option value="one_time">One-Time</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Due Days</label>
                        <input type="number" name="due_days" class="form-control" value="30">
                    </div>
                </div>
            </div>
            <div class="offcanvas-footer" style="padding: 1.25rem 1.75rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 0.75rem; background: var(--hover-bg);">
                <button type="button" class="btn btn-secondary" onclick="closeOffcanvas('tariffModal')">Cancel</button>
                <button type="submit" name="save_tariff" class="btn btn-primary">Save Tariff Fee</button>
            </div>
        </form>
    </div>
</div>

<script>
function showBillingTab(tabId, btn) {
    document.querySelectorAll('.billing-tab-content').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.tab-btn').forEach(el => {
        el.style.background = 'transparent';
        el.style.color = 'var(--text-secondary)';
    });
    document.getElementById(tabId).style.display = 'block';
    btn.style.background = 'var(--primary-light)';
    btn.style.color = 'var(--primary-color)';
}

function openPaymentModal(invId, invNum, amt) {
    document.getElementById('payInvoiceId').value = invId;
    document.getElementById('payInvoiceNum').value = invNum;
    document.getElementById('payAmount').value = amt;
    openOffcanvas('paymentModal');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
