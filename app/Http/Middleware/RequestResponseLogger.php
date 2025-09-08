<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RequestResponseLogger
{
    public function handle(Request $request, Closure $next)
    {
        // Only log API routes by default to avoid asset noise
        $logAll = (bool) env('LOG_HTTP_ALL_ROUTES', false);
        $shouldLog = $logAll || $request->is('api/*') || $request->is('up');
        if (!$shouldLog) {
            return $next($request);
        }

        $start = microtime(true);

        $reqCtx = [
            'method' => $request->getMethod(),
            'path' => '/'.$request->path(),
            'ip' => $request->ip(),
            'query' => $request->query(),
            'user_agent' => $request->userAgent(),
            'content_type' => $request->header('Content-Type'),
            'content_length' => $request->header('Content-Length'),
            'headers' => $this->sanitizeHeaders($request->headers->all()),
        ];

        // Optional request body logging (small JSON/text only)
        $logReqBody = (bool) env('LOG_HTTP_REQUEST_BODY', false);
        $maxBody = (int) env('LOG_HTTP_MAX_BODY', 2048);
        if ($logReqBody && $this->isTextual($request->header('Content-Type'))) {
            try {
                $raw = (string) $request->getContent();
                if ($raw !== '') {
                    $reqCtx['body'] = $this->truncate($raw, $maxBody);
                }
            } catch (\Throwable) {}
        }

        Log::info('http.request', $reqCtx);

        $response = $next($request);

        $durationMs = (int) round((microtime(true) - $start) * 1000);
        $resCtx = [
            'method' => $request->getMethod(),
            'path' => '/'.$request->path(),
            'status' => $response->getStatusCode(),
            'duration_ms' => $durationMs,
            'response_length' => $response->headers->get('Content-Length') ?? strlen((string) $response->getContent()),
            'content_type' => $response->headers->get('Content-Type'),
        ];

        $logResBody = (bool) env('LOG_HTTP_RESPONSE_BODY', false);
        if ($logResBody && $this->isTextual($response->headers->get('Content-Type'))) {
            try {
                $resCtx['body'] = $this->truncate((string) $response->getContent(), $maxBody);
            } catch (\Throwable) {}
        }

        Log::info('http.response', $resCtx);

        return $response;
    }

    private function sanitizeHeaders(array $headers): array
    {
        $redact = ['authorization','cookie','set-cookie','x-api-key'];
        $out = [];
        foreach ($headers as $k => $v) {
            $lk = strtolower($k);
            $out[$k] = in_array($lk, $redact, true) ? ['REDACTED'] : $v;
        }
        return $out;
    }

    private function isTextual(?string $contentType): bool
    {
        if (!$contentType) return false;
        $contentType = strtolower($contentType);
        return str_starts_with($contentType, 'application/json')
            || str_starts_with($contentType, 'text/')
            || str_contains($contentType, 'xml')
            || str_contains($contentType, 'javascript');
    }

    private function truncate(string $s, int $max): string
    {
        if (strlen($s) <= $max) return $s;
        return substr($s, 0, max(0, $max - 3)) . '...';
    }
}

