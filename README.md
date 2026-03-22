# plainbooru

A minimal image/video booru board built with zero client-side JavaScript.

**Stack:** PHP 8.2+ · Slim 4 · SQLite · Tailwind CSS v4 · Basecoat UI

---

## Features

### Media

- Upload images (JPEG, PNG, WebP, GIF) and videos (MP4, WebM, MOV)
- SHA-256 deduplication — duplicate uploads link to existing media
- Automatic thumbnail generation (GD/WebP for images; ffmpeg poster for videos)
- Server-rendered HTML — **zero JavaScript shipped to the browser**

### Tagging & Search

- Flexible tagging system with tag browsing
- Full-text and tag-based search

### Pools

- Ordered collections of media
- Reorder, add, remove items; soft-delete with recovery

### Social

- User accounts with public profiles and upload history
- Comments, favorites, and upvote/downvote
- Dark/light theme toggle (cookie-based)

### Admin & Moderation

- Role-based access: anonymous → user → trusted → moderator → admin
- Admin panel: site settings, user management, role assignment, banning
- Moderation log with full audit trail
- Trash/recovery system for soft-deleted media, pools, tags, and comments

### API

- JSON REST API v1 with Bearer token authentication
- Rate limiting per endpoint (uploads, comments, API calls)
- Per-user API token management

---

## Quick Start

### Docker Compose (recommended)

```bash
docker compose up --build
```

Open <http://localhost:8080> and follow the `/install` wizard to create the
admin account.

### PHP built-in server (no Docker)

Requirements: PHP 8.2+, Composer, GD extension.

```bash
composer install
mkdir -p storage/uploads storage/thumbs data
php -S localhost:8080 -t public
```

Open <http://localhost:8080> and follow the `/install` wizard.

---

## CSS Build

The compiled CSS is committed (`public/assets/app.css`) so production needs no
Node.

To rebuild after template changes:

```bash
npm install
npm run build:css   # one-shot
npm run watch:css   # watch mode
```

---

## Environment Variables

| Variable        | Default      | Description                                              |
| --------------- | ------------ | -------------------------------------------------------- |
| `APP_ENV`       | `production` | Set to `development` for verbose errors                  |
| `MAX_UPLOAD_MB` | `50`         | Maximum file upload size                                 |
| `ADMIN_SECRET`  | _(empty)_    | Legacy token for API mutations (prefer user tokens)      |
| `NGINX_ACCEL`   | `false`      | Enable nginx X-Accel-Redirect for efficient file serving |

---

## Authentication

### User sessions

Register at `/signup` (if enabled) or create the first admin via `/install`.
Roles: `anonymous`, `user`, `trusted`, `moderator`, `admin`.

### API tokens

Log in and generate a token at `/settings/tokens`. Use it as a Bearer token:

```bash
curl -H "Authorization: Bearer <token>" http://localhost:8080/api/v1/media/1
```

### Legacy admin secret

For API mutations without a user account, set `ADMIN_SECRET` in `.env` and pass
it as:

- HTTP header: `X-Admin-Secret: your_secret`
- Query parameter: `?admin_secret=your_secret`

---

## API Examples

### Upload an image

```bash
curl -X POST http://localhost:8080/api/v1/media \
  -H "Authorization: Bearer <token>" \
  -F "file=@photo.jpg" \
  -F "tags=cat cute" \
  -F "source=https://example.com/original"
```

Response:

```json
{
  "id": 1,
  "kind": "image",
  "mime": "image/jpeg",
  "size_bytes": 102400,
  "width": 1920,
  "height": 1080,
  "duration_seconds": null,
  "created_at": "2025-01-01T12:00:00+00:00",
  "source": "https://example.com/original",
  "tags": ["cat", "cute"],
  "urls": {
    "page": "/m/1",
    "file": "/file/1",
    "thumb": "/thumb/1",
    "api": "/api/v1/media/1"
  }
}
```

### Search by tags

```bash
curl "http://localhost:8080/api/v1/search?tags=cat+cute&page=1&page_size=20"
```

### Create a pool

```bash
curl -X POST http://localhost:8080/api/v1/pools \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"name": "Favorites", "description": "My favorite images"}'
```

### Add media to a pool

```bash
curl -X POST http://localhost:8080/api/v1/pools/1/items \
  -H "Authorization: Bearer <token>" \
  -H "Content-Type: application/json" \
  -d '{"media_id": 1}'
```

---

## Deploying to cPanel with GitHub Actions

The included workflow (`.github/workflows/release-and-deploy.yml`) triggers on a
published GitHub release. It builds a release zip (attached to the release) and
deploys via SSH rsync to a cPanel host.

### Prerequisites

- cPanel hosting with SSH access enabled (**cPanel → Terminal** or **SSH
  Access**)
- Domain's document root pointed to the `public/` subdirectory (**cPanel →
  Domains**)
- PHP 8.2+ and the GD extension available on the host

### GitHub Secrets

Add these in **GitHub → Settings → Secrets and variables → Actions**:

| Secret                  | Where to get it                                                                                                                                        |
| ----------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `CPANEL_HOST`           | Server hostname or IP — shown in cPanel under **Server Information**                                                                                   |
| `CPANEL_USER`           | Your cPanel username                                                                                                                                   |
| `CPANEL_SSH_PORT`       | SSH port — cPanel hosts typically use `21098`; check with your host                                                                                    |
| `CPANEL_SSH_KEY`        | Private half of an SSH key pair — generate with `ssh-keygen -t ed25519`, then add the public key in **cPanel → SSH Access → Manage SSH Keys → Import** |
| `PLAINBOORU_ADMIN_USER` | Admin username to create on first deploy                                                                                                               |
| `PLAINBOORU_ADMIN_PASS` | Admin password to create on first deploy                                                                                                               |

### What the workflow does

1. Runs `composer install --no-dev` and builds a release zip attached to the
   GitHub release.
2. Rsyncs app files to `/home/<user>/<domain>/` over SSH, skipping `data/`,
   `storage/`, and `.env` so live data is never overwritten.
3. On first deploy (no `data/installed.lock`), runs `php bin/install` with the
   admin credentials from secrets.
4. Subsequent deploys are code-only — the database and uploads are untouched.

### First deploy

1. Create a domain/subdomain in cPanel and point its document root to `public/`.
2. Add all six secrets above.
3. Publish a GitHub release — the workflow fires automatically.

---

## Routes Reference

### HTML (server-rendered, no JS)

| Method   | Path                                        | Description                                   |
| -------- | ------------------------------------------- | --------------------------------------------- |
| GET      | `/`                                         | Home: latest uploads grid                     |
| GET/POST | `/install`                                  | First-run setup wizard                        |
| GET/POST | `/login`                                    | Login form                                    |
| GET/POST | `/signup`                                   | Registration form                             |
| POST     | `/logout`                                   | Sign out                                      |
| GET      | `/upload`                                   | Upload form                                   |
| POST     | `/upload`                                   | Handle upload → redirect to `/m/{id}`         |
| GET      | `/m/{id}`                                   | Media detail page                             |
| POST     | `/m/{id}/tags`                              | Add tag to media                              |
| POST     | `/m/{id}/tags/remove`                       | Remove tag from media                         |
| POST     | `/m/{id}/favorite`                          | Toggle favorite                               |
| POST     | `/m/{id}/vote`                              | Upvote/downvote                               |
| POST     | `/m/{id}/comments`                          | Add comment                                   |
| POST     | `/m/{id}/comments/{cid}/delete`             | Delete comment                                |
| POST     | `/m/{id}/delete`                            | Soft-delete media                             |
| GET      | `/search`                                   | Search by tags (`?tags=`) and/or text (`?q=`) |
| GET      | `/t/{tag}`                                  | Tag page                                      |
| GET      | `/tags`                                     | All tags listing                              |
| GET      | `/pools`                                    | Pools list                                    |
| POST     | `/pools`                                    | Create pool                                   |
| GET      | `/pools/{id}`                               | Pool detail                                   |
| GET/POST | `/pools/{id}/edit`                          | Edit pool metadata                            |
| POST     | `/pools/{id}/upload`                        | Upload + add to pool                          |
| POST     | `/pools/{id}/items`                         | Add media to pool                             |
| POST     | `/pools/{id}/reorder`                       | Reorder pool items                            |
| POST     | `/pools/{id}/move`                          | Shift item left/right                         |
| POST     | `/pools/{id}/remove`                        | Remove item from pool                         |
| POST     | `/pools/{id}/tags`                          | Add tag to pool                               |
| POST     | `/pools/{id}/tags/remove`                   | Remove tag from pool                          |
| POST     | `/pools/{id}/delete`                        | Soft-delete pool                              |
| GET      | `/pools/{poolId}/m/{mediaId}`               | Media viewer in pool context                  |
| GET      | `/u/{username}`                             | Public user profile                           |
| GET      | `/u/{username}/favorites`                   | User's favorites                              |
| GET/POST | `/settings/account`                         | Password and bio settings                     |
| GET/POST | `/settings/tokens`                          | API token management                          |
| POST     | `/settings/tokens/{id}/revoke`              | Revoke API token                              |
| POST     | `/theme`                                    | Toggle dark/light theme                       |
| GET/POST | `/admin/settings`                           | Site configuration                            |
| GET      | `/admin/users`                              | User management                               |
| POST     | `/admin/users/{id}/role`                    | Change user role                              |
| POST     | `/admin/users/{id}/ban`                     | Ban user                                      |
| GET      | `/admin/mod-log`                            | Moderation log                                |
| GET      | `/admin/trash`                              | Soft-deleted items                            |
| POST     | `/admin/trash/media/{id}/restore\|purge`    | Restore or purge media                        |
| POST     | `/admin/trash/pools/{id}/restore\|purge`    | Restore or purge pool                         |
| POST     | `/admin/trash/tags/{name}/restore\|purge`   | Restore or purge tag                          |
| POST     | `/admin/trash/comments/{id}/restore\|purge` | Restore or purge comment                      |
| GET      | `/file/{id}`                                | Stream original file                          |
| GET      | `/thumb/{id}`                               | Stream thumbnail                              |

### REST API (JSON)

| Method | Path                                  | Description         |
| ------ | ------------------------------------- | ------------------- |
| POST   | `/api/v1/media`                       | Upload media        |
| GET    | `/api/v1/media/{id}`                  | Get media           |
| DELETE | `/api/v1/media/{id}`                  | Delete media        |
| GET    | `/api/v1/search`                      | Search media        |
| POST   | `/api/v1/pools`                       | Create pool         |
| GET    | `/api/v1/pools`                       | List pools          |
| GET    | `/api/v1/pools/{id}`                  | Get pool with items |
| POST   | `/api/v1/pools/{id}/items`            | Add item to pool    |
| POST   | `/api/v1/pools/{id}/reorder`          | Reorder pool items  |
| DELETE | `/api/v1/pools/{id}/items/{media_id}` | Remove item         |
| DELETE | `/api/v1/pools/{id}`                  | Delete pool         |

---

## Project Structure

```text
plainbooru/
├── composer.json           # PHP dependencies (Slim 4, Twig, PHP-DI)
├── package.json            # Node devDependencies (Tailwind, Basecoat)
├── docker-compose.yml      # Docker Compose (nginx + php-fpm)
├── docker/
│   ├── nginx.conf          # nginx configuration
│   └── php/Dockerfile      # PHP-FPM image
├── public/
│   ├── index.php           # Slim front controller
│   └── assets/
│       └── app.css         # Compiled CSS (committed)
├── src/
│   ├── App.php             # Slim app + all routes
│   ├── Config.php          # Env + path helpers
│   ├── Db.php              # PDO SQLite + pragmas
│   ├── Migrations.php      # Schema creation
│   ├── Settings.php        # Site settings (DB-backed key-value)
│   ├── Auth/
│   │   ├── UserService.php
│   │   ├── TokenService.php
│   │   ├── Guard.php       # Auth middleware
│   │   ├── Policy.php      # Role/permission checks
│   │   └── ModLog.php      # Moderation audit log
│   ├── Media/
│   │   ├── MediaService.php
│   │   ├── ThumbService.php
│   │   └── Mime.php
│   ├── Pools/
│   │   └── PoolService.php
│   ├── Social/
│   │   ├── CommentService.php
│   │   ├── FavoriteService.php
│   │   └── VoteService.php
│   ├── Http/
│   │   ├── Csrf.php
│   │   ├── Flash.php
│   │   └── Response.php
│   ├── Install/
│   │   └── InstallService.php
│   └── Templates/
│       └── Renderer.php
├── templates/              # Twig templates (HTML, no JS)
├── assets/
│   └── app.src.css         # Tailwind + Basecoat CSS source
├── storage/
│   ├── uploads/            # Original media files
│   └── thumbs/             # Thumbnails/posters
└── data/                   # SQLite database
```
