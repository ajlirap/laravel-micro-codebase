<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;

/**
 * @OA\Info(title="Service API", version="1.0.0")
 */
class HealthController extends Controller
{
    /**
     * @OA\Get(
     *   path="/api/v1/health",
     *   summary="Liveness/Readiness",
     *   @OA\Response(response=200, description="OK")
     * )
     * @OA\Get(
     *   path="/api/v1/secure/health",
     *   summary="Liveness/Readiness (secured)",
     *   security={{"bearerAuth": {}}},
     *   @OA\Response(response=200, description="OK"),
     *   @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function health(): JsonResponse
    {
        Log::debug('Health check starting');
        $db = false;
        $queue = false;
        try {
            DB::connection()->getPdo();
            $db = true;
        } catch (\Throwable $e) {
            $db = false;
            Log::error('DB check failed', ['error' => $e->getMessage()]);
        }

        // RabbitMQ connectivity check (non-intrusive): open + close a connection/channel
        try {
            $cfg = config('queue.connections.rabbitmq');
            if (is_array($cfg) && !empty($cfg['hosts'])) {
                $h = $cfg['hosts'][0];
                $host = $h['host'] ?? 'localhost';
                $port = (int) ($h['port'] ?? 5672);
                $user = $h['user'] ?? 'guest';
                $pass = $h['password'] ?? 'guest';
                $vhost = $h['vhost'] ?? '/';
                $heartbeat = (int) (data_get($cfg, 'options.heartbeat', 30));
                $conn = new AMQPStreamConnection(
                    $host,
                    $port,
                    $user,
                    $pass,
                    $vhost,
                    false,
                    'AMQPLAIN',
                    null,
                    'en_US',
                    2.0, // connection timeout (s)
                    2.0, // read/write timeout (s)
                    null,
                    false,
                    $heartbeat
                );
                $ch = $conn->channel();
                $ch->close();
                $conn->close();
                $queue = true;
            } else {
                // If not configured, mark as false and log
                $queue = false;
                Log::warning('RabbitMQ check skipped: queue.connections.rabbitmq missing or hosts empty');
            }
        } catch (\Throwable $e) {
            $queue = false;
            Log::error('RabbitMQ check failed', ['error' => $e->getMessage()]);
        }

        $allOk = $db && $queue;
        $status = [
            'status' => $allOk ? 'ok' : 'degraded',
            'checks' => [
                'db' => $db ? 'ok' : 'fail',
                'queue' => $queue ? 'ok' : 'fail',
            ],
        ];
        $code = $allOk ? 200 : 503;
        return response()->json($status, $code);
    }
}
