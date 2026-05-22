<?php

require_once __DIR__ . '/../config/app.php';

class RateLimiter
{
    private string $storageDir;
    private int    $maxRequests;
    private int    $windowSeconds;

    public function __construct(
        string $storageDir    = RATE_LIMIT_STORAGE,
        int    $maxRequests   = RATE_LIMIT_MAX_REQUESTS,
        int    $windowSeconds = RATE_LIMIT_WINDOW_SECONDS
    ) {
        $this->storageDir    = rtrim($storageDir, '/') . '/';
        $this->maxRequests   = $maxRequests;
        $this->windowSeconds = $windowSeconds;

        if (!is_dir($this->storageDir)) {
            mkdir($this->storageDir, 0755, true);
        }
    }

    /**
     * Check if the given IP is within the rate limit.
     * Returns ['allowed' => bool, 'remaining' => int, 'reset_at' => int]
     */
    public function check(string $ip): array
    {
        $file = $this->storageDir . md5($ip) . '.json';
        $now  = time();

        $data = $this->readFile($file);

        // Purge entries outside the current window
        $windowStart = $now - $this->windowSeconds;
        $data['hits'] = array_values(array_filter($data['hits'] ?? [], fn($t) => $t >= $windowStart));

        $count = count($data['hits']);

        if ($count >= $this->maxRequests) {
            $oldestHit = $data['hits'][0];
            $resetAt   = $oldestHit + $this->windowSeconds;
            return [
                'allowed'   => false,
                'remaining' => 0,
                'reset_at'  => $resetAt,
                'limit'     => $this->maxRequests,
            ];
        }

        // Record this hit
        $data['hits'][] = $now;
        $this->writeFile($file, $data);

        return [
            'allowed'   => true,
            'remaining' => $this->maxRequests - $count - 1,
            'reset_at'  => $now + $this->windowSeconds,
            'limit'     => $this->maxRequests,
        ];
    }

    /**
     * Clean up stale rate-limit files older than the window
     */
    public function cleanup(): int
    {
        $cutoff = time() - $this->windowSeconds;
        $count  = 0;

        foreach (glob($this->storageDir . '*.json') as $file) {
            if (filemtime($file) < $cutoff) {
                unlink($file);
                $count++;
            }
        }

        return $count;
    }

    private function readFile(string $file): array
    {
        if (!file_exists($file)) {
            return ['hits' => []];
        }

        $content = file_get_contents($file);
        $data    = json_decode($content, true);

        return is_array($data) ? $data : ['hits' => []];
    }

    private function writeFile(string $file, array $data): void
    {
        file_put_contents($file, json_encode($data), LOCK_EX);
    }
}
