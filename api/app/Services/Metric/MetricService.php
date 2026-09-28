<?php

namespace App\Services\Metric;

use App\Http\Resources\UserResource;
use App\Models\Card;
use App\Models\Priority;
use App\Models\Project;
use App\Services\Project\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class MetricService
{
    public function __construct(
        private ProjectService $projectService,
    ) {}

    public function forProject(string $projectUuid, ?string $sprint): JsonResponse
    {
        $project = $this->projectService->find($projectUuid);
        $sprintId = $this->projectService->resolveSprintScope($project, $sprint);

        $cards = $project->cards()
            ->with(['assignee', 'column'])
            ->when($sprintId !== 'all', fn ($q) => $q->when(
                $sprintId === null,
                fn ($s) => $s->whereNull('sprint_id'),
                fn ($s) => $s->where('sprint_id', $sprintId),
            ))
            ->get();

        $done = $cards->filter(fn (Card $c) => $c->completed_at !== null);
        $today = now()->startOfDay();

        $scope = match (true) {
            $sprintId === 'all' => 'all',
            $sprintId === null => 'backlog',
            default => $project->sprints()->whereKey($sprintId)->value('uuid'),
        };

        return response()->json(['data' => [
            'scope' => ['sprint' => $scope],
            'cards_total' => $cards->count(),
            'cards_done' => $done->count(),
            'cards_open' => $cards->count() - $done->count(),
            'cards_overdue' => $cards->filter(fn (Card $c) => $c->is_overdue)->count(),
            'throughput' => $done->count(),
            'avg_cycle_time_days' => $this->averageDays($done, fn (Card $c) => $c->started_at),
            'avg_lead_time_days' => $this->averageDays($done, fn (Card $c) => $c->created_at),
            'by_column' => $this->byColumn($project, $cards),
            'by_priority' => $this->byPriority($cards),
            'by_assignee' => $this->byAssignee($cards),
            'completed_by_day' => $this->completedByDay($done, $today),
        ]]);
    }

    /** Média em dias (1 casa) entre `$start($card)` e `completed_at`; null sem amostra. */
    private function averageDays(Collection $done, callable $start): ?float
    {
        $samples = $done
            ->map(function (Card $card) use ($start) {
                $from = $start($card);

                return $from ? max(0, $from->diffInSeconds($card->completed_at)) / 86400 : null;
            })
            ->filter(fn ($v) => $v !== null);

        return $samples->isEmpty() ? null : round($samples->avg(), 1);
    }

    private function byColumn(Project $project, Collection $cards): array
    {
        return $project->columns()->get()->map(fn ($column) => [
            'uuid' => $column->uuid,
            'name' => $column->name,
            'color' => $column->color,
            'is_done' => (bool) $column->is_done,
            'count' => $cards->where('board_column_id', $column->id)->count(),
        ])->values()->all();
    }

    private function byPriority(Collection $cards): array
    {
        return Priority::query()->orderBy('position')->get()->map(fn ($priority) => [
            'slug' => $priority->slug,
            'name' => $priority->name,
            'color' => $priority->color,
            'count' => $cards->where('priority', $priority->slug)->count(),
        ])->values()->all();
    }

    private function byAssignee(Collection $cards): array
    {
        return $cards
            ->groupBy(fn (Card $c) => $c->assignee_id ?? 0)
            ->map(fn (Collection $group, $key) => [
                'user' => $key ? (new UserResource($group->first()->assignee))->resolve() : null,
                'count' => $group->count(),
                'done' => $group->filter(fn (Card $c) => $c->completed_at !== null)->count(),
            ])
            ->sortByDesc('count')
            ->values()
            ->all();
    }

    private function completedByDay(Collection $done, Carbon $today): array
    {
        $counts = $done
            ->groupBy(fn (Card $c) => $c->completed_at->toDateString())
            ->map->count();

        $days = [];

        for ($i = 29; $i >= 0; $i--) {
            $date = $today->copy()->subDays($i)->toDateString();
            $days[] = ['date' => $date, 'count' => (int) ($counts[$date] ?? 0)];
        }

        return $days;
    }
}
