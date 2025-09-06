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
            try {
                logger()->warning('JWT auth missing bearer token', [
                    'path' => $request->path(),
                    'method' => $request->method(),
                    'ip' => $request->ip(),
                    'auth_header_present' => $request->headers->has('Authorization'),
                    // Do NOT log full header/token for security; headers can still confirm presence/casing issues
                ]);
            } catch (\Throwable) {}
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
            try { logger()->debug('JWT header parsed', [ 'alg' => $alg, 'kid' => $kid, 'accepted_algs' => $accepted ]); } catch (\Throwable) {}
            if ($alg && !in_array($alg, (array) $accepted, true)) {
                try { logger()->warning('JWT algorithm not accepted', [ 'alg' => $alg, 'accepted_algs' => $accepted ]); } catch (\Throwable) {}
                throw new \RuntimeException('Algorithm not accepted');
            }

            $keys = $this->jwks->getKeys();
            $jwks = JWK::parseKeySet(['keys' => $keys]);
            try { logger()->debug('JWKS loaded', [ 'keys_count' => count($keys), 'kids' => array_values(array_filter(array_map(static fn($k) => $k['kid'] ?? null, $keys))) ]); } catch (\Throwable) {}

            try {
                $decoded = JWT::decode($token, $jwks);
            } catch (\Throwable $e) {
                try { logger()->warning('JWT signature/claims decode failed', [ 'error' => $e->getMessage(), 'alg' => $alg, 'kid' => $kid ]); } catch (\Throwable) {}
                throw $e;
            }

            $claims = (array) $decoded;
            try {
                // Log core claims (avoid full token dump)
                $audLog = isset($claims['aud']) ? (is_array($claims['aud']) ? $claims['aud'] : [(string) $claims['aud']]) : [];
                logger()->debug('JWT decoded claims (summary)', [
                    'iss' => $claims['iss'] ?? null,
                    'sub' => $claims['sub'] ?? null,
                    'aud' => $audLog,
                    'exp' => $claims['exp'] ?? null,
                    'nbf' => $claims['nbf'] ?? null,
                    'iat' => $claims['iat'] ?? null,
                    'scp' => $claims['scp'] ?? null,
                    'scope' => $claims['scope'] ?? null,
                    'roles' => $claims['roles'] ?? null,
                ]);
            } catch (\Throwable) {}
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
                if (!$ok) {
                    try { logger()->warning('JWT issuer mismatch', [ 'claim_iss' => $claimIss, 'expected_iss' => $issList ]); } catch (\Throwable) {}
                    throw new \RuntimeException('Issuer mismatch');
                }
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
                if (!$ok) {
                    try { logger()->warning('JWT audience mismatch', [ 'claim_aud' => $claimAud, 'expected_aud' => $audList ]); } catch (\Throwable) {}
                    throw new \RuntimeException('Audience mismatch');
                }
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
                        try { logger()->warning('JWT missing required scope', [ 'required' => $required, 'token_scopes' => $tokenScopes ]); } catch (\Throwable) {}
                        return response()->json(['message' => 'Forbidden'], 403);
                    }
                }
            }

            // Attach claims for logging
            app()->instance('jwt_claims', $claims);
        } catch (\Throwable $e) {
            // Avoid leaking details to clients; log for server diagnostics.
            try {
                logger()->warning('JWT auth failed', [ 'error' => $e->getMessage(), 'path' => $request->path(), 'method' => $request->method() ]);
            } catch (\Throwable) {}
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        return $next($request);
    }
}
