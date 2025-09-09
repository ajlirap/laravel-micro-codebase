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
    ->withProviders([
        \App\Providers\AppServiceProvider::class,
        \App\Providers\RepositoryServiceProvider::class,
    ])
    ->withCommands([
        \App\Console\Commands\PublishExampleEvent::class,
        \App\Console\Commands\DocsAssertCovered::class,
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\CorrelationId::class);
        // Log API requests/responses (see LOG_HTTP_* env toggles)
        $middleware->append(\App\Http\Middleware\RequestResponseLogger::class);
        $middleware->alias(['auth.jwt' => \App\Http\Middleware\JwtAuthenticate::class]);
        $middleware->alias(['gateway.secret' => \App\Http\Middleware\GatewaySecret::class]);
        // Apply formatter to API group only
        $middleware->appendToGroup('api', \App\Http\Middleware\ApiResponseFormatter::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Centralized JSON error shaping for API routes
        $exceptions->render(new \App\Exceptions\ApiExceptionRenderer());
    })->create();
