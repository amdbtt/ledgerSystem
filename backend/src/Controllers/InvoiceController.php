<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Helpers;
use App\Request;
use App\Response;

final class InvoiceController
{
    public static function create(Request $request): void
    {
        $pdo = Database::pdo();
        $body = $request->body();
        $clientId = Helpers::extractId($body['client'] ?? null);
        if (!$clientId) {
            Response::fail('client is required');
        }

        $items = is_array($body['items'] ?? null) ? $body['items'] : [];
        if ($items === []) {
            Response::fail('items are required');
        }

        $taxRate = (float) ($body['taxRate'] ?? 0);
        [$normalized, $subTotal, $taxTotal, $total] = Helpers::calcTotals($items, $taxRate);
        foreach ($normalized as $item) {
            if ($item['itemName'] === '') {
                Response::fail('Each item needs itemName');
            }
        }

        $number = (int) ($body['number'] ?? 0);
        $year = (int) ($body['year'] ?? date('Y'));
        $date = Helpers::parseDate($body['date'] ?? null) ?? date('Y-m-d');
        $expired = Helpers::parseDate($body['expiredDate'] ?? null) ?? date('Y-m-d', strtotime('+30 days'));
        $currency = strtoupper((string) ($body['currency'] ?? Helpers::getSetting($pdo, 'default_currency_code', 'PKR')));
        $status = (string) ($body['status'] ?? 'draft');
        $notes = (string) ($body['notes'] ?? '');
        $discount = (float) ($body['discount'] ?? 0);

        if ($number <= 0) {
            $number = ((int) Helpers::getSetting($pdo, 'last_invoice_number', '0')) + 1;
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO invoices
                (client_id, number, year, date, expired_date, currency, status, payment_status, notes,
                 sub_total, tax_rate, tax_total, total, discount, credit)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)'
            );
            $stmt->execute([
                $clientId, $number, $year, $date, $expired, $currency, $status, 'unpaid', $notes,
                $subTotal, $taxRate, $taxTotal, $total, $discount,
            ]);
            $id = (int) $pdo->lastInsertId();
            self::insertItems($pdo, $id, $normalized);
            Helpers::bumpFinanceNumber($pdo, 'last_invoice_number', $number);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Response::fail('Could not create invoice: ' . $e->getMessage(), 500);
        }

        Response::ok(self::readById($id), 'Invoice created');
    }

    public static function read(Request $request, array $params): void
    {
        $row = self::readById((int) $params['id']);
        if (!$row) {
            Response::fail('Invoice not found', 404);
        }
        Response::ok($row);
    }

    public static function update(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $existing = self::readById($id);
        if (!$existing) {
            Response::fail('Invoice not found', 404);
        }

        $pdo = Database::pdo();
        $body = $request->body();
        $clientId = Helpers::extractId($body['client'] ?? $existing['client']) ?? (int) $existing['client']['_id'];
        $items = is_array($body['items'] ?? null) ? $body['items'] : ($existing['items'] ?? []);
        $taxRate = (float) ($body['taxRate'] ?? $existing['taxRate']);
        [$normalized, $subTotal, $taxTotal, $total] = Helpers::calcTotals($items, $taxRate);

        $number = (int) ($body['number'] ?? $existing['number']);
        $year = (int) ($body['year'] ?? $existing['year']);
        $date = Helpers::parseDate($body['date'] ?? $existing['date']) ?? $existing['date'];
        $expired = Helpers::parseDate($body['expiredDate'] ?? $existing['expiredDate']) ?? $existing['expiredDate'];
        $currency = strtoupper((string) ($body['currency'] ?? $existing['currency']));
        $status = (string) ($body['status'] ?? $existing['status']);
        $notes = (string) ($body['notes'] ?? $existing['notes'] ?? '');
        $discount = (float) ($body['discount'] ?? $existing['discount']);
        $credit = (float) $existing['credit'];
        $paymentStatus = Helpers::paymentStatus($total, $discount, $credit);

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'UPDATE invoices SET client_id = ?, number = ?, year = ?, date = ?, expired_date = ?,
                 currency = ?, status = ?, payment_status = ?, notes = ?, sub_total = ?, tax_rate = ?,
                 tax_total = ?, total = ?, discount = ? WHERE id = ? AND removed = 0'
            );
            $stmt->execute([
                $clientId, $number, $year, $date, $expired, $currency, $status, $paymentStatus, $notes,
                $subTotal, $taxRate, $taxTotal, $total, $discount, $id,
            ]);
            $pdo->prepare('DELETE FROM invoice_items WHERE invoice_id = ?')->execute([$id]);
            self::insertItems($pdo, $id, $normalized);
            Helpers::bumpFinanceNumber($pdo, 'last_invoice_number', $number);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Response::fail('Could not update invoice: ' . $e->getMessage(), 500);
        }

        Response::ok(self::readById($id), 'Invoice updated');
    }

    public static function delete(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('UPDATE invoices SET removed = 1 WHERE id = ?');
        $stmt->execute([$id]);
        Response::ok(['_id' => Helpers::id($id)], 'Invoice deleted');
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
        self::queryList($request, false, false, true);
    }

    public static function summary(Request $request): void
    {
        $pdo = Database::pdo();
        $currency = strtoupper((string) $request->query('currency', Helpers::getSetting($pdo, 'default_currency_code', 'PKR')));

        $totalStmt = $pdo->prepare(
            "SELECT COALESCE(SUM(total),0) FROM invoices
             WHERE removed = 0 AND currency = ?
             AND YEAR(date) = YEAR(CURDATE()) AND MONTH(date) = MONTH(CURDATE())"
        );
        $totalStmt->execute([$currency]);
        $total = (float) $totalStmt->fetchColumn();

        $undueStmt = $pdo->prepare(
            "SELECT COALESCE(SUM(GREATEST(total - discount - credit, 0)),0) FROM invoices
             WHERE removed = 0 AND currency = ? AND payment_status <> 'paid'"
        );
        $undueStmt->execute([$currency]);
        $undue = (float) $undueStmt->fetchColumn();

        $perfStmt = $pdo->prepare(
            'SELECT status, COUNT(*) AS c FROM invoices WHERE removed = 0 AND currency = ? GROUP BY status'
        );
        $perfStmt->execute([$currency]);
        $rows = $perfStmt->fetchAll();
        $sum = array_sum(array_map(static fn ($r) => (int) $r['c'], $rows)) ?: 1;
        $performance = array_map(static function (array $r) use ($sum): array {
            return [
                'status' => $r['status'],
                'percentage' => (int) round(((int) $r['c'] / $sum) * 100),
            ];
        }, $rows);

        Response::ok([
            'total' => $total,
            'total_undue' => $undue,
            'performance' => $performance,
        ]);
    }

    public static function mail(Request $request): void
    {
        Response::fail('Mail is disabled. Set MAIL_ENABLED=true and configure SMTP later.', 501);
    }

    public static function readById(int $id): ?array
    {
        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'SELECT i.*, c.id AS c_id, c.name AS c_name, c.country AS c_country, c.address AS c_address,
                    c.phone AS c_phone, c.email AS c_email
             FROM invoices i
             JOIN clients c ON c.id = i.client_id
             WHERE i.id = ? AND i.removed = 0 LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $itemsStmt = $pdo->prepare('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY id ASC');
        $itemsStmt->execute([$id]);
        $items = array_map([Helpers::class, 'mapItem'], $itemsStmt->fetchAll());

        return [
            '_id' => Helpers::id($row['id']),
            'number' => (int) $row['number'],
            'year' => (int) $row['year'],
            'date' => $row['date'],
            'expiredDate' => $row['expired_date'],
            'currency' => $row['currency'],
            'status' => $row['status'],
            'paymentStatus' => $row['payment_status'],
            'notes' => $row['notes'],
            'subTotal' => (float) $row['sub_total'],
            'taxRate' => (float) $row['tax_rate'],
            'taxTotal' => (float) $row['tax_total'],
            'total' => (float) $row['total'],
            'discount' => (float) $row['discount'],
            'credit' => (float) $row['credit'],
            'client' => [
                '_id' => Helpers::id($row['c_id']),
                'name' => $row['c_name'],
                'country' => $row['c_country'],
                'address' => $row['c_address'],
                'phone' => $row['c_phone'],
                'email' => $row['c_email'],
            ],
            'items' => $items,
        ];
    }

    private static function insertItems(\PDO $pdo, int $invoiceId, array $items): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO invoice_items (invoice_id, item_name, description, price, quantity, total)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        foreach ($items as $item) {
            $stmt->execute([
                $invoiceId,
                $item['itemName'],
                $item['description'],
                $item['price'],
                $item['quantity'],
                $item['total'],
            ]);
        }
    }

    private static function queryList(Request $request, bool $all, bool $search = false, bool $filter = false): void
    {
        $pdo = Database::pdo();
        $page = max(1, (int) $request->query('page', 1));
        $itemsPerPage = max(1, min(100, (int) $request->query('items', 10)));
        $where = ['i.removed = 0'];
        $params = [];

        if ($filter && (string) $request->query('filter') === 'client') {
            $equal = Helpers::extractId($request->query('equal'));
            if ($equal) {
                $where[] = 'i.client_id = ?';
                $params[] = $equal;
            }
        }

        if ($search) {
            $q = trim((string) $request->query('q', ''));
            if ($q !== '') {
                $where[] = '(CAST(i.number AS CHAR) LIKE ? OR c.name LIKE ?)';
                $params[] = '%' . $q . '%';
                $params[] = '%' . $q . '%';
            }
        }

        $sqlWhere = implode(' AND ', $where);
        $countStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM invoices i JOIN clients c ON c.id = i.client_id WHERE {$sqlWhere}"
        );
        $countStmt->execute($params);
        $count = (int) $countStmt->fetchColumn();

        $sql = "SELECT i.id FROM invoices i JOIN clients c ON c.id = i.client_id WHERE {$sqlWhere} ORDER BY i.id DESC";
        if (!$all) {
            $offset = ($page - 1) * $itemsPerPage;
            $sql .= " LIMIT {$itemsPerPage} OFFSET {$offset}";
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $ids = array_map(static fn ($r) => (int) $r['id'], $stmt->fetchAll());
        $rows = array_values(array_filter(array_map([self::class, 'readById'], $ids)));

        if ($all) {
            Response::ok($rows);
        }
        Response::ok($rows, '', ['page' => $page, 'count' => $count]);
    }
}
