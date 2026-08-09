<?php

declare(strict_types=1);

namespace Switch\ErrorHandler\Exception;

class ServiceUnavailableHttpException extends HttpException
{
    public function __construct(
        int|string|null $retryAfter = null,
        string $message = 'Service Unavailable',
        ?\Throwable $previous = null
    ) {
        $headers = [];
        if ($retryAfter !== null) {
            $headers['Retry-After'] = (string) $retryAfter;
        }

        parent::__construct(503, $message, $previous, $headers);
    }
}
