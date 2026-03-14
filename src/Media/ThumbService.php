<?php

declare(strict_types=1);

namespace Plainbooru\Media;

use Plainbooru\Config;

final class ThumbService
{
    public static function generate(string $storedName, string $mime, int $mediaId): bool
    {
        $thumbPath = Config::thumbsPath() . '/' . $mediaId . '.webp';
        if (file_exists($thumbPath)) {
            return true;
        }

        $kind = Mime::kind($mime);

        if ($kind === 'image') {
            return self::imageThumb(Config::uploadsPath() . '/' . $storedName, $thumbPath);
        }

        if ($kind === 'video') {
            return self::videoThumb(Config::uploadsPath() . '/' . $storedName, $thumbPath);
        }

        return false;
    }

    private static function imageThumb(string $src, string $dest): bool
    {
        try {
            $info = @getimagesize($src);
            if (!$info) {
                return false;
            }
            [$w, $h, $type] = $info;
            $maxDim = 400;

            if ($w <= $maxDim && $h <= $maxDim) {
                $newW = $w;
                $newH = $h;
            } elseif ($w > $h) {
                $newW = $maxDim;
                $newH = (int)round($h * $maxDim / $w);
            } else {
                $newH = $maxDim;
                $newW = (int)round($w * $maxDim / $h);
            }

            $src_img = match ($type) {
                IMAGETYPE_JPEG => imagecreatefromjpeg($src),
                IMAGETYPE_PNG  => imagecreatefrompng($src),
                IMAGETYPE_WEBP => imagecreatefromwebp($src),
                IMAGETYPE_GIF  => imagecreatefromgif($src),
                default => false,
            };

            if (!$src_img) {
                return false;
            }

            $thumb = imagecreatetruecolor($newW, $newH);
            if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF) {
                imagealphablending($thumb, false);
                imagesavealpha($thumb, true);
                $transparent = imagecolorallocatealpha($thumb, 255, 255, 255, 127);
                imagefilledrectangle($thumb, 0, 0, $newW, $newH, $transparent);
            }

            imagecopyresampled($thumb, $src_img, 0, 0, 0, 0, $newW, $newH, $w, $h);
            imagedestroy($src_img);

            $result = function_exists('imagewebp')
                ? imagewebp($thumb, $dest, 80)
                : imagejpeg($thumb, $dest, 85);

            imagedestroy($thumb);
            return $result;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function videoThumb(string $src, string $dest): bool
    {
        $ffmpeg = self::findBin('ffmpeg');
        if (!$ffmpeg) {
            return self::placeholderThumb($dest);
        }

        $cmd = sprintf(
            '%s -y -i %s -ss 00:00:01 -vframes 1 -vf "scale=400:-1" %s 2>/dev/null',
            escapeshellarg($ffmpeg),
            escapeshellarg($src),
            escapeshellarg($dest)
        );
        exec($cmd, $out, $code);
        if ($code !== 0 || !file_exists($dest)) {
            return self::placeholderThumb($dest);
        }
        return true;
    }

    private static function placeholderThumb(string $dest): bool
    {
        // Create a simple grey placeholder image
        $img = imagecreatetruecolor(400, 300);
        $bg  = imagecolorallocate($img, 50, 50, 60);
        $fg  = imagecolorallocate($img, 180, 180, 200);
        imagefilledrectangle($img, 0, 0, 399, 299, $bg);
        // Draw a simple play triangle
        $pts = [160, 80, 160, 220, 280, 150];
        imagefilledpolygon($img, $pts, $fg);

        $result = function_exists('imagewebp')
            ? imagewebp($img, $dest, 80)
            : imagejpeg($img, $dest, 85);
        imagedestroy($img);
        return $result;
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

    public static function thumbMime(): string
    {
        return function_exists('imagewebp') ? 'image/webp' : 'image/jpeg';
    }

    public static function thumbExt(): string
    {
        return function_exists('imagewebp') ? 'webp' : 'jpg';
    }
}
