-- CampMart AI feature schema
-- Run once via phpMyAdmin or:  mysql -u root -p campmartv2 < database/ai_migration.sql
--
-- Adds:
--   product_embeddings   -> semantic vectors for products (semantic search + recommendations)
--   ai_request_logs      -> audit trail of AI API calls (cost control / debugging)
--   FULLTEXT index       -> fast keyword fallback + prefix matching for autocomplete
--   search_history index -> fast personalized / popular suggestions

CREATE TABLE IF NOT EXISTS `product_embeddings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `product_id` int(11) NOT NULL,
  `model` varchar(100) NOT NULL,
  `embedding` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL,
  `text_hash` char(64) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_product_model` (`product_id`, `model`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ai_request_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `provider` varchar(50) DEFAULT NULL,
  `model` varchar(100) DEFAULT NULL,
  `endpoint` varchar(100) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `prompt_tokens` int(11) NOT NULL DEFAULT 0,
  `completion_tokens` int(11) NOT NULL DEFAULT 0,
  `latency_ms` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Keyword search index for hybrid ranking + fast autocomplete
-- NOTE: `tags` cannot be part of a FULLTEXT index because it has a CHECK (json_valid) constraint.
ALTER TABLE `products` ADD FULLTEXT INDEX `ft_products_search` (`title`, `description`);

-- Faster personalized suggestions and popular search queries
ALTER TABLE `search_history` ADD INDEX `idx_sh_user_time` (`user_id`, `created_at`);
ALTER TABLE `search_history` ADD INDEX `idx_sh_query` (`search_query`);
