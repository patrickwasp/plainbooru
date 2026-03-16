<?php

declare(strict_types=1);

namespace Plainbooru\Auth;

final class Guard
{
    public const LEVELS = [
        'anonymous' => 0,
        'user'      => 1,
        'trusted'   => 2,
        'moderator' => 3,
        'admin'     => 4,
    ];

    public static function level(?array $user): int
    {
        if ($user === null) {
            return self::LEVELS['anonymous'];
        }
        return self::LEVELS[$user['role'] ?? ''] ?? self::LEVELS['user'];
    }

    public static function atLeast(string $role, ?array $user): bool
    {
        return self::level($user) >= (self::LEVELS[$role] ?? 0);
    }

    /** @throws \RuntimeException with code 403 if the user does not meet the role threshold. */
    public static function requireAtLeast(string $role, ?array $user): void
    {
        if (!self::atLeast($role, $user)) {
            throw new \RuntimeException('Forbidden.', 403);
        }
    }
}
