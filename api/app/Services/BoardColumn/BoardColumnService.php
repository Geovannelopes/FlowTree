<?php

namespace App\Services\BoardColumn;

use App\DTOs\BoardColumn\BoardColumnCreateDTO;
use App\DTOs\BoardColumn\BoardColumnUpdateDTO;
use App\Events\Project\ColumnChanged;
use App\Http\Resources\BoardColumnResource;
use App\Models\BoardColumn;
use App\Repositories\BoardColumn\BoardColumnRepository;
use App\Services\Project\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class BoardColumnService
{
    public function __construct(
        private BoardColumnRepository $boardColumnRepository,
        private ProjectService $projectService,
    ) {}

    public function list(string $projectUuid): JsonResponse
    {
        $project = $this->projectService->find($projectUuid);

        return response()->json(['data' => BoardColumnResource::collection($this->boardColumnRepository->listForProject($project))]);
    }

    public function create(string $projectUuid, BoardColumnCreateDTO $dto): JsonResponse
    {
        $project = $this->projectService->find($projectUuid);
        $column = $this->boardColumnRepository->create($project, $dto->toArray());

        broadcast(new ColumnChanged($project->uuid, $column->uuid, 'created'))->toOthers();

        return response()->json(['data' => new BoardColumnResource($column)], 201);
    }

    public function update(string $uuid, BoardColumnUpdateDTO $dto): JsonResponse
    {
        $column = $this->find($uuid);
        $this->boardColumnRepository->update($column, $dto);

        broadcast(new ColumnChanged($column->project->uuid, $column->uuid, 'updated'))->toOthers();

        return response()->json(['data' => new BoardColumnResource($column->loadCount('cards'))]);
    }

    public function reorder(string $projectUuid, array $uuids): JsonResponse
    {
        $project = $this->projectService->find($projectUuid);

        DB::transaction(fn () => $this->boardColumnRepository->reorder($project, $uuids));

        broadcast(new ColumnChanged($project->uuid, null, 'reordered'))->toOthers();

        return response()->json(['data' => BoardColumnResource::collection($this->boardColumnRepository->listForProject($project))]);
    }

    public function destroy(string $uuid): JsonResponse
    {
        $column = $this->find($uuid);

        if ($this->boardColumnRepository->count($column->project) <= 1) {
            throw new HttpException(422, 'O projeto precisa ter pelo menos uma coluna!');
        }

        DB::transaction(fn () => $this->boardColumnRepository->delete($column));

        broadcast(new ColumnChanged($column->project->uuid, $column->uuid, 'deleted'))->toOthers();

        return response()->json(null, 204);
    }

    public function find(string $uuid): BoardColumn
    {
        $column = $this->boardColumnRepository->findByUuid($uuid);

        if (! $column) {
            throw new HttpException(404, 'Coluna não encontrada!');
        }

        return $column;
    }
}
