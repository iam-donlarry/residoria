<!-- includes/property_drawer.php - Offcanvas Property Quick-View Drawer -->
<div id="propertyDrawer" class="drawer-overlay" style="display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.4); z-index: 1050; backdrop-filter: blur(4px); justify-content: flex-end;">
    <div class="drawer-content" style="width: 100%; max-width: 480px; background: white; height: 100%; box-shadow: -10px 0 25px rgba(0,0,0,0.1); display: flex; flex-direction: column; overflow-y: auto;">
        <div class="drawer-header" style="padding: 1.5rem; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: var(--hover-bg);">
            <div>
                <span class="badge badge-info" id="drawerUnitStatus">STATUS</span>
                <h3 id="drawerUnitCode" style="margin: 0.25rem 0 0 0; font-size: 1.25rem; font-weight: 700; color: var(--text-primary);">PROP-0000</h3>
            </div>
            <button type="button" onclick="closePropertyDrawer()" style="background: transparent; border: none; font-size: 1.25rem; cursor: pointer; color: var(--text-secondary);">&times;</button>
        </div>

        <div class="drawer-body" style="padding: 1.5rem; flex: 1;">
            <div id="drawerLoading" style="text-align: center; padding: 2rem 0;">
                <div class="spinner-border text-primary" role="status"></div>
                <p style="margin-top: 0.5rem; color: var(--text-secondary); font-size: 0.875rem;">Loading unit metrics...</p>
            </div>

            <div id="drawerDetails" style="display: none;">
                <div style="background: var(--primary-light); padding: 1rem; border-radius: 0.75rem; margin-bottom: 1.25rem;">
                    <div style="font-size: 0.75rem; color: var(--primary-color); font-weight: 600; text-transform: uppercase;">Head of Household</div>
                    <div id="drawerHeadName" style="font-size: 1.1rem; font-weight: 700; color: var(--text-primary); margin-top: 0.2rem;">Mr. John Ade</div>
                    <div id="drawerHeadPhone" style="font-size: 0.8125rem; color: var(--text-secondary);">+234 803 111 2222</div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; margin-bottom: 1.25rem;">
                    <div style="border: 1px solid var(--border-color); padding: 0.75rem; border-radius: 0.75rem;">
                        <small style="color: var(--text-secondary); font-weight: 600;">Perm. Population</small>
                        <div id="drawerPermOcc" style="font-size: 1.25rem; font-weight: 700;">4</div>
                    </div>
                    <div style="border: 1px solid var(--border-color); padding: 0.75rem; border-radius: 0.75rem;">
                        <small style="color: var(--text-secondary); font-weight: 600;">Daily Staff Today</small>
                        <div id="drawerDailyStaff" style="font-size: 1.25rem; font-weight: 700; color: #0284c7;">1</div>
                    </div>
                    <div style="border: 1px solid var(--border-color); padding: 0.75rem; border-radius: 0.75rem;">
                        <small style="color: var(--text-secondary); font-weight: 600;">Vehicles</small>
                        <div id="drawerVehicles" style="font-size: 1.25rem; font-weight: 700;">2</div>
                    </div>
                    <div style="border: 1px solid var(--border-color); padding: 0.75rem; border-radius: 0.75rem;">
                        <small style="color: var(--text-secondary); font-weight: 600;">Arrears</small>
                        <div id="drawerArrears" style="font-size: 1.25rem; font-weight: 700; color: #dc2626;">₦0.00</div>
                    </div>
                </div>

                <div style="border-top: 1px solid var(--border-color); padding-top: 1rem;">
                    <h4 style="font-size: 0.875rem; font-weight: 700; margin-bottom: 0.75rem;">Location & Category</h4>
                    <p id="drawerLocation" style="font-size: 0.875rem; color: var(--text-secondary); margin: 0;"></p>
                </div>
            </div>
        </div>

        <div class="drawer-footer" style="padding: 1.25rem; border-top: 1px solid var(--border-color); display: flex; flex-direction: column; gap: 0.5rem; background: var(--hover-bg);">
            <a id="drawerFullViewLink" href="#" class="btn btn-primary" style="width: 100%; text-align: center; justify-content: center;">
                <i data-lucide="external-link" style="width: 16px; height: 16px;"></i> Full 360° Inspection Page
            </a>
            <button type="button" onclick="closePropertyDrawer()" class="btn btn-secondary" style="width: 100%; text-align: center; justify-content: center;">Close</button>
        </div>
    </div>
</div>

<script>
function openPropertyDrawer(unitId) {
    const drawer = document.getElementById('propertyDrawer');
    const loading = document.getElementById('drawerLoading');
    const details = document.getElementById('drawerDetails');
    
    drawer.style.display = 'flex';
    loading.style.display = 'block';
    details.style.display = 'none';

    fetch(`<?= BASE_URL ?>ajax/get_property_summary.php?id=${unitId}`)
        .then(r => r.json())
        .then(res => {
            loading.style.display = 'none';
            if (res.success) {
                const data = res.data;
                document.getElementById('drawerUnitCode').innerText = data.unit_code;
                document.getElementById('drawerUnitStatus').innerText = data.status.toUpperCase().replace('_', ' ');
                document.getElementById('drawerHeadName').innerText = data.head_name || 'No Active Head';
                document.getElementById('drawerHeadPhone').innerText = data.head_phone || 'N/A';
                document.getElementById('drawerPermOcc').innerText = data.permanent_occupancy;
                document.getElementById('drawerDailyStaff').innerText = data.daily_staff_count;
                document.getElementById('drawerVehicles').innerText = data.vehicles_count;
                document.getElementById('drawerArrears').innerText = data.formatted_arrears;
                document.getElementById('drawerLocation').innerText = `${data.street_name} • ${data.building_name} (Floor ${data.floor_number}, Unit ${data.unit_number})`;
                document.getElementById('drawerFullViewLink').href = `<?= BASE_URL ?>modules/properties/view.php?id=${unitId}`;
                details.style.display = 'block';
                if (window.lucide) lucide.createIcons();
            } else {
                alert("Error fetching property summary.");
            }
        })
        .catch(err => {
            loading.style.display = 'none';
            alert("Failed to load property drawer data.");
        });
}

function closePropertyDrawer() {
    document.getElementById('propertyDrawer').style.display = 'none';
}
</script>
