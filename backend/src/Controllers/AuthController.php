<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Database;
use App\Helpers;
use App\Jwt;
use App\Request;
use App\Response;
use App\Config;

final class AuthController
{
    public static function login(Request $request): void
    {
        $email = strtolower(trim((string) $request->body('email', '')));
        $password = (string) $request->body('password', '');

        if ($email === '' || $password === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::fail('Invalid email or password', 401);
        }

        $pdo = Database::pdo();
        $admin = Auth::findByEmail($pdo, $email);
        if (!$admin || !(int) $admin['enabled'] || !password_verify($password, $admin['password_hash'])) {
            // Constant-ish delay against timing probes.
            usleep(200000);
            Response::fail('Invalid email or password', 401);
        }

        $token = Jwt::encode([
            'sub' => (int) $admin['id'],
            'email' => $admin['email'],
            'role' => $admin['role'],
        ]);

        Response::ok(Auth::publicAdmin($admin, $token), 'Login success');
    }

    public static function logout(Request $request): void
    {
        Auth::requireAdmin($request);
        Response::ok([], 'Logout success');
    }

    public static function forgetPassword(Request $request): void
    {
        $email = strtolower(trim((string) $request->body('email', '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::fail('Valid email is required');
        }

        $pdo = Database::pdo();
        $admin = Auth::findByEmail($pdo, $email);

        // Always succeed to avoid email enumeration.
        if ($admin) {
            $raw = bin2hex(random_bytes(32));
            $hash = hash('sha256', $raw);
            $expires = (new \DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s');
            $stmt = $pdo->prepare(
                'INSERT INTO password_resets (admin_id, token_hash, expires_at) VALUES (?, ?, ?)'
            );
            $stmt->execute([(int) $admin['id'], $hash, $expires]);

            // Mail is disabled in v1. Token is returned only when MAIL_ENABLED is false for local testing.
            if (!Config::bool('MAIL_ENABLED', false)) {
                Response::ok(['resetToken' => $raw], 'Password reset token created (mail disabled)');
            }
        }

        Response::ok([], 'If the account exists, a reset email was sent');
    }

    public static function resetPassword(Request $request): void
    {
        $token = (string) $request->body('token', $request->body('emailToken', ''));
        $password = (string) $request->body('password', '');
        $userId = Helpers::extractId($request->body('userId', $request->body('id')));

        if ($token === '' || strlen($password) < 6) {
            Response::fail('Token and a password of at least 6 characters are required');
        }

        $pdo = Database::pdo();
        $hash = hash('sha256', $token);
        $stmt = $pdo->prepare(
            'SELECT pr.*, a.email, a.name, a.surname, a.photo, a.role, a.enabled, a.removed
             FROM password_resets pr
             JOIN admins a ON a.id = pr.admin_id
             WHERE pr.token_hash = ? AND pr.used_at IS NULL
             LIMIT 1'
        );
        $stmt->execute([$hash]);
        $row = $stmt->fetch();

        if (!$row || ($userId && (int) $row['admin_id'] !== $userId)) {
            Response::fail('Invalid or expired reset token', 400);
        }

        if (strtotime((string) $row['expires_at']) < time()) {
            Response::fail('Invalid or expired reset token', 400);
        }

        if ((int) $row['removed'] === 1 || (int) $row['enabled'] !== 1) {
            Response::fail('Account is disabled', 403);
        }

        $pdo->beginTransaction();
        try {
            $update = $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?');
            $update->execute([password_hash($password, PASSWORD_BCRYPT), (int) $row['admin_id']]);

            $mark = $pdo->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?');
            $mark->execute([(int) $row['id']]);
            $pdo->commit();
        } catch (\Throwable $e) {
            $pdo->rollBack();
            Response::fail('Could not reset password', 500);
        }

        $admin = [
            'id' => $row['admin_id'],
            'name' => $row['name'],
            'surname' => $row['surname'],
            'email' => $row['email'],
            'photo' => $row['photo'],
            'role' => $row['role'],
        ];
        $jwt = Jwt::encode(['sub' => (int) $admin['id'], 'email' => $admin['email'], 'role' => $admin['role']]);
        Response::ok(Auth::publicAdmin($admin, $jwt), 'Password updated');
    }

    public static function updateProfile(Request $request): void
    {
        $admin = Auth::requireAdmin($request);
        $pdo = Database::pdo();

        $name = trim((string) $request->body('name', $admin['name']));
        $surname = trim((string) $request->body('surname', $admin['surname']));
        $email = strtolower(trim((string) $request->body('email', $admin['email'])));

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            Response::fail('Name and valid email are required');
        }

        $check = $pdo->prepare('SELECT id FROM admins WHERE email = ? AND id <> ? LIMIT 1');
        $check->execute([$email, (int) $admin['id']]);
        if ($check->fetch()) {
            Response::fail('Email already in use');
        }

        $photo = $admin['photo'];
        $files = $request->files();
        if (!empty($files['file']['tmp_name'])) {
            $photo = UploadController::storeImage($files['file'], 'admin', (int) $admin['id']);
        }

        $stmt = $pdo->prepare('UPDATE admins SET name = ?, surname = ?, email = ?, photo = ? WHERE id = ?');
        $stmt->execute([$name, $surname, $email, $photo, (int) $admin['id']]);

        $fresh = $pdo->prepare('SELECT id, name, surname, email, photo, role FROM admins WHERE id = ?');
        $fresh->execute([(int) $admin['id']]);
        $row = $fresh->fetch();
        Response::ok(Auth::publicAdmin($row), 'Profile updated');
    }

    public static function updatePassword(Request $request): void
    {
        $admin = Auth::requireAdmin($request);
        $password = (string) $request->body('password', '');
        $passwordCheck = (string) $request->body('passwordCheck', $request->body('confirm_password', ''));

        if (strlen($password) < 6) {
            Response::fail('Password must be at least 6 characters');
        }
        if ($passwordCheck !== '' && $password !== $passwordCheck) {
            Response::fail('Passwords do not match');
        }

        $pdo = Database::pdo();
        $stmt = $pdo->prepare('UPDATE admins SET password_hash = ? WHERE id = ?');
        $stmt->execute([password_hash($password, PASSWORD_BCRYPT), (int) $admin['id']]);
        Response::ok([], 'Password updated');
    }
}
