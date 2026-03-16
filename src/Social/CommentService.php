<?php

declare(strict_types=1);

namespace Plainbooru\Social;

use Plainbooru\Db;

final class CommentService
{
    private const MAX_BODY_LENGTH = 2000;

    public static function add(int $mediaId, ?int $userId, string $body): array
    {
        $body = trim($body);
        if ($body === '') {
            throw new \RuntimeException('Comment cannot be empty.');
        }
        if (strlen($body) > self::MAX_BODY_LENGTH) {
            throw new \RuntimeException('Comment is too long (max ' . self::MAX_BODY_LENGTH . ' characters).');
        }

        $pdo = Db::get();
        $now = gmdate('Y-m-d\TH:i:s\Z');
        $pdo->prepare(
            'INSERT INTO comments (media_id, user_id, body, created_at) VALUES (?, ?, ?, ?)'
        )->execute([$mediaId, $userId, $body, $now]);

        $id = (int)$pdo->lastInsertId();
        return self::getById($id);
    }

    /** Returns all non-deleted comments for a media item, oldest first. */
    public static function getForMedia(int $mediaId): array
    {
        $stmt = Db::get()->prepare(
            'SELECT c.*, u.username
             FROM comments c
             LEFT JOIN users u ON u.id = c.user_id
             WHERE c.media_id = ? AND c.deleted_at IS NULL
             ORDER BY c.created_at ASC'
        );
        $stmt->execute([$mediaId]);
        return $stmt->fetchAll();
    }

    /**
     * Soft-delete a comment. Ownership rules:
     * - Owner of the comment can delete their own (user_id must match).
     * - Moderators/admins can delete any comment including anonymous ones.
     * - Anonymous comments (user_id IS NULL) require moderator+.
     *
     * @throws \RuntimeException on permission violation or not found.
     */
    public static function delete(int $commentId, array $actor): void
    {
        $comment = self::getById($commentId);
        if (!$comment) {
            throw new \RuntimeException('Comment not found.');
        }

        $actorId   = (int)$actor['id'];
        $actorRole = $actor['role'] ?? 'user';
        $isMod     = in_array($actorRole, ['moderator', 'admin'], true);
        $isOwner   = $comment['user_id'] !== null && (int)$comment['user_id'] === $actorId;

        if (!$isOwner && !$isMod) {
            throw new \RuntimeException('You do not have permission to delete this comment.');
        }

        Db::get()->prepare(
            'UPDATE comments SET deleted_at = ?, deleted_by = ? WHERE id = ?'
        )->execute([gmdate('Y-m-d\TH:i:s\Z'), $actorId, $commentId]);
    }

    public static function getById(int $id): ?array
    {
        $stmt = Db::get()->prepare(
            'SELECT c.*, u.username
             FROM comments c
             LEFT JOIN users u ON u.id = c.user_id
             WHERE c.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }
}
