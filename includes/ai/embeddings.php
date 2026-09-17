<?php
// Text -> vector embeddings, cached by text hash.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/cache.php';
require_once __DIR__ . '/llm.php';

function ai_embed($text) {
    if (!AI_ENABLED || empty(AI_API_KEY)) {
        return null;
    }
    $text = trim((string) $text);
    if ($text === '') {
        return null;
    }
    $cacheKey = 'embed:' . AI_EMBEDDING_MODEL . ':' . hash('sha256', $text);
    $cached = ai_cache_get($cacheKey);
    if (is_array($cached)) {
        return $cached;
    }

    $res = ai_http_request(
        'embeddings',
        rtrim(AI_BASE_URL, '/') . '/embeddings',
        ['Content-Type: application/json', 'Authorization: Bearer ' . AI_API_KEY],
        json_encode(['model' => AI_EMBEDDING_MODEL, 'input' => $text]),
        AI_EMBEDDING_MODEL
    );
    if (!$res) {
        return null;
    }
    $vector = $res['body']['data'][0]['embedding'] ?? null;
    if (is_array($vector)) {
        ai_cache_set($cacheKey, $vector);
    }
    return $vector;
}

function ai_embed_batch($texts) {
    if (!AI_ENABLED || empty(AI_API_KEY)) {
        return null;
    }
    $texts = array_values(array_map('trim', (array) $texts));
    if (empty($texts)) {
        return [];
    }
    $res = ai_http_request(
        'embeddings',
        rtrim(AI_BASE_URL, '/') . '/embeddings',
        ['Content-Type: application/json', 'Authorization: Bearer ' . AI_API_KEY],
        json_encode(['model' => AI_EMBEDDING_MODEL, 'input' => $texts]),
        AI_EMBEDDING_MODEL
    );
    if (!$res) {
        return null;
    }
    $out = [];
    foreach (($res['body']['data'] ?? []) as $item) {
        $out[$item['index']] = $item['embedding'];
    }
    return $out;
}
