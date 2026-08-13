<?php
// modules/billing/index.php - Finance, Tariffs, Invoices & Property Ledgers
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
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

        if (empty($name) || $amt <= 0) {
            $error = "Tariff name and valid amount are required.";
        } else {
            $config->saveTariff([
                'name' => $name,
                'category' => $cat,
                'amount' => $amt,
                'frequency' => $freq,
                'due_days' => $due_days,
                'penalty_percent' => $penalty,
                'is_active' => 1
            ]);
            $message = "Estate tariff fee saved successfully!";
            logAudit($conn, "Save Tariff", "Billing", "Saved tariff $name ($amt)");
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// 2. GENERATE INVOICE FOR PROPERTY UNIT
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
            $invId = $db->lastInsertId();

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

// 3. RECORD MANUAL PAYMENT
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
            $payId = $db->lastInsertId();

            $db->query("INSERT INTO payment_allocations (payment_id, invoice_id, allocated_amount) VALUES (?, ?, ?)", [
                $payId, $inv_id, $pay_amt
            ]);

            // Update Invoice Status
            $newStatus = ($pay_amt >= $inv['net_amount']) ? 'paid' : 'partial';
            $db->query("UPDATE invoices SET status = ? WHERE id = ?", [$newStatus, $inv_id]);

            $message = "Payment of " . formatMoney($pay_amt) . " recorded successfully (Receipt #$pay_ref)!";
            logAudit($conn, "Record Payment", "Billing", "Recorded payment $pay_ref for invoice ID #$inv_id");
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch Data
$invoices = $db->fetchAll("SELECT inv.*, u.unit_number, u.unit_code, b.name as building_name,
                           p.first_name, p.last_name, p.primary_phone
                           FROM invoices inv 
                           JOIN property_units u ON inv.unit_id = u.id 
                           JOIN buildings_blocks b ON u.building_id = b.id 
                           LEFT JOIN persons p ON inv.payer_person_id = p.id 
                           ORDER BY inv.created_at DESC LIMIT 100");

$tariffs = $config->getTariffs(false);
$units = $db->fetchAll("SELECT u.id, u.unit_number, u.unit_code, b.name as building_name FROM property_units u JOIN buildings_blocks b ON u.building_id = b.id ORDER BY b.name, u.unit_number ASC");
$persons = $db->fetchAll("SELECT id, person_code, first_name, last_name, primary_phone FROM persons ORDER BY first_name ASC");

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
    <div>
        <h1 style="font-size: 1.75rem; font-weight: 700; color: var(--text-primary); margin: 0;">Finance & Property Ledgers</h1>
        <p style="color: var(--text-muted); font-size: 0.95rem; margin: 0.25rem 0 0;">Manage tariffs, property-centric invoices, payments, and arrears</p>
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <button onclick="openModal('tariffModal')" class="btn btn-secondary btn-sm">+ Configure Tariff</button>
        <?php if (hasPermission('billing.create')): ?>
            <button onclick="openModal('invoiceModal')" class="btn btn-primary btn-sm">+ Generate Invoice</button>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($message)): ?>
    <script>document.addEventListener('DOMContentLoaded', function() { showToast(<?= json_encode($message) ?>, 'success'); });</script>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <script>document.addEventListener('DOMContentLoaded', function() { showToast(<?= json_encode($error) ?>, 'error'); });</script>
<?php endif; ?>

<!-- Tabs -->
<div style="display: flex; gap: 0.5rem; border-bottom: 2px solid var(--border-color); margin-bottom: 1.5rem;">
    <button class="tab-btn active" onclick="showTab('invoices-tab', this)">
        <i data-lucide="receipt" style="width: 16px; height: 16px;"></i> Invoices & Ledgers (<?= count($invoices) ?>)
    </button>
    <button class="tab-btn" onclick="showTab('tariffs-tab', this)">
        <i data-lucide="coins" style="width: 16px; height: 16px;"></i> Tariff Fee Schedule (<?= count($tariffs) ?>)
    </button>
</div>

<style>
    .tab-btn { background: none; border: none; padding: 0.75rem 1.25rem; font-family: inherit; font-weight: 600; font-size: 0.95rem; color: var(--text-muted); cursor: pointer; border-bottom: 3px solid transparent; display: flex; align-items: center; gap: 0.5rem; }
    .tab-btn.active { color: var(--primary); border-bottom-color: var(--primary); }
</style>

<!-- TAB 1: Invoices -->
<div id="invoices-tab" class="tab-content">
    <div class="data-table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Invoice No.</th>
                    <th>Property Unit</th>
                    <th>Payer Name</th>
                    <th>Billing Title</th>
                    <th>Net Amount</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th style="text-align: right;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($invoices)): ?>
                    <?php foreach ($invoices as $inv): ?>
                    <tr>
                        <td style="font-weight: 700; color: var(--primary);"><?= e($inv['invoice_number']) ?></td>
                        <td style="font-weight: 600;"><?= e($inv['building_name']) ?> - Unit <?= e($inv['unit_number']) ?></td>
                        <td><?= e($inv['first_name'] ? ($inv['first_name'] . ' ' . $inv['last_name']) : 'Estate Resident') ?></td>
                        <td><?= e($inv['title']) ?></td>
                        <td style="font-weight: 700; color: var(--text-primary);"><?= formatMoney($inv['net_amount']) ?></td>
                        <td><?= formatDate($inv['due_date']) ?></td>
                        <td>
                            <span class="badge <?= $inv['status'] === 'paid' ? 'badge-success' : ($inv['status'] === 'unpaid' ? 'badge-danger' : 'badge-warning') ?>">
                                <?= e(ucfirst($inv['status'])) ?>
                            </span>
                        </td>
                        <td style="text-align: right;">
                            <?php if ($inv['status'] !== 'paid' && hasPermission('payments.record')): ?>
                                <button class="btn btn-primary btn-sm" onclick='openPayModal(<?= json_encode($inv) ?>)'>Record Payment</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="empty-state">No invoices generated yet. Click "+ Generate Invoice" to bill units.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- TAB 2: Tariffs -->
<div id="tariffs-tab" class="tab-content" style="display: none;">
    <div class="data-table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tariff Name</th>
                    <th>Category</th>
                    <th>Amount</th>
                    <th>Frequency</th>
                    <th>Due Period</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tariffs as $t): ?>
                <tr>
                    <td style="font-weight: 700; color: var(--primary);"><?= e($t['name']) ?></td>
                    <td><span class="badge badge-info"><?= e(ucfirst(str_replace('_', ' ', $t['category']))) ?></span></td>
                    <td style="font-weight: 800;"><?= formatMoney($t['amount']) ?></td>
                    <td><?= e(ucfirst($t['frequency'])) ?></td>
                    <td><?= $t['due_days'] ?> Days</td>
                    <td><span class="badge badge-success">Active</span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Generate Invoice Modal -->
<div id="invoiceModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <h3>Generate Property Invoice</h3>
            <button class="toast-close" onclick="closeModal('invoiceModal')">&times;</button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Property Unit *</label>
                    <select name="unit_id" class="form-control" required>
                        <option value="">Select Property Unit...</option>
                        <?php foreach ($units as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= e($u['building_name']) ?> - Unit <?= e($u['unit_number']) ?> (<?= e($u['unit_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Payer Person (Optional)</label>
                    <select name="payer_person_id" class="form-control">
                        <option value="">Auto-assign Current Resident</option>
                        <?php foreach ($persons as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= e($p['first_name'] . ' ' . $p['last_name']) ?> (<?= e($p['primary_phone']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Tariff Fee Schedule Item</label>
                    <select name="tariff_id" class="form-control" onchange="autoFillTariff(this)">
                        <option value="0">Custom Billing Item</option>
                        <?php foreach ($tariffs as $t): ?>
                            <option value="<?= $t['id'] ?>" data-amount="<?= $t['amount'] ?>" data-name="<?= e($t['name']) ?>"><?= e($t['name']) ?> (<?= formatMoney($t['amount']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Billing Title / Description *</label>
                    <input type="text" id="inv_title" name="title" class="form-control" placeholder="e.g. Monthly Service Charge - August 2026" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Invoice Amount (₦) *</label>
                        <input type="number" step="0.01" id="inv_amount" name="amount" class="form-control" placeholder="15000.00" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Due Date</label>
                        <input type="date" name="due_date" class="form-control" value="<?= date('Y-m-d', strtotime('+30 days')) ?>">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('invoiceModal')">Cancel</button>
                <button type="submit" name="generate_invoice" class="btn btn-primary">Generate Invoice</button>
            </div>
        </form>
    </div>
</div>

<!-- Record Payment Modal -->
<div id="payModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <h3>Record Payment Receipt</h3>
            <button class="toast-close" onclick="closeModal('payModal')">&times;</button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" id="pay_inv_id" name="invoice_id" value="0">
            <div class="modal-body">
                <div style="background: var(--bg-body); padding: 1rem; border-radius: 6px; margin-bottom: 1rem;">
                    <div id="pay_inv_num" style="font-weight: 700; color: var(--primary);"></div>
                    <div id="pay_inv_title" style="font-size: 0.9rem; color: var(--text-muted);"></div>
                </div>

                <div class="form-group">
                    <label class="form-label">Payment Amount (₦) *</label>
                    <input type="number" step="0.01" id="pay_amount" name="payment_amount" class="form-control" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Payment Method</label>
                    <select name="payment_method" class="form-control">
                        <option value="bank_transfer">NIBSS Bank Transfer</option>
                        <option value="paystack">Paystack Online</option>
                        <option value="pos">POS Terminal</option>
                        <option value="cash">Cash Payment</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Payment Reference / Notes</label>
                    <input type="text" name="notes" class="form-control" placeholder="Bank teller reference / receipt note">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('payModal')">Cancel</button>
                <button type="submit" name="record_payment" class="btn btn-primary">Record Payment</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Tariff Modal -->
<div id="tariffModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <h3>Configure Tariff Fee Schedule</h3>
            <button class="toast-close" onclick="closeModal('tariffModal')">&times;</button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Tariff Fee Name *</label>
                    <input type="text" name="name" class="form-control" placeholder="Monthly Security Levy" required>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-control">
                            <option value="service_charge">Service Charge</option>
                            <option value="security">Security Levy</option>
                            <option value="waste">Waste Management</option>
                            <option value="water">Water Supply</option>
                            <option value="power">Electricity / Power</option>
                            <option value="infrastructure">Infrastructure Levy</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Amount (₦) *</label>
                        <input type="number" step="0.01" name="amount" class="form-control" placeholder="5000.00" required>
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
                        <label class="form-label">Grace Period (Days)</label>
                        <input type="number" name="due_days" class="form-control" value="30">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('tariffModal')">Cancel</button>
                <button type="submit" name="save_tariff" class="btn btn-primary">Save Tariff</button>
            </div>
        </form>
    </div>
</div>

<script>
function showTab(tabId, btn) {
    document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById(tabId).style.display = 'block';
    btn.classList.add('active');
}

function autoFillTariff(select) {
    var selected = select.options[select.selectedIndex];
    var amt = selected.getAttribute('data-amount');
    var name = selected.getAttribute('data-name');
    if (amt) {
        document.getElementById('inv_amount').value = amt;
        document.getElementById('inv_title').value = name + ' - ' + new Date().toLocaleString('default', { month: 'long', year: 'numeric' });
    }
}

function openPayModal(inv) {
    document.getElementById('pay_inv_id').value = inv.id;
    document.getElementById('pay_inv_num').innerText = 'Invoice #' + inv.invoice_number;
    document.getElementById('pay_inv_title').innerText = inv.title + ' (' + inv.building_name + ' - Unit ' + inv.unit_number + ')';
    document.getElementById('pay_amount').value = inv.net_amount;
    openModal('payModal');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
