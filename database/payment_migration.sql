-- Payment Gateway Migration for CampMart
-- Adds columns for Paystack, Flutterwave, escrow, and physical store pickup

ALTER TABLE orders
  ADD COLUMN `payment_gateway` VARCHAR(50) NULL DEFAULT NULL AFTER `payment_method`,
  ADD COLUMN `gateway_transaction_ref` VARCHAR(255) NULL DEFAULT NULL AFTER `payment_gateway`,
  ADD COLUMN `gateway_response` TEXT NULL DEFAULT NULL AFTER `gateway_transaction_ref`,
  ADD COLUMN `escrow_status` ENUM('held','released','refunded') NULL DEFAULT NULL AFTER `payment_status`,
  ADD COLUMN `buyer_completed` TINYINT(1) NOT NULL DEFAULT 0 AFTER `buyer_confirmation`,
  ADD COLUMN `seller_completed` TINYINT(1) NOT NULL DEFAULT 0 AFTER `buyer_completed`,
  ADD COLUMN `disbursed_at` DATETIME NULL DEFAULT NULL AFTER `seller_completed`,
  ADD COLUMN `store_pickup` TINYINT(1) NOT NULL DEFAULT 0 AFTER `delivery_status`,
  ADD COLUMN `pickup_address` TEXT NULL DEFAULT NULL AFTER `store_pickup`;

-- Payment gateway API key settings
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_type`, `description`, `is_public`, `updated_by`, `created_at`, `updated_at`) VALUES
('payment_option_paystack', '0', 'boolean', 'Enable Paystack online payment gateway', 0, NULL, NOW(), NOW()),
('payment_option_flutterwave', '0', 'boolean', 'Enable Flutterwave online payment gateway', 0, NULL, NOW(), NOW()),
('payment_option_pod', '1', 'boolean', 'Enable Pay on Delivery (physical store pickup)', 0, NULL, NOW(), NOW()),
('paystack_public_key', '', 'string', 'Paystack public key', 0, NULL, NOW(), NOW()),
('paystack_secret_key', '', 'string', 'Paystack secret key', 0, NULL, NOW(), NOW()),
('flutterwave_public_key', '', 'string', 'Flutterwave public key', 0, NULL, NOW(), NOW()),
('flutterwave_secret_key', '', 'string', 'Flutterwave secret key', 0, NULL, NOW(), NOW()),
('flutterwave_encryption_key', '', 'string', 'Flutterwave encryption key', 0, NULL, NOW(), NOW())
ON DUPLICATE KEY UPDATE `setting_key` = VALUES(`setting_key`);
