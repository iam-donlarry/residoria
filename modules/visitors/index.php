<?php
// modules/visitors/index.php - Resident Visitor Pre-Authorization & Passes
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../classes/ReferenceGenerator.php';

requirePermission('security.view');

$db = Database::getInstance();
$message = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_pass'])) {
    requirePermission('security.pass_gen');
    try {
        verifyCsrfToken();
        $host_p_id = intval($_POST['host_person_id']);
        $unit_id = intval($_POST['unit_id']);
        $v_name = sanitize($_POST['visitor_name']);
        $v_phone = sanitize($_POST['visitor_phone'] ?? '');
        $v_cat = sanitize($_POST['visitor_category'] ?? 'guest');
        $v_date = sanitize($_POST['visit_date'] ?? date('Y-m-d'));
        $v_plate = strtoupper(sanitize($_POST['vehicle_plate_number'] ?? ''));

        if ($host_p_id <= 0 || $unit_id <= 0 || empty($v_name)) {
            $error = "Host resident, property unit, and visitor name are required.";
        } else {
            $vCodes = ReferenceGenerator::generate('visitor');
            $access_code = $vCodes['access_code'];
            $formatted_code = $vCodes['formatted'];

            $db->query("INSERT INTO visitor_passes (host_person_id, unit_id, access_code, pass_code_formatted, visitor_name, visitor_phone, visitor_category, visit_date, vehicle_plate_number, pass_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')", [
                $host_p_id, $unit_id, $access_code, $formatted_code, $v_name, $v_phone, $v_cat, $v_date, $v_plate
            ]);

            $message = "Visitor Access Pass generated successfully! Access Code: <strong>$access_code</strong> ($formatted_code)";
            logAudit($conn, "Generate Visitor Pass", "Visitors", "Generated pass $formatted_code (Code: $access_code) for $v_name");
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch Active Visitor Passes
$passes = $db->fetchAll("SELECT vp.*, p.first_name as host_first, p.last_name as host_last, p.primary_phone as host_phone,
                         u.unit_number, u.unit_code, b.name as building_name
                         FROM visitor_passes vp 
                         JOIN persons p ON vp.host_person_id = p.id 
                         JOIN property_units u ON vp.unit_id = u.id 
                         JOIN buildings_blocks b ON u.building_id = b.id 
                         ORDER BY vp.created_at DESC LIMIT 100");

$activeResidents = $db->fetchAll("SELECT r.id, r.unit_id, r.person_id, p.first_name, p.last_name, u.unit_number, u.unit_code, b.name as building_name
                                  FROM residents r 
                                  JOIN persons p ON r.person_id = p.id 
                                  JOIN property_units u ON r.unit_id = u.id 
                                  JOIN buildings_blocks b ON u.building_id = b.id 
                                  WHERE r.status = 'active' ORDER BY p.first_name ASC");

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
    <div>
        <h1 style="font-size: 1.75rem; font-weight: 700; color: var(--text-primary); margin: 0;">Visitor Access Pre-Authorization</h1>
        <p style="color: var(--text-muted); font-size: 0.95rem; margin: 0.25rem 0 0;">Generate digital gate access codes for guests, contractors, deliveries, and service providers</p>
    </div>
    <?php if (hasPermission('security.pass_gen')): ?>
        <button onclick="openModal('passModal')" class="btn btn-primary">
            <i data-lucide="qr-code" style="width: 18px; height: 18px;"></i> Generate Visitor Access Pass
        </button>
    <?php endif; ?>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-success" style="margin-bottom: 1.5rem; background: rgba(18,177,176,0.15); border-left: 4px solid var(--primary); padding: 1rem; border-radius: 6px; color: #0f7675;">
        <?= $message ?>
    </div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <script>document.addEventListener('DOMContentLoaded', function() { showToast(<?= json_encode($error) ?>, 'error'); });</script>
<?php endif; ?>

<div class="data-table-wrapper">
    <div class="table-toolbar">
        <h3 style="font-size: 1.05rem; font-weight: 700; margin: 0;">Visitor Clearance Register (<?= count($passes) ?>)</h3>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Gate Access Code</th>
                <th>Pass Reference</th>
                <th>Visitor Name</th>
                <th>Category</th>
                <th>Host Resident</th>
                <th>Destination Unit</th>
                <th>Visit Date</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($passes)): ?>
                <?php foreach ($passes as $p): ?>
                <tr>
                    <td style="font-weight: 800; font-size: 1.15rem; color: var(--primary); letter-spacing: 1px;"><?= e($p['access_code']) ?></td>
                    <td style="font-weight: 600; color: var(--text-muted);"><?= e($p['pass_code_formatted']) ?></td>
                    <td style="font-weight: 700;"><?= e($p['visitor_name']) ?></td>
                    <td><span class="badge badge-info"><?= e(ucfirst($p['visitor_category'])) ?></span></td>
                    <td><?= e($p['host_first'] . ' ' . $p['host_last']) ?></td>
                    <td><?= e($p['building_name']) ?> - Unit <?= e($p['unit_number']) ?></td>
                    <td><?= formatDate($p['visit_date']) ?></td>
                    <td>
                        <span class="badge <?= $p['pass_status'] === 'active' ? 'badge-success' : ($p['pass_status'] === 'used' ? 'badge-warning' : 'badge-danger') ?>">
                            <?= e(ucfirst($p['pass_status'])) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="8" class="empty-state">No visitor access codes generated yet. Click "Generate Visitor Access Pass" to create guest access.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal -->
<div id="passModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <h3>Generate Visitor Access Pass</h3>
            <button class="toast-close" onclick="closeModal('passModal')">&times;</button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Host Resident *</label>
                    <select name="host_person_id" id="host_select" class="form-control" required onchange="setPassUnit(this)">
                        <option value="">Select Host Resident...</option>
                        <?php foreach ($activeResidents as $r): ?>
                            <option value="<?= $r['person_id'] ?>" data-unit="<?= $r['unit_id'] ?>"><?= e($r['first_name'] . ' ' . $r['last_name']) ?> (<?= e($r['building_name']) ?> - Unit <?= e($r['unit_number']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <input type="hidden" name="unit_id" id="pass_unit_id" value="0">

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Visitor Full Name *</label>
                        <input type="text" name="visitor_name" class="form-control" placeholder="e.g. Grace Guest" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Visitor Phone Number</label>
                        <input type="text" name="visitor_phone" class="form-control" placeholder="+234 802 000 0000">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Visitor Category</label>
                        <select name="visitor_category" class="form-control">
                            <option value="guest">Guest / Personal Visit</option>
                            <option value="delivery">Delivery Service</option>
                            <option value="contractor">Contractor / Repair Tech</option>
                            <option value="service_provider">Service Provider</option>
                            <option value="event">Event Guest</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Visit Date</label>
                        <input type="date" name="visit_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Vehicle Plate Number (Optional)</label>
                    <input type="text" name="vehicle_plate_number" class="form-control" placeholder="e.g. KJA-108BB" style="text-transform: uppercase;">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('passModal')">Cancel</button>
                <button type="submit" name="generate_pass" class="btn btn-primary">Generate Access Code</button>
            </div>
        </form>
    </div>
</div>

<script>
function setPassUnit(select) {
    var selected = select.options[select.selectedIndex];
    var unitId = selected.getAttribute('data-unit') || 0;
    document.getElementById('pass_unit_id').value = unitId;
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
