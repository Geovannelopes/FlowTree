<?php

namespace App\Http\Controllers\Flag;

use App\DTOs\Flag\FlagCreateDTO;
use App\DTOs\Flag\FlagUpdateDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Flag\FlagCreateRequest;
use App\Http\Requests\Flag\FlagUpdateRequest;
use App\Services\Flag\FlagService;
use Illuminate\Http\JsonResponse;

class FlagController extends Controller
{
    public function __construct(
        private FlagService $flagService,
    ) {}

    public function index(): JsonResponse
    {
        return $this->flagService->list();
    }

    public function store(FlagCreateRequest $request): JsonResponse
    {
        return $this->flagService->create(FlagCreateDTO::fromRequest($request->validated()));
    }

    public function update(FlagUpdateRequest $request, string $uuid): JsonResponse
    {
        return $this->flagService->update($uuid, FlagUpdateDTO::fromRequest($request->validated()));
    }

    public function destroy(string $uuid): JsonResponse
    {
        return $this->flagService->destroy($uuid);
    }
}
