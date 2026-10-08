<?php

declare(strict_types=1);

namespace App;

use PDO;

final class Helpers
{
    public static function id(mixed $value): string
    {
        return (string) $value;
    }

    public static function boolish(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            return (int) $value === 1;
        }
        return filter_var((string) $value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function settingValue(string $key, mixed $raw): mixed
    {
        if (in_array($key, ['cent_precision', 'last_invoice_number', 'last_quote_number', 'last_payment_number'], true)) {
            return is_numeric((string) $raw) ? (int) $raw : 0;
        }
        if ($key === 'zero_format') {
            return self::boolish($raw);
        }
        return $raw;
    }

    public static function parseDate(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return date('Y-m-d', (int) $value);
        }
        $ts = strtotime((string) $value);
        return $ts === false ? null : date('Y-m-d', $ts);
    }

    public static function extractId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_array($value)) {
            $value = $value['_id'] ?? $value['id'] ?? null;
        }
        if ($value === null || $value === '') {
            return null;
        }
        return (int) $value;
    }

    public static function mapClient(array $row): array
    {
        return [
            '_id' => self::id($row['id']),
            'name' => $row['name'],
            'country' => $row['country'],
            'address' => $row['address'],
            'phone' => $row['phone'],
            'email' => $row['email'],
        ];
    }

    public static function mapTax(array $row): array
    {
        return [
            '_id' => self::id($row['id']),
            'taxName' => $row['tax_name'],
            'taxValue' => (float) $row['tax_value'],
            'isDefault' => (bool) $row['is_default'],
            'enabled' => (bool) $row['enabled'],
        ];
    }

    public static function mapPaymentMode(array $row): array
    {
        return [
            '_id' => self::id($row['id']),
            'name' => $row['name'],
            'description' => $row['description'],
            'isDefault' => (bool) $row['is_default'],
            'enabled' => (bool) $row['enabled'],
        ];
    }

    public static function mapItem(array $row): array
    {
        return [
            '_id' => self::id($row['id']),
            'itemName' => $row['item_name'],
            'description' => $row['description'],
            'price' => (float) $row['price'],
            'quantity' => (float) $row['quantity'],
            'total' => (float) $row['total'],
        ];
    }

    public static function getSetting(PDO $pdo, string $key, ?string $default = null): ?string
    {
        $stmt = $pdo->prepare('SELECT setting_value FROM settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value === false ? $default : (string) $value;
    }

    public static function setSetting(PDO $pdo, string $key, mixed $value): void
    {
        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        }
        $stmt = $pdo->prepare('UPDATE settings SET setting_value = ? WHERE setting_key = ?');
        $stmt->execute([(string) $value, $key]);
    }

    public static function bumpFinanceNumber(PDO $pdo, string $key, int $number): void
    {
        $current = (int) (self::getSetting($pdo, $key, '0') ?? '0');
        if ($number > $current) {
            self::setSetting($pdo, $key, $number);
        }
    }

    public static function clearDefault(PDO $pdo, string $table, ?int $exceptId = null): void
    {
        if ($exceptId) {
            $stmt = $pdo->prepare("UPDATE {$table} SET is_default = 0 WHERE id <> ? AND removed = 0");
            $stmt->execute([$exceptId]);
            return;
        }
        $pdo->exec("UPDATE {$table} SET is_default = 0 WHERE removed = 0");
    }

    public static function paymentStatus(float $total, float $discount, float $credit): string
    {
        $due = max(0, $total - $discount);
        if ($credit <= 0) {
            return 'unpaid';
        }
        if ($credit + 0.0001 >= $due) {
            return 'paid';
        }
        return 'partially';
    }

    public static function calcTotals(array $items, float $taxRate): array
    {
        $normalized = [];
        $subTotal = 0.0;
        foreach ($items as $item) {
            $price = (float) ($item['price'] ?? 0);
            $qty = (float) ($item['quantity'] ?? 0);
            $lineTotal = round($price * $qty, 2);
            $subTotal += $lineTotal;
            $normalized[] = [
                'itemName' => trim((string) ($item['itemName'] ?? '')),
                'description' => (string) ($item['description'] ?? ''),
                'price' => $price,
                'quantity' => $qty,
                'total' => $lineTotal,
            ];
        }
        $subTotal = round($subTotal, 2);
        $taxTotal = round($subTotal * ($taxRate / 100), 2);
        $total = round($subTotal + $taxTotal, 2);
        return [$normalized, $subTotal, $taxTotal, $total];
    }
}
