<?php

declare(strict_types=1);

namespace Plainbooru\Auth;

use Plainbooru\Db;

final class TokenService
{
    private const PREFIX         = 'pbt_';
    private const LAST_USED_GRACE = 300; // seconds before updating last_used_at

    /**
     * Generate a new token for a user. Returns the raw token (shown once).
     * Stores only the SHA-256 hash.
     */
    public static function generate(int $userId, string $label): string
    {
        $label = trim($label);
        if ($label === '') {
            throw new \RuntimeException('Token label cannot be empty.');
        }

        $raw   = self::PREFIX . bin2hex(random_bytes(32));
        $hash  = hash('sha256', $raw);
        $now   = gmdate('Y-m-d\TH:i:s\Z');

        Db::get()->prepare(
            'INSERT INTO api_tokens (user_id, token_hash, label, created_at) VALUES (?, ?, ?, ?)'
        )->execute([$userId, $hash, $label, $now]);

        return $raw;
    }

    /**
     * Verify a raw bearer token. Returns the associated user row (minus password_hash) or null.
     * Updates last_used_at at most once per LAST_USED_GRACE seconds.
     */
    public static function verify(string $rawToken): ?array
    {
        if (!str_starts_with($rawToken, self::PREFIX)) {
            return null;
        }

        $hash = hash('sha256', $rawToken);
        $pdo  = Db::get();
        $stmt = $pdo->prepare(
            'SELECT t.id AS token_id, t.last_used_at,
                    u.id, u.username, u.role, u.bio, u.created_at, u.banned_at
             FROM api_tokens t
             JOIN users u ON u.id = t.user_id
             WHERE t.token_hash = ?'
        );
        $stmt->execute([$hash]);
        $row = $stmt->fetch();

        if (!$row || $row['banned_at'] !== null) {
            return null;
        }

        // Touch last_used_at if stale
        $lastUsed = $row['last_used_at'] ? strtotime($row['last_used_at']) : 0;
        if (time() - $lastUsed > self::LAST_USED_GRACE) {
            $pdo->prepare('UPDATE api_tokens SET last_used_at = ? WHERE id = ?')
                ->execute([gmdate('Y-m-d\TH:i:s\Z'), $row['token_id']]);
        }

        // Return a user-shaped array (matches UserService::current() shape)
        return [
            'id'         => $row['id'],
            'username'   => $row['username'],
            'role'       => $row['role'],
            'bio'        => $row['bio'],
            'created_at' => $row['created_at'],
            'banned_at'  => $row['banned_at'],
        ];
    }

    /** Revoke a token. Enforces that the token belongs to $userId. */
    public static function revoke(int $tokenId, int $userId): void
    {
        $pdo  = Db::get();
        $stmt = $pdo->prepare('SELECT user_id FROM api_tokens WHERE id = ?');
        $stmt->execute([$tokenId]);
        $row = $stmt->fetch();

        if (!$row || (int)$row['user_id'] !== $userId) {
            throw new \RuntimeException('Token not found.');
        }

        $pdo->prepare('DELETE FROM api_tokens WHERE id = ?')->execute([$tokenId]);
    }

    /** List all tokens for a user (hashes never exposed). */
    public static function listForUser(int $userId): array
    {
        $stmt = Db::get()->prepare(
            'SELECT id, label, last_used_at, created_at
             FROM api_tokens
             WHERE user_id = ?
             ORDER BY created_at DESC'
        );
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }
}
