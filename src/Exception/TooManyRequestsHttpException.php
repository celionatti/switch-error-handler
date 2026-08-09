<?php

declare(strict_types=1);

namespace Switch\ErrorHandler\Exception;

class TooManyRequestsHttpException extends HttpException
{
    public function __construct(
        int|string|null $retryAfter = null,
        string $message = 'Too Many Requests',
        ?\Throwable $previous = null
    ) {
        $headers = [];
        if ($retryAfter !== null) {
            $headers['Retry-After'] = (string) $retryAfter;
        }

        parent::__construct(429, $message, $previous, $headers);
    }
}
