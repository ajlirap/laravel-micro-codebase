<?php

namespace App\Azure\ServiceBus;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class ServiceBusClient
{
    private string $endpoint;
    private string $keyName;
    private string $key;

    public function __construct(
        string $connectionString,
        private readonly int $sasTtl = 3600,
    )
    {
        $parts = $this->parseConnectionString($connectionString);

        $this->endpoint = $this->normalizeEndpoint(rtrim($parts['Endpoint'] ?? '', '/'));
        $this->keyName = $parts['SharedAccessKeyName'] ?? '';
        $this->key = $parts['SharedAccessKey'] ?? '';

        if ($this->endpoint === '' || $this->keyName === '' || $this->key === '') {
            throw new RuntimeException('Azure Service Bus connection string is incomplete.');
        }
    }

    public static function fromLaravelConfig(string $connection = 'azure-servicebus'): self
    {
        $prefix = "queue.connections.$connection";

        return new self(
            connectionString: (string) config("$prefix.connection_string", ''),
            sasTtl: (int) config("$prefix.sas_ttl", 3600),
        );
    }

    public function send(string $queue, string $payload, array $headers = []): void
    {
        $url = $this->entityUrl($queue).'/messages';
        $response = Http::withHeaders($this->headers($url, $headers))
            ->withBody($payload, 'application/json')
            ->post($url);

        if (!$response->successful()) {
            throw new RuntimeException('Azure Service Bus send failed: '.$response->status().' '.$response->body());
        }
    }

    public function receiveAndDelete(string $queue, int $timeout = 30): ?array
    {
        $url = $this->entityUrl($queue).'/messages/head?timeout='.$timeout;
        $response = Http::withHeaders($this->headers($url))->delete($url);

        if ($response->status() === 204 || $response->body() === '') {
            return null;
        }

        if (!$response->successful()) {
            throw new RuntimeException('Azure Service Bus receive failed: '.$response->status().' '.$response->body());
        }

        return [
            'body' => $response->body(),
            'headers' => $response->headers(),
        ];
    }

    private function entityUrl(string $entity): string
    {
        return $this->endpoint.'/'.trim($entity, '/');
    }

    private function headers(string $resourceUri, array $headers = []): array
    {
        return array_merge([
            'Authorization' => $this->sasToken($resourceUri),
            'Content-Type' => 'application/json',
            'BrokerProperties' => json_encode([
                'MessageId' => (string) Str::uuid(),
                'ContentType' => 'application/json',
            ]),
        ], $headers);
    }

    private function sasToken(string $resourceUri): string
    {
        $resource = strtolower(rawurlencode($resourceUri));
        $expiry = time() + $this->sasTtl;
        $toSign = $resource."\n".$expiry;
        $signature = rawurlencode(base64_encode(hash_hmac('sha256', $toSign, $this->key, true)));

        return sprintf(
            'SharedAccessSignature sr=%s&sig=%s&se=%d&skn=%s',
            $resource,
            $signature,
            $expiry,
            rawurlencode($this->keyName)
        );
    }

    private function parseConnectionString(string $connectionString): array
    {
        $parts = [];
        foreach (explode(';', $connectionString) as $segment) {
            if (!str_contains($segment, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $segment, 2);
            $parts[$key] = $value;
        }

        return $parts;
    }

    private function normalizeEndpoint(string $endpoint): string
    {
        if (str_starts_with($endpoint, 'sb://')) {
            return 'https://'.substr($endpoint, strlen('sb://'));
        }

        return $endpoint;
    }
}
