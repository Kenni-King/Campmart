<?php
/**
 * CampMart v2 - Database Test & Verification Page
 * Use this page to verify your database installation and connection
 */

require_once 'includes/constant.php';

// Set page style
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CampMart Database Test</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #064E3B 0%, #065F46 100%);
            padding: 20px;
            color: #333;
        }
        .container { 
            max-width: 1200px; 
            margin: 0 auto; 
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        h1 { 
            color: #064E3B; 
            margin-bottom: 10px;
            font-size: 2.5em;
        }
        .subtitle {
            color: #64748b;
            margin-bottom: 30px;
            font-size: 1.1em;
        }
        .status-box {
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            border-left: 4px solid;
        }
        .success { 
            background: #f0fdf4; 
            border-color: #22c55e;
            color: #166534;
        }
        .error { 
            background: #fef2f2; 
            border-color: #ef4444;
            color: #991b1b;
        }
        .warning {
            background: #fffbeb;
            border-color: #f59e0b;
            color: #92400e;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            margin: 20px 0;
            background: white;
        }
        th, td { 
            padding: 12px; 
            text-align: left; 
            border-bottom: 1px solid #e2e8f0;
        }
        th { 
            background: #f8fafc; 
            font-weight: 600;
            color: #064E3B;
        }
        tr:hover { background: #f8fafc; }
        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 0.85em;
            font-weight: 600;
        }
        .badge-success { background: #d1fae5; color: #065f46; }
        .badge-error { background: #fee2e2; color: #991b1b; }
        .section {
            margin: 30px 0;
            padding: 20px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        }
        .section h2 {
            color: #064E3B;
            margin-bottom: 15px;
            font-size: 1.5em;
        }
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .stat-card {
            background: linear-gradient(135deg, #064E3B 0%, #065F46 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }
        .stat-card h3 {
            font-size: 2em;
            margin-bottom: 5px;
        }
        .stat-card p {
            opacity: 0.9;
            font-size: 0.9em;
        }
        .icon { 
            font-size: 1.5em; 
            margin-right: 8px;
        }
        pre {
            background: #f8fafc;
            padding: 15px;
            border-radius: 6px;
            overflow-x: auto;
            font-size: 0.9em;
            border: 1px solid #e2e8f0;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #064E3B;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            margin: 5px;
            transition: all 0.3s;
        }
        .btn:hover {
            background: #065F46;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(6, 78, 59, 0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 CampMart Database Test</h1>
        <p class="subtitle">Database Installation & Connection Verification</p>

        <?php
        // Test database connection
        $connection_status = true;
        $error_message = '';
        
        if ($db->connect_error) {
            $connection_status = false;
            $error_message = $db->connect_error;
        }
        ?>

        <!-- Connection Status -->
        <div class="status-box <?php echo $connection_status ? 'success' : 'error'; ?>">
            <strong>
                <?php if ($connection_status): ?>
                    ✅ Database Connection: Successful
                <?php else: ?>
                    ❌ Database Connection: Failed
                <?php endif; ?>
            </strong>
            <?php if (!$connection_status): ?>
                <p>Error: <?php echo htmlspecialchars($error_message); ?></p>
                <p>Please check your database credentials in includes/constant.php</p>
            <?php endif; ?>
        </div>

        <?php if ($connection_status): ?>

        <!-- Database Statistics -->
        <div class="section">
            <h2>📊 Database Statistics</h2>
            <div class="stat-grid">
                <?php
                $stats = [
                    ['label' => 'Total Users', 'query' => 'SELECT COUNT(*) as count FROM users', 'icon' => '👥'],
                    ['label' => 'Products', 'query' => 'SELECT COUNT(*) as count FROM products', 'icon' => '📦'],
                    ['label' => 'Categories', 'query' => 'SELECT COUNT(*) as count FROM categories', 'icon' => '📁'],
                    ['label' => 'Services', 'query' => 'SELECT COUNT(*) as count FROM services', 'icon' => '🛠️'],
                    ['label' => 'Transactions', 'query' => 'SELECT COUNT(*) as count FROM transactions', 'icon' => '💳'],
                    ['label' => 'Lost & Found', 'query' => 'SELECT COUNT(*) as count FROM lost_found_items', 'icon' => '🔍'],
                ];

                foreach ($stats as $stat) {
                    $result = $db->query($stat['query']);
                    $count = $result ? $result->fetch_assoc()['count'] : 0;
                    echo "<div class='stat-card'>";
                    echo "<div>{$stat['icon']}</div>";
                    echo "<h3>{$count}</h3>";
                    echo "<p>{$stat['label']}</p>";
                    echo "</div>";
                }
                ?>
            </div>
        </div>

        <!-- Table Verification -->
        <div class="section">
            <h2>📋 Table Verification</h2>
            <p>Checking if all required tables exist...</p>
            <table>
                <thead>
                    <tr>
                        <th>Table Name</th>
                        <th>Status</th>
                        <th>Records</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $required_tables = [
                        'universities', 'user_roles', 'users', 'categories', 'products', 
                        'product_images', 'service_categories', 'services', 'lost_found_items',
                        'transactions', 'conversations', 'messages', 'bookmarks', 'reviews',
                        'notifications', 'sponsored_content', 'reports', 'activity_logs',
                        'system_settings', 'product_views', 'search_history'
                    ];

                    foreach ($required_tables as $table) {
                        $exists = $db->query("SHOW TABLES LIKE '$table'");
                        $status = $exists && $exists->num_rows > 0;
                        
                        $count = 0;
                        if ($status) {
                            $count_result = $db->query("SELECT COUNT(*) as count FROM $table");
                            $count = $count_result ? $count_result->fetch_assoc()['count'] : 0;
                        }
                        
                        echo "<tr>";
                        echo "<td><strong>{$table}</strong></td>";
                        echo "<td>";
                        if ($status) {
                            echo "<span class='badge badge-success'>✓ Exists</span>";
                        } else {
                            echo "<span class='badge badge-error'>✗ Missing</span>";
                        }
                        echo "</td>";
                        echo "<td>{$count} records</td>";
                        echo "</tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>

        <!-- Sample Data Preview -->
        <div class="section">
            <h2>🔍 Sample Data Preview</h2>
            
            <h3 style="color: #064E3B; margin: 20px 0 10px;">Recent Products</h3>
            <?php
            $products = $db->query("
                SELECT p.title, p.price, u.username as seller, c.name as category, p.created_at
                FROM products p
                JOIN users u ON p.user_id = u.id
                JOIN categories c ON p.category_id = c.id
                ORDER BY p.created_at DESC
                LIMIT 5
            ");

            if ($products && $products->num_rows > 0):
            ?>
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Price</th>
                        <th>Seller</th>
                        <th>Category</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $products->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['title']); ?></td>
                        <td>₦<?php echo number_format($row['price'], 2); ?></td>
                        <td><?php echo htmlspecialchars($row['seller']); ?></td>
                        <td><?php echo htmlspecialchars($row['category']); ?></td>
                        <td><?php echo date('M d, Y', strtotime($row['created_at'])); ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="status-box warning">
                ⚠️ No products found. Please run the seed data script.
            </div>
            <?php endif; ?>

            <h3 style="color: #064E3B; margin: 20px 0 10px;">Active Users</h3>
            <?php
            $users = $db->query("
                SELECT username, full_name, rating, total_sales, status
                FROM users
                WHERE status = 'active'
                ORDER BY total_sales DESC
                LIMIT 5
            ");

            if ($users && $users->num_rows > 0):
            ?>
            <table>
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Rating</th>
                        <th>Total Sales</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while($row = $users->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                        <td><?php echo htmlspecialchars($row['full_name']); ?></td>
                        <td>⭐ <?php echo number_format($row['rating'], 2); ?></td>
                        <td><?php echo $row['total_sales']; ?></td>
                        <td><span class="badge badge-success"><?php echo $row['status']; ?></span></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="status-box warning">
                ⚠️ No users found. Please run the seed data script.
            </div>
            <?php endif; ?>
        </div>

        <!-- Configuration Info -->
        <div class="section">
            <h2>⚙️ Configuration</h2>
            <pre>Database Host: <?php echo DB_SERVER; ?>
Database Name: <?php echo DB_NAME; ?>
Database User: <?php echo DB_USER; ?>
Character Set: <?php echo $db->character_set_name(); ?>
Server Version: <?php echo $db->server_info; ?>
Client Version: <?php echo $db->client_info; ?></pre>
        </div>

        <!-- Quick Links -->
        <div class="section">
            <h2>🔗 Quick Links</h2>
            <a href="index.php" class="btn">📱 View Homepage</a>
            <a href="database/README.md" class="btn">📖 Database Docs</a>
            <a href="IMPLEMENTATION_GUIDE.md" class="btn">🚀 Implementation Guide</a>
            <a href="DATABASE_SUMMARY.md" class="btn">📊 Summary</a>
        </div>

        <!-- Next Steps -->
        <div class="status-box success">
            <h3 style="margin-bottom: 10px;">✅ Database is Ready!</h3>
            <p><strong>Next Steps:</strong></p>
            <ol style="margin-left: 20px; margin-top: 10px;">
                <li>Review the <strong>IMPLEMENTATION_GUIDE.md</strong> to convert static sections to dynamic</li>
                <li>Check <strong>database/query_examples.sql</strong> for ready-to-use queries</li>
                <li>Read <strong>database/README.md</strong> for complete documentation</li>
                <li>Start building dynamic pages (products.php, services.php, etc.)</li>
            </ol>
        </div>

        <?php else: ?>
        
        <!-- Installation Instructions -->
        <div class="section">
            <h2>📦 Installation Required</h2>
            <p>The database connection failed. Please follow these steps:</p>
            <ol style="margin: 15px 0 15px 20px; line-height: 1.8;">
                <li>Make sure XAMPP MySQL is running</li>
                <li>Open MySQL command line or phpMyAdmin</li>
                <li>Run: <code>mysql -u root -p &lt; database/install.sql</code></li>
                <li>Or import <strong>database/campmartv2_structure.sql</strong> and <strong>database/campmartv2_seed_data.sql</strong> via phpMyAdmin</li>
                <li>Refresh this page</li>
            </ol>
        </div>

        <?php endif; ?>

        <!-- Footer -->
        <div style="text-align: center; margin-top: 40px; padding-top: 20px; border-top: 2px solid #e2e8f0; color: #64748b;">
            <p><strong>CampMart v2</strong> - Campus Marketplace Database</p>
            <p style="font-size: 0.9em; margin-top: 5px;">Version 1.0 | MySQL 5.7+ Compatible</p>
        </div>
    </div>
</body>
</html>
