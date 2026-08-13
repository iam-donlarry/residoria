<?php
// modules/vehicles/index.php - Centralized Vehicle Register
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../classes/ReferenceGenerator.php';

requirePermission('vehicles.view');

$db = Database::getInstance();
$message = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_vehicle'])) {
    requirePermission('vehicles.manage');
    try {
        verifyCsrfToken();
        $person_id = intval($_POST['person_id']);
        $unit_id = !empty($_POST['unit_id']) ? intval($_POST['unit_id']) : null;
        $plate = strtoupper(sanitize($_POST['license_plate_number']));
        $make = sanitize($_POST['make'] ?? '');
        $model = sanitize($_POST['model'] ?? '');
        $color = sanitize($_POST['color'] ?? '');
        $v_type = sanitize($_POST['vehicle_type'] ?? 'sedan');
        $decal = sanitize($_POST['decal_number'] ?? '');
        $rfid = sanitize($_POST['rfid_tag_id'] ?? '');

        if ($person_id <= 0 || empty($plate)) {
            $error = "Driver/Owner person profile and license plate number are required.";
        } else {
            $v_code = ReferenceGenerator::generate('vehicle');
            $db->query("INSERT INTO vehicles (person_id, unit_id, license_plate_number, vehicle_code, make, model, color, vehicle_type, decal_number, rfid_tag_id, access_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')", [
                $person_id, $unit_id, $plate, $v_code, $make, $model, $color, $v_type, $decal, $rfid
            ]);
            $message = "Vehicle ($plate) registered successfully with Code #$v_code!";
            logAudit($conn, "Register Vehicle", "Vehicles", "Registered vehicle $plate ($v_code)");
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch Vehicle Register
$search = sanitize($_GET['q'] ?? '');
$query = "SELECT v.*, p.first_name, p.last_name, p.primary_phone, p.person_code,
          u.unit_number, u.unit_code, b.name as building_name
          FROM vehicles v 
          JOIN persons p ON v.person_id = p.id 
          LEFT JOIN property_units u ON v.unit_id = u.id 
          LEFT JOIN buildings_blocks b ON u.building_id = b.id";

$params = [];
if (!empty($search)) {
    $query .= " WHERE v.license_plate_number LIKE ? OR v.vehicle_code LIKE ? OR p.first_name LIKE ? OR p.last_name LIKE ? OR v.decal_number LIKE ?";
    $term = "%$search%";
    $params = [$term, $term, $term, $term, $term];
}
$query .= " ORDER BY v.created_at DESC";
$vehicles = $db->fetchAll($query, $params);

$persons = $db->fetchAll("SELECT id, person_code, first_name, last_name, primary_phone FROM persons ORDER BY first_name ASC");
$units = $db->fetchAll("SELECT u.id, u.unit_number, u.unit_code, b.name as building_name FROM property_units u JOIN buildings_blocks b ON u.building_id = b.id ORDER BY b.name, u.unit_number ASC");

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
    <div>
        <h1 style="font-size: 1.75rem; font-weight: 700; color: var(--text-primary); margin: 0;">Vehicle Access Register</h1>
        <p style="color: var(--text-muted); font-size: 0.95rem; margin: 0.25rem 0 0;">Manage resident vehicles, license plates, windshield decal tags, and gate clearance</p>
    </div>
    <?php if (hasPermission('vehicles.manage')): ?>
        <button onclick="openModal('vehModal')" class="btn btn-primary">
            <i data-lucide="car" style="width: 18px; height: 18px;"></i> Register Vehicle
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
    <div class="table-toolbar" style="display: flex; justify-content: space-between; align-items: center;">
        <h3 style="font-size: 1.05rem; font-weight: 700; margin: 0;">Registered Vehicles (<?= count($vehicles) ?>)</h3>
        <form method="GET" style="display: flex; gap: 0.5rem;">
            <input type="text" name="q" class="form-control btn-sm" placeholder="Search plate, decal, driver..." value="<?= e($search) ?>" style="width: 240px;">
            <button type="submit" class="btn btn-secondary btn-sm">Search</button>
        </form>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>License Plate</th>
                <th>Vehicle Code</th>
                <th>Registered Driver</th>
                <th>Unit Location</th>
                <th>Make / Model / Color</th>
                <th>Decal Tag No.</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($vehicles)): ?>
                <?php foreach ($vehicles as $v): ?>
                <tr>
                    <td style="font-weight: 700; font-size: 1.05rem; color: var(--primary); letter-spacing: 0.5px;"><?= e($v['license_plate_number']) ?></td>
                    <td style="font-weight: 600;"><?= e($v['vehicle_code']) ?></td>
                    <td style="font-weight: 600;"><?= e($v['first_name'] . ' ' . $v['last_name']) ?></td>
                    <td><?= $v['unit_number'] ? e($v['building_name'] . ' - Unit ' . $v['unit_number']) : 'Unlinked' ?></td>
                    <td><?= e(implode(' ', array_filter([$v['make'], $v['model'], '(' . $v['color'] . ')']))) ?></td>
                    <td><span class="badge badge-info"><?= e($v['decal_number'] ? $v['decal_number'] : 'No Tag') ?></span></td>
                    <td>
                        <span class="badge <?= $v['access_status'] === 'active' ? 'badge-success' : 'badge-danger' ?>">
                            <?= e(ucfirst($v['access_status'])) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="7" class="empty-state">No vehicles registered yet. Click "Register Vehicle" to index vehicles.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal -->
<div id="vehModal" class="modal-overlay">
    <div class="modal-container" style="max-width: 580px;">
        <div class="modal-header">
            <h3>Register Resident Vehicle</h3>
            <button class="toast-close" onclick="closeModal('vehModal')">&times;</button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Driver / Owner Person Profile *</label>
                    <select name="person_id" class="form-control" required>
                        <option value="">Select Person Profile...</option>
                        <?php foreach ($persons as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= e($p['first_name'] . ' ' . $p['last_name']) ?> (<?= e($p['primary_phone']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Property Unit Location</label>
                    <select name="unit_id" class="form-control">
                        <option value="">Unlinked / Estate Vehicle</option>
                        <?php foreach ($units as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= e($u['building_name']) ?> - Unit <?= e($u['unit_number']) ?> (<?= e($u['unit_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">License Plate Number *</label>
                        <input type="text" name="license_plate_number" class="form-control" placeholder="LSD-492AA" required style="text-transform: uppercase;">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Vehicle Type</label>
                        <select name="vehicle_type" class="form-control">
                            <option value="sedan">Sedan</option>
                            <option value="suv">SUV</option>
                            <option value="motorcycle">Motorcycle</option>
                            <option value="delivery_truck">Delivery Truck</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.75rem;">
                    <div class="form-group">
                        <label class="form-label">Make</label>
                        <input type="text" name="make" class="form-control" placeholder="Toyota">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Model</label>
                        <input type="text" name="model" class="form-control" placeholder="Camry">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Color</label>
                        <input type="text" name="color" class="form-control" placeholder="Silver">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Windshield Decal Number</label>
                        <input type="text" name="decal_number" class="form-control" placeholder="TAG-101">
                    </div>
                    <div class="form-group">
                        <label class="form-label">RFID Tag Identifier</label>
                        <input type="text" name="rfid_tag_id" class="form-control" placeholder="RFID-9988">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('vehModal')">Cancel</button>
                <button type="submit" name="save_vehicle" class="btn btn-primary">Save Vehicle</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
