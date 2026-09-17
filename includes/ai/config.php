<?php
// =====================================================
// CampMart AI configuration
// -----------------------------------------------------
// Set your API key here, or (better) via environment
// variables so it never lands in git.
//
// Works with any OpenAI-compatible endpoint, e.g.:
//   OpenAI:     https://api.openai.com/v1
//   Google:     https://generativelanguage.googleapis.com/v1beta/openai
//   DeepSeek:   https://api.deepseek.com
//
// Recommended environment variables:
//   AI_ENABLED=true
//   AI_PROVIDER=openai
//   AI_API_KEY=sk-...
//   AI_BASE_URL=https://api.openai.com/v1
//
// Settings are read from a .env file in the project root first, then from
// real environment variables (which always win). See .env.example.
// =====================================================

require_once __DIR__ . '/dotenv.php';

define('AI_ENABLED', filter_var(getenv('AI_ENABLED') ?: 'false', FILTER_VALIDATE_BOOLEAN));
define('AI_PROVIDER', getenv('AI_PROVIDER') ?: 'openai');
define('AI_API_KEY', getenv('AI_API_KEY') ?: '');
define('AI_BASE_URL', getenv('AI_BASE_URL') ?: 'https://api.openai.com/v1');
define('AI_LLM_MODEL', getenv('AI_LLM_MODEL') ?: 'gpt-4o-mini');
define('AI_EMBEDDING_MODEL', getenv('AI_EMBEDDING_MODEL') ?: 'text-embedding-3-small');
define('AI_EMBEDDING_DIMENSIONS', (int) (getenv('AI_EMBEDDING_DIMENSIONS') ?: 1536));

define('AI_CACHE_DIR', __DIR__ . '/cache');
define('AI_CACHE_TTL', 86400 * 30);      // 30 days
define('AI_REQUEST_TIMEOUT', 30);
define('AI_LOG_REQUESTS', true);
