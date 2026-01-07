-- phpMyAdmin SQL Dump
-- version 5.1.2
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Jan 06, 2026 at 02:53 PM
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
-- Database: `fdchecklist`
--

-- --------------------------------------------------------

--
-- Table structure for table `apparatus_log`
--

CREATE TABLE `apparatus_log` (
  `id` int(11) NOT NULL,
  `department_name` varchar(100) DEFAULT NULL,
  `apparatus_id` varchar(50) DEFAULT NULL,
  `incident_type` varchar(50) DEFAULT NULL,
  `timestamp` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `apparatus_log`
--

INSERT INTO `apparatus_log` (`id`, `department_name`, `apparatus_id`, `incident_type`, `timestamp`) VALUES
(1, 'NYFD', '33', 'Unknown', '2025-08-11 19:41:11');

-- --------------------------------------------------------

--
-- Table structure for table `apparatus_responding`
--

CREATE TABLE `apparatus_responding` (
  `id` int(11) NOT NULL,
  `incident_id` int(11) NOT NULL,
  `Label` varchar(100) DEFAULT NULL,
  `Type` varchar(50) DEFAULT NULL,
  `ApparatusLabel` varchar(100) DEFAULT NULL,
  `apparatus_type` int(11) NOT NULL,
  `apparatus_ID` varchar(30) NOT NULL,
  `firefighter_count` int(11) NOT NULL DEFAULT '0',
  `mutual_aid_dept` varchar(100) DEFAULT NULL,
  `incident_mutual_aid_id` int(10) UNSIGNED DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Responding',
  `eta_minutes` int(11) DEFAULT NULL,
  `last_update` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `dispatch_time` datetime NOT NULL,
  `notes` varchar(500) NOT NULL,
  `is_mutual_aid` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `apparatus_responding`
--

INSERT INTO `apparatus_responding` (`id`, `incident_id`, `Label`, `Type`, `ApparatusLabel`, `apparatus_type`, `apparatus_ID`, `firefighter_count`, `mutual_aid_dept`, `incident_mutual_aid_id`, `status`, `eta_minutes`, `last_update`, `dispatch_time`, `notes`, `is_mutual_aid`) VALUES
(50, 32, NULL, NULL, NULL, 2, 'Engine 52', 3, NULL, NULL, 'On Scene', NULL, '2025-12-16 01:57:18', '2025-12-15 15:13:53', '', 0),
(51, 33, NULL, NULL, NULL, 2, 'Engine 52', 2, NULL, NULL, 'Cancelled', NULL, '2025-12-16 02:11:26', '2025-12-15 15:45:03', 'Notes go here.', 0),
(52, 33, NULL, NULL, NULL, 1, 'Engine 56', 3, NULL, NULL, 'Responding', NULL, '2025-12-16 01:57:29', '2025-12-15 17:23:20', '', 0),
(53, 34, NULL, NULL, NULL, 2, 'Engine 52', 3, NULL, NULL, 'Responding', NULL, '2025-12-16 17:38:14', '2025-12-16 12:38:15', '', 0),
(54, 35, NULL, NULL, NULL, 2, 'Engine 52', 3, NULL, NULL, 'Responding', NULL, '2025-12-19 00:55:13', '2025-12-18 15:38:39', '', 0),
(55, 35, NULL, NULL, NULL, 1, 'Engine 56', 3, NULL, NULL, 'Responding', NULL, '2025-12-19 00:54:39', '2025-12-18 19:54:40', '', 0),
(56, 36, NULL, NULL, NULL, 13, 'Eng31', 3, NULL, NULL, 'On Scene', NULL, '2025-12-24 13:15:16', '2025-12-22 13:36:22', '', 0),
(57, 37, NULL, NULL, NULL, 15, 'Eng 32', 3, NULL, NULL, 'Responding', NULL, '2025-12-26 19:10:34', '2025-12-26 14:10:35', '', 0),
(58, 38, NULL, NULL, NULL, 15, 'Eng 32', 3, NULL, NULL, 'Responding', NULL, '2025-12-26 19:11:21', '2025-12-26 14:11:21', '', 0),
(61, 41, 'Eng 56', '', 'Eng 56', 0, 'Eng 56', 4, 'Crozet VFD', NULL, 'Responding', NULL, '2025-12-30 02:57:08', '2025-12-29 21:57:08', '', 1),
(63, 43, NULL, NULL, NULL, 9, 'Eng 56', 3, NULL, NULL, 'On Scene', NULL, '2025-12-28 17:55:06', '2025-12-27 21:32:15', '', 0),
(64, 43, 'Eng 16', 'ENGINE', 'Eng 16', 1, 'Eng 16', 4, 'ACFR', NULL, 'Responding', NULL, '2025-12-28 20:31:40', '2025-12-28 15:31:40', '', 1),
(65, 43, 'Truck 23', 'TRUCK', 'Truck 23', 2, 'Truck 23', 4, 'CVFD', NULL, 'Responding', NULL, '2025-12-28 19:31:46', '2025-12-28 14:31:46', '', 1),
(68, 43, 'Eng 8', 'ENGINE', 'Eng 8', 1, 'Eng 8', 4, 'CVFD', NULL, 'Responding', NULL, '2025-12-28 20:31:54', '2025-12-28 15:31:54', '', 1),
(76, 41, 'Engine 56', 'ENGINE', 'Engine 56', 1, 'Engine 56', 4, 'CVFD', NULL, 'Released', NULL, '2025-12-30 15:01:30', '2025-12-29 21:58:38', '', 1),
(78, 41, 'Eng 8', 'ENGINE', 'Eng 8', 1, 'Eng 8', 4, 'ACFR', NULL, 'Responding', NULL, '2025-12-30 03:00:10', '2025-12-29 22:00:10', '', 1),
(80, 41, 'Truck 23', '', 'Truck 23', 0, 'Truck 23', 4, 'Crozet VFD', NULL, 'Responding', NULL, '2025-12-30 15:23:49', '2025-12-30 10:23:49', '', 1),
(89, 41, 'Eng 16', 'ENGINE', 'Eng 16', 1, 'Eng 16', 4, 'ACFR', NULL, 'Cancelled', NULL, '2025-12-30 16:20:21', '2025-12-30 10:20:17', '', 1);

-- --------------------------------------------------------

--
-- Table structure for table `apparatus_status`
--

CREATE TABLE `apparatus_status` (
  `ID` int(11) NOT NULL,
  `ApparatusStatus` varchar(50) NOT NULL,
  `StatusDateTime` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `apparatus_status`
--

INSERT INTO `apparatus_status` (`ID`, `ApparatusStatus`, `StatusDateTime`) VALUES
(1, 'Responding', '2025-11-03 14:19:22'),
(2, 'Arrived', '2025-11-03 14:19:22'),
(3, 'Released', '2025-11-03 14:19:22'),
(4, 'On Scene', '2025-12-15 20:36:52'),
(5, 'Cancelled', '2025-12-15 20:36:52'),
(6, 'Rejoined', '2025-12-15 20:36:52'),
(7, 'Returning', '2025-12-15 20:36:52'),
(8, 'In Quarters', '2025-12-15 20:36:52');

-- --------------------------------------------------------

--
-- Table structure for table `apparatus_status_events`
--

CREATE TABLE `apparatus_status_events` (
  `id` int(11) NOT NULL,
  `incident_id` int(11) NOT NULL,
  `apparatus_responding_id` int(11) NOT NULL,
  `old_status` varchar(20) NOT NULL,
  `new_status` varchar(20) NOT NULL,
  `event_time` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `notes` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `apparatus_status_events`
--

INSERT INTO `apparatus_status_events` (`id`, `incident_id`, `apparatus_responding_id`, `old_status`, `new_status`, `event_time`, `notes`) VALUES
(1, 33, 52, 'Responding', 'Cancelled', '2025-12-15 20:45:52', ''),
(2, 33, 51, 'Responding', 'Cancelled', '2025-12-15 20:46:02', ''),
(3, 32, 50, 'Responding', 'On Scene', '2025-12-15 20:57:18', ''),
(4, 33, 52, 'Cancelled', 'Responding', '2025-12-15 20:57:29', ''),
(5, 35, 54, 'Responding', 'Cancelled', '2025-12-18 19:55:05', ''),
(6, 35, 54, 'Cancelled', 'Responding', '2025-12-18 19:55:13', ''),
(7, 36, 56, 'Responding', 'On Scene', '2025-12-24 08:15:16', ''),
(9, 43, 63, 'Responding', 'On Scene', '2025-12-28 12:55:06', 'Auto: Size-Up saved'),
(10, 41, 76, 'Responding', 'Released', '2025-12-30 10:01:30', ''),
(11, 41, 89, 'Responding', 'Cancelled', '2025-12-30 11:20:21', '');

-- --------------------------------------------------------

--
-- Table structure for table `apparatus_types`
--

CREATE TABLE `apparatus_types` (
  `id` int(11) NOT NULL,
  `ApparatusType` varchar(50) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `apparatus_types`
--

INSERT INTO `apparatus_types` (`id`, `ApparatusType`, `is_active`) VALUES
(1, 'ENGINE', 1),
(2, 'TRUCK', 1),
(3, 'TANKER', 1),
(4, 'BRUSH TRUCK', 1),
(5, 'RESCUE', 1),
(6, 'AMBULANCE', 1),
(7, 'COMMAND CAR', 1),
(8, 'AIR SUPPLY', 1),
(9, 'Brush', 1);

-- --------------------------------------------------------

--
-- Table structure for table `assignments`
--

CREATE TABLE `assignments` (
  `id` int(11) NOT NULL,
  `incident_id` int(11) NOT NULL,
  `apparatus_id` int(11) NOT NULL,
  `assignment_type_id` int(11) NOT NULL,
  `location` varchar(50) DEFAULT NULL,
  `start_time` datetime DEFAULT CURRENT_TIMESTAMP,
  `end_time` datetime DEFAULT NULL,
  `notes` text,
  `active` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `assignment_types`
--

CREATE TABLE `assignment_types` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `color_code` varchar(20) NOT NULL DEFAULT '#999999',
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ColorHex` char(7) NOT NULL DEFAULT '#999999',
  `SortOrder` int(11) NOT NULL DEFAULT '100',
  `IsActive` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `assignment_types`
--

INSERT INTO `assignment_types` (`id`, `name`, `color_code`, `description`, `created_at`, `ColorHex`, `SortOrder`, `IsActive`) VALUES
(1, 'Fire Attack', '#FF4C4C', 'Interior or exterior attack line', '2025-11-14 16:36:07', '#999999', 100, 1),
(2, 'Search & Rescue', '#FF9D3A', 'Primary or secondary search', '2025-11-14 16:36:07', '#999999', 100, 1),
(3, 'Ventilation', '#4DA3FF', 'Horizontal or vertical ventilation', '2025-11-14 16:36:07', '#999999', 100, 1),
(4, 'RIT', '#B57CFF', 'Rapid Intervention Team', '2025-11-14 16:36:07', '#999999', 100, 1),
(5, 'Water Supply', '#FFE156', 'Hydrant, tanker shuttle, relay', '2025-11-14 16:36:07', '#999999', 100, 1),
(6, 'Exposure Control', '#4CAF50', 'Protecting nearby structures', '2025-11-14 16:36:07', '#999999', 100, 1),
(7, 'Overhaul', '#E0E0E0', 'Post-extinguishment hotspot removal', '2025-11-14 16:36:07', '#999999', 100, 1),
(8, 'Staging', '#A9A9A9', 'Waiting for assignment', '2025-11-14 16:36:07', '#999999', 100, 1),
(9, 'Rehab', '#6F42C1', 'Rehab sector / medical monitoring', '2025-12-05 18:51:24', '#6F42C1', 60, 1),
(10, 'Air Supply', '#20C997', 'Air / bottle refill / cascade unit', '2025-12-05 18:51:24', '#20C997', 70, 1);

-- --------------------------------------------------------

--
-- Table structure for table `benchmark_events`
--

CREATE TABLE `benchmark_events` (
  `ID` int(11) NOT NULL,
  `incident_id` int(11) NOT NULL,
  `benchmark_type_id` int(11) NOT NULL,
  `EventTime` datetime(6) NOT NULL,
  `Notes` varchar(255) DEFAULT NULL,
  `CreatedAt` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `benchmark_types`
--

CREATE TABLE `benchmark_types` (
  `ID` int(11) NOT NULL,
  `Name` varchar(50) NOT NULL,
  `Category` varchar(50) DEFAULT NULL,
  `SortOrder` int(11) NOT NULL DEFAULT '100',
  `IsActive` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `checklist_items`
--

CREATE TABLE `checklist_items` (
  `id` int(11) NOT NULL,
  `category` varchar(100) NOT NULL,
  `description` text NOT NULL,
  `upon_arrival_only` tinyint(1) DEFAULT '0',
  `active` tinyint(1) DEFAULT '1',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `checklist_items`
--

INSERT INTO `checklist_items` (`id`, `category`, `description`, `upon_arrival_only`, `active`, `created_at`, `updated_at`) VALUES
(1, 'Safety Officer', 'Report to IC; confirm strategy/plan/safety plan', 1, 1, '2025-12-16 13:57:11', '2025-12-16 13:57:11'),
(2, 'Safety Officer', 'Walk the incident; establish perimeter; perform risk assessment', 1, 1, '2025-12-16 13:57:11', '2025-12-16 13:57:11'),
(3, 'Safety Officer', 'Strategy/Tactics: confirm offensive/defensive/marginal mode', 1, 1, '2025-12-16 13:57:11', '2025-12-16 13:57:11'),
(4, 'Safety Officer', 'Ventilation: method/location/egress/smoke conditions reviewed', 1, 1, '2025-12-16 13:57:11', '2025-12-16 13:57:11'),
(5, 'Safety Officer', 'Incident layout: crew locations; RIT/rapid intervention in place', 1, 1, '2025-12-16 13:57:11', '2025-12-16 13:57:11'),
(6, 'Safety Officer', 'Hazards: utilities (electric/gas/LP tanks) controlled/identified', 1, 1, '2025-12-16 13:57:11', '2025-12-16 13:57:11'),
(7, 'Safety Officer', 'Hazards: environmental (heat/cold/ice/wind/rain) monitored', 0, 1, '2025-12-16 13:57:11', '2025-12-16 13:57:11'),
(8, 'Safety Officer', 'Hazards: structural conditions (roof/walls/floors/facades/signs) monitored', 0, 1, '2025-12-16 13:57:11', '2025-12-16 13:57:11'),
(9, 'Safety Officer', 'Accountability: system set; PAR schedule; RIT status confirmed', 0, 1, '2025-12-16 13:57:11', '2025-12-16 13:57:11'),
(10, 'Safety Officer', 'PPE/SCBA compliance monitored (crew-wide)', 0, 1, '2025-12-16 13:57:11', '2025-12-16 13:57:11'),
(11, 'Safety Officer', 'Communications: radios/face-to-face; sectors/command comms effective', 0, 1, '2025-12-16 13:57:11', '2025-12-16 13:57:11'),
(12, 'Safety Officer', 'Hazard control zones established/respected (hot/warm/cold/no-entry)', 0, 1, '2025-12-16 13:57:11', '2025-12-16 13:57:11'),
(13, 'Safety Officer', 'Rehab: location; rotation; fluids; EMS; heat/cooling plan in place', 0, 1, '2025-12-16 13:57:11', '2025-12-16 13:57:11'),
(14, 'Safety Officer', 'Ladders: placement/secured/hazards; two egress where needed', 0, 1, '2025-12-16 13:57:11', '2025-12-16 13:57:11'),
(15, 'Safety Officer', 'Equipment use: hoselines/water supply/tools/lighting safely placed', 0, 1, '2025-12-16 13:57:11', '2025-12-16 13:57:11'),
(16, 'Safety Officer', 'Apparatus: placement; collapse/heat zone; staging/resources adequate', 0, 1, '2025-12-16 13:57:11', '2025-12-16 13:57:11'),
(17, 'Mayday', 'Initiate PAR check and confirm number of missing', 0, 1, '2025-12-30 17:11:47', '2025-12-30 17:11:47'),
(18, 'Mayday', 'Initiate emergency fireground announcement', 0, 1, '2025-12-30 17:11:47', '2025-12-30 17:11:47'),
(19, 'Mayday', 'Make search and rescue a high incident priority', 0, 1, '2025-12-30 17:11:47', '2025-12-30 17:11:47'),
(20, 'Mayday', 'Develop a rescue action plan', 0, 1, '2025-12-30 17:11:47', '2025-12-30 17:11:47'),
(21, 'Mayday', 'Monitor all incident radio channels', 0, 1, '2025-12-30 17:11:47', '2025-12-30 17:11:47'),
(22, 'Mayday', 'Assign RIT team to search and rescue', 0, 1, '2025-12-30 17:11:47', '2025-12-30 17:11:47'),
(23, 'Mayday', 'Request additional resources: Command Staff', 0, 1, '2025-12-30 17:11:47', '2025-12-30 17:11:47'),
(24, 'Mayday', 'Request additional resources: RIT Level 2 Task Force', 0, 1, '2025-12-30 17:11:47', '2025-12-30 17:11:47'),
(25, 'Mayday', 'Request additional resources: RIT Level 3 Collapse Task Force', 0, 1, '2025-12-30 17:11:47', '2025-12-30 17:11:47'),
(26, 'Mayday', 'Request additional resources: EMS Capabilities', 0, 1, '2025-12-30 17:11:47', '2025-12-30 17:11:47'),
(27, 'Mayday', 'Request additional resources: Fire Control', 0, 1, '2025-12-30 17:11:47', '2025-12-30 17:11:47'),
(28, 'Mayday', 'Request additional resources: Relief', 0, 1, '2025-12-30 17:11:47', '2025-12-30 17:11:47'),
(29, 'Mayday', 'Report of units missing or in trouble', 0, 1, '2025-12-30 17:11:47', '2025-12-30 17:11:47'),
(30, 'Mayday', 'Maintain fire attack', 0, 1, '2025-12-30 17:11:47', '2025-12-30 17:11:47'),
(31, 'Mayday', 'Expand command organization (Rescue Group)', 0, 1, '2025-12-30 17:11:47', '2025-12-30 17:11:47'),
(32, 'Mayday', 'Withdraw and control unassigned resources', 0, 1, '2025-12-30 17:11:47', '2025-12-30 17:11:47'),
(33, 'Mayday', 'Maintain strong supervision in all work areas', 0, 1, '2025-12-30 17:11:47', '2025-12-30 17:11:47'),
(34, 'Mayday', 'Control and restrict unauthorized entry', 0, 1, '2025-12-30 17:11:47', '2025-12-30 17:11:47'),
(35, 'Mayday', 'Maintain ALS capability for trapped victims', 0, 1, '2025-12-30 17:11:47', '2025-12-30 17:11:47'),
(36, 'Mayday', 'Assign Safety Group to control risk taking', 0, 1, '2025-12-30 17:11:47', '2025-12-30 17:11:47'),
(37, 'Mayday', 'Assess ability to increase points of egress', 0, 1, '2025-12-30 17:11:47', '2025-12-30 17:11:47'),
(38, 'Mayday', 'Assign a Public Information Officer', 0, 1, '2025-12-30 17:11:47', '2025-12-30 17:11:47'),
(39, 'Mayday', 'Maintain contact with Mayday firefighter', 0, 1, '2025-12-30 17:11:47', '2025-12-30 17:11:47');

-- --------------------------------------------------------

--
-- Table structure for table `command_assignments`
--

CREATE TABLE `command_assignments` (
  `ID` int(11) NOT NULL,
  `incident_id` int(11) NOT NULL,
  `command_officer_id` int(10) UNSIGNED DEFAULT NULL,
  `command_officer_source` enum('local','mutual_aid') DEFAULT NULL,
  `command_officer_display` varchar(150) DEFAULT NULL,
  `apparatus_responding_id` int(11) DEFAULT NULL,
  `assignment_type_id` int(11) NOT NULL,
  `UnitLabel` varchar(50) NOT NULL,
  `LocationSide` varchar(20) DEFAULT NULL,
  `LocationFloor` varchar(20) DEFAULT NULL,
  `LocationArea` varchar(100) DEFAULT NULL,
  `tac_channel_id` int(11) DEFAULT NULL,
  `CrewSize` tinyint(4) DEFAULT NULL,
  `Status` enum('Active','Complete','Cancelled') NOT NULL DEFAULT 'Active',
  `StartTime` datetime(6) NOT NULL,
  `EndTime` datetime(6) DEFAULT NULL,
  `Notes` varchar(255) DEFAULT NULL,
  `CreatedAt` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `UpdatedAt` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `command_assignment_events`
--

CREATE TABLE `command_assignment_events` (
  `ID` int(11) NOT NULL,
  `incident_id` int(11) NOT NULL,
  `assignment_id` int(11) DEFAULT NULL,
  `event_type` enum('Created','UnitAdded','UnitRemoved','OfficerChanged','AssignmentTypeChanged','LocationChanged','StatusChanged','Deleted') NOT NULL,
  `details` varchar(255) NOT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `department`
--

CREATE TABLE `department` (
  `id` int(10) UNSIGNED NOT NULL,
  `dept_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `dept_short_name` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL,
  `access_code_hash` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `contact_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_email` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_phone` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `contact_address` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
  `is_mutual_aid` tinyint(1) NOT NULL DEFAULT '0',
  `designation` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `station_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `department`
--

INSERT INTO `department` (`id`, `dept_name`, `dept_short_name`, `access_code_hash`, `is_active`, `contact_name`, `contact_email`, `contact_phone`, `contact_address`, `created_at`, `updated_at`, `is_mutual_aid`, `designation`, `station_id`) VALUES
(1, 'Crozet Volunteer Fire Department', 'CVFD', '$argon2id$v=19$m=65536,t=4,p=1$QXZkbHI5YzIyMER2V1o2OA$nK6J8HPCcfRkfzVU4/enhvwdo4Nw0cwlW8iw8FbS0HM', 1, 'Thomas Loach', 'tcl4p@virginia.edu', '434 466-9027', '', '2025-11-18 11:46:54', '2025-11-18 11:58:45', 0, NULL, NULL),
(2, 'Elmont Volunteer Fire Department', 'EVFD', '$2y$10$d/kXBDLpSwFSJehbd0PUnu9Ru3CDdbYTdKyrJBgZEJABeNIJiJThC', 1, 'Thomas Loach', 'tcl4p@virginia.edu', '4348236254', '1255', '2025-12-20 10:55:30', '2025-12-23 12:00:18', 0, 'EVFD', 'Station 1'),
(3, 'North Garden Volunteer Fire Department', 'NGVFD', '$argon2id$v=19$m=65536,t=4,p=1$YVhDWXc5TS5RU3kyLnB2Lg$MROCBrcIalCvsjwv2AobpATYn1XWgk2+irTleL9UHWs', 1, 'Thomas Wilson', 'tcl4p@virginia.edu', '4348236254', '333 Main Street, North Garden, VA', '2025-12-20 14:59:24', '2025-12-23 10:23:19', 0, 'NGVFD', 'Station 3'),
(4, 'Earlysville Volunteer Fire Department', 'EVFD', '$argon2id$v=19$m=65536,t=4,p=1$cE1UTjNITEk0ZjJVb3RJeQ$6AfRHmcLTjtq/BQaB55s9Ta1IdudTNNl72oCd700RFs', 1, 'Chief 40', 'chief@albemarle.org', '434 333-9999', '567 Earlysville Road, Charlottesville, Va 22901', '2025-12-20 20:26:05', NULL, 0, NULL, 'Station 4'),
(5, 'Albemarle County Fire & Rescue', 'ACFR', '$2y$10$6Z27emXy4fXr5YEvKsbtI.OilxuffIGubA1MSj0YAYcYJxQRhcP9O', 1, NULL, NULL, NULL, NULL, '2025-12-22 15:54:25', NULL, 1, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `department_apparatus`
--

CREATE TABLE `department_apparatus` (
  `id` int(10) UNSIGNED NOT NULL,
  `dept_id` int(10) UNSIGNED NOT NULL,
  `apparatus_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `apparatus_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `radio_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `department_apparatus`
--

INSERT INTO `department_apparatus` (`id`, `dept_id`, `apparatus_name`, `apparatus_type`, `radio_id`, `sort_order`, `is_active`, `created_at`) VALUES
(2, 1, 'Engine 52', 'Engine', 'E1', 1, 1, '2025-11-18 12:00:07'),
(3, 1, 'Engine 56', 'Engine', 'E2', 2, 1, '2025-11-18 12:00:07'),
(4, 1, 'Tanker 59', 'Tanker', 'T8', 3, 1, '2025-11-18 12:00:07'),
(5, 1, 'Truck 54', 'Truck', 'L5', 4, 1, '2025-11-18 12:00:07'),
(6, 1, 'Brush 53', 'Rescue', 'R2', 5, 1, '2025-11-18 12:00:07'),
(7, 1, 'Brush 55', 'Brush', 'B1', 6, 1, '2025-11-18 12:00:07'),
(8, 2, 'Eng 52', 'ENGINE', '', 0, 1, '2025-12-21 16:43:06'),
(9, 2, 'Eng 56', 'ENGINE', '', 0, 1, '2025-12-21 16:43:19'),
(10, 2, 'Eng 58', 'ENGINE', '', 0, 1, '2025-12-21 16:43:40'),
(11, 2, 'Truck 54', 'TRUCK', '', 0, 1, '2025-12-21 16:43:54'),
(12, 2, 'Tanker 59', 'TANKER', '', 0, 1, '2025-12-21 16:44:06'),
(15, 3, 'Eng 32', 'ENGINE', '', 0, 1, '2025-12-26 13:07:00'),
(16, 3, 'Eng 35', 'ENGINE', '', 0, 1, '2025-12-26 13:07:16');

-- --------------------------------------------------------

--
-- Table structure for table `department_command`
--

CREATE TABLE `department_command` (
  `id` int(10) UNSIGNED NOT NULL,
  `department_id` int(10) UNSIGNED DEFAULT NULL,
  `dept_id` int(10) UNSIGNED NOT NULL,
  `member_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `rank_id` int(10) UNSIGNED NOT NULL,
  `radio_designation` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `can_be_incident_command` tinyint(1) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_mutual_aid` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `department_command`
--

INSERT INTO `department_command` (`id`, `department_id`, `dept_id`, `member_name`, `rank_id`, `radio_designation`, `can_be_incident_command`, `is_active`, `created_at`, `is_mutual_aid`) VALUES
(2, NULL, 1, 'John Smith', 1, 'Car 1', 0, 1, '2025-11-18 12:00:29', 0),
(3, NULL, 1, 'Mary Jones', 4, 'Car 2', 0, 1, '2025-11-18 12:00:29', 0),
(4, NULL, 1, 'Bill Brown', 5, 'Car 3', 0, 1, '2025-11-18 12:00:29', 0),
(5, NULL, 1, 'Alex Miller', 6, NULL, 0, 1, '2025-11-18 12:00:29', 0),
(7, NULL, 2, 'Chief 50', 1, '', 0, 1, '2025-12-23 12:01:00', 0),
(8, NULL, 2, 'Chief 51', 1, '', 0, 1, '2025-12-23 12:01:14', 0),
(10, NULL, 2, 'Chief 52', 1, '123', 0, 1, '2025-12-23 13:10:16', 0),
(11, NULL, 3, 'Chief 30', 1, '', 0, 1, '2025-12-26 12:23:49', 0),
(12, NULL, 3, 'Chief 31', 2, '', 0, 1, '2025-12-26 12:24:10', 0),
(13, NULL, 3, 'Chief 32', 1, '', 0, 1, '2025-12-26 12:41:21', 0),
(14, NULL, 3, 'Chief 33', 3, '', 0, 1, '2025-12-26 12:41:46', 0);

-- --------------------------------------------------------

--
-- Table structure for table `department_command_rank`
--

CREATE TABLE `department_command_rank` (
  `id` int(10) UNSIGNED NOT NULL,
  `rank_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sort_order` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `department_command_rank`
--

INSERT INTO `department_command_rank` (`id`, `rank_name`, `sort_order`, `is_active`) VALUES
(1, 'Chief', 1, 1),
(2, 'Assistant Chief', 2, 1),
(3, 'Battalion Chief', 3, 1),
(4, 'Captain', 4, 1),
(5, 'Lieutenant ', 5, 1),
(6, 'Firefighter', 6, 1);

-- --------------------------------------------------------

--
-- Table structure for table `department_mutual_aid`
--

CREATE TABLE `department_mutual_aid` (
  `id` int(10) UNSIGNED NOT NULL,
  `home_dept_id` int(10) UNSIGNED NOT NULL,
  `mutual_dept_id` int(10) UNSIGNED NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int(11) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `department_mutual_aid`
--

INSERT INTO `department_mutual_aid` (`id`, `home_dept_id`, `mutual_dept_id`, `is_active`, `sort_order`, `created_at`) VALUES
(6, 2, 1, 1, 0, '2025-12-23 18:12:05'),
(7, 3, 3, 1, 0, '2025-12-26 17:42:21'),
(8, 2, 3, 1, 0, '2025-12-27 00:43:59');

-- --------------------------------------------------------

--
-- Table structure for table `department_mutual_aid_apparatus`
--

CREATE TABLE `department_mutual_aid_apparatus` (
  `id` int(10) UNSIGNED NOT NULL,
  `home_dept_id` int(10) UNSIGNED NOT NULL,
  `mutual_dept_id` int(10) UNSIGNED NOT NULL,
  `apparatus_name` varchar(100) NOT NULL,
  `apparatus_type` varchar(50) NOT NULL,
  `radio_id` varchar(50) DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `department_mutual_aid_apparatus_template`
--

CREATE TABLE `department_mutual_aid_apparatus_template` (
  `id` int(10) UNSIGNED NOT NULL,
  `home_dept_id` int(10) UNSIGNED NOT NULL,
  `mutual_dept_id` int(11) NOT NULL,
  `apparatus_label` varchar(100) NOT NULL,
  `apparatus_type_id` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `staffing` int(11) NOT NULL DEFAULT '0',
  `sort_order` int(11) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `department_mutual_aid_apparatus_template`
--

INSERT INTO `department_mutual_aid_apparatus_template` (`id`, `home_dept_id`, `mutual_dept_id`, `apparatus_label`, `apparatus_type_id`, `staffing`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(4, 2, 1, 'Eng 8', 1, 0, 0, 1, '2025-12-23 13:13:41', NULL),
(5, 2, 1, 'Eng 16', 1, 0, 0, 1, '2025-12-23 13:14:02', NULL),
(7, 3, 3, 'Engine 52', 1, 0, 0, 1, '2025-12-26 12:43:52', NULL),
(8, 3, 3, 'Engine 56', 1, 0, 0, 1, '2025-12-26 12:44:24', NULL),
(9, 2, 3, 'Eng 8', 1, 0, 0, 1, '2025-12-26 19:45:42', NULL),
(10, 2, 3, 'Engine 56', 1, 0, 0, 1, '2025-12-26 19:45:53', NULL),
(11, 2, 3, 'Truck 23', 2, 0, 0, 1, '2025-12-26 19:46:18', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `department_mutual_aid_command_staff_template`
--

CREATE TABLE `department_mutual_aid_command_staff_template` (
  `id` int(10) UNSIGNED NOT NULL,
  `home_dept_id` int(10) UNSIGNED NOT NULL,
  `mutual_dept_id` int(11) NOT NULL,
  `officer_display` varchar(100) NOT NULL,
  `rank_id` int(10) UNSIGNED NOT NULL DEFAULT '0',
  `radio_designation` varchar(100) NOT NULL DEFAULT '',
  `sort_order` int(11) NOT NULL DEFAULT '0',
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `department_mutual_aid_command_staff_template`
--

INSERT INTO `department_mutual_aid_command_staff_template` (`id`, `home_dept_id`, `mutual_dept_id`, `officer_display`, `rank_id`, `radio_designation`, `sort_order`, `is_active`, `created_at`, `updated_at`) VALUES
(7, 2, 1, 'Chief 1', 1, '', 0, 1, '2025-12-23 13:12:34', NULL),
(8, 2, 1, 'Chief 5', 0, '', 0, 1, '2025-12-23 13:31:07', NULL),
(9, 3, 3, 'Chief 50', 1, '', 0, 1, '2025-12-26 12:42:58', NULL),
(10, 3, 3, 'Chief 51', 0, '', 0, 1, '2025-12-26 12:43:28', NULL),
(11, 2, 3, 'Chief 50', 1, '', 0, 1, '2025-12-26 19:44:20', NULL),
(12, 2, 3, 'Chief 51', 2, '', 0, 1, '2025-12-26 19:44:48', NULL),
(13, 2, 3, 'Chief 52', 3, '', 0, 1, '2025-12-26 19:45:24', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `department_mutual_aid_partners`
--

CREATE TABLE `department_mutual_aid_partners` (
  `id` int(10) UNSIGNED NOT NULL,
  `dept_id` int(10) UNSIGNED NOT NULL,
  `partner_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `partner_short_name` varchar(30) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `dept_command_staff_template`
--

CREATE TABLE `dept_command_staff_template` (
  `id` int(10) UNSIGNED NOT NULL,
  `dept_id` int(10) UNSIGNED NOT NULL,
  `role` varchar(50) NOT NULL,
  `rank_designation` varchar(50) NOT NULL,
  `person_name` varchar(100) DEFAULT NULL,
  `phone` varchar(30) DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1',
  `sort_order` int(11) NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `error_log`
--

CREATE TABLE `error_log` (
  `id` int(11) NOT NULL,
  `error_time` datetime DEFAULT CURRENT_TIMESTAMP,
  `error_type` varchar(100) DEFAULT NULL,
  `error_message` text,
  `source_file` varchar(100) DEFAULT NULL,
  `additional_info` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `error_log`
--

INSERT INTO `error_log` (`id`, `error_time`, `error_type`, `error_message`, `source_file`, `additional_info`) VALUES
(1, '2025-12-20 15:59:08', 'auth', 'Access code not recognized', 'auth_access.php', '{\"len\":8,\"ip\":\"::1\"}'),
(2, '2025-12-20 20:47:51', 'auth', 'Access code not recognized', 'auth_access.php', '{\"len\":8,\"ip\":\"::1\"}'),
(3, '2025-12-20 21:07:03', 'auth', 'Access code not recognized', 'auth_access.php', '{\"len\":8,\"ip\":\"::1\"}'),
(4, '2025-12-20 21:07:32', 'auth', 'Access code not recognized', 'auth_access.php', '{\"len\":8,\"ip\":\"::1\"}'),
(5, '2025-12-20 21:54:28', 'auth', 'Access code not recognized', 'auth_access.php', '{\"len\":8,\"ip\":\"::1\"}'),
(6, '2025-12-21 17:21:22', 'auth', 'Access code not recognized', 'auth_access.php', '{\"len\":7,\"ip\":\"::1\"}'),
(7, '2025-12-22 11:30:17', 'auth', 'Access code failed format validation', 'auth_access.php', '{\"len\":0,\"ip\":\"::1\"}'),
(8, '2025-12-22 11:30:25', 'auth', 'Access code failed format validation', 'auth_access.php', '{\"len\":0,\"ip\":\"::1\"}'),
(9, '2025-12-22 11:32:34', 'auth', 'Access code failed format validation', 'auth_access.php', '{\"len\":0,\"ip\":\"::1\"}'),
(10, '2025-12-22 11:36:32', 'auth', 'Access code failed format validation', 'auth_access.php', '{\"len\":0,\"ip\":\"::1\"}'),
(11, '2025-12-22 12:15:38', 'auth', 'Access code failed format validation', 'auth_access.php', '{\"len\":0,\"ip\":\"::1\"}'),
(12, '2025-12-22 12:33:47', 'auth', 'Access code failed format validation', 'auth_access.php', '{\"len\":0,\"ip\":\"::1\"}'),
(13, '2025-12-22 12:37:04', 'auth', 'Access code failed format validation', 'auth_access.php', '{\"len\":0,\"ip\":\"::1\"}'),
(14, '2025-12-22 13:29:31', 'auth', 'Access code not recognized', 'auth_access.php', '{\"len\":8,\"ip\":\"::1\"}'),
(15, '2025-12-22 13:31:31', 'auth', 'Access code not recognized', 'auth_access.php', '{\"len\":8,\"ip\":\"::1\"}'),
(16, '2025-12-23 14:03:11', 'auth', 'Access code not recognized', 'auth_access.php', '{\"len\":8,\"ip\":\"::1\"}'),
(17, '2025-12-24 08:00:18', 'auth', 'Access code not recognized', 'auth_access.php', '{\"len\":8,\"ip\":\"::1\"}'),
(18, '2025-12-26 13:25:58', 'auth', 'Access code not recognized', 'auth_access.php', '{\"len\":7,\"ip\":\"::1\"}'),
(19, '2025-12-26 14:09:45', 'auth', 'Access code not recognized', 'auth_access.php', '{\"len\":8,\"ip\":\"::1\"}'),
(20, '2025-12-26 14:34:02', 'auth', 'Access code not recognized', 'auth_access.php', '{\"len\":8,\"ip\":\"::1\"}'),
(21, '2025-12-26 16:09:55', 'auth', 'Access code not recognized', 'auth_access.php', '{\"len\":6,\"ip\":\"::1\"}'),
(22, '2025-12-30 10:19:18', 'auth', 'Access code not recognized', 'auth_access.php', '{\"len\":7,\"ip\":\"::1\"}');

-- --------------------------------------------------------

--
-- Table structure for table `firehouse_checklist`
--

CREATE TABLE `firehouse_checklist` (
  `id` int(11) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `item_number` int(11) DEFAULT NULL,
  `description` text,
  `completed` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `firehouse_checklist`
--

INSERT INTO `firehouse_checklist` (`id`, `category`, `item_number`, `description`, `completed`) VALUES
(1, 'Before Leaving the Firehouse', 1, 'Seat Belts on for all crew', 0),
(2, 'Before Leaving the Firehouse', 2, 'All gear stored and side and top panels closed', 0),
(3, 'Before Leaving the Firehouse', 3, 'Check for all special equipment needed i.e. TIC, CO monitor etc.', 0),
(4, 'Before Leaving the Firehouse', 4, 'Check water level.', 0),
(5, 'Before Leaving the Firehouse', 5, 'Set correct TAC Channel', 0),
(6, 'Before Leaving the Firehouse', 6, 'Confirm location and route (Officer and Driver)', 0),
(7, 'Before Leaving the Firehouse', 7, 'Garage door fully open.', 0),
(8, 'En Route', 1, 'Monitor radio traffic', 0),
(9, 'En Route', 2, 'Monitor weather conditions', 0),
(10, 'En Route', 3, 'Make initial assignments', 0),
(11, 'En Route', 4, 'Request addition help if needed', 0),
(12, 'En Route', 5, 'Review and pre fire plans', 0),
(13, 'En Route', 6, 'Confirm water supply locations', 0);

-- --------------------------------------------------------

--
-- Table structure for table `incidents`
--

CREATE TABLE `incidents` (
  `id` int(11) NOT NULL,
  `dept_id` int(10) UNSIGNED DEFAULT NULL,
  `primary_apparatus_id` int(10) UNSIGNED DEFAULT NULL,
  `DeptName` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `incident_number` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `IncidentDT` datetime(6) DEFAULT NULL,
  `closed_at` datetime(6) DEFAULT NULL,
  `status` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type` int(11) UNSIGNED NOT NULL,
  `location` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `subtype` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `city` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `state` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `postal_code` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `longitude` decimal(9,6) DEFAULT NULL,
  `response_time_s` int(11) DEFAULT NULL,
  `Staging_Location` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `incident_commander_officer_id` int(11) DEFAULT NULL,
  `incident_commander_source` enum('local','mutual_aid') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `incident_commander_display` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `safety_officer_display` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `safety_officer_source` enum('local','mutual_aid','manual') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `safety_officer_officer_id` int(10) UNSIGNED DEFAULT NULL,
  `safety_officer_mutual_aid_id` int(10) UNSIGNED DEFAULT NULL,
  `safety_checklist_notes` text COLLATE utf8mb4_unicode_ci,
  `alarm_level` tinyint(3) UNSIGNED NOT NULL DEFAULT '1',
  `alarm_updated_at` datetime(6) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `incidents`
--

INSERT INTO `incidents` (`id`, `dept_id`, `primary_apparatus_id`, `DeptName`, `incident_number`, `IncidentDT`, `closed_at`, `status`, `type`, `location`, `subtype`, `address`, `city`, `state`, `postal_code`, `longitude`, `response_time_s`, `Staging_Location`, `incident_commander_officer_id`, `incident_commander_source`, `incident_commander_display`, `safety_officer_display`, `safety_officer_source`, `safety_officer_officer_id`, `safety_officer_mutual_aid_id`, `safety_checklist_notes`, `alarm_level`, `alarm_updated_at`) VALUES
(32, NULL, NULL, 'Crozet Volunteer Fire Department', NULL, '2025-12-15 15:13:52.698626', '2025-12-16 10:19:44.000000', 'Closed', 4, '333 Center Street', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL),
(33, NULL, NULL, 'Crozet Volunteer Fire Department', NULL, '2025-12-15 15:45:03.135212', '2025-12-16 10:04:28.000000', 'Closed', 4, '333 Bayberry Street', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL),
(34, NULL, NULL, 'Crozet Volunteer Fire Department', NULL, '2025-12-16 12:38:14.677384', '2025-12-18 15:37:04.000000', 'Closed', 4, '123 Central Ave', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', NULL, 'local', 'Alex Miller', 'Capt. Loach', 'manual', NULL, NULL, 'Safety notes go here.', 1, NULL),
(35, NULL, NULL, 'Crozet Volunteer Fire Department', NULL, '2025-12-18 15:38:38.503704', NULL, 'Active', 4, '333 main street', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', NULL, 'local', 'Alex Miller', 'Capt Brown', 'manual', NULL, NULL, NULL, 1, NULL),
(36, NULL, NULL, 'North Garden Volunteer Fire Department', NULL, '2025-12-22 13:36:21.844436', NULL, 'Active', 7, '333 main street', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', NULL, 'local', 'Chief 50', 'Chief 51', 'local', 8, NULL, NULL, 1, NULL),
(37, NULL, NULL, 'North Garden Volunteer Fire Department', NULL, '2025-12-26 14:10:34.604506', NULL, 'Active', 4, '123 Main Street', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL),
(38, NULL, NULL, 'North Garden Volunteer Fire Department', NULL, '2025-12-26 14:11:21.080395', NULL, 'Active', 4, '123 Main Street', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL),
(41, 2, NULL, 'Elmont Volunteer Fire Department', NULL, '2025-12-26 14:42:35.564586', NULL, 'Active', 6, '345 Bayberry Ct', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, 1, NULL),
(43, 2, NULL, 'Elmont Volunteer Fire Department', NULL, '2025-12-27 21:32:14.918217', '2025-12-28 20:38:24.000000', 'Closed', 10, '', NULL, NULL, NULL, NULL, NULL, NULL, NULL, '', NULL, 'local', 'Chief 50', 'Chief 30', 'local', 11, NULL, NULL, 1, '2025-12-28 20:37:39.680080');

-- --------------------------------------------------------

--
-- Table structure for table `incident_alarm_log`
--

CREATE TABLE `incident_alarm_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `incident_id` int(11) NOT NULL,
  `old_level` tinyint(3) UNSIGNED NOT NULL,
  `new_level` tinyint(3) UNSIGNED NOT NULL,
  `changed_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `incident_alarm_log`
--

INSERT INTO `incident_alarm_log` (`id`, `incident_id`, `old_level`, `new_level`, `changed_at`) VALUES
(1, 43, 1, 2, '2025-12-28 19:07:16.637807'),
(2, 43, 2, 3, '2025-12-28 19:07:17.695993'),
(3, 43, 1, 2, '2025-12-28 19:21:26.820336'),
(4, 43, 2, 3, '2025-12-28 19:21:28.963548'),
(5, 43, 1, 2, '2025-12-28 20:37:37.371009'),
(6, 43, 2, 3, '2025-12-28 20:37:38.054193');

-- --------------------------------------------------------

--
-- Table structure for table `incident_checklist_responses`
--

CREATE TABLE `incident_checklist_responses` (
  `id` int(11) NOT NULL,
  `incident_id` int(11) NOT NULL,
  `checklist_id` int(11) NOT NULL,
  `is_checked` tinyint(1) NOT NULL DEFAULT '0',
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `incident_checklist_responses`
--

INSERT INTO `incident_checklist_responses` (`id`, `incident_id`, `checklist_id`, `is_checked`, `updated_at`) VALUES
(1, 34, 1, 0, '2025-12-16 14:35:46.777483'),
(2, 34, 2, 1, '2025-12-16 14:48:56.782289'),
(3, 34, 3, 1, '2025-12-16 14:35:49.475868'),
(4, 34, 4, 0, '2025-12-16 14:35:50.724017');

-- --------------------------------------------------------

--
-- Table structure for table `incident_command_resources`
--

CREATE TABLE `incident_command_resources` (
  `id` int(11) NOT NULL,
  `incident_id` int(11) NOT NULL,
  `department_name` varchar(255) DEFAULT NULL,
  `designation` varchar(50) DEFAULT NULL,
  `station_id` varchar(50) DEFAULT NULL,
  `officer_display` varchar(255) NOT NULL,
  `role` varchar(50) NOT NULL,
  `assignment_name` varchar(100) DEFAULT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `incident_command_resources`
--

INSERT INTO `incident_command_resources` (`id`, `incident_id`, `department_name`, `designation`, `station_id`, `officer_display`, `role`, `assignment_name`, `created_at`) VALUES
(13, 43, 'Albemarle County Fire & Rescue', '', '', 'Chief 1', '', NULL, '2025-12-28 13:19:53'),
(14, 43, 'Crozet Volunteer Fire Department', '', '', 'Chief 51', '', NULL, '2025-12-28 13:20:22'),
(15, 43, 'Albemarle County Fire & Rescue', '', '', 'Chief 5', '', NULL, '2025-12-28 19:07:04'),
(22, 42, 'Albemarle County Fire & Rescue', '', '', 'Chief 1', '', NULL, '2025-12-28 20:39:28'),
(37, 41, 'Albemarle County Fire & Rescue', '', '', 'Chief 1', '', NULL, '2026-01-05 17:16:38');

-- --------------------------------------------------------

--
-- Table structure for table `incident_elements`
--

CREATE TABLE `incident_elements` (
  `id` int(11) NOT NULL,
  `incident_id` int(11) NOT NULL,
  `element_type` enum('OPS','BRANCH','DIVISION','GROUP') COLLATE utf8mb4_unicode_ci NOT NULL,
  `element_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_element_id` int(11) DEFAULT NULL,
  `supervisor_source` enum('local','mutual','ic') COLLATE utf8mb4_unicode_ci DEFAULT 'ic',
  `supervisor_id` int(11) DEFAULT NULL,
  `status` enum('active','released') COLLATE utf8mb4_unicode_ci DEFAULT 'active',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `released_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `incident_elements`
--

INSERT INTO `incident_elements` (`id`, `incident_id`, `element_type`, `element_name`, `parent_element_id`, `supervisor_source`, `supervisor_id`, `status`, `created_at`, `released_at`) VALUES
(1, 35, 'DIVISION', 'Division 2', NULL, 'ic', NULL, 'released', '2025-12-18 16:16:44', '2025-12-18 19:45:29'),
(2, 35, 'GROUP', 'Group 1', NULL, 'ic', NULL, 'active', '2025-12-18 16:17:05', NULL),
(3, 35, 'DIVISION', 'Division 2', NULL, 'ic', NULL, 'active', '2025-12-18 16:38:49', NULL),
(4, 35, 'GROUP', 'Group 1', NULL, 'local', 3, 'active', '2025-12-18 16:39:09', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `incident_mutual_aid`
--

CREATE TABLE `incident_mutual_aid` (
  `id` int(10) UNSIGNED NOT NULL,
  `incident_id` int(11) NOT NULL,
  `dept_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `command_identity` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `is_command_officer` tinyint(1) NOT NULL DEFAULT '0',
  `officer_name` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `rank_id` int(10) UNSIGNED DEFAULT NULL,
  `radio_designation` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `incident_mutual_aid`
--

INSERT INTO `incident_mutual_aid` (`id`, `incident_id`, `dept_name`, `command_identity`, `created_at`, `is_command_officer`, `officer_name`, `rank_id`, `radio_designation`) VALUES
(1, 28, 'ACFD', 'Chief 1', '2025-12-06 14:37:18', 0, NULL, NULL, NULL),
(2, 28, 'GCFD', 'Chief20', '2025-12-06 14:56:07', 0, NULL, NULL, NULL),
(3, 28, 'CFD', 'CFD1', '2025-12-06 15:04:06', 0, NULL, NULL, NULL),
(10, 22, 'GCFD', 'Chief 30', '2025-12-11 14:35:32', 1, 'Chief 30', 1, 'Chief 30'),
(11, 22, 'ACFD', NULL, '2025-12-11 15:03:56', 0, NULL, NULL, NULL),
(12, 22, 'ACFD', 'Chief 77', '2025-12-11 15:05:49', 1, 'Chief 77', 1, 'Chief 77'),
(13, 28, 'ACFD', 'Chief 30', '2025-12-12 17:13:28', 1, 'Chief 30', 1, 'Chief 30'),
(14, 28, 'CFD', '1', '2025-12-12 18:53:28', 1, '1', NULL, '1'),
(15, 25, 'ACFD', NULL, '2025-12-13 12:36:37', 0, NULL, NULL, NULL),
(16, 22, 'CFD', 'Assistant Chief 23', '2025-12-13 13:00:14', 1, 'Assistant Chief 23', 3, 'Assistant Chief 23'),
(17, 25, 'ACFD', 'Chief', '2025-12-13 21:53:01', 1, 'Chief', 1, 'Chief'),
(18, 25, 'ACFD', 'Assistant Chief 34', '2025-12-13 21:53:20', 1, 'Assistant Chief 34', 3, 'Assistant Chief 34'),
(19, 25, 'ACFD', 'Deputy Chief', '2025-12-13 22:07:07', 1, 'Deputy Chief', 2, 'Deputy Chief'),
(20, 22, 'ACFD', 'Deputy Chief', '2025-12-14 09:09:04', 1, 'Deputy Chief', 2, 'Deputy Chief'),
(21, 22, 'ACFD', 'Deputy Chief12', '2025-12-14 09:29:55', 1, 'Deputy Chief12', 2, 'Deputy Chief12'),
(22, 25, 'GCFD', 'Assistant Chief66', '2025-12-14 09:39:29', 1, 'Assistant Chief66', 3, 'Assistant Chief66');

-- --------------------------------------------------------

--
-- Table structure for table `incident_mutual_aid_departments`
--

CREATE TABLE `incident_mutual_aid_departments` (
  `id` int(11) NOT NULL,
  `incident_id` int(11) NOT NULL,
  `ma_department_id` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `incident_notifications`
--

CREATE TABLE `incident_notifications` (
  `ID` int(11) NOT NULL,
  `incident_id` int(11) NOT NULL,
  `notification_type_id` int(11) NOT NULL,
  `EventTime` datetime(6) NOT NULL,
  `Status` enum('Requested','En Route','On Scene','Cancelled') NOT NULL DEFAULT 'Requested',
  `Notes` varchar(255) DEFAULT NULL,
  `CreatedAt` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `incident_sizeup`
--

CREATE TABLE `incident_sizeup` (
  `id` int(11) NOT NULL,
  `incident_id` int(11) NOT NULL,
  `confirm_address` tinyint(1) NOT NULL DEFAULT '0',
  `notify_command` tinyint(1) NOT NULL DEFAULT '0',
  `building_type` varchar(50) DEFAULT NULL,
  `occupancy_type` varchar(100) DEFAULT NULL,
  `smoke_side` varchar(255) DEFAULT NULL,
  `smoke_floor` varchar(20) DEFAULT NULL,
  `fire_side` varchar(255) DEFAULT NULL,
  `fire_floor` varchar(20) DEFAULT NULL,
  `command_name` varchar(100) DEFAULT NULL,
  `command_officer` varchar(100) DEFAULT NULL,
  `iap_mode` varchar(20) DEFAULT NULL,
  `life_hazard` varchar(16) NOT NULL DEFAULT '',
  `num_stories` tinyint(3) UNSIGNED NOT NULL DEFAULT '0',
  `water_supply` varchar(255) NOT NULL DEFAULT '',
  `arrival_conditions` text,
  `walkaround_findings` text,
  `notes` text,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `walk_look_victims` tinyint(1) NOT NULL DEFAULT '0',
  `walk_note_fire_location` tinyint(1) NOT NULL DEFAULT '0',
  `walk_check_access_openings` tinyint(1) NOT NULL DEFAULT '0',
  `walk_note_basement_access` tinyint(1) NOT NULL DEFAULT '0',
  `walk_note_exposure_risk` tinyint(1) NOT NULL DEFAULT '0',
  `walk_note_power_lines` tinyint(1) NOT NULL DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `incident_sizeup`
--

INSERT INTO `incident_sizeup` (`id`, `incident_id`, `confirm_address`, `notify_command`, `building_type`, `occupancy_type`, `smoke_side`, `smoke_floor`, `fire_side`, `fire_floor`, `command_name`, `command_officer`, `iap_mode`, `life_hazard`, `num_stories`, `water_supply`, `arrival_conditions`, `walkaround_findings`, `notes`, `created_at`, `walk_look_victims`, `walk_note_fire_location`, `walk_check_access_openings`, `walk_note_basement_access`, `walk_note_exposure_risk`, `walk_note_power_lines`) VALUES
(14, 33, 1, 0, 'Wood Frame', 'Single-Family', 'Bravo, Charlie', '2nd, 3rd+', 'Alpha, Bravo', 'Garage, Unknown', 'Oak Street Command', '', 'Offensive', 'no', 2, 'Hydrant', NULL, 'Oak Street Command on scene. address confirmed with dispatch. 2-story Wood Frame Single-Family occupancy. smoke showing side(s): Bravo, Charlie floor(s): 2nd, 3rd+. fire located side(s): Alpha, Bravo floor(s): Garage, Unknown. life hazard: no. water supply: Hydrant. operating in Offensive mode.', NULL, '2025-12-15 21:44:42.924151', 0, 0, 0, 0, 0, 0),
(15, 35, 1, 1, 'Wood Frame', 'Single-Family', 'Bravo, Delta', '', 'Bravo, Delta', '', 'Main St Command', '', 'Offensive', 'no', 2, 'Hydrant', NULL, 'Main St Command on scene. address confirmed with dispatch. 2-story Wood Frame Single-Family occupancy. smoke showing side(s): Bravo, Delta. fire located side(s): Bravo, Delta. life hazard: no. water supply: Hydrant. operating in Offensive mode.', NULL, '2025-12-18 19:48:38.029467', 1, 1, 1, 0, 0, 0),
(17, 43, 1, 1, 'Wood Frame', 'Single-Family', 'Unknown', 'Unknown', 'Unknown', 'Unknown', 'Main St Command', 'Eng 56', 'Investigative', 'no', 3, 'Hydrant', NULL, 'Dispatch, Elmont Volunteer Fire Department on scene of Trash.\r\nCommand: Main St Command\r\nBuilding: Single-Family, Wood Frame\r\nSmoke: Unknown, Unknown\r\nFire: Unknown, Unknown\r\n360 report: Main St Command on scene. address confirmed with dispatch. 3-story Wood Frame Single-Family occupancy. smoke showing side(s): Unknown floor(s): Unknown. fire located side(s): Unknown floor(s): Unknown. life hazard: no. water supply: Hydrant. operating in Investigative mode.\r\nIAP: Investigative operations.', NULL, '2025-12-28 12:43:54.316394', 0, 0, 0, 0, 0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `incident_tac_roles`
--

CREATE TABLE `incident_tac_roles` (
  `id` int(11) NOT NULL,
  `incident_id` int(11) NOT NULL,
  `role` enum('CMD','WATER','MAYDAY') NOT NULL,
  `channel_num` int(11) DEFAULT NULL,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `incident_types`
--

CREATE TABLE `incident_types` (
  `ID` int(11) UNSIGNED NOT NULL,
  `incidentType` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `incident_types`
--

INSERT INTO `incident_types` (`ID`, `incidentType`) VALUES
(1, 'EMS-Rescue'),
(2, 'Home Alarm'),
(3, 'MVA'),
(4, 'Structure Fire'),
(5, 'Hazmat'),
(6, 'Brush Fire'),
(7, 'Cooking Fire'),
(8, 'Smoke Report'),
(9, 'Vehicle Fire'),
(10, 'Trash'),
(11, 'Electrical Fire'),
(12, 'Elevator Rescue'),
(13, 'Fuel Spill'),
(14, 'Gas Leak'),
(15, 'Smoke Investigation'),
(16, 'Train or Rail Incident'),
(17, 'High Angle Rescue'),
(18, 'miscellaneous'),
(19, 'Public Service'),
(20, 'Aircraft Fire'),
(21, 'Water Rescue'),
(22, 'Bomb Scare'),
(23, 'Building Collapse'),
(24, 'Tree Down'),
(27, 'Animal Rescue'),
(28, 'Chimney Fire');

-- --------------------------------------------------------

--
-- Table structure for table `mayday`
--

CREATE TABLE `mayday` (
  `id` int(11) NOT NULL,
  `incident_id` int(11) NOT NULL,
  `status` enum('ACTIVE','CLEARED') NOT NULL DEFAULT 'ACTIVE',
  `started_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `cleared_at` datetime(6) DEFAULT NULL,
  `tac_channel_id` int(11) DEFAULT NULL,
  `who_text` varchar(255) DEFAULT NULL,
  `what_text` varchar(255) DEFAULT NULL,
  `where_text` varchar(255) DEFAULT NULL,
  `air_text` varchar(100) DEFAULT NULL,
  `needs_text` varchar(255) DEFAULT NULL,
  `notes_text` text,
  `mayday_commander_source` enum('local','mutual_aid','manual') DEFAULT NULL,
  `mayday_commander_display` varchar(150) DEFAULT NULL,
  `created_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `updated_at` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `mayday`
--

INSERT INTO `mayday` (`id`, `incident_id`, `status`, `started_at`, `cleared_at`, `tac_channel_id`, `who_text`, `what_text`, `where_text`, `air_text`, `needs_text`, `notes_text`, `mayday_commander_source`, `mayday_commander_display`, `created_at`, `updated_at`) VALUES
(1, 41, 'CLEARED', '2025-12-31 13:40:14.990050', '2025-12-31 13:40:21.644978', NULL, '', '', '', '', '', NULL, NULL, '', '2025-12-31 13:40:14.990050', '2025-12-31 13:40:21.644978'),
(2, 41, 'CLEARED', '2025-12-31 14:06:37.188416', '2025-12-31 14:06:50.243669', NULL, '', '', '', '', '', NULL, NULL, '', '2025-12-31 14:06:37.188416', '2025-12-31 14:06:50.243669'),
(3, 41, 'CLEARED', '2025-12-31 14:06:50.246259', '2025-12-31 14:06:55.743666', NULL, '', '', '', '', '', NULL, NULL, '', '2025-12-31 14:06:50.246259', '2025-12-31 14:06:55.743666'),
(4, 41, 'CLEARED', '2025-12-31 14:17:11.401465', '2025-12-31 14:40:51.741079', NULL, '', '', '', '', '', NULL, NULL, '', '2025-12-31 14:17:11.401465', '2025-12-31 14:40:51.741079'),
(5, 41, 'CLEARED', '2026-01-01 12:14:52.119722', '2026-01-01 12:15:17.569228', NULL, '', '', '', '', '', NULL, NULL, '', '2026-01-01 12:14:52.119722', '2026-01-01 12:15:17.569228'),
(6, 41, 'CLEARED', '2026-01-01 13:00:24.121817', '2026-01-01 13:01:06.842124', NULL, 'asdfasdfsd', 'asdf asdfsdfsd', '', '', '', NULL, NULL, 'Chief 1', '2026-01-01 13:00:24.121817', '2026-01-01 13:01:06.842124'),
(7, 41, 'CLEARED', '2026-01-01 13:08:42.530571', '2026-01-01 13:08:48.992558', NULL, '', '', '', '', '', NULL, NULL, '', '2026-01-01 13:08:42.530571', '2026-01-01 13:08:48.992558'),
(8, 41, 'CLEARED', '2026-01-01 13:09:36.223675', '2026-01-01 13:33:34.783709', NULL, '', '', '', '', '', NULL, NULL, '', '2026-01-01 13:09:36.223675', '2026-01-01 13:33:34.783709'),
(9, 41, 'CLEARED', '2026-01-01 13:33:34.786328', '2026-01-01 13:33:36.159075', NULL, '', '', '', '', '', NULL, NULL, '', '2026-01-01 13:33:34.786328', '2026-01-01 13:33:36.159075'),
(10, 41, 'CLEARED', '2026-01-01 13:33:36.162057', '2026-01-01 13:33:39.678705', NULL, '', '', '', '', '', NULL, NULL, '', '2026-01-01 13:33:36.162057', '2026-01-01 13:33:39.678705'),
(11, 41, 'CLEARED', '2026-01-01 13:33:46.656295', '2026-01-01 13:33:53.928745', NULL, '', '', '', '', '', NULL, NULL, '', '2026-01-01 13:33:46.656295', '2026-01-01 13:33:53.928745'),
(12, 41, 'CLEARED', '2026-01-01 13:33:53.931825', '2026-01-01 14:03:27.426675', NULL, 'asdfasdf sdfasdf', 'asfasdf sdfsdfas', 'asdfsdfasdf dsfsdfsd', 'sfsdfd', 'asdfsdfd', NULL, NULL, 'Chief 1', '2026-01-01 13:33:53.931825', '2026-01-01 14:03:27.426675'),
(13, 41, 'CLEARED', '2026-01-01 14:05:55.609936', '2026-01-01 14:05:56.895298', NULL, '', '', '', '', '', NULL, NULL, '', '2026-01-01 14:05:55.609936', '2026-01-01 14:05:56.895298'),
(14, 41, 'CLEARED', '2026-01-01 14:05:56.899097', '2026-01-01 16:22:56.912955', NULL, 'as', 'ADASD', 'sadasd', 'ASDAS', 'asdasd', NULL, NULL, 'Chief 1', '2026-01-01 14:05:56.899097', '2026-01-01 16:22:56.912955'),
(15, 41, 'CLEARED', '2026-01-01 16:22:56.916178', '2026-01-02 16:07:55.605646', NULL, '', '', '', '', '', NULL, NULL, 'Chief 1', '2026-01-01 16:22:56.916178', '2026-01-02 16:07:55.605646'),
(16, 41, 'CLEARED', '2026-01-02 16:07:55.608117', '2026-01-02 16:09:30.588021', NULL, 'fasfadf asdfsdfd', 'dsdfasdfasdf adfadfasdf', 'asdfadf dfasdfdsds', 'asdfadf', 'adfadfd', NULL, NULL, 'Chief 30', '2026-01-02 16:07:55.608117', '2026-01-02 16:09:30.588021'),
(17, 41, 'ACTIVE', '2026-01-05 11:52:13.000000', NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, NULL, '2026-01-05 11:52:13.000000', '2026-01-05 11:52:13.000000');

-- --------------------------------------------------------

--
-- Table structure for table `mayday_checklist_items`
--

CREATE TABLE `mayday_checklist_items` (
  `id` int(11) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT '0',
  `label` varchar(255) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `mayday_checklist_items`
--

INSERT INTO `mayday_checklist_items` (`id`, `sort_order`, `label`, `is_active`) VALUES
(1, 10, 'Acknowledge MAYDAY. Transmit: \"MAYDAY acknowledged\"', 1),
(2, 20, 'Announce: \"All units hold radio traffic\"', 1),
(3, 30, 'Confirm who / what / where (LUNAR if possible)', 1),
(4, 40, 'Assign / confirm RIT', 1),
(5, 50, 'Establish MAYDAY channel / TAC', 1),
(6, 60, 'Request PAR / Accountability', 1),
(7, 70, 'Control additional resources / EMS staging', 1);

-- --------------------------------------------------------

--
-- Table structure for table `mayday_checklist_status`
--

CREATE TABLE `mayday_checklist_status` (
  `mayday_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `is_done` tinyint(1) NOT NULL DEFAULT '0',
  `done_at` datetime(6) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `mayday_events`
--

CREATE TABLE `mayday_events` (
  `ID` int(11) NOT NULL,
  `incident_id` int(11) NOT NULL,
  `EventTime` datetime(6) NOT NULL,
  `ClearTime` datetime(6) DEFAULT NULL,
  `Unit` varchar(50) DEFAULT NULL,
  `FirefighterName` varchar(100) DEFAULT NULL,
  `LocationText` varchar(255) DEFAULT NULL,
  `AssignmentAtTime` varchar(100) DEFAULT NULL,
  `ResourcesNeeded` varchar(255) DEFAULT NULL,
  `Notes` text,
  `CreatedAt` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `mayday_firefighter`
--

CREATE TABLE `mayday_firefighter` (
  `id` int(11) NOT NULL,
  `mayday_id` int(11) NOT NULL,
  `status_id` int(10) UNSIGNED NOT NULL,
  `name` varchar(64) NOT NULL DEFAULT 'Unknown',
  `who_text` text,
  `what_text` text,
  `where_text` text,
  `air_text` text,
  `needs_text` text,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `cleared_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `mayday_firefighter`
--

INSERT INTO `mayday_firefighter` (`id`, `mayday_id`, `status_id`, `name`, `who_text`, `what_text`, `where_text`, `air_text`, `needs_text`, `created_at`, `updated_at`, `cleared_at`) VALUES
(1, 17, 1, 'Unknown Firefighter', NULL, NULL, NULL, NULL, NULL, '2026-01-05 16:52:13', '2026-01-05 16:52:13', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `mayday_log`
--

CREATE TABLE `mayday_log` (
  `id` int(11) NOT NULL,
  `mayday_id` int(11) NOT NULL,
  `incident_id` int(11) NOT NULL,
  `event_ts` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `event_type` varchar(50) NOT NULL DEFAULT 'NOTE',
  `message` text,
  `entered_by` varchar(150) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `mayday_log`
--

INSERT INTO `mayday_log` (`id`, `mayday_id`, `incident_id`, `event_ts`, `event_type`, `message`, `entered_by`) VALUES
(1, 1, 41, '2025-12-31 13:40:14.995344', 'START', 'MAYDAY started', ''),
(2, 1, 41, '2025-12-31 13:40:21.649555', 'CLEAR', 'MAYDAY cleared', ''),
(3, 2, 41, '2025-12-31 14:06:37.196945', 'START', 'MAYDAY started', ''),
(4, 3, 41, '2025-12-31 14:06:50.249260', 'START', 'MAYDAY started', ''),
(5, 3, 41, '2025-12-31 14:06:55.749535', 'CLEAR', 'MAYDAY cleared', ''),
(6, 4, 41, '2025-12-31 14:17:11.403724', 'START', 'MAYDAY started', ''),
(7, 4, 41, '2025-12-31 14:18:04.805646', 'UPDATE', 'MAYDAY updated', ''),
(8, 4, 41, '2025-12-31 14:18:05.262928', 'UPDATE', 'MAYDAY updated', ''),
(9, 4, 41, '2025-12-31 14:18:06.073256', 'UPDATE', 'MAYDAY updated', ''),
(10, 4, 41, '2025-12-31 14:18:07.329138', 'UPDATE', 'MAYDAY updated', ''),
(11, 4, 41, '2025-12-31 14:18:11.412689', 'UPDATE', 'MAYDAY updated', ''),
(12, 4, 41, '2025-12-31 14:18:13.510985', 'UPDATE', 'MAYDAY updated', ''),
(13, 4, 41, '2025-12-31 14:18:15.358560', 'UPDATE', 'MAYDAY updated', ''),
(14, 4, 41, '2025-12-31 14:18:17.158932', 'UPDATE', 'MAYDAY updated', ''),
(15, 4, 41, '2025-12-31 14:18:17.741516', 'UPDATE', 'MAYDAY updated', ''),
(16, 4, 41, '2025-12-31 14:18:18.748686', 'UPDATE', 'MAYDAY updated', ''),
(17, 4, 41, '2025-12-31 14:18:25.215860', 'UPDATE', 'MAYDAY updated', ''),
(18, 4, 41, '2025-12-31 14:19:26.548516', 'UPDATE', 'MAYDAY updated', ''),
(19, 4, 41, '2025-12-31 14:40:07.001457', 'UPDATE', 'MAYDAY updated', ''),
(20, 4, 41, '2025-12-31 14:40:10.644341', 'UPDATE', 'MAYDAY updated', ''),
(21, 4, 41, '2025-12-31 14:40:12.893537', 'UPDATE', 'MAYDAY updated', ''),
(22, 4, 41, '2025-12-31 14:40:15.233540', 'UPDATE', 'MAYDAY updated', ''),
(23, 4, 41, '2025-12-31 14:40:23.529458', 'UPDATE', 'MAYDAY updated', ''),
(24, 4, 41, '2025-12-31 14:40:26.962284', 'UPDATE', 'MAYDAY updated', ''),
(25, 4, 41, '2025-12-31 14:40:30.338792', 'UPDATE', 'MAYDAY updated', ''),
(26, 4, 41, '2025-12-31 14:40:51.743210', 'CLEAR', 'MAYDAY cleared', ''),
(27, 5, 41, '2026-01-01 12:14:52.124028', 'START', 'MAYDAY started', ''),
(28, 5, 41, '2026-01-01 12:14:52.149661', 'NOTE', 'MAYDAY activated', ''),
(29, 5, 41, '2026-01-01 12:15:17.573441', 'CLEAR', 'MAYDAY cleared', ''),
(30, 5, 41, '2026-01-01 12:15:17.585566', 'NOTE', 'MAYDAY cleared', ''),
(31, 6, 41, '2026-01-01 13:00:24.126513', 'START', 'MAYDAY started', ''),
(32, 6, 41, '2026-01-01 13:00:24.147019', 'NOTE', 'MAYDAY activated', ''),
(33, 6, 41, '2026-01-01 13:00:49.484500', 'NOTE', 'Commander: Chief 1', ''),
(34, 6, 41, '2026-01-01 13:00:49.949540', 'UPDATE', 'MAYDAY updated', ''),
(35, 6, 41, '2026-01-01 13:00:55.082998', 'UPDATE', 'MAYDAY updated', ''),
(36, 6, 41, '2026-01-01 13:00:57.658112', 'UPDATE', 'MAYDAY updated', ''),
(37, 6, 41, '2026-01-01 13:01:06.845414', 'CLEAR', 'MAYDAY cleared', ''),
(38, 6, 41, '2026-01-01 13:01:06.864676', 'NOTE', 'MAYDAY cleared', ''),
(39, 7, 41, '2026-01-01 13:08:42.533545', 'START', 'MAYDAY started', ''),
(40, 7, 41, '2026-01-01 13:08:42.547587', 'NOTE', 'MAYDAY activated', ''),
(41, 7, 41, '2026-01-01 13:08:48.995446', 'CLEAR', 'MAYDAY cleared', ''),
(42, 7, 41, '2026-01-01 13:08:49.022126', 'NOTE', 'MAYDAY cleared', ''),
(43, 8, 41, '2026-01-01 13:09:36.225788', 'START', 'MAYDAY started', ''),
(44, 8, 41, '2026-01-01 13:09:36.239271', 'NOTE', 'MAYDAY activated', ''),
(45, 9, 41, '2026-01-01 13:33:34.787784', 'START', 'MAYDAY started', ''),
(46, 9, 41, '2026-01-01 13:33:34.821216', 'NOTE', 'MAYDAY activated', ''),
(47, 10, 41, '2026-01-01 13:33:36.164116', 'START', 'MAYDAY started', ''),
(48, 10, 41, '2026-01-01 13:33:36.186332', 'NOTE', 'MAYDAY activated', ''),
(49, 10, 41, '2026-01-01 13:33:39.681548', 'CLEAR', 'MAYDAY cleared', ''),
(50, 10, 41, '2026-01-01 13:33:39.703304', 'NOTE', 'MAYDAY cleared', ''),
(51, 11, 41, '2026-01-01 13:33:46.658545', 'START', 'MAYDAY started', ''),
(52, 11, 41, '2026-01-01 13:33:46.696698', 'NOTE', 'MAYDAY activated', ''),
(53, 11, 41, '2026-01-01 13:33:49.383738', 'CHECK', 'Checklist: Acknowledge MAYDAY. Transmit: \"MAYDAY acknowledged\" DONE', ''),
(54, 11, 41, '2026-01-01 13:33:49.916872', 'CHECK', 'Checklist: Confirm who / what / where (LUNAR if possible) DONE', ''),
(55, 11, 41, '2026-01-01 13:33:50.505509', 'CHECK', 'Checklist: Request PAR (as appropriate) and verify accountability DONE', ''),
(56, 12, 41, '2026-01-01 13:33:53.934135', 'START', 'MAYDAY started', ''),
(57, 12, 41, '2026-01-01 13:33:53.950499', 'NOTE', 'MAYDAY activated', ''),
(58, 12, 41, '2026-01-01 14:02:38.564235', 'NOTE', 'Commander: Chief 1', ''),
(59, 12, 41, '2026-01-01 14:02:39.035020', 'UPDATE', 'MAYDAY updated', ''),
(60, 12, 41, '2026-01-01 14:02:41.413885', 'UPDATE', 'MAYDAY updated', ''),
(61, 12, 41, '2026-01-01 14:02:45.875896', 'UPDATE', 'MAYDAY updated', ''),
(62, 12, 41, '2026-01-01 14:02:48.738363', 'UPDATE', 'MAYDAY updated', ''),
(63, 12, 41, '2026-01-01 14:02:51.806837', 'UPDATE', 'MAYDAY updated', ''),
(64, 12, 41, '2026-01-01 14:02:54.841138', 'UPDATE', 'MAYDAY updated', ''),
(65, 12, 41, '2026-01-01 14:02:56.355877', 'UPDATE', 'MAYDAY updated', ''),
(66, 12, 41, '2026-01-01 14:02:58.048557', 'CHECK', 'Checklist: Acknowledge MAYDAY. Transmit: \"MAYDAY acknowledged\" DONE', ''),
(67, 12, 41, '2026-01-01 14:03:00.986381', 'CHECK', 'Checklist: Assign a dedicated MAYDAY IC (separate from Incident IC if possible) DONE', ''),
(68, 12, 41, '2026-01-01 14:03:04.828047', 'CHECK', 'Checklist: Request PAR (as appropriate) and verify accountability DONE', ''),
(69, 12, 41, '2026-01-01 14:03:08.162664', 'CHECK', 'Checklist: Acknowledge MAYDAY. Transmit: \"MAYDAY acknowledged\" DONE', ''),
(70, 12, 41, '2026-01-01 14:03:11.733329', 'CHECK', 'Checklist: Announce: \"All units hold radio traffic\" DONE', ''),
(71, 12, 41, '2026-01-01 14:03:27.428512', 'CLEAR', 'MAYDAY cleared', ''),
(72, 12, 41, '2026-01-01 14:03:27.447562', 'NOTE', 'MAYDAY cleared', ''),
(73, 13, 41, '2026-01-01 14:05:55.612172', 'START', 'MAYDAY started', ''),
(74, 13, 41, '2026-01-01 14:05:55.632446', 'NOTE', 'MAYDAY activated', ''),
(75, 14, 41, '2026-01-01 14:05:56.900526', 'START', 'MAYDAY started', ''),
(76, 14, 41, '2026-01-01 14:05:56.913807', 'NOTE', 'MAYDAY activated', ''),
(77, 14, 41, '2026-01-01 14:05:59.915933', 'NOTE', 'Commander: Chief 1', ''),
(78, 14, 41, '2026-01-01 14:06:00.374099', 'UPDATE', 'MAYDAY updated', ''),
(79, 14, 41, '2026-01-01 14:06:03.194601', 'UPDATE', 'MAYDAY updated', ''),
(80, 14, 41, '2026-01-01 14:06:05.865948', 'UPDATE', 'MAYDAY updated', ''),
(81, 14, 41, '2026-01-01 14:06:07.146832', 'UPDATE', 'MAYDAY updated', ''),
(82, 14, 41, '2026-01-01 14:06:08.155076', 'UPDATE', 'MAYDAY updated', ''),
(83, 14, 41, '2026-01-01 14:06:09.333161', 'UPDATE', 'MAYDAY updated', ''),
(84, 14, 41, '2026-01-01 14:06:10.459677', 'UPDATE', 'MAYDAY updated', ''),
(85, 14, 41, '2026-01-01 14:06:11.966667', 'CHECK', 'Checklist: Acknowledge MAYDAY. Transmit: \"MAYDAY acknowledged\" DONE', ''),
(86, 14, 41, '2026-01-01 14:06:14.231964', 'CHECK', 'Checklist: Switch to / confirm MAYDAY TAC channel DONE', ''),
(87, 14, 41, '2026-01-01 14:06:15.970070', 'CHECK', 'Checklist: Deploy RIT / RIC and confirm entry point DONE', ''),
(88, 14, 41, '2026-01-01 14:06:17.208061', 'CHECK', 'Checklist: Confirm who / what / where (LUNAR if possible) DONE', ''),
(89, 14, 41, '2026-01-01 14:06:42.927741', 'NOTE', 'Commander: Chief 30', ''),
(90, 14, 41, '2026-01-01 14:06:43.396144', 'UPDATE', 'MAYDAY updated', ''),
(91, 14, 41, '2026-01-01 14:06:46.000781', 'UPDATE', 'MAYDAY updated', ''),
(92, 14, 41, '2026-01-01 14:06:49.080954', 'UPDATE', 'MAYDAY updated', ''),
(93, 14, 41, '2026-01-01 14:06:51.690433', 'UPDATE', 'MAYDAY updated', ''),
(94, 14, 41, '2026-01-01 16:22:42.938263', 'NOTE', 'Commander: Chief 1', ''),
(95, 14, 41, '2026-01-01 16:22:43.409795', 'UPDATE', 'MAYDAY updated', ''),
(96, 14, 41, '2026-01-01 16:22:46.795029', 'UPDATE', 'MAYDAY updated', ''),
(97, 14, 41, '2026-01-01 16:22:47.502360', 'UPDATE', 'MAYDAY updated', ''),
(98, 14, 41, '2026-01-01 16:22:48.334306', 'UPDATE', 'MAYDAY updated', ''),
(99, 14, 41, '2026-01-01 16:22:55.086527', 'CHECK', 'Checklist: Acknowledge MAYDAY. Transmit: \"MAYDAY acknowledged\" DONE', ''),
(100, 15, 41, '2026-01-01 16:22:56.919145', 'START', 'MAYDAY started', ''),
(101, 15, 41, '2026-01-01 16:22:56.945009', 'NOTE', 'MAYDAY activated', ''),
(102, 15, 41, '2026-01-01 16:22:59.531957', 'NOTE', 'Commander: Chief 1', ''),
(103, 15, 41, '2026-01-01 16:23:00.021874', 'UPDATE', 'MAYDAY updated', ''),
(104, 15, 41, '2026-01-01 16:23:02.257160', 'UPDATE', 'MAYDAY updated', ''),
(105, 15, 41, '2026-01-01 16:23:02.929737', 'UPDATE', 'MAYDAY updated', ''),
(106, 15, 41, '2026-01-01 16:23:12.329105', 'CHECK', 'Checklist: Confirm who / what / where (LUNAR if possible) DONE', ''),
(107, 15, 41, '2026-01-01 16:23:15.835713', 'CHECK', 'Checklist: Deploy RIT / RIC and confirm entry point DONE', ''),
(108, 15, 41, '2026-01-01 16:23:19.603919', 'CHECK', 'Checklist: Request PAR (as appropriate) and verify accountability DONE', ''),
(109, 16, 41, '2026-01-02 16:07:55.609923', 'START', 'MAYDAY started', ''),
(110, 16, 41, '2026-01-02 16:07:55.637560', 'NOTE', 'MAYDAY activated', ''),
(111, 16, 41, '2026-01-02 16:07:59.807112', 'NOTE', 'Commander: Chief 30', ''),
(112, 16, 41, '2026-01-02 16:08:00.247930', 'UPDATE', 'MAYDAY updated', ''),
(113, 16, 41, '2026-01-02 16:08:02.458446', 'UPDATE', 'MAYDAY updated', ''),
(114, 16, 41, '2026-01-02 16:08:07.771554', 'UPDATE', 'MAYDAY updated', ''),
(115, 16, 41, '2026-01-02 16:08:10.795047', 'UPDATE', 'MAYDAY updated', ''),
(116, 16, 41, '2026-01-02 16:08:12.827976', 'UPDATE', 'MAYDAY updated', ''),
(117, 16, 41, '2026-01-02 16:08:15.734462', 'UPDATE', 'MAYDAY updated', ''),
(118, 16, 41, '2026-01-02 16:08:17.454383', 'UPDATE', 'MAYDAY updated', ''),
(119, 16, 41, '2026-01-02 16:08:19.509726', 'UPDATE', 'MAYDAY updated', ''),
(120, 16, 41, '2026-01-02 16:08:21.094876', 'CHECK', 'Checklist: Acknowledge MAYDAY. Transmit: \"MAYDAY acknowledged\" DONE', ''),
(121, 16, 41, '2026-01-02 16:08:25.147242', 'CHECK', 'Checklist: Confirm who / what / where (LUNAR if possible) DONE', ''),
(122, 16, 41, '2026-01-02 16:08:54.042724', 'CHECK', 'Checklist: Deploy RIT / RIC and confirm entry point DONE', ''),
(123, 16, 41, '2026-01-02 16:09:09.464043', 'NOTE', 'asdfadfasdfsdfsdfds asdfasdfsadfsdfsdfsadasfd', ''),
(124, 16, 41, '2026-01-02 16:09:30.590349', 'CLEAR', 'MAYDAY cleared', ''),
(125, 16, 41, '2026-01-02 16:09:30.607829', 'NOTE', 'MAYDAY cleared', '');

-- --------------------------------------------------------

--
-- Table structure for table `mayday_status`
--

CREATE TABLE `mayday_status` (
  `id` int(10) UNSIGNED NOT NULL,
  `dept_id` int(10) UNSIGNED NOT NULL,
  `code` varchar(24) NOT NULL,
  `label` varchar(64) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT '0',
  `color_class` varchar(32) DEFAULT NULL,
  `is_terminal` tinyint(1) NOT NULL DEFAULT '0',
  `is_system` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `mayday_status`
--

INSERT INTO `mayday_status` (`id`, `dept_id`, `code`, `label`, `sort_order`, `color_class`, `is_terminal`, `is_system`, `created_at`, `updated_at`) VALUES
(1, 2, 'ACTIVE', 'Active', 10, 'btn-danger', 0, 1, '2026-01-05 16:46:20', '2026-01-05 16:46:20'),
(2, 2, 'CONTACT', 'Contact Made', 20, 'btn-warning', 0, 1, '2026-01-05 16:46:20', '2026-01-05 16:46:20'),
(3, 2, 'LOCATED', 'Located', 30, 'btn-warning', 0, 1, '2026-01-05 16:46:20', '2026-01-05 16:46:20'),
(4, 2, 'EXTRICATING', 'Extricating', 40, 'btn-primary', 0, 1, '2026-01-05 16:46:20', '2026-01-05 16:46:20'),
(5, 2, 'REMOVED', 'Removed', 50, 'btn-success', 1, 1, '2026-01-05 16:46:20', '2026-01-05 16:46:20'),
(6, 2, 'CLEARED', 'Cleared', 60, 'btn-secondary', 1, 1, '2026-01-05 16:46:20', '2026-01-05 16:46:20');

-- --------------------------------------------------------

--
-- Table structure for table `mutual_aid_departments`
--

CREATE TABLE `mutual_aid_departments` (
  `id` int(11) NOT NULL,
  `department_name` varchar(255) NOT NULL,
  `designation` varchar(50) DEFAULT NULL,
  `station_id` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

--
-- Dumping data for table `mutual_aid_departments`
--

INSERT INTO `mutual_aid_departments` (`id`, `department_name`, `designation`, `station_id`, `created_at`) VALUES
(1, 'Albemarle County Fire & Rescue', 'ACFR', '', '2025-12-22 16:09:42'),
(3, 'Crozet Volunteer Fire Department', 'CVFD', '', '2025-12-23 09:12:01'),
(4, 'Charlottesville Fire Department', 'CFD', '', '2025-12-23 09:15:54'),
(5, 'Nelson County Volunteer Fire Department', 'NCVFD', '', '2025-12-23 09:26:24');

-- --------------------------------------------------------

--
-- Table structure for table `notifications_log`
--

CREATE TABLE `notifications_log` (
  `id` int(11) NOT NULL,
  `incident_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `notification_types`
--

CREATE TABLE `notification_types` (
  `ID` int(11) NOT NULL,
  `Name` varchar(50) NOT NULL,
  `Category` varchar(50) DEFAULT NULL,
  `SortOrder` int(11) NOT NULL DEFAULT '100',
  `IsActive` tinyint(1) NOT NULL DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `par_events`
--

CREATE TABLE `par_events` (
  `ID` int(11) NOT NULL,
  `incident_id` int(11) NOT NULL,
  `ApparatusLabel` varchar(100) NOT NULL DEFAULT '',
  `ParStatus` varchar(20) NOT NULL DEFAULT '',
  `EventTime` datetime(6) NOT NULL,
  `Scope` varchar(100) NOT NULL,
  `Notes` varchar(255) DEFAULT NULL,
  `CreatedAt` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Table structure for table `staffing`
--

CREATE TABLE `staffing` (
  `ID` int(11) NOT NULL,
  `incident_id` int(11) NOT NULL,
  `ApparatusLabel` varchar(100) NOT NULL,
  `NumFirefighters` int(11) NOT NULL DEFAULT '0',
  `UpdatedAt` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data for table `staffing`
--

INSERT INTO `staffing` (`ID`, `incident_id`, `ApparatusLabel`, `NumFirefighters`, `UpdatedAt`) VALUES
(31, 33, 'Engine 52', 2, '2025-12-16 10:03:33'),
(32, 33, 'Engine 56', 3, '2025-12-16 10:03:33'),
(33, 32, 'Engine 52', 3, '2025-12-16 10:19:37'),
(34, 34, 'Engine 52', 3, '2025-12-18 14:45:49'),
(35, 35, 'Engine 52', 3, '2025-12-22 13:35:19'),
(36, 35, 'Engine 56', 3, '2025-12-22 13:35:19'),
(37, 36, 'Eng31', 3, '2025-12-26 14:01:26'),
(38, 41, 'Eng 56', 4, '2026-01-06 09:50:41'),
(39, 38, 'Eng 32', 3, '2025-12-26 17:02:13'),
(41, 43, 'Eng 56', 3, '2025-12-28 20:37:39'),
(42, 43, 'Eng 16', 4, '2025-12-28 20:37:39'),
(43, 43, 'Truck 23', 4, '2025-12-28 20:37:39'),
(44, 43, 'Eng 8', 4, '2025-12-28 20:37:39'),
(48, 41, 'Eng 16', 4, '2026-01-06 09:50:41'),
(49, 41, 'Eng 8', 4, '2026-01-06 09:50:41'),
(50, 41, 'Engine 56', 4, '2026-01-06 09:50:41'),
(51, 41, 'Truck 23', 4, '2026-01-06 09:50:41');

-- --------------------------------------------------------

--
-- Table structure for table `tac_channels`
--

CREATE TABLE `tac_channels` (
  `ID` int(11) NOT NULL,
  `incident_id` int(11) NOT NULL,
  `ChannelLabel` varchar(20) NOT NULL,
  `UsageLabel` varchar(100) DEFAULT NULL,
  `AssignedUnits` varchar(255) DEFAULT NULL,
  `CreatedAt` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `UpdatedAt` datetime(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `apparatus_log`
--
ALTER TABLE `apparatus_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `apparatus_responding`
--
ALTER TABLE `apparatus_responding`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_incident_unit` (`incident_id`,`apparatus_ID`),
  ADD KEY `incident_id` (`incident_id`),
  ADD KEY `status` (`status`),
  ADD KEY `incident_id_2` (`incident_id`),
  ADD KEY `apparatus_type` (`apparatus_type`),
  ADD KEY `apparatus_type_2` (`apparatus_type`),
  ADD KEY `idx_appresp_mutual` (`incident_mutual_aid_id`);

--
-- Indexes for table `apparatus_status`
--
ALTER TABLE `apparatus_status`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `uq_apparatus_status_name` (`ApparatusStatus`);

--
-- Indexes for table `apparatus_status_events`
--
ALTER TABLE `apparatus_status_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ase_incident` (`incident_id`),
  ADD KEY `idx_ase_ar` (`apparatus_responding_id`),
  ADD KEY `idx_ase_time` (`event_time`);

--
-- Indexes for table `apparatus_types`
--
ALTER TABLE `apparatus_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uniq_type` (`ApparatusType`),
  ADD KEY `id` (`id`);

--
-- Indexes for table `assignments`
--
ALTER TABLE `assignments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_assign_incident` (`incident_id`),
  ADD KEY `fk_assign_apparatus` (`apparatus_id`),
  ADD KEY `fk_assign_type` (`assignment_type_id`);

--
-- Indexes for table `assignment_types`
--
ALTER TABLE `assignment_types`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `benchmark_events`
--
ALTER TABLE `benchmark_events`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `fk_bench_incident` (`incident_id`),
  ADD KEY `fk_bench_type` (`benchmark_type_id`);

--
-- Indexes for table `benchmark_types`
--
ALTER TABLE `benchmark_types`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `checklist_items`
--
ALTER TABLE `checklist_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `command_assignments`
--
ALTER TABLE `command_assignments`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `fk_cmdassign_incident` (`incident_id`),
  ADD KEY `fk_cmdassign_apparatus` (`apparatus_responding_id`),
  ADD KEY `fk_cmdassign_type` (`assignment_type_id`),
  ADD KEY `fk_cmdassign_tac` (`tac_channel_id`);

--
-- Indexes for table `command_assignment_events`
--
ALTER TABLE `command_assignment_events`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `idx_cae_incident` (`incident_id`),
  ADD KEY `idx_cae_assignment` (`assignment_id`);

--
-- Indexes for table `department`
--
ALTER TABLE `department`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_department_designation` (`designation`);

--
-- Indexes for table `department_apparatus`
--
ALTER TABLE `department_apparatus`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_dept_apparatus_department` (`dept_id`);

--
-- Indexes for table `department_command`
--
ALTER TABLE `department_command`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_cmd_dept` (`dept_id`),
  ADD KEY `fk_cmd_rank` (`rank_id`);

--
-- Indexes for table `department_command_rank`
--
ALTER TABLE `department_command_rank`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `rank_name` (`rank_name`);

--
-- Indexes for table `department_mutual_aid`
--
ALTER TABLE `department_mutual_aid`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_home_mutual` (`home_dept_id`,`mutual_dept_id`),
  ADD KEY `idx_home` (`home_dept_id`),
  ADD KEY `idx_mutual` (`mutual_dept_id`);

--
-- Indexes for table `department_mutual_aid_apparatus`
--
ALTER TABLE `department_mutual_aid_apparatus`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_home` (`home_dept_id`),
  ADD KEY `idx_mutual` (`mutual_dept_id`);

--
-- Indexes for table `department_mutual_aid_apparatus_template`
--
ALTER TABLE `department_mutual_aid_apparatus_template`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_home` (`home_dept_id`),
  ADD KEY `idx_mutual` (`mutual_dept_id`);

--
-- Indexes for table `department_mutual_aid_command_staff_template`
--
ALTER TABLE `department_mutual_aid_command_staff_template`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_home` (`home_dept_id`),
  ADD KEY `idx_mutual` (`mutual_dept_id`);

--
-- Indexes for table `department_mutual_aid_partners`
--
ALTER TABLE `department_mutual_aid_partners`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_partner_unique` (`dept_id`,`partner_name`),
  ADD KEY `idx_partner_dept` (`dept_id`);

--
-- Indexes for table `dept_command_staff_template`
--
ALTER TABLE `dept_command_staff_template`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_dept_rank` (`dept_id`,`rank_designation`),
  ADD KEY `idx_dept` (`dept_id`);

--
-- Indexes for table `error_log`
--
ALTER TABLE `error_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `firehouse_checklist`
--
ALTER TABLE `firehouse_checklist`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `incidents`
--
ALTER TABLE `incidents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_incident_number` (`incident_number`),
  ADD KEY `idx_incidents_occurred_at` (`IncidentDT`),
  ADD KEY `idx_incidents_status` (`status`),
  ADD KEY `idx_incidents_geo` (`longitude`),
  ADD KEY `idx_incident_number` (`incident_number`),
  ADD KEY `type` (`type`),
  ADD KEY `type_2` (`type`),
  ADD KEY `type_3` (`type`),
  ADD KEY `fk_incidents_department` (`dept_id`),
  ADD KEY `idx_incidents_closed_at` (`closed_at`);

--
-- Indexes for table `incident_alarm_log`
--
ALTER TABLE `incident_alarm_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_incident_alarm_log_incident` (`incident_id`);

--
-- Indexes for table `incident_checklist_responses`
--
ALTER TABLE `incident_checklist_responses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_icr_incident` (`incident_id`),
  ADD KEY `fk_icr_checklist_items` (`checklist_id`);

--
-- Indexes for table `incident_command_resources`
--
ALTER TABLE `incident_command_resources`
  ADD PRIMARY KEY (`id`),
  ADD KEY `incident_id` (`incident_id`);

--
-- Indexes for table `incident_elements`
--
ALTER TABLE `incident_elements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_incident` (`incident_id`),
  ADD KEY `idx_parent` (`parent_element_id`);

--
-- Indexes for table `incident_mutual_aid`
--
ALTER TABLE `incident_mutual_aid`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ima_incident` (`incident_id`),
  ADD KEY `fk_incident_mutual_aid_rank` (`rank_id`);

--
-- Indexes for table `incident_mutual_aid_departments`
--
ALTER TABLE `incident_mutual_aid_departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_incident_ma_dept` (`incident_id`,`ma_department_id`),
  ADD KEY `incident_id` (`incident_id`),
  ADD KEY `ma_department_id` (`ma_department_id`);

--
-- Indexes for table `incident_notifications`
--
ALTER TABLE `incident_notifications`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `fk_notif_incident` (`incident_id`),
  ADD KEY `fk_notif_type` (`notification_type_id`);

--
-- Indexes for table `incident_sizeup`
--
ALTER TABLE `incident_sizeup`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_sizeup_incident` (`incident_id`);

--
-- Indexes for table `incident_tac_roles`
--
ALTER TABLE `incident_tac_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_incident_role` (`incident_id`,`role`);

--
-- Indexes for table `incident_types`
--
ALTER TABLE `incident_types`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `mayday`
--
ALTER TABLE `mayday`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_mayday_incidents_incident_id` (`incident_id`),
  ADD KEY `fk_mayday_tac_channels_tac_channel_id` (`tac_channel_id`);

--
-- Indexes for table `mayday_checklist_items`
--
ALTER TABLE `mayday_checklist_items`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `mayday_checklist_status`
--
ALTER TABLE `mayday_checklist_status`
  ADD PRIMARY KEY (`mayday_id`,`item_id`),
  ADD KEY `fk_mcs_item` (`item_id`);

--
-- Indexes for table `mayday_events`
--
ALTER TABLE `mayday_events`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `fk_mayday_incident` (`incident_id`);

--
-- Indexes for table `mayday_firefighter`
--
ALTER TABLE `mayday_firefighter`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_mayday_ff_mayday` (`mayday_id`),
  ADD KEY `idx_mayday_ff_status` (`status_id`);

--
-- Indexes for table `mayday_log`
--
ALTER TABLE `mayday_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_mayday_log_mayday_id` (`mayday_id`),
  ADD KEY `fk_mayday_log_incident_id` (`incident_id`);

--
-- Indexes for table `mayday_status`
--
ALTER TABLE `mayday_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_mayday_status_dept_code` (`dept_id`,`code`),
  ADD KEY `idx_mayday_status_dept_sort` (`dept_id`,`sort_order`);

--
-- Indexes for table `mutual_aid_departments`
--
ALTER TABLE `mutual_aid_departments`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_ma_dept` (`department_name`,`designation`,`station_id`);

--
-- Indexes for table `notifications_log`
--
ALTER TABLE `notifications_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `incident_id` (`incident_id`);

--
-- Indexes for table `notification_types`
--
ALTER TABLE `notification_types`
  ADD PRIMARY KEY (`ID`);

--
-- Indexes for table `par_events`
--
ALTER TABLE `par_events`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `fk_par_incident` (`incident_id`);

--
-- Indexes for table `staffing`
--
ALTER TABLE `staffing`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `incident_id` (`incident_id`);

--
-- Indexes for table `tac_channels`
--
ALTER TABLE `tac_channels`
  ADD PRIMARY KEY (`ID`),
  ADD KEY `fk_tac_incident` (`incident_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `apparatus_log`
--
ALTER TABLE `apparatus_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `apparatus_responding`
--
ALTER TABLE `apparatus_responding`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=90;

--
-- AUTO_INCREMENT for table `apparatus_status`
--
ALTER TABLE `apparatus_status`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `apparatus_status_events`
--
ALTER TABLE `apparatus_status_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `apparatus_types`
--
ALTER TABLE `apparatus_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `assignments`
--
ALTER TABLE `assignments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assignment_types`
--
ALTER TABLE `assignment_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `benchmark_events`
--
ALTER TABLE `benchmark_events`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `benchmark_types`
--
ALTER TABLE `benchmark_types`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `checklist_items`
--
ALTER TABLE `checklist_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=40;

--
-- AUTO_INCREMENT for table `command_assignments`
--
ALTER TABLE `command_assignments`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `command_assignment_events`
--
ALTER TABLE `command_assignment_events`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `department`
--
ALTER TABLE `department`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `department_apparatus`
--
ALTER TABLE `department_apparatus`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `department_command`
--
ALTER TABLE `department_command`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `department_command_rank`
--
ALTER TABLE `department_command_rank`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `department_mutual_aid`
--
ALTER TABLE `department_mutual_aid`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `department_mutual_aid_apparatus`
--
ALTER TABLE `department_mutual_aid_apparatus`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `department_mutual_aid_apparatus_template`
--
ALTER TABLE `department_mutual_aid_apparatus_template`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `department_mutual_aid_command_staff_template`
--
ALTER TABLE `department_mutual_aid_command_staff_template`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `department_mutual_aid_partners`
--
ALTER TABLE `department_mutual_aid_partners`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `dept_command_staff_template`
--
ALTER TABLE `dept_command_staff_template`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `error_log`
--
ALTER TABLE `error_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `firehouse_checklist`
--
ALTER TABLE `firehouse_checklist`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `incidents`
--
ALTER TABLE `incidents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=44;

--
-- AUTO_INCREMENT for table `incident_alarm_log`
--
ALTER TABLE `incident_alarm_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `incident_checklist_responses`
--
ALTER TABLE `incident_checklist_responses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `incident_command_resources`
--
ALTER TABLE `incident_command_resources`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `incident_elements`
--
ALTER TABLE `incident_elements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `incident_mutual_aid`
--
ALTER TABLE `incident_mutual_aid`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `incident_mutual_aid_departments`
--
ALTER TABLE `incident_mutual_aid_departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `incident_notifications`
--
ALTER TABLE `incident_notifications`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `incident_sizeup`
--
ALTER TABLE `incident_sizeup`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `incident_tac_roles`
--
ALTER TABLE `incident_tac_roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `incident_types`
--
ALTER TABLE `incident_types`
  MODIFY `ID` int(11) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=29;

--
-- AUTO_INCREMENT for table `mayday`
--
ALTER TABLE `mayday`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `mayday_checklist_items`
--
ALTER TABLE `mayday_checklist_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `mayday_events`
--
ALTER TABLE `mayday_events`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `mayday_firefighter`
--
ALTER TABLE `mayday_firefighter`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `mayday_log`
--
ALTER TABLE `mayday_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=126;

--
-- AUTO_INCREMENT for table `mayday_status`
--
ALTER TABLE `mayday_status`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `mutual_aid_departments`
--
ALTER TABLE `mutual_aid_departments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `notifications_log`
--
ALTER TABLE `notifications_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notification_types`
--
ALTER TABLE `notification_types`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `par_events`
--
ALTER TABLE `par_events`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `staffing`
--
ALTER TABLE `staffing`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `tac_channels`
--
ALTER TABLE `tac_channels`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `apparatus_responding`
--
ALTER TABLE `apparatus_responding`
  ADD CONSTRAINT `apparatus_responding_ibfk_1` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_app_incident` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `apparatus_status_events`
--
ALTER TABLE `apparatus_status_events`
  ADD CONSTRAINT `fk_ase_ar` FOREIGN KEY (`apparatus_responding_id`) REFERENCES `apparatus_responding` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_ase_incidents` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `assignments`
--
ALTER TABLE `assignments`
  ADD CONSTRAINT `fk_assign_apparatus` FOREIGN KEY (`apparatus_id`) REFERENCES `apparatus_responding` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_assign_incident` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_assign_type` FOREIGN KEY (`assignment_type_id`) REFERENCES `assignment_types` (`id`);

--
-- Constraints for table `benchmark_events`
--
ALTER TABLE `benchmark_events`
  ADD CONSTRAINT `fk_bench_incident` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_bench_type` FOREIGN KEY (`benchmark_type_id`) REFERENCES `benchmark_types` (`ID`);

--
-- Constraints for table `command_assignments`
--
ALTER TABLE `command_assignments`
  ADD CONSTRAINT `fk_cmdassign_apparatus` FOREIGN KEY (`apparatus_responding_id`) REFERENCES `apparatus_responding` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_cmdassign_incident` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cmdassign_tac` FOREIGN KEY (`tac_channel_id`) REFERENCES `tac_channels` (`ID`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_cmdassign_type` FOREIGN KEY (`assignment_type_id`) REFERENCES `assignment_types` (`id`);

--
-- Constraints for table `department_apparatus`
--
ALTER TABLE `department_apparatus`
  ADD CONSTRAINT `fk_dept_apparatus_department` FOREIGN KEY (`dept_id`) REFERENCES `department` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `department_command`
--
ALTER TABLE `department_command`
  ADD CONSTRAINT `fk_cmd_dept` FOREIGN KEY (`dept_id`) REFERENCES `department` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cmd_rank` FOREIGN KEY (`rank_id`) REFERENCES `department_command_rank` (`id`);

--
-- Constraints for table `department_mutual_aid`
--
ALTER TABLE `department_mutual_aid`
  ADD CONSTRAINT `fk_dma_home` FOREIGN KEY (`home_dept_id`) REFERENCES `department` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_dma_mutual` FOREIGN KEY (`mutual_dept_id`) REFERENCES `department` (`id`);

--
-- Constraints for table `department_mutual_aid_partners`
--
ALTER TABLE `department_mutual_aid_partners`
  ADD CONSTRAINT `fk_partner_dept` FOREIGN KEY (`dept_id`) REFERENCES `department` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `dept_command_staff_template`
--
ALTER TABLE `dept_command_staff_template`
  ADD CONSTRAINT `fk_dcst_dept` FOREIGN KEY (`dept_id`) REFERENCES `department` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `incidents`
--
ALTER TABLE `incidents`
  ADD CONSTRAINT `fk_incidents_department` FOREIGN KEY (`dept_id`) REFERENCES `department` (`id`),
  ADD CONSTRAINT `fk_incidents_type` FOREIGN KEY (`type`) REFERENCES `incident_types` (`ID`);

--
-- Constraints for table `incident_alarm_log`
--
ALTER TABLE `incident_alarm_log`
  ADD CONSTRAINT `fk_incident_alarm_log_incident` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `incident_checklist_responses`
--
ALTER TABLE `incident_checklist_responses`
  ADD CONSTRAINT `fk_icr_checklist_items` FOREIGN KEY (`checklist_id`) REFERENCES `checklist_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_icr_incident` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `incident_elements`
--
ALTER TABLE `incident_elements`
  ADD CONSTRAINT `fk_elements_incident` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `incident_mutual_aid`
--
ALTER TABLE `incident_mutual_aid`
  ADD CONSTRAINT `fk_ima_rank` FOREIGN KEY (`rank_id`) REFERENCES `department_command_rank` (`id`),
  ADD CONSTRAINT `fk_incident_mutual_aid_rank` FOREIGN KEY (`rank_id`) REFERENCES `department_command_rank` (`id`);

--
-- Constraints for table `incident_notifications`
--
ALTER TABLE `incident_notifications`
  ADD CONSTRAINT `fk_notif_incident` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_notif_type` FOREIGN KEY (`notification_type_id`) REFERENCES `notification_types` (`ID`);

--
-- Constraints for table `incident_sizeup`
--
ALTER TABLE `incident_sizeup`
  ADD CONSTRAINT `fk_sizeup_incident` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `incident_tac_roles`
--
ALTER TABLE `incident_tac_roles`
  ADD CONSTRAINT `incident_tac_roles_ibfk_1` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `mayday`
--
ALTER TABLE `mayday`
  ADD CONSTRAINT `fk_mayday_incidents_incident_id` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mayday_tac_channels_tac_channel_id` FOREIGN KEY (`tac_channel_id`) REFERENCES `tac_channels` (`ID`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `mayday_checklist_status`
--
ALTER TABLE `mayday_checklist_status`
  ADD CONSTRAINT `fk_mcs_item` FOREIGN KEY (`item_id`) REFERENCES `mayday_checklist_items` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_mcs_mayday` FOREIGN KEY (`mayday_id`) REFERENCES `mayday` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `mayday_events`
--
ALTER TABLE `mayday_events`
  ADD CONSTRAINT `fk_mayday_incident` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `mayday_firefighter`
--
ALTER TABLE `mayday_firefighter`
  ADD CONSTRAINT `fk_mayday_ff_mayday` FOREIGN KEY (`mayday_id`) REFERENCES `mayday` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mayday_ff_status` FOREIGN KEY (`status_id`) REFERENCES `mayday_status` (`id`) ON UPDATE CASCADE;

--
-- Constraints for table `mayday_log`
--
ALTER TABLE `mayday_log`
  ADD CONSTRAINT `fk_mayday_log_incident_id` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_mayday_log_mayday_id` FOREIGN KEY (`mayday_id`) REFERENCES `mayday` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `mayday_status`
--
ALTER TABLE `mayday_status`
  ADD CONSTRAINT `fk_mayday_status_dept` FOREIGN KEY (`dept_id`) REFERENCES `department` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `par_events`
--
ALTER TABLE `par_events`
  ADD CONSTRAINT `fk_par_incident` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `staffing`
--
ALTER TABLE `staffing`
  ADD CONSTRAINT `fk_staffing_incident` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tac_channels`
--
ALTER TABLE `tac_channels`
  ADD CONSTRAINT `fk_tac_incident` FOREIGN KEY (`incident_id`) REFERENCES `incidents` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
