<?php

namespace Tests\Feature;

use App\Models\BoardColumn;
use App\Models\Card;
use App\Models\Flag;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CardTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Project $project;

    private BoardColumn $backlog;

    private BoardColumn $pending;

    private BoardColumn $done;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->actingAsUserWith(['tasks']);
        $this->project = $this->makeProject($this->user);
        $this->backlog = $this->column($this->project, 'Backlog');
        $this->pending = $this->column($this->project, 'Pendente');
        $this->done = $this->column($this->project, 'Concluído');
    }

    private function card(BoardColumn $column, int $position, string $title, ?int $sprintId = null): Card
    {
        return $this->project->cards()->create([
            'board_column_id' => $column->id,
            'sprint_id' => $sprintId,
            'title' => $title,
            'position' => $position,
        ]);
    }

    public function test_cria_card_no_fim_da_celula_e_registra_movimento(): void
    {
        $this->card($this->backlog, 0, 'Existente');

        $response = $this->postJson("/api/projects/{$this->project->uuid}/cards", [
            'title' => 'Novo card',
            'board_column_uuid' => $this->backlog->uuid,
            'priority' => 'high',
            'due_date' => '2030-01-01',
        ])->assertCreated()
            ->assertJsonPath('data.position', 1)
            ->assertJsonPath('data.priority', 'high')
            ->assertJsonPath('data.board_column_uuid', $this->backlog->uuid)
            ->assertJsonPath('data.creator.uuid', $this->user->uuid);

        $card = Card::where('uuid', $response->json('data.uuid'))->first();
        $this->assertNull($card->started_at);
        $this->assertCount(1, $card->movements);
        $this->assertNull($card->movements->first()->from_column_id);

        $this->postJson("/api/projects/{$this->project->uuid}/cards", ['title' => 'X', 'board_column_uuid' => $this->backlog->uuid, 'priority' => 'nope'])
            ->assertStatus(422)->assertJsonValidationErrorFor('priority');
    }

    public function test_reordena_dentro_da_mesma_celula_sem_colisao(): void
    {
        $a = $this->card($this->backlog, 0, 'A');
        $b = $this->card($this->backlog, 1, 'B');
        $c = $this->card($this->backlog, 2, 'C');

        $this->patchJson("/api/cards/{$a->uuid}/move", ['board_column_uuid' => $this->backlog->uuid, 'position' => 2])->assertOk();

        $this->assertSame(2, $a->fresh()->position);
        $this->assertSame(0, $b->fresh()->position);
        $this->assertSame(1, $c->fresh()->position);
        $this->assertSame([0, 1, 2], $this->backlog->cards()->pluck('position')->sort()->values()->all());
    }

    public function test_move_entre_colunas_fecha_vao_abre_espaco_e_marca_started_at(): void
    {
        $a = $this->card($this->backlog, 0, 'A');
        $b = $this->card($this->backlog, 1, 'B');
        $x = $this->card($this->pending, 0, 'X');

        $this->patchJson("/api/cards/{$a->uuid}/move", ['board_column_uuid' => $this->pending->uuid, 'position' => 0])->assertOk();

        $this->assertSame(0, $b->fresh()->position);
        $this->assertSame(0, $a->fresh()->position);
        $this->assertSame(1, $x->fresh()->position);
        $this->assertNotNull($a->fresh()->started_at);
        $this->assertCount(1, $a->fresh()->movements);
        $this->assertSame($this->pending->id, $a->fresh()->movements->first()->to_column_id);
    }

    public function test_respeita_o_limite_de_wip_ao_entrar_na_coluna(): void
    {
        $this->pending->update(['wip_limit' => 1]);
        $this->card($this->pending, 0, 'X');
        $a = $this->card($this->backlog, 0, 'A');

        $this->patchJson("/api/cards/{$a->uuid}/move", ['board_column_uuid' => $this->pending->uuid, 'position' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('board_column_uuid');

        $this->assertSame($this->backlog->id, $a->fresh()->board_column_id);

        // Criar direto na coluna cheia também é barrado.
        $this->postJson("/api/projects/{$this->project->uuid}/cards", ['title' => 'Y', 'board_column_uuid' => $this->pending->uuid])
            ->assertStatus(422)->assertJsonValidationErrorFor('board_column_uuid');
    }

    public function test_move_entre_celulas_troca_a_sprint_e_reindexa(): void
    {
        $sprint = $this->project->sprints()->create(['name' => 'Sprint 1', 'status' => 'active']);
        $a = $this->card($this->backlog, 0, 'A');
        $b = $this->card($this->backlog, 1, 'B');
        $s = $this->card($this->backlog, 0, 'S', $sprint->id);

        $this->patchJson("/api/cards/{$a->uuid}/move", [
            'board_column_uuid' => $this->backlog->uuid,
            'sprint_uuid' => $sprint->uuid,
            'position' => 0,
        ])->assertOk()->assertJsonPath('data.sprint_uuid', $sprint->uuid);

        $this->assertSame($sprint->id, $a->fresh()->sprint_id);
        $this->assertSame(0, $a->fresh()->position);
        $this->assertSame(1, $s->fresh()->position);
        $this->assertNull($b->fresh()->sprint_id);
        $this->assertSame(0, $b->fresh()->position);
        // Mesma coluna: sem movimento registrado.
        $this->assertCount(0, $a->fresh()->movements);
    }

    public function test_trocar_de_sprint_na_mesma_coluna_nao_dispara_wip(): void
    {
        $this->backlog->update(['wip_limit' => 1]);
        $sprint = $this->project->sprints()->create(['name' => 'Sprint 1']);
        $a = $this->card($this->backlog, 0, 'A');

        $this->patchJson("/api/cards/{$a->uuid}/move", ['board_column_uuid' => $this->backlog->uuid, 'sprint_uuid' => $sprint->uuid, 'position' => 0])
            ->assertOk();

        $this->assertSame($sprint->id, $a->fresh()->sprint_id);
    }

    public function test_entrar_em_coluna_concluida_preenche_completed_at_e_sair_limpa(): void
    {
        $a = $this->card($this->backlog, 0, 'A');

        $this->patchJson("/api/cards/{$a->uuid}/move", ['board_column_uuid' => $this->done->uuid, 'position' => 0])->assertOk();
        $this->assertNotNull($a->fresh()->completed_at);
        $this->assertNotNull($a->fresh()->started_at);

        $this->patchJson("/api/cards/{$a->uuid}/move", ['board_column_uuid' => $this->pending->uuid, 'position' => 0])->assertOk();
        $this->assertNull($a->fresh()->completed_at);
        $this->assertCount(2, $a->fresh()->movements);
    }

    public function test_update_troca_coluna_e_flags_e_notifica_responsavel(): void
    {
        $other = User::factory()->create();
        $flag = Flag::create(['name' => 'Bug', 'slug' => 'bug', 'color' => '#a24d3f']);
        $a = $this->card($this->backlog, 0, 'A');
        $this->card($this->pending, 0, 'X');

        $this->putJson("/api/cards/{$a->uuid}", [
            'title' => 'A editado',
            'board_column_uuid' => $this->pending->uuid,
            'assignee_uuid' => $other->uuid,
            'flag_uuids' => [$flag->uuid],
        ])->assertOk()
            ->assertJsonPath('data.title', 'A editado')
            ->assertJsonPath('data.position', 1)
            ->assertJsonPath('data.assignee.uuid', $other->uuid)
            ->assertJsonPath('data.flags.0.slug', 'bug');

        $this->assertSame(1, $other->notifications()->count());
        $this->assertSame('card_assigned', $other->notifications()->first()->data['type']);

        // Detalhe traz comentários, anexos e histórico.
        $this->getJson("/api/cards/{$a->uuid}")->assertOk()
            ->assertJsonStructure(['data' => ['comments', 'attachments', 'movements']])
            ->assertJsonCount(1, 'data.movements');
    }

    public function test_excluir_card_fecha_o_vao(): void
    {
        $a = $this->card($this->backlog, 0, 'A');
        $b = $this->card($this->backlog, 1, 'B');

        $this->deleteJson("/api/cards/{$a->uuid}")->assertNoContent();

        $this->assertSoftDeleted($a);
        $this->assertSame(0, $b->fresh()->position);
    }

    public function test_listagem_de_cards_com_filtros(): void
    {
        $sprint = $this->project->sprints()->create(['name' => 'Sprint 1']);
        $this->card($this->backlog, 0, 'Um bug feio');
        $this->card($this->pending, 0, 'Outro', $sprint->id);

        $this->getJson("/api/projects/{$this->project->uuid}/cards")->assertOk()->assertJsonCount(2, 'data');
        $this->getJson("/api/projects/{$this->project->uuid}/cards?sprint=backlog")->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/projects/{$this->project->uuid}/cards?search=bug")->assertOk()->assertJsonPath('data.0.title', 'Um bug feio');
        $this->getJson("/api/projects/{$this->project->uuid}/cards?column={$this->pending->uuid}")->assertOk()->assertJsonPath('data.0.title', 'Outro');
    }
}
