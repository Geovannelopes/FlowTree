<?php

namespace App\Services\Project;

use App\DTOs\Project\ProjectCreateDTO;
use App\DTOs\Project\ProjectUpdateDTO;
use App\Http\Resources\ProjectResource;
use App\Http\Resources\SprintResource;
use App\Models\Project;
use App\Repositories\Project\ProjectRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ProjectService
{
    private const LOGO_DIRECTORY = 'project-logos';

    public function __construct(
        private ProjectRepository $projectRepository,
    ) {}

    public function list(bool $archived, ?string $search): JsonResponse
    {
        return response()->json([
            'data' => ProjectResource::collection($this->projectRepository->list($archived, $search)),
        ]);
    }

    public function show(string $uuid): JsonResponse
    {
        return $this->respond($this->find($uuid));
    }

    public function create(ProjectCreateDTO $dto): JsonResponse
    {
        $project = DB::transaction(fn () => $this->projectRepository->create($dto, Auth::user()));

        return $this->respond($project, 201);
    }

    public function update(string $uuid, ProjectUpdateDTO $dto): JsonResponse
    {
        $project = $this->find($uuid);

        DB::transaction(fn () => $this->projectRepository->update($project, $dto));

        return $this->respond($project);
    }

    public function destroy(string $uuid): JsonResponse
    {
        $project = $this->find($uuid);

        if ($project->logo) {
            Storage::disk(config('filesystems.default'))->delete($project->logo);
        }

        $this->projectRepository->delete($project);

        return response()->json(null, 204);
    }

    /**
     * Board no recorte de sprint: 'all' (padrão), 'backlog' ou uuid da sprint.
     */
    public function board(string $uuid, ?string $sprint): JsonResponse
    {
        $project = $this->find($uuid);
        $sprintId = $this->resolveSprintScope($project, $sprint);

        $this->projectRepository->loadBoard($project, $sprintId);

        $echo = match (true) {
            $sprintId === 'all' => 'all',
            $sprintId === null => 'backlog',
            default => $project->sprints->firstWhere('id', $sprintId)?->uuid,
        };

        return response()->json([
            'data' => new ProjectResource($project),
            'sprint' => $echo,
            'sprints' => SprintResource::collection($project->sprints),
        ]);
    }

    public function uploadLogo(string $uuid, UploadedFile $file): JsonResponse
    {
        $project = $this->find($uuid);
        $disk = config('filesystems.default');

        if ($project->logo) {
            Storage::disk($disk)->delete($project->logo);
        }

        $project->update(['logo' => $file->store(self::LOGO_DIRECTORY.'/'.$project->id, $disk)]);

        return $this->respond($project);
    }

    public function deleteLogo(string $uuid): JsonResponse
    {
        $project = $this->find($uuid);

        if ($project->logo) {
            Storage::disk(config('filesystems.default'))->delete($project->logo);
            $project->update(['logo' => null]);
        }

        return $this->respond($project);
    }

    /**
     * Converte o parâmetro `sprint` num recorte: 'all', null (backlog) ou id.
     * Uuid desconhecido → 404.
     */
    public function resolveSprintScope(Project $project, ?string $sprint): int|string|null
    {
        if ($sprint === null || $sprint === '' || $sprint === 'all') {
            return 'all';
        }

        if ($sprint === 'backlog') {
            return null;
        }

        $id = $project->sprints()->where('uuid', $sprint)->value('id');

        if (! $id) {
            throw new HttpException(404, 'Sprint não encontrada!');
        }

        return (int) $id;
    }

    public function find(string $uuid): Project
    {
        $project = $this->projectRepository->findByUuid($uuid);

        if (! $project) {
            throw new HttpException(404, 'Projeto não encontrado!');
        }

        return $project;
    }

    private function respond(Project $project, int $status = 200): JsonResponse
    {
        return response()->json(['data' => new ProjectResource($this->projectRepository->loadDetails($project))], $status);
    }
}
