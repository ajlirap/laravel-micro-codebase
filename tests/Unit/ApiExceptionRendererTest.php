<?php

namespace Tests\Unit;

use App\Exceptions\ApiExceptionRenderer;
use GuzzleHttp\Psr7\Response as PsrResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response as HttpClientResponse;
use Illuminate\Http\Request;
use Tests\TestCase;

class ApiExceptionRendererTest extends TestCase
{
    public function test_connection_exception_returns_service_unavailable(): void
    {
        config(['app.debug' => false]);

        $request = Request::create('/api/identity', 'GET');
        $exception = new ConnectionException('cURL error 7');

        $response = ApiExceptionRenderer::render($exception, $request);

        $this->assertNotNull($response);
        $this->assertSame(503, $response->status());

        $payload = $response->getData(true);

        $this->assertFalse($payload['success']);
        $this->assertSame('SERVICE_UNAVAILABLE', $payload['error']['code']);
        $this->assertSame('Upstream service temporarily unavailable', $payload['error']['message']);
        $this->assertArrayNotHasKey('details', $payload['error']);
    }

    public function test_request_exception_uses_upstream_payload_when_available(): void
    {
        config(['app.debug' => false]);

        $request = Request::create('/api/identity', 'GET');

        $upstreamBody = json_encode([
            'error' => [
                'code' => 'UPSTREAM_VALIDATION',
                'message' => 'Email already exists',
                'details' => ['email' => ['Already taken']],
            ],
        ]);

        $psrResponse = new PsrResponse(409, ['Content-Type' => 'application/json'], $upstreamBody);
        $httpResponse = new HttpClientResponse($psrResponse);

        $exception = new RequestException($httpResponse);

        $response = ApiExceptionRenderer::render($exception, $request);

        $this->assertNotNull($response);
        $this->assertSame(409, $response->status());

        $payload = $response->getData(true);

        $this->assertFalse($payload['success']);
        $this->assertSame('UPSTREAM_VALIDATION', $payload['error']['code']);
        $this->assertSame('Email already exists', $payload['error']['message']);
        $this->assertSame(['email' => ['Already taken']], $payload['error']['details']);
    }
}
