<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Assigns a request ID and writes one access log line per request.
 *
 * The ID is accepted from a trusted upstream header when it is well formed,
 * otherwise a new ULID is generated. The same ID is returned to the client so
 * it can be quoted when reporting a production issue.
 */
final class AssignRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->resolveRequestId($request);
        $request->attributes->set('request_id', $requestId);

        $startedAt = hrtime(true);

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            $this->logRequest($request, $this->statusFor($exception), $startedAt);

            throw $exception;
        }

        $response->headers->set('X-Request-Id', $requestId);

        $this->logRequest($request, $response->getStatusCode(), $startedAt);

        return $response;
    }

    /**
     * Accept a valid upstream request ID or generate a new one.
     */
    private function resolveRequestId(Request $request): string
    {
        $incoming = $request->header('X-Request-Id');

        if (is_string($incoming) && preg_match('/^[A-Za-z0-9._-]{8,128}$/', $incoming) === 1) {
            return $incoming;
        }

        return (string) Str::ulid();
    }

    /**
     * Resolve the HTTP status represented by an exception.
     */
    private function statusFor(Throwable $exception): int
    {
        return match (true) {
            $exception instanceof ValidationException => 422,
            $exception instanceof AuthenticationException => 401,
            $exception instanceof AuthorizationException => 403,
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            default => 500,
        };
    }

    /**
     * Write the request access log entry.
     */
    private function logRequest(Request $request, int $status, int $startedAt): void
    {
        Log::info('http.request', [
            'method' => $request->method(),
            'path' => $request->path(),
            'route' => $request->route()?->getName(),
            'status' => $status,
            'duration_ms' => (int) ((hrtime(true) - $startedAt) / 1_000_000),
        ]);
    }
}
