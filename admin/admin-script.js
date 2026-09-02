// =========================================
// SUPER ADMIN PANEL — Feliciano Restaurant
// =========================================
'use strict';

// ---- API Helper ----
async function api(action, method = 'GET', data = null, isFormData = false) {
    const url = `admin_api.php?action=${action}`;
    const opts = { method };
    if (data && !isFormData) {
        opts.headers = { 'Content-Type': 'application/json' };
        opts.body = JSON.stringify(data);
    } else if (isFormData) {
        opts.body = data; // FormData
    }
    try {
        const r = await fetch(url, opts);
        return await r.json();
    } catch (e) {
        console.error('API Error:', e);
        return { status: 'error', message: 'Connection failed' };
    }
}

// ---- Sidebar Toggle ----
function toggleSidebar() {
    const sidebar = document.getElementById('adminSidebar');
    const main = document.getElementById('adminMain');
    if (window.innerWidth <= 768) {
        sidebar.classList.toggle('mobile-open');
    } else {
        sidebar.classList.toggle('collapsed');
        main.classList.toggle('expanded');
    }
}

// ---- Navigation ----
function showSection(id, linkEl) {
    // RBAC client-side guard
    if (typeof RBAC_ALLOWED_SECTIONS !== 'undefined' && !RBAC_ALLOWED_SECTIONS.includes(id)) {
        toast('Access denied: you do not have permission to view this section.', 'error');
        return;
    }

    document.querySelectorAll('.admin-section').forEach(s => s.classList.remove('active'));
    document.querySelectorAll('.nav-link').forEach(l => l.classList.remove('active'));
    const sec = document.getElementById(id);
    if (sec) sec.classList.add('active');
    if (linkEl) linkEl.classList.add('active');

    // localStorage + URL hash দুটোতেই save করো
    try { localStorage.setItem('adminSection', id); } catch(e) {}
    history.replaceState(null, '', '#' + id);

    const refreshMap = {
        dashboard: loadDashboard,
        'category-management': loadCategories,
        'menu-management': loadMenuItems,
        'combo-meals': loadCombos,
        orders: loadOrders,
        reservations: loadReservations,
        tables: loadTables,
        kitchen: loadKitchenOrders,
        delivery: loadDeliveryOrders,
        customers: loadCustomers,
        team: loadTeam,
        branches: loadBranches,
        coupons: loadCoupons,
        billing: loadBilling,
        expenses: loadExpenses,
        inventory: loadInventory,
        suppliers: loadSuppliers,
        purchases: loadPurchases,
        'website-content': loadWebsiteContent,
        gallery: loadGallery,
        events: loadEvents,
        reviews: loadReviews,
        analytics: loadAnalytics,
        notifications: loadNotifications,
        'activity-log': loadActivityLog,
        settings: loadSettings,
        rbac: () => {}, // static PHP render, no JS load needed
    };
    if (refreshMap[id]) refreshMap[id]();
}

// ---- Logout ----
function logout() {
    if (confirm('Are you sure you want to log out?')) {
        window.location.href = '../auth/logout.php';
    }
}

// =========================================
// PAGE INIT
// =========================================
document.addEventListener('DOMContentLoaded', () => {
    const validSections = [
        'dashboard','category-management','menu-management','combo-meals','orders','reservations',
        'tables','kitchen','delivery','customers','team','branches',
        'coupons','billing','expenses','inventory','suppliers','purchases',
        'website-content','gallery','events','reviews','analytics',
        'notifications','activity-log','settings','rbac'
    ];

    // ── RBAC: show role banner for non-admin staff ──
    if (typeof RBAC_IS_ADMIN !== 'undefined' && !RBAC_IS_ADMIN) {
        showRoleBanner(RBAC_USER_ROLE);
    }

    // priority: URL hash → localStorage → default dashboard
    const hash = window.location.hash.replace('#', '');
    let savedSection = '';
    try { savedSection = localStorage.getItem('adminSection') || ''; } catch(e) {}

    let startSection = 'dashboard';
    if (hash && validSections.includes(hash)) {
        // RBAC: block if not allowed
        if (typeof RBAC_ALLOWED_SECTIONS !== 'undefined' && !RBAC_ALLOWED_SECTIONS.includes(hash)) {
            startSection = 'dashboard';
        } else {
            startSection = hash;
        }
    } else if (savedSection && validSections.includes(savedSection)) {
        if (typeof RBAC_ALLOWED_SECTIONS !== 'undefined' && !RBAC_ALLOWED_SECTIONS.includes(savedSection)) {
            startSection = 'dashboard';
        } else {
            startSection = savedSection;
        }
    }

    const startLink = document.querySelector(`.nav-link[onclick*="'${startSection}'"]`);
    showSection(startSection, startLink);

    // Dashboard auto-refresh চলতে থাকবে background-এ
    setInterval(loadDashboard, 60000);
    setInterval(updateNotificationIndicator, 30000);
    updateNotificationIndicator();
    setupFormHandlers();
    initAnalyticsDates();
});

// ── Role welcome banner for non-admin staff ──────────────────────────────
function showRoleBanner(role) {
    const roleConfig = {
        chef:         { label: 'Chef',         icon: 'fa-fire-burner',    color: '#e67e22', sections: 'Dashboard, Menu, Kitchen' },
        waiter:       { label: 'Waiter',        icon: 'fa-bell-concierge', color: '#3498db', sections: 'Dashboard, Orders, Reservations, Tables' },
        cashier:      { label: 'Cashier',       icon: 'fa-cash-register',  color: '#27ae60', sections: 'Dashboard, Orders, Billing, Customers' },
        receptionist: { label: 'Receptionist',  icon: 'fa-phone-volume',   color: '#9b59b6', sections: 'Dashboard, Reservations, Tables' },
        delivery_boy: { label: 'Delivery Boy',  icon: 'fa-motorcycle',     color: '#1abc9c', sections: 'Dashboard, Orders, Delivery' },
        cleaner:      { label: 'Cleaner',       icon: 'fa-broom',          color: '#95a5a6', sections: 'Dashboard only' },
        staff:        { label: 'Staff',         icon: 'fa-id-badge',       color: '#7f8c8d', sections: 'Dashboard only' },
    };
    const cfg = roleConfig[role] || { label: role, icon: 'fa-user', color: '#7f8c8d', sections: 'Dashboard' };

    // Insert banner at the top of main content
    const main = document.getElementById('adminMain');
    if (!main) return;
    const banner = document.createElement('div');
    banner.id = 'roleBanner';
    banner.style.cssText = `
        margin: 16px 20px 0;
        padding: 12px 20px;
        border-radius: 10px;
        background: linear-gradient(135deg, ${cfg.color}18, ${cfg.color}08);
        border: 1px solid ${cfg.color}44;
        display: flex;
        align-items: center;
        gap: 14px;
        font-size: .88rem;
    `;
    banner.innerHTML = `
        <div style="width:38px;height:38px;border-radius:50%;background:${cfg.color};display:flex;align-items:center;justify-content:center;flex-shrink:0">
            <i class="fas ${cfg.icon}" style="color:#fff;font-size:.95rem"></i>
        </div>
        <div>
            <div style="font-weight:700;color:#0f172a">
                Welcome — <span style="color:${cfg.color}">${cfg.label}</span> Panel
            </div>
            <div style="color:#64748b;font-size:.8rem;margin-top:2px">
                <i class="fas fa-shield-halved me-1" style="color:${cfg.color}"></i>
                Your access is limited to: <strong>${cfg.sections}</strong>
            </div>
        </div>
        <button onclick="this.parentElement.remove()" style="margin-left:auto;background:none;border:none;color:#94a3b8;font-size:1.1rem;cursor:pointer;line-height:1">
            <i class="fas fa-times"></i>
        </button>
    `;
    main.insertBefore(banner, main.firstChild);
}

// =========================================
// DASHBOARD — Redesigned
// =========================================

// ---- Sales Overview Line Chart ----
async function loadWeeklySalesBar() {
    const res = await api('get_daily_sales');
    const totalEl = document.getElementById('weeklySalesTotal');
    if (res.status !== 'success' || !res.data || !res.data.length) return;

    const sales = [...res.data].reverse();
    const total = sales.reduce((s, r) => s + parseFloat(r.total_revenue), 0);
    if (totalEl) totalEl.textContent = fmt(total);

    // Sales Overview line chart
    destroyChart('salesOverviewChart');
    const ctx = document.getElementById('salesOverviewChart');
    if (!ctx) return;
    _charts['salesOverviewChart'] = new Chart(ctx, {
        type: 'line',
        data: {
            labels: sales.map(s => new Date(s.sale_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })),
            datasets: [{
                label: 'Revenue (TK)',
                data: sales.map(s => parseFloat(s.total_revenue)),
                borderColor: '#f59e0b',
                backgroundColor: 'rgba(245,158,11,0.08)',
                borderWidth: 2.5,
                pointRadius: 4,
                pointBackgroundColor: '#f59e0b',
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                fill: true,
                tension: 0.45,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1e293b',
                    titleColor: '#fff',
                    bodyColor: '#e2e8f0',
                    padding: 10,
                    callbacks: { label: ctx => `TK ${fmt(ctx.raw)}` }
                }
            },
            scales: {
                y: {
                    ticks: { callback: v => 'TK ' + fmt(v), font: { size: 10 }, color: '#94a3b8' },
                    grid: { color: '#f1f5f9' },
                    beginAtZero: true
                },
                x: {
                    ticks: { font: { size: 10 }, color: '#94a3b8' },
                    grid: { display: false }
                }
            }
        }
    });
}

// ---- Kitchen Status Donut ----
async function loadDashboardKitchen() {
    const res = await api('get_kitchen');
    if (res.status !== 'success') return;
    const orders = res.data || [];
    const counts = { pending: 0, confirmed: 0, preparing: 0, ready: 0 };
    orders.forEach(o => { if (counts[o.status] !== undefined) counts[o.status]++; });
    const total = Object.values(counts).reduce((a, b) => a + b, 0);
    const totalEl = document.getElementById('kitchenTotalNum');
    if (totalEl) totalEl.textContent = total;

    // Legend numbers
    ['pending','confirmed','preparing','ready'].forEach(s => {
        const el = document.getElementById('kLeg' + s.charAt(0).toUpperCase() + s.slice(1));
        if (el) el.textContent = counts[s];
    });

    // Donut segments (SVG stroke-dasharray)
    const circ = 2 * Math.PI * 38; // circumference ≈ 238.76
    const colors = { pending: '#f39c12', confirmed: '#3498db', preparing: '#e67e22', ready: '#27ae60' };
    const ids    = { pending: 'kDonutPending', confirmed: 'kDonutConfirmed', preparing: 'kDonutPreparing', ready: 'kDonutReady' };
    let offset = 0;
    if (total === 0) return;
    Object.entries(counts).forEach(([status, count]) => {
        const el = document.getElementById(ids[status]);
        if (!el) return;
        const arc = (count / total) * circ;
        el.setAttribute('stroke-dasharray', `${arc} ${circ - arc}`);
        el.setAttribute('stroke-dashoffset', -offset);
        offset += arc;
    });
}

// ---- Delivery Status ----
async function loadDashboardDelivery() {
    const res = await api('get_delivery');
    if (res.status !== 'success') return;
    const orders = res.data || [];
    const today = new Date().toDateString();
    const todayDel = orders.filter(o => new Date(o.created_at).toDateString() === today);
    const awaiting  = todayDel.filter(o => ['pending','confirmed'].includes(o.status)).length;
    const outForDel = todayDel.filter(o => o.status === 'out_for_delivery').length;
    const delivered = todayDel.filter(o => o.status === 'completed').length;

    const setNum = (id, val) => { const e = document.getElementById(id); if (e) e.textContent = val; };
    setNum('delAwaiting', awaiting);
    setNum('delOut', outForDel);
    setNum('delDelivered', delivered);
}

// =========================================
// CHART INSTANCES (prevent memory leaks on re-render)
// =========================================
const _charts = {};
function destroyChart(id) {
    if (_charts[id]) { _charts[id].destroy(); delete _charts[id]; }
}

// =========================================
async function loadDashboard() {
    const [statsRes, ordersRes, resvRes, reviewRes] = await Promise.all([
        api('get_stats'),
        api('get_orders'),
        api('get_reservations'),
        api('get_reviews')
    ]);

    if (statsRes.status === 'success') {
        const d = statsRes.data;

        // Primary KPI — all from server (MySQL CURDATE), no JS date filtering
        setEl('todayOrders',    d.today_orders    ?? 0);
        setEl('todayRevenue',   `TK ${fmt(d.today_revenue ?? 0)}`);
        setEl('pendingOrders',  d.pending_orders  ?? 0);
        setEl('totalReservations', d.today_reservations ?? 0);

        // Secondary stats
        setEl('completedOrders', d.completed_orders ?? 0);
        setEl('cancelledOrders', d.cancelled_orders ?? 0);
        setEl('totalMenuItems',  d.menu_items       ?? 0);
        setEl('totalCustomers',  d.total_customers  ?? 0);
        setEl('monthlyRevenue',  `TK ${fmt(d.monthly_revenue ?? 0)}`);

        // KPI change indicators — server-calculated yesterday vs today
        _setChange('ordersChangePct',  d.today_orders,    d.yesterday_orders,  false);
        _setChange('revenueChangePct', d.today_revenue,   d.yesterday_revenue, true);
        _setChange('resvChangePct',    d.today_reservations, d.yesterday_reservations, false);
    }

    if (ordersRes.status === 'success') {
        const orders = ordersRes.data || [];

        // Order status chart uses all orders (not just today)
        renderOrderStatusChart(orders);
        setEl('osTotalOrders', orders.length);

        // Recent orders — latest 5
        renderRecentOrders(orders.slice(0, 5));
    }

    if (resvRes.status === 'success') {
        // upcoming reservations (sidebar widget — kept for other sections)
        renderUpcomingReservations(resvRes.data || []);
    }

    if (reviewRes && reviewRes.status === 'success') {
        const pendingRv = (reviewRes.data || []).filter(rv => rv.status === 'pending').length;
        setEl('pendingReviews', pendingRv);
    }

    loadDashboardKitchen();
    loadDashboardDelivery();
    loadWeeklySalesBar();
    loadRevenueCharts();
    loadLowStockAlert();
    loadAttendanceSummary();
    loadSalesComparison();
    loadDashPopularItems();
}

// Helper: set KPI change badge
function _setChange(elId, cur, prev, isMoney) {
    const el = document.getElementById(elId);
    if (!el) return;
    if (!prev && prev !== 0) { el.innerHTML = ''; return; }
    const diff = cur - prev;
    const pct  = prev > 0 ? Math.abs(Math.round((diff / prev) * 100)) : 0;
    const up   = diff >= 0;
    const label = isMoney
        ? (up ? `+TK ${fmt(Math.abs(diff))}` : `-TK ${fmt(Math.abs(diff))}`)
        : (up ? `+${pct}%` : `-${pct}%`);
    el.className = `db-kpi-change ${up ? 'positive' : 'negative'}`;
    el.innerHTML = `<i class="fas fa-arrow-${up ? 'up' : 'down'}"></i> ${label} vs yesterday`;
}

function renderOrderStatusChart(orders) {
    const counts = { pending: 0, preparing: 0, ready: 0, completed: 0, cancelled: 0 };
    orders.forEach(o => { if (counts[o.status] !== undefined) counts[o.status]++; });
    const total = orders.length || 1;
    const colors = {
        pending:   '#f59e0b',
        preparing: '#3b82f6',
        ready:     '#8b5cf6',
        completed: '#22c55e',
        cancelled: '#ef4444'
    };
    const el = document.getElementById('orderStatusChart');
    if (!el) return;
    el.innerHTML = Object.entries(counts).map(([status, count]) => `
        <div class="db-os-row">
            <span class="db-os-label">${status}</span>
            <div class="db-os-bar-track">
                <div class="db-os-bar-fill" style="width:${(count/total*100).toFixed(1)}%;background:${colors[status]}"></div>
            </div>
            <span class="db-os-count">${count}</span>
        </div>`).join('');
}

function renderRecentOrders(orders) {
    const el = document.getElementById('recentOrdersList');
    if (!el) return;
    if (!orders.length) {
        el.innerHTML = '<div style="text-align:center;color:#94a3b8;padding:20px;font-size:.82rem">No orders yet</div>';
        return;
    }
    el.innerHTML = orders.map((o, i) => {
        const badge = `db-badge-${o.status}`;
        return `<div class="db-recent-row">
            <span class="db-recent-id">#ORD-${String(1000 + i + 1)}</span>
            <span class="db-recent-name">${o.customer_name || 'Customer'}</span>
            <span class="db-recent-amt">TK ${fmt(o.total)}</span>
            <span class="db-recent-badge ${badge}">${o.status}</span>
        </div>`;
    }).join('');
}

function renderUpcomingReservations(resvs) {
    const el = document.getElementById('upcomingReservationsList');
    if (!el) return;
    const upcoming = resvs.filter(r => r.status !== 'cancelled' && new Date(r.reservation_date) >= new Date()).slice(0, 5);
    if (!upcoming.length) { el.innerHTML = '<div class="activity-item"><i class="fas fa-calendar"></i><span>No upcoming reservations</span></div>'; return; }
    el.innerHTML = upcoming.map(r => `
        <div class="activity-item">
            <i class="fas fa-calendar-check" style="color:#9b59b6"></i>
            <span><strong>${r.customer_name}</strong> — ${r.guests_count} guests</span>
            <small>${r.reservation_date} ${r.reservation_time}</small>
        </div>`).join('');
}

function renderDashPopular(orders) {
    // এই function টি এখন শুধু fallback হিসেবে থাকবে
    // মূল call loadDashPopularItems() করে
}

// Popular Items — dedicated API call with image_url
async function loadDashPopularItems() {
    const el = document.getElementById('dashPopularItems');
    if (!el) return;

    const res = await api('get_popular_items');
    if (res.status !== 'success' || !res.data || !res.data.length) {
        el.innerHTML = '<p style="text-align:center;color:#94a3b8;font-size:.82rem;padding:20px 0">No data available</p>';
        return;
    }

    const baseUrl = '../';
    const fallback = '../assets/images/menu/default.jpg';

    el.innerHTML = res.data.map(item => {
        // image_url format from DB: "assets/images/menu/filename.jpg"
        // admin panel is one level deep, so prepend "../"
        const imgSrc = item.image_url ? (baseUrl + item.image_url) : fallback;

        return `<div class="db-pop-item">
            <img src="${imgSrc}"
                 alt="${item.name}"
                 class="db-pop-img"
                 onerror="this.onerror=null;this.src='${fallback}'">
            <span class="db-pop-name">${item.name}</span>
            <span class="db-pop-orders">${item.order_count} orders</span>
        </div>`;
    }).join('');
}

// =========================================
// DASHBOARD — STEP 3 NEW WIDGETS
// =========================================

// ---- Revenue Charts (Chart.js) — New Design ----
async function loadRevenueCharts() {
    const res = await api('get_revenue_chart');
    if (res.status !== 'success') return;

    const { daily, monthly } = res;

    // Daily Revenue Bar Chart (blue bars like image)
    destroyChart('dailyRevenueChart');
    const dailyCtx = document.getElementById('dailyRevenueChart');
    if (dailyCtx) {
        _charts['dailyRevenueChart'] = new Chart(dailyCtx, {
            type: 'bar',
            data: {
                labels: daily.map(d => d.label),
                datasets: [{
                    label: 'Revenue (TK)',
                    data: daily.map(d => d.revenue),
                    backgroundColor: '#3b82f6',
                    borderColor: '#2563eb',
                    borderWidth: 0,
                    borderRadius: 6,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        callbacks: { label: ctx => `TK ${fmt(ctx.raw)}` }
                    }
                },
                scales: {
                    y: {
                        ticks: { callback: v => fmt(v), font: { size: 10 }, color: '#94a3b8' },
                        grid: { color: '#f1f5f9' },
                        beginAtZero: true
                    },
                    x: {
                        ticks: { font: { size: 10 }, color: '#94a3b8' },
                        grid: { display: false }
                    }
                }
            }
        });
    }

    // Monthly Revenue Line Chart (purple/violet like image)
    destroyChart('monthlyRevenueChart');
    const monthlyCtx = document.getElementById('monthlyRevenueChart');
    if (monthlyCtx) {
        _charts['monthlyRevenueChart'] = new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: monthly.map(m => m.label),
                datasets: [{
                    label: 'Revenue (TK)',
                    data: monthly.map(m => m.revenue),
                    borderColor: '#8b5cf6',
                    backgroundColor: 'rgba(139,92,246,0.1)',
                    borderWidth: 2.5,
                    pointRadius: 4,
                    pointBackgroundColor: '#8b5cf6',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    fill: true,
                    tension: 0.45,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#1e293b',
                        callbacks: { label: ctx => `TK ${fmt(ctx.raw)}` }
                    }
                },
                scales: {
                    y: {
                        ticks: { callback: v => fmt(v), font: { size: 10 }, color: '#94a3b8' },
                        grid: { color: '#f1f5f9' },
                        beginAtZero: true
                    },
                    x: {
                        ticks: { font: { size: 10 }, color: '#94a3b8', maxRotation: 45 },
                        grid: { display: false }
                    }
                }
            }
        });
    }
}

// ---- Low Stock Alert Widget (New Design) ----
async function loadLowStockAlert() {
    const res = await api('get_low_stock_alert');
    const el = document.getElementById('lowStockList');
    const badge = document.getElementById('lowStockCount');
    if (!el) return;

    if (res.status !== 'success') {
        el.innerHTML = '<p style="color:#94a3b8;font-size:.8rem;text-align:center;padding:10px 0">Unable to load</p>';
        return;
    }

    const items = res.data || [];
    if (badge) badge.textContent = res.low_count ?? items.length;

    if (!items.length) {
        el.innerHTML = `
            <div style="text-align:center;padding:20px 0">
                <i class="fas fa-check-circle" style="font-size:1.6rem;color:#22c55e;display:block;margin-bottom:6px"></i>
                <span style="color:#22c55e;font-weight:600;font-size:.82rem">All stock levels OK</span>
            </div>`;
        return;
    }

    const icons = ['fa-drumstick-bite','fa-cheese','fa-oil-can','fa-jar'];
    const bgColors = ['#fef2f2','#fff7ed','#fefce8','#eff6ff'];
    const iconColors = ['#ef4444','#f97316','#eab308','#3b82f6'];

    el.innerHTML = items.slice(0, 5).map((item, idx) => {
        const bg   = bgColors[idx % bgColors.length];
        const ic   = iconColors[idx % iconColors.length];
        const icon = icons[idx % icons.length];
        return `<div class="db-stock-item">
            <div class="db-stock-icon" style="background:${bg};color:${ic}">
                <i class="fas ${icon}"></i>
            </div>
            <span class="db-stock-name">${item.name}</span>
            <span class="db-stock-qty">Stock: ${item.stock_quantity} ${item.unit || ''}</span>
        </div>`;
    }).join('');

    if (items.length > 5) {
        el.innerHTML += `<div style="text-align:center;margin-top:8px">
            <a href="javascript:void(0)" onclick="showSection('inventory',null)" style="font-size:.75rem;color:#3b82f6;font-weight:600">+${items.length - 5} more items →</a>
        </div>`;
    }
}

// ---- Staff Attendance Summary (New Design) ----
async function loadAttendanceSummary() {
    const res = await api('get_attendance_summary');
    const el = document.getElementById('attendanceSummary');
    if (!el) return;

    if (res.status !== 'success') {
        el.innerHTML = '<p style="color:#94a3b8;font-size:.8rem;text-align:center;padding:10px 0">Unable to load</p>';
        return;
    }

    const d = res.data;
    const total = d.total_staff || 1;
    const presentRate = total > 0 ? Math.round(((d.present + (d.late||0)) / total) * 100) : 0;
    const circ = 2 * Math.PI * 38; // ~238.76
    const arc  = (presentRate / 100) * circ;

    el.innerHTML = `
        <div class="db-attend-wrap">
            <div class="db-attend-donut">
                <svg viewBox="0 0 100 100">
                    <circle cx="50" cy="50" r="38" fill="none" stroke="#e9ecef" stroke-width="11"/>
                    <circle cx="50" cy="50" r="38" fill="none" stroke="#22c55e" stroke-width="11"
                        stroke-dasharray="${arc.toFixed(1)} ${(circ - arc).toFixed(1)}"
                        stroke-dashoffset="0" stroke-linecap="round"
                        style="transition:stroke-dasharray .7s ease"/>
                </svg>
                <div class="db-attend-center">
                    <span class="db-attend-pct">${presentRate}%</span>
                    <span class="db-attend-lbl">Present</span>
                </div>
            </div>
            <div class="db-attend-legend">
                <div class="db-attend-row"><span class="db-dot" style="background:#22c55e"></span> ${d.present} Present <span class="db-attend-cnt">${d.present}</span></div>
                <div class="db-attend-row"><span class="db-dot" style="background:#ef4444"></span> Absent <span class="db-attend-cnt">${d.absent}</span></div>
                <div class="db-attend-row"><span class="db-dot" style="background:#f59e0b"></span> Late <span class="db-attend-cnt">${d.late||0}</span></div>
                <div class="db-attend-total">Total Staff: ${d.total_staff}</div>
            </div>
        </div>`;
}

// ---- Today vs Yesterday Sales Comparison ----
async function loadSalesComparison() {
    const res = await api('get_sales_comparison');
    if (res.status !== 'success') return;

    const { today, yesterday } = res;

    setEl('cmpTodayRev',    `TK ${fmt(today.revenue)}`);
    setEl('cmpTodayOrders', today.orders);
    setEl('cmpTodayAvg',    `TK ${fmt(today.avg_order)}`);

    function diffBadge(cur, prev, isMoney) {
        if (!prev && prev !== 0) return '';
        const diff = cur - prev;
        const pct  = prev > 0 ? Math.abs(Math.round((diff / Math.max(prev,1)) * 100)) : 0;
        const up   = diff >= 0;
        const color = up ? '#22c55e' : '#ef4444';
        const arrow = up ? '▲' : '▼';
        const lbl   = isMoney ? `TK ${fmt(Math.abs(diff))}` : `${pct}%`;
        return `<span style="color:${color};font-weight:600;font-size:.68rem">${arrow} ${lbl} vs yesterday</span>`;
    }

    const revDiffEl = document.getElementById('cmpRevDiff');
    const ordDiffEl = document.getElementById('cmpOrdersDiff');
    const avgDiffEl = document.getElementById('cmpAvgDiff');
    if (revDiffEl) revDiffEl.innerHTML = diffBadge(today.revenue,    yesterday.revenue,    true);
    if (ordDiffEl) ordDiffEl.innerHTML = diffBadge(today.orders,     yesterday.orders,     false);
    if (avgDiffEl) avgDiffEl.innerHTML = diffBadge(today.avg_order,  yesterday.avg_order,  true);
}

// =========================================
// MENU MANAGEMENT
// =========================================
async function loadMenuItems() {
    // If dynamic categories not loaded yet, load them first
    if (!window.dynamicCategories || !window.dynamicCategories.length) {
        await loadCategoriesQuiet();
    }
    const res = await api('get_menu');
    if (res.status === 'success') {
        window.allMenuItems = res.data || [];
        populateCategoryDropdowns();
        filterMenuItems();
    }
}

// Silent category load (no UI update) — used by other sections
async function loadCategoriesQuiet() {
    const res = await api('get_categories');
    if (res.status === 'success') {
        window.dynamicCategories = res.data || [];
        allCategories = window.dynamicCategories;
    }
}

function filterMenuItems() {
    const cat   = document.getElementById('categoryFilter')?.value   || 'all';
    const status= document.getElementById('menuStatusFilter')?.value || 'all';
    const stock = document.getElementById('menuStockFilter')?.value  || 'all';
    const q     = (document.getElementById('menuSearch')?.value || '').toLowerCase();
    const items = (window.allMenuItems || []).filter(i =>
        (cat    === 'all' || i.category === cat) &&
        (status === 'all' || i.status   === status) &&
        (stock  === 'all' || (stock === 'in_stock' ? i.in_stock != '0' : i.in_stock == '0')) &&
        i.name.toLowerCase().includes(q)
    );
    renderMenuItems(items);
}

function renderMenuItems(items) {
    const tbody = document.getElementById('menuTableBody');
    if (!tbody) return;
    if (!items.length) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No menu items found</td></tr>';
        return;
    }
    tbody.innerHTML = items.map(item => {
        const inStock  = item.in_stock != '0';
        const stockQty = parseInt(item.stock_quantity) || 0;
        const stockBadge = inStock
            ? `<span class="stock-badge-in"><i class="fas fa-check-circle me-1"></i>In Stock${stockQty > 0 ? `<span class="stock-badge-qty">(${stockQty})</span>` : ''}</span>`
            : `<span class="stock-badge-out"><i class="fas fa-times-circle me-1"></i>Out of Stock</span>`;
        return `
        <tr>
            <td><img src="../${item.image_url}" alt="${item.name}" class="menu-item-image" onerror="this.src='../assets/images/menu/default.jpg'"></td>
            <td>
                <div class="fw-semibold">${item.name}</div>
                ${item.is_special=='1' ? '<span class="badge bg-warning text-dark ms-0 mt-1" style="font-size:.68rem">Today\'s Special</span>' : ''}
                ${item.is_popular=='1' ? '<span class="badge bg-danger ms-1 mt-1" style="font-size:.68rem">Popular</span>' : ''}
            </td>
            <td>${getCategoryName(item.category)}</td>
            <td>
                <div class="fw-bold">TK ${fmt(item.price)}</div>
                ${item.discount_price > 0 ? `<div class="text-muted text-decoration-line-through" style="font-size:.8rem">TK ${fmt(item.discount_price)}</div>` : ''}
            </td>
            <td>${stockBadge}</td>
            <td><span class="status-badge status-${item.status}">${item.status}</span></td>
            <td class="action-buttons">
                <button class="action-btn edit-btn" onclick="editMenuItem(${item.id})"><i class="fas fa-edit"></i> Edit</button>
                <button class="action-btn delete-btn" onclick="deleteMenuItem(${item.id})"><i class="fas fa-trash"></i></button>
            </td>
        </tr>`;
    }).join('');
}

function showAddMenuModal() {
    document.getElementById('modalTitle').textContent = 'Add New Menu Item';
    document.getElementById('menuItemId').value = '';
    document.getElementById('addMenuForm').reset();
    resetImagePreview();
    // Hide gallery section for new items (only shown after save/edit)
    const galSec = document.getElementById('galleryImagesSection');
    if (galSec) galSec.style.display = 'none';
    const inStockChk = document.getElementById('itemInStock');
    if (inStockChk) { inStockChk.checked = true; toggleStockQtyField(); }
    document.getElementById('addMenuModal').classList.add('active');
}

function closeAddMenuModal() {
    document.getElementById('addMenuModal').classList.remove('active');
    document.getElementById('addMenuForm').reset();
    resetImagePreview();
    const galSec = document.getElementById('galleryImagesSection');
    if (galSec) galSec.style.display = 'none';
    const existing = document.getElementById('existingGalleryImages');
    if (existing) existing.innerHTML = '';
}

function editMenuItem(id) {
    const item = (window.allMenuItems || []).find(i => i.id == id);
    if (!item) return;
    document.getElementById('modalTitle').textContent = 'Edit Menu Item';
    document.getElementById('menuItemId').value = item.id;
    document.getElementById('itemName').value = item.name;
    document.getElementById('itemCategory').value = item.category;
    document.getElementById('itemPrice').value = item.price;
    document.getElementById('itemDiscountPrice').value = item.discount_price || '';
    document.getElementById('itemDescription').value = item.description || '';
    document.getElementById('itemIngredients').value = item.ingredients || '';
    document.getElementById('itemCalories').value = item.calories || '';
    document.getElementById('itemPrepTime').value = item.prep_time || '';
    document.getElementById('itemCookingTime').value = item.cooking_time || '';
    document.getElementById('itemNutrition').value = item.nutrition_info || '';
    document.getElementById('itemAllergens').value = item.allergens || '';
    document.getElementById('itemAvailabilityTime').value = item.availability_time || '';
    document.getElementById('itemStatus').value = item.status || 'active';
    document.getElementById('tagFeatured').checked   = item.is_featured    == '1';
    document.getElementById('tagPopular').checked    = item.is_popular     == '1';
    document.getElementById('tagRecommended').checked= item.is_recommended == '1';
    document.getElementById('tagSpecial').checked    = item.is_special     == '1';

    // Stock fields
    const inStockChk = document.getElementById('itemInStock');
    const stockQtyEl = document.getElementById('itemStockQty');
    if (inStockChk) {
        inStockChk.checked = item.in_stock != '0';
        toggleStockQtyField();
    }
    if (stockQtyEl) stockQtyEl.value = item.stock_quantity || '';

    // Primary image
    if (item.image_url) {
        const img = document.getElementById('previewImg');
        img.src = '../' + item.image_url;
        img.style.display = 'block';
        const ico = document.querySelector('#imagePreview i');
        const txt = document.querySelector('#imagePreview span');
        if (ico) ico.style.display = 'none';
        if (txt) txt.style.display = 'none';
    }

    // Show gallery section and load existing images
    const galSec = document.getElementById('galleryImagesSection');
    if (galSec) {
        galSec.style.display = 'block';
        loadMenuGalleryImages(item.id);
    }

    document.getElementById('addMenuModal').classList.add('active');
}

function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            const img = document.getElementById('previewImg');
            img.src = e.target.result;
            img.style.display = 'block';
            const ico = document.querySelector('#imagePreview i');
            const txt = document.querySelector('#imagePreview span');
            if (ico) ico.style.display = 'none';
            if (txt) txt.style.display = 'none';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function resetImagePreview() {
    const img = document.getElementById('previewImg');
    if (img) { img.src = ''; img.style.display = 'none'; }
    const ico = document.querySelector('#imagePreview i');
    const txt = document.querySelector('#imagePreview span');
    if (ico) ico.style.display = 'block';
    if (txt) txt.style.display = 'block';
}

// ── Stock toggle ──────────────────────────────────────────
function toggleStockQtyField() {
    const chk   = document.getElementById('itemInStock');
    const label = document.getElementById('inStockLabel');
    const group = document.getElementById('stockQtyGroup');
    if (!chk) return;
    if (chk.checked) {
        if (label) { label.textContent = 'In Stock'; label.style.color = '#27ae60'; }
        if (group) group.style.display = 'flex';
    } else {
        if (label) { label.textContent = 'Out of Stock'; label.style.color = '#dc2626'; }
        if (group) group.style.display = 'none';
    }
}

// ── Gallery images ────────────────────────────────────────
async function loadMenuGalleryImages(itemId) {
    const container = document.getElementById('existingGalleryImages');
    if (!container) return;
    container.innerHTML = '<span class="text-muted" style="font-size:.8rem"><i class="fas fa-spinner fa-spin me-1"></i>Loading...</span>';
    const res = await api(`get_menu_images&item_id=${itemId}`);
    if (res.status !== 'success') { container.innerHTML = ''; return; }
    renderGalleryThumbs(res.data || []);
    // Update counter hint
    const statusEl = document.getElementById('galleryUploadStatus');
    if (statusEl) statusEl.textContent = `${(res.data||[]).length}/5 photos`;
}

function renderGalleryThumbs(images) {
    const container = document.getElementById('existingGalleryImages');
    if (!container) return;
    if (!images.length) { container.innerHTML = '<span class="text-muted" style="font-size:.8rem">No additional photos yet.</span>'; return; }
    container.innerHTML = images.map(img => `
        <div class="gallery-thumb-wrap" id="gthumb-${img.id}">
            <img src="../${img.image_url}" alt="Gallery" onerror="this.src='../assets/images/menu/default.jpg'">
            <button class="gallery-thumb-del" onclick="deleteGalleryImage(${img.id})" title="Remove">
                <i class="fas fa-times"></i>
            </button>
        </div>`).join('');
}

async function uploadGalleryImage(input) {
    const itemId = document.getElementById('menuItemId')?.value;
    if (!itemId) { toast('Save the item first before adding gallery images.', 'warning'); input.value=''; return; }
    if (!input.files || !input.files[0]) return;

    const statusEl = document.getElementById('galleryUploadStatus');
    if (statusEl) statusEl.textContent = 'Uploading...';

    const fd = new FormData();
    fd.append('item_id', itemId);
    fd.append('gallery_image', input.files[0]);
    input.value = ''; // reset so same file can be re-selected

    const res = await fetch('admin_api.php?action=upload_gallery_image', { method: 'POST', body: fd }).then(r => r.json());
    if (res.status === 'success') {
        toast('Photo added!', 'success');
        loadMenuGalleryImages(itemId);
    } else {
        toast('Upload failed: ' + res.message, 'error');
        if (statusEl) statusEl.textContent = '';
    }
}

async function deleteGalleryImage(imgId) {
    if (!confirm('Remove this photo?')) return;
    const res = await api('delete_gallery_image', 'POST', { id: imgId });
    if (res.status === 'success') {
        const el = document.getElementById(`gthumb-${imgId}`);
        if (el) el.remove();
        toast('Photo removed.', 'success');
        // Re-count
        const itemId = document.getElementById('menuItemId')?.value;
        if (itemId) loadMenuGalleryImages(itemId);
    } else {
        toast('Error: ' + res.message, 'error');
    }
}

// Restaurant Logo local preview before upload
function previewLogoUpload(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const reader = new FileReader();
        reader.onload = e => {
            const preview = document.getElementById('currentLogoPreview');
            if (preview) preview.src = e.target.result;
        };
        reader.readAsDataURL(file);
        const nameEl = document.getElementById('logoFileName');
        if (nameEl) nameEl.textContent = file.name + ' (' + (file.size / 1024).toFixed(1) + ' KB)';
    }
}

// Restaurant Cover local preview before upload
function previewCoverUpload(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        const reader = new FileReader();
        reader.onload = e => {
            const coverEl = document.getElementById('currentCoverPreview');
            const placeholder = document.getElementById('coverPlaceholder');
            if (coverEl) { coverEl.src = e.target.result; coverEl.style.display = 'block'; }
            if (placeholder) placeholder.style.display = 'none';
        };
        reader.readAsDataURL(file);
    }
}

async function deleteMenuItem(id) {
    if (!confirm('Delete this menu item?')) return;
    const res = await api('delete_menu_item', 'POST', { id });
    if (res.status === 'success') { toast('Menu item deleted!', 'success'); loadMenuItems(); }
    else toast('Error: ' + res.message, 'error');
}

// =========================================
// ORDERS MANAGEMENT
// =========================================
async function loadOrders() {
    const res = await api('get_orders');
    if (res.status === 'success') {
        window.allOrders = res.data || [];
        filterOrders();
    }
}

function filterOrders() {
    const status = document.getElementById('orderStatusFilter')?.value || 'all';
    const date = document.getElementById('orderDateFilter')?.value || 'all';
    const type = document.getElementById('orderTypeFilter')?.value || 'all';
    const now = new Date();
    let orders = (window.allOrders || []).filter(o => {
        const d = new Date(o.date);
        if (status !== 'all' && o.status !== status) return false;
        if (type !== 'all' && o.order_type !== type) return false;
        if (date === 'today' && d.toDateString() !== now.toDateString()) return false;
        if (date === '7days' && d < new Date(now - 7*86400000)) return false;
        if (date === '30days' && d < new Date(now - 30*86400000)) return false;
        return true;
    }).sort((a, b) => new Date(b.date) - new Date(a.date));
    renderOrderAnalytics(orders);
    renderOrdersList(orders);
}

function renderOrderAnalytics(orders) {
    const total = orders.length;
    const rev = orders.reduce((s, o) => s + parseFloat(o.total), 0);
    const avg = total ? rev / total : 0;
    const pending = orders.filter(o => o.status === 'pending').length;
    const el = document.getElementById('orderAnalyticsGrid');
    if (!el) return;
    el.innerHTML = [
        { icon: 'fa-shopping-cart', color: '#3498db', label: 'Filtered Orders', val: total },
        { icon: 'fa-coins', color: '#27ae60', label: 'Revenue', val: 'TK ' + fmt(rev) },
        { icon: 'fa-chart-line', color: '#e67e22', label: 'Avg Order Value', val: 'TK ' + fmt(avg) },
        { icon: 'fa-hourglass-half', color: '#e74c3c', label: 'Pending', val: pending },
    ].map(c => `
        <div class="stat-card" style="border-left:4px solid ${c.color}">
            <div class="stat-icon" style="background:${c.color}"><i class="fas ${c.icon}"></i></div>
            <div class="stat-info"><h3>${c.val}</h3><p>${c.label}</p></div>
        </div>`).join('');
}

function renderOrdersList(orders) {
    const tbody = document.getElementById('ordersTableBody');
    if (!tbody) return;
    if (!orders.length) { tbody.innerHTML = '<tr><td colspan="8" class="text-center py-5 text-muted"><i class="fas fa-search fa-2x mb-2 d-block"></i>No orders found</td></tr>'; return; }
    tbody.innerHTML = orders.map(o => {
        const d = new Date(o.date);
        const ds = d.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
        const ts = d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
        const typeClass = o.order_type === 'online' ? 'type-online' : o.order_type === 'walk-in' ? 'type-walk-in' : 'type-offline';
        const typeIcon = o.order_type === 'online' ? 'fa-truck' : o.order_type === 'walk-in' ? 'fa-walking' : 'fa-store';
        const itemNames = (o.items || []).slice(0, 2).map(i => i.name).join(', ') + (o.items?.length > 2 ? '...' : '');
        return `<tr>
            <td><div class="fw-bold" style="font-size:.82rem;color:#1e293b">#${o.id}</div></td>
            <td>
                <div class="fw-semibold">${o.customer_name || 'Guest'}</div>
                <div class="text-muted" style="font-size:.8rem">${o.customer || ''}</div>
                <div style="font-size:.78rem;color:#64748b"><i class="fas fa-phone-alt me-1"></i>${o.customer_phone || 'N/A'}</div>
            </td>
            <td><span class="${typeClass}"><i class="fas ${typeIcon}"></i>${o.order_type}</span></td>
            <td style="font-size:.82rem;color:#64748b">${itemNames || '—'}</td>
            <td><div style="font-size:.82rem;color:#94a3b8">${ds}</div><div style="font-size:.78rem;color:#94a3b8">${ts}</div></td>
            <td><div class="fw-bold">TK ${fmt(o.total)}</div></td>
            <td><span class="order-status-pill status-${o.status}">${o.status}</span></td>
            <td><button class="details-square-btn" onclick="editOrderDetails(${o.db_id})" title="View Details"><i class="fas fa-eye"></i></button></td>
        </tr>`;
    }).join('');
}

let currentOrderDbId = null;
function editOrderDetails(dbId) {
    const o = (window.allOrders || []).find(x => x.db_id == dbId);
    if (!o) return;
    currentOrderDbId = dbId;
    setEl('editOrderModalTitle', `#${o.id}`);
    setEl('viewOrderId', o.id);
    setEl('viewOrderDate', new Date(o.date).toLocaleString());
    setEl('viewOrderCustomer', o.customer_name || o.customer || '—');
    setEl('viewOrderPhone', o.customer_phone || '—');
    setEl('viewOrderType', o.order_type?.toUpperCase() || '—');
    setEl('viewOrderTotal', fmt(o.total));
    const statusEl = document.getElementById('viewOrderStatus');
    statusEl.textContent = o.status;
    statusEl.className = `order-status-pill status-${o.status}`;
    const upd = document.getElementById('orderStatusUpdate');
    if (upd) upd.value = o.status;
    const itemsHtml = (o.items || []).map(it => `
        <div class="d-flex justify-content-between mb-2 pb-2 border-bottom">
            <span><strong>${it.name}</strong> <span class="text-muted">x${it.qty}</span></span>
            <span>TK ${fmt(it.price * it.qty)}</span>
        </div>`).join('') || '<p class="text-muted text-center">No items</p>';
    setEl('viewOrderItems', itemsHtml);
    const addrRow = document.getElementById('viewDeliveryAddressRow');
    const instrRow = document.getElementById('viewSpecialInstructionsRow');
    if (o.delivery_address) { setEl('viewDeliveryAddress', o.delivery_address); addrRow.style.display = 'block'; } else addrRow.style.display = 'none';
    if (o.special_instructions) { setEl('viewSpecialInstructions', o.special_instructions); instrRow.style.display = 'block'; } else instrRow.style.display = 'none';
    document.getElementById('editOrderModal').classList.add('active');
}

async function saveOrderStatusFromModal() {
    if (!currentOrderDbId) return;
    const status = document.getElementById('orderStatusUpdate').value;
    const res = await api('update_order_status', 'POST', { order_id: currentOrderDbId, status });
    if (res.status === 'success') { toast('Order status updated!', 'success'); closeEditOrderModal(); loadOrders(); loadDashboard(); }
    else toast('Error: ' + res.message, 'error');
}

async function deleteOrderFromModal() {
    if (!currentOrderDbId || !confirm('Delete this order permanently?')) return;
    const res = await api('delete_order', 'POST', { order_id: currentOrderDbId });
    if (res.status === 'success') { toast('Order deleted!', 'success'); closeEditOrderModal(); loadOrders(); loadDashboard(); }
    else toast('Error: ' + res.message, 'error');
}

function closeEditOrderModal() { document.getElementById('editOrderModal').classList.remove('active'); currentOrderDbId = null; }

// =========================================
// RESERVATIONS
// =========================================
async function loadReservations() {
    const res = await api('get_reservations');
    if (res.status === 'success') {
        window.allReservations = res.data || [];
        updateReservationStats();
        filterReservations();
    }
}

function updateReservationStats() {
    const r = window.allReservations || [];
    setEl('resvPending', r.filter(x => x.status === 'pending').length);
    setEl('resvConfirmed', r.filter(x => x.status === 'confirmed').length);
    setEl('resvCancelled', r.filter(x => x.status === 'cancelled').length);
    setEl('resvTotal', r.length);
}

function filterReservations() {
    const d = document.getElementById('reservationDate')?.value || '';
    const s = document.getElementById('reservationStatus')?.value || 'all';
    let r = (window.allReservations || []).filter(rv =>
        (!d || rv.reservation_date === d) && (s === 'all' || rv.status === s)
    );
    renderReservations(r);
}

function renderReservations(list) {
    const tbody = document.getElementById('reservationsTableBody');
    if (!tbody) return;
    if (!list.length) { tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">No reservations found</td></tr>'; return; }
    tbody.innerHTML = list.map(r => `
        <tr>
            <td><span style="font-size:.78rem;background:#f1f5f9;padding:3px 7px;border-radius:4px;font-weight:600">${r.reservation_id}</span></td>
            <td><div class="fw-semibold">${r.customer_name}</div></td>
            <td><i class="fas fa-phone-alt text-primary me-1" style="font-size:.8rem"></i>${r.customer_phone}</td>
            <td><i class="far fa-calendar-alt text-warning me-1"></i>${r.reservation_date}</td>
            <td><i class="far fa-clock text-purple me-1"></i>${r.reservation_time}</td>
            <td class="text-center"><span class="badge rounded-pill" style="background:#eef2ff;color:#4338ca;border:1px solid #c7d2fe;padding:5px 12px">${r.guests_count}</span></td>
            <td><span style="font-size:.8rem;text-transform:capitalize;color:#64748b">${r.occasion || '—'}</span></td>
            <td><span class="status-badge status-${r.status}">${r.status}</span></td>
            <td class="action-buttons">
                <button class="action-btn view-btn" onclick="viewReservation(${r.id})"><i class="fas fa-eye"></i></button>
                <button class="action-btn edit-btn" onclick="quickUpdateResv(${r.id},'confirmed')" title="Confirm"><i class="fas fa-check"></i></button>
                <button class="action-btn delete-btn" onclick="quickUpdateResv(${r.id},'cancelled')" title="Cancel"><i class="fas fa-times"></i></button>
            </td>
        </tr>`).join('');
}

let currentResvId = null;
function viewReservation(id) {
    const r = (window.allReservations || []).find(x => x.id == id);
    if (!r) return;
    currentResvId = id;
    setEl('resv_id', r.reservation_id);
    setEl('resv_name', r.customer_name);
    setEl('resv_phone', r.customer_phone);
    setEl('resv_email', r.customer_email || '—');
    setEl('resv_datetime', `${r.reservation_date} at ${r.reservation_time}`);
    setEl('resv_guests', `${r.guests_count} guests`);
    setEl('resv_occasion', r.occasion || '—');
    setEl('resv_request', r.special_requests || 'None');
    setEl('resv_status', `<span class="status-badge status-${r.status}">${r.status}</span>`);
    document.getElementById('reservationDetailModal').classList.add('active');
}

async function updateReservationFromModal(status) {
    if (!currentResvId) return;
    await quickUpdateResv(currentResvId, status);
    closeReservationModal();
}

async function quickUpdateResv(id, status) {
    const res = await api('update_reservation_status', 'POST', { id, status });
    if (res.status === 'success') { toast(`Reservation ${status}!`, 'success'); loadReservations(); }
    else toast('Error: ' + res.message, 'error');
}

function closeReservationModal() { document.getElementById('reservationDetailModal').classList.remove('active'); }

// =========================================
// CUSTOMERS
// =========================================
async function loadCustomers() {
    const res = await api('get_customers');
    if (res.status === 'success') {
        window.allCustomers = res.data || [];
        setEl('custTotal', window.allCustomers.length);
        setEl('custActive', window.allCustomers.filter(c => c.status === 'active').length);
        setEl('custWithOrders', window.allCustomers.filter(c => (c.total_orders || 0) > 0).length);
        filterCustomers();
    }
}

function filterCustomers() {
    const q = (document.getElementById('customerSearch')?.value || '').toLowerCase();
    const list = (window.allCustomers || []).filter(c =>
        c.full_name?.toLowerCase().includes(q) || c.email?.toLowerCase().includes(q) || c.phone?.includes(q)
    );
    renderCustomers(list);
}

function renderCustomers(list) {
    const tbody = document.getElementById('customersTableBody');
    if (!tbody) return;
    if (!list.length) { tbody.innerHTML = '<tr><td colspan="9" class="text-center py-4 text-muted">No customers found</td></tr>'; return; }
    tbody.innerHTML = list.map(c => `
        <tr>
            <td><span class="fw-bold" style="font-size:.82rem">${c.customer_id || 'CUST-' + c.id}</span></td>
            <td><div class="fw-semibold">${c.full_name}</div></td>
            <td class="text-muted" style="font-size:.85rem">${c.email}</td>
            <td class="text-muted" style="font-size:.85rem">${c.phone || '—'}</td>
            <td class="text-center"><span class="badge bg-light text-dark border">${c.total_orders || 0}</span></td>
            <td class="fw-bold">TK ${fmt(c.total_spent || 0)}</td>
            <td class="text-muted" style="font-size:.82rem">${c.last_order_date ? new Date(c.last_order_date).toLocaleDateString() : 'Never'}</td>
            <td><span class="status-badge status-${c.status || 'active'}">${c.status || 'active'}</span></td>
            <td class="action-buttons">
                <button class="action-btn view-btn" onclick="viewCustomer(${c.id})"><i class="fas fa-eye"></i></button>
                <button class="action-btn delete-btn" onclick="blockCustomer(${c.id})"><i class="fas fa-ban"></i></button>
            </td>
        </tr>`).join('');
}

let currentCustomerId = null;
function viewCustomer(id) {
    const c = (window.allCustomers || []).find(x => x.id == id);
    if (!c) return;
    currentCustomerId = id;
    setEl('custModalName', c.full_name);
    setEl('custModalEmail', c.email);
    setEl('custModalPhone', c.phone || '—');
    setEl('custModalOrders', c.total_orders || 0);
    setEl('custModalSpent', `TK ${fmt(c.total_spent || 0)}`);
    setEl('custModalLastOrder', c.last_order_date ? new Date(c.last_order_date).toLocaleDateString() : 'Never');
    setEl('custModalJoined', c.created_at ? new Date(c.created_at).toLocaleDateString() : '—');
    const statusEl = document.getElementById('custModalStatus');
    statusEl.textContent = c.status || 'active';
    statusEl.className = `status-badge status-${c.status || 'active'}`;
    const blockBtn = document.getElementById('custBlockBtn');
    blockBtn.innerHTML = c.status === 'suspended' ? '<i class="fas fa-unlock me-1"></i>Unblock' : '<i class="fas fa-ban me-1"></i>Block';
    blockBtn.className = c.status === 'suspended' ? 'btn btn-success btn-sm' : 'btn btn-danger btn-sm';
    document.getElementById('customerDetailModal').classList.add('active');
}

async function toggleCustomerBlock() {
    if (!currentCustomerId) return;
    const c = (window.allCustomers || []).find(x => x.id == currentCustomerId);
    const newStatus = c?.status === 'suspended' ? 'active' : 'suspended';
    const fd = new FormData();
    fd.append('id', currentCustomerId); fd.append('status', newStatus);
    const res = await fetch('admin_api.php?action=update_customer_status', { method: 'POST', body: fd });
    const j = await res.json();
    if (j.status === 'success') { toast(`Customer ${newStatus}!`, 'success'); closeCustomerModal(); loadCustomers(); }
    else toast('Error: ' + j.message, 'error');
}

async function blockCustomer(id) {
    const c = (window.allCustomers || []).find(x => x.id == id);
    const newStatus = c?.status === 'suspended' ? 'active' : 'suspended';
    if (!confirm(`${newStatus === 'suspended' ? 'Block' : 'Unblock'} this customer?`)) return;
    const fd = new FormData();
    fd.append('id', id); fd.append('status', newStatus);
    const res = await fetch('admin_api.php?action=update_customer_status', { method: 'POST', body: fd });
    const j = await res.json();
    if (j.status === 'success') { toast(`Customer ${newStatus}!`, 'success'); loadCustomers(); }
}

function closeCustomerModal() { document.getElementById('customerDetailModal').classList.remove('active'); }
function exportCustomers() { quickExport('customers', 'csv'); }

// =========================================

// =========================================
// TEAM / STAFF MANAGEMENT
// =========================================

const ROLE_CONFIG = {
    admin:        { label: 'Admin',        icon: 'fa-shield-halved',  cls: 'rb-admin' },
    manager:      { label: 'Manager',      icon: 'fa-user-tie',       cls: 'rb-manager' },
    chef:         { label: 'Chef',         icon: 'fa-hat-chef',       cls: 'rb-chef' },
    waiter:       { label: 'Waiter',       icon: 'fa-concierge-bell', cls: 'rb-waiter' },
    cashier:      { label: 'Cashier',      icon: 'fa-cash-register',  cls: 'rb-cashier' },
    receptionist: { label: 'Receptionist', icon: 'fa-phone-alt',      cls: 'rb-receptionist' },
    delivery_boy: { label: 'Delivery Boy', icon: 'fa-motorcycle',     cls: 'rb-delivery_boy' },
    cleaner:      { label: 'Cleaner',      icon: 'fa-broom',          cls: 'rb-cleaner' },
    staff:        { label: 'Staff',        icon: 'fa-id-badge',       cls: 'rb-staff' },
    customer:     { label: 'Customer',     icon: 'fa-user',           cls: 'rb-staff' },
};

function roleBadgeHtml(role) {
    const r = ROLE_CONFIG[role] || { label: role, icon: 'fa-user', cls: 'rb-staff' };
    return `<span class="role-badge ${r.cls}"><i class="fas ${r.icon}"></i> ${r.label}</span>`;
}

function getAvatarColor(id) {
    const c = ['#e74c3c','#e67e22','#2980b9','#27ae60','#8e44ad','#0891b2','#c9a74d','#166534','#7c3aed'];
    return c[id % c.length];
}

async function loadTeam() {
    const res = await api('get_users');
    if (res.status === 'success') {
        window.allUsers = res.data || [];
        updateTeamStats();
        await loadBranchesForDropdown();
        filterTeam();
        populateStaffDropdowns();
    }
}

function updateTeamStats() {
    const u = (window.allUsers || []).filter(x => x.role !== 'customer');
    const grid = document.getElementById('teamRoleStatsGrid');
    if (!grid) return;
    const roleColors = { admin:'#e74c3c',manager:'#f39c12',chef:'#e67e22',waiter:'#2980b9',cashier:'#27ae60',receptionist:'#7c3aed',delivery_boy:'#0891b2',cleaner:'#166534',staff:'#475569' };
    let html = `<div class="stat-card" style="border-left:4px solid #c9a74d"><div class="stat-icon" style="background:#c9a74d"><i class="fas fa-users"></i></div><div class="stat-info"><h3>${u.length}</h3><p>Total Staff</p></div></div>`;
    Object.keys(roleColors).forEach(role => {
        const cnt = u.filter(x => x.role === role).length;
        if (!cnt) return;
        const rc = roleColors[role], cfg = ROLE_CONFIG[role] || {};
        html += `<div class="stat-card" style="border-left:4px solid ${rc}"><div class="stat-icon" style="background:${rc}"><i class="fas ${cfg.icon || 'fa-user'}"></i></div><div class="stat-info"><h3>${cnt}</h3><p>${cfg.label || role}</p></div></div>`;
    });
    grid.innerHTML = html;
}

function filterTeam() {
    const role   = document.getElementById('teamRoleFilter')?.value   || 'all';
    const status = document.getElementById('teamStatusFilter')?.value || 'all';
    const members = (window.allUsers || []).filter(u => {
        if (u.role === 'customer') return false;
        if (role   !== 'all' && u.role   !== role)   return false;
        if (status !== 'all' && u.status !== status) return false;
        return true;
    });
    renderTeam(members);
}

function renderTeam(members) {
    const tbody = document.getElementById('teamTableBody');
    if (!tbody) return;
    if (!members.length) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted"><i class="fas fa-users-slash me-2"></i>No staff members found</td></tr>';
        return;
    }
    tbody.innerHTML = members.map(u => {
        const branch = (window.allBranches || []).find(b => b.id == u.branch_id);
        const joined = u.created_at ? new Date(u.created_at).toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' }) : '—';
        return `<tr>
            <td class="fw-bold text-muted" style="font-size:.8rem">#${u.id}</td>
            <td><div class="d-flex align-items-center gap-2">
                <div style="width:34px;height:34px;border-radius:50%;background:${getAvatarColor(u.id)};display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.85rem;flex-shrink:0">${(u.first_name||'?')[0].toUpperCase()}</div>
                <div><div class="fw-semibold" style="font-size:.88rem">${u.full_name || ((u.first_name||'') + ' ' + (u.last_name||''))}</div>
                <div style="font-size:.75rem;color:#94a3b8">${u.phone || ''}</div></div></div></td>
            <td style="font-size:.82rem;color:#475569">${u.email}</td>
            <td>${roleBadgeHtml(u.role)}</td>
            <td style="font-size:.82rem;color:#64748b">${branch ? branch.name : '—'}</td>
            <td><span class="status-badge status-${u.status || 'active'}">${u.status || 'active'}</span></td>
            <td style="font-size:.78rem;color:#94a3b8">${joined}</td>
            <td class="action-buttons" style="text-align:center">
                <button class="action-btn" onclick="viewStaffProfile(${u.id})" title="View Profile" style="background:#eff6ff;color:#2563eb;border-color:#dbeafe"><i class="fas fa-eye"></i></button>
                ${u.role !== 'admin'
                    ? (RBAC_IS_ADMIN
                        ? `<button class="action-btn edit-btn" onclick="editStaff(${u.id})"><i class="fas fa-edit"></i></button>
                <button class="action-btn delete-btn" onclick="deleteStaff(${u.id})"><i class="fas fa-trash"></i></button>`
                        : '')
                    : '<span class="text-muted" style="font-size:.75rem">Protected</span>'}
            </td></tr>`;
    }).join('');
}

function showAddStaffModal() {
    if (!RBAC_IS_ADMIN) { toast('Only admin can create staff accounts.', 'error'); return; }
    document.getElementById('staffModalTitle').textContent = 'Add New Staff Member';
    document.getElementById('staffId').value = '';
    document.getElementById('staffForm').reset();
    document.getElementById('pwdRequired').style.display = 'inline';
    document.getElementById('staffPassword').required = true;
    updateRoleBadgePreview();
    document.getElementById('staffModal').classList.add('active');
}
function closeStaffModal()        { document.getElementById('staffModal').classList.remove('active'); }
function closeStaffProfileModal() { document.getElementById('staffProfileModal').classList.remove('active'); }

function updateRoleBadgePreview() {
    const role    = document.getElementById('staffRole')?.value;
    const preview = document.getElementById('roleBadgePreview');
    if (preview && role) preview.innerHTML = roleBadgeHtml(role);
}

function editStaff(id) {
    if (!RBAC_IS_ADMIN) { toast('Only admin can edit staff accounts.', 'error'); return; }
    const u = (window.allUsers || []).find(x => x.id == id);
    if (!u) return;
    document.getElementById('staffModalTitle').textContent = 'Edit Staff Member';
    document.getElementById('staffId').value        = u.id;
    document.getElementById('staffFirstName').value = u.first_name  || '';
    document.getElementById('staffLastName').value  = u.last_name   || '';
    document.getElementById('staffEmail').value     = u.email       || '';
    document.getElementById('staffPhone').value     = u.phone       || '';
    document.getElementById('staffRole').value      = u.role        || 'staff';
    document.getElementById('staffStatus').value    = u.status      || 'active';
    document.getElementById('staffBranch').value    = u.branch_id   || '';
    document.getElementById('staffPassword').required = false;
    document.getElementById('pwdRequired').style.display = 'none';
    updateRoleBadgePreview();
    document.getElementById('staffModal').classList.add('active');
}

async function deleteStaff(id) {
    if (!confirm('এই staff member টি delete করবেন?')) return;
    const fd = new FormData();
    fd.append('id', id);
    const res = await fetch('admin_api.php?action=delete_user', { method: 'POST', body: fd }).then(r => r.json());
    if (res.status === 'success') { toast('Staff member deleted!', 'success'); loadTeam(); }
    else toast('Error: ' + res.message, 'error');
}

function toggleBranchSelection() {
    const role = document.getElementById('staffRole')?.value;
    const grp  = document.getElementById('branchSelectionGroup');
    if (grp) grp.style.display = role === 'admin' ? 'none' : 'block';
}

function populateStaffDropdowns() {
    const staff = (window.allUsers || []).filter(u => u.role !== 'customer');
    const opts = '<option value="">Select Staff</option>' +
        staff.map(s => `<option value="${s.id}">${s.full_name} (${(ROLE_CONFIG[s.role]||{}).label||s.role})</option>`).join('');
    ['attendanceStaffId','salaryStaffId'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.innerHTML = opts;
    });
}

async function loadBranchesForDropdown() {
    if (!window.allBranches) await loadBranches();
    const sel = document.getElementById('staffBranch');
    if (!sel || !window.allBranches) return;
    sel.innerHTML = '<option value="">No Branch / Main</option>' +
        (window.allBranches || []).map(b => `<option value="${b.id}">${b.name}</option>`).join('');
}

// ===== STAFF PROFILE MODAL =====
async function viewStaffProfile(id) {
    const modal = document.getElementById('staffProfileModal');
    const body  = document.getElementById('staffProfileBody');
    if (!modal || !body) return;
    body.innerHTML = '<div class="text-center py-5"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>';
    modal.classList.add('active');

    const res = await api('get_staff_detail&id=' + id);
    if (res.status !== 'success') { body.innerHTML = '<p class="text-danger text-center p-4">Failed to load profile.</p>'; return; }

    const u   = res.user       || {};
    const att = res.attendance || {};
    const sal = res.salary     || {};
    const rat = res.rating     || {};

    const cfg    = ROLE_CONFIG[u.role] || { label: u.role, icon: 'fa-user', cls: 'rb-staff' };
    const joined = u.created_at ? new Date(u.created_at).toLocaleDateString('en-GB', { day:'2-digit', month:'long', year:'numeric' }) : '—';
    const attPct = att.total_marked > 0 ? Math.round((att.days_present / att.total_marked) * 100) : null;

    let starsHtml = '<span class="text-muted" style="font-size:.82rem">Not rated yet</span>';
    if (rat.rating) {
        const r = Math.round(rat.rating);
        starsHtml = '';
        for (let i = 1; i <= 5; i++) starsHtml += `<i class="fas fa-star" style="color:${i<=r?'#fbbf24':'#e2e8f0'};font-size:1.1rem"></i>`;
        starsHtml += `<span class="ms-2 fw-bold text-warning">${parseFloat(rat.rating).toFixed(1)}</span>`;
    }

    body.innerHTML = `
        <div class="staff-profile-header">
            <div class="staff-avatar-lg" style="background:${getAvatarColor(u.id)}">${(u.first_name||'?')[0].toUpperCase()}</div>
            <div class="staff-profile-info">
                <h3>${u.full_name || ''}</h3>
                ${roleBadgeHtml(u.role)}
                <div class="mt-1"><span class="status-badge status-${u.status||'active'}" style="font-size:.72rem">${u.status||'active'}</span></div>
            </div>
        </div>
        <div class="staff-profile-grid">
            <div class="info-item"><label>Email</label><span>${u.email||'—'}</span></div>
            <div class="info-item"><label>Phone</label><span>${u.phone||'—'}</span></div>
            <div class="info-item"><label>Branch</label><span>${u.branch_name||'Main'}</span></div>
            <div class="info-item"><label>Joined</label><span>${joined}</span></div>
        </div>
        <div style="font-weight:700;color:#0f172a;margin-bottom:10px;font-size:.88rem"><i class="fas fa-chart-bar me-1 text-primary"></i> This Month Summary</div>
        <div class="staff-perf-row">
            <div class="staff-perf-card"><div class="val">${att.days_present ?? 0}</div><div class="lbl">Days Present</div></div>
            <div class="staff-perf-card"><div class="val">${attPct !== null ? attPct+'%' : '—'}</div><div class="lbl">Attendance</div></div>
            <div class="staff-perf-card"><div class="val">${sal.net_salary ? 'TK '+parseFloat(sal.net_salary).toLocaleString() : '—'}</div><div class="lbl">${sal.month||'Last'} Salary</div></div>
        </div>
        <div style="margin-top:16px">
            <div style="font-weight:700;color:#0f172a;margin-bottom:8px;font-size:.88rem"><i class="fas fa-star me-1 text-warning"></i> Performance Rating</div>
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <div>${starsHtml}</div>
                ${rat.review ? `<span style="font-size:.82rem;color:#475569;font-style:italic">"${rat.review}"</span>` : ''}
            </div>
        </div>
        ${u.role !== 'admin' ? `
        <div class="d-flex gap-2 mt-4 flex-wrap">
            ${RBAC_IS_ADMIN ? `<button class="btn btn-primary btn-sm" onclick="closeStaffProfileModal();editStaff(${u.id})"><i class="fas fa-edit me-1"></i>Edit</button>` : ''}
            <button class="btn btn-warning btn-sm" onclick="closeStaffProfileModal();openRatingModal(${u.id},'${(u.full_name||'').replace(/'/g,"\\'")}')"><i class="fas fa-star me-1"></i>Rate</button>
        </div>` : ''}
    `;
}

// ===== PERFORMANCE TAB =====
async function loadPerformance() {
    const month = document.getElementById('perfMonth')?.value || new Date().toISOString().slice(0,7);
    const role  = document.getElementById('perfRoleFilter')?.value || 'all';
    const disp  = document.getElementById('perfMonth_display');
    if (disp) disp.textContent = new Date(month+'-02').toLocaleString('default',{month:'long',year:'numeric'});

    const res = await api(`get_staff_performance&month=${month}&role=${role}`);
    if (res.status !== 'success') return;
    const data = res.data || [], stats = res.stats || {};

    const sg = document.getElementById('perfSummaryGrid');
    if (sg) sg.innerHTML = `
        <div class="stat-card" style="border-left:4px solid #3498db"><div class="stat-icon" style="background:#3498db"><i class="fas fa-users"></i></div><div class="stat-info"><h3>${stats.total||0}</h3><p>Total Staff</p></div></div>
        <div class="stat-card" style="border-left:4px solid #f39c12"><div class="stat-icon" style="background:#f39c12"><i class="fas fa-star"></i></div><div class="stat-info"><h3>${stats.rated||0}</h3><p>Rated</p></div></div>
        <div class="stat-card" style="border-left:4px solid #27ae60"><div class="stat-icon" style="background:#27ae60"><i class="fas fa-chart-line"></i></div><div class="stat-info"><h3>${stats.avg_rating||'—'}</h3><p>Avg Rating</p></div></div>`;

    const tbody = document.getElementById('perfTableBody');
    if (!tbody) return;
    if (!data.length) { tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No staff data found</td></tr>'; return; }

    tbody.innerHTML = data.map(s => {
        let starsHtml = '<span class="text-muted">—</span>';
        if (s.rating) {
            const r = Math.round(s.rating);
            starsHtml = '';
            for (let i=1;i<=5;i++) starsHtml += `<i class="fas fa-star" style="color:${i<=r?'#fbbf24':'#e2e8f0'};font-size:.85rem"></i>`;
            starsHtml += `<span class="ms-1 fw-bold" style="font-size:.78rem;color:#f59e0b">${parseFloat(s.rating).toFixed(1)}</span>`;
        }
        let attHtml = '<span class="text-muted">—</span>';
        if (s.attendance_pct !== null && s.attendance_pct !== undefined) {
            const pct = s.attendance_pct;
            const color = pct>=80?'#27ae60':pct>=60?'#f39c12':'#e74c3c';
            attHtml = `<div style="display:flex;align-items:center;gap:6px"><div style="width:60px;height:6px;background:#f1f5f9;border-radius:3px;overflow:hidden"><div style="width:${pct}%;height:100%;background:${color};border-radius:3px"></div></div><span style="font-size:.78rem;font-weight:600;color:${color}">${pct}%</span></div>`;
        }
        return `<tr>
            <td><div class="d-flex align-items-center gap-2">
                <div style="width:30px;height:30px;border-radius:50%;background:${getAvatarColor(s.id)};display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700;font-size:.8rem;flex-shrink:0">${(s.first_name||'?')[0].toUpperCase()}</div>
                <span style="font-size:.85rem;font-weight:600">${s.full_name}</span></div></td>
            <td>${roleBadgeHtml(s.role)}</td>
            <td style="text-align:center;font-size:.85rem;color:#475569">${s.orders_handled ?? '—'}</td>
            <td style="text-align:center;font-size:.85rem;color:#475569">${s.deliveries ?? '—'}</td>
            <td>${attHtml}</td>
            <td>${starsHtml}</td>
            <td style="text-align:center">
                <button class="action-btn" style="background:#fffbeb;color:#d97706;border-color:#fde68a;font-size:.75rem" onclick="openRatingModal(${s.id},'${(s.full_name||'').replace(/'/g,"\\'")}')"><i class="fas fa-star me-1"></i>Rate</button>
                <button class="action-btn" style="background:#eff6ff;color:#2563eb;border-color:#dbeafe;font-size:.75rem" onclick="viewStaffProfile(${s.id})"><i class="fas fa-eye"></i></button>
            </td></tr>`;
    }).join('');
}

// ===== RATING MODAL =====
let _selectedStar = 0;
function openRatingModal(userId, userName) {
    const month = document.getElementById('perfMonth')?.value || new Date().toISOString().slice(0,7);
    document.getElementById('ratingUserId').value          = userId;
    document.getElementById('ratingMonth').value           = month;
    document.getElementById('ratingStaffName').textContent = userName;
    document.getElementById('ratingValue').value           = 0;
    document.getElementById('starValueDisplay').textContent = '—';
    document.querySelectorAll('.rating-star').forEach(s => s.style.color = '#e2e8f0');
    document.getElementById('ratingReview').value = '';
    _selectedStar = 0;
    document.getElementById('perfRatingModal').classList.add('active');
}
function hoverStar(val)   { document.querySelectorAll('.rating-star').forEach(s => { s.style.color = parseInt(s.dataset.val)<=val ? '#fbbf24' : '#e2e8f0'; }); }
function resetStarHover() { document.querySelectorAll('.rating-star').forEach(s => { s.style.color = parseInt(s.dataset.val)<=_selectedStar ? '#fbbf24' : '#e2e8f0'; }); }
function selectStar(val) {
    _selectedStar = val;
    document.getElementById('ratingValue').value = val;
    const labels = ['','Poor','Fair','Good','Very Good','Excellent'];
    document.getElementById('starValueDisplay').textContent = val + ' — ' + (labels[val]||'');
    hoverStar(val);
}

// ===== STAFF TABS =====
function showStaffTab(tab, el) {
    ['list','performance','attendance','salary'].forEach(t => {
        const key  = t.charAt(0).toUpperCase() + t.slice(1);
        const pane = document.getElementById('staffTab' + key);
        if (pane) pane.style.display = (t === tab) ? '' : 'none';
    });
    document.querySelectorAll('#staffTabs .nav-link').forEach(a => a.classList.remove('active'));
    if (el) el.classList.add('active');
    if (tab === 'performance') loadPerformance();
    if (tab === 'attendance')  loadAttendance();
    if (tab === 'salary')      loadSalary();
}


// =========================================
// BRANCHES
// =========================================
async function loadBranches() {
    const res = await fetch('api-branches.php?action=list').then(r => r.json()).catch(() => ({ success: false }));
    window.allBranches = res.success ? (res.data || []) : [];
    renderBranches(window.allBranches);
}

function renderBranches(list) {
    const tbody = document.getElementById('branchTableBody');
    if (!tbody) return;
    if (!list.length) { tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No branches found</td></tr>'; return; }
    tbody.innerHTML = list.map(b => `
        <tr>
            <td class="fw-bold text-muted">#${b.id}</td>
            <td><div class="fw-semibold">${b.name}</div></td>
            <td class="text-muted" style="font-size:.85rem">${b.location || '—'}</td>
            <td>${b.phone || '—'}</td>
            <td><span class="status-badge status-${b.status || 'active'}">${b.status || 'active'}</span></td>
            <td class="text-muted" style="font-size:.82rem">${new Date(b.created_at).toLocaleDateString()}</td>
            <td class="action-buttons">
                <button class="action-btn edit-btn" onclick="editBranch(${b.id})"><i class="fas fa-edit"></i> Edit</button>
                <button class="action-btn delete-btn" onclick="deleteBranch(${b.id})"><i class="fas fa-trash"></i></button>
            </td>
        </tr>`).join('');
}

function showAddBranchModal() {
    document.getElementById('branchModalTitle').textContent = 'Add New Branch';
    document.getElementById('branchId').value = '';
    document.getElementById('branchForm').reset();
    document.getElementById('branchModal').classList.add('active');
}

function closeBranchModal() { document.getElementById('branchModal').classList.remove('active'); }

function editBranch(id) {
    const b = (window.allBranches || []).find(x => x.id == id);
    if (!b) return;
    document.getElementById('branchModalTitle').textContent = 'Edit Branch';
    document.getElementById('branchId').value = b.id;
    document.getElementById('branchNameForm').value = b.name;
    document.getElementById('branchLocation').value = b.location || '';
    document.getElementById('branchPhone').value = b.phone || '';
    document.getElementById('branchStatus').value = b.status || 'active';
    document.getElementById('branchModal').classList.add('active');
}

async function deleteBranch(id) {
    if (!confirm('Delete this branch?')) return;
    const fd = new FormData();
    fd.append('id', id);
    const res = await fetch('api-branches.php?action=delete', { method: 'POST', body: fd }).then(r => r.json());
    if (res.success) { toast('Branch deleted!', 'success'); loadBranches(); }
    else toast('Error: ' + res.message, 'error');
}

// =========================================
// TABLE MANAGEMENT
// =========================================
async function loadTables() {
    const res = await api('get_tables');
    if (res.status === 'success') {
        window.allTables = res.data || [];
        renderTableStats();
        renderTables(window.allTables);
    } else {
        document.getElementById('tablesTableBody').innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No tables found. Add your first table.</td></tr>';
    }
}

function renderTableStats() {
    const t = window.allTables || [];
    const grid = document.getElementById('tableStatsGrid');
    if (!grid) return;
    const counts = { available: 0, occupied: 0, reserved: 0, maintenance: 0 };
    t.forEach(x => { if (counts[x.status] !== undefined) counts[x.status]++; });
    const colors = { available: '#27ae60', occupied: '#e74c3c', reserved: '#f39c12', maintenance: '#95a5a6' };
    grid.innerHTML = Object.entries(counts).map(([s, n]) => `
        <div class="stat-card" style="border-left:4px solid ${colors[s]}">
            <div class="stat-icon" style="background:${colors[s]}"><i class="fas fa-chair"></i></div>
            <div class="stat-info"><h3>${n}</h3><p style="text-transform:capitalize">${s}</p></div>
        </div>`).join('');
}

function renderTables(list) {
    const tbody = document.getElementById('tablesTableBody');
    if (!tbody) return;
    if (!list.length) { tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No tables found</td></tr>'; return; }
    tbody.innerHTML = list.map(t => `
        <tr>
            <td class="fw-bold">${t.table_number}</td>
            <td><span class="badge bg-light text-dark border">${t.capacity} seats</span></td>
            <td class="text-muted">${t.location || '—'}</td>
            <td><span class="status-badge status-${t.status}">${t.status}</span></td>
            <td class="action-buttons">
                <button class="action-btn edit-btn" onclick="editTable(${t.id})"><i class="fas fa-edit"></i> Edit</button>
                <button class="action-btn delete-btn" onclick="deleteTable(${t.id})"><i class="fas fa-trash"></i></button>
            </td>
        </tr>`).join('');
}

function showTableModal() {
    document.getElementById('tableModalTitle').textContent = 'Add Table';
    document.getElementById('tableId').value = '';
    document.getElementById('tableForm').reset();
    document.getElementById('tableModal').classList.add('active');
}

function closeTableModal() { document.getElementById('tableModal').classList.remove('active'); }

function editTable(id) {
    const t = (window.allTables || []).find(x => x.id == id);
    if (!t) return;
    document.getElementById('tableModalTitle').textContent = 'Edit Table';
    document.getElementById('tableId').value = t.id;
    document.getElementById('tableNumber').value = t.table_number;
    document.getElementById('tableCapacity').value = t.capacity;
    document.getElementById('tableLocation').value = t.location || '';
    document.getElementById('tableStatus').value = t.status;
    document.getElementById('tableModal').classList.add('active');
}

async function deleteTable(id) {
    if (!confirm('Delete this table?')) return;
    const res = await api('delete_table', 'POST', { id });
    if (res.status === 'success') { toast('Table deleted!', 'success'); loadTables(); }
    else toast('Error: ' + res.message, 'error');
}

// =========================================
// COUPONS
// =========================================
async function loadCoupons() {
    const res = await api('get_coupons');
    if (res.status === 'success') {
        window.allCoupons = res.data || [];
        renderCoupons(window.allCoupons);
    } else {
        document.getElementById('couponsTableBody').innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No coupons yet. Create your first coupon.</td></tr>';
    }
}

function renderCoupons(list) {
    const tbody = document.getElementById('couponsTableBody');
    if (!tbody) return;
    if (!list.length) { tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No coupons found</td></tr>'; return; }
    tbody.innerHTML = list.map(c => `
        <tr>
            <td><span class="coupon-code">${c.code}</span></td>
            <td style="text-transform:capitalize">${c.discount_type?.replace('_', ' ') || '—'}</td>
            <td class="fw-bold">${c.discount_type === 'percentage' ? c.discount_value + '%' : c.discount_type === 'fixed' ? 'TK ' + fmt(c.discount_value) : 'Free'}</td>
            <td>${c.used_count || 0} / ${c.usage_limit > 0 ? c.usage_limit : '∞'}</td>
            <td>${c.expiry_date || '—'}</td>
            <td><span class="status-badge status-${c.status}">${c.status}</span></td>
            <td class="action-buttons">
                <button class="action-btn edit-btn" onclick="editCoupon(${c.id})"><i class="fas fa-edit"></i></button>
                <button class="action-btn delete-btn" onclick="deleteCoupon(${c.id})"><i class="fas fa-trash"></i></button>
            </td>
        </tr>`).join('');
}

function showCouponModal() {
    document.getElementById('couponModalTitle').textContent = 'New Coupon';
    document.getElementById('couponId').value = '';
    document.getElementById('couponForm').reset();
    document.getElementById('couponModal').classList.add('active');
}

function closeCouponModal() { document.getElementById('couponModal').classList.remove('active'); }

function editCoupon(id) {
    const c = (window.allCoupons || []).find(x => x.id == id);
    if (!c) return;
    document.getElementById('couponModalTitle').textContent = 'Edit Coupon';
    document.getElementById('couponId').value = c.id;
    document.getElementById('couponCode').value = c.code;
    document.getElementById('couponType').value = c.discount_type;
    document.getElementById('couponValue').value = c.discount_value;
    document.getElementById('couponMinOrder').value = c.min_order || 0;
    document.getElementById('couponUsageLimit').value = c.usage_limit || 0;
    document.getElementById('couponExpiry').value = c.expiry_date || '';
    document.getElementById('couponStatus').value = c.status || 'active';
    document.getElementById('couponModal').classList.add('active');
}

async function deleteCoupon(id) {
    if (!confirm('Delete this coupon?')) return;
    const res = await api('delete_coupon', 'POST', { id });
    if (res.status === 'success') { toast('Coupon deleted!', 'success'); loadCoupons(); }
    else toast('Feature available once coupons table is created.', 'info');
}

// =========================================
// REVIEWS
// =========================================
async function loadReviews() {
    const res = await api('get_reviews');
    if (res.status === 'success') {
        window.allReviews = res.data || [];
        updateReviewStats();
        renderRatingDistribution();
        filterReviews();
    } else {
        document.getElementById('reviewsContainer').innerHTML =
            '<div class="text-center py-5" style="color:#475569;">No reviews found or reviews table not created yet.</div>';
    }
}

function filterReviews() {
    const status  = document.getElementById('reviewStatusFilter')?.value || 'all';
    const rating  = document.getElementById('reviewRatingFilter')?.value  || 'all';
    const search  = (document.getElementById('reviewSearchInput')?.value || '').toLowerCase();
    let list = window.allReviews || [];
    if (status !== 'all') list = list.filter(r => r.status === status);
    if (rating !== 'all') list = list.filter(r => String(r.rating) === rating);
    if (search)           list = list.filter(r =>
        (r.customer_name || '').toLowerCase().includes(search) ||
        (r.comment       || '').toLowerCase().includes(search)
    );
    renderReviews(list);
}

function updateReviewStats() {
    const r   = window.allReviews || [];
    const avg = r.length ? (r.reduce((s, x) => s + parseInt(x.rating || 0), 0) / r.length).toFixed(1) : '0.0';
    const grid = document.getElementById('reviewStatsGrid');
    if (!grid) return;
    grid.innerHTML = [
        { icon: 'fa-star',       color: '#f39c12', label: 'Avg Rating',    val: avg + ' / 5' },
        { icon: 'fa-comments',   color: '#3498db', label: 'Total Reviews', val: r.length },
        { icon: 'fa-hourglass',  color: '#e67e22', label: 'Pending',       val: r.filter(x => x.status === 'pending').length  },
        { icon: 'fa-check',      color: '#27ae60', label: 'Approved',      val: r.filter(x => x.status === 'approved').length },
        { icon: 'fa-times',      color: '#e74c3c', label: 'Rejected',      val: r.filter(x => x.status === 'rejected').length },
        { icon: 'fa-reply',      color: '#8b5cf6', label: 'Replied',       val: r.filter(x => x.admin_reply).length            },
    ].map(c => `
        <div class="stat-card" style="border-left:4px solid ${c.color}">
            <div class="stat-icon" style="background:${c.color}"><i class="fas ${c.icon}"></i></div>
            <div class="stat-info"><h3>${c.val}</h3><p>${c.label}</p></div>
        </div>`).join('');
}

function renderRatingDistribution() {
    const r   = window.allReviews || [];
    const el  = document.getElementById('ratingDistributionChart');
    if (!el) return;
    if (!r.length) { el.innerHTML = '<p class="text-muted text-center py-3">No reviews yet</p>'; return; }
    const total = r.length;
    el.innerHTML = [5,4,3,2,1].map(star => {
        const cnt  = r.filter(x => parseInt(x.rating) === star).length;
        const pct  = total ? Math.round((cnt / total) * 100) : 0;
        const color = star >= 4 ? '#27ae60' : star === 3 ? '#f39c12' : '#e74c3c';
        return `
        <div class="d-flex align-items-center gap-2 mb-2">
            <span style="width:28px;font-size:.8rem;font-weight:700;color:#0f172a;">${star}★</span>
            <div style="flex:1;background:#e2e8f0;border-radius:99px;height:10px;overflow:hidden;">
                <div style="width:${pct}%;height:100%;background:${color};border-radius:99px;transition:width .5s;"></div>
            </div>
            <span style="width:36px;font-size:.8rem;color:#475569;text-align:right;">${cnt}</span>
        </div>`;
    }).join('');
}

function renderReviews(list) {
    const el = document.getElementById('reviewsContainer');
    if (!el) return;
    if (!list.length) {
        el.innerHTML = '<div class="text-center py-5" style="color:#475569;"><i class="fas fa-star-half-stroke fa-2x mb-2 d-block opacity-50"></i>No reviews match your filters.</div>';
        return;
    }

    const statusColors = { pending: '#e67e22', approved: '#27ae60', rejected: '#e74c3c' };

    el.innerHTML = `<div class="review-grid">${list.map(r => {
        const filled  = parseInt(r.rating) || 0;
        const stars   = '<span style="color:#f39c12">' + '★'.repeat(filled) + '</span>' +
                        '<span style="color:#d1d5db">' + '★'.repeat(5 - filled) + '</span>';
        const sColor  = statusColors[r.status] || '#64748b';
        const dateStr = r.created_at ? new Date(r.created_at).toLocaleDateString('en-GB', { day:'2-digit', month:'short', year:'numeric' }) : '';

        const replySection = r.admin_reply
            ? `<div class="review-reply-block">
                    <div style="font-size:.75rem;font-weight:700;color:#8b5cf6;margin-bottom:4px;">
                        <i class="fas fa-reply me-1"></i>Admin Reply · ${r.replied_at ? new Date(r.replied_at).toLocaleDateString('en-GB',{day:'2-digit',month:'short',year:'numeric'}) : ''}
                    </div>
                    <div style="font-size:.85rem;color:#334155;">${escHtml(r.admin_reply)}</div>
               </div>`
            : '';

        return `
        <div class="review-card review-status-${r.status}">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <div style="font-weight:700;color:#0f172a;font-size:.95rem;">${escHtml(r.customer_name || 'Anonymous')}</div>
                    <div style="font-size:1.05rem;line-height:1;">${stars}
                        <span style="font-size:.78rem;color:#64748b;margin-left:4px;">${filled}/5</span>
                    </div>
                </div>
                <div class="text-end">
                    <span class="review-status-badge" style="background:${sColor}20;color:${sColor};border:1px solid ${sColor}40;">
                        ${r.status.charAt(0).toUpperCase() + r.status.slice(1)}
                    </span>
                    <div style="font-size:.75rem;color:#64748b;margin-top:4px;">${dateStr}</div>
                </div>
            </div>

            <!-- Comment -->
            <p style="color:#334155;font-size:.88rem;margin-bottom:12px;line-height:1.55;">${escHtml(r.comment || '—')}</p>

            <!-- Admin Reply -->
            ${replySection}

            <!-- Actions -->
            <div class="d-flex gap-2 flex-wrap mt-2 pt-2" style="border-top:1px solid #f1f5f9;">
                ${r.status !== 'approved' ? `<button class="btn btn-success btn-sm" onclick="updateReviewStatus(${r.id},'approved')"><i class="fas fa-check me-1"></i>Approve</button>` : ''}
                ${r.status !== 'rejected' ? `<button class="btn btn-danger btn-sm"  onclick="updateReviewStatus(${r.id},'rejected')"><i class="fas fa-times me-1"></i>Reject</button>`  : ''}
                <button class="btn btn-outline-primary btn-sm" onclick="openReplyModal(${r.id})">
                    <i class="fas fa-reply me-1"></i>${r.admin_reply ? 'Edit Reply' : 'Reply'}
                </button>
                <button class="btn btn-outline-secondary btn-sm ms-auto" onclick="deleteReview(${r.id})">
                    <i class="fas fa-trash me-1"></i>Delete
                </button>
            </div>
        </div>`;
    }).join('')}</div>`;
}

// Escape HTML helper
function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

async function updateReviewStatus(id, status) {
    const res = await api('update_review_status', 'POST', { id, status });
    if (res.status === 'success') { toast(`Review ${status}!`, 'success'); loadReviews(); }
    else toast('Error: ' + res.message, 'error');
}

async function deleteReview(id) {
    if (!confirm('Delete this review permanently?')) return;
    const res = await api('delete_review', 'POST', { id });
    if (res.status === 'success') { toast('Review deleted!', 'success'); loadReviews(); }
    else toast('Error: ' + res.message, 'error');
}

// Reply Modal
function openReplyModal(id) {
    const r = (window.allReviews || []).find(x => x.id == id);
    if (!r) return;
    document.getElementById('replyReviewId').value = id;
    document.getElementById('replyText').value = r.admin_reply || '';
    document.getElementById('replyOriginalReview').innerHTML = `
        <div style="font-weight:700;color:#0f172a;margin-bottom:4px;">${escHtml(r.customer_name || 'Anonymous')}</div>
        <div style="color:#f39c12;margin-bottom:6px;">${'★'.repeat(parseInt(r.rating)||0)}${'☆'.repeat(5-(parseInt(r.rating)||0))}</div>
        <div style="font-size:.87rem;color:#334155;">${escHtml(r.comment || '—')}</div>`;
    document.getElementById('reviewReplyModal').classList.add('active');
}

function closeReplyModal() {
    document.getElementById('reviewReplyModal').classList.remove('active');
}

async function submitReply() {
    const id    = document.getElementById('replyReviewId').value;
    const reply = document.getElementById('replyText').value.trim();
    if (!reply) { toast('Reply cannot be empty', 'warning'); return; }
    const btn = document.querySelector('#reviewReplyModal .btn-primary');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Sending...';
    const res = await api('reply_review', 'POST', { id, reply });
    btn.disabled = false; btn.innerHTML = '<i class="fas fa-paper-plane me-1"></i>Send Reply';
    if (res.status === 'success') {
        toast('Reply saved!', 'success');
        closeReplyModal();
        loadReviews();
    } else toast('Error: ' + (res.message || 'Failed'), 'error');
}

// =========================================
// EXPENSE MANAGEMENT
// =========================================
async function loadExpenses() {
    const res = await api('get_expenses');
    if (res.status === 'success') {
        window.allExpenses = res.data || [];
        updateExpenseStats();
        renderExpenses(window.allExpenses);
    } else {
        document.getElementById('expensesTableBody').innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No expenses found or table not created yet.</td></tr>';
    }
}

function updateExpenseStats() {
    const e = window.allExpenses || [];
    const total = e.reduce((s, x) => s + parseFloat(x.amount || 0), 0);
    const today = new Date().toDateString();
    const todayExp = e.filter(x => new Date(x.expense_date).toDateString() === today).reduce((s, x) => s + parseFloat(x.amount || 0), 0);
    const grid = document.getElementById('expenseStatsGrid');
    if (!grid) return;
    grid.innerHTML = [
        { icon: 'fa-wallet', color: '#e74c3c', label: 'Total Expenses', val: 'TK ' + fmt(total) },
        { icon: 'fa-calendar-day', color: '#f39c12', label: "Today's Expenses", val: 'TK ' + fmt(todayExp) },
        { icon: 'fa-list', color: '#3498db', label: 'Total Records', val: e.length },
    ].map(c => `<div class="stat-card" style="border-left:4px solid ${c.color}"><div class="stat-icon" style="background:${c.color}"><i class="fas ${c.icon}"></i></div><div class="stat-info"><h3>${c.val}</h3><p>${c.label}</p></div></div>`).join('');
}

function renderExpenses(list) {
    const tbody = document.getElementById('expensesTableBody');
    if (!tbody) return;
    if (!list.length) { tbody.innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No expenses found</td></tr>'; return; }
    tbody.innerHTML = list.map(e => `
        <tr>
            <td>${e.expense_date}</td>
            <td><span class="badge bg-light text-dark border text-capitalize">${e.category}</span></td>
            <td>${e.description || '—'}</td>
            <td class="fw-bold">TK ${fmt(e.amount)}</td>
            <td class="action-buttons">
                <button class="action-btn edit-btn" onclick="editExpense(${e.id})"><i class="fas fa-edit"></i></button>
                <button class="action-btn delete-btn" onclick="deleteExpense(${e.id})"><i class="fas fa-trash"></i></button>
            </td>
        </tr>`).join('');
}

function showExpenseModal() {
    document.getElementById('expenseModalTitle').textContent = 'Add Expense';
    document.getElementById('expenseId').value = '';
    document.getElementById('expenseForm').reset();
    document.getElementById('expenseDate').value = new Date().toISOString().split('T')[0];
    document.getElementById('expenseModal').classList.add('active');
}

function closeExpenseModal() { document.getElementById('expenseModal').classList.remove('active'); }

function editExpense(id) {
    const e = (window.allExpenses || []).find(x => x.id == id);
    if (!e) return;
    document.getElementById('expenseModalTitle').textContent = 'Edit Expense';
    document.getElementById('expenseId').value = e.id;
    document.getElementById('expenseDate').value = e.expense_date;
    document.getElementById('expenseCategory').value = e.category;
    document.getElementById('expenseDesc').value = e.description || '';
    document.getElementById('expenseAmount').value = e.amount;
    document.getElementById('expenseModal').classList.add('active');
}

async function deleteExpense(id) {
    if (!confirm('Delete this expense record?')) return;
    const res = await api('delete_expense', 'POST', { id });
    if (res.status === 'success') { toast('Expense deleted!', 'success'); loadExpenses(); }
    else toast('Error: ' + res.message, 'error');
}

// =========================================
// ANALYTICS & REPORTS
// =========================================
function initAnalyticsDates() {
    const end = new Date();
    const start = new Date();
    start.setDate(start.getDate() - 30);
    const si = document.getElementById('startDate');
    const ei = document.getElementById('endDate');
    if (si) si.value = start.toISOString().split('T')[0];
    if (ei) ei.value = end.toISOString().split('T')[0];
}

async function loadAnalytics() {
    initAnalyticsDates();
    const start = document.getElementById('startDate')?.value;
    const end = document.getElementById('endDate')?.value;
    const [sumRes, popRes, salesRes, monthRes] = await Promise.all([
        fetch(`admin_api.php?action=get_analytics_summary&start=${start}&end=${end}`).then(r => r.json()),
        fetch(`admin_api.php?action=get_popular_items&start=${start}&end=${end}`).then(r => r.json()),
        fetch(`admin_api.php?action=get_daily_sales&start=${start}&end=${end}`).then(r => r.json()),
        fetch(`admin_api.php?action=get_monthly_revenue`).then(r => r.json()),
    ]);
    if (sumRes.status === 'success') renderAnalyticsSummary(sumRes.data);
    if (popRes.status === 'success') renderPopularItems(popRes.data);
    if (salesRes.status === 'success') renderRevenueTrends(salesRes.data);
    if (monthRes.status === 'success') renderMonthlyChart(monthRes.data);
    await loadProfitLoss(start, end);
}

async function generateReport() { await loadAnalytics(); }

// ── Export Report ─────────────────────────────────────────────────────────────
function exportReport(type, format) {
    const start = document.getElementById('startDate')?.value || '';
    const end   = document.getElementById('endDate')?.value   || '';

    if (!start || !end) {
        toast('Please select a date range first.', 'warning');
        return;
    }

    const url = `report_export.php?type=${encodeURIComponent(type)}&format=${encodeURIComponent(format)}&start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}`;

    if (format === 'csv') {
        // Trigger file download
        const a = document.createElement('a');
        a.href = url;
        a.download = '';
        a.style.display = 'none';
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        toast('CSV download started!', 'success');
    } else if (format === 'print') {
        // Open printable report in new tab
        window.open(url, '_blank', 'width=1100,height=800,scrollbars=yes');
    }
}

// ── Quick Export (from Orders/Reservations section, uses this month) ──────────
function quickExport(type, format) {
    const today = new Date();
    const start = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2,'0') + '-01';
    const end   = today.toISOString().split('T')[0];
    const url = `report_export.php?type=${encodeURIComponent(type)}&format=${encodeURIComponent(format)}&start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}`;
    if (format === 'csv') {
        const a = document.createElement('a');
        a.href = url; a.download = ''; a.style.display = 'none';
        document.body.appendChild(a); a.click(); document.body.removeChild(a);
        toast('CSV download started!', 'success');
    } else {
        window.open(url, '_blank', 'width=1100,height=800,scrollbars=yes');
    }
}

function renderAnalyticsSummary(d) {
    const el = document.getElementById('analyticsSummary');
    if (!el) return;
    const prev = d.previous || {};
    const pctChange = (cur, pv) => {
        if (!pv || pv == 0) return '<span class="trend-badge neutral">New</span>';
        const p = ((cur - pv) / pv * 100).toFixed(1);
        return p >= 0 ? `<span class="trend-badge positive">▲ ${p}%</span>` : `<span class="trend-badge negative">▼ ${Math.abs(p)}%</span>`;
    };
    el.innerHTML = [
        { icon: 'fa-coins',         color: '#22c55e', bg: 'rgba(34,197,94,.12)',   label: 'Total Revenue',    val: 'TK ' + fmt(d.total_revenue),  badge: pctChange(d.total_revenue, prev.total_revenue) },
        { icon: 'fa-shopping-cart', color: '#3b82f6', bg: 'rgba(59,130,246,.12)',  label: 'Total Orders',     val: d.total_orders,                 badge: pctChange(d.total_orders, prev.total_orders) },
        { icon: 'fa-chart-line',    color: '#f59e0b', bg: 'rgba(245,158,11,.12)',  label: 'Avg Order Value',  val: 'TK ' + fmt(d.avg_order_value), badge: '' },
        { icon: 'fa-star',          color: '#8b5cf6', bg: 'rgba(139,92,246,.12)',  label: 'Top Category',     val: window.categoryLabels?.[d.top_category] || d.top_category || '—', badge: '' },
    ].map(c => `
        <div class="rpt-kpi-card">
            <div class="rpt-kpi-icon" style="background:${c.bg};color:${c.color}">
                <i class="fas ${c.icon}"></i>
            </div>
            <div class="rpt-kpi-body">
                <div class="rpt-kpi-value">${c.val} ${c.badge}</div>
                <div class="rpt-kpi-label">${c.label}</div>
            </div>
        </div>`).join('');
    setEl('busiestDay', d.busiest_day || '—');
    const h = parseInt(d.peak_hour || 0);
    setEl('peakHour', h ? `${h > 12 ? h - 12 : h}:00 ${h >= 12 ? 'PM' : 'AM'}` : '—');
    const advisorText = d.total_revenue > 0
        ? `Revenue is TK ${fmt(d.total_revenue)} with ${d.total_orders} orders. Peak activity on <strong>${d.busiest_day || 'weekdays'}</strong>. Top selling category: <strong>${window.categoryLabels?.[d.top_category] || d.top_category || 'N/A'}</strong>. Average order value is TK ${fmt(d.avg_order_value)}.`
        : 'No data available for the selected period. Try adjusting the date range.';
    setEl('advisorText', advisorText);
}

function renderPopularItems(items) {
    const el = document.getElementById('popularItems');
    if (!el) return;
    if (!items.length) { el.innerHTML = '<p class="text-muted text-center py-4">No data for this period</p>'; return; }
    const max = items[0].order_count;
    const colors = ['#c9a74d','#e74c3c','#3498db','#27ae60','#8b5cf6'];
    el.innerHTML = items.map((i, idx) => `
        <div class="rpt-popular-item">
            <div class="rpt-popular-rank" style="background:${colors[idx]||'#64748b'}">${idx+1}</div>
            <div class="rpt-popular-info">
                <div class="rpt-popular-name">${htmlEsc(i.name)}</div>
                <div class="rpt-popular-bar-wrap">
                    <div class="rpt-popular-bar" style="width:${(i.order_count/max*100).toFixed(1)}%;background:${colors[idx]||'#64748b'}"></div>
                </div>
            </div>
            <div class="rpt-popular-count">${i.order_count}<span>orders</span></div>
        </div>`).join('');
}

function renderRevenueTrends(sales) {
    const el = document.getElementById('revenueChart');
    if (!el) return;
    if (!sales.length) { el.innerHTML = '<p class="text-muted text-center py-4">No data for this period</p>'; return; }
    const maxRev = Math.max(...sales.map(s => parseFloat(s.total_revenue)||0), 1);
    el.innerHTML = sales.slice(0, 7).map(s => {
        const pct = ((parseFloat(s.total_revenue)||0) / maxRev * 100).toFixed(0);
        return `<div class="rpt-trend-row">
            <div class="rpt-trend-date">${s.sale_date}</div>
            <div class="rpt-trend-bar-wrap">
                <div class="rpt-trend-bar" style="width:${pct}%"></div>
            </div>
            <div class="rpt-trend-meta">
                <span class="rpt-trend-rev">TK ${fmt(s.total_revenue)}</span>
                <span class="rpt-trend-ord">${s.total_orders} orders</span>
            </div>
        </div>`;
    }).join('');
}

function renderMonthlyChart(data) {
    const chart = document.getElementById('monthlyRevenueChart');
    const yAxis = document.getElementById('chartYAxis');
    if (!chart || !yAxis) return;
    if (!data.length) { chart.innerHTML = '<div class="text-center py-5 w-100 text-muted">No data available</div>'; return; }
    const max = Math.max(...data.map(d => d.revenue), 1);
    yAxis.innerHTML = [max, max*0.75, max*0.5, max*0.25, 0].map(v =>
        `<span>${v >= 1000 ? (v/1000).toFixed(1)+'k' : fmt(v)}</span>`).join('');
    chart.innerHTML = data.map(d => {
        const h = Math.max(4, (d.revenue / max * 100).toFixed(1));
        return `<div class="rpt-bar-col">
            <div class="rpt-bar-tooltip">TK ${fmt(d.revenue)}</div>
            <div class="rpt-bar-fill" style="height:${h}%"></div>
            <span class="rpt-bar-label">${(d.month||'').substring(0,3)}<br><small>${d.year}</small></span>
        </div>`;
    }).join('');
}

async function loadProfitLoss(start, end) {
    const [revRes, expRes] = await Promise.all([
        fetch(`admin_api.php?action=get_analytics_summary&start=${start}&end=${end}`).then(r => r.json()),
        fetch(`admin_api.php?action=get_expenses_total&start=${start}&end=${end}`).then(r => r.json()),
    ]);
    const revenue = revRes?.data?.total_revenue || 0;
    const expenses = expRes?.data?.total || 0;
    const profit = revenue - expenses;
    setEl('plRevenue', 'TK ' + fmt(revenue));
    setEl('plExpenses', 'TK ' + fmt(expenses));
    const profitEl = document.getElementById('plProfit');
    if (profitEl) {
        profitEl.textContent = 'TK ' + fmt(profit);
        const card = profitEl.closest('.rpt-pl-item');
        if (card) card.style.setProperty('--pl-color', profit >= 0 ? '#22c55e' : '#e74c3c');
    }
}

// =========================================
// NOTIFICATIONS
// =========================================
async function loadNotifications() {
    const res = await api('get_notifications');
    const el = document.getElementById('notificationsListContainer');
    if (!el) return;
    if (res.status !== 'success' || !res.data.length) { el.innerHTML = '<div class="text-center py-5 text-muted"><i class="fas fa-bell-slash fa-2x mb-2 d-block"></i>No notifications</div>'; return; }
    const icons = { order: 'fa-cart-shopping text-primary', reservation: 'fa-calendar text-warning', system: 'fa-gear text-info', other: 'fa-circle-info text-secondary' };
    const bgColors = { order: '#eff6ff', reservation: '#fffbeb', system: '#f0fdf4', other: '#f8fafc' };
    el.innerHTML = res.data.map(n => `
        <div class="notification-item ${n.is_read == '0' ? 'unread' : ''}">
            <div class="notif-icon-wrap" style="background:${bgColors[n.type]||'#f8fafc'}"><i class="fas ${icons[n.type]||'fa-bell'}"></i></div>
            <div class="notif-content">
                <div class="notif-title">${n.title}</div>
                <div class="notif-msg">${n.message}</div>
                <div class="notif-time">${new Date(n.created_at).toLocaleString()}</div>
            </div>
            <div class="notif-actions">
                ${n.is_read == '0' ? `<button class="action-btn view-btn" onclick="markNotifRead(${n.id})" title="Mark read"><i class="fas fa-check"></i></button>` : ''}
                <button class="action-btn delete-btn" onclick="deleteNotif(${n.id})" title="Delete"><i class="fas fa-trash"></i></button>
            </div>
        </div>`).join('');
}

async function markNotifRead(id) {
    await api('mark_notification_read', 'POST', { id });
    loadNotifications(); updateNotificationIndicator();
}

async function deleteNotif(id) {
    await api('delete_notification', 'POST', { id });
    loadNotifications(); updateNotificationIndicator();
}

async function markAllNotificationsRead(e) {
    if (e) e.preventDefault();
    await api('mark_all_notifications_read', 'POST', {});
    loadNotifications(); updateNotificationIndicator();
    toast('All notifications marked as read', 'success');
}

async function updateNotificationIndicator() {
    const res = await api('get_notifications');
    if (res.status === 'success') {
        const unread = res.data.filter(n => n.is_read == '0').length;
        const badge = document.getElementById('notificationCount');
        if (badge) { badge.textContent = unread; badge.classList.toggle('d-none', unread === 0); }
        document.title = unread > 0 ? `(${unread}) Super Admin — Feliciano` : 'Super Admin — Feliciano';
        renderDropdownNotifications(res.data.filter(n => n.is_read == '0').slice(0, 5));
    }
}

function renderDropdownNotifications(notifications) {
    const el = document.getElementById('dropdownNotificationsList');
    if (!el) return;
    if (!notifications.length) { el.innerHTML = '<li><span class="dropdown-item text-center text-muted py-3">No unread notifications</span></li>'; return; }
    el.innerHTML = notifications.map(n => `
        <li>
            <a class="dropdown-item notif-item" href="#" onclick="handleDropdownNotifClick(event,${n.id},'${n.type}','${n.related_id}')">
                <div class="notif-title"><i class="fas ${n.type==='order'?'fa-cart-shopping text-primary':n.type==='reservation'?'fa-calendar text-warning':'fa-circle-info text-info'}"></i> ${n.title}</div>
                <div class="notif-msg">${n.message}</div>
                <div class="notif-time">${new Date(n.created_at).toLocaleString()}</div>
            </a>
        </li>`).join('');
}

function handleDropdownNotifClick(e, id, type, relatedId) {
    e.preventDefault();
    api('mark_notification_read', 'POST', { id });
    updateNotificationIndicator();
    if (type === 'order') showSection('orders', document.querySelector('.nav-link[onclick*="orders"]'));
    else if (type === 'reservation') showSection('reservations', document.querySelector('.nav-link[onclick*="reservations"]'));
}

// =========================================
// SYSTEM LOGS (Activity Log)
// =========================================
let _logCurrentTab  = 'all';
let _logCurrentPage = 1;

async function loadActivityLog(page) {
    if (page !== undefined) _logCurrentPage = page;
    const search    = (document.getElementById('logSearchInput')?.value  || '').trim();
    const dateFrom  = document.getElementById('logDateFrom')?.value  || '';
    const dateTo    = document.getElementById('logDateTo')?.value    || '';
    const tbody     = document.getElementById('activityLogBody');
    if (!tbody) return;

    tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5" style="color:#475569;"><i class="fas fa-spinner fa-spin fa-2x mb-2 d-block"></i>Loading logs...</td></tr>';

    const params = new URLSearchParams({
        action     : 'get_system_logs',
        category   : _logCurrentTab,
        search     : search,
        date_from  : dateFrom,
        date_to    : dateTo,
        page       : _logCurrentPage
    });

    let res;
    try {
        const r = await fetch('admin_api.php?' + params.toString());
        res = await r.json();
    } catch(e) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-danger">Failed to load logs.</td></tr>';
        return;
    }

    if (res.status !== 'success') {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No logs found.</td></tr>';
        return;
    }

    // Update tab counts
    const counts = res.counts || {};
    Object.entries(counts).forEach(([cat, cnt]) => {
        const el = document.getElementById('cnt-' + cat);
        if (el) el.textContent = cnt;
    });

    // Stats row
    _renderLogStats(counts);

    const logs = res.data || [];
    if (!logs.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-5" style="color:#475569;"><i class="fas fa-inbox fa-2x mb-2 d-block opacity-40"></i>No logs found for this filter.</td></tr>';
        _updateLogPagination(0, res.total || 0, res.per_page || 50);
        return;
    }

    const catColor = {
        order      : { bg:'#dbeafe', color:'#1d4ed8', icon:'fa-cart-shopping' },
        payment    : { bg:'#dcfce7', color:'#16a34a', icon:'fa-credit-card'   },
        reservation: { bg:'#fef9c3', color:'#ca8a04', icon:'fa-calendar-check'},
        inventory  : { bg:'#f3e8ff', color:'#7c3aed', icon:'fa-boxes-stacked' },
        staff      : { bg:'#ffe4e6', color:'#be123c', icon:'fa-id-badge'      },
        login      : { bg:'#e0f2fe', color:'#0284c7', icon:'fa-right-to-bracket'},
        error      : { bg:'#fee2e2', color:'#dc2626', icon:'fa-triangle-exclamation'},
        system     : { bg:'#f1f5f9', color:'#475569', icon:'fa-server'        }
    };

    const statusColor = {
        completed : '#16a34a', success: '#16a34a', active: '#16a34a',
        pending   : '#d97706', warning: '#d97706',
        failed    : '#dc2626', error  : '#dc2626', cancelled: '#dc2626',
        info      : '#0284c7', confirmed: '#0284c7',
        partial   : '#7c3aed'
    };

    tbody.innerHTML = logs.map(l => {
        const c   = catColor[l.category] || catColor.system;
        const sc  = statusColor[l.status] || '#64748b';
        const dt  = l.created_at ? new Date(l.created_at).toLocaleString('en-GB', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' }) : '—';
        const rel = l.related_id ? `<span style="font-size:.75rem;color:#0284c7;font-weight:600;">#${l.related_id}</span>` : '<span style="color:#cbd5e1;">—</span>';
        return `<tr>
            <td style="font-size:.8rem;color:#475569;white-space:nowrap;">${dt}</td>
            <td>
                <span style="display:inline-flex;align-items:center;gap:5px;background:${c.bg};color:${c.color};border-radius:20px;padding:3px 9px;font-size:.73rem;font-weight:700;white-space:nowrap;">
                    <i class="fas ${c.icon}"></i>${l.category}
                </span>
            </td>
            <td style="font-size:.8rem;color:#334155;font-weight:600;white-space:nowrap;">${htmlEsc(l.event_type)}</td>
            <td style="font-size:.82rem;color:#1e293b;">
                ${htmlEsc(l.message)}
                ${l.user_name ? `<br><span style="font-size:.73rem;color:#94a3b8;"><i class="fas fa-user me-1"></i>${htmlEsc(l.user_name)}</span>` : ''}
                ${l.ip_address ? `<span style="font-size:.73rem;color:#94a3b8;margin-left:8px;"><i class="fas fa-network-wired me-1"></i>${htmlEsc(l.ip_address)}</span>` : ''}
            </td>
            <td>${rel}</td>
            <td>
                <span style="display:inline-block;background:${sc}20;color:${sc};border:1px solid ${sc}40;border-radius:20px;padding:2px 8px;font-size:.72rem;font-weight:700;text-transform:capitalize;white-space:nowrap;">
                    ${htmlEsc(l.status)}
                </span>
            </td>
        </tr>`;
    }).join('');

    // Table title & count
    const titleEl = document.getElementById('logTableTitle');
    const countEl = document.getElementById('logTableCount');
    const tabLabel = _logCurrentTab === 'all' ? 'All Logs' : _logCurrentTab.charAt(0).toUpperCase() + _logCurrentTab.slice(1) + ' Logs';
    if (titleEl) titleEl.textContent = tabLabel;
    if (countEl) countEl.textContent = `Showing ${logs.length} of ${res.total || 0} entries`;

    _updateLogPagination(_logCurrentPage, res.total || 0, res.per_page || 50);
}

function _renderLogStats(counts) {
    const grid = document.getElementById('logStatsGrid');
    if (!grid) return;
    const items = [
        { label:'Total Logs',    key:'all',         icon:'fa-layer-group',     color:'#3b82f6' },
        { label:'Order Logs',    key:'order',        icon:'fa-cart-shopping',   color:'#1d4ed8' },
        { label:'Payment Logs',  key:'payment',      icon:'fa-credit-card',     color:'#16a34a' },
        { label:'Reservation',   key:'reservation',  icon:'fa-calendar-check',  color:'#ca8a04' },
        { label:'Inventory',     key:'inventory',    icon:'fa-boxes-stacked',   color:'#7c3aed' },
        { label:'Staff',         key:'staff',        icon:'fa-id-badge',        color:'#be123c' },
        { label:'Login',         key:'login',        icon:'fa-right-to-bracket',color:'#0284c7' },
        { label:'Errors',        key:'error',        icon:'fa-triangle-exclamation', color:'#dc2626' }
    ];
    grid.innerHTML = items.map(item => `
        <div class="stat-card" style="border-left:4px solid ${item.color};cursor:pointer;" onclick="switchLogTab('${item.key}',null)">
            <div class="stat-icon" style="background:${item.color}"><i class="fas ${item.icon}"></i></div>
            <div class="stat-info"><h3>${counts[item.key] || 0}</h3><p>${item.label}</p></div>
        </div>`).join('');
}

function _updateLogPagination(page, total, perPage) {
    const infoEl = document.getElementById('logPaginationInfo');
    const btnsEl = document.getElementById('logPaginationBtns');
    if (!infoEl || !btnsEl) return;

    const totalPages = Math.ceil(total / perPage) || 1;
    const start = total === 0 ? 0 : (page - 1) * perPage + 1;
    const end   = Math.min(page * perPage, total);
    infoEl.textContent = total === 0 ? 'No entries' : `Showing ${start}–${end} of ${total}`;

    let btns = '';
    if (totalPages > 1) {
        btns += `<button class="btn btn-outline-secondary btn-sm" ${page <= 1 ? 'disabled' : ''} onclick="loadActivityLog(${page - 1})"><i class="fas fa-chevron-left"></i></button>`;
        const startP = Math.max(1, page - 2);
        const endP   = Math.min(totalPages, page + 2);
        for (let p = startP; p <= endP; p++) {
            btns += `<button class="btn btn-sm ${p === page ? 'btn-primary' : 'btn-outline-secondary'}" onclick="loadActivityLog(${p})">${p}</button>`;
        }
        btns += `<button class="btn btn-outline-secondary btn-sm" ${page >= totalPages ? 'disabled' : ''} onclick="loadActivityLog(${page + 1})"><i class="fas fa-chevron-right"></i></button>`;
    }
    btnsEl.innerHTML = btns;
}

function switchLogTab(tab, el) {
    _logCurrentTab  = tab;
    _logCurrentPage = 1;
    document.querySelectorAll('.syslog-tab-btn').forEach(b => b.classList.remove('active'));
    if (el) {
        el.classList.add('active');
    } else {
        // activated from stats cards — find matching button
        document.querySelectorAll('.syslog-tab-btn').forEach(b => {
            if (b.getAttribute('onclick') && b.getAttribute('onclick').includes(`'${tab}'`)) b.classList.add('active');
        });
    }
    loadActivityLog(1);
}

function clearLogFilters() {
    const s = document.getElementById('logSearchInput');
    const f = document.getElementById('logDateFrom');
    const t = document.getElementById('logDateTo');
    if (s) s.value = '';
    if (f) f.value = '';
    if (t) t.value = '';
    _logCurrentPage = 1;
    loadActivityLog(1);
}

async function exportLogCSV() {
    const search   = (document.getElementById('logSearchInput')?.value || '').trim();
    const dateFrom = document.getElementById('logDateFrom')?.value  || '';
    const dateTo   = document.getElementById('logDateTo')?.value    || '';

    const params = new URLSearchParams({
        action   : 'get_system_logs',
        category : _logCurrentTab,
        search   : search,
        date_from: dateFrom,
        date_to  : dateTo,
        page     : 1,
        per_page : 9999
    });

    let res;
    try {
        const r = await fetch('admin_api.php?' + params.toString());
        res = await r.json();
    } catch(e) { toast('Export failed', 'error'); return; }

    if (res.status !== 'success' || !res.data.length) { toast('No data to export', 'error'); return; }

    const headers = ['ID', 'Date & Time', 'Category', 'Event Type', 'Message', 'Related ID', 'Status', 'User', 'IP Address'];
    const rows = res.data.map(l => [
        l.id,
        l.created_at,
        l.category,
        l.event_type,
        '"' + (l.message || '').replace(/"/g, '""') + '"',
        l.related_id || '',
        l.status,
        l.user_name  || '',
        l.ip_address || ''
    ]);

    const csv = [headers, ...rows].map(r => r.join(',')).join('\n');
    const blob = new Blob([csv], { type: 'text/csv' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href = url;
    a.download = `system_logs_${_logCurrentTab}_${new Date().toISOString().slice(0,10)}.csv`;
    a.click();
    URL.revokeObjectURL(url);
    toast('CSV exported!', 'success');
}

// =========================================
// SETTINGS
// =========================================
function switchSettingsTab(tab, el) {
    document.querySelectorAll('.settings-tab-pane').forEach(p => p.style.display = 'none');
    document.querySelectorAll('.settings-tab-btn').forEach(b => b.classList.remove('active'));
    const pane = document.getElementById('settings-' + tab);
    if (pane) pane.style.display = '';
    if (el) el.classList.add('active');
    // Always reload settings so all tabs get fresh data
    loadSettings();
    // Security tab-এ extra loaders
    if (tab === 'security') {
        loadLoginHistory();
        loadSecActivityLog();
        loadSecurityStats();
    }
}

async function loadSettings() {
    const res = await api('get_settings');
    if (res.status === 'success') {
        const d = res.data;

        // Basic Info
        if (d.restaurant_name)    setVal('restaurantName', d.restaurant_name);
        if (d.restaurant_address) setVal('restaurantAddress', d.restaurant_address);
        if (d.restaurant_phone)   setVal('restaurantPhone', d.restaurant_phone);
        if (d.restaurant_email)   setVal('restaurantEmail', d.restaurant_email);
        if (d.restaurant_about)   setVal('restaurantAbout', d.restaurant_about);

        // Logo
        if (d.restaurant_logo) {
            const logoEl = document.getElementById('currentLogoPreview');
            if (logoEl) logoEl.src = '../' + d.restaurant_logo + '?t=' + Date.now();
            const nameEl = document.getElementById('logoFileName');
            if (nameEl) nameEl.textContent = d.restaurant_logo.split('/').pop();
        }

        // Cover Image
        if (d.restaurant_cover) {
            const coverEl = document.getElementById('currentCoverPreview');
            const placeholder = document.getElementById('coverPlaceholder');
            if (coverEl) { coverEl.src = '../' + d.restaurant_cover + '?t=' + Date.now(); coverEl.style.display = 'block'; }
            if (placeholder) placeholder.style.display = 'none';
        }

        // Google Map
        if (d.google_map_url) {
            setVal('googleMapUrl', d.google_map_url);
            const wrap = document.getElementById('googleMapPreviewWrap');
            const frame = document.getElementById('googleMapPreviewFrame');
            if (wrap && frame && d.google_map_url.startsWith('http')) {
                frame.src = d.google_map_url;
                wrap.style.display = 'block';
            }
        }

        // Social Links
        if (d.social_facebook)  setVal('settingFacebook', d.social_facebook);
        if (d.social_instagram) setVal('settingInstagram', d.social_instagram);
        if (d.social_twitter)   setVal('settingTwitter', d.social_twitter);
        if (d.social_whatsapp)  setVal('settingWhatsapp', d.social_whatsapp);
        if (d.social_youtube)   setVal('settingYoutube', d.social_youtube);
        if (d.social_tiktok)    setVal('settingTiktok', d.social_tiktok);

        // Operating Hours (per day)
        const days = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
        days.forEach(day => {
            if (d['open_' + day])  setVal('open_' + day, d['open_' + day]);
            if (d['close_' + day]) setVal('close_' + day, d['close_' + day]);
            if (d['closed_' + day] === '1' || d['closed_' + day] === 'on') {
                const cb = document.getElementById('closed_' + day);
                if (cb) { cb.checked = true; toggleDayClosed(day); }
            }
        });

        // Payment Settings
        if (d.currency)         { setVal('settingCurrency', d.currency); setVal('settingCurrencySelect', d.currency); }
        if (d.tax_percentage)   setVal('settingTax', d.tax_percentage);
        if (d.bkash_number)     setVal('settingBkash', d.bkash_number);
        if (d.nagad_number)     setVal('settingNagad', d.nagad_number);
        if (d.delivery_charge)  setVal('settingDelivery', d.delivery_charge);
        if (d.timezone)         setVal('settingTimezone', d.timezone);

        // Legal content
        if (d.terms_conditions) setVal('termsConditions', d.terms_conditions);
        if (d.privacy_policy)   setVal('privacyPolicy', d.privacy_policy);
    }
}

// Toggle day closed state
function toggleDayClosed(day) {
    const cb = document.getElementById('closed_' + day);
    const openInput  = document.getElementById('open_' + day);
    const closeInput = document.getElementById('close_' + day);
    if (!cb) return;
    const isClosed = cb.checked;
    if (openInput)  { openInput.disabled = isClosed; openInput.style.opacity = isClosed ? '.4' : '1'; }
    if (closeInput) { closeInput.disabled = isClosed; closeInput.style.opacity = isClosed ? '.4' : '1'; }
}

// Copy Monday hours to all days
function fillAllHours() {
    const openMon  = document.getElementById('open_monday')?.value;
    const closeMon = document.getElementById('close_monday')?.value;
    if (!openMon && !closeMon) { toast('Set Monday hours first', 'warning'); return; }
    const days = ['tuesday','wednesday','thursday','friday','saturday','sunday'];
    days.forEach(day => {
        if (openMon)  setVal('open_' + day, openMon);
        if (closeMon) setVal('close_' + day, closeMon);
    });
    toast('Copied Monday hours to all days', 'success');
}

// Switch Legal tabs (Terms / Privacy)
function switchLegalTab(tab, el, e) {
    if (e) e.preventDefault();
    document.querySelectorAll('#legalTabs .nav-link').forEach(a => a.classList.remove('active'));
    if (el) el.classList.add('active');
    document.getElementById('legalTabTerms').style.display = tab === 'terms' ? '' : 'none';
    document.getElementById('legalTabPrivacy').style.display = tab === 'privacy' ? '' : 'none';
}

// =========================================
// FORM HANDLERS
// =========================================
function setupFormHandlers() {
    // Menu Form
    const menuForm = document.getElementById('addMenuForm');
    if (menuForm) menuForm.addEventListener('submit', async e => {
        e.preventDefault();
        const fd = new FormData(menuForm);
        const id = document.getElementById('menuItemId').value;
        const action = id ? 'update_menu_item' : 'add_menu_item';
        const res = await fetch(`admin_api.php?action=${action}`, { method: 'POST', body: fd }).then(r => r.json());
        if (res.status === 'success') { toast(res.message || 'Saved!', 'success'); closeAddMenuModal(); loadMenuItems(); loadDashboard(); }
        else toast('Error: ' + res.message, 'error');
    });

    // Staff Form
    const staffForm = document.getElementById('staffForm');
    if (staffForm) staffForm.addEventListener('submit', async e => {
        e.preventDefault();
        const fd = new FormData(staffForm);
        const id = document.getElementById('staffId').value;
        const action = id ? 'update_user' : 'create_user';
        const res = await fetch(`admin_api.php?action=${action}`, { method: 'POST', body: fd }).then(r => r.json());
        if (res.status === 'success') { toast('Staff saved!', 'success'); closeStaffModal(); loadTeam(); }
        else toast('Error: ' + res.message, 'error');
    });

    // Branch Form
    const branchForm = document.getElementById('branchForm');
    if (branchForm) branchForm.addEventListener('submit', async e => {
        e.preventDefault();
        const fd = new FormData(branchForm);
        const res = await fetch('api-branches.php?action=save', { method: 'POST', body: fd }).then(r => r.json());
        if (res.success) { toast('Branch saved!', 'success'); closeBranchModal(); loadBranches(); }
        else toast('Error: ' + res.message, 'error');
    });

    // Table Form
    const tableForm = document.getElementById('tableForm');
    if (tableForm) tableForm.addEventListener('submit', async e => {
        e.preventDefault();
        const fd = new FormData(tableForm);
        const res = await fetch('admin_api.php?action=save_table', { method: 'POST', body: fd }).then(r => r.json());
        if (res.status === 'success') { toast('Table saved!', 'success'); closeTableModal(); loadTables(); }
        else toast('Error: ' + (res.message || 'Table feature coming soon'), 'info');
    });

    // Coupon Form
    const couponForm = document.getElementById('couponForm');
    if (couponForm) couponForm.addEventListener('submit', async e => {
        e.preventDefault();
        const fd = new FormData(couponForm);
        const res = await fetch('admin_api.php?action=save_coupon', { method: 'POST', body: fd }).then(r => r.json());
        if (res.status === 'success') { toast('Coupon saved!', 'success'); closeCouponModal(); loadCoupons(); }
        else toast('Coupon feature available once coupons table is created.', 'info');
    });

    // Expense Form
    const expenseForm = document.getElementById('expenseForm');
    if (expenseForm) expenseForm.addEventListener('submit', async e => {
        e.preventDefault();
        const fd = new FormData(expenseForm);
        const res = await fetch('admin_api.php?action=save_expense', { method: 'POST', body: fd }).then(r => r.json());
        if (res.status === 'success') { toast('Expense saved!', 'success'); closeExpenseModal(); loadExpenses(); }
        else toast('Expense feature available once expenses table is created.', 'info');
    });

    // Restaurant Info Form
    const infoForm = document.getElementById('restaurantInfoForm');
    if (infoForm) infoForm.addEventListener('submit', async e => {
        e.preventDefault();
        const data = {};
        new FormData(infoForm).forEach((v, k) => data[k] = v);
        const res = await api('update_settings', 'POST', data);
        if (res.status === 'success') {
            toast('Restaurant info saved! Refresh the public site to see changes.', 'success');
            // Admin header-এ restaurant name সাথে সাথে update করো
            const logoName = document.querySelector('.logo-name');
            if (logoName && data.restaurant_name) logoName.textContent = data.restaurant_name;
        }
        else toast('Error saving settings', 'error');
    });

    // Social Links Form
    const socialForm = document.getElementById('socialLinksForm');
    if (socialForm) socialForm.addEventListener('submit', async e => {
        e.preventDefault();
        const data = {};
        new FormData(socialForm).forEach((v, k) => data[k] = v);
        const res = await api('update_settings', 'POST', data);
        if (res.status === 'success') toast('Social links saved!', 'success');
        else toast('Error saving settings', 'error');
    });

    // Operating Hours Form
    const hoursForm = document.getElementById('operatingHoursForm');
    if (hoursForm) hoursForm.addEventListener('submit', async e => {
        e.preventDefault();
        const data = {};
        new FormData(hoursForm).forEach((v, k) => {
            // Normalize checkbox value to '1'
            data[k] = (v === 'on') ? '1' : v;
        });
        // Capture unchecked "Closed" checkboxes as '0'
        const days = ['monday','tuesday','wednesday','thursday','friday','saturday','sunday'];
        days.forEach(day => {
            const cb = document.getElementById('closed_' + day);
            if (cb && !cb.checked) data['closed_' + day] = '0';
        });
        const res = await api('update_settings', 'POST', data);
        if (res.status === 'success') toast('Operating hours saved!', 'success');
        else toast('Error saving settings', 'error');
    });

    // Logo Upload Form
    const logoForm = document.getElementById('logoUploadForm');
    if (logoForm) logoForm.addEventListener('submit', async e => {
        e.preventDefault();
        const fd = new FormData(logoForm);
        const file = document.getElementById('logoFile').files[0];
        if (!file) { toast('Please select a logo file', 'warning'); return; }
        const btn = logoForm.querySelector('button[type="submit"]');
        btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Uploading...';
        const res = await fetch('admin_api.php?action=upload_logo', { method: 'POST', body: fd }).then(r => r.json());
        btn.disabled = false; btn.innerHTML = '<i class="fas fa-upload me-1"></i>Upload Logo';
        if (res.status === 'success') {
            document.getElementById('currentLogoPreview').src = '../' + res.url + '?t=' + Date.now();
            // Admin header-এর logo-ও সাথে সাথে update করো
            const headerLogo = document.querySelector('.logo-favicon');
            if (headerLogo) headerLogo.src = '../' + res.url + '?t=' + Date.now();
            const sidebarLogo = document.querySelector('.sidebar-brand-mini img');
            if (sidebarLogo) sidebarLogo.src = '../' + res.url + '?t=' + Date.now();
            toast('Logo uploaded successfully!', 'success');
            logoForm.reset();
            document.getElementById('logoFileName').textContent = 'No file selected';
        } else toast('Upload failed: ' + (res.message || 'Unknown error'), 'error');
    });

    // Cover Upload Form
    const coverForm = document.getElementById('coverUploadForm');
    if (coverForm) coverForm.addEventListener('submit', async e => {
        e.preventDefault();
        const fd = new FormData(coverForm);
        const file = document.getElementById('coverFile').files[0];
        if (!file) { toast('Please select a cover image', 'warning'); return; }
        const btn = coverForm.querySelector('button[type="submit"]');
        btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Uploading...';
        const res = await fetch('admin_api.php?action=upload_cover', { method: 'POST', body: fd }).then(r => r.json());
        btn.disabled = false; btn.innerHTML = '<i class="fas fa-upload me-1"></i>Upload Cover';
        if (res.status === 'success') {
            const coverEl = document.getElementById('currentCoverPreview');
            const placeholder = document.getElementById('coverPlaceholder');
            if (coverEl) { coverEl.src = '../' + res.url + '?t=' + Date.now(); coverEl.style.display = 'block'; }
            if (placeholder) placeholder.style.display = 'none';
            toast('Cover image uploaded successfully!', 'success');
            coverForm.reset();
        } else toast('Upload failed: ' + (res.message || 'Unknown error'), 'error');
    });

    // Google Map Form
    const mapForm = document.getElementById('googleMapForm');
    if (mapForm) mapForm.addEventListener('submit', async e => {
        e.preventDefault();
        const url = document.getElementById('googleMapUrl').value.trim();
        const res = await api('update_settings', 'POST', { google_map_url: url });
        if (res.status === 'success') {
            toast('Google Map URL saved!', 'success');
            const wrap = document.getElementById('googleMapPreviewWrap');
            const frame = document.getElementById('googleMapPreviewFrame');
            if (url && url.startsWith('http') && wrap && frame) {
                frame.src = url;
                wrap.style.display = 'block';
            } else if (wrap) wrap.style.display = 'none';
        } else toast('Error saving map URL', 'error');
    });

    // Terms & Conditions Form
    const termsForm = document.getElementById('termsForm');
    if (termsForm) termsForm.addEventListener('submit', async e => {
        e.preventDefault();
        const data = { terms_conditions: document.getElementById('termsConditions').value };
        const res = await api('update_settings', 'POST', data);
        if (res.status === 'success') toast('Terms & Conditions saved!', 'success');
        else toast('Error saving terms', 'error');
    });

    // Privacy Policy Form
    const privacyForm = document.getElementById('privacyForm');
    if (privacyForm) privacyForm.addEventListener('submit', async e => {
        e.preventDefault();
        const data = { privacy_policy: document.getElementById('privacyPolicy').value };
        const res = await api('update_settings', 'POST', data);
        if (res.status === 'success') toast('Privacy Policy saved!', 'success');
        else toast('Error saving privacy policy', 'error');
    });

    // Payment Settings Form
    const payForm = document.getElementById('paymentSettingsForm');
    if (payForm) payForm.addEventListener('submit', async e => {
        e.preventDefault();
        const data = {};
        new FormData(payForm).forEach((v, k) => data[k] = v);
        const res = await api('update_settings', 'POST', data);
        if (res.status === 'success') toast('Payment settings saved!', 'success');
        else toast('Error saving settings', 'error');
    });

    // Security Form — with strength check
    const secForm = document.getElementById('securityForm');
    if (secForm) {
        // Password strength meter
        const newPwdInput = document.getElementById('newPassword');
        if (newPwdInput) {
            newPwdInput.addEventListener('input', () => {
                const val = newPwdInput.value;
                const bar  = document.getElementById('pwdStrengthBar');
                const fill = document.getElementById('pwdStrengthFill');
                const txt  = document.getElementById('pwdStrengthText');
                if (!bar) return;
                if (!val) { bar.style.display = 'none'; txt.textContent = ''; return; }
                bar.style.display = 'block';
                let score = 0;
                if (val.length >= 8)  score++;
                if (/[A-Z]/.test(val)) score++;
                if (/[0-9]/.test(val)) score++;
                if (/[^A-Za-z0-9]/.test(val)) score++;
                const levels = ['','Weak','Fair','Good','Strong'];
                const colors = ['','#e74c3c','#f39c12','#3498db','#27ae60'];
                fill.style.width  = (score * 25) + '%';
                fill.style.background = colors[score] || '#e74c3c';
                txt.textContent   = levels[score] || '';
                txt.style.color   = colors[score] || '#e74c3c';
            });
        }

        secForm.addEventListener('submit', async e => {
            e.preventDefault();
            const np = document.getElementById('newPassword').value;
            const cp = document.getElementById('confirmPassword').value;
            if (np !== cp)     { toast('Passwords do not match!', 'error'); return; }
            if (np.length < 8) { toast('Password must be at least 8 characters', 'error'); return; }
            const fd  = new FormData(secForm);
            const btn = secForm.querySelector('button[type="submit"]');
            btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Updating...';
            const res = await fetch('admin_api.php?action=change_password', { method: 'POST', body: fd }).then(r => r.json());
            btn.disabled = false; btn.innerHTML = '<i class="fas fa-lock me-1"></i>Update Password';
            if (res.status === 'success') {
                toast('Password changed successfully!', 'success');
                secForm.reset();
                document.getElementById('pwdStrengthBar').style.display = 'none';
                document.getElementById('pwdStrengthText').textContent = '';
            } else toast('Error: ' + res.message, 'error');
        });
    }

    // Extra form handlers (Combos, Suppliers, Purchases, Events, Website Content)
    setupExtraFormHandlers();
}

// =========================================
// UTILITY HELPERS
// =========================================
function setEl(id, html) {
    const el = document.getElementById(id);
    if (el) el.innerHTML = html;
}

function setVal(id, val) {
    const el = document.getElementById(id);
    if (el) el.value = val;
}

function fmt(n) {
    return parseFloat(n || 0).toLocaleString('en-US', { minimumFractionDigits: 0, maximumFractionDigits: 2 });
}

// =========================================
// TOAST NOTIFICATIONS
// =========================================
function toast(msg, type = 'info') {
    // Remove old toast
    const old = document.getElementById('superAdminToast');
    if (old) old.remove();
    const colors = { success: '#27ae60', error: '#e74c3c', info: '#3498db', warning: '#f39c12' };
    const icons = { success: 'fa-check-circle', error: 'fa-times-circle', info: 'fa-info-circle', warning: 'fa-exclamation-triangle' };
    const t = document.createElement('div');
    t.id = 'superAdminToast';
    t.style.cssText = `position:fixed;bottom:25px;right:25px;background:${colors[type]||colors.info};color:#fff;padding:14px 22px;border-radius:10px;z-index:9999;font-weight:600;font-size:.92rem;display:flex;align-items:center;gap:10px;box-shadow:0 6px 20px rgba(0,0,0,.2);max-width:360px;animation:slideIn .3s ease`;
    t.innerHTML = `<i class="fas ${icons[type]||icons.info}"></i> ${msg}`;
    if (!document.getElementById('toastStyle')) {
        const s = document.createElement('style');
        s.id = 'toastStyle';
        s.textContent = '@keyframes slideIn{from{transform:translateX(120%);opacity:0}to{transform:translateX(0);opacity:1}}';
        document.head.appendChild(s);
    }
    document.body.appendChild(t);
    setTimeout(() => { if (t.parentNode) { t.style.opacity = '0'; t.style.transition = 'opacity .3s'; setTimeout(() => t.remove(), 300); } }, 3500);
}

// Close modals on overlay click
document.addEventListener('click', e => {
    if (e.target.classList.contains('modal')) {
        e.target.classList.remove('active');
    }
});


// =========================================
// KITCHEN MANAGEMENT
// =========================================
async function loadKitchenOrders() {
    const res = await api('get_kitchen');
    const tbody = document.getElementById('kitchenTableBody');
    if (!tbody) return;
    if (res.status !== 'success' || !res.data.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No active kitchen orders</td></tr>';
        setEl('kitchenPending', 0); setEl('kitchenPreparing', 0); setEl('kitchenReady', 0);
        return;
    }
    const orders = res.data;
    setEl('kitchenPending', orders.filter(o => o.status === 'pending' || o.status === 'confirmed').length);
    setEl('kitchenPreparing', orders.filter(o => o.status === 'preparing').length);
    setEl('kitchenReady', orders.filter(o => o.status === 'ready').length);
    tbody.innerHTML = orders.map(o => `
        <tr>
            <td class="fw-bold">#${o.order_id}</td>
            <td style="font-size:.85rem;max-width:220px">${o.items_list || '—'}</td>
            <td><span class="badge bg-light text-dark border text-capitalize">${o.order_type}</span></td>
            <td style="font-size:.82rem;color:#64748b">${new Date(o.created_at).toLocaleTimeString('en-US',{hour:'2-digit',minute:'2-digit'})}</td>
            <td><span class="order-status-pill status-${o.status}">${o.status}</span></td>
            <td style="text-align:center">
                ${o.status === 'pending' || o.status === 'confirmed'
                    ? `<button class="btn btn-warning btn-sm" onclick="updateKitchenStatus(${o.id},'preparing')"><i class="fas fa-fire me-1"></i>Start</button>`
                    : o.status === 'preparing'
                    ? `<button class="btn btn-success btn-sm" onclick="updateKitchenStatus(${o.id},'ready')"><i class="fas fa-check me-1"></i>Ready</button>`
                    : `<span class="text-success fw-bold"><i class="fas fa-check-circle me-1"></i>Done</span>`}
            </td>
        </tr>`).join('');
}

async function updateKitchenStatus(dbId, status) {
    const res = await api('update_order_status', 'POST', { order_id: dbId, status });
    if (res.status === 'success') { toast(`Order marked as ${status}!`, 'success'); loadKitchenOrders(); }
    else toast('Error: ' + res.message, 'error');
}

// =========================================
// DELIVERY MANAGEMENT
// =========================================
async function loadDeliveryOrders() {
    const res = await api('get_delivery');
    const tbody = document.getElementById('deliveryTableBody');
    if (!tbody) return;
    if (res.status !== 'success' || !res.data.length) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center py-4 text-muted">No delivery orders found</td></tr>';
        setEl('deliveryPending', 0); setEl('deliveryActive', 0); setEl('deliveryDone', 0);
        return;
    }
    const orders = res.data;
    const today = new Date().toDateString();
    setEl('deliveryPending', orders.filter(o => o.status === 'ready').length);
    setEl('deliveryActive', orders.filter(o => o.status === 'out_for_delivery').length);
    setEl('deliveryDone', orders.filter(o => o.status === 'completed' && new Date(o.created_at).toDateString() === today).length);
    tbody.innerHTML = orders.map(o => `
        <tr>
            <td class="fw-bold">#${o.order_id}</td>
            <td>${o.customer_name || '—'}</td>
            <td style="font-size:.82rem;color:#64748b">${o.delivery_address || '—'}</td>
            <td class="fw-bold">TK ${fmt(o.total_amount)}</td>
            <td><span class="order-status-pill status-${o.status}">${o.status}</span></td>
            <td style="text-align:center">
                ${o.status === 'ready'
                    ? `<button class="btn btn-warning btn-sm" onclick="updateDeliveryStatus(${o.id},'out_for_delivery')"><i class="fas fa-motorcycle me-1"></i>Dispatch</button>`
                    : o.status === 'out_for_delivery'
                    ? `<button class="btn btn-success btn-sm" onclick="updateDeliveryStatus(${o.id},'completed')"><i class="fas fa-check me-1"></i>Delivered</button>`
                    : `<span class="text-muted" style="font-size:.82rem">${o.status}</span>`}
            </td>
        </tr>`).join('');

    // Delivery settings form prefill
    const delivForm = document.getElementById('deliverySettingsForm');
    if (delivForm) {
        const settingsRes = await api('get_settings');
        if (settingsRes.status === 'success') {
            const d = settingsRes.data;
            if (d.delivery_charge) delivForm.querySelector('[name=delivery_charge]').value = d.delivery_charge;
            if (d.free_delivery_above) delivForm.querySelector('[name=free_delivery_above]').value = d.free_delivery_above;
            if (d.delivery_time_est) delivForm.querySelector('[name=delivery_time_est]').value = d.delivery_time_est;
        }
    }
}

async function updateDeliveryStatus(dbId, status) {
    const res = await api('update_order_status', 'POST', { order_id: dbId, status });
    if (res.status === 'success') { toast(`Delivery ${status.replace('_', ' ')}!`, 'success'); loadDeliveryOrders(); }
    else toast('Error: ' + res.message, 'error');
}

// =========================================
// BILLING & PAYMENT
// =========================================
async function loadBilling() {
    const res = await api('get_billing');
    if (res.status !== 'success') return;
    const stats = res.stats || {};
    setEl('billPaid', 'TK ' + fmt(stats.paid || 0));
    setEl('billPendingCount', stats.pending_count || 0);
    setEl('billTax', 'TK ' + fmt(stats.tax || 0));
    // Payment method breakdown
    const methodsEl = document.getElementById('paymentMethodsChart');
    if (methodsEl) {
        const breakdown = res.breakdown || [];
        if (!breakdown.length) { methodsEl.innerHTML = '<p class="text-muted">No payment data</p>'; }
        else {
            const total = breakdown.reduce((s, m) => s + parseInt(m.cnt), 0) || 1;
            const colors = { cash: '#27ae60', card: '#3498db', bkash: '#e91e8c', nagad: '#f5a623', rocket: '#9b59b6', online: '#e67e22' };
            methodsEl.innerHTML = breakdown.map(m => {
                const pct = ((m.cnt / total) * 100).toFixed(1);
                const c = colors[m.payment_method?.toLowerCase()] || '#95a5a6';
                return `<div class="d-flex align-items-center gap-3 mb-2" style="flex:0 0 calc(50% - 12px)">
                    <div style="width:42px;height:42px;border-radius:50%;background:${c};display:flex;align-items:center;justify-content:center;color:#fff;font-weight:700">${m.cnt}</div>
                    <div><div class="fw-semibold text-capitalize">${m.payment_method || 'Unknown'}</div>
                    <div class="text-muted" style="font-size:.82rem">${pct}% · TK ${fmt(m.total)}</div></div>
                </div>`;
            }).join('');
        }
    }
    const tbody = document.getElementById('billingTableBody');
    if (!tbody) return;
    if (!res.data.length) { tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No billing records</td></tr>'; return; }
    tbody.innerHTML = res.data.map(o => `
        <tr>
            <td class="fw-bold" style="font-size:.82rem">${o.order_id}</td>
            <td>${o.customer_name || '—'}</td>
            <td class="fw-bold">TK ${fmt(o.total_amount)}</td>
            <td class="text-capitalize">${o.payment_method || '—'}</td>
            <td style="font-size:.82rem;color:#64748b">${new Date(o.created_at).toLocaleDateString()}</td>
            <td><span class="status-badge status-${o.payment_status || 'pending'}">${o.payment_status || 'pending'}</span></td>
            <td style="text-align:center">
                ${o.payment_status !== 'paid'
                    ? `<button class="btn btn-success btn-sm" onclick="markBillPaid(${o.id})"><i class="fas fa-check me-1"></i>Mark Paid</button>`
                    : `<span class="text-success"><i class="fas fa-check-circle"></i></span>`}
            </td>
        </tr>`).join('');
}

async function markBillPaid(id) {
    const res = await api('update_payment_status', 'POST', { order_id: id, payment_status: 'paid' });
    if (res.status === 'success') { toast('Marked as paid!', 'success'); loadBilling(); }
    else toast('Error: ' + res.message, 'error');
}

// =========================================
// INVENTORY MANAGEMENT
// =========================================
async function loadInventory() {
    const res = await api('get_inventory');
    if (res.status === 'success') {
        window.allInventory = res.data || [];
        const total = window.allInventory.length;
        const lowStock = window.allInventory.filter(i => parseFloat(i.stock_quantity) <= parseFloat(i.min_stock)).length;
        const today = new Date();
        const soon = new Date(); soon.setDate(today.getDate() + 7);
        const expiring = window.allInventory.filter(i => i.expiry_date && new Date(i.expiry_date) <= soon && new Date(i.expiry_date) >= today).length;
        setEl('invTotal', total);
        setEl('invLowStock', lowStock);
        setEl('invExpiring', expiring);
        renderInventory(window.allInventory);
    }
}

function renderInventory(list) {
    const tbody = document.getElementById('inventoryTableBody');
    if (!tbody) return;
    if (!list.length) { tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No inventory items found</td></tr>'; return; }
    tbody.innerHTML = list.map(i => {
        const isLow = parseFloat(i.stock_quantity) <= parseFloat(i.min_stock);
        const today = new Date();
        const exp = i.expiry_date ? new Date(i.expiry_date) : null;
        const isExpiring = exp && (exp - today) / 86400000 <= 7 && exp >= today;
        const isExpired = exp && exp < today;
        return `<tr>
            <td class="fw-semibold">${i.name}</td>
            <td><span class="badge bg-light text-dark border text-capitalize">${i.category}</span></td>
            <td class="fw-bold ${isLow ? 'text-danger' : ''}">${fmt(i.stock_quantity)}</td>
            <td class="text-muted">${i.unit}</td>
            <td class="text-muted">${fmt(i.min_stock)}</td>
            <td style="font-size:.82rem;${isExpired ? 'color:#e74c3c;font-weight:600' : isExpiring ? 'color:#f39c12;font-weight:600' : 'color:#64748b'}">${i.expiry_date || '—'}</td>
            <td>${isExpired ? '<span class="status-badge" style="background:#e74c3c;color:#fff">Expired</span>' : isLow ? '<span class="status-badge status-cancelled">Low Stock</span>' : '<span class="status-badge status-active">OK</span>'}</td>
            <td class="action-buttons">
                <button class="action-btn edit-btn" onclick="editInventoryItem(${i.id})"><i class="fas fa-edit"></i></button>
                <button class="action-btn delete-btn" onclick="deleteInventoryItem(${i.id})"><i class="fas fa-trash"></i></button>
            </td>
        </tr>`;
    }).join('');
}

function showInventoryModal() {
    document.getElementById('inventoryModalTitle').textContent = 'Add Inventory Item';
    document.getElementById('inventoryId').value = '';
    document.getElementById('inventoryForm').reset();
    document.getElementById('inventoryModal').classList.add('active');
}
function closeInventoryModal() { document.getElementById('inventoryModal').classList.remove('active'); }

function editInventoryItem(id) {
    const i = (window.allInventory || []).find(x => x.id == id);
    if (!i) return;
    document.getElementById('inventoryModalTitle').textContent = 'Edit Inventory Item';
    document.getElementById('inventoryId').value = i.id;
    document.getElementById('invName').value = i.name;
    document.getElementById('invCategory').value = i.category;
    document.getElementById('invUnit').value = i.unit;
    document.getElementById('invStock').value = i.stock_quantity;
    document.getElementById('invMinStock').value = i.min_stock;
    document.getElementById('invExpiry').value = i.expiry_date || '';
    document.getElementById('inventoryModal').classList.add('active');
}

async function deleteInventoryItem(id) {
    if (!confirm('Delete this inventory item?')) return;
    const res = await api('delete_inventory', 'POST', { id });
    if (res.status === 'success') { toast('Item deleted!', 'success'); loadInventory(); }
    else toast('Error: ' + res.message, 'error');
}

// =========================================
// COMBO MEALS
// =========================================
let allCombos = [];

async function loadCombos() {
    const res = await api('get_combos');
    if (res.status === 'success') {
        allCombos = res.data;
        renderCombos(allCombos);
    }
}

function renderCombos(list) {
    const tbody = document.getElementById('combosTableBody');
    if (!tbody) return;
    if (!list || list.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No combo meals found. Create your first combo!</td></tr>';
        return;
    }
    tbody.innerHTML = list.map(c => {
        const discount = c.original_price > 0
            ? Math.round(((c.original_price - c.price) / c.original_price) * 100) + '%'
            : '—';
        return `<tr>
            <td><strong>${htmlEsc(c.name)}</strong><div class="text-muted" style="font-size:.8rem">${htmlEsc(c.description || '')}</div></td>
            <td><small class="text-muted">${htmlEsc(c.items || 'Not specified')}</small></td>
            <td>${c.original_price > 0 ? 'TK ' + fmt(c.original_price) : '—'}</td>
            <td><strong class="text-success">TK ${fmt(c.price)}</strong></td>
            <td>${discount !== '—' ? `<span class="badge" style="background:#e74c3c;color:#fff">${discount} off</span>` : '—'}</td>
            <td><span class="order-status-pill status-${c.status}">${c.status}</span></td>
            <td style="text-align:center">
                <button class="btn btn-sm btn-warning me-1" onclick="editCombo(${c.id})"><i class="fas fa-edit"></i></button>
                <button class="btn btn-sm btn-danger" onclick="deleteCombo(${c.id})"><i class="fas fa-trash"></i></button>
            </td>
        </tr>`;
    }).join('');
}

function showComboModal(combo = null) {
    document.getElementById('comboId').value = combo ? combo.id : '';
    document.getElementById('comboName').value = combo ? combo.name : '';
    document.getElementById('comboDesc').value = combo ? combo.description : '';
    document.getElementById('comboItems').value = combo ? combo.items : '';
    document.getElementById('comboOriginalPrice').value = combo ? combo.original_price : '';
    document.getElementById('comboPrice').value = combo ? combo.price : '';
    document.getElementById('comboStatus').value = combo ? combo.status : 'active';
    document.getElementById('comboModalTitle').textContent = combo ? 'Edit Combo Meal' : 'New Combo Meal';
    document.getElementById('comboModal').classList.add('active');
}

function closeComboModal() {
    document.getElementById('comboModal').classList.remove('active');
}

function editCombo(id) {
    const combo = allCombos.find(c => c.id == id);
    if (combo) showComboModal(combo);
}

async function deleteCombo(id) {
    if (!confirm('Delete this combo meal?')) return;
    const res = await api('delete_combo', 'POST', { id });
    if (res.status === 'success') { toast('Combo deleted!', 'success'); loadCombos(); }
    else toast('Error: ' + res.message, 'error');
}

// =========================================
// SUPPLIERS
// =========================================
let allSuppliers = [];

async function loadSuppliers() {
    const res = await api('get_suppliers');
    if (res.status === 'success') {
        allSuppliers = res.data;
        renderSuppliers(allSuppliers);
    }
}

function renderSuppliers(list) {
    const tbody = document.getElementById('suppliersTableBody');
    if (!tbody) return;
    if (!list || list.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No suppliers found. Add your first supplier.</td></tr>';
        return;
    }
    tbody.innerHTML = list.map(s => `<tr>
        <td><strong>${htmlEsc(s.name)}</strong></td>
        <td>${htmlEsc(s.contact_person || '—')}</td>
        <td>${htmlEsc(s.phone || '—')}</td>
        <td>${htmlEsc(s.email || '—')}</td>
        <td><small class="text-muted">${htmlEsc(s.products || '—')}</small></td>
        <td>${s.payment_due > 0 ? '<span class="text-danger fw-bold">TK ' + fmt(s.payment_due) + '</span>' : '<span class="text-success">Clear</span>'}</td>
        <td style="text-align:center">
            <button class="btn btn-sm btn-warning me-1" onclick="editSupplier(${s.id})"><i class="fas fa-edit"></i></button>
            <button class="btn btn-sm btn-danger" onclick="deleteSupplier(${s.id})"><i class="fas fa-trash"></i></button>
        </td>
    </tr>`).join('');
}

function showSupplierModal(supplier = null) {
    document.getElementById('supplierId').value = supplier ? supplier.id : '';
    document.getElementById('supplierName').value = supplier ? supplier.name : '';
    document.getElementById('supplierContact').value = supplier ? supplier.contact_person : '';
    document.getElementById('supplierPhone').value = supplier ? supplier.phone : '';
    document.getElementById('supplierEmail').value = supplier ? supplier.email : '';
    document.getElementById('supplierProducts').value = supplier ? supplier.products : '';
    document.getElementById('supplierAddress').value = supplier ? supplier.address : '';
    document.getElementById('supplierModalTitle').textContent = supplier ? 'Edit Supplier' : 'Add Supplier';
    document.getElementById('supplierModal').classList.add('active');
}

function closeSupplierModal() {
    document.getElementById('supplierModal').classList.remove('active');
}

function editSupplier(id) {
    const s = allSuppliers.find(s => s.id == id);
    if (s) showSupplierModal(s);
}

async function deleteSupplier(id) {
    if (!confirm('Delete this supplier?')) return;
    const res = await api('delete_supplier', 'POST', { id });
    if (res.status === 'success') { toast('Supplier deleted!', 'success'); loadSuppliers(); }
    else toast('Error: ' + res.message, 'error');
}

// =========================================
// PURCHASES
// =========================================
let allPurchases = [];

async function loadPurchases() {
    const res = await api('get_purchases');
    if (res.status === 'success') {
        allPurchases = res.data;
        renderPurchases(allPurchases);
        // Stats
        const total = allPurchases.length;
        const due = allPurchases
            .filter(p => p.payment_status === 'pending' || p.payment_status === 'partial')
            .reduce((sum, p) => sum + parseFloat(p.total_amount || 0), 0);
        setEl('purchaseTotal', total);
        setEl('purchaseDue', 'TK ' + fmt(due));
    }
    // Populate supplier dropdown in modal
    await loadSuppliersDropdown();
}

async function loadSuppliersDropdown() {
    const res = await api('get_suppliers');
    const sel = document.getElementById('purchaseSupplier');
    if (!sel || res.status !== 'success') return;
    const current = sel.value;
    sel.innerHTML = '<option value="">Select Supplier</option>' +
        res.data.map(s => `<option value="${s.id}">${htmlEsc(s.name)}</option>`).join('');
    if (current) sel.value = current;
}

function renderPurchases(list) {
    const tbody = document.getElementById('purchasesTableBody');
    if (!tbody) return;
    if (!list || list.length === 0) {
        tbody.innerHTML = '<tr><td colspan="8" class="text-center py-4 text-muted">No purchase orders found.</td></tr>';
        return;
    }
    tbody.innerHTML = list.map(p => `<tr>
        <td><code>${htmlEsc(p.po_number || '#' + p.id)}</code></td>
        <td>${htmlEsc(p.supplier_display || p.supplier_name || '—')}</td>
        <td><small class="text-muted">${htmlEsc(p.items || '—')}</small></td>
        <td><strong>TK ${fmt(p.total_amount)}</strong></td>
        <td>${p.purchase_date || '—'}</td>
        <td>
            <select class="form-select form-select-sm" style="width:110px" onchange="updatePurchaseStatus(${p.id},'payment_status',this.value)">
                <option value="pending" ${p.payment_status==='pending'?'selected':''}>Pending</option>
                <option value="partial" ${p.payment_status==='partial'?'selected':''}>Partial</option>
                <option value="paid" ${p.payment_status==='paid'?'selected':''}>Paid</option>
            </select>
        </td>
        <td>
            <select class="form-select form-select-sm" style="width:120px" onchange="updatePurchaseStatus(${p.id},'status',this.value)">
                <option value="ordered" ${p.status==='ordered'?'selected':''}>Ordered</option>
                <option value="received" ${p.status==='received'?'selected':''}>Received</option>
                <option value="cancelled" ${p.status==='cancelled'?'selected':''}>Cancelled</option>
            </select>
        </td>
        <td style="text-align:center">
            <button class="btn btn-sm btn-danger" onclick="deletePurchase(${p.id})"><i class="fas fa-trash"></i></button>
        </td>
    </tr>`).join('');
}

function showPurchaseModal() {
    document.getElementById('purchaseForm').reset();
    document.getElementById('purchaseDate').value = new Date().toISOString().split('T')[0];
    document.getElementById('purchaseModal').classList.add('active');
    loadSuppliersDropdown();
}

function closePurchaseModal() {
    document.getElementById('purchaseModal').classList.remove('active');
}

async function updatePurchaseStatus(id, field, value) {
    const res = await api('update_purchase_status', 'POST', { id, field, value });
    if (res.status === 'success') toast('Updated!', 'success');
    else toast('Error: ' + res.message, 'error');
}

async function deletePurchase(id) {
    if (!confirm('Delete this purchase order?')) return;
    const res = await api('delete_purchase', 'POST', { id });
    if (res.status === 'success') { toast('Purchase deleted!', 'success'); loadPurchases(); }
    else toast('Error: ' + res.message, 'error');
}

// =========================================
// GALLERY
// =========================================

async function loadGallery(category = 'all') {
    const url = category !== 'all' ? `get_gallery&category=${category}` : 'get_gallery';
    const res = await api(url);
    if (res.status === 'success') renderGalleryGrid(res.data);
}

function filterGallery(cat, btn) {
    document.querySelectorAll('#gallery .btn-outline-secondary').forEach(b => b.classList.remove('active'));
    if (btn) btn.classList.add('active');
    loadGallery(cat);
}

function renderGalleryGrid(list) {
    const grid = document.getElementById('galleryGrid');
    if (!grid) return;
    if (!list || list.length === 0) {
        grid.innerHTML = '<div class="text-center py-5 text-muted w-100"><i class="fas fa-images fa-3x mb-3 d-block opacity-25"></i>No gallery images found. Upload some photos!</div>';
        return;
    }
    grid.innerHTML = list.map(img => `
        <div class="gallery-item" style="position:relative;border-radius:10px;overflow:hidden;aspect-ratio:4/3;background:#1a1a2e">
            <img src="../${htmlEsc(img.image_url)}" alt="${htmlEsc(img.title || '')}"
                 style="width:100%;height:100%;object-fit:cover;transition:transform .3s"
                 onerror="this.src='../assets/images/favicon.png'">
            <div style="position:absolute;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,0);transition:background .3s;display:flex;align-items:flex-end;padding:10px"
                 onmouseover="this.style.background='rgba(0,0,0,0.5)'" onmouseout="this.style.background='rgba(0,0,0,0)'">
                <div style="width:100%;display:flex;justify-content:space-between;align-items:center">
                    <small style="color:#fff;text-shadow:0 1px 3px rgba(0,0,0,.8)">${htmlEsc(img.title || img.category)}</small>
                    <button class="btn btn-sm btn-danger" onclick="deleteSiteGalleryImage(${img.id})" style="padding:2px 8px">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `).join('');
}

async function uploadGalleryImages(input) {
    if (!input.files || input.files.length === 0) return;
    const fd = new FormData();
    for (let f of input.files) fd.append('images[]', f);
    fd.append('category', 'food');
    toast('Uploading...', 'info');
    const res = await api('upload_gallery', 'POST', fd, true);
    if (res.status === 'success') {
        toast(`${res.uploaded} image(s) uploaded!`, 'success');
        loadGallery();
    } else toast('Upload failed: ' + res.message, 'error');
    input.value = '';
}

async function deleteSiteGalleryImage(id) {
    if (!confirm('Delete this image permanently?')) return;
    const res = await api('delete_gallery', 'POST', { id });
    if (res.status === 'success') { toast('Image deleted!', 'success'); loadGallery(); }
    else toast('Error: ' + res.message, 'error');
}

// =========================================
// EVENTS
// =========================================
let allEvents = [];

async function loadEvents() {
    const res = await api('get_events');
    if (res.status === 'success') {
        allEvents = res.data;
        renderEvents(allEvents);
    }
}

function renderEvents(list) {
    const tbody = document.getElementById('eventsTableBody');
    if (!tbody) return;
    if (!list || list.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No events found. Add your first event.</td></tr>';
        return;
    }
    tbody.innerHTML = list.map(e => {
        const typeMap = { birthday: '🎂 Birthday', corporate: '💼 Corporate', private: '🔒 Private', live_music: '🎵 Live Music', festival: '🎉 Festival', other: '📋 Other' };
        const isPast = e.event_date && new Date(e.event_date) < new Date();
        return `<tr>
            <td><strong>${htmlEsc(e.name)}</strong></td>
            <td>${typeMap[e.type] || e.type}</td>
            <td>${e.event_date ? `<span class="${isPast ? 'text-muted' : 'text-success'}">${e.event_date}</span>` : '—'}</td>
            <td>${e.capacity > 0 ? e.capacity + ' guests' : '—'}</td>
            <td>${e.price > 0 ? 'TK ' + fmt(e.price) : 'Free'}</td>
            <td><span class="order-status-pill status-${e.status}">${e.status}</span></td>
            <td style="text-align:center">
                <button class="btn btn-sm btn-warning me-1" onclick="editEvent(${e.id})"><i class="fas fa-edit"></i></button>
                <button class="btn btn-sm btn-danger" onclick="deleteEvent(${e.id})"><i class="fas fa-trash"></i></button>
            </td>
        </tr>`;
    }).join('');
}

function showEventModal(ev = null) {
    document.getElementById('eventId').value = ev ? ev.id : '';
    document.getElementById('eventName').value = ev ? ev.name : '';
    document.getElementById('eventType').value = ev ? ev.type : 'birthday';
    document.getElementById('eventDate').value = ev ? ev.event_date : '';
    document.getElementById('eventCapacity').value = ev ? ev.capacity : '';
    document.getElementById('eventPrice').value = ev ? ev.price : '';
    document.getElementById('eventDesc').value = ev ? ev.description : '';
    document.getElementById('eventStatus').value = ev ? ev.status : 'active';
    document.getElementById('eventModalTitle').textContent = ev ? 'Edit Event' : 'Add Event';
    document.getElementById('eventModal').classList.add('active');
}

function closeEventModal() {
    document.getElementById('eventModal').classList.remove('active');
}

function editEvent(id) {
    const ev = allEvents.find(e => e.id == id);
    if (ev) showEventModal(ev);
}

async function deleteEvent(id) {
    if (!confirm('Delete this event?')) return;
    const res = await api('delete_event', 'POST', { id });
    if (res.status === 'success') { toast('Event deleted!', 'success'); loadEvents(); }
    else toast('Error: ' + res.message, 'error');
}

// =========================================
// WEBSITE CONTENT
// =========================================

let _cmsCurTab = 'testimonials';

async function loadWebsiteContent() {
    const res = await api('get_website_content');
    if (res.status !== 'success') return;
    const d = res.data;
    setVal('bannerHeading', d.banner_heading || '');
    setVal('bannerSubtext', d.banner_subtext || '');
    setVal('aboutUsContent', d.about_us || '');
    // Load CMS tabs
    loadTestimonials();
    loadFaqs();
    loadFeaturedFoodsPreview();
}

function switchCmsTab(tab) {
    _cmsCurTab = tab;
    const isTest = tab === 'testimonials';
    document.getElementById('cmsTestimonialsPanel').style.display = isTest ? '' : 'none';
    document.getElementById('cmsFaqPanel').style.display           = isTest ? 'none' : '';
    document.getElementById('tabTestimonialsBtn').className = 'btn btn-sm ' + (isTest ? 'btn-primary' : 'btn-outline-secondary');
    document.getElementById('tabFaqBtn').className          = 'btn btn-sm ' + (isTest ? 'btn-outline-secondary' : 'btn-primary');
    document.getElementById('cmsAddBtnLabel').textContent   = isTest ? 'Add Testimonial' : 'Add FAQ';
}

function cmsAddItem() {
    if (_cmsCurTab === 'testimonials') showTestimonialModal();
    else showFaqModal();
}

// ─── TESTIMONIALS ──────────────────────────────────────────────────────────

async function loadTestimonials() {
    const grid = document.getElementById('testimonialsGrid');
    if (!grid) return;
    grid.innerHTML = '<div class="text-center py-4 text-muted w-100"><i class="fas fa-spinner fa-spin me-2"></i>Loading...</div>';
    const res = await api('get_testimonials');
    if (res.status !== 'success') { grid.innerHTML = '<p class="text-muted text-center py-4">Could not load testimonials.</p>'; return; }
    const list = res.data || [];
    if (!list.length) {
        grid.innerHTML = '<div class="text-center py-5 text-muted w-100"><i class="fas fa-comment-slash fa-2x mb-2 d-block opacity-25"></i>No testimonials yet. Add your first one!</div>';
        return;
    }
    const stars = n => '★'.repeat(n) + '☆'.repeat(5 - n);
    grid.innerHTML = list.map(t => `
        <div class="col-md-6 col-lg-4">
            <div class="p-3 rounded h-100" style="background:#f8fafc;border:1px solid #e2e8f0;position:relative">
                <div class="d-flex align-items-start gap-2 mb-2">
                    <div style="width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#c9a74d,#f0d080);display:flex;align-items:center;justify-content:center;font-weight:800;color:#1a1a2e;flex-shrink:0">
                        ${htmlEsc(t.name.charAt(0).toUpperCase())}
                    </div>
                    <div>
                        <div style="font-weight:700;font-size:.88rem;color:#0f172a">${htmlEsc(t.name)}</div>
                        ${t.designation ? `<div style="font-size:.75rem;color:#64748b">${htmlEsc(t.designation)}</div>` : ''}
                    </div>
                    <span class="badge ${t.status==='active' ? 'bg-success' : 'bg-secondary'} ms-auto" style="font-size:.65rem">${t.status}</span>
                </div>
                <div style="color:#f59e0b;font-size:.85rem;margin-bottom:6px">${stars(parseInt(t.rating)||5)}</div>
                <p style="font-size:.82rem;color:#475569;line-height:1.5;margin:0">"${htmlEsc(t.content)}"</p>
                <div class="d-flex gap-1 mt-2 justify-content-end">
                    <button class="btn btn-sm btn-outline-${t.status==='active'?'warning':'success'}" style="font-size:.7rem"
                        onclick="toggleTestimonialStatus(${t.id},'${t.status}')">
                        <i class="fas fa-${t.status==='active'?'eye-slash':'eye'}"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-primary" style="font-size:.7rem" onclick="editTestimonial(${t.id})">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" style="font-size:.7rem" onclick="deleteTestimonial(${t.id})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>`).join('');
}

let _testimonials = [];

function showTestimonialModal(data = null) {
    document.getElementById('testimonialId').value       = data?.id || '';
    document.getElementById('testimonialName').value     = data?.name || '';
    document.getElementById('testimonialRating').value   = data?.rating || '5';
    document.getElementById('testimonialContent').value  = data?.content || '';
    document.getElementById('testimonialDesig').value    = data?.designation || '';
    document.getElementById('testimonialStatus').value   = data?.status || 'active';
    document.getElementById('testimonialModalTitle').innerHTML = (data ? '<i class="fas fa-edit me-2"></i>Edit' : '<i class="fas fa-plus me-2"></i>Add') + ' Testimonial';
    document.getElementById('testimonialModal').classList.add('active');
}

function closeTestimonialModal() {
    document.getElementById('testimonialModal').classList.remove('active');
}

async function editTestimonial(id) {
    const res = await api('get_testimonials');
    if (res.status !== 'success') return;
    const item = res.data.find(t => t.id == id);
    if (item) showTestimonialModal(item);
}

async function deleteTestimonial(id) {
    if (!confirm('Delete this testimonial?')) return;
    const res = await api('delete_testimonial', 'POST', { id });
    if (res.status === 'success') { toast('Testimonial deleted.', 'success'); loadTestimonials(); }
    else toast('Error: ' + res.message, 'error');
}

async function toggleTestimonialStatus(id, currentStatus) {
    const res = await api('toggle_testimonial_status', 'POST', { id, status: currentStatus });
    if (res.status === 'success') { toast(`Testimonial ${res.new_status}.`, 'success'); loadTestimonials(); }
    else toast('Error: ' + res.message, 'error');
}

document.addEventListener('DOMContentLoaded', () => {
    const tForm = document.getElementById('testimonialForm');
    if (tForm) tForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        const fd = new FormData();
        fd.append('id',          document.getElementById('testimonialId').value);
        fd.append('name',        document.getElementById('testimonialName').value);
        fd.append('rating',      document.getElementById('testimonialRating').value);
        fd.append('content',     document.getElementById('testimonialContent').value);
        fd.append('designation', document.getElementById('testimonialDesig').value);
        fd.append('status',      document.getElementById('testimonialStatus').value);
        const res = await api('save_testimonial', 'POST', fd, true);
        if (res.status === 'success') {
            toast('Testimonial saved!', 'success');
            closeTestimonialModal();
            loadTestimonials();
        } else toast('Error: ' + res.message, 'error');
    });
});

// ─── FAQ ───────────────────────────────────────────────────────────────────

async function loadFaqs() {
    const list = document.getElementById('faqList');
    if (!list) return;
    list.innerHTML = '<div class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Loading...</div>';
    const res = await api('get_faqs');
    if (res.status !== 'success') { list.innerHTML = '<p class="text-muted text-center py-4">Could not load FAQs.</p>'; return; }
    const faqs = res.data || [];
    if (!faqs.length) {
        list.innerHTML = '<div class="text-center py-5 text-muted"><i class="fas fa-question-circle fa-2x mb-2 d-block opacity-25"></i>No FAQs yet. Add your first one!</div>';
        return;
    }
    list.innerHTML = faqs.map(f => `
        <div class="p-3 rounded" style="background:#f8fafc;border:1px solid #e2e8f0">
            <div class="d-flex align-items-start gap-3">
                <div style="width:26px;height:26px;border-radius:50%;background:#c9a74d;display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:800;color:#1a1a2e;flex-shrink:0">Q</div>
                <div style="flex:1">
                    <div style="font-weight:700;font-size:.9rem;color:#0f172a;margin-bottom:4px">${htmlEsc(f.question)}</div>
                    <div style="font-size:.82rem;color:#475569">${htmlEsc(f.answer)}</div>
                </div>
                <div class="d-flex gap-1 align-items-center flex-shrink-0">
                    <span class="badge ${f.status==='active'?'bg-success':'bg-secondary'}" style="font-size:.65rem">${f.status}</span>
                    <button class="btn btn-sm btn-outline-${f.status==='active'?'warning':'success'}" style="font-size:.7rem"
                        onclick="toggleFaqStatus(${f.id},'${f.status}')">
                        <i class="fas fa-${f.status==='active'?'eye-slash':'eye'}"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-primary" style="font-size:.7rem" onclick="editFaq(${f.id})">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-outline-danger" style="font-size:.7rem" onclick="deleteFaq(${f.id})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>`).join('');
}

function showFaqModal(data = null) {
    document.getElementById('faqId').value         = data?.id || '';
    document.getElementById('faqQuestion').value   = data?.question || '';
    document.getElementById('faqAnswer').value     = data?.answer || '';
    document.getElementById('faqSortOrder').value  = data?.sort_order || '0';
    document.getElementById('faqStatus').value     = data?.status || 'active';
    document.getElementById('faqModalTitle').innerHTML = (data ? '<i class="fas fa-edit me-2"></i>Edit' : '<i class="fas fa-plus me-2"></i>Add') + ' FAQ';
    document.getElementById('faqModal').classList.add('active');
}

function closeFaqModal() {
    document.getElementById('faqModal').classList.remove('active');
}

async function editFaq(id) {
    const res = await api('get_faqs');
    if (res.status !== 'success') return;
    const item = res.data.find(f => f.id == id);
    if (item) showFaqModal(item);
}

async function deleteFaq(id) {
    if (!confirm('Delete this FAQ?')) return;
    const res = await api('delete_faq', 'POST', { id });
    if (res.status === 'success') { toast('FAQ deleted.', 'success'); loadFaqs(); }
    else toast('Error: ' + res.message, 'error');
}

async function toggleFaqStatus(id, currentStatus) {
    const res = await api('toggle_faq_status', 'POST', { id, status: currentStatus });
    if (res.status === 'success') { toast(`FAQ ${res.new_status}.`, 'success'); loadFaqs(); }
    else toast('Error: ' + res.message, 'error');
}

document.addEventListener('DOMContentLoaded', () => {
    const fForm = document.getElementById('faqForm');
    if (fForm) fForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        const fd = new FormData();
        fd.append('id',         document.getElementById('faqId').value);
        fd.append('question',   document.getElementById('faqQuestion').value);
        fd.append('answer',     document.getElementById('faqAnswer').value);
        fd.append('sort_order', document.getElementById('faqSortOrder').value);
        fd.append('status',     document.getElementById('faqStatus').value);
        const res = await api('save_faq', 'POST', fd, true);
        if (res.status === 'success') {
            toast('FAQ saved!', 'success');
            closeFaqModal();
            loadFaqs();
        } else toast('Error: ' + res.message, 'error');
    });
});

// ─── FEATURED FOODS PREVIEW ────────────────────────────────────────────────

async function loadFeaturedFoodsPreview() {
    const el = document.getElementById('featuredFoodsPreview');
    if (!el) return;
    const res = await api('get_featured_items');
    if (res.status !== 'success') { el.innerHTML = '<p class="text-muted">Could not load.</p>'; return; }
    const items = res.data || [];
    if (!items.length) {
        el.innerHTML = '<div class="col-12 text-center py-3 text-muted"><i class="fas fa-star me-2"></i>No featured items yet. Go to Menu Management and toggle the ⭐ Featured flag on menu items.</div>';
        return;
    }
    el.innerHTML = items.map(i => `
        <div class="col-6 col-md-4 col-lg-3">
            <div class="d-flex align-items-center gap-2 p-2 rounded" style="background:#f8fafc;border:1px solid #e2e8f0">
                <img src="../${htmlEsc(i.image_url||'assets/images/menu/default.jpg')}"
                     style="width:40px;height:40px;object-fit:cover;border-radius:6px"
                     onerror="this.src='../assets/images/menu/default.jpg'">
                <div style="min-width:0">
                    <div style="font-size:.8rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${htmlEsc(i.name)}</div>
                    <div style="font-size:.72rem;color:#64748b">TK ${parseFloat(i.price).toFixed(0)}</div>
                </div>
                <span class="badge bg-warning text-dark ms-auto" style="font-size:.6rem">⭐ Featured</span>
            </div>
        </div>`).join('');
}

// =========================================
// FORM HANDLERS (Combo, Supplier, Purchase, Event additions)
// =========================================

// =========================================
// SECURITY — Login History, Activity Log, Backup, Helpers
// =========================================

// Toggle password visibility
function toggleSecPwd(inputId, btn) {
    const inp = document.getElementById(inputId);
    if (!inp) return;
    const isHidden = inp.type === 'password';
    inp.type = isHidden ? 'text' : 'password';
    btn.querySelector('i').className = isHidden ? 'fas fa-eye-slash' : 'fas fa-eye';
}

// Load Login History
async function loadLoginHistory() {
    const filter = document.getElementById('loginHistoryFilter')?.value || 'all';
    const search = (document.getElementById('loginHistorySearch')?.value || '').toLowerCase();
    const el = document.getElementById('loginHistoryList');
    if (!el) return;

    const res = await api('get_login_history');
    if (res.status !== 'success') { el.innerHTML = '<p class="text-center py-3" style="color:#475569;">Could not load history.</p>'; return; }

    let list = res.data || [];
    if (filter !== 'all') list = list.filter(r => r.status === filter);
    if (search) list = list.filter(r =>
        (r.ip_address || '').toLowerCase().includes(search) ||
        (r.user_name  || '').toLowerCase().includes(search)
    );

    if (!list.length) { el.innerHTML = '<p class="text-center py-3" style="color:#475569;">No records found.</p>'; return; }

    el.innerHTML = list.map(r => {
        const isSuccess = r.status === 'success';
        const dt = r.created_at ? new Date(r.created_at).toLocaleString('en-GB', { day:'2-digit', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' }) : '';
        const ua  = r.user_agent || '';
        const dev = ua.includes('Mobile') ? '📱' : '🖥️';
        return `<div class="login-history-item ${isSuccess ? 'lh-success' : 'lh-failed'}">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div style="font-weight:700;color:#0f172a;font-size:.88rem;">${r.user_name || 'Unknown'} ${dev}</div>
                    <div style="font-size:.78rem;color:#475569;margin-top:2px;">
                        <i class="fas fa-map-marker-alt me-1"></i>${r.ip_address || 'N/A'}
                    </div>
                </div>
                <div class="text-end">
                    <span class="lh-badge ${isSuccess ? 'lh-badge-success' : 'lh-badge-fail'}">
                        <i class="fas ${isSuccess ? 'fa-check' : 'fa-times'} me-1"></i>${r.status}
                    </span>
                    <div style="font-size:.72rem;color:#64748b;margin-top:3px;">${dt}</div>
                </div>
            </div>
        </div>`;
    }).join('');

    // Stats
    const all  = res.data || [];
    const statsEl = document.getElementById('loginHistoryStats');
    if (statsEl) {
        const success = all.filter(r => r.status === 'success').length;
        const failed  = all.filter(r => r.status === 'failed').length;
        statsEl.innerHTML = `
            <span style="color:#27ae60;font-weight:700;"><i class="fas fa-check me-1"></i>${success} Successful</span>
            <span style="color:#e74c3c;font-weight:700;"><i class="fas fa-times me-1"></i>${failed} Failed</span>
            <span style="color:#475569;">Total: ${all.length}</span>`;
    }
}

// Load Security Activity Log
async function loadSecActivityLog() {
    const type   = document.getElementById('secLogTypeFilter')?.value || 'all';
    const tbody  = document.getElementById('secActivityBody');
    if (!tbody) return;

    const params = new URLSearchParams({ action: 'get_system_logs', category: type, page: 1 });
    let res;
    try {
        const r = await fetch('admin_api.php?' + params.toString());
        res = await r.json();
    } catch(e) { tbody.innerHTML = '<tr><td colspan="4" class="text-center py-3" style="color:#475569;">No data</td></tr>'; return; }

    if (res.status !== 'success') { tbody.innerHTML = '<tr><td colspan="4" class="text-center py-3" style="color:#475569;">No data</td></tr>'; return; }

    let list = (res.data || []).slice(0, 40);

    if (!list.length) { tbody.innerHTML = '<tr><td colspan="4" class="text-center py-3" style="color:#475569;">No activity found</td></tr>'; return; }

    const typeColors = { order:'#3498db', payment:'#27ae60', reservation:'#f39c12', system:'#8b5cf6', staff:'#be123c', login:'#0284c7', error:'#e74c3c', inventory:'#7c3aed' };
    const typeIcons  = { order:'fa-cart-shopping', payment:'fa-credit-card', reservation:'fa-calendar', system:'fa-server', staff:'fa-id-badge', login:'fa-right-to-bracket', error:'fa-triangle-exclamation', inventory:'fa-boxes-stacked' };
    const statusColor = { completed:'#16a34a', success:'#16a34a', active:'#16a34a', pending:'#d97706', warning:'#d97706', failed:'#dc2626', error:'#dc2626', cancelled:'#dc2626', info:'#0284c7' };

    tbody.innerHTML = list.map(r => {
        const dt    = r.created_at ? new Date(r.created_at).toLocaleString('en-GB', { day:'2-digit', month:'short', hour:'2-digit', minute:'2-digit' }) : '';
        const color = typeColors[r.category] || '#64748b';
        const icon  = typeIcons[r.category]  || 'fa-circle-info';
        const sc    = statusColor[r.status]  || '#64748b';
        const status = `<span style="background:${sc}20;color:${sc};border:1px solid ${sc}40;border-radius:20px;padding:2px 7px;font-size:.72rem;font-weight:700;">${r.status}</span>`;
        return `<tr>
            <td style="font-size:.78rem;color:#475569;">${dt}</td>
            <td><span style="background:${color}20;color:${color};border:1px solid ${color}40;border-radius:20px;padding:2px 8px;font-size:.72rem;font-weight:700;white-space:nowrap;">
                <i class="fas ${icon} me-1"></i>${r.category}</span></td>
            <td style="font-size:.82rem;color:#1e293b;font-weight:500;">${htmlEsc(r.event_type)}<br><span style="font-size:.75rem;color:#64748b;">${htmlEsc(r.message)}</span></td>
            <td>${status}</td>
        </tr>`;
    }).join('');
}

// Load Security Stats
async function loadSecurityStats() {
    const el = document.getElementById('securityStats');
    if (!el) return;
    const [lhRes, notifRes] = await Promise.all([api('get_login_history'), api('get_notifications')]);
    const logins  = lhRes.data  || [];
    const notifs  = notifRes.data || [];
    const today   = new Date().toDateString();
    const todayLogins = logins.filter(r => r.created_at && new Date(r.created_at).toDateString() === today);
    el.innerHTML = `
        <div class="sec-stat-row"><span style="color:#475569;font-weight:600;">Today's Logins</span><span style="color:#0f172a;font-weight:700;">${todayLogins.length}</span></div>
        <div class="sec-stat-row"><span style="color:#475569;font-weight:600;">Total Login Records</span><span style="color:#0f172a;font-weight:700;">${logins.length}</span></div>
        <div class="sec-stat-row"><span style="color:#475569;font-weight:600;">Failed Attempts (all time)</span><span style="color:#e74c3c;font-weight:700;">${logins.filter(r => r.status === 'failed').length}</span></div>
        <div class="sec-stat-row"><span style="color:#475569;font-weight:600;">System Notifications</span><span style="color:#0f172a;font-weight:700;">${notifs.length}</span></div>`;
}

// Download DB Backup
function downloadBackup() {
    window.open('admin_api.php?action=download_backup', '_blank');
}

// Called from setupFormHandlers() on DOMContentLoaded
function setupExtraFormHandlers() {

    // Inventory Form
    const inventoryForm = document.getElementById('inventoryForm');
    if (inventoryForm) {
        inventoryForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const fd = new FormData(this);
            const res = await api('save_inventory', 'POST', fd, true);
            if (res.status === 'success') {
                toast('Inventory item saved!', 'success');
                closeInventoryModal();
                loadInventory();
            } else toast('Error: ' + res.message, 'error');
        });
    }

    // Delivery Settings Form
    const deliverySettingsForm = document.getElementById('deliverySettingsForm');
    if (deliverySettingsForm) {
        deliverySettingsForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const data = {};
            new FormData(this).forEach((v, k) => data[k] = v);
            const res = await api('update_settings', 'POST', data);
            if (res.status === 'success') toast('Delivery settings saved!', 'success');
            else toast('Error: ' + res.message, 'error');
        });
    }

    // Combo Form
    const comboForm = document.getElementById('comboForm');
    if (comboForm) {
        comboForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const fd = new FormData(this);
            const res = await api('save_combo', 'POST', fd, true);
            if (res.status === 'success') {
                toast('Combo saved!', 'success');
                closeComboModal();
                loadCombos();
            } else toast('Error: ' + res.message, 'error');
        });
    }

    // Supplier Form
    const supplierForm = document.getElementById('supplierForm');
    if (supplierForm) {
        supplierForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const fd = new FormData(this);
            const res = await api('save_supplier', 'POST', fd, true);
            if (res.status === 'success') {
                toast('Supplier saved!', 'success');
                closeSupplierModal();
                loadSuppliers();
            } else toast('Error: ' + res.message, 'error');
        });
    }

    // Purchase Form
    const purchaseForm = document.getElementById('purchaseForm');
    if (purchaseForm) {
        purchaseForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const fd = new FormData(this);
            const res = await api('save_purchase', 'POST', fd, true);
            if (res.status === 'success') {
                toast('Purchase order saved!', 'success');
                closePurchaseModal();
                loadPurchases();
            } else toast('Error: ' + res.message, 'error');
        });
    }

    // Event Form
    const eventForm = document.getElementById('eventForm');
    if (eventForm) {
        eventForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const fd = new FormData(this);
            const res = await api('save_event', 'POST', fd, true);
            if (res.status === 'success') {
                toast('Event saved!', 'success');
                closeEventModal();
                loadEvents();
            } else toast('Error: ' + res.message, 'error');
        });
    }

    // Hero Banner Form
    const heroBannerForm = document.getElementById('heroBannerForm');
    if (heroBannerForm) {
        heroBannerForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const data = { banner_heading: document.getElementById('bannerHeading').value, banner_subtext: document.getElementById('bannerSubtext').value };
            const res = await api('update_settings', 'POST', data);
            if (res.status === 'success') toast('Banner updated!', 'success');
            else toast('Error: ' + res.message, 'error');
        });
    }

    // About Us Form
    const aboutUsForm = document.getElementById('aboutUsForm');
    if (aboutUsForm) {
        aboutUsForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const data = { about_us: document.getElementById('aboutUsContent').value };
            const res = await api('update_settings', 'POST', data);
            if (res.status === 'success') toast('About Us updated!', 'success');
            else toast('Error: ' + res.message, 'error');
        });
    }

    // Legal Form
    const legalForm = document.getElementById('legalForm');
    if (legalForm) {
        legalForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const fd = new FormData(this);
            const data = { terms_conditions: fd.get('terms_conditions'), privacy_policy: fd.get('privacy_policy') };
            const res = await api('update_settings', 'POST', data);
            if (res.status === 'success') toast('Legal pages updated!', 'success');
            else toast('Error: ' + res.message, 'error');
        });
    }
}

// =========================================
// HELPER — HTML Escape
// =========================================
function htmlEsc(str) {
    if (!str) return '';
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}


// =========================================
// CATEGORY MANAGEMENT
// =========================================

let allCategories = [];
let catDeleteTargetId = null;

async function loadCategories() {
    const res = await api('get_categories');
    if (res.status !== 'success') return;

    allCategories = res.data || [];
    const stats = res.stats || {};

    // Update stat cards
    setEl('catTotalCount',     stats.total       ?? allCategories.length);
    setEl('catActiveCount',    stats.active      ?? 0);
    setEl('catInactiveCount',  stats.inactive    ?? 0);
    setEl('catMenuItemCount',  stats.total_items ?? 0);

    // Update dynamic categories cache (used by Menu Management dropdowns)
    window.dynamicCategories = allCategories;
    populateCategoryDropdowns();

    filterCategories();
}

function filterCategories() {
    const status = document.getElementById('catStatusFilter')?.value || 'all';
    const q = (document.getElementById('catSearch')?.value || '').toLowerCase();
    const list = allCategories.filter(c =>
        (status === 'all' || c.status === status) &&
        (c.name.toLowerCase().includes(q) || c.slug.toLowerCase().includes(q))
    );
    renderCategoryTable(list);
}

function renderCategoryTable(list) {
    const tbody = document.getElementById('categoryTableBody');
    if (!tbody) return;

    if (!list.length) {
        tbody.innerHTML = `<tr><td colspan="8" class="text-center py-5 text-muted">
            <i class="fas fa-tags fa-2x mb-2 d-block opacity-50"></i>
            কোনো category পাওয়া যায়নি।
        </td></tr>`;
        return;
    }

    tbody.innerHTML = list.map((cat, idx) => {
        const isActive  = cat.status === 'active';
        const iconClass = cat.icon || 'fa-tag';
        const sortMin   = parseInt(cat.sort_order) || 0;
        return `
        <tr id="cat-row-${cat.id}">
            <td style="text-align:center">
                <div class="d-flex flex-column gap-1 align-items-center">
                    <button class="btn btn-outline-secondary btn-sm p-0" style="width:26px;height:24px;line-height:1"
                        onclick="moveCategorySort(${cat.id}, ${sortMin - 1})" title="Move Up">
                        <i class="fas fa-chevron-up" style="font-size:.65rem"></i>
                    </button>
                    <span style="font-size:.75rem;font-weight:700;color:#64748b">${sortMin}</span>
                    <button class="btn btn-outline-secondary btn-sm p-0" style="width:26px;height:24px;line-height:1"
                        onclick="moveCategorySort(${cat.id}, ${sortMin + 1})" title="Move Down">
                        <i class="fas fa-chevron-down" style="font-size:.65rem"></i>
                    </button>
                </div>
            </td>
            <td class="text-muted fw-semibold">${cat.id}</td>
            <td>
                <div class="d-flex align-items-center gap-2">
                    <span style="width:32px;height:32px;border-radius:8px;background:rgba(201,167,77,.15);display:flex;align-items:center;justify-content:center;color:#c9a74d;font-size:.9rem;flex-shrink:0">
                        <i class="fas ${iconClass}"></i>
                    </span>
                    <div>
                        <div class="fw-semibold" style="color:#1e293b">${escHtml(cat.name)}</div>
                        ${cat.description ? `<div class="text-muted" style="font-size:.78rem">${escHtml(cat.description)}</div>` : ''}
                    </div>
                </div>
            </td>
            <td>
                <code style="background:#f1f5f9;padding:2px 8px;border-radius:4px;font-size:.8rem;color:#0f172a">
                    ${escHtml(cat.slug)}
                </code>
            </td>
            <td>
                <span style="font-size:.82rem;color:#64748b">
                    <i class="fas ${iconClass} me-1"></i>${escHtml(iconClass)}
                </span>
            </td>
            <td style="text-align:center">
                <span class="badge rounded-pill" style="background:#eef2ff;color:#4338ca;border:1px solid #c7d2fe;font-size:.8rem;padding:4px 12px">
                    ${cat.item_count || 0}
                </span>
            </td>
            <td style="text-align:center">
                <div class="form-check form-switch d-flex justify-content-center mb-0">
                    <input class="form-check-input" type="checkbox" role="switch"
                        id="catSwitch_${cat.id}"
                        ${isActive ? 'checked' : ''}
                        onchange="toggleCategoryStatus(${cat.id}, this.checked)"
                        style="cursor:pointer">
                </div>
                <small style="font-size:.7rem;color:${isActive ? '#27ae60' : '#e74c3c'};font-weight:600">
                    ${isActive ? 'Active' : 'Inactive'}
                </small>
            </td>
            <td>
                <div class="d-flex gap-1 justify-content-center">
                    <button class="action-btn edit-btn" onclick="editCategory(${cat.id})" title="Edit">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="action-btn delete-btn" onclick="askDeleteCategory(${cat.id})" title="Delete">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        </tr>`;
    }).join('');
}

// ---- Show Add Modal ----
function showCategoryModal() {
    document.getElementById('categoryModalTitle').innerHTML = '<i class="fas fa-plus me-2"></i>New Category';
    document.getElementById('categoryForm').reset();
    document.getElementById('categoryId').value = '';
    document.getElementById('iconPreview').innerHTML = '<i class="fas fa-tag"></i>';
    // Auto set next sort order
    const maxSort = allCategories.reduce((m, c) => Math.max(m, parseInt(c.sort_order) || 0), 0);
    document.getElementById('categorySortOrder').value = maxSort + 1;
    document.getElementById('categoryModal').classList.add('active');
}

// ---- Close Modals ----
function closeCategoryModal() {
    document.getElementById('categoryModal').classList.remove('active');
}
function closeCatDeleteModal() {
    document.getElementById('catDeleteModal').classList.remove('active');
    catDeleteTargetId = null;
}

// ---- Edit ----
function editCategory(id) {
    const cat = allCategories.find(c => c.id == id);
    if (!cat) return;
    document.getElementById('categoryModalTitle').innerHTML = '<i class="fas fa-edit me-2"></i>Edit Category';
    document.getElementById('categoryId').value        = cat.id;
    document.getElementById('categoryName').value      = cat.name;
    document.getElementById('categorySlug').value      = cat.slug;
    document.getElementById('categoryIcon').value      = cat.icon || '';
    document.getElementById('categoryDesc').value      = cat.description || '';
    document.getElementById('categorySortOrder').value = cat.sort_order || 0;
    document.getElementById('categoryStatus').value    = cat.status || 'active';
    previewCategoryIcon();
    document.getElementById('categoryModal').classList.add('active');
}

// ---- Slug auto-generate ----
function autoGenerateSlug(force = false) {
    const nameEl = document.getElementById('categoryName');
    const slugEl = document.getElementById('categorySlug');
    if (!nameEl || !slugEl) return;
    if (!force && slugEl.value.trim()) return; // don't overwrite if user typed manually
    const slug = nameEl.value
        .toLowerCase()
        .trim()
        .replace(/[^a-z0-9\s\-&]/g, '')
        .replace(/\s+/g, ' ')
        .trim();
    slugEl.value = slug;
}

// ---- Icon preview ----
function previewCategoryIcon() {
    const input = document.getElementById('categoryIcon')?.value?.trim() || 'fa-tag';
    const preview = document.getElementById('iconPreview');
    if (!preview) return;
    // Clean input: strip "fas fa-" if user pasted full class
    let icon = input.replace(/^fas\s+/, '').replace(/^fa-/, '');
    preview.innerHTML = `<i class="fas fa-${icon}"></i>`;
}

// ---- Toggle Status ----
async function toggleCategoryStatus(id, isChecked) {
    const status = isChecked ? 'active' : 'inactive';
    const res = await api('toggle_category_status', 'POST', { id, status });
    if (res.status === 'success') {
        toast(`Category ${status === 'active' ? 'activated' : 'deactivated'}`, 'success');
        // Update local cache
        const cat = allCategories.find(c => c.id == id);
        if (cat) {
            cat.status = status;
            const label = document.querySelector(`#cat-row-${id} small`);
            if (label) {
                label.textContent = status === 'active' ? 'Active' : 'Inactive';
                label.style.color = status === 'active' ? '#27ae60' : '#e74c3c';
            }
        }
        // Update stats
        setEl('catActiveCount',   allCategories.filter(c => c.status === 'active').length);
        setEl('catInactiveCount', allCategories.filter(c => c.status === 'inactive').length);
    } else {
        toast('Error: ' + res.message, 'error');
        // Revert checkbox
        const cb = document.getElementById(`catSwitch_${id}`);
        if (cb) cb.checked = !isChecked;
    }
}

// ---- Sort Order ----
async function moveCategorySort(id, newOrder) {
    if (newOrder < 0) newOrder = 0;
    const res = await api('update_category_sort', 'POST', { id, sort_order: newOrder });
    if (res.status === 'success') {
        // Reload to reflect new order
        loadCategories();
    } else toast('Error: ' + res.message, 'error');
}

// ---- Delete ----
function askDeleteCategory(id) {
    const cat = allCategories.find(c => c.id == id);
    if (!cat) return;
    catDeleteTargetId = id;
    setEl('catDeleteName', escHtml(cat.name));
    setEl('catDeleteItemCount', cat.item_count || 0);
    document.getElementById('catDeleteModal').classList.add('active');
}

async function confirmDeleteCategory() {
    if (!catDeleteTargetId) return;
    const btn = document.getElementById('catDeleteConfirmBtn');
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Deleting...'; }

    const res = await api('delete_category', 'POST', { id: catDeleteTargetId });
    if (res.status === 'success') {
        toast('Category deleted!', 'success');
        closeCatDeleteModal();
        loadCategories();
    } else {
        toast('Error: ' + res.message, 'error');
    }
    if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-trash me-1"></i>Yes, Delete'; }
}

// ---- Form Submit ----
document.addEventListener('DOMContentLoaded', () => {
    const catForm = document.getElementById('categoryForm');
    if (catForm) {
        catForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const fd = new FormData(catForm);
            // Normalize slug: lowercase, keep spaces and hyphens
            const slugVal = (fd.get('slug') || '').toLowerCase().trim();
            fd.set('slug', slugVal);

            const res = await api('save_category', 'POST', fd, true);
            if (res.status === 'success') {
                toast(res.message || 'Category saved!', 'success');
                closeCategoryModal();
                loadCategories();
                // Also refresh menu management category dropdowns
                populateCategoryDropdowns();
            } else {
                toast('Error: ' + res.message, 'error');
            }
        });
    }
});

// ---- Populate category dropdowns in Menu Management ----
function populateCategoryDropdowns() {
    const cats = window.dynamicCategories || [];
    if (!cats.length) return;

    // Filter dropdown (All Categories + each active category)
    const filterSel = document.getElementById('categoryFilter');
    if (filterSel) {
        const currentVal = filterSel.value;
        filterSel.innerHTML = '<option value="all">All Categories</option>' +
            cats
                .filter(c => c.status === 'active')
                .map(c => `<option value="${escHtml(c.slug)}">${escHtml(c.name)}</option>`)
                .join('');
        filterSel.value = currentVal; // restore selection
    }

    // Add/Edit modal category dropdown
    const modalSel = document.getElementById('itemCategory');
    if (modalSel) {
        const currentVal = modalSel.value;
        modalSel.innerHTML = '<option value="">Select Category</option>' +
            cats
                .filter(c => c.status === 'active')
                .map(c => `<option value="${escHtml(c.slug)}">${escHtml(c.name)}</option>`)
                .join('');
        if (currentVal) modalSel.value = currentVal;
    }
}

// Helper: get display name for a category slug
function getCategoryName(slug) {
    if (!slug) return '—';
    // Try dynamic categories first
    const dynCat = (window.dynamicCategories || []).find(c => c.slug === slug);
    if (dynCat) return dynCat.name;
    // Fallback to static config labels
    if (window.categoryLabels && window.categoryLabels[slug]) return window.categoryLabels[slug];
    // Last fallback: capitalize slug
    return slug.replace(/-/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}

// Helper: escape HTML
function escHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

