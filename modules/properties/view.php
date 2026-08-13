<?php
// modules/properties/view.php - Property 360° Operational Hub
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/helpers.php';

requirePermission('properties.view');

$db = Database::getInstance();
$unit_id = intval($_GET['id'] ?? 0);

if ($unit_id <= 0) {
    header("Location: index");
    exit;
}

// 1. Fetch Property Unit Details
$unit = $db->fetchOne("
    SELECT u.*, b.name as building_name, b.building_code, b.category as building_category, 
           z.name as street_name, z.code as street_code
    FROM property_units u
    JOIN buildings_blocks b ON u.building_id = b.id
    JOIN zones_streets z ON b.zone_id = z.id
    WHERE u.id = ?
", [$unit_id]);

if (!$unit) {
    die("Property Unit not found.");
}

// 2. Fetch Calculated Occupancy Metrics
$occ = calculateUnitOccupancy($unit_id);

// 3. Fetch Ownership Details
$ownerships = $db->fetchAll("
    SELECT po.*, 
           CONCAT(p.first_name, ' ', p.last_name) as owner_person_name, p.primary_phone as owner_person_phone, p.email as owner_person_email,
           co.company_name, co.official_phone as corp_phone, co.official_email as corp_email
    FROM property_ownership po
    LEFT JOIN persons p ON po.person_id = p.id
    LEFT JOIN corporate_owners co ON po.corporate_owner_id = co.id
    WHERE po.unit_id = ?
    ORDER BY po.is_current DESC, po.start_date DESC
", [$unit_id]);

// 4. Fetch Active Tenancy Agreement
$tenancy = $db->fetchOne("
    SELECT ta.*, CONCAT(p.first_name, ' ', p.last_name) as tenant_name, p.primary_phone as tenant_phone, p.email as tenant_email
    FROM tenancy_agreements ta
    JOIN persons p ON ta.tenant_person_id = p.id
    WHERE ta.unit_id = ? AND ta.status = 'active'
    LIMIT 1
", [$unit_id]);

// 5. Fetch Residents (Head & Co-Residents)
$residents = $db->fetchAll("
    SELECT r.*, CONCAT(p.first_name, ' ', p.last_name) as person_name, p.primary_phone, p.email, p.gender, p.person_code
    FROM residents r
    JOIN persons p ON r.person_id = p.id
    WHERE r.unit_id = ?
    ORDER BY r.is_head_of_household DESC, r.status ASC
", [$unit_id]);

// 6. Fetch Household Members / Dependents
$dependents = $db->fetchAll("
    SELECT hm.*, CONCAT(p.first_name, ' ', p.last_name) as person_name, p.primary_phone, p.gender, p.date_of_birth,
           CONCAT(head_p.first_name, ' ', head_p.last_name) as head_name
    FROM household_members hm
    JOIN persons p ON hm.person_id = p.id
    JOIN residents r ON hm.primary_resident_id = r.id
    JOIN persons head_p ON r.person_id = head_p.id
    WHERE hm.unit_id = ? OR r.unit_id = ?
    ORDER BY hm.created_at DESC
", [$unit_id, $unit_id]);

// 7. Fetch Domestic Staff
$staffList = $db->fetchAll("
    SELECT ds.*, CONCAT(p.first_name, ' ', p.last_name) as staff_name, p.primary_phone, p.gender,
           CONCAT(emp_p.first_name, ' ', emp_p.last_name) as employer_name
    FROM domestic_staff ds
    JOIN persons p ON ds.person_id = p.id
    JOIN residents r ON ds.employer_resident_id = r.id
    JOIN persons emp_p ON r.person_id = emp_p.id
    WHERE ds.unit_id = ?
    ORDER BY ds.access_status ASC, ds.residence_type ASC
", [$unit_id]);

// 8. Fetch Commercial Personnel (if commercial unit)
$commercialOccupant = $db->fetchOne("SELECT * FROM commercial_occupants WHERE unit_id = ? AND status = 'active' LIMIT 1", [$unit_id]);
$commercialEmployees = [];
if ($commercialOccupant) {
    $commercialEmployees = $db->fetchAll("
        SELECT ce.*, CONCAT(p.first_name, ' ', p.last_name) as employee_name, p.primary_phone, p.email
        FROM commercial_employees ce
        JOIN persons p ON ce.person_id = p.id
        WHERE ce.commercial_id = ?
        ORDER BY ce.access_status ASC
    ", [$commercialOccupant['id']]);
}

// 9. Fetch Vehicles
$vehicles = $db->fetchAll("
    SELECT v.*, CONCAT(p.first_name, ' ', p.last_name) as owner_name, p.primary_phone
    FROM vehicles v
    JOIN persons p ON v.person_id = p.id
    WHERE v.unit_id = ?
    ORDER BY v.access_status ASC
", [$unit_id]);

// 10. Fetch Visitor Passes & Active Visits
$visitorPasses = $db->fetchAll("
    SELECT vp.*, CONCAT(p.first_name, ' ', p.last_name) as host_name
    FROM visitor_passes vp
    JOIN persons p ON vp.host_person_id = p.id
    WHERE vp.unit_id = ?
    ORDER BY vp.created_at DESC
    LIMIT 20
", [$unit_id]);

$gateVisits = $db->fetchAll("
    SELECT gl.*, vp.access_code, vp.pass_code_formatted
    FROM gate_logs gl
    LEFT JOIN visitor_passes vp ON gl.pass_id = vp.id
    WHERE gl.unit_id = ?
    ORDER BY gl.entry_timestamp DESC
    LIMIT 20
", [$unit_id]);

// 11. Fetch Billing Ledger (Invoices)
$invoices = $db->fetchAll("
    SELECT inv.*, CONCAT(p.first_name, ' ', p.last_name) as payer_name
    FROM invoices inv
    LEFT JOIN persons p ON inv.payer_person_id = p.id
    WHERE inv.unit_id = ?
    ORDER BY inv.created_at DESC
", [$unit_id]);

$totalArrears = 0;
foreach ($invoices as $inv) {
    if (in_array($inv['status'], ['unpaid', 'partial'])) {
        $totalArrears += (float)$inv['net_amount'];
    }
}

// 12. Fetch Maintenance Tickets
$tickets = $db->fetchAll("
    SELECT mt.*, CONCAT(p.first_name, ' ', p.last_name) as reporter_name
    FROM maintenance_tickets mt
    JOIN persons p ON mt.reporter_person_id = p.id
    WHERE mt.unit_id = ?
    ORDER BY mt.created_at DESC
", [$unit_id]);

$pageTitle = "Property 360° Profile - " . e($unit['unit_code']);
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="content-wrapper">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <h1 class="page-title" style="margin: 0; font-size: 1.75rem; font-weight: 700;">Property 360° Profile: <?= e($unit['unit_code']) ?></h1>
                <span class="badge badge-<?= $unit['status'] === 'occupied' ? 'success' : ($unit['status'] === 'vacant_available' ? 'info' : 'warning') ?>" style="padding: 0.35rem 0.75rem; font-size: 0.8125rem;">
                    <?= strtoupper(str_replace('_', ' ', $unit['status'])) ?>
                </span>
            </div>
            <p class="text-subtitle" style="color: var(--text-secondary); margin-top: 0.25rem;">
                <i data-lucide="map-pin" style="width: 14px; height: 14px; vertical-align: middle;"></i>
                <?= e($unit['street_name']) ?> &bull; <?= e($unit['building_name']) ?> &bull; Floor <?= e($unit['floor_number']) ?> &bull; Unit <?= e($unit['unit_number']) ?>
            </p>
        </div>
        <div style="display: flex; gap: 0.5rem;">
            <a href="index.php" class="btn btn-secondary">
                <i data-lucide="arrow-left" style="width: 16px; height: 16px;"></i> Back to Properties
            </a>
            <a href="building_view.php?id=<?= $unit['building_id'] ?>" class="btn btn-outline">
                <i data-lucide="building-2" style="width: 16px; height: 16px;"></i> Building Matrix
            </a>
        </div>
    </div>

    <!-- Top KPI Grid -->
    <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem;">
        <div class="stat-card" style="background: white; padding: 1.25rem; border-radius: 1rem; border: 1px solid var(--border-color); box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
            <div class="stat-label" style="font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600;">Permanent Occupancy</div>
            <div class="stat-value" style="font-size: 1.5rem; font-weight: 700; color: var(--text-primary); margin-top: 0.35rem;">
                <?= $occ['permanent_occupancy'] ?> <span style="font-size: 0.875rem; font-weight: 500; color: var(--text-secondary);">Persons</span>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem;">
                <?= $occ['total_household_residents'] ?> Residents &bull; <?= $occ['live_in_staff_count'] ?> Live-in Staff
            </div>
        </div>

        <div class="stat-card" style="background: white; padding: 1.25rem; border-radius: 1rem; border: 1px solid var(--border-color); box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
            <div class="stat-label" style="font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600;">Daily Commuters Today</div>
            <div class="stat-value" style="font-size: 1.5rem; font-weight: 700; color: #0284c7; margin-top: 0.35rem;">
                <?= $occ['daily_active_personnel'] ?> <span style="font-size: 0.875rem; font-weight: 500;">Active</span>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem;">
                <?= $occ['daily_staff_count'] ?> Staff &bull; <?= $occ['visitors_inside_count'] ?> Visitors Inside
            </div>
        </div>

        <div class="stat-card" style="background: white; padding: 1.25rem; border-radius: 1rem; border: 1px solid var(--border-color); box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
            <div class="stat-label" style="font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600;">Registered Vehicles</div>
            <div class="stat-value" style="font-size: 1.5rem; font-weight: 700; color: var(--text-primary); margin-top: 0.35rem;">
                <?= count($vehicles) ?> <span style="font-size: 0.875rem; font-weight: 500; color: var(--text-secondary);">Decals</span>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem;">Active Gate Access Tags</div>
        </div>

        <div class="stat-card" style="background: white; padding: 1.25rem; border-radius: 1rem; border: 1px solid var(--border-color); box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
            <div class="stat-label" style="font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600;">Outstanding Arrears</div>
            <div class="stat-value" style="font-size: 1.5rem; font-weight: 700; color: <?= $totalArrears > 0 ? '#dc2626' : '#16a34a' ?>; margin-top: 0.35rem;">
                <?= formatMoney($totalArrears) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem;"><?= $totalArrears > 0 ? 'Payment Required' : 'Ledger Up to Date' ?></div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="tabs-container" style="background: white; border-radius: 1rem; border: 1px solid var(--border-color); padding: 0.5rem; margin-bottom: 1.5rem; display: flex; gap: 0.5rem; overflow-x: auto;">
        <button type="button" class="tab-btn active" onclick="showTab('tab-overview', this)" style="padding: 0.6rem 1rem; border-radius: 0.75rem; border: none; background: transparent; font-weight: 600; cursor: pointer; color: var(--text-secondary);">
            <i data-lucide="layout-dashboard" style="width: 15px; height: 15px; vertical-align: middle;"></i> Overview
        </button>
        <button type="button" class="tab-btn" onclick="showTab('tab-ownership', this)" style="padding: 0.6rem 1rem; border-radius: 0.75rem; border: none; background: transparent; font-weight: 600; cursor: pointer; color: var(--text-secondary);">
            <i data-lucide="key" style="width: 15px; height: 15px; vertical-align: middle;"></i> Ownership & Tenancy
        </button>
        <button type="button" class="tab-btn" onclick="showTab('tab-household', this)" style="padding: 0.6rem 1rem; border-radius: 0.75rem; border: none; background: transparent; font-weight: 600; cursor: pointer; color: var(--text-secondary);">
            <i data-lucide="users" style="width: 15px; height: 15px; vertical-align: middle;"></i> Household & Residents (<?= $occ['total_household_residents'] ?>)
        </button>
        <button type="button" class="tab-btn" onclick="showTab('tab-staff', this)" style="padding: 0.6rem 1rem; border-radius: 0.75rem; border: none; background: transparent; font-weight: 600; cursor: pointer; color: var(--text-secondary);">
            <i data-lucide="user-check" style="width: 15px; height: 15px; vertical-align: middle;"></i> Domestic Staff (<?= count($staffList) ?>)
        </button>
        <?php if ($commercialOccupant): ?>
        <button type="button" class="tab-btn" onclick="showTab('tab-commercial', this)" style="padding: 0.6rem 1rem; border-radius: 0.75rem; border: none; background: transparent; font-weight: 600; cursor: pointer; color: var(--text-secondary);">
            <i data-lucide="briefcase" style="width: 15px; height: 15px; vertical-align: middle;"></i> Commercial Business
        </button>
        <?php endif; ?>
        <button type="button" class="tab-btn" onclick="showTab('tab-vehicles', this)" style="padding: 0.6rem 1rem; border-radius: 0.75rem; border: none; background: transparent; font-weight: 600; cursor: pointer; color: var(--text-secondary);">
            <i data-lucide="car" style="width: 15px; height: 15px; vertical-align: middle;"></i> Vehicles (<?= count($vehicles) ?>)
        </button>
        <button type="button" class="tab-btn" onclick="showTab('tab-visitors', this)" style="padding: 0.6rem 1rem; border-radius: 0.75rem; border: none; background: transparent; font-weight: 600; cursor: pointer; color: var(--text-secondary);">
            <i data-lucide="shield-check" style="width: 15px; height: 15px; vertical-align: middle;"></i> Visitor Access
        </button>
        <button type="button" class="tab-btn" onclick="showTab('tab-billing', this)" style="padding: 0.6rem 1rem; border-radius: 0.75rem; border: none; background: transparent; font-weight: 600; cursor: pointer; color: var(--text-secondary);">
            <i data-lucide="receipt" style="width: 15px; height: 15px; vertical-align: middle;"></i> Billing Ledger (<?= count($invoices) ?>)
        </button>
        <button type="button" class="tab-btn" onclick="showTab('tab-maintenance', this)" style="padding: 0.6rem 1rem; border-radius: 0.75rem; border: none; background: transparent; font-weight: 600; cursor: pointer; color: var(--text-secondary);">
            <i data-lucide="wrench" style="width: 15px; height: 15px; vertical-align: middle;"></i> Maintenance (<?= count($tickets) ?>)
        </button>
    </div>

    <!-- TAB 1: OVERVIEW -->
    <div id="tab-overview" class="tab-content" style="display: block;">
        <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
            <div class="card" style="background: white; border-radius: 1rem; border: 1px solid var(--border-color); padding: 1.5rem;">
                <h3 style="font-size: 1.125rem; font-weight: 700; margin-bottom: 1rem;">Property & Utility Technical Details</h3>
                <table class="data-table" style="width: 100%;">
                    <tr><th style="width: 35%;">Unit Code</th><td><code><?= e($unit['unit_code']) ?></code></td></tr>
                    <tr><th>Street / Zone</th><td><?= e($unit['street_name']) ?> (<code><?= e($unit['street_code']) ?></code>)</td></tr>
                    <tr><th>Building / Block</th><td><?= e($unit['building_name']) ?> (<code><?= e($unit['building_code']) ?></code>)</td></tr>
                    <tr><th>Floor Level</th><td>Floor <?= e($unit['floor_number']) ?></td></tr>
                    <tr><th>Usage Class</th><td><span class="badge badge-info"><?= ucfirst(e($unit['usage_class'])) ?></span></td></tr>
                    <tr><th>Apartment Sub-Type</th><td><?= e($unit['sub_type']) ?> (<?= e($unit['bedrooms']) ?> Beds / <?= e($unit['bathrooms']) ?> Baths)</td></tr>
                    <tr><th>Electric Meter No.</th><td><?= !empty($unit['electric_meter_no']) ? e($unit['electric_meter_no']) : '<span class="text-muted">Not Configured</span>' ?></td></tr>
                    <tr><th>Water Meter No.</th><td><?= !empty($unit['water_meter_no']) ? e($unit['water_meter_no']) : '<span class="text-muted">Not Configured</span>' ?></td></tr>
                </table>
            </div>

            <div class="card" style="background: white; border-radius: 1rem; border: 1px solid var(--border-color); padding: 1.5rem;">
                <h3 style="font-size: 1.125rem; font-weight: 700; margin-bottom: 1rem;">Head of Household</h3>
                <?php 
                $headRes = null;
                foreach ($residents as $r) {
                    if ($r['is_head_of_household'] == 1 && $r['status'] === 'active') {
                        $headRes = $r;
                        break;
                    }
                }
                ?>
                <?php if ($headRes): ?>
                <div style="text-align: center; padding: 1rem 0;">
                    <div style="width: 64px; height: 64px; border-radius: 50%; background: var(--primary-light); color: var(--primary-color); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 700; margin: 0 auto 0.75rem;">
                        <?= strtoupper(substr($headRes['person_name'], 0, 1)) ?>
                    </div>
                    <h4 style="font-size: 1.125rem; font-weight: 700; margin: 0;"><?= e($headRes['person_name']) ?></h4>
                    <span class="badge badge-success" style="margin-top: 0.25rem;">Designated Head of Household</span>
                    <p style="font-size: 0.875rem; color: var(--text-secondary); margin-top: 0.75rem;">
                        <i data-lucide="phone" style="width: 14px; height: 14px; vertical-align: middle;"></i> <?= e($headRes['primary_phone']) ?><br>
                        <i data-lucide="mail" style="width: 14px; height: 14px; vertical-align: middle;"></i> <?= e($headRes['email'] ?: 'No email') ?>
                    </p>
                </div>
                <?php else: ?>
                <div style="text-align: center; padding: 2rem 0; color: var(--text-secondary);">
                    <i data-lucide="user-x" style="width: 36px; height: 36px; stroke-width: 1.5; margin-bottom: 0.5rem;"></i>
                    <p style="margin: 0; font-size: 0.875rem;">No active Head of Household designated for this unit.</p>
                    <a href="../residents/index.php" class="btn btn-sm btn-primary" style="margin-top: 0.75rem;">Assign Head Resident</a>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>


    <!-- TAB 2: OWNERSHIP & TENANCY -->
    <div id="tab-ownership" class="tab-content" style="display: none;">
        <div class="card" style="background: white; border-radius: 1rem; border: 1px solid var(--border-color); padding: 1.5rem; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.125rem; font-weight: 700; margin-bottom: 1rem;">Title Ownership Records</h3>
            <?php if (!empty($ownerships)): ?>
            <table class="data-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Owner Name / Entity</th>
                        <th>Type</th>
                        <th>Ownership %</th>
                        <th>Start Date</th>
                        <th>Status</th>
                        <th>Contact Phone</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($ownerships as $ow): ?>
                    <tr>
                        <td>
                            <strong><?= e($ow['owner_type'] === 'corporate' ? $ow['company_name'] : $ow['owner_person_name']) ?></strong>
                        </td>
                        <td><?= ucfirst(e($ow['owner_type'])) ?> Owner</td>
                        <td><?= e($ow['ownership_percentage']) ?>%</td>
                        <td><?= formatDate($ow['start_date']) ?></td>
                        <td>
                            <span class="badge badge-<?= $ow['is_current'] ? 'success' : 'secondary' ?>">
                                <?= $ow['is_current'] ? 'Active Title Holder' : 'Historical Owner' ?>
                            </span>
                        </td>
                        <td><?= e($ow['owner_type'] === 'corporate' ? $ow['corp_phone'] : $ow['owner_person_phone']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p class="text-muted">No ownership records registered for this unit.</p>
            <?php endif; ?>
        </div>

        <div class="card" style="background: white; border-radius: 1rem; border: 1px solid var(--border-color); padding: 1.5rem;">
            <h3 style="font-size: 1.125rem; font-weight: 700; margin-bottom: 1rem;">Active Tenancy Agreement</h3>
            <?php if ($tenancy): ?>
            <table class="data-table" style="width: 100%;">
                <tr><th style="width: 30%;">Tenant Name</th><td><strong><?= e($tenancy['tenant_name']) ?></strong></td></tr>
                <tr><th>Phone</th><td><?= e($tenancy['tenant_phone']) ?></td></tr>
                <tr><th>Email</th><td><?= e($tenancy['tenant_email']) ?></td></tr>
                <tr><th>Lease Period</th><td><?= formatDate($tenancy['lease_start_date']) ?> to <?= formatDate($tenancy['lease_end_date']) ?></td></tr>
                <tr><th>Tenancy Status</th><td><span class="badge badge-success"><?= strtoupper(e($tenancy['status'])) ?></span></td></tr>
            </table>
            <?php else: ?>
            <p class="text-muted">No active lease/tenancy agreement logged for this property.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB 3: HOUSEHOLD & RESIDENTS -->
    <div id="tab-household" class="tab-content" style="display: none;">
        <div class="card" style="background: white; border-radius: 1rem; border: 1px solid var(--border-color); padding: 1.5rem; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.125rem; font-weight: 700; margin-bottom: 1rem;">Primary & Co-Residents</h3>
            <?php if (!empty($residents)): ?>
            <table class="data-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Resident Code</th>
                        <th>Full Name</th>
                        <th>Role in Household</th>
                        <th>Category</th>
                        <th>Move-In Date</th>
                        <th>Phone Number</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($residents as $r): ?>
                    <tr>
                        <td><code><?= e($r['resident_code']) ?></code></td>
                        <td><strong><?= e($r['person_name']) ?></strong></td>
                        <td>
                            <?php if ($r['is_head_of_household'] == 1): ?>
                            <span class="badge badge-success"><i data-lucide="crown" style="width: 12px; height: 12px; vertical-align: middle;"></i> Head of Household</span>
                            <?php else: ?>
                            <span class="badge badge-info">Co-Resident</span>
                            <?php endif; ?>
                        </td>
                        <td><?= ucfirst(str_replace('_', ' ', e($r['residency_category']))) ?></td>
                        <td><?= formatDate($r['move_in_date']) ?></td>
                        <td><?= e($r['primary_phone']) ?></td>
                        <td><span class="badge badge-<?= $r['status'] === 'active' ? 'success' : 'secondary' ?>"><?= ucfirst(e($r['status'])) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p class="text-muted">No residents registered in this unit.</p>
            <?php endif; ?>
        </div>

        <div class="card" style="background: white; border-radius: 1rem; border: 1px solid var(--border-color); padding: 1.5rem;">
            <h3 style="font-size: 1.125rem; font-weight: 700; margin-bottom: 1rem;">Household Dependents & Family Members</h3>
            <?php if (!empty($dependents)): ?>
            <table class="data-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Dependent Name</th>
                        <th>Relationship to Head</th>
                        <th>Head of Household</th>
                        <th>Gender</th>
                        <th>Minor Status</th>
                        <th>Phone</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($dependents as $dep): ?>
                    <tr>
                        <td><strong><?= e($dep['person_name']) ?></strong></td>
                        <td><span class="badge badge-outline"><?= e($dep['relationship_to_head']) ?></span></td>
                        <td><?= e($dep['head_name']) ?></td>
                        <td><?= ucfirst(e($dep['gender'] ?? 'N/A')) ?></td>
                        <td><?= $dep['is_minor'] ? '<span class="badge badge-warning">Minor</span>' : '<span class="badge badge-secondary">Adult</span>' ?></td>
                        <td><?= e($dep['primary_phone'] ?: 'N/A') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p class="text-muted">No household dependents or family members registered.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB 4: DOMESTIC STAFF -->
    <div id="tab-staff" class="tab-content" style="display: none;">
        <div class="card" style="background: white; border-radius: 1rem; border: 1px solid var(--border-color); padding: 1.5rem;">
            <h3 style="font-size: 1.125rem; font-weight: 700; margin-bottom: 1rem;">Private Domestic Workers (Drivers, Cleaners, Cooks, Nannies)</h3>
            <?php if (!empty($staffList)): ?>
            <table class="data-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Staff Name</th>
                        <th>Role / Job Title</th>
                        <th>Residence Type</th>
                        <th>Gate Access Window</th>
                        <th>Employer</th>
                        <th>Guarantor & NIN</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($staffList as $st): ?>
                    <tr>
                        <td><strong><?= e($st['staff_name']) ?></strong></td>
                        <td><?= e($st['staff_role']) ?></td>
                        <td>
                            <?php if ($st['residence_type'] === 'live_in'): ?>
                            <span class="badge badge-success">Live-In Resident Staff</span>
                            <?php else: ?>
                            <span class="badge badge-info">Daily Commuter Staff</span>
                            <?php endif; ?>
                        </td>
                        <td><code><?= e($st['gate_access_start_time']) ?> - <?= e($st['gate_access_end_time']) ?></code></td>
                        <td><?= e($st['employer_name']) ?></td>
                        <td>
                            <small>
                                <strong>G:</strong> <?= e($st['guarantor_name'] ?: 'N/A') ?> (<?= e($st['guarantor_phone'] ?: 'No Phone') ?>)<br>
                                <strong>NIN:</strong> <code><?= e($st['nin_number'] ?: 'N/A') ?></code>
                            </small>
                        </td>
                        <td><span class="badge badge-<?= $st['access_status'] === 'active' ? 'success' : 'danger' ?>"><?= ucfirst(e($st['access_status'])) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p class="text-muted">No domestic staff registered for this unit.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB 5: COMMERCIAL BUSINESS -->
    <?php if ($commercialOccupant): ?>
    <div id="tab-commercial" class="tab-content" style="display: none;">
        <div class="card" style="background: white; border-radius: 1rem; border: 1px solid var(--border-color); padding: 1.5rem; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.125rem; font-weight: 700; margin-bottom: 1rem;">Commercial Outlet Profile</h3>
            <table class="data-table" style="width: 100%;">
                <tr><th style="width: 30%;">Business Name</th><td><strong><?= e($commercialOccupant['business_name']) ?></strong></td></tr>
                <tr><th>Category</th><td><?= e($commercialOccupant['business_category']) ?></td></tr>
                <tr><th>CAC RC / BN Number</th><td><code><?= e($commercialOccupant['cac_rc_number'] ?: 'N/A') ?></code></td></tr>
                <tr><th>Tax Identification No. (TIN)</th><td><code><?= e($commercialOccupant['tax_id'] ?: 'N/A') ?></code></td></tr>
                <tr><th>Official Operating Hours</th><td><?= e($commercialOccupant['operating_hours'] ?: 'Standard Business Hours') ?></td></tr>
                <tr><th>Business Phone & Email</th><td><?= e($commercialOccupant['business_phone']) ?> &bull; <?= e($commercialOccupant['business_email']) ?></td></tr>
            </table>
        </div>

        <div class="card" style="background: white; border-radius: 1rem; border: 1px solid var(--border-color); padding: 1.5rem;">
            <h3 style="font-size: 1.125rem; font-weight: 700; margin-bottom: 1rem;">Registered Commercial Employees</h3>
            <?php if (!empty($commercialEmployees)): ?>
            <table class="data-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Employee Name</th>
                        <th>Job Title</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Access Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($commercialEmployees as $ce): ?>
                    <tr>
                        <td><strong><?= e($ce['employee_name']) ?></strong></td>
                        <td><?= e($ce['job_title']) ?></td>
                        <td><?= e($ce['primary_phone']) ?></td>
                        <td><?= e($ce['email'] ?: 'N/A') ?></td>
                        <td><span class="badge badge-<?= $ce['access_status'] === 'active' ? 'success' : 'danger' ?>"><?= ucfirst(e($ce['access_status'])) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p class="text-muted">No individual commercial employees registered under this business.</p>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- TAB 6: VEHICLES -->
    <div id="tab-vehicles" class="tab-content" style="display: none;">
        <div class="card" style="background: white; border-radius: 1rem; border: 1px solid var(--border-color); padding: 1.5rem;">
            <h3 style="font-size: 1.125rem; font-weight: 700; margin-bottom: 1rem;">Registered Vehicles & Gate RFID Tags</h3>
            <?php if (!empty($vehicles)): ?>
            <table class="data-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>License Plate</th>
                        <th>Vehicle Code</th>
                        <th>Make / Model / Color</th>
                        <th>Windshield Decal</th>
                        <th>Driver / Owner</th>
                        <th>Access Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vehicles as $v): ?>
                    <tr>
                        <td><strong style="font-size: 1.1rem; color: var(--primary-color);"><?= e($v['license_plate_number']) ?></strong></td>
                        <td><code><?= e($v['vehicle_code']) ?></code></td>
                        <td><?= e($v['color']) ?> <?= e($v['make']) ?> <?= e($v['model']) ?> (<?= ucfirst(e($v['vehicle_type'])) ?>)</td>
                        <td><code><?= e($v['decal_number'] ?: 'NO-DECAL') ?></code></td>
                        <td><?= e($v['owner_name']) ?> (<?= e($v['primary_phone']) ?>)</td>
                        <td><span class="badge badge-<?= $v['access_status'] === 'active' ? 'success' : 'danger' ?>"><?= ucfirst(e($v['access_status'])) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p class="text-muted">No vehicles registered for this unit.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB 7: VISITORS -->
    <div id="tab-visitors" class="tab-content" style="display: none;">
        <div class="card" style="background: white; border-radius: 1rem; border: 1px solid var(--border-color); padding: 1.5rem; margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.125rem; font-weight: 700; margin-bottom: 1rem;">Generated Visitor Access Codes</h3>
            <?php if (!empty($visitorPasses)): ?>
            <table class="data-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Access Code</th>
                        <th>Visitor Name</th>
                        <th>Category & Type</th>
                        <th>Valid Date Range</th>
                        <th>Max Uses / Used</th>
                        <th>Pass Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($visitorPasses as $vp): ?>
                    <tr>
                        <td><strong style="font-size: 1.1rem; letter-spacing: 1px; color: var(--primary-color);"><?= e($vp['pass_code_formatted']) ?></strong></td>
                        <td><?= e($vp['visitor_name']) ?> (<?= e($vp['visitor_phone'] ?: 'No phone') ?>)</td>
                        <td><?= ucfirst(e($vp['visitor_category'])) ?> &bull; <small><?= ucfirst(e($vp['pass_type'] ?? 'single_entry')) ?></small></td>
                        <td><?= formatDate($vp['valid_from_date'] ?? $vp['visit_date']) ?> to <?= formatDate($vp['valid_until_date'] ?? $vp['visit_date']) ?></td>
                        <td><?= e($vp['use_count'] ?? 0) ?> / <?= e($vp['max_uses'] ?? 1) ?> Uses</td>
                        <td><span class="badge badge-<?= $vp['pass_status'] === 'active' ? 'success' : 'secondary' ?>"><?= strtoupper(e($vp['pass_status'])) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p class="text-muted">No visitor passes generated for this unit.</p>
            <?php endif; ?>
        </div>

        <div class="card" style="background: white; border-radius: 1rem; border: 1px solid var(--border-color); padding: 1.5rem;">
            <h3 style="font-size: 1.125rem; font-weight: 700; margin-bottom: 1rem;">Recent Gate Entries & Exits</h3>
            <?php if (!empty($gateVisits)): ?>
            <table class="data-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Visitor Name</th>
                        <th>Entry Timestamp</th>
                        <th>Exit Timestamp</th>
                        <th>Entry Type</th>
                        <th>Gate Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($gateVisits as $gv): ?>
                    <tr>
                        <td><strong><?= e($gv['visitor_name']) ?></strong> (<?= e($gv['visitor_phone'] ?: 'N/A') ?>)</td>
                        <td><?= formatDate($gv['entry_timestamp'], 'M j, Y g:i A') ?></td>
                        <td><?= !empty($gv['exit_timestamp']) ? formatDate($gv['exit_timestamp'], 'M j, Y g:i A') : '<span class="text-warning font-weight-bold">INSIDE ESTATE</span>' ?></td>
                        <td><span class="badge badge-outline"><?= ucfirst(str_replace('_', ' ', e($gv['entry_type']))) ?></span></td>
                        <td>
                            <?php if (empty($gv['exit_timestamp'])): ?>
                            <span class="badge badge-warning">Currently Inside</span>
                            <?php else: ?>
                            <span class="badge badge-secondary">Checked Out</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p class="text-muted">No recent gate clearance activity recorded for this unit.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB 8: BILLING LEDGER -->
    <div id="tab-billing" class="tab-content" style="display: none;">
        <div class="card" style="background: white; border-radius: 1rem; border: 1px solid var(--border-color); padding: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3 style="font-size: 1.125rem; font-weight: 700; margin: 0;">Property Financial Ledger & Invoices</h3>
                <div style="font-size: 1.125rem; font-weight: 700; color: <?= $totalArrears > 0 ? '#dc2626' : '#16a34a' ?>;">
                    Total Outstanding: <?= formatMoney($totalArrears) ?>
                </div>
            </div>
            <?php if (!empty($invoices)): ?>
            <table class="data-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Invoice No</th>
                        <th>Title / Description</th>
                        <th>Payer Name</th>
                        <th>Net Amount</th>
                        <th>Due Date</th>
                        <th>Status</th>
                        <th>Batch Ref</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($invoices as $inv): ?>
                    <tr>
                        <td><code><?= e($inv['invoice_number']) ?></code></td>
                        <td><strong><?= e($inv['title']) ?></strong></td>
                        <td><?= e($inv['payer_name'] ?: 'Unassigned Payer') ?></td>
                        <td><strong><?= formatMoney($inv['net_amount']) ?></strong></td>
                        <td><?= formatDate($inv['due_date']) ?></td>
                        <td>
                            <span class="badge badge-<?= $inv['status'] === 'paid' ? 'success' : ($inv['status'] === 'partial' ? 'warning' : 'danger') ?>">
                                <?= strtoupper(e($inv['status'])) ?>
                            </span>
                        </td>
                        <td><code><?= e($inv['batch_reference'] ?: 'MANUAL') ?></code></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p class="text-muted">No billing invoices recorded for this unit.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB 9: MAINTENANCE -->
    <div id="tab-maintenance" class="tab-content" style="display: none;">
        <div class="card" style="background: white; border-radius: 1rem; border: 1px solid var(--border-color); padding: 1.5rem;">
            <h3 style="font-size: 1.125rem; font-weight: 700; margin-bottom: 1rem;">Maintenance Tickets & Repair Work Orders</h3>
            <?php if (!empty($tickets)): ?>
            <table class="data-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Ticket Code</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>Priority</th>
                        <th>Reporter</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $t): ?>
                    <tr>
                        <td><code><?= e($t['ticket_code']) ?></code></td>
                        <td><strong><?= e($t['title']) ?></strong></td>
                        <td><?= ucfirst(e($t['category'])) ?></td>
                        <td><span class="badge badge-<?= $t['priority'] === 'emergency' || $t['priority'] === 'high' ? 'danger' : 'info' ?>"><?= strtoupper(e($t['priority'])) ?></span></td>
                        <td><?= e($t['reporter_name']) ?></td>
                        <td><span class="badge badge-<?= $t['status'] === 'resolved' || $t['status'] === 'closed' ? 'success' : 'warning' ?>"><?= strtoupper(str_replace('_', ' ', e($t['status']))) ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <p class="text-muted">No maintenance tickets logged for this unit.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function showTab(tabId, btn) {
    document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
    document.querySelectorAll('.tab-btn').forEach(el => {
        el.style.background = 'transparent';
        el.style.color = 'var(--text-secondary)';
    });
    document.getElementById(tabId).style.display = 'block';
    btn.style.background = 'var(--primary-light)';
    btn.style.color = 'var(--primary-color)';
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
