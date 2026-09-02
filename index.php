<?php 
session_start(); 
require_once 'config/database.php';
require_once 'config/categories.php';
require_once 'config/settings_helper.php';
$settings = get_restaurant_settings($conn);

function getFeaturedItems($conn, $category, $limit = 4) {
    $stmt = $conn->prepare("SELECT * FROM menu_items WHERE category = ? AND status = 'active' LIMIT ?");
    $stmt->bind_param("si", $category, $limit);
    $stmt->execute();
    return $stmt->get_result();
}

// Fetch logged-in user's phone from DB
$loggedUserName  = '';
$loggedUserEmail = '';
$loggedUserPhone = '';
if (isset($_SESSION['user_id'])) {
    $loggedUserName  = $_SESSION['user_name']  ?? '';
    $loggedUserEmail = $_SESSION['user_email'] ?? '';
    $ph = $conn->prepare("SELECT phone FROM users WHERE id = ? LIMIT 1");
    $ph->bind_param("i", $_SESSION['user_id']);
    $ph->execute();
    $ph_row = $ph->get_result()->fetch_assoc();
    $ph->close();
    $loggedUserPhone = $ph_row['phone'] ?? '';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($settings['restaurant_name']); ?> - Best Restaurant</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/home.css?v=<?php echo time(); ?>">
    <?php $logo_url = get_logo_url($settings); ?>
    <link rel="icon" type="image/png" href="<?php echo $logo_url; ?>">
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
            <a href="index.php" class="logo">
                <?php if (!empty($settings['restaurant_logo'])): ?>
                    <img src="<?php echo get_logo_url($settings); ?>" alt="<?php echo htmlspecialchars($settings['restaurant_name']); ?>" style="height:40px;width:auto;vertical-align:middle;margin-right:6px;">
                <?php endif; ?>
                <?php echo htmlspecialchars($settings['restaurant_name']); ?><span>.</span>
            </a>
            <nav>

                <ul class="nav-links">
                    <li><a href="index.php">Home</a></li>
                    <li><a href="pages/menu.php">Menu</a></li>
                    <li><a href="pages/about.php">About</a></li>
                    <li><a href="pages/contact.php">Contact</a></li>
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
                <a href="pages/contact.php" class="reservation-btn">Reserve a Table</a>

                <?php if(isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in']): ?>
                    <?php if($_SESSION['user_role'] === 'admin'): ?>
                        <a href="admin/admin.php" class="auth-btn admin-btn"><i class="fas fa-chart-line"></i> Dashboard</a>
                    <?php elseif(isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'manager'): ?>
                        <a href="manager/index.php" class="auth-btn admin-btn"><i class="fas fa-chart-line"></i> Dashboard</a>
                    <?php else: ?>
                        <a href="pages/Profile/profile.php" class="auth-btn profile-btn"><i class="fas fa-user"></i> Profile</a>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="auth/login.php" class="auth-btn login-btn"><i class="fas fa-sign-in-alt"></i> Login</a>
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
                        <label for="orderBranch">Select Branch</label>
                        <select id="orderBranch" name="branch_id" required>
                            <option value="">Select a branch near you</option>
                            <!-- Populated via JS -->
                        </select>
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
        <!-- Hero Section -->
        <?php $cover_url = get_cover_url($settings); ?>
        <section class="hero"<?php if ($cover_url): ?> style="background-image: linear-gradient(rgba(0,0,0,0.6), rgba(0,0,0,0.65)), url('<?php echo $cover_url; ?>'); background-size: cover; background-position: center;"<?php endif; ?>>
            <div class="container hero-content">
                <h3 class="hero-subtitle">WELCOME</h3>
                <h1 class="hero-title">Experience the finest <span>Grilled Beef</span> in town</h1>
                <p class="hero-description"><?php echo htmlspecialchars($settings['restaurant_about'] ?: 'Welcome to ' . $settings['restaurant_name'] . ', where culinary excellence meets a warm, inviting atmosphere.'); ?></p>
                <div class="cta-buttons">
                    <a href="pages/menu.php" class="btn btn-primary">View Our Menu</a>
                    <a href="pages/contact.php" class="btn btn-secondary">Book a Table</a>
                </div>
            </div>
        </section>

        <!-- Breakfast meal -->
        <section class="signature-dish page-section">
            <div class="container">
                <div class="section-title">
                    <h2>Our <span>Breakfast Option</span></h2>
                    <p>Discover the exquisite flavors that have made Feliciano the best restaurant in town</p>
                </div>

                <div class="dish-container" id="breakfastContainer">
                    <?php 
                    $breakfast_items = getFeaturedItems($conn, 'breakfast');
                    if ($breakfast_items->num_rows > 0):
                        while($item = $breakfast_items->fetch_assoc()):
                            $img_path = str_starts_with($item['image_url'], 'http') ? $item['image_url'] : $item['image_url'];
                    ?>
                        <div class="professional-menu-card">
                            <div class="card-image-container">
                                <img src="<?php echo htmlspecialchars($img_path); ?>" 
                                     alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                     class="dish-image"
                                     onerror="this.src='https://via.placeholder.com/800x600/2a2a2a/c9a74d?text=Dish+Image'; this.classList.add('demo-image');">
                                <div class="image-overlay"></div>
                                <div class="signature-badge"><?php echo htmlspecialchars($category_labels[$item['category']] ?? $item['category']); ?></div>
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
                                    <button class="order-btn" onclick="addToOrder(<?php echo $item['id']; ?>, '<?php echo addslashes($item['name']); ?>', <?php echo $item['price']; ?>)">
                                        <span>Order Now</span>
                                        <i class="fas fa-arrow-right"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; else: ?>
                        <p class="no-items">No breakfast items available.</p>
                    <?php endif; ?>
                </div>
            </div>

        </section>
        <!-- Plater items sections -->

        <section class="signature-dish page-section">
            <div class="container">
                <div class="section-title">
                    <h2>Our <span>Platters</span></h2>
                    <p>Discover the exquisite flavors that have made Feliciano the best restaurant in town</p>
                </div>

                <div class="dish-container" id="platterContainer">
                    <?php 
                    $platter_items = getFeaturedItems($conn, 'platter');
                    if ($platter_items->num_rows > 0):
                        while($item = $platter_items->fetch_assoc()):
                            $img_path = str_starts_with($item['image_url'], 'http') ? $item['image_url'] : $item['image_url'];
                    ?>
                        <div class="professional-menu-card">
                            <div class="card-image-container">
                                <img src="<?php echo htmlspecialchars($img_path); ?>" 
                                     alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                     class="dish-image"
                                     onerror="this.src='https://via.placeholder.com/800x600/2a2a2a/c9a74d?text=Dish+Image'; this.classList.add('demo-image');">
                                <div class="image-overlay"></div>
                                <div class="signature-badge"><?php echo htmlspecialchars($category_labels[$item['category']] ?? $item['category']); ?></div>
                            </div>
                            <div class="card-content">
                                <h2 class="dish-name"><?php echo htmlspecialchars($item['name']); ?></h2>
                                <p class="dish-description">
                                    <?php echo htmlspecialchars($item['description']); ?>
                                </p>
                                <div class="price-order-container">
                                    <div class="price">
                                        <small>TK</small> <?php echo number_format($item['price']); ?>
                                    </div>
                                    <button class="order-btn" onclick="addToOrder(<?php echo $item['id']; ?>, '<?php echo addslashes($item['name']); ?>', <?php echo $item['price']; ?>)">
                                        <span>Order Now</span>
                                        <i class="fas fa-arrow-right"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; else: ?>
                        <p class="no-items">No platters available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </section>

        <!-- Meal Deal -->

        <section class="signature-dish page-section">
            <div class="container">
                <div class="section-title">
                    <h2>Our <span>Meal Deal</span></h2><h4>(12 pm - 4 pm)</h4>
                    <p>Discover the exquisite flavors that have made Feliciano the best restaurant in town</p>
                </div>

                <div class="dish-container">
                    <!-- Meal Deal 1 -->

                    <?php 
                    $meal_deal_items = getFeaturedItems($conn, 'meal-deal', 3);
                    if ($meal_deal_items->num_rows > 0):
                        while($item = $meal_deal_items->fetch_assoc()):
                            $img_path = str_starts_with($item['image_url'], 'http') ? $item['image_url'] : $item['image_url'];
                    ?>
                        <div class="professional-menu-card">
                            <div class="card-image-container">
                                <img src="<?php echo htmlspecialchars($img_path); ?>" 
                                     alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                     class="dish-image"
                                     onerror="this.src='https://via.placeholder.com/800x600/2a2a2a/c9a74d?text=Dish+Image'; this.classList.add('demo-image');">
                                <div class="image-overlay"></div>
                                <div class="signature-badge"><?php echo htmlspecialchars($category_labels[$item['category']] ?? $item['category']); ?></div>
                            </div>
                            <div class="card-content">
                                <h2 class="dish-name"><?php echo htmlspecialchars($item['name']); ?></h2>
                                <p class="dish-description">
                                    <?php echo htmlspecialchars($item['description']); ?>
                                </p>
                                <div class="price-order-container">
                                    <div class="price">
                                        <small>TK</small> <?php echo number_format($item['price']); ?>
                                    </div>
                                    <button class="order-btn" onclick="addToOrder(<?php echo $item['id']; ?>, '<?php echo addslashes($item['name']); ?>', <?php echo $item['price']; ?>)">
                                        <span>Order Now</span>
                                        <i class="fas fa-arrow-right"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; else: ?>
                        <p class="no-items">No meal deals available.</p>
                    <?php endif; ?>
                </div>
            </div>
        </section>
      

        <!-- Signature Dish Section -->
        <section class="signature-dish page-section">
            <div class="container">
                <div class="section-title">
                    <h2>Our <span>Signature Dishes</span></h2>
                    <p>Discover the exquisite flavors that have made Feliciano the best restaurant in town</p>
                </div>

                <div class="dish-container">
                    <?php 
                    $signature_items = getFeaturedItems($conn, 'signature', 3);
                    if ($signature_items->num_rows > 0):
                        while($item = $signature_items->fetch_assoc()):
                            $img_path = str_starts_with($item['image_url'], 'http') ? $item['image_url'] : $item['image_url'];
                    ?>
                        <div class="professional-menu-card">
                            <div class="card-image-container">
                                <img src="<?php echo htmlspecialchars($img_path); ?>" 
                                     alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                     class="dish-image"
                                     onerror="this.src='https://via.placeholder.com/800x600/2a2a2a/c9a74d?text=Dish+Image'; this.classList.add('demo-image');">
                                <div class="image-overlay"></div>
                                <div class="signature-badge"><?php echo htmlspecialchars($category_labels[$item['category']] ?? $item['category']); ?></div>
                            </div>
                            <div class="card-content">
                                <h2 class="dish-name"><?php echo htmlspecialchars($item['name']); ?></h2>
                                <p class="dish-description">
                                    <?php echo htmlspecialchars($item['description']); ?>
                                </p>
                                <div class="price-order-container">
                                    <div class="price">
                                        <small>TK</small> <?php echo number_format($item['price']); ?>
                                    </div>
                                    <button class="order-btn" onclick="addToOrder(<?php echo $item['id']; ?>, '<?php echo addslashes($item['name']); ?>', <?php echo $item['price']; ?>)">
                                        <span>Order Now</span>
                                        <i class="fas fa-arrow-right"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; else: ?>
                        <p class="no-items">No signature dishes available.</p>
                    <?php endif; ?>
                </div>

                <div class="page-nav">
                    <a href="pages/menu.php" class="page-link">View Full Menu →</a>
                </div>
            </div>
        </section>
        <!-- Featured / Is_featured Items Section -->
        <?php
        $featured_items = $conn->query("SELECT * FROM menu_items WHERE is_featured=1 AND status='active' ORDER BY name ASC LIMIT 8");
        if ($featured_items && $featured_items->num_rows > 0):
        ?>
        <section class="signature-dish page-section">
            <div class="container">
                <div class="section-title">
                    <h2>Our <span>Featured Dishes</span></h2>
                    <p>Hand-picked favourites by our chef — must-try items loved by our guests</p>
                </div>
                <div class="dish-container">
                    <?php while ($item = $featured_items->fetch_assoc()):
                        $img_path = $item['image_url'] ?: 'assets/images/menu/default.jpg';
                        $out_of_stock = isset($item['in_stock']) && !$item['in_stock'];
                    ?>
                    <div class="professional-menu-card<?= $out_of_stock ? ' out-of-stock-card' : '' ?>">
                        <div class="card-image-container">
                            <img src="<?= htmlspecialchars($img_path) ?>"
                                 alt="<?= htmlspecialchars($item['name']) ?>"
                                 class="dish-image"
                                 onerror="this.src='assets/images/menu/default.jpg'">
                            <div class="image-overlay"></div>
                            <div class="signature-badge" style="background:#c9a74d;color:#1a1a2e">
                                <i class="fas fa-star me-1"></i>Featured
                            </div>
                            <?php if ($out_of_stock): ?>
                            <div style="position:absolute;inset:0;background:rgba(0,0,0,.55);display:flex;align-items:center;justify-content:center;border-radius:inherit">
                                <span style="background:#e74c3c;color:#fff;padding:6px 16px;border-radius:20px;font-weight:700;font-size:.85rem">Out of Stock</span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-content">
                            <h2 class="dish-name"><?= htmlspecialchars($item['name']) ?></h2>
                            <?php if (!empty($item['description'])): ?>
                            <p class="dish-description"><?= htmlspecialchars(mb_substr($item['description'],0,80)) . (mb_strlen($item['description'])>80?'…':'') ?></p>
                            <?php endif; ?>
                            <div class="price-order-container">
                                <div class="price">
                                    <?php if (!empty($item['discount_price']) && $item['discount_price'] > 0): ?>
                                    <small style="text-decoration:line-through;color:#94a3b8;font-size:.75em">TK <?= number_format($item['price']) ?></small>
                                    <span><small>TK</small> <?= number_format($item['discount_price']) ?></span>
                                    <?php else: ?>
                                    <small>TK</small> <?= number_format($item['price']) ?>
                                    <?php endif; ?>
                                </div>
                                <?php if (!$out_of_stock): ?>
                                <button class="order-btn" onclick="addToOrder(<?= $item['id'] ?>, '<?= addslashes($item['name']) ?>', <?= $item['discount_price'] ?: $item['price'] ?>)">
                                    <span>Order Now</span><i class="fas fa-arrow-right"></i>
                                </button>
                                <?php else: ?>
                                <button class="order-btn" disabled style="opacity:.5;cursor:not-allowed">
                                    <span>Out of Stock</span>
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- Testimonials Section -->
        <?php
        $testimonials = $conn->query("SHOW TABLES LIKE 'testimonials'");
        $show_testimonials = $testimonials && $testimonials->num_rows > 0;
        $testimonials_data = [];
        if ($show_testimonials) {
            $t_res = $conn->query("SELECT * FROM testimonials WHERE status='active' ORDER BY sort_order ASC, id DESC LIMIT 6");
            if ($t_res) while ($row = $t_res->fetch_assoc()) $testimonials_data[] = $row;
        }
        if (!empty($testimonials_data)):
        ?>
        <section class="page-section" style="background:linear-gradient(135deg,#0f172a 0%,#1e293b 100%);padding:70px 0">
            <div class="container">
                <div class="section-title">
                    <h2 style="color:#fff">What Our <span>Guests Say</span></h2>
                    <p style="color:#94a3b8">Real reviews from people who love our food</p>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:24px;margin-top:40px">
                    <?php foreach ($testimonials_data as $t):
                        $stars = str_repeat('★', (int)$t['rating']) . str_repeat('☆', 5 - (int)$t['rating']);
                    ?>
                    <div style="background:rgba(255,255,255,.05);border:1px solid rgba(201,167,77,.25);border-radius:14px;padding:24px;position:relative">
                        <div style="color:#c9a74d;font-size:1.1rem;margin-bottom:8px"><?= $stars ?></div>
                        <p style="color:#cbd5e1;font-size:.92rem;line-height:1.7;margin-bottom:16px;font-style:italic">"<?= htmlspecialchars($t['content']) ?>"</p>
                        <div style="display:flex;align-items:center;gap:10px">
                            <div style="width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#c9a74d,#f0d080);display:flex;align-items:center;justify-content:center;font-weight:800;color:#1a1a2e;flex-shrink:0">
                                <?= htmlspecialchars(mb_strtoupper(mb_substr($t['name'],0,1))) ?>
                            </div>
                            <div>
                                <div style="color:#f1f5f9;font-weight:700;font-size:.88rem"><?= htmlspecialchars($t['name']) ?></div>
                                <?php if (!empty($t['designation'])): ?>
                                <div style="color:#64748b;font-size:.75rem"><?= htmlspecialchars($t['designation']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div style="position:absolute;top:16px;right:18px;font-size:2.5rem;color:rgba(201,167,77,.15);line-height:1">"</div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>

        <!-- FAQ Section -->
        <?php
        $faqs_check = $conn->query("SHOW TABLES LIKE 'faqs'");
        $faqs_data = [];
        if ($faqs_check && $faqs_check->num_rows > 0) {
            $faq_res = $conn->query("SELECT * FROM faqs WHERE status='active' ORDER BY sort_order ASC, id ASC LIMIT 8");
            if ($faq_res) while ($row = $faq_res->fetch_assoc()) $faqs_data[] = $row;
        }
        if (!empty($faqs_data)):
        ?>
        <section class="page-section" style="background:#f8fafc;padding:70px 0">
            <div class="container">
                <div class="section-title">
                    <h2>Frequently Asked <span>Questions</span></h2>
                    <p>Everything you need to know about dining with us</p>
                </div>
                <div style="max-width:760px;margin:40px auto 0;display:flex;flex-direction:column;gap:12px">
                    <?php foreach ($faqs_data as $idx => $faq): ?>
                    <details style="background:#fff;border:1px solid #e2e8f0;border-radius:10px;overflow:hidden" <?= $idx===0?'open':'' ?>>
                        <summary style="padding:16px 20px;font-weight:700;font-size:.95rem;color:#0f172a;cursor:pointer;list-style:none;display:flex;justify-content:space-between;align-items:center;gap:12px">
                            <span><?= htmlspecialchars($faq['question']) ?></span>
                            <i class="fas fa-chevron-down" style="color:#c9a74d;flex-shrink:0;transition:transform .25s"></i>
                        </summary>
                        <div style="padding:0 20px 16px;color:#475569;font-size:.88rem;line-height:1.7;border-top:1px solid #f1f5f9">
                            <?= htmlspecialchars($faq['answer']) ?>
                        </div>
                    </details>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <?php endif; ?>
    <footer>
        <div class="container">
            <div class="footer-content">
                <div class="footer-column">
                    <h3><?php echo htmlspecialchars($settings['restaurant_name']); ?></h3>
                    <p><?php echo htmlspecialchars($settings['restaurant_about'] ?: 'Experience culinary excellence at Feliciano, where every dish tells a story of passion, quality, and tradition.'); ?></p>
                    <div class="social-icons">
                        <?php if (!empty($settings['social_facebook'])): ?>
                            <a href="<?php echo htmlspecialchars($settings['social_facebook']); ?>" target="_blank"><i class="fab fa-facebook-f"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($settings['social_instagram'])): ?>
                            <a href="<?php echo htmlspecialchars($settings['social_instagram']); ?>" target="_blank"><i class="fab fa-instagram"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($settings['social_twitter'])): ?>
                            <a href="<?php echo htmlspecialchars($settings['social_twitter']); ?>" target="_blank"><i class="fab fa-twitter"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($settings['social_youtube'])): ?>
                            <a href="<?php echo htmlspecialchars($settings['social_youtube']); ?>" target="_blank"><i class="fab fa-youtube"></i></a>
                        <?php endif; ?>
                        <?php if (!empty($settings['social_tiktok'])): ?>
                            <a href="<?php echo htmlspecialchars($settings['social_tiktok']); ?>" target="_blank"><i class="fab fa-tiktok"></i></a>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="footer-column">
                    <h3>Quick Links</h3>
                    <ul>
                        <li><a href="index.php">Home</a></li>
                        <li><a href="pages/menu.php">Our Menu</a></li>
                        <li><a href="pages/about.php">About Us</a></li>
                        <li><a href="pages/contact.php">Contact Us</a></li>
                    </ul>
                </div>

                <div class="footer-column">
                    <h3>Opening Hours</h3>
                    <ul>
                        <?php
                        $days_map = [
                            'monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday',
                            'thursday' => 'Thursday', 'friday' => 'Friday', 'saturday' => 'Saturday', 'sunday' => 'Sunday'
                        ];
                        foreach ($days_map as $key => $label):
                            $is_closed = !empty($settings['closed_' . $key]) && $settings['closed_' . $key] === '1';
                            $open_fmt  = $is_closed ? 'Closed' : date('g:i A', strtotime($settings['open_' . $key] ?? '11:00')) . ' - ' . date('g:i A', strtotime($settings['close_' . $key] ?? '22:00'));
                        ?>
                        <li><?php echo $label; ?>: <?php echo $open_fmt; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>

                <div class="footer-column">
                    <h3>Contact Info</h3>
                    <ul>
                        <li><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($settings['restaurant_address']); ?></li>
                        <li><i class="fas fa-phone"></i> <?php echo htmlspecialchars($settings['restaurant_phone']); ?></li>
                        <li><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($settings['restaurant_email']); ?></li>
                        <?php if (!empty($settings['social_whatsapp'])): ?>
                        <li><i class="fab fa-whatsapp"></i> <?php echo htmlspecialchars($settings['social_whatsapp']); ?></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <div class="copyright">
                <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($settings['restaurant_name']); ?>. All rights reserved. | Designed with passion for fine dining</p>
            </div>
        </div>
    </footer>

    <button id="backToTop" class="back-to-top" title="Back to top">↑</button>
    <script src="assets/js/script.js"></script>
</body>

</html>
