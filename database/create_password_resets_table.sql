-- Create password_resets table for handling password reset tokens
-- Run this SQL to set up the password reset functionality

CREATE TABLE IF NOT EXISTS `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `used_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`),
  KEY `token` (`token`),
  KEY `email` (`email`),
  KEY `expires_at` (`expires_at`),
  CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Index for faster token lookups
CREATE INDEX idx_token_expires ON password_resets(token, expires_at);

-- Clean up expired tokens (older than 24 hours)
DELETE FROM password_resets WHERE expires_at < DATE_SUB(NOW(), INTERVAL 24 HOUR);
