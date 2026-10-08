<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Helpers;
use App\Request;
use App\Response;

final class PaymentModeController
{
    public static function create(Request $request): void
    {
        $data = self::validate($request->body());
        $pdo = Database::pdo();
        if ($data['isDefault']) {
            Helpers::clearDefault($pdo, 'payment_modes');
        }
        $stmt = $pdo->prepare(
            'INSERT INTO payment_modes (name, description, is_default, enabled) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$data['name'], $data['description'], $data['isDefault'] ? 1 : 0, $data['enabled'] ? 1 : 0]);
        Response::ok(self::readById((int) $pdo->lastInsertId()), 'Payment mode created');
    }

    public static function read(Request $request, array $params): void
    {
        $row = self::readById((int) $params['id']);
        if (!$row) {
            Response::fail('Payment mode not found', 404);
        }
        Response::ok($row);
    }

    public static function update(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        if (!self::readById($id)) {
            Response::fail('Payment mode not found', 404);
        }
        $data = self::validate($request->body());
        $pdo = Database::pdo();
        if ($data['isDefault']) {
            Helpers::clearDefault($pdo, 'payment_modes', $id);
        }
        $stmt = $pdo->prepare(
            'UPDATE payment_modes SET name = ?, description = ?, is_default = ?, enabled = ? WHERE id = ? AND removed = 0'
        );
        $stmt->execute([$data['name'], $data['description'], $data['isDefault'] ? 1 : 0, $data['enabled'] ? 1 : 0, $id]);
        Response::ok(self::readById($id), 'Payment mode updated');
    }

    public static function delete(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('UPDATE payment_modes SET removed = 1, is_default = 0 WHERE id = ?');
        $stmt->execute([$id]);
        Response::ok(['_id' => Helpers::id($id)], 'Payment mode deleted');
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
        Response::ok(['total' => 0, 'total_undue' => 0, 'performance' => []]);
    }

    private static function queryList(Request $request, bool $all, bool $search = false): void
    {
        $pdo = Database::pdo();
        $page = max(1, (int) $request->query('page', 1));
        $items = max(1, min(100, (int) $request->query('items', 10)));
        $q = trim((string) $request->query('q', $request->query('equal', '')));

        $where = ['removed = 0'];
        $params = [];
        if ($search && $q !== '') {
            $where[] = '(name LIKE ? OR description LIKE ?)';
            $params[] = '%' . $q . '%';
            $params[] = '%' . $q . '%';
        }
        $sqlWhere = implode(' AND ', $where);
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM payment_modes WHERE {$sqlWhere}");
        $countStmt->execute($params);
        $count = (int) $countStmt->fetchColumn();

        $sql = "SELECT * FROM payment_modes WHERE {$sqlWhere} ORDER BY id DESC";
        if (!$all) {
            $offset = ($page - 1) * $items;
            $sql .= " LIMIT {$items} OFFSET {$offset}";
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = array_map([Helpers::class, 'mapPaymentMode'], $stmt->fetchAll());
        if ($all) {
            Response::ok($rows);
        }
        Response::ok($rows, '', ['page' => $page, 'count' => $count]);
    }

    private static function validate(array $body): array
    {
        $name = trim((string) ($body['name'] ?? ''));
        if ($name === '') {
            Response::fail('name is required');
        }
        return [
            'name' => $name,
            'description' => trim((string) ($body['description'] ?? '')),
            'isDefault' => Helpers::boolish($body['isDefault'] ?? false),
            'enabled' => Helpers::boolish($body['enabled'] ?? true),
        ];
    }

    private static function readById(int $id): ?array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT * FROM payment_modes WHERE id = ? AND removed = 0 LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? Helpers::mapPaymentMode($row) : null;
    }
}
