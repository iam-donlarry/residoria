<?php
// modules/residents/index.php - Modular Residents Directory, Head of Household & Move-In Wizard
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../classes/ReferenceGenerator.php';

requirePermission('residents.view');

$db = Database::getInstance();
$message = "";
$error = "";

// 1. HANDLE RESIDENT MOVE-IN WIZARD FORM SUBMISSION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_resident_wizard'])) {
    requirePermission('residents.create');
    try {
        verifyCsrfToken();

        $pdo = $db->getPdo();
        $pdo->beginTransaction();

        $unit_id = intval($_POST['unit_id']);
        $person_id = intval($_POST['person_id'] ?? 0);
        $category = sanitize($_POST['residency_category'] ?? 'tenant');
        $is_head = !empty($_POST['is_head_of_household']) ? 1 : 0;
        $move_in = sanitize($_POST['move_in_date'] ?? date('Y-m-d'));
        $create_user_account = !empty($_POST['create_user_account']);

        // Handle Person Selection or New Person Creation (Prevents Duplicate Identity!)
        if ($person_id <= 0) {
            $first_name = sanitize($_POST['first_name'] ?? '');
            $last_name = sanitize($_POST['last_name'] ?? '');
            $primary_phone = sanitize($_POST['primary_phone'] ?? '');
            $email = sanitize($_POST['email'] ?? '');
            $gender = sanitize($_POST['gender'] ?? 'male');

            if (empty($first_name) || empty($last_name) || empty($primary_phone)) {
                throw new Exception("First name, last name, and primary phone are required for a new Person profile.");
            }

            // Check if person with same phone or email already exists
            $existingPerson = $db->fetchOne("SELECT id FROM persons WHERE primary_phone = ? OR (email IS NOT NULL AND email != '' AND email = ?)", [$primary_phone, $email]);
            if ($existingPerson) {
                $person_id = intval($existingPerson['id']);
            } else {
                $person_code = ReferenceGenerator::generate('person');
                $db->query("INSERT INTO persons (person_code, first_name, last_name, primary_phone, email, gender) VALUES (?, ?, ?, ?, ?, ?)", [
                    $person_code, $first_name, $last_name, $primary_phone, $email ?: null, $gender
                ]);
                $person_id = intval($db->lastInsertId());
            }
        }

        if ($unit_id <= 0 || $person_id <= 0) {
            throw new Exception("Invalid Property Unit or Person profile selected.");
        }

        // If designating as Head of Household, unmark any previous active head in this unit
        if ($is_head === 1) {
            $db->query("UPDATE residents SET is_head_of_household = 0 WHERE unit_id = ? AND status = 'active'", [$unit_id]);
        }

        // Insert Resident Record
        $res_code = ReferenceGenerator::generate('resident');
        $db->query("INSERT INTO residents (person_id, unit_id, resident_code, residency_category, is_head_of_household, move_in_date, status) VALUES (?, ?, ?, ?, ?, ?, 'active')", [
            $person_id, $unit_id, $res_code, $category, $is_head, $move_in
        ]);

        // Update Property Unit Status to Occupied
        $db->query("UPDATE property_units SET status = 'occupied' WHERE id = ?", [$unit_id]);

        // Optionally Add Dependent / Spouse
        $dep_first_name = sanitize($_POST['dep_first_name'] ?? '');
        $dep_last_name = sanitize($_POST['dep_last_name'] ?? '');
        $dep_relation = sanitize($_POST['dep_relationship'] ?? '');
        if (!empty($dep_first_name) && !empty($dep_last_name) && !empty($dep_relation)) {
            $dep_code = ReferenceGenerator::generate('person');
            $dep_phone = sanitize($_POST['dep_phone'] ?? '');
            $dep_is_minor = !empty($_POST['dep_is_minor']) ? 1 : 0;

            $db->query("INSERT INTO persons (person_code, first_name, last_name, primary_phone) VALUES (?, ?, ?, ?)", [
                $dep_code, $dep_first_name, $dep_last_name, $dep_phone ?: null
            ]);
            $dep_person_id = intval($db->lastInsertId());

            $newResId = intval($db->lastInsertId());
            $db->query("INSERT INTO household_members (primary_resident_id, unit_id, person_id, relationship_to_head, is_minor) VALUES (?, ?, ?, ?, ?)", [
                $newResId, $unit_id, $dep_person_id, $dep_relation, $dep_is_minor
            ]);
        }

        // Optionally Add Vehicle
        $plate = strtoupper(sanitize($_POST['vehicle_plate'] ?? ''));
        if (!empty($plate)) {
            $v_code = ReferenceGenerator::generate('vehicle');
            $v_make = sanitize($_POST['vehicle_make'] ?? 'Sedan');
            $v_decal = sanitize($_POST['vehicle_decal'] ?? '');
            $db->query("INSERT IGNORE INTO vehicles (person_id, unit_id, license_plate_number, vehicle_code, make, decal_number, access_status) VALUES (?, ?, ?, ?, ?, ?, 'active')", [
                $person_id, $unit_id, $plate, $v_code, $v_make, $v_decal ?: null
            ]);
        }

        // Optionally Create User Login Account
        if ($create_user_account) {
            $person = $db->fetchOne("SELECT first_name, last_name, email FROM persons WHERE id = ?", [$person_id]);
            if ($person && !empty($person['email'])) {
                $checkUser = $db->fetchOne("SELECT id FROM users WHERE email = ?", [$person['email']]);
                if (!$checkUser) {
                    $pass = password_hash('resident123', PASSWORD_DEFAULT);
                    $db->query("INSERT INTO users (person_id, name, email, password, role, status) VALUES (?, ?, ?, ?, 'resident', 'active')", [
                        $person_id, $person['first_name'] . ' ' . $person['last_name'], $person['email'], $pass
                    ]);
                    $uId = intval($db->lastInsertId());
                    $db->query("INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, 5)", [$uId]);
                }
            }
        }

        $pdo->commit();
        $message = "Resident Move-In completed successfully with Resident Code #$res_code!";
        logAudit($conn, "Resident Move In", "Residents", "Completed resident move-in Code #$res_code for Unit ID #$unit_id");

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Move-In Error: " . $e->getMessage();
    }
}

// 2. HANDLE RESIDENT MOVE OUT ACTION (WITH CASCADE PROTECTION)
if (isset($_GET['action']) && $_GET['action'] === 'move_out' && !empty($_GET['id'])) {
    requirePermission('residents.archive');
    try {
        verifyCsrfToken($_GET['csrf'] ?? '');
        $resId = intval($_GET['id']);
        $res = $db->fetchOne("SELECT r.*, p.first_name, p.last_name FROM residents r JOIN persons p ON r.person_id = p.id WHERE r.id = ?", [$resId]);
        
        if ($res) {
            $unit_id = intval($res['unit_id']);
            
            // Mark Resident as Moved Out
            $db->query("UPDATE residents SET status = 'moved_out', is_head_of_household = 0, actual_move_out_date = CURDATE() WHERE id = ?", [$resId]);
            
            // Cascade: Suspend domestic staff linked to this departing resident
            $db->query("UPDATE domestic_staff SET access_status = 'suspended' WHERE employer_resident_id = ?", [$resId]);

            // Check remaining active residents in unit
            $remaining = $db->fetchOne("SELECT COUNT(*) as c FROM residents WHERE unit_id = ? AND status = 'active'", [$unit_id])['c'];
            if ($remaining == 0) {
                $db->query("UPDATE property_units SET status = 'vacant_available' WHERE id = ?", [$unit_id]);
            } else {
                // If the departing resident was Head of Household, assign headship to the oldest remaining active resident
                if ($res['is_head_of_household'] == 1) {
                    $nextHead = $db->fetchOne("SELECT id FROM residents WHERE unit_id = ? AND status = 'active' ORDER BY move_in_date ASC LIMIT 1", [$unit_id]);
                    if ($nextHead) {
                        $db->query("UPDATE residents SET is_head_of_household = 1 WHERE id = ?", [$nextHead['id']]);
                    }
                }
            }

            $message = "Resident {$res['first_name']} {$res['last_name']} marked as Moved Out. Unit occupancy updated.";
            logAudit($conn, "Resident Move Out", "Residents", "Moved out resident ID #$resId from Unit ID #$unit_id");
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch Active Residents Directory
$residents = $db->fetchAll("
    SELECT r.*, p.first_name, p.last_name, p.primary_phone, p.email, p.person_code,
           u.unit_number, u.unit_code, b.name as building_name, z.name as zone_name
    FROM residents r 
    JOIN persons p ON r.person_id = p.id 
    JOIN property_units u ON r.unit_id = u.id 
    JOIN buildings_blocks b ON u.building_id = b.id 
    JOIN zones_streets z ON b.zone_id = z.id 
    WHERE r.status = 'active' 
    ORDER BY r.is_head_of_household DESC, r.created_at DESC
");

$persons = $db->fetchAll("SELECT id, person_code, first_name, last_name, primary_phone, email FROM persons ORDER BY first_name, last_name ASC");
$units = $db->fetchAll("SELECT u.id, u.unit_number, u.unit_code, u.status, b.name as building_name FROM property_units u JOIN buildings_blocks b ON u.building_id = b.id ORDER BY b.name, u.unit_number ASC");

$pageTitle = "Residents Directory & Move-In Manager";
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="content-wrapper">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h1 class="page-title" style="margin: 0; font-size: 1.75rem; font-weight: 700;">Residents Directory & Occupancy</h1>
            <p class="text-subtitle" style="color: var(--text-secondary); margin-top: 0.25rem;">
                Manage primary residents, designated Heads of Household, and unit occupancy status.
            </p>
        </div>
        <div>
            <button type="button" class="btn btn-primary" onclick="openOffcanvas('moveInWizardModal')">
                <i data-lucide="user-plus" style="width: 16px; height: 16px;"></i> Resident Move-In Wizard
            </button>
        </div>
    </div>

    <?php if (!empty($message)): ?>
    <div class="alert alert-success" style="margin-bottom: 1.5rem;">
        <i data-lucide="check-circle" style="width: 16px; height: 16px; vertical-align: middle;"></i> <?= e($message) ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
    <div class="alert alert-danger" style="margin-bottom: 1.5rem;">
        <i data-lucide="alert-circle" style="width: 16px; height: 16px; vertical-align: middle;"></i> <?= e($error) ?>
    </div>
    <?php endif; ?>

    <div class="card" style="background: white; border-radius: 1rem; border: 1px solid var(--border-color); padding: 1.5rem;">
        <div class="table-responsive">
            <table class="data-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Resident Code</th>
                        <th>Resident Name</th>
                        <th>Role in Household</th>
                        <th>Property Unit</th>
                        <th>Building / Zone</th>
                        <th>Phone Number</th>
                        <th>Move-In Date</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($residents)): ?>
                        <?php foreach ($residents as $r): ?>
                        <tr>
                            <td><code><?= e($r['resident_code']) ?></code></td>
                            <td>
                                <strong><?= e($r['first_name']) ?> <?= e($r['last_name']) ?></strong><br>
                                <small class="text-muted"><?= e($r['email'] ?: 'No email') ?></small>
                            </td>
                            <td>
                                <?php if ($r['is_head_of_household'] == 1): ?>
                                <span class="badge badge-success"><i data-lucide="crown" style="width: 12px; height: 12px; vertical-align: middle;"></i> Head of Household</span>
                                <?php else: ?>
                                <span class="badge badge-info">Co-Resident</span>
                                <?php endif; ?>
                            </td>
                            <td><strong style="color: var(--primary-color);"><?= e($r['unit_code']) ?></strong> (Unit <?= e($r['unit_number']) ?>)</td>
                            <td><?= e($r['building_name']) ?> &bull; <small><?= e($r['zone_name']) ?></small></td>
                            <td><?= e($r['primary_phone']) ?></td>
                            <td><?= formatDate($r['move_in_date']) ?></td>
                            <td style="text-align: right;">
                                <div style="display: flex; gap: 0.35rem; justify-content: flex-end;">
                                    <a href="<?= BASE_URL ?>properties/view?id=<?= $r['unit_id'] ?>" class="btn btn-sm btn-primary" title="Inspect Property 360°">
                                        <i data-lucide="eye" style="width: 14px; height: 14px;"></i> 360° Profile
                                    </a>
                                    <a href="<?= BASE_URL ?>residents?action=move_out&id=<?= $r['id'] ?>&csrf=<?= generateCsrfToken() ?>" 
                                       class="btn btn-sm btn-danger" 
                                       onclick="return confirm('Are you sure you want to mark this resident as Moved Out? Shared staff access will be suspended.')"
                                       title="Mark Move Out">
                                        <i data-lucide="user-minus" style="width: 14px; height: 14px;"></i> Move Out
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="empty-state">No active residents registered in the estate.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- MULTI-STEP RESIDENT MOVE-IN WIZARD OFFCANVAS PANEL -->
<div id="moveInWizardModal" class="offcanvas-overlay">
    <div class="offcanvas-panel">
        <div class="offcanvas-header">
            <h3 style="margin: 0; font-weight: 700; font-size: 1.25rem;">Resident Move-In Wizard</h3>
            <button type="button" class="toast-close" onclick="closeOffcanvas('moveInWizardModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <?= csrfField() ?>
            <div class="offcanvas-body" style="padding: 1.5rem; flex: 1; overflow-y: auto;">
                
                <!-- Section 1: Property & Headship -->
                <h4 style="font-size: 0.9375rem; font-weight: 700; color: var(--primary-color); margin-bottom: 0.75rem;">1. Target Property Unit & Role</h4>
                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                    <div class="form-group">
                        <label class="form-label">Select Property Unit *</label>
                        <select name="unit_id" class="form-control" required>
                            <option value="">-- Choose Unit --</option>
                            <?php foreach ($units as $u): ?>
                            <option value="<?= $u['id'] ?>">
                                <?= e($u['building_name']) ?> - Unit <?= e($u['unit_number']) ?> (<?= e($u['unit_code']) ?>) [<?= strtoupper(e($u['status'])) ?>]
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Residency Category</label>
                        <select name="residency_category" class="form-control">
                            <option value="tenant">Tenant</option>
                            <option value="owner_occupier">Owner Occupier</option>
                            <option value="co_resident">Co-Resident</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label class="checkbox-label" style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-weight: 600;">
                        <input type="checkbox" name="is_head_of_household" value="1" checked style="accent-color: var(--primary-color);">
                        <span>Designate as Head of Household (Primary Billing & Governance Contact)</span>
                    </label>
                </div>

                <!-- Section 2: Person Selection or Creation -->
                <h4 style="font-size: 0.9375rem; font-weight: 700; color: var(--primary-color); margin-bottom: 0.75rem;">2. Resident Identity (Search or Create)</h4>
                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Select Existing Person Profile (Search directory)</label>
                    <select name="person_id" id="person_select" class="form-control" onchange="togglePersonForm(this.value)">
                        <option value="0">+ Register New Person Profile</option>
                        <?php foreach ($persons as $p): ?>
                        <option value="<?= $p['id'] ?>">
                            <?= e($p['first_name']) ?> <?= e($p['last_name']) ?> (<?= e($p['primary_phone']) ?>) [<?= e($p['person_code']) ?>]
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div id="newPersonFields" style="display: block; background: var(--hover-bg); padding: 1rem; border-radius: 0.75rem; border: 1px solid var(--border-color); margin-bottom: 1.25rem;">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 0.75rem;">
                        <div class="form-group">
                            <label class="form-label">First Name *</label>
                            <input type="text" name="first_name" class="form-control" placeholder="e.g. John">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Last Name *</label>
                            <input type="text" name="last_name" class="form-control" placeholder="e.g. Ade">
                        </div>
                    </div>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <div class="form-group">
                            <label class="form-label">Primary Phone *</label>
                            <input type="text" name="primary_phone" class="form-control" placeholder="+234 803 000 0000">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email Address</label>
                            <input type="email" name="email" class="form-control" placeholder="john@example.com">
                        </div>
                    </div>
                </div>

                <!-- Section 3: Dates & Account -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                    <div class="form-group">
                        <label class="form-label">Move-In Date</label>
                        <input type="date" name="move_in_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group" style="display: flex; align-items: center; margin-top: 1.5rem;">
                        <label class="checkbox-label" style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-weight: 500; font-size: 0.875rem;">
                            <input type="checkbox" name="create_user_account" value="1" checked style="accent-color: var(--primary-color);">
                            <span>Create Resident Portal Login</span>
                        </label>
                    </div>
                </div>

                <!-- Section 4: Optional Vehicle -->
                <h4 style="font-size: 0.9375rem; font-weight: 700; color: var(--primary-color); margin-bottom: 0.75rem;">3. Optional Resident Vehicle Registration</h4>
                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.75rem;">
                    <div class="form-group">
                        <label class="form-label">License Plate</label>
                        <input type="text" name="vehicle_plate" class="form-control" placeholder="LSD-492AA">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Vehicle Make</label>
                        <input type="text" name="vehicle_make" class="form-control" placeholder="Toyota Camry">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Decal / RFID Tag</label>
                        <input type="text" name="vehicle_decal" class="form-control" placeholder="TAG-101">
                    </div>
                </div>

            </div>
            <div class="offcanvas-footer" style="padding: 1.25rem 1.75rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 0.75rem; background: var(--hover-bg);">
                <button type="button" class="btn btn-secondary" onclick="closeOffcanvas('moveInWizardModal')">Cancel</button>
                <button type="submit" name="save_resident_wizard" class="btn btn-primary">
                    <i data-lucide="check" style="width: 16px; height: 16px;"></i> Complete Move-In Wizard
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function togglePersonForm(val) {
    const fields = document.getElementById('newPersonFields');
    if (val == 0) {
        fields.style.display = 'block';
    } else {
        fields.style.display = 'none';
    }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
