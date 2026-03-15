<?php

declare(strict_types=1);

namespace Plainbooru\Auth;

use Plainbooru\Db;

final class UserService
{
    private static ?array $cachedUser = null;

    public static function register(string $username, string $password): array
    {
        self::validateUsername($username);
        self::validatePassword($password);

        $pdo  = Db::get();
        $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            throw new \RuntimeException('Username already taken.');
        }

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $now  = gmdate('Y-m-d\TH:i:s\Z');

        $stmt = $pdo->prepare(
            'INSERT INTO users (username, password_hash, role, created_at) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$username, $hash, 'user', $now]);

        return self::getById((int)$pdo->lastInsertId());
    }

    public static function login(string $username, string $password): array
    {
        $pdo  = Db::get();
        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            throw new \RuntimeException('Invalid username or password.');
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        self::$cachedUser    = $user;

        return $user;
    }

    public static function logout(): void
    {
        $_SESSION     = [];
        self::$cachedUser = null;
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    /** Returns the logged-in user row (minus password_hash), or null. */
    public static function current(): ?array
    {
        if (self::$cachedUser !== null) {
            return self::$cachedUser;
        }
        $id = $_SESSION['user_id'] ?? null;
        if ($id === null) {
            return null;
        }
        self::$cachedUser = self::getById((int)$id);
        return self::$cachedUser;
    }

    public static function getById(int $id): ?array
    {
        $pdo  = Db::get();
        $stmt = $pdo->prepare('SELECT id, username, role, created_at FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    // ── Validation ───────────────────────────────────────────────────────────

    private static function validateUsername(string $username): void
    {
        if (strlen($username) < 3 || strlen($username) > 30) {
            throw new \RuntimeException('Username must be 3–30 characters.');
        }
        if (!preg_match('/^[a-zA-Z0-9_-]+$/', $username)) {
            throw new \RuntimeException('Username may only contain letters, numbers, underscores, and hyphens.');
        }
    }

    private static function validatePassword(string $password): void
    {
        if (strlen($password) < 8) {
            throw new \RuntimeException('Password must be at least 8 characters.');
        }
    }
}
