<?php
// includes/helpers.php - Core Helper Functions & Security Utilities (Standalone Architecture)

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../classes/Database.php';

/**
 * Escape HTML special characters for XSS prevention
 */
function e($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Sanitize string input
 */
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return trim(strip_tags($input ?? ''));
}

/**
 * Obfuscate integer database ID into a 20-character URL-safe string
 */
function encodeId($id) {
    if (empty($id) || !is_numeric($id)) return $id;
    $id = (int)$id;
    $hex = sprintf('%04x', $id);
    $salt1 = substr(md5('estate_salt1_' . $id), 0, 6);
    $salt2 = substr(md5('estate_salt2_' . $id), 0, 10);
    return $salt1 . $hex . $salt2; // Exactly 20 URL-safe characters
}

/**
 * Decode obfuscated 20-character URL-safe ID back into an integer database ID
 */
function decodeId($str) {
    if (empty($str)) return 0;
    if (is_numeric($str) && intval($str) > 0) return intval($str);
    if (is_string($str) && strlen($str) >= 20) {
        $hex = substr($str, 6, 4);
        return hexdec($hex);
    }
    return 0;
}

/**
 * Generate CSRF token for form security
 */
function generateCsrfToken() {
    if (empty($_SESSION[CSRF_TOKEN_KEY])) {
        $_SESSION[CSRF_TOKEN_KEY] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_KEY];
}

/**
 * CSRF field HTML output
 */
function csrfField() {
    $token = generateCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . e($token) . '">';
}

/**
 * Verify submitted CSRF token
 */
function verifyCsrfToken($token = null) {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    }
    if (empty($_SESSION[CSRF_TOKEN_KEY]) || !hash_equals($_SESSION[CSRF_TOKEN_KEY], $token)) {
        http_response_code(403);
        die("CSRF Token Verification Failed.");
    }
    return true;
}

/**
 * Legacy compatibility stub for get_estate_id (Always returns 1 in standalone mode)
 */
function get_estate_id() {
    return 1;
}

/**
 * Global Audit Logger using PDO prepared statement
 */
function logAudit($conn, $action, $module, $details = "") {
    try {
        $db = Database::getInstance();
        $user_id = $_SESSION['user_id'] ?? null;
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        $sql = "INSERT INTO audit_logs (user_id, action, module, details, ip_address) 
                VALUES (:user_id, :action, :module, :details, :ip)";
        
        $db->query($sql, [
            ':user_id' => $user_id,
            ':action' => $action,
            ':module' => $module,
            ':details' => $details,
            ':ip' => $ip
        ]);
        return true;
    } catch (Exception $e) {
        error_log("Audit Log Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Secure File Upload Handler
 */
function secureUpload($file, $allowedExts = ['jpg', 'jpeg', 'png', 'pdf', 'doc', 'docx'], $maxSize = 5242880) {
    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'No file uploaded or upload error occurred.'];
    }

    if ($file['size'] > $maxSize) {
        return ['success' => false, 'error' => 'File size exceeds maximum limit of 5MB.'];
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowedExts)) {
        return ['success' => false, 'error' => 'Invalid file extension. Only ' . implode(', ', $allowedExts) . ' allowed.'];
    }

    $targetDir = __DIR__ . '/../uploads/';
    if (!is_dir($targetDir)) {
        mkdir($targetDir, 0755, true);
    }

    $filename = time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $targetFile = $targetDir . $filename;
    $relativePath = '../uploads/' . $filename;

    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
        return ['success' => true, 'path' => $relativePath, 'filename' => $filename];
    }

    return ['success' => false, 'error' => 'Failed to save uploaded file.'];
}

/**
 * JSON Response Helper
 */
function jsonResponse($success, $message = "", $data = [], $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

/**
 * Currency Formatter
 */
function formatMoney($amount, $currency = '₦') {
    return $currency . number_format((float)$amount, 2);
}

/**
 * Date Formatter
 */
function formatDate($dateString, $format = 'M j, Y') {
    if (empty($dateString)) return 'N/A';
    return date($format, strtotime($dateString));
}

/**
 * Calculate Property Unit Occupancy Metrics
 */
function calculateUnitOccupancy($unit_id) {
    $db = Database::getInstance();

    // 1. Head of Household (max 1)
    $head = $db->fetchOne("SELECT COUNT(*) as cnt FROM residents WHERE unit_id = ? AND status = 'active' AND is_head_of_household = 1", [$unit_id]);
    $headCount = (int)($head['cnt'] ?? 0);

    // 2. Co-Residents
    $coRes = $db->fetchOne("SELECT COUNT(*) as cnt FROM residents WHERE unit_id = ? AND status = 'active' AND is_head_of_household = 0", [$unit_id]);
    $coResidentCount = (int)($coRes['cnt'] ?? 0);

    // 3. Household Dependents
    $deps = $db->fetchOne("SELECT COUNT(*) as cnt FROM household_members hm JOIN residents r ON hm.primary_resident_id = r.id WHERE (hm.unit_id = ? OR r.unit_id = ?) AND r.status = 'active'", [$unit_id, $unit_id]);
    $dependentCount = (int)($deps['cnt'] ?? 0);

    // Total Household Residents
    $totalHouseholdResidents = $headCount + $coResidentCount + $dependentCount;

    // 4. Live-In Staff
    $liveIn = $db->fetchOne("SELECT COUNT(*) as cnt FROM domestic_staff WHERE unit_id = ? AND access_status = 'active' AND residence_type = 'live_in'", [$unit_id]);
    $liveInStaffCount = (int)($liveIn['cnt'] ?? 0);

    // Total Permanent Physical Occupancy
    $permanentOccupancy = $totalHouseholdResidents + $liveInStaffCount;

    // 5. Daily Staff
    $daily = $db->fetchOne("SELECT COUNT(*) as cnt FROM domestic_staff WHERE unit_id = ? AND access_status = 'active' AND residence_type = 'daily'", [$unit_id]);
    $dailyStaffCount = (int)($daily['cnt'] ?? 0);

    // 6. Commercial Employees
    $comm = $db->fetchOne("SELECT COUNT(*) as cnt FROM commercial_employees ce JOIN commercial_occupants co ON ce.commercial_id = co.id WHERE co.unit_id = ? AND ce.access_status = 'active'", [$unit_id]);
    $commercialStaffCount = (int)($comm['cnt'] ?? 0);

    // 7. Active Visitors Inside
    $vis = $db->fetchOne("SELECT COUNT(*) as cnt FROM gate_logs WHERE unit_id = ? AND exit_timestamp IS NULL AND DATE(entry_timestamp) = CURDATE()", [$unit_id]);
    $visitorsInsideCount = (int)($vis['cnt'] ?? 0);

    return [
        'head_count' => $headCount,
        'co_resident_count' => $coResidentCount,
        'dependent_count' => $dependentCount,
        'total_household_residents' => $totalHouseholdResidents,
        'live_in_staff_count' => $liveInStaffCount,
        'permanent_occupancy' => $permanentOccupancy,
        'daily_staff_count' => $dailyStaffCount,
        'commercial_staff_count' => $commercialStaffCount,
        'visitors_inside_count' => $visitorsInsideCount,
        'daily_active_personnel' => $dailyStaffCount + $commercialStaffCount + $visitorsInsideCount
    ];
}

