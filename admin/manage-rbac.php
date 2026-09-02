<!-- ===== ROLE PERMISSION EDITOR ===== -->
<?php
// Only rendered for admin — already gated in admin.php via $is_admin check
$rbac_all  = rbac_get_all($conn);
$all_roles   = RBAC_ROLES;
$all_sections= RBAC_SECTION_LABELS;

// Group sections for display
$section_groups = [
    'Operations' => ['dashboard','category-management','menu-management','combo-meals','orders','reservations','tables','kitchen','delivery'],
    'Finance'    => ['coupons','billing','expenses'],
    'Inventory'  => ['inventory','suppliers','purchases'],
    'People'     => ['customers','team','branches'],
    'Content'    => ['website-content','gallery','events','reviews'],
    'System'     => ['analytics','notifications','activity-log','settings','rbac'],
];
?>

<section id="rbac" class="admin-section">
    <div class="section-header">
        <div>
            <h2><i class="fas fa-shield-halved me-2"></i>Role Permission Editor</h2>
            <p>Control which sections each staff role can access</p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <button class="btn btn-success btn-sm" onclick="rbacSaveAll()">
                <i class="fas fa-save me-1"></i>Save All Changes
            </button>
            <button class="btn btn-outline-secondary btn-sm" onclick="rbacResetDefaults()">
                <i class="fas fa-undo me-1"></i>Reset Defaults
            </button>
        </div>
    </div>

    <!-- Role color badges (reference) -->
    <div class="d-flex flex-wrap gap-2 mb-4">
        <?php foreach ($all_roles as $rkey => $rlabel): ?>
            <span class="role-badge rb-<?= $rkey ?>"><i class="fas fa-user me-1"></i><?= $rlabel ?></span>
        <?php endforeach; ?>
        <span class="text-muted ms-2" style="font-size:.8rem;align-self:center">
            <i class="fas fa-info-circle me-1"></i>Admin always has full access and cannot be restricted.
        </span>
    </div>

    <!-- Unsaved changes alert -->
    <div id="rbacUnsavedBanner" style="display:none"
         class="mb-3 p-3 rounded d-flex align-items-center gap-3"
         style="background:#fefce8;border:1px solid #fde047">
        <i class="fas fa-circle-exclamation text-warning"></i>
        <span style="color:#854d0e;font-weight:600">You have unsaved changes. Click "Save All Changes" to apply.</span>
    </div>

    <!-- Permission Table -->
    <div class="analytics-card p-0" style="overflow:hidden">
        <div class="table-responsive">
            <table class="admin-table rbac-table" id="rbacTable">
                <thead>
                    <tr>
                        <th style="width:200px;position:sticky;left:0;z-index:2;background:#1a1a2e">
                            Section
                        </th>
                        <?php foreach ($all_roles as $rkey => $rlabel): ?>
                        <th class="text-center" style="min-width:100px">
                            <div class="role-badge rb-<?= $rkey ?>" style="justify-content:center;width:100%">
                                <?= $rlabel ?>
                            </div>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($section_groups as $groupLabel => $groupSections): ?>
                    <!-- Group header row -->
                    <tr class="rbac-group-row">
                        <td colspan="<?= count($all_roles) + 1 ?>"
                            style="background:#0f172a;color:#c9a74d;font-weight:700;font-size:.78rem;text-transform:uppercase;letter-spacing:.8px;padding:8px 16px">
                            <?= $groupLabel ?>
                            <button class="btn btn-sm ms-3" style="font-size:.7rem;padding:1px 8px;color:#c9a74d;border:1px solid #c9a74d;background:transparent"
                                    onclick="rbacToggleGroup('<?= $groupLabel ?>',true)">
                                All On
                            </button>
                            <button class="btn btn-sm" style="font-size:.7rem;padding:1px 8px;color:#94a3b8;border:1px solid #334155;background:transparent"
                                    onclick="rbacToggleGroup('<?= $groupLabel ?>',false)">
                                All Off
                            </button>
                        </td>
                    </tr>
                    <?php foreach ($groupSections as $sectionKey):
                        if (!isset($all_sections[$sectionKey])) continue;
                        $sectionLabel = $all_sections[$sectionKey];
                    ?>
                    <tr class="rbac-row" data-group="<?= $groupLabel ?>">
                        <td style="position:sticky;left:0;z-index:1;background:#1e293b;font-weight:600;color:#f1f5f9">
                            <?= htmlspecialchars($sectionLabel) ?>
                        </td>
                        <?php foreach ($all_roles as $rkey => $rlabel):
                            $checked = !empty($rbac_all[$rkey][$sectionKey]);
                        ?>
                        <td class="text-center">
                            <label class="rbac-toggle-wrap" title="<?= $rlabel ?> → <?= $sectionLabel ?>">
                                <input type="checkbox"
                                       class="rbac-chk"
                                       data-role="<?= $rkey ?>"
                                       data-section="<?= $sectionKey ?>"
                                       <?= $checked ? 'checked' : '' ?>
                                       onchange="rbacMarkDirty()">
                                <span class="rbac-toggle-slider"></span>
                            </label>
                        </td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Quick role presets -->
    <div class="analytics-card mt-4">
        <h5 class="mb-3"><i class="fas fa-bolt me-2 text-warning"></i>Quick Role Presets</h5>
        <p class="text-muted mb-3" style="font-size:.85rem">Apply preset permission profiles to a role. This will overwrite that role's current settings.</p>
        <div class="d-flex flex-wrap gap-3">
            <?php foreach ($all_roles as $rkey => $rlabel): ?>
            <div class="p-3 rounded" style="background:#f8fafc;border:1px solid #e2e8f0;min-width:180px">
                <div class="fw-bold mb-2" style="font-size:.85rem">
                    <span class="role-badge rb-<?= $rkey ?>"><?= $rlabel ?></span>
                </div>
                <div class="d-flex gap-1 flex-wrap">
                    <button class="btn btn-sm btn-outline-success" style="font-size:.72rem"
                            onclick="rbacApplyPreset('<?= $rkey ?>','default')">
                        <i class="fas fa-check me-1"></i>Default
                    </button>
                    <button class="btn btn-sm btn-outline-danger" style="font-size:.72rem"
                            onclick="rbacApplyPreset('<?= $rkey ?>','none')">
                        <i class="fas fa-times me-1"></i>None
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Real-time Sidebar Preview -->
    <div class="analytics-card mt-4" id="rbacPreviewCard">
        <div class="d-flex align-items-center justify-content-between mb-3">
            <h5 class="mb-0"><i class="fas fa-eye me-2 text-info"></i>Real-time Sidebar Preview</h5>
            <div class="d-flex align-items-center gap-2">
                <label class="text-muted me-1" style="font-size:.83rem;margin-bottom:0">Preview role:</label>
                <select id="rbacPreviewRole" class="form-select form-select-sm" style="width:auto;min-width:140px" onchange="rbacUpdatePreview()">
                    <?php foreach ($all_roles as $rkey => $rlabel): ?>
                    <option value="<?= $rkey ?>"><?= $rlabel ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <p class="text-muted mb-3" style="font-size:.82rem">
            <i class="fas fa-info-circle me-1"></i>
            This preview updates live as you toggle permissions above — showing exactly what the selected role will see in the sidebar after saving.
        </p>
        <div class="row g-3">
            <!-- Mini sidebar mockup -->
            <div class="col-md-4">
                <div id="rbacSidebarPreview" style="
                    background:#1a1a2e;
                    border-radius:10px;
                    padding:12px 0;
                    min-height:300px;
                    border:1px solid #334155;
                    overflow:hidden;
                ">
                    <!-- populated by JS -->
                </div>
            </div>
            <!-- Stats panel -->
            <div class="col-md-8">
                <div id="rbacPreviewStats" class="row g-2">
                    <!-- populated by JS -->
                </div>
                <div id="rbacPreviewSummary" class="mt-3 p-3 rounded" style="background:#f8fafc;border:1px solid #e2e8f0;font-size:.85rem;color:#475569">
                    <!-- summary text -->
                </div>
            </div>
        </div>
    </div>

    <!-- Current Session Info (who is logged in) -->
    <?php if (!$is_admin): ?>
    <div class="analytics-card mt-4" style="background:#fef2f2;border:1px solid #fecaca">
        <p style="color:#dc2626;font-size:.88rem;margin:0">
            <i class="fas fa-lock me-2"></i>
            You are viewing this panel as <strong><?= htmlspecialchars(ucfirst($user_role)) ?></strong>.
            Permission changes require Super Admin access.
        </p>
    </div>
    <?php endif; ?>
</section>

<style>
/* ── RBAC Table styles ─────────────────────────────────── */
.rbac-table thead tr th {
    background: #1a1a2e;
    color: #94a3b8;
    font-size: .75rem;
    text-transform: uppercase;
    letter-spacing: .5px;
    padding: 12px 10px;
    border-bottom: 2px solid #334155;
    white-space: nowrap;
}
.rbac-table tbody tr td {
    padding: 10px 12px;
    border-bottom: 1px solid #1e293b;
    font-size: .85rem;
}
.rbac-table tbody tr:hover td {
    background: rgba(201,167,77,.06);
}
.rbac-group-row td { padding: 8px 16px !important; }

/* Toggle switch */
.rbac-toggle-wrap {
    position: relative;
    display: inline-block;
    width: 40px;
    height: 22px;
    cursor: pointer;
}
.rbac-toggle-wrap input { opacity: 0; width: 0; height: 0; }
.rbac-toggle-slider {
    position: absolute;
    inset: 0;
    background: #334155;
    border-radius: 34px;
    transition: .25s;
}
.rbac-toggle-slider:before {
    content: '';
    position: absolute;
    width: 16px; height: 16px;
    left: 3px; bottom: 3px;
    background: #64748b;
    border-radius: 50%;
    transition: .25s;
}
.rbac-toggle-wrap input:checked + .rbac-toggle-slider {
    background: rgba(39,174,96,.25);
    border: 1px solid #27ae60;
}
.rbac-toggle-wrap input:checked + .rbac-toggle-slider:before {
    background: #27ae60;
    transform: translateX(18px);
}
.rbac-toggle-wrap input:disabled + .rbac-toggle-slider {
    opacity: .4;
    cursor: not-allowed;
}

/* Unsaved banner */
#rbacUnsavedBanner {
    background: #fefce8 !important;
    border: 1px solid #fde047 !important;
}
</style>

<script>
// ── RBAC default sections per role (mirrors PHP RBAC_SECTIONS) ────────────
const RBAC_DEFAULTS = <?php
    $defaults = [];
    foreach (RBAC_ROLES as $rkey => $_) {
        $defaults[$rkey] = [];
        foreach (RBAC_SECTIONS as $sec => $allowed_roles) {
            $defaults[$rkey][$sec] = in_array($rkey, $allowed_roles);
        }
    }
    echo json_encode($defaults);
?>;

let rbacDirty = false;

function rbacMarkDirty() {
    rbacDirty = true;
    document.getElementById('rbacUnsavedBanner').style.display = 'flex';
}

function rbacToggleGroup(group, val) {
    document.querySelectorAll(`.rbac-row[data-group="${group}"] .rbac-chk`).forEach(chk => {
        chk.checked = val;
    });
    rbacMarkDirty();
}

function rbacApplyPreset(role, preset) {
    const msg = preset === 'none'
        ? `Remove ALL permissions for ${role}?`
        : `Apply DEFAULT permissions for ${role}?`;
    if (!confirm(msg)) return;

    document.querySelectorAll(`.rbac-chk[data-role="${role}"]`).forEach(chk => {
        const section = chk.dataset.section;
        chk.checked = preset === 'default'
            ? (RBAC_DEFAULTS[role]?.[section] ?? false)
            : false;
    });
    rbacMarkDirty();
}

async function rbacSaveAll() {
    const checkboxes = document.querySelectorAll('.rbac-chk');
    const changes = [];
    checkboxes.forEach(chk => {
        changes.push({
            role:    chk.dataset.role,
            section: chk.dataset.section,
            allowed: chk.checked ? 1 : 0
        });
    });

    const btn = document.querySelector('[onclick="rbacSaveAll()"]');
    const origText = btn.innerHTML;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Saving...';
    btn.disabled = true;

    const res = await fetch('admin_api.php?action=save_rbac_permissions', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ permissions: changes })
    }).then(r => r.json());

    btn.innerHTML = origText;
    btn.disabled = false;

    if (res.status === 'success') {
        toast('Permissions saved! Changes take effect on next login.', 'success');
        rbacDirty = false;
        document.getElementById('rbacUnsavedBanner').style.display = 'none';
    } else {
        toast('Error: ' + (res.message || 'Save failed'), 'error');
    }
}

async function rbacResetDefaults() {
    if (!confirm('Reset ALL roles to default permissions? This cannot be undone.')) return;
    const res = await fetch('admin_api.php?action=rbac_reset_defaults', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({})
    }).then(r => r.json());

    if (res.status === 'success') {
        toast('Permissions reset to defaults.', 'success');
        // Apply defaults visually
        document.querySelectorAll('.rbac-chk').forEach(chk => {
            const role    = chk.dataset.role;
            const section = chk.dataset.section;
            chk.checked = RBAC_DEFAULTS[role]?.[section] ?? false;
        });
        rbacDirty = false;
        document.getElementById('rbacUnsavedBanner').style.display = 'none';
    } else {
        toast('Error: ' + res.message, 'error');
    }
}

// Warn before leaving page with unsaved changes
window.addEventListener('beforeunload', e => {
    if (rbacDirty) {
        e.preventDefault();
        e.returnValue = '';
    }
});

// ── Real-time Sidebar Preview ─────────────────────────────────────────────────

// Full sidebar nav structure (mirrors admin.php sidebar)
const SIDEBAR_NAV = [
    { group: 'Main', items: [
        { key: 'dashboard', icon: 'fa-gauge-high', label: 'Dashboard', always: true }
    ]},
    { group: 'Operations', items: [
        { key: 'category-management', icon: 'fa-tags',          label: 'Categories' },
        { key: 'menu-management',     icon: 'fa-utensils',      label: 'Menu Management' },
        { key: 'combo-meals',         icon: 'fa-layer-group',   label: 'Combo Meals' },
        { key: 'orders',              icon: 'fa-shopping-cart', label: 'Orders' },
        { key: 'reservations',        icon: 'fa-calendar-check',label: 'Reservations' },
        { key: 'tables',              icon: 'fa-chair',         label: 'Table Management' },
        { key: 'kitchen',             icon: 'fa-fire-burner',   label: 'Kitchen' },
        { key: 'delivery',            icon: 'fa-motorcycle',    label: 'Delivery' },
    ]},
    { group: 'Finance', items: [
        { key: 'coupons',  icon: 'fa-ticket',              label: 'Coupons & Offers' },
        { key: 'billing',  icon: 'fa-file-invoice-dollar', label: 'Billing & Payment' },
        { key: 'expenses', icon: 'fa-wallet',              label: 'Expenses' },
    ]},
    { group: 'Inventory', items: [
        { key: 'inventory',  icon: 'fa-boxes-stacked', label: 'Inventory' },
        { key: 'suppliers',  icon: 'fa-truck-field',   label: 'Suppliers' },
        { key: 'purchases',  icon: 'fa-cart-flatbed',  label: 'Purchases' },
    ]},
    { group: 'People', items: [
        { key: 'customers', icon: 'fa-users',    label: 'Customers' },
        { key: 'team',      icon: 'fa-id-badge', label: 'Staff Management' },
        { key: 'branches',  icon: 'fa-store',    label: 'Branches' },
    ]},
    { group: 'Content', items: [
        { key: 'website-content', icon: 'fa-globe',            label: 'Website Content' },
        { key: 'gallery',         icon: 'fa-images',           label: 'Gallery' },
        { key: 'events',          icon: 'fa-calendar-star',    label: 'Events' },
        { key: 'reviews',         icon: 'fa-star-half-stroke', label: 'Reviews' },
    ]},
    { group: 'Reports & System', items: [
        { key: 'analytics',     icon: 'fa-chart-bar',         label: 'Reports & Analytics' },
        { key: 'notifications', icon: 'fa-bell',              label: 'Notifications' },
        { key: 'activity-log',  icon: 'fa-clock-rotate-left', label: 'System Logs' },
        { key: 'settings',      icon: 'fa-sliders',           label: 'Settings' },
        { key: 'rbac',          icon: 'fa-shield-halved',     label: 'Role Permissions' },
    ]},
];

function rbacGetCurrentPermissions(role) {
    const perms = {};
    document.querySelectorAll(`.rbac-chk[data-role="${role}"]`).forEach(chk => {
        perms[chk.dataset.section] = chk.checked;
    });
    // dashboard always allowed
    perms['dashboard'] = true;
    return perms;
}

function rbacUpdatePreview() {
    const role = document.getElementById('rbacPreviewRole')?.value;
    if (!role) return;

    const perms = rbacGetCurrentPermissions(role);

    // Count allowed sections
    const allKeys = Object.keys(perms).filter(k => k !== 'dashboard');
    const allowed = Object.values(perms).filter(Boolean).length;
    const total   = allKeys.length + 1; // +1 for dashboard

    // Build mini sidebar HTML
    let html = `<div style="padding:8px 12px 4px;font-size:.65rem;color:#64748b;text-transform:uppercase;letter-spacing:.6px">Admin Panel Preview</div>`;

    let visibleGroups = 0;
    SIDEBAR_NAV.forEach(group => {
        const visibleItems = group.items.filter(item => item.always || perms[item.key]);
        if (!visibleItems.length) return;
        visibleGroups++;

        html += `<div style="padding:6px 12px 2px;font-size:.6rem;color:#64748b;text-transform:uppercase;letter-spacing:.5px;border-top:1px solid #1e293b;margin-top:4px">${group.group}</div>`;
        visibleItems.forEach(item => {
            html += `<div style="display:flex;align-items:center;gap:8px;padding:6px 14px;font-size:.75rem;color:#cbd5e1">
                <i class="fas ${item.icon}" style="width:14px;color:#c9a74d;text-align:center;font-size:.7rem"></i>
                <span>${item.label}</span>
            </div>`;
        });
    });

    if (visibleGroups === 0) {
        html += `<div style="padding:20px;text-align:center;color:#64748b;font-size:.75rem"><i class="fas fa-lock d-block mb-2" style="font-size:1.2rem"></i>No sections visible</div>`;
    }

    document.getElementById('rbacSidebarPreview').innerHTML = html;

    // Stats
    const pct = Math.round(allowed / total * 100);
    const pctColor = pct >= 75 ? '#22c55e' : pct >= 40 ? '#f59e0b' : '#ef4444';
    document.getElementById('rbacPreviewStats').innerHTML = `
        <div class="col-6"><div class="p-3 rounded text-center" style="background:#f0fdf4;border:1px solid #bbf7d0">
            <div style="font-size:1.4rem;font-weight:800;color:#166534">${allowed}</div>
            <div style="font-size:.72rem;color:#64748b">Sections Allowed</div>
        </div></div>
        <div class="col-6"><div class="p-3 rounded text-center" style="background:#fef2f2;border:1px solid #fecaca">
            <div style="font-size:1.4rem;font-weight:800;color:#dc2626">${total - allowed}</div>
            <div style="font-size:.72rem;color:#64748b">Sections Blocked</div>
        </div></div>
        <div class="col-12"><div class="p-2 rounded" style="background:#f8fafc;border:1px solid #e2e8f0">
            <div style="font-size:.72rem;color:#64748b;margin-bottom:4px">Access Level</div>
            <div style="background:#e2e8f0;border-radius:4px;height:8px;overflow:hidden">
                <div style="background:${pctColor};height:100%;width:${pct}%;transition:width .4s ease;border-radius:4px"></div>
            </div>
            <div style="font-size:.7rem;color:${pctColor};margin-top:3px;font-weight:700">${pct}% access</div>
        </div></div>
    `;

    // Summary
    const roleLabel = document.querySelector('#rbacPreviewRole option:checked')?.textContent || role;
    const groupNames = SIDEBAR_NAV
        .filter(g => g.items.some(i => i.always || perms[i.key]))
        .map(g => g.group)
        .join(', ');
    document.getElementById('rbacPreviewSummary').innerHTML = `
        <strong>${roleLabel}</strong> can access <strong>${allowed} of ${total}</strong> sections.
        ${allowed > 0 ? `Accessible groups: <em>${groupNames || 'Main only'}</em>.` : 'This role has <strong>no panel access</strong> beyond the dashboard.'}
        ${rbacDirty ? ' <span style="color:#f59e0b;font-weight:600"><i class="fas fa-triangle-exclamation me-1"></i>Unsaved changes — save to apply.</span>' : ''}
    `;
}

// Hook into rbacMarkDirty to also refresh preview
const _origMarkDirty = rbacMarkDirty;
// Re-define with preview refresh
rbacMarkDirty = function() {
    rbacDirty = true;
    document.getElementById('rbacUnsavedBanner').style.display = 'flex';
    rbacUpdatePreview();
};

// Also re-attach all existing checkboxes to the new rbacMarkDirty
document.querySelectorAll('.rbac-chk').forEach(chk => {
    chk.onchange = rbacMarkDirty;
});

// Initialize preview on load
document.addEventListener('DOMContentLoaded', () => {
    setTimeout(rbacUpdatePreview, 200);
});
// Also run now in case DOM is already ready
if (document.readyState !== 'loading') {
    setTimeout(rbacUpdatePreview, 100);
}
</script>
