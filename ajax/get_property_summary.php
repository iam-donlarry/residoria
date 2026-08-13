<?php
// ajax/get_property_summary.php - Property Summary AJAX Endpoint
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/helpers.php';

requirePermission('properties.view');

$unit_id = intval($_GET['id'] ?? 0);

if ($unit_id <= 0) {
    jsonResponse(false, "Invalid property unit ID.", [], 200);
}

$db = Database::getInstance();
$unit = $db->fetchOne("
    SELECT u.*, b.name as building_name, z.name as street_name
    FROM property_units u
    JOIN buildings_blocks b ON u.building_id = b.id
    JOIN zones_streets z ON b.zone_id = z.id
    WHERE u.id = ?
", [$unit_id]);

if (!$unit) {
    jsonResponse(false, "Property unit not found.", [], 200);
}

$occ = calculateUnitOccupancy($unit_id);

$head = $db->fetchOne("
    SELECT CONCAT(p.first_name, ' ', p.last_name) as name, p.primary_phone
    FROM residents r
    JOIN persons p ON r.person_id = p.id
    WHERE r.unit_id = ? AND r.status = 'active' AND r.is_head_of_household = 1
    LIMIT 1
", [$unit_id]);

$vehCount = $db->fetchOne("SELECT COUNT(*) as cnt FROM vehicles WHERE unit_id = ? AND access_status = 'active'", [$unit_id])['cnt'] ?? 0;

$arrears = $db->fetchOne("
    SELECT SUM(net_amount) as total 
    FROM invoices 
    WHERE unit_id = ? AND status IN ('unpaid', 'partial')
", [$unit_id])['total'] ?? 0.00;

jsonResponse(true, "Property summary fetched.", [
    'unit_id' => $unit['id'],
    'unit_code' => $unit['unit_code'],
    'unit_number' => $unit['unit_number'],
    'status' => $unit['status'],
    'street_name' => $unit['street_name'],
    'building_name' => $unit['building_name'],
    'floor_number' => $unit['floor_number'],
    'head_name' => $head['name'] ?? null,
    'head_phone' => $head['primary_phone'] ?? null,
    'permanent_occupancy' => $occ['permanent_occupancy'],
    'daily_staff_count' => $occ['daily_staff_count'],
    'vehicles_count' => (int)$vehCount,
    'arrears' => (float)$arrears,
    'formatted_arrears' => formatMoney($arrears)
]);
