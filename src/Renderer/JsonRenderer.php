<?php

declare(strict_types=1);

namespace Switch\ErrorHandler\Renderer;

use Switch\ErrorHandler\Exception\HttpException;

/**
 * JSON error renderer for API responses.
 */
class JsonRenderer implements RendererInterface
{
    public function __construct(
        private readonly bool $debug = false
    ) {
    }

    public function render(\Throwable $exception): string
    {
        $statusCode = $exception instanceof HttpException
            ? $exception->getStatusCode()
            : 500;

        $data = [
            'error' => true,
            'status' => $statusCode,
            'message' => $exception->getMessage(),
        ];

        if ($this->debug) {
            $data['exception'] = get_class($exception);
            $data['file'] = $exception->getFile();
            $data['line'] = $exception->getLine();
            $data['code'] = $exception->getCode();
            $data['trace'] = $this->formatTrace($exception);

            $previous = $exception->getPrevious();
            if ($previous !== null) {
                $data['previous'] = [
                    'exception' => get_class($previous),
                    'message' => $previous->getMessage(),
                    'file' => $previous->getFile(),
                    'line' => $previous->getLine(),
                ];
            }
        }

        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Format the stack trace for JSON output.
     *
     * @return array<int, array<string, mixed>>
     */
    private function formatTrace(\Throwable $exception): array
    {
        $frames = [];

        foreach ($exception->getTrace() as $frame) {
            $entry = [];

            if (isset($frame['file'])) {
                $entry['file'] = $frame['file'];
            }
            if (isset($frame['line'])) {
                $entry['line'] = $frame['line'];
            }

            $call = '';
            if (isset($frame['class'])) {
                $call .= $frame['class'] . ($frame['type'] ?? '::');
            }
            $call .= $frame['function'] ?? '';
            $entry['call'] = $call . '()';

            $frames[] = $entry;
        }

        return $frames;
    }
}
