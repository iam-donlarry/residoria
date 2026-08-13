<?php
// ajax/universal_search.php - Global Header Universal Search Endpoint
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../includes/helpers.php';

$query = trim(sanitize($_GET['q'] ?? ''));

if (strlen($query) < 2) {
    jsonResponse(true, "Query too short", ['persons' => [], 'properties' => [], 'vehicles' => [], 'visitors' => []]);
}

$db = Database::getInstance();
$param = '%' . $query . '%';

// 1. Search Persons
$persons = $db->fetchAll("
    SELECT id, person_code, CONCAT(first_name, ' ', last_name) as name, primary_phone, email 
    FROM persons 
    WHERE first_name LIKE ? OR last_name LIKE ? OR primary_phone LIKE ? OR email LIKE ? OR person_code LIKE ?
    LIMIT 5
", [$param, $param, $param, $param, $param]);

// 2. Search Properties
$properties = $db->fetchAll("
    SELECT u.id, u.unit_code, u.unit_number, u.status, b.name as building_name, z.name as street_name
    FROM property_units u
    JOIN buildings_blocks b ON u.building_id = b.id
    JOIN zones_streets z ON b.zone_id = z.id
    WHERE u.unit_code LIKE ? OR u.unit_number LIKE ? OR b.name LIKE ? OR z.name LIKE ?
    LIMIT 5
", [$param, $param, $param, $param]);

// 3. Search Vehicles
$vehicles = $db->fetchAll("
    SELECT v.id, v.unit_id, v.license_plate_number, v.vehicle_code, v.make, CONCAT(p.first_name, ' ', p.last_name) as owner_name
    FROM vehicles v
    JOIN persons p ON v.person_id = p.id
    WHERE v.license_plate_number LIKE ? OR v.vehicle_code LIKE ? OR v.decal_number LIKE ?
    LIMIT 5
", [$param, $param, $param]);

// 4. Search Visitor Passes
$visitors = $db->fetchAll("
    SELECT vp.id, vp.unit_id, vp.access_code, vp.pass_code_formatted, vp.visitor_name, vp.pass_status
    FROM visitor_passes vp
    WHERE vp.access_code LIKE ? OR vp.pass_code_formatted LIKE ? OR vp.visitor_name LIKE ? OR vp.visitor_phone LIKE ?
    LIMIT 5
", [$param, $param, $param, $param]);

jsonResponse(true, "Search completed", [
    'persons' => $persons,
    'properties' => $properties,
    'vehicles' => $vehicles,
    'visitors' => $visitors
]);
