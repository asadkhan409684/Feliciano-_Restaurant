<!-- Orders Section -->
<section id="orders" class="admin-section">
    <div class="section-header">
        <h2>Orders Management</h2>
        <div class="order-filters" style="display: flex; gap: 10px;">
            <select id="orderDateFilter" onchange="filterOrders()">
                <option value="all">All Time</option>
                <option value="today">Today</option>
                <option value="7days">Last 7 Days</option>
                <option value="30days">Last 30 Days</option>
            </select>
            <select id="orderStatusFilter" onchange="filterOrders()">
                <option value="all">All Orders</option>
                <option value="pending">Pending</option>
                <option value="preparing">Preparing</option>
                <option value="ready">Ready</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
            </select>
        </div>
    </div>
    
    <div id="orderAnalyticsGrid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
        <!-- Analytics cards will be populated here via admin-script.js -->
    </div>
    
    <div class="orders-table-container">
        <table class="admin-table">
            <thead>
                <tr>
                    <th style="width: 150px;">Order ID</th>
                    <th>Customer Info</th>
                    <th style="width: 150px; text-align: center;">Order Type</th>
                    <th style="width: 180px;">Date & Time</th>
                    <th style="width: 120px;">Total</th>
                    <th style="width: 140px; text-align: center;">Status</th>
                    <th style="width: 100px; text-align: center;">Details</th>
                </tr>
            </thead>
            <tbody id="ordersTableBody">
                <!-- Orders will be populated here via admin-script.js -->
            </tbody>
        </table>
    </div>
</section>

<!-- View Order Modal -->
<div id="editOrderModal" class="modal">
    <div class="modal-content" style="max-width: 650px; border-radius: 12px; overflow: hidden; padding: 0;">
        <div class="modal-header" style="background-color: #f8f9fa; border-bottom: 1px solid #e0e6ed; padding: 20px 30px;">
            <h3 id="editOrderModalTitle" style="margin: 0; color: #2c3e50; font-size: 1.25rem;">View Order Details</h3>
            <button class="close-modal" onclick="closeEditOrderModal()" style="font-size: 1.5rem; color: #7f8c8d; background: transparent; border: none; cursor: pointer;">&times;</button>
        </div>
        <div style="padding: 30px;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 25px; border-bottom: 2px solid #f1f5f9; padding-bottom: 20px;">
                <div style="font-size: 1rem; line-height: 1.8; color: #475569;">
                    <div style="margin-bottom: 5px;"><strong style="color: #1e293b; display: inline-block; width: 90px;">Order ID:</strong> <span id="viewOrderId" style="font-family: monospace; background: #f1f5f9; padding: 2px 6px; border-radius: 4px;"></span></div>
                    <div style="margin-bottom: 5px;"><strong style="color: #1e293b; display: inline-block; width: 90px;">Date:</strong> <span id="viewOrderDate"></span></div>
                    <div><strong style="color: #1e293b; display: inline-block; width: 90px;">Customer:</strong> <span id="viewOrderCustomer"></span></div>
                </div>
                <div style="text-align: right; display: flex; flex-direction: column; justify-content: center;">
                    <strong style="color: #1e293b; margin-bottom: 8px; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.5px;">Current Status</strong>
                    <span id="viewOrderStatus" class="order-status" style="display: inline-block; padding: 6px 12px; font-size: 0.95rem; border-radius: 20px;"></span>
                </div>
            </div>
            
            <h4 style="margin-bottom: 15px; font-size: 1.15rem; color: #1e293b; border-left: 4px solid #3498db; padding-left: 10px;">Order Items</h4>
            <div id="viewOrderItems" style="background: #f8fafc; padding: 20px; border-radius: 8px; margin-bottom: 25px; border: 1px solid #e2e8f0; font-size: 1rem;">
                <!-- Items will be injected here via JS -->
            </div>
            
            <div style="text-align: right; font-size: 1.3rem; margin-bottom: 30px; color: #0f172a; background: #f1f5f9; padding: 15px 20px; border-radius: 8px; display: inline-block; float: right;">
                Grand Total: <strong style="color: #2c3e50;">TK <span id="viewOrderTotal"></span></strong>
            </div>
            <div style="clear: both;"></div>
            
            <div class="form-actions" style="justify-content: flex-end; margin-top: 10px; border-top: 1px solid #e2e8f0; padding-top: 20px;">
                <button type="button" class="btn btn-secondary" onclick="closeEditOrderModal()" style="padding: 10px 25px; font-weight: 500; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.05); transition: all 0.2s;">Close Window</button>
            </div>
        </div>
    </div>
</div>
