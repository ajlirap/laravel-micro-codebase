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

        // Only format API JSON 2xx responses. Skip 204 and non-JSON.
        $isApi = $request->is('api/*');
        $status = $response->getStatusCode();
        $is2xx = $status >= 200 && $status < 300;
        $isNoContent = $status === 204;

        if (!$isApi || !$is2xx || $isNoContent) {
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

        // Avoid double wrapping
        if (array_key_exists('success', $data) && (array_key_exists('data', $data) || array_key_exists('error', $data))) {
            return $response;
        }

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
}

