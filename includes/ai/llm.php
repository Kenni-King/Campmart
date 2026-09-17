<?php
// OpenAI-compatible HTTP client + audit logging.

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/cache.php';

function ai_log_request($endpoint, $provider, $model, $status, $promptTokens, $completionTokens, $latencyMs) {
    if (!defined('AI_LOG_REQUESTS') || !AI_LOG_REQUESTS) {
        return;
    }
    global $db;
    if (!isset($db) || !($db instanceof mysqli)) {
        return;
    }
    $e = $db->real_escape_string((string) $endpoint);
    $p = $db->real_escape_string((string) $provider);
    $m = $db->real_escape_string((string) $model);
    $db->query(
        "INSERT INTO ai_request_logs (provider, model, endpoint, status, prompt_tokens, completion_tokens, latency_ms)
         VALUES ('$p', '$m', '$e', '" . (int) $status . "', '" . (int) $promptTokens . "', '" . (int) $completionTokens . "', '" . (int) $latencyMs . "')"
    );
}

function ai_http_request($endpoint, $url, $headers, $body, $model = '') {
    if (!function_exists('curl_init')) {
        return null;
    }
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, AI_REQUEST_TIMEOUT);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $start = microtime(true);
    $response = curl_exec($ch);
    $latency = (int) ((microtime(true) - $start) * 1000);
    $err = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    ai_log_request($endpoint, AI_PROVIDER, $model, $status, 0, 0, $latency);

    if ($err || $response === false || $status !== 200) {
        return null;
    }
    return ['status' => $status, 'body' => json_decode($response, true)];
}

/**
 * Chat completion. Returns the assistant's text, or null on failure / disabled.
 * Pass an optional cache key (string) to reuse identical answers.
 */
function ai_chat($messages, $opts = []) {
    if (!AI_ENABLED || empty(AI_API_KEY)) {
        return null;
    }
    $model = $opts['model'] ?? AI_LLM_MODEL;
    $cacheKey = $opts['cache'] ?? null;
    if ($cacheKey !== null) {
        $cached = ai_cache_get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }
    }

    $payload = ['model' => $model, 'messages' => $messages];
    if (isset($opts['temperature'])) {
        $payload['temperature'] = (float) $opts['temperature'];
    }
    if (isset($opts['max_tokens'])) {
        $payload['max_tokens'] = (int) $opts['max_tokens'];
    }

    $res = ai_http_request(
        'chat/completions',
        rtrim(AI_BASE_URL, '/') . '/chat/completions',
        ['Content-Type: application/json', 'Authorization: Bearer ' . AI_API_KEY],
        json_encode($payload),
        $model
    );
    if (!$res) {
        return null;
    }
    $content = $res['body']['choices'][0]['message']['content'] ?? null;
    if ($content !== null && $cacheKey !== null) {
        ai_cache_set($cacheKey, $content);
    }
    return $content;
}
