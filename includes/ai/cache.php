<?php
// File-based cache so expensive AI calls (embeddings, chat)
// are stored on disk instead of hitting the API every time.

require_once __DIR__ . '/config.php';

function ai_cache_dir() {
    if (!is_dir(AI_CACHE_DIR)) {
        @mkdir(AI_CACHE_DIR, 0775, true);
    }
    return AI_CACHE_DIR;
}

function ai_cache_key($key) {
    return ai_cache_dir() . '/' . md5($key) . '.json';
}

function ai_cache_get($key) {
    $file = ai_cache_key($key);
    if (!is_file($file)) {
        return null;
    }
    if ((time() - filemtime($file)) > AI_CACHE_TTL) {
        @unlink($file);
        return null;
    }
    $data = json_decode((string) file_get_contents($file), true);
    return is_array($data) && array_key_exists('v', $data) ? $data['v'] : null;
}

function ai_cache_set($key, $value) {
    file_put_contents(ai_cache_key($key), json_encode(['v' => $value], JSON_UNESCAPED_SLASHES), LOCK_EX);
}

function ai_cache_delete($key) {
    $file = ai_cache_key($key);
    if (is_file($file)) {
        @unlink($file);
    }
}
