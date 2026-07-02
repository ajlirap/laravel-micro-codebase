<?php

namespace App\Telemetry;

use Illuminate\Support\Facades\Log;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\SDK\Trace\TracerProviderFactory;
use OpenTelemetry\SDK\Trace\TracerProviderInterface;
use Throwable;

class Telemetry
{
    private TracerProviderInterface $provider;
    private TracerInterface $tracer;

    public function __construct()
    {
        $this->configureEnvironment();

        $this->provider = (new TracerProviderFactory())->create();
        $this->tracer = $this->provider->getTracer(config('otel.instrumentation_name', 'laravel-micro-codebase'));
    }

    public function trace(string $name, int $kind, array $attributes, callable $callback): mixed
    {
        if (!config('otel.enabled')) {
            return $callback(null);
        }

        $span = $this->startSpan($name, $kind, $attributes);
        $scope = $span->activate();

        try {
            $result = $callback($span);
            $span->setStatus(StatusCode::STATUS_OK);

            return $result;
        } catch (Throwable $e) {
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());

            throw $e;
        } finally {
            $scope->detach();
            $span->end();
        }
    }

    public function startSpan(string $name, int $kind = SpanKind::KIND_INTERNAL, array $attributes = []): SpanInterface
    {
        return $this->tracer
            ->spanBuilder($name)
            ->setSpanKind($kind)
            ->setAttributes($attributes)
            ->startSpan();
    }

    public function flush(): void
    {
        try {
            $this->provider->forceFlush();
        } catch (Throwable $e) {
            Log::debug('OpenTelemetry force flush failed', ['error' => $e->getMessage()]);
        }
    }

    public function shutdown(): void
    {
        try {
            $this->provider->shutdown();
        } catch (Throwable $e) {
            Log::debug('OpenTelemetry shutdown failed', ['error' => $e->getMessage()]);
        }
    }

    private function configureEnvironment(): void
    {
        $attributes = [
            'service.name='.config('otel.service_name'),
            'deployment.environment='.config('otel.environment'),
        ];

        $this->setEnv('OTEL_SERVICE_NAME', (string) config('otel.service_name'));
        $this->setEnv('OTEL_RESOURCE_ATTRIBUTES', implode(',', $attributes));
        $this->setEnv('OTEL_TRACES_EXPORTER', (string) config('otel.traces_exporter'));
        $this->setEnv('OTEL_EXPORTER_OTLP_PROTOCOL', (string) config('otel.protocol'));
        $this->setEnv('OTEL_EXPORTER_OTLP_ENDPOINT', rtrim((string) config('otel.endpoint'), '/'));
        $this->setEnv('OTEL_PROPAGATORS', (string) config('otel.propagators'));
        $this->setEnv('OTEL_PHP_TRACES_PROCESSOR', (string) config('otel.processor'));
    }

    private function setEnv(string $key, string $value): void
    {
        if ($value === '') {
            return;
        }

        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
        putenv($key.'='.$value);
    }
}
