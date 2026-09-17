-- Add email verification columns to users table
-- Run this SQL script to add email verification functionality

-- Add email verification columns if they don't exist
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS email_verified TINYINT(1) DEFAULT 0,
ADD COLUMN IF NOT EXISTS email_verification_token VARCHAR(255) NULL,
ADD COLUMN IF NOT EXISTS email_verification_expiry DATETIME NULL,
ADD COLUMN IF NOT EXISTS phone_verified TINYINT(1) DEFAULT 0;

-- Add index for faster lookups
ALTER TABLE users ADD INDEX idx_email_verification_token (email_verification_token);

-- Update existing users to have email_verified = 1 (if you want to grandfather them in)
-- UNCOMMENT the line below if you want existing users to be automatically verified
-- UPDATE users SET email_verified = 1 WHERE email_verified IS NULL OR email_verified = 0;
