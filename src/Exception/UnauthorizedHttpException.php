<?php

declare(strict_types=1);

namespace Switch\ErrorHandler\Exception;

class UnauthorizedHttpException extends HttpException
{
    public function __construct(string $message = 'Unauthorized', ?\Throwable $previous = null, array $headers = [])
    {
        parent::__construct(401, $message, $previous, $headers);
    }
}
