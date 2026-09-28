<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTest extends TestCase
{
    use RefreshDatabase;

    public function test_criar_projeto_gera_colunas_padrao_e_dono(): void
    {
        $user = $this->actingAsUserWith(['tasks']);

        $response = $this->postJson('/api/projects', ['name' => 'Meu projeto', 'color' => '#3f6650'])
            ->assertCreated()
            ->assertJsonPath('data.owner.uuid', $user->uuid)
            ->assertJsonCount(6, 'data.columns');

        $columns = collect($response->json('data.columns'));
        $this->assertSame('Backlog', $columns->first()['name']);
        $this->assertTrue($columns->last()['is_done']);
        $this->assertSame('Concluído', $columns->last()['name']);
    }

    public function test_board_filtra_por_sprint(): void
    {
        $user = $this->actingAsUserWith(['tasks']);
        $project = $this->makeProject($user);
        $backlog = $this->column($project, 'Backlog');
        $sprint = $project->sprints()->create(['name' => 'Sprint 1', 'status' => 'active']);

        $project->cards()->create(['board_column_id' => $backlog->id, 'title' => 'No backlog', 'position' => 0]);
        $project->cards()->create(['board_column_id' => $backlog->id, 'sprint_id' => $sprint->id, 'title' => 'Na sprint', 'position' => 0]);

        $all = $this->getJson("/api/projects/{$project->uuid}/board")->assertOk();
        $this->assertSame('all', $all->json('sprint'));
        $this->assertCount(2, $all->json('data.columns.0.cards'));
        $this->assertCount(1, $all->json('sprints'));

        $only = $this->getJson("/api/projects/{$project->uuid}/board?sprint={$sprint->uuid}")->assertOk();
        $this->assertSame($sprint->uuid, $only->json('sprint'));
        $this->assertCount(1, $only->json('data.columns.0.cards'));
        $this->assertSame('Na sprint', $only->json('data.columns.0.cards.0.title'));

        $backlogOnly = $this->getJson("/api/projects/{$project->uuid}/board?sprint=backlog")->assertOk();
        $this->assertSame('backlog', $backlogOnly->json('sprint'));
        $this->assertSame('No backlog', $backlogOnly->json('data.columns.0.cards.0.title'));

        $this->getJson("/api/projects/{$project->uuid}/board?sprint=nao-existe")->assertStatus(404);
    }

    public function test_listagem_separa_arquivados(): void
    {
        $user = $this->actingAsUserWith(['tasks']);
        $active = $this->makeProject($user, 'Ativo');
        $archived = $this->makeProject($user, 'Arquivado');
        $archived->update(['is_archived' => true]);

        $this->getJson('/api/projects')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.uuid', $active->uuid);
        $this->getJson('/api/projects?archived=1')->assertOk()->assertJsonPath('data.0.uuid', $archived->uuid);

        $this->putJson("/api/projects/{$active->uuid}", ['is_archived' => true])->assertOk()->assertJsonPath('data.is_archived', true);
    }

    public function test_reordena_colunas_e_impede_excluir_a_ultima(): void
    {
        $user = $this->actingAsUserWith(['tasks']);
        $project = $this->makeProject($user);
        $uuids = $project->columns()->pluck('uuid')->reverse()->values()->all();

        $response = $this->patchJson("/api/projects/{$project->uuid}/columns/reorder", ['column_uuids' => $uuids])->assertOk();
        $this->assertSame($uuids, collect($response->json('data'))->pluck('uuid')->all());

        $project->columns()->where('position', '>', 0)->get()->each(fn ($c) => $this->deleteJson("/api/columns/{$c->uuid}")->assertNoContent());
        $last = $project->columns()->first();
        $this->deleteJson("/api/columns/{$last->uuid}")->assertStatus(422);
    }
}
