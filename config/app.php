<?php
// config/app.php - Main Application & Gateway Config

require_once __DIR__ . '/constants.php';

return [
    'app_name' => APP_NAME,
    'version' => APP_VERSION,
    'brand_color' => PRIMARY_COLOR,
    'font' => DEFAULT_FONT,
    'currency' => '₦',
    'timezone' => 'Africa/Lagos',
    'upload_dir' => __DIR__ . '/../uploads/',
    'max_upload_size' => 5 * 1024 * 1024, // 5MB
    'allowed_upload_extensions' => ['jpg', 'jpeg', 'png', 'pdf'],
    
    // Paystack Configuration
    'paystack' => [
        'public_key' => '',
        'secret_key' => '',
        'currency' => 'NGN',
    ]
];
