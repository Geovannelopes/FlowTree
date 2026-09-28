<?php

namespace App\Http\Controllers\Priority;

use App\DTOs\Priority\PriorityUpdateDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Priority\PriorityUpdateRequest;
use App\Services\Priority\PriorityService;
use Illuminate\Http\JsonResponse;

class PriorityController extends Controller
{
    public function __construct(
        private PriorityService $priorityService,
    ) {}

    public function index(): JsonResponse
    {
        return $this->priorityService->list();
    }

    public function update(PriorityUpdateRequest $request, string $uuid): JsonResponse
    {
        return $this->priorityService->update($uuid, PriorityUpdateDTO::fromRequest($request->validated()));
    }
}
