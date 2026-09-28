<?php

namespace App\Http\Controllers\BoardColumn;

use App\DTOs\BoardColumn\BoardColumnCreateDTO;
use App\DTOs\BoardColumn\BoardColumnUpdateDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\BoardColumn\BoardColumnCreateRequest;
use App\Http\Requests\BoardColumn\BoardColumnReorderRequest;
use App\Http\Requests\BoardColumn\BoardColumnUpdateRequest;
use App\Services\BoardColumn\BoardColumnService;
use Illuminate\Http\JsonResponse;

class BoardColumnController extends Controller
{
    public function __construct(
        private BoardColumnService $boardColumnService,
    ) {}

    public function index(string $projectUuid): JsonResponse
    {
        return $this->boardColumnService->list($projectUuid);
    }

    public function store(BoardColumnCreateRequest $request, string $projectUuid): JsonResponse
    {
        return $this->boardColumnService->create($projectUuid, BoardColumnCreateDTO::fromRequest($request->validated()));
    }

    public function update(BoardColumnUpdateRequest $request, string $uuid): JsonResponse
    {
        return $this->boardColumnService->update($uuid, BoardColumnUpdateDTO::fromRequest($request->validated()));
    }

    public function reorder(BoardColumnReorderRequest $request, string $projectUuid): JsonResponse
    {
        return $this->boardColumnService->reorder($projectUuid, $request->validated('column_uuids'));
    }

    public function destroy(string $uuid): JsonResponse
    {
        return $this->boardColumnService->destroy($uuid);
    }
}
