<?php
// GET /api/ai/lostfound-match.php?item_id=5&limit=6
// Returns semantically matched lost <-> found items for one item.
header('Content-Type: application/json; charset=utf-8');
require_once '../../includes/constant.php';
require_once '../../includes/ai/lostfound.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$itemId = (int) ($_GET['item_id'] ?? 0);
$limit = min(12, max(1, (int) ($_GET['limit'] ?? 6)));

if ($itemId < 1) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'item_id is required']);
    exit;
}

$ranked = ai_match_lost_found($itemId, ['limit' => $limit]);

$matches = [];
if (!empty($ranked)) {
    $ids = implode(',', array_map('intval', array_keys($ranked)));
    $r = $db->query(
        "SELECT lf.*, u.username AS reporter_name, u.profile_image
         FROM lost_found_items lf
         JOIN users u ON lf.user_id = u.id
         WHERE lf.id IN ($ids)"
    );
    $byId = [];
    while ($row = $r->fetch_assoc()) {
        $byId[(int) $row['id']] = $row;
    }
    foreach ($ranked as $id => $score) {
        if (!isset($byId[$id])) {
            continue;
        }
        $m = $byId[$id];
        $matches[] = [
            'id' => (int) $m['id'],
            'type' => $m['type'],
            'title' => $m['title'],
            'description' => $m['description'],
            'category' => $m['category'],
            'location_lost_found' => $m['location_lost_found'],
            'date_lost_found' => $m['date_lost_found'],
            'contact_info' => $m['contact_info'],
            'image_url' => $m['image_url'],
            'reporter_name' => $m['reporter_name'],
            'profile_image' => $m['profile_image'],
            'score' => (float) $score,
        ];
    }
}

echo json_encode([
    'success' => true,
    'ai_enabled' => defined('AI_ENABLED') && AI_ENABLED && !empty(AI_API_KEY),
    'item_id' => $itemId,
    'matches' => $matches,
]);
