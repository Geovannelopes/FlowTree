<?php

namespace App\Repositories\BoardColumn;

use App\DTOs\BoardColumn\BoardColumnUpdateDTO;
use App\Models\BoardColumn;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;

class BoardColumnRepository
{
    /** Paleta de acento das colunas (FlowTree). */
    public const PALETTE = ['#64748b', '#f59e0b', '#3b82f6', '#8b5cf6', '#f97316', '#22c55e', '#0ea5e9', '#ec4899'];

    public function listForProject(Project $project): Collection
    {
        return $project->columns()->withCount('cards')->get();
    }

    public function findByUuid(string $uuid): ?BoardColumn
    {
        return BoardColumn::query()->with('project')->where('uuid', $uuid)->first();
    }

    public function findInProject(Project $project, string $uuid): ?BoardColumn
    {
        return $project->columns()->where('uuid', $uuid)->first();
    }

    public function count(Project $project): int
    {
        return $project->columns()->count();
    }

    public function create(Project $project, array $attributes): BoardColumn
    {
        $count = $this->count($project);

        return $project->columns()->create([
            ...$attributes,
            'color' => $attributes['color'] ?? self::PALETTE[$count % count(self::PALETTE)],
            'position' => (int) $project->columns()->max('position') + 1,
        ]);
    }

    public function update(BoardColumn $column, BoardColumnUpdateDTO $dto): BoardColumn
    {
        $column->update($dto->toArray());

        return $column;
    }

    /** Renumera na ordem enviada; colunas fora da lista vão para o fim. */
    public function reorder(Project $project, array $uuids): void
    {
        $idByUuid = $project->columns()->pluck('id', 'uuid');
        $ordered = collect($uuids)->unique()->filter(fn ($u) => $idByUuid->has($u))->map(fn ($u) => (int) $idByUuid[$u])->values()->all();
        $rest = $project->columns()->whereNotIn('id', $ordered)->orderBy('position')->orderBy('id')->pluck('id')->all();

        foreach ([...$ordered, ...$rest] as $position => $id) {
            $project->columns()->whereKey($id)->update(['position' => $position]);
        }
    }

    public function delete(BoardColumn $column): void
    {
        $column->cards()->delete(); // soft delete dos cards
        $column->delete();
    }
}
