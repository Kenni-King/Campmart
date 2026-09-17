CREATE TABLE `guide_videos` (
  `id` int(11) NOT AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `video_url` varchar(500) NOT NULL COMMENT 'YouTube URL or embed URL',
  `icon` varchar(50) DEFAULT 'play_circle' COMMENT 'Material icon name',
  `icon_color` varchar(20) DEFAULT 'primary' COMMENT 'Tailwind color class for icon',
  `display_order` int(11) NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `display_order` (`display_order`),
  KEY `is_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
