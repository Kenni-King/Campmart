-- Add university_id to sponsored_content table for localization

-- Add university_id to sponsored_content table
ALTER TABLE `sponsored_content` 
ADD COLUMN `university_id` INT(11) DEFAULT NULL AFTER `user_id`,
ADD KEY `idx_university` (`university_id`);

-- Update existing records to inherit university_id from users table
UPDATE `sponsored_content` sc 
INNER JOIN `users` u ON sc.user_id = u.id 
SET sc.university_id = u.university_id 
WHERE u.university_id IS NOT NULL;
