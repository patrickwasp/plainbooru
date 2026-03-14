<?php

declare(strict_types=1);

namespace Plainbooru;

final class Migrations
{
    public static function run(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS media (
                id             INTEGER PRIMARY KEY AUTOINCREMENT,
                kind           TEXT    NOT NULL,
                sha256         TEXT    NOT NULL UNIQUE,
                original_name  TEXT    NOT NULL,
                stored_name    TEXT    NOT NULL,
                mime           TEXT    NOT NULL,
                ext            TEXT    NOT NULL,
                size_bytes     INTEGER NOT NULL,
                width          INTEGER NULL,
                height         INTEGER NULL,
                duration_seconds REAL  NULL,
                created_at     TEXT    NOT NULL,
                source         TEXT    NULL
            );

            CREATE TABLE IF NOT EXISTS tags (
                id   INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT    NOT NULL UNIQUE
            );

            CREATE TABLE IF NOT EXISTS media_tags (
                media_id INTEGER NOT NULL REFERENCES media(id) ON DELETE CASCADE,
                tag_id   INTEGER NOT NULL REFERENCES tags(id)  ON DELETE CASCADE,
                PRIMARY KEY (media_id, tag_id)
            );

            CREATE TABLE IF NOT EXISTS pools (
                id          INTEGER PRIMARY KEY AUTOINCREMENT,
                name        TEXT    NOT NULL UNIQUE,
                description TEXT    NULL,
                created_at  TEXT    NOT NULL
            );

            CREATE TABLE IF NOT EXISTS pool_items (
                pool_id   INTEGER NOT NULL REFERENCES pools(id)  ON DELETE CASCADE,
                media_id  INTEGER NOT NULL REFERENCES media(id)  ON DELETE CASCADE,
                position  INTEGER NOT NULL,
                added_at  TEXT    NOT NULL,
                PRIMARY KEY (pool_id, media_id),
                UNIQUE(pool_id, position)
            );

            CREATE INDEX IF NOT EXISTS idx_media_created_at   ON media(created_at DESC);
            CREATE INDEX IF NOT EXISTS idx_tags_name          ON tags(name);
            CREATE INDEX IF NOT EXISTS idx_media_tags_tag_id  ON media_tags(tag_id);
            CREATE INDEX IF NOT EXISTS idx_pool_items_pos     ON pool_items(pool_id, position);
            CREATE INDEX IF NOT EXISTS idx_pools_created_at   ON pools(created_at DESC);
        SQL);
    }
}
