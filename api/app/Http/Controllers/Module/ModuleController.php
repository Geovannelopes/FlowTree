<?php

namespace App\Http\Controllers\Module;

use App\Http\Controllers\Controller;
use App\Services\Module\ModuleService;
use Illuminate\Http\JsonResponse;

class ModuleController extends Controller
{
    public function __construct(
        private ModuleService $moduleService,
    ) {}

    public function index(): JsonResponse
    {
        return $this->moduleService->list();
    }
}
