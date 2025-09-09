<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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

        $traceId = app()->bound('correlation_id') ? app('correlation_id') : (app()->bound('request_id') ? app('request_id') : null);

        [$status, $code, $message, $details] = self::map($e);

        $body = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
        if (!is_null($details)) {
            $body['error']['details'] = $details;
        }
        if ($traceId) {
            $body['traceId'] = $traceId;
        }

        return response()->json($body, $status);
    }

    protected static function map(Throwable $e): array
    {
        $status = 500;
        $code = 'INTERNAL_ERROR';
        $message = 'An unexpected error occurred';
        $details = null;

        if ($e instanceof ValidationException) {
            $status = 422; $code = 'VALIDATION_ERROR';
            $message = 'Validation failed';
            $details = $e->errors();
        } elseif ($e instanceof AuthenticationException) {
            $status = 401; $code = 'UNAUTHORIZED'; $message = 'Unauthorized';
        } elseif ($e instanceof AuthorizationException) {
            $status = 403; $code = 'FORBIDDEN'; $message = 'Forbidden';
        } elseif ($e instanceof ModelNotFoundException) {
            $status = 404; $code = 'NOT_FOUND'; $message = 'Resource not found';
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
            default => 'HTTP_ERROR',
        };
    }
}

