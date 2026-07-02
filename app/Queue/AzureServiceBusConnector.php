<?php

namespace App\Queue;

use App\Azure\ServiceBus\ServiceBusClient;
use Illuminate\Queue\Connectors\ConnectorInterface;

class AzureServiceBusConnector implements ConnectorInterface
{
    public function connect(array $config): AzureServiceBusQueue
    {
        return new AzureServiceBusQueue(
            client: new ServiceBusClient(
                connectionString: (string) ($config['connection_string'] ?? ''),
                sasTtl: (int) ($config['sas_ttl'] ?? 3600),
            ),
            defaultQueue: (string) ($config['queue'] ?? 'default'),
            receiveTimeout: (int) ($config['receive_timeout'] ?? 30),
        );
    }
}
