<?php
// setup_database.php - Standalone Database Migrator & Seed Enforcer (v3.0.0)
require_once __DIR__ . '/config.php';

try {
    $db = Database::getInstance();
    $pdo = $db->getPdo();

    echo "--- INITIALIZING STANDALONE DATABASE SETUP ---\n";

    // Disable Foreign Key Checks during structural migration
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

    // All database tables to drop and rebuild for clean standalone schema
    $allTables = [
        'audit_logs', 'announcements', 'documents', 'maintenance_tickets',
        'payment_allocations', 'payments', 'invoice_items', 'invoices',
        'tariffs', 'gate_logs', 'visitor_passes', 'vehicles', 'estate_staff',
        'domestic_staff', 'tenancy_agreements', 'commercial_occupants',
        'household_members', 'residents', 'property_ownership', 'corporate_owners',
        'property_units', 'buildings_blocks', 'zones_streets', 'user_roles',
        'role_permissions', 'permissions', 'roles', 'users', 'persons',
        'system_settings', 'estates', 'community_chat', 'flats', 'buildings',
        'streets', 'household_staff', 'visitors', 'maintenance_requests',
        'property_owners', 'resident_history'
    ];

    // Force rebuild tables if vehicles table lacks person_id
    $vehCols = [];
    try {
        $vehCols = $pdo->query("DESCRIBE vehicles")->fetchAll(PDO::FETCH_COLUMN);
    } catch (Exception $e) {}

    if (!in_array('person_id', $vehCols)) {
        echo "Rebuilding database structure for v3.0.0 schema...\n";
        foreach ($allTables as $tbl) {
            $pdo->exec("DROP TABLE IF EXISTS `$tbl`");
        }
    }

    // 1. Execute Canonical Schema (v3.0.0)
    $schemaSql = file_get_contents(__DIR__ . '/database/schema.sql');
    if ($schemaSql) {
        $pdo->exec($schemaSql);
        echo "✓ Canonical v3.0.0 schema executed successfully.\n";
    }

    // Re-enable Foreign Key Checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    // 2. Seed Default Permissions
    $permissions = [
        ['properties.view', 'properties', 'View streets, buildings, and units'],
        ['properties.create', 'properties', 'Add new streets, buildings, and units'],
        ['properties.edit', 'properties', 'Edit existing streets, buildings, and units'],
        ['properties.delete', 'properties', 'Delete or archive property structures'],
        
        ['people.view', 'people', 'View centralized person profiles and directory'],
        ['people.create', 'people', 'Register new people and profiles'],
        ['people.edit', 'people', 'Update person identity information'],
        
        ['residents.view', 'residents', 'View resident directory and occupancy'],
        ['residents.create', 'residents', 'Register residents and household members'],
        ['residents.edit', 'residents', 'Update resident occupancy details'],
        ['residents.archive', 'residents', 'Archive moved out residents'],
        
        ['owners.view', 'owners', 'View property title holders'],
        ['owners.manage', 'owners', 'Add and update property owners'],

        ['commercial.view', 'commercial', 'View commercial business occupants'],
        ['commercial.manage', 'commercial', 'Manage commercial business profiles'],

        ['billing.view', 'billing', 'View property billing ledgers and tariffs'],
        ['billing.create', 'billing', 'Generate invoices for residents'],
        ['billing.manage', 'billing', 'Update and cancel billing items'],

        ['payments.view', 'payments', 'View payment records and receipts'],
        ['payments.record', 'payments', 'Record manual payments and allocations'],

        ['maintenance.view', 'maintenance', 'View maintenance tickets'],
        ['maintenance.create', 'maintenance', 'Submit maintenance requests'],
        ['maintenance.manage', 'maintenance', 'Assign staff and update repair tickets'],

        ['security.view', 'security', 'View gate logs and visitor passes'],
        ['security.gate', 'security', 'Operate gate check-in clearance portal'],
        ['security.pass_gen', 'security', 'Generate visitor authorization access codes'],

        ['vehicles.view', 'vehicles', 'View registered vehicles'],
        ['vehicles.manage', 'vehicles', 'Register and update vehicle decals'],

        ['staff.view', 'staff', 'View estate staff directory'],
        ['staff.manage', 'staff', 'Register and assign estate personnel'],

        ['settings.view', 'settings', 'View estate system settings'],
        ['settings.manage', 'settings', 'Configure estate settings and tariffs'],

        ['roles.manage', 'roles', 'Configure roles and assign permissions'],
        ['reports.view', 'reports', 'Access operational and financial reports']
    ];

    $stmtPerm = $pdo->prepare("INSERT INTO permissions (code, module, description) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE description = VALUES(description)");
    foreach ($permissions as $p) {
        $stmtPerm->execute($p);
    }
    echo "✓ Permissions seeded successfully.\n";

    // 3. Seed Default Roles
    $roles = [
        [1, 'Estate Administrator', 'Full access to all estate management modules'],
        [2, 'Property Manager', 'Manage properties, units, owners, and residents'],
        [3, 'Finance Officer', 'Manage billing, tariffs, invoices, payments, and receipts'],
        [4, 'Security Officer', 'Manage gate access, visitor logs, and security passes'],
        [5, 'Resident', 'View personal dashboard, pay invoices, report maintenance issues']
    ];

    $stmtRole = $pdo->prepare("INSERT INTO roles (id, name, description) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description)");
    foreach ($roles as $r) {
        $stmtRole->execute($r);
    }
    echo "✓ Roles seeded successfully.\n";

    // 4. Map Role Permissions
    $permMap = [];
    $allPerms = $pdo->query("SELECT id, code FROM permissions")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($allPerms as $ap) {
        $permMap[$ap['code']] = $ap['id'];
    }

    $rolePermissionMap = [
        // Role #1: Estate Administrator (All permissions)
        1 => array_values($permMap),
        
        // Role #2: Property Manager
        2 => array_filter(array_map(fn($c) => $permMap[$c] ?? null, [
            'properties.view', 'properties.create', 'properties.edit',
            'people.view', 'people.create', 'people.edit',
            'residents.view', 'residents.create', 'residents.edit',
            'owners.view', 'owners.manage',
            'commercial.view', 'commercial.manage',
            'maintenance.view', 'maintenance.manage',
            'vehicles.view', 'security.view', 'reports.view'
        ])),

        // Role #3: Finance Officer
        3 => array_filter(array_map(fn($c) => $permMap[$c] ?? null, [
            'billing.view', 'billing.create', 'billing.manage',
            'payments.view', 'payments.record',
            'residents.view', 'owners.view', 'commercial.view', 'reports.view'
        ])),

        // Role #4: Security Officer
        4 => array_filter(array_map(fn($c) => $permMap[$c] ?? null, [
            'security.view', 'security.gate',
            'residents.view', 'vehicles.view', 'maintenance.view', 'maintenance.create'
        ])),

        // Role #5: Resident
        5 => array_filter(array_map(fn($c) => $permMap[$c] ?? null, [
            'security.pass_gen', 'maintenance.create', 'maintenance.view',
            'billing.view', 'payments.view'
        ]))
    ];

    $stmtRP = $pdo->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
    foreach ($rolePermissionMap as $roleId => $pIds) {
        foreach ($pIds as $pId) {
            if ($pId) {
                $stmtRP->execute([$roleId, $pId]);
            }
        }
    }
    echo "✓ Role Permissions mapped successfully.\n";

    // 5. Create Default Admin Person & User Account
    $adminEmail = 'admin@estate.com';
    $checkAdmin = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $checkAdmin->execute([$adminEmail]);
    $adminUser = $checkAdmin->fetch(PDO::FETCH_ASSOC);

    if (!$adminUser) {
        // Create Admin Person Profile
        $checkPerson = $pdo->prepare("SELECT id FROM persons WHERE person_code = 'PRS-000001'");
        $checkPerson->execute();
        $pRow = $checkPerson->fetch(PDO::FETCH_ASSOC);

        if (!$pRow) {
            $stmtP = $pdo->prepare("INSERT INTO persons (person_code, first_name, last_name, email, primary_phone) VALUES (?, ?, ?, ?, ?)");
            $stmtP->execute(['PRS-000001', 'System', 'Admin', $adminEmail, '+234 800 000 0001']);
            $personId = $pdo->lastInsertId();
        } else {
            $personId = $pRow['id'];
        }

        // Create Admin User Account
        $adminPass = password_hash('admin123', PASSWORD_DEFAULT);
        $stmtU = $pdo->prepare("INSERT INTO users (person_id, name, email, password, role, status) VALUES (?, ?, ?, ?, 'admin', 'active')");
        $stmtU->execute([$personId, 'Estate Admin', $adminEmail, $adminPass]);
        $userId = $pdo->lastInsertId();

        // Assign Role #1 to User
        $pdo->query("INSERT IGNORE INTO user_roles (user_id, role_id) VALUES ($userId, 1)");
        echo "✓ Default Admin User created: admin@estate.com / admin123\n";
    }

    echo "--- DATABASE SETUP COMPLETE! ---\n";

} catch (Exception $e) {
    die("Database Setup Error: " . $e->getMessage() . "\n");
}
