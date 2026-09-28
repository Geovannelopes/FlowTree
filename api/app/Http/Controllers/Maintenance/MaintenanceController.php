<?php

namespace App\Http\Controllers\Maintenance;

use App\DTOs\Maintenance\MaintenanceResetDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Maintenance\MaintenanceResetRequest;
use App\Services\Maintenance\MaintenanceService;
use Illuminate\Http\JsonResponse;

class MaintenanceController extends Controller
{
    public function __construct(
        private MaintenanceService $maintenanceService,
    ) {}

    public function reset(MaintenanceResetRequest $request): JsonResponse
    {
        return $this->maintenanceService->reset($request->user(), MaintenanceResetDTO::fromRequest($request->validated()));
    }
}
