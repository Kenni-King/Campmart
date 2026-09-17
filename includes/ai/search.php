<?php
// Hybrid semantic search: merges keyword matches with embedding (meaning-based)
// matches so results are ranked by relevance, with keyword hits always boosted.
//
// Every function degrades gracefully:
//   AI disabled / no key            -> keyword-only ranking
//   embeddings not built yet        -> keyword-only ranking
//   LLM/embedding API down          -> keyword-only ranking

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/vectors.php';
require_once __DIR__ . '/embeddings.php';

function ai_build_service_text($service) {
    $parts = [];
    foreach (['title', 'short_description', 'description'] as $col) {
        if (!empty($service[$col])) {
            $parts[] = $service[$col];
        }
    }
    if (!empty($service['skills'])) {
        $skills = is_string($service['skills']) ? json_decode($service['skills'], true) : $service['skills'];
        if (is_array($skills)) {
            $parts[] = implode(' ', $skills);
        }
    }
    return trim(implode("\n", $parts));
}

function ai_index_service($serviceId) {
    global $db;
    $serviceId = (int) $serviceId;
    $res = $db->query("SELECT * FROM services WHERE id = $serviceId LIMIT 1");
    if (!$res || $res->num_rows === 0) {
        return false;
    }
    $service = $res->fetch_assoc();
    $text = ai_build_service_text($service);
    $vector = ai_embed($text);
    if ($vector === null) {
        return false;
    }
    return ai_upsert_embedding('service_embeddings', 'service_id', $serviceId, $vector, AI_EMBEDDING_MODEL);
}

function ai_delete_service_embedding($serviceId) {
    global $db;
    $db->query("DELETE FROM service_embeddings WHERE service_id = " . (int) $serviceId);
}

function ai_index_product_safe($productId) {
    return ai_index_product((int) $productId);
}

/**
 * Combine semantic cosine scores with keyword hits.
 * Returns id => combined_score, sorted best first.
 */
function ai_hybrid_rank($semanticScores, $keywordIds, $keywordBoost = 0.35) {
    $combined = [];
    foreach ($semanticScores as $id => $score) {
        $combined[$id] = (float) $score;
    }
    foreach ($keywordIds as $id => $_) {
        if (isset($combined[$id])) {
            $combined[$id] += $keywordBoost;
        } else {
            $combined[$id] = $keywordBoost;
        }
    }
    arsort($combined);
    return $combined;
}

/**
 * AI search over products. Returns an ordered array of [product_id => score]
 * or null when AI is completely unavailable (caller keeps normal LIKE search).
 */
function ai_search_products($query, $opts = []) {
    if (!AI_ENABLED || empty(AI_API_KEY)) {
        return null;
    }
    $query = trim((string) $query);
    if ($query === '') {
        return [];
    }
    $limit = (int) ($opts['limit'] ?? 200);
    $universityId = $opts['universityId'] ?? null;
    $categoryId = (int) ($opts['categoryId'] ?? 0);

    $semantic = ai_semantic_search($query, 500, $universityId);
    $semanticScores = [];
    foreach ($semantic as $row) {
        $semanticScores[$row['product_id']] = $row['score'];
    }

    $keywordIds = ai_keyword_hit_ids('products', 'id', ['title', 'description'], $query);

    $ranked = ai_hybrid_rank($semanticScores, $keywordIds);

    if ($categoryId > 0) {
        global $db;
        $cat = (int) $categoryId;
        $ranked = array_filter($ranked, function ($id) use ($db, $cat) {
            $row = $db->query("SELECT category_id FROM products WHERE id = " . (int) $id)->fetch_assoc();
            return $row && (int) $row['category_id'] === $cat;
        }, ARRAY_FILTER_USE_KEY);
    }

    $ranked = array_slice($ranked, 0, $limit, true);
    return array_map(fn($s) => round($s, 4), $ranked);
}

/**
 * AI search over services. Returns ordered [service_id => score] or null.
 */
function ai_search_services($query, $opts = []) {
    if (!AI_ENABLED || empty(AI_API_KEY)) {
        return null;
    }
    $query = trim((string) $query);
    if ($query === '') {
        return [];
    }
    $limit = (int) ($opts['limit'] ?? 200);
    $universityId = $opts['universityId'] ?? null;
    $categoryId = (int) ($opts['categoryId'] ?? 0);

    global $db;
    $vector = ai_embed($query);
    $semanticScores = [];
    if ($vector !== null) {
        $where = "";
        if ($universityId) {
            $where = "s.university_id = " . (int) $universityId;
        }
        $sql = "SELECT se.service_id, se.embedding, s.university_id
                FROM service_embeddings se
                JOIN services s ON s.id = se.service_id
                WHERE s.status = 'active'";
        if ($where !== '') {
            $sql .= " AND " . $where;
        }
        $sql .= " LIMIT 500";
        $res = $db->query($sql);
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $vec = json_decode((string) $row['embedding'], true);
                if (is_array($vec)) {
                    $semanticScores[(int) $row['service_id']] = ai_cosine_similarity($vector, $vec);
                }
            }
            arsort($semanticScores);
        }
    }

    $keywordIds = ai_keyword_hit_ids('services', 'id', ['title', 'description', 'short_description'], $query);

    $ranked = ai_hybrid_rank($semanticScores, $keywordIds);

    if ($categoryId > 0) {
        $cat = (int) $categoryId;
        $ranked = array_filter($ranked, function ($id) use ($db, $cat) {
            $row = $db->query("SELECT service_category_id FROM services WHERE id = " . (int) $id)->fetch_assoc();
            return $row && (int) $row['service_category_id'] === $cat;
        }, ARRAY_FILTER_USE_KEY);
    }

    $ranked = array_slice($ranked, 0, $limit, true);
    return array_map(fn($s) => round($s, 4), $ranked);
}
