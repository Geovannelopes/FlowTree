<?php

namespace App\Services\Sprint;

use App\DTOs\Sprint\SprintCreateDTO;
use App\DTOs\Sprint\SprintUpdateDTO;
use App\Events\Project\SprintChanged;
use App\Http\Resources\SprintResource;
use App\Models\Sprint;
use App\Repositories\Card\CardRepository;
use App\Repositories\Sprint\SprintRepository;
use App\Services\Project\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SprintService
{
    public function __construct(
        private SprintRepository $sprintRepository,
        private CardRepository $cardRepository,
        private ProjectService $projectService,
    ) {}

    public function list(string $projectUuid): JsonResponse
    {
        $project = $this->projectService->find($projectUuid);

        return response()->json(['data' => SprintResource::collection($this->sprintRepository->listForProject($project))]);
    }

    public function create(string $projectUuid, SprintCreateDTO $dto): JsonResponse
    {
        $project = $this->projectService->find($projectUuid);
        $sprint = $this->sprintRepository->create($project, $dto);

        $this->broadcast($sprint, 'created');

        return $this->respond($sprint, 201);
    }

    public function update(string $uuid, SprintUpdateDTO $dto): JsonResponse
    {
        $sprint = $this->sprintRepository->update($this->find($uuid), $dto);

        $this->broadcast($sprint, 'updated');

        return $this->respond($sprint);
    }

    public function destroy(string $uuid): JsonResponse
    {
        $sprint = $this->find($uuid);

        DB::transaction(function () use ($sprint) {
            // Os cards voltam ao backlog (FK nullOnDelete); renumera as células do backlog.
            $this->sprintRepository->delete($sprint);
            $this->cardRepository->reindexProjectCells($sprint->project, null);
        });

        $this->broadcast($sprint, 'deleted');

        return response()->json(null, 204);
    }

    public function reorder(string $projectUuid, array $uuids): JsonResponse
    {
        $project = $this->projectService->find($projectUuid);

        DB::transaction(fn () => $this->sprintRepository->reorder($project, $uuids));

        broadcast(new SprintChanged($project->uuid, null, 'reordered'))->toOthers();

        return response()->json(['data' => SprintResource::collection($this->sprintRepository->listForProject($project))]);
    }

    public function activate(string $uuid): JsonResponse
    {
        $sprint = $this->find($uuid);

        DB::transaction(fn () => $this->sprintRepository->activate($sprint));

        $this->broadcast($sprint, 'activated');

        return $this->respond($sprint);
    }

    /**
     * Conclui a sprint. Cards abertos (coluna sem is_done) podem ficar,
     * voltar ao backlog ou ir para outra sprint do mesmo projeto.
     */
    public function complete(string $uuid, ?string $moveOpenTo): JsonResponse
    {
        $sprint = $this->find($uuid);

        $target = null; // null = ficam
        $targetSprintId = null;

        if ($moveOpenTo === 'backlog') {
            $target = 'backlog';
        } elseif ($moveOpenTo !== null && $moveOpenTo !== '') {
            $destination = $this->sprintRepository->findInProject($sprint->project, $moveOpenTo);

            if (! $destination || $destination->id === $sprint->id) {
                throw new HttpException(422, 'Sprint de destino inválida!');
            }

            $target = 'sprint';
            $targetSprintId = $destination->id;
        }

        DB::transaction(function () use ($sprint, $target, $targetSprintId) {
            if ($target !== null) {
                $this->cardRepository->moveOpenCardsToSprint($sprint, $targetSprintId);
            }

            $this->sprintRepository->complete($sprint);
        });

        $this->broadcast($sprint, 'completed');

        return $this->respond($sprint);
    }

    public function reopen(string $uuid): JsonResponse
    {
        $sprint = $this->sprintRepository->reopen($this->find($uuid));

        $this->broadcast($sprint, 'reopened');

        return $this->respond($sprint);
    }

    /** Todos os cards do backlog passam para esta sprint. */
    public function assignBacklog(string $uuid): JsonResponse
    {
        $sprint = $this->find($uuid);

        if ($sprint->status === Sprint::STATUS_COMPLETED) {
            throw new HttpException(422, 'Não é possível atribuir cards a uma sprint concluída!');
        }

        $assigned = DB::transaction(fn () => $this->cardRepository->assignBacklogToSprint($sprint));

        $this->broadcast($sprint, 'assigned');

        return response()->json([
            'data' => new SprintResource($this->sprintRepository->withCounts($sprint)),
            'assigned' => $assigned,
        ]);
    }

    public function export(string $uuid): StreamedResponse
    {
        $sprint = $this->find($uuid);
        $cards = $this->sprintRepository->cardsForExport($sprint);
        $filename = 'sprint-'.Str::slug($sprint->name).'.csv';

        return response()->streamDownload(function () use ($cards) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM: Excel abre UTF-8 corretamente
            fputcsv($out, ['uuid', 'titulo', 'coluna', 'prioridade', 'responsavel', 'prazo', 'concluido_em'], ';');

            foreach ($cards as $card) {
                fputcsv($out, [
                    $card->uuid,
                    $card->title,
                    $card->column?->name,
                    $card->priority,
                    $card->assignee?->name,
                    $card->due_date?->toDateString(),
                    $card->completed_at?->toDateTimeString(),
                ], ';');
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function find(string $uuid): Sprint
    {
        $sprint = $this->sprintRepository->findByUuid($uuid);

        if (! $sprint) {
            throw new HttpException(404, 'Sprint não encontrada!');
        }

        return $sprint;
    }

    private function broadcast(Sprint $sprint, string $action): void
    {
        broadcast(new SprintChanged($sprint->project->uuid, $sprint->uuid, $action))->toOthers();
    }

    private function respond(Sprint $sprint, int $status = 200): JsonResponse
    {
        return response()->json(['data' => new SprintResource($this->sprintRepository->withCounts($sprint))], $status);
    }
}
