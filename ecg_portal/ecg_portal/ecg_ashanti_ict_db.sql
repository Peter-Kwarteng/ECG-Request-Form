-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Sep 22, 2026 at 05:51 PM
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
-- Database: `ecg_ashanti_ict_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `admin_users`
--

CREATE TABLE `admin_users` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'Administrator',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `admin_users`
--

INSERT INTO `admin_users` (`id`, `username`, `password_hash`, `role`, `created_at`) VALUES
(1, 'Peter', '$2y$10$q.jvyfzp3q8sbYBd.nj/WOYdzN.DarDpEIN5wRwc4KoJPqcpgeALu', 'Administrator', '2026-09-22 14:03:58');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(10) UNSIGNED NOT NULL,
  `action` text DEFAULT NULL,
  `admin_user` varchar(100) DEFAULT NULL,
  `action_performed` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `action`, `admin_user`, `action_performed`, `ip_address`, `created_at`) VALUES
(1, 'Administrator logged out: Mr. Martin', NULL, NULL, NULL, '2026-09-22 14:03:16'),
(2, 'New administrator registered: Peter', NULL, NULL, NULL, '2026-09-22 14:03:58'),
(3, 'Updated assessment and status for request ID #1', 'Peter', 'Updated assessment and status for request ID #1', '::1', '2026-09-22 14:06:12'),
(4, 'Administrator logged out: Peter', NULL, NULL, NULL, '2026-09-22 14:13:47'),
(5, 'Updated assessment and status for request ID #2', 'Peter', 'Updated assessment and status for request ID #2', '::1', '2026-09-22 14:28:16'),
(6, 'Updated assessment and status for request ID #3', 'Peter', 'Updated assessment and status for request ID #3', '::1', '2026-09-22 15:14:52'),
(7, 'Deleted request record ID #4', 'Peter', 'Deleted request record ID #4', '::1', '2026-09-22 15:49:55'),
(8, 'Administrator logged out: Peter', NULL, NULL, NULL, '2026-09-22 15:50:04');

-- --------------------------------------------------------

--
-- Table structure for table `requests`
--

CREATE TABLE `requests` (
  `id` int(10) UNSIGNED NOT NULL,
  `name_of_staff` varchar(255) NOT NULL,
  `staff_no` varchar(50) NOT NULL,
  `department` varchar(255) NOT NULL,
  `mobile_contact` varchar(50) NOT NULL,
  `request_type` varchar(255) NOT NULL,
  `further_details` text NOT NULL,
  `job_title_rank` varchar(100) DEFAULT NULL,
  `requester_signature` varchar(100) DEFAULT NULL,
  `date_submitted` date NOT NULL,
  `receiving_ict_officer` varchar(255) DEFAULT NULL,
  `date_received` date DEFAULT NULL,
  `admin_updated_at` datetime DEFAULT NULL,
  `details_of_assessment` text DEFAULT NULL,
  `status_of_complaint` varchar(255) NOT NULL DEFAULT 'Resolution in progress',
  `status_comments` text DEFAULT NULL,
  `supervisor_name` varchar(255) DEFAULT NULL,
  `supervisor_signature` varchar(255) DEFAULT NULL,
  `supervisor_date` date DEFAULT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `attachment_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `requests`
--

INSERT INTO `requests` (`id`, `name_of_staff`, `staff_no`, `department`, `mobile_contact`, `request_type`, `further_details`, `job_title_rank`, `requester_signature`, `date_submitted`, `receiving_ict_officer`, `date_received`, `admin_updated_at`, `details_of_assessment`, `status_of_complaint`, `status_comments`, `supervisor_name`, `supervisor_signature`, `supervisor_date`, `is_read`, `created_at`, `attachment_path`) VALUES
(1, 'CALEB KWARTENG MANTEY', '20264444', 'Billing & Revenue 22', '0541922160', 'ISP/Examination/Result', 'urgently', 'HR ', 'hr', '2026-09-22', 'MR. Mike', '2026-09-22', '2026-09-22 14:06:12', 'sjvjhvyh', 'Resolved and closed', 'Recieved pending confirmation', 'Mr. Quaye', 'MRQ', '2026-09-22', 1, '2026-09-22 14:05:21', NULL),
(2, 'Mr. Martin', '20261234', 'Billing & Revenue 22', '0533525973', 'ISP/Examination/Result', 'dfgfg', 'HR ', 'CKM', '2026-09-22', 'MR. Mike', '2026-09-22', '2026-09-22 14:28:16', 'ssasas', 'Pending (in wait of input/support)', 'Recieved pending confirmation', 'Mr. Quaye', 'MRQ', '2026-09-22', 1, '2026-09-22 14:07:59', 'uploads/1790086079_c69ff43481005a19.jpg'),
(3, 'CALEB KWARTENG MANTEY', '20261234', 'Registry', '0533525973', 'ISP/Examination/Result', 'hhh', 'PRO', 'CKM', '2026-09-22', 'MR. Mikee', '2026-09-22', '2026-09-22 15:14:52', 'j', 'Resolution in progress', '', 'Mr. Quayee', 'MRQq', NULL, 1, '2026-09-22 15:14:11', NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `admin_users`
--
ALTER TABLE `admin_users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_admin_users_username` (`username`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_audit_logs_created_at` (`created_at`),
  ADD KEY `idx_audit_logs_admin_user` (`admin_user`);

--
-- Indexes for table `requests`
--
ALTER TABLE `requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_requests_staff_no` (`staff_no`),
  ADD KEY `idx_requests_department` (`department`),
  ADD KEY `idx_requests_status` (`status_of_complaint`),
  ADD KEY `idx_requests_date_submitted` (`date_submitted`),
  ADD KEY `idx_requests_is_read` (`is_read`),
  ADD KEY `idx_requests_admin_updated_at` (`admin_updated_at`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `admin_users`
--
ALTER TABLE `admin_users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `requests`
--
ALTER TABLE `requests`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
