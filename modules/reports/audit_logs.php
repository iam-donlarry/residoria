<?php
// modules/reports/audit_logs.php - Centralized System Activity & Audit Trail Logger
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/helpers.php';

requirePermission('reports.view');

$db = Database::getInstance();

// Filters & Search Parameters
$filterModule = sanitize($_GET['module'] ?? '');
$filterUserId = intval($_GET['user_id'] ?? 0);
$filterDateFrom = sanitize($_GET['date_from'] ?? '');
$filterDateTo = sanitize($_GET['date_to'] ?? '');
$searchQuery = sanitize($_GET['q'] ?? '');

// Build Query
$where = ["1=1"];
$params = [];

if (!empty($filterModule)) {
    $where[] = "a.module = ?";
    $params[] = $filterModule;
}
if ($filterUserId > 0) {
    $where[] = "a.user_id = ?";
    $params[] = $filterUserId;
}
if (!empty($filterDateFrom)) {
    $where[] = "DATE(a.created_at) >= ?";
    $params[] = $filterDateFrom;
}
if (!empty($filterDateTo)) {
    $where[] = "DATE(a.created_at) <= ?";
    $params[] = $filterDateTo;
}
if (!empty($searchQuery)) {
    $where[] = "(a.action LIKE ? OR a.module LIKE ? OR a.details LIKE ? OR u.email LIKE ? OR u.name LIKE ? OR a.ip_address LIKE ?)";
    $term = "%{$searchQuery}%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

$whereClause = implode(" AND ", $where);

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $exportLogs = $db->fetchAll("
        SELECT a.id, a.created_at, a.module, a.action, u.name as user_name, u.email as user_email, u.role as user_role, a.ip_address, a.details
        FROM audit_logs a
        LEFT JOIN users u ON a.user_id = u.id
        WHERE {$whereClause}
        ORDER BY a.created_at DESC
        LIMIT 2000
    ", $params);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=estate_audit_logs_' . date('Y-m-d_H-i') . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Log ID', 'Timestamp', 'Module', 'Action', 'Actor Name', 'Actor Email', 'Role', 'IP Address', 'Details']);

    foreach ($exportLogs as $row) {
        fputcsv($output, [
            $row['id'],
            $row['created_at'],
            $row['module'],
            $row['action'],
            $row['user_name'] ?: 'System / Guest',
            $row['user_email'] ?: 'N/A',
            ucfirst($row['user_role'] ?: 'System'),
            $row['ip_address'],
            $row['details']
        ]);
    }
    fclose($output);
    exit;
}

// Fetch Paginated Logs (Default Top 200 Recent Logs)
$logs = $db->fetchAll("
    SELECT a.*, 
           u.email as user_email, 
           u.name as user_name, 
           u.role as user_role,
           CONCAT(p.first_name, ' ', p.last_name) as person_name
    FROM audit_logs a
    LEFT JOIN users u ON a.user_id = u.id
    LEFT JOIN persons p ON u.person_id = p.id
    WHERE {$whereClause}
    ORDER BY a.created_at DESC
    LIMIT 200
", $params);

// Fetch Modules List & Users List for Filter Select Dropdowns
$modulesList = $db->fetchAll("SELECT DISTINCT module FROM audit_logs WHERE module IS NOT NULL AND module != '' ORDER BY module ASC");
$usersList = $db->fetchAll("SELECT id, name, email FROM users ORDER BY name ASC");

$pageTitle = "Centralized System Activity & Audit Trail Logger";
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="content-wrapper">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
        <div>
            <h1 class="page-title" style="margin: 0; font-size: 1.75rem; font-weight: 700;">System Audit Logs & Activity Trail</h1>
            <p class="text-subtitle" style="color: var(--text-secondary); margin-top: 0.25rem;">
                Tamper-evident system activity logger tracking logins, gate clearances, financial billing, and security events.
            </p>
        </div>
        <div>
            <a href="audit_logs?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-outline">
                <i data-lucide="download" style="width: 16px; height: 16px;"></i> Export CSV Report
            </a>
        </div>
    </div>

    <!-- Filters Bar -->
    <div class="card" style="background: white; border-radius: 1rem; border: 1px solid var(--border-color); padding: 1.25rem; margin-bottom: 1.5rem;">
        <form method="GET" action="audit_logs" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(170px, 1fr)) 100px; gap: 1rem; align-items: end;">
            <div class="form-group" style="margin: 0;">
                <label class="form-label" style="font-size: 0.8125rem;">Module</label>
                <select name="module" class="form-control" style="font-size: 0.875rem;">
                    <option value="">-- All Modules --</option>
                    <?php foreach ($modulesList as $m): ?>
                    <option value="<?= e($m['module']) ?>" <?= $filterModule === $m['module'] ? 'selected' : '' ?>><?= e($m['module']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin: 0;">
                <label class="form-label" style="font-size: 0.8125rem;">Performing User / Actor</label>
                <select name="user_id" class="form-control" style="font-size: 0.875rem;">
                    <option value="">-- All System Users --</option>
                    <?php foreach ($usersList as $usr): ?>
                    <option value="<?= $usr['id'] ?>" <?= $filterUserId == $usr['id'] ? 'selected' : '' ?>><?= e($usr['name']) ?> (<?= e($usr['email']) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" style="margin: 0;">
                <label class="form-label" style="font-size: 0.8125rem;">Date From</label>
                <input type="date" name="date_from" class="form-control" value="<?= e($filterDateFrom) ?>" style="font-size: 0.875rem;">
            </div>

            <div class="form-group" style="margin: 0;">
                <label class="form-label" style="font-size: 0.8125rem;">Date To</label>
                <input type="date" name="date_to" class="form-control" value="<?= e($filterDateTo) ?>" style="font-size: 0.875rem;">
            </div>

            <div class="form-group" style="margin: 0;">
                <label class="form-label" style="font-size: 0.8125rem;">Search Query</label>
                <input type="text" name="q" class="form-control" placeholder="Action / Details..." value="<?= e($searchQuery) ?>" style="font-size: 0.875rem;">
            </div>

            <div style="display: flex; gap: 0.5rem;">
                <button type="submit" class="btn btn-primary" style="width: 100%; padding: 0.55rem 0.75rem;">
                    <i data-lucide="filter" style="width: 14px; height: 14px;"></i> Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Audit Logs Data Table -->
    <div class="card" style="background: white; border-radius: 1rem; border: 1px solid var(--border-color); padding: 1.5rem;">
        <div class="table-responsive">
            <table class="data-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th style="width: 80px;">Log ID</th>
                        <th>Timestamp</th>
                        <th>Module</th>
                        <th>Action Performed</th>
                        <th>Actor / User</th>
                        <th>IP Address</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($logs)): ?>
                        <?php foreach ($logs as $l): ?>
                        <tr>
                            <td><code>#<?= $l['id'] ?></code></td>
                            <td><strong style="color: var(--text-primary);"><?= formatDate($l['created_at'], 'M j, Y H:i:s') ?></strong></td>
                            <td><span class="badge badge-info"><?= e($l['module'] ?: 'System') ?></span></td>
                            <td><strong style="color: var(--primary-color);"><?= e($l['action']) ?></strong></td>
                            <td>
                                <?php if (!empty($l['user_name'])): ?>
                                <strong><?= e($l['user_name']) ?></strong><br>
                                <small class="text-muted"><?= e($l['user_email']) ?></small>
                                <?php else: ?>
                                <span class="badge badge-secondary">System / Guest</span>
                                <?php endif; ?>
                            </td>
                            <td><code><?= e($l['ip_address'] ?: '127.0.0.1') ?></code></td>
                            <td style="text-align: right;">
                                <button type="button" class="btn btn-sm btn-outline" onclick="openLogDetailDrawer(<?= htmlspecialchars(json_encode($l), ENT_QUOTES, 'UTF-8') ?>)" title="View Event Payload">
                                    <i data-lucide="eye" style="width: 14px; height: 14px;"></i> Inspect
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr><td colspan="7" class="empty-state">No audit log records match the selected filter criteria.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- AUDIT LOG DETAIL OFFCANVAS SLIDE-OVER DRAWER -->
<div id="logDrawer" class="offcanvas-overlay">
    <div class="offcanvas-panel">
        <div class="offcanvas-header">
            <div>
                <span class="badge badge-info" id="drawerLogBadge">LOG EVENT</span>
                <h3 id="drawerLogTitle" style="margin: 0.25rem 0 0 0; font-size: 1.25rem; font-weight: 700; color: var(--text-primary);">Audit Log Details</h3>
            </div>
            <button type="button" class="toast-close" onclick="closeOffcanvas('logDrawer')">&times;</button>
        </div>
        
        <div class="offcanvas-body" style="padding: 1.5rem; flex: 1; overflow-y: auto;">
            <div style="margin-bottom: 1.25rem; background: var(--hover-bg); padding: 1rem; border-radius: 0.75rem; border-left: 3px solid var(--primary-color);">
                <div style="font-size: 0.75rem; text-transform: uppercase; color: var(--text-secondary); font-weight: 700;">Event Action</div>
                <div id="drawerAction" style="font-size: 1.15rem; font-weight: 700; color: var(--primary-color); margin-top: 0.2rem;">-</div>
            </div>

            <table class="data-table" style="width: 100%; margin-bottom: 1.5rem;">
                <tr><th style="width: 35%;">Log ID</th><td><code id="drawerLogId">#0</code></td></tr>
                <tr><th>Exact Timestamp</th><td><strong id="drawerTimestamp">-</strong></td></tr>
                <tr><th>System Module</th><td><span class="badge badge-info" id="drawerModule">-</span></td></tr>
                <tr><th>Performing Actor</th><td><strong id="drawerActorName">-</strong> (<span id="drawerActorEmail">-</span>)</td></tr>
                <tr><th>Assigned Role</th><td><span class="badge badge-outline" id="drawerRole">-</span></td></tr>
                <tr><th>Client IP Address</th><td><code id="drawerIp">-</code></td></tr>
            </table>

            <div style="border: 1px solid var(--border-color); border-radius: 0.75rem; padding: 1rem; background: #f8fafc;">
                <h4 style="font-size: 0.85rem; font-weight: 700; text-transform: uppercase; color: var(--text-secondary); margin: 0 0 0.5rem 0;">Event Payload Details</h4>
                <div id="drawerDetails" style="font-family: monospace; font-size: 0.875rem; color: #0f172a; white-space: pre-wrap; word-break: break-all;">-</div>
            </div>
        </div>

        <div class="offcanvas-footer" style="padding: 1.25rem 1.75rem; border-top: 1px solid var(--border-color); display: flex; justify-content: flex-end; background: var(--hover-bg);">
            <button type="button" class="btn btn-secondary" onclick="closeOffcanvas('logDrawer')">Close Inspection</button>
        </div>
    </div>
</div>

<script>
function openLogDetailDrawer(log) {
    document.getElementById('drawerLogBadge').innerText = "LOG #" + log.id;
    document.getElementById('drawerLogTitle').innerText = log.action;
    document.getElementById('drawerAction').innerText = log.action;
    document.getElementById('drawerLogId').innerText = "#" + log.id;
    document.getElementById('drawerTimestamp').innerText = log.created_at;
    document.getElementById('drawerModule').innerText = log.module || 'System';
    document.getElementById('drawerActorName').innerText = log.user_name || 'System / Guest';
    document.getElementById('drawerActorEmail').innerText = log.user_email || 'N/A';
    document.getElementById('drawerRole').innerText = (log.user_role || 'System').toUpperCase();
    document.getElementById('drawerIp').innerText = log.ip_address || '127.0.0.1';
    document.getElementById('drawerDetails').innerText = log.details || 'No additional payload details logged.';

    openOffcanvas('logDrawer');
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
