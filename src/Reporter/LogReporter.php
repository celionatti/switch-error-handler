<?php

declare(strict_types=1);

namespace Switch\ErrorHandler\Reporter;

class LogReporter implements ReporterInterface
{
    private string $logFile;

    private string $dateFormat;

    public function __construct(string $logFile, string $dateFormat = 'Y-m-d H:i:s')
    {
        $this->logFile = $logFile;
        $this->dateFormat = $dateFormat;
    }

    public function report(\Throwable $exception): void
    {
        $dir = dirname($this->logFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $entry = $this->formatEntry($exception);
        file_put_contents($this->logFile, $entry, FILE_APPEND | LOCK_EX);
    }

    /**
     * Format an exception into a log entry string.
     */
    private function formatEntry(\Throwable $exception): string
    {
        $timestamp = date($this->dateFormat);
        $class = get_class($exception);
        $message = $exception->getMessage();
        $code = $exception->getCode();
        $file = $exception->getFile();
        $line = $exception->getLine();
        $trace = $exception->getTraceAsString();

        $entry = "[{$timestamp}] {$class}: {$message} (code: {$code})\n";
        $entry .= "  in {$file}:{$line}\n";
        $entry .= "  Stack trace:\n";

        foreach (explode("\n", $trace) as $traceLine) {
            $entry .= "    {$traceLine}\n";
        }

        // Include previous exception chain
        $previous = $exception->getPrevious();
        $depth = 1;
        while ($previous !== null && $depth <= 5) {
            $entry .= "  Caused by: " . get_class($previous) . ": {$previous->getMessage()}\n";
            $entry .= "    in {$previous->getFile()}:{$previous->getLine()}\n";
            $previous = $previous->getPrevious();
            $depth++;
        }

        $entry .= str_repeat('-', 80) . "\n";

        return $entry;
    }
}
