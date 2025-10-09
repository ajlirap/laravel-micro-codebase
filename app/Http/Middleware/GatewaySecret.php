<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;

class GatewaySecret
{
    public function handle(Request $request, Closure $next)
    {
        $expectedList = array_values(array_filter(
            array_map('strval', (array) config('micro.security.gateway.accepted_secrets', [])),
            fn(string $secret) => $secret !== ''
        ));

        if ($expectedList === []) {
            // Not configured: skip enforcement, but log once per process start.
            static $warned = false;
            if (!$warned) {
                try {
                    logger()->warning('Gateway secret not configured; skipping enforcement');
                } catch (\Throwable) {
                    // no-op
                }
                $warned = true;
            }

            return $next($request);
        }

        $headerNames = array_values(array_filter(
            array_map('trim', (array) config('micro.security.gateway.header_names', ['X-Internal-Secret', 'Accepted-Secret', 'X-Accepted-Secret'])),
            fn(string $name) => $name !== ''
        ));

        $provided = null;
        $usedHeader = null;
        foreach ($headerNames as $name) {
            if ($request->headers->has($name)) {
                $provided = (string) $request->headers->get($name);
                $usedHeader = $name;
                break;
            }
        }

        if ($provided === null || $provided === '') {
            try {
                logger()->warning('Gateway secret header missing', [
                    'path' => $request->path(),
                    'method' => $request->method(),
                    'ip' => $request->ip(),
                    'expected_headers' => $headerNames,
                ]);
            } catch (\Throwable) {
                // no-op
            }

            return ApiResponse::error('gateway_secret_missing', 'Unauthorized', 401);
        }

        $authorized = false;
        foreach ($expectedList as $expected) {
            if (function_exists('hash_equals')) {
                if (hash_equals($expected, $provided)) {
                    $authorized = true;
                    break;
                }
            } elseif ($expected === $provided) {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            try {
                logger()->warning('Gateway secret mismatch', [
                    'path' => $request->path(),
                    'method' => $request->method(),
                    'ip' => $request->ip(),
                    'used_header' => $usedHeader,
                    'accepted_count' => count($expectedList),
                ]);
            } catch (\Throwable) {
                // no-op
            }

            return ApiResponse::error('gateway_secret_invalid', 'Unauthorized', 401);
        }

        return $next($request);
    }
}
