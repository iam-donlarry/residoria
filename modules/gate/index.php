<?php
// modules/gate/index.php - Security Gate Check-in Clearance Portal & Outbound Checkout Handler
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/helpers.php';

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
            $pass = $db->fetchOne("
                SELECT vp.*, p.first_name as host_first, p.last_name as host_last, p.primary_phone as host_phone,
                       u.unit_number, u.unit_code, b.name as building_name
                FROM visitor_passes vp 
                JOIN persons p ON vp.host_person_id = p.id 
                JOIN property_units u ON vp.unit_id = u.id 
                JOIN buildings_blocks b ON u.building_id = b.id 
                WHERE (vp.access_code = ? OR vp.pass_code_formatted = ?) AND vp.pass_status = 'active'
            ", [$code, strtoupper($code)]);

            if ($pass) {
                // Check validity range
                $today = date('Y-m-d');
                $validFrom = $pass['valid_from_date'] ?? $pass['visit_date'];
                $validUntil = $pass['valid_until_date'] ?? $pass['visit_date'];

                if ($today < $validFrom || $today > $validUntil) {
                    $error = "PASS EXPIRED OR NOT YET VALID: Pass is valid from $validFrom to $validUntil.";
                } elseif ($pass['use_count'] >= $pass['max_uses'] && $pass['pass_type'] === 'single_entry') {
                    $error = "PASS EXHAUSTED: Maximum allowed uses ({$pass['max_uses']}) reached for this pass.";
                } else {
                    $verifyResult = $pass;
                    logAudit($conn, "Gate Verification Search", "Gate", "Searched access code: $code");
                }
            } else {
                $error = "INVALID OR EXPIRED ACCESS CODE: No active matching visitor pass found in system.";
            }
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// 2. CHECK IN VISITOR (Grant Inbound Entry)
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

        // Increment pass use count
        $pass = $db->fetchOne("SELECT max_uses, use_count, pass_type FROM visitor_passes WHERE id = ?", [$pass_id]);
        $newUseCount = ($pass['use_count'] ?? 0) + 1;
        $newStatus = ($pass['pass_type'] === 'single_entry' || $newUseCount >= $pass['max_uses']) ? 'used' : 'active';

        $db->query("UPDATE visitor_passes SET use_count = ?, pass_status = ? WHERE id = ?", [$newUseCount, $newStatus, $pass_id]);

        $message = "INBOUND ENTRY GRANTED! Gate clearance logged for <strong>" . e($v_name) . "</strong>.";
        logAudit($conn, "Grant Gate Entry", "Gate", "Granted entry for pass ID #$pass_id ($v_name)");
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// 3. RECORD VISITOR EXIT / CHECK OUT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_exit'])) {
    try {
        verifyCsrfToken();
        $log_id = intval($_POST['gate_log_id']);
        $officer_user_id = $_SESSION['user_id'] ?? null;

        $log = $db->fetchOne("SELECT * FROM gate_logs WHERE id = ? AND exit_timestamp IS NULL", [$log_id]);
        if ($log) {
            $db->query("UPDATE gate_logs SET exit_timestamp = NOW(), exit_processing_officer_user_id = ? WHERE id = ?", [$officer_user_id, $log_id]);
            $message = "OUTBOUND CHECK-OUT COMPLETED! Exit logged for <strong>" . e($log['visitor_name']) . "</strong>.";
            logAudit($conn, "Record Visitor Exit", "Gate", "Recorded exit for visitor " . $log['visitor_name']);
        } else {
            $error = "Visitor exit already recorded or invalid log ID.";
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// 4. LOG UNEXPECTED WALK-IN VISITOR
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['log_walk_in'])) {
    try {
        verifyCsrfToken();
        $unit_id = intval($_POST['unit_id']);
        $v_name = sanitize($_POST['visitor_name']);
        $v_phone = sanitize($_POST['visitor_phone']);
        $purpose = sanitize($_POST['purpose'] ?? 'Walk-In Artisan / Delivery');
        $officer_user_id = $_SESSION['user_id'] ?? null;

        if ($unit_id <= 0 || empty($v_name)) {
            $error = "Please specify visitor name and destination unit.";
        } else {
            $db->query("INSERT INTO gate_logs (unit_id, visitor_name, visitor_phone, entry_timestamp, processing_officer_user_id, entry_type, notes) VALUES (?, ?, ?, NOW(), ?, 'walk_in', ?)", [
                $unit_id, $v_name, $v_phone, $officer_user_id, "Walk-in Purpose: " . $purpose
            ]);
            $message = "WALK-IN VISITOR LOGGED! Inbound entry recorded for <strong>" . e($v_name) . "</strong>.";
            logAudit($conn, "Log Walk-In Visitor", "Gate", "Logged walk-in visitor $v_name for Unit #$unit_id");
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Fetch Active Visitors Currently Inside Estate (No Exit Timestamp)
$activeInside = $db->fetchAll("
    SELECT gl.*, u.unit_number, u.unit_code, b.name as building_name, usr.name as officer_name,
           TIMESTAMPDIFF(HOUR, gl.entry_timestamp, NOW()) as hours_inside
    FROM gate_logs gl 
    JOIN property_units u ON gl.unit_id = u.id 
    JOIN buildings_blocks b ON u.building_id = b.id 
    LEFT JOIN users usr ON gl.processing_officer_user_id = usr.id 
    WHERE gl.exit_timestamp IS NULL 
    ORDER BY gl.entry_timestamp DESC
");

// Fetch All Today's Gate Activity Logs
$todayLogs = $db->fetchAll("
    SELECT gl.*, u.unit_number, u.unit_code, b.name as building_name, usr.name as officer_name, exit_usr.name as exit_officer_name
    FROM gate_logs gl 
    JOIN property_units u ON gl.unit_id = u.id 
    JOIN buildings_blocks b ON u.building_id = b.id 
    LEFT JOIN users usr ON gl.processing_officer_user_id = usr.id 
    LEFT JOIN users exit_usr ON gl.exit_processing_officer_user_id = exit_usr.id
    WHERE DATE(gl.entry_timestamp) = CURDATE() 
    ORDER BY gl.entry_timestamp DESC
");

$units = $db->fetchAll("SELECT u.id, u.unit_number, u.unit_code, b.name as building_name FROM property_units u JOIN buildings_blocks b ON u.building_id = b.id ORDER BY b.name, u.unit_number ASC");

$pageTitle = "Security Gate Clearance & Visitor Log Portal";
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div class="content-wrapper">
    <div style="margin-bottom: 1.5rem;">
        <h1 style="font-size: 1.75rem; font-weight: 700; color: var(--text-primary); margin: 0;">Gate Security Clearance & Visitor Portal</h1>
        <p style="color: var(--text-secondary); font-size: 0.95rem; margin: 0.25rem 0 0;">
            Verify inbound access codes, track active visitors inside the estate, record outbound check-outs, and register walk-ins.
        </p>
    </div>

    <?php if (!empty($message)): ?>
    <div class="alert alert-success" style="margin-bottom: 1.5rem;">
        <i data-lucide="check-circle" style="width: 16px; height: 16px; vertical-align: middle;"></i> <?= $message ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
    <div class="alert alert-danger" style="margin-bottom: 1.5rem;">
        <i data-lucide="alert-circle" style="width: 16px; height: 16px; vertical-align: middle;"></i> <?= $error ?>
    </div>
    <?php endif; ?>

    <!-- Navigation Tabs -->
    <div class="tabs-container" style="background: white; border-radius: 1rem; border: 1px solid var(--border-color); padding: 0.5rem; margin-bottom: 1.5rem; display: flex; gap: 0.5rem;">
        <button type="button" class="tab-btn active" onclick="showGateTab('tab-scanner', this)" style="padding: 0.6rem 1rem; border-radius: 0.75rem; border: none; background: var(--primary-light); color: var(--primary-color); font-weight: 600; cursor: pointer;">
            <i data-lucide="shield-check" style="width: 16px; height: 16px; vertical-align: middle;"></i> Inbound Code Scanner
        </button>
        <button type="button" class="tab-btn" onclick="showGateTab('tab-inside', this)" style="padding: 0.6rem 1rem; border-radius: 0.75rem; border: none; background: transparent; color: var(--text-secondary); font-weight: 600; cursor: pointer;">
            <i data-lucide="user-check" style="width: 16px; height: 16px; vertical-align: middle;"></i> Active Visitors Inside (<?= count($activeInside) ?>)
        </button>
        <button type="button" class="tab-btn" onclick="showGateTab('tab-walkin', this)" style="padding: 0.6rem 1rem; border-radius: 0.75rem; border: none; background: transparent; color: var(--text-secondary); font-weight: 600; cursor: pointer;">
            <i data-lucide="user-plus" style="width: 16px; height: 16px; vertical-align: middle;"></i> Register Walk-In Visitor
        </button>
        <button type="button" class="tab-btn" onclick="showGateTab('tab-logs', this)" style="padding: 0.6rem 1rem; border-radius: 0.75rem; border: none; background: transparent; color: var(--text-secondary); font-weight: 600; cursor: pointer;">
            <i data-lucide="list" style="width: 16px; height: 16px; vertical-align: middle;"></i> Today's Full Gate Log (<?= count($todayLogs) ?>)
        </button>
    </div>

    <!-- TAB 1: INBOUND SCANNER -->
    <div id="tab-scanner" class="gate-tab-content" style="display: block;">
        <div class="card" style="background: white; border-radius: 1rem; padding: 1.5rem; border: 1px solid var(--border-color); margin-bottom: 1.5rem;">
            <h3 style="font-size: 1.1rem; font-weight: 700; margin-top: 0; color: var(--primary-color); display: flex; align-items: center; gap: 0.5rem;">
                <i data-lucide="qr-code"></i> Visitor Code Clearance Scanner
            </h3>
            <form method="POST" style="display: flex; gap: 1rem; margin-top: 1rem;">
                <?= csrfField() ?>
                <input type="text" name="code_input" class="form-control" placeholder="Enter Access Code (e.g. 483921 or VST-849201)" required style="font-size: 1.2rem; font-weight: 700; letter-spacing: 1px; flex: 1;" autofocus>
                <button type="submit" name="verify_code" class="btn btn-primary" style="padding: 0.75rem 1.5rem; font-weight: 700; font-size: 1rem;">
                    Verify Access Code
                </button>
            </form>

            <?php if ($verifyResult): ?>
                <div style="margin-top: 1.5rem; background: var(--hover-bg); border-radius: 0.75rem; padding: 1.25rem; border: 2px solid var(--primary-color);">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div>
                            <span class="badge badge-success" style="font-size: 0.875rem; padding: 0.4rem 0.8rem;">AUTHORIZED CODE VERIFIED</span>
                            <h2 style="font-size: 1.4rem; font-weight: 700; margin: 0.5rem 0 0.25rem;"><?= e($verifyResult['visitor_name']) ?></h2>
                            <div style="color: var(--text-secondary); font-size: 0.9rem;">
                                Category: <strong><?= e(ucfirst($verifyResult['visitor_category'])) ?></strong> &bull; 
                                Type: <strong><?= e(ucfirst($verifyResult['pass_type'] ?? 'single_entry')) ?></strong> &bull; 
                                Phone: <?= e($verifyResult['visitor_phone'] ?? 'N/A') ?>
                            </div>
                        </div>
                        <div style="text-align: right;">
                            <div style="font-size: 0.85rem; color: var(--text-secondary);">Destination Unit</div>
                            <div style="font-size: 1.2rem; font-weight: 800; color: var(--primary-color);"><?= e($verifyResult['building_name']) ?> - Unit <?= e($verifyResult['unit_number']) ?></div>
                            <div style="font-size: 0.85rem; font-weight: 600; color: var(--text-primary); margin-top: 0.2rem;">Host: <?= e($verifyResult['host_first'] . ' ' . $verifyResult['host_last']) ?> (<?= e($verifyResult['host_phone']) ?>)</div>
                        </div>
                    </div>

                    <form method="POST" style="margin-top: 1.25rem; border-top: 1px solid var(--border-color); padding-top: 1rem; display: flex; gap: 1rem; align-items: center;">
                        <?= csrfField() ?>
                        <input type="hidden" name="pass_id" value="<?= $verifyResult['id'] ?>">
                        <input type="hidden" name="unit_id" value="<?= $verifyResult['unit_id'] ?>">
                        <input type="hidden" name="visitor_name" value="<?= e($verifyResult['visitor_name']) ?>">
                        <input type="hidden" name="visitor_phone" value="<?= e($verifyResult['visitor_phone']) ?>">
                        <input type="text" name="notes" class="form-control" placeholder="Security notes / vehicle plate check..." style="flex: 1;">
                        <button type="submit" name="grant_entry" class="btn btn-success btn-lg" style="background: #16a34a; border: none; font-weight: 800;">
                            ✓ GRANT INBOUND ENTRY
                        </button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB 2: ACTIVE VISITORS INSIDE ESTATE (EXIT CHECKOUT) -->
    <div id="tab-inside" class="gate-tab-content" style="display: none;">
        <div class="card" style="background: white; border-radius: 1rem; border: 1px solid var(--border-color); padding: 1.5rem;">
            <h3 style="font-size: 1.125rem; font-weight: 700; margin-bottom: 1.25rem;">Visitors Currently Inside Estate (Awaiting Check-Out)</h3>
            <?php if (!empty($activeInside)): ?>
            <div class="table-responsive">
                <table class="data-table" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Visitor Name</th>
                            <th>Destination Unit</th>
                            <th>Entry Timestamp</th>
                            <th>Time Inside</th>
                            <th>Entry Type</th>
                            <th>Officer On Duty</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($activeInside as $act): ?>
                        <tr>
                            <td>
                                <strong><?= e($act['visitor_name']) ?></strong><br>
                                <small class="text-muted"><?= e($act['visitor_phone'] ?: 'No Phone') ?></small>
                            </td>
                            <td><strong style="color: var(--primary-color);"><?= e($act['unit_code']) ?></strong> (Unit <?= e($act['unit_number']) ?>)</td>
                            <td><?= formatDate($act['entry_timestamp'], 'g:i A (M j)') ?></td>
                            <td>
                                <?php if ($act['hours_inside'] >= 12): ?>
                                <span class="badge badge-danger" title="Overstayed Warning (>12 hours inside)">
                                    <i data-lucide="alert-triangle" style="width: 12px; height: 12px; vertical-align: middle;"></i> <?= $act['hours_inside'] ?> hrs (Overstayed)
                                </span>
                                <?php else: ?>
                                <span class="badge badge-info"><?= $act['hours_inside'] ?> hrs inside</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge-outline"><?= ucfirst(str_replace('_', ' ', e($act['entry_type']))) ?></span></td>
                            <td><?= e($act['officer_name'] ?: 'Gate Officer') ?></td>
                            <td style="text-align: right;">
                                <form method="POST" style="display: inline;">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="gate_log_id" value="<?= $act['id'] ?>">
                                    <button type="submit" name="record_exit" class="btn btn-sm btn-warning" style="font-weight: 700;">
                                        <i data-lucide="log-out" style="width: 14px; height: 14px;"></i> Record Exit / Check Out
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-muted">No visitors currently inside the estate awaiting checkout.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- TAB 3: WALK-IN VISITOR REGISTRATION -->
    <div id="tab-walkin" class="gate-tab-content" style="display: none;">
        <div class="card" style="background: white; border-radius: 1rem; border: 1px solid var(--border-color); padding: 1.5rem;">
            <h3 style="font-size: 1.125rem; font-weight: 700; margin-bottom: 1.25rem;">Register Unexpected Walk-In Visitor</h3>
            <form method="POST">
                <?= csrfField() ?>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Visitor Full Name *</label>
                        <input type="text" name="visitor_name" class="form-control" placeholder="e.g. Musa Ibrahim" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Visitor Phone Number *</label>
                        <input type="text" name="visitor_phone" class="form-control" placeholder="+234 803 000 0000" required>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1.25rem;">
                    <div class="form-group">
                        <label class="form-label">Destination Unit *</label>
                        <select name="unit_id" class="form-control" required>
                            <option value="">-- Select Destination Unit --</option>
                            <?php foreach ($units as $u): ?>
                            <option value="<?= $u['id'] ?>">
                                <?= e($u['building_name']) ?> - Unit <?= e($u['unit_number']) ?> (<?= e($u['unit_code']) ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Purpose of Visit</label>
                        <input type="text" name="purpose" class="form-control" placeholder="e.g. Jumia Delivery / Plumbing Repair">
                    </div>
                </div>

                <button type="submit" name="log_walk_in" class="btn btn-primary">
                    <i data-lucide="check" style="width: 16px; height: 16px;"></i> Log Walk-In Entry & Clear Gate
                </button>
            </form>
        </div>
    </div>

    <!-- TAB 4: TODAY'S FULL GATE LOG -->
    <div id="tab-logs" class="gate-tab-content" style="display: none;">
        <div class="card" style="background: white; border-radius: 1rem; border: 1px solid var(--border-color); padding: 1.5rem;">
            <h3 style="font-size: 1.125rem; font-weight: 700; margin-bottom: 1.25rem;">Today's Complete Gate Log</h3>
            <?php if (!empty($todayLogs)): ?>
            <div class="table-responsive">
                <table class="data-table" style="width: 100%;">
                    <thead>
                        <tr>
                            <th>Entry Timestamp</th>
                            <th>Visitor Name</th>
                            <th>Destination Unit</th>
                            <th>Entry Type</th>
                            <th>Inbound Officer</th>
                            <th>Exit Timestamp</th>
                            <th>Outbound Officer</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($todayLogs as $g): ?>
                        <tr>
                            <td><strong><?= formatDate($g['entry_timestamp'], 'g:i:s A (M j)') ?></strong></td>
                            <td><strong><?= e($g['visitor_name']) ?></strong> (<?= e($g['visitor_phone'] ?: 'N/A') ?>)</td>
                            <td><strong style="color: var(--primary-color);"><?= e($g['unit_code']) ?></strong> (Unit <?= e($g['unit_number']) ?>)</td>
                            <td><span class="badge badge-outline"><?= ucfirst(str_replace('_', ' ', e($g['entry_type']))) ?></span></td>
                            <td><?= e($g['officer_name'] ?: 'Gate Guard') ?></td>
                            <td>
                                <?php if (!empty($g['exit_timestamp'])): ?>
                                <?= formatDate($g['exit_timestamp'], 'g:i:s A') ?>
                                <?php else: ?>
                                <span class="badge badge-warning">Currently Inside</span>
                                <?php endif; ?>
                            </td>
                            <td><?= e($g['exit_officer_name'] ?: 'N/A') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <p class="text-muted">No gate clearance activity logged today.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function showGateTab(tabId, btn) {
    document.querySelectorAll('.gate-tab-content').forEach(el => el.style.display = 'none');
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
