<!-- ===== CUSTOMER MANAGEMENT SECTION ===== -->
<section id="customers" class="admin-section">
    <div class="section-header">
        <div><h2><i class="fas fa-users me-2"></i>Customer Management</h2><p>View and manage registered customers</p></div>
        <div class="d-flex gap-2">
            <input type="text" id="customerSearch" class="form-control form-control-sm" placeholder="Search customers..." onkeyup="filterCustomers()" style="width:220px">
            <button class="btn btn-secondary btn-sm" onclick="exportCustomers()"><i class="fas fa-download me-1"></i>Export</button>
        </div>
    </div>

    <!-- Customer Stats -->
    <div class="stats-grid mb-4" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr))">
        <div class="stat-card" style="border-left:4px solid #3498db">
            <div class="stat-icon" style="background:#3498db"><i class="fas fa-users"></i></div>
            <div class="stat-info"><h3 id="custTotal">0</h3><p>Total Customers</p></div>
        </div>
        <div class="stat-card" style="border-left:4px solid #27ae60">
            <div class="stat-icon" style="background:#27ae60"><i class="fas fa-user-check"></i></div>
            <div class="stat-info"><h3 id="custActive">0</h3><p>Active Customers</p></div>
        </div>
        <div class="stat-card" style="border-left:4px solid #c9a74d">
            <div class="stat-icon" style="background:#c9a74d"><i class="fas fa-shopping-bag"></i></div>
            <div class="stat-info"><h3 id="custWithOrders">0</h3><p>With Orders</p></div>
        </div>
    </div>

    <div class="customers-table-container table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Customer ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Total Orders</th>
                    <th>Total Spent</th>
                    <th>Last Order</th>
                    <th>Status</th>
                    <th style="text-align:center">Actions</th>
                </tr>
            </thead>
            <tbody id="customersTableBody">
                <tr><td colspan="9" class="text-center py-4"><i class="fas fa-spinner fa-spin me-2"></i>Loading customers...</td></tr>
            </tbody>
        </table>
    </div>
</section>

<!-- Customer Detail Modal -->
<div id="customerDetailModal" class="modal">
    <div class="modal-content" style="max-width:560px">
        <div class="modal-header">
            <h2>Customer Profile</h2>
            <button class="close-btn" onclick="closeCustomerModal()">&times;</button>
        </div>
        <div style="padding:25px">
            <div class="text-center mb-4">
                <div style="width:80px;height:80px;border-radius:50%;background:#2c3e50;color:#c9a74d;display:flex;align-items:center;justify-content:center;font-size:2rem;margin:0 auto"><i class="fas fa-user"></i></div>
                <h4 class="mt-2 mb-0" id="custModalName"></h4>
                <span id="custModalStatus" class="status-badge mt-1"></span>
            </div>
            <table class="table table-borderless table-sm">
                <tr><td class="fw-bold text-muted" style="width:120px">Email</td><td id="custModalEmail"></td></tr>
                <tr><td class="fw-bold text-muted">Phone</td><td id="custModalPhone"></td></tr>
                <tr><td class="fw-bold text-muted">Total Orders</td><td id="custModalOrders"></td></tr>
                <tr><td class="fw-bold text-muted">Total Spent</td><td id="custModalSpent"></td></tr>
                <tr><td class="fw-bold text-muted">Last Order</td><td id="custModalLastOrder"></td></tr>
                <tr><td class="fw-bold text-muted">Joined</td><td id="custModalJoined"></td></tr>
                <tr><td class="fw-bold text-muted">Loyalty Points</td><td><span id="custModalLoyalty" class="badge" style="background:#c9a74d;color:#1a1a2e;font-size:.85rem">0 pts</span></td></tr>
            </table>
            <!-- Loyalty Points Management -->
            <div class="analytics-card p-3 mb-3" style="background:#f8fafc">
                <h6 class="mb-2"><i class="fas fa-gift me-2 text-warning"></i>Loyalty Points</h6>
                <div class="d-flex gap-2">
                    <input type="number" id="loyaltyPointsInput" class="form-control form-control-sm" placeholder="Add points" min="1" style="width:120px">
                    <button class="btn btn-warning btn-sm" onclick="addLoyaltyPoints()"><i class="fas fa-plus me-1"></i>Add</button>
                </div>
            </div>
            <!-- Order History Tab -->
            <ul class="nav nav-tabs nav-sm mb-2" id="custModalTabs">
                <li class="nav-item"><a class="nav-link active" href="javascript:void(0)" onclick="showCustTab('orders',this)"><i class="fas fa-shopping-cart me-1"></i>Orders</a></li>
                <li class="nav-item"><a class="nav-link" href="javascript:void(0)" onclick="showCustTab('reservations',this)"><i class="fas fa-calendar me-1"></i>Reservations</a></li>
            </ul>
            <div id="custTabOrders" class="cust-tab-pane" style="max-height:160px;overflow-y:auto">
                <div class="text-muted text-center py-2" style="font-size:.82rem">Loading...</div>
            </div>
            <div id="custTabReservations" class="cust-tab-pane" style="max-height:160px;overflow-y:auto;display:none">
                <div class="text-muted text-center py-2" style="font-size:.82rem">Loading...</div>
            </div>
            <div class="d-flex gap-2 mt-3 border-top pt-3">
                <button class="btn btn-danger btn-sm" id="custBlockBtn" onclick="toggleCustomerBlock()"><i class="fas fa-ban me-1"></i>Block Customer</button>
                <button class="btn btn-secondary ms-auto" onclick="closeCustomerModal()">Close</button>
            </div>
        </div>
    </div>
</div>
