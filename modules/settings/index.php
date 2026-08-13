<?php
// modules/settings/index.php - System Configuration & Estate Branding
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../classes/SystemConfig.php';

requirePermission('settings.view');

$configManager = new SystemConfig();
$message = "";
$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    requirePermission('settings.manage');
    try {
        verifyCsrfToken();

        $settingsToSave = [
            'estate_name' => sanitize($_POST['estate_name']),
            'estate_motto' => sanitize($_POST['estate_motto'] ?? ''),
            'estate_location' => sanitize($_POST['estate_location'] ?? ''),
            'office_email' => sanitize($_POST['office_email'] ?? ''),
            'office_phone' => sanitize($_POST['office_phone'] ?? ''),
            'currency_symbol' => sanitize($_POST['currency_symbol'] ?? '₦'),
            'theme_color' => sanitize($_POST['theme_color'] ?? '#12B1B0'),
            'paystack_public_key' => sanitize($_POST['paystack_public_key'] ?? ''),
            'paystack_secret_key' => sanitize($_POST['paystack_secret_key'] ?? ''),
            'resident_id_prefix' => sanitize($_POST['resident_id_prefix'] ?? 'RES-'),
            'property_code_prefix' => sanitize($_POST['property_code_prefix'] ?? 'PROP-'),
            'visitor_pass_prefix' => sanitize($_POST['visitor_pass_prefix'] ?? 'VST-'),
            'invoice_prefix' => sanitize($_POST['invoice_prefix'] ?? 'INV-'),
            'receipt_prefix' => sanitize($_POST['receipt_prefix'] ?? 'REC-'),
            'ticket_prefix' => sanitize($_POST['ticket_prefix'] ?? 'TKT-'),
            'staff_prefix' => sanitize($_POST['staff_prefix'] ?? 'STF-'),
            'vehicle_prefix' => sanitize($_POST['vehicle_prefix'] ?? 'VEH-'),
            'estate_rules' => sanitize($_POST['estate_rules'] ?? ''),
            'estate_code_of_conduct' => sanitize($_POST['estate_code_of_conduct'] ?? '')
        ];

        // Handle Logo Upload if provided
        if (isset($_FILES['estate_logo']) && $_FILES['estate_logo']['error'] === UPLOAD_ERR_OK) {
            $up = secureUpload($_FILES['estate_logo'], ['jpg', 'jpeg', 'png']);
            if ($up['success']) {
                $settingsToSave['estate_logo'] = $up['path'];
            }
        }

        $configManager->updateSettings($settingsToSave);
        $message = "Estate system settings updated successfully!";
        logAudit($conn, "Update Settings", "Settings", "Updated estate profile and prefix configurations");

    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

$s = $configManager->getSettings();

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<div style="margin-bottom: 1.5rem;">
    <h1 style="font-size: 1.75rem; font-weight: 700; color: var(--text-primary); margin: 0;">Estate System Settings</h1>
    <p style="color: var(--text-muted); font-size: 0.95rem; margin: 0.25rem 0 0;">Configure estate identity, reference code formats, currency, Paystack keys, and rules</p>
</div>

<?php if (!empty($message)): ?>
    <script>document.addEventListener('DOMContentLoaded', function() { showToast(<?= json_encode($message) ?>, 'success'); });</script>
<?php endif; ?>
<?php if (!empty($error)): ?>
    <script>document.addEventListener('DOMContentLoaded', function() { showToast(<?= json_encode($error) ?>, 'error'); });</script>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
    <?= csrfField() ?>
    
    <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem;">
        <!-- Left Column: Core Identity & Format Settings -->
        <div>
            <!-- Section 1: Estate Identity -->
            <div class="card" style="background: var(--card-bg); border-radius: 10px; padding: 1.5rem; border: 1px solid var(--border-color); margin-bottom: 1.5rem;">
                <h3 style="font-size: 1.1rem; font-weight: 700; margin-top: 0; color: var(--primary); display: flex; align-items: center; gap: 0.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
                    <i data-lucide="building-2"></i> Estate Identity & Profile
                </h3>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Estate Name *</label>
                        <input type="text" name="estate_name" class="form-control" value="<?= e($s['estate_name'] ?? 'Residoria Estate') ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Motto / Tagline</label>
                        <input type="text" name="estate_motto" class="form-control" value="<?= e($s['estate_motto'] ?? '') ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Estate Physical Address / Location</label>
                    <input type="text" name="estate_location" class="form-control" value="<?= e($s['estate_location'] ?? '') ?>">
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Admin Office Phone</label>
                        <input type="text" name="office_phone" class="form-control" value="<?= e($s['office_phone'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Admin Office Email</label>
                        <input type="email" name="office_email" class="form-control" value="<?= e($s['office_email'] ?? '') ?>">
                    </div>
                </div>
            </div>

            <!-- Section 2: Reference Code Prefixes -->
            <div class="card" style="background: var(--card-bg); border-radius: 10px; padding: 1.5rem; border: 1px solid var(--border-color); margin-bottom: 1.5rem;">
                <h3 style="font-size: 1.1rem; font-weight: 700; margin-top: 0; color: var(--primary); display: flex; align-items: center; gap: 0.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
                    <i data-lucide="hash"></i> Reference Code Format Prefixes
                </h3>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-top: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Resident Code Prefix</label>
                        <input type="text" name="resident_id_prefix" class="form-control" value="<?= e($s['resident_id_prefix'] ?? 'RES-') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Property Unit Code Prefix</label>
                        <input type="text" name="property_code_prefix" class="form-control" value="<?= e($s['property_code_prefix'] ?? 'PROP-') ?>">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">Visitor Pass Prefix</label>
                        <input type="text" name="visitor_pass_prefix" class="form-control" value="<?= e($s['visitor_pass_prefix'] ?? 'VST-') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Invoice Prefix</label>
                        <input type="text" name="invoice_prefix" class="form-control" value="<?= e($s['invoice_prefix'] ?? 'INV-') ?>">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.75rem;">
                    <div class="form-group">
                        <label class="form-label">Receipt Prefix</label>
                        <input type="text" name="receipt_prefix" class="form-control" value="<?= e($s['receipt_prefix'] ?? 'REC-') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Ticket Prefix</label>
                        <input type="text" name="ticket_prefix" class="form-control" value="<?= e($s['ticket_prefix'] ?? 'TKT-') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Staff Prefix</label>
                        <input type="text" name="staff_prefix" class="form-control" value="<?= e($s['staff_prefix'] ?? 'STF-') ?>">
                    </div>
                </div>
            </div>

            <!-- Section 3: Rules & Regulations -->
            <div class="card" style="background: var(--card-bg); border-radius: 10px; padding: 1.5rem; border: 1px solid var(--border-color); margin-bottom: 1.5rem;">
                <h3 style="font-size: 1.1rem; font-weight: 700; margin-top: 0; color: var(--primary); display: flex; align-items: center; gap: 0.5rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem;">
                    <i data-lucide="file-text"></i> Estate Rules & Code of Conduct
                </h3>

                <div class="form-group" style="margin-top: 1rem;">
                    <label class="form-label">Estate Rules & Directives</label>
                    <textarea name="estate_rules" class="form-control" rows="3"><?= e($s['estate_rules'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- Right Column: Brand & Payment Gateway -->
        <div>
            <div class="card" style="background: var(--card-bg); border-radius: 10px; padding: 1.5rem; border: 1px solid var(--border-color); margin-bottom: 1.5rem;">
                <h3 style="font-size: 1.05rem; font-weight: 700; margin-top: 0; color: var(--primary);">Brand & Theme</h3>

                <div class="form-group">
                    <label class="form-label">Currency Symbol</label>
                    <input type="text" name="currency_symbol" class="form-control" value="<?= e($s['currency_symbol'] ?? '₦') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Primary Brand Color</label>
                    <input type="color" name="theme_color" class="form-control" value="<?= e($s['theme_color'] ?? '#12B1B0') ?>" style="height: 42px; padding: 0.2rem;">
                </div>

                <div class="form-group">
                    <label class="form-label">Estate Logo Image</label>
                    <input type="file" name="estate_logo" class="form-control" accept="image/*">
                </div>
            </div>

            <div class="card" style="background: var(--card-bg); border-radius: 10px; padding: 1.5rem; border: 1px solid var(--border-color); margin-bottom: 1.5rem;">
                <h3 style="font-size: 1.05rem; font-weight: 700; margin-top: 0; color: var(--primary);">Paystack Gateway</h3>

                <div class="form-group">
                    <label class="form-label">Paystack Public Key</label>
                    <input type="text" name="paystack_public_key" class="form-control" value="<?= e($s['paystack_public_key'] ?? '') ?>" placeholder="pk_test_...">
                </div>

                <div class="form-group">
                    <label class="form-label">Paystack Secret Key</label>
                    <input type="password" name="paystack_secret_key" class="form-control" value="<?= e($s['paystack_secret_key'] ?? '') ?>" placeholder="sk_test_...">
                </div>
            </div>

            <?php if (hasPermission('settings.manage')): ?>
                <button type="submit" name="save_settings" class="btn btn-primary btn-lg" style="width: 100%; font-weight: 700; padding: 0.8rem;">
                    Save System Settings
                </button>
            <?php endif; ?>
        </div>
    </div>
</form>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
