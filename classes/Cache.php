<?php

require_once __DIR__ . '/../config/app.php';

class Cache
{
    private string $cacheDir;

    public function __construct(string $cacheDir = CACHE_DIR)
    {
        $this->cacheDir = rtrim($cacheDir, '/') . '/data/';

        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    /**
     * Get a cached value. Returns null if missing or expired.
     */
    public function get(string $key): mixed
    {
        $file = $this->filePath($key);

        if (!file_exists($file)) {
            return null;
        }

        $content = file_get_contents($file);
        $data    = json_decode($content, true);

        if (!$data || !isset($data['expires_at'], $data['value'])) {
            return null;
        }

        if (time() > $data['expires_at']) {
            unlink($file);
            return null;
        }

        return $data['value'];
    }

    /**
     * Store a value in cache for $ttl seconds.
     */
    public function set(string $key, mixed $value, int $ttl): bool
    {
        $file = $this->filePath($key);
        $data = [
            'key'        => $key,
            'expires_at' => time() + $ttl,
            'value'      => $value,
        ];

        return (bool)file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    /**
     * Delete a specific cache key.
     */
    public function delete(string $key): bool
    {
        $file = $this->filePath($key);

        if (file_exists($file)) {
            return unlink($file);
        }

        return false;
    }

    /**
     * Clear all cache entries.
     */
    public function flush(): int
    {
        $count = 0;
        foreach (glob($this->cacheDir . '*.cache') as $file) {
            unlink($file);
            $count++;
        }
        return $count;
    }

    /**
     * Remove expired cache entries.
     */
    public function cleanup(): int
    {
        $count = 0;
        $now   = time();

        foreach (glob($this->cacheDir . '*.cache') as $file) {
            $content = file_get_contents($file);
            $data    = json_decode($content, true);

            if (!$data || (isset($data['expires_at']) && $now > $data['expires_at'])) {
                unlink($file);
                $count++;
            }
        }

        return $count;
    }

    /**
     * Get or compute a value (cache-aside pattern).
     */
    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $cached = $this->get($key);

        if ($cached !== null) {
            return $cached;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }

    private function filePath(string $key): string
    {
        return $this->cacheDir . md5($key) . '.cache';
    }
}
