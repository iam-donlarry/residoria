<?php
// modules/gate/index.php - Security Gate Check-in Clearance Portal
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth_check.php';

requirePermission('security.gate');

$db = Database::getInstance();
$verifyResult = null;
$message = "";
$error = "";

// 1. VERIFY GATE CODE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_code'])) {
    try {
        verifyCsrfToken();
        $code = trim(sanitize($_POST['code_input']));

        if (empty($code)) {
            $error = "Please enter an Access Code or Pass Reference.";
        } else {
            $pass = $db->fetchOne("SELECT vp.*, p.first_name as host_first, p.last_name as host_last, p.primary_phone as host_phone,
                                   u.unit_number, u.unit_code, b.name as building_name
                                   FROM visitor_passes vp 
                                   JOIN persons p ON vp.host_person_id = p.id 
                                   JOIN property_units u ON vp.unit_id = u.id 
                                   JOIN buildings_blocks b ON u.building_id = b.id 
                                   WHERE vp.access_code = ? OR vp.pass_code_formatted = ?", [$code, strtoupper($code)]);

            if ($pass) {
                $verifyResult = $pass;
                logAudit($conn, "Gate Verification Search", "Gate", "Searched access code: $code");
            } else {
                $error = "INVALID ACCESS CODE: No matching visitor pass found in system.";
            }
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// 2. CHECK IN VISITOR (Grant Entry)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['grant_entry'])) {
    try {
        verifyCsrfToken();
        $pass_id = intval($_POST['pass_id']);
        $unit_id = intval($_POST['unit_id']);
        $v_name = sanitize($_POST['visitor_name']);
        $v_phone = sanitize($_POST['visitor_phone'] ?? '');
        $officer_user_id = $_SESSION['user_id'] ?? null;
        $notes = sanitize($_POST['notes'] ?? '');

        // Log entry in gate_logs
        $db->query("INSERT INTO gate_logs (pass_id, unit_id, visitor_name, visitor_phone, entry_timestamp, processing_officer_user_id, entry_type, notes) VALUES (?, ?, ?, ?, NOW(), ?, 'pre_authorized', ?)", [
            $pass_id, $unit_id, $v_name, $v_phone, $officer_user_id, $notes
        ]);

        // Update pass status to used
        $db->query("UPDATE visitor_passes SET pass_status = 'used' WHERE id = ?", [$pass_id]);

        $message = "ENTRY GRANTED! Gate clearance logged for <strong>$v_name</strong>.";
        logAudit($conn, "Grant Gate Entry", "Gate", "Granted entry for pass ID #$pass_id ($v_name)");
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch Today's Gate Entry Logs
$todayLogs = $db->fetchAll("SELECT gl.*, u.unit_number, b.name as building_name, usr.name as officer_name
                            FROM gate_logs gl 
                            JOIN property_units u ON gl.unit_id = u.id 
                            JOIN buildings_blocks b ON u.building_id = b.id 
                            LEFT JOIN users usr ON gl.processing_officer_user_id = usr.id 
                            WHERE DATE(gl.entry_timestamp) = CURDATE() 
                            ORDER BY gl.entry_timestamp DESC");

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div style="margin-bottom: 1.5rem;">
    <h1 style="font-size: 1.75rem; font-weight: 700; color: var(--text-primary); margin: 0;">Gate Security Clearance Portal</h1>
    <p style="color: var(--text-muted); font-size: 0.95rem; margin: 0.25rem 0 0;">Scan QR Code or enter 6-digit access code to verify visitor authorization</p>
</div>

<?php if (!empty($message)): ?>
    <div class="alert alert-success" style="margin-bottom: 1.5rem; background: rgba(18,177,176,0.15); border-left: 4px solid var(--primary); padding: 1rem; border-radius: 6px; color: #0f7675;">
        <?= $message ?>
    </div>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <div class="alert alert-danger" style="margin-bottom: 1.5rem; background: rgba(220,53,69,0.15); border-left: 4px solid var(--danger); padding: 1rem; border-radius: 6px; color: #721c24; font-weight: 600;">
        <?= $error ?>
    </div>
<?php endif; ?>

<!-- Gate Clearance Verification Card -->
<div class="card" style="background: var(--card-bg); border-radius: 12px; padding: 1.5rem; border: 1px solid var(--border-color); margin-bottom: 2rem; box-shadow: 0 4px 12px rgba(0,0,0,0.05);">
    <h3 style="font-size: 1.1rem; font-weight: 700; margin-top: 0; color: var(--primary); display: flex; align-items: center; gap: 0.5rem;">
        <i data-lucide="shield-check"></i> Visitor Code Clearance Scanner
    </h3>
    <form method="POST" style="display: flex; gap: 1rem; margin-top: 1rem;">
        <?= csrfField() ?>
        <input type="text" name="code_input" class="form-control" placeholder="Enter Access Code (e.g. 483921 or VST-849201)" required style="font-size: 1.2rem; font-weight: 700; letter-spacing: 1px; flex: 1;" autofocus>
        <button type="submit" name="verify_code" class="btn btn-primary" style="padding: 0.75rem 1.5rem; font-weight: 700; font-size: 1rem;">
            Verify Access Code
        </button>
    </form>

    <?php if ($verifyResult): ?>
        <div style="margin-top: 1.5rem; background: var(--bg-body); border-radius: 8px; padding: 1.25rem; border: 2px solid var(--primary);">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <span class="badge badge-success" style="font-size: 0.9rem; padding: 0.4rem 0.8rem;">AUTHORIZED CODE VERIFIED</span>
                    <h2 style="font-size: 1.4rem; font-weight: 700; margin: 0.5rem 0 0.25rem;"><?= e($verifyResult['visitor_name']) ?></h2>
                    <div style="color: var(--text-muted); font-size: 0.9rem;">Category: <strong><?= e(ucfirst($verifyResult['visitor_category'])) ?></strong> | Phone: <?= e($verifyResult['visitor_phone'] ?? 'N/A') ?></div>
                </div>
                <div style="text-align: right;">
                    <div style="font-size: 0.85rem; color: var(--text-muted);">Destination Unit</div>
                    <div style="font-size: 1.2rem; font-weight: 800; color: var(--primary);"><?= e($verifyResult['building_name']) ?> - Unit <?= e($verifyResult['unit_number']) ?></div>
                    <div style="font-size: 0.85rem; font-weight: 600; color: var(--text-primary); margin-top: 0.2rem;">Host: <?= e($verifyResult['host_first'] . ' ' . $verifyResult['host_last']) ?> (<?= e($verifyResult['host_phone']) ?>)</div>
                </div>
            </div>

            <form method="POST" style="margin-top: 1.25rem; border-top: 1px solid var(--border-color); padding-top: 1rem; display: flex; gap: 1rem; align-items: center;">
                <?= csrfField() ?>
                <input type="hidden" name="pass_id" value="<?= $verifyResult['id'] ?>">
                <input type="hidden" name="unit_id" value="<?= $verifyResult['unit_id'] ?>">
                <input type="hidden" name="visitor_name" value="<?= e($verifyResult['visitor_name']) ?>">
                <input type="hidden" name="visitor_phone" value="<?= e($verifyResult['visitor_phone']) ?>">
                <input type="text" name="notes" class="form-control btn-sm" placeholder="Security notes / vehicle plate check..." style="flex: 1;">
                <button type="submit" name="grant_entry" class="btn btn-success btn-lg" style="background: #10b981; border: none; font-weight: 800;">
                    ✓ GRANT ENTRY & LOG ENTRY
                </button>
            </form>
        </div>
    <?php endif; ?>
</div>

<!-- Today's Gate Log Table -->
<div class="data-table-wrapper">
    <div class="table-toolbar">
        <h3 style="font-size: 1.05rem; font-weight: 700; margin: 0;">Today's Gate Clearance Log (<?= count($todayLogs) ?>)</h3>
    </div>
    <table class="data-table">
        <thead>
            <tr>
                <th>Entry Timestamp</th>
                <th>Visitor Name</th>
                <th>Destination Unit</th>
                <th>Entry Type</th>
                <th>Officer on Duty</th>
                <th>Notes</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($todayLogs)): ?>
                <?php foreach ($todayLogs as $g): ?>
                <tr>
                    <td style="font-weight: 600; color: var(--primary);"><?= formatDate($g['entry_timestamp'], 'H:i:s A (M j)') ?></td>
                    <td style="font-weight: 700;"><?= e($g['visitor_name']) ?></td>
                    <td><?= e($g['building_name']) ?> - Unit <?= e($g['unit_number']) ?></td>
                    <td><span class="badge badge-success"><?= e(ucfirst(str_replace('_', ' ', $g['entry_type']))) ?></span></td>
                    <td style="font-weight: 600;"><?= e($g['officer_name'] ?? 'Gate Guard') ?></td>
                    <td style="color: var(--text-muted); font-size: 0.85rem;"><?= e($g['notes'] ?? 'Clearance OK') ?></td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="6" class="empty-state">No gate entries logged today yet.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
