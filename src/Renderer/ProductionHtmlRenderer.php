<?php

declare(strict_types=1);

namespace Switch\ErrorHandler\Renderer;

use Switch\ErrorHandler\Exception\HttpException;

/**
 * Clean, user-friendly production error page.
 * Never exposes stack traces, file paths, or internal details.
 */
class ProductionHtmlRenderer implements RendererInterface
{
    /** @var array<int, string> */
    private const STATUS_TITLES = [
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Access Denied',
        404 => 'Page Not Found',
        405 => 'Method Not Allowed',
        409 => 'Conflict',
        410 => 'Gone',
        419 => 'Session Expired',
        422 => 'Validation Error',
        429 => 'Too Many Requests',
        500 => 'Server Error',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
    ];

    /** @var array<int, string> */
    private const STATUS_MESSAGES = [
        400 => 'The request could not be understood. Please check your input and try again.',
        401 => 'You need to be authenticated to access this resource.',
        403 => 'You do not have permission to access this resource.',
        404 => 'The page you are looking for does not exist or has been moved.',
        405 => 'The HTTP method used is not supported for this endpoint.',
        409 => 'The request conflicts with the current state of the resource.',
        410 => 'This resource is no longer available and has been permanently removed.',
        419 => 'Your session has expired. Please refresh and try again.',
        422 => 'The submitted data could not be processed. Please check and try again.',
        429 => 'You have made too many requests. Please wait a moment and try again.',
        500 => 'Something went wrong on our end. We\'re working to fix it.',
        502 => 'We received an invalid response from an upstream server.',
        503 => 'The service is temporarily unavailable. Please try again shortly.',
    ];

    /** @var array<int, string> */
    private const STATUS_ICONS = [
        400 => '⚠️',
        401 => '🔒',
        403 => '🚫',
        404 => '🔍',
        405 => '🚧',
        409 => '💥',
        410 => '👻',
        419 => '⏰',
        422 => '📝',
        429 => '🐌',
        500 => '⚙️',
        502 => '🌐',
        503 => '🔧',
    ];

    public function render(\Throwable $exception): string
    {
        $statusCode = $exception instanceof HttpException
            ? $exception->getStatusCode()
            : 500;

        $title = self::STATUS_TITLES[$statusCode] ?? 'Error';
        $message = self::STATUS_MESSAGES[$statusCode] ?? 'An unexpected error occurred.';
        $icon = self::STATUS_ICONS[$statusCode] ?? '❌';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$statusCode} — {$title}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, 'Roboto', sans-serif;
            background: #0f0f1a;
            color: #e0e0e0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }
        .error-page {
            text-align: center;
            max-width: 520px;
        }
        .error-icon {
            font-size: 64px;
            margin-bottom: 20px;
            animation: pulse 2s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.08); opacity: 0.8; }
        }
        .error-code {
            font-size: 96px;
            font-weight: 800;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            line-height: 1.1;
            margin-bottom: 8px;
        }
        .error-title {
            font-size: 24px;
            font-weight: 600;
            color: #c0c0d0;
            margin-bottom: 16px;
        }
        .error-message {
            font-size: 15px;
            color: #888;
            line-height: 1.7;
            margin-bottom: 32px;
        }
        .error-action a {
            display: inline-block;
            padding: 12px 32px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        .error-action a:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.3);
        }
        .error-footer {
            margin-top: 48px;
            color: #444;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="error-page">
        <div class="error-icon">{$icon}</div>
        <div class="error-code">{$statusCode}</div>
        <h1 class="error-title">{$title}</h1>
        <p class="error-message">{$message}</p>
        <div class="error-action">
            <a href="/">← Go Home</a>
        </div>
        <div class="error-footer">
            <p>Switch Framework</p>
        </div>
    </div>
</body>
</html>
HTML;
    }
}
