<?php

namespace App\Providers;

use App\Azure\Storage\AzureBlobClient;
use App\Filesystem\AzureBlobAdapter;
use App\Telemetry\Telemetry;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\ServiceProvider;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\InMemory;
use App\FeatureFlags\FeatureFlagClient;
use App\FeatureFlags\ArrayFeatureFlags;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use App\Support\ApiResponse;
use App\Queue\AzureServiceBusConnector;
use Illuminate\Filesystem\FilesystemAdapter;
use League\Flysystem\Filesystem;
use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\SpanKind;
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

        $this->app->singleton(AzureBlobClient::class, function () {
            return AzureBlobClient::fromLaravelConfig();
        });

        $this->app->singleton(Telemetry::class, fn () => new Telemetry());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Queue::addConnector('azure-servicebus', function () {
            return new AzureServiceBusConnector();
        });

        Storage::extend('azure', function ($app, $config) {
            $adapter = new AzureBlobAdapter(
                new AzureBlobClient(
                    connectionString: $config['connection_string'] ?? null,
                    account: $config['account'] ?? null,
                    key: $config['key'] ?? null,
                ),
                $config['container'] ?? 'app'
            );

            return new FilesystemAdapter(new Filesystem($adapter), $adapter, $config);
        });

        if (config('otel.enabled') && config('otel.database_spans')) {
            DB::listen(function (QueryExecuted $query) {
                app(Telemetry::class)->trace('db.query', SpanKind::KIND_CLIENT, [
                    'db.system' => config('database.default'),
                    'db.operation.name' => $this->operationName($query->sql),
                    'db.query.text' => $query->sql,
                    'db.response.duration_ms' => $query->time,
                ], fn () => null);
            });
        }

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
            if (config('otel.enabled')) {
                Globals::propagator()->inject($headers);
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

        Response::macro('error', function (string $code, string $message, int $status = 400, array $details = [], array $context = []) {
            return ApiResponse::error($code, $message, $status, $details, $context);
        });
    }

    private function operationName(string $sql): string
    {
        $operation = strtok(ltrim($sql), " \t\n\r\0\x0B");

        return $operation ? strtolower($operation) : 'unknown';
    }
}
