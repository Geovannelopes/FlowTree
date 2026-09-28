<?php

namespace App\Repositories\Sprint;

use App\DTOs\Sprint\SprintCreateDTO;
use App\DTOs\Sprint\SprintUpdateDTO;
use App\Models\Project;
use App\Models\Sprint;
use Illuminate\Database\Eloquent\Collection;

class SprintRepository
{
    public function listForProject(Project $project): Collection
    {
        return $project->sprints()->withCount(['cards', 'doneCards'])->get();
    }

    public function findByUuid(string $uuid): ?Sprint
    {
        return Sprint::query()->with('project')->where('uuid', $uuid)->first();
    }

    public function findInProject(Project $project, string $uuid): ?Sprint
    {
        return $project->sprints()->where('uuid', $uuid)->first();
    }

    public function withCounts(Sprint $sprint): Sprint
    {
        return $sprint->loadCount(['cards', 'doneCards']);
    }

    public function create(Project $project, SprintCreateDTO $dto): Sprint
    {
        return $project->sprints()->create([
            ...$dto->toArray(),
            'status' => Sprint::STATUS_PLANNED,
            'position' => (int) $project->sprints()->max('position') + 1,
        ]);
    }

    public function update(Sprint $sprint, SprintUpdateDTO $dto): Sprint
    {
        $sprint->update($dto->toArray());

        return $sprint;
    }

    /** Só uma sprint ativa por projeto: as outras voltam a planejadas. */
    public function activate(Sprint $sprint): Sprint
    {
        $sprint->project->sprints()
            ->where('status', Sprint::STATUS_ACTIVE)
            ->whereKeyNot($sprint->id)
            ->update(['status' => Sprint::STATUS_PLANNED]);

        $sprint->update(['status' => Sprint::STATUS_ACTIVE, 'completed_at' => null]);

        return $sprint;
    }

    public function complete(Sprint $sprint): Sprint
    {
        $sprint->update(['status' => Sprint::STATUS_COMPLETED, 'completed_at' => now()]);

        return $sprint;
    }

    public function reopen(Sprint $sprint): Sprint
    {
        $sprint->update(['status' => Sprint::STATUS_PLANNED, 'completed_at' => null]);

        return $sprint;
    }

    /** Renumera na ordem enviada; sprints fora da lista vão para o fim. */
    public function reorder(Project $project, array $uuids): void
    {
        $idByUuid = $project->sprints()->pluck('id', 'uuid');
        $ordered = collect($uuids)->unique()->filter(fn ($u) => $idByUuid->has($u))->map(fn ($u) => (int) $idByUuid[$u])->values()->all();
        $rest = $project->sprints()->whereNotIn('id', $ordered)->orderBy('position')->orderBy('id')->pluck('id')->all();

        foreach ([...$ordered, ...$rest] as $position => $id) {
            $project->sprints()->whereKey($id)->update(['position' => $position]);
        }
    }

    public function delete(Sprint $sprint): void
    {
        $sprint->delete();
    }

    /** Cards da sprint para exportação (com coluna, responsável). */
    public function cardsForExport(Sprint $sprint): Collection
    {
        return $sprint->cards()
            ->with(['column', 'assignee'])
            ->join('board_columns', 'board_columns.id', '=', 'cards.board_column_id')
            ->orderBy('board_columns.position')
            ->orderBy('cards.position')
            ->select('cards.*')
            ->get();
    }
}
