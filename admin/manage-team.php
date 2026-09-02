<!-- ===== STAFF MANAGEMENT SECTION ===== -->
<section id="team" class="admin-section">
    <div class="section-header">
        <div><h2><i class="fas fa-id-badge me-2"></i>Staff Management</h2><p>Manage restaurant staff, roles, performance and attendance</p></div>
        <div class="d-flex gap-2 flex-wrap">
            <select id="teamRoleFilter" class="form-select form-select-sm" onchange="filterTeam()" style="width:160px">
                <option value="all">All Roles</option>
                <option value="admin">Admin</option>
                <option value="manager">Manager</option>
                <option value="chef">Chef</option>
                <option value="waiter">Waiter</option>
                <option value="cashier">Cashier</option>
            </select>
            <select id="teamStatusFilter" class="form-select form-select-sm" onchange="filterTeam()" style="width:130px">
                <option value="all">All Status</option>
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
                <option value="suspended">Suspended</option>
            </select>
            <?php if (($_SESSION['user_role'] ?? '') === 'admin'): ?>
            <button class="btn btn-primary btn-sm" onclick="showAddStaffModal()"><i class="fas fa-plus me-1"></i> Add Staff</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Role Stats Cards -->
    <div class="stats-grid mb-4" id="teamRoleStatsGrid" style="grid-template-columns:repeat(auto-fit,minmax(130px,1fr))">
        <!-- JS দিয়ে populate হবে -->
    </div>

    <!-- Staff Sub-tabs -->
    <ul class="nav nav-tabs mb-3" id="staffTabs">
        <li class="nav-item"><a class="nav-link active" href="javascript:void(0)" onclick="showStaffTab('list',this)"><i class="fas fa-users me-1"></i>Staff List</a></li>
        <li class="nav-item"><a class="nav-link" href="javascript:void(0)" onclick="showStaffTab('performance',this)"><i class="fas fa-chart-line me-1"></i>Performance</a></li>
        <li class="nav-item"><a class="nav-link" href="javascript:void(0)" onclick="showStaffTab('attendance',this)"><i class="fas fa-calendar-check me-1"></i>Attendance</a></li>
        <li class="nav-item"><a class="nav-link" href="javascript:void(0)" onclick="showStaffTab('salary',this)"><i class="fas fa-money-bill-wave me-1"></i>Salary</a></li>
    </ul>

    <!-- ===== Staff List Tab ===== -->
    <div id="staffTabList">
        <div class="team-table-container table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width:50px">ID</th>
                        <th>Name</th>
                        <th>Email / Phone</th>
                        <th>Role</th>
                        <th>Branch</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th style="text-align:center">Actions</th>
                    </tr>
                </thead>
                <tbody id="teamTableBody">
                    <tr><td colspan="8" class="text-center py-4"><i class="fas fa-spinner fa-spin me-2"></i>Loading staff...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ===== Performance Tab ===== -->
    <div id="staffTabPerformance" style="display:none">
        <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
            <div class="d-flex align-items-center gap-2">
                <label class="fw-semibold mb-0">Month:</label>
                <input type="month" id="perfMonth" class="form-control form-control-sm" style="width:160px"
                       value="<?= date('Y-m') ?>" onchange="loadPerformance()">
            </div>
            <select id="perfRoleFilter" class="form-select form-select-sm" style="width:160px" onchange="loadPerformance()">
                <option value="all">All Roles</option>
                <option value="waiter">Waiter</option>
                <option value="cashier">Cashier</option>
                <option value="chef">Chef</option>
            </select>
        </div>

        <!-- Performance Summary Cards -->
        <div class="stats-grid mb-4" id="perfSummaryGrid" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr))"></div>

        <!-- Performance Table -->
        <div class="analytics-card p-0" style="overflow:hidden">
            <div class="d-flex justify-content-between align-items-center p-3" style="border-bottom:1px solid #f1f5f9">
                <span style="font-weight:700;color:#0f172a"><i class="fas fa-chart-bar me-2 text-primary"></i>Monthly Performance Report</span>
                <span id="perfMonth_display" style="font-size:.82rem;color:#64748b"></span>
            </div>
            <div class="table-responsive">
                <table class="admin-table" id="perfTable">
                    <thead>
                        <tr>
                            <th>Staff Member</th>
                            <th>Role</th>
                            <th>Orders Handled</th>
                            <th>Deliveries</th>
                            <th>Attendance %</th>
                            <th>Rating</th>
                            <th style="text-align:center">Actions</th>
                        </tr>
                    </thead>
                    <tbody id="perfTableBody">
                        <tr><td colspan="7" class="text-center py-4 text-muted"><i class="fas fa-spinner fa-spin me-2"></i>Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ===== Attendance Tab ===== -->
    <div id="staffTabAttendance" style="display:none">
        <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
            <div class="d-flex align-items-center gap-2">
                <label class="fw-semibold mb-0">Month:</label>
                <input type="month" id="attendanceMonth" class="form-control form-control-sm" style="width:160px"
                       value="<?= date('Y-m') ?>" onchange="loadAttendance()">
            </div>
            <button class="btn btn-primary btn-sm" onclick="showAttendanceModal()"><i class="fas fa-plus me-1"></i>Mark Attendance</button>
        </div>
        <div class="stats-grid mb-3" id="attendanceStatsGrid" style="grid-template-columns:repeat(auto-fit,minmax(150px,1fr))"></div>
        <div class="table-responsive">
            <table class="admin-table">
                <thead><tr><th>Staff Name</th><th>Role</th><th>Date</th><th>Status</th><th>Check In</th><th>Check Out</th><th>Notes</th></tr></thead>
                <tbody id="attendanceTableBody"><tr><td colspan="7" class="text-center py-4 text-muted">Loading...</td></tr></tbody>
            </table>
        </div>
    </div>

    <!-- ===== Salary Tab ===== -->
    <div id="staffTabSalary" style="display:none">
        <div class="d-flex align-items-center gap-3 mb-3 flex-wrap">
            <div class="d-flex align-items-center gap-2">
                <label class="fw-semibold mb-0">Month:</label>
                <input type="month" id="salaryMonth" class="form-control form-control-sm" style="width:160px"
                       value="<?= date('Y-m') ?>" onchange="loadSalary()">
            </div>
            <button class="btn btn-success btn-sm" onclick="showSalaryModal()"><i class="fas fa-plus me-1"></i>Process Salary</button>
        </div>
        <div class="table-responsive">
            <table class="admin-table">
                <thead><tr><th>Staff Name</th><th>Role</th><th>Base Salary</th><th>Bonus</th><th>Deduction</th><th>Net Salary</th><th>Status</th></tr></thead>
                <tbody id="salaryTableBody"><tr><td colspan="7" class="text-center py-4 text-muted">Loading...</td></tr></tbody>
            </table>
        </div>
    </div>
</section>

<!-- ===== Add / Edit Staff Modal ===== -->
<div id="staffModal" class="modal">
    <div class="modal-content" style="max-width:600px">
        <div class="modal-header">
            <h2 id="staffModalTitle">Add New Staff Member</h2>
            <button class="close-btn" onclick="closeStaffModal()">&times;</button>
        </div>
        <form id="staffForm" style="padding:20px">
            <input type="hidden" id="staffId" name="id">
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group"><label>First Name <span class="text-danger">*</span></label>
                        <input type="text" id="staffFirstName" name="first_name" required placeholder="First name">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group"><label>Last Name <span class="text-danger">*</span></label>
                        <input type="text" id="staffLastName" name="last_name" required placeholder="Last name">
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group"><label>Email <span class="text-danger">*</span></label>
                        <input type="email" id="staffEmail" name="email" required placeholder="email@example.com">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group"><label>Phone</label>
                        <input type="text" id="staffPhone" name="phone" placeholder="+88017XXXXXXXX">
                    </div>
                </div>
            </div>
            <div class="form-group" id="staffPasswordGroup">
                <label>Password <span class="text-danger" id="pwdRequired">*</span></label>
                <input type="password" id="staffPassword" name="password" placeholder="Min. 8 characters">
                <small class="text-muted">Edit mode-এ blank রাখলে পুরানো password থাকবে</small>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group"><label>Role <span class="text-danger">*</span></label>
                        <select id="staffRole" name="role" required onchange="toggleBranchSelection(); updateRoleBadgePreview()">
                            <option value="manager">Manager</option>
                            <option value="chef">Chef</option>
                            <option value="waiter">Waiter</option>
                            <option value="cashier">Cashier</option>
                        </select>
                        <!-- Role badge preview -->
                        <div class="mt-1" id="roleBadgePreview"></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group"><label>Status</label>
                        <select id="staffStatus" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="form-group" id="branchSelectionGroup">
                <label>Assigned Branch</label>
                <select id="staffBranch" name="branch_id">
                    <option value="">No Branch / Main</option>
                </select>
            </div>
            <div class="form-group">
                <label>Join Date</label>
                <input type="date" id="staffJoinDate" name="join_date" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeStaffModal()">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Member</button>
            </div>
        </form>
    </div>
</div>

<!-- ===== Staff Profile Detail Modal ===== -->
<div id="staffProfileModal" class="modal">
    <div class="modal-content" style="max-width:640px">
        <div class="modal-header">
            <h2><i class="fas fa-id-card me-2"></i>Staff Profile</h2>
            <button class="close-btn" onclick="closeStaffProfileModal()">&times;</button>
        </div>
        <div id="staffProfileBody" style="padding:24px">
            <!-- JS দিয়ে populate হবে -->
        </div>
    </div>
</div>

<!-- ===== Performance Rating Modal ===== -->
<div id="perfRatingModal" class="modal">
    <div class="modal-content" style="max-width:420px">
        <div class="modal-header">
            <h2><i class="fas fa-star me-2 text-warning"></i>Rate Staff</h2>
            <button class="close-btn" onclick="document.getElementById('perfRatingModal').classList.remove('active')">&times;</button>
        </div>
        <form id="perfRatingForm" style="padding:20px">
            <input type="hidden" id="ratingUserId" name="user_id">
            <input type="hidden" id="ratingMonth" name="month" value="<?= date('Y-m') ?>">
            <div class="form-group">
                <label class="fw-bold mb-2" id="ratingStaffName"></label>
            </div>
            <div class="form-group">
                <label>Rating (1–5)</label>
                <div class="d-flex gap-2 align-items-center mt-1" id="starRatingInput">
                    <?php for($s=1;$s<=5;$s++): ?>
                    <i class="fas fa-star rating-star" data-val="<?= $s ?>"
                       style="font-size:1.8rem;cursor:pointer;color:#e2e8f0;transition:color .15s"
                       onmouseover="hoverStar(<?= $s ?>)" onmouseout="resetStarHover()"
                       onclick="selectStar(<?= $s ?>)"></i>
                    <?php endfor; ?>
                    <span id="starValueDisplay" class="ms-2 fw-bold text-warning" style="font-size:1rem">—</span>
                </div>
                <input type="hidden" id="ratingValue" name="rating" value="0">
            </div>
            <div class="form-group">
                <label>Review / Notes</label>
                <textarea id="ratingReview" name="review" rows="3" placeholder="Optional review comment..."></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('perfRatingModal').classList.remove('active')">Cancel</button>
                <button type="submit" class="btn btn-warning"><i class="fas fa-star me-1"></i>Save Rating</button>
            </div>
        </form>
    </div>
</div>

<!-- Attendance Modal -->
<div id="attendanceModal" class="modal">
    <div class="modal-content" style="max-width:480px">
        <div class="modal-header"><h2>Mark Attendance</h2>
            <button class="close-btn" onclick="document.getElementById('attendanceModal').classList.remove('active')">&times;</button>
        </div>
        <form id="attendanceForm" style="padding:20px">
            <div class="form-group"><label>Staff Member <span class="text-danger">*</span></label>
                <select id="attendanceStaffId" name="user_id" required>
                    <option value="">Select Staff</option>
                </select>
            </div>
            <div class="row">
                <div class="col-md-6"><div class="form-group"><label>Date</label>
                    <input type="date" id="attendanceDate" name="attendance_date" required value="<?= date('Y-m-d') ?>">
                </div></div>
                <div class="col-md-6"><div class="form-group"><label>Status</label>
                    <select id="attendanceStatus" name="status">
                        <option value="present">Present</option>
                        <option value="absent">Absent</option>
                        <option value="half_day">Half Day</option>
                        <option value="late">Late</option>
                    </select>
                </div></div>
            </div>
            <div class="row">
                <div class="col-md-6"><div class="form-group"><label>Check In</label>
                    <input type="time" id="attendanceCheckIn" name="check_in">
                </div></div>
                <div class="col-md-6"><div class="form-group"><label>Check Out</label>
                    <input type="time" id="attendanceCheckOut" name="check_out">
                </div></div>
            </div>
            <div class="form-group"><label>Notes</label>
                <textarea id="attendanceNotes" name="notes" rows="2"></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('attendanceModal').classList.remove('active')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Attendance</button>
            </div>
        </form>
    </div>
</div>

<!-- Salary Modal -->
<div id="salaryModal" class="modal">
    <div class="modal-content" style="max-width:480px">
        <div class="modal-header"><h2>Process Salary</h2>
            <button class="close-btn" onclick="document.getElementById('salaryModal').classList.remove('active')">&times;</button>
        </div>
        <form id="salaryForm" style="padding:20px">
            <div class="form-group"><label>Staff Member <span class="text-danger">*</span></label>
                <select id="salaryStaffId" name="user_id" required>
                    <option value="">Select Staff</option>
                </select>
            </div>
            <div class="form-group"><label>Month</label>
                <input type="month" id="salaryMonthInput" name="month" value="<?= date('Y-m') ?>" required>
            </div>
            <div class="row">
                <div class="col-md-4"><div class="form-group"><label>Base Salary (TK)</label>
                    <input type="number" id="baseSalary" name="base_salary" min="0" step="0.01" required>
                </div></div>
                <div class="col-md-4"><div class="form-group"><label>Bonus (TK)</label>
                    <input type="number" id="bonusSalary" name="bonus" min="0" step="0.01" value="0">
                </div></div>
                <div class="col-md-4"><div class="form-group"><label>Deduction (TK)</label>
                    <input type="number" id="deductionSalary" name="deduction" min="0" step="0.01" value="0">
                </div></div>
            </div>
            <div class="form-group">
                <label>Net Salary (auto)</label>
                <div class="fw-bold" style="font-size:1.2rem;color:#27ae60">TK <span id="netSalaryDisplay">0</span></div>
            </div>
            <div class="form-group"><label>Payment Status</label>
                <select id="salaryPayStatus" name="payment_status">
                    <option value="pending">Pending</option>
                    <option value="paid">Paid</option>
                </select>
            </div>
            <div class="form-group"><label>Notes</label>
                <textarea id="salaryNotes" name="notes" rows="2"></textarea>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('salaryModal').classList.remove('active')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Salary</button>
            </div>
        </form>
    </div>
</div>

<style>
/* ===== ROLE BADGE STYLES ===== */
.role-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: .75rem;
    font-weight: 700;
    letter-spacing: .3px;
    white-space: nowrap;
}
.role-badge i { font-size: .7rem; }
.rb-admin        { background:#fde8e8; color:#c0392b; border:1px solid #f5c6c6; }
.rb-manager      { background:#fef3cd; color:#d97706; border:1px solid #fde68a; }
.rb-chef         { background:#fff0e0; color:#e67e22; border:1px solid #fcd9b1; }
.rb-waiter       { background:#e0f0ff; color:#2980b9; border:1px solid #bee3f8; }
.rb-cashier      { background:#e8f5e9; color:#27ae60; border:1px solid #c3e6cb; }

/* Performance table stars */
.perf-stars { color: #fbbf24; letter-spacing:1px; font-size:.9rem; }
.perf-stars .empty { color:#e2e8f0; }

/* Rating stars interactive */
.rating-star.active { color: #fbbf24 !important; }
.rating-star:hover  { color: #f59e0b !important; }

/* Staff profile modal layout */
.staff-profile-header {
    display: flex;
    align-items: center;
    gap: 18px;
    padding: 16px 20px;
    background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
    border-radius: 10px;
    margin-bottom: 20px;
}
.staff-avatar-lg {
    width: 64px; height: 64px;
    border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.6rem; color: #fff;
    flex-shrink: 0;
}
.staff-profile-info h3 { color: #f1f5f9; margin: 0 0 4px; font-size: 1.1rem; }
.staff-profile-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
    margin-bottom: 16px;
}
.staff-profile-grid .info-item label { font-size: .72rem; color: #94a3b8; text-transform: uppercase; letter-spacing: .5px; display: block; margin-bottom: 2px; }
.staff-profile-grid .info-item span  { font-size: .88rem; color: #0f172a; font-weight: 600; }
.staff-perf-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 10px;
    margin-top: 12px;
}
.staff-perf-card {
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 12px;
    text-align: center;
}
.staff-perf-card .val { font-size: 1.4rem; font-weight: 800; color: #0f172a; }
.staff-perf-card .lbl { font-size: .72rem; color: #64748b; margin-top: 2px; }
</style>
