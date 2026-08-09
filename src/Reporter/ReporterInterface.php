<?php

declare(strict_types=1);

namespace Switch\ErrorHandler\Reporter;

interface ReporterInterface
{
    /**
     * Report an exception (e.g. log it, send to monitoring, etc.).
     */
    public function report(\Throwable $exception): void;
}
