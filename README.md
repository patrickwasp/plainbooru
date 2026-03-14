# plainbooru

A minimal image/video booru board built with zero client-side JavaScript.

**Stack:** PHP 8.3 · Slim 4 · SQLite · Tailwind CSS v4 · Basecoat UI

---

## Features

- Upload images (JPEG, PNG, WebP, GIF) and videos (MP4, WebM, MOV)
- Tag system with search
- Pools (ordered collections of media)
- Server-rendered HTML — **zero JavaScript shipped to the browser**
- REST API (JSON) for programmatic access
- SHA-256 deduplication
- Automatic thumbnail generation (GD/WebP; ffmpeg poster for videos)

---

## Quick Start

### Docker Compose (recommended)

```bash
cp .env.example .env
docker compose up --build
```

Open http://localhost:8080

### PHP built-in server (no Docker)

Requirements: PHP 8.3+, Composer, GD extension.

```bash
composer install
cp .env.example .env
mkdir -p storage/uploads storage/thumbs data
php -S localhost:8080 -t public
```

Open http://localhost:8080

---

## CSS Build

The compiled CSS is committed (`public/assets/app.css`) so production needs no Node.

To rebuild after template changes:

```bash
npm install
npm run build:css
```

See `scripts/css_build.md` for details.

---

## Environment Variables

See `.env.example` for all options.

| Variable | Default | Description |
|---|---|---|
| `MAX_UPLOAD_MB` | `50` | Maximum file upload size |
| `ADMIN_SECRET` | _(empty)_ | Token to gate mutating endpoints (leave empty for open access) |
| `NGINX_ACCEL` | `false` | Enable nginx X-Accel-Redirect for efficient file serving |

---

## API Examples

### Upload an image

```bash
curl -X POST http://localhost:8080/api/v1/media \
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

### Get media by ID

```bash
curl http://localhost:8080/api/v1/media/1
```

---

## Pools API Examples

### Create a pool

```bash
curl -X POST http://localhost:8080/api/v1/pools \
  -H "Content-Type: application/json" \
  -d '{"name": "Favorites", "description": "My favorite images"}'
```

Response:
```json
{
  "id": 1,
  "name": "Favorites",
  "description": "My favorite images",
  "created_at": "2025-01-01T12:00:00+00:00",
  "items_count": 0,
  "urls": {
    "html": "/pools/1",
    "api": "/api/v1/pools/1"
  }
}
```

### Add media to a pool

```bash
curl -X POST http://localhost:8080/api/v1/pools/1/items \
  -H "Content-Type: application/json" \
  -d '{"media_id": 1}'
```

### Add media at a specific position

```bash
curl -X POST http://localhost:8080/api/v1/pools/1/items \
  -H "Content-Type: application/json" \
  -d '{"media_id": 2, "position": 0}'
```

### Reorder pool items

```bash
curl -X POST http://localhost:8080/api/v1/pools/1/reorder \
  -H "Content-Type: application/json" \
  -d '{"media_ids": [2, 1, 3]}'
```

### Remove an item from a pool

```bash
curl -X DELETE http://localhost:8080/api/v1/pools/1/items/1
```

### List all pools

```bash
curl "http://localhost:8080/api/v1/pools?page=1&page_size=20"
```

### Get pool with items

```bash
curl http://localhost:8080/api/v1/pools/1
```

---

## Routes Reference

### HTML (server-rendered, no JS)

| Method | Path | Description |
|---|---|---|
| GET | `/` | Home: latest uploads grid |
| GET | `/upload` | Upload form |
| POST | `/upload` | Handle upload → redirect to `/m/{id}` |
| GET | `/m/{id}` | Media detail page |
| GET | `/search` | Search by tags (`?tags=`) and/or text (`?q=`) |
| GET | `/t/{tag}` | Tag page |
| GET | `/tags` | All tags listing |
| GET | `/pools` | Pools list + create form |
| POST | `/pools` | Create pool |
| GET | `/pools/{id}` | Pool detail |
| POST | `/pools/{id}/items` | Add media to pool |
| POST | `/pools/{id}/reorder` | Reorder pool items |
| POST | `/pools/{id}/remove` | Remove item from pool |
| GET | `/file/{id}` | Stream original file |
| GET | `/thumb/{id}` | Stream thumbnail |

### REST API (JSON)

| Method | Path | Description |
|---|---|---|
| POST | `/api/v1/media` | Upload media |
| GET | `/api/v1/media/{id}` | Get media |
| DELETE | `/api/v1/media/{id}` | Delete media (requires ADMIN_SECRET) |
| GET | `/api/v1/search` | Search media |
| POST | `/api/v1/pools` | Create pool |
| GET | `/api/v1/pools` | List pools |
| GET | `/api/v1/pools/{id}` | Get pool with items |
| POST | `/api/v1/pools/{id}/items` | Add item to pool |
| POST | `/api/v1/pools/{id}/reorder` | Reorder pool items |
| DELETE | `/api/v1/pools/{id}/items/{media_id}` | Remove item |
| DELETE | `/api/v1/pools/{id}` | Delete pool (requires ADMIN_SECRET) |

---

## Project Structure

```
plainbooru/
├── composer.json           # PHP dependencies (Slim 4, PHP-DI)
├── package.json            # Node devDependencies (Tailwind, Basecoat)
├── docker-compose.yml      # Docker Compose (nginx + php-fpm)
├── .env.example            # Environment template
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
│   ├── Media/
│   │   ├── MediaService.php
│   │   ├── ThumbService.php
│   │   └── Mime.php
│   ├── Pools/
│   │   └── PoolService.php
│   └── Templates/
│       └── Renderer.php
├── templates/              # PHP templates (HTML, no JS)
├── assets/
│   └── app.src.css         # Tailwind + Basecoat CSS source
├── storage/
│   ├── uploads/            # Original media files
│   └── thumbs/             # Thumbnails/posters
├── data/                   # SQLite database
└── scripts/
    ├── init_db.php         # CLI database init
    └── css_build.md        # CSS build instructions
```

---

## Admin Secret

To protect mutating operations (upload, pool creation, deletion), set `ADMIN_SECRET` in `.env`.

Then pass the secret as:
- HTTP header: `X-Admin-Secret: your_secret`
- Query parameter: `?admin_secret=your_secret`

```bash
# With ADMIN_SECRET set:
curl -X POST http://localhost:8080/api/v1/media \
  -H "X-Admin-Secret: your_secret" \
  -F "file=@photo.jpg"
```
