<?php

namespace App\Http\Controllers\Metric;

use App\Http\Controllers\Controller;
use App\Services\Metric\MetricService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MetricController extends Controller
{
    public function __construct(
        private MetricService $metricService,
    ) {}

    public function project(Request $request, string $projectUuid): JsonResponse
    {
        return $this->metricService->forProject($projectUuid, $request->query('sprint'));
    }
}
