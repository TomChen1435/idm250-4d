-- phpMyAdmin SQL Dump
-- version 5.1.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:8889
-- Generation Time: Feb 27, 2026 at 01:33 AM
-- Server version: 5.7.24
-- PHP Version: 8.3.1

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `4d_wms`
--

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(11) NOT NULL,
  `sku` varchar(50) NOT NULL,
  `quantity_available` int(11) DEFAULT '0',
  `quantity_reserved` int(11) DEFAULT '0',
  `last_updated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(11) NOT NULL,
  `order_number` varchar(100) NOT NULL,
  `customer_name` varchar(255) DEFAULT NULL,
  `total_items` int(11) DEFAULT '0',
  `address` text,
  `time_created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `time_shipped` timestamp NULL DEFAULT NULL,
  `status` enum('pending','processing','shipped','cancelled') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `order_items`
--

CREATE TABLE `order_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `sku` varchar(50) NOT NULL,
  `ordered` int(11) DEFAULT '0',
  `shipped` int(11) DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `packing_list`
--

CREATE TABLE `packing_list` (
  `id` int(11) NOT NULL,
  `mpl_number` varchar(100) NOT NULL,
  `status` enum('pending','confirmed','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `confirmed_at` timestamp NULL DEFAULT NULL,
  `confirmed_by_user_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `packing_list`
--

INSERT INTO `packing_list` (`id`, `mpl_number`, `status`, `created_at`, `confirmed_at`, `confirmed_by_user_id`) VALUES
(16, 'MPL-TEST-001', 'pending', '2026-02-26 21:01:47', NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `packing_list_items`
--

CREATE TABLE `packing_list_items` (
  `id` int(11) NOT NULL,
  `mpl_id` int(11) NOT NULL,
  `sku` varchar(50) NOT NULL,
  `quantity_expected` int(11) NOT NULL,
  `quantity_received` int(11) DEFAULT '0',
  `status` enum('pending','received','partial') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `packing_list_items`
--

INSERT INTO `packing_list_items` (`id`, `mpl_id`, `sku`, `quantity_expected`, `quantity_received`, `status`, `created_at`) VALUES
(1, 16, '16', 100, 0, 'pending', '2026-02-26 21:01:47');

-- --------------------------------------------------------

--
-- Table structure for table `shipped_items`
--

CREATE TABLE `shipped_items` (
  `id` int(11) NOT NULL,
  `order_id` int(11) NOT NULL,
  `order_number` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sku` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int(11) NOT NULL,
  `customer_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `shipped_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sku`
--

CREATE TABLE `sku` (
  `id` int(11) NOT NULL,
  `ficha` int(11) DEFAULT NULL,
  `sku` varchar(50) NOT NULL,
  `description` text,
  `uom` varchar(20) DEFAULT NULL,
  `pieces` int(11) DEFAULT NULL,
  `length` varchar(50) DEFAULT NULL,
  `width` varchar(50) DEFAULT NULL,
  `height` varchar(50) DEFAULT NULL,
  `weight` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `sku`
--

INSERT INTO `sku` (`id`, `ficha`, `sku`, `description`, `uom`, `pieces`, `length`, `width`, `height`, `weight`) VALUES
(1, 452, '1720823-0567', 'BIRCH YEL FAS 6/4 RGH KD 10FT', 'PALLET', 95, '120', '44', '34', '3120.00'),
(2, 163, '1720824-0891', 'HEMLOCK DIM 2X8X14FT #2BTR STD', 'BUNDLE', 160, '168', '40', '29', '2975.00'),
(3, 589, '1720825-0234', 'ASH WHT FAS 4/4 RGH KD 9-11FT', 'PALLET', 110, '132', '46', '40', '3541.00'),
(4, 734, '1720826-0412', 'MDF ULTRALT C1-- 2440X1220X18MM', 'BUNDLE', 85, '96', '48', '52', '4251.00'),
(5, 298, '1720827-0178', 'CHERRY BLK SEL 5/4 RGH KD 8FT', 'PALLET', 70, '96', '42', '26', '1980.00'),
(6, 641, '1720828-0923', 'REDWOOD CLR VG 2X4X10FT KD HRT', 'BUNDLE', 225, '120', '38', '32', '2431.00'),
(7, 812, '1720829-0056', 'PARTICLEBOARD IND 3/4X49X97', 'PALLET', 60, '97', '49', '45', '3890.00'),
(8, 445, '1720830-0789', 'ALDER RED SEL 4/4 RGH KD 8-10FT', 'BUNDLE', 140, '120', '40', '30', '2181.00'),
(9, 127, '1720831-0345', 'WHITE OAK QS 4/4 RGH KD 10FT', 'PALLET', 65, '120', '48', '38', '2891.00'),
(10, 568, '1720832-0612', 'SOUTHERN PINE PT 4X4X12FT GC', 'BUNDLE', 130, '144', '44', '48', '5120.00'),
(11, 821, '1720860-0528', 'ASH 4/4 FAS KD 9FT', 'PALLET', 125, '108', '48', '38', '3400.00'),
(12, 822, '1720860-0529', 'ASH 4/4 FAS KD 9FT', 'PALLET', 126, '144', '48', '40', '4000.00'),
(13, 823, '1720860-0530', 'ASH 4/4 FAS KD 9FT', 'PALLET', 127, '120', '36', '30', '2400.00'),
(16, 12345, 'TEST-SKU-001', 'Test Product', '0', 50, '48', '40', '50', '1200.00');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `email`, `password`, `last_login`, `created_at`) VALUES
(1, 'enoch', 'et556@drexel.edu', '$2y$10$d.A4x7MRcJ9DDbj0CmLlKeMgZGW0AjAuxlW9QctPH0FeKg6KM0.c.', '2026-02-25 04:00:53', '2026-02-25 08:54:21'),
(5, 'admin', 'admin@wms.com', '$2y$10$QYYg6BNTNpNYxF3BbVLuceaTOBA2wyhIttNeLDcwsorIyM3fxEiI2', '2026-02-25 13:56:17', '2026-02-25 09:19:55');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_sku` (`sku`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `idx_order_number` (`order_number`),
  ADD KEY `idx_time_created` (`time_created`);

--
-- Indexes for table `order_items`
--
ALTER TABLE `order_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_sku` (`sku`);

--
-- Indexes for table `packing_list`
--
ALTER TABLE `packing_list`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `mpl_number` (`mpl_number`),
  ADD KEY `idx_mpl_number` (`mpl_number`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `packing_list_items`
--
ALTER TABLE `packing_list_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mpl_id` (`mpl_id`);

--
-- Indexes for table `shipped_items`
--
ALTER TABLE `shipped_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_order_id` (`order_id`),
  ADD KEY `idx_sku` (`sku`),
  ADD KEY `idx_shipped_at` (`shipped_at`);

--
-- Indexes for table `sku`
--
ALTER TABLE `sku`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `sku` (`sku`),
  ADD UNIQUE KEY `ficha` (`ficha`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `order_items`
--
ALTER TABLE `order_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `packing_list`
--
ALTER TABLE `packing_list`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `packing_list_items`
--
ALTER TABLE `packing_list_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `shipped_items`
--
ALTER TABLE `shipped_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sku`
--
ALTER TABLE `sku`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `inventory`
--
ALTER TABLE `inventory`
  ADD CONSTRAINT `inventory_ibfk_1` FOREIGN KEY (`sku`) REFERENCES `sku` (`sku`);

--
-- Constraints for table `order_items`
--
ALTER TABLE `order_items`
  ADD CONSTRAINT `order_items_ibfk_1` FOREIGN KEY (`order_id`) REFERENCES `orders` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `order_items_ibfk_2` FOREIGN KEY (`sku`) REFERENCES `sku` (`sku`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
