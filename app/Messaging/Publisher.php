<?php

namespace App\Messaging;

use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class Publisher
{
    public function __construct(private readonly array $config = []) {}

    public function publish(string $routingKey, array $message, array $headers = []): void
    {
        $conf = $this->config + config('queue.connections.rabbitmq');
        $host = $conf['hosts'][0] ?? [];
        $exchange = $conf['options']['exchange'] ?? 'app.exchange';

        $connection = new AMQPStreamConnection(
            $host['host'] ?? 'rabbitmq',
            $host['port'] ?? 5672,
            $host['user'] ?? 'guest',
            $host['password'] ?? 'guest',
            $host['vhost'] ?? '/'
        );

        $channel = $connection->channel();
        $channel->exchange_declare($exchange, $conf['options']['exchange_type'] ?? 'topic', false, true, false);

        $props = [
            'content_type' => 'application/json',
            'delivery_mode' => AMQPMessage::DELIVERY_MODE_PERSISTENT,
            'application_headers' => $headers,
        ];

        $msg = new AMQPMessage(json_encode($message), $props);
        $channel->basic_publish($msg, $exchange, $routingKey);
        $channel->close();
        $connection->close();
    }
}

