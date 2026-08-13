<?php
// database/migrations/002_domain_refinements.php - Migration for Estate Management v3.1.0 Domain Refinements

require_once __DIR__ . '/../../config.php';

$db = Database::getInstance();
$pdo = $db->getPdo();

echo "Starting Database Migration 002_domain_refinements...\n";

try {
    $pdo->beginTransaction();

    // 1. Update `residents` table: Add `is_head_of_household`
    $cols = $db->fetchAll("SHOW COLUMNS FROM `residents` LIKE 'is_head_of_household'");
    if (empty($cols)) {
        echo "Adding `is_head_of_household` to `residents`...\n";
        $pdo->exec("ALTER TABLE `residents` ADD COLUMN `is_head_of_household` TINYINT(1) NOT NULL DEFAULT 0 AFTER `residency_category`");
        // Backfill: set is_head_of_household = 1 for active residents
        $pdo->exec("UPDATE `residents` SET `is_head_of_household` = 1 WHERE `status` = 'active'");
    }

    // 2. Update `household_members` table: Add `unit_id`
    $cols = $db->fetchAll("SHOW COLUMNS FROM `household_members` LIKE 'unit_id'");
    if (empty($cols)) {
        echo "Adding `unit_id` to `household_members`...\n";
        $pdo->exec("ALTER TABLE `household_members` ADD COLUMN `unit_id` INT(11) NULL AFTER `primary_resident_id`");
        // Backfill unit_id from residents table
        $pdo->exec("UPDATE `household_members` hm JOIN `residents` r ON hm.primary_resident_id = r.id SET hm.unit_id = r.unit_id");
        $pdo->exec("ALTER TABLE `household_members` MODIFY COLUMN `unit_id` INT(11) NOT NULL");
        $pdo->exec("ALTER TABLE `household_members` ADD CONSTRAINT `fk_hm_unit` FOREIGN KEY (`unit_id`) REFERENCES `property_units` (`id`) ON DELETE CASCADE");
    }

    // 3. Update `domestic_staff` table: Add Guarantor & NIN fields
    $cols = $db->fetchAll("SHOW COLUMNS FROM `domestic_staff` LIKE 'guarantor_name'");
    if (empty($cols)) {
        echo "Adding Guarantor & NIN fields to `domestic_staff`...\n";
        $pdo->exec("ALTER TABLE `domestic_staff` 
            ADD COLUMN `guarantor_name` VARCHAR(150) DEFAULT NULL AFTER `verification_notes`,
            ADD COLUMN `guarantor_phone` VARCHAR(20) DEFAULT NULL AFTER `guarantor_name`,
            ADD COLUMN `guarantor_address` TEXT DEFAULT NULL AFTER `guarantor_phone`,
            ADD COLUMN `nin_number` VARCHAR(50) DEFAULT NULL AFTER `guarantor_address`
        ");
    }

    // 4. Create `commercial_employees` table
    $tables = $db->fetchAll("SHOW TABLES LIKE 'commercial_employees'");
    if (empty($tables)) {
        echo "Creating `commercial_employees` table...\n";
        $pdo->exec("CREATE TABLE IF NOT EXISTS `commercial_employees` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `commercial_id` INT(11) NOT NULL,
            `person_id` INT(11) NOT NULL,
            `job_title` VARCHAR(100) NOT NULL DEFAULT 'Employee',
            `access_status` ENUM('active','suspended') DEFAULT 'active',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP(),
            PRIMARY KEY (`id`),
            KEY `fk_ce_commercial` (`commercial_id`),
            KEY `fk_ce_person` (`person_id`),
            CONSTRAINT `fk_ce_commercial` FOREIGN KEY (`commercial_id`) REFERENCES `commercial_occupants` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_ce_person` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
    }

    // 5. Update `visitor_passes` table: Add pass_type, valid_from/until, max_uses, use_count
    $cols = $db->fetchAll("SHOW COLUMNS FROM `visitor_passes` LIKE 'pass_type'");
    if (empty($cols)) {
        echo "Adding pass control fields to `visitor_passes`...\n";
        $pdo->exec("ALTER TABLE `visitor_passes` 
            ADD COLUMN `pass_type` ENUM('single_entry','multi_entry') DEFAULT 'single_entry' AFTER `visitor_category`,
            ADD COLUMN `valid_from_date` DATE DEFAULT NULL AFTER `visit_date`,
            ADD COLUMN `valid_until_date` DATE DEFAULT NULL AFTER `valid_from_date`,
            ADD COLUMN `max_uses` INT(11) DEFAULT 1 AFTER `expected_visitor_count`,
            ADD COLUMN `use_count` INT(11) DEFAULT 0 AFTER `max_uses`
        ");
        $pdo->exec("UPDATE `visitor_passes` SET `valid_from_date` = `visit_date`, `valid_until_date` = `visit_date` WHERE `valid_from_date` IS NULL");
    }

    // 6. Update `gate_logs` table: Add exit_processing_officer_user_id
    $cols = $db->fetchAll("SHOW COLUMNS FROM `gate_logs` LIKE 'exit_processing_officer_user_id'");
    if (empty($cols)) {
        echo "Adding `exit_processing_officer_user_id` to `gate_logs`...\n";
        $pdo->exec("ALTER TABLE `gate_logs` 
            ADD COLUMN `exit_processing_officer_user_id` INT(11) DEFAULT NULL AFTER `processing_officer_user_id`,
            ADD KEY `fk_gl_exit_officer` (`exit_processing_officer_user_id`),
            ADD CONSTRAINT `fk_gl_exit_officer` FOREIGN KEY (`exit_processing_officer_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ");
    }

    // 7. Update `tariffs` table: Add default_payer_role
    $cols = $db->fetchAll("SHOW COLUMNS FROM `tariffs` LIKE 'default_payer_role'");
    if (empty($cols)) {
        echo "Adding `default_payer_role` to `tariffs`...\n";
        $pdo->exec("ALTER TABLE `tariffs` 
            ADD COLUMN `default_payer_role` ENUM('owner','tenant') DEFAULT 'tenant' AFTER `is_active`
        ");
    }

    // 8. Update `invoices` table: Add batch_reference
    $cols = $db->fetchAll("SHOW COLUMNS FROM `invoices` LIKE 'batch_reference'");
    if (empty($cols)) {
        echo "Adding `batch_reference` to `invoices`...\n";
        $pdo->exec("ALTER TABLE `invoices` 
            ADD COLUMN `batch_reference` VARCHAR(50) DEFAULT NULL AFTER `billing_period_end`,
            ADD KEY `idx_batch_ref` (`batch_reference`)
        ");
    }

    $pdo->commit();
    echo "Migration 002_domain_refinements completed successfully!\n";

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Migration Failed: " . $e->getMessage() . "\n";
    exit(1);
}
