<?php
// modules/owners/index.php - Modular Property Owners Management
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth_check.php';

requirePermission('owners.view');

$db = Database::getInstance();
$message = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requirePermission('owners.manage');
    try {
        verifyCsrfToken();

        // 1. REGISTER INDIVIDUAL PROPERTY OWNER
        if (isset($_POST['save_individual_owner'])) {
            $person_id = intval($_POST['person_id']);
            $unit_id = intval($_POST['unit_id']);
            $start_date = sanitize($_POST['start_date'] ?? date('Y-m-d'));
            $percentage = floatval($_POST['ownership_percentage'] ?? 100.00);

            if ($person_id <= 0 || $unit_id <= 0) {
                $error = "Please select a Person profile and Property unit.";
            } else {
                $db->query("INSERT INTO property_ownership (unit_id, owner_type, person_id, ownership_percentage, start_date, is_current) VALUES (?, 'individual', ?, ?, ?, 1)", [
                    $unit_id, $person_id, $percentage, $start_date
                ]);
                $message = "Individual property ownership recorded successfully!";
                logAudit($conn, "Register Owner", "Owners", "Assigned person ID #$person_id to unit ID #$unit_id");
            }
        }

        // 2. REGISTER CORPORATE OWNER
        elseif (isset($_POST['save_corporate_owner'])) {
            $company_name = sanitize($_POST['company_name']);
            $cac = sanitize($_POST['cac_rc_number'] ?? '');
            $tax_id = sanitize($_POST['tax_id'] ?? '');
            $address = sanitize($_POST['office_address'] ?? '');
            $phone = sanitize($_POST['official_phone'] ?? '');
            $email = sanitize($_POST['official_email'] ?? '');
            $rep_person_id = !empty($_POST['representative_person_id']) ? intval($_POST['representative_person_id']) : null;
            $unit_id = intval($_POST['unit_id'] ?? 0);

            if (empty($company_name)) {
                $error = "Company name is required.";
            } else {
                $db->query("INSERT INTO corporate_owners (company_name, cac_rc_number, tax_id, office_address, official_phone, official_email, representative_person_id) VALUES (?, ?, ?, ?, ?, ?, ?)", [
                    $company_name, $cac, $tax_id, $address, $phone, $email, $rep_person_id
                ]);
                $corpId = $db->lastInsertId();

                if ($unit_id > 0) {
                    $db->query("INSERT INTO property_ownership (unit_id, owner_type, corporate_owner_id, ownership_percentage, start_date, is_current) VALUES (?, 'corporate', ?, 100.00, CURDATE(), 1)", [
                        $unit_id, $corpId
                    ]);
                }
                $message = "Corporate owner ($company_name) registered successfully!";
                logAudit($conn, "Register Corporate Owner", "Owners", "Registered company: $company_name");
            }
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch Ownership Records
$ownerships = $db->fetchAll("SELECT po.*, u.unit_number, u.unit_code, b.name as building_name, 
                             p.first_name, p.last_name, p.primary_phone, p.email,
                             co.company_name, co.cac_rc_number
                             FROM property_ownership po 
                             JOIN property_units u ON po.unit_id = u.id 
                             JOIN buildings_blocks b ON u.building_id = b.id 
                             LEFT JOIN persons p ON po.person_id = p.id 
                             LEFT JOIN corporate_owners co ON po.corporate_owner_id = co.id 
                             WHERE po.is_current = 1 
                             ORDER BY po.created_at DESC");

$persons = $db->fetchAll("SELECT id, person_code, first_name, last_name, primary_phone FROM persons ORDER BY first_name, last_name ASC");
$units = $db->fetchAll("SELECT u.id, u.unit_number, u.unit_code, b.name as building_name FROM property_units u JOIN buildings_blocks b ON u.building_id = b.id ORDER BY b.name, u.unit_number ASC");

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
    <div>
        <h1 style="font-size: 1.75rem; font-weight: 700; color: var(--text-primary); margin: 0;">Property Owners Directory</h1>
        <p style="color: var(--text-muted); font-size: 0.95rem; margin: 0.25rem 0 0;">Manage individual and corporate title holders across estate properties</p>
    </div>
    <?php if (hasPermission('owners.manage')): ?>
        <div style="display: flex; gap: 0.5rem;">
            <button onclick="openModal('individualOwnerModal')" class="btn btn-primary">
                <i data-lucide="user-plus" style="width: 18px; height: 18px;"></i> Link Individual Owner
            </button>
            <button onclick="openModal('corporateOwnerModal')" class="btn btn-secondary">
                <i data-lucide="building" style="width: 18px; height: 18px;"></i> Add Corporate Owner
            </button>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($message)): ?>
    <script>document.addEventListener('DOMContentLoaded', function() { showToast(<?= json_encode($message) ?>, 'success'); });</script>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <script>document.addEventListener('DOMContentLoaded', function() { showToast(<?= json_encode($error) ?>, 'error'); });</script>
<?php endif; ?>

<!-- Owners Table -->
<div class="data-table-wrapper">
    <div class="table-toolbar">
        <h3 style="font-size: 1.05rem; font-weight: 700; margin: 0;">Property Title Holders (Active Ledgers)</h3>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Property Unit</th>
                <th>Owner Type</th>
                <th>Owner Name</th>
                <th>Contact Phone</th>
                <th>Ownership %</th>
                <th>Title Start Date</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($ownerships)): ?>
                <?php foreach ($ownerships as $o): ?>
                <tr>
                    <td style="font-weight: 600; color: var(--primary);"><?= e($o['building_name']) ?> - Unit <?= e($o['unit_number']) ?> (<?= e($o['unit_code']) ?>)</td>
                    <td>
                        <span class="badge <?= $o['owner_type'] === 'individual' ? 'badge-info' : 'badge-warning' ?>">
                            <?= e(ucfirst($o['owner_type'])) ?>
                        </span>
                    </td>
                    <td style="font-weight: 700;">
                        <?= $o['owner_type'] === 'individual' ? e($o['first_name'] . ' ' . $o['last_name']) : e($o['company_name'] . ' (CAC: ' . ($o['cac_rc_number'] ?? 'N/A') . ')') ?>
                    </td>
                    <td><?= e($o['primary_phone'] ?? 'N/A') ?></td>
                    <td style="font-weight: 600;"><?= e($o['ownership_percentage']) ?>%</td>
                    <td style="color: var(--text-muted);"><?= formatDate($o['start_date']) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6" class="empty-state">No property owners registered yet. Click "Link Individual Owner" to assign title holders.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Link Individual Owner Modal -->
<div id="individualOwnerModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <h3>Link Individual Owner to Unit</h3>
            <button class="toast-close" onclick="closeModal('individualOwnerModal')">&times;</button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Select Person *</label>
                    <select name="person_id" class="form-control" required>
                        <option value="">Select Person Profile...</option>
                        <?php foreach ($persons as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= e($p['first_name'] . ' ' . $p['last_name']) ?> (<?= e($p['person_code']) ?> - <?= e($p['primary_phone']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Select Property Unit *</label>
                    <select name="unit_id" class="form-control" required>
                        <option value="">Select Property Unit...</option>
                        <?php foreach ($units as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= e($u['building_name']) ?> - Unit <?= e($u['unit_number']) ?> (<?= e($u['unit_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Ownership Share (%)</label>
                        <input type="number" step="0.1" name="ownership_percentage" class="form-control" value="100.0">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Title Acquisition Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('individualOwnerModal')">Cancel</button>
                <button type="submit" name="save_individual_owner" class="btn btn-primary">Save Ownership Title</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Corporate Owner Modal -->
<div id="corporateOwnerModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <h3>Add Corporate Owner / Entity</h3>
            <button class="toast-close" onclick="closeModal('corporateOwnerModal')">&times;</button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Registered Company Name *</label>
                    <input type="text" name="company_name" class="form-control" placeholder="e.g. Topsail Realty Ltd" required>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">CAC RC/BN Number</label>
                        <input type="text" name="cac_rc_number" class="form-control" placeholder="RC-998811">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tax ID (TIN)</label>
                        <input type="text" name="tax_id" class="form-control" placeholder="TIN-445566">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Official Phone</label>
                        <input type="text" name="official_phone" class="form-control" placeholder="+234 1 000 0000">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Official Email</label>
                        <input type="email" name="official_email" class="form-control" placeholder="info@company.ng">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Designated Corporate Representative (Person)</label>
                    <select name="representative_person_id" class="form-control">
                        <option value="">Select Representative...</option>
                        <?php foreach ($persons as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= e($p['first_name'] . ' ' . $p['last_name']) ?> (<?= e($p['primary_phone']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Assign to Property Unit (Optional)</label>
                    <select name="unit_id" class="form-control">
                        <option value="">Do Not Assign Yet</option>
                        <?php foreach ($units as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= e($u['building_name']) ?> - Unit <?= e($u['unit_number']) ?> (<?= e($u['unit_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('corporateOwnerModal')">Cancel</button>
                <button type="submit" name="save_corporate_owner" class="btn btn-primary">Save Corporate Entity</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
