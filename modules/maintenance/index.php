<?php
// modules/maintenance/index.php - Work Orders & Maintenance Tickets
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../classes/ReferenceGenerator.php';

requirePermission('maintenance.view');

$db = Database::getInstance();
$message = "";
$error = "";

// 1. SUBMIT TICKET
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_ticket'])) {
    requirePermission('maintenance.create');
    try {
        verifyCsrfToken();
        $unit_id = intval($_POST['unit_id']);
        $reporter_p_id = intval($_POST['reporter_person_id']);
        $title = sanitize($_POST['title']);
        $desc = sanitize($_POST['description'] ?? '');
        $cat = sanitize($_POST['category'] ?? 'plumbing');
        $priority = sanitize($_POST['priority'] ?? 'medium');
        $resp = sanitize($_POST['cost_responsibility'] ?? 'resident_billed');
        $est_cost = floatval($_POST['estimated_cost'] ?? 0.00);

        if ($unit_id <= 0 || $reporter_p_id <= 0 || empty($title)) {
            $error = "Property unit, reporter profile, and title are required.";
        } else {
            $tkt_code = ReferenceGenerator::generate('ticket');
            $db->query("INSERT INTO maintenance_tickets (ticket_code, unit_id, reporter_person_id, title, description, category, priority, cost_responsibility, estimated_cost, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'submitted')", [
                $tkt_code, $unit_id, $reporter_p_id, $title, $desc, $cat, $priority, $resp, $est_cost
            ]);
            $message = "Maintenance ticket #$tkt_code submitted successfully!";
            logAudit($conn, "Submit Maintenance Ticket", "Maintenance", "Submitted ticket #$tkt_code ($title)");
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// 2. UPDATE TICKET STATUS / RESOLUTION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_ticket_status'])) {
    requirePermission('maintenance.manage');
    try {
        verifyCsrfToken();
        $tkt_id = intval($_POST['ticket_id']);
        $status = sanitize($_POST['status']);
        $assigned_staff_id = !empty($_POST['assigned_staff_id']) ? intval($_POST['assigned_staff_id']) : null;
        $actual_cost = floatval($_POST['actual_cost'] ?? 0.00);
        $res_notes = sanitize($_POST['admin_resolution_notes'] ?? '');

        if ($tkt_id <= 0) {
            $error = "Invalid ticket ID.";
        } else {
            $resolved_at = ($status === 'resolved' || $status === 'closed') ? date('Y-m-d H:i:s') : null;
            $db->query("UPDATE maintenance_tickets SET status = ?, assigned_staff_id = ?, actual_cost = ?, admin_resolution_notes = ?, resolved_at = ? WHERE id = ?", [
                $status, $assigned_staff_id, $actual_cost, $res_notes, $resolved_at, $tkt_id
            ]);
            $message = "Maintenance ticket status updated to " . ucfirst($status) . "!";
            logAudit($conn, "Update Ticket Status", "Maintenance", "Updated ticket ID #$tkt_id to $status");
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch Maintenance Tickets
$tickets = $db->fetchAll("SELECT mt.*, u.unit_number, u.unit_code, b.name as building_name,
                          p.first_name, p.last_name, p.primary_phone,
                          stf_p.first_name as staff_first, stf_p.last_name as staff_last
                          FROM maintenance_tickets mt 
                          JOIN property_units u ON mt.unit_id = u.id 
                          JOIN buildings_blocks b ON u.building_id = b.id 
                          JOIN persons p ON mt.reporter_person_id = p.id 
                          LEFT JOIN estate_staff es ON mt.assigned_staff_id = es.id 
                          LEFT JOIN persons stf_p ON es.person_id = stf_p.id 
                          ORDER BY mt.created_at DESC");

$units = $db->fetchAll("SELECT u.id, u.unit_number, u.unit_code, b.name as building_name FROM property_units u JOIN buildings_blocks b ON u.building_id = b.id ORDER BY b.name, u.unit_number ASC");
$persons = $db->fetchAll("SELECT id, person_code, first_name, last_name, primary_phone FROM persons ORDER BY first_name ASC");
$staffList = $db->fetchAll("SELECT es.id, p.first_name, p.last_name, es.position_title FROM estate_staff es JOIN persons p ON es.person_id = p.id ORDER BY p.first_name ASC");

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
    <div>
        <h1 style="font-size: 1.75rem; font-weight: 700; color: var(--text-primary); margin: 0;">Maintenance & Work Orders</h1>
        <p style="color: var(--text-muted); font-size: 0.95rem; margin: 0.25rem 0 0;">Manage facility issues, plumbing/electrical work orders, assigned staff, and repair costs</p>
    </div>
    <?php if (hasPermission('maintenance.create')): ?>
        <button onclick="openModal('ticketModal')" class="btn btn-primary">
            <i data-lucide="wrench" style="width: 18px; height: 18px;"></i> Submit Maintenance Ticket
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
        <h3 style="font-size: 1.05rem; font-weight: 700; margin: 0;">Work Order Requests (<?= count($tickets) ?>)</h3>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Ticket Code</th>
                <th>Property Unit</th>
                <th>Issue Title</th>
                <th>Category</th>
                <th>Priority</th>
                <th>Cost Responsibility</th>
                <th>Assigned Staff</th>
                <th>Status</th>
                <th style="text-align: right;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($tickets)): ?>
                <?php foreach ($tickets as $t): ?>
                <tr>
                    <td style="font-weight: 700; color: var(--primary);"><?= e($t['ticket_code']) ?></td>
                    <td><?= e($t['building_name']) ?> - Unit <?= e($t['unit_number']) ?></td>
                    <td style="font-weight: 600;"><?= e($t['title']) ?></td>
                    <td><span class="badge badge-info"><?= e(ucfirst($t['category'])) ?></span></td>
                    <td>
                        <span class="badge <?= $t['priority'] === 'emergency' ? 'badge-danger' : ($t['priority'] === 'high' ? 'badge-warning' : 'badge-info') ?>">
                            <?= e(ucfirst($t['priority'])) ?>
                        </span>
                    </td>
                    <td style="color: var(--text-muted); font-size: 0.85rem;"><?= e(str_replace('_', ' ', ucfirst($t['cost_responsibility']))) ?></td>
                    <td><?= e($t['staff_first'] ? ($t['staff_first'] . ' ' . $t['staff_last']) : 'Unassigned') ?></td>
                    <td>
                        <span class="badge <?= $t['status'] === 'resolved' ? 'badge-success' : ($t['status'] === 'in_progress' ? 'badge-info' : 'badge-warning') ?>">
                            <?= e(ucfirst(str_replace('_', ' ', $t['status']))) ?>
                        </span>
                    </td>
                    <td style="text-align: right;">
                        <?php if (hasPermission('maintenance.manage')): ?>
                            <button class="btn btn-secondary btn-sm" onclick='openManageModal(<?= json_encode($t) ?>)'>Manage</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="9" class="empty-state">No maintenance tickets submitted yet. Click "Submit Maintenance Ticket" to log a work order.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Submit Ticket Modal -->
<div id="ticketModal" class="modal-overlay">
    <div class="modal-container" style="max-width: 580px;">
        <div class="modal-header">
            <h3>Submit Maintenance Work Order</h3>
            <button class="toast-close" onclick="closeModal('ticketModal')">&times;</button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Property Unit *</label>
                    <select name="unit_id" class="form-control" required>
                        <option value="">Select Property Unit...</option>
                        <?php foreach ($units as $u): ?>
                            <option value="<?= $u['id'] ?>"><?= e($u['building_name']) ?> - Unit <?= e($u['unit_number']) ?> (<?= e($u['unit_code']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Reporter Person Profile *</label>
                    <select name="reporter_person_id" class="form-control" required>
                        <option value="">Select Reporter...</option>
                        <?php foreach ($persons as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= e($p['first_name'] . ' ' . $p['last_name']) ?> (<?= e($p['primary_phone']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Issue Title / Summary *</label>
                    <input type="text" name="title" class="form-control" placeholder="e.g. Water leak in bathroom ceiling" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Detailed Description</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="Describe problem..."></textarea>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Category</label>
                        <select name="category" class="form-control">
                            <option value="plumbing">Plumbing</option>
                            <option value="electrical">Electrical</option>
                            <option value="structural">Structural / Civil</option>
                            <option value="security">Security Hardware</option>
                            <option value="waste">Waste Disposal</option>
                            <option value="facility">General Facility</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Priority Level</label>
                        <select name="priority" class="form-control">
                            <option value="low">Low</option>
                            <option value="medium" selected>Medium</option>
                            <option value="high">High Priority</option>
                            <option value="emergency">EMERGENCY</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Cost Responsibility</label>
                        <select name="cost_responsibility" class="form-control">
                            <option value="resident_billed">Resident Billed</option>
                            <option value="owner_billed">Property Owner Billed</option>
                            <option value="estate_funded">HOA Estate Funded</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Estimated Cost (₦)</label>
                        <input type="number" step="0.01" name="estimated_cost" class="form-control" placeholder="0.00">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('ticketModal')">Cancel</button>
                <button type="submit" name="save_ticket" class="btn btn-primary">Submit Work Order</button>
            </div>
        </form>
    </div>
</div>

<!-- Manage Ticket Modal -->
<div id="manageModal" class="modal-overlay">
    <div class="modal-container">
        <div class="modal-header">
            <h3>Update Work Order Status</h3>
            <button class="toast-close" onclick="closeModal('manageModal')">&times;</button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" id="m_tkt_id" name="ticket_id" value="0">
            <div class="modal-body">
                <div style="background: var(--bg-body); padding: 1rem; border-radius: 6px; margin-bottom: 1rem;">
                    <div id="m_tkt_code" style="font-weight: 700; color: var(--primary);"></div>
                    <div id="m_tkt_title" style="font-size: 0.9rem; color: var(--text-muted);"></div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Ticket Status *</label>
                        <select id="m_status" name="status" class="form-control">
                            <option value="submitted">Submitted / New</option>
                            <option value="reviewed">Reviewed</option>
                            <option value="assigned">Assigned</option>
                            <option value="in_progress">In Progress</option>
                            <option value="resolved">Resolved</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Assign Estate Staff</label>
                        <select id="m_staff" name="assigned_staff_id" class="form-control">
                            <option value="">Unassigned</option>
                            <?php foreach ($staffList as $s): ?>
                                <option value="<?= $s['id'] ?>"><?= e($s['first_name'] . ' ' . $s['last_name']) ?> (<?= e($s['position_title']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Actual Cost (₦)</label>
                    <input type="number" step="0.01" id="m_actual_cost" name="actual_cost" class="form-control">
                </div>

                <div class="form-group">
                    <label class="form-label">Resolution / Admin Notes</label>
                    <textarea id="m_notes" name="admin_resolution_notes" class="form-control" rows="2" placeholder="Action taken to resolve..."></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('manageModal')">Cancel</button>
                <button type="submit" name="update_ticket_status" class="btn btn-primary">Update Work Order</button>
            </div>
        </form>
    </div>
</div>

<script>
function openManageModal(tkt) {
    document.getElementById('m_tkt_id').value = tkt.id;
    document.getElementById('m_tkt_code').innerText = 'Ticket #' + tkt.ticket_code;
    document.getElementById('m_tkt_title').innerText = tkt.title + ' (' + tkt.building_name + ' - Unit ' + tkt.unit_number + ')';
    document.getElementById('m_status').value = tkt.status;
    document.getElementById('m_staff').value = tkt.assigned_staff_id || '';
    document.getElementById('m_actual_cost').value = tkt.actual_cost || '0.00';
    document.getElementById('m_notes').value = tkt.admin_resolution_notes || '';
    openModal('manageModal');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
