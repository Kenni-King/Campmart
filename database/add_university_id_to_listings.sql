-- Add university_id column to products, services, and lost_found_items tables
-- This allows filtering listings by university

-- Add university_id to products table
ALTER TABLE `products` 
ADD COLUMN `university_id` INT(11) DEFAULT NULL AFTER `user_id`,
ADD KEY `idx_university` (`university_id`);

-- Add university_id to services table
ALTER TABLE `services` 
ADD COLUMN `university_id` INT(11) DEFAULT NULL AFTER `user_id`,
ADD KEY `idx_university` (`university_id`);

-- Add university_id to lost_found_items table
ALTER TABLE `lost_found_items` 
ADD COLUMN `university_id` INT(11) DEFAULT NULL AFTER `user_id`,
ADD KEY `idx_university` (`university_id`);

-- Add university_id to sponsored_content table
ALTER TABLE `sponsored_content` 
ADD COLUMN `university_id` INT(11) DEFAULT NULL AFTER `user_id`,
ADD KEY `idx_university` (`university_id`);

-- Update existing records to inherit university_id from users table
UPDATE `products` p 
INNER JOIN `users` u ON p.user_id = u.id 
SET p.university_id = u.university_id 
WHERE u.university_id IS NOT NULL;

UPDATE `services` s 
INNER JOIN `users` u ON s.user_id = u.id 
SET s.university_id = u.university_id 
WHERE u.university_id IS NOT NULL;

UPDATE `lost_found_items` lf 
INNER JOIN `users` u ON lf.user_id = u.id 
SET lf.university_id = u.university_id 
WHERE u.university_id IS NOT NULL;

UPDATE `sponsored_content` sc 
INNER JOIN `users` u ON sc.user_id = u.id 
SET sc.university_id = u.university_id 
WHERE u.university_id IS NOT NULL;
