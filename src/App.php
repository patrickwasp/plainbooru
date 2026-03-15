<?php

declare(strict_types=1);

namespace Plainbooru;

use Plainbooru\Db;
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
            $pageSize = 18;
            $data     = MediaService::getList($page, $pageSize);
            $sidebar  = $renderer->partial('sidebar_tags', [
                'sidebar_tags' => MediaService::getPopularTags(20),
            ]);
            $html = $renderer->render('home', [
                'title'     => 'plainbooru',
                'media'     => $data['results'],
                'total'     => $data['total'],
                'page'      => $page,
                'page_size' => $pageSize,
                'sidebar'   => $sidebar,
            ]);
            $resp->getBody()->write($html);
            return $resp->withHeader('Content-Type', 'text/html; charset=utf-8');
        });

        // POST /theme – toggle dark/light mode cookie
        $app->post('/theme', function (Request $req, Response $resp) {
            $params = $req->getParsedBody();
            $theme  = ($params['theme'] ?? 'light') === 'dark' ? 'dark' : 'light';
            $return = $params['return'] ?? '/';
            if (!str_starts_with($return, '/') || str_starts_with($return, '//')) {
                $return = '/';
            }
            setcookie('theme', $theme, [
                'expires'  => time() + 60 * 60 * 24 * 365,
                'path'     => '/',
                'samesite' => 'Lax',
                'httponly' => false,
            ]);
            return $resp->withStatus(302)->withHeader('Location', $return);
        });

        // GET /upload
        $app->get('/upload', function (Request $req, Response $resp) use ($renderer) {
            $html = $renderer->render('upload', ['title' => 'Upload – plainbooru', 'error' => null]);
            $resp->getBody()->write($html);
            return $resp->withHeader('Content-Type', 'text/html; charset=utf-8');
        });

        // POST /upload
        $app->post('/upload', function (Request $req, Response $resp) use ($renderer) {
            $allFiles = $req->getUploadedFiles();
            $params   = $req->getParsedBody();

            // Accept files[] (multi) or legacy file (single)
            $uploaded = $allFiles['files'] ?? ($allFiles['file'] ?? []);
            if (!is_array($uploaded)) {
                $uploaded = [$uploaded];
            }
            $uploaded = array_filter($uploaded, fn($f) => $f && $f->getError() === UPLOAD_ERR_OK);

            if (empty($uploaded)) {
                $first = is_array($allFiles['files'] ?? null) ? ($allFiles['files'][0] ?? null) : ($allFiles['files'] ?? $allFiles['file'] ?? null);
                $html = $renderer->render('upload', [
                    'title' => 'Upload – plainbooru',
                    'error' => 'Upload failed: ' . self::uploadError($first),
                ]);
                $resp->getBody()->write($html);
                return $resp->withStatus(400)->withHeader('Content-Type', 'text/html; charset=utf-8');
            }

            $errors  = [];
            $lastId  = null;
            foreach ($uploaded as $file) {
                $tmpPath = tempnam(sys_get_temp_dir(), 'pb_');
                $file->moveTo($tmpPath);
                $phpFile = [
                    'name'     => $file->getClientFilename(),
                    'tmp_name' => $tmpPath,
                    'size'     => $file->getSize(),
                    'error'    => UPLOAD_ERR_OK,
                ];
                try {
                    $media  = MediaService::storeFromPath($tmpPath, $phpFile, $params['tags'] ?? '');
                    $lastId = $media['id'];
                } catch (\RuntimeException $e) {
                    $errors[] = ($file->getClientFilename() ?? 'file') . ': ' . $e->getMessage();
                } finally {
                    @unlink($tmpPath);
                }
            }

            if (!empty($errors) && $lastId === null) {
                $html = $renderer->render('upload', [
                    'title' => 'Upload – plainbooru',
                    'error' => implode(' | ', $errors),
                ]);
                $resp->getBody()->write($html);
                return $resp->withStatus(400)->withHeader('Content-Type', 'text/html; charset=utf-8');
            }

            return $resp->withStatus(302)->withHeader('Location', '/m/' . $lastId);
        });

        // GET /m/{id}
        $app->get('/m/{id:[0-9]+}', function (Request $req, Response $resp, array $args) use ($renderer) {
            $media = MediaService::getById((int)$args['id']);
            if (!$media) {
                $html = $renderer->render('error', ['title' => 'Not Found', 'message' => 'Media not found.', 'code' => 404]);
                $resp->getBody()->write($html);
                return $resp->withStatus(404)->withHeader('Content-Type', 'text/html; charset=utf-8');
            }
            $html = $renderer->render('post', [
                'title'     => 'Post #' . $media['id'] . ' – plainbooru',
                'media'     => $media,
                'pools'     => PoolService::getPoolsForMedia((int)$args['id']),
                'bodyClass' => 'overflow-hidden',
                'mainClass' => 'flex-1 flex overflow-hidden',
            ]);
            $resp->getBody()->write($html);
            return $resp->withHeader('Content-Type', 'text/html; charset=utf-8');
        });

        // POST /m/{id}/tags – add a tag from the post page sidebar
        $app->post('/m/{id:[0-9]+}/tags', function (Request $req, Response $resp, array $args) {
            $id    = (int)$args['id'];
            $media = MediaService::getById($id);
            if (!$media) {
                return $resp->withStatus(404);
            }
            $body = (array)($req->getParsedBody() ?? []);
            $tag  = trim($body['tag'] ?? '');
            if ($tag !== '') {
                MediaService::addTags($media, $tag);
            }
            return $resp->withStatus(302)->withHeader('Location', '/m/' . $id);
        });

        // POST /m/{id}/tags/remove
        $app->post('/m/{id:[0-9]+}/tags/remove', function (Request $req, Response $resp, array $args) {
            $id   = (int)$args['id'];
            $body = (array)($req->getParsedBody() ?? []);
            $tag  = trim($body['tag'] ?? '');
            if ($tag !== '') {
                MediaService::removeTag($id, $tag);
            }
            return $resp->withStatus(302)->withHeader('Location', '/m/' . $id);
        });

        // POST /m/{id}/delete
        $app->post('/m/{id:[0-9]+}/delete', function (Request $req, Response $resp, array $args) {
            self::requireAdmin($req);
            MediaService::delete((int)$args['id']);
            return $resp->withStatus(302)->withHeader('Location', '/');
        });

        // GET /search
        $app->get('/search', function (Request $req, Response $resp) use ($renderer) {
            $params   = $req->getQueryParams();
            $tags     = $params['tags'] ?? '';
            $q        = $params['q'] ?? '';
            $page     = max(1, (int)($params['page'] ?? 1));
            $pageSize = 18;
            $data     = MediaService::search($tags, $q, $page, $pageSize);
            $mediaIds = array_column($data['results'], 'id');
            $sidebar  = $renderer->partial('sidebar_tags', [
                'sidebar_tags' => MediaService::getTagsForMediaSet($mediaIds),
            ]);
            $html = $renderer->render('search', [
                'title'     => 'Search – plainbooru',
                'media'     => $data['results'],
                'total'     => $data['total'],
                'page'      => $page,
                'page_size' => $pageSize,
                'tags'      => $tags,
                'q'         => $q,
                'sidebar'   => $sidebar,
            ]);
            $resp->getBody()->write($html);
            return $resp->withHeader('Content-Type', 'text/html; charset=utf-8');
        });

        // GET /t – redirect helper for tag browse form
        $app->get('/t', function (Request $req, Response $resp) {
            $params = $req->getQueryParams();
            $tag    = trim($params['name'] ?? '');
            if ($tag === '') {
                return $resp->withStatus(302)->withHeader('Location', '/tags');
            }
            return $resp->withStatus(302)->withHeader('Location', '/t/' . urlencode($tag));
        });

        // GET /t/{tag}
        $app->get('/t/{tag}', function (Request $req, Response $resp, array $args) use ($renderer) {
            $tag      = $args['tag'];
            $params   = $req->getQueryParams();
            $page     = max(1, (int)($params['page'] ?? 1));
            $pageSize = 18;
            $data     = MediaService::getByTag($tag, $page, $pageSize);
            $mediaIds = array_column($data['results'], 'id');
            $sidebar  = $renderer->partial('sidebar_tags', [
                'sidebar_tags' => MediaService::getTagsForMediaSet($mediaIds),
            ]);
            $html = $renderer->render('search', [
                'title'     => "Tag: $tag – plainbooru",
                'media'     => $data['results'],
                'total'     => $data['total'],
                'page'      => $page,
                'page_size' => $pageSize,
                'tags'      => $tag,
                'q'         => '',
                'sidebar'   => $sidebar,
            ]);
            $resp->getBody()->write($html);
            return $resp->withHeader('Content-Type', 'text/html; charset=utf-8');
        });

        // GET /tags
        $app->get('/tags', function (Request $req, Response $resp) use ($renderer) {
            $tags    = MediaService::getAllTags();
            $sidebar = $renderer->partial('sidebar_tag_search', []);
            $html    = $renderer->render('tags', [
                'title'   => 'Tags – plainbooru',
                'tags'    => $tags,
                'sidebar' => $sidebar,
            ]);
            $resp->getBody()->write($html);
            return $resp->withHeader('Content-Type', 'text/html; charset=utf-8');
        });

        // POST /tags – create a standalone tag
        $app->post('/tags', function (Request $req, Response $resp) {
            $params = $req->getParsedBody();
            $name   = trim($params['name'] ?? '');
            if ($name !== '') {
                $normalized = MediaService::parseTags($name);
                if ($normalized) {
                    $pdo = Db::get();
                    $pdo->prepare('INSERT OR IGNORE INTO tags (name) VALUES (?)')->execute([$normalized[0]]);
                }
            }
            return $resp->withStatus(302)->withHeader('Location', '/tags');
        });

        // POST /tags/{name}/delete
        $app->post('/tags/{name}/delete', function (Request $req, Response $resp, array $args) {
            self::requireAdmin($req);
            MediaService::deleteTag(urldecode($args['name']));
            return $resp->withStatus(302)->withHeader('Location', '/tags');
        });

        // ── Pools HTML ───────────────────────────────────────────────────────

        // GET /pools
        $app->get('/pools', function (Request $req, Response $resp) use ($renderer) {
            $params  = $req->getQueryParams();
            $page    = max(1, (int)($params['page'] ?? 1));
            $data    = PoolService::getList($page, 18);
            $sidebar = $renderer->partial('sidebar_create_pool', ['error' => null]);
            $html    = $renderer->render('pools', [
                'title'     => 'Pools – plainbooru',
                'pools'     => $data['results'],
                'total'     => $data['total'],
                'page'      => $page,
                'page_size' => 18,
                'error'     => null,
                'sidebar'   => $sidebar,
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
                return $resp->withStatus(302)->withHeader('Location', '/pools/' . $pool['id'] . '/edit');
            } catch (\RuntimeException $e) {
                $data    = PoolService::getList(1, 18);
                $sidebar = $renderer->partial('sidebar_create_pool', ['error' => $e->getMessage()]);
                $html    = $renderer->render('pools', [
                    'title'     => 'Pools – plainbooru',
                    'pools'     => $data['results'],
                    'total'     => $data['total'],
                    'page'      => 1,
                    'page_size' => 18,
                    'error'     => $e->getMessage(),
                    'sidebar'   => $sidebar,
                ]);
                $resp->getBody()->write($html);
                return $resp->withStatus(400)->withHeader('Content-Type', 'text/html; charset=utf-8');
            }
        });

        // GET /pools/{id}/edit — must be before GET /pools/{id}
        $app->get('/pools/{id:[0-9]+}/edit', function (Request $req, Response $resp, array $args) use ($renderer) {
            $pool = PoolService::getById((int)$args['id']);
            if (!$pool) {
                $html = $renderer->render('error', ['title' => 'Not Found', 'message' => 'Pool not found.', 'code' => 404]);
                $resp->getBody()->write($html);
                return $resp->withStatus(404)->withHeader('Content-Type', 'text/html; charset=utf-8');
            }
            $params     = $req->getQueryParams();
            $searchTags = trim($params['search_tags'] ?? '');
            $searchPage = max(1, (int)($params['search_page'] ?? 1));
            $searchData = $searchTags !== '' ? MediaService::search($searchTags, '', $searchPage, 24) : null;
            $poolItemIds = array_column($pool['items'] ?? [], 'id');
            $html = $renderer->render('pool_edit', [
                'title'        => 'Edit: ' . $pool['name'] . ' – plainbooru',
                'pool'         => $pool,
                'error'        => null,
                'search_tags'  => $searchTags,
                'search_page'  => $searchPage,
                'search_data'  => $searchData,
                'pool_item_ids' => $poolItemIds,
            ]);
            $resp->getBody()->write($html);
            return $resp->withHeader('Content-Type', 'text/html; charset=utf-8');
        });

        // POST /pools/{id}/update
        $app->post('/pools/{id:[0-9]+}/update', function (Request $req, Response $resp, array $args) use ($renderer) {
            self::requireAdmin($req);
            $poolId = (int)$args['id'];
            $params = $req->getParsedBody();
            $name   = trim($params['name'] ?? '');
            $desc   = trim($params['description'] ?? '') ?: null;
            try {
                PoolService::update($poolId, $name, $desc);
            } catch (\RuntimeException $e) {
                $pool = PoolService::getById($poolId);
                $html = $renderer->render('pool_edit', [
                    'title' => 'Edit: ' . ($pool['name'] ?? '') . ' – plainbooru',
                    'pool'  => $pool,
                    'error' => $e->getMessage(),
                ]);
                $resp->getBody()->write($html);
                return $resp->withStatus(400)->withHeader('Content-Type', 'text/html; charset=utf-8');
            }
            return $resp->withStatus(302)->withHeader('Location', '/pools/' . $poolId . '/edit');
        });

        // POST /pools/{id}/tags – add tag to pool
        $app->post('/pools/{id:[0-9]+}/tags', function (Request $req, Response $resp, array $args) {
            self::requireAdmin($req);
            $poolId = (int)$args['id'];
            $params = $req->getParsedBody();
            $tag    = trim($params['tag'] ?? '');
            if ($tag !== '') {
                PoolService::addTag($poolId, $tag);
            }
            $return = $params['return'] ?? '/pools/' . $poolId . '/edit';
            if (!str_starts_with($return, '/') || str_starts_with($return, '//')) {
                $return = '/pools/' . $poolId . '/edit';
            }
            return $resp->withStatus(302)->withHeader('Location', $return);
        });

        // POST /pools/{id}/tags/remove
        $app->post('/pools/{id:[0-9]+}/tags/remove', function (Request $req, Response $resp, array $args) {
            self::requireAdmin($req);
            $poolId = (int)$args['id'];
            $params = $req->getParsedBody();
            $tag    = trim($params['tag'] ?? '');
            if ($tag !== '') {
                PoolService::removeTag($poolId, $tag);
            }
            return $resp->withStatus(302)->withHeader('Location', '/pools/' . $poolId . '/edit');
        });

        // POST /pools/{id}/upload – upload one or more files and add to pool
        $app->post('/pools/{id:[0-9]+}/upload', function (Request $req, Response $resp, array $args) use ($renderer) {
            self::requireAdmin($req);
            $poolId  = (int)$args['id'];
            $allFiles = $req->getUploadedFiles();
            $params  = $req->getParsedBody();

            // Accept files[] (multi-upload) or legacy file (single)
            $uploaded = $allFiles['files'] ?? ($allFiles['file'] ?? []);
            if (!is_array($uploaded)) {
                $uploaded = [$uploaded];
            }
            $uploaded = array_filter($uploaded, fn($f) => $f && $f->getError() === UPLOAD_ERR_OK);

            if (empty($uploaded)) {
                $pool = PoolService::getById($poolId);
                $html = $renderer->render('pool_edit', [
                    'title' => 'Edit: ' . ($pool['name'] ?? '') . ' – plainbooru',
                    'pool'  => $pool,
                    'error' => 'No valid files received. Check file types and try again.',
                ]);
                $resp->getBody()->write($html);
                return $resp->withStatus(400)->withHeader('Content-Type', 'text/html; charset=utf-8');
            }

            $errors = [];
            foreach ($uploaded as $file) {
                $tmpPath = tempnam(sys_get_temp_dir(), 'pb_');
                $file->moveTo($tmpPath);
                $phpFile = [
                    'name'     => $file->getClientFilename(),
                    'tmp_name' => $tmpPath,
                    'size'     => $file->getSize(),
                    'error'    => UPLOAD_ERR_OK,
                ];
                try {
                    $media = MediaService::storeFromPath($tmpPath, $phpFile, $params['tags'] ?? '');
                    PoolService::addItem($poolId, (int)$media['id']);
                } catch (\RuntimeException $e) {
                    $errors[] = ($file->getClientFilename() ?? 'file') . ': ' . $e->getMessage();
                } finally {
                    @unlink($tmpPath);
                }
            }

            if (!empty($errors)) {
                $pool = PoolService::getById($poolId);
                $html = $renderer->render('pool_edit', [
                    'title' => 'Edit: ' . ($pool['name'] ?? '') . ' – plainbooru',
                    'pool'  => $pool,
                    'error' => implode(' | ', $errors),
                ]);
                $resp->getBody()->write($html);
                return $resp->withStatus(400)->withHeader('Content-Type', 'text/html; charset=utf-8');
            }

            return $resp->withStatus(302)->withHeader('Location', '/pools/' . $poolId . '/edit#items');
        });

        // POST /pools/{id}/delete
        $app->post('/pools/{id:[0-9]+}/delete', function (Request $req, Response $resp, array $args) {
            self::requireAdmin($req);
            PoolService::delete((int)$args['id']);
            return $resp->withStatus(302)->withHeader('Location', '/pools');
        });

        // GET /pools/{poolId}/m/{mediaId} — pool-context media viewer
        $app->get('/pools/{poolId:[0-9]+}/m/{mediaId:[0-9]+}', function (Request $req, Response $resp, array $args) use ($renderer) {
            $pool  = PoolService::getById((int)$args['poolId']);
            $media = MediaService::getById((int)$args['mediaId']);
            if (!$pool || !$media) {
                $html = $renderer->render('error', ['title' => 'Not Found', 'message' => 'Not found.', 'code' => 404]);
                $resp->getBody()->write($html);
                return $resp->withStatus(404)->withHeader('Content-Type', 'text/html; charset=utf-8');
            }
            $items    = array_column($pool['items'], 'id');
            $idx      = array_search((int)$args['mediaId'], $items);
            $poolPrev = ($idx !== false && $idx > 0)                   ? $items[$idx - 1] : null;
            $poolNext = ($idx !== false && $idx < count($items) - 1)   ? $items[$idx + 1] : null;
            $html = $renderer->render('post_pool', [
                'title'      => 'Post #' . $media['id'] . ' – ' . $pool['name'] . ' – plainbooru',
                'media'      => $media,
                'pools'      => PoolService::getPoolsForMedia((int)$args['mediaId']),
                'pool'       => $pool,
                'pool_prev'  => $poolPrev,
                'pool_next'  => $poolNext,
                'pool_pos'   => $idx !== false ? $idx + 1 : '?',
                'pool_total' => count($items),
                'bodyClass'  => 'overflow-hidden',
                'mainClass'  => 'flex-1 flex overflow-hidden',
            ]);
            $resp->getBody()->write($html);
            return $resp->withHeader('Content-Type', 'text/html; charset=utf-8');
        });

        // GET /pools/{id}
        $app->get('/pools/{id:[0-9]+}', function (Request $req, Response $resp, array $args) use ($renderer) {
            $pool = PoolService::getById((int)$args['id']);
            if (!$pool) {
                $html = $renderer->render('error', ['title' => 'Not Found', 'message' => 'Pool not found.', 'code' => 404]);
                $resp->getBody()->write($html);
                return $resp->withStatus(404)->withHeader('Content-Type', 'text/html; charset=utf-8');
            }
            $sidebar = $renderer->partial('sidebar_pool', ['pool' => $pool]);
            $html    = $renderer->render('pool', [
                'title'   => $pool['name'] . ' – plainbooru',
                'pool'    => $pool,
                'sidebar' => $sidebar,
            ]);
            $resp->getBody()->write($html);
            return $resp->withHeader('Content-Type', 'text/html; charset=utf-8');
        });

        // POST /pools/{id}/media-search – redirect to edit page with search params + anchor
        $app->post('/pools/{id:[0-9]+}/media-search', function (Request $req, Response $resp, array $args) {
            $poolId     = (int)$args['id'];
            $params     = $req->getParsedBody();
            $searchTags = trim($params['search_tags'] ?? '');
            $location   = '/pools/' . $poolId . '/edit';
            if ($searchTags !== '') {
                $location .= '?' . http_build_query(['search_tags' => $searchTags]);
            }
            $location .= '#add-media';
            return $resp->withStatus(302)->withHeader('Location', $location);
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
                $pool = PoolService::getById($poolId);
                $html = $renderer->render('pool_edit', ['title' => ($pool['name'] ?? 'Pool') . ' – plainbooru', 'pool' => $pool, 'error' => $e->getMessage()]);
                $resp->getBody()->write($html);
                return $resp->withStatus(400)->withHeader('Content-Type', 'text/html; charset=utf-8');
            }
            $return = trim($params['return'] ?? '');
            if ($return === '' || !str_starts_with($return, '/') || str_starts_with($return, '//')) {
                $return = '/pools/' . $poolId . '/edit';
            }
            return $resp->withStatus(302)->withHeader('Location', $return);
        });

        // POST /pools/{id}/reorder
        $app->post('/pools/{id:[0-9]+}/reorder', function (Request $req, Response $resp, array $args) {
            self::requireAdmin($req);
            $poolId = (int)$args['id'];
            $params = $req->getParsedBody();
            $mediaIds = [];
            if (!empty($params['item_ids'])) {
                $raw      = preg_split('/[\s,]+/', trim($params['item_ids']), -1, PREG_SPLIT_NO_EMPTY);
                $mediaIds = array_map('intval', $raw);
            } elseif (!empty($params['item_id']) && is_array($params['item_id'])) {
                $mediaIds = array_map('intval', $params['item_id']);
            }
            try {
                PoolService::reorder($poolId, $mediaIds);
            } catch (\Throwable $e) {
                // ignore reorder errors silently
            }
            return $resp->withStatus(302)->withHeader('Location', '/pools/' . $poolId . '/edit#items');
        });

        // POST /pools/{id}/move – shift one item one position left or right
        $app->post('/pools/{id:[0-9]+}/move', function (Request $req, Response $resp, array $args) {
            self::requireAdmin($req);
            $poolId    = (int)$args['id'];
            $params    = $req->getParsedBody();
            $mediaId   = (int)($params['media_id'] ?? 0);
            $direction = $params['direction'] ?? '';
            $pool      = PoolService::getById($poolId);
            $ids       = array_column($pool['items'] ?? [], 'id');
            $idx       = array_search($mediaId, $ids);
            if ($idx !== false) {
                if ($direction === 'prev' && $idx > 0) {
                    [$ids[$idx - 1], $ids[$idx]] = [$ids[$idx], $ids[$idx - 1]];
                } elseif ($direction === 'next' && $idx < count($ids) - 1) {
                    [$ids[$idx], $ids[$idx + 1]] = [$ids[$idx + 1], $ids[$idx]];
                }
                PoolService::reorder($poolId, $ids);
            }
            return $resp->withStatus(302)->withHeader('Location', '/pools/' . $poolId . '/edit#items');
        });

        // POST /pools/{id}/remove
        $app->post('/pools/{id:[0-9]+}/remove', function (Request $req, Response $resp, array $args) {
            self::requireAdmin($req);
            $poolId  = (int)$args['id'];
            $params  = $req->getParsedBody();
            $mediaId = (int)($params['media_id'] ?? 0);
            PoolService::removeItem($poolId, $mediaId);
            return $resp->withStatus(302)->withHeader('Location', '/pools/' . $poolId . '/edit#items');
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
                $media = MediaService::storeFromPath($tmpPath, $phpFile, $params['tags'] ?? '');
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
            $pageSize = min(100, max(1, (int)($params['page_size'] ?? 18)));
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
            $pageSize = min(100, max(1, (int)($params['page_size'] ?? 18)));
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
