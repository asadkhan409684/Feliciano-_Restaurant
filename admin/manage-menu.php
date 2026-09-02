<!-- ===== MENU MANAGEMENT SECTION ===== -->
<section id="menu-management" class="admin-section">
    <div class="section-header">
        <div><h2><i class="fas fa-utensils me-2"></i>Menu Management</h2><p>Add, edit and manage food items</p></div>
        <button class="btn btn-primary" onclick="showAddMenuModal()"><i class="fas fa-plus"></i> Add New Item</button>
    </div>

    <!-- Menu Filters -->
    <div class="menu-filters mb-3">
        <select id="categoryFilter" onchange="filterMenuItems()">
            <option value="all">All Categories</option>
            <?php foreach ($category_labels as $val => $label): ?>
                <option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($label) ?></option>
            <?php endforeach; ?>
        </select>
        <select id="menuStatusFilter" onchange="filterMenuItems()">
            <option value="all">All Status</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
        </select>
        <select id="menuStockFilter" onchange="filterMenuItems()">
            <option value="all">All Stock</option>
            <option value="in_stock">In Stock</option>
            <option value="out_of_stock">Out of Stock</option>
        </select>
        <input type="text" id="menuSearch" placeholder="Search menu items..." onkeyup="filterMenuItems()">
    </div>

    <div class="menu-table-container">
        <table class="admin-table" id="menuTable">
            <thead>
                <tr>
                    <th style="width:80px">Image</th>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Stock</th>
                    <th>Status</th>
                    <th style="width:160px;text-align:center">Actions</th>
                </tr>
            </thead>
            <tbody id="menuTableBody">
                <tr><td colspan="7" class="text-center py-4"><i class="fas fa-spinner fa-spin me-2"></i>Loading menu...</td></tr>
            </tbody>
        </table>
    </div>
</section>

<!-- Add / Edit Menu Item Modal -->
<div id="addMenuModal" class="modal">
    <div class="modal-content" style="max-width:680px">
        <div class="modal-header">
            <h2 id="modalTitle">Add New Menu Item</h2>
            <button class="close-btn" onclick="closeAddMenuModal()">&times;</button>
        </div>
        <form id="addMenuForm" style="padding:20px">
            <input type="hidden" id="menuItemId" name="id">

            <!-- ── Basic Info ── -->
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group"><label>Item Name <span class="text-danger">*</span></label><input type="text" id="itemName" name="name" required></div>
                </div>
                <div class="col-md-6">
                    <div class="form-group"><label>Category <span class="text-danger">*</span></label>
                        <select id="itemCategory" name="category" required>
                            <option value="">Select Category</option>
                            <?php foreach ($category_labels as $val => $label): ?>
                                <option value="<?= htmlspecialchars($val) ?>"><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group"><label>Price (TK) <span class="text-danger">*</span></label><input type="number" id="itemPrice" name="price" required min="0" step="0.01"></div>
                </div>
                <div class="col-md-6">
                    <div class="form-group"><label>Discount Price (TK)</label><input type="number" id="itemDiscountPrice" name="discount_price" min="0" step="0.01" placeholder="Optional"></div>
                </div>
            </div>
            <div class="form-group"><label>Description <span class="text-danger">*</span></label><textarea id="itemDescription" name="description" rows="3" required></textarea></div>
            <div class="form-group"><label>Ingredients (comma separated)</label><input type="text" id="itemIngredients" name="ingredients" placeholder="Chicken, Rice, Spices..."></div>

            <div class="row">
                <div class="col-md-3">
                    <div class="form-group"><label>Calories</label><input type="number" id="itemCalories" name="calories" min="0" placeholder="e.g. 450"></div>
                </div>
                <div class="col-md-3">
                    <div class="form-group"><label>Prep Time (min)</label><input type="number" id="itemPrepTime" name="prep_time" min="0" placeholder="e.g. 15"></div>
                </div>
                <div class="col-md-3">
                    <div class="form-group"><label>Cooking Time (min)</label><input type="number" id="itemCookingTime" name="cooking_time" min="0" placeholder="e.g. 20"></div>
                </div>
                <div class="col-md-3">
                    <div class="form-group"><label>Status</label>
                        <select id="itemStatus" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="form-group"><label>Nutrition Info</label><input type="text" id="itemNutrition" name="nutrition_info" placeholder="Protein: 20g, Carbs: 45g, Fat: 12g..."></div>
            <div class="form-group"><label>Allergens</label><input type="text" id="itemAllergens" name="allergens" placeholder="Gluten, Dairy, Nuts..."></div>
            <div class="form-group"><label>Availability Time</label><input type="text" id="itemAvailabilityTime" name="availability_time" placeholder="e.g. 08:00 AM – 10:00 PM"></div>

            <!-- ── Stock Availability ── -->
            <div class="form-group">
                <label>Stock Availability</label>
                <div class="d-flex align-items-center gap-3 flex-wrap">
                    <div class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" id="itemInStock" name="in_stock" value="1" checked
                               onchange="toggleStockQtyField()">
                        <label class="form-check-label fw-semibold" for="itemInStock" id="inStockLabel" style="color:#27ae60">In Stock</label>
                    </div>
                    <div id="stockQtyGroup" class="d-flex align-items-center gap-2">
                        <input type="number" id="itemStockQty" name="stock_quantity" min="0"
                               style="width:120px" placeholder="Qty (optional)" class="form-control form-control-sm">
                        <small class="text-muted">0 = unlimited</small>
                    </div>
                </div>
            </div>

            <!-- ── Tags ── -->
            <div class="form-group">
                <label>Tags</label>
                <div class="d-flex flex-wrap gap-3">
                    <label class="d-flex align-items-center gap-2"><input type="checkbox" id="tagFeatured" name="is_featured" value="1"> Featured</label>
                    <label class="d-flex align-items-center gap-2"><input type="checkbox" id="tagPopular" name="is_popular" value="1"> Popular</label>
                    <label class="d-flex align-items-center gap-2"><input type="checkbox" id="tagRecommended" name="is_recommended" value="1"> Recommended</label>
                    <label class="d-flex align-items-center gap-2"><input type="checkbox" id="tagSpecial" name="is_special" value="1"> Today's Special</label>
                </div>
            </div>

            <!-- ── Primary Image ── -->
            <div class="form-group">
                <label>Primary Image</label>
                <div class="image-upload-wrapper">
                    <input type="file" id="itemImageFile" name="image_file" accept="image/*" onchange="previewImage(this)">
                    <div class="image-preview" id="imagePreview">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <span>Click or drag to upload</span>
                        <img src="" alt="Preview" id="previewImg" style="display:none">
                    </div>
                </div>
            </div>

            <!-- ── Gallery Images (only shown in edit mode) ── -->
            <div id="galleryImagesSection" style="display:none">
                <div class="form-group">
                    <label>Additional Gallery Images
                        <small class="text-muted ms-2">(max 5 extra photos)</small>
                    </label>

                    <!-- Existing gallery thumbnails -->
                    <div id="existingGalleryImages" class="d-flex flex-wrap gap-2 mb-2"></div>

                    <!-- New gallery upload -->
                    <div class="d-flex align-items-center gap-2">
                        <label class="btn btn-outline-secondary btn-sm mb-0" style="cursor:pointer">
                            <i class="fas fa-images me-1"></i> Add More Photos
                            <input type="file" id="galleryImageFile" accept="image/*"
                                   style="display:none" onchange="uploadGalleryImage(this)">
                        </label>
                        <span id="galleryUploadStatus" style="font-size:.8rem;color:#64748b"></span>
                    </div>
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeAddMenuModal()">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Save Item</button>
            </div>
        </form>
    </div>
</div>

<style>
/* ── Gallery thumbnail inside modal ── */
.gallery-thumb-wrap {
    position: relative;
    width: 72px;
    height: 72px;
    border-radius: 8px;
    overflow: hidden;
    border: 2px solid #e2e8f0;
    flex-shrink: 0;
}
.gallery-thumb-wrap img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.gallery-thumb-del {
    position: absolute;
    top: 2px;
    right: 2px;
    background: rgba(220,38,38,.85);
    color: #fff;
    border: none;
    border-radius: 50%;
    width: 20px;
    height: 20px;
    font-size: .65rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    line-height: 1;
}
.gallery-thumb-del:hover { background:#b91c1c; }

/* ── Out of Stock badge in table ── */
.stock-badge-out  { background:#fef2f2; color:#dc2626; border:1px solid #fecaca; padding:3px 9px; border-radius:20px; font-size:.72rem; font-weight:700; }
.stock-badge-in   { background:#f0fdf4; color:#16a34a; border:1px solid #bbf7d0; padding:3px 9px; border-radius:20px; font-size:.72rem; font-weight:700; }
.stock-badge-qty  { font-size:.68rem; color:#64748b; margin-left:4px; }
</style>
