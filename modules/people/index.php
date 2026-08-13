<?php
// modules/people/index.php - Centralized Persons Directory
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../classes/ReferenceGenerator.php';

requirePermission('people.view');

$db = Database::getInstance();
$message = "";
$error = "";

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_person'])) {
    requirePermission('people.create');
    try {
        verifyCsrfToken();
        $person_id = intval($_POST['person_id'] ?? 0);
        $first_name = sanitize($_POST['first_name']);
        $last_name = sanitize($_POST['last_name']);
        $other_names = sanitize($_POST['other_names'] ?? '');
        $email = sanitize($_POST['email']);
        $primary_phone = sanitize($_POST['primary_phone']);
        $secondary_phone = sanitize($_POST['secondary_phone'] ?? '');
        $gender = sanitize($_POST['gender'] ?? 'other');
        $dob = !empty($_POST['date_of_birth']) ? sanitize($_POST['date_of_birth']) : null;
        $id_type = sanitize($_POST['id_type'] ?? '');
        $id_number = sanitize($_POST['id_number'] ?? '');
        $em_name = sanitize($_POST['emergency_contact_name'] ?? '');
        $em_phone = sanitize($_POST['emergency_contact_phone'] ?? '');
        $em_rel = sanitize($_POST['emergency_contact_relationship'] ?? '');

        if (empty($first_name) || empty($last_name) || empty($primary_phone)) {
            $error = "First name, last name, and primary phone number are required.";
        } else {
            if ($person_id > 0) {
                requirePermission('people.edit');
                $db->query("UPDATE persons SET first_name = ?, last_name = ?, other_names = ?, email = ?, primary_phone = ?, secondary_phone = ?, gender = ?, date_of_birth = ?, id_type = ?, id_number = ?, emergency_contact_name = ?, emergency_contact_phone = ?, emergency_contact_relationship = ? WHERE id = ?", [
                    $first_name, $last_name, $other_names, $email, $primary_phone, $secondary_phone, $gender, $dob, $id_type, $id_number, $em_name, $em_phone, $em_rel, $person_id
                ]);
                $message = "Person profile updated successfully!";
                logAudit($conn, "Update Person", "People", "Updated profile for ID #$person_id");
            } else {
                $person_code = ReferenceGenerator::generate('person');
                $db->query("INSERT INTO persons (person_code, first_name, last_name, other_names, email, primary_phone, secondary_phone, gender, date_of_birth, id_type, id_number, emergency_contact_name, emergency_contact_phone, emergency_contact_relationship) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", [
                    $person_code, $first_name, $last_name, $other_names, $email, $primary_phone, $secondary_phone, $gender, $dob, $id_type, $id_number, $em_name, $em_phone, $em_rel
                ]);
                $message = "Person registered successfully with Code #$person_code!";
                logAudit($conn, "Create Person", "People", "Registered person: $first_name $last_name ($person_code)");
            }
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch Persons Directory with Search Filter
$search = sanitize($_GET['q'] ?? '');
$query = "SELECT * FROM persons";
$params = [];
if (!empty($search)) {
    $query .= " WHERE person_code LIKE ? OR first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR primary_phone LIKE ?";
    $term = "%$search%";
    $params = [$term, $term, $term, $term, $term];
}
$query .= " ORDER BY created_at DESC LIMIT 100";
$persons = $db->fetchAll($query, $params);

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
    <div>
        <h1 style="font-size: 1.75rem; font-weight: 700; color: var(--text-primary); margin: 0;">Centralized Persons Directory</h1>
        <p style="color: var(--text-muted); font-size: 0.95rem; margin: 0.25rem 0 0;">Unified identity management for estate owners, residents, tenants, and personnel</p>
    </div>
    <?php if (hasPermission('people.create')): ?>
        <button onclick="openModal('personModal')" class="btn btn-primary">
            <i data-lucide="user-plus" style="width: 18px; height: 18px;"></i> Register Person
        </button>
    <?php endif; ?>
</div>

<?php if (!empty($message)): ?>
    <script>document.addEventListener('DOMContentLoaded', function() { showToast(<?= json_encode($message) ?>, 'success'); });</script>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <script>document.addEventListener('DOMContentLoaded', function() { showToast(<?= json_encode($error) ?>, 'error'); });</script>
<?php endif; ?>

<!-- Persons Table -->
<div class="data-table-wrapper">
    <div class="table-toolbar" style="display: flex; justify-content: space-between; align-items: center;">
        <h3 style="font-size: 1.05rem; font-weight: 700; margin: 0;">Registered Individuals (<?= count($persons) ?>)</h3>
        <form method="GET" style="display: flex; gap: 0.5rem;">
            <input type="text" name="q" class="form-control btn-sm" placeholder="Search by name, phone, code..." value="<?= e($search) ?>" style="width: 240px;">
            <button type="submit" class="btn btn-secondary btn-sm">Search</button>
        </form>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Person Code</th>
                <th>Full Name</th>
                <th>Primary Phone</th>
                <th>Email Address</th>
                <th>Gender</th>
                <th>Identity Document</th>
                <th style="text-align: right;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($persons)): ?>
                <?php foreach ($persons as $p): ?>
                <tr>
                    <td style="font-weight: 600; color: var(--primary);"><?= e($p['person_code']) ?></td>
                    <td style="font-weight: 700;"><?= e($p['first_name'] . ' ' . $p['last_name'] . ($p['other_names'] ? ' ' . $p['other_names'] : '')) ?></td>
                    <td><?= e($p['primary_phone']) ?></td>
                    <td style="color: var(--text-muted);"><?= e($p['email'] ?? 'N/A') ?></td>
                    <td><?= e(ucfirst($p['gender'] ?? 'N/A')) ?></td>
                    <td><span class="badge badge-info"><?= e($p['id_type'] ? $p['id_type'] : 'Not Provided') ?></span></td>
                    <td style="text-align: right;">
                        <button class="btn btn-secondary btn-sm" onclick='editPerson(<?= json_encode($p) ?>)'>Edit</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="7" class="empty-state">No persons found. Click "Register Person" to add new profile.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Register / Edit Person Modal -->
<div id="personModal" class="modal-overlay">
    <div class="modal-container" style="max-width: 620px;">
        <div class="modal-header">
            <h3 id="personModalTitle">Register Person Profile</h3>
            <button class="toast-close" onclick="closeModal('personModal')">&times;</button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" id="person_id" name="person_id" value="0">
            <div class="modal-body">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">First Name *</label>
                        <input type="text" id="first_name" name="first_name" class="form-control" required placeholder="John">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Last Name *</label>
                        <input type="text" id="last_name" name="last_name" class="form-control" required placeholder="Ade">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Other Names</label>
                        <input type="text" id="other_names" name="other_names" class="form-control" placeholder="Okafor">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Gender</label>
                        <select id="gender" name="gender" class="form-control">
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Primary Phone *</label>
                        <input type="text" id="primary_phone" name="primary_phone" class="form-control" required placeholder="+234 803 000 0000">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control" placeholder="john@example.com">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" id="date_of_birth" name="date_of_birth" class="form-control">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Identity Document Type</label>
                        <select id="id_type" name="id_type" class="form-control">
                            <option value="">Select Document Type...</option>
                            <option value="NIN">National Identity Number (NIN)</option>
                            <option value="Driver License">Driver's License</option>
                            <option value="International Passport">International Passport</option>
                            <option value="Voter Card">Voter's Card</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">ID Number / Reference</label>
                    <input type="text" id="id_number" name="id_number" class="form-control" placeholder="e.g. 12345678901">
                </div>

                <h4 style="font-size: 0.95rem; font-weight: 700; color: var(--primary); margin: 1.25rem 0 0.75rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.3rem;">Emergency Contact</h4>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.75rem;">
                    <div class="form-group">
                        <label class="form-label">Contact Name</label>
                        <input type="text" id="emergency_contact_name" name="emergency_contact_name" class="form-control" placeholder="Mary Ade">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Contact Phone</label>
                        <input type="text" id="emergency_contact_phone" name="emergency_contact_phone" class="form-control" placeholder="+234 803...">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Relationship</label>
                        <input type="text" id="emergency_contact_relationship" name="emergency_contact_relationship" class="form-control" placeholder="Spouse">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('personModal')">Cancel</button>
                <button type="submit" name="save_person" class="btn btn-primary">Save Person Profile</button>
            </div>
        </form>
    </div>
</div>

<script>
function editPerson(p) {
    document.getElementById('person_id').value = p.id;
    document.getElementById('first_name').value = p.first_name;
    document.getElementById('last_name').value = p.last_name;
    document.getElementById('other_names').value = p.other_names || '';
    document.getElementById('gender').value = p.gender || 'male';
    document.getElementById('primary_phone').value = p.primary_phone;
    document.getElementById('email').value = p.email || '';
    document.getElementById('date_of_birth').value = p.date_of_birth || '';
    document.getElementById('id_type').value = p.id_type || '';
    document.getElementById('id_number').value = p.id_number || '';
    document.getElementById('emergency_contact_name').value = p.emergency_contact_name || '';
    document.getElementById('emergency_contact_phone').value = p.emergency_contact_phone || '';
    document.getElementById('emergency_contact_relationship').value = p.emergency_contact_relationship || '';
    document.getElementById('personModalTitle').innerText = 'Edit Person Profile';
    openModal('personModal');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
