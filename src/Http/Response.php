<?php

declare(strict_types=1);

namespace Plainbooru\Http;

final class Response
{
    public static function redirect(string $url, int $code = 302): never
    {
        header('Location: ' . $url, true, $code);
        exit;
    }

    public static function forbidden(string $msg = 'Forbidden'): never
    {
        http_response_code(403);
        exit(htmlspecialchars($msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
    }

    public static function notFound(): never
    {
        http_response_code(404);
        exit('Not Found');
    }
}
