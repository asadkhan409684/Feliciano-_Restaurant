<?php
require_once 'auth_check.php';
require_once '../config/database.php';

$message = '';
$edit_item = null;

// Handle Update Result
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_menu'])) {
    $id = (int)$_POST['id'];
    $price = (float)$_POST['price'];
    $status = $_POST['status'];
    $description = $_POST['description'];
    
    $stmt = $conn->prepare("UPDATE menu_items SET price = ?, status = ?, description = ? WHERE id = ?");
    $stmt->bind_param("dssi", $price, $status, $description, $id);
    
    if ($stmt->execute()) {
        $message = "<div class='alert alert-success' style='margin: 20px; padding: 15px; border-radius: 5px; color: #155724; background-color: #d4edda; border-color: #c3e6cb;'>Menu item updated successfully!</div>";
    } else {
        $message = "<div class='alert alert-danger' style='margin: 20px; padding: 15px; border-radius: 5px; color: #721c24; background-color: #f8d7da; border-color: #f5c6cb;'>Error: " . $conn->error . "</div>";
    }
}

// Check if editing
if (isset($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];
    $res = $conn->query("SELECT * FROM menu_items WHERE id = $edit_id");
    if ($res && $res->num_rows > 0) {
        $edit_item = $res->fetch_assoc();
    }
}

// Fetch all menu items
$sql = "SELECT id, name, category, price, status, image_url FROM menu_items ORDER BY category ASC, name ASC";
$result = $conn->query($sql);

include 'header.php';
?>

<section class="admin-section active">
    <div class="section-header">
        <h2>Menu Management</h2>
        <?php if ($edit_item): ?>
            <button class="btn btn-secondary" onclick="window.location.href='menu.php'">
                <i class="fas fa-arrow-left"></i> Back to List
            </button>
        <?php endif; ?>
    </div>

    <?= $message ?>

    <?php if ($edit_item): ?>
        <!-- Edit Form -->
        <div class="chart-container" style="max-width: 600px; margin: 0 auto; padding: 30px; border-radius: 10px; background: white; box-shadow: 0 4px 6px rgba(0,0,0,0.05);">
            <h3 style="margin-bottom: 20px; border-bottom: 2px solid #f1f2f6; padding-bottom: 10px;">Update Details: <?= htmlspecialchars($edit_item['name']) ?></h3>
            <form method="POST" action="menu.php">
                <input type="hidden" name="update_menu" value="1">
                <input type="hidden" name="id" value="<?= $edit_item['id'] ?>">
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; color: #2c3e50; font-weight: 500;">Item Name (Read Only)</label>
                    <input type="text" class="form-control" value="<?= htmlspecialchars($edit_item['name']) ?>" disabled style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; background: #f8f9fa;">
                </div>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; color: #2c3e50; font-weight: 500;">Price (TK)</label>
                    <input type="number" step="0.01" name="price" class="form-control" value="<?= htmlspecialchars($edit_item['price']) ?>" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                </div>
                
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; color: #2c3e50; font-weight: 500;">Status</label>
                    <select name="status" class="form-control" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                        <option value="active" <?= ($edit_item['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= ($edit_item['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                
                <div class="form-group" style="margin-bottom: 25px;">
                    <label style="display: block; margin-bottom: 5px; color: #2c3e50; font-weight: 500;">Description</label>
                    <textarea name="description" class="form-control" rows="3" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;"><?= htmlspecialchars($edit_item['description'] ?? '') ?></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary" style="background-color: #c9a74d; color: white; border: none; padding: 10px 20px; border-radius: 5px; font-weight: 600; cursor: pointer; display: inline-block;">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </form>
        </div>

    <?php else: ?>
        <!-- List -->
        <div class="menu-table-container team-table-container">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th style="width: 80px;">Image</th>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result && $result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?php if(!empty($row['image_url'])): ?>
                                        <img src="../<?= htmlspecialchars($row['image_url']) ?>" alt="MenuItem" style="width:50px; height:50px; object-fit:cover; border-radius:5px;">
                                    <?php endif; ?>
                                </td>
                                <td><strong><?= htmlspecialchars($row['name']) ?></strong></td>
                                <td><?= htmlspecialchars($row['category']) ?></td>
                                <td>TK <?= number_format($row['price'], 2) ?></td>
                                <td>
                                    <span class="status-badge status-<?= htmlspecialchars($row['status'] ?? 'active') ?>"><?= htmlspecialchars($row['status'] ?? 'active') ?></span>
                                </td>
                                <td class="action-buttons">
                                    <a href="menu.php?edit_id=<?= $row['id'] ?>" class="action-btn edit-btn" style="text-decoration:none; display:inline-block;">
                                        <i class="fas fa-edit"></i> Update
                                    </a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr><td colspan="6" style="text-align: center; padding: 40px; color: #7f8c8d;">No menu items found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<?php include 'footer.php'; ?>
