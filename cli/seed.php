#!/usr/bin/env php
<?php

/**
 * Seed script — generates dummy activity logs for testing.
 * Usage:  php cli/seed.php [--count=10000] [--users=50]
 */

// Must be run from CLI
if (PHP_SAPI !== 'cli') {
    echo "This script must be run from the command line.\n";
    exit(1);
}

define('ROOT_DIR', dirname(__DIR__));
require_once ROOT_DIR . '/config/database.php';
require_once ROOT_DIR . '/config/app.php';
require_once ROOT_DIR . '/classes/Database.php';
require_once ROOT_DIR . '/classes/ActivityLogger.php';

// ── Parse CLI arguments ────────────────────────────────────────────────────
$opts  = getopt('', ['count:', 'users:', 'anomalies']);
$count = (int)($opts['count'] ?? 10000);
$users = (int)($opts['users'] ?? 50);
$withAnomalies = isset($opts['anomalies']);

echo "🌱 Seeding {$count} activity logs across {$users} users…\n";
if ($withAnomalies) {
    echo "   (with anomaly patterns)\n";
}

// ── Sample data ────────────────────────────────────────────────────────────
$actions = [
    'login', 'logout', 'view_page', 'search', 'purchase',
    'add_to_cart', 'remove_from_cart', 'update_profile',
    'change_password', 'view_product', 'submit_form',
    'download_file', 'upload_file', 'delete_item',
    'share_post', 'like_post', 'comment_post', 'follow_user',
];

$pages = ['/home', '/shop', '/cart', '/profile', '/settings', '/about', '/contact'];
$ips   = array_map(fn($i) => "192.168.1.{$i}", range(1, 30));
$uas   = [
    'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
    'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15',
    'Mozilla/5.0 (Linux; Android 12) AppleWebKit/537.36',
    'Mozilla/5.0 (iPhone; CPU iPhone OS 16_0) AppleWebKit/605.1.15',
];

// ── Batch insert ────────────────────────────────────────────────────────────
$db         = Database::getInstance()->getConnection();
$batchSize  = 500;
$inserted   = 0;
$startTime  = microtime(true);
$now        = time();
$thirtyDays = 30 * 86400;

$baseInsert = 'INSERT INTO activity_logs (user_id, action, metadata, ip_address, user_agent, created_at) VALUES ';
$valueSets  = [];
$stmtParams = [];

for ($i = 0; $i < $count; $i++) {
    $userId    = rand(1, $users);
    $action    = $actions[array_rand($actions)];
    $ip        = $ips[array_rand($ips)];
    $ua        = $uas[array_rand($uas)];
    $ts        = date('Y-m-d H:i:s', $now - rand(0, $thirtyDays));
    $metadata  = json_encode([
        'page'       => $pages[array_rand($pages)],
        'session_id' => bin2hex(random_bytes(8)),
        'ref'        => rand(0, 1) ? 'direct' : 'search',
    ]);

    $valueSets[] = '(' . implode(',', [
        (int)$userId,
        "'" . $db->real_escape_string($action)   . "'",
        "'" . $db->real_escape_string($metadata)  . "'",
        "'" . $db->real_escape_string($ip)         . "'",
        "'" . $db->real_escape_string($ua)         . "'",
        "'" . $ts                                  . "'",
    ]) . ')';

    if (count($valueSets) >= $batchSize) {
        $db->query($baseInsert . implode(',', $valueSets));
        $inserted  += $db->affected_rows;
        $valueSets  = [];
        echo "\r   Inserted {$inserted}/{$count}…";
    }
}

// Flush remainder
if ($valueSets) {
    $db->query($baseInsert . implode(',', $valueSets));
    $inserted += $db->affected_rows;
}

echo "\r   Inserted {$inserted}/{$count}…\n";

// ── Optionally inject anomaly patterns ─────────────────────────────────────
if ($withAnomalies) {
    echo "   Injecting anomaly patterns…\n";

    // High-frequency burst: user 1 does 15 actions in under 1 minute
    $burstTs = date('Y-m-d H:i:s', $now - 30);
    $burstIp = '10.0.0.1';
    for ($i = 0; $i < 15; $i++) {
        $ts = date('Y-m-d H:i:s', $now - 30 + $i * 3);
        $db->query("INSERT INTO activity_logs (user_id, action, metadata, ip_address, user_agent, created_at)
                    VALUES (1, 'burst_action', '{}', '{$burstIp}', 'BurstAgent/1.0', '{$ts}')");
    }

    // Multi-IP access: user 2 from 4 IPs in 5 minutes
    $multiIps = ['10.0.1.1', '10.0.1.2', '10.0.1.3', '10.0.1.4'];
    foreach ($multiIps as $idx => $ip) {
        $ts = date('Y-m-d H:i:s', $now - 240 + $idx * 60);
        $db->query("INSERT INTO activity_logs (user_id, action, metadata, ip_address, user_agent, created_at)
                    VALUES (2, 'multi_ip_access', '{}', '{$ip}', 'MultiAgent/1.0', '{$ts}')");
    }

    echo "   Anomaly patterns injected.\n";
}

$elapsed = round(microtime(true) - $startTime, 2);
echo "\n✅ Done! {$inserted} records inserted in {$elapsed}s.\n";
