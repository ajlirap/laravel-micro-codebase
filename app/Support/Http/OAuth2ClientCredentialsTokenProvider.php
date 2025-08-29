<?php

namespace App\Support\Http;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class OAuth2ClientCredentialsTokenProvider
{
    public function __construct(
        private readonly ?string $tokenUrl = null,
        private readonly ?string $clientId = null,
        private readonly ?string $clientSecret = null,
        private readonly ?string $audience = null,
        private readonly ?int $cacheTtlSeconds = null,
    ) {}

    public function getToken(array $scopes = []): string
    {
        $tokenUrl = $this->tokenUrl ?? config('micro.http.oauth2.token_url');
        $clientId = $this->clientId ?? config('micro.http.oauth2.client_id');
        $clientSecret = $this->clientSecret ?? config('micro.http.oauth2.client_secret');
        $audience = $this->audience ?? config('micro.http.oauth2.audience');
        $ttl = $this->cacheTtlSeconds ?? (int) config('micro.http.oauth2.cache_ttl_seconds', 300);

        $scopeKey = implode(' ', Arr::wrap($scopes));
        $cacheKey = 'oauth2:cc:'.md5($tokenUrl.'|'.$clientId.'|'.$scopeKey.'|'.$audience);

        return Cache::remember($cacheKey, $ttl, function () use ($tokenUrl, $clientId, $clientSecret, $audience, $scopes) {
            $payload = [
                'grant_type' => 'client_credentials',
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
            ];
            if (!empty($scopes)) {
                $payload['scope'] = implode(' ', $scopes);
            }
            if ($audience) {
                $payload['audience'] = $audience;
            }

            $resp = Http::asForm()->post($tokenUrl, $payload);
            $resp->throw();
            $data = $resp->json();
            return $data['access_token'] ?? '';
        });
    }
}

