<?php

declare(strict_types=1);

namespace Plainbooru\Media;

use Plainbooru\Config;

final class ThumbService
{
    public static function generate(string $storedName, string $mime, int $mediaId, bool $force = false): bool
    {
        $thumbPath = Config::thumbsPath() . '/' . $mediaId . '.' . self::thumbExt();
        if (!$force && file_exists($thumbPath)) {
            return true;
        }
        if ($force && file_exists($thumbPath)) {
            @unlink($thumbPath);
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

        // Output to a temp JPEG first — static ffmpeg binaries often lack libwebp.
        // GD handles the final format conversion.
        $tmp = sys_get_temp_dir() . '/pb_vthumb_' . getmypid() . '_' . mt_rand() . '.jpg';
        $cmd = sprintf(
            '%s -y -ss 00:00:01 -i %s -vframes 1 -vf "scale=400:-1" -f image2 %s 2>&1',
            escapeshellarg($ffmpeg),
            escapeshellarg($src),
            escapeshellarg($tmp)
        );
        exec($cmd, $out, $code);

        if ($code !== 0 || !file_exists($tmp)) {
            // Persist last error so admin diagnostics can surface it
            @file_put_contents(sys_get_temp_dir() . '/pb_ffmpeg_last_err.txt', implode("\n", $out));
            return self::placeholderThumb($dest);
        }

        $img = @imagecreatefromjpeg($tmp);
        @unlink($tmp);

        if (!$img) {
            return self::placeholderThumb($dest);
        }

        $result = function_exists('imagewebp')
            ? imagewebp($img, $dest, 80)
            : imagejpeg($img, $dest, 85);
        imagedestroy($img);
        return $result ?: self::placeholderThumb($dest);
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
        foreach ([Config::rootPath() . '/bin/' . $name, "/usr/bin/$name", "/usr/local/bin/$name", "/bin/$name"] as $p) {
            if (is_executable($p)) {
                return $p;
            }
        }
        $which = shell_exec('which ' . escapeshellarg($name) . ' 2>/dev/null');
        $p = $which ? trim($which) : null;
        return ($p && is_executable($p)) ? $p : null;
    }

    public static function diagnostics(): array
    {
        $ffmpeg  = self::findBin('ffmpeg');
        $ffprobe = self::findBin('ffprobe');

        // Run a quick version check to confirm the binary actually executes
        $ffmpegVersion  = null;
        $ffprobeVersion = null;
        if ($ffmpeg && function_exists('exec')) {
            exec(escapeshellarg($ffmpeg) . ' -version 2>&1', $out, $code);
            if ($code === 0 && !empty($out[0])) {
                $ffmpegVersion = $out[0];
            }
        }
        if ($ffprobe && function_exists('exec')) {
            exec(escapeshellarg($ffprobe) . ' -version 2>&1', $out, $code);
            if ($code === 0 && !empty($out[0])) {
                $ffprobeVersion = $out[0];
            }
        }

        $errFile = sys_get_temp_dir() . '/pb_ffmpeg_last_err.txt';
        $lastErr = file_exists($errFile) ? trim((string)file_get_contents($errFile)) : null;

        return [
            'ffmpeg'              => $ffmpeg !== null,
            'ffmpeg_path'         => $ffmpeg,
            'ffmpeg_version'      => $ffmpegVersion,
            'ffprobe'             => $ffprobe !== null,
            'ffprobe_path'        => $ffprobe,
            'ffprobe_version'     => $ffprobeVersion,
            'gd'                  => function_exists('imagecreatetruecolor'),
            'gd_webp'             => function_exists('imagewebp'),
            'exec_enabled'        => function_exists('exec'),
            'shell_exec_enabled'  => function_exists('shell_exec'),
            'open_basedir'        => ini_get('open_basedir') ?: null,
            'bin_path'            => Config::rootPath() . '/bin',
            'last_ffmpeg_error'   => $lastErr,
        ];
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
