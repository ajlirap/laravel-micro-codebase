<?php

namespace App\Http\Middleware;

use App\Telemetry\Telemetry;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use Symfony\Component\HttpFoundation\Response;

class OpenTelemetryRequestMiddleware
{
    public function __construct(private readonly Telemetry $telemetry)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $started = microtime(true);

        if (!config('otel.enabled')) {
            return $next($request);
        }

        return $this->telemetry->trace(
            $request->method().' '.$request->path(),
            SpanKind::KIND_SERVER,
            [
                'http.method' => $request->method(),
                'url.path' => $request->path(),
                'url.full' => $request->fullUrl(),
                'user_agent.original' => (string) $request->userAgent(),
                'service.name' => config('otel.service_name'),
                'deployment.environment' => config('otel.environment'),
            ],
            function (?SpanInterface $span) use ($request, $next, $started): Response {
                $response = $next($request);

                $durationMs = (microtime(true) - $started) * 1000;
                $span?->setAttributes([
                    'http.response.status_code' => $response->getStatusCode(),
                    'duration_ms' => round($durationMs, 2),
                ]);

                Log::info('otel.http.server.request', [
                    'http.method' => $request->method(),
                    'url.path' => $request->path(),
                    'http.response.status_code' => $response->getStatusCode(),
                    'duration_ms' => round($durationMs, 2),
                    'service.name' => config('otel.service_name'),
                    'deployment.environment' => config('otel.environment'),
                ]);

                $this->telemetry->flush();

                return $response;
            }
        );
    }
}
