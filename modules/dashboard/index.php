<?php
// modules/dashboard/index.php - Standalone Estate Operations Dashboard & Analytics Command Center
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/helpers.php';

$db = Database::getInstance();

// 1. Core Operational Metrics
$totalUnits = $db->fetchOne("SELECT COUNT(*) as c FROM property_units")['c'] ?? 0;
$occupiedUnits = $db->fetchOne("SELECT COUNT(*) as c FROM property_units WHERE status = 'occupied'")['c'] ?? 0;
$vacantUnits = $db->fetchOne("SELECT COUNT(*) as c FROM property_units WHERE status = 'vacant_available'")['c'] ?? 0;
$maintenanceUnits = $db->fetchOne("SELECT COUNT(*) as c FROM property_units WHERE status = 'under_maintenance'")['c'] ?? 0;
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

// 2. Chart Dataset 1: 6-Month Financial Collections & Invoiced Revenue Trend
$monthlyFinancials = [];
for ($i = 5; $i >= 0; $i--) {
    $monthKey = date('Y-m', strtotime("-$i months"));
    $monthName = date('M Y', strtotime("-$i months"));
    
    $invAmt = $db->fetchOne("
        SELECT SUM(net_amount) as s 
        FROM invoices 
        WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
    ", [$monthKey])['s'] ?? 0.00;

    $payAmt = $db->fetchOne("
        SELECT SUM(amount) as s 
        FROM payments 
        WHERE status = 'successful' AND DATE_FORMAT(transaction_date, '%Y-%m') = ?
    ", [$monthKey])['s'] ?? 0.00;

    $monthlyFinancials[] = [
        'month' => $monthName,
        'invoiced' => (float)$invAmt,
        'collected' => (float)$payAmt
    ];
}

// 3. Chart Dataset 2: Property Occupancy & Unit Status Breakdown
$occupancyBreakdown = [
    'Occupied Residential' => $db->fetchOne("SELECT COUNT(*) as c FROM property_units WHERE status = 'occupied' AND usage_class = 'residential'")['c'] ?? 0,
    'Occupied Commercial'  => $db->fetchOne("SELECT COUNT(*) as c FROM property_units WHERE status = 'occupied' AND usage_class = 'commercial'")['c'] ?? 0,
    'Vacant Available'     => $vacantUnits,
    'Under Maintenance'    => $maintenanceUnits,
    'Other / Reserved'     => $db->fetchOne("SELECT COUNT(*) as c FROM property_units WHERE status NOT IN ('occupied', 'vacant_available', 'under_maintenance')")['c'] ?? 0
];

// 4. Chart Dataset 3: Today's Hourly Security Gate Traffic Density (24 Hours)
$hourlyGateData = array_fill(0, 24, 0);
$gateRaw = $db->fetchAll("
    SELECT HOUR(entry_timestamp) as hr, COUNT(*) as cnt 
    FROM gate_logs 
    WHERE DATE(entry_timestamp) = CURDATE() 
    GROUP BY hr
");
foreach ($gateRaw as $gr) {
    $hourlyGateData[(int)$gr['hr']] = (int)$gr['cnt'];
}

// 5. Chart Dataset 4: Maintenance Categories Breakdown
$maintCategories = $db->fetchAll("
    SELECT category, COUNT(*) as total_tickets,
           SUM(CASE WHEN status IN ('resolved', 'closed') THEN 1 ELSE 0 END) as resolved_tickets
    FROM maintenance_tickets 
    GROUP BY category
    ORDER BY total_tickets DESC
");

// 6. Recent Activity Tables
$recentGateLogs = $db->fetchAll("
    SELECT gl.*, u.unit_number, u.unit_code, b.name as building_name 
    FROM gate_logs gl 
    JOIN property_units u ON gl.unit_id = u.id 
    JOIN buildings_blocks b ON u.building_id = b.id 
    ORDER BY gl.entry_timestamp DESC LIMIT 5
");

$recentInvoices = $db->fetchAll("
    SELECT inv.*, u.unit_number, u.unit_code, b.name as building_name 
    FROM invoices inv 
    JOIN property_units u ON inv.unit_id = u.id 
    JOIN buildings_blocks b ON u.building_id = b.id 
    ORDER BY inv.created_at DESC LIMIT 5
");

$pageTitle = "Estate Operations Command & Analytics Portal";
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<!-- Include Chart.js v4.4 via CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>

<div class="content-wrapper">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h1 class="page-title" style="margin: 0; font-size: 1.75rem; font-weight: 700;">Estate Command Dashboard</h1>
            <p class="text-subtitle" style="color: var(--text-secondary); margin-top: 0.25rem;">
                Real-time operational summary, visual analytics, financial ledgers, and gate security metrics.
            </p>
        </div>
    </div>

    <!-- Top KPI Cards Grid -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(210px, 1fr)); gap: 1.25rem; margin-bottom: 2rem;">
        <div class="card" style="background: white; border-radius: 1rem; padding: 1.25rem; border: 1px solid var(--border-color); box-shadow: 0 2px 6px rgba(0,0,0,0.03);">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 0.75rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px;">Occupancy Rate</span>
                <div style="width: 36px; height: 36px; border-radius: 0.5rem; background: var(--primary-light); color: var(--primary-color); display: flex; align-items: center; justify-content: center;">
                    <i data-lucide="building" style="width: 20px; height: 20px;"></i>
                </div>
            </div>
            <div style="font-size: 1.85rem; font-weight: 800; color: var(--text-primary); margin: 0.5rem 0 0.2rem;"><?= $occupancyRate ?>%</div>
            <div style="font-size: 0.8125rem; color: var(--text-secondary);"><?= $occupiedUnits ?> of <?= $totalUnits ?> Units Occupied</div>
        </div>

        <div class="card" style="background: white; border-radius: 1rem; padding: 1.25rem; border: 1px solid var(--border-color); box-shadow: 0 2px 6px rgba(0,0,0,0.03);">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 0.75rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px;">Active Residents</span>
                <div style="width: 36px; height: 36px; border-radius: 0.5rem; background: #e0f2fe; color: #0284c7; display: flex; align-items: center; justify-content: center;">
                    <i data-lucide="users" style="width: 20px; height: 20px;"></i>
                </div>
            </div>
            <div style="font-size: 1.85rem; font-weight: 800; color: var(--text-primary); margin: 0.5rem 0 0.2rem;"><?= $activeResidents ?></div>
            <div style="font-size: 0.8125rem; color: var(--text-secondary);"><?= $totalPersons ?> Registered Persons</div>
        </div>

        <div class="card" style="background: white; border-radius: 1rem; padding: 1.25rem; border: 1px solid var(--border-color); box-shadow: 0 2px 6px rgba(0,0,0,0.03);">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 0.75rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px;">Revenue Collected</span>
                <div style="width: 36px; height: 36px; border-radius: 0.5rem; background: #dcfce7; color: #16a34a; display: flex; align-items: center; justify-content: center;">
                    <i data-lucide="wallet" style="width: 20px; height: 20px;"></i>
                </div>
            </div>
            <div style="font-size: 1.5rem; font-weight: 800; color: #16a34a; margin: 0.5rem 0 0.2rem;"><?= formatMoney($totalCollected) ?></div>
            <div style="font-size: 0.8125rem; color: #dc2626;">Arrears: <?= formatMoney($outstandingArrears) ?></div>
        </div>

        <div class="card" style="background: white; border-radius: 1rem; padding: 1.25rem; border: 1px solid var(--border-color); box-shadow: 0 2px 6px rgba(0,0,0,0.03);">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 0.75rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px;">Today's Gate Traffic</span>
                <div style="width: 36px; height: 36px; border-radius: 0.5rem; background: #fef3c7; color: #d97706; display: flex; align-items: center; justify-content: center;">
                    <i data-lucide="shield-check" style="width: 20px; height: 20px;"></i>
                </div>
            </div>
            <div style="font-size: 1.85rem; font-weight: 800; color: var(--text-primary); margin: 0.5rem 0 0.2rem;"><?= $todayGateLogs ?></div>
            <div style="font-size: 0.8125rem; color: var(--text-secondary);"><?= $todayVisitors ?> Passes Active Today</div>
        </div>

        <div class="card" style="background: white; border-radius: 1rem; padding: 1.25rem; border: 1px solid var(--border-color); box-shadow: 0 2px 6px rgba(0,0,0,0.03);">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 0.75rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; letter-spacing: 0.5px;">Open Maintenance</span>
                <div style="width: 36px; height: 36px; border-radius: 0.5rem; background: #fee2e2; color: #dc2626; display: flex; align-items: center; justify-content: center;">
                    <i data-lucide="wrench" style="width: 20px; height: 20px;"></i>
                </div>
            </div>
            <div style="font-size: 1.85rem; font-weight: 800; color: #dc2626; margin: 0.5rem 0 0.2rem;"><?= $openTickets ?></div>
            <div style="font-size: 0.8125rem; color: var(--text-secondary);">Active Work Orders</div>
        </div>
    </div>

    <!-- Quick Action Shortcuts -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 2rem;">
        <a href="<?= BASE_URL ?>people" class="btn btn-secondary" style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.8rem; font-weight: 600;">
            <i data-lucide="user-plus" style="width: 16px; height: 16px;"></i> Persons Directory
        </a>
        <a href="<?= BASE_URL ?>visitors" class="btn btn-secondary" style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.8rem; font-weight: 600;">
            <i data-lucide="qr-code" style="width: 16px; height: 16px;"></i> Visitor Access Code
        </a>
        <a href="<?= BASE_URL ?>billing" class="btn btn-secondary" style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.8rem; font-weight: 600;">
            <i data-lucide="receipt" style="width: 16px; height: 16px;"></i> Billing & Invoices
        </a>
        <a href="<?= BASE_URL ?>gate" class="btn btn-primary" style="display: flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.8rem; font-weight: 700;">
            <i data-lucide="shield-alert" style="width: 16px; height: 16px;"></i> Gate Clearance Portal
        </a>
    </div>

    <!-- ANALYTICS CHARTS GRID 1 -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; width: 100%; min-width: 0;">
        <!-- Chart 1: 6-Month Financial Collections & Invoiced Trend -->
        <div class="card" style="background: white; border-radius: 1rem; border: 1px solid var(--border-color); padding: 1.5rem; min-width: 0; max-width: 100%; overflow: hidden;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <div>
                    <h3 style="font-size: 1.1rem; font-weight: 700; margin: 0; color: var(--text-primary);">6-Month Financial Revenue & Collections Trend</h3>
                    <p style="font-size: 0.8125rem; color: var(--text-secondary); margin: 0.15rem 0 0;">Monthly comparison of invoiced service charges vs. actual payment receipts</p>
                </div>
                <span class="badge badge-success">FINANCIAL HEALTH</span>
            </div>
            <div style="height: 280px; position: relative; width: 100%; min-width: 0; overflow: hidden;">
                <canvas id="financialTrendChart"></canvas>
            </div>
        </div>

        <!-- Chart 2: Property Occupancy Breakdown -->
        <div class="card" style="background: white; border-radius: 1rem; border: 1px solid var(--border-color); padding: 1.5rem; min-width: 0; max-width: 100%; overflow: hidden;">
            <div style="margin-bottom: 1rem;">
                <h3 style="font-size: 1.1rem; font-weight: 700; margin: 0; color: var(--text-primary);">Property Unit Distribution</h3>
                <p style="font-size: 0.8125rem; color: var(--text-secondary); margin: 0.15rem 0 0;">Capacity utilization across residential, commercial, & vacant units</p>
            </div>
            <div style="height: 280px; position: relative; display: flex; align-items: center; justify-content: center; width: 100%; min-width: 0; overflow: hidden;">
                <canvas id="occupancyDoughnutChart"></canvas>
            </div>
        </div>
    </div>

    <!-- ANALYTICS CHARTS GRID 2 -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; width: 100%; min-width: 0;">
        <!-- Chart 3: 24-Hour Gate Clearance Traffic Density -->
        <div class="card" style="background: white; border-radius: 1rem; border: 1px solid var(--border-color); padding: 1.5rem; min-width: 0; max-width: 100%; overflow: hidden;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <div>
                    <h3 style="font-size: 1.1rem; font-weight: 700; margin: 0; color: var(--text-primary);">Today's Security Gate Traffic Density</h3>
                    <p style="font-size: 0.8125rem; color: var(--text-secondary); margin: 0.15rem 0 0;">Hourly breakdown of visitor check-in volume across the gate lane</p>
                </div>
                <span class="badge badge-info">24-HOUR TRAFFIC</span>
            </div>
            <div style="height: 240px; position: relative; width: 100%; min-width: 0; overflow: hidden;">
                <canvas id="gateTrafficChart"></canvas>
            </div>
        </div>

        <!-- Maintenance Work Orders Breakdown -->
        <div class="card" style="background: white; border-radius: 1rem; border: 1px solid var(--border-color); padding: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <div>
                    <h3 style="font-size: 1.1rem; font-weight: 700; margin: 0; color: var(--text-primary);">Maintenance Tickets by Category</h3>
                    <p style="font-size: 0.8125rem; color: var(--text-secondary); margin: 0.15rem 0 0;">Service requests and resolution rates across facility departments</p>
                </div>
                <span class="badge badge-warning">OPERATIONS</span>
            </div>
            
            <?php if (!empty($maintCategories)): ?>
            <div style="display: flex; flex-direction: column; gap: 1rem; margin-top: 0.5rem;">
                <?php foreach ($maintCategories as $mc): ?>
                <?php 
                $pct = $mc['total_tickets'] > 0 ? round(($mc['resolved_tickets'] / $mc['total_tickets']) * 100) : 0;
                ?>
                <div>
                    <div style="display: flex; justify-content: space-between; font-size: 0.875rem; margin-bottom: 0.25rem;">
                        <strong style="text-transform: capitalize; color: var(--text-primary);"><?= e($mc['category']) ?></strong>
                        <span style="color: var(--text-secondary); font-size: 0.8125rem;">
                            <?= $mc['resolved_tickets'] ?> / <?= $mc['total_tickets'] ?> Resolved (<?= $pct ?>%)
                        </span>
                    </div>
                    <div style="width: 100%; height: 8px; background: var(--hover-bg); border-radius: 4px; overflow: hidden;">
                        <div style="width: <?= $pct ?>%; height: 100%; background: <?= $pct >= 80 ? '#16a34a' : ($pct >= 40 ? '#f59e0b' : '#dc2626') ?>; border-radius: 4px; transition: width 0.4s ease;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="text-muted" style="margin-top: 2rem; text-align: center;">No maintenance tickets submitted yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Activity Tables Grid -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem;">
        <!-- Today's Gate Entries Table -->
        <div class="card" style="background: white; border-radius: 1rem; border: 1px solid var(--border-color); padding: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3 style="font-size: 1.1rem; font-weight: 700; margin: 0;">Recent Gate Clearances</h3>
                <a href="<?= BASE_URL ?>gate" style="font-size: 0.85rem; font-weight: 600; color: var(--primary-color);">View All →</a>
            </div>
            <div class="table-responsive">
                <table class="data-table" style="width: 100%;">
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
                                <td><strong style="color: var(--primary-color);"><?= formatDate($gl['entry_timestamp'], 'H:i A') ?></strong></td>
                                <td><strong><?= e($gl['visitor_name']) ?></strong></td>
                                <td><?= e($gl['building_name']) ?> - Unit <?= e($gl['unit_number']) ?> (<code><?= e($gl['unit_code']) ?></code>)</td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="3" class="empty-state">No gate entries logged today.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Invoices Table -->
        <div class="card" style="background: white; border-radius: 1rem; border: 1px solid var(--border-color); padding: 1.5rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem;">
                <h3 style="font-size: 1.1rem; font-weight: 700; margin: 0;">Recent Invoices Issued</h3>
                <a href="<?= BASE_URL ?>billing" style="font-size: 0.85rem; font-weight: 600; color: var(--primary-color);">View All →</a>
            </div>
            <div class="table-responsive">
                <table class="data-table" style="width: 100%;">
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
                                <td><code><?= e($inv['invoice_number']) ?></code></td>
                                <td><?= e($inv['building_name']) ?> - Unit <?= e($inv['unit_number']) ?></td>
                                <td><strong><?= formatMoney($inv['net_amount']) ?></strong></td>
                                <td>
                                    <span class="badge badge-<?= $inv['status'] === 'paid' ? 'success' : ($inv['status'] === 'partial' ? 'warning' : 'danger') ?>">
                                        <?= strtoupper(e($inv['status'])) ?>
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
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Chart 1: Financial Collections & Invoiced Trend (Line Chart)
    const finData = <?= json_encode($monthlyFinancials) ?>;
    const finLabels = finData.map(d => d.month);
    const invValues = finData.map(d => d.invoiced);
    const payValues = finData.map(d => d.collected);

    const ctxFin = document.getElementById('financialTrendChart').getContext('2d');
    new Chart(ctxFin, {
        type: 'line',
        data: {
            labels: finLabels,
            datasets: [
                {
                    label: 'Revenue Invoiced (₦)',
                    data: invValues,
                    borderColor: '#0284c7',
                    backgroundColor: 'rgba(2, 132, 199, 0.08)',
                    fill: true,
                    tension: 0.35,
                    borderWidth: 2.5,
                    pointRadius: 4
                },
                {
                    label: 'Payments Collected (₦)',
                    data: payValues,
                    borderColor: '#16a34a',
                    backgroundColor: 'rgba(22, 163, 74, 0.08)',
                    fill: true,
                    tension: 0.35,
                    borderWidth: 2.5,
                    pointRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'top', labels: { usePointStyle: true, font: { family: 'Outfit', size: 12 } } },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            return ctx.dataset.label + ': ₦' + ctx.raw.toLocaleString('en-US', {minimumFractionDigits: 2});
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: 'rgba(0,0,0,0.04)' },
                    ticks: {
                        callback: function(v) { return '₦' + (v >= 1000 ? (v/1000) + 'k' : v); }
                    }
                },
                x: { grid: { display: false } }
            }
        }
    });

    // 2. Chart 2: Property Occupancy Breakdown (Doughnut Chart)
    const occData = <?= json_encode($occupancyBreakdown) ?>;
    const occLabels = Object.keys(occData);
    const occValues = Object.values(occData);

    const ctxOcc = document.getElementById('occupancyDoughnutChart').getContext('2d');
    new Chart(ctxOcc, {
        type: 'doughnut',
        data: {
            labels: occLabels,
            datasets: [{
                data: occValues,
                backgroundColor: ['#12B1B0', '#0284c7', '#f59e0b', '#ef4444', '#94a3b8'],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { position: 'bottom', labels: { usePointStyle: true, font: { family: 'Outfit', size: 11 } } }
            },
            cutout: '65%'
        }
    });

    // 3. Chart 3: 24-Hour Gate Clearance Traffic Density (Bar Chart)
    const gateHourly = <?= json_encode($hourlyGateData) ?>;
    const gateLabels = Array.from({length: 24}, (_, i) => sprintf("%02d:00", i));

    const ctxGate = document.getElementById('gateTrafficChart').getContext('2d');
    new Chart(ctxGate, {
        type: 'bar',
        data: {
            labels: gateLabels,
            datasets: [{
                label: 'Inbound Gate Entries',
                data: gateHourly,
                backgroundColor: '#12B1B0',
                borderRadius: 4,
                hoverBackgroundColor: '#0e8f8e'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true, ticks: { stepSize: 1 } },
                x: { grid: { display: false } }
            }
        }
    });

    function sprintf(format, number) {
        return (number < 10 ? '0' : '') + number + ':00';
    }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
