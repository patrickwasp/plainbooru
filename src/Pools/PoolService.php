<?php

declare(strict_types=1);

namespace Plainbooru\Pools;

use Plainbooru\Db;
use Plainbooru\Media\MediaService;

final class PoolService
{
    public static function create(string $name, ?string $description = null, ?int $creatorId = null, string $visibility = 'public'): array
    {
        $name       = trim($name);
        $visibility = self::normaliseVisibility($visibility);
        if ($name === '') {
            throw new \RuntimeException('Pool name cannot be empty.');
        }
        $pdo = Db::get();
        $now = gmdate('Y-m-d\TH:i:s\Z');
        $pdo->prepare('INSERT INTO pools (name, description, creator_id, visibility, created_at) VALUES (?, ?, ?, ?, ?)')
            ->execute([$name, $description, $creatorId, $visibility, $now]);
        return self::getById((int)$pdo->lastInsertId());
    }

    /**
     * Load a pool. Pass $viewerUserId to enforce private visibility:
     * private pools are only visible to their creator, moderators, and admins.
     */
    public static function getById(int $id, ?int $viewerUserId = null, string $viewerRole = 'anonymous'): ?array
    {
        $pdo  = Db::get();
        $stmt = $pdo->prepare('SELECT * FROM pools WHERE id = ?');
        $stmt->execute([$id]);
        $pool = $stmt->fetch();
        if (!$pool) {
            return null;
        }
        if (!self::canView($pool, $viewerUserId, $viewerRole)) {
            return null;
        }
        $pool['items'] = self::getItems($id);
        $pool['tags']  = self::getTags($id);
        return $pool;
    }

    public static function getList(int $page = 1, int $pageSize = 20, ?int $viewerUserId = null, string $viewerRole = 'anonymous'): array
    {
        $pdo    = Db::get();
        $offset = ($page - 1) * $pageSize;

        $canSeePrivate = in_array($viewerRole, ['moderator', 'admin'], true);

        // Visibility filter: public OR (private AND viewer is owner)
        if ($canSeePrivate) {
            $visFilter  = '1=1';
            $bindParams = [];
        } elseif ($viewerUserId !== null) {
            $visFilter  = "(p.visibility = 'public' OR p.creator_id = :uid)";
            $bindParams = [':uid' => $viewerUserId];
        } else {
            $visFilter  = "p.visibility = 'public'";
            $bindParams = [];
        }

        $sql = <<<SQL
            SELECT p.*,
                   (SELECT COUNT(*) FROM pool_items pi WHERE pi.pool_id = p.id) AS items_count,
                   (SELECT pi2.media_id FROM pool_items pi2 WHERE pi2.pool_id = p.id ORDER BY pi2.position ASC LIMIT 1) AS first_media_id,
                   (SELECT GROUP_CONCAT(name, ',') FROM (SELECT t.name FROM tags t JOIN pool_tags pt ON pt.tag_id = t.id WHERE pt.pool_id = p.id ORDER BY t.name LIMIT 3) sub) AS top_tags
            FROM pools p
            WHERE $visFilter
            ORDER BY p.created_at DESC
            LIMIT :lim OFFSET :off
        SQL;
        $stmt = $pdo->prepare($sql);
        foreach ($bindParams as $k => $v) {
            $stmt->bindValue($k, $v, \PDO::PARAM_INT);
        }
        $stmt->bindValue(':lim', $pageSize, \PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $countSql  = "SELECT COUNT(*) FROM pools p WHERE $visFilter";
        $countStmt = $pdo->prepare($countSql);
        foreach ($bindParams as $k => $v) {
            $countStmt->bindValue($k, $v, \PDO::PARAM_INT);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        return ['total' => $total, 'results' => $rows, 'page' => $page, 'page_size' => $pageSize];
    }

    public static function addItem(int $poolId, int $mediaId, ?int $position = null): bool
    {
        $pdo = Db::get();
        // Check pool & media exist
        $pool  = $pdo->prepare('SELECT id FROM pools WHERE id = ?');
        $pool->execute([$poolId]);
        if (!$pool->fetch()) {
            throw new \RuntimeException('Pool not found.');
        }
        $media = $pdo->prepare('SELECT id FROM media WHERE id = ?');
        $media->execute([$mediaId]);
        if (!$media->fetch()) {
            throw new \RuntimeException('Media not found.');
        }

        // Check if already in pool
        $exists = $pdo->prepare('SELECT 1 FROM pool_items WHERE pool_id = ? AND media_id = ?');
        $exists->execute([$poolId, $mediaId]);
        if ($exists->fetchColumn()) {
            return true; // already added
        }

        if ($position === null) {
            $maxPos = $pdo->prepare('SELECT COALESCE(MAX(position), -1) FROM pool_items WHERE pool_id = ?');
            $maxPos->execute([$poolId]);
            $position = (int)$maxPos->fetchColumn() + 1;
        }

        $now  = date('c');
        $stmt = $pdo->prepare('INSERT INTO pool_items (pool_id, media_id, position, added_at) VALUES (?, ?, ?, ?)');
        $stmt->execute([$poolId, $mediaId, $position, $now]);
        return true;
    }

    public static function removeItem(int $poolId, int $mediaId): bool
    {
        $pdo  = Db::get();
        $stmt = $pdo->prepare('DELETE FROM pool_items WHERE pool_id = ? AND media_id = ?');
        $stmt->execute([$poolId, $mediaId]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Reorder pool items by providing an ordered list of media IDs.
     */
    public static function reorder(int $poolId, array $mediaIds): bool
    {
        $pdo = Db::get();
        $pdo->beginTransaction();
        try {
            // Clear all positions first using temp negative values to avoid unique constraint
            foreach ($mediaIds as $pos => $mediaId) {
                $pdo->prepare('UPDATE pool_items SET position = ? WHERE pool_id = ? AND media_id = ?')
                    ->execute([-(int)$pos - 1, $poolId, (int)$mediaId]);
            }
            // Now set positive positions
            foreach ($mediaIds as $pos => $mediaId) {
                $pdo->prepare('UPDATE pool_items SET position = ? WHERE pool_id = ? AND media_id = ?')
                    ->execute([(int)$pos, $poolId, (int)$mediaId]);
            }
            $pdo->commit();
            return true;
        } catch (\Throwable $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    public static function getItems(int $poolId): array
    {
        $pdo  = Db::get();
        $stmt = $pdo->prepare(<<<'SQL'
            SELECT pi.position, pi.added_at,
                   m.id, m.kind, m.sha256, m.original_name, m.stored_name,
                   m.mime, m.ext, m.size_bytes, m.width, m.height,
                   m.duration_seconds, m.created_at, m.source
            FROM pool_items pi
            JOIN media m ON m.id = pi.media_id
            WHERE pi.pool_id = ?
            ORDER BY pi.position ASC
        SQL);
        $stmt->execute([$poolId]);
        return $stmt->fetchAll();
    }

    public static function update(int $id, string $name, ?string $description = null, string $visibility = 'public'): array
    {
        $name       = trim($name);
        $visibility = self::normaliseVisibility($visibility);
        if ($name === '') {
            throw new \RuntimeException('Pool name cannot be empty.');
        }
        $pdo = Db::get();
        $pdo->prepare('UPDATE pools SET name = ?, description = ?, visibility = ? WHERE id = ?')
            ->execute([$name, $description ?: null, $visibility, $id]);
        return self::getById($id);
    }

    public static function getTags(int $poolId): array
    {
        $pdo  = Db::get();
        $stmt = $pdo->prepare(<<<'SQL'
            SELECT t.name FROM tags t
            JOIN pool_tags pt ON pt.tag_id = t.id
            WHERE pt.pool_id = ?
            ORDER BY t.name
        SQL);
        $stmt->execute([$poolId]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public static function addTag(int $poolId, string $tagName): void
    {
        $parsed = MediaService::parseTags($tagName);
        if (empty($parsed)) {
            return;
        }
        $tagName = $parsed[0];
        $pdo = Db::get();
        $pdo->prepare('INSERT OR IGNORE INTO tags (name) VALUES (?)')->execute([$tagName]);
        $stmt = $pdo->prepare('SELECT id FROM tags WHERE name = ?');
        $stmt->execute([$tagName]);
        $tagId = (int)$stmt->fetchColumn();
        $pdo->prepare('INSERT OR IGNORE INTO pool_tags (pool_id, tag_id) VALUES (?, ?)')->execute([$poolId, $tagId]);
    }

    public static function removeTag(int $poolId, string $tagName): void
    {
        $pdo = Db::get();
        $pdo->prepare(<<<'SQL'
            DELETE FROM pool_tags
            WHERE pool_id = ?
              AND tag_id = (SELECT id FROM tags WHERE name = ?)
        SQL)->execute([$poolId, $tagName]);
    }

    public static function getPoolsForMedia(int $mediaId): array
    {
        $pdo  = Db::get();
        $stmt = $pdo->prepare(<<<'SQL'
            SELECT p.id, p.name
            FROM pools p
            JOIN pool_items pi ON pi.pool_id = p.id
            WHERE pi.media_id = ?
            ORDER BY p.name ASC
        SQL);
        $stmt->execute([$mediaId]);
        return $stmt->fetchAll();
    }

    public static function canView(array $pool, ?int $viewerUserId, string $viewerRole): bool
    {
        if (($pool['visibility'] ?? 'public') === 'public') {
            return true;
        }
        if (in_array($viewerRole, ['moderator', 'admin'], true)) {
            return true;
        }
        return $viewerUserId !== null && (int)$pool['creator_id'] === $viewerUserId;
    }

    public static function canEdit(array $pool, ?int $viewerUserId, string $viewerRole): bool
    {
        if (in_array($viewerRole, ['moderator', 'admin'], true)) {
            return true;
        }
        // Null creator (old pools) → mod/admin only (already handled above)
        if ($pool['creator_id'] === null) {
            return false;
        }
        return $viewerUserId !== null && (int)$pool['creator_id'] === $viewerUserId;
    }

    private static function normaliseVisibility(string $v): string
    {
        return $v === 'private' ? 'private' : 'public';
    }

    public static function delete(int $id): bool
    {
        $pdo  = Db::get();
        $stmt = $pdo->prepare('DELETE FROM pools WHERE id = ?');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }
}
