<?php

declare(strict_types=1);

namespace Plainbooru\Templates;

use Plainbooru\Auth\Policy;
use Plainbooru\Auth\UserService;
use Plainbooru\Http\Csrf;
use Plainbooru\Http\Flash;
use Plainbooru\Settings;

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
        $data['csrf_token']  = Csrf::token();
        $data['flash']       = Flash::drain();
        $data['site_title']  = Settings::getString('site_title', 'plainbooru');
        $data['can_upload']  = Policy::canUpload($data['currentUser']);
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

    /**
     * Render the CSRF hidden input. Safe to call from any partial.
     */
    public function csrfInput(): string
    {
        return '<input type="hidden" name="_csrf" value="' . $this->e(Csrf::token()) . '">';
    }

    /**
     * Format a byte count as a human-readable string (e.g. "512 B", "4.25 MB").
     */
    public function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float)$bytes;
        $i = 0;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }
        $formatted = $i === 0 ? (string)(int)$value : number_format($value, 2);
        return $formatted . ' ' . $units[$i];
    }
}
