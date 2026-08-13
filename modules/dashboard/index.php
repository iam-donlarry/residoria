<?php
// modules/dashboard/index.php - Standalone Estate Operations Dashboard
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth_check.php';

$db = Database::getInstance();

// 1. Operational Metrics
$totalUnits = $db->fetchOne("SELECT COUNT(*) as c FROM property_units")['c'] ?? 0;
$occupiedUnits = $db->fetchOne("SELECT COUNT(*) as c FROM property_units WHERE status = 'occupied'")['c'] ?? 0;
$occupancyRate = ($totalUnits > 0) ? round(($occupiedUnits / $totalUnits) * 100, 1) : 0;

$totalPersons = $db->fetchOne("SELECT COUNT(*) as c FROM persons")['c'] ?? 0;
$activeResidents = $db->fetchOne("SELECT COUNT(*) as c FROM residents WHERE status = 'active'")['c'] ?? 0;
$commercialCount = $db->fetchOne("SELECT COUNT(*) as c FROM commercial_occupants WHERE status = 'active'")['c'] ?? 0;

$totalInvoiced = $db->fetchOne("SELECT SUM(net_amount) as s FROM invoices")['s'] ?? 0.00;
$totalCollected = $db->fetchOne("SELECT SUM(amount) as s FROM payments WHERE status = 'successful'")['s'] ?? 0.00;
$outstandingArrears = $db->fetchOne("SELECT SUM(net_amount) as s FROM invoices WHERE status IN ('unpaid', 'partial')")['s'] ?? 0.00;

$todayVisitors = $db->fetchOne("SELECT COUNT(*) as c FROM visitor_passes WHERE visit_date = CURDATE()")['c'] ?? 0;
$todayGateLogs = $db->fetchOne("SELECT COUNT(*) as c FROM gate_logs WHERE DATE(entry_timestamp) = CURDATE()")['c'] ?? 0;
$openTickets = $db->fetchOne("SELECT COUNT(*) as c FROM maintenance_tickets WHERE status NOT IN ('resolved', 'closed')")['c'] ?? 0;

// Recent Gate Logs
$recentGateLogs = $db->fetchAll("SELECT gl.*, u.unit_number, b.name as building_name 
                                 FROM gate_logs gl 
                                 JOIN property_units u ON gl.unit_id = u.id 
                                 JOIN buildings_blocks b ON u.building_id = b.id 
                                 ORDER BY gl.entry_timestamp DESC LIMIT 5");

// Recent Invoices
$recentInvoices = $db->fetchAll("SELECT inv.*, u.unit_number, b.name as building_name 
                                 FROM invoices inv 
                                 JOIN property_units u ON inv.unit_id = u.id 
                                 JOIN buildings_blocks b ON u.building_id = b.id 
                                 ORDER BY inv.created_at DESC LIMIT 5");

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div style="margin-bottom: 1.5rem;">
    <h1 style="font-size: 1.75rem; font-weight: 700; color: var(--text-primary); margin: 0; display: flex; align-items: center; gap: 0.5rem;">
        Estate Command Dashboard
    </h1>
    <p style="color: var(--text-muted); font-size: 0.95rem; margin: 0.25rem 0 0;">Real-time operational summary, property occupancy, billing ledgers, and gate security metrics</p>
</div>

<!-- KPI Cards Grid -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
    <div class="card" style="background: var(--card-bg); border-radius: 12px; padding: 1.25rem; border: 1px solid var(--border-color); box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase;">Occupancy Rate</span>
            <i data-lucide="building" style="color: var(--primary); width: 22px; height: 22px;"></i>
        </div>
        <div style="font-size: 2rem; font-weight: 800; color: var(--text-primary); margin: 0.5rem 0 0.2rem;"><?= $occupancyRate ?>%</div>
        <div style="font-size: 0.8rem; color: var(--text-muted);"><?= $occupiedUnits ?> of <?= $totalUnits ?> Units Occupied</div>
    </div>

    <div class="card" style="background: var(--card-bg); border-radius: 12px; padding: 1.25rem; border: 1px solid var(--border-color); box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase;">Active Residents</span>
            <i data-lucide="users" style="color: #3b82f6; width: 22px; height: 22px;"></i>
        </div>
        <div style="font-size: 2rem; font-weight: 800; color: var(--text-primary); margin: 0.5rem 0 0.2rem;"><?= $activeResidents ?></div>
        <div style="font-size: 0.8rem; color: var(--text-muted);"><?= $totalPersons ?> Registered Persons</div>
    </div>

    <div class="card" style="background: var(--card-bg); border-radius: 12px; padding: 1.25rem; border: 1px solid var(--border-color); box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase;">Revenue Collected</span>
            <i data-lucide="wallet" style="color: #10b981; width: 22px; height: 22px;"></i>
        </div>
        <div style="font-size: 1.6rem; font-weight: 800; color: #10b981; margin: 0.5rem 0 0.2rem;"><?= formatMoney($totalCollected) ?></div>
        <div style="font-size: 0.8rem; color: var(--danger);">Arrears: <?= formatMoney($outstandingArrears) ?></div>
    </div>

    <div class="card" style="background: var(--card-bg); border-radius: 12px; padding: 1.25rem; border: 1px solid var(--border-color); box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase;">Today's Gate Clearance</span>
            <i data-lucide="shield-check" style="color: #f59e0b; width: 22px; height: 22px;"></i>
        </div>
        <div style="font-size: 2rem; font-weight: 800; color: var(--text-primary); margin: 0.5rem 0 0.2rem;"><?= $todayGateLogs ?></div>
        <div style="font-size: 0.8rem; color: var(--text-muted);"><?= $todayVisitors ?> Visitor Passes Generated</div>
    </div>

    <div class="card" style="background: var(--card-bg); border-radius: 12px; padding: 1.25rem; border: 1px solid var(--border-color); box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <span style="font-size: 0.85rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase;">Open Maintenance</span>
            <i data-lucide="wrench" style="color: #ef4444; width: 22px; height: 22px;"></i>
        </div>
        <div style="font-size: 2rem; font-weight: 800; color: var(--danger); margin: 0.5rem 0 0.2rem;"><?= $openTickets ?></div>
        <div style="font-size: 0.8rem; color: var(--text-muted);">Active Repair Work Orders</div>
    </div>
</div>

<!-- Quick Action Shortcuts -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
    <a href="<?= BASE_URL ?>people" class="btn btn-secondary" style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.85rem; font-weight: 600;">
        <i data-lucide="user-plus"></i> Persons Directory
    </a>
    <a href="<?= BASE_URL ?>visitors" class="btn btn-secondary" style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.85rem; font-weight: 600;">
        <i data-lucide="qr-code"></i> Visitor Access Code
    </a>
    <a href="<?= BASE_URL ?>billing" class="btn btn-secondary" style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.85rem; font-weight: 600;">
        <i data-lucide="receipt"></i> Billing & Invoices
    </a>
    <a href="<?= BASE_URL ?>gate" class="btn btn-primary" style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.85rem; font-weight: 700;">
        <i data-lucide="shield-alert"></i> Gate Clearance Portal
    </a>
</div>

<!-- Recent Activity Grid -->
<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
    <!-- Today's Gate Entries -->
    <div class="data-table-wrapper">
        <div class="table-toolbar" style="display: flex; justify-content: space-between; align-items: center;">
            <h3 style="font-size: 1rem; font-weight: 700; margin: 0;">Recent Gate Clearances</h3>
            <a href="<?= BASE_URL ?>gate" style="font-size: 0.85rem; font-weight: 600; color: var(--primary);">View All →</a>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Visitor Name</th>
                    <th>Destination Unit</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($recentGateLogs)): ?>
                    <?php foreach ($recentGateLogs as $gl): ?>
                    <tr>
                        <td style="font-weight: 600; color: var(--primary);"><?= formatDate($gl['entry_timestamp'], 'H:i A') ?></td>
                        <td style="font-weight: 700;"><?= e($gl['visitor_name']) ?></td>
                        <td><?= e($gl['building_name']) ?> - Unit <?= e($gl['unit_number']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="3" class="empty-state">No gate entries logged today.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Recent Invoices -->
    <div class="data-table-wrapper">
        <div class="table-toolbar" style="display: flex; justify-content: space-between; align-items: center;">
            <h3 style="font-size: 1rem; font-weight: 700; margin: 0;">Recent Invoices Issued</h3>
            <a href="<?= BASE_URL ?>billing" style="font-size: 0.85rem; font-weight: 600; color: var(--primary);">View All →</a>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Invoice No.</th>
                    <th>Unit</th>
                    <th>Net Amount</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($recentInvoices)): ?>
                    <?php foreach ($recentInvoices as $inv): ?>
                    <tr>
                        <td style="font-weight: 700; color: var(--primary);"><?= e($inv['invoice_number']) ?></td>
                        <td><?= e($inv['building_name']) ?> - Unit <?= e($inv['unit_number']) ?></td>
                        <td style="font-weight: 700;"><?= formatMoney($inv['net_amount']) ?></td>
                        <td>
                            <span class="badge <?= $inv['status'] === 'paid' ? 'badge-success' : 'badge-danger' ?>">
                                <?= e(ucfirst($inv['status'])) ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" class="empty-state">No recent invoices found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
