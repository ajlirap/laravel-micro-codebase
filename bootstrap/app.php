<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withCommands([
        \App\Console\Commands\PublishExampleEvent::class,
        \App\Console\Commands\DocsAssertCovered::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\CorrelationId::class);
        $middleware->alias(['auth.jwt' => \App\Http\Middleware\JwtAuthenticate::class]);
        // Apply formatter to API group only
        $middleware->appendToGroup('api', \App\Http\Middleware\ApiResponseFormatter::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // JSON error shaping
        $exceptions->render(function (\Throwable $e, $request) {
            $wantsJson = $request->expectsJson() || $request->is('api/*');
            if (!$wantsJson) {
                return null; // use default
            }

            $traceId = app()->bound('correlation_id') ? app('correlation_id') : (app()->bound('request_id') ? app('request_id') : null);

            $status = 500;
            $code = 'INTERNAL_ERROR';
            $message = 'An unexpected error occurred';
            $details = null;

            if ($e instanceof \Illuminate\Validation\ValidationException) {
                $status = 422; $code = 'VALIDATION_ERROR';
                $message = 'Validation failed';
                $details = $e->errors();
            } elseif ($e instanceof \Illuminate\Auth\AuthenticationException) {
                $status = 401; $code = 'UNAUTHORIZED'; $message = 'Unauthorized';
            } elseif ($e instanceof \Illuminate\Auth\Access\AuthorizationException) {
                $status = 403; $code = 'FORBIDDEN'; $message = 'Forbidden';
            } elseif ($e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException) {
                $status = 404; $code = 'NOT_FOUND'; $message = 'Resource not found';
            } elseif ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface) {
                $status = $e->getStatusCode();
                $message = $e->getMessage() ?: match ($status) {
                    400 => 'Bad request',
                    401 => 'Unauthorized',
                    403 => 'Forbidden',
                    404 => 'Resource not found',
                    409 => 'Conflict',
                    default => 'HTTP Error',
                };
                $code = match ($status) {
                    400 => 'BAD_REQUEST',
                    401 => 'UNAUTHORIZED',
                    403 => 'FORBIDDEN',
                    404 => 'NOT_FOUND',
                    409 => 'CONFLICT',
                    default => 'HTTP_ERROR',
                };
            }

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
        });
    })->create();
