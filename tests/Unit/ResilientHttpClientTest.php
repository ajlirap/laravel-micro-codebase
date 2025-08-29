<?php

namespace Tests\Unit;

use App\Support\Http\ResilientHttpClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ResilientHttpClientTest extends TestCase
{
    public function test_retries_and_succeeds(): void
    {
        Http::fakeSequence()
            ->pushStatus(500)
            ->push(['ok' => true], 200);

        $client = new ResilientHttpClient('example', ['base_url' => 'http://example.test', 'retries' => 2, 'timeout_ms' => 200]);

        $resp = $client->request('GET', '/ping');
        $this->assertTrue($resp->successful());
    }
}
