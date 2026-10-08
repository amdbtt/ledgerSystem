<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Config;
use App\Request;
use App\Response;

$origin = Config::get('CORS_ORIGIN', 'http://localhost:3000');
header('Access-Control-Allow-Origin: ' . $origin);
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Authorization, Content-Type, X-Auth-Token, x-auth-token');
header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');

if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Static uploads under /public/uploads are served by the PHP built-in server router below.
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
if (str_starts_with($path, '/uploads/')) {
    $file = __DIR__ . $path;
    $realUploads = realpath(__DIR__ . '/uploads');
    $realFile = realpath($file);
    if ($realUploads && $realFile && str_starts_with($realFile, $realUploads) && is_file($realFile)) {
        $mime = mime_content_type($realFile) ?: 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('X-Content-Type-Options: nosniff');
        readfile($realFile);
        exit;
    }
    Response::fail('File not found', 404);
}

try {
    /** @var \App\Router $router */
    $router = require dirname(__DIR__) . '/src/routes.php';
    $router->dispatch(new Request());
} catch (Throwable $e) {
    $message = Config::get('APP_DEBUG', 'false') === 'true'
        ? $e->getMessage()
        : 'Internal server error';
    Response::fail($message, 500);
}
