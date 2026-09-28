<?php

namespace App\Http\Controllers\Project;

use App\DTOs\Project\ProjectCreateDTO;
use App\DTOs\Project\ProjectUpdateDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Project\ProjectCreateRequest;
use App\Http\Requests\Project\ProjectLogoRequest;
use App\Http\Requests\Project\ProjectUpdateRequest;
use App\Services\Project\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    public function __construct(
        private ProjectService $projectService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->projectService->list($request->boolean('archived'), $request->query('search'));
    }

    public function show(string $uuid): JsonResponse
    {
        return $this->projectService->show($uuid);
    }

    public function store(ProjectCreateRequest $request): JsonResponse
    {
        return $this->projectService->create(ProjectCreateDTO::fromRequest($request->validated()));
    }

    public function update(ProjectUpdateRequest $request, string $uuid): JsonResponse
    {
        return $this->projectService->update($uuid, ProjectUpdateDTO::fromRequest($request->validated()));
    }

    public function destroy(string $uuid): JsonResponse
    {
        return $this->projectService->destroy($uuid);
    }

    public function board(Request $request, string $uuid): JsonResponse
    {
        return $this->projectService->board($uuid, $request->query('sprint'));
    }

    public function uploadLogo(ProjectLogoRequest $request, string $uuid): JsonResponse
    {
        return $this->projectService->uploadLogo($uuid, $request->file('logo'));
    }

    public function deleteLogo(string $uuid): JsonResponse
    {
        return $this->projectService->deleteLogo($uuid);
    }
}
