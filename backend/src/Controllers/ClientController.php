<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Helpers;
use App\Request;
use App\Response;

final class ClientController
{
    public static function create(Request $request): void
    {
        $data = self::validate($request->body());
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO clients (name, country, address, phone, email) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $data['name'],
            $data['country'],
            $data['address'],
            $data['phone'],
            $data['email'],
        ]);
        Response::ok(self::readById((int) $pdo->lastInsertId()), 'Client created');
    }

    public static function read(Request $request, array $params): void
    {
        $row = self::readById((int) $params['id']);
        if (!$row) {
            Response::fail('Client not found', 404);
        }
        Response::ok($row);
    }

    public static function update(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        if (!self::readById($id)) {
            Response::fail('Client not found', 404);
        }
        $data = self::validate($request->body());
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'UPDATE clients SET name = ?, country = ?, address = ?, phone = ?, email = ? WHERE id = ? AND removed = 0'
        );
        $stmt->execute([
            $data['name'],
            $data['country'],
            $data['address'],
            $data['phone'],
            $data['email'],
            $id,
        ]);
        Response::ok(self::readById($id), 'Client updated');
    }

    public static function delete(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('UPDATE clients SET removed = 1 WHERE id = ?');
        $stmt->execute([$id]);
        Response::ok(['_id' => Helpers::id($id)], 'Client deleted');
    }

    public static function list(Request $request): void
    {
        self::queryList($request, false);
    }

    public static function listAll(Request $request): void
    {
        self::queryList($request, true);
    }

    public static function search(Request $request): void
    {
        self::queryList($request, false, true);
    }

    public static function filter(Request $request): void
    {
        self::queryList($request, false, true);
    }

    public static function summary(Request $request): void
    {
        $pdo = Database::pdo();
        $total = (int) $pdo->query('SELECT COUNT(*) FROM clients WHERE removed = 0')->fetchColumn();
        $active = (int) $pdo->query(
            'SELECT COUNT(*) FROM clients WHERE removed = 0 AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)'
        )->fetchColumn();
        $new = (int) $pdo->query(
            'SELECT COUNT(*) FROM clients WHERE removed = 0 AND YEAR(created_at) = YEAR(CURDATE()) AND MONTH(created_at) = MONTH(CURDATE())'
        )->fetchColumn();

        Response::ok([
            'total' => $total,
            'active' => $active,
            'new' => $new,
        ]);
    }

    private static function queryList(Request $request, bool $all, bool $search = false): void
    {
        $pdo = Database::pdo();
        $page = max(1, (int) $request->query('page', 1));
        $items = max(1, min(100, (int) $request->query('items', $request->query('count', 10))));
        $q = trim((string) ($request->query('q', $request->query('equal', $request->query('filter', '')))));
        $fields = (string) $request->query('fields', 'name,email,phone,country,address');

        $where = ['removed = 0'];
        $params = [];
        if ($search && $q !== '') {
            $parts = [];
            foreach (explode(',', $fields) as $field) {
                $field = trim($field);
                if (!in_array($field, ['name', 'email', 'phone', 'country', 'address'], true)) {
                    continue;
                }
                $parts[] = "{$field} LIKE ?";
                $params[] = '%' . $q . '%';
            }
            if ($parts) {
                $where[] = '(' . implode(' OR ', $parts) . ')';
            }
        }

        $sqlWhere = implode(' AND ', $where);
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM clients WHERE {$sqlWhere}");
        $countStmt->execute($params);
        $count = (int) $countStmt->fetchColumn();

        $sql = "SELECT * FROM clients WHERE {$sqlWhere} ORDER BY id DESC";
        if (!$all) {
            $offset = ($page - 1) * $items;
            $sql .= " LIMIT {$items} OFFSET {$offset}";
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = array_map([Helpers::class, 'mapClient'], $stmt->fetchAll());

        if ($all) {
            Response::ok($rows);
        }
        Response::ok($rows, '', ['page' => $page, 'count' => $count]);
    }

    private static function validate(array $body): array
    {
        $name = trim((string) ($body['name'] ?? $body['company'] ?? ''));
        $email = trim((string) ($body['email'] ?? ''));
        if ($name === '') {
            Response::fail('name is required');
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::fail('Valid email is required');
        }
        return [
            'name' => $name,
            'country' => trim((string) ($body['country'] ?? '')),
            'address' => trim((string) ($body['address'] ?? '')),
            'phone' => trim((string) ($body['phone'] ?? '')),
            'email' => $email,
        ];
    }

    private static function readById(int $id): ?array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT * FROM clients WHERE id = ? AND removed = 0 LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? Helpers::mapClient($row) : null;
    }
}
