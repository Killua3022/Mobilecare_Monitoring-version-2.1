-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Feb 17, 2026 at 03:31 AM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.0.28

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `Mobilecare_Monitoring`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `module` varchar(50) NOT NULL,
  `action` text NOT NULL,
  `site` varchar(50) DEFAULT NULL,
  `activity` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `target_table` varchar(50) DEFAULT NULL,
  `target_id` int(11) DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `module`, `action`, `site`, `activity`, `created_at`, `target_table`, `target_id`, `deleted_at`) VALUES
(175, 4, 'Escalation', 'Added escalation: AR=sample only, Serial=EXAMPLE ONLY, Type=Normal', 'The Podium', NULL, '2026-02-15 02:07:57', NULL, NULL, NULL),
(176, 4, 'Escalation', 'Added escalation: AR=ito, Serial=2, Type=Normal', 'The Podium', NULL, '2026-02-15 02:26:29', NULL, NULL, NULL),
(177, 4, 'Escalation', 'Added escalation: AR=ito ang example ko, Serial=ganda, Type=Normal', 'The Podium', NULL, '2026-02-15 02:29:53', NULL, NULL, NULL),
(178, 32, 'Escalation', 'Updated escalation ID 49: AR=ito ang example ko, Serial=ganda, Type=Normal', 'The Podium', NULL, '2026-02-15 02:31:37', NULL, NULL, NULL),
(179, 32, 'Escalation', 'Added escalation: AR=ito ang example ko, Serial=ganda, Type=Reso', 'The Podium', NULL, '2026-02-15 02:31:47', NULL, NULL, NULL),
(180, 1, 'Escalation', 'Updated escalation ID 50: AR=ito ang example ko, Serial=ganda, Type=Normal', 'The Podium', NULL, '2026-02-15 02:32:28', NULL, NULL, NULL),
(181, 1, 'Escalation', 'Updated escalation ID 50: AR=ito ang example ko, Serial=ganda, Type=Normal', 'The Podium', NULL, '2026-02-15 02:33:30', NULL, NULL, NULL),
(182, 1, 'Escalation', 'Hard deleted escalation ID 48: AR=ito, Serial=2, Type=Normal', 'The Podium', NULL, '2026-02-15 02:33:48', NULL, NULL, NULL),
(183, 1, 'Escalation', 'Hard deleted escalation ID 47: AR=sample only, Serial=EXAMPLE ONLY, Type=Normal', 'The Podium', NULL, '2026-02-15 02:33:51', NULL, NULL, NULL),
(184, 32, 'Escalation', 'Added escalation: AR=ito ang example ko, Serial=ganda, Type=Reso', 'The Podium', NULL, '2026-02-15 02:34:06', NULL, NULL, NULL),
(185, 1, 'Escalation', 'Added escalation: AR=ito ang example ko, Serial=ganda, Type=Reso', 'The Podium', NULL, '2026-02-15 02:37:27', NULL, NULL, NULL),
(186, 1, 'Escalation', 'Added escalation: AR=ito ang example ko, Serial=ganda, Type=Reso', 'The Podium', NULL, '2026-02-15 02:37:53', NULL, NULL, NULL),
(187, 1, 'Escalation', 'Hard deleted escalation ID 53: AR=ito ang example ko, Serial=ganda, Type=Reso', 'Marikina', NULL, '2026-02-15 02:38:31', NULL, NULL, NULL),
(188, 1, 'Escalation', 'Hard deleted escalation ID 52: AR=ito ang example ko, Serial=ganda, Type=Reso', 'Marikina', NULL, '2026-02-15 02:38:32', NULL, NULL, NULL),
(189, 1, 'Escalation', 'Updated escalation ID 51: AR=ito ang example ko, Serial=ganda, Type=Normal', 'The Podium', NULL, '2026-02-15 02:38:42', NULL, NULL, NULL),
(190, 1, 'Escalation', 'Updated escalation ID 51: AR=ito ang example ko, Serial=ganda, Type=Normal', 'The Podium', NULL, '2026-02-15 02:43:20', NULL, NULL, NULL),
(191, 1, 'Escalation', 'Updated escalation ID 51: AR=ito ang example ko, Serial=ganda, Type=Normal', 'The Podium', NULL, '2026-02-15 02:43:28', NULL, NULL, NULL),
(192, 32, 'Escalation', 'Added escalation: AR=ito ang example ko, Serial=ganda, Type=Reso', 'The Podium', NULL, '2026-02-15 02:43:52', NULL, NULL, NULL),
(193, 1, 'Escalation', 'Updated escalation ID 54: AR=ito ang example ko, Serial=ganda, Type=Normal', 'The Podium', NULL, '2026-02-15 02:44:09', NULL, NULL, NULL),
(194, 1, 'Escalation', 'Updated escalation ID 54: AR=ito ang example ko, Serial=ganda, Type=Normal', 'The Podium', NULL, '2026-02-15 02:44:18', NULL, NULL, NULL),
(195, 1, 'Escalation', 'Updated escalation ID 54: AR=ito ang example ko, Serial=ganda, Type=Normal', 'The Podium', NULL, '2026-02-15 02:44:28', NULL, NULL, NULL),
(196, 1, 'Escalation', 'Hard deleted escalation ID 54: AR=ito ang example ko, Serial=ganda, Type=Normal', 'The Podium', NULL, '2026-02-15 02:44:35', NULL, NULL, NULL),
(197, 32, 'Escalation', 'Added escalation: AR=ito ang example ko, Serial=ganda, Type=Reso', 'The Podium', NULL, '2026-02-15 02:44:47', NULL, NULL, NULL),
(198, 1, 'Escalation', 'Updated escalation ID 55: AR=ito ang example ko, Serial=ganda, Type=Normal', 'The Podium', NULL, '2026-02-15 02:45:12', NULL, NULL, NULL),
(199, 32, 'Escalation', 'Added escalation: AR=ito ang example ko, Serial=ganda, Type=Reso', 'The Podium', NULL, '2026-02-15 02:46:18', NULL, NULL, NULL),
(200, 1, 'Escalation', 'Updated escalation ID 56: AR=ito ang example ko, Serial=ganda, Type=Normal', 'The Podium', NULL, '2026-02-15 02:46:35', NULL, NULL, NULL),
(201, 1, 'Escalation', 'Hard deleted escalation ID 56: AR=ito ang example ko, Serial=ganda, Type=Normal', 'The Podium', NULL, '2026-02-15 02:50:29', NULL, NULL, NULL),
(202, 32, 'Escalation', 'Added escalation: AR=ito ang example ko, Serial=ganda, Type=Reso', 'The Podium', NULL, '2026-02-15 02:50:42', NULL, NULL, NULL),
(203, 1, 'Escalation', 'Updated escalation ID 57: AR=ito ang example ko, Serial=ganda, Type=Reso', 'The Podium', NULL, '2026-02-15 02:50:57', NULL, NULL, NULL),
(204, 1, 'Escalation', 'Updated escalation ID 57: AR=ito ang example ko, Serial=ganda, Type=Reso', 'The Podium', NULL, '2026-02-15 02:51:03', NULL, NULL, NULL),
(205, 1, 'Escalation', 'Updated escalation ID 57: AR=ito ang example ko, Serial=ganda, Type=Reso', 'The Podium', NULL, '2026-02-15 02:51:08', NULL, NULL, NULL),
(206, 32, 'Escalation', 'Added escalation: AR=ito ang example ko, Serial=ganda, Type=Reso', 'The Podium', NULL, '2026-02-15 02:55:48', NULL, NULL, NULL),
(207, 34, 'Inventory', 'Added inventory: Serial=JASFBWIFBIWDI, Type=Asset, Qty=1', 'The Podium', NULL, '2026-02-15 05:44:02', NULL, NULL, NULL),
(208, 34, 'Escalation', 'Added escalation: AR=720300, Serial=MDFNBjhfh , Type=Normal', 'The Podium', NULL, '2026-02-15 05:45:40', NULL, NULL, NULL),
(209, 32, 'Escalation', 'Updated escalation ID 59: AR=720300, Serial=MDFNBjhfh , Type=Normal', 'The Podium', NULL, '2026-02-15 05:46:10', NULL, NULL, NULL),
(210, 32, 'Escalation', 'Added escalation: AR=720300, Serial=MDFNBjhfh , Type=Reso', 'The Podium', NULL, '2026-02-15 05:46:43', NULL, NULL, NULL),
(211, 34, 'Escalation', 'Soft deleted escalation ID 58', 'The Podium', NULL, '2026-02-15 05:47:11', NULL, NULL, NULL),
(212, 1, 'Inventory', 'Hard deleted inventory ID 4: Serial=JASFBWIFBIWDI, Type=Asset, Qty=1', 'The Podium', NULL, '2026-02-15 05:53:15', NULL, NULL, NULL),
(213, 1, 'Inventory', 'Hard deleted inventory ID 3: Serial=EXAMPLE ONLY, Type=Consumables, Qty=1', 'Marikina', NULL, '2026-02-15 05:53:17', NULL, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `chats`
--

CREATE TABLE `chats` (
  `id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text DEFAULT NULL,
  `file_path` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `status` enum('sent','delivered') DEFAULT 'sent',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `engineer_schedule`
--

CREATE TABLE `engineer_schedule` (
  `id` int(11) NOT NULL,
  `engineer_name` varchar(100) DEFAULT NULL,
  `site` varchar(100) DEFAULT NULL,
  `status` enum('active','vacation','absent') DEFAULT 'active',
  `schedule_date` date DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `engineer_tally_adjustments`
--

CREATE TABLE `engineer_tally_adjustments` (
  `id` int(11) NOT NULL,
  `engineer_id` int(11) NOT NULL,
  `category` enum('iPhone','MacBook','iMac') NOT NULL,
  `count_value` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `escalations`
--

CREATE TABLE `escalations` (
  `id` int(11) NOT NULL,
  `ar_number` varchar(50) DEFAULT NULL,
  `engineer_number` varchar(50) DEFAULT NULL,
  `dispatch_id` varchar(50) DEFAULT NULL,
  `serial_number` varchar(50) DEFAULT NULL,
  `unit_description` varchar(255) DEFAULT NULL,
  `css_response` varchar(255) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `site` varchar(100) NOT NULL,
  `status` varchar(50) DEFAULT 'Open',
  `type` varchar(50) DEFAULT 'Normal',
  `approval_status` varchar(50) DEFAULT 'Pending',
  `approval_by` int(11) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `parent_escalation_id` int(11) DEFAULT NULL,
  `attachment` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `frontline`
--

CREATE TABLE `frontline` (
  `id` int(10) UNSIGNED NOT NULL,
  `site` varchar(100) NOT NULL,
  `start_time` time NOT NULL,
  `end_time` time DEFAULT NULL,
  `aht` varchar(20) DEFAULT NULL,
  `type` varchar(50) DEFAULT NULL,
  `product` varchar(50) DEFAULT NULL,
  `ar` varchar(50) DEFAULT NULL,
  `cso` varchar(100) NOT NULL,
  `serial_number` varchar(50) DEFAULT NULL,
  `is_deleted` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `engineer` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `frontline`
--

INSERT INTO `frontline` (`id`, `site`, `start_time`, `end_time`, `aht`, `type`, `product`, `ar`, `cso`, `serial_number`, `is_deleted`, `created_at`, `updated_at`, `engineer`) VALUES
(23, 'Marikina', '15:20:19', '15:20:24', '0.08333333333333333', 'RECEIVED (WALK-IN)', 'PORTABLE', '1', 'Super Admin', '1', 0, '2026-02-16 07:20:19', '2026-02-16 07:20:24', NULL),
(24, 'The Podium', '17:37:28', '17:37:35', '0.11666666666666667', 'RECEIVED (APPOINTMENT)', 'DESKTOP', '1', 'Alexander Losaynon', '1', 0, '2026-02-16 09:37:28', '2026-02-16 09:38:07', 'Jasmil Rose Guban'),
(25, 'The Podium', '17:58:47', '17:58:57', '0.16666666666666666', 'RELEASED: NTF', 'PORTABLE', '112', 'Alexander Losaynon', '12', 0, '2026-02-16 09:58:47', '2026-02-16 09:58:57', 'Jasmil Rose Guban'),
(26, 'The Podium', '18:00:25', '18:00:38', '0.21666666666666667', 'RELEASED: NRS', 'PORTABLE', '1', 'Alexander Losaynon', '1', 0, '2026-02-16 10:00:25', '2026-02-16 10:00:38', NULL),
(27, 'The Podium', '18:01:03', '18:01:05', '0.03333333333333333', 'RECEIVED (APPOINTMENT)', 'PORTABLE', '1', 'Alexander Losaynon', '1', 0, '2026-02-16 10:01:03', '2026-02-16 10:01:05', NULL),
(28, 'The Podium', '18:01:41', '18:01:45', '0.06666666666666667', 'RECEIVED (WALK-IN)', 'DESKTOP', '1', 'Alexander Losaynon', '1', 0, '2026-02-16 10:01:41', '2026-02-16 10:01:45', 'Jasmil Rose Guban'),
(29, 'The Podium', '18:01:51', '18:01:53', '0.03333333333333333', 'RELEASED: SAF L1 (APPOINTMENT)', 'DESKTOP', '1', 'Alexander Losaynon', '1', 0, '2026-02-16 10:01:51', '2026-02-16 10:01:53', NULL),
(30, 'The Podium', '18:01:59', '18:05:13', '3.2333333333333334', 'RELEASED: PULL OUT', 'PORTABLE', '1', 'Alexander Losaynon', '1', 0, '2026-02-16 10:01:59', '2026-02-16 10:05:13', NULL),
(31, 'The Podium', '18:06:46', '18:06:53', '0.11666666666666667', 'RELEASED: REPLACED', 'SHUFFLE', '1', 'Alexander Losaynon', '1', 0, '2026-02-16 10:06:46', '2026-02-16 10:07:06', NULL),
(32, 'The Podium', '18:07:22', '18:07:30', '0.13333333333333333', 'RECEIVED (APPOINTMENT)', 'MAC ACCS', '123', 'Alexander Losaynon', '123', 0, '2026-02-16 10:07:22', '2026-02-16 10:41:08', 'Jasmil Rose Guban'),
(33, 'The Podium', '18:07:37', '18:07:46', '0.15', 'RELEASED: REPLACED', 'PORTABLE', '1', 'Alexander Losaynon', '2', 0, '2026-02-16 10:07:37', '2026-02-16 10:07:46', NULL),
(34, 'The Podium', '18:41:57', '18:41:58', '0.016666666666666666', 'RECEIVED (APPOINTMENT)', 'PORTABLE', '1', 'Alexander Losaynon', '1', 0, '2026-02-16 10:41:57', '2026-02-16 10:42:16', 'Jasmil Rose Guban'),
(35, 'The Podium', '18:42:04', '18:42:07', '0.05', 'RECEIVED (WALK-IN)', 'DESKTOP', '1', 'Alexander Losaynon', '1', 0, '2026-02-16 10:42:04', '2026-02-16 10:42:07', 'Jasmil Rose Guban'),
(36, 'The Podium', '10:15:10', '10:15:12', '0.03333333333333333', 'RECEIVED (WALK-IN)', 'PORTABLE', '1', 'Alexander Losaynon', '1', 0, '2026-02-17 02:15:10', '2026-02-17 02:15:12', 'sample'),
(37, 'The Podium', '10:15:58', '10:15:59', '0.016666666666666666', 'RECEIVED (WALK-IN)', 'PORTABLE', '1', 'Alexander Losaynon', '1', 0, '2026-02-17 02:15:58', '2026-02-17 02:15:59', 'Jasmil Rose Guban');

-- --------------------------------------------------------

--
-- Table structure for table `inventory`
--

CREATE TABLE `inventory` (
  `id` int(11) NOT NULL,
  `site` varchar(100) DEFAULT NULL,
  `item_type` varchar(50) DEFAULT NULL,
  `ownership` varchar(100) DEFAULT NULL,
  `part_number` varchar(100) DEFAULT NULL,
  `serial_number` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `created_by` int(11) DEFAULT NULL,
  `is_deleted` tinyint(4) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` varchar(255) NOT NULL,
  `link` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `name` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `role` enum('super_admin','admin','user') DEFAULT 'user',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `site` varchar(100) NOT NULL DEFAULT 'Marikina',
  `position` varchar(50) DEFAULT 'Engineer',
  `profile_image` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `status`, `created_at`, `site`, `position`, `profile_image`) VALUES
(1, 'Super Admin', 'superadmin@test.com', '$2y$10$wgtzc7JG5oWsOSV2bJgcveBS.3f87/X.AJp7yn5RjqohSBxW2y/6C', 'super_admin', 'active', '2026-02-10 02:32:36', 'Marikina', 'Manager', 'user_1_1771069145.jpg'),
(4, 'Alexander Losaynon', 'alexander.losaynon@mobilecareph.com', '$2y$10$VPihM4ydo5girHjDaUejCeXOHVLEyMxtFa9/Aq7s0ffU1pMxJrSUa', 'user', 'active', '2026-02-10 03:40:07', 'The Podium', 'Customer Service Officer', 'user_4_1771069500.jpg'),
(9, 'Daphne Bascuguin', 'daphneclaire.bascuguin@mobilecareph.com', '$2y$10$jvQDjuQaNVfg67cs1IlBUezAMExD6Mbn5hgahXrYHLukEmAVc18.6', 'super_admin', 'active', '2026-02-10 19:52:51', 'Northeast Square', 'Manager', NULL),
(10, 'Paul Angelo Revilla', 'paulangelorevilla@mobilecareph.com', '$2y$10$fvjLBHb9wBtTMuaU5S4qUeIUIMigt.wnU4fZYLXhc4XejujsiFM6G', 'admin', 'active', '2026-02-10 19:53:58', 'The Podium', 'Supervisor', NULL),
(11, 'Raul Thomas Cloma', 'raulthomas.cloma@mobilecareph.com', '$2y$10$rWOhFjwoT4Uz/QloRDDFgeca8JbcN1BOu/V1t4X5NYaOlO.mUqWIy', 'admin', 'active', '2026-02-10 21:01:14', 'APP GREENBELT 3', 'Engineer', NULL),
(13, 'John Nitimari Espiritu', 'johnnitimari.espiritu@mobilecareph.com', '$2y$10$aL3lfG3rdICFZoTp3qzPk.4TFKQjuJyg12Pz7a0.uZY0JjKc7diOW', 'admin', 'active', '2026-02-10 21:08:13', 'APP POWER PLANT MALL', 'Engineer', NULL),
(14, 'Cassandra Aila De Gala', 'cassandraaila.degala@mobilecareph.com', '$2y$10$YUahQiUQTGs0IYKpmrzD4OPpHPLk.6mkyGNzhohXNtXfjtLh58PQa', 'admin', 'active', '2026-02-10 21:08:52', 'GLORIETTA 5', 'Engineer', NULL),
(15, 'Patricia Bagares', 'patricia.bagares@mobilecareph.com', '$2y$10$Yn.K/bqMz7FypGevOPGi4eEA2jPUwKmKT.7nf6C1ksND0fCyHNO/S', 'admin', 'active', '2026-02-10 21:09:25', 'APP BONIFACIO HIGH STREET', 'Engineer', NULL),
(16, 'Inah Conta', 'inahmarie.conta@mobilecareph.com', '$2y$10$J1TJdQSeNQs8emuFUOXm0uaLfa.jgoL4Kh3aB342RKnCDSGmILg5C', 'admin', 'active', '2026-02-10 21:10:08', 'LIMKETKAI MALL, CDO', 'Engineer', NULL),
(17, 'Anjo Alcazar', 'anjo.alcazar@mobilecareph.com', '$2y$10$Cg5Nf./lB6ab6usr.GgdXeuLDSbtBEmsYgL4/EfUwJNwjdQ8WT/Ka', 'admin', 'active', '2026-02-10 21:10:39', 'Northeast Square', 'Engineer', NULL),
(18, 'John Francis Madronio', 'john.madronio@mobilecareph.com', '$2y$10$.HWsEk2Z9KiN5W98eLd5Me2Ib7TmiUKhGBnmf/cKasyIE0vYHOjEa', 'admin', 'active', '2026-02-10 21:11:04', 'Marikina', 'Engineer', NULL),
(19, 'Leandro Lee', 'leandro.lee@mobilecareph.com', '$2y$10$uAne9NUA7WLutBbB3a7MRO.Lo7D/x3.gPIwt589YxZEiSPTy0P8N6', 'admin', 'active', '2026-02-10 21:12:14', 'APP MEGA MALL', 'Engineer', NULL),
(20, 'Marc Aaron Litao', 'marcaaron.litao@mobilecareph.com', '$2y$10$GWJcilP8eVjxhWDu6eoP9e.2yHpy7fhWiPVGxnM.qQo7inyzvnp3y', 'admin', 'active', '2026-02-10 21:12:41', 'VERTIS NORTH', 'Engineer', NULL),
(21, 'Randall Esguerra', 'randallchristian.esguerra@mobilecareph.com', '$2y$10$mdJd4m5NREHfDcwTUb/koO/knpnr5BMSKrdZaQw6ew6XejKDMNL8e', 'admin', 'active', '2026-02-10 21:14:28', 'APP SM ANNEX', 'Supervisor', NULL),
(22, 'Edward Russel Vistan', 'edwardrussel.vistan@mobilecareph.com', '$2y$10$j48UNYJbAgSUXLe8CMvzJuj.UULpRCOxLMMMBdGNbAOABNZNC2gEW', 'admin', 'active', '2026-02-10 21:14:58', 'APP ROBINSONS MAGNOLIA', 'Supervisor', NULL),
(23, 'Allelley Suba', 'allelley.suba@mobilecareph.com', '$2y$10$Ey/EuHciPYwg5PD3qV1Y8eyQNK6tmxeIDgepyiC9mfoeW.zOZ5up.', 'admin', 'active', '2026-02-10 21:15:25', 'NEWPOINT MALL', 'Engineer', NULL),
(24, 'Felix Mendioro Jr.', 'felix.mendiorojr@mobilecareph.com', '$2y$10$zIonYLzetlBr.STG6rxPteZQ23g/aJjQlguFvjFifXVJQB1cUtzFa', 'admin', 'active', '2026-02-10 21:15:46', 'ROBINSONS LA UNION', 'Engineer', NULL),
(25, 'Ronald Diaz', 'ronald.diaz@mobilecareph.com', '$2y$10$ES2.2BXNYhzm92GySssgk.r8B9NY7uLyouZoso9620k4Vhv.SDYOi', 'admin', 'active', '2026-02-10 21:16:55', 'KCC MALL, ZAMBOANGA', 'Engineer', NULL),
(26, 'Roshell Vanzuela', 'roshell.saballa@mobilecareph.com', '$2y$10$omR2T8y47tHm2F6m3YRGF.8m60ZsI9ey.P30pZ9O0x5pjuTVOndKe', 'admin', 'active', '2026-02-10 21:17:44', 'ABREEZA MALL, DAVAO', 'Engineer', NULL),
(27, 'Allyn Joyce Aguit', 'allynjoyce.aguit@mobilecareph.com', '$2y$10$C8ktIR1FLtPnqCiFiu8saecEQ5iRr4GKV7heK7Nx8ffgyfqQLnupO', 'admin', 'active', '2026-02-10 21:18:34', 'KCC MALL, COTABATO', 'Engineer', NULL),
(28, 'Keirone Mcilvine Bautista', 'keironemcilvaine.bautista@mobilecareph.com', '$2y$10$jlaRG3I0cfo/Uv9kHmsGRuB2WqnvM7JK4yR6OrgacMkL9NLzIoNPW', 'admin', 'active', '2026-02-10 21:18:57', 'S MAISON', 'Engineer', NULL),
(29, 'Jay Ian Dulay', 'jayian.dulay@mobilecareph.com', '$2y$10$RyaJDG5qQiowkk37uv3/ROnvgZSnKGAxl0LCCn.cM1pO0eledNzwu', 'admin', 'active', '2026-02-10 21:19:27', 'APP MALL OF ASIA', 'Engineer', NULL),
(30, 'Macrine Jane Gaculais', 'macrinejane.gaculais@mobilecareph.com', '$2y$10$pcadRihK0aI/oNHea9mKVO643iIzVIVA/5Jgs2SzIbqH3Z322pjv2', 'admin', 'active', '2026-02-10 21:19:53', 'APP FESTIVAL MALL', 'Engineer', NULL),
(31, 'Sharmaine Espejo', 'sharmaine.espejo@mobilecareph.com', '$2y$10$yj17.Avwf964Qcqnkp4FuugBGLLB4BAufjHAR8akdZ8L/wSuwX/S2', 'admin', 'active', '2026-02-10 21:20:29', 'LIMA ESTATE', 'Engineer', NULL),
(32, 'Alexander Losaynon', 'alexanderlosaynonII@gmail.com', '$2y$10$DpMYtYxfFl.ekBp4EYBXCOqucPXnKQELy.YVvNjSuaKg6XVpC9SKC', 'admin', 'active', '2026-02-15 02:28:05', 'The Podium', 'Engineer', NULL),
(33, 'Jhustine jae', 'JHUSTINE041@GMAIL.COM', '$2y$10$HT2b1hBK42lu9SZunA0OruIqRUqXrdXhavahz/GkM6ZOai.TfAUya', 'admin', 'active', '2026-02-15 04:39:58', 'The Podium', 'Engineer', NULL),
(34, 'Jasmil Rose Guban', 'jasmilrose.guban@mobilecareph.com', '$2y$10$i5Mx6WGYC2cl8msS1104yekWUFaBQcXINcUj8C22WFjaW96u8DJkO', 'user', 'active', '2026-02-15 04:45:46', 'The Podium', 'Engineer', NULL),
(35, 'sample', 'a@gmail.com', '$2y$10$u4KKWkdLpSLSFtxXSNS/tOdgNfMCsoGP2zU/a2R3E2pLuXW54IbwK', 'user', 'active', '2026-02-16 10:01:25', 'The Podium', 'Engineer', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `chats`
--
ALTER TABLE `chats`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Indexes for table `engineer_schedule`
--
ALTER TABLE `engineer_schedule`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `engineer_tally_adjustments`
--
ALTER TABLE `engineer_tally_adjustments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `engineer_id` (`engineer_id`,`category`);

--
-- Indexes for table `escalations`
--
ALTER TABLE `escalations`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `frontline`
--
ALTER TABLE `frontline`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `inventory`
--
ALTER TABLE `inventory`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=214;

--
-- AUTO_INCREMENT for table `chats`
--
ALTER TABLE `chats`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=100;

--
-- AUTO_INCREMENT for table `engineer_schedule`
--
ALTER TABLE `engineer_schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `engineer_tally_adjustments`
--
ALTER TABLE `engineer_tally_adjustments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `escalations`
--
ALTER TABLE `escalations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT for table `frontline`
--
ALTER TABLE `frontline`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `inventory`
--
ALTER TABLE `inventory`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=36;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `chats`
--
ALTER TABLE `chats`
  ADD CONSTRAINT `chats_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `chats_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
