<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SprintTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->actingAsUserWith(['tasks'], ['name' => 'Ana Silva']);
        $this->project = $this->makeProject($this->user);
    }

    public function test_cria_e_valida_datas(): void
    {
        $this->postJson("/api/projects/{$this->project->uuid}/sprints", ['name' => 'Sprint 1', 'starts_at' => '2030-01-10', 'ends_at' => '2030-01-01'])
            ->assertStatus(422)->assertJsonValidationErrorFor('ends_at');

        $this->postJson("/api/projects/{$this->project->uuid}/sprints", ['name' => 'Sprint 1', 'goal' => 'Entregar', 'starts_at' => '2030-01-01', 'ends_at' => '2030-01-10'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'planned')
            ->assertJsonPath('data.position', 1)
            ->assertJsonPath('data.starts_at', '2030-01-01');
    }

    public function test_so_uma_sprint_ativa_por_projeto(): void
    {
        $s1 = $this->project->sprints()->create(['name' => 'S1', 'status' => 'active']);
        $s2 = $this->project->sprints()->create(['name' => 'S2']);

        $this->postJson("/api/sprints/{$s2->uuid}/activate")->assertOk()->assertJsonPath('data.status', 'active');

        $this->assertSame('planned', $s1->fresh()->status);
        $this->assertSame('active', $s2->fresh()->status);
    }

    public function test_assign_backlog_leva_todos_os_cards_sem_sprint(): void
    {
        $backlog = $this->column($this->project, 'Backlog');
        $pending = $this->column($this->project, 'Pendente');
        $sprint = $this->project->sprints()->create(['name' => 'S1', 'status' => 'active']);

        $this->project->cards()->create(['board_column_id' => $backlog->id, 'title' => 'A', 'position' => 0]);
        $this->project->cards()->create(['board_column_id' => $backlog->id, 'title' => 'B', 'position' => 1]);
        $this->project->cards()->create(['board_column_id' => $pending->id, 'title' => 'C', 'position' => 0]);
        $already = $this->project->cards()->create(['board_column_id' => $backlog->id, 'sprint_id' => $sprint->id, 'title' => 'S', 'position' => 0]);

        $this->postJson("/api/sprints/{$sprint->uuid}/assign-backlog")
            ->assertOk()
            ->assertJsonPath('assigned', 3)
            ->assertJsonPath('data.cards_count', 4);

        $this->assertSame(0, $this->project->cards()->whereNull('sprint_id')->count());
        // Os novos entram depois dos que já estavam na célula.
        $this->assertSame([0, 1, 2], $backlog->cards()->where('sprint_id', $sprint->id)->pluck('position')->all());
        $this->assertSame(0, $already->fresh()->position);
    }

    public function test_complete_move_cards_abertos_para_o_backlog_ou_outra_sprint(): void
    {
        $pending = $this->column($this->project, 'Pendente');
        $done = $this->column($this->project, 'Concluído');
        $s1 = $this->project->sprints()->create(['name' => 'S1', 'status' => 'active']);
        $s2 = $this->project->sprints()->create(['name' => 'S2']);

        $open = $this->project->cards()->create(['board_column_id' => $pending->id, 'sprint_id' => $s1->id, 'title' => 'Aberto', 'position' => 0]);
        $finished = $this->project->cards()->create(['board_column_id' => $done->id, 'sprint_id' => $s1->id, 'title' => 'Feito', 'position' => 0, 'completed_at' => now()]);

        $this->postJson("/api/sprints/{$s1->uuid}/complete", ['move_open_to' => $s2->uuid])
            ->assertOk()->assertJsonPath('data.status', 'completed');

        $this->assertSame($s2->id, $open->fresh()->sprint_id);
        $this->assertSame($s1->id, $finished->fresh()->sprint_id);
        $this->assertNotNull($s1->fresh()->completed_at);

        // Reabrir e concluir mandando para o backlog.
        $this->postJson("/api/sprints/{$s1->uuid}/reopen")->assertOk()->assertJsonPath('data.status', 'planned');
        $open->fresh()->update(['sprint_id' => $s1->id]);
        $this->postJson("/api/sprints/{$s1->uuid}/complete", ['move_open_to' => 'backlog'])->assertOk();
        $this->assertNull($open->fresh()->sprint_id);

        $this->postJson("/api/sprints/{$s1->uuid}/complete", ['move_open_to' => 'invalido'])->assertStatus(422);
    }

    public function test_excluir_sprint_devolve_cards_ao_backlog_sem_colisao(): void
    {
        $backlog = $this->column($this->project, 'Backlog');
        $sprint = $this->project->sprints()->create(['name' => 'S1']);

        $b0 = $this->project->cards()->create(['board_column_id' => $backlog->id, 'title' => 'B0', 'position' => 0]);
        $s0 = $this->project->cards()->create(['board_column_id' => $backlog->id, 'sprint_id' => $sprint->id, 'title' => 'S0', 'position' => 0]);

        $this->deleteJson("/api/sprints/{$sprint->uuid}")->assertNoContent();

        $this->assertNull($s0->fresh()->sprint_id);
        $positions = $backlog->cards()->pluck('position')->sort()->values()->all();
        $this->assertSame([0, 1], $positions);
        $this->assertSame(0, $b0->fresh()->position);
    }

    public function test_exporta_csv_da_sprint(): void
    {
        $pending = $this->column($this->project, 'Pendente');
        $sprint = $this->project->sprints()->create(['name' => 'Sprint Um']);
        $this->project->cards()->create(['board_column_id' => $pending->id, 'sprint_id' => $sprint->id, 'title' => 'Card CSV', 'position' => 0, 'assignee_id' => $this->user->id, 'due_date' => '2030-02-02']);

        $response = $this->get("/api/sprints/{$sprint->uuid}/export")->assertOk();
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
        $this->assertStringContainsString('sprint-sprint-um.csv', $response->headers->get('content-disposition'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('uuid;titulo;coluna;prioridade;responsavel;prazo;concluido_em', $content);
        $this->assertStringContainsString('"Card CSV";Pendente;medium;"Ana Silva";2030-02-02', $content);
    }

    public function test_reordena_sprints(): void
    {
        $s1 = $this->project->sprints()->create(['name' => 'S1', 'position' => 0]);
        $s2 = $this->project->sprints()->create(['name' => 'S2', 'position' => 1]);

        $this->patchJson("/api/projects/{$this->project->uuid}/sprints/reorder", ['sprint_uuids' => [$s2->uuid]])
            ->assertOk()->assertJsonPath('data.0.uuid', $s2->uuid)->assertJsonPath('data.1.uuid', $s1->uuid);
    }
}
