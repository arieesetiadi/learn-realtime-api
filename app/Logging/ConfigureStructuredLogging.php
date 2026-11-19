<?php

declare(strict_types=1);

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\FormattableHandlerInterface;
use Monolog\Logger as MonologLogger;

/**
 * Configures the application log channel for structured output.
 *
 * JSON logs are easy to ingest into a log aggregator later without changing
 * application code. Stack traces are included for exceptions.
 */
final class ConfigureStructuredLogging
{
    public function __invoke(Logger $logger): void
    {
        $monolog = $logger->getLogger();

        if (! $monolog instanceof MonologLogger) {
            return;
        }

        foreach ($monolog->getHandlers() as $handler) {
            if ($handler instanceof FormattableHandlerInterface) {
                $handler->setFormatter(new JsonFormatter(includeStacktraces: true));
            }
        }

        $monolog->pushProcessor(new RequestContextProcessor);
    }
}
