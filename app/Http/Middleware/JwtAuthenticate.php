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

            // Config
            $accepted = config('micro.security.jwt.accepted_algs', ['RS256']);
            $issCfg = (string) config('micro.security.jwt.expected_iss');
            $audCfg = (string) config('micro.security.jwt.expected_aud');

            // Enforce allowed algorithms from config
            $alg = $header['alg'] ?? null;
            if ($alg && !in_array($alg, (array) $accepted, true)) {
                throw new \RuntimeException('Algorithm not accepted');
            }

            $keys = $this->jwks->getKeys();
            $jwks = JWK::parseKeySet(['keys' => $keys]);

            $decoded = JWT::decode($token, $jwks);

            $claims = (array) $decoded;
            // Expected issuers: support comma-separated list and tolerate trailing slash differences (AAD B2C)
            $issList = array_filter(array_map('trim', explode(',', $issCfg)));
            if (!empty($issList)) {
                $claimIss = (string) ($claims['iss'] ?? '');
                $claimIssNorm = rtrim($claimIss, '/');
                $ok = false;
                foreach ($issList as $iss) {
                    $issNorm = rtrim($iss, '/');
                    if ($claimIss === $iss || $claimIss === $iss.'/' || $claimIssNorm === $issNorm) {
                        $ok = true; break;
                    }
                }
                if (!$ok) throw new \RuntimeException('Issuer mismatch');
            }

            // Expected audiences: support comma-separated list and both string/array claim forms
            $audList = array_filter(array_map('trim', explode(',', $audCfg)));
            if (!empty($audList)) {
                $claimAudRaw = $claims['aud'] ?? [];
                $claimAud = is_array($claimAudRaw) ? $claimAudRaw : [(string) $claimAudRaw];
                $ok = false;
                foreach ($audList as $expectedAud) {
                    if (in_array($expectedAud, $claimAud, true)) { $ok = true; break; }
                }
                if (!$ok) throw new \RuntimeException('Audience mismatch');
            }

            // Scope/role check
            // Treat empty/blank middleware params as "no scope required"
            $requiredScopes = array_values(array_filter(array_map(static fn($s) => trim((string) $s), $scopes), static fn($s) => $s !== ''));
            if (!empty($requiredScopes)) {
                // Collect token scopes from common JWT claims
                $tokenScopes = [];
                // OAuth2 "scope" (space-delimited string)
                if (isset($claims['scope'])) {
                    $tokenScopes = array_merge($tokenScopes, preg_split('/\s+/', (string) $claims['scope'], -1, PREG_SPLIT_NO_EMPTY));
                }
                // Azure AD v2 "scp" can be string (space-delimited) or array
                if (isset($claims['scp'])) {
                    $scp = $claims['scp'];
                    if (is_array($scp)) {
                        $tokenScopes = array_merge($tokenScopes, $scp);
                    } else {
                        $tokenScopes = array_merge($tokenScopes, preg_split('/\s+/', (string) $scp, -1, PREG_SPLIT_NO_EMPTY));
                    }
                }
                // Optional: treat app roles as scopes
                if (isset($claims['roles'])) {
                    $roles = $claims['roles'];
                    $tokenScopes = array_merge($tokenScopes, is_array($roles) ? $roles : [ (string) $roles ]);
                }
                // Normalize uniques
                $tokenScopes = array_values(array_unique(array_map('trim', $tokenScopes)));

                foreach ($requiredScopes as $required) {
                    if (!in_array($required, $tokenScopes, true)) {
                        return response()->json(['message' => 'Forbidden'], 403);
                    }
                }
            }

            // Attach claims for logging
            app()->instance('jwt_claims', $claims);
        } catch (\Throwable $e) {
            // Avoid leaking details to clients; log for server diagnostics.
            try { logger()->warning('JWT auth failed: '.$e->getMessage()); } catch (\Throwable) {}
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
