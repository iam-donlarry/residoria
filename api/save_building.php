<?php
header('Content-Type: application/json');
require_once '../config.php';

$data = json_decode(file_get_contents("php://input"), true);

if(isset($data['name']) && isset($data['street_id'])) {
    $street_id = intval($data['street_id']);
    $name = $conn->real_escape_string($data['name']);
    $type = $conn->real_escape_string($data['type']);
    $type_id = $conn->real_escape_string($data['type_id']);
    $total_floors = intval($data['total_floors']);

    $sql = "INSERT INTO buildings (street_id, name, type, type_id, total_floors) VALUES ($street_id, '$name', '$type', '$type_id', $total_floors)";
    
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
