<?php

declare(strict_types=1);

namespace Plainbooru\Templates;

use Plainbooru\Auth\UserService;

final class Renderer
{
    private string $templateDir;

    public function __construct(string $templateDir)
    {
        $this->templateDir = rtrim($templateDir, '/');
    }

    /**
     * Render a template with data, wrapped in layout.
     */
    public function render(string $template, array $data = []): string
    {
        $data['_renderer']   = $this;
        $data['currentUser'] = UserService::current();
        $content = $this->partial($template, $data);
        $data['content'] = $content;
        return $this->partial('layout', $data);
    }

    /**
     * Render a partial template (no layout wrapping).
     */
    public function partial(string $template, array $data = []): string
    {
        $file = $this->templateDir . '/' . $template . '.php';
        if (!file_exists($file)) {
            throw new \RuntimeException("Template not found: $template");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        include $file;
        return ob_get_clean();
    }

    /**
     * Escape for HTML output.
     */
    public function e(mixed $value): string
    {
        return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
