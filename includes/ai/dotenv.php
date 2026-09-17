<?php
// Minimal .env loader (no external deps). Looks for .env in the project root
// and loads KEY=VALUE lines into the environment (does NOT overwrite real env vars).

if (!function_exists('ai_load_dotenv')) {
    function ai_load_dotenv($path = null) {
        if ($path === null) {
            $path = dirname(__DIR__, 2) . '/.env';
        }
        if (!is_file($path)) {
            return;
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return;
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));
            if (preg_match('/^"(.*)"$/', $value, $m)) {
                $value = $m[1];
            } elseif (preg_match("/^'(.*)'$/", $value, $m)) {
                $value = $m[1];
            }
            // Real environment variables win over the file.
            if (getenv($key) === false) {
                putenv("$key=$value");
                $_ENV[$key] = $value;
            }
        }
    }
}

ai_load_dotenv();
