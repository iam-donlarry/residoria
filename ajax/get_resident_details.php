<?php
// ajax/get_resident_details.php - Fetch Resident Profile & User Account Status
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/helpers.php';

requirePermission('residents.view');

$resident_id = intval($_GET['id'] ?? 0);

if ($resident_id <= 0) {
    jsonResponse(false, "Invalid resident ID.", [], 200);
}

$db = Database::getInstance();
$resident = $db->fetchOne("
    SELECT r.*, p.first_name, p.last_name, p.email, p.primary_phone, p.gender,
           u.unit_code, u.unit_number, b.name as building_name
    FROM residents r
    JOIN persons p ON r.person_id = p.id
    JOIN property_units u ON r.unit_id = u.id
    JOIN buildings_blocks b ON u.building_id = b.id
    WHERE r.id = ?
", [$resident_id]);

if (!$resident) {
    jsonResponse(false, "Resident record not found.", [], 200);
}

// Check for existing User Login Account
$userAccount = $db->fetchOne("SELECT id, name, email, role, status, created_at FROM users WHERE person_id = ?", [$resident['person_id']]);

jsonResponse(true, "Resident details fetched successfully.", [
    'resident' => $resident,
    'has_user_account' => !empty($userAccount),
    'user_account' => $userAccount
]);
