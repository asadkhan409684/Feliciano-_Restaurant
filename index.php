<?php 
session_start(); 
require_once 'config/database.php';
require_once 'config/categories.php';

function getFeaturedItems($conn, $category, $limit = 4) {
    $stmt = $conn->prepare("SELECT * FROM menu_items WHERE category = ? AND status = 'active' LIMIT ?");
    $stmt->bind_param("si", $category, $limit);
    $stmt->execute();
    return $stmt->get_result();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feliciano - Best Restaurant</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="assets/css/home.css?v=<?php echo time(); ?>">
    <link rel="icon" type="image/png" href="assets/images/favicon.png">
</head>

<body>
    <script>
        const currentUserEmail = "<?php echo isset($_SESSION['user_email']) ? $_SESSION['user_email'] : ''; ?>";
    </script>

    <!-- Header -->
    <header>
        <div class="container header-container">
            <a href="index.php" class="logo">Feliciano<span>.</span></a>
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
                        <input type="email" id="customerEmailOffline" name="customerEmailOffline" placeholder="Optional for guests" value="<?php echo isset($_SESSION['user_email']) ? $_SESSION['user_email'] : ''; ?>">
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
        <section class="hero">
            <div class="container hero-content">
                <h3 class="hero-subtitle">WELLCOME</h3>
                <h1 class="hero-title">Experience the finest <span>Grilled Beef</span> in town</h1>
                <p class="hero-description">Welcome to Feliciano, where culinary excellence meets a warm, inviting
                    atmosphere. Our signature Grilled Beef with potatoes is crafted with the finest ingredients and
                    cooked to perfection.</p>
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
                        <li><a href="index.php">Home</a></li>
                        <li><a href="pages/menu.php">Our Menu</a></li>
                        <li><a href="pages/about.php">About Us</a></li>
                        <li><a href="pages/contact.php">Contact Us</a></li>
                    </ul>
                </div>

                <div class="footer-column">
                    <h3>Opening Hours</h3>
                    <ul>
                        <li>Monday - Thursday: 11:00 AM - 10:00 PM</li>
                        <li>Friday - Saturday: 11:00 AM - 11:00 PM</li>
                        <li>Sunday: 12:00 PM - 9:00 PM</li>
                    </ul>
                </div>

                <div class="footer-column">
                    <h3>Contact Info</h3>
                    <ul>
                        <li><i class="fas fa-map-marker-alt"></i> 123 Gourmet Street, Food City</li>
                        <li><i class="fas fa-phone"></i> +8801772-353298</li>
                        <li><i class="fas fa-envelope"></i> info@feliciano.com</li>
                    </ul>
                </div>
            </div>

            <div class="copyright">
                <p>&copy; 2023 Feliciano Restaurant. All rights reserved. | Designed with passion for fine dining</p>
            </div>
        </div>
    </footer>

    <button id="backToTop" class="back-to-top" title="Back to top">↑</button>
    <script src="assets/js/script.js"></script>
</body>

</html>
