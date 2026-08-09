<?php

declare(strict_types=1);

namespace Switch\ErrorHandler\Exception;

class MethodNotAllowedHttpException extends HttpException
{
    /**
     * @param string[] $allowedMethods
     */
    public function __construct(
        array $allowedMethods = [],
        string $message = 'Method Not Allowed',
        ?\Throwable $previous = null
    ) {
        $headers = [];
        if (!empty($allowedMethods)) {
            $headers['Allow'] = implode(', ', $allowedMethods);
        }

        parent::__construct(405, $message, $previous, $headers);
    }
}
