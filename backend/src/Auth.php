<?php

declare(strict_types=1);

namespace App;

use PDO;

final class Auth
{
    private static ?array $admin = null;

    public static function requireAdmin(Request $request): array
    {
        $token = $request->bearerToken();
        if (!$token) {
            Response::fail('Unauthorized', 401);
        }

        try {
            $payload = Jwt::decode($token);
        } catch (\Throwable $e) {
            Response::fail('Unauthorized', 401);
        }

        $adminId = (int) ($payload['sub'] ?? 0);
        if ($adminId <= 0) {
            Response::fail('Unauthorized', 401);
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT id, name, surname, email, photo, role, enabled, removed
             FROM admins WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$adminId]);
        $admin = $stmt->fetch();

        if (!$admin || (int) $admin['removed'] === 1 || (int) $admin['enabled'] !== 1) {
            Response::fail('Unauthorized', 401);
        }

        self::$admin = $admin;
        return $admin;
    }

    public static function admin(): ?array
    {
        return self::$admin;
    }

    public static function publicAdmin(array $admin, ?string $token = null): array
    {
        $result = [
            '_id' => Helpers::id($admin['id']),
            'name' => $admin['name'],
            'surname' => $admin['surname'],
            'email' => $admin['email'],
            'photo' => $admin['photo'],
            'role' => $admin['role'] ?? 'admin',
        ];
        if ($token !== null) {
            $result['token'] = $token;
        }
        return $result;
    }

    public static function findByEmail(PDO $pdo, string $email): ?array
    {
        $stmt = $pdo->prepare(
            'SELECT * FROM admins WHERE email = ? AND removed = 0 LIMIT 1'
        );
        $stmt->execute([strtolower(trim($email))]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
