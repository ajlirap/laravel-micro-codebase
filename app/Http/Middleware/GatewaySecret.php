<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class GatewaySecret
{
    public function handle(Request $request, Closure $next)
    {
        $expected = (string) config('micro.security.gateway.accepted_secret', '');
        if ($expected === '') {
            // Not configured: skip enforcement, but log once per process start.
            static $warned = false;
            if (!$warned) {
                try { logger()->warning('Gateway secret not configured; skipping enforcement'); } catch (\Throwable) {}
                $warned = true;
            }
            return $next($request);
        }

        $headerNames = (array) config('micro.security.gateway.header_names', ['Accepted-Secret','X-Accepted-Secret']);
        $provided = null; $usedHeader = null;
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
            } catch (\Throwable) {}
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Constant-time compare to avoid timing leaks
        $ok = function_exists('hash_equals') ? hash_equals($expected, $provided) : ($expected === $provided);
        if (!$ok) {
            try {
                logger()->warning('Gateway secret mismatch', [
                    'path' => $request->path(),
                    'method' => $request->method(),
                    'ip' => $request->ip(),
                    'used_header' => $usedHeader,
                ]);
            } catch (\Throwable) {}
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}

