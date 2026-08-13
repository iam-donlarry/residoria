<?php
// modules/domestic-staff/index.php - Resident-Employed Domestic Staff & Nigerian Security Verification
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/helpers.php';

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
        $g_name = sanitize($_POST['guarantor_name'] ?? '');
        $g_phone = sanitize($_POST['guarantor_phone'] ?? '');
        $g_address = sanitize($_POST['guarantor_address'] ?? '');
        $nin = sanitize($_POST['nin_number'] ?? '');

        if ($person_id <= 0 || $employer_res_id <= 0 || $unit_id <= 0 || empty($role)) {
            $error = "Person profile, employer resident, unit, and staff role are required.";
        } else {
            $db->query("INSERT INTO domestic_staff (person_id, employer_resident_id, unit_id, staff_role, residence_type, gate_access_start_time, gate_access_end_time, verification_status, guarantor_name, guarantor_phone, guarantor_address, nin_number, access_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')", [
                $person_id, $employer_res_id, $unit_id, $role, $res_type, $start_time, $end_time, $verif_status, $g_name, $g_phone, $g_address, $nin
            ]);
            $message = "Domestic staff member ($role) registered successfully with Nigerian security verification details!";
            logAudit($conn, "Register Domestic Staff", "Domestic Staff", "Registered staff ID #$person_id under resident ID #$employer_res_id");
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch Domestic Staff
$staffList = $db->fetchAll("
    SELECT ds.*, p.first_name, p.last_name, p.primary_phone, p.id_type, p.id_number,
           head_p.first_name as emp_first, head_p.last_name as emp_last,
           u.unit_number, u.unit_code, b.name as building_name
    FROM domestic_staff ds 
    JOIN persons p ON ds.person_id = p.id 
    JOIN residents r ON ds.employer_resident_id = r.id 
    JOIN persons head_p ON r.person_id = head_p.id 
    JOIN property_units u ON ds.unit_id = u.id 
    JOIN buildings_blocks b ON u.building_id = b.id 
    ORDER BY ds.created_at DESC
");

$activeResidents = $db->fetchAll("
    SELECT r.id, r.unit_id, p.first_name, p.last_name, u.unit_number, u.unit_code, b.name as building_name
    FROM residents r 
    JOIN persons p ON r.person_id = p.id 
    JOIN property_units u ON r.unit_id = u.id 
    JOIN buildings_blocks b ON u.building_id = b.id 
    WHERE r.status = 'active' ORDER BY p.first_name ASC
");

$persons = $db->fetchAll("SELECT id, person_code, first_name, last_name, primary_phone FROM persons ORDER BY first_name ASC");

$pageTitle = "Domestic Staff Directory & Security Verification";
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="content-wrapper">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h1 class="page-title" style="margin: 0; font-size: 1.75rem; font-weight: 700;">Resident Domestic Staff</h1>
            <p class="text-subtitle" style="color: var(--text-secondary); margin-top: 0.25rem;">
                Manage drivers, cooks, nannies, cleaners, gate clearance windows, and Nigerian guarantor verifications.
            </p>
        </div>
        <?php if (hasPermission('residents.create')): ?>
            <button onclick="openOffcanvas('dsModal')" class="btn btn-primary">
                <i data-lucide="user-cog" style="width: 16px; height: 16px;"></i> Register Domestic Staff
            </button>
        <?php endif; ?>
    </div>

    <?php if (!empty($message)): ?>
    <div class="alert alert-success" style="margin-bottom: 1.5rem;">
        <i data-lucide="check-circle" style="width: 16px; height: 16px; vertical-align: middle;"></i> <?= e($message) ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
    <div class="alert alert-danger" style="margin-bottom: 1.5rem;">
        <i data-lucide="alert-circle" style="width: 16px; height: 16px; vertical-align: middle;"></i> <?= e($error) ?>
    </div>
    <?php endif; ?>

    <div class="card" style="background: white; border-radius: 1rem; border: 1px solid var(--border-color); padding: 1.5rem;">
        <div class="table-responsive">
            <table class="data-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Staff Name</th>
                        <th>Role / Position</th>
                        <th>Type</th>
                        <th>Employer Resident</th>
                        <th>Property Unit</th>
                        <th>Gate Access Window</th>
                        <th>Guarantor & NIN</th>
                        <th>Verification</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($staffList)): ?>
                        <?php foreach ($staffList as $st): ?>
                        <tr>
                            <td>
                                <strong><?= e($st['first_name']) ?> <?= e($st['last_name']) ?></strong><br>
                                <small class="text-muted"><?= e($st['primary_phone']) ?></small>
                            </td>
                            <td><strong><?= e($st['staff_role']) ?></strong></td>
                            <td>
                                <?php if ($st['residence_type'] === 'live_in'): ?>
                                <span class="badge badge-success">Live-In Staff</span>
                                <?php else: ?>
                                <span class="badge badge-info">Daily Commuter</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($st['emp_first']) ?> <?= e($st['emp_last']) ?></td>
                            <td><strong style="color: var(--primary-color);"><?= e($st['unit_code']) ?></strong> (Unit <?= e($st['unit_number']) ?>)</td>
                            <td><code><?= e($st['gate_access_start_time']) ?> - <?= e($st['gate_access_end_time']) ?></code></td>
                            <td>
                                <small>
                                    <strong>G:</strong> <?= e($st['guarantor_name'] ?: 'N/A') ?> (<?= e($st['guarantor_phone'] ?: 'No phone') ?>)<br>
                                    <strong>NIN:</strong> <code><?= e($st['nin_number'] ?: 'N/A') ?></code>
                                </small>
                            </td>
                            <td>
                                <span class="badge badge-<?= $st['verification_status'] === 'verified' ? 'success' : ($st['verification_status'] === 'rejected' ? 'danger' : 'warning') ?>">
                                    <?= ucfirst(e($st['verification_status'])) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="empty-state">No domestic staff members registered.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- REGISTER DOMESTIC STAFF OFFCANVAS PANEL -->
<div id="dsModal" class="offcanvas-overlay">
    <div class="offcanvas-panel">
        <div class="offcanvas-header">
            <h3 style="margin: 0; font-weight: 700; font-size: 1.25rem;">Register Domestic Staff Member</h3>
            <button type="button" class="toast-close" onclick="closeOffcanvas('dsModal')">&times;</button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <div class="offcanvas-body" style="padding: 1.5rem; flex: 1; overflow-y: auto;">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Select Person Profile *</label>
                        <select name="person_id" class="form-control" required>
                            <option value="">-- Choose Person Profile --</option>
                            <?php foreach ($persons as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= e($p['first_name']) ?> <?= e($p['last_name']) ?> (<?= e($p['primary_phone']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Employer Resident & Unit *</label>
                        <select name="employer_resident_id" class="form-control" required onchange="setUnitFromResident(this)">
                            <option value="">-- Choose Employer Resident --</option>
                            <?php foreach ($activeResidents as $r): ?>
                            <option value="<?= $r['id'] ?>" data-unit="<?= $r['unit_id'] ?>">
                                <?= e($r['first_name']) ?> <?= e($r['last_name']) ?> (Unit <?= e($r['unit_number']) ?> - <?= e($r['unit_code']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="unit_id" id="hidden_unit_id">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Staff Role / Position *</label>
                        <input type="text" name="staff_role" class="form-control" placeholder="e.g. Private Driver / Nanny / Cleaner" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Residence Type *</label>
                        <select name="residence_type" class="form-control">
                            <option value="daily">Daily Commuter (Non-Resident)</option>
                            <option value="live_in">Live-In Staff (Permanent Resident)</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                    <div class="form-group">
                        <label class="form-label">Gate Access Start Time</label>
                        <input type="time" name="gate_access_start_time" class="form-control" value="07:00">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Gate Access End Time</label>
                        <input type="time" name="gate_access_end_time" class="form-control" value="18:00">
                    </div>
                </div>

                <!-- Nigerian Security Guarantor Fields -->
                <h4 style="font-size: 0.9375rem; font-weight: 700; color: var(--primary-color); margin-bottom: 0.75rem;">Nigerian Security Verification & Guarantor</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 0.75rem;">
                    <div class="form-group">
                        <label class="form-label">Guarantor Name</label>
                        <input type="text" name="guarantor_name" class="form-control" placeholder="e.g. Chief O. Adebayo">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Guarantor Phone Number</label>
                        <input type="text" name="guarantor_phone" class="form-control" placeholder="+234 802 000 0000">
                    </div>
                </div>
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Guarantor Address</label>
                        <input type="text" name="guarantor_address" class="form-control" placeholder="Lagos, Nigeria">
                    </div>
                    <div class="form-group">
                        <label class="form-label">National ID (NIN)</label>
                        <input type="text" name="nin_number" class="form-control" placeholder="11-digit NIN">
                    </div>
                </div>
            </div>
            <div class="offcanvas-footer" style="padding: 1.25rem 1.75rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 0.75rem; background: var(--hover-bg);">
                <button type="button" class="btn btn-secondary" onclick="closeOffcanvas('dsModal')">Cancel</button>
                <button type="submit" name="save_domestic" class="btn btn-primary">Save Domestic Staff</button>
            </div>
        </form>
    </div>
</div>

<script>
function setUnitFromResident(sel) {
    const opt = sel.options[sel.selectedIndex];
    const unitId = opt.getAttribute('data-unit');
    document.getElementById('hidden_unit_id').value = unitId || '';
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
