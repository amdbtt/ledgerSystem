<?php

declare(strict_types=1);

namespace App;

final class Response
{
    public static function json(
        bool $success,
        mixed $result = [],
        string $message = '',
        ?array $pagination = null,
        int $status = 200
    ): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        $payload = [
            'success' => $success,
            'result' => $result,
            'message' => $message,
        ];
        if ($pagination !== null) {
            $payload['pagination'] = $pagination;
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function ok(mixed $result = [], string $message = '', ?array $pagination = null): void
    {
        self::json(true, $result, $message, $pagination, 200);
    }

    public static function fail(string $message, int $status = 400, mixed $result = []): void
    {
        self::json(false, $result, $message, null, $status);
    }
}
