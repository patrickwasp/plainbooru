<?php

declare(strict_types=1);

namespace Plainbooru\Auth;

use Plainbooru\Settings;

/**
 * Centralised permission checks.
 *
 * The can*() methods check the anon_can_* settings for anonymous users
 * and require login for authenticated users (role >= user).
 */
final class Policy
{
    // ── Action checks ────────────────────────────────────────────────────────

    public static function canUpload(?array $user): bool
    {
        if ($user !== null) {
            return Guard::atLeast('user', $user);
        }
        return Settings::getBool('anon_can_upload', false);
    }

    public static function canComment(?array $user): bool
    {
        if ($user !== null) {
            return Guard::atLeast('user', $user);
        }
        return Settings::getBool('anon_can_comment', false);
    }

    public static function canVote(?array $user): bool
    {
        if ($user !== null) {
            return Guard::atLeast('user', $user);
        }
        return Settings::getBool('anon_can_vote', false);
    }

    public static function canCreatePool(?array $user): bool
    {
        if ($user !== null) {
            return Guard::atLeast('user', $user);
        }
        return Settings::getBool('anon_can_create_pool', false);
    }

    public static function canEditTags(?array $user): bool
    {
        if ($user !== null) {
            return Guard::atLeast('user', $user);
        }
        return Settings::getBool('anon_can_edit_tags', false);
    }

    public static function canModerate(?array $user): bool
    {
        return Guard::atLeast('moderator', $user);
    }

    // ── Ownership-aware checks ───────────────────────────────────────────────

    public static function isOwner(int|string|null $ownerId, ?array $user): bool
    {
        if ($ownerId === null || $user === null) {
            return false;
        }
        return (int)$ownerId === (int)$user['id'];
    }

    /**
     * Passes if the user is the owner OR meets the role threshold.
     *
     * @throws \RuntimeException with code 403 on failure.
     */
    public static function requireOwnerOrAtLeast(string $role, int|string|null $ownerId, ?array $user): void
    {
        if (!self::isOwner($ownerId, $user) && !Guard::atLeast($role, $user)) {
            throw new \RuntimeException('Forbidden.', 403);
        }
    }
}
