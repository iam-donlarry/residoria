<?php
// config/database.php - Centralized Database Bootstrap

require_once __DIR__ . '/../classes/Database.php';

$dbInstance = Database::getInstance();
$pdo = $dbInstance->getPdo();
$conn = $dbInstance->getMysqli(); // Legacy bridge
