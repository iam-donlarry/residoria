<?php
// modules/residents/index.php - Modular Residents Directory, Head of Household, Edit/Login Provisioning & Relocation History Manager
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

// 2. HANDLE EDIT RESIDENT & ASSIGN USER LOGIN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_resident_profile'])) {
    requirePermission('residents.edit');
    try {
        verifyCsrfToken();
        $res_id = intval($_POST['edit_resident_id']);
        $category = sanitize($_POST['edit_residency_category'] ?? 'tenant');
        $is_head = !empty($_POST['edit_is_head_of_household']) ? 1 : 0;
        $move_in = sanitize($_POST['edit_move_in_date'] ?? date('Y-m-d'));
        $provision_user = !empty($_POST['edit_provision_user']);
        $reset_password = !empty($_POST['edit_reset_password']);

        $res = $db->fetchOne("SELECT r.*, p.first_name, p.last_name, p.email, p.primary_phone FROM residents r JOIN persons p ON r.person_id = p.id WHERE r.id = ?", [$res_id]);
        if (!$res) {
            throw new Exception("Resident profile not found.");
        }

        // If designating as Head of Household, unmark previous head in this unit
        if ($is_head === 1 && $res['is_head_of_household'] != 1) {
            $db->query("UPDATE residents SET is_head_of_household = 0 WHERE unit_id = ? AND status = 'active'", [$res['unit_id']]);
        }

        // Update resident details
        $db->query("UPDATE residents SET residency_category = ?, is_head_of_household = ?, move_in_date = ? WHERE id = ?", [
            $category, $is_head, $move_in, $res_id
        ]);

        // Handle User Login Account Provisioning / Password Reset
        if ($provision_user && !empty($res['email'])) {
            $checkUser = $db->fetchOne("SELECT id FROM users WHERE person_id = ? OR email = ?", [$res['person_id'], $res['email']]);
            if (!$checkUser) {
                $pass = password_hash('resident123', PASSWORD_DEFAULT);
                $db->query("INSERT INTO users (person_id, name, email, password, role, status) VALUES (?, ?, ?, ?, 'resident', 'active')", [
                    $res['person_id'], $res['first_name'] . ' ' . $res['last_name'], $res['email'], $pass
                ]);
                $uId = intval($db->lastInsertId());
                $db->query("INSERT IGNORE INTO user_roles (user_id, role_id) VALUES (?, 5)", [$uId]);
                $message = "Resident profile updated & new Resident User Login Account provisioned (Default Password: resident123)!";
            } elseif ($reset_password) {
                $pass = password_hash('resident123', PASSWORD_DEFAULT);
                $db->query("UPDATE users SET password = ?, status = 'active' WHERE id = ?", [$pass, $checkUser['id']]);
                $message = "Resident profile updated & Login Password reset to resident123!";
            } else {
                $message = "Resident profile updated successfully!";
            }
        } else {
            $message = "Resident profile updated successfully!";
        }

        logAudit($conn, "Resident Profile Edit", "Residents", "Updated resident ID #$res_id profile & tenancy attributes");
    } catch (Exception $e) {
        $error = "Update Error: " . $e->getMessage();
    }
}

// 3. HANDLE RESIDENT UNIT RELOCATION / FLAT TRANSFER
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['relocate_resident'])) {
    requirePermission('residents.edit');
    try {
        verifyCsrfToken();
        $pdo = $db->getPdo();
        $pdo->beginTransaction();

        $res_id = intval($_POST['relocate_resident_id']);
        $new_unit_id = intval($_POST['target_unit_id']);
        $transfer_date = sanitize($_POST['transfer_date'] ?? date('Y-m-d'));
        $new_category = sanitize($_POST['relocate_residency_category'] ?? 'tenant');
        $is_head = !empty($_POST['relocate_is_head']) ? 1 : 0;

        if ($res_id <= 0 || $new_unit_id <= 0) {
            throw new Exception("Invalid resident profile or target unit selected.");
        }

        $current = $db->fetchOne("SELECT r.*, p.first_name, p.last_name FROM residents r JOIN persons p ON r.person_id = p.id WHERE r.id = ?", [$res_id]);
        if (!$current) {
            throw new Exception("Resident record not found.");
        }

        $old_unit_id = intval($current['unit_id']);
        if ($old_unit_id === $new_unit_id) {
            throw new Exception("Target unit must be different from current property unit.");
        }

        // 1. Archive previous unit occupancy
        $db->query("UPDATE residents SET status = 'moved_out', is_head_of_household = 0, actual_move_out_date = ? WHERE id = ?", [
            $transfer_date, $res_id
        ]);

        // 2. Check previous unit remaining active residents
        $remainingOld = $db->fetchOne("SELECT COUNT(*) as c FROM residents WHERE unit_id = ? AND status = 'active'", [$old_unit_id])['c'];
        if ($remainingOld == 0) {
            $db->query("UPDATE property_units SET status = 'vacant_available' WHERE id = ?", [$old_unit_id]);
        }

        // 3. Unmark previous head in new target unit if setting as head
        if ($is_head === 1) {
            $db->query("UPDATE residents SET is_head_of_household = 0 WHERE unit_id = ? AND status = 'active'", [$new_unit_id]);
        }

        // 4. Create new active resident occupancy in target unit
        $res_code = ReferenceGenerator::generate('resident');
        $db->query("INSERT INTO residents (person_id, unit_id, resident_code, residency_category, is_head_of_household, move_in_date, status) VALUES (?, ?, ?, ?, ?, ?, 'active')", [
            $current['person_id'], $new_unit_id, $res_code, $new_category, $is_head, $transfer_date
        ]);

        // 5. Update new unit status to occupied
        $db->query("UPDATE property_units SET status = 'occupied' WHERE id = ?", [$new_unit_id]);

        // 6. Update registered vehicles unit_id reference
        $db->query("UPDATE vehicles SET unit_id = ? WHERE person_id = ?", [$new_unit_id, $current['person_id']]);

        $pdo->commit();
        $message = "Resident {$current['first_name']} {$current['last_name']} successfully relocated to new unit! Tenancy history preserved.";
        logAudit($conn, "Resident Unit Relocation", "Residents", "Relocated resident {$current['first_name']} {$current['last_name']} from Unit ID #$old_unit_id to Unit ID #$new_unit_id");

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = "Relocation Error: " . $e->getMessage();
    }
}

// 4. HANDLE RESIDENT MOVE OUT ACTION (WITH CASCADE PROTECTION)
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
           u.unit_number, u.unit_code, b.name as building_name, z.name as zone_name,
           (SELECT COUNT(*) FROM users u2 WHERE u2.person_id = p.id) as has_user_account
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
                Manage primary residents, designated Heads of Household, unit transfers, and login access.
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
                        <th>User Account</th>
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
                                <?php if ($r['has_user_account'] > 0): ?>
                                    <span class="badge badge-success" title="Portal User Account Active"><i data-lucide="user-check" style="width: 12px; height: 12px;"></i> Active Login</span>
                                <?php else: ?>
                                    <span class="badge badge-warning" title="No Portal Account Assigned"><i data-lucide="user-x" style="width: 12px; height: 12px;"></i> No Access</span>
                                <?php endif; ?>
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
                                <div class="table-dropdown">
                                    <button type="button" class="table-dropdown-toggle" onclick="toggleTableDropdown(event, this)" style="display: inline-flex; align-items: center; justify-content: center; gap: 0.4rem; padding: 0.45rem 0.85rem; font-size: 0.8125rem; font-weight: 600; color: #1e293b; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 8px; cursor: pointer; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
                                        <i data-lucide="more-horizontal" style="width: 15px; height: 15px;"></i>
                                        <span>Actions</span>
                                        <i data-lucide="chevron-down" style="width: 12px; height: 12px;"></i>
                                    </button>
                                    <div class="table-dropdown-menu">
                                        <button type="button" class="table-dropdown-item" onclick="openEditResidentModal(<?= $r['id'] ?>)">
                                            <i data-lucide="edit-3"></i> Edit & Login Access
                                        </button>
                                        <button type="button" class="table-dropdown-item" onclick="openRelocateModal(<?= $r['id'] ?>, '<?= e($r['first_name'] . ' ' . $r['last_name']) ?>', '<?= e($r['unit_code']) ?>')">
                                            <i data-lucide="arrow-right-left"></i> Relocate / Transfer Unit
                                        </button>
                                        <button type="button" class="table-dropdown-item" onclick="openHistoryModal(<?= $r['person_id'] ?>)">
                                            <i data-lucide="history"></i> Tenancy History
                                        </button>
                                        <a href="<?= BASE_URL ?>properties/view?id=<?= $r['unit_id'] ?>" class="table-dropdown-item">
                                            <i data-lucide="eye"></i> 360° Property Profile
                                        </a>
                                        <div class="table-dropdown-divider"></div>
                                        <a href="<?= BASE_URL ?>residents?action=move_out&id=<?= $r['id'] ?>&csrf=<?= generateCsrfToken() ?>" 
                                           class="table-dropdown-item danger-item" 
                                           onclick="return confirm('Are you sure you want to mark this resident as Moved Out? Shared staff access will be suspended.')">
                                            <i data-lucide="user-minus"></i> Move Out Resident
                                        </a>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="9" class="empty-state">No active residents registered in the estate.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- 1. MULTI-STEP RESIDENT MOVE-IN WIZARD OFFCANVAS PANEL -->
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

<!-- 2. EDIT RESIDENT PROFILE & USER LOGIN PROVISIONING MODAL -->
<div id="editResidentModal" class="offcanvas-overlay">
    <div class="offcanvas-panel">
        <div class="offcanvas-header">
            <h3 style="margin: 0; font-weight: 700; font-size: 1.25rem;">Edit Resident Profile & User Login Access</h3>
            <button type="button" class="toast-close" onclick="closeOffcanvas('editResidentModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <?= csrfField() ?>
            <input type="hidden" name="edit_resident_id" id="edit_resident_id">
            <div class="offcanvas-body" style="padding: 1.5rem; flex: 1; overflow-y: auto;">
                
                <div style="background: var(--hover-bg); padding: 1rem; border-radius: 0.75rem; border: 1px solid var(--border-color); margin-bottom: 1.25rem;">
                    <div style="font-weight: 700; font-size: 1.1rem; color: var(--text-primary);" id="edit_resident_name">Resident Name</div>
                    <div style="font-size: 0.85rem; color: var(--text-secondary);" id="edit_resident_unit">Unit Info</div>
                    <div style="font-size: 0.85rem; color: var(--text-secondary);" id="edit_resident_email">Email</div>
                </div>

                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label class="form-label">Residency Category *</label>
                    <select name="edit_residency_category" id="edit_residency_category" class="form-control" required>
                        <option value="tenant">Tenant</option>
                        <option value="owner_occupier">Owner Occupier</option>
                        <option value="co_resident">Co-Resident</option>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label class="checkbox-label" style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-weight: 600;">
                        <input type="checkbox" name="edit_is_head_of_household" id="edit_is_head_of_household" value="1" style="accent-color: var(--primary-color);">
                        <span>Head of Household (Primary Billing & Governance Contact)</span>
                    </label>
                </div>

                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label class="form-label">Move-In Date *</label>
                    <input type="date" name="edit_move_in_date" id="edit_move_in_date" class="form-control" required>
                </div>

                <hr style="border: none; border-top: 1px solid var(--border-color); margin: 1.5rem 0;">

                <h4 style="font-size: 0.9375rem; font-weight: 700; color: var(--primary-color); margin-bottom: 0.75rem;">Resident User Login Account Access</h4>
                
                <div id="user_account_status_box" style="margin-bottom: 1.25rem; padding: 0.85rem; border-radius: 0.5rem; background: #f8fafc; border: 1px solid var(--border-color);">
                    <!-- Status populated via JS -->
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="checkbox-label" style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-weight: 600;">
                        <input type="checkbox" name="edit_provision_user" id="edit_provision_user" value="1" style="accent-color: var(--primary-color);">
                        <span>Provision / Grant System User Login Account (Role: Resident)</span>
                    </label>
                    <small class="text-muted" style="display: block; margin-top: 0.25rem;">If resident does not have an account, this creates a login user using their email address with default password <code>resident123</code>.</small>
                </div>

                <div class="form-group" id="reset_pass_wrapper" style="margin-bottom: 1rem; display: none;">
                    <label class="checkbox-label" style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-weight: 500; color: var(--danger-color);">
                        <input type="checkbox" name="edit_reset_password" value="1" style="accent-color: var(--danger-color);">
                        <span>Reset Password to Default (<code>resident123</code>)</span>
                    </label>
                </div>

            </div>
            <div class="offcanvas-footer" style="padding: 1.25rem 1.75rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 0.75rem; background: var(--hover-bg);">
                <button type="button" class="btn btn-secondary" onclick="closeOffcanvas('editResidentModal')">Cancel</button>
                <button type="submit" name="update_resident_profile" class="btn btn-primary">
                    <i data-lucide="check" style="width: 16px; height: 16px;"></i> Save Resident Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 3. RELOCATE / FLAT TRANSFER WIZARD MODAL -->
<div id="relocateResidentModal" class="offcanvas-overlay">
    <div class="offcanvas-panel">
        <div class="offcanvas-header">
            <h3 style="margin: 0; font-weight: 700; font-size: 1.25rem;">Relocate Resident / Flat Transfer Wizard</h3>
            <button type="button" class="toast-close" onclick="closeOffcanvas('relocateResidentModal')">&times;</button>
        </div>
        <form method="POST" action="">
            <?= csrfField() ?>
            <input type="hidden" name="relocate_resident_id" id="relocate_resident_id">
            <div class="offcanvas-body" style="padding: 1.5rem; flex: 1; overflow-y: auto;">
                
                <div class="alert alert-info" style="margin-bottom: 1.25rem; font-size: 0.875rem;">
                    <i data-lucide="info" style="width: 16px; height: 16px; vertical-align: middle;"></i> Relocating a resident archives their current flat occupancy as <strong>Moved Out</strong> and opens a seamless new active occupancy in the target flat, preserving full historical records.
                </div>

                <div style="background: var(--hover-bg); padding: 1rem; border-radius: 0.75rem; border: 1px solid var(--border-color); margin-bottom: 1.25rem;">
                    <div style="font-weight: 700; font-size: 1.1rem; color: var(--text-primary);" id="relocate_resident_name">Resident Name</div>
                    <div style="font-size: 0.85rem; color: var(--text-secondary);">Current Occupancy: <strong id="relocate_current_unit">Unit</strong></div>
                </div>

                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label class="form-label">Select Target New Property Unit *</label>
                    <select name="target_unit_id" id="target_unit_id" class="form-control" required>
                        <option value="">-- Choose Target Building & Unit --</option>
                        <?php foreach ($units as $u): ?>
                        <option value="<?= $u['id'] ?>">
                            <?= e($u['building_name']) ?> - Unit <?= e($u['unit_number']) ?> (<?= e($u['unit_code']) ?>) [<?= strtoupper(e($u['status'])) ?>]
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                    <div class="form-group">
                        <label class="form-label">Relocation / Transfer Date *</label>
                        <input type="date" name="transfer_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">New Residency Category</label>
                        <select name="relocate_residency_category" class="form-control">
                            <option value="tenant">Tenant</option>
                            <option value="owner_occupier">Owner Occupier</option>
                            <option value="co_resident">Co-Resident</option>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label class="checkbox-label" style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-weight: 600;">
                        <input type="checkbox" name="relocate_is_head" value="1" checked style="accent-color: var(--primary-color);">
                        <span>Designate as Head of Household in New Property Unit</span>
                    </label>
                </div>

            </div>
            <div class="offcanvas-footer" style="padding: 1.25rem 1.75rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 0.75rem; background: var(--hover-bg);">
                <button type="button" class="btn btn-secondary" onclick="closeOffcanvas('relocateResidentModal')">Cancel</button>
                <button type="submit" name="relocate_resident" class="btn btn-info">
                    <i data-lucide="arrow-right-left" style="width: 16px; height: 16px;"></i> Complete Unit Relocation
                </button>
            </div>
        </form>
    </div>
</div>

<!-- 4. TENANCY & RELOCATION MOVEMENT HISTORY TIMELINE MODAL -->
<div id="historyTimelineModal" class="offcanvas-overlay">
    <div class="offcanvas-panel" style="max-width: 620px;">
        <div class="offcanvas-header">
            <h3 style="margin: 0; font-weight: 700; font-size: 1.25rem;">Tenancy & Relocation Movement History</h3>
            <button type="button" class="toast-close" onclick="closeOffcanvas('historyTimelineModal')">&times;</button>
        </div>
        <div class="offcanvas-body" style="padding: 1.5rem; flex: 1; overflow-y: auto;">
            
            <div style="background: var(--hover-bg); padding: 1rem; border-radius: 0.75rem; border: 1px solid var(--border-color); margin-bottom: 1.5rem;">
                <div style="font-weight: 700; font-size: 1.15rem; color: var(--text-primary);" id="history_person_name">Person Name</div>
                <div style="font-size: 0.85rem; color: var(--text-secondary);" id="history_person_contact">Contact Details</div>
            </div>

            <h4 style="font-size: 0.9375rem; font-weight: 700; color: var(--primary-color); margin-bottom: 1rem;">Chronological Occupancy Trajectory</h4>

            <div id="history_timeline_container">
                <!-- Timeline items populated via JS -->
            </div>

        </div>
        <div class="offcanvas-footer" style="padding: 1.25rem 1.75rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; background: var(--hover-bg);">
            <button type="button" class="btn btn-secondary" onclick="closeOffcanvas('historyTimelineModal')">Close</button>
        </div>
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

function openEditResidentModal(residentId) {
    fetch(`<?= BASE_URL ?>ajax/get_resident_details.php?id=${residentId}`)
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                alert(res.message || 'Error fetching resident details');
                return;
            }
            const r = res.data.resident;
            const hasUser = res.data.has_user_account;
            const user = res.data.user_account;

            document.getElementById('edit_resident_id').value = r.id;
            document.getElementById('edit_resident_name').innerText = `${r.first_name} ${r.last_name} (${r.resident_code})`;
            document.getElementById('edit_resident_unit').innerText = `${r.building_name} - Unit ${r.unit_number} (${r.unit_code})`;
            document.getElementById('edit_resident_email').innerText = `Email: ${r.email || 'None'}`;

            document.getElementById('edit_residency_category').value = r.residency_category;
            document.getElementById('edit_is_head_of_household').checked = r.is_head_of_household == 1;
            document.getElementById('edit_move_in_date').value = r.move_in_date;

            const statusBox = document.getElementById('user_account_status_box');
            const resetPassWrapper = document.getElementById('reset_pass_wrapper');
            const provisionCheckbox = document.getElementById('edit_provision_user');

            if (hasUser && user) {
                statusBox.style.background = '#dcfce7';
                statusBox.style.borderColor = '#86efac';
                statusBox.innerHTML = `<strong><i data-lucide="check-circle" style="width: 14px; height: 14px;"></i> User Account Active</strong><br><small>Email: ${user.email} | Role: ${user.role} | Created: ${user.created_at}</small>`;
                resetPassWrapper.style.display = 'block';
                provisionCheckbox.checked = true;
            } else {
                statusBox.style.background = '#fef3c7';
                statusBox.style.borderColor = '#fde047';
                statusBox.innerHTML = `<strong><i data-lucide="alert-circle" style="width: 14px; height: 14px;"></i> No User Login Account Assigned</strong><br><small>Check box below to provision user access.</small>`;
                resetPassWrapper.style.display = 'none';
                provisionCheckbox.checked = false;
            }

            openOffcanvas('editResidentModal');
            if (window.lucide) window.lucide.createIcons();
        });
}

function openRelocateModal(residentId, residentName, unitCode) {
    document.getElementById('relocate_resident_id').value = residentId;
    document.getElementById('relocate_resident_name').innerText = residentName;
    document.getElementById('relocate_current_unit').innerText = unitCode;
    openOffcanvas('relocateResidentModal');
}

function openHistoryModal(personId) {
    fetch(`<?= BASE_URL ?>ajax/get_resident_history.php?person_id=${personId}`)
        .then(r => r.json())
        .then(res => {
            if (!res.success) {
                alert(res.message || 'Error fetching tenancy history');
                return;
            }
            const person = res.data.person;
            const history = res.data.history;

            document.getElementById('history_person_name').innerText = `${person.first_name} ${person.last_name} (${person.person_code})`;
            document.getElementById('history_person_contact').innerText = `Phone: ${person.primary_phone} | Email: ${person.email || 'None'}`;

            const container = document.getElementById('history_timeline_container');
            let html = '';

            if (history && history.length > 0) {
                history.forEach((item, index) => {
                    const isActive = item.status === 'active';
                    const badgeClass = isActive ? 'badge-success' : 'badge-secondary';
                    const statusText = isActive ? 'CURRENT ACTIVE OCCUPANCY' : 'PREVIOUS OCCUPANCY';
                    const headBadge = item.is_head_of_household == 1 ? '<span class="badge badge-success" style="font-size:0.7rem; margin-left:0.5rem;"><i data-lucide="crown" style="width:10px;height:10px;"></i> Head of Household</span>' : '';
                    
                    html += `
                    <div style="position: relative; padding-left: 1.75rem; margin-bottom: 1.5rem; border-left: 2px solid ${isActive ? 'var(--primary-color)' : '#cbd5e1'};">
                        <div style="position: absolute; left: -7px; top: 0; width: 12px; height: 12px; border-radius: 50%; background: ${isActive ? 'var(--primary-color)' : '#94a3b8'}; border: 2px solid white;"></div>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.25rem;">
                            <strong style="font-size: 1rem; color: var(--text-primary);">${item.building_name} - Unit ${item.unit_number} (${item.unit_code})</strong>
                            <span class="badge ${badgeClass}">${statusText}</span>
                        </div>
                        <div style="font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 0.35rem;">
                            Category: <strong style="text-transform: capitalize;">${item.residency_category.replace('_', ' ')}</strong> ${headBadge}
                        </div>
                        <div style="font-size: 0.8125rem; color: var(--text-muted); background: var(--hover-bg); padding: 0.5rem 0.75rem; border-radius: 0.5rem; display: inline-block;">
                            <i data-lucide="calendar" style="width: 12px; height: 12px; vertical-align: middle;"></i>
                            Move-In: <strong>${item.move_in_date}</strong> 
                            ${item.actual_move_out_date ? ` &bull; Move-Out: <strong>${item.actual_move_out_date}</strong>` : ' &bull; <strong>Present</strong>'}
                            &bull; Duration: <strong>${item.duration_formatted}</strong>
                        </div>
                    </div>`;
                });
            } else {
                html = '<div style="text-align: center; color: var(--text-secondary); padding: 1.5rem;">No historical movement records found.</div>';
            }

            container.innerHTML = html;
            openOffcanvas('historyTimelineModal');
            if (window.lucide) window.lucide.createIcons();
        });
}

function toggleTableDropdown(e, btn) {
    e.stopPropagation();
    const dropdown = btn.closest('.table-dropdown');
    document.querySelectorAll('.table-dropdown.open').forEach(d => {
        if (d !== dropdown) d.classList.remove('open');
    });
    dropdown.classList.toggle('open');
}

document.addEventListener('click', function() {
    document.querySelectorAll('.table-dropdown.open').forEach(d => d.classList.remove('open'));
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
