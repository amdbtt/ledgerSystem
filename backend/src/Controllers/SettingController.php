<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Helpers;
use App\Request;
use App\Response;

final class SettingController
{
    public static function listAll(Request $request): void
    {
        $pdo = Database::pdo();
        $rows = $pdo->query(
            'SELECT setting_category, setting_key, setting_value FROM settings ORDER BY id ASC'
        )->fetchAll();

        $result = array_map(static function (array $row): array {
            return [
                'settingCategory' => $row['setting_category'],
                'settingKey' => $row['setting_key'],
                'settingValue' => Helpers::settingValue($row['setting_key'], $row['setting_value']),
            ];
        }, $rows);

        Response::ok($result);
    }

    public static function updateBySettingKey(Request $request, array $params): void
    {
        $key = $params['key'] ?? '';
        if ($key === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $key)) {
            Response::fail('Invalid setting key');
        }

        $value = $request->body('settingValue', $request->body($key));
        if ($value === null) {
            $body = $request->body();
            unset($body['settingKey'], $body['settingCategory']);
            $value = count($body) === 1 ? reset($body) : ($body['settingValue'] ?? null);
        }

        if ($value === null) {
            Response::fail('settingValue is required');
        }

        $pdo = Database::pdo();
        $exists = $pdo->prepare('SELECT id FROM settings WHERE setting_key = ? LIMIT 1');
        $exists->execute([$key]);
        if (!$exists->fetch()) {
            Response::fail('Setting not found', 404);
        }

        if (is_bool($value)) {
            $value = $value ? 'true' : 'false';
        }

        $stmt = $pdo->prepare('UPDATE settings SET setting_value = ? WHERE setting_key = ?');
        $stmt->execute([(string) $value, $key]);

        Response::ok([
            'settingKey' => $key,
            'settingValue' => Helpers::settingValue($key, $value),
        ], 'Setting updated');
    }

    public static function updateMany(Request $request): void
    {
        $settings = $request->body('settings', []);
        if (!is_array($settings) || $settings === []) {
            // Accept flat object from some forms.
            $settings = [];
            foreach ($request->body() as $key => $value) {
                if (is_string($key) && $key !== 'settings') {
                    $settings[] = ['settingKey' => $key, 'settingValue' => $value];
                }
            }
        }

        if ($settings === []) {
            Response::fail('settings array is required');
        }

        $pdo = Database::pdo();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('UPDATE settings SET setting_value = ? WHERE setting_key = ?');
            foreach ($settings as $item) {
                $key = (string) ($item['settingKey'] ?? '');
                if ($key === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $key)) {
                    throw new \InvalidArgumentException('Invalid setting key');
                }
                $value = $item['settingValue'] ?? null;
                if (is_bool($value)) {
                    $value = $value ? 'true' : 'false';
                }
                $stmt->execute([(string) $value, $key]);
            }
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Response::fail($e->getMessage() ?: 'Could not update settings');
        }

        self::listAll($request);
    }

    public static function upload(Request $request, array $params): void
    {
        $key = $params['key'] ?? 'company_logo';
        if ($key === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $key)) {
            Response::fail('Invalid setting key');
        }

        $files = $request->files();
        if (empty($files['file']['tmp_name'])) {
            Response::fail('file is required');
        }

        $pdo = Database::pdo();
        $exists = $pdo->prepare('SELECT id FROM settings WHERE setting_key = ? LIMIT 1');
        $exists->execute([$key]);
        $row = $exists->fetch();
        if (!$row) {
            Response::fail('Setting not found', 404);
        }

        $fileName = UploadController::storeImage($files['file'], 'setting', (int) $row['id']);
        Helpers::setSetting($pdo, $key, $fileName);

        Response::ok([
            'settingKey' => $key,
            'settingValue' => $fileName,
        ], 'File uploaded');
    }
}
