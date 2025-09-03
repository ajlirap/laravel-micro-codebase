<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use OpenApi\Annotations as OA;

class MetricsController extends Controller
{
    /**
     * @OA\Get(
     *   path="/api/v1/metrics",
     *   summary="Prometheus metrics",
     *   tags={"metrics"},
     *   @OA\Response(response=200, description="OK", @OA\MediaType(mediaType="text/plain"))
     * )
     * @OA\Get(
     *   path="/api/v1/secure/metrics",
     *   summary="Prometheus metrics (secured)",
     *   tags={"metrics"},
     *   security={{"bearerAuth": {}}},
     *   @OA\Response(response=200, description="OK", @OA\MediaType(mediaType="text/plain")),
     *   @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function metrics(CollectorRegistry $registry): Response
    {
        $renderer = new RenderTextFormat();
        $metrics = $renderer->render($registry->getMetricFamilySamples());
        return response($metrics, 200)->header('Content-Type', RenderTextFormat::MIME_TYPE);
    }
}
