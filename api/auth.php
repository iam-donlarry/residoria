<?php
header('Content-Type: application/json');
require_once '../config.php';

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data['email']) || !isset($data['password'])) {
    echo json_encode(['success' => false, 'message' => 'Email and password required']);
    exit;
}

$email = $conn->real_escape_string($data['email']);
$password = $data['password'];

$sql = "SELECT id, name, password, role FROM users WHERE email = '$email'";
$result = $conn->query($sql);

if ($result->num_rows === 1) {
    $user = $result->fetch_assoc();
    
    // Verify password (plain text for now based on previous mock, but config has hash, supporting both for migration)
    // IMPORTANT: For production, we should ALWAYS use password_verify. 
    // Since I seeded with hash in setup, I will use verify.
    if (password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'];
        $_SESSION['user_role'] = $user['role'];
        
        echo json_encode([
            'success' => true, 
            'user' => [
                'id' => $user['id'], 
                'name' => $user['name'], 
                'email' => $email, 
                'role' => $user['role']
            ],
            'redirect' => 'admin/index.php' // Redirect to dashboard
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid password']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'User not found']);
}

$conn->close();
?>
