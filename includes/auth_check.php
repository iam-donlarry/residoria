<?php
// includes/auth_check.php - Centralized Route Authentication Guard

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/rbac.php';

// Verify session authentication
if (!isset($_SESSION['user_id'])) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        jsonResponse(false, "Session expired. Please log in again.", ['redirect' => '../index.php'], 401);
    } else {
        header("Location: ../index.php");
        exit;
    }
}

// Session Timeout check
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
    session_unset();
    session_destroy();
    session_start();
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        jsonResponse(false, "Session timed out due to inactivity.", ['redirect' => '../index.php'], 401);
    } else {
        header("Location: ../index.php?timeout=1");
        exit;
    }
}
$_SESSION['last_activity'] = time();

// Standardize Session Keys
if (isset($_SESSION['user_role']) && !isset($_SESSION['role'])) {
    $_SESSION['role'] = $_SESSION['user_role'];
}
if (isset($_SESSION['user_name']) && !isset($_SESSION['name'])) {
    $_SESSION['name'] = $_SESSION['user_name'];
}
