<?php
session_start();
require '../config/database.php';

$success = '';
$error   = '';

if (isset($_SESSION['reservation_success'])) {
    $success = $_SESSION['reservation_success'];
    unset($_SESSION['reservation_success']);
}
if (isset($_SESSION['reservation_error'])) {
    $error = $_SESSION['reservation_error'];
    unset($_SESSION['reservation_error']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!isset($_SESSION['user_logged_in']) || !$_SESSION['user_logged_in']) {
        $error = "You must be logged in to make a reservation.";
    } else {
        // Collect & sanitize
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $date     = $_POST['date'] ?? '';
        $time     = $_POST['time'] ?? '';
        $guests   = (int) ($_POST['guests'] ?? 0);
        $occasion = $_POST['occasion'] ?? null;
        $branch_id = !empty($_POST['branch_id']) ? intval($_POST['branch_id']) : null;
        $message  = trim($_POST['message'] ?? '');

        // Basic validation
        if (
            empty($name) || empty($email) || empty($phone) ||
            empty($date) || empty($time) || $guests <= 0
        ) {
            $error = "Please fill in all required fields correctly.";
        } else {

        // Reservation ID
        $reservation_id = 'RES-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));

        // Resolve customer_id if user is logged in
        $customer_id = NULL;
        if (isset($_SESSION['user_id'])) {
            $user_id = $_SESSION['user_id'];
            $cust_stmt = $conn->prepare("SELECT id FROM customers WHERE user_id = ?");
            if ($cust_stmt) {
                $cust_stmt->bind_param("i", $user_id);
                $cust_stmt->execute();
                $cust_res = $cust_stmt->get_result();
                if ($row = $cust_res->fetch_assoc()) {
                    $customer_id = $row['id'];
                }
                $cust_stmt->close();
            }
        }

        $stmt = $conn->prepare("
            INSERT INTO reservations
            (reservation_id, customer_id, customer_name, customer_email, customer_phone, reservation_date, reservation_time, guests_count, occasion, special_requests, branch_id)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->bind_param(
            "sisssssissi",
            $reservation_id,
            $customer_id,
            $name,
            $email,
            $phone,
            $date,
            $time,
            $guests,
            $occasion,
            $message,
            $branch_id
        );

        if ($stmt->execute()) {
            // Add notification for admin
            $notif_title = "New Reservation Received";
            $notif_msg = "Table reservation $reservation_id for $guests guests from $name";
            $notif_sql = "INSERT INTO admin_notifications (type, title, message, related_id, branch_id) VALUES ('reservation', ?, ?, ?, ?)";
            $notif_stmt = $conn->prepare($notif_sql);
            if ($notif_stmt) {
                $notif_stmt->bind_param("sssi", $notif_title, $notif_msg, $reservation_id, $branch_id);
                $notif_stmt->execute();
                $notif_stmt->close();
            }

            $_SESSION['reservation_success'] = "Your table reservation has been successfully confirmed.<br><br>Reservation ID: <strong style='font-size: 1.2rem; color: #fff;'>$reservation_id</strong>";
            header("Location: contact.php");
            exit;
        } else {
            $_SESSION['reservation_error'] = "We encountered a database error while processing your request. Please try again or contact support.";
            header("Location: contact.php");
            exit;
        }

        $stmt->close();
        $conn->close();
    }
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - Feliciano Restaurant</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="../assets/css/contact.css?v=<?php echo time(); ?>">
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

    <!-- Main Content -->
    <main>
        <section class="page-section">
            <div class="container">
                <style>
                @keyframes slideDownFade {
                    0% { opacity: 0; transform: translateY(-20px); }
                    100% { opacity: 1; transform: translateY(0); }
                }
                .elegant-alert {
                    background: #1a1a1a;
                    border: 1px solid #c9a74d;
                    color: #fff;
                    padding: 30px;
                    border-radius: 12px;
                    margin-bottom: 40px;
                    text-align: center;
                    box-shadow: 0 10px 30px rgba(201, 167, 77, 0.15);
                    animation: slideDownFade 0.6s ease-out forwards;
                    transition: opacity 0.5s ease-out, transform 0.5s ease-out;
                    position: relative;
                    overflow: hidden;
                }
                .elegant-alert::before {
                    content: '';
                    position: absolute;
                    top: 0; left: 0; right: 0; height: 3px;
                    background: linear-gradient(90deg, transparent, #c9a74d, transparent);
                }
                .elegant-alert .icon-wrapper {
                    width: 70px; height: 70px;
                    background: rgba(201, 167, 77, 0.1);
                    border-radius: 50%;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    margin-bottom: 15px;
                    border: 1px solid rgba(201, 167, 77, 0.3);
                }
                .elegant-alert-error {
                    background: #1a1a1a;
                    border: 1px solid #dc3545;
                    color: #fff;
                    padding: 30px;
                    border-radius: 12px;
                    margin-bottom: 40px;
                    text-align: center;
                    box-shadow: 0 10px 30px rgba(220, 53, 69, 0.15);
                    animation: slideDownFade 0.6s ease-out forwards;
                    transition: opacity 0.5s ease-out, transform 0.5s ease-out;
                    position: relative;
                }
                .elegant-alert-error::before {
                    content: '';
                    position: absolute;
                    top: 0; left: 0; right: 0; height: 3px;
                    background: linear-gradient(90deg, transparent, #dc3545, transparent);
                }
                .elegant-alert-error .icon-wrapper {
                    width: 70px; height: 70px;
                    background: rgba(220, 53, 69, 0.1);
                    border-radius: 50%;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    margin-bottom: 15px;
                    border: 1px solid rgba(220, 53, 69, 0.3);
                }
                </style>

                <?php if (!empty($success)): ?>
                    <div id="reservationAlert" class="elegant-alert">
                        <div class="icon-wrapper">
                            <i class="fas fa-check" style="font-size: 2rem; color: #c9a74d;"></i>
                        </div>
                        <h3 style="color: #c9a74d; margin-bottom: 15px; font-size: 1.8rem; letter-spacing: 1px;">Reservation Confirmed!</h3>
                        <p style="font-size: 1.1rem; margin: 0; color: #ddd; line-height: 1.6;"><?= $success ?></p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($error)): ?>
                    <div id="reservationAlert" class="elegant-alert-error">
                        <div class="icon-wrapper">
                            <i class="fas fa-exclamation" style="font-size: 2rem; color: #dc3545;"></i>
                        </div>
                        <h3 style="color: #dc3545; margin-bottom: 15px; font-size: 1.8rem; letter-spacing: 1px;">Action Failed</h3>
                        <p style="font-size: 1.1rem; margin: 0; color: #ddd; line-height: 1.6;"><?= $error ?></p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($success) || !empty($error)): ?>
                <script>
                    setTimeout(function() {
                        var alertMsg = document.getElementById('reservationAlert');
                        if (alertMsg) {
                            alertMsg.style.opacity = '0';
                            alertMsg.style.transform = 'translateY(-20px)';
                            setTimeout(function() {
                                alertMsg.style.display = 'none';
                            }, 500); // Wait for transition to finish
                        }
                    }, 2000);
                </script>
                <?php endif; ?>
                
                <div class="section-title">
                    <h2>Contact <span>Us</span></h2>
                    <p>Get in touch with us for reservations, inquiries, or special requests</p>
                </div>
                
                <div class="contact-container">
                    <div class="contact-info">
                        <h3>Visit Our Restaurant</h3>
                        <div class="contact-detail">
                            <i class="fas fa-map-marker-alt"></i>
                            <div>
                                <h4>Address</h4>
                                <p>123 Gourmet Street, Food City, FC 10001</p>
                            </div>
                        </div>
                        
                        <div class="contact-detail">
                            <i class="fas fa-phone"></i>
                            <div>
                                <h4>Phone Number</h4>
                                <p>+8801772-353298</p>
                                <p style="color: #aaa; font-size: 0.9rem; margin-top: 5px;">For reservations, call: +8801772-353299</p>
                            </div>
                        </div>
                        
                        <div class="contact-detail">
                            <i class="fas fa-envelope"></i>
                            <div>
                                <h4>Email Address</h4>
                                <p>info@feliciano.com</p>
                                <p style="color: #aaa; font-size: 0.9rem; margin-top: 5px;">reservations@feliciano.com</p>
                            </div>
                        </div>
                        
                        <div class="contact-detail">
                            <i class="fas fa-clock"></i>
                            <div>
                                <h4>Opening Hours</h4>
                                <p><strong>Monday - Thursday:</strong> 11:00 AM - 10:00 PM</p>
                                <p><strong>Friday - Saturday:</strong> 11:00 AM - 11:00 PM</p>
                                <p><strong>Sunday:</strong> 12:00 PM - 9:00 PM</p>
                            </div>
                        </div>
                        
                        <div class="contact-detail">
                            <i class="fas fa-utensils"></i>
                            <div>
                                <h4>Special Events</h4>
                                <p>We host private events, weddings, and corporate functions. Contact our events team for more information.</p>
                            </div>
                        </div>
                        
                        <div class="social-icons" style="margin-top: 30px;">
                            <a href="https://www.twitter.com" target="_blank"><i class="fab fa-twitter"></i></a>
                            <a href="https://www.instagram.com" target="_blank"><i class="fab fa-instagram"></i></a>
                            <a href="https://www.twitter.com" target="_blank"><i class="fab fa-twitter"></i></a>
                            
                           
                        </div>
                    </div>
                    
                    <div class="contact-form" id="reservation">
                        <h3>Make a Reservation</h3>
                        
                        <?php if(isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in']): ?>
                        <form id="reservationForm" method="POST" action="contact.php">
                            <div class="form-group">
                                <label for="name">Full Name *</label>
                                <input type="text" id="name" name="name" required placeholder="Your full name" value="<?= htmlspecialchars($name ?? '') ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email Address *</label>
                                <input type="email" id="email" name="email" required placeholder="your.email@example.com" value="<?= htmlspecialchars($email ?? '') ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="phone">Phone Number *</label>
                                <input type="tel" id="phone" name="phone" required placeholder="(+880) 1---------" value="<?= htmlspecialchars($phone ?? '') ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="date">Date *</label>
                                <input type="date" id="date" name="date" required value="<?= htmlspecialchars($date ?? '') ?>">
                            </div>

                            <div class="form-group">
                                <label for="branch_id">Select Branch *</label>
                                <select id="branch_id" name="branch_id" required>
                                    <option value="">Select Branch</option>
                                    <?php 
                                    $branches_res = $conn->query("SELECT id, name, location FROM branches ORDER BY name");
                                    if ($branches_res):
                                        while($branch = $branches_res->fetch_assoc()):
                                    ?>
                                        <option value="<?= $branch['id'] ?>" <?= (isset($branch_id) && $branch_id == $branch['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($branch['name']) ?> - <?= htmlspecialchars($branch['location']) ?>
                                        </option>
                                    <?php endwhile; endif; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="time">Time *</label>
                                <select id="time" name="time" required>
                                    <option value="">Select Time</option>
                                    <option value="17:00">5:00 PM</option>
                                    <option value="17:30">5:30 PM</option>
                                    <option value="18:00">6:00 PM</option>
                                    <option value="18:30">6:30 PM</option>
                                    <option value="19:00">7:00 PM</option>
                                    <option value="19:30">7:30 PM</option>
                                    <option value="20:00">8:00 PM</option>
                                    <option value="20:30">8:30 PM</option>
                                    <option value="21:00">9:00 PM</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="guests">Number of Guests *</label>
                                <select id="guests" name="guests" required>
                                    <option value="">Select Guests</option>
                                    <option value="1">1 Person</option>
                                    <option value="2">2 People</option>
                                    <option value="3">3 People</option>
                                    <option value="4">4 People</option>
                                    <option value="5">5 People</option>
                                    <option value="6">6 People</option>
                                    <option value="7">7 People</option>
                                    <option value="8">8 People</option>
                                    <option value="9">9 People</option>
                                    <option value="10">10+ People</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="occasion">Occasion (Optional)</label>
                                <select id="occasion" name="occasion">
                                    <option value="other">Select Occasion</option>
                                    <option value="birthday">Birthday</option>
                                    <option value="anniversary">Anniversary</option>
                                    <option value="business">Business Dinner</option>
                                    <option value="date">Date Night</option>
                                    <option value="family">Family Gathering</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="message">Special Requests</label>
                                <textarea id="message" name="message" placeholder="Any special requests, dietary restrictions, or allergies..."><?= htmlspecialchars($message ?? '') ?></textarea>
                            </div>
                            
                            <button type="submit" name="reserve_submit" class="btn btn-primary" style="width: 100%; padding: 15px; font-size: 1.1rem;">Book Reservation Now</button>
                            
                            <p style="color: #aaa; font-size: 0.9rem; margin-top: 15px; text-align: center;">
                                * Required fields. We'll contact you within 24 hours to confirm your reservation.
                            </p>
                        </form>
                        <?php else: ?>
                            <div style="text-align: center; padding: 50px 20px; border: 1px dashed #c9a74d; border-radius: 8px; margin-top: 20px; background-color: rgba(201, 167, 77, 0.05);">
                                <i class="fas fa-lock" style="font-size: 3rem; color: #c9a74d; margin-bottom: 20px;"></i>
                                <h4 style="margin-bottom: 15px; color: #333; font-size: 1.5rem;">Login Required</h4>
                                <p style="color: #666; margin-bottom: 25px;">You need to be logged into your account to make a table reservation.</p>
                                <a href="../auth/login.php" class="btn btn-primary" style="padding: 12px 30px; font-weight: 600;">Log In to Reserve</a>
                                <p style="margin-top: 15px; font-size: 0.9rem; color: #777;">Don't have an account? <a href="../auth/register.php" style="color: #c9a74d;">Register here</a>.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="map-container">
                    <!-- Map placeholder - in production, embed Google Maps iframe here -->
                    <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: linear-gradient(rgba(0,0,0,0.7), rgba(0,0,0,0.7)), url('https://images.unsplash.com/photo-1506905925346-21bda4d32df4?ixlib=rb-4.0.3&auto=format&fit=crop&w=2070&q=80'); background-size: cover; background-position: center;">
                        <div style="text-align: center; padding: 20px; background-color: rgba(0,0,0,0.8); border-radius: 10px; max-width: 400px;">
                            <i class="fas fa-map-marked-alt" style="font-size: 3rem; color: #c9a74d; margin-bottom: 20px;"></i>
                            <h3 style="color: #c9a74d; margin-bottom: 10px;">Our Location</h3>
                            <p><strong>Feliciano Restaurant</strong></p>
                            <p>123 Gourmet Street<br>Food City, FC 10001</p>
                            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #444;">
                                <p style="color: #aaa; font-size: 0.9rem;">
                                    <i class="fas fa-subway"></i> Nearest subway: Gourmet Station (Line 1, 2)<br>
                                    <i class="fas fa-parking"></i> Valet parking available<br>
                                    <i class="fas fa-wheelchair"></i> Wheelchair accessible
                                </p>
                            </div>
                            <a href="#" class="btn btn-secondary" style="margin-top: 20px; display: inline-block;">
                                <i class="fas fa-directions"></i> Get Directions
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Additional Contact Information -->
                <div style="margin-top: 60px; background-color: #1a1a1a; padding: 40px; border-radius: 10px; border: 1px solid #333;">
                    <div class="section-title" style="margin-bottom: 40px;">
                        <h3>Additional <span>Information</span></h3>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 30px;">
                        <div>
                            <h4 style="color: #c9a74d; margin-bottom: 15px; font-size: 1.3rem;">
                                <i class="fas fa-users"></i> Large Groups & Events
                            </h4>
                            <p style="color: #aaa;">We accommodate large groups and private events. For parties of 10+ or private dining inquiries, please contact our events coordinator at <strong>events@feliciano.com</strong> or call <strong>(+880)1772-353298</strong>.</p>
                        </div>
                        
                        <div>
                            <h4 style="color: #c9a74d; margin-bottom: 15px; font-size: 1.3rem;">
                                <i class="fas fa-utensils"></i> Dietary Restrictions
                            </h4>
                            <p style="color: #aaa;">Our chefs are happy to accommodate dietary restrictions and allergies. Please inform us of any requirements when making your reservation, and our staff will ensure a safe and enjoyable dining experience.</p>
                        </div>
                        
                        <div>
                            <h4 style="color: #c9a74d; margin-bottom: 15px; font-size: 1.3rem;">
                                <i class="fas fa-gift"></i> Gift Cards
                            </h4>
                            <p style="color: #aaa;">Give the gift of fine dining! Feliciano gift cards are available in any amount and can be purchased at the restaurant or online. Perfect for birthdays, anniversaries, or corporate gifts.</p>
                        </div>
                    </div>
                </div>
                
                <div class="page-nav" style="margin-top: 60px;">
                    <a href="menu.php" class="page-link">← View Menu</a>
                    <a href="../index.php" class="page-link">Back to Home →</a>
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
                    <p>Experience culinary excellence at Feliciano, where every dish tells a story of passion, quality, and tradition.</p>
                    <div class="social-icons">
                        <a href="https://www.facebook.com"><i class="fab fa-facebook-f"></i></a>
                        <a href="https://www.instagram.com"><i class="fab fa-instagram"></i></a>
                        <a href="https://www.twitter.com"><i class="fab fa-twitter"></i></a>
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
                <p style="margin-top: 10px; font-size: 0.8rem; color: #666;">
                    <a href="#" style="color: #666; text-decoration: none;">Privacy Policy</a> | 
                    <a href="#" style="color: #666; text-decoration: none;">Terms of Service</a> | 
                    <a href="#" style="color: #666; text-decoration: none;">Accessibility Statement</a>
                </p>
            </div>
        </div>
    </footer>


    <button id="backToTop" class="back-to-top" title="Back to top">↑</button>
    <script src="../assets/js/script.js"></script>
</body>
</html>
