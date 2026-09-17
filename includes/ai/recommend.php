<?php
// Personalized recommendations.
//
// Strategy:
//   1. Collect the user's interests from product_views, bookmarks, orders and
//      recent searches (search_history).
//   2. Build a single "interest vector" = weighted average of the embeddings
//      of those products / search queries.
//   3. Score every available product against the interest vector.
//   4. Exclude products the user already owns / saw / bookmarked / bought.
//
// When AI is disabled or the user has no signals yet, falls back to popular
// products (featured + most viewed), so the section never looks empty.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/embeddings.php';
require_once __DIR__ . '/vectors.php';

function ai_user_interest_ids($userId) {
    global $db;
    $userId = (int) $userId;
    $interest = [];

    $res = $db->query(
        "SELECT pv.product_id, COUNT(*) AS w
         FROM product_views pv
         JOIN products p ON p.id = pv.product_id
         WHERE pv.user_id = $userId AND p.status = 'approved' AND p.availability = 'available'
         GROUP BY pv.product_id
         ORDER BY w DESC, MAX(pv.viewed_at) DESC
         LIMIT 15"
    );
    while ($row = $res->fetch_assoc()) {
        $interest[(int) $row['product_id']] = min(3, (int) $row['w']);
    }

    $res = $db->query(
        "SELECT product_id FROM bookmarks WHERE user_id = $userId ORDER BY created_at DESC LIMIT 15"
    );
    while ($row = $res->fetch_assoc()) {
        $pid = (int) $row['product_id'];
        $interest[$pid] = ($interest[$pid] ?? 0) + 4;
    }

    $res = $db->query(
        "SELECT product_id FROM orders WHERE buyer_id = $userId ORDER BY created_at DESC LIMIT 15"
    );
    while ($row = $res->fetch_assoc()) {
        $pid = (int) $row['product_id'];
        $interest[$pid] = ($interest[$pid] ?? 0) + 5;
    }

    return $interest;
}

/**
 * Build the user's interest vector. Returns [vector, hadSignal].
 */
function ai_user_interest_vector($userId) {
    global $db;
    $interest = ai_user_interest_ids($userId);
    $vectors = [];
    $weights = [];

    foreach ($interest as $productId => $weight) {
        $row = $db->query(
            "SELECT embedding FROM product_embeddings WHERE product_id = " . (int) $productId . " LIMIT 1"
        )->fetch_assoc();
        if ($row) {
            $vec = json_decode((string) $row['embedding'], true);
            if (is_array($vec)) {
                $vectors[] = $vec;
                $weights[] = max(1, $weight);
            }
        }
    }

    // Include recent searches as text queries (embed on the fly, cached).
    $res = $db->query(
        "SELECT search_query FROM search_history
         WHERE user_id = $userId AND search_query <> ''
         GROUP BY search_query ORDER BY MAX(created_at) DESC LIMIT 5"
    );
    while ($row = $res->fetch_assoc()) {
        $vec = ai_embed((string) $row['search_query']);
        if (is_array($vec)) {
            $vectors[] = $vec;
            $weights[] = 2;
        }
    }

    if (empty($vectors)) {
        return [null, false];
    }

    $dim = count($vectors[0]);
    $sum = array_fill(0, $dim, 0.0);
    foreach ($vectors as $i => $vec) {
        $w = $weights[$i];
        for ($j = 0; $j < $dim; $j++) {
            $sum[$j] += (float) $vec[$j] * $w;
        }
    }
    $norm = 0.0;
    foreach ($sum as $v) {
        $norm += $v * $v;
    }
    $norm = sqrt($norm);
    if ($norm == 0.0) {
        return [null, true];
    }
    foreach ($sum as &$v) {
        $v /= $norm;
    }
    return [$sum, true];
}

/**
 * Return ordered product ids recommended for $userId. Falls back to popular.
 */
function ai_recommend_products($userId, $opts = []) {
    global $db;
    $userId = (int) $userId;
    $limit = (int) ($opts['limit'] ?? 8);
    $universityId = isset($opts['universityId']) ? (int) $opts['universityId'] : null;

    $popular = function () use ($db, $userId, $limit, $universityId) {
        $univ = $universityId ? "AND p.university_id = $universityId" : "";
        $res = $db->query(
            "SELECT p.id
             FROM products p
             WHERE p.status = 'approved' AND p.availability = 'available'
               AND p.user_id <> $userId
               $univ
             ORDER BY p.is_featured DESC, p.views_count DESC, p.bookmarks_count DESC
             LIMIT $limit"
        );
        $ids = [];
        while ($row = $res->fetch_assoc()) {
            $ids[(int) $row['id']] = 0.0;
        }
        return $ids;
    };

    if ($userId < 1 || !AI_ENABLED || empty(AI_API_KEY)) {
        return $popular();
    }

    list($interestVector, $hadSignal) = ai_user_interest_vector($userId);
    if ($interestVector === null) {
        if (!$hadSignal) {
            return $popular();
        }
        return [];
    }

    // Products the user already interacted with or owns.
    $exclude = [$userId];
    $res = $db->query(
        "SELECT product_id FROM product_views WHERE user_id = $userId
         UNION SELECT product_id FROM bookmarks WHERE user_id = $userId
         UNION SELECT product_id FROM orders WHERE buyer_id = $userId"
    );
    while ($row = $res->fetch_assoc()) {
        $exclude[] = (int) $row['product_id'];
    }

    $where = "p.status = 'approved' AND p.availability = 'available' AND p.user_id <> $userId";
    if ($universityId) {
        $where .= " AND p.university_id = $universityId";
    }

    $sql = "SELECT pe.product_id, pe.embedding
            FROM product_embeddings pe
            JOIN products p ON p.id = pe.product_id
            WHERE $where LIMIT 500";
    $res = $db->query($sql);
    if (!$res) {
        return $popular();
    }

    $excludeMap = array_fill_keys($exclude, true);
    $scored = [];
    while ($row = $res->fetch_assoc()) {
        $pid = (int) $row['product_id'];
        if (isset($excludeMap[$pid])) {
            continue;
        }
        $vec = json_decode((string) $row['embedding'], true);
        if (!is_array($vec)) {
            continue;
        }
        $scored[] = ['id' => $pid, 'score' => ai_cosine_similarity($interestVector, $vec)];
    }
    usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

    if (empty($scored)) {
        return $popular();
    }

    $ids = [];
    foreach (array_slice($scored, 0, $limit) as $item) {
        $ids[$item['id']] = round($item['score'], 4);
    }
    return $ids;
}
