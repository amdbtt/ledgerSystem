<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Helpers;
use App\Request;
use App\Response;

final class PaymentController
{
    public static function create(Request $request): void
    {
        $pdo = Database::pdo();
        $body = $request->body();

        $invoiceId = Helpers::extractId($body['invoice'] ?? null);
        $clientId = Helpers::extractId($body['client'] ?? null);
        $modeId = Helpers::extractId($body['paymentMode'] ?? null);
        $amount = (float) ($body['amount'] ?? 0);

        if (!$invoiceId || !$modeId || $amount <= 0) {
            Response::fail('invoice, paymentMode, and a positive amount are required');
        }

        $invoice = InvoiceController::readById($invoiceId);
        if (!$invoice) {
            Response::fail('Invoice not found', 404);
        }

        if (!$clientId) {
            $clientId = (int) $invoice['client']['_id'];
        }

        $modeCheck = $pdo->prepare('SELECT id FROM payment_modes WHERE id = ? AND removed = 0');
        $modeCheck->execute([$modeId]);
        if (!$modeCheck->fetch()) {
            Response::fail('Payment mode not found', 404);
        }

        $number = (int) ($body['number'] ?? 0);
        if ($number <= 0) {
            $number = ((int) Helpers::getSetting($pdo, 'last_payment_number', '0')) + 1;
        }
        $year = (int) ($body['year'] ?? date('Y'));
        $date = Helpers::parseDate($body['date'] ?? null) ?? date('Y-m-d');
        $currency = strtoupper((string) ($body['currency'] ?? $invoice['currency']));
        $ref = (string) ($body['ref'] ?? '');
        $description = (string) ($body['description'] ?? '');

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'INSERT INTO payments
                (number, year, client_id, invoice_id, payment_mode_id, date, amount, currency, ref, description)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $number, $year, $clientId, $invoiceId, $modeId, $date, $amount, $currency, $ref, $description,
            ]);
            $id = (int) $pdo->lastInsertId();

            $newCredit = round((float) $invoice['credit'] + $amount, 2);
            $paymentStatus = Helpers::paymentStatus((float) $invoice['total'], (float) $invoice['discount'], $newCredit);
            $status = $paymentStatus === 'paid' ? 'paid' : $invoice['status'];

            $upd = $pdo->prepare(
                'UPDATE invoices SET credit = ?, payment_status = ?, status = ? WHERE id = ?'
            );
            $upd->execute([$newCredit, $paymentStatus, $status, $invoiceId]);

            Helpers::bumpFinanceNumber($pdo, 'last_payment_number', $number);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Response::fail('Could not record payment: ' . $e->getMessage(), 500);
        }

        $result = self::readById($id);
        Response::ok($result, 'Payment recorded');
    }

    public static function read(Request $request, array $params): void
    {
        $row = self::readById((int) $params['id']);
        if (!$row) {
            Response::fail('Payment not found', 404);
        }
        Response::ok($row);
    }

    public static function update(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $existing = self::readById($id);
        if (!$existing) {
            Response::fail('Payment not found', 404);
        }

        $pdo = Database::pdo();
        $body = $request->body();
        $oldAmount = (float) $existing['amount'];
        $newAmount = (float) ($body['amount'] ?? $oldAmount);
        $modeId = Helpers::extractId($body['paymentMode'] ?? $existing['paymentMode']) ?? (int) $existing['paymentMode']['_id'];
        $date = Helpers::parseDate($body['date'] ?? $existing['date']) ?? $existing['date'];
        $number = (int) ($body['number'] ?? $existing['number']);
        $ref = (string) ($body['ref'] ?? $existing['ref'] ?? '');
        $description = (string) ($body['description'] ?? $existing['description'] ?? '');
        $invoiceId = (int) $existing['invoice']['_id'];

        $invoice = InvoiceController::readById($invoiceId);
        if (!$invoice) {
            Response::fail('Invoice not found', 404);
        }

        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare(
                'UPDATE payments SET number = ?, payment_mode_id = ?, date = ?, amount = ?, ref = ?, description = ?
                 WHERE id = ? AND removed = 0'
            );
            $stmt->execute([$number, $modeId, $date, $newAmount, $ref, $description, $id]);

            $newCredit = round((float) $invoice['credit'] - $oldAmount + $newAmount, 2);
            if ($newCredit < 0) {
                $newCredit = 0;
            }
            $paymentStatus = Helpers::paymentStatus((float) $invoice['total'], (float) $invoice['discount'], $newCredit);
            $status = $paymentStatus === 'paid' ? 'paid' : ($invoice['status'] === 'paid' ? 'pending' : $invoice['status']);

            $upd = $pdo->prepare('UPDATE invoices SET credit = ?, payment_status = ?, status = ? WHERE id = ?');
            $upd->execute([$newCredit, $paymentStatus, $status, $invoiceId]);

            Helpers::bumpFinanceNumber($pdo, 'last_payment_number', $number);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Response::fail('Could not update payment: ' . $e->getMessage(), 500);
        }

        Response::ok(self::readById($id), 'Payment updated');
    }

    public static function delete(Request $request, array $params): void
    {
        $id = (int) $params['id'];
        $existing = self::readById($id);
        if (!$existing) {
            Response::fail('Payment not found', 404);
        }

        $pdo = Database::pdo();
        $invoiceId = (int) $existing['invoice']['_id'];
        $invoice = InvoiceController::readById($invoiceId);

        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE payments SET removed = 1 WHERE id = ?')->execute([$id]);
            if ($invoice) {
                $newCredit = max(0, round((float) $invoice['credit'] - (float) $existing['amount'], 2));
                $paymentStatus = Helpers::paymentStatus((float) $invoice['total'], (float) $invoice['discount'], $newCredit);
                $status = $paymentStatus === 'paid' ? 'paid' : ($invoice['status'] === 'paid' ? 'pending' : $invoice['status']);
                $pdo->prepare('UPDATE invoices SET credit = ?, payment_status = ?, status = ? WHERE id = ?')
                    ->execute([$newCredit, $paymentStatus, $status, $invoiceId]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Response::fail('Could not delete payment: ' . $e->getMessage(), 500);
        }

        Response::ok(['_id' => Helpers::id($id)], 'Payment deleted');
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
            "SELECT COALESCE(SUM(amount),0) FROM payments
             WHERE removed = 0 AND currency = ?
             AND YEAR(date) = YEAR(CURDATE()) AND MONTH(date) = MONTH(CURDATE())"
        );
        $totalStmt->execute([$currency]);

        Response::ok([
            'total' => (float) $totalStmt->fetchColumn(),
            'total_undue' => 0,
            'performance' => [],
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
            'SELECT p.*,
                    c.id AS c_id, c.name AS c_name, c.country AS c_country, c.address AS c_address,
                    c.phone AS c_phone, c.email AS c_email,
                    pm.id AS pm_id, pm.name AS pm_name, pm.description AS pm_description,
                    pm.is_default AS pm_default, pm.enabled AS pm_enabled
             FROM payments p
             JOIN clients c ON c.id = p.client_id
             JOIN payment_modes pm ON pm.id = p.payment_mode_id
             WHERE p.id = ? AND p.removed = 0 LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }

        $invoice = InvoiceController::readById((int) $row['invoice_id']);
        if (!$invoice) {
            return null;
        }

        return [
            '_id' => Helpers::id($row['id']),
            'number' => (int) $row['number'],
            'year' => (int) $row['year'],
            'date' => $row['date'],
            'amount' => (float) $row['amount'],
            'currency' => $row['currency'],
            'status' => 'success',
            'ref' => $row['ref'],
            'description' => $row['description'],
            'subTotal' => $invoice['subTotal'],
            'total' => $invoice['total'],
            'credit' => $invoice['credit'],
            'client' => [
                '_id' => Helpers::id($row['c_id']),
                'name' => $row['c_name'],
                'country' => $row['c_country'],
                'address' => $row['c_address'],
                'phone' => $row['c_phone'],
                'email' => $row['c_email'],
            ],
            'invoice' => $invoice,
            'paymentMode' => [
                '_id' => Helpers::id($row['pm_id']),
                'name' => $row['pm_name'],
                'description' => $row['pm_description'],
                'isDefault' => (bool) $row['pm_default'],
                'enabled' => (bool) $row['pm_enabled'],
            ],
        ];
    }

    private static function queryList(Request $request, bool $all, bool $search = false, bool $filter = false): void
    {
        $pdo = Database::pdo();
        $page = max(1, (int) $request->query('page', 1));
        $itemsPerPage = max(1, min(100, (int) $request->query('items', 10)));
        $where = ['p.removed = 0'];
        $params = [];

        if ($filter && (string) $request->query('filter') === 'client') {
            $equal = Helpers::extractId($request->query('equal'));
            if ($equal) {
                $where[] = 'p.client_id = ?';
                $params[] = $equal;
            }
        }

        if ($search) {
            $q = trim((string) $request->query('q', ''));
            if ($q !== '') {
                $where[] = '(CAST(p.number AS CHAR) LIKE ? OR c.name LIKE ?)';
                $params[] = '%' . $q . '%';
                $params[] = '%' . $q . '%';
            }
        }

        $sqlWhere = implode(' AND ', $where);
        $countStmt = $pdo->prepare(
            "SELECT COUNT(*) FROM payments p JOIN clients c ON c.id = p.client_id WHERE {$sqlWhere}"
        );
        $countStmt->execute($params);
        $count = (int) $countStmt->fetchColumn();

        $sql = "SELECT p.id FROM payments p JOIN clients c ON c.id = p.client_id WHERE {$sqlWhere} ORDER BY p.id DESC";
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
