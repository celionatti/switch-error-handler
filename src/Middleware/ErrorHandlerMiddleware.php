<?php

declare(strict_types=1);

namespace Switch\ErrorHandler\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Switch\ErrorHandler\ErrorHandler;
use Switch\ErrorHandler\Exception\HttpException;
use Switch\Http\Response;
use Switch\Http\Stream;

/**
 * PSR-15 middleware that catches all exceptions thrown during request handling
 * and converts them into proper HTTP error responses.
 */
class ErrorHandlerMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ErrorHandler $errorHandler
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (\Throwable $exception) {
            // Report the exception
            $this->errorHandler->reportException($exception);

            // Determine status code
            $statusCode = $this->errorHandler->resolveStatusCode($exception);

            // Render the error
            $output = $this->errorHandler->getRenderer()->render($exception);

            // Determine content type
            $renderer = $this->errorHandler->getRenderer();
            $contentType = ($renderer instanceof \Switch\ErrorHandler\Renderer\JsonRenderer)
                ? 'application/json; charset=UTF-8'
                : 'text/html; charset=UTF-8';

            // Build headers
            $headers = ['Content-Type' => $contentType];

            // Merge HttpException headers
            if ($exception instanceof HttpException) {
                foreach ($exception->getHeaders() as $name => $value) {
                    $headers[$name] = $value;
                }
            }

            return new Response(
                $statusCode,
                $headers,
                Stream::create($output)
            );
        }
    }
}
