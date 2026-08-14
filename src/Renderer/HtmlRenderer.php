<?php

declare(strict_types=1);

namespace Switch\ErrorHandler\Renderer;

use Switch\ErrorHandler\Exception\HttpException;
use Throwable;

/**
 * Modern, high-performance card-grid HTML error page with interactive stack frame inspection,
 * contextual code snippets, tabbed environment/request drawer, and responsive layout.
 */
class HtmlRenderer implements RendererInterface
{
    public function render(Throwable $exception): string
    {
        $title = $this->getTitle($exception);
        $statusCode = $this->getStatusCode($exception);
        $exceptionClass = get_class($exception);
        $shortClass = (new \ReflectionClass($exception))->getShortName();
        $message = htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8');
        $file = htmlspecialchars($exception->getFile(), ENT_QUOTES, 'UTF-8');
        $shortFile = htmlspecialchars(basename($exception->getFile()), ENT_QUOTES, 'UTF-8');
        $line = $exception->getLine();
        $code = $exception->getCode();

        $mainSnippet = $this->getCodeSnippet($exception->getFile(), $line, 10);
        $framesHtml = $this->renderFrames($exception);
        $chainHtml = $this->renderExceptionChain($exception);
        $requestData = $this->extractRequestData();
        $traceCount = count($exception->getTrace());

        $memoryPeak = $this->formatBytes(memory_get_peak_usage(true));
        $phpVersion = PHP_VERSION;
        $os = PHP_OS_FAMILY;

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0">
    <title>{$title} — {$shortClass}</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Instrument+Sans:ital,wght@0,400..700;1,400..700&family=JetBrains+Mono:ital,wght@0,400..700;1,400..700&display=swap" rel="stylesheet">
    <style>{$this->getStyles()}</style>
</head>
<body>
    <div class="error-dashboard">
        <!-- Top Navigation Bar -->
        <nav class="top-bar">
            <div class="top-left">
                <span class="status-badge status-{$statusCode}">
                    <span class="pulse-dot"></span>
                    {$title}
                </span>
                <span class="brand-tag">⚡ Switch Error Inspector</span>
            </div>
            <div class="top-meta">
                <span class="meta-chip"><span>PHP</span> {$phpVersion}</span>
                <span class="meta-chip"><span>Memory</span> {$memoryPeak}</span>
                <span class="meta-chip"><span>OS</span> {$os}</span>
            </div>
            <div class="top-actions">
                <button type="button" class="btn-action" onclick="copyErrorDetails()">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    <span>Copy Error</span>
                </button>
            </div>
        </nav>

        <!-- Hero Exception Banner Card -->
        <header class="hero-card">
            <div class="hero-badge-row">
                <span class="exception-type">{$exceptionClass}</span>
                <span class="error-code-badge">Code: {$code}</span>
            </div>
            <h1 class="error-message-title">{$message}</h1>
            <div class="error-location-strip">
                <div class="location-item">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                    <span class="file-path" title="{$file}">{$file}</span>
                    <span class="line-badge">Line {$line}</span>
                </div>
            </div>
        </header>

        <!-- Main Multi-Column Grid -->
        <main class="dashboard-grid">
            <!-- Left Column: Source Code & Stack Trace Frames -->
            <div class="grid-col-main">
                <!-- Source Code Card -->
                <section class="card source-card">
                    <div class="card-header">
                        <div class="card-title-group">
                            <span class="card-icon">💻</span>
                            <h2 class="card-title">Source Code</h2>
                            <span class="file-basename">{$shortFile}:{$line}</span>
                        </div>
                    </div>
                    <div class="card-body no-pad">
                        <div class="code-container">
                            {$mainSnippet}
                        </div>
                    </div>
                </section>

                <!-- Stack Trace Card -->
                <section class="card trace-card">
                    <div class="card-header">
                        <div class="card-title-group">
                            <span class="card-icon">🪜</span>
                            <h2 class="card-title">Stack Trace</h2>
                            <span class="trace-count">{$traceCount} frames</span>
                        </div>
                        <div class="trace-controls">
                            <button type="button" class="btn-mini" onclick="toggleAllFrames(true)">Expand All</button>
                            <button type="button" class="btn-mini" onclick="toggleAllFrames(false)">Collapse All</button>
                        </div>
                    </div>
                    <div class="card-body no-pad">
                        <div class="frames-list">
                            {$framesHtml}
                        </div>
                    </div>
                </section>

                {$chainHtml}
            </div>

            <!-- Right Column: Diagnostics, Metrics & Request Drawer -->
            <aside class="grid-col-sidebar">
                <!-- Request & Environment Card -->
                <section class="card">
                    <div class="card-header">
                        <div class="card-title-group">
                            <span class="card-icon">🌐</span>
                            <h2 class="card-title">Request Context</h2>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="metrics-grid">
                            <div class="metric-box">
                                <span class="metric-label">Method</span>
                                <span class="metric-value method-badge method-{$requestData['method']}">{$requestData['method']}</span>
                            </div>
                            <div class="metric-box">
                                <span class="metric-label">Status</span>
                                <span class="metric-value">{$statusCode}</span>
                            </div>
                            <div class="metric-box full-width">
                                <span class="metric-label">Request URI</span>
                                <span class="metric-value uri-text" title="{$requestData['uri']}">{$requestData['uri']}</span>
                            </div>
                            <div class="metric-box">
                                <span class="metric-label">Client IP</span>
                                <span class="metric-value">{$requestData['ip']}</span>
                            </div>
                            <div class="metric-box">
                                <span class="metric-label">PHP SAPI</span>
                                <span class="metric-value">{$requestData['sapi']}</span>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Interactive Tabbed Drawer (Headers, Query, Post, Cookies, Session, Server) -->
                <section class="card drawer-card">
                    <div class="drawer-tabs">
                        <button type="button" class="tab-btn active" onclick="switchDrawerTab('headers', this)">
                            Headers <span class="tab-count">{$requestData['counts']['headers']}</span>
                        </button>
                        <button type="button" class="tab-btn" onclick="switchDrawerTab('query', this)">
                            Query <span class="tab-count">{$requestData['counts']['query']}</span>
                        </button>
                        <button type="button" class="tab-btn" onclick="switchDrawerTab('body', this)">
                            Body <span class="tab-count">{$requestData['counts']['body']}</span>
                        </button>
                        <button type="button" class="tab-btn" onclick="switchDrawerTab('cookies', this)">
                            Cookies <span class="tab-count">{$requestData['counts']['cookies']}</span>
                        </button>
                        <button type="button" class="tab-btn" onclick="switchDrawerTab('session', this)">
                            Session <span class="tab-count">{$requestData['counts']['session']}</span>
                        </button>
                        <button type="button" class="tab-btn" onclick="switchDrawerTab('server', this)">
                            Server <span class="tab-count">{$requestData['counts']['server']}</span>
                        </button>
                    </div>

                    <div class="drawer-search-bar">
                        <input type="text" id="drawerSearchInput" placeholder="Filter parameters..." onkeyup="filterDrawerTable(this.value)">
                    </div>

                    <div class="card-body no-pad drawer-body">
                        <!-- Headers Tab Pane -->
                        <div id="pane-headers" class="tab-pane active">
                            {$this->renderKeyValTable($requestData['headers'])}
                        </div>
                        <!-- Query Tab Pane -->
                        <div id="pane-query" class="tab-pane">
                            {$this->renderKeyValTable($requestData['query'])}
                        </div>
                        <!-- Body Tab Pane -->
                        <div id="pane-body" class="tab-pane">
                            {$this->renderKeyValTable($requestData['body'])}
                        </div>
                        <!-- Cookies Tab Pane -->
                        <div id="pane-cookies" class="tab-pane">
                            {$this->renderKeyValTable($requestData['cookies'])}
                        </div>
                        <!-- Session Tab Pane -->
                        <div id="pane-session" class="tab-pane">
                            {$this->renderKeyValTable($requestData['session'])}
                        </div>
                        <!-- Server Tab Pane -->
                        <div id="pane-server" class="tab-pane">
                            {$this->renderKeyValTable($requestData['server'])}
                        </div>
                    </div>
                </section>
            </aside>
        </main>

        <!-- Footer -->
        <footer class="error-footer">
            <span>Switch Framework v1.0.0</span>
            <span>•</span>
            <span>Zero-Overhead High-Velocity Architecture</span>
        </footer>
    </div>

    <!-- Hidden Container for Copying Error Details -->
    <textarea id="copyPayload" style="display:none;">{$this->getPlainTextPayload($exception)}</textarea>

    <script>{$this->getScript()}</script>
</body>
</html>
HTML;
    }

    private function getTitle(Throwable $exception): string
    {
        if ($exception instanceof HttpException) {
            return $exception->getStatusCode() . ' Error';
        }
        return '500 Error';
    }

    private function getStatusCode(Throwable $exception): int
    {
        if ($exception instanceof HttpException) {
            return $exception->getStatusCode();
        }
        return 500;
    }

    /**
     * Extract a code snippet around the error line.
     */
    private function getCodeSnippet(string $file, int $errorLine, int $padding = 8): string
    {
        if (!is_readable($file)) {
            return '<div class="snippet-empty">Source file not readable or internal engine script.</div>';
        }

        $lines = file($file);
        if ($lines === false) {
            return '<div class="snippet-empty">Could not read source file.</div>';
        }

        $start = max(0, $errorLine - $padding - 1);
        $end = min(count($lines), $errorLine + $padding);

        $html = '<div class="code-pre">';
        for ($i = $start; $i < $end; $i++) {
            $lineNum = $i + 1;
            $lineContent = htmlspecialchars($lines[$i] ?? '', ENT_QUOTES, 'UTF-8');
            $lineContent = rtrim($lineContent);
            $isError = ($lineNum === $errorLine);
            $rowClass = $isError ? 'code-line error-line' : 'code-line';
            $numStr = (string) $lineNum;

            $html .= "<div class=\"{$rowClass}\"><span class=\"line-num\">{$numStr}</span><span class=\"line-code\">{$lineContent}</span></div>";
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * Render the stack trace frames.
     */
    private function renderFrames(Throwable $exception): string
    {
        $trace = $exception->getTrace();
        $html = '';

        foreach ($trace as $index => $frame) {
            $frameFile = $frame['file'] ?? '[internal function]';
            $frameShortFile = htmlspecialchars(basename($frameFile), ENT_QUOTES, 'UTF-8');
            $fullFile = htmlspecialchars($frameFile, ENT_QUOTES, 'UTF-8');
            $frameLine = $frame['line'] ?? 0;
            $frameClass = htmlspecialchars($frame['class'] ?? '', ENT_QUOTES, 'UTF-8');
            $frameFunction = htmlspecialchars($frame['function'] ?? '', ENT_QUOTES, 'UTF-8');
            $frameType = htmlspecialchars($frame['type'] ?? '', ENT_QUOTES, 'UTF-8');

            $call = $frameClass ? "<span class=\"call-class\">{$frameClass}</span><span class=\"call-type\">{$frameType}</span><span class=\"call-func\">{$frameFunction}()</span>" : "<span class=\"call-func\">{$frameFunction}()</span>";

            $snippet = '';
            if (isset($frame['file']) && $frameLine > 0 && is_readable($frame['file'])) {
                $snippet = $this->getCodeSnippet($frame['file'], $frameLine, 4);
            }

            $isVendor = str_contains($frameFile, 'vendor');
            $frameCategory = $isVendor ? 'frame-vendor' : 'frame-app';
            $catLabel = $isVendor ? 'vendor' : 'app';

            $html .= <<<FRAME
<div class="frame-item {$frameCategory}">
    <div class="frame-header" onclick="toggleFrame(this)">
        <span class="frame-idx">#{$index}</span>
        <div class="frame-info">
            <div class="frame-call-row">
                <span class="frame-call">{$call}</span>
                <span class="cat-pill cat-{$catLabel}">{$catLabel}</span>
            </div>
            <div class="frame-location-row" title="{$fullFile}:{$frameLine}">
                <span class="loc-file">{$frameShortFile}</span>
                <span class="loc-line">:{$frameLine}</span>
            </div>
        </div>
        <button type="button" class="btn-toggle-frame" aria-label="Toggle frame">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
        </button>
    </div>
    <div class="frame-body" style="display:none;">
        <div class="frame-full-path">📄 {$fullFile}:{$frameLine}</div>
        {$snippet}
    </div>
</div>
FRAME;
        }

        return $html ?: '<div class="snippet-empty">No stack trace available.</div>';
    }

    /**
     * Render the previous exception chain.
     */
    private function renderExceptionChain(Throwable $exception): string
    {
        $previous = $exception->getPrevious();
        if ($previous === null) {
            return '';
        }

        $html = '<section class="card chain-card"><div class="card-header"><div class="card-title-group"><span class="card-icon">🔗</span><h2 class="card-title">Exception Chain</h2></div></div><div class="card-body"><div class="chain-timeline">';
        $depth = 0;

        while ($previous !== null && $depth < 10) {
            $class = htmlspecialchars(get_class($previous), ENT_QUOTES, 'UTF-8');
            $msg = htmlspecialchars($previous->getMessage(), ENT_QUOTES, 'UTF-8');
            $file = htmlspecialchars($previous->getFile(), ENT_QUOTES, 'UTF-8');
            $line = $previous->getLine();

            $html .= <<<CHAIN
<div class="chain-step">
    <div class="chain-marker"></div>
    <div class="chain-content">
        <span class="chain-class">{$class}</span>
        <p class="chain-msg">{$msg}</p>
        <span class="chain-loc">📄 {$file}:{$line}</span>
    </div>
</div>
CHAIN;
            $previous = $previous->getPrevious();
            $depth++;
        }

        $html .= '</div></div></section>';
        return $html;
    }

    /**
     * Extract request info for diagnostics.
     *
     * @return array<string, mixed>
     */
    private function extractRequestData(): array
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? (PHP_SAPI === 'cli' ? 'CLI' : 'GET');
        $uri = $_SERVER['REQUEST_URI'] ?? (PHP_SAPI === 'cli' ? 'command-line' : '/');
        $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $sapi = PHP_SAPI;

        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $name = str_replace('_', '-', substr($k, 5));
                $headers[strtolower($name)] = (string) $v;
            }
        }

        $query = $_GET ?? [];
        $body = $_POST ?? [];
        $cookies = $_COOKIE ?? [];
        $session = isset($_SESSION) ? $_SESSION : [];
        $server = $_SERVER ?? [];

        return [
            'method' => strtoupper($method),
            'uri' => $uri,
            'ip' => $ip,
            'sapi' => $sapi,
            'headers' => $headers,
            'query' => $query,
            'body' => $body,
            'cookies' => $cookies,
            'session' => $session,
            'server' => $server,
            'counts' => [
                'headers' => count($headers),
                'query' => count($query),
                'body' => count($body),
                'cookies' => count($cookies),
                'session' => count($session),
                'server' => count($server),
            ],
        ];
    }

    /**
     * Render an interactive key-value table for inspection.
     *
     * @param array<string, mixed> $data
     */
    private function renderKeyValTable(array $data): string
    {
        if (empty($data)) {
            return '<div class="tab-empty">No parameters recorded.</div>';
        }

        $rows = '';
        foreach ($data as $key => $val) {
            $k = htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8');
            $v = htmlspecialchars(is_array($val) ? json_encode($val, JSON_PRETTY_PRINT) : (string) $val, ENT_QUOTES, 'UTF-8');
            $rows .= "<tr class=\"drawer-row\"><td class=\"drawer-key\">{$k}</td><td class=\"drawer-val\">{$v}</td></tr>";
        }

        return <<<TABLE
<div class="table-responsive">
    <table class="drawer-table">
        <thead>
            <tr><th>Key</th><th>Value</th></tr>
        </thead>
        <tbody>
            {$rows}
        </tbody>
    </table>
</div>
TABLE;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = $bytes > 0 ? (int) floor(log($bytes, 1024)) : 0;
        return number_format($bytes / (1024 ** $power), 2) . ' ' . ($units[$power] ?? 'B');
    }

    private function getPlainTextPayload(Throwable $exception): string
    {
        return get_class($exception) . ": " . $exception->getMessage() . "\nIn " . $exception->getFile() . ":" . $exception->getLine() . "\n\nStack Trace:\n" . $exception->getTraceAsString();
    }

    private function getStyles(): string
    {
        return <<<'CSS'
*, *::before, *::after {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

:root {
    --bg-base: #090a0f;
    --bg-surface: #11141d;
    --bg-elevated: #181d2b;
    --bg-card: rgba(17, 20, 29, 0.95);
    
    --border-subtle: rgba(255, 255, 255, 0.08);
    --border-hover: rgba(255, 255, 255, 0.16);
    --border-danger: rgba(244, 63, 94, 0.4);
    
    --text-main: #f3f4f6;
    --text-muted: #94a3b8;
    --text-dim: #64748b;

    --rose-500: #f43f5e;
    --rose-400: #fb7185;
    --rose-glow: rgba(244, 63, 94, 0.15);

    --cyan-400: #22d3ee;
    --cyan-500: #06b6d4;
    --emerald-400: #34d399;
    --amber-400: #fbbf24;
    --indigo-400: #818cf8;

    --font-sans: 'Instrument Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    --font-mono: 'JetBrains Mono', Consolas, monospace;

    --radius-sm: 6px;
    --radius-md: 10px;
    --radius-lg: 14px;
    --radius-full: 9999px;

    --transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
}

body {
    font-family: var(--font-sans);
    background-color: var(--bg-base);
    color: var(--text-main);
    line-height: 1.55;
    min-height: 100vh;
    -webkit-font-smoothing: antialiased;
    padding-bottom: 40px;
}

.error-dashboard {
    max-width: 1440px;
    margin: 0 auto;
    padding: 20px 24px;
}

/* 1. Top Navigation Bar */
.top-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 12px 20px;
    background: var(--bg-surface);
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-lg);
    margin-bottom: 18px;
    backdrop-filter: blur(12px);
}

.top-left {
    display: flex;
    align-items: center;
    gap: 12px;
}

.status-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: rgba(244, 63, 94, 0.12);
    color: var(--rose-400);
    border: 1px solid var(--border-danger);
    padding: 4px 12px;
    border-radius: var(--radius-full);
    font-size: 0.8rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
}

.pulse-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background-color: var(--rose-400);
    box-shadow: 0 0 8px var(--rose-400);
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.4; transform: scale(1.3); }
}

.brand-tag {
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--cyan-400);
    letter-spacing: 0.02em;
}

.top-meta {
    display: flex;
    align-items: center;
    gap: 12px;
}

.meta-chip {
    font-size: 0.78rem;
    font-family: var(--font-mono);
    color: var(--text-muted);
    background: var(--bg-elevated);
    border: 1px solid var(--border-subtle);
    padding: 4px 10px;
    border-radius: var(--radius-sm);
}

.meta-chip span {
    color: var(--text-dim);
    margin-right: 4px;
}

.btn-action {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: var(--bg-elevated);
    color: var(--text-main);
    border: 1px solid var(--border-subtle);
    padding: 6px 14px;
    border-radius: var(--radius-sm);
    font-size: 0.8rem;
    font-weight: 600;
    cursor: pointer;
    transition: var(--transition);
}

.btn-action:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: var(--border-hover);
    color: #ffffff;
}

/* 2. Hero Exception Card */
.hero-card {
    background: linear-gradient(135deg, rgba(244, 63, 94, 0.08) 0%, rgba(17, 20, 29, 0.95) 100%);
    border: 1px solid var(--border-danger);
    border-radius: var(--radius-lg);
    padding: 24px 28px;
    margin-bottom: 20px;
    box-shadow: 0 12px 30px rgba(0, 0, 0, 0.4);
}

.hero-badge-row {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 8px;
}

.exception-type {
    font-family: var(--font-mono);
    font-size: 0.88rem;
    font-weight: 700;
    color: var(--rose-400);
    letter-spacing: -0.01em;
}

.error-code-badge {
    font-family: var(--font-mono);
    font-size: 0.72rem;
    background: rgba(0, 0, 0, 0.35);
    color: var(--text-dim);
    padding: 2px 8px;
    border-radius: var(--radius-sm);
    border: 1px solid var(--border-subtle);
}

.error-message-title {
    font-size: 1.45rem;
    font-weight: 700;
    color: #ffffff;
    line-height: 1.35;
    margin-bottom: 16px;
    letter-spacing: -0.02em;
    word-break: break-word;
}

.error-location-strip {
    display: flex;
    align-items: center;
    gap: 16px;
}

.location-item {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: rgba(0, 0, 0, 0.45);
    border: 1px solid var(--border-subtle);
    padding: 6px 14px;
    border-radius: var(--radius-md);
    font-family: var(--font-mono);
    font-size: 0.82rem;
}

.location-item svg {
    color: var(--rose-400);
}

.file-path {
    color: var(--text-muted);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
    max-width: 650px;
}

.line-badge {
    background: var(--rose-500);
    color: #ffffff;
    padding: 1px 7px;
    border-radius: var(--radius-sm);
    font-weight: 700;
    font-size: 0.75rem;
}

/* 3. Dashboard Multi-Column Grid Layout */
.dashboard-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.35fr) minmax(0, 1fr);
    gap: 20px;
    align-items: start;
}

.grid-col-main, .grid-col-sidebar {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

/* Generic Card Styling */
.card {
    background: var(--bg-surface);
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-lg);
    overflow: hidden;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.25);
}

.card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 18px;
    background: var(--bg-elevated);
    border-bottom: 1px solid var(--border-subtle);
}

.card-title-group {
    display: flex;
    align-items: center;
    gap: 8px;
}

.card-icon {
    font-size: 0.95rem;
}

.card-title {
    font-size: 0.85rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--text-main);
}

.file-basename, .trace-count {
    font-family: var(--font-mono);
    font-size: 0.72rem;
    color: var(--cyan-400);
    background: rgba(6, 182, 212, 0.08);
    border: 1px solid rgba(6, 182, 212, 0.2);
    padding: 2px 8px;
    border-radius: var(--radius-sm);
}

.card-body {
    padding: 18px;
}

.card-body.no-pad {
    padding: 0;
}

/* Source Code Block */
.code-container {
    background: #08090d;
    max-height: 380px;
    overflow-y: auto;
}

.code-pre {
    font-family: var(--font-mono);
    font-size: 0.82rem;
    line-height: 1.7;
    padding: 10px 0;
}

.code-line {
    display: flex;
    align-items: center;
    padding: 0 16px;
    transition: background 0.15s;
}

.code-line:hover {
    background: rgba(255, 255, 255, 0.03);
}

.code-line.error-line {
    background: rgba(244, 63, 94, 0.18);
    border-left: 3px solid var(--rose-500);
}

.line-num {
    width: 48px;
    min-width: 48px;
    color: var(--text-dim);
    user-select: none;
    text-align: right;
    padding-right: 18px;
    font-size: 0.75rem;
}

.error-line .line-num {
    color: var(--rose-400);
    font-weight: 700;
}

.line-code {
    white-space: pre;
    color: #e2e8f0;
    overflow-x: auto;
}

.error-line .line-code {
    color: #ffffff;
    font-weight: 600;
}

.snippet-empty {
    padding: 20px;
    color: var(--text-dim);
    font-style: italic;
    font-size: 0.85rem;
    text-align: center;
}

/* Stack Trace Frames List */
.trace-controls {
    display: flex;
    gap: 6px;
}

.btn-mini {
    background: transparent;
    border: 1px solid var(--border-subtle);
    color: var(--text-muted);
    padding: 2px 8px;
    border-radius: var(--radius-sm);
    font-size: 0.7rem;
    cursor: pointer;
    font-weight: 600;
    transition: var(--transition);
}

.btn-mini:hover {
    background: var(--bg-surface);
    color: var(--text-main);
}

.frames-list {
    max-height: 480px;
    overflow-y: auto;
}

.frame-item {
    border-bottom: 1px solid var(--border-subtle);
}

.frame-item:last-child {
    border-bottom: none;
}

.frame-header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 16px;
    background: var(--bg-surface);
    cursor: pointer;
    transition: background 0.15s;
}

.frame-header:hover {
    background: var(--bg-elevated);
}

.frame-idx {
    font-family: var(--font-mono);
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--text-dim);
    min-width: 24px;
}

.frame-info {
    flex: 1;
    min-width: 0;
}

.frame-call-row {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 2px;
}

.frame-call {
    font-family: var(--font-mono);
    font-size: 0.8rem;
    color: #ffffff;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.call-class { color: var(--indigo-400); }
.call-type { color: var(--text-dim); }
.call-func { color: var(--cyan-400); font-weight: 600; }

.cat-pill {
    font-family: var(--font-mono);
    font-size: 0.62rem;
    text-transform: uppercase;
    font-weight: 700;
    padding: 1px 5px;
    border-radius: var(--radius-sm);
}

.cat-app {
    background: rgba(34, 211, 238, 0.1);
    color: var(--cyan-400);
    border: 1px solid rgba(34, 211, 238, 0.25);
}

.cat-vendor {
    background: rgba(148, 163, 184, 0.08);
    color: var(--text-dim);
    border: 1px solid rgba(148, 163, 184, 0.15);
}

.frame-location-row {
    font-family: var(--font-mono);
    font-size: 0.72rem;
    color: var(--text-muted);
}

.loc-file { color: var(--text-muted); }
.loc-line { color: var(--rose-400); font-weight: 600; }

.btn-toggle-frame {
    background: transparent;
    border: none;
    color: var(--text-dim);
    cursor: pointer;
    padding: 4px;
    display: flex;
    align-items: center;
    transition: transform 0.2s;
}

.frame-item.open .btn-toggle-frame {
    transform: rotate(180deg);
}

.frame-body {
    padding: 12px 16px;
    background: #06070a;
    border-top: 1px solid var(--border-subtle);
}

.frame-full-path {
    font-family: var(--font-mono);
    font-size: 0.72rem;
    color: var(--text-dim);
    margin-bottom: 8px;
    word-break: break-all;
}

/* Exception Timeline Chain */
.chain-timeline {
    position: relative;
    padding-left: 20px;
}

.chain-timeline::before {
    content: '';
    position: absolute;
    top: 6px;
    bottom: 6px;
    left: 6px;
    width: 2px;
    background: var(--border-danger);
}

.chain-step {
    position: relative;
    margin-bottom: 14px;
}

.chain-step:last-child {
    margin-bottom: 0;
}

.chain-marker {
    position: absolute;
    left: -20px;
    top: 4px;
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: var(--rose-500);
    border: 2px solid var(--bg-surface);
}

.chain-class {
    font-family: var(--font-mono);
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--rose-400);
}

.chain-msg {
    font-size: 0.85rem;
    color: var(--text-main);
    margin: 2px 0 4px;
}

.chain-loc {
    font-family: var(--font-mono);
    font-size: 0.72rem;
    color: var(--text-dim);
}

/* Diagnostics & Metrics Grid */
.metrics-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.metric-box {
    background: var(--bg-elevated);
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-md);
    padding: 10px 14px;
}

.metric-box.full-width {
    grid-column: span 2;
}

.metric-label {
    display: block;
    font-size: 0.68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: var(--text-dim);
    margin-bottom: 4px;
}

.metric-value {
    font-family: var(--font-mono);
    font-size: 0.84rem;
    color: #ffffff;
    font-weight: 600;
}

.uri-text {
    word-break: break-all;
    font-size: 0.78rem;
    color: var(--cyan-400);
}

.method-badge {
    display: inline-block;
    padding: 2px 6px;
    border-radius: var(--radius-sm);
    font-size: 0.75rem;
}

.method-GET { background: rgba(52, 211, 153, 0.15); color: var(--emerald-400); }
.method-POST { background: rgba(6, 182, 212, 0.15); color: var(--cyan-400); }
.method-PUT, .method-PATCH { background: rgba(251, 191, 36, 0.15); color: var(--amber-400); }
.method-DELETE { background: rgba(244, 63, 94, 0.15); color: var(--rose-400); }

/* Tabbed Drawer */
.drawer-tabs {
    display: flex;
    overflow-x: auto;
    background: var(--bg-elevated);
    border-bottom: 1px solid var(--border-subtle);
    padding: 4px 6px 0;
    gap: 4px;
}

.tab-btn {
    background: transparent;
    border: none;
    color: var(--text-muted);
    padding: 8px 12px;
    font-size: 0.76rem;
    font-weight: 600;
    border-radius: var(--radius-md) var(--radius-md) 0 0;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    transition: var(--transition);
    white-space: nowrap;
}

.tab-btn:hover {
    color: var(--text-main);
    background: rgba(255, 255, 255, 0.03);
}

.tab-btn.active {
    color: var(--cyan-400);
    background: var(--bg-surface);
    border-top: 2px solid var(--cyan-400);
}

.tab-count {
    font-family: var(--font-mono);
    font-size: 0.65rem;
    background: rgba(0, 0, 0, 0.35);
    padding: 1px 5px;
    border-radius: var(--radius-full);
}

.drawer-search-bar {
    padding: 8px 12px;
    background: var(--bg-surface);
    border-bottom: 1px solid var(--border-subtle);
}

.drawer-search-bar input {
    width: 100%;
    background: var(--bg-elevated);
    border: 1px solid var(--border-subtle);
    border-radius: var(--radius-sm);
    padding: 6px 12px;
    font-size: 0.78rem;
    color: var(--text-main);
    outline: none;
}

.drawer-search-bar input:focus {
    border-color: var(--cyan-400);
}

.drawer-body {
    max-height: 380px;
    overflow-y: auto;
}

.tab-pane {
    display: none;
}

.tab-pane.active {
    display: block;
}

.drawer-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.76rem;
}

.drawer-table th {
    text-align: left;
    padding: 8px 14px;
    color: var(--text-dim);
    font-size: 0.68rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    background: var(--bg-elevated);
    border-bottom: 1px solid var(--border-subtle);
}

.drawer-table td {
    padding: 8px 14px;
    border-bottom: 1px solid var(--border-subtle);
    color: var(--text-muted);
}

.drawer-table tr:hover {
    background: rgba(255, 255, 255, 0.02);
}

.drawer-key {
    font-family: var(--font-mono);
    color: var(--cyan-400);
    white-space: nowrap;
    width: 35%;
}

.drawer-val {
    font-family: var(--font-mono);
    color: #e2e8f0;
    word-break: break-all;
}

.tab-empty {
    padding: 30px;
    text-align: center;
    color: var(--text-dim);
    font-size: 0.8rem;
    font-style: italic;
}

/* Footer */
.error-footer {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    margin-top: 36px;
    color: var(--text-dim);
    font-size: 0.75rem;
}

/* Responsive Media Queries */
@media (max-width: 1100px) {
    .dashboard-grid {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .error-dashboard {
        padding: 12px 14px;
    }

    .top-bar {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
    }

    .top-meta {
        flex-wrap: wrap;
    }

    .hero-card {
        padding: 18px 16px;
    }

    .error-message-title {
        font-size: 1.15rem;
    }

    .error-location-strip {
        flex-direction: column;
        align-items: flex-start;
        width: 100%;
    }

    .location-item {
        width: 100%;
    }

    .file-path {
        max-width: 100%;
    }

    .metrics-grid {
        grid-template-columns: 1fr;
    }

    .metric-box.full-width {
        grid-column: span 1;
    }
}
CSS;
    }

    private function getScript(): string
    {
        return <<<'JS'
function toggleFrame(el) {
    const parent = el.closest('.frame-item');
    const body = parent.querySelector('.frame-body');
    if (body) {
        const isHidden = body.style.display === 'none';
        body.style.display = isHidden ? 'block' : 'none';
        parent.classList.toggle('open', isHidden);
    }
}

function toggleAllFrames(expand) {
    document.querySelectorAll('.frame-item').forEach(item => {
        const body = item.querySelector('.frame-body');
        if (body) {
            body.style.display = expand ? 'block' : 'none';
            item.classList.toggle('open', expand);
        }
    });
}

function switchDrawerTab(tabId, btn) {
    document.querySelectorAll('.drawer-tabs .tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.drawer-body .tab-pane').forEach(p => p.classList.remove('active'));
    
    btn.classList.add('active');
    const targetPane = document.getElementById('pane-' + tabId);
    if (targetPane) {
        targetPane.classList.add('active');
    }
}

function filterDrawerTable(query) {
    const q = query.toLowerCase();
    document.querySelectorAll('.drawer-row').forEach(row => {
        const text = row.innerText.toLowerCase();
        row.style.display = text.includes(q) ? '' : 'none';
    });
}

function copyErrorDetails() {
    const text = document.getElementById('copyPayload').value;
    navigator.clipboard.writeText(text).then(() => {
        const btn = document.querySelector('.btn-action');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<span>✓ Copied!</span>';
        setTimeout(() => {
            btn.innerHTML = originalText;
        }, 2000);
    });
}
JS;
    }
}
