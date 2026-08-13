<?php
// modules/roles/index.php - Role-Based Access Control (RBAC) Management
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth_check.php';

requirePermission('roles.manage');

$db = Database::getInstance();
$message = "";
$error = "";

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

// Fetch Roles & Permissions
$roles = $db->fetchAll("SELECT r.*, (SELECT COUNT(*) FROM role_permissions WHERE role_id = r.id) as perm_count FROM roles r ORDER BY r.id ASC");
$permissions = $db->fetchAll("SELECT * FROM permissions ORDER BY module, code ASC");

// Group permissions by module
$groupedPerms = [];
foreach ($permissions as $p) {
    $groupedPerms[$p['module']][] = $p;
}

$selected_role_id = intval($_GET['role_id'] ?? 1);
$currentRolePerms = $db->fetchAll("SELECT permission_id FROM role_permissions WHERE role_id = ?", [$selected_role_id]);
$currentPermIds = array_column($currentRolePerms, 'permission_id');

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div style="margin-bottom: 1.5rem;">
    <h1 style="font-size: 1.75rem; font-weight: 700; color: var(--text-primary); margin: 0;">RBAC Roles & Permissions</h1>
    <p style="color: var(--text-muted); font-size: 0.95rem; margin: 0.25rem 0 0;">Manage access control policies for estate administrators, managers, finance officers, security, and residents</p>
</div>

<?php if (!empty($message)): ?>
    <script>document.addEventListener('DOMContentLoaded', function() { showToast(<?= json_encode($message) ?>, 'success'); });</script>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <script>document.addEventListener('DOMContentLoaded', function() { showToast(<?= json_encode($error) ?>, 'error'); });</script>
<?php endif; ?>

<div style="display: grid; grid-template-columns: 280px 1fr; gap: 1.5rem;">
    <!-- Roles List Sidebar -->
    <div>
        <div class="card" style="background: var(--card-bg); border-radius: 10px; padding: 1rem; border: 1px solid var(--border-color);">
            <h3 style="font-size: 1rem; font-weight: 700; margin-top: 0; padding-bottom: 0.5rem; border-bottom: 1px solid var(--border-color);">System Roles</h3>
            <div style="display: flex; flex-direction: column; gap: 0.5rem; margin-top: 0.75rem;">
                <?php foreach ($roles as $r): ?>
                    <a href="?role_id=<?= $r['id'] ?>" style="text-decoration: none; padding: 0.75rem 1rem; border-radius: 6px; font-weight: 600; font-size: 0.9rem; display: flex; justify-content: space-between; align-items: center; background: <?= $r['id'] === $selected_role_id ? 'var(--primary-light)' : 'transparent' ?>; color: <?= $r['id'] === $selected_role_id ? 'var(--primary)' : 'var(--text-primary)' ?>; border: 1px solid <?= $r['id'] === $selected_role_id ? 'var(--primary)' : 'transparent' ?>;">
                        <span><?= e($r['name']) ?></span>
                        <span class="badge badge-info" style="font-size: 0.75rem;"><?= $r['perm_count'] ?> perms</span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Permissions Matrix Form -->
    <div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="role_id" value="<?= $selected_role_id ?>">
            
            <div class="card" style="background: var(--card-bg); border-radius: 10px; padding: 1.5rem; border: 1px solid var(--border-color);">
                <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 1rem; margin-bottom: 1rem;">
                    <div>
                        <h3 style="font-size: 1.2rem; font-weight: 700; margin: 0; color: var(--primary);">
                            Configure Role: <?= e(array_values(array_filter($roles, fn($x) => $x['id'] === $selected_role_id))[0]['name'] ?? 'Role') ?>
                        </h3>
                        <div style="color: var(--text-muted); font-size: 0.85rem; margin-top: 0.2rem;">
                            <?= e(array_values(array_filter($roles, fn($x) => $x['id'] === $selected_role_id))[0]['description'] ?? '') ?>
                        </div>
                    </div>
                    <button type="submit" name="save_role_permissions" class="btn btn-primary">Save Role Permissions</button>
                </div>

                <div style="display: flex; flex-direction: column; gap: 1.5rem;">
                    <?php foreach ($groupedPerms as $module => $perms): ?>
                        <div style="border: 1px solid var(--border-color); border-radius: 8px; padding: 1rem; background: var(--bg-body);">
                            <h4 style="font-size: 0.95rem; font-weight: 700; margin: 0 0 0.75rem; text-transform: uppercase; color: var(--primary); letter-spacing: 0.5px;">
                                Module: <?= e($module) ?>
                            </h4>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem;">
                                <?php foreach ($perms as $p): ?>
                                    <label style="display: flex; align-items: flex-start; gap: 0.5rem; font-size: 0.88rem; cursor: pointer;">
                                        <input type="checkbox" name="permission_ids[]" value="<?= $p['id'] ?>" <?= in_array($p['id'], $currentPermIds) ? 'checked' : '' ?> style="margin-top: 0.25rem;">
                                        <div>
                                            <strong style="color: var(--text-primary);"><?= e($p['code']) ?></strong>
                                            <div style="color: var(--text-muted); font-size: 0.8rem;"><?= e($p['description']) ?></div>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
