<!-- Team Management Section -->
<section id="team" class="admin-section">
    <div class="section-header">
        <h2>Team Management</h2>
        <button class="btn btn-primary" onclick="showAddStaffModal()">
            <i class="fas fa-plus"></i> Add New Member
        </button>
    </div>
    
    <div class="team-table-container">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Joined Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="teamTableBody">
                <!-- Team data will be populated here via admin-script.js -->
            </tbody>
        </table>
    </div>
</section>

<!-- Add/Edit Staff Modal -->
<div id="staffModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="staffModalTitle">Add New Team Member</h2>
            <button class="close-btn" onclick="closeStaffModal()">&times;</button>
        </div>
        <form id="staffForm">
            <input type="hidden" id="staffId" name="id">
            <div class="form-group">
                <label>First Name</label>
                <input type="text" id="staffFirstName" name="first_name" required>
            </div>
            <div class="form-group">
                <label>Last Name</label>
                <input type="text" id="staffLastName" name="last_name" required>
            </div>
            <div class="form-group">
                <label>Email</label>
                <input type="email" id="staffEmail" name="email" required>
            </div>
            <div class="form-group" id="staffPasswordGroup">
                <label>Password</label>
                <input type="password" id="staffPassword" name="password" required>
                <small>Required for new members</small>
            </div>
            <div class="form-group">
                <label>Role</label>
                <select id="staffRole" name="role" required onchange="toggleBranchSelection()">
                    <option value="staff">Employee</option>
                    <option value="manager">Manager</option>
                    <option value="admin">Administrator</option>
                    <option value="customer">Customer</option>
                </select>
            </div>
            <div class="form-group" id="branchSelectionGroup">
                <label>Assigned Branch</label>
                <select id="staffBranch" name="branch_id">
                    <option value="">No Branch / Main</option>
                    <!-- Populated by JS -->
                </select>
                <small>Required for Managers and Staff</small>
            </div>
            <div class="form-group">
                <label>Status</label>
                <select id="staffStatus" name="status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="suspended">Suspended</option>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeStaffModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Member</button>
            </div>
        </form>
    </div>
</div>
