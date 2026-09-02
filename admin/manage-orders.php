<!-- ===== ORDERS MANAGEMENT SECTION ===== -->
<section id="orders" class="admin-section">
    <div class="section-header">
        <div><h2><i class="fas fa-shopping-cart me-2"></i>Order Management</h2><p>Track and manage all restaurant orders</p></div>
        <div class="d-flex gap-2 flex-wrap align-items-center">
            <select id="orderDateFilter" class="form-select form-select-sm" onchange="filterOrders()" style="width:130px">
                <option value="all">All Time</option>
                <option value="today">Today</option>
                <option value="7days">Last 7 Days</option>
                <option value="30days">Last 30 Days</option>
            </select>
            <select id="orderStatusFilter" class="form-select form-select-sm" onchange="filterOrders()" style="width:150px">
                <option value="all">All Status</option>
                <option value="pending">Pending</option>
                <option value="confirmed">Confirmed</option>
                <option value="preparing">Preparing</option>
                <option value="ready">Ready</option>
                <option value="out_for_delivery">Out for Delivery</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
                <option value="refunded">Refunded</option>
            </select>
            <select id="orderTypeFilter" class="form-select form-select-sm" onchange="filterOrders()" style="width:130px">
                <option value="all">All Types</option>
                <option value="online">Online</option>
                <option value="offline">Offline</option>
                <option value="walk-in">Walk-in</option>
            </select>
            <!-- Export buttons -->
            <div class="d-flex gap-1 ms-1">
                <button class="rpt-export-btn rpt-btn-csv" onclick="quickExport('orders','csv')" title="Export as CSV">
                    <i class="fas fa-file-csv"></i> CSV
                </button>
                <button class="rpt-export-btn rpt-btn-print" onclick="quickExport('orders','print')" title="Print / PDF">
                    <i class="fas fa-print"></i> PDF
                </button>
            </div>
        </div>
    </div>

    <!-- Order Quick Stats -->
    <div id="orderAnalyticsGrid" class="stats-grid mb-4" style="grid-template-columns:repeat(auto-fit,minmax(200px,1fr))"></div>

    <div class="orders-table-container table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Customer Info</th>
                    <th>Type</th>
                    <th>Items</th>
                    <th>Date & Time</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th style="text-align:center">Action</th>
                </tr>
            </thead>
            <tbody id="ordersTableBody">
                <tr><td colspan="8" class="text-center py-4"><i class="fas fa-spinner fa-spin me-2"></i>Loading orders...</td></tr>
            </tbody>
        </table>
    </div>
</section>

<!-- Order Detail Modal -->
<div id="editOrderModal" class="modal">
    <div class="modal-content" style="max-width:700px;padding:0">
        <div class="modal-header">
            <h3>Order Details — <span id="editOrderModalTitle"></span></h3>
            <button class="close-modal" onclick="closeEditOrderModal()" style="font-size:1.5rem;background:none;border:none;cursor:pointer;color:#7f8c8d">&times;</button>
        </div>
        <div style="padding:25px">
            <!-- Order Info Row -->
            <div class="row mb-3">
                <div class="col-md-6">
                    <table class="table table-sm table-borderless">
                        <tr><td class="fw-bold text-muted" style="width:110px">Order ID</td><td><code id="viewOrderId"></code></td></tr>
                        <tr><td class="fw-bold text-muted">Date</td><td id="viewOrderDate"></td></tr>
                        <tr><td class="fw-bold text-muted">Customer</td><td id="viewOrderCustomer"></td></tr>
                        <tr><td class="fw-bold text-muted">Phone</td><td id="viewOrderPhone"></td></tr>
                        <tr><td class="fw-bold text-muted">Type</td><td id="viewOrderType"></td></tr>
                    </table>
                </div>
                <div class="col-md-6 text-end">
                    <div class="mb-2"><strong>Current Status</strong></div>
                    <span id="viewOrderStatus" class="order-status-pill"></span>
                    <div class="mt-3">
                        <label class="fw-bold text-muted d-block mb-1">Update Status</label>
                        <select id="orderStatusUpdate" class="form-select form-select-sm" style="width:180px;display:inline-block">
                            <option value="pending">Pending</option>
                            <option value="confirmed">Confirmed</option>
                            <option value="preparing">Preparing</option>
                            <option value="ready">Ready</option>
                            <option value="out_for_delivery">Out for Delivery</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                            <option value="refunded">Refunded</option>
                        </select>
                        <button class="btn btn-primary btn-sm ms-2" onclick="saveOrderStatusFromModal()"><i class="fas fa-save"></i></button>
                    </div>
                </div>
            </div>
            <!-- Order Items -->
            <h5 class="border-start border-4 ps-2 mb-3" style="border-color:#3498db!important">Order Items</h5>
            <div id="viewOrderItems" class="bg-light p-3 rounded mb-3" style="border:1px solid #e2e8f0"></div>
            <div class="text-end fw-bold fs-5">Grand Total: TK <span id="viewOrderTotal"></span></div>
            <!-- Delivery Address -->
            <div id="viewDeliveryAddressRow" class="mt-3 p-3 bg-light rounded" style="display:none">
                <i class="fas fa-map-marker-alt text-danger me-2"></i>
                <strong>Delivery Address:</strong> <span id="viewDeliveryAddress"></span>
            </div>
            <!-- Special Instructions -->
            <div id="viewSpecialInstructionsRow" class="mt-2 p-3 bg-light rounded" style="display:none">
                <i class="fas fa-sticky-note text-warning me-2"></i>
                <strong>Special Instructions:</strong> <span id="viewSpecialInstructions"></span>
            </div>
            <div class="d-flex justify-content-between mt-4 border-top pt-3">
                <button class="btn btn-danger btn-sm" onclick="deleteOrderFromModal()"><i class="fas fa-trash me-1"></i>Delete Order</button>
                <div class="d-flex gap-2">
                    <button class="btn btn-info btn-sm text-white" onclick="openInvoiceModal()"><i class="fas fa-file-invoice me-1"></i>Invoice</button>
                    <button class="btn btn-secondary" onclick="closeEditOrderModal()">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>
