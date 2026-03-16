<?php

declare(strict_types=1);

namespace Plainbooru\Social;

use Plainbooru\Db;

final class VoteService
{
    /**
     * Cast or toggle a vote. Rules:
     * - Re-clicking the same value removes the vote.
     * - Clicking the opposite value flips to -1 or +1.
     *
     * @param int $value Must be 1 or -1.
     */
    public static function cast(int $userId, int $mediaId, int $value): void
    {
        if ($value !== 1 && $value !== -1) {
            return;
        }

        $pdo  = Db::get();
        $stmt = $pdo->prepare('SELECT value FROM votes WHERE user_id = ? AND media_id = ?');
        $stmt->execute([$userId, $mediaId]);
        $existing = $stmt->fetchColumn();

        if ($existing === false) {
            // No vote yet — insert
            $pdo->prepare('INSERT INTO votes (user_id, media_id, value, created_at) VALUES (?, ?, ?, ?)')
                ->execute([$userId, $mediaId, $value, gmdate('Y-m-d\TH:i:s\Z')]);
        } elseif ((int)$existing === $value) {
            // Same value — remove (toggle off)
            $pdo->prepare('DELETE FROM votes WHERE user_id = ? AND media_id = ?')
                ->execute([$userId, $mediaId]);
        } else {
            // Opposite value — flip
            $pdo->prepare('UPDATE votes SET value = ? WHERE user_id = ? AND media_id = ?')
                ->execute([$value, $userId, $mediaId]);
        }
    }

    /** Sum of all vote values for a media item. */
    public static function score(int $mediaId): int
    {
        $stmt = Db::get()->prepare('SELECT COALESCE(SUM(value), 0) FROM votes WHERE media_id = ?');
        $stmt->execute([$mediaId]);
        return (int)$stmt->fetchColumn();
    }

    /** Returns the current user's vote value (1, -1, or null if none). */
    public static function userVote(int $userId, int $mediaId): ?int
    {
        $stmt = Db::get()->prepare('SELECT value FROM votes WHERE user_id = ? AND media_id = ?');
        $stmt->execute([$userId, $mediaId]);
        $val = $stmt->fetchColumn();
        return $val !== false ? (int)$val : null;
    }
}
