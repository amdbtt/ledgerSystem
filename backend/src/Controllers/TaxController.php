<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Helpers;
use App\Request;
use App\Response;

final class TaxController
{
    public static function create(Request $request): void
    {
        $data = self::validate($request->body());
        $pdo = Database::pdo();
        if ($data['isDefault']) {
            Helpers::clearDefault($pdo, 'taxes');
        }
        $stmt = $pdo->prepare(
            'INSERT INTO taxes (tax_name, tax_value, is_default, enabled) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$data['taxName'], $data['taxValue'], $data['isDefault'] ? 1 : 0, $data['enabled'] ? 1 : 0]);
        Response::ok(self::readById((int) $pdo->lastInsertId()), 'Tax created');
    }

    public static function read(Request $request, array $params): void
    {
        $row = self::readById((int) $params['id']);
        if (!$row) {
            Response::fail('Tax not found', 404);
        }
        Response::ok($row);
    }

    public static function update(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        if (!self::readById($id)) {
            Response::fail('Tax not found', 404);
        }
        $data = self::validate($request->body());
        $pdo = Database::pdo();
        if ($data['isDefault']) {
            Helpers::clearDefault($pdo, 'taxes', $id);
        }
        $stmt = $pdo->prepare(
            'UPDATE taxes SET tax_name = ?, tax_value = ?, is_default = ?, enabled = ? WHERE id = ? AND removed = 0'
        );
        $stmt->execute([$data['taxName'], $data['taxValue'], $data['isDefault'] ? 1 : 0, $data['enabled'] ? 1 : 0, $id]);
        Response::ok(self::readById($id), 'Tax updated');
    }

    public static function delete(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('UPDATE taxes SET removed = 1, is_default = 0 WHERE id = ?');
        $stmt->execute([$id]);
        Response::ok(['_id' => Helpers::id($id)], 'Tax deleted');
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
            $where[] = '(tax_name LIKE ?)';
            $params[] = '%' . $q . '%';
        }
        $sqlWhere = implode(' AND ', $where);
        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM taxes WHERE {$sqlWhere}");
        $countStmt->execute($params);
        $count = (int) $countStmt->fetchColumn();

        $sql = "SELECT * FROM taxes WHERE {$sqlWhere} ORDER BY id DESC";
        if (!$all) {
            $offset = ($page - 1) * $items;
            $sql .= " LIMIT {$items} OFFSET {$offset}";
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = array_map([Helpers::class, 'mapTax'], $stmt->fetchAll());
        if ($all) {
            Response::ok($rows);
        }
        Response::ok($rows, '', ['page' => $page, 'count' => $count]);
    }

    private static function validate(array $body): array
    {
        $taxName = trim((string) ($body['taxName'] ?? $body['name'] ?? ''));
        if ($taxName === '') {
            Response::fail('taxName is required');
        }
        return [
            'taxName' => $taxName,
            'taxValue' => (float) ($body['taxValue'] ?? 0),
            'isDefault' => Helpers::boolish($body['isDefault'] ?? false),
            'enabled' => Helpers::boolish($body['enabled'] ?? true),
        ];
    }

    private static function readById(int $id): ?array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('SELECT * FROM taxes WHERE id = ? AND removed = 0 LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? Helpers::mapTax($row) : null;
    }
}
