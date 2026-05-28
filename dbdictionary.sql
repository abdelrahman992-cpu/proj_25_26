-- phpMyAdmin SQL Dump
-- version 5.2.1deb3
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: May 20, 2026 at 07:06 AM
-- Server version: 8.0.45-0ubuntu0.24.04.1
-- PHP Version: 8.3.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `dbdictionary`
--

-- --------------------------------------------------------

--
-- Table structure for table `failed_attempts`
--

CREATE TABLE `failed_attempts` (
  `id` int NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `attempt_time` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `failed_attempts`
--

INSERT INTO `failed_attempts` (`id`, `ip_address`, `attempt_time`) VALUES
(1, '127.0.0.1', '2026-05-20 05:14:59'),
(2, '127.0.0.1', '2026-05-20 05:15:13'),
(3, '127.0.0.1', '2026-05-20 05:15:21'),
(4, '127.0.0.1', '2026-05-20 05:15:29'),
(5, '127.0.0.1', '2026-05-20 05:15:37');

-- --------------------------------------------------------

--
-- Table structure for table `sections`
--

CREATE TABLE `sections` (
  `s_id` int NOT NULL,
  `user_id` int NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `deta` text COLLATE utf8mb4_general_ci,
  `last_activity` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `sections`
--

INSERT INTO `sections` (`s_id`, `user_id`, `username`, `deta`, `last_activity`) VALUES
(11, 46, 'rrr', '3c90lbqjh0u1ln53gscihi87nn', '2026-04-04 03:59:13'),
(12, 46, 'rrr', '3c90lbqjh0u1ln53gscihi87nn', '2026-04-04 03:59:34'),
(13, 47, 'ttt', 'crcrulfhfvht40jd393mhptljk', '2026-05-20 05:22:12');

-- --------------------------------------------------------

--
-- Table structure for table `terms`
--

CREATE TABLE `terms` (
  `id` int NOT NULL,
  `term` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `trans` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `defe` varchar(220) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL,
  `picture` varchar(30) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `user_id` int DEFAULT NULL,
  `status` varchar(20) DEFAULT 'pending'
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(30) CHARACTER SET utf8mb3 COLLATE utf8mb3_general_ci NOT NULL,
  `passwor` varchar(255) DEFAULT NULL,
  `role` enum('admin','user') NOT NULL DEFAULT 'user',
  `otp_code` varchar(10) DEFAULT NULL,
  `otp_expire` datetime DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `delete_otp` varchar(6) DEFAULT NULL,
  `delete_expire` datetime DEFAULT NULL,
  `login_attempts` int DEFAULT '0',
  `last_attempt_time` datetime DEFAULT NULL,
  `pending_email` varchar(255) DEFAULT NULL,
  `reset_code` varchar(10) DEFAULT NULL,
  `reset_expire` datetime DEFAULT NULL,
  `pending_phone` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=latin1;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `passwor`, `role`, `otp_code`, `otp_expire`, `email`, `phone`, `delete_otp`, `delete_expire`, `login_attempts`, `last_attempt_time`, `pending_email`, `reset_code`, `reset_expire`, `pending_phone`) VALUES
(46, 'rrr', '$2y$10$UoYACKKJekPSS7QJCHW5mOfYNwjeRubo/GxGNyXmkn68qIAqu7v8m', 'admin', '540520', '2026-04-04 06:04:13', 'abdelrahmandarag25@gmail.com', '+201037284457', '920935', '2026-04-04 06:04:49', 5, '2026-05-20 08:15:37', NULL, NULL, NULL, NULL),
(47, 'aaa', '$2y$10$rvScqFZnm4WaT6Y0SdmXPex4M333wUjMnS0PSURXj3/Cb52xqAtqu', 'user', NULL, NULL, '29211260100998@cis.asu.edu.eg', '+201037284457', NULL, NULL, 0, NULL, NULL, NULL, '2026-05-20 09:14:25', '+201037284457');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `failed_attempts`
--
ALTER TABLE `failed_attempts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `sections`
--
ALTER TABLE `sections`
  ADD PRIMARY KEY (`s_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `terms`
--
ALTER TABLE `terms`
  ADD PRIMARY KEY (`id`),
  ADD KEY `terms_ibfk_1` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `failed_attempts`
--
ALTER TABLE `failed_attempts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `sections`
--
ALTER TABLE `sections`
  MODIFY `s_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `terms`
--
ALTER TABLE `terms`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `sections`
--
ALTER TABLE `sections`
  ADD CONSTRAINT `sections_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `terms`
--
ALTER TABLE `terms`
  ADD CONSTRAINT `terms_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
