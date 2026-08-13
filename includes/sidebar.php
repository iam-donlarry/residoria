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
                <a href="<?= BASE_URL ?>dashboard" title="Dashboard">
                    <i data-lucide="layout-dashboard"></i>
                    <span class="nav-text">Dashboard</span>
                </a>
            </li>
            
            <li class="nav-label">Estate Structure</li>
            <?php if (hasPermission('properties.view')): ?>
            <li class="<?= str_contains($requestUri, 'properties') ? 'active' : ''; ?>">
                <a href="<?= BASE_URL ?>properties" title="Properties & Streets">
                    <i data-lucide="building"></i>
                    <span class="nav-text">Properties & Units</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (hasPermission('people.view')): ?>
            <li class="<?= (str_contains($requestUri, 'people') && !str_contains($requestUri, 'roles')) ? 'active' : ''; ?>">
                <a href="<?= BASE_URL ?>people" title="Persons Directory">
                    <i data-lucide="user-check"></i>
                    <span class="nav-text">Persons Directory</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (hasPermission('owners.view')): ?>
            <li class="<?= str_contains($requestUri, 'owners') ? 'active' : ''; ?>">
                <a href="<?= BASE_URL ?>owners" title="Property Owners">
                    <i data-lucide="award"></i>
                    <span class="nav-text">Property Owners</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (hasPermission('residents.view')): ?>
            <li class="<?= str_contains($requestUri, 'residents') ? 'active' : ''; ?>">
                <a href="<?= BASE_URL ?>residents" title="Residents Directory">
                    <i data-lucide="users"></i>
                    <span class="nav-text">Residents & Occupancy</span>
                </a>
            </li>
            <li class="<?= str_contains($requestUri, 'households') ? 'active' : ''; ?>">
                <a href="<?= BASE_URL ?>households" title="Household Members">
                    <i data-lucide="heart-handshake"></i>
                    <span class="nav-text">Household Members</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (hasPermission('commercial.view')): ?>
            <li class="<?= str_contains($requestUri, 'commercial') ? 'active' : ''; ?>">
                <a href="<?= BASE_URL ?>commercial" title="Commercial Occupants">
                    <i data-lucide="store"></i>
                    <span class="nav-text">Commercial Businesses</span>
                </a>
            </li>
            <?php endif; ?>

            <li class="nav-label">Personnel & Vehicles</li>
            <?php if (hasPermission('residents.view')): ?>
            <li class="<?= str_contains($requestUri, 'domestic-staff') ? 'active' : ''; ?>">
                <a href="<?= BASE_URL ?>domestic-staff" title="Domestic Staff">
                    <i data-lucide="user-cog"></i>
                    <span class="nav-text">Domestic Staff</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (hasPermission('staff.view')): ?>
            <li class="<?= str_contains($requestUri, 'estate-staff') ? 'active' : ''; ?>">
                <a href="<?= BASE_URL ?>estate-staff" title="Estate Staff">
                    <i data-lucide="badge-check"></i>
                    <span class="nav-text">Estate Staff</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (hasPermission('vehicles.view')): ?>
            <li class="<?= str_contains($requestUri, 'vehicles') ? 'active' : ''; ?>">
                <a href="<?= BASE_URL ?>vehicles" title="Vehicle Register">
                    <i data-lucide="car"></i>
                    <span class="nav-text">Vehicle Register</span>
                </a>
            </li>
            <?php endif; ?>

            <li class="nav-label">Gate Security</li>
            <?php if (hasPermission('security.gate')): ?>
            <li class="<?= str_contains($requestUri, 'gate') ? 'active' : ''; ?>">
                <a href="<?= BASE_URL ?>gate" title="Gate Portal">
                    <i data-lucide="shield-alert"></i>
                    <span class="nav-text">Gate Check-in Portal</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (hasPermission('security.view')): ?>
            <li class="<?= str_contains($requestUri, 'visitors') ? 'active' : ''; ?>">
                <a href="<?= BASE_URL ?>visitors" title="Visitor Passes">
                    <i data-lucide="qr-code"></i>
                    <span class="nav-text">Visitor Authorization</span>
                </a>
            </li>
            <?php endif; ?>

            <li class="nav-label">Finance & Ops</li>
            <?php if (hasPermission('billing.view')): ?>
            <li class="<?= (str_contains($requestUri, 'billing') || str_contains($requestUri, 'payments')) ? 'active' : ''; ?>">
                <a href="<?= BASE_URL ?>billing" title="Finance & Billing">
                    <i data-lucide="receipt"></i>
                    <span class="nav-text">Finance & Ledgers</span>
                </a>
            </li>
            <?php endif; ?>

            <?php if (hasPermission('maintenance.view')): ?>
            <li class="<?= str_contains($requestUri, 'maintenance') ? 'active' : ''; ?>">
                <a href="<?= BASE_URL ?>maintenance" title="Maintenance">
                    <i data-lucide="wrench"></i>
                    <span class="nav-text">Maintenance Tickets</span>
                </a>
            </li>
            <?php endif; ?>

            <li class="nav-label">System</li>
            <?php if (hasPermission('settings.manage')): ?>
            <li class="<?= str_contains($requestUri, 'users') ? 'active' : ''; ?>">
                <a href="<?= BASE_URL ?>users" title="System Users & Accounts">
                    <i data-lucide="user-check"></i>
                    <span class="nav-text">System Users</span>
                </a>
            </li>
            <?php endif; ?>
            <?php if (hasPermission('settings.view')): ?>
            <li class="<?= str_contains($requestUri, 'roles') ? 'active' : ''; ?>">
                <a href="<?= BASE_URL ?>roles" title="Roles & Permissions">
                    <i data-lucide="shield-check"></i>
                    <span class="nav-text">Roles & Permissions</span>
                </a>
            </li>
            <li class="<?= (str_contains($requestUri, 'settings') && !str_contains($requestUri, 'roles') && !str_contains($requestUri, 'users')) ? 'active' : ''; ?>">
                <a href="<?= BASE_URL ?>settings" title="System Settings">
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
