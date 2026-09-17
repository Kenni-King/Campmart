-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Jan 16, 2026 at 08:06 AM
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
-- Database: `campmartv2`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_logs`
--

CREATE TABLE `activity_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) DEFAULT NULL,
  `entity_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_logs`
--

INSERT INTO `activity_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `ip_address`, `user_agent`, `metadata`, `created_at`) VALUES
(1, 2, 'product_created', 'product', 1, '192.168.1.100', NULL, NULL, '2026-01-13 12:41:39'),
(2, 3, 'product_created', 'product', 2, '192.168.1.101', NULL, NULL, '2026-01-13 12:41:39'),
(3, 2, 'login', 'user', 2, '192.168.1.100', NULL, NULL, '2026-01-13 12:41:39'),
(4, 3, 'login', 'user', 3, '192.168.1.101', NULL, NULL, '2026-01-13 12:41:39'),
(5, 4, 'product_viewed', 'product', 1, '192.168.1.102', NULL, NULL, '2026-01-13 12:41:39'),
(6, 5, 'product_viewed', 'product', 4, '192.168.1.103', NULL, NULL, '2026-01-13 12:41:39');

-- --------------------------------------------------------

--
-- Table structure for table `affiliate_earnings`
--

CREATE TABLE `affiliate_earnings` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'The affiliate who earned the commission',
  `referral_user_id` int(11) NOT NULL COMMENT 'The referred user who made the purchase',
  `order_id` int(11) NOT NULL COMMENT 'The order that generated the commission',
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Commission amount earned',
  `commission_rate` decimal(5,2) NOT NULL DEFAULT 5.00 COMMENT 'Commission percentage applied',
  `order_amount` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Original order amount',
  `status` enum('pending','approved','paid','cancelled') NOT NULL DEFAULT 'pending',
  `payment_method` varchar(50) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `account_number` varchar(50) DEFAULT NULL,
  `account_name` varchar(100) DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `affiliate_withdrawals`
--

CREATE TABLE `affiliate_withdrawals` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `bank_name` varchar(100) NOT NULL,
  `account_number` varchar(50) NOT NULL,
  `account_name` varchar(100) NOT NULL,
  `status` enum('pending','processing','approved','paid','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `processed_by` int(11) DEFAULT NULL COMMENT 'Admin user who processed the withdrawal',
  `processed_at` datetime DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `bookmarks`
--

CREATE TABLE `bookmarks` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `bookmark_type` enum('product','service') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `bookmarks`
--

INSERT INTO `bookmarks` (`id`, `user_id`, `product_id`, `service_id`, `bookmark_type`, `created_at`) VALUES
(1, 2, 4, NULL, 'product', '2026-01-13 12:41:39'),
(2, 2, 6, NULL, 'product', '2026-01-13 12:41:39'),
(3, 3, 1, NULL, 'product', '2026-01-13 12:41:39'),
(4, 3, 10, NULL, 'product', '2026-01-13 12:41:39'),
(5, 4, 2, NULL, 'product', '2026-01-13 12:41:39'),
(6, 5, 5, NULL, 'product', '2026-01-13 12:41:39'),
(7, 6, 1, NULL, 'product', '2026-01-13 12:41:39'),
(8, 6, 4, NULL, 'product', '2026-01-13 12:41:39'),
(9, 7, 6, NULL, 'product', '2026-01-13 12:41:39'),
(10, 8, 11, NULL, 'product', '2026-01-13 12:41:39'),
(11, 1, 3, NULL, 'product', '2026-01-13 14:11:04'),
(12, 1, 4, NULL, 'product', '2026-01-14 20:38:18');

--
-- Triggers `bookmarks`
--
DELIMITER $$
CREATE TRIGGER `after_bookmark_delete` AFTER DELETE ON `bookmarks` FOR EACH ROW BEGIN
    IF OLD.product_id IS NOT NULL THEN
        UPDATE products SET bookmarks_count = bookmarks_count - 1 WHERE id = OLD.product_id;
    ELSEIF OLD.service_id IS NOT NULL THEN
        UPDATE services SET bookmarks_count = bookmarks_count - 1 WHERE id = OLD.service_id;
    END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_bookmark_insert` AFTER INSERT ON `bookmarks` FOR EACH ROW BEGIN
    IF NEW.product_id IS NOT NULL THEN
        UPDATE products SET bookmarks_count = bookmarks_count + 1 WHERE id = NEW.product_id;
    ELSEIF NEW.service_id IS NOT NULL THEN
        UPDATE services SET bookmarks_count = bookmarks_count + 1 WHERE id = NEW.service_id;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `categories`
--

CREATE TABLE `categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(100) DEFAULT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `display_order` int(11) DEFAULT 0,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `categories`
--

INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `icon`, `parent_id`, `display_order`, `is_active`, `created_at`, `updated_at`) VALUES
(1, 'Electronics', 'electronics', 'Phones, Laptops, Tablets, and more', 'devices', NULL, 1, 1, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(2, 'Textbooks', 'textbooks', 'Academic books and study materials', 'menu_book', NULL, 2, 1, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(3, 'Fashion', 'fashion', 'Clothing, Shoes, Accessories', 'apparel', NULL, 3, 1, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(4, 'Furniture', 'furniture', 'Home and dorm furniture', 'chair', NULL, 4, 1, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(5, 'Sports', 'sports', 'Sports equipment and gear', 'sports_soccer', NULL, 5, 1, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(6, 'Services', 'services', 'Student services and freelancing', 'home_repair_service', NULL, 6, 1, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(7, 'Home & Living', 'home-living', 'Home essentials and decor', 'home', NULL, 7, 1, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(8, 'Academic', 'academic', 'Academic supplies and tools', 'school', NULL, 8, 1, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(9, 'Others', 'others', 'Miscellaneous items', 'more_horiz', NULL, 9, 1, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(10, 'Laptops', 'laptops', 'Notebooks and ultrabooks', 'laptop_mac', 1, 1, 1, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(11, 'Phones & Tablets', 'phones-tablets', 'Mobile devices', 'smartphone', 1, 2, 1, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(12, 'Accessories', 'accessories', 'Tech accessories', 'headphones', 1, 3, 1, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(13, 'Engineering', 'engineering', 'Engineering textbooks', 'precision_manufacturing', 2, 1, 1, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(14, 'Sciences', 'sciences', 'Science textbooks', 'science', 2, 2, 1, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(15, 'Arts', 'arts', 'Arts and humanities books', 'palette', 2, 3, 1, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(16, 'Food', 'food', '', 'palette', NULL, 2, 1, '2026-01-15 23:00:05', '2026-01-15 23:02:08');

-- --------------------------------------------------------

--
-- Table structure for table `conversations`
--

CREATE TABLE `conversations` (
  `id` int(11) NOT NULL,
  `pubkey` varchar(46) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `user1_id` int(11) NOT NULL,
  `user2_id` int(11) NOT NULL,
  `last_message_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_message_id` int(10) DEFAULT NULL,
  `updated_at` varchar(26) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `conversations`
--

INSERT INTO `conversations` (`id`, `pubkey`, `product_id`, `service_id`, `user1_id`, `user2_id`, `last_message_at`, `created_at`, `last_message_id`, `updated_at`) VALUES
(1, 'dfdsfdsf235234523sfsdf4', 13, NULL, 18, 19, '2026-01-14 18:19:46', '2026-01-14 18:19:46', 8, '2026-01-15 23:01:31');

-- --------------------------------------------------------

--
-- Table structure for table `flash_sales`
--

CREATE TABLE `flash_sales` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(200) NOT NULL,
  `description` text DEFAULT NULL,
  `discount_percentage` decimal(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Overall sale discount (e.g., 50.00 for 50%)',
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `status` enum('scheduled','active','expired','cancelled') DEFAULT 'scheduled',
  `banner_image` varchar(500) DEFAULT NULL,
  `is_featured` tinyint(1) DEFAULT 0 COMMENT 'Show on homepage',
  `max_discount_amount` decimal(10,2) DEFAULT NULL COMMENT 'Maximum discount cap per product',
  `created_by` int(11) DEFAULT NULL COMMENT 'Admin user ID',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `flash_sales`
--

INSERT INTO `flash_sales` (`id`, `title`, `description`, `discount_percentage`, `start_time`, `end_time`, `status`, `banner_image`, `is_featured`, `max_discount_amount`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 'Tech Gadgets Flash Sale', 'Massive discounts on selected tech items! Limited time only.', 50.00, '2026-01-13 12:46:42', '2026-01-13 18:46:42', 'active', NULL, 1, NULL, 1, '2026-01-13 13:46:42', '2026-01-13 13:46:42'),
(2, 'Back to School Sale', 'Get ready for the semester with discounts on textbooks and supplies.', 30.00, '2026-01-15 14:46:42', '2026-01-18 14:46:42', 'scheduled', NULL, 1, NULL, 1, '2026-01-13 13:46:42', '2026-01-13 13:46:42');

--
-- Triggers `flash_sales`
--
DELIMITER $$
CREATE TRIGGER `update_flash_sale_status_on_insert` BEFORE INSERT ON `flash_sales` FOR EACH ROW BEGIN
  IF NEW.start_time <= NOW() AND NEW.end_time >= NOW() THEN
    SET NEW.status = 'active';
  ELSEIF NEW.end_time < NOW() THEN
    SET NEW.status = 'expired';
  ELSE
    SET NEW.status = 'scheduled';
  END IF;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_flash_sale_status_on_update` BEFORE UPDATE ON `flash_sales` FOR EACH ROW BEGIN
  IF NEW.status != 'cancelled' THEN
    IF NEW.start_time <= NOW() AND NEW.end_time >= NOW() THEN
      SET NEW.status = 'active';
    ELSEIF NEW.end_time < NOW() THEN
      SET NEW.status = 'expired';
    ELSE
      SET NEW.status = 'scheduled';
    END IF;
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `flash_sale_products`
--

CREATE TABLE `flash_sale_products` (
  `id` int(10) UNSIGNED NOT NULL,
  `flash_sale_id` int(10) UNSIGNED NOT NULL,
  `product_id` int(11) NOT NULL,
  `original_price` decimal(10,2) NOT NULL COMMENT 'Product price before discount',
  `sale_price` decimal(10,2) NOT NULL COMMENT 'Discounted price',
  `discount_percentage` decimal(5,2) NOT NULL COMMENT 'Specific discount for this product',
  `stock_limit` int(10) UNSIGNED DEFAULT NULL COMMENT 'Limited quantity for flash sale (NULL = no limit)',
  `sold_count` int(10) UNSIGNED DEFAULT 0 COMMENT 'Number sold during this flash sale',
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `flash_sale_products`
--

INSERT INTO `flash_sale_products` (`id`, `flash_sale_id`, `product_id`, `original_price`, `sale_price`, `discount_percentage`, `stock_limit`, `sold_count`, `is_active`, `created_at`) VALUES
(1, 1, 1, 250000.00, 125000.00, 50.00, 5, 2, 1, '2026-01-13 13:46:42'),
(2, 1, 4, 120000.00, 60000.00, 50.00, 3, 1, 1, '2026-01-13 13:46:42'),
(3, 1, 10, 45000.00, 27000.00, 40.00, 10, 5, 1, '2026-01-13 13:46:42');

-- --------------------------------------------------------

--
-- Table structure for table `flash_sale_views`
--

CREATE TABLE `flash_sale_views` (
  `id` int(10) UNSIGNED NOT NULL,
  `flash_sale_id` int(10) UNSIGNED NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `viewed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `lost_found_items`
--

CREATE TABLE `lost_found_items` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `type` enum('lost','found') NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `location_lost_found` varchar(255) DEFAULT NULL,
  `date_lost_found` date DEFAULT NULL,
  `contact_info` varchar(255) DEFAULT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `status` enum('open','claimed','closed') DEFAULT 'open',
  `claimed_by` int(11) DEFAULT NULL,
  `claimed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lost_found_items`
--

INSERT INTO `lost_found_items` (`id`, `user_id`, `type`, `title`, `description`, `category`, `location_lost_found`, `date_lost_found`, `contact_info`, `image_url`, `status`, `claimed_by`, `claimed_at`, `created_at`, `updated_at`) VALUES
(1, 12, 'found', 'Leather Wallet with ID', 'Found a brown leather wallet containing student ID and some cash. Found near the library. Contact to claim and verify ownership.', 'Wallet', 'Main Library', '2026-01-10', '08023456780', 'https://lh3.googleusercontent.com/aida-public/AB6AXuDr-15RiviFzCtn0XGu_7bGbYqukteTRSbLZYltiinr5cMTI6IhvciugOgt85PbqLHvuBizvHL17joQ9bHVqgm0b0PF_yl3PoB0jgg_Lo6_xT2rTWycNEdfTEmuQismpo2TQXwyBCdmKqOUYeheJOWaHfsCg5mmAtL3B8x0ovasH98cSK3a3SlIz93WcCWeKW0yIg0cHybPh_6N9yprCEqdmc9JAKsqSjb6dTYn2b3glGN_D0JK2fsKM0wrlSWdEIwNdyhx5Al-hlcM', 'open', NULL, NULL, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(2, 13, 'lost', 'Leather Wallet with ID', 'Lost my black leather wallet containing student ID, ATM card, and some cash. Last seen at the cafeteria. Reward offered for return.', 'Wallet', 'Student Cafeteria', '2026-01-11', '08034567891', 'https://lh3.googleusercontent.com/aida-public/AB6AXuDr-15RiviFzCtn0XGu_7bGbYqukteTRSbLZYltiinr5cMTI6IhvciugOgt85PbqLHvuBizvHL17joQ9bHVqgm0b0PF_yl3PoB0jgg_Lo6_xT2rTWycNEdfTEmuQismpo2TQXwyBCdmKqOUYeheJOWaHfsCg5mmAtL3B8x0ovasH98cSK3a3SlIz93WcCWeKW0yIg0cHybPh_6N9yprCEqdmc9JAKsqSjb6dTYn2b3glGN_D0JK2fsKM0wrlSWdEIwNdyhx5Al-hlcM', 'open', NULL, NULL, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(3, 14, 'found', 'Black Backpack with Textbooks', 'Found a black Nike backpack containing engineering textbooks and notebooks. Found at lecture hall B. Please contact to claim.', 'Backpack', 'Lecture Hall B', '2026-01-09', '08045678902', 'https://lh3.googleusercontent.com/aida-public/AB6AXuDr-15RiviFzCtn0XGu_7bGbYqukteTRSbLZYltiinr5cMTI6IhvciugOgt85PbqLHvuBizvHL17joQ9bHVqgm0b0PF_yl3PoB0jgg_Lo6_xT2rTWycNEdfTEmuQismpo2TQXwyBCdmKqOUYeheJOWaHfsCg5mmAtL3B8x0ovasH98cSK3a3SlIz93WcCWeKW0yIg0cHybPh_6N9yprCEqdmc9JAKsqSjb6dTYn2b3glGN_D0JK2fsKM0wrlSWdEIwNdyhx5Al-hlcM', 'open', NULL, NULL, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(4, 18, 'found', 'Blue Chapman', 'No phone', 'Drinks', 'Akure, Nigeria', '2026-01-12', '080323243435', 'uploads/lost-found/lostfound_1768376272.jpeg', 'open', NULL, NULL, '2026-01-14 07:37:52', '2026-01-14 07:37:52'),
(5, 18, 'lost', 'Exercize book', 'in the Akure, Nigeria', 'general vocabulary', 'Akure, Nigeria', '2026-01-21', '080323243423', 'uploads/lost-found/lostfound_1768402061.jpeg', 'open', NULL, NULL, '2026-01-14 14:47:41', '2026-01-14 14:47:41'),
(6, 18, 'lost', 'Bracelet', 'Working', 'Books', 'Block CA', '2026-01-13', '08032324343', 'uploads/lost-found/lostfound_1768402134.jpeg', 'open', NULL, NULL, '2026-01-14 14:48:54', '2026-01-14 14:48:54');

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `conversation_id` int(11) NOT NULL,
  `sender_id` int(11) NOT NULL,
  `receiver_id` int(11) NOT NULL,
  `message` text NOT NULL,
  `attachment_url` varchar(500) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `messages`
--

INSERT INTO `messages` (`id`, `conversation_id`, `sender_id`, `receiver_id`, `message`, `attachment_url`, `is_read`, `read_at`, `created_at`) VALUES
(2, 1, 18, 19, 'hello', NULL, 1, NULL, '2026-01-14 18:25:18'),
(3, 1, 18, 19, 'How are you today?', NULL, 1, NULL, '2026-01-15 08:05:54'),
(4, 1, 18, 19, 'Are you coming to town for the manifestation of the inestimable excellence performance glory dominion?', NULL, 1, NULL, '2026-01-15 08:07:07'),
(5, 1, 19, 18, 'Yes i cam meet up with you immediately', NULL, 1, NULL, '2026-01-15 21:59:37'),
(6, 1, 19, 18, 'let me know if you are already on your way', NULL, 1, NULL, '2026-01-15 22:00:09'),
(7, 1, 18, 19, 'i am not sure yet i\\\'m on my way', NULL, 1, NULL, '2026-01-15 22:00:55'),
(8, 1, 18, 19, 'tell the truth', NULL, 1, NULL, '2026-01-15 22:01:31');

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `type` enum('message','transaction','product','service','system','promotion') NOT NULL,
  `related_id` int(11) DEFAULT NULL,
  `related_type` varchar(50) DEFAULT NULL,
  `action_url` varchar(500) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT 0,
  `read_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `title`, `message`, `type`, `related_id`, `related_type`, `action_url`, `is_read`, `read_at`, `created_at`) VALUES
(1, 2, 'New Message', 'You have a new inquiry about MacBook Air M1', 'message', 1, NULL, NULL, 0, NULL, '2026-01-13 12:41:39'),
(2, 2, 'Product Viewed', 'Your MacBook Air listing was viewed 15 times today', 'product', 1, NULL, NULL, 0, NULL, '2026-01-13 12:41:39'),
(3, 3, 'Sale Confirmed', 'Your sneakers have been marked as sold', 'transaction', 2, NULL, NULL, 1, NULL, '2026-01-13 12:41:39'),
(4, 4, 'Bookmark Alert', 'Price drop on iPad Air 4th Gen', 'product', 4, NULL, NULL, 0, NULL, '2026-01-13 12:41:39'),
(5, 5, 'New Review', 'You received a 5-star review from a buyer', 'system', 5, NULL, NULL, 0, NULL, '2026-01-13 12:41:39');

-- --------------------------------------------------------

--
-- Table structure for table `orders`
--

CREATE TABLE `orders` (
  `id` int(10) UNSIGNED NOT NULL,
  `order_number` varchar(50) DEFAULT NULL,
  `buyer_id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `affiliate_id` int(10) DEFAULT NULL,
  `product_id` int(11) NOT NULL,
  `payment_method` enum('cash','bank_transfer','card','wallet') NOT NULL,
  `delivery_location` varchar(255) NOT NULL,
  `buyer_phone` varchar(20) NOT NULL,
  `notes` text DEFAULT NULL,
  `item_price` decimal(15,2) NOT NULL,
  `service_fee` decimal(15,2) DEFAULT 0.00,
  `total_amount` decimal(15,2) NOT NULL,
  `status` enum('pending','confirmed','processing','completed','cancelled','refunded') DEFAULT 'pending',
  `payment_status` enum('pending','paid','failed','refunded') DEFAULT 'pending',
  `delivery_status` enum('pending','in_transit','delivered','returned') DEFAULT 'pending',
  `seller_confirmation` tinyint(1) DEFAULT 0,
  `buyer_confirmation` tinyint(1) DEFAULT 0,
  `completed_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Triggers `orders`
--
DELIMITER $$
CREATE TRIGGER `generate_order_number` BEFORE INSERT ON `orders` FOR EACH ROW BEGIN
  IF NEW.order_number IS NULL OR NEW.order_number = '' THEN
    SET NEW.order_number = CONCAT(
      'ORD-',
      DATE_FORMAT(NOW(6), '%y%m%d%H%i%s'),
      LPAD(FLOOR(MICROSECOND(NOW(6)) / 1000), 3, '0'),
      '-',
      LPAD(FLOOR(RAND() * 100), 2, '0')
    );
  END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `products`
--

CREATE TABLE `products` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `price` decimal(15,2) NOT NULL,
  `original_price` decimal(15,2) DEFAULT NULL,
  `condition_type` enum('new','like_new','good','fair','for_parts') DEFAULT 'good',
  `availability` enum('available','reserved','sold','unavailable') DEFAULT 'available',
  `location` varchar(255) DEFAULT NULL,
  `negotiable` tinyint(1) DEFAULT 1,
  `views_count` int(11) DEFAULT 0,
  `bookmarks_count` int(11) DEFAULT 0,
  `shares_count` int(11) DEFAULT 0,
  `is_featured` tinyint(1) DEFAULT 0,
  `is_trending` tinyint(1) DEFAULT 0,
  `is_sponsored` tinyint(1) DEFAULT 0,
  `is_urgent` tinyint(1) DEFAULT 0,
  `tags` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`tags`)),
  `metadata` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`metadata`)),
  `status` enum('draft','pending','approved','rejected','sold') DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `sold_at` timestamp NULL DEFAULT NULL,
  `expires_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `products`
--

INSERT INTO `products` (`id`, `user_id`, `category_id`, `title`, `slug`, `description`, `price`, `original_price`, `condition_type`, `availability`, `location`, `negotiable`, `views_count`, `bookmarks_count`, `shares_count`, `is_featured`, `is_trending`, `is_sponsored`, `is_urgent`, `tags`, `metadata`, `status`, `rejection_reason`, `approved_by`, `approved_at`, `sold_at`, `expires_at`, `created_at`, `updated_at`) VALUES
(1, 2, 10, 'MacBook Air M1 (2020) Space Gray', 'macbook-air-m1-2020-space-gray', 'Excellent condition MacBook Air with M1 chip, 8GB RAM, 256GB SSD. Used for light tasks only. Battery health at 95%. Comes with original charger and box. Perfect for students.', 250000.00, 320000.00, 'like_new', 'available', 'Jaja Hall, FUTA', 1, 240, 47, 0, 1, 1, 0, 0, '[\"macbook\", \"apple\", \"laptop\", \"m1\", \"tech\"]', NULL, 'approved', NULL, 1, '2026-01-13 12:41:39', NULL, NULL, '2026-01-13 12:41:39', '2026-01-13 14:46:45'),
(2, 3, 3, 'Original Air Max Sneakers - Size 42', 'original-air-max-sneakers-size-42', 'Brand new Air Max sneakers in perfect condition. Size 42 (EU). Never worn outdoors. Original box included. Authentic and verified.', 18500.00, 25000.00, 'new', 'available', 'South Gate, FUTA', 1, 158, 33, 0, 1, 0, 0, 0, '[\"sneakers\", \"nike\", \"shoes\", \"fashion\"]', NULL, 'approved', NULL, 1, '2026-01-13 12:41:39', NULL, NULL, '2026-01-13 12:41:39', '2026-01-14 21:29:10'),
(3, 4, 7, 'Adjustable LED Desk Lamp', 'adjustable-led-desk-lamp', 'Modern LED desk lamp with adjustable brightness and color temperature. Perfect for late-night study sessions. Energy efficient and eye-friendly.', 4500.00, 6000.00, 'good', 'available', 'Library Area, FUTA', 1, 94, 19, 0, 0, 0, 0, 0, '[\"lamp\", \"desk\", \"led\", \"study\"]', NULL, 'approved', NULL, 1, '2026-01-13 12:41:39', NULL, NULL, '2026-01-13 12:41:39', '2026-01-16 07:05:18'),
(4, 5, 11, 'iPad Air 4th Gen - 64GB WiFi', 'ipad-air-4th-gen-64gb-wifi', 'iPad Air 4th generation in excellent condition. 64GB WiFi model. Screen is pristine with no scratches. Comes with protective case and charger. Great for note-taking and media consumption.', 120000.00, 150000.00, 'like_new', 'available', 'ETF Building, FUTA', 1, 196, 59, 0, 1, 1, 0, 0, '[\"ipad\", \"tablet\", \"apple\", \"tech\"]', NULL, 'approved', NULL, 1, '2026-01-13 12:41:39', NULL, NULL, '2026-01-13 12:41:39', '2026-01-14 20:38:18'),
(5, 6, 5, 'Adjustable Dumbbells Set', 'adjustable-dumbbells-set', 'Professional adjustable dumbbells set (5kg-25kg each). Perfect for home workouts. Barely used, in excellent condition. Weight plates included.', 12000.00, 18000.00, 'like_new', 'available', 'Sports Complex, FUTA', 1, 92, 24, 0, 0, 1, 0, 0, '[\"fitness\", \"dumbbells\", \"sports\", \"gym\"]', NULL, 'approved', NULL, 1, '2026-01-13 12:41:39', NULL, NULL, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(6, 7, 12, 'Noise Cancelling Headphones', 'noise-cancelling-headphones', 'Premium noise cancelling over-ear headphones. Great sound quality with active noise cancellation. Perfect for studying in noisy environments. Includes carrying case.', 45000.00, 55000.00, 'good', 'available', 'North Gate, FUTA', 1, 145, 40, 0, 1, 0, 0, 0, '[\"headphones\", \"audio\", \"tech\", \"noise-cancelling\"]', NULL, 'approved', NULL, 1, '2026-01-13 12:41:39', NULL, NULL, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(7, 8, 12, 'Pro Waterproof Laptop Bag', 'pro-waterproof-laptop-bag', 'Durable waterproof laptop bag fits up to 15.6 inch laptops. Multiple compartments for organization. Padded straps for comfort. Perfect for daily campus use.', 12000.00, 15000.00, 'new', 'available', 'Student Affairs, FUTA', 1, 67, 15, 0, 0, 0, 0, 0, '[\"laptop-bag\", \"bag\", \"accessories\"]', NULL, 'approved', NULL, 1, '2026-01-13 12:41:39', NULL, NULL, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(8, 8, 12, 'Wireless Ergonomic Mouse', 'wireless-ergonomic-mouse', 'Comfortable wireless mouse with ergonomic design. 2.4GHz connection, long battery life. Reduces wrist strain during long study sessions.', 8500.00, 12000.00, 'new', 'available', 'ICT Center, FUTA', 1, 102, 22, 0, 0, 0, 0, 0, '[\"mouse\", \"wireless\", \"accessories\", \"ergonomic\"]', NULL, 'approved', NULL, 1, '2026-01-13 12:41:39', NULL, NULL, '2026-01-13 12:41:39', '2026-01-13 16:35:29'),
(9, 9, 3, 'Limited Edition Uni Hoodie', 'limited-edition-uni-hoodie', 'Limited edition university branded hoodie. Size L. Premium quality cotton blend. Comfortable and stylish. Perfect for campus life.', 15000.00, 20000.00, 'new', 'available', 'Student Center, FUTA', 1, 134, 47, 0, 0, 1, 0, 0, '[\"hoodie\", \"fashion\", \"clothing\", \"campus\"]', NULL, 'approved', NULL, 1, '2026-01-13 12:41:39', NULL, NULL, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(10, 10, 3, 'Nike Air Max Pre-Day', 'nike-air-max-pre-day', 'Nike Air Max Pre-Day sneakers in excellent condition. Size 43. Worn a few times. Authentic Nike product with original box.', 32000.00, 45000.00, 'like_new', 'available', 'FUTA South, Gate', 1, 191, 62, 0, 1, 1, 0, 0, '[\"nike\", \"sneakers\", \"fashion\", \"airmax\"]', NULL, 'approved', NULL, 1, '2026-01-13 12:41:39', NULL, NULL, '2026-01-13 12:41:39', '2026-01-13 16:43:51'),
(11, 11, 8, 'Scientific Calculator Casio', 'scientific-calculator-casio', 'Casio FX-991ES scientific calculator. Essential for engineering and science students. In perfect working condition.', 5000.00, 7500.00, 'good', 'available', 'Engineering Faculty, FUTA', 1, 112, 29, 0, 0, 0, 0, 0, '[\"calculator\", \"casio\", \"academic\", \"science\"]', NULL, 'approved', NULL, 1, '2026-01-13 12:41:39', NULL, NULL, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(12, 11, 2, 'Organic Chemistry Vol II Textbook', 'organic-chemistry-vol-ii-textbook', 'Organic Chemistry Volume II textbook by Morrison and Boyd. Latest edition. Excellent condition with minimal highlighting. Perfect for chemistry students.', 8500.00, 12000.00, 'good', 'available', 'SEET Building, FUTA', 1, 87, 19, 0, 0, 0, 0, 0, '[\"textbook\", \"chemistry\", \"academic\", \"science\"]', NULL, 'approved', NULL, 1, '2026-01-13 12:41:39', NULL, NULL, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(13, 15, 2, 'Organic Chemistry Vol II', 'free-organic-chemistry-vol-ii', 'Donating this Organic Chemistry textbook to help students. The book is in good condition. Pickup at Library Annex during working hours.', 0.00, NULL, 'good', 'available', 'Library Annex', 0, 0, 0, 0, 0, 0, 0, 0, '[\"free\", \"textbook\", \"chemistry\", \"donation\"]', NULL, 'approved', NULL, 1, '2026-01-13 12:41:39', NULL, NULL, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(14, 16, 4, 'Vintage Student Bicycle', 'free-vintage-student-bicycle', 'Giving away my old bicycle as I am graduating. Still in working condition, just needs some minor repairs. Good for getting around campus.', 0.00, NULL, 'fair', 'available', 'Jaja Hall', 0, 0, 0, 0, 0, 0, 0, 0, '[\"free\", \"bicycle\", \"transport\", \"donation\"]', NULL, 'approved', NULL, 1, '2026-01-13 12:41:39', NULL, NULL, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(15, 18, 6, 'Internet Router 4G', 'internet-router-4g-1768333775', 'new item', 4350.00, 6900.00, 'like_new', 'available', 'Akure, Nigeria', 1, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, 'pending', NULL, NULL, NULL, NULL, NULL, '2026-01-13 19:49:35', '2026-01-13 19:49:35'),
(16, 18, 1, 'iPhone 13 Pro Max', 'iphone-13-pro-max-1768377849', 'The phone is literally very good', 450000.00, 600000.00, 'like_new', 'available', 'Main Campus of Admin', 1, 0, 0, 0, 0, 0, 0, 0, NULL, NULL, 'pending', NULL, NULL, NULL, NULL, NULL, '2026-01-14 08:04:09', '2026-01-14 08:04:09');

-- --------------------------------------------------------

--
-- Table structure for table `product_images`
--

CREATE TABLE `product_images` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `image_url` varchar(500) NOT NULL,
  `display_order` int(11) DEFAULT 0,
  `is_primary` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_images`
--

INSERT INTO `product_images` (`id`, `product_id`, `image_url`, `display_order`, `is_primary`, `created_at`) VALUES
(1, 1, 'https://lh3.googleusercontent.com/aida-public/AB6AXuCIiIzDmCrRT_1Rz9yi681hD9D_Y6qM-8o-WlZ-s1zZMh55ER-XJDAovHiOsPAparfNWTaVdsAyjiFqq62rqVyridPPRO2JphM6dAt2z_Cglhm_1XUUbNxq1e5NAracJ7tVyR4_-jz7-eLD8aIfao5g8bVESmfY3q4yPqP49lnDW3jg6FaPgOoSq8zf0kB-2fmESajDbvFazGRO1Vx35XT03HZqsZ937ZV4T_-5__zF2ABxHgBX4CdTkEr-wcAVAOLbLkIEJwo1pGo1', 1, 1, '2026-01-13 12:41:39'),
(2, 2, 'https://lh3.googleusercontent.com/aida-public/AB6AXuBZqLo54qUZLfHJo4HuP2vyyAMfVAIqSoCd8y_jm2uFmksdKXq6Pw5HXUnWPGFvhu_cKdifDLWRVOUoxUID_pIs3m1I98ligJwBbAx3doyDq_B5V79yFRmJeDMMM4nSjEslAhM336KXGO5XYnn9ve43xVLTL-2UK6fZ9ydFRgcgrfHfuOe8aExfHNFguxx0NEaeyGxjt9ZQczr7fjDGErGKAzNBSxtSx05vOhqx6VdYDAiqckEUB2konM2aIqQyTJKUznNGLUdzkrQa', 1, 1, '2026-01-13 12:41:39'),
(3, 3, 'https://lh3.googleusercontent.com/aida-public/AB6AXuBvo43Ap3gk_wgNLlbF9wgVFOD6uiDM3VLI0iTlcUmM8EG_YFELSqVlhw9PPn_GoeAwQUxinCfQDa5zt-arPkVp3GL_vvPp-x8piqtqErXm8ZG-C081rc-qLLsrcE61PzOzIQyVjUPW2Ahcqu1t1X5-kZoh8DfQ7N0hcbOfchqPtAopTKir4SVMenP8P7E3URCZoASlQ6uBRSuv-7zrO-1lUax9fZ87Qhs8nQEAtB6QG4VWkRs1sZy1FLj94XKznVlAr_IFO45XbDnS', 1, 1, '2026-01-13 12:41:39'),
(4, 4, 'https://lh3.googleusercontent.com/aida-public/AB6AXuDgbv0SEJ9TfGzJHyb6tFzf0JBrf5h_NAXesDKuzIxasZG6PjsC5-M49iyyrdQMw6eJGsv4NQqQ6QHHKw6UvKNbZ34zr3yRjgyPbnS9d1oLuKhjNhITxtFtBM-HJqB4192FZokMWqdKfM7X8DxnMosAi6IVLdUeAIkb-Ye_uuaLQVAvcmXsDBL0dCjAWYWYVU_naH5u3DEZQiY3x435LIrcdmvRJCBvL9jQcjsFnkdhIcghcNuPRgYO473kqYtZ3Q6o9Kw1m3jFDq4H', 1, 1, '2026-01-13 12:41:39'),
(5, 5, 'https://lh3.googleusercontent.com/aida-public/AB6AXuDr-15RiviFzCtn0XGu_7bGbYqukteTRSbLZYltiinr5cMTI6IhvciugOgt85PbqLHvuBizvHL17joQ9bHVqgm0b0PF_yl3PoB0jgg_Lo6_xT2rTWycNEdfTEmuQismpo2TQXwyBCdmKqOUYeheJOWaHfsCg5mmAtL3B8x0ovasH98cSK3a3SlIz93WcCWeKW0yIg0cHybPh_6N9yprCEqdmc9JAKsqSjb6dTYn2b3glGN_D0JK2fsKM0wrlSWdEIwNdyhx5Al-hlcM', 1, 1, '2026-01-13 12:41:39'),
(6, 6, 'https://lh3.googleusercontent.com/aida-public/AB6AXuCIiIzDmCrRT_1Rz9yi681hD9D_Y6qM-8o-WlZ-s1zZMh55ER-XJDAovHiOsPAparfNWTaVdsAyjiFqq62rqVyridPPRO2JphM6dAt2z_Cglhm_1XUUbNxq1e5NAracJ7tVyR4_-jz7-eLD8aIfao5g8bVESmfY3q4yPqP49lnDW3jg6FaPgOoSq8zf0kB-2fmESajDbvFazGRO1Vx35XT03HZqsZ937ZV4T_-5__zF2ABxHgBX4CdTkEr-wcAVAOLbLkIEJwo1pGo1', 1, 1, '2026-01-13 12:41:39'),
(7, 7, 'https://lh3.googleusercontent.com/aida-public/AB6AXuDgbv0SEJ9TfGzJHyb6tFzf0JBrf5h_NAXesDKuzIxasZG6PjsC5-M49iyyrdQMw6eJGsv4NQqQ6QHHKw6UvKNbZ34zr3yRjgyPbnS9d1oLuKhjNhITxtFtBM-HJqB4192FZokMWqdKfM7X8DxnMosAi6IVLdUeAIkb-Ye_uuaLQVAvcmXsDBL0dCjAWYWYVU_naH5u3DEZQiY3x435LIrcdmvRJCBvL9jQcjsFnkdhIcghcNuPRgYO473kqYtZ3Q6o9Kw1m3jFDq4H', 1, 1, '2026-01-13 12:41:39'),
(8, 8, 'https://lh3.googleusercontent.com/aida-public/AB6AXuBvo43Ap3gk_wgNLlbF9wgVFOD6uiDM3VLI0iTlcUmM8EG_YFELSqVlhw9PPn_GoeAwQUxinCfQDa5zt-arPkVp3GL_vvPp-x8piqtqErXm8ZG-C081rc-qLLsrcE61PzOzIQyVjUPW2Ahcqu1t1X5-kZoh8DfQ7N0hcbOfchqPtAopTKir4SVMenP8P7E3URCZoASlQ6uBRSuv-7zrO-1lUax9fZ87Qhs8nQEAtB6QG4VWkRs1sZy1FLj94XKznVlAr_IFO45XbDnS', 1, 1, '2026-01-13 12:41:39'),
(9, 9, 'https://lh3.googleusercontent.com/aida-public/AB6AXuBZqLo54qUZLfHJo4HuP2vyyAMfVAIqSoCd8y_jm2uFmksdKXq6Pw5HXUnWPGFvhu_cKdifDLWRVOUoxUID_pIs3m1I98ligJwBbAx3doyDq_B5V79yFRmJeDMMM4nSjEslAhM336KXGO5XYnn9ve43xVLTL-2UK6fZ9ydFRgcgrfHfuOe8aExfHNFguxx0NEaeyGxjt9ZQczr7fjDGErGKAzNBSxtSx05vOhqx6VdYDAiqckEUB2konM2aIqQyTJKUznNGLUdzkrQa', 1, 1, '2026-01-13 12:41:39'),
(10, 10, 'https://lh3.googleusercontent.com/aida-public/AB6AXuDr-15RiviFzCtn0XGu_7bGbYqukteTRSbLZYltiinr5cMTI6IhvciugOgt85PbqLHvuBizvHL17joQ9bHVqgm0b0PF_yl3PoB0jgg_Lo6_xT2rTWycNEdfTEmuQismpo2TQXwyBCdmKqOUYeheJOWaHfsCg5mmAtL3B8x0ovasH98cSK3a3SlIz93WcCWeKW0yIg0cHybPh_6N9yprCEqdmc9JAKsqSjb6dTYn2b3glGN_D0JK2fsKM0wrlSWdEIwNdyhx5Al-hlcM', 1, 1, '2026-01-13 12:41:39'),
(11, 11, 'https://lh3.googleusercontent.com/aida-public/AB6AXuDyZynZ9wGNiLOY_vqvHdeIb4_Wq7kbchvzU5xRZaYblOi2itGiRT6HGGdzH_OPxnrpjmQmpuIZFhnsd53HmR_IbAw1vQsN6Ea-tegqqJFjvOjyJCsp2ANLc7A8ek06uompM6n6MDAB8Q5N8XbB0zdOkc9Pl1lN_u4tO9V9oX4fhAAJAZoRVZWtwx3IvJLuIMMl4Tdr0pfQ3TktxTBmKbXJ8Cn4i69pDjVFHIGf_X3cdzGPhons3hCMPtdNI_hp9CznoSzeHnBwVIY2', 1, 1, '2026-01-13 12:41:39'),
(12, 12, 'https://lh3.googleusercontent.com/aida-public/AB6AXuDyZynZ9wGNiLOY_vqvHdeIb4_Wq7kbchvzU5xRZaYblOi2itGiRT6HGGdzH_OPxnrpjmQmpuIZFhnsd53HmR_IbAw1vQsN6Ea-tegqqJFjvOjyJCsp2ANLc7A8ek06uompM6n6MDAB8Q5N8XbB0zdOkc9Pl1lN_u4tO9V9oX4fhAAJAZoRVZWtwx3IvJLuIMMl4Tdr0pfQ3TktxTBmKbXJ8Cn4i69pDjVFHIGf_X3cdzGPhons3hCMPtdNI_hp9CznoSzeHnBwVIY2', 1, 1, '2026-01-13 12:41:39'),
(13, 12, 'https://lh3.googleusercontent.com/aida-public/AB6AXuDyZynZ9wGNiLOY_vqvHdeIb4_Wq7kbchvzU5xRZaYblOi2itGiRT6HGGdzH_OPxnrpjmQmpuIZFhnsd53HmR_IbAw1vQsN6Ea-tegqqJFjvOjyJCsp2ANLc7A8ek06uompM6n6MDAB8Q5N8XbB0zdOkc9Pl1lN_u4tO9V9oX4fhAAJAZoRVZWtwx3IvJLuIMMl4Tdr0pfQ3TktxTBmKbXJ8Cn4i69pDjVFHIGf_X3cdzGPhons3hCMPtdNI_hp9CznoSzeHnBwVIY2', 1, 1, '2026-01-13 12:41:39'),
(14, 13, 'https://lh3.googleusercontent.com/aida-public/AB6AXuDr-15RiviFzCtn0XGu_7bGbYqukteTRSbLZYltiinr5cMTI6IhvciugOgt85PbqLHvuBizvHL17joQ9bHVqgm0b0PF_yl3PoB0jgg_Lo6_xT2rTWycNEdfTEmuQismpo2TQXwyBCdmKqOUYeheJOWaHfsCg5mmAtL3B8x0ovasH98cSK3a3SlIz93WcCWeKW0yIg0cHybPh_6N9yprCEqdmc9JAKsqSjb6dTYn2b3glGN_D0JK2fsKM0wrlSWdEIwNdyhx5Al-hlcM', 1, 1, '2026-01-13 12:41:39'),
(15, 15, 'uploads/products/product_15_1768333775_0.png', 0, 1, '2026-01-13 19:49:35'),
(16, 16, 'uploads/products/product_16_1768377849_0.png', 0, 1, '2026-01-14 08:04:09');

-- --------------------------------------------------------

--
-- Table structure for table `product_views`
--

CREATE TABLE `product_views` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `viewed_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `product_views`
--

INSERT INTO `product_views` (`id`, `product_id`, `user_id`, `ip_address`, `user_agent`, `viewed_at`) VALUES
(1, 3, NULL, '::1', NULL, '2026-01-13 14:26:40'),
(2, 1, NULL, '::1', NULL, '2026-01-13 14:26:56'),
(3, 1, NULL, '::1', NULL, '2026-01-13 14:27:03'),
(4, 1, NULL, '::1', NULL, '2026-01-13 14:31:42'),
(5, 1, NULL, '::1', NULL, '2026-01-13 14:36:08'),
(6, 1, NULL, '::1', NULL, '2026-01-13 14:45:49'),
(7, 1, NULL, '::1', NULL, '2026-01-13 14:46:45'),
(8, 2, NULL, '::1', NULL, '2026-01-13 15:03:49'),
(9, 4, NULL, '::1', NULL, '2026-01-13 15:11:08'),
(10, 4, NULL, '::1', NULL, '2026-01-13 15:24:19'),
(11, 4, NULL, '::1', NULL, '2026-01-13 15:30:31'),
(12, 4, NULL, '::1', NULL, '2026-01-13 15:31:54'),
(13, 4, NULL, '::1', NULL, '2026-01-13 15:32:50'),
(14, 4, NULL, '::1', NULL, '2026-01-13 15:35:08'),
(15, 4, NULL, '::1', NULL, '2026-01-13 15:35:39'),
(16, 4, NULL, '::1', NULL, '2026-01-13 15:35:43'),
(17, 4, NULL, '::1', NULL, '2026-01-13 15:35:44'),
(18, 4, NULL, '::1', NULL, '2026-01-13 15:38:45'),
(19, 4, NULL, '::1', NULL, '2026-01-13 15:39:49'),
(20, 4, NULL, '::1', NULL, '2026-01-13 15:40:10'),
(21, 4, NULL, '::1', NULL, '2026-01-13 15:40:40'),
(22, 4, NULL, '::1', NULL, '2026-01-13 15:40:47'),
(23, 4, 1, '::1', NULL, '2026-01-13 15:48:43'),
(24, 4, 1, '::1', NULL, '2026-01-13 15:49:11'),
(25, 4, 1, '::1', NULL, '2026-01-13 15:49:16'),
(26, 4, 1, '::1', NULL, '2026-01-13 15:50:17'),
(27, 8, 1, '::1', NULL, '2026-01-13 15:50:58'),
(28, 8, 1, '::1', NULL, '2026-01-13 15:51:56'),
(29, 8, 1, '::1', NULL, '2026-01-13 15:57:39'),
(30, 8, 1, '::1', NULL, '2026-01-13 16:35:29'),
(31, 10, 1, '::1', NULL, '2026-01-13 16:43:37'),
(32, 10, 1, '::1', NULL, '2026-01-13 16:43:51'),
(33, 3, 1, '::1', NULL, '2026-01-13 17:04:08'),
(34, 3, 1, '::1', NULL, '2026-01-13 17:04:49'),
(35, 3, 1, '::1', NULL, '2026-01-13 17:14:41'),
(36, 2, NULL, '::1', NULL, '2026-01-14 21:29:10'),
(37, 3, NULL, '::1', NULL, '2026-01-16 07:05:18');

-- --------------------------------------------------------

--
-- Table structure for table `reports`
--

CREATE TABLE `reports` (
  `id` int(11) NOT NULL,
  `reporter_id` int(11) NOT NULL,
  `reported_user_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `report_type` enum('user','product','service','message') NOT NULL,
  `reason` enum('spam','inappropriate','fraud','duplicate','other') NOT NULL,
  `description` text NOT NULL,
  `status` enum('pending','under_review','resolved','dismissed') DEFAULT 'pending',
  `reviewed_by` int(11) DEFAULT NULL,
  `resolution_notes` text DEFAULT NULL,
  `resolved_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `reviews`
--

CREATE TABLE `reviews` (
  `id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  `reviewed_user_id` int(11) NOT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `product_id` int(11) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `review_type` enum('product','service','seller','buyer') NOT NULL,
  `rating` int(11) NOT NULL CHECK (`rating` between 1 and 5),
  `review_text` text DEFAULT NULL,
  `images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`images`)),
  `is_verified_purchase` tinyint(1) DEFAULT 0,
  `helpful_count` int(11) DEFAULT 0,
  `status` enum('pending','approved','rejected') DEFAULT 'approved',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `reviews`
--

INSERT INTO `reviews` (`id`, `reviewer_id`, `reviewed_user_id`, `transaction_id`, `product_id`, `service_id`, `review_type`, `rating`, `review_text`, `images`, `is_verified_purchase`, `helpful_count`, `status`, `created_at`, `updated_at`) VALUES
(1, 7, 11, 1, 12, NULL, 'seller', 5, 'Great seller! Book was in excellent condition as described. Quick and smooth transaction.', NULL, 1, 0, 'approved', '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(2, 8, 11, 2, 11, NULL, 'seller', 5, 'Very helpful seller. Calculator works perfectly. Highly recommended!', NULL, 1, 0, 'approved', '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(3, 4, 8, 3, 7, NULL, 'seller', 4, 'Good quality laptop bag. Delivery was a bit delayed but overall satisfied.', NULL, 1, 0, 'approved', '2026-01-13 12:41:39', '2026-01-13 12:41:39');

--
-- Triggers `reviews`
--
DELIMITER $$
CREATE TRIGGER `after_review_insert` AFTER INSERT ON `reviews` FOR EACH ROW BEGIN
    UPDATE users 
    SET 
        rating = (SELECT AVG(rating) FROM reviews WHERE reviewed_user_id = NEW.reviewed_user_id AND status = 'approved'),
        total_ratings = (SELECT COUNT(*) FROM reviews WHERE reviewed_user_id = NEW.reviewed_user_id AND status = 'approved')
    WHERE id = NEW.reviewed_user_id;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `search_history`
--

CREATE TABLE `search_history` (
  `id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `search_query` varchar(255) NOT NULL,
  `filters` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`filters`)),
  `results_count` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `services`
--

CREATE TABLE `services` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `service_category_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `short_description` varchar(500) DEFAULT NULL,
  `pricing_type` enum('fixed','hourly','daily','project') DEFAULT 'fixed',
  `price` decimal(15,2) NOT NULL,
  `delivery_time` varchar(100) DEFAULT NULL,
  `availability` enum('available','busy','unavailable') DEFAULT 'available',
  `rating` decimal(3,2) DEFAULT 0.00,
  `total_ratings` int(11) DEFAULT 0,
  `total_orders` int(11) DEFAULT 0,
  `views_count` int(11) DEFAULT 0,
  `bookmarks_count` int(11) DEFAULT 0,
  `portfolio_images` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`portfolio_images`)),
  `skills` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`skills`)),
  `is_featured` tinyint(1) DEFAULT 0,
  `status` enum('active','paused','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `services`
--

INSERT INTO `services` (`id`, `user_id`, `service_category_id`, `title`, `slug`, `description`, `short_description`, `pricing_type`, `price`, `delivery_time`, `availability`, `rating`, `total_ratings`, `total_orders`, `views_count`, `bookmarks_count`, `portfolio_images`, `skills`, `is_featured`, `status`, `created_at`, `updated_at`) VALUES
(1, 2, 1, 'Graphic & UI Design', 'graphic-ui-design', 'Professional graphic design services including logos, posters, flyers, social media graphics, and UI/UX design. Student-friendly rates with agency-level quality. Fast turnaround time. Portfolio available upon request.', 'Need a poster, logo, or presentation? Student-friendly rates with agency-level quality.', 'project', 5000.00, '2-3 days', 'available', 4.90, 78, 156, 892, 0, NULL, NULL, 1, 'active', '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(2, 11, 2, 'Maths & Physics Tutoring', 'maths-physics-tutoring', 'Specialized tutoring for Engineering Mathematics and Physics courses. One-on-one or group sessions available. Exam preparation specialist with proven track record. Covers MTH 101-401 and PHY 101-401.', 'Specialized exam prep for Engineering courses. Get ahead of the curve this semester.', 'hourly', 2000.00, 'Flexible scheduling', 'available', 4.85, 92, 234, 1245, 0, NULL, NULL, 1, 'active', '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(3, 5, 3, 'PC & Phone Repairs', 'pc-phone-repairs', 'Professional computer and smartphone repair services. Screen replacement, battery replacement, software issues, virus removal, data recovery, and more. Fast and affordable tech repairs by certified student technicians. Same-day service available for most repairs.', 'Fast and affordable tech repairs by certified student technicians. Same-day service available.', 'fixed', 3000.00, '24-48 hours', 'available', 4.75, 67, 189, 734, 0, NULL, NULL, 1, 'active', '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(4, 18, 5, 'Professional Photo Editing', 'professional-photo-editing-1768376130', 'The new photogreaphy service for the new millenium', 'The photography', 'project', 20000.00, '2 - 3 Days', 'available', 0.00, 0, 0, 0, 0, NULL, NULL, 0, 'active', '2026-01-14 07:35:30', '2026-01-14 07:35:30');

-- --------------------------------------------------------

--
-- Table structure for table `service_categories`
--

CREATE TABLE `service_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(100) DEFAULT NULL,
  `color_code` varchar(20) DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `service_categories`
--

INSERT INTO `service_categories` (`id`, `name`, `slug`, `description`, `icon`, `color_code`, `is_active`, `created_at`) VALUES
(1, 'Creative Services', 'creative', 'Design, Graphics, and Creative work', 'palette', '#064E3B', 1, '2026-01-13 12:41:39'),
(2, 'Academic Tutoring', 'tutoring', 'Subject tutoring and exam prep', 'school', '#3B82F6', 1, '2026-01-13 12:41:39'),
(3, 'Tech Support', 'tech-support', 'Computer and phone repairs', 'build', '#F97316', 1, '2026-01-13 12:41:39'),
(4, 'Writing Services', 'writing', 'Content writing and editing', 'edit_note', '#8B5CF6', 1, '2026-01-13 12:41:39'),
(5, 'Photography', 'photography', 'Event and portrait photography', 'photo_camera', '#EC4899', 1, '2026-01-13 12:41:39');

-- --------------------------------------------------------

--
-- Table structure for table `sponsored_content`
--

CREATE TABLE `sponsored_content` (
  `id` int(11) NOT NULL,
  `user_id` int(10) DEFAULT NULL,
  `sponsor_name` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `image_url` varchar(500) DEFAULT NULL,
  `target_url` varchar(500) DEFAULT NULL,
  `placement` enum('hero','sidebar','footer','grid') DEFAULT 'grid',
  `click_count` int(11) DEFAULT 0,
  `view_count` int(11) DEFAULT 0,
  `budget` decimal(15,2) DEFAULT NULL,
  `spent` decimal(15,2) DEFAULT 0.00,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `status` enum('active','paused','ended') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sponsored_content`
--

INSERT INTO `sponsored_content` (`id`, `user_id`, `sponsor_name`, `title`, `description`, `image_url`, `target_url`, `placement`, `click_count`, `view_count`, `budget`, `spent`, `start_date`, `end_date`, `status`, `created_at`, `updated_at`) VALUES
(1, 19, 'Internship Hub', 'Summer Internship Hub', 'Find the best tech internships for 2024.', 'https://lh3.googleusercontent.com/aida-public/AB6AXuBUiH2u9u1nKamwsBksUCRNmSBmrHXY7-EWQe4GB9hp01dZScSWroKz7C_OVtmCMt6BWYuEAi15ZagHN6huwRgfwNySH-AN3pbmcUMJwczW-jhKILhRmoLukK9ON3XgE02OHdwpp7cRnBw2dwotdxUooSM5vK93XqwSt_0SLerdj4vgcNx6qyJFrEVhtHyOhGh9-QtZ8S0H1NjMvVUrOCZuBz9K2FO_yZgtkVDGIF2JixH6AtHfCg-QFIMJ9RPW2E0mmGggbJvuLC7O', 'https://internshiphub.com', 'grid', 156, 2345, NULL, 0.00, '2026-01-01', '2026-03-31', 'active', '2026-01-13 12:41:39', '2026-01-14 20:02:37'),
(2, 18, 'Design Academy', 'Graphic Design Masterclass', 'Student discount: 50% off for 48 hours.', 'https://lh3.googleusercontent.com/aida-public/AB6AXuD34MMi95aW5tRvHtIocL2m5HDKDQAf2Of4LYk18rf92c3FFvcS-inD0jCUMFCv5s6wtxSrl627ohBSbsnppvPvLvp_1FRFnbfhqXkR-cZzBGvKOP0Y_5_xWUyubQLvghgWwJf6P12ADfaQg2ZqvQiHgl2HZH6yr6uP0GppETTIcrcqKBeADgX-Pa-Uw3Xdpw9ElAvFcWGJDZsZbU9EKePe1UIx5fxcfgAeqRm-T-eCzjpGl5yJ5EGuWHF59yGJZ2nrier2WwbihTfi', 'https://designacademy.com', 'grid', 234, 1892, NULL, 0.00, '2026-01-01', '2026-02-28', 'active', '2026-01-13 12:41:39', '2026-01-14 20:02:26'),
(3, 18, 'BookRental Pro', 'Textbook Rental Pro', 'Rent your textbooks for pennies a day.', 'https://lh3.googleusercontent.com/aida-public/AB6AXuDyZynZ9wGNiLOY_vqvHdeIb4_Wq7kbchvzU5xRZaYblOi2itGiRT6HGGdzH_OPxnrpjmQmpuIZFhnsd53HmR_IbAw1vQsN6Ea-tegqqJFjvOjyJCsp2ANLc7A8ek06uompM6n6MDAB8Q5N8XbB0zdOkc9Pl1lN_u4tO9V9oX4fhAAJAZoRVZWtwx3IvJLuIMMl4Tdr0pfQ3TktxTBmKbXJ8Cn4i69pDjVFHIGf_X3cdzGPhons3hCMPtdNI_hp9CznoSzeHnBwVIY2', 'https://bookrentalpro.com', 'grid', 189, 1567, NULL, 0.00, '2026-01-01', '2026-12-31', 'active', '2026-01-13 12:41:39', '2026-01-14 20:02:26'),
(4, 18, 'TechFix Campus', 'Campus Tech Repair', 'Quick fixes for screens, batteries & more.', 'https://lh3.googleusercontent.com/aida-public/AB6AXuCIiIzDmCrRT_1Rz9yi681hD9D_Y6qM-8o-WlZ-s1zZMh55ER-XJDAovHiOsPAparfNWTaVdsAyjiFqq62rqVyridPPRO2JphM6dAt2z_Cglhm_1XUUbNxq1e5NAracJ7tVyR4_-jz7-eLD8aIfao5g8bVESmfY3q4yPqP49lnDW3jg6FaPgOoSq8zf0kB-2fmESajDbvFazGRO1Vx35XT03HZqsZ937ZV4T_-5__zF2ABxHgBX4CdTkEr-wcAVAOLbLkIEJwo1pGo1', 'https://techfixcampus.com', 'grid', 267, 2134, NULL, 0.00, '2026-01-01', '2026-06-30', 'active', '2026-01-13 12:41:39', '2026-01-14 20:02:26'),
(5, 18, 'Campus Bookstore', '20% Off Exam Prep Bundles', 'Get ready for finals with our comprehensive textbook sets. Exclusive to CampMart users.', 'https://lh3.googleusercontent.com/aida-public/AB6AXuD34MMi95aW5tRvHtIocL2m5HDKDQAf2Of4LYk18rf92c3FFvcS-inD0jCUMFCv5s6wtxSrl627ohBSbsnppvPvLvp_1FRFnbfhqXkR-cZzBGvKOP0Y_5_xWUyubQLvghgWwJf6P12ADfaQg2ZqvQiHgl2HZH6yr6uP0GppETTIcrcqKBeADgX-Pa-Uw3Xdpw9ElAvFcWGJDZsZbU9EKePe1UIx5fxcfgAeqRm-T-eCzjpGl5yJ5EGuWHF59yGJZ2nrier2WwbihTfi', 'https://campusbookstore.com', 'sidebar', 421, 3456, NULL, 0.00, '2026-01-01', '2026-12-31', 'active', '2026-01-13 12:41:39', '2026-01-14 20:02:26'),
(6, 18, 'DataPlan NG', 'Student Data Plans', 'Unlimited night data for your late-night study sessions. Activate today.', 'https://lh3.googleusercontent.com/aida-public/AB6AXuBUiH2u9u1nKamwsBksUCRNmSBmrHXY7-EWQe4GB9hp01dZScSWroKz7C_OVtmCMt6BWYuEAi15ZagHN6huwRgfwNySH-AN3pbmcUMJwczW-jhKILhRmoLukK9ON3XgE02OHdwpp7cRnBw2dwotdxUooSM5vK93XqwSt_0SLerdj4vgcNx6qyJFrEVhtHyOhGh9-QtZ8S0H1NjMvVUrOCZuBz9K2FO_yZgtkVDGIF2JixH6AtHfCg-QFIMJ9RPW2E0mmGggbJvuLC7O', 'https://dataplan.ng', 'sidebar', 356, 2890, NULL, 0.00, '2026-01-01', '2026-12-31', 'active', '2026-01-13 12:41:39', '2026-01-14 20:02:26'),
(7, 18, 'Olawale Jibson', 'Catchy Book Titles', 'This is the new wave of book titles', 'uploads/ads/ad_6967f58e801fb.jpeg', 'https://livepetal.com', 'grid', 0, 0, 10000.00, 0.00, '2026-01-15', '2026-01-25', 'active', '2026-01-14 19:59:10', '2026-01-14 20:02:26');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` text DEFAULT NULL,
  `setting_type` enum('string','number','boolean','json') DEFAULT 'string',
  `description` text DEFAULT NULL,
  `is_public` tinyint(1) DEFAULT 0,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `setting_key`, `setting_value`, `setting_type`, `description`, `is_public`, `updated_by`, `created_at`, `updated_at`) VALUES
(1, 'site_name', 'CampMart', 'string', 'Website name', 1, NULL, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(2, 'site_tagline', 'The Premium Campus Marketplace', 'string', 'Website tagline', 1, NULL, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(3, 'default_currency', 'NGN', 'string', 'Default currency code', 1, NULL, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(4, 'max_product_images', '5', 'number', 'Maximum images per product', 0, NULL, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(5, 'featured_product_fee', '1000', 'number', 'Fee to feature a product (in Naira)', 0, NULL, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(6, 'transaction_fee_percent', '2.5', 'number', 'Platform transaction fee percentage', 0, NULL, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(7, 'min_withdrawal_amount', '5000', 'number', 'Minimum withdrawal amount', 0, NULL, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(8, 'support_email', 'support@campmart.ng', 'string', 'Support email address', 1, NULL, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(9, 'support_phone', '08012345678', 'string', 'Support phone number', 1, NULL, '2026-01-13 12:41:39', '2026-01-13 12:41:39');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int(11) NOT NULL,
  `transaction_ref` varchar(100) NOT NULL,
  `product_id` int(11) DEFAULT NULL,
  `service_id` int(11) DEFAULT NULL,
  `buyer_id` int(11) NOT NULL,
  `seller_id` int(11) NOT NULL,
  `transaction_type` enum('product','service') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_method` enum('cash','transfer','card','wallet') DEFAULT 'cash',
  `payment_status` enum('pending','paid','failed','refunded') DEFAULT 'pending',
  `delivery_method` enum('meetup','campus_delivery','shipping') DEFAULT 'meetup',
  `delivery_status` enum('pending','processing','shipped','delivered','cancelled') DEFAULT 'pending',
  `delivery_address` text DEFAULT NULL,
  `tracking_number` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `status` enum('pending','confirmed','completed','cancelled','disputed') DEFAULT 'pending',
  `completed_at` timestamp NULL DEFAULT NULL,
  `cancelled_at` timestamp NULL DEFAULT NULL,
  `cancellation_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `transaction_ref`, `product_id`, `service_id`, `buyer_id`, `seller_id`, `transaction_type`, `amount`, `payment_method`, `payment_status`, `delivery_method`, `delivery_status`, `delivery_address`, `tracking_number`, `notes`, `status`, `completed_at`, `cancelled_at`, `cancellation_reason`, `created_at`, `updated_at`) VALUES
(1, 'TXN-2026-001', 12, NULL, 7, 11, 'product', 8500.00, 'transfer', 'paid', 'meetup', 'delivered', NULL, NULL, NULL, 'completed', '2026-01-08 13:30:00', NULL, NULL, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(2, 'TXN-2026-002', 11, NULL, 8, 11, 'product', 5000.00, 'cash', 'paid', 'campus_delivery', 'delivered', NULL, NULL, NULL, 'completed', '2026-01-09 15:45:00', NULL, NULL, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(3, 'TXN-2026-003', 7, NULL, 4, 8, 'product', 12000.00, 'transfer', 'paid', 'meetup', 'delivered', NULL, NULL, NULL, 'completed', '2026-01-10 10:20:00', NULL, NULL, '2026-01-13 12:41:39', '2026-01-13 12:41:39');

--
-- Triggers `transactions`
--
DELIMITER $$
CREATE TRIGGER `after_transaction_complete` AFTER UPDATE ON `transactions` FOR EACH ROW BEGIN
    IF NEW.status = 'completed' AND OLD.status != 'completed' THEN
        IF NEW.product_id IS NOT NULL THEN
            UPDATE products SET availability = 'sold', sold_at = NOW() WHERE id = NEW.product_id;
            UPDATE users SET total_sales = total_sales + 1 WHERE id = NEW.seller_id;
            UPDATE users SET total_purchases = total_purchases + 1 WHERE id = NEW.buyer_id;
        END IF;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `universities`
--

CREATE TABLE `universities` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(50) NOT NULL,
  `location` varchar(255) DEFAULT NULL,
  `country` varchar(100) DEFAULT 'Nigeria',
  `logo_url` varchar(500) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `universities`
--

INSERT INTO `universities` (`id`, `name`, `code`, `location`, `country`, `logo_url`, `status`, `created_at`, `updated_at`) VALUES
(1, 'Federal University of Technology, Akure', 'FUTA', 'Akure, Ondo State', 'Nigeria', 'https://example.com/logos/futa.png', 'active', '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(2, 'University of Lagos', 'UNILAG', 'Lagos, Lagos State', 'Nigeria', 'https://example.com/logos/unilag.png', 'active', '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(3, 'Obafemi Awolowo University', 'OAU', 'Ile-Ife, Osun State', 'Nigeria', 'https://example.com/logos/oau.png', 'active', '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(4, 'University of Ibadan', 'UI', 'Ibadan, Oyo State', 'Nigeria', 'https://example.com/logos/ui.png', 'active', '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(5, 'Ahmadu Bello University', 'ABU', 'Zaria, Kaduna State', 'Nigeria', 'https://example.com/logos/abu.png', 'active', '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(6, 'Adekunle Ajasin University, Akungba', 'AAUA', 'Akungba-Akoko', 'Nigeria', '', 'active', '2026-01-15 23:05:36', '2026-01-15 23:05:36'),
(7, 'Federal University of Technology, Minna', 'FUTMINNA', 'Minna', 'Nigeria', '', 'active', '2026-01-15 23:06:35', '2026-01-15 23:06:35');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `pubkey` varchar(65) DEFAULT NULL,
  `username` varchar(100) NOT NULL,
  `email` varchar(255) NOT NULL,
  `role` enum('user','admin','seller') DEFAULT 'user',
  `password_hash` varchar(255) NOT NULL,
  `firstname` varchar(25) DEFAULT NULL,
  `lastname` varchar(25) DEFAULT NULL,
  `full_name` varchar(255) NOT NULL,
  `affiliate_code` varchar(12) NOT NULL,
  `referred_by` int(10) NOT NULL DEFAULT 0,
  `phone` varchar(20) DEFAULT NULL,
  `location` varchar(150) DEFAULT NULL,
  `bio` varchar(260) DEFAULT NULL,
  `date_of_birth` varchar(22) DEFAULT NULL,
  `profile_image` varchar(500) DEFAULT NULL,
  `university_id` int(11) DEFAULT NULL,
  `student_id` varchar(100) DEFAULT NULL,
  `department` varchar(150) DEFAULT NULL,
  `level` varchar(16) DEFAULT NULL,
  `role_id` int(11) DEFAULT 3,
  `is_verified` tinyint(1) DEFAULT 0,
  `verification_method` enum('email','student_id','phone','admin') DEFAULT 'email',
  `rating` decimal(3,2) DEFAULT 0.00,
  `total_ratings` int(11) DEFAULT 0,
  `total_sales` int(11) DEFAULT 0,
  `total_purchases` int(11) DEFAULT 0,
  `account_balance` decimal(15,2) DEFAULT 0.00,
  `status` enum('active','suspended','banned','pending') DEFAULT 'pending',
  `last_login` timestamp NULL DEFAULT NULL,
  `last_seen` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `pubkey`, `username`, `email`, `role`, `password_hash`, `firstname`, `lastname`, `full_name`, `affiliate_code`, `referred_by`, `phone`, `location`, `bio`, `date_of_birth`, `profile_image`, `university_id`, `student_id`, `department`, `level`, `role_id`, `is_verified`, `verification_method`, `rating`, `total_ratings`, `total_sales`, `total_purchases`, `account_balance`, `status`, `last_login`, `last_seen`, `created_at`, `updated_at`) VALUES
(1, NULL, 'admin', 'admin@campmart.ng', 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, 'System Administrator', '', 0, '08012345678', NULL, NULL, NULL, 'https://lh3.googleusercontent.com/aida-public/AB6AXuAjdTN-7iGUrMSaOC0E033hZX2t2y0cK-akq4Axba-3aH-8KjmvHJJDWTkGKagW6Gu58FgOFK6iHXRGb2rpaoCKtoTJUlAo1G1IiExKzdrPntaHyhBTwtvzxjDmqUg2X-T7M041GqciVdC6b7LlBfZEh_T6u6l68EzSw_63KG7L6DKHhZ2deqzDPMoHIFYWcnF3yye_FNWf_afEWlz1Vyic5MATIHnq88O1aMvlbZstIW8NKOPKv1zfhI7Medu8Sgmqn9vfx2QVpKNf', 1, NULL, 'Administration', NULL, 1, 1, 'email', 5.00, 100, 0, 0, 0.00, 'active', '2026-01-13 12:41:39', NULL, '2026-01-13 12:41:39', '2026-01-13 13:47:23'),
(2, NULL, 'alexjohnson', 'alex.johnson@futa.edu.ng', 'user', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, 'Alex Johnson', '', 0, '08023456789', NULL, NULL, NULL, 'https://i.pravatar.cc/150?img=12', 1, 'FUT/19/0234', 'Computer Science', NULL, 4, 1, 'email', 4.85, 45, 23, 0, 0.00, 'active', '2026-01-13 12:41:39', NULL, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(3, NULL, 'campuskicks', 'info@campuskicks.com', 'user', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, 'CampusKicks', '', 0, '08034567890', NULL, NULL, NULL, 'https://i.pravatar.cc/150?img=25', 1, 'FUT/20/1145', 'Business Administration', NULL, 4, 1, 'email', 4.72, 89, 156, 0, 0.00, 'active', '2026-01-13 12:41:39', NULL, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(4, NULL, 'studyspace', 'contact@studyspace.ng', 'user', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, 'StudySpace', '', 0, '08045678901', NULL, NULL, NULL, 'https://i.pravatar.cc/150?img=18', 1, 'FUT/19/0567', 'Industrial Design', NULL, 4, 1, 'email', 4.90, 67, 89, 0, 0.00, 'active', '2026-01-13 12:41:39', NULL, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(5, NULL, 'techtrade', 'sales@techtrade.ng', 'user', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, 'TechTrade', '', 0, '08056789012', NULL, NULL, NULL, 'https://i.pravatar.cc/150?img=33', 1, 'FUT/18/0892', 'Electrical Engineering', NULL, 4, 1, 'email', 4.65, 112, 198, 0, 0.00, 'active', '2026-01-13 12:41:39', NULL, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(6, NULL, 'fithub', 'fithub@campus.ng', 'user', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, 'FitHub', '', 0, '08067890123', NULL, NULL, NULL, 'https://i.pravatar.cc/150?img=45', 1, 'FUT/20/0234', 'Sports Science', NULL, 3, 1, 'email', 4.50, 34, 45, 0, 0.00, 'active', '2026-01-13 12:41:39', NULL, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(7, NULL, 'audiohub', 'contact@audiohub.ng', 'user', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, 'AudioHub', '', 0, '08078901234', NULL, NULL, NULL, 'https://i.pravatar.cc/150?img=28', 1, 'FUT/19/1567', 'Music Technology', NULL, 4, 1, 'email', 4.78, 56, 78, 0, 0.00, 'active', '2026-01-13 12:41:39', NULL, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(8, NULL, 'peripheralshop', 'info@peripheralshop.ng', 'user', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, 'PeripheralShop', '', 0, '08089012345', NULL, NULL, NULL, 'https://i.pravatar.cc/150?img=52', 1, 'FUT/20/0678', 'Computer Engineering', NULL, 4, 1, 'email', 4.00, 1, 123, 0, 0.00, 'active', '2026-01-13 12:41:39', NULL, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(9, NULL, 'campusfashion', 'style@campusfashion.ng', 'user', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, 'CampusFashion', '', 0, '08090123456', NULL, NULL, NULL, 'https://i.pravatar.cc/150?img=31', 1, 'FUT/19/0901', 'Fashion Design', NULL, 4, 1, 'email', 4.82, 92, 167, 0, 0.00, 'active', '2026-01-13 12:41:39', NULL, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(10, NULL, 'sneakerworld', 'kicks@sneakerworld.ng', 'user', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, 'SneakerWorld', '', 0, '08001234567', NULL, NULL, NULL, 'https://i.pravatar.cc/150?img=41', 1, 'FUT/18/1234', 'Marketing', NULL, 4, 1, 'email', 4.70, 105, 189, 0, 0.00, 'active', '2026-01-13 12:41:39', NULL, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(11, NULL, 'academicstore', 'support@academicstore.ng', 'user', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, 'AcademicStore', '', 0, '08012345670', NULL, NULL, NULL, 'https://i.pravatar.cc/150?img=15', 1, 'FUT/19/0456', 'Mathematics', NULL, 4, 1, 'email', 5.00, 2, 245, 0, 0.00, 'active', '2026-01-13 12:41:39', NULL, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(12, NULL, 'samuelobi', 'samuel.obi@futa.edu.ng', 'user', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, 'Samuel Obi', '', 0, '08023456780', NULL, NULL, NULL, 'https://i.pravatar.cc/150?img=8', 1, 'FUT/20/0789', 'Civil Engineering', NULL, 3, 1, 'email', 4.60, 15, 8, 0, 0.00, 'active', '2026-01-13 12:41:39', NULL, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(13, NULL, 'johndoe', 'john.doe@futa.edu.ng', 'user', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, 'John Doe', '', 0, '08034567891', NULL, NULL, NULL, 'https://i.pravatar.cc/150?img=22', 1, 'FUT/21/0123', 'Mechanical Engineering', NULL, 3, 1, 'email', 4.40, 10, 5, 0, 0.00, 'active', '2026-01-13 12:41:39', NULL, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(14, NULL, 'jamesmiller', 'james.miller@futa.edu.ng', 'user', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, 'James Miller', '', 0, '08045678902', NULL, NULL, NULL, 'https://i.pravatar.cc/150?img=36', 1, 'FUT/20/0345', 'Chemistry', NULL, 3, 1, 'email', 4.75, 20, 12, 0, 0.00, 'active', '2026-01-13 12:41:39', NULL, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(15, NULL, 'dradeyemi', 'adeyemi@futa.edu.ng', 'user', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, 'Dr. Adeyemi', '', 0, '08056789023', NULL, NULL, NULL, 'https://i.pravatar.cc/150?img=49', 1, 'STAFF/001', 'Chemistry Department', NULL, 3, 1, 'email', 5.00, 45, 0, 0, 0.00, 'active', '2026-01-13 12:41:39', NULL, '2026-01-13 12:41:39', '2026-01-13 12:41:39'),
(16, NULL, 'emmanuelchukwu', 'emmanuel.c@futa.edu.ng', 'user', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', NULL, NULL, 'Emmanuel Chukwu', 'ht48f6', 0, '08067890134', NULL, NULL, NULL, 'https://i.pravatar.cc/150?img=56', 1, 'FUT/18/0678', 'Architecture', NULL, 3, 1, 'email', 4.55, 18, 7, 0, 0.00, 'active', '2026-01-13 12:41:39', NULL, '2026-01-13 12:41:39', '2026-01-13 18:13:58'),
(18, 'dth926i1oktcrmqjym7upi', 'ogbajigodwin', 'ogbajigodwin@gmail.com', 'admin', '$2y$10$e76/cr8ZInIeiuJUEPltBuPHdBpZlMtNjACQvF1NONZgFuxzRMbm6', 'Godwin', 'Ogbaji', 'Godwin Ogbaji', 'rghg2t', 16, '+2348032318588', 'Block CA', 'tell2', '2026-01-15', 'uploads/profiles/18_1768412965.jpeg', NULL, NULL, 'Mechanical Engineering', '200', 3, 0, 'email', 0.00, 0, 0, 0, 0.00, 'active', '2026-01-14 19:42:16', '2026-01-14 19:42:16', '2026-01-13 18:16:13', '2026-01-15 08:11:58'),
(19, 'sa7lcj7nv5pc8xfepuzj9l', 'ogbajiaremu', 'ogbajiaremu@gmail.com', 'user', '$2y$10$AeG7g1f/.U/wETAY7S4hOeaqaXT4KC2.JnTbQTn7d5LxpATA.7huS', 'Oluwabukola', 'Aremu', 'Oluwabukola Aremu', '4k6v2c', 18, '08032318583', NULL, NULL, NULL, 'uploads/profiles/19_1768514318.jpeg', NULL, NULL, NULL, NULL, 3, 0, 'email', 0.00, 0, 0, 0, 0.00, 'active', '2026-01-15 21:57:27', '2026-01-15 21:57:27', '2026-01-14 18:06:25', '2026-01-15 21:58:38');

-- --------------------------------------------------------

--
-- Table structure for table `user_roles`
--

CREATE TABLE `user_roles` (
  `id` int(11) NOT NULL,
  `role_name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `permissions` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL CHECK (json_valid(`permissions`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `user_roles`
--

INSERT INTO `user_roles` (`id`, `role_name`, `description`, `permissions`, `created_at`) VALUES
(1, 'super_admin', 'Super Administrator with full access', '{\"all\": true}', '2026-01-13 12:41:39'),
(2, 'admin', 'Administrator with management access', '{\"manage_users\": true, \"manage_products\": true, \"manage_reports\": true}', '2026-01-13 12:41:39'),
(3, 'student', 'Regular student user', '{\"create_listings\": true, \"buy_products\": true, \"message\": true}', '2026-01-13 12:41:39'),
(4, 'verified_seller', 'Verified seller with enhanced features', '{\"create_listings\": true, \"featured_listings\": true, \"analytics\": true}', '2026-01-13 12:41:39'),
(5, 'moderator', 'Content moderator', '{\"approve_content\": true, \"manage_reports\": true}', '2026-01-13 12:41:39');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `affiliate_earnings`
--
ALTER TABLE `affiliate_earnings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `referral_user_id` (`referral_user_id`),
  ADD KEY `order_id` (`order_id`),
  ADD KEY `status` (`status`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `affiliate_withdrawals`
--
ALTER TABLE `affiliate_withdrawals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `status` (`status`),
  ADD KEY `created_at` (`created_at`);

--
-- Indexes for table `bookmarks`
--
ALTER TABLE `bookmarks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_bookmark` (`user_id`,`product_id`,`service_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_product` (`product_id`),
  ADD KEY `idx_service` (`service_id`);

--
-- Indexes for table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_slug` (`slug`),
  ADD KEY `idx_parent` (`parent_id`),
  ADD KEY `idx_active` (`is_active`);

--
-- Indexes for table `conversations`
--
ALTER TABLE `conversations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `service_id` (`service_id`),
  ADD KEY `user2_id` (`user2_id`),
  ADD KEY `idx_users` (`user1_id`,`user2_id`),
  ADD KEY `idx_last_message` (`last_message_at`);

--
-- Indexes for table `flash_sales`
--
ALTER TABLE `flash_sales`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status_featured` (`status`,`is_featured`),
  ADD KEY `idx_time_range` (`start_time`,`end_time`),
  ADD KEY `idx_created_by` (`created_by`);

--
-- Indexes for table `flash_sale_products`
--
ALTER TABLE `flash_sale_products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_sale_product` (`flash_sale_id`,`product_id`),
  ADD KEY `idx_flash_sale_id` (`flash_sale_id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_is_active` (`is_active`);

--
-- Indexes for table `flash_sale_views`
--
ALTER TABLE `flash_sale_views`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_flash_sale_id` (`flash_sale_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_viewed_at` (`viewed_at`);

--
-- Indexes for table `lost_found_items`
--
ALTER TABLE `lost_found_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `claimed_by` (`claimed_by`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_type` (`type`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_date` (`date_lost_found`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_conversation` (`conversation_id`),
  ADD KEY `idx_sender` (`sender_id`),
  ADD KEY `idx_receiver` (`receiver_id`),
  ADD KEY `idx_read` (`is_read`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_read` (`is_read`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `orders`
--
ALTER TABLE `orders`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `order_number` (`order_number`),
  ADD KEY `idx_order_number` (`order_number`),
  ADD KEY `idx_buyer_id` (`buyer_id`),
  ADD KEY `idx_seller_id` (`seller_id`),
  ADD KEY `idx_product_id` (`product_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `products`
--
ALTER TABLE `products`
  ADD PRIMARY KEY (`id`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_category` (`category_id`),
  ADD KEY `idx_slug` (`slug`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_availability` (`availability`),
  ADD KEY `idx_price` (`price`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_featured` (`is_featured`),
  ADD KEY `idx_trending` (`is_trending`);
ALTER TABLE `products` ADD FULLTEXT KEY `idx_search` (`title`,`description`);

--
-- Indexes for table `product_images`
--
ALTER TABLE `product_images`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product` (`product_id`);

--
-- Indexes for table `product_views`
--
ALTER TABLE `product_views`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_product` (`product_id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_viewed` (`viewed_at`);

--
-- Indexes for table `reports`
--
ALTER TABLE `reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `reporter_id` (`reporter_id`),
  ADD KEY `reported_user_id` (`reported_user_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `service_id` (`service_id`),
  ADD KEY `reviewed_by` (`reviewed_by`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_type` (`report_type`);

--
-- Indexes for table `reviews`
--
ALTER TABLE `reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transaction_id` (`transaction_id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `service_id` (`service_id`),
  ADD KEY `idx_reviewer` (`reviewer_id`),
  ADD KEY `idx_reviewed` (`reviewed_user_id`),
  ADD KEY `idx_rating` (`rating`);

--
-- Indexes for table `search_history`
--
ALTER TABLE `search_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_query` (`search_query`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `services`
--
ALTER TABLE `services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user` (`user_id`),
  ADD KEY `idx_category` (`service_category_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_rating` (`rating`);
ALTER TABLE `services` ADD FULLTEXT KEY `idx_search` (`title`,`description`);

--
-- Indexes for table `service_categories`
--
ALTER TABLE `service_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `slug` (`slug`),
  ADD KEY `idx_slug` (`slug`);

--
-- Indexes for table `sponsored_content`
--
ALTER TABLE `sponsored_content`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_dates` (`start_date`,`end_date`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `setting_key` (`setting_key`),
  ADD KEY `updated_by` (`updated_by`),
  ADD KEY `idx_key` (`setting_key`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `transaction_ref` (`transaction_ref`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `service_id` (`service_id`),
  ADD KEY `idx_ref` (`transaction_ref`),
  ADD KEY `idx_buyer` (`buyer_id`),
  ADD KEY `idx_seller` (`seller_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_payment_status` (`payment_status`),
  ADD KEY `idx_created` (`created_at`);

--
-- Indexes for table `universities`
--
ALTER TABLE `universities`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `idx_code` (`code`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `role_id` (`role_id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_university` (`university_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_rating` (`rating`);

--
-- Indexes for table `user_roles`
--
ALTER TABLE `user_roles`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `role_name` (`role_name`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_logs`
--
ALTER TABLE `activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `affiliate_earnings`
--
ALTER TABLE `affiliate_earnings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `affiliate_withdrawals`
--
ALTER TABLE `affiliate_withdrawals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `bookmarks`
--
ALTER TABLE `bookmarks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `conversations`
--
ALTER TABLE `conversations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `flash_sales`
--
ALTER TABLE `flash_sales`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `flash_sale_products`
--
ALTER TABLE `flash_sale_products`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `flash_sale_views`
--
ALTER TABLE `flash_sale_views`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `lost_found_items`
--
ALTER TABLE `lost_found_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `orders`
--
ALTER TABLE `orders`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `products`
--
ALTER TABLE `products`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `product_images`
--
ALTER TABLE `product_images`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `product_views`
--
ALTER TABLE `product_views`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=38;

--
-- AUTO_INCREMENT for table `reports`
--
ALTER TABLE `reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `reviews`
--
ALTER TABLE `reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `search_history`
--
ALTER TABLE `search_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `services`
--
ALTER TABLE `services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `service_categories`
--
ALTER TABLE `service_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `sponsored_content`
--
ALTER TABLE `sponsored_content`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `universities`
--
ALTER TABLE `universities`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `user_roles`
--
ALTER TABLE `user_roles`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_logs`
--
ALTER TABLE `activity_logs`
  ADD CONSTRAINT `activity_logs_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `bookmarks`
--
ALTER TABLE `bookmarks`
  ADD CONSTRAINT `bookmarks_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookmarks_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `bookmarks_ibfk_3` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `categories`
--
ALTER TABLE `categories`
  ADD CONSTRAINT `categories_ibfk_1` FOREIGN KEY (`parent_id`) REFERENCES `categories` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `conversations`
--
ALTER TABLE `conversations`
  ADD CONSTRAINT `conversations_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `conversations_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `conversations_ibfk_3` FOREIGN KEY (`user1_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `conversations_ibfk_4` FOREIGN KEY (`user2_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `flash_sales`
--
ALTER TABLE `flash_sales`
  ADD CONSTRAINT `fk_flash_sales_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `flash_sale_products`
--
ALTER TABLE `flash_sale_products`
  ADD CONSTRAINT `fk_flash_sale_products_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_flash_sale_products_sale` FOREIGN KEY (`flash_sale_id`) REFERENCES `flash_sales` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `flash_sale_views`
--
ALTER TABLE `flash_sale_views`
  ADD CONSTRAINT `fk_flash_sale_views_sale` FOREIGN KEY (`flash_sale_id`) REFERENCES `flash_sales` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_flash_sale_views_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `lost_found_items`
--
ALTER TABLE `lost_found_items`
  ADD CONSTRAINT `lost_found_items_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `lost_found_items_ibfk_2` FOREIGN KEY (`claimed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`conversation_id`) REFERENCES `conversations` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_3` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `orders`
--
ALTER TABLE `orders`
  ADD CONSTRAINT `fk_orders_buyer` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_orders_product` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_orders_seller` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `products`
--
ALTER TABLE `products`
  ADD CONSTRAINT `products_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `products_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `products_ibfk_3` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `product_images`
--
ALTER TABLE `product_images`
  ADD CONSTRAINT `product_images_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `product_views`
--
ALTER TABLE `product_views`
  ADD CONSTRAINT `product_views_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_views_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `reports`
--
ALTER TABLE `reports`
  ADD CONSTRAINT `reports_ibfk_1` FOREIGN KEY (`reporter_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reports_ibfk_2` FOREIGN KEY (`reported_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reports_ibfk_3` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reports_ibfk_4` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reports_ibfk_5` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `reviews`
--
ALTER TABLE `reviews`
  ADD CONSTRAINT `reviews_ibfk_1` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_2` FOREIGN KEY (`reviewed_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_3` FOREIGN KEY (`transaction_id`) REFERENCES `transactions` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `reviews_ibfk_4` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `reviews_ibfk_5` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `search_history`
--
ALTER TABLE `search_history`
  ADD CONSTRAINT `search_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `services`
--
ALTER TABLE `services`
  ADD CONSTRAINT `services_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `services_ibfk_2` FOREIGN KEY (`service_category_id`) REFERENCES `service_categories` (`id`);

--
-- Constraints for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD CONSTRAINT `system_settings_ibfk_1` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`service_id`) REFERENCES `services` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `transactions_ibfk_3` FOREIGN KEY (`buyer_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `transactions_ibfk_4` FOREIGN KEY (`seller_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`university_id`) REFERENCES `universities` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `users_ibfk_2` FOREIGN KEY (`role_id`) REFERENCES `user_roles` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
