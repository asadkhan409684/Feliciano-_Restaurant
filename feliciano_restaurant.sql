-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Mar 10, 2026 at 07:52 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `feliciano_restaurant`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_notifications`
--

CREATE TABLE `admin_notifications` (
  `id` int(11) NOT NULL,
  `notification_id` bigint(20) DEFAULT NULL,
  `type` enum('order','reservation','system','other') DEFAULT 'order',
  `title` varchar(200) DEFAULT NULL,
  `message` text NOT NULL,
  `related_id` varchar(50) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `admin_notifications`
--

INSERT INTO `admin_notifications` (`id`, `notification_id`, `type`, `title`, `message`, `related_id`, `is_read`, `created_at`) VALUES
(1, NULL, 'order', 'New Order Received', 'Order ORD-20260308-6480 received from Asad Khan (asadkhan409684@gmail.com)', 'ORD-20260308-6480', 0, '2026-03-08 21:05:29'),
(2, NULL, 'order', 'New Order Received', 'Order ORD-20260308-1942 received from Asad Khan (asadkhan409684@gmail.com)', 'ORD-20260308-1942', 0, '2026-03-08 21:09:33'),
(3, NULL, 'order', 'New Order Received', 'Order ORD-20260308-1920 received from Asad Khan (asadkhan409684@gmail.com)', 'ORD-20260308-1920', 0, '2026-03-08 21:10:10'),
(4, NULL, 'order', 'New Order Received', 'Order ORD-20260308-8103 received from Bani Amin ()', 'ORD-20260308-8103', 0, '2026-03-08 21:58:42'),
(5, NULL, 'order', 'New Order Received', 'Order ORD-20260308-4180 received from bani amin (baniamin@gmail.com)', 'ORD-20260308-4180', 0, '2026-03-08 22:05:39');

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `customer_id` varchar(50) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `total_orders` int(11) DEFAULT 0,
  `total_spent` decimal(10,2) DEFAULT 0.00,
  `last_order_date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `customer_analytics`
-- (See below for the actual view)
--
CREATE TABLE `customer_analytics` (
`id` int(11)
,`customer_id` varchar(50)
,`full_name` varchar(100)
,`email` varchar(100)
,`phone` varchar(20)
,`total_orders` bigint(21)
,`total_spent` decimal(32,2)
,`last_order_date` timestamp
,`average_order_value` decimal(14,6)
);

-- --------------------------------------------------------

--
-- Stand-in structure for view `daily_sales_summary`
-- (See below for the actual view)
--
CREATE TABLE `daily_sales_summary` (
`sale_date` date
,`total_orders` bigint(21)
,`total_revenue` decimal(32,2)
,`average_order_value` decimal(14,6)
);

-- --------------------------------------------------------

--
-- Table structure for table `menu_items`
--

CREATE TABLE `menu_items` (
  `id` int(11) NOT NULL,
  `menu_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(50) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `ingredients` text DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `menu_items`
--

INSERT INTO `menu_items` (`id`, `menu_id`, `name`, `description`, `category`, `price`, `image_url`, `ingredients`, `status`, `created_at`, `updated_at`) VALUES
(9, NULL, 'BACON AND EGGS', 'Bacon and eggs is a classic breakfast item that combines the savory crunch of bacon with the creamy richness of eggs', 'breakfast', 100.00, 'assets/images/menu/menu_69add7a3dcff7.jpg', '', 'active', '2026-03-08 20:10:11', '2026-03-08 21:15:33'),
(10, NULL, 'Pancakes with Honey', 'Pancakes with honey are a delightful dish that combines the soft, fluffy texture of pancakes with the natural sweetness of honey. ', 'breakfast', 120.00, 'assets/images/menu/menu_69add88c8b486.jpg', '', 'active', '2026-03-08 20:14:04', '2026-03-08 20:19:39'),
(11, NULL, 'Sausage & Egg Breakfast Plate', 'Cooked sausages are a staple, often browned and seasoned to taste. \r\n', 'breakfast', 150.00, 'assets/images/menu/menu_69add9a9647c1.jpeg', '', 'active', '2026-03-08 20:18:49', '2026-03-08 20:19:30'),
(12, NULL, 'Beef Steak Platter  ', 'A hearty beef steak platter featuring a perfectly seared, tender steak (such as sirloin or ribeye) paired with creamy, buttered mashed.', 'platter', 300.00, 'assets/images/menu/menu_69addb7c552ea.jpg', '', 'active', '2026-03-08 20:26:36', '2026-03-08 20:26:36'),
(13, NULL, 'Chicken BBQ Pizza', 'A Chicken BBQ Pizza features a crispy crust topped with tangy BBQ sauce, grilled chicken breast, mozzarella/gouda cheese, and sliced red onions.', 'pizza', 400.00, 'assets/images/menu/menu_69addcac4d5e3.jpg', '', 'active', '2026-03-08 20:31:40', '2026-03-08 20:31:40'),
(14, NULL, 'Beef Pepperoni Pizza', 'A beef pepperoni pizza is a classic, popular pizza variety featuring a crispy or chewy crust topped with savory, slightly spicy, cured beef slices and melted cheese', 'pizza', 600.00, 'assets/images/menu/menu_69addd8199953.jpg', '', 'active', '2026-03-08 20:35:13', '2026-03-08 20:35:13'),
(15, NULL, 'Mutton Biryani', 'Mutton biryani is a luxurious South Asian rice dish featuring tender, marinated goat or lamb layered with fragrant, long-grain basmati rice and aromatic spices', 'signature', 300.00, 'assets/images/menu/menu_69adde2b3f344.png', '', 'active', '2026-03-08 20:38:03', '2026-03-08 20:38:03'),
(16, NULL, 'Whopper', 'The Burger King Whopper is a signature fast-food burger featuring a 1/4 lb savory flame-grilled beef patty, topped with ', 'burger', 300.00, 'assets/images/menu/menu_69adde96ce213.png', '', 'active', '2026-03-08 20:39:50', '2026-03-08 20:39:50'),
(17, NULL, 'pasta and chowmein', 'Pasta and chowmein are distinct noodle dishes, with pasta traditionally using durum wheat in Italian cuisine, while chowmein consists of stir-fried Chinese noodles', 'pasta & chowmein', 250.00, 'assets/images/menu/menu_69addf3255b63.jpg', '', 'active', '2026-03-08 20:42:26', '2026-03-08 20:42:26');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `order_id` varchar(50) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `customer_email` varchar(100) DEFAULT NULL,
  `customer_phone` varchar(20) DEFAULT NULL,
  `order_type` enum('online','offline','walk-in') NOT NULL,
  `table_number` varchar(20) DEFAULT NULL,
  `delivery_address` text DEFAULT NULL,
  `delivery_time` datetime DEFAULT NULL,
  `special_instructions` text DEFAULT NULL,
  `subtotal` decimal(10,2) NOT NULL,
  `tax` decimal(10,2) DEFAULT 0.00,
  `total_amount` decimal(10,2) NOT NULL,
  `status` enum('pending','preparing','ready','completed','cancelled') DEFAULT 'pending',
  `payment_status` enum('pending','paid','failed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `orders`
--

INSERT INTO `orders` (`id`, `order_id`, `customer_id`, `customer_name`, `customer_email`, `customer_phone`, `order_type`, `table_number`, `delivery_address`, `delivery_time`, `special_instructions`, `subtotal`, `tax`, `total_amount`, `status`, `payment_status`, `created_at`, `updated_at`) VALUES
(4, 'ORD-20260308-6480', NULL, 'Asad Khan', 'asadkhan409684@gmail.com', '01772353298', 'online', NULL, 'kazipara', '2026-03-09 15:05:00', NULL, 700.00, 0.00, 700.00, 'completed', 'pending', '2026-03-08 21:05:29', '2026-03-08 21:38:48'),
(5, 'ORD-20260308-1942', NULL, 'Asad Khan', 'asadkhan409684@gmail.com', '01772353298', 'online', '', 'kazipara', '2026-03-09 15:05:00', '', 700.00, 0.00, 700.00, 'completed', 'pending', '2026-03-08 21:09:33', '2026-03-08 21:15:19'),
(7, 'ORD-20260308-8103', NULL, 'Bani Amin', '', '01234567891', 'offline', '1', '', NULL, 'Make good', 670.00, 0.00, 670.00, 'preparing', 'pending', '2026-03-08 21:58:42', '2026-03-08 22:03:05'),
(8, 'ORD-20260308-4180', NULL, 'bani amin', 'baniamin@gmail.com', '01324567891', 'online', '', 'kazipara', '2026-03-10 16:05:00', 'dfds', 100.00, 0.00, 100.00, 'pending', 'pending', '2026-03-08 22:05:39', '2026-03-08 22:05:39');

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `menu_item_id` int(11) NOT NULL,
  `menu_item_name` varchar(100) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 1,
  `unit_price` decimal(10,2) NOT NULL,
  `total_price` decimal(10,2) NOT NULL,
  `special_instructions` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `order_items`
--

INSERT INTO `order_items` (`id`, `order_id`, `menu_item_id`, `menu_item_name`, `quantity`, `unit_price`, `total_price`, `special_instructions`, `created_at`) VALUES
(2, 4, 13, 'Chicken BBQ Pizza', 1, 400.00, 400.00, NULL, '2026-03-08 21:05:29'),
(3, 4, 12, 'Beef Steak Platter  ', 1, 300.00, 300.00, NULL, '2026-03-08 21:05:29'),
(4, 5, 13, 'Chicken BBQ Pizza', 1, 400.00, 400.00, NULL, '2026-03-08 21:09:33'),
(5, 5, 12, 'Beef Steak Platter  ', 1, 300.00, 300.00, NULL, '2026-03-08 21:09:33'),
(8, 7, 9, 'BACON AND EGGS', 1, 100.00, 100.00, NULL, '2026-03-08 21:58:42'),
(9, 7, 10, 'Pancakes with Honey', 1, 120.00, 120.00, NULL, '2026-03-08 21:58:42'),
(10, 7, 11, 'Sausage & Egg Breakfast Plate', 1, 150.00, 150.00, NULL, '2026-03-08 21:58:42'),
(11, 7, 16, 'Whopper', 1, 300.00, 300.00, NULL, '2026-03-08 21:58:42'),
(12, 8, 9, 'BACON AND EGGS', 1, 100.00, 100.00, NULL, '2026-03-08 22:05:39');

-- --------------------------------------------------------

--
-- Stand-in structure for view `popular_menu_items`
-- (See below for the actual view)
--
CREATE TABLE `popular_menu_items` (
`id` int(11)
,`name` varchar(100)
,`category` varchar(50)
,`total_ordered` decimal(32,0)
,`order_count` bigint(21)
,`revenue_generated` decimal(32,2)
);

-- --------------------------------------------------------

--
-- Table structure for table `reservations`
--

CREATE TABLE `reservations` (
  `id` int(11) NOT NULL,
  `reservation_id` varchar(50) NOT NULL,
  `customer_id` int(11) DEFAULT NULL,
  `customer_name` varchar(100) NOT NULL,
  `customer_email` varchar(100) DEFAULT NULL,
  `customer_phone` varchar(20) NOT NULL,
  `reservation_date` date NOT NULL,
  `reservation_time` time NOT NULL,
  `guests_count` int(11) NOT NULL,
  `occasion` enum('birthday','anniversary','business','date','family','other') DEFAULT 'other',
  `special_requests` text DEFAULT NULL,
  `status` enum('pending','confirmed','cancelled','completed') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `reservations`
--

INSERT INTO `reservations` (`id`, `reservation_id`, `customer_id`, `customer_name`, `customer_email`, `customer_phone`, `reservation_date`, `reservation_time`, `guests_count`, `occasion`, `special_requests`, `status`, `created_at`, `updated_at`) VALUES
(1, 'RES-20260308-4779AA8A', NULL, 'Bani Amin', 'baniamin@gmail.com', '01324567891', '2026-03-10', '20:00:00', 6, 'family', 'fgfd', 'confirmed', '2026-03-08 21:59:34', '2026-03-08 22:02:45');

-- --------------------------------------------------------

--
-- Table structure for table `restaurant_settings`
--

CREATE TABLE `restaurant_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(50) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('string','number','boolean','json','array') DEFAULT 'string',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `restaurant_settings`
--

INSERT INTO `restaurant_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `created_at`, `updated_at`) VALUES
(1, 'restaurant_name', 'Feliciano', 'string', 'Restaurant Name', '2026-03-08 18:08:40', '2026-03-08 18:08:40'),
(2, 'restaurant_address', '123 Gourmet Street, Food City', 'string', 'Restaurant Address', '2026-03-08 18:08:40', '2026-03-08 18:08:40'),
(3, 'restaurant_phone', '+8801772-353298', 'string', 'Restaurant Phone', '2026-03-08 18:08:40', '2026-03-08 18:08:40'),
(4, 'restaurant_email', 'info@feliciano.com', 'string', 'Restaurant Email', '2026-03-08 18:08:40', '2026-03-08 18:08:40'),
(5, 'opening_hours_mon_thu', '11:00 AM - 10:00 PM', 'string', 'Monday-Thursday Hours', '2026-03-08 18:08:40', '2026-03-08 18:08:40'),
(6, 'opening_hours_fri_sat', '11:00 AM - 11:00 PM', 'string', 'Friday-Saturday Hours', '2026-03-08 18:08:40', '2026-03-08 18:08:40'),
(7, 'opening_hours_sun', '12:00 PM - 9:00 PM', 'string', 'Sunday Hours', '2026-03-08 18:08:40', '2026-03-08 18:08:40');

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `customer_name` varchar(100) DEFAULT NULL,
  `rating` tinyint(4) NOT NULL CHECK (`rating` between 1 and 5),
  `comment` text DEFAULT NULL,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `roles`
--

CREATE TABLE `roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `roles`
--

INSERT INTO `roles` (`id`, `role_name`, `description`, `created_at`) VALUES
(1, 'admin', 'Full system access', '2026-03-08 18:08:39'),
(2, 'manager', 'Restaurant operation access', '2026-03-08 18:08:39'),
(3, 'staff', 'Basic operational access', '2026-03-08 18:08:39'),
(4, 'customer', 'Customer access', '2026-03-08 18:08:39');

-- --------------------------------------------------------

--
-- Table structure for table `subscribers`
--

CREATE TABLE `subscribers` (
  `id` int(11) NOT NULL,
  `email` varchar(100) NOT NULL,
  `subscribed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('active','unsubscribed') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `subscribers`
--

INSERT INTO `subscribers` (`id`, `email`, `subscribed_at`, `status`) VALUES
(1, 'baniamin@gmail.com', '2026-03-08 21:52:07', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `tables`
--

CREATE TABLE `tables` (
  `id` int(11) NOT NULL,
  `table_number` varchar(20) NOT NULL,
  `capacity` int(11) NOT NULL,
  `location` varchar(100) DEFAULT NULL,
  `status` enum('available','occupied','reserved','maintenance') DEFAULT 'available',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `first_name` varchar(50) NOT NULL,
  `last_name` varchar(50) NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `role` enum('admin','customer','staff','manager') DEFAULT 'customer',
  `password` varchar(255) NOT NULL,
  `status` enum('active','inactive','suspended') DEFAULT 'active',
  `terms_accepted` tinyint(1) DEFAULT 0,
  `login_attempts` int(11) DEFAULT 0,
  `last_login_attempt` datetime DEFAULT NULL,
  `account_locked` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `first_name`, `last_name`, `full_name`, `email`, `phone`, `role`, `password`, `status`, `terms_accepted`, `login_attempts`, `last_login_attempt`, `account_locked`, `created_at`, `updated_at`) VALUES
(2, 'Asad', 'Khan', 'Asad khan', 'asadkhan405896@gmail.com', '+8801772353298', 'admin', '$2y$10$S3lKpwKhOU6ITx0QlxPE3uQ4C2MEqUQzgLWdg0lvYPqeVrgu8vBLy', 'active', 0, 0, NULL, 0, '2026-03-08 18:18:39', '2026-03-08 18:18:39'),
(3, 'Mosabbir', 'Isalm', 'Mosabbir Isalm', 'mosabbir@gmail.com', NULL, 'manager', '$2y$10$JSY/92BTlwXJFMGzcrK9p.QfKdV/wTjGiRSPgwfGRXrIxwNDKsPK2', 'active', 1, 0, NULL, 0, '2026-03-08 18:19:31', '2026-03-08 19:03:15'),
(4, 'Bani', 'Amin', 'Bani Amin', 'baniamin@gmail.com', '01745623891', 'customer', '$2y$10$2JGARpR9maVU.YgBBdhZ..Ub6TBiCzthcE0bbztrWH1lvYHgy7aga', 'active', 1, 0, NULL, 0, '2026-03-08 21:52:07', '2026-03-08 21:52:07');

-- --------------------------------------------------------

--
-- Table structure for table `user_sessions`
--

CREATE TABLE `user_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `session_token` varchar(255) NOT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `expires_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Structure for view `customer_analytics`
--
DROP TABLE IF EXISTS `customer_analytics`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `customer_analytics`  AS SELECT `c`.`id` AS `id`, `c`.`customer_id` AS `customer_id`, `c`.`full_name` AS `full_name`, `c`.`email` AS `email`, `c`.`phone` AS `phone`, count(`o`.`id`) AS `total_orders`, sum(`o`.`total_amount`) AS `total_spent`, max(`o`.`created_at`) AS `last_order_date`, avg(`o`.`total_amount`) AS `average_order_value` FROM (`customers` `c` left join `orders` `o` on(`c`.`id` = `o`.`customer_id` and `o`.`status` <> 'cancelled')) GROUP BY `c`.`id`, `c`.`customer_id`, `c`.`full_name`, `c`.`email`, `c`.`phone` ;

-- --------------------------------------------------------

--
-- Structure for view `daily_sales_summary`
--
DROP TABLE IF EXISTS `daily_sales_summary`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `daily_sales_summary`  AS SELECT cast(`orders`.`created_at` as date) AS `sale_date`, count(0) AS `total_orders`, sum(`orders`.`total_amount`) AS `total_revenue`, avg(`orders`.`total_amount`) AS `average_order_value` FROM `orders` WHERE `orders`.`status` <> 'cancelled' GROUP BY cast(`orders`.`created_at` as date) ;

-- --------------------------------------------------------

--
-- Structure for view `popular_menu_items`
--
DROP TABLE IF EXISTS `popular_menu_items`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `popular_menu_items`  AS SELECT `mi`.`id` AS `id`, `mi`.`name` AS `name`, `mi`.`category` AS `category`, sum(`oi`.`quantity`) AS `total_ordered`, count(distinct `oi`.`order_id`) AS `order_count`, sum(`oi`.`total_price`) AS `revenue_generated` FROM ((`menu_items` `mi` join `order_items` `oi` on(`mi`.`id` = `oi`.`menu_item_id`)) join `orders` `o` on(`oi`.`order_id` = `o`.`id`)) WHERE `o`.`status` <> 'cancelled' GROUP BY `mi`.`id`, `mi`.`name`, `mi`.`category` ORDER BY sum(`oi`.`quantity`) DESC ;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `notification_id` (`notification_id`),
  ADD KEY `idx_is_read` (`is_read`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `customer_id` (`customer_id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `menu_items`
--
ALTER TABLE `menu_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `menu_id` (`menu_id`),
  ADD KEY `idx_category` (`category`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_id` (`order_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_menu_item_id` (`menu_item_id`);

--
-- Indexes for table `reservations`
--
ALTER TABLE `reservations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reservation_id` (`reservation_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `idx_reservation_id` (`reservation_id`),
  ADD KEY `idx_date` (`reservation_date`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `restaurant_settings`
--
ALTER TABLE `restaurant_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `idx_setting_key` (`setting_key`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `roles`
--
ALTER TABLE `roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- Indexes for table `subscribers`
--
ALTER TABLE `subscribers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`);

--
-- Indexes for table `tables`
--
ALTER TABLE `tables`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `table_number` (`table_number`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_role` (`role`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_token` (`session_token`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_token` (`session_token`),
  ADD KEY `idx_expires` (`expires_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_notifications`
--
ALTER TABLE `admin_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `menu_items`
--
ALTER TABLE `menu_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `reservations`
--
ALTER TABLE `reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `restaurant_settings`
--
ALTER TABLE `restaurant_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `roles`
--
ALTER TABLE `roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `subscribers`
--
ALTER TABLE `subscribers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `tables`
--
ALTER TABLE `tables`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `user_sessions`
--
ALTER TABLE `user_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `customers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `orders_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`menu_item_id`) REFERENCES `menu_items` (`id`);

--
-- Constraints for table `reservations`
--
ALTER TABLE `reservations`
  ADD CONSTRAINT `reservations_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`);

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `user_sessions`
--
ALTER TABLE `user_sessions`
  ADD CONSTRAINT `user_sessions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
