<?php

return [
    'tracing' => [
        'propagate_w3c' => env('TRACE_PROPAGATE_W3C', true),
        'request_id_header' => env('TRACE_HEADER_REQUEST_ID', 'X-Request-Id'),
        'correlation_id_header' => env('TRACE_HEADER_CORRELATION_ID', 'X-Correlation-Id'),
    ],

    'http' => [
        'defaults' => [
            'timeout_ms' => env('HTTP_DEFAULT_TIMEOUT_MS', 2000),
            'retries' => env('HTTP_DEFAULT_RETRIES', 2),
            'retry_base_ms' => env('HTTP_DEFAULT_RETRY_BASE_MS', 200),
            'retry_max_ms' => env('HTTP_DEFAULT_RETRY_MAX_MS', 1500),
            'jitter' => env('HTTP_DEFAULT_JITTER', true),
            'breaker_failure_threshold' => env('HTTP_DEFAULT_BREAKER_FAILURE_THRESHOLD', 5),
            'breaker_reset_ms' => env('HTTP_DEFAULT_BREAKER_RESET_MS', 30000),
            'hedge' => [
                'enabled' => env('HTTP_DEFAULT_HEDGE_ENABLED', false),
                'delay_ms' => env('HTTP_DEFAULT_HEDGE_DELAY_MS', 100),
            ],
        ],
        'mtls' => [
            'ca_path' => env('MTLS_CA_PATH'),
            'cert_path' => env('MTLS_CERT_PATH'),
            'key_path' => env('MTLS_KEY_PATH'),
            'key_passphrase' => env('MTLS_KEY_PASSPHRASE'),
        ],
        'oauth2' => [
            'token_url' => env('OAUTH2_TOKEN_URL'),
            'client_id' => env('OAUTH2_CLIENT_ID'),
            'client_secret' => env('OAUTH2_CLIENT_SECRET'),
            'scopes' => env('OAUTH2_SCOPES'),
            'audience' => env('OAUTH2_AUDIENCE'),
            'cache_ttl_seconds' => env('OAUTH2_CACHE_TTL_SECONDS', 300),
        ],
        'targets' => [
            'example' => [
                'base_url' => env('EXAMPLE_API_BASE_URL'),
                'timeout_ms' => env('EXAMPLE_API_TIMEOUT_MS', 1500),
                'retries' => env('EXAMPLE_API_RETRIES', 2),
                'scopes' => env('EXAMPLE_API_SCOPES'),
            ],
        ],
    ],

    'security' => [
        'jwt' => [
            'jwks_url' => env('AUTH_JWKS_URL'),
            'expected_iss' => env('AUTH_EXPECTED_ISS'),
            'expected_aud' => env('AUTH_EXPECTED_AUD'),
            'accepted_algs' => array_filter(array_map('trim', explode(',', env('AUTH_ACCEPTED_ALGS', 'RS256')))),
            // Acceptable clock skew (in seconds) for exp/nbf/iat validation
            'leeway_seconds' => env('AUTH_LEEWAY_SECONDS', 0),
            // Prefer reporting "expired" based on unverified exp claim when signature fails
            // Useful in dev to surface that a token is old even if the key is wrong.
            'prefer_exp_reason_on_failure' => env('AUTH_PREFER_EXP_REASON', false),
            'cache_ttl_seconds' => env('AUTH_CACHE_TTL_SECONDS', 3600),
        ],
        'gateway' => [
            // Shared secret(s) expected from BFF/Gateway/other trusted services.
            // If empty, the middleware will be a no-op.
            // Supports multiple comma-separated values via ACCEPTED_SECRETS.
            'accepted_secrets' => (function () {
                $raw = env('ACCEPTED_SECRETS', '');
                $list = array_map('trim', explode(',', (string) $raw));
                return array_values(array_filter($list, fn($v) => $v !== ''));
            })(),
            // Standard header name preferred: 'X-Internal-Secret'.
            // You may set multiple candidates comma-separated; first match wins.
            // Configured via ACCEPTED_SECRET_HEADERS (comma-separated).
            'header_names' => (function () {
                $raw = env('ACCEPTED_SECRET_HEADERS', '');
                $default = 'X-Internal-Secret,Accepted-Secret,X-Accepted-Secret';
                $value = $raw !== '' ? $raw : $default;
                $list = array_map('trim', explode(',', (string) $value));
                return array_values(array_filter($list, fn($v) => $v !== ''));
            })(),
        ],
        'field_encryption' => [
            // Key rotation example: keys are stored with versions, e.g., key_v1, key_v2
            // The active version is used for encryption; all versions are tried for decryption.
            'active_version' => env('PII_KEY_ACTIVE_VERSION', 'v1'),
            'keys' => [
                // 'v1' => env('PII_KEY_V1'),
                // 'v2' => env('PII_KEY_V2'),
            ],
        ],
    ],

    'metrics' => [
        'namespace' => env('METRICS_NAMESPACE', 'app'),
        'enable_default' => env('METRICS_ENABLE_DEFAULT', true),
    ],

    'feature_flags' => [
        'driver' => env('FEATURE_FLAGS_DRIVER', 'array'),
        'drivers' => [
            'array' => [
                'flags' => [
                    // 'example.canary' => true,
                ],
            ],
            'launchdarkly' => [
                'sdk_key' => env('FEATURE_FLAGS_LAUNCHDARKLY_SDK_KEY'),
            ],
            'unleash' => [
                'url' => env('FEATURE_FLAGS_UNLEASH_URL'),
                'token' => env('FEATURE_FLAGS_UNLEASH_TOKEN'),
                'app_name' => env('APP_NAME', 'laravel-service'),
            ],
        ],
    ],
];
