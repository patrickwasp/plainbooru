<?php

declare(strict_types=1);

namespace Plainbooru\Install;

use Plainbooru\Config;
use Plainbooru\Db;

final class InstallService
{
    private const LOCK_FILE = '/data/installed.lock';

    public static function isInstalled(): bool
    {
        return file_exists(Config::rootPath() . self::LOCK_FILE);
    }

    /**
     * Run the installer with validated params.
     *
     * @param array{
     *   admin_user: string,
     *   admin_pass: string,
     *   admin_email?: string,
     *   site_title?: string,
     * } $params
     * @throws \RuntimeException on validation failure or already-installed.
     */
    public static function run(array $params): void
    {
        if (self::isInstalled()) {
            throw new \RuntimeException('Already installed.');
        }

        $errors = self::validate($params);
        if (!empty($errors)) {
            throw new \RuntimeException(implode("\n", $errors));
        }

        $root      = Config::rootPath();
        $username  = trim($params['admin_user']);
        $password  = $params['admin_pass'];
        $siteTitle = trim($params['site_title'] ?? 'plainbooru');

        // Ensure required directories exist
        foreach (['/data', '/storage/uploads', '/storage/thumbs'] as $dir) {
            if (!is_dir($root . $dir)) {
                mkdir($root . $dir, 0775, true);
            }
        }

        // Initialize DB and run migrations
        $pdo = Db::get();

        // Verify username not already taken (idempotency guard)
        $check = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $check->execute([$username]);
        if ($check->fetch()) {
            throw new \RuntimeException('An admin user with that username already exists.');
        }

        // Create admin user
        $hash = password_hash($password, PASSWORD_BCRYPT);
        $now  = gmdate('Y-m-d\TH:i:s\Z');
        $pdo->prepare('INSERT INTO users (username, password_hash, role, created_at) VALUES (?, ?, ?, ?)')
            ->execute([$username, $hash, 'admin', $now]);

        // Set site title if non-default
        if ($siteTitle !== '' && $siteTitle !== 'plainbooru') {
            $pdo->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)')
                ->execute(['site_title', $siteTitle]);
        }

        // Write install lock
        file_put_contents($root . self::LOCK_FILE, date('Y-m-d H:i:s') . "\n");
    }

    /**
     * Validate installer params. Returns list of error strings (empty = valid).
     */
    public static function validate(array $params): array
    {
        $errors   = [];
        $username = trim($params['admin_user'] ?? '');
        $password = $params['admin_pass'] ?? '';

        if ($username === '') {
            $errors[] = 'Admin username is required.';
        } elseif (strlen($username) < 3 || strlen($username) > 30) {
            $errors[] = 'Admin username must be 3–30 characters.';
        } elseif (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
            $errors[] = 'Admin username may only contain letters, numbers, underscores, and hyphens.';
        }

        if ($password === '') {
            $errors[] = 'Admin password is required.';
        } elseif (strlen($password) < 8) {
            $errors[] = 'Admin password must be at least 8 characters.';
        }

        return $errors;
    }
}
