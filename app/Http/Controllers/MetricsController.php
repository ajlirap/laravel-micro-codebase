<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;

class MetricsController extends Controller
{
    public function metrics(CollectorRegistry $registry): Response
    {
        $renderer = new RenderTextFormat();
        $metrics = $renderer->render($registry->getMetricFamilySamples());
        return response($metrics, 200)->header('Content-Type', RenderTextFormat::MIME_TYPE);
    }
}

