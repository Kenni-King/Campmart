<?php
include_once dirname(__DIR__, 3).'/includes/constant.php';
include_once dirname(__DIR__, 3).'/includes/function.php';

function sqL1($table, $col, $value) {
    global $db;
    $col = preg_replace('/[^a-zA-Z0-9_]/', '', $col);
    $value = $db->real_escape_string($value);
    $sql = $db->query("SELECT 1 FROM `$table` WHERE `$col`='$value' LIMIT 1");
    return $sql ? $sql->num_rows : 0;
}

function sqLx($table, $col, $value, $returnCol) {
    global $db;
    $col = preg_replace('/[^a-zA-Z0-9_]/', '', $col);
    $returnCol = preg_replace('/[^a-zA-Z0-9_]/', '', $returnCol);
    $value = $db->real_escape_string($value);
    $sql = $db->query("SELECT `$returnCol` FROM `$table` WHERE `$col`='$value' LIMIT 1");
    if ($sql && $sql->num_rows) {
        $row = $sql->fetch_assoc();
        return $row[$returnCol] ?? null;
    }
    return null;
}

function ensureTable($tableName, $createSql) {
    global $db;
    $db->query("CREATE TABLE IF NOT EXISTS `$tableName` $createSql");
}

function ensureColumn($table, $column, $definition) {
    global $db;
    $column = $db->real_escape_string($column);
    $exists = $db->query("SHOW COLUMNS FROM `$table` LIKE '$column'");
    if ($exists && $exists->num_rows == 0) {
        $db->query("ALTER TABLE `$table` ADD COLUMN $column $definition");
    }
}

function createBusinessTable() {
    ensureTable('business', "(
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `user_id` INT(11) NOT NULL DEFAULT 0,
        `name` VARCHAR(255) DEFAULT NULL,
        `address` VARCHAR(255) DEFAULT NULL,
        `phone` VARCHAR(50) DEFAULT NULL,
        `email` VARCHAR(255) DEFAULT NULL,
        `description` TEXT DEFAULT NULL,
        `logo` VARCHAR(255) DEFAULT NULL,
        `photo` VARCHAR(255) DEFAULT NULL,
        `qr_payment_option` VARCHAR(20) NOT NULL DEFAULT 'both',
        `qr_delivery_available` INT(2) NOT NULL DEFAULT 0,
        `status` ENUM('active','inactive') DEFAULT 'active',
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `user_id` (`user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function createStaffTable() {
    ensureTable('staff', "(
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `bid` INT(11) NOT NULL DEFAULT 0,
        `pub` VARCHAR(255) DEFAULT NULL,
        `role` VARCHAR(50) DEFAULT 'staff',
        `name` VARCHAR(255) DEFAULT NULL,
        `status` ENUM('active','inactive') DEFAULT 'active',
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `bid` (`bid`),
        KEY `pub` (`pub`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function createPaymentMethodsTable() {
    ensureTable('payment_methods', "(
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `bid` INT(11) NOT NULL DEFAULT 0,
        `title` VARCHAR(255) DEFAULT NULL,
        `type` VARCHAR(50) DEFAULT 'bank',
        `bankName` VARCHAR(255) DEFAULT NULL,
        `accountName` VARCHAR(255) DEFAULT NULL,
        `accountNumber` VARCHAR(50) DEFAULT NULL,
        `note` TEXT DEFAULT NULL,
        `status` ENUM('active','inactive') DEFAULT 'active',
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `bid` (`bid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

function createSalesOrderTable() {
    ensureTable('salesorder', "(
        `id` INT(11) NOT NULL AUTO_INCREMENT,
        `salesid` VARCHAR(100) DEFAULT NULL,
        `total` DECIMAL(15,2) DEFAULT 0,
        `uid` INT(11) DEFAULT 0,
        `bid` INT(11) DEFAULT 0,
        `cid` INT(11) DEFAULT 0,
        `customer` VARCHAR(255) DEFAULT NULL,
        `mode` VARCHAR(50) DEFAULT NULL,
        `phone` VARCHAR(50) DEFAULT NULL,
        `app` VARCHAR(50) DEFAULT 'qrcode',
        `data` LONGTEXT DEFAULT NULL,
        `status` INT(2) DEFAULT 1,
        `salesdate` DATETIME DEFAULT NULL,
        `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `salesid` (`salesid`),
        KEY `bid` (`bid`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

class DashboardPro {

    function getProducts($bid) {
        global $db;
        $bid = (int)$bid;
        $products = [];
        $business = $db->query("SELECT user_id FROM business WHERE id='$bid' LIMIT 1");
        if (!$business || !$business->num_rows) return $products;
        $businessRow = $business->fetch_assoc();
        $userId = (int)$businessRow['user_id'];

        if ($userId > 0) {
            $sql = $db->query("
                SELECT p.id, p.title, p.price, p.description, p.metadata,
                       c.name AS category,
                       (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) AS photo
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.user_id = '$userId' AND p.status = 'approved' AND p.availability = 'available'
                ORDER BY p.created_at DESC
            ");
        } else {
            $sql = $db->query("
                SELECT p.id, p.title, p.price, p.description, p.metadata,
                       c.name AS category,
                       (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.id AND pi.is_primary = 1 LIMIT 1) AS photo
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                WHERE p.status = 'approved' AND p.availability = 'available'
                ORDER BY p.created_at DESC
            ");
        }

        while ($row = $sql->fetch_assoc()) {
            $photo = $row['photo'] ?? '';
            if ($photo && strpos($photo, 'http') !== 0) {
                $photo = $this->baseUrl().'/'.$photo;
            }
            $meta = json_decode($row['metadata'] ?? '{}', true);
            $products[] = [
                'id' => $row['id'],
                'title' => $row['title'],
                'price' => $row['price'],
                'photo' => $photo,
                'category' => $row['category'] ?? 'Uncategorized',
                'note' => substr(strip_tags($row['description'] ?? ''), 0, 100),
                'barcode' => $meta['barcode'] ?? ''
            ];
        }
        return $products;
    }

    function getPayMethod($bid) {
        global $db;
        $bid = (int)$bid;
        $methods = [];
        $sql = $db->query("SELECT * FROM payment_methods WHERE bid='$bid' AND status='active' ORDER BY id ASC");
        while ($row = $sql->fetch_assoc()) {
            $methods[] = [
                'id' => $row['id'],
                'title' => $row['title'],
                'bankName' => $row['bankName'],
                'accountName' => $row['accountName'],
                'accountNumber' => $row['accountNumber'],
                'note' => $row['note'] ?? ''
            ];
        }
        return $methods;
    }

    function EnsureDeliveryTables() {
        ensureTable('deliveries', "(
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `bid` INT(11) DEFAULT 0,
            `salesid` VARCHAR(100) DEFAULT NULL,
            `source_type` VARCHAR(50) DEFAULT 'salesorder',
            `customer` VARCHAR(255) DEFAULT NULL,
            `phone` VARCHAR(50) DEFAULT NULL,
            `address` TEXT DEFAULT NULL,
            `rider_id` INT(11) DEFAULT 0,
            `status` VARCHAR(50) DEFAULT 'pending_assignment',
            `received_at` DATETIME DEFAULT NULL,
            `review_stars` INT(2) DEFAULT NULL,
            `review_note` TEXT DEFAULT NULL,
            `reviewed_at` DATETIME DEFAULT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `bid` (`bid`),
            KEY `salesid` (`salesid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        ensureTable('delivery_riders', "(
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `bid` INT(11) DEFAULT 0,
            `name` VARCHAR(255) DEFAULT NULL,
            `phone` VARCHAR(50) DEFAULT NULL,
            `vehicle` VARCHAR(100) DEFAULT NULL,
            `plate_no` VARCHAR(50) DEFAULT NULL,
            `status` ENUM('active','inactive') DEFAULT 'active',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `bid` (`bid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        ensureTable('delivery_logs', "(
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `bid` INT(11) DEFAULT 0,
            `delivery_id` INT(11) DEFAULT 0,
            `salesid` VARCHAR(100) DEFAULT NULL,
            `status` VARCHAR(50) DEFAULT NULL,
            `note` TEXT DEFAULT NULL,
            `uid` INT(11) DEFAULT 0,
            `actor` VARCHAR(50) DEFAULT 'system',
            `created` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `delivery_id` (`delivery_id`),
            KEY `salesid` (`salesid`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    function DeliveryStatusLabels() {
        return [
            'pending_assignment' => 'Pending assignment',
            'assigned' => 'Rider assigned',
            'picked_up' => 'Picked up',
            'in_transit' => 'In transit',
            'out_for_delivery' => 'Out for delivery',
            'delivered' => 'Delivered',
            'received' => 'Received',
            'reviewed' => 'Reviewed',
            'cancelled' => 'Cancelled',
            'returned' => 'Returned'
        ];
    }

    private function baseUrl() {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        return rtrim($scheme.'://'.$host.preg_replace('#/api/v[12](/.*)?$#', '', $script), '/');
    }
}

createBusinessTable();
createStaffTable();
createPaymentMethodsTable();
createSalesOrderTable();

// Ensure business table has user_id column (legacy tables might lack it)
ensureColumn('business', 'user_id', 'INT(11) NOT NULL DEFAULT 0');
ensureColumn('business', 'qr_payment_option', "VARCHAR(20) NOT NULL DEFAULT 'both'");
ensureColumn('business', 'qr_delivery_available', 'INT(2) NOT NULL DEFAULT 0');

// Only create $pro if it doesn't exist (don't overwrite main app's $pro Profile object)
if (!isset($pro)) {
    $pro = new DashboardPro();
}
