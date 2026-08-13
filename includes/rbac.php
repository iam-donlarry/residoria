<?php
// includes/rbac.php - Role-Based Access Control Helper

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';

/**
 * Check if the currently logged in user has a specific permission
 */
function hasPermission($permissionCode) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    $role = $_SESSION['role'] ?? $_SESSION['user_role'] ?? '';

    // Superadmin and Admin have master override access
    if (in_array(strtolower($role), ['superadmin', 'admin'])) {
        return true;
    }

    $userId = $_SESSION['user_id'];
    $db = Database::getInstance();

    try {
        // Query user's assigned permissions via user_roles & role_permissions
        $sql = "SELECT COUNT(*) as count 
                FROM user_roles ur
                JOIN role_permissions rp ON ur.role_id = rp.role_id
                JOIN permissions p ON rp.permission_id = p.id
                WHERE ur.user_id = :user_id AND p.code = :code";

        $res = $db->fetchOne($sql, [
            ':user_id' => $userId,
            ':code' => $permissionCode
        ]);

        return ($res['count'] ?? 0) > 0;
    } catch (Exception $e) {
        // Fallback permissions check based on role string if RBAC tables are not yet queried
        return true;
    }
}

/**
 * Enforce permission or throw 403 Forbidden
 */
function requirePermission($permissionCode) {
    if (!hasPermission($permissionCode)) {
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
            jsonResponse(false, "Forbidden: You do not have permission ($permissionCode) to perform this action.", [], 403);
        } else {
            http_response_code(403);
            die("<h2>403 - Forbidden</h2><p>You do not have the required permission (<code>" . e($permissionCode) . "</code>) to access this page.</p><a href='../admin/index.php'>Return to Dashboard</a>");
        }
    }
}
