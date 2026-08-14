<!-- includes/sidebar.php - Dynamic Standalone Navigation Sidebar (Expense App Paradigm with Sleek Custom Tooltips) -->
<?php
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$user_name = $_SESSION['name'] ?? $_SESSION['user_name'] ?? 'Admin User';
$user_role = ucfirst($_SESSION['role'] ?? $_SESSION['user_role'] ?? 'Admin');

// Active Group Detection Helpers
$isEstateGroupActive = str_contains($requestUri, 'properties') || (str_contains($requestUri, 'people') && !str_contains($requestUri, 'roles')) || str_contains($requestUri, 'owners') || str_contains($requestUri, 'residents') || str_contains($requestUri, 'households') || str_contains($requestUri, 'commercial');

$isPersonnelGroupActive = str_contains($requestUri, 'domestic-staff') || str_contains($requestUri, 'estate-staff') || (str_contains($requestUri, 'staff') && !str_contains($requestUri, 'domestic-staff')) || str_contains($requestUri, 'vehicles');

$isSecurityGroupActive = str_contains($requestUri, 'gate') || str_contains($requestUri, 'visitors');

$isFinanceGroupActive = str_contains($requestUri, 'billing') || str_contains($requestUri, 'payments') || str_contains($requestUri, 'maintenance');

$isSystemGroupActive = str_contains($requestUri, 'users') || str_contains($requestUri, 'roles') || str_contains($requestUri, 'audit_logs') || (str_contains($requestUri, 'settings') && !str_contains($requestUri, 'roles') && !str_contains($requestUri, 'users') && !str_contains($requestUri, 'audit_logs'));
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <?php if (!empty($estate_logo)): ?>
                <img src="<?= e($estate_logo) ?>" alt="Logo" style="height: 32px; border-radius: 4px;">
                <span><?= e($estate_name) ?></span>
            <?php else: ?>
                <i data-lucide="building-2"></i>
                <span><?= e($estate_name ?? 'Residoria Estate') ?></span>
            <?php endif; ?>
        </div>
    </div>
    
    <nav class="sidebar-nav">
        <div class="sidebar-section-header">Main</div>
        <a href="<?= BASE_URL ?>dashboard" class="nav-link <?= (str_contains($requestUri, 'dashboard')) ? 'active' : ''; ?>" data-tooltip="Dashboard">
            <i data-lucide="layout-dashboard" class="nav-icon"></i> <span class="nav-text">Dashboard</span>
        </a>

        <!-- Estate & Occupants -->
        <?php if (hasPermission('properties.view') || hasPermission('people.view') || hasPermission('owners.view') || hasPermission('residents.view') || hasPermission('commercial.view')): ?>
        <div class="sidebar-section-header">Estate Structure</div>
        <details class="nav-group" <?= $isEstateGroupActive ? 'open' : '' ?>>
            <summary class="nav-group-summary" data-tooltip="Estate & Occupants">
                <i data-lucide="building-2" class="nav-icon"></i>
                <span class="nav-text">Estate & Occupants</span>
                <i data-lucide="chevron-right" class="caret-icon"></i>
            </summary>
            <div class="sub-menu">
                <?php if (hasPermission('properties.view')): ?>
                <a href="<?= BASE_URL ?>properties" class="sub-nav-link <?= str_contains($requestUri, 'properties') ? 'active' : ''; ?>" data-tooltip="Properties & Units">
                    <i data-lucide="building"></i>
                    <span class="nav-text">Properties & Units</span>
                </a>
                <?php endif; ?>

                <?php if (hasPermission('people.view')): ?>
                <a href="<?= BASE_URL ?>people" class="sub-nav-link <?= (str_contains($requestUri, 'people') && !str_contains($requestUri, 'roles')) ? 'active' : ''; ?>" data-tooltip="Persons Directory">
                    <i data-lucide="user-check"></i>
                    <span class="nav-text">Persons Directory</span>
                </a>
                <?php endif; ?>

                <?php if (hasPermission('owners.view')): ?>
                <a href="<?= BASE_URL ?>owners" class="sub-nav-link <?= str_contains($requestUri, 'owners') ? 'active' : ''; ?>" data-tooltip="Property Owners">
                    <i data-lucide="award"></i>
                    <span class="nav-text">Property Owners</span>
                </a>
                <?php endif; ?>

                <?php if (hasPermission('residents.view')): ?>
                <a href="<?= BASE_URL ?>residents" class="sub-nav-link <?= str_contains($requestUri, 'residents') ? 'active' : ''; ?>" data-tooltip="Residents Directory">
                    <i data-lucide="users"></i>
                    <span class="nav-text">Residents & Occupancy</span>
                </a>
                <a href="<?= BASE_URL ?>households" class="sub-nav-link <?= str_contains($requestUri, 'households') ? 'active' : ''; ?>" data-tooltip="Household Members">
                    <i data-lucide="heart-handshake"></i>
                    <span class="nav-text">Household Members</span>
                </a>
                <?php endif; ?>

                <?php if (hasPermission('commercial.view')): ?>
                <a href="<?= BASE_URL ?>commercial" class="sub-nav-link <?= str_contains($requestUri, 'commercial') ? 'active' : ''; ?>" data-tooltip="Commercial Businesses">
                    <i data-lucide="store"></i>
                    <span class="nav-text">Commercial Businesses</span>
                </a>
                <?php endif; ?>
            </div>
        </details>
        <?php endif; ?>

        <!-- Personnel & Fleet -->
        <?php if (hasPermission('residents.view') || hasPermission('staff.view') || hasPermission('vehicles.view')): ?>
        <div class="sidebar-section-header">Personnel & Fleet</div>
        <details class="nav-group" <?= $isPersonnelGroupActive ? 'open' : '' ?>>
            <summary class="nav-group-summary" data-tooltip="Personnel & Vehicles">
                <i data-lucide="users-round" class="nav-icon"></i>
                <span class="nav-text">Personnel & Vehicles</span>
                <i data-lucide="chevron-right" class="caret-icon"></i>
            </summary>
            <div class="sub-menu">
                <?php if (hasPermission('residents.view')): ?>
                <a href="<?= BASE_URL ?>domestic-staff" class="sub-nav-link <?= str_contains($requestUri, 'domestic-staff') ? 'active' : ''; ?>" data-tooltip="Domestic Staff">
                    <i data-lucide="user-cog"></i>
                    <span class="nav-text">Domestic Staff</span>
                </a>
                <?php endif; ?>

                <?php if (hasPermission('staff.view')): ?>
                <a href="<?= BASE_URL ?>estate-staff" class="sub-nav-link <?= (str_contains($requestUri, 'estate-staff') || (str_contains($requestUri, 'staff') && !str_contains($requestUri, 'domestic-staff'))) ? 'active' : ''; ?>" data-tooltip="Estate Staff">
                    <i data-lucide="badge-check"></i>
                    <span class="nav-text">Estate Staff</span>
                </a>
                <?php endif; ?>

                <?php if (hasPermission('vehicles.view')): ?>
                <a href="<?= BASE_URL ?>vehicles" class="sub-nav-link <?= str_contains($requestUri, 'vehicles') ? 'active' : ''; ?>" data-tooltip="Vehicle Register">
                    <i data-lucide="car"></i>
                    <span class="nav-text">Vehicle Register</span>
                </a>
                <?php endif; ?>
            </div>
        </details>
        <?php endif; ?>

        <!-- Gate Security -->
        <?php if (hasPermission('security.gate') || hasPermission('security.view')): ?>
        <div class="sidebar-section-header">Gate Security</div>
        <details class="nav-group" <?= $isSecurityGroupActive ? 'open' : '' ?>>
            <summary class="nav-group-summary" data-tooltip="Gate & Security">
                <i data-lucide="shield-check" class="nav-icon"></i>
                <span class="nav-text">Gate & Security</span>
                <i data-lucide="chevron-right" class="caret-icon"></i>
            </summary>
            <div class="sub-menu">
                <?php if (hasPermission('security.gate')): ?>
                <a href="<?= BASE_URL ?>gate" class="sub-nav-link <?= str_contains($requestUri, 'gate') ? 'active' : ''; ?>" data-tooltip="Gate Check-in Portal">
                    <i data-lucide="shield-alert"></i>
                    <span class="nav-text">Gate Check-in Portal</span>
                </a>
                <?php endif; ?>

                <?php if (hasPermission('security.view')): ?>
                <a href="<?= BASE_URL ?>visitors" class="sub-nav-link <?= str_contains($requestUri, 'visitors') ? 'active' : ''; ?>" data-tooltip="Visitor Authorization">
                    <i data-lucide="qr-code"></i>
                    <span class="nav-text">Visitor Authorization</span>
                </a>
                <?php endif; ?>
            </div>
        </details>
        <?php endif; ?>

        <!-- Finance & Operations -->
        <?php if (hasPermission('billing.view') || hasPermission('maintenance.view')): ?>
        <div class="sidebar-section-header">Finance & Ops</div>
        <details class="nav-group" <?= $isFinanceGroupActive ? 'open' : '' ?>>
            <summary class="nav-group-summary" data-tooltip="Finance & Ops">
                <i data-lucide="landmark" class="nav-icon"></i>
                <span class="nav-text">Finance & Ops</span>
                <i data-lucide="chevron-right" class="caret-icon"></i>
            </summary>
            <div class="sub-menu">
                <?php if (hasPermission('billing.view')): ?>
                <a href="<?= BASE_URL ?>billing" class="sub-nav-link <?= (str_contains($requestUri, 'billing') || str_contains($requestUri, 'payments')) ? 'active' : ''; ?>" data-tooltip="Finance & Ledgers">
                    <i data-lucide="receipt"></i>
                    <span class="nav-text">Finance & Ledgers</span>
                </a>
                <?php endif; ?>

                <?php if (hasPermission('maintenance.view')): ?>
                <a href="<?= BASE_URL ?>maintenance" class="sub-nav-link <?= str_contains($requestUri, 'maintenance') ? 'active' : ''; ?>" data-tooltip="Maintenance Tickets">
                    <i data-lucide="wrench"></i>
                    <span class="nav-text">Maintenance Tickets</span>
                </a>
                <?php endif; ?>
            </div>
        </details>
        <?php endif; ?>

        <!-- System Administration -->
        <?php if (hasPermission('settings.manage') || hasPermission('settings.view')): ?>
        <div class="sidebar-section-header">System</div>
        <details class="nav-group" <?= $isSystemGroupActive ? 'open' : '' ?>>
            <summary class="nav-group-summary" data-tooltip="System Admin">
                <i data-lucide="sliders" class="nav-icon"></i>
                <span class="nav-text">System Admin</span>
                <i data-lucide="chevron-right" class="caret-icon"></i>
            </summary>
            <div class="sub-menu">
                <?php if (hasPermission('settings.manage')): ?>
                <a href="<?= BASE_URL ?>users" class="sub-nav-link <?= str_contains($requestUri, 'users') ? 'active' : ''; ?>" data-tooltip="System Users">
                    <i data-lucide="user-check"></i>
                    <span class="nav-text">System Users</span>
                </a>
                <?php endif; ?>
                <?php if (hasPermission('settings.view')): ?>
                <a href="<?= BASE_URL ?>roles" class="sub-nav-link <?= str_contains($requestUri, 'roles') ? 'active' : ''; ?>" data-tooltip="Roles & Permissions">
                    <i data-lucide="shield-check"></i>
                    <span class="nav-text">Roles & Permissions</span>
                </a>
                <a href="<?= BASE_URL ?>reports/audit_logs" class="sub-nav-link <?= str_contains($requestUri, 'audit_logs') ? 'active' : ''; ?>" data-tooltip="Audit Logs">
                    <i data-lucide="history"></i>
                    <span class="nav-text">Audit Logs</span>
                </a>
                <a href="<?= BASE_URL ?>settings" class="sub-nav-link <?= (str_contains($requestUri, 'settings') && !str_contains($requestUri, 'roles') && !str_contains($requestUri, 'users') && !str_contains($requestUri, 'audit_logs')) ? 'active' : ''; ?>" data-tooltip="System Settings">
                    <i data-lucide="settings"></i>
                    <span class="nav-text">System Settings</span>
                </a>
                <?php endif; ?>
            </div>
        </details>
        <?php endif; ?>

        <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 0.75rem 0;">

        <a href="<?= BASE_URL ?>logout.php" class="nav-link" data-tooltip="Logout">
            <i data-lucide="log-out" class="nav-icon"></i> <span class="nav-text">Logout</span>
        </a>
    </nav>
</aside>

<main class="main-content">
    <header class="top-bar">
        <div style="display: flex; align-items: center; gap: 1rem;">
            <button class="btn btn-secondary btn-sm" id="toggle-sidebar-btn" style="padding: 0.4rem 0.6rem;" title="Toggle Sidebar">
                <i data-lucide="menu"></i>
            </button>
            <span style="font-weight: 600; color: var(--text-muted); font-size: 0.9rem;"><?= e($estate_name ?? 'Residoria Estate') ?></span>
        </div>

        <!-- Universal Search Input & Dropdown Overlay -->
        <div style="position: relative; width: 100%; max-width: 420px;">
            <div style="position: relative;">
                <i data-lucide="search" style="position: absolute; left: 0.75rem; top: 50%; transform: translateY(-50%); width: 16px; height: 16px; color: var(--text-secondary);"></i>
                <input type="text" id="globalSearchInput" placeholder="Search Name, Phone, Plate, Unit, Pass Code..." onkeyup="handleGlobalSearch(this.value)" autocomplete="off" style="width: 100%; padding: 0.45rem 0.75rem 0.45rem 2.25rem; border-radius: 999px; border: 1px solid var(--border-color); font-size: 0.875rem; background: var(--hover-bg); transition: var(--transition);">
            </div>
            <div id="globalSearchResults" style="display: none; position: absolute; top: 110%; left: 0; right: 0; background: white; border-radius: 0.75rem; border: 1px solid var(--border-color); box-shadow: 0 10px 25px rgba(0,0,0,0.1); max-height: 400px; overflow-y: auto; z-index: 1100; padding: 0.5rem;"></div>
        </div>

        <div class="user-menu" style="display: flex; align-items: center; gap: 0.75rem;">
            <div style="text-align: right;">
                <div style="font-weight: 600; font-size: 0.9rem; color: var(--text-primary);"><?= e($user_name) ?></div>
                <div style="font-size: 0.75rem; color: var(--text-muted); font-weight: 500;"><?= e($user_role) ?></div>
            </div>
            <div class="user-avatar" style="width: 38px; height: 38px; background: var(--primary-light); color: var(--primary); font-weight: 700; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.9rem; border: 1px solid rgba(18,177,176,0.3);">
                <?= strtoupper(substr($user_name, 0, 2)) ?>
            </div>
        </div>
    </header>

<script>
let searchTimeout = null;
function handleGlobalSearch(q) {
    clearTimeout(searchTimeout);
    const resultsBox = document.getElementById('globalSearchResults');
    if (q.trim().length < 2) {
        resultsBox.style.display = 'none';
        return;
    }
    searchTimeout = setTimeout(() => {
        fetch(`<?= BASE_URL ?>ajax/universal_search.php?q=${encodeURIComponent(q)}`)
            .then(r => r.json())
            .then(res => {
                if (!res.success) return;
                const d = res.data;
                let html = '';
                
                // People
                if (d.persons && d.persons.length > 0) {
                    html += `<div style="font-size: 0.7rem; font-weight: 700; color: var(--text-secondary); text-transform: uppercase; padding: 0.4rem 0.5rem;">People Directory</div>`;
                    d.persons.forEach(p => {
                        html += `<a href="<?= BASE_URL ?>people" style="display: block; padding: 0.4rem 0.5rem; border-radius: 0.5rem; text-decoration: none; color: var(--text-primary); margin-bottom: 0.2rem;" onmouseover="this.style.background='var(--hover-bg)'" onmouseout="this.style.background='transparent'">
                            <strong>${p.name}</strong> <small style="color:var(--text-secondary)">(${p.primary_phone})</small>
                        </a>`;
                    });
                }

                // Properties
                if (d.properties && d.properties.length > 0) {
                    html += `<div style="font-size: 0.7rem; font-weight: 700; color: var(--primary); text-transform: uppercase; padding: 0.4rem 0.5rem; margin-top: 0.4rem;">Properties & Units</div>`;
                    d.properties.forEach(pr => {
                        html += `<a href="<?= BASE_URL ?>properties/view?id=${pr.id}" style="display: block; padding: 0.4rem 0.5rem; border-radius: 0.5rem; text-decoration: none; color: var(--text-primary); margin-bottom: 0.2rem;" onmouseover="this.style.background='var(--hover-bg)'" onmouseout="this.style.background='transparent'">
                            <strong>${pr.unit_code}</strong> <small style="color:var(--text-secondary)">${pr.building_name} (Unit ${pr.unit_number})</small>
                        </a>`;
                    });
                }

                // Vehicles
                if (d.vehicles && d.vehicles.length > 0) {
                    html += `<div style="font-size: 0.7rem; font-weight: 700; color: #0284c7; text-transform: uppercase; padding: 0.4rem 0.5rem; margin-top: 0.4rem;">Vehicles</div>`;
                    d.vehicles.forEach(v => {
                        html += `<a href="<?= BASE_URL ?>vehicles" style="display: block; padding: 0.4rem 0.5rem; border-radius: 0.5rem; text-decoration: none; color: var(--text-primary); margin-bottom: 0.2rem;" onmouseover="this.style.background='var(--hover-bg)'" onmouseout="this.style.background='transparent'">
                            <strong>${v.license_plate_number}</strong> <small style="color:var(--text-secondary)">${v.make} (${v.owner_name})</small>
                        </a>`;
                    });
                }

                // Visitors
                if (d.visitors && d.visitors.length > 0) {
                    html += `<div style="font-size: 0.7rem; font-weight: 700; color: #16a34a; text-transform: uppercase; padding: 0.4rem 0.5rem; margin-top: 0.4rem;">Visitor Passes</div>`;
                    d.visitors.forEach(v => {
                        html += `<a href="<?= BASE_URL ?>gate" style="display: block; padding: 0.4rem 0.5rem; border-radius: 0.5rem; text-decoration: none; color: var(--text-primary); margin-bottom: 0.2rem;" onmouseover="this.style.background='var(--hover-bg)'" onmouseout="this.style.background='transparent'">
                            <strong>${v.pass_code_formatted}</strong> <small style="color:var(--text-secondary)">${v.visitor_name}</small>
                        </a>`;
                    });
                }

                if (!html) {
                    html = `<div style="padding: 0.75rem; text-align: center; color: var(--text-secondary); font-size: 0.85rem;">No matching records found.</div>`;
                }

                resultsBox.innerHTML = html;
                resultsBox.style.display = 'block';
            });
    }, 250);
}

document.addEventListener('click', function(e) {
    const box = document.getElementById('globalSearchResults');
    const input = document.getElementById('globalSearchInput');
    if (box && input && !box.contains(e.target) && !input.contains(e.target)) {
        box.style.display = 'none';
    }
});
</script>

    <div class="content-wrapper">
