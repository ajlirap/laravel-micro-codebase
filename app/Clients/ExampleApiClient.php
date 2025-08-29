<?php

namespace App\Clients;

use App\Support\Http\ResilientHttpClient;
use Illuminate\Http\Client\Response;

class ExampleApiClient
{
    private ResilientHttpClient $client;

    public function __construct()
    {
        $this->client = new ResilientHttpClient('example');
    }

    public function getWidget(string $id): array
    {
        $response = $this->client->request('GET', "/widgets/{$id}");
        $response->throw();
        return $response->json();
    }

    public function createWidget(array $payload): array
    {
        $response = $this->client->request('POST', '/widgets', [
            'json' => $payload,
        ]);
        $response->throw();
        return $response->json();
    }
}

