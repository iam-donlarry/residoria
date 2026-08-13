<?php
header('Content-Type: application/json');
require_once '../config.php';

$data = json_decode(file_get_contents("php://input"), true);

if(isset($data['name'])) {
    $name = $conn->real_escape_string($data['name']);
    $sql = "INSERT INTO streets (name) VALUES ('$name')";
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
