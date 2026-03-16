<?php

declare(strict_types=1);

namespace Plainbooru;

final class RateLimiter
{
    /**
     * Check and increment a rate bucket. Returns true if the request is allowed,
     * false if the limit has been reached for this window.
     *
     * Key pattern: 'upload:user:123', 'comment:ip:1.2.3.4', 'api:token:456'
     *
     * @param string $key           Unique bucket identifier.
     * @param int    $limit         Max hits allowed in the window.
     * @param int    $windowSeconds Length of the window in seconds.
     */
    public static function hit(string $key, int $limit, int $windowSeconds): bool
    {
        $pdo = Db::get();
        $now = gmdate('Y-m-d\TH:i:s\Z');

        $stmt = $pdo->prepare('SELECT count, window_start FROM rate_buckets WHERE key = ?');
        $stmt->execute([$key]);
        $row = $stmt->fetch();

        if (!$row) {
            // First hit — insert
            $pdo->prepare('INSERT INTO rate_buckets (key, count, window_start) VALUES (?, 1, ?)')
                ->execute([$key, $now]);
            return true;
        }

        $windowAge = time() - strtotime($row['window_start']);
        if ($windowAge >= $windowSeconds) {
            // Window expired — reset
            $pdo->prepare('UPDATE rate_buckets SET count = 1, window_start = ? WHERE key = ?')
                ->execute([$now, $key]);
            return true;
        }

        if ((int)$row['count'] >= $limit) {
            return false;
        }

        $pdo->prepare('UPDATE rate_buckets SET count = count + 1 WHERE key = ?')
            ->execute([$key]);
        return true;
    }

    /** Build a bucket key, preferring user ID over IP. */
    public static function key(string $action, ?int $userId): string
    {
        if ($userId !== null) {
            return $action . ':user:' . $userId;
        }
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['REMOTE_ADDR']
            ?? 'unknown';
        // Use only the first IP if X-Forwarded-For contains a chain
        $ip = trim(explode(',', $ip)[0]);
        return $action . ':ip:' . $ip;
    }
}
