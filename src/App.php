<?php

declare(strict_types=1);

namespace Plainbooru;

use Plainbooru\Auth\Guard;
use Plainbooru\Auth\ModLog;
use Plainbooru\Auth\Policy;
use Plainbooru\Auth\TokenService;
use Plainbooru\Auth\UserService;
use Plainbooru\Install\InstallService;
use Plainbooru\RateLimiter;
use Plainbooru\Db;
use Plainbooru\Http\Csrf;
use Plainbooru\Http\Flash;
use Plainbooru\Settings;
use Plainbooru\Media\MediaService;
use Plainbooru\Media\ThumbService;
use Plainbooru\Pools\PoolService;
use Plainbooru\Social\CommentService;
use Plainbooru\Social\FavoriteService;
use Plainbooru\Social\VoteService;
use Plainbooru\Templates\Renderer;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UploadedFileInterface;
use Slim\App as SlimApp;
use Slim\Exception\HttpNotFoundException;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\StreamFactory;

final class App
{
    public static function create(): SlimApp
    {
        Config::load();

        if (session_status() === PHP_SESSION_NONE) {
            session_start([
                'cookie_httponly' => true,
                'cookie_samesite' => 'Lax',
                'use_strict_mode' => true,
            ]);
        }

        $app = AppFactory::create();

        $renderer = new Renderer(Config::rootPath() . '/templates');

        $errorMiddleware = $app->addErrorMiddleware(false, true, true);
        $errorHandler = function (Request $req, \Throwable $ex) use ($renderer): Response {
            $code = $ex->getCode();

            // Not logged in + forbidden → redirect to login
            if ($code === 403 && UserService::current() === null) {
                $next = urlencode((string)$req->getUri()->getPath());
                $resp = new \Slim\Psr7\Response(302);
                return $resp->withHeader('Location', '/login?next=' . $next);
            }

            [$status, $title, $message] = match(true) {
                $ex instanceof HttpNotFoundException         => [404, 'Not Found',            'The page you\'re looking for doesn\'t exist.'],
                $ex instanceof HttpMethodNotAllowedException => [405, 'Method Not Allowed',   'That request method is not supported here.'],
                $code === 403                               => [403, 'Forbidden',             'You don\'t have permission to access this page.'],
                $code === 404                               => [404, 'Not Found',             'The page you\'re looking for doesn\'t exist.'],
                default                                     => [500, 'Something went wrong',  'An unexpected error occurred. Please try again.'],
            };

            $html = $renderer->render('error', ['title' => $title, 'message' => $message, 'code' => $status]);
            $resp = new \Slim\Psr7\Response($status);
            $resp->getBody()->write($html);
            return $resp->withHeader('Content-Type', 'text/html; charset=utf-8');
        };
        $errorMiddleware->setDefaultErrorHandler($errorHandler);
        $errorMiddleware->setErrorHandler(HttpNotFoundException::class, $errorHandler, true);
        $errorMiddleware->setErrorHandler(HttpMethodNotAllowedException::class, $errorHandler, true);

        // CSRF middleware — verify token on all unsafe methods
        $app->add(function (Request $req, $handler) use ($renderer) {
            $method = strtoupper($req->getMethod());
            if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
                $body      = (array)($req->getParsedBody() ?? []);
                $submitted = $body['_csrf'] ?? '';
                if (!Csrf::verify($submitted)) {
                    $html = $renderer->render('error', [
                        'title'   => 'Forbidden',
                        'message' => 'Invalid or missing security token. Please go back and try again.',
                        'code'    => 403,
                    ]);
                    $resp = new \Slim\Psr7\Response(403);
                    $resp->getBody()->write($html);
                    return $resp->withHeader('Content-Type', 'text/html; charset=utf-8');
                }
            }
            return $handler->handle($req);
        });

        // Security headers middleware
        $app->add(function (Request $req, $handler) {
            $resp = $handler->handle($req);
            return $resp
                ->withHeader('X-Content-Type-Options', 'nosniff')
                ->withHeader('X-Frame-Options', 'SAMEORIGIN')
                ->withHeader('Referrer-Policy', 'same-origin');
        });

        // Bearer token middleware — resolves API user on /api/* routes
        $app->add(function (Request $req, $handler) {
            $path = $req->getUri()->getPath();
            if (str_starts_with($path, '/api/')) {
                $header = $req->getHeaderLine('Authorization');
                if (str_starts_with($header, 'Bearer ')) {
                    $raw     = substr($header, 7);
                    $apiUser = TokenService::verify($raw);
                    if ($apiUser !== null) {
                        $req = $req->withAttribute('api_user', $apiUser);
                    }
                }
            }
            return $handler->handle($req);
        });

        // Maintenance mode — serve 503 to non-admins when maintenance_mode is enabled.
        // Exempt: /admin/*, /login, /logout, /install
        $app->add(function (Request $req, $handler) use ($renderer) {
            $path = $req->getUri()->getPath();
            $exempt = $path === '/login' || $path === '/logout' || $path === '/install'
                || str_starts_with($path, '/admin');
            if (!$exempt && Settings::getBool('maintenance_mode', false)) {
                $user = UserService::current();
                if (!Guard::atLeast('admin', $user)) {
                    $message = Settings::getString('maintenance_message', 'Site is under maintenance. Please check back soon.');
                    $html = $renderer->render('maintenance', [
                        'title'   => 'Maintenance',
                        'message' => $message,
                    ]);
                    $resp = new \Slim\Psr7\Response(503);
                    $resp->getBody()->write($html);
                    return $resp->withHeader('Content-Type', 'text/html; charset=utf-8');
                }
            }
            return $handler->handle($req);
        });

        // Require login — redirect anonymous users to /login when require_login_to_view is on.
        // Exempt: /login, /register, /logout, /install, /api/*
        $app->add(function (Request $req, $handler) {
            $path = $req->getUri()->getPath();
            $exempt = $path === '/login' || $path === '/register' || $path === '/logout'
                || $path === '/install' || str_starts_with($path, '/api/');
            if (!$exempt && Settings::getBool('require_login_to_view', false)) {
                if (UserService::current() === null) {
                    $resp = new \Slim\Psr7\Response(302);
                    return $resp->withHeader('Location', '/login');
                }
            }
            return $handler->handle($req);
        });

        // Install guard — redirect to /install if not yet installed, or away from
        // /install if already done. Added last so it runs outermost (first on request).
        $app->add(function (Request $req, $handler) {
            $path      = $req->getUri()->getPath();
            $installed = InstallService::isInstalled();

            if (!$installed && $path !== '/install') {
                $resp = new \Slim\Psr7\Response(302);
                return $resp->withHeader('Location', '/install');
            }

            if ($installed && $path === '/install') {
                $resp = new \Slim\Psr7\Response(302);
                return $resp->withHeader('Location', '/');
            }

            return $handler->handle($req);
        });

        // ── Install Routes ───────────────────────────────────────────────────

        // GET /install
        $app->get('/install', function (Request $req, Response $resp) use ($renderer) {
            $html = $renderer->render('install', [
                'title'     => 'Install plainbooru',
                'mainClass' => 'flex-1 flex items-center justify-center px-4 py-12',
                'errors'    => [],
                'values'    => [],
            ]);
            $resp->getBody()->write($html);
            return $resp->withHeader('Content-Type', 'text/html; charset=utf-8');
        });

        // POST /install
        $app->post('/install', function (Request $req, Response $resp) use ($renderer) {
            $params = (array)($req->getParsedBody() ?? []);
            $input  = [
                'admin_user'  => trim($params['admin_user'] ?? ''),
                'admin_pass'  => $params['admin_pass'] ?? '',
                'site_title'  => trim($params['site_title'] ?? ''),
            ];

            $errors = InstallService::validate($input);
            if (!empty($errors)) {
                $html = $renderer->render('install', [
                    'title'     => 'Install plainbooru',
                    'mainClass' => 'flex-1 flex items-center justify-center px-4 py-12',
                    'errors'    => $errors,
                    'values'    => $input,
                ]);
                $resp->getBody()->write($html);
                return $resp->withStatus(422)->withHeader('Content-Type', 'text/html; charset=utf-8');
            }

            try {
                InstallService::run($input);
                return $resp->withStatus(302)->withHeader('Location', '/login');
            } catch (\RuntimeException $e) {
                $html = $renderer->render('install', [
                    'title'     => 'Install plainbooru',
                    'mainClass' => 'flex-1 flex items-center justify-center px-4 py-12',
                    'errors'    => [$e->getMessage()],
                    'values'    => $input,
                ]);
                $resp->getBody()->write($html);
                return $resp->withStatus(500)->withHeader('Content-Type', 'text/html; charset=utf-8');
            }
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
                'title'      => 'plainbooru',
                'media'      => $data['results'],
                'total'      => $data['total'],
                'page'       => $page,
                'page_size'  => $pageSize,
                'totalPages' => max(1, (int)ceil($data['total'] / max(1, $pageSize))),
                'sidebar'    => $sidebar,
            ]);
            $resp->getBody()->write($html);
            return $resp->withHeader('Content-Type', 'text/html; charset=utf-8');
        });

        // POST /sidebar – toggle sidebar visibility in session
        $app->post('/sidebar', function (Request $req, Response $resp) {
            $params  = $req->getParsedBody();
            $current = $_SESSION['sidebar'] ?? 'auto';
            $_SESSION['sidebar'] = ($current === 'hidden') ? 'shown' : 'hidden';
            $return = $params['return'] ?? '/';
            if (!str_starts_with($return, '/') || str_starts_with($return, '//')) {
                $return = '/';
            }
            return $resp->withStatus(302)->withHeader('Location', $return);
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

        // GET /login
        $app->get('/login', function (Request $req, Response $resp) use ($renderer) {
            if (UserService::current()) {
                return $resp->withStatus(302)->withHeader('Location', '/');
            }
            $params = $req->getQueryParams();
            $html = $renderer->render('login', [
                'title'                => 'Log in – plainbooru',
                'mainClass'            => 'flex-1 flex items-center justify-center px-4 py-12',
                'error'                => null,
                'next'                 => $params['next'] ?? '/',
                'registration_enabled' => Settings::getBool('registration_enabled', true),
            ]);
            $resp->getBody()->write($html);
            return $resp->withHeader('Content-Type', 'text/html; charset=utf-8');
        });

        // POST /login
        $app->post('/login', function (Request $req, Response $resp) use ($renderer) {
            $params   = $req->getParsedBody();
            $username = trim($params['username'] ?? '');
            $password = $params['password'] ?? '';
            $next     = $params['next'] ?? '/';
            if (!str_starts_with($next, '/') || str_starts_with($next, '//')) {
                $next = '/';
            }
            try {
                UserService::login($username, $password);
                return $resp->withStatus(302)->withHeader('Location', $next);
            } catch (\RuntimeException $e) {
                $html = $renderer->render('login', [
                    'title'                => 'Log in – plainbooru',
                    'mainClass'            => 'flex-1 flex items-center justify-center px-4 py-12',
                    'error'                => $e->getMessage(),
                    'next'                 => $next,
                    'registration_enabled' => Settings::getBool('registration_enabled', true),
                ]);
                $resp->getBody()->write($html);
                return $resp->withStatus(401)->withHeader('Content-Type', 'text/html; charset=utf-8');
            }
        });

        // GET /signup
        $app->get('/signup', function (Request $req, Response $resp) use ($renderer) {
            if (!Settings::getBool('registration_enabled', true)) {
                return $resp->withStatus(302)->withHeader('Location', '/login');
            }
            if (UserService::current()) {
                return $resp->withStatus(302)->withHeader('Location', '/');
            }
            $html = $renderer->render('signup', [
                'title'     => 'Sign up – plainbooru',
                'mainClass' => 'flex-1 flex items-center justify-center px-4 py-12',
                'error'     => null,
            ]);
            $resp->getBody()->write($html);
            return $resp->withHeader('Content-Type', 'text/html; charset=utf-8');
        });

        // POST /signup
        $app->post('/signup', function (Request $req, Response $resp) use ($renderer) {
            if (!Settings::getBool('registration_enabled', true)) {
                return $resp->withStatus(302)->withHeader('Location', '/login');
            }
            $params   = $req->getParsedBody();
            $username = trim($params['username'] ?? '');
            $password = $params['password'] ?? '';
            $confirm  = $params['confirm'] ?? '';
            try {
                if ($password !== $confirm) {
                    throw new \RuntimeException('Passwords do not match.');
                }
                $user = UserService::register($username, $password);
                UserService::login($username, $password);
                return $resp->withStatus(302)->withHeader('Location', '/');
            } catch (\RuntimeException $e) {
                $html = $renderer->render('signup', [
                    'title'     => 'Sign up – plainbooru',
                    'mainClass' => 'flex-1 flex items-center justify-center px-4 py-12',
                    'error'     => $e->getMessage(),
                    'username'  => $username,
                ]);
                $resp->getBody()->write($html);
                return $resp->withStatus(422)->withHeader('Content-Type', 'text/html; charset=utf-8');
            }
        });

        // GET /settings → redirect to account settings
        $app->get('/settings', function (Request $req, Response $resp) {
            return $resp->withStatus(302)->withHeader('Location', '/settings/account');
        });

        // GET /settings/account
        $app->get('/settings/account', function (Request $req, Response $resp) use ($renderer) {
            $user = UserService::current();
            if (!$user) {
                return $resp->withStatus(302)->withHeader('Location', '/login?next=/settings/account');
            }
            $html = $renderer->render('account_settings', [
                'title' => 'Account Settings – plainbooru',
                'error' => null,
            ]);
            $resp->getBody()->write($html);
            return $resp->withHeader('Content-Type', 'text/html; charset=utf-8');
        });

        // POST /settings/account
        $app->post('/settings/account', function (Request $req, Response $resp) use ($renderer) {
            $user = UserService::current();
            if (!$user) {
                return $resp->withStatus(302)->withHeader('Location', '/login?next=/settings/account');
            }
            $body   = (array)($req->getParsedBody() ?? []);
            $action = $body['action'] ?? '';

            if ($action === 'bio') {
                UserService::updateBio((int)$user['id'], $body['bio'] ?? null);
                Flash::set('success', 'Bio updated.');
                return $resp->withStatus(302)->withHeader('Location', '/settings/account');
            }

            if ($action === 'password') {
                $current = $body['current_password'] ?? '';
                $new     = $body['new_password'] ?? '';
                $confirm = $body['confirm_password'] ?? '';
                try {
                    if ($new !== $confirm) {
                        throw new \RuntimeException('New passwords do not match.');
                    }
                    if (strlen($new) < 8) {
                        throw new \RuntimeException('Password must be at least 8 characters.');
                    }
                    if (!UserService::changePassword((int)$user['id'], $current, $new)) {
                        throw new \RuntimeException('Current password is incorrect.');
                    }
                    Flash::set('success', 'Password updated.');
                    return $resp->withStatus(302)->withHeader('Location', '/settings/account');
                } catch (\RuntimeException $e) {
                    $html = $renderer->render('account_settings', [
                        'title' => 'Account Settings – plainbooru',
                        'error' => $e->getMessage(),
                    ]);
                    $resp->getBody()->write($html);
                    return $resp->withStatus(422)->withHeader('Content-Type', 'text/html; charset=utf-8');
                }
            }

            return $resp->withStatus(302)->withHeader('Location', '/settings/account');
        });

        // GET /settings/tokens
        $app->get('/settings/tokens', function (Request $req, Response $resp) use ($renderer) {
            $user = UserService::current();
            if (!$user) {
                return $resp->withStatus(302)->withHeader('Location', '/login?next=/settings/tokens');
            }
            $html = $renderer->render('account_settings', [
                'title'  => 'API Tokens – plainbooru',
                'tokens' => TokenService::listForUser((int)$user['id']),
                'error'  => null,
            ]);
            $resp->getBody()->write($html);
            return $resp->withHeader('Content-Type', 'text/html; charset=utf-8');
        });

        // POST /settings/tokens
        $app->post('/settings/tokens', function (Request $req, Response $resp) use ($renderer) {
            $user = UserService::current();
            if (!$user) {
                return $resp->withStatus(302)->withHeader('Location', '/login?next=/settings/tokens');
            }
            $body  = (array)($req->getParsedBody() ?? []);
            $label = trim($body['label'] ?? '');
            try {
                $raw = TokenService::generate((int)$user['id'], $label);
                Flash::set('success', 'Token created: ' . $raw);
            } catch (\RuntimeException $e) {
                Flash::set('error', $e->getMessage());
            }
            return $resp->withStatus(302)->withHeader('Location', '/settings/tokens');
        });

        // POST /settings/tokens/{id}/revoke
        $app->post('/settings/tokens/{id:[0-9]+}/revoke', function (Request $req, Response $resp, array $args) {
            $user = UserService::current();
            if (!$user) {
                return $resp->withStatus(302)->withHeader('Location', '/login');
            }
            try {
                TokenService::revoke((int)$args['id'], (int)$user['id']);
                Flash::set('success', 'Token revoked.');
            } catch (\RuntimeException $e) {
                Flash::set('error', $e->getMessage());
            }
            return $resp->withStatus(302)->withHeader('Location', '/settings/tokens');
        });

        // GET /u/{username} – public profile page
        $app->get('/u/{username}', function (Request $req, Response $resp, array $args) use ($renderer) {
            $profile = UserService::getByUsername($args['username']);
            if (!$profile) {
                $html = $renderer->render('error', ['title' => 'Not Found', 'message' => 'User not found.', 'code' => 404]);
                $resp->getBody()->write($html);
                return $resp->withStatus(404)->withHeader('Content-Type', 'text/html; charset=utf-8');
            }
            $params   = $req->getQueryParams();
            $page     = max(1, (int)($params['page'] ?? 1));
            $pageSize = Settings::getInt('items_per_page', 20);
            $uploads  = MediaService::getByUploader((int)$profile['id'], $page, $pageSize);
            $html = $renderer->render('profile', [
                'title'      => $profile['username'] . ' – plainbooru',
                'profile'    => $profile,
                'media'      => $uploads['results'],
                'total'      => $uploads['total'],
                'page'       => $page,
                'page_size'  => $pageSize,
                'totalPages' => max(1, (int)ceil($uploads['total'] / max(1, $pageSize))),
            ]);
            $resp->getBody()->write($html);
            return $resp->withHeader('Content-Type', 'text/html; charset=utf-8');
        });

        // GET /u/{username}/favorites
        $app->get('/u/{username}/favorites', function (Request $req, Response $resp, array $args) use ($renderer) {
            $profile = UserService::getByUsername($args['username']);
            if (!$profile) {
                $html = $renderer->render('error', ['title' => 'Not Found', 'message' => 'User not found.', 'code' => 404]);
                $resp->getBody()->write($html);
                return $resp->withStatus(404)->withHeader('Content-Type', 'text/html; charset=utf-8');
            }
            $params   = $req->getQueryParams();
            $page     = max(1, (int)($params['page'] ?? 1));
            $pageSize = Settings::getInt('items_per_page', 20);
            $data     = FavoriteService::getForUser((int)$profile['id'], $page, $pageSize);
            $html = $renderer->render('favorites', [
                'title'      => $profile['username'] . '\'s Favorites – plainbooru',
                'profile'    => $profile,
                'media'      => $data['results'],
                'total'      => $data['total'],
                'page'       => $page,
                'page_size'  => $pageSize,
                'totalPages' => max(1, (int)ceil($data['total'] / max(1, $pageSize))),
            ]);
            $resp->getBody()->write($html);
            return $resp->withHeader('Content-Type', 'text/html; charset=utf-8');
        });

        // GET /admin/settings
        $app->get('/admin/settings', function (Request $req, Response $resp) use ($renderer) {
            $user = UserService::current();
            Guard::requireAtLeast('admin', $user);
            $pdo      = Db::get();
            $rows     = $pdo->query('SELECT key, value FROM settings')->fetchAll(\PDO::FETCH_KEY_PAIR);
            $settings = array_merge(Settings::defaults(), $rows);
            $iniToBytes = static function (string $v): int {
                $v = trim($v);
                $last = strtolower($v[-1] ?? '');
                $n = (int)$v;
                return match ($last) {
                    'g' => $n * 1_073_741_824,
                    'm' => $n * 1_048_576,
                    'k' => $n * 1_024,
                    default => $n,
                };
            };
            $phpLimits = [
                'upload_max_filesize'       => ini_get('upload_max_filesize') ?: '?',
                'upload_max_filesize_bytes' => $iniToBytes(ini_get('upload_max_filesize') ?: '0'),
                'post_max_size'             => ini_get('post_max_size') ?: '?',
                'post_max_size_bytes'       => $iniToBytes(ini_get('post_max_size') ?: '0'),
            ];
            $html = $renderer->render('admin/site_settings', [
                'title'     => 'Site Settings – Admin – plainbooru',
                'settings'  => $settings,
                'diag'      => ThumbService::diagnostics(),
                'phpLimits' => $phpLimits,
            ]);
            $resp->getBody()->write($html);
            return $resp->withHeader('Content-Type', 'text/html; charset=utf-8');
        });

        // POST /admin/settings
        $app->post('/admin/settings', function (Request $req, Response $resp) {
            $user = UserService::current();
            Guard::requireAtLeast('admin', $user);
            $body = (array)($req->getParsedBody() ?? []);

            $boolKeys = ['registration_enabled','anon_can_upload','anon_can_comment',
                         'anon_can_vote','anon_can_create_pool','anon_can_edit_tags',
                         'require_login_to_view','maintenance_mode'];

            foreach (Settings::defaults() as $key => $default) {
                if (in_array($key, $boolKeys, true)) {
                    // Checkboxes are absent when unchecked
                    Settings::setBool($key, isset($body[$key]) && $body[$key] === '1');
                } elseif (isset($body[$key])) {
                    Settings::set($key, (string)$body[$key]);
                }
            }

            Flash::set('success', 'Settings saved.');
            return $resp->withStatus(302)->withHeader('Location', '/admin/settings');
        });

        // POST /logout
        $app->post('/logout', function (Request $req, Response $resp) {
            UserService::logout();
            return $resp->withStatus(302)->withHeader('Location', '/');
        });

        // GET /upload
        $app->get('/upload', function (Request $req, Response $resp) use ($renderer) {
            $html = $renderer->render('upload', ['title' => 'Upload – plainbooru', 'error' => null]);
            $resp->getBody()->write($html);
            return $resp->withHeader('Content-Type', 'text/html; charset=utf-8');
        });

        // POST /upload
        $app->post('/upload', function (Request $req, Response $resp) use ($renderer) {
            $uploadUser  = UserService::current();
            if (!Policy::canUpload($uploadUser)) {
                Flash::set('error', 'You do not have permission to upload.');
                return $resp->withStatus(302)->withHeader('Location', '/upload');
            }
            $uploadLimit = Settings::getInt('rate_limit_uploads_per_hour', 20);
            $uploadKey   = RateLimiter::key('upload', $uploadUser ? (int)$uploadUser['id'] : null);
            if (!RateLimiter::hit($uploadKey, $uploadLimit, 3600)) {
                Flash::set('error', 'Upload rate limit reached. Please wait before uploading again.');
                return $resp->withStatus(302)->withHeader('Location', '/upload');
            }
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

            $uploaderId = UserService::current()['id'] ?? null;
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
                    $media  = MediaService::storeFromPath($tmpPath, $phpFile, $params['tags'] ?? '', $uploaderId);
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
            $currentUser = UserService::current();
            $mediaId     = (int)$args['id'];
            $html = $renderer->render('post', [
                'title'        => 'Post #' . $media['id'] . ' – plainbooru',
                'media'        => $media,
                'pools'        => PoolService::getPoolsForMedia($mediaId),
                'bodyClass'    => 'h-full overflow-hidden',
                'mainClass'    => 'flex-1 min-h-0 flex overflow-hidden',
                'vote_score'   => VoteService::score($mediaId),
                'user_vote'    => $currentUser ? VoteService::userVote((int)$currentUser['id'], $mediaId) : null,
                'fav_count'    => FavoriteService::countForMedia($mediaId),
                'is_favorited' => $currentUser ? FavoriteService::isFavorited((int)$currentUser['id'], $mediaId) : false,
                'can_vote'      => Policy::canVote($currentUser),
                'can_comment'   => Policy::canComment($currentUser),
                'can_edit_tags' => Policy::canEditTags($currentUser),
                'can_moderate'  => Policy::canModerate($currentUser),
                'is_owner'      => Policy::isOwner($media['uploader_id'] ?? null, $currentUser),
                'comments'      => CommentService::getForMedia($mediaId),
            ]);
            $resp->getBody()->write($html);
            return $resp->withHeader('Content-Type', 'text/html; charset=utf-8');
        });

        // POST /m/{id}/tags – add a tag from the post page sidebar
        $app->post('/m/{id:[0-9]+}/tags', function (Request $req, Response $resp, array $args) {
            $user = UserService::current();
            if (!Policy::canEditTags($user)) {
                Flash::set('error', 'You do not have permission to edit tags.');
                return $resp->withStatus(302)->withHeader('Location', '/m/' . $args['id']);
            }
            $id    = (int)$args['id'];
            $media = MediaService::getById($id);
            if (!$media) {
                return $resp->withStatus(404);
            }
            $body = (array)($req->getParsedBody() ?? []);
            $tag  = trim($body['tag'] ?? '');
            if ($tag !== '') {
                $maxTags = Settings::getInt('max_tags_per_media', 50);
                if (count(MediaService::getTags($id)) >= $maxTags) {
                    Flash::set('error', "Tag limit reached ({$maxTags} max).");
                    return $resp->withStatus(302)->withHeader('Location', '/m/' . $id);
                }
                MediaService::addTags($media, $tag);
            }
            return $resp->withStatus(302)->withHeader('Location', '/m/' . $id);
        });

        // POST /m/{id}/tags/remove
        $app->post('/m/{id:[0-9]+}/tags/remove', function (Request $req, Response $resp, array $args) {
            $user = UserService::current();
            if (!Policy::canEditTags($user)) {
                return $resp->withStatus(302)->withHeader('Location', '/m/' . $args['id']);
            }
            $id   = (int)$args['id'];
            $body = (array)($req->getParsedBody() ?? []);
            $tag  = trim($body['tag'] ?? '');
            if ($tag !== '') {
                MediaService::removeTag($id, $tag);
            }
            return $resp->withStatus(302)->withHeader('Location', '/m/' . $id);
        });

        // POST /m/{id}/favorite – toggle favorite
        $app->post('/m/{id:[0-9]+}/favorite', function (Request $req, Response $resp, array $args) {
            $user = UserService::current();
            if (!$user) {
                return $resp->withStatus(302)->withHeader('Location', '/login?next=/m/' . $args['id']);
            }
            FavoriteService::toggle((int)$user['id'], (int)$args['id']);
            return $resp->withStatus(302)->withHeader('Location', '/m/' . $args['id']);
        });

        // POST /m/{id}/vote
        $app->post('/m/{id:[0-9]+}/vote', function (Request $req, Response $resp, array $args) {
            $user = UserService::current();
            if (!Policy::canVote($user)) {
                Flash::set('error', 'You must be logged in to vote.');
                return $resp->withStatus(302)->withHeader('Location', '/m/' . $args['id']);
            }
            $body  = (array)($req->getParsedBody() ?? []);
            $value = (int)($body['value'] ?? 0);
            if ($value === 1 || $value === -1) {
                VoteService::cast((int)$user['id'], (int)$args['id'], $value);
            }
            return $resp->withStatus(302)->withHeader('Location', '/m/' . $args['id']);
        });

        // POST /m/{id}/comments
        $app->post('/m/{id:[0-9]+}/comments', function (Request $req, Response $resp, array $args) {
            $user    = UserService::current();
            $mediaId = (int)$args['id'];
            if (!Policy::canComment($user)) {
                Flash::set('error', 'You must be logged in to comment.');
                return $resp->withStatus(302)->withHeader('Location', '/m/' . $mediaId);
            }
            $commentLimit = Settings::getInt('rate_limit_comments_per_hour', 30);
            $commentKey   = RateLimiter::key('comment', $user ? (int)$user['id'] : null);
            if (!RateLimiter::hit($commentKey, $commentLimit, 3600)) {
                Flash::set('error', 'Comment rate limit reached. Please wait before commenting again.');
                return $resp->withStatus(302)->withHeader('Location', '/m/' . $mediaId . '#comments');
            }
            $body = (array)($req->getParsedBody() ?? []);
            try {
                CommentService::add($mediaId, $user ? (int)$user['id'] : null, $body['body'] ?? '');
            } catch (\RuntimeException $e) {
                Flash::set('error', $e->getMessage());
            }
            return $resp->withStatus(302)->withHeader('Location', '/m/' . $mediaId . '#comments');
        });

        // POST /m/{id}/comments/{cid}/delete
        $app->post('/m/{id:[0-9]+}/comments/{cid:[0-9]+}/delete', function (Request $req, Response $resp, array $args) {
            $user = UserService::current();
            if (!$user) {
                return $resp->withStatus(302)->withHeader('Location', '/login');
            }
            $mediaId   = (int)$args['id'];
            $commentId = (int)$args['cid'];
            try {
                CommentService::delete($commentId, $user);
                ModLog::write('comment.delete', 'comment:' . $commentId, (int)$user['id']);
            } catch (\RuntimeException $e) {
                Flash::set('error', $e->getMessage());
            }
            return $resp->withStatus(302)->withHeader('Location', '/m/' . $mediaId . '#comments');
        });

        // POST /m/{id}/delete
        $app->post('/m/{id:[0-9]+}/delete', function (Request $req, Response $resp, array $args) {
            $user    = UserService::current();
            $mediaId = (int)$args['id'];
            $media   = MediaService::getById($mediaId);
            if (!$media) {
                return $resp->withStatus(404);
            }
            $isOwner = $user && (int)($media['uploader_id'] ?? -1) === (int)$user['id'];
            if (!$isOwner && !Policy::canModerate($user)) {
                return $resp->withStatus(403);
            }
            MediaService::delete($mediaId, $user ? (int)$user['id'] : null);
            if ($user) {
                ModLog::write('media.delete', 'media:' . $mediaId, (int)$user['id']);
            }
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
                'title'      => 'Search – plainbooru',
                'media'      => $data['results'],
                'total'      => $data['total'],
                'page'       => $page,
                'page_size'  => $pageSize,
                'totalPages' => max(1, (int)ceil($data['total'] / max(1, $pageSize))),
                'tags'       => $tags,
                'q'          => $q,
                'sidebar'    => $sidebar,
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
                'title'      => "Tag: $tag – plainbooru",
                'media'      => $data['results'],
                'total'      => $data['total'],
                'page'       => $page,
                'page_size'  => $pageSize,
                'totalPages' => max(1, (int)ceil($data['total'] / max(1, $pageSize))),
                'tags'       => $tag,
                'q'          => '',
                'sidebar'    => $sidebar,
            ]);
            $resp->getBody()->write($html);
            return $resp->withHeader('Content-Type', 'text/html; charset=utf-8');
        });

        // GET /tags
        $app->get('/tags', function (Request $req, Response $resp) use ($renderer) {
            $tags    = MediaService::getAllTags();
            $user    = UserService::current();
            $sidebar = Policy::canEditTags($user) ? $renderer->partial('sidebar_tag_search', []) : null;
            $html    = $renderer->render('tags', [
                'title'        => 'Tags – plainbooru',
                'tags'         => $tags,
                'sidebar'      => $sidebar,
                'can_moderate' => Policy::canModerate($user),
            ]);
            $resp->getBody()->write($html);
            return $resp->withHeader('Content-Type', 'text/html; charset=utf-8');
        });

        // POST /tags – create a standalone tag
        $app->post('/tags', function (Request $req, Response $resp) {
            $user = UserService::current();
            if (!Policy::canEditTags($user)) {
                Flash::set('error', 'You do not have permission to create tags.');
                return $resp->withStatus(302)->withHeader('Location', '/tags');
            }
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

        // POST /tags/{name}/delete – requires moderator+
        $app->post('/tags/{name}/delete', function (Request $req, Response $resp, array $args) {
            $user = UserService::current();
            Guard::requireAtLeast('moderator', $user);
            MediaService::deleteTag(urldecode($args['name']), (int)$user['id']);
            ModLog::write('tag.delete', 'tag:' . urldecode($args['name']), (int)$user['id']);
            return $resp->withStatus(302)->withHeader('Location', '/tags');
        });

        // ── Pools HTML ───────────────────────────────────────────────────────

        // GET /pools
        $app->get('/pools', function (Request $req, Response $resp) use ($renderer) {
            $params  = $req->getQueryParams();
            $page    = max(1, (int)($params['page'] ?? 1));
            $viewer  = UserService::current();
            $data    = PoolService::getList($page, 18, $viewer ? (int)$viewer['id'] : null, $viewer['role'] ?? 'anonymous');
            $sidebar = Policy::canCreatePool($viewer) ? $renderer->partial('sidebar_create_pool', ['error' => null]) : null;
            $html    = $renderer->render('pools', [
                'title'      => 'Pools – plainbooru',
                'pools'      => $data['results'],
                'total'      => $data['total'],
                'page'       => $page,
                'page_size'  => 18,
                'totalPages' => max(1, (int)ceil($data['total'] / 18)),
                'error'      => null,
                'sidebar'    => $sidebar,
            ]);
            $resp->getBody()->write($html);
            return $resp->withHeader('Content-Type', 'text/html; charset=utf-8');
        });

        // POST /pools
        $app->post('/pools', function (Request $req, Response $resp) use ($renderer) {
            $viewer = UserService::current();
            if (!Policy::canCreatePool($viewer)) {
                Flash::set('error', 'You do not have permission to create pools.');
                return $resp->withStatus(302)->withHeader('Location', '/pools');
            }
            $params     = $req->getParsedBody();
            $name       = trim($params['name'] ?? '');
            $desc       = trim($params['description'] ?? '') ?: null;
            $visibility = $params['visibility'] ?? 'public';
            try {
                $pool = PoolService::create($name, $desc, $viewer ? (int)$viewer['id'] : null, $visibility);
                return $resp->withStatus(302)->withHeader('Location', '/pools/' . $pool['id'] . '/edit');
            } catch (\RuntimeException $e) {
                $data    = PoolService::getList(1, 18, $viewer ? (int)$viewer['id'] : null, $viewer['role'] ?? 'anonymous');
                $sidebar = Policy::canCreatePool($viewer) ? $renderer->partial('sidebar_create_pool', ['error' => $e->getMessage()]) : null;
                $html    = $renderer->render('pools', [
                    'title'      => 'Pools – plainbooru',
                    'pools'      => $data['results'],
                    'total'      => $data['total'],
                    'page'       => 1,
                    'page_size'  => 18,
                    'totalPages' => max(1, (int)ceil($data['total'] / 18)),
                    'error'      => $e->getMessage(),
                    'sidebar'    => $sidebar,
                ]);
                $resp->getBody()->write($html);
                return $resp->withStatus(400)->withHeader('Content-Type', 'text/html; charset=utf-8');
            }
        });

        // GET /pools/{id}/edit — must be before GET /pools/{id}
        $app->get('/pools/{id:[0-9]+}/edit', function (Request $req, Response $resp, array $args) use ($renderer) {
            $viewer = UserService::current();
            $pool   = PoolService::getById((int)$args['id'], $viewer ? (int)$viewer['id'] : null, $viewer['role'] ?? 'anonymous');
            if (!$pool || !PoolService::canEdit($pool, $viewer ? (int)$viewer['id'] : null, $viewer['role'] ?? 'anonymous')) {
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
            $viewer = UserService::current();
            $poolId = (int)$args['id'];
            $pool   = PoolService::getById($poolId, $viewer ? (int)$viewer['id'] : null, $viewer['role'] ?? 'anonymous');
            if (!$pool || !PoolService::canEdit($pool, $viewer ? (int)$viewer['id'] : null, $viewer['role'] ?? 'anonymous')) {
                return $resp->withStatus(403);
            }
            $params     = $req->getParsedBody();
            $name       = trim($params['name'] ?? '');
            $desc       = trim($params['description'] ?? '') ?: null;
            $visibility = $params['visibility'] ?? 'public';
            try {
                PoolService::update($poolId, $name, $desc, $visibility);
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
            $viewer = UserService::current();
            $poolId = (int)$args['id'];
            $pool   = PoolService::getById($poolId, $viewer ? (int)$viewer['id'] : null, $viewer['role'] ?? 'anonymous');
            if (!$pool || !PoolService::canEdit($pool, $viewer ? (int)$viewer['id'] : null, $viewer['role'] ?? 'anonymous')) {
                return $resp->withStatus(403);
            }
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
            $viewer = UserService::current();
            $poolId = (int)$args['id'];
            $pool   = PoolService::getById($poolId, $viewer ? (int)$viewer['id'] : null, $viewer['role'] ?? 'anonymous');
            if (!$pool || !PoolService::canEdit($pool, $viewer ? (int)$viewer['id'] : null, $viewer['role'] ?? 'anonymous')) {
                return $resp->withStatus(403);
            }
            $params = $req->getParsedBody();
            $tag    = trim($params['tag'] ?? '');
            if ($tag !== '') {
                PoolService::removeTag($poolId, $tag);
            }
            return $resp->withStatus(302)->withHeader('Location', '/pools/' . $poolId . '/edit');
        });

        // POST /pools/{id}/upload – upload one or more files and add to pool
        $app->post('/pools/{id:[0-9]+}/upload', function (Request $req, Response $resp, array $args) use ($renderer) {
            $viewer  = UserService::current();
            $poolId  = (int)$args['id'];
            $pool_   = PoolService::getById($poolId, $viewer ? (int)$viewer['id'] : null, $viewer['role'] ?? 'anonymous');
            if (!$pool_ || !PoolService::canEdit($pool_, $viewer ? (int)$viewer['id'] : null, $viewer['role'] ?? 'anonymous')) {
                return $resp->withStatus(403);
            }
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

            $poolUploaderId = UserService::current()['id'] ?? null;
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
                    $media = MediaService::storeFromPath($tmpPath, $phpFile, $params['tags'] ?? '', $poolUploaderId);
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
            $viewer = UserService::current();
            $pool   = PoolService::getById((int)$args['id'], $viewer ? (int)$viewer['id'] : null, $viewer['role'] ?? 'anonymous');
            if (!$pool || !PoolService::canEdit($pool, $viewer ? (int)$viewer['id'] : null, $viewer['role'] ?? 'anonymous')) {
                return $resp->withStatus(403);
            }
            PoolService::delete((int)$args['id'], $viewer ? (int)$viewer['id'] : null);
            return $resp->withStatus(302)->withHeader('Location', '/pools');
        });

        // GET /pools/{poolId}/m/{mediaId} — pool-context media viewer
        $app->get('/pools/{poolId:[0-9]+}/m/{mediaId:[0-9]+}', function (Request $req, Response $resp, array $args) use ($renderer) {
            $viewer = UserService::current();
            $pool   = PoolService::getById((int)$args['poolId'], $viewer ? (int)$viewer['id'] : null, $viewer['role'] ?? 'anonymous');
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
                'title'         => 'Post #' . $media['id'] . ' – ' . $pool['name'] . ' – plainbooru',
                'media'         => $media,
                'pools'         => PoolService::getPoolsForMedia((int)$args['mediaId']),
                'pool'          => $pool,
                'pool_prev'     => $poolPrev,
                'pool_next'     => $poolNext,
                'pool_pos'      => $idx !== false ? $idx + 1 : '?',
                'pool_total'    => count($items),
                'can_edit_tags' => Policy::canEditTags($viewer),
                'bodyClass'     => 'h-full overflow-hidden',
                'mainClass'     => 'flex-1 min-h-0 flex overflow-hidden',
            ]);
            $resp->getBody()->write($html);
            return $resp->withHeader('Content-Type', 'text/html; charset=utf-8');
        });

        // GET /pools/{id}
        $app->get('/pools/{id:[0-9]+}', function (Request $req, Response $resp, array $args) use ($renderer) {
            $viewer = UserService::current();
            $pool   = PoolService::getById((int)$args['id'], $viewer ? (int)$viewer['id'] : null, $viewer['role'] ?? 'anonymous');
            if (!$pool) {
                $html = $renderer->render('error', ['title' => 'Not Found', 'message' => 'Pool not found.', 'code' => 404]);
                $resp->getBody()->write($html);
                return $resp->withStatus(404)->withHeader('Content-Type', 'text/html; charset=utf-8');
            }
            $canEdit = PoolService::canEdit($pool, $viewer ? (int)$viewer['id'] : null, $viewer['role'] ?? 'anonymous');
            $sidebar = $renderer->partial('sidebar_pool', ['pool' => $pool, 'can_edit' => $canEdit]);
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
            $viewer = UserService::current();
            $poolId = (int)$args['id'];
            $pool__ = PoolService::getById($poolId, $viewer ? (int)$viewer['id'] : null, $viewer['role'] ?? 'anonymous');
            if (!$pool__ || !PoolService::canEdit($pool__, $viewer ? (int)$viewer['id'] : null, $viewer['role'] ?? 'anonymous')) {
                return $resp->withStatus(403);
            }
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
            $viewer = UserService::current();
            $poolId = (int)$args['id'];
            $pool_r = PoolService::getById($poolId, $viewer ? (int)$viewer['id'] : null, $viewer['role'] ?? 'anonymous');
            if (!$pool_r || !PoolService::canEdit($pool_r, $viewer ? (int)$viewer['id'] : null, $viewer['role'] ?? 'anonymous')) {
                return $resp->withStatus(403);
            }
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
            $viewer    = UserService::current();
            $poolId    = (int)$args['id'];
            $pool      = PoolService::getById($poolId, $viewer ? (int)$viewer['id'] : null, $viewer['role'] ?? 'anonymous');
            if (!$pool || !PoolService::canEdit($pool, $viewer ? (int)$viewer['id'] : null, $viewer['role'] ?? 'anonymous')) {
                return $resp->withStatus(403);
            }
            $params    = $req->getParsedBody();
            $mediaId   = (int)($params['media_id'] ?? 0);
            $direction = $params['direction'] ?? '';
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
            $viewer  = UserService::current();
            $poolId  = (int)$args['id'];
            $pool_rm = PoolService::getById($poolId, $viewer ? (int)$viewer['id'] : null, $viewer['role'] ?? 'anonymous');
            if (!$pool_rm || !PoolService::canEdit($pool_rm, $viewer ? (int)$viewer['id'] : null, $viewer['role'] ?? 'anonymous')) {
                return $resp->withStatus(403);
            }
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
            // Check for nginx X-Accel-Redirect (Nginx handles range requests natively)
            if (Config::get('NGINX_ACCEL') === 'true') {
                return $resp
                    ->withHeader('X-Accel-Redirect', '/internal/uploads/' . $media['stored_name'])
                    ->withHeader('Content-Type', $media['mime'])
                    ->withHeader('Content-Disposition', 'inline; filename="' . $media['original_name'] . '"');
            }
            if ($media['kind'] === 'video') {
                return self::serveVideo($resp, $req, $path, $media['mime'], $media['original_name']);
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
            $apiUser  = $req->getAttribute('api_user');
            $apiLimit = Settings::getInt('rate_limit_api_per_minute', 300);
            $apiKey   = RateLimiter::key('api', $apiUser ? (int)$apiUser['id'] : null);
            if (!RateLimiter::hit($apiKey, $apiLimit, 60)) {
                return self::jsonResp($resp, ['error' => 'Rate limit exceeded.'], 429);
            }

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

        // ── Admin: user management ────────────────────────────────────────────

        // GET /admin/users
        $app->get('/admin/users', function (Request $req, Response $resp) use ($renderer) {
            $user = UserService::current();
            Guard::requireAtLeast('admin', $user);
            $pdo   = Db::get();
            $users = $pdo->query('SELECT id, username, role, created_at, banned_at FROM users ORDER BY id ASC')->fetchAll();
            $html  = $renderer->render('admin/users', [
                'title'       => 'Users – Admin – plainbooru',
                'users'       => $users,
                'currentUser' => $user,
            ]);
            $resp->getBody()->write($html);
            return $resp->withHeader('Content-Type', 'text/html; charset=utf-8');
        });

        // POST /admin/users/{id}/role
        $app->post('/admin/users/{id:[0-9]+}/role', function (Request $req, Response $resp, array $args) {
            $actor = UserService::current();
            Guard::requireAtLeast('admin', $actor);
            $body   = (array)($req->getParsedBody() ?? []);
            $role   = trim($body['role'] ?? '');
            try {
                UserService::setRole((int)$args['id'], $role, $actor);
                Flash::set('success', 'Role updated.');
            } catch (\RuntimeException $e) {
                Flash::set('error', $e->getMessage());
            }
            return $resp->withStatus(302)->withHeader('Location', '/admin/users');
        });

        // POST /admin/users/{id}/ban
        $app->post('/admin/users/{id:[0-9]+}/ban', function (Request $req, Response $resp, array $args) {
            $actor = UserService::current();
            Guard::requireAtLeast('moderator', $actor);
            $body   = (array)($req->getParsedBody() ?? []);
            $reason = trim($body['reason'] ?? '') ?: null;
            try {
                UserService::ban((int)$args['id'], $reason, $actor);
                Flash::set('success', 'User banned.');
            } catch (\RuntimeException $e) {
                Flash::set('error', $e->getMessage());
            }
            return $resp->withStatus(302)->withHeader('Location', '/admin/users');
        });

        // GET /admin/mod-log
        $app->get('/admin/mod-log', function (Request $req, Response $resp) use ($renderer) {
            $user = UserService::current();
            Guard::requireAtLeast('moderator', $user);
            $params = $req->getQueryParams();
            $page   = max(1, (int)($params['page'] ?? 1));
            $limit  = 50;
            $offset = ($page - 1) * $limit;
            $entries = ModLog::recent($limit, $offset);
            $total   = (int)Db::get()->query('SELECT COUNT(*) FROM mod_log')->fetchColumn();
            $html = $renderer->render('admin/mod_log', [
                'title'      => 'Mod Log – Admin – plainbooru',
                'entries'    => $entries,
                'total'      => $total,
                'page'       => $page,
                'page_size'  => $limit,
                'totalPages' => max(1, (int)ceil($total / max(1, $limit))),
            ]);
            $resp->getBody()->write($html);
            return $resp->withHeader('Content-Type', 'text/html; charset=utf-8');
        });

        // ── Admin: trash (soft-deleted items) ────────────────────────────────

        // GET /admin/trash
        $app->get('/admin/trash', function (Request $req, Response $resp) use ($renderer) {
            $user = UserService::current();
            Guard::requireAtLeast('moderator', $user);
            $params       = $req->getQueryParams();
            $mediaPage    = max(1, (int)($params['mp'] ?? 1));
            $poolPage     = max(1, (int)($params['pp'] ?? 1));
            $tagPage      = max(1, (int)($params['tp'] ?? 1));
            $commentPage  = max(1, (int)($params['cp'] ?? 1));
            $pageSize     = 20;
            $mediaData    = MediaService::getDeleted($mediaPage, $pageSize);
            $poolData     = PoolService::getDeleted($poolPage, $pageSize);
            $tagData      = MediaService::getDeletedTags($tagPage, 50);
            $commentData  = CommentService::getDeleted($commentPage, $pageSize);
            $html = $renderer->render('admin/trash', [
                'title'              => 'Trash – Admin – plainbooru',
                'deletedMedia'       => $mediaData['results'],
                'mediaTotal'         => $mediaData['total'],
                'mediaPage'          => $mediaPage,
                'mediaTotalPages'    => max(1, (int)ceil($mediaData['total'] / $pageSize)),
                'deletedPools'       => $poolData['results'],
                'poolsTotal'         => $poolData['total'],
                'poolsPage'          => $poolPage,
                'poolsTotalPages'    => max(1, (int)ceil($poolData['total'] / $pageSize)),
                'deletedTags'        => $tagData['results'],
                'tagsTotal'          => $tagData['total'],
                'tagPage'            => $tagPage,
                'tagsTotalPages'     => max(1, (int)ceil($tagData['total'] / 50)),
                'deletedComments'    => $commentData['results'],
                'commentsTotal'      => $commentData['total'],
                'commentPage'        => $commentPage,
                'commentsTotalPages' => max(1, (int)ceil($commentData['total'] / $pageSize)),
                'pageSize'           => $pageSize,
            ]);
            $resp->getBody()->write($html);
            return $resp->withHeader('Content-Type', 'text/html; charset=utf-8');
        });

        // POST /admin/trash/media/{id}/restore
        $app->post('/admin/trash/media/{id:[0-9]+}/restore', function (Request $req, Response $resp, array $args) {
            $user = UserService::current();
            Guard::requireAtLeast('moderator', $user);
            $id = (int)$args['id'];
            MediaService::restore($id);
            ModLog::write('media.restore', 'media:' . $id, (int)$user['id']);
            return $resp->withStatus(302)->withHeader('Location', '/admin/trash');
        });

        // POST /admin/trash/media/{id}/purge
        $app->post('/admin/trash/media/{id:[0-9]+}/purge', function (Request $req, Response $resp, array $args) {
            $user = UserService::current();
            Guard::requireAtLeast('moderator', $user);
            $id = (int)$args['id'];
            MediaService::permanentDelete($id);
            ModLog::write('media.purge', 'media:' . $id, (int)$user['id']);
            return $resp->withStatus(302)->withHeader('Location', '/admin/trash');
        });

        // POST /admin/trash/pools/{id}/restore
        $app->post('/admin/trash/pools/{id:[0-9]+}/restore', function (Request $req, Response $resp, array $args) {
            $user = UserService::current();
            Guard::requireAtLeast('moderator', $user);
            $id = (int)$args['id'];
            PoolService::restore($id);
            ModLog::write('pool.restore', 'pool:' . $id, (int)$user['id']);
            return $resp->withStatus(302)->withHeader('Location', '/admin/trash');
        });

        // POST /admin/trash/pools/{id}/purge
        $app->post('/admin/trash/pools/{id:[0-9]+}/purge', function (Request $req, Response $resp, array $args) {
            $user = UserService::current();
            Guard::requireAtLeast('moderator', $user);
            $id = (int)$args['id'];
            PoolService::permanentDelete($id);
            ModLog::write('pool.purge', 'pool:' . $id, (int)$user['id']);
            return $resp->withStatus(302)->withHeader('Location', '/admin/trash');
        });

        // POST /admin/trash/tags/{name}/restore
        $app->post('/admin/trash/tags/{name}/restore', function (Request $req, Response $resp, array $args) {
            $user = UserService::current();
            Guard::requireAtLeast('moderator', $user);
            $name = urldecode($args['name']);
            MediaService::restoreTag($name);
            ModLog::write('tag.restore', 'tag:' . $name, (int)$user['id']);
            return $resp->withStatus(302)->withHeader('Location', '/admin/trash');
        });

        // POST /admin/trash/tags/{name}/purge
        $app->post('/admin/trash/tags/{name}/purge', function (Request $req, Response $resp, array $args) {
            $user = UserService::current();
            Guard::requireAtLeast('moderator', $user);
            $name = urldecode($args['name']);
            MediaService::permanentDeleteTag($name);
            ModLog::write('tag.purge', 'tag:' . $name, (int)$user['id']);
            return $resp->withStatus(302)->withHeader('Location', '/admin/trash');
        });

        // POST /admin/trash/comments/{id}/restore
        $app->post('/admin/trash/comments/{id:[0-9]+}/restore', function (Request $req, Response $resp, array $args) {
            $user = UserService::current();
            Guard::requireAtLeast('moderator', $user);
            $id = (int)$args['id'];
            CommentService::restore($id);
            ModLog::write('comment.restore', 'comment:' . $id, (int)$user['id']);
            return $resp->withStatus(302)->withHeader('Location', '/admin/trash');
        });

        // POST /admin/trash/comments/{id}/purge
        $app->post('/admin/trash/comments/{id:[0-9]+}/purge', function (Request $req, Response $resp, array $args) {
            $user = UserService::current();
            Guard::requireAtLeast('moderator', $user);
            $id = (int)$args['id'];
            CommentService::permanentDelete($id);
            ModLog::write('comment.purge', 'comment:' . $id, (int)$user['id']);
            return $resp->withStatus(302)->withHeader('Location', '/admin/trash');
        });

        return $app;
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    private static function serveVideo(Response $resp, Request $req, string $path, string $mime, ?string $name = null): Response
    {
        $size = filesize($path);
        $r = $resp
            ->withHeader('Content-Type', $mime)
            ->withHeader('Accept-Ranges', 'bytes')
            ->withHeader('X-Content-Type-Options', 'nosniff');

        if ($name) {
            $r = $r->withHeader('Content-Disposition', 'inline; filename="' . addslashes($name) . '"');
        }

        $rangeHeader = $req->getHeaderLine('Range');
        if ($rangeHeader && preg_match('/^bytes=(\d*)-(\d*)$/', $rangeHeader, $m)) {
            $start = $m[1] !== '' ? (int)$m[1] : 0;
            $end   = $m[2] !== '' ? (int)$m[2] : $size - 1;
            $end   = min($end, $size - 1);

            if ($start > $end || $start >= $size) {
                return $resp->withStatus(416)->withHeader('Content-Range', "bytes */$size");
            }

            $length = $end - $start + 1;
            $fh = fopen($path, 'rb');
            fseek($fh, $start);
            $data = fread($fh, $length);
            fclose($fh);

            return $r->withStatus(206)
                ->withBody((new StreamFactory())->createStream($data))
                ->withHeader('Content-Length', (string)$length)
                ->withHeader('Content-Range', "bytes $start-$end/$size");
        }

        return $r
            ->withBody((new StreamFactory())->createStreamFromFile($path))
            ->withHeader('Content-Length', (string)$size);
    }

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
        // Accept bearer token with admin role
        $apiUser = $req->getAttribute('api_user');
        if ($apiUser !== null && Guard::atLeast('admin', $apiUser)) {
            return;
        }
        // Fall back to legacy admin secret
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
