<!-- ===== BRANCH MANAGEMENT SECTION ===== -->
<section id="branches" class="admin-section">
    <div class="section-header">
        <div><h2><i class="fas fa-store-alt me-2"></i>Branch Management</h2><p>Manage restaurant branches and locations</p></div>
        <button class="btn btn-primary" onclick="showAddBranchModal()"><i class="fas fa-plus"></i> Add Branch</button>
    </div>

    <div class="team-table-container table-responsive">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Branch Name</th>
                    <th>Location</th>
                    <th>Phone</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th style="text-align:center">Actions</th>
                </tr>
            </thead>
            <tbody id="branchTableBody">
                <tr><td colspan="7" class="text-center py-4"><i class="fas fa-spinner fa-spin me-2"></i>Loading branches...</td></tr>
            </tbody>
        </table>
    </div>
</section>

<!-- Add / Edit Branch Modal -->
<div id="branchModal" class="modal">
    <div class="modal-content" style="max-width:480px">
        <div class="modal-header">
            <h2 id="branchModalTitle">Add New Branch</h2>
            <button class="close-btn" onclick="closeBranchModal()">&times;</button>
        </div>
        <form id="branchForm" style="padding:20px">
            <input type="hidden" id="branchId" name="id">
            <div class="form-group">
                <label>Branch Name <span class="text-danger">*</span></label>
                <input type="text" id="branchNameForm" name="name" required placeholder="e.g. Dhanmondi Branch">
            </div>
            <div class="form-group">
                <label>Location / Address <span class="text-danger">*</span></label>
                <textarea id="branchLocation" name="location" required rows="3" placeholder="Full address of the branch"></textarea>
            </div>
            <div class="form-group">
                <label>Phone Number <span class="text-danger">*</span></label>
                <input type="text" id="branchPhone" name="phone" required placeholder="+88017XXXXXXXX">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select id="branchStatus" name="status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeBranchModal()">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Branch</button>
            </div>
        </form>
    </div>
</div>
