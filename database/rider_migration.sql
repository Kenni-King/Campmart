-- Platform Riders Migration
-- Adds rider role, delivery_tasks table, and delivery_option column to orders

ALTER TABLE users MODIFY COLUMN role enum('user','admin','seller','rider') NOT NULL DEFAULT 'user';

ALTER TABLE orders ADD COLUMN delivery_option enum('pickup','riders') NOT NULL DEFAULT 'pickup' AFTER buyer_phone;

CREATE TABLE IF NOT EXISTS delivery_tasks (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT(10) UNSIGNED NOT NULL,
  rider_id INT(11) DEFAULT NULL,
  status enum('interested','assigned','picked_up','delivered','completed','cancelled') NOT NULL DEFAULT 'interested',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
  FOREIGN KEY (rider_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
