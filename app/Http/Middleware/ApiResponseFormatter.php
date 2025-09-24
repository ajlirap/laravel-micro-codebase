<?php

namespace App\Http\Middleware;

use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ApiResponseFormatter
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        if (!$this->shouldFormat($request, $response)) {
            return $response;
        }

        $status = $response->getStatusCode();
        if ($status === Response::HTTP_NO_CONTENT || $status === Response::HTTP_RESET_CONTENT) {
            return $response;
        }

        $extraction = $this->extractPayload($response);
        if (!$extraction['ok']) {
            return $response;
        }

        $payload = $extraction['payload'];

        if (ApiResponse::isEnveloped($payload)) {
            $this->ensureJsonHeaders($response);
            return $response;
        }

        if ($status >= 200 && $status < 300) {
            [$data, $message, $meta] = $this->normalizeSuccess($payload);
            $envelope = ApiResponse::wrapSuccess($data, $message, $meta);
        } else {
            [$message, $details, $code] = $this->normalizeError($payload);
            $envelope = ApiResponse::wrapError(
                status: $status,
                code: $code,
                message: $message,
                details: $details
            );
        }

        $this->applyEnvelope($response, $envelope);

        return $response;
    }

    private function shouldFormat(Request $request, Response $response): bool
    {
        if ($response instanceof BinaryFileResponse || $response instanceof StreamedResponse) {
            return false;
        }

        $isApi = $request->is('api/*') || $request->expectsJson();
        if (!$isApi) {
            return false;
        }

        if ($response instanceof JsonResponse) {
            return true;
        }

        $contentType = strtolower((string) $response->headers->get('Content-Type', ''));
        if ($contentType === '') {
            return false;
        }

        return str_contains($contentType, 'json');
    }

    private function extractPayload(Response $response): array
    {
        if ($response instanceof JsonResponse) {
            return ['ok' => true, 'payload' => $response->getData(true)];
        }

        $content = $response->getContent();
        if ($content === null || $content === '') {
            return ['ok' => true, 'payload' => null];
        }

        $decoded = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return ['ok' => true, 'payload' => $decoded];
        }

        return ['ok' => false, 'payload' => null];
    }

    private function normalizeSuccess(mixed $payload): array
    {
        $message = null;
        $meta = [];

        if (is_array($payload)) {
            $data = $payload;

            if (array_key_exists('message', $data) && is_string($data['message'])) {
                $message = $data['message'];
                unset($data['message']);
            }

            if (array_key_exists('meta', $data) && is_array($data['meta'])) {
                $meta = $data['meta'];
                unset($data['meta']);
            }

            if ($data === []) {
                $data = null;
            }

            return [$data, $message, $meta];
        }

        return [$payload, $message, $meta];
    }

    private function normalizeError(mixed $payload): array
    {
        $message = null;
        $details = null;
        $code = null;

        if (is_array($payload)) {
            if (isset($payload['error']) && is_array($payload['error'])) {
                $inner = $payload['error'];
                if (isset($inner['code']) && is_scalar($inner['code'])) {
                    $code = (string) $inner['code'];
                }
                if (isset($inner['message']) && is_string($inner['message'])) {
                    $message = $inner['message'];
                }
                if (array_key_exists('details', $inner)) {
                    $details = $inner['details'];
                } else {
                    $extra = array_diff_key($inner, array_flip(['code', 'message', 'details']));
                    if (!empty($extra)) {
                        $details = $extra;
                    }
                }
                return [$message, $details, $code];
            }

            $data = $payload;
            $usedKeys = [];

            if (isset($data['code']) && is_scalar($data['code'])) {
                $code = (string) $data['code'];
                $usedKeys[] = 'code';
            }

            if (isset($data['message']) && is_string($data['message'])) {
                $message = $data['message'];
                $usedKeys[] = 'message';
            }

            if ($message === null && isset($data['detail']) && is_string($data['detail'])) {
                $message = $data['detail'];
                $usedKeys[] = 'detail';
            }

            if ($message === null && isset($data['title']) && is_string($data['title'])) {
                $message = $data['title'];
                $usedKeys[] = 'title';
            }

            if ($message === null && isset($data['error']) && is_scalar($data['error'])) {
                $message = (string) $data['error'];
                $usedKeys[] = 'error';
            }

            $details = array_diff_key($data, array_flip($usedKeys));
            if ($details === []) {
                $details = null;
            }
        } elseif (is_string($payload) && $payload !== '') {
            $message = $payload;
        }

        return [$message, $details, $code];
    }

    private function applyEnvelope(Response $response, array $payload): void
    {
        if ($response instanceof JsonResponse) {
            $response->setData($payload);
            $this->ensureJsonHeaders($response);
            return;
        }

        $response->setContent($this->encode($payload));
        $this->ensureJsonHeaders($response);
        $response->headers->remove('Content-Length');
    }

    private function ensureJsonHeaders(Response $response): void
    {
        $response->headers->set('Content-Type', 'application/json');
    }

    private function encode(array $payload): string
    {
        return json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
