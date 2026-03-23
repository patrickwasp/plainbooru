<?php

declare(strict_types=1);

namespace Plainbooru;

final class Settings
{
    private static ?array $cache = null;

    // ── Defaults ─────────────────────────────────────────────────────────────

    /**
     * Canonical defaults used by migrations (seeding), forms, and resets.
     * Keys map to the settings table. Values are always strings.
     */
    public static function defaults(): array
    {
        return [
            'site_title'              => 'plainbooru',
            'registration_enabled'    => '1',
            'default_user_role'       => 'user',
            'items_per_page'          => '20',
            'max_upload_mb'           => '50',
            'allowed_mime_types'      => 'image/jpeg,image/png,image/gif,image/webp,video/mp4,video/webm',
            'anon_can_upload'              => '0',
            'anon_can_comment'             => '0',
            'anon_can_vote'                => '0',
            'anon_can_create_pool'         => '0',
            'anon_can_edit_tags'           => '0',
            'rate_limit_uploads_per_hour'  => '20',
            'rate_limit_comments_per_hour' => '30',
            'rate_limit_api_per_minute'    => '300',
            'site_description'             => '',
            'require_login_to_view'        => '0',
            'maintenance_mode'             => '0',
            'maintenance_message'          => 'Site is under maintenance. Please check back soon.',
            'max_tags_per_media'           => '50',
            'moderation_queue'             => '0',
        ];
    }

    // ── Typed accessors ───────────────────────────────────────────────────────

    public static function getString(string $key, string $default = ''): string
    {
        return self::raw($key) ?? $default;
    }

    public static function getInt(string $key, int $default = 0): int
    {
        $val = self::raw($key);
        return $val !== null ? (int)$val : $default;
    }

    public static function getBool(string $key, bool $default = false): bool
    {
        $val = self::raw($key);
        if ($val === null) {
            return $default;
        }
        return $val === '1';
    }

    /** Returns an array of non-empty trimmed strings. */
    public static function getCsv(string $key, array $default = []): array
    {
        $val = self::raw($key);
        if ($val === null) {
            return $default;
        }
        return array_values(array_filter(array_map('trim', explode(',', $val))));
    }

    // ── Write with validation ─────────────────────────────────────────────────

    public static function set(string $key, string $value): void
    {
        $value = self::validate($key, $value);
        Db::get()
            ->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)')
            ->execute([$key, $value]);
        self::$cache = null;
    }

    public static function setBool(string $key, bool $value): void
    {
        self::set($key, $value ? '1' : '0');
    }

    public static function setInt(string $key, int $value): void
    {
        self::set($key, (string)$value);
    }

    // ── Internals ─────────────────────────────────────────────────────────────

    private static function raw(string $key): ?string
    {
        if (self::$cache === null) {
            self::$cache = Db::get()
                ->query('SELECT key, value FROM settings')
                ->fetchAll(\PDO::FETCH_KEY_PAIR);
        }
        return self::$cache[$key] ?? null;
    }

    /**
     * Validates and normalises a value before writing.
     * Falls back to the default if the value is invalid.
     *
     * @throws \InvalidArgumentException on unrecoverable invalid input.
     */
    private static function validate(string $key, string $value): string
    {
        return match ($key) {
            'items_per_page'                => (string)max(5, min(100, (int)$value)),
            'max_upload_mb'                 => (string)max(1, min(2048, (int)$value)),
            'max_tags_per_media'            => (string)max(1, min(200, (int)$value)),
            'rate_limit_uploads_per_hour'   => (string)max(1, min(10000, (int)$value)),
            'rate_limit_comments_per_hour'  => (string)max(1, min(10000, (int)$value)),
            'rate_limit_api_per_minute'     => (string)max(1, min(10000, (int)$value)),
            'default_user_role'    => self::validateRole($value),
            'allowed_mime_types'   => self::normaliseCsv($value),
            'registration_enabled',
            'anon_can_upload',
            'anon_can_comment',
            'anon_can_vote',
            'anon_can_create_pool',
            'anon_can_edit_tags',
            'require_login_to_view',
            'maintenance_mode',
            'moderation_queue'     => in_array($value, ['0', '1'], true) ? $value : '0',
            default                => $value,
        };
    }

    private static function validateRole(string $role): string
    {
        $allowed = ['user', 'trusted'];  // admin cannot be default; prevents footgun
        return in_array($role, $allowed, true) ? $role : 'user';
    }

    private static function normaliseCsv(string $value): string
    {
        $parts = array_values(array_filter(array_map(
            fn($s) => strtolower(trim($s)),
            explode(',', $value)
        )));
        return implode(',', $parts);
    }
}
