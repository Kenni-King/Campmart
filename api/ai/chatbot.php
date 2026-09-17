<?php
// POST /api/ai/chatbot.php
// Body: { "messages": [ {"role":"user","content":"..."}, ... ] }
// Returns the assistant's reply.
header('Content-Type: application/json; charset=utf-8');
require_once '../../includes/constant.php';
require_once '../../includes/ai/chatbot.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$messages = is_array($data['messages'] ?? null) ? $data['messages'] : [];

$reply = ai_support_chat($messages);

if ($reply === null) {
    http_response_code(503);
    echo json_encode([
        'success' => false,
        'message' => 'AI is not available right now. Please try again later or create a support ticket.',
    ]);
    exit;
}

echo json_encode(['success' => true, 'reply' => $reply]);
