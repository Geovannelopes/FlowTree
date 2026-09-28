<?php

namespace App\Http\Controllers\Sprint;

use App\DTOs\Sprint\SprintCreateDTO;
use App\DTOs\Sprint\SprintUpdateDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sprint\SprintCompleteRequest;
use App\Http\Requests\Sprint\SprintCreateRequest;
use App\Http\Requests\Sprint\SprintReorderRequest;
use App\Http\Requests\Sprint\SprintUpdateRequest;
use App\Services\Sprint\SprintService;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SprintController extends Controller
{
    public function __construct(
        private SprintService $sprintService,
    ) {}

    public function index(string $projectUuid): JsonResponse
    {
        return $this->sprintService->list($projectUuid);
    }

    public function store(SprintCreateRequest $request, string $projectUuid): JsonResponse
    {
        return $this->sprintService->create($projectUuid, SprintCreateDTO::fromRequest($request->validated()));
    }

    public function update(SprintUpdateRequest $request, string $uuid): JsonResponse
    {
        return $this->sprintService->update($uuid, SprintUpdateDTO::fromRequest($request->validated()));
    }

    public function destroy(string $uuid): JsonResponse
    {
        return $this->sprintService->destroy($uuid);
    }

    public function reorder(SprintReorderRequest $request, string $projectUuid): JsonResponse
    {
        return $this->sprintService->reorder($projectUuid, $request->validated('sprint_uuids'));
    }

    public function activate(string $uuid): JsonResponse
    {
        return $this->sprintService->activate($uuid);
    }

    public function complete(SprintCompleteRequest $request, string $uuid): JsonResponse
    {
        return $this->sprintService->complete($uuid, $request->validated('move_open_to'));
    }

    public function reopen(string $uuid): JsonResponse
    {
        return $this->sprintService->reopen($uuid);
    }

    public function assignBacklog(string $uuid): JsonResponse
    {
        return $this->sprintService->assignBacklog($uuid);
    }

    public function export(string $uuid): StreamedResponse
    {
        return $this->sprintService->export($uuid);
    }
}
