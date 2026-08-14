<?php
// ajax/get_resident_history.php - Fetch Resident Tenancy & Relocation Timeline History
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/helpers.php';

requirePermission('residents.view');

$person_id = intval($_GET['person_id'] ?? 0);
$resident_id = intval($_GET['resident_id'] ?? 0);

$db = Database::getInstance();

if ($person_id <= 0 && $resident_id > 0) {
    $person_id = intval($db->fetchOne("SELECT person_id FROM residents WHERE id = ?", [$resident_id])['person_id'] ?? 0);
}

if ($person_id <= 0) {
    jsonResponse(false, "Invalid Person or Resident ID.", [], 200);
}

$person = $db->fetchOne("SELECT id, person_code, first_name, last_name, email, primary_phone FROM persons WHERE id = ?", [$person_id]);

if (!$person) {
    jsonResponse(false, "Person profile not found.", [], 200);
}

$history = $db->fetchAll("
    SELECT r.*, u.unit_code, u.unit_number, b.name as building_name, z.name as zone_name
    FROM residents r
    JOIN property_units u ON r.unit_id = u.id
    JOIN buildings_blocks b ON u.building_id = b.id
    JOIN zones_streets z ON b.zone_id = z.id
    WHERE r.person_id = ?
    ORDER BY r.move_in_date DESC, r.id DESC
", [$person_id]);

// Calculate duration for each tenancy
foreach ($history as &$item) {
    $startDate = new DateTime($item['move_in_date']);
    $endDate = !empty($item['actual_move_out_date']) ? new DateTime($item['actual_move_out_date']) : new DateTime();
    $diff = $startDate->diff($endDate);
    
    $durationStr = "";
    if ($diff->y > 0) $durationStr .= $diff->y . " yr" . ($diff->y > 1 ? "s " : " ");
    if ($diff->m > 0) $durationStr .= $diff->m . " mo" . ($diff->m > 1 ? "s " : " ");
    if ($diff->d > 0 && empty($durationStr)) $durationStr .= $diff->d . " day" . ($diff->d > 1 ? "s" : "");
    if (empty($durationStr)) $durationStr = "1 day";

    $item['duration_formatted'] = trim($durationStr);
}

jsonResponse(true, "Resident history timeline fetched successfully.", [
    'person' => $person,
    'history' => $history
]);
