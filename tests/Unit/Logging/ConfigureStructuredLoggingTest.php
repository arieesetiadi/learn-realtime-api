<?php

declare(strict_types=1);

use App\Logging\ConfigureStructuredLogging;
use Illuminate\Log\Logger;
use Monolog\Formatter\JsonFormatter;
use Monolog\Handler\TestHandler;
use Monolog\Logger as MonologLogger;

test('the application log channel uses JSON output', function () {
    $handler = new TestHandler;
    $logger = new Logger(new MonologLogger('app', [$handler]));

    (new ConfigureStructuredLogging)($logger);

    expect($handler->getFormatter())->toBeInstanceOf(JsonFormatter::class);
});
