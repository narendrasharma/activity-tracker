<?php

// Rate limiting
define('RATE_LIMIT_MAX_REQUESTS', 100);
define('RATE_LIMIT_WINDOW_SECONDS', 3600); // 1 hour
define('RATE_LIMIT_STORAGE', __DIR__ . '/../cache/rate_limits/');

// Cache
define('CACHE_DIR', __DIR__ . '/../cache/');
define('TOP_USERS_CACHE_TTL', 120); // 2 minutes

// Anomaly detection
define('ANOMALY_MAX_ACTIONS_PER_MINUTE', 10);
define('ANOMALY_MULTI_IP_WINDOW_SECONDS', 300); // 5 minutes

// Pagination defaults
define('DEFAULT_LIMIT', 50);
define('MAX_LIMIT', 500);

// API Auth (optional)
define('API_KEY', getenv('API_KEY') ?: 'dev-secret-key-change-in-production');
define('API_AUTH_ENABLED', false); // Set true to enable
