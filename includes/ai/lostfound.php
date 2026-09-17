<?php
// Lost & Found semantic matching.
//
// Each item (lost OR found) gets an embedding of its title/description/
// category/location. To match a "lost" item we compare against "found" items
// (and vice-versa) using cosine similarity, so a "lost black leather wallet
// near the cafeteria" links to a "found brown wallet in the library".

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/embeddings.php';
require_once __DIR__ . '/vectors.php';

function ai_build_lost_found_text($item) {
    $parts = [];
    foreach (['title', 'category', 'location_lost_found', 'description'] as $col) {
        if (!empty($item[$col])) {
            $parts[] = $item[$col];
        }
    }
    return trim(implode("\n", $parts));
}

function ai_index_lost_found($itemId) {
    global $db;
    $itemId = (int) $itemId;
    $res = $db->query("SELECT * FROM lost_found_items WHERE id = $itemId LIMIT 1");
    if (!$res || $res->num_rows === 0) {
        return false;
    }
    $item = $res->fetch_assoc();
    $text = ai_build_lost_found_text($item);
    $vector = ai_embed($text);
    if ($vector === null) {
        return false;
    }
    return ai_upsert_embedding('lost_found_embeddings', 'item_id', $itemId, $vector, AI_EMBEDDING_MODEL);
}

function ai_delete_lost_found_embedding($itemId) {
    global $db;
    $db->query("DELETE FROM lost_found_embeddings WHERE item_id = " . (int) $itemId);
}

/**
 * Semantic matches for a lost/found item. Returns an ordered array of
 * [item_id => score] of the OPPOSITE type. Degrades to keyword matching
 * when AI is disabled or the item has no embedding yet.
 */
function ai_match_lost_found($itemId, $opts = []) {
    global $db;
    $itemId = (int) $itemId;
    $limit = (int) ($opts['limit'] ?? 6);

    $res = $db->query("SELECT * FROM lost_found_items WHERE id = $itemId LIMIT 1");
    if (!$res || $res->num_rows === 0) {
        return [];
    }
    $item = $res->fetch_assoc();
    $targetType = $item['type'] === 'lost' ? 'found' : 'lost';

    // --- Semantic path ---
    if (AI_ENABLED && !empty(AI_API_KEY)) {
        $row = $db->query(
            "SELECT embedding FROM lost_found_embeddings WHERE item_id = $itemId LIMIT 1"
        )->fetch_assoc();
        if ($row) {
            $vector = json_decode((string) $row['embedding'], true);
            if (is_array($vector)) {
                $where = "lf.type = '$targetType' AND lf.status = 'open' AND lf.id <> $itemId";
                if (!empty($item['university_id'])) {
                    $where .= " AND lf.university_id = " . (int) $item['university_id'];
                }
                $sql = "SELECT lfe.item_id, lfe.embedding
                        FROM lost_found_embeddings lfe
                        JOIN lost_found_items lf ON lf.id = lfe.item_id
                        WHERE $where LIMIT 500";
                $r = $db->query($sql);
                $scored = [];
                if ($r) {
                    while ($row = $r->fetch_assoc()) {
                        $vec = json_decode((string) $row['embedding'], true);
                        if (is_array($vec)) {
                            $scored[] = [
                                'id' => (int) $row['item_id'],
                                'score' => ai_cosine_similarity($vector, $vec),
                            ];
                        }
                    }
                    usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
                    $out = [];
                    foreach (array_slice($scored, 0, $limit) as $m) {
                        if ($m['score'] > 0.25) { // ignore noise
                            $out[$m['id']] = round($m['score'], 4);
                        }
                    }
                    if ($out) {
                        return $out;
                    }
                }
            }
        }
    }

    // --- Keyword fallback ---
    $terms = [];
    foreach ([$item['title'], $item['category'], $item['description']] as $t) {
        foreach (preg_split('/\s+/', trim((string) $t)) as $word) {
            $word = preg_replace('/[^\p{L}\p{N}]+/u', '', $word);
            if (mb_strlen($word) >= 3) {
                $terms[] = $word;
            }
        }
    }
    $terms = array_slice(array_unique($terms), 0, 6);
    if (empty($terms)) {
        return [];
    }
    $like = [];
    foreach ($terms as $term) {
        $esc = $db->real_escape_string($term);
        $like[] = "(lf.title LIKE '%$esc%' OR lf.description LIKE '%$esc%' OR lf.category LIKE '%$esc%')";
    }
    $where = "lf.type = '$targetType' AND lf.status = 'open' AND lf.id <> $itemId AND (" . implode(' OR ', $like) . ")";
    if (!empty($item['university_id'])) {
        $where .= " AND lf.university_id = " . (int) $item['university_id'];
    }
    $r = $db->query("SELECT lf.id FROM lost_found_items lf WHERE $where ORDER BY lf.created_at DESC LIMIT $limit");
    $out = [];
    if ($r) {
        while ($row = $r->fetch_assoc()) {
            $out[(int) $row['id']] = 0.0;
        }
    }
    return $out;
}
