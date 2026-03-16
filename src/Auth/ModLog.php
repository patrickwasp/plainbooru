<?php

declare(strict_types=1);

namespace Plainbooru\Auth;

use Plainbooru\Db;

final class ModLog
{
    /**
     * Write a moderation log entry.
     *
     * @param string      $action  e.g. 'user.ban', 'user.role', 'media.delete', 'comment.delete'
     * @param string      $target  e.g. 'user:42', 'media:17', 'comment:5'
     * @param int|null    $modId   The acting moderator's user ID.
     * @param string|null $details Optional JSON-ish context string (ban reason, old role, etc.)
     */
    public static function write(string $action, string $target, ?int $modId, ?string $details = null): void
    {
        Db::get()->prepare(
            'INSERT INTO mod_log (mod_id, action, target, details, created_at) VALUES (?, ?, ?, ?, ?)'
        )->execute([$modId, $action, $target, $details, gmdate('Y-m-d\TH:i:s\Z')]);
    }

    /** Returns recent log entries, newest first. */
    public static function recent(int $limit = 100, int $offset = 0): array
    {
        $stmt = Db::get()->prepare(
            'SELECT ml.*, u.username AS mod_username
             FROM mod_log ml
             LEFT JOIN users u ON u.id = ml.mod_id
             ORDER BY ml.created_at DESC
             LIMIT ? OFFSET ?'
        );
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll();
    }
}
