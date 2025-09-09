<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiResponseFormatter
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // Only format API JSON responses (both success and failure). Skip 204 and non-JSON.
        $isApi = $request->is('api/*');
        $status = $response->getStatusCode();
        $is2xx = $status >= 200 && $status < 300;
        $isNoContent = $status === 204;

        if (!$isApi || $isNoContent) {
            return $response;
        }

        // Ensure we only wrap JSON responses
        $contentType = $response->headers->get('Content-Type');
        $isJson = $response instanceof JsonResponse || ($contentType && str_contains(strtolower($contentType), 'application/json'));
        if (!$isJson) {
            return $response;
        }

        // Extract data as array
        $data = null;
        if ($response instanceof JsonResponse) {
            $data = $response->getData(true);
        } else {
            $decoded = json_decode((string) $response->getContent(), true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $data = $decoded;
            }
        }

        if (!is_array($data)) {
            return $response; // don't change if unknown structure
        }

        // Avoid double wrapping if already standardized
        if (array_key_exists('success', $data) && (array_key_exists('data', $data) || array_key_exists('error', $data))) {
            return $response;
        }

        // Success path: wrap as { success: true, data }
        if ($is2xx) {
            $wrapped = [
                'success' => true,
                'data' => $data,
            ];
            // Keep message if controller set it
            if (array_key_exists('message', $data) && is_string($data['message'])) {
                $wrapped['message'] = $data['message'];
            }

            if ($response instanceof JsonResponse) {
                $response->setData($wrapped);
            } else {
                $response->setContent(json_encode($wrapped));
                $response->headers->set('Content-Type', 'application/json');
            }
            return $response;
        }

        // Error path: wrap as { success: false, error: { code, message, details? }, traceId? }
        $code = match ($status) {
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHORIZED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            409 => 'CONFLICT',
            422 => 'VALIDATION_ERROR',
            default => 'HTTP_ERROR',
        };

        $message = is_string($data['message'] ?? null) ? $data['message'] : match ($status) {
            400 => 'Bad request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Resource not found',
            409 => 'Conflict',
            422 => 'Validation failed',
            default => 'HTTP Error',
        };

        $details = null;
        if (isset($data['errors']) && is_array($data['errors'])) {
            $details = $data['errors'];
        }

        $wrapped = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
        if (!is_null($details)) {
            $wrapped['error']['details'] = $details;
        }
        if (app()->bound('correlation_id')) {
            $wrapped['traceId'] = app('correlation_id');
        }

        if ($response instanceof JsonResponse) {
            $response->setData($wrapped);
        } else {
            $response->setContent(json_encode($wrapped));
            $response->headers->set('Content-Type', 'application/json');
        }

        return $response;
    }
}
