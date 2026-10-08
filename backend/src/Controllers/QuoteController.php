<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Helpers;
use App\Request;
use App\Response;

final class QuoteController
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
            $number = ((int) Helpers::getSetting($pdo, 'last_quote_number', '0')) + 1;
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO quotes
                (client_id, number, year, date, expired_date, currency, status, notes,
                 sub_total, tax_rate, tax_total, total, discount)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $clientId, $number, $year, $date, $expired, $currency, $status, $notes,
                $subTotal, $taxRate, $taxTotal, $total, $discount,
            ]);
            $id = (int) $pdo->lastInsertId();
            self::insertItems($pdo, $id, $normalized);
            Helpers::bumpFinanceNumber($pdo, 'last_quote_number', $number);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Response::fail('Could not create quote: ' . $e->getMessage(), 500);
        }

        Response::ok(self::readById($id), 'Quote created');
    }

    public static function read(Request $request, array $params): void
    {
        $row = self::readById((int) $params['id']);
        if (!$row) {
            Response::fail('Quote not found', 404);
        }
        Response::ok($row);
    }

    public static function update(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $existing = self::readById($id);
        if (!$existing) {
            Response::fail('Quote not found', 404);
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

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'UPDATE quotes SET client_id = ?, number = ?, year = ?, date = ?, expired_date = ?,
                 currency = ?, status = ?, notes = ?, sub_total = ?, tax_rate = ?,
                 tax_total = ?, total = ?, discount = ? WHERE id = ? AND removed = 0'
            );
            $stmt->execute([
                $clientId, $number, $year, $date, $expired, $currency, $status, $notes,
                $subTotal, $taxRate, $taxTotal, $total, $discount, $id,
            ]);
            $pdo->prepare('DELETE FROM quote_items WHERE quote_id = ?')->execute([$id]);
            self::insertItems($pdo, $id, $normalized);
            Helpers::bumpFinanceNumber($pdo, 'last_quote_number', $number);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Response::fail('Could not update quote: ' . $e->getMessage(), 500);
        }

        Response::ok(self::readById($id), 'Quote updated');
    }

    public static function delete(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $pdo = Database::pdo();
        $stmt = $pdo->prepare('UPDATE quotes SET removed = 1 WHERE id = ?');
        $stmt->execute([$id]);
        Response::ok(['_id' => Helpers::id($id)], 'Quote deleted');
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

    public static function convert(Request $request, array $params): void
    {
        $quoteId = (int) $params['id'];
        $quote = self::readById($quoteId);
        if (!$quote) {
            Response::fail('Quote not found', 404);
        }

        $pdo = Database::pdo();
        $existing = $pdo->prepare('SELECT id FROM invoices WHERE quote_id = ? AND removed = 0 LIMIT 1');
        $existing->execute([$quoteId]);
        if ($existing->fetch()) {
            Response::fail('Quote already converted to an invoice');
        }

        $number = ((int) Helpers::getSetting($pdo, 'last_invoice_number', '0')) + 1;
        $year = (int) date('Y');
        $clientId = (int) $quote['client']['_id'];

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO invoices
                (client_id, quote_id, number, year, date, expired_date, currency, status, payment_status, notes,
                 sub_total, tax_rate, tax_total, total, discount, credit)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)'
            );
            $stmt->execute([
                $clientId,
                $quoteId,
                $number,
                $year,
                date('Y-m-d'),
                date('Y-m-d', strtotime('+30 days')),
                $quote['currency'],
                'pending',
                'unpaid',
                $quote['notes'] ?? '',
                $quote['subTotal'],
                $quote['taxRate'],
                $quote['taxTotal'],
                $quote['total'],
                $quote['discount'],
            ]);
            $invoiceId = (int) $pdo->lastInsertId();

            $itemStmt = $pdo->prepare(
                'INSERT INTO invoice_items (invoice_id, item_name, description, price, quantity, total)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            foreach ($quote['items'] as $item) {
                $itemStmt->execute([
                    $invoiceId,
                    $item['itemName'],
                    $item['description'],
                    $item['price'],
                    $item['quantity'],
                    $item['total'],
                ]);
            }

            Helpers::bumpFinanceNumber($pdo, 'last_invoice_number', $number);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Response::fail('Could not convert quote: ' . $e->getMessage(), 500);
        }

        Response::ok(InvoiceController::readById($invoiceId), 'Quote converted');
    }

    public static function summary(Request $request): void
    {
        $pdo = Database::pdo();
        $currency = strtoupper((string) $request->query('currency', Helpers::getSetting($pdo, 'default_currency_code', 'PKR')));

        $totalStmt = $pdo->prepare(
            "SELECT COALESCE(SUM(total),0) FROM quotes
             WHERE removed = 0 AND currency = ?
             AND YEAR(date) = YEAR(CURDATE()) AND MONTH(date) = MONTH(CURDATE())"
        );
        $totalStmt->execute([$currency]);
        $total = (float) $totalStmt->fetchColumn();

        $perfStmt = $pdo->prepare(
            'SELECT status, COUNT(*) AS c FROM quotes WHERE removed = 0 AND currency = ? GROUP BY status'
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
            'total_undue' => 0,
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
            'SELECT q.*, c.id AS c_id, c.name AS c_name, c.country AS c_country, c.address AS c_address,
                    c.phone AS c_phone, c.email AS c_email
             FROM quotes q
             JOIN clients c ON c.id = q.client_id
             WHERE q.id = ? AND q.removed = 0 LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $itemsStmt = $pdo->prepare('SELECT * FROM quote_items WHERE quote_id = ? ORDER BY id ASC');
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
            'notes' => $row['notes'],
            'subTotal' => (float) $row['sub_total'],
            'taxRate' => (float) $row['tax_rate'],
            'taxTotal' => (float) $row['tax_total'],
            'total' => (float) $row['total'],
            'discount' => (float) $row['discount'],
            'credit' => 0,
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

    private static function insertItems(\PDO $pdo, int $quoteId, array $items): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO quote_items (quote_id, item_name, description, price, quantity, total)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        foreach ($items as $item) {
            $stmt->execute([
                $quoteId,
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
        $where = ['q.removed = 0'];
        $params = [];

        if ($filter && (string) $request->query('filter') === 'client') {
            $equal = Helpers::extractId($request->query('equal'));
            if ($equal) {
                $where[] = 'q.client_id = ?';
                $params[] = $equal;
            }
        }

        if ($search) {
            $q = trim((string) $request->query('q', ''));
            if ($q !== '') {
                $where[] = '(CAST(q.number AS CHAR) LIKE ? OR c.name LIKE ?)';
                $params[] = '%' . $q . '%';
                $params[] = '%' . $q . '%';
            }
        }

        $sqlWhere = implode(' AND ', $where);
        $countStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM quotes q JOIN clients c ON c.id = q.client_id WHERE {$sqlWhere}"
        );
        $countStmt->execute($params);
        $count = (int) $countStmt->fetchColumn();

        $sql = "SELECT q.id FROM quotes q JOIN clients c ON c.id = q.client_id WHERE {$sqlWhere} ORDER BY q.id DESC";
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
