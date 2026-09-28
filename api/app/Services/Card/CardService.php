<?php

namespace App\Services\Card;

use App\DTOs\Card\CardCreateDTO;
use App\DTOs\Card\CardFilterDTO;
use App\DTOs\Card\CardMoveDTO;
use App\DTOs\Card\CardUpdateDTO;
use App\Events\Project\CardChanged;
use App\Http\Resources\CardResource;
use App\Models\BoardColumn;
use App\Models\Card;
use App\Models\Project;
use App\Models\Sprint;
use App\Models\User;
use App\Notifications\CardAssigned;
use App\Repositories\Card\CardRepository;
use App\Repositories\User\UserRepository;
use App\Services\Project\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class CardService
{
    public function __construct(
        private CardRepository $cardRepository,
        private UserRepository $userRepository,
        private ProjectService $projectService,
    ) {}

    public function list(string $projectUuid, CardFilterDTO $filter): JsonResponse
    {
        $project = $this->projectService->find($projectUuid);
        $sprintId = $this->projectService->resolveSprintScope($project, $filter->sprint);

        return response()->json(['data' => CardResource::collection($this->cardRepository->list($project, $filter, $sprintId))]);
    }

    public function show(string $uuid): JsonResponse
    {
        return response()->json(['data' => new CardResource($this->cardRepository->loadDetails($this->find($uuid)))]);
    }

    public function create(string $projectUuid, CardCreateDTO $dto): JsonResponse
    {
        $project = $this->projectService->find($projectUuid);
        $column = $this->column($project, $dto->boardColumnUuid);
        $sprintId = $dto->sprintUuid ? $this->sprint($project, $dto->sprintUuid)->id : null;
        $assigneeId = $dto->assigneeUuid ? $this->user($dto->assigneeUuid)->id : null;
        $actor = Auth::user();

        $card = DB::transaction(function () use ($project, $column, $sprintId, $assigneeId, $dto, $actor) {
            $this->assertWipLimit($column);

            $card = $this->cardRepository->create($project, [
                ...$dto->toArray(),
                'board_column_id' => $column->id,
                'sprint_id' => $sprintId,
                'assignee_id' => $assigneeId,
                'created_by' => $actor->id,
                'position' => $this->cardRepository->nextPosition($column->id, $sprintId),
                ...$this->stageAttributes($project, $column, null),
            ]);

            if ($dto->flagUuids !== null) {
                $this->cardRepository->syncFlags($card, $dto->flagUuids);
            }

            $this->cardRepository->recordMovement($card, null, $column->id, $actor->id);

            return $card;
        });

        $this->notifyAssignee($card, $assigneeId, $actor);
        $this->broadcast($card, 'created');

        return response()->json(['data' => new CardResource($this->cardRepository->loadForBoard($card))], 201);
    }

    public function update(string $uuid, CardUpdateDTO $dto): JsonResponse
    {
        $card = $this->find($uuid);
        $project = $card->project;
        $actor = Auth::user();
        $oldAssigneeId = $card->assignee_id;

        $attributes = $dto->toArray();

        if ($dto->assigneeUuid !== false) {
            $attributes['assignee_id'] = $dto->assigneeUuid ? $this->user($dto->assigneeUuid)->id : null;
        }

        $targetColumn = $dto->boardColumnUuid !== false ? $this->column($project, $dto->boardColumnUuid) : null;
        $targetSprintId = $dto->sprintUuid !== false
            ? ($dto->sprintUuid ? $this->sprint($project, $dto->sprintUuid)->id : null)
            : false;

        DB::transaction(function () use ($card, $project, $attributes, $targetColumn, $targetSprintId, $dto, $actor) {
            $columnChanged = $targetColumn && $targetColumn->id !== $card->board_column_id;
            $sprintChanged = $targetSprintId !== false && $targetSprintId !== $card->sprint_id;

            // Troca de célula via update: vai para o fim da célula de destino.
            if ($columnChanged || $sprintChanged) {
                $newColumn = $targetColumn ?? $card->column;
                $newSprintId = $targetSprintId !== false ? $targetSprintId : $card->sprint_id;

                if ($columnChanged) {
                    $this->assertWipLimit($newColumn);
                }

                $this->cardRepository->closeGap($card->board_column_id, $card->sprint_id, $card->position);
                $attributes['board_column_id'] = $newColumn->id;
                $attributes['sprint_id'] = $newSprintId;
                $attributes['position'] = $this->cardRepository->nextPosition($newColumn->id, $newSprintId);

                if ($columnChanged) {
                    $attributes = [...$attributes, ...$this->stageAttributes($project, $newColumn, $card)];
                    $this->cardRepository->recordMovement($card, $card->board_column_id, $newColumn->id, $actor->id);
                }
            }

            $this->cardRepository->update($card, $attributes);

            if ($dto->flagUuids !== null) {
                $this->cardRepository->syncFlags($card, $dto->flagUuids);
            }
        });

        if (array_key_exists('assignee_id', $attributes) && $attributes['assignee_id'] !== $oldAssigneeId) {
            $this->notifyAssignee($card, $attributes['assignee_id'], $actor);
        }

        $this->broadcast($card, 'updated');

        return response()->json(['data' => new CardResource($this->cardRepository->loadForBoard($card->refresh()))]);
    }

    /**
     * Move o card para (coluna, sprint, posição). Posições são por célula
     * (coluna × sprint). O WIP conta a coluna inteira e só importa quando a
     * coluna muda.
     */
    public function move(string $uuid, CardMoveDTO $dto): JsonResponse
    {
        $card = $this->find($uuid);
        $project = $card->project;
        $targetColumn = $this->column($project, $dto->boardColumnUuid);
        $targetSprintId = $dto->sprintUuid === false
            ? $card->sprint_id
            : ($dto->sprintUuid ? $this->sprint($project, $dto->sprintUuid)->id : null);
        $actor = Auth::user();

        DB::transaction(function () use ($card, $project, $targetColumn, $targetSprintId, $dto, $actor) {
            $sourceColumnId = (int) $card->board_column_id;
            $sourceSprintId = $card->sprint_id;
            $oldPosition = (int) $card->position;
            $newPosition = $dto->position;

            $sameCell = $sourceColumnId === $targetColumn->id && $sourceSprintId === $targetSprintId;

            if ($sameCell) {
                $this->cardRepository->shiftWithinCell($sourceColumnId, $sourceSprintId, $oldPosition, $newPosition);
            } else {
                if ($sourceColumnId !== $targetColumn->id) {
                    $this->assertWipLimit($targetColumn);
                }

                $this->cardRepository->closeGap($sourceColumnId, $sourceSprintId, $oldPosition);
                $this->cardRepository->openGap($targetColumn->id, $targetSprintId, $newPosition);
            }

            $attributes = [
                'board_column_id' => $targetColumn->id,
                'sprint_id' => $targetSprintId,
                'position' => $newPosition,
            ];

            if ($sourceColumnId !== $targetColumn->id) {
                $attributes = [...$attributes, ...$this->stageAttributes($project, $targetColumn, $card)];
                $this->cardRepository->recordMovement($card, $sourceColumnId, $targetColumn->id, $actor->id);
            }

            $this->cardRepository->update($card, $attributes);
        });

        $this->broadcast($card, 'moved');

        return response()->json(['data' => new CardResource($this->cardRepository->loadForBoard($card->refresh()))]);
    }

    public function destroy(string $uuid): JsonResponse
    {
        $card = $this->find($uuid);

        DB::transaction(function () use ($card) {
            $this->cardRepository->delete($card);
            $this->cardRepository->closeGap($card->board_column_id, $card->sprint_id, $card->position);
        });

        $this->broadcast($card, 'deleted');

        return response()->json(null, 204);
    }

    public function find(string $uuid): Card
    {
        $card = $this->cardRepository->findByUuid($uuid);

        if (! $card) {
            throw new HttpException(404, 'Card não encontrado!');
        }

        return $card;
    }

    /**
     * Atributos de estágio ao entrar numa coluna: `completed_at` segue o
     * `is_done`; `started_at` é preenchido na primeira entrada numa coluna
     * que não é a primeira do projeto.
     */
    private function stageAttributes(Project $project, BoardColumn $column, ?Card $card): array
    {
        $attributes = ['completed_at' => $column->is_done ? ($card?->completed_at ?? now()) : null];

        $isFirst = $this->cardRepository->firstColumnId($project) === $column->id;

        if (! $isFirst && ($card === null || $card->started_at === null)) {
            $attributes['started_at'] = now();
        }

        return $attributes;
    }

    private function assertWipLimit(BoardColumn $column): void
    {
        if ($column->wip_limit === null) {
            return;
        }

        if ($this->cardRepository->countInColumn($column->id) >= $column->wip_limit) {
            throw ValidationException::withMessages([
                'board_column_uuid' => ["A coluna \"{$column->name}\" atingiu o limite de {$column->wip_limit} cards (WIP)."],
            ]);
        }
    }

    private function notifyAssignee(Card $card, ?int $assigneeId, User $actor): void
    {
        if (! $assigneeId || $assigneeId === $actor->id) {
            return;
        }

        $card->loadMissing('project');
        $this->userRepository->findById($assigneeId)?->notify(new CardAssigned($card, $actor));
    }

    private function broadcast(Card $card, string $action): void
    {
        broadcast(new CardChanged($card->project->uuid, $card->uuid, $action))->toOthers();
    }

    private function column(Project $project, string $uuid): BoardColumn
    {
        $column = $this->cardRepository->columnInProject($project, $uuid);

        if (! $column) {
            throw ValidationException::withMessages(['board_column_uuid' => ['A coluna não pertence a este projeto.']]);
        }

        return $column;
    }

    private function sprint(Project $project, string $uuid): Sprint
    {
        $sprint = $this->cardRepository->sprintInProject($project, $uuid);

        if (! $sprint) {
            throw ValidationException::withMessages(['sprint_uuid' => ['A sprint não pertence a este projeto.']]);
        }

        return $sprint;
    }

    private function user(string $uuid): User
    {
        $user = $this->userRepository->findByUuid($uuid);

        if (! $user) {
            throw ValidationException::withMessages(['assignee_uuid' => ['Responsável inválido.']]);
        }

        return $user;
    }
}
