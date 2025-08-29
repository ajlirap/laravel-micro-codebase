<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\InMemory;
use App\FeatureFlags\FeatureFlagClient;
use App\FeatureFlags\ArrayFeatureFlags;
use Illuminate\Support\Facades\Response;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Prometheus in-memory registry (swap in Redis/APCu in prod if desired)
        $this->app->singleton(CollectorRegistry::class, function () {
            return new CollectorRegistry(new InMemory(), false);
        });

        // Feature flags
        $this->app->bind(FeatureFlagClient::class, function () {
            $driver = config('micro.feature_flags.driver', 'array');
            return match ($driver) {
                default => new ArrayFeatureFlags(),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Convenient response macros for controllers/services
        Response::macro('success', function ($data = null, ?string $message = null, int $status = 200) {
            $payload = ['success' => true, 'data' => $data];
            if ($message) { $payload['message'] = $message; }
            return Response::json($payload, $status);
        });

        Response::macro('error', function (string $code, string $message, array $details = null, int $status = 400) {
            $body = [
                'success' => false,
                'error' => [
                    'code' => $code,
                    'message' => $message,
                ],
            ];
            if (!is_null($details)) { $body['error']['details'] = $details; }
            if (app()->bound('correlation_id')) { $body['traceId'] = app('correlation_id'); }
            return Response::json($body, $status);
        });
    }
}
