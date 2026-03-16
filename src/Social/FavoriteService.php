<?php

declare(strict_types=1);

namespace Plainbooru\Social;

use Plainbooru\Db;

final class FavoriteService
{
    /**
     * Toggle favorite. Returns true if now favorited, false if removed.
     */
    public static function toggle(int $userId, int $mediaId): bool
    {
        $pdo  = Db::get();
        $stmt = $pdo->prepare('SELECT 1 FROM favorites WHERE user_id = ? AND media_id = ?');
        $stmt->execute([$userId, $mediaId]);

        if ($stmt->fetch()) {
            $pdo->prepare('DELETE FROM favorites WHERE user_id = ? AND media_id = ?')
                ->execute([$userId, $mediaId]);
            return false;
        }

        $pdo->prepare('INSERT INTO favorites (user_id, media_id, created_at) VALUES (?, ?, ?)')
            ->execute([$userId, $mediaId, gmdate('Y-m-d\TH:i:s\Z')]);
        return true;
    }

    public static function isFavorited(int $userId, int $mediaId): bool
    {
        $stmt = Db::get()->prepare('SELECT 1 FROM favorites WHERE user_id = ? AND media_id = ?');
        $stmt->execute([$userId, $mediaId]);
        return (bool)$stmt->fetch();
    }

    /** Returns paginated favorites for a user as media rows. */
    public static function getForUser(int $userId, int $page, int $pageSize): array
    {
        $pdo    = Db::get();
        $offset = ($page - 1) * $pageSize;

        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM favorites WHERE user_id = ?');
        $countStmt->execute([$userId]);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $pdo->prepare(
            'SELECT m.* FROM media m
             JOIN favorites f ON f.media_id = m.id
             WHERE f.user_id = ?
             ORDER BY f.created_at DESC
             LIMIT ? OFFSET ?'
        );
        $stmt->execute([$userId, $pageSize, $offset]);
        $results = $stmt->fetchAll();

        return ['results' => $results, 'total' => $total];
    }

    public static function countForMedia(int $mediaId): int
    {
        $stmt = Db::get()->prepare('SELECT COUNT(*) FROM favorites WHERE media_id = ?');
        $stmt->execute([$mediaId]);
        return (int)$stmt->fetchColumn();
    }
}
