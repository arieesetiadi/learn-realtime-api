<?php

declare(strict_types=1);

require __DIR__.'/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Test Environment
|--------------------------------------------------------------------------
|
| Docker Compose exports local-development values (Postgres, Redis) into the
| container environment. Those values take precedence over phpunit.xml, so we
| force test-safe defaults here before Laravel boots. CI can opt into Postgres
| with TEST_DB_CONNECTION=pgsql.
|
*/

$overrides = [
    'APP_ENV' => 'testing',
    'APP_MAINTENANCE_DRIVER' => 'file',
    'CACHE_STORE' => 'array',
    'SESSION_DRIVER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'BROADCAST_CONNECTION' => 'null',
    'MAIL_MAILER' => 'array',
    'LOG_CHANNEL' => 'null',
    'DB_URL' => '',
    'DB_CONNECTION' => getenv('TEST_DB_CONNECTION') ?: 'sqlite',
    'DB_DATABASE' => getenv('TEST_DB_DATABASE') ?: ':memory:',
];

foreach ($overrides as $key => $value) {
    putenv("{$key}={$value}");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}
