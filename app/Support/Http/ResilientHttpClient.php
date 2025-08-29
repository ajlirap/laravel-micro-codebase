<?php

namespace App\Support\Http;

use Closure;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ResilientHttpClient
{
    private string $serviceKey;
    private array $config;
    private ?OAuth2ClientCredentialsTokenProvider $tokenProvider;

    public function __construct(string $serviceKey, array $overrides = [], ?OAuth2ClientCredentialsTokenProvider $tokenProvider = null)
    {
        $this->serviceKey = $serviceKey;
        $defaults = config('micro.http.defaults');
        $target = config("micro.http.targets.$serviceKey", []);
        $this->config = array_merge($defaults, $target, $overrides);
        $this->tokenProvider = $tokenProvider;
    }

    public function request(string $method, string $uri, array $options = []): Response
    {
        $baseUrl = rtrim((string)($this->config['base_url'] ?? ''), '/');
        $url = $baseUrl.'/'.ltrim($uri, '/');

        $corrHeader = config('micro.tracing.correlation_id_header', 'X-Correlation-Id');
        $reqHeader = config('micro.tracing.request_id_header', 'X-Request-Id');
        $cid = app()->bound('correlation_id') ? app('correlation_id') : Str::uuid()->toString();
        $rid = app()->bound('request_id') ? app('request_id') : Str::uuid()->toString();

        $pending = Http::withHeaders([
            $corrHeader => $cid,
            $reqHeader => $rid,
        ]);

        // mTLS
        $mtls = config('micro.http.mtls');
        if (!empty($mtls['cert_path']) && !empty($mtls['key_path'])) {
            $pending = $pending->withOptions([
                'cert' => [$mtls['cert_path'], $mtls['key_passphrase'] ?? null],
                'ssl_key' => [$mtls['key_path'], $mtls['key_passphrase'] ?? null],
                'verify' => $mtls['ca_path'] ?? true,
            ]);
        }

        // Timeouts
        $timeout = (int) ($this->config['timeout_ms'] ?? 2000);
        $pending = $pending->timeout($timeout / 1000);

        // OAuth2
        $scopes = Arr::wrap($this->config['scopes'] ?? []);
        if (!empty($scopes)) {
            $token = ($this->tokenProvider ?? new OAuth2ClientCredentialsTokenProvider())->getToken($scopes);
            if ($token) {
                $pending = $pending->withToken($token);
            }
        }

        // Retry with jitter
        $retries = (int) ($this->config['retries'] ?? 2);
        $base = (int) ($this->config['retry_base_ms'] ?? 200);
        $max = (int) ($this->config['retry_max_ms'] ?? 1500);
        $useJitter = (bool) ($this->config['jitter'] ?? true);

        $retryDecider = function ($exception, $request, $response) {
            if ($exception) return true;
            if (!$response) return true;
            return $response->serverError() || $response->status() == 429;
        };

        $pending = $pending->retry(
            $retries,
            function ($attempt) use ($base, $max, $useJitter) {
                $delay = min($max, $base * (2 ** ($attempt - 1)));
                if ($useJitter) {
                    $delay = random_int((int) ($delay * 0.5), $delay);
                }
                return $delay; // milliseconds
            },
            $retryDecider,
            false
        );

        // Circuit breaker
        $breaker = new CircuitBreaker('http:'.$this->serviceKey, (int) ($this->config['breaker_failure_threshold'] ?? 5), (int) ($this->config['breaker_reset_ms'] ?? 30000));

        $execute = function () use ($pending, $method, $url, $options): Response {
            /** @var PendingRequest $pending */
            return $pending->send($method, $url, $options);
        };

        $fallback = function () {
            return response()->noContent(503);
        };

        return $breaker->call($execute, $fallback);
    }
}
