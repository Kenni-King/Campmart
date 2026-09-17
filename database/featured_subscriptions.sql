-- Featured Subscriptions System
-- Run this migration to add subscription plans and featured subscription tracking

-- 1. Subscription Plans table
CREATE TABLE IF NOT EXISTS `subscription_plans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `slug` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `price` decimal(15,2) NOT NULL,
  `duration_days` int(11) NOT NULL,
  `entity_type` enum('product','service','profile','all') DEFAULT 'all',
  `is_active` tinyint(1) DEFAULT 1,
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Featured Subscriptions table
CREATE TABLE IF NOT EXISTS `featured_subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `entity_type` enum('product','service','profile') NOT NULL,
  `entity_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `start_date` datetime NOT NULL,
  `end_date` datetime NOT NULL,
  `amount_paid` decimal(15,2) NOT NULL,
  `payment_ref` varchar(100) DEFAULT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `status` enum('active','expired','cancelled','refunded') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `entity` (`entity_type`, `entity_id`),
  KEY `user_id` (`user_id`),
  KEY `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Add featured columns to users table
ALTER TABLE `users` ADD COLUMN `is_featured` tinyint(1) DEFAULT 0 AFTER `status`;
ALTER TABLE `users` ADD COLUMN `featured_at` datetime DEFAULT NULL AFTER `is_featured`;
ALTER TABLE `users` ADD COLUMN `featured_until` datetime DEFAULT NULL AFTER `featured_at`;

-- 4. Seed default plans
INSERT IGNORE INTO `subscription_plans` (`name`, `slug`, `price`, `duration_days`, `entity_type`, `sort_order`) VALUES
('Basic Featured',   'basic-featured',   500.00,  7, 'all', 1),
('Premium Featured', 'premium-featured', 1000.00, 14, 'all', 2),
('Pro Featured',    'pro-featured',     2000.00, 30, 'all', 3);
