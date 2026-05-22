<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/ActivityLogger.php';
require_once __DIR__ . '/../classes/RateLimiter.php';
require_once __DIR__ . '/../classes/Cache.php';
require_once __DIR__ . '/../classes/ApiResponse.php';

// Create required directories
$dirs = [CACHE_DIR . 'data/', CACHE_DIR . 'rate_limits/'];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// CORS headers (adjust origins in production)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

/**
 * Apply rate limiting. Terminates with 429 if exceeded.
 */
function applyRateLimit(): void
{
    $limiter = new RateLimiter();
    $ip      = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $result  = $limiter->check($ip);

    header('X-RateLimit-Limit: ' . $result['limit']);
    header('X-RateLimit-Remaining: ' . $result['remaining']);
    header('X-RateLimit-Reset: ' . $result['reset_at']);

    if (!$result['allowed']) {
        ApiResponse::rateLimitExceeded($result['reset_at'], $result['limit']);
    }
}

/**
 * Optional API key authentication.
 */
function applyAuth(): void
{
    if (!API_AUTH_ENABLED) {
        return;
    }

    $key = $_SERVER['HTTP_X_API_KEY'] ?? '';

    if ($key !== API_KEY) {
        ApiResponse::error('Unauthorized. Provide a valid X-API-Key header.', 401);
    }
}

/**
 * Get and validate an integer query param.
 */
function intParam(string $key, int $default, int $min = 0, int $max = PHP_INT_MAX): int
{
    $val = isset($_GET[$key]) ? (int)$_GET[$key] : $default;
    return max($min, min($max, $val));
}

/**
 * Get a sanitized string query param.
 */
function strParam(string $key, string $default = ''): string
{
    return isset($_GET[$key]) ? trim(strip_tags($_GET[$key])) : $default;
}
