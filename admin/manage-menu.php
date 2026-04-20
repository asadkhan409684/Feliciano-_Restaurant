<!-- Menu Management Section -->
<section id="menu-management" class="admin-section">
    <div class="section-header">
        <h2>Menu Management</h2>
        <button class="btn btn-primary" onclick="showAddMenuModal()">
            <i class="fas fa-plus"></i> Add New Item
        </button>
    </div>
    
    <div class="menu-filters">
        <select id="categoryFilter" onchange="filterMenuItems()">
            <option value="all">All Categories</option>
            <?php foreach ($category_labels as $val => $label): ?>
                <option value="<?php echo htmlspecialchars($val); ?>"><?php echo htmlspecialchars($label); ?></option>
            <?php endforeach; ?>
        </select>
        
        <input type="text" id="menuSearch" placeholder="Search menu items..." onkeyup="searchMenuItems()">
    </div>
    
    <div class="menu-table-container">
        <table class="admin-table" id="menuTable">
            <thead>
                <tr>
                    <th>Image</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="menuTableBody">
                <!-- Menu items will be populated here -->
            </tbody>
        </table>
    </div>
</section>

<!-- Add Menu Item Modal -->
<div id="addMenuModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2 id="modalTitle">Add New Menu Item</h2>
            <button class="close-btn" onclick="closeAddMenuModal()">&times;</button>
        </div>
        <form id="addMenuForm">
            <input type="hidden" id="menuItemId" name="id">
            <div class="form-group">
                <label>Item Name</label>
                <input type="text" id="itemName" name="name" required>
            </div>
            <div class="form-group">
                <label>Category</label>
                <select id="itemCategory" name="category" required>
                    <option value="">Select Category</option>
                    <?php foreach ($category_labels as $val => $label): ?>
                        <option value="<?php echo htmlspecialchars($val); ?>"><?php echo htmlspecialchars($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Price (TK)</label>
                <input type="number" id="itemPrice" name="price" required>
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea id="itemDescription" name="description" rows="3" required></textarea>
            </div>
            <div class="form-group">
                <label>Item Image</label>
                <div class="image-upload-wrapper">
                    <input type="file" id="itemImageFile" name="image_file" accept="image/*" onchange="previewImage(this)">
                    <div class="image-preview" id="imagePreview">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <span>Click or drag to upload image</span>
                        <img src="" alt="Preview" id="previewImg" style="display: none;">
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label>Ingredients (comma separated)</label>
                <input type="text" id="itemIngredients" name="ingredients" placeholder="Ingredient 1, Ingredient 2, ...">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeAddMenuModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Item</button>
            </div>
        </form>
    </div>
</div>
