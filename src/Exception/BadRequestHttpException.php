<?php

declare(strict_types=1);

namespace Switch\ErrorHandler\Exception;

class BadRequestHttpException extends HttpException
{
    public function __construct(string $message = 'Bad Request', ?\Throwable $previous = null, array $headers = [])
    {
        parent::__construct(400, $message, $previous, $headers);
    }
}
