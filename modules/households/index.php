<?php
// modules/households/index.php - Household Members Directory
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth_check.php';

requirePermission('residents.view');

$db = Database::getInstance();
$message = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_member'])) {
    requirePermission('residents.create');
    try {
        verifyCsrfToken();
        $primary_res_id = intval($_POST['primary_resident_id']);
        $person_id = intval($_POST['person_id']);
        $relationship = sanitize($_POST['relationship_to_head']);
        $is_minor = !empty($_POST['is_minor']) ? 1 : 0;

        if ($primary_res_id <= 0 || $person_id <= 0 || empty($relationship)) {
            $error = "Primary resident, person profile, and relationship are required.";
        } else {
            $db->query("INSERT INTO household_members (primary_resident_id, person_id, relationship_to_head, is_minor) VALUES (?, ?, ?, ?)", [
                $primary_res_id, $person_id, $relationship, $is_minor
            ]);
            $message = "Household member linked successfully!";
            logAudit($conn, "Add Household Member", "Households", "Linked person ID #$person_id to resident ID #$primary_res_id");
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch Household Members List
$members = $db->fetchAll("SELECT hm.*, p.first_name, p.last_name, p.gender, p.date_of_birth, p.primary_phone,
                          head_p.first_name as head_first, head_p.last_name as head_last,
                          u.unit_number, u.unit_code, b.name as building_name
                          FROM household_members hm 
                          JOIN persons p ON hm.person_id = p.id 
                          JOIN residents r ON hm.primary_resident_id = r.id 
                          JOIN persons head_p ON r.person_id = head_p.id 
                          JOIN property_units u ON r.unit_id = u.id 
                          JOIN buildings_blocks b ON u.building_id = b.id 
                          ORDER BY hm.created_at DESC");

$activeResidents = $db->fetchAll("SELECT r.id, r.resident_code, p.first_name, p.last_name, u.unit_number, b.name as building_name
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
        <h1 style="font-size: 1.75rem; font-weight: 700; color: var(--text-primary); margin: 0;">Household Members Directory</h1>
        <p style="color: var(--text-muted); font-size: 0.95rem; margin: 0.25rem 0 0;">Track family members, dependents, and co-habitants linked to primary residents</p>
    </div>
    <?php if (hasPermission('residents.create')): ?>
        <button onclick="openModal('memberModal')" class="btn btn-primary">
            <i data-lucide="user-plus" style="width: 18px; height: 18px;"></i> Add Household Member
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
        <h3 style="font-size: 1.05rem; font-weight: 700; margin: 0;">Registered Household Members (<?= count($members) ?>)</h3>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Member Name</th>
                <th>Relationship to Head</th>
                <th>Primary Resident (Head)</th>
                <th>Property Unit</th>
                <th>Demographic Tag</th>
                <th>Contact Phone</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($members)): ?>
                <?php foreach ($members as $m): ?>
                <tr>
                    <td style="font-weight: 700;"><?= e($m['first_name'] . ' ' . $m['last_name']) ?></td>
                    <td><span class="badge badge-info"><?= e($m['relationship_to_head']) ?></span></td>
                    <td style="font-weight: 600; color: var(--primary);"><?= e($m['head_first'] . ' ' . $m['head_last']) ?></td>
                    <td><?= e($m['building_name']) ?> - Unit <?= e($m['unit_number']) ?> (<?= e($m['unit_code']) ?>)</td>
                    <td>
                        <span class="badge <?= $m['is_minor'] ? 'badge-warning' : 'badge-success' ?>">
                            <?= $m['is_minor'] ? 'Minor (Under 18)' : 'Adult' ?>
                        </span>
                    </td>
                    <td><?= e($m['primary_phone'] ?? 'N/A') ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6" class="empty-state">No household members linked yet. Click "Add Household Member" to register dependents.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Modal -->
<div id="memberModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <h3>Add Household Member</h3>
            <button class="toast-close" onclick="closeModal('memberModal')">&times;</button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Primary Resident (Head of Household) *</label>
                    <select name="primary_resident_id" class="form-control" required>
                        <option value="">Select Primary Resident...</option>
                        <?php foreach ($activeResidents as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= e($r['head_first'] ?? $r['first_name'] . ' ' . $r['last_name']) ?> (<?= e($r['building_name']) ?> - Unit <?= e($r['unit_number']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Member Person Profile *</label>
                    <select name="person_id" class="form-control" required>
                        <option value="">Select Person Profile...</option>
                        <?php foreach ($persons as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= e($p['first_name'] . ' ' . $p['last_name']) ?> (<?= e($p['person_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Relationship to Head *</label>
                        <input type="text" name="relationship_to_head" class="form-control" placeholder="Spouse, Child, Sibling" required>
                    </div>
                    <div class="form-group" style="display: flex; align-items: center; padding-top: 1.8rem;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-size: 0.9rem;">
                            <input type="checkbox" name="is_minor" value="1">
                            <span>Is Minor (Child under 18)</span>
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('memberModal')">Cancel</button>
                <button type="submit" name="save_member" class="btn btn-primary">Save Member</button>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
