<?php

namespace App\Services\Module;

use App\Http\Resources\ModuleResource;
use App\Repositories\Module\ModuleRepository;
use Illuminate\Http\JsonResponse;

class ModuleService
{
    public function __construct(
        private ModuleRepository $moduleRepository,
    ) {}

    public function list(): JsonResponse
    {
        return response()->json(['data' => ModuleResource::collection($this->moduleRepository->all())]);
    }
}
