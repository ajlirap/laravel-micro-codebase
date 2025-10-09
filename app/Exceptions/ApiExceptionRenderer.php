<?php

namespace App\Exceptions;

use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response as HttpResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

class ApiExceptionRenderer
{
    public function __invoke(Throwable $e, $request)
    {
        return self::render($e, $request);
    }

    public static function render(Throwable $e, $request)
    {
        /** @var Request $request */
        $wantsJson = $request->expectsJson() || $request->is('api/*');
        if (!$wantsJson) {
            return null; // use default
        }

        [$status, $code, $message, $details] = self::map($e);

        $payload = ApiResponse::wrapError(
            status: $status,
            code: $code,
            message: $message,
            details: $details,
        );

        if (isset($payload['error']['traceId'])) {
            $payload['traceId'] = $payload['error']['traceId'];
        }

        return response()->json($payload, $status);
    }

    protected static function map(Throwable $e): array
    {
        $status = 500;
        $code = 'INTERNAL_ERROR';
        $message = 'An unexpected error occurred';
        $details = null;

        if ($e instanceof ValidationException) {
            $status = 422;
            $code = 'VALIDATION_ERROR';
            $message = 'Validation failed';
            $details = $e->errors();
        } elseif ($e instanceof BadRequestException) {
            $status = 400;
            $code = 'BAD_REQUEST';
            $message = $e->getMessage() ?: 'Invalid application payload';
        } elseif ($e instanceof AuthenticationException) {
            $status = 401;
            $code = 'UNAUTHORIZED';
            $message = 'Unauthorized';
        } elseif ($e instanceof AuthorizationException) {
            $status = 403;
            $code = 'FORBIDDEN';
            $message = 'Forbidden';
        } elseif ($e instanceof ModelNotFoundException) {
            $status = 404;
            $code = 'NOT_FOUND';
            $message = 'Resource not found';
        } elseif ($e instanceof ConnectionException) {
            $status = 503;
            $code = 'SERVICE_UNAVAILABLE';
            $message = 'Upstream service temporarily unavailable';
            if (config('app.debug')) {
                $details = ['reason' => $e->getMessage()];
            }
        } elseif ($e instanceof RequestException) {
            $httpResponse = $e->response;

            if ($httpResponse instanceof HttpResponse) {
                $status = $httpResponse->status();
                $code = self::defaultCodeFor($status);
                $body = $httpResponse->json();

                if (is_array($body)) {
                    $message = data_get($body, 'error.message') ?? data_get($body, 'message') ?? $message;
                    $details = data_get($body, 'error.details') ?? data_get($body, 'details') ?? $details;
                    $innerCode = data_get($body, 'error.code') ?? data_get($body, 'code');
                    if (is_scalar($innerCode)) {
                        $code = (string) $innerCode;
                    }
                } elseif (is_string($body) && $body !== '') {
                    $message = $body;
                } else {
                    $raw = $httpResponse->body();
                    if (is_string($raw) && $raw !== '') {
                        $message = $raw;
                    }
                }
            } else {
                $status = 502;
                $code = 'BAD_GATEWAY';
                $message = 'Received an invalid response from upstream service';
            }
        } elseif ($e instanceof ProviderRequestException) {
            $status = $e->statusCode ?? 502;
            $code = self::defaultCodeFor($status);
            $message = $e->getMessage();

            if (isset($e->context['body'])) {
                if (is_string($e->context['body']) && trim($e->context['body']) !== '') {
                    // surface upstream body so clients can diagnose payload issues
                    $details = ['providerResponse' => $e->context['body']];
                } elseif (is_array($e->context['body'])) {
                    $details = ['providerResponse' => $e->context['body']];
                }
            } elseif (!empty($e->context)) {
                $details = $e->context;
            }

            if ($status === 502 && $code === 'HTTP_ERROR') {
                $code = 'BAD_GATEWAY';
            }
        } elseif ($e instanceof HttpExceptionInterface) {
            $status = $e->getStatusCode();
            $message = $e->getMessage() ?: self::defaultMessageFor($status);
            $code = self::defaultCodeFor($status);
        }

        return [$status, $code, $message, $details];
    }

    protected static function defaultMessageFor(int $status): string
    {
        return match ($status) {
            400 => 'Bad request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Resource not found',
            409 => 'Conflict',
            502 => 'Bad gateway',
            503 => 'Service unavailable',
            504 => 'Gateway timeout',
            default => 'HTTP Error',
        };
    }

    protected static function defaultCodeFor(int $status): string
    {
        return match ($status) {
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHORIZED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            409 => 'CONFLICT',
            502 => 'BAD_GATEWAY',
            503 => 'SERVICE_UNAVAILABLE',
            504 => 'GATEWAY_TIMEOUT',
            default => 'HTTP_ERROR',
        };
    }
}
