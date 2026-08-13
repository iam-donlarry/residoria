<?php
// modules/estate-staff/index.php - HOA & Estate Operations Personnel
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../classes/ReferenceGenerator.php';

requirePermission('staff.view');

$db = Database::getInstance();
$message = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_estate_staff'])) {
    requirePermission('staff.manage');
    try {
        verifyCsrfToken();
        $person_id = intval($_POST['person_id']);
        $department = sanitize($_POST['department'] ?? 'Security');
        $position = sanitize($_POST['position_title']);
        $status = sanitize($_POST['employment_status'] ?? 'active');
        $hire_date = !empty($_POST['hire_date']) ? sanitize($_POST['hire_date']) : date('Y-m-d');
        $assign_role_id = intval($_POST['assign_role_id'] ?? 0);

        if ($person_id <= 0 || empty($position)) {
            $error = "Person profile and position title are required.";
        } else {
            $staff_code = ReferenceGenerator::generate('staff');
            
            // Check if person has user account
            $person = $db->fetchOne("SELECT first_name, last_name, email FROM persons WHERE id = ?", [$person_id]);
            $user_id = null;
            if ($person && !empty($person['email'])) {
                $user = $db->fetchOne("SELECT id FROM users WHERE email = ?", [$person['email']]);
                if ($user) {
                    $user_id = $user['id'];
                } elseif ($assign_role_id > 0) {
                    // Create user account
                    $pass = password_hash('staff123', PASSWORD_DEFAULT);
                    $db->query("INSERT INTO users (person_id, name, email, password, role, status) VALUES (?, ?, ?, ?, 'staff', 'active')", [
                        $person_id, $person['first_name'] . ' ' . $person['last_name'], $person['email'], $pass
                    ]);
                    $user_id = $db->lastInsertId();
                    $db->query("INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, ?)", [$user_id, $assign_role_id]);
                }
            }

            $db->query("INSERT INTO estate_staff (person_id, user_id, staff_code, department, position_title, employment_status, hire_date) VALUES (?, ?, ?, ?, ?, ?, ?)", [
                $person_id, $user_id, $staff_code, $department, $position, $status, $hire_date
            ]);

            $message = "Estate staff member registered successfully with Code #$staff_code!";
            logAudit($conn, "Register Estate Staff", "Estate Staff", "Registered staff Code #$staff_code: $position");
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch Estate Staff Directory
$staffList = $db->fetchAll("SELECT es.*, p.first_name, p.last_name, p.primary_phone, p.email, p.person_code, u.name as user_name
                            FROM estate_staff es 
                            JOIN persons p ON es.person_id = p.id 
                            LEFT JOIN users u ON es.user_id = u.id 
                            ORDER BY es.created_at DESC");

$persons = $db->fetchAll("SELECT id, person_code, first_name, last_name, primary_phone FROM persons ORDER BY first_name ASC");
$roles = $db->fetchAll("SELECT id, name FROM roles ORDER BY name ASC");

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
    <div>
        <h1 style="font-size: 1.75rem; font-weight: 700; color: var(--text-primary); margin: 0;">HOA & Estate Employees Directory</h1>
        <p style="color: var(--text-muted); font-size: 0.95rem; margin: 0.25rem 0 0;">Manage security guards, facility officers, administrative team, and system access</p>
    </div>
    <?php if (hasPermission('staff.manage')): ?>
        <button onclick="openModal('esModal')" class="btn btn-primary">
            <i data-lucide="badge-check" style="width: 18px; height: 18px;"></i> Register Estate Staff
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
        <h3 style="font-size: 1.05rem; font-weight: 700; margin: 0;">Estate Operations Personnel (<?= count($staffList) ?>)</h3>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Staff Code</th>
                <th>Employee Name</th>
                <th>Department</th>
                <th>Position Title</th>
                <th>Phone Number</th>
                <th>Hire Date</th>
                <th>Status</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($staffList)): ?>
                <?php foreach ($staffList as $s): ?>
                <tr>
                    <td style="font-weight: 600; color: var(--primary);"><?= e($s['staff_code']) ?></td>
                    <td style="font-weight: 700;"><?= e($s['first_name'] . ' ' . $s['last_name']) ?></td>
                    <td><span class="badge badge-info"><?= e($s['department']) ?></span></td>
                    <td style="font-weight: 600;"><?= e($s['position_title']) ?></td>
                    <td><?= e($s['primary_phone']) ?></td>
                    <td><?= formatDate($s['hire_date']) ?></td>
                    <td>
                        <span class="badge <?= $s['employment_status'] === 'active' ? 'badge-success' : 'badge-danger' ?>">
                            <?= e(ucfirst($s['employment_status'])) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="7" class="empty-state">No estate personnel registered yet. Click "Register Estate Staff" to add team members.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal -->
<div id="esModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <h3>Register Estate Staff Member</h3>
            <button class="toast-close" onclick="closeModal('esModal')">&times;</button>
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

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Department</label>
                        <select name="department" class="form-control">
                            <option value="Security">Security Operations</option>
                            <option value="Facility Management">Facility Management</option>
                            <option value="Finance & Accounts">Finance & Accounts</option>
                            <option value="Administration">Administration</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Position Title *</label>
                        <input type="text" name="position_title" class="form-control" placeholder="Gate Lead, Maintenance Tech, Accountant" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Hire Date</label>
                        <input type="date" name="hire_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">System RBAC Role (If creating login)</label>
                        <select name="assign_role_id" class="form-control">
                            <option value="0">Do Not Assign System Role</option>
                            <?php foreach ($roles as $r): ?>
                                <option value="<?= $r['id'] ?>"><?= e($r['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('esModal')">Cancel</button>
                <button type="submit" name="save_estate_staff" class="btn btn-primary">Save Staff Member</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
