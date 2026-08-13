<?php
// modules/security/index.php - Modular Security Routing
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth_check.php';

requirePermission('security.view');

$estate_id = get_estate_id();
$db = Database::getInstance();

$message = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log_visitor'])) {
    requirePermission('security.manage');
    try {
        verifyCsrfToken();
        $name = sanitize($_POST['name']);
        $phone = sanitize($_POST['phone']);
        $flat_id = !empty($_POST['flat_id']) ? intval($_POST['flat_id']) : null;

        if (empty($name)) {
            $error = "Visitor name is required.";
        } else {
            $pass_code = 'PASS-' . rand(1000, 9999);
            $db->query("INSERT INTO visitors (estate_id, flat_id, name, phone, pass_code, status, check_in_time) 
                        VALUES (?, ?, ?, ?, ?, 'checked_in', NOW())", [
                $estate_id, $flat_id, $name, $phone, $pass_code
            ]);

            $message = "Visitor ($name) checked in successfully! Pass Code: #$pass_code";
            logAudit($conn, "Visitor Check-In", "Security", "Visitor checked in: " . $name);
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['checkout_visitor'])) {
    requirePermission('security.manage');
    try {
        verifyCsrfToken();
        $v_id = intval($_POST['visitor_id']);
        $db->query("UPDATE visitors SET status = 'checked_out', check_out_time = NOW() WHERE id = ? AND estate_id = ?", [$v_id, $estate_id]);
        $message = "Visitor checked out successfully.";
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch Visitor Logs
$visitors = $db->fetchAll("SELECT v.*, f.number as flat_number, b.name as building_name 
                          FROM visitors v 
                          LEFT JOIN flats f ON v.flat_id = f.id 
                          LEFT JOIN buildings b ON f.building_id = b.id 
                          WHERE v.estate_id = ? 
                          ORDER BY v.check_in_time DESC LIMIT 100", [$estate_id]);

$flats = $db->fetchAll("SELECT f.id, f.number, b.name as building_name 
                        FROM flats f 
                        JOIN buildings b ON f.building_id = b.id 
                        WHERE f.estate_id = ? 
                        ORDER BY b.name, f.number ASC", [$estate_id]);

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
    <div>
        <h1 style="font-size: 1.75rem; font-weight: 700; color: var(--text-primary); margin: 0;">Gate Access & Visitor Security</h1>
        <p style="color: var(--text-muted); font-size: 0.95rem; margin: 0.25rem 0 0;">Monitor visitor check-ins, issue gate passes, and audit entry logs</p>
    </div>
    <?php if (hasPermission('security.manage')): ?>
        <button onclick="openModal('visitorModal')" class="btn btn-primary">
            <i data-lucide="user-check" style="width: 18px; height: 18px;"></i> Check-In Visitor
        </button>
    <?php endif; ?>
</div>

<?php if (!empty($message)): ?>
    <script>document.addEventListener('DOMContentLoaded', function() { showToast(<?= json_encode($message) ?>, 'success'); });</script>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <script>document.addEventListener('DOMContentLoaded', function() { showToast(<?= json_encode($error) ?>, 'error'); });</script>
<?php endif; ?>

<!-- Visitor Table -->
<div class="data-table-wrapper">
    <div class="table-toolbar">
        <h3 style="font-size: 1.05rem; font-weight: 700; margin: 0;">Visitor Access Logs</h3>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Pass Code</th>
                <th>Visitor Name</th>
                <th>Phone</th>
                <th>Visiting Flat</th>
                <th>Check-In Time</th>
                <th>Check-Out Time</th>
                <th>Status</th>
                <th style="text-align: right;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($visitors)): ?>
                <?php foreach ($visitors as $v): ?>
                <tr>
                    <td style="font-weight: 600; color: var(--primary);"><?= e($v['pass_code'] ?? ('PASS-' . $v['id'])) ?></td>
                    <td style="font-weight: 700;"><?= e($v['name']) ?></td>
                    <td style="color: var(--text-muted);"><?= e($v['phone'] ?? 'N/A') ?></td>
                    <td><?= e($v['building_name'] ?? 'General') ?> - Unit <?= e($v['flat_number'] ?? 'N/A') ?></td>
                    <td><?= formatDate($v['check_in_time'], 'M j, g:i A') ?></td>
                    <td><?= !empty($v['check_out_time']) ? formatDate($v['check_out_time'], 'M j, g:i A') : '—' ?></td>
                    <td>
                        <span class="badge <?= $v['status'] === 'checked_in' ? 'badge-success' : 'badge-info' ?>">
                            <?= e($v['status']) ?>
                        </span>
                    </td>
                    <td style="text-align: right;">
                        <?php if ($v['status'] === 'checked_in'): ?>
                            <form method="POST" style="display:inline;">
                                <?= csrfField() ?>
                                <input type="hidden" name="visitor_id" value="<?= $v['id'] ?>">
                                <button type="submit" name="checkout_visitor" class="btn btn-secondary btn-sm">Check-Out</button>
                            </form>
                        <?php else: ?>
                            <span style="font-size: 0.8rem; color: var(--text-muted);">Completed</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="8" class="empty-state">No visitor records found. Click "Check-In Visitor" to log entry.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Check-In Visitor Modal -->
<div id="visitorModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <h3>Visitor Gate Check-In</h3>
            <button class="toast-close" onclick="closeModal('visitorModal')">&times;</button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Visitor Full Name</label>
                    <input type="text" name="name" class="form-control" placeholder="e.g. Alex Smith" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Phone Number</label>
                    <input type="text" name="phone" class="form-control" placeholder="555-9999">
                </div>
                <div class="form-group">
                    <label class="form-label">Visiting Unit / Flat</label>
                    <select name="flat_id" class="form-control">
                        <option value="">General Entry / Facility</option>
                        <?php foreach ($flats as $f): ?>
                            <option value="<?= $f['id'] ?>"><?= e($f['building_name']) ?> - Unit <?= e($f['number']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('visitorModal')">Cancel</button>
                <button type="submit" name="log_visitor" class="btn btn-primary">Check-In Visitor</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
