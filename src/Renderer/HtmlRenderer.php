<?php

declare(strict_types=1);

namespace Switch\ErrorHandler\Renderer;

use Switch\ErrorHandler\Exception\HttpException;

/**
 * Beautiful development-mode HTML error page with stack trace,
 * source code snippets, syntax highlighting, and dark theme.
 */
class HtmlRenderer implements RendererInterface
{
    public function render(\Throwable $exception): string
    {
        $title = $this->getTitle($exception);
        $exceptionClass = get_class($exception);
        $message = htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8');
        $file = htmlspecialchars($exception->getFile(), ENT_QUOTES, 'UTF-8');
        $line = $exception->getLine();
        $code = $exception->getCode();

        $snippet = $this->getCodeSnippet($exception->getFile(), $line);
        $frames = $this->renderFrames($exception);
        $chainHtml = $this->renderExceptionChain($exception);
        $requestInfo = $this->renderRequestInfo();

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title}</title>
    <style>{$this->getStyles()}</style>
</head>
<body>
    <div class="error-container">
        <header class="error-header">
            <div class="error-badge">{$title}</div>
            <h1 class="error-class">{$exceptionClass}</h1>
            <p class="error-message">{$message}</p>
            <div class="error-meta">
                <span class="error-file">📄 {$file}:{$line}</span>
                <span class="error-code">Code: {$code}</span>
            </div>
        </header>

        <section class="source-section">
            <h2>Source</h2>
            <div class="code-block">{$snippet}</div>
        </section>

        <section class="trace-section">
            <h2>Stack Trace</h2>
            <div class="frames">{$frames}</div>
        </section>

        {$chainHtml}

        {$requestInfo}

        <footer class="error-footer">
            <p>Switch Framework — Error Handler</p>
        </footer>
    </div>
    <script>{$this->getScript()}</script>
</body>
</html>
HTML;
    }

    private function getTitle(\Throwable $exception): string
    {
        if ($exception instanceof HttpException) {
            return $exception->getStatusCode() . ' Error';
        }
        return '500 Error';
    }

    /**
     * Extract a code snippet around the error line.
     */
    private function getCodeSnippet(string $file, int $errorLine, int $padding = 10): string
    {
        if (!is_readable($file)) {
            return '<pre class="code-pre"><code>Source file not readable.</code></pre>';
        }

        $lines = file($file);
        if ($lines === false) {
            return '<pre class="code-pre"><code>Could not read source file.</code></pre>';
        }

        $start = max(0, $errorLine - $padding - 1);
        $end = min(count($lines), $errorLine + $padding);

        $html = '<pre class="code-pre"><code>';
        for ($i = $start; $i < $end; $i++) {
            $lineNum = $i + 1;
            $lineContent = htmlspecialchars($lines[$i] ?? '', ENT_QUOTES, 'UTF-8');
            $lineContent = rtrim($lineContent);
            $highlightClass = ($lineNum === $errorLine) ? ' class="highlight-line"' : '';
            $numStr = str_pad((string)$lineNum, 4, ' ', STR_PAD_LEFT);
            $html .= "<span{$highlightClass}><span class=\"line-num\">{$numStr}</span> {$lineContent}</span>\n";
        }
        $html .= '</code></pre>';

        return $html;
    }

    /**
     * Render the stack trace frames.
     */
    private function renderFrames(\Throwable $exception): string
    {
        $trace = $exception->getTrace();
        $html = '';

        foreach ($trace as $index => $frame) {
            $frameFile = htmlspecialchars($frame['file'] ?? '[internal]', ENT_QUOTES, 'UTF-8');
            $frameLine = $frame['line'] ?? 0;
            $frameClass = htmlspecialchars($frame['class'] ?? '', ENT_QUOTES, 'UTF-8');
            $frameFunction = htmlspecialchars($frame['function'] ?? '', ENT_QUOTES, 'UTF-8');
            $frameType = htmlspecialchars($frame['type'] ?? '', ENT_QUOTES, 'UTF-8');

            $call = $frameClass ? "{$frameClass}{$frameType}{$frameFunction}()" : "{$frameFunction}()";

            $snippet = '';
            if (isset($frame['file']) && $frameLine > 0) {
                $snippet = $this->getCodeSnippet($frame['file'], $frameLine, 5);
            }

            $html .= <<<FRAME
<div class="frame" onclick="toggleFrame(this)">
    <div class="frame-header">
        <span class="frame-index">#{$index}</span>
        <span class="frame-call">{$call}</span>
        <span class="frame-location">{$frameFile}:{$frameLine}</span>
    </div>
    <div class="frame-body" style="display:none;">
        {$snippet}
    </div>
</div>
FRAME;
        }

        return $html ?: '<p class="no-trace">No stack trace available.</p>';
    }

    /**
     * Render the previous exception chain.
     */
    private function renderExceptionChain(\Throwable $exception): string
    {
        $previous = $exception->getPrevious();
        if ($previous === null) {
            return '';
        }

        $html = '<section class="chain-section"><h2>Previous Exceptions</h2>';
        $depth = 0;

        while ($previous !== null && $depth < 10) {
            $class = htmlspecialchars(get_class($previous), ENT_QUOTES, 'UTF-8');
            $msg = htmlspecialchars($previous->getMessage(), ENT_QUOTES, 'UTF-8');
            $file = htmlspecialchars($previous->getFile(), ENT_QUOTES, 'UTF-8');
            $line = $previous->getLine();

            $html .= <<<CHAIN
<div class="chain-item">
    <strong>{$class}</strong>: {$msg}
    <div class="chain-location">📄 {$file}:{$line}</div>
</div>
CHAIN;
            $previous = $previous->getPrevious();
            $depth++;
        }

        $html .= '</section>';
        return $html;
    }

    /**
     * Render request information (if available).
     */
    private function renderRequestInfo(): string
    {
        if (PHP_SAPI === 'cli') {
            return '';
        }

        $method = htmlspecialchars($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN', ENT_QUOTES, 'UTF-8');
        $uri = htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/', ENT_QUOTES, 'UTF-8');
        $ip = htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'unknown', ENT_QUOTES, 'UTF-8');
        $phpVersion = htmlspecialchars(PHP_VERSION, ENT_QUOTES, 'UTF-8');

        $headersHtml = '';
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $headerName = htmlspecialchars(
                    str_replace('_', '-', substr($key, 5)),
                    ENT_QUOTES,
                    'UTF-8'
                );
                $headerValue = htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
                $headersHtml .= "<tr><td>{$headerName}</td><td>{$headerValue}</td></tr>";
            }
        }

        return <<<INFO
<section class="request-section">
    <h2>Request Information</h2>
    <div class="info-grid">
        <div class="info-item"><strong>Method</strong><span>{$method}</span></div>
        <div class="info-item"><strong>URI</strong><span>{$uri}</span></div>
        <div class="info-item"><strong>IP</strong><span>{$ip}</span></div>
        <div class="info-item"><strong>PHP</strong><span>{$phpVersion}</span></div>
    </div>
    <h3>Headers</h3>
    <table class="headers-table">
        <thead><tr><th>Header</th><th>Value</th></tr></thead>
        <tbody>{$headersHtml}</tbody>
    </table>
</section>
INFO;
    }

    private function getStyles(): string
    {
        return <<<'CSS'
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, 'Roboto', sans-serif;
    background: #0f0f1a;
    color: #e0e0e0;
    line-height: 1.6;
    min-height: 100vh;
}
.error-container {
    max-width: 1100px;
    margin: 0 auto;
    padding: 30px 20px;
}
.error-header {
    background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
    border: 1px solid #e74c3c44;
    border-radius: 12px;
    padding: 32px;
    margin-bottom: 24px;
}
.error-badge {
    display: inline-block;
    background: #e74c3c;
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    padding: 4px 14px;
    border-radius: 20px;
    margin-bottom: 16px;
    letter-spacing: 0.5px;
}
.error-class {
    font-size: 24px;
    color: #ff6b6b;
    font-weight: 700;
    margin-bottom: 8px;
    word-break: break-all;
}
.error-message {
    font-size: 17px;
    color: #ffa07a;
    margin-bottom: 16px;
    font-weight: 400;
}
.error-meta {
    display: flex;
    gap: 24px;
    font-size: 13px;
    color: #888;
}
.error-meta span { display: flex; align-items: center; gap: 6px; }

section {
    background: #1a1a2e;
    border: 1px solid #2a2a4a;
    border-radius: 10px;
    padding: 24px;
    margin-bottom: 20px;
}
h2 {
    font-size: 16px;
    color: #7b8cde;
    margin-bottom: 16px;
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 600;
}
h3 {
    font-size: 14px;
    color: #7b8cde;
    margin: 16px 0 8px;
    font-weight: 600;
}

.code-block { border-radius: 8px; overflow: hidden; }
.code-pre {
    background: #0d0d1a;
    padding: 16px;
    overflow-x: auto;
    font-size: 13px;
    line-height: 1.7;
    border-radius: 8px;
}
.code-pre code { font-family: 'JetBrains Mono', 'Fira Code', 'Consolas', monospace; }
.code-pre span { display: block; padding: 0 8px; }
.code-pre .highlight-line {
    background: #e74c3c22;
    border-left: 3px solid #e74c3c;
    margin-left: -8px;
    padding-left: 5px;
}
.line-num {
    display: inline-block;
    width: 42px;
    color: #555;
    text-align: right;
    margin-right: 12px;
    user-select: none;
}

.frame {
    border: 1px solid #2a2a4a;
    border-radius: 8px;
    margin-bottom: 8px;
    overflow: hidden;
    transition: border-color 0.2s;
}
.frame:hover { border-color: #7b8cde44; cursor: pointer; }
.frame-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 16px;
    background: #12122a;
    font-size: 13px;
}
.frame-index {
    color: #555;
    font-weight: 700;
    min-width: 28px;
}
.frame-call { color: #82aaff; font-family: 'JetBrains Mono', monospace; font-size: 12px; }
.frame-location { color: #666; margin-left: auto; font-size: 12px; white-space: nowrap; }
.frame-body { padding: 0 16px 16px; }

.chain-item {
    background: #12122a;
    border: 1px solid #2a2a4a;
    border-radius: 8px;
    padding: 14px 18px;
    margin-bottom: 8px;
    font-size: 14px;
}
.chain-item strong { color: #ff6b6b; }
.chain-location { color: #666; font-size: 12px; margin-top: 6px; }

.info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 12px;
    margin-bottom: 16px;
}
.info-item {
    background: #12122a;
    border-radius: 8px;
    padding: 12px 16px;
}
.info-item strong { display: block; color: #7b8cde; font-size: 11px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 4px; }
.info-item span { font-size: 14px; color: #e0e0e0; word-break: break-all; }

.headers-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 13px;
}
.headers-table th {
    text-align: left;
    color: #7b8cde;
    padding: 8px 12px;
    border-bottom: 1px solid #2a2a4a;
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.headers-table td {
    padding: 6px 12px;
    border-bottom: 1px solid #1a1a3a;
    color: #ccc;
}
.headers-table td:first-child { color: #82aaff; font-family: monospace; white-space: nowrap; }
.headers-table tbody tr:hover { background: #12122a; }

.error-footer {
    text-align: center;
    padding: 20px;
    color: #444;
    font-size: 12px;
}
.no-trace { color: #666; font-style: italic; }
CSS;
    }

    private function getScript(): string
    {
        return <<<'JS'
function toggleFrame(el) {
    var body = el.querySelector('.frame-body');
    if (body) {
        body.style.display = body.style.display === 'none' ? 'block' : 'none';
    }
}
JS;
    }
}
