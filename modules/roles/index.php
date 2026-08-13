<?php
// modules/roles/index.php - Role-Based Access Control (RBAC) Management
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/helpers.php';

requirePermission('roles.manage');

$db = Database::getInstance();
$message = "";
$error = "";

// Handle Saving Role Permissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_role_permissions'])) {
    try {
        verifyCsrfToken();
        $role_id = intval($_POST['role_id']);
        $assigned_perm_ids = $_POST['permission_ids'] ?? [];

        if ($role_id <= 0) {
            $error = "Invalid role selected.";
        } else {
            // Clear existing permissions for role
            $db->query("DELETE FROM role_permissions WHERE role_id = ?", [$role_id]);

            // Re-insert assigned permissions
            if (!empty($assigned_perm_ids)) {
                $stmt = $db->getPdo()->prepare("INSERT INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
                foreach ($assigned_perm_ids as $pId) {
                    $stmt->execute([$role_id, intval($pId)]);
                }
            }

            $message = "Role permissions updated successfully!";
            logAudit($conn, "Update Role Permissions", "Roles", "Updated permissions for Role ID #$role_id");
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch System Roles with user counts and permission counts
$roles = $db->fetchAll("
    SELECT r.*, 
           (SELECT COUNT(*) FROM role_permissions WHERE role_id = r.id) as perm_count,
           (SELECT COUNT(*) FROM user_roles WHERE role_id = r.id) as user_count
    FROM roles r 
    ORDER BY r.id ASC
");

// Fetch All System Permissions
$permissions = $db->fetchAll("SELECT * FROM permissions ORDER BY module, code ASC");

// Group permissions by module
$groupedPerms = [];
foreach ($permissions as $p) {
    $groupedPerms[$p['module']][] = $p;
}

// Map each role to its assigned permission IDs for client-side offcanvas drawer JS
$rolePermMap = [];
foreach ($roles as $r) {
    $assigned = $db->fetchAll("SELECT permission_id FROM role_permissions WHERE role_id = ?", [$r['id']]);
    $rolePermMap[$r['id']] = array_map('intval', array_column($assigned, 'permission_id'));
}

$pageTitle = "RBAC System Roles & Permissions Matrix";
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="content-wrapper">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h1 class="page-title" style="margin: 0; font-size: 1.75rem; font-weight: 700;">RBAC System Roles & Permissions</h1>
            <p class="text-subtitle" style="color: var(--text-secondary); margin-top: 0.25rem;">
                Manage access control policies, security levels, and module permission matrices for estate roles.
            </p>
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

    <!-- Roles Table -->
    <div class="card" style="background: white; border-radius: 1rem; border: 1px solid var(--border-color); padding: 1.5rem;">
        <div class="table-responsive">
            <table class="data-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th style="width: 80px;">Role ID</th>
                        <th>Role Name</th>
                        <th>Description</th>
                        <th>Active Users</th>
                        <th>Granted Permissions</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($roles)): ?>
                        <?php foreach ($roles as $r): ?>
                        <tr>
                            <td><code>#<?= $r['id'] ?></code></td>
                            <td><strong style="font-size: 1rem; color: var(--primary-color);"><?= e($r['name']) ?></strong></td>
                            <td style="color: var(--text-secondary); font-size: 0.875rem;"><?= e($r['description'] ?: 'No description provided.') ?></td>
                            <td>
                                <span class="badge badge-info" style="font-size: 0.8125rem;">
                                    <i data-lucide="users" style="width: 12px; height: 12px; vertical-align: middle;"></i> <?= $r['user_count'] ?> User(s)
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-success" style="font-size: 0.8125rem;">
                                    <i data-lucide="shield-check" style="width: 12px; height: 12px; vertical-align: middle;"></i> <?= $r['perm_count'] ?> / <?= count($permissions) ?> Permissions
                                </span>
                            </td>
                            <td style="text-align: right;">
                                <button type="button" class="btn btn-sm btn-primary" onclick="openPermissionsDrawer(<?= $r['id'] ?>, '<?= e(addslashes($r['name'])) ?>')" title="Configure Permissions">
                                    <i data-lucide="key" style="width: 14px; height: 14px;"></i> Configure Permissions
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="6" class="empty-state">No roles configured in the system.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- PERMISSIONS MATRIX OFFCANVAS SLIDE-OVER DRAWER -->
<div id="rolePermissionsDrawer" class="offcanvas-overlay">
    <div class="offcanvas-panel">
        <div class="offcanvas-header">
            <div>
                <span class="badge badge-info" id="drawerRoleBadge">ROLE PERMISSIONS</span>
                <h3 id="drawerRoleTitle" style="margin: 0.25rem 0 0 0; font-size: 1.25rem; font-weight: 700; color: var(--text-primary);">Configure Role Permissions</h3>
            </div>
            <button type="button" class="toast-close" onclick="closeOffcanvas('rolePermissionsDrawer')">&times;</button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="role_id" id="drawerRoleId">
            
            <div class="offcanvas-body" style="padding: 1.5rem; flex: 1; overflow-y: auto;">
                <div style="margin-bottom: 1rem; background: var(--hover-bg); padding: 0.75rem 1rem; border-radius: 0.5rem; border-left: 3px solid var(--primary-color);">
                    <small style="color: var(--text-secondary); font-size: 0.8125rem;">
                        Select or uncheck the specific capabilities granted to this system role below. Changes take effect immediately upon saving.
                    </small>
                </div>

                <div style="display: flex; flex-direction: column; gap: 1.25rem;">
                    <?php foreach ($groupedPerms as $module => $perms): ?>
                    <div style="border: 1px solid var(--border-color); border-radius: 0.75rem; padding: 1rem; background: white;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
                            <h4 style="font-size: 0.875rem; font-weight: 700; text-transform: uppercase; color: var(--primary-color); letter-spacing: 0.5px; margin: 0;">
                                Module: <?= e($module) ?>
                            </h4>
                            <button type="button" class="btn btn-sm btn-outline" onclick="toggleModuleCheckboxes('module-group-<?= e($module) ?>')" style="padding: 0.2rem 0.5rem; font-size: 0.75rem;">
                                Select All / Toggle
                            </button>
                        </div>
                        <div id="module-group-<?= e($module) ?>" style="display: grid; grid-template-columns: 1fr; gap: 0.6rem;">
                            <?php foreach ($perms as $p): ?>
                            <label style="display: flex; align-items: flex-start; gap: 0.6rem; font-size: 0.85rem; cursor: pointer; padding: 0.35rem 0.5rem; border-radius: 0.5rem;" onmouseover="this.style.background='var(--hover-bg)'" onmouseout="this.style.background='transparent'">
                                <input type="checkbox" name="permission_ids[]" value="<?= $p['id'] ?>" class="perm-checkbox" id="perm-check-<?= $p['id'] ?>" style="margin-top: 0.2rem; accent-color: var(--primary-color);">
                                <div>
                                    <strong style="color: var(--text-primary); font-family: monospace; font-size: 0.8125rem;"><?= e($p['code']) ?></strong>
                                    <div style="color: var(--text-secondary); font-size: 0.775rem; margin-top: 0.1rem;"><?= e($p['description']) ?></div>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="offcanvas-footer" style="padding: 1.25rem 1.75rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; gap: 0.75rem; background: var(--hover-bg);">
                <button type="button" class="btn btn-secondary" onclick="closeOffcanvas('rolePermissionsDrawer')">Cancel</button>
                <button type="submit" name="save_role_permissions" class="btn btn-primary">
                    <i data-lucide="check-circle" style="width: 16px; height: 16px;"></i> Save Role Permissions
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const rolePermMap = <?= json_encode($rolePermMap) ?>;

function openPermissionsDrawer(roleId, roleName) {
    document.getElementById('drawerRoleId').value = roleId;
    document.getElementById('drawerRoleTitle').innerText = "Configure Role: " + roleName;
    document.getElementById('drawerRoleBadge').innerText = "ROLE #" + roleId;

    const assignedIds = rolePermMap[roleId] || [];

    // Reset and set all checkboxes
    document.querySelectorAll('.perm-checkbox').forEach(cb => {
        const val = parseInt(cb.value);
        cb.checked = assignedIds.includes(val);
    });

    openOffcanvas('rolePermissionsDrawer');
}

function toggleModuleCheckboxes(containerId) {
    const container = document.getElementById(containerId);
    if (!container) return;
    const checkboxes = container.querySelectorAll('.perm-checkbox');
    const allChecked = Array.from(checkboxes).every(cb => cb.checked);
    checkboxes.forEach(cb => cb.checked = !allChecked);
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
