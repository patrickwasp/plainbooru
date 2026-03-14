<?php

declare(strict_types=1);

namespace Plainbooru;

use Plainbooru\Media\MediaService;
use Plainbooru\Media\ThumbService;
use Plainbooru\Pools\PoolService;
use Plainbooru\Templates\Renderer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;
use Slim\App as SlimApp;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\StreamFactory;

final class App
{
    public static function create(): SlimApp
    {
        Config::load();

        $app = AppFactory::create();
        $app->addErrorMiddleware(true, true, true);

        $renderer = new Renderer(Config::rootPath() . '/templates');

        // Security headers middleware
        $app->add(function (Request $req, $handler) {
            $resp = $handler->handle($req);
            return $resp
                ->withHeader('X-Content-Type-Options', 'nosniff')
                ->withHeader('X-Frame-Options', 'SAMEORIGIN')
                ->withHeader('Referrer-Policy', 'same-origin');
        });

        // ── HTML Routes ──────────────────────────────────────────────────────

        // GET /
        $app->get('/', function (Request $req, Response $resp) use ($renderer) {
            $params   = $req->getQueryParams();
            $page     = max(1, (int)($params['page'] ?? 1));
            $pageSize = 20;
            $data     = MediaService::getList($page, $pageSize);

            $html = $renderer->render('home', [
                'title'     => 'plainbooru',
                'media'     => $data['results'],
                'total'     => $data['total'],
                'page'      => $page,
                'page_size' => $pageSize,
            ]);
            $resp->getBody()->write($html);
            return $resp->withHeader('Content-Type', 'text/html; charset=utf-8');
        });

        // GET /upload
        $app->get('/upload', function (Request $req, Response $resp) use ($renderer) {
            $html = $renderer->render('upload', ['title' => 'Upload – plainbooru', 'error' => null]);
            $resp->getBody()->write($html);
            return $resp->withHeader('Content-Type', 'text/html; charset=utf-8');
        });

        // POST /upload
        $app->post('/upload', function (Request $req, Response $resp) use ($renderer) {
            $files  = $req->getUploadedFiles();
            $params = $req->getParsedBody();
            /** @var UploadedFileInterface|null $file */
            $file = $files['file'] ?? null;

            if (!$file || $file->getError() !== UPLOAD_ERR_OK) {
                $html = $renderer->render('upload', [
                    'title' => 'Upload – plainbooru',
                    'error' => 'Upload failed: ' . self::uploadError($file),
                ]);
                $resp->getBody()->write($html);
                return $resp->withStatus(400)->withHeader('Content-Type', 'text/html; charset=utf-8');
            }

            // Move to tmp so MediaService can work with it
            $tmpPath = tempnam(sys_get_temp_dir(), 'pb_');
            $file->moveTo($tmpPath);

            $phpFile = [
                'name'     => $file->getClientFilename(),
                'tmp_name' => $tmpPath,
                'size'     => $file->getSize(),
                'error'    => UPLOAD_ERR_OK,
            ];

            try {
                // We already moved it, so use copy instead of move_uploaded_file
                $media = MediaService::storeFromPath($tmpPath, $phpFile, $params['tags'] ?? '', $params['source'] ?? null);
            } catch (\RuntimeException $e) {
                @unlink($tmpPath);
                $html = $renderer->render('upload', [
                    'title' => 'Upload – plainbooru',
                    'error' => $e->getMessage(),
                ]);
                $resp->getBody()->write($html);
                return $resp->withStatus(400)->withHeader('Content-Type', 'text/html; charset=utf-8');
            }
            @unlink($tmpPath);

            return $resp->withStatus(302)->withHeader('Location', '/m/' . $media['id']);
        });

        // GET /m/{id}
        $app->get('/m/{id:[0-9]+}', function (Request $req, Response $resp, array $args) use ($renderer) {
            $media = MediaService::getById((int)$args['id']);
            if (!$media) {
                $html = $renderer->render('error', ['title' => 'Not Found', 'message' => 'Media not found.', 'code' => 404]);
                $resp->getBody()->write($html);
                return $resp->withStatus(404)->withHeader('Content-Type', 'text/html; charset=utf-8');
            }
            // prev / next
            $pdo  = Db::get();
            $prev = $pdo->prepare("SELECT id FROM media WHERE id < ? ORDER BY id DESC LIMIT 1");
            $prev->execute([$args['id']]);
            $prevId = $prev->fetchColumn();
            $next = $pdo->prepare("SELECT id FROM media WHERE id > ? ORDER BY id ASC LIMIT 1");
            $next->execute([$args['id']]);
            $nextId = $next->fetchColumn();

            $html = $renderer->render('post', [
                'title'  => 'Post #' . $media['id'] . ' – plainbooru',
                'media'  => $media,
                'prevId' => $prevId ?: null,
                'nextId' => $nextId ?: null,
            ]);
            $resp->getBody()->write($html);
            return $resp->withHeader('Content-Type', 'text/html; charset=utf-8');
        });

        // GET /search
        $app->get('/search', function (Request $req, Response $resp) use ($renderer) {
            $params   = $req->getQueryParams();
            $tags     = $params['tags'] ?? '';
            $q        = $params['q'] ?? '';
            $page     = max(1, (int)($params['page'] ?? 1));
            $pageSize = 20;
            $data     = MediaService::search($tags, $q, $page, $pageSize);

            $html = $renderer->render('search', [
                'title'     => 'Search – plainbooru',
                'media'     => $data['results'],
                'total'     => $data['total'],
                'page'      => $page,
                'page_size' => $pageSize,
                'tags'      => $tags,
                'q'         => $q,
            ]);
            $resp->getBody()->write($html);
            return $resp->withHeader('Content-Type', 'text/html; charset=utf-8');
        });

        // GET /t/{tag}
        $app->get('/t/{tag}', function (Request $req, Response $resp, array $args) use ($renderer) {
            $tag      = $args['tag'];
            $params   = $req->getQueryParams();
            $page     = max(1, (int)($params['page'] ?? 1));
            $pageSize = 20;
            $data     = MediaService::getByTag($tag, $page, $pageSize);

            $html = $renderer->render('search', [
                'title'     => "Tag: $tag – plainbooru",
                'media'     => $data['results'],
                'total'     => $data['total'],
                'page'      => $page,
                'page_size' => $pageSize,
                'tags'      => $tag,
                'q'         => '',
            ]);
            $resp->getBody()->write($html);
            return $resp->withHeader('Content-Type', 'text/html; charset=utf-8');
        });

        // GET /tags
        $app->get('/tags', function (Request $req, Response $resp) use ($renderer) {
            $tags = MediaService::getAllTags();
            $html = $renderer->render('tags', ['title' => 'Tags – plainbooru', 'tags' => $tags]);
            $resp->getBody()->write($html);
            return $resp->withHeader('Content-Type', 'text/html; charset=utf-8');
        });

        // ── Pools HTML ───────────────────────────────────────────────────────

        // GET /pools
        $app->get('/pools', function (Request $req, Response $resp) use ($renderer) {
            $params   = $req->getQueryParams();
            $page     = max(1, (int)($params['page'] ?? 1));
            $data     = PoolService::getList($page, 20);
            $html     = $renderer->render('pools', [
                'title'     => 'Pools – plainbooru',
                'pools'     => $data['results'],
                'total'     => $data['total'],
                'page'      => $page,
                'page_size' => 20,
                'error'     => null,
            ]);
            $resp->getBody()->write($html);
            return $resp->withHeader('Content-Type', 'text/html; charset=utf-8');
        });

        // POST /pools
        $app->post('/pools', function (Request $req, Response $resp) use ($renderer) {
            self::requireAdmin($req);
            $params = $req->getParsedBody();
            $name   = trim($params['name'] ?? '');
            $desc   = trim($params['description'] ?? '') ?: null;
            try {
                $pool = PoolService::create($name, $desc);
                return $resp->withStatus(302)->withHeader('Location', '/pools/' . $pool['id']);
            } catch (\RuntimeException $e) {
                $data = PoolService::getList(1, 20);
                $html = $renderer->render('pools', [
                    'title'     => 'Pools – plainbooru',
                    'pools'     => $data['results'],
                    'total'     => $data['total'],
                    'page'      => 1,
                    'page_size' => 20,
                    'error'     => $e->getMessage(),
                ]);
                $resp->getBody()->write($html);
                return $resp->withStatus(400)->withHeader('Content-Type', 'text/html; charset=utf-8');
            }
        });

        // GET /pools/{id}
        $app->get('/pools/{id:[0-9]+}', function (Request $req, Response $resp, array $args) use ($renderer) {
            $pool = PoolService::getById((int)$args['id']);
            if (!$pool) {
                $html = $renderer->render('error', ['title' => 'Not Found', 'message' => 'Pool not found.', 'code' => 404]);
                $resp->getBody()->write($html);
                return $resp->withStatus(404)->withHeader('Content-Type', 'text/html; charset=utf-8');
            }
            $html = $renderer->render('pool', [
                'title' => $pool['name'] . ' – plainbooru',
                'pool'  => $pool,
                'error' => null,
            ]);
            $resp->getBody()->write($html);
            return $resp->withHeader('Content-Type', 'text/html; charset=utf-8');
        });

        // POST /pools/{id}/items
        $app->post('/pools/{id:[0-9]+}/items', function (Request $req, Response $resp, array $args) use ($renderer) {
            self::requireAdmin($req);
            $poolId  = (int)$args['id'];
            $params  = $req->getParsedBody();
            $mediaId = (int)($params['media_id'] ?? 0);
            $pos     = isset($params['position']) && $params['position'] !== '' ? (int)$params['position'] : null;
            try {
                PoolService::addItem($poolId, $mediaId, $pos);
            } catch (\RuntimeException $e) {
                // Show pool page with error
                $pool = PoolService::getById($poolId);
                $html = $renderer->render('pool', ['title' => ($pool['name'] ?? 'Pool') . ' – plainbooru', 'pool' => $pool, 'error' => $e->getMessage()]);
                $resp->getBody()->write($html);
                return $resp->withStatus(400)->withHeader('Content-Type', 'text/html; charset=utf-8');
            }
            return $resp->withStatus(302)->withHeader('Location', '/pools/' . $poolId);
        });

        // POST /pools/{id}/reorder
        $app->post('/pools/{id:[0-9]+}/reorder', function (Request $req, Response $resp, array $args) use ($renderer) {
            self::requireAdmin($req);
            $poolId = (int)$args['id'];
            $params = $req->getParsedBody();
            // Accept textarea of newline/comma separated IDs OR item_id[] array
            $mediaIds = [];
            if (!empty($params['item_ids'])) {
                // textarea approach
                $raw = preg_split('/[\s,]+/', trim($params['item_ids']), -1, PREG_SPLIT_NO_EMPTY);
                $mediaIds = array_map('intval', $raw);
            } elseif (!empty($params['item_id']) && is_array($params['item_id'])) {
                $mediaIds = array_map('intval', $params['item_id']);
            }
            try {
                PoolService::reorder($poolId, $mediaIds);
            } catch (\Throwable $e) {
                // ignore reorder errors silently
            }
            return $resp->withStatus(302)->withHeader('Location', '/pools/' . $poolId);
        });

        // POST /pools/{id}/remove
        $app->post('/pools/{id:[0-9]+}/remove', function (Request $req, Response $resp, array $args) use ($renderer) {
            self::requireAdmin($req);
            $poolId  = (int)$args['id'];
            $params  = $req->getParsedBody();
            $mediaId = (int)($params['media_id'] ?? 0);
            PoolService::removeItem($poolId, $mediaId);
            return $resp->withStatus(302)->withHeader('Location', '/pools/' . $poolId);
        });

        // ── Media serving ────────────────────────────────────────────────────

        // GET /file/{id}
        $app->get('/file/{id:[0-9]+}', function (Request $req, Response $resp, array $args) {
            $media = MediaService::getById((int)$args['id']);
            if (!$media) {
                return $resp->withStatus(404);
            }
            $path = Config::uploadsPath() . '/' . $media['stored_name'];
            if (!file_exists($path)) {
                return $resp->withStatus(404);
            }
            // Check for nginx X-Accel-Redirect
            if (Config::get('NGINX_ACCEL') === 'true') {
                return $resp
                    ->withHeader('X-Accel-Redirect', '/internal/uploads/' . $media['stored_name'])
                    ->withHeader('Content-Type', $media['mime'])
                    ->withHeader('Content-Disposition', 'inline; filename="' . $media['original_name'] . '"');
            }
            return self::serveFile($resp, $path, $media['mime'], $media['original_name']);
        });

        // GET /thumb/{id}
        $app->get('/thumb/{id:[0-9]+}', function (Request $req, Response $resp, array $args) {
            $id    = (int)$args['id'];
            $media = MediaService::getById($id);
            if (!$media) {
                return $resp->withStatus(404);
            }
            $ext   = ThumbService::thumbExt();
            $path  = Config::thumbsPath() . '/' . $id . '.' . $ext;
            if (!file_exists($path)) {
                // Try to regenerate
                ThumbService::generate($media['stored_name'], $media['mime'], $id);
            }
            if (!file_exists($path)) {
                return $resp->withStatus(404);
            }
            if (Config::get('NGINX_ACCEL') === 'true') {
                return $resp
                    ->withHeader('X-Accel-Redirect', '/internal/thumbs/' . $id . '.' . $ext)
                    ->withHeader('Content-Type', ThumbService::thumbMime());
            }
            return self::serveFile($resp, $path, ThumbService::thumbMime(), null, 3600);
        });

        // ── REST API v1 ───────────────────────────────────────────────────────

        // POST /api/v1/media
        $app->post('/api/v1/media', function (Request $req, Response $resp) {
            $files = $req->getUploadedFiles();
            /** @var UploadedFileInterface|null $file */
            $file  = $files['file'] ?? null;

            if (!$file || $file->getError() !== UPLOAD_ERR_OK) {
                return self::jsonResp($resp, ['error' => 'No file uploaded or upload error: ' . self::uploadError($file)], 400);
            }

            $tmpPath = tempnam(sys_get_temp_dir(), 'pb_');
            $file->moveTo($tmpPath);

            $params = $req->getParsedBody();
            $phpFile = [
                'name'     => $file->getClientFilename(),
                'tmp_name' => $tmpPath,
                'size'     => $file->getSize(),
                'error'    => UPLOAD_ERR_OK,
            ];

            try {
                $media = MediaService::storeFromPath($tmpPath, $phpFile, $params['tags'] ?? '', $params['source'] ?? null);
            } catch (\RuntimeException $e) {
                @unlink($tmpPath);
                return self::jsonResp($resp, ['error' => $e->getMessage()], 422);
            }
            @unlink($tmpPath);

            return self::jsonResp($resp, self::mediaResource($media), 201);
        });

        // GET /api/v1/media/{id}
        $app->get('/api/v1/media/{id:[0-9]+}', function (Request $req, Response $resp, array $args) {
            $media = MediaService::getById((int)$args['id']);
            if (!$media) {
                return self::jsonResp($resp, ['error' => 'Not found'], 404);
            }
            return self::jsonResp($resp, self::mediaResource($media));
        });

        // DELETE /api/v1/media/{id}
        $app->delete('/api/v1/media/{id:[0-9]+}', function (Request $req, Response $resp, array $args) {
            self::requireAdminApi($req, $resp);
            $deleted = MediaService::delete((int)$args['id']);
            return self::jsonResp($resp, ['deleted' => $deleted], $deleted ? 200 : 404);
        });

        // GET /api/v1/search
        $app->get('/api/v1/search', function (Request $req, Response $resp) {
            $params   = $req->getQueryParams();
            $tags     = $params['tags'] ?? '';
            $q        = $params['q'] ?? '';
            $page     = max(1, (int)($params['page'] ?? 1));
            $pageSize = min(100, max(1, (int)($params['page_size'] ?? 20)));
            $data     = MediaService::search($tags, $q, $page, $pageSize);

            $results = array_map([self::class, 'mediaResource'], $data['results']);
            return self::jsonResp($resp, [
                'page'           => $page,
                'page_size'      => $pageSize,
                'total_estimate' => $data['total'],
                'results'        => $results,
            ]);
        });

        // POST /api/v1/pools
        $app->post('/api/v1/pools', function (Request $req, Response $resp) {
            self::requireAdminApi($req, $resp);
            $body = self::parseJsonBody($req);
            $name = trim($body['name'] ?? '');
            $desc = $body['description'] ?? null;
            try {
                $pool = PoolService::create($name, $desc);
            } catch (\RuntimeException $e) {
                return self::jsonResp($resp, ['error' => $e->getMessage()], 422);
            }
            return self::jsonResp($resp, self::poolResource($pool), 201);
        });

        // GET /api/v1/pools
        $app->get('/api/v1/pools', function (Request $req, Response $resp) {
            $params   = $req->getQueryParams();
            $page     = max(1, (int)($params['page'] ?? 1));
            $pageSize = min(100, max(1, (int)($params['page_size'] ?? 20)));
            $data     = PoolService::getList($page, $pageSize);
            $results  = array_map([self::class, 'poolResource'], $data['results']);
            return self::jsonResp($resp, [
                'page'           => $page,
                'page_size'      => $pageSize,
                'total_estimate' => $data['total'],
                'results'        => $results,
            ]);
        });

        // GET /api/v1/pools/{id}
        $app->get('/api/v1/pools/{id:[0-9]+}', function (Request $req, Response $resp, array $args) {
            $pool = PoolService::getById((int)$args['id']);
            if (!$pool) {
                return self::jsonResp($resp, ['error' => 'Not found'], 404);
            }
            return self::jsonResp($resp, self::poolResource($pool, true));
        });

        // POST /api/v1/pools/{id}/items
        $app->post('/api/v1/pools/{id:[0-9]+}/items', function (Request $req, Response $resp, array $args) {
            self::requireAdminApi($req, $resp);
            $body    = self::parseJsonBody($req);
            $poolId  = (int)$args['id'];
            $mediaId = (int)($body['media_id'] ?? 0);
            $pos     = isset($body['position']) ? (int)$body['position'] : null;
            try {
                PoolService::addItem($poolId, $mediaId, $pos);
            } catch (\RuntimeException $e) {
                return self::jsonResp($resp, ['error' => $e->getMessage()], 422);
            }
            $pool = PoolService::getById($poolId);
            return self::jsonResp($resp, self::poolResource($pool, true), 200);
        });

        // POST /api/v1/pools/{id}/reorder
        $app->post('/api/v1/pools/{id:[0-9]+}/reorder', function (Request $req, Response $resp, array $args) {
            self::requireAdminApi($req, $resp);
            $body     = self::parseJsonBody($req);
            $poolId   = (int)$args['id'];
            $mediaIds = array_map('intval', $body['media_ids'] ?? []);
            PoolService::reorder($poolId, $mediaIds);
            $pool = PoolService::getById($poolId);
            return self::jsonResp($resp, self::poolResource($pool, true));
        });

        // DELETE /api/v1/pools/{id}/items/{media_id}
        $app->delete('/api/v1/pools/{id:[0-9]+}/items/{media_id:[0-9]+}', function (Request $req, Response $resp, array $args) {
            self::requireAdminApi($req, $resp);
            $deleted = PoolService::removeItem((int)$args['id'], (int)$args['media_id']);
            return self::jsonResp($resp, ['deleted' => $deleted], $deleted ? 200 : 404);
        });

        // DELETE /api/v1/pools/{id}
        $app->delete('/api/v1/pools/{id:[0-9]+}', function (Request $req, Response $resp, array $args) {
            self::requireAdminApi($req, $resp);
            $deleted = PoolService::delete((int)$args['id']);
            return self::jsonResp($resp, ['deleted' => $deleted], $deleted ? 200 : 404);
        });

        return $app;
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private static function serveFile(Response $resp, string $path, string $mime, ?string $name = null, int $maxAge = 0): Response
    {
        $stream = (new StreamFactory())->createStreamFromFile($path);
        $r = $resp
            ->withBody($stream)
            ->withHeader('Content-Type', $mime)
            ->withHeader('Content-Length', (string)filesize($path))
            ->withHeader('X-Content-Type-Options', 'nosniff');

        if ($name) {
            $r = $r->withHeader('Content-Disposition', 'inline; filename="' . addslashes($name) . '"');
        }
        if ($maxAge > 0) {
            $r = $r->withHeader('Cache-Control', "public, max-age=$maxAge");
        }
        return $r;
    }

    private static function jsonResp(Response $resp, mixed $data, int $status = 200): Response
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $resp->getBody()->write($json);
        return $resp
            ->withStatus($status)
            ->withHeader('Content-Type', 'application/json');
    }

    private static function parseJsonBody(Request $req): array
    {
        $body = (string)$req->getBody();
        $data = json_decode($body, true);
        return is_array($data) ? $data : [];
    }

    private static function requireAdmin(Request $req): void
    {
        $secret = Config::adminSecret();
        if ($secret === null) {
            return; // no secret set = open
        }
        $params = $req->getQueryParams();
        $header = $req->getHeaderLine('X-Admin-Secret');
        if ($header !== $secret && ($params['admin_secret'] ?? '') !== $secret) {
            throw new \Slim\Exception\HttpForbiddenException($req, 'Admin secret required.');
        }
    }

    private static function requireAdminApi(Request $req, Response $resp): void
    {
        self::requireAdmin($req);
    }

    private static function uploadError(?UploadedFileInterface $file): string
    {
        if ($file === null) {
            return 'No file provided';
        }
        return match ($file->getError()) {
            UPLOAD_ERR_INI_SIZE   => 'File exceeds server limit',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds form limit',
            UPLOAD_ERR_PARTIAL    => 'Partial upload',
            UPLOAD_ERR_NO_FILE    => 'No file selected',
            UPLOAD_ERR_NO_TMP_DIR => 'No temp directory',
            UPLOAD_ERR_CANT_WRITE => 'Cannot write file',
            UPLOAD_ERR_EXTENSION  => 'Upload blocked by extension',
            default               => 'Unknown error',
        };
    }

    private static function mediaResource(array $media): array
    {
        return [
            'id'               => $media['id'],
            'kind'             => $media['kind'],
            'mime'             => $media['mime'],
            'size_bytes'       => $media['size_bytes'],
            'width'            => $media['width'],
            'height'           => $media['height'],
            'duration_seconds' => $media['duration_seconds'],
            'created_at'       => $media['created_at'],
            'source'           => $media['source'],
            'tags'             => $media['tags'] ?? MediaService::getTags((int)$media['id']),
            'urls'             => [
                'page'  => '/m/' . $media['id'],
                'file'  => '/file/' . $media['id'],
                'thumb' => '/thumb/' . $media['id'],
                'api'   => '/api/v1/media/' . $media['id'],
            ],
        ];
    }

    private static function poolResource(array $pool, bool $withItems = false): array
    {
        $res = [
            'id'          => $pool['id'],
            'name'        => $pool['name'],
            'description' => $pool['description'],
            'created_at'  => $pool['created_at'],
            'items_count' => $pool['items_count'] ?? count($pool['items'] ?? []),
            'urls'        => [
                'html' => '/pools/' . $pool['id'],
                'api'  => '/api/v1/pools/' . $pool['id'],
            ],
        ];
        if ($withItems && isset($pool['items'])) {
            $res['items'] = array_map(fn($item) => [
                'position' => $item['position'],
                'media'    => self::mediaResource($item),
            ], $pool['items']);
        }
        return $res;
    }
}
