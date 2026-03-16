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
    public static function storeFromPath(string $tmpPath, array $fileInfo, string $tags = '', ?int $uploaderId = null): array
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

        $animated = ($kind === 'image' && $ext === 'gif' && self::isAnimatedGif($destPath)) ? 1 : 0;

        $now  = date('c');
        $stmt = $pdo->prepare(<<<'SQL'
            INSERT INTO media (kind, sha256, original_name, stored_name, mime, ext, size_bytes,
                               width, height, duration_seconds, animated, uploader_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        SQL);
        $stmt->execute([$kind, $sha256, $origName, $storedName, $mime, $ext, $size,
                        $width, $height, $duration, $animated, $uploaderId, $now]);
        $id = (int)$pdo->lastInsertId();

        ThumbService::generate($storedName, $mime, $id);

        $row = $pdo->query("SELECT * FROM media WHERE id = $id")->fetch();
        return self::addTags($row, $tags);
    }

    /**
     * Process an uploaded file, store it, create thumbnail, insert into DB.
     * Returns the media row array.
     * @throws \RuntimeException on validation failure
     */
    public static function store(array $uploadedFile, string $tags = '', ?int $uploaderId = null): array
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

        $animated = ($kind === 'image' && $ext === 'gif' && self::isAnimatedGif($destPath)) ? 1 : 0;

        $now = date('c');
        $stmt = $pdo->prepare(<<<'SQL'
            INSERT INTO media (kind, sha256, original_name, stored_name, mime, ext, size_bytes,
                               width, height, duration_seconds, animated, uploader_id, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        SQL);
        $stmt->execute([$kind, $sha256, $origName, $storedName, $mime, $ext, $size,
                        $width, $height, $duration, $animated, $uploaderId, $now]);
        $id = (int)$pdo->lastInsertId();

        // Generate thumbnail
        ThumbService::generate($storedName, $mime, $id);

        $row = $pdo->query("SELECT * FROM media WHERE id = $id")->fetch();
        return self::addTags($row, $tags);
    }

    public static function getById(int $id): ?array
    {
        $pdo  = Db::get();
        $stmt = $pdo->prepare('SELECT * FROM media WHERE id = ?');
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
        $rows   = $pdo->query("SELECT * FROM media ORDER BY created_at DESC LIMIT $pageSize OFFSET $offset")->fetchAll();
        $total  = (int)$pdo->query('SELECT COUNT(*) FROM media')->fetchColumn();
        return ['total' => $total, 'results' => $rows];
    }

    public static function getByUploader(int $userId, int $page = 1, int $pageSize = 20): array
    {
        $pdo    = Db::get();
        $offset = ($page - 1) * $pageSize;
        $stmt   = $pdo->prepare('SELECT * FROM media WHERE uploader_id = ? ORDER BY created_at DESC LIMIT ? OFFSET ?');
        $stmt->execute([$userId, $pageSize, $offset]);
        $rows   = $stmt->fetchAll();
        $cstmt  = $pdo->prepare('SELECT COUNT(*) FROM media WHERE uploader_id = ?');
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
        $where  = ['1=1'];

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
                                                  WHERE t.name = :req$i)";
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
                                          WHERE t.name IN ($in))";
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
                                                      WHERE t.name = :exc$i)";
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
            LEFT JOIN media_tags mt ON mt.tag_id = t.id
            GROUP BY t.id
            ORDER BY count DESC, t.name ASC
        SQL)->fetchAll();
    }

    public static function delete(int $id): bool
    {
        $row = self::getById($id);
        if (!$row) {
            return false;
        }
        $pdo = Db::get();
        $pdo->prepare('DELETE FROM media WHERE id = ?')->execute([$id]);

        // Remove files
        $upload = Config::uploadsPath() . '/' . $row['stored_name'];
        $thumb  = Config::thumbsPath() . '/' . $id . '.webp';
        foreach ([$upload, $thumb] as $f) {
            if (file_exists($f)) {
                @unlink($f);
            }
        }
        return true;
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

    public static function deleteTag(string $tagName): bool
    {
        $pdo  = Db::get();
        $stmt = $pdo->prepare('DELETE FROM tags WHERE name = ?');
        $stmt->execute([$tagName]);
        return $stmt->rowCount() > 0;
    }

    public static function getPopularTags(int $limit = 20): array
    {
        $pdo  = Db::get();
        $stmt = $pdo->prepare(<<<'SQL'
            SELECT t.name, COUNT(mt.media_id) AS count
            FROM tags t
            JOIN media_tags mt ON mt.tag_id = t.id
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
            WHERE mt.media_id IN ($placeholders)
            GROUP BY t.id
            ORDER BY count DESC
            LIMIT $limit
        SQL);
        $stmt->execute($mediaIds);
        return $stmt->fetchAll();
    }

    // ---- Helpers ----

    public static function addTags(array $row, string $tags): array
    {
        $tagList = self::parseTags($tags);
        if ($tagList) {
            $pdo = Db::get();
            foreach ($tagList as $tag) {
                $pdo->prepare('INSERT OR IGNORE INTO tags (name) VALUES (?)')->execute([$tag]);
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
            WHERE mt.media_id = ?
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
