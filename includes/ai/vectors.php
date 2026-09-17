<?php
// Vector storage + cosine similarity (semantic search foundation).

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/embeddings.php';

function ai_cosine_similarity($a, $b) {
    if (!is_array($a) || !is_array($b) || count($a) !== count($b) || empty($a)) {
        return 0.0;
    }
    $dot = 0.0;
    $normA = 0.0;
    $normB = 0.0;
    foreach ($a as $i => $v) {
        $v = (float) $v;
        $w = (float) $b[$i];
        $dot += $v * $w;
        $normA += $v * $v;
        $normB += $w * $w;
    }
    if ($normA == 0.0 || $normB == 0.0) {
        return 0.0;
    }
    return $dot / (sqrt($normA) * sqrt($normB));
}

function ai_build_product_text($product) {
    $parts = [];
    if (!empty($product['title'])) {
        $parts[] = $product['title'];
    }
    if (!empty($product['category_name'])) {
        $parts[] = $product['category_name'];
    }
    if (!empty($product['description'])) {
        $parts[] = $product['description'];
    }
    if (!empty($product['tags'])) {
        $tags = is_string($product['tags']) ? json_decode($product['tags'], true) : $product['tags'];
        if (is_array($tags)) {
            $parts[] = implode(' ', $tags);
        }
    }
    return trim(implode("\n", $parts));
}

function ai_upsert_product_embedding($productId, $vector, $model) {
    global $db;
    $productId = (int) $productId;
    $embedding = json_encode($vector, JSON_UNESCAPED_SLASHES);
    $stmt = $db->prepare(
        "INSERT INTO product_embeddings (product_id, model, embedding, text_hash)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE embedding = VALUES(embedding), text_hash = VALUES(text_hash)"
    );
    $textHash = hash('sha256', $embedding);
    $stmt->bind_param('isss', $productId, $model, $embedding, $textHash);
    return $stmt->execute();
}

/**
 * Generic embedding upsert for any *_embeddings table.
 * $idColumn is the FK column pointing back to the entity table.
 */
function ai_upsert_embedding($table, $idColumn, $entityId, $vector, $model) {
    global $db;
    $table = preg_replace('/[^a-z_]/', '', $table);
    $idColumn = preg_replace('/[^a-z_]/', '', $idColumn);
    $entityId = (int) $entityId;
    $embedding = json_encode($vector, JSON_UNESCAPED_SLASHES);
    $stmt = $db->prepare(
        "INSERT INTO `$table` (`$idColumn`, model, embedding, text_hash)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE embedding = VALUES(embedding), text_hash = VALUES(text_hash)"
    );
    $textHash = hash('sha256', $embedding);
    $stmt->bind_param('isss', $entityId, $model, $embedding, $textHash);
    return $stmt->execute();
}

/**
 * Score every row in an embeddings table against $vector, newest first, and
 * return a ranked array of [id, score]. Only rows matching $whereSql are kept.
 */
function ai_rank_by_vector($table, $idColumn, $whereSql, $vector, $limit = 500) {
    global $db;
    $table = preg_replace('/[^a-z_]/', '', $table);
    $idColumn = preg_replace('/[^a-z_]/', '', $idColumn);
    $sql = "SELECT `$idColumn`, embedding FROM `$table`";
    if ($whereSql !== '') {
        $sql .= " WHERE " . $whereSql;
    }
    $sql .= " LIMIT 500";
    $res = $db->query($sql);
    if (!$res) {
        return [];
    }
    $scored = [];
    while ($row = $res->fetch_assoc()) {
        $vec = json_decode((string) $row['embedding'], true);
        if (!is_array($vec)) {
            continue;
        }
        $scored[] = [
            'id' => (int) $row[$idColumn],
            'score' => ai_cosine_similarity($vector, $vec),
        ];
    }
    usort($scored, fn($x, $y) => $y['score'] <=> $x['score']);
    return array_slice($scored, 0, (int) $limit);
}

/**
 * Keyword match set (approximate) for hybrid scoring. Returns id => 1 map.
 */
function ai_keyword_hit_ids($table, $idColumn, $columns, $query) {
    global $db;
    $table = preg_replace('/[^a-z_]/', '', $table);
    $idColumn = preg_replace('/[^a-z_]/', '', $idColumn);
    $esc = $db->real_escape_string($query);
    $conds = [];
    foreach ($columns as $col) {
        $col = preg_replace('/[^a-z_]/', '', $col);
        $conds[] = "`$col` LIKE '%$esc%'";
    }
    $res = $db->query("SELECT `$idColumn` FROM `$table` WHERE " . implode(' OR ', $conds));
    $ids = [];
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $ids[(int) $row[$idColumn]] = 1;
        }
    }
    return $ids;
}

function ai_delete_product_embedding($productId) {
    global $db;
    $db->query("DELETE FROM product_embeddings WHERE product_id = " . (int) $productId);
}

/**
 * Embed one product and store it. Returns true on success.
 * Call this when a product is created / edited, or backfill via ai-reindex.php.
 */
function ai_index_product($productId) {
    global $db;
    $productId = (int) $productId;
    $res = $db->query(
        "SELECT p.*, c.name AS category_name
         FROM products p
         LEFT JOIN categories c ON p.category_id = c.id
         WHERE p.id = $productId LIMIT 1"
    );
    if (!$res || $res->num_rows === 0) {
        return false;
    }
    $product = $res->fetch_assoc();
    $text = ai_build_product_text($product);
    $vector = ai_embed($text);
    if ($vector === null) {
        return false;
    }
    return ai_upsert_product_embedding($productId, $vector, AI_EMBEDDING_MODEL);
}

/**
 * Semantic (meaning-based) search over products.
 * Returns [ ['product_id' => int, 'score' => float], ... ] ordered best first.
 */
function ai_semantic_search($query, $limit = 20, $universityId = null) {
    global $db;
    $query = trim((string) $query);
    if ($query === '') {
        return [];
    }
    $vector = ai_embed($query);
    if ($vector === null) {
        return [];
    }

    $sql = "SELECT pe.product_id, pe.embedding, p.university_id
            FROM product_embeddings pe
            JOIN products p ON p.id = pe.product_id
            WHERE p.status = 'approved' AND p.availability = 'available'";
    if ($universityId) {
        $sql .= " AND p.university_id = " . (int) $universityId;
    }
    $sql .= " LIMIT 500";

    $res = $db->query($sql);
    if (!$res) {
        return [];
    }
    $scored = [];
    while ($row = $res->fetch_assoc()) {
        $vec = json_decode((string) $row['embedding'], true);
        if (!is_array($vec)) {
            continue;
        }
        $scored[] = [
            'product_id' => (int) $row['product_id'],
            'score' => ai_cosine_similarity($vector, $vec),
        ];
    }
    usort($scored, fn($x, $y) => $y['score'] <=> $x['score']);
    return array_slice($scored, 0, $limit);
}
