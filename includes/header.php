<?php
// includes/header.php - Global Top Header & Asset Loader
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/rbac.php';

// Fetch Estate Details & Primary Color setting
$theme_color = PRIMARY_COLOR;
$estate_name = 'Residoria';
$estate_logo = '';

try {
    $db = Database::getInstance();
    $estate_id = get_estate_id();
    $sys_res = $db->fetchAll("SELECT setting_key, setting_value FROM system_settings WHERE estate_id = ?", [$estate_id]);
    $sys = [];
    foreach ($sys_res as $row) {
        $sys[$row['setting_key']] = $row['setting_value'];
    }
    if (!empty($sys['theme_color'])) $theme_color = $sys['theme_color'];
    if (!empty($sys['estate_name'])) $estate_name = $sys['estate_name'];
    if (!empty($sys['estate_logo'])) $estate_logo = $sys['estate_logo'];
} catch (Exception $e) {
    // Fallback defaults
}

$is_sidebar_mini = isset($_COOKIE['sidebar_mini']) && $_COOKIE['sidebar_mini'] === '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($estate_name) ?> - System</title>
    
    <!-- Google Font: Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- Custom CSS via Absolute BASE_URL -->
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/style.css?v=<?= APP_VERSION ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/components.css?v=<?= APP_VERSION ?>">
    
    <style>
        :root {
            --primary: <?= e($theme_color) ?>;
        }
    </style>
    <script>
        // Instant pre-hydration check for sidebar-mini to prevent load flicker
        if (localStorage.getItem('sidebar_mini') === '1') {
            document.documentElement.classList.add('sidebar-mini-prehydrate');
        }
    </script>
</head>
<body class="<?= $is_sidebar_mini ? 'sidebar-mini' : '' ?>">
    <script>
        if (localStorage.getItem('sidebar_mini') === '1') {
            document.body.classList.add('sidebar-mini');
        }
    </script>
    <div class="app-container">
