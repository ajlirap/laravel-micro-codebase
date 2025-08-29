<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CorrelationId
{
    public function handle(Request $request, Closure $next): Response
    {
        $config = config('micro.tracing');
        $corrHeader = $config['correlation_id_header'] ?? 'X-Correlation-Id';
        $reqHeader = $config['request_id_header'] ?? 'X-Request-Id';

        $correlationId = $request->headers->get($corrHeader) ?: Str::uuid()->toString();
        $requestId = $request->headers->get($reqHeader) ?: Str::uuid()->toString();

        // Stash in request container for downstream usage
        app()->instance('correlation_id', $correlationId);
        app()->instance('request_id', $requestId);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set($corrHeader, $correlationId);
        $response->headers->set($reqHeader, $requestId);

        return $response;
    }
}

