<!-- Branch Management Section -->
<section id="branches" class="admin-section">
    <div class="section-header">
        <h2>Branch Management</h2>
        <button class="btn btn-primary" onclick="showAddBranchModal()">
            <i class="fas fa-plus"></i> Add New Branch
        </button>
    </div>
    
    <div class="team-table-container">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Branch Name</th>
                    <th>Location</th>
                    <th>Phone</th>
                    <th>Created At</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="branchTableBody">
                <!-- Branch data will be populated here via admin-script.js -->
            </tbody>
        </table>
    </div>
</section>

<!-- Add/Edit Branch Modal -->
<div id="branchModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="branchModalTitle">Add New Branch</h2>
            <button class="close-btn" onclick="closeBranchModal()">&times;</button>
        </div>
        <form id="branchForm">
            <input type="hidden" id="branchId" name="id">
            <div class="form-group">
                <label>Branch Name</label>
                <input type="text" id="branchNameForm" name="name" required placeholder="e.g. Dhanmondi Branch">
            </div>
            <div class="form-group">
                <label>Location</label>
                <textarea id="branchLocation" name="location" required placeholder="Full address of the branch"></textarea>
            </div>
            <div class="form-group">
                <label>Phone Number</label>
                <input type="text" id="branchPhone" name="phone" required placeholder="e.g. +88017XXXXXXXX">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeBranchModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Branch</button>
            </div>
        </form>
    </div>
</div>
