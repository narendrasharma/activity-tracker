<?php

require_once __DIR__ . '/Database.php';

class ActivityLogger
{
    private static ?ActivityLogger $instance = null;
    private Database $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Log a user activity
     *
     * @param int    $userId   The user performing the action
     * @param string $action   The action identifier (e.g., "login", "view_page")
     * @param array  $metadata Additional context data (stored as JSON)
     * @param string|null $ipAddress  Override IP (auto-detected if null)
     * @param string|null $userAgent  Override User-Agent (auto-detected if null)
     * @return int  The inserted log ID
     */
    public static function log(int $userId, string $action, array $metadata = [], ?string $ipAddress = null, ?string $userAgent = null): int
    {
        return self::getInstance()->insertLog($userId, $action, $metadata, $ipAddress, $userAgent);
    }

    public function insertLog(int $userId, string $action, array $metadata = [], ?string $ipAddress = null, ?string $userAgent = null): int
    {
        $ip         = $ipAddress  ?? $this->detectIp();
        $ua         = $userAgent  ?? ($_SERVER['HTTP_USER_AGENT'] ?? 'CLI');
        $metaJson   = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp  = date('Y-m-d H:i:s');

        $stmt = $this->db->prepare(
            'INSERT INTO activity_logs (user_id, action, metadata, ip_address, user_agent, created_at)
             VALUES (?, ?, ?, ?, ?, ?)'
        );

        if (!$stmt) {
            throw new RuntimeException('Failed to prepare log statement: ' . $this->db->getConnection()->error);
        }

        $stmt->bind_param('isssss', $userId, $action, $metaJson, $ip, $ua, $timestamp);

        if (!$stmt->execute()) {
            throw new RuntimeException('Failed to insert log: ' . $stmt->error);
        }

        $id = $this->db->lastInsertId();
        $stmt->close();

        return $id;
    }

    /**
     * Fetch logs with dynamic filtering, sorting, and pagination
     */
    public function fetchLogs(array $filters = [], int $limit = 50, int $offset = 0, string $sortDir = 'DESC'): array
    {
        $limit  = min(max(1, $limit), MAX_LIMIT);
        $offset = max(0, $offset);
        $sortDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        $conditions = [];
        $bindings   = [];
        $types      = '';

        if (!empty($filters['user_id'])) {
            $conditions[] = 'user_id = ?';
            $bindings[]   = (int)$filters['user_id'];
            $types       .= 'i';
        }

        if (!empty($filters['action'])) {
            $conditions[] = 'action = ?';
            $bindings[]   = $filters['action'];
            $types       .= 's';
        }

        if (!empty($filters['date_from'])) {
            $conditions[] = 'created_at >= ?';
            $bindings[]   = $filters['date_from'];
            $types       .= 's';
        }

        if (!empty($filters['date_to'])) {
            $conditions[] = 'created_at <= ?';
            $bindings[]   = $filters['date_to'];
            $types       .= 's';
        }

        if (!empty($filters['ip_address'])) {
            $conditions[] = 'ip_address = ?';
            $bindings[]   = $filters['ip_address'];
            $types       .= 's';
        }

        $whereClause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        // Count total for pagination metadata
        $countSql  = "SELECT COUNT(*) as total FROM activity_logs {$whereClause}";
        $totalRows = $this->executeCountQuery($countSql, $types, $bindings);

        // Fetch page
        $dataSql = "SELECT id, user_id, action, metadata, ip_address, user_agent, created_at
                    FROM activity_logs
                    {$whereClause}
                    ORDER BY created_at {$sortDir}
                    LIMIT ? OFFSET ?";

        $types    .= 'ii';
        $bindings[] = $limit;
        $bindings[] = $offset;

        $rows = $this->executeFetchQuery($dataSql, $types, $bindings);

        // Decode metadata JSON
        foreach ($rows as &$row) {
            $row['metadata'] = json_decode($row['metadata'], true) ?? [];
        }

        return [
            'data'       => $rows,
            'pagination' => [
                'total'   => $totalRows,
                'limit'   => $limit,
                'offset'  => $offset,
                'pages'   => $limit > 0 ? (int)ceil($totalRows / $limit) : 1,
            ],
        ];
    }

    /**
     * Get top N users by activity count
     */
    public function getTopUsers(int $n = 5): array
    {
        $stmt = $this->db->prepare(
            'SELECT user_id, COUNT(*) as activity_count
             FROM activity_logs
             GROUP BY user_id
             ORDER BY activity_count DESC
             LIMIT ?'
        );

        if (!$stmt) {
            throw new RuntimeException('Failed to prepare top-users statement');
        }

        $stmt->bind_param('i', $n);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows   = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $rows;
    }

    /**
     * Detect anomalies:
     * 1. >10 actions in 1 minute by same user
     * 2. Same user from multiple IPs within 5 minutes
     */
    public function detectAnomalies(): array
    {
        return [
            'high_frequency' => $this->detectHighFrequency(),
            'multi_ip'       => $this->detectMultiIp(),
            'generated_at'   => date('Y-m-d H:i:s'),
        ];
    }

    private function detectHighFrequency(): array
    {
        $threshold = ANOMALY_MAX_ACTIONS_PER_MINUTE;
        $sql = "SELECT user_id,
                       DATE_FORMAT(created_at, '%Y-%m-%d %H:%i') as minute_window,
                       COUNT(*) as action_count
                FROM activity_logs
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
                GROUP BY user_id, minute_window
                HAVING action_count > ?
                ORDER BY action_count DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('i', $threshold);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows   = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $rows;
    }

    private function detectMultiIp(): array
    {
        $windowSeconds = ANOMALY_MULTI_IP_WINDOW_SECONDS;
        $sql = "SELECT a.user_id,
                       COUNT(DISTINCT a.ip_address) as ip_count,
                       GROUP_CONCAT(DISTINCT a.ip_address ORDER BY a.ip_address SEPARATOR ', ') as ip_addresses,
                       MIN(a.created_at) as first_seen,
                       MAX(a.created_at) as last_seen
                FROM activity_logs a
                WHERE a.created_at >= DATE_SUB(NOW(), INTERVAL ? SECOND)
                GROUP BY a.user_id
                HAVING ip_count > 1
                ORDER BY ip_count DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->bind_param('i', $windowSeconds);
        $stmt->execute();
        $result = $stmt->get_result();
        $rows   = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $rows;
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function detectIp(): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'] as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = trim(explode(',', $_SERVER[$key])[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }

    private function executeCountQuery(string $sql, string $types, array $bindings): int
    {
        if (empty($bindings)) {
            $result = $this->db->query($sql);
            $row    = $result->fetch_assoc();
            return (int)($row['total'] ?? 0);
        }

        $stmt = $this->db->prepare($sql);
        if ($bindings) {
            $stmt->bind_param($types, ...$bindings);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $row    = $result->fetch_assoc();
        $stmt->close();

        return (int)($row['total'] ?? 0);
    }

    private function executeFetchQuery(string $sql, string $types, array $bindings): array
    {
        $stmt = $this->db->prepare($sql);
        if (!$stmt) {
            throw new RuntimeException('Query prepare failed: ' . $this->db->getConnection()->error);
        }

        if ($bindings) {
            $stmt->bind_param($types, ...$bindings);
        }

        $stmt->execute();
        $result = $stmt->get_result();
        $rows   = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $rows;
    }
}
