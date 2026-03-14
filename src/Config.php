<?php

declare(strict_types=1);

namespace Plainbooru;

final class Config
{
    private static array $data = [];

    public static function load(): void
    {
        $envFile = dirname(__DIR__) . '/.env';
        if (file_exists($envFile)) {
            foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                [$key, $value] = array_map('trim', explode('=', $line, 2));
                if (!isset($_ENV[$key])) {
                    $_ENV[$key] = $value;
                    putenv("$key=$value");
                }
            }
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_ENV[$key] ?? getenv($key) ?: $default;
    }

    public static function rootPath(): string
    {
        return dirname(__DIR__);
    }

    public static function storagePath(): string
    {
        return self::rootPath() . '/storage';
    }

    public static function uploadsPath(): string
    {
        return self::storagePath() . '/uploads';
    }

    public static function thumbsPath(): string
    {
        return self::storagePath() . '/thumbs';
    }

    public static function dbPath(): string
    {
        return self::rootPath() . '/data/plainbooru.sqlite';
    }

    public static function maxUploadBytes(): int
    {
        $val = self::get('MAX_UPLOAD_MB', '50');
        return (int)$val * 1024 * 1024;
    }

    public static function adminSecret(): ?string
    {
        $s = self::get('ADMIN_SECRET');
        return $s ?: null;
    }
}
