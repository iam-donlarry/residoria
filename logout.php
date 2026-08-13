<?php
// logout.php - Session Termination & Audit Log
require_once __DIR__ . '/config.php';

if (isset($_SESSION['user_id'])) {
    logAudit($conn, "User Logout", "Auth", "Logged out successfully.");
}

session_unset();
session_destroy();

header("Location: index.php");
exit;
