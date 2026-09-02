<?php 
session_start(); 
require_once '../config/database.php';
require_once '../config/categories.php';
require_once '../config/settings_helper.php';
$settings = get_restaurant_settings($conn);

// Fetch logged-in user's phone number from DB (name & email already in session)
$loggedUserName  = '';
$loggedUserEmail = '';
$loggedUserPhone = '';
if (isset($_SESSION['user_id'])) {
    $loggedUserName  = $_SESSION['user_name']  ?? '';
    $loggedUserEmail = $_SESSION['user_email'] ?? '';
    $phone_stmt = $conn->prepare("SELECT phone FROM users WHERE id = ? LIMIT 1");
    $phone_stmt->bind_param("i", $_SESSION['user_id']);
    $phone_stmt->execute();
    $phone_row = $phone_stmt->get_result()->fetch_assoc();
    $phone_stmt->close();
    $loggedUserPhone = $phone_row['phone'] ?? '';
}

// Load categories from DB (dynamic) — fallback to static config if table missing
$dynamic_categories = [];
$cat_result = $conn->query("SELECT name, slug, icon FROM menu_categories WHERE status='active' ORDER BY sort_order ASC, name ASC");
if ($cat_result && $cat_result->num_rows > 0) {
    while ($cat_row = $cat_result->fetch_assoc()) {
        $dynamic_categories[$cat_row['slug']] = [
            'name' => $cat_row['name'],
            'icon' => $cat_row['icon'] ?? 'fa-tag',
        ];
    }
} else {
    // Fallback: use static config
    foreach ($category_labels as $slug => $name) {
        $dynamic_categories[$slug] = ['name' => $name, 'icon' => 'fa-tag'];
    }
}

// Only show categories that actually have active menu items
$available_categories = [];
foreach (array_keys($dynamic_categories) as $slug) {
    $check = $conn->prepare("SELECT COUNT(*) as c FROM menu_items WHERE LOWER(TRIM(category))=LOWER(?) AND status='active'");
    $check->bind_param("s", $slug);
    $check->execute();
    $cnt = (int)$check->get_result()->fetch_assoc()['c'];
    if ($cnt > 0) $available_categories[] = $slug;
}

// Fetch all active menu items
$menu_query = "SELECT * FROM menu_items WHERE status = 'active' ORDER BY category, name";
$menu_result = $conn->query($menu_query);
$menu_items = [];
while($row = $menu_result->fetch_assoc()) {
    // Ensure in_stock column exists (backward compat)
    if (!array_key_exists('in_stock', $row)) $row['in_stock'] = 1;
    $menu_items[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu - <?php echo htmlspecialchars($settings['restaurant_name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/menu.css?v=<?php echo time(); ?>">
    <link rel="icon" type="image/png" href="<?php echo get_logo_url($settings, '../'); ?>">
</head>

<body>
    <script>
        const currentUserEmail = "<?php echo htmlspecialchars($loggedUserEmail, ENT_QUOTES); ?>";
        const currentUserName  = "<?php echo htmlspecialchars($loggedUserName,  ENT_QUOTES); ?>";
        const currentUserPhone = "<?php echo htmlspecialchars($loggedUserPhone, ENT_QUOTES); ?>";
    </script>

    <!-- Header -->
     <header>   
        <div class="container header-container">
            <a href="../index.php" class="logo">
                <?php if (!empty($settings['restaurant_logo'])): ?>
                    <img src="<?php echo get_logo_url($settings, '../'); ?>" alt="<?php echo htmlspecialchars($settings['restaurant_name']); ?>" style="height:40px;width:auto;vertical-align:middle;margin-right:6px;">
                <?php endif; ?>
                <?php echo htmlspecialchars($settings['restaurant_name']); ?><span>.</span>
            </a>
            <nav>

                <ul class="nav-links">
                    <li><a href="../index.php">Home</a></li>
                    <li><a href="menu.php">Menu</a></li>
                    <li><a href="about.php">About</a></li>
                    <li><a href="contact.php">Contact</a></li>
                </ul>
            </nav>
            <div class="header-actions justify-content-end">
                <button id="orderBtn" class="order-badge-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" fill="currentColor"
                        class="bi bi-card-checklist" viewBox="0 0 16 16">
                        <path
                            d="M14.5 3a.5.5 0 0 1 .5.5v9a.5.5 0 0 1-.5.5h-13a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5zm-13-1A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h13a1.5 1.5 0 0 0 1.5-1.5v-9A1.5 1.5 0 0 0 14.5 2z" />
                        <path
                            d="M7 5.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5m-1.496-.854a.5.5 0 0 1 0 .708l-1.5 1.5a.5.5 0 0 1-.708 0l-.5-.5a.5.5 0 1 1 .708-.708l.146.147 1.146-1.147a.5.5 0 0 1 .708 0M7 9.5a.5.5 0 0 1 .5-.5h5a.5.5 0 0 1 0 1h-5a.5.5 0 0 1-.5-.5m-1.496-.854a.5.5 0 0 1 0 .708l-1.5 1.5a.5.5 0 0 1-.708 0l-.5-.5a.5.5 0 0 1 .708-.708l.146.147 1.146-1.147a.5.5 0 0 1 .708 0" />
                    </svg>
                    <span id="orderCount" class="order-count-badge">0</span>
                    <!-- <span id="orderCount" class="order-count-badge">0</span> -->
                </button>
                <a href="contact.php" class="reservation-btn">Reserve a Table</a>

                <?php if(isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in']): ?>
                    <?php if(isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin'): ?>
                        <a href="../admin/admin.php" class="auth-btn admin-btn"><i class="fas fa-chart-line"></i> Dashboard</a>
                    <?php elseif(isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'manager'): ?>
                        <a href="../manager/index.php" class="auth-btn admin-btn"><i class="fas fa-chart-line"></i> Dashboard</a>
                    <?php else: ?>
                        <a href="Profile/profile.php" class="auth-btn profile-btn"><i class="fas fa-user"></i> Profile</a>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="../auth/login.php" class="auth-btn login-btn"><i class="fas fa-sign-in-alt"></i> Login</a>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <!-- Order Card Modal -->
    <div id="orderModal" class="order-modal">
        <div class="order-modal-content">
            <div class="order-modal-header">
                <h2>Your Order</h2>
                <button class="close-btn" id="closeOrderBtn">&times;</button>
            </div>
            <div class="order-modal-body" id="orderList">
                <p class="empty-order">No items in your order yet</p>
            </div>
            <div class="order-modal-footer">
                <div class="order-total">
                    <strong>Total: </strong>
                    <span id="orderTotal">TK 0</span>
                </div>
                <button class="btn btn-primary" id="checkoutBtn">Proceed to Checkout</button>
                <button class="btn btn-secondary" id="clearOrderBtn">Clear Order</button>
            </div>
        </div>
    </div>

    <!-- Online Order Modal -->
    <div id="onlineOrderModal" class="order-modal">
        <div class="order-modal-content">
            <div class="order-modal-header">
                <h2>Online Order</h2>
                <button class="close-btn" id="closeOnlineOrderBtn">&times;</button>
            </div>
            <div class="order-modal-body">
                <form id="onlineOrderForm">
                    <div class="form-group">
                        <label for="customerName">Full Name</label>
                        <input type="text" id="customerName" name="customerName" required>
                    </div>
                    <div class="form-group">
                        <label for="customerPhone">Phone Number</label>
                        <input type="tel" id="customerPhone" name="customerPhone" required>
                    </div>
                    <div class="form-group">
                        <label for="customerEmail">Email</label>
                        <input type="email" id="customerEmail" name="customerEmail" required>
                    </div>
                    <div class="form-group">
                        <label for="deliveryAddress">Delivery Address</label>
                        <textarea id="deliveryAddress" name="deliveryAddress" rows="3" required></textarea>
                    </div>
                    <div class="form-group">
                        <label for="deliveryTime">Preferred Delivery Time</label>
                        <input type="datetime-local" id="deliveryTime" name="deliveryTime">
                    </div>
                    <div class="form-group">
                        <label for="specialInstructions">Special Instructions</label>
                        <textarea id="specialInstructions" name="specialInstructions" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="order-modal-footer">
                <div class="order-total">
                    <strong>Total: </strong>
                    <span id="onlineOrderTotal">TK 0</span>
                </div>
                <button class="btn btn-primary" id="confirmOnlineOrderBtn">Confirm Order</button>
                <button class="btn btn-secondary" id="cancelOnlineOrderBtn">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Offline Order Modal -->
    <div id="offlineOrderModal" class="order-modal">
        <div class="order-modal-content">
            <div class="order-modal-header">
                <h2>Offline Order</h2>
                <button class="close-btn" id="closeOfflineOrderBtn">&times;</button>
            </div>
            <div class="order-modal-body">
                <form id="offlineOrderForm">
                    <div class="form-group">
                        <label for="tableName">Table Number</label>
                        <input type="text" id="tableName" name="tableName" placeholder="Enter table number" required>
                    </div>
                    <div class="form-group">
                        <label for="personCount">Number of People</label>
                        <input type="number" id="personCount" name="personCount" min="1" max="20" value="1" required>
                    </div>
                    <div class="form-group">
                        <label for="customerNameOffline">Full Name</label>
                        <input type="text" id="customerNameOffline" name="customerNameOffline" required>
                    </div>
                    <div class="form-group">
                        <label for="customerPhoneOffline">Phone Number</label>
                        <input type="tel" id="customerPhoneOffline" name="customerPhoneOffline" required>
                    </div>
                    <div class="form-group">
                        <label for="customerEmailOffline">Email</label>
                        <input type="email" id="customerEmailOffline" name="customerEmailOffline" placeholder="Optional for guests">
                    </div>
                    <div class="form-group">
                        <label for="specialInstructionsOffline">Special Instructions</label>
                        <textarea id="specialInstructionsOffline" name="specialInstructionsOffline" rows="2"></textarea>
                    </div>
                </form>
            </div>
            <div class="order-modal-footer">
                <div class="order-total">
                    <strong>Total: </strong>
                    <span id="offlineOrderTotal">TK 0</span>
                </div>
                <button class="btn btn-primary" id="confirmOfflineOrderBtn">Confirm Order</button>
                <button class="btn btn-secondary" id="cancelOfflineOrderBtn">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <main>
        <section class="page-section">
            <div class="container">
                <div class="section-title">
                    <h2>Our <span>Menu</span></h2>
                    <p>Explore our extensive menu featuring the finest dishes crafted by our master chefs</p>
                </div>

                <div class="search-bar-container">
                    <input type="text" id="searchInput" class="search-bar"
                        placeholder="Search for dishes, ingredients, or prices...">
                    <i class="fas fa-search search-icon"></i>
                </div>

                <div class="menu-categories">
                    <button class="category-btn active" data-category="all">All</button>
                    <?php foreach ($available_categories as $cat): ?>
                        <?php $catData = $dynamic_categories[$cat] ?? ['name' => ucfirst($cat), 'icon' => 'fa-tag']; ?>
                        <button class="category-btn" data-category="<?php echo htmlspecialchars($cat); ?>">
                            <i class="fas <?php echo htmlspecialchars($catData['icon']); ?> me-1"></i>
                            <?php echo htmlspecialchars($catData['name']); ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <div class="menu-items-container">
                    <?php if (count($menu_items) > 0): ?>
                        <?php foreach ($menu_items as $item): ?>
                            <div class="professional-menu-card" data-category="<?php echo htmlspecialchars($item['category']); ?>">
                                <div class="card-image-container">
                                    <?php 
                                        $img_path = str_starts_with($item['image_url'], 'http') ? $item['image_url'] : '../' . $item['image_url'];
                                        $is_out_of_stock = isset($item['in_stock']) && $item['in_stock'] == 0;
                                    ?>
                                    <img src="<?php echo htmlspecialchars($img_path); ?>" 
                                         alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                         class="dish-image<?php echo $is_out_of_stock ? ' img-grayscale' : ''; ?>"
                                         onerror="this.src='https://via.placeholder.com/800x600/2a2a2a/c9a74d?text=Dish+Image'; this.classList.add('demo-image');">
                                    <div class="image-overlay"></div>
                                    <?php if ($is_out_of_stock): ?>
                                        <div class="out-of-stock-overlay">
                                            <span class="out-of-stock-badge">
                                                <i class="fas fa-ban me-1"></i>Out of Stock
                                            </span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="signature-badge"><?php echo htmlspecialchars($dynamic_categories[$item['category']]['name'] ?? ($category_labels[$item['category']] ?? $item['category'])); ?></div>
                                </div>
                                <div class="card-content">
                                    <div class="rating">
                                        <div class="stars">
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                            <i class="fas fa-star"></i>
                                        </div>
                                        <span class="review-count">(128 reviews)</span>
                                    </div>
                                    <h2 class="dish-name"><?php echo htmlspecialchars($item['name']); ?></h2>
                                    <p class="dish-description">
                                        <?php echo htmlspecialchars($item['description']); ?>
                                    </p>
                                    
                                    <div class="price-order-container">
                                        <div class="price">
                                            <small>TK</small> <?php echo number_format($item['price']); ?>
                                        </div>
                                        <?php if ($is_out_of_stock): ?>
                                            <button class="order-btn order-btn-disabled" disabled>
                                                <span>Out of Stock</span>
                                                <i class="fas fa-ban"></i>
                                            </button>
                                        <?php else: ?>
                                            <button class="order-btn" onclick="addToOrder(<?php echo $item['id']; ?>, '<?php echo addslashes($item['name']); ?>', <?php echo $item['price']; ?>)">
                                                <span>Order Now</span>
                                                <i class="fas fa-arrow-right"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="no-items">No menu items found.</p>
                    <?php endif; ?>
                </div>
            <div class="page-nav">
                <a href="../index.php" class="page-link">← Back to Home</a>
                <a href="contact.php" class="page-link">Make a Reservation →</a>
            </div>
            </div>
        </section>
    </main>

    <!-- Footer -->
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-column">
                    <h3><?php echo htmlspecialchars($settings['restaurant_name']); ?></h3>
                    <p><?php echo htmlspecialchars($settings['restaurant_about'] ?: 'Experience culinary excellence at ' . $settings['restaurant_name'] . ', where every dish tells a story of passion, quality, and tradition.'); ?></p>
                    <div class="social-icons">
                        <?php if (!empty($settings['social_facebook'])): ?><a href="<?php echo htmlspecialchars($settings['social_facebook']); ?>" target="_blank"><i class="fab fa-facebook-f"></i></a><?php endif; ?>
                        <?php if (!empty($settings['social_instagram'])): ?><a href="<?php echo htmlspecialchars($settings['social_instagram']); ?>" target="_blank"><i class="fab fa-instagram"></i></a><?php endif; ?>
                        <?php if (!empty($settings['social_twitter'])): ?><a href="<?php echo htmlspecialchars($settings['social_twitter']); ?>" target="_blank"><i class="fab fa-twitter"></i></a><?php endif; ?>
                    </div>
                </div>

                <div class="footer-column">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="../index.php">Home</a></li>
                        <li><a href="menu.php">Our Menu</a></li>
                        <li><a href="about.php">About Us</a></li>
                        <li><a href="contact.php">Reservations</a></li>
                    </ul>
                </div>

                <div class="footer-column">
                    <h3>Opening Hours</h3>
                    <ul>
                        <?php
                        $days_map = ['monday'=>'Mon','tuesday'=>'Tue','wednesday'=>'Wed','thursday'=>'Thu','friday'=>'Fri','saturday'=>'Sat','sunday'=>'Sun'];
                        foreach ($days_map as $key => $label):
                            $is_closed = !empty($settings['closed_'.$key]) && $settings['closed_'.$key]==='1';
                            $hrs = $is_closed ? 'Closed' : date('g:i A', strtotime($settings['open_'.$key]??'11:00')).' - '.date('g:i A', strtotime($settings['close_'.$key]??'22:00'));
                        ?>
                        <li><?php echo $label; ?>: <?php echo $hrs; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="footer-column">
                    <h3>Contact Info</h3>
                    <ul>
                        <li><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($settings['restaurant_address']); ?></li>
                        <li><i class="fas fa-phone"></i> <?php echo htmlspecialchars($settings['restaurant_phone']); ?></li>
                        <li><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($settings['restaurant_email']); ?></li>
                    </ul>
                </div>
            </div>

            <div class="copyright">
                <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($settings['restaurant_name']); ?>. All rights reserved.</p>
            </div>
        </div>
    </footer>


   <button id="backToTop" class="back-to-top" title="Back to top">↑</button>
    <script src="../assets/js/script.js?v=<?php echo time(); ?>"></script>
</body>

</html>
