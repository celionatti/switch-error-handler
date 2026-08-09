<?php

declare(strict_types=1);

namespace Switch\ErrorHandler\Tests;

use ErrorException;
use RuntimeException;
use Switch\ErrorHandler\ErrorHandler;
use Switch\ErrorHandler\Exception\BadRequestHttpException;
use Switch\ErrorHandler\Exception\ConflictHttpException;
use Switch\ErrorHandler\Exception\ForbiddenHttpException;
use Switch\ErrorHandler\Exception\GoneHttpException;
use Switch\ErrorHandler\Exception\HttpException;
use Switch\ErrorHandler\Exception\MethodNotAllowedHttpException;
use Switch\ErrorHandler\Exception\NotFoundHttpException;
use Switch\ErrorHandler\Exception\ServiceUnavailableHttpException;
use Switch\ErrorHandler\Exception\TooManyRequestsHttpException;
use Switch\ErrorHandler\Exception\UnauthorizedHttpException;
use Switch\ErrorHandler\Exception\UnprocessableEntityHttpException;
use Switch\ErrorHandler\Renderer\HtmlRenderer;
use Switch\ErrorHandler\Renderer\JsonRenderer;
use Switch\ErrorHandler\Renderer\PlainTextRenderer;
use Switch\ErrorHandler\Renderer\ProductionHtmlRenderer;
use Switch\ErrorHandler\Reporter\LogReporter;
use Switch\ErrorHandler\Reporter\ReporterInterface;

class ErrorHandlerTest
{
    protected function setUp(): void {}
    protected function tearDown(): void {}

    // ── ErrorHandler Core ──────────────────────────────────────────

    public function testRegisterCreatesInstance(): void
    {
        $handler = new ErrorHandler(true);
        assert($handler->isDebug() === true, 'Debug mode should be enabled');
    }

    public function testSetDebugToggles(): void
    {
        $handler = new ErrorHandler(false);
        assert($handler->isDebug() === false);

        $handler->setDebug(true);
        assert($handler->isDebug() === true);
    }

    public function testHandleErrorConvertsToException(): void
    {
        $handler = new ErrorHandler(true);

        try {
            $handler->handleError(E_WARNING, 'Test warning', '/test.php', 42);
            assert(false, 'Should have thrown ErrorException');
        } catch (ErrorException $e) {
            assert($e->getMessage() === 'Test warning');
            assert($e->getSeverity() === E_WARNING);
            assert($e->getFile() === '/test.php');
            assert($e->getLine() === 42);
        }
    }

    public function testHandleErrorRespectsErrorReporting(): void
    {
        $handler = new ErrorHandler(true);

        // Suppress all errors temporarily
        $oldLevel = error_reporting(0);
        $result = $handler->handleError(E_NOTICE, 'Suppressed notice', '/test.php', 1);
        error_reporting($oldLevel);

        assert($result === false, 'Suppressed errors should return false');
    }

    public function testHandleExceptionCallsOutputCallback(): void
    {
        $handler = new ErrorHandler(true);

        $captured = null;
        $capturedCode = null;
        $handler->setOutputCallback(function (string $output, int $code, \Throwable $e) use (&$captured, &$capturedCode) {
            $captured = $output;
            $capturedCode = $code;
        });

        $handler->handleException(new RuntimeException('Test exception'));

        assert($captured !== null, 'Output callback should have been called');
        assert($capturedCode === 500, 'Status code should be 500 for generic exception');
    }

    public function testHandleExceptionUsesHttpExceptionStatusCode(): void
    {
        $handler = new ErrorHandler(true);

        $capturedCode = null;
        $handler->setOutputCallback(function (string $output, int $code) use (&$capturedCode) {
            $capturedCode = $code;
        });

        $handler->handleException(new NotFoundHttpException());

        assert($capturedCode === 404, "Expected 404, got {$capturedCode}");
    }

    public function testResolveStatusCode(): void
    {
        $handler = new ErrorHandler();

        assert($handler->resolveStatusCode(new RuntimeException()) === 500);
        assert($handler->resolveStatusCode(new NotFoundHttpException()) === 404);
        assert($handler->resolveStatusCode(new ForbiddenHttpException()) === 403);
        assert($handler->resolveStatusCode(new HttpException(503)) === 503);
    }

    public function testAddReporterIsCalledOnException(): void
    {
        $handler = new ErrorHandler(true);

        $reported = null;
        $reporter = new class implements ReporterInterface {
            public ?\Throwable $exception = null;
            public function report(\Throwable $exception): void
            {
                $this->exception = $exception;
            }
        };

        $handler->addReporter($reporter);
        $handler->setOutputCallback(function () {}); // suppress output

        $exception = new RuntimeException('Reporter test');
        $handler->handleException($exception);

        assert($reporter->exception === $exception, 'Reporter should have received the exception');
    }

    public function testReporterFailureIsSilenced(): void
    {
        $handler = new ErrorHandler(true);

        $failingReporter = new class implements ReporterInterface {
            public function report(\Throwable $exception): void
            {
                throw new RuntimeException('Reporter crashed!');
            }
        };

        $handler->addReporter($failingReporter);
        $handler->setOutputCallback(function () {}); // suppress output

        // Should not throw even though the reporter fails
        $handler->handleException(new RuntimeException('Test'));
        assert(true, 'Reporter failure should be silenced');
    }

    // ── HTTP Exceptions ────────────────────────────────────────────

    public function testHttpExceptionStatusCodes(): void
    {
        $cases = [
            [new BadRequestHttpException(), 400, 'Bad Request'],
            [new UnauthorizedHttpException(), 401, 'Unauthorized'],
            [new ForbiddenHttpException(), 403, 'Forbidden'],
            [new NotFoundHttpException(), 404, 'Not Found'],
            [new MethodNotAllowedHttpException(['GET', 'POST']), 405, 'Method Not Allowed'],
            [new ConflictHttpException(), 409, 'Conflict'],
            [new GoneHttpException(), 410, 'Gone'],
            [new UnprocessableEntityHttpException(), 422, 'Unprocessable Entity'],
            [new TooManyRequestsHttpException(60), 429, 'Too Many Requests'],
            [new ServiceUnavailableHttpException(120), 503, 'Service Unavailable'],
        ];

        foreach ($cases as [$exception, $expectedCode, $expectedMessage]) {
            $class = get_class($exception);
            assert(
                $exception->getStatusCode() === $expectedCode,
                "{$class} should have status {$expectedCode}, got {$exception->getStatusCode()}"
            );
            assert(
                $exception->getMessage() === $expectedMessage,
                "{$class} should have message '{$expectedMessage}'"
            );
        }
    }

    public function testHttpExceptionCustomMessage(): void
    {
        $e = new NotFoundHttpException('User not found');
        assert($e->getMessage() === 'User not found');
        assert($e->getStatusCode() === 404);
    }

    public function testHttpExceptionHeaders(): void
    {
        $e = new HttpException(401, 'Auth required', null, ['WWW-Authenticate' => 'Bearer']);
        assert($e->getHeaders()['WWW-Authenticate'] === 'Bearer');
    }

    public function testMethodNotAllowedSetsAllowHeader(): void
    {
        $e = new MethodNotAllowedHttpException(['GET', 'POST']);
        assert($e->getHeaders()['Allow'] === 'GET, POST', 'Should set Allow header');
    }

    public function testTooManyRequestsSetsRetryAfterHeader(): void
    {
        $e = new TooManyRequestsHttpException(60);
        assert($e->getHeaders()['Retry-After'] === '60', 'Should set Retry-After header');
    }

    public function testServiceUnavailableSetsRetryAfterHeader(): void
    {
        $e = new ServiceUnavailableHttpException(300);
        assert($e->getHeaders()['Retry-After'] === '300', 'Should set Retry-After header');
    }

    public function testHttpExceptionPreviousChain(): void
    {
        $cause = new RuntimeException('DB is down');
        $e = new ServiceUnavailableHttpException(null, 'Service Unavailable', $cause);
        assert($e->getPrevious() === $cause);
    }

    public function testSetHeaders(): void
    {
        $e = new HttpException(500, 'Error');
        $e->setHeaders(['X-Custom' => 'value']);
        assert($e->getHeaders()['X-Custom'] === 'value');
    }

    // ── Renderers ──────────────────────────────────────────────────

    public function testHtmlRendererContainsExceptionDetails(): void
    {
        $renderer = new HtmlRenderer();
        $output = $renderer->render(new RuntimeException('Something broke'));

        assert(str_contains($output, 'RuntimeException'), 'Should contain exception class');
        assert(str_contains($output, 'Something broke'), 'Should contain message');
        assert(str_contains($output, 'Stack Trace'), 'Should contain stack trace section');
        assert(str_contains($output, '<!DOCTYPE html>'), 'Should be valid HTML');
    }

    public function testHtmlRendererShowsHttpExceptionCode(): void
    {
        $renderer = new HtmlRenderer();
        $output = $renderer->render(new NotFoundHttpException('Missing page'));

        assert(str_contains($output, '404 Error'), 'Should show 404 error badge');
        assert(str_contains($output, 'Missing page'), 'Should show custom message');
    }

    public function testJsonRendererDebugMode(): void
    {
        $renderer = new JsonRenderer(true);
        $output = $renderer->render(new RuntimeException('JSON error'));
        $data = json_decode($output, true);

        assert($data['error'] === true);
        assert($data['status'] === 500);
        assert($data['message'] === 'JSON error');
        assert($data['exception'] === 'RuntimeException');
        assert(isset($data['file']));
        assert(isset($data['line']));
        assert(isset($data['trace']));
    }

    public function testJsonRendererProductionMode(): void
    {
        $renderer = new JsonRenderer(false);
        $output = $renderer->render(new NotFoundHttpException('Not found'));
        $data = json_decode($output, true);

        assert($data['error'] === true);
        assert($data['status'] === 404);
        assert($data['message'] === 'Not found');
        assert(!isset($data['trace']), 'Production mode should NOT include trace');
        assert(!isset($data['file']), 'Production mode should NOT include file');
    }

    public function testJsonRendererWithHttpException(): void
    {
        $renderer = new JsonRenderer(true);
        $output = $renderer->render(new ForbiddenHttpException('Access denied'));
        $data = json_decode($output, true);

        assert($data['status'] === 403);
        assert($data['message'] === 'Access denied');
    }

    public function testJsonRendererWithPrevious(): void
    {
        $renderer = new JsonRenderer(true);
        $cause = new RuntimeException('Root cause');
        $exception = new HttpException(500, 'Wrapped', $cause);
        $output = $renderer->render($exception);
        $data = json_decode($output, true);

        assert(isset($data['previous']));
        assert($data['previous']['message'] === 'Root cause');
    }

    public function testPlainTextRendererDebugMode(): void
    {
        $renderer = new PlainTextRenderer(true);
        $output = $renderer->render(new RuntimeException('CLI error'));

        assert(str_contains($output, 'RuntimeException'), 'Should contain class');
        assert(str_contains($output, 'CLI error'), 'Should contain message');
        assert(str_contains($output, 'STACK TRACE'), 'Should contain stack trace');
    }

    public function testPlainTextRendererProductionMode(): void
    {
        $renderer = new PlainTextRenderer(false);
        $output = $renderer->render(new RuntimeException('CLI error'));

        assert(str_contains($output, 'Error: CLI error'), 'Should contain error message');
        assert(!str_contains($output, 'STACK TRACE'), 'Should NOT contain stack trace');
    }

    public function testProductionHtmlRendererNoInternals(): void
    {
        $renderer = new ProductionHtmlRenderer();
        $output = $renderer->render(new NotFoundHttpException('Secret path'));

        assert(str_contains($output, '404'), 'Should show status code');
        assert(str_contains($output, 'Page Not Found'), 'Should show friendly title');
        assert(!str_contains($output, 'Secret path'), 'Should NOT expose exception message');
        assert(!str_contains($output, 'Stack Trace'), 'Should NOT show stack trace');
        assert(str_contains($output, 'Go Home'), 'Should have a go-home link');
    }

    public function testProductionHtmlRendererVariousStatusCodes(): void
    {
        $renderer = new ProductionHtmlRenderer();

        $output500 = $renderer->render(new RuntimeException('DB crash'));
        assert(str_contains($output500, '500'), 'Should show 500');
        assert(str_contains($output500, 'Server Error'), 'Should show Server Error title');
        assert(!str_contains($output500, 'DB crash'), 'Should NOT show internal message');

        $output403 = $renderer->render(new ForbiddenHttpException());
        assert(str_contains($output403, '403'), 'Should show 403');
        assert(str_contains($output403, 'Access Denied'), 'Should show Access Denied');
    }

    // ── LogReporter ────────────────────────────────────────────────

    public function testLogReporterWritesToFile(): void
    {
        $logFile = sys_get_temp_dir() . '/switch_test_' . uniqid() . '.log';

        try {
            $reporter = new LogReporter($logFile);
            $exception = new RuntimeException('Test log entry');
            $reporter->report($exception);

            assert(file_exists($logFile), 'Log file should be created');
            $contents = file_get_contents($logFile);
            assert(str_contains($contents, 'RuntimeException'), 'Should log exception class');
            assert(str_contains($contents, 'Test log entry'), 'Should log message');
            assert(str_contains($contents, 'Stack trace'), 'Should log stack trace');
        } finally {
            if (file_exists($logFile)) {
                unlink($logFile);
            }
        }
    }

    public function testLogReporterAppends(): void
    {
        $logFile = sys_get_temp_dir() . '/switch_test_' . uniqid() . '.log';

        try {
            $reporter = new LogReporter($logFile);
            $reporter->report(new RuntimeException('First error'));
            $reporter->report(new RuntimeException('Second error'));

            $contents = file_get_contents($logFile);
            assert(str_contains($contents, 'First error'));
            assert(str_contains($contents, 'Second error'));
        } finally {
            if (file_exists($logFile)) {
                unlink($logFile);
            }
        }
    }

    public function testLogReporterIncludesPreviousException(): void
    {
        $logFile = sys_get_temp_dir() . '/switch_test_' . uniqid() . '.log';

        try {
            $reporter = new LogReporter($logFile);
            $cause = new RuntimeException('Root cause');
            $exception = new HttpException(500, 'Wrapper', $cause);
            $reporter->report($exception);

            $contents = file_get_contents($logFile);
            assert(str_contains($contents, 'Caused by: RuntimeException'));
            assert(str_contains($contents, 'Root cause'));
        } finally {
            if (file_exists($logFile)) {
                unlink($logFile);
            }
        }
    }

    // ── Auto-renderer Selection ────────────────────────────────────

    public function testGetRendererDefaultsToPlainTextInCli(): void
    {
        $handler = new ErrorHandler(true);
        // In CLI mode, should return PlainTextRenderer
        $renderer = $handler->getRenderer();
        assert($renderer instanceof PlainTextRenderer, 'CLI should use PlainTextRenderer, got ' . get_class($renderer));
    }

    public function testGetRendererUsesCustomRenderer(): void
    {
        $handler = new ErrorHandler(true);
        $custom = new JsonRenderer(true);
        $handler->setRenderer($custom);

        assert($handler->getRenderer() === $custom, 'Should use the custom renderer');
    }
}
