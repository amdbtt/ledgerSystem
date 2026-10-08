<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use App\Config;

$host = Config::require('DB_HOST');
$port = Config::get('DB_PORT', '3306');
$name = Config::require('DB_NAME');
$user = Config::require('DB_USER');
$pass = Config::get('DB_PASS', '');

$pdo = new PDO(
    "mysql:host={$host};port={$port};charset=utf8mb4",
    $user,
    $pass,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `{$name}`");

$schema = file_get_contents(dirname(__DIR__) . '/sql/schema.sql');
$seed = file_get_contents(dirname(__DIR__) . '/sql/seed.sql');

if ($schema === false || $seed === false) {
    fwrite(STDERR, "Could not read SQL files\n");
    exit(1);
}

$pdo->exec($schema);
$pdo->exec($seed);

echo "Migrated and seeded database `{$name}` on {$host}:{$port}\n";
echo "Admin login: admin@admin.com / admin123\n";
