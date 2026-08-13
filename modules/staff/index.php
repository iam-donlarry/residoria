<?php
// modules/staff/index.php - Modular Staff Routing
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth_check.php';

requirePermission('residents.view');

$estate_id = get_estate_id();
$db = Database::getInstance();

$message = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_staff'])) {
    requirePermission('residents.create');
    try {
        verifyCsrfToken();
        $name = sanitize($_POST['name']);
        $role = sanitize($_POST['role']);
        $phone = sanitize($_POST['phone']);
        $status = sanitize($_POST['status'] ?? 'active');

        if (empty($name) || empty($role)) {
            $error = "Staff name and role position are required.";
        } else {
            $email = strtolower(str_replace(' ', '', $name)) . '_' . rand(10,99) . '@estate.com';
            $defaultPass = password_hash('welcome123', PASSWORD_DEFAULT);

            $db->query("INSERT INTO users (estate_id, name, email, phone, password, role, status) VALUES (?, ?, ?, ?, ?, 'staff', ?)", [
                $estate_id, $name, $email, $phone, $defaultPass, $status
            ]);
            $u_id = $db->lastInsertId();

            $custom_id = 'STF-' . sprintf('%05d', rand(100, 99999));
            $db->query("INSERT INTO estate_staff (estate_id, custom_id, user_id, role, phone, status) VALUES (?, ?, ?, ?, ?, ?)", [
                $estate_id, $custom_id, $u_id, $role, $phone, $status
            ]);
            $stf_id = $db->lastInsertId();
            $db->query("UPDATE estate_staff SET custom_id = ? WHERE id = ?", ['STF-' . sprintf('%05d', $stf_id), $stf_id]);

            $message = "Estate staff member ($name) added successfully!";
            logAudit($conn, "Add Staff", "Staff", "Registered estate staff: " . $name);
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch Estate Staff
$staffList = $db->fetchAll("SELECT s.*, u.name as user_name, u.email as user_email 
                            FROM estate_staff s 
                            JOIN users u ON s.user_id = u.id 
                            WHERE s.estate_id = ? 
                            ORDER BY s.created_at DESC", [$estate_id]);

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
    <div>
        <h1 style="font-size: 1.75rem; font-weight: 700; color: var(--text-primary); margin: 0;">Estate Staff Directory</h1>
        <p style="color: var(--text-muted); font-size: 0.95rem; margin: 0.25rem 0 0;">Manage security guards, facility managers, and administrative personnel</p>
    </div>
    <?php if (hasPermission('residents.create')): ?>
        <button onclick="openModal('staffModal')" class="btn btn-primary">
            <i data-lucide="user-plus" style="width: 18px; height: 18px;"></i> Add Staff Member
        </button>
    <?php endif; ?>
</div>

<?php if (!empty($message)): ?>
    <script>document.addEventListener('DOMContentLoaded', function() { showToast(<?= json_encode($message) ?>, 'success'); });</script>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <script>document.addEventListener('DOMContentLoaded', function() { showToast(<?= json_encode($error) ?>, 'error'); });</script>
<?php endif; ?>

<!-- Staff Table -->
<div class="data-table-wrapper">
    <div class="table-toolbar">
        <h3 style="font-size: 1.05rem; font-weight: 700; margin: 0;">Estate Employees & Personnel</h3>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Staff ID</th>
                <th>Name</th>
                <th>Role / Position</th>
                <th>Phone Number</th>
                <th>System Email</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($staffList)): ?>
                <?php foreach ($staffList as $stf): ?>
                <tr>
                    <td style="font-weight: 600; color: var(--primary);"><?= e($stf['custom_id'] ?? ('STF-' . $stf['id'])) ?></td>
                    <td style="font-weight: 700;"><?= e($stf['user_name']) ?></td>
                    <td><span class="badge badge-info"><?= e($stf['role']) ?></span></td>
                    <td><?= e($stf['phone'] ?? 'N/A') ?></td>
                    <td style="color: var(--text-muted); font-family: monospace;"><?= e($stf['user_email']) ?></td>
                    <td>
                        <span class="badge <?= $stf['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>">
                            <?= e($stf['status']) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6" class="empty-state">No staff members registered yet.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Add Staff Modal -->
<div id="staffModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <h3>Add Estate Staff Member</h3>
            <button class="toast-close" onclick="closeModal('staffModal')">&times;</button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Officer Paul" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Role / Position</label>
                    <select name="role" class="form-control" required>
                        <option value="Security Guard">Security Guard</option>
                        <option value="Facility Manager">Facility Manager</option>
                        <option value="Accountant">Accountant</option>
                        <option value="Maintenance Specialist">Maintenance Specialist</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Phone Number</label>
                    <input type="text" name="phone" class="form-control" placeholder="555-8888">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('staffModal')">Cancel</button>
                <button type="submit" name="save_staff" class="btn btn-primary">Save Staff Member</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
