<?php
// modules/domestic-staff/index.php - Resident-Employed Domestic Staff
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth_check.php';

requirePermission('residents.view');

$db = Database::getInstance();
$message = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_domestic'])) {
    requirePermission('residents.create');
    try {
        verifyCsrfToken();
        $person_id = intval($_POST['person_id']);
        $employer_res_id = intval($_POST['employer_resident_id']);
        $unit_id = intval($_POST['unit_id']);
        $role = sanitize($_POST['staff_role']);
        $res_type = sanitize($_POST['residence_type'] ?? 'daily');
        $start_time = sanitize($_POST['gate_access_start_time'] ?? '07:00:00');
        $end_time = sanitize($_POST['gate_access_end_time'] ?? '18:00:00');
        $verif_status = sanitize($_POST['verification_status'] ?? 'pending');

        if ($person_id <= 0 || $employer_res_id <= 0 || $unit_id <= 0 || empty($role)) {
            $error = "Person profile, employer resident, unit, and staff role are required.";
        } else {
            $db->query("INSERT INTO domestic_staff (person_id, employer_resident_id, unit_id, staff_role, residence_type, gate_access_start_time, gate_access_end_time, verification_status, access_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')", [
                $person_id, $employer_res_id, $unit_id, $role, $res_type, $start_time, $end_time, $verif_status
            ]);
            $message = "Domestic staff member ($role) registered successfully!";
            logAudit($conn, "Register Domestic Staff", "Domestic Staff", "Registered staff ID #$person_id under resident ID #$employer_res_id");
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch Domestic Staff
$staffList = $db->fetchAll("SELECT ds.*, p.first_name, p.last_name, p.primary_phone, p.id_type, p.id_number,
                            head_p.first_name as emp_first, head_p.last_name as emp_last,
                            u.unit_number, u.unit_code, b.name as building_name
                            FROM domestic_staff ds 
                            JOIN persons p ON ds.person_id = p.id 
                            JOIN residents r ON ds.employer_resident_id = r.id 
                            JOIN persons head_p ON r.person_id = head_p.id 
                            JOIN property_units u ON ds.unit_id = u.id 
                            JOIN buildings_blocks b ON u.building_id = b.id 
                            ORDER BY ds.created_at DESC");

$activeResidents = $db->fetchAll("SELECT r.id, r.unit_id, p.first_name, p.last_name, u.unit_number, u.unit_code, b.name as building_name
                                  FROM residents r 
                                  JOIN persons p ON r.person_id = p.id 
                                  JOIN property_units u ON r.unit_id = u.id 
                                  JOIN buildings_blocks b ON u.building_id = b.id 
                                  WHERE r.status = 'active' ORDER BY p.first_name ASC");

$persons = $db->fetchAll("SELECT id, person_code, first_name, last_name, primary_phone FROM persons ORDER BY first_name ASC");

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
    <div>
        <h1 style="font-size: 1.75rem; font-weight: 700; color: var(--text-primary); margin: 0;">Resident Domestic Staff</h1>
        <p style="color: var(--text-muted); font-size: 0.95rem; margin: 0.25rem 0 0;">Manage drivers, cooks, nannies, cleaners, and gate clearance windows</p>
    </div>
    <?php if (hasPermission('residents.create')): ?>
        <button onclick="openModal('dsModal')" class="btn btn-primary">
            <i data-lucide="user-cog" style="width: 18px; height: 18px;"></i> Register Domestic Staff
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
        <h3 style="font-size: 1.05rem; font-weight: 700; margin: 0;">Registered Household Personnel (<?= count($staffList) ?>)</h3>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Staff Name</th>
                <th>Role / Position</th>
                <th>Employer Resident</th>
                <th>Property Unit</th>
                <th>Type</th>
                <th>Gate Access Window</th>
                <th>Verification</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($staffList)): ?>
                <?php foreach ($staffList as $s): ?>
                <tr>
                    <td style="font-weight: 700; color: var(--primary);"><?= e($s['first_name'] . ' ' . $s['last_name']) ?></td>
                    <td><span class="badge badge-info"><?= e($s['staff_role']) ?></span></td>
                    <td style="font-weight: 600;"><?= e($s['emp_first'] . ' ' . $s['emp_last']) ?></td>
                    <td><?= e($s['building_name']) ?> - Unit <?= e($s['unit_number']) ?> (<?= e($s['unit_code']) ?>)</td>
                    <td><?= e(ucfirst(str_replace('_', ' ', $s['residence_type']))) ?></td>
                    <td style="color: var(--text-muted); font-size: 0.85rem;"><?= e($s['gate_access_start_time']) ?> - <?= e($s['gate_access_end_time']) ?></td>
                    <td>
                        <span class="badge <?= $s['verification_status'] === 'verified' ? 'badge-success' : 'badge-warning' ?>">
                            <?= e(ucfirst($s['verification_status'])) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="7" class="empty-state">No domestic staff registered yet. Click "Register Domestic Staff" to add personnel.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal -->
<div id="dsModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <h3>Register Household Domestic Staff</h3>
            <button class="toast-close" onclick="closeModal('dsModal')">&times;</button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Staff Person Profile *</label>
                    <select name="person_id" class="form-control" required>
                        <option value="">Select Person Profile...</option>
                        <?php foreach ($persons as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= e($p['first_name'] . ' ' . $p['last_name']) ?> (<?= e($p['primary_phone']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Employer Resident *</label>
                    <select name="employer_resident_id" id="emp_select" class="form-control" required onchange="updateUnitId(this)">
                        <option value="">Select Employer Resident...</option>
                        <?php foreach ($activeResidents as $r): ?>
                            <option value="<?= $r['id'] ?>" data-unit="<?= $r['unit_id'] ?>"><?= e($r['first_name'] . ' ' . $r['last_name']) ?> (<?= e($r['building_name']) ?> - Unit <?= e($r['unit_number']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <input type="hidden" name="unit_id" id="unit_id_input" value="0">

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Staff Role / Duty *</label>
                        <input type="text" name="staff_role" class="form-control" placeholder="Driver, Cook, Nanny, Housekeeper" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Residence Type</label>
                        <select name="residence_type" class="form-control">
                            <option value="daily">Daily Commuter</option>
                            <option value="live_in">Live-In Staff</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Gate Access Start Time</label>
                        <input type="time" name="gate_access_start_time" class="form-control" value="07:00">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Gate Access End Time</label>
                        <input type="time" name="gate_access_end_time" class="form-control" value="18:00">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Verification Status</label>
                    <select name="verification_status" class="form-control">
                        <option value="verified">Verified</option>
                        <option value="pending">Pending Background Verification</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('dsModal')">Cancel</button>
                <button type="submit" name="save_domestic" class="btn btn-primary">Save Domestic Staff</button>
            </div>
        </form>
    </div>
</div>

<script>
function updateUnitId(select) {
    var selected = select.options[select.selectedIndex];
    var unitId = selected.getAttribute('data-unit') || 0;
    document.getElementById('unit_id_input').value = unitId;
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
