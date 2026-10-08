<?php

declare(strict_types=1);

namespace App;

final class Request
{
    private array $json;
    private array $query;
    private string $method;
    private string $path;

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $this->path = rtrim($uri, '/') ?: '/';
        $this->query = $_GET;

        $raw = file_get_contents('php://input') ?: '';
        $decoded = json_decode($raw, true);
        $this->json = is_array($decoded) ? $decoded : [];

        if (!empty($_POST) && empty($this->json)) {
            $this->json = $_POST;
        }
    }

    public function method(): string
    {
        return $this->method;
    }

    public function path(): string
    {
        return $this->path;
    }

    public function query(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }
        return $this->query[$key] ?? $default;
    }

    public function body(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->json;
        }
        return $this->json[$key] ?? $default;
    }

    public function bearerToken(): ?string
    {
        $header = $_SERVER['HTTP_AUTHORIZATION']
            ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
            ?? '';

        if ($header === '' && function_exists('apache_request_headers')) {
            $headers = apache_request_headers();
            $header = $headers['Authorization'] ?? $headers['authorization'] ?? '';
        }

        if (preg_match('/Bearer\s+(\S+)/i', $header, $matches)) {
            return $matches[1];
        }

        $alt = $_SERVER['HTTP_X_AUTH_TOKEN'] ?? '';
        return $alt !== '' ? $alt : null;
    }

    public function files(): array
    {
        return $_FILES;
    }
}
