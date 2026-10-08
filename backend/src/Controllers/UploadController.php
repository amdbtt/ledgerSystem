<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Config;
use App\Database;
use App\Response;

final class UploadController
{
    private const ALLOWED = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    public static function storeImage(array $file, string $model, int $modelId): string
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            Response::fail('File upload failed');
        }

        $max = Config::int('UPLOAD_MAX_BYTES', 2097152);
        if (($file['size'] ?? 0) > $max) {
            Response::fail('File is too large');
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']) ?: '';
        if (!isset(self::ALLOWED[$mime])) {
            Response::fail('Only JPG, PNG, WEBP, or GIF images are allowed');
        }

        $ext = self::ALLOWED[$mime];
        $safeName = $model . '_' . $modelId . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        $dir = dirname(__DIR__, 2) . '/public/uploads';
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            Response::fail('Upload directory is not writable', 500);
        }

        $dest = $dir . '/' . $safeName;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            Response::fail('Could not store uploaded file', 500);
        }

        // Harden permissions.
        chmod($dest, 0644);

        $pdo = Database::pdo();
        $stmt = $pdo->prepare(
            'INSERT INTO uploads (model, model_id, file_name, path) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$model, $modelId, $safeName, 'uploads/' . $safeName]);

        return $safeName;
    }
}
