<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Response;

class ApiResponse
{
    /**
     * Build a standardized success response.
     */
    public static function success(mixed $data = null, ?string $message = null, int $status = 200): JsonResponse
    {
        $payload = [
            'success' => true,
            'data' => $data,
        ];
        if ($message) {
            $payload['message'] = $message;
        }

        return Response::json($payload, $status);
    }

    /**
     * Build a standardized error response.
     */
    public static function error(string $code, string $message, ?array $details = null, int $status = 400): JsonResponse
    {
        $body = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
        if (!is_null($details)) {
            $body['error']['details'] = $details;
        }
        if (app()->bound('correlation_id')) {
            $body['traceId'] = app('correlation_id');
        }

        return Response::json($body, $status);
    }
}

