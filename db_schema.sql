-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 18, 2025 at 08:57 AM
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
-- Database: `pao_run_db`
--

-- --------------------------------------------------------

--
-- Table structure for table `distances`
--

CREATE TABLE `distances` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL COMMENT 'ชื่อระยะทาง เช่น 10 KM',
  `price` decimal(10,2) NOT NULL COMMENT 'ค่าสมัคร',
  `category` varchar(100) DEFAULT NULL COMMENT 'ประเภท เช่น Fun Run',
  `bib_color` varchar(7) DEFAULT '#4f46e5' COMMENT 'รหัสสีสำหรับพื้นหลัง BIB ของระยะนี้',
  `bib_prefix` varchar(20) DEFAULT NULL COMMENT 'คำนำหน้าเลข BIB สำหรับระยะนี้',
  `bib_start_number` int(11) DEFAULT 1 COMMENT 'เลข BIB เริ่มต้นสำหรับระยะนี้',
  `bib_padding` int(11) DEFAULT 4 COMMENT 'จำนวนหลักของเลข BIB',
  `bib_next_number` int(11) DEFAULT NULL COMMENT 'เลข BIB ลำดับถัดไปที่จะใช้'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `distances`
--

INSERT INTO `distances` (`id`, `event_id`, `name`, `price`, `category`, `bib_color`, `bib_prefix`, `bib_start_number`, `bib_padding`, `bib_next_number`) VALUES
(75, 1, '15 KM', 599.00, 'Mini Marathon', '#4f46e5', NULL, 1, 4, NULL),
(76, 1, '5 KM', 399.00, 'Fun Run', '#4f46e5', NULL, 1, 4, NULL),
(77, 1, '5 KM', 399.00, 'ปั่นจักรยานถวายเป็นพระราชกุศล', '#4f46e5', NULL, 1, 4, NULL),
(83, 2, '5 KM', 399.00, 'ปั่นจักรยานถวายเป็นพระราชกุศล', '#4f46e5', NULL, 1, 4, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `events`
--

CREATE TABLE `events` (
  `id` int(11) NOT NULL,
  `event_code` varchar(50) NOT NULL COMMENT 'รหัสอ้างอิงกิจกรรม เช่น sskpa-run-25',
  `name` varchar(255) NOT NULL COMMENT 'ชื่อกิจกรรม',
  `slogan` varchar(255) DEFAULT NULL,
  `theme_color` varchar(20) NOT NULL DEFAULT 'indigo',
  `color_code` varchar(7) NOT NULL DEFAULT '#4f46e5',
  `logo_text` varchar(100) DEFAULT NULL,
  `is_visible` tinyint(1) NOT NULL DEFAULT 1,
  `is_cancelled` tinyint(1) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_registration_open` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=ปิด, 1=เปิด',
  `organizer` varchar(255) DEFAULT NULL,
  `organizer_phone` varchar(50) DEFAULT NULL,
  `organizer_email` varchar(100) DEFAULT NULL,
  `organizer_line_id` varchar(100) DEFAULT NULL,
  `organizer_logo_url` text DEFAULT NULL,
  `contact_person_name` varchar(255) DEFAULT NULL,
  `contact_person_phone` varchar(50) DEFAULT NULL,
  `payment_bank` varchar(100) DEFAULT NULL,
  `payment_account_name` varchar(255) DEFAULT NULL,
  `payment_account_number` varchar(50) DEFAULT NULL,
  `payment_qr_code_url` text DEFAULT NULL,
  `enable_shipping` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=ปิด, 1=เปิดใช้งานตัวเลือกการจัดส่ง',
  `shipping_cost` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'ค่าจัดส่ง (ถ้ามี)',
  `start_date` datetime NOT NULL,
  `cover_image_url` text DEFAULT NULL,
  `card_thumbnail_url` text DEFAULT NULL,
  `map_embed_url` text DEFAULT NULL,
  `map_direction_url` text DEFAULT NULL,
  `description` text DEFAULT NULL,
  `awards_description` text DEFAULT NULL,
  `bib_prefix` varchar(20) DEFAULT NULL COMMENT 'คำนำหน้า BIB',
  `bib_start_number` int(11) NOT NULL DEFAULT 1 COMMENT 'เลข BIB เริ่มต้น',
  `bib_padding` int(11) NOT NULL DEFAULT 4 COMMENT 'จำนวนหลักของเลข BIB',
  `bib_next_number` int(11) DEFAULT NULL COMMENT 'เลข BIB ลำดับถัดไปที่จะใช้',
  `corral_settings` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'การตั้งค่ากลุ่มปล่อยตัว' CHECK (json_valid(`corral_settings`)),
  `payment_deadline` datetime DEFAULT NULL COMMENT 'วันหมดเขตการชำระเงิน',
  `bib_background_url` text DEFAULT NULL COMMENT 'Path to custom BIB background image'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `events`
--

INSERT INTO `events` (`id`, `event_code`, `name`, `slogan`, `theme_color`, `color_code`, `logo_text`, `is_visible`, `is_cancelled`, `sort_order`, `is_registration_open`, `organizer`, `organizer_phone`, `organizer_email`, `organizer_line_id`, `organizer_logo_url`, `contact_person_name`, `contact_person_phone`, `payment_bank`, `payment_account_name`, `payment_account_number`, `payment_qr_code_url`, `enable_shipping`, `shipping_cost`, `start_date`, `cover_image_url`, `card_thumbnail_url`, `map_embed_url`, `map_direction_url`, `description`, `awards_description`, `bib_prefix`, `bib_start_number`, `bib_padding`, `bib_next_number`, `corral_settings`, `payment_deadline`, `bib_background_url`) VALUES
(1, 'sskpa-run-25', 'kokphet Run For Love 2026', 'รักสุขภาพ รักตนเอง รักครอบครัว รักชุมชน รักสังคมและสิ่งแวดล้อม', 'indigo', '#4f46e5', 'SSKPAO RUN 🏃‍♀️', 1, 0, 2, 1, 'รพ.สต.บ้านโคกเพชร', '045-888-999', 'ssk-pao@run.com', '@sskpaorun', 'uploads/sskpa-run-25/organizer/organizer_68f729c73fa4d.png', 'นางสาวรุจิกาญจน์ อสิพงษ์(ฝ่ายทะเบียน)', '087-9617951', 'เพื่อการเกษตรและสหกรณ์การเกษตร', 'เงินบำรุง รพ.สต.บ้านโคกเพชร', '012392939172', 'uploads/sskpa-run-25/payment/payment_691b30643cf4e.jpg', 1, 50.00, '2026-01-18 05:00:00', 'uploads/sskpa-run-25/cover/cover_690d77642ab56.png', 'uploads/sskpa-run-25/cover/cover_690d77642af6e.png', 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3889.375005898851!2d104.3005556!3d15.1111111!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x311a2f5f5c5b4e3d%3A0x8c5b1b4b1a4a4b1c!2sSisaket%20Provincial%20Stadium!5e0!3m2!1sen!2sth!4v1678888888888!5m2!1sen!2sth', 'https://maps.google.com/?q=Sisaket+Provincial+Stadium', '<p><em><strong>&nbsp;วันเสาร์ที่ 17 มกราคม 2569&nbsp; ปั่นจักรยานถวายเป็นพระราชกุศล</strong></em></p>\r\n\r\n<p><em><strong>&nbsp;วันอาทิตย์ที่ 18 มกราคม 2569 </strong></em></p>\r\n\r\n<p><em><strong>&nbsp; การแข่งขัน 2 ประเภท Fun Run ระยะทาง&nbsp; 5 กม. และ&nbsp;</strong></em><strong><em>Mini marathon ระยะทาง 15 กม.&nbsp;</em></strong><strong>ประเภทรุ่นในการแข่งขัน แยกชาย/หญิง </strong></p>\r\n\r\n<p>อายุไม่เกิน 19 ปี&nbsp; อายุไม่เกิน 29 ปี</p>\r\n\r\n<p>อายุไม่เกิน 39 ปี อายุไม่เกิน 49 ปี</p>\r\n\r\n<p>อายุไม่เกิน 59 ปี อายุ 60 ปีขึ้นไป</p>\r\n\r\n<p>Fancy รวม</p>\r\n', '<p><em><strong>Fun Run ระยะทาง&nbsp; 5 กม. เงินรางวัล 500 บาท 300 บาท 200 บาท พร้อมเกียรติบัตร</strong></em></p>\r\n\r\n<p><strong><em>Mini marathon ระยะทาง 15 กม. เงินรางวัล 1000 บาท 600 บาท 400 บาท พร้อมเกียรติบัตร</em></strong></p>\r\n\r\n<p><strong><em>Fancy รวม 8 รางวัลๆละ 500 บาท</em></strong></p>\r\n\r\n<p><strong>(สมัครวิ่งได้เหรียญทุกคน -ชนะเลิศ 1-2-3 ได้เงินรางวัลพร้อมเกียรติบัตร)</strong></p>\r\n', '0', 1, 4, 6, '[{\"name\":\"A\",\"from_bib\":\"\",\"to_bib\":\"\",\"color\":\"#3b82f6\",\"time\":\"04:00\",\"description\":\"\"},{\"name\":\"B\",\"from_bib\":\"\",\"to_bib\":\"\",\"color\":\"#f7d83b\",\"time\":\"05:00\",\"description\":\"\"},{\"name\":\"C\",\"from_bib\":\"\",\"to_bib\":\"\",\"color\":\"#87380d\",\"time\":\"\",\"description\":\"\"}]', '2025-12-10 00:00:00', NULL),
(2, 'mountain-trail-challenge-25', 'ปั่นจักรยานถวายเป็นพระราชกุศล', 'รักสุขภาพ รักตนเอง รักครอบครัว รักชุมชน รักสังคมและสิ่งแวดล้อม', 'green', '#10b981', 'TRAIL CHALLENGE ⛰️', 1, 0, 1, 1, 'รพ.สต.บ้านโคกเพชร', '090-555-4444', 'trail@run.com', '@thaitrail', 'uploads/mountain-trail-challenge-25/organizer/organizer_691c257275d31.png', 'นางสาวรุจิกาญจน์ อสิพงษ์(ฝ่ายทะเบียน)', '087-9617951', 'เพื่อการเกษตรและสหกรณ์การเกษตร', 'เงินบำรุง รพ.สต.บ้านโคกเพชร', '012392939172', 'uploads/mountain-trail-challenge-25/payment/payment_691c25727e9e7.jpg', 0, 50.00, '2026-01-17 06:00:00', 'https://placehold.co/800x300/10b981/ffffff?text=Mountain+Trail+Cover', 'https://placehold.co/400x150/10b981/ffffff?text=Mountain+Trail+Card', 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3889.375005898851!2d104.3005556!3d15.1111111!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x311a2f5f5c5b4e3d%3A0x8c5b1b4b1a4a4b1c!2sSisaket%20Provincial%20Stadium!5e0!3m2!1sen!2sth!4v1678888888888!5m2!1sen!2sth', 'https://maps.google.com/?q=Sisaket+Provincial+Stadium', '<p><em><strong>วันเสาร์ที่ 17 มกราคม 2569&nbsp; ปั่นจักรยานถวายเป็นพระราชกุศล</strong></em></p>\r\n', '', '0', 1, 4, 1, NULL, '2025-11-30 00:30:00', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `event_images`
--

CREATE TABLE `event_images` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `image_url` text NOT NULL,
  `image_type` enum('merch','medal','detail') NOT NULL COMMENT 'ประเภทรูปภาพ'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `event_images`
--

INSERT INTO `event_images` (`id`, `event_id`, `image_url`, `image_type`) VALUES
(11, 1, 'uploads/sskpa-run-25/merch/691b30645d3fe8.27181544.jpg', 'merch'),
(12, 1, 'uploads/sskpa-run-25/merch/691b373a276392.87125828.jpg', 'merch'),
(13, 2, 'uploads/mountain-trail-challenge-25/merch/691c261a288639.51774330.png', 'merch');

-- --------------------------------------------------------

--
-- Table structure for table `form_fields`
--

CREATE TABLE `form_fields` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `field_label` varchar(255) NOT NULL,
  `field_name` varchar(100) NOT NULL,
  `field_type` enum('text','email','tel','date','select','textarea') NOT NULL,
  `is_required` tinyint(1) NOT NULL DEFAULT 0,
  `options` text DEFAULT NULL COMMENT 'JSON encoded options for select type',
  `sort_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `form_fields`
--

INSERT INTO `form_fields` (`id`, `event_id`, `field_label`, `field_name`, `field_type`, `is_required`, `options`, `sort_order`) VALUES
(1, 1, 'คำนำหน้าชื่อ', 'title', 'select', 1, '[\"นาย\",\"นาง\",\"นางสาว\"]', 0),
(2, 1, 'ชื่อจริง', 'first_name', 'text', 1, NULL, 1),
(3, 1, 'นามสกุล', 'last_name', 'text', 1, NULL, 2),
(4, 1, 'วัน/เดือน/ปีเกิด', 'birth_date', 'date', 1, NULL, 3),
(5, 1, 'หมายเลขบัตรประชาชน', 'thai_id', 'text', 1, NULL, 4),
(6, 1, 'อีเมล', 'email', 'email', 1, NULL, 5),
(7, 1, 'เบอร์โทรศัพท์', 'phone', 'tel', 1, NULL, 6);

-- --------------------------------------------------------

--
-- Table structure for table `master_genders`
--

CREATE TABLE `master_genders` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `master_genders`
--

INSERT INTO `master_genders` (`id`, `name`) VALUES
(1, 'ชาย'),
(2, 'หญิง');

-- --------------------------------------------------------

--
-- Table structure for table `master_pickup_options`
--

CREATE TABLE `master_pickup_options` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL COMMENT 'e.g., รับด้วยตนเอง, จัดส่งทางไปรษณีย์',
  `cost` decimal(10,2) NOT NULL DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `master_pickup_options`
--

INSERT INTO `master_pickup_options` (`id`, `name`, `cost`) VALUES
(1, 'รับด้วยตนเองในวันงาน', 0.00),
(2, 'จัดส่งทางไปรษณีย์ (มีค่าใช้จ่ายเพิ่มเติม)', 0.00);

-- --------------------------------------------------------

--
-- Table structure for table `master_runner_types`
--

CREATE TABLE `master_runner_types` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL COMMENT 'e.g., General, VIP'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `master_runner_types`
--

INSERT INTO `master_runner_types` (`id`, `name`) VALUES
(2, 'VIP'),
(1, 'ทั่วไป (General)');

-- --------------------------------------------------------

--
-- Table structure for table `master_shirt_sizes`
--

CREATE TABLE `master_shirt_sizes` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL COMMENT 'e.g., XL',
  `description` varchar(100) DEFAULT NULL COMMENT 'e.g., (รอบอก 42")'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `master_shirt_sizes`
--

INSERT INTO `master_shirt_sizes` (`id`, `name`, `description`) VALUES
(1, 'XS', '(รอบอก 34\")'),
(2, 'S', '(รอบอก 36\")'),
(3, 'M', '(รอบอก 38\")'),
(4, 'L', '(รอบอก 40\")'),
(5, 'XL', '(รอบอก 42\")'),
(6, '2XL', '(รอบอก 44\")');

-- --------------------------------------------------------

--
-- Table structure for table `master_titles`
--

CREATE TABLE `master_titles` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `master_titles`
--

INSERT INTO `master_titles` (`id`, `name`) VALUES
(2, 'นาง'),
(3, 'นางสาว'),
(1, 'นาย'),
(4, 'อื่นๆ');

-- --------------------------------------------------------

--
-- Table structure for table `posts`
--

CREATE TABLE `posts` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `content` longtext DEFAULT NULL,
  `cover_image_url` text DEFAULT NULL,
  `is_published` tinyint(1) NOT NULL DEFAULT 1,
  `author_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `post_images`
--

CREATE TABLE `post_images` (
  `id` int(11) NOT NULL,
  `post_id` int(11) NOT NULL,
  `image_url` text NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `race_categories`
--

CREATE TABLE `race_categories` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `distance` varchar(100) NOT NULL,
  `name` varchar(255) NOT NULL,
  `gender` enum('ชาย','หญิง') NOT NULL,
  `minAge` int(11) NOT NULL DEFAULT 0,
  `maxAge` int(11) NOT NULL DEFAULT 99
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `race_categories`
--

INSERT INTO `race_categories` (`id`, `event_id`, `distance`, `name`, `gender`, `minAge`, `maxAge`) VALUES
(9, 1, '15 KM', 'รุ่นทั่วไป อายุไม่เกิน 19 ปี', 'ชาย', 10, 19),
(10, 1, '15 KM', 'รุ่นทั่วไป อายุไม่เกิน 29 ปี', 'ชาย', 20, 29),
(11, 1, '15 KM', 'รุ่นทั่วไป อายุไม่เกิน 39 ปี', 'ชาย', 30, 39),
(12, 1, '15 KM', 'รุ่นทั่วไป อายุไม่เกิน 49 ปี', 'ชาย', 40, 49),
(13, 1, '15 KM', 'รุ่นทั่วไป อายุไม่เกิน 49 ปี', 'ชาย', 50, 59),
(15, 1, '15 KM', 'รุ่นทั่วไป อายุไม่เกิน 19 ปี', 'หญิง', 10, 19),
(16, 1, '15 KM', 'รุ่นทั่วไป อายุไม่เกิน 29 ปี', 'หญิง', 20, 29),
(17, 1, '15 KM', 'รุ่นทั่วไป อายุไม่เกิน 39 ปี', 'หญิง', 30, 39),
(19, 1, '15 KM', 'รุ่นทั่วไป อายุไม่เกิน 49 ปี', 'หญิง', 40, 49),
(20, 1, '15 KM', 'รุ่นทั่วไป อายุไม่เกิน 59 ปี', 'หญิง', 50, 59),
(21, 1, '15 KM', 'รุ่นทั่วไป อายุ 60 ปี ขึ้นไป', 'หญิง', 60, 99),
(22, 1, '15 KM', 'รุ่นทั่วไป อายุ 60 ปี ขึ้นไป', 'ชาย', 60, 99),
(23, 1, '5 KM', 'รุ่นทั่วไป อายุ 60 ปี ขึ้นไป', 'ชาย', 60, 99),
(24, 1, '5 KM', 'รุ่นทั่วไป อายุไม่เกิน 19 ปี', 'ชาย', 10, 19),
(26, 1, '5 KM', 'รุ่นทั่วไป อายุไม่เกิน 29 ปี', 'ชาย', 20, 29),
(27, 1, '5 KM', 'รุ่นทั่วไป อายุไม่เกิน 39 ปี', 'ชาย', 30, 39),
(28, 1, '5 KM', 'รุ่นทั่วไป อายุไม่เกิน 49 ปี', 'ชาย', 40, 49),
(29, 1, '5 KM', 'รุ่นทั่วไป อายุไม่เกิน 59 ปี', 'ชาย', 50, 59),
(30, 1, '5 KM', 'รุ่นทั่วไป อายุไม่เกิน 59 ปี', 'หญิง', 50, 59),
(31, 1, '5 KM', 'รุ่นทั่วไป อายุไม่เกิน 49 ปี', 'หญิง', 40, 49),
(32, 1, '5 KM', 'รุ่นทั่วไป อายุไม่เกิน 39 ปี', 'หญิง', 30, 39),
(33, 1, '5 KM', 'รุ่นทั่วไป อายุไม่เกิน 29 ปี', 'หญิง', 20, 29),
(34, 1, '5 KM', 'รุ่นทั่วไป อายุไม่เกิน 19 ปี', 'หญิง', 10, 19),
(37, 1, '5 KM', 'รุ่นทั่วไป อายุ 60 ปี ขึ้นไป', 'หญิง', 60, 99);

-- --------------------------------------------------------

--
-- Table structure for table `registrations`
--

CREATE TABLE `registrations` (
  `id` int(11) NOT NULL,
  `registration_code` varchar(20) NOT NULL COMMENT 'รหัสการสมัคร เช่น R001',
  `user_id` int(11) DEFAULT NULL COMMENT 'เชื่อมกับตาราง users ถ้าสมัครแบบ login',
  `event_id` int(11) NOT NULL,
  `distance_id` int(11) NOT NULL,
  `race_category_id` int(11) DEFAULT NULL,
  `bib_number` varchar(20) DEFAULT NULL,
  `corral` varchar(10) DEFAULT NULL COMMENT 'กลุ่มปล่อยตัวที่นักวิ่งถูกจัดให้อยู่',
  `shirt_size` varchar(10) NOT NULL,
  `shipping_option` enum('pickup','delivery') NOT NULL DEFAULT 'pickup' COMMENT 'pickup=รับเอง, delivery=จัดส่ง',
  `shipping_address` text DEFAULT NULL COMMENT 'ที่อยู่สำหรับจัดส่ง (ถ้าเลือก delivery)',
  `status` enum('รอชำระเงิน','รอตรวจสอบ','ชำระเงินแล้ว') NOT NULL DEFAULT 'รอชำระเงิน',
  `payment_slip_url` text DEFAULT NULL,
  `total_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'ยอดรวมที่ต้องชำระ (รวมค่าส่ง ถ้ามี)',
  `registered_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `title` varchar(50) NOT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `gender` varchar(50) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `line_id` varchar(100) DEFAULT NULL,
  `thai_id` varchar(13) NOT NULL,
  `birth_date` date DEFAULT NULL,
  `disease` varchar(255) DEFAULT 'ไม่มีโรคประจำตัว',
  `disease_detail` text DEFAULT NULL,
  `emergency_contact_name` varchar(255) DEFAULT NULL,
  `emergency_contact_phone` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `registration_data`
--

CREATE TABLE `registration_data` (
  `id` int(11) NOT NULL,
  `registration_id` int(11) NOT NULL,
  `field_name` varchar(100) NOT NULL,
  `field_value` text NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `schedules`
--

CREATE TABLE `schedules` (
  `id` int(11) NOT NULL,
  `event_id` int(11) NOT NULL,
  `date` date DEFAULT NULL,
  `time` time NOT NULL,
  `activity` varchar(255) NOT NULL,
  `is_highlight` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1 = Highlighted item'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `schedules`
--

INSERT INTO `schedules` (`id`, `event_id`, `date`, `time`, `activity`, `is_highlight`) VALUES
(41, 1, '2026-01-18', '05:00:00', 'ลงทะเบียนเข้าร่วมงาน Kokphet Run For Love 2026  วิ่งเพื่อรัก #3', 0),
(42, 1, '2026-01-18', '06:00:00', 'ร่วมถวายอาลัยแด่พระบรมราชชนนีพันปีหลวง พิธีเปิดและวอร์มอัพรวม', 1),
(43, 1, '2026-01-18', '07:00:00', 'ปล่อยตัว (Start Time) ระยะ 10 KM', 1),
(44, 1, '2026-01-18', '07:30:00', 'ปล่อยตัว (Start Time) ระยะ 5 KM', 1),
(45, 1, '2026-01-18', '09:00:00', 'พิธีมอบรางวัลและประกาศเชิดชูเกียรติบุคคลต้นแบบลดปัจจัยเสี่ยงระดับตำบล', 1),
(46, 1, '2026-01-18', '10:00:00', 'กิจกรรมสิ้นสุด', 0),
(47, 1, '2026-01-17', '07:00:00', 'ลงทะเบียนเข้าร่วมงาน ปั่นจักรยานถวายเป็นพระราชกุศล', 0),
(48, 1, '2026-01-17', '07:30:00', 'ร่วมถวายอาลัยแด่พระบรมราชชนนีพันปีหลวง พิธีเปิดและวอร์มอัพรวม', 1),
(49, 1, '2026-01-17', '08:00:00', 'ปล่อยตัว (Start Time) ระยะ 5 KM', 1),
(50, 1, '2026-01-17', '09:00:00', 'พิธีมอบรางวัลและประกาศเชิดชูเกียรติบุคคลต้นแบบลดปัจจัยเสี่ยงระดับตำบล', 1),
(51, 1, '2026-01-17', '10:00:00', 'กิจกรรมสิ้นสุด', 0),
(57, 2, '2026-01-17', '07:00:00', 'ลงทะเบียนเข้าร่วมงาน ปั่นจักรยานถวายเป็นพระราชกุศล', 0),
(58, 2, '2026-01-17', '07:30:00', 'ร่วมถวายอาลัยแด่พระบรมราชชนนีพันปีหลวง พิธีเปิดและวอร์มอัพรวม', 0),
(59, 2, '2026-01-17', '08:00:00', 'ปล่อยตัว (Start Time) ระยะ 5 KM', 1),
(60, 2, '2026-01-17', '09:00:00', 'พิธีมอบรางวัลและประกาศเชิดชูเกียรติบุคคลต้นแบบลดปัจจัยเสี่ยงระดับตำบล', 0),
(61, 2, '2026-01-17', '10:00:00', 'กิจกรรมสิ้นสุด', 0);

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`setting_key`, `setting_value`) VALUES
('system_email', 'support@sisaketpaorun.com'),
('system_phone', '02-XXX-XXXX');

-- --------------------------------------------------------

--
-- Table structure for table `slides`
--

CREATE TABLE `slides` (
  `id` int(11) NOT NULL,
  `image_url` text NOT NULL,
  `title` varchar(255) NOT NULL,
  `subtitle` text DEFAULT NULL,
  `link_url` text DEFAULT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `slides`
--

INSERT INTO `slides` (`id`, `image_url`, `title`, `subtitle`, `link_url`, `sort_order`, `is_active`) VALUES
(1, 'https://placehold.co/800x250/ef4444/ffffff?text=PROMO+SLIDE', 'โปรโมชั่นพิเศษ! สมัครคู่ถูกกว่า 10%', 'เฉพาะ 100 คู่แรกของกิจกรรม SSKPAO RUN ถึง 31 ตุลาคมนี้เท่านั้น!', '?page=microsite&amp;event_code=sskpa-run-25', 2, 0),
(2, 'https://placehold.co/800x250/3b82f6/ffffff?text=NEWS+UPDATE', 'ประกาศ: เปลี่ยนเส้นทาง Mountain Trail ระยะ 25KM', 'โปรดตรวจสอบแผนที่ใหม่ในหน้ากิจกรรมก่อนการแข่งขัน', '?page=microsite&event_code=mountain-trail-challenge-25', 1, 1),
(3, 'https://placehold.co/800x250/22c55e/ffffff?text=LAST+CALL', 'โค้งสุดท้าย! SSKPAO RUN ปิดรับสมัครสัปดาห์หน้า', 'อย่าพลาดโอกาสในการเป็นส่วนหนึ่งของการวิ่งครั้งสำคัญนี้!', '?page=microsite&event_code=sskpa-run-25', 3, 1);

-- --------------------------------------------------------

--
-- Table structure for table `staff`
--

CREATE TABLE `staff` (
  `id` int(11) NOT NULL,
  `username` varchar(100) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `role` enum('admin','staff') NOT NULL,
  `assigned_event_id` int(11) DEFAULT NULL COMMENT 'ID กิจกรรมที่รับผิดชอบ'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff`
--

INSERT INTO `staff` (`id`, `username`, `password_hash`, `full_name`, `role`, `assigned_event_id`) VALUES
(1, 'admin', '$2a$12$zk8PKOASddu96PjkItPoFu9JyLOZMeoC4gg4.BoVElSsBKyKtcss2', 'Super Admin', 'admin', NULL),
(2, 'staff01', '$2y$10$BwkMzf3FPG1z46g.AvihFe3.aP.7wPIs0vM3ym6tKi4v9qYsdxgd2', 'คุณสมหญิง ใจดี', 'staff', 1),
(3, 'staff02', '$2a$12$zk8PKOASddu96PjkItPoFu9JyLOZMeoC4gg4.BoVElSsBKyKtcss2', 'คุณอดิศักดิ์ แข็งแรง', 'staff', 2);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `title` varchar(50) DEFAULT NULL,
  `first_name` varchar(255) NOT NULL,
  `last_name` varchar(255) NOT NULL,
  `gender` varchar(50) DEFAULT NULL,
  `birth_date` date DEFAULT NULL,
  `thai_id` varchar(13) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `line_id` varchar(100) DEFAULT NULL,
  `address` text DEFAULT NULL,
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_token_expires_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Indexes for dumped tables
--

--
-- Indexes for table `distances`
--
ALTER TABLE `distances`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `events`
--
ALTER TABLE `events`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `event_code` (`event_code`);

--
-- Indexes for table `event_images`
--
ALTER TABLE `event_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `form_fields`
--
ALTER TABLE `form_fields`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `master_genders`
--
ALTER TABLE `master_genders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `master_pickup_options`
--
ALTER TABLE `master_pickup_options`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `master_runner_types`
--
ALTER TABLE `master_runner_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `master_shirt_sizes`
--
ALTER TABLE `master_shirt_sizes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `master_titles`
--
ALTER TABLE `master_titles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `posts`
--
ALTER TABLE `posts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `author_id` (`author_id`);

--
-- Indexes for table `post_images`
--
ALTER TABLE `post_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `post_id` (`post_id`);

--
-- Indexes for table `race_categories`
--
ALTER TABLE `race_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `registrations`
--
ALTER TABLE `registrations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `event_id` (`event_id`),
  ADD KEY `distance_id` (`distance_id`),
  ADD KEY `race_category_id` (`race_category_id`);

--
-- Indexes for table `registration_data`
--
ALTER TABLE `registration_data`
  ADD PRIMARY KEY (`id`),
  ADD KEY `registration_id` (`registration_id`);

--
-- Indexes for table `schedules`
--
ALTER TABLE `schedules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `event_id` (`event_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`setting_key`);

--
-- Indexes for table `slides`
--
ALTER TABLE `slides`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `staff`
--
ALTER TABLE `staff`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `thai_id` (`thai_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `distances`
--
ALTER TABLE `distances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=84;

--
-- AUTO_INCREMENT for table `events`
--
ALTER TABLE `events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `event_images`
--
ALTER TABLE `event_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `form_fields`
--
ALTER TABLE `form_fields`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `master_genders`
--
ALTER TABLE `master_genders`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `master_pickup_options`
--
ALTER TABLE `master_pickup_options`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `master_runner_types`
--
ALTER TABLE `master_runner_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `master_shirt_sizes`
--
ALTER TABLE `master_shirt_sizes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `master_titles`
--
ALTER TABLE `master_titles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `posts`
--
ALTER TABLE `posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `post_images`
--
ALTER TABLE `post_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `race_categories`
--
ALTER TABLE `race_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `registrations`
--
ALTER TABLE `registrations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `registration_data`
--
ALTER TABLE `registration_data`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `schedules`
--
ALTER TABLE `schedules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `slides`
--
ALTER TABLE `slides`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `staff`
--
ALTER TABLE `staff`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `posts`
--
ALTER TABLE `posts`
  ADD CONSTRAINT `fk_post_author` FOREIGN KEY (`author_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `post_images`
--
ALTER TABLE `post_images`
  ADD CONSTRAINT `fk_post_images_post` FOREIGN KEY (`post_id`) REFERENCES `posts` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `race_categories`
--
ALTER TABLE `race_categories`
  ADD CONSTRAINT `fk_event_categories` FOREIGN KEY (`event_id`) REFERENCES `events` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `registrations`
--
ALTER TABLE `registrations`
  ADD CONSTRAINT `fk_registration_category` FOREIGN KEY (`race_category_id`) REFERENCES `race_categories` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
