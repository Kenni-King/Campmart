<?php
// Log a search into search_history (feeds suggestions + recommendations).
header('Content-Type: application/json; charset=utf-8');
require_once '../includes/constant.php';
require_once '../includes/ai/search-logger.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$query = trim((string) ($data['q'] ?? ''));
$resultsCount = (int) ($data['results_count'] ?? 0);
$filters = is_array($data['filters'] ?? null) ? $data['filters'] : null;

if (strlen($query) > 255) {
    $query = substr($query, 0, 255);
}

if ($query === '') {
    echo json_encode(['success' => false, 'message' => 'Empty query']);
    exit;
}

$ok = ai_log_search($query, $resultsCount, $filters);
echo json_encode(['success' => (bool) $ok]);
