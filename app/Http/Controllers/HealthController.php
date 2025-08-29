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
     */
    public function health(): JsonResponse
    {
        Log::debug('Health check starting');
        $db = false;
        try {
            DB::connection()->getPdo();
            $db = true;
        } catch (\Throwable $e) {
            $db = false;
            Log::error('DB check failed', ['error' => $e->getMessage()]);
        }

        $status = [
            'status' => 'ok',
            'checks' => [
                'db' => $db ? 'ok' : 'fail',
            ],
        ];
        $code = $db ? 200 : 503;
        return response()->json($status, $code);
    }
}
