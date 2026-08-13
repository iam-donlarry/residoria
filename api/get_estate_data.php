<?php
header('Content-Type: application/json');
require_once '../config.php';

// Fetch Streets
$estate_id = get_estate_id();
$streets = [];
$res = $conn->query("SELECT * FROM streets WHERE estate_id = $estate_id ORDER BY id DESC");
while($row = $res->fetch_assoc()) $streets[] = $row;

// Fetch Buildings
$buildings = [];
$res = $conn->query("SELECT * FROM buildings WHERE estate_id = $estate_id ORDER BY id DESC");
while($row = $res->fetch_assoc()) $buildings[] = $row;

// Fetch Flats
$flats = [];
$res = $conn->query("SELECT * FROM flats WHERE estate_id = $estate_id ORDER BY id DESC");
while($row = $res->fetch_assoc()) $flats[] = $row;

echo json_encode(['success' => true, 'data' => [
    'streets' => $streets,
    'buildings' => $buildings,
    'flats' => $flats
]]);

$conn->close();
?>
