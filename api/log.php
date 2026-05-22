<?php

require_once __DIR__ . '/../config/bootstrap.php';

applyRateLimit();
applyAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ApiResponse::error('Method not allowed. Use POST.', 405);
}

// Accept JSON or form-data
$input = [];
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';

if (str_contains($contentType, 'application/json')) {
    $raw   = file_get_contents('php://input');
    $input = json_decode($raw, true) ?? [];
} else {
    $input = $_POST;
}

// Validate required fields
$errors = [];

if (empty($input['user_id']) || !is_numeric($input['user_id'])) {
    $errors[] = 'user_id is required and must be a numeric value.';
}

if (empty($input['action']) || !is_string($input['action'])) {
    $errors[] = 'action is required and must be a string.';
}

if (strlen($input['action'] ?? '') > 100) {
    $errors[] = 'action must not exceed 100 characters.';
}

if (!empty($errors)) {
    ApiResponse::error('Validation failed.', 422, $errors);
}

$userId   = (int)$input['user_id'];
$action   = trim($input['action']);
$metadata = [];

if (isset($input['metadata'])) {
    if (is_array($input['metadata'])) {
        $metadata = $input['metadata'];
    } elseif (is_string($input['metadata'])) {
        $decoded = json_decode($input['metadata'], true);
        $metadata = is_array($decoded) ? $decoded : ['raw' => $input['metadata']];
    }
}

// Optional overrides (useful for server-side calls)
$ip = isset($input['ip_address']) && filter_var($input['ip_address'], FILTER_VALIDATE_IP)
    ? $input['ip_address']
    : null;

$ua = isset($input['user_agent']) ? substr($input['user_agent'], 0, 512) : null;

try {
    $logId = ActivityLogger::log($userId, $action, $metadata, $ip, $ua);

    ApiResponse::success(
        ['log_id' => $logId],
        'Activity logged successfully.',
        201
    );
} catch (RuntimeException $e) {
    ApiResponse::error('Failed to log activity: ' . $e->getMessage(), 500);
}
