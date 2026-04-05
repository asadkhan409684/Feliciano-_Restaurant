<?php 
session_start(); 
require_once '../config/database.php';
require_once '../config/categories.php';

// Fetch only active categories that have items (or just use all from config if requested)
// The user said "the categories in $category_labels are not showing on the website"
// So I will use the keys from $category_labels as the base.
$available_categories = array_keys($category_labels);

// Fetch all active menu items
$menu_query = "SELECT * FROM menu_items WHERE status = 'active' ORDER BY category, name";
$menu_result = $conn->query($menu_query);
$menu_items = [];
while($row = $menu_result->fetch_assoc()) {
    $menu_items[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Menu - Feliciano Restaurant</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/menu.css?v=<?php echo time(); ?>">
    <link rel="icon" type="image/png" href="../assets/images/favicon.png">
</head>

<body>
    <!-- Header -->
     <header>   
        <div class="container header-container">
            <a href="../index.php" class="logo">Feliciano<span>.</span></a>
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
                        <button class="category-btn" data-category="<?php echo htmlspecialchars($cat); ?>">
                            <?php echo htmlspecialchars($category_labels[$cat] ?? ucfirst($cat)); ?>
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
                                    ?>
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
                    <h3>Feliciano</h3>
                    <p>Experience culinary excellence at Feliciano, where every dish tells a story of passion, quality,
                        and tradition.</p>
                    <div class="social-icons">
                        <a href="https://www.facebook.com" target="_blank"><i class="fab fa-facebook-f"></i></a>
                        <a href="https://www.instagram.com" target="_blank"><i class="fab fa-instagram"></i></a>
                        <a href="https://www.twitter.com" target="_blank"><i class="fab fa-twitter"></i></a>

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
                        <li>Monday - Thursday: 11:00 AM - 10:00 PM</li>
                        <li>Friday - Saturday: 11:00 AM - 11:00 PM</li>
                        <li>Sunday: 10:00 AM - 9:00 PM</li>
                    </ul>
                </div>
            </div>
        </div>
    </footer>


   <button id="backToTop" class="back-to-top" title="Back to top">↑</button>
    <script src="../assets/js/script.js?v=<?php echo time(); ?>"></script>
</body>

</html>
