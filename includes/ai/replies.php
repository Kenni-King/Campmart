<?php
// Smart reply: generates short, in-context reply suggestions for a chat
// conversation using the LLM. Grounded in the recent messages and the
// product/service the conversation is about.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/llm.php';

function ai_smart_replies($conversationId, $userId, $limit = 3) {
    global $db;
    $conversationId = (int) $conversationId;
    $userId = (int) $userId;
    $limit = max(1, min(5, (int) $limit));

    if (!AI_ENABLED || empty(AI_API_KEY)) {
        return [];
    }

    $conv = $db->query(
        "SELECT * FROM conversations WHERE id = $conversationId AND (user1_id = $userId OR user2_id = $userId)"
    )->fetch_assoc();
    if (!$conv) {
        return [];
    }

    $otherId = $conv['user1_id'] == $userId ? $conv['user2_id'] : $conv['user1_id'];
    $myName = $db->query("SELECT full_name FROM users WHERE id = $userId")->fetch_assoc()['full_name'] ?? '';

    // Recent messages (most recent last, so the last one is "theirs").
    $rows = $db->query(
        "SELECT sender_id, message FROM messages
         WHERE conversation_id = $conversationId
         ORDER BY created_at DESC LIMIT 8"
    );
    $recent = [];
    if ($rows) {
        while ($row = $rows->fetch_assoc()) {
            $recent[] = $row;
        }
        $recent = array_reverse($recent);
    }

    // Context of what is being discussed.
    $context = '';
    if (!empty($conv['product_id'])) {
        $p = $db->query("SELECT title, price FROM products WHERE id = " . (int) $conv['product_id'])->fetch_assoc();
        if ($p) {
            $context = "Product: {$p['title']} (₦" . number_format((float) $p['price']) . ").";
        }
    } elseif (!empty($conv['service_id'])) {
        $s = $db->query("SELECT title FROM services WHERE id = " . (int) $conv['service_id'])->fetch_assoc();
        if ($s) {
            $context = "Service: {$s['title']}.";
        }
    }

    $dialogue = '';
    foreach ($recent as $m) {
        $who = ((int) $m['sender_id'] === $userId) ? 'me' : 'them';
        $dialogue .= $who . ": " . trim((string) $m['message']) . "\n";
    }

    $system = "You are a smart-reply assistant for CampMart, a Nigerian campus marketplace. " .
        "You write short, friendly, natural replies in casual English on behalf of the user. " .
        "Keep each reply under 20 words. Never use markdown, quotes, numbering or bullets. " .
        "Return exactly {$limit} replies, one per line, nothing else.";

    $userPrompt = "Context: $context\n" .
        "You are replying as: {$myName}\n" .
        "Recent chat:\n{$dialogue}\n" .
        "Write {$limit} possible replies I could send next.";

    $content = ai_chat(
        [
            ['role' => 'system', 'content' => $system],
            ['role' => 'user', 'content' => $userPrompt],
        ],
        ['temperature' => 0.8, 'max_tokens' => 120, 'cache' => null]
    );

    if ($content === null) {
        return [];
    }

    $lines = preg_split('/\r\n|\r|\n/', trim((string) $content));
    $replies = [];
    foreach ($lines as $line) {
        $line = trim($line, " \t-•*\"'");
        if ($line === '') {
            continue;
        }
        $replies[] = $line;
        if (count($replies) >= $limit) {
            break;
        }
    }
    return $replies;
}
