<!-- ===== CATEGORY MANAGEMENT SECTION ===== -->
<section id="category-management" class="admin-section">
    <div class="section-header">
        <div>
            <h2><i class="fas fa-tags me-2"></i>Category Management</h2>
            <p>Menu categories পরিচালনা করুন — Add, Edit, Delete এবং Sort Order</p>
        </div>
        <button class="btn btn-primary" onclick="showCategoryModal()">
            <i class="fas fa-plus"></i> New Category
        </button>
    </div>

    <!-- Stats -->
    <div class="stats-grid mb-4" style="grid-template-columns:repeat(auto-fit,minmax(180px,1fr))">
        <div class="stat-card" style="border-left:4px solid #3498db">
            <div class="stat-icon" style="background:#3498db"><i class="fas fa-tags"></i></div>
            <div class="stat-info"><h3 id="catTotalCount">0</h3><p>Total Categories</p></div>
        </div>
        <div class="stat-card" style="border-left:4px solid #27ae60">
            <div class="stat-icon" style="background:#27ae60"><i class="fas fa-check-circle"></i></div>
            <div class="stat-info"><h3 id="catActiveCount">0</h3><p>Active</p></div>
        </div>
        <div class="stat-card" style="border-left:4px solid #e74c3c">
            <div class="stat-icon" style="background:#e74c3c"><i class="fas fa-pause-circle"></i></div>
            <div class="stat-info"><h3 id="catInactiveCount">0</h3><p>Inactive</p></div>
        </div>
        <div class="stat-card" style="border-left:4px solid #c9a74d">
            <div class="stat-icon" style="background:#c9a74d"><i class="fas fa-utensils"></i></div>
            <div class="stat-info"><h3 id="catMenuItemCount">0</h3><p>Menu Items</p></div>
        </div>
    </div>

    <!-- Filter & Search -->
    <div class="menu-filters mb-3">
        <select id="catStatusFilter" onchange="filterCategories()">
            <option value="all">All Status</option>
            <option value="active">Active</option>
            <option value="inactive">Inactive</option>
        </select>
        <input type="text" id="catSearch" placeholder="Category নাম খুঁজুন..." onkeyup="filterCategories()">
    </div>

    <!-- Category Table -->
    <div class="table-responsive">
        <table class="admin-table" id="categoryTable">
            <thead>
                <tr>
                    <th style="width:60px">Sort</th>
                    <th style="width:50px">#</th>
                    <th>Category Name (বাংলা/English)</th>
                    <th>Slug / Value</th>
                    <th>Icon</th>
                    <th style="text-align:center">Menu Items</th>
                    <th style="text-align:center">Status</th>
                    <th style="width:160px;text-align:center">Actions</th>
                </tr>
            </thead>
            <tbody id="categoryTableBody">
                <tr>
                    <td colspan="8" class="text-center py-4 text-muted">
                        <i class="fas fa-spinner fa-spin me-2"></i>Loading categories...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Info note -->
    <div class="mt-3 p-3 rounded" style="background:#fffbeb;border:1px solid #f59e0b;font-size:.84rem;color:#92400e;">
        <i class="fas fa-info-circle me-2"></i>
        <strong>নোট:</strong> Category delete করলে সেই category-র menu items গুলো <em>Uncategorized</em> হয়ে যাবে।
        Sort order পরিবর্তন করতে ↑↓ বাটন ব্যবহার করুন।
    </div>
</section>

<!-- ===== ADD / EDIT CATEGORY MODAL ===== -->
<div id="categoryModal" class="modal">
    <div class="modal-content" style="max-width:500px">
        <div class="modal-header">
            <h2 id="categoryModalTitle"><i class="fas fa-tag me-2"></i>New Category</h2>
            <button class="close-btn" onclick="closeCategoryModal()">&times;</button>
        </div>
        <form id="categoryForm" style="padding:20px">
            <input type="hidden" id="categoryId" name="id">

            <div class="form-group">
                <label>Category Name <span class="text-danger">*</span>
                    <small class="text-muted">(যেটা দেখানো হবে)</small>
                </label>
                <input type="text" id="categoryName" name="name" required
                       placeholder="e.g. Breakfast, Pizza, Beverages..."
                       oninput="autoGenerateSlug()">
            </div>

            <div class="form-group">
                <label>Slug / Value <span class="text-danger">*</span>
                    <small class="text-muted">(ছোট হাতে, hyphen দিয়ে)</small>
                </label>
                <div class="d-flex gap-2">
                    <input type="text" id="categorySlug" name="slug" required
                           placeholder="e.g. breakfast, hot-coffee"
                           style="text-transform:lowercase;">
                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="autoGenerateSlug(true)"
                            title="Auto generate from name" style="white-space:nowrap;padding:0 10px">
                        <i class="fas fa-magic"></i>
                    </button>
                </div>
                <small class="text-muted">এই value টি database-এ store হবে। পরিবর্তন করলে existing menu items affected হতে পারে।</small>
            </div>

            <div class="form-group">
                <label>Icon <small class="text-muted">(Font Awesome class)</small></label>
                <div class="d-flex gap-2 align-items-center">
                    <input type="text" id="categoryIcon" name="icon"
                           placeholder="e.g. fa-coffee, fa-pizza-slice, fa-burger"
                           oninput="previewCategoryIcon()">
                    <span id="iconPreview" style="font-size:1.5rem;width:36px;text-align:center;color:#c9a74d">
                        <i class="fas fa-tag"></i>
                    </span>
                </div>
                <small class="text-muted">
                    <a href="https://fontawesome.com/icons" target="_blank">Font Awesome icons</a> থেকে নাম কপি করুন
                </small>
            </div>

            <div class="form-group">
                <label>Description <small class="text-muted">(optional)</small></label>
                <textarea id="categoryDesc" name="description" rows="2"
                          placeholder="Brief description of this category..."></textarea>
            </div>

            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Sort Order</label>
                        <input type="number" id="categorySortOrder" name="sort_order"
                               min="0" value="0" placeholder="0 = first">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>Status</label>
                        <select id="categoryStatus" name="status">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" onclick="closeCategoryModal()">Cancel</button>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save me-1"></i>Save Category
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ===== DELETE CONFIRM MODAL ===== -->
<div id="catDeleteModal" class="modal">
    <div class="modal-content" style="max-width:420px">
        <div class="modal-header" style="background:#fff5f5;border-bottom:1px solid #fed7d7">
            <h2 style="color:#e53e3e"><i class="fas fa-triangle-exclamation me-2"></i>Delete Category</h2>
            <button class="close-btn" onclick="closeCatDeleteModal()">&times;</button>
        </div>
        <div style="padding:20px">
            <p style="color:#2d3748;font-size:.95rem;margin-bottom:8px">
                আপনি কি নিশ্চিত যে <strong id="catDeleteName" style="color:#e53e3e"></strong> category টি delete করতে চান?
            </p>
            <div class="p-3 rounded mb-4" style="background:#fff5f5;border:1px solid #fed7d7;font-size:.85rem;color:#c53030">
                <i class="fas fa-exclamation-circle me-1"></i>
                এই category-র <strong id="catDeleteItemCount">0</strong> টি menu item আছে।
                Delete করলে সেগুলো <em>Uncategorized</em> হয়ে যাবে।
            </div>
            <div class="modal-actions">
                <button class="btn btn-secondary" onclick="closeCatDeleteModal()">Cancel</button>
                <button class="btn btn-danger" id="catDeleteConfirmBtn" onclick="confirmDeleteCategory()">
                    <i class="fas fa-trash me-1"></i>Yes, Delete
                </button>
            </div>
        </div>
    </div>
</div>
