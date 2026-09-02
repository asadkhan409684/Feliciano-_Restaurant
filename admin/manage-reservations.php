<!-- ===== RESERVATIONS MANAGEMENT SECTION ===== -->
<section id="reservations" class="admin-section">
    <div class="section-header">
        <div><h2><i class="fas fa-calendar-alt me-2"></i>Reservation Management</h2><p>Accept, reject and manage table reservations</p></div>
        <div class="d-flex gap-2 flex-wrap">
            <input type="date" id="reservationDate" class="form-control form-control-sm" onchange="filterReservations()" style="width:150px">
            <select id="reservationStatus" class="form-select form-select-sm" onchange="filterReservations()" style="width:150px">
                <option value="all">All Status</option>
                <option value="pending">Pending</option>
                <option value="confirmed">Confirmed</option>
                <option value="completed">Completed</option>
                <option value="cancelled">Cancelled</option>
            </select>
            <!-- Export buttons -->
            <div class="d-flex gap-1">
                <button class="rpt-export-btn rpt-btn-csv" onclick="quickExport('reservations','csv')" title="Export CSV">
                    <i class="fas fa-file-csv"></i> CSV
                </button>
                <button class="rpt-export-btn rpt-btn-print" onclick="quickExport('reservations','print')" title="Print/PDF">
                    <i class="fas fa-print"></i> PDF
                </button>
            </div>
        </div>
    </div>

    <!-- Reservation Stats -->
    <div class="stats-grid mb-4" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr))">
        <div class="stat-card" style="border-left:4px solid #f39c12">
            <div class="stat-icon" style="background:#f39c12"><i class="fas fa-hourglass-half"></i></div>
            <div class="stat-info"><h3 id="resvPending">0</h3><p>Pending</p></div>
        </div>
        <div class="stat-card" style="border-left:4px solid #27ae60">
            <div class="stat-icon" style="background:#27ae60"><i class="fas fa-check-circle"></i></div>
            <div class="stat-info"><h3 id="resvConfirmed">0</h3><p>Confirmed</p></div>
        </div>
        <div class="stat-card" style="border-left:4px solid #e74c3c">
            <div class="stat-icon" style="background:#e74c3c"><i class="fas fa-times-circle"></i></div>
            <div class="stat-info"><h3 id="resvCancelled">0</h3><p>Cancelled</p></div>
        </div>
        <div class="stat-card" style="border-left:4px solid #3498db">
            <div class="stat-icon" style="background:#3498db"><i class="fas fa-calendar-check"></i></div>
            <div class="stat-info"><h3 id="resvTotal">0</h3><p>Total</p></div>
        </div>
    </div>

    <div class="reservations-table-container table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Customer Name</th>
                    <th>Phone</th>
                    <th>Date</th>
                    <th>Time</th>
                    <th>Guests</th>
                    <th>Occasion</th>
                    <th>Status</th>
                    <th style="text-align:center">Actions</th>
                </tr>
            </thead>
            <tbody id="reservationsTableBody">
                <tr><td colspan="9" class="text-center py-4"><i class="fas fa-spinner fa-spin me-2"></i>Loading reservations...</td></tr>
            </tbody>
        </table>
    </div>
</section>

<!-- Reservation Detail Modal -->
<div id="reservationDetailModal" class="modal">
    <div class="modal-content" style="max-width:560px">
        <div class="modal-header">
            <h2>Reservation Details</h2>
            <button class="close-btn" onclick="closeReservationModal()">&times;</button>
        </div>
        <div style="padding:25px">
            <table class="table table-borderless table-sm">
                <tr><td class="fw-bold text-muted" style="width:130px">Reservation ID</td><td><code id="resv_id"></code></td></tr>
                <tr><td class="fw-bold text-muted">Customer</td><td id="resv_name"></td></tr>
                <tr><td class="fw-bold text-muted">Phone</td><td id="resv_phone"></td></tr>
                <tr><td class="fw-bold text-muted">Email</td><td id="resv_email"></td></tr>
                <tr><td class="fw-bold text-muted">Date & Time</td><td id="resv_datetime"></td></tr>
                <tr><td class="fw-bold text-muted">Guests</td><td id="resv_guests"></td></tr>
                <tr><td class="fw-bold text-muted">Occasion</td><td id="resv_occasion"></td></tr>
                <tr><td class="fw-bold text-muted">Special Request</td><td id="resv_request"></td></tr>
                <tr><td class="fw-bold text-muted">Status</td><td id="resv_status"></td></tr>
            </table>
            <!-- Assign Table -->
            <div class="form-group mt-2">
                <label class="fw-bold text-muted" style="font-size:.85rem">Assign Table</label>
                <div class="d-flex gap-2">
                    <select id="resvTableAssign" class="form-select form-select-sm">
                        <option value="">-- Select Table --</option>
                    </select>
                    <button class="btn btn-sm btn-primary" onclick="assignTableToReservation()"><i class="fas fa-chair me-1"></i>Assign</button>
                </div>
            </div>
            <div class="d-flex gap-2 flex-wrap mt-3 border-top pt-3">
                <button class="btn btn-success btn-sm" onclick="updateReservationFromModal('confirmed')"><i class="fas fa-check me-1"></i>Confirm</button>
                <button class="btn btn-danger btn-sm" onclick="updateReservationFromModal('cancelled')"><i class="fas fa-times me-1"></i>Cancel</button>
                <button class="btn btn-secondary btn-sm" onclick="updateReservationFromModal('completed')"><i class="fas fa-flag-checkered me-1"></i>Completed</button>
                <button class="btn btn-outline-secondary btn-sm ms-auto" onclick="closeReservationModal()">Close</button>
            </div>
        </div>
    </div>
</div>
