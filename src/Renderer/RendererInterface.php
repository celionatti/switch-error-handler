<?php

declare(strict_types=1);

namespace Switch\ErrorHandler\Renderer;

interface RendererInterface
{
    /**
     * Render an exception into a presentable string (HTML, JSON, plain text, etc.).
     */
    public function render(\Throwable $exception): string;
}
