<?php
// modules/properties/index.php - Modular Properties, Streets & Units Management
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../classes/ReferenceGenerator.php';

requirePermission('properties.view');

$db = Database::getInstance();
$message = "";
$error = "";

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requirePermission('properties.create');
    try {
        verifyCsrfToken();

        // 1. ADD / EDIT STREET OR ZONE
        if (isset($_POST['save_zone'])) {
            $zone_id = intval($_POST['zone_id'] ?? 0);
            $name = sanitize($_POST['name']);
            $description = sanitize($_POST['description']);
            $code = sanitize($_POST['code'] ?? '');

            if (empty($name)) {
                $error = "Street/Zone name is required.";
            } else {
                if ($zone_id > 0) {
                    $db->query("UPDATE zones_streets SET name = ?, description = ?, code = ? WHERE id = ?", [$name, $description, $code, $zone_id]);
                    $message = "Street/Zone updated successfully!";
                } else {
                    $code = !empty($code) ? $code : ('STR-' . sprintf('%02d', rand(1, 99)));
                    $db->query("INSERT INTO zones_streets (code, name, description) VALUES (?, ?, ?)", [$code, $name, $description]);
                    $message = "Street/Zone registered successfully!";
                }
                logAudit($conn, "Save Street", "Properties", "Saved street: $name");
            }
        }

        // 2. ADD / EDIT BUILDING OR BLOCK
        elseif (isset($_POST['save_building'])) {
            $building_id = intval($_POST['building_id'] ?? 0);
            $zone_id = intval($_POST['zone_id']);
            $name = sanitize($_POST['name']);
            $property_type = sanitize($_POST['property_type'] ?? 'Apartment');
            $total_floors = intval($_POST['total_floors'] ?? 1);
            $category = sanitize($_POST['category'] ?? 'residential');

            if ($zone_id <= 0 || empty($name)) {
                $error = "Street selection and building name are required.";
            } else {
                if ($building_id > 0) {
                    $db->query("UPDATE buildings_blocks SET zone_id = ?, name = ?, property_type = ?, total_floors = ?, category = ? WHERE id = ?", [
                        $zone_id, $name, $property_type, $total_floors, $category, $building_id
                    ]);
                    $message = "Building block updated successfully!";
                } else {
                    $b_code = 'BLD-' . sprintf('%04d', rand(10, 9999));
                    $db->query("INSERT INTO buildings_blocks (zone_id, building_code, name, property_type, total_floors, category) VALUES (?, ?, ?, ?, ?, ?)", [
                        $zone_id, $b_code, $name, $property_type, $total_floors, $category
                    ]);
                    $message = "Building block added successfully!";
                }
                logAudit($conn, "Save Building", "Properties", "Saved building: $name");
            }
        }

        // 3. ADD / EDIT PROPERTY UNIT
        elseif (isset($_POST['save_unit'])) {
            $unit_id = intval($_POST['unit_id'] ?? 0);
            $building_id = intval($_POST['building_id']);
            $unit_number = sanitize($_POST['unit_number']);
            $floor_number = intval($_POST['floor_number'] ?? 1);
            $usage_class = sanitize($_POST['usage_class'] ?? 'residential');
            $sub_type = sanitize($_POST['sub_type'] ?? '2BHK');
            $bedrooms = intval($_POST['bedrooms'] ?? 0);
            $bathrooms = intval($_POST['bathrooms'] ?? 0);
            $status = sanitize($_POST['status'] ?? 'vacant_available');
            $elec_meter = sanitize($_POST['electric_meter_no'] ?? '');
            $water_meter = sanitize($_POST['water_meter_no'] ?? '');

            if ($building_id <= 0 || empty($unit_number)) {
                $error = "Building block and unit number are required.";
            } else {
                if ($unit_id > 0) {
                    $db->query("UPDATE property_units SET building_id = ?, unit_number = ?, floor_number = ?, usage_class = ?, sub_type = ?, bedrooms = ?, bathrooms = ?, status = ?, electric_meter_no = ?, water_meter_no = ? WHERE id = ?", [
                        $building_id, $unit_number, $floor_number, $usage_class, $sub_type, $bedrooms, $bathrooms, $status, $elec_meter, $water_meter, $unit_id
                    ]);
                    $message = "Property unit updated successfully!";
                } else {
                    $unit_code = ReferenceGenerator::generate('property');
                    $db->query("INSERT INTO property_units (building_id, unit_number, unit_code, floor_number, usage_class, sub_type, bedrooms, bathrooms, status, electric_meter_no, water_meter_no) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", [
                        $building_id, $unit_number, $unit_code, $floor_number, $usage_class, $sub_type, $bedrooms, $bathrooms, $status, $elec_meter, $water_meter
                    ]);
                    $message = "Property unit #$unit_number created successfully ($unit_code)!";
                }
                logAudit($conn, "Save Unit", "Properties", "Saved unit #$unit_number");
            }
        }

    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch Data
$zones = $db->fetchAll("SELECT z.*, (SELECT COUNT(*) FROM buildings_blocks WHERE zone_id = z.id) as building_count FROM zones_streets z ORDER BY z.name ASC");
$buildings = $db->fetchAll("SELECT b.*, z.name as zone_name, (SELECT COUNT(*) FROM property_units WHERE building_id = b.id) as unit_count FROM buildings_blocks b JOIN zones_streets z ON b.zone_id = z.id ORDER BY b.name ASC");
$units = $db->fetchAll("SELECT u.*, b.name as building_name, z.name as zone_name FROM property_units u JOIN buildings_blocks b ON u.building_id = b.id JOIN zones_streets z ON b.zone_id = z.id ORDER BY b.name, u.unit_number ASC");

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
    <div>
        <h1 style="font-size: 1.75rem; font-weight: 700; color: var(--text-primary); margin: 0;">Properties & Unit Hierarchy</h1>
        <p style="color: var(--text-muted); font-size: 0.95rem; margin: 0.25rem 0 0;">Manage streets, building blocks, and residential/commercial units</p>
    </div>
    <div style="display: flex; gap: 0.5rem;">
        <button onclick="openModal('zoneModal')" class="btn btn-secondary btn-sm">+ Add Street/Zone</button>
        <button onclick="openModal('buildingModal')" class="btn btn-secondary btn-sm">+ Add Building</button>
        <button onclick="openModal('unitModal')" class="btn btn-primary btn-sm">+ Add Unit</button>
    </div>
</div>

<?php if (!empty($message)): ?>
    <script>document.addEventListener('DOMContentLoaded', function() { showToast(<?= json_encode($message) ?>, 'success'); });</script>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <script>document.addEventListener('DOMContentLoaded', function() { showToast(<?= json_encode($error) ?>, 'error'); });</script>
<?php endif; ?>

<!-- Tabs Header -->
<div style="display: flex; gap: 0.5rem; border-bottom: 2px solid var(--border-color); margin-bottom: 1.5rem;">
    <button class="tab-btn active" onclick="showTab('units-tab', this)">
        <i data-lucide="home" style="width: 16px; height: 16px;"></i> Property Units (<?= count($units) ?>)
    </button>
    <button class="tab-btn" onclick="showTab('buildings-tab', this)">
        <i data-lucide="building" style="width: 16px; height: 16px;"></i> Buildings & Blocks (<?= count($buildings) ?>)
    </button>
    <button class="tab-btn" onclick="showTab('zones-tab', this)">
        <i data-lucide="map-pin" style="width: 16px; height: 16px;"></i> Streets & Zones (<?= count($zones) ?>)
    </button>
</div>

<style>
    .tab-btn { background: none; border: none; padding: 0.75rem 1.25rem; font-family: inherit; font-weight: 600; font-size: 0.95rem; color: var(--text-muted); cursor: pointer; border-bottom: 3px solid transparent; display: flex; align-items: center; gap: 0.5rem; }
    .tab-btn.active { color: var(--primary); border-bottom-color: var(--primary); }
</style>

<!-- TAB 1: Units -->
<div id="units-tab" class="tab-content">
    <div class="data-table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Unit Code</th>
                    <th>Unit No.</th>
                    <th>Building / Block</th>
                    <th>Street / Zone</th>
                    <th>Usage Class</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($units)): ?>
                    <?php foreach ($units as $u): ?>
                    <tr>
                        <td style="font-weight: 600; color: var(--primary);"><?= e($u['unit_code']) ?></td>
                        <td style="font-weight: 700;"><?= e($u['unit_number']) ?></td>
                        <td><?= e($u['building_name']) ?></td>
                        <td style="color: var(--text-muted);"><?= e($u['zone_name']) ?></td>
                        <td><span class="badge <?= $u['usage_class'] === 'residential' ? 'badge-info' : 'badge-warning' ?>"><?= e(ucfirst($u['usage_class'])) ?></span></td>
                        <td><?= e($u['sub_type']) ?></td>
                        <td>
                            <span class="badge <?= $u['status'] === 'occupied' ? 'badge-success' : ($u['status'] === 'vacant_available' ? 'badge-warning' : 'badge-danger') ?>">
                                <?= e(str_replace('_', ' ', $u['status'])) ?>
                            </span>
                        </td>
                        <td style="text-align: right;">
                            <div style="display: flex; gap: 0.35rem; justify-content: flex-end;">
                                <a href="view.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-primary" title="Inspect 360° Profile">
                                    <i data-lucide="eye" style="width: 14px; height: 14px;"></i> Inspect 360°
                                </a>
                                <button type="button" class="btn btn-sm btn-outline" onclick="openPropertyDrawer(<?= $u['id'] ?>)" title="Quick Summary">
                                    <i data-lucide="sidebar" style="width: 14px; height: 14px;"></i> Quick
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="8" class="empty-state">No property units registered yet. Click "+ Add Unit" to configure units.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- TAB 2: Buildings -->
<div id="buildings-tab" class="tab-content" style="display: none;">
    <div class="data-table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Building Code</th>
                    <th>Building Name</th>
                    <th>Street / Zone</th>
                    <th>Property Type</th>
                    <th>Total Units</th>
                    <th>Category</th>
                    <th style="text-align: right;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($buildings)): ?>
                    <?php foreach ($buildings as $b): ?>
                    <tr>
                        <td style="font-weight: 600; color: var(--primary);"><?= e($b['building_code'] ?? 'N/A') ?></td>
                        <td style="font-weight: 700;"><?= e($b['name']) ?></td>
                        <td><?= e($b['zone_name']) ?></td>
                        <td><?= e($b['property_type']) ?></td>
                        <td><span class="badge badge-info"><?= $b['unit_count'] ?> Unit(s)</span></td>
                        <td><?= e(ucfirst($b['category'])) ?></td>
                        <td style="text-align: right;">
                            <a href="building_view.php?id=<?= $b['id'] ?>" class="btn btn-sm btn-primary">
                                <i data-lucide="building-2" style="width: 14px; height: 14px;"></i> Occupancy Matrix
                            </a>
                        </td>
                    </tr>

                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="7" class="empty-state">No buildings configured yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>


<!-- TAB 3: Streets & Zones -->
<div id="zones-tab" class="tab-content" style="display: none;">
    <div class="data-table-wrapper">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Zone Code</th>
                    <th>Street / Zone Name</th>
                    <th>Description</th>
                    <th>Total Buildings</th>
                    <th>Reg Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($zones)): ?>
                    <?php foreach ($zones as $z): ?>
                    <tr>
                        <td style="font-weight: 600; color: var(--primary);"><?= e($z['code'] ?? 'N/A') ?></td>
                        <td style="font-weight: 700;"><?= e($z['name']) ?></td>
                        <td style="color: var(--text-muted);"><?= e($z['description'] ?? 'N/A') ?></td>
                        <td><span class="badge badge-info"><?= $z['building_count'] ?> Building(s)</span></td>
                        <td><?= formatDate($z['created_at']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="5" class="empty-state">No streets registered yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Add Street / Zone Modal -->
<div id="zoneModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <h3>Add New Street / Zone</h3>
            <button class="toast-close" onclick="closeModal('zoneModal')">&times;</button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Street / Zone Name *</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Palm Avenue" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Street Code (Optional)</label>
                    <input type="text" name="code" class="form-control" placeholder="e.g. STR-01">
                </div>
                <div class="form-group">
                    <label class="form-label">Description / Notes</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="Notes..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('zoneModal')">Cancel</button>
                <button type="submit" name="save_zone" class="btn btn-primary">Save Street</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Building Modal -->
<div id="buildingModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <h3>Add Building / Property Block</h3>
            <button class="toast-close" onclick="closeModal('buildingModal')">&times;</button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Select Street / Zone *</label>
                    <select name="zone_id" class="form-control" required>
                        <option value="">Select Street...</option>
                        <?php foreach ($zones as $z): ?>
                            <option value="<?= $z['id'] ?>"><?= e($z['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Building / Block Name *</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Sunrise Apartments" required>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Property Type</label>
                        <select name="property_type" class="form-control">
                            <option value="Apartment">Apartment</option>
                            <option value="Duplex">Duplex</option>
                            <option value="Bungalow">Bungalow</option>
                            <option value="Villa">Villa</option>
                            <option value="Plaza">Commercial Plaza</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-control">
                            <option value="residential">Residential</option>
                            <option value="commercial">Commercial</option>
                            <option value="mixed">Mixed-Use</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('buildingModal')">Cancel</button>
                <button type="submit" name="save_building" class="btn btn-primary">Save Building</button>
            </div>
        </form>
    </div>
</div>

<!-- Add Unit Modal -->
<div id="unitModal" class="modal-overlay">
    <div class="modal-container" style="max-width: 580px;">
        <div class="modal-header">
            <h3>Add Property Unit</h3>
            <button class="toast-close" onclick="closeModal('unitModal')">&times;</button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Select Building / Block *</label>
                    <select name="building_id" class="form-control" required>
                        <option value="">Select Building...</option>
                        <?php foreach ($buildings as $b): ?>
                            <option value="<?= $b['id'] ?>"><?= e($b['name']) ?> (<?= e($b['zone_name']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Unit Number *</label>
                        <input type="text" name="unit_number" class="form-control" placeholder="e.g. 101 or Shop 01" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Usage Class</label>
                        <select name="usage_class" class="form-control">
                            <option value="residential">Residential</option>
                            <option value="commercial">Commercial</option>
                            <option value="mixed">Mixed-Use</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.75rem;">
                    <div class="form-group">
                        <label class="form-label">Sub-Type</label>
                        <input type="text" name="sub_type" class="form-control" value="2BHK" placeholder="e.g. Shop, 2BHK">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Bedrooms</label>
                        <input type="number" name="bedrooms" class="form-control" value="2">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Bathrooms</label>
                        <input type="number" name="bathrooms" class="form-control" value="2">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Electric Meter No.</label>
                        <input type="text" name="electric_meter_no" class="form-control" placeholder="ELE-xxxx">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Water Meter No.</label>
                        <input type="text" name="water_meter_no" class="form-control" placeholder="WAT-xxxx">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="vacant_available">Vacant & Available</option>
                        <option value="occupied">Occupied</option>
                        <option value="reserved">Reserved</option>
                        <option value="under_maintenance">Under Maintenance</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('unitModal')">Cancel</button>
                <button type="submit" name="save_unit" class="btn btn-primary">Save Property Unit</button>
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
</script>

<?php include __DIR__ . '/../../includes/property_drawer.php'; ?>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

