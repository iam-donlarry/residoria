<?php
// seed_data.php - Standalone Sample Data Seeder (v3.0.0)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/classes/ReferenceGenerator.php';

try {
    $db = Database::getInstance();
    $pdo = $db->getPdo();

    echo "--- SEEDING SAMPLE DOMAIN DATA ---\n";

    // Helper for PDO scalar fetch
    $getScalar = function($sql) use ($pdo) {
        $stmt = $pdo->query($sql);
        if ($stmt) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? reset($row) : null;
        }
        return null;
    };

    // 1. Create Streets / Zones
    $streets = [
        ['STR-01', 'Palm Avenue', 'Primary residential avenue'],
        ['STR-02', 'Orchid Road', 'Secondary residential street'],
        ['COM-01', 'Commercial Zone', 'Plaza and retail outlets']
    ];
    $stmtS = $pdo->prepare("INSERT INTO zones_streets (code, name, description) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name)");
    foreach ($streets as $s) {
        $stmtS->execute($s);
    }
    echo "✓ Streets and zones seeded.\n";

    $palmId = $getScalar("SELECT id FROM zones_streets WHERE name = 'Palm Avenue' LIMIT 1");
    $commZoneId = $getScalar("SELECT id FROM zones_streets WHERE name = 'Commercial Zone' LIMIT 1");

    // 2. Create Buildings / Blocks
    $buildings = [
        [$palmId, 'BLD-01', 'Sunrise Apartments', 'Apartment', 4, 'residential'],
        [$palmId, 'BLD-02', 'Palm Villa Block', 'Duplex', 2, 'residential'],
        [$commZoneId, 'BLD-03', 'Executive Plaza', 'Plaza', 3, 'commercial']
    ];
    $stmtB = $pdo->prepare("INSERT INTO buildings_blocks (zone_id, building_code, name, property_type, total_floors, category) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name = VALUES(name)");
    foreach ($buildings as $b) {
        $stmtB->execute($b);
    }
    echo "✓ Buildings and blocks seeded.\n";

    $sunriseId = $getScalar("SELECT id FROM buildings_blocks WHERE name = 'Sunrise Apartments' LIMIT 1");
    $villaBlockId = $getScalar("SELECT id FROM buildings_blocks WHERE name = 'Palm Villa Block' LIMIT 1");
    $plazaId = $getScalar("SELECT id FROM buildings_blocks WHERE name = 'Executive Plaza' LIMIT 1");

    // 3. Create Property Units
    $units = [
        [$sunriseId, '101', 'PROP-000001', 1, 'residential', '2BHK', 2, 2, 'occupied', 'ELE-1001', 'WAT-1001'],
        [$sunriseId, '102', 'PROP-000002', 1, 'residential', '3BHK', 3, 3, 'occupied', 'ELE-1002', 'WAT-1002'],
        [$villaBlockId, 'Villa 14', 'PROP-000003', 1, 'residential', 'Duplex', 4, 4, 'vacant_available', 'ELE-1003', 'WAT-1003'],
        [$plazaId, 'Shop 01', 'PROP-000004', 1, 'commercial', 'Shop', 0, 1, 'occupied', 'ELE-2001', 'WAT-2001'],
        [$plazaId, 'Suite 201', 'PROP-000005', 2, 'commercial', 'Office', 0, 2, 'vacant_available', 'ELE-2002', 'WAT-2002']
    ];
    $stmtU = $pdo->prepare("INSERT INTO property_units (building_id, unit_number, unit_code, floor_number, usage_class, sub_type, bedrooms, bathrooms, status, electric_meter_no, water_meter_no) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status)");
    foreach ($units as $u) {
        $stmtU->execute($u);
    }
    echo "✓ Property units seeded.\n";

    $unit101Id = $getScalar("SELECT id FROM property_units WHERE unit_code = 'PROP-000001' LIMIT 1");
    $unit102Id = $getScalar("SELECT id FROM property_units WHERE unit_code = 'PROP-000002' LIMIT 1");
    $shop01Id  = $getScalar("SELECT id FROM property_units WHERE unit_code = 'PROP-000004' LIMIT 1");

    // 4. Create Persons Profiles
    $personList = [
        ['PRS-000002', 'John', 'Ade', 'Ade', 'john.ade@example.com', '+234 803 111 2222', 'male', '1985-04-12', 'NIN', '12345678901', 'Mary Ade', '+234 803 111 3333', 'Spouse'],
        ['PRS-000003', 'Mary', 'Ade', 'Okafor', 'mary.ade@example.com', '+234 803 111 3333', 'female', '1988-08-20', 'NIN', '12345678902', 'John Ade', '+234 803 111 2222', 'Spouse'],
        ['PRS-000004', 'David', 'Ade', '', NULL, '+234 803 111 2222', 'male', '2015-06-10', NULL, NULL, 'John Ade', '+234 803 111 2222', 'Father'],
        ['PRS-000005', 'Samuel', 'Driver', '', NULL, '+234 802 999 0001', 'male', '1992-01-15', 'Driver License', 'DL-884102', 'Peter Driver', '+234 802 999 0002', 'Brother'],
        ['PRS-000006', 'Alice', 'Proprietor', '', 'alice@proprietor.com', '+234 805 222 4444', 'female', '1979-11-05', 'Passport', 'A0099410', 'Bob Proprietor', '+234 805 222 5555', 'Husband'],
        ['PRS-000007', 'Bob', 'Tenant', '', 'bob.tenant@example.com', '+234 807 333 5555', 'male', '1990-03-30', 'NIN', '98765432101', 'Grace Tenant', '+234 807 333 6666', 'Sister'],
        ['PRS-000008', 'MedPlus', 'Representative', '', 'contact@medplus.ng', '+234 809 444 6666', 'female', '1984-09-14', 'NIN', '55544433322', 'Corp HQ', '+234 1 234 5678', 'Employer'],
        ['PRS-000009', 'Paul', 'Security', '', 'officer.paul@estate.com', '+234 810 555 7777', 'male', '1991-12-01', 'NIN', '77788899900', 'Chief Guard', '+234 810 555 0000', 'Supervisor']
    ];

    $stmtPrs = $pdo->prepare("INSERT INTO persons (person_code, first_name, last_name, other_names, email, primary_phone, gender, date_of_birth, id_type, id_number, emergency_contact_name, emergency_contact_phone, emergency_contact_relationship) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE primary_phone = VALUES(primary_phone)");
    foreach ($personList as $p) {
        $stmtPrs->execute($p);
    }
    echo "✓ Persons directory seeded.\n";

    $johnId   = $getScalar("SELECT id FROM persons WHERE person_code = 'PRS-000002' LIMIT 1");
    $maryId   = $getScalar("SELECT id FROM persons WHERE person_code = 'PRS-000003' LIMIT 1");
    $davidId  = $getScalar("SELECT id FROM persons WHERE person_code = 'PRS-000004' LIMIT 1");
    $samuelId = $getScalar("SELECT id FROM persons WHERE person_code = 'PRS-000005' LIMIT 1");
    $aliceId  = $getScalar("SELECT id FROM persons WHERE person_code = 'PRS-000006' LIMIT 1");
    $bobId    = $getScalar("SELECT id FROM persons WHERE person_code = 'PRS-000007' LIMIT 1");
    $medRepId = $getScalar("SELECT id FROM persons WHERE person_code = 'PRS-000008' LIMIT 1");
    $paulId   = $getScalar("SELECT id FROM persons WHERE person_code = 'PRS-000009' LIMIT 1");

    // 5. Create Corporate Owner
    $stmtCorp = $pdo->prepare("INSERT INTO corporate_owners (company_name, cac_rc_number, tax_id, office_address, official_phone, official_email, representative_person_id) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE company_name = VALUES(company_name)");
    $stmtCorp->execute(['Topsail Realty Ltd', 'RC-998811', 'TIN-445566', '12 Marina, Lagos Island, Lagos', '+234 1 400 9000', 'info@topsail.ng', $aliceId]);
    $topsailCorpId = $getScalar("SELECT id FROM corporate_owners WHERE company_name = 'Topsail Realty Ltd' LIMIT 1");
    echo "✓ Corporate owners seeded.\n";

    // 6. Create Property Ownership
    $stmtPO = $pdo->prepare("INSERT INTO property_ownership (unit_id, owner_type, person_id, corporate_owner_id, ownership_percentage, start_date, acquisition_type, is_current) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    if (!$getScalar("SELECT id FROM property_ownership WHERE unit_id = $unit101Id LIMIT 1")) {
        $stmtPO->execute([$unit101Id, 'individual', $johnId, NULL, 100.00, '2022-01-15', 'Purchase', 1]);
    }
    if (!$getScalar("SELECT id FROM property_ownership WHERE unit_id = $unit102Id LIMIT 1")) {
        $stmtPO->execute([$unit102Id, 'individual', $aliceId, NULL, 100.00, '2021-06-01', 'Purchase', 1]);
    }
    if (!$getScalar("SELECT id FROM property_ownership WHERE unit_id = $shop01Id LIMIT 1")) {
        $stmtPO->execute([$shop01Id, 'corporate', NULL, $topsailCorpId, 100.00, '2020-03-10', 'Development', 1]);
    }
    echo "✓ Property ownership titles seeded.\n";

    // 7. Create Residents
    $stmtRes = $pdo->prepare("INSERT INTO residents (person_id, unit_id, resident_code, residency_category, move_in_date, status) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE status = VALUES(status)");
    $stmtRes->execute([$johnId, $unit101Id, 'RES-000001', 'owner_occupier', '2022-01-20', 'active']);
    $resJohnId = $getScalar("SELECT id FROM residents WHERE resident_code = 'RES-000001' LIMIT 1");

    $stmtRes->execute([$bobId, $unit102Id, 'RES-000002', 'tenant', '2023-02-01', 'active']);
    $resBobId = $getScalar("SELECT id FROM residents WHERE resident_code = 'RES-000002' LIMIT 1");
    echo "✓ Active residents seeded.\n";

    // 8. Create Household Members
    $stmtHM = $pdo->prepare("INSERT INTO household_members (primary_resident_id, person_id, relationship_to_head, is_minor) VALUES (?, ?, ?, ?)");
    if (!$getScalar("SELECT id FROM household_members WHERE person_id = $maryId LIMIT 1")) {
        $stmtHM->execute([$resJohnId, $maryId, 'Spouse', 0]);
    }
    if (!$getScalar("SELECT id FROM household_members WHERE person_id = $davidId LIMIT 1")) {
        $stmtHM->execute([$resJohnId, $davidId, 'Child', 1]);
    }
    echo "✓ Household members seeded.\n";

    // 9. Create Commercial Occupant
    if (!$getScalar("SELECT id FROM commercial_occupants WHERE unit_id = $shop01Id LIMIT 1")) {
        $stmtComm = $pdo->prepare("INSERT INTO commercial_occupants (unit_id, business_name, business_category, cac_rc_number, tax_id, principal_contact_person_id, business_phone, business_email, operating_hours, estimated_staff_count, lease_start_date, lease_end_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtComm->execute([$shop01Id, 'MedPlus Pharmacy', 'Pharmacy & Health', 'RC-894102', 'TIN-889900', $medRepId, '+234 809 444 6666', 'pharmacy@medplus.ng', '08:00 - 21:00 (Mon-Sat)', 6, '2023-01-01', '2026-12-31', 'active']);
    }
    echo "✓ Commercial occupants seeded.\n";

    // 10. Create Domestic Staff
    if (!$getScalar("SELECT id FROM domestic_staff WHERE person_id = $samuelId LIMIT 1")) {
        $stmtDS = $pdo->prepare("INSERT INTO domestic_staff (person_id, employer_resident_id, unit_id, staff_role, residence_type, gate_access_start_time, gate_access_end_time, verification_status, access_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtDS->execute([$samuelId, $resJohnId, $unit101Id, 'Private Driver', 'live_in', '06:00:00', '22:00:00', 'verified', 'active']);
    }
    echo "✓ Domestic staff seeded.\n";

    // 11. Create Security Officer User Account & Estate Staff
    $securityEmail = 'officer.paul@estate.com';
    $checkPaulUser = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $checkPaulUser->execute([$securityEmail]);
    $paulUser = $checkPaulUser->fetch(PDO::FETCH_ASSOC);
    if (!$paulUser) {
        $paulPass = password_hash('security123', PASSWORD_DEFAULT);
        $stmtPU = $pdo->prepare("INSERT INTO users (person_id, name, email, password, role, status) VALUES (?, ?, ?, ?, 'security', 'active')");
        $stmtPU->execute([$paulId, 'Officer Paul', $securityEmail, $paulPass]);
        $paulUserId = $pdo->lastInsertId();
        $pdo->query("INSERT IGNORE INTO user_roles (user_id, role_id) VALUES ($paulUserId, 4)"); // Role #4: Security Officer

        $stmtES = $pdo->prepare("INSERT INTO estate_staff (person_id, user_id, staff_code, department, position_title, employment_status, hire_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmtES->execute([$paulId, $paulUserId, 'STF-000001', 'Security', 'Gate Security Lead', 'active', '2022-05-01']);
    }
    echo "✓ Estate staff seeded.\n";

    // 12. Create Vehicles
    $stmtVeh = $pdo->prepare("INSERT INTO vehicles (person_id, unit_id, license_plate_number, vehicle_code, make, model, color, vehicle_type, decal_number, access_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE make = VALUES(make)");
    $stmtVeh->execute([$johnId, $unit101Id, 'LSD-492AA', 'VEH-000001', 'Toyota', 'Camry', 'Silver', 'sedan', 'TAG-101', 'active']);
    $stmtVeh->execute([$bobId, $unit102Id, 'KJA-108BB', 'VEH-000002', 'Honda', 'Accord', 'Black', 'sedan', 'TAG-102', 'active']);
    echo "✓ Vehicles seeded.\n";

    // 13. Create Visitor Pass
    if (!$getScalar("SELECT id FROM visitor_passes WHERE access_code = '483921' LIMIT 1")) {
        $stmtVP = $pdo->prepare("INSERT INTO visitor_passes (host_person_id, unit_id, access_code, pass_code_formatted, visitor_name, visitor_phone, visitor_category, visit_date, expected_arrival_start, expected_arrival_end, pass_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtVP->execute([$johnId, $unit101Id, '483921', 'VST-849201', 'Grace Guest', '+234 802 111 0000', 'guest', date('Y-m-d'), '10:00:00', '18:00:00', 'active']);
    }
    echo "✓ Visitor passes seeded.\n";

    // 14. Create Property-Centric Invoices & Payments
    $dueDate = date('Y-m-d', strtotime('+30 days'));
    
    // Invoice 1: Unit 101 (Paid)
    if (!$getScalar("SELECT id FROM invoices WHERE invoice_number = 'INV-2026-00001' LIMIT 1")) {
        $stmtInv1 = $pdo->prepare("INSERT INTO invoices (invoice_number, unit_id, payer_person_id, title, total_amount, penalty_amount, discount_amount, net_amount, status, due_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtInv1->execute(['INV-2026-00001', $unit101Id, $johnId, 'Monthly Service & Security Charge', 23000.00, 0.00, 0.00, 23000.00, 'paid', $dueDate]);
        $inv1Id = $pdo->lastInsertId();

        $stmtII = $pdo->prepare("INSERT INTO invoice_items (invoice_id, tariff_id, description, amount) VALUES (?, ?, ?, ?)");
        $stmtII->execute([$inv1Id, 1, 'Monthly Estate Service Charge', 15000.00]);
        $stmtII->execute([$inv1Id, 2, 'Monthly Security Levy', 5000.00]);
        $stmtII->execute([$inv1Id, 3, 'Waste Management Fee', 3000.00]);

        // Payment for Invoice 1
        $stmtPay = $pdo->prepare("INSERT INTO payments (payment_reference, invoice_id, payer_person_id, amount, payment_method, status, gateway_reference, transaction_date) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmtPay->execute(['PAY-20260812-0001', $inv1Id, $johnId, 23000.00, 'paystack', 'successful', 'PST_REF_99881122']);
        $pay1Id = $pdo->lastInsertId();

        $stmtAlloc = $pdo->prepare("INSERT INTO payment_allocations (payment_id, invoice_id, allocated_amount) VALUES (?, ?, ?)");
        $stmtAlloc->execute([$pay1Id, $inv1Id, 23000.00]);
    }

    // Invoice 2: Unit 102 (Unpaid Arrears)
    if (!$getScalar("SELECT id FROM invoices WHERE invoice_number = 'INV-2026-00002' LIMIT 1")) {
        $stmtInv2 = $pdo->prepare("INSERT INTO invoices (invoice_number, unit_id, payer_person_id, title, total_amount, penalty_amount, discount_amount, net_amount, status, due_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmtInv2->execute(['INV-2026-00002', $unit102Id, $bobId, 'Monthly Service & Security Charge', 23000.00, 0.00, 0.00, 23000.00, 'unpaid', $dueDate]);
        $inv2Id = $pdo->lastInsertId();
        
        $stmtII = $pdo->prepare("INSERT INTO invoice_items (invoice_id, tariff_id, description, amount) VALUES (?, ?, ?, ?)");
        $stmtII->execute([$inv2Id, 1, 'Monthly Estate Service Charge', 15000.00]);
        $stmtII->execute([$inv2Id, 2, 'Monthly Security Levy', 5000.00]);
        $stmtII->execute([$inv2Id, 3, 'Waste Management Fee', 3000.00]);
    }
    echo "✓ Invoices and payment records seeded.\n";

    // 15. Create Maintenance Ticket
    if (!$getScalar("SELECT id FROM maintenance_tickets WHERE ticket_code = 'TKT-00001' LIMIT 1")) {
        $stmtTkt = $pdo->prepare("INSERT INTO maintenance_tickets (ticket_code, unit_id, reporter_person_id, title, description, category, priority, cost_responsibility, estimated_cost, actual_cost, status, admin_resolution_notes, resolved_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmtTkt->execute(['TKT-00001', $unit101Id, $johnId, 'Water leak in master bathroom', 'Plumbing leak discovered under sink basin.', 'plumbing', 'medium', 'resident_billed', 10000.00, 8500.00, 'resolved', 'Replaced worn rubber washer gasket and sealed pipe joints.']);
    }
    echo "✓ Maintenance tickets seeded.\n";

    echo "--- SAMPLE DATA SEEDING COMPLETE! ---\n";

} catch (Exception $e) {
    die("Seeding Error: " . $e->getMessage() . "\n");
}
