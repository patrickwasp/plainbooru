<?php

declare(strict_types=1);

namespace Plainbooru\Media;

use Plainbooru\Config;
use Plainbooru\Db;
use Plainbooru\Settings;

final class MediaService
{
    /**
     * Store a file that's already been moved to $tmpPath (after PSR-7 moveTo).
     */
    public static function storeFromPath(string $tmpPath, array $fileInfo, string $tags = '', ?int $uploaderId = null, bool $requiresModeration = false): array
    {
        $size = filesize($tmpPath);
        if ($size === false || $size === 0) {
            throw new \RuntimeException('File is empty.');
        }
        $maxBytes = Settings::getInt('max_upload_mb', 50) * 1024 * 1024;
        if ($size > $maxBytes) {
            throw new \RuntimeException('File exceeds maximum upload size of ' . Settings::getInt('max_upload_mb', 50) . 'MB.');
        }

        $origName = basename($fileInfo['name'] ?? 'upload');

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($tmpPath);

        $allowedMimes = Settings::getCsv('allowed_mime_types', array_keys(Mime::all()));
        if (!in_array($mime, $allowedMimes, true) || !Mime::isAllowed($mime)) {
            throw new \RuntimeException("File type '$mime' is not allowed.");
        }

        $kind = Mime::kind($mime);
        $ext  = Mime::ext($mime);

        $width  = null;
        $height = null;
        if ($kind === 'image') {
            $info = @getimagesize($tmpPath);
            if (!$info) {
                throw new \RuntimeException('Cannot read image data. File may be corrupt.');
            }
            $width  = $info[0];
            $height = $info[1];
        }

        $sha256 = hash_file('sha256', $tmpPath);
        $pdo    = Db::get();

        $existing = $pdo->prepare('SELECT * FROM media WHERE sha256 = ?');
        $existing->execute([$sha256]);
        $row = $existing->fetch();
        if ($row) {
            return self::addTags($row, $tags);
        }

        $storedName = $sha256 . '.' . $ext;
        $destPath   = Config::uploadsPath() . '/' . $storedName;
        if (!copy($tmpPath, $destPath)) {
            throw new \RuntimeException('Failed to store uploaded file.');
        }

        $duration = null;
        if ($kind === 'video') {
            $duration = self::extractDuration($destPath);
        }

        $animated  = ($kind === 'image' && $ext === 'gif' && self::isAnimatedGif($destPath)) ? 1 : 0;
        $pendingAt = $requiresModeration ? gmdate('Y-m-d\TH:i:s\Z') : null;

        $now  = date('c');
        $stmt = $pdo->prepare(<<<'SQL'
            INSERT INTO media (kind, sha256, original_name, stored_name, mime, ext, size_bytes,
                               width, height, duration_seconds, animated, uploader_id, created_at,
                               pending_at, pending_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        SQL);
        $stmt->execute([$kind, $sha256, $origName, $storedName, $mime, $ext, $size,
                        $width, $height, $duration, $animated, $uploaderId, $now,
                        $pendingAt, $requiresModeration ? $uploaderId : null]);
        $id = (int)$pdo->lastInsertId();

        ThumbService::generate($storedName, $mime, $id);

        $row = $pdo->query("SELECT * FROM media WHERE id = $id")->fetch();
        if ($requiresModeration) {
            // Queue tags for approval rather than applying immediately
            self::queueTags($id, $uploaderId, $tags);
            $row['tags'] = self::getTags($id);
            return $row;
        }
        return self::addTags($row, $tags);
    }

    /**
     * Process an uploaded file, store it, create thumbnail, insert into DB.
     * Returns the media row array.
     * @throws \RuntimeException on validation failure
     */
    public static function store(array $uploadedFile, string $tags = '', ?int $uploaderId = null, bool $requiresModeration = false): array
    {
        // Validate size
        $size = $uploadedFile['size'] ?? 0;
        if ($size === 0) {
            throw new \RuntimeException('File is empty.');
        }
        $maxBytes = Settings::getInt('max_upload_mb', 50) * 1024 * 1024;
        if ($size > $maxBytes) {
            throw new \RuntimeException('File exceeds maximum upload size of ' . Settings::getInt('max_upload_mb', 50) . 'MB.');
        }

        $tmpPath = $uploadedFile['tmp_name'];
        $origName = basename($uploadedFile['name'] ?? 'upload');

        // Detect MIME via finfo
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($tmpPath);

        $allowedMimes = Settings::getCsv('allowed_mime_types', array_keys(Mime::all()));
        if (!in_array($mime, $allowedMimes, true) || !Mime::isAllowed($mime)) {
            throw new \RuntimeException("File type '$mime' is not allowed.");
        }

        $kind = Mime::kind($mime);
        $ext  = Mime::ext($mime);

        // Validate images with getimagesize
        $width  = null;
        $height = null;
        if ($kind === 'image') {
            $info = @getimagesize($tmpPath);
            if (!$info) {
                throw new \RuntimeException('Cannot read image data. File may be corrupt.');
            }
            $width  = $info[0];
            $height = $info[1];
        }

        // Compute SHA-256 and dedupe
        $sha256 = hash_file('sha256', $tmpPath);
        $pdo    = Db::get();

        $existing = $pdo->prepare('SELECT * FROM media WHERE sha256 = ?');
        $existing->execute([$sha256]);
        $row = $existing->fetch();
        if ($row) {
            return self::addTags($row, $tags);
        }

        // Store original file
        $storedName = $sha256 . '.' . $ext;
        $destPath   = Config::uploadsPath() . '/' . $storedName;
        if (!move_uploaded_file($tmpPath, $destPath)) {
            // For API/CLI usage, allow copy too
            if (!copy($tmpPath, $destPath)) {
                throw new \RuntimeException('Failed to store uploaded file.');
            }
        }

        // Duration for video
        $duration = null;
        if ($kind === 'video') {
            $duration = self::extractDuration($destPath);
        }

        $animated  = ($kind === 'image' && $ext === 'gif' && self::isAnimatedGif($destPath)) ? 1 : 0;
        $pendingAt = $requiresModeration ? gmdate('Y-m-d\TH:i:s\Z') : null;

        $now = date('c');
        $stmt = $pdo->prepare(<<<'SQL'
            INSERT INTO media (kind, sha256, original_name, stored_name, mime, ext, size_bytes,
                               width, height, duration_seconds, animated, uploader_id, created_at,
                               pending_at, pending_by)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        SQL);
        $stmt->execute([$kind, $sha256, $origName, $storedName, $mime, $ext, $size,
                        $width, $height, $duration, $animated, $uploaderId, $now,
                        $pendingAt, $requiresModeration ? $uploaderId : null]);
        $id = (int)$pdo->lastInsertId();

        // Generate thumbnail
        ThumbService::generate($storedName, $mime, $id);

        $row = $pdo->query("SELECT * FROM media WHERE id = $id")->fetch();
        if ($requiresModeration) {
            self::queueTags($id, $uploaderId, $tags);
            $row['tags'] = self::getTags($id);
            return $row;
        }
        return self::addTags($row, $tags);
    }

    /**
     * @param bool $includePending  When true, returns the row even if pending_at is set.
     * @param bool $includeDeleted  When true, returns the row even if deleted_at is set.
     */
    public static function getById(int $id, bool $includePending = false, bool $includeDeleted = false): ?array
    {
        $pdo  = Db::get();
        $pendingClause = $includePending ? '' : 'AND pending_at IS NULL';
        $deletedClause = $includeDeleted ? '' : 'AND deleted_at IS NULL';
        $stmt = $pdo->prepare("SELECT * FROM media WHERE id = ? $deletedClause $pendingClause");
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        $row['tags'] = self::getTags($id);
        return $row;
    }

    public static function getList(int $page = 1, int $pageSize = 20): array
    {
        $pdo    = Db::get();
        $offset = ($page - 1) * $pageSize;
        $rows   = $pdo->query("SELECT * FROM media WHERE deleted_at IS NULL AND pending_at IS NULL ORDER BY created_at DESC LIMIT $pageSize OFFSET $offset")->fetchAll();
        $total  = (int)$pdo->query('SELECT COUNT(*) FROM media WHERE deleted_at IS NULL AND pending_at IS NULL')->fetchColumn();
        return ['total' => $total, 'results' => $rows];
    }

    public static function getByUploader(int $userId, int $page = 1, int $pageSize = 20, bool $includePending = false): array
    {
        $pdo    = Db::get();
        $offset = ($page - 1) * $pageSize;
        $pendingClause = $includePending ? '' : 'AND pending_at IS NULL';
        $stmt   = $pdo->prepare("SELECT * FROM media WHERE uploader_id = ? AND deleted_at IS NULL $pendingClause ORDER BY pending_at IS NULL ASC, created_at DESC LIMIT ? OFFSET ?");
        $stmt->execute([$userId, $pageSize, $offset]);
        $rows   = $stmt->fetchAll();
        $cstmt  = $pdo->prepare("SELECT COUNT(*) FROM media WHERE uploader_id = ? AND deleted_at IS NULL $pendingClause");
        $cstmt->execute([$userId]);
        $total  = (int)$cstmt->fetchColumn();
        return ['total' => $total, 'results' => $rows];
    }

    public static function search(string $tags = '', string $q = '', int $page = 1, int $pageSize = 20): array
    {
        $pdo    = Db::get();
        $offset = ($page - 1) * $pageSize;

        $parsed   = self::parseSearchQuery($tags);
        $required = $parsed['required'];
        $excluded = $parsed['excluded'];
        $union    = $parsed['union'];

        $params = [];
        $where  = ['1=1', 'm.deleted_at IS NULL', 'm.pending_at IS NULL'];

        // Required: each token must match (AND). Virtual tags become direct SQL; real tags use subquery.
        foreach ($required as $i => $token) {
            $vsql = self::virtualTagSql($token);
            if ($vsql !== null) {
                $where[] = $vsql;
            } else {
                $name = self::normalizeTagName($token);
                if ($name !== '') {
                    $where[]         = "m.id IN (SELECT mt.media_id FROM media_tags mt
                                                  JOIN tags t ON t.id = mt.tag_id
                                                  WHERE t.name = :req$i AND t.deleted_at IS NULL)";
                    $params[":req$i"] = $name;
                }
            }
        }

        // Union: at least one must match (OR). Mixes virtual SQL and real tag IN().
        if ($union) {
            $unionSqls     = [];
            $realUnionTags = [];
            foreach ($union as $i => $token) {
                $vsql = self::virtualTagSql($token);
                if ($vsql !== null) {
                    $unionSqls[] = $vsql;
                } else {
                    $name = self::normalizeTagName($token);
                    if ($name !== '') {
                        $realUnionTags[":union$i"] = $name;
                    }
                }
            }
            if ($realUnionTags) {
                $in = implode(', ', array_keys($realUnionTags));
                foreach ($realUnionTags as $k => $v) {
                    $params[$k] = $v;
                }
                $unionSqls[] = "m.id IN (SELECT mt.media_id FROM media_tags mt
                                          JOIN tags t ON t.id = mt.tag_id
                                          WHERE t.name IN ($in) AND t.deleted_at IS NULL)";
            }
            if ($unionSqls) {
                $where[] = '(' . implode(' OR ', $unionSqls) . ')';
            }
        }

        // Excluded: must not match (NOT).
        foreach ($excluded as $i => $token) {
            $vsql = self::virtualTagSql($token);
            if ($vsql !== null) {
                $where[] = "NOT ($vsql)";
            } else {
                $name = self::normalizeTagName($token);
                if ($name !== '') {
                    $where[]         = "m.id NOT IN (SELECT mt.media_id FROM media_tags mt
                                                      JOIN tags t ON t.id = mt.tag_id
                                                      WHERE t.name = :exc$i AND t.deleted_at IS NULL)";
                    $params[":exc$i"] = $name;
                }
            }
        }

        if ($q !== '') {
            $where[]      = "m.original_name LIKE :q";
            $params[':q'] = '%' . $q . '%';
        }

        $whereSQL = implode(' AND ', $where);

        $countStmt = $pdo->prepare("SELECT COUNT(*) FROM media m WHERE $whereSQL");
        $countStmt->execute($params);
        $total = (int)$countStmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT m.* FROM media m WHERE $whereSQL ORDER BY m.created_at DESC LIMIT :lim OFFSET :off");
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':lim', $pageSize, \PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        return ['total' => $total, 'results' => $rows, 'page' => $page, 'page_size' => $pageSize];
    }

    /**
     * Parse a search query string into required, excluded, and union token sets.
     * Tokens are returned raw (not normalized) so virtual tags like width:>2000 survive.
     * - plain token → required (AND)
     * - -token      → excluded (NOT)
     * - ~token      → union    (OR)
     */
    public static function parseSearchQuery(string $input): array
    {
        $tokens   = preg_split('/\s+/', trim($input), -1, PREG_SPLIT_NO_EMPTY);
        $required = [];
        $excluded = [];
        $union    = [];

        foreach ($tokens as $token) {
            if (str_starts_with($token, '-')) {
                $raw = substr($token, 1);
                if ($raw !== '') {
                    $excluded[] = $raw;
                }
            } elseif (str_starts_with($token, '~')) {
                $raw = substr($token, 1);
                if ($raw !== '') {
                    $union[] = $raw;
                }
            } else {
                if ($token !== '') {
                    $required[] = $token;
                }
            }
        }

        // If the first token was a plain tag and union tags exist, promote it to union
        // so that "sunset ~dawn ..." means "(sunset OR dawn) AND ..."
        if ($union && $required && $tokens && !str_starts_with($tokens[0], '-')) {
            $first = ltrim($tokens[0], '~');
            if (in_array($first, $required, true)) {
                $required = array_values(array_filter($required, fn($t) => $t !== $first));
                array_unshift($union, $first);
            }
        }

        return [
            'required' => array_unique($required),
            'excluded' => array_unique($excluded),
            'union'    => array_unique($union),
        ];
    }

    /**
     * Returns a SQL expression (using 'm.' prefix) for intrinsic/virtual tags,
     * or null if the token is a regular tag to look up in the tags table.
     */
    private static function virtualTagSql(string $token): ?string
    {
        $t = strtolower($token);

        return match($t) {
            'video'     => "m.kind = 'video'",
            'image'     => "m.kind = 'image'",
            'animated'  => "m.animated = 1",
            'landscape' => "m.width IS NOT NULL AND m.width > m.height",
            'portrait'  => "m.height IS NOT NULL AND m.height > m.width",
            'square'    => "m.width IS NOT NULL AND m.width = m.height",
            'highres'   => "(m.width >= 3000 OR m.height >= 3000)",
            'lowres'    => "(m.width IS NOT NULL AND m.width < 1280 AND m.height < 1280)",
            'short'     => "m.duration_seconds IS NOT NULL AND m.duration_seconds < 10",
            'long'      => "m.duration_seconds IS NOT NULL AND m.duration_seconds > 60",
            default     => self::parseMetaTag($token),
        };
    }

    /**
     * Parses parameterized meta-tags: width:>2000, duration:<30, filesize:>10mb,
     * date:2024, date:>2024-01-01, date:2024-01-01..2024-02-01
     */
    private static function parseMetaTag(string $token): ?string
    {
        if (!preg_match('/^(width|height|duration|filesize|date):(.+)$/i', $token, $m)) {
            return null;
        }

        $field = strtolower($m[1]);
        $value = $m[2];

        $col = match($field) {
            'width'    => 'm.width',
            'height'   => 'm.height',
            'duration' => 'm.duration_seconds',
            'filesize' => 'm.size_bytes',
            'date'     => 'm.created_at',
        };

        // Date: special handling
        if ($field === 'date') {
            $dp = '\d{4}(?:-\d{2}(?:-\d{2})?)?';
            // range: date:2024-01-01..2024-02-01
            if (preg_match("/^($dp)\.\.($dp)$/", $value, $r)) {
                return "$col >= '$r[1]' AND $col <= '$r[2]T23:59:59'";
            }
            // comparison: date:>2024-01-01 or date:<2024-01-01
            if (preg_match("/^([><])($dp)$/", $value, $r)) {
                return "$col $r[1] '$r[2]'";
            }
            // exact prefix: date:2024 or date:2024-01 or date:2024-01-15
            if (preg_match("/^($dp)$/", $value, $r)) {
                return "$col LIKE '$r[1]%'";
            }
            return null;
        }

        // Numeric fields: width, height, duration, filesize
        if (!preg_match('/^([><]=?|=?)(.+)$/', $value, $r)) {
            return null;
        }
        $op  = $r[1] !== '' ? $r[1] : '=';
        $raw = $r[2];

        if ($field === 'filesize') {
            $bytes = self::parseFileSize($raw);
            if ($bytes === null) {
                return null;
            }
            return "$col $op $bytes";
        }

        if (!is_numeric($raw)) {
            return null;
        }
        return "$col $op " . (float)$raw;
    }

    private static function parseFileSize(string $s): ?int
    {
        if (!preg_match('/^(\d+(?:\.\d+)?)(b|kb|mb|gb)?$/i', $s, $m)) {
            return null;
        }
        $n    = (float)$m[1];
        $unit = strtolower($m[2] ?? 'b');
        return (int)match($unit) {
            'kb'    => $n * 1024,
            'mb'    => $n * 1024 ** 2,
            'gb'    => $n * 1024 ** 3,
            default => $n,
        };
    }

    private static function isAnimatedGif(string $path): bool
    {
        // Count Graphic Control Extension markers — one appears per frame in an animated GIF
        $data = file_get_contents($path, false, null, 0, 524288);
        return $data !== false && substr_count($data, "\x21\xF9\x04") > 1;
    }

    public static function getByTag(string $tag, int $page = 1, int $pageSize = 20): array
    {
        return self::search($tag, '', $page, $pageSize);
    }

    public static function getAllTags(): array
    {
        $pdo = Db::get();
        return $pdo->query(<<<'SQL'
            SELECT t.name, COUNT(mt.media_id) AS count
            FROM tags t
            LEFT JOIN (
                SELECT mt2.tag_id, mt2.media_id
                FROM media_tags mt2
                JOIN media m ON m.id = mt2.media_id AND m.deleted_at IS NULL AND m.pending_at IS NULL
            ) mt ON mt.tag_id = t.id
            WHERE t.deleted_at IS NULL
            GROUP BY t.id
            ORDER BY count DESC, t.name ASC
        SQL)->fetchAll();
    }

    public static function delete(int $id, ?int $deletedBy = null): bool
    {
        $pdo  = Db::get();
        $now  = gmdate('Y-m-d\TH:i:s\Z');
        $stmt = $pdo->prepare('UPDATE media SET deleted_at = ?, deleted_by = ? WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$now, $deletedBy, $id]);
        return $stmt->rowCount() > 0;
    }

    public static function restore(int $id): bool
    {
        $pdo  = Db::get();
        $stmt = $pdo->prepare('UPDATE media SET deleted_at = NULL, deleted_by = NULL WHERE id = ? AND deleted_at IS NOT NULL');
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    public static function permanentDelete(int $id): bool
    {
        $pdo  = Db::get();
        $stmt = $pdo->prepare('SELECT * FROM media WHERE id = ?');
        $stmt->execute([$id]);
        $row  = $stmt->fetch();
        if (!$row) {
            return false;
        }
        $pdo->prepare('DELETE FROM media WHERE id = ?')->execute([$id]);

        $upload = Config::uploadsPath() . '/' . $row['stored_name'];
        $thumb  = Config::thumbsPath() . '/' . $id . '.webp';
        foreach ([$upload, $thumb] as $f) {
            if (file_exists($f)) {
                @unlink($f);
            }
        }
        return true;
    }

    // ── Moderation queue ──────────────────────────────────────────────────────

    public static function getPending(int $page = 1, int $pageSize = 20): array
    {
        $pdo    = Db::get();
        $offset = ($page - 1) * $pageSize;
        $stmt   = $pdo->prepare(<<<'SQL'
            SELECT m.*, u.username AS uploader_username
            FROM media m
            LEFT JOIN users u ON u.id = m.uploader_id
            WHERE m.pending_at IS NOT NULL AND m.deleted_at IS NULL
            ORDER BY m.pending_at ASC
            LIMIT ? OFFSET ?
        SQL);
        $stmt->execute([$pageSize, $offset]);
        $rows  = $stmt->fetchAll();
        $total = (int)$pdo->query('SELECT COUNT(*) FROM media WHERE pending_at IS NOT NULL AND deleted_at IS NULL')->fetchColumn();
        return ['total' => $total, 'results' => $rows];
    }

    public static function approvePending(int $id): bool
    {
        $pdo  = Db::get();
        $stmt = $pdo->prepare('UPDATE media SET pending_at = NULL, pending_by = NULL WHERE id = ? AND pending_at IS NOT NULL');
        $stmt->execute([$id]);
        if ($stmt->rowCount() === 0) {
            return false;
        }
        // Apply any queued tags
        $tagStmt = $pdo->prepare('SELECT tag FROM tag_queue WHERE media_id = ?');
        $tagStmt->execute([$id]);
        $row = $pdo->prepare('SELECT * FROM media WHERE id = ?');
        $row->execute([$id]);
        $media = $row->fetch();
        foreach ($tagStmt->fetchAll(\PDO::FETCH_COLUMN) as $tag) {
            self::addTags($media, $tag);
        }
        $pdo->prepare('DELETE FROM tag_queue WHERE media_id = ?')->execute([$id]);
        return true;
    }

    public static function rejectPending(int $id): bool
    {
        $pdo  = Db::get();
        $stmt = $pdo->prepare('SELECT * FROM media WHERE id = ? AND pending_at IS NOT NULL');
        $stmt->execute([$id]);
        $row  = $stmt->fetch();
        if (!$row) {
            return false;
        }
        // Delete file, thumb, and DB row
        $pdo->prepare('DELETE FROM tag_queue WHERE media_id = ?')->execute([$id]);
        $pdo->prepare('DELETE FROM media WHERE id = ?')->execute([$id]);
        $upload   = Config::uploadsPath() . '/' . $row['stored_name'];
        $thumbDir = Config::thumbsPath();
        foreach ([$upload, "$thumbDir/$id.webp", "$thumbDir/$id.jpg"] as $f) {
            if (file_exists($f)) {
                @unlink($f);
            }
        }
        return true;
    }

    public static function getPendingTagsForMedia(int $mediaId): array
    {
        $stmt = Db::get()->prepare('SELECT id, tag, user_id, created_at FROM tag_queue WHERE media_id = ? ORDER BY created_at ASC');
        $stmt->execute([$mediaId]);
        return $stmt->fetchAll();
    }

    public static function approveTagQueue(int $queueId): void
    {
        $pdo  = Db::get();
        $stmt = $pdo->prepare('SELECT * FROM tag_queue WHERE id = ?');
        $stmt->execute([$queueId]);
        $item = $stmt->fetch();
        if (!$item) {
            return;
        }
        $row = $pdo->prepare('SELECT * FROM media WHERE id = ?');
        $row->execute([$item['media_id']]);
        $media = $row->fetch();
        if ($media) {
            self::addTags($media, $item['tag']);
        }
        $pdo->prepare('DELETE FROM tag_queue WHERE id = ?')->execute([$queueId]);
    }

    public static function rejectTagQueue(int $queueId): void
    {
        Db::get()->prepare('DELETE FROM tag_queue WHERE id = ?')->execute([$queueId]);
    }

    public static function getPendingTags(int $page = 1, int $pageSize = 50): array
    {
        $pdo    = Db::get();
        $offset = ($page - 1) * $pageSize;
        $stmt   = $pdo->prepare(<<<'SQL'
            SELECT tq.*, m.original_name, u.username AS submitter_username
            FROM tag_queue tq
            JOIN media m ON m.id = tq.media_id
            LEFT JOIN users u ON u.id = tq.user_id
            ORDER BY tq.created_at ASC
            LIMIT ? OFFSET ?
        SQL);
        $stmt->execute([$pageSize, $offset]);
        $rows  = $stmt->fetchAll();
        $total = (int)$pdo->query('SELECT COUNT(*) FROM tag_queue')->fetchColumn();
        return ['total' => $total, 'results' => $rows];
    }

    // ── End moderation queue ──────────────────────────────────────────────────

    public static function getDeleted(int $page = 1, int $pageSize = 20): array
    {
        $pdo    = Db::get();
        $offset = ($page - 1) * $pageSize;
        $stmt   = $pdo->prepare(<<<'SQL'
            SELECT m.*, u.username AS deleted_by_username
            FROM media m
            LEFT JOIN users u ON u.id = m.deleted_by
            WHERE m.deleted_at IS NOT NULL
            ORDER BY m.deleted_at DESC
            LIMIT ? OFFSET ?
        SQL);
        $stmt->execute([$pageSize, $offset]);
        $rows  = $stmt->fetchAll();
        $total = (int)$pdo->query('SELECT COUNT(*) FROM media WHERE deleted_at IS NOT NULL')->fetchColumn();
        return ['total' => $total, 'results' => $rows];
    }

    public static function removeTag(int $mediaId, string $tagName): void
    {
        $pdo = Db::get();
        $pdo->prepare(<<<'SQL'
            DELETE FROM media_tags
            WHERE media_id = ?
              AND tag_id = (SELECT id FROM tags WHERE name = ?)
        SQL)->execute([$mediaId, $tagName]);
    }

    public static function deleteTag(string $tagName, ?int $deletedBy = null): bool
    {
        $pdo  = Db::get();
        $now  = gmdate('Y-m-d\TH:i:s\Z');
        $stmt = $pdo->prepare('UPDATE tags SET deleted_at = ?, deleted_by = ? WHERE name = ? AND deleted_at IS NULL');
        $stmt->execute([$now, $deletedBy, $tagName]);
        return $stmt->rowCount() > 0;
    }

    public static function restoreTag(string $tagName): bool
    {
        $pdo  = Db::get();
        $stmt = $pdo->prepare('UPDATE tags SET deleted_at = NULL, deleted_by = NULL WHERE name = ? AND deleted_at IS NOT NULL');
        $stmt->execute([$tagName]);
        return $stmt->rowCount() > 0;
    }

    public static function permanentDeleteTag(string $tagName): bool
    {
        $pdo  = Db::get();
        $stmt = $pdo->prepare('DELETE FROM tags WHERE name = ?');
        $stmt->execute([$tagName]);
        return $stmt->rowCount() > 0;
    }

    public static function getDeletedTags(int $page = 1, int $pageSize = 50): array
    {
        $pdo    = Db::get();
        $offset = ($page - 1) * $pageSize;
        $stmt   = $pdo->prepare(<<<'SQL'
            SELECT t.*, u.username AS deleted_by_username,
                   COUNT(mt.media_id) AS media_count
            FROM tags t
            LEFT JOIN users u ON u.id = t.deleted_by
            LEFT JOIN media_tags mt ON mt.tag_id = t.id
            WHERE t.deleted_at IS NOT NULL
            GROUP BY t.id
            ORDER BY t.deleted_at DESC
            LIMIT ? OFFSET ?
        SQL);
        $stmt->execute([$pageSize, $offset]);
        $rows  = $stmt->fetchAll();
        $total = (int)$pdo->query('SELECT COUNT(*) FROM tags WHERE deleted_at IS NOT NULL')->fetchColumn();
        return ['total' => $total, 'results' => $rows];
    }

    public static function getPopularTags(int $limit = 20): array
    {
        $pdo  = Db::get();
        $stmt = $pdo->prepare(<<<'SQL'
            SELECT t.name, COUNT(mt.media_id) AS count
            FROM tags t
            JOIN media_tags mt ON mt.tag_id = t.id
            JOIN media m ON m.id = mt.media_id AND m.deleted_at IS NULL AND m.pending_at IS NULL
            WHERE t.deleted_at IS NULL
            GROUP BY t.id
            ORDER BY count DESC
            LIMIT ?
        SQL);
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    }

    public static function getTagsForMediaSet(array $mediaIds, int $limit = 30): array
    {
        if (empty($mediaIds)) {
            return [];
        }
        $pdo          = Db::get();
        $placeholders = implode(',', array_fill(0, count($mediaIds), '?'));
        $stmt = $pdo->prepare(<<<SQL
            SELECT t.name, COUNT(mt.media_id) AS count
            FROM tags t
            JOIN media_tags mt ON mt.tag_id = t.id
            WHERE mt.media_id IN ($placeholders) AND t.deleted_at IS NULL
            GROUP BY t.id
            ORDER BY count DESC
            LIMIT $limit
        SQL);
        $stmt->execute($mediaIds);
        return $stmt->fetchAll();
    }

    // ---- Helpers ----

    public static function queueTagForMedia(int $mediaId, ?int $userId, string $tag): void
    {
        $normalized = self::parseTags($tag)[0] ?? '';
        if ($normalized === '') {
            return;
        }
        Db::get()->prepare('INSERT INTO tag_queue (media_id, user_id, tag, created_at) VALUES (?, ?, ?, ?)')
            ->execute([$mediaId, $userId, $normalized, gmdate('Y-m-d\TH:i:s\Z')]);
    }

    private static function queueTags(int $mediaId, ?int $userId, string $tags): void
    {
        $tagList = self::parseTags($tags);
        if (!$tagList) {
            return;
        }
        $pdo  = Db::get();
        $stmt = $pdo->prepare('INSERT INTO tag_queue (media_id, user_id, tag, created_at) VALUES (?, ?, ?, ?)');
        $now  = gmdate('Y-m-d\TH:i:s\Z');
        foreach ($tagList as $tag) {
            $stmt->execute([$mediaId, $userId, $tag, $now]);
        }
    }

    public static function addTags(array $row, string $tags): array
    {
        $tagList = self::parseTags($tags);
        if ($tagList) {
            $pdo = Db::get();
            foreach ($tagList as $tag) {
                $pdo->prepare('INSERT OR IGNORE INTO tags (name) VALUES (?)')->execute([$tag]);
                // Restore tag if it was soft-deleted
                $pdo->prepare('UPDATE tags SET deleted_at = NULL, deleted_by = NULL WHERE name = ? AND deleted_at IS NOT NULL')
                    ->execute([$tag]);
                $tagId = (int)$pdo->query("SELECT id FROM tags WHERE name = " . $pdo->quote($tag))->fetchColumn();
                $pdo->prepare('INSERT OR IGNORE INTO media_tags (media_id, tag_id) VALUES (?, ?)')
                    ->execute([$row['id'], $tagId]);
            }
        }
        $row['tags'] = self::getTags((int)$row['id']);
        return $row;
    }

    public static function getTags(int $mediaId): array
    {
        $pdo  = Db::get();
        $stmt = $pdo->prepare(<<<'SQL'
            SELECT t.name FROM tags t
            JOIN media_tags mt ON mt.tag_id = t.id
            WHERE mt.media_id = ? AND t.deleted_at IS NULL
            ORDER BY t.name
        SQL);
        $stmt->execute([$mediaId]);
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public static function parseTags(string $input): array
    {
        // Split on commas and/or newlines; spaces within a token become underscores
        $raw  = preg_split('/[,\n]+/', trim($input), -1, PREG_SPLIT_NO_EMPTY);
        $tags = [];
        foreach ($raw as $t) {
            $normalized = self::normalizeTagName($t);
            if ($normalized !== '') {
                $tags[] = $normalized;
            }
        }
        return array_unique($tags);
    }

    private static function normalizeTagName(string $t): string
    {
        $normalized = strtolower(preg_replace('/[^a-z0-9]+/i', '_', trim($t)));
        $normalized = preg_replace('/_+/', '_', $normalized);
        return trim($normalized, '_');
    }

    private static function extractDuration(string $path): ?float
    {
        $ffprobe = self::findBin('ffprobe');
        if (!$ffprobe) {
            return null;
        }
        $cmd = sprintf(
            '%s -v quiet -show_entries format=duration -of default=noprint_wrappers=1:nokey=1 %s 2>/dev/null',
            escapeshellarg($ffprobe),
            escapeshellarg($path)
        );
        $out = shell_exec($cmd);
        return $out ? (float)trim($out) : null;
    }

    private static function findBin(string $name): ?string
    {
        foreach (["/usr/bin/$name", "/usr/local/bin/$name", "/bin/$name"] as $p) {
            if (is_executable($p)) {
                return $p;
            }
        }
        $which = shell_exec('which ' . escapeshellarg($name) . ' 2>/dev/null');
        $p = $which ? trim($which) : null;
        return ($p && is_executable($p)) ? $p : null;
    }
}
