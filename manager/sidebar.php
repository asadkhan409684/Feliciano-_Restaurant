<?php
// manager/sidebar.php
?>
<!-- Manager Sidebar -->
<aside class="admin-sidebar">
    <nav class="admin-nav">
        <ul style="padding-left: 0; margin-bottom: 0;">
            <li><a href="index.php" class="nav-link <?= is_active('index.php') ?>">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a></li>
            <li><a href="reservations.php" class="nav-link <?= is_active('reservations.php') ?>">
                <i class="fas fa-calendar-alt"></i> Reservations
            </a></li>
            <li><a href="orders.php" class="nav-link <?= is_active('orders.php') ?>">
                <i class="fas fa-shopping-cart"></i> Orders
            </a></li>
            <li><a href="menu.php" class="nav-link <?= is_active('menu.php') ?>">
                <i class="fas fa-utensils"></i> Menu Update
            </a></li>
            <li><a href="feedback.php" class="nav-link <?= is_active('feedback.php') ?>">
                <i class="fas fa-comments"></i> Customer Feedback
            </a></li>
            <li class="nav-separator"></li>
            <li><a href="../index.php" class="nav-link view-site-link">
                <i class="fas fa-external-link-alt"></i> View Website
            </a></li>
        </ul>
    </nav>
</aside>
