<?php
header('Content-Type: application/json');
require_once '../config.php';

$data = json_decode(file_get_contents("php://input"), true);

if(isset($data['number']) && isset($data['building_id'])) {
    $building_id = intval($data['building_id']);
    $number = $conn->real_escape_string($data['number']);
    $floor = intval($data['floor']);
    $type = $conn->real_escape_string($data['type']);
    $status = $conn->real_escape_string($data['status']);

    $sql = "INSERT INTO flats (building_id, number, floor, type, status) VALUES ($building_id, '$number', $floor, '$type', '$status')";
    
    if ($conn->query($sql) === TRUE) {
        echo json_encode(['success' => true, 'id' => $conn->insert_id]);
    } else {
        echo json_encode(['success' => false, 'message' => $conn->error]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid Input']);
}
$conn->close();
?>
