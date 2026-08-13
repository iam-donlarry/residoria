<?php
// modules/properties/building_view.php - Building Multi-Unit Occupancy Matrix
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/helpers.php';

requirePermission('properties.view');

$db = Database::getInstance();
$building_id = decodeId($_GET['id'] ?? 0);

if ($building_id <= 0) {
    // If no building specified, grab the first building available
    $firstBuilding = $db->fetchOne("SELECT id FROM buildings_blocks ORDER BY id ASC LIMIT 1");
    if ($firstBuilding) {
        $building_id = intval($firstBuilding['id']);
    } else {
        die("No buildings registered in the system.");
    }
}

// 1. Fetch Building Details
$building = $db->fetchOne("
    SELECT b.*, z.name as street_name, z.code as street_code
    FROM buildings_blocks b
    JOIN zones_streets z ON b.zone_id = z.id
    WHERE b.id = ?
", [$building_id]);

if (!$building) {
    die("Building block not found.");
}

// 2. Fetch All Buildings for Selector Dropdown
$allBuildings = $db->fetchAll("
    SELECT b.id, b.name, b.building_code, z.name as street_name
    FROM buildings_blocks b
    JOIN zones_streets z ON b.zone_id = z.id
    ORDER BY z.name ASC, b.name ASC
");

// 3. Fetch Building Matrix Units Query (Optimized Single Query)
$units = $db->fetchAll("
    SELECT 
        u.id AS unit_id,
        u.unit_number,
        u.unit_code,
        u.floor_number,
        u.usage_class,
        u.sub_type,
        u.status AS unit_status,
        CONCAT(p_head.first_name, ' ', p_head.last_name) AS head_name,
        p_head.primary_phone AS head_phone,
        (
            SELECT COUNT(*) FROM residents r 
            WHERE r.unit_id = u.id AND r.status = 'active'
        ) + (
            SELECT COUNT(*) FROM household_members hm 
            JOIN residents r2 ON hm.primary_resident_id = r2.id 
            WHERE (hm.unit_id = u.id OR r2.unit_id = u.id) AND r2.status = 'active'
        ) AS household_residents_count,
        (
            SELECT COUNT(*) FROM domestic_staff ds 
            WHERE ds.unit_id = u.id AND ds.access_status = 'active' AND ds.residence_type = 'live_in'
        ) AS live_in_staff_count,
        (
            SELECT COUNT(*) FROM domestic_staff ds 
            WHERE ds.unit_id = u.id AND ds.access_status = 'active' AND ds.residence_type = 'daily'
        ) AS daily_staff_count,
        (
            SELECT COUNT(*) FROM vehicles v 
            WHERE v.unit_id = u.id AND v.access_status = 'active'
        ) AS vehicles_count,
        COALESCE((
            SELECT SUM(net_amount) FROM invoices inv 
            WHERE inv.unit_id = u.id AND inv.status IN ('unpaid', 'partial')
        ), 0.00) AS arrears_balance
    FROM property_units u
    LEFT JOIN residents r_head ON u.id = r_head.unit_id AND r_head.status = 'active' AND r_head.is_head_of_household = 1
    LEFT JOIN persons p_head ON r_head.person_id = p_head.id
    WHERE u.building_id = ?
    ORDER BY u.floor_number ASC, u.unit_number ASC
", [$building_id]);

// Calculate Building Aggregates
$totalUnits = count($units);
$occupiedUnits = 0;
$vacantUnits = 0;
$totalBuildingResidents = 0;
$totalLiveInStaff = 0;
$totalBuildingArrears = 0;

foreach ($units as $u) {
    if ($u['unit_status'] === 'occupied') {
        $occupiedUnits++;
    } elseif ($u['unit_status'] === 'vacant_available') {
        $vacantUnits++;
    }
    $totalBuildingResidents += (int)$u['household_residents_count'];
    $totalLiveInStaff += (int)$u['live_in_staff_count'];
    $totalBuildingArrears += (float)$u['arrears_balance'];
}

$pageTitle = "Building Matrix - " . e($building['name']);
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="content-wrapper">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <h1 class="page-title" style="margin: 0; font-size: 1.75rem; font-weight: 700;">Building Occupancy Matrix</h1>
                <span class="badge badge-info"><?= e($building['building_code']) ?></span>
            </div>
            <p class="text-subtitle" style="color: var(--text-secondary); margin-top: 0.25rem;">
                <i data-lucide="building-2" style="width: 14px; height: 14px; vertical-align: middle;"></i>
                <?= e($building['street_name']) ?> &bull; <strong><?= e($building['name']) ?></strong> &bull; Total Floors: <?= e($building['total_floors']) ?>
            </p>
        </div>
        <div style="display: flex; gap: 0.75rem; align-items: center;">
            <label style="font-weight: 600; font-size: 0.875rem;">Switch Building:</label>
            <select onchange="window.location.href='<?= BASE_URL ?>properties/building_view?id=' + this.value" class="form-control" style="width: auto;">
                <?php foreach ($allBuildings as $bOption): ?>
                <option value="<?= $bOption['id'] ?>" <?= $bOption['id'] == $building_id ? 'selected' : '' ?>>
                    <?= e($bOption['street_name']) ?> - <?= e($bOption['name']) ?> (<?= e($bOption['building_code']) ?>)
                </option>
                <?php endforeach; ?>
            </select>
            <a href="<?= BASE_URL ?>properties" class="btn btn-secondary">
                <i data-lucide="arrow-left" style="width: 16px; height: 16px;"></i> All Properties
            </a>
        </div>
    </div>

    <!-- Building Summary Cards -->
    <div class="stats-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1.25rem; margin-bottom: 1.5rem;">
        <div class="stat-card" style="background: white; padding: 1.25rem; border-radius: 1rem; border: 1px solid var(--border-color); box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
            <div class="stat-label" style="font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600;">Total Building Units</div>
            <div class="stat-value" style="font-size: 1.5rem; font-weight: 700; margin-top: 0.35rem;"><?= $totalUnits ?> Units</div>
            <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem;"><?= $occupiedUnits ?> Occupied &bull; <?= $vacantUnits ?> Vacant</div>
        </div>

        <div class="stat-card" style="background: white; padding: 1.25rem; border-radius: 1rem; border: 1px solid var(--border-color); box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
            <div class="stat-label" style="font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600;">Building Population</div>
            <div class="stat-value" style="font-size: 1.5rem; font-weight: 700; color: var(--primary-color); margin-top: 0.35rem;"><?= $totalBuildingResidents + $totalLiveInStaff ?> Persons</div>
            <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem;"><?= $totalBuildingResidents ?> Residents &bull; <?= $totalLiveInStaff ?> Live-in Staff</div>
        </div>

        <div class="stat-card" style="background: white; padding: 1.25rem; border-radius: 1rem; border: 1px solid var(--border-color); box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
            <div class="stat-label" style="font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600;">Occupancy Rate</div>
            <div class="stat-value" style="font-size: 1.5rem; font-weight: 700; color: #16a34a; margin-top: 0.35rem;">
                <?= $totalUnits > 0 ? round(($occupiedUnits / $totalUnits) * 100) : 0 ?>%
            </div>
            <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem;">Active Residential Rate</div>
        </div>

        <div class="stat-card" style="background: white; padding: 1.25rem; border-radius: 1rem; border: 1px solid var(--border-color); box-shadow: 0 2px 4px rgba(0,0,0,0.02);">
            <div class="stat-label" style="font-size: 0.75rem; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.05em; font-weight: 600;">Total Building Arrears</div>
            <div class="stat-value" style="font-size: 1.5rem; font-weight: 700; color: <?= $totalBuildingArrears > 0 ? '#dc2626' : '#16a34a' ?>; margin-top: 0.35rem;">
                <?= formatMoney($totalBuildingArrears) ?>
            </div>
            <div style="font-size: 0.75rem; color: var(--text-secondary); margin-top: 0.25rem;">Unpaid Building Invoices</div>
        </div>
    </div>

    <!-- Vertical Building Occupancy Matrix Table -->
    <div class="card" style="background: white; border-radius: 1rem; border: 1px solid var(--border-color); padding: 1.5rem;">
        <h3 style="font-size: 1.125rem; font-weight: 700; margin-bottom: 1.25rem;">Building Unit Matrix (Sorted by Floor & Unit)</h3>
        
        <?php if (!empty($units)): ?>
        <div class="table-responsive">
            <table class="data-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Floor</th>
                        <th>Unit No.</th>
                        <th>Unit Code</th>
                        <th>Usage</th>
                        <th>Head of Household</th>
                        <th>Residents</th>
                        <th>Live-in Staff</th>
                        <th>Perm. Occ.</th>
                        <th>Daily Staff</th>
                        <th>Vehicles</th>
                        <th>Arrears</th>
                        <th>Status</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($units as $u): ?>
                    <?php $permOcc = (int)$u['household_residents_count'] + (int)$u['live_in_staff_count']; ?>
                    <tr>
                        <td><strong>Floor <?= e($u['floor_number']) ?></strong></td>
                        <td><strong><?= e($u['unit_number']) ?></strong></td>
                        <td><code><?= e($u['unit_code']) ?></code></td>
                        <td><span class="badge badge-info"><?= ucfirst(e($u['usage_class'])) ?></span></td>
                        <td>
                            <?php if (!empty($u['head_name'])): ?>
                            <strong><?= e($u['head_name']) ?></strong><br>
                            <small class="text-muted"><?= e($u['head_phone']) ?></small>
                            <?php else: ?>
                            <span class="text-muted">&mdash; Vacant / No Head &mdash;</span>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= e($u['household_residents_count']) ?></strong></td>
                        <td><?= e($u['live_in_staff_count']) ?></td>
                        <td><strong style="color: var(--primary-color); font-size: 1.05rem;"><?= $permOcc ?></strong></td>
                        <td><?= e($u['daily_staff_count']) ?></td>
                        <td><?= e($u['vehicles_count']) ?></td>
                        <td>
                            <strong style="color: <?= $u['arrears_balance'] > 0 ? '#dc2626' : '#16a34a' ?>;">
                                <?= formatMoney($u['arrears_balance']) ?>
                            </strong>
                        </td>
                        <td>
                            <span class="badge badge-<?= $u['unit_status'] === 'occupied' ? 'success' : ($u['unit_status'] === 'vacant_available' ? 'info' : 'warning') ?>">
                                <?= strtoupper(str_replace('_', ' ', e($u['unit_status']))) ?>
                            </span>
                        </td>
                        <td style="text-align: right;">
                            <div style="display: flex; gap: 0.35rem; justify-content: flex-end;">
                                <a href="<?= BASE_URL ?>properties/view?id=<?= encodeId($u['unit_id']) ?>" class="btn btn-sm btn-primary" title="Inspect 360° Profile">
                                    <i data-lucide="eye" style="width: 14px; height: 14px;"></i> Inspect 360°
                                </a>
                                <button type="button" class="btn btn-sm btn-outline" onclick="openPropertyDrawer(<?= $u['unit_id'] ?>)" title="Quick Summary">
                                    <i data-lucide="sidebar" style="width: 14px; height: 14px;"></i> Quick
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p class="text-muted">No property units registered in this building block yet.</p>
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/../../includes/property_drawer.php'; ?>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
