<?php
// GET /api/ai/smart-replies.php?conversation_id=1&limit=3
// Returns LLM-generated reply suggestions for the current user.
header('Content-Type: application/json; charset=utf-8');
require_once '../../includes/constant.php';
require_once '../../includes/ai/replies.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$userId = (int) ($_SESSION['userAppId'] ?? 0);
if ($userId < 1) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Login required']);
    exit;
}

$conversationId = (int) ($_GET['conversation_id'] ?? 0);
$limit = min(5, max(1, (int) ($_GET['limit'] ?? 3)));

if ($conversationId < 1) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'conversation_id is required']);
    exit;
}

$replies = ai_smart_replies($conversationId, $userId, $limit);

echo json_encode([
    'success' => true,
    'ai_enabled' => defined('AI_ENABLED') && AI_ENABLED && !empty(AI_API_KEY),
    'conversation_id' => $conversationId,
    'replies' => $replies,
]);
