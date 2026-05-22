<?php

require_once __DIR__ . '/../config/bootstrap.php';

applyRateLimit();
applyAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    ApiResponse::error('Method not allowed. Use GET.', 405);
}

// Check if CSV export requested
$exportCsv = isset($_GET['export']) && $_GET['export'] === 'csv';

// Build filters from query string
$filters = [];

$userId = strParam('user_id');
if ($userId !== '' && is_numeric($userId)) {
    $filters['user_id'] = (int)$userId;
}

$action = strParam('action');
if ($action !== '') {
    $filters['action'] = $action;
}

// Validate and normalize date params
$dateFrom = strParam('date_from');
if ($dateFrom !== '') {
    $dt = DateTime::createFromFormat('Y-m-d', $dateFrom)
       ?: DateTime::createFromFormat('Y-m-d H:i:s', $dateFrom);

    if ($dt) {
        $filters['date_from'] = $dt->format('Y-m-d H:i:s');
    }
}

$dateTo = strParam('date_to');
if ($dateTo !== '') {
    $dt = DateTime::createFromFormat('Y-m-d', $dateTo)
       ?: DateTime::createFromFormat('Y-m-d H:i:s', $dateTo);

    if ($dt) {
        // Include the full day if only a date was provided
        if (strlen($dateTo) === 10) {
            $dt->setTime(23, 59, 59);
        }
        $filters['date_to'] = $dt->format('Y-m-d H:i:s');
    }
}

$ipAddress = strParam('ip_address');
if ($ipAddress !== '' && filter_var($ipAddress, FILTER_VALIDATE_IP)) {
    $filters['ip_address'] = $ipAddress;
}

// Pagination & sorting
$limit   = intParam('limit', DEFAULT_LIMIT, 1, MAX_LIMIT);
$offset  = intParam('offset', 0, 0);
$sortDir = in_array(strtoupper(strParam('sort', 'DESC')), ['ASC', 'DESC'])
    ? strtoupper(strParam('sort', 'DESC'))
    : 'DESC';

try {
    $logger = ActivityLogger::getInstance();
    $result = $logger->fetchLogs($filters, $limit, $offset, $sortDir);

    if ($exportCsv) {
        exportAsCsv($result['data'], $filters);
        exit;
    }

    ApiResponse::paginated(
        $result['data'],
        $result['pagination'],
        ['filters_applied' => $filters, 'sort' => $sortDir]
    );
} catch (RuntimeException $e) {
    ApiResponse::error('Failed to fetch logs: ' . $e->getMessage(), 500);
}

function exportAsCsv(array $rows, array $filters): void
{
    $filename = 'activity_logs_' . date('Ymd_His') . '.csv';

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-cache, no-store, must-revalidate');

    $fp = fopen('php://output', 'w');

    // UTF-8 BOM for Excel compatibility
    fprintf($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));

    fputcsv($fp, ['ID', 'User ID', 'Action', 'Metadata', 'IP Address', 'User Agent', 'Created At']);

    foreach ($rows as $row) {
        fputcsv($fp, [
            $row['id'],
            $row['user_id'],
            $row['action'],
            is_array($row['metadata']) ? json_encode($row['metadata']) : $row['metadata'],
            $row['ip_address'],
            $row['user_agent'],
            $row['created_at'],
        ]);
    }

    fclose($fp);
}
