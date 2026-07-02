<?php

namespace App\Azure\Storage;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class AzureBlobClient
{
    private string $account;
    private string $key;
    private string $blobEndpoint;

    public function __construct(
        ?string $connectionString = null,
        ?string $account = null,
        ?string $key = null,
    )
    {
        $parts = $this->parseConnectionString((string) $connectionString);

        $this->account = (string) ($account ?? $parts['AccountName'] ?? 'devstoreaccount1');
        $this->key = (string) ($key ?? $parts['AccountKey'] ?? $this->azuriteKey());
        $this->blobEndpoint = rtrim((string) ($parts['BlobEndpoint'] ?? "https://{$this->account}.blob.core.windows.net/"), '/');
    }

    public static function fromLaravelConfig(string $disk = 'azure'): self
    {
        $prefix = "filesystems.disks.$disk";

        return new self(
            connectionString: config("$prefix.connection_string"),
            account: config("$prefix.account"),
            key: config("$prefix.key"),
        );
    }

    public function put(string $container, string $path, string $contents, string $contentType = 'application/octet-stream'): void
    {
        $this->ensureContainer($container);
        $url = $this->blobUrl($container, $path);
        $headers = $this->signedHeaders('PUT', $url, [
            'Content-Length' => (string) strlen($contents),
            'Content-Type' => $contentType,
            'x-ms-blob-type' => 'BlockBlob',
        ]);

        $response = Http::withHeaders($headers)->withBody($contents, $contentType)->put($url);

        if (!$response->successful()) {
            throw new RuntimeException('Azure Blob put failed: '.$response->status().' '.$response->body());
        }
    }

    public function get(string $container, string $path): string
    {
        $url = $this->blobUrl($container, $path);
        $response = Http::withHeaders($this->signedHeaders('GET', $url))->get($url);

        if (!$response->successful()) {
            throw new RuntimeException('Azure Blob get failed: '.$response->status().' '.$response->body());
        }

        return $response->body();
    }

    public function delete(string $container, string $path): void
    {
        $url = $this->blobUrl($container, $path);
        $response = Http::withHeaders($this->signedHeaders('DELETE', $url))->delete($url);

        if (!$response->successful() && $response->status() !== 404) {
            throw new RuntimeException('Azure Blob delete failed: '.$response->status().' '.$response->body());
        }
    }

    public function exists(string $container, string $path): bool
    {
        $url = $this->blobUrl($container, $path);
        $response = Http::withHeaders($this->signedHeaders('HEAD', $url))->head($url);

        return $response->successful();
    }

    public function ensureContainer(string $container): void
    {
        $url = $this->blobEndpoint.'/'.rawurlencode($container).'?restype=container';
        $response = Http::withHeaders($this->signedHeaders('PUT', $url, ['Content-Length' => '0']))->put($url);

        if (!in_array($response->status(), [201, 202, 409], true)) {
            throw new RuntimeException('Azure Blob container create failed: '.$response->status().' '.$response->body());
        }
    }

    private function blobUrl(string $container, string $path): string
    {
        return $this->blobEndpoint.'/'.rawurlencode($container).'/'.str_replace('%2F', '/', rawurlencode(ltrim($path, '/')));
    }

    private function signedHeaders(string $method, string $url, array $headers = []): array
    {
        $headers = array_merge([
            'x-ms-date' => gmdate('D, d M Y H:i:s').' GMT',
            'x-ms-version' => '2023-11-03',
        ], $headers);

        $headers['Authorization'] = 'SharedKey '.$this->account.':'.$this->signature($method, $url, $headers);

        return $headers;
    }

    private function signature(string $method, string $url, array $headers): string
    {
        $parts = parse_url($url);
        $path = $parts['path'] ?? '/';
        $query = [];
        parse_str($parts['query'] ?? '', $query);
        ksort($query);

        $canonicalizedHeaders = '';
        $xms = array_change_key_case(array_filter($headers, fn ($value, $key) => str_starts_with(strtolower((string) $key), 'x-ms-'), ARRAY_FILTER_USE_BOTH), CASE_LOWER);
        ksort($xms);
        foreach ($xms as $key => $value) {
            $canonicalizedHeaders .= strtolower($key).':'.$value."\n";
        }

        $canonicalizedResource = '/'.$this->account.$path;
        foreach ($query as $key => $value) {
            $canonicalizedResource .= "\n".strtolower($key).':'.$value;
        }

        $contentLength = ($headers['Content-Length'] ?? '') === '0' ? '' : ($headers['Content-Length'] ?? '');
        $stringToSign = implode("\n", [
            strtoupper($method),
            '',
            '',
            $contentLength,
            '',
            $headers['Content-Type'] ?? '',
            '',
            '',
            '',
            '',
            '',
            '',
            $canonicalizedHeaders.$canonicalizedResource,
        ]);

        return base64_encode(hash_hmac('sha256', $stringToSign, base64_decode($this->key), true));
    }

    private function parseConnectionString(string $connectionString): array
    {
        if ($connectionString === 'UseDevelopmentStorage=true' || str_starts_with($connectionString, 'UseDevelopmentStorage=true;')) {
            return [
                'AccountName' => 'devstoreaccount1',
                'AccountKey' => $this->azuriteKey(),
                'BlobEndpoint' => $this->extractBlobEndpoint($connectionString) ?? 'http://127.0.0.1:10000/devstoreaccount1',
            ];
        }

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

    private function extractBlobEndpoint(string $connectionString): ?string
    {
        foreach (explode(';', $connectionString) as $segment) {
            if (str_starts_with($segment, 'BlobEndpoint=')) {
                return substr($segment, strlen('BlobEndpoint='));
            }
        }

        return null;
    }

    private function azuriteKey(): string
    {
        return 'Eby8vdM02xNOcqFlqUwJPLlmEtlCDXJ1OUzFT50uSRZ6IFsuFq2UVErCz4I6tq/K1SZFPTOtr/KBHBeksoGMGw==';
    }
}
