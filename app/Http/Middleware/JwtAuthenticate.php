<?php

namespace App\Http\Middleware;

use App\Support\Auth\JwksProvider;
use Closure;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;

class JwtAuthenticate
{
    public function __construct(private readonly JwksProvider $jwks) {}

    public function handle(Request $request, Closure $next, ...$scopes)
    {
        $auth = $request->bearerToken();
        if (!$auth) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        try {
            $token = $auth;
            $parts = explode('.', $token);
            if (count($parts) < 2) throw new \RuntimeException('Invalid token');
            $header = json_decode(JWT::urlsafeB64Decode($parts[0]), true);
            $kid = $header['kid'] ?? null;

            $accepted = config('micro.security.jwt.accepted_algs', ['RS256']);
            $iss = config('micro.security.jwt.expected_iss');
            $aud = config('micro.security.jwt.expected_aud');

            $keys = $this->jwks->getKeys();
            $jwks = JWK::parseKeySet(['keys' => $keys]);

            $decoded = JWT::decode($token, $jwks);

            $claims = (array) $decoded;
            if ($iss && ($claims['iss'] ?? null) !== $iss) throw new \RuntimeException('Issuer mismatch');
            if ($aud && !in_array($aud, (array) ($claims['aud'] ?? []), true)) throw new \RuntimeException('Audience mismatch');

            // Scope/role check
            if (!empty($scopes)) {
                $tokenScopes = array_merge(
                    explode(' ', (string) ($claims['scope'] ?? '')),
                    (array) ($claims['scp'] ?? [])
                );
                foreach ($scopes as $required) {
                    if (!in_array($required, $tokenScopes, true)) {
                        return response()->json(['message' => 'Forbidden'], 403);
                    }
                }
            }

            // Attach claims for logging
            app()->instance('jwt_claims', $claims);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}

