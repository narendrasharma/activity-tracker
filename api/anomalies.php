<?php

require_once __DIR__ . '/../config/bootstrap.php';

applyRateLimit();
applyAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ApiResponse::error('Method not allowed. Use GET.', 405);
}

try {
    $logger    = ActivityLogger::getInstance();
    $anomalies = $logger->detectAnomalies();

    $summary = [
        'high_frequency_count' => count($anomalies['high_frequency']),
        'multi_ip_count'       => count($anomalies['multi_ip']),
        'rules' => [
            'high_frequency' => 'More than ' . ANOMALY_MAX_ACTIONS_PER_MINUTE . ' actions within 1 minute by same user',
            'multi_ip'       => 'Same user from multiple IPs within ' . (ANOMALY_MULTI_IP_WINDOW_SECONDS / 60) . ' minutes',
        ],
    ];

    ApiResponse::success(
        array_merge($anomalies, ['summary' => $summary]),
        'Anomaly detection complete'
    );
} catch (RuntimeException $e) {
    ApiResponse::error('Anomaly detection failed: ' . $e->getMessage(), 500);
}
