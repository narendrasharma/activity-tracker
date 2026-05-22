<?php

require_once __DIR__ . '/../config/bootstrap.php';

applyRateLimit();
applyAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ApiResponse::error('Method not allowed. Use GET.', 405);
}

$n = intParam('n', 5, 1, 50);

$cache    = new Cache();
$cacheKey = "top_users_{$n}";

$fromCache = false;
$data = $cache->remember($cacheKey, TOP_USERS_CACHE_TTL, function () use ($n, &$fromCache) {
    $logger    = ActivityLogger::getInstance();
    $fromCache = false;
    return $logger->getTopUsers($n);
});

// If data came from cache, $fromCache will still be false because
// the closure wasn't called; check by fetching directly
$cached = $cache->get($cacheKey);
$fromCache = ($cached !== null);

header('X-Cache: ' . ($fromCache ? 'HIT' : 'MISS'));
header('X-Cache-TTL: ' . TOP_USERS_CACHE_TTL);

ApiResponse::success(
    $data,
    "Top {$n} active users",
    200
);
