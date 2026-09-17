<?php
// Autocomplete endpoint for the search bars.
// Returns product/service titles, categories, and past searches.
header('Content-Type: application/json; charset=utf-8');
require_once '../includes/constant.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$q = trim($_GET['q'] ?? '');
$scope = ($_GET['scope'] ?? 'products') === 'services' ? 'services' : 'products';

if (strlen($q) < 2) {
    echo json_encode(['success' => true, 'query' => $q, 'scope' => $scope, 'suggestions' => []]);
    exit;
}

$results_url = $scope === 'services' ? 'services.php' : 'products.php';
$suggestions = [];
$seen = [];

$esc = $db->real_escape_string($q);

// 1. This user's own recent searches (personalized)
if (isset($_SESSION['userAppId'])) {
    $uid = (int) $_SESSION['userAppId'];
    $r = $db->query(
        "SELECT search_query, COUNT(*) AS cnt, MAX(created_at) AS last
         FROM search_history
         WHERE user_id = $uid AND search_query LIKE '{$esc}%'
         GROUP BY search_query
         ORDER BY cnt DESC, last DESC
         LIMIT 3"
    );
    while ($row = $r->fetch_assoc()) {
        $text = (string) $row['search_query'];
        if (isset($seen[$text])) {
            continue;
        }
        $seen[$text] = true;
        $suggestions[] = [
            'type' => 'history',
            'text' => $text,
            'sub' => 'Your recent search',
            'url' => $results_url . '?search=' . rawurlencode($text),
        ];
    }
}

// 2. Popular searches across all users
$r = $db->query(
    "SELECT search_query, COUNT(*) AS cnt
     FROM search_history
     WHERE search_query LIKE '{$esc}%'
     GROUP BY search_query
     ORDER BY cnt DESC
     LIMIT 3"
);
while ($row = $r->fetch_assoc()) {
    $text = (string) $row['search_query'];
    if (isset($seen[$text])) {
        continue;
    }
    $seen[$text] = true;
    $suggestions[] = [
        'type' => 'popular',
        'text' => $text,
        'sub' => 'Popular search',
        'url' => $results_url . '?search=' . rawurlencode($text),
    ];
}

// 2.5 Semantic suggestions: meaning-based matches via embeddings
if (strlen($q) >= 3) {
    require_once '../includes/ai/embeddings.php';
    require_once '../includes/ai/vectors.php';
    if (defined('AI_ENABLED') && AI_ENABLED && !empty(AI_API_KEY)) {
        $vec = ai_embed($q);
        if (is_array($vec)) {
            if ($scope === 'services') {
                $semSql = "SELECT se.service_id AS sid, se.embedding FROM service_embeddings se
                           JOIN services s ON s.id = se.service_id
                           WHERE s.status = 'active' LIMIT 300";
            } else {
                $semSql = "SELECT pe.product_id AS sid, pe.embedding FROM product_embeddings pe
                           JOIN products p ON p.id = pe.product_id
                           WHERE p.status = 'approved' AND p.availability = 'available' LIMIT 300";
            }
            $scored = [];
            $sr = $db->query($semSql);
            if ($sr) {
                while ($row = $sr->fetch_assoc()) {
                    $v = json_decode((string) $row['embedding'], true);
                    if (is_array($v)) {
                        $scored[] = [(int) $row['sid'], ai_cosine_similarity($vec, $v)];
                    }
                }
                usort($scored, fn($a, $b) => $b[1] <=> $a[1]);
                $semIds = array_column(array_slice($scored, 0, 5), 0);
                if ($semIds) {
                    $idList = implode(',', $semIds);
                    if ($scope === 'services') {
                        $sr2 = $db->query("SELECT id, title, slug FROM services WHERE id IN ($idList)");
                        while ($row = $sr2->fetch_assoc()) {
                            $key = 'sem:' . $row['id'];
                            if (isset($seen[$key])) {
                                continue;
                            }
                            $seen[$key] = true;
                            $suggestions[] = [
                                'type' => 'semantic',
                                'text' => $row['title'],
                                'sub' => 'AI match for "' . htmlspecialchars($q) . '"',
                                'url' => 'service/' . $row['slug'],
                            ];
                        }
                    } else {
                        $sr2 = $db->query("SELECT id, title, slug FROM products WHERE id IN ($idList)");
                        while ($row = $sr2->fetch_assoc()) {
                            $key = 'sem:' . $row['id'];
                            if (isset($seen[$key])) {
                                continue;
                            }
                            $seen[$key] = true;
                            $suggestions[] = [
                                'type' => 'semantic',
                                'text' => $row['title'],
                                'sub' => 'AI match for "' . htmlspecialchars($q) . '"',
                                'url' => 'product/' . $row['slug'],
                            ];
                        }
                    }
                }
            }
        }
    }
}

// 3. Matching listings (title prefix first, then contains)
$prefix_count = count(array_filter($suggestions, fn($s) => in_array($s['type'], ['history', 'popular', 'semantic'], true)));
$remaining = 8 - $prefix_count;

if ($scope === 'services') {
    $r = $db->query(
        "SELECT id, title, slug FROM services
         WHERE status = 'active' AND title LIKE '{$esc}%'
         ORDER BY total_orders DESC, rating DESC
         LIMIT " . max(0, $remaining)
    );
    while ($row = $r->fetch_assoc()) {
        $key = 'svc:' . $row['slug'];
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $suggestions[] = [
            'type' => 'service',
            'text' => $row['title'],
            'sub' => 'Service',
            'url' => 'service/' . $row['slug'],
        ];
    }
} else {
    $r = $db->query(
        "SELECT p.id, p.title, p.slug, pi.image_url
         FROM products p
         LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
         WHERE p.status = 'approved' AND p.availability = 'available' AND p.title LIKE '{$esc}%'
         ORDER BY p.is_featured DESC, p.views_count DESC
         LIMIT " . max(0, $remaining)
    );
    while ($row = $r->fetch_assoc()) {
        $key = 'prod:' . $row['id'];
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $suggestions[] = [
            'type' => 'product',
            'text' => $row['title'],
            'sub' => 'Product',
            'url' => 'product/' . $row['slug'],
            'image' => $row['image_url'],
        ];
    }
}

// 4. Matching categories
if ($scope === 'services') {
    $r = $db->query(
        "SELECT name, slug FROM service_categories
         WHERE is_active = 1 AND name LIKE '{$esc}%'
         ORDER BY name LIMIT 3"
    );
    while ($row = $r->fetch_assoc()) {
        $key = 'scat:' . $row['slug'];
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $suggestions[] = [
            'type' => 'category',
            'text' => $row['name'],
            'sub' => 'Service category',
            'url' => 'services.php?category=' . rawurlencode($row['slug']),
        ];
    }
} else {
    $r = $db->query(
        "SELECT name, slug FROM categories
         WHERE is_active = 1 AND name LIKE '{$esc}%'
         ORDER BY name LIMIT 3"
    );
    while ($row = $r->fetch_assoc()) {
        $key = 'cat:' . $row['slug'];
        if (isset($seen[$key])) {
            continue;
        }
        $seen[$key] = true;
        $suggestions[] = [
            'type' => 'category',
            'text' => $row['name'],
            'sub' => 'Category',
            'url' => 'products.php?category=' . rawurlencode($row['slug']),
        ];
    }
}

$suggestions[] = [
    'type' => 'search',
    'text' => $q,
    'sub' => 'Search CampMart',
    'url' => $results_url . '?search=' . rawurlencode($q),
];

echo json_encode([
    'success' => true,
    'query' => $q,
    'scope' => $scope,
    'suggestions' => array_slice($suggestions, 0, 10),
]);
