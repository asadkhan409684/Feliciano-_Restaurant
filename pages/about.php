<?php session_start();
require_once '../config/database.php';
require_once '../config/settings_helper.php';
$settings = get_restaurant_settings($conn);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - <?php echo htmlspecialchars($settings['restaurant_name']); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/about.css?v=<?php echo time(); ?>">
    <link rel="icon" type="image/png" href="<?php echo get_logo_url($settings, '../'); ?>">
</head>

<body>
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

    <!-- Main Content -->
    <main>
        <!-- Hero Section -->
        <section class="about-hero">
            <div class="container">
                <div class="section-title">
                    <h2>About <span><?php echo htmlspecialchars($settings['restaurant_name']); ?></span></h2>
                    <p>Discover the story behind our culinary excellence and passion for fine dining</p>
                </div>
            </div>
        </section>

        <!-- Our Story Section -->
        <section class="page-section">
            <div class="container">
                <div class="story-container">
                    <div class="story-content">
                        <h3>Our <span>Story</span></h3>
                        <?php if (!empty($settings['restaurant_about'])): ?>
                            <p><?php echo nl2br(htmlspecialchars($settings['restaurant_about'])); ?></p>
                        <?php else: ?>
                        <p>Founded in 2015, <?php echo htmlspecialchars($settings['restaurant_name']); ?> began as a dream to create an extraordinary dining experience that celebrates the art of culinary excellence. Named after our founder's grandfather, Feliciano embodies the tradition of bringing families together through exceptional food and warm hospitality.</p>
                        <p>What started as a small family restaurant has grown into one of the city's most beloved dining destinations, known for our signature grilled beef, innovative menu, and commitment to using only the finest ingredients sourced from local farms and trusted suppliers.</p>
                        <p>Every dish at <?php echo htmlspecialchars($settings['restaurant_name']); ?> tells a story of passion, creativity, and dedication to the craft of cooking. Our chefs combine traditional techniques with modern innovation to create memorable experiences that keep our guests coming back.</p>
                        <?php endif; ?>
                    </div>
                    <div class="story-image">
                        <div class="image-placeholder">
                            <i class="fas fa-utensils"></i>
                            <p>Restaurant Interior</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Our Mission Section -->
        <section class="page-section mission-section">
            <div class="container">
                <div class="mission-container">
                    <div class="mission-item">
                        <div class="mission-icon">
                            <i class="fas fa-heart"></i>
                        </div>
                        <h4>Our Mission</h4>
                        <p>To create unforgettable dining experiences through exceptional cuisine, outstanding service, and a warm, welcoming atmosphere that makes every guest feel like family.</p>
                    </div>
                    
                    <div class="mission-item">
                        <div class="mission-icon">
                            <i class="fas fa-eye"></i>
                        </div>
                        <h4>Our Vision</h4>
                        <p>To be recognized as the premier dining destination, setting the standard for culinary excellence and hospitality in our community and beyond.</p>
                    </div>
                    
                    <div class="mission-item">
                        <div class="mission-icon">
                            <i class="fas fa-star"></i>
                        </div>
                        <h4>Our Values</h4>
                        <p>Quality, authenticity, sustainability, and community. We believe in supporting local producers and creating a positive impact through our business practices.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Meet Our Team Section -->
        <section class="page-section">
            <div class="container">
                <div class="section-title">
                    <h2>Meet Our <span>Team</span></h2>
                    <p>The passionate professionals behind your exceptional dining experience</p>
                </div>
                
                <div class="team-container">
                    <div class="team-member">
                        <div class="member-image">
                            <i class="fas fa-user-tie"></i>
                        </div>
                        <div class="member-info">
                            <h4>Marco Rodriguez</h4>
                            <p class="member-role">Executive Chef & Owner</p>
                            <p class="member-description">With over 15 years of culinary experience, Marco brings his passion for innovative cuisine and commitment to excellence to every dish served at Feliciano.</p>
                        </div>
                    </div>
                    
                    <div class="team-member">
                        <div class="member-image">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="member-info">
                            <h4>Sarah Johnson</h4>
                            <p class="member-role">Head Pastry Chef</p>
                            <p class="member-description">Sarah's artistic approach to desserts and pastries has earned recognition from food critics and delighted countless guests with her creative confections.</p>
                        </div>
                    </div>
                    
                    <div class="team-member">
                        <div class="member-image">
                            <i class="fas fa-user-friends"></i>
                        </div>
                        <div class="member-info">
                            <h4>David Chen</h4>
                            <p class="member-role">Restaurant Manager</p>
                            <p class="member-description">David ensures every guest receives exceptional service, overseeing our front-of-house operations with attention to detail and genuine hospitality.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Awards & Recognition Section -->
        <section class="page-section awards-section">
            <div class="container">
                <div class="section-title">
                    <h2>Awards & <span>Recognition</span></h2>
                    <p>Celebrating our achievements and commitment to excellence</p>
                </div>
                
                <div class="awards-container">
                    <div class="award-item">
                        <div class="award-icon">
                            <i class="fas fa-trophy"></i>
                        </div>
                        <h4>Best Restaurant 2023</h4>
                        <p>City Food & Wine Magazine</p>
                    </div>
                    
                    <div class="award-item">
                        <div class="award-icon">
                            <i class="fas fa-medal"></i>
                        </div>
                        <h4>Excellence in Service</h4>
                        <p>Hospitality Awards 2022</p>
                    </div>
                    
                    <div class="award-item">
                        <div class="award-icon">
                            <i class="fas fa-star"></i>
                        </div>
                        <h4>4.8/5 Rating</h4>
                        <p>Based on 500+ Reviews</p>
                    </div>
                    
                    <div class="award-item">
                        <div class="award-icon">
                            <i class="fas fa-leaf"></i>
                        </div>
                        <h4>Sustainability Leader</h4>
                        <p>Green Restaurant Certification</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Our Commitment Section -->
        <section class="page-section commitment-section">
            <div class="container">
                <div class="commitment-container">
                    <div class="commitment-content">
                        <h3>Our <span>Commitment</span></h3>
                        <div class="commitment-list">
                            <div class="commitment-item">
                                <i class="fas fa-seedling"></i>
                                <div>
                                    <h4>Sustainability</h4>
                                    <p>We source ingredients locally and implement eco-friendly practices to minimize our environmental impact.</p>
                                </div>
                            </div>
                            
                            <div class="commitment-item">
                                <i class="fas fa-handshake"></i>
                                <div>
                                    <h4>Community</h4>
                                    <p>Supporting local farmers, suppliers, and community initiatives is at the heart of our business philosophy.</p>
                                </div>
                            </div>
                            
                            <div class="commitment-item">
                                <i class="fas fa-shield-alt"></i>
                                <div>
                                    <h4>Quality Assurance</h4>
                                    <p>Every ingredient is carefully selected and every dish is prepared to meet our highest standards of quality and safety.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Call to Action Section -->
        <section class="page-section cta-section">
            <div class="container">
                <div class="cta-content">
                    <h3>Experience <span><?php echo htmlspecialchars($settings['restaurant_name']); ?></span> Today</h3>
                    <p>Join us for an unforgettable dining experience where every meal is a celebration of flavor, quality, and hospitality.</p>
                    <div class="cta-buttons">
                        <a href="menu.php" class="btn btn-primary">View Our Menu</a>
                        <a href="contact.php" class="btn btn-secondary">Make a Reservation</a>
                    </div>
                </div>
            </div>
        </section>

        <div class="page-nav">
            <a href="../index.php" class="page-link">← Back to Home</a>
            <a href="menu.php" class="page-link">View Menu →</a>
        </div>
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
                        <?php if (!empty($settings['social_youtube'])): ?><a href="<?php echo htmlspecialchars($settings['social_youtube']); ?>" target="_blank"><i class="fab fa-youtube"></i></a><?php endif; ?>
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
                <p>&copy; <?php echo date('Y'); ?> <?php echo htmlspecialchars($settings['restaurant_name']); ?>. All rights reserved. | Designed with passion for fine dining</p>
            </div>
        </div>
    </footer>

    <button id="backToTop" class="back-to-top" title="Back to top">↑</button>
    <script src="../assets/js/script.js"></script>
</body>

</html>