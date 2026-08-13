<?php
// modules/users/index.php - System Users & Portal Accounts Manager
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/helpers.php';

requirePermission('settings.manage');

$db = Database::getInstance();
$message = "";
$error = "";

// 1. SAVE / EDIT USER ACCOUNT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_user'])) {
    try {
        verifyCsrfToken();
        $user_id = intval($_POST['user_id'] ?? 0);
        $person_id = !empty($_POST['person_id']) ? intval($_POST['person_id']) : null;
        $name = sanitize($_POST['name']);
        $email = sanitize($_POST['email']);
        $password = $_POST['password'] ?? '';
        $role_id = intval($_POST['role_id']);
        $status = sanitize($_POST['status'] ?? 'active');

        if (empty($name) || empty($email) || $role_id <= 0) {
            $error = "Full name, login email, and assigned system role are required.";
        } else {
            $roleObj = $db->fetchOne("SELECT name FROM roles WHERE id = ?", [$role_id]);
            $roleSlug = strtolower(str_replace(' ', '_', $roleObj['name'] ?? 'resident'));

            if ($user_id > 0) {
                // Update User Account
                if (!empty($password)) {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $db->query("UPDATE users SET person_id = ?, name = ?, email = ?, password = ?, role = ?, status = ? WHERE id = ?", [
                        $person_id, $name, $email, $hash, $roleSlug, $status, $user_id
                    ]);
                } else {
                    $db->query("UPDATE users SET person_id = ?, name = ?, email = ?, role = ?, status = ? WHERE id = ?", [
                        $person_id, $name, $email, $roleSlug, $status, $user_id
                    ]);
                }

                // Update Role in user_roles
                $db->query("DELETE FROM user_roles WHERE user_id = ?", [$user_id]);
                $db->query("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)", [$user_id, $role_id]);

                $message = "User login account updated successfully!";
                logAudit($conn, "Update User", "Users", "Updated user account #$user_id ($email)");
            } else {
                // Create New User Account
                $checkEmail = $db->fetchOne("SELECT id FROM users WHERE email = ?", [$email]);
                if ($checkEmail) {
                    $error = "A user account with this email address ($email) already exists.";
                } else {
                    $pass = !empty($password) ? password_hash($password, PASSWORD_DEFAULT) : password_hash('estate123', PASSWORD_DEFAULT);
                    $db->query("INSERT INTO users (person_id, name, email, password, role, status) VALUES (?, ?, ?, ?, ?, ?)", [
                        $person_id, $name, $email, $pass, $roleSlug, $status
                    ]);
                    $newUserId = intval($db->lastInsertId());

                    $db->query("INSERT INTO user_roles (user_id, role_id) VALUES (?, ?)", [$newUserId, $role_id]);

                    $message = "User account created successfully for <strong>" . e($email) . "</strong>!";
                    logAudit($conn, "Create User", "Users", "Created user account #$newUserId ($email)");
                }
            }
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// 2. TOGGLE SUSPEND / ACTIVATE STATUS
if (isset($_GET['action']) && $_GET['action'] === 'toggle_status' && !empty($_GET['id'])) {
    try {
        verifyCsrfToken($_GET['csrf'] ?? '');
        $uId = intval($_GET['id']);
        $usr = $db->fetchOne("SELECT status, email FROM users WHERE id = ?", [$uId]);
        if ($usr) {
            $newStatus = ($usr['status'] === 'active') ? 'suspended' : 'active';
            $db->query("UPDATE users SET status = ? WHERE id = ?", [$newStatus, $uId]);
            $message = "User account status updated to <strong>" . strtoupper($newStatus) . "</strong>.";
            logAudit($conn, "Toggle User Status", "Users", "Changed status of user #$uId to $newStatus");
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch All System Users
$users = $db->fetchAll("
    SELECT u.*, p.person_code, p.primary_phone, r.name as role_name, r.id as role_id
    FROM users u
    LEFT JOIN persons p ON u.person_id = p.id
    LEFT JOIN user_roles ur ON u.id = ur.user_id
    LEFT JOIN roles r ON ur.role_id = r.id
    ORDER BY u.created_at DESC
");

$persons = $db->fetchAll("SELECT id, person_code, first_name, last_name, primary_phone, email FROM persons ORDER BY first_name ASC");
$roles = $db->fetchAll("SELECT id, name FROM roles ORDER BY id ASC");

$pageTitle = "System Users & Portal Accounts Manager";
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="content-wrapper">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h1 class="page-title" style="margin: 0; font-size: 1.75rem; font-weight: 700;">System Users & Portal Accounts</h1>
            <p class="text-subtitle" style="color: var(--text-secondary); margin-top: 0.25rem;">
                Manage login credentials, system roles, portal access privileges, and user account statuses.
            </p>
        </div>
        <div>
            <button type="button" class="btn btn-primary" onclick="openCreateUserDrawer()">
                <i data-lucide="user-plus" style="width: 16px; height: 16px;"></i> + Create User Account
            </button>
        </div>
    </div>

    <?php if (!empty($message)): ?>
        <script>document.addEventListener('DOMContentLoaded', function() { showToast(<?= json_encode(strip_tags($message)) ?>, 'success'); });</script>
    <?php endif; ?>
    <?php if (!empty($error)): ?>
        <script>document.addEventListener('DOMContentLoaded', function() { showToast(<?= json_encode(strip_tags($error)) ?>, 'error'); });</script>
    <?php endif; ?>

    <!-- User Accounts Table -->
    <div class="card" style="background: white; border-radius: 1rem; border: 1px solid var(--border-color); padding: 1.5rem;">
        <div class="table-responsive">
            <table class="data-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th style="width: 70px;">ID</th>
                        <th>User Name</th>
                        <th>Login Email</th>
                        <th>Linked Person Profile</th>
                        <th>Assigned Role</th>
                        <th>Account Status</th>
                        <th>Created Date</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($users)): ?>
                        <?php foreach ($users as $u): ?>
                        <tr>
                            <td><code>#<?= $u['id'] ?></code></td>
                            <td><strong><?= e($u['name']) ?></strong></td>
                            <td><code><?= e($u['email']) ?></code></td>
                            <td>
                                <?php if (!empty($u['person_code'])): ?>
                                <strong><code><?= e($u['person_code']) ?></code></strong><br>
                                <small class="text-muted"><?= e($u['primary_phone']) ?></small>
                                <?php else: ?>
                                <span class="badge badge-secondary">Unlinked</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-info" style="font-size: 0.8125rem;">
                                    <i data-lucide="shield-check" style="width: 12px; height: 12px; vertical-align: middle;"></i> <?= e($u['role_name'] ?: ucfirst($u['role'])) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?= $u['status'] === 'active' ? 'success' : ($u['status'] === 'suspended' ? 'danger' : 'secondary') ?>">
                                    <?= strtoupper(e($u['status'])) ?>
                                </span>
                            </td>
                            <td><?= formatDate($u['created_at']) ?></td>
                            <td style="text-align: right;">
                                <div style="display: flex; gap: 0.35rem; justify-content: flex-end;">
                                    <button type="button" class="btn btn-sm btn-outline" onclick="openEditUserDrawer(<?= htmlspecialchars(json_encode($u), ENT_QUOTES, 'UTF-8') ?>)" title="Edit Account">
                                        <i data-lucide="edit" style="width: 14px; height: 14px;"></i> Edit
                                    </button>
                                    <a href="<?= BASE_URL ?>users?action=toggle_status&id=<?= $u['id'] ?>&csrf=<?= generateCsrfToken() ?>" 
                                       class="btn btn-sm btn-<?= $u['status'] === 'active' ? 'warning' : 'success' ?>" 
                                       onclick="return confirm('Change status for user <?= e($u['email']) ?>?')"
                                       title="Toggle Status">
                                        <i data-lucide="<?= $u['status'] === 'active' ? 'user-x' : 'user-check' ?>" style="width: 14px; height: 14px;"></i>
                                        <?= $u['status'] === 'active' ? 'Suspend' : 'Activate' ?>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="8" class="empty-state">No system users configured.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- USER ACCOUNT PROVISIONING OFFCANVAS PANEL -->
<div id="userDrawer" class="offcanvas-overlay">
    <div class="offcanvas-panel">
        <div class="offcanvas-header">
            <h3 id="userDrawerTitle" style="margin: 0; font-weight: 700; font-size: 1.25rem;">Create User Login Account</h3>
            <button type="button" class="toast-close" onclick="closeOffcanvas('userDrawer')">&times;</button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="user_id" id="formUserId" value="0">
            
            <div class="offcanvas-body" style="padding: 1.5rem; flex: 1; overflow-y: auto;">
                
                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label class="form-label">Link Person Profile (Search directory)</label>
                    <select name="person_id" id="formPersonId" class="form-control" onchange="syncPersonDetails(this)">
                        <option value="">-- Standalone User (No Linked Person Profile) --</option>
                        <?php foreach ($persons as $p): ?>
                        <option value="<?= $p['id'] ?>" data-name="<?= e($p['first_name'] . ' ' . $p['last_name']) ?>" data-email="<?= e($p['email'] ?? '') ?>">
                            <?= e($p['first_name']) ?> <?= e($p['last_name']) ?> (<?= e($p['primary_phone']) ?>) [<?= e($p['person_code']) ?>]
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Full Name *</label>
                    <input type="text" name="name" id="formName" class="form-control" placeholder="e.g. Chief O. Adebayo" required>
                </div>

                <div class="form-group" style="margin-bottom: 1rem;">
                    <label class="form-label">Login Email Address *</label>
                    <input type="email" name="email" id="formEmail" class="form-control" placeholder="user@residoria.com" required>
                </div>

                <div class="form-group" style="margin-bottom: 1.25rem;">
                    <label class="form-label">Password <small id="passHelpText" class="text-muted">(Leave blank to keep existing password)</small></label>
                    <input type="password" name="password" id="formPassword" class="form-control" placeholder="••••••••">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                    <div class="form-group">
                        <label class="form-label">Assigned System Role *</label>
                        <select name="role_id" id="formRoleId" class="form-control" required>
                            <option value="">-- Choose Role --</option>
                            <?php foreach ($roles as $r): ?>
                            <option value="<?= $r['id'] ?>"><?= e($r['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Account Status</label>
                        <select name="status" id="formStatus" class="form-control">
                            <option value="active">Active</option>
                            <option value="suspended">Suspended</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>

            </div>
            <div class="offcanvas-footer" style="padding: 1.25rem 1.75rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 0.75rem; background: var(--hover-bg);">
                <button type="button" class="btn btn-secondary" onclick="closeOffcanvas('userDrawer')">Cancel</button>
                <button type="submit" name="save_user" class="btn btn-primary">
                    <i data-lucide="check" style="width: 16px; height: 16px;"></i> Save User Account
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openCreateUserDrawer() {
    document.getElementById('userDrawerTitle').innerText = "Create User Login Account";
    document.getElementById('formUserId').value = 0;
    document.getElementById('formPersonId').value = "";
    document.getElementById('formName').value = "";
    document.getElementById('formEmail').value = "";
    document.getElementById('formPassword').value = "";
    document.getElementById('formPassword').required = true;
    document.getElementById('passHelpText').style.display = 'none';
    document.getElementById('formRoleId').value = "5"; // Default Resident role
    document.getElementById('formStatus').value = "active";

    openOffcanvas('userDrawer');
}

function openEditUserDrawer(u) {
    document.getElementById('userDrawerTitle').innerText = "Edit User Account: #" + u.id;
    document.getElementById('formUserId').value = u.id;
    document.getElementById('formPersonId').value = u.person_id || "";
    document.getElementById('formName').value = u.name;
    document.getElementById('formEmail').value = u.email;
    document.getElementById('formPassword').value = "";
    document.getElementById('formPassword').required = false;
    document.getElementById('passHelpText').style.display = 'inline';
    document.getElementById('formRoleId').value = u.role_id || "";
    document.getElementById('formStatus').value = u.status;

    openOffcanvas('userDrawer');
}

function syncPersonDetails(sel) {
    const opt = sel.options[sel.selectedIndex];
    const name = opt.getAttribute('data-name');
    const email = opt.getAttribute('data-email');
    if (name && !document.getElementById('formName').value) {
        document.getElementById('formName').value = name;
    }
    if (email && !document.getElementById('formEmail').value) {
        document.getElementById('formEmail').value = email;
    }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
