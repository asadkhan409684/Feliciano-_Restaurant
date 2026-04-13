<?php
require_once 'auth_check.php';
require_once '../config/database.php';

$message = '';

// Handle Order Status Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'], $_POST['order_id'], $_POST['status'])) {
    $order_id = (int)$_POST['order_id'];
    $status = $_POST['status'];
    
    $allowed_statuses = ['pending', 'preparing', 'ready', 'completed', 'cancelled'];
    
    if (in_array($status, $allowed_statuses)) {
        $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $order_id);
        if ($stmt->execute()) {
            $message = "<div class='alert alert-success' style='margin: 20px; padding: 15px; border-radius: 5px; color: #155724; background-color: #d4edda; border-color: #c3e6cb;'>Order status updated to <strong>$status</strong>!</div>";
        } else {
            $message = "<div class='alert alert-danger' style='margin: 20px; padding: 15px; border-radius: 5px; color: #721c24; background-color: #f8d7da; border-color: #f5c6cb;'>Error: " . $conn->error . "</div>";
        }
    }
}

// Fetch all orders with all relevant data
// Managers should only see orders from their branch
$branch_id = $_SESSION['user_branch_id'] ?? null;
$role = $_SESSION['user_role'] ?? '';

if ($role === 'manager' && !empty($branch_id)) {
    $stmt = $conn->prepare("SELECT * FROM orders WHERE branch_id = ? ORDER BY created_at DESC LIMIT 50");
    $stmt->bind_param("i", $branch_id);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // Admins or managers without branch (though they should have one) see all
    $sql = "SELECT * FROM orders ORDER BY created_at DESC LIMIT 50";
    $result = $conn->query($sql);
}

include 'header.php';
?>

<section class="admin-section active">
    <div class="section-header">
        <h2>Order Management</h2>
    </div>

    <?= $message ?>

    <div class="orders-table-container team-table-container" style="background: white; border-radius: 10px; overflow-x: auto; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">
        <table class="admin-table" style="width: 100%; border-collapse: collapse;">
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Customer Info</th>
                    <th>Order Type</th>
                    <th>Date & Time</th>
                    <th>Total</th>
                    <th>Status</th>
                    <th style="width: 80px; text-align: center;">Details</th>
                    <th style="width: 200px; text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($order = $result->fetch_assoc()): 
                        // Fetch order items to embed into JSON (Using Prepared Statement)
                        $db_id = $order['id'];
                        $items_stmt = $conn->prepare("SELECT menu_item_name, quantity, unit_price FROM order_items WHERE order_id = ?");
                        $items_stmt->bind_param("i", $db_id);
                        $items_stmt->execute();
                        $items_res = $items_stmt->get_result();
                        
                        $items = [];
                        if ($items_res && $items_res->num_rows > 0) {
                            while ($item = $items_res->fetch_assoc()) {
                                $items[] = $item;
                            }
                        }
                        $order['items'] = $items;
                        $status = $order['status'];
                        $type = $order['order_type'] ?? 'online';
                    ?>
                        <tr>
                            <td><strong>#<?= htmlspecialchars($order['order_id']) ?></strong></td>
                            <td>
                                <div><strong><?= htmlspecialchars($order['customer_name'] ?: 'Guest') ?></strong></div>
                                <div style="font-size: 0.85rem; color: #7f8c8d;"><?= htmlspecialchars($order['customer_email']) ?></div>
                                <div style="font-size: 0.85rem; color: #7f8c8d;"><i class="fas fa-phone-alt" style="font-size: 0.75rem;"></i> <?= htmlspecialchars($order['customer_phone'] ?: 'No Phone') ?></div>
                            </td>
                            <td>
                                <span class="badge" style="background: <?= ($type === 'online') ? '#3498db' : '#e67e22' ?>; color: white; padding: 4px 10px; border-radius: 4px; font-size: 0.75rem; text-transform: uppercase;">
                                    <i class="fas <?= ($type === 'online') ? 'fa-truck' : 'fa-store' ?> me-1"></i> <?= $type ?>
                                </span>
                            </td>
                            <td>
                                <span style="font-size: 0.9em; color: #7f8c8d;"><?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></span>
                            </td>
                            <td><strong>TK <?= number_format($order['total_amount'], 2) ?></strong></td>
                            <td>
                                <span class="status-badge status-<?= htmlspecialchars($status) ?>"><?= htmlspecialchars($status) ?></span>
                            </td>
                            <td style="width: 80px; text-align: center;">
                                <button type="button" class="action-btn" style="background:#3498db; color:white; border:none; padding: 5px 10px; border-radius: 4px; cursor:pointer;" title="View Details" onclick='viewOrderDetails(<?= htmlspecialchars(json_encode($order), ENT_QUOTES, "UTF-8") ?>)'>
                                    <i class="fas fa-eye"></i>
                                </button>
                            </td>
                            <td style="width: 200px; white-space: nowrap; text-align: center;">
                                <?php if ($status !== 'cancelled' && $status !== 'completed'): ?>
                                <form method="POST" class="d-inline" style="display: flex; gap: 8px; margin: 0; align-items: center; justify-content: center;">
                                    <input type="hidden" name="update_status" value="1">
                                    <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                    
                                    <?php if ($status === 'pending'): ?>
                                        <button type="submit" name="status" value="preparing" class="action-btn" style="background:#f39c12; color:white; border:none; padding: 5px 10px; border-radius: 4px; cursor:pointer;" title="Prepare" onclick="return confirm('Start preparing this order?');"><i class="fas fa-fire"></i></button>
                                    <?php endif; ?>
                                    
                                    <?php if ($status === 'preparing'): ?>
                                        <button type="submit" name="status" value="ready" class="action-btn" style="background:#9b59b6; color:white; border:none; padding: 5px 10px; border-radius: 4px; cursor:pointer;" title="Ready" onclick="return confirm('Mark order as ready?');"><i class="fas fa-bell"></i></button>
                                    <?php endif; ?>
                                    
                                    <?php if ($status === 'ready' || $status === 'preparing' || $status === 'pending'): ?>
                                        <button type="submit" name="status" value="completed" class="action-btn" style="background:#2ecc71; color:white; border:none; padding: 5px 10px; border-radius: 4px; cursor:pointer;" title="Complete" onclick="return confirm('Complete this order?');"><i class="fas fa-check"></i></button>
                                    <?php endif; ?>
                                    
                                    <button type="submit" name="status" value="cancelled" class="action-btn delete-btn" style="background:#e74c3c; color:white; border:none; padding: 5px 10px; border-radius: 4px; cursor:pointer;" title="Cancel" onclick="return confirm('Cancel this order?');"><i class="fas fa-times"></i></button>
                                </form>
                                <?php else: ?>
                                    <span style="color: #95a5a6; font-size: 0.85rem;"><i class="fas fa-lock" style="margin-right: 5px;"></i> Action Taken</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="7" style="text-align: center; padding: 40px; color: #7f8c8d;">No Orders Found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<style>
/* Custom Elegant Modal Styles */
.elegant-modal-content {
    background: #1a1a1a;
    border: 1px solid #c9a74d;
    border-radius: 12px;
    color: #e0e0e0;
    box-shadow: 0 15px 40px rgba(0,0,0,0.5);
    overflow: hidden;
}
.elegant-modal-header {
    background: linear-gradient(135deg, #111 0%, #1a1a1a 100%);
    border-bottom: 1px solid rgba(201, 167, 77, 0.3);
    padding: 20px 25px;
}
.elegant-modal-title {
    color: #c9a74d;
    font-size: 1.4rem;
    font-family: 'Playfair Display', serif;
    letter-spacing: 1px;
    margin: 0;
    display: flex;
    align-items: center;
}
.elegant-modal-body {
    padding: 25px;
    background: #1a1a1a;
}
.detail-row {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px dashed rgba(255,255,255,0.1);
}
.detail-row:last-child {
    border-bottom: none;
}
.detail-label {
    color: #888;
    font-size: 0.95rem;
}
.detail-value {
    color: #fff;
    font-weight: 500;
    text-align: right;
    max-width: 60%;
    word-break: break-word;
}
.elegant-modal-footer {
    border-top: 1px solid rgba(201, 167, 77, 0.3);
    padding: 20px 25px;
    background: #111;
    display: flex;
    justify-content: flex-end;
}
.elegant-btn-close {
    background: #c9a74d;
    color: #111;
    border: none;
    padding: 10px 30px;
    border-radius: 6px;
    font-weight: bold;
    font-size: 1rem;
    cursor: pointer;
    transition: all 0.3s;
    line-height: normal;
    margin: 0;
}
.elegant-btn-close:hover {
    background: #b59545;
}
.order-items-list {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px;
    padding: 15px;
    margin-top: 15px;
}
.order-item-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 10px;
    color: #ccc;
    font-size: 0.95rem;
}
.order-item-row:last-child {
    margin-bottom: 0;
}
.order-total-row {
    display: flex;
    justify-content: space-between;
    margin-top: 15px;
    padding-top: 15px;
    border-top: 1px solid rgba(201, 167, 77, 0.3);
    font-weight: bold;
    color: #2ecc71;
    font-size: 1.1rem;
}
</style>

<!-- Order Details Modal -->
<div class="modal fade" id="orderModal" tabindex="-1" aria-labelledby="orderModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
    <div class="modal-content elegant-modal-content">
      <div class="modal-header elegant-modal-header">
        <h5 class="modal-title elegant-modal-title" id="orderModalLabel">
            <i class="fas fa-shopping-bag me-3" style="color: #c9a74d;"></i> Order Details
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="filter: invert(1) grayscale(100%) brightness(200%);"></button>
      </div>
      <div class="modal-body elegant-modal-body">
        
        <div class="detail-row">
            <span class="detail-label">Order Reference</span>
            <span class="detail-value" id="modalOrderId"></span>
        </div>
        
        <div class="detail-row">
            <span class="detail-label">Customer Info</span>
            <span class="detail-value">
                <div id="modalOrderName" style="font-weight: bold; color: #c9a74d;"></div>
                <div id="modalOrderEmail" style="font-size: 0.9rem;"></div>
                <div id="modalOrderPhone" style="font-size: 0.9rem; color: #bbb;"></div>
            </span>
        </div>
        
        <div class="detail-row">
            <span class="detail-label">Order Type</span>
            <span id="modalOrderTypeBadge"></span>
        </div>

        <div id="deliveryInfoContainer">
            <div class="detail-row">
                <span class="detail-label">Delivery Address</span>
                <span class="detail-value text-info" id="modalOrderAddress"></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Requested Time</span>
                <span class="detail-value text-warning" id="modalOrderTime"></span>
            </div>
        </div>

        <div id="tableInfoContainer">
            <div class="detail-row">
                <span class="detail-label">Table Number</span>
                <span class="detail-value" style="font-size: 1.2rem; color: #f39c12;" id="modalOrderTable"></span>
            </div>
        </div>
        
        <div class="detail-row">
            <span class="detail-label">Current Status</span>
            <span class="detail-value" id="modalOrderStatus" style="text-transform: capitalize; font-weight: bold;"></span>
        </div>

        <div id="instructionsContainer" style="margin-top: 15px; padding: 12px; background: rgba(231, 76, 60, 0.1); border-left: 3px solid #e74c3c; border-radius: 4px;">
            <div style="font-size: 0.8rem; color: #e74c3c; text-transform: uppercase; font-weight: bold; margin-bottom: 5px;">Special Instructions</div>
            <div id="modalOrderInstructions" style="font-style: italic; color: #eee;"></div>
        </div>
        
        <div class="order-items-list">
            <div style="color: #888; margin-bottom: 10px; font-weight: bold; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 5px;">Ordered Items</div>
            <div id="modalOrderItems"></div>
            
            <div class="order-total-row" style="margin-top: 15px; padding-top: 15px; display: flex; justify-content: space-between;">
                <span>Grand Total</span>
                <span id="modalOrderTotal"></span>
            </div>
        </div>
        
      </div>
      <div class="modal-footer elegant-modal-footer">
        <button type="button" class="elegant-btn-close" data-bs-dismiss="modal">Close Window</button>
      </div>
    </div>
  </div>
</div>

<script>
function viewOrderDetails(data) {
    document.getElementById('modalOrderId').textContent = '#' + data.order_id;
    document.getElementById('modalOrderName').textContent = data.customer_name || 'Guest';
    document.getElementById('modalOrderEmail').textContent = data.customer_email || 'No Email';
    document.getElementById('modalOrderPhone').textContent = data.customer_phone || 'No Phone';
    
    const type = data.order_type || 'online';
    const typeBadge = document.getElementById('modalOrderTypeBadge');
    if (type === 'online') {
        typeBadge.innerHTML = '<span class="badge bg-primary"><i class="fas fa-truck me-1"></i> ONLINE</span>';
        document.getElementById('deliveryInfoContainer').style.display = 'block';
        document.getElementById('tableInfoContainer').style.display = 'none';
        document.getElementById('modalOrderAddress').textContent = data.delivery_address || 'Not Provided';
        document.getElementById('modalOrderTime').textContent = data.delivery_time || data.created_at;
    } else {
        typeBadge.innerHTML = '<span class="badge bg-warning text-dark"><i class="fas fa-store me-1"></i> OFFLINE</span>';
        document.getElementById('deliveryInfoContainer').style.display = 'none';
        document.getElementById('tableInfoContainer').style.display = 'block';
        document.getElementById('modalOrderTable').textContent = data.table_number || 'N/A';
    }
    
    document.getElementById('modalOrderStatus').textContent = data.status || 'Pending';
    document.getElementById('modalOrderTotal').textContent = 'TK ' + parseFloat(data.total_amount).toFixed(2);
    
    // Handle Instructions
    const instContainer = document.getElementById('instructionsContainer');
    if (data.special_instructions && data.special_instructions.trim() !== '') {
        instContainer.style.display = 'block';
        document.getElementById('modalOrderInstructions').textContent = data.special_instructions;
    } else {
        instContainer.style.display = 'none';
    }
    
    const itemsContainer = document.getElementById('modalOrderItems');
    itemsContainer.innerHTML = ''; 
    
    if (data.items && data.items.length > 0) {
        data.items.forEach(item => {
            const row = document.createElement('div');
            row.className = 'order-item-row';
            const price = parseFloat(item.unit_price) * parseInt(item.quantity);
            row.innerHTML = `<span><strong style="color: #fff;">${item.quantity}x</strong> ${item.menu_item_name}</span><span>TK ${price.toFixed(2)}</span>`;
            itemsContainer.appendChild(row);
        });
    } else {
        itemsContainer.innerHTML = '<span style="color:#7f8c8d;">No items recorded in this order.</span>';
    }
    
    var ordModal = new bootstrap.Modal(document.getElementById('orderModal'));
    ordModal.show();
}
</script>

<?php include 'footer.php'; ?>
