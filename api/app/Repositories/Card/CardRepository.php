<?php

namespace App\Repositories\Card;

use App\DTOs\Card\CardFilterDTO;
use App\Models\BoardColumn;
use App\Models\Card;
use App\Models\Flag;
use App\Models\Project;
use App\Models\Sprint;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class CardRepository
{
    public const BOARD_RELATIONS = ['assignee', 'creator', 'flags', 'column', 'sprint'];

    public function findByUuid(string $uuid): ?Card
    {
        return Card::query()->with('project')->where('uuid', $uuid)->first();
    }

    public function loadForBoard(Card $card): Card
    {
        return $card->load(self::BOARD_RELATIONS)->loadCount(['comments', 'attachments']);
    }

    public function loadDetails(Card $card): Card
    {
        return $card->load([
            ...self::BOARD_RELATIONS,
            'comments.user',
            'attachments.user',
            'movements.fromColumn',
            'movements.toColumn',
            'movements.user',
        ])->loadCount(['comments', 'attachments']);
    }

    /**
     * @param  int|string|null  $sprintId  'all' | null (backlog) | id
     */
    public function list(Project $project, CardFilterDTO $filter, int|string|null $sprintId): Collection
    {
        return $project->cards()
            ->with(self::BOARD_RELATIONS)
            ->withCount(['comments', 'attachments'])
            ->when($sprintId !== 'all', fn ($q) => $q->when(
                $sprintId === null,
                fn ($s) => $s->whereNull('sprint_id'),
                fn ($s) => $s->where('sprint_id', $sprintId),
            ))
            ->when($filter->columnUuid, fn ($q, $v) => $q->whereHas('column', fn ($c) => $c->where('uuid', $v)))
            ->when($filter->assigneeUuid, fn ($q, $v) => $q->whereHas('assignee', fn ($a) => $a->where('uuid', $v)))
            ->when($filter->priority, fn ($q, $v) => $q->where('priority', $v))
            ->when($filter->flagUuid, fn ($q, $v) => $q->whereHas('flags', fn ($f) => $f->where('flags.uuid', $v)))
            ->when($filter->search, fn ($q, $v) => $q->where(function ($s) use ($v) {
                $s->where('title', 'like', "%{$v}%")->orWhere('description', 'like', "%{$v}%");
            }))
            ->join('board_columns', 'board_columns.id', '=', 'cards.board_column_id')
            ->orderBy('board_columns.position')
            ->orderBy('cards.position')
            ->select('cards.*')
            ->get();
    }

    /** Célula = coluna × sprint (null = backlog). */
    public function cell(int $columnId, ?int $sprintId): Builder
    {
        return Card::query()
            ->where('board_column_id', $columnId)
            ->when(
                $sprintId === null,
                fn ($q) => $q->whereNull('sprint_id'),
                fn ($q) => $q->where('sprint_id', $sprintId),
            );
    }

    public function nextPosition(int $columnId, ?int $sprintId): int
    {
        $max = $this->cell($columnId, $sprintId)->max('position');

        return $max === null ? 0 : (int) $max + 1;
    }

    public function countInColumn(int $columnId): int
    {
        return Card::query()->where('board_column_id', $columnId)->count();
    }

    public function create(Project $project, array $attributes): Card
    {
        return $project->cards()->create($attributes);
    }

    public function update(Card $card, array $attributes): Card
    {
        $card->update($attributes);

        return $card;
    }

    public function delete(Card $card): void
    {
        $card->delete();
    }

    /** @param  array<int, string>  $flagUuids */
    public function syncFlags(Card $card, array $flagUuids): void
    {
        $card->flags()->sync(Flag::query()->whereIn('uuid', $flagUuids)->pluck('id'));
    }

    public function recordMovement(Card $card, ?int $fromColumnId, int $toColumnId, ?int $userId): void
    {
        $card->movements()->create([
            'from_column_id' => $fromColumnId,
            'to_column_id' => $toColumnId,
            'user_id' => $userId,
            'created_at' => now(),
        ]);
    }

    /** Fecha o vão deixado numa célula (posições acima de `$position` descem 1). */
    public function closeGap(int $columnId, ?int $sprintId, int $position): void
    {
        $this->cell($columnId, $sprintId)->where('position', '>', $position)->decrement('position');
    }

    /** Abre espaço numa célula (posições a partir de `$position` sobem 1). */
    public function openGap(int $columnId, ?int $sprintId, int $position): void
    {
        $this->cell($columnId, $sprintId)->where('position', '>=', $position)->increment('position');
    }

    /** Reordenação dentro da mesma célula: desloca só o intervalo afetado. */
    public function shiftWithinCell(int $columnId, ?int $sprintId, int $from, int $to): void
    {
        if ($to > $from) {
            $this->cell($columnId, $sprintId)->whereBetween('position', [$from + 1, $to])->decrement('position');
        } elseif ($to < $from) {
            $this->cell($columnId, $sprintId)->whereBetween('position', [$to, $from - 1])->increment('position');
        }
    }

    /** Renumera (0..n) as células de uma sprint (ou do backlog) em todas as colunas do projeto. */
    public function reindexProjectCells(Project $project, ?int $sprintId): void
    {
        foreach ($project->columns()->pluck('id') as $columnId) {
            $ids = $this->cell($columnId, $sprintId)->orderBy('position')->orderBy('id')->pluck('id');

            foreach ($ids as $position => $id) {
                Card::query()->whereKey($id)->update(['position' => $position]);
            }
        }
    }

    /** Backlog → sprint, anexando ao fim de cada célula. Retorna o total movido. */
    public function assignBacklogToSprint(Sprint $sprint): int
    {
        $moved = 0;

        foreach ($sprint->project->columns()->pluck('id') as $columnId) {
            $next = $this->nextPosition($columnId, $sprint->id);
            $cards = $this->cell($columnId, null)->orderBy('position')->orderBy('id')->get();

            foreach ($cards as $card) {
                $card->update(['sprint_id' => $sprint->id, 'position' => $next++]);
                $moved++;
            }
        }

        return $moved;
    }

    /** Cards abertos (coluna sem is_done) da sprint → destino (null = backlog). */
    public function moveOpenCardsToSprint(Sprint $sprint, ?int $targetSprintId): int
    {
        $moved = 0;
        $columns = $sprint->project->columns()->where('is_done', false)->pluck('id');

        foreach ($columns as $columnId) {
            $next = $this->nextPosition($columnId, $targetSprintId);
            $cards = $this->cell($columnId, $sprint->id)->orderBy('position')->orderBy('id')->get();

            foreach ($cards as $card) {
                $card->update(['sprint_id' => $targetSprintId, 'position' => $next++]);
                $moved++;
            }
        }

        return $moved;
    }

    public function firstColumnId(Project $project): ?int
    {
        $id = $project->columns()->orderBy('position')->orderBy('id')->value('id');

        return $id ? (int) $id : null;
    }

    public function columnInProject(Project $project, string $uuid): ?BoardColumn
    {
        return $project->columns()->where('uuid', $uuid)->first();
    }

    public function sprintInProject(Project $project, string $uuid): ?Sprint
    {
        return $project->sprints()->where('uuid', $uuid)->first();
    }
}
