<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_metricas_do_projeto_e_por_sprint(): void
    {
        $user = $this->actingAsUserWith(['tasks']);
        $project = $this->makeProject($user);
        $backlog = $this->column($project, 'Backlog');
        $done = $this->column($project, 'Concluído');
        $sprint = $project->sprints()->create(['name' => 'S1', 'status' => 'active']);

        // Concluído em 2 dias de ciclo (started → completed) e 4 de lead (created → completed).
        $card = $project->cards()->create([
            'board_column_id' => $done->id, 'sprint_id' => $sprint->id, 'title' => 'Feito', 'position' => 0,
            'assignee_id' => $user->id, 'started_at' => now()->subDays(3), 'completed_at' => now()->subDay(),
        ]);
        $card->forceFill(['created_at' => now()->subDays(5)])->save();

        $project->cards()->create(['board_column_id' => $backlog->id, 'sprint_id' => $sprint->id, 'title' => 'Atrasado', 'position' => 0, 'due_date' => now()->subDays(2)->toDateString()]);
        $project->cards()->create(['board_column_id' => $backlog->id, 'title' => 'No backlog', 'position' => 1, 'priority' => 'high']);

        $all = $this->getJson("/api/projects/{$project->uuid}/metrics")->assertOk()->json('data');
        $this->assertSame('all', $all['scope']['sprint']);
        $this->assertSame(3, $all['cards_total']);
        $this->assertSame(1, $all['cards_done']);
        $this->assertSame(2, $all['cards_open']);
        $this->assertSame(1, $all['cards_overdue']);
        $this->assertSame(1, $all['throughput']);
        $this->assertEqualsWithDelta(2.0, $all['avg_cycle_time_days'], 0.1);
        $this->assertEqualsWithDelta(4.0, $all['avg_lead_time_days'], 0.1);
        $this->assertCount(6, $all['by_column']);
        $this->assertSame(2, collect($all['by_column'])->firstWhere('name', 'Backlog')['count']);
        $this->assertSame(1, collect($all['by_priority'])->firstWhere('slug', 'high')['count']);
        $this->assertCount(30, $all['completed_by_day']);
        $this->assertSame(1, collect($all['completed_by_day'])->sum('count'));
        $this->assertSame($user->uuid, collect($all['by_assignee'])->firstWhere('done', 1)['user']['uuid']);

        $scoped = $this->getJson("/api/projects/{$project->uuid}/metrics?sprint={$sprint->uuid}")->assertOk()->json('data');
        $this->assertSame($sprint->uuid, $scoped['scope']['sprint']);
        $this->assertSame(2, $scoped['cards_total']);

        $backlogOnly = $this->getJson("/api/projects/{$project->uuid}/metrics?sprint=backlog")->assertOk()->json('data');
        $this->assertSame(1, $backlogOnly['cards_total']);
        $this->assertNull($backlogOnly['avg_cycle_time_days']);
    }
}
