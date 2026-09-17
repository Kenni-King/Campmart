-- Add SEO image settings and general settings to system_settings table
-- Run this SQL to initialize the SEO and general settings

-- Insert social preview image setting (if not exists)
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`, `is_public`)
SELECT 'social_preview_image', 'uploads/campmart-social-preview.jpg', 'string', 'Social media preview image for Open Graph and Twitter Cards', 1
WHERE NOT EXISTS (SELECT 1 FROM `system_settings` WHERE `setting_key` = 'social_preview_image');

-- Insert site logo setting (if not exists)
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`, `is_public`)
SELECT 'site_logo', 'uploads/campmart-logo.png', 'string', 'Site logo for structured data and branding', 1
WHERE NOT EXISTS (SELECT 1 FROM `system_settings` WHERE `setting_key` = 'site_logo');

-- Update existing site_name and site_tagline or insert if they don't exist
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`, `is_public`)
VALUES ('site_tagline', 'The Premium Campus Marketplace', 'string', 'Website tagline', 1)
ON DUPLICATE KEY UPDATE `setting_value` = 'The Premium Campus Marketplace';

-- Insert support email (if not exists)
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`, `is_public`)
SELECT 'support_email', 'support@campmart.ng', 'string', 'Support email address', 1
WHERE NOT EXISTS (SELECT 1 FROM `system_settings` WHERE `setting_key` = 'support_email');

-- Insert support phone (if not exists)
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`, `is_public`)
SELECT 'support_phone', '+234 800 000 0000', 'string', 'Support phone number', 1
WHERE NOT EXISTS (SELECT 1 FROM `system_settings` WHERE `setting_key` = 'support_phone');

-- Insert default campus (if not exists)
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`, `is_public`)
SELECT 'default_campus', 'FUTA', 'string', 'Default campus/university', 1
WHERE NOT EXISTS (SELECT 1 FROM `system_settings` WHERE `setting_key` = 'default_campus');

-- Insert meta description (if not exists)
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`, `is_public`)
SELECT 'meta_description', 'Marketplace for students to buy, sell, and swap items on campus.', 'string', 'Default meta description', 1
WHERE NOT EXISTS (SELECT 1 FROM `system_settings` WHERE `setting_key` = 'meta_description');

-- Insert toggle settings (if not exists)
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`, `is_public`)
SELECT 'maintenance_mode', '0', 'boolean', 'Enable maintenance mode', 0
WHERE NOT EXISTS (SELECT 1 FROM `system_settings` WHERE `setting_key` = 'maintenance_mode');

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`, `is_public`)
SELECT 'allow_signups', '1', 'boolean', 'Allow new user signups', 0
WHERE NOT EXISTS (SELECT 1 FROM `system_settings` WHERE `setting_key` = 'allow_signups');

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`, `is_public`)
SELECT 'require_verification', '1', 'boolean', 'Require email verification for listings', 0
WHERE NOT EXISTS (SELECT 1 FROM `system_settings` WHERE `setting_key` = 'require_verification');

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`, `is_public`)
SELECT 'enable_chat', '1', 'boolean', 'Enable buyer-seller chat', 0
WHERE NOT EXISTS (SELECT 1 FROM `system_settings` WHERE `setting_key` = 'enable_chat');

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`, `is_public`)
SELECT 'auto_approve_listings', '0', 'boolean', 'Auto-approve listings without review', 0
WHERE NOT EXISTS (SELECT 1 FROM `system_settings` WHERE `setting_key` = 'auto_approve_listings');

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`, `is_public`)
SELECT 'email_notifications', '1', 'boolean', 'Send email notifications for orders', 0
WHERE NOT EXISTS (SELECT 1 FROM `system_settings` WHERE `setting_key` = 'email_notifications');

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`, `is_public`)
SELECT 'allow_guest_browsing', '1', 'boolean', 'Allow guest browsing without login', 0
WHERE NOT EXISTS (SELECT 1 FROM `system_settings` WHERE `setting_key` = 'allow_guest_browsing');

INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`, `is_public`)
SELECT 'enable_wishlist', '1', 'boolean', 'Enable wishlist/bookmark feature', 0
WHERE NOT EXISTS (SELECT 1 FROM `system_settings` WHERE `setting_key` = 'enable_wishlist');
