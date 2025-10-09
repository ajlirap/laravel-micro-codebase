<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\InMemory;
use App\FeatureFlags\FeatureFlagClient;
use App\FeatureFlags\ArrayFeatureFlags;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\RequestInterface;

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
        // Propagate tracing and correlation headers on all outbound Http:: calls
        Http::globalRequestMiddleware(function (RequestInterface $request) {
            $tcfg = config('identity.tracing', []);
            $corrHeader = $tcfg['correlation_id_header'] ?? 'X-Correlation-Id';
            $reqHeader  = $tcfg['request_id_header'] ?? 'X-Request-Id';
            $tpHeader   = $tcfg['traceparent_header'] ?? 'traceparent';
            $tsHeader   = $tcfg['tracestate_header'] ?? 'tracestate';

            $corr = app()->bound('correlation_id') ? (string) app('correlation_id') : null;
            $req  = app()->bound('request_id') ? (string) app('request_id') : null;
            $traceId = app()->bound('trace_id') ? (string) app('trace_id') : bin2hex(random_bytes(16));
            $spanId  = app()->bound('span_id') ? (string) app('span_id') : bin2hex(random_bytes(8));
            $traceparent = sprintf('00-%s-%s-01', $traceId, $spanId);

            $headers = [$tpHeader => $traceparent];
            if ($corr) {
                $headers[$corrHeader] = $corr;
            }
            if ($req) {
                $headers[$reqHeader] = $req;
            }
            if (app()->bound('tracestate')) {
                $headers[$tsHeader] = (string) app('tracestate');
            }

            foreach ($headers as $key => $value) {
                if ($value !== null && $value !== '') {
                    $request = $request->withHeader($key, $value);
                }
            }
            return $request;
        });

        // Convenient response macros for controllers/services
        Response::macro('success', function ($data = null, ?string $message = null, int $status = 200) {
            $payload = ['success' => true, 'data' => $data];
            if ($message) {
                $payload['message'] = $message;
            }
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
            if (!is_null($details)) {
                $body['error']['details'] = $details;
            }
            if (app()->bound('correlation_id')) {
                $body['traceId'] = app('correlation_id');
            }
            return Response::json($body, $status);
        });
    }
}
