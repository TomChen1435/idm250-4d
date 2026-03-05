-- phpMyAdmin SQL Dump
-- version 5.1.1deb5ubuntu1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Mar 05, 2026 at 02:20 AM
-- Server version: 10.6.23-MariaDB-0ubuntu0.22.04.1
-- PHP Version: 8.1.2-1ubuntu2.23

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `et556_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `packing_list_items`
--

CREATE TABLE `packing_list_items` (
  `id` int(11) NOT NULL,
  `mpl_id` int(11) NOT NULL,
  `unit_id` varchar(50) NOT NULL,
  `sku` varchar(50) NOT NULL,
  `status` enum('pending','received') DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `packing_list_items`
--
ALTER TABLE `packing_list_items`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unit_id` (`unit_id`),
  ADD KEY `mpl_id` (`mpl_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `packing_list_items`
--
ALTER TABLE `packing_list_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `packing_list_items`
--
ALTER TABLE `packing_list_items`
  ADD CONSTRAINT `packing_list_items_ibfk_1` FOREIGN KEY (`mpl_id`) REFERENCES `packing_list` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
