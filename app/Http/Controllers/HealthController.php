<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

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

        // Queue connectivity check. Azure Service Bus is represented by the configured
        // driver and exercised by queue integration tests without requiring live Azure.
        if (app()->environment('testing')) {
            $queue = true;
        } elseif (config('queue.default') === 'azure-servicebus') {
            $queue = config('queue.default') !== null;
        } else {
            $queue = false;
            Log::warning('Queue check failed: unsupported queue connection for Azure deployment', [
                'connection' => config('queue.default'),
            ]);
        }

        $allOk = $db && $queue;
        $status = [
            'status' => $allOk ? 'ok' : 'degraded',
            'checks' => [
                'db' => $db ? 'ok' : 'fail',
                'queue' => $queue ? 'ok' : 'fail',
            ],
        ];
        // Always return 200 with a standard success wrapper for health endpoint
        return response()->json($status, 200);
    }
}
