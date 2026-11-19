<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Normalizes API exceptions into a stable JSON error contract.
 *
 * Validation errors keep Laravel's "message" + "errors" shape for client
 * compatibility, while every error also receives a machine-readable "code"
 * and the current request ID.
 */
final class ApiErrorResponse
{
    /**
     * Build a JSON error response for the given exception.
     *
     * Returns null for exceptions that already carry their own response.
     */
    public static function from(Throwable $exception, Request $request): ?JsonResponse
    {
        if ($exception instanceof HttpResponseException) {
            return null;
        }

        [$status, $code, $message, $headers] = self::map($exception);

        $payload = [
            'message' => $message,
            'code' => $code,
            'request_id' => $request->attributes->get('request_id'),
        ];

        if ($exception instanceof ValidationException) {
            $payload['errors'] = $exception->errors();
        }

        return response()->json($payload, $status, $headers);
    }

    /**
     * Map an exception to status, code, message, and headers.
     *
     * @return array{0: int, 1: string, 2: string, 3: array<string, mixed>}
     */
    private static function map(Throwable $exception): array
    {
        $headers = $exception instanceof HttpExceptionInterface
            ? $exception->getHeaders()
            : [];

        return match (true) {
            $exception instanceof ValidationException => [
                422,
                'validation_failed',
                $exception->getMessage(),
                $headers,
            ],
            $exception instanceof AuthenticationException => [
                401,
                'unauthenticated',
                'Unauthenticated.',
                $headers,
            ],
            $exception instanceof AuthorizationException,
            $exception instanceof AccessDeniedHttpException => [
                403,
                'forbidden',
                'Forbidden.',
                $headers,
            ],
            $exception instanceof ModelNotFoundException,
            $exception instanceof NotFoundHttpException => [
                404,
                'not_found',
                'Resource not found.',
                $headers,
            ],
            $exception instanceof ThrottleRequestsException => [
                429,
                'too_many_requests',
                'Too many requests.',
                $headers,
            ],
            $exception instanceof HttpExceptionInterface => [
                $exception->getStatusCode(),
                self::codeForStatus($exception->getStatusCode()),
                $exception->getMessage() !== '' ? $exception->getMessage() : 'HTTP error.',
                $headers,
            ],
            default => [
                500,
                'server_error',
                'Server Error.',
                [],
            ],
        };
    }

    /**
     * Map an HTTP status code to a stable error code.
     */
    private static function codeForStatus(int $status): string
    {
        return match ($status) {
            400 => 'bad_request',
            401 => 'unauthenticated',
            403 => 'forbidden',
            404 => 'not_found',
            405 => 'method_not_allowed',
            409 => 'conflict',
            413 => 'payload_too_large',
            422 => 'validation_failed',
            429 => 'too_many_requests',
            default => $status >= 500 ? 'server_error' : 'error',
        };
    }
}
