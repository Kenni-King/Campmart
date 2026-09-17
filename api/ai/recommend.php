<?php
// GET /api/ai/recommend.php?limit=8
// Returns personalized product recommendations for the logged-in user.
header('Content-Type: application/json; charset=utf-8');
require_once '../../includes/constant.php';
require_once '../../includes/ai/recommend.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$limit = min(20, max(1, (int) ($_GET['limit'] ?? 8)));

$userId = (int) ($_SESSION['userAppId'] ?? 0);
$universityId = 0;
if ($userId > 0) {
    $u = $db->query("SELECT university_id FROM users WHERE id = $userId")->fetch_assoc();
    $universityId = (int) ($u['university_id'] ?? 0);
}

$ranked = ai_recommend_products($userId, ['limit' => $limit, 'universityId' => $universityId]);

$products = [];
if (!empty($ranked)) {
    $ids = implode(',', array_map('intval', array_keys($ranked)));
    $r = $db->query(
        "SELECT p.id, p.title, p.slug, p.price, p.views_count, p.is_featured, u.full_name AS seller_name,
                pi.image_url
         FROM products p
         JOIN users u ON p.user_id = u.id
         LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
         WHERE p.id IN ($ids)"
    );
    $byId = [];
    while ($row = $r->fetch_assoc()) {
        $byId[(int) $row['id']] = $row;
    }
    foreach ($ranked as $id => $score) {
        if (!isset($byId[$id])) {
            continue;
        }
        $p = $byId[$id];
        $products[] = [
            'id' => (int) $p['id'],
            'title' => $p['title'],
            'slug' => $p['slug'],
            'price' => (float) $p['price'],
            'views_count' => (int) $p['views_count'],
            'is_featured' => (bool) $p['is_featured'],
            'seller_name' => $p['seller_name'],
            'image_url' => $p['image_url'],
            'score' => (float) $score,
        ];
    }
}

echo json_encode([
    'success' => true,
    'ai_enabled' => defined('AI_ENABLED') && AI_ENABLED && !empty(AI_API_KEY),
    'user_id' => $userId,
    'products' => $products,
]);
