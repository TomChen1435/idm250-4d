-- phpMyAdmin SQL Dump
-- version 5.1.1deb5ubuntu1
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Mar 05, 2026 at 02:22 AM
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
-- Table structure for table `sku`
--

CREATE TABLE `sku` (
  `id` int(11) NOT NULL,
  `ficha` int(11) DEFAULT NULL,
  `sku` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `uom` varchar(20) DEFAULT NULL,
  `pieces` int(11) DEFAULT NULL,
  `length` varchar(50) DEFAULT NULL,
  `width` varchar(50) DEFAULT NULL,
  `height` varchar(50) DEFAULT NULL,
  `weight` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_general_ci;

--
-- Dumping data for table `sku`
--

INSERT INTO `sku` (`id`, `ficha`, `sku`, `description`, `uom`, `pieces`, `length`, `width`, `height`, `weight`) VALUES
(1, 452, '1720823-0567', 'BIRCH YEL FAS 6/4 RGH KD 10FT', 'PALLET', 95, '120', '44', '34', '3120.00'),
(2, 163, '1720824-0891', 'HEMLOCK DIM 2X8X14FT #2BTR STD', 'BUNDLE', 160, '168', '40', '29', '2975.00'),
(3, 0, '1720825-0234', 'ASH WHT FAS 4/4 RGH KD 9-11FT', '110', 132, '46', '40', '3541', '0.00'),
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
(26, 641, '1720828-0923', 'REDWOOD CLR VG 2X4X10FT KD HRT', 'BUNDLE', 225, '120', '38', '32', '2431.00'),
(27, 662, '1720862-0556', 'CEDAR WRC 2X6X10FT CLR', 'BUNDLE', 160, '120', '36', '30', '2400.00'),
(28, 731, '1720857-0486', 'BIRCH PLY 3/4X5X5', 'PALLET', 42, '60', '60', '48', '2950.00'),
(29, 734, '1720826-0412', 'MDF ULTRALT C1-- 2440X1220X18MM', 'BUNDLE', 85, '96', '48', '52', '4251.00'),
(30, 812, '1720829-0056', 'PARTICLEBOARD IND 3/4X49X97', 'PALLET', 60, '97', '49', '45', '3890.00'),
(31, 821, '1720860-0528', 'ASH 4/4 FAS KD 9FT', 'PALLET', 125, '108', '48', '38', '3400.00'),
(32, 943, '1720855-0458', 'DOUGLAS FIR 2X8X14FT #2', 'BUNDLE', 105, '168', '48', '40', '4200.00'),
(33, 944, '1720859-0514', 'PINE #2 2X12X10FT KD', 'BUNDLE', 90, '120', '48', '44', '3800.00');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `sku`
--
ALTER TABLE `sku`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `sku`
--
ALTER TABLE `sku`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
