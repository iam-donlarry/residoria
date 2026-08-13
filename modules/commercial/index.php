<?php
// modules/commercial/index.php - Commercial Occupants & Business Directory
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth_check.php';

requirePermission('commercial.view');

$db = Database::getInstance();
$message = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_commercial'])) {
    requirePermission('commercial.manage');
    try {
        verifyCsrfToken();
        $unit_id = intval($_POST['unit_id']);
        $b_name = sanitize($_POST['business_name']);
        $b_cat = sanitize($_POST['business_category'] ?? 'Retail');
        $cac = sanitize($_POST['cac_rc_number'] ?? '');
        $tax_id = sanitize($_POST['tax_id'] ?? '');
        $contact_p_id = !empty($_POST['principal_contact_person_id']) ? intval($_POST['principal_contact_person_id']) : null;
        $b_phone = sanitize($_POST['business_phone'] ?? '');
        $b_email = sanitize($_POST['business_email'] ?? '');
        $op_hours = sanitize($_POST['operating_hours'] ?? '');
        $staff_cnt = intval($_POST['estimated_staff_count'] ?? 0);
        $l_start = !empty($_POST['lease_start_date']) ? sanitize($_POST['lease_start_date']) : null;
        $l_end = !empty($_POST['lease_end_date']) ? sanitize($_POST['lease_end_date']) : null;

        if ($unit_id <= 0 || empty($b_name)) {
            $error = "Property unit and business name are required.";
        } else {
            $db->query("INSERT INTO commercial_occupants (unit_id, business_name, business_category, cac_rc_number, tax_id, principal_contact_person_id, business_phone, business_email, operating_hours, estimated_staff_count, lease_start_date, lease_end_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')", [
                $unit_id, $b_name, $b_cat, $cac, $tax_id, $contact_p_id, $b_phone, $b_email, $op_hours, $staff_cnt, $l_start, $l_end
            ]);
            
            // Mark unit as occupied
            $db->query("UPDATE property_units SET status = 'occupied' WHERE id = ?", [$unit_id]);

            $message = "Commercial business occupant ($b_name) registered successfully!";
            logAudit($conn, "Register Commercial Business", "Commercial", "Registered business $b_name in unit ID #$unit_id");
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch Commercial Occupants
$commercials = $db->fetchAll("SELECT co.*, u.unit_number, u.unit_code, b.name as building_name, z.name as zone_name,
                              p.first_name, p.last_name, p.primary_phone as contact_phone
                              FROM commercial_occupants co 
                              JOIN property_units u ON co.unit_id = u.id 
                              JOIN buildings_blocks b ON u.building_id = b.id 
                              JOIN zones_streets z ON b.zone_id = z.id 
                              LEFT JOIN persons p ON co.principal_contact_person_id = p.id 
                              WHERE co.status = 'active' ORDER BY co.created_at DESC");

$units = $db->fetchAll("SELECT u.id, u.unit_number, u.unit_code, b.name as building_name FROM property_units u JOIN buildings_blocks b ON u.building_id = b.id WHERE u.usage_class IN ('commercial', 'mixed') ORDER BY b.name, u.unit_number ASC");
$persons = $db->fetchAll("SELECT id, person_code, first_name, last_name, primary_phone FROM persons ORDER BY first_name ASC");

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
    <div>
        <h1 style="font-size: 1.75rem; font-weight: 700; color: var(--text-primary); margin: 0;">Commercial Businesses Directory</h1>
        <p style="color: var(--text-muted); font-size: 0.95rem; margin: 0.25rem 0 0;">Manage commercial occupants, retail shops, offices, operating hours, and CAC registration</p>
    </div>
    <?php if (hasPermission('commercial.manage')): ?>
        <button onclick="openModal('commModal')" class="btn btn-primary">
            <i data-lucide="store" style="width: 18px; height: 18px;"></i> Register Commercial Business
        </button>
    <?php endif; ?>
</div>

<?php if (!empty($message)): ?>
    <script>document.addEventListener('DOMContentLoaded', function() { showToast(<?= json_encode($message) ?>, 'success'); });</script>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <script>document.addEventListener('DOMContentLoaded', function() { showToast(<?= json_encode($error) ?>, 'error'); });</script>
<?php endif; ?>

<div class="data-table-wrapper">
    <div class="table-toolbar">
        <h3 style="font-size: 1.05rem; font-weight: 700; margin: 0;">Active Business Outlets (<?= count($commercials) ?>)</h3>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Business Name</th>
                <th>Category</th>
                <th>Property Unit</th>
                <th>CAC Reg No.</th>
                <th>Principal Contact</th>
                <th>Operating Hours</th>
                <th>Lease Expiry</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($commercials)): ?>
                <?php foreach ($commercials as $c): ?>
                <tr>
                    <td style="font-weight: 700; color: var(--primary);"><?= e($c['business_name']) ?></td>
                    <td><span class="badge badge-info"><?= e($c['business_category']) ?></span></td>
                    <td><?= e($c['building_name']) ?> - Unit <?= e($c['unit_number']) ?> (<?= e($c['unit_code']) ?>)</td>
                    <td><?= e($c['cac_rc_number'] ? $c['cac_rc_number'] : 'N/A') ?></td>
                    <td><?= e($c['first_name'] ? ($c['first_name'] . ' ' . $c['last_name'] . ' (' . $c['contact_phone'] . ')') : 'N/A') ?></td>
                    <td style="color: var(--text-muted); font-size: 0.85rem;"><?= e($c['operating_hours'] ?? 'Unspecified') ?></td>
                    <td><?= formatDate($c['lease_end_date']) ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="7" class="empty-state">No commercial businesses registered yet. Click "Register Commercial Business" to add.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal -->
<div id="commModal" class="modal-overlay">
    <div class="modal-container" style="max-width: 600px;">
        <div class="modal-header">
            <h3>Register Commercial Business</h3>
            <button class="toast-close" onclick="closeModal('commModal')">&times;</button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Select Commercial Unit *</label>
                    <select name="unit_id" class="form-control" required>
                        <option value="">Select Commercial Unit...</option>
                        <?php foreach ($units as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= e($u['building_name']) ?> - Unit <?= e($u['unit_number']) ?> (<?= e($u['unit_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Business / Trade Name *</label>
                        <input type="text" name="business_name" class="form-control" placeholder="MedPlus Pharmacy" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Business Category</label>
                        <input type="text" name="business_category" class="form-control" value="Retail" placeholder="Retail, Pharmacy, Bank">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">CAC RC/BN Number</label>
                        <input type="text" name="cac_rc_number" class="form-control" placeholder="RC-894102">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tax Identification No. (TIN)</label>
                        <input type="text" name="tax_id" class="form-control" placeholder="TIN-889900">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Principal Contact Person</label>
                    <select name="principal_contact_person_id" class="form-control">
                        <option value="">Select Contact Person...</option>
                        <?php foreach ($persons as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= e($p['first_name'] . ' ' . $p['last_name']) ?> (<?= e($p['primary_phone']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Business Phone</label>
                        <input type="text" name="business_phone" class="form-control" placeholder="+234 809 000 0000">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Business Email</label>
                        <input type="email" name="business_email" class="form-control" placeholder="contact@business.ng">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Operating Hours</label>
                        <input type="text" name="operating_hours" class="form-control" placeholder="08:00 - 21:00 (Mon-Sat)">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Estimated Staff Count</label>
                        <input type="number" name="estimated_staff_count" class="form-control" value="5">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Lease Start Date</label>
                        <input type="date" name="lease_start_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Lease End Date</label>
                        <input type="date" name="lease_end_date" class="form-control" value="<?= date('Y-m-d', strtotime('+1 year')) ?>">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('commModal')">Cancel</button>
                <button type="submit" name="save_commercial" class="btn btn-primary">Save Commercial Business</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
