<?php

class ApiResponse
{
    public static function success(mixed $data = null, string $message = 'OK', int $statusCode = 200): void
    {
        self::send([
            'success' => true,
            'message' => $message,
            'data'    => $data,
        ], $statusCode);
    }

    public static function error(string $message, int $statusCode = 400, array $errors = []): void
    {
        $body = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors) {
            $body['errors'] = $errors;
        }

        self::send($body, $statusCode);
    }

    public static function paginated(array $rows, array $pagination, mixed $extra = null): void
    {
        $body = [
            'success'    => true,
            'data'       => $rows,
            'pagination' => $pagination,
        ];

        if ($extra !== null) {
            $body['meta'] = $extra;
        }

        self::send($body, 200);
    }

    public static function rateLimitExceeded(int $resetAt, int $limit): void
    {
        header('Retry-After: ' . ($resetAt - time()));
        header('X-RateLimit-Limit: ' . $limit);
        header('X-RateLimit-Remaining: 0');
        header('X-RateLimit-Reset: ' . $resetAt);

        self::error('Rate limit exceeded. Try again later.', 429);
    }

    private static function send(array $body, int $statusCode): void
    {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            http_response_code($statusCode);
        }

        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }
}
