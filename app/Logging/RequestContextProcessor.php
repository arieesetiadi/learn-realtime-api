<?php

declare(strict_types=1);

namespace App\Logging;

use Illuminate\Support\Facades\Auth;
use Monolog\LogRecord;

/**
 * Adds request-scoped context to every Monolog record.
 *
 * The context is resolved when the record is written, so callers do not need
 * to pass request or user details into every log call. Auth::hasUser() avoids
 * triggering guard resolution just to write a log record.
 */
final class RequestContextProcessor
{
    public function __invoke(LogRecord $record): LogRecord
    {
        if (! app()->bound('request')) {
            return $record;
        }

        $request = request();

        return $record->with(extra: array_merge($record->extra, array_filter([
            'request_id' => $request->attributes->get('request_id'),
            'user_id' => Auth::hasUser() ? Auth::id() : null,
            'route' => $request->route()?->getName(),
            'method' => $request->method(),
            'path' => $request->path(),
            'ip' => $request->ip(),
        ], static fn (mixed $value): bool => $value !== null)));
    }
}
