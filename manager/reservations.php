<?php
require_once 'auth_check.php';
require_once '../config/database.php';

// Handle Action
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['reservation_id'])) {
    $res_id = (int)$_POST['reservation_id'];
    $action = $_POST['action'];
    
    if (in_array($action, ['confirmed', 'cancelled'])) {
        $stmt = $conn->prepare("UPDATE reservations SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $action, $res_id);
        if ($stmt->execute()) {
            $message = "<div class='alert alert-success' style='margin: 20px; padding: 15px; border-radius: 5px; color: #155724; background-color: #d4edda; border-color: #c3e6cb;'>Reservation status updated to <strong>$action</strong>!</div>";
        } else {
            $message = "<div class='alert alert-danger' style='margin: 20px; padding: 15px; border-radius: 5px; color: #721c24; background-color: #f8d7da; border-color: #f5c6cb;'>Error updating status: " . $conn->error . "</div>";
        }
    }
}

// Fetch all reservations ordered by date
// Managers should only see reservations from their branch
$branch_id = $_SESSION['user_branch_id'] ?? null;
$role = $_SESSION['user_role'] ?? '';

if ($role === 'manager' && !empty($branch_id)) {
    $stmt = $conn->prepare("SELECT * FROM reservations WHERE branch_id = ? ORDER BY reservation_date DESC, reservation_time DESC");
    $stmt->bind_param("i", $branch_id);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // Admins see all
    $sql = "SELECT * FROM reservations ORDER BY reservation_date DESC, reservation_time DESC";
    $result = $conn->query($sql);
}

include 'header.php';
?>

<section class="admin-section active">
    <div class="section-header">
        <h2>Manage Reservations</h2>
    </div>

    <?= $message ?>

    <div class="orders-table-container team-table-container">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Date & Time</th>
                    <th>Customer</th>
                    <th>Phone</th>
                    <th>Guests</th>
                    <th>Status</th>
                    <th style="width: 80px; text-align: center;">Details</th>
                    <th style="width: 150px; text-align: center;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($row['reservation_date']) ?></strong><br>
                                <span style="font-size: 0.85em; color: #7f8c8d;"><?= htmlspecialchars($row['reservation_time']) ?></span>
                            </td>
                            <td><?= htmlspecialchars($row['customer_name'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['customer_phone'] ?? 'N/A') ?></td>
                            <td><?= htmlspecialchars($row['guests_count']) ?> Persons</td>
                            <td>
                                <?php 
                                    $status = $row['status'] ?? 'pending';
                                ?>
                                <span class="status-badge status-<?= htmlspecialchars($status) ?>"><?= htmlspecialchars($status) ?></span>
                            </td>
                            <td style="width: 80px; text-align: center;">
                                <button type="button" class="action-btn" style="background:#3498db; color:white; border:none; padding: 5px 10px; border-radius: 4px; cursor:pointer;" title="View Details" onclick='viewDetails(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, "UTF-8") ?>)'>
                                    <i class="fas fa-eye"></i>
                                </button>
                            </td>
                            <td style="width: 150px; text-align: center; white-space: nowrap;">
                                <?php if ($status === 'pending'): ?>
                                <form method="POST" class="d-inline" style="display: flex; gap: 8px; margin: 0; align-items: center; justify-content: center;">
                                    <input type="hidden" name="reservation_id" value="<?= $row['id'] ?>">
                                    <button type="submit" name="action" value="confirmed" class="action-btn" style="background:#2ecc71; color:white; border:none; padding: 5px 10px; border-radius: 4px; cursor:pointer;" title="Approve" onclick="return confirm('Are you sure you want to approve this reservation?');">
                                        <i class="fas fa-check"></i>
                                    </button>
                                    <button type="submit" name="action" value="cancelled" class="action-btn delete-btn" style="background:#e74c3c; color:white; border:none; padding: 5px 10px; border-radius: 4px; cursor:pointer;" title="Cancel" onclick="return confirm('Are you sure you want to cancel this reservation?');">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </form>
                                <?php else: ?>
                                    <span style="color: #95a5a6; font-size: 0.85rem;"><i class="fas fa-lock" style="margin-right: 5px;"></i><span>Action Taken</span></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align: center; padding: 40px; color: #7f8c8d;">No reservations found.</td></tr>
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
.special-requests-box {
    background: rgba(255,255,255,0.03);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 8px;
    padding: 15px;
    margin-top: 10px;
    color: #ccc;
    font-size: 0.95rem;
    line-height: 1.5;
}
.elegant-modal-footer {
    border-top: 1px solid rgba(201, 167, 77, 0.3);
    padding: 10px 20px;
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
</style>

<!-- Reservation Details Modal -->
<div class="modal fade" id="reservationModal" tabindex="-1" aria-labelledby="reservationModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">
    <div class="modal-content elegant-modal-content">
      <div class="modal-header elegant-modal-header">
        <h5 class="modal-title elegant-modal-title" id="reservationModalLabel">
            <i class="fas fa-calendar-check me-3" style="color: #c9a74d;"></i> Reservation Details
        </h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" style="filter: invert(1) grayscale(100%) brightness(200%);"></button>
      </div>
      <div class="modal-body elegant-modal-body">
        
        <div class="detail-row">
            <span class="detail-label">Reservation ID</span>
            <span class="detail-value" id="modalResId"></span>
        </div>
        
        <div class="detail-row">
            <span class="detail-label">Customer Name</span>
            <span class="detail-value" id="modalName"></span>
        </div>
        
        <div class="detail-row">
            <span class="detail-label">Phone Number</span>
            <span class="detail-value" id="modalPhone"></span>
        </div>
        
        <div class="detail-row">
            <span class="detail-label">Email Address</span>
            <span class="detail-value" id="modalEmail"></span>
        </div>
        
        <div class="detail-row">
            <span class="detail-label">Date & Time</span>
            <span class="detail-value">
                <span id="modalDate" style="color: #fff;"></span> <br> 
                <span id="modalTime" style="color: #c9a74d;"></span>
            </span>
        </div>
        
        <div class="detail-row">
            <span class="detail-label">Guests Count</span>
            <span class="detail-value" id="modalGuests"></span>
        </div>
        
        <div class="detail-row">
            <span class="detail-label">Occasion</span>
            <span class="detail-value text-capitalize" id="modalOccasion"></span>
        </div>
        
        <div style="margin-top: 25px;">
            <span class="detail-label" style="display: block; margin-bottom: 8px;"><i class="fas fa-comment-alt" style="margin-right: 8px; color: #888;"></i>Special Requests</span>
            <div class="special-requests-box" id="modalRequests"></div>
        </div>
        
      </div>
      <div class="modal-footer elegant-modal-footer">
        <button type="button" class="elegant-btn-close" data-bs-dismiss="modal">Close Window</button>
      </div>
    </div>
  </div>
</div>

<script>
function viewDetails(data) {
    document.getElementById('modalResId').textContent = data.reservation_id || 'N/A';
    document.getElementById('modalName').textContent = data.customer_name || 'N/A';
    document.getElementById('modalPhone').textContent = data.customer_phone || 'N/A';
    document.getElementById('modalEmail').textContent = data.customer_email || 'N/A';
    document.getElementById('modalDate').textContent = data.reservation_date || 'N/A';
    document.getElementById('modalTime').textContent = data.reservation_time || 'N/A';
    document.getElementById('modalGuests').textContent = (data.guests_count || '0') + ' Persons';
    
    let occasion = data.occasion || 'None';
    if(occasion === 'other') occasion = 'N/A';
    document.getElementById('modalOccasion').textContent = occasion;
    
    document.getElementById('modalRequests').textContent = data.special_requests || 'No special requests provided.';
    
    var resModal = new bootstrap.Modal(document.getElementById('reservationModal'));
    resModal.show();
}
</script>

<?php include 'footer.php'; ?>
