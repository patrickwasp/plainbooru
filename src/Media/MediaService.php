<?php

declare(strict_types=1);

namespace Plainbooru\Media;

use Plainbooru\Config;
use Plainbooru\Db;

final class MediaService
{
    /**
     * Store a file that's already been moved to $tmpPath (after PSR-7 moveTo).
     */
    public static function storeFromPath(string $tmpPath, array $fileInfo, string $tags = '', ?string $source = null): array
    {
        $size = filesize($tmpPath);
        if ($size === false || $size === 0) {
            throw new \RuntimeException('File is empty.');
        }
        if ($size > Config::maxUploadBytes()) {
            throw new \RuntimeException('File exceeds maximum upload size of ' . (Config::maxUploadBytes() / 1024 / 1024) . 'MB.');
        }

        $origName = basename($fileInfo['name'] ?? 'upload');

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($tmpPath);

        if (!Mime::isAllowed($mime)) {
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

        $now  = date('c');
        $stmt = $pdo->prepare(<<<'SQL'
            INSERT INTO media (kind, sha256, original_name, stored_name, mime, ext, size_bytes,
                               width, height, duration_seconds, created_at, source)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        SQL);
        $stmt->execute([$kind, $sha256, $origName, $storedName, $mime, $ext, $size,
                        $width, $height, $duration, $now, $source]);
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
    public static function store(array $uploadedFile, string $tags = '', ?string $source = null): array
    {
        // Validate size
        $size = $uploadedFile['size'] ?? 0;
        if ($size === 0) {
            throw new \RuntimeException('File is empty.');
        }
        if ($size > Config::maxUploadBytes()) {
            throw new \RuntimeException('File exceeds maximum upload size of ' . (Config::maxUploadBytes() / 1024 / 1024) . 'MB.');
        }

        $tmpPath = $uploadedFile['tmp_name'];
        $origName = basename($uploadedFile['name'] ?? 'upload');

        // Detect MIME via finfo
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime  = $finfo->file($tmpPath);

        if (!Mime::isAllowed($mime)) {
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

        $now = date('c');
        $stmt = $pdo->prepare(<<<'SQL'
            INSERT INTO media (kind, sha256, original_name, stored_name, mime, ext, size_bytes,
                               width, height, duration_seconds, created_at, source)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        SQL);
        $stmt->execute([$kind, $sha256, $origName, $storedName, $mime, $ext, $size,
                        $width, $height, $duration, $now, $source]);
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

    public static function search(string $tags = '', string $q = '', int $page = 1, int $pageSize = 20): array
    {
        $pdo    = Db::get();
        $offset = ($page - 1) * $pageSize;

        $tagList = self::parseTags($tags);
        $params  = [];
        $where   = ['1=1'];

        if ($tagList) {
            foreach ($tagList as $i => $tag) {
                $where[] = "m.id IN (SELECT mt.media_id FROM media_tags mt
                                      JOIN tags t ON t.id = mt.tag_id
                                      WHERE t.name = :tag$i)";
                $params[":tag$i"] = $tag;
            }
        }

        if ($q !== '') {
            $where[] = "(m.original_name LIKE :q OR m.source LIKE :q)";
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
            JOIN media_tags mt ON mt.tag_id = t.id
            GROUP BY t.id
            ORDER BY count DESC
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

    // ---- Helpers ----

    private static function addTags(array $row, string $tags): array
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
        // Split on whitespace and/or commas, normalize to lowercase+underscore
        $raw  = preg_split('/[\s,]+/', trim($input), -1, PREG_SPLIT_NO_EMPTY);
        $tags = [];
        foreach ($raw as $t) {
            $normalized = strtolower(preg_replace('/[^a-z0-9_]/i', '_', $t));
            $normalized = preg_replace('/_+/', '_', $normalized);
            $normalized = trim($normalized, '_');
            if ($normalized !== '') {
                $tags[] = $normalized;
            }
        }
        return array_unique($tags);
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
