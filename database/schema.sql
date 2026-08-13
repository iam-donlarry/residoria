-- Estate Management Application Canonical Database Schema
-- Version 3.0.0 (Standalone Single-Estate Deployment Architecture)

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Table structure for table `system_settings`
--
CREATE TABLE IF NOT EXISTS `system_settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `system_settings` (`setting_key`, `setting_value`) VALUES
('estate_name', 'Residoria Estate'),
('estate_location', 'Victoria Island, Lagos, Nigeria'),
('estate_motto', 'Excellence in Living & Security'),
('estate_logo', ''),
('estate_hero_image', ''),
('office_email', 'admin@residoria.com'),
('office_phone', '+234 800 123 4567'),
('currency_symbol', '₦'),
('theme_color', '#12B1B0'),
('paystack_public_key', ''),
('paystack_secret_key', ''),
('estate_rules', '1. Respect quiet hours after 10:00 PM.\r\n2. Ensure all visitors generate gate access codes.'),
('estate_code_of_conduct', 'Be courteous to security officers and management personnel.'),
('resident_id_prefix', 'RES-'),
('property_code_prefix', 'PROP-'),
('visitor_pass_prefix', 'VST-'),
('invoice_prefix', 'INV-'),
('receipt_prefix', 'REC-'),
('ticket_prefix', 'TKT-'),
('staff_prefix', 'STF-'),
('vehicle_prefix', 'VEH-')
ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);

--
-- Table structure for table `persons` (Normalized Human Identities)
--
CREATE TABLE IF NOT EXISTS `persons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `person_code` varchar(50) NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `other_names` varchar(100) DEFAULT NULL,
  `email` varchar(150) DEFAULT NULL,
  `primary_phone` varchar(20) NOT NULL,
  `secondary_phone` varchar(20) DEFAULT NULL,
  `gender` enum('male','female','other') DEFAULT NULL,
  `date_of_birth` date DEFAULT NULL,
  `profile_photo` varchar(255) DEFAULT NULL,
  `id_type` varchar(50) DEFAULT NULL,
  `id_number` varchar(100) DEFAULT NULL,
  `emergency_contact_name` varchar(150) DEFAULT NULL,
  `emergency_contact_phone` varchar(20) DEFAULT NULL,
  `emergency_contact_relationship` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `person_code` (`person_code`),
  KEY `email` (`email`),
  KEY `primary_phone` (`primary_phone`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `users` (System Login Accounts)
--
CREATE TABLE IF NOT EXISTS `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `person_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(150) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'resident',
  `status` enum('active','suspended','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`),
  KEY `fk_users_person` (`person_id`),
  CONSTRAINT `fk_users_person` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `roles`
--
CREATE TABLE IF NOT EXISTS `roles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `roles` (`id`, `name`, `description`) VALUES
(1, 'Estate Administrator', 'Full administrative access to all system modules and configuration'),
(2, 'Property Manager', 'Manage streets, buildings, units, owners, residents, and tenancy'),
(3, 'Finance Officer', 'Manage tariffs, invoices, payments, allocations, and receipts'),
(4, 'Security Officer', 'Operate gate clearance portal, verify visitor passes, log gate entries'),
(5, 'Resident', 'View personal unit dashboard, pay invoices, submit maintenance requests, generate visitor passes')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

--
-- Table structure for table `permissions`
--
CREATE TABLE IF NOT EXISTS `permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(100) NOT NULL,
  `module` varchar(100) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `permissions` (`id`, `code`, `module`, `description`) VALUES
(1, 'properties.view', 'properties', 'View streets, buildings, and units'),
(2, 'properties.create', 'properties', 'Add new streets, buildings, and units'),
(3, 'properties.edit', 'properties', 'Edit existing streets, buildings, and units'),
(4, 'properties.delete', 'properties', 'Delete or archive property structures'),
(5, 'people.view', 'people', 'View centralized person profiles and directory'),
(6, 'people.create', 'people', 'Register new people and profiles'),
(7, 'people.edit', 'people', 'Update person identity information'),
(8, 'residents.view', 'residents', 'View resident directory and occupancy'),
(9, 'residents.create', 'residents', 'Register residents and household members'),
(10, 'residents.edit', 'residents', 'Update resident occupancy details'),
(11, 'residents.archive', 'residents', 'Archive moved out residents'),
(12, 'owners.view', 'owners', 'View property title holders'),
(13, 'owners.manage', 'owners', 'Add and update property owners'),
(14, 'commercial.view', 'commercial', 'View commercial business occupants'),
(15, 'commercial.manage', 'commercial', 'Manage commercial business profiles'),
(16, 'billing.view', 'billing', 'View property billing ledgers and tariffs'),
(17, 'billing.create', 'billing', 'Generate invoices and manage tariffs'),
(18, 'billing.manage', 'billing', 'Update and cancel billing items'),
(19, 'payments.view', 'payments', 'View payment records and receipts'),
(20, 'payments.record', 'payments', 'Record manual payments and allocations'),
(21, 'maintenance.view', 'maintenance', 'View maintenance tickets'),
(22, 'maintenance.create', 'maintenance', 'Submit maintenance requests'),
(23, 'maintenance.manage', 'maintenance', 'Assign staff and update work orders'),
(24, 'security.view', 'security', 'View visitor logs and gate activity'),
(25, 'security.gate', 'security', 'Operate security gate check-in portal'),
(26, 'security.pass_gen', 'security', 'Generate visitor authorization access codes'),
(27, 'vehicles.view', 'vehicles', 'View registered vehicles'),
(28, 'vehicles.manage', 'vehicles', 'Register and update vehicle decals'),
(29, 'staff.view', 'staff', 'View estate staff directory'),
(30, 'staff.manage', 'staff', 'Register and assign estate personnel'),
(31, 'reports.view', 'reports', 'Access operational and financial reports'),
(32, 'settings.view', 'settings', 'View estate system settings'),
(33, 'settings.manage', 'settings', 'Configure estate profile and tariffs'),
(34, 'roles.manage', 'roles', 'Manage roles and assign permissions')
ON DUPLICATE KEY UPDATE `code` = VALUES(`code`);

--
-- Table structure for table `role_permissions`
--
CREATE TABLE IF NOT EXISTS `role_permissions` (
  `role_id` int(11) NOT NULL,
  `permission_id` int(11) NOT NULL,
  PRIMARY KEY (`role_id`,`permission_id`),
  KEY `fk_rp_permission` (`permission_id`),
  CONSTRAINT `fk_rp_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rp_permission` FOREIGN KEY (`permission_id`) REFERENCES `permissions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `user_roles`
--
CREATE TABLE IF NOT EXISTS `user_roles` (
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  PRIMARY KEY (`user_id`,`role_id`),
  KEY `fk_ur_role` (`role_id`),
  CONSTRAINT `fk_ur_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ur_role` FOREIGN KEY (`role_id`) REFERENCES `roles` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `zones_streets`
--
CREATE TABLE IF NOT EXISTS `zones_streets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `code` varchar(50) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `buildings_blocks`
--
CREATE TABLE IF NOT EXISTS `buildings_blocks` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `zone_id` int(11) NOT NULL,
  `building_code` varchar(50) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `property_type` varchar(50) NOT NULL DEFAULT 'Apartment',
  `total_floors` int(11) NOT NULL DEFAULT 1,
  `category` enum('residential','commercial','mixed') DEFAULT 'residential',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `building_code` (`building_code`),
  KEY `fk_buildings_zone` (`zone_id`),
  CONSTRAINT `fk_buildings_zone` FOREIGN KEY (`zone_id`) REFERENCES `zones_streets` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `property_units`
--
CREATE TABLE IF NOT EXISTS `property_units` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `building_id` int(11) NOT NULL,
  `unit_number` varchar(50) NOT NULL,
  `unit_code` varchar(50) NOT NULL,
  `floor_number` int(11) DEFAULT 1,
  `usage_class` enum('residential','commercial','mixed') DEFAULT 'residential',
  `sub_type` varchar(50) DEFAULT '2BHK',
  `bedrooms` int(11) DEFAULT 0,
  `bathrooms` int(11) DEFAULT 0,
  `status` enum('vacant_available','reserved','occupied','under_maintenance','under_notice','disputed_inactive') DEFAULT 'vacant_available',
  `electric_meter_no` varchar(50) DEFAULT NULL,
  `water_meter_no` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unit_code` (`unit_code`),
  KEY `fk_units_building` (`building_id`),
  CONSTRAINT `fk_units_building` FOREIGN KEY (`building_id`) REFERENCES `buildings_blocks` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `corporate_owners`
--
CREATE TABLE IF NOT EXISTS `corporate_owners` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `company_name` varchar(150) NOT NULL,
  `cac_rc_number` varchar(50) DEFAULT NULL,
  `tax_id` varchar(50) DEFAULT NULL,
  `office_address` text DEFAULT NULL,
  `official_phone` varchar(20) DEFAULT NULL,
  `official_email` varchar(100) DEFAULT NULL,
  `representative_person_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_corp_rep` (`representative_person_id`),
  CONSTRAINT `fk_corp_rep` FOREIGN KEY (`representative_person_id`) REFERENCES `persons` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `property_ownership`
--
CREATE TABLE IF NOT EXISTS `property_ownership` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `unit_id` int(11) NOT NULL,
  `owner_type` enum('individual','corporate') DEFAULT 'individual',
  `person_id` int(11) DEFAULT NULL,
  `corporate_owner_id` int(11) DEFAULT NULL,
  `ownership_percentage` decimal(5,2) DEFAULT 100.00,
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `acquisition_type` varchar(100) DEFAULT 'Purchase',
  `is_current` tinyint(1) DEFAULT 1,
  `supporting_doc_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_po_unit` (`unit_id`),
  KEY `fk_po_person` (`person_id`),
  KEY `fk_po_corp` (`corporate_owner_id`),
  CONSTRAINT `fk_po_unit` FOREIGN KEY (`unit_id`) REFERENCES `property_units` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_po_person` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_po_corp` FOREIGN KEY (`corporate_owner_id`) REFERENCES `corporate_owners` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `residents`
--
CREATE TABLE IF NOT EXISTS `residents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `person_id` int(11) NOT NULL,
  `unit_id` int(11) NOT NULL,
  `resident_code` varchar(50) NOT NULL,
  `residency_category` enum('owner_occupier','tenant','co_resident') DEFAULT 'tenant',
  `is_head_of_household` tinyint(1) NOT NULL DEFAULT 0,
  `move_in_date` date DEFAULT NULL,
  `scheduled_move_out_date` date DEFAULT NULL,
  `actual_move_out_date` date DEFAULT NULL,
  `status` enum('active','moved_out','suspended') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `resident_code` (`resident_code`),
  KEY `fk_res_person` (`person_id`),
  KEY `fk_res_unit` (`unit_id`),
  CONSTRAINT `fk_res_person` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_res_unit` FOREIGN KEY (`unit_id`) REFERENCES `property_units` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `household_members`
--
CREATE TABLE IF NOT EXISTS `household_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `primary_resident_id` int(11) NOT NULL,
  `unit_id` int(11) NOT NULL,
  `person_id` int(11) NOT NULL,
  `relationship_to_head` varchar(50) NOT NULL,
  `is_minor` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_hm_resident` (`primary_resident_id`),
  KEY `fk_hm_unit` (`unit_id`),
  KEY `fk_hm_person` (`person_id`),
  CONSTRAINT `fk_hm_resident` FOREIGN KEY (`primary_resident_id`) REFERENCES `residents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hm_unit` FOREIGN KEY (`unit_id`) REFERENCES `property_units` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_hm_person` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `commercial_occupants`
--
CREATE TABLE IF NOT EXISTS `commercial_occupants` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `unit_id` int(11) NOT NULL,
  `business_name` varchar(150) NOT NULL,
  `business_category` varchar(100) DEFAULT 'Retail',
  `cac_rc_number` varchar(50) DEFAULT NULL,
  `tax_id` varchar(50) DEFAULT NULL,
  `principal_contact_person_id` int(11) DEFAULT NULL,
  `business_phone` varchar(20) DEFAULT NULL,
  `business_email` varchar(100) DEFAULT NULL,
  `operating_hours` varchar(100) DEFAULT NULL,
  `estimated_staff_count` int(11) DEFAULT 0,
  `lease_start_date` date DEFAULT NULL,
  `lease_end_date` date DEFAULT NULL,
  `status` enum('active','expired','terminated') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_comm_unit` (`unit_id`),
  KEY `fk_comm_contact` (`principal_contact_person_id`),
  CONSTRAINT `fk_comm_unit` FOREIGN KEY (`unit_id`) REFERENCES `property_units` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_comm_contact` FOREIGN KEY (`principal_contact_person_id`) REFERENCES `persons` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `commercial_employees`
--
CREATE TABLE IF NOT EXISTS `commercial_employees` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `commercial_id` int(11) NOT NULL,
  `person_id` int(11) NOT NULL,
  `job_title` varchar(100) NOT NULL DEFAULT 'Employee',
  `access_status` enum('active','suspended') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_ce_commercial` (`commercial_id`),
  KEY `fk_ce_person` (`person_id`),
  CONSTRAINT `fk_ce_commercial` FOREIGN KEY (`commercial_id`) REFERENCES `commercial_occupants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ce_person` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `tenancy_agreements`
--
CREATE TABLE IF NOT EXISTS `tenancy_agreements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `unit_id` int(11) NOT NULL,
  `tenant_person_id` int(11) NOT NULL,
  `landlord_person_id` int(11) DEFAULT NULL,
  `landlord_corporate_id` int(11) DEFAULT NULL,
  `lease_start_date` date NOT NULL,
  `lease_end_date` date NOT NULL,
  `status` enum('active','notice_served','expired','terminated') DEFAULT 'active',
  `agreement_doc_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_ta_unit` (`unit_id`),
  KEY `fk_ta_tenant` (`tenant_person_id`),
  KEY `fk_ta_landlord_p` (`landlord_person_id`),
  KEY `fk_ta_landlord_c` (`landlord_corporate_id`),
  CONSTRAINT `fk_ta_unit` FOREIGN KEY (`unit_id`) REFERENCES `property_units` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ta_tenant` FOREIGN KEY (`tenant_person_id`) REFERENCES `persons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ta_landlord_p` FOREIGN KEY (`landlord_person_id`) REFERENCES `persons` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_ta_landlord_c` FOREIGN KEY (`landlord_corporate_id`) REFERENCES `corporate_owners` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `domestic_staff`
--
CREATE TABLE IF NOT EXISTS `domestic_staff` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `person_id` int(11) NOT NULL,
  `employer_resident_id` int(11) NOT NULL,
  `unit_id` int(11) NOT NULL,
  `staff_role` varchar(100) NOT NULL,
  `residence_type` enum('live_in','daily') DEFAULT 'daily',
  `gate_access_start_time` time DEFAULT '07:00:00',
  `gate_access_end_time` time DEFAULT '18:00:00',
  `verification_status` enum('pending','verified','rejected') DEFAULT 'pending',
  `verification_notes` text DEFAULT NULL,
  `guarantor_name` varchar(150) DEFAULT NULL,
  `guarantor_phone` varchar(20) DEFAULT NULL,
  `guarantor_address` text DEFAULT NULL,
  `nin_number` varchar(50) DEFAULT NULL,
  `access_status` enum('active','suspended','revoked') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_ds_person` (`person_id`),
  KEY `fk_ds_employer` (`employer_resident_id`),
  KEY `fk_ds_unit` (`unit_id`),
  CONSTRAINT `fk_ds_person` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ds_employer` FOREIGN KEY (`employer_resident_id`) REFERENCES `residents` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ds_unit` FOREIGN KEY (`unit_id`) REFERENCES `property_units` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `estate_staff`
--
CREATE TABLE IF NOT EXISTS `estate_staff` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `person_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `staff_code` varchar(50) NOT NULL,
  `department` varchar(100) DEFAULT 'Security',
  `position_title` varchar(100) NOT NULL,
  `employment_status` enum('active','suspended','terminated') DEFAULT 'active',
  `hire_date` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `staff_code` (`staff_code`),
  KEY `fk_es_person` (`person_id`),
  KEY `fk_es_user` (`user_id`),
  CONSTRAINT `fk_es_person` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_es_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `vehicles`
--
CREATE TABLE IF NOT EXISTS `vehicles` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `person_id` int(11) NOT NULL,
  `unit_id` int(11) DEFAULT NULL,
  `license_plate_number` varchar(50) NOT NULL,
  `vehicle_code` varchar(50) NOT NULL,
  `make` varchar(50) DEFAULT NULL,
  `model` varchar(50) DEFAULT NULL,
  `color` varchar(30) DEFAULT NULL,
  `vehicle_type` enum('sedan','suv','motorcycle','delivery_truck','other') DEFAULT 'sedan',
  `decal_number` varchar(50) DEFAULT NULL,
  `rfid_tag_id` varchar(50) DEFAULT NULL,
  `access_status` enum('active','suspended','blacklisted') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `license_plate_number` (`license_plate_number`),
  UNIQUE KEY `vehicle_code` (`vehicle_code`),
  KEY `fk_veh_person` (`person_id`),
  KEY `fk_veh_unit` (`unit_id`),
  CONSTRAINT `fk_veh_person` FOREIGN KEY (`person_id`) REFERENCES `persons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_veh_unit` FOREIGN KEY (`unit_id`) REFERENCES `property_units` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `visitor_passes`
--
CREATE TABLE IF NOT EXISTS `visitor_passes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `host_person_id` int(11) NOT NULL,
  `unit_id` int(11) NOT NULL,
  `access_code` varchar(50) NOT NULL,
  `pass_code_formatted` varchar(50) NOT NULL,
  `visitor_name` varchar(150) NOT NULL,
  `visitor_phone` varchar(20) DEFAULT NULL,
  `visitor_category` enum('guest','delivery','contractor','service_provider','event') DEFAULT 'guest',
  `pass_type` enum('single_entry','multi_entry') DEFAULT 'single_entry',
  `visit_date` date NOT NULL,
  `valid_from_date` date DEFAULT NULL,
  `valid_until_date` date DEFAULT NULL,
  `expected_arrival_start` time DEFAULT NULL,
  `expected_arrival_end` time DEFAULT NULL,
  `vehicle_plate_number` varchar(50) DEFAULT NULL,
  `expected_visitor_count` int(11) DEFAULT 1,
  `max_uses` int(11) DEFAULT 1,
  `use_count` int(11) DEFAULT 0,
  `pass_status` enum('active','used','expired','cancelled') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `access_code` (`access_code`),
  UNIQUE KEY `pass_code_formatted` (`pass_code_formatted`),
  KEY `fk_vp_host` (`host_person_id`),
  KEY `fk_vp_unit` (`unit_id`),
  CONSTRAINT `fk_vp_host` FOREIGN KEY (`host_person_id`) REFERENCES `persons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_vp_unit` FOREIGN KEY (`unit_id`) REFERENCES `property_units` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `gate_logs`
--
CREATE TABLE IF NOT EXISTS `gate_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pass_id` int(11) DEFAULT NULL,
  `unit_id` int(11) NOT NULL,
  `visitor_name` varchar(150) NOT NULL,
  `visitor_phone` varchar(20) DEFAULT NULL,
  `entry_timestamp` datetime DEFAULT current_timestamp(),
  `exit_timestamp` datetime DEFAULT NULL,
  `processing_officer_user_id` int(11) DEFAULT NULL,
  `exit_processing_officer_user_id` int(11) DEFAULT NULL,
  `entry_type` enum('pre_authorized','walk_in') DEFAULT 'pre_authorized',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_gl_pass` (`pass_id`),
  KEY `fk_gl_unit` (`unit_id`),
  KEY `fk_gl_officer` (`processing_officer_user_id`),
  KEY `fk_gl_exit_officer` (`exit_processing_officer_user_id`),
  CONSTRAINT `fk_gl_pass` FOREIGN KEY (`pass_id`) REFERENCES `visitor_passes` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_gl_unit` FOREIGN KEY (`unit_id`) REFERENCES `property_units` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_gl_officer` FOREIGN KEY (`processing_officer_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_gl_exit_officer` FOREIGN KEY (`exit_processing_officer_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `tariffs`
--
CREATE TABLE IF NOT EXISTS `tariffs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(150) NOT NULL,
  `category` enum('service_charge','security','waste','water','power','infrastructure','penalty','other') DEFAULT 'service_charge',
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `frequency` enum('monthly','quarterly','annually','one_time') DEFAULT 'monthly',
  `due_days` int(11) DEFAULT 30,
  `penalty_percent` decimal(5,2) DEFAULT 0.00,
  `is_active` tinyint(1) DEFAULT 1,
  `default_payer_role` enum('owner','tenant') DEFAULT 'tenant',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

INSERT INTO `tariffs` (`id`, `name`, `category`, `amount`, `frequency`, `due_days`, `penalty_percent`, `default_payer_role`) VALUES
(1, 'Monthly Estate Service Charge', 'service_charge', 15000.00, 'monthly', 30, 5.00, 'tenant'),
(2, 'Monthly Security Levy', 'security', 5000.00, 'monthly', 30, 0.00, 'tenant'),
(3, 'Waste Management Fee', 'waste', 3000.00, 'monthly', 30, 0.00, 'tenant')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);

--
-- Table structure for table `invoices` (Property-Centric Billing)
--
CREATE TABLE IF NOT EXISTS `invoices` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_number` varchar(50) NOT NULL,
  `unit_id` int(11) NOT NULL,
  `payer_person_id` int(11) DEFAULT NULL,
  `title` varchar(150) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `penalty_amount` decimal(10,2) DEFAULT 0.00,
  `discount_amount` decimal(10,2) DEFAULT 0.00,
  `net_amount` decimal(10,2) NOT NULL,
  `status` enum('unpaid','partial','paid','cancelled') DEFAULT 'unpaid',
  `due_date` date NOT NULL,
  `billing_period_start` date DEFAULT NULL,
  `billing_period_end` date DEFAULT NULL,
  `batch_reference` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `invoice_number` (`invoice_number`),
  KEY `fk_inv_unit` (`unit_id`),
  KEY `fk_inv_payer` (`payer_person_id`),
  KEY `idx_batch_ref` (`batch_reference`),
  CONSTRAINT `fk_inv_unit` FOREIGN KEY (`unit_id`) REFERENCES `property_units` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inv_payer` FOREIGN KEY (`payer_person_id`) REFERENCES `persons` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `invoice_items`
--
CREATE TABLE IF NOT EXISTS `invoice_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_id` int(11) NOT NULL,
  `tariff_id` int(11) DEFAULT NULL,
  `description` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_ii_invoice` (`invoice_id`),
  KEY `fk_ii_tariff` (`tariff_id`),
  CONSTRAINT `fk_ii_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ii_tariff` FOREIGN KEY (`tariff_id`) REFERENCES `tariffs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `payments`
--
CREATE TABLE IF NOT EXISTS `payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payment_reference` varchar(50) NOT NULL,
  `invoice_id` int(11) DEFAULT NULL,
  `payer_person_id` int(11) DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_method` enum('paystack','bank_transfer','pos','cash','cheque') DEFAULT 'paystack',
  `status` enum('pending','successful','failed','reversed') DEFAULT 'pending',
  `gateway_reference` varchar(100) DEFAULT NULL,
  `transaction_date` datetime DEFAULT current_timestamp(),
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `payment_reference` (`payment_reference`),
  KEY `fk_pay_invoice` (`invoice_id`),
  KEY `fk_pay_payer` (`payer_person_id`),
  CONSTRAINT `fk_pay_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pay_payer` FOREIGN KEY (`payer_person_id`) REFERENCES `persons` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `payment_allocations`
--
CREATE TABLE IF NOT EXISTS `payment_allocations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payment_id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `allocated_amount` decimal(10,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_pa_payment` (`payment_id`),
  KEY `fk_pa_invoice` (`invoice_id`),
  CONSTRAINT `fk_pa_payment` FOREIGN KEY (`payment_id`) REFERENCES `payments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pa_invoice` FOREIGN KEY (`invoice_id`) REFERENCES `invoices` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `maintenance_tickets`
--
CREATE TABLE IF NOT EXISTS `maintenance_tickets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ticket_code` varchar(50) NOT NULL,
  `unit_id` int(11) NOT NULL,
  `reporter_person_id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `description` text DEFAULT NULL,
  `category` enum('plumbing','electrical','structural','security','waste','facility','other') DEFAULT 'plumbing',
  `priority` enum('low','medium','high','emergency') DEFAULT 'medium',
  `cost_responsibility` enum('estate_funded','resident_billed','owner_billed') DEFAULT 'resident_billed',
  `estimated_cost` decimal(10,2) DEFAULT 0.00,
  `actual_cost` decimal(10,2) DEFAULT 0.00,
  `status` enum('submitted','reviewed','assigned','in_progress','resolved','closed') DEFAULT 'submitted',
  `assigned_staff_id` int(11) DEFAULT NULL,
  `admin_resolution_notes` text DEFAULT NULL,
  `photo_attachment_path` varchar(255) DEFAULT NULL,
  `resolved_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `ticket_code` (`ticket_code`),
  KEY `fk_mt_unit` (`unit_id`),
  KEY `fk_mt_reporter` (`reporter_person_id`),
  KEY `fk_mt_staff` (`assigned_staff_id`),
  CONSTRAINT `fk_mt_unit` FOREIGN KEY (`unit_id`) REFERENCES `property_units` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mt_reporter` FOREIGN KEY (`reporter_person_id`) REFERENCES `persons` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_mt_staff` FOREIGN KEY (`assigned_staff_id`) REFERENCES `estate_staff` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `documents`
--
CREATE TABLE IF NOT EXISTS `documents` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entity_type` enum('property','tenancy','ownership','person','maintenance') NOT NULL,
  `entity_id` int(11) NOT NULL,
  `document_type` varchar(50) NOT NULL,
  `document_name` varchar(150) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `file_size` int(11) DEFAULT 0,
  `mime_type` varchar(100) DEFAULT NULL,
  `uploaded_by_user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_doc_user` (`uploaded_by_user_id`),
  CONSTRAINT `fk_doc_user` FOREIGN KEY (`uploaded_by_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `announcements`
--
CREATE TABLE IF NOT EXISTS `announcements` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(150) NOT NULL,
  `content` text NOT NULL,
  `target_group` enum('all','residential','commercial','owners','tenants','arrears') DEFAULT 'all',
  `author_user_id` int(11) DEFAULT NULL,
  `publish_date` date DEFAULT NULL,
  `expiry_date` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_ann_author` (`author_user_id`),
  CONSTRAINT `fk_ann_author` FOREIGN KEY (`author_user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Table structure for table `audit_logs`
--
CREATE TABLE IF NOT EXISTS `audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(255) NOT NULL,
  `module` varchar(100) DEFAULT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_audit_user` (`user_id`),
  CONSTRAINT `fk_audit_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

COMMIT;
