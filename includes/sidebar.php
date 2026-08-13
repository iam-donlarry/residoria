<!-- includes/sidebar.php - Dynamic Standalone Navigation Sidebar -->
<?php
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$user_name = $_SESSION['name'] ?? $_SESSION['user_name'] ?? 'Admin User';
$user_role = ucfirst($_SESSION['role'] ?? $_SESSION['user_role'] ?? 'Admin');
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
        <ul>
            <li class="nav-label">Main</li>
            <li class="<?= (str_contains($requestUri, 'dashboard')) ? 'active' : ''; ?>">
                <a href="<?= BASE_URL ?>modules/dashboard" title="Dashboard">
                    <i data-lucide="layout-dashboard"></i>
                    <span class="nav-text">Dashboard</span>
                </a>
            </li>
            
            <li class="nav-label">Estate Structure</li>
            <?php if (hasPermission('properties.view')): ?>
            <li class="<?= str_contains($requestUri, 'properties') ? 'active' : ''; ?>">
                <a href="<?= BASE_URL ?>modules/properties" title="Properties & Streets">
                    <i data-lucide="building"></i>
                    <span class="nav-text">Properties & Units</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (hasPermission('people.view')): ?>
            <li class="<?= (str_contains($requestUri, 'people') && !str_contains($requestUri, 'roles')) ? 'active' : ''; ?>">
                <a href="<?= BASE_URL ?>modules/people" title="Persons Directory">
                    <i data-lucide="user-check"></i>
                    <span class="nav-text">Persons Directory</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (hasPermission('owners.view')): ?>
            <li class="<?= str_contains($requestUri, 'owners') ? 'active' : ''; ?>">
                <a href="<?= BASE_URL ?>modules/owners" title="Property Owners">
                    <i data-lucide="award"></i>
                    <span class="nav-text">Property Owners</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (hasPermission('residents.view')): ?>
            <li class="<?= str_contains($requestUri, 'residents') ? 'active' : ''; ?>">
                <a href="<?= BASE_URL ?>modules/residents" title="Residents Directory">
                    <i data-lucide="users"></i>
                    <span class="nav-text">Residents & Occupancy</span>
                </a>
            </li>
            <li class="<?= str_contains($requestUri, 'households') ? 'active' : ''; ?>">
                <a href="<?= BASE_URL ?>modules/households" title="Household Members">
                    <i data-lucide="heart-handshake"></i>
                    <span class="nav-text">Household Members</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (hasPermission('commercial.view')): ?>
            <li class="<?= str_contains($requestUri, 'commercial') ? 'active' : ''; ?>">
                <a href="<?= BASE_URL ?>modules/commercial" title="Commercial Occupants">
                    <i data-lucide="store"></i>
                    <span class="nav-text">Commercial Businesses</span>
                </a>
            </li>
            <?php endif; ?>

            <li class="nav-label">Personnel & Vehicles</li>
            <?php if (hasPermission('residents.view')): ?>
            <li class="<?= str_contains($requestUri, 'domestic-staff') ? 'active' : ''; ?>">
                <a href="<?= BASE_URL ?>modules/domestic-staff" title="Domestic Staff">
                    <i data-lucide="user-cog"></i>
                    <span class="nav-text">Domestic Staff</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (hasPermission('staff.view')): ?>
            <li class="<?= str_contains($requestUri, 'estate-staff') ? 'active' : ''; ?>">
                <a href="<?= BASE_URL ?>modules/estate-staff" title="Estate Staff">
                    <i data-lucide="badge-check"></i>
                    <span class="nav-text">Estate Staff</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (hasPermission('vehicles.view')): ?>
            <li class="<?= str_contains($requestUri, 'vehicles') ? 'active' : ''; ?>">
                <a href="<?= BASE_URL ?>modules/vehicles" title="Vehicle Register">
                    <i data-lucide="car"></i>
                    <span class="nav-text">Vehicle Register</span>
                </a>
            </li>
            <?php endif; ?>

            <li class="nav-label">Gate Security</li>
            <?php if (hasPermission('security.gate')): ?>
            <li class="<?= str_contains($requestUri, 'gate') ? 'active' : ''; ?>">
                <a href="<?= BASE_URL ?>modules/gate" title="Gate Portal">
                    <i data-lucide="shield-alert"></i>
                    <span class="nav-text">Gate Check-in Portal</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (hasPermission('security.view')): ?>
            <li class="<?= str_contains($requestUri, 'visitors') ? 'active' : ''; ?>">
                <a href="<?= BASE_URL ?>modules/visitors" title="Visitor Passes">
                    <i data-lucide="qr-code"></i>
                    <span class="nav-text">Visitor Authorization</span>
                </a>
            </li>
            <?php endif; ?>

            <li class="nav-label">Finance & Ops</li>
            <?php if (hasPermission('billing.view')): ?>
            <li class="<?= (str_contains($requestUri, 'billing') || str_contains($requestUri, 'payments')) ? 'active' : ''; ?>">
                <a href="<?= BASE_URL ?>modules/billing" title="Finance & Billing">
                    <i data-lucide="receipt"></i>
                    <span class="nav-text">Finance & Ledgers</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (hasPermission('maintenance.view')): ?>
            <li class="<?= str_contains($requestUri, 'maintenance') ? 'active' : ''; ?>">
                <a href="<?= BASE_URL ?>modules/maintenance" title="Maintenance">
                    <i data-lucide="wrench"></i>
                    <span class="nav-text">Maintenance Tickets</span>
                </a>
            </li>
            <?php endif; ?>

            <li class="nav-label">System</li>
            <?php if (hasPermission('settings.view')): ?>
            <li class="<?= str_contains($requestUri, 'roles') ? 'active' : ''; ?>">
                <a href="<?= BASE_URL ?>modules/roles" title="Roles & Permissions">
                    <i data-lucide="shield-check"></i>
                    <span class="nav-text">Roles & Permissions</span>
                </a>
            </li>
            <li class="<?= (str_contains($requestUri, 'settings') && !str_contains($requestUri, 'roles')) ? 'active' : ''; ?>">
                <a href="<?= BASE_URL ?>modules/settings" title="System Settings">
                    <i data-lucide="settings"></i>
                    <span class="nav-text">System Settings</span>
                </a>
            </li>
            <?php endif; ?>
            <li>
                <a href="<?= BASE_URL ?>logout.php" title="Logout">
                    <i data-lucide="log-out"></i>
                    <span class="nav-text">Logout</span>
                </a>
            </li>
        </ul>
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
    <div class="content-wrapper">
