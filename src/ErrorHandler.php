<?php

declare(strict_types=1);

namespace Switch\ErrorHandler;

use ErrorException;
use Switch\ErrorHandler\Exception\HttpException;
use Switch\ErrorHandler\Renderer\HtmlRenderer;
use Switch\ErrorHandler\Renderer\JsonRenderer;
use Switch\ErrorHandler\Renderer\PlainTextRenderer;
use Switch\ErrorHandler\Renderer\ProductionHtmlRenderer;
use Switch\ErrorHandler\Renderer\RendererInterface;
use Switch\ErrorHandler\Reporter\ReporterInterface;

class ErrorHandler
{
    private bool $debug = false;

    private ?RendererInterface $renderer = null;

    /** @var array<int, ReporterInterface> */
    private array $reporters = [];

    /** @var array<string, mixed> */
    private array $context = [];

    private bool $registered = false;

    /** @var callable|null */
    private mixed $previousErrorHandler = null;

    /** @var callable|null */
    private mixed $previousExceptionHandler = null;

    /** @var callable|null */
    private mixed $outputCallback = null;

    public function __construct(bool $debug = false)
    {
        $this->debug = $debug;
    }

    /**
     * Create a new ErrorHandler and register it immediately.
     */
    public static function register(bool $debug = false): self
    {
        $handler = new self($debug);
        $handler->registerHandlers();

        return $handler;
    }

    /**
     * Register as the global PHP error/exception/shutdown handler.
     */
    public function registerHandlers(): void
    {
        if ($this->registered) {
            return;
        }

        $this->previousErrorHandler = set_error_handler([$this, 'handleError']);
        $this->previousExceptionHandler = set_exception_handler([$this, 'handleException']);
        register_shutdown_function([$this, 'handleShutdown']);

        $this->registered = true;
    }

    /**
     * Restore the previous handlers.
     */
    public function unregister(): void
    {
        if (!$this->registered) {
            return;
        }

        restore_error_handler();
        restore_exception_handler();

        $this->registered = false;
    }

    /**
     * Set debug mode.
     */
    public function setDebug(bool $debug): self
    {
        $this->debug = $debug;
        return $this;
    }

    /**
     * Check if debug mode is active.
     */
    public function isDebug(): bool
    {
        return $this->debug;
    }

    /**
     * Set a custom renderer.
     */
    public function setRenderer(RendererInterface $renderer): self
    {
        $this->renderer = $renderer;
        return $this;
    }

    /**
     * Get the active renderer (auto-selects based on debug/context if none set).
     */
    public function getRenderer(): RendererInterface
    {
        if ($this->renderer !== null) {
            return $this->renderer;
        }

        // Auto-detect based on environment
        if (PHP_SAPI === 'cli') {
            return new PlainTextRenderer($this->debug);
        }

        if ($this->isJsonRequest()) {
            return new JsonRenderer($this->debug);
        }

        return $this->debug
            ? new HtmlRenderer()
            : new ProductionHtmlRenderer();
    }

    /**
     * Add a reporter (e.g. logger).
     */
    public function addReporter(ReporterInterface $reporter): self
    {
        $this->reporters[] = $reporter;
        return $this;
    }

    /**
     * Set additional context (request info, user, etc.) for reporters.
     */
    public function setContext(array $context): self
    {
        $this->context = $context;
        return $this;
    }

    /**
     * Set a custom output callback (useful for testing or custom response emission).
     */
    public function setOutputCallback(callable $callback): self
    {
        $this->outputCallback = $callback;
        return $this;
    }

    /**
     * Convert PHP errors into ErrorException.
     *
     * @throws ErrorException
     */
    public function handleError(int $severity, string $message, string $file = '', int $line = 0): bool
    {
        // Respect the error_reporting level and @ operator
        if (!(error_reporting() & $severity)) {
            return false;
        }

        throw new ErrorException($message, 0, $severity, $file, $line);
    }

    /**
     * Handle an uncaught exception.
     */
    public function handleException(\Throwable $exception): void
    {
        // Report to all reporters
        $this->reportException($exception);

        // Render the error
        $statusCode = $this->resolveStatusCode($exception);
        $output = $this->getRenderer()->render($exception);

        if ($this->outputCallback !== null) {
            ($this->outputCallback)($output, $statusCode, $exception);
            return;
        }

        // Send HTTP response if not in CLI and headers not yet sent
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            http_response_code($statusCode);

            $renderer = $this->getRenderer();
            if ($renderer instanceof JsonRenderer) {
                header('Content-Type: application/json; charset=UTF-8');
            } else {
                header('Content-Type: text/html; charset=UTF-8');
            }

            // Forward custom headers from HttpException
            if ($exception instanceof HttpException) {
                foreach ($exception->getHeaders() as $name => $value) {
                    header("{$name}: {$value}");
                }
            }
        }

        echo $output;
    }

    /**
     * Handle fatal errors on shutdown.
     */
    public function handleShutdown(): void
    {
        $error = error_get_last();

        if ($error !== null && $this->isFatalError($error['type'])) {
            $exception = new ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            );

            $this->handleException($exception);
        }
    }

    /**
     * Report an exception to all registered reporters.
     */
    public function reportException(\Throwable $exception): void
    {
        foreach ($this->reporters as $reporter) {
            try {
                $reporter->report($exception);
            } catch (\Throwable) {
                // Prevent reporter failures from causing further errors
            }
        }
    }

    /**
     * Resolve the HTTP status code for an exception.
     */
    public function resolveStatusCode(\Throwable $exception): int
    {
        if ($exception instanceof HttpException) {
            return $exception->getStatusCode();
        }

        return 500;
    }

    /**
     * Check if this is a JSON/API request.
     */
    private function isJsonRequest(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

        return str_contains($accept, 'application/json')
            || str_contains($accept, 'application/problem+json')
            || str_contains($contentType, 'application/json');
    }

    /**
     * Check if the error type is fatal.
     */
    private function isFatalError(int $type): bool
    {
        return in_array($type, [
            E_ERROR,
            E_CORE_ERROR,
            E_COMPILE_ERROR,
            E_PARSE,
        ], true);
    }
}
