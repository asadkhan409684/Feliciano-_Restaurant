// Admin Panel JavaScript
let activeBranchId = 'all';

// API Helper
async function fetchAPI(action, method = 'GET', data = null) {
    let url = `admin_api.php?action=${action}`;
    if (method === 'GET') {
        url += `&branch_id=${activeBranchId}`;
    }
    
    const options = {
        method: method,
        headers: {
            'Content-Type': 'application/json'
        }
    };
    if (data) options.body = JSON.stringify(data);

    try {
        const response = await fetch(url, options);
        return await response.json();
    } catch (error) {
        console.error('API Error:', error);
        return { status: 'error', message: 'Connection failed' };
    }
}

function handleGlobalBranchChange(branchId) {
    activeBranchId = branchId;
    // Show loading state or refresh data
    loadAllData();
    console.log(`Switched to Branch ID: ${branchId}`);
}

// Data fetching functions
async function loadAllData() {
    await Promise.all([
        loadDashboardStats(),
        loadMenuItems(),
        loadOrders(),
        loadReservations(),
        loadCustomers(),
        loadTeam(),
        loadBranches(),
        loadSettings()
    ]);
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function () {
    loadAllData();
    // Refresh stats every minute
    setInterval(loadDashboardStats, 60000);
    setInterval(updateNotificationIndicator, 30000); // Check for new notifications every 30 seconds
    updateNotificationIndicator(); // Initial check
    setupFormHandlers();
    initAnalyticsDates(); // Set default date range for analytics
});

// Navigation
function showSection(sectionId) {
    document.querySelectorAll('.admin-section').forEach(section => {
        section.classList.remove('active');
    });

    document.querySelectorAll('.nav-link').forEach(link => {
        link.classList.remove('active');
    });

    const activeSection = document.getElementById(sectionId);
    if (activeSection) activeSection.classList.add('active');

    // Find the link that was clicked or matching link
    const links = document.querySelectorAll('.nav-link');
    links.forEach(link => {
        const onClickAttr = link.getAttribute('onclick');
        if (onClickAttr && onClickAttr.includes(sectionId)) {
            link.classList.add('active');
        }
    });

    // Refresh data for the section
    if (sectionId === 'dashboard') loadDashboardStats();
    if (sectionId === 'menu-management') loadMenuItems();
    if (sectionId === 'orders') loadOrders();
    if (sectionId === 'customers') loadCustomers();
    if (sectionId === 'team') loadTeam();
    if (sectionId === 'reservations') loadReservations();
    if (sectionId === 'analytics') loadAnalytics();
    if (sectionId === 'settings') loadSettings();
    if (sectionId === 'notifications') loadNotifications();
    if (sectionId === 'branches') loadBranches();
}

// Dashboard Functions
async function loadDashboardStats() {
    const res = await fetchAPI('get_stats');
    if (res.status === 'success') {
        const stats = res.data;
        document.getElementById('totalOrders').textContent = stats.total_orders;
        document.getElementById('totalRevenue').textContent = `TK ${parseFloat(stats.total_revenue).toLocaleString()}`;
        document.getElementById('totalMenuItems').textContent = stats.menu_items;
        document.getElementById('totalReservations').textContent = stats.total_customers; // Using customers as placeholder if reservations count not in stats

        // Actually, let's fix the reservations count in stats or use a separate call
        const resStats = await fetchAPI('get_reservations');
        if (resStats.status === 'success') {
            document.getElementById('totalReservations').textContent = resStats.data.length;
        }
    }
}

async function updateRecentActivity() {
    // This could also be pulled from a real notifications table
    const activityContainer = document.getElementById('recentActivity');
    activityContainer.innerHTML = `
        <div class="activity-item">
            <i class="fas fa-info-circle"></i>
            <span>Monitoring system active</span>
            <small>Live updates enabled</small>
        </div>
    `;
}

async function updateNotificationIndicator() {
    const res = await fetchAPI('get_notifications');
    if (res.status === 'success') {
        const notifications = res.data;
        const unreadCount = notifications.filter(n => parseInt(n.is_read) === 0).length;

        const notificationCount = document.getElementById('notificationCount');
        if (notificationCount) {
            notificationCount.textContent = unreadCount;
            if (unreadCount > 0) {
                notificationCount.classList.remove('hidden');
                document.title = `(${unreadCount}) Feliciano Admin Panel`;
            } else {
                notificationCount.classList.add('hidden');
                document.title = 'Feliciano Admin Panel';
            }
        }
        
        // Also update the dropdown menu content
        renderDropdownNotifications(notifications.filter(n => parseInt(n.is_read) === 0).slice(0, 5));
    }
}

function showNotifications() {
    showSection('notifications');
}

// Menu Management Functions
async function loadMenuItems() {
    const res = await fetchAPI('get_menu');
    if (res.status === 'success') {
        const menuItems = res.data;
        window.allMenuItems = menuItems; // Store globally for filtering
        renderMenuItems(menuItems);
    }
}

function renderMenuItems(menuItems) {
    const tbody = document.getElementById('menuTableBody');
    tbody.innerHTML = '';

    menuItems.forEach(item => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td><img src="../${item.image_url}" alt="${item.name}" class="menu-item-image"></td>
            <td>${item.name}</td>
            <td>${window.categoryLabels && window.categoryLabels[item.category] ? window.categoryLabels[item.category] : item.category}</td>
            <td>TK ${parseFloat(item.price).toLocaleString()}</td>
            <td><span class="status-badge status-${item.status}">${item.status}</span></td>
            <td class="action-buttons">
                <button class="action-btn edit-btn" onclick="editMenuItem(${item.id})">
                    <i class="fas fa-edit"></i> Edit
                </button>
                <button class="action-btn delete-btn" onclick="deleteMenuItem(${item.id})">
                    <i class="fas fa-trash"></i> Delete
                </button>
            </td>
        `;
        tbody.appendChild(row);
    });
}

function filterMenuItems() {
    const category = document.getElementById('categoryFilter').value;
    const searchTerm = document.getElementById('menuSearch').value.toLowerCase();

    if (!window.allMenuItems) return;

    const filteredItems = window.allMenuItems.filter(item => {
        const matchesCategory = category === 'all' || item.category === category;
        const matchesSearch = item.name.toLowerCase().includes(searchTerm);
        return matchesCategory && matchesSearch;
    });

    renderMenuItems(filteredItems);
}

function searchMenuItems() {
    filterMenuItems();
}

function showAddMenuModal() {
    document.getElementById('modalTitle').textContent = 'Add New Menu Item';
    document.getElementById('menuItemId').value = '';
    document.getElementById('addMenuForm').reset();

    // Reset Image Preview
    const previewImg = document.getElementById('previewImg');
    const previewPlaceholder = document.querySelector('.image-preview i');
    const previewText = document.querySelector('.image-preview span');

    previewImg.src = '';
    previewImg.style.display = 'none';
    if (previewPlaceholder) previewPlaceholder.style.display = 'block';
    if (previewText) previewText.style.display = 'block';

    document.getElementById('addMenuModal').classList.add('active');
}

function closeAddMenuModal() {
    document.getElementById('addMenuModal').classList.remove('active');
    document.getElementById('addMenuForm').reset();
}

function editMenuItem(id) {
    const item = window.allMenuItems.find(i => i.id == id);
    if (item) {
        document.getElementById('modalTitle').textContent = 'Edit Menu Item';
        document.getElementById('menuItemId').value = item.id;
        document.getElementById('itemName').value = item.name;
        document.getElementById('itemCategory').value = item.category;
        document.getElementById('itemPrice').value = item.price;
        document.getElementById('itemDescription').value = item.description || '';
        document.getElementById('itemIngredients').value = item.ingredients || '';

        // Show Current Image in Preview
        const previewImg = document.getElementById('previewImg');
        const previewPlaceholder = document.querySelector('.image-preview i');
        const previewText = document.querySelector('.image-preview span');

        if (item.image_url) {
            previewImg.src = '../' + item.image_url; // Adjust path if needed
            previewImg.style.display = 'block';
            if (previewPlaceholder) previewPlaceholder.style.display = 'none';
            if (previewText) previewText.style.display = 'none';
        }

        document.getElementById('addMenuModal').classList.add('active');
    }
}

// Image Preview Function
function previewImage(input) {
    const previewImg = document.getElementById('previewImg');
    const previewPlaceholder = document.querySelector('.image-preview i');
    const previewText = document.querySelector('.image-preview span');

    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function (e) {
            previewImg.src = e.target.result;
            previewImg.style.display = 'block';
            if (previewPlaceholder) previewPlaceholder.style.display = 'none';
            if (previewText) previewText.style.display = 'none';
        }
        reader.readAsDataURL(input.files[0]);
    }
}

async function deleteMenuItem(id) {
    if (confirm('Are you sure you want to delete this menu item?')) {
        const res = await fetchAPI('delete_menu_item', 'POST', { id: id });
        if (res.status === 'success') {
            loadMenuItems();
            loadDashboardStats();
            alert('Menu item deleted successfully!');
        } else {
            alert('Error deleting item: ' + res.message);
        }
    }
}

// Orders Management Functions
async function loadOrders() {
    const res = await fetchAPI('get_orders');

    const orders = (res.status === 'success' && res.data) ? res.data : [];
    window.allOrders = orders;

    filterOrders(); // Trigger filter to render the list and analytics
}

function filterOrders() {
    const status = document.getElementById('orderStatusFilter').value;
    const dateFilter = document.getElementById('orderDateFilter') ? document.getElementById('orderDateFilter').value : 'all';
    const orders = window.allOrders || [];

    const now = new Date();
    const filteredOrders = orders.filter(order => {
        // Status filter
        if (status !== 'all' && order.status !== status) return false;

        // Date filter
        const orderDate = new Date(order.date);
        if (dateFilter === 'today') {
            return orderDate.toDateString() === now.toDateString();
        } else if (dateFilter === '7days') {
            const sevenDaysAgo = new Date(now.getTime() - (7 * 24 * 60 * 60 * 1000));
            return orderDate >= sevenDaysAgo;
        } else if (dateFilter === '30days') {
            const thirtyDaysAgo = new Date(now.getTime() - (30 * 24 * 60 * 60 * 1000));
            return orderDate >= thirtyDaysAgo;
        }
        return true;
    });

    // Sort orders by date (newest first)
    filteredOrders.sort((a, b) => new Date(b.date) - new Date(a.date));

    renderOrderAnalytics(filteredOrders);
    renderOrdersList(filteredOrders);
}

function renderOrderAnalytics(orders) {
    const totalOrders = orders.length;
    const totalRevenue = orders.reduce((sum, order) => sum + parseFloat(order.total), 0);
    const avgValue = totalOrders > 0 ? totalRevenue / totalOrders : 0;

    const container = document.getElementById('orderAnalyticsGrid');
    if (!container) return;

    container.innerHTML = `
        <div class="stat-card" style="background: white; padding: 25px 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-left: 5px solid #3498db; display: flex; align-items: center; gap: 20px; text-align: left; justify-content: flex-start; margin: 0;">
            <div style="background: rgba(52, 152, 219, 0.1); width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #3498db; font-size: 1.8rem; flex-shrink: 0;">
                <i class="fas fa-shopping-cart"></i>
            </div>
            <div>
                <div style="color: #7f8c8d; font-size: 0.95rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; margin-bottom: 5px;">Filtered Orders</div>
                <div style="font-size: 2rem; font-weight: 700; color: #2c3e50; line-height: 1;">${totalOrders}</div>
            </div>
        </div>
        
        <div class="stat-card" style="background: white; padding: 25px 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-left: 5px solid #2ecc71; display: flex; align-items: center; gap: 20px; text-align: left; justify-content: flex-start; margin: 0;">
            <div style="background: rgba(46, 204, 113, 0.1); width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #2ecc71; font-size: 1.8rem; flex-shrink: 0;">
                <i class="fas fa-coins"></i>
            </div>
            <div>
                <div style="color: #7f8c8d; font-size: 0.95rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; margin-bottom: 5px;">Filtered Revenue</div>
                <div style="font-size: 1.8rem; font-weight: 700; color: #2c3e50; line-height: 1;">TK ${totalRevenue.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
            </div>
        </div>
        
        <div class="stat-card" style="background: white; padding: 25px 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); border-left: 5px solid #e67e22; display: flex; align-items: center; gap: 20px; text-align: left; justify-content: flex-start; margin: 0;">
            <div style="background: rgba(230, 126, 34, 0.1); width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #e67e22; font-size: 1.8rem; flex-shrink: 0;">
                <i class="fas fa-chart-line"></i>
            </div>
            <div>
                <div style="color: #7f8c8d; font-size: 0.95rem; text-transform: uppercase; font-weight: 600; letter-spacing: 0.5px; margin-bottom: 5px;">Avg Order Value</div>
                <div style="font-size: 1.8rem; font-weight: 700; color: #2c3e50; line-height: 1;">TK ${avgValue.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
            </div>
        </div>
    `;
}

function renderOrdersList(orders) {
    const tbody = document.getElementById('ordersTableBody');
    if (!tbody) return;
    
    tbody.innerHTML = '';

    if (orders.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 40px; color: #7f8c8d;"><i class="fas fa-search" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i> No orders found.</td></tr>';
        return;
    }

    orders.forEach(order => {
        const row = document.createElement('tr');
        
        const dateObj = new Date(order.date);
        const dateStr = dateObj.toLocaleDateString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
        const timeStr = dateObj.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit', hour12: true });

        const isOnline = order.order_type === 'online';
        const typeIcon = isOnline ? 'fa-truck' : 'fa-store';
        const typeClass = isOnline ? 'type-online' : 'type-offline';

        row.innerHTML = `
            <td>
                <div style="font-weight: 700; color: #1e293b; font-size: 0.95rem;">#${order.id.split('-')[0]}-</div>
                <div style="font-weight: 700; color: #1e293b; font-size: 0.95rem;">${order.id.split('-').slice(1).join('-')}</div>
            </td>
            <td>
                <div style="font-weight: 700; color: #2c3e50; font-size: 0.95rem; margin-bottom: 2px;">${order.customer_name || 'Customer'}</div>
                <div style="color: #94a3b8; font-size: 0.85rem; margin-bottom: 2px;">${order.customer}</div>
                <div style="color: #64748b; font-size: 0.85rem;"><i class="fas fa-phone-alt" style="font-size: 0.75rem; margin-right: 5px;"></i> ${order.customer_phone || 'N/A'}</div>
            </td>
            <td style="text-align: center;">
                <span class="order-type-badge ${typeClass}">
                    <i class="fas ${typeIcon}"></i> ${order.order_type.toUpperCase()}
                </span>
            </td>
            <td>
                <div style="color: #94a3b8; font-size: 0.9rem; margin-bottom: 4px;">${dateStr},</div>
                <div style="color: #94a3b8; font-size: 0.9rem;">${timeStr}</div>
            </td>
            <td>
                <div style="color: #1e293b; font-weight: 700; font-size: 0.85rem; margin-bottom: 2px;">TK</div>
                <div style="color: #1e293b; font-weight: 700; font-size: 1rem;">${parseFloat(order.total).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</div>
            </td>
            <td style="text-align: center;">
                <span class="order-status-pill status-${order.status}">${order.status}</span>
            </td>
            <td style="text-align: center;">
                <button class="details-square-btn" onclick="editOrderDetails(${order.db_id})" title="View Details">
                    <i class="fas fa-eye"></i>
                </button>
            </td>
        `;

        tbody.appendChild(row);
    });
}

async function updateOrderStatus(dbId, newStatus) {
    const res = await fetchAPI('update_order_status', 'POST', { order_id: dbId, status: newStatus });
    if (res.status === 'success') {
        loadOrders();
        loadDashboardStats();
        alert(`Order status updated to ${newStatus}!`);
    } else {
        alert('Error updating status: ' + res.message);
    }
}

function editOrderDetails(dbId) {
    const order = window.allOrders.find(o => o.db_id == dbId);
    if (!order) return;

    document.getElementById('viewOrderId').textContent = order.id;
    document.getElementById('viewOrderDate').textContent = new Date(order.date).toLocaleString();
    document.getElementById('viewOrderCustomer').textContent = order.customer;

    const statusSpan = document.getElementById('viewOrderStatus');
    statusSpan.textContent = order.status.charAt(0).toUpperCase() + order.status.slice(1);
    statusSpan.className = `order-status status-${order.status}`;

    document.getElementById('viewOrderTotal').textContent = parseFloat(order.total).toLocaleString(undefined, { minimumFractionDigits: 2 });

    const itemsHTML = order.items.map(item => `
        <div style="display: flex; justify-content: space-between; margin-bottom: 8px; padding-bottom: 8px; border-bottom: 1px dashed #e0e6ed;">
            <span><strong>${item.name}</strong> <span style="color: #7f8c8d;">x ${item.qty}</span></span>
            <span>TK ${(item.price * item.qty).toLocaleString()}</span>
        </div>
    `).join('');

    document.getElementById('viewOrderItems').innerHTML = itemsHTML || '<div style="color: #7f8c8d; text-align: center;">No items found</div>';

    document.getElementById('editOrderModal').classList.add('active');
}

function closeEditOrderModal() {
    document.getElementById('editOrderModal').classList.remove('active');
}

async function deleteOrder(dbId) {
    if (confirm('Are you absolutely sure you want to delete this order? This action cannot be undone.')) {
        const res = await fetchAPI('delete_order', 'POST', { order_id: dbId });
        if (res.status === 'success') {
            loadOrders();
            loadDashboardStats();
            alert('Order deleted successfully!');
        } else {
            alert('Error deleting order: ' + res.message);
        }
    }
}

// Reservations Management Functions
async function loadReservations() {
    const res = await fetchAPI('get_reservations');
    if (res.status === 'success') {
        window.allReservations = res.data || [];
        filterReservations();
    }
}

function filterReservations() {
    const dateFilter = document.getElementById('reservationDate').value;
    const statusFilter = document.getElementById('reservationStatus').value;
    const reservations = window.allReservations || [];

    let filtered = reservations;
    if (dateFilter) {
        filtered = filtered.filter(res => res.reservation_date === dateFilter);
    }
    if (statusFilter !== 'all') {
        filtered = filtered.filter(res => res.status === statusFilter);
    }

    const tbody = document.getElementById('reservationsTableBody');
    if (!tbody) return;
    tbody.innerHTML = '';

    if (filtered.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 40px; color: #7f8c8d;">No matching reservations found.</td></tr>';
        return;
    }

    filtered.forEach(res => {
        const row = document.createElement('tr');
        
        // Status color mapping for a more attractive look
        const statusColors = {
            'pending': { bg: '#fff4e6', text: '#d9480f', border: '#ffd8a8' },
            'confirmed': { bg: '#e6fcf5', text: '#0ca678', border: '#c3fae8' },
            'cancelled': { bg: '#fff5f5', text: '#f03e3e', border: '#ffc9c9' },
            'completed': { bg: '#f4fce3', text: '#5c940d', border: '#d8f5a2' }
        };
        const status = (res.status || 'pending').toLowerCase();
        const colors = statusColors[status] || statusColors['pending'];

        row.innerHTML = `
            <td>
                <div style="font-weight: 600; color: #2c3e50; font-size: 0.85rem; background: #f1f3f5; padding: 4px 8px; border-radius: 4px; display: inline-block;">
                    #${res.reservation_id}
                </div>
            </td>
            <td>
                <div style="font-weight: 600; color: #1e293b;">${res.customer_name}</div>
            </td>
            <td>
                <div style="color: #64748b; font-size: 0.9rem;">
                    <i class="fas fa-phone-alt" style="color: #3498db; margin-right: 6px; font-size: 0.8rem;"></i>${res.customer_phone}
                </div>
            </td>
            <td>
                <div style="color: #64748b; font-size: 0.9rem;">
                    <i class="far fa-calendar-alt" style="color: #e67e22; margin-right: 6px; font-size: 0.8rem;"></i>${res.reservation_date}
                </div>
            </td>
            <td>
                <div style="color: #64748b; font-size: 0.9rem;">
                    <i class="far fa-clock" style="color: #9b59b6; margin-right: 6px; font-size: 0.8rem;"></i>${res.reservation_time}
                </div>
            </td>
            <td style="text-align: center;">
                <div style="display: inline-flex; align-items: center; justify-content: center; background: #eef2ff; color: #4338ca; font-weight: 700; padding: 4px 12px; border-radius: 20px; font-size: 0.85rem; border: 1px solid #c7d2fe;">
                    <i class="fas fa-users" style="margin-right: 6px; font-size: 0.75rem;"></i>${res.guests_count}
                </div>
            </td>
            <td>
                <span style="display: inline-block; padding: 6px 12px; border-radius: 6px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; background-color: ${colors.bg}; color: ${colors.text}; border: 1px solid ${colors.border};">
                    ${res.status}
                </span>
            </td>
        `;
        tbody.appendChild(row);
    });
}

async function updateReservationStatus(resId, newStatus) {
    const res = await fetchAPI('update_reservation_status', 'POST', { id: resId, status: newStatus });
    if (res.status === 'success') {
        loadReservations();
    } else {
        alert('Error updating reservation: ' + res.message);
    }
}

// Customers Management Functions
async function loadCustomers() {
    const res = await fetchAPI('get_customers');
    const tbody = document.getElementById('customersTableBody');
    if (!tbody) return;
    tbody.innerHTML = '';

    if (res.status !== 'success' || !res.data || res.data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 40px; color: #7f8c8d;">No customer data available yet</td></tr>';
        return;
    }

    res.data.forEach(customer => {
        const row = document.createElement('tr');
        const lastOrder = customer.last_order_date ? new Date(customer.last_order_date).toLocaleDateString() : 'Never';
        const totalOrders = customer.total_orders || 0;
        const totalSpent = parseFloat(customer.total_spent || 0).toLocaleString(undefined, { minimumFractionDigits: 2 });
        const custId = customer.customer_id || `CUST-${customer.id}`;
        
        row.innerHTML = `
            <td><span style="font-weight: 600; color: #1e293b;">${custId}</span></td>
            <td><div style="font-weight: 500;">${customer.full_name}</div></td>
            <td style="color: #64748b;">${customer.email}</td>
            <td style="color: #64748b;">${customer.phone || 'N/A'}</td>
            <td style="text-align: center;"><span class="badge bg-light text-dark" style="border: 1px solid #ddd; padding: 4px 10px;">${totalOrders}</span></td>
            <td style="font-weight: 600; color: #1e293b;">TK ${totalSpent}</td>
            <td style="color: #94a3b8; font-size: 0.9rem;">${lastOrder}</td>
        `;
        tbody.appendChild(row);
    });
}

// Team Management Functions
async function loadTeam() {
    const res = await fetchAPI('get_users');
    const tbody = document.getElementById('teamTableBody');
    tbody.innerHTML = '';

    if (res.status !== 'success' || res.data.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 40px; color: #7f8c8d;">No team data available</td></tr>';
        return;
    }

    window.allUsers = res.data; // Store for editing

    const table = tbody.closest('table');
    if (table) table.classList.add('team-table-management');

    // Filter non-customer roles
    const teamMembers = res.data.filter(u => {
        const r = (u.role || '').toLowerCase().trim();
        return r === 'admin' || r === 'manager' || r === 'staff' || r !== 'customer';
    });

    // Load branches for the dropdown if not already loaded
    if (!window.allBranches) await loadBranches();
    
    const branchDropdown = document.getElementById('staffBranch');
    if (branchDropdown) {
        branchDropdown.innerHTML = '<option value="">No Branch / Main</option>';
        window.allBranches.forEach(b => {
            branchDropdown.innerHTML += `<option value="${b.id}">${b.name}</option>`;
        });
    }

    if (teamMembers.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; padding: 40px; color: #7f8c8d;">No team members found.</td></tr>';
        return;
    }

    teamMembers.forEach(user => {
        const roleLower = (user.role || '').toLowerCase().trim();
        const isMainAdmin = roleLower === 'admin';
        const roleColor = roleLower === 'admin' ? '#e74c3c' : (roleLower === 'manager' ? '#f39c12' : '#3498db');
        const row = document.createElement('tr');
        
        row.innerHTML = `
            <td style="font-weight: 600; color: #34495e;">#${user.id}</td>
            <td style="font-weight: 500;">${user.full_name}</td>
            <td style="max-width: 200px; word-break: break-all; font-size: 0.85rem; color: #7f8c8d;">${user.email}</td>
            <td>
                <span class="status-badge" style="background: ${roleColor}; color: white; display: inline-block; min-width: 90px; text-align: center; text-transform: capitalize; border-radius: 4px; padding: 4px 8px; font-weight: 600; font-size: 0.75rem;">
                    ${user.role}
                </span>
                ${user.branch_id ? `<div style="font-size: 0.7rem; color: #7f8c8d; margin-top: 4px;"><i class="fas fa-map-marker-alt"></i> ${window.allBranches.find(b => b.id == user.branch_id)?.name || 'Unknown'}</div>` : ''}
            </td>
            <td>
                <span class="status-badge status-${(user.status || 'active').toLowerCase()}" style="text-transform: capitalize;">
                    ${user.status || 'Active'}
                </span>
            </td>
            <td style="color: #95a5a6; font-size: 0.8rem;">${new Date(user.created_at).toLocaleDateString()}</td>
            <td>
                <div class="action-buttons" style="justify-content: center; gap: 8px;">
                    ${isMainAdmin ?
                '<span class="badge bg-light text-muted" style="font-style: italic; border: 1px solid #ddd; padding: 5px 10px; font-size: 0.75rem;">System Admin</span>' :
                `<button class="action-btn edit-btn" onclick="editStaff(${user.id})" title="Edit Detail">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="action-btn delete-btn" onclick="deleteStaff(${user.id})" title="Remove Member">
                        <i class="fas fa-trash"></i>
                    </button>`
            }
                </div>
            </td>
        `;
        tbody.appendChild(row);
    });
}

function showAddStaffModal() {
    document.getElementById('staffModalTitle').textContent = 'Add New Team Member';
    document.getElementById('staffId').value = '';
    document.getElementById('staffForm').reset();
    document.getElementById('staffPasswordGroup').style.display = 'block';
    document.getElementById('staffPassword').required = true;
    document.getElementById('staffBranch').value = '';
    toggleBranchSelection();
    document.getElementById('staffModal').classList.add('active');
}

function closeStaffModal() {
    document.getElementById('staffModal').classList.remove('active');
}

function editStaff(id) {
    const user = window.allUsers.find(u => u.id == id);
    if (user) {
        document.getElementById('staffModalTitle').textContent = 'Edit Team Member';
        document.getElementById('staffId').value = user.id;
        document.getElementById('staffFirstName').value = user.first_name;
        document.getElementById('staffLastName').value = user.last_name;
        document.getElementById('staffEmail').value = user.email;
        document.getElementById('staffRole').value = user.role;
        document.getElementById('staffStatus').value = user.status;
        document.getElementById('staffBranch').value = user.branch_id || '';
        
        toggleBranchSelection();

        // Hide password requirement for editing
        document.getElementById('staffPassword').required = false;
        document.getElementById('staffPassword').value = '';

        document.getElementById('staffModal').classList.add('active');
    }
}

async function deleteStaff(id) {
    if (confirm('Are you sure you want to remove this team member?')) {
        const res = await fetchAPI('delete_user', 'POST', { id: id });
        if (res.status === 'success') {
            loadTeam();
            alert('Team member removed successfully!');
        } else {
            alert('Error: ' + res.message);
        }
    }
}

function exportCustomers() {
    alert('Customer data export functionality would be implemented here!');
}

// Analytics & Reports Dashboard Functions
async function loadAnalytics() {
    const startDateInput = document.getElementById('startDate');
    const endDateInput = document.getElementById('endDate');
    
    // Set default dates if empty
    if (!startDateInput.value || !endDateInput.value) {
        initAnalyticsDates();
    }
    
    const start = startDateInput.value;
    const end = endDateInput.value;
    const query = (start && end) ? `&start=${start}&end=${end}` : "";

    // Show loading state
    const summaryContainer = document.getElementById('analyticsSummary');
    if (summaryContainer) summaryContainer.innerHTML = '<div class="col-12 text-center py-4"><i class="fas fa-circle-notch fa-spin me-2"></i> Loading data...</div>';

    try {
        // 1. Load Summary
        const resSummary = await fetchAPI(`get_analytics_summary${query}`);
        if (resSummary.status === 'success') {
            renderAnalyticsSummary(resSummary.data);
        }

        // 2. Load Popular Items
        const resItems = await fetchAPI(`get_popular_items${query}`);
        if (resItems.status === 'success') {
            renderPopularItems(resItems.data);
        }

        // 3. Load Daily Sales
        const resSales = await fetchAPI(`get_daily_sales${query}`);
        if (resSales.status === 'success') {
            renderSalesTrend(resSales.data);
        }

        // 4. Load Monthly Revenue (1 Year)
        const resMonthly = await fetchAPI('get_monthly_revenue');
        if (resMonthly.status === 'success') {
            renderMonthlyChart(resMonthly.data);
        } else {
            renderMonthlyChart([]); // Clear loading even on error
        }
    } catch (error) {
        console.error('Analytics Loading Error:', error);
        renderMonthlyChart([]); // Clear loading state
    }
}

function renderMonthlyChart(data) {
    const container = document.getElementById('monthlyRevenueChart');
    const yAxisContainer = document.getElementById('chartYAxis');
    if (!container || !yAxisContainer) return;

    if (!data || data.length === 0) {
        container.innerHTML = '<div class="text-center py-5 w-100 text-muted">No monthly data available</div>';
        yAxisContainer.innerHTML = '<span></span><span></span><span></span><span></span><span>0</span>';
        return;
    }

    const revenues = data.map(d => parseFloat(d.revenue));
    const maxRevenue = Math.max(...revenues, 1000); // At least 1000 for scale
    
    // Generate Y-Axis Labels (amount markers)
    const steps = 4;
    const stepSize = maxRevenue / steps;
    let yAxisHTML = '';
    for (let i = steps; i >= 0; i--) {
        const value = i * stepSize;
        let label = value >= 1000 ? (value / 1000).toFixed(1) + 'K' : Math.round(value).toString();
        if (label.endsWith('.0K')) label = label.split('.')[0] + 'K';
        yAxisHTML += `<span>${label}</span>`;
    }
    yAxisContainer.innerHTML = yAxisHTML;

    // Render Bars
    container.innerHTML = data.map(item => {
        const height = maxRevenue > 0 ? (item.revenue / maxRevenue) * 100 : 0;
        const formattedRevenue = `TK ${parseFloat(item.revenue).toLocaleString()}`;
        const shortMonth = item.month.substring(0, 3);
        const shortYear = item.year.toString().slice(-2);
        
        return `
            <div class="chart-bar-container">
                <div class="chart-bar" 
                     style="height: 0%" 
                     data-height="${height}%" 
                     data-revenue="${formattedRevenue}">
                </div>
                <div class="chart-label">${shortMonth} '${shortYear}</div>
            </div>
        `;
    }).join('');

    // Trigger animation
    setTimeout(() => {
        container.querySelectorAll('.chart-bar').forEach(bar => {
            bar.style.height = bar.dataset.height;
        });
    }, 200);
}

function calculateGrowth(current, previous) {
    if (!previous || previous == 0) return current > 0 ? 100 : 0;
    return ((current - previous) / previous) * 100;
}

function getTrendBadge(percentage) {
    const icon = percentage >= 0 ? '<i class="fas fa-caret-up"></i>' : '<i class="fas fa-caret-down"></i>';
    const type = percentage > 0 ? 'positive' : (percentage < 0 ? 'negative' : 'neutral');
    return `<span class="trend-badge ${type}">${icon} ${Math.abs(percentage).toFixed(1)}%</span>`;
}

function formatHour(hour) {
    const h = parseInt(hour);
    const ampm = h >= 12 ? 'PM' : 'AM';
    const displayHour = h % 12 || 12;
    return `${displayHour} ${ampm}`;
}

function renderAnalyticsSummary(data) {
    const container = document.getElementById('analyticsSummary');
    if (!container) return;

    const prev = data.previous || { total_revenue: 0, total_orders: 0, avg_order_value: 0 };
    const revGrowth = calculateGrowth(data.total_revenue, prev.total_revenue);
    const ordGrowth = calculateGrowth(data.total_orders, prev.total_orders);
    const aovGrowth = calculateGrowth(data.avg_order_value, prev.avg_order_value);

    container.innerHTML = `
        <div class="metric-card revenue">
            <div class="metric-icon"><i class="fas fa-hand-holding-usd"></i></div>
            <div class="metric-details">
                <span class="metric-label">Total Revenue ${getTrendBadge(revGrowth)}</span>
                <span class="metric-value">TK ${parseFloat(data.total_revenue).toLocaleString(undefined, {minimumFractionDigits: 2})}</span>
            </div>
        </div>
        <div class="metric-card orders">
            <div class="metric-icon"><i class="fas fa-shopping-basket"></i></div>
            <div class="metric-details">
                <span class="metric-label">Total Orders ${getTrendBadge(ordGrowth)}</span>
                <span class="metric-value">${data.total_orders}</span>
            </div>
        </div>
        <div class="metric-card aov">
            <div class="metric-icon"><i class="fas fa-chart-line"></i></div>
            <div class="metric-details">
                <span class="metric-label">Avg. Order Value ${getTrendBadge(aovGrowth)}</span>
                <span class="metric-value">TK ${parseFloat(data.avg_order_value).toLocaleString(undefined, {maximumFractionDigits: 0})}</span>
            </div>
        </div>
        <div class="metric-card category">
            <div class="metric-icon"><i class="fas fa-award"></i></div>
            <div class="metric-details">
                <span class="metric-label">Top Category</span>
                <span class="metric-value text-capitalize" style="font-size: 1.2rem;">${data.top_category || 'N/A'}</span>
            </div>
        </div>
    `;

    // Operational Insights
    const busiestDayEl = document.getElementById('busiestDay');
    const peakHourEl = document.getElementById('peakHour');
    if (busiestDayEl) busiestDayEl.textContent = data.busiest_day || 'N/A';
    if (peakHourEl) {
        const startH = formatHour(data.peak_hour);
        const endH = formatHour(parseInt(data.peak_hour) + 1);
        peakHourEl.textContent = `${startH} - ${endH}`;
    }

    // Generate Smart Advice
    renderSmartInsights(data, revGrowth);
}

function renderSmartInsights(data, growth) {
    const advisorText = document.getElementById('advisorText');
    if (!advisorText) return;

    const formattedPeak = formatHour(data.peak_hour);
    let advice = "";
    if (growth > 10) {
        advice = `Excellent growth! High demand on <strong>${data.busiest_day}s</strong>. Consider spotlighting your popular <strong>${data.top_category}</strong> items in your next newsletter to maintain momentum.`;
    } else if (growth < -5) {
        advice = `Revenue is down by ${Math.abs(growth).toFixed(1)}%. We noticed a peak at <strong>${formattedPeak}</strong>. Try launching a 'Happy Hour' promotion around this time to boost numbers!`;
    } else if (data.total_orders > 0 && parseFloat(data.avg_order_value) < 500) {
        advice = `Steady volume, but Average Order Value is low (TK ${Math.round(data.avg_order_value)}). Try 'Combo Deals' for your top-selling items to encourage higher spending.`;
    } else {
        advice = `Your business is stable. <strong>${data.busiest_day}</strong> is your strongest day. Why not try a special weekend bundle to attract more customers during off-peak times?`;
    }

    advisorText.innerHTML = advice;
}

function renderPopularItems(items) {
    const container = document.getElementById('popularItems');
    if (!container) return;

    if (!items || items.length === 0) {
        container.innerHTML = '<div style="text-align: center; padding: 40px; color: #94a3b8;"><i class="fas fa-info-circle mb-2 d-block" style="font-size: 2rem;"></i> No data for this period</div>';
        return;
    }

    const maxOrders = Math.max(...items.map(i => i.order_count));
    
    container.innerHTML = items.map(item => {
        const percentage = maxOrders > 0 ? (item.order_count / maxOrders) * 100 : 0;
        return `
            <div class="popular-item-container">
                <div class="popular-item-head">
                    <span class="popular-item-name">${item.name}</span>
                    <span class="popular-item-val">${item.order_count} Orders</span>
                </div>
                <div class="popularity-progress">
                    <div class="popularity-bar" style="width: 0%" data-width="${percentage}%"></div>
                </div>
            </div>
        `;
    }).join('');

    // Animate bars
    setTimeout(() => {
        container.querySelectorAll('.popularity-bar').forEach(bar => {
            bar.style.width = bar.dataset.width;
        });
    }, 100);
}

function renderSalesTrend(sales) {
    const container = document.getElementById('revenueChart');
    if (!container) return;

    if (!sales || sales.length === 0) {
        container.innerHTML = '<div style="text-align: center; padding: 40px; color: #94a3b8;"><i class="fas fa-history mb-2 d-block" style="font-size: 2rem;"></i> No sales recorded</div>';
        return;
    }

    container.innerHTML = sales.map(sale => `
        <div class="sales-trend-item">
            <div class="trend-date">${new Date(sale.sale_date).toLocaleDateString('en-GB', {day: '2-digit', month: 'short', year: 'numeric'})}</div>
            <div class="trend-metrics">
                <span class="trend-revenue">TK ${parseFloat(sale.total_revenue).toLocaleString(undefined, {minimumFractionDigits: 2})}</span>
                <span class="trend-orders">${sale.total_orders} orders total</span>
            </div>
        </div>
    `).join('');
}

function generateReport() {
    loadAnalytics();
}

function initAnalyticsDates() {
    const endDate = new Date();
    const startDate = new Date();
    startDate.setDate(startDate.getDate() - 30);

    const startInput = document.getElementById('startDate');
    const endInput = document.getElementById('endDate');
    
    if (startInput && !startInput.value) startInput.value = startDate.toISOString().split('T')[0];
    if (endInput && !endInput.value) endInput.value = endDate.toISOString().split('T')[0];

    loadAnalytics();
}

// Settings Functions
async function loadSettings() {
    const res = await fetchAPI('get_settings');
    if (res.status === 'success') {
        const settings = res.data;
        if (document.getElementById('restaurantName')) document.getElementById('restaurantName').value = settings.restaurant_name || '';
        if (document.getElementById('restaurantAddress')) document.getElementById('restaurantAddress').value = settings.restaurant_address || '';
        if (document.getElementById('restaurantPhone')) document.getElementById('restaurantPhone').value = settings.restaurant_phone || '';
        if (document.getElementById('restaurantEmail')) document.getElementById('restaurantEmail').value = settings.restaurant_email || '';
        
        // Load Operating Hours
        if (document.getElementById('opening_hours_mon_thu')) document.getElementById('opening_hours_mon_thu').value = settings.opening_hours_mon_thu || '';
        if (document.getElementById('opening_hours_fri_sat')) document.getElementById('opening_hours_fri_sat').value = settings.opening_hours_fri_sat || '';
        if (document.getElementById('opening_hours_sun')) document.getElementById('opening_hours_sun').value = settings.opening_hours_sun || '';
    }
}

// Form Handlers
function setupFormHandlers() {
    // Add Menu Form
    const addMenuForm = document.getElementById('addMenuForm');
    if (addMenuForm) {
        addMenuForm.removeEventListener('submit', handleAddMenu); // Prevent duplicate listeners
        addMenuForm.addEventListener('submit', handleAddMenu);
    }

    // Restaurant Info Form
    const restaurantInfoForm = document.getElementById('restaurantInfoForm');
    if (restaurantInfoForm) {
        restaurantInfoForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Saving...';

            const data = {
                restaurant_name: document.getElementById('restaurantName').value,
                restaurant_address: document.getElementById('restaurantAddress').value,
                restaurant_phone: document.getElementById('restaurantPhone').value,
                restaurant_email: document.getElementById('restaurantEmail').value
            };
            const res = await fetchAPI('update_settings', 'POST', data);
            
            btn.disabled = false;
            btn.textContent = 'Save Changes';

            if (res.status === 'success') {
                showToast('Success!', 'Restaurant information updated successfully.', 'success');
            } else {
                showToast('Error', res.message || 'Failed to update settings', 'error');
            }
        });
    }

    // Operating Hours Form
    const operatingHoursForm = document.getElementById('operatingHoursForm');
    if (operatingHoursForm) {
        operatingHoursForm.addEventListener('submit', async function (e) {
            e.preventDefault();
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Updating...';

            const data = {
                opening_hours_mon_thu: document.getElementById('opening_hours_mon_thu').value,
                opening_hours_fri_sat: document.getElementById('opening_hours_fri_sat').value,
                opening_hours_sun: document.getElementById('opening_hours_sun').value
            };
            const res = await fetchAPI('update_settings', 'POST', data);

            btn.disabled = false;
            btn.textContent = 'Update Hours';

            if (res.status === 'success') {
                showToast('Success!', 'Operating hours updated successfully.', 'success');
            } else {
                showToast('Error', res.message || 'Failed to update hours', 'error');
            }
        });
    }

    // Staff Form
    const staffForm = document.getElementById('staffForm');
    if (staffForm) {
        staffForm.addEventListener('submit', handleStaffSubmit);
    }

    // Branch Form
    const branchForm = document.getElementById('branchForm');
    if (branchForm) {
        branchForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            const btn = this.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Saving...';

            try {
                const response = await fetch('api-branches.php?action=save', {
                    method: 'POST',
                    body: formData
                });
                const res = await response.json();
                if (res.success) {
                    loadBranches();
                    closeBranchModal();
                    showToast('Success', 'Branch details saved!', 'success');
                } else {
                    showToast('Error', res.message, 'error');
                }
            } catch (error) {
                console.error('Error saving branch:', error);
                showToast('Error', 'Connection failed', 'error');
            } finally {
                btn.disabled = false;
                btn.textContent = 'Save Branch';
            }
        });
    }
}

async function handleStaffSubmit(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const staffId = document.getElementById('staffId').value;

    let action = staffId ? 'update_user' : 'create_user';

    const response = await fetch(`admin_api.php?action=${action}`, {
        method: 'POST',
        body: formData
    });
    const res = await response.json();

    if (res.status === 'success') {
        loadTeam();
        closeStaffModal();
        alert(staffId ? 'Team member updated successfully!' : 'New team member created successfully!');
    } else {
        alert('Error: ' + res.message);
    }
}

async function handleAddMenu(e) {
    e.preventDefault();
    const formData = new FormData(e.target);
    const itemId = document.getElementById('menuItemId').value;

    let action = 'add_menu_item';
    if (itemId) {
        action = 'update_menu_item';
        formData.append('id', itemId);
    }

    const response = await fetch(`admin_api.php?action=${action}`, {
        method: 'POST',
        body: formData
    });
    const res = await response.json();

    if (res.status === 'success') {
        loadMenuItems();
        loadDashboardStats();
        closeAddMenuModal();
        alert(itemId ? 'Menu item updated successfully!' : 'Menu item added successfully!');
    } else {
        alert('Error: ' + res.message);
    }
}

// Logout Function
function logout() {
    if (confirm('Are you sure you want to logout?')) {
        window.location.href = '../auth/logout.php';
    }
}

// Branch Management Functions
async function loadBranches() {
    const response = await fetch('api-branches.php?action=list');
    const res = await response.json();
    if (res.success) {
        window.allBranches = res.data;
        renderBranches(res.data);
        
        // Update any branch dropdowns in the UI
        const staffBranch = document.getElementById('staffBranch');
        if (staffBranch) {
            const currentVal = staffBranch.value;
            staffBranch.innerHTML = '<option value="">No Branch / Main</option>';
            res.data.forEach(b => {
                staffBranch.innerHTML += `<option value="${b.id}">${b.name}</option>`;
            });
            staffBranch.value = currentVal;
        }
    }
}

function renderBranches(branches) {
    const tbody = document.getElementById('branchTableBody');
    if (!tbody) return;
    tbody.innerHTML = '';

    if (branches.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 40px; color: #7f8c8d;">No branches found. Add your first branch!</td></tr>';
        return;
    }

    branches.forEach(branch => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td style="font-weight: 600; color: #34495e;">#${branch.id}</td>
            <td style="font-weight: 600;">${branch.name}</td>
            <td style="font-size: 0.9rem; color: #7f8c8d;">${branch.location}</td>
            <td style="color: #3498db; font-weight: 600;">${branch.phone}</td>
            <td style="color: #95a5a6; font-size: 0.8rem;">${new Date(branch.created_at).toLocaleDateString()}</td>
            <td>
                <div class="action-buttons" style="justify-content: center; gap: 8px;">
                    <button class="action-btn edit-btn" onclick="editBranch(${branch.id})" title="Edit Branch">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="action-btn delete-btn" onclick="deleteBranch(${branch.id})" title="Delete Branch">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </td>
        `;
        tbody.appendChild(row);
    });
}

function showAddBranchModal() {
    document.getElementById('branchModalTitle').textContent = 'Add New Branch';
    document.getElementById('branchId').value = '';
    document.getElementById('branchForm').reset();
    document.getElementById('branchModal').classList.add('active');
}

function closeBranchModal() {
    document.getElementById('branchModal').classList.remove('active');
}

function editBranch(id) {
    const branch = window.allBranches.find(b => b.id == id);
    if (branch) {
        document.getElementById('branchModalTitle').textContent = 'Edit Branch';
        document.getElementById('branchId').value = branch.id;
        document.getElementById('branchNameForm').value = branch.name;
        document.getElementById('branchLocation').value = branch.location;
        document.getElementById('branchPhone').value = branch.phone;
        document.getElementById('branchModal').classList.add('active');
    }
}

async function deleteBranch(id) {
    if (confirm('Are you sure you want to delete this branch? Orders associated with this branch will remain but the branch link will be removed.')) {
        const formData = new FormData();
        formData.append('id', id);
        const response = await fetch('api-branches.php?action=delete', {
            method: 'POST',
            body: formData
        });
        const res = await response.json();
        if (res.success) {
            loadBranches();
            showToast('Success', 'Branch deleted successfully', 'success');
        } else {
            showToast('Error', res.message, 'error');
        }
    }
}

function toggleBranchSelection() {
    const role = document.getElementById('staffRole').value;
    const branchGroup = document.getElementById('branchSelectionGroup');
    if (role === 'manager' || role === 'staff') {
        branchGroup.style.display = 'block';
    } else {
        branchGroup.style.display = 'none';
        document.getElementById('staffBranch').value = '';
    }
}



function renderDropdownNotifications(notifications) {
    const container = document.getElementById('dropdownNotificationsList');
    if (!container) return;

    if (notifications.length === 0) {
        container.innerHTML = `<li id="noNotifItem"><span class="dropdown-item py-4 text-center text-muted">No unread notifications</span></li>`;
        return;
    }

    container.innerHTML = notifications.map(notif => `
        <li>
            <a class="dropdown-item py-3 px-3 border-bottom notification-dropdown-item" href="#" onclick="handleDropdownNotifClick(event, ${notif.id}, '${notif.type}', '${notif.related_id}')" style="white-space: normal; background-color: #f8f9fa; transition: all 0.2s;">
                <div class="d-flex w-100 justify-content-between align-items-start mb-1">
                    <h6 class="mb-0 text-truncate" style="font-size: 0.95rem; font-weight: 600; color: #2c3e50; max-width: 75%;">
                        <i class="fas ${notif.type === 'order' ? 'fa-shopping-cart text-primary' : (notif.type === 'reservation' ? 'fa-calendar-alt text-warning' : 'fa-info-circle text-info')} me-2"></i>
                        ${notif.title}
                    </h6>
                    <small class="text-muted" style="font-size: 0.75rem;">${new Date(notif.created_at).toLocaleDateString('en-US', {month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit'})}</small>
                </div>
                <p class="mb-0 text-muted" style="font-size: 0.85rem; line-height: 1.4; margin-left: 28px;">
                    ${notif.message}
                </p>
            </a>
        </li>
    `).join('');
}

async function handleDropdownNotifClick(e, id, type, relatedId) {
    if (e) e.preventDefault();
    
    // Mark as read
    await fetchAPI('mark_notification_read', 'POST', { id: id });
    
    // Update UI
    updateNotificationIndicator();

    // Navigate to relevant section
    if (type === 'order') {
        showSection('orders');
    } else if (type === 'reservation') {
        showSection('reservations');
    } else {
        showSection('notifications');
    }
}

async function markNotificationRead(id) {
    const res = await fetchAPI('mark_notification_read', 'POST', { id: id });
    if (res.status === 'success') {
        updateNotificationIndicator();
    }
}

async function markAllNotificationsRead(e) {
    if (e) e.preventDefault();
    const res = await fetchAPI('mark_all_notifications_read', 'POST');
    if (res.status === 'success') {
        updateNotificationIndicator();
        // Close dropdown if Bootstrap is available
        const dropdownEl = document.getElementById('notificationDropdown');
        if (dropdownEl && window.bootstrap) {
            const dropdown = bootstrap.Dropdown.getInstance(dropdownEl);
            if (dropdown) dropdown.hide();
        }
    }
}

async function deleteNotification(id) {
    if (confirm('Delete this notification?')) {
        const res = await fetchAPI('delete_notification', 'POST', { id: id });
        if (res.status === 'success') {
            updateNotificationIndicator();
        }
    }
}

// Utility: Show Toast Notification
function showToast(title, message, type = 'info') {
    let toastContainer = document.getElementById('toastContainer');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.id = 'toastContainer';
        toastContainer.className = 'toast-container';
        document.body.appendChild(toastContainer);
    }
    
    const toast = document.createElement('div');
    toast.className = `custom-toast ${type}`;
    
    const icons = {
        success: 'fa-check-circle',
        error: 'fa-exclamation-circle',
        warning: 'fa-exclamation-triangle',
        info: 'fa-info-circle'
    };
    
    toast.innerHTML = `
        <div class="toast-icon">
            <i class="fas ${icons[type]}"></i>
        </div>
        <div class="toast-content">
            <div class="toast-title">${title}</div>
            <div class="toast-message">${message}</div>
        </div>
        <button class="toast-close" onclick="this.parentElement.remove()">
            <i class="fas fa-times"></i>
        </button>
    `;
    
    toastContainer.appendChild(toast);
    
    // Auto remove after 5 seconds
    setTimeout(() => {
        if(toast.parentElement) {
            toast.classList.add('fade-out');
            setTimeout(() => toast.remove(), 500);
        }
    }, 5000);
}
