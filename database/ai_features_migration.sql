-- =====================================================
-- CampMart AI feature schema (part 2)
-- Run once via phpMyAdmin or:
--   mysql -u root -p campmartv2 < database/ai_features_migration.sql
--
-- Adds:
--   service_embeddings      -> semantic vectors for services
--   lost_found_embeddings   -> semantic vectors for lost & found items
--   support_tickets         -> missing table used by support-tickets.php
--   FULLTEXT index          -> keyword search over services
-- =====================================================

CREATE TABLE IF NOT EXISTS `service_embeddings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `service_id` int(11) NOT NULL,
  `model` varchar(100) NOT NULL,
  `embedding` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `text_hash` char(64) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_service_model` (`service_id`, `model`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `lost_found_embeddings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `model` varchar(100) NOT NULL,
  `embedding` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `text_hash` char(64) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_item_model` (`item_id`, `model`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `support_tickets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `category` varchar(100) DEFAULT NULL,
  `priority` enum('low','medium','high') DEFAULT 'medium',
  `description` text NOT NULL,
  `status` enum('open','in_progress','closed','resolved') DEFAULT 'open',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tickets_user` (`user_id`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Keyword search for services (hybrid ranking + autocomplete)
ALTER TABLE `services` ADD FULLTEXT INDEX `ft_services_search` (`title`, `description`) ;
