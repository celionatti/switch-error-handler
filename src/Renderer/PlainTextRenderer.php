<?php

declare(strict_types=1);

namespace Switch\ErrorHandler\Renderer;

/**
 * Plain text error renderer for CLI output.
 */
class PlainTextRenderer implements RendererInterface
{
    public function __construct(
        private readonly bool $debug = false
    ) {
    }

    public function render(\Throwable $exception): string
    {
        $class = get_class($exception);
        $message = $exception->getMessage();

        if (!$this->debug) {
            return "Error: {$message}\n";
        }

        $output = str_repeat('=', 60) . "\n";
        $output .= "  EXCEPTION: {$class}\n";
        $output .= str_repeat('=', 60) . "\n\n";
        $output .= "  Message:  {$message}\n";
        $output .= "  Code:     {$exception->getCode()}\n";
        $output .= "  File:     {$exception->getFile()}:{$exception->getLine()}\n\n";

        $output .= str_repeat('-', 60) . "\n";
        $output .= "  STACK TRACE\n";
        $output .= str_repeat('-', 60) . "\n\n";
        $output .= $exception->getTraceAsString() . "\n\n";

        // Previous exceptions
        $previous = $exception->getPrevious();
        $depth = 1;
        while ($previous !== null && $depth <= 5) {
            $output .= str_repeat('-', 60) . "\n";
            $output .= "  CAUSED BY: " . get_class($previous) . "\n";
            $output .= str_repeat('-', 60) . "\n";
            $output .= "  Message:  {$previous->getMessage()}\n";
            $output .= "  File:     {$previous->getFile()}:{$previous->getLine()}\n\n";
            $previous = $previous->getPrevious();
            $depth++;
        }

        return $output;
    }
}
