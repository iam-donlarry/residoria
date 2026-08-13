<?php
// modules/residents/index.php - Modular Residents Directory & Occupancy Management
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../classes/ReferenceGenerator.php';

requirePermission('residents.view');

$db = Database::getInstance();
$message = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_resident'])) {
    requirePermission('residents.create');
    try {
        verifyCsrfToken();
        $person_id = intval($_POST['person_id']);
        $unit_id = intval($_POST['unit_id']);
        $category = sanitize($_POST['residency_category'] ?? 'tenant');
        $move_in = sanitize($_POST['move_in_date'] ?? date('Y-m-d'));
        $create_user_account = !empty($_POST['create_user_account']);

        if ($person_id <= 0 || $unit_id <= 0) {
            $error = "Please select a Person profile and Property unit.";
        } else {
            $res_code = ReferenceGenerator::generate('resident');
            $db->query("INSERT INTO residents (person_id, unit_id, resident_code, residency_category, move_in_date, status) VALUES (?, ?, ?, ?, ?, 'active')", [
                $person_id, $unit_id, $res_code, $category, $move_in
            ]);

            // Update Unit status to occupied
            $db->query("UPDATE property_units SET status = 'occupied' WHERE id = ?", [$unit_id]);

            // Optionally create login account for user
            if ($create_user_account) {
                $person = $db->fetchOne("SELECT first_name, last_name, email FROM persons WHERE id = ?", [$person_id]);
                if ($person && !empty($person['email'])) {
                    $checkUser = $db->fetchOne("SELECT id FROM users WHERE email = ?", [$person['email']]);
                    if (!$checkUser) {
                        $pass = password_hash('resident123', PASSWORD_DEFAULT);
                        $db->query("INSERT INTO users (person_id, name, email, password, role, status) VALUES (?, ?, ?, ?, 'resident', 'active')", [
                            $person_id, $person['first_name'] . ' ' . $person['last_name'], $person['email'], $pass
                        ]);
                        $uId = $db->lastInsertId();
                        $db->query("INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, 5)", [$uId]); // Role #5: Resident
                    }
                }
            }

            $message = "Resident registered successfully with Code #$res_code!";
            logAudit($conn, "Register Resident", "Residents", "Registered resident Code #$res_code for Unit ID #$unit_id");
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Handle Resident Move Out Action
if (isset($_GET['action']) && $_GET['action'] === 'move_out' && !empty($_GET['id'])) {
    requirePermission('residents.archive');
    try {
        verifyCsrfToken($_GET['csrf'] ?? '');
        $resId = intval($_GET['id']);
        $res = $db->fetchOne("SELECT unit_id FROM residents WHERE id = ?", [$resId]);
        if ($res) {
            $db->query("UPDATE residents SET status = 'moved_out', actual_move_out_date = CURDATE() WHERE id = ?", [$resId]);
            
            // Check remaining active residents in unit
            $remaining = $db->fetchOne("SELECT COUNT(*) as c FROM residents WHERE unit_id = ? AND status = 'active'", [$res['unit_id']])['c'];
            if ($remaining == 0) {
                $db->query("UPDATE property_units SET status = 'vacant_available' WHERE id = ?", [$res['unit_id']]);
            }
            $message = "Resident status updated to Moved Out.";
            logAudit($conn, "Resident Move Out", "Residents", "Moved out resident ID #$resId");
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch Active Residents Directory
$residents = $db->fetchAll("SELECT r.*, p.first_name, p.last_name, p.primary_phone, p.email, p.person_code,
                            u.unit_number, u.unit_code, b.name as building_name, z.name as zone_name
                            FROM residents r 
                            JOIN persons p ON r.person_id = p.id 
                            JOIN property_units u ON r.unit_id = u.id 
                            JOIN buildings_blocks b ON u.building_id = b.id 
                            JOIN zones_streets z ON b.zone_id = z.id 
                            WHERE r.status = 'active' 
                            ORDER BY r.created_at DESC");

$persons = $db->fetchAll("SELECT id, person_code, first_name, last_name, primary_phone FROM persons ORDER BY first_name, last_name ASC");
$units = $db->fetchAll("SELECT u.id, u.unit_number, u.unit_code, b.name as building_name FROM property_units u JOIN buildings_blocks b ON u.building_id = b.id ORDER BY b.name, u.unit_number ASC");

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
    <div>
        <h1 style="font-size: 1.75rem; font-weight: 700; color: var(--text-primary); margin: 0;">Residents Directory & Occupancy</h1>
        <p style="color: var(--text-muted); font-size: 0.95rem; margin: 0.25rem 0 0;">Manage primary estate residents, owner-occupiers, tenants, and move-in dates</p>
    </div>
    <?php if (hasPermission('residents.create')): ?>
        <button onclick="openModal('residentModal')" class="btn btn-primary">
            <i data-lucide="user-check" style="width: 18px; height: 18px;"></i> Register Resident
        </button>
    <?php endif; ?>
</div>

<?php if (!empty($message)): ?>
    <script>document.addEventListener('DOMContentLoaded', function() { showToast(<?= json_encode($message) ?>, 'success'); });</script>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <script>document.addEventListener('DOMContentLoaded', function() { showToast(<?= json_encode($error) ?>, 'error'); });</script>
<?php endif; ?>

<!-- Residents Table -->
<div class="data-table-wrapper">
    <div class="table-toolbar">
        <h3 style="font-size: 1.05rem; font-weight: 700; margin: 0;">Active Estate Occupants (<?= count($residents) ?>)</h3>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Resident Code</th>
                <th>Full Name</th>
                <th>Property Unit</th>
                <th>Category</th>
                <th>Phone Number</th>
                <th>Move-In Date</th>
                <th style="text-align: right;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($residents)): ?>
                <?php foreach ($residents as $r): ?>
                <tr>
                    <td style="font-weight: 600; color: var(--primary);"><?= e($r['resident_code']) ?></td>
                    <td style="font-weight: 700;"><?= e($r['first_name'] . ' ' . $r['last_name']) ?></td>
                    <td><?= e($r['building_name']) ?> - Unit <?= e($r['unit_number']) ?> (<?= e($r['unit_code']) ?>)</td>
                    <td>
                        <span class="badge <?= $r['residency_category'] === 'owner_occupier' ? 'badge-success' : 'badge-info' ?>">
                            <?= e(str_replace('_', ' ', ucfirst($r['residency_category']))) ?>
                        </span>
                    </td>
                    <td><?= e($r['primary_phone']) ?></td>
                    <td><?= formatDate($r['move_in_date']) ?></td>
                    <td style="text-align: right;">
                        <a href="?action=move_out&id=<?= $r['id'] ?>&csrf=<?= generateCsrfToken() ?>" class="btn btn-secondary btn-sm" onclick="return confirm('Mark resident as moved out?')">Move Out</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="7" class="empty-state">No active residents found. Click "Register Resident" to add primary occupants.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Register Resident Modal -->
<div id="residentModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <h3>Register Estate Resident</h3>
            <button class="toast-close" onclick="closeModal('residentModal')">&times;</button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Select Person Profile *</label>
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
                        <label class="form-label">Residency Category</label>
                        <select name="residency_category" class="form-control">
                            <option value="tenant">Tenant</option>
                            <option value="owner_occupier">Owner Occupier</option>
                            <option value="co_resident">Co-Resident</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Move-In Date</label>
                        <input type="date" name="move_in_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                </div>

                <div class="form-group" style="margin-top: 0.5rem;">
                    <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.9rem;">
                        <input type="checkbox" name="create_user_account" value="1" checked>
                        <span>Create login user account for resident (Password: resident123)</span>
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('residentModal')">Cancel</button>
                <button type="submit" name="save_resident" class="btn btn-primary">Register Resident</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
