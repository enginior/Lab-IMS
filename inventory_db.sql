-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 11, 2026 at 03:30 PM
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
-- Database: `inventory_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(11) NOT NULL,
  `item_name` varchar(100) NOT NULL,
  `category` enum('Electronic','Computer','Tool','Other') DEFAULT 'Electronic',
  `sub_category` varchar(50) DEFAULT NULL,
  `item_type` enum('Bulk','Unique') NOT NULL DEFAULT 'Bulk',
  `component_rating` varchar(120) DEFAULT NULL,
  `model_number` varchar(100) DEFAULT NULL,
  `serial_number` varchar(120) DEFAULT NULL,
  `condition_status` enum('New','Working','Untested','For Parts') NOT NULL DEFAULT 'Working',
  `image_path` varchar(255) DEFAULT NULL,
  `quantity` int(11) DEFAULT 0,
  `min_quantity` int(11) NOT NULL DEFAULT 2,
  `location` varchar(50) DEFAULT NULL,
  `specifications` text DEFAULT NULL,
  `datasheet_url` varchar(500) DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory`
--

INSERT INTO `inventory` (`id`, `item_name`, `category`, `sub_category`, `image_path`, `quantity`, `location`, `specifications`, `last_updated`, `created_at`) VALUES
(1, 'Multiple Resistor', 'Electronic', 'Resistor', '1768070251_9305.jpg', 3, 'Drawer 1', '1/2 watts', '2026-01-10 18:37:31', '2026-01-11 14:05:41'),
(2, 'Porsche GT3', 'Computer', 'Car', '1768070382_5031.jpg', 1, 'Drawer 2', 'GT3 RS', '2026-01-10 18:39:42', '2026-01-11 14:05:41'),
(6, 'Capacitor', 'Electronic', 'Capacitor', '1768133997_9505.png', 10, 'rack 1', '4700uF, 25V', '2026-01-11 12:19:57', '2026-01-11 14:05:41'),
(7, 'Multimeter', 'Tool', 'Multimeter', '1768134578_3716.png', 1, 'Drawer 1', 'enno Logic', '2026-01-11 12:29:38', '2026-01-11 14:05:41'),
(8, 'CD4017', 'Electronic', 'Integrated Circuit', '1768134697_7880.png', 1, 'Drawer 2', 'CD4017 IC', '2026-01-11 12:31:37', '2026-01-11 14:05:41'),
(9, 'WD 3.5\" HDD', 'Computer', '3.5\" HDD', '1768139139_8049.png', 1, 'Drawer 3', 'Serial: eaEGgafhq113t , Model:1EHRANJ2, Not Used,	UASP (Serial ATA),	5400 RPM', '2026-01-11 13:46:23', '2026-01-11 14:05:41'),
(10, 'AF Corse Ferrari', 'Electronic', 'Ferrari, car', '1768140744_7841.jpg', 1, 'Drawer 2', 'Ferrari 499P', '2026-01-11 14:12:24', '2026-01-11 14:12:24'),
(11, 'DDR5 Ram', 'Computer', 'RAM', '1768141096_1083.png', 2, 'Drawer 2', '16GB DDR5', '2026-01-11 14:18:16', '2026-01-11 14:18:16'),
(12, 'Arduino Uno R3', 'Electronic', 'Microcontroller Unit', '1768141179_5308.png', 1, 'Drawer 3', 'Uno R3', '2026-01-11 14:19:39', '2026-01-11 14:19:39');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('Admin','Operator') NOT NULL DEFAULT 'Admin',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `created_at`) VALUES
(1, 'admin', '$2y$10$gsH97QgZs8Z9zuBaB3Bo0eQw467o8Y6FVeEc4HTVPgCbpVXTl/byC', 'Admin', '2026-01-10 18:36:50');

-- --------------------------------------------------------

--
-- Table structure for table `inventory_logs`
--

CREATE TABLE `inventory_logs` (
  `id` int(11) NOT NULL,
  `item_id` int(11) DEFAULT NULL,
  `item_name` varchar(100) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `change_type` enum('quick_adjust','restock','manual_edit','item_created','item_deleted') NOT NULL,
  `delta` int(11) NOT NULL,
  `old_quantity` int(11) NOT NULL,
  `new_quantity` int(11) NOT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `inventory_logs`
--

INSERT INTO `inventory_logs` (`id`, `item_id`, `item_name`, `user_id`, `username`, `change_type`, `delta`, `old_quantity`, `new_quantity`, `notes`, `created_at`) VALUES
(1, 1, 'Multiple Resistor', 1, 'admin', 'item_created', 3, 0, 3, 'Initial inventory setup', '2026-01-11 14:05:41'),
(2, 6, 'Capacitor', 1, 'admin', 'item_created', 10, 0, 10, 'Initial inventory setup', '2026-01-11 14:05:41'),
(3, 11, 'DDR5 Ram', 1, 'admin', 'item_created', 2, 0, 2, 'Batch receipt from supplier', '2026-01-11 14:18:16');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `item_id` (`item_id`),
  ADD KEY `created_at` (`created_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=33;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `inventory_logs`
--
ALTER TABLE `inventory_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
