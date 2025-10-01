<?php

namespace App\Clients;

use App\Support\ApiResponse;
use App\Support\Http\ResilientHttpClient;

class IdentityApiClient
{
    private ResilientHttpClient $client;

    public function __construct()
    {
        $this->client = new ResilientHttpClient('identity');
    }

    public function getExistingEmail(string $email): array
    {
        $response = $this->client->request('GET', "/api/admin/users/by-email?email={$email}", [
            'headers' => [
                'X-Internal-Secret' => 'x-secret-bff',
            ]
        ]);

        $response->throw();
        return $response->json();
    }
}
