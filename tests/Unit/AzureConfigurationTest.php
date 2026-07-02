<?php

namespace Tests\Unit;

use App\Jobs\ExampleEventConsumer;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AzureConfigurationTest extends TestCase
{
    public function test_azure_service_bus_queue_connection_is_configured(): void
    {
        config([
            'queue.default' => 'azure-servicebus',
            'queue.connections.azure-servicebus.connection_string' => 'Endpoint=sb://example.servicebus.windows.net;SharedAccessKeyName=RootManageSharedAccessKey;SharedAccessKey=abc;',
            'queue.connections.azure-servicebus.queue' => 'app.queue',
        ]);

        Queue::fake();

        ExampleEventConsumer::dispatch(['id' => '1'], ['Idempotency-Key' => '1']);

        Queue::assertPushed(ExampleEventConsumer::class);
    }

    public function test_azure_blob_disk_is_configured(): void
    {
        $disk = config('filesystems.disks.azure');

        $this->assertSame('azure', $disk['driver']);
        $this->assertSame('app', $disk['container']);
    }

    public function test_azure_storage_driver_can_be_resolved(): void
    {
        config([
            'filesystems.disks.azure.connection_string' => 'UseDevelopmentStorage=true;BlobEndpoint=http://127.0.0.1:10000/devstoreaccount1;',
            'filesystems.disks.azure.container' => 'app',
        ]);

        $this->assertNotNull(Storage::disk('azure'));
    }

    public function test_azure_service_bus_queue_can_push_raw_payload(): void
    {
        Http::fake([
            'https://example.servicebus.windows.net/*' => Http::response('', 201),
        ]);

        config([
            'queue.default' => 'azure-servicebus',
            'queue.connections.azure-servicebus.connection_string' => 'Endpoint=sb://example.servicebus.windows.net;SharedAccessKeyName=RootManageSharedAccessKey;SharedAccessKey=YWJj;',
            'queue.connections.azure-servicebus.queue' => 'app.queue',
        ]);

        $id = Queue::connection('azure-servicebus')->pushRaw(json_encode([
            'uuid' => 'test-message',
            'displayName' => 'test',
            'job' => 'test',
            'data' => [],
        ]));

        $this->assertSame('test-message', $id);

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && str_contains($request->url(), 'https://example.servicebus.windows.net/app.queue/messages'));
    }
}
