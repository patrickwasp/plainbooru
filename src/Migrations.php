<?php

declare(strict_types=1);

namespace Plainbooru;

final class Migrations
{
    public static function run(\PDO $pdo): void
    {
        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS users (
                id            INTEGER PRIMARY KEY AUTOINCREMENT,
                username      TEXT    NOT NULL UNIQUE,
                password_hash TEXT    NOT NULL,
                role          TEXT    NOT NULL DEFAULT 'user',
                created_at    TEXT    NOT NULL
            );

            CREATE INDEX IF NOT EXISTS idx_users_username ON users(username);

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
                created_at     TEXT    NOT NULL
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

            CREATE TABLE IF NOT EXISTS pool_tags (
                pool_id INTEGER NOT NULL REFERENCES pools(id) ON DELETE CASCADE,
                tag_id  INTEGER NOT NULL REFERENCES tags(id)  ON DELETE CASCADE,
                PRIMARY KEY (pool_id, tag_id)
            );

            CREATE TABLE IF NOT EXISTS favorites (
                user_id    INTEGER NOT NULL REFERENCES users(id)  ON DELETE CASCADE,
                media_id   INTEGER NOT NULL REFERENCES media(id)  ON DELETE CASCADE,
                created_at TEXT    NOT NULL,
                PRIMARY KEY (user_id, media_id)
            );

            CREATE TABLE IF NOT EXISTS votes (
                user_id    INTEGER NOT NULL REFERENCES users(id)  ON DELETE CASCADE,
                media_id   INTEGER NOT NULL REFERENCES media(id)  ON DELETE CASCADE,
                value      INTEGER NOT NULL CHECK(value IN (-1, 1)),
                created_at TEXT    NOT NULL,
                PRIMARY KEY (user_id, media_id)
            );

            CREATE INDEX IF NOT EXISTS idx_media_created_at   ON media(created_at DESC);
            CREATE INDEX IF NOT EXISTS idx_tags_name          ON tags(name);
            CREATE INDEX IF NOT EXISTS idx_media_tags_tag_id  ON media_tags(tag_id);
            CREATE INDEX IF NOT EXISTS idx_pool_items_pos     ON pool_items(pool_id, position);
            CREATE INDEX IF NOT EXISTS idx_pool_tags_pool_id  ON pool_tags(pool_id);
            CREATE INDEX IF NOT EXISTS idx_pools_created_at   ON pools(created_at DESC);
            CREATE TABLE IF NOT EXISTS comments (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                media_id   INTEGER NOT NULL REFERENCES media(id)  ON DELETE CASCADE,
                user_id    INTEGER NULL     REFERENCES users(id)  ON DELETE SET NULL,
                body       TEXT    NOT NULL,
                deleted_at TEXT    NULL,
                deleted_by INTEGER NULL     REFERENCES users(id),
                created_at TEXT    NOT NULL
            );

            CREATE INDEX IF NOT EXISTS idx_favorites_user_id  ON favorites(user_id);
            CREATE INDEX IF NOT EXISTS idx_favorites_media_id ON favorites(media_id);
            CREATE INDEX IF NOT EXISTS idx_votes_media_id     ON votes(media_id);
            CREATE INDEX IF NOT EXISTS idx_votes_user_id      ON votes(user_id);
            CREATE TABLE IF NOT EXISTS rate_buckets (
                key          TEXT    NOT NULL PRIMARY KEY,
                count        INTEGER NOT NULL DEFAULT 0,
                window_start TEXT    NOT NULL
            );

            CREATE TABLE IF NOT EXISTS api_tokens (
                id           INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id      INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                token_hash   TEXT    NOT NULL UNIQUE,
                label        TEXT    NOT NULL,
                last_used_at TEXT    NULL,
                created_at   TEXT    NOT NULL
            );

            CREATE TABLE IF NOT EXISTS mod_log (
                id         INTEGER PRIMARY KEY AUTOINCREMENT,
                mod_id     INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
                action     TEXT    NOT NULL,
                target     TEXT    NOT NULL,
                details    TEXT    NULL,
                created_at TEXT    NOT NULL
            );

            CREATE INDEX IF NOT EXISTS idx_comments_media_id  ON comments(media_id);
            CREATE INDEX IF NOT EXISTS idx_comments_user_id   ON comments(user_id);
            CREATE INDEX IF NOT EXISTS idx_api_tokens_user_id ON api_tokens(user_id);
            CREATE INDEX IF NOT EXISTS idx_mod_log_mod_id     ON mod_log(mod_id);
            CREATE INDEX IF NOT EXISTS idx_mod_log_created_at ON mod_log(created_at);
        SQL);

        // Additive column migrations (safe to run on every boot)
        $cols = array_column($pdo->query('PRAGMA table_info(media)')->fetchAll(), 'name');
        if (!in_array('animated', $cols, true)) {
            $pdo->exec('ALTER TABLE media ADD COLUMN animated INTEGER NOT NULL DEFAULT 0');
        }
        if (!in_array('uploader_id', $cols, true)) {
            $pdo->exec('ALTER TABLE media ADD COLUMN uploader_id INTEGER NULL REFERENCES users(id) ON DELETE SET NULL');
        }

        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_media_uploader_id ON media(uploader_id)');

        $userCols = array_column($pdo->query('PRAGMA table_info(users)')->fetchAll(), 'name');
        if (!in_array('banned_at', $userCols, true)) {
            $pdo->exec('ALTER TABLE users ADD COLUMN banned_at TEXT NULL');
        }
        if (!in_array('ban_reason', $userCols, true)) {
            $pdo->exec('ALTER TABLE users ADD COLUMN ban_reason TEXT NULL');
        }
        if (!in_array('banned_by', $userCols, true)) {
            $pdo->exec('ALTER TABLE users ADD COLUMN banned_by INTEGER NULL REFERENCES users(id)');
        }
        if (!in_array('bio', $userCols, true)) {
            $pdo->exec('ALTER TABLE users ADD COLUMN bio TEXT NULL');
        }

        $poolCols = array_column($pdo->query('PRAGMA table_info(pools)')->fetchAll(), 'name');
        if (!in_array('creator_id', $poolCols, true)) {
            $pdo->exec('ALTER TABLE pools ADD COLUMN creator_id INTEGER NULL REFERENCES users(id) ON DELETE SET NULL');
        }
        if (!in_array('visibility', $poolCols, true)) {
            $pdo->exec("ALTER TABLE pools ADD COLUMN visibility TEXT NOT NULL DEFAULT 'public'");
        }
        if (!in_array('deleted_at', $poolCols, true)) {
            $pdo->exec('ALTER TABLE pools ADD COLUMN deleted_at TEXT NULL');
        }
        if (!in_array('deleted_by', $poolCols, true)) {
            $pdo->exec('ALTER TABLE pools ADD COLUMN deleted_by INTEGER NULL REFERENCES users(id)');
        }

        if (!in_array('deleted_at', $cols, true)) {
            $pdo->exec('ALTER TABLE media ADD COLUMN deleted_at TEXT NULL');
        }
        if (!in_array('deleted_by', $cols, true)) {
            $pdo->exec('ALTER TABLE media ADD COLUMN deleted_by INTEGER NULL REFERENCES users(id)');
        }

        $tagCols = array_column($pdo->query('PRAGMA table_info(tags)')->fetchAll(), 'name');
        if (!in_array('deleted_at', $tagCols, true)) {
            $pdo->exec('ALTER TABLE tags ADD COLUMN deleted_at TEXT NULL');
        }
        if (!in_array('deleted_by', $tagCols, true)) {
            $pdo->exec('ALTER TABLE tags ADD COLUMN deleted_by INTEGER NULL REFERENCES users(id)');
        }

        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_pools_creator_id ON pools(creator_id)');
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_pools_visibility  ON pools(visibility)');

        // Defensive: clear any stale null roles
        $pdo->exec("UPDATE users SET role = 'user' WHERE role IS NULL");

        // Settings table
        $pdo->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS settings (
                key   TEXT NOT NULL PRIMARY KEY,
                value TEXT NOT NULL
            )
        SQL);

        // Seed defaults (INSERT OR IGNORE so existing values are never clobbered)
        $defaults = \Plainbooru\Settings::defaults();
        $seedStmt = $pdo->prepare('INSERT OR IGNORE INTO settings (key, value) VALUES (?, ?)');
        foreach ($defaults as $k => $v) {
            $seedStmt->execute([$k, $v]);
        }
    }
}
