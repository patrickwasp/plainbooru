<?php

declare(strict_types=1);

namespace Plainbooru\Media;

final class Mime
{
    public const IMAGES = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
    ];

    public const VIDEOS = [
        'video/mp4'       => 'mp4',
        'video/webm'      => 'webm',
        'video/quicktime' => 'mov',
    ];

    public static function all(): array
    {
        return array_merge(self::IMAGES, self::VIDEOS);
    }

    public static function isAllowed(string $mime): bool
    {
        return isset(self::all()[$mime]);
    }

    public static function ext(string $mime): ?string
    {
        return self::all()[$mime] ?? null;
    }

    public static function kind(string $mime): ?string
    {
        if (isset(self::IMAGES[$mime])) {
            return 'image';
        }
        if (isset(self::VIDEOS[$mime])) {
            return 'video';
        }
        return null;
    }
}
