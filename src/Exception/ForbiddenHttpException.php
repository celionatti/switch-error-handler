<?php

declare(strict_types=1);

namespace Switch\ErrorHandler\Exception;

class ForbiddenHttpException extends HttpException
{
    public function __construct(string $message = 'Forbidden', ?\Throwable $previous = null, array $headers = [])
    {
        parent::__construct(403, $message, $previous, $headers);
    }
}
