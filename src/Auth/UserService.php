<?php

declare(strict_types=1);

namespace Plainbooru\Auth;

use Plainbooru\Db;
use Plainbooru\Settings;
use Plainbooru\Auth\ModLog;

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

        if (!Settings::getBool('registration_enabled', true)) {
            throw new \RuntimeException('Registration is currently disabled.');
        }

        $role               = Settings::getString('default_user_role', 'user');
        $requiresModeration = in_array($role, ['admin', 'moderator'], true) ? 0 : 1;

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $now  = gmdate('Y-m-d\TH:i:s\Z');

        $stmt = $pdo->prepare(
            'INSERT INTO users (username, password_hash, role, created_at, requires_moderation) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$username, $hash, $role, $now, $requiresModeration]);

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

        if ($user['banned_at'] !== null) {
            throw new \RuntimeException('This account has been banned.');
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        self::$cachedUser    = self::stripHash($user);

        return self::$cachedUser;
    }

    public static function logout(): void
    {
        $_SESSION         = [];
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
        $user = self::getById((int)$id);
        if ($user === null) {
            return null;
        }
        // If the user was banned since they last logged in, force logout
        if ($user['banned_at'] !== null) {
            self::logout();
            return null;
        }
        self::$cachedUser = $user;
        return self::$cachedUser;
    }

    public static function getById(int $id): ?array
    {
        $pdo  = Db::get();
        $stmt = $pdo->prepare('SELECT id, username, role, bio, created_at, banned_at, ban_reason, requires_moderation FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function setRequiresModeration(int $userId, bool $value): void
    {
        Db::get()->prepare('UPDATE users SET requires_moderation = ? WHERE id = ?')
            ->execute([$value ? 1 : 0, $userId]);
        if (self::$cachedUser !== null && (int)self::$cachedUser['id'] === $userId) {
            self::$cachedUser = null;
        }
    }

    /**
     * Changes the user's password after verifying the current one.
     * Returns true on success, false if the current password is wrong.
     */
    public static function changePassword(int $id, string $currentPassword, string $newPassword): bool
    {
        $pdo  = Db::get();
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row || !password_verify($currentPassword, $row['password_hash'])) {
            return false;
        }
        $hash = password_hash($newPassword, PASSWORD_BCRYPT);
        $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $id]);
        self::$cachedUser = null;
        return true;
    }

    public static function updateBio(int $id, ?string $bio): void
    {
        $bio = ($bio !== null && trim($bio) !== '') ? trim($bio) : null;
        Db::get()->prepare('UPDATE users SET bio = ? WHERE id = ?')->execute([$bio, $id]);
        if (self::$cachedUser !== null && (int)self::$cachedUser['id'] === $id) {
            self::$cachedUser['bio'] = $bio;
        }
    }

    public static function getByUsername(string $username): ?array
    {
        $pdo  = Db::get();
        $stmt = $pdo->prepare('SELECT id, username, role, bio, created_at, banned_at, requires_moderation FROM users WHERE username = ?');
        $stmt->execute([$username]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Change a user's role. Validates role, prevents self-demotion below admin,
     * and prevents removing the last admin account.
     *
     * @throws \RuntimeException on invalid input or policy violation.
     */
    public static function setRole(int $targetId, string $role, array $actor): void
    {
        $validRoles = array_keys(Guard::LEVELS);
        // 'anonymous' is not a stored role
        $storedRoles = array_filter($validRoles, fn($r) => $r !== 'anonymous');
        if (!in_array($role, $storedRoles, true)) {
            throw new \RuntimeException("Invalid role '$role'.");
        }

        // Prevent actor from demoting themselves below admin
        if ($targetId === (int)$actor['id'] && Guard::LEVELS[$role] < Guard::LEVELS['admin']) {
            throw new \RuntimeException('You cannot demote yourself below admin.');
        }

        // Prevent removing the last admin
        if ($role !== 'admin') {
            $pdo = Db::get();
            $adminCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
            $target = self::getById($targetId);
            if ($target && $target['role'] === 'admin' && $adminCount <= 1) {
                throw new \RuntimeException('Cannot remove the last admin account.');
            }
        }

        $pdo = Db::get();
        $pdo->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, $targetId]);

        ModLog::write('user.role', 'user:' . $targetId, (int)$actor['id'], $role);

        // Invalidate cache if this is the current user
        if (self::$cachedUser !== null && (int)self::$cachedUser['id'] === $targetId) {
            self::$cachedUser = null;
        }
    }

    /**
     * Ban a user. Sets banned_at, ban_reason, banned_by.
     *
     * @throws \RuntimeException if trying to ban another admin or self.
     */
    public static function ban(int $targetId, ?string $reason, array $moderator): void
    {
        if ($targetId === (int)$moderator['id']) {
            throw new \RuntimeException('You cannot ban yourself.');
        }

        $target = self::getById($targetId);
        if (!$target) {
            throw new \RuntimeException('User not found.');
        }

        // Only admins can ban admins
        if ($target['role'] === 'admin' && $moderator['role'] !== 'admin') {
            throw new \RuntimeException('Only admins can ban other admins.');
        }

        $pdo = Db::get();
        $pdo->prepare('UPDATE users SET banned_at = ?, ban_reason = ?, banned_by = ? WHERE id = ?')
            ->execute([gmdate('Y-m-d\TH:i:s\Z'), $reason, $moderator['id'], $targetId]);

        ModLog::write('user.ban', 'user:' . $targetId, (int)$moderator['id'], $reason);

        // Invalidate cache if this is the current user
        if (self::$cachedUser !== null && (int)self::$cachedUser['id'] === $targetId) {
            self::$cachedUser = null;
        }
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

    private static function stripHash(array $user): array
    {
        unset($user['password_hash']);
        return $user;
    }
}
